# Pisah Metode Bayar di Laporan Keuangan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menampilkan 4 baris metode pembayaran (CASH, TRANSFER, QRIS, DEBIT) secara terpisah di laporan keuangan, menggantikan kalkulasi `$revenueTransfer = $totalRevenue - $revenueCash` yang salah.

**Architecture:** Ganti 2 variabel scalar di ReportController dengan 1 query `groupBy('method')` yang menghasilkan Collection keyed by method name. View membaca Collection tersebut dengan `?? 0` untuk method yang tidak ada transaksinya di bulan tersebut.

**Tech Stack:** Laravel 11, Blade, PHPUnit (Feature test), Tailwind CSS

---

## File Map

| File | Aksi | Perubahan |
|------|------|-----------|
| `app/Http/Controllers/ReportController.php` | Modify (baris 54–59 & compact) | Ganti 2 variabel dengan `$revenueByMethod` groupBy query |
| `resources/views/reports/finance.blade.php` | Modify (baris 100–107) | Ganti 2 baris (Cash/Transfer) dengan 4 baris (Cash/Transfer/QRIS/Debit) |
| `tests/Feature/ReportFinanceMethodTest.php` | Create | Feature test untuk breakdown metode bayar |

---

## Task 1: Tulis Failing Test

**Files:**
- Create: `tests/Feature/ReportFinanceMethodTest.php`

- [ ] **Step 1: Buat file test baru**

```php
<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test breakdown metode bayar di laporan keuangan.
 * Memastikan QRIS dan DEBIT tampil terpisah (bukan digabung ke Transfer).
 */
class ReportFinanceMethodTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->owner->assignRole('Owner');
    }

    /** @test */
    public function laporan_menampilkan_empat_metode_bayar_terpisah(): void
    {
        $year  = 2026;
        $month = 5;
        $date  = "2026-05-15";

        // Buat invoice dummy untuk masing-masing payment
        $invoiceA = Invoice::factory()->create(['year' => $year, 'month' => $month, 'total_amount' => 500000, 'paid_amount' => 500000, 'status' => 'PAID']);
        $invoiceB = Invoice::factory()->create(['year' => $year, 'month' => $month, 'total_amount' => 400000, 'paid_amount' => 400000, 'status' => 'PAID']);
        $invoiceC = Invoice::factory()->create(['year' => $year, 'month' => $month, 'total_amount' => 300000, 'paid_amount' => 300000, 'status' => 'PAID']);
        $invoiceD = Invoice::factory()->create(['year' => $year, 'month' => $month, 'total_amount' => 200000, 'paid_amount' => 200000, 'status' => 'PAID']);

        Payment::factory()->create(['invoice_id' => $invoiceA->id, 'method' => 'CASH',     'amount' => 500000, 'payment_date' => $date, 'voided_at' => null]);
        Payment::factory()->create(['invoice_id' => $invoiceB->id, 'method' => 'TRANSFER', 'amount' => 400000, 'payment_date' => $date, 'voided_at' => null]);
        Payment::factory()->create(['invoice_id' => $invoiceC->id, 'method' => 'QRIS',     'amount' => 300000, 'payment_date' => $date, 'voided_at' => null]);
        Payment::factory()->create(['invoice_id' => $invoiceD->id, 'method' => 'DEBIT',    'amount' => 200000, 'payment_date' => $date, 'voided_at' => null]);

        $response = $this->actingAs($this->owner)
            ->get(route('reports.finance', ['year' => $year, 'month' => $month]));

        $response->assertStatus(200);

        // View harus menerima revenueByMethod (Collection), bukan revenueCash/revenueTransfer
        $response->assertViewHas('revenueByMethod');

        $byMethod = $response->viewData('revenueByMethod');
        $this->assertEquals(500000, $byMethod['CASH']     ?? 0, 'CASH salah');
        $this->assertEquals(400000, $byMethod['TRANSFER'] ?? 0, 'TRANSFER salah');
        $this->assertEquals(300000, $byMethod['QRIS']     ?? 0, 'QRIS salah');
        $this->assertEquals(200000, $byMethod['DEBIT']    ?? 0, 'DEBIT salah');
    }

    /** @test */
    public function method_tanpa_transaksi_tidak_error_di_view(): void
    {
        // Bulan kosong tanpa payment apapun — view harus tampil Rp 0 untuk semua method
        $response = $this->actingAs($this->owner)
            ->get(route('reports.finance', ['year' => 2026, 'month' => 1]));

        $response->assertStatus(200);
        $response->assertViewHas('revenueByMethod');

        $byMethod = $response->viewData('revenueByMethod');
        $this->assertEquals(0, $byMethod['CASH']     ?? 0);
        $this->assertEquals(0, $byMethod['TRANSFER'] ?? 0);
        $this->assertEquals(0, $byMethod['QRIS']     ?? 0);
        $this->assertEquals(0, $byMethod['DEBIT']    ?? 0);
    }

    /** @test */
    public function payment_void_tidak_masuk_revenueByMethod(): void
    {
        $invoice = Invoice::factory()->create(['year' => 2026, 'month' => 5, 'total_amount' => 100000]);

        // Payment void — tidak boleh masuk ke revenueByMethod
        Payment::factory()->create([
            'invoice_id'   => $invoice->id,
            'method'       => 'QRIS',
            'amount'       => 100000,
            'payment_date' => '2026-05-10',
            'voided_at'    => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('reports.finance', ['year' => 2026, 'month' => 5]));

        $byMethod = $response->viewData('revenueByMethod');
        $this->assertEquals(0, $byMethod['QRIS'] ?? 0, 'Payment void tidak boleh dihitung');
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test tests/Feature/ReportFinanceMethodTest.php --no-coverage
```

Expected output (fail karena controller masih kirim `revenueCash`, bukan `revenueByMethod`):
```
FAILED  Tests\Feature\ReportFinanceMethodTest > laporan_menampilkan_empat_metode_bayar_terpisah
Expected response view to have data for key [revenueByMethod].
```

---

## Task 2: Update ReportController

**Files:**
- Modify: `app/Http/Controllers/ReportController.php`

- [ ] **Step 3: Ganti kalkulasi metode bayar di method `finance()`**

Buka `app/Http/Controllers/ReportController.php`. Temukan baris 54–59 (section breakdown cash vs transfer):

```php
// Breakdown cash vs transfer
$revenueCash     = Payment::whereNull('voided_at')
    ->where('method', 'CASH')
    ->whereYear('payment_date', $year)
    ->whereMonth('payment_date', $month)
    ->sum('amount');
$revenueTransfer = $totalRevenue - $revenueCash;
```

**Ganti** dengan:

```php
// Breakdown per metode bayar (CASH, TRANSFER, QRIS, DEBIT)
$revenueByMethod = Payment::whereNull('voided_at')
    ->whereYear('payment_date', $year)
    ->whereMonth('payment_date', $month)
    ->selectRaw('method, SUM(amount) as total')
    ->groupBy('method')
    ->pluck('total', 'method');
```

- [ ] **Step 4: Update `compact()` di return statement**

Temukan baris `return view('reports.finance', compact(...))` di akhir method.

Ganti `'revenueCash', 'revenueTransfer'` dengan `'revenueByMethod'`:

```php
return view('reports.finance', compact(
    'year', 'month', 'monthName',
    'revenueByType', 'totalRevenue', 'revenueByMethod',
    'honorSlips', 'totalHonor', 'honorPaid',
    'expenseByCategory', 'totalPengeluaran',
    'labaBersih',
    'invoiceStats',
    'availableMonths',
));
```

- [ ] **Step 5: Jalankan test — pastikan PASS**

```bash
php artisan test tests/Feature/ReportFinanceMethodTest.php --no-coverage
```

Expected output:
```
PASS  Tests\Feature\ReportFinanceMethodTest
✓ laporan menampilkan empat metode bayar terpisah
✓ method tanpa transaksi tidak error di view
✓ payment void tidak masuk revenueByMethod
```

---

## Task 3: Update View finance.blade.php

**Files:**
- Modify: `resources/views/reports/finance.blade.php`

- [ ] **Step 6: Ganti 2 baris (Cash/Transfer) dengan 4 baris**

Buka `resources/views/reports/finance.blade.php`. Temukan section (~baris 100–107):

```blade
<div class="flex justify-between">
    <span class="text-mk-muted">Cash</span>
    <span class="font-mono">Rp {{ number_format($revenueCash, 0, ',', '.') }}</span>
</div>
<div class="flex justify-between">
    <span class="text-mk-muted">Transfer</span>
    <span class="font-mono">Rp {{ number_format($revenueTransfer, 0, ',', '.') }}</span>
</div>
```

**Ganti** dengan:

```blade
<div class="flex justify-between">
    <span class="text-mk-muted">Cash</span>
    <span class="font-mono">Rp {{ number_format($revenueByMethod['CASH'] ?? 0, 0, ',', '.') }}</span>
</div>
<div class="flex justify-between">
    <span class="text-mk-muted">Transfer</span>
    <span class="font-mono">Rp {{ number_format($revenueByMethod['TRANSFER'] ?? 0, 0, ',', '.') }}</span>
</div>
<div class="flex justify-between">
    <span class="text-mk-muted">QRIS</span>
    <span class="font-mono">Rp {{ number_format($revenueByMethod['QRIS'] ?? 0, 0, ',', '.') }}</span>
</div>
<div class="flex justify-between">
    <span class="text-mk-muted">Debit</span>
    <span class="font-mono">Rp {{ number_format($revenueByMethod['DEBIT'] ?? 0, 0, ',', '.') }}</span>
</div>
```

- [ ] **Step 7: Clear view cache**

```bash
php artisan view:clear
```

- [ ] **Step 8: Jalankan seluruh test suite**

```bash
php artisan test --no-coverage
```

Expected: semua test pass, tidak ada regresi.

---

## Task 4: Verifikasi Manual & Commit

- [ ] **Step 9: Buka laporan di browser**

Buka `http://localhost/musik-kita-ops/public/reports/finance` (atau IP Laragon).

Pastikan:
- Section "Pendapatan per Jenis" menampilkan 4 baris: Cash, Transfer, QRIS, Debit
- Angka Cash + Transfer + QRIS + Debit = Total Pendapatan di P&L
- Tidak ada error PHP di halaman

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/ReportController.php \
        resources/views/reports/finance.blade.php \
        tests/Feature/ReportFinanceMethodTest.php \
        docs/superpowers/specs/2026-05-28-pisah-metode-bayar-laporan-design.md \
        docs/superpowers/plans/2026-05-28-pisah-metode-bayar-laporan.md
git commit -m "M09: Pisah breakdown metode bayar CASH/TRANSFER/QRIS/DEBIT di laporan keuangan"
```

---

## Checklist Akhir

- [ ] Test `ReportFinanceMethodTest` → 3 test pass
- [ ] Tidak ada variabel `$revenueCash` / `$revenueTransfer` tersisa di controller
- [ ] Tidak ada variabel `$revenueCash` / `$revenueTransfer` tersisa di view
- [ ] Tampilan browser: 4 baris metode bayar muncul
- [ ] Jumlah 4 baris = `$totalRevenue` (P&L tidak berubah)
- [ ] Commit berhasil
