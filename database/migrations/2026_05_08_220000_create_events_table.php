<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel events: Mini Concert dan Ujian (M08).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('event_number', 30)->unique()->comment('EVT/YYYY/NNNN');
            $table->string('name', 100);
            $table->enum('type', ['MINI_CONCERT', 'UJIAN', 'MINI_CONCERT_UJIAN'])
                  ->default('MINI_CONCERT');
            $table->date('event_date');
            $table->text('notes')->nullable();
            $table->enum('status', ['DRAFT', 'COMPLETED'])->default('DRAFT');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
