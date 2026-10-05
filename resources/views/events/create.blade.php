<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-mk-text">Buat Event</h1>
                <p class="mt-1 text-sm text-mk-muted">Siapkan identitas dan tenggat pembayaran Mini Concert.</p>
            </div>
            <a href="{{ route('events.index') }}" class="text-sm font-semibold text-mk-muted hover:text-mk-text">Kembali</a>
        </div>
    </x-slot>

    <div class="px-4 py-6 lg:px-8">
        <form method="POST" action="{{ route('events.store') }}"
              class="max-w-2xl rounded-lg border border-mk-borderLight bg-mk-card p-5 sm:p-6">
            @csrf

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="block text-sm font-semibold text-mk-text">Nama event <span class="text-red-700">*</span></label>
                    <input id="name" type="text" name="name" required maxlength="100" value="{{ old('name') }}"
                           placeholder="Mini Concert Musik KITA Akhir Tahun"
                           class="mt-1.5 block w-full rounded-md @error('name') !border-red-600 @enderror">
                    @error('name')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="type" class="block text-sm font-semibold text-mk-text">Tipe event <span class="text-red-700">*</span></label>
                    <select id="type" name="type" required class="mt-1.5 block w-full rounded-md @error('type') !border-red-600 @enderror">
                        <option value="MINI_CONCERT" @selected(old('type', 'MINI_CONCERT') === 'MINI_CONCERT')>Mini Concert</option>
                        <option value="UJIAN_GRADE" disabled>Ujian Kenaikan Grade — segera hadir</option>
                    </select>
                    <p class="mt-1.5 text-sm text-mk-muted">Fase ini hanya mendukung Mini Concert.</p>
                    @error('type')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="event_date" class="block text-sm font-semibold text-mk-text">Tanggal event <span class="text-red-700">*</span></label>
                    <input id="event_date" type="date" name="event_date" required value="{{ old('event_date') }}"
                           class="mt-1.5 block w-full rounded-md @error('event_date') !border-red-600 @enderror">
                    @error('event_date')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="payment_due_date" class="block text-sm font-semibold text-mk-text">Batas pembayaran <span class="text-red-700">*</span></label>
                    <input id="payment_due_date" type="date" name="payment_due_date" required value="{{ old('payment_due_date') }}"
                           class="mt-1.5 block w-full rounded-md @error('payment_due_date') !border-red-600 @enderror">
                    <p class="mt-1.5 text-sm text-mk-muted">Tidak boleh melewati tanggal event.</p>
                    @error('payment_due_date')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="notes" class="block text-sm font-semibold text-mk-text">Catatan</label>
                    <textarea id="notes" name="notes" rows="4" maxlength="1000"
                              class="mt-1.5 block w-full rounded-md @error('notes') !border-red-600 @enderror"
                              placeholder="Informasi lokasi, tema, atau kebutuhan operasional">{{ old('notes') }}</textarea>
                    @error('notes')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-mk-borderLight pt-5">
                <a href="{{ route('events.index') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-mk-muted hover:bg-mk-surface hover:text-mk-text">Batal</a>
                <button type="submit" class="btn-mk-primary rounded-lg px-4 py-2 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-mk-accent focus:ring-offset-2">Buat Event</button>
            </div>
        </form>
    </div>
</x-app-layout>
