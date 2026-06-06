# Auto-kirim Laporan Sesi WA — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Setelah guru menyimpan catatan per sesi, sistem otomatis mengirim pesan teks WhatsApp ramah ke ortu (fallback nomor murid) via Fonnte dengan debounce 10 menit.

**Architecture:** `SessionReportWaService` mengikuti pola `ScheduleReminderService` (compose template, resolve nomor, kirim Fonnte, persist log). Trigger via `SendSessionReportWaJob` (delayed queue) dari `GuruController::updateSessionNotes`. Debounce: job membawa snapshot `note_updated_at`; job yang stale atau sudah terkirim di-skip.

**Tech Stack:** Laravel 11, database queue, Fonnte HTTP API, Blade, PHPUnit, Spatie Permission

**Design spec:** `docs/superpowers/specs/2026-06-07-session-report-wa-design.md`

---

## File Map

**Create:**
- `config/session_report_wa.php`
- `database/migrations/2026_06_07_100000_create_session_report_wa_logs_table.php`
- `app/Models/SessionReportWaLog.php`
- `app/Services/SessionReportWaService.php`
- `app/Jobs/SendSessionReportWaJob.php`
- `app/Http/Controllers/SessionReportWaLogController.php`
- `resources/views/session-report-wa-logs/index.blade.php`
- `tests/Feature/SessionReportWaTest.php`

**Modify:**
- `app/Models/WhatsappMessageTemplate.php` — konstanta + `defaultSessionReport()`
- `database/seeders/WhatsappMessageTemplateSeeder.php` — seed `SESSION_REPORT`
- `app/Http/Controllers/WhatsappMessageTemplateController.php` — protect delete
- `resources/views/whatsapp-templates/_form.blade.php` — readonly code
- `app/Http/Controllers/GuruController.php` — dispatch job setelah simpan catatan
- `resources/views/guru/_sesi-absensi-actions.blade.php` — status chip WA
- `routes/web.php` — admin log routes + resend
- `resources/views/layouts/navigation.blade.php` — sidebar link
- `.env.example` — env vars baru

---

### Task 1: Config + Migration + Model

**Files:**
- Create: `config/session_report_wa.php`, migration, `SessionReportWaLog.php`
- Modify: `.env.example`

- [ ] **Step 1: Write failing test — model constants exist**

Add to `tests/Feature/SessionReportWaTest.php` (create file with only this test first):

```php
<?php

namespace Tests\Feature;

use App\Models\SessionReportWaLog;
use Tests\TestCase;

class SessionReportWaTest extends TestCase
{
    public function test_session_report_wa_log_status_constants(): void
    {
        $this->assertSame('SUCCESS', SessionReportWaLog::STATUS_SUCCESS);
        $this->assertSame('FAILED', SessionReportWaLog::STATUS_FAILED);
        $this->assertSame('SKIPPED', SessionReportWaLog::STATUS_SKIPPED);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd c:/laragon/www/musik-kita-ops && php artisan test --filter=test_session_report_wa_log_status_constants
```

Expected: FAIL — class `SessionReportWaLog` not found

- [ ] **Step 3: Create config**

`config/session_report_wa.php`:

```php
<?php

return [
    'enabled' => env('SESSION_REPORT_WA_ENABLED', false),
    'debounce_minutes' => (int) env('SESSION_REPORT_WA_DEBOUNCE_MINUTES', 10),
    'update_prefix' => env('SESSION_REPORT_WA_UPDATE_PREFIX', '[Update]'),
];
```

Append to `.env.example`:

```env
# Laporan sesi otomatis ke WhatsApp ortu (Fonnte)
SESSION_REPORT_WA_ENABLED=false
SESSION_REPORT_WA_DEBOUNCE_MINUTES=10
```

- [ ] **Step 4: Create migration**

`database/migrations/2026_06_07_100000_create_session_report_wa_logs_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_report_wa_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->text('message_body');
            $table->string('provider', 20)->default('fonnte');
            $table->json('provider_message_ids')->nullable();
            $table->string('status', 20);
            $table->boolean('is_update')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['class_session_id', 'sent_at'], 'sess_wa_log_session_sent_idx');
            $table->index(['student_id', 'sent_at'], 'sess_wa_log_student_sent_idx');
            $table->index(['status', 'sent_at'], 'sess_wa_log_status_sent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_report_wa_logs');
    }
};
```

- [ ] **Step 5: Create model**

`app/Models/SessionReportWaLog.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionReportWaLog extends Model
{
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_SKIPPED = 'SKIPPED';

    protected $fillable = [
        'class_session_id',
        'student_id',
        'phone',
        'message_body',
        'provider',
        'provider_message_ids',
        'status',
        'is_update',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'provider_message_ids' => 'array',
        'is_update'            => 'boolean',
        'sent_at'              => 'datetime',
    ];

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
```

- [ ] **Step 6: Run migration + test**

```bash
php artisan migrate
php artisan test --filter=test_session_report_wa_log_status_constants
```

Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add config/session_report_wa.php database/migrations/2026_06_07_100000_create_session_report_wa_logs_table.php app/Models/SessionReportWaLog.php tests/Feature/SessionReportWaTest.php .env.example
git commit -m "feat: add session report WA log table and config"
```

---

### Task 2: WhatsApp Template SESSION_REPORT

**Files:**
- Modify: `WhatsappMessageTemplate.php`, `WhatsappMessageTemplateSeeder.php`, `WhatsappMessageTemplateController.php`, `whatsapp-templates/_form.blade.php`

- [ ] **Step 1: Write failing test**

Add to `SessionReportWaTest.php`:

```php
use App\Models\WhatsappMessageTemplate;

public function test_default_session_report_template_exists_after_seed(): void
{
    $this->seed(\Database\Seeders\WhatsappMessageTemplateSeeder::class);

    $template = WhatsappMessageTemplate::defaultSessionReport();

    $this->assertNotNull($template);
    $this->assertSame(WhatsappMessageTemplate::CODE_SESSION_REPORT, $template->code);
    $this->assertTrue($template->is_active);
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --filter=test_default_session_report_template_exists_after_seed
```

- [ ] **Step 3: Extend WhatsappMessageTemplate model**

```php
public const CODE_SESSION_REPORT = 'SESSION_REPORT';

public static function defaultSessionReport(): ?self
{
    return static::query()
        ->where('code', self::CODE_SESSION_REPORT)
        ->where('is_active', true)
        ->first();
}
```

- [ ] **Step 4: Add seeder entry**

In `WhatsappMessageTemplateSeeder::run()`, after SCHEDULE_REMINDER block:

```php
WhatsappMessageTemplate::firstOrCreate(
    ['code' => WhatsappMessageTemplate::CODE_SESSION_REPORT],
    [
        'name'       => 'Laporan Sesi ke Ortu',
        'sort_order' => 3,
        'is_active'  => true,
        'body'       => <<<'TEXT'
Halo, Yth. {nama_ortu} 👋

Les musik *{nama_murid}* hari ini sudah selesai. Terima kasih sudah mempercayakan perjalanan musiknya kepada kami di Musik KITA 🎵

📅 *{tanggal_sesi}*
🎹 Instrumen: {instrumen}
👨‍🏫 Guru: {nama_guru}

*Materi hari ini:*
{materi}

*Latihan minggu ini:*
{tugas}

{blok_catatan}

{pesan_semangat}

Kami senang melihat langkah-langkah kecil {nama_murid} menuju kemampuan bermusik yang lebih baik. Dukungan Bapak/Ibu di rumah sangat berarti — semangat latihan ya! 💪🎶

Salam hangat,
Musik KITA
WA: {studio_wa}
TEXT,
    ],
);
```

- [ ] **Step 5: Protect template from delete**

In `WhatsappMessageTemplateController::destroy`, add to protected codes array:

```php
WhatsappMessageTemplate::CODE_SESSION_REPORT,
```

In `resources/views/whatsapp-templates/_form.blade.php`, add to readonly `@if` array:

```php
\App\Models\WhatsappMessageTemplate::CODE_SESSION_REPORT,
```

- [ ] **Step 6: Run test + commit**

```bash
php artisan test --filter=test_default_session_report_template_exists_after_seed
git add app/Models/WhatsappMessageTemplate.php database/seeders/WhatsappMessageTemplateSeeder.php app/Http/Controllers/WhatsappMessageTemplateController.php resources/views/whatsapp-templates/_form.blade.php tests/Feature/SessionReportWaTest.php
git commit -m "feat: add SESSION_REPORT WhatsApp message template"
```

---

### Task 3: SessionReportWaService (core logic)

**Files:**
- Create: `app/Services/SessionReportWaService.php`
- Modify: `tests/Feature/SessionReportWaTest.php`

- [ ] **Step 1: Write failing test — compose message with placeholders**

Add helper + test to `SessionReportWaTest.php`:

```php
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\SessionTeacherNote;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\SessionReportWaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

// Add RefreshDatabase trait to class

private function seedSessionReportTemplate(): void
{
    WhatsappMessageTemplate::create([
        'code'       => WhatsappMessageTemplate::CODE_SESSION_REPORT,
        'name'       => 'Laporan Sesi',
        'body'       => 'Halo {nama_ortu}, {nama_murid} {tanggal_sesi} {instrumen} {nama_guru} M:{materi} T:{tugas} {blok_catatan} {pesan_semangat} {studio_wa}',
        'is_active'  => true,
        'sort_order' => 3,
    ]);
}

public function test_compose_message_includes_session_fields(): void
{
    $this->seedSessionReportTemplate();

    $instrument = Instrument::factory()->create(['name' => 'Piano']);
    $package = Package::factory()->create(['instrument_id' => $instrument->id]);
    $teacher = Teacher::factory()->create(['name' => 'Pak Budi']);
    $student = Student::factory()->create([
        'full_name'    => 'Ani Kecil',
        'parent_name'  => 'Bu Siti',
        'parent_phone' => '0816920592',
        'status'       => 'Aktif',
    ]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'package_id' => $package->id,
        'teacher_id' => $teacher->id,
        'status'     => Enrollment::STATUS_ACTIVE,
    ]);
    $session = ClassSession::factory()->create([
        'student_id'    => $student->id,
        'enrollment_id' => $enrollment->id,
        'teacher_id'    => $teacher->id,
        'session_date'  => '2026-06-05',
        'status'        => ClassSession::STATUS_HADIR,
    ]);
    SessionTeacherNote::create([
        'class_session_id' => $session->id,
        'teacher_id'       => $teacher->id,
        'material_learned' => 'Scales mayor',
        'homework_notes'   => 'Latihan 15 menit',
        'notes'            => 'Antusias',
        'session_rating'   => 5,
    ]);

    $session->load(['student', 'teacher', 'enrollment.package.instrument', 'teacherNote']);
    $service = app(SessionReportWaService::class);
    $message = $service->composeMessage($session);

    $this->assertStringContainsString('Bu Siti', $message);
    $this->assertStringContainsString('Ani Kecil', $message);
    $this->assertStringContainsString('Scales mayor', $message);
    $this->assertStringContainsString('antusias dan fokus', $message);
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --filter=test_compose_message_includes_session_fields
```

- [ ] **Step 3: Implement SessionReportWaService**

`app/Services/SessionReportWaService.php`:

```php
<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\SessionReportWaLog;
use App\Models\SessionTeacherNote;
use App\Models\Student;
use App\Models\WhatsappMessageTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SessionReportWaService
{
    public function __construct(
        private readonly FonnteService $fonnte,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('session_report_wa.enabled');
    }

    public function debounceMinutes(): int
    {
        return max(1, (int) config('session_report_wa.debounce_minutes', 10));
    }

    /** @return 'DISABLED'|'PENDING'|'SENT'|'FAILED'|'SKIPPED' */
    public function deliveryState(ClassSession $session): string
    {
        if (! $this->isEnabled()) {
            return 'DISABLED';
        }

        $note = $session->teacherNote;
        if (! $note || ! $this->noteHasSendableContent($note)) {
            return 'DISABLED';
        }

        $latest = $this->latestSuccessLog($session->id);
        if ($latest && $note->updated_at && $latest->sent_at->gte($note->updated_at)) {
            return 'SENT';
        }

        $latestAny = SessionReportWaLog::query()
            ->where('class_session_id', $session->id)
            ->latest('sent_at')
            ->first();

        if ($latestAny?->status === SessionReportWaLog::STATUS_FAILED) {
            return 'FAILED';
        }

        if ($latestAny?->status === SessionReportWaLog::STATUS_SKIPPED) {
            return 'SKIPPED';
        }

        return 'PENDING';
    }

    public function maskPhone(?string $phone): string
    {
        $normalized = $this->fonnte->normalizePhone($phone);
        if ($normalized === null || strlen($normalized) < 8) {
            return '—';
        }

        return substr($normalized, 0, 4) . '***' . substr($normalized, -4);
    }

    public function composeMessage(ClassSession $session, bool $isUpdate = false): string
    {
        $template = WhatsappMessageTemplate::defaultSessionReport();
        if (! $template) {
            throw new \RuntimeException('Template SESSION_REPORT aktif tidak ditemukan.');
        }

        $student = $session->student;
        $note = $session->teacherNote;
        $teacherName = $session->status === ClassSession::STATUS_DIGANTI
            ? ($session->substituteTeacher?->name ?? $session->teacher?->name ?? '-')
            : ($session->teacher?->name ?? '-');

        $sessionDate = Carbon::parse($session->session_date)->locale('id')->translatedFormat('d F Y');
        $instrument = $session->enrollment?->package?->instrument?->name ?? 'Les Musik';

        $catatan = trim((string) ($note?->notes ?? ''));
        $blokCatatan = $catatan !== ''
            ? "*Catatan guru:*\n{$catatan}"
            : '';

        $replacements = [
            '{nama_ortu}'      => $student?->parent_name ?? 'Bapak/Ibu',
            '{nama_murid}'     => $student?->full_name ?? '-',
            '{tanggal_sesi}'   => $sessionDate,
            '{instrumen}'      => $instrument,
            '{nama_guru}'      => $teacherName,
            '{materi}'         => filled(trim((string) ($note?->material_learned ?? '')))
                ? trim((string) $note->material_learned)
                : 'Belum dicatat',
            '{tugas}'          => filled(trim((string) ($note?->homework_notes ?? '')))
                ? trim((string) $note->homework_notes)
                : 'Tidak ada tugas khusus — cukup latihan ringan sesuai materi hari ini',
            '{blok_catatan}'   => $blokCatatan,
            '{pesan_semangat}' => $this->encouragementLine($student, $note?->session_rating),
            '{studio_wa}'      => FonnteService::STUDIO_WA_DISPLAY,
        ];

        $body = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template->body,
        );

        // Rapikan baris kosong ganda setelah blok catatan dihilangkan
        $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;

        if ($isUpdate) {
            $prefix = trim((string) config('session_report_wa.update_prefix', '[Update]'));
            if ($prefix !== '') {
                $body = "{$prefix}\n\n{$body}";
            }
        }

        return trim($body);
    }

    /**
     * Kirim WA untuk satu sesi. Return log row.
     */
    public function sendForSession(ClassSession $session, ?Carbon $noteUpdatedAt = null): ?SessionReportWaLog
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $session->loadMissing([
            'student',
            'teacher',
            'substituteTeacher',
            'enrollment.package.instrument',
            'teacherNote',
        ]);

        $note = $session->teacherNote;
        if (! $note || ! $this->noteHasSendableContent($note)) {
            return null;
        }

        if ($noteUpdatedAt !== null && $note->updated_at->gt($noteUpdatedAt)) {
            return null;
        }

        $latestSuccess = $this->latestSuccessLog($session->id);
        if ($latestSuccess && $note->updated_at && $latestSuccess->sent_at->gte($note->updated_at)) {
            return null;
        }

        $student = $session->student;
        if (! $student) {
            return null;
        }

        $recipientPhone = $this->resolveRecipientPhone($student);
        if ($recipientPhone === null) {
            return $this->persistLog(
                session: $session,
                student: $student,
                phone: '',
                message: '',
                providerMessageIds: [],
                status: SessionReportWaLog::STATUS_SKIPPED,
                isUpdate: $latestSuccess !== null,
                error: 'Nomor WhatsApp tujuan tidak tersedia.',
            );
        }

        if (! $this->fonnte->isConfigured()) {
            return $this->persistLog(
                session: $session,
                student: $student,
                phone: (string) $this->fonnte->normalizePhone($recipientPhone),
                message: '',
                providerMessageIds: [],
                status: SessionReportWaLog::STATUS_FAILED,
                isUpdate: $latestSuccess !== null,
                error: 'Kredensial Fonnte belum dikonfigurasi.',
            );
        }

        $isUpdate = $latestSuccess !== null;
        $message = $this->composeMessage($session, $isUpdate);
        $result = $this->fonnte->sendText($recipientPhone, $message);

        return $this->persistLog(
            session: $session,
            student: $student,
            phone: (string) $this->fonnte->normalizePhone($recipientPhone),
            message: $message,
            providerMessageIds: $result['message_ids'],
            status: $result['ok'] ? SessionReportWaLog::STATUS_SUCCESS : SessionReportWaLog::STATUS_FAILED,
            isUpdate: $isUpdate,
            error: $result['error'],
        );
    }

    private function encouragementLine(?Student $student, ?int $rating): string
    {
        $name = $student?->full_name ?? 'Ananda';

        return match (true) {
            $rating === 5 => "Hari ini {$name} tampil sangat antusias dan fokus — perkembangannya terlihat jelas!",
            $rating === 4 => "{$name} menunjukkan kemajuan yang baik hari ini. Pertahankan semangatnya!",
            $rating === 3 => "{$name} sudah berusaha dengan baik. Sedikit latihan rutin di rumah akan membuat hasilnya makin terasa.",
            default       => "Setiap sesi adalah langkah berharga. Mari terus mendampingi {$name} dengan sabar dan konsisten.",
        };
    }

    private function noteHasSendableContent(SessionTeacherNote $note): bool
    {
        return filled(trim((string) ($note->material_learned ?? '')))
            || filled(trim((string) ($note->homework_notes ?? '')))
            || filled(trim((string) ($note->notes ?? '')))
            || filled($note->session_rating);
    }

    private function resolveRecipientPhone(Student $student): ?string
    {
        if ($this->fonnte->isValidPhone($student->parent_phone)) {
            return $student->parent_phone;
        }

        if ($this->fonnte->isValidPhone($student->phone)) {
            return $student->phone;
        }

        return null;
    }

    private function latestSuccessLog(int $classSessionId): ?SessionReportWaLog
    {
        return SessionReportWaLog::query()
            ->where('class_session_id', $classSessionId)
            ->where('status', SessionReportWaLog::STATUS_SUCCESS)
            ->latest('sent_at')
            ->first();
    }

    /** @param array<int, string> $providerMessageIds */
    private function persistLog(
        ClassSession $session,
        Student $student,
        string $phone,
        string $message,
        array $providerMessageIds,
        string $status,
        bool $isUpdate,
        ?string $error,
    ): SessionReportWaLog {
        return DB::transaction(function () use (
            $session, $student, $phone, $message, $providerMessageIds, $status, $isUpdate, $error,
        ) {
            return SessionReportWaLog::create([
                'class_session_id'       => $session->id,
                'student_id'             => $student->id,
                'phone'                  => $phone,
                'message_body'           => $message,
                'provider'               => 'fonnte',
                'provider_message_ids'   => $providerMessageIds,
                'status'                 => $status,
                'is_update'              => $isUpdate,
                'error_message'          => $error,
                'sent_at'                => now(),
            ]);
        });
    }
}
```

- [ ] **Step 4: Run compose test**

```bash
php artisan test --filter=test_compose_message_includes_session_fields
```

Expected: PASS

- [ ] **Step 5: Write failing test — send via Fonnte + parent phone fallback**

```php
public function test_sends_to_parent_phone_after_job_runs(): void
{
    $this->seedSessionReportTemplate();
    config([
        'session_report_wa.enabled'    => true,
        'session_report_wa.debounce_minutes' => 0,
        'services.fonnte.token'        => 'test-fonnte-token',
        'services.fonnte.base_url'     => 'https://api.fonnte.com',
        'services.fonnte.country_code' => '62',
    ]);

    Http::fake([
        'https://api.fonnte.com/send' => Http::response([
            'status' => true,
            'id'     => ['999'],
            'detail' => 'success',
        ], 200),
    ]);

    // ... build session + note same as compose test ...

    $note = SessionTeacherNote::first();
    $snapshot = $note->updated_at->copy();

    app(SessionReportWaService::class)->sendForSession($session->fresh()->load([
        'student', 'teacher', 'enrollment.package.instrument', 'teacherNote',
    ]), $snapshot);

    Http::assertSent(fn ($req) => $req['target'] === '0816920592');
    $this->assertDatabaseHas('session_report_wa_logs', [
        'class_session_id' => $session->id,
        'status'           => SessionReportWaLog::STATUS_SUCCESS,
    ]);
}

public function test_falls_back_to_student_phone_when_parent_null(): void
{
    // Same setup, parent_phone null, student phone 081234567890
    // Assert target 081234567890
}

public function test_skips_when_note_updated_after_snapshot(): void
{
    // sendForSession with old snapshot after note->touch()
    // assert no new log / assertDatabaseCount unchanged
}
```

- [ ] **Step 6: Run send tests + commit**

```bash
php artisan test --filter=SessionReportWaTest
git add app/Services/SessionReportWaService.php tests/Feature/SessionReportWaTest.php
git commit -m "feat: add SessionReportWaService for compose and send"
```

---

### Task 4: Queue Job + GuruController dispatch

**Files:**
- Create: `app/Jobs/SendSessionReportWaJob.php`
- Modify: `app/Http/Controllers/GuruController.php`, `tests/Feature/SessionReportWaTest.php`

- [ ] **Step 1: Write failing test — saving notes dispatches delayed job**

```php
use App\Jobs\SendSessionReportWaJob;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use Spatie\Permission\Models\Role;

public function test_saving_session_notes_dispatches_wa_job(): void
{
    Queue::fake();
    $this->seedSessionReportTemplate();
    config(['session_report_wa.enabled' => true, 'session_report_wa.debounce_minutes' => 10]);

    // setup guru user + hadir session (copy pattern from SessionTeacherNoteTest)

    $this->actingAs($guruUser)
        ->patch(route('guru.sesi.catatan.update', $session), [
            'material_learned' => 'Scales',
        ])
        ->assertSessionHas('success');

    Queue::assertPushed(SendSessionReportWaJob::class, function ($job) use ($session) {
        return $job->classSessionId === $session->id;
    });
}
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
php artisan test --filter=test_saving_session_notes_dispatches_wa_job
```

- [ ] **Step 3: Create job**

`app/Jobs/SendSessionReportWaJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\ClassSession;
use App\Services\SessionReportWaService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSessionReportWaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $classSessionId,
        public string $noteUpdatedAtIso,
    ) {}

    public function handle(SessionReportWaService $service): void
    {
        $session = ClassSession::query()
            ->with([
                'student',
                'teacher',
                'substituteTeacher',
                'enrollment.package.instrument',
                'teacherNote',
            ])
            ->find($this->classSessionId);

        if (! $session || ! $session->teacherNote) {
            return;
        }

        $snapshot = Carbon::parse($this->noteUpdatedAtIso);
        $service->sendForSession($session, $snapshot);
    }
}
```

- [ ] **Step 4: Wire GuruController**

In `updateSessionNotes`, after `SessionTeacherNote::updateOrCreate(...)`:

```php
$note = SessionTeacherNote::query()
    ->where('class_session_id', $classSession->id)
    ->first();

if ($note && app(SessionReportWaService::class)->isEnabled()) {
    SendSessionReportWaJob::dispatch(
        $classSession->id,
        $note->updated_at->toIso8601String(),
    )->delay(now()->addMinutes(app(SessionReportWaService::class)->debounceMinutes()));
}
```

Update success flash message:

```php
return back()->with('success', 'Catatan sesi tersimpan. Laporan sesi akan otomatis dikirim ke WhatsApp orang tua.');
```

Add constructor injection or `app()` — match existing controller style (constructor already has services).

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=SessionReportWaTest
php artisan test --filter=SessionTeacherNoteTest
```

Expected: all PASS

- [ ] **Step 6: Commit**

```bash
git add app/Jobs/SendSessionReportWaJob.php app/Http/Controllers/GuruController.php tests/Feature/SessionReportWaTest.php
git commit -m "feat: dispatch delayed WA job when guru saves session notes"
```

---

### Task 5: Guru UI — status chip

**Files:**
- Modify: `resources/views/guru/_sesi-absensi-actions.blade.php`

- [ ] **Step 1: Eager-load data in guru dashboard/jadwal views**

Find where `_sesi-absensi-actions` is included; ensure `$sesi` loads `teacherNote` and latest WA log if needed. Simplest: compute state in blade via injected service:

At top of `_sesi-absensi-actions.blade.php` after existing `@php`:

```php
$waService = app(\App\Services\SessionReportWaService::class);
$waState = $teacherNote ? $waService->deliveryState($sesi) : 'DISABLED';
$waMaskedPhone = $waService->maskPhone(
    $sesi->student?->parent_phone ?: $sesi->student?->phone
);
```

Ensure parent views eager-load `student` on `$sesi` (likely already).

- [ ] **Step 2: Add status chip below Simpan button**

Inside the catatan form, after submit button:

```blade
@if($waState !== 'DISABLED')
    <div class="text-xs rounded-lg px-3 py-2 border
        @if($waState === 'SENT') border-green-200 bg-green-50 text-green-700
        @elseif($waState === 'FAILED') border-red-200 bg-red-50 text-red-700
        @elseif($waState === 'SKIPPED') border-gray-200 bg-gray-50 text-gray-600
        @else border-amber-200 bg-amber-50 text-amber-700
        @endif">
        @if($waState === 'SENT')
            ✓ Pesan WA terkirim ke {{ $waMaskedPhone }}
        @elseif($waState === 'FAILED')
            ⚠ Gagal kirim WA — hubungi admin
        @elseif($waState === 'SKIPPED')
            ℹ Nomor WA tidak tersedia
        @else
            ⏳ Akan dikirim ke ortu dalam ~{{ config('session_report_wa.debounce_minutes', 10) }} menit
        @endif
    </div>
    <p class="text-[11px] text-mk-muted">Dikirim ke nomor ortu, atau nomor murid jika ortu kosong.</p>
@endif
```

- [ ] **Step 3: Manual smoke check**

Enable di `.env` testing: `SESSION_REPORT_WA_ENABLED=true`, simpan catatan sesi di portal guru, verifikasi chip PENDING muncul.

- [ ] **Step 4: Commit**

```bash
git add resources/views/guru/_sesi-absensi-actions.blade.php
git commit -m "feat: show WA delivery status on guru session notes form"
```

---

### Task 6: Admin log page + manual resend

**Files:**
- Create: `SessionReportWaLogController.php`, `session-report-wa-logs/index.blade.php`
- Modify: `routes/web.php`, `navigation.blade.php`, `SessionReportWaService.php` (optional `resend()`)

- [ ] **Step 1: Write failing feature test**

```php
public function test_admin_can_view_session_report_wa_logs(): void
{
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    SessionReportWaLog::create([
        'class_session_id' => ClassSession::factory()->create()->id,
        'student_id'       => Student::factory()->create()->id,
        'phone'            => '62816920592',
        'message_body'     => 'Test',
        'status'           => SessionReportWaLog::STATUS_SUCCESS,
        'is_update'        => false,
        'sent_at'          => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('session-report-wa-logs.index'))
        ->assertOk()
        ->assertSee('Test');
}
```

- [ ] **Step 2: Create controller**

`app/Http/Controllers/SessionReportWaLogController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\SessionReportWaLog;
use App\Services\SessionReportWaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionReportWaLogController extends Controller
{
    public function __construct(
        private readonly SessionReportWaService $waService,
    ) {}

    public function index(Request $request): View
    {
        $query = SessionReportWaLog::query()
            ->with(['student', 'classSession.teacher', 'classSession.enrollment.package.instrument'])
            ->latest('sent_at');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->get('search', ''))) {
            $query->whereHas('student', fn ($q) => $q
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('student_code', 'like', "%{$search}%"));
        }

        if ($date = $request->get('date')) {
            $query->whereHas('classSession', fn ($q) => $q->whereDate('session_date', $date));
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('session-report-wa-logs.index', [
            'logs'      => $logs,
            'waService' => $this->waService,
            'filters'   => $request->only(['status', 'search', 'date']),
        ]);
    }

    public function resend(SessionReportWaLog $sessionReportWaLog): RedirectResponse
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['Owner', 'Admin']),
            403,
        );

        $session = $sessionReportWaLog->classSession()
            ->with(['student', 'teacher', 'substituteTeacher', 'enrollment.package.instrument', 'teacherNote'])
            ->first();

        if (! $session || ! $session->teacherNote) {
            return back()->with('error', 'Sesi atau catatan tidak ditemukan.');
        }

        $this->waService->sendForSession($session, null);

        return back()->with('success', 'Permintaan kirim ulang diproses.');
    }
}
```

Add public method on service for forced resend — pass `noteUpdatedAt: null` and bypass idempotent check when second arg is special. **Adjust `sendForSession`:** add optional `$force = false` parameter; when `$force === true`, skip `latestSuccess sent_at >= updated_at` check.

```php
public function sendForSession(ClassSession $session, ?Carbon $noteUpdatedAt = null, bool $force = false): ?SessionReportWaLog
{
    // ...
    if (! $force && $latestSuccess && $note->updated_at && $latestSuccess->sent_at->gte($note->updated_at)) {
        return null;
    }
    // ...
}
```

Controller resend calls `sendForSession($session, null, force: true)`.

- [ ] **Step 3: Create view**

`resources/views/session-report-wa-logs/index.blade.php` — table with columns: Tanggal Sesi, Murid, Guru, Instrumen, Nomor (masked via `$waService->maskPhone`), Status badge, Update?, Waktu kirim, Aksi (Kirim Ulang if FAILED).

Follow styling from `invoice-reminders/index.blade.php`.

- [ ] **Step 4: Register routes**

In admin read+write group (Owner + Admin), near invoice-reminders:

```php
Route::get('session-report-wa-logs', [SessionReportWaLogController::class, 'index'])
    ->name('session-report-wa-logs.index');
Route::post('session-report-wa-logs/{sessionReportWaLog}/resend', [SessionReportWaLogController::class, 'resend'])
    ->name('session-report-wa-logs.resend');
```

Sidebar in `navigation.blade.php` (near Reminder WA):

```blade
<x-sidebar-item route="session-report-wa-logs.index" icon="📋" label="Log Laporan Sesi WA"
    :active="request()->routeIs('session-report-wa-logs.*')"
    :roles="['Owner', 'Admin', 'Auditor']" />
```

Match existing `x-sidebar-item` props pattern.

- [ ] **Step 5: Run tests + commit**

```bash
php artisan test --filter=SessionReportWaTest
git add app/Http/Controllers/SessionReportWaLogController.php resources/views/session-report-wa-logs/index.blade.php routes/web.php resources/views/layouts/navigation.blade.php app/Services/SessionReportWaService.php tests/Feature/SessionReportWaTest.php
git commit -m "feat: add admin log page and manual resend for session report WA"
```

---

### Task 7: Integration verification

- [ ] **Step 1: Run full test suite**

```bash
php artisan test
```

Expected: all PASS

- [ ] **Step 2: Queue worker note for deploy**

Document in plan / README snippet: production needs `php artisan queue:work` (or supervisor) because this is the **first queue job** in the project.

- [ ] **Step 3: Enable locally and smoke test**

`.env`:
```env
SESSION_REPORT_WA_ENABLED=true
QUEUE_CONNECTION=database
```

Terminal 1: `php artisan queue:work`
Terminal 2: simpan catatan sesi → tunggu debounce → cek log admin + Fonnte sandbox.

- [ ] **Step 4: Final commit if any doc tweaks**

```bash
git commit -m "chore: verify session report WA integration"
```

---

## Spec Self-Review

| Requirement | Task |
|-------------|------|
| Per sesi trigger | Task 4 |
| Teks WA only | Task 3 (Fonnte sendText) |
| Opsi A debounce 10 min | Task 4 job delay |
| parent_phone → phone murid | Task 3 `resolveRecipientPhone` |
| Template ramah | Task 2 seeder |
| `{pesan_semangat}` dinamis | Task 3 `encouragementLine` |
| Edit setelah terkirim → update | Task 3 `isUpdate` + prefix |
| Guru status chip | Task 5 |
| Admin log + resend | Task 6 |
| Feature flag | Task 1 config |

**Placeholder scan:** No TBD/TODO in plan.  
**Type consistency:** `SendSessionReportWaJob::$classSessionId`, `noteUpdatedAtIso`, `sendForSession($session, $snapshot)` aligned across tasks.

---

## Production Checklist

1. Set `SESSION_REPORT_WA_ENABLED=true`
2. Fonnte credentials sudah ada (sama dengan schedule reminder)
3. Jalankan `php artisan db:seed --class=WhatsappMessageTemplateSeeder` di staging/prod jika belum
4. Pastikan **queue worker** berjalan terus-menerus
5. Owner review template di `/whatsapp-templates`
