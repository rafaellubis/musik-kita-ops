<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-mk-text">Edit Event</h1>
                <p class="mt-1 text-sm text-mk-muted">{{ $event->event_number }} · hanya dapat diubah selama Draft.</p>
            </div>
            <a href="{{ route('events.show', $event) }}" class="text-sm font-semibold text-mk-muted hover:text-mk-text">Kembali</a>
        </div>
    </x-slot>

    <div class="px-4 py-6 lg:px-8">
        <form method="POST" action="{{ route('events.update', $event) }}"
              class="max-w-2xl rounded-lg border border-mk-borderLight bg-mk-card p-5 sm:p-6">
            @csrf
            @method('PATCH')

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="block text-sm font-semibold text-mk-text">Nama event <span class="text-red-700">*</span></label>
                    <input id="name" type="text" name="name" required maxlength="100" value="{{ old('name', $event->name) }}"
                           class="mt-1.5 block w-full rounded-md @error('name') !border-red-600 @enderror">
                    @error('name')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="type" class="block text-sm font-semibold text-mk-text">Tipe event <span class="text-red-700">*</span></label>
                    <select id="type" name="type" required class="mt-1.5 block w-full rounded-md @error('type') !border-red-600 @enderror">
                        <option value="MINI_CONCERT" @selected(old('type', $event->type) === 'MINI_CONCERT')>Mini Concert</option>
                        <option value="UJIAN_GRADE" disabled>Ujian Grade — belum tersedia</option>
                    </select>
                    <p class="mt-1.5 text-sm text-mk-muted">Fase ini hanya mendukung Mini Concert.</p>
                    @error('type')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="event_date" class="block text-sm font-semibold text-mk-text">Tanggal event <span class="text-red-700">*</span></label>
                    <input id="event_date" type="date" name="event_date" required
                           value="{{ old('event_date', $event->event_date->format('Y-m-d')) }}"
                           class="mt-1.5 block w-full rounded-md @error('event_date') !border-red-600 @enderror">
                    @error('event_date')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="payment_due_date" class="block text-sm font-semibold text-mk-text">Batas pembayaran <span class="text-red-700">*</span></label>
                    <input id="payment_due_date" type="date" name="payment_due_date" required
                           value="{{ old('payment_due_date', $event->payment_due_date->format('Y-m-d')) }}"
                           class="mt-1.5 block w-full rounded-md @error('payment_due_date') !border-red-600 @enderror">
                    <p class="mt-1.5 text-sm text-mk-muted">Tidak boleh melewati tanggal event.</p>
                    @error('payment_due_date')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="notes" class="block text-sm font-semibold text-mk-text">Catatan</label>
                    <textarea id="notes" name="notes" rows="4" maxlength="1000"
                              class="mt-1.5 block w-full rounded-md @error('notes') !border-red-600 @enderror">{{ old('notes', $event->notes) }}</textarea>
                    @error('notes')<p class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-mk-borderLight pt-5">
                <a href="{{ route('events.show', $event) }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-mk-muted hover:bg-mk-surface hover:text-mk-text">Batal</a>
                <button type="submit" class="btn-mk-primary rounded-lg px-4 py-2 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-mk-accent focus:ring-offset-2">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</x-app-layout>
