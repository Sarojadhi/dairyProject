<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
requireLogin();
requireRole('farmer');

$pageTitle = 'My Records';
$farmerId = $_SESSION['user_id'];
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');

$farmer = $conn->query("SELECT * FROM farmers WHERE id = $farmerId")->fetch_assoc();

$milkRecords = $conn->query("
    SELECT * FROM milk_collection
    WHERE farmer_id = $farmerId
    AND DATE_FORMAT(collection_date, '%Y-%m') = '$month'
    ORDER BY collection_date DESC
");

$danaRecords = $conn->query("
    SELECT * FROM dana_records
    WHERE farmer_id = $farmerId
    AND DATE_FORMAT(dana_date, '%Y-%m') = '$month'
    ORDER BY dana_date DESC
");

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

$dana = $conn->query("
    SELECT SUM(total_amount) AS amount
    FROM dana_records
    WHERE farmer_id = $farmerId
    AND DATE_FORMAT(dana_date, '%Y-%m') = '$month'
")->fetch_assoc();

$milkAmount = $milk['amount'] ?? 0;
$danaAmount = $dana['amount'] ?? 0;
$netAmount = $milkAmount - $danaAmount;

$payment = $conn->query("
    SELECT * FROM payments
    WHERE farmer_id = $farmerId AND payment_month = '$month'
")->fetch_assoc();
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1 class="page-title">
            <div class="page-title-icon"><i class="bi bi-journal-text"></i></div>
            My Records
        </h1>
        <button class="btn btn-sm btn-amber" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>

    <!-- Farmer Info -->
    <div class="farmer-lookup-card mb-4">
        <?php if (!empty($farmer['photo'])): ?>
            <img src="<?= BASE_URL ?><?= htmlspecialchars($farmer['photo']) ?>" class="farmer-photo" alt="Farmer">
        <?php else: ?>
            <div class="farmer-photo-placeholder">👨‍🌾</div>
        <?php endif; ?>
        <div class="farmer-info">
            <h5><?= htmlspecialchars($farmer['name']) ?></h5>
            <span class="farmer-code"><?= htmlspecialchars($farmer['code']) ?></span>
            <p>
                📞 <?= htmlspecialchars($farmer['phone'] ?? 'N/A') ?>
                &nbsp;|&nbsp; 📍 <?= htmlspecialchars($farmer['address'] ?? 'N/A') ?>
                &nbsp;|&nbsp; 🗓 Member since <?= date('d M Y', strtotime($farmer['created_at'])) ?>
            </p>
        </div>
    </div>

    <!-- Month Selector -->
    <div class="data-card mb-4">
        <div class="data-card-body">
            <form method="GET" class="d-flex align-items-end gap-2 flex-wrap">
                <div style="max-width:240px">
                    <label class="form-label">Select Month</label>
                    <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($month) ?>">
                </div>
                <button class="btn btn-teal"><i class="bi bi-funnel me-1"></i>View</button>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($milk['litres'] ?? 0, 2) ?></div>
                <div class="stat-label">Milk (L)</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($milk['fat'] ?? 0, 2) ?>%</div>
                <div class="stat-label">Avg Fat</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-value">रू<?= number_format($milkAmount, 2) ?></div>
                <div class="stat-label">Milk Earnings</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-value" style="color:<?= $netAmount >= 0 ? 'var(--teal-900)' : 'var(--danger)' ?>">
                    रू<?= number_format($netAmount, 2) ?>
                </div>
                <div class="stat-label">Net Payable</div>
            </div>
        </div>
    </div>

    <!-- Payment status -->
    <div class="data-card mb-4">
        <div class="data-card-header">
            <h6 class="data-card-title"><i class="bi bi-wallet2"></i> Payment Status</h6>
        </div>
        <div class="data-card-body">
            <?php if ($payment): ?>
                <?php if ($payment['is_paid']): ?>
                    <span class="badge-paid">✅ Paid</span>
                    <span class="ms-2 text-muted">on <?= date('d M Y', strtotime($payment['paid_at'])) ?></span>
                <?php else: ?>
                    <span class="badge-unpaid">⏳ Pending</span>
                    <span class="ms-2 text-muted">dana deduction: रू<?= number_format($danaAmount, 2) ?></span>
                <?php endif; ?>
            <?php else: ?>
                <p class="mb-0 text-muted">No payment record for this month yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Milk Records -->
    <div class="data-card">
        <div class="data-card-header">
            <h6 class="data-card-title"><i class="bi bi-droplet-fill"></i> Milk Collection — <?= date('F Y', strtotime($month . '-01')) ?></h6>
        </div>
        <div class="data-card-body p-0">
            <div class="table-responsive">
                <table class="dairy-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Shift</th>
                            <th>Litres</th>
                            <th>Fat %</th>
                            <th>SNF %</th>
                            <th>Rate/L</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $number = 1;
                    $totalLitres = 0;
                    $totalAmount = 0;
                    if ($milkRecords->num_rows === 0):
                    ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No milk collection recorded this month.</td></tr>
                    <?php endif; ?>
                    <?php while ($row = $milkRecords->fetch_assoc()):
                        $totalLitres += $row['litre'];
                        $totalAmount += $row['total_amount'];
                    ?>
                        <tr>
                            <td><?= $number++ ?></td>
                            <td><?= date('d M Y', strtotime($row['collection_date'])) ?></td>
                            <td>
                                <?= $row['shift'] === 'morning'
                                    ? '<span class="shift-morning">☀️ Morning</span>'
                                    : '<span class="shift-evening">🌙 Evening</span>' ?>
                            </td>
                            <td><?= number_format($row['litre'], 2) ?> L</td>
                            <td><?= number_format($row['fat'], 2) ?>%</td>
                            <td><?= number_format($row['snf'], 2) ?>%</td>
                            <td>रू<?= number_format($row['rate_per_liter'], 2) ?></td>
                            <td><strong style="color:var(--teal-700)">रू<?= number_format($row['total_amount'], 2) ?></strong></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                    <?php if ($milkRecords->num_rows > 0): ?>
                    <tfoot>
                        <tr>
                            <th colspan="3">Total</th>
                            <th><?= number_format($totalLitres, 2) ?> L</th>
                            <th colspan="3"></th>
                            <th>रू<?= number_format($totalAmount, 2) ?></th>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Dana Records -->
    <?php if ($danaRecords->num_rows > 0): ?>
    <div class="data-card">
        <div class="data-card-header">
            <h6 class="data-card-title"><i class="bi bi-basket3-fill"></i> Dana Records</h6>
        </div>
        <div class="data-card-body p-0">
            <div class="table-responsive">
                <table class="dairy-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Bags</th>
                            <th>Rate/Bag</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $number = 1; while ($row = $danaRecords->fetch_assoc()): ?>
                        <tr>
                            <td><?= $number++ ?></td>
                            <td><?= date('d M Y', strtotime($row['dana_date'])) ?></td>
                            <td><?= $row['bags'] ?> bags</td>
                            <td>रू<?= number_format($row['rate_per_bag'], 2) ?></td>
                            <td><strong style="color:var(--danger)">रू<?= number_format($row['total_amount'], 2) ?></strong></td>
                            <td>
                                <?= $row['is_paid']
                                    ? '<span class="badge-paid">✅ Paid</span>'
                                    : '<span class="badge-unpaid">❌ Not Paid</span>' ?>
                            </td>
                            <td><?= htmlspecialchars($row['notes'] ?? '—') ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4">Total Dana</th>
                            <th>रू<?= number_format($danaAmount, 2) ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
