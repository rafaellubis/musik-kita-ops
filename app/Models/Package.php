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

    /**
     * Helper kategori paket: cek apakah paket ini tipe Duo (privat berdua).
     */
    public function isDuo(): bool
    {
        return $this->class_type === 'DUO';
    }

    public function getLevelLabel(): string
    {
        if ($this->isKidsClass()) {
            return 'Kids Class';
        }
        if ($this->class_type === 'HOBBY') {
            return 'Hobby';
        }
        if ($this->isDuo()) {
            return 'Basic · Belajar Berdua';
        }
        if ($this->class_type === 'REGULER') {
            return $this->grade === 'BASIC' ? 'Basic' : 'Level ' . ($this->grade ?? '-');
        }
        return $this->code;
    }

    /**
     * Label instrumen + level untuk header/PDF laporan progress.
     * HOBBY/Kids: instrumen saja. DUO/REGULER: instrumen · Basic atau Level N.
     */
    public function getReportInstrumentLabel(): string
    {
        $instrumentName = $this->instrument?->name ?? '';
        $levelSuffix    = $this->getReportLevelSuffix();

        if ($levelSuffix === null) {
            return $instrumentName;
        }

        return $instrumentName . ' · ' . $levelSuffix;
    }

    private function getReportLevelSuffix(): ?string
    {
        if ($this->isKidsClass() || $this->class_type === 'HOBBY') {
            return null;
        }

        if ($this->isDuo() || ($this->class_type === 'REGULER' && $this->grade === 'BASIC')) {
            return 'Basic';
        }

        if ($this->class_type === 'REGULER') {
            $gradeMap = [
                'L1' => 'Level 1',
                'L2' => 'Level 2',
                'L3' => 'Level 3',
                'L4' => 'Level 4',
            ];

            return $gradeMap[$this->grade] ?? ('Level ' . ($this->grade ?? '-'));
        }

        return null;
    }
}
