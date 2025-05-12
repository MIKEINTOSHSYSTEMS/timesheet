<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

$client = new Google\Client();
$client->setClientId(getSetting('GOOGLE_OAUTH_CLIENT_ID'));
$client->setClientSecret(getSetting('GOOGLE_OAUTH_CLIENT_SECRET'));
$client->setRedirectUri(BASE_URL . '/auth/google');
$client->addScope('email');
$client->addScope('profile');

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);

        $oauth = new Google\Service\Oauth2($client);
        $user_info = $oauth->userinfo->get();

        // Check if email domain is allowed
        if (strpos($user_info->email, '@merqconsultancy.org') === false) {
            throw new Exception("Only @merqconsultancy.org emails are allowed");
        }

        // Find or create user
        $user = new User();
        $existing_user = $user->getUserByEmail($user_info->email);

        if ($existing_user) {
            // Update user info
            $user->updateUser($existing_user['user_id'], [
                'first_name' => $user_info->givenName,
                'last_name' => $user_info->familyName,
                'email' => $user_info->email
            ]);
            $user_id = $existing_user['user_id'];
        } else {
            // Create new user
            $user_id = $user->createUser([
                'first_name' => $user_info->givenName,
                'last_name' => $user_info->familyName,
                'email' => $user_info->email,
                'password' => bin2hex(random_bytes(16)), // Random password
                'role' => 'employee'
            ]);
        }

        // Log user in
        $_SESSION['user_id'] = $user_id;
        header('Location: ' . BASE_URL . '/pages/dashboard.php');
        exit;
    } catch (Exception $e) {
        error_log("Google OAuth error: " . $e->getMessage());
        header('Location: ' . BASE_URL . '/pages/login.php?error=oauth_failed');
        exit;
    }
} else {
    // Redirect to Google auth URL
    header('Location: ' . $client->createAuthUrl());
    exit;
}
