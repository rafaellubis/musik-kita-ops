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
             x-data="{
                 sidebarOpen: false,
                 theme: localStorage.getItem('mk-theme') || 'dark',
                 toggleTheme() {
                     this.theme = this.theme === 'dark' ? 'light' : 'dark';
                     localStorage.setItem('mk-theme', this.theme);
                 }
             }"
             :data-theme="theme">

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
                <div class="mk-topbar shrink-0 h-14 bg-mk-sidebar border-b border-white/[0.06]
                            flex items-center px-4 lg:px-6 gap-3">

                    {{-- Hamburger (mobile) --}}
                    <button @click="sidebarOpen = !sidebarOpen"
                            class="lg:hidden p-1.5 rounded-md text-mk-muted hover:text-mk-text
                                   hover:bg-white/5 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    {{-- Identitas sistem --}}
                    <div class="flex-1 text-xs text-mk-dim hidden lg:block">
                        Musik KITA — Sistem Operasional
                    </div>

                    {{-- Kanan: Tanggal + Avatar + Toggle tema + Keluar --}}
                    <div class="flex items-center gap-3 ml-auto">
                        <span class="hidden sm:block text-xs text-mk-dim">
                            {{ now()->translatedFormat('l, j F Y') }}
                        </span>

                        {{-- Avatar inisial (nama tampil di sidebar kiri bawah) --}}
                        <div class="w-7 h-7 rounded-full bg-mk-accentDim flex items-center
                                    justify-center text-xs font-bold text-mk-accent shrink-0">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>

                        {{-- Tombol toggle tema gelap/terang --}}
                        <button @click="toggleTheme()"
                                class="text-mk-dim hover:text-mk-muted transition-colors p-1.5 rounded hover:bg-white/5 text-sm leading-none"
                                :title="theme === 'dark' ? 'Beralih ke tema terang' : 'Beralih ke tema gelap'">
                            <span x-text="theme === 'dark' ? '☀️' : '🌙'">☀️</span>
                        </button>

                        {{-- Tombol keluar --}}
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="text-xs text-mk-dim hover:text-mk-muted transition-colors
                                           px-2 py-1 rounded hover:bg-white/5">
                                Keluar
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Page Header — tema menyesuaikan pilihan dark/light --}}
                @isset($header)
                <div :class="theme === 'dark' ? 'dark-content' : 'light-content'"
                     class="shrink-0 bg-mk-card border-b border-white/[0.06] px-4 lg:px-8 py-4">
                    {{ $header }}
                </div>
                @endisset

                {{-- Konten Halaman — tema menyesuaikan pilihan dark/light --}}
                <main :class="theme === 'dark' ? 'dark-content' : 'light-content'"
                      class="flex-1 overflow-y-auto bg-mk-bg">
                    {{ $slot }}
                </main>

            </div>
        </div>
        @stack('scripts')
    </body>
</html>
