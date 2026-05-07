<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'invoice_component_id', 'added_by',
        'item_code', 'description', 'amount', 'metadata',
    ];

    protected $casts = [
        'amount'   => 'integer',
        'metadata' => 'array',
    ];

    /** Item manual: added_by tidak null. Item sistem: added_by null. */
    public function isManual(): bool
    {
        return $this->added_by !== null;
    }

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
}
