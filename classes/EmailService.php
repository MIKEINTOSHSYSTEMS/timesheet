<?php
class EmailService {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function sendEmail($to, $subject, $body, $headers = []) {
        $smtp_settings = $this->getSmtpSettings();
        
        if ($smtp_settings['SMTP_HOST']) {
            return $this->sendViaSmtp($to, $subject, $body, $smtp_settings);
        } else {
            return $this->sendViaMailFunction($to, $subject, $body, $headers);
        }
    }
    
    private function sendViaSmtp($to, $subject, $body, $smtp_settings) {
        require_once __DIR__ . '/../vendor/autoload.php'; // Assuming PHPMailer is installed via Composer
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $smtp_settings['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_settings['SMTP_USERNAME'];
            $mail->Password   = $smtp_settings['SMTP_PASSWORD'];
            $mail->SMTPSecure = $smtp_settings['SMTP_SECURITY'];
            $mail->Port       = $smtp_settings['SMTP_PORT'];
            
            // Recipients
            $mail->setFrom($smtp_settings['SMTP_FROM_EMAIL'], $smtp_settings['SMTP_FROM_NAME']);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendViaMailFunction($to, $subject, $body, $headers) {
        $default_headers = [
            'MIME-Version' => '1.0',
            'Content-type' => 'text/html; charset=utf-8',
            'From' => 'noreply@merqconsultancy.org'
        ];
        
        $merged_headers = array_merge($default_headers, $headers);
        $headers_string = '';
        
        foreach ($merged_headers as $key => $value) {
            $headers_string .= "$key: $value\r\n";
        }
        
        return mail($to, $subject, $body, $headers_string);
    }
    
    public function getSmtpSettings() {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->query("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key LIKE 'SMTP_%' OR setting_key = 'SMTP_FROM_NAME'
        ");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return $settings;
    }
    
    public function getEmailTemplate($template_name) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("
            SELECT template_subject, template_body 
            FROM email_templates 
            WHERE template_name = ? AND is_active = 1
        ");
        $stmt->execute([$template_name]);
        return $stmt->fetch();
    }
    
    public function sendPasswordResetEmail($email, $reset_token) {
        $template = $this->getEmailTemplate('password_reset');
        if (!$template) {
            return false;
        }
        
        $reset_link = BASE_URL . "/pages/reset-password.php?token=" . urlencode($reset_token);
        
        $subject = $template['template_subject'];
        $body = str_replace('{reset_link}', $reset_link, $template['template_body']);
        
        return $this->sendEmail($email, $subject, $body);
    }
    
    public function sendTimesheetReminder($user_id) {
        $pdo = $this->db->getConnection();
        
        // Get user email
        $stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $email = $stmt->fetchColumn();
        
        if (!$email) {
            return false;
        }
        
        $template = $this->getEmailTemplate('timesheet_reminder');
        if (!$template) {
            return false;
        }
        
        return $this->sendEmail($email, $template['template_subject'], $template['template_body']);
    }
    
    public function sendLeaveStatusNotification($leave_request_id) {
        $pdo = $this->db->getConnection();
        
        // Get leave request details
        $stmt = $pdo->prepare("
            SELECT lr.status, lr.rejection_reason, u.email, u.first_name, lt.type_name
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.user_id
            JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
            WHERE lr.leave_request_id = ?
        ");
        $stmt->execute([$leave_request_id]);
        $leave = $stmt->fetch();
        
        if (!$leave) {
            return false;
        }
        
        // Determine which template to use
        $template_name = ($leave['status'] == 'approved') ? 'leave_approval' : 'leave_rejection';
        $template = $this->getEmailTemplate($template_name);
        
        if (!$template) {
            return false;
        }
        
        // Replace placeholders in template
        $subject = str_replace('{leave_type}', $leave['type_name'], $template['template_subject']);
        $body = str_replace(
            ['{first_name}', '{leave_type}', '{reason}'],
            [$leave['first_name'], $leave['type_name'], $leave['rejection_reason'] ?? ''],
            $template['template_body']
        );
        
        return $this->sendEmail($leave['email'], $subject, $body);
    }

    public function getAllEmailTemplates()
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->query("SELECT * FROM email_templates");
        return $stmt->fetchAll();
    }

    public function getEmailTemplateById($template_id)
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE template_id = ?");
        $stmt->execute([$template_id]);
        return $stmt->fetch();
    }
}