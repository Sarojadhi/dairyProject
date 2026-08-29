// Shree Tri Shakti Dairy - helpers.js
// Shared utility functions used by every other script.


// True when a value is empty or only whitespace.
function isEmpty(v) {
    return (v || '').trim() === '';
}


// Local date string for "today" comparisons (YYYY-MM-DD).
function todayStr() {
    const d = new Date();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return d.getFullYear() + '-' + mm + '-' + dd;
}


// Mark a field as invalid and show its inline error message.
function showFieldError(fieldId, msg) {
    const input = document.getElementById(fieldId);
    const errBox = document.getElementById(fieldId + '_error');
    if (input) input.classList.add('invalid');
    if (errBox) {
        errBox.textContent = msg;
        errBox.classList.add('show');
    }
}


// Clear a field's invalid state and hide its inline error.
function showFieldOk(fieldId) {
    const input = document.getElementById(fieldId);
    const errBox = document.getElementById(fieldId + '_error');
    if (input) input.classList.remove('invalid');
    if (errBox) {
        errBox.textContent = '';
        errBox.classList.remove('show');
    }
}


// Show a Bootstrap-style result message inside a target element.
function showResultAlert(box, type, message) {
    if (!box) return;
    box.innerHTML =
        '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
        message +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
        '</div>';
}