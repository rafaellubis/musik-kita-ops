# Pemisahan Pengeluaran & Petty Cash — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.
>
> **Setiap commit:** REQUIRED SUB-SKILL: `atomic-commit` (`.cursor/skills/atomic-commit/SKILL.md`) — satu commit = satu unit kerja, tests pass, **jangan** `git add .`

**Goal:** Memisahkan pengeluaran operasional dari petty cash: Owner mengisi saldo (masuk P&L), Admin mencatat pengeluaran petty cash (hanya mengurangi saldo), laporan keuangan dan dashboard tidak lagi mencampur tunai murid dengan kas kecil, plus **export PDF laporan petty cash per bulan** (cetak/Save as PDF via browser).

**Architecture:** Dua tabel baru (`petty_cash_topups`, `petty_cash_expenses`) + `PettyCashService` untuk saldo dan validasi. Modul `expenses` tetap untuk pengeluaran operasional (TRANSFER). P&L: `revenue − honor − staffPayroll − expenses(operasional) − SUM(topups)`; petty expenses hanya informatif di section terpisah.

**Tech Stack:** Laravel 11, Blade + Tailwind + Alpine.js, PHPUnit Feature tests, Spatie Permission RBAC, MySQL

**Keputusan bisnis (confirmed):**
- Isi saldo Owner = pengeluaran P&L (single hit)
- Pengeluaran petty cash Admin = tidak double-hit P&L
- Saldo petty cash independen dari pembayaran tunai murid

---

## Commit Protocol (Atomic Commit Skill)

**Setiap task yang berakhir dengan step Commit WAJIB mengikuti urutan ini.** Jangan lewati langkah; jangan campur file dari task lain dalam satu commit.

1. **Analyze** — cek hanya perubahan task ini:
   ```bash
   git status && git diff && git diff --staged
   ```
2. **Detect mixed concerns** — jika diff mencampur fitur/fix/refactor/docs dari task berbeda, **split** dulu; commit hanya setelah satu concern saja.
3. **Stage explicit** — daftar file di step Commit task ( **NO** `git add .` ):
   ```bash
   git add path/to/file1 path/to/file2
   ```
4. **Review staged**:
   ```bash
   git diff --staged
   ```
5. **Test** — jalankan test yang disebut di step Commit task; wajib PASS sebelum commit.
6. **Commit** — format proyek Musik KITA (bukan conventional `feat:` unless user minta):
   ```bash
   git commit -m "[Modul/Area] Deskripsi singkat satu perubahan"
   ```
7. **Verify**:
   ```bash
   git log -1 --oneline && git status
   ```
8. **Final review** (setelah semua task selesai):
   ```bash
   git log --oneline -n 9
   ```

**Atomic commit rules (dari skill):**
- Satu commit = satu fitur/fix/refactor lengkap; codebase tetap runnable
- Tests terkait task ikut di commit yang sama (TDD: test + impl satu task)
- Jangan campur kode + docs SRS kecuali docs hanya menjelaskan fitur yang sama di commit itu
- Revertable independently

---

## File Map

| File | Aksi | Tanggung jawab |
|------|------|----------------|
| `database/migrations/2026_06_13_000001_create_petty_cash_topups_table.php` | Create | Schema top-up Owner |
| `database/migrations/2026_06_13_000002_create_petty_cash_expenses_table.php` | Create | Schema expense Admin |
| `database/migrations/2026_06_13_000003_migrate_legacy_cash_expenses_to_petty_cash.php` | Create | Pindah `expenses` CASH → petty |
| `app/Models/PettyCashTopup.php` | Create | Model top-up |
| `app/Models/PettyCashExpense.php` | Create | Model petty expense |
| `app/Services/PettyCashService.php` | Create | Saldo, nomor, validasi |
| `app/Http/Controllers/PettyCashController.php` | Create | CRUD modul petty cash |
| `app/Http/Requests/StorePettyCashTopupRequest.php` | Create | Validasi top-up |
| `app/Http/Requests/StorePettyCashExpenseRequest.php` | Create | Validasi + cek saldo |
| `app/Http/Requests/UpdatePettyCashTopupRequest.php` | Create | Edit top-up Owner |
| `app/Http/Requests/UpdatePettyCashExpenseRequest.php` | Create | Edit expense |
| `resources/views/petty-cash/*.blade.php` | Create | UI modul petty cash |
| `resources/views/petty-cash/print.blade.php` | Create | Layout A4 printable laporan bulanan |
| `tests/Feature/PettyCashPrintTest.php` | Create | RBAC + konten PDF view |
| `routes/web.php` | Modify | Route + RBAC |
| `resources/views/layouts/navigation.blade.php` | Modify | Menu Petty Cash |
| `app/Http/Controllers/ExpenseController.php` | Modify | Hapus kalkulasi petty cash |
| `resources/views/expenses/index.blade.php` | Modify | Hapus widget petty cash |
| `resources/views/expenses/create.blade.php` | Modify | Default TRANSFER, sembunyikan CASH |
| `resources/views/expenses/edit.blade.php` | Modify | Idem create |
| `app/Http/Controllers/ReportController.php` | Modify | P&L terpisah |
| `resources/views/reports/finance.blade.php` | Modify | Section operasional + petty cash |
| `app/Http/Controllers/DashboardController.php` | Modify | Saldo dari PettyCashService |
| `resources/views/dashboard.blade.php` | Modify | Label "Saldo Petty Cash" |
| `database/factories/PettyCashTopupFactory.php` | Create | Factory test |
| `database/factories/PettyCashExpenseFactory.php` | Create | Factory test |
| `tests/Feature/PettyCashTest.php` | Create | Saldo, RBAC, validasi |
| `tests/Feature/PettyCashReportTest.php` | Create | P&L tidak double-count |
| `tests/Feature/DashboardRoleViewTest.php` | Modify | Label + saldo petty |
| `docs/srs/modules/M07-pengeluaran.md` | Modify | SRS petty cash |
| `docs/srs/modules/M09-laporan.md` | Modify | SRS laporan |

---

## Task 1: Migration & Models

**Files:**
- Create: `database/migrations/2026_06_13_000001_create_petty_cash_topups_table.php`
- Create: `database/migrations/2026_06_13_000002_create_petty_cash_expenses_table.php`
- Create: `app/Models/PettyCashTopup.php`
- Create: `app/Models/PettyCashExpense.php`

- [x] **Step 1: Migration petty_cash_topups**

```php
Schema::create('petty_cash_topups', function (Blueprint $table) {
    $table->id();
    $table->string('topup_number', 30)->unique(); // PCU/YYYY/MM/NNNN
    $table->unsignedInteger('amount');
    $table->date('topup_date');
    $table->string('description', 255);
    $table->text('notes')->nullable();
    $table->string('receipt_image')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

- [x] **Step 2: Migration petty_cash_expenses**

```php
Schema::create('petty_cash_expenses', function (Blueprint $table) {
    $table->id();
    $table->string('expense_number', 30)->unique(); // PCE/YYYY/MM/NNNN
    $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
    $table->unsignedInteger('amount');
    $table->string('description', 255);
    $table->date('expense_date');
    $table->string('receipt_image')->nullable();
    $table->text('notes')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

- [x] **Step 3: Model PettyCashTopup**

```php
class PettyCashTopup extends Model
{
    use HasFactory;

    protected $fillable = [
        'topup_number', 'amount', 'topup_date', 'description',
        'notes', 'receipt_image', 'created_by',
    ];

    protected $casts = [
        'amount'     => 'integer',
        'topup_date' => 'date',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('topup_date', $year)->whereMonth('topup_date', $month);
    }
}
```

- [x] **Step 4: Model PettyCashExpense** — mirror `Expense` scopes (`forMonth`, `forDate`), relasi `category()`, `createdBy()`.

- [x] **Step 5: Run migration**

Run: `php artisan migrate`
Expected: Both tables created without error.

- [x] **Step 6: Commit** (atomic-commit)

1. Analyze: `git status && git diff && git diff --staged`
2. Stage (explicit):
   ```bash
   git add database/migrations/2026_06_13_000001_create_petty_cash_topups_table.php database/migrations/2026_06_13_000002_create_petty_cash_expenses_table.php app/Models/PettyCashTopup.php app/Models/PettyCashExpense.php
   ```
3. Review: `git diff --staged`
4. Test: `php artisan migrate` (fresh test DB / sqlite in-memory via phpunit env OK)
5. Commit:
   ```bash
   git commit -m "[M07] Schema petty cash topup dan expense terpisah"
   ```
6. Verify: `git log -1 --oneline`

---

## Task 2: PettyCashService (TDD)

**Files:**
- Create: `app/Services/PettyCashService.php`
- Create: `database/factories/PettyCashTopupFactory.php`
- Create: `database/factories/PettyCashExpenseFactory.php`
- Create: `tests/Feature/PettyCashTest.php`

- [x] **Step 1: Write failing test saldo**

```php
/** @test */
public function saldo_petty_cash_adalah_topup_dikurangi_expense(): void
{
    $owner = User::factory()->create();
    $owner->assignRole('Owner');

    PettyCashTopup::factory()->create(['amount' => 500_000, 'topup_date' => '2026-06-01']);
    PettyCashExpense::factory()->create(['amount' => 150_000, 'expense_date' => '2026-06-05']);

    $service = app(PettyCashService::class);
    $this->assertSame(350_000, $service->getCurrentBalance());
}
```

- [x] **Step 2: Run test — expect FAIL**

Run: `php artisan test --filter=saldo_petty_cash_adalah_topup_dikurangi_expense`
Expected: FAIL — class `PettyCashService` not found

- [x] **Step 3: Implement PettyCashService**

```php
class PettyCashService
{
    public function getCurrentBalance(): int
    {
        $in  = (int) PettyCashTopup::query()->sum('amount');
        $out = (int) PettyCashExpense::query()->sum('amount');
        return $in - $out;
    }

    public function getBalanceBeforeExpense(int $excludeExpenseId = null): int
    {
        return $this->getCurrentBalance(); // dipakai saat edit dengan adjustment di Task 6
    }

    public function generateTopupNumber(int $year, int $month): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $latest = PettyCashTopup::where('topup_number', 'like', "PCU/{$year}/{$monthStr}/%")
            ->orderByDesc('topup_number')->value('topup_number');
        $next = 1;
        if ($latest) {
            $next = ((int) last(explode('/', $latest))) + 1;
        }
        return sprintf('PCU/%d/%s/%04d', $year, $monthStr, $next);
    }

    public function generateExpenseNumber(int $year, int $month): string
    {
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $latest = PettyCashExpense::where('expense_number', 'like', "PCE/{$year}/{$monthStr}/%")
            ->orderByDesc('expense_number')->value('expense_number');
        $next = 1;
        if ($latest) {
            $next = ((int) last(explode('/', $latest))) + 1;
        }
        return sprintf('PCE/%d/%s/%04d', $year, $monthStr, $next);
    }

    /** @return Collection<int, object{type:string,date:string,number:string,description:string,amount:int}> */
    public function getMutations(int $year, int $month): Collection
    {
        $topups = PettyCashTopup::forMonth($year, $month)->get()->map(fn ($t) => (object) [
            'type' => 'topup', 'date' => $t->topup_date->toDateString(),
            'number' => $t->topup_number, 'description' => $t->description,
            'amount' => $t->amount, 'model' => $t,
        ]);
        $expenses = PettyCashExpense::forMonth($year, $month)->with('category')->get()->map(fn ($e) => (object) [
            'type' => 'expense', 'date' => $e->expense_date->toDateString(),
            'number' => $e->expense_number, 'description' => $e->description,
            'amount' => $e->amount, 'model' => $e,
        ]);
        return $topups->concat($expenses)->sortByDesc('date')->values();
    }
}
```

- [x] **Step 4: Factories** — definisi minimal dengan `ExpenseCategory::factory()` atau seeder category ATK/KONSUMSI.

- [x] **Step 5: Run test — expect PASS**

Run: `php artisan test tests/Feature/PettyCashTest.php --filter=saldo_petty_cash`
Expected: PASS

- [x] **Step 6: Commit** (atomic-commit)

1. Analyze: `git status && git diff && git diff --staged`
2. Stage (explicit):
   ```bash
   git add app/Services/PettyCashService.php database/factories/PettyCashTopupFactory.php database/factories/PettyCashExpenseFactory.php tests/Feature/PettyCashTest.php
   ```
3. Review: `git diff --staged`
4. Test: `php artisan test tests/Feature/PettyCashTest.php --filter=saldo_petty_cash`
5. Commit:
   ```bash
   git commit -m "[M07] PettyCashService saldo dan nomor PCU/PCE"
   ```
6. Verify: `git log -1 --oneline`

---

## Task 3: Form Requests & Validasi Saldo

**Files:**
- Create: `app/Http/Requests/StorePettyCashTopupRequest.php`
- Create: `app/Http/Requests/StorePettyCashExpenseRequest.php`
- Modify: `tests/Feature/PettyCashTest.php`

- [x] **Step 1: Write failing test — expense melebihi saldo ditolak**

```php
/** @test */
public function admin_tidak_bisa_catat_petty_expense_melebihi_saldo(): void
{
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $category = ExpenseCategory::where('code', 'ATK')->first();

    $response = $this->actingAs($admin)->post(route('petty-cash.expenses.store'), [
        'expense_category_id' => $category->id,
        'amount'              => 100_000,
        'description'         => 'Beli ATK',
        'expense_date'        => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors('amount');
}
```

- [x] **Step 2: StorePettyCashTopupRequest** — rules: amount min 1, topup_date before_or_equal today, description required; pesan Bahasa Indonesia.

- [x] **Step 3: StorePettyCashExpenseRequest** — tambah rule custom:

```php
public function withValidator($validator): void
{
    $validator->after(function ($validator) {
        $amount = (int) $this->input('amount', 0);
        $saldo  = app(PettyCashService::class)->getCurrentBalance();
        if ($amount > $saldo) {
            $validator->errors()->add('amount', 'Saldo petty cash tidak cukup. Tersedia: Rp ' . number_format($saldo, 0, ',', '.'));
        }
    });
}
```

- [x] **Step 4: Run test — expect PASS after controller wired in Task 4**

- [x] **Step 5: Commit** (atomic-commit)

1. Analyze: `git status && git diff && git diff --staged`
2. Stage (explicit):
   ```bash
   git add app/Http/Requests/StorePettyCashTopupRequest.php app/Http/Requests/StorePettyCashExpenseRequest.php tests/Feature/PettyCashTest.php
   ```
3. Review: `git diff --staged`
4. Test: `php artisan test tests/Feature/PettyCashTest.php --filter=admin_tidak_bisa_catat_petty_expense_melebihi_saldo`
5. Commit:
   ```bash
   git commit -m "[M07] Validasi form petty cash + cek saldo"
   ```
6. Verify: `git log -1 --oneline`

---

## Task 4: PettyCashController & Routes

**Files:**
- Create: `app/Http/Controllers/PettyCashController.php`
- Create: `resources/views/petty-cash/index.blade.php`
- Create: `resources/views/petty-cash/topups/create.blade.php`
- Create: `resources/views/petty-cash/expenses/create.blade.php`
- Create: `resources/views/petty-cash/topups/show.blade.php`
- Create: `resources/views/petty-cash/expenses/show.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/navigation.blade.php`

- [x] **Step 1: Routes di `routes/web.php`**

```php
// READ — Owner|Admin|Auditor
Route::middleware('role:Owner|Admin|Auditor')->group(function () {
    Route::get('petty-cash', [PettyCashController::class, 'index'])->name('petty-cash.index');
    Route::get('petty-cash/topups/{topup}', [PettyCashController::class, 'showTopup'])->name('petty-cash.topups.show');
    Route::get('petty-cash/expenses/{expense}', [PettyCashController::class, 'showExpense'])->name('petty-cash.expenses.show');
});

// WRITE operasional — Owner|Admin (petty expense)
Route::middleware('role:Owner|Admin')->group(function () {
    Route::get('petty-cash/expenses/create', [PettyCashController::class, 'createExpense'])->name('petty-cash.expenses.create');
    Route::post('petty-cash/expenses', [PettyCashController::class, 'storeExpense'])->name('petty-cash.expenses.store');
    Route::get('petty-cash/expenses/{expense}/edit', [PettyCashController::class, 'editExpense'])->name('petty-cash.expenses.edit');
    Route::patch('petty-cash/expenses/{expense}', [PettyCashController::class, 'updateExpense'])->name('petty-cash.expenses.update');
});

// WRITE sensitif — Owner only (top-up + delete)
Route::middleware('role:Owner')->group(function () {
    Route::get('petty-cash/topups/create', [PettyCashController::class, 'createTopup'])->name('petty-cash.topups.create');
    Route::post('petty-cash/topups', [PettyCashController::class, 'storeTopup'])->name('petty-cash.topups.store');
    Route::get('petty-cash/topups/{topup}/edit', [PettyCashController::class, 'editTopup'])->name('petty-cash.topups.edit');
    Route::patch('petty-cash/topups/{topup}', [PettyCashController::class, 'updateTopup'])->name('petty-cash.topups.update');
    Route::delete('petty-cash/topups/{topup}', [PettyCashController::class, 'destroyTopup'])->name('petty-cash.topups.destroy');
    Route::delete('petty-cash/expenses/{expense}', [PettyCashController::class, 'destroyExpense'])->name('petty-cash.expenses.destroy');
});
```

- [x] **Step 2: Controller methods** — ikuti pola `ExpenseController`: upload `receipts/petty-cash/`, `AuditLog::record()` pada create/delete, redirect ke `petty-cash.index` dengan year/month filter.

- [x] **Step 3: index.blade.php** — card saldo tersedia (`PettyCashService::getCurrentBalance()`), filter tahun/bulan, tabel mutasi (top-up hijau +, expense merah −), tombol:
  - Owner: "+ Isi Saldo"
  - Admin|Owner: "+ Catat Pengeluaran Petty Cash"

- [x] **Step 4: Sidebar** — tambah setelah Pengeluaran:

```blade
<x-sidebar-item route="petty-cash.index" icon="💵" label="Petty Cash"
    :active="request()->routeIs('petty-cash.*')" />
```

- [x] **Step 5: RBAC tests**

```php
/** @test */
public function admin_tidak_bisa_akses_form_topup(): void
{
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin)->get(route('petty-cash.topups.create'))->assertForbidden();
}

/** @test */
public function owner_bisa_topup_dan_admin_bisa_expense(): void
{
    // Owner top-up 500k → Admin expense 50k → saldo 450k
}
```

- [x] **Step 6: Run tests**

Run: `php artisan test tests/Feature/PettyCashTest.php`
Expected: All PASS

- [x] **Step 7: Commit** (atomic-commit)

1. Analyze: `git status && git diff && git diff --staged`
2. Stage (explicit):
   ```bash
   git add app/Http/Controllers/PettyCashController.php resources/views/petty-cash/ routes/web.php resources/views/layouts/navigation.blade.php tests/Feature/PettyCashTest.php
   ```
3. Review: `git diff --staged`
4. Test: `php artisan test tests/Feature/PettyCashTest.php`
5. Commit:
   ```bash
   git commit -m "[M07] Modul petty cash CRUD + RBAC Owner/Admin"
   ```
6. Verify: `git log -1 --oneline`

---

## Task 5: Refactor Pengeluaran Operasional

**Files:**
- Modify: `app/Http/Controllers/ExpenseController.php` (hapus baris 65–86 petty cash)
- Modify: `resources/views/expenses/index.blade.php` (hapus section baris 77–129)
- Modify: `resources/views/expenses/create.blade.php` + `edit.blade.php`
- Modify: header `expenses/index.blade.php` → "Pengeluaran Operasional"

- [x] **Step 1: ExpenseController::index** — hapus variabel `$kasmasukHariIni`, `$kaskeluarHariIni`, dll.; compact hanya data operasional.

- [x] **Step 2: Form create/edit** — ganti default payment_method ke TRANSFER; opsi CASH dihapus dari select (legacy CASH di DB tetap tampil di show, tidak bisa dipilih di form baru):

```blade
<input type="hidden" name="payment_method" value="TRANSFER">
<p class="text-xs text-mk-dim mt-1">Pengeluaran operasional dicatat via transfer bank.</p>
```

- [x] **Step 3: store/update validation** — ubah rule menjadi `'payment_method' => 'required|in:TRANSFER'` (atau tetap terima CASH hanya jika edit record lama — YAGNI: cukup TRANSFER saja untuk create baru).

- [x] **Step 4: Manual smoke test**

1. Login Owner → `/expenses` — tidak ada widget petty cash
2. Create expense → hanya TRANSFER
3. Login Admin → `/petty-cash` — saldo + form expense

- [x] **Step 5: Commit** (atomic-commit)

1. Analyze: `git status && git diff && git diff --staged`
2. Stage (explicit):
   ```bash
   git add app/Http/Controllers/ExpenseController.php resources/views/expenses/
   ```
3. Review: `git diff --staged`
4. Test: smoke manual (Step 4) + `php artisan test tests/Feature/PettyCashTest.php` (regression)
5. Commit:
   ```bash
   git commit -m "[M07] Pengeluaran operasional terpisah dari petty cash"
   ```
6. Verify: `git log -1 --oneline`

---

## Task 6: Laporan Keuangan & Dashboard

**Files:**
- Modify: `app/Http/Controllers/ReportController.php`
- Modify: `resources/views/reports/finance.blade.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/views/dashboard.blade.php`
- Create: `tests/Feature/PettyCashReportTest.php`
- Modify: `tests/Feature/DashboardRoleViewTest.php`

- [x] **Step 1: Write failing test P&L**

```php
/** @test */
public function pl_masukkan_topup_tapi_tidak_double_count_petty_expense(): void
{
    $owner = User::factory()->create();
    $owner->assignRole('Owner');

    PettyCashTopup::factory()->create(['amount' => 500_000, 'topup_date' => '2026-06-10']);
    PettyCashExpense::factory()->create(['amount' => 100_000, 'expense_date' => '2026-06-12']);

    Expense::factory()->create([
        'amount' => 200_000, 'expense_date' => '2026-06-11', 'payment_method' => 'TRANSFER',
    ]);

    $response = $this->actingAs($owner)->get(route('reports.finance', ['year' => 2026, 'month' => 6]));

    $response->assertViewHas('totalPettyCashTopup', 500_000);
    $response->assertViewHas('totalPengeluaranOperasional', 200_000);
    // petty expense 100k TIDAK boleh masuk totalPengeluaran P&L
    $laba = $response->viewData('labaBersih');
    // revenue 0, honor 0 → laba = -500k - 200k = -700k (bukan -800k)
    $this->assertSame(-700_000, $laba);
}
```

- [x] **Step 2: ReportController::finance** — tambah query:

```php
$totalPettyCashTopup = PettyCashTopup::forMonth($year, $month)->sum('amount');
$pettyCashExpenses = PettyCashExpense::forMonth($year, $month)
    ->with('category')->orderByDesc('expense_date')->get();
$totalPettyCashExpense = $pettyCashExpenses->sum('amount'); // informatif saja

$totalPengeluaranOperasional = Expense::forMonth($year, $month)
    ->where('payment_method', 'TRANSFER')
    ->join('expense_categories', ...)
    ->... // existing expenseByCategory query, filter TRANSFER

$labaBersih = $totalRevenue - $totalHonor - $totalPengeluaranOperasional - $totalPettyCashTopup;
```

**Catatan:** Honor di P&L saat ini pakai `$honorPaid` — pertahankan pola existing di `finance.blade.php`.

- [x] **Step 3: finance.blade.php** — pisah 3 section:
  1. Ringkasan P&L (tambah baris "Isi Saldo Petty Cash")
  2. Pengeluaran Operasional per kategori
  3. Petty Cash (top-up bulan ini, daftar expense informatif, saldo akhir)

- [x] **Step 4: DashboardController** — ganti blok petty cash (baris 100–108):

```php
$saldoPettyCash = app(PettyCashService::class)->getCurrentBalance();
```

Hapus `$kasmasukTotal` / `$kaskeluarTotal` dari payment murid.

P&L Owner: `$pengeluaranBulan = Expense::forMonth(...)->where('payment_method','TRANSFER')->sum('amount') + PettyCashTopup::forMonth(...)->sum('amount');`

- [x] **Step 5: dashboard.blade.php** — ganti label "Saldo Kas" → "Saldo Petty Cash"

- [x] **Step 6: Update DashboardRoleViewTest**

```php
$response->assertSee('Saldo Petty Cash');
$response->assertDontSee('Saldo Kas');
```

- [x] **Step 7: Run tests**

Run: `php artisan test tests/Feature/PettyCashReportTest.php tests/Feature/DashboardRoleViewTest.php`
Expected: PASS

- [x] **Step 8: Commit** (atomic-commit)

1. Analyze: `git status && git diff && git diff --staged`
2. Stage (explicit):
   ```bash
   git add app/Http/Controllers/ReportController.php app/Http/Controllers/DashboardController.php resources/views/reports/finance.blade.php resources/views/dashboard.blade.php tests/Feature/PettyCashReportTest.php tests/Feature/DashboardRoleViewTest.php
   ```
3. Review: `git diff --staged`
4. Test: `php artisan test tests/Feature/PettyCashReportTest.php tests/Feature/DashboardRoleViewTest.php`
5. Commit:
   ```bash
   git commit -m "[M09] Laporan keuangan & dashboard pisah petty cash"
   ```
6. Verify: `git log -1 --oneline`

---

## Task 7: Migrasi Data Legacy

**Files:**
- Create: `database/migrations/2026_06_13_000003_migrate_legacy_cash_expenses_to_petty_cash.php`

- [x] **Step 1: Migration data**

```php
public function up(): void
{
    $cashExpenses = DB::table('expenses')->where('payment_method', 'CASH')->get();
    $totalMigrated = 0;

    foreach ($cashExpenses as $row) {
        DB::table('petty_cash_expenses')->insert([
            'expense_number'      => str_replace('EXP/', 'PCE/', $row->expense_number),
            'expense_category_id' => $row->expense_category_id,
            'amount'              => $row->amount,
            'description'         => $row->description,
            'expense_date'        => $row->expense_date,
            'receipt_image'       => $row->receipt_image,
            'notes'               => $row->notes,
            'created_by'          => $row->created_by,
            'created_at'          => $row->created_at,
            'updated_at'          => $row->updated_at,
        ]);
        $totalMigrated += $row->amount;
    }

    if ($totalMigrated > 0) {
        // Top-up penyesuaian agar saldo tidak negatif
        DB::table('petty_cash_topups')->insert([
            'topup_number'  => 'PCU/2026/06/0001',
            'amount'        => $totalMigrated,
            'topup_date'    => now()->toDateString(),
            'description'   => 'Saldo awal migrasi dari expenses CASH',
            'notes'         => 'Auto-generated migration',
            'created_by'    => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    DB::table('expenses')->where('payment_method', 'CASH')->delete();
}
```

- [x] **Step 2: Run migration di environment dev**

Run: `php artisan migrate`
Expected: Legacy CASH expenses moved; saldo net 0 setelah migrasi penyesuaian.

- [x] **Step 3: Commit** (atomic-commit)

1. Analyze: `git status && git diff && git diff --staged`
2. Stage (explicit):
   ```bash
   git add database/migrations/2026_06_13_000003_migrate_legacy_cash_expenses_to_petty_cash.php
   ```
3. Review: `git diff --staged`
4. Test: `php artisan migrate` di dev DB (backup dulu; **jangan** `migrate:fresh` production)
5. Commit:
   ```bash
   git commit -m "[M07] Migrasi expenses CASH ke petty cash"
   ```
6. Verify: `git log -1 --oneline`

---

## Task 8: Dokumentasi SRS

**Files:**
- Modify: `docs/srs/modules/M07-pengeluaran.md`
- Modify: `docs/srs/modules/M09-laporan.md`

- [x] **Step 1: M07** — tambah section Petty Cash:
  - Owner: isi saldo (`petty_cash_topups`)
  - Admin: catat expense (`petty_cash_expenses`)
  - BR: saldo = topup − expense; expense tidak masuk P&L; topup masuk P&L
  - expenses operasional: TRANSFER only
  - Export PDF: `GET /petty-cash/print?year=&month=` (Owner|Admin|Auditor)

- [x] **Step 2: M09** — dashboard saldo petty cash; laporan keuangan 3 section; petty cash PDF terpisah dari `reports.finance`

- [x] **Step 3: Commit** (atomic-commit)

1. Analyze: `git status && git diff && git diff --staged`
2. Stage (explicit):
   ```bash
   git add docs/srs/modules/M07-pengeluaran.md docs/srs/modules/M09-laporan.md
   ```
3. Review: `git diff --staged`
4. Test: n/a (docs only — pastikan tidak ada file kode tercampur)
5. Commit:
   ```bash
   git commit -m "[Docs] SRS M07/M09 petty cash terpisah"
   ```
6. Verify: `git log -1 --oneline`

---

## Task 9: Export PDF Laporan Petty Cash

**Files:**
- Modify: `app/Http/Controllers/PettyCashController.php` (method `print`)
- Create: `resources/views/petty-cash/print.blade.php`
- Modify: `resources/views/petty-cash/index.blade.php` (tombol Cetak PDF)
- Modify: `routes/web.php`
- Create: `tests/Feature/PettyCashPrintTest.php`

**Pendekatan:** Ikuti pola [`resources/views/reports/finance.blade.php`](resources/views/reports/finance.blade.php) — halaman Blade khusus cetak dengan `@media print`, tombol `window.print()` untuk Save as PDF. **Tidak** pakai DomPDF (cukup untuk laporan tabular bulanan, konsisten dengan laporan keuangan existing).

**Isi laporan PDF (per bulan filter):**
- Header: logo Musik KITA + judul "Laporan Petty Cash" + periode (F YYYY)
- Ringkasan: total top-up bulan ini, total pengeluaran bulan ini, saldo awal bulan, saldo akhir bulan
- Tabel mutasi kronologis: tanggal, nomor (PCU/PCE), tipe, keterangan, kategori (expense saja), debit/kredit, saldo berjalan
- Footer: dicetak tanggal/waktu + role tidak perlu ditampilkan

- [x] **Step 1: Write failing test**

```php
/** @test */
public function petty_cash_print_menampilkan_mutasi_bulan(): void
{
    $owner = User::factory()->create();
    $owner->assignRole('Owner');

    PettyCashTopup::factory()->create([
        'amount' => 500_000, 'topup_date' => '2026-06-01',
        'topup_number' => 'PCU/2026/06/0001', 'description' => 'Isi saldo awal',
    ]);
    PettyCashExpense::factory()->create([
        'amount' => 75_000, 'expense_date' => '2026-06-05',
        'expense_number' => 'PCE/2026/06/0001', 'description' => 'Beli ATK',
    ]);

    $response = $this->actingAs($owner)->get(route('petty-cash.print', [
        'year' => 2026, 'month' => 6,
    ]));

    $response->assertStatus(200);
    $response->assertSee('Laporan Petty Cash');
    $response->assertSee('PCU/2026/06/0001');
    $response->assertSee('PCE/2026/06/0001');
    $response->assertSee('500.000'); // atau number_format pattern di view
}

/** @test */
public function guest_tidak_bisa_akses_petty_cash_print(): void
{
    $this->get(route('petty-cash.print', ['year' => 2026, 'month' => 6]))
        ->assertRedirect(route('login'));
}
```

- [x] **Step 2: Route** — dalam group read `role:Owner|Admin|Auditor`:

```php
Route::get('petty-cash/print', [PettyCashController::class, 'print'])
    ->name('petty-cash.print');
```

- [x] **Step 3: Controller method `print`**

```php
public function print(Request $request, PettyCashService $service)
{
    $year  = (int) $request->get('year', now()->year);
    $month = (int) $request->get('month', now()->month);

    $mutations = $service->getMutationsWithRunningBalance($year, $month);
    $summary = $service->getMonthSummary($year, $month);
    // summary: opening_balance, total_topup, total_expense, closing_balance
    $monthName = Carbon::create($year, $month, 1)->translatedFormat('F Y');

    return view('petty-cash.print', compact(
        'year', 'month', 'monthName', 'mutations', 'summary'
    ));
}
```

- [x] **Step 4: Extend PettyCashService** — tambah method yang belum ada di Task 2:

```php
public function getOpeningBalanceForMonth(int $year, int $month): int
{
    $start = Carbon::create($year, $month, 1)->startOfMonth();
    $in  = (int) PettyCashTopup::where('topup_date', '<', $start)->sum('amount');
    $out = (int) PettyCashExpense::where('expense_date', '<', $start)->sum('amount');
    return $in - $out;
}

public function getMonthSummary(int $year, int $month): array
{
    $opening = $this->getOpeningBalanceForMonth($year, $month);
    $topup   = (int) PettyCashTopup::forMonth($year, $month)->sum('amount');
    $expense = (int) PettyCashExpense::forMonth($year, $month)->sum('amount');
    return [
        'opening_balance'  => $opening,
        'total_topup'      => $topup,
        'total_expense'    => $expense,
        'closing_balance'  => $opening + $topup - $expense,
    ];
}

public function getMutationsWithRunningBalance(int $year, int $month): Collection
{
    $balance = $this->getOpeningBalanceForMonth($year, $month);
    return $this->getMutations($year, $month)
        ->sortBy('date')
        ->values()
        ->map(function ($row) use (&$balance) {
            if ($row->type === 'topup') {
                $balance += $row->amount;
                $row->debit = $row->amount;
                $row->credit = 0;
            } else {
                $balance -= $row->amount;
                $row->debit = 0;
                $row->credit = $row->amount;
            }
            $row->running_balance = $balance;
            return $row;
        });
}
```

- [x] **Step 5: View `print.blade.php`** — standalone HTML (tanpa `x-app-layout`), pola CSS dari `staff-payrolls/print.blade.php`:

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Petty Cash — {{ $monthName }}</title>
    <style>
        /* @page margin, table borders, .no-print untuk tombol */
        @media print { .no-print { display: none !important; } nav, header { display: none !important; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:right; margin-bottom:16px;">
        <button onclick="window.print()">Cetak / Simpan PDF</button>
        <a href="{{ route('petty-cash.index', ['year' => $year, 'month' => $month]) }}">← Kembali</a>
    </div>
    {{-- logo + judul + ringkasan 4 angka + tabel mutasi --}}
</body>
</html>
```

Logo: `asset('images/logo-musikkita-light-mode.PNG')` (sama `reports/finance.blade.php` print header).

- [x] **Step 6: Tombol di index** — di header `petty-cash/index.blade.php`:

```blade
<a href="{{ route('petty-cash.print', ['year' => $year, 'month' => $month]) }}"
   target="_blank"
   class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
    Cetak PDF
</a>
```

- [x] **Step 7: Audit log PRINT (opsional tapi disarankan)**

```php
AuditLog::record(
    action: AuditLog::ACTION_PRINT,
    entityType: 'PettyCashReport',
    entityId: null,
    newValues: ['year' => $year, 'month' => $month],
);
```

- [x] **Step 8: Run tests**

Run: `php artisan test tests/Feature/PettyCashPrintTest.php`
Expected: PASS

- [x] **Step 9: Commit** (atomic-commit)

1. Analyze: `git status && git diff && git diff --staged`
2. Stage (explicit):
   ```bash
   git add app/Http/Controllers/PettyCashController.php app/Services/PettyCashService.php resources/views/petty-cash/print.blade.php resources/views/petty-cash/index.blade.php routes/web.php tests/Feature/PettyCashPrintTest.php
   ```
3. Review: `git diff --staged`
4. Test: `php artisan test tests/Feature/PettyCashPrintTest.php`
5. Commit:
   ```bash
   git commit -m "[M07] Export PDF laporan petty cash per bulan"
   ```
6. Verify: `git log -1 --oneline`

---

## Self-Review Checklist

| Requirement | Task |
|-------------|------|
| Owner isi saldo | Task 4 |
| Admin kelola expense petty | Task 4 |
| Top-up masuk P&L | Task 6 |
| Petty expense tidak double P&L | Task 6 |
| Independen dari tunai murid | Task 5, 6 |
| Laporan terpisah | Task 6 |
| Export PDF petty cash | Task 9 |
| RBAC Owner/Admin/Auditor | Task 4, 9 |
| Migrasi legacy CASH | Task 7 |
| Audit log | Task 4 (controller), Task 9 (PRINT) |

---

## Out of Scope

- Approval workflow replenishment
- Multi-fund petty cash per lokasi
- DomPDF/server-side PDF generation (browser print cukup Fase 1)

---

## Execution Handoff

Plan saved to `docs/superpowers/plans/2026-06-13-petty-cash-terpisah.md`.

**Commit discipline:** Setiap task selesai → jalankan **Commit Protocol (Atomic Commit Skill)** di atas. Target: **9 commit atomik** (Task 1–9), masing-masing tests pass sebelum commit. Setelah Task 9: `git log --oneline -n 9`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks, **atomic commit per task**
2. **Inline Execution** — execute tasks in this session with checkpoints + **atomic commit per task**

**Which approach?**
