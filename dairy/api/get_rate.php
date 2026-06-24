<?php
session_start();
require_once '../includes/config.php';
requireLogin();
header('Content-Type: application/json');
$fat = (float)($_GET['fat'] ?? 0);
$snf = (float)($_GET['snf'] ?? 0);
$rate = getRateForFat($conn, $fat, $snf);
echo json_encode(['rate' => $rate]);
