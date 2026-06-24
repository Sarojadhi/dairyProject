<?php
session_start();
require_once '../includes/config.php';
requireLogin();
requireRole('admin');

$pageTitle = 'Payments';
$msg = ''; $err = '';
$filterMonth = sanitize($conn, $_GET['month'] ?? date('Y-m'));

// Generate payment summary for selected month
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payments'])) {
    $month = sanitize($conn, $_POST['month']);
    // For each active farmer, upsert payment record
    $activeFarmers = $conn->query("SELECT id FROM farmers WHERE is_active=1");
    $count = 0;
    while ($f = $activeFarmers->fetch_assoc()) {
        $fid = $f['id'];
        $milkAmt = $conn->query("SELECT COALESCE(SUM(total_amount),0) as t FROM milk_collection WHERE farmer_id=$fid AND DATE_FORMAT(collection_date,'%Y-%m')='$month'")->fetch_assoc()['t'];
        $danaAmt = $conn->query("SELECT COALESCE(SUM(total_amount),0) as t FROM dana_records WHERE farmer_id=$fid AND DATE_FORMAT(dana_date,'%Y-%m')='$month'")->fetch_assoc()['t'];

        if ($milkAmt > 0 || $danaAmt > 0) {
            // Check if exists
            $exists = $conn->query("SELECT id FROM payments WHERE farmer_id=$fid AND payment_month='$month'")->fetch_assoc();
            if ($exists) {
                $conn->query("UPDATE payments SET total_milk_amount=$milkAmt, total_dana_amount=$danaAmt WHERE farmer_id=$fid AND payment_month='$month' AND is_paid=0");
            } else {
                $conn->query("INSERT INTO payments (farmer_id, payment_month, total_milk_amount, total_dana_amount) VALUES ($fid, '$month', $milkAmt, $danaAmt)");
            }
            $count++;
        }
    }
    $msg = "✅ Payment summary generated for $count farmers for " . date('F Y', strtotime($month . '-01')) . ".";
    $filterMonth = $month;
}

// Mark as paid
if (isset($_GET['pay']) && is_numeric($_GET['pay'])) {
    $pid = (int)$_GET['pay'];
    $by = $_SESSION['user_id'];
    $conn->query("UPDATE payments SET is_paid=1, paid_at=NOW(), paid_by=$by WHERE id=$pid");
    $msg = "✅ Payment marked as paid.";
}

// Mark as unpaid
if (isset($_GET['unpay']) && is_numeric($_GET['unpay'])) {
    $pid = (int)$_GET['unpay'];
    $conn->query("UPDATE payments SET is_paid=0, paid_at=NULL, paid_by=NULL WHERE id=$pid");
    $msg = "Payment reverted to unpaid.";
}

// Delete payment record
if (isset($_GET['delete'])) {
    $pid = (int)$_GET['delete'];
    $conn->query("DELETE FROM payments WHERE id=$pid AND is_paid=0");
    $msg = "🗑️ Payment record deleted.";
}

// Fetch payment records for selected month
$payments = $conn->query("
    SELECT p.*, f.name, f.code, f.photo,
           u.name as paid_by_name
    FROM payments p
    JOIN farmers f ON f.id = p.farmer_id
    LEFT JOIN users u ON u.id = p.paid_by
    WHERE p.payment_month = '$filterMonth'
    ORDER BY p.is_paid ASC, f.name ASC
");

// Summary totals
$totals = $conn->query("
    SELECT
        COUNT(*) as total_farmers,
        SUM(total_milk_amount) as total_milk,
        SUM(total_dana_amount) as total_dana,
        SUM(net_payable) as total_net,
        SUM(CASE WHEN is_paid=1 THEN net_payable ELSE 0 END) as paid_amount,
        SUM(CASE WHEN is_paid=0 THEN net_payable ELSE 0 END) as pending_amount,
        SUM(CASE WHEN is_paid=1 THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN is_paid=0 THEN 1 ELSE 0 END) as pending_count
    FROM payments WHERE payment_month='$filterMonth'
")->fetch_assoc();
?>
<?php include '../includes/header.php'; ?>
<div class="main-content">

<div class="page-header">
    <h1 class="page-title">
        <div class="page-title-icon"><i class="bi bi-wallet2"></i></div>
        Payment Settlement
    </h1>
    <button class="btn btn-amber no-print" onclick="window.print()">
        <i class="bi bi-printer me-2"></i>Print
    </button>
</div>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger alert-dismissible fade show"><?= $err ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Generate Payment Form -->
<div class="form-card mb-3">
    <div class="form-section-title">📅 Generate Monthly Payment Summary</div>
    <form class="row g-2 align-items-end" method="POST">
        <div class="col-auto">
            <label class="form-label mb-1">Select Month</label>
            <input type="month" name="month" class="form-control" value="<?= $filterMonth ?>">
        </div>
        <div class="col-auto">
            <button type="submit" name="generate_payments" class="btn btn-teal">
                <i class="bi bi-calculator me-2"></i>Generate Summary
            </button>
        </div>
        <div class="col-auto">
            <a href="?month=<?= $filterMonth ?>" class="btn btn-outline-secondary">
                <i class="bi bi-eye me-2"></i>View <?= date('F Y', strtotime($filterMonth . '-01')) ?>
            </a>
        </div>
    </form>
</div>

<!-- Summary Stats -->
<?php if ($totals && $totals['total_farmers'] > 0): ?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon teal"><i class="bi bi-people-fill"></i></div>
            <div class="stat-value"><?= $totals['total_farmers'] ?></div>
            <div class="stat-label">Total Farmers</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-currency-rupee"></i></div>
            <div class="stat-value">रू<?= number_format($totals['total_net'], 0) ?></div>
            <div class="stat-label">Total Net Payable</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-value">रू<?= number_format($totals['paid_amount'], 0) ?></div>
            <div class="stat-label">Paid (<?= $totals['paid_count'] ?> farmers)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-value">रू<?= number_format($totals['pending_amount'], 0) ?></div>
            <div class="stat-label">Pending (<?= $totals['pending_count'] ?> farmers)</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Payment Table -->
<div class="data-card" id="paymentPrint">
    <div class="data-card-header">
        <h6 class="data-card-title">
            <i class="bi bi-table"></i>
            Payment Records — <?= date('F Y', strtotime($filterMonth . '-01')) ?>
        </h6>
    </div>

    <?php if (!$payments || $payments->num_rows === 0): ?>
    <div class="data-card-body">
        <div class="alert alert-warning mb-0">
            <i class="bi bi-exclamation-triangle me-2"></i>
            No payment records for <?= date('F Y', strtotime($filterMonth . '-01')) ?>.
            Click "Generate Summary" to create payment records from milk collection data.
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="dairy-table">
            <thead>
                <tr>
                    <th>Farmer</th>
                    <th>Milk Earnings</th>
                    <th>Dana Deduct</th>
                    <th>Net Payable</th>
                    <th>Status</th>
                    <th>Paid On</th>
                    <th class="no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($p = $payments->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($p['photo']): ?>
                            <img src="/dairy/<?= $p['photo'] ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid var(--teal-300);">
                            <?php else: ?>
                            <span style="font-size:1.6rem">👨‍🌾</span>
                            <?php endif; ?>
                            <div>
                                <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                                <small class="text-muted"><?= $p['code'] ?></small>
                            </div>
                        </div>
                    </td>
                    <td style="color:var(--teal-700)"><strong>रू<?= number_format($p['total_milk_amount'], 2) ?></strong></td>
                    <td style="color:var(--danger)">रू<?= number_format($p['total_dana_amount'], 2) ?></td>
                    <td>
                        <strong style="font-size:1.05rem;color:var(--teal-900)">
                            रू<?= number_format($p['net_payable'], 2) ?>
                        </strong>
                    </td>
                    <td>
                        <?php if ($p['is_paid']): ?>
                        <span class="badge-paid">✅ Paid</span>
                        <?php else: ?>
                        <span class="badge-unpaid">⏳ Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['is_paid'] && $p['paid_at']): ?>
                        <small><?= date('d M Y', strtotime($p['paid_at'])) ?><br>
                        by <?= htmlspecialchars($p['paid_by_name'] ?? '—') ?></small>
                        <?php else: ?>
                        <small class="text-muted">—</small>
                        <?php endif; ?>
                    </td>
                    <td class="no-print">
                        <?php if (!$p['is_paid']): ?>
                        <a href="?pay=<?= $p['id'] ?>&month=<?= $filterMonth ?>"
                           class="btn-action btn-view me-1"
                           onclick="return confirm('Mark this payment as PAID?')"
                           title="Mark Paid">
                            <i class="bi bi-check2-circle"></i> Pay
                        </a>
                        <a href="?delete=<?= $p['id'] ?>&month=<?= $filterMonth ?>"
                           class="btn-action btn-delete confirm-delete"
                           title="Delete">
                            <i class="bi bi-trash"></i>
                        </a>
                        <?php else: ?>
                        <a href="?unpay=<?= $p['id'] ?>&month=<?= $filterMonth ?>"
                           class="btn-action btn-delete"
                           onclick="return confirm('Revert payment to UNPAID?')"
                           title="Revert">
                            <i class="bi bi-arrow-counterclockwise"></i> Revert
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
            <!-- Totals Row -->
            <?php if ($totals && $totals['total_farmers'] > 0): ?>
            <tfoot>
                <tr style="background:var(--teal-900);color:#fff;font-weight:700;">
                    <td>TOTAL (<?= $totals['total_farmers'] ?> farmers)</td>
                    <td>रू<?= number_format($totals['total_milk'], 2) ?></td>
                    <td>रू<?= number_format($totals['total_dana'], 2) ?></td>
                    <td>रू<?= number_format($totals['total_net'], 2) ?></td>
                    <td colspan="3">
                        Paid: <?= $totals['paid_count'] ?> &nbsp;|&nbsp;
                        Pending: <?= $totals['pending_count'] ?>
                    </td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

</div><!-- /main-content -->
<?php include '../includes/footer.php'; ?>
