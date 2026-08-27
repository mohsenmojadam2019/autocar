<?php

use App\Support\JalaliDate;

it('round trips Gregorian and Jalali dates including Persian digits', function (): void {
    $jalali = new JalaliDate;
    [$jy, $jm, $jd] = $jalali->gregorianToJalali(2026, 8, 28);
    expect($jalali->jalaliToGregorian($jy, $jm, $jd))->toBe([2026, 8, 28]);
    $formatted = $jalali->date('2026-08-28');
    expect($formatted)->toContain('۱۴۰۵')->and($jalali->parse($formatted)->format('Y-m-d'))->toBe('2026-08-28');
});
