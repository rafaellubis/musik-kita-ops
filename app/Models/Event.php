<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Event studio: Mini Concert, Ujian, atau gabungan (M08).
 *
 * Status lifecycle:
 *   DRAFT     -> event sudah dibuat, pendaftaran peserta masih dibuka
 *   COMPLETED -> event selesai, hasil ujian sudah diinput, slip honor bisa dibuat
 */
class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_number', 'name', 'type', 'event_date',
        'notes', 'status', 'created_by',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public const TYPE_MINI_CONCERT        = 'MINI_CONCERT';
    public const TYPE_UJIAN               = 'UJIAN';
    public const TYPE_MINI_CONCERT_UJIAN  = 'MINI_CONCERT_UJIAN';

    public const STATUS_DRAFT     = 'DRAFT';
    public const STATUS_COMPLETED = 'COMPLETED';

    public const TYPE_LABELS = [
        'MINI_CONCERT'        => 'Mini Concert',
        'UJIAN'               => 'Ujian',
        'MINI_CONCERT_UJIAN'  => 'Mini Concert + Ujian',
    ];

    // ============= ACCESSORS =============

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /** Apakah event punya komponen ujian (bisa input hasil). */
    public function hasExam(): bool
    {
        return in_array($this->type, [self::TYPE_UJIAN, self::TYPE_MINI_CONCERT_UJIAN]);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    // ============= RELATIONSHIPS =============

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function honorSlips(): HasMany
    {
        return $this->hasMany(EventHonorSlip::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============= SCOPES =============

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
}
