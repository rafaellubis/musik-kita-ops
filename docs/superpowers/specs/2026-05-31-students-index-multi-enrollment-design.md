# Spesifikasi: Multi-Enrollment di Daftar Murid

**Tanggal:** 2026-05-31  
**Status:** Disetujui — implementasi

---

## Masalah

Halaman `students/index` hanya menampilkan `primaryEnrollment` (via accessor `$s->package`, `$s->assignedTeacher`, jadwal primary). Murid multi-kelas (contoh: Piano + Gitar) hanya terlihat satu paket.

## Keputusan UX

- **Format:** Stacked — satu baris per murid; kolom Paket / Guru / Jadwal menampilkan baris vertikal per enrollment
- **Urutan:** Primary (`is_primary = true`) selalu baris pertama, tanpa badge/ikon
- **Scope:** Hanya halaman Daftar Murid (`students/index`)

## Enrollment yang ditampilkan

Status `ACTIVE` atau `TRIAL`, urut `is_primary DESC`.

| Skenario | Tampilan |
|---|---|
| 1 enrollment ACTIVE | 1 baris (sama seperti sekarang) |
| 2+ enrollment ACTIVE | Stacked per paket/guru/jadwal |
| Murid Trial (1 TRIAL) | 1 baris paket trial |
| Tidak ada enrollment | `—` |

## Perubahan Backend

### Relasi `Student::activeEnrollments()`

```php
public function activeEnrollments(): HasMany
{
    return $this->hasMany(Enrollment::class)
        ->whereIn('status', ['ACTIVE', 'TRIAL'])
        ->orderByDesc('is_primary');
}
```

### `StudentController::index`

Ganti eager load `primaryEnrollment` dengan:

```php
'activeEnrollments.package.instrument',
'activeEnrollments.teacher',
'activeEnrollments.schedules' => fn ($q) => $q->where('is_active', true),
```

Filter instrumen/paket tidak berubah.

## Perubahan Blade

Loop `@foreach($s->activeEnrollments as $enr)` di kolom Paket, Guru, Jadwal. Hapus `truncate` di kolom Paket. Jadwal: jadwal aktif pertama per enrollment.

## Out of Scope

- Halaman detail murid, dashboard, laporan
- Refactor `show()` ke relasi baru (opsional nanti)
