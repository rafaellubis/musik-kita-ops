<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentRequest;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Schedule;
use App\Models\Student;
use App\Services\ScheduleConflictDetector;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller untuk manajemen kelas per murid (fitur multi-kelas).
 *
 * Satu murid bisa punya beberapa enrollment ACTIVE secara bersamaan.
 * Setiap enrollment memiliki 1 jadwal mingguan tetap (Schedule).
 * Tepat 1 enrollment per murid ditandai is_primary = true.
 */
class EnrollmentController extends Controller
{
    /**
     * Inject ScheduleConflictDetector untuk cek konflik guru dan ruang
     * sebelum enrollment baru dibuat.
     */
    public function __construct(private ScheduleConflictDetector $conflictDetector) {}

    /**
     * Tambah kelas baru ke murid yang sudah aktif.
     *
     * Membuat Enrollment baru + Schedule mingguan sekaligus dalam satu transaksi.
     * Jika jadikan_utama = true: enrollment lama dilepas status utama,
     * enrollment baru dijadikan utama, dan primary_enrollment_id di-update.
     */
    public function store(StoreEnrollmentRequest $request, Student $student): RedirectResponse
    {
        // Hanya murid Aktif yang boleh ditambah kelas baru via multi-kelas.
        // Status lain harus melalui flow lifecycle masing-masing terlebih dahulu.
        abort_if(
            $student->status !== 'Aktif',
            422,
            'Kelas baru hanya bisa ditambah untuk murid yang sedang Aktif.'
        );

        $data = $request->validated();

        // Hitung end_time berdasarkan durasi paket
        $package   = Package::findOrFail($data['package_id']);
        $startTime = $data['start_time'];
        $endTime   = Carbon::createFromFormat('H:i', $startTime)
            ->addMinutes($package->duration_min)
            ->format('H:i');

        $isDuo = $package->isDuo();

        // Cek konflik guru — DUO boleh berbagi slot jika partner juga DUO (maks 2)
        $teacherConflicts = $this->conflictDetector->findTeacherConflicts(
            teacherId: $data['teacher_id'],
            dayOfWeek: $data['day_of_week'],
            startTime: $startTime,
            endTime:   $endTime,
        );

        if ($isDuo) {
            $nonDuoConflicts = $teacherConflicts->filter(
                fn ($s) => $s->enrollment?->package?->class_type !== 'DUO'
            );
            $duoConflicts = $teacherConflicts->filter(
                fn ($s) => $s->enrollment?->package?->class_type === 'DUO'
            );

            if ($nonDuoConflicts->isNotEmpty()) {
                return back()
                    ->withErrors(['teacher_id' => 'Guru sudah punya jadwal non-DUO di hari dan jam tersebut.'])
                    ->withInput();
            }
            if ($duoConflicts->count() >= 2) {
                return back()
                    ->withErrors(['teacher_id' => 'Slot DUO sudah penuh (maksimal 2 murid per slot).'])
                    ->withInput();
            }
        } else {
            if ($teacherConflicts->isNotEmpty()) {
                return back()
                    ->withErrors(['teacher_id' => 'Guru sudah punya jadwal di hari dan jam tersebut.'])
                    ->withInput();
            }
        }

        // Cek konflik ruang — DUO boleh berbagi ruang dengan partner DUO (maks 2)
        if ($isDuo) {
            $roomConflicts = $this->conflictDetector->findRoomConflicts(
                roomId:    $data['room_id'],
                dayOfWeek: $data['day_of_week'],
                startTime: $startTime,
                endTime:   $endTime,
            );
            $nonDuoRoom = $roomConflicts->filter(
                fn ($s) => $s->enrollment?->package?->class_type !== 'DUO'
            );
            $duoRoom = $roomConflicts->filter(
                fn ($s) => $s->enrollment?->package?->class_type === 'DUO'
            );

            if ($nonDuoRoom->isNotEmpty() || $duoRoom->count() >= 2) {
                return back()
                    ->withErrors(['room_id' => 'Ruangan tidak tersedia di slot ini untuk DUO.'])
                    ->withInput();
            }
        } else {
            if ($this->conflictDetector->isRoomFull(
                roomId:    $data['room_id'],
                dayOfWeek: $data['day_of_week'],
                startTime: $startTime,
                endTime:   $endTime,
            )) {
                return back()
                    ->withErrors(['room_id' => 'Ruangan sudah penuh di hari dan jam tersebut.'])
                    ->withInput();
            }
        }

        DB::transaction(function () use ($student, $data, $startTime, $endTime) {
            $jadikanUtama = (bool) ($data['jadikan_utama'] ?? false);

            // Jika enrollment baru akan jadi utama: lepas flag utama dari yang lama
            if ($jadikanUtama) {
                $student->enrollments()->where('is_primary', true)->update(['is_primary' => false]);
            }

            // Buat enrollment baru
            $enrollment = Enrollment::create([
                'student_id'     => $student->id,
                'package_id'     => $data['package_id'],
                'teacher_id'     => $data['teacher_id'],
                'effective_date' => $data['effective_date'],
                'status'         => 'ACTIVE',
                'is_primary'     => $jadikanUtama,
            ]);

            // Buat jadwal mingguan tetap untuk enrollment ini
            Schedule::create([
                'enrollment_id' => $enrollment->id,
                'day_of_week'   => $data['day_of_week'],
                'start_time'    => $startTime,
                'end_time'      => $endTime,
                'room_id'       => $data['room_id'],
                'is_active'     => true,
            ]);

            // Update pointer primary_enrollment_id di tabel students
            if ($jadikanUtama) {
                $student->update(['primary_enrollment_id' => $enrollment->id]);
            }
        });

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Kelas berhasil ditambahkan.');
    }

    /**
     * Jadikan enrollment ini sebagai kelas utama murid.
     *
     * Melepas flag is_primary dari enrollment saat ini,
     * menetapkan enrollment yang dipilih sebagai utama,
     * dan memperbarui primary_enrollment_id di tabel students.
     */
    public function setPrimary(Student $student, Enrollment $enrollment): RedirectResponse
    {
        // Pastikan enrollment memang milik murid ini
        abort_if($enrollment->student_id !== $student->id, 403);
        // Hanya enrollment ACTIVE yang bisa dijadikan utama
        abort_if($enrollment->status !== 'ACTIVE', 422, 'Hanya kelas yang sedang berjalan bisa dijadikan utama.');

        DB::transaction(function () use ($student, $enrollment) {
            // Lepas flag utama dari semua enrollment murid ini
            $student->enrollments()->where('is_primary', true)->update(['is_primary' => false]);
            // Tetapkan enrollment yang dipilih sebagai utama
            $enrollment->update(['is_primary' => true]);
            // Update pointer di tabel students
            $student->update(['primary_enrollment_id' => $enrollment->id]);
        });

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Kelas utama berhasil diperbarui.');
    }

    /**
     * Hentikan kelas (set status INACTIVE + nonaktifkan jadwal).
     *
     * Kasus khusus: jika kelas yang dihentikan adalah kelas UTAMA dan
     * masih ada kelas aktif lain, maka sistem meminta konfirmasi pilih
     * kelas utama baru terlebih dahulu (via session 'confirm_primary_swap').
     *
     * Jika 'new_primary_enrollment_id' disertakan di request, konfirmasi
     * dianggap sudah diberikan dan proses langsung dieksekusi.
     */
    public function destroy(Student $student, Enrollment $enrollment, Request $request): RedirectResponse
    {
        // Pastikan enrollment milik murid ini
        abort_if($enrollment->student_id !== $student->id, 403);
        // Hanya enrollment ACTIVE yang bisa dihentikan
        abort_if($enrollment->status !== 'ACTIVE', 422, 'Kelas sudah tidak aktif.');

        $isPrimary    = (bool) $enrollment->is_primary;
        $otherActives = $student->enrollments()
            ->active()
            ->where('id', '!=', $enrollment->id)
            ->get();

        // Kasus: menghentikan kelas UTAMA sementara masih ada kelas aktif lain
        if ($isPrimary && $otherActives->isNotEmpty()) {
            // Validasi input new_primary_enrollment_id sebelum digunakan
            $validated    = $request->validate([
                'new_primary_enrollment_id' => ['sometimes', 'nullable', 'exists:enrollments,id'],
            ]);
            $newPrimaryId = $validated['new_primary_enrollment_id'] ?? null;

            // Belum ada konfirmasi — kembalikan ke halaman murid dengan data konfirmasi.
            // Package di-load sekalian agar Blade tidak perlu query per-item (hindari N+1).
            if (!$newPrimaryId) {
                return redirect()
                    ->route('students.show', $student)
                    ->with('confirm_primary_swap', [
                        'enrollment_id' => $enrollment->id,
                        'other_actives' => $otherActives->load('package')->map(fn ($e) => [
                            'id'           => $e->id,
                            'package_id'   => $e->package_id,
                            'package_code' => $e->package->code ?? ('Kelas #' . $e->id),
                        ])->values()->toArray(),
                    ]);
            }

            // Konfirmasi sudah diberikan — validasi enrollment pengganti
            $newPrimary = Enrollment::findOrFail($newPrimaryId);
            abort_if($newPrimary->student_id !== $student->id, 403);
            abort_if($newPrimary->status !== 'ACTIVE', 422, 'Kelas pengganti harus berstatus aktif.');

            // Lanjutkan proses swap + hentikan dalam satu transaksi
            DB::transaction(function () use ($student, $enrollment, $newPrimary) {
                // Lepas flag utama dari semua enrollment
                $student->enrollments()->where('is_primary', true)->update(['is_primary' => false]);
                // Tetapkan enrollment pengganti sebagai utama baru
                $newPrimary->update(['is_primary' => true]);
                $student->update(['primary_enrollment_id' => $newPrimary->id]);
                // Hentikan enrollment yang diminta
                $this->hentikanEnrollment($enrollment);
            });

            return redirect()
                ->route('students.show', $student)
                ->with('success', 'Kelas dihentikan dan kelas utama diperbarui.');
        }

        // Kasus normal: hentikan kelas non-utama, atau kelas utama satu-satunya
        // Dibungkus dalam transaksi agar enrollment->update dan schedules->update atomic
        DB::transaction(function () use ($enrollment) {
            $this->hentikanEnrollment($enrollment);
        });

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Kelas berhasil dihentikan.');
    }

    /**
     * Helper: set enrollment ke INACTIVE dan nonaktifkan semua jadwal terkait.
     */
    private function hentikanEnrollment(Enrollment $enrollment): void
    {
        $enrollment->update([
            'status'   => 'INACTIVE',
            'end_date' => now()->toDateString(),
        ]);

        // Nonaktifkan semua jadwal mingguan yang terkait dengan enrollment ini
        $enrollment->schedules()->update(['is_active' => false]);
    }
}
