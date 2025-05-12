<?php
class Permission
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function getAllPermissions()
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->query("SELECT * FROM permissions ORDER BY permission_name");
        return $stmt->fetchAll();
    }

    public function createPermission($data)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO permissions (permission_name, permission_key, description)
            VALUES (?, ?, ?)
        ");
        return $stmt->execute([
            $data['permission_name'],
            $data['permission_key'],
            $data['description'] ?? null
        ]);
    }

    public function updatePermission($permission_id, $data)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            UPDATE permissions 
            SET permission_name = ?, permission_key = ?, description = ?
            WHERE permission_id = ?
        ");
        return $stmt->execute([
            $data['permission_name'],
            $data['permission_key'],
            $data['description'] ?? null,
            $permission_id
        ]);
    }

    public function deletePermission($permission_id)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("DELETE FROM permissions WHERE permission_id = ?");
        return $stmt->execute([$permission_id]);
    }

    public function getDefaultMenuItems()
    {
        return [
            'dashboard' => 'Dashboard',
            'timesheet' => 'Timesheet',
            'leave' => 'Leave Management',
            'reports' => 'Reports',
            'user_management' => 'User Management',
            'project_management' => 'Project Management',
            'system_settings' => 'System Settings',
            'admin_dashboard' => 'Admin Dashboard'
        ];
    }
}
