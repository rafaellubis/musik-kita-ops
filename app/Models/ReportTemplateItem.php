<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportTemplateItem extends Model
{
    protected $fillable = ['report_template_section_id', 'label', 'sort_order'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ReportTemplateSection::class, 'report_template_section_id');
    }
}
