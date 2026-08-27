<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FeatureFlagService
{
    /** Evaluates time-windowed and percentage-rollout feature flags deterministically per subject. */
    public function enabled(string $key, string|int|null $subject = null): bool
    {
        $flag = Cache::remember('feature-flag:'.$key, 60, fn () => DB::table('feature_flags')->where('key', $key)->first());
        if (! $flag || ! $flag->enabled) {
            return false;
        }
        if ($flag->starts_at && now()->lt($flag->starts_at)) {
            return false;
        }
        if ($flag->ends_at && now()->gt($flag->ends_at)) {
            return false;
        }
        $percentage = (int) $flag->rollout_percentage;
        if ($percentage >= 100 || $subject === null) {
            return $percentage > 0;
        }

        return abs(crc32($key.':'.$subject)) % 100 < $percentage;
    }

    /** Clears cached evaluation immediately after an admin changes a flag. */
    public function forget(string $key): void
    {
        Cache::forget('feature-flag:'.$key);
    }
}
