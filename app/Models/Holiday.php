<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'name',
        'type',
        'is_active',
        'notes',
        'replacement_date',
        'is_honor_paid',
    ];

    protected $casts = [
        'date'             => 'date',
        'replacement_date' => 'date',
        'is_active'        => 'boolean',
        'is_honor_paid'    => 'boolean',
    ];
}
