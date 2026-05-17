@php $isLibur = $session->status === 'LIBUR'; @endphp

<tr class="hover:bg-gray-50 transition-colors"
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
                const res = await fetch('{{ route('absensi.update', $session) }}', {
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
        :style="status === 'SCHEDULED' ? 'color:#D4A853;font-weight:700' : ''"
        :class="status !== 'SCHEDULED' ? 'text-gray-500' : ''"
        @else
        class="text-gray-500"
        @endif>
        {{ substr($session->start_time, 0, 5) }}
    </td>

    {{-- Murid --}}
    <td class="px-3 py-2.5 text-sm"
        @if(! $isLibur)
        :class="status === 'SCHEDULED' ? 'text-gray-800 font-medium' : 'text-gray-500'"
        @else
        class="text-gray-500"
        @endif>
        {{ $session->student->full_name }}
    </td>

    {{-- Guru --}}
    <td class="px-3 py-2.5 text-xs text-gray-500">{{ $session->teacher->name }}</td>

    {{-- Ruang --}}
    <td class="px-3 py-2.5 text-xs text-gray-500">{{ $session->room?->code ?? '—' }}</td>

    {{-- Aksi --}}
    <td class="px-4 py-2.5 text-right">

        @if($isLibur)
            <span class="bg-gray-100 text-gray-500 border border-gray-200 rounded px-3 py-1 text-xs">
                🗓 LIBUR
            </span>

        @else
            {{-- Badge (setelah status diinput / baru di-update via AJAX) --}}
            <div x-show="status !== 'SCHEDULED'" class="flex items-center justify-end gap-2">
                <span class="rounded px-3 py-1 text-xs border"
                    :class="{
                        'bg-green-100 text-green-700 border-green-200': status === 'HADIR',
                        'bg-orange-100 text-orange-700 border-orange-200': status === 'HADIR_TERLAMBAT',
                        'bg-red-100 text-red-700 border-red-200': status === 'HANGUS',
                        'bg-yellow-100 text-yellow-700 border-yellow-200': status === 'IZIN_RESCHEDULE',
                        'bg-blue-100 text-blue-700 border-blue-200': status === 'IZIN_VIDEO',
                        'bg-purple-100 text-purple-700 border-purple-200': status === 'DIGANTI',
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
                    class="text-gray-400 hover:text-gray-600 text-xs underline">ubah</button>
            </div>

            {{-- Tombol aksi (status belum diinput) --}}
            <div x-show="status === 'SCHEDULED'"
                class="flex items-center justify-end gap-1.5"
                :class="loading ? 'opacity-50 pointer-events-none' : ''">

                <button @click="save('HADIR')"
                    class="rounded px-3 py-1.5 text-xs font-semibold"
                    style="background:#D4A853;color:#1A1000">
                    HADIR
                </button>
                <button @click="save('HANGUS')"
                    class="border border-red-300 text-red-600 hover:bg-red-50 rounded px-3 py-1.5 text-xs">
                    HANGUS
                </button>
                <button @click="save('IZIN_RESCHEDULE')"
                    class="border border-yellow-300 text-yellow-700 hover:bg-yellow-50 rounded px-3 py-1.5 text-xs">
                    IZIN
                </button>
                <button @click="save('IZIN_VIDEO')"
                    class="border border-blue-300 text-blue-600 hover:bg-blue-50 rounded px-3 py-1.5 text-xs">
                    VIDEO
                </button>

                {{-- Tombol ··· dengan dropdown --}}
                <div class="relative">
                    <button @click="showModal = showModal === 'menu' ? null : 'menu'"
                        class="border border-gray-300 text-gray-600 hover:bg-gray-100 rounded px-2.5 py-1.5 text-xs">
                        ···
                    </button>

                    {{-- Dropdown menu --}}
                    <div x-show="showModal === 'menu'" @click.outside="showModal = null"
                        class="absolute right-0 top-8 z-20 bg-white border border-gray-200 rounded-lg shadow-lg w-36 py-1">
                        <button @click="showModal = 'terlambat'"
                            class="w-full text-left px-4 py-2 text-orange-600 text-xs hover:bg-gray-50">
                            Terlambat
                        </button>
                        <button @click="showModal = 'diganti'"
                            class="w-full text-left px-4 py-2 text-purple-600 text-xs hover:bg-gray-50">
                            Diganti
                        </button>
                    </div>

                    {{-- Mini-modal: TERLAMBAT --}}
                    <div x-show="showModal === 'terlambat'" @click.outside="showModal = null"
                        class="absolute right-0 top-8 z-20 bg-white border border-gray-200 rounded-lg shadow-lg w-56 p-4">
                        <p class="text-gray-500 text-xs mb-3 truncate">
                            {{ $session->student->full_name }} · {{ $session->teacher->name }}
                        </p>
                        <label class="block text-gray-500 text-xs mb-1">Terlambat berapa menit?</label>
                        <div class="flex items-center gap-2 mb-4">
                            <input type="number" x-model.number="lateMinutes" min="1" max="60"
                                class="border border-gray-300 text-gray-700 rounded px-3 py-1.5 w-20 text-center text-sm">
                            <span class="text-gray-500 text-xs">menit</span>
                        </div>
                        <div class="flex gap-2">
                            <button @click="save('HADIR_TERLAMBAT', { late_minutes: lateMinutes })"
                                class="flex-1 font-semibold text-xs py-2 rounded"
                                style="background:#D4A853;color:#1A1000">
                                Simpan
                            </button>
                            <button @click="showModal = null"
                                class="border border-gray-200 text-gray-500 hover:bg-gray-50 text-xs py-2 px-3 rounded">
                                Batal
                            </button>
                        </div>
                    </div>

                    {{-- Mini-modal: DIGANTI --}}
                    <div x-show="showModal === 'diganti'" @click.outside="showModal = null"
                        class="absolute right-0 top-8 z-20 bg-white border border-gray-200 rounded-lg shadow-lg w-64 p-4">
                        <p class="text-gray-500 text-xs mb-3 truncate">
                            {{ $session->student->full_name }} · {{ $session->teacher->name }}
                        </p>
                        <label class="block text-gray-500 text-xs mb-1">Guru pengganti</label>
                        <select x-model="substituteId"
                            class="w-full border border-gray-300 text-gray-700 rounded px-3 py-1.5 text-sm mb-1">
                            <option value="">— Pilih guru pengganti —</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-gray-400 text-xs mb-3">Honor otomatis ke guru pengganti.</p>
                        <div class="flex gap-2">
                            <button @click="if(substituteId) save('DIGANTI', { substitute_teacher_id: substituteId })"
                                :disabled="!substituteId"
                                class="flex-1 disabled:opacity-40 disabled:cursor-not-allowed font-semibold text-xs py-2 rounded"
                                style="background:#D4A853;color:#1A1000">
                                Simpan
                            </button>
                            <button @click="showModal = null"
                                class="border border-gray-200 text-gray-500 hover:bg-gray-50 text-xs py-2 px-3 rounded">
                                Batal
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        @endif
    </td>
</tr>
