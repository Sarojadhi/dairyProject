<?php
session_start();

require_once '../../includes/config.php';

requireLogin();
requireRole('farmer');

$pageTitle = 'My Records';

$farmerId = $_SESSION['user_id'];
$month = $_GET['month'] ?? date('Y-m');

/* Farmer information */
$farmer = $conn->query("
    SELECT * FROM farmers
    WHERE id = $farmerId
")->fetch_assoc();

/* Milk records */
$milkRecords = $conn->query("
    SELECT *
    FROM milk_collection
    WHERE farmer_id = $farmerId
    AND DATE_FORMAT(collection_date, '%Y-%m') = '$month'
    ORDER BY collection_date DESC
");

/* Dana records */
$danaRecords = $conn->query("
    SELECT *
    FROM dana_records
    WHERE farmer_id = $farmerId
    AND DATE_FORMAT(dana_date, '%Y-%m') = '$month'
    ORDER BY dana_date DESC
");

/* Milk totals */
$milk = $conn->query("
    SELECT
        SUM(litre) AS litres,
        AVG(fat) AS fat,
        AVG(snf) AS snf,
        SUM(total_amount) AS amount,
        COUNT(*) AS entries
    FROM milk_collection
    WHERE farmer_id = $farmerId
    AND DATE_FORMAT(collection_date, '%Y-%m') = '$month'
")->fetch_assoc();

/* Dana total */
$dana = $conn->query("
    SELECT SUM(total_amount) AS amount
    FROM dana_records
    WHERE farmer_id = $farmerId
    AND DATE_FORMAT(dana_date, '%Y-%m') = '$month'
")->fetch_assoc();

$milkAmount = $milk['amount'] ?? 0;
$danaAmount = $dana['amount'] ?? 0;
$netAmount = $milkAmount - $danaAmount;

/* Payment */
$payment = $conn->query("
    SELECT *
    FROM payments
    WHERE farmer_id = $farmerId
    AND payment_month = '$month'
")->fetch_assoc();
?>

<?php include '../../includes/header.php'; ?>

<div class="main-content">

    <h1>My Records</h1>

    <button onclick="window.print()">Print</button>


    <!-- Farmer Information -->

    <h2>My Information</h2>

    <?php if (!empty($farmer['photo'])): ?>

        <img
            src="/dairy/<?= htmlspecialchars($farmer['photo']) ?>"
            width="100"
            alt="Farmer Photo"
        >

    <?php endif; ?>

    <p>
        <strong>Name:</strong>
        <?= htmlspecialchars($farmer['name']) ?>
    </p>

    <p>
        <strong>Code:</strong>
        <?= htmlspecialchars($farmer['code']) ?>
    </p>

    <p>
        <strong>Phone:</strong>
        <?= htmlspecialchars($farmer['phone'] ?? 'N/A') ?>
    </p>

    <p>
        <strong>Address:</strong>
        <?= htmlspecialchars($farmer['address'] ?? 'N/A') ?>
    </p>

    <p>
        <strong>Member Since:</strong>
        <?= date('d F Y', strtotime($farmer['created_at'])) ?>
    </p>


    <!-- Month -->

    <h2>Monthly Records</h2>

    <form method="GET">

        <label>Select Month:</label>

        <input
            type="month"
            name="month"
            value="<?= htmlspecialchars($month) ?>"
        >

        <button type="submit">View</button>

    </form>


    <!-- Summary -->

    <h2>Summary</h2>

    <p>
        <strong>Total Milk:</strong>
        <?= number_format($milk['litres'] ?? 0, 2) ?> L
    </p>

    <p>
        <strong>Milk Entries:</strong>
        <?= $milk['entries'] ?? 0 ?>
    </p>

    <p>
        <strong>Average Fat:</strong>
        <?= number_format($milk['fat'] ?? 0, 2) ?>%
    </p>

    <p>
        <strong>Average SNF:</strong>
        <?= number_format($milk['snf'] ?? 0, 2) ?>%
    </p>

    <p>
        <strong>Milk Earnings:</strong>
        रू<?= number_format($milkAmount, 2) ?>
    </p>

    <p>
        <strong>Dana Deduction:</strong>
        रू<?= number_format($danaAmount, 2) ?>
    </p>

    <p>
        <strong>Net Payable:</strong>
        रू<?= number_format($netAmount, 2) ?>
    </p>


    <!-- Payment -->

    <h2>Payment</h2>

    <?php if ($payment): ?>

        <?php if ($payment['is_paid']): ?>

            <p>
                <strong>Status:</strong> Paid
            </p>

            <p>
                <strong>Paid Date:</strong>
                <?= date('d F Y', strtotime($payment['paid_at'])) ?>
            </p>

        <?php else: ?>

            <p>
                <strong>Status:</strong> Pending
            </p>

            <p>Contact admin for payment.</p>

        <?php endif; ?>

    <?php else: ?>

        <p>No payment record for this month.</p>

    <?php endif; ?>


    <!-- Milk Records -->

    <h2>Milk Collection</h2>

    <table border="1" cellpadding="8" cellspacing="0">

        <tr>
            <th>#</th>
            <th>Date</th>
            <th>Shift</th>
            <th>Litres</th>
            <th>Fat</th>
            <th>SNF</th>
            <th>Rate/Litre</th>
            <th>Amount</th>
        </tr>

        <?php
        $number = 1;
        $totalLitres = 0;
        $totalAmount = 0;
        ?>

        <?php while ($row = $milkRecords->fetch_assoc()): ?>

            <?php
            $totalLitres += $row['litre'];
            $totalAmount += $row['total_amount'];
            ?>

            <tr>

                <td><?= $number++ ?></td>

                <td>
                    <?= date('d M Y', strtotime($row['collection_date'])) ?>
                </td>

                <td>
                    <?= ucfirst($row['shift']) ?>
                </td>

                <td>
                    <?= number_format($row['litre'], 2) ?> L
                </td>

                <td>
                    <?= number_format($row['fat'], 2) ?>%
                </td>

                <td>
                    <?= number_format($row['snf'], 2) ?>%
                </td>

                <td>
                    रू<?= number_format($row['rate_per_liter'], 2) ?>
                </td>

                <td>
                    रू<?= number_format($row['total_amount'], 2) ?>
                </td>

            </tr>

        <?php endwhile; ?>


        <tr>

            <th colspan="3">Total</th>

            <th>
                <?= number_format($totalLitres, 2) ?> L
            </th>

            <th colspan="3"></th>

            <th>
                रू<?= number_format($totalAmount, 2) ?>
            </th>

        </tr>

    </table>


    <!-- Dana Records -->

    <?php if ($danaRecords->num_rows > 0): ?>

        <h2>Dana Records</h2>

        <table border="1" cellpadding="8" cellspacing="0">

            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Bags</th>
                <th>Rate/Bag</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Notes</th>
            </tr>

            <?php $number = 1; ?>

            <?php while ($row = $danaRecords->fetch_assoc()): ?>

                <tr>

                    <td><?= $number++ ?></td>

                    <td>
                        <?= date('d M Y', strtotime($row['dana_date'])) ?>
                    </td>

                    <td>
                        <?= $row['bags'] ?> bags
                    </td>

                    <td>
                        रू<?= number_format($row['rate_per_bag'], 2) ?>
                    </td>

                    <td>
                        रू<?= number_format($row['total_amount'], 2) ?>
                    </td>

                    <td>
                        <?= $row['is_paid'] ? 'Paid' : 'Not Paid' ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['notes'] ?? '-') ?>
                    </td>

                </tr>

            <?php endwhile; ?>


            <tr>

                <th colspan="4">Total Dana</th>

                <th>
                    रू<?= number_format($danaAmount, 2) ?>
                </th>

                <th colspan="2"></th>

            </tr>

        </table>

    <?php endif; ?>

</div>

<?php include '../../includes/footer.php'; ?>