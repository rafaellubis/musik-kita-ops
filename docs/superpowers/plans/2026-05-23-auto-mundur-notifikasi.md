# Auto-Mundur — Laravel Database Notifications — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bangun sistem notifikasi untuk mendeteksi murid Aktif dengan tunggakan >1 bulan, kirim notifikasi ke Admin/Owner lewat Laravel Database Notifications, tampilkan di topbar sebagai bell badge dengan dropdown.

**Architecture:** Cron tgl 1 jam 06:05 jalankan `CheckOverdueStudents` command → buat `MuridOverdueNotification` database record per murid eligible → View Composer inject notif ke layout → topbar bell badge + Alpine.js dropdown → Admin klik "Tinjau →" → redirect ke halaman murid → klik Mundurkan.

**Tech Stack:** Laravel 11, PHP 8.3, Spatie Permission, Alpine.js, PHPUnit (SQLite in-memory)

---

## File Map

| Aksi | File | Tanggung Jawab |
|------|------|----------------|
| BUAT via artisan | `database/migrations/xxxx_create_notifications_table.php` | Tabel notifikasi Laravel |
| BUAT | `app/Notifications/MuridOverdueNotification.php` | Payload notif + channel database |
| BUAT | `app/Console/Commands/CheckOverdueStudents.php` | Query murid overdue + kirim notif |
| BUAT | `app/Http/Controllers/NotificationController.php` | Mark read satu / semua |
| BUAT | `tests/Unit/MuridOverdueNotificationTest.php` | Unit test notification class |
| BUAT | `tests/Feature/Admin/CheckOverdueStudentsTest.php` | Feature test command |
| BUAT | `tests/Feature/Admin/NotificationControllerTest.php` | Feature test controller |
| UBAH | `app/Providers/AppServiceProvider.php` | Daftarkan View Composer |
| UBAH | `resources/views/layouts/app.blade.php` | Tambah bell + dropdown topbar |
| UBAH | `routes/web.php` | 2 route notifikasi |
| UBAH | `routes/console.php` | Daftarkan cron students:check-overdue |

---

## Task 1: Migration Tabel Notifications

**Files:**
- Create: `database/migrations/xxxx_create_notifications_table.php` (via artisan)

- [ ] **Step 1.1: Generate migration**

```bash
php artisan notifications:table
```

Expected output: `Migration created successfully.`

- [ ] **Step 1.2: Jalankan migration**

```bash
php artisan migrate
```

Expected: tabel `notifications` terbuat di database.

- [ ] **Step 1.3: Verifikasi kolom tabel**

```bash
php artisan tinker --execute="Schema::getColumnListing('notifications');"
```

Expected output mengandung: `id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`

- [ ] **Step 1.4: Commit**

```bash
git add database/migrations/
git commit -m "M05: Migration tabel notifications untuk Laravel Database Notifications"
```

---

## Task 2: MuridOverdueNotification Class

**Files:**
- Create: `app/Notifications/MuridOverdueNotification.php`
- Create: `tests/Unit/MuridOverdueNotificationTest.php`

- [ ] **Step 2.1: Tulis test yang gagal**

Buat file `tests/Unit/MuridOverdueNotificationTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Student;
use App\Notifications\MuridOverdueNotification;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Tests\TestCase;

class MuridOverdueNotificationTest extends TestCase
{
    public function test_via_returns_database_channel(): void
    {
        $student = new Student();
        $student->id = 1;
        $student->full_name = 'Budi Santoso';
        $student->student_code = 'M-2025-0042';

        $notif = new MuridOverdueNotification($student, 340000, 'Mei 2026');

        $this->assertEquals(['database'], $notif->via(null));
    }

    public function test_to_database_returns_correct_payload(): void
    {
        $student = new Student();
        $student->id = 42;
        $student->full_name = 'Budi Santoso';
        $student->student_code = 'M-2025-0042';

        $notif = new MuridOverdueNotification($student, 340000, 'Mei 2026');
        $data  = $notif->toDatabase(null);

        $this->assertEquals(42, $data['student_id']);
        $this->assertEquals('Budi Santoso', $data['student_name']);
        $this->assertEquals('M-2025-0042', $data['student_code']);
        $this->assertEquals(340000, $data['total_overdue']);
        $this->assertEquals('Mei 2026', $data['invoice_month']);
        $this->assertStringContainsString('/students/42', $data['student_url']);
    }
}
```

- [ ] **Step 2.2: Jalankan test — pastikan GAGAL**

```bash
php artisan test tests/Unit/MuridOverdueNotificationTest.php
```

Expected: FAIL — `App\Notifications\MuridOverdueNotification not found`

- [ ] **Step 2.3: Buat notification class**

Buat file `app/Notifications/MuridOverdueNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\Student;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi murid Aktif dengan tunggakan >1 bulan.
 * Dikirim ke Admin + Owner setiap tgl 1 via cron students:check-overdue.
 */
class MuridOverdueNotification extends Notification
{
    public function __construct(
        private Student $student,
        private int     $totalOverdue,
        private string  $invoiceMonth,
    ) {}

    /** Kirim hanya via database (tidak perlu email/push di Fase 1). */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** Payload yang disimpan di kolom data (JSON) tabel notifications. */
    public function toDatabase(object $notifiable): array
    {
        return [
            'student_id'    => $this->student->id,
            'student_name'  => $this->student->full_name,
            'student_code'  => $this->student->student_code,
            'total_overdue' => $this->totalOverdue,
            'invoice_month' => $this->invoiceMonth,
            'student_url'   => route('students.show', $this->student),
        ];
    }
}
```

- [ ] **Step 2.4: Jalankan test — pastikan LULUS**

```bash
php artisan test tests/Unit/MuridOverdueNotificationTest.php
```

Expected: PASS (2 tests, 7 assertions)

- [ ] **Step 2.5: Commit**

```bash
git add app/Notifications/MuridOverdueNotification.php tests/Unit/MuridOverdueNotificationTest.php
git commit -m "M05: Tambah MuridOverdueNotification + unit test"
```

---

## Task 3: Artisan Command `students:check-overdue`

**Files:**
- Create: `app/Console/Commands/CheckOverdueStudents.php`
- Create: `tests/Feature/Admin/CheckOverdueStudentsTest.php`
- Modify: `routes/console.php`

- [ ] **Step 3.1: Tulis test yang gagal**

Buat file `tests/Feature/Admin/CheckOverdueStudentsTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use App\Notifications\MuridOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CheckOverdueStudentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin',   'guard_name' => 'web']);
        Role::create(['name' => 'Owner',   'guard_name' => 'web']);
        Role::create(['name' => 'Auditor', 'guard_name' => 'web']);

        $this->admin = User::factory()->create()->assignRole('Admin');
        $this->owner = User::factory()->create()->assignRole('Owner');
    }

    /** Murid Aktif + invoice UNPAID bulan lalu → notif dikirim ke Admin dan Owner. */
    public function test_murid_aktif_dengan_tunggakan_mendapat_notifikasi(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['status' => 'Aktif']);

        // Invoice bulan lalu yang belum lunas
        $bulanLalu = now()->subMonth();
        Invoice::factory()->create([
            'student_id' => $student->id,
            'year'       => $bulanLalu->year,
            'month'      => $bulanLalu->month,
            'status'     => 'UNPAID',
        ]);

        $this->artisan('students:check-overdue')->assertSuccessful();

        // Admin dan Owner harus menerima notifikasi
        Notification::assertSentTo($this->admin, MuridOverdueNotification::class);
        Notification::assertSentTo($this->owner, MuridOverdueNotification::class);
    }

    /** Murid Aktif tanpa tunggakan → tidak ada notif. */
    public function test_murid_aktif_tanpa_tunggakan_tidak_dapat_notifikasi(): void
    {
        Notification::fake();

        Student::factory()->create(['status' => 'Aktif']);
        // Tidak ada invoice UNPAID

        $this->artisan('students:check-overdue')->assertSuccessful();

        Notification::assertNothingSent();
    }

    /** Murid bukan Aktif + invoice UNPAID → tidak ada notif. */
    public function test_murid_tidak_aktif_diabaikan(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['status' => 'Mengundurkan Diri']);
        $bulanLalu = now()->subMonth();
        Invoice::factory()->create([
            'student_id' => $student->id,
            'year'       => $bulanLalu->year,
            'month'      => $bulanLalu->month,
            'status'     => 'UNPAID',
        ]);

        $this->artisan('students:check-overdue')->assertSuccessful();

        Notification::assertNothingSent();
    }

    /** Invoice bulan ini yang belum lunas → tidak trigger (baru jatuh tempo tgl 10). */
    public function test_tunggakan_bulan_ini_tidak_trigger(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['status' => 'Aktif']);
        Invoice::factory()->create([
            'student_id' => $student->id,
            'year'       => now()->year,
            'month'      => now()->month,
            'status'     => 'UNPAID',
        ]);

        $this->artisan('students:check-overdue')->assertSuccessful();

        Notification::assertNothingSent();
    }

    /** Jalankan command dua kali → notif tidak duplikat (idempotent). */
    public function test_idempotent_tidak_kirim_notif_duplikat(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['status' => 'Aktif']);
        $bulanLalu = now()->subMonth();
        Invoice::factory()->create([
            'student_id' => $student->id,
            'year'       => $bulanLalu->year,
            'month'      => $bulanLalu->month,
            'status'     => 'UNPAID',
        ]);

        // Jalankan dua kali
        $this->artisan('students:check-overdue')->assertSuccessful();
        $this->artisan('students:check-overdue')->assertSuccessful();

        // Admin hanya terima 1 notif (bukan 2)
        Notification::assertSentToTimes($this->admin, MuridOverdueNotification::class, 1);
    }

    /** Invoice PARTIAL juga trigger notif. */
    public function test_invoice_partial_juga_trigger(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['status' => 'Aktif']);
        $bulanLalu = now()->subMonth();
        Invoice::factory()->create([
            'student_id' => $student->id,
            'year'       => $bulanLalu->year,
            'month'      => $bulanLalu->month,
            'status'     => 'PARTIAL',
        ]);

        $this->artisan('students:check-overdue')->assertSuccessful();

        Notification::assertSentTo($this->admin, MuridOverdueNotification::class);
    }
}
```

- [ ] **Step 3.2: Jalankan test — pastikan GAGAL**

```bash
php artisan test tests/Feature/Admin/CheckOverdueStudentsTest.php
```

Expected: FAIL — `Command "students:check-overdue" is not defined`

- [ ] **Step 3.3: Buat command class**

Buat file `app/Console/Commands/CheckOverdueStudents.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\User;
use App\Notifications\MuridOverdueNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deteksi murid Aktif dengan tunggakan >1 bulan dan kirim notifikasi ke Admin/Owner.
 * Dijadwalkan tgl 1 tiap bulan jam 06:05 (setelah generate-spp).
 * Idempotent: murid yang sudah punya notif pending bulan ini tidak akan dinotif ulang.
 */
class CheckOverdueStudents extends Command
{
    protected $signature   = 'students:check-overdue';
    protected $description = 'Kirim notifikasi murid Aktif dengan tunggakan >1 bulan ke Admin dan Owner';

    public function handle(): int
    {
        $today = now();

        // Query murid Aktif yang punya invoice UNPAID/PARTIAL dari bulan sebelumnya
        $overdueStudents = Student::where('status', 'Aktif')
            ->whereHas('invoices', function ($q) use ($today) {
                $q->whereIn('status', ['UNPAID', 'PARTIAL'])
                  ->where(function ($q) use ($today) {
                      // Bulan sebelumnya: bisa tahun sebelumnya atau bulan sebelumnya di tahun ini
                      $q->where('year', '<', $today->year)
                        ->orWhere(function ($q) use ($today) {
                            $q->where('year', $today->year)
                              ->where('month', '<', $today->month);
                        });
                  });
            })
            ->get();

        if ($overdueStudents->isEmpty()) {
            $this->info('Tidak ada murid dengan tunggakan >1 bulan.');
            return self::SUCCESS;
        }

        // Idempotency: ambil student_id yang sudah punya notif pending bulan ini
        $sudahDinotif = DB::table('notifications')
            ->where('type', MuridOverdueNotification::class)
            ->whereNull('read_at')
            ->whereYear('created_at', $today->year)
            ->whereMonth('created_at', $today->month)
            ->pluck('data')
            ->map(fn ($d) => json_decode($d, true)['student_id'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Filter: hanya murid yang belum dinotif bulan ini
        $muridBaru = $overdueStudents->reject(fn ($s) => in_array($s->id, $sudahDinotif));

        if ($muridBaru->isEmpty()) {
            $this->info('Semua murid overdue sudah dinotifikasi bulan ini.');
            return self::SUCCESS;
        }

        // Penerima: semua user berole Admin atau Owner
        $penerima = User::role(['Admin', 'Owner'])->get();

        $jumlah = 0;
        foreach ($muridBaru as $student) {
            // Hitung total tunggakan dari semua invoice overdue murid ini
            $totalOverdue = $student->invoices()
                ->whereIn('status', ['UNPAID', 'PARTIAL'])
                ->where(function ($q) use ($today) {
                    $q->where('year', '<', $today->year)
                      ->orWhere(function ($q) use ($today) {
                          $q->where('year', $today->year)
                            ->where('month', '<', $today->month);
                      });
                })
                ->get()
                ->sum(fn ($inv) => $inv->total_amount - $inv->paid_amount);

            $bulanLabel = Carbon::create($today->year, $today->month - 1 > 0 ? $today->month - 1 : 12, 1)
                ->translatedFormat('F Y');

            foreach ($penerima as $user) {
                $user->notify(new MuridOverdueNotification($student, (int) $totalOverdue, $bulanLabel));
            }

            $jumlah++;
            $this->line("  → Notif dikirim: {$student->full_name} ({$student->student_code})");
        }

        $this->info("Selesai: {$jumlah} murid dinotifikasi ke " . $penerima->count() . " user.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 3.4: Jalankan test — pastikan LULUS**

```bash
php artisan test tests/Feature/Admin/CheckOverdueStudentsTest.php
```

Expected: PASS (6 tests)

- [ ] **Step 3.5: Daftarkan cron di `routes/console.php`**

Buka `routes/console.php`, tambahkan setelah blok `m05-apply-late-fines`:

```php
// ===== M05: Deteksi murid overdue & notif Admin/Owner =====
// Tgl 1 tiap bulan jam 06:05 — setelah generate-spp (06:00).
// Kirim MuridOverdueNotification ke Admin + Owner untuk murid
// Aktif dengan invoice UNPAID/PARTIAL dari bulan sebelumnya.
// Idempotent: murid yang sudah dinotif bulan ini tidak dinotif ulang.
Schedule::command('students:check-overdue')
    ->monthlyOn(1, '06:05')
    ->name('m05-check-overdue-students')
    ->withoutOverlapping();
```

- [ ] **Step 3.6: Verifikasi cron terdaftar**

```bash
php artisan schedule:list
```

Expected: baris `students:check-overdue` muncul dengan jadwal `Monthly on the 1st at 06:05`.

- [ ] **Step 3.7: Jalankan semua test — pastikan tidak ada regresi**

```bash
php artisan test
```

Expected: semua test hijau.

- [ ] **Step 3.8: Commit**

```bash
git add app/Console/Commands/CheckOverdueStudents.php tests/Feature/Admin/CheckOverdueStudentsTest.php routes/console.php
git commit -m "M05: Tambah command students:check-overdue + cron tgl 1 + 6 tests"
```

---

## Task 4: NotificationController + Routes

**Files:**
- Create: `app/Http/Controllers/NotificationController.php`
- Create: `tests/Feature/Admin/NotificationControllerTest.php`
- Modify: `routes/web.php`

- [ ] **Step 4.1: Tulis test yang gagal**

Buat file `tests/Feature/Admin/NotificationControllerTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Notifications\MuridOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin',   'guard_name' => 'web']);
        Role::create(['name' => 'Owner',   'guard_name' => 'web']);
        Role::create(['name' => 'Auditor', 'guard_name' => 'web']);

        $this->admin = User::factory()->create()->assignRole('Admin');
    }

    /** Guest tidak bisa akses route notifikasi. */
    public function test_guest_tidak_bisa_mark_read(): void
    {
        $this->postJson(route('notifications.read', Str::uuid()))
             ->assertRedirect(route('login'));
    }

    /** Mark satu notifikasi sebagai dibaca. */
    public function test_mark_satu_notif_as_read(): void
    {
        // Kirim notif ke admin agar ada record di tabel
        $this->admin->notify(new MuridOverdueNotification(
            $this->makeStudent(),
            340000,
            'Mei 2026'
        ));

        $notif = $this->admin->unreadNotifications()->first();
        $this->assertNotNull($notif);

        $this->actingAs($this->admin)
             ->postJson(route('notifications.read', $notif->id))
             ->assertOk()
             ->assertJson(['success' => true]);

        $this->assertNotNull($notif->fresh()->read_at);
    }

    /** Mark semua notifikasi sebagai dibaca. */
    public function test_mark_semua_notif_as_read(): void
    {
        $student = $this->makeStudent();

        $this->admin->notify(new MuridOverdueNotification($student, 340000, 'Mei 2026'));
        $this->admin->notify(new MuridOverdueNotification($student, 450000, 'April 2026'));

        $this->assertEquals(2, $this->admin->unreadNotifications()->count());

        $this->actingAs($this->admin)
             ->postJson(route('notifications.read-all'))
             ->assertOk()
             ->assertJson(['success' => true]);

        $this->assertEquals(0, $this->admin->unreadNotifications()->count());
    }

    /** Admin tidak bisa mark notif milik user lain. */
    public function test_tidak_bisa_mark_notif_user_lain(): void
    {
        $owner = User::factory()->create()->assignRole('Owner');
        $owner->notify(new MuridOverdueNotification($this->makeStudent(), 340000, 'Mei 2026'));

        $notifMilikOwner = $owner->unreadNotifications()->first();

        $this->actingAs($this->admin)
             ->postJson(route('notifications.read', $notifMilikOwner->id))
             ->assertForbidden();
    }

    /** Helper buat student dummy tanpa simpan ke DB. */
    private function makeStudent(): \App\Models\Student
    {
        $s = new \App\Models\Student();
        $s->id           = 99;
        $s->full_name    = 'Test Murid';
        $s->student_code = 'M-2025-0099';
        return $s;
    }
}
```

- [ ] **Step 4.2: Jalankan test — pastikan GAGAL**

```bash
php artisan test tests/Feature/Admin/NotificationControllerTest.php
```

Expected: FAIL — route `notifications.read` not found.

- [ ] **Step 4.3: Buat NotificationController**

Buat file `app/Http/Controllers/NotificationController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Kelola status baca notifikasi user yang sedang login.
 * Hanya bisa mark as read notif milik sendiri.
 */
class NotificationController extends Controller
{
    /** Mark satu notifikasi sebagai sudah dibaca. */
    public function markRead(DatabaseNotification $notification): JsonResponse
    {
        // Pastikan notif ini milik user yang sedang login
        if ($notification->notifiable_id !== auth()->id()) {
            abort(403);
        }

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /** Mark semua notifikasi user yang login sebagai sudah dibaca. */
    public function markAllRead(): JsonResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }
}
```

- [ ] **Step 4.4: Tambah routes ke `routes/web.php`**

Cari blok route yang sudah ada (biasanya di dekat akhir, dalam middleware `auth`), tambahkan:

```php
// ===== Notifikasi =====
Route::middleware(['auth'])->group(function () {
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
});
```

Dan tambahkan import di atas file:

```php
use App\Http\Controllers\NotificationController;
```

- [ ] **Step 4.5: Jalankan test — pastikan LULUS**

```bash
php artisan test tests/Feature/Admin/NotificationControllerTest.php
```

Expected: PASS (4 tests)

- [ ] **Step 4.6: Jalankan semua test — pastikan tidak ada regresi**

```bash
php artisan test
```

Expected: semua test hijau.

- [ ] **Step 4.7: Commit**

```bash
git add app/Http/Controllers/NotificationController.php tests/Feature/Admin/NotificationControllerTest.php routes/web.php
git commit -m "M05: Tambah NotificationController (mark read / read-all) + 4 tests"
```

---

## Task 5: View Composer — Inject Notif ke Topbar

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 5.1: Daftarkan View Composer di `AppServiceProvider::boot()`**

Buka `app/Providers/AppServiceProvider.php`, tambahkan import dan logic di `boot()`:

```php
use App\Notifications\MuridOverdueNotification;
use Illuminate\Support\Facades\View;
```

Di dalam method `boot()`:

```php
public function boot(): void
{
    // Inject notifikasi overdue ke layout — hanya untuk user yang login.
    // Query dibatasi 10 terbaru agar dropdown tidak terlalu panjang.
    View::composer('layouts.app', function ($view) {
        if (auth()->check()) {
            $notifs = auth()->user()
                ->unreadNotifications()
                ->where('type', MuridOverdueNotification::class)
                ->latest()
                ->take(10)
                ->get();

            $view->with('overdueNotifs', $notifs);
            $view->with('overdueNotifCount', $notifs->count());
        } else {
            $view->with('overdueNotifs', collect());
            $view->with('overdueNotifCount', 0);
        }
    });
}
```

- [ ] **Step 5.2: Verifikasi tidak ada error**

```bash
php artisan config:clear && php artisan view:clear
php artisan route:list 2>&1 | head -5
```

Expected: tidak ada error.

- [ ] **Step 5.3: Jalankan semua test**

```bash
php artisan test
```

Expected: semua test hijau. (View Composer hanya jalan saat ada request HTTP, tidak mempengaruhi unit test.)

- [ ] **Step 5.4: Commit**

```bash
git add app/Providers/AppServiceProvider.php
git commit -m "M05: Daftarkan View Composer notif overdue di AppServiceProvider"
```

---

## Task 6: Topbar UI — Bell Badge + Dropdown Alpine.js

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 6.1: Tambah bell badge + dropdown ke topbar**

Buka `resources/views/layouts/app.blade.php`. Cari baris ini (sekitar line 92):

```blade
{{-- Kanan: Tanggal + Avatar + Toggle tema + Keluar --}}
<div class="flex items-center gap-3 ml-auto">
```

Tambahkan blok bell notification **setelah** `<span class="hidden sm:block ...">` (tanggal) dan **sebelum** avatar. Sehingga urutan menjadi: Tanggal → Bell → Avatar → Toggle → Keluar.

Ganti bagian `{{-- Kanan: ... --}}` menjadi:

```blade
{{-- Kanan: Tanggal + Bell Notif + Avatar + Toggle tema + Keluar --}}
<div class="flex items-center gap-3 ml-auto">
    <span class="hidden sm:block text-xs text-mk-dim">
        {{ now()->translatedFormat('l, j F Y') }}
    </span>

    {{-- Bell Notifikasi Auto-Mundur (hanya Admin & Owner) --}}
    @if(auth()->check() && auth()->user()->hasAnyRole(['Admin', 'Owner']) && $overdueNotifCount > 0)
    <div class="relative" x-data="{ terbuka: false }" @click.away="terbuka = false">

        {{-- Tombol Bell --}}
        <button @click="terbuka = !terbuka"
                class="relative flex items-center justify-center w-8 h-8 rounded-lg
                       border border-mk-border bg-mk-accentDim
                       hover:border-mk-accent transition-colors text-sm"
                :class="terbuka ? 'border-mk-accent' : ''"
                title="Notifikasi auto-mundur">
            🔔
            <span class="absolute -top-1 -right-1 flex items-center justify-center
                         min-w-[16px] h-4 px-1 rounded-full bg-red-500
                         text-white text-[9px] font-bold border-2 border-mk-sidebar">
                {{ $overdueNotifCount }}
            </span>
        </button>

        {{-- Dropdown --}}
        <div x-show="terbuka"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             x-cloak
             class="absolute right-0 top-full mt-2 w-80 z-50
                    bg-mk-card border border-mk-border rounded-xl
                    shadow-[0_12px_40px_rgba(0,0,0,0.55)] overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3
                        border-b border-mk-border bg-mk-accentDim/30">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-mk-accent">Konfirmasi Auto-Mundur</span>
                    <span class="text-[10px] font-bold text-red-400 bg-red-500/15
                                 px-2 py-0.5 rounded-full">
                        {{ $overdueNotifCount }}
                    </span>
                </div>
                {{-- Tombol mark all read --}}
                <button onclick="markAllRead(this)"
                        class="text-[10px] text-mk-dim hover:text-mk-muted transition-colors">
                    Tandai semua dibaca
                </button>
            </div>

            {{-- List notifikasi --}}
            <div class="max-h-72 overflow-y-auto">
                @foreach($overdueNotifs as $notif)
                @php $d = $notif->data; @endphp
                <div class="flex items-start gap-3 px-4 py-3
                            border-b border-mk-border/50 last:border-0
                            hover:bg-mk-cardHover transition-colors">
                    <div class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0 mt-1.5"></div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-mk-text truncate">
                            {{ $d['student_name'] }}
                        </div>
                        <div class="text-[11px] text-mk-muted mt-0.5">
                            Tunggakan {{ $d['invoice_month'] }} ·
                            <span class="text-red-400 font-medium">
                                Rp {{ number_format($d['total_overdue'], 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                    <a href="{{ $d['student_url'] }}"
                       onclick="markRead('{{ $notif->id }}', this)"
                       class="flex-shrink-0 text-[11px] text-mk-accent font-medium
                              px-2 py-1 rounded border border-mk-border
                              hover:bg-mk-accentDim transition-colors">
                        Tinjau →
                    </a>
                </div>
                @endforeach
            </div>

            {{-- Footer --}}
            <div class="px-4 py-2 border-t border-mk-border">
                <p class="text-[10px] text-mk-dim text-center">
                    Klik Tinjau → halaman murid → klik Mundurkan
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- Avatar inisial (nama tampil di sidebar kiri bawah) --}}
    <div class="w-7 h-7 rounded-full bg-mk-accentDim flex items-center
                justify-center text-xs font-bold text-mk-accent shrink-0">
        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
    </div>

    {{-- Tombol toggle tema gelap/terang --}}
    <button @click="toggleTheme()"
            class="text-mk-dim hover:text-mk-muted transition-colors p-1.5 rounded hover:bg-white/5 text-sm leading-none"
            :title="theme === 'dark' ? 'Beralih ke tema terang' : 'Beralih ke tema gelap'">
        <span x-text="theme === 'dark' ? '☀️' : '🌙'">☀️</span>
    </button>

    {{-- Tombol keluar --}}
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit"
                class="text-xs text-mk-dim hover:text-mk-muted transition-colors
                       px-2 py-1 rounded hover:bg-white/5">
            Keluar
        </button>
    </form>
</div>
```

- [ ] **Step 6.2: Tambah JavaScript helper untuk mark read**

Tambahkan di dalam `@stack('scripts')` atau sebelum `</body>` di `app.blade.php`:

```blade
<script>
// Mark satu notifikasi sebagai dibaca lalu redirect ke halaman murid
function markRead(notifId, linkEl) {
    fetch(`/notifications/${notifId}/read`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    }).catch(() => {}); // fire-and-forget — redirect tetap jalan
    // Biarkan browser follow link href murid
}

// Mark semua notifikasi sebagai dibaca dan reload halaman
function markAllRead(btn) {
    btn.disabled = true;
    fetch('/notifications/read-all', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    }).then(() => window.location.reload());
}
</script>
```

- [ ] **Step 6.3: Build assets**

```bash
npm run build
```

Expected: build sukses tanpa error.

- [ ] **Step 6.4: Jalankan semua test**

```bash
php artisan test
```

Expected: semua test hijau.

- [ ] **Step 6.5: Verifikasi manual di browser**

1. Login sebagai Admin atau Owner
2. Jalankan command manual untuk buat notif test:
```bash
php artisan tinker
```
```php
// Di tinker — buat notif test
use App\Models\Student;
use App\Models\User;
use App\Notifications\MuridOverdueNotification;

$student = Student::first(); // ambil murid pertama
$user = User::first();       // ambil user pertama
$s = new Student();
$s->id = $student->id;
$s->full_name = $student->full_name;
$s->student_code = $student->student_code;
$user->notify(new MuridOverdueNotification($s, 340000, 'Mei 2026'));
```
3. Refresh halaman — bell badge merah harus muncul di topbar
4. Klik bell — dropdown terbuka dengan nama murid + nominal
5. Klik "Tinjau →" — redirect ke halaman murid, badge berkurang
6. Klik "Tandai semua dibaca" — badge hilang, halaman reload

- [ ] **Step 6.6: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "M05: Tambah bell notif auto-mundur di topbar + dropdown Alpine.js"
```

---

## Task 7: Push & Final Check

- [ ] **Step 7.1: Jalankan seluruh test suite**

```bash
php artisan test
```

Expected: semua test hijau, tidak ada regresi.

- [ ] **Step 7.2: Cek schedule**

```bash
php artisan schedule:list
```

Expected: `students:check-overdue` terdaftar `Monthly on the 1st at 06:05`.

- [ ] **Step 7.3: Push ke GitHub**

```bash
git push origin main
```

---

## Self-Review

**Spec coverage:**
- ✅ Trigger: murid Aktif + invoice UNPAID/PARTIAL bulan sebelumnya
- ✅ Mekanisme: Laravel Database Notifications
- ✅ Jadwal cron: tgl 1 jam 06:05
- ✅ Penerima: Admin + Owner
- ✅ UI: bell topbar + badge + dropdown Alpine.js
- ✅ Idempotency: guard duplikat notif per bulan per murid
- ✅ Mark read: satu notif + semua notif
- ✅ Ownership check: tidak bisa mark notif user lain
- ✅ Testing: 6 test command + 4 test controller + 2 test unit

**Placeholder check:** Tidak ada TBD/TODO.

**Type consistency:**
- `MuridOverdueNotification` dipanggil konsisten di semua task
- `overdueNotifs` / `overdueNotifCount` konsisten antara Composer (Task 5) dan Blade (Task 6)
- Route name `notifications.read` / `notifications.read-all` konsisten antara Task 4 dan Task 6
