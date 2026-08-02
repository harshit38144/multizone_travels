<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/package_quotation.php';

header('Content-Type: application/json; charset=utf-8');

function pkgQuotationJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$packageId = (int) ($_GET['id'] ?? 0);
if ($packageId <= 0) {
    pkgQuotationJson(false, 'Invalid package.');
}

if (!crmPackageTablesExist($conn)) {
    pkgQuotationJson(false, 'Packages module is not available.');
}

$pkg = crmGetPackageForQuotation($conn, $packageId);
if (!$pkg) {
    pkgQuotationJson(false, 'Package not found.');
}

if (empty($pkg['itinerary'])) {
    pkgQuotationJson(false, 'This package has no itinerary days defined.');
}

pkgQuotationJson(true, 'OK', ['package' => $pkg]);
