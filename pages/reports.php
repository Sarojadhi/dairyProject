<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole(['admin', 'staff']);

$pageTitle = 'Reports';

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
if ($date > date('Y-m-d')) $date = date('Y-m-d');

$stmt = $conn->prepare("
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
    WHERE mc.collection_date = ?
    ORDER BY mc.shift, f.name
");
$stmt->bind_param('s', $date);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS entries,
        COALESCE(SUM(litre),0) AS litres,
        COALESCE(SUM(total_amount),0) AS amount
    FROM milk_collection
    WHERE collection_date = ?
");
$stmt->bind_param('s', $date);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM farmers WHERE is_active=1");
$stmt->execute();
$totalCount = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1 class="page-title">
            <div class="page-title-icon"><i class="bi bi-bar-chart-line-fill"></i></div>
            Daily Report
        </h1>
        <button class="btn btn-sm btn-amber" onclick="printSection('reportTable')"><i class="bi bi-printer"></i> Print</button>
    </div>

    <!-- Date Filter -->
    <div class="form-card">
        <form method="GET" class="d-flex align-items-end gap-2 flex-wrap">
            <div style="max-width:240px">
                <label class="form-label">Select Date</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <button class="btn btn-teal"><i class="bi bi-funnel me-1"></i>Generate</button>
        </form>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-value"><?= $totals['entries'] ?></div>
                <div class="stat-label">Milk Entries</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($totals['litres'], 2) ?> L</div>
                <div class="stat-label">Total Milk</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-value" style="color:var(--amber-600)">रू<?= number_format($totals['amount'], 2) ?></div>
                <div class="stat-label">Total Amount</div>
            </div>
        </div>
    </div>

    <div class="data-card">
        <div class="data-card-header">
            <h6 class="data-card-title"><i class="bi bi-table"></i> Collection — <?= date('d M Y', strtotime($date)) ?></h6>
        </div>
        <div class="data-card-body p-0">
            <div id="reportTable" class="table-responsive">
                <table class="dairy-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Farmer</th>
                            <th>Shift</th>
                            <th>Litres</th>
                            <th>Fat %</th>
                            <th>SNF %</th>
                            <th>Rate/L</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No milk collection recorded on this date.</td></tr>
                    <?php endif; ?>
                    <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($row['name']) ?></strong><br>
                                <small class="text-muted"><?= $row['code'] ?></small>
                            </td>
                            <td>
                                <?= $row['shift'] === 'morning'
                                    ? '<span class="shift-morning">☀️ Morning</span>'
                                    : '<span class="shift-evening">🌙 Evening</span>' ?>
                            </td>
                            <td><?= number_format($row['litre'], 2) ?></td>
                            <td><?= number_format($row['fat'], 1) ?></td>
                            <td><?= number_format($row['snf'], 1) ?></td>
                            <td>रू<?= number_format($row['rate_per_liter'], 2) ?></td>
                            <td><strong style="color:var(--teal-700)">रू<?= number_format($row['total_amount'], 2) ?></strong></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                    <?php if ($result->num_rows > 0): ?>
                    <tfoot>
                        <tr>
                            <th colspan="3">Total (<?= $totals['entries'] ?> entries)</th>
                            <th><?= number_format($totals['litres'], 2) ?> L</th>
                            <th colspan="3"></th>
                            <th>रू<?= number_format($totals['amount'], 2) ?></th>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
