// Shree Tri Shakti Dairy - Main JavaScript


// Hide alerts automatically after 4 seconds
document.addEventListener('DOMContentLoaded', function () {

    setTimeout(function () {
        document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        });
    }, 4000);


    // Search table rows
    const search = document.getElementById('tableSearch');

    if (search) {
        search.addEventListener('input', function () {

            document.querySelectorAll('.searchable-table tbody tr').forEach(function (row) {

                if (row.textContent.toLowerCase().includes(search.value.toLowerCase())) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }

            });
        });
    }


    // Ask before deleting
    document.querySelectorAll('.confirm-delete').forEach(function (button) {

        button.addEventListener('click', function (event) {

            if (!confirm('Are you sure you want to delete this record?')) {
                event.preventDefault();
            }

        });

    });


    // Convert farmer code to uppercase
    document.querySelectorAll('.code-input').forEach(function (input) {

        input.addEventListener('input', function () {
            input.value = input.value.toUpperCase();
        });

    });

});


// Find farmer using farmer code
function lookupFarmer(code, targetDiv) {

    if (!code || code.length < 2) {
        targetDiv.innerHTML = '';
        return;
    }

    fetch('/dairy/api/farmer_lookup.php?code=' + encodeURIComponent(code))
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {

            if (data.error) {
                targetDiv.innerHTML =
                    '<div class="alert alert-danger py-2">' +
                    data.error +
                    '</div>';

                return;
            }


            // Show farmer information
            targetDiv.innerHTML = `
                <div class="card bg-success text-white p-3 mb-3">
                    <div class="d-flex align-items-center gap-3">

                        ${
                            data.photo
                            ? `<img src="/dairy/${data.photo}" width="80" height="80"
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


            // Update rate if FAT is already entered
            const fat = document.getElementById('fat');

            if (fat && fat.value) {
                updateRate(fat.value);
            }

        })
        .catch(function () {

            targetDiv.innerHTML =
                '<div class="alert alert-danger py-2">' +
                'Lookup failed. Please try again.' +
                '</div>';

        });
}


// Get milk rate using FAT and SNF
function updateRate(fat) {

    if (!fat) {
        return;
    }

    const snf = document.getElementById('snf').value || 0;

    fetch('/dairy/api/get_rate.php?fat=' + fat + '&snf=' + snf)
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


// Calculate total milk price
function calculateTotal() {

    const litre = parseFloat(document.getElementById('litre').value) || 0;
    const rate = parseFloat(document.getElementById('rate_per_liter').value) || 0;

    document.getElementById('total_amount').textContent =
        'रू ' + (litre * rate).toFixed(2);
}


// Print a section of the page
function printSection(id) {

    const content = document.getElementById(id).innerHTML;

    const printWindow = window.open('', '_blank');

    printWindow.document.write(`
        <html>
        <head>

            <title>Shree Tri Shakti Dairy</title>

            <link
                href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
                rel="stylesheet"
            >

        </head>

        <body class="p-4">

            ${content}

        </body>
        </html>
    `);

    printWindow.document.close();

    setTimeout(function () {
        printWindow.print();
        printWindow.close();
    }, 500);
}


// Check form before submitting
function validateForm(form) {

    // Check all required inputs
    const inputs = form.querySelectorAll(
        'input:not([type=hidden]), select, textarea'
    );

    for (let input of inputs) {

        if (!input.checkValidity()) {

            input.classList.add('is-invalid');

            alert('Please enter a valid value in ' + input.name);

            return false;
        }

    }


    // Check required hidden fields
    const hiddenInputs = form.querySelectorAll(
        'input[type=hidden][required]'
    );

    for (let input of hiddenInputs) {

        if (!input.value) {

            alert('Please select a farmer first.');

            return false;
        }

    }


    // Check password
    const password = form.querySelector('input[type=password]');

    if (password) {

        if (
            password.value.length < 6 ||
            password.value.length > 8
        ) {
            alert('Password must be 6-8 characters.');

            return false;
        }

    }


    return true;
}


// Validate forms before submitting
document.addEventListener('submit', function (event) {

    if (!validateForm(event.target)) {
        event.preventDefault();
    }

});