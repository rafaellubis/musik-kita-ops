<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplate extends Model
{
    public const KIND_REGULER = 'REGULER';
    public const KIND_HOBBY   = 'HOBBY';
    public const KIND_KIDS    = 'KIDS';

    protected $fillable = ['instrument_id', 'name', 'template_kind', 'description', 'is_active', 'sort_order'];
    protected $casts = ['is_active' => 'boolean'];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ReportTemplateSection::class)->orderBy('sort_order');
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProgressReport::class);
    }
}
