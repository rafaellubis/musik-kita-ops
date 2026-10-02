<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventHonorEntry extends Model
{
    use HasFactory;

    public const STATUS_POSTED = 'POSTED';
    public const STATUS_REVERSED = 'REVERSED';

    protected $fillable = [
        'event_id', 'event_participant_id', 'teacher_id',
        'snapshot_package_id', 'snapshot_package_code', 'snapshot_package_price',
        'honor_formula', 'amount', 'honor_slip_id', 'status',
        'posted_at', 'posted_by', 'reversed_at', 'reversed_by', 'reversal_reason',
    ];

    protected $casts = [
        'snapshot_package_price' => 'integer',
        'amount' => 'integer',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class, 'event_participant_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function honorSlip(): BelongsTo
    {
        return $this->belongsTo(HonorSlip::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
