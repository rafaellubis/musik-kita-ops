# Catatan Per Sesi Guru — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Guru isi catatan terstruktur (Materi, Tugas/Latihan, Catatan) per ClassSession setelah mengajar; catatan otomatis sync read-only ke laporan progress bulanan dan snapshot saat submit.

**Architecture:** Tabel `session_teacher_notes` (Opsi A) sebagai source of truth per sesi. `SessionNoteSyncService` aggregate ke `progress_report_session_notes` saat guru buka/simpan/submit laporan DRAFT. UI capture di dashboard/jadwal via PATCH route.

**Tech Stack:** Laravel 11, Blade, Alpine.js, PHPUnit, Spatie Permission

**Design spec:** `docs/superpowers/specs/2026-06-06-catatan-per-sesi-guru-design.md`

---

## File Map

**Create:**
- `database/migrations/2026_06_06_100001_create_session_teacher_notes_table.php`
- `database/migrations/2026_06_06_100002_add_structured_fields_to_progress_report_session_notes_table.php`
- `app/Models/SessionTeacherNote.php`
- `app/Services/SessionNoteSyncService.php`
- `tests/Feature/SessionTeacherNoteTest.php`

**Modify:**
- `app/Models/ClassSession.php` — relasi `teacherNote()`
- `app/Models/ProgressReportSessionNote.php` — fillable + casts
- `app/Http/Controllers/GuruController.php` — `updateSessionNotes`, sync calls, remove manual session notes save
- `routes/web.php` — PATCH route
- `resources/views/guru/_sesi-absensi-actions.blade.php` — form 3 field
- `resources/views/guru/laporan-form.blade.php` — read-only section
- `resources/views/progress-reports/pdf.blade.php` — structured display
- `resources/views/progress-reports/show.blade.php` — structured display
- `tests/Feature/ProgressReportGuruTest.php` — sync tests

---

### Task 1: Migrations + Models

**Files:**
- Create: migrations + `SessionTeacherNote.php`
- Modify: `ClassSession.php`, `ProgressReportSessionNote.php`

- [ ] **Step 1: Write migration `create_session_teacher_notes_table`**

```php
Schema::create('session_teacher_notes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('class_session_id')->unique()->constrained()->cascadeOnDelete();
    $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
    $table->text('material_learned')->nullable();
    $table->text('homework_notes')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 2: Write migration alter `progress_report_session_notes`**

Add nullable: `class_session_id` (FK), `material_learned`, `homework_notes`, `session_sequence`.

- [ ] **Step 3: Create `SessionTeacherNote` model**

Fillable: `class_session_id`, `teacher_id`, `material_learned`, `homework_notes`, `notes`.
Relations: `classSession()`, `teacher()`.
Method: `isComplete(): bool` — at least one of 3 text fields non-empty.

- [ ] **Step 4: Add `ClassSession::teacherNote(): HasOne`**

- [ ] **Step 5: Update `ProgressReportSessionNote` fillable + `classSession()` relation**

- [ ] **Step 6: Run migrations**

```bash
php artisan migrate
```

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models
git commit -m "feat: add session_teacher_notes table and extend progress report session notes"
```

---

### Task 2: SessionNoteSyncService

**Files:**
- Create: `app/Services/SessionNoteSyncService.php`
- Modify: `app/Http/Controllers/GuruController.php` (laporanEdit, laporanUpdate)

- [ ] **Step 1: Write failing test in `ProgressReportGuruTest`**

Test: create enrollment + 2 HADIR sessions in report month, add `SessionTeacherNote` on one session, create DRAFT report, call sync (via laporanEdit GET), assert `progress_report_session_notes` has 2 rows (one with notes, one empty placeholder OR only synced sessions with notes — follow design: sync all eligible HADIR sessions).

- [ ] **Step 2: Implement `SessionNoteSyncService::sync(ProgressReport $report): void`**

Query sessions: same `enrollment_id`, month/year from report, status in `[HADIR, HADIR_TERLAMBAT]`.
Upsert by `class_session_id`. Delete orphaned snapshot rows.
Set `session_date`, `session_sequence`, structured fields from `SessionTeacherNote` (nullable if missing).

- [ ] **Step 3: Call sync in `laporanEdit` and `laporanUpdate` before render/save**

- [ ] **Step 4: Remove manual session_notes delete/create block from `laporanUpdate`**

Remove validation for `session_dates`, `session_notes_text`.

- [ ] **Step 5: Run tests, commit**

```bash
php artisan test --filter=ProgressReportGuruTest
git commit -m "feat: sync session teacher notes into progress report snapshots"
```

---

### Task 3: Guru Capture UI + PATCH Route

**Files:**
- Create: `tests/Feature/SessionTeacherNoteTest.php`
- Modify: `GuruController.php`, `routes/web.php`, `_sesi-absensi-actions.blade.php`

- [ ] **Step 1: Write failing tests**

Cases:
- Guru utama can PATCH notes on own HADIR session
- Substitute can PATCH after DIGANTI confirmed
- Other guru gets 403
- Cannot PATCH on SCHEDULED session
- Validation: at least one field required
- Cannot edit when monthly report already SUBMITTED for that month

- [ ] **Step 2: Add route**

```php
Route::patch('/sesi/{classSession}/catatan', [GuruController::class, 'updateSessionNotes'])
    ->name('sesi.catatan.update');
```

- [ ] **Step 3: Implement `updateSessionNotes(Request, ClassSession)`**

Authorization helper: teacher owns session OR is confirmed substitute.
Lock check: no SUBMITTED ProgressReport for enrollment + session month/year.
Upsert `SessionTeacherNote`.

- [ ] **Step 4: Update `_sesi-absensi-actions.blade.php`**

After HADIR/HADIR_TERLAMBAT status block: show Alpine collapsible with 3 textareas + save form PATCH.
Pre-fill from `$sesi->teacherNote` if exists. Eager-load `teacherNote` in dashboard/jadwal controller.

- [ ] **Step 5: Run tests, commit**

```bash
php artisan test --filter=SessionTeacherNoteTest
git commit -m "feat: guru can save structured session notes after attendance"
```

---

### Task 4: Laporan Form Read-Only

**Files:**
- Modify: `resources/views/guru/laporan-form.blade.php`

- [ ] **Step 1: Replace Alpine editable session notes with read-only cards**

For each `$progressReport->sessionNotes` (sorted): show date, session label, 3 fields.
If all empty: yellow "Belum diisi" badge.
Remove tambah/hapus buttons.

- [ ] **Step 2: Add submit confirmation**

If any synced session note row has empty material+homework+notes, `onsubmit` confirm dialog.

- [ ] **Step 3: Manual smoke test + commit**

```bash
git commit -m "feat: show session notes read-only in monthly progress report form"
```

---

### Task 5: PDF + Admin Show

**Files:**
- Modify: `pdf.blade.php`, `show.blade.php`

- [ ] **Step 1: Update session notes section**

Display structured 3 fields when present. Fallback to legacy `notes` only for old data.

- [ ] **Step 2: Run ProgressReportAdminTest if exists, commit**

```bash
php artisan test --filter=ProgressReport
git commit -m "feat: structured session notes in PDF and admin show views"
```

---

### Task 6: Final Verification

- [ ] **Step 1: Run full test suite**

```bash
php artisan test
```

- [ ] **Step 2: Update SRS**

Add note to `docs/srs/modules/M11-laporan-progres.md` about session_teacher_notes pipeline.

- [ ] **Step 3: Final commit if SRS changed**
