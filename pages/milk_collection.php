<?php
session_start();
require_once '../includes/config.php';
requireLogin();
requireRole(['admin', 'staff']);

$pageTitle = 'Milk Collection';
$action = $_GET['action'] ?? 'list';
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $farmer_id = (int)$_POST['farmer_id'];
    $date = sanitize($_POST['date']);
    $shift = ($_POST['shift'] ?? '') === 'evening' ? 'evening' : 'morning';
    $litre = (float)$_POST['litre'];
    $fat = (float)$_POST['fat'];
    $snf = (float)$_POST['snf'];

    if ($farmer_id < 1 || $litre <= 0) {
        $err = "Please select a farmer and enter litres.";
    } else {
        // total_amount is a GENERATED column - the database calculates it
        $rate = getRateForFat($conn, $fat, $snf);

        $stmt = $conn->prepare(
            "INSERT INTO milk_collection
            (farmer_id, collection_date, shift, litre, fat, snf, rate_per_liter)
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "issdddd",
            $farmer_id,
            $date,
            $shift,
            $litre,
            $fat,
            $snf,
            $rate
        );

        try {
            $stmt->execute();
            $msg = "✅ Milk collection added.";
        } catch (mysqli_sql_exception $e) {
            $err = "Could not save the record. Please check the values and try again.";
        }
        $action = 'list';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $litre = (float)$_POST['litre'];
    $fat = (float)$_POST['fat'];
    $snf = (float)$_POST['snf'];

    // total_amount is regenerated automatically by the database
    $rate = getRateForFat($conn, $fat, $snf);

    $stmt = $conn->prepare(
        "UPDATE milk_collection
         SET litre=?, fat=?, snf=?, rate_per_liter=?
         WHERE id=?"
    );

    $stmt->bind_param(
        "ddddi",
        $litre,
        $fat,
        $snf,
        $rate,
        $id
    );

    try {
        $stmt->execute();
        $msg = "✅ Record updated.";
    } catch (mysqli_sql_exception $e) {
        $err = "Could not update the record. Please try again.";
    }
    $action = 'list';
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM milk_collection WHERE id=$id");
    $msg = "🗑️ Record deleted.";
}

$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $editRow = $conn->query(
        "SELECT mc.*, f.name, f.code
         FROM milk_collection mc
         JOIN farmers f ON f.id = mc.farmer_id
         WHERE mc.id=$id"
    )->fetch_assoc();
    if (!$editRow) { $action = 'list'; $err = "Record not found."; }
}

$farmers = $conn->query(
    "SELECT id, code, name FROM farmers WHERE is_active=1"
);

$records = $conn->query(
    "SELECT milk_collection.*, farmers.name, farmers.code
     FROM milk_collection
     JOIN farmers ON farmers.id = milk_collection.farmer_id
     ORDER BY collection_date DESC, id DESC
     LIMIT 200"
);
?>
<?php include '../includes/header.php'; ?>
<div class="main-content">

<div class="page-header">
    <h1 class="page-title">
        <div class="page-title-icon"><i class="bi bi-droplet-fill"></i></div>
        Milk Collection
    </h1>
    <?php if ($action !== 'add' && $action !== 'edit'): ?>
    <a href="?action=add" class="btn btn-teal"><i class="bi bi-plus-circle me-2"></i>New Milk Entry</a>
    <?php endif; ?>
</div>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger alert-dismissible fade show"><?= $err ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="form-card">
    <div class="form-section-title"><?= $action === 'add' ? '➕ New Milk Entry' : '✏️ Edit Milk Record' ?></div>

    <?php if ($action === 'add'): ?>
    <div class="mb-3">
        <label class="form-label"><i class="bi bi-upc-scan"></i> Farmer Code <span class="text-danger">*</span></label>
        <div class="d-flex gap-2">
            <input type="text" id="farmerCodeInput" class="form-control code-input" style="max-width:180px" placeholder="F001"
                   oninput="lookupFarmer(this.value, document.getElementById('farmerInfo'))">
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
            <p>📅 <?= date('d M Y', strtotime($editRow['collection_date'])) ?> &nbsp;|&nbsp;
               <?= $editRow['shift'] === 'morning' ? '☀️ Morning' : '🌙 Evening' ?> shift</p>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <?php if ($action === 'add'): ?>
        <input type="hidden" name="farmer_id" id="farmer_id" required>
        <?php else: ?>
        <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
        <?php endif; ?>

        <div class="row g-3">
            <?php if ($action === 'add'): ?>
            <div class="col-md-3">
                <label class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Shift</label>
                <select name="shift" class="form-control">
                    <option value="morning">☀️ Morning</option>
                    <option value="evening">🌙 Evening</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label">Litres <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" name="litre" id="litre" class="form-control" step="0.01" min="0.1"
                           value="<?= $editRow['litre'] ?? '' ?>" placeholder="0.00"
                           oninput="calculateTotal()" required>
                    <span class="input-group-text">L</span>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fat % <span class="text-danger">*</span></label>
                <input type="number" name="fat" id="fat" class="form-control" step="0.1" min="0" max="15"
                       value="<?= $editRow['fat'] ?? '' ?>" placeholder="e.g. 4.5"
                       oninput="updateRate(this.value)" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">SNF %</label>
                <input type="number" name="snf" id="snf" class="form-control" step="0.1" min="0" max="15"
                       value="<?= $editRow['snf'] ?? '' ?>" placeholder="e.g. 8.5"
                       oninput="updateRate(document.getElementById('fat').value)">
            </div>
            <div class="col-md-3">
                <label class="form-label">Rate / Litre</label>
                <div class="input-group">
                    <span class="input-group-text">रू</span>
                    <input type="number" id="rate_per_liter" class="form-control bg-light" step="0.01" readonly
                           value="<?= $editRow['rate_per_liter'] ?? '' ?>" placeholder="—">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Total Amount</label>
                <div class="form-control bg-light fw-bold" id="total_amount" style="color:var(--teal-900);font-size:1.05rem">—</div>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button type="submit" name="<?= $action === 'add' ? 'add' : 'update' ?>" class="btn btn-teal">
                <i class="bi bi-check2-circle me-2"></i><?= $action === 'add' ? 'Save Entry' : 'Update Record' ?>
            </button>
            <a href="milk_collection.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="data-card">
    <div class="data-card-header">
        <h6 class="data-card-title"><i class="bi bi-table"></i> Collection Records</h6>
        <div class="d-flex gap-2">
            <input type="text" id="tableSearch" class="search-bar" placeholder="🔍 Search farmer / date...">
            <button class="btn btn-sm btn-amber" onclick="printSection('milkTable')"><i class="bi bi-printer"></i> Print</button>
        </div>
    </div>
    <div class="data-card-body p-0">
        <div id="milkTable" class="table-responsive searchable-table">
            <table class="dairy-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Farmer</th>
                        <th>Date</th>
                        <th>Shift</th>
                        <th>Litres</th>
                        <th>Fat %</th>
                        <th>SNF %</th>
                        <th>Rate/L</th>
                        <th>Amount</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($records->num_rows === 0): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">No milk collection records yet.</td></tr>
                <?php endif; ?>
                <?php $i = 1; while ($row = $records->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong><br><small class="text-muted"><?= $row['code'] ?></small></td>
                        <td><?= date('d M Y', strtotime($row['collection_date'])) ?></td>
                        <td><?= $row['shift'] === 'morning'
                                ? '<span class="shift-morning">☀️ Morning</span>'
                                : '<span class="shift-evening">🌙 Evening</span>' ?></td>
                        <td><?= number_format($row['litre'], 2) ?> L</td>
                        <td><?= number_format($row['fat'], 1) ?></td>
                        <td><?= number_format($row['snf'], 1) ?></td>
                        <td>रू<?= number_format($row['rate_per_liter'], 2) ?></td>
                        <td><strong style="color:var(--teal-700)">रू<?= number_format($row['total_amount'], 2) ?></strong></td>
                        <td class="no-print">
                            <a href="?action=edit&id=<?= $row['id'] ?>" class="btn-action btn-edit me-1" title="Edit"><i class="bi bi-pencil"></i></a>
                            <a href="?delete=<?= $row['id'] ?>" class="btn-action btn-delete confirm-delete" title="Delete"><i class="bi bi-trash"></i></a>
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
