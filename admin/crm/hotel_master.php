<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/hotel_db.php';

crmEnsureHotelTables($conn);

$flashMsg = '';
$flashType = 'success';
if (!empty($_SESSION['hotel_flash'])) {
    $flashMsg = (string) $_SESSION['hotel_flash'];
    $flashType = !empty($_SESSION['hotel_flash_type']) ? (string) $_SESSION['hotel_flash_type'] : 'success';
    unset($_SESSION['hotel_flash'], $_SESSION['hotel_flash_type']);
}

$hotelDestinations = [];
$destRows = [];
$citiesByDest = [];
$hotelsByDest = [];

if (isset($conn) && $conn instanceof mysqli) {
    $destResult = $conn->query("SELECT id, name, slug, country FROM destinations WHERE is_active = 1 ORDER BY display_order ASC, name ASC");
    if ($destResult) {
        while ($destRow = $destResult->fetch_assoc()) {
            $hotelDestinations[] = [
                'id' => (int) $destRow['id'],
                'name' => $destRow['name'],
                'slug' => $destRow['slug'],
                'country' => $destRow['country'] ?? '',
            ];
        }
    }

    $statsSql = "SELECT d.id, d.name, d.slug,
            COUNT(DISTINCT h.id) AS hotel_count,
            COUNT(DISTINCT h.city_id) AS city_count,
            SUM(CASE WHEN h.is_default = 1 THEN 1 ELSE 0 END) AS default_count
        FROM destinations d
        LEFT JOIN crm_hotels h ON h.destination_id = d.id AND h.is_active = 1
        WHERE d.is_active = 1
        GROUP BY d.id, d.name, d.slug
        ORDER BY d.display_order ASC, d.name ASC";
    $statsRes = $conn->query($statsSql);
    if ($statsRes) {
        while ($statsRow = $statsRes->fetch_assoc()) {
            $destId = (int) ($statsRow['id'] ?? 0);
            $defaultCount = (int) ($statsRow['default_count'] ?? 0);
            $slug = trim((string) ($statsRow['slug'] ?? ''));
            if ($slug === '') {
                $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower((string) ($statsRow['name'] ?? 'dest')));
                $slug = trim((string) $slug, '-') ?: ('dest-' . $destId);
            }
            $destRows[] = [
                'id' => $destId,
                'slug' => $slug,
                'name' => (string) ($statsRow['name'] ?? ''),
                'hotels' => (int) ($statsRow['hotel_count'] ?? 0),
                'city_count' => (int) ($statsRow['city_count'] ?? 0),
                'badge' => $defaultCount > 0 ? ($defaultCount === 1 ? '1 default' : $defaultCount . ' default') : null,
                'cities' => [],
                'hotel_list' => [],
            ];
            $citiesByDest[$destId] = [];
            $hotelsByDest[$destId] = [];
        }
    }

    $citySql = "SELECT h.destination_id, c.id AS city_id, c.name AS city_name,
            COUNT(h.id) AS hotel_count,
            SUM(CASE WHEN h.is_default = 1 THEN 1 ELSE 0 END) AS default_count
        FROM crm_hotels h
        INNER JOIN cities c ON c.id = h.city_id
        WHERE h.is_active = 1
        GROUP BY h.destination_id, c.id, c.name
        ORDER BY c.name ASC";
    $cityRes = $conn->query($citySql);
    if ($cityRes) {
        while ($cityRow = $cityRes->fetch_assoc()) {
            $destId = (int) ($cityRow['destination_id'] ?? 0);
            $cityId = (int) ($cityRow['city_id'] ?? 0);
            $cityName = trim((string) ($cityRow['name'] ?? $cityRow['city_name'] ?? ''));
            if ($destId > 0 && $cityId > 0 && $cityName !== '') {
                if (!isset($citiesByDest[$destId])) {
                    $citiesByDest[$destId] = [];
                }
                $citiesByDest[$destId][] = [
                    'id' => $cityId,
                    'name' => $cityName,
                    'hotels' => (int) ($cityRow['hotel_count'] ?? 0),
                    'default_count' => (int) ($cityRow['default_count'] ?? 0),
                ];
            }
        }
    }

    $hotelListSql = "SELECT h.id, h.destination_id, h.city_id, h.hotel_name, h.star_category,
            h.star_rating, h.is_default, h.address, h.image_path, h.review_link,
            c.name AS city_name
        FROM crm_hotels h
        INNER JOIN cities c ON c.id = h.city_id
        WHERE h.is_active = 1
        ORDER BY h.is_default DESC, c.name ASC, h.hotel_name ASC";
    $hotelListRes = $conn->query($hotelListSql);
    if ($hotelListRes) {
        while ($hotelRow = $hotelListRes->fetch_assoc()) {
            $destId = (int) ($hotelRow['destination_id'] ?? 0);
            if ($destId <= 0) {
                continue;
            }
            if (!isset($hotelsByDest[$destId])) {
                $hotelsByDest[$destId] = [];
            }
            $hotelsByDest[$destId][] = [
                'id' => (int) ($hotelRow['id'] ?? 0),
                'city_id' => (int) ($hotelRow['city_id'] ?? 0),
                'city_name' => (string) ($hotelRow['city_name'] ?? ''),
                'hotel_name' => (string) ($hotelRow['hotel_name'] ?? ''),
                'star_category' => (string) ($hotelRow['star_category'] ?? ''),
                'star_rating' => (float) ($hotelRow['star_rating'] ?? 0),
                'is_default' => (int) ($hotelRow['is_default'] ?? 0),
                'address' => (string) ($hotelRow['address'] ?? ''),
                'image_path' => (string) ($hotelRow['image_path'] ?? ''),
                'review_link' => (string) ($hotelRow['review_link'] ?? ''),
            ];
        }
    }

    foreach ($destRows as $idx => $destRow) {
        $destId = (int) $destRow['id'];
        $destRows[$idx]['cities'] = $citiesByDest[$destId] ?? [];
        $destRows[$idx]['city_count'] = count($destRows[$idx]['cities']);
        $destRows[$idx]['city_tags'] = array_map(static function ($c) {
            return $c['name'];
        }, $destRows[$idx]['cities']);
        $destRows[$idx]['hotel_list'] = $hotelsByDest[$destId] ?? [];
        $hotelNames = array_map(static function ($h) {
            return $h['hotel_name'];
        }, $destRows[$idx]['hotel_list']);
        $destRows[$idx]['hotel_tags'] = $hotelNames;
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Hotel Master</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        .crm-hotel-master .content-wrapper>.content {
            background: #f4f7f6;
        }

        .crm-hotel-master .page-head-block {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .crm-hotel-master .page-head-block .title-block {
            display: flex;
            gap: 0.65rem;
            align-items: flex-start;
        }

        .crm-hotel-master .page-head-block .title-ico {
            font-size: 1.75rem;
            color: #343a40;
            line-height: 1;
            margin-top: 0.15rem;
        }

        .crm-hotel-master .page-head-block h1 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: #212529;
        }

        .crm-hotel-master .page-head-block .subtitle {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.2rem;
        }

        .crm-hotel-master .breadcrumbs {
            font-size: 0.875rem;
        }

        .crm-hotel-master .breadcrumbs a {
            color: #007bff;
        }

        .crm-hotel-master .breadcrumbs .bc-muted {
            color: #6c757d;
        }

        .crm-hotel-master .panel-card {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .crm-hotel-master .filters-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.9rem 1.15rem;
            border-bottom: 1px solid #e9ecef;
        }

        .crm-hotel-master .filters-head .filters-title {
            font-weight: 700;
            color: #212529;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .crm-hotel-master .filters-head .filters-title i {
            color: #6c757d;
        }

        .crm-hotel-master .btn-add-hotel {
            background: #007bff;
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 0.45rem 1rem;
            border-radius: 4px;
            white-space: nowrap;
        }

        .crm-hotel-master .btn-add-hotel:hover {
            color: #fff;
            background: #0069d9;
        }

        a.btn-add-hotel,
        a.btn-add-hotel:hover {
            color: #fff !important;
            text-decoration: none;
        }

        .crm-hotel-master .filters-row {
            padding: 1rem 1.15rem;
            display: grid;
            grid-template-columns: 1fr repeat(3, minmax(140px, 1fr));
            gap: 0.75rem;
        }

        @media (max-width: 991px) {
            .crm-hotel-master .filters-row {
                grid-template-columns: 1fr;
            }
        }

        .crm-hotel-master .filters-row .search-wrap {
            position: relative;
        }

        .crm-hotel-master .filters-row .search-wrap i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
            pointer-events: none;
        }

        .crm-hotel-master .filters-row .search-wrap input {
            padding-right: 2.25rem;
        }

        .crm-hotel-master .dest-list {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .crm-hotel-master .dest-row {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .03);
            margin-bottom: 0.65rem;
            overflow: hidden;
        }

        .crm-hotel-master .dest-row.hidden-filter {
            display: none !important;
        }

        .crm-hotel-master .dest-row-head {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid transparent;
        }

        .crm-hotel-master .dest-row-head.has-collapse.collapsed-here {
            border-bottom-color: #e9ecef;
        }

        .crm-hotel-master .dest-toggle {
            width: 34px;
            height: 34px;
            padding: 0;
            border: 1px solid #ced4da;
            border-radius: 4px;
            background: #fff;
            color: #495057;
            cursor: pointer;
            flex-shrink: 0;
        }

        .crm-hotel-master .dest-toggle:hover {
            background: #f8f9fa;
        }

        .crm-hotel-master .dest-toggle:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, .2);
        }

        .crm-hotel-master .dest-route {
            color: #6c757d;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .crm-hotel-master .dest-name-wrap {
            flex: 1;
            min-width: 180px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem;
        }

        .crm-hotel-master .dest-name {
            font-weight: 700;
            font-size: 1rem;
            color: #007bff;
            margin: 0;
            display: inline;
        }

        .crm-hotel-master .dest-stats {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .crm-hotel-master .badge-default {
            background: #28a745;
            color: #fff;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            border-radius: 1rem;
        }

        .crm-hotel-master .dest-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            margin-left: auto;
        }

        .crm-hotel-master .dest-collapse-in {
            padding: 1rem 1.15rem 1.25rem;
            background: linear-gradient(180deg, #f8fafc 0%, #fff 45%);
            font-size: 0.9rem;
            color: #64748b;
            border-top: 1px solid #e9ecef;
        }

        .crm-hotel-master .dest-hotels-heading {
            font-weight: 700;
            color: #334155;
            margin-bottom: 0.85rem;
            font-size: 0.95rem;
        }

        .crm-hotel-master .hotel-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1rem;
        }

        .crm-hotel-master .hotel-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
            display: flex;
            flex-direction: column;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            height: 100%;
        }

        .crm-hotel-master .hotel-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.1);
            border-color: #cbd5e1;
        }

        .crm-hotel-master .hotel-card-media {
            position: relative;
            height: 148px;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 45%, #38bdf8 100%);
            overflow: hidden;
        }

        .crm-hotel-master .hotel-card-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .crm-hotel-master .hotel-card-media-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.85);
            font-size: 2.4rem;
        }

        .crm-hotel-master .hotel-card-badges {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            display: flex;
            justify-content: space-between;
            gap: 0.4rem;
            pointer-events: none;
        }

        .crm-hotel-master .hotel-card-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.72);
            color: #fff;
        }

        .crm-hotel-master .hotel-card-badge.is-default {
            background: #16a34a;
        }

        .crm-hotel-master .hotel-card-badge.is-star {
            background: rgba(245, 158, 11, 0.95);
            color: #111;
            margin-left: auto;
        }

        .crm-hotel-master .hotel-card-body {
            padding: 0.9rem 1rem 0.75rem;
            flex: 1 1 auto;
        }

        .crm-hotel-master .hotel-card-title {
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 0.35rem;
            line-height: 1.3;
        }

        .crm-hotel-master .hotel-card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem 0.75rem;
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 0.45rem;
        }

        .crm-hotel-master .hotel-card-meta i {
            color: #2563eb;
            width: 14px;
            text-align: center;
        }

        .crm-hotel-master .hotel-card-address {
            font-size: 0.78rem;
            color: #94a3b8;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.2em;
        }

        .crm-hotel-master .hotel-card-footer {
            padding: 0.75rem 1rem 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            border-top: 1px solid #f1f5f9;
            background: #fafbfc;
        }

        .crm-hotel-master .hotel-card-footer .btn {
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0.3rem 0.65rem;
            border-radius: 8px;
        }

        .crm-hotel-master .dest-city-empty {
            background: #fff;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 1.25rem;
            color: #64748b;
            text-align: center;
        }

        #hotelDetailModal .hotel-detail-dialog {
            max-width: 860px;
            width: calc(100% - 1.5rem);
        }

        #hotelDetailModal .modal-content {
            border: none;
            border-radius: 14px;
            overflow: hidden;
        }

        #hotelDetailModal .hotel-detail-hd {
            background: linear-gradient(125deg, #0f172a 0%, #1e3a8a 55%, #2563eb 100%);
            color: #fff;
            border: none;
            padding: 1rem 1.25rem;
        }

        #hotelDetailModal .hotel-detail-hd .close {
            color: #fff;
            text-shadow: none;
            opacity: 0.9;
        }

        #hotelDetailModal .hotel-detail-bd {
            padding: 0;
            background: #f8fafc;
            max-height: calc(100vh - 180px);
            overflow-y: auto;
        }

        #hotelDetailModal .hotel-detail-hero {
            position: relative;
            height: 220px;
            background: linear-gradient(135deg, #1e3a8a, #38bdf8);
        }

        #hotelDetailModal .hotel-detail-hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        #hotelDetailModal .hotel-detail-hero-fallback {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,.85);
            font-size: 3.5rem;
        }

        #hotelDetailModal .hotel-detail-main {
            padding: 1.15rem 1.25rem 1.35rem;
        }

        #hotelDetailModal .hotel-detail-name {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 0.35rem;
        }

        #hotelDetailModal .hotel-detail-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 1rem;
        }

        #hotelDetailModal .hotel-detail-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0.28rem 0.65rem;
            border-radius: 999px;
            background: #e2e8f0;
            color: #334155;
        }

        #hotelDetailModal .hotel-detail-chip.is-default {
            background: #dcfce7;
            color: #166534;
        }

        #hotelDetailModal .hotel-detail-chip.is-star {
            background: #fef3c7;
            color: #92400e;
        }

        #hotelDetailModal .hotel-detail-section {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.9rem 1rem;
            margin-bottom: 0.85rem;
        }

        #hotelDetailModal .hotel-detail-section h6 {
            font-weight: 800;
            font-size: 0.85rem;
            color: #1e40af;
            margin: 0 0 0.55rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        #hotelDetailModal .hotel-detail-kv {
            margin: 0;
            font-size: 0.9rem;
            color: #334155;
            line-height: 1.55;
        }

        #hotelDetailModal .hotel-detail-list {
            margin: 0;
            padding-left: 1.1rem;
            font-size: 0.88rem;
            color: #475569;
        }

        #hotelDetailModal .hotel-detail-list li {
            margin-bottom: 0.35rem;
        }

        #hotelDetailModal .hotel-detail-ft {
            border-top: 1px solid #e2e8f0;
            background: #fff;
        }

        .crm-hotel-master .btn-view-city-hotels {
            background: #fff;
            border: 1px solid #17a2b8;
            color: #17a2b8;
            font-weight: 600;
            padding: 0.25rem 0.65rem;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .crm-hotel-master .btn-view-city-hotels:hover {
            background: #e8f7fa;
            color: #117a8b;
        }

        #hotelCityHotelsModal .hotel-city-hotels-dialog {
            max-width: 720px;
            width: calc(100% - 1.5rem);
            margin: 1rem auto;
        }

        #hotelCityHotelsModal .modal-content.hotel-city-hotels-shell {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
        }

        #hotelCityHotelsModal .modal-header.hotel-city-hotels-hd {
            background: linear-gradient(125deg, #0f766e 0%, #14b8a6 55%, #2dd4bf 100%);
            color: #fff;
            border: none;
            padding: 1rem 1.25rem;
        }

        #hotelCityHotelsModal .modal-header.hotel-city-hotels-hd .modal-title {
            font-weight: 800;
            font-size: 1.05rem;
        }

        #hotelCityHotelsModal .modal-header.hotel-city-hotels-hd .close {
            color: #fff;
            text-shadow: none;
            opacity: 0.9;
        }

        #hotelCityHotelsModal .modal-body.hotel-city-hotels-bd {
            padding: 1rem 1.25rem;
            background: #fafbfc;
            max-height: calc(100vh - 12rem);
            overflow-y: auto;
        }

        #hotelCityHotelsModal .hotel-city-hotels-table {
            margin-bottom: 0;
            font-size: 0.875rem;
            background: #fff;
        }

        #hotelCityHotelsModal .hotel-city-hotels-table th {
            background: #f8f9fa;
            font-weight: 700;
            white-space: nowrap;
        }

        #hotelCityHotelsModal .badge-hotel-default {
            background: #28a745;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.15rem 0.45rem;
            border-radius: 1rem;
        }

        #hotelCityHotelsModal .hotel-city-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            white-space: nowrap;
        }

        #hotelCityHotelsModal .btn-hotel-edit,
        #hotelCityHotelsModal .btn-hotel-delete {
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.2rem 0.55rem;
        }

        #hotelFormModal .hotel-current-image-wrap {
            margin-top: 0.65rem;
            display: none;
        }

        #hotelFormModal .hotel-current-image-wrap.is-visible {
            display: block;
        }

        #hotelFormModal .hotel-current-image-wrap img {
            max-width: 100%;
            max-height: 120px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            object-fit: cover;
        }

        #hotelFormModal .btn-hotel-delete-form {
            background: #dc2626;
            border: none;
            color: #fff;
            font-weight: 700;
            padding: 0.55rem 1.1rem;
            border-radius: 8px;
            display: none;
        }

        #hotelFormModal .btn-hotel-delete-form:hover {
            background: #b91c1c;
            color: #fff;
        }

        #hotelFormModal.is-edit-mode .btn-hotel-delete-form {
            display: inline-flex;
            align-items: center;
        }

        .crm-hotel-master .btn-outline-add {
            background: #fff;
            border: 1px solid #007bff;
            color: #007bff;
            font-weight: 600;
            padding: 0.35rem 0.85rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .crm-hotel-master .btn-outline-add:hover {
            background: #f0f7ff;
            color: #0056b3;
        }

        .crm-hotel-master .btn-view-dest {
            background: #fff;
            border: 1px solid #ced4da;
            color: #6c757d;
            font-weight: 600;
            padding: 0.35rem 0.85rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .crm-hotel-master .btn-view-dest:hover {
            background: #f8f9fa;
            color: #495057;
        }

        /* Hotel form modal */
        #hotelFormModal .hotel-form-dialog {
            max-width: 1100px;
            width: calc(100% - 1.5rem);
            max-height: calc(100vh - 2rem);
            margin: 1rem auto;
            display: flex;
            align-items: stretch;
        }
        #hotelFormModal .modal-content.hotel-form-shell {
            border: none;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 2rem);
            overflow: hidden;
            min-height: 0;
        }
        #hotelFormModal #hotelFormInner {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            max-height: 100%;
            overflow: hidden;
        }
        #hotelFormModal .modal-header.hotel-form-hd {
            flex: 0 0 auto;
            background: linear-gradient(125deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
            color: #fff;
            border: none;
            padding: 1rem 1.25rem;
        }
        #hotelFormModal .modal-header.hotel-form-hd .modal-title {
            font-weight: 800;
            font-size: 1.1rem;
        }
        #hotelFormModal .modal-header.hotel-form-hd .close {
            color: #fff;
            text-shadow: none;
            opacity: 0.9;
        }
        #hotelFormModal .modal-body.hotel-modal-bd {
            flex: 1 1 auto;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            padding: 1rem 1.25rem;
            background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
        }
        #hotelFormModal .hotel-modal-bd label {
            font-weight: 700;
            color: #212529;
            margin-bottom: 0.35rem;
            font-size: 0.85rem;
        }
        #hotelFormModal .label-req::after {
            content: " *";
            color: #dc3545;
        }
        #hotelFormModal .text-help {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        #hotelFormModal .text-disclaimer {
            font-size: 0.75rem;
            color: #dc3545;
            margin-top: 0.35rem;
        }
        #hotelFormModal .text-tip {
            font-size: 0.75rem;
            color: #28a745;
            margin-top: 0.35rem;
        }
        #hotelFormModal .hotel-info-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 1rem;
            background: #fff;
        }
        #hotelFormModal .hotel-panel-hd-blue {
            background: #007bff;
            color: #fff;
            font-weight: 700;
            padding: 0.55rem 1rem;
            font-size: 0.95rem;
            margin: 0;
        }
        #hotelFormModal .hotel-info-card-bd {
            padding: 1rem;
        }
        #hotelFormModal .hotel-sub-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 1rem;
            background: #fff;
        }
        #hotelFormModal .hotel-sub-card:last-child {
            margin-bottom: 0;
        }
        #hotelFormModal .hotel-sub-card-bd {
            padding: 1rem;
        }
        #hotelFormModal .hotel-panel-hd-green,
        #hotelFormModal .hotel-panel-hd-teal {
            color: #fff;
            font-weight: 700;
            padding: 0.5rem 0.85rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin: 0;
        }
        #hotelFormModal .hotel-panel-hd-green {
            background: #28a745;
        }
        #hotelFormModal .hotel-panel-hd-teal {
            background: #17a2b8;
        }
        #hotelFormModal .hotel-panel-hd-green .btn-sm-h,
        #hotelFormModal .hotel-panel-hd-teal .btn-sm-h {
            background: rgba(255, 255, 255, .2);
            border: 1px solid rgba(255, 255, 255, .5);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
        }
        #hotelFormModal .hotel-empty-box {
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 1.5rem 1rem;
            text-align: center;
            color: #6c757d;
            font-size: 0.85rem;
            background: #fff;
            margin-bottom: 0;
        }
        #hotelFormModal .hotel-dynamic-list {
            margin: 0;
            padding: 0;
        }
        #hotelFormModal .hotel-line-item {
            position: relative;
            padding-top: 0.25rem;
            padding-right: 1.75rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        #hotelFormModal .hotel-line-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        #hotelFormModal .hotel-line-item .hotel-line-remove {
            position: absolute;
            top: 0;
            right: 0;
            padding: 0.15rem 0.35rem;
            font-size: 1rem;
            line-height: 1;
            color: #adb5bd;
            opacity: 0.85;
        }
        #hotelFormModal .hotel-line-item .hotel-line-remove:hover {
            color: #dc3545;
            opacity: 1;
        }
        #hotelFormModal .hotel-line-item .form-group:last-child {
            margin-bottom: 0;
        }
        #hotelFormModal .hotel-line-item .input-group-text {
            background: #e9ecef;
            color: #495057;
            font-weight: 600;
        }
        #hotelFormModal .modal-footer.hotel-form-ft {
            flex: 0 0 auto;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-top: 1px solid #e2e8f0;
            padding: 0.85rem 1.25rem 1rem;
            gap: 0.5rem;
        }
        #hotelFormModal .btn-hotel-primary {
            background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
            border: none;
            color: #fff;
            font-weight: 700;
            padding: 0.5rem 1.2rem;
            border-radius: 8px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
        }
        #hotelFormModal .btn-hotel-primary:hover {
            color: #fff;
            transform: translateY(-1px);
        }
        #hotelFormModal .btn-hotel-ghost {
            background: #fff;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-weight: 700;
            padding: 0.5rem 1.1rem;
            border-radius: 8px;
        }
        #hotelFormModal .btn-hotel-ghost:hover {
            background: #f8fafc;
            color: #334155;
        }
        #hotelFormModal .hotel-dest-combobox,
        #hotelFormModal .hotel-city-combobox {
            position: relative;
        }
        #hotelFormModal .hotel-dest-menu,
        #hotelFormModal .hotel-city-menu {
            position: absolute;
            top: calc(100% + 2px);
            left: 0;
            right: 0;
            z-index: 1060;
            max-height: 220px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.12);
        }
        #hotelFormModal .hotel-dest-item,
        #hotelFormModal .hotel-city-item {
            display: block;
            width: 100%;
            padding: 0.55rem 0.75rem;
            border: 0;
            background: transparent;
            color: #212529;
            text-align: left;
            cursor: pointer;
        }
        #hotelFormModal .hotel-dest-item:hover,
        #hotelFormModal .hotel-dest-item:focus,
        #hotelFormModal .hotel-city-item:hover,
        #hotelFormModal .hotel-city-item:focus {
            background: #f1f3f5;
            outline: none;
        }
        #hotelFormModal .hotel-dest-empty,
        #hotelFormModal .hotel-city-empty {
            padding: 0.65rem 0.75rem;
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* View destination modal (list row) */
        #hotelDestViewModal .hotel-dest-view-dialog {
            max-width: 520px;
            width: calc(100% - 1.5rem);
            margin: 1rem auto;
        }
        #hotelDestViewModal .modal-content.hotel-dest-view-shell {
            border: none;
            border-radius: 12px;
            overflow-x: hidden;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 2rem);
        }
        #hotelDestViewModal #hotelDestViewInner {
            display: flex;
            flex-direction: column;
            flex: 0 0 auto;
            max-height: inherit;
        }
        #hotelDestViewModal .modal-header.hotel-dest-view-hd {
            flex: 0 0 auto;
            background: linear-gradient(125deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
            color: #fff;
            border: none;
            padding: 1rem 1.25rem;
        }
        #hotelDestViewModal .modal-header.hotel-dest-view-hd .modal-title {
            font-weight: 800;
            font-size: 1.1rem;
        }
        #hotelDestViewModal .modal-header.hotel-dest-view-hd .close {
            color: #fff;
            text-shadow: none;
            opacity: 0.9;
        }
        #hotelDestViewModal .modal-body.hotel-dest-view-bd {
            flex: 0 0 auto;
            padding: 1rem 1.35rem;
            background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
        }
        #hotelDestViewModal .hotel-dest-view-table {
            margin-bottom: 0;
            font-size: 0.9rem;
        }
        #hotelDestViewModal .hotel-dest-view-table th {
            width: 38%;
            background: #f8f9fa;
            font-weight: 700;
            color: #212529;
            vertical-align: middle;
            padding: 0.55rem 0.75rem;
            border-color: #dee2e6;
        }
        #hotelDestViewModal .hotel-dest-view-table td {
            vertical-align: middle;
            padding: 0.55rem 0.75rem;
            border-color: #dee2e6;
            color: #334155;
        }
        #hotelDestViewModal .modal-footer.hotel-dest-view-ft {
            flex: 0 0 auto;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-top: 1px solid #e2e8f0;
            padding: 0.85rem 1.25rem 1rem;
            gap: 0.5rem;
        }
        #hotelDestViewModal .btn-hotel-ghost {
            background: #fff;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-weight: 700;
            padding: 0.5rem 1.1rem;
            border-radius: 8px;
        }
        #hotelDestViewModal .btn-hotel-ghost:hover {
            background: #f8fafc;
            color: #334155;
        }

        @media (max-height: 720px) {
            #hotelFormModal .hotel-form-dialog,
            #hotelFormModal .modal-content.hotel-form-shell {
                max-height: calc(100vh - 1rem);
            }
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper crm-hotel-master">

        <?php include __DIR__ . '/../includes/top-header.php'; ?>
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="content-wrapper">

            <?php include __DIR__ . '/../includes/page-header.php'; ?>

            <section class="content">
                <div class="container-fluid">

                    <div class="page-head-block">
                        <div class="title-block">
                            <i class="fas fa-building title-ico"></i>
                            <div>
                                <h1>Hotel Master</h1>
                                <div class="subtitle">Organized by destinations</div>
                            </div>
                        </div>
                        <nav class="breadcrumbs">
                            <a href="dashboard.php">Home</a> / <a href="crm/hotel_master.php">Masters</a> / <span class="bc-muted">Hotel</span>
                        </nav>
                    </div>

                    <?php if ($flashMsg !== '') { ?>
                        <div class="alert alert-<?= htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show shadow-sm" role="alert">
                            <?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <?php } ?>

                    <div class="panel-card">
                        <div class="filters-head">
                            <div class="filters-title">
                                <i class="fas fa-filter"></i>
                                Filters &amp; Actions
                            </div>
                            <button type="button" class="btn btn-add-hotel" id="btnOpenHotelFormModal"><i class="fas fa-plus mr-1"></i> Add New Hotel</button>
                        </div>
                        <div class="filters-row">
                            <div class="search-wrap">
                                <input type="search" class="form-control" placeholder="Search hotels, cities...">
                                <i class="fas fa-search"></i>
                            </div>
                            <select class="form-control">
                                <option selected>All Destinations</option>
                            </select>
                            <select class="form-control">
                                <option selected>All Cities</option>
                            </select>
                            <select class="form-control">
                                <option selected>All Star Categories</option>
                            </select>
                        </div>
                    </div>

                    <div class="dest-list" id="hotelDestList">
                        <?php
                        $hotelWord = function ($n) {
                            return $n === 1 ? '1 hotel' : $n . ' hotels';
                        };
                        $cityWord = function ($n) {
                            return $n === 1 ? '1 city' : $n . ' cities';
                        };
                        if (empty($destRows)) {
                            echo '<div class="alert alert-info mb-0">No active destinations found. Add destinations first, then create hotels.</div>';
                        }
                        foreach ($destRows as $row) {
                            $slug = $row['slug'];
                            $h = (int) $row['hotels'];
                            $c = (int) $row['city_count'];
                            $dn = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                            $badgeAttr = $row['badge'] !== null ? htmlspecialchars($row['badge'], ENT_QUOTES, 'UTF-8') : '';
                            $citiesJson = htmlspecialchars(json_encode($row['city_tags']), ENT_QUOTES, 'UTF-8');
                            $searchBlob = strtolower($row['name'] . ' ' . implode(' ', $row['city_tags']) . ' ' . implode(' ', $row['hotel_tags'] ?? []));
                            ?>
                            <div class="dest-row"
                                id="hotel-dest-row-<?= htmlspecialchars($slug) ?>"
                                data-dest-id="<?= (int) $row['id'] ?>"
                                data-slug="<?= htmlspecialchars($slug) ?>"
                                data-cities="<?= $citiesJson ?>"
                                data-search="<?= htmlspecialchars($searchBlob) ?>">
                                <div class="dest-row-head has-collapse collapsed-here">
                                    <button type="button" class="dest-toggle" data-toggle="collapse"
                                        data-target="#hotel-collapse-<?= htmlspecialchars($slug) ?>"
                                        aria-expanded="false" aria-controls="hotel-collapse-<?= htmlspecialchars($slug) ?>"
                                        title="Expand">
                                        <i class="fas fa-plus collapse-icon-plus"></i>
                                    </button>
                                    <i class="fas fa-map-marker-alt dest-route" aria-hidden="true"></i>
                                    <div class="dest-name-wrap">
                                        <span class="dest-name"><?= htmlspecialchars($row['name']) ?></span>
                                        <span class="dest-stats"><?= $hotelWord($h) ?> (<?= $cityWord($c) ?>)</span>
                                        <?php if ($row['badge'] !== null) { ?>
                                            <span class="badge-default"><?= htmlspecialchars($row['badge']) ?></span>
                                        <?php } ?>
                                    </div>
                                    <div class="dest-actions">
                                        <button type="button" class="btn btn-outline-add btn-hotel-add-row" data-destination="<?= $dn ?>"><i class="fas fa-plus mr-1"></i> Add Hotel</button>
                                        <button type="button" class="btn btn-view-dest btn-hotel-view-dest" data-destination="<?= $dn ?>" data-hotels="<?= $h ?>" data-cities="<?= $c ?>" data-badge="<?= $badgeAttr ?>"><i class="far fa-eye mr-1"></i> View Dest.</button>
                                    </div>
                                </div>
                                <div class="collapse" id="hotel-collapse-<?= htmlspecialchars($slug) ?>">
                                    <div class="dest-collapse-in">
                                        <?php
                                        $hotelList = $row['hotel_list'] ?? [];
                                        if (!empty($hotelList)) {
                                            ?>
                                            <p class="dest-hotels-heading">Hotels in <?= htmlspecialchars($row['name']) ?></p>
                                            <div class="hotel-card-grid">
                                                <?php foreach ($hotelList as $hotel) {
                                                    $hid = (int) ($hotel['id'] ?? 0);
                                                    $hName = (string) ($hotel['hotel_name'] ?? '');
                                                    $hCity = (string) ($hotel['city_name'] ?? '');
                                                    $hStar = (string) ($hotel['star_category'] ?? '');
                                                    $hRating = (float) ($hotel['star_rating'] ?? 0);
                                                    $hDefault = (int) ($hotel['is_default'] ?? 0) === 1;
                                                    $hAddr = (string) ($hotel['address'] ?? '');
                                                    $hImg = (string) ($hotel['image_path'] ?? '');
                                                    $searchExtra = strtolower($hName . ' ' . $hCity);
                                                    ?>
                                                    <article class="hotel-card" data-hotel-search="<?= htmlspecialchars($searchExtra, ENT_QUOTES, 'UTF-8') ?>">
                                                        <div class="hotel-card-media">
                                                            <?php if ($hImg !== '') { ?>
                                                                <img src="<?= htmlspecialchars($hImg, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($hName, ENT_QUOTES, 'UTF-8') ?>">
                                                            <?php } else { ?>
                                                                <div class="hotel-card-media-fallback"><i class="fas fa-hotel"></i></div>
                                                            <?php } ?>
                                                            <div class="hotel-card-badges">
                                                                <?php if ($hDefault) { ?>
                                                                    <span class="hotel-card-badge is-default"><i class="fas fa-check"></i> Default</span>
                                                                <?php } else { ?>
                                                                    <span></span>
                                                                <?php } ?>
                                                                <?php if ($hStar !== '') { ?>
                                                                    <span class="hotel-card-badge is-star"><i class="fas fa-star"></i> <?= htmlspecialchars($hStar) ?></span>
                                                                <?php } ?>
                                                            </div>
                                                        </div>
                                                        <div class="hotel-card-body">
                                                            <h3 class="hotel-card-title"><?= htmlspecialchars($hName) ?></h3>
                                                            <div class="hotel-card-meta">
                                                                <?php if ($hCity !== '') { ?>
                                                                    <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($hCity) ?></span>
                                                                <?php } ?>
                                                                <?php if ($hRating > 0) { ?>
                                                                    <span><i class="fas fa-star"></i> <?= number_format($hRating, 1) ?>/5</span>
                                                                <?php } ?>
                                                            </div>
                                                            <p class="hotel-card-address"><?= $hAddr !== '' ? htmlspecialchars($hAddr) : 'No address added' ?></p>
                                                        </div>
                                                        <div class="hotel-card-footer">
                                                            <button type="button" class="btn btn-primary btn-hotel-view-detail" data-hotel-id="<?= $hid ?>">
                                                                <i class="far fa-eye mr-1"></i> View Hotel
                                                            </button>
                                                            <button type="button" class="btn btn-outline-primary btn-hotel-edit" data-hotel-id="<?= $hid ?>">
                                                                <i class="fas fa-edit mr-1"></i> Edit
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger btn-hotel-delete" data-hotel-id="<?= $hid ?>" data-hotel-name="<?= htmlspecialchars($hName, ENT_QUOTES, 'UTF-8') ?>">
                                                                <i class="fas fa-trash-alt mr-1"></i> Delete
                                                            </button>
                                                        </div>
                                                    </article>
                                                <?php } ?>
                                            </div>
                                        <?php } else { ?>
                                            <div class="dest-city-empty">No hotels added yet for this destination. Use <strong>Add Hotel</strong> to add properties.</div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                </div>
            </section>

        </div>

        <!-- Create / Edit Hotel modal -->
        <div class="modal fade" id="hotelFormModal" tabindex="-1" role="dialog" aria-labelledby="hotelFormModalLabel" aria-hidden="true" data-backdrop="static">
            <div class="modal-dialog modal-dialog-centered modal-xl hotel-form-dialog" role="document">
                <div class="modal-content hotel-form-shell">
                    <form id="hotelFormInner" action="#" method="post" onsubmit="return false;"
                        data-hotel-destinations="<?= htmlspecialchars(json_encode($hotelDestinations), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" id="hotelFormId" value="">
                        <div class="modal-header hotel-form-hd text-white">
                            <h5 class="modal-title mb-0" id="hotelFormModalLabel">Create Hotel</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body hotel-modal-bd">
                            <div class="row">
                                <div class="col-lg-8 mb-3 mb-lg-0">
                                    <div class="hotel-info-card">
                                        <div class="hotel-panel-hd-blue">Hotel Information</div>
                                        <div class="hotel-info-card-bd">
                                            <div class="row">
                                                <div class="col-md-7">
                                                    <div class="form-group">
                                                        <label class="label-req" for="hotelFormDestinationInput">Destination</label>
                                                        <div class="hotel-dest-combobox js-hotel-dest-combobox">
                                                            <input type="hidden" name="destination" id="hotelFormDestinationId" class="js-hotel-dest-id" value="">
                                                            <input type="text" class="form-control js-hotel-dest-input" id="hotelFormDestinationInput"
                                                                placeholder="Type to search destination" autocomplete="off">
                                                            <div class="hotel-dest-menu js-hotel-dest-menu" style="display:none;"></div>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="label-req" for="hotelFormCityInput">City</label>
                                                        <div class="hotel-city-combobox js-hotel-city-combobox">
                                                            <input type="hidden" name="city_id" id="hotelFormCityId" class="js-hotel-city-id" value="">
                                                            <input type="text" class="form-control js-hotel-city-input" id="hotelFormCityInput"
                                                                placeholder="Type to search city" autocomplete="off">
                                                            <div class="hotel-city-menu js-hotel-city-menu" style="display:none;"></div>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="label-req" for="hotelFormName">Hotel Name</label>
                                                        <input type="text" class="form-control" id="hotelFormName" name="hotel_name" placeholder="Enter hotel name" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <div class="custom-control custom-checkbox">
                                                            <input type="checkbox" class="custom-control-input" id="hotelFormDefault" name="default_hotel">
                                                            <label class="custom-control-label font-weight-bold" for="hotelFormDefault">Set as default hotel for this city</label>
                                                        </div>
                                                        <div class="text-help">Only one hotel can be set as default per city</div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="label-req" for="hotelFormStarCat">Star Category</label>
                                                        <select class="form-control" id="hotelFormStarCat" name="star_category" required>
                                                            <option selected>1 Star</option>
                                                            <option>2 Star</option>
                                                            <option>3 Star</option>
                                                            <option>4 Star</option>
                                                            <option>5 Star</option>
                                                        </select>
                                                        <div class="text-help">The official star category of the hotel</div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="hotelFormStarRating">Star Rating</label>
                                                        <input type="number" class="form-control" id="hotelFormStarRating" name="star_rating" value="0" min="0" max="5" step="0.1">
                                                        <div class="text-help">The average customer rating out of 5</div>
                                                    </div>
                                                    <div class="form-group mb-md-0">
                                                        <label for="hotelFormReviewLink">Review Link</label>
                                                        <input type="url" class="form-control" id="hotelFormReviewLink" name="review_link" placeholder="https://...">
                                                    </div>
                                                </div>
                                                <div class="col-md-5">
                                                    <div class="form-group">
                                                        <label for="hotelFormImage">Hotel Image</label>
                                                        <div class="custom-file">
                                                            <input type="file" class="custom-file-input" id="hotelFormImage" name="image" accept=".jpg,.jpeg,.png,.gif">
                                                            <label class="custom-file-label" for="hotelFormImage">Choose file</label>
                                                        </div>
                                                        <div class="text-help">Recommended: 800×600 px. Max 2MB. JPG, PNG, GIF.</div>
                                                        <div class="text-disclaimer"><i class="fas fa-exclamation-triangle mr-1"></i>Use royalty-free or owned images only.</div>
                                                        <div class="text-tip"><i class="fas fa-check-circle mr-1"></i>Sources:
                                                            <a href="https://www.pexels.com" target="_blank" rel="noopener">Pexels</a> /
                                                            <a href="https://pixabay.com" target="_blank" rel="noopener">Pixabay</a> /
                                                            <a href="https://unsplash.com" target="_blank" rel="noopener">Unsplash</a>.
                                                        </div>
                                                        <div class="hotel-current-image-wrap" id="hotelFormCurrentImageWrap">
                                                            <div class="text-help mb-1">Current image</div>
                                                            <img src="" alt="Current hotel" id="hotelFormCurrentImage">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group mb-0">
                                                <label for="hotelFormAddress">Address</label>
                                                <textarea class="form-control" id="hotelFormAddress" name="address" rows="4" placeholder="Enter hotel address"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="hotel-sub-card">
                                        <div class="hotel-panel-hd-green">
                                            <span>Room Types</span>
                                            <button type="button" class="btn btn-sm-h" id="btnAddRoomType">+ Add Room Type</button>
                                        </div>
                                        <div class="hotel-sub-card-bd">
                                            <div id="hotelRoomTypesEmpty" class="hotel-empty-box mb-0">No room types added yet. Click &quot;Add Room Type&quot; to add one.</div>
                                            <div id="hotelRoomTypesList" class="hotel-dynamic-list"></div>
                                        </div>
                                    </div>
                                    <div class="hotel-sub-card">
                                        <div class="hotel-panel-hd-teal">
                                            <span>Meal Plans</span>
                                            <button type="button" class="btn btn-sm-h" id="btnAddMealPlan">+ Add Meal Plan</button>
                                        </div>
                                        <div class="hotel-sub-card-bd">
                                            <div id="hotelMealPlansEmpty" class="hotel-empty-box mb-0">No meal plans added yet. Click &quot;Add Meal Plan&quot; to add one.</div>
                                            <div id="hotelMealPlansList" class="hotel-dynamic-list"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer hotel-form-ft justify-content-start flex-wrap">
                            <button type="submit" class="btn btn-hotel-primary" id="hotelFormSubmitBtn"><i class="fas fa-save mr-2"></i><span id="hotelFormSubmitText">Create Hotel</span></button>
                            <button type="button" class="btn btn-hotel-delete-form" id="hotelFormDeleteBtn"><i class="fas fa-trash-alt mr-2"></i>Delete Hotel</button>
                            <button type="button" class="btn btn-hotel-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View destination (list row) -->
        <div class="modal fade" id="hotelDestViewModal" tabindex="-1" role="dialog" aria-labelledby="hotelDestViewModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered hotel-dest-view-dialog" role="document">
                <div class="modal-content hotel-dest-view-shell">
                    <div id="hotelDestViewInner">
                        <div class="modal-header hotel-dest-view-hd text-white">
                            <h5 class="modal-title mb-0" id="hotelDestViewModalLabel">View Destination</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body hotel-dest-view-bd">
                            <p class="font-weight-bold mb-2" style="font-size: 0.95rem; color: #222;">Destination summary</p>
                            <table class="table table-bordered hotel-dest-view-table">
                                <tbody>
                                    <tr>
                                        <th scope="row">Destination</th>
                                        <td id="hotelDestViewName">—</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Hotels</th>
                                        <td id="hotelDestViewHotels">—</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Cities</th>
                                        <td id="hotelDestViewCities">—</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Default hotels note</th>
                                        <td id="hotelDestViewBadge">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="modal-footer hotel-dest-view-ft justify-content-start flex-wrap">
                            <button type="button" class="btn btn-hotel-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- City hotels list -->
        <div class="modal fade" id="hotelCityHotelsModal" tabindex="-1" role="dialog" aria-labelledby="hotelCityHotelsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered hotel-city-hotels-dialog" role="document">
                <div class="modal-content hotel-city-hotels-shell">
                    <div class="modal-header hotel-city-hotels-hd text-white">
                        <h5 class="modal-title mb-0" id="hotelCityHotelsModalLabel">City Hotels</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body hotel-city-hotels-bd" id="hotelCityHotelsBody">
                        <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin mr-1"></i> Loading hotels...</div>
                    </div>
                    <div class="modal-footer hotel-dest-view-ft justify-content-start flex-wrap">
                        <button type="button" class="btn btn-hotel-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hotel detail view -->
        <div class="modal fade" id="hotelDetailModal" tabindex="-1" role="dialog" aria-labelledby="hotelDetailModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered hotel-detail-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header hotel-detail-hd">
                        <h5 class="modal-title mb-0" id="hotelDetailModalLabel">Hotel Details</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body hotel-detail-bd" id="hotelDetailBody">
                        <div class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin mr-1"></i> Loading details...</div>
                    </div>
                    <div class="modal-footer hotel-detail-ft justify-content-start flex-wrap">
                        <button type="button" class="btn btn-primary" id="hotelDetailEditBtn"><i class="fas fa-edit mr-1"></i> Edit</button>
                        <button type="button" class="btn btn-outline-danger" id="hotelDetailDeleteBtn"><i class="fas fa-trash-alt mr-1"></i> Delete</button>
                        <button type="button" class="btn btn-hotel-ghost" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Close</button>
                    </div>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/../includes/footer-links.php'; ?>

    </div>

<script>
$(function () {
	var hotelRoomSeq = 0;
	var hotelMealSeq = 0;
	var $hotelDestWrap = $('.js-hotel-dest-combobox');
	var $hotelDestId = $('#hotelFormDestinationId');
	var $hotelDestInput = $('#hotelFormDestinationInput');
	var $hotelDestMenu = $('.js-hotel-dest-menu');
	var hotelDestinationOptions = [];
	var $hotelCityWrap = $('.js-hotel-city-combobox');
	var $hotelCityId = $('#hotelFormCityId');
	var $hotelCityInput = $('#hotelFormCityInput');
	var $hotelCityMenu = $('.js-hotel-city-menu');
	var hotelCitySearchUrl = 'crm/ajax/search_cities.php';
	var hotelCitySearchTimer = null;
	var hotelCityLastResults = [];

	try {
		hotelDestinationOptions = JSON.parse($('#hotelFormInner').attr('data-hotel-destinations') || '[]');
	} catch (e) {
		hotelDestinationOptions = [];
	}

	function hideHotelDestMenu() {
		$hotelDestMenu.hide().empty();
	}

	function renderHotelDestMenu(filterText) {
		var query = (filterText || '').toLowerCase().trim();
		var filtered = hotelDestinationOptions.filter(function (dest) {
			return !query || dest.name.toLowerCase().indexOf(query) >= 0;
		});

		$hotelDestMenu.empty();

		if (filtered.length === 0) {
			$hotelDestMenu.append('<div class="hotel-dest-empty">No destinations found</div>');
		} else {
			filtered.forEach(function (dest) {
				$hotelDestMenu.append(
					$('<button type="button" class="hotel-dest-item"></button>')
						.attr('data-id', dest.id)
						.text(dest.name)
				);
			});
		}

		$hotelDestMenu.show();
	}

	function selectHotelDestination(dest, opts) {
		opts = opts || {};
		$hotelDestId.val(dest.id);
		$hotelDestInput.val(dest.name);
		hideHotelDestMenu();
		if (!opts.keepCity) {
			resetHotelCityField();
		}
	}

	function syncHotelDestInputValue() {
		var text = $.trim($hotelDestInput.val());
		if (!text) {
			$hotelDestId.val('');
			return;
		}

		var exact = hotelDestinationOptions.find(function (dest) {
			return dest.name.toLowerCase() === text.toLowerCase();
		});

		$hotelDestId.val(exact ? exact.id : '');
	}

	function resetHotelDestinationField() {
		hideHotelDestMenu();
		$hotelDestId.val('');
		$hotelDestInput.val('');
	}

	function selectDestinationByName(name) {
		if (!name) {
			return;
		}
		var dest = hotelDestinationOptions.find(function (item) {
			return item.name === name;
		});
		if (dest) {
			selectHotelDestination(dest);
		}
	}

	if ($hotelDestWrap.length) {
		$hotelDestInput.on('focus click', function () {
			renderHotelDestMenu($hotelDestInput.val());
		}).on('input', function () {
			$hotelDestId.val('');
			renderHotelDestMenu($hotelDestInput.val());
		}).on('blur', function () {
			window.setTimeout(function () {
				hideHotelDestMenu();
				syncHotelDestInputValue();
			}, 150);
		});

		$hotelDestMenu.on('mousedown', '.hotel-dest-item', function (e) {
			e.preventDefault();
			var id = $(this).data('id');
			var dest = hotelDestinationOptions.find(function (item) {
				return String(item.id) === String(id);
			});
			if (dest) {
				selectHotelDestination(dest);
			}
		});
	}

	function hideHotelCityMenu() {
		$hotelCityMenu.hide().empty();
	}

	function formatHotelCityLabel(city) {
		var label = city.name || '';
		if (city.state_name) {
			label += ', ' + city.state_name;
		}
		if (city.country_name) {
			label += ' (' + city.country_name + ')';
		}
		if (city.airport_code) {
			label += ' [' + city.airport_code + ']';
		}
		return label;
	}

	function renderHotelCityMenu(cities) {
		hotelCityLastResults = cities || [];
		$hotelCityMenu.empty();

		if (!hotelCityLastResults.length) {
			$hotelCityMenu.append('<div class="hotel-city-empty">No cities found</div>');
		} else {
			hotelCityLastResults.forEach(function (city) {
				$hotelCityMenu.append(
					$('<button type="button" class="hotel-city-item"></button>')
						.attr('data-id', city.id)
						.attr('data-name', city.name)
						.text(formatHotelCityLabel(city))
				);
			});
		}

		$hotelCityMenu.show();
	}

	function fetchHotelCityOptions(query, callback) {
		$.getJSON(hotelCitySearchUrl, {
			q: query || '',
			destination_id: $hotelDestId.val() || ''
		}).done(function (res) {
			callback((res && res.success && res.data) ? res.data : []);
		}).fail(function () {
			callback([]);
		});
	}

	function loadHotelCityMenu(query) {
		window.clearTimeout(hotelCitySearchTimer);
		hotelCitySearchTimer = window.setTimeout(function () {
			fetchHotelCityOptions(query, renderHotelCityMenu);
		}, 200);
	}

	function selectHotelCity(city) {
		$hotelCityId.val(city.id);
		$hotelCityInput.val(city.name);
		hideHotelCityMenu();
	}

	function syncHotelCityInputValue() {
		var text = $.trim($hotelCityInput.val());
		if (!text) {
			$hotelCityId.val('');
			return;
		}

		var exact = hotelCityLastResults.find(function (city) {
			return String(city.name).toLowerCase() === text.toLowerCase();
		});

		if (exact) {
			$hotelCityId.val(exact.id);
			return;
		}

		$hotelCityId.val('');
	}

	function resetHotelCityField() {
		hideHotelCityMenu();
		hotelCityLastResults = [];
		$hotelCityId.val('');
		$hotelCityInput.val('');
	}

	if ($hotelCityWrap.length) {
		$hotelCityInput.on('focus click', function () {
			loadHotelCityMenu($hotelCityInput.val());
		}).on('input', function () {
			$hotelCityId.val('');
			loadHotelCityMenu($hotelCityInput.val());
		}).on('blur', function () {
			window.setTimeout(function () {
				hideHotelCityMenu();
				syncHotelCityInputValue();
			}, 150);
		});

		$hotelCityMenu.on('mousedown', '.hotel-city-item', function (e) {
			e.preventDefault();
			var id = $(this).data('id');
			var city = hotelCityLastResults.find(function (item) {
				return String(item.id) === String(id);
			});
			if (city) {
				selectHotelCity(city);
			}
		});
	}

	$('#hotelFormModal').on('click', function (e) {
		if ($hotelDestWrap.length && !$hotelDestWrap.is(e.target) && $hotelDestWrap.has(e.target).length === 0) {
			hideHotelDestMenu();
		}
		if ($hotelCityWrap.length && !$hotelCityWrap.is(e.target) && $hotelCityWrap.has(e.target).length === 0) {
			hideHotelCityMenu();
		}
	});

	$('.collapse').on('show.bs.collapse', function () {
		var id = $(this).attr('id');
		var btn = $('[data-target="#' + id + '"]');
		btn.find('.collapse-icon-plus').removeClass('fa-plus').addClass('fa-minus');
		btn.attr('aria-expanded', 'true');
	});
	$('.collapse').on('hide.bs.collapse', function () {
		var id = $(this).attr('id');
		var btn = $('[data-target="#' + id + '"]');
		btn.find('.collapse-icon-plus').removeClass('fa-minus').addClass('fa-plus');
		btn.attr('aria-expanded', 'false');
	});

	function refreshHotelRoomTypesVisibility() {
		var has = $('#hotelRoomTypesList').children().length > 0;
		$('#hotelRoomTypesEmpty').toggle(!has);
	}

	function refreshHotelMealPlansVisibility() {
		var has = $('#hotelMealPlansList').children().length > 0;
		$('#hotelMealPlansEmpty').toggle(!has);
	}

	function addHotelRoomTypeRow(data) {
		data = data || {};
		var idx = hotelRoomSeq++;
		var $block = $('<div class="hotel-line-item" data-room-index="' + idx + '"></div>');
		var $type = $('<input type="text" class="form-control" id="hotel_room_' + idx + '_type" name="room_types[' + idx + '][type]" placeholder="Enter room type (e.g. Standard, Deluxe, Suite)">').val(data.type || '');
		var $desc = $('<textarea class="form-control" id="hotel_room_' + idx + '_desc" name="room_types[' + idx + '][description]" rows="2" placeholder="Enter room description"></textarea>').val(data.description || '');
		var priceVal = (data.price !== undefined && data.price !== null && data.price !== '') ? data.price : '';
		var $price = $('<input type="number" class="form-control" id="hotel_room_' + idx + '_price" name="room_types[' + idx + '][price]" min="0" step="0.01" placeholder="Enter price">').val(priceVal);
		$block.append(
			'<button type="button" class="btn btn-link hotel-line-remove text-muted" aria-label="Remove room type" title="Remove">' +
				'<i class="fas fa-times"></i>' +
			'</button>'
		);
		$block.append($('<div class="form-group"></div>').append('<label for="hotel_room_' + idx + '_type">Room Type</label>').append($type));
		$block.append($('<div class="form-group"></div>').append('<label for="hotel_room_' + idx + '_desc">Description</label>').append($desc));
		$block.append(
			$('<div class="form-group mb-0"></div>')
				.append('<label for="hotel_room_' + idx + '_price">Price</label>')
				.append(
					$('<div class="input-group"></div>')
						.append('<div class="input-group-prepend"><span class="input-group-text">$</span></div>')
						.append($price)
				)
		);
		$('#hotelRoomTypesList').append($block);
		refreshHotelRoomTypesVisibility();
	}

	function addHotelMealPlanRow(data) {
		data = data || {};
		var idx = hotelMealSeq++;
		var $block = $('<div class="hotel-line-item" data-meal-index="' + idx + '"></div>');
		var $name = $('<input type="text" class="form-control" id="hotel_meal_' + idx + '_name" name="meal_plans[' + idx + '][name]" placeholder="Enter meal plan (e.g. Breakfast, Half Board, All Inclusive)">').val(data.name || '');
		var $desc = $('<textarea class="form-control" id="hotel_meal_' + idx + '_desc" name="meal_plans[' + idx + '][description]" rows="2" placeholder="Enter meal plan description"></textarea>').val(data.description || '');
		var priceVal = (data.price !== undefined && data.price !== null && data.price !== '') ? data.price : '';
		var $price = $('<input type="number" class="form-control" id="hotel_meal_' + idx + '_price" name="meal_plans[' + idx + '][price]" min="0" step="0.01" placeholder="Enter price">').val(priceVal);
		$block.append(
			'<button type="button" class="btn btn-link hotel-line-remove text-muted" aria-label="Remove meal plan" title="Remove">' +
				'<i class="fas fa-times"></i>' +
			'</button>'
		);
		$block.append($('<div class="form-group"></div>').append('<label for="hotel_meal_' + idx + '_name">Meal Plan</label>').append($name));
		$block.append($('<div class="form-group"></div>').append('<label for="hotel_meal_' + idx + '_desc">Description</label>').append($desc));
		$block.append(
			$('<div class="form-group mb-0"></div>')
				.append('<label for="hotel_meal_' + idx + '_price">Price</label>')
				.append(
					$('<div class="input-group"></div>')
						.append('<div class="input-group-prepend"><span class="input-group-text">$</span></div>')
						.append($price)
				)
		);
		$('#hotelMealPlansList').append($block);
		refreshHotelMealPlansVisibility();
	}

	function setHotelFormMode(mode) {
		var isEdit = mode === 'edit';
		$('#hotelFormModal').toggleClass('is-edit-mode', isEdit);
		$('#hotelFormModalLabel').text(isEdit ? 'Edit Hotel' : 'Create Hotel');
		$('#hotelFormSubmitText').text(isEdit ? 'Update Hotel' : 'Create Hotel');
	}

	function setHotelCurrentImage(path) {
		var $wrap = $('#hotelFormCurrentImageWrap');
		var $img = $('#hotelFormCurrentImage');
		if (path) {
			$img.attr('src', path);
			$wrap.addClass('is-visible');
		} else {
			$img.attr('src', '');
			$wrap.removeClass('is-visible');
		}
	}

	function resetHotelForm() {
		var f = document.getElementById('hotelFormInner');
		if (f) f.reset();
		$('#hotelFormId').val('');
		resetHotelDestinationField();
		resetHotelCityField();
		$('#hotelFormStarCat').prop('selectedIndex', 0);
		$('#hotelFormStarRating').val('0');
		setHotelFormMode('create');
		setHotelCurrentImage('');
		$('#hotelFormImage').siblings('.custom-file-label').removeClass('selected').html('Choose file');
		$('#hotelRoomTypesList').empty();
		$('#hotelMealPlansList').empty();
		hotelRoomSeq = 0;
		hotelMealSeq = 0;
		refreshHotelRoomTypesVisibility();
		refreshHotelMealPlansVisibility();
	}

	function populateHotelForm(hotel) {
		if (!hotel) {
			return;
		}
		resetHotelForm();
		setHotelFormMode('edit');
		$('#hotelFormId').val(hotel.id || '');

		if (hotel.destination_id) {
			selectHotelDestination({
				id: hotel.destination_id,
				name: hotel.destination_name || ''
			}, { keepCity: true });
		}
		if (hotel.city_id) {
			selectHotelCity({
				id: hotel.city_id,
				name: hotel.city_name || ''
			});
		}

		$('#hotelFormName').val(hotel.hotel_name || '');
		$('#hotelFormDefault').prop('checked', parseInt(hotel.is_default, 10) === 1);
		if (hotel.star_category) {
			$('#hotelFormStarCat').val(hotel.star_category);
		}
		$('#hotelFormStarRating').val(hotel.star_rating != null ? hotel.star_rating : '0');
		$('#hotelFormReviewLink').val(hotel.review_link || '');
		$('#hotelFormAddress').val(hotel.address || '');
		setHotelCurrentImage(hotel.image_path || '');

		(hotel.room_types || []).forEach(function (room) {
			addHotelRoomTypeRow(room);
		});
		(hotel.meal_plans || []).forEach(function (meal) {
			addHotelMealPlanRow(meal);
		});
	}

	function openHotelFormModal(destinationName) {
		resetHotelForm();
		if (destinationName) {
			selectDestinationByName(destinationName);
		}
		$('#hotelFormModal').modal('show');
	}

	function openHotelEditModal(hotelId) {
		hotelId = parseInt(hotelId, 10) || 0;
		if (hotelId <= 0) {
			return;
		}
		$.getJSON('crm/ajax/get_hotel.php', { id: hotelId })
			.done(function (res) {
				if (!res || !res.success || !res.hotel) {
					alert((res && res.message) ? res.message : 'Could not load hotel.');
					return;
				}
				populateHotelForm(res.hotel);
				$('#hotelCityHotelsModal').modal('hide');
				$('#hotelFormModal').modal('show');
			})
			.fail(function () {
				alert('Could not load hotel. Please try again.');
			});
	}

	function deleteHotelById(hotelId, hotelName) {
		hotelId = parseInt(hotelId, 10) || 0;
		if (hotelId <= 0) {
			return;
		}
		var label = hotelName ? ('"' + hotelName + '"') : 'this hotel';
		if (!window.confirm('Delete ' + label + '? This cannot be undone from the list.')) {
			return;
		}
		$.ajax({
			url: 'crm/ajax/delete_hotel.php',
			type: 'POST',
			dataType: 'json',
			data: { id: hotelId }
		}).done(function (res) {
			if (res && res.success) {
				$('#hotelFormModal').modal('hide');
				$('#hotelCityHotelsModal').modal('hide');
				$('#hotelDetailModal').modal('hide');
				window.location.reload();
				return;
			}
			alert((res && res.message) ? res.message : 'Could not delete hotel.');
		}).fail(function () {
			alert('Could not delete hotel. Please try again.');
		});
	}

	function openHotelDestViewModal($btn) {
		var name = $btn.data('destination') || '—';
		var h = parseInt($btn.data('hotels'), 10);
		var c = parseInt($btn.data('cities'), 10);
		var badge = ($btn.attr('data-badge') || '').trim();
		$('#hotelDestViewName').text(name);
		$('#hotelDestViewHotels').text(!isNaN(h) ? (h === 1 ? '1 hotel' : h + ' hotels') : '—');
		$('#hotelDestViewCities').text(!isNaN(c) ? (c === 1 ? '1 city' : c + ' cities') : '—');
		$('#hotelDestViewBadge').text(badge || '—');
		$('#hotelDestViewModal').modal('show');
	}

	$('#btnOpenHotelFormModal').on('click', function () {
		openHotelFormModal(null);
	});

	$(document).on('click', '.btn-hotel-add-row', function () {
		var d = $(this).data('destination');
		openHotelFormModal(typeof d === 'string' ? d : null);
	});

	$(document).on('click', '.btn-hotel-view-dest', function () {
		openHotelDestViewModal($(this));
	});

	function openCityHotelsModal($btn) {
		var destId = parseInt($btn.data('destination-id'), 10) || 0;
		var destName = $btn.data('destination-name') || '';
		var cityId = parseInt($btn.data('city-id'), 10) || 0;
		var cityName = $btn.data('city-name') || 'City';
		var title = cityName;
		if (destName) {
			title += ' — ' + destName;
		}
		$('#hotelCityHotelsModalLabel').text(title);
		$('#hotelCityHotelsBody').html('<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin mr-1"></i> Loading hotels...</div>');
		$('#hotelCityHotelsModal').modal('show');

		$.getJSON('crm/ajax/get_city_hotels.php', {
			destination_id: destId,
			city_id: cityId
		}).done(function (res) {
			if (!res || !res.success) {
				$('#hotelCityHotelsBody').html('<div class="alert alert-warning mb-0">' + ((res && res.message) ? res.message : 'Could not load hotels.') + '</div>');
				return;
			}
			var hotels = res.hotels || [];
			if (!hotels.length) {
				$('#hotelCityHotelsBody').html('<div class="alert alert-info mb-0">No hotels found for this city.</div>');
				return;
			}
			var html = '<div class="table-responsive"><table class="table table-bordered table-sm hotel-city-hotels-table"><thead><tr>' +
				'<th>Hotel</th><th>Star</th><th>Rating</th><th>Default</th><th>Action</th></tr></thead><tbody>';
			hotels.forEach(function (h) {
				var hid = parseInt(h.id, 10) || 0;
				html += '<tr>' +
					'<td><strong>' + $('<div>').text(h.hotel_name || '—').html() + '</strong>' +
					(h.address ? '<div class="text-muted small mt-1">' + $('<div>').text(h.address).html() + '</div>' : '') +
					'</td>' +
					'<td>' + $('<div>').text(h.star_category || '—').html() + '</td>' +
					'<td>' + (h.star_rating ? parseFloat(h.star_rating).toFixed(1) : '—') + '</td>' +
					'<td>' + (parseInt(h.is_default, 10) === 1 ? '<span class="badge-hotel-default">Default</span>' : '—') + '</td>' +
					'<td><div class="hotel-city-actions">' +
					'<button type="button" class="btn btn-outline-primary btn-sm btn-hotel-edit" data-hotel-id="' + hid + '"><i class="fas fa-edit mr-1"></i>Edit</button>' +
					'<button type="button" class="btn btn-outline-danger btn-sm btn-hotel-delete" data-hotel-id="' + hid + '"><i class="fas fa-trash-alt mr-1"></i>Delete</button>' +
					'</div></td>' +
					'</tr>';
			});
			html += '</tbody></table></div>';
			$('#hotelCityHotelsBody').html(html);
			$('#hotelCityHotelsBody .btn-hotel-delete').each(function (i) {
				$(this).data('hotel-name', (hotels[i] && hotels[i].hotel_name) ? hotels[i].hotel_name : '');
			});
		}).fail(function () {
			$('#hotelCityHotelsBody').html('<div class="alert alert-danger mb-0">Could not load hotels. Please try again.</div>');
		});
	}

	$(document).on('click', '.btn-view-city-hotels', function () {
		openCityHotelsModal($(this));
	});

	var hotelDetailCurrentId = 0;
	var hotelDetailCurrentName = '';

	function escHtml(str) {
		return $('<div>').text(str == null ? '' : String(str)).html();
	}

	function renderHotelDetail(hotel) {
		hotelDetailCurrentId = parseInt(hotel.id, 10) || 0;
		hotelDetailCurrentName = hotel.hotel_name || '';
		$('#hotelDetailModalLabel').text(hotelDetailCurrentName || 'Hotel Details');

		var hero = hotel.image_path
			? '<img src="' + escHtml(hotel.image_path) + '" alt="' + escHtml(hotel.hotel_name || '') + '">'
			: '<div class="hotel-detail-hero-fallback"><i class="fas fa-hotel"></i></div>';

		var chips = '';
		if (hotel.destination_name) {
			chips += '<span class="hotel-detail-chip"><i class="fas fa-globe-asia"></i> ' + escHtml(hotel.destination_name) + '</span>';
		}
		if (hotel.city_name) {
			chips += '<span class="hotel-detail-chip"><i class="fas fa-map-marker-alt"></i> ' + escHtml(hotel.city_name) + '</span>';
		}
		if (hotel.star_category) {
			chips += '<span class="hotel-detail-chip is-star"><i class="fas fa-star"></i> ' + escHtml(hotel.star_category) + '</span>';
		}
		if (parseFloat(hotel.star_rating) > 0) {
			chips += '<span class="hotel-detail-chip"><i class="fas fa-star-half-alt"></i> ' + parseFloat(hotel.star_rating).toFixed(1) + ' / 5</span>';
		}
		if (parseInt(hotel.is_default, 10) === 1) {
			chips += '<span class="hotel-detail-chip is-default"><i class="fas fa-check"></i> Default hotel</span>';
		}

		var roomsHtml = '';
		var rooms = hotel.room_types || [];
		if (rooms.length) {
			roomsHtml = '<ul class="hotel-detail-list">';
			rooms.forEach(function (r) {
				var line = escHtml(r.type || 'Room');
				if (r.description) {
					line += ' — ' + escHtml(r.description);
				}
				if (r.price !== undefined && r.price !== null && r.price !== '') {
					line += ' <strong>($' + escHtml(String(r.price)) + ')</strong>';
				}
				roomsHtml += '<li>' + line + '</li>';
			});
			roomsHtml += '</ul>';
		} else {
			roomsHtml = '<p class="hotel-detail-kv text-muted mb-0">No room types added.</p>';
		}

		var mealsHtml = '';
		var meals = hotel.meal_plans || [];
		if (meals.length) {
			mealsHtml = '<ul class="hotel-detail-list">';
			meals.forEach(function (m) {
				var line = escHtml(m.name || 'Meal plan');
				if (m.description) {
					line += ' — ' + escHtml(m.description);
				}
				if (m.price !== undefined && m.price !== null && m.price !== '') {
					line += ' <strong>($' + escHtml(String(m.price)) + ')</strong>';
				}
				mealsHtml += '<li>' + line + '</li>';
			});
			mealsHtml += '</ul>';
		} else {
			mealsHtml = '<p class="hotel-detail-kv text-muted mb-0">No meal plans added.</p>';
		}

		var reviewHtml = hotel.review_link
			? '<a href="' + escHtml(hotel.review_link) + '" target="_blank" rel="noopener">' + escHtml(hotel.review_link) + '</a>'
			: '<span class="text-muted">Not added</span>';

		var html = '' +
			'<div class="hotel-detail-hero">' + hero + '</div>' +
			'<div class="hotel-detail-main">' +
			'<h3 class="hotel-detail-name">' + escHtml(hotel.hotel_name || '—') + '</h3>' +
			'<div class="hotel-detail-chips">' + chips + '</div>' +
			'<div class="hotel-detail-section"><h6>Address</h6><p class="hotel-detail-kv">' +
			(hotel.address ? escHtml(hotel.address) : '<span class="text-muted">Not added</span>') +
			'</p></div>' +
			'<div class="hotel-detail-section"><h6>Review Link</h6><p class="hotel-detail-kv">' + reviewHtml + '</p></div>' +
			'<div class="hotel-detail-section"><h6>Room Types</h6>' + roomsHtml + '</div>' +
			'<div class="hotel-detail-section"><h6>Meal Plans</h6>' + mealsHtml + '</div>' +
			'</div>';

		$('#hotelDetailBody').html(html);
	}

	function openHotelDetailModal(hotelId) {
		hotelId = parseInt(hotelId, 10) || 0;
		if (hotelId <= 0) {
			return;
		}
		$('#hotelDetailBody').html('<div class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin mr-1"></i> Loading details...</div>');
		$('#hotelDetailModal').modal('show');
		$.getJSON('crm/ajax/get_hotel.php', { id: hotelId })
			.done(function (res) {
				if (!res || !res.success || !res.hotel) {
					$('#hotelDetailBody').html('<div class="alert alert-warning m-3">' + escHtml((res && res.message) || 'Could not load hotel.') + '</div>');
					return;
				}
				renderHotelDetail(res.hotel);
			})
			.fail(function () {
				$('#hotelDetailBody').html('<div class="alert alert-danger m-3">Could not load hotel details. Please try again.</div>');
			});
	}

	$(document).on('click', '.btn-hotel-view-detail', function () {
		openHotelDetailModal($(this).data('hotel-id'));
	});

	$(document).on('click', '.btn-hotel-edit', function () {
		openHotelEditModal($(this).data('hotel-id'));
	});

	$(document).on('click', '.btn-hotel-delete', function () {
		deleteHotelById($(this).data('hotel-id'), $(this).data('hotel-name'));
	});

	$('#hotelDetailEditBtn').on('click', function () {
		if (!hotelDetailCurrentId) {
			return;
		}
		$('#hotelDetailModal').modal('hide');
		openHotelEditModal(hotelDetailCurrentId);
	});

	$('#hotelDetailDeleteBtn').on('click', function () {
		deleteHotelById(hotelDetailCurrentId, hotelDetailCurrentName);
	});

	$('#hotelFormDeleteBtn').on('click', function () {
		var id = parseInt($('#hotelFormId').val(), 10) || 0;
		var name = $.trim($('#hotelFormName').val());
		deleteHotelById(id, name);
	});

	$('#hotelFormInner').on('submit', function (e) {
		e.preventDefault();
		syncHotelDestInputValue();
		syncHotelCityInputValue();
		if (!$hotelDestId.val()) {
			alert('Please select a valid destination from the list.');
			$hotelDestInput.focus();
			return false;
		}
		if (!$hotelCityId.val()) {
			alert('Please select a valid city from the list.');
			$hotelCityInput.focus();
			return false;
		}
		if (!$.trim($('#hotelFormName').val())) {
			alert('Hotel name is required.');
			$('#hotelFormName').focus();
			return false;
		}

		var fd = new FormData(this);
		fd.set('default_hotel', $('#hotelFormDefault').is(':checked') ? '1' : '0');

		var $btn = $('#hotelFormSubmitBtn').prop('disabled', true);
		var btnHtml = $btn.html();
		$btn.html('<i class="fas fa-spinner fa-spin mr-2"></i>Saving...');

		$.ajax({
			url: 'crm/ajax/save_hotel.php',
			type: 'POST',
			data: fd,
			processData: false,
			contentType: false,
			dataType: 'json'
		}).done(function (res) {
			if (res && res.success) {
				$('#hotelFormModal').modal('hide');
				window.location.reload();
				return;
			}
			alert((res && res.message) ? res.message : 'Could not save hotel.');
		}).fail(function () {
			alert('Could not save hotel. Please try again.');
		}).always(function () {
			$btn.prop('disabled', false).html(btnHtml);
		});

		return false;
	});

	$('#hotelFormImage').on('change', function () {
		var fileName = $(this).val().split('\\').pop();
		$(this).siblings('.custom-file-label').addClass('selected').html(fileName || 'Choose file');
	});

	$('#btnAddRoomType').on('click', function () {
		addHotelRoomTypeRow();
	});

	$('#btnAddMealPlan').on('click', function () {
		addHotelMealPlanRow();
	});

	$(document).on('click', '#hotelRoomTypesList .hotel-line-remove', function () {
		$(this).closest('.hotel-line-item').remove();
		refreshHotelRoomTypesVisibility();
	});

	$(document).on('click', '#hotelMealPlansList .hotel-line-remove', function () {
		$(this).closest('.hotel-line-item').remove();
		refreshHotelMealPlansVisibility();
	});

	var q = new URLSearchParams(window.location.search);
	if (q.get('open') === 'create') {
		openHotelFormModal(q.get('destination') || null);
		if (window.history && window.history.replaceState) {
			var u = new URL(window.location.href);
			u.search = '';
			window.history.replaceState({}, '', u.pathname + u.search);
		}
	}
});
</script>

</body>

</html>
