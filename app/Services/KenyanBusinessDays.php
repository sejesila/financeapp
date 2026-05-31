<?php

namespace App\Services;

use Carbon\Carbon;

class KenyanBusinessDays
{
    /**
     * Public holidays that are fixed each year (month, day).
     * Floating holidays (Easter) are handled separately.
     */
    private const FIXED_HOLIDAYS = [
        [1,  1],  // New Year's Day
        [5,  1],  // Labour Day
        [6,  1],  // Madaraka Day
        [10, 20], // Mashujaa Day
        [12, 12], // Jamhuri Day
        [12, 25], // Christmas Day
        [12, 26], // Boxing Day
    ];

    /**
     * Returns true if the given date is a Kenyan public holiday.
     */
    public static function isHoliday(Carbon $date): bool
    {
        $month = (int) $date->format('n');
        $day   = (int) $date->format('j');
        $year  = (int) $date->format('Y');

        // Fixed holidays
        foreach (self::FIXED_HOLIDAYS as [$hMonth, $hDay]) {
            if ($month === $hMonth && $day === $hDay) {
                return true;
            }
        }

        // Easter Friday and Easter Monday (floating)
        $easter      = Carbon::createFromTimestamp(easter_date($year));
        $easterFriday = $easter->copy()->subDays(2);
        $easterMonday = $easter->copy()->addDay();

        if ($date->isSameDay($easterFriday) || $date->isSameDay($easterMonday)) {
            return true;
        }

        return false;
    }

    /**
     * Returns true if the given date is a business day
     * (Monday–Friday, not a public holiday).
     */
    public static function isBusinessDay(Carbon $date): bool
    {
        return ! $date->isWeekend() && ! self::isHoliday($date);
    }

    /**
     * Returns the next business day after the given date.
     * e.g. Friday → Monday (if Monday is not a holiday)
     *      Friday before Madaraka Day Monday → Tuesday
     */
    public static function nextBusinessDay(Carbon $date): Carbon
    {
        $next = $date->copy()->addDay();

        while (! self::isBusinessDay($next)) {
            $next->addDay();
        }

        return $next;
    }
}
