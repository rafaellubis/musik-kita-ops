<?php

namespace Tests\Unit;

use App\Console\Commands\ClearStudentData;
use App\Services\ProtectedDatabaseGuard;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProtectedDatabaseGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        FreshCommand::prohibit(false);
        ClearStudentData::prohibit(false);

        parent::tearDown();
    }

    public function test_mk_operasional_is_protected(): void
    {
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', 'mk_operasional');
        Config::set('database.protected_databases', ['mk_operasional']);

        $this->assertTrue(ProtectedDatabaseGuard::currentDatabaseIsProtected());
    }

    public function test_fallback_protects_mk_operasional_when_config_list_empty(): void
    {
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', 'mk_operasional');
        Config::set('database.protected_databases', []);

        $this->assertSame(['mk_operasional'], ProtectedDatabaseGuard::protectedDatabaseNames());
        $this->assertTrue(ProtectedDatabaseGuard::currentDatabaseIsProtected());
    }

    public function test_sqlite_memory_is_not_protected(): void
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        $this->assertFalse(ProtectedDatabaseGuard::currentDatabaseIsProtected());
    }

    public function test_guard_inactive_during_unit_tests(): void
    {
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', 'mk_operasional');

        $this->assertFalse(ProtectedDatabaseGuard::isActive());
    }

    public function test_artisan_migrate_fresh_fails_when_prohibited(): void
    {
        FreshCommand::prohibit(true);

        $exitCode = Artisan::call('migrate:fresh', ['--force' => true]);

        $this->assertSame(1, $exitCode);
    }

    public function test_blocked_message_mentions_database_name(): void
    {
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', 'mk_operasional');

        $this->assertStringContainsString('mk_operasional', ProtectedDatabaseGuard::blockedMessage());
    }
}
