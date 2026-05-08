<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">Audit Log</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">

            {{-- Filter --}}
            <div class="bg-white shadow-sm rounded-lg p-4 mb-4">
                <form method="GET" action="{{ route('audit-logs.index') }}"
                      class="flex flex-wrap gap-3 items-end">

                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Aksi</label>
                        <select name="action" class="border-gray-300 rounded text-sm">
                            <option value="">Semua Aksi</option>
                            @foreach($actions as $code => $label)
                                <option value="{{ $code }}" {{ request('action') === $code ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Entitas</label>
                        <select name="entity_type" class="border-gray-300 rounded text-sm">
                            <option value="">Semua Entitas</option>
                            @foreach($entityTypes as $et)
                                <option value="{{ $et }}" {{ request('entity_type') === $et ? 'selected' : '' }}>
                                    {{ $et }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Dari Tanggal</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}"
                               class="border-gray-300 rounded text-sm">
                    </div>

                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Sampai Tanggal</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}"
                               class="border-gray-300 rounded text-sm">
                    </div>

                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Cari</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Label / catatan / user..."
                               class="border-gray-300 rounded text-sm w-48">
                    </div>

                    <button type="submit"
                            class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded text-sm">
                        Filter
                    </button>
                    @if(request()->hasAny(['action','entity_type','date_from','date_to','search']))
                    <a href="{{ route('audit-logs.index') }}"
                       class="px-4 py-2 text-gray-600 hover:text-gray-800 text-sm">Reset</a>
                    @endif
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b bg-gray-50 flex justify-between items-center">
                    <span class="text-sm text-gray-600">{{ $logs->total() }} entri ditemukan</span>
                </div>

                <table class="w-full text-xs">
                    <thead class="border-b text-gray-500 uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left">Waktu</th>
                            <th class="px-3 py-2 text-left">User</th>
                            <th class="px-3 py-2 text-center">Aksi</th>
                            <th class="px-3 py-2 text-left">Entitas</th>
                            <th class="px-3 py-2 text-left">Label</th>
                            <th class="px-3 py-2 text-left">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap text-gray-500">
                                    {{ $log->created_at->format('d/m H:i') }}
                                    <div class="text-gray-300">{{ $log->created_at->format('Y') }}</div>
                                </td>
                                <td class="px-3 py-2 font-medium">
                                    {{ $log->user_name ?? '(sistem)' }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @php
                                        $actionColors = [
                                            'CREATE'    => 'bg-green-100 text-green-700',
                                            'UPDATE'    => 'bg-blue-100 text-blue-700',
                                            'DELETE'    => 'bg-red-100 text-red-700',
                                            'LOGIN'     => 'bg-gray-100 text-gray-600',
                                            'LOGOUT'    => 'bg-gray-100 text-gray-600',
                                            'PRINT'     => 'bg-purple-100 text-purple-700',
                                            'VOID'      => 'bg-orange-100 text-orange-700',
                                            'LIFECYCLE' => 'bg-yellow-100 text-yellow-700',
                                        ];
                                    @endphp
                                    <span class="px-2 py-0.5 rounded {{ $actionColors[$log->action] ?? 'bg-gray-100' }}">
                                        {{ $log->action_label }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-500">
                                    {{ $log->entity_type ?? '—' }}
                                    @if($log->entity_id)
                                        <span class="text-gray-300">#{{ $log->entity_id }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 font-medium">
                                    {{ Str::limit($log->entity_label, 40) ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-gray-500">
                                    {{ Str::limit($log->notes, 50) ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                                    Belum ada entri audit log.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="px-4 py-3 border-t">
                    {{ $logs->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
