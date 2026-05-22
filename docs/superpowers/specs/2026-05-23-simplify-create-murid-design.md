# Spesifikasi: Penyederhanaan Form Create Murid

**Tanggal:** 2026-05-23
**Status:** Disetujui — siap implementasi

---

## Latar Belakang

Setelah migrasi multi-kelas, tabel `students` tidak lagi menyimpan `package_id`, `assigned_teacher_id`, `assigned_room_id`, `preferred_day`, `preferred_time`. Data kelas sepenuhnya hidup di tabel `enrollments`. Namun form create murid masih merender field `package_id` dan `assigned_teacher_id`, dan `StoreStudentRequest` masih memvalidasi kedua field tersebut — sisa dari arsitektur lama yang belum dibersihkan.

---

## Keputusan Desain

### Status saat create: selalu Calon

Murid baru selalu dibuat dengan status `Calon`. Status tidak bisa diubah dari form create. Ini menjaga konsistensi dengan BR-MK-2 (murid Aktif wajib punya enrollment) — kita tidak bisa izinkan create langsung Aktif tanpa enrollment.

### Enrollment tidak dibuat saat create

Form create hanya menyimpan data murid. Enrollment pertama dibuat oleh lifecycle actions di halaman detail (`mulaiTrial`, `skipTrial`, `konversiAktif`). Kelas ke-2 dan seterusnya ditambah via Tab Kelas setelah murid Aktif.

### Alur lengkap

```
1. Create murid (Calon) — data murid saja
   → redirect ke students/{id}#tab-kelas + flash

2. Admin lakukan lifecycle action di halaman detail:
   - mulaiTrial (package + teacher di form lifecycle) → enrollment #1 dibuat
   - atau skipTrial (package + teacher di form lifecycle) → enrollment #1 dibuat

3. Setelah murid Aktif:
   - Tab Kelas → Tambah Kelas → enrollment #2, #3, dst.
```

---

## Perubahan yang Diperlukan

### 1. `resources/views/students/_form.blade.php`

Hapus blok field `package_id` dan `assigned_teacher_id` yang saat ini hanya ditampilkan di mode create (`@if(!isset($student))`). Seluruh blok tersebut dihapus — tidak ada lagi enrollment-related fields di form ini.

### 2. `app/Http/Requests/StoreStudentRequest.php`

Hapus rules berikut:
```php
'package_id'          => '...',
'assigned_teacher_id' => '...',
```

Hapus messages terkait kedua field di atas.

Hapus seluruh method `withValidator()` jika isinya hanya memvalidasi kedua field yang sudah dihapus.

### 3. `app/Http/Controllers/StudentController::store()`

- Hapus pengambilan `package_id` dan `assigned_teacher_id` dari request
- Hapus pemanggilan lifecycle service atau logika yang bergantung pada kedua field tersebut
- Pastikan `Student::create()` tidak menyertakan field yang sudah tidak ada di tabel
- Ganti redirect dari `students.index` ke `students.show` dengan flash message

```php
return redirect()
    ->route('students.show', $student)
    ->withFragment('tab-kelas')
    ->with('success', 'Murid berhasil didaftarkan. Silakan tambahkan kelas via Tab Kelas.');
```

### 4. Tab Kelas & Lifecycle Actions

Tidak ada perubahan. Tab Kelas tetap hanya berfungsi untuk murid Aktif. Lifecycle actions tetap menangani pembuatan enrollment pertama dengan form package + teacher yang sudah ada di halaman detail.

---

## Yang Tidak Berubah

- `StudentLifecycleService` — tidak ada perubahan
- `EnrollmentController` — tidak ada perubahan
- `InvoiceService::generateMonthlySPP` — tidak ada perubahan
- `resources/views/students/partials/tab-kelas.blade.php` — tidak ada perubahan
- Semua test yang ada — tidak boleh ada regresi

---

## Business Rules

```
BR-CREATE-1 : Murid baru selalu dibuat dengan status Calon
BR-CREATE-2 : Form create tidak membuat enrollment — enrollment dikelola via lifecycle + Tab Kelas
BR-CREATE-3 : Setelah create, redirect ke halaman detail murid dengan Tab Kelas visible
```

---

## File yang Dimodifikasi

```
resources/views/students/_form.blade.php        ← hapus package_id, assigned_teacher_id fields
app/Http/Requests/StoreStudentRequest.php       ← hapus rules + messages kedua field
app/Http/Controllers/StudentController.php      ← hapus logika field lama di store(), ganti redirect
```
