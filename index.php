<?php
session_start();
require_once 'includes/config.php';

if (isLoggedIn()) {
    redirect('/dairy/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    // Farmer login
    $stmt = $conn->prepare(
        "SELECT * FROM farmers WHERE code = ? AND is_active = 1"
    );
    $stmt->bind_param('s', $login);
    $stmt->execute();

    $farmer = $stmt->get_result()->fetch_assoc();

    if ($farmer && password_verify($password, $farmer['password'])) {

        $_SESSION['user_id'] = $farmer['id'];
        $_SESSION['name'] = $farmer['name'];
        $_SESSION['role'] = 'farmer';
        $_SESSION['code'] = $farmer['code'];

        redirect('/dairy/dashboard.php');
    }

    // Admin / Staff login
    $stmt = $conn->prepare(
        "SELECT * FROM users WHERE username = ? AND is_active = 1"
    );
    $stmt->bind_param('s', $login);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        redirect('/dairy/dashboard.php');
    }

    $error = 'Invalid username/code or password.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Shree Tri Shakti Dairy</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="/dairy/assets/css/style.css">
</head>

<body class="login-page">

<div class="login-card">

    <div class="login-logo">🐄</div>

    <h1 class="login-title">
        Shree Tri Shakti Dairy
    </h1>

    <p class="login-sub">
        Milk Collection Management System
    </p>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="mb-3">

            <label class="form-label">
                Username / Farmer Code
            </label>

            <input
                type="text"
                name="login"
                class="form-control"
                placeholder="Enter username or farmer code"
                required
            >

        </div>

        <div class="mb-4">

            <label class="form-label">
                Password
            </label>

            <input
                type="password"
                name="password"
                class="form-control"
                placeholder="Enter password"
                required
            >

        </div>

        <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right"></i>
            Sign In
        </button>

    </form>

</div>

</body>
</html>