<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori pengeluaran studio (M07).
 * Contoh: SEWA, LISTRIK, GAJI_STAFF, PERALATAN, dll.
 * Dikelola Owner via master data.
 */
class ExpenseCategory extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
