# Kids Class Bundle — Cicilan 3 Termin UI

**Tanggal:** 2026-05-23
**Status:** Approved
**Scope:** UI only — backend logic (InvoiceService, lifecycle service) sudah lengkap

---

## Latar Belakang

Murid Kids Class Bundle bisa bayar dengan dua cara (BR-10.10):
- **FULL** — satu invoice lunas
- **INSTALLMENT** — 3 invoice cicilan (Termin 1: bulan ke-1, Termin 2: bulan ke-2, Termin 3: bulan ke-4)

Backend sudah lengkap: `InvoiceService::createKidsBundleInstallments()` membuat 3 invoice terikat `installment_group_id`. Radio button FULL/INSTALLMENT sudah ada di form aktivasi murid. Yang belum ada adalah **UI untuk melihat progress ketiga termin** setelah invoice dibuat.

---

## Yang Sudah Ada (Tidak Diubah)

- `Invoice::installment_label` → `"Termin 2/3"` (accessor)
- `Invoice::isInstallment()` (method)
- Badge "Termin X/3" di header `invoices/show.blade.php` (sudah ada, dipertahankan)
- Badge "Kids Bundle – Lunas" di header `invoices/show.blade.php` (sudah ada, dipertahankan)
- Radio button FULL/INSTALLMENT di `students/show.blade.php` (tidak diubah)

---

## Perubahan yang Diperlukan

### 1. Invoice Index (`invoices/index.blade.php`)

**Perubahan minimal** pada kolom keterangan di baris tabel:

- Untuk invoice `class_type = 'KIDS_CLASS_BUNDLE'` + `payment_mode = 'INSTALLMENT'`:
  tambah dua badge: `Kids Bundle` (warna ungu/purple) + `Termin X/3` (warna biru)
- Untuk invoice `class_type = 'KIDS_CLASS_BUNDLE'` + `payment_mode = 'FULL'`:
  tampilkan badge `Kids Bundle – Lunas` (warna ungu)
- Tabel tetap flat (tidak digrouping) — filter per bulan tetap bekerja normal
- Data sudah tersedia dari query yang ada (kolom `class_type`, `payment_mode`, `installment_number` sudah di-select)

**Badge colors (dark mode):**
- Kids Bundle: `bg-purple-50 text-purple-700` → override dark: `rgba(123,94,167,0.18)` / `#B09AD8`
- Termin X/3: `bg-blue-100 text-blue-700` → override dark: `rgba(58,97,134,0.18)` / `#7AAAC8`

### 2. Invoice Show — Panel Progress Cicilan (`invoices/show.blade.php`)

Panel baru disisipkan **antara header card dan ringkasan total**, hanya tampil jika `$invoice->isInstallment()`.

**Komponen panel:**
```
┌─ Cicilan Kids Class Bundle ──────────────────────────────────┐
│ 1 dari 3 termin lunas · Rp 113.333 dari Rp 340.000           │
│ [███████░░░░░░░░░░░░░░░░░░░░░░░░░] (progress bar 3 segmen)    │
│                                                               │
│ ● Termin 1/3  · 10 Jan 2026     Rp 113.333   [LUNAS]         │
│ ● Termin 2/3  · 10 Feb 2026 ←   Rp 113.333   [BELUM BAYAR]  │ ← highlight
│ ○ Termin 3/3  · 10 Apr 2026     Rp 113.334   [menunggu]      │
└───────────────────────────────────────────────────────────────┘
```

**Detail:**
- Progress bar: 3 segmen flex, warna hijau (PAID) / gold (termin pertama yang belum PAID, atau invoice yang sedang dibuka) / abu (termin sesudahnya)
- Di **invoice show**: segmen gold = termin yang sedang dibuka (berdasarkan `installment_number`)
- Di **student show**: segmen gold = termin pertama dengan status bukan PAID (termin yang perlu dibayar berikutnya)
- Baris aktif (= invoice yang sedang dibuka): highlight dengan `border border-mk-accent/20 bg-mk-accent/5`
- Baris termin lain yang invoicenya sudah ada: diklik → link ke `route('invoices.show', $siblingId)`
- Baris termin yang belum punya invoice (edge case): ditampilkan sebagai teks non-link

**Data source:**
- Di `InvoiceController@show`, tambah query:
  ```php
  $siblings = $invoice->installment_group_id
      ? Invoice::where('installment_group_id', $invoice->installment_group_id)
          ->orderBy('installment_number')
          ->get(['id', 'installment_number', 'total_amount', 'status', 'due_date'])
      : collect();
  ```
- Pass `$siblings` ke view

**Kalkulasi progress bar:**
- `$paidCount` = count siblings dengan status PAID
- `$totalInstallments` = 3 (fixed untuk KIDS_CLASS_BUNDLE)
- Subtitle: `"$paidCount dari 3 termin lunas · Rp {paid} dari Rp {total}"`

### 3. Student Show — Kartu Cicilan (`students/show.blade.php`)

Kartu baru ditambahkan **di atas tabel daftar invoice murid** (setelah section info/kelas, sebelum list tagihan di `students/show.blade.php`), **hanya tampil** jika enrollment primary murid adalah `KIDS_CLASS_BUNDLE` + `payment_mode = 'INSTALLMENT'`.

**Konten identik** dengan panel di invoice show, perbedaan:
- Semua tiga baris bisa diklik sebagai link ke masing-masing invoice (bukan hanya sibling)
- Baris aktif tidak di-highlight (tidak ada "invoice yang sedang dibuka")
- Jika semua termin PAID: tampilkan badge `Lunas ✓` di judul kartu

**Data source:**
- Di `StudentController@show`, tambah query jika enrollment primary adalah KIDS_CLASS_BUNDLE:
  ```php
  $kidsInstallments = null;
  $primaryEnrollment = $student->primaryEnrollment;
  if ($primaryEnrollment && $primaryEnrollment->package?->class_type === 'KIDS_CLASS_BUNDLE') {
      // Cari installment_group_id terbaru dari invoice murid ini
      $latestGroup = Invoice::where('student_id', $student->id)
          ->where('payment_mode', 'INSTALLMENT')
          ->whereNotNull('installment_group_id')
          ->latest('id')
          ->value('installment_group_id');

      if ($latestGroup) {
          $kidsInstallments = Invoice::where('installment_group_id', $latestGroup)
              ->orderBy('installment_number')
              ->get(['id', 'installment_number', 'total_amount', 'status', 'due_date']);
      }
  }
  ```
- Pass `$kidsInstallments` ke view (null jika tidak relevan)

---

## Edge Cases

| Kasus | Penanganan |
|-------|-----------|
| Invoice KIDS_CLASS_BUNDLE + FULL (bukan cicilan) | Panel tidak muncul. Badge "Kids Bundle – Lunas" di index tetap muncul. |
| `installment_group_id` null tapi `isInstallment()` true | Defensive: panel tidak muncul, badge termin tetap muncul di header show |
| Murid punya >1 Kids Bundle (selesai lalu mulai lagi) | Tampilkan bundle terbaru (`latest('id')`) |
| Sibling invoice belum di-generate | Baris termin tersebut tampil sebagai teks abu tanpa link |

---

## File yang Diubah

| File | Perubahan |
|------|-----------|
| `app/Http/Controllers/InvoiceController.php` | Tambah query `$siblings` di method `show()` |
| `app/Http/Controllers/StudentController.php` | Tambah query `$kidsInstallments` di method `show()` |
| `resources/views/invoices/index.blade.php` | Tambah badge Kids Bundle + Termin X/3 di kolom keterangan |
| `resources/views/invoices/show.blade.php` | Tambah panel progress cicilan |
| `resources/views/students/show.blade.php` | Tambah kartu cicilan di section tagihan |

**Tidak ada migration, tidak ada model baru, tidak ada route baru.**

---

## Testing

- Murid Kids Bundle INSTALLMENT: panel muncul di invoice show + kartu di student show
- Murid Kids Bundle FULL: panel tidak muncul, badge "Kids Bundle – Lunas" muncul di index
- Murid reguler: tidak ada perubahan tampilan
- Invoice Termin 2 dibuka: Termin 1 link ke invoice T1, Termin 3 tampil tanpa link (belum jatuh tempo bayar bulan ke-4)
- Student show: klik baris termin → navigasi ke invoice yang benar
