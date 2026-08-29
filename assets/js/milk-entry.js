// Shree Tri Shakti Dairy - milk-entry.js
// Everything specific to the Milk Collection page (add / edit).
// Depends on: helpers.js


// Farmer selected by the code lookup (a valid farmer must be picked).
let selectedFarmerId = null;


// Find a farmer by code and show their card.
// Supports 2-arg (code, targetDiv) or 3-arg (code, targetDiv, farmerIdField) for backward compat.
function lookupFarmer(code, targetDiv, farmerIdField) {

    if (!code || code.length < 2) {
        targetDiv.innerHTML = '<div class="text-warning">Please enter at least 2 characters.</div>';
        return;
    }

    fetch(window.BASE_URL + 'api/farmer_lookup.php?code=' + encodeURIComponent(code))
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {

            if (data.error) {
                targetDiv.innerHTML = `<div class="text-danger">${data.error}</div>`;
                selectedFarmerId = null;
                if (farmerIdField) {
                    farmerIdField.value = 0;
                }
                return;
            }

            // Remember the farmer that was actually looked up.
            selectedFarmerId = data.id;

            // Show farmer information - matching the inline version style
            const photoHtml = data.photo
                ? `<img src="${window.BASE_URL}${data.photo}" class="farmer-photo" alt="${data.name}">`
                : `<div class="farmer-photo-placeholder">👨‍🌾</div>`;
            
            targetDiv.innerHTML = `
                <div class="farmer-lookup-card">
                    ${photoHtml}
                    <div class="farmer-info">
                        <h5>${data.name}</h5>
                        <span class="farmer-code">${data.code}</span>
                        <p>📞 ${data.phone || 'N/A'} &nbsp;|&nbsp; 📍 ${data.address || 'N/A'}</p>
                    </div>
                </div>
            `;

            // Put farmer ID into hidden input
            const farmerId = farmerIdField || document.getElementById('farmer_id');
            if (farmerId) {
                farmerId.value = data.id;
            }

            // Lock the farmer's fat & snf to their last-known values
            const fatInput = document.getElementById('fat');
            const snfInput = document.getElementById('snf');

            if (fatInput && snfInput) {
                if (data.last_fat != null && data.last_snf != null) {
                    fatInput.value = data.last_fat;
                    snfInput.value = data.last_snf;
                    fatInput.readOnly = true;
                    snfInput.readOnly = true;
                    fatInput.title = 'Locked to this farmer\u2019s previous record';
                    snfInput.title = 'Locked to this farmer\u2019s previous record';
                    showFieldOk('fat');
                    showFieldOk('snf');
                } else {
                    // First time: let the staff enter once, then it locks.
                    fatInput.readOnly = false;
                    snfInput.readOnly = false;
                    fatInput.title = '';
                    snfInput.title = '';
                }

                // Recalculate the rate with the (now set) fat/snf values.
                if (fatInput.value) {
                    updateRate(fatInput.value);
                }
            }

            // Fire custom event with farmer data for pages that need extra info (e.g., dana.php)
            const event = new CustomEvent('farmerLoaded', { detail: data });
            document.dispatchEvent(event);

        })
        .catch(function () {

            selectedFarmerId = null;
            targetDiv.innerHTML =
                '<div class="alert alert-danger py-2">' +
                'Lookup failed. Please try again.' +
                '</div>';

        });
}


// Get milk rate using FAT and SNF via API.
function updateRate(fat) {

    if (!fat) {
        return;
    }

    const snf = document.getElementById('snf').value || 0;

    fetch(window.BASE_URL + 'api/get_rate.php?fat=' + fat + '&snf=' + snf)
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {

            const rate = document.getElementById('rate_per_liter');

            if (rate) {
                rate.value = data.rate;
                calculateTotal();
            }

        });
}


// Calculate total milk price (litres x rate).
function calculateTotal() {

    const litre = parseFloat(document.getElementById('litre').value) || 0;
    const rate = parseFloat(document.getElementById('rate_per_liter').value) || 0;

    const totalEl = document.getElementById('total_amount');
    if (totalEl) {
        if (litre > 0 && rate > 0) {
            totalEl.textContent = 'रू ' + (litre * rate).toFixed(2);
        } else {
            totalEl.textContent = '—';
        }
    }
}


// Validate the milk entry form before submit
function validateMilkEntryForm() {
    const mode = document.getElementById('date') ? 'add' : 'update';
    let isValid = true;

    // For add mode, check farmer selection
    if (mode === 'add') {
        const farmerId = document.getElementById('farmer_id');
        if (!farmerId || parseInt(farmerId.value) < 1) {
            showFieldErrorBootstrap('farmerCodeInput', 'Please lookup and select a valid farmer first.');
            isValid = false;
        } else {
            showFieldOkBootstrap('farmerCodeInput');
        }
    }

    // Validate all milk fields
    const fields = ['litre', 'fat', 'snf'];
    if (mode === 'add') {
        fields.push('date', 'shift');
    }

    fields.forEach(fieldName => {
        if (!validateField(fieldName)) {
            isValid = false;
        }
    });

    if (!isValid) {
        const alertBox = document.getElementById('milkFormAlert');
        showResultAlert(alertBox, 'danger', 'Please fix all errors before submitting.');
    }

    return isValid;
}


// Save a milk entry (add or update) using fetch() and show the server reply.
function submitMilkEntry(form) {
    if (!validateMilkEntryForm()) {
        return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    const mode = submitBtn && submitBtn.name === 'update' ? 'update' : 'add';

    const data = new FormData();
    data.append('action', mode);

    if (mode === 'add') {
        data.append('farmer_id', document.getElementById('farmer_id').value);
        data.append('date', document.getElementById('date').value);
        data.append('shift', document.getElementById('shift').value);
    } else {
        data.append('id', form.querySelector('[name="id"]').value);
    }

    data.append('litre', document.getElementById('litre').value);
    data.append('fat', document.getElementById('fat').value);
    data.append('snf', document.getElementById('snf').value || 0);

    const alertBox = document.getElementById('milkFormAlert');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Saving...';
    }

    fetch(window.BASE_URL + 'api/milk_save.php', { method: 'POST', body: data })
        .then(function (response) {
            return response.json();
        })
        .then(function (result) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML =
                    '<i class="bi bi-check2-circle me-2"></i>' +
                    (mode === 'update' ? 'Update Record' : 'Save Entry');
            }

            if (result.ok) {
                showResultAlert(alertBox, 'success', result.msg || 'Saved successfully.');
                setTimeout(function () {
                    window.location.href = window.BASE_URL + 'pages/milk_collection.php';
                }, 1000);
            } else {
                showResultAlert(alertBox, 'danger', result.error || 'Could not save the record. Please check the values.');
            }
        })
        .catch(function () {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML =
                    '<i class="bi bi-check2-circle me-2"></i>' +
                    (mode === 'update' ? 'Update Record' : 'Save Entry');
            }
            showResultAlert(alertBox, 'danger', 'Network error — could not reach the server. Please try again.');
        });
}


// For backward compatibility with dana.php which calls validateForm()
function validateForm() {
    return validateMilkEntryForm();
}


// Initialize milk entry form - attach event listeners
function initMilkEntryForm() {
    const form = document.getElementById('milkEntryForm');
    if (!form) return;

    // Auto-calculate on page load if in edit mode
    if (document.getElementById('litre') && document.getElementById('rate_per_liter')) {
        calculateTotal();
    }

    // Farmer code lookup - for milk_collection.php and dana.php
    const farmerCodeInput = document.getElementById('farmerCodeInput');
    const lookupBtn = document.getElementById('lookupFarmerBtn');
    const farmerInfoDiv = document.getElementById('farmerInfo');

    if (farmerCodeInput && farmerInfoDiv) {
        // Live lookup on input (debounced)
        let lookupTimer;
        farmerCodeInput.addEventListener('input', function() {
            clearTimeout(lookupTimer);
            lookupTimer = setTimeout(() => {
                const code = this.value.trim().toUpperCase();
                if (code.length >= 2) {
                    lookupFarmer(code, farmerInfoDiv);
                } else {
                    farmerInfoDiv.innerHTML = '';
                }
            }, 300);
        });

        // Lookup button click
        if (lookupBtn) {
            lookupBtn.addEventListener('click', function() {
                const code = farmerCodeInput.value.trim().toUpperCase();
                if (code.length >= 2) {
                    lookupFarmer(code, farmerInfoDiv);
                } else {
                    farmerInfoDiv.innerHTML = '<div class="text-warning">Please enter at least 2 characters.</div>';
                }
            });
        }
    }

    // Trigger validation on blur for milk entry fields
    const milkFields = ['litre', 'fat', 'snf', 'date', 'shift'];
    milkFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (field) {
            field.addEventListener('blur', function() {
                validateField(fieldName);
            });
            field.addEventListener('input', function() {
                // Real-time validation feedback
                validateField(fieldName);
                // Update rate when fat or snf changes
                if (fieldName === 'fat' || fieldName === 'snf') {
                    const fatVal = document.getElementById('fat')?.value;
                    if (fatVal) updateRate(fatVal);
                }
                if (fieldName === 'litre') calculateTotal();
            });
        }
    });

    // Form submit handler - use AJAX
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        if (validateForm()) {
            submitMilkEntry(form);
        }
    });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initMilkEntryForm();
});