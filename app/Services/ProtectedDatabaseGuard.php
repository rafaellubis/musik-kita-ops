<?php

namespace App\Services;

use App\Console\Commands\ClearStudentData;
use Illuminate\Support\Facades\DB;

/**
 * Lindungi database operasional studio dari perintah Artisan destructive.
 *
 * Aktif otomatis saat DB_DATABASE cocok dengan daftar protected (default: mk_operasional).
 * Testing PHPUnit (SQLite :memory:) tidak terpengaruh.
 */
class ProtectedDatabaseGuard
{
    /** Database operasional default studio — selalu dilindungi meski config cache stale. */
    private const DEFAULT_PROTECTED = ['mk_operasional'];

    /** Command custom yang ikut diblokir (selain yang Laravel handle via DB::prohibitDestructiveCommands). */
    private const EXTRA_BLOCKED_COMMANDS = [
        ClearStudentData::class,
    ];

    /**
     * Guard aktif di environment ini?
     */
    public static function isActive(): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        if (! config('database.guard_destructive_commands', true)) {
            return false;
        }

        return self::currentDatabaseIsProtected();
    }

    /**
     * Nama database default saat ini termasuk daftar protected?
     */
    public static function currentDatabaseIsProtected(): bool
    {
        $connection = (string) config('database.default', '');
        $database = (string) config("database.connections.{$connection}.database", '');

        if ($database === '' || $database === ':memory:') {
            return false;
        }

        $protected = self::protectedDatabaseNames();

        return in_array(strtolower($database), array_map('strtolower', $protected), true);
    }

    /** @return list<string> */
    public static function protectedDatabaseNames(): array
    {
        $configured = config('database.protected_databases');

        if (! is_array($configured) || $configured === []) {
            return self::DEFAULT_PROTECTED;
        }

        return array_values(array_filter(array_map('trim', $configured)));
    }

    /** Label database default untuk pesan UI. */
    public static function protectedDatabaseLabel(): string
    {
        $connection = (string) config('database.default', '');

        return (string) config("database.connections.{$connection}.database", '');
    }

    /** Pesan penolakan (Bahasa Indonesia). */
    public static function blockedMessage(): string
    {
        return 'Perintah ini diblokir — database operasional «'
            . self::protectedDatabaseLabel()
            . '» dilindungi. Set DB_GUARD_DESTRUCTIVE=false di .env hanya jika benar-benar diperlukan (hati-hati!).';
    }

    /**
     * Terapkan larangan ke command Artisan yang destructive.
     */
    public static function apply(): void
    {
        if (! self::isActive()) {
            return;
        }

        // migrate:fresh, migrate:refresh, migrate:reset, migrate:rollback, db:wipe
        DB::prohibitDestructiveCommands(true);

        foreach (self::EXTRA_BLOCKED_COMMANDS as $commandClass) {
            $commandClass::prohibit(true);
        }
    }
}
