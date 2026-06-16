<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="text-xs text-mk-dim mb-0.5 font-medium">Pengaturan Sistem</div>
            <h2 class="font-semibold text-xl text-mk-text">Pengaturan Scheduler & Reminder</h2>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8 max-w-5xl mx-auto">
        <form action="{{ route('scheduler.update') }}" method="POST" class="space-y-6" x-data="{
            sessionsGenerateEnabled: {{ $settings['scheduler_sessions_generate_enabled'] ? 'true' : 'false' }},
            invoicesGenerateEnabled: {{ $settings['scheduler_invoices_generate_enabled'] ? 'true' : 'false' }},
            honorCalculateEnabled: {{ $settings['scheduler_honor_calculate_enabled'] ? 'true' : 'false' }},
            finesApplyEnabled: {{ $settings['scheduler_fines_apply_enabled'] ? 'true' : 'false' }},
            overdueCheckEnabled: {{ $settings['scheduler_overdue_check_enabled'] ? 'true' : 'false' }},
            reminderEnabled: {{ $settings['schedule_reminder_enabled'] ? 'true' : 'false' }},
            reminderMode: '{{ $settings['schedule_reminder_mode'] }}'
        }">
            @csrf
            @method('PATCH')

            {{-- Menampilkan info petunjuk --}}
            <div class="bg-mk-surface border border-mk-border rounded-2xl p-4 flex gap-3 text-xs text-mk-text shadow-sm">
                <span class="text-lg shrink-0">⚙️</span>
                <div>
                    <h4 class="font-bold text-secondary mb-1">Informasi Sinkronisasi Jadwal</h4>
                    <p class="text-mk-muted leading-relaxed">
                        Halaman ini mengatur pemicu otomatis untuk tugas operasional internal studio Musik KITA. Perubahan waktu atau tanggal di bawah akan langsung disesuaikan ke pemicu server (Linux Cron / Windows Task Scheduler) secara instan.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- Card 1: Generator Sesi Mingguan --}}
                <div class="bg-mk-card rounded-2xl border border-mk-borderLight p-6 shadow-sm space-y-4 flex flex-col justify-between transition-all hover:shadow-md hover:border-secondary/20">
                    <div class="space-y-4">
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-mk-surface flex items-center justify-center text-xl shrink-0 shadow-inner">
                                    📅
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-mk-text">Generator Sesi Mingguan</h3>
                                    <p class="text-[11px] text-mk-muted">Generate sesi reguler otomatis untuk bulan berikutnya.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" name="scheduler_sessions_generate_enabled" value="1"
                                       class="sr-only" x-model="sessionsGenerateEnabled">
                                <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner"
                                     :class="sessionsGenerateEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                    <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                          :class="sessionsGenerateEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                                </div>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-mk-borderLight" x-show="sessionsGenerateEnabled" x-collapse>
                            <div>
                                <label class="block text-[11px] font-semibold text-mk-muted mb-1">Tanggal Eksekusi</label>
                                <input type="number" name="scheduler_sessions_generate_day"
                                       value="{{ old('scheduler_sessions_generate_day', $settings['scheduler_sessions_generate_day']) }}"
                                       min="1" max="31" required
                                       class="w-full rounded-lg text-xs shadow-sm focus:border-secondary focus:ring-secondary @error('scheduler_sessions_generate_day') border-red-500 @enderror">
                                @error('scheduler_sessions_generate_day')
                                    <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Eksekusi</label>
                                <input type="time" name="scheduler_sessions_generate_time"
                                       value="{{ old('scheduler_sessions_generate_time', $settings['scheduler_sessions_generate_time']) }}"
                                       required
                                       class="w-full rounded-lg text-xs shadow-sm focus:border-secondary focus:ring-secondary @error('scheduler_sessions_generate_time') border-red-500 @enderror">
                                @error('scheduler_sessions_generate_time')
                                    <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-mk-dim pt-2.5 border-t border-mk-borderLight bg-mk-bg/50 -mx-6 -mb-6 px-6 py-3 rounded-b-2xl">
                        Default: Tanggal 25, Jam 06:00
                    </div>
                </div>

                {{-- Card 2: Generator SPP Bulanan --}}
                <div class="bg-mk-card rounded-2xl border border-mk-borderLight p-6 shadow-sm space-y-4 flex flex-col justify-between transition-all hover:shadow-md hover:border-secondary/20">
                    <div class="space-y-4">
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-mk-surface flex items-center justify-center text-xl shrink-0 shadow-inner">
                                    🧾
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-mk-text">Generator SPP Bulanan</h3>
                                    <p class="text-[11px] text-mk-muted">Penerbitan otomatis invoice bulanan untuk murid Aktif.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" name="scheduler_invoices_generate_enabled" value="1"
                                       class="sr-only" x-model="invoicesGenerateEnabled">
                                <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner"
                                     :class="invoicesGenerateEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                    <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                          :class="invoicesGenerateEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                                </div>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-mk-borderLight" x-show="invoicesGenerateEnabled" x-collapse>
                            <div>
                                <label class="block text-[11px] font-semibold text-mk-muted mb-1">Tanggal Eksekusi</label>
                                <input type="number" name="scheduler_invoices_generate_day"
                                       value="{{ old('scheduler_invoices_generate_day', $settings['scheduler_invoices_generate_day']) }}"
                                       min="1" max="31" required
                                       class="w-full rounded-lg text-xs shadow-sm focus:border-secondary focus:ring-secondary @error('scheduler_invoices_generate_day') border-red-500 @enderror">
                                @error('scheduler_invoices_generate_day')
                                    <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Eksekusi</label>
                                <input type="time" name="scheduler_invoices_generate_time"
                                       value="{{ old('scheduler_invoices_generate_time', $settings['scheduler_invoices_generate_time']) }}"
                                       required
                                       class="w-full rounded-lg text-xs shadow-sm focus:border-secondary focus:ring-secondary @error('scheduler_invoices_generate_time') border-red-500 @enderror">
                                @error('scheduler_invoices_generate_time')
                                    <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-mk-dim pt-2.5 border-t border-mk-borderLight bg-mk-bg/50 -mx-6 -mb-6 px-6 py-3 rounded-b-2xl">
                        Default: Tanggal 1, Jam 06:00
                    </div>
                </div>

                {{-- Card 3: Kalkulasi Honor Guru --}}
                <div class="bg-mk-card rounded-2xl border border-mk-borderLight p-6 shadow-sm space-y-4 flex flex-col justify-between transition-all hover:shadow-md hover:border-secondary/20">
                    <div class="space-y-4">
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-mk-surface flex items-center justify-center text-xl shrink-0 shadow-inner">
                                    💰
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-mk-text">Kalkulasi Honor Guru</h3>
                                    <p class="text-[11px] text-mk-muted">Perhitungan honor guru dari absensi (H-2 akhir bulan).</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" name="scheduler_honor_calculate_enabled" value="1"
                                       class="sr-only" x-model="honorCalculateEnabled">
                                <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner"
                                     :class="honorCalculateEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                    <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                          :class="honorCalculateEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                                </div>
                            </label>
                        </div>

                        <div class="pt-4 border-t border-mk-borderLight" x-show="honorCalculateEnabled" x-collapse>
                            <div class="w-1/2">
                                <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Eksekusi</label>
                                <input type="time" name="scheduler_honor_calculate_time"
                                       value="{{ old('scheduler_honor_calculate_time', $settings['scheduler_honor_calculate_time']) }}"
                                       required
                                       class="w-full rounded-lg text-xs shadow-sm focus:border-secondary focus:ring-secondary @error('scheduler_honor_calculate_time') border-red-500 @enderror">
                                @error('scheduler_honor_calculate_time')
                                    <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-mk-dim pt-2.5 border-t border-mk-borderLight bg-mk-bg/50 -mx-6 -mb-6 px-6 py-3 rounded-b-2xl">
                        Default: H-2 Akhir Bulan, Jam 06:00
                    </div>
                </div>

                {{-- Card 4: Apply Denda Harian --}}
                <div class="bg-mk-card rounded-2xl border border-mk-borderLight p-6 shadow-sm space-y-4 flex flex-col justify-between transition-all hover:shadow-md hover:border-secondary/20">
                    <div class="space-y-4">
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-mk-surface flex items-center justify-center text-xl shrink-0 shadow-inner">
                                    📉
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-mk-text">Apply Denda Harian</h3>
                                    <p class="text-[11px] text-mk-muted">Kalkulasi & penambahan denda Rp 5.000/hari mulai tanggal 11.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" name="scheduler_fines_apply_enabled" value="1"
                                       class="sr-only" x-model="finesApplyEnabled">
                                <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner"
                                     :class="finesApplyEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                    <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                          :class="finesApplyEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                                </div>
                            </label>
                        </div>

                        <div class="pt-4 border-t border-mk-borderLight" x-show="finesApplyEnabled" x-collapse>
                            <div class="w-1/2">
                                <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Eksekusi</label>
                                <input type="time" name="scheduler_fines_apply_time"
                                       value="{{ old('scheduler_fines_apply_time', $settings['scheduler_fines_apply_time']) }}"
                                       required
                                       class="w-full rounded-lg text-xs shadow-sm focus:border-secondary focus:ring-secondary @error('scheduler_fines_apply_time') border-red-500 @enderror">
                                @error('scheduler_fines_apply_time')
                                    <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-mk-dim pt-2.5 border-t border-mk-borderLight bg-mk-bg/50 -mx-6 -mb-6 px-6 py-3 rounded-b-2xl">
                        Default: Setiap Hari (mulai tgl 11), Jam 06:00
                    </div>
                </div>

                {{-- Card 5: Deteksi Murid Overdue --}}
                <div class="bg-mk-card rounded-2xl border border-mk-borderLight p-6 shadow-sm space-y-4 flex flex-col justify-between transition-all hover:shadow-md hover:border-secondary/20">
                    <div class="space-y-4">
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-mk-surface flex items-center justify-center text-xl shrink-0 shadow-inner">
                                    ⚠️
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-mk-text">Deteksi Murid Overdue</h3>
                                    <p class="text-[11px] text-mk-muted">Deteksi status murid menunggak SPP & kirim notifikasi.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" name="scheduler_overdue_check_enabled" value="1"
                                       class="sr-only" x-model="overdueCheckEnabled">
                                <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner"
                                     :class="overdueCheckEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                    <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                          :class="overdueCheckEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                                </div>
                            </label>
                        </div>

                        <div class="pt-4 border-t border-mk-borderLight" x-show="overdueCheckEnabled" x-collapse>
                            <div class="w-1/2">
                                <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Eksekusi</label>
                                <input type="time" name="scheduler_overdue_check_time"
                                       value="{{ old('scheduler_overdue_check_time', $settings['scheduler_overdue_check_time']) }}"
                                       required
                                       class="w-full rounded-lg text-xs shadow-sm focus:border-secondary focus:ring-secondary @error('scheduler_overdue_check_time') border-red-500 @enderror">
                                @error('scheduler_overdue_check_time')
                                    <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-mk-dim pt-2.5 border-t border-mk-borderLight bg-mk-bg/50 -mx-6 -mb-6 px-6 py-3 rounded-b-2xl">
                        Default: Tanggal 1, Jam 06:05
                    </div>
                </div>

                {{-- Card 6: WhatsApp Reminder --}}
                <div class="bg-mk-card rounded-2xl border border-mk-borderLight p-6 shadow-sm space-y-4 flex flex-col justify-between transition-all hover:shadow-md hover:border-secondary/20 md:col-span-2">
                    <div class="space-y-4">
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-mk-surface flex items-center justify-center text-xl shrink-0 shadow-inner">
                                    📲
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-mk-text">WhatsApp Scheduler Reminder</h3>
                                    <p class="text-[11px] text-mk-muted">Pengiriman otomatis pengingat sesi pelajaran murid via Fonnte.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" name="schedule_reminder_enabled" value="1"
                                       class="sr-only" x-model="reminderEnabled">
                                <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner"
                                     :class="reminderEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                    <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                          :class="reminderEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                                </div>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-mk-borderLight" x-show="reminderEnabled" x-collapse>
                            <div>
                                <label class="block text-[11px] font-semibold text-mk-muted mb-1">Mode Pengiriman</label>
                                <select name="schedule_reminder_mode" x-model="reminderMode"
                                        class="w-full rounded-lg text-xs shadow-sm focus:border-secondary focus:ring-secondary">
                                    <option value="day_before">H-1 Pelajaran (Harian)</option>
                                    <option value="same_day">Hari-H Pelajaran (Harian)</option>
                                    <option value="hours_before">N Jam Sebelum Pelajaran (Berkala)</option>
                                </select>
                            </div>

                            <div x-show="reminderMode !== 'hours_before'">
                                <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Pengiriman</label>
                                <input type="time" name="schedule_reminder_send_time"
                                       value="{{ old('schedule_reminder_send_time', $settings['schedule_reminder_send_time']) }}"
                                       class="w-full rounded-lg text-xs shadow-sm focus:border-secondary focus:ring-secondary">
                                @error('schedule_reminder_send_time')
                                    <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div x-show="reminderMode === 'hours_before'">
                                <label class="block text-[11px] font-semibold text-mk-muted mb-1">Kirim Berapa Jam Sebelum?</label>
                                <input type="number" name="schedule_reminder_hours_before"
                                       value="{{ old('schedule_reminder_hours_before', $settings['schedule_reminder_hours_before']) }}"
                                       min="1" max="24"
                                       class="w-full rounded-lg text-xs shadow-sm focus:border-secondary focus:ring-secondary">
                                @error('schedule_reminder_hours_before')
                                    <p class="text-[10px] text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-mk-dim pt-2.5 border-t border-mk-borderLight bg-mk-bg/50 -mx-6 -mb-6 px-6 py-3 rounded-b-2xl">
                        Default: H-1, Jam 18:00
                    </div>
                </div>

            </div>

            {{-- Button Save --}}
            <div class="flex justify-end pt-4">
                <button type="submit"
                        class="px-6 py-2.5 rounded-lg text-sm font-bold transition-colors btn-mk-primary flex items-center gap-2 shadow-sm">
                    <span>💾</span> Simpan Konfigurasi
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
