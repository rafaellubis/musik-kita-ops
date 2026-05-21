# Spec: Redesign Print — Invoice & Kuitansi
**Tanggal:** 2026-05-20
**Status:** Approved (mockup final disetujui user)

---

## Lingkup

Tiga dokumen cetak ada di sistem. Hanya dua yang diubah:

| Dokumen | File | Perubahan |
|---------|------|-----------|
| Invoice | `resources/views/invoices/print.blade.php` | ✅ Redesign |
| Kuitansi | `resources/views/payments/receipt.blade.php` | ✅ Update branding |
| Slip Honor | `resources/views/honors/print.blade.php` | ❌ Tidak diubah |

---

## 1. Invoice — Redesign

### Ukuran
- Dari: A4 penuh (`min-height: 297mm`)
- Ke: Half A4 portrait (`min-height: 148mm`, `max-width: 210mm`)
- Print CSS disesuaikan untuk half A4

### Palet Warna
Ganti seluruh warna biru cold (`#1e40af`) ke warm Musik KITA palette:

| Elemen | Lama | Baru |
|--------|------|------|
| Border bawah header | `#1e40af` | `#D4A853` (gold) |
| Header background | `#fff` | `#FBF5EC` (cream) |
| Nama studio / judul | `#1e40af` | `#7A3B00` (cokelat tua) |
| Section label | `#777` | `#9B5E00` |
| Table header bg | `#f1f5f9` | `#FBF0DC` |
| Table header text | `#475569` | `#9B5E00` |
| Table border | `#ddd` | `#F5EBD0` |
| Summary border top | `#1e40af` | `#D4A853` |
| Footer background | `#fff` | `#FBF5EC` |
| Footer text | `#666` | `#9B5E00` |
| Toolbar button | `#1e40af` | `#D4A853` (teks `#1A1000`) |

Status pill tetap semantik: UNPAID=merah, PARTIAL=kuning, PAID=hijau.

### Header Kiri
```
[Logo Musik KITA — dari asset('images/logo-musikkita-light-mode.PNG'), height 44px]
[tag border gold] Les Musik · Toko Alat Musik
[pin icon] Ruko Serpong Garden 1 Ruko 2 No. 19, Tangerang - Banten
[WA icon SVG] 0816-92-05-92
```

### Header Kanan
```
INVOICE          ← 16pt, bold, #7A3B00
INV/YYYY/MM/NNNN ← monospace, 8.5pt
[status pill]
```

### Body — Info Murid
Tampilkan tanpa label "Tagihan Untuk":
- Nama murid (bold)
- Kode murid (monospace, muted)
- Nomor telepon murid (jika ada)

**Dihapus:** baris "Orang tua: ..."

### Body — Tanggal (kanan atas)
- Tanggal Terbit
- Jatuh Tempo
- Periode

### Body — Tabel Rincian
Kolom: Kode | Deskripsi | Jumlah
Styling: header cream gold, border F5EBD0

### Body — Ringkasan (kanan bawah)
```
Total Tagihan    Rp xxx
Sudah Dibayar    Rp xxx   ← hijau
TOTAL            Rp xxx   ← bold, border-top gold (#D4A853)
```

**Catatan:** Label row ketiga adalah "TOTAL" (bukan "SALDO").
Warna TOTAL: selalu bold dark (`#2C1A07`) — status sudah dikomunikasikan oleh status pill di header.
Nilai yang ditampilkan: `$invoice->balance` (sisa tagihan setelah pembayaran).

**Dihapus:** tabel Riwayat Pembayaran

### Footer
- Kiri: "Pembayaran: CASH di studio / TRANSFER (hubungi admin)"
- Kanan: timestamp cetak
- Background: `#FBF5EC`, teks `#9B5E00`

---

## 2. Kuitansi — Update Branding

### Ukuran
Tidak berubah: half A4 portrait (`min-height: 148mm`)

### Warna
Tidak berubah: green theme (`#166534`)

### Header Kiri — Perubahan
Tambah branding di bawah logo:
```
[Logo Musik KITA — height 44px]  ← BARU: logo asli (sebelumnya ada placeholder teks)
[tag border hijau] Les Musik · Toko Alat Musik  ← BARU
[pin icon hijau] Ruko Serpong Garden 1 Ruko 2 No. 19, Tangerang - Banten  ← BARU
[WA icon SVG] 0816-92-05-92  ← BARU (ganti teks "— alamat dan kontak studio —")
```

### Header Kanan
Tidak berubah: "KUITANSI" + nomor KW/YYYY/MM/NNNN

### Body
Tidak berubah secara struktural. Cleanup minor:
- Hapus titik dua `:` di akhir label grid (konsistensi)
- Kota di signature block: ganti hardcode "Jakarta" → "Tangerang"

### Elemen yang Tidak Berubah
- Amount box hijau menonjol
- Field terbilang
- Signature block kanan bawah
- VOID stamp overlay
- Footer "Kuitansi ini sah dan diterbitkan elektronik"

---

## 3. Slip Honor
**Tidak ada perubahan.** Di luar lingkup spec ini.

---

## File yang Disentuh
```
resources/views/invoices/print.blade.php   ← full redesign
resources/views/payments/receipt.blade.php ← update branding & logo
```

Tidak ada perubahan pada: controller, routes, model, migration.

---

## Catatan Implementasi
- Logo: pakai `asset('images/logo-musikkita-light-mode.PNG')` — sama seperti sebelumnya
- WhatsApp icon: SVG inline (tidak perlu library eksternal)
- Pin icon: SVG inline
- Tidak ada dependency baru (tetap browser-native print, bukan PDF library)
- Tidak ada perubahan permission/middleware
