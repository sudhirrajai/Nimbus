<?php
/**
 * Roundcube SSO Login Script
 * This script reads a token from Nimbus and redirects to Roundcube with username pre-filled
 * 
 * Place this file at: /var/lib/roundcube/public_html/sso.php
 */

// Token directory (Nimbus storage path)
$tokenDir = '/usr/local/nimbus/storage/app/roundcube_tokens';

// Get token from query string
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Validate token format
if (empty($token) || !preg_match('/^[a-zA-Z0-9]+$/', $token)) {
    header('Location: /roundcube/');
    exit;
}

// Read token file
$tokenFile = $tokenDir . '/' . $token . '.json';

if (!file_exists($tokenFile)) {
    // Token doesn't exist or already used
    header('Location: /roundcube/');
    exit;
}

$tokenData = json_decode(file_get_contents($tokenFile), true);

// Delete token immediately (one-time use)
@unlink($tokenFile);

// Check expiration
if (!$tokenData || time() > $tokenData['expires_at']) {
    header('Location: /roundcube/');
    exit;
}

$email = $tokenData['email'];

// Redirect to Roundcube with username pre-filled via cookie
// Roundcube remembers last username in a cookie
setcookie('roundcube_username', $email, time() + 300, '/roundcube');

// Redirect to Roundcube
header('Location: /roundcube/?_user=' . urlencode($email));
exit;
