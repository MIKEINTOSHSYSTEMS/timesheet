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
    /*
    public function getTimesheet($user_id, $month, $year) {
        $pdo = (new Database())->getConnection();

        // Fetch timesheet details
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
    */

    public function getTimesheet($user_id, $month, $year)
    {
        // Convert from Ethiopian to Gregorian if needed
        if (CalendarHelper::isEthiopian()) {
            $ethiopianDate = "$year-$month-01";
            $gregorianDate = DateConverter::toGregorian($ethiopianDate);
            list($year, $month) = explode('-', $gregorianDate);
        }

        $pdo = (new Database())->getConnection();

        // Fetch timesheet details
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

        // Convert dates back to Ethiopian if needed
        if (CalendarHelper::isEthiopian()) {
            $timesheet['month'] = DateConverter::toEthiopian("$year-{$timesheet['month']}-01");
            $timesheet['month'] = explode('-', $timesheet['month'])[1];
            $timesheet['year'] = explode('-', $timesheet['month'])[0];
        }

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

    public function saveTimesheet($data, $user_id, $month, $year)
    {
        // Convert from Ethiopian to Gregorian if needed
        if (CalendarHelper::isEthiopian()) {
            $ethiopianDate = "$year-$month-01";
            $gregorianDate = DateConverter::toGregorian($ethiopianDate);
            list($year, $month) = explode('-', $gregorianDate);
        }

        $pdo = $this->db->getConnection();

        try {
            $pdo->beginTransaction();

            // Check if this is a future timesheet
            if ($this->isFutureTimesheet($month, $year) && !(new User())->isAdmin($user_id)) {
                throw new Exception("Cannot create/edit future timesheets");
            }

                

            // Check if already submitted
            $stmt = $pdo->prepare("
            SELECT timesheet_id, status FROM timesheets 
            WHERE user_id = ? AND month = ? AND year = ?
        ");
            $stmt->execute([$user_id, $month, $year]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            // If already submitted and trying to submit again, reject
            if ($existing && $existing['status'] === 'submitted' && isset($data['action']) && $data['action'] === 'submit') {
                throw new Exception("You have already submitted this timesheet");
            }
            
            // Get or create timesheet
            $stmt = $pdo->prepare("
                SELECT timesheet_id FROM timesheets 
                WHERE user_id = ? AND month = ? AND year = ?
            ");
            $stmt->execute([$user_id, $month, $year]);
            $timesheet_id = $stmt->fetchColumn();
            
            if (!$timesheet_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO timesheets (user_id, month, year, status)
                    VALUES (?, ?, ?, 'draft')
                ");
                $stmt->execute([$user_id, $month, $year]);
                $timesheet_id = $pdo->lastInsertId();
            }
            
            // Save entries
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
                    SET status = 'submitted', submitted_at = NOW()
                    WHERE timesheet_id = ?
                ");
                $stmt->execute([$timesheet_id]);
            }
            
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Timesheet save error: " . $e->getMessage());
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

    public function getSubmittedTimesheets($user_id) {
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
// Approval functions
    public function approveTimesheet($timesheet_id, $approver_id)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
        UPDATE timesheets 
        SET status = 'approved', 
            approved_at = NOW(), 
            approved_by = ?,
            rejection_reason = NULL
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
            rejection_reason = ?
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

    // user timesheet method for new func

    public function getAllUserTimesheets($user_id, $month = null, $year = null)
    {
        $pdo = $this->db->getConnection();

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
            $sql .= " AND t.month = ? AND t.year = ?";
            $params[] = $month;
            $params[] = $year;
        }

        $sql .= " GROUP BY t.timesheet_id ORDER BY t.year DESC, t.month DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function canEditTimesheet($timesheet_id, $user_id, $is_admin = false)
    {
        if ($is_admin) {
            return true; // Admins can always edit
        }

        $pdo = $this->db->getConnection();

        // Get timesheet details
        $stmt = $pdo->prepare("
        SELECT t.month, t.year, t.status, t.user_id 
        FROM timesheets t
        WHERE t.timesheet_id = ?
    ");
        $stmt->execute([$timesheet_id]);
        $timesheet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$timesheet || $timesheet['user_id'] != $user_id) {
            return false; // Not the owner
        }

        $currentMonth = date('n');
        $currentYear = date('Y');

        // For current month, always allow editing if status is draft
        if ($timesheet['month'] == $currentMonth && $timesheet['year'] == $currentYear) {
            return true;
        }

        // For previous months, allow editing within 5 days after month ends
        if (
            $timesheet['year'] < $currentYear ||
            ($timesheet['year'] == $currentYear && $timesheet['month'] < $currentMonth)
        ) {

            $lastDayOfMonth = date('t', strtotime("{$timesheet['year']}-{$timesheet['month']}-01"));
            $deadline = date('Y-m-d', strtotime("{$timesheet['year']}-{$timesheet['month']}-{$lastDayOfMonth} +5 days"));

            return date('Y-m-d') <= $deadline;
        }

        // Future months - no editing allowed
        return false;
    }

    public function isFutureTimesheet($month, $year)
    {
        $currentMonth = date('n');
        $currentYear = date('Y');

        return ($year > $currentYear) || ($year == $currentYear && $month > $currentMonth);
    }

}
