<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Event Mini Concert dengan lifecycle terpisah dari sesi kelas reguler. */
class Event extends Model
{
    use HasFactory;

    public const TYPE_MINI_CONCERT = 'MINI_CONCERT';
    public const TYPE_UJIAN_GRADE = 'UJIAN_GRADE';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const TYPE_LABELS = [
        self::TYPE_MINI_CONCERT => 'Mini Concert',
        self::TYPE_UJIAN_GRADE => 'Ujian Kenaikan Grade',
    ];

    protected $fillable = [
        'event_number', 'name', 'type', 'event_date', 'payment_due_date',
        'notes', 'status', 'created_by', 'published_at', 'published_by',
        'completed_at', 'completed_by', 'cancelled_at', 'cancelled_by',
        'cancellation_reason',
    ];

    protected $casts = [
        'event_date' => 'date',
        'payment_due_date' => 'date',
        'published_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(EventTeacherAssignment::class);
    }

    public function honorEntries(): HasMany
    {
        return $this->hasMany(EventHonorEntry::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }
}
