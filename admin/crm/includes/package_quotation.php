<?php

/**
 * Helpers to suggest package itineraries in the quotation generator.
 */

function crmPackageTablesExist(mysqli $conn): bool
{
    $res = $conn->query("SHOW TABLES LIKE 'packages'");
    return $res && $res->num_rows > 0;
}

/**
 * @return array<int, array<string, mixed>>
 */
function crmSearchPackagesForQuotation(mysqli $conn, string $query, int $limit = 15): array
{
    if (!crmPackageTablesExist($conn)) {
        return [];
    }

    $query = trim($query);
    $limit = max(1, min(30, $limit));
    $rows = [];

    if ($query === '') {
        $sql = "SELECT p.id, p.title, p.duration_nights, p.duration_days, p.sale_price, p.status,
                (SELECT GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR ', ')
                 FROM destinations d
                 INNER JOIN package_destination_map pdm ON pdm.destination_id = d.id
                 WHERE pdm.package_id = p.id) AS dest_names
            FROM packages p
            WHERE p.status IN ('Published', 'Draft')
            ORDER BY p.id DESC
            LIMIT " . (int) $limit;
        $res = $conn->query($sql);
    } else {
        $like = '%' . $query . '%';
        $sql = "SELECT p.id, p.title, p.duration_nights, p.duration_days, p.sale_price, p.status,
                (SELECT GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR ', ')
                 FROM destinations d
                 INNER JOIN package_destination_map pdm ON pdm.destination_id = d.id
                 WHERE pdm.package_id = p.id) AS dest_names
            FROM packages p
            WHERE p.status IN ('Published', 'Draft')
              AND (
                    p.title LIKE ?
                 OR EXISTS (
                        SELECT 1 FROM destinations d
                        INNER JOIN package_destination_map pdm ON pdm.destination_id = d.id
                        WHERE pdm.package_id = p.id AND d.name LIKE ?
                    )
              )
            ORDER BY p.id DESC
            LIMIT " . (int) $limit;
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
    }

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $nights = max(0, (int) ($row['duration_nights'] ?? 0));
            $days = max(0, (int) ($row['duration_days'] ?? 0));
            if ($days <= 0 && $nights > 0) {
                $days = $nights + 1;
            }
            $destNames = trim((string) ($row['dest_names'] ?? ''));
            $label = (string) ($row['title'] ?? '');
            $sub = [];
            if ($nights > 0) {
                $sub[] = $nights . 'N / ' . max(1, $days) . 'D';
            }
            if ($destNames !== '') {
                $sub[] = $destNames;
            }
            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => $label,
                'duration_nights' => $nights,
                'duration_days' => $days,
                'destination' => $destNames,
                'sale_price' => (float) ($row['sale_price'] ?? 0),
                'label' => $label,
                'sub_label' => implode(' · ', $sub),
            ];
        }
    }

    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }

    return $rows;
}

function crmPackageBuildDayDescription(array $iti): string
{
    $parts = [];
    $description = trim((string) ($iti['description'] ?? ''));
    if ($description !== '') {
        if (strpos($description, '<') !== false) {
            $parts[] = $description;
        } else {
            $parts[] = nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8'));
        }
    }

    $fields = [
        'meals' => 'Meals',
        'accommodation' => 'Accommodation',
        'activities' => 'Activities',
    ];
    foreach ($fields as $key => $label) {
        $val = trim((string) ($iti[$key] ?? ''));
        if ($val !== '') {
            $parts[] = '<p><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ':</strong> '
                . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</p>';
        }
    }

    return implode("\n", $parts);
}

/**
 * @return array<string, mixed>|null
 */
function crmGetPackageForQuotation(mysqli $conn, int $packageId): ?array
{
    if ($packageId <= 0 || !crmPackageTablesExist($conn)) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT p.id, p.title, p.duration_nights, p.duration_days, p.sale_price,
                p.inclusions, p.exclusions, p.highlights,
                (SELECT GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR ', ')
                 FROM destinations d
                 INNER JOIN package_destination_map pdm ON pdm.destination_id = d.id
                 WHERE pdm.package_id = p.id) AS dest_names
         FROM packages p
         WHERE p.id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $packageId);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $res = $stmt->get_result();
    $pkg = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$pkg) {
        return null;
    }

    $itinerary = [];
    $itiRes = $conn->query(
        'SELECT day_number, title, description, meals, accommodation, activities
         FROM package_itineraries
         WHERE package_id = ' . (int) $packageId . '
         ORDER BY day_number ASC, id ASC'
    );
    if ($itiRes) {
        while ($iti = $itiRes->fetch_assoc()) {
            $itinerary[] = [
                'title' => (string) ($iti['title'] ?? ''),
                'description' => crmPackageBuildDayDescription($iti),
                'image' => '',
            ];
        }
    }

    $nights = max(0, (int) ($pkg['duration_nights'] ?? 0));
    $days = max(0, (int) ($pkg['duration_days'] ?? 0));
    if ($days <= 0 && $nights > 0) {
        $days = $nights + 1;
    }
    if ($nights <= 0 && count($itinerary) > 1) {
        $nights = count($itinerary) - 1;
    }
    if ($days <= 0 && count($itinerary) > 0) {
        $days = count($itinerary);
    }

    return [
        'id' => (int) ($pkg['id'] ?? 0),
        'title' => (string) ($pkg['title'] ?? ''),
        'destination' => trim((string) ($pkg['dest_names'] ?? '')),
        'duration_nights' => $nights,
        'duration_days' => $days,
        'sale_price' => (float) ($pkg['sale_price'] ?? 0),
        'inclusion' => (string) ($pkg['inclusions'] ?? ''),
        'exclusion' => (string) ($pkg['exclusions'] ?? ''),
        'highlights' => (string) ($pkg['highlights'] ?? ''),
        'itinerary' => $itinerary,
    ];
}
