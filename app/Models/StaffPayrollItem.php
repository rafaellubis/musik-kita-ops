<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris komponen slip gaji staff: tunjangan, lembur, atau potongan.
 */
class StaffPayrollItem extends Model
{
    use HasFactory;

    public const TYPE_ALLOWANCE = 'ALLOWANCE';
    public const TYPE_OVERTIME  = 'OVERTIME';
    public const TYPE_DEDUCTION = 'DEDUCTION';

    /** Kode item yang umum dipakai di UI dropdown */
    public const CODE_LABELS = [
        'TUNJ_TRANSPORT'  => 'Tunjangan Transport',
        'TUNJ_MAKAN'      => 'Tunjangan Makan',
        'BONUS'           => 'Bonus',
        'LEMBUR'          => 'Lembur',
        'BPJS'            => 'Potongan BPJS',
        'KASBON'          => 'Kasbon',
        'POTONGAN_ABSEN'  => 'Potongan Absen',
        'LAINNYA'         => 'Lainnya',
    ];

    protected $fillable = [
        'staff_payroll_slip_id',
        'item_type',
        'item_code',
        'description',
        'amount',
        'metadata',
    ];

    protected $casts = [
        'amount'   => 'integer',
        'metadata' => 'array',
    ];

    public function slip(): BelongsTo
    {
        return $this->belongsTo(StaffPayrollSlip::class, 'staff_payroll_slip_id');
    }

    public function getItemCodeLabelAttribute(): string
    {
        return self::CODE_LABELS[$this->item_code] ?? $this->item_code;
    }

    public function getItemTypeLabelAttribute(): string
    {
        return match ($this->item_type) {
            self::TYPE_ALLOWANCE => 'Tunjangan',
            self::TYPE_OVERTIME   => 'Lembur',
            self::TYPE_DEDUCTION  => 'Potongan',
            default               => $this->item_type,
        };
    }
}
