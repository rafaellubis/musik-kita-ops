<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportStudentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_kartu_murid_trial_mengikuti_bulan_dan_tahun_yang_dipilih(): void
    {
        $this->withoutVite();

        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('Admin');

        Student::factory()->create([
            'status'     => 'Trial',
            'trial_date' => '2026-07-05 10:00:00',
        ]);
        Student::factory()->create([
            'status'     => 'Aktif',
            'trial_date' => '2026-07-20 14:00:00',
        ]);
        Student::factory()->create([
            'status'     => 'Trial',
            'trial_date' => '2026-08-01 10:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('reports.students', ['year' => 2026, 'month' => 7]))
            ->assertOk()
            ->assertViewHas('muridTrial', 2)
            ->assertSeeTextInOrder([
                'Murid Baru Aktif',
                'Mundur / Selesai',
                'Murid Trial',
                'Murid Aktif',
                'Total Terdaftar',
            ]);
    }
}
