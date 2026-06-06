<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgressReport extends Model
{
    public const STATUS_DRAFT     = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const KESIMPULAN_PERLU_PENDAMPINGAN = 'PERLU_PENDAMPINGAN';
    public const KESIMPULAN_CUKUP = 'CUKUP';
    public const KESIMPULAN_BAIK = 'BAIK';
    public const KESIMPULAN_SANGAT_BAIK = 'SANGAT_BAIK';

    protected $fillable = [
        'enrollment_id', 'student_id', 'teacher_id', 'report_template_id',
        'month', 'year', 'status',
        'highlight', 'summary_notes', 'target_notes', 'repertoire',
        'submitted_at',
        'rating_teknik', 'rating_materi', 'rating_reading', 'rating_repertoar',
        'catatan_perkembangan_musikal', 'catatan_karakter',
        'kesimpulan_progress', 'progress_percent',
    ];

    protected $casts = [
        'repertoire'      => 'array',
        'submitted_at'    => 'datetime',
        'rating_teknik'   => 'integer',
        'rating_materi'   => 'integer',
        'rating_reading'  => 'integer',
        'rating_repertoar' => 'integer',
        'progress_percent' => 'integer',
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

    public static function kesimpulanLabels(): array
    {
        return [
            self::KESIMPULAN_PERLU_PENDAMPINGAN => 'Perlu Pendampingan Lebih',
            self::KESIMPULAN_CUKUP              => 'Cukup',
            self::KESIMPULAN_BAIK               => 'Baik',
            self::KESIMPULAN_SANGAT_BAIK        => 'Sangat Baik',
        ];
    }

    public function averageSessionRating(): ?float
    {
        $ratings = $this->sessionNotes->pluck('session_rating')->filter(fn ($r) => $r !== null);
        if ($ratings->isEmpty()) {
            return null;
        }
        return (float) round($ratings->avg(), 1);
    }

    public function weeklyMaterials(): array
    {
        $materials = [1 => null, 2 => null, 3 => null, 4 => null];
        foreach ($this->sessionNotes as $note) {
            if ($note->session_sequence >= 1 && $note->session_sequence <= 4) {
                $materials[$note->session_sequence] = $note->material_learned;
            }
        }
        return $materials;
    }

    public static function renderStars(?int $rating): string
    {
        if ($rating === null) {
            return '—';
        }
        return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    }

    public function instrumentEmoji(): string
    {
        $code = $this->enrollment?->package?->instrument?->code ?? '';
        return config("instruments.emojis.{$code}", config('instruments.default_emoji'));
    }
}
