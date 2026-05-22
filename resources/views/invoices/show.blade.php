<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-mk-text">{{ $invoice->invoice_number }}</h2>
                <div class="text-xs text-mk-muted mt-0.5">
                    {{ $invoice->student->full_name ?? '?' }} ·
                    {{ \Carbon\Carbon::create($invoice->year, $invoice->month, 1)->format('F Y') }}
                </div>
            </div>
            <a href="{{ route('invoices.index', ['year' => $invoice->year, 'month' => $invoice->month]) }}"
               class="text-sm text-mk-muted hover:text-mk-text transition-colors">
                ← Kembali
            </a>
        </div>
    </x-slot>

    @php
        $statusColors = [
            'UNPAID'  => 'bg-red-100 text-red-700 border-red-300',
            'PARTIAL' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
            'PAID'    => 'bg-green-100 text-green-700 border-green-300',
            'VOID'    => 'bg-gray-100 text-gray-500 border-gray-300',
        ];
        $isOwner      = auth()->user()?->hasRole('Owner');
        $canPay       = $invoice->status !== 'PAID' && $invoice->status !== 'VOID';
        // Item manual bisa ditambah/dihapus selama invoice belum PAID/VOID
        $canEditItems = in_array($invoice->status, ['UNPAID', 'PARTIAL']);
        // Diskon: boleh selama UNPAID atau PARTIAL (sama dengan canEditItems)
        $canDiscount = $canEditItems;
        // Semua invoice harus dibayar penuh — field amount selalu di-lock = saldo.
        // Untuk KIDS_CLASS_BUNDLE, "cicilan" berarti 3 invoice terpisah yang masing-masing lunas.
        $lockAmount = true;
    @endphp

    <div class="py-6 px-4 lg:px-8 space-y-4">

        @if(session('success'))
        <div class="p-3 rounded-lg text-sm"
             style="background:rgba(52,211,153,0.1);color:#34D399;border:1px solid rgba(52,211,153,0.2)">
            {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="p-3 rounded-lg text-sm"
             style="background:rgba(248,113,113,0.1);color:#F87171;border:1px solid rgba(248,113,113,0.2)">
            {{ session('error') }}
        </div>
        @endif

        {{-- ============= HEADER INVOICE ============= --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6"
             x-data="{ showPay: false }">
            <div class="flex justify-between items-start">
                <div>
                    <div class="font-mono text-sm text-gray-500">{{ $invoice->invoice_number }}</div>
                    <div class="text-2xl font-bold mt-1">{{ $invoice->student->full_name ?? '?' }}</div>
                    <div class="text-gray-600 text-sm">
                        <a href="{{ route('students.show', $invoice->student_id) }}"
                           class="text-blue-600 hover:underline">
                            {{ $invoice->student->student_code ?? '' }}
                        </a>
                    </div>
                    <div class="mt-2 flex items-center gap-2 flex-wrap">
                        <span class="px-3 py-1 rounded text-sm border {{ $statusColors[$invoice->status] }}">
                            {{ $invoice->status }}
                        </span>
                        @if($invoice->installment_label)
                            <span class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-700 border border-blue-300">
                                {{ $invoice->installment_label }}
                            </span>
                        @elseif($invoice->class_type === 'KIDS_CLASS_BUNDLE' && $invoice->payment_mode === 'FULL')
                            <span class="px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-700 border border-purple-300">
                                Kids Bundle – Lunas
                            </span>
                        @endif
                        <span class="text-sm text-gray-600">
                            Jatuh tempo: {{ $invoice->due_date->format('d M Y') }}
                            @if($invoice->balance > 0 && $invoice->due_date->lt(now()))
                                <span class="text-orange-600">
                                    (Telat {{ (int) $invoice->due_date->diffInDays(now()) }} hari)
                                </span>
                            @endif
                        </span>
                    </div>
                </div>

                <div class="flex gap-2 flex-wrap justify-end">
                    <a href="{{ route('invoices.print', $invoice->id) }}" target="_blank"
                       class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                        Cetak Invoice
                    </a>
                    @if($canPay)
                        <button type="button" @click="showPay = !showPay"
                                class="px-4 py-2 rounded text-sm font-bold"
                                style="background:#16A34A;color:#fff">
                            Catat Pembayaran
                        </button>
                    @endif
                </div>
            </div>

            {{-- ===== Total summary ===== --}}
            <div class="mt-4 grid grid-cols-3 gap-4 text-sm border-t pt-3">
                <div>
                    <div class="text-gray-500 text-xs">Total Tagihan</div>
                    <div class="text-lg font-bold">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-gray-500 text-xs">Sudah Dibayar</div>
                    <div class="text-lg font-bold text-green-600">Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-gray-500 text-xs">Saldo</div>
                    <div class="text-lg font-bold {{ $invoice->balance > 0 ? 'text-red-600' : 'text-green-600' }}">
                        Rp {{ number_format($invoice->balance, 0, ',', '.') }}
                    </div>
                </div>
            </div>

            {{-- ===== Form catat pembayaran ===== --}}
            @if($canPay)
                <div x-show="showPay" x-cloak
                     class="mt-4 p-4 border border-green-200 bg-green-50 rounded">
                    <form method="POST" action="{{ route('payments.store', $invoice->id) }}"
                          enctype="multipart/form-data">
                        @csrf
                        <h4 class="font-medium mb-3">Catat Pembayaran Baru</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div>
                                <label class="block">Jumlah <span class="text-red-500">*</span></label>
                                @if($lockAmount)
                                    {{-- Non-KIDS_CLASS_BUNDLE: harus bayar penuh, field di-lock --}}
                                    <input type="number" name="amount" required
                                           min="{{ $invoice->balance }}" max="{{ $invoice->balance }}"
                                           value="{{ $invoice->balance }}"
                                           readonly
                                           class="mt-1 block w-full border-gray-300 rounded bg-gray-50 cursor-not-allowed">
                                    <p class="text-xs text-gray-500 mt-1">
                                        Harus dilunasi penuh: Rp {{ number_format($invoice->balance, 0, ',', '.') }}
                                    </p>
                                @endif
                            </div>
                            <div>
                                <label class="block">Metode <span class="text-red-500">*</span></label>
                                <select name="method" required
                                        class="mt-1 block w-full border-gray-300 rounded">
                                    @foreach(\App\Models\Payment::METHODS as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block">Tanggal Pembayaran <span class="text-red-500">*</span></label>
                                <input type="date" name="payment_date" required
                                       value="{{ old('payment_date', now()->toDateString()) }}"
                                       max="{{ now()->toDateString() }}"
                                       class="mt-1 block w-full border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block">Bukti Pembayaran (opsional)</label>
                                <input type="file" name="proof_image" accept="image/*"
                                       class="mt-1 block w-full text-sm">
                                <p class="text-xs text-gray-500 mt-1">Maks 2 MB, JPG/PNG.</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block">Catatan</label>
                                <textarea name="notes" rows="2" maxlength="500"
                                          class="mt-1 block w-full border-gray-300 rounded"></textarea>
                            </div>
                        </div>
                        <button type="submit"
                                class="mt-3 px-4 py-2 rounded text-sm font-bold"
                                style="background:#16A34A;color:#fff">
                            Simpan Pembayaran
                        </button>
                    </form>
                </div>
            @endif
        </div>

        {{-- ============= ITEMS ============= --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6"
             x-data="{ showAddItem: false }">

            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-medium text-gray-700">Item Tagihan</h3>
                @hasanyrole('Owner|Admin')
                    @if($canEditItems && $catalogItems->isNotEmpty())
                        <button type="button" @click="showAddItem = !showAddItem"
                                class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
                            + Tambah Item Manual
                        </button>
                    @endif
                @endhasanyrole
            </div>

            {{-- ===== Form tambah item manual ===== --}}
            @hasanyrole('Owner|Admin')
                @if($canEditItems && $catalogItems->isNotEmpty())
                    <div x-show="showAddItem" x-cloak
                         class="mb-4 p-4 border border-indigo-200 bg-indigo-50 rounded"
                         x-data="{
                            selectedId: '',
                            defaultPrice: 0,
                            defaultName: '',
                            selectComponent(id, price, name) {
                                this.selectedId = id;
                                this.defaultPrice = price;
                                this.defaultName = name;
                                this.$refs.amount.value = price;
                                this.$refs.description.value = name;
                            }
                         }">
                        <form method="POST"
                              action="{{ route('invoice-items.store', $invoice->id) }}">
                            @csrf
                            <h4 class="font-medium text-sm mb-3">Pilih Item dari Katalog</h4>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                                <div>
                                    <label class="block text-xs font-medium mb-1">
                                        Item <span class="text-red-500">*</span>
                                    </label>
                                    <select name="invoice_component_id" required
                                            class="block w-full border-gray-300 rounded"
                                            @change="selectComponent($event.target.value,
                                                $event.target.selectedOptions[0]?.dataset.price,
                                                $event.target.selectedOptions[0]?.dataset.name)">
                                        <option value="">— Pilih item —</option>
                                        @foreach($catalogItems as $cat)
                                            <option value="{{ $cat->id }}"
                                                    data-price="{{ $cat->default_price }}"
                                                    data-name="{{ $cat->name }}"
                                                    {{ old('invoice_component_id') == $cat->id ? 'selected' : '' }}>
                                                {{ $cat->code }} — {{ $cat->name }}
                                                (Rp {{ number_format($cat->default_price, 0, ',', '.') }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">
                                        Deskripsi di Invoice <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="description" required maxlength="255"
                                           x-ref="description"
                                           value="{{ old('description') }}"
                                           class="block w-full border-gray-300 rounded"
                                           placeholder="Nama item di invoice">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1">
                                        Jumlah (Rp) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" name="amount" required
                                           min="1" max="99999999"
                                           x-ref="amount"
                                           value="{{ old('amount') }}"
                                           class="block w-full border-gray-300 rounded"
                                           placeholder="100000">
                                    <p class="text-xs text-gray-400 mt-1">
                                        Pre-fill dari harga default, bisa diubah.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3 flex gap-2">
                                <button type="submit"
                                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-sm">
                                    Tambahkan ke Invoice
                                </button>
                                <button type="button" @click="showAddItem = false"
                                        class="px-4 py-2 text-gray-600 hover:text-gray-800 text-sm">
                                    Batal
                                </button>
                            </div>
                        </form>
                    </div>
                @elseif($canEditItems && $catalogItems->isEmpty())
                    <div class="mb-3 p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-700">
                        Belum ada katalog item manual.
                        @role('Owner')
                            <a href="{{ route('invoice-components.create') }}" class="underline">
                                Tambah katalog di sini.
                            </a>
                        @else
                            Minta Owner untuk menambahkan item di menu Katalog Item Tagihan.
                        @endrole
                    </div>
                @endif
            @endhasanyrole

            {{-- ===== Tabel item ===== --}}
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-xs text-gray-500 uppercase">
                        <th class="py-1">Kode</th>
                        <th class="py-1">Deskripsi</th>
                        <th class="py-1 text-right">Jumlah</th>
                        <th class="py-1 text-center">Tipe</th>
                        @hasanyrole('Owner|Admin')
                            @if($canEditItems)
                                <th class="py-1 text-right">Aksi</th>
                            @endif
                        @endhasanyrole
                    </tr>
                </thead>
                @foreach($invoice->items as $item)
                <tbody x-data="{
                    showDiscount: false,
                    type: '{{ $item->discountItem?->discount_type ?? 'NOMINAL' }}',
                    value: '{{ $item->discountItem?->discount_value ?? '' }}',
                    itemAmount: {{ $item->amount }},
                    isDenda: {{ $item->item_code === 'DENDA' ? 'true' : 'false' }},
                    get preview() {
                        const v = parseInt(this.value) || 0;
                        if (v <= 0) return 0;
                        if (this.type === 'PERCENT') return Math.floor(this.itemAmount * v / 100);
                        return v;
                    },
                    get previewFormatted() {
                        return this.preview.toLocaleString('id-ID');
                    }
                }">
                    {{-- ===== Baris item induk ===== --}}
                    <tr class="border-b">
                        <td class="py-2 font-mono text-xs">{{ $item->item_code }}</td>
                        <td class="py-2">
                            {{ $item->description }}
                            @if($item->isManual() && $item->addedBy)
                                <div class="text-xs text-gray-400">+ oleh {{ $item->addedBy->name }}</div>
                            @endif
                        </td>
                        <td class="py-2 text-right">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                        <td class="py-2 text-center">
                            @if($item->isManual())
                                <span class="px-2 py-0.5 rounded text-xs bg-indigo-100 text-indigo-700">Manual</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">Sistem</span>
                            @endif
                        </td>
                        @hasanyrole('Owner|Admin')
                            @if($canEditItems)
                                <td class="py-2 text-right space-x-2 whitespace-nowrap">
                                    @if($item->isManual())
                                        <form method="POST"
                                              action="{{ route('invoice-items.destroy', $item->id) }}"
                                              class="inline"
                                              onsubmit="return confirm('Hapus item {{ $item->item_code }} dari invoice ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Hapus</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-300">—</span>
                                    @endif
                                    @if($canDiscount)
                                        <button type="button"
                                                @click="showDiscount = !showDiscount"
                                                class="text-xs text-amber-700 hover:underline">
                                            {{ $item->discountItem ? 'Edit Diskon' : 'Beri Diskon' }}
                                        </button>
                                    @endif
                                </td>
                            @endif
                        @endhasanyrole
                    </tr>

                    {{-- ===== Form beri/edit diskon (inline expand, Alpine.js) ===== --}}
                    @hasanyrole('Owner|Admin')
                        @if($canDiscount)
                            <tr x-show="showDiscount" x-cloak>
                                <td colspan="{{ $canEditItems ? 5 : 4 }}"
                                    class="py-3 px-4 bg-amber-50 border-b border-amber-200">
                                    <form method="POST"
                                          action="{{ route('invoice-items.discount.store', $item->id) }}">
                                        @csrf
                                        <p class="text-xs font-semibold text-amber-800 mb-2">
                                            Beri Diskon untuk: <span class="font-normal">{{ $item->description }}</span>
                                        </p>
                                        <div class="flex gap-3 items-end flex-wrap">
                                            <div class="shrink-0">
                                                <label class="block text-xs font-medium text-gray-700 mb-1">Tipe</label>
                                                <select name="discount_type" x-model="type"
                                                        class="block w-36 border-gray-300 rounded text-sm">
                                                    <option value="NOMINAL">Nominal (Rp)</option>
                                                    <option value="PERCENT">Persentase (%)</option>
                                                </select>
                                            </div>
                                            <div class="shrink-0">
                                                <label class="block text-xs font-medium text-gray-700 mb-1">
                                                    Nilai <span class="text-red-500">*</span>
                                                </label>
                                                <input type="number" name="discount_value"
                                                       x-model="value"
                                                       min="1"
                                                       :max="type === 'PERCENT' ? (isDenda ? 100 : 90) : (isDenda ? itemAmount : itemAmount - 1)"
                                                       required
                                                       class="block w-28 border-gray-300 rounded text-sm"
                                                       placeholder="0">
                                            </div>
                                            <div class="flex-1 min-w-[180px]">
                                                <label class="block text-xs font-medium text-gray-700 mb-1">
                                                    Alasan <span class="text-red-500">*</span>
                                                </label>
                                                <input type="text" name="discount_reason"
                                                       required minlength="3" maxlength="500"
                                                       value="{{ $item->discountItem?->discount_reason }}"
                                                       class="block w-full border-gray-300 rounded text-sm"
                                                       placeholder="Mis: Diskon ulang tahun, promosi bulan ini...">
                                            </div>
                                            <div class="flex gap-2 shrink-0">
                                                <button type="submit"
                                                        class="px-3 py-1.5 rounded text-sm font-medium whitespace-nowrap"
                                                        style="background:rgba(212,168,83,0.9);color:#1A1000">
                                                    Simpan Diskon
                                                </button>
                                                <button type="button"
                                                        @click="showDiscount = false"
                                                        class="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 whitespace-nowrap">
                                                    Batal
                                                </button>
                                            </div>
                                        </div>
                                        <p class="text-xs text-amber-700 mt-2">
                                            Preview diskon: –Rp <span x-text="previewFormatted">0</span>
                                        </p>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endhasanyrole

                    {{-- ===== Sub-baris diskon (jika ada) ===== --}}
                    @if($item->discountItem)
                        <tr class="border-b">
                            <td class="py-1.5 pl-6 font-mono text-xs">
                                <span class="px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-800">DISKON</span>
                            </td>
                            <td class="py-1.5 text-xs text-gray-600">
                                ↳ {{ $item->discountItem->discount_reason }}
                                @if($item->discountItem->discount_type === 'PERCENT')
                                    <span class="text-gray-400">({{ $item->discountItem->discount_value }}%)</span>
                                @endif
                            </td>
                            <td class="py-1.5 text-right text-red-600 text-xs font-medium">
                                –Rp {{ number_format(abs($item->discountItem->amount), 0, ',', '.') }}
                            </td>
                            <td class="py-1.5 text-center">
                                <span class="px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-700">Diskon</span>
                            </td>
                            @hasanyrole('Owner|Admin')
                                @if($canDiscount)
                                    <td class="py-1.5 text-right">
                                        <form method="POST"
                                              action="{{ route('invoice-items.discount.destroy', $item->id) }}"
                                              class="inline"
                                              onsubmit="return confirm('Hapus diskon dari item {{ $item->item_code }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">
                                                Hapus Diskon
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            @endhasanyrole
                        </tr>
                    @endif
                </tbody>
                @endforeach

                {{-- Baris total — pakai total_amount dari DB (sudah termasuk diskon) --}}
                <tbody>
                    <tr class="font-bold border-t-2">
                        <td colspan="2" class="py-2 text-right text-gray-700">Total</td>
                        <td class="py-2 text-right">
                            Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}
                        </td>
                        <td colspan="{{ $canEditItems ? 2 : 1 }}"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ============= PAYMENTS ============= --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-700 mb-3">Riwayat Pembayaran</h3>

            @if($invoice->payments->isEmpty())
                <p class="text-sm text-gray-500">Belum ada pembayaran.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs text-gray-500 uppercase">
                            <th class="py-1">Tanggal</th>
                            <th class="py-1">No. Kuitansi</th>
                            <th class="py-1">Metode</th>
                            <th class="py-1 text-right">Jumlah</th>
                            <th class="py-1">Bukti</th>
                            <th class="py-1">Catatan</th>
                            <th class="py-1 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->payments as $p)
                            <tr class="border-b {{ $p->is_voided ? 'opacity-50 line-through' : '' }}">
                                <td class="py-2">{{ $p->payment_date->format('d M Y') }}</td>
                                <td class="py-2 font-mono text-xs">{{ $p->receipt_number }}</td>
                                <td class="py-2">{{ $p->method }}</td>
                                <td class="py-2 text-right">Rp {{ number_format($p->amount, 0, ',', '.') }}</td>
                                <td class="py-2">
                                    @if($p->proof_image)
                                        <a href="{{ asset('storage/'.$p->proof_image) }}" target="_blank"
                                           class="text-blue-600 hover:underline text-xs">Lihat</a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="py-2 text-xs">
                                    @if($p->is_voided)
                                        <span class="text-red-600">
                                            VOID oleh {{ $p->voidedBy->name ?? '?' }} ·
                                            {{ $p->voided_reason }}
                                        </span>
                                    @else
                                        {{ $p->notes ?? '—' }}
                                        @if($p->createdBy)
                                            <div class="text-gray-400">
                                                dicatat oleh {{ $p->createdBy->name }}
                                            </div>
                                        @endif
                                    @endif
                                </td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    <a href="{{ route('payments.receipt', $p->id) }}" target="_blank"
                                       class="text-xs text-blue-600 hover:underline">
                                        Kuitansi
                                    </a>
                                    @if(!$p->is_voided && $isOwner)
                                        ·
                                        <button type="button"
                                                onclick="document.getElementById('void-form-{{ $p->id }}').style.display = 'block'"
                                                class="text-xs text-red-600 hover:underline">
                                            Void
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @if(!$p->is_voided && $isOwner)
                                <tr id="void-form-{{ $p->id }}" style="display:none">
                                    <td colspan="7" class="py-2 px-3 bg-red-50">
                                        <form method="POST" action="{{ route('payments.void', $p->id) }}"
                                              onsubmit="return confirm('Void pembayaran {{ $p->receipt_number }}? Status invoice akan di-recalc.')">
                                            @csrf
                                            <label class="block text-xs">Alasan Void <span class="text-red-500">*</span></label>
                                            <input type="text" name="reason" required maxlength="500"
                                                   class="mt-1 block w-full border-gray-300 rounded text-sm"
                                                   placeholder="Mis: pembayaran double, dibatalkan murid">
                                            <button type="submit"
                                                    class="mt-2 px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs">
                                                Konfirmasi Void
                                            </button>
                                            <button type="button"
                                                    onclick="document.getElementById('void-form-{{ $p->id }}').style.display = 'none'"
                                                    class="text-xs text-gray-600 ml-2">Batal</button>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!$isOwner)
                <p class="mt-3 text-xs text-gray-500">
                    Void pembayaran hanya bisa dilakukan oleh role Owner (BR-5.18).
                </p>
            @endif
        </div>

    </div>
    <style>[x-cloak] { display: none !important; }</style>
</x-app-layout>
