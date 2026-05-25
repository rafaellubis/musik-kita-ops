<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah unique index pada students.nickname.
 * NULL boleh banyak (perilaku standar MySQL unique pada nullable column).
 * Hanya nilai string yang harus unik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unique('nickname');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['nickname']);
        });
    }
};
