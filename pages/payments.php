<?php
require_once '../includes/config.php';
requireLogin();

$month = $_GET['month'] ?? date('Y-m');

if (isset($_GET['pay'])) {
    $id = $_GET['pay'];

    $conn->query(
        "UPDATE payments SET is_paid=1 WHERE id=$id"
    );
}

$payments = $conn->query("
    SELECT 
        p.id,
        f.code,
        f.name,
        p.total_milk_amount,
        p.total_dana_amount,
        p.net_payable,
        p.is_paid
    FROM payments p
    JOIN farmers f ON f.id = p.farmer_id
    WHERE p.payment_month = '$month'
");
?>

<h2>Payments</h2>

<form>
    <input type="month" name="month" value="<?= $month ?>">
    <button>View</button>
</form>

<table border="1">

<tr>
    <th>Farmer</th>
    <th>Milk</th>
    <th>Dana</th>
    <th>Net</th>
    <th>Status</th>
    <th>Action</th>
</tr>

<?php while ($p = $payments->fetch_assoc()): ?>

<tr>
    <td><?= $p['code'] ?> - <?= $p['name'] ?></td>
    <td>Rs. <?= $p['total_milk_amount'] ?></td>
    <td>Rs. <?= $p['total_dana_amount'] ?></td>
    <td>Rs. <?= $p['net_payable'] ?></td>

    <td>
        <?= $p['is_paid'] ? 'Paid' : 'Pending' ?>
    </td>

    <td>
        <?php if (!$p['is_paid']): ?>
            <a href="?pay=<?= $p['id'] ?>&month=<?= $month ?>">
                Mark Paid
            </a>
        <?php endif; ?>
    </td>
</tr>

<?php endwhile; ?>

</table>