// Shree Tri Shakti Dairy - validation.js
// Live client-side validation for the milk entry form and helpers
// for other forms. Depends on: helpers.js, milk-entry.js.


// Validate a single milk-entry field live.
function validateField(fieldId) {
    const input = document.getElementById(fieldId);
    if (!input) return true;
    const val = (input.value || '').trim();

    switch (fieldId) {

        case 'date': {
            if (isEmpty(val)) {
                showFieldError('date', 'Please select a date.');
                return false;
            }
            if (val > todayStr()) {
                showFieldError('date', 'Future date not allowed — only today or past dates.');
                return false;
            }
            showFieldOk('date');
            return true;
        }

        case 'shift': {
            if (val !== 'morning' && val !== 'evening') {
                showFieldError('shift', 'Please select a time slot (Morning or Evening).');
                return false;
            }
            showFieldOk('shift');
            return true;
        }

        case 'litre': {
            if (isEmpty(val)) {
                showFieldError('litre', 'Please enter the milk quantity.');
                return false;
            }
            const n = parseFloat(val);
            if (isNaN(n)) {
                showFieldError('litre', 'Please enter a valid number for milk.');
                return false;
            }
            if (n <= 0) {
                showFieldError('litre', 'Milk quantity must be greater than 0 — -1 is not valid.');
                return false;
            }
            if (n > 200) {
                showFieldError('litre', 'Milk quantity looks too high (max 200 litres).');
                return false;
            }
            showFieldOk('litre');
            return true;
        }

        case 'fat': {
            if (isEmpty(val)) {
                showFieldError('fat', 'Please enter the Fat %.');
                return false;
            }
            const n = parseFloat(val);
            if (isNaN(n)) {
                showFieldError('fat', 'Please enter a valid number for Fat %.');
                return false;
            }
            if (n <= 0) {
                showFieldError('fat', 'Fat % must be greater than 0.');
                return false;
            }
            if (n > 15) {
                showFieldError('fat', 'Fat % looks too high (max 15%).');
                return false;
            }
            showFieldOk('fat');
            return true;
        }

        case 'snf': {
            if (isEmpty(val)) {
                showFieldError('snf', 'Please enter the SNF %.');
                return false;
            }
            const n = parseFloat(val);
            if (isNaN(n)) {
                showFieldError('snf', 'Please enter a valid number for SNF %.');
                return false;
            }
            if (n <= 0) {
                showFieldError('snf', 'SNF % must be greater than 0.');
                return false;
            }
            if (n > 15) {
                showFieldError('snf', 'SNF % looks too high (max 15%).');
                return false;
            }
            showFieldOk('snf');
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
        showFieldError('farmerCodeInput', 'Please enter a farmer code.');
        if (farmerId) farmerId.value = '';
        selectedFarmerId = null;
        return false;
    }
    if (!selectedFarmerId) {
        showFieldError('farmerCodeInput', 'Unknown farmer code — press Lookup and pick a real farmer.');
        if (farmerId) farmerId.value = '';
        return false;
    }
    showFieldOk('farmerCodeInput');
    return true;
}


// Small helper for other (non-milk) forms: checks required fields.
function validateForm(form) {
    const inputs = form.querySelectorAll(
        'input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea'
    );
    for (let input of inputs) {
        if (input.hasAttribute('required') && isEmpty(input.value)) {
            showFieldError(input.id || input.name, 'This field is required.');
            return false;
        }
    }
    return true;
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