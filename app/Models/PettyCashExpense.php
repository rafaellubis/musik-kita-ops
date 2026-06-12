<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengeluaran petty cash oleh Admin (M07).
 *
 * Nomor: PCE/YYYY/MM/NNNN (reset per bulan).
 * Hanya mengurangi saldo petty cash — tidak double-hit P&L.
 */
class PettyCashExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_number',
        'expense_category_id',
        'amount',
        'description',
        'expense_date',
        'receipt_image',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'expense_date' => 'date',
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
}
