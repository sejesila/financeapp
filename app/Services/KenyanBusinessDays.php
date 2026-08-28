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
     * Hour (24h, local time) before which an Etica transfer made on a
     * business day settles same-day. At/after this hour — or on a
     * non-business day — it settles on the next business day instead.
     */
    private const ETICA_SAME_DAY_CUTOFF_HOUR = 17;

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

    /**
     * Resolve the value_date for a transfer into an interest-gated (Etica)
     * savings account.
     *
     * Single canonical implementation shared by TransferService (manual
     * transfer form) and TransferRecorder (SMS webhook), so the same-day
     * cutoff rule can't drift between the two call sites the way it did
     * before this method existed — each previously had its own inline
     * "always next business day" logic with no cutoff at all.
     *
     * @param Carbon $anchorTime   The moment used to evaluate the same-day
     *                             cutoff against — i.e. "was this submitted
     *                             on a business day before the cutoff hour".
     *                             Callers must pass whichever timestamp
     *                             actually reflects when the transfer
     *                             happened in the real world:
     *                               - TransferService (manual form): the
     *                                 form's $date field may be backdated
     *                                 by the user, so now() is used instead
     *                                 — the wall-clock moment of execution.
     *                               - TransferRecorder (SMS webhook): the
     *                                 SMS confirmation timestamp IS the real
     *                                 transaction time, so that parsed
     *                                 Carbon instance is passed directly
     *                                 rather than now() (which would only
     *                                 reflect whenever the webhook happened
     *                                 to process the message).
     * @param Carbon $transferDate The date recorded on the Transfer row
     *                             itself — used as the anchor for computing
     *                             the *next* business day when the transfer
     *                             doesn't qualify for same-day settlement.
     *                             For TransferRecorder this is normally the
     *                             same instant as $anchorTime; for
     *                             TransferService it's the (possibly
     *                             backdated) form date.
     * @param int    $cutoffHour  Override for testing; defaults to 5pm.
     *
     * @return string  Y-m-d value date.
     */
    public static function resolveEticaValueDate(
        Carbon $anchorTime,
        Carbon $transferDate,
        int $cutoffHour = self::ETICA_SAME_DAY_CUTOFF_HOUR,
    ): string
    {
        $cutoff = $anchorTime->copy()->setTime($cutoffHour, 0, 0);

        $qualifiesForSameDay = self::isBusinessDay($anchorTime)
            && $anchorTime->lt($cutoff);

        return $qualifiesForSameDay
            ? $transferDate->format('Y-m-d')
            : self::nextBusinessDay($transferDate)->format('Y-m-d');
    }
}
