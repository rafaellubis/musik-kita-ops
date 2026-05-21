<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'invoice_component_id', 'added_by',
        'parent_item_id',
        'item_code', 'description', 'amount', 'metadata',
        'discount_type', 'discount_value', 'discount_reason',
    ];

    protected $casts = [
        'amount'         => 'integer',
        'metadata'       => 'array',
        'discount_value' => 'integer',
    ];

    // Tipe diskon
    public const DISCOUNT_TYPE_NOMINAL = 'NOMINAL';
    public const DISCOUNT_TYPE_PERCENT = 'PERCENT';

    // ============= HELPERS =============

    /** Item manual: added_by tidak null. Item sistem: added_by null. */
    public function isManual(): bool
    {
        return $this->added_by !== null;
    }

    /** Cek apakah item ini adalah item diskon (item_code = DISKON). */
    public function isDiscount(): bool
    {
        return $this->item_code === 'DISKON';
    }

    // ============= RELATIONSHIPS =============

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(InvoiceComponent::class, 'invoice_component_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'added_by');
    }

    /** Item induk yang didiskon oleh item ini (hanya berlaku jika isDiscount()). */
    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class, 'parent_item_id');
    }

    /** Item diskon yang terikat ke item ini (satu item maks 1 diskon). */
    public function discountItem(): HasOne
    {
        return $this->hasOne(InvoiceItem::class, 'parent_item_id');
    }
}
