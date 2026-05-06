<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'capacity',
        'has_piano', 'has_drum', 'has_amplifier',
        'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'has_piano' => 'boolean',
        'has_drum' => 'boolean',
        'has_amplifier' => 'boolean',
        'capacity' => 'integer',
    ];
}