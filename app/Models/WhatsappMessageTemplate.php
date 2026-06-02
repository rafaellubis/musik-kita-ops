<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Template pesan WhatsApp untuk reminder tagihan (Master Data).
 */
class WhatsappMessageTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'body',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public const CODE_INVOICE_REMINDER = 'INVOICE_REMINDER';

    /** Template aktif default untuk reminder tagihan. */
    public static function defaultInvoiceReminder(): ?self
    {
        return static::query()
            ->where('code', self::CODE_INVOICE_REMINDER)
            ->where('is_active', true)
            ->first();
    }
}
