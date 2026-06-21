{{-- Baris ringkasan tagihan — dipakai print (browser) & pdf (DomPDF) --}}
<tr>
    <td>Total Tagihan</td>
    <td class="right">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</td>
</tr>
<tr class="summary-paid">
    <td>Sudah Dibayar</td>
    <td class="right">Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</td>
</tr>
<tr class="summary-total">
    <td>SISA TAGIHAN</td>
    <td class="right">Rp {{ number_format($invoice->balance, 0, ',', '.') }}</td>
</tr>
