# Manual Sessions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans. TDD: failing test first.

**Goal:** Admin dapat menambah sesi manual dengan atribusi bulan, agar rapel cross-month masuk laporan progres bulan yang benar.

**Architecture:** Kolom `attribution_*` + `session_type` di `class_sessions`. `ManualSessionService` handle create + conflict + slot summary. `SessionNoteSyncService` filter by attribution.

**Tech Stack:** Laravel 11, PHPUnit, Blade + Alpine

---

### Task 1: Migration + Model

- [ ] Migration add columns + backfill
- [ ] ClassSession constants + fillable + attribution helpers + label manual

### Task 2: ManualSessionService (TDD)

- [ ] Test: create manual session with attribution
- [ ] Test: suggest next sequence
- [ ] Test: reject duplicate sequence
- [ ] Test: rapel cross-month keeps attribution
- [ ] Implement ManualSessionService

### Task 3: SessionNoteSync + GuruController (TDD)

- [ ] Test: sync manual Feb session into Jan report
- [ ] Update SessionNoteSyncService filter
- [ ] Update GuruController submitted guard

### Task 4: SessionGenerator attribution

- [ ] Set attribution_* on all generator-created sessions

### Task 5: HTTP + UI

- [ ] StoreManualSessionRequest + ManualSessionController
- [ ] Route + slot panel in tab-kelas
- [ ] Feature test POST manual session

### Task 6: Verify

- [ ] `php artisan test` filtered + full suite
