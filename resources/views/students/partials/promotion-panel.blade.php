@php
    $promotionPanelOptions = [
        'teachers' => $teachers->map(fn ($teacher) => ['id' => $teacher->id, 'name' => $teacher->name, 'instruments' => $teacher->instruments->pluck('id')])->values(),
        'rooms' => $roomsForFilter,
        'restore' => (bool) old('promotion_enrollment_id'),
        'errors' => old('promotion_enrollment_id') ? $errors->all() : [],
    ];
    $promotionInput = 'mt-1 block w-full min-h-11 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-secondary focus:ring-secondary';
    $promotionButton = 'min-h-11 rounded-lg border border-mk-borderLight px-4 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-secondary disabled:opacity-50';
@endphp
<div x-data="enrollmentPromotionPanel(@js($promotionPanelOptions))" @open-promotion.window="show($event.detail)">
    <div x-show="open" x-cloak @click.self="close()" @keydown.escape.window="escape()" @keydown.tab="trap($event)"
         class="fixed inset-0 z-50 flex justify-end bg-black/40">
        <section x-show="open" x-ref="panel" role="dialog" aria-modal="true" aria-labelledby="promotion-title"
                 x-transition:enter="transform transition ease-in-out duration-300 motion-reduce:duration-0"
                 x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in-out duration-300 motion-reduce:duration-0"
                 x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                 class="flex h-full w-full max-w-[30rem] flex-col border-l border-mk-border/30 bg-mk-card text-mk-text shadow-2xl">
            <header class="flex items-start justify-between gap-3 bg-mk-sidebar p-6 text-white">
                <div class="min-w-0">
                    <h3 id="promotion-title" class="text-lg font-bold">Naik Level Kelas</h3>
                    <p class="mt-1 text-sm text-white/80">{{ $student->full_name }}</p>
                    <p class="mt-1 text-sm text-white/80" x-text="config.source_label"></p>
                </div>
                <button x-ref="close" type="button" @click="close()" :disabled="submitting" aria-label="Tutup panel kenaikan level" class="{{ $promotionButton }} text-mk-accent">&times;</button>
            </header>
            <form x-ref="form" method="POST" :action="config.store_url" @submit="submit($event)" @input="changed()" @change="changed()" class="flex min-h-0 flex-1 flex-col">
                @csrf
                <input type="hidden" name="promotion_enrollment_id" :value="config.enrollment_id">
                <input type="hidden" name="effective_date" :value="form.effective_date">
                <div class="flex-1 space-y-5 overflow-y-auto p-6">
                    <div x-show="errors.length" role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        <p class="font-semibold">Kenaikan belum dapat diproses</p>
                        <ul class="mt-2 list-disc space-y-1 pl-4"><template x-for="(error, index) in errors" :key="index"><li x-text="error"></li></template></ul>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4"><dt>Mulai berlaku</dt><dd class="font-semibold" x-text="form.effective_date"></dd></div>
                    </dl>
                    <label class="block text-sm font-medium" for="promotion-package">Level tujuan
                        <select id="promotion-package" name="package_id" x-model="form.package_id" required class="{{ $promotionInput }}">
                            <option value="">Pilih level tujuan</option>
                            <template x-for="target in config.targets || []" :key="target.id"><option :value="target.id" x-text="target.label"></option></template>
                        </select>
                    </label>
                    <label class="block text-sm font-medium" for="promotion-teacher">Guru
                        <select id="promotion-teacher" name="teacher_id" x-model="form.teacher_id" required class="{{ $promotionInput }}">
                            <option value="">Pilih guru</option>
                            <template x-for="teacher in teachers" :key="teacher.id"><option :value="teacher.id" x-text="teacher.name"></option></template>
                        </select>
                    </label>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium" for="promotion-day">Hari
                            <select id="promotion-day" name="day_of_week" x-model="form.day_of_week" required class="{{ $promotionInput }}">
                                <option value="">Pilih hari</option>
                                @foreach(['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $day => $dayLabel)<option value="{{ $day }}">{{ $dayLabel }}</option>@endforeach
                            </select>
                        </label>
                        <label class="block text-sm font-medium" for="promotion-time">Jam mulai
                            <input id="promotion-time" name="start_time" type="time" x-model="form.start_time" required class="{{ $promotionInput }}">
                        </label>
                    </div>
                    <label class="block text-sm font-medium" for="promotion-room">Ruangan
                        <select id="promotion-room" name="room_id" x-model="form.room_id" required class="{{ $promotionInput }}">
                            <option value="">Pilih ruangan</option>
                            <template x-for="room in rooms" :key="room.id"><option :value="room.id" x-text="room.name"></option></template>
                        </select>
                    </label>
                    <label class="block text-sm font-medium" for="promotion-evaluation">Evaluasi guru
                        <textarea id="promotion-evaluation" name="evaluation_note" x-model="form.evaluation_note" rows="3" maxlength="2000" required class="{{ $promotionInput }}"></textarea>
                    </label>
                    <template x-if="preview">
                        <div class="space-y-4 border-t border-mk-borderLight pt-4" aria-live="polite">
                            <h4 class="font-semibold">Dampak kenaikan level</h4>
                            <dl class="space-y-2 text-sm">
                                <div><dt class="text-mk-muted">SPP per bulan</dt><dd x-text="money(preview.old_price) + ' → ' + money(preview.new_price)"></dd></div>
                                <div><dt class="text-mk-muted">Honor per sesi</dt><dd x-text="money(preview.old_honor) + ' → ' + money(preview.new_honor)"></dd></div>
                            </dl>
                            <p class="text-sm" x-text="preview.sessions_to_delete.length + ' sesi lama dihapus dan jadwal baru dibuat untuk ' + preview.months.join(', ') + '.'"></p>
                            <p class="text-sm" x-text="preview.preserved_sessions.length + ' sesi hak periode lama dipertahankan.'"></p>
                            <ul class="space-y-2 text-sm"><template x-for="invoice in preview.invoice_changes" :key="invoice.original_invoice_id"><li x-text="invoice.invoice_number + ': ' + (invoice.mode === 'UPDATE' ? 'SPP diperbarui menjadi ' : 'tagihan selisih ') + money(invoice.amount)"></li></template></ul>
                            <p x-show="!preview.invoice_changes.length" class="text-sm text-mk-muted">Belum ada invoice SPP mendatang. Tagihan mengikuti level baru saat generate SPP.</p>
                            <details class="text-sm" x-show="preview.sessions_to_delete.length">
                                <summary class="min-h-11 cursor-pointer py-3 font-medium">Lihat sesi yang akan dihapus</summary>
                                <ul class="space-y-1"><template x-for="session in preview.sessions_to_delete" :key="session.id"><li x-text="session.session_date + ' · ' + session.start_time + ' · ' + session.status"></li></template></ul>
                            </details>
                        </div>
                    </template>
                </div>
                <footer class="flex flex-wrap justify-end gap-3 border-t border-mk-borderLight p-4">
                    <button type="button" @click="close()" :disabled="submitting" class="{{ $promotionButton }}">Batal</button>
                    <button type="button" @click="inspect()" :disabled="loading || submitting" x-show="!preview" class="{{ $promotionButton }} bg-mk-sidebar text-white" x-text="loading ? 'Memeriksa…' : 'Periksa perubahan'"></button>
                    <button type="submit" x-show="preview" :disabled="submitting" class="{{ $promotionButton }} bg-mk-sidebar text-white" x-text="submitting ? 'Menyimpan…' : 'Konfirmasi naik level'"></button>
                </footer>
            </form>
        </section>
    </div>
</div>
