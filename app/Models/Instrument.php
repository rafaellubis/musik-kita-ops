<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
 
class Instrument extends Model
{
    use HasFactory;
    protected $fillable = [
        'code', 'name', 'description', 'is_active', 'sort_order',
    ];
    protected $casts = [
        'is_active' => 'boolean',
    ];
 
    public function instrument(): BelongsTo
    {
		return $this->hasMany(Package::class);
    }
	public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'teacher_instruments')
                    ->withPivot('is_primary')->withTimestamps();
    }

}