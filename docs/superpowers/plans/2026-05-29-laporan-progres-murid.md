# Laporan Progres Murid — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Guru isi laporan progres murid bulanan via portal, Admin/Owner download PDF untuk dikirim ke orangtua.

**Architecture:** Template checklist per instrumen dikelola Owner via Master Data (tabel `report_templates` + sections + items). Guru membuat laporan bulanan per enrollment (`progress_reports`) dengan mengisi checklist, narasi, dan catatan sesi. Admin/Owner download PDF yang formatnya mengikuti laporan Word yang sudah ada.

**Tech Stack:** Laravel 11, Blade, Tailwind CSS, barryvdh/laravel-dompdf (PDF), Spatie Permission (RBAC)

---

## File Map

**Migrations (7 file):**
- `database/migrations/2026_05_29_200001_create_report_templates_table.php`
- `database/migrations/2026_05_29_200002_create_report_template_sections_table.php`
- `database/migrations/2026_05_29_200003_create_report_template_items_table.php`
- `database/migrations/2026_05_29_200004_create_progress_reports_table.php`
- `database/migrations/2026_05_29_200005_create_progress_report_sections_table.php`
- `database/migrations/2026_05_29_200006_create_progress_report_items_table.php`
- `database/migrations/2026_05_29_200007_create_progress_report_session_notes_table.php`

**Models (7 file baru):**
- `app/Models/ReportTemplate.php`
- `app/Models/ReportTemplateSection.php`
- `app/Models/ReportTemplateItem.php`
- `app/Models/ProgressReport.php`
- `app/Models/ProgressReportSection.php`
- `app/Models/ProgressReportItem.php`
- `app/Models/ProgressReportSessionNote.php`

**Models dimodifikasi:**
- `app/Models/Instrument.php` — tambah relasi `reportTemplates()`
- `app/Models/Enrollment.php` — tambah relasi `progressReports()`

**Controllers (2 baru, 1 dimodifikasi):**
- `app/Http/Controllers/ReportTemplateController.php` — Owner CRUD template + sections + items
- `app/Http/Controllers/ProgressReportController.php` — Admin: index, show, PDF download
- `app/Http/Controllers/GuruController.php` — tambah: `laporan()`, `laporanCreate()`, `laporanStore()`, `laporanEdit()`, `laporanUpdate()`

**Views:**
- `resources/views/report-templates/index.blade.php`
- `resources/views/report-templates/show.blade.php` (sections + items dalam satu halaman)
- `resources/views/report-templates/create.blade.php`
- `resources/views/report-templates/edit.blade.php`
- `resources/views/progress-reports/index.blade.php` (Admin)
- `resources/views/progress-reports/show.blade.php` (Admin)
- `resources/views/progress-reports/pdf.blade.php` (template PDF cetak)
- `resources/views/guru/laporan.blade.php` (daftar laporan guru)
- `resources/views/guru/laporan-form.blade.php` (form isi laporan)

**Routes:** `routes/web.php` — tambah grup report-templates + progress-reports + guru laporan

**Navigation:** `resources/views/layouts/navigation.blade.php` + `resources/views/guru/dashboard.blade.php`

**Tests:**
- `tests/Feature/ReportTemplateTest.php`
- `tests/Feature/ProgressReportGuruTest.php`
- `tests/Feature/ProgressReportAdminTest.php`

---

## Task 1: Install PDF Library

- [ ] **1.1 Install barryvdh/laravel-dompdf**

```bash
composer require barryvdh/laravel-dompdf
```

Expected: `Package manifest generated successfully.`

- [ ] **1.2 Publish config**

```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

Expected: File `config/dompdf.php` terbuat.

- [ ] **1.3 Commit**

```bash
git add composer.json composer.lock config/dompdf.php
git commit -m "Feat: install barryvdh/laravel-dompdf untuk cetak laporan progres"
```

---

## Task 2: Migrations

- [ ] **2.1 Buat migration report_templates**

```bash
php artisan make:migration create_report_templates_table
```

Isi file migration yang dibuat (ganti tanggal sesuai output):

```php
public function up(): void
{
    Schema::create('report_templates', function (Blueprint $table) {
        $table->id();
        $table->foreignId('instrument_id')->constrained()->restrictOnDelete();
        $table->string('name', 100); // cth: "Template Vokal"
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(true);
        $table->unsignedTinyInteger('sort_order')->default(0);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('report_templates');
}
```

- [ ] **2.2 Buat migration report_template_sections**

```bash
php artisan make:migration create_report_template_sections_table
```

```php
public function up(): void
{
    Schema::create('report_template_sections', function (Blueprint $table) {
        $table->id();
        $table->foreignId('report_template_id')->constrained()->cascadeOnDelete();
        $table->string('title', 100); // cth: "Kemampuan Bernyanyi"
        $table->unsignedTinyInteger('sort_order')->default(0);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('report_template_sections');
}
```

- [ ] **2.3 Buat migration report_template_items**

```bash
php artisan make:migration create_report_template_items_table
```

```php
public function up(): void
{
    Schema::create('report_template_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('report_template_section_id')->constrained()->cascadeOnDelete();
        $table->string('label', 200); // cth: "Teknik Pernafasan Diafragma"
        $table->unsignedTinyInteger('sort_order')->default(0);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('report_template_items');
}
```

- [ ] **2.4 Buat migration progress_reports**

```bash
php artisan make:migration create_progress_reports_table
```

```php
public function up(): void
{
    Schema::create('progress_reports', function (Blueprint $table) {
        $table->id();
        $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
        $table->foreignId('student_id')->constrained()->restrictOnDelete();
        $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
        $table->foreignId('report_template_id')->constrained()->restrictOnDelete();
        $table->unsignedTinyInteger('month');  // 1–12
        $table->unsignedSmallInteger('year');
        $table->enum('status', ['DRAFT', 'SUBMITTED'])->default('DRAFT');
        $table->text('highlight')->nullable();    // Highlight pencapaian
        $table->text('summary_notes')->nullable(); // Catatan akhir guru
        $table->text('target_notes')->nullable();  // Target ke depan
        $table->json('repertoire')->nullable();    // Array string judul lagu
        $table->timestamp('submitted_at')->nullable();
        $table->timestamps();

        // Satu laporan per enrollment per bulan per tahun
        $table->unique(['enrollment_id', 'month', 'year']);
    });
}

public function down(): void
{
    Schema::dropIfExists('progress_reports');
}
```

- [ ] **2.5 Buat migration progress_report_sections**

```bash
php artisan make:migration create_progress_report_sections_table
```

```php
public function up(): void
{
    Schema::create('progress_report_sections', function (Blueprint $table) {
        $table->id();
        $table->foreignId('progress_report_id')->constrained()->cascadeOnDelete();
        $table->foreignId('report_template_section_id')->constrained()->restrictOnDelete();
        $table->text('summary')->nullable(); // Narasi ringkasan per seksi
        $table->timestamps();

        $table->unique(['progress_report_id', 'report_template_section_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('progress_report_sections');
}
```

- [ ] **2.6 Buat migration progress_report_items**

```bash
php artisan make:migration create_progress_report_items_table
```

```php
public function up(): void
{
    Schema::create('progress_report_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('progress_report_id')->constrained()->cascadeOnDelete();
        $table->foreignId('report_template_item_id')->constrained()->restrictOnDelete();
        $table->boolean('is_checked')->default(false);
        $table->timestamps();

        $table->unique(['progress_report_id', 'report_template_item_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('progress_report_items');
}
```

- [ ] **2.7 Buat migration progress_report_session_notes**

```bash
php artisan make:migration create_progress_report_session_notes_table
```

```php
public function up(): void
{
    Schema::create('progress_report_session_notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('progress_report_id')->constrained()->cascadeOnDelete();
        $table->date('session_date');
        $table->text('notes');
        $table->unsignedTinyInteger('sort_order')->default(0);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('progress_report_session_notes');
}
```

- [ ] **2.8 Jalankan migrations**

```bash
php artisan migrate
```

Expected: 7 tabel baru terbuat tanpa error.

- [ ] **2.9 Commit**

```bash
git add database/migrations/
git commit -m "DB: Migrasi 7 tabel fitur Laporan Progres Murid"
```

---

## Task 3: Models

- [ ] **3.1 Buat ReportTemplate**

Buat file `app/Models/ReportTemplate.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplate extends Model
{
    protected $fillable = ['instrument_id', 'name', 'description', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ReportTemplateSection::class)->orderBy('sort_order');
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProgressReport::class);
    }
}
```

- [ ] **3.2 Buat ReportTemplateSection**

Buat file `app/Models/ReportTemplateSection.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplateSection extends Model
{
    protected $fillable = ['report_template_id', 'title', 'sort_order'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReportTemplateItem::class)->orderBy('sort_order');
    }
}
```

- [ ] **3.3 Buat ReportTemplateItem**

Buat file `app/Models/ReportTemplateItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportTemplateItem extends Model
{
    protected $fillable = ['report_template_section_id', 'label', 'sort_order'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ReportTemplateSection::class, 'report_template_section_id');
    }
}
```

- [ ] **3.4 Buat ProgressReport**

Buat file `app/Models/ProgressReport.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgressReport extends Model
{
    public const STATUS_DRAFT     = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';

    protected $fillable = [
        'enrollment_id', 'student_id', 'teacher_id', 'report_template_id',
        'month', 'year', 'status',
        'highlight', 'summary_notes', 'target_notes', 'repertoire',
        'submitted_at',
    ];

    protected $casts = [
        'repertoire'   => 'array',
        'submitted_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ProgressReportSection::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProgressReportItem::class);
    }

    public function sessionNotes(): HasMany
    {
        return $this->hasMany(ProgressReportSessionNote::class)->orderBy('session_date');
    }

    /** Nama bulan dalam Bahasa Indonesia. */
    public function namaBulan(): string
    {
        $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return $bulan[$this->month] . ' ' . $this->year;
    }
}
```

- [ ] **3.5 Buat ProgressReportSection**

Buat file `app/Models/ProgressReportSection.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressReportSection extends Model
{
    protected $fillable = ['progress_report_id', 'report_template_section_id', 'summary'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class, 'progress_report_id');
    }

    public function templateSection(): BelongsTo
    {
        return $this->belongsTo(ReportTemplateSection::class, 'report_template_section_id');
    }
}
```

- [ ] **3.6 Buat ProgressReportItem**

Buat file `app/Models/ProgressReportItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressReportItem extends Model
{
    protected $fillable = ['progress_report_id', 'report_template_item_id', 'is_checked'];

    protected $casts = ['is_checked' => 'boolean'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class, 'progress_report_id');
    }

    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(ReportTemplateItem::class, 'report_template_item_id');
    }
}
```

- [ ] **3.7 Buat ProgressReportSessionNote**

Buat file `app/Models/ProgressReportSessionNote.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressReportSessionNote extends Model
{
    protected $fillable = ['progress_report_id', 'session_date', 'notes', 'sort_order'];

    protected $casts = ['session_date' => 'date'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class, 'progress_report_id');
    }
}
```

- [ ] **3.8 Tambah relasi ke Instrument dan Enrollment**

Edit `app/Models/Instrument.php` — tambah setelah method `teachers()`:

```php
public function reportTemplates(): HasMany
{
    return $this->hasMany(ReportTemplate::class)->orderBy('sort_order');
}
```

Edit `app/Models/Enrollment.php` — tambah setelah relasi yang sudah ada:

```php
public function progressReports(): HasMany
{
    return $this->hasMany(ProgressReport::class)->orderByDesc('year')->orderByDesc('month');
}
```

- [ ] **3.9 Commit**

```bash
git add app/Models/
git commit -m "Feat: Models ReportTemplate, ProgressReport dan relasi-relasinya"
```

---

## Task 4: ReportTemplate CRUD (Owner — Master Data)

- [ ] **4.1 Tulis failing test**

Buat file `tests/Feature/ReportTemplateTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateSection;
use App\Models\ReportTemplateItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $admin;
    private Instrument $instrument;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        $this->owner      = User::factory()->create(['email_verified_at' => now()]);
        $this->owner->assignRole('Owner');
        $this->admin      = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('Admin');
        $this->instrument = Instrument::create(['code' => 'VOC', 'name' => 'Vocal', 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_owner_bisa_lihat_daftar_template(): void
    {
        $this->actingAs($this->owner)->get('/report-templates')->assertOk();
    }

    public function test_admin_tidak_bisa_buat_template(): void
    {
        $this->actingAs($this->admin)
            ->post('/report-templates', ['name' => 'X', 'instrument_id' => $this->instrument->id])
            ->assertForbidden();
    }

    public function test_owner_bisa_buat_template(): void
    {
        $this->actingAs($this->owner)
            ->post('/report-templates', [
                'instrument_id' => $this->instrument->id,
                'name'          => 'Template Vocal',
                'description'   => 'Template untuk siswa vokal',
                'sort_order'    => 1,
            ])
            ->assertRedirect('/report-templates');

        $this->assertDatabaseHas('report_templates', ['name' => 'Template Vocal']);
    }

    public function test_owner_bisa_tambah_section(): void
    {
        $template = ReportTemplate::create([
            'instrument_id' => $this->instrument->id,
            'name'          => 'Template Vocal',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        $this->actingAs($this->owner)
            ->post("/report-templates/{$template->id}/sections", [
                'title'      => 'Kemampuan Bernyanyi',
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('report_template_sections', ['title' => 'Kemampuan Bernyanyi']);
    }

    public function test_owner_bisa_tambah_item_ke_section(): void
    {
        $template = ReportTemplate::create([
            'instrument_id' => $this->instrument->id,
            'name'          => 'Template Vocal',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);
        $section = ReportTemplateSection::create([
            'report_template_id' => $template->id,
            'title'              => 'Bernyanyi',
            'sort_order'         => 1,
        ]);

        $this->actingAs($this->owner)
            ->post("/report-templates/{$template->id}/sections/{$section->id}/items", [
                'label'      => 'Teknik Pernafasan Diafragma',
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('report_template_items', ['label' => 'Teknik Pernafasan Diafragma']);
    }
}
```

- [ ] **4.2 Jalankan test — pastikan FAIL**

```bash
php artisan test tests/Feature/ReportTemplateTest.php
```

Expected: FAIL — route not defined / class not found.

- [ ] **4.3 Buat ReportTemplateController**

Buat file `app/Http/Controllers/ReportTemplateController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateSection;
use App\Models\ReportTemplateItem;
use Illuminate\Http\Request;

/**
 * CRUD template laporan progres per instrumen (Owner only).
 */
class ReportTemplateController extends Controller
{
    public function index()
    {
        $templates = ReportTemplate::with('instrument')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('report-templates.index', compact('templates'));
    }

    public function create()
    {
        $instruments = Instrument::where('is_active', true)->orderBy('name')->get();
        return view('report-templates.create', compact('instruments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'instrument_id' => 'required|exists:instruments,id',
            'name'          => 'required|string|max:100',
            'description'   => 'nullable|string|max:500',
            'sort_order'    => 'required|integer|min:0|max:999',
        ], [
            'instrument_id.required' => 'Instrumen wajib dipilih.',
            'instrument_id.exists'   => 'Instrumen tidak valid.',
            'name.required'          => 'Nama template wajib diisi.',
            'sort_order.required'    => 'Urutan tampil wajib diisi.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        ReportTemplate::create($validated);

        return redirect()->route('report-templates.index')
            ->with('success', "Template '{$validated['name']}' berhasil dibuat.");
    }

    public function show(ReportTemplate $reportTemplate)
    {
        $reportTemplate->load(['instrument', 'sections.items']);
        return view('report-templates.show', compact('reportTemplate'));
    }

    public function edit(ReportTemplate $reportTemplate)
    {
        $instruments = Instrument::where('is_active', true)->orderBy('name')->get();
        return view('report-templates.edit', compact('reportTemplate', 'instruments'));
    }

    public function update(Request $request, ReportTemplate $reportTemplate)
    {
        $validated = $request->validate([
            'instrument_id' => 'required|exists:instruments,id',
            'name'          => 'required|string|max:100',
            'description'   => 'nullable|string|max:500',
            'sort_order'    => 'required|integer|min:0|max:999',
        ], [
            'instrument_id.required' => 'Instrumen wajib dipilih.',
            'name.required'          => 'Nama template wajib diisi.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', false);

        $reportTemplate->update($validated);

        return redirect()->route('report-templates.show', $reportTemplate)
            ->with('success', 'Template berhasil diperbarui.');
    }

    public function destroy(ReportTemplate $reportTemplate)
    {
        if ($reportTemplate->progressReports()->exists()) {
            return back()->with('error',
                "Template '{$reportTemplate->name}' tidak bisa dihapus karena sudah dipakai di laporan. Nonaktifkan saja.");
        }

        $name = $reportTemplate->name;
        $reportTemplate->delete();

        return redirect()->route('report-templates.index')
            ->with('success', "Template '{$name}' berhasil dihapus.");
    }

    // ===== Sections =====

    public function storeSection(Request $request, ReportTemplate $reportTemplate)
    {
        $validated = $request->validate([
            'title'      => 'required|string|max:100',
            'sort_order' => 'required|integer|min:0|max:99',
        ], [
            'title.required'      => 'Judul seksi wajib diisi.',
            'sort_order.required' => 'Urutan wajib diisi.',
        ]);

        $validated['report_template_id'] = $reportTemplate->id;
        ReportTemplateSection::create($validated);

        return back()->with('success', "Seksi '{$validated['title']}' berhasil ditambahkan.");
    }

    public function destroySection(ReportTemplate $reportTemplate, ReportTemplateSection $section)
    {
        abort_if($section->report_template_id !== $reportTemplate->id, 404);
        $title = $section->title;
        $section->delete(); // cascade ke items

        return back()->with('success', "Seksi '{$title}' berhasil dihapus.");
    }

    // ===== Items =====

    public function storeItem(Request $request, ReportTemplate $reportTemplate, ReportTemplateSection $section)
    {
        abort_if($section->report_template_id !== $reportTemplate->id, 404);

        $validated = $request->validate([
            'label'      => 'required|string|max:200',
            'sort_order' => 'required|integer|min:0|max:99',
        ], [
            'label.required'      => 'Label indikator wajib diisi.',
            'sort_order.required' => 'Urutan wajib diisi.',
        ]);

        $validated['report_template_section_id'] = $section->id;
        ReportTemplateItem::create($validated);

        return back()->with('success', "Indikator berhasil ditambahkan.");
    }

    public function destroyItem(ReportTemplate $reportTemplate, ReportTemplateSection $section, ReportTemplateItem $item)
    {
        abort_if($section->report_template_id !== $reportTemplate->id, 404);
        abort_if($item->report_template_section_id !== $section->id, 404);

        $item->delete();
        return back()->with('success', 'Indikator berhasil dihapus.');
    }
}
```

- [ ] **4.4 Tambah routes**

Edit `routes/web.php` — di dalam grup `role:Owner`, tambahkan:

```php
// Laporan Progres — Template Master Data (Owner only)
Route::resource('report-templates', ReportTemplateController::class)
    ->parameters(['report-templates' => 'reportTemplate']);

Route::post('report-templates/{reportTemplate}/sections',
    [ReportTemplateController::class, 'storeSection'])
    ->name('report-templates.sections.store');

Route::delete('report-templates/{reportTemplate}/sections/{section}',
    [ReportTemplateController::class, 'destroySection'])
    ->name('report-templates.sections.destroy');

Route::post('report-templates/{reportTemplate}/sections/{section}/items',
    [ReportTemplateController::class, 'storeItem'])
    ->name('report-templates.items.store');

Route::delete('report-templates/{reportTemplate}/sections/{section}/items/{item}',
    [ReportTemplateController::class, 'destroyItem'])
    ->name('report-templates.items.destroy');
```

Tambahkan import di atas file routes/web.php (atau pastikan sudah ada):

```php
use App\Http\Controllers\ReportTemplateController;
```

- [ ] **4.5 Buat views — index**

Buat direktori: `resources/views/report-templates/`

Buat file `resources/views/report-templates/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Template Laporan Progres</h2>
                <div class="text-xs text-mk-muted mt-0.5">Template checklist per instrumen untuk laporan bulanan guru</div>
            </div>
            @role('Owner')
            <a href="{{ route('report-templates.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary">
                + Tambah Template
            </a>
            @endrole
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        @if(session('success'))
            <div class="mb-5 p-3 rounded-lg text-sm"
                 style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-5 p-3 rounded-lg text-sm"
                 style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @if($templates->isEmpty())
                <div class="p-8 text-center text-gray-400">
                    Belum ada template. Klik "+ Tambah Template" untuk mulai.
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="border-b text-left text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-4 py-3">Urut</th>
                            <th class="px-4 py-3">Instrumen</th>
                            <th class="px-4 py-3">Nama Template</th>
                            <th class="px-4 py-3 text-center">Seksi</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            @role('Owner')
                                <th class="px-4 py-3 text-right">Aksi</th>
                            @endrole
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($templates as $t)
                            <tr class="hover:bg-gray-50 {{ $t->is_active ? '' : 'opacity-50' }}">
                                <td class="px-4 py-2 text-gray-400 text-xs">{{ $t->sort_order }}</td>
                                <td class="px-4 py-2 font-medium text-gray-700">{{ $t->instrument->name }}</td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('report-templates.show', $t) }}"
                                       class="text-indigo-600 hover:underline font-medium">{{ $t->name }}</a>
                                    @if($t->description)
                                        <div class="text-xs text-gray-400 mt-0.5">{{ $t->description }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center text-gray-500">{{ $t->sections->count() }}</td>
                                <td class="px-4 py-2 text-center">
                                    @if($t->is_active)
                                        <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Aktif</span>
                                    @else
                                        <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-500">Nonaktif</span>
                                    @endif
                                </td>
                                @role('Owner')
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        <a href="{{ route('report-templates.show', $t) }}"
                                           class="text-gray-500 hover:underline text-xs mr-2">Detail</a>
                                        <a href="{{ route('report-templates.edit', $t) }}"
                                           class="text-indigo-600 hover:underline text-xs mr-2">Edit</a>
                                        <form action="{{ route('report-templates.destroy', $t) }}"
                                              method="POST" class="inline"
                                              onsubmit="return confirm('Hapus template {{ $t->name }}?')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-500 hover:underline text-xs">Hapus</button>
                                        </form>
                                    </td>
                                @endrole
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
```

- [ ] **4.6 Buat view create & edit**

Buat file `resources/views/report-templates/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text">Tambah Template Laporan</h2>
    </x-slot>
    <div class="py-6 px-4 lg:px-8 max-w-xl">
        <div class="bg-white shadow-sm rounded-lg p-6">
            <form method="POST" action="{{ route('report-templates.store') }}">
                @csrf
                @include('report-templates._form', ['template' => null])
                <div class="flex gap-3 mt-6">
                    <button type="submit"
                            class="px-5 py-2 rounded-lg text-sm font-bold btn-mk-primary">Simpan</button>
                    <a href="{{ route('report-templates.index') }}"
                       class="px-5 py-2 rounded-lg text-sm border border-gray-200 text-gray-600 hover:bg-gray-50">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
```

Buat file `resources/views/report-templates/edit.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text">Edit Template: {{ $reportTemplate->name }}</h2>
    </x-slot>
    <div class="py-6 px-4 lg:px-8 max-w-xl">
        <div class="bg-white shadow-sm rounded-lg p-6">
            <form method="POST" action="{{ route('report-templates.update', $reportTemplate) }}">
                @csrf @method('PUT')
                @include('report-templates._form', ['template' => $reportTemplate])
                <div class="flex gap-3 mt-6">
                    <button type="submit"
                            class="px-5 py-2 rounded-lg text-sm font-bold btn-mk-primary">Simpan</button>
                    <a href="{{ route('report-templates.show', $reportTemplate) }}"
                       class="px-5 py-2 rounded-lg text-sm border border-gray-200 text-gray-600 hover:bg-gray-50">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
```

Buat file `resources/views/report-templates/_form.blade.php`:

```blade
{{-- Instrumen --}}
<div class="mb-4">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
        Instrumen <span class="text-red-500">*</span>
    </label>
    <select name="instrument_id"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900" required>
        <option value="">-- Pilih Instrumen --</option>
        @foreach($instruments as $inst)
            <option value="{{ $inst->id }}"
                    {{ old('instrument_id', $template?->instrument_id) == $inst->id ? 'selected' : '' }}>
                {{ $inst->name }}
            </option>
        @endforeach
    </select>
    @error('instrument_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

{{-- Nama --}}
<div class="mb-4">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
        Nama Template <span class="text-red-500">*</span>
    </label>
    <input type="text" name="name"
           value="{{ old('name', $template?->name) }}"
           placeholder="cth: Template Vocal"
           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900" required>
    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

{{-- Deskripsi --}}
<div class="mb-4">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Deskripsi</label>
    <textarea name="description" rows="2"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900"
              >{{ old('description', $template?->description) }}</textarea>
</div>

{{-- Urutan + Status --}}
<div class="flex gap-4 mb-4">
    <div class="flex-1">
        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Urutan</label>
        <input type="number" name="sort_order"
               value="{{ old('sort_order', $template?->sort_order ?? 0) }}"
               min="0" max="999"
               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
    </div>
    <div class="flex items-end mb-0 pb-0">
        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer mb-2">
            <input type="checkbox" name="is_active" value="1"
                   {{ old('is_active', $template?->is_active ?? true) ? 'checked' : '' }}>
            Aktif
        </label>
    </div>
</div>
```

- [ ] **4.7 Buat view show (sections + items)**

Buat file `resources/views/report-templates/show.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">{{ $reportTemplate->name }}</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    {{ $reportTemplate->instrument->name }} ·
                    {{ $reportTemplate->is_active ? 'Aktif' : 'Nonaktif' }}
                </div>
            </div>
            @role('Owner')
                <a href="{{ route('report-templates.edit', $reportTemplate) }}"
                   class="px-4 py-2 rounded-lg text-sm font-bold border border-gray-200 text-gray-600 hover:bg-gray-50">
                    Edit Info
                </a>
            @endrole
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg text-sm"
                 style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
                {{ session('success') }}
            </div>
        @endif

        {{-- Daftar Seksi --}}
        @foreach($reportTemplate->sections as $section)
            <div class="bg-white shadow-sm rounded-lg mb-4 overflow-hidden">
                <div class="flex justify-between items-center px-5 py-3 border-b border-gray-100 bg-gray-50">
                    <div class="font-semibold text-gray-700 text-sm">
                        {{ $section->sort_order }}. {{ $section->title }}
                    </div>
                    @role('Owner')
                        <form action="{{ route('report-templates.sections.destroy', [$reportTemplate, $section]) }}"
                              method="POST"
                              onsubmit="return confirm('Hapus seksi dan semua indikatornya?')">
                            @csrf @method('DELETE')
                            <button class="text-red-400 hover:text-red-600 text-xs">Hapus Seksi</button>
                        </form>
                    @endrole
                </div>

                {{-- Items --}}
                <div class="divide-y divide-gray-50">
                    @forelse($section->items as $item)
                        <div class="flex justify-between items-center px-5 py-2 text-sm text-gray-700">
                            <span>{{ $item->sort_order }}. {{ $item->label }}</span>
                            @role('Owner')
                                <form action="{{ route('report-templates.items.destroy', [$reportTemplate, $section, $item]) }}"
                                      method="POST"
                                      onsubmit="return confirm('Hapus indikator ini?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-400 hover:text-red-600 text-xs">×</button>
                                </form>
                            @endrole
                        </div>
                    @empty
                        <div class="px-5 py-3 text-xs text-gray-400">Belum ada indikator.</div>
                    @endforelse
                </div>

                {{-- Form tambah item --}}
                @role('Owner')
                    <div class="border-t border-gray-100 px-5 py-3 bg-gray-50">
                        <form method="POST"
                              action="{{ route('report-templates.items.store', [$reportTemplate, $section]) }}"
                              class="flex gap-2">
                            @csrf
                            <input type="text" name="label" placeholder="Label indikator baru..."
                                   class="flex-1 border border-gray-200 rounded px-3 py-1.5 text-sm text-gray-900" required>
                            <input type="number" name="sort_order" value="{{ $section->items->count() + 1 }}"
                                   class="w-16 border border-gray-200 rounded px-2 py-1.5 text-sm text-gray-900 text-center">
                            <button type="submit"
                                    class="px-3 py-1.5 rounded text-sm font-semibold btn-mk-primary">+ Item</button>
                        </form>
                    </div>
                @endrole
            </div>
        @endforeach

        {{-- Form tambah seksi baru --}}
        @role('Owner')
            <div class="bg-white shadow-sm rounded-lg p-5">
                <div class="text-sm font-semibold text-gray-700 mb-3">+ Tambah Seksi Baru</div>
                <form method="POST"
                      action="{{ route('report-templates.sections.store', $reportTemplate) }}"
                      class="flex gap-2">
                    @csrf
                    <input type="text" name="title" placeholder="Judul seksi, cth: Kemampuan Bernyanyi"
                           class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900" required>
                    <input type="number" name="sort_order"
                           value="{{ $reportTemplate->sections->count() + 1 }}"
                           class="w-16 border border-gray-200 rounded-lg px-2 py-2 text-sm text-gray-900 text-center">
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary">Tambah</button>
                </form>
            </div>
        @endrole

        <div class="mt-4">
            <a href="{{ route('report-templates.index') }}"
               class="text-sm text-gray-500 hover:underline">← Kembali ke daftar template</a>
        </div>
    </div>
</x-app-layout>
```

- [ ] **4.8 Jalankan test — harus PASS**

```bash
php artisan test tests/Feature/ReportTemplateTest.php
```

Expected: 4 tests, 4 passed.

- [ ] **4.9 Commit**

```bash
git add app/Http/Controllers/ReportTemplateController.php \
        resources/views/report-templates/ \
        routes/web.php
git commit -m "Feat: ReportTemplate CRUD — Owner kelola template laporan per instrumen"
```

---

## Task 5: Guru — Buat & Isi Laporan Progres

- [ ] **5.1 Tulis failing test**

Buat file `tests/Feature/ProgressReportGuruTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\ProgressReport;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateSection;
use App\Models\ReportTemplateItem;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgressReportGuruTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;
    private Teacher $teacher;
    private Enrollment $enrollment;
    private ReportTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $this->guruUser = User::factory()->create(['email_verified_at' => now()]);
        $this->guruUser->assignRole('Guru');
        $this->teacher = Teacher::factory()->create(['user_id' => $this->guruUser->id]);

        $instrument = Instrument::create(['code' => 'VOC', 'name' => 'Vocal', 'is_active' => true, 'sort_order' => 1]);
        $package    = Package::create([
            'code' => 'VOC-HOB-30', 'instrument_id' => $instrument->id,
            'class_type' => 'HOBBY', 'duration_min' => 30,
            'price_per_month' => 390000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $student = Student::factory()->create(['status' => 'Aktif']);
        $this->enrollment = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $package->id,
            'teacher_id' => $this->teacher->id, 'status' => 'ACTIVE',
            'effective_date' => now()->toDateString(), 'is_primary' => true,
        ]);

        $this->template = ReportTemplate::create([
            'instrument_id' => $instrument->id,
            'name'          => 'Template Vocal',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);
        $section = ReportTemplateSection::create([
            'report_template_id' => $this->template->id,
            'title' => 'Kemampuan Bernyanyi', 'sort_order' => 1,
        ]);
        ReportTemplateItem::create([
            'report_template_section_id' => $section->id,
            'label' => 'Teknik Pernafasan', 'sort_order' => 1,
        ]);
    }

    public function test_guru_bisa_lihat_halaman_laporan(): void
    {
        $this->actingAs($this->guruUser)->get('/guru/laporan')->assertOk();
    }

    public function test_guru_bisa_buat_laporan_baru(): void
    {
        $this->actingAs($this->guruUser)
            ->post('/guru/laporan', [
                'enrollment_id'      => $this->enrollment->id,
                'report_template_id' => $this->template->id,
                'month'              => 5,
                'year'               => 2026,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('progress_reports', [
            'enrollment_id' => $this->enrollment->id,
            'month'         => 5,
            'year'          => 2026,
            'status'        => 'DRAFT',
        ]);
    }

    public function test_guru_tidak_bisa_buat_laporan_duplikat(): void
    {
        ProgressReport::create([
            'enrollment_id'      => $this->enrollment->id,
            'student_id'         => $this->enrollment->student_id,
            'teacher_id'         => $this->teacher->id,
            'report_template_id' => $this->template->id,
            'month' => 5, 'year' => 2026, 'status' => 'DRAFT',
        ]);

        $this->actingAs($this->guruUser)
            ->post('/guru/laporan', [
                'enrollment_id'      => $this->enrollment->id,
                'report_template_id' => $this->template->id,
                'month'              => 5,
                'year'               => 2026,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_guru_bisa_submit_laporan(): void
    {
        $report = ProgressReport::create([
            'enrollment_id'      => $this->enrollment->id,
            'student_id'         => $this->enrollment->student_id,
            'teacher_id'         => $this->teacher->id,
            'report_template_id' => $this->template->id,
            'month' => 5, 'year' => 2026, 'status' => 'DRAFT',
        ]);

        $item = $this->template->sections->first()->items->first();

        $this->actingAs($this->guruUser)
            ->put("/guru/laporan/{$report->id}", [
                'highlight'      => 'Murid berkembang pesat bulan ini.',
                'summary_notes'  => 'Terus latihan teknik pernafasan.',
                'target_notes'   => 'Kuasai teknik falsetto bulan depan.',
                'repertoire'     => ['Reflection', 'Andaikan Aku Punya Sayap'],
                'section_summary' => [],
                'checked_items'  => [$item->id],
                'submit'         => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('progress_reports', [
            'id'     => $report->id,
            'status' => 'SUBMITTED',
        ]);
    }

    public function test_guru_tidak_bisa_edit_laporan_guru_lain(): void
    {
        $guruLain = User::factory()->create(['email_verified_at' => now()]);
        $guruLain->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $guruLain->id]);

        $report = ProgressReport::create([
            'enrollment_id'      => $this->enrollment->id,
            'student_id'         => $this->enrollment->student_id,
            'teacher_id'         => $this->teacher->id,
            'report_template_id' => $this->template->id,
            'month' => 5, 'year' => 2026, 'status' => 'DRAFT',
        ]);

        $this->actingAs($guruLain)->get("/guru/laporan/{$report->id}/edit")->assertForbidden();
    }
}
```

- [ ] **5.2 Jalankan test — pastikan FAIL**

```bash
php artisan test tests/Feature/ProgressReportGuruTest.php
```

Expected: FAIL — route not defined.

- [ ] **5.3 Tambah methods ke GuruController**

Edit `app/Http/Controllers/GuruController.php` — tambah use statements di atas:

```php
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\ProgressReport;
use App\Models\ProgressReportItem;
use App\Models\ProgressReportSection;
use App\Models\ProgressReportSessionNote;
use App\Models\ReportTemplate;
```

Tambah methods berikut di dalam class GuruController (sebelum tutup `}`):

```php
/**
 * Daftar laporan progres milik guru yang login.
 */
public function laporan()
{
    $teacher = auth()->user()->teacher;
    abort_if(!$teacher, 403, 'Akun ini tidak terhubung ke data guru.');

    // Semua enrollment aktif milik guru ini — untuk form buat laporan baru
    $enrollments = Enrollment::where('teacher_id', $teacher->id)
        ->whereIn('status', ['ACTIVE', 'ON_LEAVE'])
        ->with(['student', 'package.instrument'])
        ->get();

    // Laporan yang sudah ada
    $laporan = ProgressReport::where('teacher_id', $teacher->id)
        ->with(['student', 'enrollment.package'])
        ->orderByDesc('year')
        ->orderByDesc('month')
        ->get();

    // Template tersedia per instrumen
    $templates = ReportTemplate::where('is_active', true)
        ->with('instrument')
        ->orderBy('sort_order')
        ->get();

    return view('guru.laporan', compact('teacher', 'laporan', 'enrollments', 'templates'));
}

/**
 * Simpan laporan baru (DRAFT) — inisialisasi baris untuk semua items dan sections.
 */
public function laporanStore(Request $request)
{
    $teacher = auth()->user()->teacher;
    abort_if(!$teacher, 403);

    $validated = $request->validate([
        'enrollment_id'      => 'required|exists:enrollments,id',
        'report_template_id' => 'required|exists:report_templates,id',
        'month'              => 'required|integer|min:1|max:12',
        'year'               => 'required|integer|min:2024|max:2030',
    ], [
        'enrollment_id.required'      => 'Kelas wajib dipilih.',
        'report_template_id.required' => 'Template laporan wajib dipilih.',
        'month.required'              => 'Bulan wajib diisi.',
        'year.required'               => 'Tahun wajib diisi.',
    ]);

    // Pastikan enrollment ini memang milik guru yang login
    $enrollment = Enrollment::findOrFail($validated['enrollment_id']);
    abort_if($enrollment->teacher_id !== $teacher->id, 403, 'Bukan enrollment Anda.');

    // Cek duplikat
    $sudahAda = ProgressReport::where('enrollment_id', $enrollment->id)
        ->where('month', $validated['month'])
        ->where('year', $validated['year'])
        ->exists();

    if ($sudahAda) {
        return back()->with('error', 'Laporan untuk kelas dan bulan ini sudah ada.');
    }

    $template = ReportTemplate::with('sections.items')->findOrFail($validated['report_template_id']);

    $report = ProgressReport::create([
        'enrollment_id'      => $enrollment->id,
        'student_id'         => $enrollment->student_id,
        'teacher_id'         => $teacher->id,
        'report_template_id' => $template->id,
        'month'              => $validated['month'],
        'year'               => $validated['year'],
        'status'             => ProgressReport::STATUS_DRAFT,
    ]);

    // Inisialisasi baris section dan item (semua unchecked)
    foreach ($template->sections as $section) {
        ProgressReportSection::create([
            'progress_report_id'         => $report->id,
            'report_template_section_id' => $section->id,
            'summary'                    => null,
        ]);
        foreach ($section->items as $item) {
            ProgressReportItem::create([
                'progress_report_id'       => $report->id,
                'report_template_item_id'  => $item->id,
                'is_checked'               => false,
            ]);
        }
    }

    return redirect()->route('guru.laporan.edit', $report)
        ->with('success', 'Laporan baru dibuat. Silakan isi dan submit.');
}

/**
 * Form edit laporan (DRAFT saja yang bisa diedit).
 */
public function laporanEdit(ProgressReport $progressReport)
{
    $teacher = auth()->user()->teacher;
    abort_if(!$teacher, 403);
    abort_if($progressReport->teacher_id !== $teacher->id, 403, 'Bukan laporan Anda.');
    abort_if($progressReport->status === ProgressReport::STATUS_SUBMITTED, 403, 'Laporan sudah disubmit, tidak bisa diedit.');

    $progressReport->load([
        'template.sections.items',
        'sections.templateSection',
        'items.templateItem',
        'sessionNotes',
        'student',
        'enrollment.package',
    ]);

    return view('guru.laporan-form', compact('progressReport'));
}

/**
 * Update isi laporan. Jika request punya 'submit' => '1', status jadi SUBMITTED.
 */
public function laporanUpdate(Request $request, ProgressReport $progressReport)
{
    $teacher = auth()->user()->teacher;
    abort_if(!$teacher, 403);
    abort_if($progressReport->teacher_id !== $teacher->id, 403, 'Bukan laporan Anda.');
    abort_if($progressReport->status === ProgressReport::STATUS_SUBMITTED, 403, 'Laporan sudah disubmit.');

    $validated = $request->validate([
        'highlight'        => 'nullable|string|max:3000',
        'summary_notes'    => 'nullable|string|max:2000',
        'target_notes'     => 'nullable|string|max:2000',
        'repertoire'       => 'nullable|array',
        'repertoire.*'     => 'string|max:200',
        'section_summary'  => 'nullable|array',
        'section_summary.*'=> 'nullable|string|max:500',
        'checked_items'    => 'nullable|array',
        'checked_items.*'  => 'integer|exists:report_template_items,id',
        // Catatan sesi
        'session_dates'    => 'nullable|array',
        'session_dates.*'  => 'nullable|date',
        'session_notes_text' => 'nullable|array',
        'session_notes_text.*' => 'nullable|string|max:2000',
    ]);

    $progressReport->update([
        'highlight'     => $validated['highlight'] ?? null,
        'summary_notes' => $validated['summary_notes'] ?? null,
        'target_notes'  => $validated['target_notes'] ?? null,
        'repertoire'    => array_filter($validated['repertoire'] ?? []),
    ]);

    // Update section summaries
    if (!empty($validated['section_summary'])) {
        foreach ($validated['section_summary'] as $sectionId => $summary) {
            ProgressReportSection::where('progress_report_id', $progressReport->id)
                ->where('report_template_section_id', $sectionId)
                ->update(['summary' => $summary ?: null]);
        }
    }

    // Update checklist items
    $checkedIds = $validated['checked_items'] ?? [];
    ProgressReportItem::where('progress_report_id', $progressReport->id)
        ->update(['is_checked' => false]);
    if (!empty($checkedIds)) {
        ProgressReportItem::where('progress_report_id', $progressReport->id)
            ->whereIn('report_template_item_id', $checkedIds)
            ->update(['is_checked' => true]);
    }

    // Rebuild catatan sesi
    $progressReport->sessionNotes()->delete();
    $dates = $validated['session_dates'] ?? [];
    $notes = $validated['session_notes_text'] ?? [];
    foreach ($dates as $i => $date) {
        if (!empty($date) && !empty($notes[$i])) {
            ProgressReportSessionNote::create([
                'progress_report_id' => $progressReport->id,
                'session_date'       => $date,
                'notes'              => $notes[$i],
                'sort_order'         => $i,
            ]);
        }
    }

    // Submit jika diminta
    if ($request->input('submit') === '1') {
        $progressReport->update([
            'status'       => ProgressReport::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
        return redirect()->route('guru.laporan.index')
            ->with('success', 'Laporan berhasil disubmit.');
    }

    return back()->with('success', 'Draft laporan tersimpan.');
}
```

- [ ] **5.4 Tambah routes guru laporan**

Edit `routes/web.php` — di dalam grup `prefix('guru')`, tambahkan:

```php
Route::get('/laporan',                         [GuruController::class, 'laporan'])->name('laporan.index');
Route::post('/laporan',                        [GuruController::class, 'laporanStore'])->name('laporan.store');
Route::get('/laporan/{progressReport}/edit',   [GuruController::class, 'laporanEdit'])->name('laporan.edit');
Route::put('/laporan/{progressReport}',        [GuruController::class, 'laporanUpdate'])->name('laporan.update');
```

Tambah import di atas routes/web.php:

```php
use App\Models\ProgressReport;
```

- [ ] **5.5 Buat view laporan index (guru)**

Buat file `resources/views/guru/laporan.blade.php`:

```blade
<x-guru-layout title="Laporan Progres">

<div class="px-4 pt-5 pb-2">
    <h1 class="text-lg font-semibold text-mk-text">Laporan Progres</h1>
    <p class="text-sm text-mk-muted">Laporan perkembangan murid bulanan</p>
</div>

{{-- Flash --}}
@if(session('success'))
    <div class="mx-4 mb-3 p-3 rounded-xl text-sm"
         style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="mx-4 mb-3 p-3 rounded-xl text-sm"
         style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
        {{ session('error') }}
    </div>
@endif

{{-- Form buat laporan baru --}}
<div class="mx-4 mb-4">
    <details class="bg-mk-card border border-mk-border rounded-xl">
        <summary class="px-4 py-3 font-semibold text-sm text-mk-text cursor-pointer">
            + Buat Laporan Baru
        </summary>
        <div class="px-4 pb-4 pt-2 border-t border-mk-border">
            <form method="POST" action="{{ route('guru.laporan.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="block text-xs text-mk-muted mb-1">Kelas / Murid</label>
                    <select name="enrollment_id" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm" required>
                        <option value="">-- Pilih murid --</option>
                        @foreach($enrollments as $e)
                            <option value="{{ $e->id }}">
                                {{ $e->student->full_name }} — {{ $e->package->code }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-xs text-mk-muted mb-1">Template Laporan</label>
                    <select name="report_template_id" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm" required>
                        <option value="">-- Pilih template --</option>
                        @foreach($templates as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->instrument->name }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2 mb-3">
                    <div class="flex-1">
                        <label class="block text-xs text-mk-muted mb-1">Bulan</label>
                        <select name="month" class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm" required>
                            @foreach(range(1,12) as $m)
                                <option value="{{ $m }}" {{ $m == now()->month ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create()->month($m)->locale('id')->isoFormat('MMMM') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-24">
                        <label class="block text-xs text-mk-muted mb-1">Tahun</label>
                        <input type="number" name="year" value="{{ now()->year }}"
                               min="2024" max="2030"
                               class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <button type="submit"
                        class="w-full py-2.5 rounded-xl font-semibold text-sm btn-mk-primary">
                    Buat Laporan
                </button>
            </form>
        </div>
    </details>
</div>

{{-- Daftar laporan --}}
<div class="px-4 space-y-3 pb-6">
    <h2 class="text-xs font-semibold tracking-widest text-mk-muted uppercase">Laporan Saya</h2>

    @forelse($laporan as $r)
        <div class="bg-mk-card border border-mk-border rounded-xl px-4 py-3">
            <div class="flex justify-between items-start">
                <div>
                    <div class="font-semibold text-mk-text text-sm">{{ $r->student->full_name }}</div>
                    <div class="text-xs text-mk-muted mt-0.5">
                        {{ $r->enrollment->package->code }} · {{ $r->namaBulan() }}
                    </div>
                </div>
                <span class="text-xs px-2 py-1 rounded-full font-medium
                    {{ $r->status === 'SUBMITTED'
                        ? 'bg-green-100 text-green-700'
                        : 'bg-amber-100 text-amber-700' }}">
                    {{ $r->status === 'SUBMITTED' ? 'Submitted' : 'Draft' }}
                </span>
            </div>
            @if($r->status === 'DRAFT')
                <a href="{{ route('guru.laporan.edit', $r) }}"
                   class="mt-3 block text-center py-2 rounded-lg text-sm font-semibold border border-mk-accent/40 text-mk-accent hover:bg-mk-accent/10">
                    Lanjut Isi →
                </a>
            @endif
        </div>
    @empty
        <div class="text-center py-8 text-mk-muted text-sm">Belum ada laporan.</div>
    @endforelse
</div>

</x-guru-layout>
```

- [ ] **5.6 Buat view form isi laporan (guru)**

Buat file `resources/views/guru/laporan-form.blade.php`:

```blade
<x-guru-layout title="Isi Laporan">

<div class="px-4 pt-5 pb-2">
    <h1 class="text-base font-semibold text-mk-text">
        {{ $progressReport->student->full_name }}
    </h1>
    <p class="text-sm text-mk-muted">
        {{ $progressReport->enrollment->package->code }} · {{ $progressReport->namaBulan() }}
    </p>
</div>

@if(session('success'))
    <div class="mx-4 mb-3 p-3 rounded-xl text-sm"
         style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
        {{ session('success') }}
    </div>
@endif

<form method="POST" action="{{ route('guru.laporan.update', $progressReport) }}" id="form-laporan">
    @csrf @method('PUT')

    {{-- ===== CHECKLIST PER SEKSI ===== --}}
    @foreach($progressReport->template->sections as $section)
        @php
            $sectionRecord = $progressReport->sections->firstWhere('report_template_section_id', $section->id);
        @endphp
        <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-mk-border bg-mk-sidebar/30">
                <div class="font-semibold text-sm text-mk-text">{{ $section->title }}</div>
            </div>
            {{-- Narasi ringkasan seksi --}}
            <div class="px-4 pt-3">
                <textarea name="section_summary[{{ $section->id }}]"
                          rows="2"
                          placeholder="Ringkasan singkat untuk seksi ini (opsional)..."
                          class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">{{ old("section_summary.{$section->id}", $sectionRecord?->summary) }}</textarea>
            </div>
            {{-- Checklist items --}}
            <div class="px-4 pb-3 pt-2 space-y-2">
                @foreach($section->items as $item)
                    @php
                        $itemRecord = $progressReport->items->firstWhere('report_template_item_id', $item->id);
                    @endphp
                    <label class="flex items-center gap-3 text-sm text-mk-text cursor-pointer">
                        <input type="checkbox"
                               name="checked_items[]"
                               value="{{ $item->id }}"
                               class="w-4 h-4 rounded"
                               {{ $itemRecord?->is_checked ? 'checked' : '' }}>
                        <span>{{ $item->label }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach

    {{-- ===== REPERTOAR ===== --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4"
         x-data="{
            lagu: {{ json_encode(old('repertoire', $progressReport->repertoire ?? [])) }},
            tambah() { this.lagu.push(''); },
            hapus(i) { this.lagu.splice(i, 1); }
         }">
        <div class="font-semibold text-sm text-mk-text mb-2">Repertoar (Lagu yang Dipelajari)</div>
        <template x-for="(l, i) in lagu" :key="i">
            <div class="flex gap-2 mb-2">
                <input type="text" :name="'repertoire[' + i + ']'" x-model="lagu[i]"
                       placeholder="Judul lagu..."
                       class="flex-1 bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
                <button type="button" @click="hapus(i)"
                        class="text-red-400 hover:text-red-600 text-lg leading-none px-1">×</button>
            </div>
        </template>
        <button type="button" @click="tambah()"
                class="text-mk-accent text-sm hover:underline">+ Tambah lagu</button>
    </div>

    {{-- ===== HIGHLIGHT PENCAPAIAN ===== --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-2">Highlight Pencapaian</div>
        <textarea name="highlight" rows="4"
                  placeholder="Ceritakan perkembangan murid yang menonjol bulan ini..."
                  class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900"
                  >{{ old('highlight', $progressReport->highlight) }}</textarea>
    </div>

    {{-- ===== CATATAN PER SESI ===== --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4"
         x-data="{
            sesi: {{ json_encode(
                $progressReport->sessionNotes->map(fn($n) => ['date' => $n->session_date->format('Y-m-d'), 'notes' => $n->notes])->values()->toArray()
                ?: []
            ) }},
            tambah() { this.sesi.push({ date: '', notes: '' }); },
            hapus(i) { this.sesi.splice(i, 1); }
         }">
        <div class="font-semibold text-sm text-mk-text mb-2">Catatan Per Sesi</div>
        <template x-for="(s, i) in sesi" :key="i">
            <div class="mb-3 border border-gray-100 rounded-lg p-3 bg-white">
                <div class="flex gap-2 mb-2">
                    <input type="date" :name="'session_dates[' + i + ']'" x-model="s.date"
                           class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm text-gray-900">
                    <button type="button" @click="hapus(i)"
                            class="ml-auto text-red-400 hover:text-red-600 text-lg leading-none px-1">×</button>
                </div>
                <textarea :name="'session_notes_text[' + i + ']'" x-model="s.notes"
                          rows="3" placeholder="Catatan sesi ini..."
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900"></textarea>
            </div>
        </template>
        <button type="button" @click="tambah()"
                class="text-mk-accent text-sm hover:underline">+ Tambah catatan sesi</button>
    </div>

    {{-- ===== CATATAN AKHIR & TARGET ===== --}}
    <div class="mx-4 mb-4 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-2">Catatan Akhir (Pesan ke Murid/Orangtua)</div>
        <textarea name="summary_notes" rows="3"
                  placeholder="Saran dan pesan untuk latihan ke depan..."
                  class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900"
                  >{{ old('summary_notes', $progressReport->summary_notes) }}</textarea>
    </div>

    <div class="mx-4 mb-6 bg-mk-card border border-mk-border rounded-xl p-4">
        <div class="font-semibold text-sm text-mk-text mb-2">Target Bulan Depan</div>
        <textarea name="target_notes" rows="3"
                  placeholder="Target yang ingin dicapai murid bulan berikutnya..."
                  class="w-full bg-white border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900"
                  >{{ old('target_notes', $progressReport->target_notes) }}</textarea>
    </div>

    {{-- ===== TOMBOL AKSI ===== --}}
    <div class="mx-4 mb-8 flex gap-3">
        <button type="submit"
                class="flex-1 py-3 rounded-xl font-semibold text-sm border border-mk-accent/40 text-mk-accent hover:bg-mk-accent/10">
            Simpan Draft
        </button>
        <button type="submit" name="submit" value="1"
                onclick="return confirm('Submit laporan? Setelah disubmit, laporan tidak bisa diedit.')"
                class="flex-1 py-3 rounded-xl font-semibold text-sm btn-mk-primary">
            Submit Laporan
        </button>
    </div>
</form>

</x-guru-layout>
```

- [ ] **5.7 Jalankan test — harus PASS**

```bash
php artisan test tests/Feature/ProgressReportGuruTest.php
```

Expected: 4 tests, 4 passed.

- [ ] **5.8 Commit**

```bash
git add app/Http/Controllers/GuruController.php \
        resources/views/guru/laporan.blade.php \
        resources/views/guru/laporan-form.blade.php \
        routes/web.php
git commit -m "Feat: Guru bisa buat, isi, dan submit laporan progres bulanan"
```

---

## Task 6: Admin — View Laporan & PDF Download

- [ ] **6.1 Tulis failing test**

Buat file `tests/Feature/ProgressReportAdminTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\ProgressReport;
use App\Models\ReportTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgressReportAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $admin;
    private ProgressReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Owner', 'Admin', 'Auditor', 'Guru'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->owner->assignRole('Owner');
        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('Admin');

        $instrument  = Instrument::create(['code' => 'VOC', 'name' => 'Vocal', 'is_active' => true, 'sort_order' => 1]);
        $package     = Package::create([
            'code' => 'VOC-HOB-30', 'instrument_id' => $instrument->id,
            'class_type' => 'HOBBY', 'duration_min' => 30,
            'price_per_month' => 390000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $teacher     = Teacher::factory()->create();
        $student     = Student::factory()->create(['status' => 'Aktif']);
        $enrollment  = Enrollment::create([
            'student_id' => $student->id, 'package_id' => $package->id,
            'teacher_id' => $teacher->id, 'status' => 'ACTIVE',
            'effective_date' => now()->toDateString(), 'is_primary' => true,
        ]);
        $template    = ReportTemplate::create([
            'instrument_id' => $instrument->id, 'name' => 'Template Vocal',
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->report = ProgressReport::create([
            'enrollment_id'      => $enrollment->id,
            'student_id'         => $student->id,
            'teacher_id'         => $teacher->id,
            'report_template_id' => $template->id,
            'month' => 5, 'year' => 2026, 'status' => 'SUBMITTED',
            'submitted_at'       => now(),
        ]);
    }

    public function test_admin_bisa_lihat_daftar_laporan(): void
    {
        $this->actingAs($this->admin)->get('/progress-reports')->assertOk();
    }

    public function test_owner_bisa_lihat_daftar_laporan(): void
    {
        $this->actingAs($this->owner)->get('/progress-reports')->assertOk();
    }

    public function test_guru_tidak_bisa_akses_progress_reports_admin(): void
    {
        $guruUser = User::factory()->create(['email_verified_at' => now()]);
        $guruUser->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $guruUser->id]);

        $this->actingAs($guruUser)->get('/progress-reports')->assertForbidden();
    }

    public function test_admin_bisa_download_pdf(): void
    {
        $this->actingAs($this->admin)
            ->get("/progress-reports/{$this->report->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
```

- [ ] **6.2 Jalankan test — pastikan FAIL**

```bash
php artisan test tests/Feature/ProgressReportAdminTest.php
```

Expected: FAIL — route not defined.

- [ ] **6.3 Buat ProgressReportController**

Buat file `app/Http/Controllers/ProgressReportController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\ProgressReport;
use App\Models\Teacher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * ProgressReportController — Admin/Owner/Auditor view laporan yang disubmit guru.
 */
class ProgressReportController extends Controller
{
    public function index(Request $request)
    {
        $query = ProgressReport::with(['student', 'teacher', 'enrollment.package'])
            ->orderByDesc('year')
            ->orderByDesc('month');

        // Filter by guru
        if ($teacherId = $request->get('teacher_id')) {
            $query->where('teacher_id', $teacherId);
        }

        // Filter by status
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Filter by bulan/tahun
        if ($month = $request->get('month')) {
            $query->where('month', $month);
        }
        if ($year = $request->get('year')) {
            $query->where('year', $year);
        }

        $laporan  = $query->paginate(30)->withQueryString();
        $teachers = Teacher::where('is_active', true)->orderBy('name')->get();

        return view('progress-reports.index', compact('laporan', 'teachers'));
    }

    public function show(ProgressReport $progressReport)
    {
        $progressReport->load([
            'student',
            'teacher',
            'enrollment.package.instrument',
            'template.sections.items',
            'sections.templateSection',
            'items.templateItem',
            'sessionNotes',
        ]);

        return view('progress-reports.show', compact('progressReport'));
    }

    public function pdf(ProgressReport $progressReport)
    {
        $progressReport->load([
            'student',
            'teacher',
            'enrollment.package.instrument',
            'template.sections.items',
            'sections.templateSection',
            'items.templateItem',
            'sessionNotes',
        ]);

        $pdf = Pdf::loadView('progress-reports.pdf', compact('progressReport'))
            ->setPaper('a4', 'portrait');

        $filename = 'Laporan-' .
            str_replace(' ', '-', $progressReport->student->full_name) . '-' .
            $progressReport->namaBulan() . '.pdf';

        return $pdf->download($filename);
    }
}
```

- [ ] **6.4 Tambah routes admin progress-reports**

Edit `routes/web.php` — di dalam grup `role:Owner|Admin|Auditor`, tambahkan:

```php
// Laporan Progres — Admin view
Route::get('progress-reports',               [ProgressReportController::class, 'index'])->name('progress-reports.index');
Route::get('progress-reports/{progressReport}', [ProgressReportController::class, 'show'])->name('progress-reports.show');
Route::get('progress-reports/{progressReport}/pdf', [ProgressReportController::class, 'pdf'])->name('progress-reports.pdf');
```

Tambah import:

```php
use App\Http\Controllers\ProgressReportController;
```

- [ ] **6.5 Buat view index (admin)**

Buat file `resources/views/progress-reports/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text">Laporan Progres Murid</h2>
        <div class="text-xs text-mk-muted mt-0.5">Laporan bulanan yang disubmit guru</div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg text-sm"
                 style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
                {{ session('success') }}
            </div>
        @endif

        {{-- Filter --}}
        <form method="GET" class="mb-5 flex flex-wrap gap-3 items-center">
            <select name="teacher_id" class="bg-white border border-gray-200 text-gray-700 text-sm rounded-lg px-3 py-2">
                <option value="">Semua Guru</option>
                @foreach($teachers as $t)
                    <option value="{{ $t->id }}" {{ request('teacher_id') == $t->id ? 'selected' : '' }}>
                        {{ $t->name }}
                    </option>
                @endforeach
            </select>

            <select name="status" class="bg-white border border-gray-200 text-gray-700 text-sm rounded-lg px-3 py-2">
                <option value="">Semua Status</option>
                <option value="SUBMITTED" {{ request('status') === 'SUBMITTED' ? 'selected' : '' }}>Submitted</option>
                <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>Draft</option>
            </select>

            <select name="month" class="bg-white border border-gray-200 text-gray-700 text-sm rounded-lg px-3 py-2">
                <option value="">Semua Bulan</option>
                @foreach(range(1,12) as $m)
                    <option value="{{ $m }}" {{ request('month') == $m ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create()->month($m)->locale('id')->isoFormat('MMMM') }}
                    </option>
                @endforeach
            </select>

            <input type="number" name="year" value="{{ request('year', now()->year) }}"
                   min="2024" max="2030"
                   class="bg-white border border-gray-200 text-gray-700 text-sm rounded-lg px-3 py-2 w-24">

            <button type="submit"
                    class="px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary">Filter</button>
            <a href="{{ route('progress-reports.index') }}"
               class="text-xs text-mk-muted hover:text-mk-text">× Reset</a>
        </form>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @if($laporan->isEmpty())
                <div class="p-8 text-center text-gray-400">Belum ada laporan.</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="border-b text-left text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-4 py-3">Murid</th>
                            <th class="px-4 py-3">Guru</th>
                            <th class="px-4 py-3">Kelas</th>
                            <th class="px-4 py-3">Periode</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($laporan as $r)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium text-gray-800">{{ $r->student->full_name }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $r->teacher->name }}</td>
                                <td class="px-4 py-2 text-gray-500 text-xs">{{ $r->enrollment->package->code }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $r->namaBulan() }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium
                                        {{ $r->status === 'SUBMITTED' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ $r->status === 'SUBMITTED' ? 'Submitted' : 'Draft' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <a href="{{ route('progress-reports.show', $r) }}"
                                       class="text-indigo-600 hover:underline text-xs mr-3">Detail</a>
                                    @if($r->status === 'SUBMITTED')
                                        <a href="{{ route('progress-reports.pdf', $r) }}"
                                           class="text-green-600 hover:underline text-xs">↓ PDF</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 py-3 border-t">
                    {{ $laporan->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
```

- [ ] **6.6 Buat view show (admin)**

Buat file `resources/views/progress-reports/show.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">
                    Laporan: {{ $progressReport->student->full_name }}
                </h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    {{ $progressReport->teacher->name }} ·
                    {{ $progressReport->enrollment->package->code }} ·
                    {{ $progressReport->namaBulan() }}
                </div>
            </div>
            @if($progressReport->status === 'SUBMITTED')
                <a href="{{ route('progress-reports.pdf', $progressReport) }}"
                   class="px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary">
                    ↓ Download PDF
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8 max-w-3xl">
        {{-- Checklist per seksi --}}
        @foreach($progressReport->template->sections as $section)
            @php
                $sectionRecord = $progressReport->sections->firstWhere('report_template_section_id', $section->id);
            @endphp
            <div class="bg-white shadow-sm rounded-lg mb-4 overflow-hidden">
                <div class="px-5 py-3 border-b bg-gray-50 font-semibold text-sm text-gray-700">
                    {{ $section->title }}
                </div>
                @if($sectionRecord?->summary)
                    <div class="px-5 py-2 text-sm text-gray-600 italic border-b">
                        {{ $sectionRecord->summary }}
                    </div>
                @endif
                <div class="px-5 py-3 space-y-1.5">
                    @foreach($section->items as $item)
                        @php
                            $itemRecord = $progressReport->items->firstWhere('report_template_item_id', $item->id);
                        @endphp
                        <div class="flex items-center gap-2 text-sm">
                            <span class="{{ $itemRecord?->is_checked ? 'text-green-600' : 'text-gray-300' }}">
                                {{ $itemRecord?->is_checked ? '✓' : '○' }}
                            </span>
                            <span class="{{ $itemRecord?->is_checked ? 'text-gray-700' : 'text-gray-400' }}">
                                {{ $item->label }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Repertoar --}}
        @if($progressReport->repertoire)
            <div class="bg-white shadow-sm rounded-lg mb-4 p-5">
                <div class="font-semibold text-sm text-gray-700 mb-2">Repertoar</div>
                <ul class="list-disc list-inside space-y-1 text-sm text-gray-600">
                    @foreach($progressReport->repertoire as $lagu)
                        <li>{{ $lagu }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Highlight --}}
        @if($progressReport->highlight)
            <div class="bg-white shadow-sm rounded-lg mb-4 p-5">
                <div class="font-semibold text-sm text-gray-700 mb-2">Highlight Pencapaian</div>
                <p class="text-sm text-gray-600 whitespace-pre-line">{{ $progressReport->highlight }}</p>
            </div>
        @endif

        {{-- Catatan Sesi --}}
        @if($progressReport->sessionNotes->isNotEmpty())
            <div class="bg-white shadow-sm rounded-lg mb-4 p-5">
                <div class="font-semibold text-sm text-gray-700 mb-3">Catatan Per Sesi</div>
                @foreach($progressReport->sessionNotes as $note)
                    <div class="mb-3 border-b border-gray-50 pb-3 last:border-0 last:pb-0">
                        <div class="text-xs font-semibold text-gray-500 mb-1">
                            {{ \Carbon\Carbon::parse($note->session_date)->locale('id')->isoFormat('D MMMM Y') }}
                        </div>
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $note->notes }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Catatan Akhir & Target --}}
        @if($progressReport->summary_notes || $progressReport->target_notes)
            <div class="bg-white shadow-sm rounded-lg mb-4 p-5 space-y-4">
                @if($progressReport->summary_notes)
                    <div>
                        <div class="font-semibold text-sm text-gray-700 mb-1">Catatan Akhir</div>
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $progressReport->summary_notes }}</p>
                    </div>
                @endif
                @if($progressReport->target_notes)
                    <div>
                        <div class="font-semibold text-sm text-gray-700 mb-1">Target Bulan Depan</div>
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $progressReport->target_notes }}</p>
                    </div>
                @endif
            </div>
        @endif

        <a href="{{ route('progress-reports.index') }}" class="text-sm text-gray-500 hover:underline">
            ← Kembali ke daftar laporan
        </a>
    </div>
</x-app-layout>
```

- [ ] **6.7 Buat template PDF**

Buat file `resources/views/progress-reports/pdf.blade.php`:

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Progres — {{ $progressReport->student->full_name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; padding: 20px; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 12px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        .header-box { border: 1px solid #ccc; padding: 12px 16px; margin-bottom: 16px; border-radius: 4px; }
        .header-title { font-size: 13px; font-weight: bold; text-align: center; margin-bottom: 8px; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 16px; }
        .meta-row { display: flex; gap: 8px; }
        .meta-label { color: #555; min-width: 100px; }
        .section-box { margin-bottom: 14px; }
        .section-title { font-weight: bold; font-size: 11px; background: #f0f0f0; padding: 4px 8px; margin-bottom: 4px; }
        .section-summary { font-style: italic; color: #444; margin: 0 0 6px 8px; }
        .checklist-item { display: flex; gap: 8px; margin: 2px 0 2px 8px; }
        .check-yes { color: #2a7a2a; font-weight: bold; }
        .check-no { color: #aaa; }
        .repertoire li { margin: 2px 0; }
        .narrative { white-space: pre-wrap; line-height: 1.5; }
        .session-date { font-weight: bold; color: #444; margin-top: 6px; }
        .footer { margin-top: 24px; display: flex; justify-content: flex-end; }
        .ttd-box { text-align: center; width: 160px; }
        .ttd-line { border-top: 1px solid #333; margin-top: 48px; padding-top: 4px; font-size: 10px; }
    </style>
</head>
<body>

{{-- Header --}}
<div class="header-box">
    <div class="header-title">LAPORAN EVALUASI LES MUSIK KITA</div>
    <div class="meta-grid">
        <div class="meta-row">
            <span class="meta-label">Nama Siswa</span>
            <span>: <strong>{{ $progressReport->student->full_name }}</strong></span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Periode</span>
            <span>: {{ $progressReport->namaBulan() }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Jurusan/Grade</span>
            <span>: {{ $progressReport->enrollment->package->instrument->name }}
                   / {{ $progressReport->enrollment->package->grade ?? $progressReport->enrollment->package->class_type }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Nama Pengajar</span>
            <span>: {{ $progressReport->teacher->name }}</span>
        </div>
    </div>
</div>

{{-- Checklist per seksi --}}
@foreach($progressReport->template->sections as $section)
    @php
        $sectionRecord = $progressReport->sections->firstWhere('report_template_section_id', $section->id);
    @endphp
    <div class="section-box">
        <div class="section-title">{{ $section->title }}</div>
        @if($sectionRecord?->summary)
            <div class="section-summary">{{ $sectionRecord->summary }}</div>
        @endif
        @foreach($section->items as $item)
            @php
                $itemRecord = $progressReport->items->firstWhere('report_template_item_id', $item->id);
                $checked    = $itemRecord?->is_checked ?? false;
            @endphp
            <div class="checklist-item">
                <span class="{{ $checked ? 'check-yes' : 'check-no' }}">{{ $checked ? '✓' : '○' }}</span>
                <span>{{ $item->label }}</span>
            </div>
        @endforeach
    </div>
@endforeach

{{-- Repertoar --}}
@if($progressReport->repertoire)
    <h2>Repertoar yang Sudah Dipelajari</h2>
    <ul class="repertoire">
        @foreach($progressReport->repertoire as $lagu)
            <li>{{ $lagu }}</li>
        @endforeach
    </ul>
@endif

{{-- Highlight --}}
@if($progressReport->highlight)
    <h2>Highlight Pencapaian</h2>
    <div class="narrative">{{ $progressReport->highlight }}</div>
@endif

{{-- Catatan per sesi --}}
@if($progressReport->sessionNotes->isNotEmpty())
    <h2>Catatan Per Sesi</h2>
    @foreach($progressReport->sessionNotes as $note)
        <div class="session-date">
            {{ \Carbon\Carbon::parse($note->session_date)->locale('id')->isoFormat('D MMMM Y') }}
        </div>
        <div class="narrative">{{ $note->notes }}</div>
    @endforeach
@endif

{{-- Catatan akhir & target --}}
@if($progressReport->summary_notes)
    <h2>Catatan</h2>
    <div class="narrative">{{ $progressReport->summary_notes }}</div>
@endif

@if($progressReport->target_notes)
    <h2>Target</h2>
    <div class="narrative">{{ $progressReport->target_notes }}</div>
@endif

{{-- TTD --}}
<div class="footer">
    <div class="ttd-box">
        <div>Pengajar,</div>
        <div class="ttd-line">{{ $progressReport->teacher->name }}</div>
    </div>
</div>

</body>
</html>
```

- [ ] **6.8 Jalankan test — harus PASS**

```bash
php artisan test tests/Feature/ProgressReportAdminTest.php
```

Expected: 4 tests, 4 passed.

- [ ] **6.9 Commit**

```bash
git add app/Http/Controllers/ProgressReportController.php \
        resources/views/progress-reports/ \
        routes/web.php
git commit -m "Feat: Admin lihat + download PDF laporan progres murid"
```

---

## Task 7: Navigation & Sidebar Wiring

- [ ] **7.1 Tambah link sidebar admin (navigation.blade.php)**

Edit `resources/views/layouts/navigation.blade.php`.

Cari blok Master Data dan tambahkan sebelum `report-templates` (Owner only), tambahkan di grup Owner+Admin+Auditor:

Tepat sebelum baris `<x-sidebar-item route="reports.finance"...` atau `reports.students`, tambahkan:

```blade
<x-sidebar-item route="progress-reports.index" icon="📝" label="Laporan Progres"
    :active="request()->routeIs('progress-reports.*')" />
```

Di dalam blok Master Data (di dalam `@role('Owner')`), tambahkan:

```blade
<x-sidebar-item route="report-templates.index" icon="📋" label="Template Laporan"
    :active="request()->routeIs('report-templates.*')" />
```

- [ ] **7.2 Tambah link ke dashboard guru**

Edit `resources/views/guru/dashboard.blade.php` — tambahkan card navigasi laporan setelah card jadwal yang sudah ada:

```blade
{{-- Link laporan progres --}}
<div class="mx-4 mb-3">
    <a href="{{ route('guru.laporan.index') }}"
       class="flex items-center gap-3 bg-mk-card border border-mk-border rounded-xl px-4 py-3 hover:bg-mk-cardHover transition-colors">
        <span class="text-xl">📝</span>
        <div class="flex-1">
            <div class="font-semibold text-mk-text text-sm">Laporan Progres</div>
            <div class="text-xs text-mk-muted mt-0.5">Isi laporan perkembangan murid bulanan</div>
        </div>
        <span class="text-mk-muted text-lg">›</span>
    </a>
</div>
```

- [ ] **7.3 Build assets**

```bash
npm run build
```

Expected: build selesai tanpa error.

- [ ] **7.4 Commit**

```bash
git add resources/views/layouts/navigation.blade.php \
        resources/views/guru/dashboard.blade.php
git commit -m "Feat: Tambah navigasi Laporan Progres di sidebar admin dan dashboard guru"
```

---

## Task 8: Jalankan Semua Test

- [ ] **8.1 Jalankan full test suite**

```bash
php artisan test
```

Expected: semua test pass. Tidak ada regresi.

- [ ] **8.2 Jika ada test gagal**

Periksa error, fix issue yang ditemukan, commit fix:

```bash
git add -A
git commit -m "Fix: [deskripsikan fix yang dilakukan]"
```

---

## Checklist Spec Coverage

- [x] Template per instrumen, dikelola Owner — Task 4
- [x] Template sections + items (nested) — Task 4
- [x] Laporan per bulan per enrollment — Task 5
- [x] Guru isi via portal — Task 5
- [x] Checklist + narasi + catatan sesi + highlight + repertoar + target — Task 5
- [x] Tidak ada approval flow (langsung SUBMITTED) — Task 5 & 6
- [x] Admin/Owner download PDF — Task 6
- [x] Format PDF mengikuti laporan Word yang ada — Task 6 (pdf.blade.php)
- [x] Guru tidak bisa akses laporan guru lain — Task 5 (test_guru_tidak_bisa_edit_laporan_guru_lain)
- [x] Guru tidak bisa akses admin routes — Task 6 (test_guru_tidak_bisa_akses)
- [x] Laporan duplikat dicegah (unique enrollment+month+year) — Migration Task 2 + test Task 5
