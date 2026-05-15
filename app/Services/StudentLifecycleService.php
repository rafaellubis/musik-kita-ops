<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentStatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pusat logika transisi status murid (M02).
 *
 * Aturan emas:
 *   - SEMUA perubahan status murid harus lewat service ini, jangan
 *     update kolom $student->status langsung di controller.
 *   - Setiap method jalankan validasi state machine + simpan history
 *     di satu transaksi DB. Kalau salah satu gagal, semua di-rollback.
 *
 * State machine (sumber: CLAUDE.md "Transisi Valid"):
 *   Calon              -> Trial | Aktif (skip trial, wajib reason)
 *   Trial              -> Aktif | Mengundurkan Diri
 *   Aktif              -> Cuti | Mengundurkan Diri | Selesai (Kids Class)
 *   Cuti               -> Aktif | Cuti (perpanjang) | Mengundurkan Diri
 *   Selesai            -> Aktif (re-enroll, TANPA registrasi ulang)
 *   Mengundurkan Diri  -> Aktif (re-aktivasi, BAYAR registrasi Rp 250rb)
 *
 * Catatan tagihan:
 *   Modul tagihan (M05) belum ada. Untuk sekarang setiap transisi yang
 *   memunculkan tagihan (Aktif/Cuti/re-aktif) cuma menulis flag
 *   `pending_invoices` di metadata. Saat M05 tersedia, generator tagihan
 *   tinggal baca flag itu dan bikin invoice yang sesuai.
 */
class StudentLifecycleService
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * Map transisi yang DIIZINKAN. Format: status_asal => [status_tujuan, ...].
     */
    private const VALID_TRANSITIONS = [
        'Calon'             => ['Trial', 'Aktif'],
        'Trial'             => ['Aktif', 'Mengundurkan Diri'],
        'Aktif'             => ['Cuti', 'Mengundurkan Diri', 'Selesai'],
        'Cuti'              => ['Aktif', 'Cuti', 'Mengundurkan Diri'],
        'Selesai'           => ['Aktif'],
        'Mengundurkan Diri' => ['Aktif'],
    ];

    // =================================================================
    // PUBLIC: 7 Transisi Lifecycle
    // =================================================================

    /**
     * Calon -> Trial. Jadwalkan trial 30 menit (BR-1.3).
     *
     * @param array{
     *     trial_date: string,
     *     package_id?: int,
     *     assigned_teacher_id?: int,
     *     assigned_room_id?: int,
     *     notes?: string|null,
     * } $data
     */
    public function mulaiTrial(Student $student, array $data): Student
    {
        $this->ensureTransition($student, 'Trial');

        return DB::transaction(function () use ($student, $data) {
            $from = $student->status;

            $student->update([
                'status' => 'Trial',
                'trial_date' => $data['trial_date'],
                'package_id' => $data['package_id'] ?? $student->package_id,
                'assigned_teacher_id' => $data['assigned_teacher_id'] ?? $student->assigned_teacher_id,
                'assigned_room_id' => $data['assigned_room_id'] ?? $student->assigned_room_id,
            ]);

            $this->recordHistory(
                student:  $student,
                from:     $from,
                to:       'Trial',
                reason:   $data['notes'] ?? null,
                metadata: [
                    'trial_date' => $data['trial_date'],
                ],
            );

            return $student->fresh();
        });
    }

    /**
     * Trial -> Aktif. Murid lanjut daftar penuh setelah trial sukses.
     * Auto-flag pending invoice REG (Rp 250rb) + SPP bulan pertama (BR-1.5, BR-1.8).
     *
     * @param array{
     *     package_id: int,
     *     assigned_teacher_id: int,
     *     assigned_room_id?: int,
     *     notes?: string|null,
     * } $data
     */
    public function konversiAktif(Student $student, array $data): Student
    {
        $this->ensureTransition($student, 'Aktif');

        return DB::transaction(function () use ($student, $data) {
            $from = $student->status;

            $student->update([
                'status' => 'Aktif',
                'package_id' => $data['package_id'],
                'assigned_teacher_id' => $data['assigned_teacher_id'],
                'assigned_room_id' => $data['assigned_room_id'] ?? $student->assigned_room_id,
                'active_since' => now()->toDateString(),
            ]);

            // Bikin enrollment ACTIVE — sumber kebenaran untuk M03 (jadwal/sesi).
            $enrollment = $this->openEnrollment(
                $student,
                $data['package_id'],
                $data['assigned_teacher_id'],
            );

            // Terbitkan invoice REG + SPP bulan ini (BR-1.5, BR-1.8).
            // payment_mode: FULL atau INSTALLMENT, relevan untuk KIDS_CLASS_BUNDLE.
            $invoice = $this->issueActivationInvoice(
                $student, $enrollment,
                includeReg: true,
                paymentMode: $data['payment_mode'] ?? 'FULL',
            );

            $this->recordHistory(
                student:  $student,
                from:     $from,
                to:       'Aktif',
                reason:   $data['notes'] ?? 'Konversi dari Trial',
                metadata: [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ],
            );

            return $student->fresh();
        });
    }

    /**
     * Calon -> Aktif (Hybrid skip trial). Wajib isi reason + reason_code (BR Hybrid v1.1).
     *
     * @param array{
     *     reason_code: string,    // walk_in | migrasi | reaktivasi | lulus_kids
     *     reason: string,
     *     package_id: int,
     *     assigned_teacher_id: int,
     *     assigned_room_id?: int,
     * } $data
     */
    public function skipTrial(Student $student, array $data): Student
    {
        if ($student->status !== 'Calon') {
            throw new InvalidArgumentException(
                'Skip trial hanya bisa dari status Calon. Status sekarang: ' . $student->status
            );
        }

        $allowedReasons = ['walk_in', 'migrasi', 'reaktivasi', 'lulus_kids'];
        if (!in_array($data['reason_code'], $allowedReasons, true)) {
            throw new InvalidArgumentException(
                'Reason code tidak valid. Pilih: ' . implode(', ', $allowedReasons)
            );
        }

        return DB::transaction(function () use ($student, $data) {
            $from = $student->status;

            $student->update([
                'status' => 'Aktif',
                'package_id' => $data['package_id'],
                'assigned_teacher_id' => $data['assigned_teacher_id'],
                'assigned_room_id' => $data['assigned_room_id'] ?? null,
                'active_since' => now()->toDateString(),
            ]);

            // Bikin enrollment ACTIVE — sumber kebenaran untuk M03 (jadwal/sesi).
            $enrollment = $this->openEnrollment(
                $student,
                $data['package_id'],
                $data['assigned_teacher_id'],
            );

            // Terbitkan invoice. Lulus Kids Class re-enroll TIDAK perlu REG (BR-10.7).
            $includeReg = $data['reason_code'] !== 'lulus_kids';
            $invoice = $this->issueActivationInvoice(
                $student, $enrollment,
                includeReg: $includeReg,
                paymentMode: $data['payment_mode'] ?? 'FULL',
            );

            $this->recordHistory(
                student:      $student,
                from:         $from,
                to:           'Aktif',
                reason:       $data['reason'],
                skippedTrial: true,
                metadata:     [
                    'reason_code'    => $data['reason_code'],
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ],
            );

            return $student->fresh();
        });
    }

    /**
     * Aktif -> Cuti. Berbayar Rp 100rb/pengajuan, maks 1 bulan + perpanjang 1x (BR-9).
     * Method ini juga handle perpanjang cuti (Cuti -> Cuti).
     *
     * Guard (Gap 3): Blok pengajuan cuti jika ada invoice SPP UNPAID/PARTIAL di bulan berjalan.
     *
     * @param array{
     *     cuti_from: string,
     *     cuti_until: string,
     *     reason: string,
     *     is_extension?: bool,
     * } $data
     */
    public function ajukanCuti(Student $student, array $data): Student
    {
        if (!in_array($student->status, ['Aktif', 'Cuti'], true)) {
            throw new InvalidArgumentException(
                'Cuti hanya bisa dari Aktif atau perpanjangan dari Cuti. Status sekarang: ' . $student->status
            );
        }

        // Guard: blok cuti jika SPP bulan berjalan belum dibayar (Gap 3)
        $unpaidSppCurrentMonth = $student->invoices()
            ->whereIn('status', ['UNPAID', 'PARTIAL'])
            ->whereHas('items', fn ($q) => $q->where('item_code', 'SPP'))
            ->exists();

        if ($unpaidSppCurrentMonth) {
            throw new InvalidArgumentException(
                'Selesaikan tagihan SPP bulan berjalan sebelum mengajukan cuti.'
            );
        }

        $isExtension = $student->status === 'Cuti';

        return DB::transaction(function () use ($student, $data, $isExtension) {
            $from = $student->status;

            $student->update(['status' => 'Cuti']);

            // Terbitkan invoice biaya cuti Rp 100.000 (BR-9)
            $invoice = $this->invoiceService->createOneOff(
                student: $student,
                items: [[
                    'code'        => 'CUTI',
                    'description' => "Biaya cuti " .
                        \Carbon\Carbon::parse($data['cuti_from'])->format('d M') . " - " .
                        \Carbon\Carbon::parse($data['cuti_until'])->format('d M Y') .
                        ($isExtension ? ' (perpanjangan)' : ''),
                    'amount'      => InvoiceService::FEE_CUTI,
                    'metadata'    => [
                        'cuti_from'  => $data['cuti_from'],
                        'cuti_until' => $data['cuti_until'],
                        'is_extension' => $isExtension,
                    ],
                ]],
                description: $isExtension ? 'Perpanjangan Cuti' : 'Pengajuan Cuti',
            );

            $this->recordHistory(
                student:  $student,
                from:     $from,
                to:       'Cuti',
                reason:   $data['reason'],
                metadata: [
                    'cuti_from'      => $data['cuti_from'],
                    'cuti_until'     => $data['cuti_until'],
                    'is_extension'   => $isExtension,
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ],
            );

            return $student->fresh();
        });
    }

    /**
     * Mundur dari studio. Bisa dari status apa saja kecuali Selesai/sudah Mundur.
     *
     * @param array{
     *     reason: string,
     *     auto?: bool,  // true kalau dipicu cron auto-mundur
     * } $data
     */
    public function mundurkan(Student $student, array $data): Student
    {
        $this->ensureTransition($student, 'Mengundurkan Diri');

        return DB::transaction(function () use ($student, $data) {
            $from = $student->status;

            $student->update(['status' => 'Mengundurkan Diri']);

            // Tutup enrollment aktif: INACTIVE + end_date = today.
            $this->closeActiveEnrollments($student, status: 'INACTIVE');

            // Cancel semua sesi SCHEDULED yang terkait enrollment murid ini.
            // Pakai CANCELLED (bukan hapus) agar audit trail history sesi terjaga.
            // Gunakan semua enrollment (bukan hanya yang aktif) untuk menangkap
            // edge case di mana enrollment sudah ditutup tapi sesi masih tersisa.
            ClassSession::whereIn(
                'enrollment_id',
                $student->enrollments()->pluck('id')
            )
            ->where('status', ClassSession::STATUS_SCHEDULED)
            ->update([
                'status' => ClassSession::STATUS_CANCELLED,
                'notes'  => 'Murid mengundurkan diri — sesi dibatalkan otomatis',
            ]);

            $this->recordHistory(
                student:  $student,
                from:     $from,
                to:       'Mengundurkan Diri',
                reason:   $data['reason'],
                metadata: [
                    'auto' => $data['auto'] ?? false,
                ],
            );

            return $student->fresh();
        });
    }

    /**
     * Aktif -> Selesai. Khusus murid Kids Class yang lulus 6 bulan (BR-10.7).
     */
    public function selesai(Student $student, array $data = []): Student
    {
        $this->ensureTransition($student, 'Selesai');

        $classType = $student->package?->class_type ?? '';
        if (!in_array($classType, ['KIDS_CLASS', 'KIDS_CLASS_BUNDLE'], true)) {
            throw new InvalidArgumentException(
                'Status Selesai hanya berlaku untuk paket Kids Class.'
            );
        }

        // Guard: blok graduasi jika masih ada tagihan belum lunas
        if ($student->invoices()->whereIn('status', ['UNPAID', 'PARTIAL'])->exists()) {
            throw new InvalidArgumentException(
                'Murid masih punya tagihan yang belum lunas. Selesaikan semua tagihan sebelum menandai lulus.'
            );
        }

        return DB::transaction(function () use ($student, $data) {
            $from = $student->status;

            $student->update(['status' => 'Selesai']);

            // Tutup enrollment Kids Class: COMPLETED + end_date = today.
            $this->closeActiveEnrollments($student, status: 'COMPLETED');

            $this->recordHistory(
                student: $student,
                from:    $from,
                to:      'Selesai',
                reason:  $data['notes'] ?? 'Lulus Kids Class 6 bulan',
            );

            return $student->fresh();
        });
    }

    /**
     * Selesai -> Aktif (re-enroll privat, TANPA registrasi ulang, BR-10.7).
     * Mengundurkan Diri -> Aktif (re-aktivasi, BAYAR registrasi ulang Rp 250rb).
     *
     * Method ini TIDAK menerima Cuti sebagai status asal — gunakan
     * aktifkanDariCuti() yang lebih sederhana (tidak pilih paket lagi).
     *
     * @param array{
     *     package_id: int,
     *     assigned_teacher_id: int,
     *     assigned_room_id?: int,
     *     notes?: string|null,
     * } $data
     */
    public function aktifkanKembali(Student $student, array $data): Student
    {
        $this->ensureTransition($student, 'Aktif');

        if (!in_array($student->status, ['Selesai', 'Mengundurkan Diri'], true)) {
            throw new InvalidArgumentException(
                'Aktifkan kembali hanya dari Selesai atau Mengundurkan Diri. ' .
                'Untuk dari Cuti gunakan aksi "Akhiri Cuti".'
            );
        }

        // Warning (bukan block): cek hutang lama dari sebelum murid mundur.
        // Owner yang memutuskan apakah lanjut — proses tetap berjalan.
        $unpaidCount = $student->invoices()
            ->whereIn('status', ['UNPAID', 'PARTIAL'])
            ->count();

        if ($unpaidCount > 0) {
            session()->flash(
                'warning',
                "Perhatian: murid ini memiliki {$unpaidCount} tagihan lama yang belum lunas. " .
                "Periksa riwayat tagihan setelah aktivasi."
            );
        }

        // Mundur -> Aktif WAJIB bayar registrasi ulang. Selesai -> Aktif tidak.
        $needRegFee = $student->status === 'Mengundurkan Diri';

        return DB::transaction(function () use ($student, $data, $needRegFee) {
            $from = $student->status;

            $student->update([
                'status' => 'Aktif',
                'package_id' => $data['package_id'],
                'assigned_teacher_id' => $data['assigned_teacher_id'],
                'assigned_room_id' => $data['assigned_room_id'] ?? $student->assigned_room_id,
                'active_since' => now()->toDateString(),
            ]);

            // Re-enroll: bikin enrollment baru. Yang lama sudah INACTIVE/COMPLETED
            // dari transisi sebelumnya (Mundur/Selesai), tapi defensive close dulu.
            $this->closeActiveEnrollments($student, status: 'INACTIVE');
            $enrollment = $this->openEnrollment(
                $student,
                $data['package_id'],
                $data['assigned_teacher_id'],
            );

            // Mundur->Aktif WAJIB bayar REG ulang (Rp 250rb). Selesai->Aktif tidak.
            $invoice = $this->issueActivationInvoice(
                $student, $enrollment,
                includeReg: $needRegFee,
                paymentMode: $data['payment_mode'] ?? 'FULL',
            );

            $this->recordHistory(
                student:  $student,
                from:     $from,
                to:       'Aktif',
                reason:   $data['notes'] ?? "Aktifkan kembali dari status {$from}",
                metadata: [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ],
            );

            return $student->fresh();
        });
    }

    /**
     * Cuti -> Aktif. Murid kembali setelah periode cuti.
     * TIDAK ada tagihan tambahan — biaya cuti sudah ditagih saat pengajuan.
     * Paket & guru tetap (tidak perlu input ulang).
     *
     * Idealnya method ini dipanggil cron otomatis saat cuti_until berakhir,
     * tapi untuk Fase 1 admin bisa trigger manual lewat tombol "Akhiri Cuti".
     */
    public function aktifkanDariCuti(Student $student, array $data = []): Student
    {
        if ($student->status !== 'Cuti') {
            throw new InvalidArgumentException(
                'Akhiri cuti hanya bisa dari status Cuti. Status sekarang: ' . $student->status
            );
        }

        return DB::transaction(function () use ($student, $data) {
            $from = $student->status;

            $student->update(['status' => 'Aktif']);

            $this->recordHistory(
                student: $student,
                from:    $from,
                to:      'Aktif',
                reason:  $data['notes'] ?? 'Cuti berakhir, murid kembali aktif',
            );

            return $student->fresh();
        });
    }

    // =================================================================
    // INTERNAL HELPERS
    // =================================================================

    /**
     * Cek transisi from->to valid sesuai state machine.
     * Throw kalau tidak valid.
     */
    private function ensureTransition(Student $student, string $to): void
    {
        $from = $student->status;
        $allowed = self::VALID_TRANSITIONS[$from] ?? [];

        if (!in_array($to, $allowed, true)) {
            $allowedStr = empty($allowed) ? '(tidak ada)' : implode(', ', $allowed);
            throw new InvalidArgumentException(
                "Transisi tidak valid: {$from} → {$to}. Yang diizinkan dari {$from}: {$allowedStr}."
            );
        }
    }

    /**
     * Tutup semua enrollment ACTIVE milik murid: set status target + end_date=today.
     * Dipanggil sebelum bikin enrollment baru (ganti paket) atau saat
     * murid Mundur/Selesai.
     */
    private function closeActiveEnrollments(Student $student, string $status): void
    {
        $student->enrollments()
            ->where('status', 'ACTIVE')
            ->update([
                'status'   => $status,
                'end_date' => now()->toDateString(),
            ]);
    }

    /**
     * Terbitkan invoice aktivasi (REG opsional + SPP / cicilan Kids Class Bundle).
     * Dipanggil saat skipTrial / konversiAktif / aktifkanKembali.
     *
     * @param  bool    $includeReg   Apakah include item REG Rp 250.000.
     *                               False kalau lulus_kids re-enroll (BR-10.7).
     * @param  string  $paymentMode  'FULL' atau 'INSTALLMENT' (hanya relevan untuk KIDS_CLASS_BUNDLE).
     * @return Invoice  Invoice pertama yang dibuat (untuk dicatat di history).
     */
    private function issueActivationInvoice(
        Student $student,
        Enrollment $enrollment,
        bool $includeReg,
        string $paymentMode = 'FULL',
    ): Invoice {
        $package = $enrollment->package ?? \App\Models\Package::find($enrollment->package_id);

        // === KIDS_CLASS_BUNDLE: buat 3 cicilan atau 1 invoice lunas ===
        if ($package->class_type === 'KIDS_CLASS_BUNDLE') {
            if ($paymentMode === 'INSTALLMENT') {
                // REG dibuat terpisah (satu invoice), lalu 3 cicilan SPP.
                if ($includeReg) {
                    $this->invoiceService->createOneOff(
                        student: $student,
                        items: [[
                            'code'        => 'REG',
                            'description' => 'Biaya Pendaftaran',
                            'amount'      => InvoiceService::FEE_REG,
                        ]],
                        description: 'Biaya Pendaftaran',
                        classType: $package->class_type,
                    );
                }

                $cicilans = $this->invoiceService->createKidsBundleInstallments(
                    student: $student,
                    enrollment: $enrollment,
                    startDate: now(),
                );

                // Kembalikan invoice cicilan pertama untuk dicatat di history.
                return $cicilans[0];
            }

            // FULL: satu invoice dengan total penuh (REG + SPP digabung atau terpisah).
            if ($includeReg) {
                $this->invoiceService->createOneOff(
                    student: $student,
                    items: [[
                        'code'        => 'REG',
                        'description' => 'Biaya Pendaftaran',
                        'amount'      => InvoiceService::FEE_REG,
                    ]],
                    description: 'Biaya Pendaftaran',
                    classType: $package->class_type,
                );
            }

            return $this->invoiceService->createOneOff(
                student: $student,
                items: [[
                    'code'        => 'SPP',
                    'description' => "SPP Kids Class Bundle " . now()->format('F Y') . " (Lunas)",
                    'amount'      => $package->price_per_month,
                    'metadata'    => ['package_id' => $package->id],
                ]],
                description: 'Kids Class Bundle – Pembayaran Lunas',
                classType: $package->class_type,
                paymentMode: 'FULL',
            );
        }

        // === Paket reguler: REG opsional + SPP bulan ini ===
        $items = [];

        if ($includeReg) {
            $items[] = [
                'code'        => 'REG',
                'description' => 'Biaya Pendaftaran',
                'amount'      => InvoiceService::FEE_REG,
            ];
        }

        $items[] = [
            'code'        => 'SPP',
            'description' => "SPP {$package->code} " . now()->format('F Y'),
            'amount'      => $package->price_per_month,
            'metadata'    => ['package_id' => $package->id],
        ];

        return $this->invoiceService->createOneOff(
            student: $student,
            items: $items,
            description: $includeReg ? 'Aktivasi murid (REG + SPP bulan ke-1)' : 'SPP bulan ke-1',
            classType: $package->class_type,
        );
    }

    /**
     * Bikin enrollment ACTIVE baru. Dipanggil saat skipTrial / konversiAktif /
     * aktifkanKembali. Tutup enrollment ACTIVE existing dulu biar tidak
     * ada 2 ACTIVE bersamaan.
     */
    private function openEnrollment(Student $student, int $packageId, int $teacherId): Enrollment
    {
        // Defensive: tutup ACTIVE existing kalau paket/guru beda dengan yang baru.
        $this->closeActiveEnrollments($student, status: 'INACTIVE');

        return Enrollment::create([
            'student_id'     => $student->id,
            'package_id'     => $packageId,
            'teacher_id'     => $teacherId,
            'effective_date' => now()->toDateString(),
            'status'         => 'ACTIVE',
        ]);
    }

    /**
     * Append 1 baris ke student_status_histories.
     * Pakai named arguments biar callsite jelas.
     */
    private function recordHistory(
        Student $student,
        ?string $from,
        string $to,
        ?string $reason = null,
        bool $skippedTrial = false,
        ?array $metadata = null,
    ): StudentStatusHistory {
        return StudentStatusHistory::create([
            'student_id'    => $student->id,
            'from_status'   => $from,
            'to_status'     => $to,
            'reason'        => $reason,
            'skipped_trial' => $skippedTrial,
            'metadata'      => $metadata,
            'changed_by'    => Auth::id(),
        ]);
    }
}
