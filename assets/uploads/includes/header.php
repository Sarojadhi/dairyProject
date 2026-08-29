<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$role = $_SESSION['role'] ?? '';
$userName = $_SESSION['name'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?> — Shree Tri Shakti Dairy</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/dairy/assets/css/style.css">
</head>

<body>

<nav class="navbar dairy-navbar">
    <div class="container-fluid">

        <!-- Logo -->
        <a class="navbar-brand d-flex align-items-center gap-2"
           href="/dairy/dashboard.php">

            <span class="brand-icon">🐄</span>

            <div class="brand-text">
                <span class="brand-main">Tri Shakti Dairy</span>
                <span class="brand-sub">Milk Collection System</span>
            </div>

        </a>


        <!-- Navigation -->
        <ul class="navbar-nav d-flex flex-row gap-2">

            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?= $pageTitle === 'Dashboard' ? 'active' : '' ?>"
                   href="/dairy/dashboard.php">

                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </li>


            <!-- Admin / Staff -->
            <?php if ($role === 'admin' || $role === 'staff'): ?>

                <li class="nav-item">
                    <a class="nav-link <?= strpos($pageTitle, 'Collection') !== false ? 'active' : '' ?>"
                       href="/dairy/pages/milk_collection.php">

                        <i class="bi bi-droplet-fill"></i>
                        Milk Entry
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= strpos($pageTitle, 'Dana') !== false ? 'active' : '' ?>"
                       href="/dairy/pages/dana.php">

                        <i class="bi bi-basket3-fill"></i>
                        Dana
                    </a>
                </li>

            <?php endif; ?>


            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link <?= strpos($pageTitle, 'Report') !== false ? 'active' : '' ?>"
                   href="/dairy/pages/reports.php">

                    <i class="bi bi-bar-chart-line-fill"></i>
                    Reports
                </a>
            </li>


            <!-- Manage -->
            <?php if ($role === 'admin' || $role === 'staff'): ?>

                <li class="nav-item dropdown">

                    <a class="nav-link dropdown-toggle"
                       href="#"
                       data-bs-toggle="dropdown">

                        <i class="bi bi-gear-fill"></i>
                        Manage
                    </a>

                    <ul class="dropdown-menu">

                        <li>
                            <a class="dropdown-item"
                               href="/dairy/pages/farmers.php">

                                <i class="bi bi-people-fill"></i>
                                Farmers
                            </a>
                        </li>

                        <?php if ($role === 'admin'): ?>

                            <li>
                                <a class="dropdown-item"
                                   href="/dairy/pages/staff.php">

                                    <i class="bi bi-person-badge-fill"></i>
                                    Staff
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item"
                                   href="/dairy/pages/rates.php">

                                    <i class="bi bi-currency-rupee"></i>
                                    Milk Rates
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item"
                                   href="/dairy/pages/payments.php">

                                    <i class="bi bi-wallet2"></i>
                                    Payments
                                </a>
                            </li>

                        <?php endif; ?>

                    </ul>
                </li>

            <?php endif; ?>


            <!-- Farmer -->
            <?php if ($role === 'farmer'): ?>

                <li class="nav-item">
                    <a class="nav-link"
                       href="/dairy/pages/farmer/my_records.php">

                        <i class="bi bi-journal-text"></i>
                        My Records
                    </a>
                </li>

            <?php endif; ?>

        </ul>


        <!-- User -->
        <div class="user-area">

            <div class="user-badge">

                <span class="role-dot role-<?= htmlspecialchars($role) ?>"></span>

                <span class="user-name">
                    <?= htmlspecialchars($userName) ?>
                </span>

                <span class="badge role-badge-<?= htmlspecialchars($role) ?>">
                    <?= strtoupper(htmlspecialchars($role)) ?>
                </span>

            </div>

            <a href="/dairy/logout.php"
               class="btn btn-sm btn-logout">

                <i class="bi bi-box-arrow-right"></i>
                Logout

            </a>

        </div>

    </div>
</nav>


<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>