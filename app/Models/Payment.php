<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pembayaran terhadap invoice (M05).
 *
 * Append-only secara semantik: row tidak pernah dihapus, tapi bisa di-VOID
 * (set voided_at). Saat void, paid_amount invoice di-recalc tanpa payment ini.
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number', 'invoice_id',
        'amount', 'method', 'payment_date',
        'proof_image', 'notes',
        'voided_at', 'voided_by', 'voided_reason',
        'created_by',
    ];

    protected $casts = [
        'amount'        => 'integer',
        'payment_date'  => 'date',
        'voided_at'     => 'datetime',
    ];

    public const METHOD_CASH     = 'CASH';
    public const METHOD_TRANSFER = 'TRANSFER';
    public const METHOD_QRIS     = 'QRIS';
    public const METHOD_DEBIT    = 'DEBIT';

    /**
     * Daftar semua metode valid + label tampil. Dipakai untuk dropdown
     * dan validasi di service/controller — tidak ada hardcoded list lain.
     */
    public const METHODS = [
        self::METHOD_CASH     => 'CASH (Tunai)',
        self::METHOD_TRANSFER => 'TRANSFER (Bank)',
        self::METHOD_QRIS     => 'QRIS (QR Code)',
        self::METHOD_DEBIT    => 'DEBIT (Kartu)',
    ];

    public function getIsVoidedAttribute(): bool
    {
        return !is_null($this->voided_at);
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    // ============= RELATIONSHIPS =============

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
