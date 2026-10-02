<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTeacherReassignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'event_participant_id', 'from_teacher_id', 'to_teacher_id',
        'reason', 'changed_by', 'changed_at',
    ];

    protected $casts = ['changed_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class, 'event_participant_id');
    }

    public function fromTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'from_teacher_id');
    }

    public function toTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'to_teacher_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
