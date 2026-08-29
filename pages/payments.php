<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('admin');

$pageTitle = 'Payments';

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');

if (isset($_GET['pay'])) {
    $id = (int)$_GET['pay'];
    $stmt = $conn->prepare("UPDATE payments SET is_paid=1, paid_at=NOW(), paid_by=? WHERE id=?");
    $stmt->bind_param('ii', $_SESSION['user_id'], $id);
    $stmt->execute();
    $stmt->close();
}

$stmt = $conn->prepare("
    SELECT
        p.id,
        f.code,
        f.name,
        p.total_milk_amount,
        p.total_dana_amount,
        p.net_payable,
        p.is_paid,
        p.paid_at
    FROM payments p
    JOIN farmers f ON f.id = p.farmer_id
    WHERE p.payment_month = ?
    ORDER BY f.name
");
$stmt->bind_param('s', $month);
$stmt->execute();
$payments = $stmt->get_result();
$stmt->close();

$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(total_milk_amount),0) AS milk,
        COALESCE(SUM(total_dana_amount),0) AS dana,
        COALESCE(SUM(net_payable),0) AS net
    FROM payments
    WHERE payment_month = ?
");
$stmt->bind_param('s', $month);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM payments WHERE payment_month=? AND is_paid=1");
$stmt->bind_param('s', $month);
$stmt->execute();
$paidCount = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM payments WHERE payment_month=?");
$stmt->bind_param('s', $month);
$stmt->execute();
$totalCount = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1 class="page-title">
            <div class="page-title-icon"><i class="bi bi-wallet2"></i></div>
            Payments
        </h1>
    </div>

    <!-- Month Filter + Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="data-card mb-0">
                <div class="data-card-body">
                    <form method="GET" class="d-flex align-items-end gap-2">
                        <div class="flex-grow-1">
                            <label class="form-label">Select Month</label>
                            <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($month) ?>">
                        </div>
                        <button class="btn btn-teal"><i class="bi bi-funnel me-1"></i>View</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="row g-3">
                <div class="col-sm-4">
                    <div class="stat-card">
                        <div class="stat-value">रू<?= number_format($totals['net'] ?? 0, 0) ?></div>
                        <div class="stat-label">Net Payable</div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="stat-card">
                        <div class="stat-value"><?= $paidCount ?>/<?= $totalCount ?></div>
                        <div class="stat-label">Paid</div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="stat-card">
                        <div class="stat-value" style="color:var(--amber-600)">
                            <?= (int)($totalCount - $paidCount) ?>
                        </div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="data-card">
        <div class="data-card-header">
            <h6 class="data-card-title"><i class="bi bi-table"></i> Payments — <?= date('F Y', strtotime($month . '-01')) ?></h6>
        </div>
        <div class="data-card-body p-0">
            <div class="table-responsive">
                <table class="dairy-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Farmer</th>
                            <th>Milk Amount</th>
                            <th>Dana Amount</th>
                            <th>Net Payable</th>
                            <th>Status</th>
                            <th class="no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($payments->num_rows === 0): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No payments found for this month.</td></tr>
                    <?php endif; ?>
                    <?php $i = 1; while ($p = $payments->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                                <small class="text-muted"><?= $p['code'] ?></small>
                            </td>
                            <td>रू<?= number_format($p['total_milk_amount'], 2) ?></td>
                            <td>रू<?= number_format($p['total_dana_amount'], 2) ?></td>
                            <td><strong style="color:var(--teal-700)">रू<?= number_format($p['net_payable'], 2) ?></strong></td>
                            <td>
                                <?= $p['is_paid']
                                    ? '<span class="badge-paid">✅ Paid</span>'
                                    : '<span class="badge-unpaid">⏳ Pending</span>' ?>
                            </td>
                            <td class="no-print">
                                <?php if (!$p['is_paid']): ?>
                                    <a href="?pay=<?= $p['id'] ?>&month=<?= urlencode($month) ?>"
                                       class="btn-action btn-edit confirm-pay" title="Mark as Paid">
                                        <i class="bi bi-check2-circle"></i> Mark Paid
                                    </a>
                                <?php else: ?>
                                    <small class="text-muted">Paid <?= date('d M Y', strtotime($p['paid_at'])) ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
(function () {
    document.querySelectorAll('.confirm-pay').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirm('Mark this farmer as PAID for ' + 'the selected month' + '?')) e.preventDefault();
        });
    });
})();
</script>
