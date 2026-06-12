<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isi saldo petty cash oleh Owner (M07).
 *
 * Nomor: PCU/YYYY/MM/NNNN (reset per bulan).
 * Top-up masuk P&L sebagai pengeluaran operasional.
 */
class PettyCashTopup extends Model
{
    use HasFactory;

    protected $fillable = [
        'topup_number',
        'amount',
        'topup_date',
        'description',
        'notes',
        'receipt_image',
        'created_by',
    ];

    protected $casts = [
        'amount'     => 'integer',
        'topup_date' => 'date',
    ];

    // ============= RELATIONSHIPS =============

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============= SCOPES =============

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('topup_date', $year)
                     ->whereMonth('topup_date', $month);
    }
}
