# Kids Class Bundle — Cicilan 3 Termin UI — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tampilkan progress 3 termin cicilan Kids Class Bundle di invoice index (badge), invoice show (panel progress), dan student show (kartu cicilan).

**Architecture:** Tiga perubahan independen — (1) badge di tabel index, (2) panel sibling di invoice show dengan data dari controller, (3) kartu cicilan di student show dengan data dari controller. Tidak ada model baru, tidak ada migration, tidak ada route baru.

**Tech Stack:** Laravel 11, Blade, Tailwind CSS, Alpine.js (tidak dipakai di feature ini), PHPUnit feature tests.

---

## File Map

| File | Perubahan |
|------|-----------|
| `app/Http/Controllers/InvoiceController.php` | Tambah query `$siblings` di `show()` sebelum `return view()` |
| `app/Http/Controllers/StudentController.php` | Tambah query `$kidsInstallments` di `show()` setelah `$activeEnrollments` |
| `resources/views/invoices/index.blade.php` | Modifikasi kolom "Items" di tabel (baris 211–215): tambah badge Kids Bundle + Termin X/3 |
| `resources/views/invoices/show.blade.php` | Sisipkan panel progress cicilan antara baris 101 dan 103 |
| `resources/views/students/show.blade.php` | Sisipkan kartu cicilan antara baris 987 dan 989 (antara ringkasan saldo dan "5 Tagihan Terbaru") |
| `tests/Feature/KidsBundleInstallmentUiTest.php` | File test baru — 4 test |

---

### Task 1: Test file skeleton + Invoice Index badge

**Files:**
- Create: `tests/Feature/KidsBundleInstallmentUiTest.php`
- Modify: `resources/views/invoices/index.blade.php:211-215`

- [ ] **Step 1.1: Tulis test skeleton dengan 2 test yang failing**

Buat file `tests/Feature/KidsBundleInstallmentUiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Student;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KidsBundleInstallmentUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    // ─── Helper: buat murid + enrollment KIDS_CLASS_BUNDLE + 3 invoice cicilan ───

    private function buatMuridDenganCicilan(): array
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $pkg = Package::factory()->create([
            'class_type'      => 'KIDS_CLASS_BUNDLE',
            'price_per_month' => 340000,
        ]);
        $enrollment = Enrollment::factory()->for($student)->create([
            'package_id' => $pkg->id,
            'status'     => 'ACTIVE',
            'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        // Buat 3 invoice cicilan secara manual (mirip createKidsBundleInstallments)
        $groupId = Str::uuid()->toString();
        $invoices = [];
        $offsets  = [0, 1, 3];
        $amounts  = [113333, 113333, 113334];
        foreach ($offsets as $i => $offset) {
            $no = $i + 1;
            $issued = now()->addMonths($offset)->startOfMonth();
            $invoices[] = Invoice::create([
                'invoice_number'      => "INV/2026/0{$no}/000{$no}",
                'student_id'          => $student->id,
                'enrollment_id'       => $enrollment->id,
                'year'                => $issued->year,
                'month'               => $issued->month,
                'class_type'          => 'KIDS_CLASS_BUNDLE',
                'payment_mode'        => 'INSTALLMENT',
                'installment_number'  => $no,
                'installment_group_id'=> $groupId,
                'total_amount'        => $amounts[$i],
                'paid_amount'         => 0,
                'status'              => 'UNPAID',
                'due_date'            => $issued->copy()->setDay(10)->toDateString(),
            ]);
        }

        return [$student, $enrollment, $invoices];
    }

    /** Invoice index: badge Termin X/3 muncul untuk invoice cicilan */
    public function test_invoice_index_menampilkan_badge_termin_untuk_cicilan(): void
    {
        [$student, , $invoices] = $this->buatMuridDenganCicilan();

        $response = $this->actingAs($this->admin)
            ->get(route('invoices.index', [
                'year'  => now()->year,
                'month' => now()->month,
            ]));

        $response->assertOk();
        $response->assertSee('Kids Bundle');
        $response->assertSee('Termin 1/3');
    }

    /** Invoice index: invoice KIDS_CLASS_BUNDLE FULL menampilkan badge "Kids Bundle – Lunas" */
    public function test_invoice_index_menampilkan_badge_lunas_untuk_bundle_full(): void
    {
        $student = Student::factory()->create(['status' => 'Aktif']);
        $pkg = Package::factory()->create([
            'class_type'      => 'KIDS_CLASS_BUNDLE',
            'price_per_month' => 340000,
        ]);
        $enrollment = Enrollment::factory()->for($student)->create([
            'package_id' => $pkg->id,
            'status'     => 'ACTIVE',
            'is_primary' => true,
        ]);
        $student->update(['primary_enrollment_id' => $enrollment->id]);

        Invoice::create([
            'invoice_number' => 'INV/2026/05/0099',
            'student_id'     => $student->id,
            'enrollment_id'  => $enrollment->id,
            'year'           => now()->year,
            'month'          => now()->month,
            'class_type'     => 'KIDS_CLASS_BUNDLE',
            'payment_mode'   => 'FULL',
            'total_amount'   => 340000,
            'paid_amount'    => 0,
            'status'         => 'UNPAID',
            'due_date'       => now()->setDay(10)->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('invoices.index', [
                'year'  => now()->year,
                'month' => now()->month,
            ]));

        $response->assertOk();
        $response->assertSee('Kids Bundle');
        $response->assertSee('Lunas');
    }

    /** Invoice show: panel progress cicilan muncul untuk invoice installment */
    public function test_invoice_show_menampilkan_panel_progress_cicilan(): void
    {
        [$student, , $invoices] = $this->buatMuridDenganCicilan();

        $response = $this->actingAs($this->admin)
            ->get(route('invoices.show', $invoices[1]->id)); // buka Termin 2

        $response->assertOk();
        $response->assertSee('Cicilan Kids Class Bundle');
        $response->assertSee('Termin 1/3');
        $response->assertSee('Termin 2/3');
        $response->assertSee('Termin 3/3');
        $response->assertViewHas('siblings');
    }

    /** Student show: kartu cicilan muncul di tab tagihan untuk murid Kids Bundle */
    public function test_student_show_menampilkan_kartu_cicilan_kids_bundle(): void
    {
        [$student, , $invoices] = $this->buatMuridDenganCicilan();

        $response = $this->actingAs($this->admin)
            ->get(route('students.show', $student->id));

        $response->assertOk();
        $response->assertSee('Cicilan Kids Class Bundle');
        $response->assertViewHas('kidsInstallments');

        // Pastikan 3 invoice ada di collection
        $kidsInstallments = $response->viewData('kidsInstallments');
        $this->assertCount(3, $kidsInstallments);
    }
}
```

- [ ] **Step 1.2: Jalankan tests — pastikan semua 4 failing**

```bash
php artisan test tests/Feature/KidsBundleInstallmentUiTest.php --no-coverage
```

Expected: 4 FAILED (badge belum ada di view, controller belum pass `$siblings` / `$kidsInstallments`)

- [ ] **Step 1.3: Tambah badge di kolom Items — `invoices/index.blade.php`**

Ganti baris 211–215 (kolom `<td class="px-2 py-1.5 text-xs">`):

```blade
{{-- Sebelum: --}}
<td class="px-2 py-1.5 text-xs">
    @foreach($inv->items as $item)
        <span class="inline-block px-1 bg-gray-100 rounded mr-0.5">{{ $item->item_code }}</span>
    @endforeach
</td>
```

Ganti dengan:

```blade
<td class="px-2 py-1.5 text-xs">
    @foreach($inv->items as $item)
        <span class="inline-block px-1 bg-gray-100 rounded mr-0.5">{{ $item->item_code }}</span>
    @endforeach
    {{-- Badge khusus KIDS_CLASS_BUNDLE --}}
    @if($inv->class_type === 'KIDS_CLASS_BUNDLE')
        @if($inv->payment_mode === 'INSTALLMENT' && $inv->installment_number)
            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold ml-1
                         bg-purple-50 text-purple-700">Kids Bundle</span>
            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold
                         bg-blue-100 text-blue-700">
                Termin {{ $inv->installment_number }}/3
            </span>
        @elseif($inv->payment_mode === 'FULL')
            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold ml-1
                         bg-purple-50 text-purple-700">Kids Bundle – Lunas</span>
        @endif
    @endif
</td>
```

- [ ] **Step 1.4: Jalankan 2 test index — harus PASS, 2 sisanya masih FAIL**

```bash
php artisan test tests/Feature/KidsBundleInstallmentUiTest.php --no-coverage
```

Expected: 2 PASS (`test_invoice_index_*`), 2 FAIL (`test_invoice_show_*`, `test_student_show_*`)

- [ ] **Step 1.5: Commit**

```bash
git add tests/Feature/KidsBundleInstallmentUiTest.php resources/views/invoices/index.blade.php
git commit -m "M05: Kids Bundle — badge Termin X/3 di invoice index"
```

---

### Task 2: InvoiceController + Invoice Show panel progress cicilan

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php:72-91`
- Modify: `resources/views/invoices/show.blade.php` (sisipkan setelah baris 101)

- [ ] **Step 2.1: Tambah query `$siblings` di `InvoiceController@show`**

Ganti method `show()` di `app/Http/Controllers/InvoiceController.php` (baris 72–91):

```php
public function show(Invoice $invoice)
{
    $invoice->load([
        'student',
        // Hanya item induk (bukan item DISKON) + eager load diskon tiap item
        'items' => fn ($q) => $q->whereNull('parent_item_id')
                                ->with(['discountItem', 'addedBy']),
        'payments' => fn ($q) => $q->latest('payment_date'),
        'payments.createdBy',
        'payments.voidedBy',
    ]);

    // Sibling invoices untuk panel progress cicilan Kids Bundle (BR-10.10).
    // Hanya di-query jika invoice ini adalah bagian dari cicilan (installment_group_id ada).
    $siblings = $invoice->installment_group_id
        ? Invoice::where('installment_group_id', $invoice->installment_group_id)
            ->orderBy('installment_number')
            ->get(['id', 'installment_number', 'total_amount', 'paid_amount', 'status', 'due_date'])
        : collect();

    // Katalog item manual yang aktif — untuk dropdown tambah item
    $catalogItems = \App\Models\InvoiceComponent::where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('code')
        ->get(['id', 'code', 'name', 'default_price']);

    return view('invoices.show', compact('invoice', 'catalogItems', 'siblings'));
}
```

- [ ] **Step 2.2: Sisipkan panel progress cicilan di `invoices/show.blade.php`**

Cari baris 101–103 (penutup header card diikuti `{{-- ===== Total summary ===== --}}`):

```blade
            </div>
        </div>

        {{-- ===== Total summary ===== --}}
```

Sisipkan panel **antara** `</div>` (baris 101) dan `{{-- ===== Total summary ===== --}}` (baris 103):

```blade
            </div>
        </div>

        {{-- ===== Panel Progress Cicilan Kids Bundle (hanya untuk INSTALLMENT) ===== --}}
        @if($invoice->isInstallment() && $siblings->isNotEmpty())
        @php
            $paidCount   = $siblings->where('status', 'PAID')->count();
            $totalAmount = $siblings->sum('total_amount');
            $paidAmount  = $siblings->sum('paid_amount');
        @endphp
        <div class="bg-white shadow-sm sm:rounded-lg p-5">
            <div class="flex justify-between items-center mb-3">
                <div>
                    <div class="text-sm font-semibold text-gray-800">Cicilan Kids Class Bundle</div>
                    <div class="text-xs text-gray-500 mt-0.5">
                        {{ $paidCount }} dari 3 termin lunas ·
                        Rp {{ number_format($paidAmount, 0, ',', '.') }} dari
                        Rp {{ number_format($totalAmount, 0, ',', '.') }}
                    </div>
                </div>
            </div>

            {{-- Progress bar 3 segmen --}}
            <div class="flex gap-1 h-1.5 rounded-full overflow-hidden mb-4">
                @foreach($siblings as $sib)
                    @php
                        $isActive = $sib->installment_number === $invoice->installment_number;
                        if ($sib->status === 'PAID') {
                            $segColor = 'bg-green-400';
                        } elseif ($isActive || $sib->installment_number === $siblings->where('status', '!=', 'PAID')->min('installment_number')) {
                            $segColor = 'bg-yellow-400';
                        } else {
                            $segColor = 'bg-gray-200';
                        }
                    @endphp
                    <div class="flex-1 rounded-sm {{ $segColor }}"></div>
                @endforeach
            </div>

            {{-- Tabel 3 termin --}}
            <div class="space-y-1.5">
                @foreach($siblings as $sib)
                @php
                    $isActive = $sib->installment_number === $invoice->installment_number;
                    $dotColor = match($sib->status) {
                        'PAID'    => 'bg-green-400',
                        'PARTIAL' => 'bg-yellow-400',
                        default   => $isActive ? 'bg-yellow-400' : 'bg-gray-300',
                    };
                    $badgeClass = match($sib->status) {
                        'PAID'    => 'bg-green-50 text-green-700',
                        'PARTIAL' => 'bg-yellow-50 text-yellow-800',
                        default   => 'bg-gray-100 text-gray-500',
                    };
                    $badgeText = match($sib->status) {
                        'PAID'    => 'LUNAS',
                        'PARTIAL' => 'SEBAGIAN',
                        default   => 'BELUM BAYAR',
                    };
                @endphp
                <div class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm
                    {{ $isActive ? 'border border-yellow-200 bg-yellow-50/30' : 'border border-transparent' }}">
                    <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $dotColor }}"></div>
                    <div class="flex-1 text-gray-700">
                        Termin {{ $sib->installment_number }}/3
                        <span class="text-gray-400 text-xs ml-1">
                            · {{ $sib->due_date->format('d M Y') }}
                            @if($isActive) <span class="text-yellow-600 font-medium">← ini</span> @endif
                        </span>
                    </div>
                    <div class="text-gray-700 font-mono text-xs">
                        Rp {{ number_format($sib->total_amount, 0, ',', '.') }}
                    </div>
                    @if(!$isActive && $sib->id)
                        <a href="{{ route('invoices.show', $sib->id) }}"
                           class="text-xs text-indigo-600 hover:underline ml-1">
                            <span class="px-1.5 py-0.5 rounded {{ $badgeClass }}">{{ $badgeText }}</span>
                        </a>
                    @else
                        <span class="px-1.5 py-0.5 rounded text-xs {{ $badgeClass }}">{{ $badgeText }}</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ===== Total summary ===== --}}
```

- [ ] **Step 2.3: Jalankan test invoice show — harus PASS**

```bash
php artisan test tests/Feature/KidsBundleInstallmentUiTest.php --filter test_invoice_show --no-coverage
```

Expected: PASS

- [ ] **Step 2.4: Jalankan full suite — pastikan tidak ada regresi**

```bash
php artisan test --no-coverage
```

Expected: semua test PASS (sebelumnya 158 tests)

- [ ] **Step 2.5: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php resources/views/invoices/show.blade.php
git commit -m "M05: Kids Bundle — panel progress cicilan di invoice show"
```

---

### Task 3: StudentController + Student Show kartu cicilan

**Files:**
- Modify: `app/Http/Controllers/StudentController.php:128-155`
- Modify: `resources/views/students/show.blade.php` (sisipkan antara baris 987 dan 989)

- [ ] **Step 3.1: Tambah query `$kidsInstallments` di `StudentController@show`**

Cari blok di baris 128–141 (setelah `$recentInvoices`):

```php
        // M05: 5 invoice terbaru + total saldo outstanding (UNPAID + PARTIAL)
        $recentInvoices = $student->invoices()
            ->with('items')
            ->limit(5)
            ->get();
```

Tambahkan blok berikut **setelah** blok `$recentInvoices`:

```php
        // M05: Data cicilan Kids Bundle (BR-10.10) — null jika bukan KIDS_CLASS_BUNDLE INSTALLMENT.
        // Ditampilkan sebagai kartu progress di tab tagihan.
        $kidsInstallments = null;
        $primaryEnrollment = $student->primaryEnrollment;
        if ($primaryEnrollment && $primaryEnrollment->package?->class_type === 'KIDS_CLASS_BUNDLE') {
            $latestGroup = \App\Models\Invoice::where('student_id', $student->id)
                ->where('payment_mode', 'INSTALLMENT')
                ->whereNotNull('installment_group_id')
                ->latest('id')
                ->value('installment_group_id');

            if ($latestGroup) {
                $kidsInstallments = \App\Models\Invoice::where('installment_group_id', $latestGroup)
                    ->orderBy('installment_number')
                    ->get(['id', 'installment_number', 'total_amount', 'paid_amount', 'status', 'due_date']);
            }
        }
```

Pastikan `$kidsInstallments` masuk ke `compact()` di akhir method `show()`. Cari baris `return view(...)`:

```php
        return view('students.show', compact(
            'student', 'packages', 'teachers', 'rooms',
            'roomsForFilter', 'bookedSchedules',
            'upcomingSessions', 'recentInvoices',
            'outstandingBalance', 'unpaidCount',
            'activeEnrollments',
            'kidsInstallments',    // ← tambahkan ini
        ));
```

> **Catatan:** Jika `return view(...)` menggunakan `compact(...)` dengan daftar variabel berbeda, tambahkan `'kidsInstallments'` ke daftar tersebut.

- [ ] **Step 3.2: Sisipkan kartu cicilan di `students/show.blade.php`**

Cari baris 987–989 (penutup grid ringkasan saldo diikuti komentar "Invoice terbaru"):

```blade
                </div>

                {{-- Invoice terbaru --}}
```

Sisipkan kartu **antara** `</div>` (baris 987) dan `{{-- Invoice terbaru --}}` (baris 989):

```blade
                </div>

                {{-- Kartu Cicilan Kids Bundle (hanya muncul jika primary enrollment = KIDS_CLASS_BUNDLE INSTALLMENT) --}}
                @if(!empty($kidsInstallments) && $kidsInstallments->isNotEmpty())
                @php
                    $kPaidCount   = $kidsInstallments->where('status', 'PAID')->count();
                    $kTotalAmount = $kidsInstallments->sum('total_amount');
                    $kPaidAmount  = $kidsInstallments->sum('paid_amount');
                    $kAllPaid     = $kPaidCount === 3;
                @endphp
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                        <div class="text-[10px] uppercase tracking-widest font-semibold" style="color:#D4A853">
                            Cicilan Kids Class Bundle
                        </div>
                        @if($kAllPaid)
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-green-50 text-green-700">
                                Lunas ✓
                            </span>
                        @else
                            <span class="text-[10px] text-gray-400">
                                {{ $kPaidCount }}/3 termin lunas
                            </span>
                        @endif
                    </div>
                    <div class="px-5 py-3">
                        {{-- Subtitle nominal --}}
                        <div class="text-xs text-gray-500 mb-2">
                            Rp {{ number_format($kPaidAmount, 0, ',', '.') }} dibayar
                            dari Rp {{ number_format($kTotalAmount, 0, ',', '.') }}
                        </div>

                        {{-- Progress bar --}}
                        <div class="flex gap-1 h-1.5 rounded-full overflow-hidden mb-3">
                            @foreach($kidsInstallments as $kInv)
                            @php
                                $isNext = !$kAllPaid &&
                                    $kInv->installment_number === $kidsInstallments->where('status', '!=', 'PAID')->min('installment_number');
                                if ($kInv->status === 'PAID') {
                                    $kSegColor = 'bg-green-400';
                                } elseif ($isNext) {
                                    $kSegColor = 'bg-yellow-400';
                                } else {
                                    $kSegColor = 'bg-gray-200';
                                }
                            @endphp
                            <div class="flex-1 rounded-sm {{ $kSegColor }}"></div>
                            @endforeach
                        </div>

                        {{-- Tabel 3 termin --}}
                        <div class="space-y-1">
                            @foreach($kidsInstallments as $kInv)
                            @php
                                $kDotColor = match($kInv->status) {
                                    'PAID'    => 'bg-green-400',
                                    'PARTIAL' => 'bg-yellow-400',
                                    default   => 'bg-gray-300',
                                };
                                $kBadgeClass = match($kInv->status) {
                                    'PAID'    => 'bg-green-50 text-green-700',
                                    'PARTIAL' => 'bg-yellow-50 text-yellow-800',
                                    default   => 'bg-gray-100 text-gray-500',
                                };
                                $kBadgeText = match($kInv->status) {
                                    'PAID'    => 'LUNAS',
                                    'PARTIAL' => 'SEBAGIAN',
                                    default   => 'BELUM BAYAR',
                                };
                            @endphp
                            <a href="{{ route('invoices.show', $kInv->id) }}"
                               class="flex items-center gap-3 px-2 py-1.5 rounded-lg text-xs hover:bg-gray-50 transition-colors group">
                                <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $kDotColor }}"></div>
                                <div class="flex-1 text-gray-700">
                                    Termin {{ $kInv->installment_number }}/3
                                    <span class="text-gray-400 ml-1">· {{ $kInv->due_date->format('d M Y') }}</span>
                                </div>
                                <div class="font-mono text-gray-600">
                                    Rp {{ number_format($kInv->total_amount, 0, ',', '.') }}
                                </div>
                                <span class="px-1.5 py-0.5 rounded {{ $kBadgeClass }} text-[10px] font-semibold">
                                    {{ $kBadgeText }}
                                </span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                {{-- Invoice terbaru --}}
```

- [ ] **Step 3.3: Cari dan update `return view(...)` di StudentController jika perlu**

Jalankan:
```bash
grep -n "return view.*students.show\|compact(" app/Http/Controllers/StudentController.php | tail -10
```

Pastikan `kidsInstallments` sudah ada di `compact()`. Jika belum, tambahkan.

- [ ] **Step 3.4: Jalankan test student show — harus PASS**

```bash
php artisan test tests/Feature/KidsBundleInstallmentUiTest.php --filter test_student_show --no-coverage
```

Expected: PASS

- [ ] **Step 3.5: Jalankan full test suite**

```bash
php artisan test --no-coverage
```

Expected: 162 tests PASS (158 lama + 4 baru)

- [ ] **Step 3.6: Commit**

```bash
git add app/Http/Controllers/StudentController.php resources/views/students/show.blade.php tests/Feature/KidsBundleInstallmentUiTest.php
git commit -m "M05: Kids Bundle — kartu cicilan 3 termin di student show"
```

---

## Ringkasan Akhir

Setelah ketiga task selesai:

| Lokasi | Tampilan baru |
|--------|---------------|
| Invoice Index | Badge "Kids Bundle" (ungu) + "Termin X/3" (biru) di kolom Items |
| Invoice Show | Panel progress bar + 3 baris termin, baris aktif di-highlight gold |
| Student Show | Kartu "Cicilan Kids Class Bundle" di atas tabel tagihan, semua baris klikable |
| Invoice FULL | Badge "Kids Bundle – Lunas" di index, panel tidak muncul di show |
| Murid reguler | Tidak ada perubahan tampilan |
