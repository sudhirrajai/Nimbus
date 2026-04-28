<?php
/**
 * Nimbus DB SSO Wrapper
 */
@session_start();

if (isset($_GET['logout'])) {
    unset($_SESSION['adminer_username'], $_SESSION['adminer_password'], $_SESSION['adminer_server'], $_SESSION['adminer_db']);
    header('Location: /database');
    exit;
}

if (empty($_SESSION['adminer_username'])) {
    header('Location: /database?notice=unauthorized');
    exit;
}

if (isset($_SESSION['adminer_created']) && (time() - $_SESSION['adminer_created'] > 900)) {
    unset($_SESSION['adminer_username'], $_SESSION['adminer_password'], $_SESSION['adminer_server']);
    header('Location: /database?notice=session_expired');
    exit;
}

function adminer_object() {
    class NimbusDB extends Adminer {
        function name() { return 'Nimbus DB'; }
        function credentials() {
            return [$_SESSION['adminer_server'] ?? 'localhost', $_SESSION['adminer_username'], $_SESSION['adminer_password']];
        }
        function database() {
            return $_SESSION['adminer_db'] ?? '';
        }
        function databases($flush = true) {
            if (isset($_GET['server'])) {
                $return = get_databases($flush);
                $key = array_search('nimbus', $return);
                if ($key !== false) {
                    unset($return[$key]);
                }
                return array_values($return);
            }
            return [$_SESSION['adminer_db'] ?? ''];
        }
        function login($login, $password) {
            return true;
        }
    }
    return new NimbusDB;
}

ob_start(function($buffer) {
    // Replace text branding safely without breaking file paths (like adminer.css)
    $buffer = str_replace('Adminer', 'System', $buffer);
    $buffer = str_replace('<title>System', '<title>Database', $buffer);
    // Remove donation message
    $buffer = preg_replace('/<i[^>]*>\s*Thanks for using.*?donating.*?<\/i>/is', '', $buffer);
    $buffer = preg_replace('/Thanks for using.*?donating.*?<\/a>\./is', '', $buffer);
    return $buffer;
});

include '/usr/share/adminer/adminer.php';
ob_end_flush();