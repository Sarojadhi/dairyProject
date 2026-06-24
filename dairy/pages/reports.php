<?php
session_start();
require_once '../includes/config.php';
requireLogin();

$pageTitle = 'Reports';
$role = $_SESSION['role'];

$reportType = $_GET['type'] ?? 'daily';
$filterDate = $_GET['date'] ?? date('Y-m-d');
$filterMonth = $_GET['month'] ?? date('Y-m');
$filterFarmer = (int)($_GET['farmer_id'] ?? ($role === 'farmer' ? $_SESSION['user_id'] : 0));

$farmers = $conn->query("SELECT id, code, name FROM farmers WHERE is_active=1 ORDER BY name");

// --- DAILY REPORT ---
$dailyData = null;
$dailySummary = null;
if ($reportType === 'daily') {
    $where = "mc.collection_date='$filterDate'";
    if ($role === 'farmer') $where .= " AND mc.farmer_id=" . $_SESSION['user_id'];
    $dailyData = $conn->query("SELECT mc.*, f.name, f.code FROM milk_collection mc JOIN farmers f ON f.id=mc.farmer_id WHERE $where ORDER BY mc.shift, f.name");
    $dailySummary = $conn->query("SELECT shift, COUNT(*) as entries, SUM(litre) as total_litre, AVG(fat) as avg_fat, SUM(total_amount) as total_amount FROM milk_collection mc WHERE $where GROUP BY shift");
}

// --- MONTHLY REPORT ---
$monthlyData = null;
$monthlySummary = null;
if ($reportType === 'monthly') {
    $where = "DATE_FORMAT(mc.collection_date,'%Y-%m')='$filterMonth'";
    if ($role === 'farmer') $where .= " AND mc.farmer_id=" . $_SESSION['user_id'];
    $monthlyData = $conn->query("SELECT f.code, f.name, SUM(mc.litre) as total_litre, AVG(mc.fat) as avg_fat, AVG(mc.snf) as avg_snf, SUM(mc.total_amount) as total_milk_amt, COALESCE((SELECT SUM(dr.total_amount) FROM dana_records dr WHERE dr.farmer_id=f.id AND DATE_FORMAT(dr.dana_date,'%Y-%m')='$filterMonth'),0) as total_dana FROM milk_collection mc JOIN farmers f ON f.id=mc.farmer_id WHERE $where GROUP BY mc.farmer_id ORDER BY total_litre DESC");
    $monthlySummary = $conn->query("SELECT COUNT(DISTINCT farmer_id) as farmers, SUM(litre) as total_litre, SUM(total_amount) as total_amount FROM milk_collection mc WHERE $where");
}

// --- FARMER REPORT ---
$farmerData = null;
$farmerInfo = null;
if ($reportType === 'farmer' && $filterFarmer > 0) {
    $fid = $filterFarmer;
    $farmerInfo = $conn->query("SELECT * FROM farmers WHERE id=$fid")->fetch_assoc();
    $farmerData = $conn->query("SELECT mc.collection_date, mc.shift, mc.litre, mc.fat, mc.snf, mc.rate_per_liter, mc.total_amount FROM milk_collection mc WHERE mc.farmer_id=$fid AND DATE_FORMAT(mc.collection_date,'%Y-%m')='$filterMonth' ORDER BY mc.collection_date, mc.shift");
    $farmerDana = $conn->query("SELECT * FROM dana_records WHERE farmer_id=$fid AND DATE_FORMAT(dana_date,'%Y-%m')='$filterMonth' ORDER BY dana_date");
    $farmerTotals = $conn->query("SELECT SUM(litre) as tl, SUM(total_amount) as ta FROM milk_collection WHERE farmer_id=$fid AND DATE_FORMAT(collection_date,'%Y-%m')='$filterMonth'")->fetch_assoc();
    $danaTotals = $conn->query("SELECT SUM(total_amount) as td FROM dana_records WHERE farmer_id=$fid AND DATE_FORMAT(dana_date,'%Y-%m')='$filterMonth'")->fetch_assoc();
}
if ($role === 'farmer' && $reportType === 'farmer') {
    $filterFarmer = $_SESSION['user_id'];
}
?>
<?php include '../includes/header.php'; ?>
<div class="main-content">
<div class="page-header">
    <h1 class="page-title">
        <div class="page-title-icon"><i class="bi bi-bar-chart-line-fill"></i></div>
        Reports & Analysis
    </h1>
    <button class="btn btn-amber no-print" onclick="window.print()"><i class="bi bi-printer me-2"></i>Print Report</button>
</div>

<!-- Report Type Tabs -->
<div class="data-card mb-3">
    <div class="data-card-body py-2">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a href="?type=daily&date=<?= $filterDate ?>" class="btn <?= $reportType === 'daily' ? 'btn-teal' : 'btn-outline-secondary' ?> btn-sm">
                <i class="bi bi-calendar-day"></i> Daily Report
            </a>
            <a href="?type=monthly&month=<?= $filterMonth ?>" class="btn <?= $reportType === 'monthly' ? 'btn-teal' : 'btn-outline-secondary' ?> btn-sm">
                <i class="bi bi-calendar-month"></i> Monthly Report
            </a>
            <a href="?type=farmer&farmer_id=<?= $filterFarmer ?>&month=<?= $filterMonth ?>" class="btn <?= $reportType === 'farmer' ? 'btn-teal' : 'btn-outline-secondary' ?> btn-sm">
                <i class="bi bi-person-lines-fill"></i> Farmer Report
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="form-card mb-3 py-3">
    <form class="row g-2 align-items-end" method="GET">
        <input type="hidden" name="type" value="<?= $reportType ?>">
        <?php if ($reportType === 'daily'): ?>
        <div class="col-auto">
            <label class="form-label mb-1">Date</label>
            <input type="date" name="date" class="form-control form-control-sm" value="<?= $filterDate ?>">
        </div>
        <?php else: ?>
        <div class="col-auto">
            <label class="form-label mb-1">Month</label>
            <input type="month" name="month" class="form-control form-control-sm" value="<?= $filterMonth ?>">
        </div>
        <?php endif; ?>
        <?php if ($reportType === 'farmer' && $role !== 'farmer'): ?>
        <div class="col-auto">
            <label class="form-label mb-1">Farmer</label>
            <select name="farmer_id" class="form-control form-control-sm">
                <option value="">Select Farmer</option>
                <?php foreach ($farmers as $f): ?>
                <option value="<?= $f['id'] ?>" <?= $filterFarmer == $f['id'] ? 'selected' : '' ?>><?= $f['code'] ?> — <?= htmlspecialchars($f['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-auto">
            <button class="btn btn-teal btn-sm"><i class="bi bi-funnel"></i> Generate</button>
        </div>
    </form>
</div>

<!-- ======= DAILY REPORT ======= -->
<?php if ($reportType === 'daily'): ?>
<div id="reportPrint">
    <div class="text-center mb-3 d-none d-print-block">
        <h4>🐄 Shree Tri Shakti Dairy — Daily Report</h4>
        <p>Date: <?= date('d F Y', strtotime($filterDate)) ?></p>
    </div>

    <?php if ($dailySummary && $dailySummary->num_rows > 0): ?>
    <div class="row g-3 mb-3">
    <?php $totL=0; $totA=0; while ($s = $dailySummary->fetch_assoc()): $totL+=$s['total_litre']; $totA+=$s['total_amount']; ?>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon <?= $s['shift'] === 'morning' ? 'amber' : 'teal' ?>">
                    <?= $s['shift'] === 'morning' ? '🌅' : '🌙' ?>
                </div>
                <div class="stat-value"><?= number_format($s['total_litre'], 1) ?><small style="font-size:1rem">L</small></div>
                <div class="stat-label"><?= ucfirst($s['shift']) ?> — <?= $s['entries'] ?> entries, Avg Fat: <?= number_format($s['avg_fat'], 2) ?>%</div>
                <div class="mt-1 fw-bold" style="color:var(--teal-700)">रू<?= number_format($s['total_amount'], 2) ?></div>
            </div>
        </div>
    <?php endwhile; ?>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-droplet-fill"></i></div>
                <div class="stat-value"><?= number_format($totL, 1) ?><small style="font-size:1rem">L</small></div>
                <div class="stat-label">Total Day Collection</div>
                <div class="mt-1 fw-bold" style="color:var(--teal-700)">रू<?= number_format($totA, 2) ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="data-card">
        <div class="data-card-header">
            <h6 class="data-card-title">Daily Entries — <?= date('d F Y', strtotime($filterDate)) ?></h6>
        </div>
        <div class="table-responsive">
            <table class="dairy-table">
                <thead>
                    <tr><th>#</th><th>Code</th><th>Farmer</th><th>Shift</th><th>Litres</th><th>Fat%</th><th>SNF%</th><th>Rate</th><th>Amount</th></tr>
                </thead>
                <tbody>
                <?php $i=1; $grandTotal=0; while ($row = $dailyData->fetch_assoc()): $grandTotal += $row['total_amount']; ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= $row['code'] ?></strong></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><span class="shift-<?= $row['shift'] ?>"><?= ucfirst($row['shift']) ?></span></td>
                        <td><strong><?= number_format($row['litre'], 2) ?></strong></td>
                        <td><?= number_format($row['fat'], 2) ?>%</td>
                        <td><?= number_format($row['snf'], 2) ?>%</td>
                        <td>रू<?= number_format($row['rate_per_liter'], 2) ?></td>
                        <td><strong style="color:var(--teal-700)">रू<?= number_format($row['total_amount'], 2) ?></strong></td>
                    </tr>
                <?php endwhile; ?>
                    <tr style="background:var(--teal-100);font-weight:700;">
                        <td colspan="8" class="text-end">Grand Total</td>
                        <td style="color:var(--teal-700)">रू<?= number_format($grandTotal, 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======= MONTHLY REPORT ======= -->
<?php elseif ($reportType === 'monthly'): ?>
<div id="reportPrint">
    <?php if ($monthlySummary): $ms = $monthlySummary->fetch_assoc(); ?>
    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="stat-card"><div class="stat-icon teal"><i class="bi bi-people-fill"></i></div><div class="stat-value"><?= $ms['farmers'] ?></div><div class="stat-label">Farmers Active</div></div></div>
        <div class="col-md-4"><div class="stat-card"><div class="stat-icon amber"><i class="bi bi-droplet-fill"></i></div><div class="stat-value"><?= number_format($ms['total_litre'], 1) ?><small style="font-size:1rem">L</small></div><div class="stat-label">Total Litres</div></div></div>
        <div class="col-md-4"><div class="stat-card"><div class="stat-icon green"><i class="bi bi-currency-rupee"></i></div><div class="stat-value">रू<?= number_format($ms['total_amount'], 0) ?></div><div class="stat-label">Total Revenue</div></div></div>
    </div>
    <?php endif; ?>

    <div class="data-card">
        <div class="data-card-header">
            <h6 class="data-card-title">Monthly Summary — <?= date('F Y', strtotime($filterMonth . '-01')) ?></h6>
        </div>
        <div class="table-responsive">
            <table class="dairy-table">
                <thead>
                    <tr><th>#</th><th>Code</th><th>Farmer</th><th>Total Litres</th><th>Avg Fat%</th><th>Avg SNF%</th><th>Milk Amount</th><th>Dana Deduct</th><th>Net Payable</th></tr>
                </thead>
                <tbody>
                <?php $i=1; while ($row = $monthlyData->fetch_assoc()): $net = $row['total_milk_amt'] - $row['total_dana']; ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= $row['code'] ?></strong></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><strong><?= number_format($row['total_litre'], 2) ?>L</strong></td>
                        <td><?= number_format($row['avg_fat'], 2) ?>%</td>
                        <td><?= number_format($row['avg_snf'], 2) ?>%</td>
                        <td style="color:var(--teal-700)">रू<?= number_format($row['total_milk_amt'], 2) ?></td>
                        <td style="color:var(--danger)">रू<?= number_format($row['total_dana'], 2) ?></td>
                        <td><strong style="color:var(--teal-900)">रू<?= number_format($net, 2) ?></strong></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======= FARMER REPORT ======= -->
<?php elseif ($reportType === 'farmer' && $farmerInfo): ?>
<div id="reportPrint">
    <!-- Farmer Profile Card -->
    <div class="farmer-lookup-card mb-3">
        <?php if ($farmerInfo['photo']): ?>
        <img src="/dairy/<?= $farmerInfo['photo'] ?>" class="farmer-photo" alt="<?= htmlspecialchars($farmerInfo['name']) ?>">
        <?php else: ?>
        <div class="farmer-photo-placeholder">👨‍🌾</div>
        <?php endif; ?>
        <div class="farmer-info">
            <h5><?= htmlspecialchars($farmerInfo['name']) ?></h5>
            <span class="farmer-code"><?= $farmerInfo['code'] ?></span>
            <p>📞 <?= $farmerInfo['phone'] ?? 'N/A' ?> &nbsp;|&nbsp; 📍 <?= htmlspecialchars($farmerInfo['address'] ?? 'N/A') ?></p>
            <p>Report for: <strong><?= date('F Y', strtotime($filterMonth . '-01')) ?></strong></p>
        </div>
    </div>

    <?php if ($farmerTotals): $dana = $danaTotals['td'] ?? 0; $net = $farmerTotals['ta'] - $dana; ?>
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon teal"><i class="bi bi-droplet-fill"></i></div><div class="stat-value"><?= number_format($farmerTotals['tl'], 1) ?>L</div><div class="stat-label">Total Litres</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon green"><i class="bi bi-currency-rupee"></i></div><div class="stat-value">रू<?= number_format($farmerTotals['ta'], 0) ?></div><div class="stat-label">Milk Earnings</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon red"><i class="bi bi-basket3-fill"></i></div><div class="stat-value">रू<?= number_format($dana, 0) ?></div><div class="stat-label">Dana Deduction</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon amber"><i class="bi bi-wallet2"></i></div><div class="stat-value">रू<?= number_format($net, 0) ?></div><div class="stat-label">Net Payable</div></div></div>
    </div>
    <?php endif; ?>

    <div class="data-card mb-3">
        <div class="data-card-header"><h6 class="data-card-title">Milk Collection Details</h6></div>
        <div class="table-responsive">
            <table class="dairy-table">
                <thead><tr><th>Date</th><th>Shift</th><th>Litres</th><th>Fat%</th><th>SNF%</th><th>Rate</th><th>Amount</th></tr></thead>
                <tbody>
                <?php $runTotal=0; while ($row = $farmerData->fetch_assoc()): $runTotal += $row['total_amount']; ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($row['collection_date'])) ?></td>
                        <td><span class="shift-<?= $row['shift'] ?>"><?= ucfirst($row['shift']) ?></span></td>
                        <td><strong><?= number_format($row['litre'], 2) ?></strong></td>
                        <td><?= number_format($row['fat'], 2) ?>%</td>
                        <td><?= number_format($row['snf'], 2) ?>%</td>
                        <td>रू<?= number_format($row['rate_per_liter'], 2) ?></td>
                        <td style="color:var(--teal-700)">रू<?= number_format($row['total_amount'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
                    <tr style="background:var(--teal-100);font-weight:700;"><td colspan="6" class="text-end">Milk Total</td><td style="color:var(--teal-700)">रू<?= number_format($runTotal, 2) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($farmerDana && $farmerDana->num_rows > 0): ?>
    <div class="data-card">
        <div class="data-card-header"><h6 class="data-card-title"><i class="bi bi-basket3-fill"></i> Dana Records</h6></div>
        <div class="table-responsive">
            <table class="dairy-table">
                <thead><tr><th>Date</th><th>Bags</th><th>Rate/Bag</th><th>Amount</th><th>Status</th><th>Notes</th></tr></thead>
                <tbody>
                <?php $danaRun=0; while ($d = $farmerDana->fetch_assoc()): $danaRun += $d['total_amount']; ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($d['dana_date'])) ?></td>
                        <td><?= number_format($d['bags'], 0) ?> bag(s)</td>
                        <td>रू<?= number_format($d['rate_per_bag'], 2) ?></td>
                        <td style="color:var(--danger)">रू<?= number_format($d['total_amount'], 2) ?></td>
                        <td><?= $d['is_paid'] ? '<span class="badge-paid">✅ Paid</span>' : '<span class="badge-unpaid">❌ Not Paid</span>' ?></td>
                        <td><?= htmlspecialchars($d['notes'] ?? '—') ?></td>
                    </tr>
                <?php endwhile; ?>
                    <tr style="background:#fee2e2;font-weight:700;"><td colspan="4" class="text-end">Dana Total</td><td style="color:var(--danger)">रू<?= number_format($danaRun, 2) ?></td><td></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

</div><!-- /main-content -->
<?php include '../includes/footer.php'; ?>
