<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgressReport extends Model
{
    public const STATUS_DRAFT     = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';

    protected $fillable = [
        'enrollment_id', 'student_id', 'teacher_id', 'report_template_id',
        'month', 'year', 'status',
        'highlight', 'summary_notes', 'target_notes', 'repertoire',
        'submitted_at',
    ];

    protected $casts = [
        'repertoire'   => 'array',
        'submitted_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo { return $this->belongsTo(Enrollment::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(Teacher::class); }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }

    public function sections(): HasMany { return $this->hasMany(ProgressReportSection::class); }
    public function items(): HasMany { return $this->hasMany(ProgressReportItem::class); }

    public function sessionNotes(): HasMany
    {
        return $this->hasMany(ProgressReportSessionNote::class)->orderBy('session_date');
    }

    public function namaBulan(): string
    {
        $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
                  'Juli','Agustus','September','Oktober','November','Desember'];
        return $bulan[$this->month] . ' ' . $this->year;
    }
}
