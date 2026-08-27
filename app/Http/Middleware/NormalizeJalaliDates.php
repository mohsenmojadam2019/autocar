<?php

namespace App\Http\Middleware;

use App\Support\JalaliDate;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class NormalizeJalaliDates
{
    public function __construct(private readonly JalaliDate $jalali) {}

    /** Converts recognized Jalali web-form dates to Gregorian before Laravel validation/persistence. */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            $request->merge($this->normalizeArray($request->all()));
        }

        return $next($request);
    }

    /** Recursively converts date-like fields while leaving ordinary text untouched. */
    private function normalizeArray(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = $this->normalizeArray($value);
                continue;
            }

            if (! is_string($value) || ! $this->isDateKey((string) $key)) {
                continue;
            }

            $latin = trim($this->jalali->latinDigits($value));
            if (! preg_match('/^1[34]\d{2}[\/-]\d{1,2}[\/-]\d{1,2}/', $latin)) {
                continue;
            }

            try {
                $date = $this->jalali->parse($latin);
                $input[$key] = preg_match('/\d{1,2}:\d{1,2}/', $latin)
                    ? $date->format('Y-m-d H:i:s')
                    : $date->format('Y-m-d');
            } catch (InvalidArgumentException) {
                // Keep the original value so normal validator messages remain attached to the field.
            }
        }

        return $input;
    }

    /** Limits automatic conversion to fields that semantically represent dates. */
    private function isDateKey(string $key): bool
    {
        return str_ends_with($key, '_at')
            || str_ends_with($key, '_date')
            || in_array($key, ['date', 'from_date', 'to_date', 'date_from', 'date_to'], true);
    }
}
