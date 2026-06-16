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
            <div class="bg-[#2c694e]/10 border border-[#2c694e]/30 rounded-2xl p-4 flex gap-3 text-xs text-mk-text">
                <span class="text-lg">⚙️</span>
                <div>
                    <h4 class="font-bold text-[#b1f0ce] mb-1">Informasi Sinkronisasi Jadwal</h4>
                    <p class="text-white/70 leading-relaxed">
                        Halaman ini mengatur pemicu otomatis untuk tugas operasional internal studio Musik KITA. Perubahan waktu atau tanggal di bawah akan langsung disesuaikan ke pemicu server (Linux Cron / Windows Task Scheduler) secara instan.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- Card 1: Generator Sesi Mingguan --}}
                <div class="bg-mk-card rounded-2xl border border-mk-border p-6 shadow-sm space-y-4 flex flex-col justify-between transition-all hover:border-white/10">
                    <div class="space-y-3">
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-2.5">
                                <span class="text-2xl">📅</span>
                                <div>
                                    <h3 class="text-sm font-bold text-white">Generator Sesi Mingguan</h3>
                                    <p class="text-[11px] text-mk-dim">Generate sesi reguler otomatis untuk bulan berikutnya.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="scheduler_sessions_generate_enabled" value="1"
                                       class="sr-only peer" x-model="sessionsGenerateEnabled">
                                <div class="w-9 h-5 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-secondary"></div>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-3 border-t border-white/[0.04]" x-show="sessionsGenerateEnabled" x-collapse>
                            <div>
                                <label class="block text-xs font-semibold text-white/70 mb-1.5">Tanggal Eksekusi</label>
                                <input type="number" name="scheduler_sessions_generate_day"
                                       value="{{ old('scheduler_sessions_generate_day', $settings['scheduler_sessions_generate_day']) }}"
                                       min="1" max="31" required
                                       class="w-full bg-mk-sidebar border border-white/10 text-white rounded-lg p-2 text-xs focus:border-secondary focus:ring-1 focus:ring-secondary @error('scheduler_sessions_generate_day') border-red-500 @enderror">
                                @error('scheduler_sessions_generate_day')
                                    <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-white/70 mb-1.5">Jam Eksekusi</label>
                                <input type="time" name="scheduler_sessions_generate_time"
                                       value="{{ old('scheduler_sessions_generate_time', $settings['scheduler_sessions_generate_time']) }}"
                                       required
                                       class="w-full bg-mk-sidebar border border-white/10 text-white rounded-lg p-2 text-xs focus:border-secondary focus:ring-1 focus:ring-secondary @error('scheduler_sessions_generate_time') border-red-500 @enderror">
                                @error('scheduler_sessions_generate_time')
                                    <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-white/40 pt-2 border-t border-white/[0.04] bg-white/[0.01] -mx-6 -mb-6 p-4 rounded-b-2xl">
                        Default: Tanggal 25, Jam 06:00
                    </div>
                </div>

                {{-- Card 2: Generator SPP Bulanan --}}
                <div class="bg-mk-card rounded-2xl border border-mk-border p-6 shadow-sm space-y-4 flex flex-col justify-between transition-all hover:border-white/10">
                    <div class="space-y-3">
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-2.5">
                                <span class="text-2xl">🧾</span>
                                <div>
                                    <h3 class="text-sm font-bold text-white">Generator SPP Bulanan</h3>
                                    <p class="text-[11px] text-mk-dim">Penerbitan otomatis invoice bulanan untuk murid Aktif.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="scheduler_invoices_generate_enabled" value="1"
                                       class="sr-only peer" x-model="invoicesGenerateEnabled">
                                <div class="w-9 h-5 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-secondary"></div>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-3 border-t border-white/[0.04]" x-show="invoicesGenerateEnabled" x-collapse>
                            <div>
                                <label class="block text-xs font-semibold text-white/70 mb-1.5">Tanggal Eksekusi</label>
                                <input type="number" name="scheduler_invoices_generate_day"
                                       value="{{ old('scheduler_invoices_generate_day', $settings['scheduler_invoices_generate_day']) }}"
                                       min="1" max="31" required
                                       class="w-full bg-mk-sidebar border border-white/10 text-white rounded-lg p-2 text-xs focus:border-secondary focus:ring-1 focus:ring-secondary @error('scheduler_invoices_generate_day') border-red-500 @enderror">
                                @error('scheduler_invoices_generate_day')
                                    <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-white/70 mb-1.5">Jam Eksekusi</label>
                                <input type="time" name="scheduler_invoices_generate_time"
                                       value="{{ old('scheduler_invoices_generate_time', $settings['scheduler_invoices_generate_time']) }}"
                                       required
                                       class="w-full bg-mk-sidebar border border-white/10 text-white rounded-lg p-2 text-xs focus:border-secondary focus:ring-1 focus:ring-secondary @error('scheduler_invoices_generate_time') border-red-500 @enderror">
                                @error('scheduler_invoices_generate_time')
                                    <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-white/40 pt-2 border-t border-white/[0.04] bg-white/[0.01] -mx-6 -mb-6 p-4 rounded-b-2xl">
                        Default: Tanggal 1, Jam 06:00
                    </div>
                </div>

                {{-- Card 3: Kalkulasi Honor Guru --}}
                <div class="bg-mk-card rounded-2xl border border-mk-border p-6 shadow-sm space-y-4 flex flex-col justify-between transition-all hover:border-white/10">
                    <div class="space-y-3">
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-2.5">
                                <span class="text-2xl">💰</span>
                                <div>
                                    <h3 class="text-sm font-bold text-white">Kalkulasi Honor Guru</h3>
                                    <p class="text-[11px] text-mk-dim">Perhitungan honor guru dari absensi (H-2 akhir bulan).</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="scheduler_honor_calculate_enabled" value="1"
                                       class="sr-only peer" x-model="honorCalculateEnabled">
                                <div class="w-9 h-5 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-secondary"></div>
                            </label>
                        </div>

                        <div class="pt-3 border-t border-white/[0.04]" x-show="honorCalculateEnabled" x-collapse>
                            <div class="w-1/2">
                                <label class="block text-xs font-semibold text-white/70 mb-1.5">Jam Eksekusi</label>
                                <input type="time" name="scheduler_honor_calculate_time"
                                       value="{{ old('scheduler_honor_calculate_time', $settings['scheduler_honor_calculate_time']) }}"
                                       required
                                       class="w-full bg-mk-sidebar border border-white/10 text-white rounded-lg p-2 text-xs focus:border-secondary focus:ring-1 focus:ring-secondary @error('scheduler_honor_calculate_time') border-red-500 @enderror">
                                @error('scheduler_honor_calculate_time')
                                    <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-white/40 pt-2 border-t border-white/[0.04] bg-white/[0.01] -mx-6 -mb-6 p-4 rounded-b-2xl">
                        Default: H-2 Akhir Bulan, Jam 06:00
                    </div>
                </div>

                {{-- Card 4: Apply Denda Harian --}}
                <div class="bg-mk-card rounded-2xl border border-mk-border p-6 shadow-sm space-y-4 flex flex-col justify-between transition-all hover:border-white/10">
                    <div class="space-y-3">
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-2.5">
                                <span class="text-2xl">📉</span>
                                <div>
                                    <h3 class="text-sm font-bold text-white">Apply Denda Harian</h3>
                                    <p class="text-[11px] text-mk-dim">Kalkulasi & penambahan denda Rp 5.000/hari mulai tanggal 11.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="scheduler_fines_apply_enabled" value="1"
                                       class="sr-only peer" x-model="finesApplyEnabled">
                                <div class="w-9 h-5 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-secondary"></div>
                            </label>
                        </div>

                        <div class="pt-3 border-t border-white/[0.04]" x-show="finesApplyEnabled" x-collapse>
                            <div class="w-1/2">
                                <label class="block text-xs font-semibold text-white/70 mb-1.5">Jam Eksekusi</label>
                                <input type="time" name="scheduler_fines_apply_time"
                                       value="{{ old('scheduler_fines_apply_time', $settings['scheduler_fines_apply_time']) }}"
                                       required
                                       class="w-full bg-mk-sidebar border border-white/10 text-white rounded-lg p-2 text-xs focus:border-secondary focus:ring-1 focus:ring-secondary @error('scheduler_fines_apply_time') border-red-500 @enderror">
                                @error('scheduler_fines_apply_time')
                                    <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-white/40 pt-2 border-t border-white/[0.04] bg-white/[0.01] -mx-6 -mb-6 p-4 rounded-b-2xl">
                        Default: Setiap Hari (mulai tgl 11), Jam 06:00
                    </div>
                </div>

                {{-- Card 5: Deteksi Murid Overdue --}}
                <div class="bg-mk-card rounded-2xl border border-mk-border p-6 shadow-sm space-y-4 flex flex-col justify-between transition-all hover:border-white/10">
                    <div class="space-y-3">
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-2.5">
                                <span class="text-2xl">⚠️</span>
                                <div>
                                    <h3 class="text-sm font-bold text-white">Deteksi Murid Overdue</h3>
                                    <p class="text-[11px] text-mk-dim">Deteksi status murid menunggak SPP & kirim notifikasi.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="scheduler_overdue_check_enabled" value="1"
                                       class="sr-only peer" x-model="overdueCheckEnabled">
                                <div class="w-9 h-5 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-secondary"></div>
                            </label>
                        </div>

                        <div class="pt-3 border-t border-white/[0.04]" x-show="overdueCheckEnabled" x-collapse>
                            <div class="w-1/2">
                                <label class="block text-xs font-semibold text-white/70 mb-1.5">Jam Eksekusi</label>
                                <input type="time" name="scheduler_overdue_check_time"
                                       value="{{ old('scheduler_overdue_check_time', $settings['scheduler_overdue_check_time']) }}"
                                       required
                                       class="w-full bg-mk-sidebar border border-white/10 text-white rounded-lg p-2 text-xs focus:border-secondary focus:ring-1 focus:ring-secondary @error('scheduler_overdue_check_time') border-red-500 @enderror">
                                @error('scheduler_overdue_check_time')
                                    <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-white/40 pt-2 border-t border-white/[0.04] bg-white/[0.01] -mx-6 -mb-6 p-4 rounded-b-2xl">
                        Default: Tanggal 1, Jam 06:05
                    </div>
                </div>

                {{-- Card 6: WhatsApp Reminder --}}
                <div class="bg-mk-card rounded-2xl border border-mk-border p-6 shadow-sm space-y-4 flex flex-col justify-between transition-all hover:border-white/10 md:col-span-2">
                    <div class="space-y-4">
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-2.5">
                                <span class="text-2xl">📲</span>
                                <div>
                                    <h3 class="text-sm font-bold text-white">WhatsApp Scheduler Reminder</h3>
                                    <p class="text-[11px] text-mk-dim">Pengiriman otomatis pengingat sesi pelajaran murid via Fonnte.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="schedule_reminder_enabled" value="1"
                                       class="sr-only peer" x-model="reminderEnabled">
                                <div class="w-9 h-5 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-secondary"></div>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-3 border-t border-white/[0.04]" x-show="reminderEnabled" x-collapse>
                            <div>
                                <label class="block text-xs font-semibold text-white/70 mb-1.5">Mode Pengiriman</label>
                                <select name="schedule_reminder_mode" x-model="reminderMode"
                                        class="w-full bg-mk-sidebar border border-white/10 text-white rounded-lg p-2.5 text-xs focus:border-secondary focus:ring-1 focus:ring-secondary">
                                    <option value="day_before">H-1 Pelajaran (Harian)</option>
                                    <option value="same_day">Hari-H Pelajaran (Harian)</option>
                                    <option value="hours_before">N Jam Sebelum Pelajaran (Berkala)</option>
                                </select>
                            </div>

                            <div x-show="reminderMode !== 'hours_before'">
                                <label class="block text-xs font-semibold text-white/70 mb-1.5">Jam Pengiriman</label>
                                <input type="time" name="schedule_reminder_send_time"
                                       value="{{ old('schedule_reminder_send_time', $settings['schedule_reminder_send_time']) }}"
                                       class="w-full bg-mk-sidebar border border-white/10 text-white rounded-lg p-2.5 text-xs focus:border-secondary focus:ring-1 focus:ring-secondary">
                                @error('schedule_reminder_send_time')
                                    <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div x-show="reminderMode === 'hours_before'">
                                <label class="block text-xs font-semibold text-white/70 mb-1.5">Kirim Berapa Jam Sebelum?</label>
                                <input type="number" name="schedule_reminder_hours_before"
                                       value="{{ old('schedule_reminder_hours_before', $settings['schedule_reminder_hours_before']) }}"
                                       min="1" max="24"
                                       class="w-full bg-mk-sidebar border border-white/10 text-white rounded-lg p-2.5 text-xs focus:border-secondary focus:ring-1 focus:ring-secondary">
                                @error('schedule_reminder_hours_before')
                                    <p class="text-[10px] text-red-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-white/40 pt-2 border-t border-white/[0.04] bg-white/[0.01] -mx-6 -mb-6 p-4 rounded-b-2xl">
                        Default: H-1, Jam 18:00
                    </div>
                </div>

            </div>

            {{-- Button Save --}}
            <div class="flex justify-end pt-4">
                <button type="submit"
                        class="px-6 py-2.5 bg-secondary hover:bg-opacity-95 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
                    <span>💾</span> Simpan Konfigurasi
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
