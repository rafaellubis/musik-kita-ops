<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
 
class Teacher extends Model
{
    use HasFactory;
    protected $fillable = [
        'code', 'name', 'email', 'phone', 'bank_name', 'bank_account',
        'joined_date', 'is_active', 'notes',
    ];
    protected $casts = ['is_active' => 'boolean', 'joined_date' => 'date'];
 
    public function instruments()
    {
        return $this->belongsToMany(Instrument::class, 'teacher_instruments')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }
}
