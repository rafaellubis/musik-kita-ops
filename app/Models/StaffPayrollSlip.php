<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Slip gaji bulanan karyawan non-guru (M12).
 *
 * Lifecycle: DRAFT → CALCULATED → PAID (terkunci).
 */
class StaffPayrollSlip extends Model
{
    use HasFactory;

    protected $fillable = [
        'slip_number',
        'employee_id',
        'month',
        'year',
        'base_salary',
        'total_allowances',
        'total_deductions',
        'net_salary',
        'status',
        'paid_at',
        'paid_by',
        'expense_id',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'month'            => 'integer',
        'year'             => 'integer',
        'base_salary'      => 'integer',
        'total_allowances' => 'integer',
        'total_deductions' => 'integer',
        'net_salary'       => 'integer',
        'paid_at'          => 'datetime',
    ];

    public const STATUS_DRAFT      = 'DRAFT';
    public const STATUS_CALCULATED = 'CALCULATED';
    public const STATUS_PAID       = 'PAID';

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Hitung ulang total tunjangan, potongan, dan gaji bersih dari baris item.
     */
    public function recalcNet(): void
    {
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->get();

        $allowances = $items
            ->whereIn('item_type', [StaffPayrollItem::TYPE_ALLOWANCE, StaffPayrollItem::TYPE_OVERTIME])
            ->sum('amount');

        $deductions = $items
            ->where('item_type', StaffPayrollItem::TYPE_DEDUCTION)
            ->sum('amount');

        $this->total_allowances = $allowances;
        $this->total_deductions = $deductions;
        $this->net_salary       = ($this->base_salary ?? 0) + $allowances - $deductions;

        if ($this->net_salary < 0) {
            $this->net_salary = 0;
        }

        if ($this->status !== self::STATUS_PAID) {
            $this->status = self::STATUS_CALCULATED;
        }
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT      => 'Draft',
            self::STATUS_CALCULATED => 'Terhitung',
            self::STATUS_PAID       => 'Dibayarkan',
            default                 => $this->status,
        };
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StaffPayrollItem::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }
}
