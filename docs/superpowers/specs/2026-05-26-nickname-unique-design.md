# Design: Nickname Murid Harus Unik

**Tanggal:** 2026-05-26
**Status:** Disetujui

---

## Latar Belakang

Kolom `students.nickname` saat ini tidak punya constraint unik — dua murid bisa punya nama panggilan yang sama. Ini membingungkan secara operasional karena nickname dipakai sebagai pengenal singkat di tabel murid dan fitur pencarian.

Sebelum implementasi, dilakukan pengecekan data existing: 9 nickname awalnya duplikat, sudah dibersihkan manual. Data sekarang bersih dan aman untuk ditambahkan unique constraint.

---

## Keputusan Desain

**Opsi yang dipilih: Dua lapisan (validasi Laravel + DB unique index)**

- Validasi Laravel mencegah duplikat saat input via UI
- DB unique index menutup celah dari jalur lain (import Excel, tinker, dll)
- NULL tetap diperbolehkan banyak — constraint hanya berlaku untuk nilai yang diisi

**Opsi yang tidak dipilih:**
- Validasi Laravel saja — meninggalkan celah di ImportController dan akses DB langsung

---

## Scope Perubahan

### 1. Migration Baru
File: `database/migrations/2026_05_26_xxxxxx_add_unique_nickname_to_students.php`

Tambahkan unique index pada kolom `nickname` di tabel `students`.
MySQL unique index pada nullable column: nilai NULL boleh banyak, nilai string harus unik.

### 2. StoreStudentRequest
File: `app/Http/Requests/StoreStudentRequest.php`

Rule nickname diubah dari:
```
'nullable|string|max:30'
```
Menjadi:
```
'nullable|string|max:30|unique:students,nickname'
```

Tambah pesan error di method `messages()`:
```
'nickname.unique' => 'Nama panggilan sudah dipakai murid lain.'
```

### 3. UpdateStudentRequest
File: `app/Http/Requests/UpdateStudentRequest.php`

Rule nickname diubah dari:
```
'nullable|string|max:30'
```
Menjadi (menggunakan `Rule::unique` dengan `->ignore()` agar tidak bentrok dengan data murid itu sendiri):
```php
use Illuminate\Validation\Rule;

'nickname' => [
    'nullable', 'string', 'max:30',
    Rule::unique('students', 'nickname')->ignore($this->student->id),
],
```

Tambah pesan error di method `messages()`:
```
'nickname.unique' => 'Nama panggilan sudah dipakai murid lain.'
```

---

## Yang Tidak Berubah

- Controller, Model, View — tidak ada perubahan
- Normalisasi Title Case di `prepareForValidation` — tetap berjalan, otomatis menangani case-insensitive (input "andi" dan "ANDI" keduanya menjadi "Andi" sebelum validasi)
- Nickname tetap field opsional (nullable)

---

## Urutan Eksekusi

1. Buat migration
2. Jalankan `php artisan migrate`
3. Update `StoreStudentRequest`
4. Update `UpdateStudentRequest`

---

## Testing

- Input murid baru dengan nickname yang sudah ada → error "Nama panggilan sudah dipakai murid lain."
- Edit murid tanpa mengubah nickname → tidak error (ignore own ID)
- Dua murid tanpa nickname (kosong) → keduanya boleh disimpan
- Input "andi" saat "Andi" sudah ada → error (Title Case normalization menangkap ini)
