<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill honor_code dan honor_amount untuk sesi LIBUR yang sudah ada.
     * Semua LIBUR existing dianggap "libur nasional tanpa pengganti" (BR-4.10) karena
     * kolom replacement_date baru saja ditambah dan semua nilai-nya masih NULL.
     *
     * Kids Class (KIDS_CLASS / KIDS_CLASS_BUNDLE) dikecualikan — honor-nya
     * dihitung per jumlah murid aktif, tidak bisa di-backfill secara otomatis.
     */
    public function up(): void
    {
        // Set honor untuk sesi LIBUR paket reguler/hobby (bukan Kids Class)
        DB::statement("
            UPDATE class_sessions cs
            INNER JOIN enrollments e ON cs.enrollment_id = e.id
            INNER JOIN packages p ON e.package_id = p.id
            SET cs.honor_code   = 'H_LIBUR',
                cs.honor_amount = ROUND(p.price_per_month * 0.5 / 4)
            WHERE cs.status     = 'LIBUR'
            AND   cs.honor_code IS NULL
            AND   p.class_type  NOT IN ('KIDS_CLASS', 'KIDS_CLASS_BUNDLE')
        ");
    }

    public function down(): void
    {
        // Rollback: kembalikan honor LIBUR ke NULL
        DB::statement("
            UPDATE class_sessions
            SET honor_code   = NULL,
                honor_amount = NULL
            WHERE status     = 'LIBUR'
            AND   honor_code = 'H_LIBUR'
        ");
    }
};
