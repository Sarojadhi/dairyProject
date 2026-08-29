<?php
session_start();
require_once __DIR__ . '/includes/config.php';
requireLogin();

$pageTitle = 'Dashboard';
$role = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

// Initialize variables
$farmers = 0;
$milk = ['litres' => 0, 'earning' => 0, 'revenue' => 0];
$recent = [];
$errorMsg = '';

try {
    if ($role === 'farmer') {
        // Farmer view: get their total milk and earnings
        $stmt = $conn->prepare("
            SELECT 
                SUM(litre) AS litres,
                SUM(total_amount) AS earning
            FROM milk_collection
            WHERE farmer_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $milk = $result->fetch_assoc() ?: ['litres' => 0, 'earning' => 0];
            $stmt->close();
        }

        // Recent records for this farmer
        $stmt = $conn->prepare("
            SELECT *
            FROM milk_collection
            WHERE farmer_id = ?
            ORDER BY collection_date DESC
            LIMIT 10
        ");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $recent = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } else {
        // Admin/Staff view: get total farmers, total milk, revenue
        $result = $conn->query("
            SELECT COUNT(*) AS total
            FROM farmers
            WHERE is_active = 1
        ");
        $farmers = $result ? (int)$result->fetch_assoc()['total'] : 0;

        $result = $conn->query("
            SELECT 
                SUM(litre) AS litres,
                SUM(total_amount) AS revenue
            FROM milk_collection
        ");
        $milk = $result ? $result->fetch_assoc() : ['litres' => 0, 'revenue' => 0];
        $milk['litres'] = (float)($milk['litres'] ?? 0);
        $milk['revenue'] = (float)($milk['revenue'] ?? 0);

        // Recent records across all farmers
        $result = $conn->query("
            SELECT mc.*, f.name, f.code
            FROM milk_collection mc
            JOIN farmers f ON f.id = mc.farmer_id
            ORDER BY mc.collection_date DESC
            LIMIT 10
        ");
        $recent = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

} catch (Exception $e) {
    // Log error and show generic message
    error_log("Dashboard error: " . $e->getMessage());
    $errorMsg = "Unable to load dashboard data. Please try again later.";
}
?>

<?php include 'includes/header.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1 class="page-title">Dashboard</h1>
        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>
    </div>

    <!-- Statistics -->
    <div class="row g-3 mb-4">

        <?php if ($role === 'farmer'): ?>

            <div class="col-md-6">
                <div class="stat-card">
                    <div class="stat-value">
                        <?= number_format($milk['litres'] ?? 0, 2) ?> L
                    </div>
                    <div class="stat-label">Total Milk</div>
                </div>
            </div>

            <div class="col-md-6">
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
                    <div class="stat-value"><?= (int)$farmers ?></div>
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

                <?php if (!empty($recent)): ?>
                    <?php foreach ($recent as $row): ?>

                        <tr>

                            <?php if ($role !== 'farmer'): ?>
                                <td>
                                    <?= htmlspecialchars($row['name'] ?? '') ?><br>
                                    <small><?= htmlspecialchars($row['code'] ?? '') ?></small>
                                </td>
                            <?php endif; ?>

                            <td>
                                <?= date('d M Y', strtotime($row['collection_date'] ?? '')) ?>
                            </td>

                            <td>
                                <?= ucfirst(htmlspecialchars($row['shift'] ?? '')) ?>
                            </td>

                            <td>
                                <?= number_format((float)($row['litre'] ?? 0), 2) ?> L
                            </td>

                            <td>
                                <?= number_format((float)($row['fat'] ?? 0), 2) ?>%
                            </td>

                            <td>
                                <?= number_format((float)($row['snf'] ?? 0), 2) ?>%
                            </td>

                            <td>
                                रू<?= number_format((float)($row['rate_per_liter'] ?? 0), 2) ?>
                            </td>

                            <td>
                                रू<?= number_format((float)($row['total_amount'] ?? 0), 2) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $role === 'farmer' ? 7 : 8 ?>" class="text-center text-muted py-4">
                            No milk collection records found.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>

        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>