<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail satu transisi status murid.
 *
 * Tabel ini APPEND-ONLY: tidak boleh di-update atau di-delete via aplikasi.
 * Semua mutasi harus lewat StudentLifecycleService.
 */
class StudentStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'from_status',
        'to_status',
        'reason',
        'skipped_trial',
        'metadata',
        'changed_by',
    ];

    protected $casts = [
        'skipped_trial' => 'boolean',
        'metadata'      => 'array',
    ];

    // ============= RELATIONSHIPS =============

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * User yang melakukan transisi. NULL kalau dipicu cron/system.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
