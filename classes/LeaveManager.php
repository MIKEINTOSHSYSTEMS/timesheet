<?php
class LeaveManager
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getLeaveTypes()
    {
        $stmt = $this->pdo->query("SELECT * FROM leave_types WHERE is_active = 1");
        return $stmt->fetchAll();
    }

    public function getLeaveBalance($user_id)
    {
        $stmt = $this->pdo->prepare("SELECT leave_balance FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }

    public function requestLeave($user_id, $data)
    {
        try {
            $start_date = new DateTime($data['start_date']);
            $end_date = new DateTime($data['end_date']);
            $days_requested = $this->calculateWorkingDays($start_date, $end_date);

            $stmt = $this->pdo->prepare("
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
        } catch (Exception $e) {
            error_log("Leave request failed: " . $e->getMessage());
            throw new Exception("Failed to create leave request");
        }
    }

    public function approveLeave($leave_request_id, $approver_id)
    {
        $this->pdo->beginTransaction();

        try {
            // Get leave request details
            $stmt = $this->pdo->prepare("
                SELECT lr.*, u.leave_balance 
                FROM leave_requests lr
                JOIN users u ON lr.user_id = u.user_id
                WHERE lr.leave_request_id = ?
                FOR UPDATE
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
            $stmt = $this->pdo->prepare("
                UPDATE leave_requests 
                SET status = 'approved', approved_by = ?, approved_at = NOW()
                WHERE leave_request_id = ?
            ");
            $stmt->execute([$approver_id, $leave_request_id]);

            // Deduct from user's leave balance
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET leave_balance = leave_balance - ?
                WHERE user_id = ?
            ");
            $stmt->execute([$leave['days_requested'], $leave['user_id']]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Leave approval failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function rejectLeave($leave_request_id, $approver_id, $reason)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE leave_requests 
                SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ?
                WHERE leave_request_id = ?
            ");
            return $stmt->execute([$approver_id, $reason, $leave_request_id]);
        } catch (Exception $e) {
            error_log("Leave rejection failed: " . $e->getMessage());
            throw new Exception("Failed to reject leave request");
        }
    }

    public function getUserLeaveRequests($user_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT lr.*, lt.type_name 
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                WHERE lr.user_id = ?
                ORDER BY lr.start_date DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get user leave requests: " . $e->getMessage());
            return [];
        }
    }

    public function getPendingLeaveRequests()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT lr.*, u.first_name, u.last_name, lt.type_name 
                FROM leave_requests lr
                JOIN users u ON lr.user_id = u.user_id
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                WHERE lr.status IN ('pending', 'approved')  /* Show both pending and approved */
                ORDER BY lr.created_at DESC
            ");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get leave requests: " . $e->getMessage());
            return [];
        }
    }

    private function calculateWorkingDays($start_date, $end_date)
    {
        $working_days = 0;
        $current = clone $start_date;

        while ($current <= $end_date) {
            $day_of_week = $current->format('N');
            if ($day_of_week <= 5) {
                $working_days++;
            }
            $current->modify('+1 day');
        }

        return $working_days;
    }

    public function updateLeaveBalance($user_id, $balance)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET leave_balance = ?
                WHERE user_id = ?
            ");
            return $stmt->execute([$balance, $user_id]);
        } catch (Exception $e) {
            error_log("Failed to update leave balance: " . $e->getMessage());
            throw new Exception("Failed to update leave balance");
        }
    }

    public function getLeaveHistory($user_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT lr.*, lt.type_name 
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                WHERE lr.user_id = ? AND lr.status != 'pending'
                ORDER BY lr.start_date DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get leave history: " . $e->getMessage());
            return [];
        }
    }

    public function getLeaveRequestDetails($leave_request_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT lr.*, u.first_name, u.last_name, lt.type_name 
                FROM leave_requests lr
                JOIN users u ON lr.user_id = u.user_id
                JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
                WHERE lr.leave_request_id = ?
            ");
            $stmt->execute([$leave_request_id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Failed to get leave request details: " . $e->getMessage());
            return null;
        }
    }

    public function calculateAnnualEntitlement($join_date)
    {
        // Handle empty or invalid dates
        if (empty($join_date)) {
            return 0;
        }

        try {
            $joinDate = new DateTime($join_date);
            $currentDate = new DateTime();

            // If join date is in future, return 0
            if ($joinDate > $currentDate) {
                return 0;
            }

            // Calculate years of service
            $interval = $currentDate->diff($joinDate);
            $yearsOfService = $interval->y;

            // Calculate entitlement
            $baseDays = 20;
            $additionalDays = min($yearsOfService * 2, 10); // Max 10 additional days

            return $baseDays + $additionalDays;
        } catch (Exception $e) {
            error_log("Date calculation error: " . $e->getMessage());
            return 0;
        }
    }

    public function calculateAnnualLeave($user_id)
    {
        try {
            // Get user details without FOR UPDATE lock
            $stmt = $this->pdo->prepare("
                SELECT join_date, leave_balance 
                FROM users 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new Exception("User not found");
            }

            // Calculate new entitlement
            $newBalance = $this->calculateAnnualEntitlement($user['join_date']);

            // Apply carryover (max 5 days)
            $carryover = min($user['leave_balance'], 5);
            $finalBalance = $newBalance + $carryover;

            // Update balance
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET leave_balance = ?, 
                    last_leave_increment = CURDATE() 
                WHERE user_id = ?
            ");
            $stmt->execute([$finalBalance, $user_id]);

            return true;
        } catch (Exception $e) {
            error_log("Leave calculation failed for user $user_id: " . $e->getMessage());
            throw $e;
        }
    }

    public function bulkUpdateLeaveBalances($users)
    {
        $this->pdo->beginTransaction();

        try {
            foreach ($users as $user_id => $balance) {
                $stmt = $this->pdo->prepare("UPDATE users SET leave_balance = ? WHERE user_id = ?");
                $stmt->execute([$balance, $user_id]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Bulk update failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function getLeaveSummary($user_id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    SUM(CASE WHEN status = 'approved' THEN days_requested ELSE 0 END) as used_leave,
                    leave_balance as remaining_balance
                FROM leave_requests 
                JOIN users USING (user_id)
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Failed to get leave summary: " . $e->getMessage());
            return null;
        }
    }



    public function updateLeaveBalanceWithoutTransaction($user_id)
    {
        try {
            // Get user details without FOR UPDATE lock
            $stmt = $this->pdo->prepare("
                SELECT join_date, leave_balance 
                FROM users 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new Exception("User not found");
            }

            // Calculate new entitlement
            $newBalance = $this->calculateAnnualEntitlement($user['join_date']);

            // Apply carryover (max 5 days)
            $carryover = min($user['leave_balance'], 5);
            $finalBalance = $newBalance + $carryover;

            // Update balance
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET leave_balance = ?, 
                    last_leave_increment = CURDATE() 
                WHERE user_id = ?
            ");
            $stmt->execute([$finalBalance, $user_id]);

            return true;
        } catch (Exception $e) {
            error_log("Leave calculation failed for user $user_id: " . $e->getMessage());
            throw $e;
        }
    }

    public function calculateAnnualLeaveWithoutTransaction($user_id)
    {
        try {
            // Get user details without FOR UPDATE lock
            $stmt = $this->pdo->prepare("
                SELECT join_date, leave_balance 
                FROM users 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new Exception("User not found");
            }

            // Calculate new entitlement
            $newBalance = $this->calculateAnnualEntitlement($user['join_date']);

            // Apply carryover (max 5 days)
            $carryover = min($user['leave_balance'], 5);
            $finalBalance = $newBalance + $carryover;

            // Update balance
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET leave_balance = ?, 
                    last_leave_increment = CURDATE() 
                WHERE user_id = ?
            ");
            $stmt->execute([$finalBalance, $user_id]);

            return true;
        } catch (Exception $e) {
            error_log("Leave calculation failed for user $user_id: " . $e->getMessage());
            throw $e;
        }
    }
}