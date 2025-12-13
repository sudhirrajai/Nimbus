<?php
/**
 * Roundcube SSO Login Script
 * This script is placed in /var/lib/roundcube/public_html/sso.php
 * It reads a token from Nimbus and auto-logs the user into Roundcube
 */

// Token directory (Nimbus storage path)
$tokenDir = '/usr/local/nimbus/storage/app/roundcube_tokens';

// Get token from query string
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token) || !preg_match('/^[a-zA-Z0-9]+$/', $token)) {
    die('Invalid token');
}

// Read token file
$tokenFile = $tokenDir . '/' . $token . '.json';

if (!file_exists($tokenFile)) {
    die('Token expired or invalid');
}

$tokenData = json_decode(file_get_contents($tokenFile), true);

// Delete token immediately (one-time use)
unlink($tokenFile);

// Check expiration
if (time() > $tokenData['expires_at']) {
    die('Token expired');
}

$email = $tokenData['email'];

// Initialize Roundcube
define('INSTALL_PATH', realpath(__DIR__) . '/');
require_once INSTALL_PATH . 'program/include/iniset.php';

$rcmail = rcmail::get_instance();

// Set session variables for auto-login
$_SESSION['username'] = $email;
$_SESSION['password'] = ''; // We use Dovecot's master password feature or let user enter password

// Redirect to Roundcube
// Note: Full auto-login requires Roundcube plugin or master password setup
// For now, pre-fill the username
header('Location: /roundcube/?_user=' . urlencode($email));
exit;
