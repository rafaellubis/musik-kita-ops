<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressReportSessionNote extends Model
{
    protected $fillable = [
        'progress_report_id',
        'class_session_id',
        'session_date',
        'notes',
        'material_learned',
        'homework_notes',
        'session_sequence',
        'session_rating',
        'sort_order',
    ];

    protected $casts = [
        'session_date'     => 'date',
        'session_sequence' => 'integer',
        'session_rating'   => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class, 'progress_report_id');
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }
}
