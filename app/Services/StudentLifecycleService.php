<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\StudentStatusHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
 */
class StudentLifecycleService
{
    /** Pesan warning dari operasi terakhir, jika ada. Dibaca oleh controller. */
    public ?string $lastWarning = null;

    /**
     * Status sesi trial yang sudah diabsen — jadwal tidak boleh diubah lagi.
     */
    private const TRIAL_SESSION_RESCHEDULE_BLOCK = [
        ClassSession::STATUS_HADIR,
        ClassSession::STATUS_HADIR_TERLAMBAT,
        ClassSession::STATUS_HANGUS,
        ClassSession::STATUS_IZIN_RESCHEDULE,
        ClassSession::STATUS_IZIN_PENDING,
        ClassSession::STATUS_IZIN_VIDEO,
        ClassSession::STATUS_DIGANTI,
    ];

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
     * Ubah jadwal trial (Opsi B): hapus sesi trial lama, buat sesi SCHEDULED baru.
     * Hanya untuk murid status Trial — recovery setelah sesi di-cancel atau dihapus.
     *
     * @param array{
     *     trial_date: string,
     *     package_id: int,
     *     assigned_teacher_id: int,
     *     assigned_room_id?: int|null,
     *     notes?: string|null,
     * } $data
     */
    public function rescheduleTrial(Student $student, array $data): Student
    {
        if ($student->status !== 'Trial') {
            throw new InvalidArgumentException(
                'Ubah jadwal trial hanya bisa untuk murid berstatus Trial. Status sekarang: ' . $student->status
            );
        }

        if (empty($data['assigned_teacher_id'])) {
            throw new InvalidArgumentException('assigned_teacher_id wajib diisi untuk jadwal trial.');
        }

        if (empty($data['package_id'])) {
            throw new InvalidArgumentException('package_id wajib diisi untuk jadwal trial.');
        }

        return DB::transaction(function () use ($student, $data) {
            $enrollment = $student->enrollments()
                ->where('status', Enrollment::STATUS_TRIAL)
                ->first();

            if (! $enrollment) {
                throw new InvalidArgumentException(
                    'Enrollment trial tidak ditemukan. Hubungi admin sistem.'
                );
            }

            $existingSessions = ClassSession::where('enrollment_id', $enrollment->id)->get();

            foreach ($existingSessions as $session) {
                if (in_array($session->status, self::TRIAL_SESSION_RESCHEDULE_BLOCK, true)) {
                    throw new InvalidArgumentException(
                        'Trial sudah pernah diabsen — jadwal tidak dapat diubah. ' .
                        'Gunakan Konversi → Aktif atau Tandai Mundur.'
                    );
                }
            }

            $oldTrialDate = $student->trial_date
                ? \Carbon\Carbon::parse($student->trial_date)->format('Y-m-d H:i')
                : null;

            // Opsi B: hard delete sesi lama (SCHEDULED / CANCELLED / LIBUR)
            foreach ($existingSessions as $session) {
                $session->delete();
            }

            $trialDateTime = \Carbon\Carbon::parse($data['trial_date']);
            $sessionDate   = $trialDateTime->toDateString();
            $startTime     = $trialDateTime->format('H:i:s');
            $endTime       = $trialDateTime->copy()->addMinutes(30)->format('H:i:s');

            $this->assertTrialSlotAvailable(
                sessionDate: $sessionDate,
                startTime: $startTime,
                endTime: $endTime,
                teacherId: (int) $data['assigned_teacher_id'],
                roomId: isset($data['assigned_room_id']) ? (int) $data['assigned_room_id'] : null,
            );

            $student->update(['trial_date' => $data['trial_date']]);

            $enrollment->update([
                'package_id' => $data['package_id'],
                'teacher_id' => $data['assigned_teacher_id'],
            ]);

            ClassSession::create([
                'schedule_id'   => null,
                'enrollment_id' => $enrollment->id,
                'student_id'    => $student->id,
                'teacher_id'    => $data['assigned_teacher_id'],
                'room_id'       => $data['assigned_room_id'] ?? null,
                'session_date'  => $sessionDate,
                'start_time'    => $startTime,
                'end_time'      => $endTime,
                'status'        => ClassSession::STATUS_SCHEDULED,
            ]);

            $this->recordHistory(
                student:  $student,
                from:     'Trial',
                to:       'Trial',
                reason:   $data['notes'] ?? 'Jadwal trial diubah',
                metadata: [
                    'action'            => 'reschedule_trial',
                    'rescheduled_from'  => $oldTrialDate,
                    'rescheduled_to'    => $data['trial_date'],
                    'package_id'        => $data['package_id'],
                    'assigned_teacher_id' => $data['assigned_teacher_id'],
                ],
            );

            return $student->fresh();
        });
    }

    /**
     * Cek konflik guru/ruang untuk slot trial baru (sama seperti SessionController::update).
     */
    private function assertTrialSlotAvailable(
        string $sessionDate,
        string $startTime,
        string $endTime,
        int $teacherId,
        ?int $roomId,
    ): void {
        $teacherConflict = ClassSession::where('session_date', $sessionDate)
            ->where('teacher_id', $teacherId)
            ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($teacherConflict) {
            throw new InvalidArgumentException('Guru sudah punya sesi lain di jam yang sama.');
        }

        if ($roomId) {
            $roomConflict = ClassSession::where('session_date', $sessionDate)
                ->where('room_id', $roomId)
                ->whereNotIn('status', ClassSession::statusesExcludedFromScheduleConflict())
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->exists();

            if ($roomConflict) {
                throw new InvalidArgumentException('Ruang sudah dipakai sesi lain di jam yang sama.');
            }
        }
    }

    /**
     * Trial -> Aktif. Murid lanjut daftar penuh setelah trial sukses.
     * Auto-flag pending invoice REG (Rp 250rb) + SPP bulan pertama (BR-1.5, BR-1.8).
     *
     * @param array{
     *     package_id: int,
     *     assigned_teacher_id: int,
     *     active_since: string,
     *     notes?: string|null,
     * } $data
     */
    public function konversiAktif(Student $student, array $data): Student
    {
        $this->ensureTransition($student, 'Aktif');

        return DB::transaction(function () use ($student, $data) {
            $from = $student->status;
            $activationDate = Carbon::parse($data['active_since'])->startOfDay();

            $student->update([
                'status'       => 'Aktif',
                'active_since' => $activationDate->toDateString(),
            ]);

            // Tutup enrollment TRIAL jika ada (murid berhasil convert dari trial)
            $this->closeTrialEnrollments($student);

            // Bikin enrollment ACTIVE — sumber kebenaran untuk M03 (jadwal/sesi).
            $enrollment = $this->openEnrollment(
                $student,
                $data['package_id'],
                $data['assigned_teacher_id'],
                $activationDate,
            );

            // Terbitkan invoice REG + SPP untuk bulan mulai aktif (BR-1.5, BR-1.8).
            // payment_mode: FULL atau INSTALLMENT, relevan untuk KIDS_CLASS_BUNDLE.
            $invoice = $this->issueActivationInvoice(
                $student, $enrollment,
                includeReg: true,
                paymentMode: $data['payment_mode'] ?? 'FULL',
                activationDate: $activationDate,
            );

            $this->recordHistory(
                student:  $student,
                from:     $from,
                to:       'Aktif',
                reason:   $data['notes'] ?? 'Konversi dari Trial',
                metadata: [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'active_since'   => $activationDate->toDateString(),
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
     * Cuti berlaku per kelas, maksimal 31 hari inklusif dan satu kali perpanjangan
     * hingga total 62 hari. Pengajuan/perpanjangan ditagih Rp 100.000 per kelas.
     */
    public function ajukanCuti(Student $student, array $data): Student
    {
        $validator = Validator::make($data, [
            'enrollment_id' => 'required|integer',
            'cuti_until' => 'required|date_format:Y-m-d',
            'reason' => 'required|string|max:500',
        ]);
        if ($validator->fails()) {
            throw new InvalidArgumentException('Data pengajuan cuti tidak valid: ' . $validator->errors()->first());
        }

        return DB::transaction(function () use ($student, $data) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $enrollment = $this->leaveEnrollment($student, $data);
            $leave = $enrollment->currentLeave;
            $isExtension = $leave !== null;
            $newUntil = Carbon::parse($data['cuti_until'])->startOfDay();
            $fromStatus = $student->status;
            $oldValues = [
                'status' => $enrollment->status,
                'cuti_from' => $leave?->cuti_from?->toDateString(),
                'cuti_until' => $leave?->cuti_until?->toDateString(),
            ];

            if ($isExtension) {
                if ($leave->cuti_until->lt(today())) {
                    throw new InvalidArgumentException('Periode cuti sudah berakhir. Akhiri cuti sebelum mengajukan periode baru.');
                }
                if ($leave->extension_count >= 1) {
                    throw new InvalidArgumentException('Cuti hanya boleh diperpanjang satu kali.');
                }
                if (!$newUntil->gt($leave->cuti_until)) {
                    throw new InvalidArgumentException('Perpanjang cuti harus melebihi tanggal cuti saat ini.');
                }
                $start = $leave->cuti_from;
                if ($start->diffInDays($newUntil) + 1 > 62) {
                    throw new InvalidArgumentException('Total cuti melebihi batas maksimal 2 bulan (62 hari inklusif).');
                }
                $cancelFrom = $leave->cuti_until->copy()->addDay()->toDateString();
                $leave->update([
                    'cuti_until' => $newUntil->toDateString(),
                    'extension_count' => $leave->extension_count + 1,
                    'reason' => $data['reason'],
                ]);
            } else {
                if ($enrollment->status !== Enrollment::STATUS_ACTIVE) {
                    throw new InvalidArgumentException('Pengajuan baru hanya untuk kelas ACTIVE.');
                }
                $validator = Validator::make($data, ['cuti_from' => 'required|date_format:Y-m-d']);
                if ($validator->fails()) {
                    throw new InvalidArgumentException('Tanggal mulai cuti wajib diisi dengan format YYYY-MM-DD.');
                }
                $start = Carbon::parse($data['cuti_from'])->startOfDay();
                if ($start->lt(today()) || $newUntil->lt($start)) {
                    throw new InvalidArgumentException('Tanggal mulai cuti tidak boleh lampau dan tanggal akhir tidak boleh sebelum awal.');
                }
                if ($start->diffInDays($newUntil) + 1 > 31) {
                    throw new InvalidArgumentException('Pengajuan cuti maksimal 1 bulan (31 hari inklusif).');
                }
                $cancelFrom = $start->toDateString();
                $leave = $enrollment->leaves()->create([
                    'cuti_from' => $start->toDateString(),
                    'cuti_until' => $newUntil->toDateString(),
                    'reason' => $data['reason'],
                ]);
            }

            // Hanya sesi kelas terpilih yang belum diabsen, termasuk sesi pengganti.
            ClassSession::where('enrollment_id', $enrollment->id)
                ->where('status', ClassSession::STATUS_SCHEDULED)
                ->whereBetween('session_date', [$cancelFrom, $newUntil->toDateString()])
                ->update([
                    'status' => ClassSession::STATUS_CANCELLED,
                    'honor_code' => null,
                    'honor_amount' => 0,
                    'notes' => 'Sesi dibatalkan otomatis — kelas cuti ' . $start->toDateString() . ' s/d ' . $newUntil->toDateString(),
                ]);

            $this->synchronizeLeaveStatus($student);
            $invoice = $this->invoiceService->createOneOff(
                student: $student,
                items: [[
                    'code' => 'CUTI',
                    'description' => 'Biaya cuti ' . $start->format('d M') . ' - ' . $newUntil->format('d M Y') .
                        ($isExtension ? ' (perpanjangan)' : ''),
                    'amount' => InvoiceService::FEE_CUTI,
                    'metadata' => [
                        'enrollment_id' => $enrollment->id,
                        'leave_id' => $leave->id,
                        'cuti_from' => $start->toDateString(),
                        'cuti_until' => $newUntil->toDateString(),
                        'is_extension' => $isExtension,
                    ],
                ]],
                description: $isExtension ? 'Perpanjangan Cuti Kelas' : 'Pengajuan Cuti Kelas',
                classType: $enrollment->package?->class_type,
                enrollmentId: $enrollment->id,
            );

            $this->recordHistory(
                student: $student,
                from: $fromStatus,
                to: $student->status,
                reason: $data['reason'],
                metadata: [
                    'enrollment_id' => $enrollment->id,
                    'leave_id' => $leave->id,
                    'cuti_from' => $start->toDateString(),
                    'cuti_until' => $newUntil->toDateString(),
                    'is_extension' => $isExtension,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ],
            );
            AuditLog::record(
                action: AuditLog::ACTION_LIFECYCLE,
                entity: $enrollment,
                entityLabel: 'Cuti kelas ' . $student->full_name,
                oldValues: $oldValues,
                newValues: [
                    'status' => $enrollment->fresh()->status,
                    'leave_id' => $leave->id,
                    'cuti_from' => $start->toDateString(),
                    'cuti_until' => $newUntil->toDateString(),
                    'extension_count' => $isExtension ? 1 : 0,
                    'invoice_id' => $invoice->id,
                ],
                notes: $data['reason'],
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

    /** Kembali hanya setelah tanggal akhir inklusif; periode tetap tersimpan. */
    public function aktifkanDariCuti(Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $enrollment = $this->leaveEnrollment($student, $data);
            $leave = $enrollment->currentLeave;
            if (!$leave) {
                throw new InvalidArgumentException('Kelas tidak memiliki periode cuti yang belum diakhiri.');
            }
            if ($leave->cuti_until->gte(today())) {
                throw new InvalidArgumentException('Cuti belum selesai. Cuti berlaku hingga ' . $leave->cuti_until->format('d M Y') . '.');
            }

            $from = $student->status;
            $isAuto = (bool) ($data['auto'] ?? false);
            $leave->update(['completed_at' => now()]);
            $this->synchronizeLeaveStatus($student);
            $this->recordHistory(
                student: $student,
                from: $from,
                to: $student->status,
                reason: $data['notes'] ?? ($isAuto ? 'Cuti kelas berakhir otomatis (scheduler)' : 'Cuti kelas berakhir'),
                metadata: [
                    'enrollment_id' => $enrollment->id,
                    'leave_id' => $leave->id,
                    'auto' => $isAuto,
                ],
            );
            if (!$isAuto) {
                AuditLog::record(
                    action: AuditLog::ACTION_LIFECYCLE,
                    entity: $enrollment,
                    entityLabel: 'Kembali dari cuti kelas ' . $student->full_name,
                    oldValues: ['status' => $enrollment->status, 'leave_id' => $leave->id],
                    newValues: ['status' => $enrollment->fresh()->status, 'completed_at' => $leave->completed_at->toDateTimeString()],
                    notes: $data['notes'] ?? 'Cuti kelas berakhir',
                );
            }

            return $student->fresh();
        });
    }

    /** Status kelas mengikuti tanggal cuti; murid tetap Aktif bila satu kelas aktif. */
    public function synchronizeLeaveStatus(Student $student): void
    {
        DB::transaction(function () use ($student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $student->refresh();
            if (!in_array($student->status, ['Aktif', 'Cuti'], true)) {
                return;
            }
            $enrollments = $student->enrollments()
                ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_ON_LEAVE])
                ->with('currentLeave')->lockForUpdate()->get();
            foreach ($enrollments as $enrollment) {
                $leave = $enrollment->currentLeave;
                $status = $leave && $leave->cuti_from->lte(today()) && $leave->cuti_until->gte(today())
                    ? Enrollment::STATUS_ON_LEAVE : Enrollment::STATUS_ACTIVE;
                if ($enrollment->status !== $status) {
                    AuditLog::record(
                        action: AuditLog::ACTION_LIFECYCLE,
                        entity: $enrollment,
                        entityLabel: 'Status cuti kelas ' . $student->full_name,
                        oldValues: ['status' => $enrollment->status],
                        newValues: ['status' => $status, 'leave_id' => $leave?->id],
                        notes: 'Sinkronisasi tanggal cuti kelas',
                    );
                    $enrollment->update(['status' => $status]);
                }
            }

            $status = $enrollments->contains('status', Enrollment::STATUS_ACTIVE) ? 'Aktif'
                : ($enrollments->contains('status', Enrollment::STATUS_ON_LEAVE) ? 'Cuti' : $student->status);
            $projection = $enrollments->pluck('currentLeave')->filter()->sortBy('cuti_from')->first();
            if ($student->status !== $status) {
                AuditLog::record(
                    action: AuditLog::ACTION_LIFECYCLE,
                    entity: $student,
                    entityLabel: $student->full_name,
                    oldValues: ['status' => $student->status],
                    newValues: ['status' => $status],
                    notes: 'Sinkronisasi status dari kelas aktif dan periode cuti',
                );
            }
            // Kolom lama hanya proyeksi untuk impor lama, bukan sumber periode cuti.
            $student->update([
                'status' => $status,
                'cuti_from' => $projection?->cuti_from,
                'cuti_until' => $projection?->cuti_until,
            ]);
        });
    }

    private function leaveEnrollment(Student $student, array $data): Enrollment
    {
        if (!in_array($student->status, ['Aktif', 'Cuti'], true)) {
            throw new InvalidArgumentException('Cuti kelas hanya berlaku untuk murid Aktif atau Cuti.');
        }
        if (empty($data['enrollment_id']) || filter_var($data['enrollment_id'], FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('enrollment_id wajib diisi.');
        }
        $enrollment = $student->enrollments()->whereKey($data['enrollment_id'])->lockForUpdate()->first();
        if (!$enrollment || !in_array($enrollment->status, [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_ON_LEAVE], true)) {
            throw new InvalidArgumentException('Kelas harus milik murid ini dan berstatus ACTIVE atau ON_LEAVE.');
        }

        return $enrollment;
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
     * Tutup semua enrollment ACTIVE/ON_LEAVE milik murid: status target + end_date=today.
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
            ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_ON_LEAVE])
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
     * @param  Carbon|null  $activationDate  Penentu periode invoice; default hari ini.
     * @return Invoice  Invoice pertama yang dibuat (untuk dicatat di history).
     */
    private function issueActivationInvoice(
        Student $student,
        Enrollment $enrollment,
        bool $includeReg,
        string $paymentMode = 'FULL',
        ?Carbon $activationDate = null,
    ): Invoice {
        $activationDate ??= now();
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
                        periodDate: $activationDate,
                    );
                }

                $cicilans = $this->invoiceService->createKidsBundleInstallments(
                    student: $student,
                    enrollment: $enrollment,
                    startDate: $activationDate,
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
                'description' => "SPP Kids Class Bundle " . $activationDate->format('F Y') . " (Lunas)",
                'amount'      => $package->price_per_month,
                'metadata'    => [
                    'package_id'    => $package->id,
                    'enrollment_id' => $enrollment->id,
                ],
            ];

            return $this->invoiceService->createOneOff(
                student: $student,
                items: $items,
                description: $includeReg ? 'Aktivasi Kids Bundle (REG + SPP Lunas)' : 'SPP Kids Class Bundle – Lunas',
                classType: $package->class_type,
                paymentMode: 'FULL',
                enrollmentId: $enrollment->id,
                periodDate: $activationDate,
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
            'description' => "SPP {$package->code} " . $activationDate->format('F Y'),
            'amount'      => $package->price_per_month,
            'metadata'    => [
                'package_id'    => $package->id,
                'enrollment_id' => $enrollment->id,
            ],
        ];

        return $this->invoiceService->createOneOff(
            student: $student,
            items: $items,
            description: $includeReg ? 'Aktivasi murid (REG + SPP bulan ke-1)' : 'SPP bulan ke-1',
            classType: $package->class_type,
            enrollmentId: $enrollment->id,
            periodDate: $activationDate,
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
    private function openEnrollment(
        Student $student,
        int $packageId,
        int $teacherId,
        ?Carbon $effectiveDate = null,
    ): Enrollment
    {
        // Defensive: tutup ACTIVE existing kalau paket/guru beda dengan yang baru.
        $this->closeActiveEnrollments($student, status: 'INACTIVE');

        $enrollment = Enrollment::create([
            'student_id'     => $student->id,
            'package_id'     => $packageId,
            'teacher_id'     => $teacherId,
            'effective_date' => ($effectiveDate ?? now())->toDateString(),
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
