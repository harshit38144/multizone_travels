<?php

/**
 * Reusable city → latitude/longitude lookup.
 * Static places cover common tour cities offline. Unknown names are resolved
 * once through the free Open-Meteo geocoder and stored in a JSON cache.
 */

function dashMapNormalizeCity(string $name): string
{
    $name = strtolower(trim($name));
    $name = preg_replace('/\s*\([a-z0-9]{2,4}\)\s*/', ' ', $name) ?? $name;
    $name = preg_replace('/^ex[-\s]+/', '', $name) ?? $name;
    $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name;
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

    $aliases = [
        'bengaluru' => 'bangalore',
        'bombay' => 'mumbai',
        'calcutta' => 'kolkata',
        'madras' => 'chennai',
        'trivandrum' => 'thiruvananthapuram',
        'andaman' => 'port blair',
        'andaman islands' => 'port blair',
        'andaman and nicobar' => 'port blair',
        'andaman nicobar' => 'port blair',
        'kerala' => 'kochi',
        'kashmir' => 'srinagar',
        'jammu and kashmir' => 'srinagar',
        'goa' => 'panaji',
        'rajasthan' => 'jaipur',
        'himachal' => 'shimla',
        'himachal pradesh' => 'shimla',
        'tamil nadu' => 'chennai',
        'maldives' => 'male',
    ];

    return $aliases[$name] ?? $name;
}

function dashMapCityDisplay(string $raw): string
{
    $raw = trim($raw);
    $raw = preg_replace('/\s*\([A-Za-z0-9]{2,4}\)\s*$/', '', $raw) ?? $raw;
    $raw = preg_replace('/^ex[-\s]+/i', '', $raw) ?? $raw;
    return trim($raw);
}

/** @return array<string, array{0: float, 1: float}> */
function dashMapStaticCities(): array
{
    return [
        'ranchi' => [23.3441, 85.3096],
        'delhi' => [28.6139, 77.2090],
        'new delhi' => [28.6139, 77.2090],
        'mumbai' => [19.0760, 72.8777],
        'bangalore' => [12.9716, 77.5946],
        'hyderabad' => [17.3850, 78.4867],
        'chennai' => [13.0827, 80.2707],
        'kolkata' => [22.5726, 88.3639],
        'kochi' => [9.9312, 76.2673],
        'thiruvananthapuram' => [8.5241, 76.9366],
        'port blair' => [11.6234, 92.7265],
        'panaji' => [15.4909, 73.8278],
        'jaipur' => [26.9124, 75.7873],
        'udaipur' => [24.5854, 73.7125],
        'jodhpur' => [26.2389, 73.0243],
        'srinagar' => [34.0837, 74.7973],
        'shimla' => [31.1048, 77.1734],
        'manali' => [32.2432, 77.1892],
        'leh' => [34.1526, 77.5771],
        'ladakh' => [34.1526, 77.5771],
        'varanasi' => [25.3176, 82.9739],
        'agra' => [27.1767, 78.0081],
        'amritsar' => [31.6340, 74.8723],
        'ahmedabad' => [23.0225, 72.5714],
        'pune' => [18.5204, 73.8567],
        'bhubaneswar' => [20.2961, 85.8245],
        'guwahati' => [26.1445, 91.7362],
        'darjeeling' => [27.0360, 88.2627],
        'gangtok' => [27.3389, 88.6065],
        'sikkim' => [27.3389, 88.6065],
        'shillong' => [25.5788, 91.8933],
        'patna' => [25.5941, 85.1376],
        'lucknow' => [26.8467, 80.9462],
        'chandigarh' => [30.7333, 76.7794],
        'dehradun' => [30.3165, 78.0322],
        'rishikesh' => [30.0869, 78.2676],
        'haridwar' => [29.9457, 78.1642],
        'nainital' => [29.3919, 79.4542],
        'mussoorie' => [30.4598, 78.0644],
        'ooty' => [11.4064, 76.6932],
        'munnar' => [10.0889, 77.0595],
        'alleppey' => [9.4981, 76.3388],
        'alappuzha' => [9.4981, 76.3388],
        'kovalam' => [8.4004, 76.9787],
        'wayanad' => [11.6854, 76.1320],
        'coorg' => [12.3375, 75.8069],
        'madurai' => [9.9252, 78.1198],
        'pondicherry' => [11.9416, 79.8083],
        'puducherry' => [11.9416, 79.8083],
        'havelock' => [12.0270, 92.9916],
        'neil island' => [11.8330, 93.0500],
        'dubai' => [25.2048, 55.2708],
        'abu dhabi' => [24.4539, 54.3773],
        'singapore' => [1.3521, 103.8198],
        'bangkok' => [13.7563, 100.5018],
        'phuket' => [7.8804, 98.3923],
        'pattaya' => [12.9236, 100.8825],
        'thailand' => [13.7563, 100.5018],
        'bali' => [-8.4095, 115.1889],
        'jakarta' => [-6.2088, 106.8456],
        'kuala lumpur' => [3.1390, 101.6869],
        'male' => [4.1755, 73.5093],
        'colombo' => [6.9271, 79.8612],
        'sri lanka' => [6.9271, 79.8612],
        'kathmandu' => [27.7172, 85.3240],
        'nepal' => [27.7172, 85.3240],
        'paris' => [48.8566, 2.3522],
        'london' => [51.5074, -0.1278],
        'zurich' => [47.3769, 8.5417],
        'switzerland' => [46.8182, 8.2275],
        'rome' => [41.9028, 12.4964],
        'milan' => [45.4642, 9.1900],
        'venice' => [45.4408, 12.3155],
        'barcelona' => [41.3874, 2.1686],
        'amsterdam' => [52.3676, 4.9041],
        'istanbul' => [41.0082, 28.9784],
        'cairo' => [30.0444, 31.2357],
        'new york' => [40.7128, -74.0060],
        'los angeles' => [34.0522, -118.2437],
        'sydney' => [-33.8688, 151.2093],
        'melbourne' => [-37.8136, 144.9631],
        'tokyo' => [35.6762, 139.6503],
        'seoul' => [37.5665, 126.9780],
        'hong kong' => [22.3193, 114.1694],
        'mauritius' => [-20.3484, 57.5522],
        'seychelles' => [-4.6796, 55.4920],
        'bhutan' => [27.5142, 90.4336],
        'paro' => [27.4305, 89.4136],
        'vietnam' => [21.0278, 105.8342],
        'hanoi' => [21.0278, 105.8342],
        'ho chi minh' => [10.8231, 106.6297],
        'saigon' => [10.8231, 106.6297],
    ];
}

function dashMapCoordinateCachePath(): string
{
    return __DIR__ . '/../cache/city_coordinates.json';
}

/** @return array<string, array{lat: float, lng: float}> */
function dashMapReadCoordinateCache(): array
{
    $path = dashMapCoordinateCachePath();
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

/** @param array<string, array{lat: float, lng: float}> $cache */
function dashMapWriteCoordinateCache(array $cache): void
{
    $path = dashMapCoordinateCachePath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($path, json_encode($cache), LOCK_EX);
}

/**
 * @return array{lat: float, lng: float}|null
 */
function dashMapGeocodeCity(string $label): ?array
{
    $global = dashMapGeocodeSearch($label, '');
    $picked = dashMapPickGeocodeHit($global, $label);
    $population = (int) ($picked['population'] ?? 0);
    if ($picked === null || $population < 50000) {
        $india = dashMapPickGeocodeHit(dashMapGeocodeSearch($label, 'IN'), $label);
        $indiaPopulation = (int) ($india['population'] ?? 0);
        if (is_array($india) && ($picked === null || $indiaPopulation >= $population)) {
            $picked = $india;
        }
    }
    if (!is_array($picked)) {
        return null;
    }
    return [
        'lat' => (float) $picked['latitude'],
        'lng' => (float) $picked['longitude'],
    ];
}

/** @return array<int, array<string, mixed>> */
function dashMapGeocodeSearch(string $label, string $countryCode): array
{
    $url = 'https://geocoding-api.open-meteo.com/v1/search?name=' . rawurlencode($label)
        . '&count=8&language=en&format=json';
    if ($countryCode !== '') {
        $url .= '&countryCode=' . rawurlencode($countryCode);
    }
    $body = dashMapHttpGet($url);
    if ($body === '') {
        return [];
    }
    $decoded = json_decode($body, true);
    return (isset($decoded['results']) && is_array($decoded['results'])) ? $decoded['results'] : [];
}

/**
 * @param array<int, array<string, mixed>> $results
 * @return array<string, mixed>|null
 */
function dashMapPickGeocodeHit(array $results, string $label): ?array
{
    $want = dashMapNormalizeCity($label);
    $best = null;
    $bestScore = -1;
    foreach ($results as $hit) {
        if (!is_array($hit) || !isset($hit['latitude'], $hit['longitude'])) {
            continue;
        }
        $name = dashMapNormalizeCity((string) ($hit['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $population = (int) ($hit['population'] ?? 0);
        if ($name === $want) {
            $score = 5000000 + $population;
        } elseif (strpos($name, $want) !== false || strpos($want, $name) !== false) {
            $score = 1000000 + $population;
        } else {
            continue;
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $hit;
        }
    }
    return $best;
}

function dashMapHttpGet(string $url): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (is_string($body) && $code >= 200 && $code < 300) {
            return $body;
        }
    }

    $context = stream_context_create([
        'http' => ['timeout' => 3],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $context);
    return is_string($body) ? $body : '';
}

/**
 * @return array{label: string, lat: float, lng: float}|null
 */
function dashMapCityCoordinate(string $raw): ?array
{
    $label = dashMapCityDisplay($raw);
    if ($label === '') {
        return null;
    }
    $key = dashMapNormalizeCity($label);
    if ($key === '') {
        return null;
    }

    $static = dashMapStaticCities();
    if (isset($static[$key])) {
        return [
            'label' => $label,
            'lat' => $static[$key][0],
            'lng' => $static[$key][1],
        ];
    }

    $cache = dashMapReadCoordinateCache();
    if (isset($cache[$key]['lat'], $cache[$key]['lng'])) {
        return [
            'label' => $label,
            'lat' => (float) $cache[$key]['lat'],
            'lng' => (float) $cache[$key]['lng'],
        ];
    }

    $found = dashMapGeocodeCity($label);
    if ($found === null) {
        return null;
    }
    $cache[$key] = $found;
    dashMapWriteCoordinateCache($cache);
    return [
        'label' => $label,
        'lat' => $found['lat'],
        'lng' => $found['lng'],
    ];
}
