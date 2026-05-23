<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl">{{ $event->name }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $event->event_number }} · {{ $event->type_label }} ·
                    {{ $event->event_date->format('d M Y') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if($event->isDraft() && auth()->user()->hasRole('Owner'))
                    <a href="{{ route('events.edit', $event) }}"
                       class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-sm">Edit</a>
                    <form method="POST" action="{{ route('events.complete', $event) }}"
                          onsubmit="return confirm('Tandai event ini sebagai SELESAI? Setelah itu peserta tidak bisa diubah.')">
                        @csrf
                        <button type="submit"
                                class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded text-sm">
                            Tandai Selesai
                        </button>
                    </form>
                @elseif($event->isCompleted())
                    <span class="px-3 py-1.5 bg-green-100 text-green-700 rounded text-sm font-medium">
                        ✓ Selesai
                    </span>
                @endif
                <a href="{{ route('events.index') }}" class="text-sm text-gray-600 hover:underline ml-2">← Kembali</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-700 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded">{{ session('error') }}</div>
            @endif

            {{-- ===== INFO EVENT ===== --}}
            @if($event->notes)
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <p class="text-sm text-gray-600">{{ $event->notes }}</p>
            </div>
            @endif

            {{-- ===== PESERTA ===== --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b flex justify-between items-center bg-gray-50">
                    <h3 class="font-semibold text-sm">
                        Daftar Peserta ({{ $event->participants->count() }} murid)
                    </h3>
                    @if($event->isDraft())
                    <span class="text-xs text-gray-400">Tambah peserta di bawah tabel</span>
                    @endif
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-xs text-gray-500 uppercase text-left">
                            <th class="px-4 py-2">Murid</th>
                            <th class="px-4 py-2">Partisipasi</th>
                            <th class="px-4 py-2">Paket Saat Ini</th>
                            <th class="px-4 py-2 text-right">Biaya</th>
                            <th class="px-4 py-2">Guru Pendamping</th>
                            @if($event->hasExam() && $event->isCompleted())
                                <th class="px-4 py-2 text-center">Hasil Ujian</th>
                                <th class="px-4 py-2 text-center">Grade</th>
                            @endif
                            @if($event->isDraft() && auth()->user()->hasRole(['Owner', 'Admin']))
                                <th class="px-4 py-2"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($event->participants as $p)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-2">
                                    <a href="{{ route('students.show', $p->student) }}"
                                       class="font-medium text-indigo-600 hover:underline">
                                        {{ $p->student->full_name }}
                                    </a>
                                    <span class="text-xs text-gray-400 ml-1">{{ $p->student->student_code }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    @if($p->participation_type === 'UJIAN_TAMPIL')
                                        <span class="px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-700">
                                            Ujian + Tampil
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700">
                                            Tampil Saja
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-500">
                                    {{ $p->enrollment?->package?->code ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-right font-mono text-xs">
                                    Rp {{ number_format($p->fee_amount, 0, ',', '.') }}
                                </td>
                                {{-- Kolom Guru Pendamping (tampil untuk semua event; dropdown hanya untuk DRAFT) --}}
                                <td class="px-4 py-3">
                                    @if($event->isDraft() && (auth()->user()->hasRole('Owner') || auth()->user()->hasRole('Admin')))
                                        <form method="POST"
                                              action="{{ route('event-participants.update-teacher', $p) }}"
                                              class="flex items-center gap-1">
                                            @csrf @method('PATCH')
                                            <select name="accompanying_teacher_id"
                                                    onchange="this.form.submit()"
                                                    class="text-sm border-gray-300 rounded-md py-1">
                                                <option value="">— Tidak ada —</option>
                                                @foreach($activeTeachers as $teacher)
                                                    <option value="{{ $teacher->id }}"
                                                        {{ $p->accompanying_teacher_id == $teacher->id ? 'selected' : '' }}>
                                                        {{ $teacher->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @else
                                        <span class="text-sm text-gray-600">
                                            {{ $p->accompanyingTeacher?->name ?? '—' }}
                                        </span>
                                    @endif
                                </td>
                                @if($event->hasExam() && $event->isCompleted())
                                    <td class="px-4 py-2 text-center">
                                        @if($p->participation_type === 'UJIAN_TAMPIL')
                                            @if($p->exam_result === 'LULUS')
                                                <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Lulus</span>
                                            @elseif($p->exam_result === 'TIDAK_LULUS')
                                                <span class="px-2 py-0.5 rounded text-xs bg-red-100 text-red-700">Tidak Lulus</span>
                                            @else
                                                <span class="text-gray-400 text-xs">—</span>
                                            @endif
                                        @else
                                            <span class="text-gray-300 text-xs">n/a</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-center text-xs text-gray-500">
                                        @if($p->grade_before && $p->grade_after)
                                            <span class="text-gray-400">{{ $p->grade_before }}</span>
                                            → <span class="text-green-700 font-medium">{{ $p->grade_after }}</span>
                                        @elseif($p->grade_before)
                                            {{ $p->grade_before }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endif
                                @if($event->isDraft() && auth()->user()->hasRole(['Owner', 'Admin']))
                                    <td class="px-4 py-2 text-right">
                                        <form method="POST"
                                              action="{{ route('event-participants.destroy', $p) }}"
                                              onsubmit="return confirm('Hapus {{ $p->student->full_name }} dari daftar peserta?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Hapus</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-gray-400 text-sm">
                                    Belum ada peserta.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($event->participants->count() > 0)
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="3" class="px-4 py-2 text-sm font-medium">Total Biaya Peserta</td>
                            <td class="px-4 py-2 text-right font-semibold font-mono text-sm">
                                Rp {{ number_format($event->participants->sum('fee_amount'), 0, ',', '.') }}
                            </td>
                            <td></td>
                            @if($event->hasExam() && $event->isCompleted())
                                <td colspan="2"></td>
                            @endif
                            @if($event->isDraft() && auth()->user()->hasRole(['Owner', 'Admin']))
                                <td></td>
                            @endif
                        </tr>
                    </tfoot>
                    @endif
                </table>

                {{-- Form tambah peserta (hanya saat DRAFT) --}}
                @if($event->isDraft() && auth()->user()->hasRole(['Owner', 'Admin']))
                <div class="px-4 py-4 border-t bg-gray-50">
                    <h4 class="text-xs font-medium text-gray-700 mb-3">Tambah Peserta</h4>
                    <form method="POST" action="{{ route('events.participants.store', $event) }}"
                          class="flex flex-wrap gap-3 items-end">
                        @csrf

                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Murid</label>
                            <select name="student_id" required
                                    class="border-gray-300 rounded text-sm w-72">
                                <option value="">-- Pilih murid --</option>
                                @foreach($availableStudents as $s)
                                    <option value="{{ $s->id }}">
                                        {{ $s->full_name }} ({{ $s->student_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Partisipasi</label>
                            <select name="participation_type" required
                                    class="border-gray-300 rounded text-sm">
                                @if($event->hasExam())
                                    <option value="UJIAN_TAMPIL">Ujian + Tampil (Rp 395.000)</option>
                                    <option value="TAMPIL_SAJA">Tampil Saja (Rp 295.000)</option>
                                @else
                                    <option value="TAMPIL_SAJA">Tampil Saja (Rp 295.000)</option>
                                @endif
                            </select>
                        </div>

                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
                            + Tambah
                        </button>
                    </form>
                </div>
                @endif
            </div>

            {{-- ===== INPUT HASIL UJIAN (setelah COMPLETED) ===== --}}
            @if($event->hasExam() && $event->isCompleted() && auth()->user()->hasRole('Owner'))
            @php
                $ujianPeserta = $event->participants->where('participation_type', 'UJIAN_TAMPIL');
                $belumDiinput = $ujianPeserta->filter(fn($p) => $p->exam_result === null);
            @endphp
            @if($ujianPeserta->count() > 0)
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50">
                    <h3 class="font-semibold text-sm">Input Hasil Ujian</h3>
                    @if($belumDiinput->count() > 0)
                        <p class="text-xs text-yellow-700 mt-1">
                            {{ $belumDiinput->count() }} peserta belum diinput hasilnya.
                        </p>
                    @endif
                </div>

                <form method="POST" action="{{ route('events.exam-results', $event) }}" class="p-4">
                    @csrf @method('PATCH')

                    <div class="space-y-3">
                        @foreach($ujianPeserta as $p)
                        <div class="flex items-start gap-4 p-3 bg-gray-50 rounded">
                            <div class="flex-1">
                                <div class="font-medium text-sm">{{ $p->student->full_name }}</div>
                                <div class="text-xs text-gray-500">
                                    Paket: {{ $p->enrollment?->package?->code ?? '—' }}
                                    | Grade: {{ $p->enrollment?->package?->grade ?? '—' }}
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                    <input type="radio" name="results[{{ $p->id }}]" value="LULUS"
                                           {{ $p->exam_result === 'LULUS' ? 'checked' : '' }}
                                           class="text-green-600">
                                    <span class="text-green-700 font-medium">Lulus</span>
                                </label>
                                <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                    <input type="radio" name="results[{{ $p->id }}]" value="TIDAK_LULUS"
                                           {{ $p->exam_result === 'TIDAK_LULUS' ? 'checked' : '' }}
                                           class="text-red-600">
                                    <span class="text-red-700">Tidak Lulus</span>
                                </label>
                                <input type="text" name="notes[{{ $p->id }}]"
                                       value="{{ old("notes.{$p->id}", $p->exam_notes) }}"
                                       placeholder="Catatan (opsional)"
                                       maxlength="255"
                                       class="border-gray-300 rounded text-xs w-48">
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        <button type="submit"
                                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
                            Simpan Hasil Ujian
                        </button>
                        <p class="mt-2 text-xs text-gray-500">
                            Grade akan naik otomatis untuk peserta Lulus dengan paket Reguler.
                        </p>
                    </div>
                </form>
            </div>
            @endif
            @endif

            {{-- Honor guru masuk ke slip bulanan M06, bukan slip terpisah --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <h3 class="font-semibold text-sm mb-2">Honor Guru</h3>
                <p class="text-sm text-gray-600">
                    Honor guru untuk event ini dimasukkan manual ke slip honor bulanan
                    masing-masing guru di bulan yang sama dengan event berlangsung.
                </p>
                <a href="{{ route('honors.index', [
                            'year'  => $event->event_date->year,
                            'month' => $event->event_date->month,
                           ]) }}"
                   class="inline-block mt-3 text-sm text-indigo-600 hover:underline">
                    → Lihat Slip Honor {{ $event->event_date->format('F Y') }}
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
