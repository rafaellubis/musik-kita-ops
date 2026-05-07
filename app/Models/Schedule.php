<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jadwal mingguan tetap per enrollment (M03).
 *
 * day_of_week mengikuti Carbon::dayOfWeek (0=Minggu, 1=Senin, ..., 6=Sabtu).
 * Saat generator jalan, schedule yang is_active=true di-iterasi.
 */
class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id', 'day_of_week',
        'start_time', 'end_time',
        'room_id', 'is_active', 'notes',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_active'   => 'boolean',
    ];

    /**
     * Pemetaan day_of_week ke nama hari Bahasa Indonesia.
     * Dipakai untuk display di UI.
     */
    public const DAY_NAMES = [
        0 => 'Minggu',
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ];

    public function getDayNameAttribute(): string
    {
        return self::DAY_NAMES[$this->day_of_week] ?? '?';
    }

    // ============= RELATIONSHIPS =============

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    // ============= SCOPES =============

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
