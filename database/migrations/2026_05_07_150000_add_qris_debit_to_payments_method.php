<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tambah 2 metode pembayaran baru ke kolom payments.method:
 *   - QRIS  (QR Code Indonesia Standard)
 *   - DEBIT (kartu debit)
 *
 * Pakai ALTER TABLE raw karena Laravel migration tidak punya helper
 * untuk modifikasi enum existing tanpa drop column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;
        DB::statement(
            "ALTER TABLE payments MODIFY COLUMN method ENUM('CASH', 'TRANSFER', 'QRIS', 'DEBIT') NOT NULL"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') return;
        // CATATAN: rollback akan FAIL kalau sudah ada row dengan method
        // QRIS/DEBIT. Bersihkan datanya dulu sebelum rollback.
        DB::statement(
            "ALTER TABLE payments MODIFY COLUMN method ENUM('CASH', 'TRANSFER') NOT NULL"
        );
    }
};
