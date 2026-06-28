<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SessionGeneratorAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $this->owner = User::factory()->create()->assignRole('Owner');
    }

    public function test_artisan_generate_month_creates_audit_log_summary(): void
    {
        $this->artisan('sessions:generate-month', [
            '--year'  => 2026,
            '--month' => 8,
        ])->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'entity_label' => 'Generate Sesi Bulanan',
            'action'       => AuditLog::ACTION_CREATE,
            'user_id'      => $this->owner->id,
        ]);

        $log = AuditLog::query()->where('entity_label', 'Generate Sesi Bulanan')->first();
        $this->assertSame('scheduler', $log->new_values['source']);
        $this->assertSame(2026, $log->new_values['year']);
        $this->assertSame(8, $log->new_values['month']);
    }

    public function test_manual_generate_creates_audit_log_with_authenticated_user(): void
    {
        $response = $this->actingAs($this->owner)->post(route('sessions.generate'), [
            'year'  => 2026,
            'month' => 9,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'entity_label' => 'Generate Sesi Bulanan',
            'action'       => AuditLog::ACTION_CREATE,
            'user_id'      => $this->owner->id,
        ]);

        $log = AuditLog::query()->where('entity_label', 'Generate Sesi Bulanan')->first();
        $this->assertSame('manual', $log->new_values['source']);
        $this->assertSame(2026, $log->new_values['year']);
        $this->assertSame(9, $log->new_values['month']);
    }

    public function test_idempotent_run_still_creates_audit_log(): void
    {
        $this->actingAs($this->owner)->post(route('sessions.generate'), [
            'year'  => 2026,
            'month' => 10,
        ]);

        $countAfterFirst = AuditLog::where('entity_label', 'Generate Sesi Bulanan')->count();

        $this->actingAs($this->owner)->post(route('sessions.generate'), [
            'year'  => 2026,
            'month' => 10,
        ]);

        $this->assertSame($countAfterFirst + 1, AuditLog::where('entity_label', 'Generate Sesi Bulanan')->count());
    }
}
