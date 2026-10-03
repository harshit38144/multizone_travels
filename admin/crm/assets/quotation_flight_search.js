/* Flight search for Quotation Generator (mirrors admin/etickets.php flow) */
(function ($) {
    'use strict';

    var qfsSelectedOnward = null;
    var qfsSelectedReturn = null;
    var qfsSelectedOnwardId = '';
    var qfsSelectedReturnId = '';
    var qfsSearchContext = { from: '', to: '', date: '', returnDate: '' };
    var QFS_PAGE_SIZE = 12;
    var qfsResultsState = {
        tType: 'ONEWAY',
        from: '',
        to: '',
        fromCity: '',
        toCity: '',
        date: '',
        returnDate: '',
        onward: [],
        returning: [],
        onwardPage: 1,
        returnPage: 1,
        stopFilter: 'all',
        timeFilter: 'all',
        sortType: 'price',
        sortOrder: 'asc'
    };

    window.qfsOpenDatePicker = function (id) {
        var $text = $('#' + id + 'Text');
        if ($text.hasClass('hasDatepicker')) {
            $text.datepicker('show');
        } else {
            $text.trigger('focus');
        }
    };

    /* Visible "<id>Text" field shows dd/mm/yy; hidden "<id>" keeps YYYY-MM-DD for the search API. */
    function qfsYmdToDate(ymd) {
        var m = moment(ymd, 'YYYY-MM-DD', true);
        return m.isValid() ? m.toDate() : null;
    }

    function qfsSetDateField(id, ymd) {
        var $hidden = $('#' + id);
        var $text = $('#' + id + 'Text');
        var min = String($hidden.attr('min') || '');
        if (ymd && min && ymd < min) {
            ymd = min;
        }
        $hidden.val(ymd || '');
        var d = qfsYmdToDate(ymd);
        if ($text.hasClass('hasDatepicker')) {
            $text.datepicker('setDate', d);
        } else {
            $text.val(d ? moment(d).format('DD/MM/YY') : '');
        }
    }

    function qfsSetDateMin(id, ymd) {
        $('#' + id).attr('min', ymd);
        var $text = $('#' + id + 'Text');
        if ($text.hasClass('hasDatepicker')) {
            $text.datepicker('option', 'minDate', qfsYmdToDate(ymd));
        }
        var cur = String($('#' + id).val() || '');
        if (cur && ymd && cur < ymd) {
            qfsSetDateField(id, ymd);
        }
    }

    /* jQuery UI anchors the popup with document offset. Inside the scrolled
       quotation page that lands the calendar far below the Search Flight field. */
    function qfsPlaceDatepicker(input) {
        var dp = document.getElementById('ui-datepicker-div');
        if (!dp || !input || dp.style.display === 'none') {
            return;
        }
        var rect = input.getBoundingClientRect();
        if (!rect.width && !rect.height) {
            return;
        }
        var gap = 4;
        var dpH = dp.offsetHeight || 280;
        var dpW = dp.offsetWidth || 240;
        var top = rect.bottom + gap;
        if (top + dpH > window.innerHeight - 8 && rect.top - dpH - gap > 8) {
            top = rect.top - dpH - gap;
        }
        var left = rect.left;
        if (left + dpW > window.innerWidth - 8) {
            left = window.innerWidth - dpW - 8;
        }
        if (left < 8) {
            left = 8;
        }
        dp.style.position = 'fixed';
        dp.style.top = Math.round(top) + 'px';
        dp.style.left = Math.round(left) + 'px';
        dp.style.right = 'auto';
        dp.style.bottom = 'auto';
        dp.style.zIndex = '2200';
    }

    function qfsFollowDatepicker(input) {
        var follow = function () {
            qfsPlaceDatepicker(input);
        };
        $(window).off('scroll.qfsDp resize.qfsDp').on('scroll.qfsDp resize.qfsDp', follow);
        $('#qfsSearchModal').off('scroll.qfsDp').on('scroll.qfsDp', follow);
        setTimeout(follow, 0);
        setTimeout(follow, 60);
    }

    function qfsInitDateField(id) {
        var $hidden = $('#' + id);
        var $text = $('#' + id + 'Text');
        if (!$text.length) {
            return;
        }
        if ($.fn.datepicker) {
            $text.datepicker({
                dateFormat: 'dd/mm/y',
                showButtonPanel: true,
                closeText: 'Done',
                currentText: 'Today',
                prevText: '',
                nextText: '',
                minDate: qfsYmdToDate(String($hidden.attr('min') || '')),
                beforeShow: function (input, inst) {
                    inst.dpDiv.addClass('crm-q-datepicker');
                    qfsFollowDatepicker(input);
                },
                onChangeMonthYear: function () {
                    qfsFollowDatepicker($text[0]);
                },
                onSelect: function () {
                    $text.trigger('change');
                },
                onClose: function () {
                    $(window).off('scroll.qfsDp resize.qfsDp');
                    $('#qfsSearchModal').off('scroll.qfsDp');
                }
            });
        }
        $text.on('change', function () {
            var raw = String($text.val() || '').trim();
            var m = moment(raw, ['DD/MM/YY', 'D/M/YY', 'DD/MM/YYYY', 'D/M/YYYY', 'DD-MM-YY', 'DD-MM-YYYY'], true);
            if (!m.isValid()) {
                qfsSetDateField(id, String($hidden.val() || ''));
                return;
            }
            qfsSetDateField(id, m.format('YYYY-MM-DD'));
            $hidden.trigger('change', ['user']);
        });
        qfsSetDateField(id, String($hidden.val() || ''));
    }

    function initQfsAirportAutosuggest(inputId, suggestDivId) {
        var timeoutId = null;
        var inputEl = $('#' + inputId);
        var suggestObj = $('#' + suggestDivId);

        function positionSuggest() {
            if (!inputEl.length || !suggestObj.length) {
                return;
            }
            var rect = inputEl[0].getBoundingClientRect();
            var width = Math.max(rect.width, 220);
            var left = Math.min(rect.left, window.innerWidth - width - 8);
            left = Math.max(8, left);
            var top = rect.bottom + 4;
            var maxH = Math.min(250, Math.max(120, window.innerHeight - top - 12));
            if (suggestObj.parent()[0] !== document.body) {
                suggestObj.appendTo(document.body);
            }
            suggestObj
                .addClass('qfs-airport-suggest-open')
                .css({
                    position: 'fixed',
                    left: left + 'px',
                    top: top + 'px',
                    width: width + 'px',
                    right: 'auto',
                    zIndex: 2200,
                    maxHeight: maxH + 'px',
                    display: 'block',
                    visibility: 'visible'
                })
                .show();
        }

        function hideSuggest() {
            suggestObj
                .removeClass('qfs-airport-suggest-open')
                .hide()
                .css({
                    position: '',
                    left: '',
                    top: '',
                    width: '',
                    right: '',
                    zIndex: '',
                    maxHeight: '',
                    visibility: ''
                });
            var $field = inputEl.closest('.qfs-airport-field, .qfs-route-field');
            if ($field.length && suggestObj.parent()[0] !== $field[0]) {
                $field.append(suggestObj);
            }
        }

        inputEl.on('input keyup', function () {
            var query = $(this).val().trim();
            if (query.length < 2) {
                hideSuggest();
                return;
            }
            clearTimeout(timeoutId);
            timeoutId = setTimeout(function () {
                $.ajax({
                    url: 'ajax/mmt_autosuggest.php',
                    type: 'GET',
                    dataType: 'json',
                    data: { q: query },
                    success: function (res) {
                        suggestObj.empty();
                        var items = res && res.r ? res.r : (Array.isArray(res) ? res : []);
                        if (!Array.isArray(items)) {
                            items = [items];
                        }
                        var html = '';
                        var count = 0;
                        for (var i = 0; i < items.length; i++) {
                            var item = items[i] || {};
                            var code = item.iata || '';
                            var city = item.ct || item.cName || '';
                            var country = item.cnty || item.countryName || '';
                            // Input value: City (CODE) — country stays in dropdown only
                            var fullStr = city + ' (' + code + ')';
                            if (code && city) {
                                html += '<div class="qfs-suggest-item p-2 border-bottom" style="cursor:pointer; font-size:14px;" data-code="' + code + '" data-full="' + $('<div>').text(fullStr).html() + '" data-city="' + $('<div>').text(city).html() + '">' +
                                    '<div class="d-flex justify-content-between">' +
                                    '<div><i class="fa fa-plane text-muted mr-1"></i> ' + city + ' <small class="text-muted">' + country + '</small></div>' +
                                    '<div class="font-weight-bold text-primary">' + code + '</div></div></div>';
                                count++;
                            }
                        }
                        if (count > 0) {
                            suggestObj.html(html);
                            positionSuggest();
                        } else {
                            hideSuggest();
                        }
                    },
                    error: function () {
                        hideSuggest();
                    }
                });
            }, 300);
        });

        // Use document delegation so clicks still work after suggest is moved to <body>.
        $(document).on('click', '#' + suggestDivId + ' .qfs-suggest-item', function () {
            var code = $(this).data('code');
            var fullText = $(this).data('full');
            var city = $(this).data('city');
            inputEl.attr('data-code', code);
            if (city) {
                inputEl.attr('data-city', city);
            }
            inputEl.val(fullText ? fullText : code);
            hideSuggest();
            qfsSyncReturnRoute();
        });

        $(document).on('mousedown', function (e) {
            if (!$(e.target).closest('#' + inputId).length && !$(e.target).closest('#' + suggestDivId).length) {
                hideSuggest();
            }
        });

        $(window).on('resize', function () {
            if (suggestObj.hasClass('qfs-airport-suggest-open')) {
                positionSuggest();
            }
        });

        $('#qfsSearchModal').on('scroll hidden.bs.modal', function () {
            hideSuggest();
        });
    }

    function formatLayoverDuration(arrTimeRaw, depTimeRaw) {
        if (!arrTimeRaw || !depTimeRaw || !moment(arrTimeRaw).isValid() || !moment(depTimeRaw).isValid()) {
            return '';
        }
        var mins = moment(depTimeRaw).diff(moment(arrTimeRaw), 'minutes');
        if (mins < 0) {
            return '';
        }
        var h = Math.floor(mins / 60);
        var m = mins % 60;
        var parts = [];
        if (h > 0) {
            parts.push(h + ' hr' + (h > 1 ? 's' : ''));
        }
        if (m > 0) {
            parts.push(m + ' min');
        }
        return parts.join(' ') || '0 min';
    }

    function qfsNormalizeBaggageValue(val) {
        if (val == null || val === false) {
            return '';
        }
        if (typeof val === 'number' && !isNaN(val) && val > 0) {
            return val + ' kg';
        }
        if (typeof val === 'object') {
            var weight = val.weight || val.wt || val.amount || val.value || val.qty || val.quantity || '';
            var unitRaw = String(val.unit || val.weightUnit || val.wu || '').trim().toLowerCase();
            var desc = String(val.text || val.label || val.desc || val.description || '').trim();
            var isPiece = /^(pc|pcs|piece|pieces)$/.test(unitRaw) || /\bpcs?\b|\bpiece/i.test(desc);
            if (weight !== '' && weight != null) {
                var wStr = String(weight).trim();
                if (/^\d+(\.\d+)?$/.test(wStr)) {
                    if (isPiece) {
                        return String(parseFloat(wStr)) + ' pc';
                    }
                    var unit = unitRaw === 'kgs' || unitRaw === 'kg' || unitRaw === '' ? 'kg' : unitRaw;
                    return String(parseFloat(wStr)) + ' ' + unit;
                }
                return wStr;
            }
            if (desc) {
                return desc;
            }
            return '';
        }
        var str = String(val).trim();
        if (!str || /^(n\/?a|nil|none|-|null|undefined)$/i.test(str)) {
            return '';
        }
        return str;
    }

    function qfsPickFirstBaggage(obj, keys) {
        if (!obj || typeof obj !== 'object') {
            return '';
        }
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            if (Object.prototype.hasOwnProperty.call(obj, key) && obj[key] != null && obj[key] !== '') {
                var normalized = qfsNormalizeBaggageValue(obj[key]);
                if (normalized) {
                    return normalized;
                }
            }
        }
        return '';
    }

    function qfsParseCombinedBaggage(text) {
        var out = { hand: '', checkin: '' };
        var str = String(text || '').trim();
        if (!str) {
            return out;
        }
        var cabinMatch = str.match(/(?:cabin|hand|carry[\s-]?on)\s*(?:bag(?:gage)?|baggage)?\s*[:\-]?\s*([^|/·•]+)/i);
        var checkMatch = str.match(/(?:check[\s-]?in|checked|hold)\s*(?:bag(?:gage)?|baggage)?\s*[:\-]?\s*([^|/·•]+)/i);
        if (cabinMatch) {
            out.hand = qfsNormalizeBaggageValue(cabinMatch[1]);
        }
        if (checkMatch) {
            out.checkin = qfsNormalizeBaggageValue(checkMatch[1]);
        }
        if (!out.hand && !out.checkin && /\d/.test(str)) {
            // Single allowance string — treat as check-in unless clearly cabin-only.
            if (/cabin|hand|carry/i.test(str) && !/check/i.test(str)) {
                out.hand = qfsNormalizeBaggageValue(str);
            } else {
                out.checkin = qfsNormalizeBaggageValue(str);
            }
        }
        return out;
    }

    function qfsPaxBagPiece(piece) {
        if (piece == null || piece === '') {
            return '';
        }
        if (typeof piece !== 'object') {
            return qfsNormalizeBaggageValue(piece);
        }
        var pax = piece.adt || piece.chd || piece.inf || piece;
        if (pax && typeof pax === 'object' && pax !== piece && (pax.qty != null || pax.desc || pax.weight)) {
            return qfsNormalizeBaggageValue(pax);
        }
        return qfsNormalizeBaggageValue(piece);
    }

    function qfsReadAmenitiesBaggage(node) {
        var bag = node && node.amenities && node.amenities.baggage;
        if (!bag || typeof bag !== 'object') {
            return { hand: '', checkin: '' };
        }
        return {
            hand: qfsPaxBagPiece(bag.cabin || bag.hand || bag.cabinBaggage),
            checkin: qfsPaxBagPiece(bag.checkin || bag.checkIn || bag.checked)
        };
    }

    function qfsExtractBaggage(sources) {
        var handKeys = [
            'hand_baggage', 'handBaggage', 'handBag', 'cabinBaggage', 'cabin_baggage',
            'cBag', 'cabinBag', 'carryOnBaggage', 'carry_on_baggage', 'handLuggage',
            'cbag', 'CabinBaggage'
        ];
        var checkinKeys = [
            'checkin_baggage', 'checkinBaggage', 'checkInBaggage', 'check_in_baggage',
            'iBag', 'checkInBag', 'checkedBaggage', 'checked_baggage', 'holdBaggage',
            'frBag', 'freeBaggage', 'includedBaggage', 'IncludedBaggage', 'baggageAllowance',
            'ibag', 'CheckInBaggage'
        ];
        var hand = '';
        var checkin = '';
        var list = Array.isArray(sources) ? sources : [sources];
        var i;

        function walk(node, depth) {
            if (!node || depth > 6) {
                return;
            }
            if (Array.isArray(node)) {
                for (var ai = 0; ai < Math.min(node.length, 12); ai++) {
                    walk(node[ai], depth + 1);
                    if (hand && checkin) {
                        return;
                    }
                }
                return;
            }
            if (typeof node !== 'object') {
                return;
            }
            var amenityBag = qfsReadAmenitiesBaggage(node);
            if (!hand && amenityBag.hand) {
                hand = amenityBag.hand;
            }
            if (!checkin && amenityBag.checkin) {
                checkin = amenityBag.checkin;
            }
            if (!hand) {
                hand = qfsPickFirstBaggage(node, handKeys);
            }
            if (!checkin) {
                checkin = qfsPickFirstBaggage(node, checkinKeys);
            }
            if ((!hand || !checkin) && typeof node.baggage === 'string') {
                var parsed = qfsParseCombinedBaggage(node.baggage);
                if (!hand && parsed.hand) {
                    hand = parsed.hand;
                }
                if (!checkin && parsed.checkin) {
                    checkin = parsed.checkin;
                }
            }
            if (hand && checkin) {
                return;
            }
            var keys = Object.keys(node);
            for (var ki = 0; ki < keys.length; ki++) {
                var key = keys[ki];
                var val = node[key];
                if (val && typeof val === 'object') {
                    walk(val, depth + 1);
                    if (hand && checkin) {
                        return;
                    }
                }
            }
        }

        for (i = 0; i < list.length; i++) {
            walk(list[i], 0);
            if (hand && checkin) {
                break;
            }
        }
        return {
            hand_baggage: hand,
            checkin_baggage: checkin,
            baggage: (hand || checkin)
                ? ('Cabin: ' + (hand || '—') + ' | Check-in: ' + (checkin || '—'))
                : ''
        };
    }

    function qfsBaggageFromFlightItem(item, seg) {
        var journey = (item && (item.journey || item)) || {};
        var fares = journey.fares || {};
        var paxFares = (fares.paxFares && fares.paxFares.adt) ? fares.paxFares.adt : {};
        var primary = seg || item.primaryFlight || (journey.flights && journey.flights[0]) || item || {};
        var bag = qfsExtractBaggage([
            primary,
            journey,
            fares,
            paxFares,
            fares.totalFare,
            fares.fare,
            item
        ]);
        if (bag.hand_baggage || bag.checkin_baggage) {
            bag.baggage = 'Cabin: ' + (bag.hand_baggage || '—') + ' | Check-in: ' + (bag.checkin_baggage || '—');
        }
        return bag;
    }

    function segmentLocation(detail, fallbackCity, fallbackCode) {
        if (detail && (detail.name || detail.code)) {
            return (detail.name || fallbackCity || '') + (detail.code ? ' (' + detail.code + ')' : '');
        }
        if (fallbackCity && fallbackCode) {
            return fallbackCity + ' (' + fallbackCode + ')';
        }
        return fallbackCity || fallbackCode || '';
    }

    function mapSegmentToQuotationRow(seg, fare, bagInfo) {
        var airlineObj = seg.carrier || seg.airline || {};
        var fname = airlineObj.name || seg.airlineName || seg.carrierName || '';
        var fno = (airlineObj.code || 'FL') + '-' + (seg.flightNo || seg.flightNumber || '');
        var dRaw = (seg.depDetail && seg.depDetail.time) ? seg.depDetail.time : null;
        var aRaw = (seg.arrDetail && seg.arrDetail.time) ? seg.arrDetail.time : null;
        var depDate = '';
        var depTime = '';
        var arrDate = '';
        var arrTime = '';
        var bag = bagInfo || qfsExtractBaggage([seg]);

        if (dRaw && moment(dRaw).isValid()) {
            depDate = moment(dRaw).format('YYYY-MM-DD');
            depTime = moment(dRaw).format('HH:mm');
        }
        if (aRaw && moment(aRaw).isValid()) {
            arrDate = moment(aRaw).format('YYYY-MM-DD');
            arrTime = moment(aRaw).format('HH:mm');
        }

        return {
            from: segmentLocation(seg.depDetail),
            to: segmentLocation(seg.arrDetail),
            name: fname,
            fl_tr_no: fno,
            dep_date: depDate,
            dep_time: depTime,
            arr_date: arrDate,
            arr_time: arrTime,
            fare: fare || '',
            hand_baggage: bag.hand_baggage || '',
            checkin_baggage: bag.checkin_baggage || ''
        };
    }

    function mapJourneyToQuotationRows(f, legDate) {
        var segments = (f.segments && f.segments.length) ? f.segments : null;
        var journeyBag = qfsExtractBaggage([f, {
            hand_baggage: f.hand_baggage,
            checkin_baggage: f.checkin_baggage,
            baggage: f.baggage
        }]);
        if (!segments || segments.length <= 1) {
            return [mapFlightToQuotationRow(f, legDate)];
        }

        var rows = [];
        segments.forEach(function (seg, idx) {
            var segBag = qfsExtractBaggage([seg, journeyBag, f]);
            var row = mapSegmentToQuotationRow(seg, idx === 0 ? (f.tot || '') : '', segBag);
            if (idx === 0 && !row.dep_date && legDate) {
                row.dep_date = legDate;
            }
            if (idx > 0) {
                var prevSeg = segments[idx - 1];
                var prevArr = (prevSeg.arrDetail && prevSeg.arrDetail.time) ? prevSeg.arrDetail.time : null;
                var curDep = (seg.depDetail && seg.depDetail.time) ? seg.depDetail.time : null;
                row.layover_time = formatLayoverDuration(prevArr, curDep);
                row.layover_at = segmentLocation(prevSeg.arrDetail);
            }
            rows.push(row);
        });
        return rows;
    }

    function createQfsFlightCard(item, overallDepCode, overallArrCode, overallDepCityName, overallArrCityName, opts) {
        opts = opts || {};
        var journey = item.journey || item;
        var flightsArray = (journey.flights && journey.flights.length > 0) ? journey.flights : [item.primaryFlight || item];
        var primaryF = item.primaryFlight || flightsArray[0] || item;
        var pAirlineObj = primaryF.carrier || primaryF.airline || {};
        var pFname = pAirlineObj.name || primaryF.airlineName || primaryF.carrierName || 'Airline';
        var pFno = (pAirlineObj.code || 'FL') + '-' + (primaryF.flightNo || primaryF.flightNumber || '101');
        var pCarrierCode = pAirlineObj.code || '6E';
        var stops = flightsArray.length > 1 ? (flightsArray.length - 1) : 0;
        var stopsStr = stops === 0 ? 'Non Stop' : stops + ' Stop(s)';

        var pDurationRaw = item._qfsMeta
            ? item._qfsMeta.duration
            : (primaryF.flyTime || primaryF.duration || 150);
        var pDuration = typeof pDurationRaw === 'number'
            ? Math.floor(pDurationRaw / 60) + ' hrs ' + (pDurationRaw % 60) + ' min'
            : pDurationRaw;

        var guestFares = (item._qfsMeta && item._qfsMeta.fares) ? item._qfsMeta.fares : qfsJourneyGuestFares(journey, primaryF);
        var base = guestFares.base;
        var tax = guestFares.tax;
        var unitFare = guestFares.adt;

        var paxAdults = Math.max(1, parseInt(qfsResultsState.adults, 10) || 1);
        var paxChildren = Math.max(0, parseInt(qfsResultsState.children, 10) || 0);
        var paxInfants = Math.max(0, parseInt(qfsResultsState.infants, 10) || 0);
        var paxCount = Math.max(1, paxAdults + paxChildren + paxInfants);
        var totalFare = qfsGuestTotal(guestFares);
        var inr = function (n) {
            return '₹' + Math.round(n).toLocaleString('en-IN', { maximumFractionDigits: 0 });
        };
        var totalLabel = Math.round(totalFare).toLocaleString('en-IN', { maximumFractionDigits: 0 });
        var guestLine = function (label, count, fare, estimated) {
            if (!count) {
                return '';
            }
            return '<div class="qfs-pax-line"' + (estimated ? ' title="Fare not returned for this guest type — adult fare used"' : '') + '>' +
                '<span class="qfs-pax-type">' + label + '</span>' +
                '<span class="qfs-pax-calc">' + inr(fare) + ' × ' + count + (estimated ? '<sup>*</sup>' : '') + '</span>' +
                '<span class="qfs-pax-sum">' + inr(fare * count) + '</span></div>';
        };
        var guestBreakdownHtml = '<div class="qfs-pax-breakdown">' +
            guestLine('Adult', paxAdults, guestFares.adt, false) +
            guestLine('Child', paxChildren, guestFares.chd, guestFares.chdEstimated) +
            guestLine('Infant', paxInfants, guestFares.inf, guestFares.infEstimated) +
            '</div>';

        var dTimeRaw = (primaryF.depDetail && primaryF.depDetail.time) ? primaryF.depDetail.time : (primaryF.departureTime || primaryF.depTime || '08:00 AM');
        var aTimeRaw = (primaryF.arrDetail && primaryF.arrDetail.time) ? primaryF.arrDetail.time : (primaryF.arrivalTime || primaryF.arrTime || '10:30 AM');
        var dTime = moment(dTimeRaw).isValid() ? moment(dTimeRaw).format('DD MMM YYYY | hh:mm A') : dTimeRaw;
        var aTime = moment(aTimeRaw).isValid() ? moment(aTimeRaw).format('DD MMM YYYY | hh:mm A') : aTimeRaw;

        var bagInfo = qfsBaggageFromFlightItem(item, primaryF);
        var fData = {
            fname: pFname, fno: pFno, dTime: dTime, aTime: aTime,
            base: base, tax: tax, tot: totalFare,
            unit_fare: unitFare,
            pax_count: paxCount,
            pax_fares: {
                adult: guestFares.adt, child: guestFares.chd, infant: guestFares.inf,
                adults: paxAdults, children: paxChildren, infants: paxInfants
            },
            terminal: (primaryF.depDetail && primaryF.depDetail.terminal) ? primaryF.depDetail.terminal : 'T1',
            stops: stopsStr, duration: pDuration,
            baggage: bagInfo.baggage || primaryF.baggage || '',
            hand_baggage: bagInfo.hand_baggage || '',
            checkin_baggage: bagInfo.checkin_baggage || '',
            carrierCode: pCarrierCode,
            segments: flightsArray,
            depCity: overallDepCityName,
            arrCity: overallArrCityName,
            depCode: overallDepCode,
            arrCode: overallArrCode
        };
        var encodedData = encodeURIComponent(JSON.stringify(fData));
        var dTimeRawUnix = item._qfsMeta ? item._qfsMeta.dtime : moment(dTimeRaw).valueOf();
        var aTimeRawUnix = item._qfsMeta ? item._qfsMeta.atime : moment(aTimeRaw).valueOf();
        var qfsId = opts.qfsId || item._qfsId || '';
        var selectedBorder = opts.selected ? '2px solid #28a745' : '1px solid #e9ecef';

        var cardHtml = '<div class="card mb-2 qfs-select-flight-card" data-qfs-id="' + qfsId + '" data-flight="' + encodedData + '" data-duration="' + pDurationRaw + '" data-dtime="' + dTimeRawUnix + '" data-atime="' + aTimeRawUnix + '" data-stops="' + stops + '" data-airline="' + pFname + '" data-price="' + totalFare + '" style="border: ' + selectedBorder + '; box-shadow: none; border-radius:4px; cursor:pointer; background-color: #f8f9fa;">' +
            '<div class="card-body p-3 d-flex align-items-stretch">' +
            '<div class="qfs-flight-main flex-grow-1">';

        flightsArray.forEach(function (f, index) {
            var airlineObj = f.carrier || f.airline || {};
            var fname = airlineObj.name || f.airlineName || f.carrierName || 'Airline';
            var fno = (airlineObj.code || 'FL') + '-' + (f.flightNo || f.flightNumber || '101');
            var carrierCode = airlineObj.code || '6E';
            var logoUrl = 'ajax/image_proxy.php?url=' + encodeURIComponent('https://images.kiwi.com/airlines/64/' + carrierCode + '.png');

            var legDTimeRaw = (f.depDetail && f.depDetail.time) ? f.depDetail.time : (f.departureTime || f.depTime || '08:00 AM');
            var legATimeRaw = (f.arrDetail && f.arrDetail.time) ? f.arrDetail.time : (f.arrivalTime || f.arrTime || '10:30 AM');
            var legDTime = moment(legDTimeRaw).isValid() ? moment(legDTimeRaw).format('DD MMM YYYY | hh:mm A') : legDTimeRaw;
            var legATime = moment(legATimeRaw).isValid() ? moment(legATimeRaw).format('DD MMM YYYY | hh:mm A') : legATimeRaw;

            var durationRaw = f.flyTime || f.duration || 150;
            var duration = typeof durationRaw === 'number'
                ? Math.floor(durationRaw / 60) + ' hrs ' + (durationRaw % 60) + ' min'
                : durationRaw;

            var legDepCity = (f.depDetail && f.depDetail.name) ? f.depDetail.name : (index === 0 ? overallDepCityName : 'City');
            var legDepCode = (f.depDetail && f.depDetail.code) ? f.depDetail.code : (index === 0 ? overallDepCode : 'XXX');
            var legArrCity = (f.arrDetail && f.arrDetail.name) ? f.arrDetail.name : (index === flightsArray.length - 1 ? overallArrCityName : 'City');
            var legArrCode = (f.arrDetail && f.arrDetail.code) ? f.arrDetail.code : (index === flightsArray.length - 1 ? overallArrCode : 'XXX');

            var depTerminal = (f.depDetail && f.depDetail.terminal) ? 'Terminal ' + f.depDetail.terminal : 'Terminal 1';
            var arrTerminal = (f.arrDetail && f.arrDetail.terminal) ? 'Terminal ' + f.arrDetail.terminal : 'Terminal 1';
            var legStopsStr = flightsArray.length > 1 ? stopsStr : 'Non Stop';
            var legBag = index === 0 ? bagInfo : qfsExtractBaggage([f, bagInfo]);
            if (!legBag.hand_baggage) {
                legBag.hand_baggage = bagInfo.hand_baggage;
            }
            if (!legBag.checkin_baggage) {
                legBag.checkin_baggage = bagInfo.checkin_baggage;
            }

            cardHtml += '<div class="row align-items-center">' +
                '<div class="col-md-3 d-flex align-items-center" style="padding-right:0;">' +
                '<img src="' + logoUrl + '" alt="' + carrierCode + '" loading="lazy" decoding="async" style="width:30px; height:30px; object-fit:contain; margin-right:8px;">' +
                '<div><div style="font-weight:700; font-size:12px; color:#333; line-height:1.2;">' + fname + '</div>' +
                '<div class="text-muted" style="font-size:11px;">' + fno + '</div></div></div>' +
                '<div class="col-md-3" style="padding-left:5px; padding-right:5px;">' +
                '<div style="font-weight:700; font-size:12px; color:#333;">' + legDepCity + ' (' + legDepCode + ')</div>' +
                '<div class="text-muted" style="font-size:10px;">' + depTerminal + '</div>' +
                '<div class="text-muted" style="font-size:11px;">' + legDTime + '</div></div>' +
                '<div class="col-md-3" style="padding-left:5px; padding-right:5px;">' +
                '<div style="font-weight:700; font-size:12px; color:#333;">' + legArrCity + ' (' + legArrCode + ')</div>' +
                '<div class="text-muted" style="font-size:10px;">' + arrTerminal + '</div>' +
                '<div class="text-muted" style="font-size:11px;">' + legATime + '</div></div>' +
                '<div class="col-md-3 text-left" style="padding-left:5px;">' +
                '<div style="font-weight:700; font-size:12px; color:#333;">' + duration + '</div>' +
                '<div class="text-muted" style="font-size:11px;">' + legStopsStr + '</div>' +
                '<div class="qfs-card-baggage">' +
                '<span title="Hand baggage"><i class="fas fa-briefcase"></i> ' + $('<div>').text(legBag.hand_baggage || '—').html() + '</span>' +
                '<span title="Check-in baggage"><i class="fas fa-suitcase"></i> ' + $('<div>').text(legBag.checkin_baggage || '—').html() + '</span>' +
                '</div>' +
                '</div></div>';

            if (index < flightsArray.length - 1) {
                var nextF = flightsArray[index + 1];
                var nextDepRaw = (nextF.depDetail && nextF.depDetail.time) ? nextF.depDetail.time : (nextF.departureTime || nextF.depTime || null);
                var layoverStr = formatLayoverDuration(legATimeRaw, nextDepRaw);
                if (layoverStr) {
                    cardHtml += '<div class="qfs-layover text-center"><i class="fa fa-clock-o mr-1"></i>Layover at ' +
                        legArrCity + ' (' + legArrCode + '): <strong>' + layoverStr + '</strong></div>';
                }
            }
        });

        var seatsLeftMeta = item._qfsMeta ? item._qfsMeta.seatsLeft : null;
        var seatsHtml = '';
        if (seatsLeftMeta !== null && seatsLeftMeta !== undefined && !isNaN(parseInt(seatsLeftMeta, 10))) {
            seatsHtml = '<div class="text-muted" style="font-size:11px; margin-top:2px;">' +
                parseInt(seatsLeftMeta, 10) + ' seat' + (parseInt(seatsLeftMeta, 10) === 1 ? '' : 's') + ' left</div>';
        }
        cardHtml += '</div>' +
            '<div class="qfs-price-col">' +
            '<div class="qfs-price-value">₹' + totalLabel + '</div>' +
            '<div class="qfs-price-total-label">Total for ' + paxCount + ' guest' + (paxCount === 1 ? '' : 's') + '</div>' +
            guestBreakdownHtml +
            seatsHtml +
            '</div></div></div>';
        return cardHtml;
    }

    function mapFlightToQuotationRow(f, legDate) {
        var from = f.depCity || f.depCode || '';
        var to = f.arrCity || f.arrCode || '';
        var depDate = legDate || '';
        var depTime = '';
        var arrDate = '';
        var arrTime = '';
        var flNo = f.fno || '';

        if (f.segments && f.segments.length) {
            var first = f.segments[0];
            var last = f.segments[f.segments.length - 1];
            if (first.depDetail) {
                from = (first.depDetail.name || from) + (first.depDetail.code ? ' (' + first.depDetail.code + ')' : '');
            }
            if (last.arrDetail) {
                to = (last.arrDetail.name || to) + (last.arrDetail.code ? ' (' + last.arrDetail.code + ')' : '');
            }
            var dRaw = (first.depDetail && first.depDetail.time) ? first.depDetail.time : null;
            var aRaw = (last.arrDetail && last.arrDetail.time) ? last.arrDetail.time : null;
            if (dRaw && moment(dRaw).isValid()) {
                depDate = moment(dRaw).format('YYYY-MM-DD');
                depTime = moment(dRaw).format('HH:mm');
            }
            if (aRaw && moment(aRaw).isValid()) {
                arrDate = moment(aRaw).format('YYYY-MM-DD');
                arrTime = moment(aRaw).format('HH:mm');
            }
            if (!flNo && first.carrier) {
                flNo = (first.carrier.code || 'FL') + '-' + (first.flightNo || first.flightNumber || '');
            }
        }

        if (!depDate && f.dTime && moment(f.dTime, 'DD MMM YYYY | hh:mm A', true).isValid()) {
            depDate = moment(f.dTime, 'DD MMM YYYY | hh:mm A').format('YYYY-MM-DD');
            depTime = moment(f.dTime, 'DD MMM YYYY | hh:mm A').format('HH:mm');
        } else if (!depDate && legDate) {
            depDate = legDate;
        }

        if (!arrDate && f.aTime && moment(f.aTime, 'DD MMM YYYY | hh:mm A', true).isValid()) {
            arrDate = moment(f.aTime, 'DD MMM YYYY | hh:mm A').format('YYYY-MM-DD');
            arrTime = moment(f.aTime, 'DD MMM YYYY | hh:mm A').format('HH:mm');
        }

        var bag = qfsExtractBaggage([
            f,
            (f.segments && f.segments[0]) ? f.segments[0] : null,
            {
                hand_baggage: f.hand_baggage,
                checkin_baggage: f.checkin_baggage,
                baggage: f.baggage
            }
        ]);

        return {
            from: from,
            to: to,
            name: f.fname || '',
            fl_tr_no: flNo,
            dep_date: depDate,
            dep_time: depTime,
            arr_date: arrDate,
            arr_time: arrTime,
            fare: f.tot || '',
            hand_baggage: bag.hand_baggage || '',
            checkin_baggage: bag.checkin_baggage || ''
        };
    }

    function addFlightRowsToQuotation(flights, dates) {
        if (typeof window.qQuotationAddFlightJourney !== 'function' && typeof window.qQuotationAddFlightRow !== 'function') {
            return;
        }
        var labels = ['Outbound', 'Return'];
        flights.forEach(function (f, idx) {
            if (!f) {
                return;
            }
            var rows = mapJourneyToQuotationRows(f, dates[idx] || '');
            var opts = {
                label: labels[idx] || ('Flight ' + (idx + 1)),
                totalFare: f.tot || (rows[0] && rows[0].fare) || ''
            };
            if (typeof window.qQuotationAddFlightJourney === 'function' && rows.length) {
                window.qQuotationAddFlightJourney(rows, opts);
            } else {
                rows.forEach(function (row, rowIdx) {
                    if (rowIdx === 0) {
                        row.journey_start = true;
                        row.journey_label = opts.label;
                    }
                    window.qQuotationAddFlightRow(row);
                });
            }
        });
    }

    function qfsPaxFareTotal(paxFare) {
        if (!paxFare || typeof paxFare !== 'object') {
            return 0;
        }
        var tot = paxFare.total || paxFare.totalFare || {};
        var amount = typeof tot === 'object' ? parseFloat(tot.amount || tot.total) : parseFloat(tot);
        if (!isNaN(amount) && amount > 0) {
            return Math.round(amount);
        }
        var base = parseFloat((paxFare.base && (paxFare.base.amount || paxFare.base.baseFare)) || 0) || 0;
        var tax = parseFloat((paxFare.tax && (paxFare.tax.amount || paxFare.tax.taxes)) || 0) || 0;
        return Math.round(base + tax);
    }

    /**
     * Per-guest fares from the journey (paxFares.adt / chd / inf). A missing child or infant
     * fare falls back to the adult fare and is flagged so the card can say it is estimated.
     */
    function qfsJourneyGuestFares(journey, primaryF) {
        var faresObj = (journey && journey.fares) || {};
        var pax = faresObj.paxFares || {};
        var pick = function (keys) {
            for (var i = 0; i < keys.length; i++) {
                if (pax[keys[i]]) {
                    return pax[keys[i]];
                }
            }
            return null;
        };
        var adtObj = pick(['adt', 'ADT', 'adult']) || {};
        var totObj = adtObj.total || faresObj.totalFare || (primaryF && primaryF.fareDetails) || {};
        var base = Math.round(parseFloat((adtObj.base && (adtObj.base.amount || adtObj.base.baseFare)) || 3500) || 0);
        var tax = Math.round(parseFloat((adtObj.tax && (adtObj.tax.amount || adtObj.tax.taxes)) || 500) || 0);
        var adt = Math.round(parseFloat(totObj.amount || totObj.total || (base + tax)) || 0);
        var chd = qfsPaxFareTotal(pick(['chd', 'CHD', 'child']));
        var inf = qfsPaxFareTotal(pick(['inf', 'INF', 'infant']));
        return {
            adt: adt,
            chd: chd > 0 ? chd : adt,
            inf: inf > 0 ? inf : adt,
            chdEstimated: !(chd > 0),
            infEstimated: !(inf > 0),
            base: base,
            tax: tax
        };
    }

    function qfsGuestTotal(fares) {
        var s = qfsResultsState || {};
        var a = Math.max(1, parseInt(s.adults, 10) || 1);
        var c = Math.max(0, parseInt(s.children, 10) || 0);
        var i = Math.max(0, parseInt(s.infants, 10) || 0);
        return (fares.adt * a) + (fares.chd * c) + (fares.inf * i);
    }

    function qfsBuildSearchText(journey, flightsArray, meta) {
        var parts = [];
        flightsArray.forEach(function (f) {
            var al = f.carrier || f.airline || {};
            parts.push(al.name || f.airlineName || f.carrierName || '');
            parts.push((al.code || '') + '-' + (f.flightNo || f.flightNumber || ''));
            parts.push((al.code || '') + (f.flightNo || f.flightNumber || ''));
            ['depDetail', 'arrDetail'].forEach(function (k) {
                var d = f[k] || {};
                parts.push(d.code || '', d.name || '', d.city || '');
            });
        });
        parts.push(String(meta.price || ''));
        parts.push(meta.stops === 0 ? 'non stop nonstop direct' : meta.stops + ' stop');
        return parts.join(' ').toLowerCase();
    }

    function qfsPrepareFlightList(list, prefix) {
        return (list || []).map(function (item, index) {
            var journey = item.journey || item;
            var flightsArray = (journey.flights && journey.flights.length > 0) ? journey.flights : [item.primaryFlight || item];
            var primaryF = item.primaryFlight || flightsArray[0] || item;
            var guestFares = qfsJourneyGuestFares(journey, primaryF);
            var price = qfsGuestTotal(guestFares);
            var dTimeRaw = (primaryF.depDetail && primaryF.depDetail.time) ? primaryF.depDetail.time : (primaryF.departureTime || primaryF.depTime || '');
            var aTimeRaw = (primaryF.arrDetail && primaryF.arrDetail.time) ? primaryF.arrDetail.time : (primaryF.arrivalTime || primaryF.arrTime || '');
            var duration = primaryF.flyTime || primaryF.duration || 150;
            if (typeof duration !== 'number') {
                duration = parseInt(duration, 10) || 150;
            }
            var seatsLeft = parseInt(journey.seatsLeft, 10);
            if (isNaN(seatsLeft)) {
                seatsLeft = parseInt(item.seatsLeft, 10);
            }
            if (isNaN(seatsLeft)) {
                seatsLeft = null;
            }
            item._qfsId = prefix + '-' + index;
            item._qfsMeta = {
                price: price,
                fares: guestFares,
                duration: duration,
                dtime: moment(dTimeRaw).isValid() ? moment(dTimeRaw).valueOf() : 0,
                atime: moment(aTimeRaw).isValid() ? moment(aTimeRaw).valueOf() : 0,
                stops: flightsArray.length > 1 ? (flightsArray.length - 1) : 0,
                seatsLeft: seatsLeft
            };
            item._qfsMeta.searchText = qfsBuildSearchText(journey, flightsArray, item._qfsMeta);
            return item;
        });
    }

    function qfsFlightPassesFilters(item) {
        var meta = item._qfsMeta || {};
        var stopFilter = qfsResultsState.stopFilter;
        var timeFilter = qfsResultsState.timeFilter;
        var requiredSeats = parseInt(qfsResultsState.requiredSeats, 10);
        if (isNaN(requiredSeats) || requiredSeats < 1) {
            requiredSeats = 1;
        }

        var query = String(qfsResultsState.searchText || '').trim().toLowerCase();
        if (query) {
            var haystack = meta.searchText || '';
            var terms = query.split(/\s+/);
            for (var t = 0; t < terms.length; t++) {
                if (haystack.indexOf(terms[t]) === -1) {
                    return false;
                }
            }
        }

        if (meta.seatsLeft !== null && meta.seatsLeft !== undefined) {
            var seatsLeft = parseInt(meta.seatsLeft, 10);
            if (isNaN(seatsLeft) || seatsLeft < requiredSeats) {
                return false;
            }
        }

        if (stopFilter !== 'all') {
            var cardStops = parseInt(meta.stops, 10);
            if (isNaN(cardStops)) {
                cardStops = 0;
            }
            if (stopFilter === '0' && cardStops !== 0) {
                return false;
            }
            if (stopFilter === '1' && cardStops !== 1) {
                return false;
            }
            if (stopFilter === '2' && cardStops < 2) {
                return false;
            }
        }

        if (timeFilter !== 'all') {
            var hour = (meta.dtime && typeof moment === 'function') ? moment(meta.dtime).hour() : NaN;
            if (isNaN(hour)) {
                return false;
            }
            if (timeFilter === 'morning' && (hour < 6 || hour >= 12)) {
                return false;
            }
            if (timeFilter === 'afternoon' && (hour < 12 || hour >= 18)) {
                return false;
            }
            if (timeFilter === 'evening' && (hour < 18 || hour > 23)) {
                return false;
            }
            if (timeFilter === 'night' && hour > 5) {
                return false;
            }
        }
        return true;
    }

    function qfsGetFilteredSortedList(list) {
        var filtered = (list || []).filter(qfsFlightPassesFilters);
        var sortType = qfsResultsState.sortType || 'price';
        var sortOrder = qfsResultsState.sortOrder || 'asc';
        filtered.sort(function (a, b) {
            var valA = parseInt((a._qfsMeta && a._qfsMeta[sortType]) || 0, 10);
            var valB = parseInt((b._qfsMeta && b._qfsMeta[sortType]) || 0, 10);
            return sortOrder === 'asc' ? valA - valB : valB - valA;
        });
        return filtered;
    }

    function qfsBuildPaginationHtml(listKey, page, totalFiltered) {
        var totalPages = Math.max(1, Math.ceil(totalFiltered / QFS_PAGE_SIZE));
        page = Math.min(Math.max(1, page), totalPages);
        if (totalFiltered <= QFS_PAGE_SIZE) {
            return '<div class="qfs-pagination d-flex justify-content-between align-items-center mt-2 mb-1">' +
                '<small class="text-muted">' + totalFiltered + ' flight' + (totalFiltered === 1 ? '' : 's') + '</small>' +
                '</div>';
        }
        var fromIdx = ((page - 1) * QFS_PAGE_SIZE) + 1;
        var toIdx = Math.min(page * QFS_PAGE_SIZE, totalFiltered);
        return '<div class="qfs-pagination d-flex justify-content-between align-items-center mt-2 mb-1 flex-wrap">' +
            '<small class="text-muted mb-1">Showing ' + fromIdx + '–' + toIdx + ' of ' + totalFiltered + '</small>' +
            '<div class="btn-group btn-group-sm mb-1" role="group">' +
            '<button type="button" class="btn btn-outline-secondary qfs-page-btn" data-list="' + listKey + '" data-page="1" ' + (page <= 1 ? 'disabled' : '') + ' title="First">&laquo;</button>' +
            '<button type="button" class="btn btn-outline-secondary qfs-page-btn" data-list="' + listKey + '" data-page="' + (page - 1) + '" ' + (page <= 1 ? 'disabled' : '') + ' title="Previous">&lsaquo;</button>' +
            '<button type="button" class="btn btn-secondary" disabled>Page ' + page + ' / ' + totalPages + '</button>' +
            '<button type="button" class="btn btn-outline-secondary qfs-page-btn" data-list="' + listKey + '" data-page="' + (page + 1) + '" ' + (page >= totalPages ? 'disabled' : '') + ' title="Next">&rsaquo;</button>' +
            '<button type="button" class="btn btn-outline-secondary qfs-page-btn" data-list="' + listKey + '" data-page="' + totalPages + '" ' + (page >= totalPages ? 'disabled' : '') + ' title="Last">&raquo;</button>' +
            '</div></div>';
    }

    function qfsRenderFlightPage(containerSel, list, page, listKey, from, to, fromCity, toCity, selectedId) {
        var $wrap = $(containerSel);
        if (!$wrap.length) {
            return;
        }
        var filtered = qfsGetFilteredSortedList(list);
        var totalPages = Math.max(1, Math.ceil(filtered.length / QFS_PAGE_SIZE) || 1);
        page = Math.min(Math.max(1, page || 1), totalPages);
        if (listKey === 'onward') {
            qfsResultsState.onwardPage = page;
        } else if (listKey === 'return') {
            qfsResultsState.returnPage = page;
        }

        if (!list.length) {
            var emptyLabel = listKey === 'return'
                ? 'return '
                : (qfsResultsState.tType === 'ROUNDTRIP' ? 'onward ' : '');
            $wrap.html('<div class="alert alert-info mb-0">No ' + emptyLabel + 'flights found.</div>');
            return;
        }
        if (!filtered.length && String(qfsResultsState.searchText || '').trim()) {
            $wrap.html(
                qfsBuildPaginationHtml(listKey, 1, 0) +
                '<div class="alert alert-info mb-0">No flights match "<strong>' +
                $('<div>').text(String(qfsResultsState.searchText).trim()).html() +
                '</strong>". Try another airline, flight number or city.</div>'
            );
            return;
        }
        if (!filtered.length) {
            $wrap.html(
                qfsBuildPaginationHtml(listKey, 1, 0) +
                '<div class="alert alert-info mb-0">No flights with at least ' +
                (parseInt(qfsResultsState.requiredSeats, 10) || 1) +
                ' seat' + ((parseInt(qfsResultsState.requiredSeats, 10) || 1) === 1 ? '' : 's') +
                ' available match the selected filters.</div>'
            );
            return;
        }

        var start = (page - 1) * QFS_PAGE_SIZE;
        var pageItems = filtered.slice(start, start + QFS_PAGE_SIZE);
        var html = [];
        html.push(qfsBuildPaginationHtml(listKey, page, filtered.length));
        for (var i = 0; i < pageItems.length; i++) {
            var item = pageItems[i];
            html.push(createQfsFlightCard(item, from, to, fromCity, toCity, {
                qfsId: item._qfsId,
                selected: !!(selectedId && item._qfsId === selectedId)
            }));
        }
        if (filtered.length > QFS_PAGE_SIZE) {
            html.push(qfsBuildPaginationHtml(listKey, page, filtered.length));
        }
        $wrap.html(html.join(''));
    }

    function qfsRefreshVisibleResults() {
        var s = qfsResultsState;
        if (s.tType === 'ROUNDTRIP') {
            qfsRenderFlightPage('#qfsOnwardList', s.onward, s.onwardPage, 'onward', s.from, s.to, s.fromCity, s.toCity, qfsSelectedOnwardId);
            qfsRenderFlightPage('#qfsReturnList', s.returning, s.returnPage, 'return', s.to, s.from, s.toCity, s.fromCity, qfsSelectedReturnId);
        } else {
            qfsRenderFlightPage('#qfsOnewayList', s.onward, s.onwardPage, 'onward', s.from, s.to, s.fromCity, s.toCity, qfsSelectedOnwardId);
        }
        var modalBody = document.getElementById('qfsFlightsModalBody');
        if (modalBody) {
            var scrollParent = $('#qfsFlightsModal .modal-body')[0];
            if (scrollParent) {
                scrollParent.scrollTop = 0;
            }
        }
    }

    function qfsRenderSearchResults(opts) {
        opts = opts || {};
        var flights = opts.flights || [];
        var rFlights = opts.rFlights || [];
        var from = opts.from || '';
        var to = opts.to || '';
        var fromCity = opts.fromCity || from;
        var toCity = opts.toCity || to;
        var date = opts.date || '';
        var returnDate = opts.returnDate || '';
        var tType = opts.tType || 'ONEWAY';
        var adults = parseInt(opts.adults, 10);
        var children = parseInt(opts.children, 10);
        var infants = parseInt(opts.infants, 10);
        if (isNaN(adults) || adults < 1) {
            adults = 1;
        }
        if (isNaN(children) || children < 0) {
            children = 0;
        }
        if (isNaN(infants) || infants < 0) {
            infants = 0;
        }
        var nonStopOnly = !!opts.nonStopOnly;
        var paxLabel = adults + ' Adult' + (adults > 1 ? 's' : '');
        if (children > 0) {
            paxLabel += ', ' + children + ' Child' + (children > 1 ? 'ren' : '');
        }
        if (infants > 0) {
            paxLabel += ', ' + infants + ' Infant' + (infants > 1 ? 's' : '');
        }
        if (nonStopOnly) {
            paxLabel += ' · Non-stop';
        }
        // Require enough seats for all searched guests (adults + children + infants).
        var requiredSeats = Math.max(1, adults + children + infants);

        qfsSelectedOnward = null;
        qfsSelectedReturn = null;
        qfsSelectedOnwardId = '';
        qfsSelectedReturnId = '';

        qfsResultsState = {
            tType: tType,
            from: from,
            to: to,
            fromCity: fromCity,
            toCity: toCity,
            date: date,
            returnDate: returnDate,
            onward: [],
            returning: [],
            onwardPage: 1,
            returnPage: 1,
            stopFilter: nonStopOnly ? '0' : 'all',
            timeFilter: 'all',
            sortType: 'price',
            sortOrder: 'asc',
            nonStopOnly: nonStopOnly,
            requiredSeats: requiredSeats,
            adults: adults,
            children: children,
            infants: infants,
            searchText: ''
        };
        // Prepared after the state is set: guest-wise totals read the pax counts from it.
        qfsResultsState.onward = qfsPrepareFlightList(flights, 'onward');
        qfsResultsState.returning = qfsPrepareFlightList(rFlights, 'return');

        var modalBody = $('#qfsFlightsModalBody');
        modalBody.empty();

        var stopAllCls = nonStopOnly ? 'btn btn-sm btn-outline-secondary qfs-flight-filter-btn' : 'btn btn-sm btn-secondary active qfs-flight-filter-btn';
        var stopZeroCls = nonStopOnly ? 'btn btn-sm btn-secondary active qfs-flight-filter-btn' : 'btn btn-sm btn-outline-secondary qfs-flight-filter-btn';

        var sortHtml = '<div class="d-flex justify-content-between align-items-center flex-wrap w-100 mt-2 pb-2" style="font-size: 14px; border-bottom: 1px solid #eee; gap: .5rem;">' +
            '<div class="qfs-results-search">' +
            '<i class="fa fa-search"></i>' +
            '<input type="search" class="form-control" id="qfsResultsSearch" placeholder="Search airline, flight no., city or airport" autocomplete="off" spellcheck="false" aria-label="Search flights">' +
            '<button type="button" class="qfs-search-clear d-none" id="qfsResultsSearchClear" title="Clear search" aria-label="Clear search">&times;</button>' +
            '</div>' +
            '<div class="d-flex align-items-center">' +
            '<label class="mb-0 mr-2" style="font-weight: 600; color: #555; font-size: 13px;"><i class="fa fa-sort-amount-desc"></i> Sort By:</label>' +
            '<div class="btn-group" role="group">' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-sort-btn" data-sort="price" data-order="asc">Price <i class="fa fa-sort"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-sort-btn" data-sort="duration" data-order="asc">Duration <i class="fa fa-sort"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-sort-btn" data-sort="dtime" data-order="asc">Departure <i class="fa fa-sort"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-sort-btn" data-sort="atime" data-order="asc">Arrival <i class="fa fa-sort"></i></button>' +
            '</div></div></div>' +
            '<div class="w-100 pt-2 pb-2 mb-2" style="font-size: 13px; border-bottom: 1px solid #ddd;">' +
            '<div class="row m-0"><div class="col-md-5 p-0 d-flex align-items-center">' +
            '<label class="mb-0 mr-2" style="font-weight: 600; color: #555;"><i class="fa fa-filter"></i> Stops:</label>' +
            '<div class="btn-group qfs-filter-group-stops" role="group">' +
            '<button type="button" class="' + stopAllCls + '" data-filter="stops" data-value="all">All</button>' +
            '<button type="button" class="' + stopZeroCls + '" data-filter="stops" data-value="0">Non-Stop</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-filter-btn" data-filter="stops" data-value="1">1 Stop</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-filter-btn" data-filter="stops" data-value="2">2+ Stops</button>' +
            '</div></div><div class="col-md-7 p-0 d-flex align-items-center justify-content-end">' +
            '<label class="mb-0 mr-2" style="font-weight: 600; color: #555;"><i class="fa fa-clock-o"></i> Time:</label>' +
            '<div class="btn-group qfs-filter-group-time" role="group">' +
            '<button type="button" class="btn btn-sm btn-secondary active qfs-flight-filter-btn" data-filter="time" data-value="all">All</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-filter-btn" data-filter="time" data-value="morning">Morning</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-filter-btn" data-filter="time" data-value="afternoon">Afternoon</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-filter-btn" data-filter="time" data-value="evening">Evening</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-filter-btn" data-filter="time" data-value="night">Night</button>' +
            '</div></div></div></div>';

        if (tType === 'ROUNDTRIP') {
            $('#qfsFlightsModal .modal-dialog').css('max-width', '1200px');
            var rightTitle = to + ' - ' + from + " <span style='color:#ccc'>|</span> " + moment(returnDate).format('DD/MM/YYYY');
            var leftTitle = from + ' - ' + to + " <span style='color:#ccc'>|</span> " + moment(date).format('DD/MM/YYYY');
            $('#qfsFlightsModalTitle').html('<div class="row w-100 m-0"><div class="col-md-6 text-center">' + leftTitle + '</div><div class="col-md-6 text-center" style="border-left:1px solid #ccc;">' + rightTitle + '</div></div><div class="w-100 text-center mt-1" style="font-size:12px;color:#64748b;">' + paxLabel + '</div>' + sortHtml);
            modalBody.html(
                '<div class="row mt-2">' +
                '<div class="col-md-6" id="qfsOnwardCol"><div id="qfsOnwardList"></div></div>' +
                '<div class="col-md-6" id="qfsReturnCol"><div id="qfsReturnList"></div></div>' +
                '</div>'
            );
        } else {
            $('#qfsFlightsModal .modal-dialog').css('max-width', '900px');
            $('#qfsFlightsModalTitle').html('<div class="w-100 text-center">' + from + ' - ' + to + " <span style='color:#ccc'>|</span> " + moment(date).format('DD/MM/YYYY') + " <span style='color:#ccc'>|</span> " + paxLabel + '</div>' + sortHtml);
            modalBody.html('<div id="qfsOnewayList"></div>');
        }

        $('#qfsFlightsModal').modal('show');
        // Render first page after modal starts opening so UI feels responsive.
        setTimeout(function () {
            qfsRefreshVisibleResults();
        }, 0);
    }

    function qfsTodayYmd() {
        var d = new Date();
        var yyyy = d.getFullYear();
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var dd = String(d.getDate()).padStart(2, '0');
        return yyyy + '-' + mm + '-' + dd;
    }

    function qfsTomorrowYmd() {
        var d = new Date();
        d.setDate(d.getDate() + 1);
        var yyyy = d.getFullYear();
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var dd = String(d.getDate()).padStart(2, '0');
        return yyyy + '-' + mm + '-' + dd;
    }

    function qfsGetAirportCode($input, fallback) {
        var code = ($input.attr('data-code') || $input.val() || fallback || '').toString().trim().toUpperCase();
        if (code.indexOf('(') !== -1) {
            var match = code.match(/\(([A-Z0-9]{3})\)/);
            if (match) {
                code = match[1];
            }
        }
        return code.substring(0, 3);
    }

    function qfsBuildViaSearchPayload(sectorInfos, isDomestic, adults, children, infants) {
        var adt = parseInt(adults, 10);
        var chd = parseInt(children, 10);
        var inf = parseInt(infants, 10);
        if (isNaN(adt) || adt < 1) {
            adt = 1;
        }
        if (isNaN(chd) || chd < 0) {
            chd = 0;
        }
        if (isNaN(inf) || inf < 0) {
            inf = 0;
        }
        return {
            sectorInfos: sectorInfos,
            prefAirlines: [{ code: 'ALL', name: 'ALL' }],
            class: 'ALL',
            paxCount: { adt: adt, chd: chd, inf: inf },
            route: 'ALL',
            disc: false,
            multiHop: false,
            multiCity: false,
            senior: false,
            special: false,
            domestic: !!isDomestic,
            isOfflineSearch: false,
            isPaxWiseCommission: false
        };
    }

    function qfsParseViaSearchResponse(res) {
        if (typeof res === 'string') {
            try { res = JSON.parse(res); } catch (err) { res = {}; }
        }
        var flights = [];
        var rFlights = [];
        var source = (res && res.data) ? res.data : (res || {});

        if (source.onwardJourneys && source.onwardJourneys.length) {
            source.onwardJourneys.forEach(function (journey) {
                flights.push({
                    journey: journey,
                    primaryFlight: (journey.flights && journey.flights.length) ? journey.flights[0] : {}
                });
            });
        }
        if (source.returnJourneys && source.returnJourneys.length) {
            source.returnJourneys.forEach(function (journey) {
                rFlights.push({
                    journey: journey,
                    primaryFlight: (journey.flights && journey.flights.length) ? journey.flights[0] : {}
                });
            });
        } else if (source.combinedJourneys && source.combinedJourneys.length) {
            source.combinedJourneys.forEach(function (journey) {
                flights.push({
                    journey: journey,
                    primaryFlight: (journey.flights && journey.flights.length) ? journey.flights[0] : {}
                });
            });
        } else if (res && res.flights) {
            flights = res.flights;
        }

        return { flights: flights, rFlights: rFlights };
    }

    function qfsReadGuestCounts() {
        var adults = parseInt($('#q_adults').val(), 10);
        var children = parseInt($('#q_children').val(), 10);
        var infants = parseInt($('#q_infants').val(), 10);
        if (isNaN(adults) || adults < 1) {
            adults = 1;
        }
        if (isNaN(children) || children < 0) {
            children = 0;
        }
        if (isNaN(infants) || infants < 0) {
            infants = 0;
        }
        return { adults: adults, children: children, infants: infants };
    }

    function qfsSyncPaxFromQuotation() {
        var counts = qfsReadGuestCounts();
        $('#qfsAdults').val(counts.adults);
        $('#qfsChildren').val(counts.children);
        $('#qfsInfants').val(counts.infants);
    }

    function qfsSyncPaxToQuotation() {
        var adults = parseInt($('#qfsAdults').val(), 10);
        var children = parseInt($('#qfsChildren').val(), 10);
        var infants = parseInt($('#qfsInfants').val(), 10);
        if (isNaN(adults) || adults < 1) {
            adults = 1;
        }
        if (isNaN(children) || children < 0) {
            children = 0;
        }
        if (isNaN(infants) || infants < 0) {
            infants = 0;
        }
        $('#qfsAdults').val(adults);
        $('#qfsChildren').val(children);
        $('#qfsInfants').val(infants);
        if ($('#q_adults').prop('readonly')) {
            return { adults: adults, children: children, infants: infants };
        }
        if ($('#q_adults').length) {
            $('#q_adults').val(adults).trigger('change');
        }
        if ($('#q_children').length) {
            $('#q_children').val(children).trigger('change');
        }
        if ($('#q_infants').length) {
            $('#q_infants').val(infants).trigger('change');
        }
        return { adults: adults, children: children, infants: infants };
    }

    function qfsApplyAirportToInput($input, city, code) {
        var $input = $($input);
        if (!$input.length) {
            return;
        }
        city = String(city || '').trim();
        code = String(code || '').trim().toUpperCase();
        if (!city && !code) {
            return;
        }
        if (code) {
            $input
                .val(city ? (city + ' (' + code + ')') : code)
                .attr('data-code', code)
                .attr('data-city', city || code);
        } else {
            $input.val(city).attr('data-city', city).removeAttr('data-code');
        }
        if ($input.is('#qfsApiFrom, #qfsApiTo')) {
            qfsSyncReturnRoute();
        }
    }

    function qfsCopyAirportField($src, $dest) {
        if (!$src.length || !$dest.length) {
            return;
        }
        $dest.val($src.val() || '');
        var code = String($src.attr('data-code') || '');
        var city = String($src.attr('data-city') || '');
        if (code) {
            $dest.attr('data-code', code);
        } else {
            $dest.removeAttr('data-code');
        }
        if (city) {
            $dest.attr('data-city', city);
        } else {
            $dest.removeAttr('data-city');
        }
    }

    /** Return From/To always mirror the opposite of the onward route. */
    function qfsSyncReturnRoute() {
        qfsCopyAirportField($('#qfsApiTo'), $('#qfsReturnFrom'));
        qfsCopyAirportField($('#qfsApiFrom'), $('#qfsReturnTo'));
    }

    function qfsResolveAirportByCity(city, done) {
        city = String(city || '').trim();
        if (!city) {
            if (typeof done === 'function') {
                done(null);
            }
            return;
        }
        $.ajax({
            url: 'ajax/mmt_autosuggest.php',
            type: 'GET',
            dataType: 'json',
            data: { q: city },
            success: function (res) {
                var items = res && res.r ? res.r : (Array.isArray(res) ? res : []);
                if (!Array.isArray(items)) {
                    items = [items];
                }
                var cityLower = city.toLowerCase();
                var match = null;
                for (var i = 0; i < items.length; i++) {
                    var item = items[i] || {};
                    var code = String(item.iata || '').trim().toUpperCase();
                    var itemCity = String(item.ct || item.cName || '').trim();
                    if (!code || !itemCity) {
                        continue;
                    }
                    if (itemCity.toLowerCase() === cityLower) {
                        match = { city: itemCity, code: code };
                        break;
                    }
                    if (!match && itemCity.toLowerCase().indexOf(cityLower) === 0) {
                        match = { city: itemCity, code: code };
                    }
                }
                if (!match) {
                    for (var j = 0; j < items.length; j++) {
                        var it = items[j] || {};
                        var c = String(it.iata || '').trim().toUpperCase();
                        var ct = String(it.ct || it.cName || '').trim();
                        if (c && ct) {
                            match = { city: ct, code: c };
                            break;
                        }
                    }
                }
                if (typeof done === 'function') {
                    done(match);
                }
            },
            error: function () {
                if (typeof done === 'function') {
                    done(null);
                }
            }
        });
    }

    function qfsReadDeparturePrefill() {
        var p = (typeof window.QUOTATION_PREFILL === 'object' && window.QUOTATION_PREFILL) ? window.QUOTATION_PREFILL : {};
        var city = String(p.departure_city || p.tp_departure || '').trim();
        var code = String(p.departure_airport_code || p.tp_departure_code || '').trim().toUpperCase();
        return { city: city, code: code };
    }

    function qfsReadDestinationPrefill() {
        var liveDest = String($('[name=destination]').val() || $('#qDestinationInput').val() || '').trim();
        var p = (typeof window.QUOTATION_PREFILL === 'object' && window.QUOTATION_PREFILL) ? window.QUOTATION_PREFILL : {};
        var raw = liveDest || String(p.destination || p.tp_arrival || '').trim();
        if (!raw || raw === '—') {
            return { city: '', code: '' };
        }
        // Prefer the first destination when multiple are listed (e.g. "Goa, Manali").
        var first = raw.split(/[,;|]+/)[0] || raw;
        first = String(first).replace(/\s*-\s*\d+\s*N\b/i, '').trim();
        return { city: first, code: '' };
    }

    function qfsPrefillAirportField($input, city, code) {
        if (!$input || !$input.length || (!city && !code)) {
            return;
        }
        // Keep a user-edited value if already set with a valid airport code.
        if ($input.val().trim() && ($input.attr('data-code') || '').trim().length === 3) {
            return;
        }
        if (code) {
            qfsApplyAirportToInput($input, city, code);
            return;
        }
        qfsResolveAirportByCity(city, function (match) {
            if (match && match.code) {
                qfsApplyAirportToInput($input, match.city || city, match.code);
            } else {
                qfsApplyAirportToInput($input, city, '');
            }
        });
    }

    function qfsPrefillRouteAirports() {
        var dep = qfsReadDeparturePrefill();
        var dest = qfsReadDestinationPrefill();
        qfsPrefillAirportField($('#qfsApiFrom'), dep.city, dep.code);
        qfsPrefillAirportField($('#qfsApiTo'), dest.city, dest.code);
    }

    /** Tentative Date from Guest & Tour (dd/mm/yyyy) as YYYY-MM-DD, or '' when empty/invalid. */
    function qfsReadTentativeDateYmd() {
        var raw = String($('#q_tentative_date').val() || '').trim();
        if (!raw) {
            return '';
        }
        var m = moment(raw, ['DD/MM/YYYY', 'D/M/YYYY', 'DD-MM-YYYY', 'YYYY-MM-DD'], true);
        return m.isValid() ? m.format('YYYY-MM-DD') : '';
    }

    function prefillQfsSearchFromQuotation() {
        var today = qfsTodayYmd();
        var tomorrow = qfsTomorrowYmd();
        var tentative = qfsReadTentativeDateYmd();
        var onward = (tentative && tentative >= today) ? tentative : today;
        qfsSetDateMin('qfsApiDate', today);
        qfsSetDateField('qfsApiDate', onward);
        var ret = String($('#qfsApiReturnDate').val() || '');
        qfsSetDateMin('qfsApiReturnDate', onward);
        if (!ret || ret < onward) {
            qfsSetDateField('qfsApiReturnDate', onward === today ? tomorrow : onward);
        }
        qfsSyncPaxFromQuotation();
        qfsPrefillRouteAirports();
        qfsAutoSetReturnDate();
    }

    /** Round trip: return date = onward date + No. of Nights from Guest & Tour details. */
    function qfsAutoSetReturnDate() {
        if ($('input[name="qfs_tripType"]:checked').val() !== 'roundtrip') {
            return;
        }
        var onward = String($('#qfsApiDate').val() || '').trim();
        if (!onward || !moment(onward, 'YYYY-MM-DD', true).isValid()) {
            return;
        }
        var nights = parseInt($('#q_nights').val(), 10);
        if (isNaN(nights) || nights < 0) {
            nights = 0;
        }
        qfsSetDateMin('qfsApiReturnDate', onward);
        qfsSetDateField('qfsApiReturnDate', moment(onward, 'YYYY-MM-DD').add(nights, 'days').format('YYYY-MM-DD'));
        $('#qfsReturnDateHint').text('Onward date + ' + nights + ' night' + (nights === 1 ? '' : 's') + ' (from tour details)');
    }

    $(function () {
        initQfsAirportAutosuggest('qfsApiFrom', 'qfsApiFromSuggest');
        initQfsAirportAutosuggest('qfsApiTo', 'qfsApiToSuggest');

        $('#qfsSwapAirports').on('click', function () {
            var $from = $('#qfsApiFrom');
            var $to = $('#qfsApiTo');
            var fromVal = $from.val();
            var toVal = $to.val();
            var fromCode = $from.attr('data-code') || '';
            var toCode = $to.attr('data-code') || '';
            var fromCity = $from.attr('data-city') || '';
            var toCity = $to.attr('data-city') || '';

            $from.val(toVal);
            $to.val(fromVal);

            if (toCode) {
                $from.attr('data-code', toCode);
            } else {
                $from.removeAttr('data-code');
            }
            if (fromCode) {
                $to.attr('data-code', fromCode);
            } else {
                $to.removeAttr('data-code');
            }

            if (toCity) {
                $from.attr('data-city', toCity);
            } else {
                $from.removeAttr('data-city');
            }
            if (fromCity) {
                $to.attr('data-city', fromCity);
            } else {
                $to.removeAttr('data-city');
            }

            $('#qfsApiFromSuggest, #qfsApiToSuggest').hide().empty();
            qfsSyncReturnRoute();
        });

        $('#qfsApiFrom, #qfsApiTo').on('input change', qfsSyncReturnRoute);

        $('#qSearchFlight').on('click', function () {
            prefillQfsSearchFromQuotation();
            $('#qfsSearchModal').modal('show');
        });

        $('input[name="qfs_tripType"]').on('change', function () {
            if ($(this).val() === 'roundtrip') {
                qfsSyncReturnRoute();
                $('#qfsReturnRouteRow').show();
                qfsAutoSetReturnDate();
            } else {
                $('#qfsReturnRouteRow').hide();
            }
        });

        qfsInitDateField('qfsApiDate');
        qfsInitDateField('qfsApiReturnDate');

        $('#qfsApiDate').on('change', function () {
            var onwardDate = $(this).val();
            if (onwardDate) {
                qfsSetDateMin('qfsApiReturnDate', onwardDate);
                qfsAutoSetReturnDate();
            }
        });

        $('#qfsApiReturnDate').on('change', function (e, source) {
            if (source === 'user') {
                $('#qfsReturnDateHint').text('Custom return date');
            }
        });

        $('#qfsSearchFlightsBtn').on('click', function (e) {
            e.preventDefault();

            var fromVal = $('#qfsApiFrom').val().trim();
            var toVal = $('#qfsApiTo').val().trim();
            var date = $('#qfsApiDate').val().trim();
            var isInternational = $('input[name="qfs_flightType"]:checked').val() === 'international';
            var from = qfsGetAirportCode($('#qfsApiFrom'), '');
            var to = qfsGetAirportCode($('#qfsApiTo'), '');
            var fromCity = ($('#qfsApiFrom').attr('data-city') || fromVal).split(',')[0].replace(/\(.*?\)/g, '').trim();
            var toCity = ($('#qfsApiTo').attr('data-city') || toVal).split(',')[0].replace(/\(.*?\)/g, '').trim();
            var paxCounts = qfsSyncPaxToQuotation();
            var adults = paxCounts.adults;
            var children = paxCounts.children;
            var infants = paxCounts.infants;
            var nonStopOnly = $('#qfsNonStop').is(':checked');
            var tType = $('input[name="qfs_tripType"]:checked').val() === 'roundtrip' ? 'ROUNDTRIP' : 'ONEWAY';
            var returnDate = $('#qfsApiReturnDate').val().trim();

            if (!from || !to || from.length !== 3 || to.length !== 3 || !date || (tType === 'ROUNDTRIP' && !returnDate)) {
                alert('Please fill all required search fields and select airports from the suggestions');
                return;
            }

            qfsSearchContext = {
                from: from,
                to: to,
                date: date,
                returnDate: returnDate,
                adults: adults,
                children: children,
                infants: infants,
                nonStopOnly: nonStopOnly
            };
            qfsSelectedOnward = null;
            qfsSelectedReturn = null;
            qfsSelectedOnwardId = '';
            qfsSelectedReturnId = '';

            var sectorInfos = [{
                src: { code: from, name: fromCity || from, city: fromCity || from },
                dest: { code: to, name: toCity || to, city: toCity || to },
                date: moment(date).format('YYYY-MM-DD'),
                debug: false
            }];
            if (tType === 'ROUNDTRIP') {
                sectorInfos.push({
                    src: { code: to, name: toCity || to, city: toCity || to },
                    dest: { code: from, name: fromCity || from, city: fromCity || from },
                    date: moment(returnDate).format('YYYY-MM-DD'),
                    debug: false
                });
            }

            var isDomesticSearch = !isInternational;
            var useDualIntlRoundTrip = tType === 'ROUNDTRIP' && isInternational;
            var $btn = $('#qfsSearchFlightsBtn').text('Searching...').prop('disabled', true);

            function handleQfsSearchSuccess(res, preParsed) {
                $btn.text('Search').prop('disabled', false);
                var parsed = preParsed || qfsParseViaSearchResponse(res);
                qfsRenderSearchResults({
                    flights: parsed.flights || [],
                    rFlights: parsed.rFlights || [],
                    from: from,
                    to: to,
                    fromCity: fromCity,
                    toCity: toCity,
                    date: date,
                    returnDate: returnDate,
                    tType: tType,
                    adults: adults,
                    children: children,
                    infants: infants,
                    nonStopOnly: nonStopOnly
                });
            }

            function handleQfsSearchError(err) {
                $btn.text('Search').prop('disabled', false);
                $('#qfsFlightsModalTitle').text(from + ' - ' + to + ' | ' + date);
                var errMsg = (err.responseJSON && err.responseJSON.err && err.responseJSON.err.title)
                    ? err.responseJSON.err.title
                    : 'Could not connect to flight search. Please try again.';
                $('#qfsFlightsModalBody').html('<div class="alert alert-danger" role="alert"><h5 class="alert-heading"><i class="fa fa-exclamation-triangle"></i> Search Failed</h5><p style="margin-bottom:0; font-size:13px;">' + errMsg + '</p></div>');
                $('#qfsFlightsModal').modal('show');
            }

            if (useDualIntlRoundTrip) {
                var onwardPayload = qfsBuildViaSearchPayload([sectorInfos[0]], false, adults, children, infants);
                var returnPayload = qfsBuildViaSearchPayload([sectorInfos[1]], false, adults, children, infants);

                $.when(
                    $.ajax({
                        url: 'ajax/via_search.php',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify(onwardPayload)
                    }),
                    $.ajax({
                        url: 'ajax/via_search.php',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify(returnPayload)
                    })
                ).done(function (onwardRes, returnRes) {
                    var onwardParsed = qfsParseViaSearchResponse(onwardRes[0]);
                    var returnParsed = qfsParseViaSearchResponse(returnRes[0]);
                    handleQfsSearchSuccess(null, {
                        flights: onwardParsed.flights,
                        rFlights: returnParsed.flights
                    });
                }).fail(function (err) {
                    handleQfsSearchError(err);
                });
                return;
            }

            $.ajax({
                url: 'ajax/via_search.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(qfsBuildViaSearchPayload(sectorInfos, isDomesticSearch, adults, children, infants)),
                success: function (res) {
                    handleQfsSearchSuccess(res);
                },
                error: handleQfsSearchError
            });
        });

        $(document).on('click', '#qfsFlightsModal .qfs-select-flight-card', function () {
            var f = JSON.parse(decodeURIComponent($(this).attr('data-flight')));
            var qfsId = String($(this).attr('data-qfs-id') || '');
            var isReturn = $(this).closest('#qfsReturnCol').length > 0;
            var isRoundTrip = $('input[name="qfs_tripType"]:checked').val() === 'roundtrip';

            if (isRoundTrip) {
                if (isReturn) {
                    qfsSelectedReturn = f;
                    qfsSelectedReturnId = qfsId;
                } else {
                    qfsSelectedOnward = f;
                    qfsSelectedOnwardId = qfsId;
                }
                $(this).closest('.col-md-6').find('.qfs-select-flight-card').css('border', '1px solid #e9ecef');
                $(this).css('border', '2px solid #28a745');

                if (qfsSelectedOnward && qfsSelectedReturn) {
                    addFlightRowsToQuotation([qfsSelectedOnward, qfsSelectedReturn], [qfsSearchContext.date, qfsSearchContext.returnDate]);
                    $('#qfsFlightsModal').modal('hide');
                    $('#qfsSearchModal').modal('hide');
                }
            } else {
                addFlightRowsToQuotation([f], [qfsSearchContext.date]);
                $('#qfsFlightsModal').modal('hide');
                $('#qfsSearchModal').modal('hide');
            }
        });

        var qfsSearchTimer = null;
        $(document).on('input', '#qfsResultsSearch', function () {
            var val = String($(this).val() || '');
            $('#qfsResultsSearchClear').toggleClass('d-none', val === '');
            if (qfsSearchTimer) {
                clearTimeout(qfsSearchTimer);
            }
            qfsSearchTimer = setTimeout(function () {
                qfsSearchTimer = null;
                qfsResultsState.searchText = val;
                qfsResultsState.onwardPage = 1;
                qfsResultsState.returnPage = 1;
                qfsRefreshVisibleResults();
            }, 200);
        });

        $(document).on('keydown', '#qfsResultsSearch', function (e) {
            if (e.key === 'Escape' && $(this).val()) {
                e.preventDefault();
                e.stopPropagation();
                $('#qfsResultsSearchClear').trigger('click');
            }
        });

        $(document).on('click', '#qfsResultsSearchClear', function () {
            $('#qfsResultsSearch').val('').trigger('input').trigger('focus');
        });

        $(document).on('click', '#qfsFlightsModal .qfs-flight-sort-btn', function () {
            var $btn = $(this);
            var sortType = $btn.attr('data-sort');
            var newOrder = ($btn.hasClass('active') && $btn.attr('data-order') === 'asc') ? 'desc' : 'asc';
            $('#qfsFlightsModal .qfs-flight-sort-btn').attr('data-order', 'asc').removeClass('active btn-secondary').addClass('btn-outline-secondary').find('i').attr('class', 'fa fa-sort');
            $btn.attr('data-order', newOrder).removeClass('btn-outline-secondary').addClass('active btn-secondary');
            $btn.find('i').attr('class', newOrder === 'asc' ? 'fa fa-sort-amount-asc' : 'fa fa-sort-amount-desc');
            qfsResultsState.sortType = sortType;
            qfsResultsState.sortOrder = newOrder;
            qfsResultsState.onwardPage = 1;
            qfsResultsState.returnPage = 1;
            qfsRefreshVisibleResults();
        });

        $(document).on('click', '#qfsFlightsModal .qfs-flight-filter-btn', function () {
            var $btn = $(this);
            var filterType = String($btn.attr('data-filter') || '');
            var $group = filterType === 'time'
                ? $btn.closest('.qfs-filter-group-time')
                : $btn.closest('.qfs-filter-group-stops');
            if (!$group.length) {
                $group = $btn.parent();
            }
            $group.find('.qfs-flight-filter-btn')
                .removeClass('active btn-secondary')
                .addClass('btn-outline-secondary');
            $btn.removeClass('btn-outline-secondary').addClass('active btn-secondary');

            if (filterType === 'time') {
                qfsResultsState.timeFilter = String($btn.attr('data-value') || 'all');
            } else {
                qfsResultsState.stopFilter = String($btn.attr('data-value') || 'all');
            }
            qfsResultsState.onwardPage = 1;
            qfsResultsState.returnPage = 1;
            qfsRefreshVisibleResults();
        });

        $(document).on('click', '#qfsFlightsModal .qfs-page-btn', function () {
            var $btn = $(this);
            if ($btn.prop('disabled')) {
                return;
            }
            var listKey = String($btn.attr('data-list') || 'onward');
            var page = parseInt($btn.attr('data-page'), 10) || 1;
            if (listKey === 'return') {
                qfsResultsState.returnPage = page;
            } else {
                qfsResultsState.onwardPage = page;
            }
            qfsRefreshVisibleResults();
        });
    });
})(jQuery);
