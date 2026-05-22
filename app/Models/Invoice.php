<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Invoice / tagihan ke murid (M05).
 *
 * Status enum:
 *   UNPAID  — belum ada pembayaran sama sekali
 *   PARTIAL — sudah ada pembayaran, tapi belum lunas
 *   PAID    — paid_amount >= total_amount
 *   VOID    — invoice dibatalkan
 *
 * Jangan update status manual — pakai InvoiceService::recalcStatus().
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'student_id',
        'enrollment_id',
        'year', 'month',
        'description',
        'total_amount', 'paid_amount',
        'status',
        'due_date', 'issued_at',
        'notes',
        'class_type',
        'payment_mode',
        'installment_number',
        'installment_group_id',
    ];

    protected $casts = [
        'due_date'           => 'date',
        'issued_at'          => 'date',
        'year'               => 'integer',
        'month'              => 'integer',
        'total_amount'       => 'integer',
        'paid_amount'        => 'integer',
        'installment_number' => 'integer',
    ];

    public const STATUS_UNPAID  = 'UNPAID';
    public const STATUS_PARTIAL = 'PARTIAL';
    public const STATUS_PAID    = 'PAID';
    public const STATUS_VOID    = 'VOID';

    public const MODE_FULL        = 'FULL';
    public const MODE_INSTALLMENT = 'INSTALLMENT';

    // ============= HELPERS =============

    /** Invoice ini milik paket KIDS_CLASS_BUNDLE? */
    public function isKidsClassBundle(): bool
    {
        return $this->class_type === 'KIDS_CLASS_BUNDLE';
    }

    /** Invoice ini adalah cicilan (salah satu dari 3 termin)? */
    public function isInstallment(): bool
    {
        return $this->payment_mode === self::MODE_INSTALLMENT;
    }

    /**
     * Label termin untuk ditampilkan di UI.
     * Contoh: "Termin 1/3", "Termin 2/3", "Termin 3/3".
     * Null jika bukan installment.
     */
    public function getInstallmentLabelAttribute(): ?string
    {
        if (!$this->isInstallment() || !$this->installment_number) return null;
        return "Termin {$this->installment_number}/3";
    }

    // ============= ACCESSORS =============

    public function getBalanceAttribute(): int
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    public function getDaysOverdueAttribute(): int
    {
        if ($this->status === self::STATUS_PAID) return 0;
        $today = now()->startOfDay();
        if ($this->due_date && $today->gt($this->due_date)) {
            return $today->diffInDays($this->due_date);
        }
        return 0;
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    public function getFormattedBalanceAttribute(): string
    {
        return 'Rp ' . number_format($this->balance, 0, ',', '.');
    }

    // ============= RELATIONSHIPS =============

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Pembayaran valid (belum di-void). Untuk hitung paid_amount.
     */
    public function validPayments(): HasMany
    {
        return $this->hasMany(Payment::class)->whereNull('voided_at');
    }

    // ============= SCOPES =============

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [self::STATUS_UNPAID, self::STATUS_PARTIAL]);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }
}
