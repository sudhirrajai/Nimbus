<?php
/**
 * phpMyAdmin Single Sign-On Script for Nimbus Panel
 * This script handles automatic login to phpMyAdmin from the Nimbus panel
 */

session_name('SignonSession');
session_start();

// Check if credentials are set in session
if (!isset($_SESSION['PMA_single_signon_user']) || !isset($_SESSION['PMA_single_signon_password'])) {
    // No credentials - redirect to panel
    header('Location: /database');
    exit;
}

// Set the credentials for phpMyAdmin
$_SESSION['PMA_single_signon_host'] = $_SESSION['PMA_single_signon_host'] ?? 'localhost';

// Get the database to open (if any)
$db = $_SESSION['PMA_single_signon_db'] ?? '';

// Clear the stored password after setting up the session
// (phpMyAdmin will read it once)
$user = $_SESSION['PMA_single_signon_user'];
$pass = $_SESSION['PMA_single_signon_password'];
$host = $_SESSION['PMA_single_signon_host'];

// Redirect to phpMyAdmin with the database parameter
$redirect = '/phpmyadmin/index.php';
if ($db) {
    $redirect .= '?db=' . urlencode($db);
}

header('Location: ' . $redirect);
exit;
