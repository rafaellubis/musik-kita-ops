# Design Spec: Halaman User Management

**Tanggal:** 2026-05-28
**Status:** Approved
**Modul:** Sistem (Owner only)

---

## Ringkasan

Halaman untuk Owner mengelola semua akun login sistem — Owner, Admin, Auditor, dan Guru.
Saat ini belum ada UI sama sekali; user dibuat manual via seeder.
Pendekatan: satu halaman list + modal (tidak pindah halaman).

---

## Scope

Yang **termasuk** dalam spec ini:
- Halaman index list semua user (`/users`)
- Modal Tambah/Edit User
- Modal Reset Password
- Modal Konfirmasi Hapus
- Entri sidebar "Pengguna" di grup Master Data (Owner-only)

Yang **tidak termasuk** (di luar scope):
- Halaman profil user (sudah ada via Breeze `/profile`)
- Notifikasi email (sistem offline LAN, tidak ada email)
- Import user massal
- Permission granular per user (hanya role-based)

---

## Arsitektur

### Route

```
GET    /users                    → UserController@index
POST   /users                    → UserController@store
PUT    /users/{user}             → UserController@update
POST   /users/{user}/reset-password → UserController@resetPassword
POST   /users/{user}/toggle-active  → UserController@toggleActive
DELETE /users/{user}             → UserController@destroy
```

Semua route di dalam grup `middleware('role:Owner')`.

### Controller

`App\Http\Controllers\UserController` — resource manual (bukan `--resource` penuh).

Method:
- `index()` — list semua user + eager load roles
- `store(StoreUserRequest)` — buat user baru, assign role, link Teacher jika Guru
- `update(UpdateUserRequest, User)` — update nama/email/role, update link Teacher
- `resetPassword(ResetPasswordRequest, User)` — set password baru langsung
- `toggleActive(User)` — aktifkan / nonaktifkan (via kolom `banned_at` atau flag)
- `destroy(User)` — hapus permanen, hanya jika tidak ada audit_log entries dan sudah nonaktif

### Form Requests

- `StoreUserRequest` — validasi: name required, email unique, role valid, password min:8, teacher_id required_if:role,Guru
- `UpdateUserRequest` — validasi: name required, email unique (ignore self), role valid, teacher_id required_if:role,Guru
- `ResetPasswordRequest` — validasi: password min:8, confirmed

### Model

`User` sudah punya `HasRoles` (Spatie) dan relasi `teacher()`.

Penambahan yang diperlukan: kolom `is_active` (boolean, default true) di tabel `users`
via migration baru — digunakan untuk status Aktif/Nonaktif.

Alternatif: gunakan kolom `banned_at` (nullable timestamp) — lebih eksplisit.
**Keputusan: gunakan `is_active` boolean** — lebih sederhana dan konsisten dengan pola
`is_active` yang sudah dipakai di tabel lain (teachers, rooms, packages).

---

## Halaman Index (`users/index.blade.php`)

### Filter Bar
- Search: nama atau email (query string `?search=`)
- Filter role: dropdown pilih satu (Owner / Admin / Auditor / Guru / Semua)
- Filter status: Aktif / Nonaktif / Semua

### Tabel

Kolom:
| Kolom | Keterangan |
|---|---|
| Nama | Avatar initial + nama + badge "Anda" jika user saat ini |
| Email | email login |
| Role | Badge berwarna per role |
| Info Tambahan | Untuk Guru: "Teacher: [NAMA]". Role lain: "—" |
| Status | Badge Aktif (hijau) / Nonaktif (merah) |
| Aksi | Lihat bawah |

### Aksi per baris

User **Aktif** (bukan diri sendiri):
- `✏️ Edit` → buka modal Edit User
- `🔑 Reset PW` → buka modal Reset Password
- `⛔ Nonaktifkan` → buka modal konfirmasi nonaktif

User **Aktif** (diri sendiri — user yang sedang login):
- Tampilkan teks "Akun Anda sendiri" — tidak ada tombol aksi
- Mencegah Owner mengunci diri sendiri dari sistem

User **Nonaktif**:
- `✅ Aktifkan` → langsung aktifkan (tanpa modal konfirmasi)
- `🗑️ Hapus` → buka modal Konfirmasi Hapus (cek audit log dulu)

### Summary bar (bawah tabel)
Tampilkan: Total user · Aktif: N · Nonaktif: N

---

## Modal 1 — Tambah / Edit User

**Trigger:** Tombol "+ Tambah User" (create) atau tombol "✏️ Edit" di baris (edit).

**Field:**

| Field | Tambah | Edit | Aturan |
|---|---|---|---|
| Nama Lengkap | ✅ wajib | ✅ wajib | min:2, max:100 |
| Email | ✅ wajib | ✅ wajib | email, unique (ignore self saat edit) |
| Role | ✅ wajib | ✅ wajib | enum: Owner/Admin/Auditor/Guru |
| Teacher | ✅ jika Guru | ✅ jika Guru | required_if:role,Guru; dropdown Teacher aktif yang belum punya akun |
| Password Awal | ✅ wajib | ❌ tidak ada | min:8; user bisa ganti sendiri via /profile |

**Logika dropdown Teacher:**
- Query: `Teacher::whereIsActive(true)->whereDoesntHave('user')->get()`
- Saat Edit user Guru yang sudah linked: tampilkan teacher yang sama + teacher lain yang belum linked

**Saat simpan:**
1. Buat/update `User`
2. Sync role via `$user->syncRoles([$request->role])`
3. Jika role Guru: set `teacher->user_id` atau update relasi via `Teacher::where('id', $request->teacher_id)->update(['user_id' => $user->id])` — *lihat catatan relasi di bawah*
4. Jika role berubah dari Guru ke lain: lepas link Teacher lama (`teacher->update(['user_id' => null])`)
5. Catat audit log: action CREATE atau UPDATE

**Catatan relasi Teacher↔User:**
Model `Teacher` saat ini tidak punya kolom `user_id`. Relasi saat ini adalah `User hasOne Teacher`.
Artinya kolom FK ada di tabel `teachers` sebagai `user_id` (nullable).
Perlu dicek apakah kolom ini sudah ada atau perlu migration.

---

## Modal 2 — Reset Password

**Trigger:** Tombol "🔑 Reset PW" di baris user aktif.

**Field:**
- Password Baru (required, min:8)
- Konfirmasi Password (required, confirmed)

**Proses:** `$user->update(['password' => Hash::make($request->password)])`

**Audit log:** action UPDATE, entity User, new_values: `{password_reset: true}` (jangan log nilai password).

---

## Modal 3 — Konfirmasi Hapus

**Trigger:** Tombol "🗑️ Hapus" di baris user nonaktif.

**Sebelum modal dibuka:** Controller cek `AuditLog::where('user_id', $user->id)->exists()`.
- Jika ada → tombol Hapus disabled + tooltip "User ini memiliki riwayat aktivitas, tidak bisa dihapus"
- Jika tidak ada → modal terbuka dengan konfirmasi

**Isi modal:**
- Info user (nama, email, role, status)
- Pesan konfirmasi: "User ini tidak memiliki audit log — aman untuk dihapus permanen."
- Tombol: Batal | Hapus Permanen

**Proses destroy:**
1. Cek ulang di server: `AuditLog::where('user_id', $user->id)->exists()` → 422 jika ada
2. Cek user bukan diri sendiri → 403 jika ya
3. Lepas relasi Teacher jika Guru
4. `$user->delete()`

---

## Sidebar

Tambah entri di grup **Master Data** (`navigation.blade.php`), **setelah** "Config Honor":

```blade
@role('Owner')
<x-sidebar-item route="users.index" icon="👤" label="Pengguna"
    :active="request()->routeIs('users.*')" />
@endrole
```

---

## Migration

Satu migration baru:

```php
// add_is_active_to_users_table
$table->boolean('is_active')->default(true)->after('remember_token');
```

Kolom `teachers.user_id` (nullable, unique, FK ke users, nullOnDelete) **sudah ada**
via migration `2026_05_28_100000_add_user_id_to_teachers.php` — tidak perlu migration tambahan.

---

## Audit Log

Setiap aksi dicatat ke tabel `audit_logs`:

| Aksi | action | entity_type | Catatan |
|---|---|---|---|
| Buat user | CREATE | User | new_values: nama, email, role |
| Edit user | UPDATE | User | old/new values |
| Reset password | UPDATE | User | new_values: `{password_reset: true}` |
| Nonaktifkan | UPDATE | User | old: `{is_active: true}`, new: `{is_active: false}` |
| Aktifkan | UPDATE | User | old: `{is_active: false}`, new: `{is_active: true}` |
| Hapus | DELETE | User | old_values: nama, email, role |

---

## Validasi Business Rules

- Owner tidak bisa nonaktifkan atau hapus akun diri sendiri
- Tidak bisa hapus user yang punya entri audit_log
- Hapus hanya bisa dilakukan jika user sudah berstatus Nonaktif
- Role Guru wajib link ke Teacher; Teacher yang sudah punya akun tidak muncul di dropdown
- Sistem offline LAN — tidak ada kirim email untuk reset password; Owner set langsung

---

## Testing

Test yang perlu ditulis (`tests/Feature/UserManagementTest.php`):

- [ ] Owner bisa lihat halaman users index
- [ ] Admin dan Auditor tidak bisa akses (403)
- [ ] Owner bisa buat user Admin baru
- [ ] Owner bisa buat user Guru + link Teacher
- [ ] Dropdown Teacher tidak tampilkan Teacher yang sudah punya akun
- [ ] Edit user: ganti role dari Guru ke Admin → Teacher-link dilepas
- [ ] Reset password berhasil
- [ ] Nonaktifkan user berhasil; Owner tidak bisa nonaktifkan diri sendiri
- [ ] Hapus gagal jika user punya audit log
- [ ] Hapus berhasil jika user tidak punya audit log dan sudah nonaktif
- [ ] Filter search dan filter role bekerja

---

## File yang Akan Dibuat / Dimodifikasi

| File | Status |
|---|---|
| `app/Http/Controllers/UserController.php` | Baru |
| `app/Http/Requests/StoreUserRequest.php` | Baru |
| `app/Http/Requests/UpdateUserRequest.php` | Baru |
| `app/Http/Requests/ResetPasswordRequest.php` | Baru |
| `resources/views/users/index.blade.php` | Baru |
| `routes/web.php` | Modifikasi — tambah user routes |
| `resources/views/layouts/navigation.blade.php` | Modifikasi — tambah sidebar item |
| `database/migrations/xxxx_add_is_active_to_users_table.php` | Baru |
| `database/migrations/2026_05_28_100000_add_user_id_to_teachers.php` | Sudah ada ✅ |
| `tests/Feature/UserManagementTest.php` | Baru |
