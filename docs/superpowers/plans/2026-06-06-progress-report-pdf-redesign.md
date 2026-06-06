# Progress Report PDF Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti form laporan guru, halaman admin show, dan PDF agar sesuai mockup baru — rating bulanan manual, catatan naratif, kesimpulan progress, progress bar manual; data sesi tetap dari sync catatan per sesi yang sudah ada.

**Architecture:** Tambah kolom bulanan di `progress_reports`. Rating header "Anak hari Ini" dihitung otomatis dari rata-rata `session_rating` di `progress_report_session_notes`. Minggu 1–4 di PDF/form menampilkan `material_learned` per `session_sequence`. Checklist `report_template` tidak lagi ditampilkan/diupdate; kolom lama tetap di DB untuk laporan historis.

**Tech Stack:** Laravel 11, Blade, DomPDF (barryvdh/laravel-dompdf), Alpine.js, PHPUnit, Spatie Permission

**Design spec:** `docs/superpowers/specs/2026-06-06-progress-report-pdf-redesign-design.md`

---

## Keputusan Desain (disetujui)

| Aspek | Keputusan |
|-------|-----------|
| Rating Anak hari Ini (header) | Otomatis = rata-rata `session_rating` dari session notes bulan ini |
| Minggu 1–4 | Slot tetap 4; isi `material_learned` by `session_sequence` 1–4 |
| Rating Teknik/Materi/Reading/Repertoar | Input manual guru (1–5) |
| Catatan Karakter | Satu field (duplikat mockup = typo) |
| Kesimpulan Progress | `PERLU_PENDAMPINGAN`, `CUKUP`, `BAIK`, `SANGAT_BAIK` |
| Progress bar footer | Manual guru 0–100%, wajib saat submit |
| Emoji instrumen | Map statis `config/instruments.php` by `instrument.code` |

---

## File Map

**Create:**
- `docs/superpowers/specs/2026-06-06-progress-report-pdf-redesign-design.md`
- `database/migrations/2026_06_07_100002_add_redesign_fields_to_progress_reports_table.php`
- `config/instruments.php`
- `resources/views/components/star-rating-select.blade.php`
- `resources/views/components/kesimpulan-progress-select.blade.php`
- `tests/Unit/ProgressReportHelpersTest.php`

**Modify:**
- `app/Models/ProgressReport.php` — fillable, casts, helpers
- `app/Models/Package.php` — `getLevelLabel()`
- `app/Http/Controllers/GuruController.php` — `laporanUpdate()` validation + save
- `app/Http/Controllers/ProgressReportController.php` — kurangi eager-load template
- `resources/views/guru/laporan-form.blade.php` — rewrite total
- `resources/views/progress-reports/show.blade.php` — rewrite total
- `resources/views/progress-reports/pdf.blade.php` — rewrite total
- `tests/Feature/ProgressReportGuruTest.php` — update submit/draft tests

---

### Task 1: Design Spec

**Files:**
- Create: `docs/superpowers/specs/2026-06-06-progress-report-pdf-redesign-design.md`

- [ ] **Step 1: Write design spec**

Isi minimal:
- Mockup → field mapping (header, minggu 1–4, rating bulanan, catatan, kesimpulan, footer)
- Edge cases: bulan <4 sesi (box kosong), tidak ada session rating (header tampil "—"), laporan lama dengan checklist tetap ada di DB tapi tidak di UI baru
- Validasi submit vs draft

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/specs/2026-06-06-progress-report-pdf-redesign-design.md
git commit -m "docs: add progress report PDF redesign design spec"
```

---

### Task 2: Migration + Config + Model Helpers

**Files:**
- Create: `database/migrations/2026_06_07_100002_add_redesign_fields_to_progress_reports_table.php`
- Create: `config/instruments.php`
- Modify: `app/Models/ProgressReport.php`
- Modify: `app/Models/Package.php`
- Test: `tests/Unit/ProgressReportHelpersTest.php`

- [ ] **Step 1: Write failing unit tests**

Create `tests/Unit/ProgressReportHelpersTest.php`:

```php
<?php
namespace Tests\Unit;

use App\Models\ProgressReport;
use App\Models\ProgressReportSessionNote;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Instrument;
use App\Models\ReportTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressReportHelpersTest extends TestCase
{
    use RefreshDatabase;

    private function makeReport(): ProgressReport
    {
        $instrument = Instrument::create(['code' => 'VOCAL', 'name' => 'Vocal', 'is_active' => true, 'sort_order' => 1]);
        $package = Package::create([
            'code' => 'VOCAL_HOBBY_30', 'instrument_id' => $instrument->id,
            'class_type' => 'HOBBY', 'duration_min' => 30,
            'price_per_month' => 390000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $student = Student::factory()->create();
        $teacher = Teacher::factory()->create();
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $package->id,
            'teacher_id' => $teacher->id, 'status' => 'ACTIVE',
            'effective_date' => now()->toDateString(), 'is_primary' => true,
        ]);
        $template = ReportTemplate::create([
            'instrument_id' => $instrument->id, 'name' => 'Vocal Hobby',
            'template_kind' => 'HOBBY', 'is_active' => true, 'sort_order' => 1,
        ]);

        return ProgressReport::create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'report_template_id' => $template->id,
            'month' => 6, 'year' => 2026, 'status' => 'DRAFT',
        ]);
    }

    public function test_average_session_rating_returns_null_when_no_ratings(): void
    {
        $report = $this->makeReport();
        $this->assertNull($report->averageSessionRating());
    }

    public function test_average_session_rating_averages_non_null_ratings(): void
    {
        $report = $this->makeReport();
        ProgressReportSessionNote::create([
            'progress_report_id' => $report->id, 'session_date' => '2026-06-03',
            'session_sequence' => 1, 'session_rating' => 3, 'sort_order' => 0,
        ]);
        ProgressReportSessionNote::create([
            'progress_report_id' => $report->id, 'session_date' => '2026-06-10',
            'session_sequence' => 2, 'session_rating' => 5, 'sort_order' => 1,
        ]);
        $report->load('sessionNotes');

        $this->assertSame(4.0, $report->averageSessionRating());
    }

    public function test_weekly_materials_maps_by_session_sequence(): void
    {
        $report = $this->makeReport();
        ProgressReportSessionNote::create([
            'progress_report_id' => $report->id, 'session_date' => '2026-06-03',
            'session_sequence' => 1, 'material_learned' => 'Skala C', 'sort_order' => 0,
        ]);
        ProgressReportSessionNote::create([
            'progress_report_id' => $report->id, 'session_date' => '2026-06-17',
            'session_sequence' => 3, 'material_learned' => 'Arpeggio', 'sort_order' => 1,
        ]);
        $report->load('sessionNotes');

        $this->assertSame([
            1 => 'Skala C',
            2 => null,
            3 => 'Arpeggio',
            4 => null,
        ], $report->weeklyMaterials());
    }

    public function test_render_stars(): void
    {
        $this->assertSame('—', ProgressReport::renderStars(null));
        $this->assertSame('★★★☆☆', ProgressReport::renderStars(3));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/ProgressReportHelpersTest.php`
Expected: FAIL — methods not defined

- [ ] **Step 3: Write migration**

`database/migrations/2026_06_07_100002_add_redesign_fields_to_progress_reports_table.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating_teknik')->nullable()->after('repertoire');
            $table->unsignedTinyInteger('rating_materi')->nullable()->after('rating_teknik');
            $table->unsignedTinyInteger('rating_reading')->nullable()->after('rating_materi');
            $table->unsignedTinyInteger('rating_repertoar')->nullable()->after('rating_reading');
            $table->text('catatan_perkembangan_musikal')->nullable()->after('rating_repertoar');
            $table->text('catatan_karakter')->nullable()->after('catatan_perkembangan_musikal');
            $table->enum('kesimpulan_progress', [
                'PERLU_PENDAMPINGAN', 'CUKUP', 'BAIK', 'SANGAT_BAIK',
            ])->nullable()->after('catatan_karakter');
            $table->unsignedTinyInteger('progress_percent')->nullable()->after('kesimpulan_progress');
        });
    }

    public function down(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->dropColumn([
                'rating_teknik', 'rating_materi', 'rating_reading', 'rating_repertoar',
                'catatan_perkembangan_musikal', 'catatan_karakter',
                'kesimpulan_progress', 'progress_percent',
            ]);
        });
    }
};
```

- [ ] **Step 4: Create `config/instruments.php`**

```php
<?php
return [
    'emojis' => [
        'PIANO'  => '🎹',
        'GITAR'  => '🎸',
        'DRUM'   => '🥁',
        'VOCAL'  => '🎤',
        'BASS'   => '🎸',
        'VIOLIN' => '🎻',
        'KIDS'   => '🎵',
        'SAX'    => '🎷',
    ],
    'default_emoji' => '🎵',
];
```

- [ ] **Step 5: Update `ProgressReport` model**

Add to `$fillable`: `rating_teknik`, `rating_materi`, `rating_reading`, `rating_repertoar`, `catatan_perkembangan_musikal`, `catatan_karakter`, `kesimpulan_progress`, `progress_percent`.

Add constants + methods:

```php
public const KESIMPULAN_PERLU_PENDAMPINGAN = 'PERLU_PENDAMPINGAN';
public const KESIMPULAN_CUKUP = 'CUKUP';
public const KESIMPULAN_BAIK = 'BAIK';
public const KESIMPULAN_SANGAT_BAIK = 'SANGAT_BAIK';

public static function kesimpulanLabels(): array
{
    return [
        self::KESIMPULAN_PERLU_PENDAMPINGAN => 'Perlu Pendampingan Lebih',
        self::KESIMPULAN_CUKUP              => 'Cukup',
        self::KESIMPULAN_BAIK               => 'Baik',
        self::KESIMPULAN_SANGAT_BAIK        => 'Sangat Baik',
    ];
}

public function averageSessionRating(): ?float
{
    $ratings = $this->sessionNotes->pluck('session_rating')->filter(fn ($r) => $r !== null);
    if ($ratings->isEmpty()) {
        return null;
    }
    return round($ratings->avg(), 1);
}

public function weeklyMaterials(): array
{
    $materials = [1 => null, 2 => null, 3 => null, 4 => null];
    foreach ($this->sessionNotes as $note) {
        if ($note->session_sequence >= 1 && $note->session_sequence <= 4) {
            $materials[$note->session_sequence] = $note->material_learned;
        }
    }
    return $materials;
}

public static function renderStars(?int $rating): string
{
    if ($rating === null) {
        return '—';
    }
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

public function instrumentEmoji(): string
{
    $code = $this->enrollment->package->instrument->code ?? '';
    return config("instruments.emojis.{$code}", config('instruments.default_emoji'));
}
```

- [ ] **Step 6: Add `Package::getLevelLabel()`**

```php
public function getLevelLabel(): string
{
    if ($this->isKidsClass()) {
        return 'Kids Class';
    }
    if ($this->class_type === 'HOBBY') {
        return 'Hobby';
    }
    if ($this->isDuo()) {
        return 'Basic · Belajar Berdua';
    }
    if ($this->class_type === 'REGULER') {
        return $this->grade === 'BASIC' ? 'Basic' : 'Level ' . ($this->grade ?? '-');
    }
    return $this->code;
}
```

- [ ] **Step 7: Run migration + tests**

```bash
php artisan migrate
php artisan test tests/Unit/ProgressReportHelpersTest.php
```

Expected: PASS (4 tests)

- [ ] **Step 8: Commit**

```bash
git add database/migrations config/instruments.php app/Models tests/Unit/ProgressReportHelpersTest.php
git commit -m "feat: add progress report redesign fields and model helpers"
```

---

### Task 3: GuruController — Validation & Save

**Files:**
- Modify: `app/Http/Controllers/GuruController.php:353-407`
- Test: `tests/Feature/ProgressReportGuruTest.php`

- [ ] **Step 1: Write failing feature tests**

Add to `ProgressReportGuruTest.php`:

```php
public function test_guru_bisa_simpan_draft_dengan_field_baru(): void
{
    $report = ProgressReport::create([
        'enrollment_id' => $this->enrollment->id,
        'student_id' => $this->enrollment->student_id,
        'teacher_id' => $this->teacher->id,
        'report_template_id' => $this->template->id,
        'month' => 5, 'year' => 2026, 'status' => 'DRAFT',
    ]);

    $this->actingAs($this->guruUser)
        ->put("/guru/laporan/{$report->id}", [
            'rating_teknik' => 4,
            'rating_materi' => 3,
            'rating_reading' => 5,
            'rating_repertoar' => 4,
            'catatan_perkembangan_musikal' => 'Teknik jari membaik.',
            'catatan_karakter' => 'Rajin dan fokus.',
            'kesimpulan_progress' => 'BAIK',
            'progress_percent' => 40,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('progress_reports', [
        'id' => $report->id,
        'rating_teknik' => 4,
        'kesimpulan_progress' => 'BAIK',
        'progress_percent' => 40,
    ]);
}

public function test_submit_gagal_jika_field_wajib_kosong(): void
{
    $report = ProgressReport::create([
        'enrollment_id' => $this->enrollment->id,
        'student_id' => $this->enrollment->student_id,
        'teacher_id' => $this->teacher->id,
        'report_template_id' => $this->template->id,
        'month' => 5, 'year' => 2026, 'status' => 'DRAFT',
    ]);

    $this->actingAs($this->guruUser)
        ->put("/guru/laporan/{$report->id}", ['submit' => '1'])
        ->assertSessionHasErrors([
            'rating_teknik', 'rating_materi', 'rating_reading', 'rating_repertoar',
            'kesimpulan_progress', 'progress_percent',
        ]);
}
```

Update existing `test_guru_bisa_submit_laporan` payload:

```php
$this->actingAs($this->guruUser)
    ->put("/guru/laporan/{$report->id}", [
        'rating_teknik' => 4,
        'rating_materi' => 4,
        'rating_reading' => 3,
        'rating_repertoar' => 4,
        'catatan_perkembangan_musikal' => 'Bagus bulan ini.',
        'catatan_karakter' => 'Disiplin latihan.',
        'kesimpulan_progress' => 'BAIK',
        'progress_percent' => 40,
        'submit' => '1',
    ])
    ->assertRedirect();
```

- [ ] **Step 2: Run tests to verify failure**

Run: `php artisan test --filter=ProgressReportGuruTest`
Expected: FAIL on new/updated submit tests

- [ ] **Step 3: Replace `laporanUpdate` validation + save block**

Remove: `highlight`, `summary_notes`, `target_notes`, `repertoire`, `section_summary`, `checked_items` validation and DB updates.

Replace with:

```php
$rules = [
    'rating_teknik'                => 'nullable|integer|min:1|max:5',
    'rating_materi'                => 'nullable|integer|min:1|max:5',
    'rating_reading'               => 'nullable|integer|min:1|max:5',
    'rating_repertoar'             => 'nullable|integer|min:1|max:5',
    'catatan_perkembangan_musikal' => 'nullable|string|max:3000',
    'catatan_karakter'             => 'nullable|string|max:3000',
    'kesimpulan_progress'          => 'nullable|in:PERLU_PENDAMPINGAN,CUKUP,BAIK,SANGAT_BAIK',
    'progress_percent'             => 'nullable|integer|min:0|max:100',
];

if ($request->input('submit') === '1') {
    $rules['rating_teknik'] = 'required|integer|min:1|max:5';
    $rules['rating_materi'] = 'required|integer|min:1|max:5';
    $rules['rating_reading'] = 'required|integer|min:1|max:5';
    $rules['rating_repertoar'] = 'required|integer|min:1|max:5';
    $rules['kesimpulan_progress'] = 'required|in:PERLU_PENDAMPINGAN,CUKUP,BAIK,SANGAT_BAIK';
    $rules['progress_percent'] = 'required|integer|min:0|max:100';
}

$validated = $request->validate($rules);

$progressReport->update([
    'rating_teknik'                => $validated['rating_teknik'] ?? null,
    'rating_materi'                => $validated['rating_materi'] ?? null,
    'rating_reading'               => $validated['rating_reading'] ?? null,
    'rating_repertoar'             => $validated['rating_repertoar'] ?? null,
    'catatan_perkembangan_musikal' => $validated['catatan_perkembangan_musikal'] ?? null,
    'catatan_karakter'             => $validated['catatan_karakter'] ?? null,
    'kesimpulan_progress'          => $validated['kesimpulan_progress'] ?? null,
    'progress_percent'             => $validated['progress_percent'] ?? null,
]);
```

Also simplify `laporanForm()` eager-load — remove `template.sections.items`, `sections`, `items` if no longer used in view.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=ProgressReportGuruTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/GuruController.php tests/Feature/ProgressReportGuruTest.php
git commit -m "feat: save redesigned progress report fields in guru laporan update"
```

---

### Task 4: Blade Components

**Files:**
- Create: `resources/views/components/star-rating-select.blade.php`
- Create: `resources/views/components/kesimpulan-progress-select.blade.php`

- [ ] **Step 1: Create star-rating-select component**

```blade
@props(['name', 'label', 'value' => null])

<div>
    <label class="block text-sm font-medium text-mk-text mb-1">{{ $label }}</label>
    <select name="{{ $name }}"
            class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
        <option value="">— Pilih rating —</option>
        @for ($i = 1; $i <= 5; $i++)
            <option value="{{ $i }}" @selected((int) old($name, $value) === $i)>
                {{ str_repeat('★', $i) }}{{ str_repeat('☆', 5 - $i) }}
            </option>
        @endfor
    </select>
    @error($name)
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>
```

- [ ] **Step 2: Create kesimpulan-progress-select component**

```blade
@props(['name' => 'kesimpulan_progress', 'value' => null])

<div>
    <div class="font-semibold text-sm text-mk-text mb-2">Kesimpulan Progress</div>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach(\App\Models\ProgressReport::kesimpulanLabels() as $key => $label)
            <label class="cursor-pointer">
                <input type="radio" name="{{ $name }}" value="{{ $key }}" class="sr-only peer"
                       @checked(old($name, $value) === $key)>
                <div class="text-center text-xs border border-mk-border rounded-lg px-2 py-3
                            peer-checked:border-mk-accent peer-checked:bg-mk-accent/10 peer-checked:font-semibold">
                    {{ $label }}
                </div>
            </label>
        @endforeach
    </div>
    @error($name)
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/components/star-rating-select.blade.php resources/views/components/kesimpulan-progress-select.blade.php
git commit -m "feat: add star rating and kesimpulan progress form components"
```

---

### Task 5: Guru Form Rewrite

**Files:**
- Modify: `resources/views/guru/laporan-form.blade.php`

- [ ] **Step 1: Rewrite `laporan-form.blade.php` dengan scaffold berikut**

```blade
<x-guru-layout title="Isi Laporan">

@php
    $avgRating = $progressReport->averageSessionRating();
    $headerStars = $avgRating !== null
        ? \App\Models\ProgressReport::renderStars((int) round($avgRating))
        : '—';
    $weekly = $progressReport->weeklyMaterials();
    $mingguLabels = [1 => 'Minggu 1', 2 => 'Minggu 2', 3 => 'Minggu 3', 4 => 'Minggu 4'];
@endphp

<div class="px-4 pt-5 pb-2">
    <h1 class="text-base font-semibold text-mk-text">{{ $progressReport->student->full_name }}</h1>
    <p class="text-sm text-mk-muted">{{ $progressReport->enrollment->package->code }} · {{ $progressReport->namaBulan() }}</p>
</div>

<form method="POST" action="{{ route('guru.laporan.update', $progressReport) }}" onsubmit="return confirmSubmit(event)">
    @csrf @method('PUT')

    {{-- Header info + Rating Anak --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <table class="w-full text-sm">
            <tr><td class="text-mk-muted w-36 py-0.5">Nama</td><td class="py-0.5">: <strong>{{ $progressReport->student->full_name }}</strong></td></tr>
            <tr><td class="text-mk-muted py-0.5">Instrumen</td><td class="py-0.5">: {{ $progressReport->enrollment->package->instrument->name }}</td></tr>
            <tr><td class="text-mk-muted py-0.5">Guru Pengajar</td><td class="py-0.5">: {{ $progressReport->teacher->name }}</td></tr>
            <tr><td class="text-mk-muted py-0.5">Bulan</td><td class="py-0.5">: {{ $progressReport->namaBulan() }}</td></tr>
            <tr>
                <td class="text-mk-muted py-0.5">Rating Anak</td>
                <td class="py-0.5">: <span class="text-yellow-500 tracking-wide">{{ $headerStars }}</span></td>
            </tr>
        </table>
    </div>

    {{-- Kehadiran & Materi Minggu 1–4 --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-3">Kehadiran dan Materi yang Dipelajari</div>
        <div class="space-y-3">
            @foreach ($mingguLabels as $seq => $label)
                <div class="flex items-start gap-3">
                    <span class="text-sm text-mk-muted w-20 shrink-0 pt-2">{{ $label }}</span>
                    <div class="flex-1 border border-[#E8D5A0] rounded-lg bg-white px-3 py-2 text-sm min-h-[2.5rem] text-mk-text whitespace-pre-line">
                        {{ $weekly[$seq] ?? '—' }}
                    </div>
                </div>
            @endforeach
        </div>
        <p class="text-xs text-mk-muted mt-2">Diisi otomatis dari catatan sesi. Edit via Dashboard → sesi terkait.</p>
    </div>

    {{-- Perkembangan bulanan — 4 star ratings --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-3">
            Perkembangan {{ $progressReport->student->full_name }} Selama Les di Bulan {{ $progressReport->namaBulan() }}
        </div>
        <div class="space-y-3">
            <x-star-rating-select name="rating_teknik" label="Teknik Bermain" :value="$progressReport->rating_teknik" />
            <x-star-rating-select name="rating_materi" label="Materi" :value="$progressReport->rating_materi" />
            <x-star-rating-select name="rating_reading" label="Reading" :value="$progressReport->rating_reading" />
            <x-star-rating-select name="rating_repertoar" label="Repertoar" :value="$progressReport->rating_repertoar" />
        </div>
    </div>

    {{-- Catatan naratif --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-2">Catatan Guru Terhadap Perkembangan Musikal</div>
        <textarea name="catatan_perkembangan_musikal" rows="4"
                  placeholder="Tuliskan catatan perkembangan musikal murid..."
                  class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">{{ old('catatan_perkembangan_musikal', $progressReport->catatan_perkembangan_musikal) }}</textarea>
        @error('catatan_perkembangan_musikal')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-2">Catatan Guru Terhadap Karakter</div>
        <textarea name="catatan_karakter" rows="4"
                  placeholder="Tuliskan catatan karakter dan kebiasaan belajar murid..."
                  class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">{{ old('catatan_karakter', $progressReport->catatan_karakter) }}</textarea>
        @error('catatan_karakter')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Kesimpulan Progress --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <x-kesimpulan-progress-select :value="$progressReport->kesimpulan_progress" />
    </div>

    {{-- Progress percent + bar preview --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4"
         x-data="{ pct: {{ (int) old('progress_percent', $progressReport->progress_percent ?? 0) }} }">
        <div class="font-semibold text-sm text-mk-text mb-2">Progress Keseluruhan (%)</div>
        <div class="flex items-center gap-3 mb-2">
            <input type="number" name="progress_percent" min="0" max="100"
                   x-model.number="pct"
                   class="w-24 bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-center">
            <span class="text-sm text-mk-muted">%</span>
        </div>
        <div class="w-full bg-[#F0E4C0] rounded-full h-3 overflow-hidden border border-[#C8A870]">
            <div class="bg-[#C8A870] h-3 rounded-full transition-all"
                 :style="'width:' + Math.min(pct, 100) + '%'"></div>
        </div>
        @error('progress_percent')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Catatan per sesi — read-only --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-1">Catatan Per Sesi</div>
        <p class="text-xs text-mk-muted mb-3">Diisi per sesi dari dashboard/jadwal — otomatis tampil di sini.</p>
        @forelse($progressReport->sessionNotes->sortBy([['session_date', 'asc'], ['sort_order', 'asc']]) as $note)
            <x-session-note-card
                :student-name="$progressReport->student->full_name"
                :teacher-name="$progressReport->teacher->name"
                :substitute-teacher-name="$note->substitute_teacher_name"
                :session-date="\Carbon\Carbon::parse($note->session_date)->locale('id')->translatedFormat('d F Y')"
                :session-rating="$note->session_rating"
                :material-learned="$note->material_learned"
                :homework-notes="$note->homework_notes"
                :notes="$note->notes"
                :show-empty-badge="true"
            />
        @empty
            <p class="text-sm text-mk-muted">Belum ada sesi HADIR bulan ini.</p>
        @endforelse
    </div>

    {{-- Submit buttons --}}
    <div class="mx-4 mb-8 flex gap-3">
        <button type="submit"
                class="flex-1 py-3 rounded-xl font-semibold text-sm border border-mk-accent/40 text-mk-accent hover:bg-mk-accent/10">
            Simpan Draft
        </button>
        <button type="submit" name="submit" value="1"
                class="flex-1 py-3 rounded-xl font-semibold text-sm btn-mk-primary">
            Submit Laporan
        </button>
    </div>
</form>

<script>
function confirmSubmit(event) {
    const isSubmit = event.submitter?.name === 'submit' || event.submitter?.value === '1';
    if (!isSubmit) return true;

    const hasEmptyNotes = @json(
        $progressReport->sessionNotes->contains(fn ($n) =>
            blank($n->material_learned) && blank($n->homework_notes) && blank($n->notes)
        )
    );
    if (hasEmptyNotes && !confirm('Masih ada sesi tanpa catatan. Lanjut submit?')) return false;
    return confirm('Submit laporan? Setelah disubmit, laporan tidak bisa diedit.');
}
</script>

</x-guru-layout>
```

- [ ] **Step 2: Manual smoke test**

Open `/guru/laporan/{id}/edit` as guru — verify no checklist, new fields render, draft save works.

- [ ] **Step 3: Commit**

```bash
git add resources/views/guru/laporan-form.blade.php
git commit -m "feat: redesign guru laporan form to match new progress report mockup"
```

---

### Task 6: Admin Show Rewrite

**Files:**
- Modify: `resources/views/progress-reports/show.blade.php`
- Modify: `app/Http/Controllers/ProgressReportController.php:52-64`

- [ ] **Step 1: Simplify controller eager-load in `show()`**

```php
$progressReport->load([
    'student',
    'teacher',
    'enrollment.package.instrument',
    'sessionNotes',
]);
```

- [ ] **Step 2: Rewrite `show.blade.php` dengan scaffold berikut**

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Laporan: {{ $progressReport->student->full_name }}</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    {{ $progressReport->teacher->name }} · {{ $progressReport->enrollment->package->code }} · {{ $progressReport->namaBulan() }}
                </div>
            </div>
            @if($progressReport->status === 'SUBMITTED')
                <a href="{{ route('progress-reports.pdf', $progressReport) }}" class="px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary">
                    ↓ Download PDF
                </a>
            @endif
        </div>
    </x-slot>

    @php
        $avgRating = $progressReport->averageSessionRating();
        $headerStars = $avgRating !== null
            ? \App\Models\ProgressReport::renderStars((int) round($avgRating))
            : '—';
        $weekly = $progressReport->weeklyMaterials();
        $pkg = $progressReport->enrollment->package;
        $emoji = $progressReport->instrumentEmoji();
        $pct = $progressReport->progress_percent ?? 0;
    @endphp

    <div class="py-6 px-4 lg:px-8 max-w-3xl space-y-4">

        {{-- Header meta --}}
        <div class="bg-white shadow-sm rounded-lg p-5">
            <table class="w-full text-sm">
                <tr><td class="text-gray-500 w-40 py-0.5">Nama</td><td class="py-0.5 font-semibold">{{ $progressReport->student->full_name }}</td></tr>
                <tr><td class="text-gray-500 py-0.5">Instrumen</td><td class="py-0.5">{{ $pkg->instrument->name }}</td></tr>
                <tr><td class="text-gray-500 py-0.5">Guru Pengajar</td><td class="py-0.5">{{ $progressReport->teacher->name }}</td></tr>
                <tr><td class="text-gray-500 py-0.5">Bulan</td><td class="py-0.5">{{ $progressReport->namaBulan() }}</td></tr>
                <tr>
                    <td class="text-gray-500 py-0.5">Rating Anak</td>
                    <td class="py-0.5 text-yellow-500 tracking-wide">{{ $headerStars }}</td>
                </tr>
            </table>
        </div>

        {{-- Kehadiran & Materi Minggu 1–4 --}}
        <div class="bg-white shadow-sm rounded-lg p-5">
            <div class="font-semibold text-sm text-gray-700 mb-3">Kehadiran dan Materi yang Dipelajari</div>
            <div class="space-y-2">
                @foreach ([1 => 'Minggu 1', 2 => 'Minggu 2', 3 => 'Minggu 3', 4 => 'Minggu 4'] as $seq => $label)
                    <div class="flex items-start gap-3">
                        <span class="text-sm text-gray-500 w-20 shrink-0 pt-2">{{ $label }}</span>
                        <div class="flex-1 border border-gray-200 rounded-lg bg-gray-50 px-3 py-2 text-sm min-h-[2.5rem] whitespace-pre-line text-gray-700">
                            {{ $weekly[$seq] ?? '—' }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Rating bulanan --}}
        <div class="bg-white shadow-sm rounded-lg p-5">
            <div class="font-semibold text-sm text-gray-700 mb-3">
                Perkembangan {{ $progressReport->student->full_name }} Selama Les di Bulan {{ $progressReport->namaBulan() }}
            </div>
            <div class="space-y-2 text-sm">
                @foreach ([
                    'Teknik Bermain' => $progressReport->rating_teknik,
                    'Materi'         => $progressReport->rating_materi,
                    'Reading'        => $progressReport->rating_reading,
                    'Repertoar'      => $progressReport->rating_repertoar,
                ] as $label => $rating)
                    <div class="flex items-center gap-3">
                        <span class="text-gray-500 w-32">{{ $label }}</span>
                        <span class="text-yellow-500 tracking-wide">
                            {{ $rating ? \App\Models\ProgressReport::renderStars($rating) : '—' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Catatan naratif --}}
        @if($progressReport->catatan_perkembangan_musikal)
            <div class="bg-white shadow-sm rounded-lg p-5">
                <div class="font-semibold text-sm text-gray-700 mb-2">Catatan Perkembangan Musikal</div>
                <p class="text-sm text-gray-600 whitespace-pre-line">{{ $progressReport->catatan_perkembangan_musikal }}</p>
            </div>
        @endif

        @if($progressReport->catatan_karakter)
            <div class="bg-white shadow-sm rounded-lg p-5">
                <div class="font-semibold text-sm text-gray-700 mb-2">Catatan Karakter</div>
                <p class="text-sm text-gray-600 whitespace-pre-line">{{ $progressReport->catatan_karakter }}</p>
            </div>
        @endif

        {{-- Kesimpulan Progress --}}
        @if($progressReport->kesimpulan_progress)
            <div class="bg-white shadow-sm rounded-lg p-5">
                <div class="font-semibold text-sm text-gray-700 mb-3">Kesimpulan Progress</div>
                <div class="grid grid-cols-4 gap-2 text-xs text-center">
                    @foreach (\App\Models\ProgressReport::kesimpulanLabels() as $key => $label)
                        <div class="border rounded-lg px-2 py-3
                            {{ $progressReport->kesimpulan_progress === $key
                                ? 'border-amber-600 bg-amber-50 font-bold text-amber-800'
                                : 'border-gray-200 text-gray-400' }}">
                            {{ $label }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Footer — Level + Progress bar --}}
        <div class="bg-white shadow-sm rounded-lg p-5">
            <div class="text-sm text-gray-700 mb-3">
                {{ $emoji }} {{ $pkg->instrument->name }} · {{ $pkg->getLevelLabel() }}
            </div>
            <div class="w-full bg-gray-100 rounded-full h-4 overflow-hidden border border-gray-200">
                <div class="bg-amber-400 h-4 rounded-full flex items-center justify-end pr-2"
                     style="width: {{ $pct }}%; min-width: {{ $pct > 0 ? '2rem' : '0' }};">
                    @if($pct > 0)
                        <span class="text-[10px] text-white font-bold">{{ $pct }}%</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Catatan per sesi --}}
        @if($progressReport->sessionNotes->isNotEmpty())
            <div class="bg-white shadow-sm rounded-lg p-5">
                <div class="font-semibold text-sm text-gray-700 mb-3">Catatan Per Sesi</div>
                @foreach($progressReport->sessionNotes->sortBy([['session_date', 'asc'], ['sort_order', 'asc']]) as $note)
                    <x-session-note-card
                        class="mb-4 border-gray-200 bg-gray-50/50"
                        :student-name="$progressReport->student->full_name"
                        :teacher-name="$progressReport->teacher->name"
                        :substitute-teacher-name="$note->substitute_teacher_name"
                        :session-date="\Carbon\Carbon::parse($note->session_date)->locale('id')->isoFormat('D MMMM Y')"
                        :session-rating="$note->session_rating"
                        :material-learned="$note->material_learned"
                        :homework-notes="$note->homework_notes"
                        :notes="$note->notes"
                    />
                @endforeach
            </div>
        @endif

        <a href="{{ route('progress-reports.index') }}" class="text-sm text-gray-500 hover:underline">← Kembali</a>
    </div>
</x-app-layout>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/progress-reports/show.blade.php app/Http/Controllers/ProgressReportController.php
git commit -m "feat: redesign admin progress report show page"
```

---

### Task 7: PDF Rewrite

**Files:**
- Modify: `resources/views/progress-reports/pdf.blade.php`
- Modify: `app/Http/Controllers/ProgressReportController.php:71-81` (same eager-load as show)

- [ ] **Step 1: Rewrite PDF template (DomPDF-safe table layout)**

Structure:
- Header box: Nama, Instrumen, Guru Pengajar, Bulan, Rating Anak hari Ini (stars)
- Section "Kehadiran dan Materi yang dipelajari": 4 rows `Minggu N` + bordered `<td>` with material or em-dash
- Section "Perkembangan [Nama] Selama Les di Bulan [Bulan]": 4 rows Teknik/Materi/Reading/Repertoar + stars from report fields
- Narrative boxes: catatan musikal, catatan karakter
- Kesimpulan: `<table>` 4 columns, selected cell gets thick border + `#FBF3E0` background
- Footer: emoji + instrument + level + progress bar using nested tables (`<td width="X%" bgcolor="#C8A870">`)
- Keep TTD block + footnote

Remove: checklist legend, `@foreach($progressReport->template->sections)`, repertoire, highlight, summary/target, old session detail loop.

Helper at top of blade:

```blade
@php
    $pkg = $progressReport->enrollment->package;
    $weekly = $progressReport->weeklyMaterials();
    $avgRating = $progressReport->averageSessionRating();
    $headerStars = $avgRating !== null
        ? \App\Models\ProgressReport::renderStars((int) round($avgRating))
        : '—';
    $pct = $progressReport->progress_percent ?? 0;
@endphp
```

Progress bar HTML untuk PDF (DomPDF) — **continuous bar**, label `%` di kanan bar:

```html
<table width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width:80%; vertical-align:middle;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #C8A870; border-radius:4px; overflow:hidden;">
                <tr>
                    <td width="{{ $pct }}%" bgcolor="#C8A870" style="height:12px;"></td>
                    <td width="{{ 100 - $pct }}%" bgcolor="#F0E4C0" style="height:12px;"></td>
                </tr>
            </table>
        </td>
        <td style="width:20%; text-align:right; padding-left:8px; font-size:10px; color:#3D2610; vertical-align:middle;">{{ $pct }}%</td>
    </tr>
</table>
```

- [ ] **Step 2: Write PDF feature test**

Add to `ProgressReportGuruTest.php`:

```php
public function test_admin_bisa_download_pdf_laporan_submitted(): void
{
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');

    $report = ProgressReport::create([
        'enrollment_id' => $this->enrollment->id,
        'student_id' => $this->enrollment->student_id,
        'teacher_id' => $this->teacher->id,
        'report_template_id' => $this->template->id,
        'month' => 5, 'year' => 2026, 'status' => 'SUBMITTED',
        'submitted_at' => now(),
        'rating_teknik' => 4, 'rating_materi' => 4,
        'rating_reading' => 3, 'rating_repertoar' => 4,
        'kesimpulan_progress' => 'BAIK',
        'progress_percent' => 40,
        'catatan_perkembangan_musikal' => 'Progres bagus.',
        'catatan_karakter' => 'Rajin.',
    ]);

    $this->actingAs($admin)
        ->get("/progress-reports/{$report->id}/pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
}
```

- [ ] **Step 3: Run test**

Run: `php artisan test --filter=test_admin_bisa_download_pdf_laporan_submitted`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add resources/views/progress-reports/pdf.blade.php app/Http/Controllers/ProgressReportController.php tests/Feature/ProgressReportGuruTest.php
git commit -m "feat: redesign progress report PDF to match new mockup layout"
```

---

### Task 8: Final Verification

**Files:** (none new)

- [ ] **Step 1: Run full test suite for affected areas**

```bash
php artisan test tests/Unit/ProgressReportHelpersTest.php tests/Feature/ProgressReportGuruTest.php
```

Expected: all PASS

- [ ] **Step 2: Manual verify**

1. Guru: buat draft → isi field baru → simpan → submit
2. Admin: buka show → download PDF → cek layout mockup
3. Edge case: laporan tanpa session rating → header stars tampil "—"

- [ ] **Step 3: Commit any fixes, then done**

---

## Self-Review Checklist

| Spec requirement | Task |
|------------------|------|
| Header Nama/Instrumen/Guru/Bulan | Task 5, 6, 7 |
| Rating Anak = avg session ratings | Task 2 (helper), Task 5/6/7 (display) |
| Minggu 1–4 material boxes | Task 2 (weeklyMaterials), Task 5/6/7 |
| 4 monthly star ratings manual | Task 3, 5, 6, 7 |
| Catatan musikal + karakter (1 field) | Task 3, 5, 6, 7 |
| Kesimpulan 4 options | Task 4, 5, 6, 7 |
| Progress bar manual | Task 3, 5, 6, 7 |
| Emoji + level footer | Task 2 (config + getLevelLabel), Task 6, 7 |
| Remove checklist from guru flow | Task 3, 5 |
| Submit validation | Task 3 |
| Tests | Task 2, 3, 7 |

No TBD/placeholder steps remain.

---

## Out of Scope

- Drop `report_templates` tables (historical FK still needed)
- Kids Class custom layout variant
- Uncommitted session identity work (`getGuruSessionIdentity`) — commit separately if desired
