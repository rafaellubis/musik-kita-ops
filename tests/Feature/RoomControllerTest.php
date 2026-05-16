<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoomControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    private function ownerUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');
        return $user;
    }

    public function test_owner_dapat_buat_ruangan_dengan_supported_instruments(): void
    {
        $owner = $this->ownerUser();
        Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);
        Instrument::create(['name' => 'Gitar', 'code' => 'GITAR', 'is_active' => true, 'sort_order' => 2]);

        $response = $this->actingAs($owner)->post(route('rooms.store'), [
            'code'                  => 'TEST',
            'name'                  => 'Studio Test',
            'capacity'              => 1,
            'supported_instruments' => ['Piano', 'Gitar'],
            'is_active'             => '1',
        ]);

        $response->assertRedirect(route('rooms.index'));
        $room = Room::where('code', 'TEST')->first();
        $this->assertNotNull($room);
        $this->assertContains('Piano', $room->supported_instruments);
        $this->assertContains('Gitar', $room->supported_instruments);
    }

    public function test_supported_instruments_tersimpan_sebagai_array_bukan_string(): void
    {
        $owner = $this->ownerUser();
        Instrument::create(['name' => 'Drum', 'code' => 'DRUM', 'is_active' => true, 'sort_order' => 3]);

        $this->actingAs($owner)->post(route('rooms.store'), [
            'code'                  => 'R99',
            'name'                  => 'Studio Drum',
            'capacity'              => 1,
            'supported_instruments' => ['Drum'],
        ]);

        $room = Room::where('code', 'R99')->first();
        $this->assertIsArray($room->supported_instruments);
    }

    public function test_update_ruangan_hapus_instrumen_tidak_trigger_warning_tanpa_jadwal(): void
    {
        $owner = $this->ownerUser();
        Instrument::create(['name' => 'Piano', 'code' => 'PIANO', 'is_active' => true, 'sort_order' => 1]);

        $room = Room::create([
            'code'                  => 'R2',
            'name'                  => 'Studio 2',
            'capacity'              => 1,
            'supported_instruments' => ['Piano'],
            'is_active'             => true,
        ]);

        // Hapus Piano dari fasilitas — tidak ada jadwal aktif, tidak ada warning
        $response = $this->actingAs($owner)->put(route('rooms.update', $room), [
            'code'                  => 'R2',
            'name'                  => 'Studio 2',
            'capacity'              => 1,
            'supported_instruments' => [],
        ]);

        $response->assertRedirect(route('rooms.index'));
        $response->assertSessionMissing('warning');
    }

    public function test_kolom_boolean_lama_tidak_ada_lagi(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('rooms', 'has_piano'),
            'Kolom has_piano seharusnya sudah dihapus oleh migration.'
        );
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('rooms', 'has_drum'),
            'Kolom has_drum seharusnya sudah dihapus oleh migration.'
        );
    }
}
