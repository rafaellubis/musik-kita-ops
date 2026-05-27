<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite (dipakai di test environment) tidak mendukung sintaks MODIFY COLUMN.
        // MySQL di production mendukung penuh — skip saja saat SQLite.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE packages MODIFY class_type ENUM('REGULER','HOBBY','DUO','KIDS_CLASS','KIDS_CLASS_BUNDLE') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE packages MODIFY class_type ENUM('REGULER','HOBBY','KIDS_CLASS','KIDS_CLASS_BUNDLE') NOT NULL");
    }
};
