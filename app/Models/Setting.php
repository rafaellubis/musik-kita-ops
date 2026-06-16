<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'description'];

    /**
     * Dapatkan nilai setting berdasarkan key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Simpan atau update nilai setting.
     *
     * @param string $key
     * @param mixed $value
     * @param string|null $description
     * @return self
     */
    public static function set(string $key, $value, ?string $description = null): self
    {
        // Ubah boolean ke string format database
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'description' => $description
            ]
        );
    }
}
