<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="text-xs text-mk-dim mb-0.5 font-medium">Pengaturan Sistem</div>
            <h2 class="font-semibold text-xl text-mk-text">Pengaturan Scheduler & Reminder</h2>
        </div>
    </x-slot>

    <div class="py-6 px-4 lg:px-8 max-w-5xl mx-auto space-y-6">

        {{-- Zona 1: Preview jadwal aktif --}}
        <div class="bg-mk-card rounded-2xl border border-mk-borderLight p-6 shadow-sm">
            <h3 class="text-sm font-bold text-mk-text mb-1">Preview Jadwal Aktif</h3>
            <p class="text-[11px] text-mk-muted mb-4">Estimasi berdasarkan konfigurasi saat ini. Berlaku setelah proses <code class="text-xs">schedule:run</code> berikutnya.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-mk-borderLight text-left text-[10px] font-semibold uppercase tracking-widest text-mk-dim">
                            <th class="pb-3 pr-4">Job</th>
                            <th class="pb-3 pr-4">Status</th>
                            <th class="pb-3 pr-4">Frekuensi</th>
                            <th class="pb-3">Eksekusi Berikutnya</th>
                        </tr>
                    </thead>
                    <tbody class="text-mk-text">
                        @foreach($preview as $row)
                        <tr class="border-b border-mk-borderLight/60 last:border-0 hover:bg-mk-surface/40 transition-colors duration-150">
                            <td class="py-2.5 pr-4 font-medium">{{ $row['name'] }}</td>
                            <td class="py-2.5 pr-4">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $row['enabled'] ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-mk-surface text-mk-dim border border-mk-borderLight' }}">
                                    {{ $row['status_label'] }}
                                </span>
                            </td>
                            <td class="py-2.5 pr-4 text-mk-muted">{{ $row['frequency_label'] }}</td>
                            <td class="py-2.5 text-mk-muted">{{ $row['next_run_label'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-[10px] text-mk-dim mt-4 leading-relaxed">
                Prasyarat: Windows Task Scheduler (atau cron Linux) harus menjalankan
                <code class="text-[10px]">php artisan schedule:run</code> setiap menit.
                Halaman ini tidak mengubah task OS secara otomatis.
            </p>
        </div>

        <div
              x-data="schedulerSettingsForm(@js([
                  'sessionsGenerateEnabled' => (bool) $settings['scheduler_sessions_generate_enabled'],
                  'invoicesGenerateEnabled' => (bool) $settings['scheduler_invoices_generate_enabled'],
                  'honorCalculateEnabled' => (bool) $settings['scheduler_honor_calculate_enabled'],
                  'finesApplyEnabled' => (bool) $settings['scheduler_fines_apply_enabled'],
                  'overdueCheckEnabled' => (bool) $settings['scheduler_overdue_check_enabled'],
                  'invoiceReminderEnabled' => (bool) $settings['scheduler_invoice_reminder_enabled'],
                  'cutiReturnEnabled' => (bool) $settings['scheduler_cuti_return_enabled'],
                  'staffPayrollEnabled' => (bool) $settings['scheduler_staff_payroll_enabled'],
                  'reminderEnabled' => (bool) $settings['schedule_reminder_enabled'],
                  'reminderMode' => $settings['schedule_reminder_mode'],
                  'whatsappProvider' => old('integration_whatsapp_provider', $settings['integration_whatsapp_provider']),
                  'integrationDirty' => $errors->any() && old('_method') === 'PATCH',
                  'fonnteTokenMasked' => $settings['integration_fonnte_token_masked'],
                  'wablasTokenMasked' => $settings['integration_wablas_token_masked'],
                  'wablasSecretMasked' => $settings['integration_wablas_secret_masked'],
              ]))"
              >
        <form action="{{ route('scheduler.update') }}" method="POST" class="space-y-6"
              @submit="clearMaskedSecretsBeforeSubmit($event)">
            @csrf
            @method('PATCH')

            <div class="bg-mk-warningBg border border-mk-warningBorder text-mk-warning rounded-2xl p-4 flex gap-3 text-xs shadow-sm">
                <span class="text-lg shrink-0" aria-hidden="true">⚠️</span>
                <div>
                    <h4 class="font-bold text-mk-warning mb-1">Cara Kerja Pengaturan Scheduler</h4>
                    <p class="text-mk-muted leading-relaxed">
                        Perubahan disimpan ke database dan dibaca ulang setiap kali <code class="text-xs">php artisan schedule:run</code> dijalankan (biasanya ≤ 1 menit).
                        Task Windows Task Scheduler atau cron Linux <strong>tidak</strong> diubah otomatis dari halaman ini.
                    </p>
                </div>
            </div>

            {{-- Zona 2: Keuangan & Billing --}}
            <section class="bg-mk-card rounded-2xl border border-mk-borderLight p-6 shadow-sm space-y-0">
                <header class="mb-5">
                    <h3 class="text-sm font-bold text-mk-text">Job Otomatis — Keuangan & Billing</h3>
                    <p class="text-[11px] text-mk-muted mt-0.5">SPP, denda, reminder tagihan, dan deteksi tunggakan.</p>
                </header>

                {{-- Generator SPP --}}
                <div class="pt-5 border-t border-mk-borderLight space-y-4">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h4 class="text-sm font-semibold text-mk-text">Generator SPP Bulanan</h4>
                            <p class="text-[11px] text-mk-muted mt-0.5">Penerbitan otomatis invoice bulanan untuk enrollment ACTIVE.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <span class="sr-only">Aktifkan generator SPP bulanan</span>
                            <input type="checkbox" name="scheduler_invoices_generate_enabled" value="1" class="sr-only peer" x-model="invoicesGenerateEnabled">
                            <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-offset-1 peer-focus:ring-mk-accent"
                                 :class="invoicesGenerateEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                      :class="invoicesGenerateEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                            </div>
                        </label>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4" :class="!invoicesGenerateEnabled && 'opacity-50'" x-show="invoicesGenerateEnabled" x-cloak>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Tanggal Eksekusi</label>
                            <input type="number" name="scheduler_invoices_generate_day"
                                   value="{{ old('scheduler_invoices_generate_day', $settings['scheduler_invoices_generate_day']) }}"
                                   min="1" max="31" class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_invoices_generate_day') border-red-500 @enderror">
                            @error('scheduler_invoices_generate_day')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Eksekusi</label>
                            <input type="time" name="scheduler_invoices_generate_time"
                                   value="{{ old('scheduler_invoices_generate_time', $settings['scheduler_invoices_generate_time']) }}"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_invoices_generate_time') border-red-500 @enderror">
                            @error('scheduler_invoices_generate_time')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Tempo Akhir Bayar SPP</label>
                            <input type="number" name="spp_due_day"
                                   value="{{ old('spp_due_day', $settings['spp_due_day']) }}"
                                   min="1" max="31" required
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('spp_due_day') border-red-500 @enderror">
                            <p class="text-[10px] text-mk-dim mt-1">Tempo akhir bayar SPP setiap bulan (default tgl 10).</p>
                            @error('spp_due_day')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- Apply Denda --}}
                <div class="pt-5 mt-5 border-t border-mk-borderLight space-y-4">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h4 class="text-sm font-semibold text-mk-text">Apply Denda Harian</h4>
                            <p class="text-[11px] text-mk-muted mt-0.5">
                                Kalkulasi denda Rp {{ number_format($finePerDay, 0, ',', '.') }}/hari.
                                Nominal diatur di <a href="{{ route('invoice-components.index') }}" class="text-indigo-600 hover:underline focus:outline-none focus:ring-2 focus:ring-mk-accent rounded">Komponen Invoice → DENDA</a>.
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <span class="sr-only">Aktifkan apply denda harian</span>
                            <input type="checkbox" name="scheduler_fines_apply_enabled" value="1" class="sr-only peer" x-model="finesApplyEnabled">
                            <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-offset-1 peer-focus:ring-mk-accent"
                                 :class="finesApplyEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                      :class="finesApplyEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                            </div>
                        </label>
                    </div>
                    <div class="grid grid-cols-2 gap-4" :class="!finesApplyEnabled && 'opacity-50'" x-show="finesApplyEnabled" x-cloak>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Tanggal Mulai Denda</label>
                            <input type="number" name="scheduler_fines_start_day"
                                   value="{{ old('scheduler_fines_start_day', $settings['scheduler_fines_start_day']) }}"
                                   min="1" max="31"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_fines_start_day') border-red-500 @enderror">
                            @error('scheduler_fines_start_day')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Eksekusi</label>
                            <input type="time" name="scheduler_fines_apply_time"
                                   value="{{ old('scheduler_fines_apply_time', $settings['scheduler_fines_apply_time']) }}"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_fines_apply_time') border-red-500 @enderror">
                            @error('scheduler_fines_apply_time')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- Invoice Reminder Tagihan --}}
                <div class="pt-5 mt-5 border-t border-mk-borderLight space-y-4">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h4 class="text-sm font-semibold text-mk-text">Invoice Reminder Tagihan</h4>
                            <p class="text-[11px] text-mk-muted mt-0.5">Pengingat otomatis ke ortu untuk tagihan UNPAID/PARTIAL. Max 1× per murid per bulan. Pesan teks saja.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <span class="sr-only">Aktifkan reminder tagihan otomatis</span>
                            <input type="checkbox" name="scheduler_invoice_reminder_enabled" value="1" class="sr-only peer" x-model="invoiceReminderEnabled">
                            <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-offset-1 peer-focus:ring-mk-accent"
                                 :class="invoiceReminderEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                      :class="invoiceReminderEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                            </div>
                        </label>
                    </div>
                    {{-- ponytail: template dipilih otomatis oleh service (INVOICE_REMINDER / INVOICE_REMINDER_STUDENT via fallback no HP) --}}
                    <div class="grid grid-cols-2 gap-4" :class="!invoiceReminderEnabled && 'opacity-50'" x-show="invoiceReminderEnabled" x-cloak>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Kirim H-N (hari sebelum tempo)</label>
                            <input type="number" name="scheduler_invoice_reminder_days_before_due"
                                   value="{{ old('scheduler_invoice_reminder_days_before_due', $settings['scheduler_invoice_reminder_days_before_due']) }}"
                                   min="1" max="{{ max(1, $settings['spp_due_day'] - 1) }}"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_invoice_reminder_days_before_due') border-red-500 @enderror">
                            @error('scheduler_invoice_reminder_days_before_due')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Eksekusi</label>
                            <input type="time" name="scheduler_invoice_reminder_time"
                                   value="{{ old('scheduler_invoice_reminder_time', $settings['scheduler_invoice_reminder_time']) }}"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_invoice_reminder_time') border-red-500 @enderror">
                            @error('scheduler_invoice_reminder_time')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- Deteksi Overdue --}}
                <div class="pt-5 mt-5 border-t border-mk-borderLight space-y-4">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h4 class="text-sm font-semibold text-mk-text">Deteksi Murid Overdue</h4>
                            <p class="text-[11px] text-mk-muted mt-0.5">Deteksi status murid menunggak SPP & kirim notifikasi in-app.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <span class="sr-only">Aktifkan deteksi murid overdue</span>
                            <input type="checkbox" name="scheduler_overdue_check_enabled" value="1" class="sr-only peer" x-model="overdueCheckEnabled">
                            <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-offset-1 peer-focus:ring-mk-accent"
                                 :class="overdueCheckEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                      :class="overdueCheckEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                            </div>
                        </label>
                    </div>
                    <div class="grid grid-cols-2 gap-4" :class="!overdueCheckEnabled && 'opacity-50'" x-show="overdueCheckEnabled" x-cloak>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Tanggal Eksekusi</label>
                            <input type="number" name="scheduler_overdue_check_day"
                                   value="{{ old('scheduler_overdue_check_day', $settings['scheduler_overdue_check_day']) }}"
                                   min="1" max="31"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_overdue_check_day') border-red-500 @enderror">
                            @error('scheduler_overdue_check_day')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Eksekusi</label>
                            <input type="time" name="scheduler_overdue_check_time"
                                   value="{{ old('scheduler_overdue_check_time', $settings['scheduler_overdue_check_time']) }}"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_overdue_check_time') border-red-500 @enderror">
                            @error('scheduler_overdue_check_time')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </section>

            {{-- Zona 3: Operasional --}}
            <section class="bg-mk-card rounded-2xl border border-mk-borderLight p-6 shadow-sm space-y-0">
                <header class="mb-5">
                    <h3 class="text-sm font-bold text-mk-text">Job Otomatis — Operasional</h3>
                    <p class="text-[11px] text-mk-muted mt-0.5">Sesi, honor guru, cuti, dan gaji staff.</p>
                </header>

                {{-- Generator Sesi --}}
                <div class="pt-5 border-t border-mk-borderLight space-y-4">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h4 class="text-sm font-semibold text-mk-text">Generator Sesi Mingguan</h4>
                            <p class="text-[11px] text-mk-muted mt-0.5">Generate sesi reguler otomatis untuk bulan berikutnya.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <span class="sr-only">Aktifkan generator sesi mingguan</span>
                            <input type="checkbox" name="scheduler_sessions_generate_enabled" value="1" class="sr-only peer" x-model="sessionsGenerateEnabled">
                            <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-offset-1 peer-focus:ring-mk-accent"
                                 :class="sessionsGenerateEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                      :class="sessionsGenerateEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                            </div>
                        </label>
                    </div>
                    <div class="grid grid-cols-2 gap-4" :class="!sessionsGenerateEnabled && 'opacity-50'" x-show="sessionsGenerateEnabled" x-cloak>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Tanggal Eksekusi</label>
                            <input type="number" name="scheduler_sessions_generate_day"
                                   value="{{ old('scheduler_sessions_generate_day', $settings['scheduler_sessions_generate_day']) }}"
                                   min="1" max="31"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_sessions_generate_day') border-red-500 @enderror">
                            @error('scheduler_sessions_generate_day')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Eksekusi</label>
                            <input type="time" name="scheduler_sessions_generate_time"
                                   value="{{ old('scheduler_sessions_generate_time', $settings['scheduler_sessions_generate_time']) }}"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_sessions_generate_time') border-red-500 @enderror">
                            @error('scheduler_sessions_generate_time')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- Honor Guru --}}
                <div class="pt-5 mt-5 border-t border-mk-borderLight space-y-4">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h4 class="text-sm font-semibold text-mk-text">Kalkulasi Honor Guru</h4>
                            <p class="text-[11px] text-mk-muted mt-0.5">Perhitungan honor guru dari absensi — tanggal tetap setiap bulan.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <span class="sr-only">Aktifkan kalkulasi honor guru</span>
                            <input type="checkbox" name="scheduler_honor_calculate_enabled" value="1" class="sr-only peer" x-model="honorCalculateEnabled">
                            <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-offset-1 peer-focus:ring-mk-accent"
                                 :class="honorCalculateEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                      :class="honorCalculateEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                            </div>
                        </label>
                    </div>
                    <div class="grid grid-cols-2 gap-4" :class="!honorCalculateEnabled && 'opacity-50'" x-show="honorCalculateEnabled" x-cloak>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Tanggal Eksekusi</label>
                            <input type="number" name="scheduler_honor_calculate_day"
                                   value="{{ old('scheduler_honor_calculate_day', $settings['scheduler_honor_calculate_day']) }}"
                                   min="1" max="31"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_honor_calculate_day') border-red-500 @enderror">
                            @error('scheduler_honor_calculate_day')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Eksekusi</label>
                            <input type="time" name="scheduler_honor_calculate_time"
                                   value="{{ old('scheduler_honor_calculate_time', $settings['scheduler_honor_calculate_time']) }}"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_honor_calculate_time') border-red-500 @enderror">
                            @error('scheduler_honor_calculate_time')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- Cuti → Aktif --}}
                <div class="pt-5 mt-5 border-t border-mk-borderLight space-y-4">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h4 class="text-sm font-semibold text-mk-text">Cuti → Aktif Otomatis</h4>
                            <p class="text-[11px] text-mk-muted mt-0.5">Cuti per kelas disinkronkan pukul 00:00 dan jam di bawah. Kelas aktif kembali sehari setelah tanggal akhir + notifikasi Admin/Owner.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <span class="sr-only">Aktifkan cuti ke aktif otomatis</span>
                            <input type="checkbox" name="scheduler_cuti_return_enabled" value="1" class="sr-only peer" x-model="cutiReturnEnabled">
                            <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-offset-1 peer-focus:ring-mk-accent"
                                 :class="cutiReturnEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                      :class="cutiReturnEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                            </div>
                        </label>
                    </div>
                    <div class="max-w-xs" :class="!cutiReturnEnabled && 'opacity-50'" x-show="cutiReturnEnabled" x-cloak>
                        <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Eksekusi Tambahan Harian</label>
                        <input type="time" name="scheduler_cuti_return_time"
                               value="{{ old('scheduler_cuti_return_time', $settings['scheduler_cuti_return_time']) }}"
                               class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_cuti_return_time') border-red-500 @enderror">
                        @error('scheduler_cuti_return_time')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Staff Payroll --}}
                <div class="pt-5 mt-5 border-t border-mk-borderLight space-y-4">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h4 class="text-sm font-semibold text-mk-text">Generate Slip Gaji Staff</h4>
                            <p class="text-[11px] text-mk-muted mt-0.5">Buat slip DRAFT untuk semua karyawan aktif bulan berjalan (M12).</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <span class="sr-only">Aktifkan generate slip gaji staff</span>
                            <input type="checkbox" name="scheduler_staff_payroll_enabled" value="1" class="sr-only peer" x-model="staffPayrollEnabled">
                            <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-offset-1 peer-focus:ring-mk-accent"
                                 :class="staffPayrollEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                      :class="staffPayrollEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                            </div>
                        </label>
                    </div>
                    <div class="grid grid-cols-2 gap-4" :class="!staffPayrollEnabled && 'opacity-50'" x-show="staffPayrollEnabled" x-cloak>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Tanggal Eksekusi</label>
                            <input type="number" name="scheduler_staff_payroll_day"
                                   value="{{ old('scheduler_staff_payroll_day', $settings['scheduler_staff_payroll_day']) }}"
                                   min="1" max="31"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_staff_payroll_day') border-red-500 @enderror">
                            @error('scheduler_staff_payroll_day')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Eksekusi</label>
                            <input type="time" name="scheduler_staff_payroll_time"
                                   value="{{ old('scheduler_staff_payroll_time', $settings['scheduler_staff_payroll_time']) }}"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('scheduler_staff_payroll_time') border-red-500 @enderror">
                            @error('scheduler_staff_payroll_time')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </section>

            {{-- Zona 4: Reminder & Notifikasi --}}
            <section class="bg-mk-card rounded-2xl border border-mk-borderLight p-6 shadow-sm space-y-0">
                <header class="mb-5">
                    <h3 class="text-sm font-bold text-mk-text">Reminder & Notifikasi</h3>
                    <p class="text-[11px] text-mk-muted mt-0.5">Pengingat jadwal kelas melalui provider WhatsApp global.</p>
                </header>

                <div class="pt-5 border-t border-mk-borderLight space-y-4">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h4 class="text-sm font-semibold text-mk-text">WhatsApp Reminder Jadwal Kelas</h4>
                            <p class="text-[11px] text-mk-muted mt-0.5">Pengiriman otomatis mengikuti provider yang dipilih pada Integrasi WhatsApp.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <span class="sr-only">Aktifkan WhatsApp reminder jadwal</span>
                            <input type="checkbox" name="schedule_reminder_enabled" value="1" class="sr-only peer" x-model="reminderEnabled">
                            <div class="w-12 h-7 rounded-full transition-colors relative border shadow-inner peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-offset-1 peer-focus:ring-mk-accent"
                                 :class="reminderEnabled ? 'bg-secondary border-secondary' : 'bg-gray-200 border-gray-300'">
                                <span class="absolute top-1 left-1 bg-white rounded-full h-5 w-5 transition-transform duration-200 shadow-sm"
                                      :class="reminderEnabled ? 'translate-x-5' : 'translate-x-0'"></span>
                            </div>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4" :class="!reminderEnabled && 'opacity-50'" x-show="reminderEnabled" x-cloak>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Mode Pengiriman</label>
                            <select name="schedule_reminder_mode" x-model="reminderMode"
                                    class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0">
                                <option value="day_before">H-1 Pelajaran (Harian)</option>
                                <option value="same_day">Hari-H Pelajaran (Harian)</option>
                                <option value="hours_before">N Jam Sebelum Pelajaran (Berkala)</option>
                            </select>
                        </div>

                        <div x-show="reminderMode !== 'hours_before'">
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Jam Pengiriman</label>
                            <input type="time" name="schedule_reminder_send_time"
                                   value="{{ old('schedule_reminder_send_time', $settings['schedule_reminder_send_time']) }}"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0">
                            @error('schedule_reminder_send_time')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div x-show="reminderMode === 'hours_before'">
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Kirim Berapa Jam Sebelum?</label>
                            <input type="number" name="schedule_reminder_hours_before"
                                   value="{{ old('schedule_reminder_hours_before', $settings['schedule_reminder_hours_before']) }}"
                                   min="1" max="24"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0">
                            @error('schedule_reminder_hours_before')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div x-show="reminderMode === 'hours_before'">
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Interval Poll (menit)</label>
                            <input type="number" name="schedule_reminder_poll_minutes"
                                   value="{{ old('schedule_reminder_poll_minutes', $settings['schedule_reminder_poll_minutes']) }}"
                                   min="5" max="60"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0">
                            <p class="text-[10px] text-mk-dim mt-1">Seberapa sering cron cek sesi (mode N jam sebelum).</p>
                            @error('schedule_reminder_poll_minutes')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </section>

            {{-- Zona 5: Integrasi WhatsApp --}}
            <section id="whatsapp-integration" @input="integrationDirty = true" @change="integrationDirty = true"
                     class="bg-mk-card rounded-2xl border border-mk-borderLight p-6 shadow-sm space-y-0">
                <header class="mb-5">
                    <h3 class="text-sm font-bold text-mk-text">Integrasi WhatsApp</h3>
                    <p class="text-[11px] text-mk-muted mt-0.5">Kredensial disimpan terenkripsi. Isi field hanya jika ingin mengganti nilai yang sudah ada.</p>
                </header>
                <div class="mb-6 space-y-2">
                    <label for="integration_whatsapp_provider" class="block text-sm font-semibold text-mk-text">Provider WhatsApp global</label>
                    <select id="integration_whatsapp_provider" name="integration_whatsapp_provider" x-model="whatsappProvider"
                            class="min-h-11 w-full rounded-lg border-mk-border text-sm focus:border-mk-accent focus:ring-mk-accent">
                        <option value="fonnte">Fonnte</option>
                        <option value="gowa">GOWA — server sendiri</option>
                    </select>
                    <p class="text-sm text-mk-muted">Berlaku untuk reminder jadwal, reminder tagihan, dan laporan sesi. Konfigurasi provider lain tetap tersimpan.</p>
                    @error('integration_whatsapp_provider')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Fonnte --}}
                <div x-show="whatsappProvider === 'fonnte'" class="pt-5 border-t border-mk-borderLight space-y-4">
                    <h4 class="text-sm font-semibold text-mk-text">Fonnte</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Token Device</label>
                            <div class="mb-1.5">
                                @if($settings['integration_fonnte_token_configured'])
                                    <p class="text-[10px] leading-snug">
                                        <span class="text-mk-muted">Nilai aktif:</span>
                                        <code class="font-mono text-mk-text tracking-wide">{{ $settings['integration_fonnte_token_masked'] }}</code>
                                        @if($settings['integration_fonnte_token_from_env'])
                                            <span class="text-mk-dim"> · dari .env</span>
                                        @endif
                                    </p>
                                @else
                                    <p class="text-[10px] text-mk-dim leading-snug">Belum dikonfigurasi — isi token di bawah.</p>
                                @endif
                            </div>
                            <div class="relative">
                                <input x-ref="fonnteTokenInput"
                                       :type="showFonnteToken ? 'text' : 'password'"
                                       name="integration_fonnte_token"
                                       placeholder="Token baru (kosongkan jika tidak mengubah)"
                                       autocomplete="new-password"
                                       class="w-full h-9 rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 pr-16 @error('integration_fonnte_token') border-red-500 @enderror">
                                @if($settings['integration_fonnte_token_configured'])
                                <button type="button" @click="toggleSecret('fonnte')"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-mk-muted hover:text-mk-text px-2 py-1 focus:outline-none focus:ring-2 focus:ring-mk-accent rounded">
                                    <span x-text="showFonnteToken ? 'Sembunyi' : 'Tampil'"></span>
                                </button>
                                @endif
                            </div>
                            @error('integration_fonnte_token')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Base URL API</label>
                            {{-- Spacer invisible: salin markup baris status Token agar tinggi identik (desktop) --}}
                            <div class="mb-1.5 hidden md:block" aria-hidden="true">
                                @if($settings['integration_fonnte_token_configured'])
                                    <p class="text-[10px] leading-snug" style="visibility:hidden">
                                        <span>Nilai aktif:</span>
                                        <code class="font-mono tracking-wide">{{ $settings['integration_fonnte_token_masked'] }}</code>
                                        @if($settings['integration_fonnte_token_from_env'])
                                            <span> · dari .env</span>
                                        @endif
                                    </p>
                                @else
                                    <p class="text-[10px] leading-snug" style="visibility:hidden">Belum dikonfigurasi — isi token di bawah.</p>
                                @endif
                            </div>
                            <input type="url" name="integration_fonnte_base_url"
                                   value="{{ old('integration_fonnte_base_url', $settings['integration_fonnte_base_url']) }}"
                                   placeholder="https://api.fonnte.com"
                                   class="w-full h-9 rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('integration_fonnte_base_url') border-red-500 @enderror">
                            @error('integration_fonnte_base_url')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div x-show="whatsappProvider === 'gowa'" x-cloak class="pt-5 border-t border-mk-borderLight space-y-4">
                    <h4 class="text-sm font-semibold text-mk-text">GOWA melalui Tailscale</h4>
                    <p class="text-sm text-mk-muted">Scan QR, login, dan kelola perangkat di dashboard GOWA. Musik KITA hanya menggunakan koneksi ini untuk mengirim pesan.</p>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="integration_gowa_base_url" class="mb-1 block text-sm font-semibold text-mk-text">Base URL GOWA</label>
                            <input id="integration_gowa_base_url" name="integration_gowa_base_url" type="url"
                                   value="{{ old('integration_gowa_base_url', $settings['integration_gowa_base_url']) }}"
                                   placeholder="http://nama-vps-tailscale:3000"
                                   class="min-h-11 w-full rounded-lg border-mk-border text-sm focus:border-mk-accent focus:ring-mk-accent">
                            <p class="mt-1 text-xs text-mk-muted">Alamat Tailscale/MagicDNS dari VPS Musik KITA, termasuk base path jika ada.</p>
                            @error('integration_gowa_base_url')<p role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="integration_gowa_device_id" class="mb-1 block text-sm font-semibold text-mk-text">Device ID</label>
                            <input id="integration_gowa_device_id" name="integration_gowa_device_id" type="text"
                                   value="{{ old('integration_gowa_device_id', $settings['integration_gowa_device_id']) }}"
                                   class="min-h-11 w-full rounded-lg border-mk-border text-sm focus:border-mk-accent focus:ring-mk-accent">
                            @error('integration_gowa_device_id')<p role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="integration_gowa_username" class="mb-1 block text-sm font-semibold text-mk-text">Username Basic Auth</label>
                            <input id="integration_gowa_username" name="integration_gowa_username" type="text" autocomplete="off"
                                   value="{{ old('integration_gowa_username', $settings['integration_gowa_username']) }}"
                                   class="min-h-11 w-full rounded-lg border-mk-border text-sm focus:border-mk-accent focus:ring-mk-accent">
                            @error('integration_gowa_username')<p role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="integration_gowa_password" class="mb-1 block text-sm font-semibold text-mk-text">Password Basic Auth</label>
                            <div class="flex gap-2">
                                <input id="integration_gowa_password" name="integration_gowa_password" :type="showGowaPassword ? 'text' : 'password'"
                                       autocomplete="new-password" placeholder="Kosongkan jika tidak mengubah"
                                       class="min-h-11 min-w-0 flex-1 rounded-lg border-mk-border text-sm focus:border-mk-accent focus:ring-mk-accent">
                                <button type="button" @click="showGowaPassword = !showGowaPassword" :aria-pressed="showGowaPassword"
                                        class="min-h-11 rounded-lg border border-mk-border px-3 text-sm text-mk-text focus-visible:ring-2 focus-visible:ring-mk-accent">
                                    <span x-text="showGowaPassword ? 'Sembunyikan' : 'Tampilkan'"></span>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-mk-muted">{{ $settings['integration_gowa_password_configured'] ? 'Password tersimpan: ********. Isi hanya untuk mengganti.' : 'Password belum dikonfigurasi.' }}</p>
                            @error('integration_gowa_password')<p role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- Wablas --}}
                <div class="pt-5 mt-5 border-t border-mk-borderLight space-y-4">
                    <h4 class="text-sm font-semibold text-mk-text">Wablas <span class="text-[11px] font-normal text-mk-muted">(reminder tagihan & laporan sesi)</span></h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Token</label>
                            @if($settings['integration_wablas_token_configured'])
                                <p class="text-[10px] mb-1.5">
                                    <span class="text-mk-muted">Nilai aktif:</span>
                                    <code class="font-mono text-mk-text tracking-wide">{{ $settings['integration_wablas_token_masked'] }}</code>
                                    @if($settings['integration_wablas_token_from_env'])
                                        <span class="text-mk-dim"> · dari .env</span>
                                    @endif
                                </p>
                            @else
                                <p class="text-[10px] text-mk-dim mb-1.5">Belum dikonfigurasi — isi token di bawah.</p>
                            @endif
                            <div class="relative">
                                <input x-ref="wablasTokenInput"
                                       :type="showWablasToken ? 'text' : 'password'"
                                       name="integration_wablas_token"
                                       placeholder="Token baru (kosongkan jika tidak mengubah)"
                                       autocomplete="new-password"
                                       class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 pr-16 @error('integration_wablas_token') border-red-500 @enderror">
                                @if($settings['integration_wablas_token_configured'])
                                <button type="button" @click="toggleSecret('wablasToken')"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-mk-muted hover:text-mk-text px-2 py-1 focus:outline-none focus:ring-2 focus:ring-mk-accent rounded">
                                    <span x-text="showWablasToken ? 'Sembunyi' : 'Tampil'"></span>
                                </button>
                                @endif
                            </div>
                            @error('integration_wablas_token')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Secret Key</label>
                            @if($settings['integration_wablas_secret_configured'])
                                <p class="text-[10px] mb-1.5">
                                    <span class="text-mk-muted">Nilai aktif:</span>
                                    <code class="font-mono text-mk-text tracking-wide">{{ $settings['integration_wablas_secret_masked'] }}</code>
                                    @if($settings['integration_wablas_secret_from_env'])
                                        <span class="text-mk-dim"> · dari .env</span>
                                    @endif
                                </p>
                            @else
                                <p class="text-[10px] text-mk-dim mb-1.5">Belum dikonfigurasi — isi secret key di bawah.</p>
                            @endif
                            <div class="relative">
                                <input x-ref="wablasSecretInput"
                                       :type="showWablasSecret ? 'text' : 'password'"
                                       name="integration_wablas_secret_key"
                                       placeholder="Secret key baru (kosongkan jika tidak mengubah)"
                                       autocomplete="new-password"
                                       class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 pr-16 @error('integration_wablas_secret_key') border-red-500 @enderror">
                                @if($settings['integration_wablas_secret_configured'])
                                <button type="button" @click="toggleSecret('wablasSecret')"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-mk-muted hover:text-mk-text px-2 py-1 focus:outline-none focus:ring-2 focus:ring-mk-accent rounded">
                                    <span x-text="showWablasSecret ? 'Sembunyi' : 'Tampil'"></span>
                                </button>
                                @endif
                            </div>
                            @error('integration_wablas_secret_key')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-semibold text-mk-muted mb-1">Base URL API</label>
                            <input type="url" name="integration_wablas_base_url"
                                   value="{{ old('integration_wablas_base_url', $settings['integration_wablas_base_url']) }}"
                                   placeholder="https://solo.wablas.com"
                                   class="w-full rounded-lg text-xs shadow-sm border-mk-border focus:border-mk-accent focus:ring-1 focus:ring-mk-accent focus:ring-offset-0 @error('integration_wablas_base_url') border-red-500 @enderror">
                            @error('integration_wablas_base_url')<p class="text-[10px] text-error font-semibold mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex justify-end pt-2 pb-6">
                <button type="submit"
                        class="px-6 py-2.5 rounded-lg text-sm font-bold transition-colors btn-mk-primary flex items-center gap-2 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-mk-accent">
                    Simpan Konfigurasi
                </button>
            </div>
        </form>

        <section id="whatsapp-diagnostics" class="mt-6 space-y-4 rounded-2xl border border-mk-borderLight bg-mk-card p-6">
            <div>
                <h3 class="text-sm font-bold text-mk-text">Uji koneksi WhatsApp</h3>
                <p class="mt-1 text-sm text-mk-muted">Menggunakan konfigurasi tersimpan. Provider aktif: <strong>{{ strtoupper($settings['integration_whatsapp_provider']) }}</strong>. Tidak berpindah provider otomatis jika gagal.</p>
            </div>
            <p x-show="integrationDirty" x-cloak role="status" class="text-sm text-mk-warning">Simpan konfigurasi WhatsApp terlebih dahulu.</p>
            @if($feedback = session('whatsapp_result'))
                <div role="{{ in_array($feedback['state'], ['connected', 'configuration_present', 'sent']) ? 'status' : 'alert' }}"
                     class="rounded-lg border border-mk-borderLight p-4 text-sm text-mk-text">
                    <p class="font-semibold">{{ strtoupper($feedback['provider']) }} — {{ $feedback['message'] }}</p>
                    @if(!empty($feedback['message_ids']))
                        <p class="mt-1 break-all text-xs text-mk-muted">ID pesan: {{ implode(', ', $feedback['message_ids']) }}</p>
                    @endif
                </div>
            @endif
            <form method="POST" action="{{ route('scheduler.whatsapp.check') }}" @submit="if (integrationDirty || diagnosticBusy) { $event.preventDefault(); } else { diagnosticBusy = true; }">
                @csrf
                <button type="submit" :disabled="integrationDirty || diagnosticBusy"
                        class="min-h-11 rounded-lg border border-mk-border px-4 text-sm font-semibold text-mk-text disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-mk-accent">
                    Cek koneksi tersimpan
                </button>
            </form>
            <form method="POST" action="{{ route('scheduler.whatsapp.test') }}" class="space-y-2"
                  @submit="if (integrationDirty || diagnosticBusy) { $event.preventDefault(); } else { diagnosticBusy = true; }">
                @csrf
                <label for="whatsapp_test_phone" class="block text-sm font-semibold text-mk-text">Nomor WhatsApp tujuan uji</label>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input id="whatsapp_test_phone" name="phone" type="tel" required maxlength="30" autocomplete="off"
                           value="{{ old('phone') }}" placeholder="08… atau 628…"
                           class="min-h-11 min-w-0 flex-1 rounded-lg border-mk-border text-sm focus:border-mk-accent focus:ring-mk-accent">
                    <button type="submit" :disabled="integrationDirty || diagnosticBusy"
                            class="btn-mk-primary min-h-11 rounded-lg px-4 text-sm font-semibold disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-mk-accent">
                        Kirim pesan uji
                    </button>
                </div>
                <p class="text-xs text-mk-muted">Mengirim satu pesan nyata ke nomor ini. Respons sukses API belum membuktikan pesan diterima atau dibaca.</p>
                @error('phone')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
            </form>
            <p x-show="diagnosticBusy" x-cloak role="status" class="text-sm text-mk-muted">Memproses permintaan. Jangan kirim ulang sebelum hasil diketahui.</p>
        </section>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('schedulerSettingsForm', (initial) => ({
                sessionsGenerateEnabled: initial.sessionsGenerateEnabled,
                invoicesGenerateEnabled: initial.invoicesGenerateEnabled,
                honorCalculateEnabled: initial.honorCalculateEnabled,
                finesApplyEnabled: initial.finesApplyEnabled,
                overdueCheckEnabled: initial.overdueCheckEnabled,
                invoiceReminderEnabled: initial.invoiceReminderEnabled,
                cutiReturnEnabled: initial.cutiReturnEnabled,
                staffPayrollEnabled: initial.staffPayrollEnabled,
                reminderEnabled: initial.reminderEnabled,
                reminderMode: initial.reminderMode,
                whatsappProvider: initial.whatsappProvider,
                integrationDirty: initial.integrationDirty,
                showGowaPassword: false,
                diagnosticBusy: false,
                fonnteTokenMasked: initial.fonnteTokenMasked ?? '',
                wablasTokenMasked: initial.wablasTokenMasked ?? '',
                wablasSecretMasked: initial.wablasSecretMasked ?? '',
                showFonnteToken: false,
                showWablasToken: false,
                showWablasSecret: false,

                toggleSecret(field) {
                    if (field === 'fonnte') {
                        this.showFonnteToken = !this.showFonnteToken;
                        const el = this.$refs.fonnteTokenInput;
                        if (this.showFonnteToken && this.fonnteTokenMasked) {
                            el.value = this.fonnteTokenMasked;
                        } else if (!this.showFonnteToken && el.value === this.fonnteTokenMasked) {
                            el.value = '';
                        }
                    } else if (field === 'wablasToken') {
                        this.showWablasToken = !this.showWablasToken;
                        const el = this.$refs.wablasTokenInput;
                        if (this.showWablasToken && this.wablasTokenMasked) {
                            el.value = this.wablasTokenMasked;
                        } else if (!this.showWablasToken && el.value === this.wablasTokenMasked) {
                            el.value = '';
                        }
                    } else if (field === 'wablasSecret') {
                        this.showWablasSecret = !this.showWablasSecret;
                        const el = this.$refs.wablasSecretInput;
                        if (this.showWablasSecret && this.wablasSecretMasked) {
                            el.value = this.wablasSecretMasked;
                        } else if (!this.showWablasSecret && el.value === this.wablasSecretMasked) {
                            el.value = '';
                        }
                    }
                },

                clearMaskedSecretsBeforeSubmit(event) {
                    const pairs = [
                        ['integration_fonnte_token', this.fonnteTokenMasked],
                        ['integration_wablas_token', this.wablasTokenMasked],
                        ['integration_wablas_secret_key', this.wablasSecretMasked],
                    ];

                    pairs.forEach(([name, masked]) => {
                        const el = event.target.elements.namedItem(name);
                        if (!el) return;
                        const val = el.value.trim();
                        if (val === '' || val === masked || val.startsWith('********') || val.startsWith('••••')) {
                            el.disabled = true;
                        }
                    });
                },
            }));
        });
    </script>
    @endpush
</x-app-layout>
