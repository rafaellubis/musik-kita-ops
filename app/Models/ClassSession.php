<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sesi kelas konkret per tanggal (M03/M04).
 *
 * Dinamai ClassSession (bukan Session) karena facade `Session` Laravel
 * sudah pakai nama itu. Tabel underlying: class_sessions.
 *
 * Lifecycle:
 *   1. Generator (M03) bikin row dengan status SCHEDULED atau LIBUR
 *   2. Admin input absensi (M04) -> status berubah ke HADIR/IZIN/HANGUS/dll
 *   3. Kalkulasi honor (M06) isi honor_code dan honor_amount
 */
class ClassSession extends Model
{
    use HasFactory;

    protected $table = 'class_sessions';

    protected $fillable = [
        'schedule_id', 'enrollment_id',
        'student_id', 'teacher_id', 'substitute_teacher_id',
        'session_date', 'start_time', 'end_time',
        'room_id', 'status',
        'late_minutes', 'notes',
        'honor_code', 'honor_amount',
        'session_sequence', 'origin_session_id',
    ];

    protected $casts = [
        // session_date tidak di-cast ke Carbon agar perbandingan string di collection
        // (firstWhere, contains) bekerja langsung tanpa perlu ->toDateString().
        // Kode yang butuh operasi tanggal pakai Carbon::parse($session->session_date).
        'late_minutes'     => 'integer',
        'honor_amount'     => 'integer',
        'session_sequence' => 'integer',
    ];

    /**
     * Status enum yang valid (referensi cepat ke CLAUDE.md).
     */
    public const STATUS_SCHEDULED       = 'SCHEDULED';
    public const STATUS_HADIR            = 'HADIR';
    public const STATUS_HADIR_TERLAMBAT  = 'HADIR_TERLAMBAT';
    public const STATUS_IZIN_RESCHEDULE  = 'IZIN_RESCHEDULE';
    public const STATUS_IZIN_VIDEO       = 'IZIN_VIDEO';
    public const STATUS_HANGUS           = 'HANGUS';
    public const STATUS_LIBUR            = 'LIBUR';
    public const STATUS_DIGANTI          = 'DIGANTI';
    public const STATUS_CANCELLED        = 'CANCELLED';

    /**
     * Guru yang berhak honor untuk sesi ini.
     * Kalau ada substitute (DIGANTI), honor ke substitute (BR-4.9).
     * Selain itu honor ke guru asli.
     */
    public function getHonoredTeacherIdAttribute(): int
    {
        return $this->substitute_teacher_id ?? $this->teacher_id;
    }

    // ============= RELATIONSHIPS =============

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'substitute_teacher_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** Sesi asal yang di-reschedule atau yang digantikan (holiday replacement). */
    public function originSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'origin_session_id');
    }

    /** Sesi-sesi yang mengacu ke sesi ini sebagai origin. */
    public function replacementSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'origin_session_id');
    }

    /**
     * Format label urutan sesi untuk ditampilkan di UI.
     *
     * Contoh output:
     *   "Sesi ke-2 Bulan Mei 2026"
     *   "Reschedule dari Sesi ke-2 Bulan Mei 2026"
     *   "—" (LIBUR yang punya sesi pengganti — sequence null)
     *
     * Requires: relasi originSession sudah di-eager-load oleh controller.
     */
    public function getSessionLabel(): string
    {
        // Sesi pengganti / reschedule — ada origin
        if ($this->origin_session_id && $this->originSession) {
            // Bulan dari tanggal ORIGIN (kapan sesi aslinya harusnya berlangsung).
            // Sequence dari sesi INI sendiri: sudah mewarisi slot dari origin,
            // tapi origin LIBUR selalu punya sequence=null.
            $bulan = Carbon::parse($this->originSession->session_date)
                           ->locale('id')->translatedFormat('F Y');
            $seq   = $this->session_sequence;
            return "Reschedule dari Sesi ke-{$seq} Bulan {$bulan}";
        }

        // Sesi biasa dengan sequence (SCHEDULED atau LIBUR tanpa replacement)
        if ($this->session_sequence) {
            $bulan = Carbon::parse($this->session_date)->locale('id')->translatedFormat('F Y');
            return "Sesi ke-{$this->session_sequence} Bulan {$bulan}";
        }

        // LIBUR yang punya replacement — sequence null
        return '—';
    }

    // ============= SCOPES =============

    public function scopeInMonth($query, int $year, int $month)
    {
        return $query->whereYear('session_date', $year)
                     ->whereMonth('session_date', $month);
    }

    public function scopeForTeacher($query, int $teacherId)
    {
        // Cocokkan teacher asli ATAU substitute (untuk laporan honor)
        return $query->where(function ($q) use ($teacherId) {
            $q->where('teacher_id', $teacherId)
              ->orWhere('substitute_teacher_id', $teacherId);
        });
    }
}
