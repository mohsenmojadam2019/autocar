<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SettingsRepository
{
    /** Returns a typed setting while decrypting secret values only at the service boundary. */
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember('setting:'.$key, now()->addHour(), fn () => Setting::query()->where('key', $key)->first());
        if (! $setting) {
            return $default;
        }

        $value = $setting->is_secret && $setting->value !== null ? Crypt::decryptString($setting->value) : $setting->value;

        return $this->cast($value, $setting->type);
    }

    /** Persists a normal or encrypted setting and invalidates its cache key immediately. */
    public function set(string $key, mixed $value, string $group = 'general', string $type = 'string', bool $secret = false): Setting
    {
        $stored = $value === null ? null : (string) ($type === 'json' ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value);
        if ($secret && $stored !== null) {
            $stored = Crypt::encryptString($stored);
        }

        $setting = Setting::query()->updateOrCreate(['key' => $key], [
            'group' => $group,
            'value' => $stored,
            'type' => $type,
            'is_secret' => $secret,
        ]);
        Cache::forget('setting:'.$key);

        return $setting;
    }

    /** Converts persisted text to the configured PHP value type. */
    private function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            'int' => (int) $value,
            'float' => (float) $value,
            'json' => $value ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : [],
            default => $value,
        };
    }
}
