<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\PayrollConfig;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Input absensi & kalkulasi honor per sesi (M04).
 *
 * Logic absensi sumber: CLAUDE.md Business Rules BR-4.*.
 *   - HADIR / HADIR_TERLAMBAT  → guru dapat honor penuh
 *   - IZIN_RESCHEDULE          → sesi tidak terjadi, honor 0 (akan ada
 *                                sesi pengganti dengan honor sendiri)
 *   - IZIN_VIDEO               → guru kerjakan video pengganti, honor penuh
 *   - HANGUS                   → murid no-show, guru tetap dibayar penuh
 *   - LIBUR                    → libur nasional, guru dibayar penuh (BR-4.10)
 *   - DIGANTI                  → honor ke substitute (BR-4.9)
 *
 * Kalkulasi honor:
 *   Reguler/Hobby : harga_paket * 50% / 4
 *   Kids Class    : 42.500 per murid (1 row sessions per murid → flat 42.500)
 *   Trial         : sama formula H_REG, kecuali no-show = 0 (BR-1.4 v1.1)
 *
 * Honor di-set langsung saat input absensi supaya admin lihat estimasi.
 * M06 nanti tinggal aggregate per teacher per month untuk slip honor.
 */
class AttendanceService
{
    /**
     * Honor flat per murid Kids Class (BR-7 H_KIDS).
     */
    private const KIDS_HONOR_PER_STUDENT = 42500;

    /**
     * Status valid untuk input absensi (7 nilai, exclude SCHEDULED yang
     * adalah default sebelum absensi disubmit).
     */
    public const VALID_ATTENDANCE_STATUSES = [
        'HADIR',
        'HADIR_TERLAMBAT',
        'IZIN_RESCHEDULE',
        'IZIN_PENDING',
        'IZIN_VIDEO',
        'HANGUS',
        'LIBUR',
        'DIGANTI',
        'CANCELLED',
    ];

    /**
     * Submit absensi untuk satu sesi. Idempotent: kalau dipanggil ulang
     * dengan data berbeda, akan update record (admin koreksi typo).
     *
     * @param array{
     *     status: string,
     *     late_minutes?: int|null,
     *     substitute_teacher_id?: int|null,
     *     notes?: string|null,
     * } $data
     */
    public function recordAttendance(ClassSession $session, array $data): ClassSession
    {
        $status = $data['status'];

        if (!in_array($status, self::VALID_ATTENDANCE_STATUSES, true)) {
            throw new InvalidArgumentException(
                'Status absensi tidak valid: ' . $status
            );
        }

        // Load relasi yang dibutuhkan calculateHonor() agar tidak lazy-load
        $session->loadMissing(['enrollment.package', 'student']);

        // Validasi konsistensi field per status
        $this->validateStatusFields($status, $data);

        return DB::transaction(function () use ($session, $data, $status) {
            $update = [
                'status'                => $status,
                'late_minutes'          => $status === 'HADIR_TERLAMBAT'
                    ? ($data['late_minutes'] ?? null)
                    : null,
                'substitute_teacher_id' => $status === 'DIGANTI'
                    ? $data['substitute_teacher_id']
                    : null,
                'notes'                 => $data['notes'] ?? $session->notes,
            ];

            // Saat DIGANTI: update jam/ruang jika pengganti masuk di waktu/tempat berbeda.
            // Honor TIDAK dihitung sekarang — pending sampai dikonfirmasi via confirmSubstitute().
            if ($status === 'DIGANTI') {
                if (!empty($data['substitute_start_time']) && !empty($data['substitute_end_time'])) {
                    $update['start_time'] = $data['substitute_start_time'] . ':00';
                    $update['end_time']   = $data['substitute_end_time'] . ':00';
                }
                if (!empty($data['substitute_room_id'])) {
                    $update['room_id'] = (int) $data['substitute_room_id'];
                }
            }

            // Hitung honor & honor_code
            $session->fill($update);
            $honor = $this->calculateHonor($session);
            $update['honor_code']   = $honor['code'];
            $update['honor_amount'] = $honor['amount'];

            $session->update($update);

            // Update last_session_at di student kalau status terminal
            // (SCHEDULED tidak count, IZIN_RESCHEDULE belum count)
            if (in_array($status, ['HADIR', 'HADIR_TERLAMBAT', 'HANGUS', 'IZIN_VIDEO', 'LIBUR', 'DIGANTI'], true)) {
                $session->student?->update([
                    'last_session_at' => \Carbon\Carbon::parse($session->session_date)->setTimeFromTimeString($session->start_time),
                ]);
            }

            return $session->fresh();
        });
    }

    /**
     * Pastikan field wajib per status sudah ada.
     * HADIR_TERLAMBAT → late_minutes wajib (>0)
     * DIGANTI         → substitute_teacher_id wajib
     */
    private function validateStatusFields(string $status, array $data): void
    {
        if ($status === 'HADIR_TERLAMBAT') {
            $late = $data['late_minutes'] ?? 0;
            if (!is_numeric($late) || $late <= 0) {
                throw new InvalidArgumentException(
                    'Status HADIR_TERLAMBAT wajib disertai late_minutes > 0.'
                );
            }
        }

        if ($status === 'DIGANTI') {
            if (empty($data['substitute_teacher_id'])) {
                throw new InvalidArgumentException(
                    'Status DIGANTI wajib disertai substitute_teacher_id.'
                );
            }
            // Guru pengganti tidak boleh sama dengan guru asli
            $session = $data['__session'] ?? null;
            if ($session instanceof ClassSession
                && (int) $data['substitute_teacher_id'] === (int) $session->teacher_id) {
                throw new InvalidArgumentException(
                    'Guru pengganti tidak boleh sama dengan guru asli.'
                );
            }

            // === VALIDASI KONFLIK GURU PENGGANTI ===
            if ($session instanceof ClassSession) {
                // Gunakan jam pengganti jika disediakan, jika tidak gunakan jam sesi asli
                $effectiveStart = !empty($data['substitute_start_time'])
                    ? $data['substitute_start_time'] . ':00'
                    : $session->start_time;
                $effectiveEnd = !empty($data['substitute_end_time'])
                    ? $data['substitute_end_time'] . ':00'
                    : $session->end_time;

                // Cek apakah guru pengganti sudah ada sesi sebagai guru asli atau
                // sebagai guru pengganti di sesi lain di waktu yang sama
                $excludedStatuses = ClassSession::statusesExcludedFromScheduleConflict();
                $guruConflict = ClassSession::where(function ($q) use ($data) {
                        $q->where('teacher_id', $data['substitute_teacher_id'])
                          ->orWhere('substitute_teacher_id', $data['substitute_teacher_id']);
                    })
                    ->whereDate('session_date', $session->session_date)
                    ->where('start_time', '<', $effectiveEnd)
                    ->where('end_time', '>', $effectiveStart)
                    ->whereNotIn('status', $excludedStatuses)
                    ->where('id', '!=', $session->id)
                    ->exists();

                if ($guruConflict) {
                    throw new InvalidArgumentException(
                        'Guru pengganti sudah memiliki sesi lain di waktu tersebut.'
                    );
                }

                // === VALIDASI KONFLIK RUANGAN PENGGANTI ===
                if (!empty($data['substitute_room_id'])) {
                    $roomConflict = ClassSession::where('room_id', (int) $data['substitute_room_id'])
                        ->whereDate('session_date', $session->session_date)
                        ->where('start_time', '<', $effectiveEnd)
                        ->where('end_time', '>', $effectiveStart)
                        ->whereNotIn('status', $excludedStatuses)
                        ->where('id', '!=', $session->id)
                        ->exists();

                    if ($roomConflict) {
                        throw new InvalidArgumentException(
                            'Ruangan pengganti sudah dipakai di waktu tersebut.'
                        );
                    }
                }
            }
        }
    }

    /**
     * Hitung honor berdasarkan status + paket di enrollment.
     *
     * Deteksi trial: enrollment->status === TRIAL.
     * Backward compat: enrollment_id === null juga dianggap trial (data lama sebelum fix).
     *
     * @return array{code: string|null, amount: int}
     */
    private function calculateHonor(ClassSession $session): array
    {
        $status = $session->status;

        // IZIN_RESCHEDULE: sesi tidak terjadi, honor di sesi pengganti.
        if ($status === 'IZIN_RESCHEDULE') {
            return ['code' => null, 'amount' => 0];
        }

        // IZIN_PENDING: murid izin tanpa tanggal pengganti yang sudah ditentukan,
        // honor nol karena status masih menunggu konfirmasi reschedule.
        if ($status === 'IZIN_PENDING') {
            return ['code' => 'H_IZIN', 'amount' => 0];
        }

        // SCHEDULED: belum ada absensi, jangan kalkulasi.
        if ($status === 'SCHEDULED') {
            return ['code' => null, 'amount' => 0];
        }

        // CANCELLED: sesi dibatalkan setelah sempat HADIR — honor hangus (tidak dibayar).
        if ($status === 'CANCELLED') {
            return ['code' => null, 'amount' => 0];
        }

        // Split reschedule (H_SPLIT): honor = setengah honor normal per sesi.
        // Dicek sebelum kalkulasi reguler agar tidak di-override.
        if ($session->split_part !== null) {
            $package = $session->enrollment?->package;
            $amount  = $package
                ? (int) round($package->price_per_month * 0.5 / 4 / 2)
                : 0;
            return ['code' => 'H_SPLIT', 'amount' => $amount];
        }

        // Resolve paket dari enrollment
        $package = $session->enrollment?->package;

        // Deteksi trial: enrollment TRIAL = murid belum jadi aktif.
        // Backward compat: enrollment_id NULL = data lama sebelum enrollment TRIAL diimplementasi.
        $isTrial = $session->enrollment_id === null
            || $session->enrollment?->status === \App\Models\Enrollment::STATUS_TRIAL;

        // Kids Class: pakai flat per murid
        if ($package && $package->isKidsClass()) {
            if ($isTrial && $status === 'HANGUS') {
                return ['code' => 'TRIAL_NS', 'amount' => 0];
            }
            return ['code' => 'H_KIDS', 'amount' => self::KIDS_HONOR_PER_STUDENT];
        }

        // DUO: honor per murid dari PayrollConfig H_DUO (bukan formula % harga paket)
        if ($package && $package->isDuo()) {
            $honorPerMurid = (int) (PayrollConfig::where('scenario_code', 'H_DUO')
                ->value('value_or_formula') ?? 40000);

            // Trial DUO: sama dengan BR-1.4 — no-show dapat TRIAL_NS Rp 0
            if ($isTrial) {
                return $status === 'HANGUS'
                    ? ['code' => 'TRIAL_NS', 'amount' => 0]
                    : ['code' => 'H_TRIAL', 'amount' => $honorPerMurid];
            }

            $code = match ($status) {
                'HADIR', 'HADIR_TERLAMBAT' => 'H_DUO',
                'IZIN_VIDEO'               => 'H_DUO',
                'HANGUS'                   => 'H_DUO',
                'LIBUR'                    => 'H_DUO',
                // DIGANTI: honor pending — akan di-set ke H_PENG saat confirmSubstitute()
                'DIGANTI'                  => null,
                default                    => null,
            };

            return ['code' => $code, 'amount' => $code ? $honorPerMurid : 0];
        }

        // Reguler/Hobby: honor = harga_paket * 50% / 4
        $baseHonor = $package
            ? (int) round($package->price_per_month * 0.5 / 4)
            : 0;

        // Trial khusus (BR-1.4)
        if ($isTrial) {
            return $status === 'HANGUS'
                ? ['code' => 'TRIAL_NS', 'amount' => 0]
                : ['code' => 'H_TRIAL', 'amount' => $baseHonor];
        }

        // Mapping status → honor_code (Reguler/Hobby)
        $code = match ($status) {
            'HADIR', 'HADIR_TERLAMBAT' => 'H_REG',
            'IZIN_VIDEO'               => 'H_VIDEO',
            'HANGUS'                   => 'H_HANGUS',
            'LIBUR'                    => 'H_LIBUR',
            // DIGANTI: honor pending — akan di-set ke H_PENG saat confirmSubstitute()
            'DIGANTI'                  => null,
            default                    => null,
        };

        return ['code' => $code, 'amount' => $code ? $baseHonor : 0];
    }

    /**
     * Hitung honor guru pengganti saat konfirmasi DIGANTI hadir (H_PENG).
     * Dipakai oleh AbsensiController::confirmSubstitute().
     *
     * @return array{code: string, amount: int}
     */
    public function calculateSubstituteHonor(ClassSession $session): array
    {
        $session->loadMissing(['enrollment.package']);
        $package = $session->enrollment?->package;

        if (!$package) {
            return ['code' => 'H_PENG', 'amount' => 0];
        }

        // Kids Class: flat per murid
        if ($package->isKidsClass()) {
            return ['code' => 'H_KIDS', 'amount' => self::KIDS_HONOR_PER_STUDENT];
        }

        // DUO: honor dari PayrollConfig H_DUO
        if ($package->isDuo()) {
            $honorPerMurid = (int) (PayrollConfig::where('scenario_code', 'H_DUO')
                ->value('value_or_formula') ?? 40000);
            return ['code' => 'H_PENG', 'amount' => $honorPerMurid];
        }

        // Reguler/Hobby: formula harga_paket * 50% / 4
        $amount = (int) round($package->price_per_month * 0.5 / 4);
        return ['code' => 'H_PENG', 'amount' => $amount];
    }
}
