// Shree Tri Shakti Dairy - ui.js
// Page-level behaviour: auto-hide alerts, table search,
// delete confirmation, code auto-uppercase, printing.


document.addEventListener('DOMContentLoaded', function () {

    // Hide alerts automatically after 4 seconds
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