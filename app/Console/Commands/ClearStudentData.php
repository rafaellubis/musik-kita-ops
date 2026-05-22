<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Command untuk menghapus semua data murid dan turunannya dari database.
 * Master data (paket, guru, ruang, instrumen, user) TIDAK disentuh.
 *
 * Gunakan sebelum import data real dari Excel.
 * Jalankan: php artisan db:clear-students
 */
class ClearStudentData extends Command
{
    protected $signature   = 'db:clear-students';
    protected $description = 'Hapus semua data murid (students, enrollments, invoices, dll) — master data tetap aman';

    /**
     * Tabel yang akan di-truncate beserta label tampilannya.
     * Urutan mengikuti dependency FK (dari paling dalam ke luar).
     */
    private array $tables = [
        'event_participants'       => 'Peserta Event',
        'payments'                 => 'Pembayaran',
        'invoice_items'            => 'Item Invoice',
        'invoices'                 => 'Invoice / Tagihan',
        'class_sessions'           => 'Sesi Kelas',
        'schedules'                => 'Jadwal Mingguan',
        'student_status_histories' => 'Riwayat Status Murid',
        'enrollments'              => 'Enrollment / Kelas',
        'students'                 => 'Data Murid',
        'audit_logs'               => 'Audit Log',
    ];

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=yellow;options=bold>⚠  HAPUS DATA MURID</>');
        $this->newLine();

        // Tampilkan jumlah data yang akan dihapus per tabel
        $this->line('  Data yang akan dihapus:');
        $totalRows = 0;
        foreach ($this->tables as $table => $label) {
            $count = DB::table($table)->count();
            $totalRows += $count;
            $color = $count > 0 ? 'red' : 'gray';
            $this->line(sprintf(
                '    <fg=%s>%-30s %d baris</>',
                $color,
                $label,
                $count
            ));
        }

        $this->newLine();

        if ($totalRows === 0) {
            $this->info('  Tidak ada data untuk dihapus. Database sudah bersih.');
            return self::SUCCESS;
        }

        $this->line("  Total: <fg=red;options=bold>{$totalRows} baris</> akan dihapus.");
        $this->newLine();
        $this->line('  Master data (paket, guru, ruang, instrumen, user) <fg=green>TIDAK DISENTUH</>.');
        $this->newLine();

        // Konfirmasi eksplisit — batalkan jika user ketik selain 'yes'
        if (!$this->confirm('Lanjutkan hapus semua data murid di atas?', false)) {
            $this->line('  Dibatalkan. Tidak ada yang dihapus.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('  Menghapus data...');

        // Disable FK checks agar truncate bisa dilakukan tanpa urutan strict
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($this->tables as $table => $label) {
                DB::table($table)->truncate();
                $this->line("  <fg=green>✓</> {$label}");
            }
        } finally {
            // Pastikan FK checks selalu di-enable kembali meski ada error
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->info('  Selesai. Semua data murid telah dihapus.');
        $this->line('  Sekarang bisa import data real via menu <fg=cyan>Import Murid</>.');
        $this->newLine();

        return self::SUCCESS;
    }
}
