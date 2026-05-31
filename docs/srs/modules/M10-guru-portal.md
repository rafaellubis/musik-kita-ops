# SRS M10 — Portal Guru

**Induk:** [SRS-musik-kita-ops-2026-05-31.md](../SRS-musik-kita-ops-2026-05-31.md)

> **Catatan:** Modul ini **tidak** ada di BRD Fase 1 awal; sudah terimplementasi di kode (Mei 2026).

## 1. Tujuan

Guru login terbatas: lihat jadwal, input absensi sesi sendiri, usulkan tanggal pengganti `IZIN_PENDING`, lihat honor, isi laporan progres.

## 2. Route (`routes/web.php`)

Prefix `/guru`, middleware `auth`, `verified`, `role:Guru`, name prefix `guru.`

| Route | Method | Fungsi |
|-------|--------|--------|
| `guru/dashboard` | GET | Dashboard + counter pending |
| `guru/jadwal` | GET | Jadwal mengajar |
| `guru/honor` | GET | Daftar slip |
| `guru/honor/{honorSlip}` | GET | Detail slip |
| `guru/sesi/{classSession}/absensi` | PATCH | Update absensi |
| `guru/profil` | GET | Profil |
| `guru/sesi-pending` | GET | Daftar IZIN_PENDING |
| `guru/sesi-pending/{session}/suggest` | POST | Saran tanggal pengganti |
| `guru/laporan` | GET/POST | List / buat laporan |
| `guru/laporan/{progressReport}/edit` | GET | Edit draft |
| `guru/laporan/{progressReport}` | PUT | Update laporan |

**Controller:** `GuruController`  
**Layout:** `resources/views/layouts/guru.blade.php`, component `GuruLayout`

## 3. Akun Guru

- Role `Guru` di `RoleSeeder`
- `teachers.user_id` → `users.id`
- Command: `php artisan guru:create-accounts` (lihat `GuruCreateAccounts` command)

Login redirect: test `GuruLoginRedirectTest` — Guru tidak ke `/dashboard` staff.

## 4. Business Rules

| BR | Aturan |
|----|--------|
| Absensi | Hanya sesi dengan `teacher_id` = guru login (atau substitute sesuai policy controller) |
| IZIN_PENDING | Hanya sesi milik guru; suggest date → status/workflow lanjut ke Admin (open slot board M04) |
| Honor | Read-only slip milik sendiri |
| Laporan progres | Create/edit milik guru; submit untuk direview staff (M11) |

Admin tetap input absensi penuh via `AbsensiController` (M04).

## 5. File Scope

```
app/Http/Controllers/GuruController.php
app/Console/Commands/GuruCreateAccounts.php
resources/views/guru/
resources/views/layouts/guru.blade.php
app/View/Components/GuruLayout.php
routes/web.php (guru group)
```

## 6. Acceptance Criteria

- [ ] User tanpa role Guru tidak akses `/guru/*`
- [ ] Guru tidak akses route Owner/Admin write
- [ ] PATCH absensi menolak sesi bukan milik guru
- [ ] Tests: `GuruControllerAccessTest`, `GuruLoginRedirectTest`, `GuruUpdateAbsensiTest`, `GuruCreateAccountsCommandTest`
