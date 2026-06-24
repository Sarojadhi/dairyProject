<?php
session_start();
require_once 'includes/config.php';

if (isLoggedIn()) redirect('/dairy/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = sanitize($conn, $_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    // Auto-detect role: if login starts with F followed by digits, treat as farmer
    if (preg_match('/^F\d+$/i', $login)) {
        $stmt = $conn->prepare("SELECT * FROM farmers WHERE code = ? AND is_active = 1");
        $stmt->bind_param('s', $login);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = 'farmer';
            $_SESSION['code'] = $user['code'];
            redirect('/dairy/dashboard.php');
        } else {
            $error = 'Invalid farmer code or password.';
        }
    } else {
        // Try admin first, then staff
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->bind_param('s', $login);
        $stmt->execute();
        $result = $stmt->get_result();
        $found = false;
        while ($u = $result->fetch_assoc()) {
            if (password_verify($password, $u['password'])) {
                $_SESSION['user_id'] = $u['id'];
                $_SESSION['name'] = $u['name'];
                $_SESSION['role'] = $u['role'];
                $found = true;
                redirect('/dairy/dashboard.php');
                break;
            }
        }
        if (!$found) $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Shree Tri Shakti Dairy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/dairy/assets/css/style.css">
</head>
<body class="login-page">

<div class="login-card">
    <div class="login-logo">🐄</div>
    <h1 class="login-title">Shree Tri Shakti Dairy</h1>
    <p class="login-sub">Milk Collection Management System</p>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="mt-3">
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-person"></i> Username / Farmer Code</label>
            <input type="text" name="login" class="form-control" id="loginField"
                   placeholder="Enter username" required
                   value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
        </div>
        <div class="mb-4">
            <label class="form-label"><i class="bi bi-lock"></i> Password</label>
            <div class="input-group">
                <input type="password" name="password" class="form-control" id="passwordField"
                       placeholder="Enter password" required>
                <button class="btn btn-outline-secondary" type="button" onclick="togglePass()">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePass() {
    const f = document.getElementById('passwordField');
    const i = document.getElementById('eyeIcon');
    f.type = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
</body>
</html>
