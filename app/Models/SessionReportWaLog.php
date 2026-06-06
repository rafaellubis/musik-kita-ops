<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionReportWaLog extends Model
{
    public const STATUS_SUCCESS = 'SUCCESS';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_SKIPPED = 'SKIPPED';

    protected $fillable = [
        'class_session_id',
        'student_id',
        'phone',
        'message_body',
        'provider',
        'provider_message_ids',
        'status',
        'is_update',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'provider_message_ids' => 'array',
        'is_update'            => 'boolean',
        'sent_at'              => 'datetime',
    ];

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
