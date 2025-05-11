<?php
class CalendarHelper {
    private static $ethiopianCalendar = false;

    public static function init() {
        self::$ethiopianCalendar = $_SESSION['ethiopian_calendar'] ?? false;
    }

    public static function isEthiopian() {
        return self::$ethiopianCalendar;
    }

    public static function getCurrentDate() {
        if (self::$ethiopianCalendar) {
            return self::gregorianToEthiopian(date('Y-m-d'));
        }
        return date('Y-m-d');
    }


    public static function gregorianToEthiopian($gregorianDate)
    {
        $date = new DateTime($gregorianDate);
        $year = (int)$date->format('Y');
        $month = (int)$date->format('m');
        $day = (int)$date->format('d');

        // Calculate Ethiopian date
        $ethYear = $year - 7;

        if ($month < 9 || ($month == 9 && $day < 11)) {
            $ethYear--;
        }

        $ethMonth = (($month + 3) % 12) + 1;
        $ethDay = $day;

        // Adjust for month differences
        $monthDiffs = [
            1 => 8,
            2 => 7,
            3 => 9,
            4 => 8,
            5 => 8,
            6 => 7,
            7 => 7,
            8 => 6,
            9 => [5, 6],
            10 => [10, 11],
            11 => [9, 10],
            12 => [9, 10]
        ];

        if ($month == 9) {
            $isLeap = ($year % 4 == 0);
            $diff = $isLeap ? $monthDiffs[$month][1] : $monthDiffs[$month][0];
            if ($day <= $diff + ($isLeap ? 1 : 0)) {
                $ethMonth = 13;
                $ethDay = $day - $diff + ($isLeap ? 1 : 0);
            }
        } else {
            $diff = is_array($monthDiffs[$month]) ? $monthDiffs[$month][0] : $monthDiffs[$month];
            $ethDay = $day - $diff;

            if ($ethDay <= 0) {
                $ethMonth--;
                if ($ethMonth < 1) $ethMonth = 12;
                $ethDay += 30;
            }
        }

        return sprintf('%04d-%02d-%02d', $ethYear, $ethMonth, $ethDay);
    }

    public static function ethiopianToGregorian($ethiopianDate)
    {
        list($ethYear, $ethMonth, $ethDay) = explode('-', $ethiopianDate);
        $ethYear = (int)$ethYear;
        $ethMonth = (int)$ethMonth;
        $ethDay = (int)$ethDay;

        // Handle Pagume (13th month)
        if ($ethMonth == 13) {
            $isLeap = ($ethYear % 4 == 3);
            $gcYear = $ethYear + 7;
            $gcMonth = 9;
            $gcDay = $ethDay + ($isLeap ? 5 : 6);

            if ($gcDay > 30) {
                $gcDay -= 30;
                $gcMonth++;
            }

            return sprintf('%04d-%02d-%02d', $gcYear, $gcMonth, $gcDay);
        }

        // Calculate Gregorian year
        $gcYear = $ethYear + 8;
        if ($ethMonth >= 1 && $ethMonth <= 4) {
            $gcMonth = $ethMonth + 8;
        } else {
            $gcMonth = $ethMonth - 4;
            if ($gcMonth < 1) {
                $gcMonth += 12;
                $gcYear--;
            }
        }

        // Calculate Gregorian day
        $monthDiffs = [
            1 => 8,
            2 => 7,
            3 => 9,
            4 => 8,
            5 => 8,
            6 => 7,
            7 => 7,
            8 => 6,
            9 => [5, 6],
            10 => [10, 11],
            11 => [9, 10],
            12 => [9, 10]
        ];

        $diff = is_array($monthDiffs[$gcMonth]) ?
            (($gcYear % 4 == 0) ? $monthDiffs[$gcMonth][1] : $monthDiffs[$gcMonth][0]) :
            $monthDiffs[$gcMonth];

        $gcDay = $ethDay + $diff;

        // Adjust for month length
        $monthLength = cal_days_in_month(CAL_GREGORIAN, $gcMonth, $gcYear);
        if ($gcDay > $monthLength) {
            $gcDay -= $monthLength;
            $gcMonth++;
            if ($gcMonth > 12) {
                $gcMonth = 1;
                $gcYear++;
            }
        }

        return sprintf('%04d-%02d-%02d', $gcYear, $gcMonth, $gcDay);
    }

    public static function getMonthName($month, $year = null) {
        if (self::$ethiopianCalendar) {
            $ethiopianMonths = [
                "መስከረም", "ጥቅምት", "ህዳር", "ታህሳስ", 
                "ጥር", "የካቲት", "መጋቢት", "ሚያዚያ", 
                "ግንቦት", "ሰኔ", "ሐምሌ", "ነሀሴ", "ጳጉሜ"
            ];
            return $ethiopianMonths[$month - 1] ?? '';
        }
        return date('F', mktime(0, 0, 0, $month, 1, $year ?? date('Y')));
    }

    public static function getDayName($day, $month, $year) {
        if (self::$ethiopianCalendar) {
            $ethiopianDays = ["እሁድ", "ሰኞ", "ማክሰኞ", "ረቡዕ", "ሐሙስ", "ዓርብ", "ቅዳሜ"];
            $timestamp = mktime(0, 0, 0, $month, $day, $year);
            $dayOfWeek = date('w', $timestamp); // 0 (Sunday) through 6 (Saturday)
            return $ethiopianDays[$dayOfWeek] ?? '';
        }
        return date('l', mktime(0, 0, 0, $month, $day, $year));
    }

    public static function getDaysInMonth($month, $year) {
        if (self::$ethiopianCalendar) {
            // Ethiopian months have 30 days each except Pagume (13th month) which has 5 or 6
            return ($month == 13) ? (self::isLeapYear($year) ? 6 : 5) : 30;
        }
        return cal_days_in_month(CAL_GREGORIAN, $month, $year);
    }

    private static function isLeapYear($year) {
        // Ethiopian leap year calculation
        return ($year % 4) == 3;
    }

    public static function displayDate($date, $format = 'Y-m-d')
    {
        if (self::isEthiopian()) {
            if (strpos($date, '-') !== false) {
                // Assume it's Gregorian if it has dashes
                $ethDate = self::gregorianToEthiopian($date);
                return self::formatEthiopianDate($ethDate, $format);
            }
            // Otherwise assume it's already Ethiopian
            return self::formatEthiopianDate($date, $format);
        }
        // For Gregorian calendar
        return date($format, strtotime($date));
    }

    private static function formatEthiopianDate($ethiopianDate, $format)
    {
        list($year, $month, $day) = explode('-', $ethiopianDate);

        $replacements = [
            'Y' => $year,
            'y' => substr($year, -2),
            'm' => str_pad($month, 2, '0', STR_PAD_LEFT),
            'n' => $month,
            'd' => str_pad($day, 2, '0', STR_PAD_LEFT),
            'j' => $day,
            'M' => self::getMonthName($month),
            'F' => self::getMonthName($month),
        ];

        $formatted = $format;
        foreach ($replacements as $key => $value) {
            $formatted = str_replace($key, $value, $formatted);
        }

        return $formatted;
    }

    public static function displayMonthYear($month, $year)
    {
        $currentCalendar = self::isEthiopian() ? 'ethiopian' : 'gregorian';

        // If the stored calendar type is different from current, convert
        if (($year > 1900 && $year < 2100 && $currentCalendar === 'ethiopian') ||
            ($year < 1900 && $currentCalendar === 'gregorian')
        ) {

            $dateStr = sprintf('%04d-%02d-15', $year, $month);
            $converted = $currentCalendar === 'ethiopian'
                ? self::gregorianToEthiopian($dateStr)
                : self::ethiopianToGregorian($dateStr);

            list($convYear, $convMonth) = explode('-', $converted);
            return [
                'month' => self::getMonthName($convMonth),
                'year' => $convYear
            ];
        }

        // Already in correct calendar system
        return [
            'month' => self::getMonthName($month),
            'year' => $year
        ];
    }


    public static function getDisplayDate($month, $year, $originalCalendar)
    {
        $currentCalendar = self::isEthiopian() ? 'ethiopian' : 'gregorian';

        if ($currentCalendar === $originalCalendar) {
            return [
                'month' => $month,
                'year' => $year,
                'month_name' => self::getMonthName($month, $year)
            ];
        }

        // Convert to current calendar system
        $dateStr = sprintf('%04d-%02d-15', $year, $month);

        if ($originalCalendar === 'ethiopian') {
            $converted = self::ethiopianToGregorian($dateStr);
        } else {
            $converted = self::gregorianToEthiopian($dateStr);
        }

        list($convYear, $convMonth) = explode('-', $converted);

        return [
            'month' => (int)$convMonth,
            'year' => (int)$convYear,
            'month_name' => self::getMonthName((int)$convMonth, (int)$convYear)
        ];
    }

    public static function getUrlParams($month, $year, $originalCalendar)
    {
        $currentCalendar = self::isEthiopian() ? 'ethiopian' : 'gregorian';

        if ($currentCalendar === $originalCalendar) {
            return [
                'month' => $month,
                'year' => $year
            ];
        }

        // Convert to current calendar system for URL
        $dateStr = sprintf('%04d-%02d-15', $year, $month);

        if ($originalCalendar === 'ethiopian') {
            $converted = self::ethiopianToGregorian($dateStr);
        } else {
            $converted = self::gregorianToEthiopian($dateStr);
        }

        list($convYear, $convMonth) = explode('-', $converted);

        return [
            'month' => (int)$convMonth,
            'year' => (int)$convYear
        ];
    }

        public static function shouldConvertCalendar($storedCalendarType)
    {
        $currentCalendar = self::isEthiopian() ? 'ethiopian' : 'gregorian';
        
        // Check if URL has explicit calendar type parameter
        if (isset($_GET['cal'])) {
            return $_GET['cal'] !== $currentCalendar;
        }
        
        
        // Default comparison
        return $storedCalendarType !== $currentCalendar;
    }


}

// Initialize the calendar helper
CalendarHelper::init();
?>