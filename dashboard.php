<?php
session_start();
require_once 'includes/config.php';
requireLogin();

$pageTitle = 'Dashboard';
$role = $_SESSION['role'];
$userId = $_SESSION['user_id'];

if ($role === 'farmer') {

    $milk = $conn->query("
        SELECT 
            SUM(litre) AS litres,
            SUM(total_amount) AS earning
        FROM milk_collection
        WHERE farmer_id = $userId
    ")->fetch_assoc();

    $recent = $conn->query("
        SELECT *
        FROM milk_collection
        WHERE farmer_id = $userId
        ORDER BY collection_date DESC
        LIMIT 10
    ");

} else {

    $farmers = $conn->query("
        SELECT COUNT(*) AS total
        FROM farmers
        WHERE is_active = 1
    ")->fetch_assoc()['total'];

    $milk = $conn->query("
        SELECT 
            SUM(litre) AS litres,
            SUM(total_amount) AS revenue
        FROM milk_collection
    ")->fetch_assoc();

    $recent = $conn->query("
        SELECT mc.*, f.name, f.code
        FROM milk_collection mc
        JOIN farmers f ON f.id = mc.farmer_id
        ORDER BY mc.collection_date DESC
        LIMIT 10
    ");
}
?>

<?php include 'includes/header.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1 class="page-title">Dashboard</h1>
    </div>

    <!-- Statistics -->
    <div class="row g-3 mb-4">

        <?php if ($role === 'farmer'): ?>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-value">
                        <?= number_format($milk['litres'] ?? 0, 2) ?> L
                    </div>
                    <div class="stat-label">Total Milk</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-value">
                        रू<?= number_format($milk['earning'] ?? 0, 2) ?>
                    </div>
                    <div class="stat-label">Total Earning</div>
                </div>
            </div>

        <?php else: ?>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-value"><?= $farmers ?></div>
                    <div class="stat-label">Active Farmers</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-value">
                        <?= number_format($milk['litres'] ?? 0, 2) ?> L
                    </div>
                    <div class="stat-label">Total Milk</div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-value">
                        रू<?= number_format($milk['revenue'] ?? 0, 2) ?>
                    </div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>

        <?php endif; ?>

    </div>


    <!-- Recent Milk -->
    <div class="data-card">

        <div class="data-card-header">
            <h6 class="data-card-title">
                Recent Milk Collection
            </h6>
        </div>

        <div class="table-responsive">

            <table class="dairy-table">

                <thead>
                    <tr>

                        <?php if ($role !== 'farmer'): ?>
                            <th>Farmer</th>
                        <?php endif; ?>

                        <th>Date</th>
                        <th>Shift</th>
                        <th>Litres</th>
                        <th>Fat</th>
                        <th>SNF</th>
                        <th>Rate</th>
                        <th>Amount</th>

                    </tr>
                </thead>

                <tbody>

                <?php while ($row = $recent->fetch_assoc()): ?>

                    <tr>

                        <?php if ($role !== 'farmer'): ?>
                            <td>
                                <?= htmlspecialchars($row['name']) ?><br>
                                <small><?= $row['code'] ?></small>
                            </td>
                        <?php endif; ?>

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

                </tbody>

            </table>

        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>