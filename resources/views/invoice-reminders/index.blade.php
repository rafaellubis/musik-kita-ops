<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">Reminder WA Tagihan</h2>
                <div class="text-xs text-mk-muted mt-0.5">Kirim pengingat ke ortu murid melalui provider WhatsApp aktif</div>
            </div>
            @role('Owner')
            <a href="{{ route('whatsapp-templates.index') }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                Template Pesan →
            </a>
            @endrole
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8"
         x-data="reminderPage(@js($whatsappReady), @js($rows->map(fn ($r) => [
             'id' => $r->student->id,
             'name' => $r->student->full_name,
             'code' => $r->student->student_code,
             'parent' => $r->student->parent_name,
             'phone' => $r->phone_normalized,
             'phone_valid' => $r->phone_valid,
             'message' => $r->preview_message,
             'preview_error' => $r->preview_error,
             'invoice_count' => $r->invoice_count,
             'total' => $r->total_balance,
             'pdf_count' => $r->invoice_count,
         ])))">

        @if(session('reminder_errors'))
        <div class="mb-4 p-3 rounded-lg text-sm font-medium"
             style="background:rgba(251,146,60,0.14);color:#FDBA74;border:1px solid rgba(251,146,60,0.35)">
            <div class="font-semibold mb-1">Detail kegagalan:</div>
            <ul class="list-disc list-inside text-xs">
                @foreach(session('reminder_errors') as $code => $msg)
                    <li>{{ $code }}: {{ $msg }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @unless($whatsappReady)
        <div class="mb-4 p-3 rounded-lg text-sm bg-yellow-50 text-yellow-800 border border-yellow-200">
            Konfigurasi provider WhatsApp belum lengkap. Periksa Pengaturan Scheduler & Reminder.
        </div>
        @endunless

        {{-- Filter --}}
        <form method="GET" class="mb-4 bg-mk-card shadow-sm sm:rounded-lg p-4 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-mk-dim mb-1">Cari murid / kode / ortu</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="w-full rounded border-gray-300 text-sm"
                       placeholder="Nama, kode, ortu...">
            </div>
            <label class="flex items-center gap-2 text-sm text-mk-muted">
                <input type="checkbox" name="overdue_only" value="1"
                       @checked($filters['overdue_only'] ?? false)
                       class="rounded border-gray-300">
                Hanya overdue
            </label>
            <label class="flex items-center gap-2 text-sm text-mk-muted">
                <input type="checkbox" name="never_reminded_this_month" value="1"
                       @checked($filters['never_reminded_this_month'] ?? false)
                       class="rounded border-gray-300">
                Belum di-reminder bulan ini
            </label>
            <button type="submit"
                    class="px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary">
                Filter
            </button>
        </form>

        <div class="bg-mk-card shadow-sm sm:rounded-lg overflow-hidden">
            @if($rows->isEmpty())
                <div class="p-8 text-center text-mk-dim">
                    Tidak ada murid dengan tagihan belum lunas.
                </div>
            @else
                <div class="px-4 py-2 border-b border-mk-border flex flex-wrap gap-3 items-center text-sm">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" @change="toggleAll($event.target.checked)"
                               class="rounded border-gray-300">
                        <span class="text-mk-muted">Pilih semua (yang bisa dikirim)</span>
                    </label>
                    <span class="text-mk-dim text-xs" x-text="selectedCount + ' murid dipilih'"></span>
                    <button type="button"
                            @click="openModal($event.currentTarget)"
                            :disabled="selectedCount === 0 || !whatsappReady"
                            class="ml-auto px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary disabled:opacity-40">
                        Preview & Kirim
                    </button>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-mk-surface">
                        <tr class="border-b text-left text-xs text-mk-dim uppercase">
                            <th class="px-4 py-3 w-10"></th>
                            <th class="px-4 py-3">Murid</th>
                            <th class="px-4 py-3">Ortu / WA</th>
                            <th class="px-4 py-3 text-center">Invoice</th>
                            <th class="px-4 py-3 text-right">Total Tagihan</th>
                            <th class="px-4 py-3">Tempo</th>
                            <th class="px-4 py-3">Reminder Terakhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mk-border">
                        @foreach($rows as $row)
                            @php
                                $sid = $row->student->id;
                                $overdue = $row->oldest_due_date && $row->oldest_due_date->lt(now()->startOfDay());
                            @endphp
                            <tr class="hover:bg-mk-surface {{ $row->phone_valid ? '' : 'opacity-60' }}">
                                <td class="px-4 py-2">
                                    @if($row->phone_valid && !$row->preview_error)
                                        <input type="checkbox"
                                               value="{{ $sid }}"
                                               x-model.number="selected"
                                               class="rounded border-gray-300 row-check">
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <div class="font-medium">{{ $row->student->full_name }}</div>
                                    <div class="text-xs font-mono text-mk-dim">{{ $row->student->student_code }}</div>
                                    @if($row->preview_error)
                                        <p class="mt-1 text-xs text-red-700">{{ $row->preview_error }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <div>{{ $row->student->parent_name ?? '—' }}</div>
                                    @if($row->phone_valid)
                                        <div class="text-xs text-mk-dim font-mono">{{ $row->phone_normalized }}</div>
                                    @else
                                        <span class="text-xs px-2 py-0.5 rounded bg-red-100 text-red-700">No HP ortu</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center">{{ $row->invoice_count }}</td>
                                <td class="px-4 py-2 text-right font-medium text-orange-700">
                                    Rp {{ number_format($row->total_balance, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-2 text-xs {{ $overdue ? 'text-red-600 font-semibold' : 'text-mk-dim' }}">
                                    {{ $row->oldest_due_date?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs text-mk-dim">
                                    {{ $row->last_reminder_at?->format('d M Y H:i') ?? 'Belum pernah' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Modal konfirmasi --}}
        <div x-show="showModal" x-cloak
             @click.self="closeModal()" @keydown.escape.window="closeFromEscape()"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
            <div x-ref="modal" role="dialog" aria-modal="true" aria-labelledby="reminder-preview-title"
                 @keydown.tab="trapFocus($event)"
                 class="bg-white border border-mk-accent rounded-xl shadow-xl max-w-2xl w-full max-h-[calc(100dvh-2rem)] flex flex-col overflow-hidden">
                <div class="shrink-0 flex items-center justify-between gap-4 px-4 py-4 sm:px-6 bg-mk-sidebar text-white">
                    <h3 id="reminder-preview-title" class="text-lg font-semibold">Preview &amp; Kirim</h3>
                    <span class="shrink-0 text-sm text-white" x-text="selectedCount + ' penerima'"></span>
                </div>
                <div tabindex="0" role="region" aria-label="Pesan WhatsApp lengkap"
                     class="min-h-0 overflow-y-auto p-4 sm:p-6 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-mk-accent">
                     <ul class="space-y-4">
                        <template x-for="item in selectedDetails" :key="item.id">
                            <li class="overflow-hidden rounded-lg border border-mk-accent">
                                <div class="border-b border-mk-accent bg-mk-cardHover px-4 py-3">
                                    <h4 class="font-semibold text-mk-text break-words" x-text="item.name"></h4>
                                    <p class="mt-1 text-sm text-mk-muted">
                                        <span x-text="item.code"></span>
                                        <span aria-hidden="true" class="mx-1">&middot;</span>
                                        <span x-text="item.phone"></span>
                                    </p>
                                </div>
                                <p class="px-4 py-4 text-sm leading-relaxed text-mk-text whitespace-pre-wrap break-words" x-text="item.message"></p>
                            </li>
                        </template>
                    </ul>
                </div>
                <div class="shrink-0 px-4 py-4 sm:px-6 border-t border-mk-accent bg-mk-bg">
                <form method="POST" action="{{ route('invoice-reminders.send') }}">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="student_ids[]" :value="id">
                    </template>
                    {{-- ponytail: template dipilih otomatis oleh service (INVOICE_REMINDER jika no ortu ada, INVOICE_REMINDER_STUDENT jika tidak) --}}
                    <div class="flex justify-end gap-2">
                        <button x-ref="cancel" type="button" @click="closeModal()"
                                class="min-h-11 px-4 py-2 border border-mk-muted bg-white hover:bg-mk-cardHover rounded-lg text-sm text-mk-text cursor-pointer transition-colors focus-visible:ring-2 focus-visible:ring-mk-accent">
                            Batal
                        </button>
                        <button type="submit" :disabled="selectedCount === 0 || !whatsappReady"
                                class="min-h-11 px-4 py-2 rounded-lg text-sm font-bold btn-mk-primary cursor-pointer disabled:opacity-40 focus-visible:ring-2 focus-visible:ring-mk-accent">
                            Kirim
                        </button>
                    </div>
                </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function reminderPage(whatsappReady, rows) {
            const validIds = rows.filter(r => r.phone_valid && !r.preview_error).map(r => r.id);
            return {
                whatsappReady,
                rows,
                selected: [],
                showModal: false,
                trigger: null,
                openModal(trigger) {
                    this.trigger = trigger;
                    this.showModal = true;
                    this.$nextTick(() => this.$refs.cancel.focus());
                },
                closeModal() {
                    this.showModal = false;
                    this.trigger?.focus();
                },
                closeFromEscape() {
                    if (!this.showModal || document.querySelector('dialog[open]')) return;
                    this.closeModal();
                },
                trapFocus(event) {
                    const controls = [...this.$refs.modal.querySelectorAll('button:not(:disabled), [tabindex="0"]')];
                    const first = controls[0];
                    const last = controls[controls.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                },
                get selectedCount() { return this.selected.length; },
                get selectedDetails() {
                    return this.rows.filter(r => this.selected.includes(r.id));
                },
                get totalPdfs() {
                    return this.selectedDetails.reduce((s, r) => s + r.pdf_count, 0);
                },
                toggleAll(checked) {
                    this.selected = checked ? [...validIds] : [];
                },
            };
        }
    </script>
</x-app-layout>
