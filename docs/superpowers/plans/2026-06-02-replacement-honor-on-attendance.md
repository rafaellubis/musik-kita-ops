# Replacement Honor on Attendance — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Sesi pengganti kalender akademik tidak pre-fill honor; honor diisi saat absensi seperti `RescheduleService`.

**Architecture:** Hapus blok perhitungan honor di FASE 3 `SessionGeneratorService`; backfill data lama; perbarui test + komentar model.

**Tech Stack:** Laravel 11, PHPUnit

---

### Task 1: Test — replacement honor null

**Files:**
- Modify: `tests/Feature/Services/SessionGeneratorServiceTest.php`

- [ ] **Step 1:** Tambah `test_replacement_session_honor_null_until_attendance`
- [ ] **Step 2:** Run `php artisan test --filter=test_replacement_session_honor_null_until_attendance` → FAIL

### Task 2: Service — hapus pre-fill honor

**Files:**
- Modify: `app/Services/SessionGeneratorService.php`

- [ ] Hapus blok `$repHonorCode` / `$repHonorAmount`; set `honor_code`/`honor_amount` null pada create replacement
- [ ] Update komentar header service

### Task 3: Backfill migration

**Files:**
- Create: `database/migrations/2026_06_02_000001_clear_honor_scheduled_calendar_replacements.php`

- [ ] Null honor untuk SCHEDULED + origin_session_id + notes generator

### Task 4: Komentar model

**Files:**
- Modify: `app/Models/ClassSession.php`

- [ ] Perbarui lifecycle comment (honor: LIBUR saat generate, sisanya saat absensi)

### Task 5: Verify

- [ ] Run `php artisan test --filter=SessionGeneratorServiceTest`
- [ ] Run `php artisan test --filter=SessionGeneratorSequenceTest`
