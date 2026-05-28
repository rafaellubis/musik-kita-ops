# User Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membuat halaman `/users` untuk Owner mengelola semua akun login (Owner/Admin/Auditor/Guru) via tabel + modal, tanpa pindah halaman.

**Architecture:** Single-page list dengan Alpine.js modals. UserController menangani 6 endpoint. Migration menambah kolom `is_active` ke tabel `users`. Guru users di-link ke data Teacher via `teachers.user_id` yang sudah ada.

**Tech Stack:** Laravel 11, Blade + Alpine.js, Tailwind CSS, Spatie Permission v6, TDD dengan PHPUnit.

---

## File Map

| File | Status | Tanggung Jawab |
|---|---|---|
| `database/migrations/xxxx_add_is_active_to_users_table.php` | Baru | Tambah kolom `is_active` boolean ke tabel users |
| `app/Models/User.php` | Modifikasi | Tambah `is_active` ke fillable + casts |
| `app/Http/Requests/StoreUserRequest.php` | Baru | Validasi form Tambah User |
| `app/Http/Requests/UpdateUserRequest.php` | Baru | Validasi form Edit User |
| `app/Http/Requests/ResetPasswordRequest.php` | Baru | Validasi form Reset Password |
| `app/Http/Controllers/UserController.php` | Baru | 6 method: index, store, update, resetPassword, toggleActive, destroy |
| `tests/Feature/UserManagementTest.php` | Baru | 13 test case untuk semua skenario |
| `resources/views/users/index.blade.php` | Baru | Tabel user + 4 modal Alpine.js |
| `routes/web.php` | Modifikasi | Tambah 6 route users di grup role:Owner |
| `resources/views/layouts/navigation.blade.php` | Modifikasi | Tambah item "Pengguna" di sidebar Master Data |

---

## Task 1: Migration + User Model

**Files:**
- Create: `database/migrations/2026_05_28_110000_add_is_active_to_users_table.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Buat migration**

```bash
php artisan make:migration add_is_active_to_users_table
```

- [ ] **Step 2: Isi migration**

Buka file yang baru dibuat di `database/migrations/`, isi dengan:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Status aktif/nonaktif akun login. Default true = semua akun lama tetap aktif.
            $table->boolean('is_active')->default(true)->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
```

- [ ] **Step 3: Jalankan migration**

```bash
php artisan migrate
```

Expected output: `Migrating: ...add_is_active_to_users_table` → `Migrated`

- [ ] **Step 4: Update User model** (`app/Models/User.php`)

Tambah `is_active` ke `$fillable` dan `casts()`:

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'is_active',
];

protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
    ];
}
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ app/Models/User.php
git commit -m "DB: Tambah kolom is_active ke tabel users untuk User Management"
```

---

## Task 2: Form Requests

**Files:**
- Create: `app/Http/Requests/StoreUserRequest.php`
- Create: `app/Http/Requests/UpdateUserRequest.php`
- Create: `app/Http/Requests/ResetPasswordRequest.php`

- [ ] **Step 1: Buat tiga Form Request**

```bash
php artisan make:request StoreUserRequest
php artisan make:request UpdateUserRequest
php artisan make:request ResetPasswordRequest
```

- [ ] **Step 2: Isi StoreUserRequest** (`app/Http/Requests/StoreUserRequest.php`)

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route sudah di-protect role:Owner
    }

    public function rules(): array
    {
        return [
            'name'       => 'required|string|min:2|max:100',
            'email'      => 'required|email|unique:users,email',
            'role'       => 'required|in:Owner,Admin,Auditor,Guru',
            'password'   => 'required|string|min:8',
            'teacher_id' => 'required_if:role,Guru|nullable|exists:teachers,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'Nama wajib diisi.',
            'name.min'               => 'Nama minimal 2 karakter.',
            'email.required'         => 'Email wajib diisi.',
            'email.unique'           => 'Email sudah digunakan oleh user lain.',
            'role.required'          => 'Role wajib dipilih.',
            'role.in'                => 'Role tidak valid.',
            'password.required'      => 'Password wajib diisi.',
            'password.min'           => 'Password minimal 8 karakter.',
            'teacher_id.required_if' => 'Teacher wajib dipilih untuk role Guru.',
            'teacher_id.exists'      => 'Teacher tidak ditemukan.',
        ];
    }
}
```

- [ ] **Step 3: Isi UpdateUserRequest** (`app/Http/Requests/UpdateUserRequest.php`)

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => 'required|string|min:2|max:100',
            'email'      => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'role'       => 'required|in:Owner,Admin,Auditor,Guru',
            'teacher_id' => 'required_if:role,Guru|nullable|exists:teachers,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'Nama wajib diisi.',
            'email.required'         => 'Email wajib diisi.',
            'email.unique'           => 'Email sudah digunakan oleh user lain.',
            'role.required'          => 'Role wajib dipilih.',
            'role.in'                => 'Role tidak valid.',
            'teacher_id.required_if' => 'Teacher wajib dipilih untuk role Guru.',
            'teacher_id.exists'      => 'Teacher tidak ditemukan.',
        ];
    }
}
```

- [ ] **Step 4: Isi ResetPasswordRequest** (`app/Http/Requests/ResetPasswordRequest.php`)

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'password.required'  => 'Password baru wajib diisi.',
            'password.min'       => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/StoreUserRequest.php \
        app/Http/Requests/UpdateUserRequest.php \
        app/Http/Requests/ResetPasswordRequest.php
git commit -m "M: Tambah Form Requests untuk User Management (Store, Update, ResetPassword)"
```

---

## Task 3: Routes + UserController Skeleton

**Files:**
- Create: `app/Http/Controllers/UserController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Buat UserController**

```bash
php artisan make:controller UserController
```

- [ ] **Step 2: Isi UserController dengan skeleton semua method** (`app/Http/Controllers/UserController.php`)

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        abort(501); // TODO: implementasi Task 4
    }

    public function store(StoreUserRequest $request)
    {
        abort(501); // TODO: implementasi Task 5
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        abort(501); // TODO: implementasi Task 6
    }

    public function resetPassword(ResetPasswordRequest $request, User $user)
    {
        abort(501); // TODO: implementasi Task 7
    }

    public function toggleActive(User $user)
    {
        abort(501); // TODO: implementasi Task 7
    }

    public function destroy(User $user)
    {
        abort(501); // TODO: implementasi Task 7
    }
}
```

- [ ] **Step 3: Tambah routes ke web.php**

Di `routes/web.php`, tambah `use App\Http\Controllers\UserController;` di blok use statements atas.

Lalu di dalam grup `Route::middleware('role:Owner')->group(...)`, tambah setelah baris import/block:

```php
// ===== User Management — kelola akun login Owner/Admin/Auditor/Guru =====
Route::get('users', [UserController::class, 'index'])->name('users.index');
Route::post('users', [UserController::class, 'store'])->name('users.store');
Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
```

- [ ] **Step 4: Verifikasi routes terdaftar**

```bash
php artisan route:list --name=users
```

Expected: 6 routes dengan prefix `/users`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/UserController.php routes/web.php
git commit -m "M: Tambah UserController skeleton + routes User Management (Owner only)"
```

---

## Task 4: Feature Tests

**Files:**
- Create: `tests/Feature/UserManagementTest.php`

- [ ] **Step 1: Buat file test**

```bash
php artisan make:test UserManagementTest
```

- [ ] **Step 2: Isi file test lengkap** (`tests/Feature/UserManagementTest.php`)

```php
<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Owner',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Guru',    'guard_name' => 'web']);
    }

    // ===== Helper =====

    private function owner(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Owner');
        return $user;
    }

    private function makeTeacher(array $attrs = []): Teacher
    {
        return Teacher::create(array_merge([
            'code'        => 'T' . rand(1000, 9999),
            'name'        => 'Teacher Test',
            'joined_date' => now()->toDateString(),
            'is_active'   => true,
        ], $attrs));
    }

    // ===== Akses =====

    public function test_owner_dapat_akses_halaman_users(): void
    {
        $response = $this->actingAs($this->owner())->get(route('users.index'));
        $response->assertOk();
    }

    public function test_admin_tidak_bisa_akses_halaman_users(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Admin');
        $this->actingAs($admin)->get(route('users.index'))->assertForbidden();
    }

    public function test_auditor_tidak_bisa_akses_halaman_users(): void
    {
        $auditor = User::factory()->create(['is_active' => true]);
        $auditor->assignRole('Auditor');
        $this->actingAs($auditor)->get(route('users.index'))->assertForbidden();
    }

    // ===== Buat User =====

    public function test_owner_bisa_buat_user_admin(): void
    {
        $this->actingAs($this->owner())->post(route('users.store'), [
            'name'     => 'Admin Baru',
            'email'    => 'admin.baru@musikkita.local',
            'role'     => 'Admin',
            'password' => 'password123',
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'admin.baru@musikkita.local')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Admin'));
        $this->assertTrue($user->is_active);
    }

    public function test_owner_bisa_buat_user_guru_dengan_link_teacher(): void
    {
        $teacher = $this->makeTeacher(['name' => 'Thomas', 'code' => 'THO']);

        $this->actingAs($this->owner())->post(route('users.store'), [
            'name'       => 'Thomas Login',
            'email'      => 'thomas@musikkita.local',
            'role'       => 'Guru',
            'password'   => 'password123',
            'teacher_id' => $teacher->id,
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'thomas@musikkita.local')->first();
        $this->assertTrue($user->hasRole('Guru'));
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'user_id' => $user->id]);
    }

    public function test_buat_user_guru_tanpa_teacher_id_ditolak(): void
    {
        $this->actingAs($this->owner())->post(route('users.store'), [
            'name'     => 'Guru Tanpa Teacher',
            'email'    => 'tanpa@musikkita.local',
            'role'     => 'Guru',
            'password' => 'password123',
        ])->assertSessionHasErrors('teacher_id');
    }

    // ===== Edit User =====

    public function test_owner_bisa_edit_nama_dan_email(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('Admin');

        $this->actingAs($this->owner())->put(route('users.update', $target), [
            'name'  => 'Nama Baru',
            'email' => 'baru@musikkita.local',
            'role'  => 'Admin',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Nama Baru']);
    }

    public function test_edit_ganti_role_guru_ke_admin_melepas_teacher(): void
    {
        $teacher = $this->makeTeacher();
        $guru = User::factory()->create(['is_active' => true]);
        $guru->assignRole('Guru');
        $teacher->update(['user_id' => $guru->id]);

        $this->actingAs($this->owner())->put(route('users.update', $guru), [
            'name'  => $guru->name,
            'email' => $guru->email,
            'role'  => 'Admin',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'user_id' => null]);
        $this->assertTrue($guru->fresh()->hasRole('Admin'));
    }

    // ===== Reset Password =====

    public function test_owner_bisa_reset_password_user_lain(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('Admin');

        $this->actingAs($this->owner())->post(route('users.reset-password', $target), [
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect(route('users.index'));

        $this->assertTrue(Hash::check('newpassword123', $target->fresh()->password));
    }

    public function test_reset_password_tidak_cocok_ditolak(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('Admin');

        $this->actingAs($this->owner())->post(route('users.reset-password', $target), [
            'password'              => 'newpassword123',
            'password_confirmation' => 'berbeda456',
        ])->assertSessionHasErrors('password');
    }

    // ===== Toggle Active =====

    public function test_owner_bisa_nonaktifkan_user_lain(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('Admin');

        $this->actingAs($this->owner())->post(route('users.toggle-active', $target))
            ->assertRedirect(route('users.index'));

        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_owner_tidak_bisa_nonaktifkan_diri_sendiri(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('users.toggle-active', $owner))
            ->assertRedirect(route('users.index'));

        // Status tidak berubah
        $this->assertTrue($owner->fresh()->is_active);
    }

    // ===== Hapus =====

    public function test_hapus_user_nonaktif_tanpa_audit_log_berhasil(): void
    {
        $target = User::factory()->create(['is_active' => false]);
        $target->assignRole('Admin');

        $this->actingAs($this->owner())->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_hapus_gagal_jika_user_masih_aktif(): void
    {
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('Admin');

        $this->actingAs($this->owner())->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_hapus_gagal_jika_user_punya_audit_log(): void
    {
        $target = User::factory()->create(['is_active' => false]);
        $target->assignRole('Admin');

        AuditLog::create([
            'user_id'      => $target->id,
            'user_name'    => $target->name,
            'action'       => 'LOGIN',
            'entity_type'  => null,
            'entity_id'    => null,
            'entity_label' => null,
        ]);

        $this->actingAs($this->owner())->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_owner_tidak_bisa_hapus_akun_sendiri(): void
    {
        $owner = $this->owner();
        $owner->update(['is_active' => false]);

        $this->actingAs($owner)->delete(route('users.destroy', $owner))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }
}
```

- [ ] **Step 3: Jalankan tests — verifikasi semua FAIL dengan 501**

```bash
php artisan test tests/Feature/UserManagementTest.php
```

Expected: beberapa test FAIL dengan `Response status code [501]` atau `[403]`. Test akses yang sudah benar (admin/auditor → 403) akan PASS lebih dulu karena route sudah ada.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/UserManagementTest.php
git commit -m "Test: Tambah 13 test case UserManagementTest (semua failing)"
```

---

## Task 5: Implementasi index()

**Files:**
- Modify: `app/Http/Controllers/UserController.php`

- [ ] **Step 1: Ganti method index() dengan implementasi nyata**

```php
public function index(Request $request)
{
    $query = User::with(['roles', 'teacher'])->orderBy('name');

    // Filter: search nama atau email
    if ($search = $request->get('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    // Filter: role
    if ($role = $request->get('role')) {
        $query->role($role); // scope dari Spatie Permission
    }

    // Filter: status aktif/nonaktif
    if ($request->get('status') === 'aktif') {
        $query->where('is_active', true);
    } elseif ($request->get('status') === 'nonaktif') {
        $query->where('is_active', false);
    }

    $users = $query->get()->map(function ($user) {
        // Tandai apakah user bisa dihapus (tidak ada audit log)
        $user->can_delete = !AuditLog::where('user_id', $user->id)->exists();
        return $user;
    });

    // Semua teacher aktif beserta user_id-nya — untuk dropdown modal
    $allTeachers = Teacher::where('is_active', true)
        ->orderBy('name')
        ->get(['id', 'name', 'code', 'user_id']);

    $totalAktif    = User::where('is_active', true)->count();
    $totalNonaktif = User::where('is_active', false)->count();

    return view('users.index', compact('users', 'allTeachers', 'totalAktif', 'totalNonaktif'));
}
```

- [ ] **Step 2: Buat view sementara agar test index() tidak error**

Buat folder dan file kosong:

```bash
mkdir -p resources/views/users
```

Isi `resources/views/users/index.blade.php` sementara:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-mk-text">Manajemen User</h2>
    </x-slot>
    <div class="py-6 px-4 lg:px-8">
        <p class="text-mk-muted">Halaman user — coming soon</p>
    </div>
</x-app-layout>
```

- [ ] **Step 3: Jalankan test index**

```bash
php artisan test tests/Feature/UserManagementTest.php --filter="akses"
```

Expected: 3 test PASS (owner OK, admin 403, auditor 403).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/UserController.php resources/views/users/index.blade.php
git commit -m "M: Implementasi UserController::index() + view placeholder"
```

---

## Task 6: Implementasi store()

**Files:**
- Modify: `app/Http/Controllers/UserController.php`

- [ ] **Step 1: Ganti method store() dengan implementasi nyata**

```php
public function store(StoreUserRequest $request)
{
    $user = User::create([
        'name'              => $request->name,
        'email'             => $request->email,
        'password'          => Hash::make($request->password),
        'is_active'         => true,
        'email_verified_at' => now(),
    ]);

    $user->syncRoles([$request->role]);

    // Hubungkan ke Teacher jika role Guru
    if ($request->role === 'Guru' && $request->teacher_id) {
        Teacher::where('id', $request->teacher_id)->update(['user_id' => $user->id]);
    }

    AuditLog::record(
        AuditLog::ACTION_CREATE,
        $user,
        $user->name,
        null,
        ['name' => $user->name, 'email' => $user->email, 'role' => $request->role],
    );

    return redirect()->route('users.index')
        ->with('success', "User {$user->name} berhasil dibuat.");
}
```

- [ ] **Step 2: Jalankan test store**

```bash
php artisan test tests/Feature/UserManagementTest.php --filter="buat"
```

Expected: 3 test PASS (buat admin, buat guru+teacher, guru tanpa teacher ditolak).

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/UserController.php
git commit -m "M: Implementasi UserController::store() — buat user + link Teacher jika Guru"
```

---

## Task 7: Implementasi update(), resetPassword(), toggleActive(), destroy()

**Files:**
- Modify: `app/Http/Controllers/UserController.php`

- [ ] **Step 1: Ganti method update()**

```php
public function update(UpdateUserRequest $request, User $user)
{
    $oldRole   = $user->getRoleNames()->first();
    $oldValues = ['name' => $user->name, 'email' => $user->email, 'role' => $oldRole];

    // Jika role berubah dari Guru → lepas link Teacher lama
    if ($oldRole === 'Guru' && $request->role !== 'Guru') {
        Teacher::where('user_id', $user->id)->update(['user_id' => null]);
    }

    $user->update([
        'name'  => $request->name,
        'email' => $request->email,
    ]);

    $user->syncRoles([$request->role]);

    // Perbarui link Teacher jika masih Guru
    if ($request->role === 'Guru' && $request->teacher_id) {
        // Lepas teacher lama jika beda
        Teacher::where('user_id', $user->id)
               ->whereNot('id', $request->teacher_id)
               ->update(['user_id' => null]);
        Teacher::where('id', $request->teacher_id)->update(['user_id' => $user->id]);
    }

    AuditLog::record(
        AuditLog::ACTION_UPDATE,
        $user,
        $user->name,
        $oldValues,
        ['name' => $user->name, 'email' => $user->email, 'role' => $request->role],
    );

    return redirect()->route('users.index')
        ->with('success', "User {$user->name} berhasil diperbarui.");
}
```

- [ ] **Step 2: Ganti method resetPassword()**

```php
public function resetPassword(ResetPasswordRequest $request, User $user)
{
    $user->update(['password' => Hash::make($request->password)]);

    AuditLog::record(
        AuditLog::ACTION_UPDATE,
        $user,
        $user->name,
        null,
        ['password_reset' => true],
        'Reset password oleh Owner',
    );

    return redirect()->route('users.index')
        ->with('success', "Password {$user->name} berhasil direset.");
}
```

- [ ] **Step 3: Ganti method toggleActive()**

```php
public function toggleActive(User $user)
{
    // Tidak boleh mengubah status akun sendiri
    if ($user->id === auth()->id()) {
        return redirect()->route('users.index')
            ->with('error', 'Tidak dapat mengubah status akun Anda sendiri.');
    }

    $wasActive = $user->is_active;
    $user->update(['is_active' => !$wasActive]);

    AuditLog::record(
        AuditLog::ACTION_UPDATE,
        $user,
        $user->name,
        ['is_active' => $wasActive],
        ['is_active' => !$wasActive],
    );

    $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';
    return redirect()->route('users.index')
        ->with('success', "User {$user->name} berhasil {$status}.");
}
```

- [ ] **Step 4: Ganti method destroy()**

```php
public function destroy(User $user)
{
    // Tidak boleh hapus akun sendiri
    if ($user->id === auth()->id()) {
        return redirect()->route('users.index')
            ->with('error', 'Tidak dapat menghapus akun Anda sendiri.');
    }

    // Hanya boleh hapus yang sudah nonaktif
    if ($user->is_active) {
        return redirect()->route('users.index')
            ->with('error', "User harus dinonaktifkan terlebih dahulu sebelum dihapus.");
    }

    // Cek audit log — user dengan riwayat tidak bisa dihapus
    if (AuditLog::where('user_id', $user->id)->exists()) {
        return redirect()->route('users.index')
            ->with('error', "User {$user->name} memiliki riwayat aktivitas dan tidak dapat dihapus.");
    }

    // Lepas link Teacher jika Guru
    Teacher::where('user_id', $user->id)->update(['user_id' => null]);

    $userName  = $user->name;
    $userEmail = $user->email;
    $userRole  = $user->getRoleNames()->first();

    $user->delete();

    AuditLog::record(
        AuditLog::ACTION_DELETE,
        null,
        $userName,
        ['name' => $userName, 'email' => $userEmail, 'role' => $userRole],
        null,
    );

    return redirect()->route('users.index')
        ->with('success', "User {$userName} berhasil dihapus dari sistem.");
}
```

- [ ] **Step 5: Jalankan semua tests**

```bash
php artisan test tests/Feature/UserManagementTest.php
```

Expected: **13/13 PASS**. Jika ada yang gagal, baca error message dan debug.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/UserController.php
git commit -m "M: Implementasi UserController — update, resetPassword, toggleActive, destroy"
```

---

## Task 8: View users/index.blade.php

**Files:**
- Modify: `resources/views/users/index.blade.php`

- [ ] **Step 1: Tulis view lengkap** — ganti isi `resources/views/users/index.blade.php` dengan:

```blade
{{--
    M-Users: Halaman manajemen user (Owner only)
    Fitur: List tabel + 4 modal Alpine.js (Tambah/Edit, Reset PW, Nonaktifkan, Hapus)
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Manajemen User</h2>
                <div class="text-xs text-mk-muted mt-0.5">Kelola akun login Owner, Admin, Auditor, dan Guru</div>
            </div>
            <button @click="openCreate()"
                    class="px-4 py-2 rounded-lg text-sm font-bold transition-colors btn-mk-primary">
                + Tambah User
            </button>
        </div>
    </x-slot>

    {{-- Alpine: state semua modal di-scope ke div utama --}}
    <div class="py-6 px-4 lg:px-8"
         x-data="{
            modal: null,
            editUser: {},
            resetUser: {},
            deleteUser: {},
            deactivateUser: {},
            selectedRole: '',
            allTeachers: {{ $allTeachers->toJson() }},

            openCreate() {
                this.editUser = {};
                this.selectedRole = '';
                this.modal = 'create';
            },
            openEdit(user) {
                this.editUser = user;
                this.selectedRole = user.role;
                this.modal = 'edit';
            },
            openReset(user) {
                this.resetUser = user;
                this.modal = 'reset';
            },
            openDeactivate(user) {
                this.deactivateUser = user;
                this.modal = 'deactivate';
            },
            openDelete(user) {
                this.deleteUser = user;
                this.modal = 'delete';
            },
            closeModal() {
                this.modal = null;
            },

            // Teachers yang belum punya akun (untuk Create)
            get availableTeachers() {
                return this.allTeachers.filter(t => t.user_id === null);
            },
            // Teachers untuk Edit: yang belum punya akun + teacher yang sedang terhubung ke user ini
            availableTeachersForEdit(userId) {
                return this.allTeachers.filter(t => t.user_id === null || t.user_id === userId);
            },
         }">

        {{-- Flash messages --}}
        @if(session('success'))
        <div class="mb-5 p-3 rounded-lg text-sm"
             style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="mb-5 p-3 rounded-lg text-sm"
             style="background:rgba(176,58,46,0.12);color:#D07868;border:1px solid rgba(176,58,46,0.25)">
            {{ session('error') }}
        </div>
        @endif

        {{-- Filter bar --}}
        <form method="GET" action="{{ route('users.index') }}"
              class="mb-5 flex flex-wrap gap-3 items-center">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Cari nama atau email..."
                   class="bg-white border border-gray-200 text-gray-900 text-sm rounded-lg px-3 py-2 w-56">

            <select name="role" class="bg-white border border-gray-200 text-gray-700 text-sm rounded-lg px-3 py-2">
                <option value="">Semua Role</option>
                @foreach(['Owner','Admin','Auditor','Guru'] as $r)
                    <option value="{{ $r }}" @selected(request('role') === $r)>{{ $r }}</option>
                @endforeach
            </select>

            <select name="status" class="bg-white border border-gray-200 text-gray-700 text-sm rounded-lg px-3 py-2">
                <option value="">Semua Status</option>
                <option value="aktif"    @selected(request('status') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
            </select>

            <button type="submit"
                    class="px-3 py-2 text-sm rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50">
                Filter
            </button>
            @if(request()->hasAny(['search','role','status']))
            <a href="{{ route('users.index') }}"
               class="text-xs text-mk-muted hover:text-mk-text">× Reset</a>
            @endif
        </form>

        {{-- Tabel --}}
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Role</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Info</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($users as $user)
                    @php
                        $role       = $user->getRoleNames()->first() ?? '—';
                        $isSelf     = $user->id === auth()->id();
                        $isActive   = $user->is_active;
                        $teacherData = $user->teacher ? ['id' => $user->teacher->id, 'name' => $user->teacher->name] : null;
                        $userData = [
                            'id'         => $user->id,
                            'name'       => $user->name,
                            'email'      => $user->email,
                            'role'       => $role,
                            'teacher_id' => $user->teacher?->id,
                            'teacher'    => $teacherData,
                            'is_active'  => $isActive,
                        ];
                        $roleBadge = match($role) {
                            'Owner'   => 'background:rgba(123,94,167,0.18);color:#B09AD8',
                            'Admin'   => 'background:rgba(58,97,134,0.18);color:#7AAAC8',
                            'Auditor' => 'background:rgba(181,101,29,0.18);color:#D4853A',
                            'Guru'    => 'background:rgba(58,125,68,0.18);color:#6BC07A',
                            default   => 'background:rgba(100,100,100,0.18);color:#888',
                        };
                        $initial = strtoupper(substr($user->name, 0, 1));
                    @endphp
                    <tr class="{{ !$isActive ? 'opacity-50' : '' }}">
                        {{-- Nama --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center
                                            text-sm font-bold shrink-0"
                                     style="{{ $roleBadge }}">
                                    {{ $initial }}
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                    @if($isSelf)
                                    <span class="text-[10px] px-1.5 py-0.5 rounded"
                                          style="background:rgba(212,168,83,0.15);color:#D4A853">Anda</span>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- Email --}}
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $user->email }}</td>

                        {{-- Role --}}
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                  style="{{ $roleBadge }}">
                                {{ $role }}
                            </span>
                        </td>

                        {{-- Info Tambahan --}}
                        <td class="px-4 py-3 text-sm text-gray-500">
                            @if($user->teacher)
                                👨‍🏫 {{ $user->teacher->name }}
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3">
                            @if($isActive)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                  style="background:rgba(58,125,68,0.14);color:#16a34a">Aktif</span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                  style="background:rgba(176,58,46,0.14);color:#dc2626">Nonaktif</span>
                            @endif
                        </td>

                        {{-- Aksi --}}
                        <td class="px-4 py-3 text-right">
                            @if($isSelf)
                                <span class="text-xs text-gray-400 italic">Akun Anda sendiri</span>
                            @elseif($isActive)
                                <div class="flex items-center justify-end gap-1.5">
                                    <button @click="openEdit({{ json_encode($userData) }})"
                                            class="px-2.5 py-1.5 text-xs rounded-md border transition-colors"
                                            style="background:rgba(212,168,83,0.12);color:#92400e;border-color:rgba(212,168,83,0.3)">
                                        ✏️ Edit
                                    </button>
                                    <button @click="openReset({{ json_encode($userData) }})"
                                            class="px-2.5 py-1.5 text-xs rounded-md border transition-colors"
                                            style="background:rgba(58,97,134,0.12);color:#1d4ed8;border-color:rgba(58,97,134,0.3)">
                                        🔑 Reset PW
                                    </button>
                                    <button @click="openDeactivate({{ json_encode($userData) }})"
                                            class="px-2.5 py-1.5 text-xs rounded-md border transition-colors"
                                            style="background:rgba(176,58,46,0.12);color:#dc2626;border-color:rgba(176,58,46,0.3)">
                                        ⛔ Nonaktif
                                    </button>
                                </div>
                            @else
                                <div class="flex items-center justify-end gap-1.5">
                                    <form method="POST" action="{{ route('users.toggle-active', $user) }}">
                                        @csrf
                                        <button type="submit"
                                                class="px-2.5 py-1.5 text-xs rounded-md border transition-colors"
                                                style="background:rgba(58,125,68,0.12);color:#16a34a;border-color:rgba(58,125,68,0.3)">
                                            ✅ Aktifkan
                                        </button>
                                    </form>
                                    @if($user->can_delete)
                                    <button @click="openDelete({{ json_encode($userData) }})"
                                            class="px-2.5 py-1.5 text-xs rounded-md border transition-colors"
                                            style="background:rgba(176,58,46,0.12);color:#dc2626;border-color:rgba(176,58,46,0.3)">
                                        🗑️ Hapus
                                    </button>
                                    @else
                                    <span class="text-xs text-gray-400" title="User ini memiliki riwayat aktivitas">
                                        🔒 Tidak bisa dihapus
                                    </span>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">
                            Tidak ada user yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Summary bar --}}
            <div class="px-4 py-3 border-t border-gray-100 flex gap-4 text-xs text-gray-500">
                <span>Total: <strong class="text-gray-800">{{ $users->count() }}</strong></span>
                <span>Aktif: <strong style="color:#16a34a">{{ $totalAktif }}</strong></span>
                <span>Nonaktif: <strong style="color:#dc2626">{{ $totalNonaktif }}</strong></span>
            </div>
        </div>

        {{-- ===== MODAL 1: TAMBAH USER ===== --}}
        <div x-show="modal === 'create'" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background:rgba(0,0,0,0.6)">
            <div @click.stop class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Tambah User Baru</h3>
                <p class="text-sm text-gray-500 mb-5">Isi semua field yang diperlukan</p>

                <form method="POST" action="{{ route('users.store') }}">
                    @csrf

                    @include('users._form_fields', ['mode' => 'create'])

                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="closeModal()"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold rounded-lg btn-mk-primary">
                            Simpan User
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ===== MODAL 2: EDIT USER ===== --}}
        <div x-show="modal === 'edit'" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background:rgba(0,0,0,0.6)">
            <div @click.stop class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Edit User</h3>
                <p class="text-sm text-gray-500 mb-5" x-text="'Mengubah akun: ' + editUser.name"></p>

                <form method="POST" :action="`/users/${editUser.id}`">
                    @csrf
                    @method('PUT')

                    @include('users._form_fields', ['mode' => 'edit'])

                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="closeModal()"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold rounded-lg btn-mk-primary">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ===== MODAL 3: RESET PASSWORD ===== --}}
        <div x-show="modal === 'reset'" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background:rgba(0,0,0,0.6)">
            <div @click.stop class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Reset Password</h3>
                <p class="text-sm text-gray-500 mb-5">
                    untuk: <strong x-text="resetUser.name"></strong>
                </p>

                <form method="POST" :action="`/users/${resetUser.id}/reset-password`">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                            Password Baru <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="password" required minlength="8"
                               placeholder="Min. 8 karakter"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
                    </div>
                    <div class="mb-6">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                            Konfirmasi Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="password_confirmation" required
                               placeholder="Ulangi password baru"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" @click="closeModal()"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold text-white rounded-lg"
                                style="background:#1d4ed8">
                            🔑 Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ===== MODAL 4: KONFIRMASI NONAKTIFKAN ===== --}}
        <div x-show="modal === 'deactivate'" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background:rgba(0,0,0,0.6)">
            <div @click.stop class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">⛔ Nonaktifkan User</h3>
                <p class="text-sm text-gray-600 mb-2">
                    User ini tidak akan bisa login setelah dinonaktifkan:
                </p>
                <div class="bg-gray-50 rounded-lg p-3 mb-5">
                    <div class="font-medium text-gray-800 text-sm" x-text="deactivateUser.name"></div>
                    <div class="text-xs text-gray-500" x-text="deactivateUser.email + ' · ' + deactivateUser.role"></div>
                </div>

                <form method="POST" :action="`/users/${deactivateUser.id}/toggle-active`">
                    @csrf
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="closeModal()"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold text-white rounded-lg"
                                style="background:#dc2626">
                            Nonaktifkan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ===== MODAL 5: KONFIRMASI HAPUS ===== --}}
        <div x-show="modal === 'delete'" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background:rgba(0,0,0,0.6)">
            <div @click.stop class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6"
                 style="border:1px solid rgba(176,58,46,0.3)">
                <h3 class="text-lg font-semibold mb-3" style="color:#dc2626">⚠️ Hapus User Permanen</h3>
                <p class="text-sm text-gray-600 mb-2">Anda akan menghapus akun berikut:</p>
                <div class="rounded-lg p-3 mb-3" style="background:rgba(176,58,46,0.06)">
                    <div class="font-medium text-gray-800 text-sm" x-text="deleteUser.name"></div>
                    <div class="text-xs text-gray-500" x-text="deleteUser.email + ' · ' + deleteUser.role"></div>
                </div>
                <div class="rounded-lg p-3 mb-5 text-xs text-gray-600"
                     style="background:rgba(212,168,83,0.08)">
                    ✅ User ini tidak memiliki audit log — aman untuk dihapus permanen.
                </div>

                <form method="POST" :action="`/users/${deleteUser.id}`">
                    @csrf
                    @method('DELETE')
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="closeModal()"
                                class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-semibold text-white rounded-lg"
                                style="background:#dc2626">
                            🗑️ Hapus Permanen
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>{{-- end x-data --}}
</x-app-layout>
```

- [ ] **Step 2: Buat partial form fields** — buat file `resources/views/users/_form_fields.blade.php`

```blade
{{--
    Partial: field form Tambah/Edit User
    $mode: 'create' atau 'edit'
    Depends on Alpine x-data: editUser, selectedRole, availableTeachers, availableTeachersForEdit()
--}}

{{-- Nama --}}
<div class="mb-4">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
        Nama Lengkap <span class="text-red-500">*</span>
    </label>
    <input type="text" name="name" required minlength="2" maxlength="100"
           :value="$mode === 'edit' ? editUser.name : ''"
           @if($mode === 'create') value="{{ old('name') }}" @endif
           placeholder="cth: Sari Andriani"
           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

{{-- Email --}}
<div class="mb-4">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
        Email <span class="text-red-500">*</span>
    </label>
    <input type="email" name="email" required
           :value="$mode === 'edit' ? editUser.email : ''"
           @if($mode === 'create') value="{{ old('email') }}" @endif
           placeholder="cth: sari@musikkita.local"
           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
    @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

{{-- Role --}}
<div class="mb-4">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
        Role <span class="text-red-500">*</span>
    </label>
    <select name="role" required
            x-model="selectedRole"
            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
        <option value="">-- Pilih Role --</option>
        @foreach(['Owner','Admin','Auditor','Guru'] as $r)
        <option value="{{ $r }}">{{ $r }}</option>
        @endforeach
    </select>
    @error('role') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

{{-- Teacher — hanya muncul jika role Guru --}}
<div class="mb-4 p-3 rounded-lg"
     style="background:rgba(58,125,68,0.06);border:1px solid rgba(58,125,68,0.2)"
     x-show="selectedRole === 'Guru'" x-cloak>
    <label class="block text-xs font-semibold mb-1.5 uppercase tracking-wide" style="color:#16a34a">
        👨‍🏫 Hubungkan ke Teacher <span class="text-red-500">*</span>
    </label>
    <select name="teacher_id"
            :required="selectedRole === 'Guru'"
            class="w-full border rounded-lg px-3 py-2 text-sm text-gray-900"
            style="border-color:rgba(58,125,68,0.35)">
        <option value="">-- Pilih Teacher --</option>
        @if($mode === 'create')
        <template x-for="t in availableTeachers" :key="t.id">
            <option :value="t.id" x-text="t.name"></option>
        </template>
        @else
        <template x-for="t in availableTeachersForEdit(editUser.id)" :key="t.id">
            <option :value="t.id" :selected="t.id === editUser.teacher_id" x-text="t.name"></option>
        </template>
        @endif
    </select>
    <p class="text-xs mt-1.5" style="color:#6B4A2A">
        @if($mode === 'create')
            Hanya Teacher yang belum punya akun yang ditampilkan.
        @else
            Teacher yang sudah punya akun lain tidak ditampilkan.
        @endif
    </p>
    @error('teacher_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>

{{-- Password — hanya saat Create --}}
@if($mode === 'create')
<div class="mb-2">
    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
        Password Awal <span class="text-red-500">*</span>
    </label>
    <input type="password" name="password" required minlength="8"
           placeholder="Min. 8 karakter"
           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900">
    <p class="text-xs text-gray-400 mt-1">User bisa ganti password sendiri via halaman Profil.</p>
    @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
</div>
@endif
```

- [ ] **Step 3: Jalankan test akses sekali lagi untuk memastikan view tidak error**

```bash
php artisan test tests/Feature/UserManagementTest.php --filter="akses"
```

Expected: 3 PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/views/users/
git commit -m "M: Tambah view users/index.blade.php + partial _form_fields (Tambah/Edit/Reset/Hapus modal)"
```

---

## Task 9: Sidebar + Final Check

**Files:**
- Modify: `resources/views/layouts/navigation.blade.php`

- [ ] **Step 1: Tambah item sidebar di navigation.blade.php**

Di dalam grup Master Data, setelah baris `@endrole` yang terakhir (setelah `payroll-configs`), tambah:

```blade
@role('Owner')
<x-sidebar-item route="users.index" icon="👤" label="Pengguna"
    :active="request()->routeIs('users.*')" />
@endrole
```

- [ ] **Step 2: Jalankan semua test satu kali terakhir**

```bash
php artisan test tests/Feature/UserManagementTest.php
```

Expected: **13/13 PASS**.

- [ ] **Step 3: Jalankan full test suite**

```bash
php artisan test
```

Expected: semua test hijau. Jika ada regresi, debug sebelum commit.

- [ ] **Step 4: Build assets**

```bash
npm run build
```

- [ ] **Step 5: Commit final**

```bash
git add resources/views/layouts/navigation.blade.php
git commit -m "M: Tambah sidebar item 'Pengguna' di Master Data (Owner only)"
```

- [ ] **Step 6: Test manual di browser**

Buka `http://localhost/musik-kita-ops` (atau port Laragon Anda), login sebagai Owner, klik "Pengguna" di sidebar. Verifikasi:
- [ ] Tabel user muncul dengan semua user yang ada
- [ ] Tombol "+ Tambah User" membuka modal
- [ ] Form tambah user (Admin) → submit → user terbuat, flash success muncul
- [ ] Form tambah user (Guru) → pilih Teacher dari dropdown → submit → Teacher ter-link
- [ ] Tombol "✏️ Edit" → modal edit terbuka dengan data user yang benar
- [ ] Tombol "🔑 Reset PW" → modal reset terbuka
- [ ] Tombol "⛔ Nonaktif" → modal konfirmasi → user menjadi Nonaktif
- [ ] User Nonaktif: tombol "✅ Aktifkan" langsung aktifkan, tombol "🗑️ Hapus" muncul jika tidak ada audit log
- [ ] Login sebagai Admin → URL `/users` → 403

---

## Ringkasan File yang Dibuat / Dimodifikasi

| File | Task |
|---|---|
| `database/migrations/xxxx_add_is_active_to_users_table.php` | 1 |
| `app/Models/User.php` | 1 |
| `app/Http/Requests/StoreUserRequest.php` | 2 |
| `app/Http/Requests/UpdateUserRequest.php` | 2 |
| `app/Http/Requests/ResetPasswordRequest.php` | 2 |
| `app/Http/Controllers/UserController.php` | 3, 5, 6, 7 |
| `routes/web.php` | 3 |
| `tests/Feature/UserManagementTest.php` | 4 |
| `resources/views/users/index.blade.php` | 5, 8 |
| `resources/views/users/_form_fields.blade.php` | 8 |
| `resources/views/layouts/navigation.blade.php` | 9 |
