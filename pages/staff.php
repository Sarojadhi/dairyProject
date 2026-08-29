<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('admin');

$pageTitle = 'Staff';
$msg = ''; $err = '';
$editing = false;

// ADD
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['add'])) {
    $name = trim(sanitize($_POST['name'] ?? ''));
    $username = trim(sanitize($_POST['username'] ?? ''));
    $phone = trim(sanitize($_POST['phone'] ?? ''));
    $email = trim(sanitize($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'staff';

    if ($name === '' || $username === '' || $password === '') {
        $err = "Name, username and password are required.";
    } elseif (strlen($password) < 6) {
        $err = "Password must be at least 6 characters.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            "INSERT INTO users (name, username, password, role, phone, email)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssss", $name, $username, $hash, $role, $phone, $email);
        try {
            $stmt->execute();
            $msg = "Staff account \"$username\" created.";
        } catch (mysqli_sql_exception $e) {
            if ($conn->errno === 1062) {
                $err = "Username \"$username\" already exists — pick a different one.";
            } else {
                $err = "Could not create staff account. Please try again.";
            }
        }
    }
}

// UPDATE
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $name = trim(sanitize($_POST['name'] ?? ''));
    $phone = trim(sanitize($_POST['phone'] ?? ''));
    $email = trim(sanitize($_POST['email'] ?? ''));
    $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'staff';

    if ($name === '') {
        $err = "Name is required.";
    } else {
        $stmt = $conn->prepare(
            "UPDATE users SET name=?, phone=?, email=?, role=? WHERE id=?"
        );
        $stmt->bind_param("ssssi", $name, $phone, $email, $role, $id);
        $stmt->execute();
        $msg = "Staff account updated.";
    }
}

// DEACTIVATE (soft delete) - prevent deleting yourself
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id === (int)$_SESSION['user_id']) {
        $err = "You cannot deactivate your own account.";
    } else {
        $conn->query("UPDATE users SET is_active=0 WHERE id=$id AND role != 'admin'");
        $msg = "Staff account deactivated.";
    }
}

if (isset($_GET['activate'])) {
    $id = (int)$_GET['activate'];
    $conn->query("UPDATE users SET is_active=1 WHERE id=$id");
    $msg = "Staff account activated.";
}

// GET EDIT ROW
$editRow = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $editRow = $conn->query("SELECT * FROM users WHERE id=$id")->fetch_assoc();
    if ($editRow) $editing = true;
}

$staff = $conn->query("SELECT id, name, username, role, phone, email, is_active FROM users ORDER BY name");
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1 class="page-title">
            <div class="page-title-icon"><i class="bi bi-person-badge-fill"></i></div>
            Staff Management
        </h1>
    </div>

    <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger alert-dismissible fade show"><?= $err ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <!-- Add / Edit Form -->
    <div class="form-card">
        <div class="form-section-title"><?= $editing ? '✏️ Edit Staff' : '➕ Add Staff' ?></div>

        <form method="POST" class="staff-form" novalidate>
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($editRow['username']) ?>" disabled>
                    <small class="text-muted">Username cannot be changed.</small>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= htmlspecialchars($editRow['name'] ?? '') ?>"
                           placeholder="e.g. Ramesh Shrestha" required>
                </div>

                <?php if (!$editing): ?>
                <div class="col-md-4">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control"
                           placeholder="Login username" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Password <span class="text-danger">*</span> <small class="text-muted">(min 6 chars)</small></label>
                    <input type="password" name="password" class="form-control"
                           placeholder="Login password" required minlength="6">
                </div>
                <?php endif; ?>

                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= htmlspecialchars($editRow['phone'] ?? '') ?>"
                           placeholder="e.g. 98xxxxxxxx" maxlength="15">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($editRow['email'] ?? '') ?>"
                           placeholder="e.g. staff@dairy.com">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control">
                        <option value="staff" <?= (($editRow['role'] ?? '') === 'staff') ? 'selected' : '' ?>>Staff</option>
                        <option value="admin" <?= (($editRow['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" name="<?= $editing ? 'update' : 'add' ?>" class="btn btn-teal">
                    <i class="bi bi-check2-circle me-2"></i><?= $editing ? 'Update Staff' : 'Add Staff' ?>
                </button>
                <?php if ($editing): ?>
                    <a href="staff.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-2"></i>Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Staff List -->
    <div class="data-card">
        <div class="data-card-header">
            <h6 class="data-card-title"><i class="bi bi-table"></i> Staff List</h6>
            <input type="text" id="tableSearch" class="search-bar" placeholder="🔍 Search staff...">
        </div>
        <div class="data-card-body p-0">
            <div class="table-responsive searchable-table">
                <table class="dairy-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($staff->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No staff yet — add the first one above.</td></tr>
                    <?php endif; ?>
                    <?php while ($s = $staff->fetch_assoc()): ?>
                        <tr>
                            <td><?= $s['id'] ?></td>
                            <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                            <td><?= htmlspecialchars($s['username']) ?></td>
                            <td>
                                <?= $s['role'] === 'admin'
                                    ? '<span class="role-badge-admin badge">ADMIN</span>'
                                    : '<span class="role-badge-staff badge">STAFF</span>' ?>
                            </td>
                            <td><?= htmlspecialchars($s['phone'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($s['email'] ?: '—') ?></td>
                            <td>
                                <?= $s['is_active']
                                    ? '<span class="badge-active">Active</span>'
                                    : '<span class="badge-inactive">Inactive</span>' ?>
                            </td>
                            <td class="no-print">
                                <a href="?edit=<?= $s['id'] ?>" class="btn-action btn-edit me-1" title="Edit"><i class="bi bi-pencil"></i></a>
                                <?php if ($s['id'] !== (int)$_SESSION['user_id']): ?>
                                    <?php if ($s['is_active']): ?>
                                        <a href="?delete=<?= $s['id'] ?>" class="btn-action btn-delete confirm-delete" title="Deactivate"><i class="bi bi-person-x"></i></a>
                                    <?php else: ?>
                                        <a href="?activate=<?= $s['id'] ?>" class="btn-action btn-view" title="Activate"><i class="bi bi-person-check"></i></a>
                                    <?php endif; ?>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
(function () {
    var form = document.querySelector('.staff-form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        var ok = true;
        var name = form.querySelector('[name="name"]');
        var username = form.querySelector('[name="username"]');
        var pass = form.querySelector('[name="password"]');

        if (ok && name && name.value.trim() === '') { alert('Full name is required.'); name.focus(); ok = false; }
        if (ok && username && username.value.trim() === '') { alert('Username is required.'); username.focus(); ok = false; }
        if (ok && pass && pass.value.length < 6) { alert('Password must be at least 6 characters.'); pass.focus(); ok = false; }

        if (!ok) e.preventDefault();
    });
})();
</script>
