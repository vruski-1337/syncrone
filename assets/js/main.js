// Pharma Care - Main JS

document.addEventListener('DOMContentLoaded', function () {

    // ─── Sidebar toggle (mobile) ───────────────────────────────────
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }

    // ─── Auto-dismiss alerts after 5 s ─────────────────────────────
    document.querySelectorAll('.alert-dismissible.auto-dismiss').forEach(function (el) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            bsAlert.close();
        }, 5000);
    });

    // ─── Sale Add – dynamic rows ────────────────────────────────────
    const addRowBtn = document.getElementById('addRowBtn');
    if (addRowBtn) {
        addRowBtn.addEventListener('click', addProductRow);
        // init first row listeners
        initRowListeners();
        updateTotals();
    }

    // Sale form discount field
    const discountField = document.getElementById('discount');
    if (discountField) {
        discountField.addEventListener('input', updateTotals);
    }

    // ─── Logo preview ────────────────────────────────────────────────
    const logoInput = document.getElementById('logo');
    const logoPreview = document.getElementById('logoPreview');
    if (logoInput && logoPreview) {
        logoInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                logoPreview.src = URL.createObjectURL(file);
                logoPreview.classList.remove('d-none');
            }
        });
    }
});

// ─── Product Row Functions ─────────────────────────────────────────────────

let rowIndex = 1;

function addProductRow() {
    const tbody = document.getElementById('saleItemsBody');
    const template = document.getElementById('rowTemplate');
    if (!tbody || !template) return;

    const newRow = template.content.cloneNode(true);
    const row = newRow.querySelector('tr');
    row.dataset.index = rowIndex;

    // Update name attributes
    row.querySelectorAll('[data-name]').forEach(function (el) {
        el.name = el.dataset.name + '[' + rowIndex + ']';
    });

    tbody.appendChild(row);
    initRowListeners();
    rowIndex++;
    updateTotals();
}

function initRowListeners() {
    document.querySelectorAll('.product-select').forEach(function (sel) {
        if (!sel.dataset.initialized) {
            sel.dataset.initialized = 'true';
            sel.addEventListener('change', function () {
                const row = this.closest('tr');
                const price = this.options[this.selectedIndex]?.dataset.price || '0';
                const priceInput = row.querySelector('.unit-price');
                if (priceInput) priceInput.value = parseFloat(price).toFixed(2);
                calcRowSubtotal(row);
            });
        }
    });

    document.querySelectorAll('.qty-input, .unit-price').forEach(function (inp) {
        if (!inp.dataset.initialized) {
            inp.dataset.initialized = 'true';
            inp.addEventListener('input', function () {
                calcRowSubtotal(this.closest('tr'));
            });
        }
    });

    document.querySelectorAll('.remove-row').forEach(function (btn) {
        if (!btn.dataset.initialized) {
            btn.dataset.initialized = 'true';
            btn.addEventListener('click', function () {
                const rows = document.querySelectorAll('#saleItemsBody tr');
                if (rows.length > 1) {
                    this.closest('tr').remove();
                    updateTotals();
                }
            });
        }
    });
}

function calcRowSubtotal(row) {
    const qty   = parseFloat(row.querySelector('.qty-input')?.value) || 0;
    const price = parseFloat(row.querySelector('.unit-price')?.value) || 0;
    const sub   = qty * price;
    const subEl = row.querySelector('.row-subtotal');
    if (subEl) subEl.textContent = '$' + sub.toFixed(2);
    const subInput = row.querySelector('.subtotal-input');
    if (subInput) subInput.value = sub.toFixed(2);
    updateTotals();
}

function updateTotals() {
    let total = 0;
    document.querySelectorAll('.subtotal-input').forEach(function (el) {
        total += parseFloat(el.value) || 0;
    });

    const discount = parseFloat(document.getElementById('discount')?.value) || 0;
    const final    = Math.max(0, total - discount);

    const elTotal   = document.getElementById('totalAmount');
    const elFinal   = document.getElementById('finalAmount');
    const hidTotal  = document.getElementById('hiddenTotal');
    const hidFinal  = document.getElementById('hiddenFinal');

    if (elTotal)  elTotal.textContent  = '$' + total.toFixed(2);
    if (elFinal)  elFinal.textContent  = '$' + final.toFixed(2);
    if (hidTotal) hidTotal.value       = total.toFixed(2);
    if (hidFinal) hidFinal.value       = final.toFixed(2);
}
