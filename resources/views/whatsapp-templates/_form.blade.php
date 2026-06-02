<div class="space-y-4">
    <div>
        <label class="block text-sm font-medium text-mk-text mb-1">Kode</label>
        <input type="text" name="code" value="{{ old('code', isset($template) ? $template->code : '') }}"
               class="w-full rounded border-gray-300 font-mono text-sm"
               placeholder="INVOICE_REMINDER"
               @if(isset($template) && $template->code === \App\Models\WhatsappMessageTemplate::CODE_INVOICE_REMINDER) readonly @endif>
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
