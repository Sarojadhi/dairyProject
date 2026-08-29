// Shree Tri Shakti Dairy - validation.js
// Live client-side validation for the milk entry form and helpers
// for other forms. Depends on: helpers.js


// Validate a single milk-entry field live.
function validateField(fieldId) {
    const input = document.getElementById(fieldId);
    if (!input) return true;
    const val = (input.value || '').trim();
    const errorDiv = document.getElementById(fieldId + '_error');

    switch (fieldId) {

        case 'date': {
            if (isEmpty(val)) {
                showFieldErrorBootstrap(fieldId, 'Please select a date.');
                return false;
            }
            if (val > todayStr()) {
                showFieldErrorBootstrap(fieldId, 'Future date not allowed — only today or past dates.');
                return false;
            }
            showFieldOkBootstrap(fieldId);
            return true;
        }

        case 'shift': {
            if (val !== 'morning' && val !== 'evening') {
                showFieldErrorBootstrap(fieldId, 'Please select a time slot (Morning or Evening).');
                return false;
            }
            showFieldOkBootstrap(fieldId);
            return true;
        }

        case 'litre': {
            if (isEmpty(val)) {
                showFieldErrorBootstrap(fieldId, 'Please enter the milk quantity.');
                return false;
            }
            const n = parseFloat(val);
            if (isNaN(n)) {
                showFieldErrorBootstrap(fieldId, 'Please enter a valid number for milk.');
                return false;
            }
            if (n <= 0) {
                showFieldErrorBootstrap(fieldId, 'Milk quantity must be greater than 0.');
                return false;
            }
            if (n > 200) {
                showFieldErrorBootstrap(fieldId, 'Milk quantity looks too high (max 200 litres).');
                return false;
            }
            showFieldOkBootstrap(fieldId);
            return true;
        }

        case 'fat': {
            if (isEmpty(val)) {
                showFieldErrorBootstrap(fieldId, 'Please enter the Fat %.');
                return false;
            }
            const n = parseFloat(val);
            if (isNaN(n)) {
                showFieldErrorBootstrap(fieldId, 'Please enter a valid number for Fat %.');
                return false;
            }
            if (n <= 0) {
                showFieldErrorBootstrap(fieldId, 'Fat % must be greater than 0.');
                return false;
            }
            if (n > 15) {
                showFieldErrorBootstrap(fieldId, 'Fat % looks too high (max 15%).');
                return false;
            }
            showFieldOkBootstrap(fieldId);
            return true;
        }

        case 'snf': {
            if (isEmpty(val)) {
                showFieldErrorBootstrap(fieldId, 'Please enter the SNF %.');
                return false;
            }
            const n = parseFloat(val);
            if (isNaN(n)) {
                showFieldErrorBootstrap(fieldId, 'Please enter a valid number for SNF %.');
                return false;
            }
            if (n <= 0) {
                showFieldErrorBootstrap(fieldId, 'SNF % must be greater than 0.');
                return false;
            }
            if (n > 15) {
                showFieldErrorBootstrap(fieldId, 'SNF % looks too high (max 15%).');
                return false;
            }
            showFieldOkBootstrap(fieldId);
            return true;
        }
    }

    return true;
}


// Validate the farmer code in add mode: it must match a real farmer.
function validateFarmerCode() {
    const codeInput = document.getElementById('farmerCodeInput');
    const code = (codeInput ? codeInput.value : '').trim();
    const farmerId = document.getElementById('farmer_id');

    if (!codeInput) return true; // edit mode - skip

    if (isEmpty(code)) {
        showFieldErrorBootstrap('farmerCodeInput', 'Please enter a farmer code.');
        if (farmerId) farmerId.value = '';
        selectedFarmerId = null;
        return false;
    }
    if (!selectedFarmerId) {
        showFieldErrorBootstrap('farmerCodeInput', 'Unknown farmer code — press Lookup and pick a real farmer.');
        if (farmerId) farmerId.value = '';
        return false;
    }
    showFieldOkBootstrap('farmerCodeInput');
    return true;
}


// Small helper for other (non-milk) forms: checks required fields.
function validateForm(form) {
    const inputs = form.querySelectorAll(
        'input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea'
    );
    for (let input of inputs) {
        if (input.hasAttribute('required') && isEmpty(input.value)) {
            showFieldErrorBootstrap(input.id || input.name, 'This field is required.');
            return false;
        }
    }
    return true;
}


// Bootstrap-compatible field error display
function showFieldErrorBootstrap(fieldId, msg) {
    const input = document.getElementById(fieldId);
    const errBox = document.getElementById(fieldId + '_error');
    if (input) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    }
    if (errBox) {
        errBox.textContent = msg;
    }
}

function showFieldOkBootstrap(fieldId) {
    const input = document.getElementById(fieldId);
    const errBox = document.getElementById(fieldId + '_error');
    if (input) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
    }
    if (errBox) {
        errBox.textContent = '';
    }
}


// Intercept every form submit: block invalid forms, save milk via JS.
document.addEventListener('submit', function (event) {
    const form = event.target;

    // Milk Collection page has its own self-contained form + submit handler;
    // it does NOT use this shared auto-save. Other forms just use native
    // validation without disruptive alerts.
    if (!form.checkValidity()) {
        event.preventDefault();
        const invalid = form.querySelector(':invalid');
        if (invalid) invalid.focus();
    }
});