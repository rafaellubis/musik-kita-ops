{{-- Baris tabel rincian tagihan — dipakai print (browser) & pdf (DomPDF) --}}
@foreach($invoice->items as $item)
    <tr>
        <td class="code">{{ $item->item_code }}</td>
        <td>
            {{ $item->description }}
            @if(isset($item->metadata['notes']))
                <div class="item-note">Notes: {!! nl2br(e($item->metadata['notes'])) !!}</div>
            @endif
        </td>
        <td class="right">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
    </tr>
    @if($item->discountItem)
        <tr class="item-discount">
            <td class="code">DISKON</td>
            <td>
                ↳ {{ $item->discountItem->discount_reason }}
                @if($item->discountItem->discount_type === 'PERCENT')
                    ({{ $item->discountItem->discount_value }}%)
                @endif
            </td>
            <td class="right">–Rp {{ number_format(abs($item->discountItem->amount), 0, ',', '.') }}</td>
        </tr>
    @endif
@endforeach
