<?php
session_start();
require_once '../includes/config.php';
requireLogin();
requireRole('admin');

$pageTitle = 'Milk Rates';
$msg = ''; $err = '';

// Get current pricing settings
$fp = FAT_PRICE; $sp = SNF_PRICE;
$r = $conn->query("SELECT setting_key, setting_value FROM pricing_settings WHERE setting_key IN ('fat_price','snf_price')");
while ($row = $r->fetch_assoc()) {
    if ($row['setting_key'] === 'fat_price') $fp = (float)$row['setting_value'];
    if ($row['setting_key'] === 'snf_price') $sp = (float)$row['setting_value'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pricing'])) {
    $newFp = (float)$_POST['fat_price'];
    $newSp = (float)$_POST['snf_price'];
    if ($newFp > 0 && $newSp > 0) {
        $conn->query("UPDATE pricing_settings SET setting_value=$newFp WHERE setting_key='fat_price'");
        $conn->query("UPDATE pricing_settings SET setting_value=$newSp WHERE setting_key='snf_price'");
        $fp = $newFp; $sp = $newSp;
        $msg = "✅ Pricing formula updated successfully!";
    } else {
        $err = "Both values must be greater than 0.";
    }
}

// Show preview for common fat/snf combinations
$preview = [];
$testFats = [2.5, 3.0, 3.5, 4.0, 4.5, 5.0, 5.5, 6.0];
$testSnfs = [7.0, 7.5, 8.0, 8.5, 9.0, 9.5];
foreach ($testFats as $f) {
    foreach ($testSnfs as $s) {
        $rate = round(($f * $fp) + ($s * $sp), 2);
        $preview[] = ['fat' => $f, 'snf' => $s, 'rate' => $rate];
    }
}
?>
<?php include '../includes/header.php'; ?>
<div class="main-content">
<div class="page-header">
    <h1 class="page-title"><div class="page-title-icon"><i class="bi bi-currency-rupee"></i></div>Milk Pricing Formula</h1>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    Rate = (Fat% × <strong>Fat Price</strong>) + (SNF% × <strong>SNF Price</strong>)
    <br>Example: 4% fat + 8.0 SNF → 4(<?= $fp ?>) + 8(<?= $sp ?>) = <strong>रू<?= round(4*$fp + 8*$sp, 2) ?>/L</strong>
</div>

<div class="row g-3">
    <!-- Settings Form -->
    <div class="col-lg-5">
        <div class="form-card">
            <div class="form-section-title">⚙️ Set Pricing Coefficients</div>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Fat Price (per %)</label>
                    <div class="input-group">
                        <span class="input-group-text">रू</span>
                        <input type="number" name="fat_price" class="form-control" step="0.1" min="1"
                               value="<?= $fp ?>" required>
                    </div>
                    <small class="text-muted">Higher = more weight to fat content</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">SNF Price (per %)</label>
                    <div class="input-group">
                        <span class="input-group-text">रू</span>
                        <input type="number" name="snf_price" class="form-control" step="0.1" min="1"
                               value="<?= $sp ?>" required>
                    </div>
                    <small class="text-muted">Higher = more weight to SNF content</small>
                </div>
                <button type="submit" name="save_pricing" class="btn btn-teal">
                    <i class="bi bi-save me-2"></i>Save Formula
                </button>
            </form>
        </div>
    </div>

    <!-- Preview Table -->
    <div class="col-lg-7">
        <div class="data-card">
            <div class="data-card-header">
                <h6 class="data-card-title">📊 Rate Preview (Fat × <?= $fp ?> + SNF × <?= $sp ?>)</h6>
            </div>
            <div class="data-card-body p-0">
                <div class="table-responsive">
                    <table class="dairy-table">
                        <thead>
                            <tr><th>Fat %</th>
                                <?php foreach ($testSnfs as $s): ?>
                                <th>SNF <?= $s ?>%</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $idx = 0; foreach ($testFats as $f): ?>
                            <tr>
                                <td><strong><?= $f ?>%</strong></td>
                                <?php foreach ($testSnfs as $s): ?>
                                <td class="<?= ($f == 4 && $s == 8) ? 'bg-warning-subtle fw-bold' : '' ?>">
                                    रू<?= $preview[$idx]['rate'] ?>
                                </td>
                                <?php $idx++; endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
<?php include '../includes/footer.php'; ?>
