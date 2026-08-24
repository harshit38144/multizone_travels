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
        var input = document.getElementById(id);
        if (input && input.showPicker) {
            input.showPicker();
        } else if (input) {
            input.focus();
        }
    };

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
                            var fullStr = city + ', ' + country + ' (' + code + ')';
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

    function segmentLocation(detail, fallbackCity, fallbackCode) {
        if (detail && (detail.name || detail.code)) {
            return (detail.name || fallbackCity || '') + (detail.code ? ' (' + detail.code + ')' : '');
        }
        if (fallbackCity && fallbackCode) {
            return fallbackCity + ' (' + fallbackCode + ')';
        }
        return fallbackCity || fallbackCode || '';
    }

    function mapSegmentToQuotationRow(seg, fare) {
        var airlineObj = seg.carrier || seg.airline || {};
        var fname = airlineObj.name || seg.airlineName || seg.carrierName || '';
        var fno = (airlineObj.code || 'FL') + '-' + (seg.flightNo || seg.flightNumber || '');
        var dRaw = (seg.depDetail && seg.depDetail.time) ? seg.depDetail.time : null;
        var aRaw = (seg.arrDetail && seg.arrDetail.time) ? seg.arrDetail.time : null;
        var depDate = '';
        var depTime = '';
        var arrDate = '';
        var arrTime = '';

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
            fare: fare || ''
        };
    }

    function mapJourneyToQuotationRows(f, legDate) {
        var segments = (f.segments && f.segments.length) ? f.segments : null;
        if (!segments || segments.length <= 1) {
            return [mapFlightToQuotationRow(f, legDate)];
        }

        var rows = [];
        segments.forEach(function (seg, idx) {
            var row = mapSegmentToQuotationRow(seg, idx === 0 ? (f.tot || '') : '');
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

        var faresObj = journey.fares || {};
        var paxFares = (faresObj.paxFares && faresObj.paxFares.adt) ? faresObj.paxFares.adt : {};
        var totObj = paxFares.total || faresObj.totalFare || primaryF.fareDetails || {};
        var baseObj = paxFares.base || {};
        var taxObj = paxFares.tax || {};

        var base = Math.round(parseFloat(baseObj.amount || baseObj.baseFare || 3500) || 0);
        var tax = Math.round(parseFloat(taxObj.amount || taxObj.taxes || 500) || 0);
        var tot = item._qfsMeta
            ? item._qfsMeta.price
            : Math.round(parseFloat(totObj.amount || totObj.total || (base + tax)) || 0);

        var dTimeRaw = (primaryF.depDetail && primaryF.depDetail.time) ? primaryF.depDetail.time : (primaryF.departureTime || primaryF.depTime || '08:00 AM');
        var aTimeRaw = (primaryF.arrDetail && primaryF.arrDetail.time) ? primaryF.arrDetail.time : (primaryF.arrivalTime || primaryF.arrTime || '10:30 AM');
        var dTime = moment(dTimeRaw).isValid() ? moment(dTimeRaw).format('DD MMM YYYY | hh:mm A') : dTimeRaw;
        var aTime = moment(aTimeRaw).isValid() ? moment(aTimeRaw).format('DD MMM YYYY | hh:mm A') : aTimeRaw;

        var fData = {
            fname: pFname, fno: pFno, dTime: dTime, aTime: aTime,
            base: base, tax: tax, tot: tot,
            terminal: (primaryF.depDetail && primaryF.depDetail.terminal) ? primaryF.depDetail.terminal : 'T1',
            stops: stopsStr, duration: pDuration,
            baggage: primaryF.baggage || 'Cabin: 7kg | Check-in: 15kg',
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

        var cardHtml = '<div class="card mb-2 qfs-select-flight-card" data-qfs-id="' + qfsId + '" data-flight="' + encodedData + '" data-duration="' + pDurationRaw + '" data-dtime="' + dTimeRawUnix + '" data-atime="' + aTimeRawUnix + '" data-stops="' + stops + '" data-airline="' + pFname + '" data-price="' + tot + '" style="border: ' + selectedBorder + '; box-shadow: none; border-radius:4px; cursor:pointer; background-color: #f8f9fa;">' +
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

        var priceLabel = (typeof tot === 'number' && !isNaN(tot))
            ? Math.round(tot).toLocaleString('en-IN', { maximumFractionDigits: 0 })
            : String(Math.round(parseFloat(tot) || 0));
        cardHtml += '</div>' +
            '<div class="qfs-price-col">' +
            '<div class="qfs-price-value">₹' + priceLabel + '</div>' +
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

        return {
            from: from,
            to: to,
            name: f.fname || '',
            fl_tr_no: flNo,
            dep_date: depDate,
            dep_time: depTime,
            arr_date: arrDate,
            arr_time: arrTime,
            fare: f.tot || ''
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

    function qfsPrepareFlightList(list, prefix) {
        return (list || []).map(function (item, index) {
            var journey = item.journey || item;
            var flightsArray = (journey.flights && journey.flights.length > 0) ? journey.flights : [item.primaryFlight || item];
            var primaryF = item.primaryFlight || flightsArray[0] || item;
            var faresObj = journey.fares || {};
            var paxFares = (faresObj.paxFares && faresObj.paxFares.adt) ? faresObj.paxFares.adt : {};
            var totObj = paxFares.total || faresObj.totalFare || primaryF.fareDetails || {};
            var baseObj = paxFares.base || {};
            var taxObj = paxFares.tax || {};
            var base = Math.round(parseFloat(baseObj.amount || baseObj.baseFare || 3500) || 0);
            var tax = Math.round(parseFloat(taxObj.amount || taxObj.taxes || 500) || 0);
            var price = Math.round(parseFloat(totObj.amount || totObj.total || (base + tax)) || 0);
            var dTimeRaw = (primaryF.depDetail && primaryF.depDetail.time) ? primaryF.depDetail.time : (primaryF.departureTime || primaryF.depTime || '');
            var aTimeRaw = (primaryF.arrDetail && primaryF.arrDetail.time) ? primaryF.arrDetail.time : (primaryF.arrivalTime || primaryF.arrTime || '');
            var duration = primaryF.flyTime || primaryF.duration || 150;
            if (typeof duration !== 'number') {
                duration = parseInt(duration, 10) || 150;
            }
            item._qfsId = prefix + '-' + index;
            item._qfsMeta = {
                price: price,
                duration: duration,
                dtime: moment(dTimeRaw).isValid() ? moment(dTimeRaw).valueOf() : 0,
                atime: moment(aTimeRaw).isValid() ? moment(aTimeRaw).valueOf() : 0,
                stops: flightsArray.length > 1 ? (flightsArray.length - 1) : 0
            };
            return item;
        });
    }

    function qfsFlightPassesFilters(item) {
        var meta = item._qfsMeta || {};
        var stopFilter = qfsResultsState.stopFilter;
        var timeFilter = qfsResultsState.timeFilter;

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
        if (!filtered.length) {
            $wrap.html(
                qfsBuildPaginationHtml(listKey, 1, 0) +
                '<div class="alert alert-info mb-0">No flights match the selected filters.</div>'
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
            onward: qfsPrepareFlightList(flights, 'onward'),
            returning: qfsPrepareFlightList(rFlights, 'return'),
            onwardPage: 1,
            returnPage: 1,
            stopFilter: 'all',
            timeFilter: 'all',
            sortType: 'price',
            sortOrder: 'asc'
        };

        var modalBody = $('#qfsFlightsModalBody');
        modalBody.empty();

        var sortHtml = '<div class="d-flex justify-content-end align-items-center w-100 mt-2 pb-2" style="font-size: 14px; border-bottom: 1px solid #eee;">' +
            '<label class="mb-0 mr-2" style="font-weight: 600; color: #555; font-size: 13px;"><i class="fa fa-sort-amount-desc"></i> Sort By:</label>' +
            '<div class="btn-group" role="group">' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-sort-btn" data-sort="price" data-order="asc">Price <i class="fa fa-sort"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-sort-btn" data-sort="duration" data-order="asc">Duration <i class="fa fa-sort"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-sort-btn" data-sort="dtime" data-order="asc">Departure <i class="fa fa-sort"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-sort-btn" data-sort="atime" data-order="asc">Arrival <i class="fa fa-sort"></i></button>' +
            '</div></div>' +
            '<div class="w-100 pt-2 pb-2 mb-2" style="font-size: 13px; border-bottom: 1px solid #ddd;">' +
            '<div class="row m-0"><div class="col-md-5 p-0 d-flex align-items-center">' +
            '<label class="mb-0 mr-2" style="font-weight: 600; color: #555;"><i class="fa fa-filter"></i> Stops:</label>' +
            '<div class="btn-group qfs-filter-group-stops" role="group">' +
            '<button type="button" class="btn btn-sm btn-secondary active qfs-flight-filter-btn" data-filter="stops" data-value="all">All</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary qfs-flight-filter-btn" data-filter="stops" data-value="0">Non-Stop</button>' +
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
            $('#qfsFlightsModalTitle').html('<div class="row w-100 m-0"><div class="col-md-6 text-center">' + leftTitle + '</div><div class="col-md-6 text-center" style="border-left:1px solid #ccc;">' + rightTitle + '</div></div>' + sortHtml);
            modalBody.html(
                '<div class="row mt-2">' +
                '<div class="col-md-6" id="qfsOnwardCol"><div id="qfsOnwardList"></div></div>' +
                '<div class="col-md-6" id="qfsReturnCol"><div id="qfsReturnList"></div></div>' +
                '</div>'
            );
        } else {
            $('#qfsFlightsModal .modal-dialog').css('max-width', '900px');
            $('#qfsFlightsModalTitle').html('<div class="w-100 text-center">' + from + ' - ' + to + " <span style='color:#ccc'>|</span> " + moment(date).format('DD/MM/YYYY') + '</div>' + sortHtml);
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

    function qfsBuildViaSearchPayload(sectorInfos, isDomestic, pax) {
        return {
            sectorInfos: sectorInfos,
            prefAirlines: [{ code: 'ALL', name: 'ALL' }],
            class: 'ALL',
            paxCount: { adt: parseInt(pax, 10) || 1, chd: 0, inf: 0 },
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

    function prefillQfsSearchFromQuotation() {
        var today = qfsTodayYmd();
        var tomorrow = qfsTomorrowYmd();
        $('#qfsApiDate').attr('min', today).val(today);
        $('#qfsApiReturnDate').attr('min', today);
        if (!$('#qfsApiReturnDate').val() || $('#qfsApiReturnDate').val() < today) {
            $('#qfsApiReturnDate').val(tomorrow);
        }
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
        });

        $('#qSearchFlight').on('click', function () {
            prefillQfsSearchFromQuotation();
            $('#qfsSearchModal').modal('show');
        });

        $('input[name="qfs_tripType"]').on('change', function () {
            if ($(this).val() === 'roundtrip') {
                $('#qfsReturnDateContainer').show();
            } else {
                $('#qfsReturnDateContainer').hide();
            }
        });

        $('#qfsApiDate').on('change', function () {
            var onwardDate = $(this).val();
            if (onwardDate) {
                $('#qfsApiReturnDate').attr('min', onwardDate);
                if ($('#qfsApiReturnDate').val() < onwardDate) {
                    $('#qfsApiReturnDate').val(onwardDate);
                }
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
            var pax = parseInt($('#q_adults').val(), 10) || 1;
            var tType = $('input[name="qfs_tripType"]:checked').val() === 'roundtrip' ? 'ROUNDTRIP' : 'ONEWAY';
            var returnDate = $('#qfsApiReturnDate').val().trim();

            if (!from || !to || from.length !== 3 || to.length !== 3 || !date || (tType === 'ROUNDTRIP' && !returnDate)) {
                alert('Please fill all required search fields and select airports from the suggestions');
                return;
            }

            qfsSearchContext = { from: from, to: to, date: date, returnDate: returnDate };
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
                    tType: tType
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
                var onwardPayload = qfsBuildViaSearchPayload([sectorInfos[0]], false, pax);
                var returnPayload = qfsBuildViaSearchPayload([sectorInfos[1]], false, pax);

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
                data: JSON.stringify(qfsBuildViaSearchPayload(sectorInfos, isDomesticSearch, pax)),
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
