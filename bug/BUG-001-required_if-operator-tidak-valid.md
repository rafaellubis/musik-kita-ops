# BUG-001 — `required_if` dengan Operator `>` Tidak Valid di Laravel

**Status:** FIXED
**Tanggal ditemukan:** 2026-05-08
**Ditemukan saat:** Testing M06 (HonorController)
**Diperbaiki di commit:** `fa1bf23`

---

## Deskripsi Bug

Di `HonorController::update()`, validasi untuk field `other_honor_note` menggunakan rule:

```php
// KODE SALAH
'other_honor_note' => 'nullable|string|max:255|required_if:other_honor,>,0',
```

Tujuannya: jika `other_honor > 0`, maka `other_honor_note` wajib diisi.

Namun Laravel **tidak mendukung operator perbandingan** (`>`, `<`, `>=`) di rule `required_if`.
Syntax resmi `required_if` hanya mendukung pencocokan nilai eksak:

```
required_if:field,value         → wajib jika field == value
required_if:field,value1,value2 → wajib jika field == value1 ATAU value2
```

String `>,0` diinterpretasikan secara literal (bukan sebagai operator), sehingga kondisi
tidak pernah terpenuhi → validasi **tidak jalan sama sekali**.

---

## Dampak

| Skenario | Expected | Aktual (buggy) |
|---|---|---|
| `other_honor = 100.000`, note kosong | Gagal (note wajib diisi) | **PASS** (lolos tanpa note) |
| `other_honor = 0`, note kosong | Pass | **FAIL** (ditolak padahal seharusnya boleh) |

Konsekuensinya: Owner bisa menyimpan slip dengan `other_honor > 0` tanpa keterangan,
melanggar business rule BRD M06 revisi v1.1.

---

## Root Cause

Kesalahan asumsi: mengira `required_if` support operator `>` seperti validasi kondisional
di framework lain. Laravel tidak punya sintaks ini di rule string biasa.

---

## Fix

Hapus `required_if` dari rule string, ganti dengan pengecekan manual setelah `validate()`:

```php
// KODE BENAR — di HonorController::update()
$data = $request->validate([
    'transport_honor'  => 'required|integer|min:0|max:99999999',
    'other_honor'      => 'required|integer|min:0|max:99999999',
    'other_honor_note' => 'nullable|string|max:255',  // required_if dihapus
]);

// required_if Laravel tidak support operator > — validasi manual
if ((int) $data['other_honor'] > 0 && empty(trim($data['other_honor_note'] ?? ''))) {
    return back()
        ->withErrors(['other_honor_note' => 'Keterangan lain-lain wajib diisi jika ada honor lain-lain.'])
        ->withInput();
}
```

---

## Cara Hindari di Masa Depan

Jika butuh validasi kondisional dengan **operator perbandingan**, gunakan salah satu dari:

```php
// Opsi 1: Rule closure (paling fleksibel)
'other_honor_note' => [
    'nullable', 'string', 'max:255',
    \Illuminate\Validation\Rule::requiredIf(fn() => (int)$request->other_honor > 0),
],

// Opsi 2: Manual check setelah validate() — lebih mudah dibaca pemula
if ($data['other_honor'] > 0 && empty($data['other_honor_note'])) {
    return back()->withErrors([...])->withInput();
}
```

File terdampak: `app/Http/Controllers/HonorController.php`
