<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log pengiriman pengingat jadwal kelas via Fonnte.
 */
class ScheduleReminderLog extends Model
{
    public const STATUS_SUCCESS = 'SUCCESS';

    public const STATUS_FAILED = 'FAILED';

    public const MODE_DAY_BEFORE = 'day_before';

    public const MODE_SAME_DAY = 'same_day';

    public const MODE_HOURS_BEFORE = 'hours_before';

    protected $fillable = [
        'student_id',
        'target_date',
        'reminder_mode',
        'class_session_ids',
        'provider',
        'phone',
        'message_body',
        'provider_message_ids',
        'status',
        'error_message',
        'sent_by',
        'sent_at',
    ];

    protected $casts = [
        'target_date'            => 'date',
        'class_session_ids'      => 'array',
        'provider_message_ids'   => 'array',
        'sent_at'                => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
