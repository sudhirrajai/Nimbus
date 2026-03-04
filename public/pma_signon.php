<?php
/**
 * phpMyAdmin Single Sign-On for Nimbus Panel
 * 
 * This script validates a one-time token issued by the Nimbus panel
 * and auto-logs the user into phpMyAdmin.
 * 
 * Direct access without a valid token redirects back to the panel.
 * This is the ONLY way to access phpMyAdmin (direct login is disabled).
 */

// Use the same session name phpMyAdmin expects for signon auth
session_name('SignonSession');
@session_start();

// Get token from URL
$token = isset($_GET['token']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['token']) : '';
$db    = isset($_GET['db']) ? $_GET['db'] : '';

// --- No token: redirect to panel with a helpful message ---
if (empty($token)) {
    header('Location: /database?pma_notice=no_token');
    exit;
}

// Validate token path (prevent path traversal)
$tokenDir  = '/usr/local/nimbus/storage/app/pma_tokens';
$tokenFile = $tokenDir . '/' . $token . '.json';

if (!file_exists($tokenFile) || !is_file($tokenFile)) {
    http_response_code(403);
    showErrorPage(
        'Access Denied',
        'Invalid or expired session. Please access phpMyAdmin from the Nimbus panel.',
        'security'
    );
    exit;
}

// Read and decode token data
$raw = file_get_contents($tokenFile);
$tokenData = json_decode($raw, true);

if (!$tokenData || !isset($tokenData['created'])) {
    unlink($tokenFile);
    http_response_code(403);
    showErrorPage(
        'Invalid Token',
        'The session token is malformed. Please try again from the panel.',
        'error'
    );
    exit;
}

// Check expiry (5 minutes)
if (time() - $tokenData['created'] > 300) {
    unlink($tokenFile);
    http_response_code(403);
    showErrorPage(
        'Session Expired',
        'Your session has expired (valid for 5 minutes). Please click the phpMyAdmin button again from the panel.',
        'expired'
    );
    exit;
}

// Consume token (one-time use)
unlink($tokenFile);

// Set phpMyAdmin signon session credentials
$_SESSION['PMA_single_signon_user']     = $tokenData['username'];
$_SESSION['PMA_single_signon_password'] = $tokenData['password'];
$_SESSION['PMA_single_signon_host']     = $tokenData['host'] ?? 'localhost';

// Redirect to phpMyAdmin
$redirectUrl = '/phpmyadmin/index.php';
if (!empty($db)) {
    $redirectUrl .= '?db=' . urlencode($db);
}

header('Location: ' . $redirectUrl);
exit;

// -------------------------------------------------------
// Helper: Show a styled error/redirect page
// -------------------------------------------------------
function showErrorPage($title, $message, $type = 'error')
{
    $icons   = ['error' => '⛔', 'security' => '🔒', 'expired' => '⏱'];
    $icon    = $icons[$type] ?? '⚠';
    $colors  = ['error' => '#e74c3c', 'security' => '#e67e22', 'expired' => '#3498db'];
    $color   = $colors[$type] ?? '#e74c3c';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Nimbus Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 1rem;
            padding: 2.5rem;
            max-width: 440px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
        }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
        h1 { font-size: 1.5rem; font-weight: 700; color: <?= $color ?>; margin-bottom: 0.75rem; }
        p  { color: #94a3b8; line-height: 1.6; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.75rem;
            background: <?= $color ?>;
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.85; }
        .redirect-info {
            margin-top: 1rem;
            font-size: 0.8rem;
            color: #475569;
        }
    </style>
    <script>
        // Auto-redirect after 4 seconds
        setTimeout(function() {
            window.location.href = '/database';
        }, 4000);
    </script>
</head>
<body>
    <div class="card">
        <div class="icon"><?= $icon ?></div>
        <h1><?= htmlspecialchars($title) ?></h1>
        <p><?= htmlspecialchars($message) ?></p>
        <a href="/database" class="btn">Go to Database Manager</a>
        <p class="redirect-info">Redirecting automatically in 4 seconds...</p>
    </div>
</body>
</html>
    <?php
}
