<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollConfig extends Model
{
    use HasFactory;

    protected $table = 'payroll_configs';
    protected $fillable = ['scenario_code', 'scenario_name', 'formula_type',
        'value_or_formula', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
