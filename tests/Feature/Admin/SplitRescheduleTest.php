<?php

namespace Tests\Feature\Admin;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SplitRescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Admin',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Owner',  'guard_name' => 'web']);
    }

    private function makeOriginalSession(array $override = []): ClassSession
    {
        $teacher    = Teacher::factory()->create(['name' => 'Guru Test', 'is_active' => true]);
        $student    = Student::factory()->create();
        $package    = Package::factory()->create([
            'duration_min'    => 30,
            'price_per_month' => 340000,
            'is_active'       => true,
        ]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);

        return ClassSession::factory()->create(array_merge([
            'teacher_id'       => $teacher->id,
            'student_id'       => $student->id,
            'enrollment_id'    => $enrollment->id,
            'session_date'     => '2026-05-20',
            'start_time'       => '10:00:00',
            'end_time'         => '10:30:00',
            'status'           => ClassSession::STATUS_SCHEDULED,
            'session_sequence' => 3,
        ], $override));
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        return $user;
    }

    /** @test */
    public function session_label_split_part1(): void
    {
        $original = $this->makeOriginalSession();
        $part1 = ClassSession::factory()->create([
            'origin_session_id' => $original->id,
            'session_sequence'  => 3,
            'split_part'        => 1,
            'session_date'      => '2026-06-05',
        ]);
        $part1->load('originSession');

        $this->assertEquals(
            'Bagian 1/2 — Reschedule dari Sesi ke-3 Bulan Mei 2026',
            $part1->getSessionLabel()
        );
    }

    /** @test */
    public function session_label_split_part2(): void
    {
        $original = $this->makeOriginalSession();
        $part2 = ClassSession::factory()->create([
            'origin_session_id' => $original->id,
            'session_sequence'  => 3,
            'split_part'        => 2,
            'session_date'      => '2026-06-12',
        ]);
        $part2->load('originSession');

        $this->assertEquals(
            'Bagian 2/2 — Reschedule dari Sesi ke-3 Bulan Mei 2026',
            $part2->getSessionLabel()
        );
    }

    /** @test */
    public function createSplitPart_membuat_sesi_dengan_durasi_setengah(): void
    {
        $original = $this->makeOriginalSession();
        // Paksa original ke IZIN_RESCHEDULE (bypass AttendanceService)
        $original->update(['status' => ClassSession::STATUS_IZIN_RESCHEDULE]);

        $service = app(\App\Services\RescheduleService::class);
        $part1   = $service->createSplitPart($original, '2026-06-05', '14:00', null, 1);

        $this->assertEquals(1, $part1->split_part);
        $this->assertEquals($original->id, $part1->origin_session_id);
        $this->assertEquals('14:00:00', $part1->start_time);
        $this->assertEquals('14:15:00', $part1->end_time); // 30 / 2 = 15 menit
        $this->assertEquals('H_SPLIT', $part1->honor_code);
        $this->assertEquals(ClassSession::STATUS_SCHEDULED, $part1->status);
    }

    /** @test */
    public function createSplitPart_honor_setengah_dari_normal(): void
    {
        $original = $this->makeOriginalSession(); // package price_per_month = 340000
        $original->update(['status' => ClassSession::STATUS_IZIN_RESCHEDULE]);

        $service = app(\App\Services\RescheduleService::class);
        $part1   = $service->createSplitPart($original, '2026-06-05', '14:00', null, 1);
        $part2   = $service->createSplitPart($original, '2026-06-12', '14:00', null, 2);

        // Honor normal = 340000 * 0.5 / 4 = 42500
        // Honor split  = 42500 / 2 = 21250
        $this->assertEquals(21250, $part1->honor_amount);
        $this->assertEquals(21250, $part2->honor_amount);
        $this->assertEquals(42500, $part1->honor_amount + $part2->honor_amount);
    }

    /** @test */
    public function createSplitPart_konflik_guru_throw_exception(): void
    {
        $original = $this->makeOriginalSession();
        $original->update(['status' => ClassSession::STATUS_IZIN_RESCHEDULE]);

        // Sesi lain dengan guru yang sama, waktu overlap
        ClassSession::factory()->create([
            'teacher_id'   => $original->teacher_id,
            'session_date' => '2026-06-05',
            'start_time'   => '14:00:00',
            'end_time'     => '14:15:00',
            'status'       => ClassSession::STATUS_SCHEDULED,
        ]);

        $service = app(\App\Services\RescheduleService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Guru/');
        $service->createSplitPart($original, '2026-06-05', '14:00', null, 1);
    }

    // =========================================================================
    // HTTP endpoint tests — storeSplitPart() di AbsensiController
    // =========================================================================

    /** @test */
    public function part1_berhasil_dibuat_dan_original_jadi_izin_reschedule(): void
    {
        $original = $this->makeOriginalSession(['status' => 'SCHEDULED']);
        $admin    = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(route('absensi.split', [$original->id, 1]), [
                'replacement_date'    => '2026-06-10',
                'replacement_time'    => '14:00',
                'replacement_room_id' => null,
            ]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true, 'part' => 1]);

        // Sesi asli harus berubah jadi IZIN_RESCHEDULE
        $original->refresh();
        $this->assertEquals('IZIN_RESCHEDULE', $original->status);

        // Part 1 harus terbuat dengan honor_code H_SPLIT dan honor 21250
        $part1 = ClassSession::where('origin_session_id', $original->id)
            ->where('split_part', 1)->first();
        $this->assertNotNull($part1);
        $this->assertEquals('H_SPLIT', $part1->honor_code);
        $this->assertEquals(21250, $part1->honor_amount);
    }

    /** @test */
    public function part2_berhasil_dibuat_setelah_part1(): void
    {
        $original = $this->makeOriginalSession(['status' => 'IZIN_RESCHEDULE']);
        $admin    = $this->adminUser();

        // Buat Part 1 terlebih dahulu
        ClassSession::factory()->create([
            'origin_session_id' => $original->id,
            'split_part'        => 1,
            'status'            => 'SCHEDULED',
            'teacher_id'        => $original->teacher_id,
            'student_id'        => $original->student_id,
            'enrollment_id'     => $original->enrollment_id,
            'session_date'      => '2026-06-05',
            'honor_code'        => 'H_SPLIT',
            'honor_amount'      => 21250,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('absensi.split', [$original->id, 2]), [
                'replacement_date'    => '2026-06-12',
                'replacement_time'    => '15:00',
                'replacement_room_id' => null,
            ]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true, 'part' => 2]);

        // Part 2 harus terbuat dengan honor_code H_SPLIT
        $part2 = ClassSession::where('origin_session_id', $original->id)
            ->where('split_part', 2)->first();
        $this->assertNotNull($part2);
        $this->assertEquals('H_SPLIT', $part2->honor_code);
    }

    /** @test */
    public function gagal_part1_jika_original_bukan_scheduled(): void
    {
        // Status HADIR tidak boleh di-split (bukan SCHEDULED maupun IZIN_RESCHEDULE)
        $original = $this->makeOriginalSession(['status' => 'HADIR']);
        $admin    = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(route('absensi.split', [$original->id, 1]), [
                'replacement_date' => '2026-06-10',
                'replacement_time' => '14:00',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    /** @test */
    public function gagal_part1_jika_sudah_ada_part1(): void
    {
        $original = $this->makeOriginalSession(['status' => 'IZIN_RESCHEDULE']);
        $admin    = $this->adminUser();

        // Part 1 sudah ada
        ClassSession::factory()->create([
            'origin_session_id' => $original->id,
            'split_part'        => 1,
            'status'            => 'SCHEDULED',
            'teacher_id'        => $original->teacher_id,
            'student_id'        => $original->student_id,
            'enrollment_id'     => $original->enrollment_id,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('absensi.split', [$original->id, 1]), [
                'replacement_date' => '2026-06-10',
                'replacement_time' => '14:00',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    /** @test */
    public function gagal_part2_jika_part1_belum_ada(): void
    {
        $original = $this->makeOriginalSession(['status' => 'IZIN_RESCHEDULE']);
        $admin    = $this->adminUser();

        // Langsung minta Part 2 tanpa Part 1
        $response = $this->actingAs($admin)
            ->postJson(route('absensi.split', [$original->id, 2]), [
                'replacement_date' => '2026-06-12',
                'replacement_time' => '15:00',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    /** @test */
    public function gagal_part2_jika_sudah_ada_part2(): void
    {
        $original = $this->makeOriginalSession(['status' => 'IZIN_RESCHEDULE']);
        $admin    = $this->adminUser();

        // Part 1 sudah ada
        ClassSession::factory()->create([
            'origin_session_id' => $original->id,
            'split_part'        => 1,
            'teacher_id'        => $original->teacher_id,
            'student_id'        => $original->student_id,
            'enrollment_id'     => $original->enrollment_id,
        ]);

        // Part 2 sudah ada juga
        ClassSession::factory()->create([
            'origin_session_id' => $original->id,
            'split_part'        => 2,
            'teacher_id'        => $original->teacher_id,
            'student_id'        => $original->student_id,
            'enrollment_id'     => $original->enrollment_id,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('absensi.split', [$original->id, 2]), [
                'replacement_date' => '2026-06-15',
                'replacement_time' => '16:00',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    /** @test */
    public function konflik_guru_return_422(): void
    {
        $original = $this->makeOriginalSession(['status' => 'SCHEDULED']);
        $admin    = $this->adminUser();

        // Buat sesi blocking di tanggal + jam yang SAMA dengan guru yang sama
        // start_time cocok persis dengan replacement_time agar konflik deterministik
        ClassSession::factory()->create([
            'teacher_id'   => $original->teacher_id,
            'session_date' => '2026-06-10',
            'start_time'   => '14:00:00',
            'end_time'     => '14:15:00',
            'status'       => 'SCHEDULED',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('absensi.split', [$original->id, 1]), [
                'replacement_date' => '2026-06-10',
                'replacement_time' => '14:00',
            ]);

        // Konflik guru harus terdeteksi → HTTP 422
        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    /** @test */
    public function konflik_ruang_return_422(): void
    {
        $room     = \App\Models\Room::factory()->create();
        $original = $this->makeOriginalSession(['status' => 'IZIN_RESCHEDULE']);
        $admin    = $this->adminUser();

        // Buat Part 1 terlebih dahulu agar request Part 2 valid
        ClassSession::factory()->create([
            'origin_session_id' => $original->id,
            'split_part'        => 1,
            'teacher_id'        => $original->teacher_id,
            'student_id'        => $original->student_id,
            'enrollment_id'     => $original->enrollment_id,
            'session_date'      => '2026-06-05',
            'honor_code'        => 'H_SPLIT',
            'honor_amount'      => 21250,
        ]);

        // Buat sesi blocking yang memakai ruangan yang sama di jam yang sama
        // end_time harus lebih dari start_time sesi baru agar overlap terdeteksi
        ClassSession::factory()->create([
            'room_id'      => $room->id,
            'session_date' => '2026-06-12',
            'start_time'   => '15:00:00',
            'end_time'     => '15:15:00',
            'status'       => 'SCHEDULED',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('absensi.split', [$original->id, 2]), [
                'replacement_date'    => '2026-06-12',
                'replacement_time'    => '15:00',
                'replacement_room_id' => $room->id,
            ]);

        // Konflik ruang harus terdeteksi → HTTP 422
        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }
}
