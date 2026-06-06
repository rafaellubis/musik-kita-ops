# Design: Auto-kirim Laporan Sesi ke WhatsApp Ortu

**Tanggal:** 2026-06-07  
**Modul:** M10 Guru Portal + Master Data WA  
**Status:** Disetujui user

---

## 1. Masalah

Guru sudah mengisi **catatan per sesi** (materi, tugas, catatan, rating) setelah absensi HADIR, tetapi orang tua tidak otomatis mendapat ringkasan perkembangan anak setelah les selesai. Informasi hanya terkumpul di laporan bulanan.

## 2. Tujuan

- Setelah guru menyimpan catatan sesi, sistem **otomatis** mengirim pesan teks WhatsApp ramah & menyemangati ke ortu
- Prioritas nomor: `parent_phone` → fallback `phone` murid
- Guru punya jendela singkat untuk koreksi sebelum pesan terkirim (debounce 10 menit)

## 3. Keputusan Desain (Disetujui)

| Aspek | Keputusan |
|-------|-----------|
| Scope | **Per sesi** — trigger saat simpan `SessionTeacherNote`, bukan laporan bulanan |
| Format | **Teks WA saja** (tanpa PDF) |
| Trigger | **Opsi A** — queue job dengan debounce 10 menit |
| Gateway | **Fonnte** (konsisten dengan pengingat jadwal) |
| Template | Master data `SESSION_REPORT` di `WhatsappMessageTemplate` |
| Nomor tujuan | `parent_phone` → `phone` murid (sama seperti `ScheduleReminderService`) |
| Edit setelah terkirim | Jika catatan diubah setelah WA sukses, kirim ulang sebagai **update** (prefix `[Update]`) |
| Feature flag | `SESSION_REPORT_WA_ENABLED` di config |

## 4. Workflow

1. Guru absensi HADIR / HADIR_TERLAMBAT / DIGANTI (pengganti konfirmasi)
2. Guru isi & simpan catatan sesi (`PATCH /guru/sesi/{classSession}/catatan`)
3. Controller dispatch `SendSessionReportWaJob` delay 10 menit, membawa snapshot `note_updated_at`
4. Saat job jalan: reload catatan — skip jika `updated_at` lebih baru dari snapshot (superseded)
5. Skip jika sudah ada log SUCCESS dengan `sent_at >= note.updated_at` (idempotent)
6. Compose pesan dari template + placeholder dinamis → kirim Fonnte → tulis `session_report_wa_logs`

## 5. Template Pesan (Seed Default)

**Kode:** `SESSION_REPORT`

```
Halo, Yth. {nama_ortu} 👋

Les musik *{nama_murid}* hari ini sudah selesai. Terima kasih sudah mempercayakan perjalanan musiknya kepada kami di Musik KITA 🎵

📅 *{tanggal_sesi}*
🎹 Instrumen: {instrumen}
👨‍🏫 Guru: {nama_guru}

*Materi hari ini:*
{materi}

*Latihan minggu ini:*
{tugas}

{blok_catatan}

{pesan_semangat}

Kami senang melihat langkah-langkah kecil {nama_murid} menuju kemampuan bermusik yang lebih baik. Dukungan Bapak/Ibu di rumah sangat berarti — semangat latihan ya! 💪🎶

Salam hangat,
Musik KITA
WA: {studio_wa}
```

**Placeholder khusus:**

| Placeholder | Sumber |
|-------------|--------|
| `{nama_ortu}` | `student.parent_name` atau `Bapak/Ibu` |
| `{nama_murid}` | `student.full_name` |
| `{tanggal_sesi}` | `session_date` format `d F Y` (id) |
| `{instrumen}` | `enrollment.package.instrument.name` |
| `{nama_guru}` | guru pengajar sesi (substitute jika DIGANTI) |
| `{materi}` | `material_learned` atau fallback teks |
| `{tugas}` | `homework_notes` atau fallback teks |
| `{blok_catatan}` | Baris `*Catatan guru:*\n{catatan}` — **dihilangkan** jika catatan kosong |
| `{pesan_semangat}` | Dinamis dari `session_rating` (1–5) |
| `{studio_wa}` | `FonnteService::STUDIO_WA_DISPLAY` |

**Rating → `{pesan_semangat}`:**

| Rating | Kalimat |
|--------|---------|
| 5 | Hari ini {nama_murid} tampil sangat antusias dan fokus — perkembangannya terlihat jelas! |
| 4 | {nama_murid} menunjukkan kemajuan yang baik hari ini. Pertahankan semangatnya! |
| 3 | {nama_murid} sudah berusaha dengan baik. Sedikit latihan rutin di rumah akan membuat hasilnya makin terasa. |
| 1–2 / kosong | Setiap sesi adalah langkah berharga. Mari terus mendampingi {nama_murid} dengan sabar dan konsisten. |

## 6. Data Model

### Tabel baru: `session_report_wa_logs`

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `id` | bigint PK | |
| `class_session_id` | FK → `class_sessions` | Sesi terkait |
| `student_id` | FK → `students` | |
| `phone` | string(20) | Nomor tujuan (normalized) |
| `message_body` | text | Pesan final |
| `provider` | string(20) | `fonnte` |
| `provider_message_ids` | json nullable | ID dari Fonnte |
| `status` | string(20) | `SUCCESS`, `FAILED`, `SKIPPED` |
| `is_update` | boolean | Pesan update setelah edit |
| `error_message` | text nullable | |
| `sent_at` | timestamp | |
| timestamps | | |

Index: `(class_session_id, sent_at)`, `(student_id, sent_at)`, `(status, sent_at)`.

## 7. UI/UX

### Guru — form Catatan Sesi (`_sesi-absensi-actions.blade.php`)

Status chip di bawah tombol Simpan:

| Kondisi | Chip |
|---------|------|
| Fitur disabled | (tidak tampil) |
| Menunggu kirim | `⏳ Akan dikirim ke ortu dalam ~10 menit` |
| Terkirim | `✓ Pesan WA terkirim ke 0816***0592` |
| Gagal | `⚠ Gagal kirim WA — hubungi admin` |
| Skip (no phone) | `ℹ Nomor WA tidak tersedia` |

Toast setelah simpan: *"Catatan tersimpan. Laporan sesi akan otomatis dikirim ke WhatsApp orang tua."*

### Admin — Log Laporan Sesi WA

- Route read-only: `GET /session-report-wa-logs`
- Filter: tanggal, status, search murid
- Kolom: tanggal sesi, murid, guru, nomor (masked), status, waktu kirim
- Tombol **Kirim Ulang** untuk status FAILED (Owner/Admin)

### WhatsApp Templates

- Tambah `SESSION_REPORT` ke seeder + protect dari delete (seperti template existing)
- Owner bisa edit isi pesan

## 8. Error Handling

| Situasi | Perilaku |
|---------|----------|
| Fonnte tidak dikonfigurasi | Log FAILED, tidak throw ke guru |
| Nomor invalid | Log SKIPPED |
| Job superseded (edit dalam debounce) | Silent return, tidak log |
| API gagal | Log FAILED, retry manual via admin |
| Laporan bulan SUBMITTED + catatan locked | Existing rule — catatan tidak bisa diubah |

## 9. Out of Scope

- PDF lampiran per sesi
- Opt-out per murid (fase 2)
- Notifikasi ke murid dewasa terpisah dari ortu
