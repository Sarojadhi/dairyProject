<?php
session_start();
require_once '../includes/config.php';
requireLogin();
requireRole(['admin','staff']);

$pageTitle = 'Dana (Cow Feed)';
$action = $_GET['action'] ?? 'list';
$msg = ''; $err = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_dana'])) {
    $farmer_id = (int)$_POST['farmer_id'];
    $date = sanitize($_POST['dana_date']);
    $bags = (float)$_POST['bags'];
    $rate = (float)$_POST['rate_per_bag'];
    $is_paid = (int)($_POST['is_paid'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? '');
    $by = $_SESSION['user_id'];

    if ($farmer_id < 1 || $bags <= 0 || $rate <= 0) {
        $err = "Please fill all required fields.";
    } else {
        $stmt = $conn->prepare("INSERT INTO dana_records (farmer_id, dana_date, bags, rate_per_bag, is_paid, notes, recorded_by) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('isddiss', $farmer_id, $date, $bags, $rate, $is_paid, $notes, $by);
        if ($stmt->execute()) { $msg = "✅ Dana record added."; $action = 'list'; }
        else $err = "Error: " . $conn->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_dana'])) {
    $id = (int)$_POST['id'];
    $bags = (float)$_POST['bags'];
    $rate = (float)$_POST['rate_per_bag'];
    $is_paid = (int)($_POST['is_paid'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? '');
    $stmt = $conn->prepare("UPDATE dana_records SET bags=?, rate_per_bag=?, is_paid=?, notes=? WHERE id=?");
    $stmt->bind_param('dddsi', $bags, $rate, $is_paid, $notes, $id);
    if ($stmt->execute()) { $msg = "✅ Record updated."; $action = 'list'; }
    else $err = "Error: " . $conn->error;
}

if (isset($_GET['delete']) && $_SESSION['role'] === 'admin') {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM dana_records WHERE id=$id");
    $msg = "🗑️ Record deleted.";
}

$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $editRow = $conn->query("SELECT dr.*, f.name, f.code FROM dana_records dr JOIN farmers f ON f.id=dr.farmer_id WHERE dr.id=$id")->fetch_assoc();
}

$records = $conn->query("SELECT dr.*, f.name, f.code FROM dana_records dr JOIN farmers f ON f.id=dr.farmer_id ORDER BY dr.dana_date DESC LIMIT 100");
?>
<?php include '../includes/header.php'; ?>
<div class="main-content">
<div class="page-header">
    <h1 class="page-title">
        <div class="page-title-icon"><i class="bi bi-basket3-fill"></i></div>
        Dana (Cow Feed) — Dana Chowker
    </h1>
    <?php if ($action !== 'add'): ?>
    <a href="?action=add" class="btn btn-teal"><i class="bi bi-plus-circle me-2"></i>New Dana Entry</a>
    <?php endif; ?>
</div>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger alert-dismissible fade show"><?= $err ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="form-card">
    <div class="form-section-title"><?= $action === 'add' ? '➕ Add Dana Record' : '✏️ Edit Dana Record' ?></div>

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
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <?php if ($action === 'add'): ?>
        <input type="hidden" name="farmer_id" id="farmer_id" required data-msg="Please lookup and select a farmer first.">
        <?php else: ?>
        <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
        <?php endif; ?>

        <div class="row g-3">
            <?php if ($action === 'add'): ?>
            <div class="col-md-4">
                <label class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" name="dana_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <?php endif; ?>
            <div class="col-md-4">
                <label class="form-label">Number of Bags <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" name="bags" id="dana_bags" class="form-control" step="1" min="1"
                           value="<?= $editRow['bags'] ?? '' ?>" placeholder="1"
                           oninput="calcDanaTotal()" required>
                    <span class="input-group-text">bag(s)</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Rate per Bag <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">रू</span>
                    <input type="number" name="rate_per_bag" id="dana_rate" class="form-control" step="0.01" min="0"
                           value="<?= $editRow['rate_per_bag'] ?? '1000' ?>" placeholder="1000"
                           oninput="calcDanaTotal()" required>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Dana Cost</label>
                <div class="form-control bg-light fw-bold" id="dana_total" style="color:var(--danger)">—</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Payment Status</label>
                <select name="is_paid" class="form-control">
                    <option value="0" <?= (isset($editRow['is_paid']) && $editRow['is_paid']) ? '' : 'selected' ?>>❌ Not Paid</option>
                    <option value="1" <?= (isset($editRow['is_paid']) && $editRow['is_paid']) ? 'selected' : '' ?>>✅ Paid</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Farmer's Month Milk</label>
                <div class="form-control bg-light" id="farmer_month_milk" style="color:var(--teal-700)">—</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Net After Dana</label>
                <div class="form-control bg-light fw-bold" id="net_after_dana" style="color:var(--teal-900);font-size:1.2rem">—</div>
            </div>
            <div class="col-md-8">
                <label class="form-label">Notes <small class="text-muted">(optional)</small></label>
                <textarea name="notes" class="form-control" rows="1" maxlength="255" placeholder="Optional notes..."><?= htmlspecialchars($editRow['notes'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" name="<?= $action === 'add' ? 'add_dana' : 'edit_dana' ?>" class="btn btn-teal">
                <i class="bi bi-check2-circle me-2"></i><?= $action === 'add' ? 'Save' : 'Update' ?>
            </button>
            <a href="dana.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="data-card">
    <div class="data-card-header">
        <h6 class="data-card-title"><i class="bi bi-table"></i> Dana Records</h6>
        <button class="btn btn-sm btn-amber" onclick="printSection('danaTable')"><i class="bi bi-printer"></i> Print</button>
    </div>
    <div class="data-card-body p-0">
        <div id="danaTable" class="table-responsive">
            <table class="dairy-table">
                <thead>
                    <tr>
                        <th>#</th><th>Farmer</th><th>Date</th><th>Bags</th><th>Rate/Bag</th><th>Total</th><th>Status</th><th>Notes</th><th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; while ($row = $records->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong><br><small class="text-muted"><?= $row['code'] ?></small></td>
                        <td><?= date('d M Y', strtotime($row['dana_date'])) ?></td>
                        <td><?= number_format($row['bags'], 0) ?> bag(s)</td>
                        <td>रू<?= number_format($row['rate_per_bag'], 2) ?></td>
                        <td><strong style="color:var(--danger)">रू<?= number_format($row['total_amount'], 2) ?></strong></td>
                        <td><?= $row['is_paid'] ? '<span class="badge-paid">✅ Paid</span>' : '<span class="badge-unpaid">❌ Not Paid</span>' ?></td>
                        <td><?= htmlspecialchars($row['notes'] ?? '—') ?></td>
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
let currentMonthMilk = 0;

function calcDanaTotal() {
    const bags = parseFloat(document.getElementById('dana_bags')?.value) || 0;
    const rate = parseFloat(document.getElementById('dana_rate')?.value) || 0;
    const danaCost = bags * rate;
    const t = document.getElementById('dana_total');
    if (t) t.textContent = 'रू ' + danaCost.toFixed(2);

    const net = currentMonthMilk - danaCost;
    const netEl = document.getElementById('net_after_dana');
    if (netEl) {
        netEl.textContent = 'रू ' + net.toFixed(2);
        netEl.style.color = net >= 0 ? 'var(--teal-900)' : 'var(--danger)';
    }
}

// Override lookupFarmer to also show month milk
const origLookup = window.lookupFarmer;
window.lookupFarmer = function(code, targetDiv) {
    if (!code || code.length < 2) {
        targetDiv.innerHTML = '';
        return;
    }
    fetch('/dairy/api/farmer_lookup.php?code=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                targetDiv.innerHTML = `<div class="alert alert-danger py-2 mb-0">❌ ${data.error}</div>`;
                return;
            }
            const photo = data.photo
                ? `<img src="/dairy/${data.photo}" class="farmer-photo" alt="${data.name}">`
                : `<div class="farmer-photo-placeholder">👨‍🌾</div>`;
            targetDiv.innerHTML = `
                <div class="farmer-lookup-card">
                    ${photo}
                    <div class="farmer-info">
                        <h5>${data.name}</h5>
                        <span class="farmer-code">${data.code}</span>
                        <p>📞 ${data.phone || 'N/A'} &nbsp;|&nbsp; 📍 ${data.address || 'N/A'}</p>
                    </div>
                </div>`;
            const hiddenId = document.getElementById('farmer_id');
            if (hiddenId) hiddenId.value = data.id;

            currentMonthMilk = data.month_milk_total || 0;
            const milkEl = document.getElementById('farmer_month_milk');
            if (milkEl) milkEl.textContent = 'रू ' + currentMonthMilk.toFixed(2);
            calcDanaTotal();
        })
        .catch(() => {
            targetDiv.innerHTML = `<div class="alert alert-danger py-2 mb-0">❌ Lookup failed</div>`;
        });
};

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($action === 'edit' && $editRow): ?>
    // For edit mode, set currentMonthMilk from available data
    currentMonthMilk = 0;
    <?php endif; ?>
    calcDanaTotal();
});
</script>
