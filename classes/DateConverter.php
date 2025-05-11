<?php
class DateConverter
{

    public static function convertMonthYear($month, $year, $fromCalendar, $toCalendar)
    {
        if ($fromCalendar === $toCalendar) {
            return ['month' => $month, 'year' => $year];
        }

        // Create a sample date (using the 15th as a safe middle date)
        $sampleDate = sprintf('%04d-%02d-15', $year, $month);

        if ($fromCalendar === 'ethiopian' && $toCalendar === 'gregorian') {
            $converted = self::toGregorian($sampleDate);
            list($newYear, $newMonth) = explode('-', $converted);
            return ['month' => (int)$newMonth, 'year' => (int)$newYear];
        }

        if ($fromCalendar === 'gregorian' && $toCalendar === 'ethiopian') {
            $converted = self::toEthiopian($sampleDate);
            list($newYear, $newMonth) = explode('-', $converted);
            return ['month' => (int)$newMonth, 'year' => (int)$newYear];
        }

        return ['month' => $month, 'year' => $year];
    }

    public static function toEthiopian($gregorianDate)
    {
        if (empty($gregorianDate)) return '';
        return CalendarHelper::gregorianToEthiopian($gregorianDate);
    }

    public static function toGregorian($ethiopianDate)
    {
        if (empty($ethiopianDate)) return '';
        return CalendarHelper::ethiopianToGregorian($ethiopianDate);
    }

    public static function formatDate($date, $format = 'Y-m-d', $fromGregorian = true)
    {
        if (empty($date)) return '';

        if (CalendarHelper::isEthiopian()) {
            $convertedDate = $fromGregorian ?
                self::toEthiopian($date) :
                $date;

            list($year, $month, $day) = explode('-', $convertedDate);

            // Format according to the Ethiopian calendar
            $replacements = [
                'Y' => $year,
                'y' => substr($year, -2),
                'm' => str_pad($month, 2, '0', STR_PAD_LEFT),
                'n' => $month,
                'd' => str_pad($day, 2, '0', STR_PAD_LEFT),
                'j' => $day,
                'M' => CalendarHelper::getMonthName($month),
                'F' => CalendarHelper::getMonthName($month),
                'D' => CalendarHelper::getDayName($day, $month, $year),
                'l' => CalendarHelper::getDayName($day, $month, $year)
            ];

            $formatted = $format;
            foreach ($replacements as $key => $value) {
                $formatted = str_replace($key, $value, $formatted);
            }

            return $formatted;
        }

        // Default Gregorian formatting
        return date($format, strtotime($date));
    }

    public static function getCurrentYear()
    {
        if (CalendarHelper::isEthiopian()) {
            $currentGregorian = date('Y-m-d');
            $ethiopian = self::toEthiopian($currentGregorian);
            return explode('-', $ethiopian)[0];
        }
        return date('Y');
    }

    public static function getCurrentMonth()
    {
        if (CalendarHelper::isEthiopian()) {
            $currentGregorian = date('Y-m-d');
            $ethiopian = self::toEthiopian($currentGregorian);
            return explode('-', $ethiopian)[1];
        }
        return date('n');
    }
}
