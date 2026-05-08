<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Master Data — Guru</h2>
                <div class="text-xs text-gray-500 mt-0.5">
                    {{ $teachers->where('is_active', true)->count() }} aktif
                    dari {{ $teachers->count() }} total guru
                </div>
            </div>
            @role('Owner|Admin')
            <a href="{{ route('teachers.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold transition-colors"
               style="background:#D4A853;color:#1A1000">
                + Tambah Guru
            </a>
            @endrole
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">

        {{-- Flash messages --}}
        @if(session('success'))
        <div class="mb-5 p-3 rounded-lg text-sm"
             style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
            {{ session('success') }}
        </div>
        @endif

        {{-- ===== GRID KARTU GURU ===== --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($teachers as $idx => $t)
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 fade-in-up
                        transition-all duration-200 hover:shadow-md"
                 style="animation-delay:{{ $idx * 60 }}ms;
                        --hover-border:rgba(212,168,83,0.4)"
                 onmouseenter="this.style.borderColor='rgba(212,168,83,0.4)'"
                 onmouseleave="this.style.borderColor='rgba(255,255,255,0.07)'">

                {{-- Header: Avatar + Nama + Status --}}
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center gap-3">
                        {{-- Avatar inisial --}}
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center
                                    text-base font-bold shrink-0"
                             style="background:rgba(212,168,83,0.15);color:#D4A853">
                            {{ strtoupper(substr($t->name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="text-sm font-bold text-gray-800">{{ $t->name }}</div>
                            <div class="text-xs font-mono text-gray-500">{{ $t->code }}</div>
                        </div>
                    </div>
                    {{-- Badge status --}}
                    @if($t->is_active)
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold shrink-0"
                          style="background:rgba(52,211,153,0.12);color:#34D399">Aktif</span>
                    @else
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold shrink-0"
                          style="background:rgba(139,146,168,0.12);color:#8B92A8">Non-aktif</span>
                    @endif
                </div>

                {{-- Instrumen tags --}}
                <div class="flex flex-wrap gap-1.5 mb-4">
                    @forelse($t->instruments as $ins)
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                          style="{{ $ins->pivot->is_primary
                              ? 'background:rgba(212,168,83,0.18);color:#D4A853'
                              : 'background:rgba(255,255,255,0.06);color:#8B92A8' }}">
                        {{ $ins->pivot->is_primary ? '★ ' : '' }}{{ $ins->name }}
                    </span>
                    @empty
                    <span class="text-xs text-gray-400">Belum ada instrumen</span>
                    @endforelse
                </div>

                {{-- Stats: murid aktif + email --}}
                <div class="grid grid-cols-2 gap-2 mb-4">
                    <div class="rounded-lg p-2.5"
                         style="background:rgba(255,255,255,0.04)">
                        <div class="text-[10px] uppercase tracking-wide text-gray-500 mb-1">Murid Aktif</div>
                        <div class="text-lg font-bold text-gray-800">{{ $t->active_students ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg p-2.5"
                         style="background:rgba(255,255,255,0.04)">
                        <div class="text-[10px] uppercase tracking-wide text-gray-500 mb-1">Instrumen</div>
                        <div class="text-lg font-bold text-gray-800">{{ $t->instruments->count() }}</div>
                    </div>
                </div>

                {{-- Email (jika ada) --}}
                @if($t->email)
                <div class="text-xs text-gray-400 mb-4 truncate">{{ $t->email }}</div>
                @endif

                {{-- Aksi --}}
                @role('Owner|Admin')
                <div class="flex gap-2 pt-3 border-t border-gray-100">
                    <a href="{{ route('teachers.edit', $t->id) }}"
                       class="flex-1 text-center py-1.5 rounded-lg text-xs font-semibold transition-colors"
                       style="background:rgba(212,168,83,0.15);color:#D4A853">
                        Edit
                    </a>
                    @role('Owner')
                    <form action="{{ route('teachers.destroy', $t->id) }}" method="POST"
                          onsubmit="return confirm('Yakin hapus guru {{ addslashes($t->name) }}?')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                                style="background:rgba(248,113,113,0.12);color:#F87171">
                            Hapus
                        </button>
                    </form>
                    @endrole
                </div>
                @endrole

            </div>
            @endforeach
        </div>

    </div>
</x-app-layout>
