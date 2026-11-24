/**
 * Airport IANA timezones for flight duration calculations.
 * Auto-generated from airports_fallback.json — do not hand-edit bulk entries.
 */
(function (global) {
  'use strict';
  var BY_IATA = {
  "ADD": "Africa/Addis_Ababa",
  "AGR": "Asia/Kolkata",
  "AJL": "Asia/Kolkata",
  "AKL": "Pacific/Auckland",
  "AMD": "Asia/Kolkata",
  "AMS": "Europe/Amsterdam",
  "ARN": "Europe/Stockholm",
  "ATH": "Europe/Athens",
  "ATL": "America/New_York",
  "ATQ": "Asia/Kolkata",
  "AUH": "Asia/Dubai",
  "BAH": "Asia/Bahrain",
  "BBI": "Asia/Kolkata",
  "BCN": "Europe/Madrid",
  "BDQ": "Asia/Kolkata",
  "BHJ": "Asia/Kolkata",
  "BHO": "Asia/Kolkata",
  "BHU": "Asia/Kolkata",
  "BHX": "Europe/London",
  "BKB": "Asia/Kolkata",
  "BKK": "Asia/Bangkok",
  "BLR": "Asia/Kolkata",
  "BNE": "Australia/Brisbane",
  "BOM": "Asia/Kolkata",
  "BOS": "America/New_York",
  "BPM": "Asia/Kolkata",
  "BPN": "Asia/Makassar",
  "BRU": "Europe/Brussels",
  "BUD": "Europe/Budapest",
  "BUP": "Asia/Kolkata",
  "CAI": "Africa/Cairo",
  "CAN": "Asia/Shanghai",
  "CCJ": "Asia/Kolkata",
  "CCU": "Asia/Kolkata",
  "CDG": "Europe/Paris",
  "CDP": "Asia/Kolkata",
  "CGK": "Asia/Jakarta",
  "CGP": "Asia/Dhaka",
  "CJB": "Asia/Kolkata",
  "CMB": "Asia/Colombo",
  "CNN": "Asia/Kolkata",
  "CNX": "Asia/Bangkok",
  "COK": "Asia/Kolkata",
  "CPH": "Europe/Copenhagen",
  "CPT": "Africa/Johannesburg",
  "CXR": "Asia/Ho_Chi_Minh",
  "DAC": "Asia/Dhaka",
  "DAD": "Asia/Ho_Chi_Minh",
  "DBR": "Asia/Kolkata",
  "DED": "Asia/Kolkata",
  "DEL": "Asia/Kolkata",
  "DFW": "America/Chicago",
  "DGH": "Asia/Kolkata",
  "DHM": "Asia/Kolkata",
  "DIB": "Asia/Kolkata",
  "DIU": "Asia/Kolkata",
  "DJJ": "Asia/Jayapura",
  "DMK": "Asia/Bangkok",
  "DMM": "Asia/Riyadh",
  "DMU": "Asia/Kolkata",
  "DOH": "Asia/Qatar",
  "DPS": "Asia/Makassar",
  "DUB": "Europe/Dublin",
  "DXB": "Asia/Dubai",
  "EWR": "America/New_York",
  "FCO": "Europe/Rome",
  "FRA": "Europe/Berlin",
  "GAU": "Asia/Kolkata",
  "GAY": "Asia/Kolkata",
  "GOI": "Asia/Kolkata",
  "GOP": "Asia/Kolkata",
  "GOX": "Asia/Kolkata",
  "GVA": "Europe/Zurich",
  "GWL": "Asia/Kolkata",
  "HAN": "Asia/Ho_Chi_Minh",
  "HBX": "Asia/Kolkata",
  "HEL": "Europe/Helsinki",
  "HJR": "Asia/Kolkata",
  "HKG": "Asia/Hong_Kong",
  "HKT": "Asia/Bangkok",
  "HND": "Asia/Tokyo",
  "HSR": "Asia/Kolkata",
  "HSS": "Asia/Kolkata",
  "HYD": "Asia/Kolkata",
  "IAD": "America/New_York",
  "ICN": "Asia/Seoul",
  "IDR": "Asia/Kolkata",
  "IMF": "Asia/Kolkata",
  "ISK": "Asia/Kolkata",
  "IST": "Europe/Istanbul",
  "IXA": "Asia/Kolkata",
  "IXB": "Asia/Kolkata",
  "IXC": "Asia/Kolkata",
  "IXD": "Asia/Kolkata",
  "IXE": "Asia/Kolkata",
  "IXG": "Asia/Kolkata",
  "IXH": "Asia/Kolkata",
  "IXI": "Asia/Kolkata",
  "IXJ": "Asia/Kolkata",
  "IXK": "Asia/Kolkata",
  "IXL": "Asia/Kolkata",
  "IXM": "Asia/Kolkata",
  "IXN": "Asia/Kolkata",
  "IXP": "Asia/Kolkata",
  "IXQ": "Asia/Kolkata",
  "IXR": "Asia/Kolkata",
  "IXS": "Asia/Kolkata",
  "IXU": "Asia/Kolkata",
  "IXV": "Asia/Kolkata",
  "IXW": "Asia/Kolkata",
  "IXX": "Asia/Kolkata",
  "IXY": "Asia/Kolkata",
  "IXZ": "Asia/Kolkata",
  "JAI": "Asia/Kolkata",
  "JDH": "Asia/Kolkata",
  "JED": "Asia/Riyadh",
  "JFK": "America/New_York",
  "JGA": "Asia/Kolkata",
  "JLR": "Asia/Kolkata",
  "JNB": "Africa/Johannesburg",
  "JOG": "Asia/Jakarta",
  "JRG": "Asia/Kolkata",
  "JRH": "Asia/Kolkata",
  "JSA": "Asia/Kolkata",
  "KIX": "Asia/Tokyo",
  "KLH": "Asia/Kolkata",
  "KNU": "Asia/Kolkata",
  "KQH": "Asia/Kolkata",
  "KTM": "Asia/Kathmandu",
  "KUL": "Asia/Kuala_Lumpur",
  "KUU": "Asia/Kolkata",
  "KWI": "Asia/Kuwait",
  "LAX": "America/Los_Angeles",
  "LGA": "America/New_York",
  "LGW": "Europe/London",
  "LHR": "Europe/London",
  "LIS": "Europe/Lisbon",
  "LKO": "Asia/Kolkata",
  "LUH": "Asia/Kolkata",
  "MAA": "Asia/Kolkata",
  "MAD": "Europe/Madrid",
  "MAN": "Europe/London",
  "MCT": "Asia/Muscat",
  "MDC": "Asia/Makassar",
  "MEL": "Australia/Melbourne",
  "MFM": "Asia/Macau",
  "MIA": "America/New_York",
  "MLE": "Indian/Maldives",
  "MNL": "Asia/Manila",
  "MRU": "Indian/Mauritius",
  "MUC": "Europe/Berlin",
  "MXP": "Europe/Rome",
  "MYQ": "Asia/Kolkata",
  "NAG": "Asia/Kolkata",
  "NBO": "Africa/Nairobi",
  "NDC": "Asia/Kolkata",
  "NRT": "Asia/Tokyo",
  "ORD": "America/Chicago",
  "ORY": "Europe/Paris",
  "OSL": "Europe/Oslo",
  "PAT": "Asia/Kolkata",
  "PBD": "Asia/Kolkata",
  "PEK": "Asia/Shanghai",
  "PER": "Australia/Perth",
  "PGH": "Asia/Kolkata",
  "PNQ": "Asia/Kolkata",
  "PRG": "Europe/Prague",
  "PUT": "Asia/Kolkata",
  "PVG": "Asia/Shanghai",
  "RAJ": "Asia/Kolkata",
  "RDP": "Asia/Kolkata",
  "RJA": "Asia/Kolkata",
  "RJI": "Asia/Kolkata",
  "RMD": "Asia/Kolkata",
  "RPR": "Asia/Kolkata",
  "RRK": "Asia/Kolkata",
  "RUH": "Asia/Riyadh",
  "SAG": "Asia/Kolkata",
  "SAW": "Europe/Istanbul",
  "SEA": "America/Los_Angeles",
  "SEZ": "Indian/Mahe",
  "SFO": "America/Los_Angeles",
  "SGN": "Asia/Ho_Chi_Minh",
  "SHJ": "Asia/Dubai",
  "SHL": "Asia/Kolkata",
  "SIN": "Asia/Singapore",
  "SSE": "Asia/Kolkata",
  "STV": "Asia/Kolkata",
  "SUB": "Asia/Jakarta",
  "SXR": "Asia/Kolkata",
  "SYD": "Australia/Sydney",
  "TCR": "Asia/Kolkata",
  "TEZ": "Asia/Kolkata",
  "TIR": "Asia/Kolkata",
  "TLV": "Asia/Jerusalem",
  "TPE": "Asia/Taipei",
  "TRV": "Asia/Kolkata",
  "TRZ": "Asia/Kolkata",
  "UDR": "Asia/Kolkata",
  "UNN": "Asia/Bangkok",
  "UPG": "Asia/Makassar",
  "VGA": "Asia/Kolkata",
  "VIE": "Europe/Vienna",
  "VNS": "Asia/Kolkata",
  "VTZ": "Asia/Kolkata",
  "WAW": "Europe/Warsaw",
  "WGC": "Asia/Kolkata",
  "YUL": "America/Toronto",
  "YVR": "America/Vancouver",
  "YYZ": "America/Toronto",
  "ZER": "Asia/Kolkata",
  "ZRH": "Europe/Zurich"
};
  var BY_COUNTRY = {
  "India": "Asia\/Kolkata",
  "United Arab Emirates": "Asia\/Dubai",
  "Qatar": "Asia\/Qatar",
  "Bahrain": "Asia\/Bahrain",
  "Kuwait": "Asia\/Kuwait",
  "Saudi Arabia": "Asia\/Riyadh",
  "Oman": "Asia\/Muscat",
  "Singapore": "Asia\/Singapore",
  "Malaysia": "Asia\/Kuala_Lumpur",
  "Thailand": "Asia\/Bangkok",
  "Hong Kong": "Asia\/Hong_Kong",
  "Macau": "Asia\/Macau",
  "Taiwan": "Asia\/Taipei",
  "Japan": "Asia\/Tokyo",
  "South Korea": "Asia\/Seoul",
  "China": "Asia\/Shanghai",
  "Philippines": "Asia\/Manila",
  "Vietnam": "Asia\/Ho_Chi_Minh",
  "Sri Lanka": "Asia\/Colombo",
  "Maldives": "Indian\/Maldives",
  "Nepal": "Asia\/Kathmandu",
  "Bangladesh": "Asia\/Dhaka",
  "United Kingdom": "Europe\/London",
  "France": "Europe\/Paris",
  "Germany": "Europe\/Berlin",
  "Netherlands": "Europe\/Amsterdam",
  "Switzerland": "Europe\/Zurich",
  "Italy": "Europe\/Rome",
  "Spain": "Europe\/Madrid",
  "Turkey": "Europe\/Istanbul",
  "Egypt": "Africa\/Cairo",
  "South Africa": "Africa\/Johannesburg",
  "Kenya": "Africa\/Nairobi",
  "Ethiopia": "Africa\/Addis_Ababa",
  "Mauritius": "Indian\/Mauritius",
  "Seychelles": "Indian\/Mahe",
  "Greece": "Europe\/Athens",
  "Austria": "Europe\/Vienna",
  "Czech Republic": "Europe\/Prague",
  "Hungary": "Europe\/Budapest",
  "Poland": "Europe\/Warsaw",
  "Finland": "Europe\/Helsinki",
  "Denmark": "Europe\/Copenhagen",
  "Norway": "Europe\/Oslo",
  "Sweden": "Europe\/Stockholm",
  "Ireland": "Europe\/Dublin",
  "Portugal": "Europe\/Lisbon",
  "Belgium": "Europe\/Brussels",
  "Israel": "Asia\/Jerusalem",
  "New Zealand": "Pacific\/Auckland"
};

  function normalizeIata(code) {
    var raw = String(code || '').trim();
    if (!raw) return '';
    // Prefer explicit IATA in parentheses: "Ngurah Rai (DPS)"
    var m = raw.match(/\(([A-Za-z]{3})\)\s*$/);
    if (m) return m[1].toUpperCase();
    m = raw.match(/\(([A-Za-z]{3})\)/);
    if (m) return m[1].toUpperCase();
    if (/^[A-Za-z]{3}$/.test(raw)) return raw.toUpperCase();
    // Trailing bare code: "Delhi DEL"
    m = raw.match(/\s([A-Za-z]{3})$/);
    if (m) return m[1].toUpperCase();
    return '';
  }

  var PLACE_HINTS = [
    { re: /NGURAH|DENPASAR|\bBALI\b|\bDPS\b/, tz: 'Asia/Makassar' },
    { re: /SOEKARNO|JAKARTA|\bCGK\b/, tz: 'Asia/Jakarta' },
    { re: /DUBAI|\bDXB\b/, tz: 'Asia/Dubai' },
    { re: /ABU\s*DHABI|\bAUH\b/, tz: 'Asia/Dubai' },
    { re: /DOHA|HAMAD|\bDOH\b/, tz: 'Asia/Qatar' },
    { re: /SINGAPORE|CHANGI|\bSIN\b/, tz: 'Asia/Singapore' },
    { re: /BANGKOK|SUVARNABHUMI|DON\s*MUEANG|\bBKK\b|\bDMK\b/, tz: 'Asia/Bangkok' },
    { re: /PHUKET|\bHKT\b/, tz: 'Asia/Bangkok' },
    { re: /MALE|VELANA|MALDIVES|\bMLE\b/, tz: 'Indian/Maldives' },
    { re: /COLOMBO|BANDARANAIKE|\bCMB\b/, tz: 'Asia/Colombo' },
    { re: /KATHMANDU|TRIBHUVAN|\bKTM\b/, tz: 'Asia/Kathmandu' },
    { re: /HEATHROW|GATWICK|LONDON|\bLHR\b|\bLGW\b/, tz: 'Europe/London' },
    { re: /FRANKFURT|\bFRA\b/, tz: 'Europe/Berlin' },
    { re: /PARIS|CHARLES\s*DE\s*GAULLE|\bCDG\b/, tz: 'Europe/Paris' },
    { re: /ISTANBUL|\bIST\b|\bSAW\b/, tz: 'Europe/Istanbul' },
    { re: /NEW\s*YORK|JFK|NEWARK|LAGUARDIA/, tz: 'America/New_York' },
    { re: /LOS\s*ANGELES|\bLAX\b/, tz: 'America/Los_Angeles' },
    { re: /SAN\s*FRANCISCO|\bSFO\b/, tz: 'America/Los_Angeles' },
    { re: /SYDNEY|\bSYD\b/, tz: 'Australia/Sydney' },
    { re: /MELBOURNE|\bMEL\b/, tz: 'Australia/Melbourne' },
    { re: /PERTH|\bPER\b/, tz: 'Australia/Perth' },
    { re: /DELHI|INDIRA\s*GANDHI|\bDEL\b/, tz: 'Asia/Kolkata' },
    { re: /MUMBAI|BOMBAY|CHHATRAPATI|\bBOM\b/, tz: 'Asia/Kolkata' }
  ];

  function airportIanaTimezone(iataOrPlace, countryHint) {
    var iata = normalizeIata(iataOrPlace);
    if (iata && BY_IATA[iata]) {
      return BY_IATA[iata];
    }
    var place = String(iataOrPlace || '').toUpperCase();
    for (var i = 0; i < PLACE_HINTS.length; i++) {
      if (PLACE_HINTS[i].re.test(place)) {
        return PLACE_HINTS[i].tz;
      }
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

    var dm = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!dm) {
      dm = dateStr.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/);
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
    var tm = timeStr.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/i);
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
        if (/^\d{10,13}$/.test(s)) {
          return true;
        }
        // ISO-8601 / RFC3339 style
        if (/^\d{4}-\d{2}-\d{2}T/.test(s)) {
          return true;
        }
        if (/[zZ]|[+\-]\d{2}:?\d{2}$/.test(s) && /\d{4}-\d{2}-\d{2}/.test(s)) {
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

    // Prefer explicit IATA when mapped; otherwise use full place text for hints
    // (truncated names like "Ngurah Rai Ar..." must not rely on a bad 3-letter guess).
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