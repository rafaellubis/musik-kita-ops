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
                                <td colspan="7" class="px-4 py-6 text-center text-gray-400 text-sm">
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

            {{-- ===== SLIP HONOR GURU ===== --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50 flex justify-between items-center">
                    <h3 class="font-semibold text-sm">Slip Honor Guru ({{ $event->honorSlips->count() }})</h3>
                </div>

                @if($event->honorSlips->count() > 0)
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-xs text-gray-500 uppercase text-left">
                            <th class="px-4 py-2">Nomor</th>
                            <th class="px-4 py-2">Guru</th>
                            <th class="px-4 py-2">Peran</th>
                            <th class="px-4 py-2 text-right">Honor Pokok</th>
                            <th class="px-4 py-2 text-right">Transport</th>
                            <th class="px-4 py-2 text-right">Total</th>
                            <th class="px-4 py-2 text-center">Status</th>
                            <th class="px-4 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($event->honorSlips as $slip)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ $slip->slip_number }}</td>
                            <td class="px-4 py-2 font-medium">{{ $slip->teacher->name }}</td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $slip->role ?? '—' }}</td>
                            <td class="px-4 py-2 text-right font-mono text-xs">
                                Rp {{ number_format($slip->base_honor, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-2 text-right font-mono text-xs">
                                Rp {{ number_format($slip->transport_honor, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-2 text-right font-semibold font-mono text-xs">
                                Rp {{ number_format($slip->total_honor, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if($slip->status === 'PAID')
                                    <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Dibayarkan</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-700">Draft</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                @if(auth()->user()->hasRole('Owner'))
                                    @if(!$slip->isLocked())
                                        <a href="{{ route('event-honor-slips.edit', $slip) }}"
                                           class="text-xs text-indigo-600 hover:underline">Edit</a>
                                        ·
                                        <form method="POST"
                                              action="{{ route('event-honor-slips.mark-paid', $slip) }}"
                                              class="inline"
                                              onsubmit="return confirm('Tandai slip ini sudah dibayarkan?')">
                                            @csrf
                                            <button type="submit" class="text-xs text-green-600 hover:underline">
                                                Bayarkan
                                            </button>
                                        </form>
                                        ·
                                        <form method="POST"
                                              action="{{ route('event-honor-slips.destroy', $slip) }}"
                                              class="inline"
                                              onsubmit="return confirm('Hapus slip honor ini?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Hapus</button>
                                        </form>
                                        ·
                                    @endif
                                @endif
                                <a href="{{ route('event-honor-slips.print', $slip) }}"
                                   target="_blank"
                                   class="text-xs text-gray-600 hover:underline">Cetak</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="5" class="px-4 py-2 text-sm font-medium">Total Seluruh Slip</td>
                            <td class="px-4 py-2 text-right font-bold font-mono text-sm">
                                Rp {{ number_format($event->honorSlips->sum('total_honor'), 0, ',', '.') }}
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
                @endif

                {{-- Form buat slip honor baru (Owner only) --}}
                @if(auth()->user()->hasRole('Owner'))
                <div class="px-4 py-4 border-t bg-gray-50">
                    <h4 class="text-xs font-medium text-gray-700 mb-3">Buat Slip Honor Guru</h4>
                    <form method="POST" action="{{ route('event-honor-slips.store', $event) }}"
                          class="flex flex-wrap gap-3 items-end">
                        @csrf

                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Guru</label>
                            <select name="teacher_id" required class="border-gray-300 rounded text-sm">
                                <option value="">-- Pilih guru --</option>
                                @foreach($teachers as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Peran / Keterangan</label>
                            <input type="text" name="role" maxlength="100" placeholder="Pengawas Ujian"
                                   class="border-gray-300 rounded text-sm w-48">
                        </div>

                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Honor Pokok (Rp)</label>
                            <input type="number" name="base_honor" required min="0" value="250000"
                                   class="border-gray-300 rounded text-sm w-36">
                        </div>

                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
                            + Buat Slip
                        </button>
                    </form>
                    <p class="mt-2 text-xs text-gray-400">
                        Default Rp 250.000 sesuai honor pengawas ujian (H_UJIAN). Transport & lain-lain diisi di halaman edit slip.
                    </p>
                </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
