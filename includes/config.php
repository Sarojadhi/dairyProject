<?php

// Database settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dairy_db');

// Website name
define('SITE_NAME', 'Shree Tri Shakti Dairy');

// Farmer photo upload location
define('UPLOAD_PATH', dirname(__DIR__) . '/assets/uploads/farmers/');
define('UPLOAD_URL', 'assets/uploads/farmers/');

// Base URL for the whole app. Detected automatically so it works no matter
// which folder the project is deployed in (https://host/dairy, /dairyProject,
// a subfolder, or the site root). Trailing slash is included.
function appBaseUrl()
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    // The project root is the parent of this file's folder (includes/).
    $projRoot = str_replace('\\', '/', dirname(__DIR__));
    $docRoot = str_replace('\\', '/', ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $script = str_replace('\\', '/', ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $scriptFile = str_replace('\\', '/', ($_SERVER['SCRIPT_FILENAME'] ?? ''));

    $urlPath = '';

    if ($docRoot !== '' && strpos($projRoot, $docRoot) === 0) {
        // Normal case: project lives inside the web root (XAMPP/WAMP).
        $urlPath = substr($projRoot, strlen($docRoot));
    } elseif ($scriptFile !== '' && strpos($scriptFile, $projRoot) === 0) {
        // Derive the base from how the current script maps to its URL.
        $rel = substr($scriptFile, strlen($projRoot)); // e.g. /pages/x.php
        $urlPath = rtrim(substr($script, 0, max(0, strlen($script) - strlen($rel))), '/');
    } else {
        // Last resort: use the project folder's name.
        $urlPath = '/' . basename($projRoot);
    }

    $base = '/' . trim($urlPath, '/');
    $base = ($base === '/') ? '/' : $base . '/';

    return $base;
}

// Shorthand constant for use in HTML/redirects, e.g. echo BASE_URL . 'css/app.css'
define('BASE_URL', appBaseUrl());

// Default milk prices
define('FAT_PRICE', 10.5);
define('SNF_PRICE', 3.5);


// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check database connection
if ($conn->connect_error) {
    die('Database connection failed');
}

// Support Nepali and other Unicode characters
$conn->set_charset('utf8mb4');


// Clean user input
function sanitize($data)
{
    return trim($data);
}


// Redirect user to another page
// Accepts a path relative to the app root (e.g. 'dashboard.php') or a full URL.
function redirect($url)
{
    if (strpos($url, 'http') === 0 || strpos($url, '/') === 0) {
        header("Location: $url");
    } else {
        header("Location: " . BASE_URL . ltrim($url, '/'));
    }
    exit;
}


// Check if user is logged in
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}


// Allow only logged-in users
function requireLogin()
{
    if (!isLoggedIn()) {
        redirect('index.php');
    }
}


// Allow only specific user roles
function requireRole($roles)
{
    requireLogin();

    if (!in_array($_SESSION['role'], (array) $roles)) {
        redirect('dashboard.php');
    }
}


// Calculate milk rate using FAT and SNF
function getRateForFat($conn, $fat, $snf = 0)
{
    // Get prices from database
    $result = $conn->query("
        SELECT setting_key, setting_value
        FROM pricing_settings
        WHERE setting_key = 'fat_price'
        OR setting_key = 'snf_price'
    ");

    // Use default prices
    $fatPrice = FAT_PRICE;
    $snfPrice = SNF_PRICE;

    // Get prices from database if available
    while ($row = $result->fetch_assoc()) {

        if ($row['setting_key'] == 'fat_price') {
            $fatPrice = $row['setting_value'];
        }

        if ($row['setting_key'] == 'snf_price') {
            $snfPrice = $row['setting_value'];
        }
    }

    // Calculate the final rate
    $rate = ($fat * $fatPrice) + ($snf * $snfPrice);

    return round($rate, 2);
}


// Show how long ago something happened
function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $difference = time() - $time;

    if ($difference < 60) {
        return 'just now';
    }

    if ($difference < 3600) {
        return floor($difference / 60) . ' min ago';
    }

    if ($difference < 86400) {
        return floor($difference / 3600) . ' hr ago';
    }

    return date('d M Y', $time);
}

?>
