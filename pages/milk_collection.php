<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole(['admin', 'staff']);

$pageTitle = 'Milk Collection';
$action = $_GET['action'] ?? 'list';
$msg = '';
$err = '';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Note: getRateForFat() is defined in includes/config.php - do not redefine it here.

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $err = "Security validation failed. Please try again.";
    } elseif (isset($_POST['add'])) {
        // ADD NEW RECORD
        $farmer_id = (int)$_POST['farmer_id'];
        $date = sanitize($_POST['date']);
        $shift = ($_POST['shift'] ?? '') === 'evening' ? 'evening' : 'morning';
        $litre = (float)$_POST['litre'];
        $fat = (float)$_POST['fat'];
        $snf = (float)$_POST['snf'];
        
        // Validation
        if ($farmer_id < 1 || $litre <= 0) {
            $err = "Please select a valid farmer and enter litres.";
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) {
            $err = "Date cannot be in the future — use today or a past date.";
        } elseif ($fat < 0 || $fat > 15 || $snf < 0 || $snf > 15) {
            $err = "Fat and SNF must be between 0 and 15.";
        } else {
            // Get rate
            $rate = getRateForFat($conn, $fat, $snf);
            
            // Prepare and execute
            $stmt = $conn->prepare(
                "INSERT INTO milk_collection 
                (farmer_id, collection_date, shift, litre, fat, snf, rate_per_liter) 
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            
            if ($stmt) {
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
                    if ($stmt->execute()) {
                        $msg = "✅ Milk collection added successfully.";
                        $action = 'list';
                    } else {
                        $err = "Failed to add record. Please try again.";
                    }
                } catch (mysqli_sql_exception $e) {
                    $err = "Database error: " . $e->getMessage();
                }
                $stmt->close();
            } else {
                $err = "Database preparation error.";
            }
        }
    } elseif (isset($_POST['update'])) {
        // UPDATE RECORD
        $id = (int)$_POST['id'];
        $litre = (float)$_POST['litre'];
        $fat = (float)$_POST['fat'];
        $snf = (float)$_POST['snf'];
        
        // Validation
        if ($id < 1 || $litre <= 0) {
            $err = "Invalid record ID or litres.";
        } elseif ($fat < 0 || $fat > 15 || $snf < 0 || $snf > 15) {
            $err = "Fat and SNF must be between 0 and 15.";
        } else {
            $rate = getRateForFat($conn, $fat, $snf);
            
            $stmt = $conn->prepare(
                "UPDATE milk_collection 
                 SET litre=?, fat=?, snf=?, rate_per_liter=? 
                 WHERE id=?"
            );
            
            if ($stmt) {
                $stmt->bind_param(
                    "ddddi",
                    $litre,
                    $fat,
                    $snf,
                    $rate,
                    $id
                );
                
                try {
                    if ($stmt->execute()) {
                        $msg = "✅ Record updated successfully.";
                        $action = 'list';
                    } else {
                        $err = "Failed to update record.";
                    }
                } catch (mysqli_sql_exception $e) {
                    $err = "Database error: " . $e->getMessage();
                }
                $stmt->close();
            } else {
                $err = "Database preparation error.";
            }
        }
    }
}

// Handle DELETE request
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM milk_collection WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $msg = "🗑️ Record deleted successfully.";
            } else {
                $err = "Failed to delete record.";
            }
            $stmt->close();
        } else {
            $err = "Database preparation error.";
        }
    }
}

// Fetch edit data
$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $stmt = $conn->prepare(
        "SELECT mc.*, f.name, f.code 
         FROM milk_collection mc 
         JOIN farmers f ON f.id = mc.farmer_id 
         WHERE mc.id = ?"
    );
    
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $editRow = $result->fetch_assoc();
        $stmt->close();
        
        if (!$editRow) {
            $action = 'list';
            $err = "Record not found.";
        }
    } else {
        $err = "Database error.";
    }
}

// Fetch farmers list
$farmers = $conn->query("SELECT id, code, name FROM farmers WHERE is_active=1 ORDER BY name");

// Fetch records
$records = $conn->query(
    "SELECT milk_collection.*, farmers.name, farmers.code 
     FROM milk_collection 
     JOIN farmers ON farmers.id = milk_collection.farmer_id 
     ORDER BY collection_date DESC, id DESC 
     LIMIT 200"
);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

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

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= htmlspecialchars($err) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="form-card">
    <div class="form-section-title">
        <?= $action === 'add' ? '➕ New Milk Entry' : '✏️ Edit Milk Record' ?>
    </div>

    <div id="milkFormAlert" class="mb-3"></div>

    <?php if ($action === 'add'): ?>
    <div class="mb-3">
        <label class="form-label"><i class="bi bi-upc-scan"></i> Farmer Code <span class="text-danger">*</span></label>
        <div class="d-flex gap-2">
            <input type="text" id="farmerCodeInput" class="form-control code-input" style="max-width:180px" placeholder="F001"
                   oninput="lookupFarmer(this.value, document.getElementById('farmerInfo'), document.getElementById('farmer_id'))">
            <button type="button" class="btn btn-teal" onclick="lookupFarmer(document.getElementById('farmerCodeInput').value, document.getElementById('farmerInfo'), document.getElementById('farmer_id'))">
                <i class="bi bi-search"></i> Lookup
            </button>
        </div>
        <div class="field-error" id="farmerCodeInput_error"></div>
    </div>
    <div id="farmerInfo" class="mb-3"></div>
    <?php else: ?>
    <div class="farmer-lookup-card mb-3">
        <div class="farmer-photo-placeholder">👨‍🌾</div>
        <div class="farmer-info">
            <h5><?= htmlspecialchars($editRow['name']) ?></h5>
            <span class="farmer-code"><?= htmlspecialchars($editRow['code']) ?></span>
            <p>📅 <?= date('d M Y', strtotime($editRow['collection_date'])) ?> &nbsp;|&nbsp;
               <?= $editRow['shift'] === 'morning' ? '☀️ Morning' : '🌙 Evening' ?> shift</p>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate id="milkEntryForm" onsubmit="return validateForm()">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        
        <?php if ($action === 'add'): ?>
        <input type="hidden" name="farmer_id" id="farmer_id" value="0">
        <?php else: ?>
        <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
        <input type="hidden" name="farmer_id" value="<?= $editRow['farmer_id'] ?>">
        <input type="hidden" name="date" value="<?= $editRow['collection_date'] ?>">
        <input type="hidden" name="shift" value="<?= $editRow['shift'] ?>">
        <?php endif; ?>

        <div class="row g-3">
            <?php if ($action === 'add'): ?>
            <div class="col-md-3">
                <label class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" name="date" id="date" class="form-control" 
                       value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                <div class="field-error" id="date_error"></div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Shift <span class="text-danger">*</span></label>
                <select name="shift" id="shift" class="form-control">
                    <option value="morning">☀️ Morning</option>
                    <option value="evening">🌙 Evening</option>
                </select>
                <div class="field-error" id="shift_error"></div>
            </div>
            <?php endif; ?>
            
            <div class="col-md-3">
                <label class="form-label">Litres <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" name="litre" id="litre" class="form-control" 
                           step="0.01" min="0.1"
                           value="<?= isset($editRow['litre']) ? htmlspecialchars($editRow['litre']) : '' ?>" 
                           placeholder="0.00"
                           oninput="validateField('litre'); calculateTotal()" required>
                    <span class="input-group-text">L</span>
                </div>
                <div class="field-error" id="litre_error"></div>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Fat % <span class="text-danger">*</span></label>
                <input type="number" name="fat" id="fat" class="form-control" 
                       step="0.1" min="0.1" max="15"
                       value="<?= isset($editRow['fat']) ? htmlspecialchars($editRow['fat']) : '' ?>" 
                       placeholder="e.g. 4.5"
                       oninput="validateField('fat'); updateRate(this.value, document.getElementById('snf').value)">
                <div class="field-error" id="fat_error"></div>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">SNF % <span class="text-danger">*</span></label>
                <input type="number" name="snf" id="snf" class="form-control" 
                       step="0.1" min="0.1" max="15"
                       value="<?= isset($editRow['snf']) ? htmlspecialchars($editRow['snf']) : '' ?>" 
                       placeholder="e.g. 8.5"
                       oninput="validateField('snf'); updateRate(document.getElementById('fat').value, this.value)">
                <div class="field-error" id="snf_error"></div>
            </div>
            
            <div class="col-12">
                <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Fat &amp; SNF lock automatically to the farmer's previous record after lookup.</small>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Rate / Litre</label>
                <div class="input-group">
                    <span class="input-group-text">रू</span>
                    <input type="number" id="rate_per_liter" class="form-control bg-light" 
                           step="0.01" readonly
                           value="<?= isset($editRow['rate_per_liter']) ? htmlspecialchars($editRow['rate_per_liter']) : '' ?>" 
                           placeholder="—">
                </div>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Total Amount</label>
                <div class="form-control bg-light fw-bold" id="total_amount" 
                     style="color:var(--teal-900);font-size:1.05rem">—</div>
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
            <input type="text" id="tableSearch" class="search-bar" placeholder="🔍 Search farmer / date..." 
                   onkeyup="filterTable(this.value)">
            <button class="btn btn-sm btn-amber" onclick="printSection('milkTable')">
                <i class="bi bi-printer"></i> Print
            </button>
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
                <?php if ($records && $records->num_rows === 0): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">No milk collection records yet.</td></tr>
                <?php else: ?>
                    <?php $i = 1; while ($row = $records->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($row['code']) ?></small>
                        </td>
                        <td><?= date('d M Y', strtotime($row['collection_date'])) ?></td>
                        <td>
                            <?= $row['shift'] === 'morning' 
                                ? '<span class="shift-morning">☀️ Morning</span>'
                                : '<span class="shift-evening">🌙 Evening</span>' ?>
                        </td>
                        <td><?= number_format($row['litre'], 2) ?> L</td>
                        <td><?= number_format($row['fat'], 1) ?></td>
                        <td><?= number_format($row['snf'], 1) ?></td>
                        <td>रू<?= number_format($row['rate_per_liter'], 2) ?></td>
                        <td><strong style="color:var(--teal-700)">रू<?= number_format($row['total_amount'], 2) ?></strong></td>
                        <td class="no-print">
                            <a href="?action=edit&id=<?= $row['id'] ?>" class="btn-action btn-edit me-1" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="?delete=<?= $row['id'] ?>" class="btn-action btn-delete confirm-delete" title="Delete">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>

<script>
// CSRF token for AJAX requests
const csrfToken = '<?= $_SESSION['csrf_token'] ?>';

// Function to lookup farmer by code
function lookupFarmer(code, infoDiv, farmerIdField) {
    if (!code || code.length < 2) {
        infoDiv.innerHTML = '<div class="text-warning">Please enter at least 2 characters.</div>';
        return;
    }
    
    const xhr = new XMLHttpRequest();
    xhr.open('GET', BASE_URL + 'api/farmer_lookup.php?code=' + encodeURIComponent(code), true);
    xhr.onload = function() {
        if (this.status === 200) {
            try {
                const data = JSON.parse(this.responseText);
                if (data.error) {
                    infoDiv.innerHTML = `<div class="text-danger">${data.error}</div>`;
                    if (farmerIdField) {
                        farmerIdField.value = 0;
                    }
                    return;
                }
                infoDiv.innerHTML = `
                    <div class="farmer-lookup-card">
                        <div class="farmer-photo-placeholder">👨‍🌾</div>
                        <div class="farmer-info">
                            <h5>${data.name || ''}</h5>
                            <span class="farmer-code">${data.code || ''}</span>
                            <p>📞 ${data.phone || 'N/A'}</p>
                        </div>
                    </div>
                `;
                if (farmerIdField) {
                    farmerIdField.value = data.id;
                }
                
                // Auto-fill fat and SNF from the farmer's previous record
                if (data.last_fat) {
                    document.getElementById('fat').value = data.last_fat;
                }
                if (data.last_snf) {
                    document.getElementById('snf').value = data.last_snf;
                }
                
                // Update rate
                updateRate(
                    document.getElementById('fat').value,
                    document.getElementById('snf').value
                );
            } catch (e) {
                infoDiv.innerHTML = '<div class="text-danger">Error parsing response.</div>';
            }
        } else {
            infoDiv.innerHTML = '<div class="text-danger">Error looking up farmer.</div>';
        }
    };
    xhr.onerror = function() {
        infoDiv.innerHTML = '<div class="text-danger">Network error. Please try again.</div>';
    };
    xhr.send();
}

// Function to validate a single field
function validateField(fieldName) {
    const field = document.getElementById(fieldName);
    const errorDiv = document.getElementById(fieldName + '_error');
    if (!field || !errorDiv) return true;
    
    const value = field.value.trim();
    let isValid = true;
    let errorMsg = '';
    
    if (!value) {
        errorMsg = 'This field is required.';
        isValid = false;
    } else if (fieldName === 'litre') {
        if (parseFloat(value) <= 0) {
            errorMsg = 'Litres must be greater than 0.';
            isValid = false;
        }
    } else if (fieldName === 'fat' || fieldName === 'snf') {
        const num = parseFloat(value);
        if (isNaN(num) || num < 0 || num > 15) {
            errorMsg = 'Must be between 0 and 15.';
            isValid = false;
        }
    } else if (fieldName === 'date') {
        const today = new Date().toISOString().split('T')[0];
        if (value > today) {
            errorMsg = 'Date cannot be in the future.';
            isValid = false;
        }
    }
    
    if (!isValid) {
        errorDiv.textContent = errorMsg;
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');
    } else {
        errorDiv.textContent = '';
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
    }
    
    return isValid;
}

// Validate entire form
function validateForm() {
    const fields = ['litre', 'fat', 'snf'];
    if (document.getElementById('date')) {
        fields.push('date');
    }
    
    let isValid = true;
    fields.forEach(field => {
        if (!validateField(field)) {
            isValid = false;
        }
    });
    
    // Check farmer selection for add mode
    if (document.getElementById('farmer_id') && 
        parseInt(document.getElementById('farmer_id').value) < 1) {
        document.getElementById('farmerCodeInput_error').textContent = 'Please select a valid farmer.';
        isValid = false;
    } else if (document.getElementById('farmerCodeInput_error')) {
        document.getElementById('farmerCodeInput_error').textContent = '';
    }
    
    if (!isValid) {
        const alertDiv = document.getElementById('milkFormAlert');
        alertDiv.innerHTML = '<div class="alert alert-danger">Please fix all errors before submitting.</div>';
    }
    
    return isValid;
}

// Update rate based on fat and SNF
function updateRate(fat, snf) {
    fat = parseFloat(fat) || 0;
    snf = parseFloat(snf) || 0;
    
    if (fat <= 0) {
        document.getElementById('rate_per_liter').value = '';
        document.getElementById('total_amount').textContent = '—';
        return;
    }
    
    // Calculate rate (same logic as PHP function)
    let rate = 0;
    if (fat >= 7.0) {
        rate = 120;
    } else if (fat >= 6.0) {
        rate = 110;
    } else if (fat >= 5.0) {
        rate = 100;
    } else if (fat >= 4.5) {
        rate = 90;
    } else if (fat >= 4.0) {
        rate = 80;
    } else if (fat >= 3.5) {
        rate = 70;
    } else {
        rate = 60;
    }
    
    if (snf >= 8.5) {
        rate += 10;
    } else if (snf >= 8.0) {
        rate += 5;
    }
    
    document.getElementById('rate_per_liter').value = rate.toFixed(2);
    calculateTotal();
}

// Calculate total amount
function calculateTotal() {
    const litres = parseFloat(document.getElementById('litre').value) || 0;
    const rate = parseFloat(document.getElementById('rate_per_liter').value) || 0;
    
    if (litres > 0 && rate > 0) {
        const total = litres * rate;
        document.getElementById('total_amount').textContent = 'रू' + total.toFixed(2);
    } else {
        document.getElementById('total_amount').textContent = '—';
    }
}

// Filter table
function filterTable(searchTerm) {
    const rows = document.querySelectorAll('#milkTable tbody tr');
    const term = searchTerm.toLowerCase();
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
}

// Print function
function printSection(elementId) {
    const printContents = document.getElementById(elementId).innerHTML;
    const originalContents = document.body.innerHTML;
    
    document.body.innerHTML = `
        <html>
            <head>
                <title>Milk Collection Records</title>
                <style>
                    table { width: 100%; border-collapse: collapse; }
                    th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
                    th { background-color: #f5f5f5; }
                    .no-print { display: none; }
                </style>
            </head>
            <body>
                <h2>Milk Collection Records</h2>
                <p>Generated on: ${new Date().toLocaleString()}</p>
                ${printContents}
            </body>
        </html>
    `;
    
    window.print();
    document.body.innerHTML = originalContents;
    // Re-initialize event listeners
    location.reload();
}

// Delete confirmation is handled by ui.js (loaded via footer).
document.addEventListener('DOMContentLoaded', function() {
    // Auto-calculate on page load if in edit mode
    if (document.getElementById('litre') && document.getElementById('rate_per_liter')) {
        calculateTotal();
    }
    
    // Trigger validation on blur
    document.querySelectorAll('.form-control').forEach(field => {
        field.addEventListener('blur', function() {
            if (this.id) {
                validateField(this.id);
            }
        });
    });
});

// Handle Enter key in search
document.getElementById('tableSearch')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        filterTable(this.value);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>