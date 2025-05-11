<?php
require_once __DIR__ . '/../classes/DateConverter.php';
require_once __DIR__ . '/../classes/CalendarHelper.php';
CalendarHelper::init();
$currentDate = CalendarHelper::getCurrentDate();

class Timesheet
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function getTimesheet($user_id, $month, $year)
    {
        $pdo = $this->db->getConnection();

        // Get timesheet with original calendar values
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   SUM(te.total_hours) AS total_hours,
                   pa.hours_allocated AS allocated_hours
            FROM timesheets t
            LEFT JOIN timesheet_entries te ON t.timesheet_id = te.timesheet_id
            LEFT JOIN project_allocations pa ON pa.user_id = t.user_id
            WHERE t.user_id = ? AND t.month = ? AND t.year = ?
            GROUP BY t.timesheet_id
        ");
        $stmt->execute([$user_id, $month, $year]);
        $timesheet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$timesheet) {
            return null;
        }

        // Calculate completion percentage
        $timesheet['completion_percentage'] = $timesheet['allocated_hours'] > 0
            ? round(($timesheet['total_hours'] / $timesheet['allocated_hours']) * 100, 2)
            : 0;

        // Fetch timesheet entries
        $stmt = $pdo->prepare("
            SELECT te.*, p.project_name
            FROM timesheet_entries te
            JOIN projects p ON te.project_id = p.project_id
            WHERE te.timesheet_id = ?
        ");
        $stmt->execute([$timesheet['timesheet_id']]);
        $timesheet['entries'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $timesheet;
    }

    public function getUserProjects($user_id)
    {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            SELECT p.project_id, p.project_name, pa.hours_allocated 
            FROM project_allocations pa
            JOIN projects p ON pa.project_id = p.project_id
            WHERE pa.user_id = ? AND pa.is_active = 1 AND p.is_active = 1
            AND (pa.end_date >= CURDATE() OR pa.end_date IS NULL)
        ");
        $stmt->execute([$user_id]);

        return $stmt->fetchAll();
    }

    public function saveTimesheet($data, $user_id, $month, $year, $is_admin = false)
    {
        $calendarType = CalendarHelper::isEthiopian() ? 'ethiopian' : 'gregorian';
        $pdo = $this->db->getConnection();

        try {
            $pdo->beginTransaction();

            // Check if this is a future timesheet (for non-admins)
            if (!$is_admin && $this->isFutureTimesheet($month, $year, $calendarType)) {
                throw new Exception("Cannot create/edit future timesheets");
            }

            // Check if already submitted
            $stmt = $pdo->prepare("
                SELECT timesheet_id, status FROM timesheets 
                WHERE user_id = ? AND month = ? AND year = ? AND calendar_type = ?
            ");
            $stmt->execute([$user_id, $month, $year, $calendarType]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            // If already submitted and trying to submit again, reject
            if ($existing && $existing['status'] === 'submitted' && isset($data['action']) && $data['action'] === 'submit') {
                throw new Exception("You have already submitted this timesheet");
            }

            // Get or create timesheet
            if ($existing) {
                $timesheet_id = $existing['timesheet_id'];
                $status = isset($data['action']) && $data['action'] === 'submit' ? 'submitted' : $existing['status'];

                // Only allow status change from draft to submitted
                if ($existing['status'] === 'draft' && $status === 'submitted') {
                    $stmt = $pdo->prepare("
                        UPDATE timesheets SET status = ?, submitted_at = NOW()
                        WHERE timesheet_id = ?
                    ");
                    $stmt->execute([$status, $timesheet_id]);
                }
            } else {
                $status = isset($data['action']) && $data['action'] === 'submit' ? 'submitted' : 'draft';

                $stmt = $pdo->prepare("
                    INSERT INTO timesheets (user_id, month, year, calendar_type, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $month, $year, $calendarType, $status]);
                $timesheet_id = $pdo->lastInsertId();
            }

            // Save entries only if we have project data
            if (isset($data['project']) && is_array($data['project'])) {
                foreach ($data['project'] as $project_id => $days) {
                    // Check if entry exists
                    $stmt = $pdo->prepare("
                        SELECT entry_id FROM timesheet_entries
                        WHERE timesheet_id = ? AND project_id = ?
                    ");
                    $stmt->execute([$timesheet_id, $project_id]);
                    $entry_id = $stmt->fetchColumn();

                    // Prepare data for update/insert
                    $columns = ['timesheet_id' => $timesheet_id, 'project_id' => $project_id];
                    $total_hours = 0;

                    for ($day = 1; $day <= 31; $day++) {
                        $hour_value = $days["day_$day"] ?? 0;
                        $columns["day_$day"] = $hour_value;
                        $total_hours += $hour_value;
                    }

                    $columns['total_hours'] = $total_hours;

                    if ($entry_id) {
                        // Update existing entry
                        $setParts = [];
                        foreach ($columns as $col => $val) {
                            if ($col !== 'timesheet_id' && $col !== 'project_id') {
                                $setParts[] = "$col = :$col";
                            }
                        }

                        $sql = "UPDATE timesheet_entries SET " . implode(', ', $setParts) . "
                                WHERE timesheet_id = :timesheet_id AND project_id = :project_id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($columns);
                    } else {
                        // Insert new entry
                        $cols = array_keys($columns);
                        $values = ':' . implode(', :', $cols);

                        $sql = "INSERT INTO timesheet_entries (" . implode(', ', $cols) . ")
                                VALUES ($values)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($columns);
                    }
                }
            }

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Timesheet save error: " . $e->getMessage());
            $_SESSION['error_message'] = $e->getMessage();
            return false;
        }
    }

    public function canEditTimesheet($timesheet_id, $user_id, $is_admin = false)
    {
        if ($is_admin) {
            return true; // Admins can always edit
        }

        // Allow creation of new timesheets
        if (!$timesheet_id) {
            return true;
        }

        $pdo = $this->db->getConnection();

        // Get timesheet details
        $stmt = $pdo->prepare("
            SELECT t.month, t.year, t.status, t.user_id, t.calendar_type 
            FROM timesheets t
            WHERE t.timesheet_id = ?
        ");
        $stmt->execute([$timesheet_id]);
        $timesheet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$timesheet || $timesheet['user_id'] != $user_id) {
            return false; // Not the owner
        }

        // Only allow editing of draft timesheets
        if ($timesheet['status'] !== 'draft') {
            return false;
        }

        // Allow editing of current and past timesheets
        return !$this->isFutureTimesheet($timesheet['month'], $timesheet['year'], $timesheet['calendar_type']);
    }

    public function isFutureTimesheet($month, $year, $calendar_type = 'gregorian')
    {
        if ($calendar_type === 'ethiopian') {
            // Get current Ethiopian date
            $currentEthDate = CalendarHelper::gregorianToEthiopian(date('Y-m-d'));
            list($currentEthYear, $currentEthMonth) = explode('-', $currentEthDate);

            return ($year > $currentEthYear) ||
                ($year == $currentEthYear && $month > $currentEthMonth);
        }

        // Gregorian calendar comparison
        $currentYear = date('Y');
        $currentMonth = date('n');

        return ($year > $currentYear) ||
            ($year == $currentYear && $month > $currentMonth);
    }

    public function canSubmitPastTimesheet($month, $year, $calendar_type)
    {
        if ($this->isFutureTimesheet($month, $year, $calendar_type)) {
            return false;
        }

        // Allow submission within 5 days of month end
        $deadline = $this->getMonthEndDate($month, $year, $calendar_type)->modify('+5 days');
        return new DateTime() <= $deadline;
    }

    private static function isLeapYear($year)
    {
        // Ethiopian leap year calculation
        return ($year % 4) == 3;
    }

    private function getMonthEndDate($month, $year, $calendar_type)
    {
        if ($calendar_type === 'ethiopian') {
            $days = ($month == 13) ? (self::isLeapYear($year) ? 6 : 5) : 30;
            return new DateTime("$year-$month-$days");
        }

        $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        return new DateTime("$year-$month-$days");
    }

    public function getSubmittedTimesheets($user_id)
    {
        $pdo = (new Database())->getConnection();

        $stmt = $pdo->prepare("
            SELECT t.*, SUM(te.total_hours) as total_hours
            FROM timesheets t
            LEFT JOIN timesheet_entries te ON t.timesheet_id = te.timesheet_id
            WHERE t.user_id = ? AND t.status IN ('submitted', 'approved')
            GROUP BY t.timesheet_id
            ORDER BY t.year DESC, t.month DESC
        ");
        $stmt->execute([$user_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllUserTimesheets($user_id, $month = null, $year = null, $calendar_type = null)
    {
        $pdo = $this->db->getConnection();

        $sql = "
        SELECT t.*, 
               SUM(te.total_hours) as total_hours,
               t.calendar_type,
               t.created_at,
               t.updated_at
        FROM timesheets t
        LEFT JOIN timesheet_entries te ON t.timesheet_id = te.timesheet_id
        WHERE t.user_id = ?
    ";

        $params = [$user_id];
        $conditions = [];

        if ($month !== null) {
            $conditions[] = "t.month = ?";
            $params[] = $month;
        }

        if ($year !== null) {
            $conditions[] = "t.year = ?";
            $params[] = $year;
        }

        if ($calendar_type !== null) {
            $conditions[] = "t.calendar_type = ?";
            $params[] = $calendar_type;
        }

        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        $sql .= " GROUP BY t.timesheet_id ORDER BY t.year DESC, t.month DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
