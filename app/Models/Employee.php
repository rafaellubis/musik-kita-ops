<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master karyawan non-guru (M12).
 * Guru tetap di tabel teachers — tidak masuk employees.
 */
class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_code',
        'full_name',
        'position',
        'user_id',
        'base_salary',
        'bank_name',
        'bank_account',
        'bank_account_holder',
        'joined_date',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'base_salary' => 'integer',
        'is_active'   => 'boolean',
        'joined_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payrollSlips(): HasMany
    {
        return $this->hasMany(StaffPayrollSlip::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
