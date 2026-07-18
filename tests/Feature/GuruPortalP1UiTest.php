<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GuruPortalP1UiTest extends TestCase
{
    public function test_honor_detail_has_valid_summary_markup(): void
    {
        $view = File::get(resource_path('views/guru/honor-show.blade.php'));

        $this->assertStringNotContainsString('<<div', $view);
    }

    public function test_session_history_uses_accessible_native_dialog(): void
    {
        $view = File::get(resource_path('views/layouts/guru.blade.php'));

        $this->assertStringContainsString('<dialog', $view);
        $this->assertStringContainsString('aria-labelledby="session-history-title"', $view);
        $this->assertStringContainsString('id="session-history-title"', $view);
        $this->assertStringContainsString('aria-label="Tutup riwayat sesi"', $view);
        $this->assertStringContainsString('.showModal()', $view);
        $this->assertStringContainsString('this.cleanupHistory(true)', $view);
        $this->assertStringContainsString('@click.self=', $view);
    }

    public function test_session_history_cancels_stale_requests_and_exposes_accessible_tabs(): void
    {
        $view = File::get(resource_path('views/layouts/guru.blade.php'));

        $this->assertStringContainsString('historyController: null', $view);
        $this->assertStringContainsString('this.historyController?.abort()', $view);
        $this->assertStringContainsString('this.historyController = new AbortController()', $view);
        $this->assertStringContainsString('const controller = this.historyController', $view);
        $this->assertStringContainsString('signal: controller.signal', $view);
        $this->assertStringContainsString('if (controller !== this.historyController || !this.open) return;', $view);
        $this->assertStringContainsString("if (error.name === 'AbortError') return;", $view);
        $this->assertStringContainsString('role="tablist"', $view);
        $this->assertStringContainsString('role="tab"', $view);
        $this->assertStringContainsString(':aria-selected="activeTab === item.key"', $view);
        $this->assertStringContainsString('aria-controls="session-history-panel"', $view);
        $this->assertStringContainsString(':tabindex="activeTab === item.key ? 0 : -1"', $view);
        $this->assertStringContainsString(':id="\'session-history-tab-\' + item.key"', $view);
        $this->assertStringContainsString('@keydown.arrow-left.prevent="moveHistoryTab(-1)"', $view);
        $this->assertStringContainsString('@keydown.arrow-right.prevent="moveHistoryTab(1)"', $view);
        $this->assertStringContainsString('@keydown.home.prevent="moveHistoryTab(0, true)"', $view);
        $this->assertStringContainsString('@keydown.end.prevent="moveHistoryTab(monthsList.length - 1, true)"', $view);
        $this->assertStringContainsString('document.getElementById(`session-history-tab-${item.key}`)?.focus()', $view);
        $this->assertStringContainsString('focus-visible:ring-2', $view);
        $this->assertStringContainsString('role="tabpanel"', $view);
        $this->assertStringContainsString('id="session-history-panel"', $view);
        $this->assertStringContainsString(':aria-labelledby="\'session-history-tab-\' + activeTab"', $view);
    }

    public function test_dashboard_chart_import_failure_reveals_accessible_inline_fallbacks(): void
    {
        $view = File::get(resource_path('views/dashboard.blade.php'));

        $this->assertStringContainsString("document.querySelectorAll('[id^=\"chart-\"]')", $view);
        $this->assertStringContainsString("fallback.setAttribute('role', 'alert')", $view);
        $this->assertStringContainsString('Grafik tidak dapat dimuat.', $view);
        $this->assertStringNotContainsString('}).catch(() => {});', $view);
    }

    public function test_jadwal_failure_replaces_stale_sessions_with_accessible_retry(): void
    {
        $script = File::get(resource_path('js/guru-jadwal-calendar.js'));

        $this->assertStringContainsString('role="alert"', $script);
        $this->assertStringContainsString('Gagal memuat sesi untuk tanggal ini.', $script);
        $this->assertStringContainsString('data-action="retry-sessions"', $script);
        $this->assertStringContainsString('this.fetchSessions(date)', $script);
        $this->assertStringContainsString('[data-action="retry-sessions"]', $script);
    }

    public function test_session_actions_have_unique_labels_and_history_button_outside_summary(): void
    {
        $view = File::get(resource_path('views/guru/_sesi-absensi-actions.blade.php'));

        foreach (['session_rating', 'material_learned', 'homework_notes', 'notes'] as $field) {
            $this->assertStringContainsString('id="'.$field.'-{{ $sesi->id }}"', $view);
            $this->assertStringContainsString('for="'.$field.'-{{ $sesi->id }}"', $view);
        }

        $html = preg_replace('/@if\b.*?@endif/s', '', $view);
        preg_match('/<details\b[^>]*>\s*<(\w+)/s', $html, $firstChild);
        $summaryEnd = strpos($view, '</summary>');
        $historyButton = strpos($view, 'open-session-history');

        $this->assertSame('summary', $firstChild[1] ?? null, 'Summary harus menjadi child langsung pertama dari details.');
        $this->assertNotFalse($summaryEnd);
        $this->assertNotFalse($historyButton);
        $this->assertGreaterThan($summaryEnd, $historyButton);
    }

    public function test_searchable_select_focused_search_input_owns_combobox_semantics(): void
    {
        $view = File::get(resource_path('views/components/searchable-select.blade.php'));

        preg_match('/<button[\s\S]*?<\/button>/', $view, $buttonMatch);
        preg_match('/<input[\s\S]*?type="text"[\s\S]*?>/', $view, $searchMatch);

        $this->assertNotEmpty($buttonMatch);
        $this->assertNotEmpty($searchMatch);
        $this->assertStringNotContainsString('role="combobox"', $buttonMatch[0]);
        $this->assertStringContainsString('aria-haspopup="listbox"', $buttonMatch[0]);
        $this->assertStringContainsString(':aria-expanded="open"', $buttonMatch[0]);

        foreach ([
            'role="combobox"',
            'aria-controls="{{ $listboxId }}"',
            ':aria-expanded="open"',
            ':aria-activedescendant="activeDescendant"',
            '@keydown.arrow-down.prevent="moveActive(1)"',
            '@keydown.arrow-up.prevent="moveActive(-1)"',
            '@keydown.enter.prevent="selectActive()"',
            '@keydown.escape.prevent="close()"',
        ] as $expected) {
            $this->assertStringContainsString($expected, $searchMatch[0]);
        }

        $this->assertStringContainsString('role="listbox"', $view);
        $this->assertStringContainsString('role="option"', $view);
        $this->assertStringContainsString(':aria-selected=', $view);
        $this->assertStringContainsString('aria-labelledby="{{ $componentId }}-label"', $view);
        $this->assertStringContainsString('id="{{ $comboboxId }}"', $view);
        $this->assertStringContainsString('id="{{ $listboxId }}"', $view);
    }

    public function test_guru_async_feedback_completes_loading_before_refresh_and_uses_single_live_mechanism(): void
    {
        $layout = File::get(resource_path('views/layouts/guru.blade.php'));
        $card = File::get(resource_path('views/guru/_sesi-card.blade.php'));

        $this->assertStringContainsString('role="{{ $flashType === \'error\' ? \'alert\' : \'status\' }}"', $layout);
        $this->assertStringNotContainsString('aria-live=', $layout);
        $this->assertStringContainsString(':aria-busy="loading"', $layout);
        $this->assertStringContainsString('role="status"', $layout);
        $this->assertStringContainsString('role="alert"', $layout);
        $this->assertStringContainsString(':aria-busy="loading"', $card);
        $this->assertStringNotContainsString('aria-live=', $card);

        preg_match_all('/if \(data\.success\) \{([\s\S]*?)\} else/', $card, $successBranches);
        $this->assertCount(2, $successBranches[1]);
        foreach ($successBranches[1] as $branch) {
            $loadingDone = strpos($branch, 'loading = false;');
            $feedbackSet = strpos($branch, "feedback = 'Kehadiran berhasil disimpan.';");
            $refreshDispatched = strpos($branch, "window.dispatchEvent(new CustomEvent('session-updated'");

            $this->assertNotFalse($loadingDone);
            $this->assertNotFalse($feedbackSet);
            $this->assertNotFalse($refreshDispatched);
            $this->assertLessThan($refreshDispatched, $loadingDone);
            $this->assertLessThan($refreshDispatched, $feedbackSet);
        }
    }

    public function test_guru_portal_uses_theme_tokens_in_p2_scope(): void
    {
        $paths = [
            'views/layouts/guru.blade.php',
            'views/guru/_sesi-absensi-actions.blade.php',
            'views/guru/_sesi-card.blade.php',
            'views/guru/dashboard.blade.php',
            'views/guru/sesi-pending.blade.php',
        ];

        foreach ($paths as $path) {
            $view = File::get(resource_path($path));

            $this->assertDoesNotMatchRegularExpression('/(?:^|[\s\'":])(?:[a-z-]+:)*(?:text|bg|border|ring|placeholder|shadow)-(?:slate|gray)-|#[0-9a-f]{3,8}|rgba?\(/im', $view, $path);
        }
    }

    public function test_p2_performance_gate_keeps_queries_and_services_out_of_blade_and_apexcharts_lazy(): void
    {
        $layout = File::get(resource_path('views/layouts/guru.blade.php'));
        $actions = File::get(resource_path('views/guru/_sesi-absensi-actions.blade.php'));
        $appJs = File::get(resource_path('js/app.js'));
        $dashboard = File::get(resource_path('views/dashboard.blade.php'));

        $this->assertStringNotContainsString('ClassSession::', $layout);
        $this->assertStringNotContainsString('app(\\App\\Services\\SessionReportWaService::class)', $actions);
        foreach (['deliveryState(', 'resolveRecipientType(', 'maskPhone('] as $call) {
            $this->assertStringNotContainsString($call, $actions);
        }
        $this->assertStringNotContainsString("import ApexCharts from 'apexcharts'", $appJs);
        $this->assertStringNotContainsString('window.ApexCharts', $appJs);
        $this->assertStringContainsString("import('apexcharts')", $dashboard);
        $this->assertStringContainsString('.catch(', $dashboard);
    }

    public function test_guru_portal_reduced_motion_gate_suppresses_motion(): void
    {
        $layout = File::get(resource_path('views/layouts/guru.blade.php'));
        $css = File::get(resource_path('css/app.css'));

        $this->assertStringContainsString('guru-portal-root', $layout);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertStringContainsString('.guru-portal-root *,', $css);
        $this->assertStringContainsString('.guru-portal-root *::before,', $css);
        $this->assertStringContainsString('.guru-portal-root *::after', $css);
        $this->assertStringContainsString('animation-duration: 0.01ms !important;', $css);
        $this->assertStringContainsString('animation-iteration-count: 1 !important;', $css);
        $this->assertStringContainsString('transition-duration: 0.01ms !important;', $css);
        $this->assertStringNotContainsString('transform: none !important;', $css);
    }

    public function test_p3_guru_shell_works_offline_and_respects_mobile_safe_area(): void
    {
        $layout = File::get(resource_path('views/layouts/guru.blade.php'));
        $css = File::get(resource_path('css/app.css'));

        $this->assertStringNotContainsString('fonts.bunny.net', $layout);
        $this->assertStringContainsString('class="flex guru-portal-root overflow-hidden"', $layout);
        $this->assertStringContainsString('<main class="guru-portal-main ', $layout);
        $this->assertStringContainsString('<nav class="guru-bottom-nav ', $layout);
        $this->assertStringContainsString('.guru-portal-root {', $css);
        $this->assertStringContainsString('height: 100vh;', $css);
        $this->assertStringContainsString('height: 100dvh;', $css);
        $this->assertStringContainsString('.guru-portal-main {', $css);
        $this->assertStringContainsString('padding-bottom: calc(5rem + env(safe-area-inset-bottom, 0px));', $css);
        $this->assertStringContainsString('.guru-bottom-nav {', $css);
        $this->assertStringContainsString('height: calc(4rem + env(safe-area-inset-bottom, 0px));', $css);
        $this->assertStringContainsString('padding-bottom: env(safe-area-inset-bottom, 0px);', $css);
    }

    public function test_p3_laporan_uses_indonesian_copy_without_changing_pending_term(): void
    {
        $layout = File::get(resource_path('views/layouts/guru.blade.php'));
        $index = File::get(resource_path('views/guru/laporan.blade.php'));
        $form = File::get(resource_path('views/guru/laporan-form.blade.php'));

        foreach (['Beranda', 'Jadwal', 'Pending', 'Laporan'] as $copy) {
            $this->assertStringContainsString($copy, $layout);
        }
        foreach (['Terkirim', 'Draf', 'Lihat PDF'] as $copy) {
            $this->assertStringContainsString($copy, $index);
        }
        foreach (['Lihat PDF', 'Simpan Draf', 'Kirim Laporan'] as $copy) {
            $this->assertStringContainsString($copy, $form);
        }
        foreach ([
            'Simpan draft dulu agar perubahan terbaru muncul di preview.',
            'Masih ada sesi tanpa catatan. Lanjut submit?',
            'Submit laporan? Setelah disubmit, laporan tidak bisa diedit.',
        ] as $oldCopy) {
            $this->assertStringNotContainsString($oldCopy, $form);
        }
    }

    public function test_p3_guru_pages_have_document_heading_and_accessible_controls(): void
    {
        $pages = [
            'dashboard.blade.php',
            'honor.blade.php',
            'honor-show.blade.php',
            'jadwal.blade.php',
            'laporan.blade.php',
            'laporan-form.blade.php',
            'profil.blade.php',
            'sesi-pending.blade.php',
        ];

        foreach ($pages as $page) {
            $view = File::get(resource_path('views/guru/'.$page));
            $this->assertSame(1, preg_match_all('/<h1\b/', $view), $page);
        }

        $layout = File::get(resource_path('views/layouts/guru.blade.php'));
        $jadwal = File::get(resource_path('views/guru/jadwal.blade.php'));
        $pending = File::get(resource_path('views/guru/sesi-pending.blade.php'));

        $this->assertStringNotContainsString('<h1', $layout);
        $this->assertStringContainsString(':aria-current="monthCellIsCurrent(index) ? \'date\' : null"', $jadwal);
        $this->assertSame(substr_count($pending, 'role="status"'), 1);
        $this->assertStringContainsString(':aria-expanded="showHistory"', $pending);
        $this->assertStringContainsString('aria-controls="pending-history-{{ $sesi->id }}"', $pending);
        $this->assertStringContainsString('id="pending-history-{{ $sesi->id }}"', $pending);
        $this->assertStringContainsString(':aria-expanded="open"', $pending);
        $this->assertStringContainsString('aria-controls="pending-form-{{ $sesi->id }}"', $pending);
        $this->assertStringContainsString('id="pending-form-{{ $sesi->id }}"', $pending);

        foreach ([$layout, $jadwal, $pending] as $view) {
            preg_match_all('/<svg\b[^>]*>/', $view, $svgs);
            foreach ($svgs[0] as $svg) {
                $this->assertStringContainsString('aria-hidden="true"', $svg, $svg);
            }
        }
    }

    public function test_p3_guru_timers_do_not_poll_every_second(): void
    {
        $layout = File::get(resource_path('views/layouts/guru.blade.php'));
        $card = File::get(resource_path('views/guru/_sesi-card.blade.php'));
        $timers = File::get(resource_path('js/guru-portal-timers.js'));

        $this->assertDoesNotMatchRegularExpression('/setInterval|,\s*1000\)/', $layout.$card.$timers);
        $this->assertStringContainsString("addEventListener('visibilitychange'", $timers);
        $this->assertStringContainsString("removeEventListener('visibilitychange'", $timers);
        $this->assertStringContainsString("addEventListener('focus'", $timers);
        $this->assertStringContainsString("removeEventListener('focus'", $timers);
        $this->assertStringContainsString('x-data="guruSessionActivation(', $card);
    }

    public function test_laporan_controls_have_labels_groups_and_accessible_errors(): void
    {
        $view = File::get(resource_path('views/guru/laporan-form.blade.php'));

        $this->assertStringContainsString('id="{{ $field[\'rating\'] }}"', $view);
        $this->assertStringContainsString('for="{{ $field[\'rating\'] }}"', $view);
        $this->assertStringContainsString('id="{{ $field[\'catatan\'] }}"', $view);
        $this->assertStringContainsString('for="{{ $field[\'catatan\'] }}"', $view);

        foreach (['catatan_perkembangan_musikal', 'catatan_karakter', 'progress_percent'] as $field) {
            $this->assertStringContainsString('id="'.$field.'"', $view);
            $this->assertStringContainsString('for="'.$field.'"', $view);
            $this->assertStringContainsString("aria-describedby=\"{{ \$errors->has('{$field}') ? '{$field}-error' : null }}\"", $view);
            $this->assertStringContainsString("aria-invalid=\"{{ \$errors->has('{$field}') ? 'true' : 'false' }}\"", $view);
            $this->assertStringContainsString('id="'.$field.'-error"', $view);
        }

        $this->assertStringContainsString('<fieldset', $view);
        $this->assertStringContainsString('<legend', $view);
    }
}
