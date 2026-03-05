<?php
/**
 * Database Viewer Single Sign-On — Nimbus Panel
 *
 * Validates a one-time token issued by the panel, sets session credentials
 * for the Database Viewer SSO wrapper, then redirects to /adminer/.
 *
 * Direct access without a valid token is blocked.
 */

// Standard session — shared with public/adminer/index.php (same domain)
@session_start();

$token = isset($_GET['token']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['token']) : '';
$db    = isset($_GET['db'])    ? $_GET['db'] : '';

if (empty($token)) {
    header('Location: /database?notice=no_token');
    exit;
}

$tokenDir  = '/usr/local/nimbus/storage/app/pma_tokens';
$tokenFile = $tokenDir . '/' . $token . '.json';

if (!file_exists($tokenFile) || !is_file($tokenFile)) {
    showError('Access Denied', 'Invalid or expired session. Please access the Database Viewer from the Nimbus panel.', 'security');
    exit;
}

$raw       = file_get_contents($tokenFile);
$tokenData = json_decode($raw, true);

if (!$tokenData || !isset($tokenData['created'])) {
    @unlink($tokenFile);
    showError('Invalid Token', 'The session token is malformed. Please try again from the panel.', 'error');
    exit;
}

if (time() - $tokenData['created'] > 300) {
    @unlink($tokenFile);
    showError('Session Expired', 'Session tokens are valid for 5 minutes. Please click the DB Manager button again.', 'expired');
    exit;
}

// Consume token (one-time use)
@unlink($tokenFile);

// Set Database Viewer session credentials (read by public/adminer/index.php)
$_SESSION['adminer_server']   = $tokenData['host']     ?? 'localhost';
$_SESSION['adminer_username'] = $tokenData['username'];
$_SESSION['adminer_password'] = $tokenData['password'];
$_SESSION['adminer_db']       = $db;
$_SESSION['adminer_created']  = time();

// Redirect to Database Viewer
$target = '/adminer/';
if (!empty($db)) {
    $target .= '?db=' . urlencode($db);
}
header('Location: ' . $target);
exit;

// ─── Helper: styled error page with auto-redirect ───────────────────────────
function showError($title, $message, $type = 'error')
{
    $icons  = ['error' => '⛔', 'security' => '🔒', 'expired' => '⏱'];
    $colors = ['error' => '#e74c3c', 'security' => '#e67e22', 'expired' => '#3498db'];
    $icon   = $icons[$type]  ?? '⚠';
    $color  = $colors[$type] ?? '#e74c3c';
    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Nimbus Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 1rem; padding: 2.5rem; max-width: 440px; width: 100%; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,.4); }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
        h1   { font-size: 1.5rem; font-weight: 700; color: <?= $color ?>; margin-bottom: .75rem; }
        p    { color: #94a3b8; line-height: 1.6; margin-bottom: 1.5rem; font-size: .95rem; }
        .btn { display: inline-block; padding: .75rem 1.75rem; background: <?= $color ?>; color: #fff; text-decoration: none; border-radius: .5rem; font-weight: 600; font-size: .9rem; }
        .btn:hover { opacity: .85; }
        small { color: #475569; font-size: .8rem; margin-top: .75rem; display: block; }
    </style>
    <script>setTimeout(() => location.href = '/database', 4000)</script>
</head>
<body>
    <div class="card">
        <div class="icon"><?= $icon ?></div>
        <h1><?= htmlspecialchars($title) ?></h1>
        <p><?= htmlspecialchars($message) ?></p>
        <a href="/database" class="btn">Go to Database Manager</a>
        <small>Redirecting in 4 seconds...</small>
    </div>
</body>
</html>
    <?php
}
