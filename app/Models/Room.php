<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'capacity',
        'has_piano', 'has_drum', 'has_amplifier',
        'notes', 'is_active',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'has_piano'     => 'boolean',
        'has_drum'      => 'boolean',
        'has_amplifier' => 'boolean',
        'capacity'      => 'integer',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }
}