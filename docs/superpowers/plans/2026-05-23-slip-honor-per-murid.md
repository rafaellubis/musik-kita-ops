# Slip Honor Guru — Rincian Per Murid Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesain slip honor cetak agar menampilkan rincian per murid (nama, instrumen, sesi, nominal), info bank di header, dan total tanpa box.

**Architecture:** Tambah kolom `bank_account_holder` di tabel `teachers`, tambah method `getStudentBreakdown()` di `HonorCalculationService` yang mengolah sesi menjadi ringkasan per murid, update controller `print()` dan redesain full `print.blade.php`.

**Tech Stack:** Laravel 11, Blade, PHP 8.3, MySQL, PHPUnit (RefreshDatabase + in-memory SQLite via phpunit.xml)

---

## Peta File

| File | Status | Tanggung jawab |
|------|--------|----------------|
| `database/migrations/2026_05_23_XXXXXX_add_bank_account_holder_to_teachers.php` | **Baru** | Tambah kolom `bank_account_holder` |
| `app/Models/Teacher.php` | **Modifikasi** | Tambah ke `$fillable` |
| `resources/views/teachers/_form.blade.php` | **Modifikasi** | Tambah field "Nama Pemilik Rekening" |
| `app/Services/HonorCalculationService.php` | **Modifikasi** | Tambah `getStudentBreakdown()` |
| `tests/Unit/Services/HonorCalculationServiceStudentBreakdownTest.php` | **Baru** | Unit test `getStudentBreakdown()` |
| `app/Http/Controllers/HonorController.php` | **Modifikasi** | Update `print()` pakai breakdown per murid |
| `resources/views/honors/print.blade.php` | **Modifikasi** | Redesain penuh sesuai mockup |

---

## Task 1: Migration + Model + Form Field

**Files:**
- Create: `database/migrations/2026_05_23_000001_add_bank_account_holder_to_teachers.php`
- Modify: `app/Models/Teacher.php`
- Modify: `resources/views/teachers/_form.blade.php`

- [ ] **Step 1: Buat migration**

```bash
php artisan make:migration add_bank_account_holder_to_teachers
```

Isi file migration yang dibuat (cek nama file aktual di `database/migrations/`):

```php
public function up(): void
{
    Schema::table('teachers', function (Blueprint $table) {
        $table->string('bank_account_holder', 100)->nullable()->after('bank_account');
    });
}

public function down(): void
{
    Schema::table('teachers', function (Blueprint $table) {
        $table->dropColumn('bank_account_holder');
    });
}
```

- [ ] **Step 2: Jalankan migration**

```bash
php artisan migrate
```

Expected: `Migrating: 2026_05_23_..._add_bank_account_holder_to_teachers` → `Migrated`

- [ ] **Step 3: Tambah ke fillable di Teacher model**

Di `app/Models/Teacher.php`, ubah:

```php
protected $fillable = [
    'code', 'name', 'email', 'phone', 'bank_name', 'bank_account',
    'joined_date', 'is_active', 'notes',
];
```

Menjadi:

```php
protected $fillable = [
    'code', 'name', 'email', 'phone', 'bank_name', 'bank_account',
    'bank_account_holder', 'joined_date', 'is_active', 'notes',
];
```

- [ ] **Step 4: Tambah field di `_form.blade.php`**

Di `resources/views/teachers/_form.blade.php`, setelah blok `bank_account` (baris 43–48), tambahkan:

```blade
<div>
    <label class="block text-sm font-medium">Nama Pemilik Rekening</label>
    <input type="text" name="bank_account_holder" maxlength="100"
           value="{{ old('bank_account_holder', $teacher->bank_account_holder ?? '') }}"
           class="mt-1 block w-full border-gray-300 rounded-md"
           placeholder="Nama sesuai buku tabungan">
</div>
```

- [ ] **Step 5: Verifikasi di browser**

Buka halaman edit salah satu guru (contoh: `/teachers/1/edit`).
Pastikan field "Nama Pemilik Rekening" muncul di antara "No. Rekening" dan "Tgl Bergabung".
Isi dan simpan — pastikan tersimpan di database.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/ app/Models/Teacher.php resources/views/teachers/_form.blade.php
git commit -m "M06: Tambah kolom bank_account_holder di profil guru"
```

---

## Task 2: `getStudentBreakdown()` + Unit Test

**Files:**
- Modify: `app/Services/HonorCalculationService.php`
- Create: `tests/Unit/Services/HonorCalculationServiceStudentBreakdownTest.php`

**Konteks penting:** Di sistem ini, setiap murid Kids Class punya row `class_session` sendiri dengan `honor_code = 'H_KIDS'` dan `honor_amount = 42500` flat. Ini berarti breakdown per-murid cukup group by `student_id` — tidak perlu query terpisah untuk Kids Class.

- [ ] **Step 1: Tulis test yang gagal**

Buat file `tests/Unit/Services/HonorCalculationServiceStudentBreakdownTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\HonorSlip;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\HonorCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HonorCalculationServiceStudentBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private HonorCalculationService $service;
    private Teacher $teacher;
    private HonorSlip $slip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HonorCalculationService();

        $this->teacher = Teacher::create([
            'code'      => 'T01',
            'name'      => 'Daniel',
            'is_active' => true,
        ]);

        $this->slip = HonorSlip::create([
            'slip_number'     => 'SLIP/2026/05/0001',
            'teacher_id'      => $this->teacher->id,
            'year'            => 2026,
            'month'           => 5,
            'base_honor'      => 0,
            'transport_honor' => 0,
            'other_honor'     => 0,
            'total_honor'     => 0,
            'status'          => 'CALCULATED',
            'created_by'      => 1,
        ]);
    }

    /** Buat murid + enrollment + sesi privat untuk teacher */
    private function buatSesiPrivat(
        string $namaMusrid,
        string $namaInstrumen,
        int $jumlahSesi,
        int $honorPerSesi
    ): void {
        $instrumen = Instrument::firstOrCreate(['name' => $namaInstrumen, 'code' => strtoupper(substr($namaInstrumen, 0, 3))]);
        $package   = Package::firstOrCreate(
            ['code' => 'PKG-' . $instrumen->id],
            [
                'instrument_id'   => $instrumen->id,
                'class_type'      => 'REGULER',
                'duration_min'    => 30,
                'price_per_month' => 400000,
                'is_active'       => true,
                'sort_order'      => 1,
            ]
        );
        $student = Student::create([
            'student_code'        => 'M-2026-' . rand(1000, 9999),
            'full_name'           => $namaMusrid,
            'gender'              => 'L',
            'parent_relationship' => 'Ayah',
            'status'              => 'Aktif',
        ]);
        $enrollment = Enrollment::create([
            'student_id'     => $student->id,
            'package_id'     => $package->id,
            'teacher_id'     => $this->teacher->id,
            'effective_date' => '2026-01-01',
            'status'         => 'ACTIVE',
        ]);

        for ($i = 1; $i <= $jumlahSesi; $i++) {
            ClassSession::create([
                'enrollment_id' => $enrollment->id,
                'student_id'    => $student->id,
                'teacher_id'    => $this->teacher->id,
                'session_date'  => Carbon::create(2026, 5, $i * 6),
                'status'        => 'HADIR',
                'honor_code'    => 'H_REG',
                'honor_amount'  => $honorPerSesi,
            ]);
        }
    }

    /** Buat murid + sesi Kids Class untuk teacher */
    private function buatSesiKids(string $namaMurid, int $jumlahSesi): void
    {
        $instrumen = Instrument::firstOrCreate(['name' => 'Kids Class', 'code' => 'KID']);
        $package   = Package::firstOrCreate(
            ['code' => 'PKG-KIDS'],
            [
                'instrument_id'   => $instrumen->id,
                'class_type'      => 'KIDS_CLASS',
                'duration_min'    => 45,
                'price_per_month' => 340000,
                'is_active'       => true,
                'sort_order'      => 10,
            ]
        );
        $student = Student::create([
            'student_code'        => 'M-2026-' . rand(1000, 9999),
            'full_name'           => $namaMurid,
            'gender'              => 'P',
            'parent_relationship' => 'Ibu',
            'status'              => 'Aktif',
        ]);
        $enrollment = Enrollment::create([
            'student_id'     => $student->id,
            'package_id'     => $package->id,
            'teacher_id'     => $this->teacher->id,
            'effective_date' => '2026-01-01',
            'status'         => 'ACTIVE',
        ]);

        for ($i = 1; $i <= $jumlahSesi; $i++) {
            ClassSession::create([
                'enrollment_id' => $enrollment->id,
                'student_id'    => $student->id,
                'teacher_id'    => $this->teacher->id,
                'session_date'  => Carbon::create(2026, 5, $i * 6),
                'status'        => 'HADIR',
                'honor_code'    => 'H_KIDS',
                'honor_amount'  => 42500,
            ]);
        }
    }

    /** T1: Guru privat 2 murid */
    public function test_privat_dua_murid_menghasilkan_dua_baris(): void
    {
        $this->buatSesiPrivat('Aditya', 'Piano', 4, 50000);
        $this->buatSesiPrivat('Bintang', 'Gitar', 3, 48750);

        $result = $this->service->getStudentBreakdown($this->slip);

        $this->assertCount(2, $result);

        $aditya = $result->firstWhere('student_name', 'Aditya');
        $this->assertEquals(4, $aditya['session_count']);
        $this->assertEquals(200000, $aditya['total_amount']);
        $this->assertEquals('Piano', $aditya['instrument']);
        $this->assertFalse($aditya['is_kids']);

        $bintang = $result->firstWhere('student_name', 'Bintang');
        $this->assertEquals(3, $bintang['session_count']);
        $this->assertEquals(146250, $bintang['total_amount']);
        $this->assertFalse($bintang['is_kids']);
    }

    /** T2: Kids Class 4 murid, 4 sesi → tiap murid 4 sesi × 42.500 */
    public function test_kids_class_tiap_murid_dapat_baris_sendiri(): void
    {
        $this->buatSesiKids('Andi', 4);
        $this->buatSesiKids('Budi', 4);
        $this->buatSesiKids('Cici', 4);
        $this->buatSesiKids('Dodi', 4);

        $result = $this->service->getStudentBreakdown($this->slip);

        $this->assertCount(4, $result);
        $result->each(function ($row) {
            $this->assertEquals(4, $row['session_count']);
            $this->assertEquals(170000, $row['total_amount']);
            $this->assertEquals('Kids Class', $row['instrument']);
            $this->assertTrue($row['is_kids']);
        });
    }

    /** T3: Murid Kids Class yang hanya hadir 3 sesi dari 4 — honor sesuai sesi yang dicatat */
    public function test_kids_murid_dengan_sesi_berbeda(): void
    {
        $this->buatSesiKids('Andi', 4);
        $this->buatSesiKids('Cici', 3); // Cici hanya 3 sesi (misal 1 sesi IZIN_RESCHEDULE tidak masuk)

        $result = $this->service->getStudentBreakdown($this->slip);
        $this->assertCount(2, $result);

        $andi = $result->firstWhere('student_name', 'Andi');
        $this->assertEquals(4, $andi['session_count']);
        $this->assertEquals(170000, $andi['total_amount']);

        $cici = $result->firstWhere('student_name', 'Cici');
        $this->assertEquals(3, $cici['session_count']);
        $this->assertEquals(127500, $cici['total_amount']);
    }

    /** T4: Campuran privat + Kids Class — privat urut nama dulu, lalu kids */
    public function test_campuran_privat_dan_kids_class_urutan_benar(): void
    {
        $this->buatSesiKids('Zara', 4);         // Kids Class (Z — urut nama dalam Kids)
        $this->buatSesiPrivat('Aditya', 'Piano', 4, 50000); // Privat (A)

        $result = $this->service->getStudentBreakdown($this->slip);
        $this->assertCount(2, $result);

        // Privat dulu
        $this->assertFalse($result->first()['is_kids']);
        $this->assertEquals('Aditya', $result->first()['student_name']);

        // Kids Class belakang
        $this->assertTrue($result->last()['is_kids']);
        $this->assertEquals('Zara', $result->last()['student_name']);
    }

    /** T5: Sesi bulan lain tidak masuk ke breakdown */
    public function test_sesi_bulan_lain_tidak_masuk(): void
    {
        // Sesi bulan April (di luar bulan slip Mei)
        $instrumen = Instrument::firstOrCreate(['name' => 'Piano', 'code' => 'PNO']);
        $package   = Package::firstOrCreate(
            ['code' => 'PKG-PNO'],
            ['instrument_id' => $instrumen->id, 'class_type' => 'REGULER',
             'duration_min' => 30, 'price_per_month' => 400000, 'is_active' => true, 'sort_order' => 1]
        );
        $student = Student::create([
            'student_code' => 'M-2026-9999', 'full_name' => 'Lain Bulan',
            'gender' => 'L', 'parent_relationship' => 'Ayah', 'status' => 'Aktif',
        ]);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $package->id,
            'teacher_id' => $this->teacher->id, 'effective_date' => '2026-01-01', 'status' => 'ACTIVE',
        ]);
        ClassSession::create([
            'enrollment_id' => $enrollment->id, 'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'session_date' => '2026-04-10',  // April, bukan Mei
            'status' => 'HADIR', 'honor_code' => 'H_REG', 'honor_amount' => 50000,
        ]);

        $result = $this->service->getStudentBreakdown($this->slip);
        $this->assertCount(0, $result);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan GAGAL**

```bash
php artisan test tests/Unit/Services/HonorCalculationServiceStudentBreakdownTest.php --stop-on-error
```

Expected: Error `Call to undefined method App\Services\HonorCalculationService::getStudentBreakdown()`

- [ ] **Step 3: Implementasi `getStudentBreakdown()` di `HonorCalculationService`**

Tambahkan method berikut di `app/Services/HonorCalculationService.php`, setelah method `getSessionBreakdown()`:

```php
/**
 * Ringkasan honor per murid untuk slip cetak (M06 print view).
 *
 * Mengolah hasil getSessionBreakdown() menjadi 1 baris per murid.
 * Urutan: privat (urut nama A-Z) dulu, lalu Kids Class (urut nama A-Z).
 *
 * @return Collection — tiap item: [student_id, student_name, instrument,
 *                                   session_count, total_amount, is_kids]
 */
public function getStudentBreakdown(HonorSlip $slip): Collection
{
    $sessions = $this->getSessionBreakdown($slip);

    // Group by student_id, hitung session_count dan total_amount per murid
    $grouped = $sessions->groupBy('student_id')->map(function ($rows) {
        $first   = $rows->first();
        $isKids  = $first->honor_code === 'H_KIDS';

        // Nama instrumen: dari enrollment.package.instrument, fallback 'Kids Class'
        $instrument = $isKids
            ? 'Kids Class'
            : optional(optional(optional($first->enrollment)->package)->instrument)->name ?? '—';

        return [
            'student_id'    => $first->student_id,
            'student_name'  => optional($first->student)->full_name ?? '—',
            'instrument'    => $instrument,
            'session_count' => $rows->count(),
            'total_amount'  => $rows->sum('honor_amount'),
            'is_kids'       => $isKids,
        ];
    })->values();

    // Privat dulu (urut nama), lalu Kids Class (urut nama)
    $privat = $grouped->where('is_kids', false)->sortBy('student_name')->values();
    $kids   = $grouped->where('is_kids', true)->sortBy('student_name')->values();

    return $privat->concat($kids);
}
```

- [ ] **Step 4: Jalankan test — pastikan LULUS**

```bash
php artisan test tests/Unit/Services/HonorCalculationServiceStudentBreakdownTest.php -v
```

Expected: 5 tests PASS

- [ ] **Step 5: Jalankan full test suite — pastikan tidak ada regresi**

```bash
php artisan test
```

Expected: semua test pass, tidak ada yang baru gagal.

- [ ] **Step 6: Commit**

```bash
git add app/Services/HonorCalculationService.php tests/Unit/Services/
git commit -m "M06: Tambah getStudentBreakdown() di HonorCalculationService + unit tests"
```

---

## Task 3: Update `HonorController::print()`

**Files:**
- Modify: `app/Http/Controllers/HonorController.php` — method `print()` saja

- [ ] **Step 1: Update method `print()`**

Di `app/Http/Controllers/HonorController.php`, ganti method `print()` (baris 214–228):

```php
/**
 * Halaman A4 untuk dicetak (Ctrl+P → PDF).
 */
public function print(HonorSlip $honor)
{
    $honor->load('teacher.instruments', 'paidBy');

    $studentBreakdown = $this->service->getStudentBreakdown($honor);
    $hasKids          = $studentBreakdown->where('is_kids', true)->isNotEmpty();

    $monthName = Carbon::create($honor->year, $honor->month, 1)->format('F Y');

    return view('honors.print', compact(
        'honor', 'studentBreakdown', 'hasKids', 'monthName'
    ));
}
```

- [ ] **Step 2: Verifikasi tidak ada error fatal**

```bash
php artisan route:list | grep honor
```

Buka URL `/honors/1/print` di browser (sesuaikan ID slip yang ada).
Expected: halaman terbuka tanpa error (meski tampilan belum diubah — view masih pakai variabel lama `$breakdown`).

> Catatan: view akan error karena `$breakdown` tidak lagi dikirim. Lanjut ke Task 4 segera.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/HonorController.php
git commit -m "M06: Update HonorController::print() pakai getStudentBreakdown()"
```

---

## Task 4: Redesain `honors/print.blade.php`

**Files:**
- Modify: `resources/views/honors/print.blade.php` — ganti seluruh isi

- [ ] **Step 1: Ganti seluruh isi `print.blade.php`**

Ganti isi file `resources/views/honors/print.blade.php` dengan:

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $honor->slip_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; background: #fff; }

        .page { max-width: 680px; margin: 0 auto; padding: 32px 28px; }

        /* Header */
        .header { display: flex; justify-content: space-between; align-items: flex-start;
                  border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
        .studio-name { font-size: 20px; font-weight: bold; }
        .studio-sub  { font-size: 11px; color: #555; margin-top: 2px; }
        .bank-info   { margin-top: 5px; font-size: 9.5px; color: #555; font-style: italic; line-height: 1.6; }
        .slip-title  { text-align: right; }
        .slip-title .label  { font-size: 11px; color: #666; }
        .slip-title .number { font-size: 14px; font-weight: bold; font-family: monospace; }

        /* Info guru */
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px;
                     background: #f9f9f9; padding: 12px; border-radius: 4px; margin-bottom: 16px; }
        .meta-item .label { font-size: 10px; color: #666; }
        .meta-item .value { font-size: 13px; font-weight: 500; }

        /* Section heading */
        h2 { font-size: 12px; font-weight: bold; border-bottom: 1px solid #ddd;
             padding-bottom: 4px; margin-bottom: 8px; }

        /* Tabel umum */
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 16px; }
        th { text-align: left; font-size: 10px; text-transform: uppercase;
             color: #666; border-bottom: 1px solid #ccc; padding: 4px 6px; font-weight: normal; }
        td { padding: 5px 6px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }
        .font-mono  { font-family: monospace; }
        .font-bold  { font-weight: bold; }

        /* Baris Kids Class */
        .row-kids { background: #fffbf0; }
        .row-kids td:nth-child(2) { font-size: 10px; color: #888; }

        /* Footer tabel per-murid */
        .subtotal-row td { border-top: 2px solid #aaa; border-bottom: none; font-weight: bold; }

        /* Baris total komponen honor */
        .total-row td { border-top: 2px solid #111; border-bottom: none; font-weight: bold; }

        /* Catatan kaki Kids Class */
        .kids-note { font-size: 10px; color: #888; font-style: italic;
                     margin-top: -10px; margin-bottom: 16px; padding-left: 6px; }

        /* Status badge */
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 3px;
                        font-size: 10px; font-weight: bold; }
        .status-paid  { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .status-calc  { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .status-draft { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }

        /* Tanda tangan */
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 28px; }
        .sign-box { text-align: center; }
        .sign-box .role { font-size: 10px; color: #666; }
        .sign-box .line { border-bottom: 1px solid #111; height: 50px; margin: 6px 10px; }
        .sign-box .name { font-size: 11px; }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
        }
    </style>
</head>
<body>
<div class="page">

    {{-- Tombol cetak --}}
    <div class="no-print" style="text-align:right; margin-bottom:16px;">
        <button onclick="window.print()" style="padding:6px 16px; background:#1e3a5f; color:#fff; border:none; border-radius:4px; cursor:pointer;">
            🖨 Cetak / Simpan PDF
        </button>
        <a href="{{ route('honors.show', $honor) }}"
           style="margin-left:10px; font-size:12px; color:#555; text-decoration:none;">
            ← Kembali
        </a>
    </div>

    {{-- Header --}}
    <div class="header">
        <div>
            <img src="{{ asset('images/logo-musikkita-light-mode.PNG') }}"
                 alt="Musik KITA"
                 style="height:48px; max-width:190px; object-fit:contain; object-position:left; display:block;">
            <div class="studio-sub">Slip Honor Guru</div>
            @if($honor->teacher->bank_name || $honor->teacher->bank_account)
                <div class="bank-info">
                    {{ $honor->teacher->bank_name }}
                    @if($honor->teacher->bank_account)
                        &nbsp;·&nbsp; {{ $honor->teacher->bank_account }}
                    @endif
                    @if($honor->teacher->bank_account_holder)
                        <br>a.n. {{ $honor->teacher->bank_account_holder }}
                    @endif
                </div>
            @endif
        </div>
        <div class="slip-title">
            <div class="label">No. Slip</div>
            <div class="number">{{ $honor->slip_number }}</div>
            <div style="margin-top:4px;">
                @if($honor->status === 'PAID')
                    <span class="status-badge status-paid">DIBAYARKAN</span>
                @elseif($honor->status === 'CALCULATED')
                    <span class="status-badge status-calc">TERHITUNG</span>
                @else
                    <span class="status-badge status-draft">DRAFT</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Info guru --}}
    <div class="meta-grid">
        <div class="meta-item">
            <div class="label">Nama Guru</div>
            <div class="value">{{ $honor->teacher->name }}</div>
        </div>
        <div class="meta-item">
            <div class="label">Periode</div>
            <div class="value">{{ $monthName }}</div>
        </div>
        <div class="meta-item">
            <div class="label">Instrumen</div>
            <div class="value">
                {{ $honor->teacher->instruments->pluck('name')->implode(', ') ?: '—' }}
            </div>
        </div>
        <div class="meta-item">
            <div class="label">Tanggal Cetak</div>
            <div class="value">{{ now()->format('d M Y') }}</div>
        </div>
    </div>

    {{-- Rincian sesi per murid --}}
    @if($studentBreakdown->isNotEmpty())
        <h2>Rincian Sesi per Murid</h2>
        <table>
            <thead>
                <tr>
                    <th>Nama Murid</th>
                    <th>Instrumen</th>
                    <th class="text-right">Sesi</th>
                    <th class="text-right">Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($studentBreakdown as $row)
                    <tr class="{{ $row['is_kids'] ? 'row-kids' : '' }}">
                        <td>{{ $row['student_name'] }}</td>
                        <td>{{ $row['instrument'] }}</td>
                        <td class="text-right">{{ $row['session_count'] }}</td>
                        <td class="text-right font-mono">
                            {{ number_format($row['total_amount'], 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="subtotal-row">
                    <td colspan="2" class="font-bold">Subtotal Honor Pokok</td>
                    <td class="text-right font-bold">
                        {{ $studentBreakdown->sum('session_count') }} sesi
                    </td>
                    <td class="text-right font-mono font-bold">
                        {{ number_format($studentBreakdown->sum('total_amount'), 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>

        @if($hasKids)
            <p class="kids-note">
                * Kids Class: honor per murid = jumlah sesi × Rp 42.500
                (dihitung dari murid terdaftar, bukan kehadiran)
            </p>
        @endif
    @endif

    {{-- Komponen honor --}}
    <h2>Komponen Honor</h2>
    <table>
        <tbody>
            <tr>
                <td>Honor Pokok</td>
                <td class="text-right" style="color:#888;">
                    {{ $studentBreakdown->sum('session_count') }} sesi
                </td>
                <td class="text-right font-mono">
                    {{ number_format($honor->base_honor, 0, ',', '.') }}
                </td>
            </tr>
            @if($honor->event_honor > 0)
                <tr>
                    <td>Honor Event</td>
                    <td style="color:#888;">{{ $honor->event_honor_note ?: 'Input manual' }}</td>
                    <td class="text-right font-mono">
                        {{ number_format($honor->event_honor, 0, ',', '.') }}
                    </td>
                </tr>
            @endif
            <tr>
                <td>Honor Transport</td>
                <td style="color:#888;">Input manual</td>
                <td class="text-right font-mono">
                    {{ number_format($honor->transport_honor, 0, ',', '.') }}
                </td>
            </tr>
            <tr>
                <td>Honor Lain-lain</td>
                <td style="color:#888;">{{ $honor->other_honor_note ?: '—' }}</td>
                <td class="text-right font-mono">
                    {{ number_format($honor->other_honor, 0, ',', '.') }}
                </td>
            </tr>
            <tr class="total-row">
                <td colspan="2" class="font-bold">TOTAL HONOR YANG DITERIMA</td>
                <td class="text-right font-mono font-bold">
                    Rp {{ number_format($honor->total_honor, 0, ',', '.') }}
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Tanda tangan --}}
    <div class="signatures">
        <div class="sign-box">
            <div class="role">Penerima Honor</div>
            <div class="line"></div>
            <div class="name">{{ $honor->teacher->name }}</div>
        </div>
        <div class="sign-box">
            <div class="role">Pimpinan Studio</div>
            <div class="line"></div>
            <div class="name">Charly Nurjaya, S.MG</div>
        </div>
    </div>

    @if($honor->status === 'PAID' && $honor->paid_at)
        <p style="font-size:10px; color:#888; text-align:right; margin-top:12px;">
            Dibayarkan: {{ $honor->paid_at->format('d M Y') }}
            @if($honor->paidBy) — {{ $honor->paidBy->name }} @endif
        </p>
    @endif

</div>
</body>
</html>
```

- [ ] **Step 2: Buka slip di browser dan verifikasi**

Buka `/honors/{id}/print` untuk slip yang punya sesi terdaftar.

Checklist visual:
- [ ] Info bank muncul di header kiri, italic kecil, di bawah "Slip Honor Guru"
- [ ] Tabel per murid muncul dengan kolom: Nama | Instrumen | Sesi | Jumlah
- [ ] Baris Kids Class background kuning muda `#fffbf0`
- [ ] Catatan kaki Kids Class muncul jika ada murid Kids Class
- [ ] Section "Komponen Honor" di bawah tabel murid
- [ ] Baris "TOTAL HONOR YANG DITERIMA" font sama dengan baris di atasnya, hanya bold
- [ ] Tanda tangan kanan: "Charly Nurjaya, S.MG"
- [ ] Tidak ada navy blue box

- [ ] **Step 3: Jalankan full test suite**

```bash
php artisan test
```

Expected: semua test pass.

- [ ] **Step 4: Commit**

```bash
git add resources/views/honors/print.blade.php
git commit -m "M06: Redesain slip honor cetak — rincian per murid, bank di header"
```

---

## Checklist Akhir

- [ ] Kolom `bank_account_holder` tersimpan dan tampil di form profil guru
- [ ] `getStudentBreakdown()` 5 tests pass
- [ ] Full test suite pass tanpa regresi
- [ ] Slip cetak menampilkan tabel per murid
- [ ] `show.blade.php` (halaman internal) tidak berubah dan masih bekerja normal
- [ ] Cetak PDF via Ctrl+P menghasilkan layout yang bersih
