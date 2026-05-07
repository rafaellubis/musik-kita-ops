<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instrument extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Satu instrumen punya banyak paket (Reguler grade Basic-L4, Hobby 30/45').
     * Dipakai di seeder & filter UI master data Paket.
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    /**
     * Matriks guru-instrumen (many-to-many via teacher_instruments).
     * Pivot is_primary menandai instrumen utama guru.
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'teacher_instruments')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }
}
