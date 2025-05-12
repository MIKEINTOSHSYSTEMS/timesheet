<?php
class Role
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function getAllRoles()
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->query("SELECT * FROM roles ORDER BY role_name");
        return $stmt->fetchAll();
    }

    public function getRole($role_id)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE role_id = ?");
        $stmt->execute([$role_id]);
        return $stmt->fetch();
    }

    public function createRole($data)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO roles (role_name, role_description, is_active)
            VALUES (?, ?, ?)
        ");
        return $stmt->execute([
            $data['role_name'],
            $data['role_description'] ?? null,
            $data['is_active'] ?? 1
        ]);
    }

    public function updateRole($role_id, $data)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            UPDATE roles 
            SET role_name = ?, role_description = ?, is_active = ?
            WHERE role_id = ?
        ");
        return $stmt->execute([
            $data['role_name'],
            $data['role_description'] ?? null,
            $data['is_active'] ?? 1,
            $role_id
        ]);
    }

    public function deleteRole($role_id)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("DELETE FROM roles WHERE role_id = ?");
        return $stmt->execute([$role_id]);
    }

    public function getRolePermissions($role_id)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT p.*, rp.role_permission_id
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.permission_id = rp.permission_id AND rp.role_id = ?
            ORDER BY p.permission_name
        ");
        $stmt->execute([$role_id]);
        return $stmt->fetchAll();
    }

    public function updateRolePermissions($role_id, $permissions)
    {
        $pdo = $this->db->getConnection();

        try {
            $pdo->beginTransaction();

            // Remove all existing permissions
            $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$role_id]);

            // Add new permissions
            if (!empty($permissions)) {
                $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($permissions as $permission_id) {
                    $stmt->execute([$role_id, $permission_id]);
                }
            }

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function getMenuPermissions($role_id)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM menu_permissions WHERE role_id = ?");
        $stmt->execute([$role_id]);
        return $stmt->fetchAll();
    }

    public function updateMenuPermissions($role_id, $menu_permissions)
    {
        $pdo = $this->db->getConnection();

        try {
            $pdo->beginTransaction();

            // Remove all existing menu permissions
            $stmt = $pdo->prepare("DELETE FROM menu_permissions WHERE role_id = ?");
            $stmt->execute([$role_id]);

            // Add new menu permissions
            if (!empty($menu_permissions)) {
                $stmt = $pdo->prepare("
                    INSERT INTO menu_permissions (role_id, menu_item, can_access)
                    VALUES (?, ?, ?)
                ");
                foreach ($menu_permissions as $menu_item => $can_access) {
                    if ($can_access) {
                        $stmt->execute([$role_id, $menu_item, 1]);
                    }
                }
            }

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function getUserRoles($user_id)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT r.* FROM roles r
            JOIN users u ON r.role_id = u.role_id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }

    public function hasPermission($user_id, $permission_key)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.permission_id
            JOIN users u ON rp.role_id = u.role_id
            WHERE u.user_id = ? AND p.permission_key = ?
        ");
        $stmt->execute([$user_id, $permission_key]);
        return $stmt->fetchColumn() > 0;
    }

    public function canAccessMenu($user_id, $menu_item)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM menu_permissions mp
            JOIN users u ON mp.role_id = u.role_id
            WHERE u.user_id = ? AND mp.menu_item = ? AND mp.can_access = 1
        ");
        $stmt->execute([$user_id, $menu_item]);
        return $stmt->fetchColumn() > 0;
    }
}
