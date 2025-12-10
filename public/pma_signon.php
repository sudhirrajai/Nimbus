<?php
/**
 * phpMyAdmin Single Sign-On for Nimbus Panel
 * This script validates a token from the panel and auto-logs into phpMyAdmin
 */

// Start session that phpMyAdmin will use
session_name('SignonSession');
@session_start();

// Get token from URL
$token = $_GET['token'] ?? '';
$db = $_GET['db'] ?? '';

if (empty($token)) {
    header('Location: /database');
    exit;
}

// Validate token from file
$tokenFile = '/usr/local/nimbus/storage/app/pma_tokens/' . $token . '.json';

if (!file_exists($tokenFile)) {
    // Token not found or expired
    header('HTTP/1.1 403 Forbidden');
    echo '<html><body style="font-family: Arial; text-align: center; padding: 50px;">';
    echo '<h1>Access Denied</h1>';
    echo '<p>Invalid or expired session. Please access phpMyAdmin from the Nimbus panel.</p>';
    echo '<a href="/database">Go to Database Manager</a>';
    echo '</body></html>';
    exit;
}

// Read and validate token
$tokenData = json_decode(file_get_contents($tokenFile), true);

// Check expiry (5 minutes)
if (time() - $tokenData['created'] > 300) {
    unlink($tokenFile);
    header('HTTP/1.1 403 Forbidden');
    echo '<html><body style="font-family: Arial; text-align: center; padding: 50px;">';
    echo '<h1>Session Expired</h1>';
    echo '<p>Your session has expired. Please try again from the Nimbus panel.</p>';
    echo '<a href="/database">Go to Database Manager</a>';
    echo '</body></html>';
    exit;
}

// Delete token (one-time use)
unlink($tokenFile);

// Set phpMyAdmin session credentials
$_SESSION['PMA_single_signon_user'] = $tokenData['username'];
$_SESSION['PMA_single_signon_password'] = $tokenData['password'];
$_SESSION['PMA_single_signon_host'] = $tokenData['host'] ?? 'localhost';

// Redirect to phpMyAdmin
$redirectUrl = '/phpmyadmin/index.php';
if (!empty($db)) {
    $redirectUrl .= '?db=' . urlencode($db);
}

header('Location: ' . $redirectUrl);
exit;
