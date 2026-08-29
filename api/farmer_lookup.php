
<?php

// Start the session so we can check if the user is logged in.
session_start();

// Load database connection and helper functions.
require_once '../includes/config.php';

// Make sure only logged-in users can access this file.
requireLogin();

// Tell the browser that we are returning JSON data.
header('Content-Type: application/json');


// Get the farmer code from the URL.
// Example: farmer.php?code=F001
$code = $_GET['code'] ?? '';


// Check if the farmer code was provided.
if (empty($code)) {
    echo json_encode([
        'error' => 'Farmer code is required'
    ]);
    exit;
}


// Find the farmer using the farmer code.
$sql = "SELECT id, code, name, phone, address, photo, is_active
        FROM farmers
        WHERE code = ?";

$stmt = $conn->prepare($sql);

// Put the farmer code into the SQL query safely.
$stmt->bind_param('s', $code);

// Run the query.
$stmt->execute();

// Get the result.
$result = $stmt->get_result();


// Check if the farmer exists.
if ($result->num_rows == 0) {
    echo json_encode([
        'error' => "Farmer code '$code' not found"
    ]);
    exit;
}


// Get farmer information.
$farmer = $result->fetch_assoc();


// Get the current month.
// Example: 2026-08
$currentMonth = date('Y-m');


// Find how much milk the farmer supplied this month.
$sql = "SELECT SUM(total_amount) AS total
        FROM milk_collection
        WHERE farmer_id = ?
        AND DATE_FORMAT(collection_date, '%Y-%m') = ?";

$stmt = $conn->prepare($sql);

// Put farmer ID and current month into the query.
$stmt->bind_param('is', $farmer['id'], $currentMonth);

// Run the query.
$stmt->execute();

// Get the result.
$result = $stmt->get_result();

// Get the total milk amount.
$milk = $result->fetch_assoc();


// If there is no milk collection, use 0.
$milkTotal = $milk['total'] ?? 0;


// Add the monthly milk total to farmer information.
$farmer['month_milk_total'] = (float) $milkTotal;


// Send farmer information as JSON.
echo json_encode($farmer);

?>