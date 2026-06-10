<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pengeluaran studio (M07).
 *
 * Nomor: EXP/YYYY/MM/NNNN (reset per bulan).
 * payment_method = CASH dipakai untuk menghitung saldo petty cash harian.
 */
class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_number',
        'expense_category_id',
        'amount',
        'description',
        'expense_date',
        'payment_method',
        'receipt_image',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'expense_date' => 'date',
    ];

    public const METHOD_CASH     = 'CASH';
    public const METHOD_TRANSFER = 'TRANSFER';

    public const METHODS = [
        self::METHOD_CASH     => 'CASH (Tunai)',
        self::METHOD_TRANSFER => 'TRANSFER (Bank)',
    ];

    // ============= RELATIONSHIPS =============

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Slip gaji staff yang membuat pengeluaran ini (M12). */
    public function staffPayrollSlip(): HasOne
    {
        return $this->hasOne(StaffPayrollSlip::class, 'expense_id');
    }

    // ============= SCOPES =============

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('expense_date', $year)
                     ->whereMonth('expense_date', $month);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('expense_date', $date);
    }

    public function scopeCash($query)
    {
        return $query->where('payment_method', self::METHOD_CASH);
    }
}
