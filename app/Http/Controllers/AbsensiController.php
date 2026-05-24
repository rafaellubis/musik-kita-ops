<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSplitPartRequest;
use App\Http\Requests\UpdateAbsensiRequest;
use App\Models\ClassSession;
use App\Models\Room;
use App\Models\Teacher;
use App\Services\AttendanceService;
use App\Services\RescheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Absensi Harian (M04) — tampilan per-hari.
 *
 * Menampilkan SEMUA sesi pada satu tanggal sekaligus
 * agar Admin bisa input absensi dalam satu layar (M04 daily view).
 *
 * Dua endpoint:
 *   GET  /absensi              -> daftar sesi hari ini (filter by tanggal)
 *   PATCH /absensi/{session}   -> update satu sesi via AJAX inline
 */
class AbsensiController extends Controller
{
    public function __construct(
        private RescheduleService $rescheduleService,
        private AttendanceService $attendanceService,
    ) {}

    /**
     * Tampilkan halaman absensi harian.
     * Default: hari ini. Bisa difilter via query ?date=YYYY-MM-DD
     */
    public function index(Request $request): View
    {
        $tanggal = $request->date
            ? Carbon::parse($request->date)->toDateString()
            : today()->toDateString();

        $sessions = ClassSession::with([
                'student', 'teacher', 'substituteTeacher', 'room',
                'originSession', 'enrollment.package',
            ])
            ->whereDate('session_date', $tanggal)
            ->orderBy('start_time')
            ->get();

        $teachers = Teacher::where('is_active', true)->orderBy('name')->get();
        $rooms    = Room::where('is_active', true)->orderBy('code')->get();

        // Kumpulkan origin_session_id dari semua split Part 1 yang tampil hari ini,
        // lalu cek apakah Part 2-nya sudah ada — dipakai view untuk disable tombol Part 2.
        $part2ExistsForOriginIds = $sessions
            ->where('split_part', 1)
            ->map(fn ($s) => $s->origin_session_id)
            ->filter()
            ->unique()
            ->pipe(function ($originIds) {
                if ($originIds->isEmpty()) {
                    return collect();
                }
                return ClassSession::whereIn('origin_session_id', $originIds)
                    ->where('split_part', 2)
                    ->pluck('origin_session_id');
            })
            ->flip(); // Dijadikan lookup set: isset($part2ExistsForOriginIds[$id])

        // ID sesi yang sudah punya sesi pengganti reguler (non-split) — dipakai view
        // untuk menyembunyikan tombol IZIN agar admin tidak bisa buat replacement kedua.
        $sessionIdsWithReplacement = ClassSession::whereIn(
                'origin_session_id',
                $sessions->pluck('id')
            )
            ->whereNull('split_part')
            ->pluck('origin_session_id')
            ->flip(); // lookup set: isset($sessionIdsWithReplacement[$id])

        return view('absensi.index', [
            'sessions'                   => $sessions,
            'teachers'                   => $teachers,
            'rooms'                      => $rooms,
            'tanggal'                    => $tanggal,
            'tanggalObj'                 => Carbon::parse($tanggal),
            'part2ExistsForOriginIds'    => $part2ExistsForOriginIds,
            'sessionIdsWithReplacement'  => $sessionIdsWithReplacement,
        ]);
    }

    /**
     * Update status absensi satu sesi (AJAX inline).
     *
     * Business rules:
     * - LIBUR tidak bisa diubah (BR-4.10)
     * - IZIN_RESCHEDULE: buat sesi pengganti via RescheduleService
     *   Jika ada konflik guru/ruangan → rollback via DB transaction, return 422
     */
    public function update(UpdateAbsensiRequest $request, ClassSession $classSession): JsonResponse
    {
        if ($classSession->status === ClassSession::STATUS_LIBUR) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi libur nasional tidak bisa diubah.',
            ], 403);
        }

        // Guard: sesi split (bagian 1 atau 2) tidak bisa di-reschedule ulang.
        // Murid hanya boleh izin pada sesi original — split adalah sesi pengganti.
        if ($classSession->split_part !== null
            && $request->status === ClassSession::STATUS_IZIN_RESCHEDULE) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi split tidak bisa di-reschedule. Hubungi admin untuk pengaturan manual.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($request, $classSession) {
                // AttendanceService menghitung honor_code + honor_amount berdasarkan status & paket
                $this->attendanceService->recordAttendance($classSession, [
                    'status'                => $request->status,
                    'late_minutes'          => $request->late_minutes,
                    'substitute_teacher_id' => $request->substitute_teacher_id,
                    'notes'                 => $request->notes,
                    '__session'             => $classSession,
                ]);

                if ($request->status === ClassSession::STATUS_IZIN_RESCHEDULE) {
                    // Guard: sesi asli tidak boleh punya lebih dari satu sesi pengganti reguler
                    $hasReplacement = ClassSession::where('origin_session_id', $classSession->id)
                        ->whereNull('split_part')
                        ->exists();
                    if ($hasReplacement) {
                        throw new \InvalidArgumentException(
                            'Sesi ini sudah memiliki sesi pengganti. Jadwal ulang sesi pengganti yang ada jika perlu diubah.'
                        );
                    }

                    $this->rescheduleService->createReplacement(
                        $classSession,
                        $request->replacement_date,
                        $request->replacement_time,
                        $request->replacement_room_id,
                    );
                }
            });
        } catch (\InvalidArgumentException $e) {
            // Konflik guru atau ruangan — transaction di-rollback otomatis
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $classSession->refresh()->load('substituteTeacher');

        return response()->json([
            'success'                 => true,
            'session_id'              => $classSession->id,
            'status'                  => $classSession->status,
            'late_minutes'            => $classSession->late_minutes,
            'substitute_teacher_name' => $classSession->substituteTeacher?->name,
            // notes berisi "Sesi pengganti: 2026-06-05 14:00" untuk IZIN_RESCHEDULE
            'replacement_label'       => $classSession->status === ClassSession::STATUS_IZIN_RESCHEDULE
                                            ? str_replace('Sesi pengganti: ', '', $classSession->notes ?? '')
                                            : null,
        ]);
    }

    /**
     * Buat satu bagian (Part 1 atau Part 2) dari split reschedule.
     *
     * Endpoint: POST /absensi/{classSession}/split/{part}
     *
     * Alur Part 1 (sesi asli masih SCHEDULED):
     *   1. Tandai sesi asli → IZIN_RESCHEDULE (honor_code=H_IZIN, honor_amount=0)
     *   2. Panggil RescheduleService::createSplitPart() untuk buat sesi Part 1
     *
     * Alur Part 2 (sesi asli sudah IZIN_RESCHEDULE, Part 1 sudah ada):
     *   1. Panggil RescheduleService::createSplitPart() untuk buat sesi Part 2
     *
     * Guard kondisi (dikembalikan sebagai 422 jika gagal):
     *   - Part 1: sesi asli harus SCHEDULED atau IZIN_RESCHEDULE
     *   - Part 1: belum ada Part 1 sebelumnya
     *   - Part 2: Part 1 sudah harus ada
     *   - Part 2: belum ada Part 2 sebelumnya
     *   - Konflik guru/ruang: dilempar oleh RescheduleService sebagai InvalidArgumentException
     */
    public function storeSplitPart(
        StoreSplitPartRequest $request,
        ClassSession $classSession,
        int $part
    ): JsonResponse {
        $newSession = null;

        try {
            DB::transaction(function () use ($request, $classSession, $part, &$newSession) {
                // Guard: validasi status dan keberadaan Part sebelum memproses
                $this->validateSplitGuards($classSession, $part);

                // Jika sesi asli masih SCHEDULED, tandai dulu sebagai IZIN_RESCHEDULE
                // agar RescheduleService bisa memproses split dengan benar.
                if ($part === 1 && $classSession->status === ClassSession::STATUS_SCHEDULED) {
                    $classSession->update([
                        'status'       => ClassSession::STATUS_IZIN_RESCHEDULE,
                        'honor_code'   => 'H_IZIN',
                        'honor_amount' => 0,
                    ]);
                    $classSession->refresh();
                }

                $newSession = $this->rescheduleService->createSplitPart(
                    $classSession,
                    $request->replacement_date,
                    $request->replacement_time,
                    $request->replacement_room_id,
                    $part
                );
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success'       => true,
            'part'          => $part,
            'message'       => "Bagian {$part} berhasil dijadwalkan.",
            'session_id'    => $newSession->id,
            'session_date'  => $newSession->session_date,
            'session_label' => $newSession->getSessionLabel(),
        ]);
    }

    /**
     * Validasi kondisi guard sebelum membuat split part.
     * Melempar InvalidArgumentException jika kondisi tidak terpenuhi.
     *
     * @throws \InvalidArgumentException
     */
    private function validateSplitGuards(ClassSession $classSession, int $part): void
    {
        // Guard: nilai part harus 1 atau 2
        if (!in_array($part, [1, 2], true)) {
            throw new \InvalidArgumentException('Nilai part tidak valid.');
        }

        // Guard: sesi yang mau di-split tidak boleh sudah merupakan bagian split (split_part = 1 atau 2).
        // Nested split tidak diizinkan — hanya sesi original yang bisa dibagi.
        if ($classSession->split_part !== null) {
            throw new \InvalidArgumentException(
                'Sesi ini adalah bagian dari split yang sudah ada dan tidak bisa dibagi lagi.'
            );
        }

        // Guard: sesi ini tidak boleh sudah punya pengganti reguler (bukan split)
        // Jika ada, harus gunakan alur reschedule biasa, bukan split.
        $regularReplacementExists = ClassSession::where('origin_session_id', $classSession->id)
            ->whereNull('split_part')
            ->exists();
        if ($regularReplacementExists) {
            throw new \InvalidArgumentException(
                'Sesi ini sudah memiliki sesi pengganti reguler. Gunakan alur reschedule biasa.'
            );
        }

        $status = $classSession->status;

        if ($part === 1) {
            // Part 1 hanya bisa dibuat jika sesi asli masih SCHEDULED atau IZIN_RESCHEDULE
            if (!in_array($status, [ClassSession::STATUS_SCHEDULED, ClassSession::STATUS_IZIN_RESCHEDULE], true)) {
                throw new \InvalidArgumentException(
                    'Split hanya bisa dilakukan pada sesi yang belum berlangsung (SCHEDULED atau IZIN_RESCHEDULE).'
                );
            }

            // Part 1 tidak boleh sudah ada
            $part1Exists = ClassSession::where('origin_session_id', $classSession->id)
                ->where('split_part', 1)
                ->exists();

            if ($part1Exists) {
                throw new \InvalidArgumentException('Bagian 1 sudah terjadwal untuk sesi ini.');
            }
        }

        if ($part === 2) {
            // Part 1 harus sudah ada sebelum buat Part 2
            $part1Exists = ClassSession::where('origin_session_id', $classSession->id)
                ->where('split_part', 1)
                ->exists();

            if (!$part1Exists) {
                throw new \InvalidArgumentException('Bagian 1 belum dijadwalkan. Jadwalkan Bagian 1 terlebih dahulu.');
            }

            // Part 2 tidak boleh sudah ada
            $part2Exists = ClassSession::where('origin_session_id', $classSession->id)
                ->where('split_part', 2)
                ->exists();

            if ($part2Exists) {
                throw new \InvalidArgumentException('Bagian 2 sudah terjadwal untuk sesi ini.');
            }
        }
    }
}
