# Spec: Edge Case Guards — Preventive Validation Layer
**Tanggal:** 2026-05-15
**Status:** Approved
**Pendekatan:** Preventive Guards di Service Layer (Pendekatan 1)

---

## Latar Belakang

Audit kode menemukan 10 gap validasi yang dapat merusak integritas data pada kondisi tertentu. Semua gap dikelompokkan dalam 3 cluster: Lifecycle Murid, Keuangan, dan Penjadwalan.

Pola yang dipakai konsisten dengan kode yang sudah ada di `PaymentService`:
```
Controller → Service → Guard (throw InvalidArgumentException jika gagal) → Action → Cascade
```

Error dari `InvalidArgumentException` ditangkap di Controller dan ditampilkan sebagai flash message error ke user — tidak crash, tidak diam-diam gagal.

---

## Cluster A: Lifecycle Guards

**File:** `app/Services/StudentLifecycleService.php`

### Gap 1 — `mundurkan()`: Tidak cancel sesi SCHEDULED
**Severity:** 🔴 High

**Masalah:** Setelah enrollment di-close, sesi `ClassSession` dengan `status=SCHEDULED` untuk bulan mendatang tetap ada di database. Sesi ini muncul di halaman absensi padahal murid sudah mundur.

**Fix:** Setelah enrollment di-set INACTIVE, query semua `ClassSession` terkait enrollment tersebut dengan `status = SCHEDULED` dan update ke status `CANCELLED` dengan notes `"Murid mengundurkan diri — sesi dibatalkan otomatis"`.

Alasan pakai CANCELLED (bukan delete): menjaga audit trail history sesi.

**Lokasi implementasi:** Dalam `DB::transaction()` di method `mundurkan()`, setelah loop close enrollment.

---

### Gap 2 — `selesai()`: Tidak cek invoice UNPAID sebelum graduasi
**Severity:** 🔴 High

**Masalah:** Kids Class bisa di-graduasi (status → SELESAI) meskipun masih ada cicilan installment yang belum lunas.

**Fix:** Tambahkan guard sebelum `ensureTransition()`:
```php
if ($student->invoices()->whereIn('status', ['UNPAID', 'PARTIAL'])->exists()) {
    throw new InvalidArgumentException(
        'Murid masih punya tagihan yang belum lunas. Selesaikan semua tagihan sebelum menandai lulus.'
    );
}
```

---

### Gap 3 — `ajukanCuti()`: Tidak cek invoice UNPAID bulan berjalan
**Severity:** 🔴 High

**Masalah:** Murid bisa masuk status CUTI meskipun SPP bulan berjalan belum dibayar. Setelah CUTI, tidak ada mekanisme penagihan otomatis — hutang bisa tertinggal.

**Fix:** Tambahkan guard sebelum proses cuti:
```php
$unpaidCurrentMonth = $student->invoices()
    ->whereIn('status', ['UNPAID', 'PARTIAL'])
    ->whereHas('items', fn($q) => $q->where('item_code', 'SPP'))
    ->exists();

if ($unpaidCurrentMonth) {
    throw new InvalidArgumentException(
        'Selesaikan tagihan SPP bulan berjalan sebelum mengajukan cuti.'
    );
}
```

---

### Gap 4 — `aktifkanKembali()`: Tidak cek hutang dari periode sebelum mundur
**Severity:** 🟡 Medium

**Masalah:** Murid yang pernah mundur bisa re-aktivasi tanpa menyelesaikan hutang lama.

**Fix:** Warning (bukan block keras) karena kasusnya bisa bermacam-macam (data impor, write-off, dll). Tampilkan informasi invoice lama yang masih UNPAID melalui flash message warning, tapi proses re-aktivasi tetap boleh dilanjutkan. Owner yang memutuskan.

**Implementasi:** Query invoice UNPAID sebelum re-aktivasi. Jika ada, tambahkan ke session flash sebagai warning (bukan error).

---

## Cluster B: Financial Guards

**File:** `app/Services/InvoiceService.php`

### Gap 5 — `recalcStatus()`: Tidak recalc `total_amount` dari items
**Severity:** 🔴 Critical

**Masalah:** `recalcStatus()` hanya update `paid_amount` dan `status`, tidak recalc `total_amount`. Scenario corrupt:
1. Invoice SPP Rp 400.000 dibuat → `total_amount = 400.000`
2. Denda Rp 25.000 ditambah → `total_amount = 425.000`
3. Payment di-void → `paid_amount = 0`, `total_amount = 425.000` (masih benar)
4. Admin hapus item DENDA manual → `items sum = 400.000`
5. `total_amount` masih `425.000` ← **data corrupt**

**Fix:** Tambahkan satu baris di awal `recalcStatus()` sebelum kalkulasi status:
```php
// Selalu sync total_amount dari items agar tidak corrupt
$invoice->update(['total_amount' => $invoice->items()->sum('amount')]);
$invoice->refresh();
```

---

### Gap 6 — `generateMonthlySPP()`: Edge case murid CUTI dengan enrollment masih ACTIVE
**Severity:** 🟡 Medium

**Masalah:** Murid yang baru masuk CUTI di pertengahan bulan memiliki `students.status = 'Cuti'` (sudah ter-skip karena filter `status='Aktif'`). Namun edge case terjadi jika lifecycle action CUTI belum sempat menutup enrollment — enrollment masih ACTIVE sementara student sudah CUTI.

**Fix:** Tambahkan filter enrollment `end_date IS NULL` pada query murid di `generateMonthlySPP()`:
```php
->with(['enrollments' => fn($q) => $q->where('status', 'ACTIVE')
    ->whereNull('end_date')
    ->with('package.instrument')])
```

---

### Gap 7 — `applyLateFines()`: Denda dikenakan ke invoice cicilan INSTALLMENT
**Severity:** 🟡 Medium

**Masalah:** Invoice Kids Class Bundle dengan `payment_mode = INSTALLMENT` memiliki struktur due date sendiri (termin 1, 2, 4). Denda Rp 5.000/hari tidak berlaku untuk cicilan ini.

**Fix:** Tambahkan filter pada query di `applyLateFines()`:
```php
->where(fn($q) => $q
    ->where('payment_mode', '!=', Invoice::MODE_INSTALLMENT)
    ->orWhereNull('payment_mode')
)
```

---

## Cluster C: Scheduling Guards

**File:** `app/Services/SessionGeneratorService.php` dan `app/Http/Controllers/TeacherController.php`

### Gap 8 — `generateForMonth()`: Tidak filter status murid
**Severity:** 🔴 High

**Masalah:** Generator cek `enrollment.status = ACTIVE` tapi tidak cek `student.status`. Jika murid mundur setelah enrollment belum sempat ditutup, sesi bulan depan tetap ter-generate.

**Fix:** Tambahkan `whereHas` pada query schedule di generator:
```php
->whereHas('enrollment.student', fn($q) => $q->where('status', 'Aktif'))
```

---

### Gap 9 — `generateForSchedule()`: Off-by-one pada `end_date`
**Severity:** 🟡 Medium

**Masalah:** Kondisi `$date->gt($enrollment->end_date)` artinya strictly greater than. Jika `end_date = 2026-05-15` dan generator jalan di tanggal yang sama, sesi masih ter-generate pada hari itu.

**Fix:** Ganti `gt()` ke `gte()`:
```php
// Sebelum
if ($enrollment->end_date && $date->gt($enrollment->end_date))

// Sesudah
if ($enrollment->end_date && $date->gte($enrollment->end_date))
```

---

### Gap 10 — Teacher deactivation: Tidak ada cascade ke sesi SCHEDULED
**Severity:** 🔴 Critical

**Masalah:** `TeacherController` hanya set `is_active = false` tanpa memeriksa enrollment aktif atau sesi masa depan. Guru yang di-nonaktifkan meninggalkan sesi SCHEDULED orphan di bulan depan.

**Fix:** Buat `app/Services/TeacherService.php` dengan method `deactivate(Teacher $teacher)`:

**Logic deactivation:**
1. **Block** jika guru masih punya enrollment ACTIVE:
   ```
   "Guru masih mengajar X murid aktif. Pindahkan murid ke guru lain sebelum menonaktifkan."
   ```
2. **Warning** jika ada sesi SCHEDULED di masa depan (tanpa enrollment aktif — sesi dari reschedule atau rapel): tambahkan notes `"Guru [nama] dinonaktifkan [tanggal] — perlu pengganti"` ke setiap sesi tersebut.
3. Set `is_active = false` + catat di audit log.

**Alasan tidak auto-delete sesi:** Admin perlu assign guru pengganti secara manual. Menghapus sesi berarti menghilangkan jadwal murid tanpa notifikasi.

`TeacherController` method yang memanggil toggle is_active diganti untuk memanggil `TeacherService::deactivate()`.

---

## Ringkasan Perubahan File

| File | Perubahan |
|------|-----------|
| `app/Services/StudentLifecycleService.php` | Gap 1–4: guards + cascade cancel sessions |
| `app/Services/InvoiceService.php` | Gap 5–7: recalcStatus fix + 2 query filter |
| `app/Services/SessionGeneratorService.php` | Gap 8–9: filter student status + gte fix |
| `app/Services/TeacherService.php` *(baru)* | Gap 10: deactivation cascade logic |
| `app/Http/Controllers/TeacherController.php` | Gap 10: delegate ke TeacherService |

**Total:** 5 file, 10 guard baru, 1 service baru.

---

## Urutan Implementasi (Prioritas)

1. **Gap 5** — `recalcStatus()` total_amount fix *(Critical, 1 baris, zero risk)*
2. **Gap 7** — `applyLateFines()` skip INSTALLMENT *(mencegah denda salah)*
3. **Gap 1** — `mundurkan()` cancel SCHEDULED sessions *(High, cascade cleanup)*
4. **Gap 2** — `selesai()` block jika ada invoice UNPAID *(High, data integrity)*
5. **Gap 3** — `ajukanCuti()` block jika ada invoice UNPAID *(High, financial)*
6. **Gap 8** — Generator filter status murid *(High, scheduling)*
7. **Gap 10** — TeacherService deactivation cascade *(Critical, new service)*
8. **Gap 9** — Off-by-one end_date *(Medium, satu karakter)*
9. **Gap 6** — SPP generator end_date filter *(Medium)*
10. **Gap 4** — Re-aktivasi warning hutang lama *(Medium, warning saja)*

---

## Perubahan Schema Database

**1 migration diperlukan:** Tambah nilai `CANCELLED` ke enum `class_sessions.status`.

Status enum saat ini: `SCHEDULED|HADIR|HADIR_TERLAMBAT|IZIN_RESCHEDULE|IZIN_VIDEO|HANGUS|LIBUR|DIGANTI`

Status setelah migration: tambah `CANCELLED` di akhir enum.

`CANCELLED` berbeda dari `HANGUS`:
- `HANGUS` = murid no-show, sesi dianggap terlaksana, honor guru tetap dibayar
- `CANCELLED` = sesi dibatalkan sistem (murid mundur/guru nonaktif), honor = 0

---

## Yang Tidak Diubah

- Tidak ada perubahan API / route
- Tidak ada perubahan UI — error message ditangani flash message yang sudah ada
- Semua fix di layer service/query kecuali 1 migration enum di atas
