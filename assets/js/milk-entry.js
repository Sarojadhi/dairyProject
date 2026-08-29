// Shree Tri Shakti Dairy - milk-entry.js
// Everything specific to the Milk Collection page (add / edit).
// Depends on: helpers.js


// Farmer selected by the code lookup (a valid farmer must be picked).
let selectedFarmerId = null;


// Find a farmer by code and show their card.
function lookupFarmer(code, targetDiv) {

    if (!code || code.length < 2) {
        targetDiv.innerHTML = '';
        return;
    }

    fetch(window.BASE_URL + 'api/farmer_lookup.php?code=' + encodeURIComponent(code))
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {

            if (data.error) {
                targetDiv.innerHTML =
                    '<div class="alert alert-danger py-2">' +
                    data.error +
                    '</div>';
                selectedFarmerId = null;
                return;
            }

            // Remember the farmer that was actually looked up.
            selectedFarmerId = data.id;

            // Show farmer information
            targetDiv.innerHTML = `
                <div class="card bg-success text-white p-3 mb-3">
                    <div class="d-flex align-items-center gap-3">

                        ${
                            data.photo
                            ? `<img src="${window.BASE_URL}${data.photo}" width="80" height="80"
                                class="rounded-circle" alt="${data.name}">`
                            : `<div class="fs-1">👨‍🌾</div>`
                        }

                        <div>
                            <h5 class="mb-1">${data.name}</h5>

                            <span class="badge bg-warning text-dark">
                                ${data.code}
                            </span>

                            <p class="mb-0 mt-2">
                                📞 ${data.phone || 'N/A'}
                                &nbsp; | &nbsp;
                                📍 ${data.address || 'N/A'}
                            </p>

                            <p class="mb-0">
                                Status:
                                ${
                                    data.is_active == 1
                                    ? '<span class="badge bg-light text-success">Active</span>'
                                    : '<span class="badge bg-secondary">Inactive</span>'
                                }
                            </p>
                        </div>

                    </div>
                </div>
            `;

            // Put farmer ID into hidden input
            const farmerId = document.getElementById('farmer_id');

            if (farmerId) {
                farmerId.value = data.id;
            }

            // Lock the farmer's fat & snf to their last-known values so the
            // same code always uses the same fat/snf (no manual changes).
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

        })
        .catch(function () {

            selectedFarmerId = null;
            targetDiv.innerHTML =
                '<div class="alert alert-danger py-2">' +
                'Lookup failed. Please try again.' +
                '</div>';

        });
}


// Get milk rate using FAT and SNF.
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

    document.getElementById('total_amount').textContent =
        'रू ' + (litre * rate).toFixed(2);
}


// Save a milk entry (add or update) using fetch() and show the server reply.
function submitMilkEntry(form) {
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