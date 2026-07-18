<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — {{ config('app.name', 'Musik KITA') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-background text-on-background">

<div class="flex guru-portal-root overflow-hidden">

    {{-- ===== SIDEBAR (Desktop) ===== --}}
    <aside class="hidden lg:flex fixed inset-y-0 left-0 z-30 w-56 bg-primary flex-col shrink-0 border-r border-outline-variant/20 text-on-primary">

        <div class="px-4 py-3 border-b border-white/[0.06] shrink-0">
            <img src="{{ asset('images/logo-musikkita-dark-mode.PNG') }}" alt="Musik KITA"
                 class="h-10 w-full object-contain object-left" style="max-width:160px">
        </div>

        <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5 text-[13px]">
            <div class="px-4 pt-4 pb-1.5 text-[10px] font-bold tracking-widest text-secondary-fixed/40 uppercase font-sans">Menu Guru</div>

            <x-sidebar-item route="guru.dashboard" icon="🏠" label="Beranda"
                :active="request()->routeIs('guru.dashboard')" />
            <x-sidebar-item route="guru.jadwal" icon="📅" label="Jadwal"
                :active="request()->routeIs('guru.jadwal')" />
            <x-sidebar-item route="guru.sesi-pending.index" icon="📋" label="Pending"
                :active="request()->routeIs('guru.sesi-pending*')"
                :badge="$guruPendingCount ?: null" />
            <x-sidebar-item route="guru.laporan.index" icon="📝" label="Laporan"
                :active="request()->routeIs('guru.laporan*')" />
            <x-sidebar-item route="guru.honor" icon="💰" label="Honor"
                :active="request()->routeIs('guru.honor*')" />
        </nav>

        {{-- ===== INFO USER (bawah sidebar) ===== --}}
        <div class="shrink-0 px-4 py-3.5 border-t border-white/[0.06] space-y-2.5">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-secondary/15 flex items-center justify-center text-sm font-bold text-secondary shrink-0">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-semibold text-white/90 truncate">{{ auth()->user()->name }}</div>
                    <div class="text-[10px] text-white/40 truncate">Guru</div>
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <a href="{{ route('guru.profil') }}"
                   class="flex items-center gap-1.5 text-[11px] text-white/60 hover:text-white/90 px-2.5 py-1.5 rounded-full hover:bg-white/5 transition-all font-medium">
                    🔑 Ganti Password
                </a>
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit"
                            class="flex items-center gap-1.5 w-full text-left text-[11px] text-white/60 hover:text-white/90 px-2.5 py-1.5 rounded-full hover:bg-white/5 transition-all font-medium">
                        ← Keluar
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ===== AREA KONTEN ===== --}}
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden lg:ml-56">

        {{-- Topbar --}}
        <div class="relative z-20 shrink-0 h-14 bg-primary border-b border-outline-variant/20 flex items-center px-4 lg:px-6 gap-3 text-on-primary">
            <span class="text-white/90 font-semibold text-sm lg:text-base flex-1">{{ $title }}</span>
            
            {{-- Desktop Topbar Actions (Right Side) --}}
            <div class="hidden lg:flex items-center gap-3 shrink-0">
                <span class="text-xs text-white/60 font-semibold tracking-wide font-sans">
                    {{ now()->translatedFormat('l, j F Y') }}
                </span>
                <div id="topbar-clock"
                     class="text-xs text-secondary-fixed font-mono tracking-wider bg-secondary/20 border border-secondary/30 px-2.5 py-1 rounded-full shrink-0 min-w-[50px] text-center shadow-inner">
                </div>
            </div>

            {{-- Mobile Topbar Actions --}}
            <div class="lg:hidden flex items-center gap-2 shrink-0">
                <a href="{{ route('guru.profil') }}"
                   class="text-[11px] text-white/50 hover:text-white/70 truncate max-w-[120px] transition-colors font-medium">
                    {{ auth()->user()->name }} 🔑
                </a>
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit"
                            title="Keluar"
                            class="flex items-center gap-1 text-[11px] text-white/50 hover:text-red-400 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Konten --}}
        <main class="guru-portal-main flex-1 overflow-y-auto bg-background lg:pb-0">
            @php
                $guruFlashClasses = [
                    'success' => 'bg-secondary-container text-on-secondary-container border-secondary/30',
                    'error'   => 'bg-error-container text-on-error-container border-error/30',
                    'warning' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                ];
            @endphp
            @foreach($guruFlashClasses as $flashType => $flashClass)
            @if(session($flashType))
            <div role="{{ $flashType === 'error' ? 'alert' : 'status' }}"
                 class="mx-4 mt-4 p-3 rounded-lg text-sm font-medium border {{ $flashClass }}">
                {{ session($flashType) }}
            </div>
            @endif
            @endforeach

            {{ $slot }}
        </main>
    </div>
</div>

{{-- ===== BOTTOM NAVIGATION (Mobile) ===== --}}
<nav class="guru-bottom-nav lg:hidden fixed bottom-0 inset-x-0 z-40 bg-primary border-t border-outline-variant/20 flex items-stretch text-on-primary">

    <a href="{{ route('guru.dashboard') }}"
       @if(request()->routeIs('guru.dashboard')) aria-current="page" @endif
       class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors">
        <span class="px-3.5 py-0.5 rounded-full transition-all {{ request()->routeIs('guru.dashboard') ? 'bg-secondary/20 text-secondary-fixed' : 'text-white/50 hover:text-white/85' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
        </span>
        <span class="text-[10px] font-semibold {{ request()->routeIs('guru.dashboard') ? 'text-secondary-fixed' : 'text-white/40' }}">
            Beranda
        </span>
    </a>

    <a href="{{ route('guru.jadwal') }}"
       @if(request()->routeIs('guru.jadwal')) aria-current="page" @endif
       class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors">
        <span class="px-3.5 py-0.5 rounded-full transition-all {{ request()->routeIs('guru.jadwal') ? 'bg-secondary/20 text-secondary-fixed' : 'text-white/50 hover:text-white/85' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </span>
        <span class="text-[10px] font-semibold {{ request()->routeIs('guru.jadwal') ? 'text-secondary-fixed' : 'text-white/40' }}">
            Jadwal
        </span>
    </a>

    <a href="{{ route('guru.sesi-pending.index') }}"
       @if(request()->routeIs('guru.sesi-pending*')) aria-current="page" @endif
       class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors relative">
        <span class="px-3.5 py-0.5 rounded-full transition-all relative {{ request()->routeIs('guru.sesi-pending*') ? 'bg-secondary/20 text-secondary-fixed' : 'text-white/50 hover:text-white/85' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            @if($guruPendingCount > 0)
            <span class="absolute -top-1 -right-1.5 bg-red-500 text-white text-[9px] font-bold
                         min-w-[14px] h-[14px] rounded-full flex items-center justify-center leading-none px-0.5 border border-primary">
                {{ $guruPendingCount }}
            </span>
            @endif
        </span>
        <span class="text-[10px] font-semibold {{ request()->routeIs('guru.sesi-pending*') ? 'text-secondary-fixed' : 'text-white/40' }}">
            Pending
        </span>
    </a>

    <a href="{{ route('guru.laporan.index') }}"
       @if(request()->routeIs('guru.laporan*')) aria-current="page" @endif
       class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors">
        <span class="px-3.5 py-0.5 rounded-full transition-all {{ request()->routeIs('guru.laporan*') ? 'bg-secondary/20 text-secondary-fixed' : 'text-white/50 hover:text-white/85' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </span>
        <span class="text-[10px] font-semibold {{ request()->routeIs('guru.laporan*') ? 'text-secondary-fixed' : 'text-white/40' }}">
            Laporan
        </span>
    </a>

    <a href="{{ route('guru.honor') }}"
       @if(request()->routeIs('guru.honor*')) aria-current="page" @endif
       class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors">
        <span class="px-3.5 py-0.5 rounded-full transition-all {{ request()->routeIs('guru.honor*') ? 'bg-secondary/20 text-secondary-fixed' : 'text-white/50 hover:text-white/85' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
        </span>
        <span class="text-[10px] font-semibold {{ request()->routeIs('guru.honor*') ? 'text-secondary-fixed' : 'text-white/40' }}">
            Honor
        </span>
    </a>

</nav>

<!-- Global Session History Drawer/Modal -->
<div x-data="{
        open: false,
        loading: false,
        studentName: '',
        sessionId: null,
        contentHtml: '',
        error: false,
        monthsList: [],
        activeTab: '',
        historyController: null,
        
        init() {
            window.addEventListener('open-session-history', (e) => {
                this.sessionId = e.detail.sessionId;
                this.studentName = e.detail.studentName;
                
                // ponytail: kalkulasi daftar 3 bulan terakhir di sisi client dari sessionDate
                const [y, m, d] = e.detail.sessionDate.split('-').map(Number);
                const sessionDate = new Date(y, m - 1, d);
                this.monthsList = this.calculateMonthsList(sessionDate);
                
                const baseMonth = sessionDate.getMonth() + 1;
                const baseYear = sessionDate.getFullYear();
                this.activeTab = `${baseMonth}-${baseYear}`;
                
                this.open = true;
                this.$nextTick(() => {
                    if (!this.$refs.historyDialog.open) {
                        this.$refs.historyDialog.showModal();
                    }
                });
                this.fetchHistory(baseMonth, baseYear);
            });
        },

        calculateMonthsList(baseDate) {
            const list = [];
            const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            
            for (let i = 0; i < 3; i++) {
                const tempDate = new Date(baseDate.getFullYear(), baseDate.getMonth() - i, 1);
                const m = tempDate.getMonth() + 1;
                const y = tempDate.getFullYear();
                list.push({
                    label: `${monthNames[tempDate.getMonth()]} ${y}`,
                    shortLabel: `${monthNames[tempDate.getMonth()].substring(0, 3)} ${y.toString().substring(2)}`,
                    month: m,
                    year: y,
                    key: `${m}-${y}`
                });
            }
            return list;
        },

        cleanupHistory(closeDialog = false) {
            cleanupSessionHistory(this, this.$refs.historyDialog, closeDialog);
        },

        closeHistory() {
            this.cleanupHistory(true);
        },

        moveHistoryTab(step, absolute = false) {
            const currentIndex = this.monthsList.findIndex(item => item.key === this.activeTab);
            const targetIndex = absolute
                ? step
                : (currentIndex + step + this.monthsList.length) % this.monthsList.length;
            const item = this.monthsList[targetIndex];
            if (!item) return;

            this.activeTab = item.key;
            this.fetchHistory(item.month, item.year);
            this.$nextTick(() => document.getElementById(`session-history-tab-${item.key}`)?.focus());
        },

        fetchHistory(month, year) {
            this.historyController?.abort();
            this.historyController = new AbortController();
            const controller = this.historyController;
            this.loading = true;
            this.error = false;
            this.contentHtml = '';
            
            fetch('{{ url('/guru/sesi') }}/' + this.sessionId + '/history?month=' + month + '&year=' + year, {
                signal: controller.signal
            })
                .then(res => {
                    if (!res.ok) throw new Error();
                    return res.text();
                })
                .then(html => {
                    if (controller !== this.historyController || !this.open) return;
                    this.contentHtml = html;
                    this.loading = false;
                })
                .catch(error => {
                    if (error.name === 'AbortError') return;
                    if (controller !== this.historyController || !this.open) return;
                    this.error = true;
                    this.loading = false;
                });
        }
     }"
     x-cloak>
     
    <dialog x-ref="historyDialog"
            aria-labelledby="session-history-title"
            @cancel.prevent="closeHistory()"
            @close="cleanupHistory()"
            @click.self="closeHistory()"
            class="fixed z-50 m-0 p-0 border-0 flex-col bg-white shadow-2xl overflow-hidden backdrop:bg-black/45
                   bottom-0 left-0 right-0 top-auto w-full max-w-none rounded-t-2xl max-h-[85%]
                   sm:inset-auto sm:top-1/2 sm:left-1/2 sm:w-[480px] sm:max-w-lg sm:rounded-2xl sm:max-h-[75%]
                   sm:-translate-x-1/2 sm:-translate-y-1/2 open:flex">

        <!-- Handle Seret untuk Mobile -->
        <div class="w-10 h-1 bg-outline-variant/30 rounded-full mx-auto my-3 shrink-0 sm:hidden"></div>

        <!-- Header -->
        <div class="px-5 py-3.5 border-b border-outline-variant/15 flex items-center justify-between shrink-0 bg-surface/30">
            <div>
                <h3 id="session-history-title" class="font-bold text-primary text-sm tracking-tight sm:text-base font-serif">Riwayat Sesi</h3>
                <p class="text-[10px] text-on-surface-variant/60 font-semibold tracking-wide uppercase mt-0.5" x-text="studentName"></p>
            </div>
            <button type="button"
                    aria-label="Tutup riwayat sesi"
                    class="text-on-surface-variant/60 hover:text-primary p-1 rounded-full hover:bg-surface transition-colors cursor-pointer text-base leading-none"
                    @click="closeHistory()">
                &times;
            </button>
        </div>

        <!-- Aksen Filter Tab (impeccable: modern style, high contrast) -->
        <div x-show="open && monthsList.length > 0" class="px-5 py-2.5 bg-surface-container-low border-b border-outline-variant/15 shrink-0">
            <div role="tablist" aria-label="Bulan riwayat sesi" class="flex gap-1.5 p-1 bg-surface-container-high/60 rounded-xl">
                <template x-for="item in monthsList" :key="item.key">
                    <button type="button"
                            role="tab"
                            :id="'session-history-tab-' + item.key"
                            :aria-selected="activeTab === item.key"
                            aria-controls="session-history-panel"
                            :tabindex="activeTab === item.key ? 0 : -1"
                            @click="activeTab = item.key; fetchHistory(item.month, item.year)"
                            @keydown.arrow-left.prevent="moveHistoryTab(-1)"
                            @keydown.arrow-right.prevent="moveHistoryTab(1)"
                            @keydown.home.prevent="moveHistoryTab(0, true)"
                            @keydown.end.prevent="moveHistoryTab(monthsList.length - 1, true)"
                            :class="activeTab === item.key ? 'bg-white text-secondary shadow-sm' : 'text-on-surface-variant hover:text-on-surface'"
                            class="flex-1 py-1.5 px-2 rounded-lg text-xs font-semibold transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary focus-visible:ring-offset-2 cursor-pointer">
                        <span class="block sm:hidden" x-text="item.shortLabel"></span>
                        <span class="hidden sm:block" x-text="item.label"></span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Area Konten Utama -->
        <div role="tabpanel"
             id="session-history-panel"
             :aria-labelledby="'session-history-tab-' + activeTab"
             class="p-5 overflow-y-auto flex-1"
             :aria-busy="loading">
            <div x-show="loading" role="status" class="space-y-4 py-2">
                <span class="sr-only">Memuat riwayat sesi.</span>
                <div class="animate-pulse flex gap-3">
                    <div class="w-3 h-3 bg-outline-variant/40 rounded-full mt-1.5"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-3 bg-outline-variant/40 rounded w-1/3"></div>
                        <div class="h-20 bg-outline-variant/30 rounded-xl"></div>
                    </div>
                </div>
                <div class="animate-pulse flex gap-3">
                    <div class="w-3 h-3 bg-outline-variant/40 rounded-full mt-1.5"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-3 bg-outline-variant/40 rounded w-1/4"></div>
                        <div class="h-16 bg-outline-variant/30 rounded-xl"></div>
                    </div>
                </div>
            </div>

            <div x-show="error" role="alert" class="py-8 text-center text-red-500 text-xs font-semibold">
                Gagal memuat riwayat. Silakan coba lagi.
            </div>
            <div x-show="!loading && !error" role="status" x-html="contentHtml"></div>
        </div>
    </dialog>
</div>

</body>
</html>
