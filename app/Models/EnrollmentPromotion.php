<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentPromotion extends Model
{
    protected $fillable = [
        'student_id', 'source_enrollment_id', 'successor_enrollment_id', 'effective_date',
        'status', 'evaluation_note', 'created_by', 'applied_at', 'snapshot', 'invoice_adjustments',
    ];

    protected $casts = [
        'effective_date' => 'date', 'applied_at' => 'datetime',
        'snapshot' => 'array', 'invoice_adjustments' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'source_enrollment_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'successor_enrollment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
