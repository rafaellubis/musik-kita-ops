# Nickname Murid Unik — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mencegah dua murid punya nama panggilan (nickname) yang sama — enforced di validasi Laravel dan unique index di database.

**Architecture:** Dua lapisan — migration menambah `UNIQUE` index pada `students.nickname` (NULL boleh banyak, hanya string yang harus unik), dan dua Form Request diupdate untuk memblok duplikat saat input via UI maupun API internal. Normalisasi Title Case yang sudah ada di `prepareForValidation` otomatis menangani case-insensitive.

**Tech Stack:** Laravel 11, PHPUnit (SQLite in-memory via phpunit.xml), Spatie Permission, `Illuminate\Validation\Rule`

---

## File Map

| File | Aksi | Keterangan |
|------|------|------------|
| `database/migrations/2026_05_26_000001_add_unique_nickname_to_students.php` | Create | Tambah unique index pada `students.nickname` |
| `app/Http/Requests/StoreStudentRequest.php` | Modify | Tambah rule `unique:students,nickname` + pesan error |
| `app/Http/Requests/UpdateStudentRequest.php` | Modify | Tambah `Rule::unique()->ignore()` + pesan error |
| `tests/Feature/NicknameUniqueTest.php` | Create | Feature test 7 skenario |

---

## Task 1: Tulis test yang gagal

**Files:**
- Create: `tests/Feature/NicknameUniqueTest.php`

- [ ] **Step 1: Buat file test**

```php
<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NicknameUniqueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);

        $user = User::factory()->create()->assignRole('Admin');
        $this->actingAs($user);
    }

    // --- STORE ---

    /** Nickname baru dan unik → boleh disimpan */
    public function test_store_nickname_unik_berhasil(): void
    {
        $this->post(route('students.store'), [
            'full_name' => 'Budi Santoso',
            'gender'    => 'L',
            'nickname'  => 'Budi',
        ])->assertSessionHasNoErrors()
          ->assertRedirect();
    }

    /** Nickname duplikat dengan murid lain → gagal validasi */
    public function test_store_nickname_duplikat_gagal(): void
    {
        Student::factory()->create(['nickname' => 'Budi']);

        $this->post(route('students.store'), [
            'full_name' => 'Budi Prasetyo',
            'gender'    => 'L',
            'nickname'  => 'Budi',
        ])->assertSessionHasErrors(['nickname']);
    }

    /** Nickname kosong (null) → boleh banyak, tidak bentrok */
    public function test_store_nickname_kosong_boleh_banyak(): void
    {
        Student::factory()->create(['nickname' => null]);

        $this->post(route('students.store'), [
            'full_name' => 'Clara Putri',
            'gender'    => 'P',
            'nickname'  => '',
        ])->assertSessionHasNoErrors()
          ->assertRedirect();
    }

    /** Input lowercase saat Title Case sudah ada → ditangkap duplikat */
    public function test_store_nickname_case_insensitive_gagal(): void
    {
        // 'Andi' sudah ada di DB (Title Case)
        Student::factory()->create(['nickname' => 'Andi']);

        // Input 'andi' (lowercase) → prepareForValidation ubah jadi 'Andi' → duplikat
        $this->post(route('students.store'), [
            'full_name' => 'Andi Prasetyo',
            'gender'    => 'L',
            'nickname'  => 'andi',
        ])->assertSessionHasErrors(['nickname']);
    }

    // --- UPDATE ---

    /** Edit murid tanpa ubah nickname → tidak bentrok dengan datanya sendiri */
    public function test_update_nickname_sendiri_berhasil(): void
    {
        $student = Student::factory()->create(['nickname' => 'Budi']);

        $this->put(route('students.update', $student), [
            'full_name' => $student->full_name,
            'gender'    => $student->gender,
            'nickname'  => 'Budi',
        ])->assertSessionHasNoErrors()
          ->assertRedirect();
    }

    /** Edit murid, ganti nickname ke milik murid lain → gagal validasi */
    public function test_update_nickname_milik_murid_lain_gagal(): void
    {
        Student::factory()->create(['nickname' => 'Budi']);
        $student = Student::factory()->create(['nickname' => 'Andi']);

        $this->put(route('students.update', $student), [
            'full_name' => $student->full_name,
            'gender'    => $student->gender,
            'nickname'  => 'Budi',
        ])->assertSessionHasErrors(['nickname']);
    }

    /** Edit murid, hapus nickname (jadi kosong) → boleh */
    public function test_update_hapus_nickname_berhasil(): void
    {
        $student = Student::factory()->create(['nickname' => 'Budi']);

        $this->put(route('students.update', $student), [
            'full_name' => $student->full_name,
            'gender'    => $student->gender,
            'nickname'  => '',
        ])->assertSessionHasNoErrors()
          ->assertRedirect();
    }
}
```

- [ ] **Step 2: Jalankan test — pastikan GAGAL**

```bash
php artisan test tests/Feature/NicknameUniqueTest.php
```

Expected: beberapa test FAIL karena rule `unique` belum ada.
Test `test_store_nickname_unik_berhasil` dan `test_store_nickname_kosong_boleh_banyak` mungkin sudah PASS — itu normal.

---

## Task 2: Buat migration

**Files:**
- Create: `database/migrations/2026_05_26_000001_add_unique_nickname_to_students.php`

- [ ] **Step 1: Generate migration**

```bash
php artisan make:migration add_unique_nickname_to_students --table=students
```

- [ ] **Step 2: Isi migration**

Buka file migration yang baru dibuat (nama diawali timestamp), isi dengan:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah unique index pada students.nickname.
 * NULL boleh banyak (perilaku standar MySQL unique pada nullable column).
 * Hanya nilai string yang harus unik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unique('nickname');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['nickname']);
        });
    }
};
```

- [ ] **Step 3: Jalankan migration**

```bash
php artisan migrate
```

Expected output mengandung:
```
... add_unique_nickname_to_students .... DONE
```

---

## Task 3: Update StoreStudentRequest

**Files:**
- Modify: `app/Http/Requests/StoreStudentRequest.php`

- [ ] **Step 1: Ubah rule nickname**

Temukan baris (sekitar baris 54):
```php
'nickname'            => 'nullable|string|max:30',
```

Ganti dengan:
```php
'nickname'            => 'nullable|string|max:30|unique:students,nickname',
```

- [ ] **Step 2: Tambah pesan error di `messages()`**

Di dalam method `messages()`, tambahkan satu baris:
```php
'nickname.unique' => 'Nama panggilan sudah dipakai murid lain.',
```

Contoh `messages()` setelah edit:
```php
public function messages(): array
{
    return [
        'full_name.required'           => 'Nama lengkap wajib diisi.',
        'full_name.max'                => 'Nama lengkap maksimal 100 karakter.',
        'nickname.unique'              => 'Nama panggilan sudah dipakai murid lain.',
        'gender.required'              => 'Jenis kelamin wajib dipilih.',
        'gender.in'                    => 'Jenis kelamin harus L atau P.',
        'birth_date.before_or_equal'   => 'Tanggal lahir tidak boleh di masa depan.',
        'email.email'                  => 'Format email tidak valid.',
        'phone.regex'                  => 'Nomor HP hanya boleh angka, +, -, spasi, dan kurung.',
        'parent_email.email'           => 'Format email orang tua tidak valid.',
        'parent_phone.regex'           => 'Nomor HP orang tua hanya boleh angka.',
        'status.in'                    => 'Status tidak valid.',
    ];
}
```

- [ ] **Step 3: Jalankan test store saja**

```bash
php artisan test tests/Feature/NicknameUniqueTest.php --filter=store
```

Expected: semua test yang mengandung kata "store" PASS.

---

## Task 4: Update UpdateStudentRequest

**Files:**
- Modify: `app/Http/Requests/UpdateStudentRequest.php`

- [ ] **Step 1: Tambah import Rule di bagian atas file**

Setelah baris `use Illuminate\Foundation\Http\FormRequest;`, tambahkan:
```php
use Illuminate\Validation\Rule;
```

- [ ] **Step 2: Ubah rule nickname di `rules()`**

Temukan baris (sekitar baris 55):
```php
'nickname'            => 'nullable|string|max:30',
```

Ganti dengan:
```php
'nickname' => [
    'nullable', 'string', 'max:30',
    Rule::unique('students', 'nickname')->ignore($this->student->id),
],
```

> `$this->student` diisi otomatis oleh Laravel via route model binding dari `{student}` di URL. Tidak perlu resolve manual.

- [ ] **Step 3: Tambah pesan error di `messages()`**

Di dalam method `messages()`, tambahkan:
```php
'nickname.unique' => 'Nama panggilan sudah dipakai murid lain.',
```

Contoh `messages()` setelah edit:
```php
public function messages(): array
{
    return [
        'full_name.required'           => 'Nama lengkap wajib diisi.',
        'gender.required'              => 'Jenis kelamin wajib dipilih.',
        'gender.in'                    => 'Jenis kelamin harus L atau P.',
        'nickname.unique'              => 'Nama panggilan sudah dipakai murid lain.',
        'birth_date.before_or_equal'   => 'Tanggal lahir tidak boleh di masa depan.',
        'email.email'                  => 'Format email tidak valid.',
        'phone.regex'                  => 'Nomor HP hanya boleh angka, +, -, spasi, dan kurung.',
        'parent_email.email'           => 'Format email orang tua tidak valid.',
        'parent_phone.regex'           => 'Nomor HP orang tua hanya boleh angka.',
    ];
}
```

---

## Task 5: Jalankan semua test dan commit

- [ ] **Step 1: Jalankan NicknameUniqueTest — pastikan semua PASS**

```bash
php artisan test tests/Feature/NicknameUniqueTest.php
```

Expected: 7 tests, 7 passed.

- [ ] **Step 2: Jalankan full test suite — pastikan tidak ada regresi**

```bash
php artisan test
```

Expected: semua test PASS. Jika ada test lain yang fail, investigasi sebelum commit.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/
git add app/Http/Requests/StoreStudentRequest.php
git add app/Http/Requests/UpdateStudentRequest.php
git add tests/Feature/NicknameUniqueTest.php
git commit -m "Murid: nickname wajib unik — validasi Laravel + DB unique index"
```
