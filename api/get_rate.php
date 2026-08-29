


<?php

// Start the session.
session_start();

// Load the database connection and helper functions.
require_once '../includes/config.php';

// Check if the user is logged in.
requireLogin();

// Tell the browser that we are sending JSON data.
header('Content-Type: application/json');


// Get FAT value from the URL.
// If FAT is not provided, use 0.
$fat = $_GET['fat'] ?? 0;

// Convert FAT into a number.
$fat = (float) $fat;


// Get SNF value from the URL.
// If SNF is not provided, use 0.
$snf = $_GET['snf'] ?? 0;

// Convert SNF into a number.
$snf = (float) $snf;


// Find the milk rate based on FAT and SNF.
$rate = getRateForFat($conn, $fat, $snf);


// Send the rate back as JSON.
echo json_encode([
    'rate' => $rate
]);

?>