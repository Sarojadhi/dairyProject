<?php
session_start();
require_once 'includes/config.php';
requireLogin();

$pageTitle = 'Dashboard';
$role = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Stats
$today = date('Y-m-d');
$thisMonth = date('Y-m');

if ($role === 'farmer') {
    $fid = $userId;
    $todayLitres = $conn->query("SELECT COALESCE(SUM(litre),0) as t FROM milk_collection WHERE farmer_id=$fid AND collection_date='$today'")->fetch_assoc()['t'];
    $monthLitres = $conn->query("SELECT COALESCE(SUM(litre),0) as t FROM milk_collection WHERE farmer_id=$fid AND DATE_FORMAT(collection_date,'%Y-%m')='$thisMonth'")->fetch_assoc()['t'];
    $monthEarning = $conn->query("SELECT COALESCE(SUM(total_amount),0) as t FROM milk_collection WHERE farmer_id=$fid AND DATE_FORMAT(collection_date,'%Y-%m')='$thisMonth'")->fetch_assoc()['t'];
    $monthDana = $conn->query("SELECT COALESCE(SUM(total_amount),0) as t FROM dana_records WHERE farmer_id=$fid AND DATE_FORMAT(dana_date,'%Y-%m')='$thisMonth'")->fetch_assoc()['t'];
    $recent = $conn->query("SELECT mc.*, f.name FROM milk_collection mc JOIN farmers f ON f.id=mc.farmer_id WHERE mc.farmer_id=$fid ORDER BY mc.collection_date DESC, mc.shift DESC LIMIT 10");
    // Chart data last 7 days
    $chartData = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $res = $conn->query("SELECT COALESCE(SUM(litre),0) as l FROM milk_collection WHERE farmer_id=$fid AND collection_date='$d'")->fetch_assoc();
        $chartData[] = ['date' => date('d M', strtotime($d)), 'litres' => (float)$res['l']];
    }
} else {
    $totalFarmers = $conn->query("SELECT COUNT(*) as c FROM farmers WHERE is_active=1")->fetch_assoc()['c'];
    $todayLitres = $conn->query("SELECT COALESCE(SUM(litre),0) as t FROM milk_collection WHERE collection_date='$today'")->fetch_assoc()['t'];
    $monthLitres = $conn->query("SELECT COALESCE(SUM(litre),0) as t FROM milk_collection WHERE DATE_FORMAT(collection_date,'%Y-%m')='$thisMonth'")->fetch_assoc()['t'];
    $monthRevenue = $conn->query("SELECT COALESCE(SUM(total_amount),0) as t FROM milk_collection WHERE DATE_FORMAT(collection_date,'%Y-%m')='$thisMonth'")->fetch_assoc()['t'];
    $todayEntries = $conn->query("SELECT COUNT(*) as c FROM milk_collection WHERE collection_date='$today'")->fetch_assoc()['c'];
    $pendingPayments = $conn->query("SELECT COUNT(*) as c FROM payments WHERE is_paid=0")->fetch_assoc()['c'];
    $monthDanaTotal = $conn->query("SELECT COALESCE(SUM(total_amount),0) as t FROM dana_records WHERE DATE_FORMAT(dana_date,'%Y-%m')='$thisMonth'")->fetch_assoc()['t'];
    $recent = $conn->query("SELECT mc.*, f.name, f.code FROM milk_collection mc JOIN farmers f ON f.id=mc.farmer_id ORDER BY mc.created_at DESC LIMIT 8");
    // Chart: last 7 days
    $chartData = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $res = $conn->query("SELECT COALESCE(SUM(litre),0) as l FROM milk_collection WHERE collection_date='$d'")->fetch_assoc();
        $chartData[] = ['date' => date('d M', strtotime($d)), 'litres' => (float)$res['l']];
    }
    // Top farmers this month
    $topFarmers = $conn->query("SELECT f.name, f.code, SUM(mc.litre) as total_l, SUM(mc.total_amount) as total_a FROM milk_collection mc JOIN farmers f ON f.id=mc.farmer_id WHERE DATE_FORMAT(mc.collection_date,'%Y-%m')='$thisMonth' GROUP BY mc.farmer_id ORDER BY total_l DESC LIMIT 5");
}
?>
<?php include 'includes/header.php'; ?>
<div class="main-content">

<!-- Welcome Banner -->
<div class="welcome-banner">
    <div class="welcome-title">
        <?php
        $hour = (int)date('H');
        $greet = $hour < 12 ? '🌅 Good Morning' : ($hour < 17 ? '☀️ Good Afternoon' : '🌙 Good Evening');
        echo "$greet, " . htmlspecialchars($_SESSION['name']) . "!";
        ?>
    </div>
    <p class="welcome-sub">
        <?= date('l, d F Y') ?> &nbsp;|&nbsp;
        <?php if ($role === 'farmer'): ?>
            Today you've submitted <strong><?= number_format($todayLitres, 2) ?> L</strong> of milk.
        <?php else: ?>
            <strong><?= $todayEntries ?? 0 ?></strong> entries logged today &nbsp;|&nbsp;
            <strong><?= number_format($todayLitres, 2) ?> L</strong> collected today
        <?php endif; ?>
    </p>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
<?php if ($role === 'farmer'): ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon teal"><i class="bi bi-droplet-fill"></i></div>
            <div class="stat-value"><?= number_format($todayLitres, 1) ?><small style="font-size:1rem">L</small></div>
            <div class="stat-label">Today's Milk</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-calendar-month"></i></div>
            <div class="stat-value"><?= number_format($monthLitres, 1) ?><small style="font-size:1rem">L</small></div>
            <div class="stat-label">This Month</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-currency-rupee"></i></div>
            <div class="stat-value">रू<?= number_format($monthEarning, 0) ?></div>
            <div class="stat-label">Month Earnings</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-basket3-fill"></i></div>
            <div class="stat-value">रू<?= number_format($monthDana, 0) ?></div>
            <div class="stat-label">Dana Deduction</div>
        </div>
    </div>
<?php else: ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon teal"><i class="bi bi-people-fill"></i></div>
            <div class="stat-value"><?= $totalFarmers ?></div>
            <div class="stat-label">Active Farmers</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-droplet-fill"></i></div>
            <div class="stat-value"><?= number_format($todayLitres, 1) ?><small style="font-size:1rem">L</small></div>
            <div class="stat-label">Today's Collection</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-currency-rupee"></i></div>
            <div class="stat-value">रू<?= number_format($monthRevenue, 0) ?></div>
            <div class="stat-label">Month Revenue</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-value"><?= $pendingPayments ?></div>
            <div class="stat-label">Pending Payments</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-basket3-fill"></i></div>
            <div class="stat-value">रू<?= number_format($monthDanaTotal ?? 0, 0) ?></div>
            <div class="stat-label">Month Dana (Feed)</div>
        </div>
    </div>
<?php endif; ?>
</div>

<div class="row g-3">
    <!-- Chart -->
    <div class="col-lg-8">
        <div class="data-card">
            <div class="data-card-header">
                <h6 class="data-card-title"><i class="bi bi-bar-chart-fill"></i> 7-Day Milk Collection</h6>
            </div>
            <div class="data-card-body">
                <div class="chart-container">
                    <canvas id="milkChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions / Top Farmers -->
    <div class="col-lg-4">
        <?php if ($role !== 'farmer'): ?>
        <div class="data-card mb-3">
            <div class="data-card-header">
                <h6 class="data-card-title"><i class="bi bi-lightning-fill"></i> Quick Actions</h6>
            </div>
            <div class="data-card-body p-2">
                <div class="d-grid gap-2">
                    <a href="/dairy/pages/milk_collection.php?action=add" class="btn btn-teal">
                        <i class="bi bi-plus-circle-fill me-2"></i>Add Milk Entry
                    </a>
                    <a href="/dairy/pages/dana.php?action=add" class="btn btn-amber">
                        <i class="bi bi-basket3-fill me-2"></i>Add Dana Entry
                    </a>
                    <a href="/dairy/pages/reports.php" class="btn btn-outline-secondary">
                        <i class="bi bi-file-earmark-bar-graph me-2"></i>Generate Report
                    </a>
                    <?php if ($role === 'admin'): ?>
                    <a href="/dairy/pages/farmers.php?action=add" class="btn btn-outline-success">
                        <i class="bi bi-person-plus-fill me-2"></i>Add Farmer
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role !== 'farmer' && isset($topFarmers) && $topFarmers->num_rows > 0): ?>
        <div class="data-card">
            <div class="data-card-header">
                <h6 class="data-card-title"><i class="bi bi-trophy-fill"></i> Top Farmers (Month)</h6>
            </div>
            <div class="data-card-body p-0">
                <ul class="list-group list-group-flush">
                <?php $rank=1; while ($f = $topFarmers->fetch_assoc()): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                        <div>
                            <span class="badge bg-secondary me-2">#<?= $rank++ ?></span>
                            <strong style="font-size:0.85rem"><?= htmlspecialchars($f['name']) ?></strong>
                            <small class="text-muted ms-1"><?= $f['code'] ?></small>
                        </div>
                        <span class="text-teal fw-bold" style="color:var(--teal-700)"><?= number_format($f['total_l'], 1) ?>L</span>
                    </li>
                <?php endwhile; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Entries -->
<div class="data-card mt-3">
    <div class="data-card-header">
        <h6 class="data-card-title"><i class="bi bi-clock-history"></i> Recent Collections</h6>
        <a href="/dairy/pages/milk_collection.php" class="btn btn-sm btn-amber">View All</a>
    </div>
    <div class="data-card-body p-0">
        <div class="table-responsive">
            <table class="dairy-table">
                <thead>
                    <tr>
                        <?php if ($role !== 'farmer'): ?><th>Farmer</th><?php endif; ?>
                        <th>Date</th>
                        <th>Shift</th>
                        <th>Litres</th>
                        <th>Fat%</th>
                        <th>SNF%</th>
                        <th>Rate</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $recent->fetch_assoc()): ?>
                    <tr>
                        <?php if ($role !== 'farmer'): ?>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong><br>
                            <small class="text-muted"><?= $row['code'] ?? '' ?></small></td>
                        <?php endif; ?>
                        <td><?= date('d M Y', strtotime($row['collection_date'])) ?></td>
                        <td><span class="shift-<?= $row['shift'] ?>"><?= ucfirst($row['shift']) ?></span></td>
                        <td><strong><?= number_format($row['litre'], 2) ?></strong></td>
                        <td><?= number_format($row['fat'], 2) ?>%</td>
                        <td><?= number_format($row['snf'], 2) ?>%</td>
                        <td>रू<?= number_format($row['rate_per_liter'], 2) ?></td>
                        <td><strong style="color:var(--teal-700)">रू<?= number_format($row['total_amount'], 2) ?></strong></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div><!-- /main-content -->

<?php include 'includes/footer.php'; ?>

<script>
const ctx = document.getElementById('milkChart').getContext('2d');
const chartData = <?= json_encode($chartData) ?>;
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: chartData.map(d => d.date),
        datasets: [{
            label: 'Litres Collected',
            data: chartData.map(d => d.litres),
            backgroundColor: 'rgba(42,158,135,0.7)',
            borderColor: '#1a6b5e',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f0f0eb' }, ticks: { font: { size: 11 } } },
            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});
</script>
