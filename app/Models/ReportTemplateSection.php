<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportTemplateSection extends Model
{
    protected $fillable = ['report_template_id', 'title', 'sort_order'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReportTemplateItem::class)->orderBy('sort_order');
    }
}
