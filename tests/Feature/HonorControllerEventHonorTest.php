<?php

namespace Tests\Feature;

use App\Models\HonorSlip;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HonorControllerEventHonorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    private function ownerUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');
        return $user;
    }

    private function buatSlip(): HonorSlip
    {
        $teacher = Teacher::create([
            'code'      => 'THM',
            'name'      => 'Thomas',
            'is_active' => true,
        ]);

        return HonorSlip::create([
            'slip_number'     => 'SLIP/2026/05/0001',
            'teacher_id'      => $teacher->id,
            'month'           => 5,
            'year'            => 2026,
            'base_honor'      => 3_200_000,
            'event_honor'     => 0,
            'transport_honor' => 0,
            'other_honor'     => 0,
            'total_honor'     => 3_200_000,
            'status'          => HonorSlip::STATUS_CALCULATED,
            'created_by'      => null,
        ]);
    }

    public function test_owner_dapat_simpan_event_honor_ke_slip(): void
    {
        $owner = $this->ownerUser();
        $slip  = $this->buatSlip();

        $response = $this->actingAs($owner)->patch(route('honors.update', $slip), [
            'event_honor'      => 250_000,
            'event_honor_note' => 'Mini Concert Mei 2026',
            'transport_honor'  => 100_000,
            'other_honor'      => 0,
            'other_honor_note' => null,
        ]);

        $response->assertRedirect(route('honors.show', $slip));
        $response->assertSessionHas('success');

        $slip->refresh();
        $this->assertEquals(250_000,   $slip->event_honor);
        $this->assertEquals('Mini Concert Mei 2026', $slip->event_honor_note);
        $this->assertEquals(3_550_000, $slip->total_honor); // 3.2jt + 250k + 100k
    }

    public function test_event_honor_lebih_dari_nol_tanpa_keterangan_ditolak(): void
    {
        $owner = $this->ownerUser();
        $slip  = $this->buatSlip();

        $response = $this->actingAs($owner)->patch(route('honors.update', $slip), [
            'event_honor'      => 250_000,
            'event_honor_note' => '',   // ← kosong, harus ditolak
            'transport_honor'  => 0,
            'other_honor'      => 0,
            'other_honor_note' => null,
        ]);

        $response->assertSessionHasErrors('event_honor_note');
    }

    public function test_event_honor_nol_tanpa_keterangan_diterima(): void
    {
        $owner = $this->ownerUser();
        $slip  = $this->buatSlip();

        $response = $this->actingAs($owner)->patch(route('honors.update', $slip), [
            'event_honor'      => 0,
            'event_honor_note' => '',   // ← boleh kosong jika event_honor = 0
            'transport_honor'  => 0,
            'other_honor'      => 0,
            'other_honor_note' => null,
        ]);

        $response->assertRedirect(route('honors.show', $slip));
        $response->assertSessionHas('success');
    }

    public function test_slip_paid_tidak_bisa_diupdate(): void
    {
        $owner = $this->ownerUser();
        $slip  = $this->buatSlip();
        $slip->update(['status' => HonorSlip::STATUS_PAID]);

        $response = $this->actingAs($owner)->patch(route('honors.update', $slip), [
            'event_honor'      => 250_000,
            'event_honor_note' => 'Mini Concert',
            'transport_honor'  => 0,
            'other_honor'      => 0,
            'other_honor_note' => null,
        ]);

        $response->assertRedirect(route('honors.show', $slip));
        $response->assertSessionHas('error');
    }
}
