<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SeoSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $settings = Cache::remember('seo_settings.all', 3600, function () {
            return self::query()->pluck('value', 'key')->all();
        });

        return $settings[$key] ?? $default;
    }

    public static function setValue(string $key, mixed $value): self
    {
        $setting = self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? $value : ['data' => $value]]
        );

        Cache::forget('seo_settings.all');

        return $setting;
    }

    public static function organizationDefaults(): array
    {
        return [
            'name' => 'Biuro Podróży RAFA',
            'legal_name' => 'Biuro Podróży "RAFA" - Rafał Latos',
            'url' => config('app.public_url', config('app.url')),
            'phone' => '+48 606 102 243',
            'email' => 'rafa@bprafa.pl',
            'street' => 'Marii Konopnickiej 6',
            'city' => 'Warszawa',
            'postal_code' => '00-491',
            'country' => 'PL',
            'nip' => '716-250-87-61',
            'regon' => '432298189',
            'founded_year' => null,
            'trips_count' => null,
            'schools_count' => null,
            'license_number' => null,
            'tfg_info' => 'Wpis do rejestru organizatorów turystyki i pośredników turystycznych (TFG).',
            'facebook' => 'https://www.facebook.com/biuropodrozyrafa/',
            'instagram' => 'https://www.instagram.com/biuropodrozyrafa/',
            'default_title' => 'Biuro Podróży RAFA – wycieczki szkolne i wyjazdy firmowe',
            'default_description' => 'Biuro Podróży RAFA organizuje wycieczki szkolne, zielone szkoły i wyjazdy integracyjne dla firm w całej Polsce i Europie.',
            'og_image' => 'uploads/logo.png',
        ];
    }

    public static function organization(): array
    {
        $stored = self::getValue('organization', []);

        return array_merge(self::organizationDefaults(), is_array($stored) ? $stored : []);
    }
}
