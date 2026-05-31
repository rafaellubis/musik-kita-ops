# SRS M08 — Event (Mini Concert & Ujian)

**Induk:** [SRS-musik-kita-ops-2026-05-31.md](../SRS-musik-kita-ops-2026-05-31.md)

## 1. Tujuan

Kelola event studio, daftar peserta, generate tagihan UJI/MC, hasil ujian & naik grade, honor pengawas/pendamping.

## 2. Controller & Route

| Aksi | Route | Role |
|------|-------|------|
| Index/show event | `events.index`, `show` | + Auditor |
| Create/edit event | `events.*` (write) | **Owner** |
| Complete event | `events.complete` | **Owner** |
| Save exam results | `events.exam-results` | **Owner** |
| Add participant | `events.participants.store` | Owner\|Admin |
| Remove participant | `event-participants.destroy` | Owner\|Admin |
| Update accompanying teacher | `event-participants.update-teacher` | Owner\|Admin |

**Service:** `EventHonorService` — suntik honor ke `teacher_honor_slips` saat event selesai

## 3. Schema

### events

Status siklus (DRAFT → … → selesai) — cek model `Event` untuk konstanta aktual.

### event_participants

`event_id`, `student_id`, `enrollment_id`, `accompanying_teacher_id` (nullable), `participation_type`, `fee_amount`, `invoice_id`, `invoice_item_id`, `exam_result`, `grade_before`, `grade_after`, `exam_notes`

**Guru pendamping:** hanya ubah saat event masih DRAFT; NULL = tidak mendampingi.

## 4. Business Rules

| BR | Aturan |
|----|--------|
| Ujian + MC | Rp 395.000 (UJI) |
| MC saja | Rp 295.000 (MC) |
| Honor pengawas ujian | Rp 250.000 flat per ujian |
| Grade naik | Otomatis jika lulus (exam flow di controller) |
| Konser KITA | `accompanying_teacher_id` + honor via EventHonorService |

## 5. File Scope

```
app/Http/Controllers/EventController.php
app/Services/EventHonorService.php
app/Models/Event.php
app/Models/EventParticipant.php
resources/views/events/
```

## 6. Acceptance Criteria

- [ ] Peserta terhubung invoice/item yang benar
- [ ] Admin tidak bisa `events.complete` atau edit event master
- [ ] Accompanying teacher patch ditolak jika event bukan DRAFT
- [ ] Tests: `EventCompleteControllerTest`, `EventHonorServiceTest`
