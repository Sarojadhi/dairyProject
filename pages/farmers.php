<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('admin');

$pageTitle = 'Farmers';
$editing = false;
$msg = ''; $err = '';

// Handle photo upload helper
function handleFarmerPhoto($file)
{
    if (empty($file['name'])) return null;

    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        return ['error' => 'Photo must be JPG, PNG, WEBP or GIF.'];
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return ['error' => 'Photo must be 2MB or smaller.'];
    }

    $filename = 'farmer_' . uniqid() . '_' . time() . '.' . $ext;

    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0775, true);
    }

    if (move_uploaded_file($file['tmp_name'], UPLOAD_PATH . $filename)) {
        return ['path' => UPLOAD_URL . $filename];
    }

    return ['error' => 'Failed to upload photo.'];
}

// ADD FARMER
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['add'])) {
    $code = strtoupper(trim(sanitize($_POST['code'] ?? '')));
    $name = trim(sanitize($_POST['name'] ?? ''));
    $phone = trim(sanitize($_POST['phone'] ?? ''));
    $address = trim(sanitize($_POST['address'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($code === '' || $name === '' || $password === '') {
        $err = 'Farmer code, name and password are required.';
    } elseif (strlen($password) < 6) {
        $err = 'Password must be at least 6 characters.';
    } else {
        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $up = handleFarmerPhoto($_FILES['photo']);
            if (isset($up['error'])) {
                $err = $up['error'];
            } else {
                $photo = $up['path'];
            }
        }

        if (!$err) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT INTO farmers (code, name, phone, address, photo, password)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("ssssss", $code, $name, $phone, $address, $photo, $hash);
            try {
                $stmt->execute();
                $msg = "Farmer \"$name\" ($code) added successfully.";
            } catch (mysqli_sql_exception $e) {
                if ($conn->errno === 1062) {
                    $err = "Farmer code $code already exists — please use a unique code.";
                } else {
                    $err = "Could not add farmer. Please try again.";
                }
            }
        }
    }
}

// UPDATE FARMER
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $name = trim(sanitize($_POST['name'] ?? ''));
    $phone = trim(sanitize($_POST['phone'] ?? ''));
    $address = trim(sanitize($_POST['address'] ?? ''));

    if ($name === '') {
        $err = 'Farmer name is required.';
    } else {
        $photoSql = '';
        $params = '';
        $values = [];

        if (!empty($_FILES['photo']['name'])) {
            $up = handleFarmerPhoto($_FILES['photo']);
            if (isset($up['error'])) {
                $err = $up['error'];
            } else {
                $photoSql = ", photo = ?";
                $params = "photo";
                $values[] = $up['path'];
            }
        }

        if (!$err) {
            $sql = "UPDATE farmers SET name = ?, phone = ?, address = ? $photoSql WHERE id = ?";
            $types = "sss" . $params . "i";
            $bind = array_merge([$name, $phone, $address], $values, [$id]);
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$bind);
            $stmt->execute();
            $msg = "Farmer updated successfully.";
            if (isset($_POST['_editing_id'])) $_POST['_editing_id'] = '';
        }
    }
}

// TOGGLE ACTIVE / DEACTIVATE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("UPDATE farmers SET is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $msg = "Farmer deactivated.";
}

if (isset($_GET['activate'])) {
    $id = (int)$_GET['activate'];
    $stmt = $conn->prepare("UPDATE farmers SET is_active = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $msg = "Farmer activated.";
}

// GET EDIT ROW
$editRow = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM farmers WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($editRow) $editing = true;
}

$farmers = $conn->query("SELECT * FROM farmers ORDER BY name");
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1 class="page-title">
            <div class="page-title-icon"><i class="bi bi-people-fill"></i></div>
            Farmers
        </h1>
    </div>

    <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger alert-dismissible fade show"><?= $err ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <!-- Add / Edit Form -->
    <div class="form-card">
        <div class="form-section-title">
            <?= $editing ? '✏️ Edit Farmer' : '➕ Add New Farmer' ?>
        </div>

        <form method="POST" enctype="multipart/form-data" class="farmer-form" novalidate>
            <?php if ($editing): ?>
                <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Farmer Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control code-input" style="max-width:200px"
                           value="<?= htmlspecialchars($editRow['code'] ?? '') ?>"
                           placeholder="e.g. F001" maxlength="20"
                           <?= $editing ? 'readonly' : 'required' ?>>
                    <?php if (!$editing): ?><small class="text-muted">Unique code used for login & lookup.</small><?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= htmlspecialchars($editRow['name'] ?? '') ?>"
                           placeholder="e.g. Hari Bahadur" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= htmlspecialchars($editRow['phone'] ?? '') ?>"
                           placeholder="e.g. 98xxxxxxxx" maxlength="15">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control"
                           value="<?= htmlspecialchars($editRow['address'] ?? '') ?>"
                           placeholder="e.g. Kathmandu">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Photo <?= $editing ? '' : '<small class="text-muted">(optional, max 2MB)</small>' ?></label>
                    <input type="file" name="photo" class="form-control" accept="image/*">
                    <?php if ($editing && !empty($editRow['photo'])): ?>
                        <img src="<?= BASE_URL ?><?= htmlspecialchars($editRow['photo']) ?>" alt="Current photo"
                             class="mt-2" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid var(--amber-400)">
                    <?php endif; ?>
                </div>

                <?php if (!$editing): ?>
                <div class="col-md-6">
                    <label class="form-label">Password <span class="text-danger">*</span> <small class="text-muted">(min 6 chars)</small></label>
                    <input type="password" name="password" class="form-control"
                           placeholder="Login password for the farmer" required minlength="6">
                </div>
                <?php endif; ?>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" name="<?= $editing ? 'update' : 'add' ?>" class="btn btn-teal">
                    <i class="bi bi-check2-circle me-2"></i><?= $editing ? 'Update Farmer' : 'Add Farmer' ?>
                </button>
                <?php if ($editing): ?>
                    <a href="farmers.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-2"></i>Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Farmers List -->
    <div class="data-card">
        <div class="data-card-header">
            <h6 class="data-card-title"><i class="bi bi-table"></i> Farmer List</h6>
            <input type="text" id="tableSearch" class="search-bar" placeholder="🔍 Search farmer...">
        </div>
        <div class="data-card-body p-0">
            <div class="table-responsive searchable-table">
                <table class="dairy-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Photo</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($farmers->num_rows === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No farmers yet — add the first one above.</td></tr>
                    <?php endif; ?>
                    <?php $i = 1; while ($f = $farmers->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <?php if (!empty($f['photo'])): ?>
                                    <img src="<?= BASE_URL ?><?= htmlspecialchars($f['photo']) ?>" alt="" style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid var(--teal-300)">
                                <?php else: ?>
                                    <div style="width:42px;height:42px;border-radius:50%;background:var(--teal-100);display:flex;align-items:center;justify-content:center;color:var(--teal-700)">👨‍🌾</div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($f['code']) ?></strong></td>
                            <td><?= htmlspecialchars($f['name']) ?></td>
                            <td><?= htmlspecialchars($f['phone'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($f['address'] ?: '—') ?></td>
                            <td>
                                <?= $f['is_active']
                                    ? '<span class="badge-active">Active</span>'
                                    : '<span class="badge-inactive">Inactive</span>' ?>
                            </td>
                            <td class="no-print">
                                <a href="?edit=<?= $f['id'] ?>" class="btn-action btn-edit me-1" title="Edit"><i class="bi bi-pencil"></i></a>
                                <?php if ($f['is_active']): ?>
                                    <a href="?delete=<?= $f['id'] ?>" class="btn-action btn-delete confirm-delete" title="Deactivate"><i class="bi bi-person-x"></i></a>
                                <?php else: ?>
                                    <a href="?activate=<?= $f['id'] ?>" class="btn-action btn-view" title="Activate"><i class="bi bi-person-check"></i></a>
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
    var form = document.querySelector('.farmer-form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        var ok = true;
        var code = form.querySelector('[name="code"]');
        var name = form.querySelector('[name="name"]');
        var pass = form.querySelector('[name="password"]');

        if (code && !code.readOnly) {
            code.value = code.value.trim().toUpperCase();
            if (code.value === '') { alert('Farmer code is required.'); code.focus(); ok = false; }
        }
        if (ok && name && name.value.trim() === '') { alert('Farmer name is required.'); name.focus(); ok = false; }
        if (ok && pass && pass.value.length < 6) { alert('Password must be at least 6 characters.'); pass.focus(); ok = false; }

        if (!ok) e.preventDefault();
    });
})();
</script>
