<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     * @param  string|null  $classType          Snapshot class_type paket murid (opsional).
     * @param  string       $paymentMode        FULL atau INSTALLMENT (default FULL).
     * @param  int|null     $installmentNumber  Nomor termin 1/2/3 (hanya untuk INSTALLMENT).
     * @param  string|null  $installmentGroupId UUID pengikat 3 invoice cicilan (hanya untuk INSTALLMENT).
     * @param  int|null     $enrollmentId       FK ke enrollment spesifik (multi-kelas: 1 invoice per enrollment).
     */
    public function createOneOff(
        Student $student,
        array $items,
        ?string $description = null,
        ?Carbon $dueDate = null,
        ?Carbon $issuedAt = null,
        ?string $classType = null,
        string $paymentMode = Invoice::MODE_FULL,
        ?int $installmentNumber = null,
        ?string $installmentGroupId = null,
        ?int $enrollmentId = null,
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

        return DB::transaction(function () use (
            $student, $items, $description, $year, $month, $dueDate, $issuedAt,
            $classType, $paymentMode, $installmentNumber, $installmentGroupId, $enrollmentId
        ) {
            $total = array_sum(array_column($items, 'amount'));

            $invoice = Invoice::create([
                'invoice_number'      => $this->generateNumber($year, $month, 'INV'),
                'student_id'          => $student->id,
                'enrollment_id'       => $enrollmentId,
                'year'                => $year,
                'month'               => $month,
                'description'         => $description ?? $this->summarizeItems($items),
                'total_amount'        => $total,
                'paid_amount'         => 0,
                'status'              => Invoice::STATUS_UNPAID,
                'due_date'            => $dueDate->toDateString(),
                'issued_at'           => $issuedAt->toDateString(),
                'class_type'          => $classType,
                'payment_mode'        => $paymentMode,
                'installment_number'  => $installmentNumber,
                'installment_group_id'=> $installmentGroupId,
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
     * Generate 3 invoice cicilan untuk murid KIDS_CLASS_BUNDLE (BR-10.10).
     *
     * Nominal:
     *   Termin 1 & 2 : intdiv(total, 3)
     *   Termin 3     : sisa (total - termin1 - termin2) agar jumlah selalu tepat.
     *
     * Due date per termin:
     *   Termin 1 : tanggal 10 bulan aktivasi (bulan ke-1)
     *   Termin 2 : tanggal 10 bulan ke-2
     *   Termin 3 : tanggal 10 bulan ke-4
     *
     * @return Invoice[]  Array 3 invoice berurutan termin 1, 2, 3.
     */
    public function createKidsBundleInstallments(
        Student $student,
        Enrollment $enrollment,
        Carbon $startDate,
    ): array {
        $package  = $enrollment->package;
        $total    = $package->price_per_month;
        $groupId  = Str::uuid()->toString();

        $termin1 = intdiv($total, 3);
        $termin2 = intdiv($total, 3);
        $termin3 = $total - $termin1 - $termin2;

        // Offset bulan per termin: bulan ke-1, ke-2, ke-4 (index 0-based: 0, 1, 3).
        $offsets = [0, 1, 3];

        // Outer transaction: jika salah satu termin gagal dibuat, seluruh batch dibatalkan.
        // Tanpa ini, termin 1 bisa ter-commit sementara termin 2/3 gagal — meninggalkan
        // data parsial yang memblokir re-run generator (Bug 2 fix).
        return DB::transaction(function () use ($student, $enrollment, $startDate, $package, $termin1, $termin2, $termin3, $groupId, $offsets) {
            $invoices = [];

            foreach ($offsets as $i => $offset) {
                $terminNo = $i + 1;
                $amount   = [$termin1, $termin2, $termin3][$i];

                $issuedAt = $startDate->copy()->addMonths($offset)->startOfMonth();
                $dueDate  = $issuedAt->copy()->setDay(self::DUE_DAY)->endOfDay();

                // Jika due date sudah lewat hari ini (mis. aktivasi setelah tgl 10),
                // geser ke akhir bulan agar murid tidak langsung berstatus overdue.
                if ($dueDate->lt(now()->startOfDay())) {
                    $dueDate = $issuedAt->copy()->endOfMonth();
                }

                $invoices[] = $this->createOneOff(
                    student: $student,
                    items: [[
                        'code'        => 'SPP',
                        'description' => "SPP Kids Class Bundle – Termin {$terminNo}/3",
                        'amount'      => $amount,
                        'metadata'    => ['package_id' => $package->id, 'installment_number' => $terminNo],
                    ]],
                    description: "Kids Class Bundle – Termin {$terminNo}/3",
                    dueDate: $dueDate,
                    issuedAt: $issuedAt,
                    classType: 'KIDS_CLASS_BUNDLE',
                    paymentMode: Invoice::MODE_INSTALLMENT,
                    installmentNumber: $terminNo,
                    installmentGroupId: $groupId,
                    enrollmentId: $enrollment->id, // Bug 1 fix: terikat ke enrollment agar idempotency guard bekerja
                );
            }

            return $invoices;
        });
    }

    /**
     * Generate SPP bulanan untuk semua enrollment ACTIVE murid Aktif.
     *
     * Multi-kelas: 1 murid dengan 2 enrollment ACTIVE → 2 invoice SPP terpisah,
     * masing-masing terikat ke enrollment_id spesifik.
     *
     * Idempotent: cek berdasarkan (student_id, enrollment_id, year, month) + item SPP.
     * Jalankan berkali-kali dalam bulan yang sama tetap aman.
     *
     * @return array{created:int, skipped:int}
     */
    public function generateMonthlySPP(int $year, int $month): array
    {
        $issuedAt = Carbon::create($year, $month, 1)->startOfMonth();
        $dueDate  = $issuedAt->copy()->day(self::DUE_DAY)->endOfDay();

        $report = ['created' => 0, 'skipped' => 0];

        // Ambil semua murid Aktif yang punya minimal 1 enrollment ACTIVE.
        // status = ACTIVE adalah sumber kebenaran tunggal — whereNull('end_date') tidak diperlukan
        // dan justru berbahaya: enrollment ACTIVE yang kebetulan punya end_date (bug data) akan
        // lolos tanpa peringatan, murid tidak dapat invoice SPP tanpa ada error apapun.
        $students = Student::where('status', 'Aktif')
            ->whereHas('enrollments', fn ($q) => $q->where('status', 'ACTIVE'))
            ->with(['enrollments' => fn ($q) => $q->where('status', 'ACTIVE')->with('package.instrument')])
            ->get();

        foreach ($students as $student) {
            // Loop per enrollment — multi-kelas: 1 murid bisa dapat 2+ invoice SPP
            foreach ($student->enrollments as $enrollment) {
                if (!$enrollment->package) continue;

                $package = $enrollment->package;

                // KIDS_CLASS_BUNDLE tidak kena SPP bulanan — tagihan mereka sudah
                // di-generate sebagai 3 cicilan saat aktivasi (BR-10.10).
                if ($package->class_type === 'KIDS_CLASS_BUNDLE') {
                    $report['skipped']++;
                    continue;
                }

                // Idempotency: cek per (student, enrollment, year, month) + item SPP.
                // Beda dari versi lama yang cek per (student, year, month) saja —
                // versi baru memastikan setiap enrollment dapat invoice sendiri.
                $exists = Invoice::where('student_id', $student->id)
                    ->where('enrollment_id', $enrollment->id)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->whereHas('items', fn ($q) => $q->where('item_code', 'SPP'))
                    ->exists();

                if ($exists) {
                    $report['skipped']++;
                    continue;
                }

                // Deskripsi invoice menyertakan nama instrumen agar mudah dibedakan
                // ketika 1 murid punya 2+ invoice di bulan yang sama.
                $instrNama   = $package->instrument->name ?? $package->code;
                $description = "SPP {$instrNama} — " . $issuedAt->translatedFormat('F Y');

                $this->createOneOff(
                    student:      $student,
                    items: [[
                        'code'        => 'SPP',
                        'description' => $description,
                        'amount'      => $package->price_per_month,
                        'metadata'    => [
                            'package_id'    => $package->id,
                            'enrollment_id' => $enrollment->id,
                        ],
                    ]],
                    description:  $description,
                    dueDate:      $dueDate,
                    issuedAt:     $issuedAt,
                    classType:    $package->class_type,
                    enrollmentId: $enrollment->id,
                );

                $report['created']++;
            }
        }

        return $report;
    }

    /**
     * Recalculate paid_amount + status berdasarkan validPayments.
     * Dipanggil setelah PaymentService::recordPayment / voidPayment.
     *
     * Aturan PARTIAL:
     *   - Hanya invoice KIDS_CLASS_BUNDLE yang boleh berstatus PARTIAL.
     *   - Invoice lain: jika bayar < total karena void, kembali ke UNPAID (tidak PARTIAL).
     *     Kondisi ini seharusnya tidak terjadi karena PaymentService memblokir partial,
     *     tapi sebagai safety net tetap ditangani di sini.
     */
    public function recalcStatus(Invoice $invoice): Invoice
    {
        // Selalu sync total_amount dari items agar tidak corrupt jika item dihapus manual
        $invoice->update(['total_amount' => $invoice->items()->sum('amount')]);
        $invoice->refresh();

        $paid = $invoice->validPayments()->sum('amount');

        // Setiap invoice harus dibayar penuh — tidak ada status PARTIAL dalam alur normal.
        // PARTIAL tidak akan terjadi karena PaymentService memblokir pembayaran kurang dari saldo.
        // Safety net: jika karena sebab apapun paid < total, status kembali ke UNPAID.
        $status = match (true) {
            $invoice->status === Invoice::STATUS_VOID => Invoice::STATUS_VOID,
            $paid <= 0                                => Invoice::STATUS_UNPAID,
            $paid >= $invoice->total_amount           => Invoice::STATUS_PAID,
            default                                   => Invoice::STATUS_UNPAID,
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
