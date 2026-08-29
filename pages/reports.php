<?php
require_once '../includes/config.php';
requireLogin();

$pageTitle = 'Reports';

$date = $_GET['date'] ?? date('Y-m-d');

$result = $conn->query("
    SELECT
        f.code,
        f.name,
        mc.shift,
        mc.litre,
        mc.fat,
        mc.snf,
        mc.rate_per_liter,
        mc.total_amount
    FROM milk_collection mc
    JOIN farmers f ON f.id = mc.farmer_id
    WHERE mc.collection_date = '$date'
    ORDER BY f.name
");
?>

<?php include '../includes/header.php'; ?>

<div class="main-content">

<h2>Daily Report</h2>

<form>
    <input type="date" name="date" value="<?= $date ?>">
    <button>Generate</button>
</form>

<table border="1">

<tr>
    <th>Farmer</th>
    <th>Shift</th>
    <th>Litres</th>
    <th>Fat</th>
    <th>SNF</th>
    <th>Rate</th>
    <th>Amount</th>
</tr>

<?php while ($row = $result->fetch_assoc()): ?>

<tr>
    <td><?= $row['code'] ?> - <?= $row['name'] ?></td>
    <td><?= $row['shift'] ?></td>
    <td><?= $row['litre'] ?></td>
    <td><?= $row['fat'] ?></td>
    <td><?= $row['snf'] ?></td>
    <td><?= $row['rate_per_liter'] ?></td>
    <td><?= $row['total_amount'] ?></td>
</tr>

<?php endwhile; ?>

</table>

</div>

<?php include '../includes/footer.php'; ?>