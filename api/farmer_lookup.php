<?php

// Start the session so we can check if the user is logged in.
session_start();

// Load database connection and helper functions.
require_once __DIR__ . '/../includes/config.php';

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


// A farmer's fat & snf stay the same on every collection, so return the
// last-known values so the milk entry form can pre-fill (and lock) them.
$farmer['last_fat'] = null;
$farmer['last_snf'] = null;

$stmt = $conn->prepare(
    "SELECT fat, snf
     FROM milk_collection
     WHERE farmer_id = ?
     ORDER BY collection_date DESC, id DESC
     LIMIT 1"
);
$stmt->bind_param('i', $farmer['id']);
$stmt->execute();
$last = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($last) {
    $farmer['last_fat'] = (float) $last['fat'];
    $farmer['last_snf'] = (float) $last['snf'];
}


// Send farmer information as JSON.
echo json_encode($farmer);

?>