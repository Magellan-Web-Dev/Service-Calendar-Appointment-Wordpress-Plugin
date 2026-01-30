<?php
/**
 * Holiday helper utilities
 *
 * @package CalendarServiceAppointmentsForm\Core
 */

namespace CalendarServiceAppointmentsForm\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Holidays {

    /**
     * Get US holiday list for a given year.
     *
     * @param int $year
     * @return array
     */
    public static function get_us_holidays_for_year($year) {
        $year = (int) $year;
        $holidays = [];

        $holidays[] = self::holiday_entry('new_years_day', "New Year's Day", self::observed_date(self::fixed_date($year, 1, 1), $year));
        $holidays[] = self::holiday_entry('mlk_day', 'Martin Luther King Jr. Day', self::nth_weekday_of_month($year, 1, 1, 3));
        $holidays[] = self::holiday_entry('presidents_day', "Washington's Birthday", self::nth_weekday_of_month($year, 2, 1, 3));
        $holidays[] = self::holiday_entry('memorial_day', 'Memorial Day', self::last_weekday_of_month($year, 5, 1));
        $holidays[] = self::holiday_entry('juneteenth', 'Juneteenth National Independence Day', self::observed_date(self::fixed_date($year, 6, 19), $year));
        $holidays[] = self::holiday_entry('independence_day', 'Independence Day', self::observed_date(self::fixed_date($year, 7, 4), $year));
        $holidays[] = self::holiday_entry('labor_day', 'Labor Day', self::nth_weekday_of_month($year, 9, 1, 1));
        $holidays[] = self::holiday_entry('columbus_day', 'Columbus Day', self::nth_weekday_of_month($year, 10, 1, 2));
        $holidays[] = self::holiday_entry('veterans_day', 'Veterans Day', self::observed_date(self::fixed_date($year, 11, 11), $year));
        $holidays[] = self::holiday_entry('thanksgiving_day', 'Thanksgiving Day', self::nth_weekday_of_month($year, 11, 4, 4));
        $holidays[] = self::holiday_entry('day_after_thanksgiving', 'Day After Thanksgiving', self::add_days(self::nth_weekday_of_month($year, 11, 4, 4), 1));
        $holidays[] = self::holiday_entry('christmas_day', 'Christmas Day', self::observed_date(self::fixed_date($year, 12, 25), $year));

        return array_values(array_filter($holidays));
    }

    /**
     * Get upcoming US holidays relative to today (past holidays roll into next year).
     *
     * @param string|null $timezone
     * @return array
     */
    public static function get_upcoming_us_holidays($timezone = null) {
        $tz = $timezone ? new \DateTimeZone($timezone) : new \DateTimeZone('UTC');
        $now = new \DateTime('now', $tz);
        $year = (int) $now->format('Y');

        $current = self::get_us_holidays_for_year($year);
        $next = self::get_us_holidays_for_year($year + 1);
        $next_map = [];
        foreach ($next as $holiday) {
            if (!empty($holiday['key'])) {
                $next_map[$holiday['key']] = $holiday;
            }
        }

        $upcoming = [];
        foreach ($current as $holiday) {
            if (empty($holiday['date']) || empty($holiday['key'])) {
                continue;
            }
            $date = \DateTime::createFromFormat('!Y-m-d', $holiday['date'], $tz);
            if (!$date) {
                continue;
            }
            if ($date < $now && isset($next_map[$holiday['key']])) {
                $upcoming[] = $next_map[$holiday['key']];
            } else {
                $upcoming[] = $holiday;
            }
        }

        return $upcoming;
    }

    /**
     * Get holiday key for a date.
     *
     * @param string $date
     * @return string|null
     */
    public static function get_us_holiday_key_for_date($date) {
        if (empty($date)) {
            return null;
        }

        $year = (int) substr($date, 0, 4);
        if ($year <= 0) {
            return null;
        }

        $holidays = self::get_us_holidays_for_year($year);
        foreach ($holidays as $holiday) {
            if (!empty($holiday['date']) && $holiday['date'] === $date) {
                return $holiday['key'];
            }
        }

        return null;
    }

    /**
     * Build a holiday entry.
     *
     * @param string $key
     * @param string $label
     * @param \DateTime $date
     * @return array|null
     */
    private static function holiday_entry($key, $label, $date) {
        if (!$date) {
            return null;
        }

        return [
            'key' => $key,
            'label' => $label,
            'date' => $date->format('Y-m-d'),
            'date_label' => $date->format('F j, Y'),
        ];
    }

    /**
     * Create a fixed date.
     *
     * @param int $year
     * @param int $month
     * @param int $day
     * @return \DateTime
     */
    private static function fixed_date($year, $month, $day) {
        return \DateTime::createFromFormat('!Y-n-j', sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    /**
     * Calculate nth weekday of month (1=Mon..7=Sun).
     *
     * @param int $year
     * @param int $month
     * @param int $weekday
     * @param int $nth
     * @return \DateTime
     */
    private static function nth_weekday_of_month($year, $month, $weekday, $nth) {
        $date = \DateTime::createFromFormat('!Y-n-j', sprintf('%04d-%02d-01', $year, $month));
        if (!$date) {
            return null;
        }

        $current_weekday = (int) $date->format('N');
        $offset = ($weekday - $current_weekday + 7) % 7;
        $date->modify('+' . $offset . ' days');
        if ($nth > 1) {
            $date->modify('+' . (7 * ($nth - 1)) . ' days');
        }

        return $date;
    }

    /**
     * Calculate last weekday of month (1=Mon..7=Sun).
     *
     * @param int $year
     * @param int $month
     * @param int $weekday
     * @return \DateTime
     */
    private static function last_weekday_of_month($year, $month, $weekday) {
        $date = \DateTime::createFromFormat('!Y-n-j', sprintf('%04d-%02d-01', $year, $month));
        if (!$date) {
            return null;
        }

        $date->modify('last day of this month');
        $current_weekday = (int) $date->format('N');
        $offset = ($current_weekday - $weekday + 7) % 7;
        if ($offset > 0) {
            $date->modify('-' . $offset . ' days');
        }

        return $date;
    }

    /**
     * Apply observed holiday rules for fixed-date holidays.
     *
     * @param \DateTime $date
     * @param int $year
     * @return \DateTime
     */
    private static function observed_date($date, $year) {
        if (!$date) {
            return null;
        }

        $dow = (int) $date->format('w'); // 0=Sun,6=Sat
        if ($dow === 6) {
            $observed = clone $date;
            $observed->modify('-1 day');
            if ((int) $observed->format('Y') === (int) $year) {
                return $observed;
            }
        } elseif ($dow === 0) {
            $observed = clone $date;
            $observed->modify('+1 day');
            if ((int) $observed->format('Y') === (int) $year) {
                return $observed;
            }
        }

        return $date;
    }

    /**
     * Add days to a date.
     *
     * @param \DateTime $date
     * @param int $days
     * @return \DateTime
     */
    private static function add_days($date, $days) {
        if (!$date) {
            return null;
        }

        $copy = clone $date;
        $copy->modify(sprintf('%+d days', (int) $days));
        return $copy;
    }
}
