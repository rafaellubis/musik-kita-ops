# Editable Encouragement UI — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Owner mengedit **6 pesan semangat** per template laporan sesi (rating 5/4/3/2/1 + default kosong) di halaman Edit Template WA.

**Architecture:** Kolom `encouragement_lines` JSON di `whatsapp_message_templates`. Form edit menampilkan **6 textarea** untuk kode SESSION_REPORT*. Service baca dari template + fallback generik (teks statis, tanpa `{nama_murid}`).

**Tech Stack:** Laravel 11, PHPUnit, Blade

**Design spec:** [`docs/superpowers/specs/2026-06-08-editable-encouragement-ui-design.md`](docs/superpowers/specs/2026-06-08-editable-encouragement-ui-design.md)

---

## File Map

**Create:**
- `database/migrations/2026_06_08_100000_add_encouragement_lines_to_whatsapp_message_templates_table.php`

**Modify:**
- `app/Models/WhatsappMessageTemplate.php`
- `app/Services/SessionReportWaService.php`
- `database/seeders/WhatsappMessageTemplateSeeder.php`
- `app/Http/Requests/UpdateWhatsappMessageTemplateRequest.php`
- `app/Http/Controllers/WhatsappMessageTemplateController.php`
- `resources/views/whatsapp-templates/_form.blade.php`
- `tests/Feature/SessionReportWaTest.php`

---

### Task 1: Migration + Model

- [ ] **Step 1:** Migration `encouragement_lines` JSON nullable after `body`
- [ ] **Step 2:** Model — fillable, cast array, constants + helper:

```php
public const SESSION_REPORT_CODES = [
    self::CODE_SESSION_REPORT,
    self::CODE_SESSION_REPORT_STUDENT,
];

public static function defaultEncouragementLines(string $code): array
{
    return match ($code) {
        self::CODE_SESSION_REPORT_STUDENT => [
            'rating_5' => 'Kamu tampil sangat antusias dan fokus hari ini — keren banget! Pertahankan ya!',
            'rating_4' => 'Kemajuanmu hari ini kelihatan banget. Terus semangat latihannya ya!',
            'rating_3' => 'Kamu sudah berusaha dengan baik hari ini. Latihan singkat tiap hari akan bikin hasil makin terasa.',
            'rating_2' => 'Kamu bisa lebih fokus lagi di sesi berikutnya. Latihan singkat di rumah akan banyak membantu!',
            'rating_1' => 'Hari ini agak kurang fokus — tidak apa-apa. Setiap proses punya naik turunnya. Semangat lagi minggu depan ya!',
            'default'  => 'Setiap sesi adalah langkah berharga — terus semangat ya!',
        ],
        default => [
            'rating_5' => 'Hari ini tampil sangat antusias dan fokus — perkembangannya terlihat jelas!',
            'rating_4' => 'Menunjukkan kemajuan yang baik hari ini. Pertahankan semangatnya!',
            'rating_3' => 'Sudah berusaha dengan baik. Sedikit latihan rutin di rumah akan membuat hasilnya makin terasa.',
            'rating_2' => 'Perlu sedikit lebih fokus di sesi berikutnya. Dukungan latihan di rumah akan sangat membantu.',
            'rating_1' => 'Hari ini agak kurang fokus — tidak apa-apa, setiap proses punya naik turunnya. Mari coba lagi dengan semangat baru.',
            'default'  => 'Setiap sesi adalah langkah berharga. Mari terus mendampingi dengan sabar dan konsisten.',
        ],
    };
}

public function encouragementForRating(?int $rating): string
{
    $key = match (true) {
        $rating === 5 => 'rating_5',
        $rating === 4 => 'rating_4',
        $rating === 3 => 'rating_3',
        $rating === 2 => 'rating_2',
        $rating === 1 => 'rating_1',
        default       => 'default',
    };

    $lines = $this->encouragement_lines ?? self::defaultEncouragementLines($this->code);
    $text = trim((string) ($lines[$key] ?? ''));

    if ($text !== '') {
        return $text;
    }

    return self::defaultEncouragementLines($this->code)[$key];
}
```

---

### Task 2: Service refactor (TDD)

- [ ] **Step 1:** Failing tests:
  - `test_compose_message_uses_custom_encouragement_from_template` (rating 5)
  - `test_compose_message_uses_rating_2_encouragement` (rating 2, teks berbeda dari default)
  - `test_compose_message_uses_rating_1_encouragement` (rating 1)

```php
// rating 2 example
$session->teacherNote->update(['session_rating' => 2]);
WhatsappMessageTemplate::where(...)->update(['encouragement_lines' => [
    'rating_5' => '...', 'rating_4' => '...', 'rating_3' => '...',
    'rating_2' => 'Pesan khusus rating dua',
    'rating_1' => '...', 'default' => '...',
]]);
$this->assertStringContainsString('Pesan khusus rating dua', $message);
```

- [ ] **Step 2:** `composeMessage` → `'{pesan_semangat}' => $template->encouragementForRating($note?->session_rating)`
- [ ] **Step 3:** Hapus `encouragementLine()` private
- [ ] **Step 4:** Update test lama assert pesan semangat sesuai default generik baru

---

### Task 3: Seeder

- [ ] `encouragement_lines` => `defaultEncouragementLines($code)` di firstOrCreate SESSION_REPORT*
- [ ] Backfill jika null pada record existing

---

### Task 4: Form + validation + controller

- [ ] `_form.blade.php` — **6 textarea**:

```
Rating 5/5
Rating 4/5
Rating 3/5
Rating 2/5
Rating 1/5
Rating tidak dipilih (default)
```

- [ ] Validasi: `encouragement_lines.rating_5` … `rating_1`, `default` — required|string|max:500
- [ ] Controller merge `encouragement_lines` saat update session report template
- [ ] Test Owner PUT menyimpan 6 field

---

### Task 5: Regression

```bash
php artisan migrate
php artisan test --filter=SessionReportWaTest
php artisan db:seed --class=WhatsappMessageTemplateSeeder
```
