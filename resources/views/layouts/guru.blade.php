<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — {{ config('app.name', 'Musik KITA') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=hanken-grotesk:300,400,500,600,700|playfair-display:600,700&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-background text-on-background">

<div class="flex h-screen overflow-hidden">

    {{-- ===== SIDEBAR (Desktop) ===== --}}
    <aside class="hidden lg:flex fixed inset-y-0 left-0 z-30 w-56 bg-primary flex-col shrink-0 border-r border-outline-variant/20 text-on-primary">

        <div class="px-4 py-3 border-b border-white/[0.06] shrink-0">
            <img src="{{ asset('images/logo-musikkita-dark-mode.PNG') }}" alt="Musik KITA"
                 class="h-10 w-full object-contain object-left" style="max-width:160px">
        </div>

        @php
            $guruPendingCount = (auth()->check() && auth()->user()->teacher)
                ? \App\Models\ClassSession::where('teacher_id', auth()->user()->teacher->id)
                    ->where('status', \App\Models\ClassSession::STATUS_IZIN_PENDING)
                    ->count()
                : 0;
        @endphp
        <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5 text-[13px]">
            <div class="px-4 pt-4 pb-1.5 text-[10px] font-bold tracking-widest text-[#b1f0ce]/40 uppercase font-sans">Menu Guru</div>

            <x-sidebar-item route="guru.dashboard" icon="🏠" label="Home"
                :active="request()->routeIs('guru.dashboard')" />
            <x-sidebar-item route="guru.jadwal" icon="📅" label="Schedule"
                :active="request()->routeIs('guru.jadwal')" />
            <x-sidebar-item route="guru.sesi-pending.index" icon="📋" label="Pending"
                :active="request()->routeIs('guru.sesi-pending*')"
                :badge="$guruPendingCount ?: null" />
            <x-sidebar-item route="guru.laporan.index" icon="📝" label="Report"
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
                     class="text-xs text-[#b1f0ce] font-mono tracking-wider bg-[#2c694e]/20 border border-[#2c694e]/30 px-2.5 py-1 rounded-full shrink-0 min-w-[50px] text-center shadow-inner">
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
        <main class="flex-1 overflow-y-auto bg-background pb-20 lg:pb-0">
            @php
                $guruFlashClasses = [
                    'success' => 'bg-secondary-container text-on-secondary-container border-secondary/30',
                    'error'   => 'bg-error-container text-on-error-container border-error/30',
                    'warning' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                ];
            @endphp
            @foreach($guruFlashClasses as $flashType => $flashClass)
            @if(session($flashType))
            <div class="mx-4 mt-4 p-3 rounded-lg text-sm font-medium border {{ $flashClass }}">
                {{ session($flashType) }}
            </div>
            @endif
            @endforeach

            {{ $slot }}
        </main>
    </div>
</div>

{{-- ===== BOTTOM NAVIGATION (Mobile) ===== --}}
<nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-primary border-t border-outline-variant/20 flex items-stretch h-16 text-on-primary">

    <a href="{{ route('guru.dashboard') }}"
       class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors">
        <span class="px-3.5 py-0.5 rounded-full transition-all {{ request()->routeIs('guru.dashboard') ? 'bg-secondary/20 text-[#b1f0ce]' : 'text-white/50 hover:text-white/85' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
        </span>
        <span class="text-[10px] font-semibold {{ request()->routeIs('guru.dashboard') ? 'text-[#b1f0ce]' : 'text-white/40' }}">
            Home
        </span>
    </a>

    <a href="{{ route('guru.jadwal') }}"
       class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors">
        <span class="px-3.5 py-0.5 rounded-full transition-all {{ request()->routeIs('guru.jadwal') ? 'bg-secondary/20 text-[#b1f0ce]' : 'text-white/50 hover:text-white/85' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </span>
        <span class="text-[10px] font-semibold {{ request()->routeIs('guru.jadwal') ? 'text-[#b1f0ce]' : 'text-white/40' }}">
            Schedule
        </span>
    </a>

    <a href="{{ route('guru.sesi-pending.index') }}"
       class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors relative">
        <span class="px-3.5 py-0.5 rounded-full transition-all relative {{ request()->routeIs('guru.sesi-pending*') ? 'bg-secondary/20 text-[#b1f0ce]' : 'text-white/50 hover:text-white/85' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
        <span class="text-[10px] font-semibold {{ request()->routeIs('guru.sesi-pending*') ? 'text-[#b1f0ce]' : 'text-white/40' }}">
            Pending
        </span>
    </a>

    <a href="{{ route('guru.laporan.index') }}"
       class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors">
        <span class="px-3.5 py-0.5 rounded-full transition-all {{ request()->routeIs('guru.laporan*') ? 'bg-secondary/20 text-[#b1f0ce]' : 'text-white/50 hover:text-white/85' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </span>
        <span class="text-[10px] font-semibold {{ request()->routeIs('guru.laporan*') ? 'text-[#b1f0ce]' : 'text-white/40' }}">
            Report
        </span>
    </a>

    <a href="{{ route('guru.honor') }}"
       class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors">
        <span class="px-3.5 py-0.5 rounded-full transition-all {{ request()->routeIs('guru.honor*') ? 'bg-secondary/20 text-[#b1f0ce]' : 'text-white/50 hover:text-white/85' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
        </span>
        <span class="text-[10px] font-semibold {{ request()->routeIs('guru.honor*') ? 'text-[#b1f0ce]' : 'text-white/40' }}">
            Honor
        </span>
    </a>

</nav>

<script>
(function() {
    const el = document.getElementById('topbar-clock');
    if (el) {
        function tick() {
            const now = new Date();
            const hh  = String(now.getHours()).padStart(2, '0');
            const mm  = String(now.getMinutes()).padStart(2, '0');
            const ss  = String(now.getSeconds()).padStart(2, '0');
            el.textContent = `${hh}:${mm}:${ss}`;
        }
        tick();
        setInterval(tick, 1000);
    }
})();

document.addEventListener('alpine:init', () => {
    Alpine.data('sessionHistoryModal', () => ({
        open: false,
        loading: false,
        studentName: '',
        sessionId: null,
        contentHtml: '',
        init() {
            window.addEventListener('open-session-history', (e) => {
                this.sessionId = e.detail.sessionId;
                this.studentName = e.detail.studentName;
                this.open = true;
                this.loading = true;
                this.contentHtml = '';
                
                fetch('{{ url('/guru/sesi') }}/' + this.sessionId + '/history')
                    .then(res => {
                        if (!res.ok) throw new Error();
                        return res.text();
                    })
                    .then(html => {
                        this.contentHtml = html;
                        this.loading = false;
                    })
                    .catch(() => {
                        this.contentHtml = '<div class="py-8 text-center text-red-500 text-xs font-semibold">Gagal memuat riwayat. Silakan coba lagi.</div>';
                        this.loading = false;
                    });
            });
        }
    }));
});
</script>

<!-- Global Session History Drawer/Modal -->
<div x-data="sessionHistoryModal()" x-cloak>
     
    <!-- Dark Backdrop -->
    <div class="fixed inset-0 z-50 transition-opacity duration-300"
         x-show="open"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="open = false"
         style="background-color: rgba(0, 0, 0, 0.45);"></div>

    <!-- Drawer/Modal Container -->
    <div class="fixed z-50 transition-all duration-300 flex flex-col bg-white shadow-2xl overflow-hidden
                /* Mobile: Slide-up Bottom Sheet */
                bottom-0 left-0 right-0 w-full rounded-t-2xl max-h-[85%]
                /* Desktop: Centered Modal */
                sm:bottom-auto sm:top-1/2 sm:left-1/2 sm:right-auto sm:w-[480px] sm:max-w-lg sm:rounded-2xl sm:max-h-[75%]"
         x-show="open"
         x-transition:enter="ease-out duration-300"
         /* Entrance transition: slide up on mobile, fade & scale on desktop */
         x-transition:enter-start="translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0 sm:-translate-x-1/2 sm:-translate-y-[45%]"
         x-transition:enter-end="translate-y-0 sm:translate-y-0 sm:scale-100 sm:opacity-100 sm:-translate-x-1/2 sm:-translate-y-1/2"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="translate-y-0 sm:translate-y-0 sm:scale-100 sm:opacity-100 sm:-translate-x-1/2 sm:-translate-y-1/2"
         x-transition:leave-end="translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0 sm:-translate-x-1/2 sm:-translate-y-[45%]"
         :style="window.innerWidth >= 640 && open ? 'transform: translate(-50%, -50%)' : ''">

        <!-- Handle Seret untuk Mobile -->
        <div class="w-10 h-1 bg-outline-variant/30 rounded-full mx-auto my-3 shrink-0 sm:hidden"></div>

        <!-- Header -->
        <div class="px-5 py-3.5 border-b border-outline-variant/15 flex items-center justify-between shrink-0 bg-surface/30">
            <div>
                <h3 class="font-bold text-primary text-sm tracking-tight sm:text-base font-serif">Riwayat Sesi</h3>
                <p class="text-[10px] text-on-surface-variant/60 font-semibold tracking-wide uppercase mt-0.5" x-text="studentName"></p>
            </div>
            <button type="button" 
                    class="text-on-surface-variant/60 hover:text-primary p-1 rounded-full hover:bg-surface transition-colors cursor-pointer text-base leading-none"
                    @click="open = false">
                &times;
            </button>
        </div>

        <!-- Area Konten Utama -->
        <div class="p-5 overflow-y-auto flex-1">
            <!-- Loading Skeleton -->
            <div x-show="loading" class="space-y-4 py-2">
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

            <!-- Konten Riwayat Asli via AJAX -->
            <div x-show="!loading" x-html="contentHtml"></div>
        </div>
    </div>
</div>

</body>
</html>
