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

        // Remove calendar type filter from query to find any matching timesheet
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

    public function getLeaveTypes()
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->query("SELECT * FROM leave_types WHERE is_active = 1");
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

                // Update existing timesheet
                $stmt = $pdo->prepare("
                    UPDATE timesheets 
                    SET status = ?, updated_at = NOW()
                    WHERE timesheet_id = ?
                ");
                $stmt->execute([$status, $timesheet_id]);
            } else {
                $status = isset($data['action']) && $data['action'] === 'submit' ? 'submitted' : 'draft';

                $stmt = $pdo->prepare("
                    INSERT INTO timesheets (user_id, month, year, calendar_type, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
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

            // Update timesheet status if submitted
            if (isset($data['action']) && $data['action'] === 'submit') {
                $stmt = $pdo->prepare("
                    UPDATE timesheets 
                    SET status = 'submitted', submitted_at = NOW(), updated_at = NOW()
                    WHERE timesheet_id = ?
                ");
                $stmt->execute([$timesheet_id]);
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

    public function getTimesheetCompletion($timesheet_id)
    {
        $pdo = $this->db->getConnection();

        // Get total allocated hours
        $stmt = $pdo->prepare("
            SELECT SUM(pa.hours_allocated) as allocated_hours
            FROM project_allocations pa
            JOIN timesheets t ON pa.user_id = t.user_id
            WHERE t.timesheet_id = ?
            AND (t.month BETWEEN MONTH(pa.start_date) AND MONTH(pa.end_date))
            AND (t.year BETWEEN YEAR(pa.start_date) AND YEAR(pa.end_date))
        ");
        $stmt->execute([$timesheet_id]);
        $allocated = $stmt->fetchColumn();

        // Get total logged hours
        $stmt = $pdo->prepare("
            SELECT SUM(total_hours) as logged_hours
            FROM timesheet_entries
            WHERE timesheet_id = ?
        ");
        $stmt->execute([$timesheet_id]);
        $logged = $stmt->fetchColumn();

        return [
            'allocated_hours' => $allocated ?? 0,
            'logged_hours' => $logged ?? 0,
            'completion_percentage' => $allocated > 0 ? round(($logged / $allocated) * 100, 2) : 0
        ];
    }

    public function getSubmittedTimesheets($user_id)
    {
        $pdo = $this->db->getConnection();

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

    public function approveTimesheet($timesheet_id, $approver_id)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            UPDATE timesheets 
            SET status = 'approved', 
                approved_at = NOW(), 
                approved_by = ?,
                rejection_reason = NULL,
                updated_at = NOW()
            WHERE timesheet_id = ?
        ");
        return $stmt->execute([$approver_id, $timesheet_id]);
    }

    public function rejectTimesheet($timesheet_id, $reason)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            UPDATE timesheets 
            SET status = 'rejected', 
                approved_at = NULL,
                approved_by = NULL,
                rejection_reason = ?,
                updated_at = NOW()
            WHERE timesheet_id = ?
        ");
        return $stmt->execute([$reason, $timesheet_id]);
    }

    public function getTimesheetDetails($timesheet_id)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT ts.*, u.first_name, u.last_name, u.role 
            FROM timesheets ts
            JOIN users u ON ts.user_id = u.user_id
            WHERE ts.timesheet_id = ?
        ");
        $stmt->execute([$timesheet_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function hasSubmittedTimesheet($user_id, $month, $year)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM timesheets 
            WHERE user_id = ? AND month = ? AND year = ? AND status = 'submitted'
        ");
        $stmt->execute([$user_id, $month, $year]);
        return $stmt->fetchColumn() > 0;
    }

    public function getAllUserTimesheets($user_id, $month = null, $year = null)
    {
        $pdo = $this->db->getConnection();
        $currentCalendar = CalendarHelper::isEthiopian() ? 'ethiopian' : 'gregorian';

        $sql = "
            SELECT t.*, 
                   SUM(te.total_hours) as total_hours,
                   u.first_name, u.last_name
            FROM timesheets t
            LEFT JOIN timesheet_entries te ON t.timesheet_id = te.timesheet_id
            LEFT JOIN users u ON t.user_id = u.user_id
            WHERE t.user_id = ?
        ";

        $params = [$user_id];

        if ($month !== null && $year !== null) {
            // Add calendar type condition to the query
            $sql .= " AND t.calendar_type = ?";
            $params[] = $currentCalendar;

            $sql .= " AND t.month = ? AND t.year = ?";
            $params[] = $month;
            $params[] = $year;
        }

        $sql .= " GROUP BY t.timesheet_id ORDER BY t.year DESC, t.month DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $timesheets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert display values without changing stored values
        foreach ($timesheets as &$ts) {
            $display = CalendarHelper::getDisplayDate(
                $ts['month'],
                $ts['year'],
                $ts['calendar_type']
            );
            $ts['display_month'] = $display['month'];
            $ts['display_year'] = $display['year'];
            $ts['display_month_name'] = $display['month_name'];
        }

        return $timesheets;
    }

    public function canEditTimesheet($timesheet_id, $user_id, $is_admin = false)
    {
        if ($is_admin) {
            return true; // Admins can always edit
        }

        $pdo = $this->db->getConnection();

        // Get timesheet details including month/year and status
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

        // Cannot edit submitted/approved timesheets
        if ($timesheet['status'] !== 'draft') {
            return false;
        }

        // Check if this is a future timesheet (no editing allowed for non-admins)
        if ($this->isFutureTimesheet($timesheet['month'], $timesheet['year'], $timesheet['calendar_type'])) {
            return false;
        }

        // For past timesheets, allow editing within a reasonable timeframe
        return true;
    }

    public function isFutureTimesheet($month, $year, $calendar_type = 'gregorian')
    {
        if ($calendar_type === 'ethiopian') {
            // Convert Ethiopian date to Gregorian for comparison
            $ethDate = sprintf('%04d-%02d-01', $year, $month);
            $gcDate = CalendarHelper::ethiopianToGregorian($ethDate);
            list($gcYear, $gcMonth) = explode('-', $gcDate);

            $currentYear = date('Y');
            $currentMonth = date('n');

            return ($gcYear > $currentYear) ||
                ($gcYear == $currentYear && $gcMonth > $currentMonth);
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
}
