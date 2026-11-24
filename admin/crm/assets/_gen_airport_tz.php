<?php
/**
 * One-shot generator for airport_timezones.js from airports_fallback.json
 */
$airports = json_decode(file_get_contents(__DIR__ . '/../../ajax/data/airports_fallback.json'), true);
if (!is_array($airports)) {
    fwrite(STDERR, "Failed to load airports\n");
    exit(1);
}

$countryTz = [
    'India' => 'Asia/Kolkata',
    'United Arab Emirates' => 'Asia/Dubai',
    'Qatar' => 'Asia/Qatar',
    'Bahrain' => 'Asia/Bahrain',
    'Kuwait' => 'Asia/Kuwait',
    'Saudi Arabia' => 'Asia/Riyadh',
    'Oman' => 'Asia/Muscat',
    'Singapore' => 'Asia/Singapore',
    'Malaysia' => 'Asia/Kuala_Lumpur',
    'Thailand' => 'Asia/Bangkok',
    'Hong Kong' => 'Asia/Hong_Kong',
    'Macau' => 'Asia/Macau',
    'Taiwan' => 'Asia/Taipei',
    'Japan' => 'Asia/Tokyo',
    'South Korea' => 'Asia/Seoul',
    'China' => 'Asia/Shanghai',
    'Philippines' => 'Asia/Manila',
    'Vietnam' => 'Asia/Ho_Chi_Minh',
    'Sri Lanka' => 'Asia/Colombo',
    'Maldives' => 'Indian/Maldives',
    'Nepal' => 'Asia/Kathmandu',
    'Bangladesh' => 'Asia/Dhaka',
    'United Kingdom' => 'Europe/London',
    'France' => 'Europe/Paris',
    'Germany' => 'Europe/Berlin',
    'Netherlands' => 'Europe/Amsterdam',
    'Switzerland' => 'Europe/Zurich',
    'Italy' => 'Europe/Rome',
    'Spain' => 'Europe/Madrid',
    'Turkey' => 'Europe/Istanbul',
    'Egypt' => 'Africa/Cairo',
    'South Africa' => 'Africa/Johannesburg',
    'Kenya' => 'Africa/Nairobi',
    'Ethiopia' => 'Africa/Addis_Ababa',
    'Mauritius' => 'Indian/Mauritius',
    'Seychelles' => 'Indian/Mahe',
    'Greece' => 'Europe/Athens',
    'Austria' => 'Europe/Vienna',
    'Czech Republic' => 'Europe/Prague',
    'Hungary' => 'Europe/Budapest',
    'Poland' => 'Europe/Warsaw',
    'Finland' => 'Europe/Helsinki',
    'Denmark' => 'Europe/Copenhagen',
    'Norway' => 'Europe/Oslo',
    'Sweden' => 'Europe/Stockholm',
    'Ireland' => 'Europe/Dublin',
    'Portugal' => 'Europe/Lisbon',
    'Belgium' => 'Europe/Brussels',
    'Israel' => 'Asia/Jerusalem',
    'New Zealand' => 'Pacific/Auckland',
];

// Multi-timezone countries / precise airport overrides
$iataOverride = [
    // Indonesia
    'CGK' => 'Asia/Jakarta',
    'DPS' => 'Asia/Makassar',
    'SUB' => 'Asia/Jakarta',
    'JOG' => 'Asia/Jakarta',
    'UPG' => 'Asia/Makassar',
    'BPN' => 'Asia/Makassar',
    'MDC' => 'Asia/Makassar',
    'DJJ' => 'Asia/Jayapura',
    // USA
    'JFK' => 'America/New_York',
    'EWR' => 'America/New_York',
    'LGA' => 'America/New_York',
    'IAD' => 'America/New_York',
    'BOS' => 'America/New_York',
    'MIA' => 'America/New_York',
    'ATL' => 'America/New_York',
    'ORD' => 'America/Chicago',
    'DFW' => 'America/Chicago',
    'LAX' => 'America/Los_Angeles',
    'SFO' => 'America/Los_Angeles',
    'SEA' => 'America/Los_Angeles',
    // Canada
    'YYZ' => 'America/Toronto',
    'YUL' => 'America/Toronto',
    'YVR' => 'America/Vancouver',
    // Australia
    'SYD' => 'Australia/Sydney',
    'MEL' => 'Australia/Melbourne',
    'BNE' => 'Australia/Brisbane',
    'PER' => 'Australia/Perth',
];

$map = [];
foreach ($airports as $a) {
    $iata = strtoupper(trim((string) ($a['iata'] ?? '')));
    if ($iata === '') {
        continue;
    }
    if (isset($iataOverride[$iata])) {
        $map[$iata] = $iataOverride[$iata];
        continue;
    }
    $cnty = trim((string) ($a['cnty'] ?? ''));
    if ($cnty !== '' && isset($countryTz[$cnty])) {
        $map[$iata] = $countryTz[$cnty];
    }
}

// Ensure critical codes
foreach ($iataOverride as $k => $v) {
    $map[$k] = $v;
}

ksort($map);

$jsMap = "{\n";
foreach ($map as $k => $v) {
    $jsMap .= '  "' . $k . '": "' . $v . '",' . "\n";
}
$jsMap = rtrim($jsMap, ",\n") . "\n}";

$countryJs = "{\n";
foreach ($countryTz as $k => $v) {
    $countryJs .= '  ' . json_encode($k) . ': ' . json_encode($v) . ",\n";
}
$countryJs = rtrim($countryJs, ",\n") . "\n}";

$out = <<<JS
/**
 * Airport IANA timezones for flight duration calculations.
 * Auto-generated from airports_fallback.json — do not hand-edit bulk entries.
 */
(function (global) {
  'use strict';
  var BY_IATA = {$jsMap};
  var BY_COUNTRY = {$countryJs};

  function normalizeIata(code) {
    var raw = String(code || '').trim();
    if (!raw) return '';
    var m = raw.match(/\\(([A-Za-z]{3})\\)\\s*$/);
    if (m) return m[1].toUpperCase();
    m = raw.match(/\\(([A-Za-z]{3})\\)/);
    if (m) return m[1].toUpperCase();
    if (/^[A-Za-z]{3}$/.test(raw)) return raw.toUpperCase();
    m = raw.match(/\\s([A-Za-z]{3})$/);
    if (m) return m[1].toUpperCase();
    return '';
  }

  function airportIanaTimezone(iataOrPlace, countryHint) {
    var iata = normalizeIata(iataOrPlace);
    if (iata && BY_IATA[iata]) {
      return BY_IATA[iata];
    }
    var c = String(countryHint || '').trim();
    if (c && BY_COUNTRY[c]) {
      return BY_COUNTRY[c];
    }
    // Default for Indian travel CRM when airport unknown
    return 'Asia/Kolkata';
  }

  function tzOffsetMinutesAt(utcMs, timeZone) {
    try {
      var d = new Date(utcMs);
      var fmt = new Intl.DateTimeFormat('en-US', {
        timeZone: timeZone || 'UTC',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      });
      var parts = {};
      fmt.formatToParts(d).forEach(function (p) {
        if (p.type !== 'literal') parts[p.type] = p.value;
      });
      var hour = parseInt(parts.hour, 10);
      if (hour === 24) hour = 0;
      var asUtc = Date.UTC(
        parseInt(parts.year, 10),
        parseInt(parts.month, 10) - 1,
        parseInt(parts.day, 10),
        hour,
        parseInt(parts.minute, 10),
        parseInt(parts.second, 10) || 0
      );
      return (asUtc - utcMs) / 60000;
    } catch (e) {
      return 0;
    }
  }

  function localWallTimeToUtcMs(y, mo, d, h, mi, timeZone) {
    y = parseInt(y, 10);
    mo = parseInt(mo, 10);
    d = parseInt(d, 10);
    h = parseInt(h, 10) || 0;
    mi = parseInt(mi, 10) || 0;
    if (!y || !mo || !d) return NaN;
    var tz = timeZone || 'UTC';
    var asUtcGuess = Date.UTC(y, mo - 1, d, h, mi, 0);
    var off1 = tzOffsetMinutesAt(asUtcGuess, tz);
    var utcMs = asUtcGuess - off1 * 60000;
    var off2 = tzOffsetMinutesAt(utcMs, tz);
    return asUtcGuess - off2 * 60000;
  }

  function parseDateTimeParts(dateVal, timeVal) {
    var dateStr = String(dateVal || '').trim();
    var timeStr = String(timeVal || '').trim();
    var y = 0, mo = 0, d = 0, h = 0, mi = 0;

    var dm = dateStr.match(/^(\\d{4})-(\\d{2})-(\\d{2})/);
    if (!dm) {
      dm = dateStr.match(/^(\\d{1,2})[\\/\\-](\\d{1,2})[\\/\\-](\\d{4})/);
      if (dm) {
        // DD/MM/YYYY
        d = parseInt(dm[1], 10);
        mo = parseInt(dm[2], 10);
        y = parseInt(dm[3], 10);
      }
    } else {
      y = parseInt(dm[1], 10);
      mo = parseInt(dm[2], 10);
      d = parseInt(dm[3], 10);
    }
    if (!y) return null;

    if (!timeStr) {
      timeStr = '00:00';
    }
    var tm = timeStr.match(/^(\\d{1,2}):(\\d{2})(?::\\d{2})?\\s*(AM|PM)?$/i);
    if (tm) {
      h = parseInt(tm[1], 10);
      mi = parseInt(tm[2], 10);
      var ap = (tm[3] || '').toUpperCase();
      if (ap === 'PM' && h < 12) h += 12;
      if (ap === 'AM' && h === 12) h = 0;
    } else if (typeof moment !== 'undefined' && moment(timeStr).isValid()) {
      var m = moment(timeStr);
      h = m.hour();
      mi = m.minute();
    }
    return { y: y, mo: mo, d: d, h: h, mi: mi };
  }

  /**
   * Minutes between departure and arrival using airport local times + timezones.
   * Accepts absolute timestamps (number/ISO) for either side when available.
   */
  function flightDurationMinutes(opts) {
    opts = opts || {};
    var depRaw = opts.depRaw;
    var arrRaw = opts.arrRaw;

    // Absolute timestamps only: epoch ms/sec or ISO-8601 with T / Z / offset.
    // Do NOT treat "25 Dec 2026 12:20 AM" as absolute — those are airport-local walls.
    if (depRaw != null && arrRaw != null && depRaw !== '' && arrRaw !== '') {
      var looksAbs = function (v) {
        if (typeof v === 'number' && isFinite(v)) {
          return true;
        }
        var s = String(v).trim();
        if (/^\\d{10,13}$/.test(s)) {
          return true;
        }
        if (/^\\d{4}-\\d{2}-\\d{2}T/.test(s)) {
          return true;
        }
        if (/[zZ]|[+\\-]\\d{2}:?\\d{2}$/.test(s) && /\\d{4}-\\d{2}-\\d{2}/.test(s)) {
          return true;
        }
        return false;
      };
      if (looksAbs(depRaw) && looksAbs(arrRaw)) {
        var depMs = typeof depRaw === 'number' ? depRaw : (typeof moment !== 'undefined' ? moment(depRaw).valueOf() : Date.parse(depRaw));
        var arrMs = typeof arrRaw === 'number' ? arrRaw : (typeof moment !== 'undefined' ? moment(arrRaw).valueOf() : Date.parse(arrRaw));
        if (typeof depRaw === 'number' && depRaw < 1e12) {
          depMs = depRaw * 1000;
        }
        if (typeof arrRaw === 'number' && arrRaw < 1e12) {
          arrMs = arrRaw * 1000;
        }
        if (isFinite(depMs) && isFinite(arrMs)) {
          var absMins = Math.round((arrMs - depMs) / 60000);
          if (absMins > 0 && absMins < 60 * 48) {
            return absMins;
          }
        }
      }
    }

    var depParts = parseDateTimeParts(opts.depDate, opts.depTime);
    var arrParts = parseDateTimeParts(opts.arrDate || opts.depDate, opts.arrTime || opts.depTime);
    if (!depParts || !arrParts) {
      return 0;
    }

    var resolveTz = function (iata, place, country) {
      var code = normalizeIata(iata || '');
      if (code && BY_IATA[code]) {
        return BY_IATA[code];
      }
      return airportIanaTimezone(place || iata, country);
    };
    var depTz = resolveTz(opts.depIata, opts.depPlace, opts.depCountry);
    var arrTz = resolveTz(opts.arrIata, opts.arrPlace, opts.arrCountry);

    var depUtc = localWallTimeToUtcMs(depParts.y, depParts.mo, depParts.d, depParts.h, depParts.mi, depTz);
    var arrUtc = localWallTimeToUtcMs(arrParts.y, arrParts.mo, arrParts.d, arrParts.h, arrParts.mi, arrTz);
    if (!isFinite(depUtc) || !isFinite(arrUtc)) {
      return 0;
    }

    var mins = Math.round((arrUtc - depUtc) / 60000);
    // Overnight without arr_date: same calendar day wall times crossing midnight
    if (mins < 0 && String(opts.arrDate || '').trim() === '') {
      mins += 24 * 60;
    }
    return mins > 0 ? mins : 0;
  }

  function formatFlightDurationShort(totalMinutes) {
    var mins = parseInt(totalMinutes, 10);
    if (isNaN(mins) || mins <= 0) return '';
    var h = Math.floor(mins / 60);
    var m = mins % 60;
    if (h > 0 && m > 0) return h + 'h ' + m + 'm';
    if (h > 0) return h + 'h';
    return m + 'm';
  }

  function formatFlightDurationLong(totalMinutes) {
    var mins = parseInt(totalMinutes, 10);
    if (isNaN(mins) || mins <= 0) return '';
    var h = Math.floor(mins / 60);
    var m = mins % 60;
    if (h > 0 && m > 0) return h + ' hrs ' + m + ' min';
    if (h > 0) return h + ' hr' + (h > 1 ? 's' : '');
    return m + ' min';
  }

  global.QAirportTz = {
    BY_IATA: BY_IATA,
    BY_COUNTRY: BY_COUNTRY,
    normalizeIata: normalizeIata,
    airportIanaTimezone: airportIanaTimezone,
    flightDurationMinutes: flightDurationMinutes,
    formatFlightDurationShort: formatFlightDurationShort,
    formatFlightDurationLong: formatFlightDurationLong,
    localWallTimeToUtcMs: localWallTimeToUtcMs,
    parseDateTimeParts: parseDateTimeParts
  };
})(typeof window !== 'undefined' ? window : this);
JS;

file_put_contents(__DIR__ . '/airport_timezones.js', $out);
echo "Wrote airport_timezones.js with " . count($map) . " airports\n";
echo "Sample DPS=" . ($map['DPS'] ?? '?') . " DEL=" . ($map['DEL'] ?? '?') . "\n";
