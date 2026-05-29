<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressReportItem extends Model
{
    protected $fillable = ['progress_report_id', 'report_template_item_id', 'is_checked'];
    protected $casts = ['is_checked' => 'boolean'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class, 'progress_report_id');
    }

    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(ReportTemplateItem::class, 'report_template_item_id');
    }
}
