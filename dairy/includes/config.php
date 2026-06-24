<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dairy_db');
define('SITE_NAME', 'Shree Tri Shakti Dairy');
define('UPLOAD_PATH', dirname(__DIR__) . '/assets/uploads/farmers/');
define('UPLOAD_URL', 'assets/uploads/farmers/');

// Pricing formula: rate = (fat × FAT_PRICE) + (snf × SNF_PRICE)
// At 4% fat + 8.0 SNF → 4(10.5) + 8(3.5) = 42 + 28 = 70
define('FAT_PRICE', 10.5);
define('SNF_PRICE', 3.5);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}
$conn->set_charset('utf8mb4');

function sanitize($conn, $data) {
    return $conn->real_escape_string(trim($data));
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('/dairy/index.php');
    }
}

function requireRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], (array)$roles)) {
        redirect('/dairy/dashboard.php');
    }
}

function getRateForFat($conn, $fat, $snf = 0) {
    $fat = (float)$fat;
    $snf = (float)$snf;
    static $fp = null; static $sp = null;
    if ($fp === null) {
        $r = $conn->query("SELECT setting_key, setting_value FROM pricing_settings WHERE setting_key IN ('fat_price','snf_price')");
        while ($row = $r->fetch_assoc()) {
            if ($row['setting_key'] === 'fat_price') $fp = (float)$row['setting_value'];
            if ($row['setting_key'] === 'snf_price') $sp = (float)$row['setting_value'];
        }
    }
    $fp = $fp ?? FAT_PRICE;
    $sp = $sp ?? SNF_PRICE;
    return round(($fat * $fp) + ($snf * $sp), 2);
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . ' min ago';
    if ($diff < 86400) return floor($diff/3600) . ' hr ago';
    return date('d M Y', $time);
}
?>
