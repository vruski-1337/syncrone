<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();
requireAnyRole(['manager', 'owner']);

$pageTitle  = 'Bulk Add Products';
$activePage = 'product-bulk-add';
$cid        = (int)$_SESSION['company_id'];
$footer     = getFooterContent($conn);
$errors     = [];

$catStmt = $conn->prepare('SELECT id, name FROM categories WHERE company_id = ? ORDER BY name');
$catStmt->bind_param('i', $cid);
$catStmt->execute();
$categories = $catStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$catStmt->close();

$unitStmt = $conn->prepare('SELECT id, name, abbreviation FROM units WHERE company_id = ? ORDER BY name');
$unitStmt->bind_param('i', $cid);
$unitStmt->execute();
$units = $unitStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$unitStmt->close();

$categoryByName = [];
foreach ($categories as $c) {
    $categoryByName[strtolower(trim((string)$c['name']))] = (int)$c['id'];
}

$unitByName = [];
foreach ($units as $u) {
    $unitByName[strtolower(trim((string)$u['name']))] = (int)$u['id'];
    if (!empty($u['abbreviation'])) {
        $unitByName[strtolower(trim((string)$u['abbreviation']))] = (int)$u['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $names       = $_POST['name'] ?? [];
        $manufacturers = $_POST['manufacturer'] ?? [];
        $batchNumbers  = $_POST['batch_number'] ?? [];
        $catIds      = $_POST['category_id'] ?? [];
        $unitIds     = $_POST['unit_id'] ?? [];
        $purchase    = $_POST['purchase_price'] ?? [];
        $selling     = $_POST['selling_price'] ?? [];
        $stock       = $_POST['stock_quantity'] ?? [];
        $threshold   = $_POST['low_stock_threshold'] ?? [];
        $expiryDates = $_POST['expiry_date'] ?? [];
        $descs       = $_POST['description'] ?? [];

        $rows = [];
        foreach ($names as $i => $rowName) {
            $name = trim((string)$rowName);
            $manufacturer = trim((string)($manufacturers[$i] ?? ''));
            $batchNumber  = trim((string)($batchNumbers[$i] ?? ''));
            $catId = (int)($catIds[$i] ?? 0) ?: null;
            $unitId = (int)($unitIds[$i] ?? 0) ?: null;
            $purchasePrice = (float)($purchase[$i] ?? 0);
            $sellingPrice  = (float)($selling[$i] ?? 0);
            $stockQty      = (float)($stock[$i] ?? 0);
            $lowThreshold  = (float)($threshold[$i] ?? 10);
            $expiry        = trim((string)($expiryDates[$i] ?? ''));
            $desc          = trim((string)($descs[$i] ?? ''));

            if ($name === '' && $sellingPrice == 0 && $stockQty == 0 && $purchasePrice == 0 && $desc === '') {
                continue;
            }
            if ($name === '') {
                $errors[] = 'Each non-empty row must include a product name.';
                continue;
            }
            if ($sellingPrice < 0 || $purchasePrice < 0 || $stockQty < 0 || $lowThreshold < 0) {
                $errors[] = "Negative values are not allowed for '{$name}'.";
                continue;
            }

            $rows[] = [
                'name' => $name,
                'manufacturer' => $manufacturer,
                'batch_number' => $batchNumber,
                'category_id' => $catId,
                'unit_id' => $unitId,
                'purchase_price' => $purchasePrice,
                'selling_price' => $sellingPrice,
                'stock_quantity' => $stockQty,
                'low_stock_threshold' => $lowThreshold,
                'expiry_date' => $expiry !== '' ? $expiry : null,
                'description' => $desc,
            ];
        }

        if (empty($rows) && empty($errors)) {
            $errors[] = 'Add at least one product row.';
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare('INSERT INTO products (company_id, name, manufacturer, batch_number, category_id, unit_id, purchase_price, selling_price, stock_quantity, low_stock_threshold, expiry_date, description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                foreach ($rows as $r) {
                    $stmt->bind_param(
                        'isssiiddddss',
                        $cid,
                        $r['name'],
                        $r['manufacturer'],
                        $r['batch_number'],
                        $r['category_id'],
                        $r['unit_id'],
                        $r['purchase_price'],
                        $r['selling_price'],
                        $r['stock_quantity'],
                        $r['low_stock_threshold'],
                        $r['expiry_date'],
                        $r['description']
                    );
                    $stmt->execute();
                }
                $stmt->close();
                $conn->commit();

                setFlash('success', count($rows) . ' product(s) added successfully.');
                header('Location: products.php');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include 'layout-top.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold"><i class="fas fa-table me-2"></i>Bulk Add Products</h5>
    <a href="products.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . sanitize($e) . '</li>'; ?></ul></div>
<?php endif; ?>

<div class="card table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Add Multiple Products</span>
        <button type="button" class="btn btn-success btn-sm" id="addBulkRowBtn"><i class="fas fa-plus me-1"></i>Add Row</button>
    </div>
    <div class="card-body border-bottom">
        <label class="form-label fw-semibold mb-2">Upload CSV (optional)</label>
        <p class="small text-muted mb-2">Headers: name, manufacturer, batch_number, category, unit, purchase_price, selling_price, stock_quantity, low_stock_threshold, expiry_date, description</p>
        <div class="input-group">
            <input type="file" id="csvFileInput" class="form-control" accept=".csv,text/csv">
            <button type="button" class="btn btn-outline-primary" id="loadCsvBtn"><i class="fas fa-upload me-1"></i>Load CSV Preview</button>
        </div>
        <div id="csvLoadMessage" class="mt-2"></div>
    </div>
    <div class="card-body p-0">
        <form method="POST" id="bulkProductForm" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0" id="bulkProductTable">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:170px;">Name</th>
                            <th style="min-width:150px;">Manufacturer</th>
                            <th style="min-width:130px;">Batch No.</th>
                            <th style="min-width:150px;">Category</th>
                            <th style="min-width:150px;">Unit</th>
                            <th style="width:110px;">Purchase (INR)</th>
                            <th style="width:110px;">Selling (INR)</th>
                            <th style="width:100px;">Stock</th>
                            <th style="width:130px;">Low Stock At</th>
                            <th style="width:140px;">Expiry Date</th>
                            <th style="min-width:170px;">Description</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="bulkProductBody">
                        <tr data-index="0">
                            <td><input type="text" name="name[0]" class="form-control form-control-sm" placeholder="Product name"></td>
                            <td><input type="text" name="manufacturer[0]" class="form-control form-control-sm" placeholder="Optional"></td>
                            <td><input type="text" name="batch_number[0]" class="form-control form-control-sm" placeholder="Optional"></td>
                            <td>
                                <select name="category_id[0]" class="form-select form-select-sm">
                                    <option value="">-</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="unit_id[0]" class="form-select form-select-sm">
                                    <option value="">-</option>
                                    <?php foreach ($units as $u): ?>
                                        <option value="<?= $u['id'] ?>"><?= sanitize($u['name']) ?> (<?= sanitize($u['abbreviation']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" min="0" name="purchase_price[0]" class="form-control form-control-sm" value="0.00"></td>
                            <td><input type="number" step="0.01" min="0" name="selling_price[0]" class="form-control form-control-sm" value="0.00"></td>
                            <td><input type="number" step="0.01" min="0" name="stock_quantity[0]" class="form-control form-control-sm" value="0"></td>
                            <td><input type="number" step="0.01" min="0" name="low_stock_threshold[0]" class="form-control form-control-sm" value="10"></td>
                            <td><input type="date" name="expiry_date[0]" class="form-control form-control-sm"></td>
                            <td><input type="text" name="description[0]" class="form-control form-control-sm" placeholder="Optional"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-bulk-row"><i class="fas fa-times"></i></button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="p-3 d-flex gap-2 justify-content-end border-top">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Products</button>
                <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<template id="bulkRowTemplate">
    <tr>
        <td><input type="text" data-name="name" class="form-control form-control-sm" placeholder="Product name"></td>
        <td><input type="text" data-name="manufacturer" class="form-control form-control-sm" placeholder="Optional"></td>
        <td><input type="text" data-name="batch_number" class="form-control form-control-sm" placeholder="Optional"></td>
        <td>
            <select data-name="category_id" class="form-select form-select-sm">
                <option value="">-</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select data-name="unit_id" class="form-select form-select-sm">
                <option value="">-</option>
                <?php foreach ($units as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= sanitize($u['name']) ?> (<?= sanitize($u['abbreviation']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" step="0.01" min="0" data-name="purchase_price" class="form-control form-control-sm" value="0.00"></td>
        <td><input type="number" step="0.01" min="0" data-name="selling_price" class="form-control form-control-sm" value="0.00"></td>
        <td><input type="number" step="0.01" min="0" data-name="stock_quantity" class="form-control form-control-sm" value="0"></td>
        <td><input type="number" step="0.01" min="0" data-name="low_stock_threshold" class="form-control form-control-sm" value="10"></td>
        <td><input type="date" data-name="expiry_date" class="form-control form-control-sm"></td>
        <td><input type="text" data-name="description" class="form-control form-control-sm" placeholder="Optional"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-bulk-row"><i class="fas fa-times"></i></button></td>
    </tr>
</template>

<script>
(function () {
    const addBtn = document.getElementById('addBulkRowBtn');
    const tbody = document.getElementById('bulkProductBody');
    const template = document.getElementById('bulkRowTemplate');
    const loadCsvBtn = document.getElementById('loadCsvBtn');
    const csvFileInput = document.getElementById('csvFileInput');
    const csvLoadMessage = document.getElementById('csvLoadMessage');
    let rowIndex = 1;

    // Simple CSV parser that handles quoted fields
    function parseCSV(csv) {
        const rows = [];
        let currentRow = [];
        let currentField = '';
        let insideQuotes = false;

        for (let i = 0; i < csv.length; i++) {
            const char = csv[i];
            const nextChar = csv[i + 1];

            if (char === '"') {
                if (insideQuotes && nextChar === '"') {
                    currentField += '"';
                    i++;
                } else {
                    insideQuotes = !insideQuotes;
                }
            } else if (char === ',' && !insideQuotes) {
                currentRow.push(currentField.trim());
                currentField = '';
            } else if ((char === '\n' || char === '\r') && !insideQuotes) {
                if (currentField || currentRow.length > 0) {
                    currentRow.push(currentField.trim());
                    if (currentRow.some(field => field)) {
                        rows.push(currentRow);
                    }
                    currentRow = [];
                    currentField = '';
                }
                if (char === '\r' && nextChar === '\n') {
                    i++;
                }
            } else {
                currentField += char;
            }
        }

        if (currentField || currentRow.length > 0) {
            currentRow.push(currentField.trim());
            if (currentRow.some(field => field)) {
                rows.push(currentRow);
            }
        }

        return rows;
    }

    function wireRowActions() {
        document.querySelectorAll('.remove-bulk-row').forEach(function (btn) {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                const rows = document.querySelectorAll('#bulkProductBody tr');
                if (rows.length > 1) {
                    this.closest('tr').remove();
                }
            });
        });
    }

    function addRowToTable(csvRowData, headerMap, index) {
        const clone = template.content.cloneNode(true);
        const row = clone.querySelector('tr');
        row.dataset.index = String(index);

        row.querySelectorAll('[data-name]').forEach(function (el) {
            el.name = el.dataset.name + '[' + index + ']';
        });

        // Map CSV columns to form fields
        const fieldMap = {
            'name': ['name', 'product name', 'product'],
            'manufacturer': ['manufacturer', 'mfg'],
            'batch_number': ['batch_number', 'batch no', 'batch'],
            'category_id': ['category_id', 'category'],
            'unit_id': ['unit_id', 'unit'],
            'purchase_price': ['purchase_price', 'purchase price', 'purchase'],
            'selling_price': ['selling_price', 'selling price', 'selling', 'price'],
            'stock_quantity': ['stock_quantity', 'stock quantity', 'stock', 'qty'],
            'low_stock_threshold': ['low_stock_threshold', 'low stock', 'threshold'],
            'expiry_date': ['expiry_date', 'expiry', 'expiry date'],
            'description': ['description', 'desc', 'notes']
        };

        // Get values from CSV row
        const values = {};
        Object.entries(fieldMap).forEach(([fieldName, aliases]) => {
            for (const alias of aliases) {
                if (headerMap[alias.toLowerCase()] !== undefined) {
                    const colIndex = headerMap[alias.toLowerCase()];
                    if (csvRowData[colIndex] !== undefined) {
                        values[fieldName] = csvRowData[colIndex];
                        break;
                    }
                }
            }
        });

        // Populate inputs
        row.querySelector('[data-name="name"]').value = values['name'] || '';
        row.querySelector('[data-name="manufacturer"]').value = values['manufacturer'] || '';
        row.querySelector('[data-name="batch_number"]').value = values['batch_number'] || '';
        row.querySelector('[data-name="category_id"]').value = values['category_id'] || '';
        row.querySelector('[data-name="unit_id"]').value = values['unit_id'] || '';
        row.querySelector('[data-name="purchase_price"]').value = values['purchase_price'] || '0.00';
        row.querySelector('[data-name="selling_price"]').value = values['selling_price'] || '0.00';
        row.querySelector('[data-name="stock_quantity"]').value = values['stock_quantity'] || '0';
        row.querySelector('[data-name="low_stock_threshold"]').value = values['low_stock_threshold'] || '10';
        row.querySelector('[data-name="expiry_date"]').value = values['expiry_date'] || '';
        row.querySelector('[data-name="description"]').value = values['description'] || '';

        tbody.appendChild(row);
        wireRowActions();
    }

    addBtn.addEventListener('click', function () {
        const clone = template.content.cloneNode(true);
        const row = clone.querySelector('tr');
        row.dataset.index = String(rowIndex);
        row.querySelectorAll('[data-name]').forEach(function (el) {
            el.name = el.dataset.name + '[' + rowIndex + ']';
        });
        tbody.appendChild(row);
        rowIndex += 1;
        wireRowActions();
    });

    loadCsvBtn.addEventListener('click', function () {
        if (!csvFileInput.files || csvFileInput.files.length === 0) {
            csvLoadMessage.innerHTML = '<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Please select a CSV file first.</div>';
            return;
        }

        const file = csvFileInput.files[0];
        const reader = new FileReader();

        reader.onload = function (e) {
            try {
                const csv = e.target.result;
                const rows = parseCSV(csv);

                if (rows.length < 2) {
                    csvLoadMessage.innerHTML = '<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle me-2"></i>CSV file must contain headers and at least one data row.</div>';
                    return;
                }

                // Create header map: header_name -> column_index
                const headerRow = rows[0];
                const headerMap = {};
                headerRow.forEach((header, index) => {
                    headerMap[header.toLowerCase()] = index;
                });

                // Clear existing table rows
                const existingRows = tbody.querySelectorAll('tr');
                existingRows.forEach(row => row.remove());

                // Add data rows from CSV
                let addedCount = 0;
                for (let i = 1; i < rows.length; i++) {
                    const dataRow = rows[i];
                    if (dataRow.some(cell => cell)) { // Only add non-empty rows
                        addRowToTable(dataRow, headerMap, rowIndex);
                        rowIndex += 1;
                        addedCount++;
                    }
                }

                if (addedCount === 0) {
                    csvLoadMessage.innerHTML = '<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle me-2"></i>No valid data rows found in CSV.</div>';
                    return;
                }

                csvLoadMessage.innerHTML = '<div class="alert alert-success mb-0"><i class="fas fa-check-circle me-2"></i><strong>' + addedCount + ' product(s) loaded from CSV.</strong> Review the preview below and click "Save Products" to upload to database.</div>';
            } catch (error) {
                csvLoadMessage.innerHTML = '<div class="alert alert-danger mb-0"><i class="fas fa-times-circle me-2"></i>Error parsing CSV: ' + error.message + '</div>';
            }
        };

        reader.onerror = function () {
            csvLoadMessage.innerHTML = '<div class="alert alert-danger mb-0"><i class="fas fa-times-circle me-2"></i>Failed to read the file.</div>';
        };

        reader.readAsText(file);
    });

    wireRowActions();
})();
</script>

<?php include 'layout-bottom.php'; ?>
</body>
</html>
