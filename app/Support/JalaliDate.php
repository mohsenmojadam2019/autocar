<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use InvalidArgumentException;

class JalaliDate
{
    /** Formats a Gregorian application date as a Persian-digit Jalali date/time. */
    public function format(CarbonInterface|DateTimeInterface|string|null $value, bool $withTime = true, bool $persianDigits = true): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $date = $value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse((string) $value);
        [$year, $month, $day] = $this->gregorianToJalali((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
        $formatted = sprintf('%04d/%02d/%02d', $year, $month, $day);
        if ($withTime) {
            $formatted .= ' '.$date->format('H:i');
        }

        return $persianDigits ? $this->toPersianDigits($formatted) : $formatted;
    }

    /** Formats a date without a clock component. */
    public function date(CarbonInterface|DateTimeInterface|string|null $value, bool $persianDigits = true): string
    {
        return $this->format($value, false, $persianDigits);
    }

    /** Parses a Jalali date/date-time submitted by Persian UI fields into Gregorian Carbon. */
    public function parse(string $value): CarbonImmutable
    {
        $normalized = trim(str_replace('-', '/', $this->latinDigits($value)));
        if (! preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})(?:\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?$/', $normalized, $matches)) {
            throw new InvalidArgumentException('تاریخ جلالی نامعتبر است. قالب صحیح ۱۴۰۵/۰۶/۰۶ است.');
        }

        $jy = (int) $matches[1];
        $jm = (int) $matches[2];
        $jd = (int) $matches[3];
        $hour = isset($matches[4]) ? (int) $matches[4] : 0;
        $minute = isset($matches[5]) ? (int) $matches[5] : 0;
        $second = isset($matches[6]) ? (int) $matches[6] : 0;

        if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > ($jm <= 6 ? 31 : 30) || $hour > 23 || $minute > 59 || $second > 59) {
            throw new InvalidArgumentException('تاریخ جلالی خارج از محدوده مجاز است.');
        }

        [$gy, $gm, $gd] = $this->jalaliToGregorian($jy, $jm, $jd);

        return CarbonImmutable::create($gy, $gm, $gd, $hour, $minute, $second, config('app.timezone'));
    }

    /** Normalizes Persian and Arabic numerals to ASCII for validation/storage boundaries. */
    public function latinDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /** Converts ASCII digits to Persian digits for storefront/admin presentation. */
    public function toPersianDigits(string $value): string
    {
        return strtr($value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    /** Converts Gregorian Y/M/D to Jalali Y/M/D using the 2820-cycle-compatible arithmetic algorithm. */
    public function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $monthOffsets = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $monthOffsets[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            return [$jy, 1 + intdiv($days, 31), 1 + ($days % 31)];
        }

        return [$jy, 7 + intdiv($days - 186, 30), 1 + (($days - 186) % 30)];
    }

    /** Converts Jalali Y/M/D to Gregorian Y/M/D for database/query boundaries. */
    public function jalaliToGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd;
        $days += $jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186;

        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;

        if ($days > 36524) {
            $days--;
            $gy += 100 * intdiv($days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }

        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $leap = ($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0;
        $monthDays = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 1;
        while ($gm <= 12 && $gd > $monthDays[$gm]) {
            $gd -= $monthDays[$gm];
            $gm++;
        }

        return [$gy, $gm, $gd];
    }
}
