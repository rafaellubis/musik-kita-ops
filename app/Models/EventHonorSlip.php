<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Slip honor guru untuk event (M08).
 *
 * Berbeda dari HonorSlip (M06) yang berbasis bulan,
 * slip ini khusus untuk satu event.
 *
 * Komponen wajib (CLAUDE.md v1.1):
 *   1. base_honor   — otomatis (default Rp 250.000 H_UJIAN)
 *   2. transport_honor — input manual
 *   3. other_honor  — input manual + other_honor_note wajib jika > 0
 */
class EventHonorSlip extends Model
{
    use HasFactory;

    protected $fillable = [
        'slip_number', 'event_id', 'teacher_id', 'role',
        'base_honor', 'transport_honor', 'other_honor', 'other_honor_note',
        'total_honor', 'status', 'paid_at', 'paid_by', 'created_by',
    ];

    protected $casts = [
        'base_honor'      => 'integer',
        'transport_honor' => 'integer',
        'other_honor'     => 'integer',
        'total_honor'     => 'integer',
        'paid_at'         => 'datetime',
    ];

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PAID  = 'PAID';

    // ============= ACCESSORS =============

    /** Slip tidak bisa di-edit setelah status PAID. */
    public function isLocked(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PAID  => 'Dibayarkan',
            default            => $this->status,
        };
    }

    /** Hitung ulang total dari komponen. */
    public function recalcTotal(): void
    {
        $this->total_honor = $this->base_honor + $this->transport_honor + $this->other_honor;
        $this->save();
    }

    // ============= RELATIONSHIPS =============

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
