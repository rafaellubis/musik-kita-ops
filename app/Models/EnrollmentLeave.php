<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentLeave extends Model
{
    protected $fillable = [
        'enrollment_id', 'cuti_from', 'cuti_until', 'extension_count', 'reason', 'completed_at',
    ];

    protected $casts = [
        'cuti_from' => 'date',
        'cuti_until' => 'date',
        'extension_count' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
