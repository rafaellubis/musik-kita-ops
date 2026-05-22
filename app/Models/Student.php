<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        // Identitas
        'student_code', 'full_name', 'nickname', 'gender',
        // Kontak
        'birth_date', 'phone', 'email', 'address', 'notes',
        // Parent
        'parent_name', 'parent_phone', 'parent_email', 'parent_relationship',
        // Status & pointer ke enrollment utama
        'status', 'primary_enrollment_id',
        // Tanggal penting
        'trial_date', 'active_since',
        // Tracking
        'last_session_at',
        // Cuti
        'cuti_from', 'cuti_until',
    ];

    protected $casts = [
        'birth_date'    => 'date',
        'trial_date'    => 'datetime',
        'active_since'  => 'date',
        'last_session_at' => 'datetime',
        'cuti_from'     => 'date',
        'cuti_until'    => 'date',
    ];

    // ============= RELATIONSHIPS =============

    /**
     * Enrollment utama murid — pointer ke paket/guru yang sedang aktif.
     * Murid bisa punya banyak enrollment, tapi hanya satu yang menjadi "primary".
     */
    public function primaryEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'primary_enrollment_id');
    }

    /**
     * Riwayat transisi status. Urut terbaru duluan untuk tampilan timeline.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(StudentStatusHistory::class)
                    ->latest();
    }

    /**
     * Semua enrollment murid (historis + aktif). Pakai scopeActive untuk
     * filter yang berjalan: $student->enrollments()->active()->first().
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Sesi yang ter-generate untuk murid ini (lewat enrollment-nya).
     */
    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    /**
     * Tagihan ke murid (M05). Urut terbaru duluan.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('issued_at');
    }

    // ============= STATIC METHODS =============

    /**
     * Generate kode murid format M-YYYY-NNNN.
     * Contoh: M-2026-0001, M-2026-0042
     */
    public static function generateCode(): string
    {
        $year   = now()->year;
        $prefix = "M-{$year}-";

        $latest = static::where('student_code', 'like', $prefix . '%')
            ->orderBy('student_code', 'desc')
            ->first();

        if (!$latest) {
            // Belum ada murid tahun ini
            return $prefix . '0001';
        }

        // Extract nomor (4 digit terakhir), parse, increment
        $lastNumber = (int) substr($latest->student_code, -4);
        $newNumber  = $lastNumber + 1;

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    // ============= ACCESSORS =============

    /**
     * Hitung umur murid berdasarkan birth_date.
     * Return null kalau birth_date tidak diisi.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }

    /**
     * Backward-compat: views yang pakai $student->package masih bisa jalan.
     * Sekarang dibaca dari primaryEnrollment, bukan kolom langsung di students.
     */
    public function getPackageAttribute(): ?Package
    {
        return $this->primaryEnrollment?->package;
    }

    /**
     * Backward-compat: views yang pakai $student->assignedTeacher masih bisa jalan.
     * Guru diambil dari primaryEnrollment.
     */
    public function getAssignedTeacherAttribute(): ?Teacher
    {
        return $this->primaryEnrollment?->teacher;
    }

    /**
     * Backward-compat: views yang pakai $student->assignedRoom masih bisa jalan.
     * Ruangan diambil dari jadwal aktif pertama di primaryEnrollment.
     */
    public function getAssignedRoomAttribute(): ?Room
    {
        // Enrollment punya banyak schedules — ambil yang aktif pertama
        return $this->primaryEnrollment?->schedules()->where('is_active', true)->first()?->room;
    }
}
