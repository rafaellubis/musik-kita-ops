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
        'supported_instruments',
        'notes', 'is_active',
    ];

    protected $casts = [
        'supported_instruments' => 'array',
        'capacity'              => 'integer',
        'is_active'             => 'boolean',
    ];

    /**
     * Cek apakah ruangan mendukung instrumen tertentu.
     * Dipakai oleh ScheduleController untuk hard block saat assign jadwal.
     */
    public function supportsInstrument(string $instrumentName): bool
    {
        return in_array($instrumentName, $this->supported_instruments ?? []);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }
}