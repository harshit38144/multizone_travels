<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../admin/includes/geo_locations.php';

$q = trim((string) ($_GET['q'] ?? ''));
if (function_exists('mb_substr')) {
    $q = mb_substr($q, 0, 80);
} else {
    $q = substr($q, 0, 80);
}

$tourType = strtolower(trim((string) ($_GET['tour_type'] ?? 'domestic')));
if ($tourType !== 'international') {
    $tourType = 'domestic';
}

$qLen = function_exists('mb_strlen') ? mb_strlen($q) : strlen($q);
if ($qLen < 2) {
    echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

function intakeIndiaStates(): array
{
    $cacheFile = geoCitiesCacheDir() . '/_states_india.json';
    $cacheTtl = 30 * 86400;
    if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < $cacheTtl) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $states = [];
    try {
        $payload = geoCountriesNowPost('/states', ['country' => 'India']);
        $list = $payload['data']['states'] ?? [];
        if (is_array($list)) {
            foreach ($list as $row) {
                $name = trim((string) (is_array($row) ? ($row['name'] ?? '') : $row));
                if ($name !== '') {
                    $states[] = $name;
                }
            }
        }
    } catch (Throwable $e) {
        $states = [];
    }

    if ($states) {
        $states = array_values(array_unique($states));
        sort($states, SORT_NATURAL | SORT_FLAG_CASE);
        @file_put_contents($cacheFile, json_encode($states, JSON_UNESCAPED_UNICODE));
    }

    return $states;
}

function intakeMmtPlaces(string $q): array
{
    $url = 'https://flights-explorer.makemytrip.com/autosuggest?limit=15&query=' . rawurlencode($q);
    if (!function_exists('curl_init')) {
        return [];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Accept: */*',
            'Accept-Language: en-US,en;q=0.9',
            'Origin: https://www.goibibo.com',
            'Referer: https://www.goibibo.com/',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code !== 200) {
        return [];
    }

    $json = json_decode((string) $raw, true);
    $list = $json['r'] ?? [];
    if (!is_array($list)) {
        return [];
    }

    $places = [];
    $seen = [];
    foreach ($list as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string) ($item['ct'] ?? $item['cName'] ?? ''));
        $country = trim((string) ($item['cnty'] ?? $item['countryName'] ?? ''));
        $cc = strtoupper(trim((string) ($item['cc'] ?? '')));
        if ($name === '') {
            continue;
        }
        $key = mb_strtolower($name . "\0" . $cc);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $places[] = [
            'name' => $name,
            'country_name' => $country,
            'cc' => $cc,
        ];
    }

    return $places;
}

$rows = [];
$seen = [];

$push = static function (string $name, string $country, string $kind, int $score) use (&$rows, &$seen): void {
    $name = trim($name);
    $country = trim($country);
    if ($name === '') {
        return;
    }
    $key = mb_strtolower($name . "\0" . $country);
    if (isset($seen[$key])) {
        return;
    }
    $seen[$key] = true;
    $rows[] = [
        'score' => $score,
        'name' => $name,
        'country_name' => $country,
        'kind' => $kind,
    ];
};

if ($tourType === 'domestic') {
    foreach (intakeIndiaStates() as $state) {
        $score = geoCityMatchScore($state, $q);
        if ($score === null) {
            continue;
        }
        $push($state, 'India', 'state', $score);
    }
    foreach (geoSearchCitiesFromApi($q, 'India', 15) as $city) {
        $name = (string) ($city['name'] ?? '');
        $score = geoCityMatchScore($name, $q);
        $push($name, 'India', 'city', $score === null ? 9 : $score);
    }
} else {
    foreach (geoFetchCountriesList() as $country) {
        if (strcasecmp($country, 'India') === 0) {
            continue;
        }
        $score = geoCityMatchScore($country, $q);
        if ($score === null) {
            continue;
        }
        $push($country, $country, 'country', $score);
    }
    foreach (intakeMmtPlaces($q) as $place) {
        $country = (string) ($place['country_name'] ?? '');
        $cc = strtoupper((string) ($place['cc'] ?? ''));
        if ($cc === 'IN' || strcasecmp($country, 'India') === 0) {
            continue;
        }
        $name = (string) ($place['name'] ?? '');
        $nameScore = geoCityMatchScore($name, $q);
        $countryScore = geoCityMatchScore($country, $q);
        if ($nameScore === null && $countryScore === null) {
            continue;
        }
        if ($nameScore !== null) {
            $nameLower = mb_strtolower($name);
            $queryLower = mb_strtolower($q);
            if (preg_match('/(?:^|[\s,(-])' . preg_quote($queryLower, '/') . '(?:$|[\s,)-])/u', $nameLower)) {
                $nameScore = $nameLower === $queryLower ? 0 : 1;
            } else {
                $nameScore = min(4, $nameScore + 1);
            }
        }
        $push($name, $country, 'city', $nameScore === null ? 2 : $nameScore);
    }
}

usort($rows, static function (array $a, array $b): int {
    if ($a['score'] !== $b['score']) {
        return $a['score'] <=> $b['score'];
    }
    $kindRank = ['state' => 0, 'country' => 0, 'city' => 1];
    $aKind = $kindRank[$a['kind']] ?? 1;
    $bKind = $kindRank[$b['kind']] ?? 1;
    if ($aKind !== $bKind) {
        return $aKind <=> $bKind;
    }

    return strcasecmp($a['name'], $b['name']);
});

$data = [];
foreach ($rows as $row) {
    if (count($data) >= 15) {
        break;
    }
    unset($row['score']);
    $data[] = $row;
}

echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
