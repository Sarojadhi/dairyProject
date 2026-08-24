// Shree Tri Shakti Dairy — Main JS

// Auto-dismiss alerts
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(() => {
        document.querySelectorAll('.alert-dismissible').forEach(el => {
            let bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            bsAlert.close();
        });
    }, 4000);

    // Table search
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.searchable-table tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // Confirm deletes
    document.querySelectorAll('.confirm-delete').forEach(btn => {
        btn.addEventListener('click', function (e) {
            if (!confirm('Are you sure you want to delete this record? This cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // Code input auto-uppercase
    document.querySelectorAll('.code-input').forEach(input => {
        input.addEventListener('input', function () {
            this.value = this.value.toUpperCase();
        });
    });
});

// Farmer lookup by code (AJAX)
function lookupFarmer(code, targetDiv) {
    if (!code || code.length < 2) {
        targetDiv.innerHTML = '';
        return;
    }
    fetch('/dairy/api/farmer_lookup.php?code=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                targetDiv.innerHTML = `<div class="alert alert-danger py-2 mb-0">❌ ${data.error}</div>`;
                return;
            }
            const photo = data.photo
                ? `<img src="/dairy/${data.photo}" class="farmer-photo" alt="${data.name}">`
                : `<div class="farmer-photo-placeholder">👨‍🌾</div>`;
            targetDiv.innerHTML = `
                <div class="farmer-lookup-card">
                    ${photo}
                    <div class="farmer-info">
                        <h5>${data.name}</h5>
                        <span class="farmer-code">${data.code}</span>
                        <p>📞 ${data.phone || 'N/A'} &nbsp;|&nbsp; 📍 ${data.address || 'N/A'}</p>
                        <p>Status: <span class="${data.is_active == 1 ? 'badge-active' : 'badge-inactive'}">${data.is_active == 1 ? 'Active' : 'Inactive'}</span></p>
                    </div>
                </div>`;
            // Set hidden farmer_id
            const hiddenId = document.getElementById('farmer_id');
            if (hiddenId) hiddenId.value = data.id;
            // Auto-fetch rate
            const fatInput = document.getElementById('fat');
            if (fatInput && fatInput.value) updateRate(fatInput.value);
        })
        .catch(() => {
            targetDiv.innerHTML = `<div class="alert alert-danger py-2 mb-0">❌ Lookup failed</div>`;
        });
}

// Auto-calculate rate based on fat and snf
function updateRate(fat) {
    if (!fat) return;
    const snf = document.getElementById('snf')?.value || 0;
    fetch('/dairy/api/get_rate.php?fat=' + encodeURIComponent(fat) + '&snf=' + encodeURIComponent(snf))
        .then(r => r.json())
        .then(data => {
            const rateField = document.getElementById('rate_per_liter');
            if (rateField && data.rate) {
                rateField.value = data.rate;
                calculateTotal();
            }
        });
}

function calculateTotal() {
    const litre = parseFloat(document.getElementById('litre')?.value) || 0;
    const rate = parseFloat(document.getElementById('rate_per_liter')?.value) || 0;
    const totalEl = document.getElementById('total_amount');
    if (totalEl) totalEl.textContent = 'रू ' + (litre * rate).toFixed(2);
}

// Print
function printSection(id) {
    const content = document.getElementById(id).innerHTML;
    const win = window.open('', '_blank');
    win.document.write(`<!DOCTYPE html><html><head>
        <title>Print - Shree Tri Shakti Dairy</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="/dairy/assets/css/style.css">
        <style>body{padding:20px;} .no-print{display:none!important;}</style>
    </head><body>${content}</body></html>`);
    win.document.close();
    setTimeout(() => { win.print(); win.close(); }, 500);
}
