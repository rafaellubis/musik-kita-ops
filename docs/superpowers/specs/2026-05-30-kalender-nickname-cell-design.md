# Design Spec: Nickname Murid di Cell Kalender Jadwal

**Tanggal:** 2026-05-30  
**Modul:** Kalender Jadwal (`/kalender`)  
**Status:** Implemented — 2026-05-30

---

## Ringkasan

Tampilkan identifier murid (nickname) di setiap cell sesi pada grid kalender mingguan, agar murid multi-kelas mudah dikenali. Jika nickname kosong, fallback ke **kata pertama `full_name`**.

---

## Latar Belakang

- Kalender saat ini menampilkan **semua sesi** dari semua enrollment (bukan primary-only).
- Cell hanya menampilkan `Instrumen · Guru` — sulit membedakan sesi milik murid yang sama dengan 2+ kelas aktif.
- Pola serupa sudah dipakai di halaman absensi (`absensi/_row.blade.php`).

---

## Keputusan Desain

| Aspek | Keputusan |
|-------|-----------|
| Identifier di cell | Nickname murid |
| Fallback nickname kosong | Kata pertama `full_name` (opsi A) |
| Format baris 1 cell | `{label} · {Instrumen} · {Guru}` |
| Format baris 2 cell | `{Ruangan} · {Jam}` (tidak berubah) |
| Popup detail | Tambah nickname jika ada (dengan tanda kutip) |
| Filter primary/secondary | Tidak — semua enrollment tetap tampil |
| Perubahan query/controller | Tidak — `student` sudah di-eager load |

---

## Helper Label Murid

Logika penentuan label ditampilkan (Blade `@php` inline atau helper kecil):

```php
// Prioritas: nickname → kata pertama full_name
$studentLabel = $session->student->nickname
    ?: explode(' ', trim($session->student->full_name ?? ''))[0]
    ?: '?';
```

Contoh:

| full_name | nickname | Label di cell |
|-----------|----------|---------------|
| Budi Santoso | Budi | Budi |
| Budi Santoso | (kosong) | Budi |
| (kosong) | (kosong) | ? |

---

## Perubahan UI

### Cell grid (before → after)

**Sebelum:**
```
Piano · ADI
R2 · 09:00
```

**Sesudah:**
```
Budi · Piano · ADI
R2 · 09:00
```

Murid multi-kelas (Piano + Gitar):
```
Budi · Piano · ADI
Budi · Gitar · NAEL
```

### Popup detail

Tambah field `studentNickname` ke `$popupData` Alpine. Tampilkan di bawah nama lengkap jika nickname ada:

```
Budi Santoso (M-2026-0042)
"Budi"
Piano · ADI · STUDIO 2
...
```

---

## File yang Diubah

| File | Perubahan |
|------|-----------|
| `resources/views/kalender/index.blade.php` | Hitung `$studentLabel`, tampilkan di cell; tambah nickname di popup |
| `tests/Feature/KalenderControllerTest.php` | Test nickname tampil; test fallback kata pertama |

**Tidak diubah:** `KalenderController.php`, routes, CSS tema.

---

## Testing

1. **Nickname ada** — cell mengandung nickname + instrumen + guru.
2. **Nickname kosong** — cell mengandung kata pertama `full_name`.
3. **Multi-enrollment** — 2 sesi murid sama muncul dengan label nickname identik, instrumen berbeda.

---

## Out of Scope

- Badge primary vs secondary enrollment
- Filter enrollment type
- Tampilkan nickname di halaman lain selain kalender
- Refactor helper global (cukup inline di view kecuali sudah ada helper serupa)

---

## Risiko & Mitigasi

| Risiko | Mitigasi |
|--------|----------|
| Cell terlalu panjang di jam sibuk | `truncate` class sudah ada di cell — pertahankan |
| Nama satu kata + nickname kosong | Fallback = full_name utuh (1 kata) — OK |
| Murid tanpa nickname dan full_name aneh | Fallback `?` |

---

*Disetujui setelah review user → lanjut ke implementation plan via writing-plans skill.*
