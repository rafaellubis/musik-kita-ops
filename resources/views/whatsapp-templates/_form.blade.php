<div class="space-y-4">
    <div>
        <label class="block text-sm font-medium text-mk-text mb-1">Kode</label>
        <input type="text" name="code" value="{{ old('code', isset($template) ? $template->code : '') }}"
               class="w-full rounded border-gray-300 font-mono text-sm"
               placeholder="INVOICE_REMINDER"
               @if(isset($template) && in_array($template->code, [
                   \App\Models\WhatsappMessageTemplate::CODE_INVOICE_REMINDER,
                   \App\Models\WhatsappMessageTemplate::CODE_SCHEDULE_REMINDER,
                   \App\Models\WhatsappMessageTemplate::CODE_SESSION_REPORT,
                   \App\Models\WhatsappMessageTemplate::CODE_SESSION_REPORT_STUDENT,
               ], true)) readonly @endif>
        @error('code')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-mk-text mb-1">Nama Tampilan</label>
        <input type="text" name="name" value="{{ old('name', isset($template) ? $template->name : '') }}"
               class="w-full rounded border-gray-300 text-sm">
        @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-mk-text mb-1">Isi Pesan</label>
        <textarea name="body" rows="14"
                  class="w-full rounded border-gray-300 text-sm font-mono">{{ old('body', isset($template) ? $template->body : '') }}</textarea>
        @error('body')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>

    @if(isset($template) && $template->isSessionReportTemplate())
        @php
            $encLines = old('encouragement_lines', $template->encouragement_lines ?? \App\Models\WhatsappMessageTemplate::defaultEncouragementLines($template->code));
            $encLabels = [
                'rating_5' => 'Rating 5/5',
                'rating_4' => 'Rating 4/5',
                'rating_3' => 'Rating 3/5',
                'rating_2' => 'Rating 2/5',
                'rating_1' => 'Rating 1/5',
                'default'  => 'Rating tidak dipilih',
            ];
        @endphp
        <div class="border border-gray-200 rounded-lg p-4 space-y-3 bg-gray-50">
            <div>
                <h3 class="text-sm font-semibold text-mk-text">Pesan Semangat ({pesan_semangat})</h3>
                <p class="text-xs text-mk-muted mt-1">
                    Teks statis — sama untuk semua murid. Mengisi placeholder <code>{pesan_semangat}</code> di template di atas.
                </p>
            </div>
            @foreach($encLabels as $key => $label)
                <div>
                    <label class="block text-xs font-medium text-mk-text mb-1">{{ $label }}</label>
                    <textarea name="encouragement_lines[{{ $key }}]" rows="2" maxlength="500"
                              class="w-full rounded border-gray-300 text-sm">{{ $encLines[$key] ?? '' }}</textarea>
                    @error("encouragement_lines.{$key}")<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
    @endif

    <div>
        <label class="block text-sm font-medium text-mk-text mb-1">Urutan Tampil</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', isset($template) ? $template->sort_order : 0) }}"
               min="0" max="999" class="w-24 rounded border-gray-300 text-sm">
        @error('sort_order')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1"
               @checked(old('is_active', isset($template) ? $template->is_active : true))
               class="rounded border-gray-300">
        Aktif
    </label>
</div>
