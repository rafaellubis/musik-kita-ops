<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventTeacherAssignment extends Model
{
    use HasFactory;

    public const ATTENDANCE_PENDING = 'PENDING';
    public const ATTENDANCE_HADIR = 'HADIR';
    public const ATTENDANCE_TIDAK_HADIR = 'TIDAK_HADIR';

    protected $fillable = [
        'event_id', 'teacher_id', 'attendance_status',
        'confirmed_at', 'confirmed_by', 'absence_reason',
    ];

    protected $casts = ['confirmed_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class, 'active_teacher_assignment_id');
    }
}
