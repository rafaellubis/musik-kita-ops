<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pusat logika invoice (M05).
 *
 * Tanggung jawab:
 *   - Generate nomor invoice INV/YYYY/MM/NNNN (BR-5.16, reset per bulan)
 *   - Buat invoice satu-kali (REG, CUTI, UJI, MC, KIDS_FP)
 *   - Buat invoice SPP bulanan untuk semua murid Aktif (BR-5.1)
 *   - Recalculate status berdasarkan paid_amount (UNPAID/PARTIAL/PAID)
 *   - Apply denda harian Rp 5.000/hari mulai tanggal 11 (BR-5.3)
 */
class InvoiceService
{
    public const FEE_REG       = 250000;
    public const FEE_CUTI      = 100000;
    public const FEE_KIDS_FP   = 140000;
    public const FEE_UJI       = 395000;
    public const FEE_MC        = 295000;
    public const FINE_PER_DAY  = 5000;
    public const FINE_START_DAY = 11; // mulai tanggal 11 (BR-5.3)
    public const DUE_DAY       = 10;  // jatuh tempo SPP (BR-5.2)

    /**
     * Generate nomor invoice unik untuk bulan target.
     * Format: INV/YYYY/MM/NNNN (NNNN reset per bulan).
     */
    public function generateNumber(int $year, int $month, string $prefix = 'INV'): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        // Cari nomor terakhir untuk prefix + year + month
        $latest = Invoice::where('invoice_number', 'like', "{$prefix}/{$year}/{$monthStr}/%")
            ->orderBy('invoice_number', 'desc')
            ->value('invoice_number');

        $nextSeq = 1;
        if ($latest) {
            $parts = explode('/', $latest);
            $nextSeq = ((int) end($parts)) + 1;
        }

        return sprintf('%s/%d/%s/%04d', $prefix, $year, $monthStr, $nextSeq);
    }

    /**
     * Bikin invoice satu-kali (one-off) dengan items list.
     * Cocok untuk: REG, CUTI, UJI, MC, KIDS_FP, atau gabungan REG+SPP.
     *
     * @param  array<int,array{code:string,description:string,amount:int,metadata?:array}>  $items
     */
    public function createOneOff(
        Student $student,
        array $items,
        ?string $description = null,
        ?Carbon $dueDate = null,
        ?Carbon $issuedAt = null,
    ): Invoice {
        if (empty($items)) {
            throw new \InvalidArgumentException('Items invoice tidak boleh kosong.');
        }

        $issuedAt ??= now();
        $dueDate ??= $issuedAt->copy()->day(self::DUE_DAY)->endOfDay();
        // Kalau sudah lewat tanggal 10 saat issue, due date pindah ke akhir bulan.
        if ($dueDate->lt($issuedAt)) {
            $dueDate = $issuedAt->copy()->endOfMonth();
        }

        $year = $issuedAt->year;
        $month = $issuedAt->month;

        return DB::transaction(function () use ($student, $items, $description, $year, $month, $dueDate, $issuedAt) {
            $total = array_sum(array_column($items, 'amount'));

            $invoice = Invoice::create([
                'invoice_number' => $this->generateNumber($year, $month, 'INV'),
                'student_id'     => $student->id,
                'year'           => $year,
                'month'          => $month,
                'description'    => $description ?? $this->summarizeItems($items),
                'total_amount'   => $total,
                'paid_amount'    => 0,
                'status'         => Invoice::STATUS_UNPAID,
                'due_date'       => $dueDate->toDateString(),
                'issued_at'      => $issuedAt->toDateString(),
            ]);

            foreach ($items as $item) {
                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'item_code'   => $item['code'],
                    'description' => $item['description'],
                    'amount'      => $item['amount'],
                    'metadata'    => $item['metadata'] ?? null,
                ]);
            }

            return $invoice->fresh('items');
        });
    }

    /**
     * Generate SPP bulanan untuk semua murid AKTIF yang punya enrollment ACTIVE.
     * Idempotent: kalau invoice SPP untuk (student, year, month) sudah ada, skip.
     *
     * @return array{created:int, skipped:int}
     */
    public function generateMonthlySPP(int $year, int $month): array
    {
        $issuedAt = Carbon::create($year, $month, 1)->startOfMonth();
        $dueDate = $issuedAt->copy()->day(self::DUE_DAY)->endOfDay();

        $report = ['created' => 0, 'skipped' => 0];

        // Murid Aktif dengan enrollment ACTIVE & punya paket
        $students = Student::where('status', 'Aktif')
            ->whereHas('enrollments', fn ($q) => $q->where('status', 'ACTIVE'))
            ->with(['enrollments' => fn ($q) => $q->where('status', 'ACTIVE')->with('package.instrument')])
            ->get();

        foreach ($students as $student) {
            $enrollment = $student->enrollments->first();
            if (!$enrollment || !$enrollment->package) continue;

            // Skip kalau invoice SPP untuk bulan ini sudah ada
            $exists = Invoice::where('student_id', $student->id)
                ->where('year', $year)
                ->where('month', $month)
                ->whereHas('items', fn ($q) => $q->where('item_code', 'SPP'))
                ->exists();

            if ($exists) {
                $report['skipped']++;
                continue;
            }

            $package = $enrollment->package;
            $this->createOneOff(
                student: $student,
                items: [[
                    'code'        => 'SPP',
                    'description' => "SPP {$package->code} {$package->instrument->name} "
                                     . $issuedAt->format('F Y'),
                    'amount'      => $package->price_per_month,
                    'metadata'    => ['package_id' => $package->id],
                ]],
                description: 'SPP ' . $issuedAt->format('F Y'),
                dueDate: $dueDate,
                issuedAt: $issuedAt,
            );
            $report['created']++;
        }

        return $report;
    }

    /**
     * Recalculate paid_amount + status berdasarkan validPayments.
     * Dipanggil setelah PaymentService::recordPayment / voidPayment.
     */
    public function recalcStatus(Invoice $invoice): Invoice
    {
        $paid = $invoice->validPayments()->sum('amount');

        $status = match (true) {
            $invoice->status === Invoice::STATUS_VOID => Invoice::STATUS_VOID,
            $paid <= 0                                => Invoice::STATUS_UNPAID,
            $paid >= $invoice->total_amount           => Invoice::STATUS_PAID,
            default                                   => Invoice::STATUS_PARTIAL,
        };

        $invoice->update([
            'paid_amount' => $paid,
            'status'      => $status,
        ]);

        return $invoice->fresh();
    }

    /**
     * Apply denda Rp 5.000/hari ke invoice UNPAID/PARTIAL bulan target,
     * berdasarkan jumlah hari telat (sejak tanggal 11).
     *
     * Idempotent: kalau item DENDA sudah ada, di-update jumlahnya
     * sesuai hari telat hari ini. Jadi dijalankan harian tetap aman.
     *
     * @return array{updated:int, processed:int}
     */
    public function applyLateFinesForMonth(int $year, int $month, ?Carbon $today = null): array
    {
        $today ??= now()->startOfDay();
        $report = ['updated' => 0, 'processed' => 0];

        // Hari telat: berapa hari setelah tanggal jatuh tempo (10).
        // Pakai startOfDay both sides + integer cast supaya tidak ada float.
        // Tanggal 11 = telat 1 hari, tanggal 15 = telat 5 hari, dst.
        $cutoff = Carbon::create($year, $month, self::DUE_DAY)->startOfDay();
        $today = $today->copy()->startOfDay();
        $daysLate = max(0, (int) $cutoff->diffInDays($today, false));

        if ($daysLate <= 0) {
            // Belum melewati tanggal jatuh tempo
            return $report;
        }

        $fineAmount = $daysLate * self::FINE_PER_DAY;

        $invoices = Invoice::forMonth($year, $month)
            ->unpaid()
            ->with('items')
            ->get();

        foreach ($invoices as $invoice) {
            $report['processed']++;

            DB::transaction(function () use ($invoice, $fineAmount, $daysLate) {
                $existingFine = $invoice->items()->where('item_code', 'DENDA')->first();

                if ($existingFine) {
                    if ($existingFine->amount === $fineAmount) {
                        return; // sudah pas, tidak perlu update
                    }
                    $existingFine->update([
                        'amount'      => $fineAmount,
                        'description' => "Denda keterlambatan ({$daysLate} hari × Rp 5.000)",
                        'metadata'    => ['days_late' => $daysLate],
                    ]);
                } else {
                    InvoiceItem::create([
                        'invoice_id'  => $invoice->id,
                        'item_code'   => 'DENDA',
                        'description' => "Denda keterlambatan ({$daysLate} hari × Rp 5.000)",
                        'amount'      => $fineAmount,
                        'metadata'    => ['days_late' => $daysLate],
                    ]);
                }

                // Recalc total_amount + status
                $newTotal = $invoice->items()->sum('amount');
                $invoice->update(['total_amount' => $newTotal]);
                $this->recalcStatus($invoice);
            });

            $report['updated']++;
        }

        return $report;
    }

    /**
     * Helper: ringkas list items jadi 1 string deskripsi.
     */
    private function summarizeItems(array $items): string
    {
        return collect($items)->pluck('code')->unique()->implode(' + ');
    }
}
