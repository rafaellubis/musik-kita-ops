<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionTeacherNote extends Model
{
    protected $fillable = [
        'class_session_id',
        'teacher_id',
        'material_learned',
        'homework_notes',
        'notes',
        'session_rating',
    ];

    protected $casts = [
        'session_rating' => 'integer',
    ];

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function isComplete(): bool
    {
        return collect([
            $this->material_learned,
            $this->homework_notes,
            $this->notes,
        ])->contains(fn (?string $value) => filled(trim((string) $value)));
    }
}
