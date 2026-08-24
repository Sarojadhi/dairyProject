<?php
session_start();
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');
$code = sanitize($conn, $_GET['code'] ?? '');
if (!$code) { echo json_encode(['error' => 'No code provided']); exit; }

$stmt = $conn->prepare("SELECT id, code, name, phone, address, photo, is_active FROM farmers WHERE code = ?");
$stmt->bind_param('s', $code);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['error' => "Farmer code '$code' not found"]);
    exit;
}
$farmer = $result->fetch_assoc();
// Include current month milk total for net calculation
$thisMonth = date('Y-m');
$milkTotal = $conn->query("SELECT COALESCE(SUM(total_amount),0) as t FROM milk_collection WHERE farmer_id={$farmer['id']} AND DATE_FORMAT(collection_date,'%Y-%m')='$thisMonth'")->fetch_assoc()['t'];
$farmer['month_milk_total'] = (float)$milkTotal;
echo json_encode($farmer);
