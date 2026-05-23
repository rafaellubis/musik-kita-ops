<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Peserta event: murid yang ikut Mini Concert dan/atau Ujian (M08).
 *
 * participation_type:
 *   UJIAN_TAMPIL -> Ujian + Tampil di Concert = Rp 395.000 (kode UJI)
 *   TAMPIL_SAJA  -> Tampil di Concert saja    = Rp 295.000 (kode MC)
 *
 * exam_result + grade_before/after diisi setelah event COMPLETED.
 */
class EventParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'student_id', 'enrollment_id',
        'accompanying_teacher_id',
        'participation_type', 'fee_amount',
        'invoice_id', 'invoice_item_id',
        'exam_result', 'grade_before', 'grade_after', 'exam_notes',
    ];

    protected $casts = [
        'fee_amount' => 'integer',
    ];

    public const TYPE_UJIAN_TAMPIL = 'UJIAN_TAMPIL';
    public const TYPE_TAMPIL_SAJA  = 'TAMPIL_SAJA';

    public const RESULT_LULUS       = 'LULUS';
    public const RESULT_TIDAK_LULUS = 'TIDAK_LULUS';

    public const FEE_UJIAN_TAMPIL = 395000;
    public const FEE_TAMPIL_SAJA  = 295000;

    public const INVOICE_CODE = [
        'UJIAN_TAMPIL' => 'UJI',
        'TAMPIL_SAJA'  => 'MC',
    ];

    public const TYPE_LABELS = [
        'UJIAN_TAMPIL' => 'Ujian + Tampil',
        'TAMPIL_SAJA'  => 'Tampil Saja',
    ];

    // ============= RELATIONSHIPS =============

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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function accompanyingTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'accompanying_teacher_id');
    }

    // ============= HELPERS =============

    /** Apakah peserta ini sudah diinput hasil ujiannya. */
    public function hasExamResult(): bool
    {
        return $this->exam_result !== null;
    }

    /** Label partisipasi yang user-friendly. */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->participation_type] ?? $this->participation_type;
    }
}
