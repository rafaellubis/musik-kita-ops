# Spec: Laporan Keuangan — Cetak PDF Profesional
**Tanggal**: 2026-07-02
**Modul**: M09 Laporan Keuangan
**Status**: Approved — siap implementasi

---

## 1. Latar Belakang & Masalah

Fitur cetak laporan keuangan saat ini menggunakan `window.print()` langsung dari halaman
`/reports/finance` yang di-render via `x-app-layout`. Akibatnya:

- Navigasi, sidebar, dan elemen UI ikut tercetak (tidak clean)
- Tidak ada kontrol halaman (page break, header/footer per halaman)
- Tampilan tidak mencerminkan standar laporan keuangan manajemen
- Berbeda dengan invoice/honor/staff-payroll yang sudah punya standalone print view

---

## 2. Tujuan

Menghasilkan cetakan PDF laporan keuangan bulanan yang:
1. **Bersih** — tidak ada elemen UI aplikasi yang ikut tercetak
2. **Profesional** — mengikuti struktur Laporan Laba Rugi manajemen standar
3. **Lengkap** — mencakup semua komponen keuangan (P&L, honor guru, gaji staff, pengeluaran operasional, petty cash)
4. **Konsisten** — menggunakan visual brand yang sama dengan slip honor/invoice yang ada

---

## 3. Cakupan (Scope)

**Dalam scope:**
- Route dan controller baru untuk standalone print view laporan keuangan
- View `finance-print.blade.php` — standalone HTML, multi-halaman
- Tombol "Cetak PDF" di halaman `/reports/finance` diubah ke link route baru

**Di luar scope:**
- Upgrade cetak slip honor guru, slip gaji staff, invoice
- Grafik/chart visual di laporan
- Export ke format Excel/CSV

---

## 4. Arsitektur & Alur

### Route

```
GET /reports/finance?year=&month=        → ReportController::finance()      (tidak berubah)
GET /reports/finance/print?year=&month= → ReportController::financePrint()  (BARU)
```

### Controller

Ekstrak query ke method private `buildFinanceData(int $year, int $month): array`.
Kedua method public memanggil private method ini — tidak ada duplikasi logika bisnis.

```php
private function buildFinanceData(int $year, int $month): array { /* semua query */ }

public function finance(Request $request) {
    $data = $this->buildFinanceData($year, $month);
    return view('reports.finance', $data);
}

public function financePrint(Request $request) {
    $data = $this->buildFinanceData($year, $month);
    return view('reports.finance-print', $data);
}
```

### View

File baru: `resources/views/reports/finance-print.blade.php`
- Standalone HTML (tidak extend `x-app-layout`)
- Font via Google Fonts CDN (Playfair Display + Hanken Grotesk)
- Auto-print on load: `window.onload = () => window.print()`
- Tombol cetak + link kembali yang hilang saat cetak (`no-print`)

---

## 5. Struktur Halaman PDF

Format: A4 Portrait, margin 15mm. Footer tiap halaman: halaman X + nama studio + tanggal cetak.

### Halaman 1 — Laporan Laba Rugi

Header: Logo (kiri) | Laporan Keuangan Bulanan · Periode (kanan)

Isi:
- **PENDAPATAN**: breakdown per item_code (SPP, REG, CUTI, UJI, MC, DENDA, dll) dengan jumlah invoice dan nominal
- **Breakdown Metode**: Cash | Transfer | QRIS | Debit
- **BEBAN**: Honor Guru (paid), Gaji Staff (paid), Pengeluaran Operasional, Isi Petty Cash
- **LABA / RUGI BERSIH** — double-border, warna hijau (laba) / merah (rugi)
- Catatan metodologi singkat di bawah

### Halaman 2 — Rincian Honor Guru

Tabel: No | Nama Guru | Honor Pokok | Transport | Lain-lain | Total | Status
Footer tabel: baris TOTAL + catatan "honor belum PAID tidak masuk P&L"
Jika ada guru tanpa slip bulan ini: daftar nama di bawah tabel

### Halaman 3 — Gaji Staff & Pengeluaran Operasional

Bagian atas: Tabel gaji staff (Nama | Posisi | Gaji Pokok | Tunjangan | Potongan | Bersih | Status)
Bagian bawah: Tabel pengeluaran operasional (Kategori | Transaksi | Total | %)

### Halaman 4 — Petty Cash

Ringkasan: Top-up bulan ini / Pengeluaran bulan ini / Saldo Akhir
Tabel: Tanggal | Kategori | Keterangan | Jumlah

---

## 6. Desain Visual

Konsisten dengan `honors/print.blade.php` (desain terbaik di sistem saat ini).

| Elemen | Spesifikasi |
|--------|-------------|
| Font heading | Playfair Display, 700 |
| Font body | Hanken Grotesk, 400/600 |
| Warna primer | `#4A1F0A` (coklat tua) |
| Warna aksen | `#5DB890` (hijau) |
| Warna muted | `#7A3818` |
| Border header | `2.5px solid #4A1F0A` |
| Angka keuangan | `font-family: monospace`, rata kanan |
| Laba positif | `color: #166534` |
| Rugi / beban | `color: #b91c1c` |
| Background cetak | `#fff` |
| Background browser | `#F5F9F7` |

Footer tiap halaman via CSS `@page` counter — nomor halaman otomatis tanpa JS.

---

## 7. Perubahan File

| File | Aksi | Keterangan |
|------|------|------------|
| `app/Http/Controllers/ReportController.php` | Modifikasi | Ekstrak query ke `buildFinanceData()`, tambah `financePrint()` |
| `routes/web.php` | Modifikasi | Route `GET /reports/finance/print` |
| `resources/views/reports/finance.blade.php` | Modifikasi | Tombol cetak → link ke route baru (target="_blank") |
| `resources/views/reports/finance-print.blade.php` | **BARU** | Standalone print view 4 halaman |

---

## 8. Keputusan Desain

| Keputusan | Alasan |
|-----------|--------|
| Standalone HTML | Kontrol penuh CSS cetak, tidak ada nav/sidebar yang bocor |
| Auto-print on load | UX paling clean — satu klik langsung dialog print muncul |
| `buildFinanceData()` private method | Ponytail: tidak ada duplikasi logika, satu source of truth |
| Nomor halaman via CSS `@page counter` | Native browser, tidak perlu JS tambahan |
| Honor/staff: hanya PAID masuk P&L | Konsisten dengan logika existing `finance()` |
| Tanda tangan: tidak diperlukan | Laporan untuk arsip digital, bukan dokumen fisik |

---

## 9. Catatan Implementasi

- Logo: `asset('images/logo-musikkita-light-mode.PNG')` — konsisten dengan print view lain
- Route gunakan middleware `auth` + `role:Owner|Admin|Auditor`
- Kolom `session_date` tidak punya cast date — gunakan `Carbon::parse()` jika diperlukan
- Git commit: `[M09] Laporan keuangan: standalone PDF print view profesional`
