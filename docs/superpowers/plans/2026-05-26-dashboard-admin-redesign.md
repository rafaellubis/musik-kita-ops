# Dashboard Admin Simplifikasi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sederhanakan dashboard role Admin — hapus widget keuangan (Saldo Kas, Aging Piutang, Slip Honor), ganti Statistik Murid dengan Daftar Absensi Hari Ini, dan hapus kolom Sisa dari tabel Tagihan Belum Lunas.

**Architecture:** Tambah flag `$isAdmin` di DashboardController dan gunakan conditional `@if($isAdmin)`/`@if(!$isAdmin)` di satu file Blade yang sudah ada — mengikuti pola `$isOwner` yang sudah berjalan. Tidak ada file baru, tidak ada refactor besar.

**Tech Stack:** Laravel 11, Blade, Tailwind CSS, Spatie Permission v6, PHPUnit/RefreshDatabase

---

## File yang Dimodifikasi

| File | Perubahan |
|------|-----------|
| `app/Http/Controllers/DashboardController.php` | Tambah `$isAdmin`, tambah query `$absensiHariIni` |
| `resources/views/dashboard.blade.php` | 5 perubahan conditional: KPI row, Bar Chart grid, Aging Piutang, Statistik Murid→Absensi, Sisa kolom, Slip Honor |
| `tests/Feature/DashboardRoleViewTest.php` | File baru — test per role |

---

## Task 1: Tulis Feature Test (Failing)

**Files:**
- Create: `tests/Feature/DashboardRoleViewTest.php`

- [ ] **Step 1.1: Buat file test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardRoleViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    // ===== ADMIN: widget yang TIDAK boleh muncul =====

    public function test_admin_tidak_melihat_saldo_kas(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertDontSee('Saldo Kas');
    }

    public function test_admin_tidak_melihat_murid_aktif_kpi(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertDontSee('Murid Aktif');
    }

    public function test_admin_tidak_melihat_aging_piutang(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertDontSee('Aging Piutang');
    }

    public function test_admin_tidak_melihat_slip_honor_belum_dibayarkan(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertDontSee('Slip Honor Belum Dibayarkan');
    }

    public function test_admin_tidak_melihat_statistik_murid(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertDontSee('Statistik Murid');
    }

    public function test_admin_tidak_melihat_kolom_sisa(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertDontSee('Sisa');
    }

    // ===== ADMIN: widget yang HARUS muncul =====

    public function test_admin_melihat_daftar_absensi_hari_ini(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertSee('Daftar Absensi Hari Ini');
    }

    public function test_admin_melihat_pesan_kosong_absensi(): void
    {
        $response = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'));
        $response->assertSee('Tidak ada sesi yang perlu diabsen hari ini.');
    }

    // ===== AUDITOR: tidak berubah =====

    public function test_auditor_masih_melihat_saldo_kas(): void
    {
        $response = $this->actingAs($this->userWithRole('Auditor'))
            ->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Saldo Kas');
    }

    public function test_auditor_masih_melihat_aging_piutang(): void
    {
        $response = $this->actingAs($this->userWithRole('Auditor'))
            ->get(route('dashboard'));
        $response->assertSee('Aging Piutang');
    }

    public function test_auditor_masih_melihat_statistik_murid(): void
    {
        $response = $this->actingAs($this->userWithRole('Auditor'))
            ->get(route('dashboard'));
        $response->assertSee('Statistik Murid');
    }

    public function test_auditor_masih_melihat_slip_honor(): void
    {
        $response = $this->actingAs($this->userWithRole('Auditor'))
            ->get(route('dashboard'));
        $response->assertSee('Slip Honor Belum Dibayarkan');
    }

    public function test_auditor_masih_melihat_kolom_sisa(): void
    {
        $response = $this->actingAs($this->userWithRole('Auditor'))
            ->get(route('dashboard'));
        $response->assertSee('Sisa');
    }

    public function test_auditor_tidak_melihat_daftar_absensi_hari_ini(): void
    {
        $response = $this->actingAs($this->userWithRole('Auditor'))
            ->get(route('dashboard'));
        $response->assertDontSee('Daftar Absensi Hari Ini');
    }

    // ===== OWNER: tidak berubah =====

    public function test_owner_masih_melihat_saldo_kas(): void
    {
        $response = $this->actingAs($this->userWithRole('Owner'))
            ->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Saldo Kas');
    }

    public function test_owner_masih_melihat_aging_piutang(): void
    {
        $response = $this->actingAs($this->userWithRole('Owner'))
            ->get(route('dashboard'));
        $response->assertSee('Aging Piutang');
    }

    public function test_owner_masih_melihat_statistik_murid(): void
    {
        $response = $this->actingAs($this->userWithRole('Owner'))
            ->get(route('dashboard'));
        $response->assertSee('Statistik Murid');
    }

    public function test_owner_masih_melihat_slip_honor(): void
    {
        $response = $this->actingAs($this->userWithRole('Owner'))
            ->get(route('dashboard'));
        $response->assertSee('Slip Honor Belum Dibayarkan');
    }

    public function test_owner_tidak_melihat_daftar_absensi_hari_ini(): void
    {
        $response = $this->actingAs($this->userWithRole('Owner'))
            ->get(route('dashboard'));
        $response->assertDontSee('Daftar Absensi Hari Ini');
    }
}
```

- [ ] **Step 1.2: Jalankan test — verifikasi GAGAL**

```bash
php artisan test tests/Feature/DashboardRoleViewTest.php
```

Ekspektasi: semua test FAIL karena controller belum mengirim `$isAdmin` dan view belum dimodifikasi.

---

## Task 2: Update DashboardController

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`

- [ ] **Step 2.1: Tambah `$isAdmin` dan query `$absensiHariIni`**

Di `DashboardController::index()`, setelah baris `$isOwner = auth()->user()->hasRole('Owner');`, tambahkan:

```php
$isAdmin = auth()->user()->hasRole('Admin');
```

Kemudian, setelah blok `// ===== PETTY CASH, AGING, INVOICE TERLAMA, HONOR (semua role) =====`, tambahkan query baru:

```php
// ===== ABSENSI HARI INI (Admin only) =====
$absensiHariIni = collect();
if ($isAdmin) {
    $absensiHariIni = ClassSession::with(['student', 'teacher', 'schedule.room'])
        ->leftJoin('schedules', 'class_sessions.schedule_id', '=', 'schedules.id')
        ->where('class_sessions.session_date', now()->toDateString())
        ->where('class_sessions.status', 'SCHEDULED')
        ->orderBy('schedules.start_time')
        ->select('class_sessions.*')
        ->get();
}
```

Di akhir method, tambahkan `'isAdmin'` dan `'absensiHariIni'` ke dalam `compact()`:

```php
return view('dashboard', compact(
    'year', 'month', 'monthName', 'isOwner', 'isAdmin',
    'revenueBulan', 'revenueCash', 'revenueTransfer',
    'pengeluaranBulan', 'pengeluaranCash',
    'labaBulan',
    'saldoKas', 'kasmasukTotal', 'kaskeluarTotal',
    'muridAktif', 'muridTrial', 'muridCuti', 'muridCalon', 'muridTotal',
    'aging', 'agingCount', 'totalPiutang',
    'invoiceTerlama',
    'honorBelumBayar',
    'revenueChart', 'instrumenChart', 'attendanceChart',
    'absensiHariIni',
));
```

- [ ] **Step 2.2: Jalankan test — verifikasi sebagian masih GAGAL**

```bash
php artisan test tests/Feature/DashboardRoleViewTest.php
```

Ekspektasi: test yang mengecek `assertStatus(200)` dan `assertSee('Daftar Absensi Hari Ini')` masih FAIL karena view belum dimodifikasi, tapi tidak ada PHP error.

---

## Task 3: Update Blade — Baris 1 KPI dan Baris 5 Slip Honor

**Files:**
- Modify: `resources/views/dashboard.blade.php`

- [ ] **Step 3.1: Wrap Baris 1 KPI grid dengan `@if(!$isAdmin)`**

Temukan baris ini di `dashboard.blade.php` (sekitar baris 26):
```blade
        {{-- ===== BARIS 1: KARTU KPI ===== --}}
        <div class="grid grid-cols-2 {{ $isOwner ? 'lg:grid-cols-4' : 'lg:grid-cols-2' }} gap-4">
```

Tambahkan `@if(!$isAdmin)` sebelumnya dan `@endif` setelah penutup `</div>` di baris 116.

Hasilnya:
```blade
        {{-- ===== BARIS 1: KARTU KPI ===== --}}
        @if(!$isAdmin)
        <div class="grid grid-cols-2 {{ $isOwner ? 'lg:grid-cols-4' : 'lg:grid-cols-2' }} gap-4">
            {{-- ... semua isi baris 1 tetap sama ... --}}
        </div>
        @endif
```

- [ ] **Step 3.2: Wrap Baris 5 Slip Honor dengan `@if(!$isAdmin)`**

Temukan baris ini (sekitar baris 293):
```blade
        {{-- ===== BARIS 5: HONOR BELUM DIBAYAR ===== --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden fade-in-up" style="animation-delay:400ms">
```

Tambahkan `@if(!$isAdmin)` sebelumnya dan `@endif` setelah `</div>` penutup di baris 331:
```blade
        @if(!$isAdmin)
        {{-- ===== BARIS 5: HONOR BELUM DIBAYAR ===== --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden fade-in-up" style="animation-delay:400ms">
            {{-- ... semua isi baris 5 tetap sama ... --}}
        </div>
        @endif
```

- [ ] **Step 3.3: Jalankan test — verifikasi sebagian LULUS**

```bash
php artisan test tests/Feature/DashboardRoleViewTest.php
```

Ekspektasi: test `test_admin_tidak_melihat_saldo_kas`, `test_admin_tidak_melihat_murid_aktif_kpi`, `test_admin_tidak_melihat_slip_honor_belum_dibayarkan`, dan semua test Auditor/Owner mulai LULUS. Test `test_admin_tidak_melihat_aging_piutang`, `test_admin_melihat_daftar_absensi_hari_ini`, dan Sisa test masih FAIL.

---

## Task 4: Update Blade — Aging Piutang dan Bar Chart Layout

**Files:**
- Modify: `resources/views/dashboard.blade.php`

- [ ] **Step 4.1: Sesuaikan grid Baris 3 dan wrap Aging Piutang**

Temukan baris ini (sekitar baris 161):
```blade
        {{-- ===== BARIS 3: BAR CHART ABSENSI + AGING PIUTANG ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
```

Ubah class grid agar Admin mendapat full width:
```blade
        {{-- ===== BARIS 3: BAR CHART ABSENSI + AGING PIUTANG ===== --}}
        <div class="grid grid-cols-1 {{ $isAdmin ? '' : 'lg:grid-cols-2' }} gap-5">
```

Kemudian temukan div Aging Piutang (sekitar baris 182):
```blade
            {{-- Aging Piutang --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 fade-in-up" style="animation-delay:280ms">
```

Bungkus div Aging Piutang (dari baris 182 s/d penutup `</div>` di baris 208) dengan kondisional:
```blade
            @if(!$isAdmin)
            {{-- Aging Piutang --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 fade-in-up" style="animation-delay:280ms">
                {{-- ... semua isi Aging Piutang tetap sama ... --}}
            </div>
            @endif
```

- [ ] **Step 4.2: Jalankan test — verifikasi lebih banyak LULUS**

```bash
php artisan test tests/Feature/DashboardRoleViewTest.php
```

Ekspektasi: `test_admin_tidak_melihat_aging_piutang` dan `test_auditor_masih_melihat_aging_piutang` LULUS. Masih FAIL: test Statistik Murid, Daftar Absensi, dan Sisa.

---

## Task 5: Update Blade — Statistik Murid → Daftar Absensi Hari Ini

**Files:**
- Modify: `resources/views/dashboard.blade.php`

- [ ] **Step 5.1: Ganti widget Statistik Murid dengan conditional block**

Temukan div Statistik Murid (sekitar baris 214):
```blade
            {{-- Statistik Murid --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 fade-in-up" style="animation-delay:320ms">
                <div class="flex justify-between items-center mb-4">
                    <div class="text-sm font-semibold text-gray-800">Statistik Murid</div>
```

Ganti seluruh div Statistik Murid (dari baris ~214 s/d penutup `</div>` di baris ~243) dengan block berikut:

```blade
            @if($isAdmin)
            {{-- Daftar Absensi Hari Ini (Admin only) --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden fade-in-up" style="animation-delay:320ms">
                <div class="px-5 py-3.5 border-b border-gray-100 flex justify-between items-center">
                    <div class="text-sm font-semibold text-gray-800">Daftar Absensi Hari Ini</div>
                    <a href="{{ route('absensi.index') }}" class="text-xs text-indigo-600 hover:underline">Buka Absensi →</a>
                </div>
                @if($absensiHariIni->count() > 0)
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="px-4 py-2.5 text-left text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Jam</th>
                            <th class="px-4 py-2.5 text-left text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Murid</th>
                            <th class="px-4 py-2.5 text-left text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Guru</th>
                            <th class="px-4 py-2.5 text-left text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Ruangan</th>
                            <th class="px-4 py-2.5 text-center text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($absensiHariIni as $sesi)
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-gray-600">
                                {{ $sesi->schedule ? \Carbon\Carbon::parse($sesi->schedule->start_time)->format('H:i') : '—' }}
                            </td>
                            <td class="px-4 py-2.5">
                                <a href="{{ route('students.show', $sesi->student_id) }}" class="text-indigo-600 hover:underline font-medium">
                                    {{ $sesi->student->full_name ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-2.5 text-gray-600">
                                {{ $sesi->teacher->name ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-600">
                                {{ $sesi->schedule?->room?->code ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-50 text-yellow-700">
                                    Belum Diabsen
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="px-5 py-8 text-center text-gray-400 text-sm">Tidak ada sesi yang perlu diabsen hari ini.</div>
                @endif
            </div>
            @else
            {{-- Statistik Murid (Owner + Auditor) --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 fade-in-up" style="animation-delay:320ms">
                <div class="flex justify-between items-center mb-4">
                    <div class="text-sm font-semibold text-gray-800">Statistik Murid</div>
                    <a href="{{ route('students.index') }}" class="text-xs text-indigo-600 hover:underline">Lihat semua →</a>
                </div>
                <div class="space-y-2.5">
                    @foreach([
                        ['Aktif', $muridAktif, 'rgba(52,211,153,0.12)',  '#34D399'],
                        ['Trial', $muridTrial, 'rgba(167,139,250,0.12)', '#A78BFA'],
                        ['Cuti',  $muridCuti,  'rgba(251,191,36,0.12)',  '#FBBF24'],
                        ['Calon', $muridCalon, 'rgba(139,146,168,0.12)', '#8B92A8'],
                    ] as [$lbl, $cnt, $bg, $clr])
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full inline-block" style="background:{{ $clr }}"></span>
                            <span class="text-sm text-gray-600">{{ $lbl }}</span>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold"
                              style="background:{{ $bg }};color:{{ $clr }}">
                            {{ $cnt }} murid
                        </span>
                    </div>
                    @endforeach
                    <div class="flex justify-between items-center border-t border-gray-100 pt-2.5 mt-1">
                        <span class="text-sm font-medium text-gray-700">Total Terdaftar</span>
                        <span class="text-sm font-bold text-gray-900">{{ $muridTotal }} murid</span>
                    </div>
                </div>
            </div>
            @endif
```

- [ ] **Step 5.2: Jalankan test — verifikasi lebih banyak LULUS**

```bash
php artisan test tests/Feature/DashboardRoleViewTest.php
```

Ekspektasi: semua test Statistik Murid dan Daftar Absensi LULUS. Masih FAIL: `test_admin_tidak_melihat_kolom_sisa` dan `test_auditor_masih_melihat_kolom_sisa`.

---

## Task 6: Update Blade — Hapus Kolom Sisa untuk Admin

**Files:**
- Modify: `resources/views/dashboard.blade.php`

- [ ] **Step 6.1: Wrap `<th>Sisa</th>` dengan kondisional**

Temukan di dalam tabel Tagihan Belum Lunas (sekitar baris 255):
```blade
                            <th class="px-4 py-2.5 text-right text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Sisa</th>
```

Ganti dengan:
```blade
                            @if(!$isAdmin)
                            <th class="px-4 py-2.5 text-right text-gray-500 font-semibold uppercase tracking-wide text-[10px]">Sisa</th>
                            @endif
```

- [ ] **Step 6.2: Wrap `<td>` nilai Sisa dengan kondisional**

Temukan di dalam loop `@foreach($invoiceTerlama as $inv)` (sekitar baris 268):
```blade
                            <td class="px-4 py-2.5 text-right font-mono font-semibold" style="color:#F87171">
                                Rp {{ number_format($inv->total_amount - $inv->paid_amount, 0, ',', '.') }}
                            </td>
```

Ganti dengan:
```blade
                            @if(!$isAdmin)
                            <td class="px-4 py-2.5 text-right font-mono font-semibold" style="color:#F87171">
                                Rp {{ number_format($inv->total_amount - $inv->paid_amount, 0, ',', '.') }}
                            </td>
                            @endif
```

- [ ] **Step 6.3: Jalankan SEMUA test — verifikasi semua LULUS**

```bash
php artisan test tests/Feature/DashboardRoleViewTest.php
```

Ekspektasi: 21 test PASS, 0 FAIL.

---

## Task 7: Build Assets + Commit Final

**Files:**
- Tidak ada perubahan file baru

- [ ] **Step 7.1: Build Tailwind CSS**

```bash
npm run build
```

Ekspektasi: build selesai tanpa error, output di `public/build/`.

- [ ] **Step 7.2: Jalankan seluruh test suite untuk deteksi regresi**

```bash
php artisan test
```

Ekspektasi: semua test suite PASS. Jika ada failure di test lain, investigasi sebelum commit.

- [ ] **Step 7.3: Commit**

```bash
git add app/Http/Controllers/DashboardController.php
git add resources/views/dashboard.blade.php
git add tests/Feature/DashboardRoleViewTest.php
git commit -m "M09: Simplifikasi dashboard Admin — hapus widget keuangan, tambah absensi hari ini"
```

---

## Ringkasan Perubahan

| Komponen | Perubahan |
|----------|-----------|
| `DashboardController` | +3 baris: `$isAdmin`, query `$absensiHariIni`, update `compact()` |
| `dashboard.blade.php` | +6 conditional blocks: KPI row, Bar Chart grid, Aging Piutang, Statistik Murid→Absensi, Sisa th, Sisa td, Slip Honor |
| `DashboardRoleViewTest` | File baru: 21 test cases (7 Admin, 6 Auditor, 5 Owner) |
