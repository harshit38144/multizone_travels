<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/legacy_import_db.php';

$probe = crmLegacyImportProbe($conn);

$crmCounts = [];
foreach (['crm_suppliers', 'crm_leads', 'crm_quotations'] as $table) {
    $res = $conn->query('SELECT COUNT(*) AS c FROM `' . $conn->real_escape_string($table) . '`');
    $crmCounts[$table] = ($res && ($row = $res->fetch_assoc())) ? (int) $row['c'] : 0;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Import Legacy Data</title>
    <base href="../">
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        .legacy-import-card { max-width: 920px; }
        .legacy-stat { font-size: 1.75rem; font-weight: 700; line-height: 1.1; }
        .legacy-log {
            background: #1e1e1e;
            color: #d4d4d4;
            border-radius: 8px;
            padding: 1rem;
            max-height: 320px;
            overflow: auto;
            font-family: Consolas, Monaco, monospace;
            font-size: 13px;
            white-space: pre-wrap;
        }
        .legacy-status-ok { color: #28a745; }
        .legacy-status-bad { color: #dc3545; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed page-bg">
<div class="wrapper">
    <?php include __DIR__ . '/../includes/top-header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-8">
                        <p class="text-muted mb-0">CRM Settings</p>
                        <h1 class="m-0 text-dark">Import Legacy Dashboard Data</h1>
                        <p class="text-muted mb-0">Copy suppliers, customers, and quotations from the old dashboard database into current CRM tables.</p>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="card legacy-import-card">
                    <div class="card-body">
                        <h5 class="mb-3">Legacy database</h5>
                        <p class="mb-2">
                            Source:
                            <strong><?= !empty($probe['file']) ? htmlspecialchars((string) $probe['file']) : 'SQL file not found' ?></strong>
                            <?php if (!empty($probe['connected'])): ?>
                                <span class="legacy-status-ok ml-2"><i class="fas fa-check-circle"></i> Ready</span>
                            <?php else: ?>
                                <span class="legacy-status-bad ml-2"><i class="fas fa-times-circle"></i> Not found</span>
                            <?php endif; ?>
                        </p>
                        <p class="text-muted small mb-2">Uses your existing CRM database only — no second database is created on the server.</p>
                        <?php if (!empty($probe['error'])): ?>
                            <div class="alert alert-warning"><?= htmlspecialchars((string) $probe['error']) ?></div>
                        <?php endif; ?>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Legacy suppliers</div>
                                    <div class="legacy-stat"><?= (int) ($probe['counts']['suppliers'] ?? 0) ?></div>
                                    <div class="text-muted small mt-1">Current CRM: <?= (int) ($crmCounts['crm_suppliers'] ?? 0) ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Legacy customers → leads</div>
                                    <div class="legacy-stat"><?= (int) ($probe['counts']['customers'] ?? 0) ?></div>
                                    <div class="text-muted small mt-1">Current CRM: <?= (int) ($crmCounts['crm_leads'] ?? 0) ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Legacy quotations</div>
                                    <div class="legacy-stat"><?= (int) ($probe['counts']['quotation'] ?? 0) ?></div>
                                    <div class="text-muted small mt-1">Current CRM: <?= (int) ($crmCounts['crm_quotations'] ?? 0) ?></div>
                                </div>
                            </div>
                        </div>

                        <form id="legacyImportForm">
                            <div class="form-group">
                                <label class="d-block font-weight-bold mb-2">Import</label>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="import_suppliers" name="import_suppliers" value="1" checked>
                                    <label class="custom-control-label" for="import_suppliers">Suppliers → CRM Suppliers</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="import_customers" name="import_customers" value="1" checked>
                                    <label class="custom-control-label" for="import_customers">Customers → CRM Leads</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="import_quotations" name="import_quotations" value="1" checked>
                                    <label class="custom-control-label" for="import_quotations">Quotations → CRM Quotations</label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="clear_existing" name="clear_existing" value="1">
                                    <label class="custom-control-label" for="clear_existing">Clear existing CRM suppliers, leads, and quotations before import</label>
                                </div>
                                <small class="text-muted">Use this if current CRM rows are dummy/test data only.</small>
                            </div>

                            <button type="submit" class="btn btn-primary" id="runImportBtn" <?= empty($probe['connected']) ? 'disabled' : '' ?>>
                                <i class="fas fa-database mr-1"></i> Run import
                            </button>
                        </form>

                        <div class="mt-4">
                            <h6>Import log</h6>
                            <div class="legacy-log" id="importLog">Ready.</div>
                        </div>
                    </div>
                </div>

                <div class="card legacy-import-card mt-3">
                    <div class="card-body">
                        <h5 class="mb-2">Setup (one time)</h5>
                        <ol class="mb-0 pl-3">
                            <li>Upload <code>u560130840_dashboard.sql</code> to your project root on the server (same folder as the <code>admin</code> folder) via FTP or Hostinger File Manager.</li>
                            <li>Open this page — it should show the file as <strong>Ready</strong>.</li>
                            <li>Check <strong>Clear existing</strong> if current CRM data is dummy, then run import.</li>
                        </ol>
                        <p class="text-muted small mt-3 mb-0">Temporary staging tables are created during import and removed automatically. Your CRM database is the only database used.</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('legacyImportForm');
    var logEl = document.getElementById('importLog');
    var btn = document.getElementById('runImportBtn');

    function appendLog(text) {
        logEl.textContent = (logEl.textContent === 'Ready.' ? '' : logEl.textContent + '\n') + text;
        logEl.scrollTop = logEl.scrollHeight;
    }

    function statLine(label, stats) {
        if (!stats) return '';
        return label + ': imported ' + (stats.imported || 0) + ', skipped ' + (stats.skipped || 0) + ', failed ' + (stats.failed || 0);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!confirm('Start legacy import now?')) return;

        btn.disabled = true;
        logEl.textContent = 'Running import...';

        var fd = new FormData(form);
        fd.append('action', 'run');

        fetch('crm/ajax/run_legacy_import.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                appendLog(res.message || (res.success ? 'Done.' : 'Failed.'));
                if (res.wiped) appendLog('Existing CRM suppliers/leads/quotations were cleared.');
                appendLog(statLine('Suppliers', res.suppliers));
                appendLog(statLine('Customers', res.customers));
                appendLog(statLine('Quotations', res.quotations));
                if (res.suppliers && res.suppliers.errors) res.suppliers.errors.forEach(function (err) { appendLog('Supplier error: ' + err); });
                if (res.customers && res.customers.errors) res.customers.errors.forEach(function (err) { appendLog('Customer error: ' + err); });
                if (res.quotations && res.quotations.errors) res.quotations.errors.forEach(function (err) { appendLog('Quotation error: ' + err); });
                if (res.success) {
                    setTimeout(function () { window.location.reload(); }, 1500);
                }
            })
            .catch(function () {
                appendLog('Request failed. Check session/login and try again.');
            })
            .finally(function () {
                btn.disabled = false;
            });
    });
})();
</script>
</body>
</html>
