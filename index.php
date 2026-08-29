<?php
// Configure session cookie before starting the session,
// otherwise PHP warns these cannot be changed once active.
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'));
ini_set('session.use_strict_mode', 1);

session_start();

require_once __DIR__ . '/includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// Initialize error message
$error = '';
$lockoutTime = 0;

// Rate limiting: track failed attempts in session
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = 0;
}

// Check if locked out (5 attempts within 15 minutes)
$attempts = (int)$_SESSION['login_attempts'];
$lastAttempt = (int)$_SESSION['last_attempt_time'];
$lockDuration = 15 * 60; // 15 minutes in seconds
if ($attempts >= 5 && (time() - $lastAttempt) < $lockDuration) {
    $remaining = $lockDuration - (time() - $lastAttempt);
    $error = 'Too many failed attempts. Please wait ' . ceil($remaining / 60) . ' minutes.';
    // Force lockout display without processing POST
} else {
    // Reset attempts if lockout expired
    if ($attempts >= 5 && (time() - $lastAttempt) >= $lockDuration) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt_time'] = 0;
    }

    // Process POST request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF Validation
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            $error = 'Security validation failed. Please try again.';
        } else {
            $login = trim($_POST['login'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($login) || empty($password)) {
                $error = 'Please enter both username/code and password.';
            } else {
                // Determine if input is a farmer code (alphanumeric) or username (could be anything)
                // We'll try farmer first, then admin.
                $authenticated = false;

                // 1. Try Farmer login (only if input seems like a code, but we can try anyway)
                $stmt = $conn->prepare("SELECT * FROM farmers WHERE code = ? AND is_active = 1");
                if ($stmt) {
                    $stmt->bind_param('s', $login);
                    $stmt->execute();
                    $farmer = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($farmer && password_verify($password, $farmer['password'])) {
                        // Password upgrade? Check if rehash needed
                        if (password_needs_rehash($farmer['password'], PASSWORD_DEFAULT)) {
                            $newHash = password_hash($password, PASSWORD_DEFAULT);
                            $updateStmt = $conn->prepare("UPDATE farmers SET password = ? WHERE id = ?");
                            if ($updateStmt) {
                                $updateStmt->bind_param('si', $newHash, $farmer['id']);
                                $updateStmt->execute();
                                $updateStmt->close();
                            }
                        }

                        // Regenerate session ID for security
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = (int)$farmer['id'];
                        $_SESSION['name'] = $farmer['name'];
                        $_SESSION['role'] = 'farmer';
                        $_SESSION['code'] = $farmer['code'];
                        // Clear login attempts
                        unset($_SESSION['login_attempts'], $_SESSION['last_attempt_time']);

                        redirect('dashboard.php');
                        exit; // Ensure exit after redirect
                    }
                }

                // 2. Try Admin/Staff login
                $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
                if ($stmt) {
                    $stmt->bind_param('s', $login);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($user && password_verify($password, $user['password'])) {
                        // Password upgrade
                        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                            $newHash = password_hash($password, PASSWORD_DEFAULT);
                            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                            if ($updateStmt) {
                                $updateStmt->bind_param('si', $newHash, $user['id']);
                                $updateStmt->execute();
                                $updateStmt->close();
                            }
                        }

                        // Regenerate session ID
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['name'] = $user['name'];
                        $_SESSION['role'] = $user['role'];
                        unset($_SESSION['login_attempts'], $_SESSION['last_attempt_time']);

                        redirect('dashboard.php');
                        exit;
                    }
                }

                // If we reach here, authentication failed
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                $_SESSION['last_attempt_time'] = time();
                $error = 'Invalid username/code or password.';
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Shree Tri Shakti Dairy</title>
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?? '' ?>assets/css/style.css">
    <style>
        /* Inline critical styles in case CSS fails */
        body.login-page {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 420px;
            width: 100%;
            text-align: center;
        }
        .login-logo {
            font-size: 64px;
            margin-bottom: 10px;
        }
        .login-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .login-sub {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 25px;
        }
        .btn-login {
            background: #1abc9c;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 24px;
            width: 100%;
            font-weight: 600;
            font-size: 16px;
            transition: background 0.2s;
        }
        .btn-login:hover {
            background: #16a085;
            color: white;
        }
        .form-label {
            font-weight: 500;
            font-size: 14px;
            color: #34495e;
        }
        .form-control:focus {
            border-color: #1abc9c;
            box-shadow: 0 0 0 0.2rem rgba(26,188,156,0.25);
        }
        .alert {
            border-radius: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body class="login-page">

<div class="login-card">
    <div class="login-logo">🐄</div>
    <h1 class="login-title">Shree Tri Shakti Dairy</h1>
    <p class="login-sub">Milk Collection Management System</p>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <!-- CSRF token -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="mb-3">
            <label class="form-label" for="loginInput">Username / Farmer Code</label>
            <input type="text" name="login" id="loginInput"
                   class="form-control" placeholder="Enter username or farmer code"
                   value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
                   required autofocus>
        </div>

        <div class="mb-4">
            <label class="form-label" for="passwordInput">Password</label>
            <input type="password" name="password" id="passwordInput"
                   class="form-control" placeholder="Enter password" required>
        </div>

        <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right"></i> Sign In
        </button>
    </form>

    <div class="mt-3 text-muted" style="font-size: 13px;">
        <i class="bi bi-info-circle"></i> For farmers: use your Farmer Code and password.
    </div>
</div>

<!-- Bootstrap JS for alert dismissal -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>