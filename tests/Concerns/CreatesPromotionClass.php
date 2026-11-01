<?php

namespace Tests\Concerns;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

trait CreatesPromotionClass
{
    protected function promotionClass(): array
    {
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));
        Role::findOrCreate('Owner', 'web');
        $actor = User::factory()->create()->assignRole('Owner');
        $student = Student::factory()->create(['status' => 'Aktif']);
        $package = Package::factory()->create(['class_type' => 'REGULER', 'grade' => 'BASIC', 'duration_min' => 30, 'price_per_month' => 340000]);
        $target = Package::factory()->create(['instrument_id' => $package->instrument_id, 'class_type' => 'REGULER', 'grade' => 'L1', 'duration_min' => 30, 'price_per_month' => 370000]);
        $teacher = Teacher::factory()->create(['is_active' => true]);
        $teacher->instruments()->attach($package->instrument_id);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 1, 'supported_instruments' => [$package->instrument->name]]);
        $source = Enrollment::factory()->for($student)->create(['package_id' => $package->id, 'status' => 'ACTIVE', 'effective_date' => '2026-01-01', 'is_primary' => true]);
        $student->update(['primary_enrollment_id' => $source->id]);
        Schedule::factory()->for($source)->create(['day_of_week' => 1, 'start_time' => '10:00', 'end_time' => '10:30', 'room_id' => $room->id, 'is_active' => true]);
        $data = ['package_id' => $target->id, 'teacher_id' => $teacher->id, 'room_id' => $room->id, 'day_of_week' => 2, 'start_time' => '11:00', 'effective_date' => '2026-11-01', 'evaluation_note' => 'Siap naik level berdasarkan evaluasi guru.'];
        return compact('actor', 'student', 'source', 'package', 'target', 'teacher', 'room', 'data');
    }
}
