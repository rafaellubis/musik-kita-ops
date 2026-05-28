# Guru Login Feature Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengimplementasikan fitur login untuk 18 guru — role Guru, dashboard minimal mobile-first, input absensi hari ini, lihat jadwal minggu ini+depan, dan slip honor read-only.

**Architecture:** Opsi A — role `Guru` (Spatie Permission) + FK `user_id` nullable di tabel `teachers`. Artisan command `guru:create-accounts` generate akun dari data guru aktif. GuruController terpisah dengan route group `prefix('guru')`. Layout `layouts/guru.blade.php` dengan bottom navigation bar di mobile, sidebar di desktop.

**Tech Stack:** Laravel 11, MySQL, Blade + Tailwind CSS, Alpine.js, Spatie Permission v6.

---

## Keputusan Desain

| Aspek | Keputusan |
|-------|-----------|
| Ganti password | Via `/profile` Breeze yang sudah ada |
| Notifikasi | Tidak ada — guru cek manual |
| Mobile layout | Bottom nav bar (Dashboard / Jadwal / Honor); desktop tetap sidebar |
| Kartu sesi | Kartu besar dengan tombol HADIR/TERLAMBAT full-width (mudah di-tap) |
| Aksen warna | Tetap mint `mk-accent` (#5DB890) — sama dengan sistem |
| Badge status | HADIR=hijau, HADIR_TERLAMBAT=kuning, SCHEDULED=abu, LIBUR=ungu, HANGUS=merah, IZIN=orange |
| Slip honor detail | Halaman terpisah `guru/honor-show.blade.php` (read-only) |
| Scope jadwal | Minggu ini + minggu depan |
| Guru pengganti | Bisa input absensi via `substitute_teacher_id` check |
| Guru tanpa email | Generate dummy `namalowercase@musikkita.local` |
| Proteksi `/teachers/*` | Guard `role:Owner|Admin|Auditor` ditambah sebagai bagian task ini |

---

## File Map

| File | Aksi | Keterangan |
|------|------|------------|
| `database/migrations/2026_05_28_100000_add_user_id_to_teachers.php` | Create | FK user_id nullable di teachers |
| `app/Models/Teacher.php` | Modify | Tambah `user()` BelongsTo + user_id ke fillable |
| `app/Models/User.php` | Modify | Tambah `teacher()` HasOne |
| `database/seeders/RoleSeeder.php` | Modify | Tambah role 'Guru' |
| `app/Console/Commands/GuruCreateAccounts.php` | Create | Artisan command buat akun guru |
| `app/Http/Controllers/GuruController.php` | Create | dashboard/jadwal/honor/honorShow/updateAbsensi |
| `app/View/Components/GuruLayout.php` | Create | Blade component untuk layout guru |
| `routes/web.php` | Modify | Guru route group + pastikan /teachers/* terlindungi |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Modify | Redirect by role setelah login |
| `resources/views/layouts/guru.blade.php` | Create | Layout dengan sidebar desktop + bottom nav mobile |
| `resources/views/guru/_badge-status.blade.php` | Create | Partial badge warna per status sesi |
| `resources/views/guru/dashboard.blade.php` | Create | Dashboard — sesi hari ini sebagai kartu + ringkasan |
| `resources/views/guru/jadwal.blade.php` | Create | Jadwal 2 minggu — kartu mobile, tabel desktop |
| `resources/views/guru/honor.blade.php` | Create | List slip honor CALCULATED/PAID |
| `resources/views/guru/honor-show.blade.php` | Create | Detail slip honor read-only |
| `tests/Feature/GuruModelRelationTest.php` | Create | Test relasi Teacher ↔ User |
| `tests/Feature/GuruCreateAccountsCommandTest.php` | Create | Test artisan command |
| `tests/Feature/GuruControllerAccessTest.php` | Create | Test akses route + guard teachers |
| `tests/Feature/GuruUpdateAbsensiTest.php` | Create | Test business rule updateAbsensi |
| `tests/Feature/GuruLoginRedirectTest.php` | Create | Test redirect setelah login |

---

## Task 1: Migration + Model Relations + RoleSeeder

**Files:**
- Create: `database/migrations/2026_05_28_100000_add_user_id_to_teachers.php`
- Modify: `app/Models/Teacher.php`
- Modify: `app/Models/User.php`
- Modify: `database/seeders/RoleSeeder.php`
- Test: `tests/Feature/GuruModelRelationTest.php`

- [ ] **Step 1: Tulis test yang gagal**

Simpan ke `tests/Feature/GuruModelRelationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruModelRelationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);
    }

    public function test_teacher_dapat_terhubung_ke_user(): void
    {
        $user    = User::factory()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $teacher->user->id);
    }

    public function test_user_dapat_akses_teacher(): void
    {
        $user    = User::factory()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($teacher->id, $user->teacher->id);
    }

    public function test_user_id_nullable(): void
    {
        $teacher = Teacher::factory()->create(['user_id' => null]);
        $this->assertNull($teacher->user);
    }

    public function test_role_guru_ada(): void
    {
        $this->assertDatabaseHas('roles', ['name' => 'Guru']);
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test tests/Feature/GuruModelRelationTest.php
```

Expected: FAIL — kolom `user_id` belum ada di tabel teachers.

- [ ] **Step 3: Buat migration**

Simpan ke `database/migrations/2026_05_28_100000_add_user_id_to_teachers.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->foreignId('user_id')
                  ->nullable()
                  ->after('notes')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
```

- [ ] **Step 4: Update `app/Models/Teacher.php`**

Tambahkan `'user_id'` ke `$fillable`:
```php
protected $fillable = [
    'code', 'name', 'email', 'phone', 'bank_name', 'bank_account',
    'bank_account_holder', 'joined_date', 'is_active', 'notes', 'user_id',
];
```

Tambahkan import `use Illuminate\Database\Eloquent\Relations\BelongsTo;` di bagian atas.

Tambahkan relasi setelah method `classSessions()`:
```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

- [ ] **Step 5: Update `app/Models/User.php`**

Tambahkan import:
```php
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Relations\HasOne;
```

Tambahkan method setelah `casts()`:
```php
public function teacher(): HasOne
{
    return $this->hasOne(Teacher::class);
}
```

- [ ] **Step 6: Update `database/seeders/RoleSeeder.php`**

```php
public function run(): void
{
    Role::firstOrCreate(['name' => 'Owner']);
    Role::firstOrCreate(['name' => 'Admin']);
    Role::firstOrCreate(['name' => 'Auditor']);
    Role::firstOrCreate(['name' => 'Guru']);
}
```

- [ ] **Step 7: Jalankan migration + test**

```bash
php artisan migrate
php artisan test tests/Feature/GuruModelRelationTest.php
```

Expected: 4 tests PASS.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_05_28_100000_add_user_id_to_teachers.php \
        app/Models/Teacher.php app/Models/User.php \
        database/seeders/RoleSeeder.php \
        tests/Feature/GuruModelRelationTest.php
git commit -m "M-Guru: Migration user_id teachers + relasi User<->Teacher + role Guru"
```

---

## Task 2: Artisan Command `guru:create-accounts`

**Files:**
- Create: `app/Console/Commands/GuruCreateAccounts.php`
- Test: `tests/Feature/GuruCreateAccountsCommandTest.php`

- [ ] **Step 1: Tulis test yang gagal**

Simpan ke `tests/Feature/GuruCreateAccountsCommandTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruCreateAccountsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);
    }

    public function test_buat_akun_untuk_guru_aktif(): void
    {
        Teacher::factory()->create([
            'name'      => 'THOMAS',
            'email'     => 'thomas@gmail.com',
            'user_id'   => null,
            'is_active' => true,
        ]);

        $this->artisan('guru:create-accounts')->assertSuccessful();

        $teacher = Teacher::first();
        $this->assertNotNull($teacher->user_id);
        $this->assertEquals('thomas@gmail.com', $teacher->user->email);
        $this->assertTrue($teacher->user->hasRole('Guru'));
    }

    public function test_generate_email_dummy_jika_email_null(): void
    {
        Teacher::factory()->create([
            'name'      => 'THOMAS',
            'email'     => null,
            'user_id'   => null,
            'is_active' => true,
        ]);

        $this->artisan('guru:create-accounts')->assertSuccessful();

        $this->assertEquals('thomas@musikkita.local', Teacher::first()->user->email);
    }

    public function test_skip_guru_yang_sudah_punya_akun(): void
    {
        $existingUser = User::factory()->create();
        Teacher::factory()->create(['user_id' => $existingUser->id, 'is_active' => true]);

        $this->artisan('guru:create-accounts')->assertSuccessful();

        // tidak ada user baru dibuat
        $this->assertEquals(1, User::count());
    }

    public function test_skip_guru_nonaktif(): void
    {
        Teacher::factory()->create(['is_active' => false, 'user_id' => null]);

        $this->artisan('guru:create-accounts')->assertSuccessful();

        $this->assertEquals(0, User::count());
    }

    public function test_password_format_nama_lowercase_tanpa_spasi(): void
    {
        Teacher::factory()->create([
            'name'      => 'T. HADI',
            'email'     => 'hadi@gmail.com',
            'user_id'   => null,
            'is_active' => true,
        ]);

        $this->artisan('guru:create-accounts');

        $this->assertTrue(Hash::check('t.hadi', User::first()->password));
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test tests/Feature/GuruCreateAccountsCommandTest.php
```

Expected: FAIL — command belum ada.

- [ ] **Step 3: Generate command skeleton**

```bash
php artisan make:command GuruCreateAccounts
```

- [ ] **Step 4: Isi `app/Console/Commands/GuruCreateAccounts.php`**

```php
<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuruCreateAccounts extends Command
{
    protected $signature   = 'guru:create-accounts';
    protected $description = 'Buat akun User untuk semua guru aktif yang belum punya akun login';

    public function handle(): int
    {
        $teachers = Teacher::where('is_active', true)->whereNull('user_id')->get();

        if ($teachers->isEmpty()) {
            $this->info('Semua guru aktif sudah punya akun login.');
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($teachers as $teacher) {
            $namaSlug = Str::lower(str_replace(' ', '', $teacher->name));

            // Gunakan email guru jika ada, generate dummy jika tidak
            $email = $teacher->email ?? "{$namaSlug}@musikkita.local";

            // Hindari duplikat email dummy
            if (User::where('email', $email)->exists()) {
                $email = "{$namaSlug}.{$teacher->id}@musikkita.local";
            }

            $password = $namaSlug;

            $user = User::create([
                'name'              => $teacher->name,
                'email'             => $email,
                'password'          => Hash::make($password),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('Guru');
            $teacher->update(['user_id' => $user->id]);

            $rows[] = [$teacher->name, $email, $password];
        }

        $this->table(['Nama Guru', 'Email Login', 'Password Awal'], $rows);
        $this->info(count($rows) . ' akun berhasil dibuat.');
        $this->warn('PENTING: Simpan daftar di atas dan bagikan ke masing-masing guru.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Jalankan test**

```bash
php artisan test tests/Feature/GuruCreateAccountsCommandTest.php
```

Expected: 5 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/GuruCreateAccounts.php \
        tests/Feature/GuruCreateAccountsCommandTest.php
git commit -m "M-Guru: Artisan command guru:create-accounts"
```

---

## Task 3: GuruController + Routes + Access Control

**Files:**
- Create: `app/Http/Controllers/GuruController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/GuruControllerAccessTest.php`

- [ ] **Step 1: Tulis test yang gagal**

Simpan ke `tests/Feature/GuruControllerAccessTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruControllerAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Guru', 'Owner', 'Admin', 'Auditor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->guruUser = User::factory()->create(['email_verified_at' => now()]);
        $this->guruUser->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $this->guruUser->id]);
    }

    public function test_guru_akses_dashboard(): void
    {
        $this->actingAs($this->guruUser)->get('/guru/dashboard')->assertOk();
    }

    public function test_guru_akses_jadwal(): void
    {
        $this->actingAs($this->guruUser)->get('/guru/jadwal')->assertOk();
    }

    public function test_guru_akses_honor(): void
    {
        $this->actingAs($this->guruUser)->get('/guru/honor')->assertOk();
    }

    public function test_owner_tidak_bisa_akses_guru_routes(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $owner->assignRole('Owner');

        $this->actingAs($owner)->get('/guru/dashboard')->assertForbidden();
    }

    public function test_guru_tidak_bisa_akses_dashboard_admin(): void
    {
        $this->actingAs($this->guruUser)->get('/dashboard')->assertForbidden();
    }

    public function test_guru_tidak_bisa_akses_teachers_index(): void
    {
        $this->actingAs($this->guruUser)->get('/teachers')->assertForbidden();
    }

    public function test_unauthenticated_redirect_ke_login(): void
    {
        $this->get('/guru/dashboard')->assertRedirect('/login');
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test tests/Feature/GuruControllerAccessTest.php
```

Expected: FAIL — route `/guru/dashboard` belum ada.

- [ ] **Step 3: Buat `app/Http/Controllers/GuruController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\HonorSlip;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuruController extends Controller
{
    /**
     * Dashboard guru — sesi hari ini + ringkasan bulan ini.
     */
    public function dashboard()
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403, 'Akun ini tidak terhubung ke data guru.');

        $today = today()->toDateString();

        $sesiHariIni = ClassSession::where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                  ->orWhere('substitute_teacher_id', $teacher->id);
            })
            ->where('session_date', $today)
            ->whereNotIn('status', ['CANCELLED'])
            ->with(['student', 'room', 'enrollment.package'])
            ->orderBy('start_time')
            ->get();

        $startBulan = now()->startOfMonth()->toDateString();
        $endBulan   = now()->endOfMonth()->toDateString();

        $totalSesiBulan = ClassSession::where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                  ->orWhere('substitute_teacher_id', $teacher->id);
            })
            ->whereBetween('session_date', [$startBulan, $endBulan])
            ->whereNotIn('status', ['CANCELLED', 'LIBUR', 'SCHEDULED'])
            ->count();

        $slipBulanIni = HonorSlip::where('teacher_id', $teacher->id)
            ->whereIn('status', ['CALCULATED', 'PAID'])
            ->where('month', now()->month)
            ->where('year', now()->year)
            ->first();

        return view('guru.dashboard', compact('teacher', 'sesiHariIni', 'totalSesiBulan', 'slipBulanIni'));
    }

    /**
     * Jadwal minggu ini + minggu depan.
     */
    public function jadwal()
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);

        $mulai = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $akhir = now()->addWeek()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $sesi = ClassSession::where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                  ->orWhere('substitute_teacher_id', $teacher->id);
            })
            ->whereBetween('session_date', [$mulai, $akhir])
            ->with(['student', 'room', 'enrollment.package'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        $today = today()->toDateString();

        return view('guru.jadwal', compact('teacher', 'sesi', 'today', 'mulai', 'akhir'));
    }

    /**
     * List slip honor CALCULATED atau PAID milik guru yang login.
     */
    public function honor()
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);

        $slips = HonorSlip::where('teacher_id', $teacher->id)
            ->whereIn('status', ['CALCULATED', 'PAID'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        return view('guru.honor', compact('teacher', 'slips'));
    }

    /**
     * Detail slip honor satu bulan — read-only.
     */
    public function honorShow(HonorSlip $honorSlip)
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);
        abort_if($honorSlip->teacher_id !== $teacher->id, 403, 'Bukan slip honor Anda.');
        abort_if(!in_array($honorSlip->status, ['CALCULATED', 'PAID']), 403, 'Slip honor belum tersedia.');

        $sesi = ClassSession::where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                  ->orWhere('substitute_teacher_id', $teacher->id);
            })
            ->whereYear('session_date', $honorSlip->year)
            ->whereMonth('session_date', $honorSlip->month)
            ->whereNotNull('honor_code')
            ->with(['student', 'room'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return view('guru.honor-show', compact('teacher', 'honorSlip', 'sesi'));
    }

    /**
     * Update status absensi — hanya HADIR/HADIR_TERLAMBAT, hanya sesi hari ini, hanya milik guru sendiri.
     */
    public function updateAbsensi(Request $request, ClassSession $classSession)
    {
        $teacher = auth()->user()->teacher;
        abort_if(!$teacher, 403);

        // Validasi kepemilikan: guru asli atau guru pengganti
        abort_if(
            $classSession->teacher_id !== $teacher->id
            && $classSession->substitute_teacher_id !== $teacher->id,
            403,
            'Bukan sesi Anda.'
        );

        // Validasi hari: hanya sesi hari ini
        abort_if(
            $classSession->session_date !== today()->toDateString(),
            403,
            'Hanya sesi hari ini yang bisa diupdate.'
        );

        $validated = $request->validate([
            'status'       => ['required', Rule::in(['HADIR', 'HADIR_TERLAMBAT'])],
            'late_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
        ], [
            'status.required' => 'Status wajib diisi.',
            'status.in'       => 'Status hanya boleh HADIR atau HADIR TERLAMBAT.',
            'late_minutes.integer' => 'Menit keterlambatan harus berupa angka.',
        ]);

        $classSession->update([
            'status'       => $validated['status'],
            'late_minutes' => $validated['status'] === 'HADIR_TERLAMBAT'
                ? ($validated['late_minutes'] ?? null)
                : null,
        ]);

        return back()->with('success', 'Absensi berhasil disimpan.');
    }
}
```

- [ ] **Step 4: Tambah import + routes di `routes/web.php`**

Tambahkan import di bagian atas bersama import lainnya:
```php
use App\Http\Controllers\GuruController;
```

Tambahkan route guru sebelum `require __DIR__.'/auth.php';`:
```php
// ===== GURU ROUTES =====
Route::middleware(['auth', 'verified', 'role:Guru'])
    ->prefix('guru')
    ->name('guru.')
    ->group(function () {
        Route::get('/dashboard',                     [GuruController::class, 'dashboard'])->name('dashboard');
        Route::get('/jadwal',                        [GuruController::class, 'jadwal'])->name('jadwal');
        Route::get('/honor',                         [GuruController::class, 'honor'])->name('honor');
        Route::get('/honor/{honorSlip}',             [GuruController::class, 'honorShow'])->name('honor.show');
        Route::patch('/sesi/{classSession}/absensi', [GuruController::class, 'updateAbsensi'])->name('absensi.update');
    });
```

- [ ] **Step 5: Pastikan `/teachers` terlindungi dari role Guru**

Periksa group yang membungkus `Route::resource('teachers', ...)` di `routes/web.php`. Route ini ada di dua tempat (baris ~92 dan ~326). Pastikan keduanya berada dalam group yang menggunakan `role:Owner|Admin` atau `role:Owner|Admin|Auditor` — tidak ada yang bisa diakses Guru.

Jika group sudah ada `role:Owner|Admin` atau `role:Owner|Admin|Auditor`, tidak perlu perubahan — role Guru tidak termasuk sehingga otomatis 403.

- [ ] **Step 6: Jalankan test**

```bash
php artisan test tests/Feature/GuruControllerAccessTest.php
```

Expected: 7 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/GuruController.php \
        routes/web.php \
        tests/Feature/GuruControllerAccessTest.php
git commit -m "M-Guru: GuruController + routes + akses kontrol"
```

---

## Task 4: `updateAbsensi` Business Logic Tests

**Files:**
- Test: `tests/Feature/GuruUpdateAbsensiTest.php`

- [ ] **Step 1: Tulis test**

Simpan ke `tests/Feature/GuruUpdateAbsensiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Instrument;
use App\Models\Package;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruUpdateAbsensiTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;
    private Teacher $teacher;
    private ClassSession $sesiHariIni;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);

        $this->guruUser = User::factory()->create(['email_verified_at' => now()]);
        $this->guruUser->assignRole('Guru');
        $this->teacher = Teacher::factory()->create(['user_id' => $this->guruUser->id]);

        $instr   = Instrument::factory()->create(['name' => 'Piano', 'code' => 'PIANO']);
        $package = Package::factory()->create([
            'class_type'   => 'REGULER',
            'instrument_id' => $instr->id,
            'duration_min' => 30,
        ]);
        $student  = Student::factory()->create();
        $room     = Room::factory()->create();
        $enroll   = Enrollment::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'package_id' => $package->id,
            'status'     => 'ACTIVE',
        ]);
        $schedule = Schedule::factory()->create([
            'enrollment_id' => $enroll->id,
            'day_of_week'   => now()->dayOfWeek,
            'room_id'       => $room->id,
        ]);

        $this->sesiHariIni = ClassSession::factory()->create([
            'schedule_id'   => $schedule->id,
            'enrollment_id' => $enroll->id,
            'student_id'    => $student->id,
            'teacher_id'    => $this->teacher->id,
            'session_date'  => today()->toDateString(),
            'status'        => 'SCHEDULED',
        ]);
    }

    public function test_guru_bisa_set_hadir(): void
    {
        $this->actingAs($this->guruUser)
            ->patch(route('guru.absensi.update', $this->sesiHariIni), ['status' => 'HADIR'])
            ->assertRedirect();

        $this->assertEquals('HADIR', $this->sesiHariIni->fresh()->status);
    }

    public function test_guru_bisa_set_hadir_terlambat_dengan_menit(): void
    {
        $this->actingAs($this->guruUser)
            ->patch(route('guru.absensi.update', $this->sesiHariIni), [
                'status'       => 'HADIR_TERLAMBAT',
                'late_minutes' => 10,
            ])
            ->assertRedirect();

        $sesi = $this->sesiHariIni->fresh();
        $this->assertEquals('HADIR_TERLAMBAT', $sesi->status);
        $this->assertEquals(10, $sesi->late_minutes);
    }

    public function test_guru_pengganti_bisa_input_absensi(): void
    {
        $guruLain = Teacher::factory()->create();
        $sesiPengganti = ClassSession::factory()->create([
            'teacher_id'            => $guruLain->id,
            'substitute_teacher_id' => $this->teacher->id,
            'student_id'            => $this->sesiHariIni->student_id,
            'session_date'          => today()->toDateString(),
            'status'                => 'SCHEDULED',
        ]);

        $this->actingAs($this->guruUser)
            ->patch(route('guru.absensi.update', $sesiPengganti), ['status' => 'HADIR'])
            ->assertRedirect();

        $this->assertEquals('HADIR', $sesiPengganti->fresh()->status);
    }

    public function test_guru_tidak_bisa_update_sesi_guru_lain(): void
    {
        $guruLain   = Teacher::factory()->create();
        $sesiLain   = ClassSession::factory()->create([
            'teacher_id'   => $guruLain->id,
            'session_date' => today()->toDateString(),
            'status'       => 'SCHEDULED',
        ]);

        $this->actingAs($this->guruUser)
            ->patch(route('guru.absensi.update', $sesiLain), ['status' => 'HADIR'])
            ->assertForbidden();
    }

    public function test_guru_tidak_bisa_update_sesi_kemarin(): void
    {
        $sesiKemarin = ClassSession::factory()->create([
            'teacher_id'   => $this->teacher->id,
            'session_date' => today()->subDay()->toDateString(),
            'status'       => 'SCHEDULED',
        ]);

        $this->actingAs($this->guruUser)
            ->patch(route('guru.absensi.update', $sesiKemarin), ['status' => 'HADIR'])
            ->assertForbidden();
    }

    public function test_guru_tidak_bisa_set_status_izin(): void
    {
        $this->actingAs($this->guruUser)
            ->patch(route('guru.absensi.update', $this->sesiHariIni), ['status' => 'IZIN_RESCHEDULE'])
            ->assertUnprocessable();
    }

    public function test_late_minutes_di_reset_jika_status_hadir(): void
    {
        $this->actingAs($this->guruUser)
            ->patch(route('guru.absensi.update', $this->sesiHariIni), [
                'status'       => 'HADIR',
                'late_minutes' => 5,
            ])
            ->assertRedirect();

        $this->assertNull($this->sesiHariIni->fresh()->late_minutes);
    }
}
```

- [ ] **Step 2: Jalankan test**

```bash
php artisan test tests/Feature/GuruUpdateAbsensiTest.php
```

Expected: 7 tests PASS (logic sudah ada di GuruController Task 3).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/GuruUpdateAbsensiTest.php
git commit -m "M-Guru: Test updateAbsensi — 7 business rule tests"
```

---

## Task 5: Login Redirect by Role

**Files:**
- Modify: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Test: `tests/Feature/GuruLoginRedirectTest.php`

- [ ] **Step 1: Tulis test yang gagal**

Simpan ke `tests/Feature/GuruLoginRedirectTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuruLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Guru', 'Owner', 'Admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_guru_redirect_ke_guru_dashboard(): void
    {
        $user = User::factory()->create([
            'email'             => 'guru@test.com',
            'password'          => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $user->id]);

        $this->post('/login', ['email' => 'guru@test.com', 'password' => 'password'])
             ->assertRedirect('/guru/dashboard');
    }

    public function test_owner_redirect_ke_dashboard(): void
    {
        $user = User::factory()->create([
            'email'             => 'owner@test.com',
            'password'          => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Owner');

        $this->post('/login', ['email' => 'owner@test.com', 'password' => 'password'])
             ->assertRedirect('/dashboard');
    }

    public function test_admin_redirect_ke_dashboard(): void
    {
        $user = User::factory()->create([
            'email'             => 'admin@test.com',
            'password'          => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Admin');

        $this->post('/login', ['email' => 'admin@test.com', 'password' => 'password'])
             ->assertRedirect('/dashboard');
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan FAIL**

```bash
php artisan test tests/Feature/GuruLoginRedirectTest.php
```

Expected: `test_guru_redirect_ke_guru_dashboard` FAIL — redirect ke `/dashboard` bukan `/guru/dashboard`.

- [ ] **Step 3: Edit `app/Http/Controllers/Auth/AuthenticatedSessionController.php`**

Ganti method `store()`:

```php
public function store(LoginRequest $request): RedirectResponse
{
    $request->authenticate();
    $request->session()->regenerate();

    // Guru diarahkan ke area mereka sendiri
    if (auth()->user()->hasRole('Guru')) {
        return redirect()->intended(route('guru.dashboard', absolute: false));
    }

    return redirect()->intended(route('dashboard', absolute: false));
}
```

- [ ] **Step 4: Jalankan test**

```bash
php artisan test tests/Feature/GuruLoginRedirectTest.php
```

Expected: 3 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Auth/AuthenticatedSessionController.php \
        tests/Feature/GuruLoginRedirectTest.php
git commit -m "M-Guru: Redirect login berdasarkan role"
```

---

## Task 6: Layout + Blade Component

**Files:**
- Create: `app/View/Components/GuruLayout.php`
- Create: `resources/views/layouts/guru.blade.php`
- Create: `resources/views/guru/_badge-status.blade.php`

- [ ] **Step 1: Buat Blade component class**

Simpan ke `app/View/Components/GuruLayout.php`:

```php
<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class GuruLayout extends Component
{
    public function __construct(public string $title = 'Dashboard')
    {
    }

    public function render(): View|Closure|string
    {
        return view('layouts.guru');
    }
}
```

- [ ] **Step 2: Buat `resources/views/layouts/guru.blade.php`**

Layout mobile-first: sidebar di desktop (`lg:`), bottom navigation bar di mobile. Warna tetap menggunakan token `mk-*` sistem.

```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — {{ config('app.name', 'Musik KITA') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:300,400,500,600,700|playfair-display:600,700&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-mk-bg text-mk-text">

<div class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: false }">

    {{-- ===== SIDEBAR (Desktop) ===== --}}
    <aside class="hidden lg:flex fixed inset-y-0 left-0 z-30 w-56 bg-mk-sidebar flex-col shrink-0 border-r border-white/[0.06]">

        <div class="px-4 py-3 border-b border-white/[0.06] shrink-0">
            <img src="{{ asset('images/logo-musikkita-dark-mode.PNG') }}" alt="Musik KITA"
                 class="h-10 w-full object-contain object-left" style="max-width:160px">
        </div>

        <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5 text-[13px]">
            <div class="px-2 pt-1 pb-1.5 text-[10px] font-semibold tracking-widest text-white/40 uppercase">Menu Guru</div>

            <x-sidebar-item route="guru.dashboard" icon="🏠" label="Dashboard"
                :active="request()->routeIs('guru.dashboard')" />
            <x-sidebar-item route="guru.jadwal" icon="📅" label="Jadwal Saya"
                :active="request()->routeIs('guru.jadwal')" />
            <x-sidebar-item route="guru.honor" icon="💰" label="Slip Honor"
                :active="request()->routeIs('guru.honor*')" />
        </nav>

        <div class="shrink-0 px-3 py-3 border-t border-white/[0.06]">
            <div class="text-[11px] text-white/40 mb-2 truncate">{{ auth()->user()->name }}</div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full text-left text-[12px] text-white/50 hover:text-white/80 px-2 py-1 rounded transition-colors">
                    ← Keluar
                </button>
            </form>
        </div>
    </aside>

    {{-- ===== AREA KONTEN ===== --}}
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden lg:ml-56">

        {{-- Topbar --}}
        <div class="relative z-20 shrink-0 h-14 bg-mk-sidebar border-b border-white/[0.06] flex items-center px-4 lg:px-6 gap-3">
            <span class="text-white/90 font-semibold text-sm lg:text-base flex-1">{{ $title }}</span>
            <span class="lg:hidden text-[11px] text-white/50 truncate max-w-[140px]">{{ auth()->user()->name }}</span>
        </div>

        {{-- Konten --}}
        <main class="flex-1 overflow-y-auto bg-mk-bg pb-20 lg:pb-0">
            @if(session('success'))
                <div class="mx-4 mt-4 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mx-4 mt-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>

{{-- ===== BOTTOM NAVIGATION (Mobile) ===== --}}
<nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-mk-sidebar border-t border-white/[0.08] flex items-stretch h-16">

    <a href="{{ route('guru.dashboard') }}"
       class="flex-1 flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition-colors
              {{ request()->routeIs('guru.dashboard') ? 'text-mk-accent' : 'text-white/45 hover:text-white/75' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        Dashboard
    </a>

    <a href="{{ route('guru.jadwal') }}"
       class="flex-1 flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition-colors
              {{ request()->routeIs('guru.jadwal') ? 'text-mk-accent' : 'text-white/45 hover:text-white/75' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        Jadwal
    </a>

    <a href="{{ route('guru.honor') }}"
       class="flex-1 flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition-colors
              {{ request()->routeIs('guru.honor*') ? 'text-mk-accent' : 'text-white/45 hover:text-white/75' }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        Honor
    </a>

</nav>

</body>
</html>
```

- [ ] **Step 3: Buat `resources/views/guru/_badge-status.blade.php`**

```html
@php
    $map = [
        'HADIR'           => ['bg-green-100 text-green-700',   'Hadir'],
        'HADIR_TERLAMBAT' => ['bg-yellow-100 text-yellow-700', 'Terlambat'],
        'SCHEDULED'       => ['bg-gray-100 text-gray-500',     'Terjadwal'],
        'LIBUR'           => ['bg-purple-100 text-purple-700', 'Libur'],
        'HANGUS'          => ['bg-red-100 text-red-600',       'Hangus'],
        'IZIN_RESCHEDULE' => ['bg-orange-100 text-orange-600', 'Izin'],
        'IZIN_VIDEO'      => ['bg-orange-100 text-orange-600', 'Izin Video'],
        'DIGANTI'         => ['bg-blue-100 text-blue-600',     'Diganti'],
        'CANCELLED'       => ['bg-gray-200 text-gray-500',     'Batal'],
    ];
    [$cls, $label] = $map[$status] ?? ['bg-gray-100 text-gray-500', $status];
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $cls }}">
    {{ $label }}
</span>
```

- [ ] **Step 4: Build assets**

```bash
npm run build
```

- [ ] **Step 5: Commit**

```bash
git add app/View/Components/GuruLayout.php \
        resources/views/layouts/guru.blade.php \
        resources/views/guru/_badge-status.blade.php
git commit -m "M-Guru: Layout guru — sidebar desktop + bottom nav mobile + badge status"
```

---

## Task 7: Dashboard View

**Files:**
- Create: `resources/views/guru/dashboard.blade.php`

- [ ] **Step 1: Buat `resources/views/guru/dashboard.blade.php`**

Sesi hari ini ditampilkan sebagai **kartu besar** dengan dua tombol full-width yang nyaman di-tap dari HP. Tombol TERLAMBAT expandable dengan input menit.

```html
<x-guru-layout title="Dashboard">

<div class="px-4 pt-5 pb-2">
    <h1 class="text-lg font-semibold text-mk-text">Halo, {{ $teacher->name }}</h1>
    <p class="text-sm text-mk-muted">{{ \Carbon\Carbon::today()->locale('id')->isoFormat('dddd, D MMMM Y') }}</p>
</div>

{{-- ===== SESI HARI INI ===== --}}
<div class="px-4 py-3">
    <h2 class="text-xs font-semibold tracking-widest text-mk-muted uppercase mb-3">Sesi Hari Ini</h2>

    @forelse($sesiHariIni as $sesi)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm mb-3 overflow-hidden">

            <div class="flex items-start justify-between px-4 py-3 border-b border-gray-100">
                <div>
                    <div class="font-semibold text-mk-text">{{ $sesi->student->full_name }}</div>
                    <div class="text-xs text-mk-muted mt-0.5">
                        {{ \Carbon\Carbon::parse($sesi->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($sesi->end_time)->format('H:i') }}
                        @if($sesi->room) · {{ $sesi->room->name }} @endif
                        @if($sesi->enrollment?->package) · {{ $sesi->enrollment->package->code }} @endif
                    </div>
                </div>
                @include('guru._badge-status', ['status' => $sesi->status])
            </div>

            @if($sesi->status === 'SCHEDULED')
                <div x-data="{ showLate: false }" class="px-4 py-3 space-y-2">
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('guru.absensi.update', $sesi) }}" class="flex-1">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="HADIR">
                            <button type="submit"
                                    class="w-full py-3 rounded-xl bg-green-500 hover:bg-green-600 active:scale-[0.98]
                                           text-white font-semibold text-sm transition-all">
                                ✓ Hadir
                            </button>
                        </form>
                        <button @click="showLate = !showLate"
                                class="flex-1 py-3 rounded-xl border-2 border-yellow-400 text-yellow-600
                                       font-semibold text-sm hover:bg-yellow-50 active:scale-[0.98] transition-all">
                            ⏱ Terlambat
                        </button>
                    </div>

                    <div x-show="showLate" x-transition class="pt-1">
                        <form method="POST" action="{{ route('guru.absensi.update', $sesi) }}" class="flex gap-2">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="HADIR_TERLAMBAT">
                            <div class="flex-1">
                                <input type="number" name="late_minutes" min="1" max="60"
                                       placeholder="Berapa menit terlambat?"
                                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                                              focus:outline-none focus:ring-2 focus:ring-yellow-300">
                            </div>
                            <button type="submit"
                                    class="px-5 py-2.5 rounded-xl bg-yellow-400 hover:bg-yellow-500
                                           text-white font-semibold text-sm transition-colors">
                                Simpan
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="px-4 py-3 text-xs text-mk-muted italic">
                    Absensi sudah tercatat.
                </div>
            @endif

        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-100 px-4 py-10 text-center">
            <div class="text-3xl mb-2">🎵</div>
            <div class="text-mk-muted text-sm">Tidak ada sesi hari ini.</div>
        </div>
    @endforelse
</div>

{{-- ===== RINGKASAN BULAN INI ===== --}}
<div class="px-4 pb-8">
    <h2 class="text-xs font-semibold tracking-widest text-mk-muted uppercase mb-3">
        {{ \Carbon\Carbon::now()->locale('id')->isoFormat('MMMM Y') }}
    </h2>
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white rounded-xl border border-gray-100 px-4 py-4 shadow-sm">
            <div class="text-2xl font-bold text-mk-text">{{ $totalSesiBulan }}</div>
            <div class="text-xs text-mk-muted mt-0.5">Sesi Terlaksana</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 px-4 py-4 shadow-sm">
            @if($slipBulanIni)
                <div class="text-lg font-bold text-mk-text leading-tight">
                    Rp {{ number_format($slipBulanIni->total_honor, 0, ',', '.') }}
                </div>
                <div class="text-xs text-mk-muted mt-0.5">
                    Honor {{ $slipBulanIni->status === 'PAID' ? '✓ Dibayar' : 'Estimasi' }}
                </div>
            @else
                <div class="text-sm text-mk-muted pt-1">—</div>
                <div class="text-xs text-mk-muted mt-0.5">Honor (belum dihitung)</div>
            @endif
        </div>
    </div>
</div>

</x-guru-layout>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/guru/dashboard.blade.php
git commit -m "M-Guru: Dashboard view — kartu sesi hari ini + ringkasan bulan"
```

---

## Task 8: Jadwal View

**Files:**
- Create: `resources/views/guru/jadwal.blade.php`

- [ ] **Step 1: Buat `resources/views/guru/jadwal.blade.php`**

Mobile: kartu per hari berkelompok. Desktop: tabel. Tombol absensi hanya muncul di sesi hari ini yang masih SCHEDULED.

```html
<x-guru-layout title="Jadwal Saya">

<div class="px-4 pt-5 pb-2">
    <h1 class="text-lg font-semibold text-mk-text">Jadwal Saya</h1>
    <p class="text-sm text-mk-muted">
        {{ \Carbon\Carbon::parse($mulai)->locale('id')->isoFormat('D MMM') }} –
        {{ \Carbon\Carbon::parse($akhir)->locale('id')->isoFormat('D MMM Y') }}
    </p>
</div>

{{-- ===== MOBILE: Kartu per hari ===== --}}
<div class="lg:hidden px-4 pb-24 space-y-4">
    @php $grouped = $sesi->groupBy('session_date'); @endphp

    @forelse($grouped as $tanggal => $sesiHari)
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="text-xs font-semibold tracking-wide text-mk-muted uppercase">
                    {{ \Carbon\Carbon::parse($tanggal)->locale('id')->isoFormat('dddd, D MMM') }}
                </span>
                @if($tanggal === $today)
                    <span class="text-[10px] bg-mk-accent text-white px-2 py-0.5 rounded-full font-semibold">Hari ini</span>
                @endif
            </div>

            @foreach($sesiHari as $s)
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm mb-2 overflow-hidden">
                    <div class="flex items-start justify-between px-4 py-3">
                        <div>
                            <div class="font-medium text-mk-text text-sm">{{ $s->student->full_name }}</div>
                            <div class="text-xs text-mk-muted mt-0.5">
                                {{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($s->end_time)->format('H:i') }}
                                @if($s->room) · {{ $s->room->name }} @endif
                            </div>
                            @if($s->substitute_teacher_id === auth()->user()->teacher?->id)
                                <div class="text-[10px] text-blue-500 mt-0.5">Anda sebagai pengganti</div>
                            @endif
                        </div>
                        @include('guru._badge-status', ['status' => $s->status])
                    </div>

                    @if($tanggal === $today && $s->status === 'SCHEDULED')
                        <div x-data="{ showLate: false }" class="px-4 pb-3 space-y-2">
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('guru.absensi.update', $s) }}" class="flex-1">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="HADIR">
                                    <button type="submit"
                                            class="w-full py-2.5 rounded-xl bg-green-500 hover:bg-green-600 text-white font-semibold text-sm transition-all active:scale-[0.98]">
                                        ✓ Hadir
                                    </button>
                                </form>
                                <button @click="showLate = !showLate"
                                        class="flex-1 py-2.5 rounded-xl border-2 border-yellow-400 text-yellow-600 font-semibold text-sm hover:bg-yellow-50 transition-all active:scale-[0.98]">
                                    ⏱ Terlambat
                                </button>
                            </div>
                            <div x-show="showLate" x-transition>
                                <form method="POST" action="{{ route('guru.absensi.update', $s) }}" class="flex gap-2">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="HADIR_TERLAMBAT">
                                    <input type="number" name="late_minutes" min="1" max="60" placeholder="Menit terlambat"
                                           class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-300">
                                    <button type="submit"
                                            class="px-4 py-2 bg-yellow-400 hover:bg-yellow-500 text-white rounded-xl font-semibold text-sm">
                                        Simpan
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-100 px-4 py-12 text-center">
            <div class="text-3xl mb-2">📅</div>
            <div class="text-mk-muted text-sm">Tidak ada sesi dalam periode ini.</div>
        </div>
    @endforelse
</div>

{{-- ===== DESKTOP: Tabel ===== --}}
<div class="hidden lg:block px-6 pb-6">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Tanggal</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Murid</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Waktu</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Ruang</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 w-32"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($sesi as $s)
                    <tr class="{{ $s->session_date === $today ? 'bg-green-50/30' : '' }}">
                        <td class="px-4 py-3 text-mk-text">
                            {{ \Carbon\Carbon::parse($s->session_date)->locale('id')->isoFormat('ddd, D MMM') }}
                            @if($s->session_date === $today)
                                <span class="ml-1 text-[10px] bg-mk-accent text-white px-1.5 py-0.5 rounded-full">Hari ini</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-mk-text">{{ $s->student->full_name }}</div>
                            @if($s->substitute_teacher_id === auth()->user()->teacher?->id)
                                <div class="text-[10px] text-blue-500">Pengganti</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-mk-muted">
                            {{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($s->end_time)->format('H:i') }}
                        </td>
                        <td class="px-4 py-3 text-mk-muted">{{ $s->room?->name ?? '—' }}</td>
                        <td class="px-4 py-3">@include('guru._badge-status', ['status' => $s->status])</td>
                        <td class="px-4 py-3 text-right">
                            @if($s->session_date === $today && $s->status === 'SCHEDULED')
                                <div x-data="{ open: false }" class="relative inline-block">
                                    <button @click="open = !open"
                                            class="text-xs px-3 py-1.5 rounded-lg bg-mk-accent hover:bg-mk-accent/80 text-white font-medium transition-colors">
                                        Input Absensi
                                    </button>
                                    <div x-show="open" x-transition @click.outside="open = false"
                                         class="absolute right-0 top-9 z-10 w-56 bg-white border border-gray-100 rounded-xl shadow-lg p-3 space-y-2">
                                        <form method="POST" action="{{ route('guru.absensi.update', $s) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="HADIR">
                                            <button type="submit" class="w-full py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium">✓ Hadir</button>
                                        </form>
                                        <form method="POST" action="{{ route('guru.absensi.update', $s) }}" class="flex gap-1.5">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="HADIR_TERLAMBAT">
                                            <input type="number" name="late_minutes" min="1" max="60" placeholder="mnt"
                                                   class="w-16 border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-yellow-300">
                                            <button type="submit" class="flex-1 py-2 bg-yellow-400 hover:bg-yellow-500 text-white rounded-lg text-sm font-medium">⏱ Terlambat</button>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-mk-muted">Tidak ada sesi dalam periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</x-guru-layout>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/guru/jadwal.blade.php
git commit -m "M-Guru: Jadwal view — kartu mobile + tabel desktop"
```

---

## Task 9: Honor Views

**Files:**
- Create: `resources/views/guru/honor.blade.php`
- Create: `resources/views/guru/honor-show.blade.php`

- [ ] **Step 1: Buat `resources/views/guru/honor.blade.php`**

```html
<x-guru-layout title="Slip Honor">

<div class="px-4 pt-5 pb-2">
    <h1 class="text-lg font-semibold text-mk-text">Slip Honor</h1>
    <p class="text-sm text-mk-muted">Honor yang sudah dihitung oleh studio</p>
</div>

<div class="px-4 pb-24 lg:pb-6 space-y-3">
    @forelse($slips as $slip)
        <a href="{{ route('guru.honor.show', $slip) }}"
           class="block bg-white rounded-xl border border-gray-100 shadow-sm hover:border-mk-accent/40 active:scale-[0.99] transition-all overflow-hidden">
            <div class="flex items-center justify-between px-4 py-4">
                <div>
                    <div class="font-semibold text-mk-text">
                        {{ \Carbon\Carbon::createFromDate($slip->year, $slip->month, 1)->locale('id')->isoFormat('MMMM Y') }}
                    </div>
                    <div class="text-xs text-mk-muted mt-0.5">{{ $slip->slip_number }}</div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-mk-text">Rp {{ number_format($slip->total_honor, 0, ',', '.') }}</div>
                    <div class="mt-1">
                        @if($slip->status === 'PAID')
                            <span class="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">✓ Dibayar</span>
                        @else
                            <span class="text-[10px] bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full font-medium">Sudah Dihitung</span>
                        @endif
                    </div>
                </div>
            </div>
        </a>
    @empty
        <div class="bg-white rounded-xl border border-gray-100 px-4 py-12 text-center">
            <div class="text-3xl mb-2">💰</div>
            <div class="text-mk-muted text-sm">Belum ada slip honor yang tersedia.</div>
            <div class="text-xs text-mk-muted mt-1">Slip honor muncul setelah dihitung oleh studio.</div>
        </div>
    @endforelse
</div>

</x-guru-layout>
```

- [ ] **Step 2: Buat `resources/views/guru/honor-show.blade.php`**

```html
<x-guru-layout :title="'Honor ' . \Carbon\Carbon::createFromDate($honorSlip->year, $honorSlip->month, 1)->locale('id')->isoFormat('MMMM Y')">

<div class="px-4 pt-4 pb-2">
    <a href="{{ route('guru.honor') }}" class="text-sm text-mk-muted hover:text-mk-text transition-colors">← Kembali</a>
</div>

{{-- ===== RINGKASAN SLIP ===== --}}
<div class="mx-4 mb-4 bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-5">
    <div class="text-xs font-semibold tracking-widest text-mk-muted uppercase mb-1">Slip Honor</div>
    <div class="text-xl font-bold text-mk-text">
        {{ \Carbon\Carbon::createFromDate($honorSlip->year, $honorSlip->month, 1)->locale('id')->isoFormat('MMMM Y') }}
    </div>
    <div class="text-xs text-mk-muted mt-0.5 mb-4">{{ $honorSlip->slip_number }}</div>

    <div class="space-y-2 border-t border-gray-100 pt-4">
        <div class="flex justify-between text-sm">
            <span class="text-mk-muted">Honor Pokok</span>
            <span class="font-medium text-mk-text">Rp {{ number_format($honorSlip->base_honor, 0, ',', '.') }}</span>
        </div>
        @if($honorSlip->event_honor > 0)
            <div class="flex justify-between text-sm">
                <span class="text-mk-muted">Honor Event</span>
                <span class="font-medium text-mk-text">Rp {{ number_format($honorSlip->event_honor, 0, ',', '.') }}</span>
            </div>
        @endif
        @if($honorSlip->transport_honor > 0)
            <div class="flex justify-between text-sm">
                <span class="text-mk-muted">Transport</span>
                <span class="font-medium text-mk-text">Rp {{ number_format($honorSlip->transport_honor, 0, ',', '.') }}</span>
            </div>
        @endif
        @if($honorSlip->other_honor > 0)
            <div class="flex justify-between text-sm">
                <span class="text-mk-muted">Lain-lain</span>
                <span class="font-medium text-mk-text">Rp {{ number_format($honorSlip->other_honor, 0, ',', '.') }}</span>
            </div>
        @endif
        <div class="flex justify-between font-bold text-base border-t border-gray-100 pt-3 mt-1">
            <span>Total</span>
            <span>Rp {{ number_format($honorSlip->total_honor, 0, ',', '.') }}</span>
        </div>
    </div>

    <div class="mt-4">
        @if($honorSlip->status === 'PAID')
            <span class="text-xs bg-green-100 text-green-700 px-3 py-1 rounded-full font-medium">✓ Sudah Dibayar</span>
        @else
            <span class="text-xs bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full font-medium">Menunggu Pembayaran</span>
        @endif
    </div>
</div>

{{-- ===== RINCIAN SESI ===== --}}
<div class="mx-4 pb-24 lg:pb-6">
    <h2 class="text-xs font-semibold tracking-widest text-mk-muted uppercase mb-3">Rincian Sesi</h2>

    {{-- Mobile: kartu --}}
    <div class="lg:hidden space-y-2">
        @forelse($sesi as $s)
            <div class="bg-white rounded-xl border border-gray-100 px-4 py-3">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-medium text-mk-text text-sm">{{ $s->student->full_name }}</div>
                        <div class="text-xs text-mk-muted mt-0.5">
                            {{ \Carbon\Carbon::parse($s->session_date)->locale('id')->isoFormat('D MMM') }}
                            · {{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}
                            @if($s->room) · {{ $s->room->name }} @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-semibold text-mk-text">Rp {{ number_format($s->honor_amount, 0, ',', '.') }}</div>
                        <div class="text-[10px] text-mk-muted mt-0.5">{{ $s->honor_code }}</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-gray-100 px-4 py-8 text-center text-mk-muted text-sm">
                Tidak ada rincian sesi.
            </div>
        @endforelse
    </div>

    {{-- Desktop: tabel --}}
    <div class="hidden lg:block bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Tanggal</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Murid</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Ruang</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Kode</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-mk-muted uppercase tracking-wider">Honor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($sesi as $s)
                    <tr>
                        <td class="px-4 py-3 text-mk-muted">{{ \Carbon\Carbon::parse($s->session_date)->locale('id')->isoFormat('D MMM Y') }}</td>
                        <td class="px-4 py-3 font-medium text-mk-text">{{ $s->student->full_name }}</td>
                        <td class="px-4 py-3 text-mk-muted">{{ $s->room?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-mk-muted">{{ $s->honor_code }}</td>
                        <td class="px-4 py-3 text-right font-medium text-mk-text">Rp {{ number_format($s->honor_amount, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-mk-muted">Tidak ada rincian sesi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</x-guru-layout>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/guru/honor.blade.php \
        resources/views/guru/honor-show.blade.php
git commit -m "M-Guru: Honor list + detail view read-only"
```

---

## Task 10: Final Check

- [ ] **Step 1: Jalankan semua test guru**

```bash
php artisan test tests/Feature/GuruModelRelationTest.php \
                tests/Feature/GuruCreateAccountsCommandTest.php \
                tests/Feature/GuruControllerAccessTest.php \
                tests/Feature/GuruUpdateAbsensiTest.php \
                tests/Feature/GuruLoginRedirectTest.php
```

Expected: 22 tests PASS.

- [ ] **Step 2: Jalankan seluruh test suite — cek regresi**

```bash
php artisan test
```

Expected: semua test existing tetap PASS.

- [ ] **Step 3: Buat akun guru di database lokal**

```bash
php artisan guru:create-accounts
```

Expected: tabel output 18 guru dengan email + password awal.

- [ ] **Step 4: Test manual di browser**

1. Login dengan akun guru (contoh: `thomas@musikkita.local` / `thomas`)
2. Pastikan redirect ke `/guru/dashboard`
3. Resize browser ke lebar <1024px — pastikan bottom nav muncul, sidebar hilang
4. Coba klik tombol HADIR di sesi hari ini
5. Buka `/teachers` — pastikan 403 Forbidden
6. Buka `/dashboard` — pastikan 403 Forbidden

- [ ] **Step 5: Build assets final**

```bash
npm run build
```

- [ ] **Step 6: Commit final**

```bash
git add .
git commit -m "M-Guru: Fitur login guru selesai — 18 guru bisa login, absensi, jadwal, honor"
git push
```

---

## Self-Review

**Spec coverage:**
- ✅ role Guru + FK user_id di teachers
- ✅ Artisan command `guru:create-accounts` dengan email dummy fallback
- ✅ Guru lihat jadwal (scope: minggu ini + minggu depan)
- ✅ Guru input absensi HADIR/HADIR_TERLAMBAT hari ini saja
- ✅ Guru pengganti bisa input absensi
- ✅ Slip honor CALCULATED/PAID saja (DRAFT tidak ditampilkan)
- ✅ Detail slip honor read-only di halaman terpisah
- ✅ Redirect login berdasarkan role
- ✅ Ganti password via `/profile` Breeze (sudah ada, tidak perlu kode baru)
- ✅ Mobile-first: bottom nav + kartu besar
- ✅ Badge status 8 warna konsisten di semua halaman guru
- ✅ Route `/teachers/*` terlindungi dari role Guru
- ✅ Guru tidak bisa akses `/dashboard` Owner/Admin

**Tidak ada placeholder — semua step memiliki kode lengkap.**
