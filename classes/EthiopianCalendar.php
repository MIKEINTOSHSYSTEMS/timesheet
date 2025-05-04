<?php
class EthiopianCalendar {
    private $year;
    private $month;
    private $day;
    private $ethiopianYear;
    private $ethiopianMonth;
    private $ethiopianDay;
    private $isEthiopian;

    public function __construct($date = null, $isEthiopian = false) {
        if ($date === null) {
            $date = new DateTime();
        } elseif (is_string($date)) {
            $date = new DateTime($date);
        }

        $this->isEthiopian = $isEthiopian;
        
        if ($isEthiopian) {
            $this->ethiopianYear = (int)$date->format('Y');
            $this->ethiopianMonth = (int)$date->format('m');
            $this->ethiopianDay = (int)$date->format('d');
            $this->convertEthiopianToGregorian();
        } else {
            $this->year = (int)$date->format('Y');
            $this->month = (int)$date->format('m');
            $this->day = (int)$date->format('d');
            $this->convertGregorianToEthiopian();
        }
    }

    private function convertGregorianToEthiopian() {
        // Implementation of Gregorian to Ethiopian conversion
        $jd = GregorianToJD($this->month, $this->day, $this->year);
        $ethiopianDate = JDToEthiopian($jd);
        
        $this->ethiopianYear = $ethiopianDate[0];
        $this->ethiopianMonth = $ethiopianDate[1];
        $this->ethiopianDay = $ethiopianDate[2];
    }

    private function convertEthiopianToGregorian() {
        // Implementation of Ethiopian to Gregorian conversion
        $jd = EthiopianToJD($this->ethiopianMonth, $this->ethiopianDay, $this->ethiopianYear);
        $gregorianDate = JDToGregorian($jd);
        
        list($month, $day, $year) = explode('/', $gregorianDate);
        $this->year = (int)$year;
        $this->month = (int)$month;
        $this->day = (int)$day;
    }

    public function getYear() {
        return $this->isEthiopian ? $this->ethiopianYear : $this->year;
    }

    public function getMonth() {
        return $this->isEthiopian ? $this->ethiopianMonth : $this->month;
    }

    public function getDay() {
        return $this->isEthiopian ? $this->ethiopianDay : $this->day;
    }

    public function getMonthName() {
        $monthNames = [
            1 => 'መስከረም', 2 => 'ጥቅምት', 3 => 'ኅዳር', 4 => 'ታኅሣሥ',
            5 => 'ጥር', 6 => 'የካቲት', 7 => 'መጋቢት', 8 => 'ሚያዝያ',
            9 => 'ግንቦት', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜ'
        ];
        
        $month = $this->getMonth();
        return $monthNames[$month] ?? '';
    }

    public function getShortMonthName() {
        $monthNames = [
            1 => 'መስከ', 2 => 'ጥቅም', 3 => 'ኅዳር', 4 => 'ታኅሣ',
            5 => 'ጥር', 6 => 'የካቲ', 7 => 'መጋቢ', 8 => 'ሚያዝ',
            9 => 'ግንቦ', 10 => 'ሰኔ', 11 => 'ሐምሌ', 12 => 'ነሐሴ', 13 => 'ጳጉሜ'
        ];
        
        $month = $this->getMonth();
        return $monthNames[$month] ?? '';
    }

    public function getDayName($timestamp = null) {
        $dayNames = ['ሰኞ', 'ማክሰኞ', 'ረቡዕ', 'ሐሙስ', 'ዓርብ', 'ቅዳሜ', 'እሁድ'];
        
        if ($timestamp === null) {
            $timestamp = $this->isEthiopian ? 
                $this->getGregorianTimestamp() : 
                mktime(0, 0, 0, $this->month, $this->day, $this->year);
        }
        
        $dayOfWeek = (int)date('N', $timestamp);
        return $dayNames[$dayOfWeek - 1] ?? '';
    }

    public function getGregorianTimestamp() {
        return mktime(0, 0, 0, $this->month, $this->day, $this->year);
    }

    public function getEthiopianTimestamp() {
        return mktime(0, 0, 0, $this->ethiopianMonth, $this->ethiopianDay, $this->ethiopianYear);
    }

    public function format($format) {
        if ($this->isEthiopian) {
            return $this->formatEthiopian($format);
        }
        return $this->formatGregorian($format);
    }

    private function formatGregorian($format) {
        $timestamp = $this->getGregorianTimestamp();
        return date($format, $timestamp);
    }

    private function formatEthiopian($format) {
        $replacements = [
            'd' => sprintf('%02d', $this->ethiopianDay),
            'j' => $this->ethiopianDay,
            'm' => sprintf('%02d', $this->ethiopianMonth),
            'n' => $this->ethiopianMonth,
            'Y' => $this->ethiopianYear,
            'y' => substr($this->ethiopianYear, -2),
            'F' => $this->getMonthName(),
            'M' => $this->getShortMonthName(),
            'l' => $this->getDayName(),
            'D' => substr($this->getDayName(), 0, 3),
        ];

        $result = '';
        $length = strlen($format);
        for ($i = 0; $i < $length; $i++) {
            $char = $format[$i];
            if ($char === '\\') {
                $result .= $format[++$i] ?? '';
            } else {
                $result .= $replacements[$char] ?? $char;
            }
        }

        return $result;
    }

    public function getDaysInMonth() {
        if ($this->isEthiopian) {
            // Ethiopian months have 30 days except the 13th month which has 5 or 6
            return $this->ethiopianMonth === 13 ? 
                ($this->ethiopianYear % 4 === 3 ? 6 : 5) : 
                30;
        }
        return cal_days_in_month(CAL_GREGORIAN, $this->month, $this->year);
    }

    public static function isLeapYear($year, $isEthiopian = false) {
        if ($isEthiopian) {
            return $year % 4 === 3;
        }
        return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
    }
}

// Helper functions for JD conversion
if (!function_exists('GregorianToJD')) {
    function GregorianToJD($month, $day, $year) {
        if ($month < 3) {
            $month += 12;
            $year -= 1;
        }
        $a = (int)($year / 100);
        $b = (int)($a / 4);
        $c = 2 - $a + $b;
        $d = (int)(365.25 * ($year + 4716));
        $e = (int)(30.6001 * ($month + 1));
        return $c + $d + $e + $day - 1524.5;
    }
}

if (!function_exists('JDToGregorian')) {
    function JDToGregorian($julian) {
        $julian += 0.5;
        $z = (int)$julian;
        $f = $julian - $z;
        
        if ($z < 2299161) {
            $a = $z;
        } else {
            $alpha = (int)(($z - 1867216.25) / 36524.25);
            $a = $z + 1 + $alpha - (int)($alpha / 4);
        }
        
        $b = $a + 1524;
        $c = (int)(($b - 122.1) / 365.25);
        $d = (int)(365.25 * $c);
        $e = (int)(($b - $d) / 30.6001);
        
        $day = $b - $d - (int)(30.6001 * $e) + $f;
        $month = $e < 14 ? $e - 1 : $e - 13;
        $year = $month > 2 ? $c - 4716 : $c - 4715;
        
        return "$month/$day/$year";
    }
}

if (!function_exists('EthiopianToJD')) {
    function EthiopianToJD($month, $day, $year) {
        $jd = GregorianToJD(9, 11, $year + 8);
        return $jd + ($month - 1) * 30 + $day - 1;
    }
}

if (!function_exists('JDToEthiopian')) {
    function JDToEthiopian($julian) {
        $gregorian = JDToGregorian($julian);
        list($month, $day, $year) = explode('/', $gregorian);
        
        $ethYear = $year - 8;
        $jdEpoch = GregorianToJD(9, 11, $ethYear);
        $diff = $julian - $jdEpoch;
        
        if ($diff < 0) {
            $ethYear--;
            $jdEpoch = GregorianToJD(9, 11, $ethYear);
            $diff = $julian - $jdEpoch;
        }
        
        $ethMonth = (int)($diff / 30) + 1;
        $ethDay = ($diff % 30) + 1;
        
        return [$ethYear, $ethMonth, $ethDay];
    }
}