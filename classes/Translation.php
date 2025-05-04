<?php
class Translation {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function getMonthName($monthNumber) {
        $language = $_SESSION['language'] ?? 'en';
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("
            SELECT mt.month_name 
            FROM month_translations mt
            JOIN languages l ON mt.language_id = l.language_id
            WHERE l.language_code = ? AND mt.month_number = ?
        ");
        $stmt->execute([$language, $monthNumber]);
        $month = $stmt->fetch();
        
        return $month ? $month['month_name'] : date('F', mktime(0, 0, 0, $monthNumber, 1));
    }
    
    public function setLanguage($language) {
        $_SESSION['language'] = $language;
    }
    
    public function setCalendarPreference($ethiopian) {
        $_SESSION['ethiopian_calendar'] = $ethiopian;
    }
    
    // Add other translation methods as needed
}

/*
{
    private $db;
    private $language = 'en';
    private $ethiopianCalendar = false;

    public function __construct()
    {
        $this->db = new Database();

        // Get user preferences if logged in
        if (isset($_SESSION['user_id'])) {
            $this->loadUserPreferences($_SESSION['user_id']);
        }
    }

    private function loadUserPreferences($user_id)
    {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            SELECT up.preferred_language, up.ethiopian_calendar_preference 
            FROM user_profiles up 
            WHERE up.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $prefs = $stmt->fetch();

        if ($prefs) {
            $this->language = $prefs['preferred_language'];
            $this->ethiopianCalendar = (bool)$prefs['ethiopian_calendar_preference'];
        }
    }

    public function getMonthName($monthNumber)
    {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            SELECT month_name 
            FROM month_translations mt
            JOIN languages l ON mt.language_id = l.language_id
            WHERE l.language_code = ? AND mt.month_number = ?
        ");
        $stmt->execute([$this->language, $monthNumber]);
        $month = $stmt->fetch();

        return $month ? $month['month_name'] : '';
    }

    public function setLanguage($language)
    {
        $this->language = $language;
    }

    public function setCalendarPreference($ethiopian)
    {
        $this->ethiopianCalendar = $ethiopian;
    }

    // Other translation methods...
}
*/