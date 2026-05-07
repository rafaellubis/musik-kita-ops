<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl">Invoice {{ $invoice->invoice_number }}</h2>
            <a href="{{ route('invoices.index', ['year' => $invoice->year, 'month' => $invoice->month]) }}"
               class="text-sm text-gray-600 hover:underline">
                ← Kembali ke daftar
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
        $isOwner = auth()->user()?->hasRole('Owner');
        $canPay = $invoice->status !== 'PAID' && $invoice->status !== 'VOID';
    @endphp

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            {{-- ============= HEADER ============= --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6"
                 x-data="{ showPay: false }">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-mono text-sm text-gray-500">{{ $invoice->invoice_number }}</div>
                        <div class="text-2xl font-bold mt-1">{{ $invoice->description ?? 'Invoice' }}</div>
                        <div class="text-gray-600 text-sm">
                            Murid:
                            <a href="{{ route('students.show', $invoice->student_id) }}"
                               class="text-blue-600 hover:underline">
                                {{ $invoice->student->full_name ?? '?' }}
                            </a>
                            <span class="font-mono text-xs">{{ $invoice->student->student_code ?? '' }}</span>
                        </div>
                        <div class="mt-2">
                            <span class="px-3 py-1 rounded text-sm border {{ $statusColors[$invoice->status] }}">
                                {{ $invoice->status }}
                            </span>
                            <span class="ml-2 text-sm text-gray-600">
                                Jatuh tempo: {{ $invoice->due_date->format('d M Y') }}
                                @if($invoice->balance > 0 && $invoice->due_date->lt(now()))
                                    <span class="text-orange-600">
                                        (lewat {{ $invoice->due_date->diffInDays(now()) }} hari)
                                    </span>
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <a href="{{ route('invoices.print', $invoice->id) }}" target="_blank"
                           class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                            🖨 Cetak Invoice
                        </a>
                        @if($canPay)
                            <button type="button" @click="showPay = !showPay"
                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded">
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
                                    <input type="number" name="amount" required
                                           min="1" max="99999999"
                                           value="{{ old('amount', $invoice->balance) }}"
                                           class="mt-1 block w-full border-gray-300 rounded">
                                    <p class="text-xs text-gray-500 mt-1">
                                        Saldo saat ini: Rp {{ number_format($invoice->balance, 0, ',', '.') }}
                                    </p>
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
                                    class="mt-3 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm">
                                Simpan Pembayaran
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            {{-- ============= ITEMS ============= --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium mb-3">Item Tagihan</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs text-gray-500 uppercase">
                            <th class="py-1">Kode</th>
                            <th class="py-1">Deskripsi</th>
                            <th class="py-1 text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                            <tr class="border-b">
                                <td class="py-2 font-mono text-xs">{{ $item->item_code }}</td>
                                <td class="py-2">{{ $item->description }}</td>
                                <td class="py-2 text-right">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        <tr class="font-bold">
                            <td colspan="2" class="py-2 text-right">Total</td>
                            <td class="py-2 text-right">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- ============= PAYMENTS ============= --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium mb-3">Riwayat Pembayaran</h3>

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
    </div>
    <style>[x-cloak] { display: none !important; }</style>
</x-app-layout>
