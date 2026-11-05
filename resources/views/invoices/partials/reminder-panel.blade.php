@hasanyrole('Owner|Admin')
@php
    $reminderButton = 'min-h-11 rounded-lg border border-mk-border px-3 py-2 text-sm font-semibold text-mk-text transition-colors hover:bg-mk-cardHover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent disabled:opacity-50';
    $reminderTab = 'min-h-11 border-b-2 px-3 py-3 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-mk-accent disabled:opacity-50';
@endphp
<div x-show="open" x-cloak @click.self="close()" @keydown.escape.window="escape()"
     class="fixed inset-0 z-50 flex justify-end bg-black/40">
    <section x-show="open" x-ref="panel" role="dialog" aria-modal="true" aria-labelledby="invoice-reminder-title"
             @keydown.tab="trap($event)"
             x-transition:enter="transform transition ease-in-out duration-300 motion-reduce:duration-0"
             x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transform transition ease-in-out duration-300 motion-reduce:duration-0"
             x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             class="flex h-full w-full max-w-[30rem] flex-col border-l border-mk-border bg-mk-card text-xs text-mk-text shadow-2xl [&_*]:text-xs">
        <header class="flex items-start justify-between gap-2 bg-mk-sidebar px-4 py-3 text-white">
            <div class="min-w-0">
                <h3 id="invoice-reminder-title" class="flex items-center gap-3 text-xl font-bold text-white">
                    <svg class="h-6 w-6 shrink-0 text-emerald-400" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-11.7 7.1L4 20l1.4-4.3A8 8 0 1 1 20 11.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9 8c0 3.5 2 5.5 5.5 6l1-1.5-2-1-1 1c-1.2-.5-2-1.3-2.5-2.5l1-1-1-2L9 8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
                    Reminder WA
                </h3>
                <p class="mt-1 break-words font-semibold text-white" x-text="config.name"></p>
                <p class="mt-1 inline-flex rounded border border-mk-accent/30 bg-black/20 px-2 py-0.5 text-xs font-medium text-mk-accent" x-text="config.number"></p>
            </div>
            <button x-ref="close" type="button" @click="close()" :disabled="submitting" aria-label="Tutup panel reminder"
                    class="min-h-11 min-w-11 rounded-lg text-2xl text-mk-accent hover:text-white focus-visible:ring-2 focus-visible:ring-mk-accent disabled:opacity-50">&times;</button>
        </header>
        <div class="flex shrink-0 gap-4 border-b border-mk-border px-4" role="tablist" aria-label="Reminder invoice">
            <button id="reminder-new-tab" type="button" role="tab" aria-controls="reminder-new-content"
                    :aria-selected="activeTab === 'new'" :tabindex="activeTab === 'new' ? 0 : -1"
                    @click="setTab('new')" @keydown.arrow-right.prevent="setTab('history'); $refs.historyTab.focus()"
                    x-ref="newTab" :disabled="submitting" class="{{ $reminderTab }}" :class="activeTab === 'new' ? 'border-emerald-600 text-mk-text' : 'border-transparent text-mk-muted'"><span class="inline-flex items-center gap-2 rounded-md px-2 py-1" :class="activeTab === 'new' && 'bg-emerald-50 text-emerald-800'"><span x-show="activeTab === 'new'" class="h-1.5 w-1.5 rounded-full bg-emerald-600" aria-hidden="true"></span>Pesan baru</span></button>
            <button id="reminder-history-tab" type="button" role="tab" aria-controls="reminder-history-content"
                    :aria-selected="activeTab === 'history'" :tabindex="activeTab === 'history' ? 0 : -1"
                    @click="setTab('history')" @keydown.arrow-left.prevent="setTab('new'); $refs.newTab.focus()"
                    x-ref="historyTab" :disabled="submitting" class="{{ $reminderTab }}" :class="activeTab === 'history' ? 'border-mk-accent text-mk-text' : 'border-transparent text-mk-muted'">Riwayat</button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto p-3 text-xs" :aria-busy="loading || submitting">
            <div role="status" aria-live="polite">
                <p x-show="loading" class="mb-3 text-sm text-mk-muted">Memuat data reminder…</p>
            </div>
            <div x-show="errors.length" role="alert" class="mb-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                <template x-for="(error, index) in errors" :key="index"><p x-text="error"></p></template>
                <button type="button" x-show="!submitting" @click="activeTab === 'history' ? loadHistory(historyPage) : resetMessage()" class="{{ $reminderButton }} mt-2">Muat ulang</button>
            </div>
            <div id="reminder-new-content" role="tabpanel" aria-labelledby="reminder-new-tab" x-show="activeTab === 'new'">
                <template x-if="preview">
                    <div class="space-y-2">
                        <div class="rounded-xl border border-mk-border bg-mk-surface px-3 py-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-xs font-semibold text-mk-muted">Penerima</h4>
                                <span class="break-words font-bold" x-text="preview.recipient?.name || 'Nomor tujuan tidak tersedia'"></span>
                                <span x-show="preview.recipient" class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900" x-text="preview.recipient?.kind === 'student' ? 'Murid' : 'Orang Tua'"></span>
                            </div>
                            <div class="mt-1 flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m8 3 3 5-2 2a14 14 0 0 0 5 5l2-2 5 3-1 4c-9 2-18-7-16-16l4-1Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                                <span class="break-all font-medium tabular-nums" x-text="preview.recipient?.phone || '—'"></span>
                            </div>
                            <p x-show="preview.recipient?.kind === 'student'" class="mt-2 text-xs text-mk-muted">Nomor orang tua tidak tersedia atau tidak valid.</p>
                            <a x-show="!preview.recipient && preview.student.edit_url" :href="preview.student.edit_url" class="{{ $reminderButton }} mt-2 inline-flex items-center">Perbaiki data murid</a>
                        </div>
                        <div x-show="preview.blockers.length" class="rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800">
                            <template x-for="(blocker, index) in preview.blockers" :key="index"><p x-text="blocker"></p></template>
                        </div>
                        <div x-show="preview.other_invoices.length || selectedIds.length > 1 || preview.excluded_invoices.length">
                            <label x-show="preview.other_invoices.length || selectedIds.length > 1" class="mt-1 flex min-h-11 cursor-pointer items-center gap-2 text-sm">
                                <input type="checkbox" :checked="includeOthers" :disabled="loading || submitting || !!result"
                                       @change="toggleOthers($event.target.checked).then(() => $event.target.checked = includeOthers)" class="rounded border-gray-300">
                                Sertakan tagihan belum lunas lainnya
                            </label>
                            <template x-for="item in preview.selected_invoices.filter(item => item.id !== config.id)" :key="item.id">
                                <div class="flex items-center justify-between gap-2 border-b border-mk-border py-1">
                                    <span class="min-w-0 break-words" x-text="item.invoice_number"></span>
                                    <button type="button" :disabled="loading || submitting || !!result" @click="changeSelection(selectedIds.filter(id => id !== item.id))" class="{{ $reminderButton }}">Lepas</button>
                                </div>
                            </template>
                            <div x-show="includeOthers" class="mt-1 space-y-2">
                                <template x-for="item in preview.other_invoices" :key="item.id">
                                    <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border border-mk-border p-3 text-sm">
                                        <input type="checkbox" :checked="selectedIds.includes(item.id)" :disabled="loading || submitting || !!result"
                                               @change="changeSelection($event.target.checked ? [...selectedIds, item.id] : selectedIds.filter(id => id !== item.id)).then(() => $event.target.checked = selectedIds.includes(item.id))" class="mt-1 rounded border-gray-300">
                                        <span><span class="block font-semibold" x-text="item.invoice_number"></span><span class="mt-1 block text-mk-muted" x-text="item.caption + ' · ' + money(item.balance)"></span></span>
                                    </label>
                                </template>
                            </div>
                            <template x-for="item in preview.excluded_invoices" :key="item.id"><p class="mt-2 text-sm text-mk-muted" x-text="'Invoice #' + item.id + ': ' + item.reason"></p></template>
                        </div>
                        <div x-show="preview.repeat_warnings.length" class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <div class="min-w-0"><p class="font-semibold">Sudah diingatkan hari ini</p>
                                <template x-for="warning in preview.repeat_warnings" :key="warning.invoice_id"><p class="mt-1" x-text="date(warning.sent_at) + ' · ' + (warning.sender_name || 'Pengguna lain')"></p></template>
                            </div>
                        </div>
                        <p x-show="expired && !result" role="alert" class="rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800">Preview kedaluwarsa. Gunakan tombol Template, lalu tinjau pesan baru sebelum mengirim.</p>
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <label for="invoice-reminder-message" class="text-sm font-bold">Pesan WA</label>
                                    <p id="invoice-reminder-message-help" class="mt-1 text-xs text-mk-muted">Periksa nominal dan isi pesan sebelum mengirim.</p>
                                </div>
                                <button type="button" @click="resetMessage()" :disabled="loading || submitting" class="{{ $reminderButton }} inline-flex items-center gap-2">
                                    <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 4v5h5M20 20v-5h-5M4.6 9a8 8 0 0 1 14-3M19.4 15a8 8 0 0 1-14 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Template
                                </button>
                            </div>
                            <textarea id="invoice-reminder-message" x-model="messageBody" :disabled="loading || submitting" :readonly="!!result" rows="1" maxlength="5000" aria-describedby="invoice-reminder-message-help"
                                      x-effect="messageBody; activeTab; open; $nextTick(() => resizeMessage($el))"
                                      @resize.window.debounce.100ms="$nextTick(() => resizeMessage($el))"
                                      class="block w-full resize-none overflow-hidden rounded-xl border-mk-border bg-mk-surface p-3 text-xs leading-relaxed text-mk-text focus:border-mk-accent focus:ring-mk-accent"></textarea>
                        </div>
                        <div x-show="previousEditedMessage" class="border-t border-mk-border pt-2">
                            <label for="invoice-reminder-old-message" class="text-sm font-semibold">Edit sebelumnya — dapat disalin</label>
                            <textarea id="invoice-reminder-old-message" readonly :value="previousEditedMessage" rows="4" class="mt-2 w-full rounded-lg border-gray-300 text-sm"></textarea>
                        </div>
                    </div>
                </template>
                <div x-show="result" role="status" aria-live="polite" class="mt-3 rounded-lg border border-mk-border bg-mk-accentDim p-3 text-sm">
                    <p class="font-semibold" x-text="result?.message"></p>
                    <p x-show="result?.sent_at" class="mt-1 text-mk-muted" x-text="date(result?.sent_at)"></p>
                    <p x-show="result?.log_id" class="mt-2 text-mk-muted" x-text="'Riwayat #' + result?.log_id"></p>
                    <button type="button" @click="setTab('history')" class="{{ $reminderButton }} mt-2">Lihat riwayat</button>
                </div>
            </div>
            <div id="reminder-history-content" role="tabpanel" aria-labelledby="reminder-history-tab" x-show="activeTab === 'history'">
                <div x-show="history?.data.length" class="mb-4 flex items-center justify-between gap-3 text-xs text-mk-muted">
                    <span>Terbaru lebih dahulu</span>
                    <span x-text="(history?.total || 0) + ' pengiriman'"></span>
                </div>
                <p x-show="history && !history.data.length && !loading" class="py-3 text-sm text-mk-muted">Belum ada pengiriman reminder untuk invoice ini.</p>
                <template x-for="(log, index) in (history?.data || [])" :key="log.id">
                    <div>
                        <h4 x-show="index === 0 || log.sent_at.slice(0, 10) !== history.data[index - 1].sent_at.slice(0, 10)"
                            class="mb-3 flex items-center gap-3 text-xs font-semibold" :class="index > 0 && 'mt-4'">
                            <span x-text="date(log.sent_at.slice(0, 10))"></span>
                            <span class="h-px flex-1 bg-mk-border" aria-hidden="true"></span>
                        </h4>
                        <article class="border-b border-mk-border py-3">
                            <div class="flex items-center justify-between gap-3">
                                <time :datetime="log.sent_at" class="font-semibold tabular-nums" x-text="log.sent_at.slice(11, 16).replace(':', '.')"></time>
                                <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                                      :class="log.status === 'SUCCESS' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : (log.status === 'PARTIAL' ? 'border-yellow-200 bg-yellow-50 text-yellow-800' : 'border-red-200 bg-red-50 text-red-700')">
                                    <svg x-show="log.status === 'SUCCESS'" class="h-3 w-3" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3 8 3 3 7-7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    <span x-text="log.status === 'SUCCESS' ? 'Terkirim' : statusLabel(log.status)"></span>
                                </span>
                            </div>
                            <p class="mt-2">Oleh <span class="font-semibold" x-text="log.sender_name"></span></p>
                            <p class="mt-1 text-mk-muted" x-text="'Ke ' + log.phone"></p>
                            <p class="mt-1 break-words text-xs text-mk-muted" x-text="log.invoices.map(i => i.invoice_number).join(' · ')"></p>
                            <p x-show="log.error_message" class="mt-2 text-sm text-red-700" x-text="log.error_message"></p>
                            <details name="invoice-reminder-history" class="group mt-1">
                                <summary tabindex="0" class="flex min-h-11 w-fit cursor-pointer list-none items-center gap-2 rounded text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mk-accent">
                                    <span class="group-open:hidden">Lihat pesan</span>
                                    <span class="hidden group-open:inline">Sembunyikan pesan</span>
                                    <svg class="h-3 w-3 group-open:rotate-180" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="m3 4 3 3 3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </summary>
                                <p class="max-h-64 overflow-y-auto whitespace-pre-wrap break-words rounded-lg bg-mk-surface p-3 text-sm leading-relaxed" tabindex="0" aria-label="Isi pesan reminder tersimpan" x-text="log.message_body"></p>
                                <p class="mt-2 text-xs text-mk-muted" x-text="'Provider: ' + log.provider"></p>
                            </details>
                            <div x-show="log.can_prepare_resend" class="mt-2 flex justify-end">
                                <button type="button" @click="prepareResend(log.id)" :disabled="loading" class="{{ $reminderButton }}">Siapkan kirim ulang</button>
                            </div>
                        </article>
                    </div>
                </template>
                <div x-show="history && history.last_page > 1" class="mt-3 flex items-center justify-between gap-2 text-sm">
                    <button type="button" @click="loadHistory(historyPage - 1)" :disabled="loading || historyPage <= 1" class="{{ $reminderButton }}">Sebelumnya</button>
                    <span x-text="historyPage + ' / ' + (history?.last_page || 1)"></span>
                    <button type="button" @click="loadHistory(historyPage + 1)" :disabled="loading || historyPage >= (history?.last_page || 1)" class="{{ $reminderButton }}">Berikutnya</button>
                </div>
            </div>
        </div>
        <footer x-show="activeTab === 'new'" class="flex shrink-0 items-center justify-end gap-3 border-t border-mk-border bg-mk-card px-4 py-4 sm:px-6">
            <button type="button" @click="close()" :disabled="submitting" class="{{ $reminderButton }} min-w-24">Batal</button>
            <button type="button" @click="send()" :disabled="!canSend" class="min-h-11 min-w-36 rounded-lg px-5 py-3 text-sm font-bold btn-mk-primary focus-visible:ring-2 focus-visible:ring-mk-accent disabled:cursor-not-allowed disabled:opacity-50" x-text="submitting ? 'Mengirim…' : 'Kirim WA'"></button>
        </footer>
        <footer x-show="activeTab === 'history' && history?.data.length" class="shrink-0 border-t border-mk-border bg-mk-card px-4 py-3 text-xs leading-relaxed text-mk-muted">
            Terkirim berarti diterima provider, bukan bukti pesan diterima atau dibaca.
        </footer>
    </section>
</div>
@endhasanyrole
