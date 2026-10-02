<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu enrollment murid yang mengikuti satu event. */
class EventParticipant extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const ATTENDANCE_PENDING = 'PENDING';
    public const ATTENDANCE_HADIR = 'HADIR';
    public const ATTENDANCE_TIDAK_HADIR = 'TIDAK_HADIR';

    protected $fillable = [
        'event_id', 'student_id', 'enrollment_id', 'status',
        'cancelled_at', 'cancelled_by', 'cancellation_reason',
        'active_teacher_assignment_id', 'invoice_id', 'invoice_item_id',
        'snapshot_package_id', 'snapshot_package_code', 'snapshot_instrument_name',
        'snapshot_class_type', 'snapshot_grade', 'snapshot_duration_min',
        'snapshot_package_price', 'snapshot_mc_fee', 'snapshot_honor_amount',
        'attendance_status', 'event_notes', 'attendance_updated_at', 'attendance_updated_by',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
        'attendance_updated_at' => 'datetime',
        'snapshot_duration_min' => 'integer',
        'snapshot_package_price' => 'integer',
        'snapshot_mc_fee' => 'integer',
        'snapshot_honor_amount' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function activeTeacherAssignment(): BelongsTo
    {
        return $this->belongsTo(EventTeacherAssignment::class, 'active_teacher_assignment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function attendanceUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_updated_by');
    }

    public function teacherReassignments(): HasMany
    {
        return $this->hasMany(EventTeacherReassignment::class);
    }

    public function honorEntries(): HasMany
    {
        return $this->hasMany(EventHonorEntry::class);
    }
}
