<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SessionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SessionGenerateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
    }

    public function test_owner_can_trigger_session_generate_with_year_and_month(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        $mock = Mockery::mock(SessionGeneratorService::class);
        $mock->shouldReceive('generateForMonth')
            ->once()
            ->with(2026, 7)
            ->andReturn([
                'created'                  => 10,
                'skipped_libur'            => 2,
                'skipped_exists'           => 0,
                'skipped_conflict_details' => [],
            ]);
        $this->app->instance(SessionGeneratorService::class, $mock);

        $response = $this->actingAs($owner)->post(route('sessions.generate'), [
            'year'  => 2026,
            'month' => 7,
        ]);

        $response->assertRedirect(route('sessions.index', ['year' => 2026, 'month' => 7]));
        $response->assertSessionHas('success');
        $response->assertSessionHasNoErrors();
    }

    public function test_generate_rejects_missing_year_and_month(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('Owner');

        $response = $this->actingAs($owner)->post(route('sessions.generate'), []);

        $response->assertSessionHasErrors(['year', 'month']);
    }
}
