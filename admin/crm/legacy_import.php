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

    var ADMIN_BASE = location.href.replace(/[?#].*$/, '').replace(/\/crm\/[^\/]*$/, '/');

    function absUrl(path) {
        if (!path) return '';
        if (/^(https?:)?\/\//i.test(path)) return path;
        return ADMIN_BASE + path.replace(/^\//, '');
    }

    function appendLog(text) {
        logEl.textContent = (logEl.textContent === 'Ready.' ? '' : logEl.textContent + '\n') + text;
        logEl.scrollTop = logEl.scrollHeight;
    }

    function statLine(label, stats) {
        if (!stats) return '';
        return label + ': imported ' + (stats.imported || 0) + ', skipped ' + (stats.skipped || 0) + ', failed ' + (stats.failed || 0);
    }

    function logErrors(prefix, stats) {
        if (!stats || !stats.errors || !stats.errors.length) return;
        stats.errors.forEach(function (err) { appendLog(prefix + ': ' + err); });
    }

    function postStep(action, fd) {
        fd.set('action', action);
        return fetch(absUrl('crm/ajax/run_legacy_import.php'), {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) {
            return r.text().then(function (text) {
                var res;
                try {
                    res = JSON.parse(text);
                } catch (e) {
                    throw new Error('HTTP ' + r.status + ' — server did not return JSON.\n' + text.substring(0, 500));
                }
                if (r.status === 401) {
                    throw new Error(res.message || 'Session expired. Please sign in again.');
                }
                if (!res.success) {
                    var msg = res.message || 'Step failed.';
                    if (res.errors && res.errors.length) {
                        msg += '\n' + res.errors.slice(0, 5).join('\n');
                    }
                    throw new Error(msg);
                }
                return res;
            });
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!confirm('Start legacy import now?')) return;

        btn.disabled = true;
        logEl.textContent = 'Starting import (runs in small steps to avoid timeout)...';

        var fd = new FormData(form);
        var clearExisting = fd.get('clear_existing') === '1';
        var totals = { suppliers: null, customers: null, quotations: null };

        var steps = [];
        if (clearExisting) steps.push({ action: 'wipe', label: 'Clearing existing CRM data...' });
        steps.push({ action: 'stage_init', label: 'Loading suppliers & customers from SQL file...' });
        steps.push({ action: 'stage_quotations', label: 'Loading quotations from SQL file...' });
        steps.push({ action: 'import_suppliers', label: 'Importing suppliers...' });
        steps.push({ action: 'import_customers', label: 'Importing customers as leads...' });
        steps.push({ action: 'import_quotations', label: 'Importing quotations...' });
        steps.push({ action: 'cleanup', label: 'Cleaning up temporary tables...' });

        var chain = Promise.resolve();
        steps.forEach(function (step) {
            chain = chain.then(function () {
                appendLog(step.label);
                return postStep(step.action, fd).then(function (res) {
                    if (res.suppliers) totals.suppliers = res.suppliers;
                    if (res.customers) totals.customers = res.customers;
                    if (res.quotations) totals.quotations = res.quotations;
                    if (res.counts) {
                        appendLog('  Loaded: suppliers ' + (res.counts.suppliers || 0) + ', customers ' + (res.counts.customers || 0) + ', quotations ' + (res.counts.quotation || 0));
                    }
                });
            });
        });

        chain.then(function () {
            appendLog('Import completed successfully.');
            if (clearExisting) appendLog('Existing dummy CRM rows were cleared.');
            appendLog(statLine('Suppliers', totals.suppliers));
            appendLog(statLine('Customers', totals.customers));
            appendLog(statLine('Quotations', totals.quotations));
            logErrors('Supplier error', totals.suppliers);
            logErrors('Customer error', totals.customers);
            logErrors('Quotation error', totals.quotations);
            setTimeout(function () { window.location.reload(); }, 2000);
        }).catch(function (err) {
            appendLog('Failed: ' + (err && err.message ? err.message : String(err)));
        }).finally(function () {
            btn.disabled = false;
        });
    });
})();
</script>
</body>
</html>
