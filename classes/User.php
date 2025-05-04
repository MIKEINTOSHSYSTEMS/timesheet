<?php
class User {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function getUserById($user_id) {
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("
            SELECT u.*, up.phone_number, up.address, up.preferred_language, up.ethiopian_calendar_preference 
            FROM users u
            LEFT JOIN user_profiles up ON u.user_id = up.user_id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }

    public function updateProfile($user_id, $data)
    {
        $pdo = $this->db->getConnection();

        try {
            $pdo->beginTransaction();

            // Update users table
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $user_id
            ]);

            // Update or insert user profile
            $stmt = $pdo->prepare("
                INSERT INTO user_profiles (user_id, phone_number, address, preferred_language, ethiopian_calendar_preference)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    phone_number = VALUES(phone_number),
                    address = VALUES(address),
                    preferred_language = VALUES(preferred_language),
                    ethiopian_calendar_preference = VALUES(ethiopian_calendar_preference)
            ");
            $stmt->execute([
                $user_id,
                $data['phone_number'] ?? null,
                $data['address'] ?? null,
                $data['preferred_language'] ?? 'en',
                $data['ethiopian_calendar_preference'] ?? 0
            ]);

            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Profile update failed: " . $e->getMessage());
            return false;
        }
    }

    public function getAllUsers($role = null)
    {
        $pdo = $this->db->getConnection();

        $sql = "SELECT u.*, up.phone_number FROM users u LEFT JOIN user_profiles up ON u.user_id = up.user_id";
        $params = [];

        if ($role) {
            $sql .= " WHERE u.role = ?";
            $params[] = $role;
        }

        $sql .= " ORDER BY u.last_name, u.first_name";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createUser($data)
    {
        $pdo = $this->db->getConnection();

        try {
            $pdo->beginTransaction();

            // Insert into users table
            $stmt = $pdo->prepare("
                INSERT INTO users (email, password_hash, first_name, last_name, role, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

            $stmt->execute([
                $data['email'],
                $password_hash,
                $data['first_name'],
                $data['last_name'],
                $data['role'],
                $data['is_active'] ?? 1
            ]);

            $user_id = $pdo->lastInsertId();

            // Insert into user_profiles table
            $stmt = $pdo->prepare("
                INSERT INTO user_profiles (user_id, phone_number, preferred_language, ethiopian_calendar_preference)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $data['phone_number'] ?? null,
                $data['preferred_language'] ?? 'en',
                $data['ethiopian_calendar_preference'] ?? 0
            ]);

            $pdo->commit();
            return $user_id;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("User creation failed: " . $e->getMessage());
            return false;
        }
    }

    // admin check to User class
    public function isAdmin($user_id)
    {
        $pdo = (new Database())->getConnection();
        $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user && $user['role'] === 'admin';
    }

    public function updateUser($user_id, $data)
    {
        $pdo = $this->db->getConnection();

        try {
            $pdo->beginTransaction();

            // Update users table
            $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, is_active = ?";
            $params = [
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['role'],
                $data['is_active'] ?? 1
            ];

            // Update password if provided
            if (!empty($data['password'])) {
                $sql .= ", password_hash = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $sql .= " WHERE user_id = ?";
            $params[] = $user_id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Update user_profiles table
            $stmt = $pdo->prepare("
                UPDATE user_profiles 
                SET phone_number = ?, preferred_language = ?, ethiopian_calendar_preference = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $data['phone_number'] ?? null,
                $data['preferred_language'] ?? 'en',
                $data['ethiopian_calendar_preference'] ?? 0,
                $user_id
            ]);

            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("User update failed: " . $e->getMessage());
            return false;
        }
        
    }
}
