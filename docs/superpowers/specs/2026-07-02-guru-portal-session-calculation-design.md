# Spesifikasi Desain: Perbaikan Penghitungan Sesi Terlaksana di Portal Guru
**Tanggal:** 2 Juli 2026  
**Status:** Draft - Menunggu Review  
**Modul Terkait:** M10 (Guru Portal) & M06 (Honor Guru)  

---

## 1. Latar Belakang & Masalah
Pada halaman beranda (dashboard) portal guru, terdapat metrik **Sesi Terlaksana** untuk bulan berjalan. Saat ini, metrik tersebut dihitung menggunakan query ad-hoc langsung pada controller:
- Query tersebut mencakup status `IZIN_RESCHEDULE` dan `IZIN_PENDING` yang secara fisik belum terlaksana dan tidak berhak mendapatkan honor pada sesi asli tersebut. Hal ini menyebabkan terjadinya perhitungan ganda (*double counting*) ketika sesi penggantinya terlaksana.
- Query tersebut menggunakan pencocokan sederhana `orWhere` untuk kolom `teacher_id` dan `substitute_teacher_id` tanpa mengecualikan status `DIGANTI` dengan benar. Ini mengakibatkan sesi pengganti terhitung untuk kedua belah pihak: guru utama yang digantikan (tidak mengajar) dan guru pengganti.
- Query tersebut mengecualikan status `LIBUR`. Padahal secara aturan bisnis (BR-4.10), hari libur nasional tetap mendapatkan honor penuh bagi guru pengajar.

Sesuai kesepakatan, penghitungan **Sesi Terlaksana** akan diselaraskan dengan **Opsi B (Finansial)**: menghitung seluruh sesi yang berhak mendapatkan honor bagi guru yang bersangkutan pada bulan berjalan.

---

## 2. Pendekatan Desain
Untuk menghindari pengulangan kode (*dry violation*) dan menjaga konsistensi kalkulasi di masa mendatang, logika penentuan sesi berbayar akan dipindahkan ke dalam **Eloquent Query Scopes** pada model `ClassSession`.

### A. Penambahan Query Scopes pada `ClassSession` Model
Dua scope baru akan dibuat pada model [ClassSession](file:///C:/laragon/www/musik-kita-ops/app/Models/ClassSession.php):
1. `scopeHonoredForTeacher($query, int $teacherId)`: Menyaring sesi yang secara finansial menjadi hak guru yang bersangkutan (guru asli jika tidak ada pengganti, atau guru pengganti itu sendiri).
2. `scopeHonoredStatus($query)`: Menyaring sesi yang memiliki status berbayar dan sudah terabsensi/terkonfirmasi (`honor_code` tidak NULL).

### B. Pembaruan Query di `GuruController`
Query ad-hoc di [GuruController](file:///C:/laragon/www/musik-kita-ops/app/Http/Controllers/GuruController.php) akan digantikan dengan pemanggilan scope baru tersebut.

### C. Refaktorisasi `HonorCalculationService` (Pencegahan Over-engineering & Konsistensi)
Untuk mencegah redundansi kode dan memastikan single-source-of-truth, query di [HonorCalculationService](file:///C:/laragon/www/musik-kita-ops/app/Services/HonorCalculationService.php) akan diperbarui untuk memanfaatkan scope baru yang telah didefinisikan di model.

---

## 3. Detail Perubahan Kode

### A. Model: [ClassSession.php](file:///C:/laragon/www/musik-kita-ops/app/Models/ClassSession.php)
Tambahkan dua method scope berikut di dalam class `ClassSession`:

```php
    /**
     * Scope untuk memfilter sesi yang honornya diberikan kepada guru tertentu.
     * Guru berhak mendapatkan honor jika dia guru asli dan tidak ada guru pengganti,
     * ATAU dia adalah guru pengganti pada sesi tersebut.
     */
    public function scopeHonoredForTeacher($query, int $teacherId)
    {
        return $query->where(function ($q) use ($teacherId) {
            $q->where(function ($qq) use ($teacherId) {
                $qq->where('teacher_id', $teacherId)
                   ->whereNull('substitute_teacher_id');
            })->orWhere('substitute_teacher_id', $teacherId);
        });
    }

    /**
     * Scope untuk memfilter sesi yang statusnya menghasilkan honor (berbayar).
     * Mengecualikan status SCHEDULED, IZIN_RESCHEDULE, IZIN_PENDING, dan CANCELLED.
     * Serta memastikan honor_code sudah terisi (terkonfirmasi/terabsensi).
     */
    public function scopeHonoredStatus($query)
    {
        return $query->whereNotIn('status', [
            self::STATUS_SCHEDULED,
            self::STATUS_IZIN_RESCHEDULE,
            self::STATUS_IZIN_PENDING,
            self::STATUS_CANCELLED,
        ])->whereNotNull('honor_code');
    }
```

### B. Controller: [GuruController.php](file:///C:/laragon/www/musik-kita-ops/app/Http/Controllers/GuruController.php)
Perbarui baris kode penghitungan `$totalSesiBulan` di dalam method `dashboard()`:

```diff
-        // Total sesi terlaksana bulan ini (tidak hitung CANCELLED, LIBUR, atau belum terjadi)
-        $totalSesiBulan = ClassSession::where(function ($q) use ($teacher) {
-                $q->where('teacher_id', $teacher->id)
-                  ->orWhere('substitute_teacher_id', $teacher->id);
-            })
-            ->whereBetween('session_date', [$startBulan, $endBulan])
-            ->whereNotIn('status', ['CANCELLED', 'LIBUR', 'SCHEDULED'])
-            ->count();
+        // Total sesi terlaksana bulan ini (sesuai sesi berbayar pada slip honor)
+        $totalSesiBulan = ClassSession::honoredForTeacher($teacher->id)
+            ->whereBetween('session_date', [$startBulan, $endBulan])
+            ->honoredStatus()
+            ->count();
```

### C. Service: [HonorCalculationService.php](file:///C:/laragon/www/musik-kita-ops/app/Services/HonorCalculationService.php)
Gunakan scope baru untuk merapikan query di `calculateForTeacher` dan `getSessionBreakdown`.

Contoh perubahan pada `calculateForTeacher`:
```diff
-        $sessions = ClassSession::query()
-            ->where(function ($q) use ($teacher) {
-                $q->where(function ($qq) use ($teacher) {
-                    $qq->where('teacher_id', $teacher->id)
-                       ->whereNull('substitute_teacher_id');
-                })->orWhere('substitute_teacher_id', $teacher->id);
-            })
-            ->whereYear('session_date', $year)
-            ->whereMonth('session_date', $month)
-            ->whereNotIn('status', [
-                ClassSession::STATUS_SCHEDULED,
-                ClassSession::STATUS_IZIN_RESCHEDULE,
-                ClassSession::STATUS_IZIN_PENDING,
-                ClassSession::STATUS_CANCELLED,
-            ])
-            // DIGANTI two-phase: honor belum dikonfirmasi (null) tidak dihitung
-            ->whereNotNull('honor_code')
-            ->get(['id', 'honor_code', 'honor_amount', 'session_date', 'status',
-                   'teacher_id', 'substitute_teacher_id', 'student_id', 'enrollment_id']);
+        $sessions = ClassSession::query()
+            ->honoredForTeacher($teacher->id)
+            ->whereYear('session_date', $year)
+            ->whereMonth('session_date', $month)
+            ->honoredStatus()
+            ->get(['id', 'honor_code', 'honor_amount', 'session_date', 'status',
+                   'teacher_id', 'substitute_teacher_id', 'student_id', 'enrollment_id']);
```

Dan lakukan penyelarasan serupa pada method `getSessionBreakdown()`.

---

## 4. Rencana Verifikasi & Pengujian
Kita akan melakukan pengujian otomatis dan pengujian manual untuk menjamin kebenaran kode:

### A. Pengujian Otomatis (Unit Testing)
Jalankan test suite aplikasi untuk memastikan perubahan query scopes tidak memecahkan tes yang ada:
```bash
php artisan test
```

### B. Pengujian Manual (UAT)
1. **Pengecekan Dashboard Guru:**
   - Login sebagai guru.
   - Periksa angka "Sesi Terlaksana" bulan ini pada dashboard.
   - Bandingkan dengan jumlah sesi berbayar pada menu Slip Honor guru tersebut. Hasilnya harus 100% cocok.
2. **Pengecekan Kasus Khusus:**
   - Pastikan sesi dengan status `IZIN_RESCHEDULE` atau `IZIN_PENDING` tidak menambah angka sesi terlaksana.
   - Pastikan sesi dengan status `DIGANTI` hanya terhitung untuk guru pengganti yang bersangkutan (setelah terkonfirmasi), bukan guru utama yang digantikan.
   - Pastikan sesi `LIBUR` (nasional) terhitung jika guru tersebut berhak mendapatkan honor (tipe non-internal).
