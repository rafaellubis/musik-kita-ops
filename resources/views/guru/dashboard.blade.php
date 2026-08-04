<x-guru-layout title="Dashboard">

<div class="px-4 pt-5 pb-2">
    <h1 class="text-2xl font-bold text-primary tracking-tight font-sans">Halo, {{ $teacher->name }}</h1>
    <p class="text-xs text-outline font-medium mt-1 tracking-wide">
        {{ \Carbon\Carbon::today()->locale('id')->isoFormat('dddd, D MMMM Y') }}
    </p>
</div>

{{-- ===== BANNER SESI PENDING (tampil hanya jika ada) ===== --}}
@if($jumlahPending > 0)
<div class="mx-4 mb-4">
    <a href="{{ route('guru.sesi-pending.index') }}"
       class="flex items-center gap-4 bg-white border border-outline-variant/15 rounded-2xl px-4 py-3.5 hover:bg-surface-container-low transition-all duration-300 shadow-[rgba(44,105,78,0.04)_0px_2px_8px] hover:shadow-[rgba(44,105,78,0.08)_0px_4px_12px] group">
        <span class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 text-xl font-bold border border-amber-100">
            📋
        </span>
        <div class="flex-1 min-w-0">
            <div class="font-bold text-primary text-sm tracking-tight">{{ $jumlahPending }} Sesi Pending</div>
            <div class="text-xs text-outline mt-0.5">Murid izin, belum ada jadwal pengganti — tap untuk detail</div>
        </div>
        <span class="text-outline group-hover:text-primary transition-colors text-lg font-bold">›</span>
    </a>
</div>
@endif

{{-- ===== SESI HARI INI ===== --}}
<div class="px-4 py-3">
    <h2 class="text-[11px] font-bold tracking-widest text-secondary uppercase mb-4 flex items-center gap-2">
        <span>Sesi Hari Ini</span>
        <span class="flex-1 h-px bg-outline-variant/15"></span>
    </h2>

    <div x-data="{
             loading: false,
             today: '{{ \Carbon\Carbon::today()->toDateString() }}',
             init() {
                 window.addEventListener('session-updated', () => this.fetchTodaySessions());
             },
             async fetchTodaySessions() {
                 this.loading = true;
                 try {
                     const response = await fetch('{{ route('guru.jadwal.sessions') }}?date=' + this.today, {
                         headers: {
                             'Accept': 'application/json',
                             'X-Requested-With': 'XMLHttpRequest'
                         }
                     });
                     if (response.ok) {
                         const data = await response.json();
                         // ponytail: parse HTML partial list sesi untuk menghindari duplikasi layout
                         const parser = new DOMParser();
                         const doc = parser.parseFromString(data.html, 'text/html');
                         const spaceY3 = doc.querySelector('.space-y-3');
                         const emptyState = doc.querySelector('.bg-surface-container-lowest');
                         
                         if (spaceY3) {
                             this.$refs.sessionContainer.innerHTML = spaceY3.innerHTML;
                         } else if (emptyState) {
                             this.$refs.sessionContainer.innerHTML = `
                                 <div class='bg-white rounded-2xl border border-outline-variant/20 px-4 py-12 text-center shadow-[rgba(44,105,78,0.04)_0px_2px_8px]'>
                                     <div class='w-12 h-12 bg-surface-container-low rounded-full flex items-center justify-center mx-auto text-xl mb-3 border border-outline-variant/20'>🎵</div>
                                     <div class='text-primary font-semibold text-sm'>Tenang &amp; Damai</div>
                                     <div class='text-outline text-xs mt-1'>Tidak ada jadwal sesi mengajar untuk hari ini.</div>
                                 </div>
                             `;
                         }
                     }
                 } catch (e) {
                     console.error('Gagal memuat ulang sesi dashboard:', e);
                     alert('Gagal memuat ulang jadwal sesi. Silakan segarkan halaman.');
                 } finally {
                     this.loading = false;
                 }
             }
         }"
         :class="{ 'opacity-50 pointer-events-none': loading }"
         class="transition-opacity duration-150">
        <div x-ref="sessionContainer" class="space-y-3">
            @forelse($sesiHariIni as $idx => $sesi)
                @include('guru._sesi-card', ['sesi' => $sesi, 'teacher' => $teacher, 'sIdx' => $idx])
            @empty
                <div class="bg-white rounded-2xl border border-outline-variant/20 px-4 py-12 text-center shadow-[rgba(44,105,78,0.04)_0px_2px_8px] fade-in-up">
                    <div class="w-12 h-12 bg-surface-container-low rounded-full flex items-center justify-center mx-auto text-xl mb-3 border border-outline-variant/20">
                        🎵
                    </div>
                    <div class="text-primary font-semibold text-sm">Tenang &amp; Damai</div>
                    <div class="text-outline text-xs mt-1">Tidak ada jadwal sesi mengajar untuk hari ini.</div>
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- ===== RINGKASAN BULAN INI ===== --}}
<div class="px-4 pb-8">
    <h2 class="text-[11px] font-bold tracking-widest text-secondary uppercase mb-4 flex items-center gap-2">
        <span>Ringkasan {{ \Carbon\Carbon::now()->locale('id')->isoFormat('MMMM Y') }}</span>
        <span class="flex-1 h-px bg-outline-variant/15"></span>
    </h2>
    
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div class="col-span-2 bg-white rounded-2xl border border-outline-variant/20 px-4 py-4.5 shadow-[rgba(44,105,78,0.04)_0px_2px_8px]">
            <div class="grid grid-cols-3 divide-x divide-outline-variant/20">
                <div class="pr-3">
                    <div class="text-xs font-semibold text-outline">Sesi Terlaksana</div>
                    <div class="text-2xl font-bold text-primary mt-2 font-sans">{{ $totalSesiTerlaksana }}</div>
                </div>
                <div class="px-3">
                    <div class="text-xs font-semibold text-outline">Honor Libur Dibayar</div>
                    <div class="text-base font-bold text-secondary mt-2 font-sans leading-tight">
                        Rp {{ number_format($totalHonorLibur, 0, ',', '.') }}
                    </div>
                </div>
                <div class="pl-3">
                    <div class="text-xs font-semibold text-outline">Sesi Hangus</div>
                    <div class="text-2xl font-bold text-error mt-2 font-sans">{{ $totalSesiHangus }}</div>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl border border-outline-variant/20 px-4 py-4.5 shadow-[rgba(44,105,78,0.04)_0px_2px_8px] hover:shadow-[rgba(44,105,78,0.08)_0px_4px_12px] transition-shadow duration-300">
            <div class="text-xs font-semibold text-outline uppercase tracking-wider">Estimasi Honor</div>
            @if($slipBulanIni)
                <div class="text-xl font-bold text-secondary mt-2 font-sans leading-none">
                    Rp {{ number_format($slipBulanIni->total_honor, 0, ',', '.') }}
                </div>
                <div class="text-[10px] text-outline mt-1.5">
                    {{ $slipBulanIni->status === 'PAID' ? '✓ Sudah Dibayar' : 'Estimasi slip' }}
                </div>
            @else
                <div class="text-xl font-bold text-outline mt-2 font-sans">—</div>
                <div class="text-[10px] text-outline mt-1.5">Honor belum dihitung</div>
            @endif
        </div>
        
        {{-- Kartu Sesi Pending — hanya tampil jika ada pending --}}
        @if($jumlahPending > 0)
        <a href="{{ route('guru.sesi-pending.index') }}"
           class="bg-white rounded-2xl border border-outline-variant/20 px-4 py-4.5 shadow-[rgba(44,105,78,0.04)_0px_2px_8px] hover:shadow-[rgba(44,105,78,0.08)_0px_4px_12px] transition-all duration-300">
            <div class="text-xs font-semibold text-outline uppercase tracking-wider">Sesi Pending</div>
            <div class="text-2xl font-bold text-error mt-2 font-sans flex items-center gap-1.5">
                <span>{{ $jumlahPending }}</span>
                <span class="text-[10px] font-bold text-error bg-error/10 px-1.5 py-0.5 rounded">Tinjau</span>
            </div>
        </a>
        @endif
    </div>
</div>

</x-guru-layout>
