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
        // Status
        'status', 'package_id', 'assigned_teacher_id', 'assigned_room_id',
        'preferred_day', 'preferred_time', 'trial_date', 'active_since',
        // Tracking
        'last_session_at',
        // Cuti
        'cuti_from', 'cuti_until',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'trial_date' => 'datetime',
        'active_since' => 'date',
        'last_session_at' => 'datetime',
        'cuti_from' => 'date',
        'cuti_until' => 'date',
    ];

    // ============= RELATIONSHIPS =============

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function assignedTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'assigned_teacher_id');
    }

    public function assignedRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'assigned_room_id');
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
        $year = now()->year;
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
        $newNumber = $lastNumber + 1;

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    // ============= ACCESSOR =============

    /**
     * Hitung umur murid berdasarkan birth_date.
     * Return null kalau birth_date tidak diisi.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }
}
