<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressReportSessionNote extends Model
{
    protected $fillable = ['progress_report_id', 'session_date', 'notes', 'sort_order'];
    protected $casts = ['session_date' => 'date'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class, 'progress_report_id');
    }
}
