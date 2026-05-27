<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'email', 'phone', 'bank_name', 'bank_account',
        'bank_account_holder', 'joined_date', 'is_active', 'notes', 'user_id',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'joined_date' => 'date',
    ];

    public function instruments(): BelongsToMany
    {
        return $this->belongsToMany(Instrument::class, 'teacher_instruments')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Sesi yang dijadwalkan untuk guru ini (sebagai guru asli).
     * Untuk include sesi pengganti, pakai scope ClassSession::forTeacher().
     */
    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    /**
     * Relasi ke akun User — dipakai saat guru login ke sistem.
     * Nullable: guru lama / guru tanpa akun tidak punya user_id.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
