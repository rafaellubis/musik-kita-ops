<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
class Package extends Model
{
    use HasFactory;
    protected $fillable = [
        'code', 'instrument_id', 'class_type', 'grade',
        'duration_min', 'price_per_month', 'is_active', 'sort_order',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'price_per_month' => 'integer',
    ];
 
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
	public function getFormattedPriceAttribute(): string
	{
		return 'Rp' . number_format($this->price_per_month, 0, ',', '.');
	}
}
