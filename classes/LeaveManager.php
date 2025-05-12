<?php
class LeaveManager
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function getLeaveTypes()
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->query("SELECT * FROM leave_types WHERE is_active = 1");
        return $stmt->fetchAll();
    }

    public function getLeaveBalance($user_id)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT leave_balance FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }

    public function requestLeave($user_id, $data)
    {
        $pdo = $this->db->getConnection();

        $start_date = new DateTime($data['start_date']);
        $end_date = new DateTime($data['end_date']);
        $days_requested = $this->calculateWorkingDays($start_date, $end_date);

        $stmt = $pdo->prepare("
            INSERT INTO leave_requests 
            (user_id, leave_type_id, start_date, end_date, days_requested, reason)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $user_id,
            $data['leave_type_id'],
            $data['start_date'],
            $data['end_date'],
            $days_requested,
            $data['reason'] ?? null
        ]);
    }

    public function approveLeave($leave_request_id, $approver_id)
    {
        $pdo = $this->db->getConnection();

        try {
            $pdo->beginTransaction();

            // Get leave request details
            $stmt = $pdo->prepare("
                SELECT lr.*, u.leave_balance 
                FROM leave_requests lr
                JOIN users u ON lr.user_id = u.user_id
                WHERE lr.leave_request_id = ?
            ");
            $stmt->execute([$leave_request_id]);
            $leave = $stmt->fetch();

            if (!$leave) {
                throw new Exception("Leave request not found");
            }

            if ($leave['leave_balance'] < $leave['days_requested']) {
                throw new Exception("Insufficient leave balance");
            }


            // Update leave request status
            $stmt = $pdo->prepare("
                UPDATE leave_requests 
                SET status = 'approved', approved_by = ?, approved_at = NOW()
                WHERE leave_request_id = ?
            ");
            $stmt->execute([$approver_id, $leave_request_id]);

            // Deduct from user's leave balance
            $stmt = $pdo->prepare("
                UPDATE users 
                SET leave_balance = leave_balance - ?
                WHERE user_id = ?
            ");
            $stmt->execute([$leave['days_requested'], $leave['user_id']]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function rejectLeave($leave_request_id, $approver_id, $reason)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            UPDATE leave_requests 
            SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ?
            WHERE leave_request_id = ?
        ");
        return $stmt->execute([$approver_id, $reason, $leave_request_id]);
    }

    public function getUserLeaveRequests($user_id)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT lr.*, lt.type_name 
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
            WHERE lr.user_id = ?
            ORDER BY lr.start_date DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public function getPendingLeaveRequests()
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->query("
            SELECT lr.*, u.first_name, u.last_name, lt.type_name 
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.user_id
            JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
            WHERE lr.status = 'pending'
            ORDER BY lr.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    private function calculateWorkingDays($start_date, $end_date)
    {
        $working_days = 0;
        $current = clone $start_date;

        while ($current <= $end_date) {
            $day_of_week = $current->format('N'); // 1 (Monday) to 7 (Sunday)
            if ($day_of_week <= 5) { // Monday to Friday
                $working_days++;
            }
            $current->modify('+1 day');
        }

        return $working_days;
    }

    public function updateLeaveBalance($user_id, $balance)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            UPDATE users 
            SET leave_balance = ?
            WHERE user_id = ?
        ");
        return $stmt->execute([$balance, $user_id]);
    }

    public function getLeaveHistory($user_id)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT lr.*, lt.type_name 
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
            WHERE lr.user_id = ? AND lr.status != 'pending'
            ORDER BY lr.start_date DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    public function getLeaveRequestDetails($leave_request_id)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT lr.*, u.first_name, u.last_name, lt.type_name 
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.user_id
            JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
            WHERE lr.leave_request_id = ?
        ");
        $stmt->execute([$leave_request_id]);
        return $stmt->fetch();
    }

    public function calculateAnnualEntitlement($join_date)
    {
        // Handle invalid dates gracefully
        if (empty($join_date) || !strtotime($join_date)) {
            error_log("Invalid join date encountered: " . ($join_date ?? 'NULL'));
            return 0;
        }

        $joinDate = new DateTime($join_date);
        $currentDate = new DateTime();

        // Ensure join date is in the past
        if ($joinDate > $currentDate) {
            error_log("Future join date encountered: $join_date");
            return 0;
        }

        $yearsOfService = $currentDate->diff($joinDate)->y;
        $baseDays = 20;
        $additionalDays = min($yearsOfService * 2, 10);

        return $baseDays + $additionalDays;
    }

    public function calculateAnnualLeave($user_id)
    {
        $pdo = $this->db->getConnection();

        try {
            $pdo->beginTransaction();

            // Get user details
            $stmt = $pdo->prepare("SELECT join_date, leave_balance FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            // Calculate new entitlement
            $newBalance = $this->calculateAnnualEntitlement($user['join_date']);

            // Apply carryover rules
            $carryover = min(($user['leave_balance'] - $newBalance), 5);
            $newBalance += max($carryover, 0);

            // Update balance
            $stmt = $pdo->prepare("UPDATE users SET leave_balance = ? WHERE user_id = ?");
            $stmt->execute([$newBalance, $user_id]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
