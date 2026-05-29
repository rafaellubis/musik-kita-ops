<?php

use App\Models\User;
use App\Services\UserUsernameService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->unique()->after('email');
        });

        // Backfill username untuk user yang sudah ada
        User::query()->orderBy('id')->each(function (User $user) {
            if ($user->username) {
                return;
            }

            $user->username = UserUsernameService::generateUnique(null, $user->name, $user->id);
            $user->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
