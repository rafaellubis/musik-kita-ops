<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Enrollment murid ke paket dengan guru tertentu (M03).
 * Bridge antara student / package / teacher dan jadwal mingguan.
 */
class Enrollment extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_ACTIVE    = 'ACTIVE';
    public const STATUS_ON_LEAVE  = 'ON_LEAVE';
    public const STATUS_INACTIVE  = 'INACTIVE';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_TRIAL     = 'TRIAL';

    protected $fillable = [
        'student_id', 'package_id', 'teacher_id',
        'effective_date', 'end_date', 'status', 'notes',
        'is_primary',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date'       => 'date',
    ];

    // ============= RELATIONSHIPS =============

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(EnrollmentLeave::class);
    }

    public function currentLeave(): HasOne
    {
        return $this->hasOne(EnrollmentLeave::class)
            ->ofMany(['id' => 'max'], fn ($query) => $query->whereNull('completed_at'))
            ->whereNull('completed_at');
    }

    /** Riwayat cuti tetap berlaku saat membuat sesi untuk tanggal lampau. */
    public function isOnLeaveOn(string $date): bool
    {
        if ($this->relationLoaded('leaves')) {
            return $this->leaves->contains(fn (EnrollmentLeave $leave) =>
                $leave->cuti_from->toDateString() <= $date && $leave->cuti_until->toDateString() >= $date
            );
        }

        return $this->leaves()->whereDate('cuti_from', '<=', $date)->whereDate('cuti_until', '>=', $date)->exists();
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProgressReport::class)->orderByDesc('year')->orderByDesc('month');
    }

    public function promotion(): HasOne
    {
        return $this->hasOne(EnrollmentPromotion::class, 'source_enrollment_id')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('status', '!=', 'CANCELLED'))
            ->where('status', '!=', 'CANCELLED');
    }

    public function promotedFrom(): HasOne
    {
        return $this->hasOne(EnrollmentPromotion::class, 'successor_enrollment_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(EnrollmentTransfer::class)->orderBy('effective_date')->orderBy('id');
    }

    /** Penugasan bertanggal: riwayat lama tidak mengikuti guru rutin terbaru. */
    public function teachingAssignmentOn(CarbonInterface $date): array
    {
        $transfers = $this->transfers;
        $schedule = $this->schedules->firstWhere('is_active', true);
        $assignment = $transfers->first()?->before ?? [
            'teacher_id' => (int) $this->teacher_id,
            'day_of_week' => $schedule?->day_of_week,
            'start_time' => $schedule?->start_time,
            'end_time' => $schedule?->end_time,
            'room_id' => $schedule?->room_id,
        ];
        foreach ($transfers as $transfer) {
            if ($transfer->effective_date->toDateString() > $date->toDateString()) {
                break;
            }
            $assignment = $transfer->after;
        }
        // Perubahan jadwal biasa setelah pengalihan tetap berlaku untuk periode terbaru.
        if ($transfers->isNotEmpty() && $transfers->last()->status === 'APPLIED'
            && $date->toDateString() >= $transfers->last()->effective_date->toDateString()) {
            $assignment = [
                'teacher_id' => (int) $this->teacher_id, 'day_of_week' => $schedule?->day_of_week,
                'start_time' => $schedule?->start_time, 'end_time' => $schedule?->end_time, 'room_id' => $schedule?->room_id,
            ];
        }
        return $assignment;
    }

    /** Penanggung jawab bulanan memakai pengalihan terakhir dalam bulan tersebut. */
    public function reportTeacherFor(int $year, int $month): int
    {
        return $this->teachingAssignmentOn(\Carbon\Carbon::create($year, $month, 1)->endOfMonth())['teacher_id'];
    }

    /** Kelas berlaku pada [from, until), termasuk penerus yang belum mulai hari ini. */
    public function scopeForPeriod(Builder $query, CarbonInterface $from, CarbonInterface $until): Builder
    {
        return $query->whereDate('effective_date', '<', $until->toDateString())
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>', $from->toDateString()))
            ->where(fn ($q) => $q->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_ON_LEAVE])
                ->orWhere(fn ($pending) => $pending->where('status', self::STATUS_INACTIVE)
                    ->whereHas('promotedFrom', fn ($p) => $p->where('status', 'SCHEDULED'))));
    }

    // ============= SCOPES =============

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeTrial($query)
    {
        return $query->where('status', self::STATUS_TRIAL);
    }
}
