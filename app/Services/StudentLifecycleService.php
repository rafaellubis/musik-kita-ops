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
    /** Pesan warning dari operasi terakhir, jika ada. Dibaca oleh controller. */
    public ?string $lastWarning = null;

    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly EnrollmentSessionCleanupService $sessionCleanup,
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
     * Buat Enrollment status=TRIAL (is_primary=false) + ClassSession dengan enrollment_id.
     * Enrollment TRIAL membawa package_id agar calculateHonor() bisa resolve paket.
     *
     * Honor ditentukan saat input absensi:
     * - Murid HADIR  → H_TRIAL = harga × 50% / 4 (BR-1.4)
     * - Murid HANGUS → TRIAL_NS = Rp 0 (BR-1.4 v1.1)
     *
     * @param array{
     *     trial_date: string,           // datetime-local string, mis. "2026-06-01T10:00"
     *     package_id: int,              // wajib — paket yang diminati murid
     *     assigned_teacher_id: int,     // wajib — FK ke teachers
     *     assigned_room_id?: int|null,  // opsional
     *     notes?: string|null,
     * } $data
     */
    public function mulaiTrial(Student $student, array $data): Student
    {
        $this->ensureTransition($student, 'Trial');

        if (empty($data['assigned_teacher_id'])) {
            throw new \InvalidArgumentException('assigned_teacher_id wajib diisi untuk membuat sesi trial.');
        }

        if (empty($data['package_id'])) {
            throw new \InvalidArgumentException('package_id wajib diisi untuk membuat sesi trial.');
        }

        return DB::transaction(function () use ($student, $data) {
            $from = $student->status;

            $student->update([
                'status'     => 'Trial',
                'trial_date' => $data['trial_date'],
            ]);

            // Buat enrollment TRIAL — membawa package_id agar honor bisa dihitung.
            // is_primary=false: tidak trigger invoice SPP otomatis.
            $enrollment = Enrollment::create([
                'student_id'     => $student->id,
                'package_id'     => $data['package_id'],
                'teacher_id'     => $data['assigned_teacher_id'],
                'effective_date' => now()->toDateString(),
                'status'         => Enrollment::STATUS_TRIAL,
                'is_primary'     => false,
            ]);

            // Buat sesi trial. Durasi 30 menit untuk semua tipe paket (BR-1.3).
            $trialDateTime = \Carbon\Carbon::parse($data['trial_date']);
            ClassSession::create([
                'schedule_id'   => null,
                'enrollment_id' => $enrollment->id,
                'student_id'    => $student->id,
                'teacher_id'    => $data['assigned_teacher_id'],
                'room_id'       => $data['assigned_room_id'] ?? null,
                'session_date'  => $trialDateTime->toDateString(),
                'start_time'    => $trialDateTime->format('H:i:s'),
                'end_time'      => $trialDateTime->copy()->addMinutes(30)->format('H:i:s'),
                'status'        => ClassSession::STATUS_SCHEDULED,
            ]);

            $this->recordHistory(
                student:  $student,
                from:     $from,
                to:       'Trial',
                reason:   $data['notes'] ?? null,
                metadata: [
                    'trial_date'          => $data['trial_date'],
                    'package_id'          => $data['package_id'],
                    'assigned_teacher_id' => $data['assigned_teacher_id'],
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
     *     notes?: string|null,
     * } $data
     */
    public function konversiAktif(Student $student, array $data): Student
    {
        $this->ensureTransition($student, 'Aktif');

        return DB::transaction(function () use ($student, $data) {
            $from = $student->status;

            $student->update([
                'status'       => 'Aktif',
                'active_since' => now()->toDateString(),
            ]);

            // Tutup enrollment TRIAL jika ada (murid berhasil convert dari trial)
            $this->closeTrialEnrollments($student);

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
                'status'       => 'Aktif',
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
     * Guard: Blok pengajuan cuti jika ada invoice SPP UNPAID/PARTIAL.
     * Perpanjang: hanya butuh cuti_until baru — cuti_from TIDAK diubah.
     * Validasi maks 62 hari total dihitung dari cuti_from awal.
     *
     * @param array{
     *     cuti_from?: string,   // wajib untuk pengajuan baru; diabaikan saat perpanjang
     *     cuti_until: string,
     *     reason: string,
     * } $data
     */
    public function ajukanCuti(Student $student, array $data): Student
    {
        if (!in_array($student->status, ['Aktif', 'Cuti'], true)) {
            throw new InvalidArgumentException(
                'Cuti hanya bisa dari Aktif atau perpanjangan dari Cuti. Status sekarang: ' . $student->status
            );
        }

        $isExtension = $student->status === 'Cuti';

        // Validasi perpanjang cuti
        if ($isExtension) {
            // Perpanjang harus memperpanjang, bukan mempersingkat — cuti_until baru
            // HARUS melebihi cuti_until saat ini.
            $currentUntil = \Carbon\Carbon::parse($student->cuti_until);
            $newUntil     = \Carbon\Carbon::parse($data['cuti_until']);
            if (!$newUntil->gt($currentUntil)) {
                throw new InvalidArgumentException(
                    'Perpanjang cuti harus melebihi tanggal cuti saat ini (' .
                    $currentUntil->format('d M Y') . ').'
                );
            }

            // Validasi maks 2 bulan total (dihitung dari cuti_from awal)
            $originalFrom = \Carbon\Carbon::parse($student->cuti_from);
            if ($originalFrom->diffInDays($newUntil) > 62) {
                throw new InvalidArgumentException(
                    'Total cuti melebihi batas maksimal 2 bulan.'
                );
            }
        }

        return DB::transaction(function () use ($student, $data, $isExtension) {
            $from = $student->status;

            // Simpan cuti_until lama sebelum diupdate — dipakai untuk range cancel pada perpanjang
            $oldCutiUntil = $student->cuti_until;

            // Update student: cuti_from hanya diset pada pengajuan baru, bukan perpanjang
            $updateData = ['status' => 'Cuti', 'cuti_until' => $data['cuti_until']];
            if (!$isExtension) {
                $updateData['cuti_from'] = $data['cuti_from'];
            }
            $student->update($updateData);
            $student->refresh();

            // Cancel sesi SCHEDULED dalam range cuti.
            // Perpanjang: cancel hanya range tambahan (oldCutiUntil → newCutiUntil).
            // Baru: cancel seluruh range (cuti_from → cuti_until).
            $cancelFrom = $isExtension ? $oldCutiUntil : $data['cuti_from'];
            ClassSession::whereIn('enrollment_id', $student->enrollments()->pluck('id'))
                ->where('status', ClassSession::STATUS_SCHEDULED)
                ->whereBetween('session_date', [$cancelFrom, $data['cuti_until']])
                ->update([
                    'status' => ClassSession::STATUS_CANCELLED,
                    'notes'  => 'Sesi dibatalkan otomatis — murid cuti ' .
                                $student->cuti_from . ' s/d ' . $data['cuti_until'],
                ]);

            // Terbitkan invoice biaya cuti Rp 100.000 (BR-9)
            $invoice = $this->invoiceService->createOneOff(
                student: $student,
                items: [[
                    'code'        => 'CUTI',
                    'description' => 'Biaya cuti ' .
                        \Carbon\Carbon::parse($student->cuti_from)->format('d M') . ' - ' .
                        \Carbon\Carbon::parse($data['cuti_until'])->format('d M Y') .
                        ($isExtension ? ' (perpanjangan)' : ''),
                    'amount'      => InvoiceService::FEE_CUTI,
                    'metadata'    => [
                        'cuti_from'    => $student->cuti_from,
                        'cuti_until'   => $data['cuti_until'],
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
                    'cuti_from'      => $student->cuti_from,
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

            // Tutup enrollment TRIAL jika ada (murid mundur tanpa jadi aktif)
            $this->closeTrialEnrollments($student);

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
            $this->lastWarning = "Perhatian: murid ini memiliki {$unpaidCount} tagihan lama yang belum lunas. " .
                "Periksa riwayat tagihan setelah aktivasi.";
        }

        // Mundur -> Aktif WAJIB bayar registrasi ulang. Selesai -> Aktif tidak.
        $needRegFee = $student->status === 'Mengundurkan Diri';

        return DB::transaction(function () use ($student, $data, $needRegFee) {
            $from = $student->status;

            $student->update([
                'status'       => 'Aktif',
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
     * Hard block: tidak bisa diakhiri sebelum cuti_until tiba (BR: cuti tidak bisa dipotong).
     * Diizinkan pada hari cuti_until itu sendiri (today >= cuti_until).
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

        // Hard block: cuti belum selesai — bandingkan tanggal saja (abaikan jam).
        // cuti_until di-cast sebagai 'date' di model, jadi sudah Carbon instance.
        if ($student->cuti_until && now()->toDateString() < $student->cuti_until->format('Y-m-d')) {
            throw new InvalidArgumentException(
                'Cuti belum selesai. Cuti berlaku hingga ' .
                $student->cuti_until->format('d M Y') . '.'
            );
        }

        return DB::transaction(function () use ($student, $data) {
            $from = $student->status;

            $student->update([
                'status'     => 'Aktif',
                'cuti_from'  => null,
                'cuti_until' => null,
            ]);

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
     *
     * primary_enrollment_id di-null setelah enrollment ditutup agar tidak ada
     * stale FK pointer ke enrollment INACTIVE/COMPLETED. Jika pemanggil
     * (openEnrollment) langsung membuat enrollment baru, pointer ini akan
     * segera diisi ulang — null sementara tidak masalah dalam satu transaksi.
     */
    private function closeActiveEnrollments(Student $student, string $status): void
    {
        $today = now()->toDateString();

        $activeEnrollments = $student->enrollments()
            ->where('status', 'ACTIVE')
            ->get();

        foreach ($activeEnrollments as $enrollment) {
            $enrollment->update([
                'status'   => $status,
                'end_date' => $today,
            ]);

            // Nonaktifkan jadwal mingguan — selaras dengan EnrollmentController::hentikanEnrollment
            $enrollment->schedules()->update(['is_active' => false]);

            $this->sessionCleanup->purgeFutureSessions($enrollment->fresh());
        }

        // Hapus pointer ke enrollment yang sudah tidak aktif
        // agar accessor ->primaryEnrollment tidak mengembalikan data lama.
        $student->update(['primary_enrollment_id' => null]);
    }

    /**
     * Tutup semua enrollment TRIAL milik murid: status → COMPLETED + end_date = today.
     * Dipanggil saat murid konversi ke ACTIVE atau mundur tanpa lanjut.
     */
    private function closeTrialEnrollments(Student $student): void
    {
        $student->enrollments()
            ->where('status', Enrollment::STATUS_TRIAL)
            ->update([
                'status'   => Enrollment::STATUS_COMPLETED,
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

            // FULL: REG + SPP digabung dalam 1 invoice — konsisten dengan pola Reguler/Hobby.
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
                'description' => "SPP Kids Class Bundle " . now()->format('F Y') . " (Lunas)",
                'amount'      => $package->price_per_month,
                'metadata'    => ['package_id' => $package->id],
            ];

            return $this->invoiceService->createOneOff(
                student: $student,
                items: $items,
                description: $includeReg ? 'Aktivasi Kids Bundle (REG + SPP Lunas)' : 'SPP Kids Class Bundle – Lunas',
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
     *
     * is_primary=true: tandai ini sebagai kelas utama murid.
     * primary_enrollment_id di student diupdate agar accessor ->package,
     * ->teacher, dll bisa berjalan tanpa kolom denormalisasi di students.
     */
    private function openEnrollment(Student $student, int $packageId, int $teacherId): Enrollment
    {
        // Defensive: tutup ACTIVE existing kalau paket/guru beda dengan yang baru.
        $this->closeActiveEnrollments($student, status: 'INACTIVE');

        $enrollment = Enrollment::create([
            'student_id'     => $student->id,
            'package_id'     => $packageId,
            'teacher_id'     => $teacherId,
            'effective_date' => now()->toDateString(),
            'status'         => 'ACTIVE',
            'is_primary'     => true,
        ]);

        // Tandai sebagai kelas utama murid agar primaryEnrollment() bekerja
        // tanpa perlu query tambahan di setiap accessor.
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        return $enrollment;
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
