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

// Redirect to Roundcube with the SSO token parameter
header('Location: /roundcube/?_sso_token=' . urlencode($token));
exit;
