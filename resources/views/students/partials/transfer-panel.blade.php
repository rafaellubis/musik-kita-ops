@php
    $transferOptions = [
        'teachers' => $teachers->map(fn ($teacher) => ['id' => $teacher->id, 'name' => $teacher->name, 'instruments' => $teacher->instruments->pluck('id')])->values(),
        'rooms' => $roomsForFilter,
        'restore' => (bool) old('transfer_enrollment_id'),
        'errors' => old('transfer_enrollment_id') ? $errors->all() : [],
    ];
    $transferInput = 'mt-1 block w-full min-h-11 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-secondary focus:ring-secondary';
    $transferButton = 'min-h-11 cursor-pointer rounded-lg border border-mk-borderLight px-4 py-2 text-sm font-semibold transition-colors hover:bg-mk-cardHover focus-visible:outline focus-visible:outline-2 focus-visible:outline-secondary disabled:cursor-wait disabled:opacity-50';
@endphp
<div x-data="enrollmentTransferPanel(@js($transferOptions))" @open-transfer.window="show($event.detail)">
    <div x-show="open" x-cloak @click.self="close()" @keydown.escape.window="escape()" @keydown.tab="trap($event)"
         class="fixed inset-0 z-50 flex justify-end bg-black/40">
        <section x-show="open" x-ref="panel" role="dialog" aria-modal="true" aria-labelledby="transfer-title"
                 x-transition:enter="transform transition ease-in-out duration-300 motion-reduce:duration-0"
                 x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in-out duration-300 motion-reduce:duration-0"
                 x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                 class="flex h-full w-full max-w-[30rem] flex-col border-l border-mk-border/30 bg-mk-card text-mk-text shadow-2xl">
            <header class="flex items-start justify-between gap-3 bg-mk-sidebar p-6 text-white">
                <div class="min-w-0">
                    <h3 id="transfer-title" class="text-lg font-bold">Edit Kelas</h3>
                    <p class="mt-1 text-sm text-white/80">{{ $student->full_name }}</p>
                    <p class="mt-1 text-sm text-white/80" x-text="config.source_label"></p>
                </div>
                <button x-ref="close" type="button" @click="close()" :disabled="submitting" aria-label="Tutup panel edit kelas" class="{{ $transferButton }} text-mk-accent">&times;</button>
            </header>
            <form x-ref="form" method="POST" :action="config.store_url" @submit="submit($event)" @input="changed()" @change="changed()" class="flex min-h-0 flex-1 flex-col">
                @csrf
                <input type="hidden" name="transfer_enrollment_id" :value="config.enrollment_id">
                <input type="hidden" name="fingerprint" :value="preview?.fingerprint || ''">
                <div class="flex-1 space-y-5 overflow-y-auto p-6">
                    <div x-show="errors.length" role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        <p class="font-semibold">Pengalihan belum dapat diproses</p>
                        <ul class="mt-2 list-disc space-y-1 pl-4"><template x-for="(error, index) in errors" :key="index"><li x-text="error"></li></template></ul>
                    </div>
                    <p class="text-sm">Guru saat ini: <strong x-text="config.source_teacher_name"></strong></p>
                    <label class="block text-sm font-medium" for="transfer-teacher">Guru tujuan
                        <select id="transfer-teacher" name="teacher_id" x-model="form.teacher_id" required class="{{ $transferInput }}">
                            <option value="">Pilih guru baru</option>
                            <template x-for="teacher in teachers" :key="teacher.id"><option :value="teacher.id" x-text="teacher.name"></option></template>
                        </select>
                    </label>
                    <label class="block text-sm font-medium" for="transfer-date">Mulai berlaku
                        <input id="transfer-date" name="effective_date" type="date" min="{{ today()->toDateString() }}" x-model="form.effective_date" required class="{{ $transferInput }}">
                    </label>
                    <p class="text-sm text-mk-muted">Pengalihan berlaku seterusnya. Sesi sebelumnya, paket, dan harga SPP tetap.</p>
                    <fieldset class="space-y-4 border-t border-mk-borderLight pt-4">
                        <legend class="pr-2 text-sm font-semibold">Jadwal setelah pengalihan</legend>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label class="block text-sm font-medium" for="transfer-day">Hari
                                <select id="transfer-day" name="day_of_week" x-model="form.day_of_week" required class="{{ $transferInput }}">
                                    @foreach(\App\Models\Schedule::DAY_NAMES as $day => $label)<option value="{{ $day }}">{{ $label }}</option>@endforeach
                                </select>
                            </label>
                            <label class="block text-sm font-medium" for="transfer-time">Jam mulai
                                <input id="transfer-time" name="start_time" type="time" x-model="form.start_time" required class="{{ $transferInput }}">
                            </label>
                        </div>
                        <p class="text-sm text-mk-muted">Selesai <span x-text="endTime"></span> · <span x-text="config.duration_min"></span> menit mengikuti paket.</p>
                        <label class="block text-sm font-medium" for="transfer-room">Ruangan
                            <select id="transfer-room" name="room_id" x-model="form.room_id" class="{{ $transferInput }}">
                                <option value="">Tanpa ruangan</option>
                                <template x-for="room in rooms" :key="room.id"><option :value="room.id" x-text="room.name"></option></template>
                            </select>
                        </label>
                    </fieldset>
                    <label class="block text-sm font-medium" for="transfer-reason">Alasan pengalihan
                        <textarea id="transfer-reason" name="reason" x-model="form.reason" rows="3" maxlength="2000" required class="{{ $transferInput }}"></textarea>
                    </label>
                    <template x-if="preview">
                        <section class="space-y-3 border-t border-mk-borderLight pt-4 text-sm" aria-live="polite">
                            <h4 class="font-semibold">Pratinjau pengalihan</h4>
                            <dl class="space-y-3">
                                <div><dt class="text-mk-muted">Sebelum</dt><dd x-text="describe(preview.before)"></dd></div>
                                <div><dt class="text-mk-muted">Mulai berlaku</dt><dd x-text="preview.effective_date"></dd></div>
                                <div><dt class="text-mk-muted">Sesudah</dt><dd x-text="describe(preview.after)"></dd></div>
                            </dl>
                            <p x-text="preview.affected_sessions.length + ' sesi reguler ter-generate akan disesuaikan; ' + preview.preserved_sessions.length + ' sesi khusus dipertahankan.'"></p>
                            <p>Honor mengikuti sesi masing-masing guru. Slip belum dibayar dihitung ulang; komponen manual tetap.</p>
                            <p>Laporan bulan pengalihan menjadi tanggung jawab guru baru, termasuk yang sudah berisi penilaian. Isi penilaian dan penulis catatan sesi tetap.</p>
                            <details x-show="preview.affected_sessions.length">
                                <summary class="min-h-11 cursor-pointer py-3 font-medium">Sesi yang akan disesuaikan</summary>
                                <ul class="space-y-2"><template x-for="session in preview.affected_sessions" :key="session.id"><li x-text="session.session_date + ' · ' + session.start_time.slice(0, 5) + ' · ' + session.status"></li></template></ul>
                            </details>
                            <details x-show="preview.preserved_sessions.length">
                                <summary class="min-h-11 cursor-pointer py-3 font-medium">Sesi khusus yang tetap</summary>
                                <ul class="space-y-2"><template x-for="session in preview.preserved_sessions" :key="session.id"><li x-text="session.session_date + ' · ' + session.start_time.slice(0, 5) + ' · ' + session.status"></li></template></ul>
                            </details>
                        </section>
                    </template>
                </div>
                <footer class="flex flex-wrap justify-end gap-3 border-t border-mk-borderLight p-4">
                    <button type="button" @click="close()" :disabled="submitting" class="{{ $transferButton }}">Batal</button>
                    <button type="button" @click="inspect()" :disabled="loading || submitting" x-show="!preview" class="{{ $transferButton }} bg-mk-sidebar text-white" x-text="loading ? 'Memeriksa…' : 'Periksa pengalihan'"></button>
                    <button type="submit" x-show="preview" :disabled="submitting" class="{{ $transferButton }} bg-mk-sidebar text-white" x-text="submitting ? 'Menyimpan…' : 'Konfirmasi pengalihan'"></button>
                </footer>
            </form>
        </section>
    </div>
</div>
