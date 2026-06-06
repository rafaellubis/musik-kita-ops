<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Template pesan WhatsApp (Master Data).
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

    public const CODE_SCHEDULE_REMINDER = 'SCHEDULE_REMINDER';

    public const CODE_SESSION_REPORT = 'SESSION_REPORT';

    /** Template aktif default untuk reminder tagihan. */
    public static function defaultInvoiceReminder(): ?self
    {
        return static::query()
            ->where('code', self::CODE_INVOICE_REMINDER)
            ->where('is_active', true)
            ->first();
    }

    /** Template aktif default untuk pengingat jadwal. */
    public static function defaultScheduleReminder(): ?self
    {
        return static::query()
            ->where('code', self::CODE_SCHEDULE_REMINDER)
            ->where('is_active', true)
            ->first();
    }

    /** Template aktif default untuk laporan sesi ke ortu. */
    public static function defaultSessionReport(): ?self
    {
        return static::query()
            ->where('code', self::CODE_SESSION_REPORT)
            ->where('is_active', true)
            ->first();
    }
}
