<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressReportSection extends Model
{
    protected $fillable = ['progress_report_id', 'report_template_section_id', 'summary'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class, 'progress_report_id');
    }

    public function templateSection(): BelongsTo
    {
        return $this->belongsTo(ReportTemplateSection::class, 'report_template_section_id');
    }
}
