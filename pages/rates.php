<?php

session_start();
require_once '../includes/config.php';

requireLogin();
requireRole('admin');

$message = '';
$error = '';

/* Save pricing */
if (isset($_POST['save'])) {

    $fat = (float) $_POST['fat_price'];
    $snf = (float) $_POST['snf_price'];

    if ($fat <= 0 || $snf <= 0) {

        $error = 'Fat price and SNF price must be greater than 0.';

    } else {

        $stmt = $conn->prepare(
            "UPDATE pricing_settings
             SET setting_value = ?
             WHERE setting_key = 'fat_price'"
        );

        $stmt->bind_param('d', $fat);
        $stmt->execute();


        $stmt = $conn->prepare(
            "UPDATE pricing_settings
             SET setting_value = ?
             WHERE setting_key = 'snf_price'"
        );

        $stmt->bind_param('d', $snf);
        $stmt->execute();


        $message = 'Milk rate updated successfully.';
    }
}


/* Get current pricing */

$fat = 10.5;
$snf = 3.5;

$result = $conn->query(
    "SELECT setting_key, setting_value
     FROM pricing_settings"
);

while ($row = $result->fetch_assoc()) {

    if ($row['setting_key'] === 'fat_price') {
        $fat = (float) $row['setting_value'];
    }

    if ($row['setting_key'] === 'snf_price') {
        $snf = (float) $row['setting_value'];
    }
}


/* Example calculation */

$exampleFat = 4;
$exampleSnf = 8;

$rate = ($exampleFat * $fat) + ($exampleSnf * $snf);

?>

<?php include '../includes/header.php'; ?>

<div class="main-content">

    <h2>Milk Rate</h2>


    <?php if ($message): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <form method="POST">

        <div class="mb-3">

            <label class="form-label">
                Fat Price
            </label>

            <input
                type="number"
                name="fat_price"
                class="form-control"
                value="<?= $fat ?>"
                step="0.1"
                min="0.1"
                required
            >

        </div>


        <div class="mb-3">

            <label class="form-label">
                SNF Price
            </label>

            <input
                type="number"
                name="snf_price"
                class="form-control"
                value="<?= $snf ?>"
                step="0.1"
                min="0.1"
                required
            >

        </div>


        <button
            type="submit"
            name="save"
            class="btn btn-teal"
        >
            Save
        </button>

    </form>


    <hr>


    <h4>Current Formula</h4>

    <p>
        Rate = (Fat × <?= $fat ?>)
        +
        (SNF × <?= $snf ?>)
    </p>


    <h4>Example</h4>

    <p>
        Fat = <?= $exampleFat ?>%
        <br>

        SNF = <?= $exampleSnf ?>%
        <br>

        Rate =
        (<?= $exampleFat ?> × <?= $fat ?>)
        +
        (<?= $exampleSnf ?> × <?= $snf ?>)

        <br>

        <strong>
            रू<?= number_format($rate, 2) ?>/L
        </strong>
    </p>

</div>

<?php include '../includes/footer.php'; ?>