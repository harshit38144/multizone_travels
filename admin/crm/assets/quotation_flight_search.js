/* Flight search for Quotation Generator (mirrors admin/etickets.php flow) */
(function ($) {
    'use strict';

    var qfsSelectedOnward = null;
    var qfsSelectedReturn = null;
    var qfsSearchContext = { from: '', to: '', date: '', returnDate: '' };

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

        inputEl.on('keyup', function () {
            var query = $(this).val().trim();
            if (query.length < 2) {
                suggestObj.hide();
                return;
            }
            clearTimeout(timeoutId);
            timeoutId = setTimeout(function () {
                $.ajax({
                    url: 'ajax/mmt_autosuggest.php',
                    type: 'GET',
                    data: { q: query },
                    success: function (res) {
                        suggestObj.empty();
                        var items = res.r ? res.r : (Array.isArray(res) ? res : []);
                        if (!Array.isArray(items)) {
                            items = [items];
                        }
                        var html = '';
                        var count = 0;
                        for (var i = 0; i < items.length; i++) {
                            var item = items[i];
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
                            suggestObj.html(html).show();
                        } else {
                            suggestObj.hide();
                        }
                    },
                    error: function () {
                        suggestObj.hide();
                    }
                });
            }, 300);
        });

        suggestObj.on('click', '.qfs-suggest-item', function () {
            var code = $(this).data('code');
            var fullText = $(this).data('full');
            var city = $(this).data('city');
            inputEl.attr('data-code', code);
            if (city) {
                inputEl.attr('data-city', city);
            }
            inputEl.val(fullText ? fullText : code);
            suggestObj.hide();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#' + inputId).length && !$(e.target).closest('#' + suggestDivId).length) {
                suggestObj.hide();
            }
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

    function createQfsFlightCard(item, overallDepCode, overallArrCode, overallDepCityName, overallArrCityName) {
        var journey = item.journey || item;
        var flightsArray = (journey.flights && journey.flights.length > 0) ? journey.flights : [item.primaryFlight || item];
        var primaryF = item.primaryFlight || flightsArray[0] || item;
        var pAirlineObj = primaryF.carrier || primaryF.airline || {};
        var pFname = pAirlineObj.name || primaryF.airlineName || primaryF.carrierName || 'Airline';
        var pFno = (pAirlineObj.code || 'FL') + '-' + (primaryF.flightNo || primaryF.flightNumber || '101');
        var pCarrierCode = pAirlineObj.code || '6E';
        var stops = flightsArray.length > 1 ? (flightsArray.length - 1) : 0;
        var stopsStr = stops === 0 ? 'Non Stop' : stops + ' Stop(s)';

        var pDurationRaw = primaryF.flyTime || primaryF.duration || 150;
        var pDuration = typeof pDurationRaw === 'number'
            ? Math.floor(pDurationRaw / 60) + ' hrs ' + (pDurationRaw % 60) + ' min'
            : pDurationRaw;

        var faresObj = journey.fares || {};
        var paxFares = (faresObj.paxFares && faresObj.paxFares.adt) ? faresObj.paxFares.adt : {};
        var totObj = paxFares.total || faresObj.totalFare || primaryF.fareDetails || {};
        var baseObj = paxFares.base || {};
        var taxObj = paxFares.tax || {};

        var base = parseFloat(baseObj.amount || baseObj.baseFare || 3500);
        var tax = parseFloat(taxObj.amount || taxObj.taxes || 500);
        var tot = parseFloat(totObj.amount || totObj.total || (base + tax));

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
        var dTimeRawUnix = moment(dTimeRaw).valueOf();
        var aTimeRawUnix = moment(aTimeRaw).valueOf();

        var cardHtml = '<div class="card mb-2 qfs-select-flight-card" data-flight="' + encodedData + '" data-duration="' + pDurationRaw + '" data-dtime="' + dTimeRawUnix + '" data-atime="' + aTimeRawUnix + '" data-stops="' + stops + '" data-airline="' + pFname + '" data-price="' + tot + '" style="border: 1px solid #e9ecef; box-shadow: none; border-radius:4px; cursor:pointer; background-color: #f8f9fa;">' +
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
                '<img src="' + logoUrl + '" alt="' + carrierCode + '" style="width:30px; height:30px; object-fit:contain; margin-right:8px;">' +
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
            ? tot.toLocaleString('en-IN', { maximumFractionDigits: 2 })
            : String(tot);
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
        if (typeof window.qQuotationAddFlightRow !== 'function') {
            return;
        }
        flights.forEach(function (f, idx) {
            if (!f) {
                return;
            }
            var rows = mapJourneyToQuotationRows(f, dates[idx] || '');
            rows.forEach(function (row) {
                window.qQuotationAddFlightRow(row);
            });
        });
    }

    function qfsSortCards(containerSel, sortType, newOrder) {
        var $cards = $(containerSel).find('.qfs-select-flight-card');
        if (!$cards.length) {
            return;
        }
        $cards.sort(function (a, b) {
            var valA = parseInt($(a).attr('data-' + sortType), 10);
            var valB = parseInt($(b).attr('data-' + sortType), 10);
            return newOrder === 'asc' ? valA - valB : valB - valA;
        });
        $cards.detach().appendTo(containerSel);
    }

    function qfsApplyFilters() {
        var stopFilter = String($('#qfsFlightsModal .qfs-filter-group-stops .active').attr('data-value') || 'all');
        var timeFilter = String($('#qfsFlightsModal .qfs-filter-group-time .active').attr('data-value') || 'all');

        function filterCards(containerSel) {
            $(containerSel).find('.qfs-select-flight-card').each(function () {
                var $card = $(this);
                var showCard = true;
                if (stopFilter !== 'all') {
                    var cardStops = parseInt($card.attr('data-stops'), 10);
                    if (isNaN(cardStops)) {
                        cardStops = 0;
                    }
                    if (stopFilter === '0' && cardStops !== 0) {
                        showCard = false;
                    } else if (stopFilter === '1' && cardStops !== 1) {
                        showCard = false;
                    } else if (stopFilter === '2' && cardStops < 2) {
                        showCard = false;
                    }
                }
                if (timeFilter !== 'all' && showCard) {
                    var dtimeMs = parseInt($card.attr('data-dtime'), 10);
                    var hour = (dtimeMs && !isNaN(dtimeMs) && typeof moment === 'function')
                        ? moment(dtimeMs).hour()
                        : NaN;
                    if (isNaN(hour)) {
                        showCard = false;
                    } else if (timeFilter === 'morning' && (hour < 6 || hour >= 12)) {
                        showCard = false;
                    } else if (timeFilter === 'afternoon' && (hour < 12 || hour >= 18)) {
                        showCard = false;
                    } else if (timeFilter === 'evening' && (hour < 18 || hour > 23)) {
                        showCard = false;
                    } else if (timeFilter === 'night' && hour > 5) {
                        showCard = false;
                    }
                }
                $card.toggle(showCard);
            });
        }

        if ($('#qfsOnwardCol').length) {
            filterCards('#qfsOnwardCol');
            filterCards('#qfsReturnCol');
        } else {
            filterCards('#qfsFlightsModalBody');
        }
    }

    function prefillQfsSearchFromQuotation() {
        var tDate = $('#q_tentative_date').val();
        if (tDate) {
            $('#qfsApiDate').val(tDate);
            $('#qfsApiReturnDate').attr('min', tDate);
            if ($('#qfsApiReturnDate').val() < tDate) {
                $('#qfsApiReturnDate').val(tDate);
            }
        }
    }

    $(function () {
        initQfsAirportAutosuggest('qfsApiFrom', 'qfsApiFromSuggest');
        initQfsAirportAutosuggest('qfsApiTo', 'qfsApiToSuggest');

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
            var from = $('#qfsApiFrom').attr('data-code') || fromVal;
            var to = $('#qfsApiTo').attr('data-code') || toVal;
            var fromCity = ($('#qfsApiFrom').attr('data-city') || fromVal).split(',')[0].replace(/\(.*?\)/g, '').trim();
            var toCity = ($('#qfsApiTo').attr('data-city') || toVal).split(',')[0].replace(/\(.*?\)/g, '').trim();
            var pax = parseInt($('#q_adults').val(), 10) || 1;
            var tType = $('input[name="qfs_tripType"]:checked').val() === 'roundtrip' ? 'ROUNDTRIP' : 'ONEWAY';
            var returnDate = $('#qfsApiReturnDate').val().trim();

            if (!from || !to || !date || (tType === 'ROUNDTRIP' && !returnDate)) {
                alert('Please fill all required search fields');
                return;
            }

            qfsSearchContext = { from: from, to: to, date: date, returnDate: returnDate };

            var sectorInfos = [{
                src: { code: from, name: from, city: from },
                dest: { code: to, name: to, city: to },
                date: moment(date).format('YYYY-MM-DD'),
                debug: false
            }];
            if (tType === 'ROUNDTRIP') {
                sectorInfos.push({
                    src: { code: to, name: to, city: to },
                    dest: { code: from, name: from, city: from },
                    date: moment(returnDate).format('YYYY-MM-DD'),
                    debug: false
                });
            }

            var payload = {
                sectorInfos: sectorInfos,
                prefAirlines: [{ code: 'ALL', name: 'ALL' }],
                class: 'ALL',
                paxCount: { adt: pax, chd: 0, inf: 0 },
                route: 'ALL',
                disc: false,
                multiHop: false,
                multiCity: false,
                senior: false,
                special: false,
                domestic: true,
                isOfflineSearch: false,
                isPaxWiseCommission: false
            };

            var $btn = $('#qfsSearchFlightsBtn').text('Searching...').prop('disabled', true);
            qfsSelectedOnward = null;
            qfsSelectedReturn = null;

            $.ajax({
                url: 'ajax/via_search.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(payload),
                success: function (res) {
                    $btn.text('Search').prop('disabled', false);
                    if (typeof res === 'string') {
                        try { res = JSON.parse(res); } catch (err) { /* ignore */ }
                    }

                    var flights = [];
                    var rFlights = [];
                    if (res && res.data) {
                        if (res.data.onwardJourneys && res.data.onwardJourneys.length) {
                            res.data.onwardJourneys.forEach(function (journey) {
                                flights.push({ journey: journey, primaryFlight: (journey.flights && journey.flights.length) ? journey.flights[0] : {} });
                            });
                        }
                        if (res.data.returnJourneys && res.data.returnJourneys.length) {
                            res.data.returnJourneys.forEach(function (journey) {
                                rFlights.push({ journey: journey, primaryFlight: (journey.flights && journey.flights.length) ? journey.flights[0] : {} });
                            });
                        }
                    } else if (res && res.onwardJourneys) {
                        res.onwardJourneys.forEach(function (journey) {
                            flights.push({ journey: journey, primaryFlight: (journey.flights && journey.flights.length) ? journey.flights[0] : {} });
                        });
                        if (res.returnJourneys) {
                            res.returnJourneys.forEach(function (journey) {
                                rFlights.push({ journey: journey, primaryFlight: (journey.flights && journey.flights.length) ? journey.flights[0] : {} });
                            });
                        }
                    } else if (res && res.flights) {
                        flights = res.flights;
                    }

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
                        modalBody.html('<div class="row mt-2"><div class="col-md-6" id="qfsOnwardCol"></div><div class="col-md-6" id="qfsReturnCol"></div></div>');
                        if (flights.length) {
                            flights.forEach(function (f) { $('#qfsOnwardCol').append(createQfsFlightCard(f, from, to, fromCity, toCity)); });
                        } else {
                            $('#qfsOnwardCol').html('<div class="alert alert-info">No onward flights found.</div>');
                        }
                        if (rFlights.length) {
                            rFlights.forEach(function (f) { $('#qfsReturnCol').append(createQfsFlightCard(f, to, from, toCity, fromCity)); });
                        } else {
                            $('#qfsReturnCol').html('<div class="alert alert-info">No return flights found.</div>');
                        }
                    } else {
                        $('#qfsFlightsModal .modal-dialog').css('max-width', '900px');
                        $('#qfsFlightsModalTitle').html('<div class="w-100 text-center">' + from + ' - ' + to + " <span style='color:#ccc'>|</span> " + moment(date).format('DD/MM/YYYY') + '</div>' + sortHtml);
                        if (flights.length) {
                            flights.forEach(function (f) { modalBody.append(createQfsFlightCard(f, from, to, fromCity, toCity)); });
                        } else {
                            modalBody.html('<div class="alert alert-info"><i class="fa fa-info-circle"></i> No flights found for this route and date.</div>');
                        }
                    }

                    $('#qfsFlightsModal').modal('show');
                },
                error: function (err) {
                    $btn.text('Search').prop('disabled', false);
                    $('#qfsFlightsModalTitle').text(from + ' - ' + to + ' | ' + date);
                    var errMsg = (err.responseJSON && err.responseJSON.err && err.responseJSON.err.title)
                        ? err.responseJSON.err.title
                        : 'Could not connect to flight search. Please try again.';
                    $('#qfsFlightsModalBody').html('<div class="alert alert-danger" role="alert"><h5 class="alert-heading"><i class="fa fa-exclamation-triangle"></i> Search Failed</h5><p style="margin-bottom:0; font-size:13px;">' + errMsg + '</p></div>');
                    $('#qfsFlightsModal').modal('show');
                }
            });
        });

        $(document).on('click', '#qfsFlightsModal .qfs-select-flight-card', function () {
            var f = JSON.parse(decodeURIComponent($(this).attr('data-flight')));
            var isReturn = $(this).closest('#qfsReturnCol').length > 0;
            var isRoundTrip = $('input[name="qfs_tripType"]:checked').val() === 'roundtrip';

            if (isRoundTrip) {
                if (isReturn) {
                    qfsSelectedReturn = f;
                } else {
                    qfsSelectedOnward = f;
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
            if ($('#qfsOnwardCol').length) {
                qfsSortCards('#qfsOnwardCol', sortType, newOrder);
                qfsSortCards('#qfsReturnCol', sortType, newOrder);
            } else {
                qfsSortCards('#qfsFlightsModalBody', sortType, newOrder);
            }
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
            qfsApplyFilters();
        });
    });
})(jQuery);
