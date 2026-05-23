<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\User;
use App\Notifications\MuridOverdueNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deteksi murid Aktif dengan tunggakan >1 bulan dan kirim notifikasi ke Admin/Owner.
 * Dijadwalkan tgl 1 tiap bulan jam 06:05 (setelah generate-spp).
 * Idempotent: murid yang sudah punya notif pending bulan ini tidak akan dinotif ulang.
 */
class CheckOverdueStudents extends Command
{
    protected $signature   = 'students:check-overdue';
    protected $description = 'Kirim notifikasi murid Aktif dengan tunggakan >1 bulan ke Admin dan Owner';

    public function handle(): int
    {
        $today = now();

        // Query murid Aktif yang punya invoice UNPAID/PARTIAL dari bulan sebelumnya
        $overdueStudents = Student::where('status', 'Aktif')
            ->whereHas('invoices', function ($q) use ($today) {
                $q->whereIn('status', ['UNPAID', 'PARTIAL'])
                  ->where(function ($q) use ($today) {
                      // Invoice dari tahun sebelumnya, ATAU dari bulan sebelumnya di tahun ini
                      $q->where('year', '<', $today->year)
                        ->orWhere(function ($q) use ($today) {
                            $q->where('year', $today->year)
                              ->where('month', '<', $today->month);
                        });
                  });
            })
            ->get();

        if ($overdueStudents->isEmpty()) {
            $this->info('Tidak ada murid dengan tunggakan >1 bulan.');
            return self::SUCCESS;
        }

        // Idempotency: ambil student_id yang sudah punya notif pending bulan ini
        // Membaca dari tabel notifications yang sudah tersimpan (tanpa Notification::fake())
        $sudahDinotif = DB::table('notifications')
            ->where('type', MuridOverdueNotification::class)
            ->whereNull('read_at')
            ->whereYear('created_at', $today->year)
            ->whereMonth('created_at', $today->month)
            ->pluck('data')
            ->map(fn ($d) => json_decode($d, true)['student_id'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Filter: hanya murid yang belum dinotif bulan ini
        $muridBaru = $overdueStudents->reject(fn ($s) => in_array($s->id, $sudahDinotif));

        if ($muridBaru->isEmpty()) {
            $this->info('Semua murid overdue sudah dinotifikasi bulan ini.');
            return self::SUCCESS;
        }

        // Penerima: semua user berole Admin atau Owner
        $penerima = User::role(['Admin', 'Owner'])->get();

        $jumlah = 0;
        foreach ($muridBaru as $student) {
            // Hitung total tunggakan dari semua invoice overdue murid ini
            $totalOverdue = $student->invoices()
                ->whereIn('status', ['UNPAID', 'PARTIAL'])
                ->where(function ($q) use ($today) {
                    $q->where('year', '<', $today->year)
                      ->orWhere(function ($q) use ($today) {
                          $q->where('year', $today->year)
                            ->where('month', '<', $today->month);
                      });
                })
                ->get()
                ->sum(fn ($inv) => $inv->total_amount - $inv->paid_amount);

            // Label bulan sebelumnya dalam Bahasa Indonesia
            $bulanLabel = Carbon::create(
                $today->month > 1 ? $today->year : $today->year - 1,
                $today->month > 1 ? $today->month - 1 : 12,
                1
            )->translatedFormat('F Y');

            foreach ($penerima as $user) {
                $user->notify(new MuridOverdueNotification($student, (int) $totalOverdue, $bulanLabel));
            }

            $jumlah++;
            $this->line("  → Notif dikirim: {$student->full_name} ({$student->student_code})");
        }

        $this->info("Selesai: {$jumlah} murid dinotifikasi ke " . $penerima->count() . " user.");

        return self::SUCCESS;
    }
}
