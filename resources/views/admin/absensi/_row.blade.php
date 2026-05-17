@php $isLibur = $session->status === 'LIBUR'; @endphp

<tr class="border-t border-stone-800 hover:bg-stone-800/20 transition-colors"
    data-teacher-id="{{ $session->teacher_id }}"
    data-status="{{ $session->status }}"
    data-murid="{{ $session->student->full_name }}"
    @if(! $isLibur)
    x-data="{
        status: '{{ $session->status }}',
        lateMinutes: {{ $session->late_minutes ?? 15 }},
        substituteId: {{ $session->substitute_teacher_id ?? 'null' }},
        substituteLabel: @js($session->substituteTeacher?->name ?? ''),
        showModal: null,
        loading: false,
        async save(newStatus, extra = {}) {
            this.loading = true;
            try {
                const res = await fetch('{{ route('admin.absensi.update', $session) }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ status: newStatus, ...extra })
                });
                const data = await res.json();
                if (data.success) {
                    this.status = data.status;
                    if (data.late_minutes) this.lateMinutes = data.late_minutes;
                    if (data.substitute_teacher_name) this.substituteLabel = data.substitute_teacher_name;
                    this.showModal = null;
                    this.$el.dataset.status = data.status;
                }
            } finally { this.loading = false; }
        }
    }"
    :class="status !== 'SCHEDULED' ? 'opacity-60' : ''"
    @endif
>
    {{-- Jam --}}
    <td class="px-4 py-2.5 font-bold text-sm"
        @if(! $isLibur)
        :class="status === 'SCHEDULED' ? 'text-amber-400' : 'text-gray-500'"
        @else
        class="text-gray-500"
        @endif>
        {{ substr($session->start_time, 0, 5) }}
    </td>

    {{-- Murid --}}
    <td class="px-3 py-2.5 text-sm"
        @if(! $isLibur)
        :class="status === 'SCHEDULED' ? 'text-gray-100 font-medium' : 'text-gray-500'"
        @else
        class="text-gray-500"
        @endif>
        {{ $session->student->full_name }}
    </td>

    {{-- Guru --}}
    <td class="px-3 py-2.5 text-xs text-gray-400">{{ $session->teacher->name }}</td>

    {{-- Ruang --}}
    <td class="px-3 py-2.5 text-xs text-gray-400">{{ $session->room?->code ?? '—' }}</td>

    {{-- Aksi --}}
    <td class="px-4 py-2.5 text-right">

        @if($isLibur)
            <span class="bg-gray-500/10 text-gray-500 border border-gray-500/20 rounded px-3 py-1 text-xs">
                🗓 LIBUR
            </span>

        @else
            {{-- Badge (setelah status diinput / baru di-update via AJAX) --}}
            <div x-show="status !== 'SCHEDULED'" class="flex items-center justify-end gap-2">
                <span class="rounded px-3 py-1 text-xs border"
                    :class="{
                        'bg-emerald-500/15 text-emerald-400 border-emerald-500/20': status === 'HADIR',
                        'bg-orange-500/15 text-orange-400 border-orange-500/20': status === 'HADIR_TERLAMBAT',
                        'bg-red-500/15 text-red-400 border-red-500/20': status === 'HANGUS',
                        'bg-amber-400/10 text-amber-300 border-amber-400/20': status === 'IZIN_RESCHEDULE',
                        'bg-blue-500/15 text-blue-400 border-blue-500/20': status === 'IZIN_VIDEO',
                        'bg-violet-500/15 text-violet-400 border-violet-500/20': status === 'DIGANTI',
                    }"
                    x-text="
                        status === 'HADIR'           ? '✓ HADIR' :
                        status === 'HADIR_TERLAMBAT' ? '⏱ +' + lateMinutes + ' mnt' :
                        status === 'HANGUS'          ? '✕ HANGUS' :
                        status === 'IZIN_RESCHEDULE' ? '📅 IZIN' :
                        status === 'IZIN_VIDEO'      ? '📹 VIDEO' :
                        status === 'DIGANTI'         ? '↔ ' + substituteLabel : status
                    ">
                </span>
                <button @click="status = 'SCHEDULED'"
                    class="text-gray-600 hover:text-gray-300 text-xs underline">ubah</button>
            </div>

            {{-- Tombol aksi (status belum diinput) --}}
            <div x-show="status === 'SCHEDULED'"
                class="flex items-center justify-end gap-1.5"
                :class="loading ? 'opacity-50 pointer-events-none' : ''">

                <button @click="save('HADIR')"
                    class="bg-emerald-600 hover:bg-emerald-500 text-white rounded px-3 py-1.5 text-xs font-semibold">
                    HADIR
                </button>
                <button @click="save('HANGUS')"
                    class="bg-red-500/15 hover:bg-red-500/25 text-red-400 border border-red-500/20 rounded px-3 py-1.5 text-xs">
                    HANGUS
                </button>
                <button @click="save('IZIN_RESCHEDULE')"
                    class="bg-amber-400/10 hover:bg-amber-400/20 text-amber-300 border border-amber-400/20 rounded px-3 py-1.5 text-xs">
                    IZIN
                </button>
                <button @click="save('IZIN_VIDEO')"
                    class="bg-blue-500/15 hover:bg-blue-500/25 text-blue-400 border border-blue-500/20 rounded px-3 py-1.5 text-xs">
                    VIDEO
                </button>

                {{-- Tombol ··· dengan dropdown --}}
                <div class="relative">
                    <button @click="showModal = showModal === 'menu' ? null : 'menu'"
                        class="bg-violet-500/10 hover:bg-violet-500/20 text-violet-400 border border-violet-500/20 rounded px-2.5 py-1.5 text-xs">
                        ···
                    </button>

                    {{-- Dropdown menu --}}
                    <div x-show="showModal === 'menu'" @click.outside="showModal = null"
                        class="absolute right-0 top-8 z-20 bg-stone-800 border border-stone-600 rounded-lg shadow-xl w-36 py-1">
                        <button @click="showModal = 'terlambat'"
                            class="w-full text-left px-4 py-2 text-orange-400 text-xs hover:bg-stone-700">
                            Terlambat
                        </button>
                        <button @click="showModal = 'diganti'"
                            class="w-full text-left px-4 py-2 text-violet-400 text-xs hover:bg-stone-700">
                            Diganti
                        </button>
                    </div>

                    {{-- Mini-modal: TERLAMBAT --}}
                    <div x-show="showModal === 'terlambat'" @click.outside="showModal = null"
                        class="absolute right-0 top-8 z-20 bg-stone-800 border border-stone-600 rounded-xl shadow-xl w-56 p-4">
                        <p class="text-gray-400 text-xs mb-3 truncate">
                            {{ $session->student->full_name }} · {{ $session->teacher->name }}
                        </p>
                        <label class="block text-gray-500 text-xs mb-1">Terlambat berapa menit?</label>
                        <div class="flex items-center gap-2 mb-4">
                            <input type="number" x-model.number="lateMinutes" min="1" max="60"
                                class="bg-stone-700 border border-stone-500 text-gray-100 rounded px-3 py-1.5 w-20 text-center text-sm">
                            <span class="text-gray-400 text-xs">menit</span>
                        </div>
                        <div class="flex gap-2">
                            <button @click="save('HADIR_TERLAMBAT', { late_minutes: lateMinutes })"
                                class="flex-1 bg-amber-500 hover:bg-amber-400 text-stone-900 font-semibold text-xs py-2 rounded-lg">
                                Simpan
                            </button>
                            <button @click="showModal = null"
                                class="bg-stone-700 hover:bg-stone-600 text-gray-400 text-xs py-2 px-3 rounded-lg">
                                Batal
                            </button>
                        </div>
                    </div>

                    {{-- Mini-modal: DIGANTI --}}
                    <div x-show="showModal === 'diganti'" @click.outside="showModal = null"
                        class="absolute right-0 top-8 z-20 bg-stone-800 border border-stone-600 rounded-xl shadow-xl w-64 p-4">
                        <p class="text-gray-400 text-xs mb-3 truncate">
                            {{ $session->student->full_name }} · {{ $session->teacher->name }}
                        </p>
                        <label class="block text-gray-500 text-xs mb-1">Guru pengganti</label>
                        <select x-model="substituteId"
                            class="w-full bg-stone-700 border border-stone-500 text-gray-100 rounded px-3 py-1.5 text-sm mb-1">
                            <option value="">— Pilih guru pengganti —</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-gray-600 text-xs mb-3">Honor otomatis ke guru pengganti.</p>
                        <div class="flex gap-2">
                            <button @click="if(substituteId) save('DIGANTI', { substitute_teacher_id: substituteId })"
                                :disabled="!substituteId"
                                class="flex-1 bg-amber-500 hover:bg-amber-400 disabled:opacity-40 disabled:cursor-not-allowed text-stone-900 font-semibold text-xs py-2 rounded-lg">
                                Simpan
                            </button>
                            <button @click="showModal = null"
                                class="bg-stone-700 hover:bg-stone-600 text-gray-400 text-xs py-2 px-3 rounded-lg">
                                Batal
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        @endif
    </td>
</tr>
