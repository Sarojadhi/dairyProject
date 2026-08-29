<?php

// Client-side entry point for Milk Collection.
// Handles 'add' and 'update' and always answers in JSON so the
// browser can show the result without reloading the page.

session_start();
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole(['admin', 'staff']);

header('Content-Type: application/json');

function apiError($msg)
{
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiError('Invalid request method.');
}

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

// Shared field validation
$litre = (float)($_POST['litre'] ?? 0);
$fat = (float)($_POST['fat'] ?? 0);
$snf = (float)($_POST['snf'] ?? 0);

if ($litre <= 0) {
    apiError('Milk quantity (litres) must be greater than 0.');
}
if ($litre > 200) {
    apiError('Milk quantity looks too high (max 200 litres).');
}
if ($fat <= 0 || $fat > 15) {
    apiError('Fat % must be greater than 0 and up to 15.');
}
if ($snf <= 0 || $snf > 15) {
    apiError('SNF % must be greater than 0 and up to 15.');
}

$rate = getRateForFat($conn, $fat, $snf);

if ($action === 'add') {

    $farmer_id = (int)($_POST['farmer_id'] ?? 0);
    $date = trim($_POST['date'] ?? '');
    $shift = ($_POST['shift'] ?? '') === 'evening' ? 'evening' : 'morning';

    if ($farmer_id < 1) {
        apiError('Please lookup and select a farmer first.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        apiError('Please select a valid date.');
    }
    if ($date > date('Y-m-d')) {
        apiError('Future date is not allowed — use today or a past date.');
    }

    $stmt = $conn->prepare("SELECT id FROM farmers WHERE id = ? AND is_active = 1");
    $stmt->bind_param('i', $farmer_id);
    $stmt->execute();
    $farmer = $stmt->get_result();
    $stmt->close();
    if (!$farmer || $farmer->num_rows === 0) {
        apiError('Farmer not found or is inactive.');
    }

    // The fat & snf for a farmer must stay the same as their previous record.
    // Fetch the farmer's last known fat / snf and lock to it.
    $stmt = $conn->prepare(
        "SELECT fat, snf FROM milk_collection
         WHERE farmer_id = ?
         ORDER BY collection_date DESC, id DESC
         LIMIT 1"
    );
    $stmt->bind_param('i', $farmer_id);
    $stmt->execute();
    $last = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($last) {
        // Farmer has a previous record -> use (and enforce) the same fat/snf.
        if ((float)$last['fat'] !== $fat || (float)$last['snf'] !== $snf) {
            apiError("Please keep the same Fat {$last['fat']}% and SNF {$last['snf']}% as this farmer's previous record.");
        }
    } else {
        // First time this farmer supplies milk -> store what was entered.
        // (Already validated to be > 0 above.)
    }

    $stmt = $conn->prepare(
        "INSERT INTO milk_collection
        (farmer_id, collection_date, shift, litre, fat, snf, rate_per_liter)
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("issdddd", $farmer_id, $date, $shift, $litre, $fat, $snf, $rate);

    try {
        $stmt->execute();
        echo json_encode(['ok' => true, 'msg' => "Milk collection saved for $shift shift."]);
    } catch (mysqli_sql_exception $e) {
        apiError('Could not save the record — please check the values and try again.');
    }
    exit;
}

if ($action === 'update') {

    if ($id < 1) {
        apiError('Missing record id.');
    }

    $stmt = $conn->prepare(
        "UPDATE milk_collection
         SET litre = ?, fat = ?, snf = ?, rate_per_liter = ?
         WHERE id = ?"
    );
    $stmt->bind_param("ddddi", $litre, $fat, $snf, $rate, $id);

    try {
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            echo json_encode(['ok' => false, 'error' => 'Record not found or no changes made.']);
            exit;
        }
        echo json_encode(['ok' => true, 'msg' => 'Milk record updated.']);
    } catch (mysqli_sql_exception $e) {
        apiError('Could not update the record — please try again.');
    }
    exit;
}

apiError('Unknown action.');