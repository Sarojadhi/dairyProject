<?php
session_start();
require_once '../includes/config.php';
requireLogin();
requireRole(['admin','staff']);

$pageTitle = 'Milk Collection';
$action = $_GET['action'] ?? 'list';
$msg = ''; $err = '';

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_milk'])) {
    $farmer_id = (int)$_POST['farmer_id'];
    $date = sanitize($conn, $_POST['collection_date']);
    $shift = sanitize($conn, $_POST['shift']);
    $litre = (float)$_POST['litre'];
    $fat = (float)$_POST['fat'];
    $snf = (float)$_POST['snf'];
    $rate = getRateForFat($conn, $fat, $snf); // staff cannot override rate
    $recorded_by = $_SESSION['user_id'];

    // Check duplicate
    $chk = $conn->query("SELECT id FROM milk_collection WHERE farmer_id=$farmer_id AND collection_date='$date' AND shift='$shift'");
    if ($chk->num_rows > 0) {
        $err = "⚠️ Entry already exists for this farmer on $date ($shift shift). Please edit the existing record.";
    } elseif ($farmer_id < 1 || $litre <= 0 || $fat <= 0) {
        $err = "Please fill all required fields correctly.";
    } else {
        $stmt = $conn->prepare("INSERT INTO milk_collection (farmer_id, collection_date, shift, litre, fat, snf, rate_per_liter, recorded_by) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('issddddi', $farmer_id, $date, $shift, $litre, $fat, $snf, $rate, $recorded_by);
        if ($stmt->execute()) {
            $msg = "✅ Milk entry added successfully!";
            $action = 'list';
        } else $err = "Error: " . $conn->error;
    }
}

// Handle Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_milk'])) {
    $id = (int)$_POST['id'];
    $litre = (float)$_POST['litre'];
    $fat = (float)$_POST['fat'];
    $snf = (float)$_POST['snf'];
    $rate = getRateForFat($conn, $fat, $snf);

    // Admin can also override rate
    if ($_SESSION['role'] === 'admin' && !empty($_POST['rate_per_liter'])) {
        $rate = (float)$_POST['rate_per_liter'];
    }

    $stmt = $conn->prepare("UPDATE milk_collection SET litre=?, fat=?, snf=?, rate_per_liter=? WHERE id=?");
    $stmt->bind_param('ddddi', $litre, $fat, $snf, $rate, $id);
    if ($stmt->execute()) { $msg = "✅ Record updated."; $action = 'list'; }
    else $err = "Error: " . $conn->error;
}

// Handle Delete (admin only)
if (isset($_GET['delete']) && $_SESSION['role'] === 'admin') {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM milk_collection WHERE id=$id");
    $msg = "🗑️ Record deleted.";
}

// Fetch for edit
$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $editRow = $conn->query("SELECT mc.*, f.name, f.code FROM milk_collection mc JOIN farmers f ON f.id=mc.farmer_id WHERE mc.id=$id")->fetch_assoc();
}

// Filters
$filterDate = sanitize($conn, $_GET['date'] ?? '');
$filterShift = sanitize($conn, $_GET['shift'] ?? '');
$filterFarmer = sanitize($conn, $_GET['farmer'] ?? '');
$where = [];
if ($filterDate) $where[] = "mc.collection_date='$filterDate'";
if ($filterShift) $where[] = "mc.shift='$filterShift'";
if ($filterFarmer) $where[] = "(f.name LIKE '%$filterFarmer%' OR f.code LIKE '%$filterFarmer%')";
$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$records = $conn->query("SELECT mc.*, f.name, f.code FROM milk_collection mc JOIN farmers f ON f.id=mc.farmer_id $whereStr ORDER BY mc.collection_date DESC, mc.shift DESC, mc.id DESC LIMIT 100");
$farmers = $conn->query("SELECT id, code, name FROM farmers WHERE is_active=1 ORDER BY name");
?>
<?php include '../includes/header.php'; ?>
<div class="main-content">

<div class="page-header">
    <h1 class="page-title">
        <div class="page-title-icon"><i class="bi bi-droplet-fill"></i></div>
        Milk Collection
    </h1>
    <?php if ($action !== 'add'): ?>
    <a href="?action=add" class="btn btn-teal"><i class="bi bi-plus-circle me-2"></i>New Entry</a>
    <?php endif; ?>
</div>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger alert-dismissible fade show"><?= $err ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- ADD / EDIT FORM -->
<div class="form-card">
    <div class="form-section-title"><?= $action === 'add' ? '➕ Add New Milk Entry' : '✏️ Edit Milk Entry' ?></div>

    <?php if ($action === 'add'): ?>
    <!-- Farmer Code Lookup -->
    <div class="mb-3">
        <label class="form-label"><i class="bi bi-upc-scan"></i> Farmer Code <span class="text-danger">*</span></label>
        <div class="d-flex gap-2">
            <input type="text" id="farmerCodeInput" class="form-control code-input" style="max-width:180px"
                   placeholder="F001" oninput="lookupFarmer(this.value, document.getElementById('farmerInfo'))">
            <button type="button" class="btn btn-teal" onclick="lookupFarmer(document.getElementById('farmerCodeInput').value, document.getElementById('farmerInfo'))">
                <i class="bi bi-search"></i> Lookup
            </button>
        </div>
    </div>
    <div id="farmerInfo" class="mb-3"></div>
    <?php else: ?>
    <div class="farmer-lookup-card mb-3">
        <div class="farmer-photo-placeholder">👨‍🌾</div>
        <div class="farmer-info">
            <h5><?= htmlspecialchars($editRow['name']) ?></h5>
            <span class="farmer-code"><?= $editRow['code'] ?></span>
            <p>Editing entry #<?= $editRow['id'] ?> — <?= date('d M Y', strtotime($editRow['collection_date'])) ?> (<?= ucfirst($editRow['shift']) ?>)</p>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST">
        <?php if ($action === 'add'): ?>
        <input type="hidden" name="farmer_id" id="farmer_id">
        <?php else: ?>
        <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
        <?php endif; ?>

        <div class="row g-3">
            <?php if ($action === 'add'): ?>
            <div class="col-md-4">
                <label class="form-label">Collection Date <span class="text-danger">*</span></label>
                <input type="date" name="collection_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Shift <span class="text-danger">*</span></label>
                <select name="shift" class="form-control" required>
                    <option value="morning">🌅 Morning</option>
                    <option value="evening">🌙 Evening</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-4">
                <label class="form-label">Litres <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" name="litre" id="litre" class="form-control" step="0.01" min="0.1"
                           placeholder="0.00" value="<?= $editRow['litre'] ?? '' ?>"
                           oninput="calculateTotal()" required>
                    <span class="input-group-text">L</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Fat % <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" name="fat" id="fat" class="form-control" step="0.01" min="0" max="20"
                           placeholder="0.00" value="<?= $editRow['fat'] ?? '' ?>"
                           oninput="updateRate(this.value)" required>
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">SNF %</label>
                <div class="input-group">
                    <input type="number" name="snf" id="snf" class="form-control" step="0.01" min="0" max="20"
                           placeholder="0.00" value="<?= $editRow['snf'] ?? '' ?>"
                           oninput="updateRate(document.getElementById('fat').value)">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Rate/Litre
                    <?= $_SESSION['role'] !== 'admin' ? '<span class="badge bg-secondary" style="font-size:0.65rem">Auto</span>' : '' ?>
                </label>
                <div class="input-group">
                    <span class="input-group-text">रू</span>
                    <input type="number" name="rate_per_liter" id="rate_per_liter" class="form-control"
                           step="0.01" value="<?= $editRow['rate_per_liter'] ?? '' ?>"
                           <?= $_SESSION['role'] !== 'admin' ? 'readonly' : '' ?> placeholder="Auto-set by fat%">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Total Amount</label>
                <div class="form-control bg-light fw-bold" id="total_amount" style="color:var(--teal-700)">—</div>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <?php if ($action === 'add'): ?>
            <button type="submit" name="add_milk" class="btn btn-teal"><i class="bi bi-check2-circle me-2"></i>Save Entry</button>
            <?php else: ?>
            <button type="submit" name="edit_milk" class="btn btn-teal"><i class="bi bi-check2-circle me-2"></i>Update Entry</button>
            <?php endif; ?>
            <a href="milk_collection.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- FILTER + LIST -->
<div class="data-card">
    <div class="data-card-header">
        <h6 class="data-card-title"><i class="bi bi-table"></i> Collection Records</h6>
        <button class="btn btn-sm btn-amber no-print" onclick="printSection('milkTable')"><i class="bi bi-printer"></i> Print</button>
    </div>
    <div class="data-card-body">
        <!-- Filters -->
        <form class="row g-2 mb-3" method="GET">
            <div class="col-6 col-md-3">
                <input type="date" name="date" class="form-control form-control-sm" value="<?= $filterDate ?>" placeholder="Filter by date">
            </div>
            <div class="col-6 col-md-2">
                <select name="shift" class="form-control form-control-sm">
                    <option value="">All Shifts</option>
                    <option value="morning" <?= $filterShift === 'morning' ? 'selected' : '' ?>>Morning</option>
                    <option value="evening" <?= $filterShift === 'evening' ? 'selected' : '' ?>>Evening</option>
                </select>
            </div>
            <div class="col-8 col-md-3">
                <input type="text" name="farmer" class="form-control form-control-sm" placeholder="Farmer name/code" value="<?= $filterFarmer ?>">
            </div>
            <div class="col-4 col-md-2">
                <button class="btn btn-teal btn-sm w-100"><i class="bi bi-filter"></i> Filter</button>
            </div>
            <div class="col-12 col-md-2">
                <a href="milk_collection.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
            </div>
        </form>

        <div id="milkTable" class="table-responsive">
            <table class="dairy-table searchable-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Farmer</th>
                        <th>Date</th>
                        <th>Shift</th>
                        <th>Litres</th>
                        <th>Fat%</th>
                        <th>SNF%</th>
                        <th>Rate</th>
                        <th>Amount</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; while ($row = $records->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong><br>
                            <small class="text-muted"><?= $row['code'] ?></small></td>
                        <td><?= date('d M Y', strtotime($row['collection_date'])) ?></td>
                        <td><span class="shift-<?= $row['shift'] ?>"><?= ucfirst($row['shift']) ?></span></td>
                        <td><strong><?= number_format($row['litre'], 2) ?>L</strong></td>
                        <td><?= number_format($row['fat'], 2) ?>%</td>
                        <td><?= number_format($row['snf'], 2) ?>%</td>
                        <td>रू<?= number_format($row['rate_per_liter'], 2) ?></td>
                        <td><strong style="color:var(--teal-700)">रू<?= number_format($row['total_amount'], 2) ?></strong></td>
                        <td class="no-print">
                            <a href="?action=edit&id=<?= $row['id'] ?>" class="btn-action btn-edit me-1"><i class="bi bi-pencil"></i></a>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="?delete=<?= $row['id'] ?>" class="btn-action btn-delete confirm-delete"><i class="bi bi-trash"></i></a>
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
<script>
// Pre-fill total if editing
document.addEventListener('DOMContentLoaded', function() {
    calculateTotal();
});
</script>
