<?php
session_start();
require_once '../includes/config.php';
requireLogin();
requireRole('admin');

$pageTitle = 'Manage Staff';
$action = $_GET['action'] ?? 'list';
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $name = sanitize($conn, $_POST['name']);
    $username = sanitize($conn, $_POST['username']);
    $phone = sanitize($conn, $_POST['phone']);
    $email = sanitize($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = sanitize($conn, $_POST['role']);

    $chk = $conn->query("SELECT id FROM users WHERE username='$username'");
    if ($chk->num_rows > 0) {
        $err = "Username '$username' already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, username, password, role, phone, email) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('ssssss', $name, $username, $password, $role, $phone, $email);
        if ($stmt->execute()) { $msg = "✅ Staff '$name' added."; $action = 'list'; }
        else $err = "Error: " . $conn->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_staff'])) {
    $id = (int)$_POST['id'];
    $name = sanitize($conn, $_POST['name']);
    $phone = sanitize($conn, $_POST['phone']);
    $email = sanitize($conn, $_POST['email']);
    $is_active = (int)$_POST['is_active'];
    $passUpdate = '';
    if (!empty($_POST['password'])) {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $passUpdate = ", password='$hash'";
    }
    $conn->query("UPDATE users SET name='$name', phone='$phone', email='$email', is_active=$is_active $passUpdate WHERE id=$id");
    $msg = "✅ Staff updated.";
    $action = 'list';
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id !== $_SESSION['user_id']) {
        $conn->query("UPDATE users SET is_active=0 WHERE id=$id");
        $msg = "Staff deactivated.";
    }
}

$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $editRow = $conn->query("SELECT * FROM users WHERE id=$id")->fetch_assoc();
}

$staff = $conn->query("SELECT * FROM users ORDER BY role, name");
?>
<?php include '../includes/header.php'; ?>
<div class="main-content">
<div class="page-header">
    <h1 class="page-title"><div class="page-title-icon"><i class="bi bi-person-badge-fill"></i></div>Manage Staff</h1>
    <?php if ($action !== 'add'): ?>
    <a href="?action=add" class="btn btn-teal"><i class="bi bi-person-plus me-2"></i>Add Staff</a>
    <?php endif; ?>
</div>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger alert-dismissible fade show"><?= $err ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="form-card">
    <div class="form-section-title"><?= $action === 'add' ? '➕ Add Staff Member' : '✏️ Edit Staff' ?></div>
    <form method="POST">
        <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editRow['name'] ?? '') ?>">
            </div>
            <?php if ($action === 'add'): ?>
            <div class="col-md-3">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Role</label>
                <select name="role" class="form-control">
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editRow['phone'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editRow['email'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= $action === 'add' ? 'Password' : 'New Password' ?> <?= $action === 'add' ? '<span class="text-danger">*</span>' : '<small>(leave blank)</small>' ?></label>
                <input type="password" name="password" class="form-control" <?= $action === 'add' ? 'required' : '' ?>>
            </div>
            <?php if ($action === 'edit'): ?>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="is_active" class="form-control">
                    <option value="1" <?= $editRow['is_active'] ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= !$editRow['is_active'] ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" name="<?= $action === 'add' ? 'add_staff' : 'edit_staff' ?>" class="btn btn-teal"><i class="bi bi-check2-circle me-2"></i><?= $action === 'add' ? 'Add' : 'Update' ?></button>
            <a href="staff.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="data-card">
    <div class="data-card-header"><h6 class="data-card-title"><i class="bi bi-people-fill"></i> All Staff & Admin</h6></div>
    <div class="data-card-body p-0">
        <div class="table-responsive">
            <table class="dairy-table">
                <thead><tr><th>#</th><th>Name</th><th>Username</th><th>Role</th><th>Phone</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php $i=1; while ($s = $staff->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                    <td><?= $s['username'] ?></td>
                    <td><span class="role-badge-<?= $s['role'] ?> badge"><?= strtoupper($s['role']) ?></span></td>
                    <td><?= $s['phone'] ?? '—' ?></td>
                    <td><?= $s['email'] ?? '—' ?></td>
                    <td><span class="<?= $s['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <a href="?action=edit&id=<?= $s['id'] ?>" class="btn-action btn-edit me-1"><i class="bi bi-pencil"></i></a>
                        <?php if ($s['id'] !== $_SESSION['user_id']): ?>
                        <a href="?delete=<?= $s['id'] ?>" class="btn-action btn-delete confirm-delete"><i class="bi bi-person-x"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
