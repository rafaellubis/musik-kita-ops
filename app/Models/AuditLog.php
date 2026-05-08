<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak audit aksi penting (M09).
 * Tidak pakai HasFactory — tidak perlu di-seed.
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'action',
        'entity_type', 'entity_id', 'entity_label',
        'old_values', 'new_values', 'notes', 'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public const ACTION_CREATE    = 'CREATE';
    public const ACTION_UPDATE    = 'UPDATE';
    public const ACTION_DELETE    = 'DELETE';
    public const ACTION_LOGIN     = 'LOGIN';
    public const ACTION_LOGOUT    = 'LOGOUT';
    public const ACTION_PRINT     = 'PRINT';
    public const ACTION_VOID      = 'VOID';
    public const ACTION_LIFECYCLE = 'LIFECYCLE';

    public const ACTION_LABELS = [
        'CREATE'    => 'Buat',
        'UPDATE'    => 'Edit',
        'DELETE'    => 'Hapus',
        'LOGIN'     => 'Login',
        'LOGOUT'    => 'Logout',
        'PRINT'     => 'Cetak',
        'VOID'      => 'Void',
        'LIFECYCLE' => 'Status Murid',
    ];

    // ============= STATIC HELPER =============

    /**
     * Catat satu entri audit log.
     * Bisa dipanggil dari mana saja: AuditLog::record('CREATE', $model, 'Invoice', ...)
     */
    public static function record(
        string $action,
        ?Model $entity = null,
        ?string $entityLabel = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $notes = null,
    ): self {
        $user = auth()->user();
        return self::create([
            'user_id'      => $user?->id,
            'user_name'    => $user?->name,
            'action'       => $action,
            'entity_type'  => $entity ? class_basename($entity) : null,
            'entity_id'    => $entity?->id,
            'entity_label' => $entityLabel,
            'old_values'   => $oldValues,
            'new_values'   => $newValues,
            'notes'        => $notes,
            'ip_address'   => request()->ip(),
        ]);
    }

    // ============= RELATIONSHIPS =============

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============= ACCESSORS =============

    public function getActionLabelAttribute(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }
}
