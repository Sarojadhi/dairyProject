<?php
session_start();
require_once '../includes/config.php';
requireLogin();
requireRole(['admin','staff']);

$pageTitle = 'Manage Farmers';
$action = $_GET['action'] ?? 'list';
$msg = ''; $err = '';

// Add farmer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_farmer'])) {
    $code = strtoupper(sanitize($conn, $_POST['code']));
    $name = sanitize($conn, $_POST['name']);
    $phone = sanitize($conn, $_POST['phone']);
    $address = sanitize($conn, $_POST['address']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $photo = null;

    // Upload photo
    if (!empty($_FILES['photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $filename = 'farmer_' . $code . '_' . time() . '.' . $ext;
            $dest = UPLOAD_PATH . $filename;
            if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                $photo = UPLOAD_URL . $filename;
            }
        } else {
            $err = "Invalid photo format. Use JPG, PNG, GIF, or WEBP.";
        }
    }

    if (!$err) {
        // Check duplicate code
        $chk = $conn->query("SELECT id FROM farmers WHERE code='$code'");
        if ($chk->num_rows > 0) {
            $err = "Farmer code '$code' already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO farmers (code, name, phone, address, photo, password) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('ssssss', $code, $name, $phone, $address, $photo, $password);
            if ($stmt->execute()) { $msg = "✅ Farmer '$name' added with code $code."; $action = 'list'; }
            else $err = "Error: " . $conn->error;
        }
    }
}

// Edit farmer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_farmer'])) {
    $id = (int)$_POST['id'];
    $name = sanitize($conn, $_POST['name']);
    $phone = sanitize($conn, $_POST['phone']);
    $address = sanitize($conn, $_POST['address']);
    $is_active = (int)$_POST['is_active'];

    // Update photo if new one uploaded
    $photoUpdate = '';
    if (!empty($_FILES['photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $existing = $conn->query("SELECT code FROM farmers WHERE id=$id")->fetch_assoc();
            $filename = 'farmer_' . $existing['code'] . '_' . time() . '.' . $ext;
            $dest = UPLOAD_PATH . $filename;
            if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                $newPhoto = UPLOAD_URL . $filename;
                $photoUpdate = ", photo='$newPhoto'";
            }
        }
    }

    // Optional password change
    $passUpdate = '';
    if (!empty($_POST['password'])) {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $passUpdate = ", password='$hash'";
    }

    $conn->query("UPDATE farmers SET name='$name', phone='$phone', address='$address', is_active=$is_active $photoUpdate $passUpdate WHERE id=$id");
    $msg = "✅ Farmer updated.";
    $action = 'list';
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("UPDATE farmers SET is_active=0 WHERE id=$id");
    $msg = "Farmer deactivated.";
}

$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $editRow = $conn->query("SELECT * FROM farmers WHERE id=$id")->fetch_assoc();
}

$farmers = $conn->query("SELECT f.*, (SELECT COUNT(*) FROM milk_collection WHERE farmer_id=f.id) as total_entries FROM farmers f ORDER BY f.is_active DESC, f.code");
?>
<?php include '../includes/header.php'; ?>
<div class="main-content">
<div class="page-header">
    <h1 class="page-title">
        <div class="page-title-icon"><i class="bi bi-people-fill"></i></div>
        Manage Farmers
    </h1>
    <?php if ($action !== 'add'): ?>
    <a href="?action=add" class="btn btn-teal"><i class="bi bi-person-plus me-2"></i>Add Farmer</a>
    <?php endif; ?>
</div>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger alert-dismissible fade show"><?= $err ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="form-card">
    <div class="form-section-title"><?= $action === 'add' ? '➕ Add New Farmer' : '✏️ Edit Farmer' ?></div>
    <form method="POST" enctype="multipart/form-data">
        <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $editRow['id'] ?>"><?php endif; ?>
        <div class="row g-3">
            <?php if ($action === 'add'): ?>
            <div class="col-md-3">
                <label class="form-label">Farmer Code <span class="text-danger">*</span></label>
                <input type="text" name="code" class="form-control code-input" placeholder="F001" required>
                <small class="text-muted">Unique code for this farmer</small>
            </div>
            <?php else: ?>
            <div class="col-md-3">
                <label class="form-label">Farmer Code</label>
                <input type="text" class="form-control" value="<?= $editRow['code'] ?>" readonly>
            </div>
            <?php endif; ?>
            <div class="col-md-4">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editRow['name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editRow['phone'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($editRow['address'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Photo <small class="text-muted">(JPG/PNG)</small></label>
                <input type="file" name="photo" class="form-control" accept="image/*">
                <?php if (!empty($editRow['photo'])): ?>
                <img src="/dairy/<?= $editRow['photo'] ?>" style="height:50px;margin-top:8px;border-radius:6px;" alt="Current photo">
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= $action === 'add' ? 'Password' : 'New Password' ?> <?= $action === 'add' ? '<span class="text-danger">*</span>' : '<small class="text-muted">(leave blank to keep)</small>' ?></label>
                <input type="password" name="password" class="form-control" <?= $action === 'add' ? 'required' : '' ?> placeholder="<?= $action === 'add' ? 'Set password' : 'Leave blank to keep' ?>">
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
            <button type="submit" name="<?= $action === 'add' ? 'add_farmer' : 'edit_farmer' ?>" class="btn btn-teal">
                <i class="bi bi-check2-circle me-2"></i><?= $action === 'add' ? 'Add Farmer' : 'Update' ?>
            </button>
            <a href="farmers.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="data-card">
    <div class="data-card-header">
        <h6 class="data-card-title"><i class="bi bi-people-fill"></i> All Farmers</h6>
        <input type="text" id="tableSearch" class="search-bar" placeholder="🔍 Search farmers...">
    </div>
    <div class="data-card-body p-0">
        <div class="table-responsive">
            <table class="dairy-table searchable-table">
                <thead>
                    <tr><th>Code</th><th>Photo</th><th>Name</th><th>Phone</th><th>Address</th><th>Entries</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php while ($f = $farmers->fetch_assoc()): ?>
                    <tr>
                        <td><span class="farmer-code" style="background:var(--teal-100);color:var(--teal-700);padding:2px 8px;border-radius:12px;font-weight:700;font-size:0.85rem;"><?= $f['code'] ?></span></td>
                        <td>
                            <?php if ($f['photo']): ?>
                            <img src="/dairy/<?= $f['photo'] ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid var(--teal-300);" alt="<?= htmlspecialchars($f['name']) ?>">
                            <?php else: ?>
                            <span style="font-size:1.8rem;">👨‍🌾</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($f['name']) ?></strong></td>
                        <td><?= htmlspecialchars($f['phone'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($f['address'] ?? '—') ?></td>
                        <td><span class="badge bg-secondary"><?= $f['total_entries'] ?></span></td>
                        <td><span class="<?= $f['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $f['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <a href="?action=edit&id=<?= $f['id'] ?>" class="btn-action btn-edit me-1"><i class="bi bi-pencil"></i></a>
                            <a href="/dairy/pages/reports.php?farmer_id=<?= $f['id'] ?>" class="btn-action btn-view me-1"><i class="bi bi-file-earmark-text"></i></a>
                            <?php if ($f['is_active']): ?>
                            <a href="?delete=<?= $f['id'] ?>" class="btn-action btn-delete confirm-delete"><i class="bi bi-person-x"></i></a>
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
