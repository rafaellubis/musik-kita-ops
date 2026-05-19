<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Slip honor bulanan per guru (M06).
 *
 * Lifecycle:
 *   DRAFT      → slip dibuat, base_honor belum dikalkulasi
 *   CALCULATED → HonorCalculationService mengisi base_honor dari sesi bulan ini
 *   PAID       → Owner tandai sudah dibayar; slip terkunci dari edit
 *
 * Komponen gaji sesuai BRD revisi v1.1:
 *   base_honor        → otomatis dari aggregate class_sessions.honor_amount
 *   event_honor       → otomatis dari sesi event (Mini Concert / Ujian)
 *   event_honor_note  → keterangan event (opsional)
 *   transport_honor   → INPUT MANUAL (tidak ada formula)
 *   other_honor       → INPUT MANUAL + wajib isi other_honor_note
 *   total_honor       → base + event + transport + other (dihitung saat save)
 */
class HonorSlip extends Model
{
    use HasFactory;

    protected $table = 'teacher_honor_slips';

    protected $fillable = [
        'slip_number', 'teacher_id',
        'month', 'year',
        'base_honor',
        'event_honor', 'event_honor_note',   // honor dari sesi event (Mini Concert / Ujian)
        'transport_honor', 'other_honor', 'other_honor_note',
        'total_honor',
        'status', 'paid_at', 'paid_by', 'created_by',
    ];

    protected $casts = [
        'month'           => 'integer',
        'year'            => 'integer',
        'base_honor'      => 'integer',
        'event_honor'     => 'integer',   // honor event (Mini Concert / Ujian)
        'transport_honor' => 'integer',
        'other_honor'     => 'integer',
        'total_honor'     => 'integer',
        'paid_at'         => 'datetime',
    ];

    public const STATUS_DRAFT      = 'DRAFT';
    public const STATUS_CALCULATED = 'CALCULATED';
    public const STATUS_PAID       = 'PAID';

    // ============= HELPERS =============

    /**
     * Slip terkunci jika sudah PAID — tidak boleh diedit lagi.
     */
    public function isLocked(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Hitung ulang total dari komponen yang ada.
     * Dipanggil sebelum save agar total selalu konsisten.
     */
    public function recalcTotal(): void
    {
        $this->total_honor = ($this->base_honor ?? 0)
            + ($this->event_honor ?? 0)
            + ($this->transport_honor ?? 0)
            + ($this->other_honor ?? 0);
    }

    /**
     * Cek apakah slip ini memiliki komponen honor event (Mini Concert / Ujian).
     * Digunakan di view untuk menampilkan/menyembunyikan baris event honor.
     */
    public function hasEventHonor(): bool
    {
        return ($this->event_honor ?? 0) > 0;
    }

    /**
     * Label status dalam Bahasa Indonesia.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT      => 'Draft',
            self::STATUS_CALCULATED => 'Terhitung',
            self::STATUS_PAID       => 'Dibayarkan',
            default                 => $this->status,
        };
    }

    // ============= RELATIONSHIPS =============

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

    // ============= SCOPES =============

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_CALCULATED]);
    }
}
