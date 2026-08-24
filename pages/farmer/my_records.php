<?php
session_start();
require_once '../../includes/config.php';
requireLogin();
requireRole('farmer');

$pageTitle = 'My Records';
$fid = $_SESSION['user_id'];
$filterMonth = sanitize($conn, $_GET['month'] ?? date('Y-m'));

// Farmer profile
$farmerInfo = $conn->query("SELECT * FROM farmers WHERE id=$fid")->fetch_assoc();

// Monthly milk records
$milkRecords = $conn->query("
    SELECT * FROM milk_collection
    WHERE farmer_id=$fid AND DATE_FORMAT(collection_date,'%Y-%m')='$filterMonth'
    ORDER BY collection_date DESC, shift DESC
");

// Monthly dana records
$danaRecords = $conn->query("
    SELECT * FROM dana_records
    WHERE farmer_id=$fid AND DATE_FORMAT(dana_date,'%Y-%m')='$filterMonth'
    ORDER BY dana_date DESC
");

// Totals
$milkTotals = $conn->query("
    SELECT
        SUM(litre) as total_litre,
        AVG(fat) as avg_fat,
        AVG(snf) as avg_snf,
        SUM(total_amount) as total_amount,
        COUNT(*) as entries
    FROM milk_collection
    WHERE farmer_id=$fid AND DATE_FORMAT(collection_date,'%Y-%m')='$filterMonth'
")->fetch_assoc();

$danaTotals = $conn->query("
    SELECT COALESCE(SUM(total_amount),0) as total_dana
    FROM dana_records
    WHERE farmer_id=$fid AND DATE_FORMAT(dana_date,'%Y-%m')='$filterMonth'
")->fetch_assoc();

$netPayable = ($milkTotals['total_amount'] ?? 0) - ($danaTotals['total_dana'] ?? 0);

// Payment status
$paymentStatus = $conn->query("
    SELECT * FROM payments
    WHERE farmer_id=$fid AND payment_month='$filterMonth'
")->fetch_assoc();

// Chart: daily litres this month
$chartDays = [];
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)substr($filterMonth,5,2), (int)substr($filterMonth,0,4));
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr = $filterMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
    $res = $conn->query("SELECT COALESCE(SUM(litre),0) as l FROM milk_collection WHERE farmer_id=$fid AND collection_date='$dateStr'")->fetch_assoc();
    $chartDays[] = ['day' => $d, 'litres' => (float)$res['l']];
}
?>
<?php include '../../includes/header.php'; ?>
<div class="main-content">

<div class="page-header">
    <h1 class="page-title">
        <div class="page-title-icon"><i class="bi bi-journal-text"></i></div>
        My Records
    </h1>
    <button class="btn btn-amber no-print" onclick="window.print()">
        <i class="bi bi-printer me-2"></i>Print
    </button>
</div>

<!-- Farmer Profile -->
<div class="farmer-lookup-card mb-3">
    <?php if ($farmerInfo['photo']): ?>
    <img src="/dairy/<?= $farmerInfo['photo'] ?>" class="farmer-photo" alt="My Photo">
    <?php else: ?>
    <div class="farmer-photo-placeholder">👨‍🌾</div>
    <?php endif; ?>
    <div class="farmer-info">
        <h5><?= htmlspecialchars($farmerInfo['name']) ?></h5>
        <span class="farmer-code"><?= $farmerInfo['code'] ?></span>
        <p>📞 <?= $farmerInfo['phone'] ?? 'N/A' ?> &nbsp;|&nbsp; 📍 <?= htmlspecialchars($farmerInfo['address'] ?? 'N/A') ?></p>
        <p>Member since: <strong><?= date('d F Y', strtotime($farmerInfo['created_at'])) ?></strong></p>
    </div>
</div>

<!-- Month Selector -->
<div class="form-card mb-3 py-2">
    <form class="d-flex align-items-center gap-3 flex-wrap" method="GET">
        <label class="form-label mb-0 fw-bold">Select Month:</label>
        <input type="month" name="month" class="form-control" style="max-width:200px" value="<?= $filterMonth ?>">
        <button class="btn btn-teal btn-sm"><i class="bi bi-funnel me-1"></i>View</button>
    </form>
</div>

<!-- Summary Stats -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon teal"><i class="bi bi-droplet-fill"></i></div>
            <div class="stat-value"><?= number_format($milkTotals['total_litre'] ?? 0, 1) ?><small style="font-size:1rem">L</small></div>
            <div class="stat-label">Total Litres (<?= $milkTotals['entries'] ?? 0 ?> entries)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-activity"></i></div>
            <div class="stat-value"><?= number_format($milkTotals['avg_fat'] ?? 0, 2) ?><small style="font-size:1rem">%</small></div>
            <div class="stat-label">Avg Fat% &nbsp;|&nbsp; SNF: <?= number_format($milkTotals['avg_snf'] ?? 0, 2) ?>%</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-currency-rupee"></i></div>
            <div class="stat-value">रू<?= number_format($milkTotals['total_amount'] ?? 0, 0) ?></div>
            <div class="stat-label">Milk Earnings</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon <?= $netPayable >= 0 ? 'green' : 'red' ?>">
                <i class="bi bi-wallet2"></i>
            </div>
            <div class="stat-value">रू<?= number_format(abs($netPayable), 0) ?></div>
            <div class="stat-label">Net Payable
                <?php if ($paymentStatus): ?>
                &nbsp;<span class="<?= $paymentStatus['is_paid'] ? 'badge-paid' : 'badge-unpaid' ?>"><?= $paymentStatus['is_paid'] ? '✅ Paid' : '⏳ Pending' ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Chart -->
    <div class="col-lg-8">
        <div class="data-card">
            <div class="data-card-header">
                <h6 class="data-card-title"><i class="bi bi-bar-chart-fill"></i> Daily Milk — <?= date('F Y', strtotime($filterMonth . '-01')) ?></h6>
            </div>
            <div class="data-card-body">
                <div class="chart-container">
                    <canvas id="milkChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Box -->
    <div class="col-lg-4">
        <div class="data-card h-100">
            <div class="data-card-header">
                <h6 class="data-card-title"><i class="bi bi-receipt"></i> Payment Summary</h6>
            </div>
            <div class="data-card-body">
                <table class="w-100" style="font-size:0.9rem">
                    <tr class="border-bottom pb-2">
                        <td class="py-2">🥛 Milk Earnings</td>
                        <td class="text-end fw-bold" style="color:var(--teal-700)">रू<?= number_format($milkTotals['total_amount'] ?? 0, 2) ?></td>
                    </tr>
                    <tr class="border-bottom">
                        <td class="py-2">🌾 Dana Deduction</td>
                        <td class="text-end fw-bold" style="color:var(--danger)">- रू<?= number_format($danaTotals['total_dana'] ?? 0, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="py-2 fw-bold">💰 Net Payable</td>
                        <td class="text-end fw-bold" style="font-size:1.2rem;color:var(--teal-900)">रू<?= number_format($netPayable, 2) ?></td>
                    </tr>
                </table>

                <?php if ($paymentStatus): ?>
                <div class="mt-3 p-3 rounded text-center <?= $paymentStatus['is_paid'] ? 'alert-success' : 'alert-warning' ?> alert">
                    <?php if ($paymentStatus['is_paid']): ?>
                    <strong>✅ Payment Received</strong><br>
                    <small><?= date('d F Y', strtotime($paymentStatus['paid_at'])) ?></small>
                    <?php else: ?>
                    <strong>⏳ Payment Pending</strong><br>
                    <small>Contact admin for settlement</small>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-info mt-3 text-center py-2">
                    <small>No payment record yet for this month.</small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Milk Records Table -->
<div class="data-card mt-3" id="myMilkTable">
    <div class="data-card-header">
        <h6 class="data-card-title"><i class="bi bi-droplet-fill"></i> My Milk Collection</h6>
    </div>
    <div class="table-responsive">
        <table class="dairy-table">
            <thead>
                <tr><th>#</th><th>Date</th><th>Shift</th><th>Litres</th><th>Fat%</th><th>SNF%</th><th>Rate/L</th><th>Amount</th></tr>
            </thead>
            <tbody>
            <?php $i=1; $totL=0; $totA=0;
            while ($row = $milkRecords->fetch_assoc()):
                $totL += $row['litre']; $totA += $row['total_amount'];
            ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= date('d M Y', strtotime($row['collection_date'])) ?></td>
                    <td><span class="shift-<?= $row['shift'] ?>"><?= ucfirst($row['shift']) ?></span></td>
                    <td><strong><?= number_format($row['litre'], 2) ?>L</strong></td>
                    <td><?= number_format($row['fat'], 2) ?>%</td>
                    <td><?= number_format($row['snf'], 2) ?>%</td>
                    <td>रू<?= number_format($row['rate_per_liter'], 2) ?></td>
                    <td style="color:var(--teal-700)"><strong>रू<?= number_format($row['total_amount'], 2) ?></strong></td>
                </tr>
            <?php endwhile; ?>
                <tr style="background:var(--teal-100);font-weight:700;">
                    <td colspan="3" class="text-end">Total</td>
                    <td><?= number_format($totL, 2) ?>L</td>
                    <td colspan="2"></td>
                    <td></td>
                    <td style="color:var(--teal-700)">रू<?= number_format($totA, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Dana Table -->
<?php if ($danaRecords->num_rows > 0): ?>
<div class="data-card mt-3">
    <div class="data-card-header">
        <h6 class="data-card-title"><i class="bi bi-basket3-fill"></i> My Dana (Feed) Records</h6>
    </div>
    <div class="table-responsive">
        <table class="dairy-table">
            <thead>
                <tr><th>#</th><th>Date</th><th>Bags</th><th>Rate/Bag</th><th>Amount</th><th>Status</th><th>Notes</th></tr>
            </thead>
            <tbody>
            <?php $i=1; while ($d = $danaRecords->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= date('d M Y', strtotime($d['dana_date'])) ?></td>
                    <td><?= number_format($d['bags'], 0) ?> bag(s)</td>
                    <td>रू<?= number_format($d['rate_per_bag'], 2) ?></td>
                    <td style="color:var(--danger)"><strong>रू<?= number_format($d['total_amount'], 2) ?></strong></td>
                    <td><?= $d['is_paid'] ? '<span class="badge-paid">✅ Paid</span>' : '<span class="badge-unpaid">❌ Not Paid</span>' ?></td>
                    <td><?= htmlspecialchars($d['notes'] ?? '—') ?></td>
                </tr>
            <?php endwhile; ?>
                <tr style="background:#fee2e2;font-weight:700;">
                    <td colspan="4" class="text-end">Dana Total (Deduction)</td>
                    <td style="color:var(--danger)">रू<?= number_format($danaTotals['total_dana'] ?? 0, 2) ?></td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div><!-- /main-content -->
<?php include '../../includes/footer.php'; ?>
<script>
const ctx = document.getElementById('milkChart').getContext('2d');
const chartData = <?= json_encode($chartDays) ?>;
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: chartData.map(d => 'Day ' + d.day),
        datasets: [{
            label: 'Litres',
            data: chartData.map(d => d.litres),
            backgroundColor: chartData.map(d => d.litres > 0 ? 'rgba(42,158,135,0.7)' : 'rgba(200,200,200,0.3)'),
            borderColor: '#1a6b5e',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f0f0eb' }, ticks: { font: { size: 10 } } },
            x: { grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 0 } }
        }
    }
});
</script>
