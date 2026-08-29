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
function redirect($url)
{
    header("Location: $url");
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
        redirect('/dairy/index.php');
    }
}


// Allow only specific user roles
function requireRole($roles)
{
    requireLogin();

    if (!in_array($_SESSION['role'], (array) $roles)) {
        redirect('/dairy/dashboard.php');
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