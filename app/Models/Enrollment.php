<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Enrollment murid ke paket dengan guru tertentu (M03).
 * Bridge antara student / package / teacher dan jadwal mingguan.
 */
class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id', 'package_id', 'teacher_id',
        'effective_date', 'end_date', 'status', 'notes',
        'is_primary',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date'       => 'date',
    ];

    // ============= RELATIONSHIPS =============

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    // ============= SCOPES =============

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }
}
