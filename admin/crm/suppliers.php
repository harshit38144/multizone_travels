<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/supplier_db.php';
require_once __DIR__ . '/../includes/geo_locations.php';

crmEnsureSupplierTables($conn);
geoEnsureTables($conn);

$serviceMap = crmSupplierServiceMap();
$supplierTypeMap = crmSupplierTypeMap();
$suppliers = [];
$res = $conn->query(
    "SELECT s.*,
            COALESCE(NULLIF(s.city_name, ''), ci.name, '') AS resolved_city_name,
            COALESCE(NULLIF(s.country_name, ''), co.name, '') AS resolved_country_name
     FROM crm_suppliers s
     LEFT JOIN cities ci ON ci.id = s.city_id
     LEFT JOIN countries co ON co.id = ci.country_id
     ORDER BY s.id DESC"
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['city_name'] = (string) ($row['resolved_city_name'] ?? $row['city_name'] ?? '');
        $row['country_name'] = (string) ($row['resolved_country_name'] ?? $row['country_name'] ?? '');
        $contacts = json_decode((string) ($row['contacts_json'] ?? '[]'), true);
        $supplierOf = json_decode((string) ($row['supplier_of_json'] ?? '[]'), true);
        $places = json_decode((string) ($row['places_json'] ?? '[]'), true);
        $contacts = crmSupplierNormalizeContacts(is_array($contacts) ? $contacts : []);
        $supplierOf = crmSupplierNormalizeSupplierOf(is_array($supplierOf) ? $supplierOf : []);
        $places = crmSupplierNormalizePlaces(is_array($places) ? $places : []);

        $row['_contacts'] = $contacts;
        $row['_supplier_of'] = $supplierOf;
        $row['_places'] = $places;
        $row['_places_label'] = crmSupplierPlacesLabel($places);
        $row['_city_line'] = crmSupplierCityLine($row['city_name'] ?? '', $row['country_name'] ?? '');
        $row['_contact_name'] = crmSupplierPrimaryContact($contacts, 'contact_name');
        $row['_email'] = crmSupplierPrimaryContact($contacts, 'email');
        $row['_mobile'] = crmSupplierPrimaryContact($contacts, 'mobile');
        $row['_place_ids'] = implode(',', array_map(static function ($p) {
            return (int) ($p['id'] ?? 0);
        }, $places));
        $row['_types'] = implode(',', $supplierOf);
        $supplierTypes = crmSupplierNormalizeTypes($row['supplier_type'] ?? '');
        $row['_supplier_types'] = $supplierTypes;
        $row['_type_label'] = crmSupplierTypesLabel($supplierTypes);
        if ($row['_type_label'] === '—' && $supplierOf) {
            $row['_type_label'] = $serviceMap[$supplierOf[0]] ?? ucfirst(str_replace('_', ' ', $supplierOf[0]));
        }
        $row['_type_keys'] = implode(',', $supplierTypes);
        $row['_service_labels'] = array_values(array_map(static function ($key) use ($serviceMap) {
            return $serviceMap[$key] ?? ucfirst(str_replace('_', ' ', $key));
        }, $supplierOf));
        $suppliers[] = $row;
    }
}

$destinations = [];
$destRes = $conn->query('SELECT id, name, country FROM destinations WHERE is_active = 1 ORDER BY display_order ASC, name ASC');
if ($destRes) {
    while ($d = $destRes->fetch_assoc()) {
        $label = trim((string) ($d['name'] ?? ''));
        $country = trim((string) ($d['country'] ?? ''));
        if ($country !== '') {
            $label .= ' - ' . $country;
        }
        $destinations[] = [
            'id' => (int) $d['id'],
            'name' => (string) ($d['name'] ?? ''),
            'label' => $label,
        ];
    }
}

$supplierCities = [];
$activeSupplierCount = 0;
$usedServices = [];
foreach ($suppliers as $supplier) {
    if ((int) ($supplier['is_active'] ?? 1) === 1) {
        $activeSupplierCount++;
    }
    $cityLine = trim((string) ($supplier['_city_line'] ?? ''));
    if ($cityLine !== '') {
        $supplierCities[$cityLine] = true;
    }
    foreach (($supplier['_supplier_of'] ?? []) as $serviceKey) {
        $usedServices[(string) $serviceKey] = true;
    }
}
$supplierCityOptions = array_keys($supplierCities);
natcasesort($supplierCityOptions);
$supplierCityOptions = array_values($supplierCityOptions);
$supplierCount = count($suppliers);
$cityCount = count($supplierCities);
$serviceCategoryCount = count($usedServices);

function crmSupplierInitials($name)
{
    $parts = preg_split('/\s+/u', trim((string) $name)) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }
    return $initials !== '' ? $initials : 'S';
}

function crmSupplierUpdatedLabel($value)
{
    $ts = strtotime((string) $value);
    return $ts ? date('d M Y', $ts) : '—';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Suppliers</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <style>
        .crm-suppliers-page {
            --sup-red: #e11d2e;
            --sup-border: #e7ebf1;
            --sup-text: #0f172a;
            --sup-muted: #64748b;
        }
        .crm-suppliers-page .content-wrapper,
        .crm-suppliers-page .content-wrapper > .content { background: #f7f8fa; }
        .crm-suppliers-page .content-wrapper > .content { padding-top: 1.5rem; }
        .crm-suppliers-page .content-header { display: none; }
        .crm-suppliers-page .supplier-page-head {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 1.25rem; margin-bottom: 1.35rem; flex-wrap: wrap;
        }
        .crm-suppliers-page .supplier-page-head > div:first-child {
            position: relative; padding-left: 1rem; padding-top: .1rem;
        }
        .crm-suppliers-page .supplier-page-head > div:first-child::before {
            content: ""; position: absolute; left: 0; top: .15rem; bottom: .15rem;
            width: 4px; border-radius: 999px; background: var(--sup-red);
        }
        .crm-suppliers-page .supplier-title {
            margin: 0; color: var(--sup-text); font-size: 2rem;
            line-height: 1.1; font-weight: 750; letter-spacing: -.035em;
        }
        .crm-suppliers-page .supplier-subtitle {
            margin: .38rem 0 0; color: #64748b; font-size: .88rem; font-weight: 500;
        }
        .crm-suppliers-page .supplier-toolbar {
            display: flex; align-items: center; justify-content: flex-end;
            flex: 1 1 680px; gap: .55rem; flex-wrap: wrap;
        }
        .crm-suppliers-page .supplier-search {
            position: relative; flex: 1 1 255px; max-width: 340px;
        }
        .crm-suppliers-page .supplier-search i {
            position: absolute; left: .82rem; top: 50%; transform: translateY(-50%);
            color: #94a3b8; z-index: 1; font-size: .82rem;
        }
        .crm-suppliers-page .supplier-search input,
        .crm-suppliers-page .supplier-filter {
            height: 42px; border: 1px solid var(--sup-border); border-radius: 9px;
            background: #fff; color: #334155; font-size: .82rem; box-shadow: none;
        }
        .crm-suppliers-page .supplier-search input { padding-left: 2.25rem; width: 100%; }
        .crm-suppliers-page .supplier-filter {
            min-width: 128px; padding: 0 .7rem; font-weight: 600;
        }
        .crm-suppliers-page .supplier-tool-btn {
            height: 42px; border: 1px solid var(--sup-border); border-radius: 9px;
            background: #fff; color: #334155; padding: 0 .9rem; font-size: .82rem;
            font-weight: 600; display: inline-flex; align-items: center; gap: .4rem;
        }
        .crm-suppliers-page .supplier-tool-btn:hover { background: #f8fafc; color: #0f172a; }
        .crm-suppliers-page .btn-add-supplier {
            height: 42px; border: 0; border-radius: 9px; background: var(--sup-red);
            color: #fff !important; padding: 0 1rem; font-size: .82rem; font-weight: 700;
            box-shadow: 0 6px 16px rgba(225,29,46,.2);
        }
        .crm-suppliers-page .btn-add-supplier:hover { background: #c81020; color: #fff !important; }
        .crm-suppliers-page .supplier-kpis {
            display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem; margin-bottom: 1.15rem;
        }
        .crm-suppliers-page .supplier-kpi {
            min-width: 0; display: flex; align-items: center; gap: .85rem;
            padding: 1rem 1.1rem; background: #fff; border: 1px solid var(--sup-border);
            border-radius: 14px; box-shadow: 0 5px 18px rgba(15,23,42,.035);
        }
        .crm-suppliers-page .supplier-kpi-icon {
            width: 44px; height: 44px; flex: 0 0 44px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            background: #fee2e2; color: var(--sup-red); font-size: 1rem;
        }
        .crm-suppliers-page .supplier-kpi-label { color: #64748b; font-size: .78rem; font-weight: 600; }
        .crm-suppliers-page .supplier-kpi-value {
            margin: .08rem 0; color: var(--sup-text); font-size: 1.45rem;
            line-height: 1.1; font-weight: 700;
        }
        .crm-suppliers-page .supplier-kpi-trend { color: #16a34a; font-size: .7rem; font-weight: 600; }
        .crm-suppliers-page .list-card {
            background: #fff; border: 1px solid var(--sup-border); border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06); overflow: hidden;
        }
        .crm-suppliers-page .list-card-body { padding: 0; }
        .crm-suppliers-page .supplier-table-scroll {
            overflow-x: auto; overflow-y: visible; background: #fff;
            scrollbar-width: thin; scrollbar-color: #475569 #d1d5db;
        }
        .crm-suppliers-page .supplier-table-scroll::-webkit-scrollbar { height: 11px; }
        .crm-suppliers-page .supplier-table-scroll::-webkit-scrollbar-thumb {
            background: #475569; border-radius: 999px; border: 2px solid #d1d5db;
        }
        .crm-suppliers-page .supplier-table-scroll::-webkit-scrollbar-track { background: #d1d5db; }
        .crm-suppliers-page #suppliersTable {
            width: 100% !important; min-width: 1180px; margin: 0 !important;
            border-collapse: collapse; border: 1px solid var(--sup-border);
            background: #fff; font-size: calc(.8125rem + 1px);
        }
        .crm-suppliers-page #suppliersTable thead th {
            position: sticky; top: 0; z-index: 2; background: #fff; border: 0;
            border-bottom: 1px solid var(--sup-border); color: #6b7280;
            font-size: calc(.72rem + 1px); font-weight: 700; white-space: nowrap;
            padding: .85rem .75rem; text-transform: uppercase; letter-spacing: .04em;
            box-shadow: inset 0 -1px 0 var(--sup-border);
        }
        .crm-suppliers-page #suppliersTable tbody td {
            color: #374151; font-size: calc(.8125rem + 1px); vertical-align: middle;
            border-top: 1px solid #f3f4f6; padding: .85rem .75rem;
            white-space: nowrap; line-height: 1.35; background: #fff;
        }
        .crm-suppliers-page #suppliersTable tbody tr:last-child td { border-bottom: 1px solid var(--sup-border); }
        .crm-suppliers-page #suppliersTable tbody tr:hover td { background: #fafafa; }
        .crm-suppliers-page .supplier-identity { display: flex; align-items: center; gap: .65rem; }
        .crm-suppliers-page .supplier-avatar {
            width: 32px; height: 32px; flex: 0 0 32px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            color: #fff; font-size: .66rem; font-weight: 700;
            background: linear-gradient(135deg,#ef4444,#b91c1c);
        }
        .crm-suppliers-page tbody tr:nth-child(4n+2) .supplier-avatar { background: linear-gradient(135deg,#38bdf8,#2563eb); }
        .crm-suppliers-page tbody tr:nth-child(4n+3) .supplier-avatar { background: linear-gradient(135deg,#22c55e,#0f766e); }
        .crm-suppliers-page tbody tr:nth-child(4n+4) .supplier-avatar { background: linear-gradient(135deg,#a855f7,#6d28d9); }
        .crm-suppliers-page .sup-name { color: var(--sup-text); font-weight: 700; }
        .crm-suppliers-page .sup-city { color: #64748b; }
        .crm-suppliers-page .sup-type,
        .crm-suppliers-page .sup-service {
            display: inline-flex; align-items: center; border-radius: 5px;
            padding: .18rem .42rem; font-size: .64rem; font-weight: 600; margin: 1px 3px 1px 0;
        }
        .crm-suppliers-page .sup-type { color: #2563eb; background: #eff6ff; }
        .crm-suppliers-page .sup-service:nth-child(3n+1) { color: #e11d48; background: #fff1f2; }
        .crm-suppliers-page .sup-service:nth-child(3n+2) { color: #7c3aed; background: #f5f3ff; }
        .crm-suppliers-page .sup-service:nth-child(3n) { color: #059669; background: #ecfdf5; }
        .crm-suppliers-page .sup-services-wrap {
            position: relative; display: inline-flex; align-items: center; gap: .25rem;
        }
        .crm-suppliers-page .sup-services-more {
            width: 23px; height: 23px; border: 1px solid #e2e8f0; border-radius: 50%;
            background: #fff; color: #64748b; padding: 0; display: inline-flex;
            align-items: center; justify-content: center; font-size: .62rem; cursor: pointer;
        }
        .crm-suppliers-page .sup-services-more:hover,
        .crm-suppliers-page .sup-services-more:focus {
            border-color: #fecdd3; background: #fff1f2; color: var(--sup-red); outline: none;
        }
        .supplier-services-popover {
            min-width: 170px; max-width: 270px;
            border-color: #e2e8f0; border-radius: 9px;
            box-shadow: 0 12px 28px rgba(15,23,42,.14);
        }
        .supplier-services-popover .popover-body {
            padding: .65rem; white-space: normal;
        }
        .supplier-services-popover .popover-body::before {
            content: "Other Services"; display: block; color: #64748b;
            font-size: .66rem; font-weight: 700; margin-bottom: .4rem;
        }
        .supplier-services-popover .sup-service {
            display: inline-flex; align-items: center; border-radius: 5px;
            padding: .18rem .42rem; margin: 1px 3px 1px 0;
            color: #7c3aed; background: #f5f3ff; font-size: .64rem; font-weight: 600;
        }
        .crm-suppliers-page .supplier-status {
            display: inline-flex; align-items: center; gap: .35rem; border-radius: 999px;
            padding: .24rem .55rem; font-size: .66rem; font-weight: 700;
        }
        .crm-suppliers-page .supplier-status::before {
            content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor;
        }
        .crm-suppliers-page .supplier-status.active { color: #059669; background: #ecfdf5; }
        .crm-suppliers-page .supplier-status.inactive { color: #e11d48; background: #fff1f2; }
        .crm-suppliers-page .sup-actions { display: inline-flex; gap: .35rem; }
        .crm-suppliers-page .sup-action {
            width: 29px; height: 29px; padding: 0; border: 0; border-radius: 50%;
            background: transparent; color: #64748b; display: inline-flex;
            align-items: center; justify-content: center; font-size: .72rem;
        }
        .crm-suppliers-page .sup-action:hover { background: #f1f5f9; color: #0f172a; }
        .crm-suppliers-page .sup-action.delete { color: #e11d2e; }
        .crm-suppliers-page div.dataTables_wrapper div.dataTables_filter { display: none; }
        .crm-suppliers-page .supplier-table-footer {
            display: flex; align-items: center; justify-content: space-between;
            gap: .75rem; flex-wrap: wrap; padding: .75rem 1rem;
            border-top: 1px solid var(--sup-border); background: #fafbfc;
            font-size: calc(.875rem + 1px);
        }
        .crm-suppliers-page .supplier-table-footer-tools {
            display: flex; align-items: center; gap: .65rem; flex-wrap: wrap;
        }
        .crm-suppliers-page div.dataTables_wrapper div.dataTables_info {
            padding: 0; color: #6b7280; white-space: nowrap;
        }
        .crm-suppliers-page div.dataTables_wrapper div.dataTables_length {
            display: block; color: #64748b; margin: 0;
        }
        .crm-suppliers-page div.dataTables_wrapper div.dataTables_length label {
            margin: 0; display: flex; align-items: center; gap: .35rem;
        }
        .crm-suppliers-page div.dataTables_wrapper div.dataTables_length select {
            width: auto; min-width: 6rem; height: 30px; padding: .1rem 1.6rem .1rem .45rem;
            border: 1px solid #cbd5e1; border-radius: 6px; background: #fff;
            color: #475569; font-size: calc(.78rem + 1px);
        }
        .crm-suppliers-page div.dataTables_wrapper div.dataTables_paginate { margin: 0; padding: 0; }
        .crm-suppliers-page .page-item .page-link {
            min-width: 36px; height: 32px; border-radius: 6px !important;
            margin: 0 2px; display: flex; align-items: center; justify-content: center;
            color: #475569; background: #f8fafc; border-color: #e2e8f0;
            font-size: calc(.78rem + 1px); font-weight: 600;
        }
        .crm-suppliers-page .page-item.active .page-link {
            background: #dbeafe; border-color: #93c5fd; color: #1d4ed8;
        }
        .crm-suppliers-page .page-item .page-link:hover {
            background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8;
        }
        @media (max-width: 1199.98px) {
            .crm-suppliers-page .supplier-kpis { grid-template-columns: repeat(2,minmax(0,1fr)); }
        }
        @media (max-width: 767.98px) {
            .crm-suppliers-page .supplier-kpis { grid-template-columns: 1fr; }
            .crm-suppliers-page .supplier-toolbar { justify-content: flex-start; }
            .crm-suppliers-page .supplier-search { max-width: none; flex-basis: 100%; }
        }
        /* Supplier Add/Edit modal — reference layout */
        #supplierModal .supplier-modal-dialog {
            max-width: 920px; width: calc(100% - 1.5rem); margin: 1rem auto;
        }
        #supplierModal .supplier-modal-content {
            border: 0; border-radius: 4px; overflow: hidden;
            box-shadow: 0 18px 50px rgba(15,23,42,.22);
            max-height: calc(100vh - 2rem); display: flex; flex-direction: column;
        }
        #supplierModal #supplierForm {
            min-height: 0; display: flex; flex: 1 1 auto; flex-direction: column;
        }
        #supplierModal .supplier-modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.1rem 1.35rem .95rem; background: #fff; color: #0f172a;
            border-bottom: 1px solid #e5e7eb;
        }
        #supplierModal .modal-title {
            color: #64748b; font-size: 1.15rem; font-weight: 600; letter-spacing: .01em;
        }
        #supplierModal .supplier-modal-header .close {
            padding: 0; margin: 0; font-size: 1.55rem; font-weight: 400;
            color: #94a3b8; opacity: 1; text-shadow: none; line-height: 1;
        }
        #supplierModal .supplier-modal-header .close:hover { color: #64748b; }
        #supplierModal .supplier-modal-body {
            min-height: 0; flex: 1 1 auto; overflow-y: auto;
            padding: 1.15rem 1.35rem 1rem; background: #fff;
        }
        #supplierModal .supplier-modal-body label {
            display: block; margin: 0 0 .35rem; color: #94a3b8;
            font-size: .82rem; font-weight: 500;
        }
        #supplierModal .form-control {
            height: 40px; border: 1px solid #e2e8f0; border-radius: 4px;
            color: #0f172a; font-size: .9rem; box-shadow: none; background: #fff;
        }
        #supplierModal .form-control:focus {
            border-color: #94a3b8; box-shadow: none;
        }
        #supplierModal .form-group { margin-bottom: .95rem; }
        #supplierModal .supplier-contact-rows { margin-bottom: .15rem; }
        #supplierModal .contact-row { margin: 0 0 .15rem; }
        #supplierModal .contact-action-wrap { display: flex; flex-direction: column; }
        #supplierModal .contact-action-label { height: 1.15rem; margin: 0; }
        #supplierModal .btn-contact-action {
            width: 40px; height: 40px; padding: 0; display: inline-flex;
            align-items: center; justify-content: center;
            border: 1px solid #cbd5e1; border-radius: 4px;
            background: #fff; color: #0f172a; font-size: .95rem;
        }
        #supplierModal .btn-contact-action:hover { background: #f8fafc; color: #0f172a; }
        #supplierModal .btn-contact-action.is-remove {
            background: #e11d48; border-color: #e11d48; color: #fff;
        }
        #supplierModal .btn-contact-action.is-remove:hover {
            background: #be123c; border-color: #be123c; color: #fff;
        }
        #supplierModal .search-wrap { position: relative; }
        #supplierModal .search-dropdown,
        #citySearchDropdown,
        #placeSearchDropdown {
            position: absolute;
            z-index: 20050;
            left: 0;
            right: 0;
            top: 100%;
            margin-top: 2px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            max-height: 190px;
            overflow-y: auto;
            display: none;
            box-shadow: 0 10px 25px rgba(15,23,42,.12);
        }
        #citySearchDropdown.is-floating,
        #placeSearchDropdown.is-floating {
            position: fixed;
            right: auto;
            margin-top: 0;
        }
        #supplierModal .search-dropdown .item {
            padding: .45rem .65rem; cursor: pointer; font-size: .875rem; color: #334155;
        }
        #supplierModal .search-dropdown .item strong,
        #citySearchDropdown .item strong,
        #placeSearchDropdown .item strong {
            font-weight: 700;
            color: #0f172a;
        }
        #supplierModal .search-dropdown .item.is-empty {
            cursor: default; color: #94a3b8; font-style: italic;
        }
        #supplierModal .search-dropdown .item:hover { background: #f1f5f9; }
        #supplierModal .city-clear {
            position: absolute; right: .65rem; top: 2.05rem;
            border: 0; background: transparent; color: #94a3b8; font-size: .95rem;
            line-height: 1; padding: 0; display: none; cursor: pointer; z-index: 2;
        }
        #supplierModal .search-wrap.has-city .city-clear { display: inline-block; }
        #supplierModal .search-wrap.has-city #supplierCitySearch { padding-right: 1.8rem; }
        #supplierModal .supplier-of-field { position: relative; }
        #supplierModal .supplier-services-control {
            width: 100%; min-height: 40px; border: 1px solid #e2e8f0; border-radius: 4px;
            background: #fff; padding: .38rem .75rem; display: flex;
            align-items: center; justify-content: space-between; gap: .75rem;
            color: #0f172a; text-align: left; font-size: .9rem;
        }
        #supplierModal .supplier-services-summary {
            display: block; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            color: #0f172a; font-size: .9rem;
        }
        #supplierModal .supplier-services-placeholder { color: #94a3b8; }
        #supplierModal .supplier-of-panel {
            display: none; position: absolute; z-index: 1070; left: 0; right: 0;
            top: calc(100% - .35rem); grid-template-columns: repeat(3,minmax(0,1fr));
            gap: .4rem; padding: .75rem; max-height: 240px; overflow-y: auto;
            border: 1px solid #e2e8f0; border-radius: 4px; background: #fff;
            box-shadow: 0 14px 32px rgba(15,23,42,.14);
        }
        #supplierModal .supplier-of-panel.is-open { display: grid; }
        #supplierModal .supplier-of-panel label {
            display: flex; align-items: center; gap: .45rem; margin: 0;
            padding: .5rem .55rem; border: 1px solid #e2e8f0; border-radius: 4px;
            background: #fff; font-weight: 500; cursor: pointer; font-size: .8rem; color: #1e293b;
        }
        #supplierModal .supplier-of-panel label:hover {
            background: #f8fafc; border-color: #cbd5e1;
        }
        #supplierModal .supplier-of-panel input {
            width: 15px; height: 15px; accent-color: #1abc9c; flex: 0 0 auto;
        }
        #supplierModal .place-tags {
            min-height: 36px; display: flex; flex-wrap: wrap; gap: .4rem; align-items: center;
        }
        #supplierModal .place-tag {
            display: inline-flex; align-items: center; gap: 6px;
            background: #1abc9c; color: #fff; border-radius: 4px;
            padding: .28rem .55rem; font-size: .78rem; font-weight: 600;
        }
        #supplierModal .place-tag button {
            border: 0; background: transparent; color: #fff; padding: 0; line-height: 1;
            font-size: .95rem; cursor: pointer; opacity: .9;
        }
        #supplierModal .place-tag.empty-tag {
            background: #f97316; color: #fff; font-weight: 600;
        }
        #supplierModal .supplier-modal-footer {
            display: flex; justify-content: flex-start; align-items: center; gap: .55rem;
            padding: .95rem 1.35rem 1.15rem; border-top: 1px solid #e5e7eb;
            background: #fff;
        }
        #supplierModal .btn-supplier-save {
            min-width: 88px; height: 38px; padding: 0 1.1rem; border: 0; border-radius: 4px;
            background: #1abc9c; color: #fff; font-weight: 600; font-size: .92rem;
            box-shadow: none;
        }
        #supplierModal .btn-supplier-save:hover { background: #16a085; color: #fff; }
        #supplierModal .btn-supplier-save:disabled { opacity: .7; }
        #supplierModal .btn-supplier-cancel {
            min-width: 88px; height: 38px; padding: 0 1.1rem; border-radius: 4px;
            background: #fff; border: 1px solid #cbd5e1; color: #334155; font-weight: 500; font-size: .92rem;
        }
        #supplierModal .btn-supplier-cancel:hover { background: #f8fafc; }
        @media (max-width: 767.98px) {
            #supplierModal .supplier-modal-dialog { width: calc(100% - 1rem); margin: .5rem auto; }
            #supplierModal .supplier-of-panel { grid-template-columns: repeat(2,minmax(0,1fr)); }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed crm-suppliers-page">
    <div class="wrapper">
        <?php include __DIR__ . '/../includes/top-header.php'; ?>
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <?php include __DIR__ . '/../includes/page-header.php'; ?>
            <section class="content">
                <div class="container-fluid">
                    <div id="supplierAlert"></div>

                    <div class="supplier-page-head">
                        <div>
                            <h1 class="supplier-title">Supplier Master</h1>
                            <p class="supplier-subtitle">Manage Supplier Directory</p>
                        </div>
                        <div class="supplier-toolbar">
                            <div class="supplier-search">
                                <i class="fas fa-search"></i>
                                <input type="search" id="supplierTableSearch" placeholder="Search suppliers by name, contact, city...">
                            </div>
                            <select id="filterType" class="supplier-filter" aria-label="Supplier type">
                                <option value="">Supplier Type &nbsp; All</option>
                                <?php foreach ($supplierTypeMap as $key => $label): ?>
                                    <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="filterCity" class="supplier-filter" aria-label="City">
                                <option value="">City &nbsp; All</option>
                                <?php foreach ($supplierCityOptions as $cityOption): ?>
                                    <option value="<?= htmlspecialchars($cityOption, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cityOption, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="filterStatus" class="supplier-filter" aria-label="Status">
                                <option value="">Status &nbsp; All</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <button type="button" class="btn btn-add-supplier" id="btnAddSupplier">
                                <i class="fas fa-plus mr-1"></i> Add Supplier
                            </button>
                        </div>
                    </div>

                    <div class="supplier-kpis">
                        <div class="supplier-kpi">
                            <span class="supplier-kpi-icon"><i class="fas fa-users"></i></span>
                            <div>
                                <div class="supplier-kpi-label">Total Suppliers</div>
                                <div class="supplier-kpi-value"><?= number_format($supplierCount) ?></div>
                                <div class="supplier-kpi-trend">↑ Supplier directory</div>
                            </div>
                        </div>
                        <div class="supplier-kpi">
                            <span class="supplier-kpi-icon"><i class="fas fa-user-check"></i></span>
                            <div>
                                <div class="supplier-kpi-label">Active Suppliers</div>
                                <div class="supplier-kpi-value"><?= number_format($activeSupplierCount) ?></div>
                                <div class="supplier-kpi-trend">↑ Currently active</div>
                            </div>
                        </div>
                        <div class="supplier-kpi">
                            <span class="supplier-kpi-icon"><i class="fas fa-city"></i></span>
                            <div>
                                <div class="supplier-kpi-label">Cities Covered</div>
                                <div class="supplier-kpi-value"><?= number_format($cityCount) ?></div>
                                <div class="supplier-kpi-trend">↑ Supplier locations</div>
                            </div>
                        </div>
                        <div class="supplier-kpi">
                            <span class="supplier-kpi-icon"><i class="fas fa-th-large"></i></span>
                            <div>
                                <div class="supplier-kpi-label">Service Categories</div>
                                <div class="supplier-kpi-value"><?= number_format($serviceCategoryCount) ?></div>
                                <div class="supplier-kpi-trend">↑ Categories in use</div>
                            </div>
                        </div>
                    </div>

                    <div class="list-card">
                        <div class="list-card-body">
                            <table class="table" id="suppliersTable">
                                <thead>
                                    <tr>
                                        <th>Supplier Name</th>
                                        <th>City</th>
                                        <th>Services</th>
                                        <th>Contact Person</th>
                                        <th>Mobile</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Last Updated</th>
                                        <th style="width:110px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($suppliers as $i => $s): ?>
                                        <tr data-id="<?= (int) $s['id'] ?>"
                                            data-place-ids="<?= htmlspecialchars($s['_place_ids'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-types="<?= htmlspecialchars($s['_types'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-supplier-type="<?= htmlspecialchars((string) ($s['_type_keys'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-city="<?= htmlspecialchars($s['_city_line'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-status="<?= (int) ($s['is_active'] ?? 1) === 1 ? 'active' : 'inactive' ?>">
                                            <td>
                                                <div class="supplier-identity">
                                                    <span class="supplier-avatar"><?= htmlspecialchars(crmSupplierInitials($s['name']), ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span class="sup-name"><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                            </td>
                                            <td><span class="sup-city"><?= htmlspecialchars($s['_city_line'] !== '' ? $s['_city_line'] : '—', ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td>
                                                <?php if ($s['_service_labels']): ?>
                                                    <span class="sup-services-wrap">
                                                        <span class="sup-service"><?= htmlspecialchars($s['_service_labels'][0], ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php if (count($s['_service_labels']) > 1): ?>
                                                            <?php
                                                            $otherServicesHtml = '';
                                                            foreach (array_slice($s['_service_labels'], 1) as $otherServiceLabel) {
                                                                $otherServicesHtml .= '<span class="sup-service">'
                                                                    . htmlspecialchars($otherServiceLabel, ENT_QUOTES, 'UTF-8')
                                                                    . '</span>';
                                                            }
                                                            ?>
                                                            <button type="button" class="sup-services-more"
                                                                data-toggle="popover"
                                                                data-content="<?= htmlspecialchars($otherServicesHtml, ENT_QUOTES, 'UTF-8') ?>"
                                                                aria-label="Show <?= count($s['_service_labels']) - 1 ?> more services">
                                                                <i class="fas fa-plus"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php else: ?>—<?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($s['_contact_name'] !== '' ? $s['_contact_name'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($s['_mobile'] !== '' ? $s['_mobile'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($s['_email'] !== '' ? $s['_email'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <?php if ((int) ($s['is_active'] ?? 1) === 1): ?>
                                                    <span class="supplier-status active">Active</span>
                                                <?php else: ?>
                                                    <span class="supplier-status inactive">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars(crmSupplierUpdatedLabel($s['updated_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td>
                                                <div class="sup-actions">
                                                    <button type="button" class="sup-action js-view-supplier" title="View / Edit">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="sup-action delete js-delete-supplier" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="modal fade" id="supplierModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered supplier-modal-dialog" role="document">
            <div class="modal-content supplier-modal-content">
                <div class="modal-header supplier-modal-header">
                    <h5 class="modal-title mb-0" id="supplierModalTitle">Add Supplier</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="supplierForm" data-save-url="crm/ajax/save_supplier.php" onsubmit="return false;">
                    <div class="modal-body supplier-modal-body">
                        <input type="hidden" name="id" id="supplierId" value="0">
                        <input type="hidden" name="city_id" id="supplierCityId" value="0">
                        <input type="hidden" name="city_name" id="supplierCityName">
                        <input type="hidden" name="country_name" id="supplierCountryName">
                        <input type="hidden" id="supplierActive" value="1">

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="supplierName">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="supplierName" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="supplierWebsite">Website</label>
                                <input type="text" class="form-control" name="website" id="supplierWebsite">
                            </div>
                        </div>

                        <div class="supplier-contact-rows" id="supplierContactRows"></div>

                        <div class="form-row">
                            <div class="form-group col-md-6 search-wrap" id="supplierCityWrap">
                                <label for="supplierCitySearch">City</label>
                                <input type="text" class="form-control" id="supplierCitySearch" placeholder="Type city name..." autocomplete="off">
                                <button type="button" class="city-clear" id="supplierCityClear" title="Clear">&times;</button>
                                <div class="search-dropdown" id="citySearchDropdown"></div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="supplierAddress">Physical Address</label>
                                <input type="text" class="form-control" name="physical_address" id="supplierAddress">
                            </div>
                        </div>

                        <div class="form-group supplier-of-field">
                            <label>Supplier Of</label>
                            <button type="button" class="supplier-services-control" id="supplierServicesToggle" aria-expanded="false">
                                <span class="supplier-services-summary" id="supplierServicesSummary">
                                    <span class="supplier-services-placeholder">Select</span>
                                </span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="supplier-of-panel" id="supplierOfPanel">
                                    <?php foreach ($serviceMap as $key => $label): ?>
                                        <label>
                                            <input type="checkbox" class="js-supplier-of" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group search-wrap">
                            <label for="placeSearch">Search places</label>
                            <input type="text" class="form-control" id="placeSearch" placeholder="Search places" autocomplete="off">
                            <div class="search-dropdown" id="placeSearchDropdown"></div>
                        </div>

                        <div class="form-group mb-0">
                            <label>Supplier of places</label>
                            <div class="place-tags" id="placeTags">
                                <span class="place-tag empty-tag">No place selected</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer supplier-modal-footer">
                        <button type="button" class="btn btn-supplier-save" id="btnSaveSupplier">Save</button>
                        <button type="button" class="btn btn-supplier-cancel" data-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer-links.php'; ?>
    <script>
        window.SUPPLIER_SERVICE_MAP = <?= json_encode($serviceMap, JSON_UNESCAPED_UNICODE) ?>;
        window.SUPPLIER_TYPE_MAP = <?= json_encode($supplierTypeMap, JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="crm/assets/suppliers.js?v=19"></script>
</body>
</html>
