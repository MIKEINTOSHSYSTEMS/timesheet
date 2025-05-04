<?php
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function login($email, $password) {
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_role'] = $user['role'];
            
            // Update last login
            $this->updateLastLogin($user['user_id']);
            
            return true;
        }
        
        return false;
    }
    
    private function updateLastLogin($user_id) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        return $stmt->execute([$user_id]);
    }
    
    public function register($data) {
        $pdo = $this->db->getConnection();
        
        try {
            $pdo->beginTransaction();
            
            // Check if email exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                throw new Exception("Email already exists");
            }
            
            // Insert user
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
                $data['role'] ?? 'employee',
                $data['is_active'] ?? 0 // Default to inactive until admin approves
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Insert user profile
            $stmt = $pdo->prepare("
                INSERT INTO user_profiles (user_id, preferred_language, ethiopian_calendar_preference)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $data['preferred_language'] ?? 'en',
                $data['ethiopian_calendar_preference'] ?? 0
            ]);
            
            $pdo->commit();
            return $user_id;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function logout() {
        session_destroy();
        session_regenerate_id(true);
    }
    
    public function getUser($user_id) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }

public function updateUser($user_id, $data) {
    $pdo = $this->db->getConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Update users table
        $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?";
        $params = [
            $data['first_name'],
            $data['last_name'],
            $data['email']
        ];
        
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
            SET phone_number = ?, address = ?, 
                preferred_language = ?, ethiopian_calendar_preference = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $data['phone_number'] ?? null,
            $data['address'] ?? null,
            $data['preferred_language'] ?? 'en',
            $data['ethiopian_calendar_preference'] ?? 0,
            $user_id
        ]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw new Exception("Failed to update user: " . $e->getMessage());
    }
}

public function changePassword($user_id, $current_password, $new_password) {
    $pdo = $this->db->getConnection();
    
    // Verify current password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($current_password, $user['password_hash'])) {
        return false;
    }
    
    // Update password
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    return $stmt->execute([$new_hash, $user_id]);
}


}