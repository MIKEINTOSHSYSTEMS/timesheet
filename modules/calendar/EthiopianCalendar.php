<?php
class EthiopianCalendar
{
    private $FIVE = 5;
    private $SIX = 6;
    private $GregorianMonthLength = [31, [29, 28], 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    private $EthiopianMonth = [5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3, 4];
    private $GregorianMonthName = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    private $EthiopianMonthName = ["መስከረም", "ጥቅምት", "ኅዳር", "ታኅሣሥ", "ጥር", "የካቲት", "መጋቢት", "ሚያዝያ", "ግንቦት", "ሰኔ", "ሐምሌ", "ነሐሴ", "ጳጉሜ"];
    private $GregorianDayName = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
    private $EthiopianDayName = ["ሰኞ", "ማክሰኞ", "ረቡዕ", "ሐሙስ", "ዓርብ", "ቅዳሜ", "እሑድ"];
    private $WEEK_DAY_LIST = ["Monday" => 0, "Tuesday" => -1, "Wednesday" => -2, "Thursday" => -3, "Friday" => -4, "Saturday" => -5, "Sunday" => -6];
    private $MonthDifference = [8, 7, 9, 8, 8, 7, 7, 6, [5, 6], [10, 11], [9, 10], [9, 10]];

    private $year;
    private $month;
    private $day;
    private $leap_year;
    private $leap_year_index;
    private $EC_year;
    private $EC_month;
    private $EC_day;
    private $GCDate;
    private $Converted = false;

    public function __construct($timestamp = null)
    {
        if ($timestamp === null) {
            $timestamp = time();
        }
        $date = getdate($timestamp);
        $this->year = $date['year'];
        $this->month = $date['mon'];
        $this->day = $date['mday'];
        $this->convert();
    }

    private function convert()
    {
        $this->SetLeapYear();
    }

    private function YearDifference()
    {
        // Assuming the Ethiopian calendar is 8 years behind the Gregorian calendar
        return 8;
    }

    private function SetLeapYear()
    {
        $this->leap_year = ($this->year % 4 == 0 && ($this->year % 100 != 0 || $this->year % 400 == 0));
        $this->leap_year_index = $this->leap_year ? 0 : 1;
    }

    private function LeapYearAddition()
    {
        // Return 1 if it's a leap year, otherwise return 0
        return $this->leap_year ? 1 : 0;
    

        $EC_year = $this->year - $this->YearDifference();
        $EC_month = $this->EthiopianMonth[$this->month - 1];
        $EC_day = 0;

        if ($this->month >= 9 && $this->month <= 12) {
            if ($this->month == 9) {
                $EC_day = $this->day - ($this->MonthDifference[$this->month - 1][$this->leap_year_index] + $this->leap_year_index + $this->LeapYearAddition());
                if ($EC_day >= -5 && $EC_day <= 0) {
                    $EC_day += 6;
                    $EC_month = 13;
                } else if ($EC_day == 0) {
                    $EC_day = 1;
                } else if ($EC_day < -5) {
                    $EC_day += 6;
                } else {
                    $EC_day += 1;
                }
            } else {
                $EC_day = $this->day - $this->MonthDifference[$this->month - 1][$this->leap_year_index];
            }
        } else {
            $EC_day = $this->day - $this->MonthDifference[$this->month - 1];
        }

        if ($EC_day <= 0) {
            $EC_day = 30 + $EC_day;
            if ($this->EthiopianMonth[$this->month - 2] != null) {
                $EC_month = $this->EthiopianMonth[$this->month - 2];
            } else {
                $EC_month = $this->EthiopianMonth[($this->month - 2) + count($this->EthiopianMonth)];
            }
        }

        $this->EC_year = $EC_year;
        $this->EC_month = $EC_month;
        $this->EC_day = $EC_day;
        $this->Converted = true;
        $this->GCDate = $date;
    }

    // ... (include all the other methods from your JavaScript version, converted to PHP)

    /**
     * Convert Ethiopian date to Gregorian date
     */
    public function ethiopianToGregorian($ethYear, $ethMonth, $ethDay)
    {
        // Implementation of the reverse conversion
        // This is a simplified version - you'll need to implement the full logic
        $gregorianYear = $ethYear + 8;

        if ($ethMonth <= 4) {
            $gregorianMonth = $ethMonth + 8;
        } else {
            $gregorianMonth = $ethMonth - 4;
            $gregorianYear++;
        }

        $gregorianDay = $ethDay;

        // Adjust for month differences
        if ($ethMonth == 13) {
            // Pagume (13th month)
            $gregorianMonth = 9;
            $gregorianDay += ($this->isEthiopianLeapYear($ethYear) ? 5 : 6);
        }

        return [
            'year' => $gregorianYear,
            'month' => $gregorianMonth,
            'day' => $gregorianDay
        ];
    }

    private function isEthiopianLeapYear($year)
    {
        return ($year % 4) == 3;
    }
}
