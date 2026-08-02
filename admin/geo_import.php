<?php
session_start();
if ($_SESSION['role'] != '1') {
    header('location:index.php');
    exit;
}

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/includes/geo_locations.php';

geoEnsureTables($conn);

$msg = '';
$msgType = 'success';
$logLines = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_time_limit(0);

    $action = $_POST['action'] ?? '';
    $logger = static function (string $line) use (&$logLines): void {
        $logLines[] = $line;
    };

    try {
        if ($action === 'countries') {
            $result = geoImportCountriesAndStates($conn, $logger);
            $msg = 'Imported ' . $result['countries'] . ' countries and ' . $result['states'] . ' states.';
        } elseif ($action === 'cities_country') {
            $result = geoImportCitiesByCountry($conn, $logger, 150);
            $msg = 'Imported ' . $result['cities'] . ' cities from ' . $result['countries_processed'] . ' countries.';
        } elseif ($action === 'cities_state') {
            $result = geoImportCitiesByState($conn, $logger, 150);
            $msg = 'Imported ' . $result['cities'] . ' cities from ' . $result['states_processed'] . ' states.';
        } elseif ($action === 'all') {
            $r1 = geoImportCountriesAndStates($conn, $logger);
            $r2 = geoImportCitiesByState($conn, $logger, 150);
            $msg = 'Full import done: ' . $r1['countries'] . ' countries, ' . $r1['states'] . ' states, ' . $r2['cities'] . ' cities.';
        } else {
            $msg = 'Invalid action.';
            $msgType = 'danger';
        }
    } catch (Throwable $e) {
        $msg = 'Import failed: ' . $e->getMessage();
        $msgType = 'danger';
    }
}

$counts = geoLocationCounts($conn);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Geo Data Import</title>
    <?php include __DIR__ . '/includes/header-links.php'; ?>
    <style>
        .geo-import-card { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; }
        .geo-stat { font-size: 1.75rem; font-weight: 700; color: #2563eb; }
        .geo-log { background: #0f172a; color: #e2e8f0; border-radius: 8px; padding: 1rem; max-height: 320px; overflow: auto; font-size: 0.82rem; white-space: pre-wrap; }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content-wrapper">
        <section class="content pt-3">
            <div class="container-fluid">
                <h1 class="mb-3">Geo Data Import</h1>
                <p class="text-muted">Import countries, states, and cities from <a href="https://countriesnow.space" target="_blank" rel="noopener">CountriesNow API</a> into your local database.</p>

                <?php if ($msg !== ''): ?>
                    <div class="alert alert-<?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4">
                        <div class="geo-import-card text-center">
                            <div class="text-muted">Countries</div>
                            <div class="geo-stat"><?= (int) $counts['countries'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="geo-import-card text-center">
                            <div class="text-muted">States</div>
                            <div class="geo-stat"><?= (int) $counts['states'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="geo-import-card text-center">
                            <div class="text-muted">Cities</div>
                            <div class="geo-stat"><?= (int) $counts['cities'] ?></div>
                        </div>
                    </div>
                </div>

                <div class="geo-import-card">
                    <h5 class="mb-3">Run Import</h5>
                    <form method="post" class="d-flex flex-wrap gap-2" style="gap:0.5rem;" onsubmit="return confirm('Start import? This may take several minutes.');">
                        <button type="submit" name="action" value="countries" class="btn btn-primary">Import Countries &amp; States</button>
                        <button type="submit" name="action" value="cities_country" class="btn btn-info">Import Cities (by Country)</button>
                        <button type="submit" name="action" value="cities_state" class="btn btn-warning">Import Cities (by State)</button>
                        <button type="submit" name="action" value="all" class="btn btn-success">Import All (Recommended)</button>
                    </form>
                    <p class="text-muted small mt-3 mb-0">
                        CLI: <code>php admin/scripts/import_geo_data.php --only=countries</code> then
                        <code>php admin/scripts/import_geo_data.php --only=cities --cities=country</code>.
                        State-level cities import can take over an hour.
                    </p>
                </div>

                <?php if (!empty($logLines)): ?>
                    <div class="geo-import-card">
                        <h5 class="mb-3">Import Log</h5>
                        <div class="geo-log"><?= htmlspecialchars(implode("\n", $logLines)) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?php include __DIR__ . '/includes/footer-links.php'; ?>
</body>
</html>
