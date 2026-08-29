<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('admin');

$pageTitle = 'Milk Rates';
$message = '';
$error = '';

// Save pricing
if (isset($_POST['save'])) {
    $fat = (float)$_POST['fat_price'];
    $snf = (float)$_POST['snf_price'];

    if ($fat <= 0 || $snf <= 0) {
        $error = 'Fat price and SNF price must be greater than 0.';
    } else {
        $stmt = $conn->prepare("UPDATE pricing_settings SET setting_value = ? WHERE setting_key = 'fat_price'");
        $stmt->bind_param('d', $fat);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE pricing_settings SET setting_value = ? WHERE setting_key = 'snf_price'");
        $stmt->bind_param('d', $snf);
        $stmt->execute();
        $stmt->close();

        $message = 'Milk rate updated successfully.';
    }
}

// Get current pricing
$fat = 10.5;
$snf = 3.5;
$result = $conn->query("SELECT setting_key, setting_value FROM pricing_settings");
while ($row = $result->fetch_assoc()) {
    if ($row['setting_key'] === 'fat_price') $fat = (float)$row['setting_value'];
    if ($row['setting_key'] === 'snf_price') $snf = (float)$row['setting_value'];
}

$exampleFat = 4;
$exampleSnf = 8;
$rate = ($exampleFat * $fat) + ($exampleSnf * $snf);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1 class="page-title">
            <div class="page-title-icon"><i class="bi bi-currency-rupee"></i></div>
            Milk Rates
        </h1>
    </div>

    <?php if ($message): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="form-card">
                <div class="form-section-title">⚙️ Set Price per Unit</div>

                <form method="POST" class="rate-form" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Fat Price (रू per %) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">रू</span>
                            <input type="number" name="fat_price" class="form-control" value="<?= $fat ?>"
                                   step="0.1" min="0.1" placeholder="e.g. 10.5" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">SNF Price (रू per %) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">रू</span>
                            <input type="number" name="snf_price" class="form-control" value="<?= $snf ?>"
                                   step="0.1" min="0.1" placeholder="e.g. 3.5" required>
                        </div>
                    </div>

                    <button type="submit" name="save" class="btn btn-teal">
                        <i class="bi bi-check2-circle me-2"></i>Save Rates
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="data-card">
                <div class="data-card-header">
                    <h6 class="data-card-title"><i class="bi bi-calculator"></i> Current Formula</h6>
                </div>
                <div class="data-card-body">
                    <p class="mb-2"><strong>Rate = (Fat × Fat Price) + (SNF × SNF Price)</strong></p>

                    <div class="mb-4 p-3" style="background:var(--teal-100);border-radius:10px">
                        <div class="d-flex justify-content-between">
                            <span>Fat Price</span><strong>रू<?= $fat ?>/%</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>SNF Price</span><strong>रू<?= $snf ?>/%</strong>
                        </div>
                    </div>

                    <h6 class="form-section-title">Example Calculation</h6>
                    <p class="mb-1">
                        Fat = <?= $exampleFat ?>%, SNF = <?= $exampleSnf ?>%
                    </p>
                    <p class="mb-2 text-muted">
                        (<?= $exampleFat ?> × <?= $fat ?>) + (<?= $exampleSnf ?> × <?= $snf ?>)
                    </p>
                    <div class="d-flex align-items-center gap-3">
                        <span>Result:</span>
                        <h2 class="mb-0" style="color:var(--teal-900)">
                            रू<?= number_format($rate, 2) ?>/L
                        </h2>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
(function () {
    var form = document.querySelector('.rate-form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        var fat = parseFloat(form.querySelector('[name="fat_price"]').value);
        var snf = parseFloat(form.querySelector('[name="snf_price"]').value);
        if (isNaN(fat) || fat <= 0 || isNaN(snf) || snf <= 0) {
            alert('Both Fat price and SNF price must be greater than 0.');
            e.preventDefault();
        }
    });
})();
</script>
