<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Template Pesan WhatsApp</h2>
                <div class="text-xs text-mk-muted mt-0.5">Reminder tagihan, jadwal, dan laporan sesi WhatsApp</div>
            </div>
            @role('Owner')
            <a href="{{ route('whatsapp-templates.create') }}"
               class="px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary">
                + Tambah Template
            </a>
            @endrole
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8">
        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            @if($templates->isEmpty())
                <div class="p-8 text-center text-mk-dim">Belum ada template.</div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-mk-surface">
                        <tr class="border-b text-left text-xs text-mk-dim uppercase">
                            <th class="px-4 py-3">Urut</th>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            @role('Owner')
                            <th class="px-4 py-3 text-right">Aksi</th>
                            @endrole
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mk-border">
                        @foreach($templates as $t)
                            <tr class="hover:bg-mk-surface {{ $t->is_active ? '' : 'opacity-50' }}">
                                <td class="px-4 py-2 text-mk-dim">{{ $t->sort_order }}</td>
                                <td class="px-4 py-2 font-mono font-bold">{{ $t->code }}</td>
                                <td class="px-4 py-2">{{ $t->name }}</td>
                                <td class="px-4 py-2 text-center">
                                    @if($t->is_active)
                                        <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Aktif</span>
                                    @else
                                        <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-600">Nonaktif</span>
                                    @endif
                                </td>
                                @role('Owner')
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <a href="{{ route('whatsapp-templates.edit', $t) }}"
                                       class="text-blue-600 hover:underline text-xs">Edit</a>
                                    @if(! in_array($t->code, [
                                        \App\Models\WhatsappMessageTemplate::CODE_INVOICE_REMINDER,
                                        \App\Models\WhatsappMessageTemplate::CODE_SCHEDULE_REMINDER,
                                        \App\Models\WhatsappMessageTemplate::CODE_SESSION_REPORT,
                                        \App\Models\WhatsappMessageTemplate::CODE_SESSION_REPORT_STUDENT,
                                    ], true))
                                    <form action="{{ route('whatsapp-templates.destroy', $t) }}" method="POST" class="inline ml-2"
                                          onsubmit="return confirm('Hapus template {{ $t->code }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline text-xs">Hapus</button>
                                    </form>
                                    @endif
                                </td>
                                @endrole
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
