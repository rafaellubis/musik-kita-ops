<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'instrument_id', 'class_type', 'grade',
        'duration_min', 'price_per_month', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'price_per_month' => 'integer',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'Rp' . number_format($this->price_per_month, 0, ',', '.');
    }

    /**
     * Helper kategori paket: untuk cek pakai logic Kids Class
     * tanpa harus inline string compare di banyak tempat.
     */
    public function isKidsClass(): bool
    {
        return in_array($this->class_type, ['KIDS_CLASS', 'KIDS_CLASS_BUNDLE'], true);
    }
}
