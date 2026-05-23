<?php

use App\Models\ClassSession;
use App\Models\Enrollment;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill honor_code dan honor_amount untuk sesi LIBUR yang sudah ada.
     * Semua LIBUR existing dianggap "libur nasional tanpa pengganti" (BR-4.10) karena
     * kolom replacement_date baru saja ditambah dan semua nilai-nya masih NULL.
     *
     * Kids Class (KIDS_CLASS / KIDS_CLASS_BUNDLE) dikecualikan — honor-nya
     * dihitung per jumlah murid aktif, tidak bisa di-backfill secara otomatis.
     *
     * Catatan: Menggunakan Eloquent agar kompatibel dengan SQLite (testing)
     * dan MySQL (production) tanpa raw JOIN syntax yang tidak portabel.
     */
    public function up(): void
    {
        // Ambil semua sesi LIBUR yang belum punya honor_code
        $sessions = ClassSession::where('status', 'LIBUR')
            ->whereNull('honor_code')
            ->with('enrollment.package')
            ->get();

        foreach ($sessions as $session) {
            $package = $session->enrollment?->package;
            if (!$package) {
                continue;
            }

            // Skip Kids Class — honor dihitung per jumlah murid aktif
            if (in_array($package->class_type, ['KIDS_CLASS', 'KIDS_CLASS_BUNDLE'])) {
                continue;
            }

            $session->update([
                'honor_code'   => 'H_LIBUR',
                'honor_amount' => (int) round($package->price_per_month * 0.5 / 4),
            ]);
        }
    }

    public function down(): void
    {
        // Rollback: kembalikan honor LIBUR ke NULL
        ClassSession::where('status', 'LIBUR')
            ->where('honor_code', 'H_LIBUR')
            ->update([
                'honor_code'   => null,
                'honor_amount' => null,
            ]);
    }
};
