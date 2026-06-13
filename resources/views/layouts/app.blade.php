<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Musik KITA') }}</title>

        <!-- Fonts: DM Sans + Playfair Display via Bunny.net -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=dm-sans:300,400,500,600,700|playfair-display:600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

    </head>
    <body class="font-sans antialiased bg-mk-bg text-mk-text">

        {{-- Layout: Sidebar gelap (kiri) + Area Konten (kanan) --}}
        <div class="flex h-screen overflow-hidden"
             x-data="{ sidebarOpen: false }"
             data-theme="mahogany-mint">

            {{-- ===== MOBILE OVERLAY ===== --}}
            <div x-show="sidebarOpen"
                 x-transition:enter="transition-opacity ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="sidebarOpen = false"
                 class="fixed inset-0 bg-black/50 z-20 lg:hidden"
                 x-cloak>
            </div>

            {{-- ===== SIDEBAR ===== --}}
            <aside class="fixed inset-y-0 left-0 z-30 w-56 bg-mk-sidebar flex flex-col shrink-0
                          border-r border-white/[0.06]
                          transition-transform duration-200 ease-in-out
                          lg:relative lg:translate-x-0"
                   :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">

                @include('layouts.navigation')

            </aside>

            {{-- ===== AREA KONTEN ===== --}}
            <div class="flex-1 flex flex-col min-h-0 overflow-hidden">

                {{-- Topbar --}}
                <div class="mk-topbar relative z-20 shrink-0 h-14 bg-mk-sidebar border-b border-white/[0.06]
                            flex items-center px-4 lg:px-6 gap-3">

                    {{-- Hamburger (mobile) --}}
                    <button @click="sidebarOpen = !sidebarOpen"
                            class="lg:hidden p-1.5 rounded-md text-white/55 hover:text-white/80
                                   hover:bg-white/5 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    {{-- Identitas sistem --}}
                    <div class="flex-1 items-center hidden lg:flex">
                        <img src="{{ asset('images/logo-musikkita-dark-mode.PNG') }}"
                             alt="Musik KITA" class="h-8 object-contain object-left"
                             style="max-width:140px">
                    </div>

                    {{-- Kanan: Tanggal + Bell Notif + Avatar + Keluar --}}
                    <div class="flex items-center gap-3 ml-auto">
                        <span class="hidden sm:block text-xs text-white/60 font-semibold tracking-wide font-sans">
                            {{ now()->translatedFormat('l, j F Y') }}
                        </span>

                        {{-- Bell Notifikasi Auto-Mundur (hanya Admin & Owner) --}}
                        @if(auth()->check() && auth()->user()->hasAnyRole(['Admin', 'Owner']) && ($overdueNotifCount ?? 0) > 0)
                        <div class="relative" x-data="{ terbuka: false }" @click.away="terbuka = false">

                            {{-- Tombol Bell --}}
                            <button @click="terbuka = !terbuka"
                                    class="relative flex items-center justify-center w-8 h-8 rounded-full
                                           border border-white/10 bg-white/5 hover:bg-white/10 hover:border-white/20
                                           transition-all text-xs text-white/85 shadow-sm"
                                    :class="terbuka ? 'border-secondary bg-white/10' : ''"
                                    title="Notifikasi auto-mundur">
                                🔔
                                <span class="absolute -top-1.5 -right-1.5 flex items-center justify-center
                                             min-w-[16px] h-4 px-1 rounded-full bg-red-500
                                             text-white text-[9px] font-bold border-2 border-mk-sidebar shadow-sm">
                                    {{ $overdueNotifCount ?? 0 }}
                                </span>
                            </button>

                            {{-- Dropdown --}}
                            <div x-show="terbuka"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 x-cloak
                                 class="absolute right-0 top-full mt-2 w-80 z-50
                                        bg-mk-card border border-mk-border rounded-2xl
                                        shadow-lg overflow-hidden">

                                {{-- Header --}}
                                <div class="flex items-center justify-between px-4 py-3
                                            border-b border-mk-border bg-mk-bg/40">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-mk-text">Konfirmasi Auto-Mundur</span>
                                        <span class="text-[9px] font-bold text-white bg-red-500
                                                     px-1.5 py-0.5 rounded-full">
                                            {{ $overdueNotifCount ?? 0 }}
                                        </span>
                                    </div>
                                    {{-- Tombol mark all read --}}
                                    <button onclick="markAllRead(this)"
                                            class="text-[10px] text-secondary hover:text-secondary-container hover:underline font-semibold transition-colors">
                                        Tandai semua dibaca
                                    </button>
                                </div>

                                {{-- List notifikasi --}}
                                <div class="max-h-72 overflow-y-auto">
                                    @foreach($overdueNotifs ?? [] as $notif)
                                    @php $d = $notif->data; @endphp
                                    <div class="flex items-start gap-3 px-4 py-3
                                                border-b border-mk-border/50 last:border-0
                                                hover:bg-mk-cardHover/50 transition-colors">
                                        <div class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0 mt-1.5"></div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs font-bold text-mk-text truncate">
                                                {{ $d['student_name'] }}
                                            </div>
                                            <div class="text-[10px] text-mk-muted mt-0.5">
                                                Tunggakan {{ $d['invoice_month'] }} ·
                                                <span class="text-error font-semibold">
                                                    Rp {{ number_format($d['total_overdue'], 0, ',', '.') }}
                                                </span>
                                            </div>
                                        </div>
                                        <a href="{{ $d['student_url'] }}"
                                           onclick="markRead('{{ $notif->id }}', this)"
                                           class="flex-shrink-0 text-[10px] text-secondary font-semibold
                                                  px-2 py-1 rounded-lg border border-mk-border
                                                  hover:bg-secondary/10 transition-colors">
                                            Tinjau →
                                        </a>
                                    </div>
                                    @endforeach
                                </div>

                                {{-- Footer --}}
                                <div class="px-4 py-2 border-t border-mk-border bg-mk-bg/10">
                                    <p class="text-[9px] text-mk-dim text-center font-medium">
                                        Klik Tinjau → halaman murid → klik Mundurkan
                                    </p>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- Jam digital --}}
                        <div id="topbar-clock"
                             class="text-xs text-[#b1f0ce] font-mono tracking-wider bg-[#2c694e]/20 border border-[#2c694e]/30 px-2.5 py-1 rounded-full shrink-0 min-w-[50px] text-center shadow-inner">
                        </div>

                        {{-- Tombol keluar --}}
                        <form method="POST" action="{{ route('logout') }}" class="m-0">
                            @csrf
                            <button type="submit"
                                    class="text-xs text-white/70 hover:text-white/95 hover:border-white/20 border border-white/10 transition-all px-3 py-1.5 rounded-full bg-white/5 font-semibold shadow-sm">
                                Keluar
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Page Header --}}
                @isset($header)
                <div class="mk-content mk-page-header shrink-0 bg-mk-card border-b border-white/[0.06] px-4 lg:px-8 py-4">
                    {{ $header }}
                </div>
                @endisset

                {{-- Konten Halaman --}}
                <main class="mk-content flex-1 overflow-y-auto bg-mk-bg">
                    {{-- Flash Messages (satu sumber — kontras tinggi agar teks terbaca di dark/light) --}}
                    @php
                        $mkFlashStyles = [
                            'success' => 'background:rgba(52,211,153,0.14);color:#6EE7B7;border:1px solid rgba(52,211,153,0.35)',
                            'error'   => 'background:rgba(248,113,113,0.14);color:#FCA5A5;border:1px solid rgba(248,113,113,0.35)',
                            'warning' => 'background:rgba(251,191,36,0.14);color:#FCD34D;border:1px solid rgba(251,191,36,0.35)',
                            'info'    => 'background:rgba(96,165,250,0.14);color:#93C5FD;border:1px solid rgba(96,165,250,0.35)',
                        ];
                    @endphp
                    @foreach($mkFlashStyles as $flashType => $flashStyle)
                    @if(session($flashType))
                    <div class="mx-4 lg:mx-8 mt-4 p-3 rounded-lg text-sm font-medium" style="{{ $flashStyle }}">
                        @if($flashType === 'warning')<span class="mr-1" aria-hidden="true">⚠️</span>@endif
                        {{ session($flashType) }}
                    </div>
                    @endif
                    @endforeach

                    {{ $slot }}
                </main>

            </div>
        </div>
        @stack('scripts')

        <script>
        function markRead(notifId, linkEl) {
            fetch(`/notifications/${notifId}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            }).catch(() => {});
        }

        function markAllRead(btn) {
            btn.disabled = true;
            fetch('/notifications/read-all', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            })
            .then(() => window.location.reload())
            .catch(() => { btn.disabled = false; });
        }

        // Jam digital topbar — update setiap detik
        (function() {
            const el = document.getElementById('topbar-clock');
            function tick() {
                const now = new Date();
                const hh  = String(now.getHours()).padStart(2, '0');
                const mm  = String(now.getMinutes()).padStart(2, '0');
                const ss  = String(now.getSeconds()).padStart(2, '0');
                el.textContent = `${hh}:${mm}:${ss}`;
            }
            tick();
            setInterval(tick, 1000);
        })();
        </script>
    </body>
</html>