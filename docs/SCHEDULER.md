# Setup Laravel Scheduler di Windows (Laragon)

Sistem ini punya 3 tugas terjadwal otomatis (lihat `routes/console.php`):

| Command | Jadwal | Kegunaan |
|---|---|---|
| `sessions:generate-month` | Tanggal 25, 06:00 | Generate sesi mingguan untuk bulan berikutnya (M03) |
| `invoices:generate-spp` | Tanggal 1, 06:00 | Terbitkan invoice SPP untuk semua murid Aktif (M05, BR-5.1) |
| `invoices:apply-fines` | Harian 06:00 (mulai tanggal 11) | Update denda Rp 5.000/hari (M05, BR-5.3) |

Supaya tugas-tugas ini benar-benar jalan otomatis, **Task Scheduler Windows** harus
memanggil `php artisan schedule:run` setiap menit. Caranya:

## 1. Setup Task Scheduler Windows

1. Buka **Task Scheduler** (Win + R → `taskschd.msc`).
2. Klik kanan **Task Scheduler Library** → **Create Task...** (bukan Basic Task).
3. Tab **General**:
   - Name: `Musik KITA Scheduler`
   - Run whether user is logged on or not: ✓
   - Run with highest privileges: ✓
4. Tab **Triggers** → New:
   - Begin the task: **On a schedule**
   - One time, start: pilih waktu sekarang
   - Advanced: **Repeat task every 1 minute** for a duration of **Indefinitely**
   - Enabled: ✓
5. Tab **Actions** → New:
   - Action: **Start a program**
   - Program/script: `C:\laragon\bin\php\php-8.3.x-Win32-vs16-x64\php.exe`
     (sesuaikan path PHP — cek di Laragon)
   - Add arguments: `artisan schedule:run`
   - Start in: `C:\laragon\www\musik-kita-ops`
6. Tab **Conditions**: uncheck "Start the task only if the computer is on AC power"
   (kalau pakai laptop yang sering unplug).
7. Tab **Settings**:
   - Allow task to be run on demand: ✓
   - If task fails, restart every 1 minute: ✓ (max 3 tries)
8. Klik **OK** → masukkan password user Windows.

## 2. Verifikasi

```powershell
# Cek daftar tugas terjadwal yang akan dieksekusi:
php artisan schedule:list

# Test sekali jalan (akan jalankan command yang due saat ini):
php artisan schedule:run

# Test loop ala production (jalan terus, Ctrl+C untuk stop):
php artisan schedule:work
```

`schedule:list` outputnya akan mirip:

```
0 6 * * *      php artisan invoices:apply-fines     Next Due: 14 hours from now
0 6 1 * *      php artisan invoices:generate-spp    Next Due: 25 days from now
0 6 25 * *     php artisan sessions:generate-month  Next Due: 18 days from now
```

## 3. Trigger Manual (Tanpa Cron)

Kalau cron belum di-setup atau perlu run ad-hoc:

```powershell
# Generate sesi bulan tertentu
php artisan sessions:generate-month --year=2026 --month=6

# Generate SPP bulan tertentu
php artisan invoices:generate-spp --year=2026 --month=6

# Apply denda untuk bulan tertentu (default: bulan ini)
php artisan invoices:apply-fines --year=2026 --month=5

# Apply denda dengan tanggal acuan custom (untuk testing)
php artisan invoices:apply-fines --year=2026 --month=5 --as-of=2026-05-15
```

Atau lewat UI:
- `/sessions` → tombol "Generate Sesi Bulan" (Owner/Admin)
- `/invoices` → tombol "Generate SPP" + "Apply Denda" (Owner/Admin)

## 4. Troubleshooting

**Tugas tidak jalan**: cek di Task Scheduler → History tab. Pastikan path `php.exe`
benar — kalau PHP 8.4 atau version lain, sesuaikan.

**Permission error**: pastikan task dijalankan dengan user yang punya akses tulis ke
`storage/` dan `database/`.

**Database lock**: pakai `withoutOverlapping()` (sudah diset di console.php) supaya
job yang sama tidak jalan paralel.

**Lihat log**: `storage/logs/laravel.log` untuk error scheduler.







