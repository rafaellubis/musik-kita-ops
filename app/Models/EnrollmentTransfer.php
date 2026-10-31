<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentTransfer extends Model
{
    protected $fillable = ['enrollment_id', 'source_teacher_id', 'target_teacher_id', 'effective_date',
        'status', 'before', 'after', 'reason', 'created_by', 'applied_at', 'blocked_reason'];

    protected $casts = ['before' => 'array', 'after' => 'array', 'effective_date' => 'date', 'applied_at' => 'datetime'];

    public function enrollment(): BelongsTo { return $this->belongsTo(Enrollment::class); }
    public function sourceTeacher(): BelongsTo { return $this->belongsTo(Teacher::class, 'source_teacher_id'); }
    public function targetTeacher(): BelongsTo { return $this->belongsTo(Teacher::class, 'target_teacher_id'); }
}
