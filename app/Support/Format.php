<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Czech formatting helpers used by views and Discord messages.
 */
class Format
{
    /** "14. 11. 2026 14:20" in the display time zone. */
    public static function datetime(?CarbonInterface $at): string
    {
        if ($at === null) {
            return '';
        }

        return $at->copy()->setTimezone(config('app.display_timezone'))->format('j. n. Y H:i');
    }

    /** "14. 11. 2026" in the display time zone. */
    public static function date(?CarbonInterface $at): string
    {
        if ($at === null) {
            return '';
        }

        return $at->copy()->setTimezone(config('app.display_timezone'))->format('j. n. Y');
    }

    /** "před 2 hodinami" */
    public static function relative(?CarbonInterface $at): string
    {
        return $at?->locale(app()->getLocale())->diffForHumans() ?? '';
    }

    /** "42,6 %" */
    public static function percent(float $value, int $decimals = 1): string
    {
        return __('app.percent', ['value' => self::number($value, $decimals)]);
    }

    /** Czech decimal comma, thin grouping: 1 500 */
    public static function number(float|int $value, int $decimals = 0): string
    {
        return number_format($value, $decimals, ',', "\u{00A0}");
    }

    /** "1,9 MB" */
    public static function bytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return self::number($bytes / 1024 / 1024, 1).' MB';
        }

        return self::number(max(1, (int) round($bytes / 1024))).' kB';
    }
}
