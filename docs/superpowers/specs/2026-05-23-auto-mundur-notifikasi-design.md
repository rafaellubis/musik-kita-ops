# Design Spec: Auto-Mundur — Laravel Database Notifications

**Tanggal:** 2026-05-23
**Modul:** M05 (Keuangan Murid) + Infrastruktur Notifikasi
**Status:** Approved

---

## Latar Belakang

BR-5.14: Murid Aktif dengan tunggakan >1 bulan (invoice UNPAID/PARTIAL dari bulan sebelumnya)
harus di-mundurkan. Admin harus konfirmasi sebelum mundur dieksekusi.

Sekaligus membangun infrastruktur notifikasi Laravel Database yang bisa dipakai
oleh fitur-fitur lain di masa depan (slip honor, lifecycle murid, saldo kas, dll).

---

## Keputusan Desain

| Aspek | Keputusan |
|-------|-----------|
| Trigger deteksi | Murid Aktif dengan invoice `UNPAID`/`PARTIAL` dari bulan < bulan ini |
| Eksekusi mundur | Admin harus konfirmasi (tidak auto) |
| Mekanisme | Laravel Database Notifications (tabel `notifications`) |
| Jadwal cron | Tgl 1 tiap bulan jam 06:05 |
| Penerima notifikasi | Semua user berole `Admin` atau `Owner` |
| UI | Bell 🔔 di topbar, badge merah unread count, dropdown Alpine.js |
| Konfirmasi | Klik "Tinjau →" → redirect ke halaman detail murid → klik tombol Mundurkan |

---

## Komponen yang Dibangun

### 1. Migration — Tabel `notifications`

```bash
php artisan notifications:table
php artisan migrate
```

Laravel sudah menyediakan migration ini. Kolom utama:
- `id` — UUID primary key
- `type` — fully-qualified class name notification
- `notifiable_type` / `notifiable_id` — polymorphic ke `users`
- `data` — JSON payload
- `read_at` — nullable timestamp (null = belum dibaca)

### 2. Notification Class — `MuridOverdueNotification`

**File:** `app/Notifications/MuridOverdueNotification.php`

Payload `data` (JSON):
```json
{
  "student_id": 42,
  "student_name": "Budi Santoso",
  "student_code": "M-2025-0042",
  "invoice_month": "Mei 2026",
  "total_overdue": 340000,
  "student_url": "/students/42"
}
```

Channel: `database` saja (tidak perlu mail/broadcast untuk Fase 1).

### 3. Artisan Command — `students:check-overdue`

**File:** `app/Console/Commands/CheckOverdueStudents.php`

**Logika:**
1. Query murid `status = 'Aktif'`
2. Filter yang punya invoice `UNPAID`/`PARTIAL` dengan `month < bulan ini` atau `year < tahun ini`
3. Idempotent: skip murid yang sudah punya notifikasi `MuridOverdueNotification` yang belum dibaca (`read_at IS NULL`) dari bulan ini
4. Untuk tiap murid eligible: kirim notifikasi ke semua user berole `Admin` atau `Owner`
5. Output command: laporan berapa murid dinotifikasi

**Daftarkan di `routes/console.php`:**
```php
Schedule::command('students:check-overdue')
    ->monthlyOn(1, '06:05')
    ->name('m05-check-overdue-students')
    ->withoutOverlapping();
```

### 4. View Composer — Inject ke Topbar

**Daftarkan di `AppServiceProvider::boot()`:**

```php
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
    }
});
```

### 5. UI — Topbar Badge + Dropdown

**Modifikasi:** `resources/views/layouts/app.blade.php`

Tambahkan bell button di antara tanggal dan avatar (topbar kanan):

```
[Tanggal] [🔔 badge] [Avatar] [☀️] [Keluar]
```

Dropdown Alpine.js (`x-data`, `x-show`, `@click.away`):
- Header: "Konfirmasi Auto-Mundur (N)"
- Per item: nama murid, kode, bulan tunggakan, nominal, tombol "Tinjau →"
- Klik "Tinjau →": mark as read + redirect ke `/students/{id}`
- Footer: "Klik Tinjau untuk ke halaman murid"

Badge hanya tampil jika `$overdueNotifCount > 0`.

### 6. Controller — `NotificationController`

**File:** `app/Http/Controllers/NotificationController.php`

Dua endpoint:
- `POST /notifications/{notification}/read` — mark satu notif as read, return JSON
- `POST /notifications/read-all` — mark semua unread notif as read

**Routes** (middleware `auth`, role `Owner|Admin`):
```php
Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
    ->name('notifications.read');
Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])
    ->name('notifications.read-all');
```

### 7. Model — Tambah `Notifiable` Trait ke `User`

**File:** `app/Models/User.php`

Pastikan sudah ada:
```php
use Illuminate\Notifications\Notifiable;
// ...
class User extends Authenticatable {
    use Notifiable;
```

Laravel Breeze sudah include ini by default — verifikasi saja.

---

## Flow Lengkap

```
Tgl 1 jam 06:00 → invoices:generate-spp (SPP baru diterbitkan)
Tgl 1 jam 06:05 → students:check-overdue
    ↓
    Query murid Aktif dengan invoice UNPAID bulan lalu
    ↓
    Untuk tiap murid: kirim MuridOverdueNotification ke Admin + Owner
    ↓
Admin buka sistem → topbar bell 🔔 badge merah muncul
    ↓
Admin klik bell → dropdown muncul (list murid + nominal)
    ↓
Admin klik "Tinjau →" → AJAX mark as read + redirect ke /students/{id}
    ↓
Admin di halaman murid → klik tombol "Mundurkan"
    ↓
mundurkan() dipanggil → status murid = Mengundurkan Diri
    ↓
Badge berkurang (atau hilang jika semua sudah ditinjau)
```

---

## Idempotency

Cron tidak boleh kirim notifikasi duplikat jika dijalankan ulang di hari yang sama.
Guard: sebelum kirim notifikasi untuk murid X, cek apakah sudah ada
`notifications` dengan `type = MuridOverdueNotification` dan `data->student_id = X`
dan `read_at IS NULL` yang dibuat bulan ini.

---

## Testing

- **Unit:** `CheckOverdueStudentsTest` — mock query, verifikasi notification dikirim/tidak
- **Feature:** murid Aktif + invoice UNPAID bulan lalu → command → notif tersimpan di DB
- **Edge cases:**
  - Murid sudah punya notif pending bulan ini → skip (idempotent)
  - Murid bayar setelah notif dikirim → notif tetap ada, Admin tetap perlu dismiss manual
  - Murid tidak ada yang overdue → command selesai dengan 0 notifikasi

---

## File yang Diubah / Dibuat

| Aksi | File |
|------|------|
| BUAT | `database/migrations/xxxx_create_notifications_table.php` (via artisan) |
| BUAT | `app/Notifications/MuridOverdueNotification.php` |
| BUAT | `app/Console/Commands/CheckOverdueStudents.php` |
| BUAT | `app/Http/Controllers/NotificationController.php` |
| UBAH | `app/Providers/AppServiceProvider.php` — daftarkan View Composer |
| UBAH | `app/Models/User.php` — verifikasi Notifiable trait |
| UBAH | `resources/views/layouts/app.blade.php` — tambah bell + dropdown |
| UBAH | `routes/web.php` — tambah 2 route notifikasi |
| UBAH | `routes/console.php` — daftarkan cron students:check-overdue |

---

## Tidak Termasuk (Scope Ini)

- Notifikasi jenis lain (slip honor, lifecycle murid, dll) — dicatat di next planning
- Email/push notification — database channel saja untuk Fase 1
- Halaman `/notifications` (index semua notifikasi) — tidak perlu, cukup dropdown
