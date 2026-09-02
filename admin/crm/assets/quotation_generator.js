/* Quotation Generator - form behaviour */
(function ($) {
    'use strict';

    var DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    var richEditors = ['inclusion', 'exclusion', 'payment_policy', 'cancellation_policy', 'terms_conditions', 'other_details'];

    function money(n) {
        n = parseFloat(n);
        if (isNaN(n)) n = 0;
        return n.toLocaleString('en-IN', { maximumFractionDigits: 2 });
    }

    function esc(str) {
        return $('<div>').text(str == null ? '' : str).html();
    }

    // admin root URL (page lives at .../admin/crm/quotation_generator.php)
    var ADMIN_BASE = location.href.replace(/[?#].*$/, '').replace(/\/crm\/[^\/]*$/, '/');

    function absUrl(u) {
        if (!u) return '';
        if (/^(https?:)?\/\//i.test(u) || /^data:/i.test(u)) return u;
        return ADMIN_BASE + u.replace(/^\//, '');
    }

    function fmtDayDate(baseStr, offset) {
        if (!baseStr) return '';
        var d = new Date(baseStr + 'T00:00:00');
        if (isNaN(d.getTime())) return '';
        d.setDate(d.getDate() + offset);
        var dd = String(d.getDate()).padStart(2, '0');
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var yyyy = d.getFullYear();
        return dd + '-' + mm + '-' + yyyy + ' ' + DAY_NAMES[d.getDay()];
    }

    /* ------------------------------------------------------------------ */
    /* Accordions                                                          */
    /* ------------------------------------------------------------------ */
    function onAccordionBodyShown($body) {
        if (!$body || !$body.length || !$body.is(':visible')) {
            return;
        }
        if ($body.attr('id') === 'qSectionBody4' || $body.find('#qItineraryDays').length) {
            initItineraryEditors();
        }
        if ($body.hasClass('q-day-body')) {
            var editorId = $body.closest('.q-day-card').attr('data-editor-id');
            if (editorId) {
                initQuotationSummernote($('#' + editorId), 160);
            }
            return;
        }
        $body.find('textarea.q-editor').each(function () {
            initQuotationSummernote($(this), 160);
        });
    }

    function toggleAccordionHead($head, forceOpen) {
        if (!$head || !$head.length) {
            return;
        }
        var target = $head.attr('data-target') || $head.data('target');
        var $body = $(target);
        if (!$body.length) {
            return;
        }
        var shouldOpen = forceOpen === true ? true : (forceOpen === false ? false : $body.is(':hidden'));
        if (shouldOpen) {
            $head.removeClass('collapsed').attr('aria-expanded', 'true');
            $body.stop(true, true).slideDown(150, function () {
                onAccordionBodyShown($body);
            });
        } else {
            $head.addClass('collapsed').attr('aria-expanded', 'false');
            $body.stop(true, true).slideUp(150);
        }
    }

    function expandWizardSection(step) {
        step = parseInt(step, 10);
        if (isNaN(step) || step < 1) {
            return;
        }
        var $body = $('#qSectionBody' + step);
        var $head = $body.prev('.q-section-accordion-head');
        if (!$head.length) {
            $head = $('#qWizardSection' + step).find('.q-section-accordion-head').first();
            $body = $('#qSectionBody' + step);
        }
        if ($head.length && $body.length && $head.hasClass('collapsed')) {
            toggleAccordionHead($head, true);
        }
    }

    function expandDayCard($card) {
        if (!$card || !$card.length) {
            return;
        }
        var $head = $card.find('.q-day-head').first();
        if ($head.length && $head.hasClass('collapsed')) {
            toggleAccordionHead($head, true);
        }
    }

    $(document).on('click', '.q-section-accordion-head', function (e) {
        if ($(e.target).closest('[data-accordion-ignore]').length) {
            return;
        }
        toggleAccordionHead($(this));
    });

    $(document).on('keydown', '.q-section-accordion-head', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            if ($(e.target).closest('[data-accordion-ignore]').length) {
                return;
            }
            toggleAccordionHead($(this));
        }
    });

    $(document).on('click', '.q-accordion-head:not(.q-section-accordion-head):not(.q-day-head)', function () {
        toggleAccordionHead($(this));
    });

    $(document).on('click', '.q-day-head', function (e) {
        if ($(e.target).closest('.q-day-ai-suggest').length) {
            return;
        }
        toggleAccordionHead($(this));
    });

    /* ------------------------------------------------------------------ */
    /* Flight / Train rows                                                 */
    /* ------------------------------------------------------------------ */
    var FLIGHT_SUPPLIER_LEGACY = ['MakeMyTrip', 'ClearTrip', 'Goibibo', 'Yatra', 'EaseMyTrip', 'Direct'];
    var qFlightSupplierCreateTarget = null;

    function getFlightSupplierList() {
        return (typeof Q_FLIGHT_SUPPLIERS !== 'undefined' && Array.isArray(Q_FLIGHT_SUPPLIERS))
            ? Q_FLIGHT_SUPPLIERS
            : [];
    }

    function upsertFlightSupplierInList(id, name) {
        id = parseInt(id, 10) || 0;
        name = String(name || '').trim();
        if (id < 1 || !name) {
            return;
        }
        if (typeof Q_FLIGHT_SUPPLIERS === 'undefined' || !Array.isArray(Q_FLIGHT_SUPPLIERS)) {
            window.Q_FLIGHT_SUPPLIERS = [];
        }
        var found = false;
        Q_FLIGHT_SUPPLIERS.forEach(function (s) {
            if (s && parseInt(s.id, 10) === id) {
                s.name = name;
                found = true;
            }
        });
        if (!found) {
            Q_FLIGHT_SUPPLIERS.push({ id: id, name: name });
            Q_FLIGHT_SUPPLIERS.sort(function (a, b) {
                return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' });
            });
        }
    }

    function normalizeLegacyDateInput(val) {
        val = String(val || '').trim();
        if (!val || val === '0000-00-00') return '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(val)) return val;
        var m = val.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
        if (m) {
            return m[3] + '-' + String(m[2]).padStart(2, '0') + '-' + String(m[1]).padStart(2, '0');
        }
        if (typeof moment !== 'undefined') {
            var parsed = moment(val, ['DD/MM/YYYY', 'DD-MM-YYYY', 'D MMM YYYY', 'DD MMM YYYY'], true);
            if (parsed.isValid()) return parsed.format('YYYY-MM-DD');
        }
        return val;
    }

    function normalizeFlightData(data) {
        data = data || {};
        return {
            from: data.from || '',
            to: data.to || '',
            name: data.name || '',
            fl_tr_no: data.fl_tr_no || data.pnr || data.fno || '',
            dep_date: normalizeLegacyDateInput(data.dep_date || data.date || ''),
            dep_time: data.dep_time || data.time || '',
            arr_date: normalizeLegacyDateInput(data.arr_date || data.arv_date || data.dep_date || data.date || ''),
            arr_time: data.arr_time || data.arv_time || '',
            fare: data.fare !== undefined && data.fare !== '' ? data.fare : (data.amount || ''),
            supplier_id: data.supplier_id || '',
            supplier: data.supplier || '',
            layover_time: data.layover_time || '',
            layover_at: data.layover_at || '',
            journey_start: data.journey_start ? true : false,
            journey_label: data.journey_label || '',
            _legacy_type: data._legacy_type || ''
        };
    }

    function parseFlightDateTimeMoment(dateVal, timeVal) {
        if (typeof moment === 'undefined') {
            var dt = parseFlightDateTime(dateVal, timeVal);
            return dt ? moment(dt) : null;
        }
        var dateStr = String(dateVal || '').trim();
        var timeStr = String(timeVal || '').trim();
        if (!dateStr) {
            return null;
        }
        if (!timeStr) {
            timeStr = '00:00';
        }
        var m = moment(dateStr + ' ' + timeStr, ['YYYY-MM-DD HH:mm', 'YYYY-MM-DD HH:mm:ss'], true);
        return m.isValid() ? m : null;
    }

    function formatLayoverMinutes(totalMinutes) {
        if (!totalMinutes || totalMinutes <= 0) {
            return '';
        }
        var hours = Math.floor(totalMinutes / 60);
        var minutes = totalMinutes % 60;
        if (hours > 0 && minutes > 0) {
            return hours + ' hrs ' + minutes + ' min';
        }
        if (hours > 0) {
            return hours + ' hr' + (hours > 1 ? 's' : '');
        }
        return minutes + ' min';
    }

    function calcLayoverMinutesBetweenData(prev, cur) {
        prev = normalizeFlightData(prev);
        cur = normalizeFlightData(cur);
        if (!flightPlacesConnect(prev.to, cur.from)) {
            return -1;
        }
        var prevArr = parseFlightDateTimeMoment(prev.arr_date, prev.arr_time);
        var curDep = parseFlightDateTimeMoment(cur.dep_date, cur.dep_time);
        if (!prevArr || !curDep) {
            return -1;
        }
        return curDep.diff(prevArr, 'minutes');
    }

    function groupFlightsForDisplay(flights) {
        var groups = [];
        var current = null;
        (flights || []).forEach(function (f) {
            var d = normalizeFlightData(f);
            var startNew = d.journey_start || !current;
            if (!startNew && current && current.rows.length) {
                var prev = current.rows[current.rows.length - 1];
                var layMins = calcLayoverMinutesBetweenData(prev, d);
                // Long gap between connected airports usually means outbound vs return, not a layover.
                if (layMins > (24 * 60)) {
                    startNew = true;
                }
            }
            if (startNew) {
                if (current) {
                    groups.push(current);
                }
                current = {
                    label: d.journey_label || '',
                    rows: [d]
                };
            } else {
                current.rows.push(d);
            }
        });
        if (current) {
            groups.push(current);
        }
        if (groups.length === 2 && !groups[0].label && !groups[1].label) {
            groups[0].label = 'Outbound';
            groups[1].label = 'Return';
        } else if (groups.length === 1 && !groups[0].label && groups[0].rows.length > 1) {
            groups[0].label = 'Flight';
        }
        return groups;
    }

    function buildFlightJourneySummary(rows, opts) {
        opts = opts || {};
        var first = normalizeFlightData(rows[0] || {});
        var last = normalizeFlightData(rows[rows.length - 1] || {});
        var segs = rows.length;
        var stops = Math.max(0, segs - 1);
        var stopsLabel = stops === 0 ? 'Non-stop' : (stops + ' stop' + (stops > 1 ? 's' : ''));
        var fare = opts.totalFare || first.fare || '';
        return {
            label: opts.label || first.journey_label || 'Flight',
            from: first.from || '',
            to: last.to || '',
            segments: segs,
            stopsLabel: stopsLabel,
            fare: fare
        };
    }

    function appendFlightJourneyCard(rows, opts) {
        opts = opts || {};
        rows = (rows || []).map(normalizeFlightData);
        if (!rows.length) {
            return;
        }
        var summary = buildFlightJourneySummary(rows, opts);
        var $card = $('<div class="q-flight-journey-card"></div>');
        var headHtml = '' +
            '<div class="q-flight-journey-head">' +
            '<span class="q-flight-journey-badge">' + esc(summary.label) + '</span>' +
            '<span class="q-flight-journey-route">' + esc(summary.from) + ' → ' + esc(summary.to) + '</span>' +
            '<span class="q-flight-journey-meta">' + esc(summary.stopsLabel) + ' · ' + summary.segments + ' segment' + (summary.segments > 1 ? 's' : '') + '</span>';
        if (summary.fare !== '' && summary.fare != null) {
            var fareNum = parseFloat(summary.fare);
            if (!isNaN(fareNum)) {
                headHtml += '<span class="q-flight-journey-fare">₹' + esc(Math.round(fareNum).toLocaleString('en-IN')) + '</span>';
            }
        }
        headHtml += '<button type="button" class="btn q-flight-journey-delete" title="Remove flight"><i class="fas fa-trash-alt"></i></button>';
        headHtml += '</div>';
        $card.append(headHtml);
        var $body = $('<div class="q-flight-journey-body"></div>');
        rows.forEach(function (row, idx) {
            if (idx === 0) {
                row.journey_start = true;
                if (opts.label) {
                    row.journey_label = opts.label;
                }
            }
            $body.append(flightRowHtml(row));
        });
        $card.append($body);
        $('#qFlightRows').append($card);
    }

    function renderFlightList(flights) {
        $('#qFlightRows').empty();
        var groups = groupFlightsForDisplay(flights);
        groups.forEach(function (group) {
            if (group.rows.length > 1 || group.label) {
                appendFlightJourneyCard(group.rows, { label: group.label });
            } else {
                $('#qFlightRows').append(flightRowHtml(group.rows[0]));
            }
        });
        renumberFlightRows();
        qInitSupplierSelect2In($('#qFlightRows'));
        refreshFlightLayovers();
    }

    function flightSupplierOptionsHtml(selectedId, selectedName) {
        var selectedVal = String(selectedId || '').trim();
        var selectedLabel = String(selectedName || '').trim();
        // Legacy rows stored supplier name in `supplier` with no id.
        if (!selectedVal && selectedLabel) {
            selectedVal = selectedLabel;
        }
        var list = getFlightSupplierList();
        var html = '<option value="">Select</option>';
        var found = false;
        var seenNames = {};

        list.forEach(function (s) {
            if (!s) {
                return;
            }
            var id = String(s.id || '').trim();
            var name = String(s.name || '').trim();
            if (!id || !name) {
                return;
            }
            seenNames[name.toLowerCase()] = true;
            var isSelected = (selectedVal !== '' && selectedVal === id)
                || (selectedLabel !== '' && selectedLabel.toLowerCase() === name.toLowerCase())
                || (selectedVal !== '' && selectedVal.toLowerCase() === name.toLowerCase());
            if (isSelected) {
                found = true;
                selectedVal = id;
            }
            html += '<option value="' + esc(id) + '" data-name="' + esc(name) + '"' + (isSelected ? ' selected' : '') + '>' + esc(name) + '</option>';
        });

        FLIGHT_SUPPLIER_LEGACY.forEach(function (name) {
            if (seenNames[String(name).toLowerCase()]) {
                return;
            }
            var isSelected = selectedVal !== '' && selectedVal.toLowerCase() === String(name).toLowerCase();
            if (isSelected) {
                found = true;
            }
            html += '<option value="' + esc(name) + '" data-name="' + esc(name) + '"' + (isSelected ? ' selected' : '') + '>' + esc(name) + '</option>';
        });

        if (selectedVal && !found) {
            html += '<option value="' + esc(selectedVal) + '" data-name="' + esc(selectedLabel || selectedVal) + '" selected>' +
                esc(selectedLabel || selectedVal) + '</option>';
        }

        html += '<option value="__create__">+ Create new supplier…</option>';
        return html;
    }

    function qRestoreSupplierSearchField($sel) {
        var data = $sel && $sel.data('select2');
        if (!data) {
            return;
        }
        var $selection = data.$selection;
        var $dropdown = data.$dropdown;
        if (!$selection || !$dropdown) {
            return;
        }
        var $search = $selection.find('.q-supplier-inline-search-field');
        var $host = $dropdown.find('.select2-search--dropdown');
        if ($search.length && $host.length) {
            $host.append($search);
        }
        $search.removeClass('q-supplier-inline-search-field');
        $selection.removeClass('q-supplier-searching');
        $selection.find('.select2-selection__rendered').removeClass('q-supplier-search-host');
        $dropdown.removeClass('q-supplier-inline-search');
    }

    function qMountSupplierInlineSearch($sel) {
        var data = $sel && $sel.data('select2');
        if (!data) {
            return;
        }
        var $selection = data.$selection;
        var $dropdown = data.$dropdown;
        if (!$selection || !$dropdown) {
            return;
        }
        var $search = $dropdown.find('.select2-search__field');
        if (!$search.length) {
            $search = $selection.find('.select2-search__field');
        }
        if (!$search.length) {
            return;
        }
        var placeholder = String($sel.data('qSupplierPlaceholder') || 'Search…');
        $dropdown.addClass('q-supplier-inline-search');
        $selection.addClass('q-supplier-searching');
        $selection.find('.select2-selection__rendered').addClass('q-supplier-search-host');
        $search
            .addClass('q-supplier-inline-search-field')
            .attr('placeholder', placeholder)
            .val('');
        // Type in the actual supplier field (selection), not a nested dropdown search.
        $selection.prepend($search);
        window.setTimeout(function () {
            try {
                $search.trigger('focus');
            } catch (e) { /* ignore */ }
        }, 0);
    }

    function qDestroySupplierSelect2($sel) {
        if (!$sel || !$sel.length || !$.fn.select2) {
            return;
        }
        qRestoreSupplierSearchField($sel);
        if ($sel.hasClass('select2-hidden-accessible')) {
            try {
                $sel.select2('destroy');
            } catch (e) { /* ignore */ }
        }
        $sel.off('select2:opening.qSupplierPrev select2:open.qCreateFooter select2:open.qInlineSearch select2:closing.qInlineSearch');
    }

    function qTriggerSupplierCreateFromSelect($sel) {
        if (!$sel || !$sel.length) {
            return;
        }
        var prev = $sel.data('prevSupplierVal');
        if (typeof prev === 'undefined') {
            prev = '';
        }
        $sel.val(prev || '').trigger('change.select2');
        if ($sel.hasClass('f-supplier')) {
            openFlightSupplierCreateModal($sel);
            return;
        }
        if ($sel.hasClass('h-supplier')) {
            openHotelSupplierCreateModal($sel);
            return;
        }
        if ($sel.hasClass('q-itin-supplier') || $sel.is('#q_itinerary_supplier')) {
            openItinerarySupplierCreateModal($sel);
        }
    }

    function qMountSupplierCreateFooter($sel, $dropdown) {
        if (!$dropdown || !$dropdown.length) {
            return;
        }
        $dropdown.find('.q-supplier-create-footer').remove();
        if (!$sel.find('option[value="__create__"]').length) {
            return;
        }
        var $footer = $(
            '<button type="button" class="q-supplier-create-footer">' +
            '<i class="fas fa-plus-circle" aria-hidden="true"></i>' +
            '<span>Create new supplier…</span>' +
            '</button>'
        );
        $footer.on('mousedown touchstart', function (e) {
            e.preventDefault();
            e.stopPropagation();
            try {
                $sel.select2('close');
            } catch (err) { /* ignore */ }
            window.setTimeout(function () {
                qTriggerSupplierCreateFromSelect($sel);
            }, 0);
        });
        $dropdown.append($footer);
    }

    function qInitSupplierSelect2($sel, opts) {
        opts = opts || {};
        if (!$sel || !$sel.length || !$.fn.select2) {
            return;
        }
        qDestroySupplierSelect2($sel);
        var hasCreate = $sel.find('option[value="__create__"]').length > 0;
        var placeholder = opts.placeholder || 'Select';
        $sel.data('qSupplierPlaceholder', placeholder);
        $sel.select2({
            width: '100%',
            placeholder: placeholder,
            allowClear: false,
            minimumResultsForSearch: 0,
            dropdownParent: $(document.body),
            dropdownCssClass: 'q-supplier-s2-dropdown' + (hasCreate ? ' has-create-action' : ''),
            selectionCssClass: 'q-supplier-s2-selection',
            templateResult: function (data) {
                if (!data || data.loading) {
                    return data && data.text ? data.text : null;
                }
                // Keep create action in a pinned footer instead of the scroll list.
                if (String(data.id) === '__create__') {
                    return null;
                }
                return data.text;
            },
            matcher: function (params, data) {
                if (String(data.id) === '__create__') {
                    return null;
                }
                if ($.fn.select2.defaults && typeof $.fn.select2.defaults.defaults.matcher === 'function') {
                    return $.fn.select2.defaults.defaults.matcher(params, data);
                }
                // Fallback: default-like contains match
                if ($.trim(params.term || '') === '') {
                    return data;
                }
                var term = String(params.term || '').toUpperCase();
                var text = String(data.text || '').toUpperCase();
                return text.indexOf(term) > -1 ? data : null;
            },
            language: {
                noResults: function () {
                    return 'No supplier found';
                },
                searching: function () {
                    return 'Searching…';
                }
            }
        });
        $sel.off('select2:opening.qSupplierPrev').on('select2:opening.qSupplierPrev', function () {
            $(this).data('prevSupplierVal', $(this).val() || '');
        });
        $sel.off('select2:open.qInlineSearch').on('select2:open.qInlineSearch', function () {
            var $open = $(this);
            window.setTimeout(function () {
                qMountSupplierInlineSearch($open);
            }, 0);
        });
        $sel.off('select2:closing.qInlineSearch').on('select2:closing.qInlineSearch', function () {
            qRestoreSupplierSearchField($(this));
        });
        $sel.off('select2:open.qCreateFooter').on('select2:open.qCreateFooter', function () {
            var $open = $(this);
            window.setTimeout(function () {
                var $dropdown = $('.select2-container--open .select2-dropdown.q-supplier-s2-dropdown').last();
                qMountSupplierCreateFooter($open, $dropdown);
            }, 0);
        });
    }

    function qInitSupplierSelect2In($root) {
        var $scope = $root && $root.length ? $root : $(document);
        $scope.find('.f-supplier').each(function () {
            qInitSupplierSelect2($(this), { placeholder: 'Select' });
        });
        $scope.find('.h-supplier').each(function () {
            qInitSupplierSelect2($(this), { placeholder: 'Select supplier' });
        });
        $scope.find('.q-itin-supplier').each(function () {
            qInitSupplierSelect2($(this), { placeholder: 'Select supplier' });
        });
        if ($scope.is('#q_itinerary_supplier') || $scope.find('#q_itinerary_supplier').length) {
            qInitSupplierSelect2($('#q_itinerary_supplier'), { placeholder: 'Select supplier' });
        }
    }

    function refreshAllFlightSupplierSelects(preferSelect) {
        preferSelect = preferSelect || null;
        $('#qFlightRows .f-supplier').each(function () {
            var $sel = $(this);
            var curVal = String($sel.val() || '');
            var curName = String($sel.find('option:selected').attr('data-name') || $sel.find('option:selected').text() || '').trim();
            if (preferSelect && preferSelect.$el && preferSelect.$el[0] === $sel[0]) {
                curVal = String(preferSelect.id || '');
                curName = String(preferSelect.name || '');
            }
            if (curVal === '__create__') {
                curVal = '';
                curName = '';
            }
            qDestroySupplierSelect2($sel);
            $sel.html(flightSupplierOptionsHtml(curVal, curName));
            if (preferSelect && preferSelect.$el && preferSelect.$el[0] === $sel[0] && preferSelect.id) {
                $sel.val(String(preferSelect.id));
            }
            qInitSupplierSelect2($sel, { placeholder: 'Select' });
            $sel.data('prevSupplierVal', $sel.val() || '');
        });
    }

    function openFlightSupplierCreateModal($select) {
        qFlightSupplierCreateTarget = $select && $select.length ? $select : null;
        window.qSupplierCreateContext = 'flight';
        var destination = String($('[name=destination]').val() || $('#qDestinationInput').val() || '').trim();
        if ($('#qSupplierCreateForm').length && $('#qSupplierCreateForm')[0]) {
            $('#qSupplierCreateForm')[0].reset();
        }
        $('#qScDestination').val(destination);
        $('#qScDestination').closest('.form-group').find('.form-text').text(
            destination
                ? 'Saved to Supplier Master as Flight / Train and linked to this destination when available.'
                : 'Saved to Supplier Master as Flight / Train.'
        );
        if ($('#qSupplierCreateModal').length) {
            $('#qSupplierCreateModal').modal('show');
        } else {
            alert('Supplier create form is not available on this page.');
        }
    }

    function renumberFlightRows() {
        refreshFlightLayovers();
    }

    function parseFlightDateTime(dateVal, timeVal) {
        var dateStr = String(dateVal || '').trim();
        var timeStr = String(timeVal || '').trim();
        if (!dateStr) {
            return null;
        }
        if (!timeStr) {
            timeStr = '00:00';
        }
        if (timeStr.length === 5) {
            timeStr += ':00';
        }
        var dt = new Date(dateStr + 'T' + timeStr);
        if (isNaN(dt.getTime())) {
            return null;
        }
        return dt;
    }

    function extractFlightAirportCode(place) {
        var s = String(place || '').trim();
        if (!s) {
            return '';
        }
        var m = s.match(/\(([A-Za-z0-9]{3})\)\s*$/);
        if (m) {
            return m[1].toUpperCase();
        }
        m = s.match(/\b([A-Za-z]{3})\b\s*$/);
        return m ? m[1].toUpperCase() : '';
    }

    function flightPlacesConnect(prevTo, curFrom) {
        var prevCode = extractFlightAirportCode(prevTo);
        var curCode = extractFlightAirportCode(curFrom);
        if (prevCode && curCode) {
            return prevCode === curCode;
        }
        var a = String(prevTo || '').trim().toLowerCase();
        var b = String(curFrom || '').trim().toLowerCase();
        return !!(a && b && a === b);
    }

    function formatLayoverDurationLabel(ms) {
        if (!ms || ms <= 0) {
            return '';
        }
        return formatLayoverMinutes(Math.round(ms / 60000));
    }

    function buildFlightLayoverHtml(layoverAt, layoverTime) {
        if (!layoverTime) {
            return '';
        }
        return '' +
            '<div class="q-flight-layover">' +
            '<div class="q-flight-layover-text">' +
            '<i class="fas fa-clock"></i>' +
            '<span>Layover at ' + esc(layoverAt || 'connection') + ': <strong>' + esc(layoverTime) + '</strong></span>' +
            '</div>' +
            '</div>';
    }

    function isFlightJourneyBoundary($prev, $row) {
        if (!$prev || !$prev.length || !$row || !$row.length) {
            return true;
        }
        if ($row.find('.f-journey-start').val() === '1') {
            return true;
        }
        var $prevJourney = $prev.closest('.q-flight-journey-card');
        var $curJourney = $row.closest('.q-flight-journey-card');
        if ($prevJourney.length && $curJourney.length && $prevJourney[0] !== $curJourney[0]) {
            return true;
        }
        return false;
    }

    function calcLayoverBetweenRows($prev, $row) {
        var prevTo = String($prev.find('.f-to').val() || '').trim();
        var curFrom = String($row.find('.f-from').val() || '').trim();
        if (!flightPlacesConnect(prevTo, curFrom)) {
            return { at: '', time: '' };
        }
        var prevArr = parseFlightDateTimeMoment($prev.find('.f-arr-date').val(), $prev.find('.f-arr-time').val());
        var curDep = parseFlightDateTimeMoment($row.find('.f-dep-date').val(), $row.find('.f-dep-time').val());
        if (!prevArr || !curDep) {
            return { at: '', time: '' };
        }
        var mins = curDep.diff(prevArr, 'minutes');
        if (mins < 0) {
            return { at: '', time: '' };
        }
        return {
            at: prevTo,
            time: formatLayoverMinutes(mins)
        };
    }

    function refreshFlightLayovers() {
        var $rows = $('#qFlightRows .q-flight-row');
        $rows.each(function (index) {
            var $row = $(this);
            var $layover = $row.find('.q-flight-layover');

            if (index === 0) {
                $row.find('.f-layover-time').val('');
                $row.find('.f-layover-at').val('');
                $layover.remove();
                return;
            }

            var $prev = $rows.eq(index - 1);
            var layoverAt = '';
            var layoverTime = '';

            if (!isFlightJourneyBoundary($prev, $row)) {
                var lay = calcLayoverBetweenRows($prev, $row);
                layoverAt = lay.at;
                layoverTime = lay.time;
            }

            $row.find('.f-layover-time').val(layoverTime);
            $row.find('.f-layover-at').val(layoverAt);

            var html = buildFlightLayoverHtml(layoverAt, layoverTime);
            if (!html) {
                $layover.remove();
                return;
            }
            if ($layover.length) {
                $layover.replaceWith(html);
            } else {
                $row.find('.q-flight-segment-card').before(html);
            }
        });
    }

    function flightRowHtml(data) {
        var d = normalizeFlightData(data);

        return '' +
            '<div class="q-flight-row">' +
            '<input type="hidden" class="f-layover-time" value="' + esc(d.layover_time) + '">' +
            '<input type="hidden" class="f-layover-at" value="' + esc(d.layover_at) + '">' +
            '<input type="hidden" class="f-journey-start" value="' + (d.journey_start ? '1' : '0') + '">' +
            '<input type="hidden" class="f-journey-label" value="' + esc(d.journey_label) + '">' +
            '<div class="q-flight-segment-card">' +
            '<div class="q-flight-segment-row">' +
            '<div class="q-ft-col q-ft-col-from">' +
            '<span class="q-ft-label">From</span>' +
            '<div class="q-flight-place">' +
            '<input type="text" class="form-control form-control-sm f-from" value="' + esc(d.from) + '" placeholder="City (CODE)">' +
            '</div></div>' +
            '<div class="q-ft-col q-ft-col-swap">' +
            '<span class="q-ft-label">&nbsp;</span>' +
            '<button type="button" class="btn q-flight-swap" title="Swap From / To"><i class="fas fa-exchange-alt"></i></button>' +
            '</div>' +
            '<div class="q-ft-col q-ft-col-to">' +
            '<span class="q-ft-label">To</span>' +
            '<div class="q-flight-place">' +
            '<input type="text" class="form-control form-control-sm f-to" value="' + esc(d.to) + '" placeholder="City (CODE)">' +
            '</div></div>' +
            '<div class="q-ft-col q-ft-col-airline">' +
            '<span class="q-ft-label">Airline / Flight No.</span>' +
            '<div class="q-flight-airline-combo">' +
            '<input type="text" class="form-control form-control-sm f-name" value="' + esc(d.name) + '" placeholder="Airline">' +
            '<span class="q-flight-airline-sep" aria-hidden="true">•</span>' +
            '<input type="text" class="form-control form-control-sm f-fl-no" value="' + esc(d.fl_tr_no) + '" placeholder="No.">' +
            '</div></div>' +
            '<div class="q-ft-col q-ft-col-depart">' +
            '<span class="q-ft-label">Departure</span>' +
            '<div class="q-flight-datetime">' +
            '<input type="date" class="form-control form-control-sm f-dep-date" value="' + esc(d.dep_date) + '" title="Departure date">' +
            '<input type="time" class="form-control form-control-sm f-dep-time" value="' + esc(d.dep_time) + '" title="Departure time">' +
            '</div></div>' +
            '<div class="q-ft-col q-ft-col-arrive">' +
            '<span class="q-ft-label">Arrival</span>' +
            '<div class="q-flight-datetime">' +
            '<input type="date" class="form-control form-control-sm f-arr-date" value="' + esc(d.arr_date) + '" title="Arrival date">' +
            '<input type="time" class="form-control form-control-sm f-arr-time" value="' + esc(d.arr_time) + '" title="Arrival time">' +
            '</div></div>' +
            '<div class="q-ft-col q-ft-col-fare">' +
            '<span class="q-ft-label">Rate</span>' +
            '<div class="q-flight-fare">' +
            '<input type="number" step="0.01" class="form-control form-control-sm f-fare" value="' + esc(d.fare) + '" placeholder="0.00">' +
            '</div></div>' +
            '<div class="q-ft-col q-ft-col-supplier">' +
            '<span class="q-ft-label">Supplier</span>' +
            '<select class="form-control form-control-sm f-supplier">' +
            flightSupplierOptionsHtml(d.supplier_id, d.supplier) +
            '</select>' +
            '</div>' +
            '<div class="q-ft-col q-ft-col-action">' +
            '<span class="q-ft-label">&nbsp;</span>' +
            '<button type="button" class="btn q-flight-remove q-remove" data-remove=".q-flight-row" title="Remove segment"><i class="fas fa-trash-alt"></i></button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
    }

    function collectFlights() {
        var out = [];
        $('#qFlightRows .q-flight-row').each(function () {
            var $r = $(this);
            var depDate = $r.find('.f-dep-date').val();
            var depTime = $r.find('.f-dep-time').val();
            var arrDate = $r.find('.f-arr-date').val();
            var arrTime = $r.find('.f-arr-time').val();
            var flNo = $r.find('.f-fl-no').val();
            var fare = $r.find('.f-fare').val();
            var $sup = $r.find('.f-supplier');
            var supplierVal = String($sup.val() || '').trim();
            if (supplierVal === '__create__') {
                supplierVal = '';
            }
            var supplierName = String($sup.find('option:selected').attr('data-name') || $sup.find('option:selected').text() || '').trim();
            if (!supplierVal || supplierName === 'Select' || supplierName.indexOf('Create new') === 0) {
                supplierName = '';
            }
            // Numeric values are master IDs; legacy string values are names.
            var supplierId = /^\d+$/.test(supplierVal) ? supplierVal : '';
            if (!supplierName && supplierVal && !supplierId) {
                supplierName = supplierVal;
            }
            out.push({
                from: $r.find('.f-from').val(),
                to: $r.find('.f-to').val(),
                name: $r.find('.f-name').val(),
                fl_tr_no: flNo,
                dep_date: depDate,
                dep_time: depTime,
                arr_date: arrDate,
                arr_time: arrTime,
                fare: fare,
                supplier_id: supplierId,
                supplier: supplierName || supplierVal,
                layover_time: $r.find('.f-layover-time').val(),
                layover_at: $r.find('.f-layover-at').val(),
                journey_start: $r.find('.f-journey-start').val() === '1',
                journey_label: $r.find('.f-journey-label').val(),
                date: depDate,
                time: depTime,
                pnr: flNo,
                amount: fare
            });
        });
        return out;
    }

    /* ------------------------------------------------------------------ */
    /* Hotel rows + category options                                       */
    /* ------------------------------------------------------------------ */
    var qHotelSearchUrl = 'crm/ajax/search_quotation_hotels.php';
    var qHotelCategorySeq = 1;
    var qActiveHotelCategoryId = '';
    var Q_MAX_HOTEL_OPTIONS = 2;

    function nextHotelCategoryId() {
        return 'opt_' + (qHotelCategorySeq++);
    }

    function defaultHotelCategoryLabel(index) {
        var n = index + 1;
        return 'Option ' + (n < 10 ? '0' : '') + n;
    }

    function bumpHotelIdSeqFromCategory(cat) {
        cat = cat || {};
        if (cat.id && /^opt_(\d+)$/.test(String(cat.id))) {
            var idNum = parseInt(RegExp.$1, 10);
            if (idNum >= qHotelCategorySeq) {
                qHotelCategorySeq = idNum + 1;
            }
        }
    }

    function syncHotelIdSeqFromPanels() {
        getHotelCategoryPanels().each(function () {
            bumpHotelIdSeqFromCategory({ id: $(this).attr('data-cat-id') });
        });
    }

    /** Always Option 1..N in DOM order — never tied to internal opt_* ids. */
    function renumberHotelCategoryLabels() {
        var $panels = getHotelCategoryPanels();
        $panels.each(function (idx) {
            $(this).find('.q-hotel-cat-label').val(defaultHotelCategoryLabel(idx));
        });
        return $panels.length;
    }

    function nextHotelCategoryLabel() {
        return defaultHotelCategoryLabel(getHotelCategoryPanels().length);
    }

    var qItinerarySupplierCreateTarget = null;
    var qHotelSupplierCreateTarget = null;

    function upsertHotelSupplierInList(id, name) {
        id = parseInt(id, 10) || 0;
        name = String(name || '').trim();
        if (id < 1 || !name) {
            return;
        }
        if (typeof Q_HOTEL_SUPPLIERS === 'undefined' || !Array.isArray(Q_HOTEL_SUPPLIERS)) {
            window.Q_HOTEL_SUPPLIERS = [];
        }
        var found = false;
        Q_HOTEL_SUPPLIERS.forEach(function (s) {
            if (s && parseInt(s.id, 10) === id) {
                s.name = name;
                found = true;
            }
        });
        if (!found) {
            Q_HOTEL_SUPPLIERS.push({ id: id, name: name });
            Q_HOTEL_SUPPLIERS.sort(function (a, b) {
                return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' });
            });
        }
    }

    function hotelSupplierOptionsHtml(selectedId, selectedName, options) {
        options = options || {};
        var selectedVal = String(selectedId || '').trim();
        var selectedLabel = String(selectedName || '').trim();
        if (selectedVal === '__create__') {
            selectedVal = '';
            selectedLabel = '';
        }
        var list = (typeof Q_HOTEL_SUPPLIERS !== 'undefined' && Array.isArray(Q_HOTEL_SUPPLIERS))
            ? Q_HOTEL_SUPPLIERS
            : [];
        var html = '<option value="">Select supplier</option>';
        var found = false;
        list.forEach(function (s) {
            if (!s) {
                return;
            }
            var id = String(s.id || '').trim();
            var name = String(s.name || '').trim();
            if (!id || !name) {
                return;
            }
            var isSelected = selectedVal !== '' && selectedVal === id;
            if (isSelected) {
                found = true;
            }
            html += '<option value="' + esc(id) + '" data-name="' + esc(name) + '"' + (isSelected ? ' selected' : '') + '>' + esc(name) + '</option>';
        });
        if (selectedVal && !found) {
            html += '<option value="' + esc(selectedVal) + '" data-name="' + esc(selectedLabel || ('Supplier #' + selectedVal)) + '" selected>' +
                esc(selectedLabel || ('Supplier #' + selectedVal)) + '</option>';
        }
        if (options.allowCreate) {
            html += '<option value="__create__">+ Create new supplier…</option>';
        }
        return html;
    }

    function refreshItinerarySupplierSelect(preferSelect) {
        preferSelect = preferSelect || null;
        var $sels = $('#qItinerarySupplierRows .q-itin-supplier');
        if (!$sels.length && $('#q_itinerary_supplier').length) {
            $sels = $('#q_itinerary_supplier');
        }
        if (!$sels.length) {
            return;
        }
        $sels.each(function () {
            var $sel = $(this);
            var curVal = String($sel.val() || '');
            var curName = String($sel.find('option:selected').attr('data-name') || $sel.find('option:selected').text() || '').trim();
            if (preferSelect && preferSelect.$el && preferSelect.$el[0] === $sel[0]) {
                curVal = String(preferSelect.id || '');
                curName = String(preferSelect.name || '');
            } else if (preferSelect && preferSelect.id && !preferSelect.$el) {
                // No specific target — keep current values, only refresh options.
            }
            if (curVal === '__create__' || curName.indexOf('Create new') === 0) {
                curVal = '';
                curName = '';
            }
            qDestroySupplierSelect2($sel);
            $sel.html(hotelSupplierOptionsHtml(curVal, curName, { allowCreate: true }));
            if (preferSelect && preferSelect.$el && preferSelect.$el[0] === $sel[0] && preferSelect.id) {
                $sel.val(String(preferSelect.id));
            }
            qInitSupplierSelect2($sel, { placeholder: 'Select supplier' });
            $sel.data('prevSupplierVal', $sel.val() || '');
        });
    }

    function itinerarySupplierRowHtml(data) {
        data = data || {};
        var rate = data.rate !== undefined && data.rate !== '' ? data.rate : '';
        if (rate !== '' && rate != null) {
            var n = parseFloat(rate);
            rate = isNaN(n) ? '' : String(Math.round(n));
        } else {
            rate = '';
        }
        return '' +
            '<div class="q-itin-supplier-row row q-row-tight align-items-end">' +
            '<div class="col-md-5 col-lg-4">' +
            '<div class="form-group mb-0">' +
            '<label class="q-label">Supplier</label>' +
            '<select class="form-control form-control-sm q-itin-supplier">' +
            hotelSupplierOptionsHtml(data.supplier_id, data.supplier || data.supplier_name, { allowCreate: true }) +
            '</select></div></div>' +
            '<div class="col-md-3 col-lg-2">' +
            '<div class="form-group mb-0">' +
            '<label class="q-label">Rate</label>' +
            '<input type="number" min="0" step="1" inputmode="numeric" class="form-control form-control-sm q-itin-rate" value="' + esc(rate) + '" placeholder="0">' +
            '</div></div>' +
            '<div class="col-auto pb-0">' +
            '<button type="button" class="btn btn-outline-danger btn-sm q-itin-supplier-remove" title="Remove supplier rate">' +
            '<i class="fas fa-trash-alt"></i></button>' +
            '</div></div>';
    }

    function ensureItinerarySupplierRows() {
        var $wrap = $('#qItinerarySupplierRows');
        if (!$wrap.length) {
            return $();
        }
        if (!$wrap.find('.q-itin-supplier-row').length) {
            addItinerarySupplierRow({});
        }
        refreshItinerarySupplierRemoveState();
        return $wrap.find('.q-itin-supplier-row');
    }

    function refreshItinerarySupplierRemoveState() {
        var $rows = $('#qItinerarySupplierRows .q-itin-supplier-row');
        var canRemove = $rows.length > 1;
        $rows.find('.q-itin-supplier-remove').prop('disabled', !canRemove).toggle(canRemove);
    }

    function addItinerarySupplierRow(data) {
        var $wrap = $('#qItinerarySupplierRows');
        if (!$wrap.length) {
            return $();
        }
        var $row = $(itinerarySupplierRowHtml(data || {}));
        $wrap.append($row);
        qInitSupplierSelect2($row.find('.q-itin-supplier'), { placeholder: 'Select supplier' });
        $row.find('.q-itin-supplier').data('prevSupplierVal', $row.find('.q-itin-supplier').val() || '');
        refreshItinerarySupplierRemoveState();
        return $row;
    }

    function openItinerarySupplierCreateModal($select) {
        qItinerarySupplierCreateTarget = $select && $select.length ? $select : $('#qItinerarySupplierRows .q-itin-supplier').last();
        window.qSupplierCreateContext = 'itinerary';
        var destination = String($('[name=destination]').val() || $('#qDestinationInput').val() || '').trim();
        if ($('#qSupplierCreateForm').length && $('#qSupplierCreateForm')[0]) {
            $('#qSupplierCreateForm')[0].reset();
        }
        $('#qScDestination').val(destination);
        $('#qScDestination').closest('.form-group').find('.form-text').text(
            destination
                ? 'Saved to Supplier Master as Land Package / Hotels and linked to this destination when available.'
                : 'Saved to Supplier Master as Land Package / Hotels.'
        );
        if ($('#qSupplierCreateModal').length) {
            $('#qSupplierCreateModal').modal('show');
        } else {
            alert('Supplier create form is not available on this page.');
        }
    }

    function refreshAllHotelSupplierSelects(preferSelect) {
        preferSelect = preferSelect || null;
        $('.q-hotel-rows .h-supplier').each(function () {
            var $sel = $(this);
            var curVal = String($sel.val() || '');
            var curName = String($sel.find('option:selected').attr('data-name') || $sel.find('option:selected').text() || '').trim();
            if (preferSelect && preferSelect.$el && preferSelect.$el[0] === $sel[0]) {
                curVal = String(preferSelect.id || '');
                curName = String(preferSelect.name || '');
            }
            if (curVal === '__create__' || curName.indexOf('Create new') === 0) {
                curVal = '';
                curName = '';
            }
            qDestroySupplierSelect2($sel);
            $sel.html(hotelSupplierOptionsHtml(curVal, curName, { allowCreate: true }));
            if (preferSelect && preferSelect.$el && preferSelect.$el[0] === $sel[0] && preferSelect.id) {
                $sel.val(String(preferSelect.id));
            }
            qInitSupplierSelect2($sel, { placeholder: 'Select supplier' });
            $sel.data('prevSupplierVal', $sel.val() || '');
        });
    }

    function openHotelSupplierCreateModal($select) {
        qHotelSupplierCreateTarget = $select && $select.length ? $select : null;
        window.qSupplierCreateContext = 'hotel';
        var destination = String($('[name=destination]').val() || $('#qDestinationInput').val() || '').trim();
        if ($('#qSupplierCreateForm').length && $('#qSupplierCreateForm')[0]) {
            $('#qSupplierCreateForm')[0].reset();
        }
        $('#qScDestination').val(destination);
        $('#qScDestination').closest('.form-group').find('.form-text').text(
            destination
                ? 'Saved to Supplier Master as Hotels / Land Package and linked to this destination when available.'
                : 'Saved to Supplier Master as Hotels / Land Package.'
        );
        if ($('#qSupplierCreateModal').length) {
            $('#qSupplierCreateModal').modal('show');
        } else {
            alert('Supplier create form is not available on this page.');
        }
    }

    function normalizeHotelData(data) {
        data = data || {};
        return {
            city: data.city || '',
            city_id: data.city_id || '',
            hotel_id: data.hotel_id || '',
            name: data.name || data.hotel_name || '',
            room_type: data.room_type || '',
            rooms: data.rooms !== undefined && data.rooms !== '' ? data.rooms : '',
            meal_plan: data.meal_plan || data.meal || 'CP',
            nights: data.nights !== undefined && data.nights !== '' ? data.nights : '',
            checkin: normalizeLegacyDateInput(data.checkin || data.check_in || ''),
            checkout: normalizeLegacyDateInput(data.checkout || data.check_out || ''),
            rate: (function () {
                var raw = data.rate !== undefined && data.rate !== '' ? data.rate : (data.amount || '');
                if (raw === '' || raw == null) return '';
                var n = parseFloat(raw);
                return isNaN(n) ? '' : String(Math.round(n));
            })(),
            supplier_id: data.supplier_id || '',
            supplier: data.supplier || data.supplier_name || ''
        };
    }

    function hotelRowHtml(data) {
        var d = normalizeHotelData(data);
        return '' +
            '<div class="q-repeat-row q-hotel-row">' +
            '<input type="hidden" class="h-city-id" value="' + esc(d.city_id) + '">' +
            '<input type="hidden" class="h-hotel-id" value="' + esc(d.hotel_id) + '">' +
            '<button type="button" class="btn btn-sm btn-outline-danger q-remove" data-remove=".q-hotel-row"><i class="fas fa-times"></i></button>' +
            '<div class="q-hotel-fields mb-2">' +
            '<div class="q-hotel-field q-hotel-combo">' +
            '<label class="q-label">City</label>' +
            '<input type="text" class="form-control form-control-sm h-city" value="' + esc(d.city) + '" autocomplete="off" placeholder="Type city">' +
            '<div class="q-hotel-menu q-hotel-city-menu" style="display:none;"></div></div>' +
            '<div class="q-hotel-field q-hotel-combo q-hotel-field-hotel">' +
            '<label class="q-label">Hotel</label>' +
            '<input type="text" class="form-control form-control-sm h-name" value="' + esc(d.name) + '" autocomplete="off" placeholder="Search or type hotel name">' +
            '<div class="q-hotel-menu q-hotel-name-menu" style="display:none;"></div></div>' +
            '<div class="q-hotel-field q-hotel-combo q-hotel-field-wide">' +
            '<label class="q-label">Room Type</label>' +
            '<input type="text" class="form-control form-control-sm h-room" value="' + esc(d.room_type) + '" autocomplete="off" placeholder="e.g. Deluxe Room">' +
            '<div class="q-hotel-menu q-hotel-room-menu" style="display:none;"></div></div>' +
            '<div class="q-hotel-field q-hotel-field-narrow">' +
            '<label class="q-label">Rooms</label>' +
            '<input type="number" min="0" class="form-control form-control-sm h-rooms" value="' + esc(d.rooms) + '"></div>' +
            '<div class="q-hotel-field q-hotel-combo q-hotel-field-narrow q-hotel-field-meal">' +
            '<label class="q-label">Meal</label>' +
            '<input type="text" class="form-control form-control-sm h-meal" value="' + esc(d.meal_plan) + '" autocomplete="off" placeholder="CP / MAP / AP">' +
            '<div class="q-hotel-menu q-hotel-meal-menu" style="display:none;"></div></div>' +
            '<div class="q-hotel-field q-hotel-field-narrow">' +
            '<label class="q-label">Nts</label>' +
            '<input type="number" min="0" class="form-control form-control-sm h-nights" value="' + esc(d.nights) + '"></div>' +
            '<div class="q-hotel-field">' +
            '<label class="q-label">Check In</label>' +
            '<input type="date" class="form-control form-control-sm h-checkin" value="' + esc(d.checkin) + '"></div>' +
            '<div class="q-hotel-field">' +
            '<label class="q-label">Check Out</label>' +
            '<input type="date" class="form-control form-control-sm h-checkout" value="' + esc(d.checkout) + '"></div>' +
            '</div>' +
            '<div class="row q-row-tight align-items-end q-hotel-rate-row">' +
            '<div class="col-md-2">' +
            '<div class="form-group mb-0">' +
            '<label class="q-label">Rate</label>' +
            '<input type="number" min="0" step="1" inputmode="numeric" class="form-control form-control-sm h-rate" value="' + esc(d.rate) + '">' +
            '</div></div>' +
            '<div class="col-md-4 col-lg-3">' +
            '<div class="form-group mb-0">' +
            '<label class="q-label">Supplier</label>' +
            '<select class="form-control form-control-sm h-supplier">' +
            hotelSupplierOptionsHtml(d.supplier_id, d.supplier, { allowCreate: true }) +
            '</select></div></div>' +
            '</div></div>';
    }

    function hotelCategoryPanelHtml(cat) {
        cat = cat || {};
        var id = cat.id || nextHotelCategoryId();
        var label = cat.label || defaultHotelCategoryLabel(0);
        return '' +
            '<div class="q-hotel-category" data-cat-id="' + esc(id) + '">' +
            '<div class="q-hotel-category-hd">' +
            '<input type="hidden" class="q-hotel-cat-label" value="' + esc(label) + '">' +
            '<div class="q-hotel-category-actions">' +
            '<button type="button" class="btn btn-q-primary btn-sm q-add-hotel-in-cat"><i class="fas fa-plus mr-1"></i>Add Hotel</button>' +
            '<button type="button" class="btn btn-outline-danger btn-sm q-remove-hotel-cat" title="Remove option"><i class="fas fa-trash-alt"></i></button>' +
            '</div></div>' +
            '<div class="q-hotel-rows"></div>' +
            '</div>';
    }

    function getHotelCategoryPanels() {
        return $('#qHotelCategories .q-hotel-category');
    }

    function refreshHotelCategoryTabs() {
        renumberHotelCategoryLabels();
        var $tabs = $('#qHotelCatTabs').empty();
        var $panels = getHotelCategoryPanels();
        var canAddOption = $panels.length < Q_MAX_HOTEL_OPTIONS;
        $('#qAddHotelCategory')
            .prop('disabled', !canAddOption)
            .toggle(canAddOption)
            .attr('title', canAddOption ? 'Add pricing option' : 'Maximum ' + Q_MAX_HOTEL_OPTIONS + ' options allowed');
        if (!$panels.length) {
            return;
        }
        var activeExists = false;
        $panels.each(function () {
            if (String($(this).attr('data-cat-id') || '') === String(qActiveHotelCategoryId || '')) {
                activeExists = true;
                return false;
            }
        });
        if (!qActiveHotelCategoryId || !activeExists) {
            qActiveHotelCategoryId = String($panels.first().attr('data-cat-id') || '');
        }
        $panels.each(function (idx) {
            var $panel = $(this);
            var id = String($panel.attr('data-cat-id') || '');
            var label = defaultHotelCategoryLabel(idx);
            $panel.find('.q-hotel-cat-label').val(label);
            var $btn = $('<button type="button" class="q-hotel-cat-tab"></button>')
                .attr('data-cat-id', id)
                .text(label);
            if (id === String(qActiveHotelCategoryId || '')) {
                $btn.addClass('is-active');
            }
            $tabs.append($btn);
        });
        $panels.removeClass('is-active');
        $panels.filter(function () {
            return String($(this).attr('data-cat-id') || '') === String(qActiveHotelCategoryId || '');
        }).addClass('is-active');
        var canRemove = $panels.length > 1;
        $panels.each(function () {
            $(this).find('.q-remove-hotel-cat').prop('disabled', !canRemove).toggle(canRemove);
        });
    }

    function setActiveHotelCategory(catId) {
        qActiveHotelCategoryId = String(catId || '');
        refreshHotelCategoryTabs();
        renderPricingSheets();
    }

    function collectHotelsFromPanel($panel) {
        var out = [];
        $panel.find('.q-hotel-rows .q-hotel-row').each(function () {
            var $r = $(this);
            var hotelId = parseInt($r.find('.h-hotel-id').val(), 10) || 0;
            var checkin = $r.find('.h-checkin').val();
            var checkout = $r.find('.h-checkout').val();
            var rate = $r.find('.h-rate').val();
            var supplierId = parseInt($r.find('.h-supplier').val(), 10) || 0;
            var $hSup = $r.find('.h-supplier');
            var supplierName = $.trim($hSup.find('option:selected').attr('data-name') || $hSup.find('option:selected').text() || '');
            if (!supplierId || supplierName.indexOf('Create new') === 0 || supplierName === 'Select supplier') {
                supplierName = '';
                supplierId = 0;
            }
            out.push({
                city: $r.find('.h-city').val(),
                city_id: $r.find('.h-city-id').val(),
                hotel_id: hotelId > 0 ? hotelId : '',
                name: $r.find('.h-name').val(),
                room_type: $r.find('.h-room').val(),
                rooms: $r.find('.h-rooms').val(),
                meal_plan: $r.find('.h-meal').val(),
                nights: $r.find('.h-nights').val(),
                checkin: checkin,
                checkout: checkout,
                check_in: checkin,
                check_out: checkout,
                rate: rate,
                amount: rate,
                supplier_id: supplierId > 0 ? supplierId : '',
                supplier: supplierName,
                is_manual: hotelId > 0 ? 0 : 1
            });
        });
        return out;
    }

    function collectHotelCategories() {
        renumberHotelCategoryLabels();
        var categories = [];
        getHotelCategoryPanels().each(function (idx) {
            var $panel = $(this);
            var id = String($panel.attr('data-cat-id') || nextHotelCategoryId());
            var label = defaultHotelCategoryLabel(idx);
            $panel.find('.q-hotel-cat-label').val(label);
            categories.push({
                id: id,
                label: label,
                hotels: collectHotelsFromPanel($panel)
            });
        });
        if (!categories.length) {
            categories.push({
                id: nextHotelCategoryId(),
                label: defaultHotelCategoryLabel(0),
                hotels: []
            });
        }
        var activeId = qActiveHotelCategoryId;
        if (!activeId || !categories.some(function (c) { return c.id === activeId; })) {
            activeId = categories[0].id;
        }
        return {
            categories: categories,
            active_category_id: activeId
        };
    }

    function collectHotels() {
        var data = collectHotelCategories();
        var active = data.categories.find(function (c) { return c.id === data.active_category_id; });
        return active ? (active.hotels || []) : [];
    }

    function normalizeHotelsPrefill(raw) {
        if (Array.isArray(raw)) {
            return {
                categories: [{
                    id: 'opt_1',
                    label: defaultHotelCategoryLabel(0),
                    hotels: raw
                }],
                active_category_id: 'opt_1'
            };
        }
        if (raw && typeof raw === 'object' && Array.isArray(raw.categories)) {
            var cats = raw.categories.slice(0, Q_MAX_HOTEL_OPTIONS).map(function (cat, idx) {
                return {
                    id: cat.id || ('opt_' + (idx + 1)),
                    label: defaultHotelCategoryLabel(idx),
                    hotels: Array.isArray(cat.hotels) ? cat.hotels : []
                };
            });
            if (!cats.length) {
                cats.push({ id: 'opt_1', label: defaultHotelCategoryLabel(0), hotels: [] });
            }
            var activeId = raw.active_category_id || cats[0].id;
            if (!cats.some(function (c) { return c.id === activeId; })) {
                activeId = cats[0].id;
            }
            return { categories: cats, active_category_id: activeId };
        }
        return {
            categories: [{ id: 'opt_1', label: defaultHotelCategoryLabel(0), hotels: [] }],
            active_category_id: 'opt_1'
        };
    }

    function renderHotelCategories(rawHotels) {
        var data = normalizeHotelsPrefill(rawHotels);
        var $wrap = $('#qHotelCategories').empty();
        qHotelCategorySeq = 1;
        data.categories.forEach(function (cat) {
            bumpHotelIdSeqFromCategory(cat);
        });
        data.categories.forEach(function (cat, idx) {
            var $panel = $(hotelCategoryPanelHtml({
                id: cat.id || nextHotelCategoryId(),
                label: defaultHotelCategoryLabel(idx)
            }));
            bumpHotelIdSeqFromCategory({
                id: $panel.attr('data-cat-id')
            });
            (cat.hotels || []).forEach(function (h) {
                var $row = $(hotelRowHtml(h));
                $panel.find('.q-hotel-rows').append($row);
                if (typeof initHotelRow === 'function') {
                    initHotelRow($row);
                }
                qInitSupplierSelect2($row.find('.h-supplier'), { placeholder: 'Select supplier' });
            });
            $wrap.append($panel);
        });
        qActiveHotelCategoryId = data.active_category_id || String($wrap.find('.q-hotel-category').first().attr('data-cat-id') || '');
        refreshHotelCategoryTabs();
        renderPricingSheets();
    }

    function ensureHotelCategoriesReady() {
        if (!getHotelCategoryPanels().length) {
            renderHotelCategories([]);
        }
    }

    function addHotelCategory(label) {
        ensureHotelCategoriesReady();
        renumberHotelCategoryLabels();
        if (getHotelCategoryPanels().length >= Q_MAX_HOTEL_OPTIONS) {
            refreshHotelCategoryTabs();
            return null;
        }
        syncHotelIdSeqFromPanels();
        var id = nextHotelCategoryId();
        var nextLabel = label || nextHotelCategoryLabel();
        var $panel = $(hotelCategoryPanelHtml({
            id: id,
            label: nextLabel
        }));
        $('#qHotelCategories').append($panel);
        qActiveHotelCategoryId = id;
        refreshHotelCategoryTabs();
        renderPricingSheets();
        return $panel;
    }

    function getHotelRowCache($row) {
        try {
            return JSON.parse($row.attr('data-hotel-cache') || '{}') || {};
        } catch (e) {
            return {};
        }
    }

    function setHotelRowCache($row, cache) {
        $row.attr('data-hotel-cache', JSON.stringify(cache || {}));
    }

    function getQuotationDestinationFilter() {
        var name = ($('[name=destination]').val() || '').trim();
        var map = (typeof window.Q_DESTINATION_NAME_TO_ID === 'object' && window.Q_DESTINATION_NAME_TO_ID)
            ? window.Q_DESTINATION_NAME_TO_ID
            : {};
        var id = 0;
        if (name) {
            if (map[name] != null) {
                id = parseInt(map[name], 10) || 0;
            }
            if (!id && map[name.toLowerCase()] != null) {
                id = parseInt(map[name.toLowerCase()], 10) || 0;
            }
            if (!id) {
                var lower = name.toLowerCase();
                Object.keys(map).some(function (key) {
                    if (String(key).toLowerCase() === lower) {
                        id = parseInt(map[key], 10) || 0;
                        return true;
                    }
                    return false;
                });
            }
        }
        return { name: name, id: id };
    }

    function withDestinationParams(params) {
        params = params || {};
        var dest = getQuotationDestinationFilter();
        if (dest.id > 0) {
            params.destination_id = dest.id;
        }
        if (dest.name) {
            params.destination = dest.name;
        }
        return params;
    }

    function hideHotelMenus($row) {
        if ($row && $row.length) {
            $row.find('.q-hotel-menu').hide().empty();
        } else {
            $('.q-hotel-menu').hide().empty();
        }
    }

    function fetchQuotationHotelCities(q, callback) {
        $.getJSON(qHotelSearchUrl, withDestinationParams({ mode: 'cities', q: q || '' })).done(function (res) {
            callback((res && res.success && res.cities) ? res.cities : []);
        }).fail(function () { callback([]); });
    }

    function fetchCityMasterCities(q, callback) {
        var dest = getQuotationDestinationFilter();
        var params = { q: q || '', limit: 25 };
        if (dest.id > 0) {
            params.destination_id = dest.id;
        }
        $.getJSON('crm/ajax/search_cities.php', params).done(function (res) {
            var list = [];
            if (res && res.success) {
                list = res.cities || res.data || [];
            }
            callback(list);
        }).fail(function () { callback([]); });
    }

    function resolveDestinationCountryId() {
        var dest = getQuotationDestinationFilter();
        var map = (typeof window.Q_DESTINATION_COUNTRY_ID_BY_NAME === 'object' && window.Q_DESTINATION_COUNTRY_ID_BY_NAME)
            ? window.Q_DESTINATION_COUNTRY_ID_BY_NAME
            : {};
        if (!dest.name) {
            return 0;
        }
        var key = String(dest.name).toLowerCase();
        if (map[key] != null) {
            return parseInt(map[key], 10) || 0;
        }
        var found = 0;
        Object.keys(map).some(function (k) {
            if (String(k).toLowerCase() === key) {
                found = parseInt(map[k], 10) || 0;
                return true;
            }
            return false;
        });
        return found;
    }

    var qCityCreateTargetRow = null;

    function resetQCityCreateForm() {
        var $form = $('#qCityCreateForm');
        if (!$form.length) {
            return;
        }
        $form.find('#qCityCreateError').addClass('d-none').text('');
        $form.find('#qCityCreateName').val('');
        $form.find('#qCityCreateCountry').val('');
        $form.find('#qCityCreateState').empty().append('<option value="">Select State (optional)</option>');
        $form.find('#qCityCreateSubmit').prop('disabled', false);
    }

    function loadQCityCreateStates(countryId, selectedStateId) {
        var $state = $('#qCityCreateState');
        $state.empty().append('<option value="">Select State (optional)</option>');
        if (!countryId) {
            return $.Deferred().resolve().promise();
        }
        return $.getJSON('../ajax/get_states_by_country.php', { country_id: countryId }).done(function (res) {
            var rows = (res && res.data) ? res.data : [];
            rows.forEach(function (item) {
                var $opt = $('<option></option>').val(item.id).text(item.state_name);
                if (selectedStateId && String(item.id) === String(selectedStateId)) {
                    $opt.prop('selected', true);
                }
                $state.append($opt);
            });
        });
    }

    function openQCityCreateModal(prefillName, $row) {
        var $modal = $('#qCityCreateModal');
        if (!$modal.length) {
            return;
        }
        qCityCreateTargetRow = $row && $row.length ? $row : null;
        resetQCityCreateForm();
        $('#qCityCreateName').val(String(prefillName || '').trim());
        var countryId = resolveDestinationCountryId();
        if (countryId > 0) {
            $('#qCityCreateCountry').val(String(countryId));
            loadQCityCreateStates(countryId);
        }
        if (!$modal.parent().is('body')) {
            $modal.appendTo('body');
        }
        $modal.modal('show');
    }

    function applyCreatedCityToRow($row, cityId, cityName) {
        if (!$row || !$row.length) {
            return;
        }
        $row.find('.h-city-id').val(cityId || '');
        $row.find('.h-city').val(cityName || '');
        $row.find('.h-hotel-id').val('');
        hideHotelMenus($row);
        if ($.trim($row.find('.h-name').val())) {
            showHotelNameSuggestions($row);
        }
        if (typeof saveFormDraftToStorage === 'function') {
            saveFormDraftToStorage();
        }
    }

    function saveQCityCreateForm() {
        var $form = $('#qCityCreateForm');
        var $error = $('#qCityCreateError');
        var $submit = $('#qCityCreateSubmit');
        var countryId = parseInt($('#qCityCreateCountry').val(), 10) || 0;
        var cityName = $.trim($('#qCityCreateName').val());
        $error.addClass('d-none').text('');

        if (countryId <= 0) {
            $error.removeClass('d-none').text('Please select a country.');
            return;
        }
        if (!cityName) {
            $error.removeClass('d-none').text('City name is required.');
            return;
        }

        var fd = new FormData();
        fd.append('country_id', String(countryId));
        fd.append('city_name', cityName);
        fd.append('is_active', '1');
        var stateId = $('#qCityCreateState').val();
        if (stateId) {
            fd.append('state_id', String(stateId));
        }

        $submit.prop('disabled', true);
        $.ajax({
            url: 'crm/ajax/save_city.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success || !res.id) {
                $error.removeClass('d-none').text((res && res.message) ? res.message : 'Could not create city.');
                return;
            }
            applyCreatedCityToRow(qCityCreateTargetRow, res.id, cityName);
            $('#qCityCreateModal').modal('hide');
        }).fail(function (xhr) {
            var message = 'Could not create city.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            $error.removeClass('d-none').text(message);
        }).always(function () {
            $submit.prop('disabled', false);
        });
    }

    function appendCityCreateAction($menu, typed) {
        $menu.append('<div class="q-hotel-menu-divider"></div>');
        $menu.append(
            $('<button type="button" class="q-hotel-menu-item q-hotel-menu-item-create q-hotel-city-create"></button>')
                .attr('data-name', typed || '')
                .html('<i class="fas fa-plus-circle mr-2 text-primary"></i>Create' +
                    (typed ? ' "' + esc(typed) + '"' : ''))
        );
    }

    function showHotelCitySuggestions($row) {
        var $input = $row.find('.h-city');
        var $menu = $row.find('.q-hotel-city-menu');
        var typed = $.trim($input.val());
        var dest = getQuotationDestinationFilter();

        if (!dest.name) {
            $menu.empty().append(
                '<div class="q-hotel-menu-empty">Set Destination in Tour Information (Step 1) to see cities from City Master</div>'
            );
            appendCityCreateAction($menu, typed);
            $menu.show();
            return;
        }

        fetchCityMasterCities(typed, function (cities) {
            $menu.empty();
            if (!cities.length) {
                if (typed) {
                    $menu.append(
                        '<div class="q-hotel-menu-empty">No cities found for "' + esc(typed) + '"</div>'
                    );
                } else {
                    $menu.append(
                        '<div class="q-hotel-menu-empty">No cities in City Master' +
                        (dest.name ? ' for ' + esc(dest.name) : '') + '</div>'
                    );
                }
            } else {
                cities.forEach(function (c) {
                    var label = c.name;
                    var sub = [c.state_name, c.country_name].filter(Boolean).join(', ');
                    var $btn = $('<button type="button" class="q-hotel-menu-item q-hotel-city-pick"></button>')
                        .attr('data-id', c.id)
                        .attr('data-name', c.name);
                    $btn.append($('<span></span>').text(label));
                    if (sub) {
                        $btn.append($('<span class="q-hotel-menu-sub"></span>').text(sub));
                    }
                    $menu.append($btn);
                });
            }
            appendCityCreateAction($menu, typed);
            $menu.show();
        });
    }

    function fetchQuotationHotelsSearch(q, cityId, callback) {
        var params = withDestinationParams({ mode: 'search', q: q || '' });
        if (cityId > 0) {
            params.city_id = cityId;
        }
        $.getJSON(qHotelSearchUrl, params).done(function (res) {
            callback((res && res.success && res.hotels) ? res.hotels : []);
        }).fail(function () { callback([]); });
    }

    function firstHotelRoomType(hotel) {
        var rooms = (hotel && hotel.room_types) ? hotel.room_types : [];
        for (var i = 0; i < rooms.length; i++) {
            if (rooms[i] && rooms[i].type) {
                return rooms[i];
            }
        }
        return null;
    }

    function firstHotelMealPlan(hotel) {
        var meals = (hotel && hotel.meal_plans) ? hotel.meal_plans : [];
        for (var i = 0; i < meals.length; i++) {
            if (meals[i] && meals[i].name) {
                return meals[i];
            }
        }
        return null;
    }

    function filterHotelListByQuery(items, query, getLabel) {
        var q = String(query || '').trim().toLowerCase();
        var list = Array.isArray(items) ? items.slice() : [];
        if (!q) {
            return list;
        }
        return list.filter(function (item) {
            var label = String(getLabel(item) || '').toLowerCase();
            return label.indexOf(q) >= 0;
        });
    }

    function ensureHotelMasterDetails($row, callback) {
        var cache = getHotelRowCache($row);
        var hotel = cache.selectedHotel || null;
        var hotelId = parseInt($row.find('.h-hotel-id').val(), 10) || 0;
        if (!hotelId && hotel && hotel.id) {
            hotelId = parseInt(hotel.id, 10) || 0;
        }

        var hasDetails = !!(hotel && ((hotel.room_types && hotel.room_types.length) || (hotel.meal_plans && hotel.meal_plans.length)));
        if (hasDetails) {
            callback(hotel);
            return;
        }
        if (hotelId <= 0) {
            callback(null);
            return;
        }

        $.getJSON('crm/ajax/get_hotel.php', { id: hotelId })
            .done(function (res) {
                if (res && res.success && res.hotel) {
                    var mapped = {
                        id: res.hotel.id,
                        city_id: res.hotel.city_id,
                        city_name: res.hotel.city_name,
                        hotel_name: res.hotel.hotel_name,
                        star_category: res.hotel.star_category,
                        room_types: res.hotel.room_types || [],
                        meal_plans: res.hotel.meal_plans || []
                    };
                    cache = getHotelRowCache($row);
                    cache.selectedHotel = mapped;
                    setHotelRowCache($row, cache);
                    callback(mapped);
                    return;
                }
                callback(hotel);
            })
            .fail(function () {
                callback(hotel);
            });
    }

    function applyHotelMasterToRow($row, hotel, opts) {
        if (!hotel) {
            return;
        }
        opts = opts || {};
        var preserve = !!opts.preserveFields;
        var forceFill = !!opts.forceFill;

        $row.find('.h-hotel-id').val(hotel.id || '');
        $row.find('.h-name').val(hotel.hotel_name || '');
        if (hotel.city_name) {
            $row.find('.h-city').val(hotel.city_name);
        }
        if (hotel.city_id) {
            $row.find('.h-city-id').val(hotel.city_id);
        }

        var room = firstHotelRoomType(hotel);
        if (room && (forceFill || !preserve || !$.trim($row.find('.h-room').val()))) {
            if (forceFill || !$.trim($row.find('.h-room').val())) {
                $row.find('.h-room').val(room.type || '');
            }
            var price = parseFloat(room.price);
            if (!isNaN(price) && price > 0 && (forceFill || !$.trim($row.find('.h-rate').val()))) {
                $row.find('.h-rate').val(Math.round(price));
            }
        }

        var meal = firstHotelMealPlan(hotel);
        if (meal && (forceFill || !$.trim($row.find('.h-meal').val()))) {
            $row.find('.h-meal').val(meal.name || 'CP');
        } else if (!$.trim($row.find('.h-meal').val())) {
            $row.find('.h-meal').val('CP');
        }

        var cache = getHotelRowCache($row);
        cache.selectedHotel = hotel;
        setHotelRowCache($row, cache);
    }

    function showHotelRoomSuggestions($row) {
        var $menu = $row.find('.q-hotel-room-menu');
        var typed = $.trim($row.find('.h-room').val());
        var hotelId = parseInt($row.find('.h-hotel-id').val(), 10) || 0;

        ensureHotelMasterDetails($row, function (hotel) {
            $menu.empty();
            if (!hotelId && !(hotel && hotel.id)) {
                $menu.append('<div class="q-hotel-menu-empty">Select a hotel from Hotel Master first</div>').show();
                return;
            }
            var rooms = filterHotelListByQuery(hotel && hotel.room_types ? hotel.room_types : [], typed, function (r) {
                return r.type || '';
            });
            if (!rooms.length) {
                if (typed) {
                    $menu.append('<div class="q-hotel-menu-empty">No matching room type — keep "' + esc(typed) + '"</div>');
                } else {
                    $menu.append('<div class="q-hotel-menu-empty">No room types in Hotel Master for this hotel</div>');
                }
            } else {
                rooms.forEach(function (r, idx) {
                    var label = r.type || ('Room ' + (idx + 1));
                    var sub = '';
                    if (r.description) {
                        sub = r.description;
                    }
                    if (r.price !== undefined && r.price !== null && r.price !== '') {
                        sub += (sub ? ' · ' : '') + 'Rate ' + Math.round(parseFloat(r.price) || 0);
                    }
                    var $btn = $('<button type="button" class="q-hotel-menu-item q-hotel-room-pick"></button>')
                        .attr('data-index', idx)
                        .data('room', r);
                    $btn.append($('<span></span>').text(label));
                    if (sub) {
                        $btn.append($('<span class="q-hotel-menu-sub"></span>').text(sub));
                    }
                    $menu.append($btn);
                });
            }
            $menu.show();
        });
    }

    function showHotelMealSuggestions($row) {
        var $menu = $row.find('.q-hotel-meal-menu');
        var typed = $.trim($row.find('.h-meal').val());
        var hotelId = parseInt($row.find('.h-hotel-id').val(), 10) || 0;

        ensureHotelMasterDetails($row, function (hotel) {
            $menu.empty();
            if (!hotelId && !(hotel && hotel.id)) {
                $menu.append('<div class="q-hotel-menu-empty">Select a hotel from Hotel Master first</div>').show();
                return;
            }
            var meals = filterHotelListByQuery(hotel && hotel.meal_plans ? hotel.meal_plans : [], typed, function (m) {
                return m.name || '';
            });
            if (!meals.length) {
                if (typed) {
                    $menu.append('<div class="q-hotel-menu-empty">No matching meal plan — keep "' + esc(typed) + '"</div>');
                } else {
                    $menu.append('<div class="q-hotel-menu-empty">No meal plans in Hotel Master for this hotel</div>');
                }
            } else {
                meals.forEach(function (m, idx) {
                    var label = m.name || ('Meal ' + (idx + 1));
                    var sub = m.description || '';
                    if (m.price !== undefined && m.price !== null && m.price !== '') {
                        sub += (sub ? ' · ' : '') + '$' + (parseFloat(m.price) || 0);
                    }
                    var $btn = $('<button type="button" class="q-hotel-menu-item q-hotel-meal-pick"></button>')
                        .attr('data-index', idx)
                        .data('meal', m);
                    $btn.append($('<span></span>').text(label));
                    if (sub) {
                        $btn.append($('<span class="q-hotel-menu-sub"></span>').text(sub));
                    }
                    $menu.append($btn);
                });
            }
            $menu.show();
        });
    }

    function appendHotelCreateAction($menu, typed) {
        $menu.append('<div class="q-hotel-menu-divider"></div>');
        $menu.append(
            $('<button type="button" class="q-hotel-menu-item q-hotel-menu-item-create q-hotel-name-create"></button>')
                .attr('data-name', typed || '')
                .html('<i class="fas fa-plus-circle mr-2 text-primary"></i>Create' +
                    (typed ? ' "' + esc(typed) + '"' : ''))
        );
    }

    function showHotelNameSuggestions($row) {
        var $menu = $row.find('.q-hotel-name-menu');
        var typed = $.trim($row.find('.h-name').val());
        var cityId = parseInt($row.find('.h-city-id').val(), 10) || 0;
        var dest = getQuotationDestinationFilter();

        if (!dest.name) {
            $menu.empty().append(
                '<div class="q-hotel-menu-empty">Set Destination in Tour Information (Step 1) to see hotels from Hotel Master</div>'
            );
            appendHotelCreateAction($menu, typed);
            $menu.show();
            return;
        }

        fetchQuotationHotelsSearch(typed, cityId, function (hotels) {
            $menu.empty();
            var cache = getHotelRowCache($row);
            cache.hotels = hotels;
            setHotelRowCache($row, cache);

            if (!hotels.length) {
                if (typed) {
                    $menu.append(
                        '<div class="q-hotel-menu-empty">No hotels found for "' + esc(typed) + '"</div>'
                    );
                } else {
                    $menu.append(
                        '<div class="q-hotel-menu-empty">Hotels for ' + esc(dest.name) + ' from Hotel Master — type to filter</div>'
                    );
                }
            } else {
                hotels.forEach(function (h) {
                    var label = h.hotel_name + (parseInt(h.is_default, 10) === 1 ? ' (Default)' : '');
                    var sub = (h.city_name || '') + (h.star_category ? ' · ' + h.star_category : '');
                    var $btn = $('<button type="button" class="q-hotel-menu-item q-hotel-name-pick"></button>')
                        .attr('data-id', h.id);
                    $btn.append($('<span></span>').text(label));
                    if (sub) {
                        $btn.append($('<span class="q-hotel-menu-sub"></span>').text(sub));
                    }
                    $menu.append($btn);
                });
            }
            appendHotelCreateAction($menu, typed);
            $menu.show();
        });
    }

    var qHotelCreateTargetRow = null;

    function resetQHotelCreateForm() {
        var $form = $('#qHotelCreateForm');
        if (!$form.length) {
            return;
        }
        $('#qHotelCreateError').addClass('d-none').text('');
        $('#qHotelCreateDestName').val('');
        $('#qHotelCreateDestId').val('');
        $('#qHotelCreateCityName').val('');
        $('#qHotelCreateCityId').val('');
        $('#qHotelCreateName').val('');
        $('#qHotelCreateStar').val('3 Star');
        $('#qHotelCreateRoom').val('');
        $('#qHotelCreateMeal').val('CP');
        $('#qHotelCreateRate').val('');
        $('#qHotelCreateSubmit').prop('disabled', false);
    }

    function openQHotelCreateModal(prefillName, $row) {
        var $modal = $('#qHotelCreateModal');
        if (!$modal.length) {
            return;
        }
        qHotelCreateTargetRow = $row && $row.length ? $row : null;
        resetQHotelCreateForm();

        var dest = getQuotationDestinationFilter();
        $('#qHotelCreateDestName').val(dest.name || '');
        $('#qHotelCreateDestId').val(dest.id > 0 ? String(dest.id) : '');

        var cityId = 0;
        var cityName = '';
        if ($row && $row.length) {
            cityId = parseInt($row.find('.h-city-id').val(), 10) || 0;
            cityName = $.trim($row.find('.h-city').val());
            $('#qHotelCreateName').val(String(prefillName || $.trim($row.find('.h-name').val()) || '').trim());
            $('#qHotelCreateRoom').val($.trim($row.find('.h-room').val()));
            $('#qHotelCreateMeal').val($.trim($row.find('.h-meal').val()) || 'CP');
            $('#qHotelCreateRate').val($.trim($row.find('.h-rate').val()));
        } else {
            $('#qHotelCreateName').val(String(prefillName || '').trim());
        }
        $('#qHotelCreateCityId').val(cityId > 0 ? String(cityId) : '');
        $('#qHotelCreateCityName').val(cityName);

        if (!$modal.parent().is('body')) {
            $modal.appendTo('body');
        }
        $modal.modal('show');
    }

    function saveQHotelCreateForm() {
        var $error = $('#qHotelCreateError');
        var $submit = $('#qHotelCreateSubmit');
        var destId = parseInt($('#qHotelCreateDestId').val(), 10) || 0;
        var cityId = parseInt($('#qHotelCreateCityId').val(), 10) || 0;
        var hotelName = $.trim($('#qHotelCreateName').val());
        var starCategory = $.trim($('#qHotelCreateStar').val()) || '3 Star';
        var roomType = $.trim($('#qHotelCreateRoom').val());
        var mealPlan = $.trim($('#qHotelCreateMeal').val()) || 'CP';
        var rate = parseFloat($('#qHotelCreateRate').val());
        if (isNaN(rate) || rate < 0) {
            rate = 0;
        }

        $error.addClass('d-none').text('');

        if (destId <= 0) {
            $error.removeClass('d-none').text('Set Destination in Tour Information (Step 1) first.');
            return;
        }
        if (cityId <= 0) {
            $error.removeClass('d-none').text('Select or create a city on the hotel row first, then create the hotel.');
            return;
        }
        if (!hotelName) {
            $error.removeClass('d-none').text('Hotel name is required.');
            return;
        }

        var fd = new FormData();
        fd.append('destination', String(destId));
        fd.append('city_id', String(cityId));
        fd.append('hotel_name', hotelName);
        fd.append('star_category', starCategory);
        fd.append('star_rating', '0');
        fd.append('default_hotel', '0');
        fd.append('room_types[0][type]', roomType || 'Standard');
        fd.append('room_types[0][description]', '');
        fd.append('room_types[0][price]', String(rate));
        fd.append('meal_plans[0][name]', mealPlan);
        fd.append('meal_plans[0][description]', '');
        fd.append('meal_plans[0][price]', '0');

        $submit.prop('disabled', true);
        $.ajax({
            url: 'crm/ajax/save_hotel.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success || !res.id) {
                $error.removeClass('d-none').text((res && res.message) ? res.message : 'Could not create hotel.');
                return;
            }
            var hotelId = parseInt(res.id, 10) || 0;
            var finishApply = function (hotel) {
                if (qHotelCreateTargetRow && qHotelCreateTargetRow.length && hotel) {
                    applyHotelMasterToRow(qHotelCreateTargetRow, hotel, { forceFill: true });
                    if (typeof saveFormDraftToStorage === 'function') {
                        saveFormDraftToStorage();
                    }
                }
                $('#qHotelCreateModal').modal('hide');
            };
            if (hotelId > 0) {
                $.getJSON('crm/ajax/get_hotel.php', { id: hotelId }).done(function (detail) {
                    if (detail && detail.success && detail.hotel) {
                        finishApply(detail.hotel);
                    } else {
                        finishApply({
                            id: hotelId,
                            hotel_name: hotelName,
                            city_id: cityId,
                            city_name: $('#qHotelCreateCityName').val(),
                            destination_id: destId,
                            star_category: starCategory,
                            room_types: [{ type: roomType || 'Standard', description: '', price: rate }],
                            meal_plans: [{ name: mealPlan, description: '', price: 0 }]
                        });
                    }
                }).fail(function () {
                    finishApply({
                        id: hotelId,
                        hotel_name: hotelName,
                        city_id: cityId,
                        city_name: $('#qHotelCreateCityName').val(),
                        destination_id: destId,
                        star_category: starCategory,
                        room_types: [{ type: roomType || 'Standard', description: '', price: rate }],
                        meal_plans: [{ name: mealPlan, description: '', price: 0 }]
                    });
                });
            } else {
                $error.removeClass('d-none').text('Could not create hotel.');
            }
        }).fail(function (xhr) {
            var message = 'Could not create hotel.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            $error.removeClass('d-none').text(message);
        }).always(function () {
            $submit.prop('disabled', false);
        });
    }

    function initHotelRow($row, callback) {
        var hotelId = parseInt($row.find('.h-hotel-id').val(), 10) || 0;
        if (hotelId > 0) {
            if (callback) callback();
            return;
        }

        var hotelName = $.trim($row.find('.h-name').val());
        var cityId = parseInt($row.find('.h-city-id').val(), 10) || 0;
        var cityName = $.trim($row.find('.h-city').val());

        var finishMatch = function () {
            if (!hotelName) {
                if (callback) callback();
                return;
            }
            fetchQuotationHotelsSearch(hotelName, cityId, function (hotels) {
                var found = hotels.find(function (h) {
                    return String(h.hotel_name || '').toLowerCase() === hotelName.toLowerCase();
                });
                if (found) {
                    applyHotelMasterToRow($row, found, { preserveFields: true });
                }
                if (callback) callback();
            });
        };

        if (cityId > 0) {
            finishMatch();
            return;
        }
        if (cityName) {
            fetchCityMasterCities(cityName, function (cities) {
                var exact = cities.find(function (c) {
                    return String(c.name).toLowerCase() === cityName.toLowerCase();
                });
                if (exact) {
                    $row.find('.h-city-id').val(exact.id);
                    cityId = exact.id;
                }
                finishMatch();
            });
            return;
        }
        if (callback) callback();
    }

    /* ------------------------------------------------------------------ */
    /* Summernote rich editor (local — works offline)                      */
    /* ------------------------------------------------------------------ */
    function quotationSummernoteToolbar() {
        return [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
            ['fontname', ['fontname']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link', 'picture', 'table', 'hr']],
            ['view', ['fullscreen', 'codeview']]
        ];
    }

    function initQuotationSummernote($ta, height) {
        if (!$ta || !$ta.length || !$.fn.summernote) {
            return;
        }
        if ($ta.data('summernote')) {
            return;
        }
        $ta.summernote({
            height: height || 200,
            toolbar: quotationSummernoteToolbar(),
            dialogsInBody: true
        });
    }

    function destroyQuotationSummernote($root) {
        if (!$root || !$root.length || !$.fn.summernote) {
            return;
        }
        $root.find('textarea').each(function () {
            var $ta = $(this);
            if ($.fn.summernote && $ta.data('summernote')) {
                try {
                    $ta.summernote('destroy');
                } catch (e) { /* ignore */ }
            }
        });
        $root.find('.note-editor').remove();
    }

    function readSummernoteHtml($ta) {
        if (!$ta || !$ta.length) {
            return '';
        }
        if ($.fn.summernote && $ta.data('summernote')) {
            return $ta.summernote('code') || '';
        }
        return $ta.val() || '';
    }

    /* ------------------------------------------------------------------ */
    /* Itinerary (auto day cards with Summernote)                          */
    /* ------------------------------------------------------------------ */
    var itineraryEditorIds = [];
    var rebuildItinerarySeq = 0;
    var nightsRebuildTimer = null;
    var itineraryRebuildSuspended = 0;
    var itineraryPreserveSeed = [];

    function suspendItineraryRebuild() {
        itineraryRebuildSuspended += 1;
    }

    function resumeItineraryRebuild() {
        itineraryRebuildSuspended = Math.max(0, itineraryRebuildSuspended - 1);
    }

    function normalizeItineraryDay(day) {
        day = day || {};
        var title = day.title || day.caption || '';
        if (!title && day.day) {
            title = 'Day ' + day.day;
        }
        if (!title && day.date) {
            title = String(day.date);
        }
        return {
            title: title,
            description: day.description || day.todo || '',
            image: day.image || day.img || day.image_url || ''
        };
    }

    function normalizeItineraryList(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        return list.map(normalizeItineraryDay);
    }

    function mergeItineraryPreserve(existing, seed) {
        var out = [];
        var maxLen = Math.max(existing.length, seed.length);
        for (var i = 0; i < maxLen; i++) {
            var cur = normalizeItineraryDay(existing[i] || {});
            var saved = normalizeItineraryDay(seed[i] || {});
            out.push({
                title: cur.title || saved.title,
                description: cur.description || saved.description,
                image: cur.image || saved.image
            });
        }
        return out;
    }

    function refreshAllItineraryImagePreviews() {
        $('#qItineraryDays .q-day-card').each(function () {
            var $card = $(this);
            updateDayImagePreview($card, $card.find('.q-day-image').val() || '');
        });
    }

    function destroyItineraryEditors() {
        destroyQuotationSummernote($('#qItineraryDays'));
        itineraryEditorIds = [];
    }

    function initItineraryEditors() {
        $('#qItineraryDays .q-day-textarea').each(function () {
            initQuotationSummernote($(this), 200);
        });
    }

    function collectItineraryMeta() {
        ensureItinerarySupplierRows();
        var suppliers = [];
        $('#qItinerarySupplierRows .q-itin-supplier-row').each(function () {
            var $row = $(this);
            var $sel = $row.find('.q-itin-supplier');
            var supplierVal = String($sel.val() || '').trim();
            if (supplierVal === '__create__') {
                supplierVal = '';
            }
            var supplierId = parseInt(supplierVal, 10) || 0;
            var supplierName = $.trim($sel.find('option:selected').attr('data-name') || $sel.find('option:selected').text() || '');
            if (!supplierId || supplierName.indexOf('Create new') === 0 || supplierName === 'Select supplier') {
                supplierName = '';
                supplierId = 0;
            }
            var rateRaw = $.trim($row.find('.q-itin-rate').val() || '');
            var rate = rateRaw;
            if (rateRaw !== '') {
                var n = parseFloat(rateRaw);
                rate = isNaN(n) ? '' : String(Math.round(n));
            }
            if (!supplierId && rate === '') {
                return;
            }
            suppliers.push({
                supplier_id: supplierId > 0 ? supplierId : '',
                supplier: supplierName,
                rate: rate
            });
        });

        // Legacy single fields = first entry (keeps older readers working).
        var first = suppliers.length ? suppliers[0] : { supplier_id: '', supplier: '', rate: '' };
        return {
            rate: first.rate || '',
            supplier_id: first.supplier_id || '',
            supplier: first.supplier || '',
            suppliers: suppliers
        };
    }

    function normalizeItinerarySupplierEntries(meta) {
        meta = meta || {};
        var list = [];
        if (Array.isArray(meta.suppliers) && meta.suppliers.length) {
            meta.suppliers.forEach(function (item) {
                if (!item) {
                    return;
                }
                var supplierId = String(item.supplier_id || '').trim();
                var supplierName = String(item.supplier || item.supplier_name || '').trim();
                var rate = item.rate !== undefined && item.rate !== '' ? item.rate : '';
                if (rate !== '' && rate != null) {
                    var n = parseFloat(rate);
                    rate = isNaN(n) ? '' : String(Math.round(n));
                } else {
                    rate = '';
                }
                if (!supplierId && !supplierName && rate === '') {
                    return;
                }
                list.push({
                    supplier_id: supplierId,
                    supplier: supplierName,
                    rate: rate
                });
            });
        }
        if (!list.length) {
            var legacyId = String(meta.supplier_id || '').trim();
            var legacyName = String(meta.supplier || meta.supplier_name || '').trim();
            var legacyRate = meta.rate !== undefined && meta.rate !== '' ? meta.rate : (meta.amount || '');
            if (legacyRate !== '' && legacyRate != null) {
                var ln = parseFloat(legacyRate);
                legacyRate = isNaN(ln) ? '' : String(Math.round(ln));
            } else {
                legacyRate = '';
            }
            if (legacyId || legacyName || legacyRate !== '') {
                list.push({
                    supplier_id: legacyId,
                    supplier: legacyName,
                    rate: legacyRate
                });
            }
        }
        if (!list.length) {
            list.push({ supplier_id: '', supplier: '', rate: '' });
        }
        return list;
    }

    function applyItineraryMeta(meta) {
        meta = meta || {};
        var entries = normalizeItinerarySupplierEntries(meta);
        var $wrap = $('#qItinerarySupplierRows');
        if (!$wrap.length) {
            return;
        }
        $wrap.find('.q-itin-supplier').each(function () {
            qDestroySupplierSelect2($(this));
        });
        $wrap.empty();
        entries.forEach(function (entry) {
            if (entry.supplier_id) {
                upsertHotelSupplierInList(parseInt(entry.supplier_id, 10) || 0, entry.supplier || ('Supplier #' + entry.supplier_id));
            }
            addItinerarySupplierRow(entry);
        });
        refreshItinerarySupplierRemoveState();
    }

    function snapshotItinerary() {
        $('#qItineraryDays .q-day-textarea').each(function () {
            var $ta = $(this);
            if ($.fn.summernote && $ta.data('summernote')) {
                try {
                    $ta.val($ta.summernote('code'));
                } catch (e) { /* ignore */ }
            }
        });

        var data = [];
        $('#qItineraryDays .q-day-card').each(function () {
            var $c = $(this);
            var html = readSummernoteHtml($c.find('.q-day-textarea'));
            var imageVal = $c.find('.q-day-image').val() || '';
            if (!imageVal) {
                var previewSrc = $c.find('.q-img-preview').attr('src') || '';
                if (previewSrc && previewSrc.indexOf('data:') !== 0) {
                    var adminBase = ADMIN_BASE.replace(/\/+$/, '');
                    if (previewSrc.indexOf(adminBase) === 0) {
                        imageVal = previewSrc.substring(adminBase.length).replace(/^\/+/, '');
                    } else if (previewSrc.indexOf('uploads/quotations/') >= 0) {
                        imageVal = previewSrc.substring(previewSrc.indexOf('uploads/quotations/'));
                    }
                }
            }
            data.push({
                title: $c.find('.q-day-title').val() || '',
                description: html,
                image: imageVal
            });
        });
        itineraryPreserveSeed = normalizeItineraryList(data);
        return data;
    }

    function rebuildItinerary(preserve) {
        var baseDate = $('#q_tentative_date').val();
        var nights = parseInt($('#q_nights').val(), 10);
        if (isNaN(nights) || nights < 0) nights = 0;
        var totalDays = nights + 1;
        if (Array.isArray(preserve) && preserve.length > totalDays) {
            totalDays = preserve.length;
        }

        var existing;
        if (Array.isArray(preserve)) {
            existing = normalizeItineraryList(preserve);
            itineraryPreserveSeed = existing.slice();
        } else {
            existing = snapshotItinerary();
            if (itineraryPreserveSeed.length) {
                existing = mergeItineraryPreserve(existing, itineraryPreserveSeed);
            }
        }
        destroyItineraryEditors();
        $('#qItineraryDays').empty();
        itineraryEditorIds = [];

        var $wrap = $('#qItineraryDays');
        var rebuildToken = Date.now();
        for (var i = 0; i < totalDays; i++) {
            var editorId = 'q_itin_day_' + rebuildToken + '_' + i;
            itineraryEditorIds.push(editorId);
            var prev = normalizeItineraryDay(existing[i] || {});
            var dateLabel = fmtDayDate(baseDate, i);
            var heading = (dateLabel ? dateLabel + ' - ' : '') + 'Day ' + (i + 1);
            var imgVal = prev.image || '';
            var dayBodyId = 'qDayBody_' + rebuildToken + '_' + i;
            var $card = $(
                '<div class="q-day-card" data-day-index="' + i + '" data-editor-id="' + editorId + '">' +
                '<div class="q-day-head q-accordion-head collapsed" data-target="#' + dayBodyId + '" role="button" tabindex="0" aria-expanded="false">' +
                '<div class="q-day-head-main">' +
                '<i class="fas fa-chevron-down toggle-icon" aria-hidden="true"></i>' +
                '<span class="q-day-head-label"></span>' +
                '</div>' +
                '<button type="button" class="btn btn-sm q-day-ai-suggest" title="AI Suggest this day">' +
                '<i class="fas fa-magic mr-1"></i>Suggest Day' +
                '</button>' +
                '</div>' +
                '<div class="q-day-body q-accordion-body" id="' + dayBodyId + '" style="display:none;">' +
                '<div class="row">' +
                '<div class="col-md-6">' +
                '<div class="form-group"><label class="q-label">Title</label>' +
                '<div class="q-day-title-row">' +
                '<input type="text" class="form-control q-day-title" placeholder="Day title">' +
                '<button type="button" class="btn q-day-ai-btn" title="AI Suggest this day" aria-label="AI Suggest this day">' +
                '<i class="fas fa-magic"></i>' +
                '</button>' +
                '</div></div>' +
                '<div class="form-group mb-md-0"><label class="q-label">What to do</label>' +
                '<textarea class="form-control q-day-textarea"></textarea></div>' +
                '</div>' +
                '<div class="col-md-6">' +
                '<div class="form-group mb-0 q-day-image-col">' +
                '<label class="q-label d-block">Image</label>' +
                '<div class="q-day-image-actions">' +
                '<button type="button" class="btn btn-q-primary btn-sm q-choose-image"><i class="fas fa-upload mr-1"></i>Upload</button>' +
                '<button type="button" class="btn btn-outline-primary btn-sm q-search-day-image"><i class="fas fa-search mr-1"></i>Search online</button>' +
                '<button type="button" class="btn btn-outline-secondary btn-sm q-clear-day-image" title="Remove image"><i class="fas fa-times"></i></button>' +
                '</div>' +
                '<div class="q-img-preview-wrap">' +
                '<img class="q-img-preview" alt="Day image preview">' +
                '<div class="q-img-preview-empty text-muted small">No image selected</div>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '<input type="hidden" class="q-day-image">' +
                '</div></div>'
            );
            $card.find('.q-day-head-label').text(heading);
            $card.find('.q-day-title').val(prev.title || '');
            $card.find('.q-day-textarea').attr('id', editorId).val(prev.description || '');
            $card.find('.q-day-image').val(imgVal);
            updateDayImagePreview($card, imgVal);
            $wrap.append($card);
        }

        rebuildItinerarySeq += 1;
        var seq = rebuildItinerarySeq;
        window.setTimeout(function () {
            if (seq !== rebuildItinerarySeq) {
                return;
            }
            if ($('#qSectionBody4').is(':visible')) {
                initItineraryEditors();
            }
        }, 50);
    }

    function scheduleItineraryRebuild() {
        if (itineraryRebuildSuspended > 0) {
            return;
        }
        clearTimeout(nightsRebuildTimer);
        nightsRebuildTimer = setTimeout(function () {
            rebuildItinerary();
        }, 200);
    }

    function setRichEditorValue(id, html) {
        var $ta = $('#' + id);
        if (!$ta.length || html === undefined || html === null || html === '') {
            return;
        }
        if ($.fn.summernote && $ta.data('summernote')) {
            $ta.summernote('code', html);
        } else {
            $ta.val(html);
        }
    }

    function loadTermsFromMaster() {
        var master = (typeof QUOTATION_TERMS_MASTER === 'object' && QUOTATION_TERMS_MASTER) ? QUOTATION_TERMS_MASTER : {};
        richEditors.forEach(function (field) {
            var html = master[field] || '';
            var $ta = $('#qed_' + field);
            if (!$ta.length) {
                return;
            }
            if ($.fn.summernote && $ta.data('summernote')) {
                $ta.summernote('code', html);
            } else {
                $ta.val(html);
            }
        });
    }

    function plainTextToHtml(text) {
        text = (text || '').toString();
        if (text.indexOf('<') >= 0) {
            return text;
        }
        return text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n').map(function (line) {
            return esc(line);
        }).join('<br>');
    }

    function applyPackageItinerary(pkg) {
        if (!pkg) {
            return;
        }

        var nights = parseInt(pkg.duration_nights, 10);
        if (isNaN(nights) || nights < 0) {
            nights = 0;
        }
        if (Array.isArray(pkg.itinerary) && pkg.itinerary.length > 1) {
            nights = Math.max(nights, pkg.itinerary.length - 1);
        }
        if (nights > 0) {
            $('#q_nights').val(nights);
        }

        if (pkg.destination && !($('[name=destination]').val() || '').trim()) {
            $('[name=destination]').val(pkg.destination);
        }

        if (parseFloat(pkg.sale_price) > 0 && rawNumber('.q-cost[data-key="land"]') <= 0) {
            $('.q-cost[data-key="land"]').val(parseFloat(pkg.sale_price).toFixed(2));
        }

        if (pkg.inclusion && !readSummernoteHtml($('#qed_inclusion')).trim()) {
            setRichEditorValue('qed_inclusion', plainTextToHtml(pkg.inclusion));
        }
        if (pkg.exclusion && !readSummernoteHtml($('#qed_exclusion')).trim()) {
            setRichEditorValue('qed_exclusion', plainTextToHtml(pkg.exclusion));
        }

        $('#q_without_itinerary').prop('checked', false);
        rebuildItinerary(pkg.itinerary || []);
        recalcCosts();

        expandWizardSection(4);
        window.setTimeout(initItineraryEditors, 120);

        $('#qAlert').html(
            '<div class="alert alert-success">Itinerary loaded from package <strong>' + esc(pkg.title || 'Package') + '</strong>.</div>'
        );
        window.scrollTo(0, 0);
    }

    /* ------------------------------------------------------------------ */
    /* Package itinerary suggest                                           */
    /* ------------------------------------------------------------------ */
    var packageLookupTimer = null;
    var packageLookupSeq = 0;
    var packageLookupCache = {};
    var selectedPackageForItinerary = null;

    function hideAllPackageMenus() {
        $('.js-q-package-menu').hide().empty();
    }

    function renderPackageMenu($menu, items, query) {
        $menu.empty();
        if (!items || !items.length) {
            $menu.append('<div class="q-lead-empty">No packages found' + (query ? ' for "' + esc(query) + '"' : '') + '</div>');
        } else {
            items.forEach(function (item) {
                var $btn = $('<button type="button" class="q-lead-item"></button>');
                $btn.append($('<span class="q-lead-item-title"></span>').text(item.label || item.title || 'Package'));
                if (item.sub_label) {
                    $btn.append($('<span class="q-lead-item-meta"></span>').text(item.sub_label));
                }
                $btn.data('package', item);
                $menu.append($btn);
            });
        }
        $menu.show();
    }

    function searchPackagesForQuotation(query, callback) {
        var q = (query || '').trim();
        var cacheKey = q || '__all__';
        if (packageLookupCache[cacheKey]) {
            callback(packageLookupCache[cacheKey]);
            return;
        }
        var seq = ++packageLookupSeq;
        $.getJSON(absUrl('crm/ajax/search_packages_for_quotation.php'), { q: q, limit: 12 })
            .done(function (res) {
                if (seq !== packageLookupSeq) {
                    return;
                }
                var items = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                packageLookupCache[cacheKey] = items;
                callback(items);
            })
            .fail(function () {
                if (seq !== packageLookupSeq) {
                    return;
                }
                callback([]);
            });
    }

    function initPackageSuggest() {
        $(document).on('input focus', '.js-q-package-search', function () {
            var $input = $(this);
            var $menu = $input.closest('.q-lead-combobox').find('.js-q-package-menu');
            var query = ($input.val() || '').trim();

            hideAllPackageMenus();
            clearTimeout(packageLookupTimer);
            packageLookupTimer = setTimeout(function () {
                searchPackagesForQuotation(query, function (items) {
                    renderPackageMenu($menu, items, query);
                });
            }, 220);
        });

        $(document).on('click', '.js-q-package-menu .q-lead-item', function () {
            var pkg = $(this).data('package');
            if (!pkg) {
                return;
            }
            selectedPackageForItinerary = pkg;
            $('.js-q-package-search').val(pkg.label || pkg.title || '');
            hideAllPackageMenus();
            $('#qApplyPackageItinerary').prop('disabled', false);
        });

        $(document).on('click', function (e) {
            if ($(e.target).closest('.q-lead-combobox').length) {
                return;
            }
            hideAllPackageMenus();
        });

        $('#qApplyPackageItinerary').on('click', function () {
            if (!selectedPackageForItinerary || !selectedPackageForItinerary.id) {
                alert('Please select a package first.');
                return;
            }

            var existing = snapshotItinerary();
            var hasContent = existing.some(function (day) {
                return ((day.title || '').trim() !== '') || ((day.description || '').replace(/<[^>]*>/g, '').trim() !== '');
            });
            if (hasContent && !window.confirm('Replace the current itinerary with the selected package itinerary?')) {
                return;
            }

            var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Loading...');
            $.getJSON(absUrl('crm/ajax/get_package_for_quotation.php'), { id: selectedPackageForItinerary.id })
                .done(function (res) {
                    if (res && res.success && res.package) {
                        applyPackageItinerary(res.package);
                    } else {
                        alert((res && res.message) || 'Could not load package itinerary.');
                    }
                })
                .fail(function () {
                    alert('Could not load package itinerary.');
                })
                .always(function () {
                    $btn.prop('disabled', false).html('<i class="fas fa-suitcase-rolling mr-1"></i>Apply Itinerary');
                });
        });
    }

    /* ------------------------------------------------------------------ */
    /* AI itinerary suggest                                                */
    /* ------------------------------------------------------------------ */
    function refreshAiItineraryMeta() {
        var $meta = $('#qAiItineraryMeta');
        if (!$meta.length) {
            return;
        }
        var dest = ($('[name=destination]').val() || '').trim();
        var nights = parseInt($('#q_nights').val(), 10);
        if (isNaN(nights) || nights < 0) {
            nights = 0;
        }
        var adults = parseInt($('#q_adults').val(), 10) || 1;
        var children = parseInt($('#q_children').val(), 10) || 0;
        var days = nights + 1;
        var chips = [];

        if (dest) {
            chips.push('<span class="q-ai-chip"><i class="fas fa-map-marker-alt"></i>' + esc(dest) + '</span>');
        } else {
            chips.push('<span class="q-ai-chip"><i class="fas fa-exclamation-circle"></i>Set destination on Step 1</span>');
        }
        if (days > 0) {
            chips.push('<span class="q-ai-chip"><i class="far fa-calendar-alt"></i>' + days + ' day' + (days !== 1 ? 's' : '') + ' / ' + nights + ' night' + (nights !== 1 ? 's' : '') + '</span>');
        } else {
            chips.push('<span class="q-ai-chip"><i class="fas fa-exclamation-circle"></i>Set nights on Step 1</span>');
        }
        chips.push('<span class="q-ai-chip"><i class="fas fa-users"></i>' + adults + ' adult' + (adults !== 1 ? 's' : '') +
            (children > 0 ? ', ' + children + ' child' + (children !== 1 ? 'ren' : '') : '') + '</span>');

        $meta.html(chips.join(''));
    }

    var pendingAIItinerary = null;

    function hideAIItineraryPreview() {
        pendingAIItinerary = null;
        var $preview = $('#qAiItineraryPreview');
        $preview.removeClass('is-visible is-new').hide();
        $('#qAiItineraryPreviewDays').empty();
        $('#qAiItineraryPreviewSub').text('');
        $('#qAiItineraryBadge').removeClass('is-previous is-new').empty();
    }

    function showAIItineraryPreview(itinerary, info) {
        info = info || {};
        pendingAIItinerary = {
            itinerary: itinerary,
            info: info
        };

        var fromPrevious = !!info.from_previous;
        var $preview = $('#qAiItineraryPreview');
        var $badge = $('#qAiItineraryBadge');
        var dest = ($('[name=destination]').val() || '').trim() || 'your destination';

        $preview.toggleClass('is-new', !fromPrevious);
        if (fromPrevious) {
            $('#qAiItineraryPreviewTitle').text('Previous itinerary match');
            $badge.removeClass('is-new').addClass('is-previous')
                .html('<i class="fas fa-history"></i> From previous itinerary');
            var sub = info.message || ('Matched previous plan for ' + dest + '.');
            if (info.match_label) {
                sub = (info.match_type === 'package' ? 'Package: ' : 'Quotation: ') + info.match_label;
            }
            $('#qAiItineraryPreviewSub').text(sub);
        } else {
            $('#qAiItineraryPreviewTitle').text('New generated suggestion');
            $badge.removeClass('is-previous').addClass('is-new')
                .html('<i class="fas fa-exclamation-circle"></i> New suggestion — not from a previous itinerary');
            $('#qAiItineraryPreviewSub').text(
                'No saved itinerary matched this destination and nights. Review the generated plan before applying.'
            );
        }

        var $list = $('#qAiItineraryPreviewDays').empty();
        (itinerary || []).forEach(function (day, idx) {
            var title = ((day && day.title) || '').trim() || ('Day ' + (idx + 1));
            $list.append(
                '<li><span class="day-num">Day ' + (idx + 1) + '</span><span>' + esc(title) + '</span></li>'
            );
        });

        $preview.addClass('is-visible').show();
        try {
            $preview[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (e) { /* ignore */ }
    }

    function applyAIItinerary(itinerary, info) {
        if (!Array.isArray(itinerary) || !itinerary.length) {
            alert('No itinerary days were returned.');
            return;
        }

        $('#q_without_itinerary').prop('checked', false);
        rebuildItinerary(itinerary);
        recalcCosts();

        window.setTimeout(function () {
            initItineraryEditors();
        }, 80);

        var source = (info && info.source) ? info.source : 'instant';
        var fromPrevious = !!(info && info.from_previous);
        var alertClass = fromPrevious ? 'alert-success' : 'alert-warning';
        var msg = '<div class="alert ' + alertClass + '">';
        if (fromPrevious) {
            msg += '<i class="fas fa-check-circle mr-1"></i> ';
            msg += 'Applied previous itinerary for <strong>' + esc(($('[name=destination]').val() || '').trim() || 'your destination') + '</strong>.';
            if (info && info.match_label) {
                msg += ' <span class="badge badge-success ml-1">' + esc(
                    (info.match_type === 'package' ? 'Package: ' : 'Quotation: ') + info.match_label
                ) + '</span>';
            }
        } else {
            msg += '<span class="badge badge-danger mr-2"><i class="fas fa-exclamation-circle"></i> New suggestion</span> ';
            if (source === 'ai') {
                msg += 'Applied AI-generated itinerary for <strong>' + esc(($('[name=destination]').val() || '').trim() || 'your destination') + '</strong> (not from a previous itinerary).';
            } else {
                msg += 'Applied generated itinerary for <strong>' + esc(($('[name=destination]').val() || '').trim() || 'your destination') + '</strong> (not from a previous itinerary).';
            }
        }
        if (info && info.message && fromPrevious) {
            msg += ' <small class="d-block mt-1 text-muted">' + esc(info.message) + '</small>';
        }
        msg += '</div>';
        $('#qAlert').html(msg);
        hideAIItineraryPreview();
        window.scrollTo(0, 0);
    }

    function initAISuggestItinerary() {
        refreshAiItineraryMeta();
        hideAIItineraryPreview();

        $(document).on('input change', '[name=destination], #q_nights, #q_adults, #q_children', function () {
            refreshAiItineraryMeta();
            hideAIItineraryPreview();
        });

        $('#qDismissAIItinerary').on('click', function () {
            hideAIItineraryPreview();
        });

        $('#qApplyAIItinerary').on('click', function () {
            if (!pendingAIItinerary || !Array.isArray(pendingAIItinerary.itinerary)) {
                alert('Generate a suggestion first.');
                return;
            }

            var existing = snapshotItinerary();
            var hasContent = existing.some(function (day) {
                return ((day.title || '').trim() !== '') || ((day.description || '').replace(/<[^>]*>/g, '').trim() !== '');
            });
            if (hasContent && !window.confirm('Replace the current itinerary with this suggestion?')) {
                return;
            }

            applyAIItinerary(pendingAIItinerary.itinerary, pendingAIItinerary.info || {});
        });

        $('#qSuggestAIItinerary').on('click', function () {
            var dest = ($('[name=destination]').val() || '').trim();
            var nights = parseInt($('#q_nights').val(), 10);
            if (isNaN(nights) || nights < 0) {
                nights = 0;
            }

            if (!dest) {
                alert('Please enter a destination on the Guest & Tour step first.');
                return;
            }
            if (nights < 1) {
                alert('Please set No of Nights (at least 1) on the Guest & Tour step.');
                return;
            }

            var $btn = $(this).prop('disabled', true).html('<i class="fas fa-bolt mr-1"></i> Generating...');
            hideAIItineraryPreview();

            $.ajax({
                url: absUrl('crm/ajax/ai_suggest_itinerary.php'),
                type: 'POST',
                dataType: 'json',
                data: {
                    destination: dest,
                    nights: nights,
                    adults: parseInt($('#q_adults').val(), 10) || 2,
                    children: parseInt($('#q_children').val(), 10) || 0,
                    start_date: $('#q_tentative_date').val() || '',
                    notes: ($('#qAiItineraryNotes').val() || '').trim(),
                    exclude_quotation_id: parseInt($('#q_id').val(), 10) || 0
                }
            }).done(function (res) {
                if (res && res.success && Array.isArray(res.itinerary)) {
                    showAIItineraryPreview(res.itinerary, {
                        source: res.source,
                        message: res.message || '',
                        from_previous: !!res.from_previous,
                        is_new_suggestion: !!res.is_new_suggestion || !res.from_previous,
                        match_type: res.match_type || '',
                        match_label: res.match_label || '',
                        match_id: res.match_id || 0
                    });
                } else {
                    alert((res && res.message) ? res.message : 'Could not generate itinerary.');
                }
            }).fail(function (xhr) {
                var msg = 'Could not generate itinerary.';
                try {
                    var j = JSON.parse(xhr.responseText);
                    if (j.message) {
                        msg = j.message;
                    }
                } catch (e) { /* ignore */ }
                alert(msg);
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="fas fa-bolt mr-1"></i> Generate Itinerary Now');
            });
        });
    }

    function applyAIDaySuggestion($card, day) {
        if (!$card || !$card.length || !day) {
            return;
        }
        var title = ((day.title || '') + '').trim();
        var description = day.description || '';
        if (title) {
            $card.find('.q-day-title').val(title);
        }
        var $ta = $card.find('.q-day-textarea');
        if ($.fn.summernote && $ta.data('summernote')) {
            $ta.summernote('code', description || '');
        } else {
            $ta.val(description || '');
        }
        itineraryPreserveSeed = snapshotItinerary();
    }

    var dayAiTargetCard = null;

    function openDayAiModal($card) {
        if (!$card || !$card.length) {
            return;
        }
        expandWizardSection(4);
        expandDayCard($card);

        var dest = ($('[name=destination]').val() || '').trim();
        var nights = parseInt($('#q_nights').val(), 10);
        if (isNaN(nights) || nights < 0) {
            nights = 0;
        }
        if (!dest) {
            alert('Please enter a destination on the Guest & Tour step first.');
            return;
        }
        if (nights < 1) {
            alert('Please set No of Nights (at least 1) on the Guest & Tour step.');
            return;
        }

        var dayIndex = parseInt($card.attr('data-day-index'), 10);
        if (isNaN(dayIndex) || dayIndex < 0) {
            dayIndex = $('#qItineraryDays .q-day-card').index($card);
        }

        dayAiTargetCard = $card;
        var dayLabel = $card.find('.q-day-head-label').text() || ('Day ' + (dayIndex + 1));
        $('#qDayAiModalSub').text(dayLabel + ' — describe what you want for this day');
        $('#qDayAiPrompt').val('');
        $('#qDayAiGenerate').prop('disabled', false).html('<i class="fas fa-bolt mr-1"></i> Generate');
        $('#qDayAiModal').modal('show');
        window.setTimeout(function () {
            $('#qDayAiPrompt').trigger('focus');
        }, 350);
    }

    function requestAIDaySuggestion($card, userPrompt) {
        if (!$card || !$card.length) {
            return;
        }

        var dest = ($('[name=destination]').val() || '').trim();
        var nights = parseInt($('#q_nights').val(), 10);
        if (isNaN(nights) || nights < 0) {
            nights = 0;
        }
        var dayIndex = parseInt($card.attr('data-day-index'), 10);
        if (isNaN(dayIndex) || dayIndex < 0) {
            dayIndex = $('#qItineraryDays .q-day-card').index($card);
        }

        if (!dest) {
            alert('Please enter a destination on the Guest & Tour step first.');
            return;
        }
        if (nights < 1) {
            alert('Please set No of Nights (at least 1) on the Guest & Tour step.');
            return;
        }

        userPrompt = (userPrompt || '').trim();
        if (!userPrompt) {
            alert('Please write what you need for this day.');
            $('#qDayAiPrompt').trigger('focus');
            return;
        }

        var existingTitle = ($card.find('.q-day-title').val() || '').trim();
        var existingDesc = (readSummernoteHtml($card.find('.q-day-textarea')) || '').replace(/<[^>]*>/g, '').trim();
        if ((existingTitle || existingDesc) && !window.confirm('Replace Day ' + (dayIndex + 1) + ' with this suggestion?')) {
            return;
        }

        var $modalBtn = $('#qDayAiGenerate').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Generating...');
        var $btns = $card.find('.q-day-ai-suggest, .q-day-ai-btn').prop('disabled', true);
        $card.find('.q-day-ai-suggest').html('<i class="fas fa-spinner fa-spin mr-1"></i>Suggesting...');

        $.ajax({
            url: absUrl('crm/ajax/ai_suggest_itinerary_day.php'),
            type: 'POST',
            dataType: 'json',
            data: {
                destination: dest,
                nights: nights,
                adults: parseInt($('#q_adults').val(), 10) || 2,
                children: parseInt($('#q_children').val(), 10) || 0,
                notes: userPrompt,
                existing_title: existingTitle,
                day_index: dayIndex
            }
        }).done(function (res) {
            if (res && res.success && res.day) {
                applyAIDaySuggestion($card, res.day);
                $('#qDayAiModal').modal('hide');
            } else {
                alert((res && res.message) ? res.message : 'Could not generate this day.');
            }
        }).fail(function (xhr) {
            var msg = 'Could not generate this day.';
            try {
                var j = JSON.parse(xhr.responseText);
                if (j.message) {
                    msg = j.message;
                }
            } catch (e) { /* ignore */ }
            alert(msg);
        }).always(function () {
            $modalBtn.prop('disabled', false).html('<i class="fas fa-bolt mr-1"></i> Generate');
            $btns.prop('disabled', false);
            $card.find('.q-day-ai-suggest').html('<i class="fas fa-magic mr-1"></i>Suggest Day');
        });
    }

    function initAISuggestDay() {
        $(document).on('click', '.q-day-ai-suggest, .q-day-ai-btn', function (e) {
            e.preventDefault();
            openDayAiModal($(this).closest('.q-day-card'));
        });

        $('#qDayAiGenerate').on('click', function () {
            if (!dayAiTargetCard || !dayAiTargetCard.length) {
                alert('Please select a day first.');
                return;
            }
            requestAIDaySuggestion(dayAiTargetCard, $('#qDayAiPrompt').val());
        });

        $('#qDayAiPrompt').on('keydown', function (e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                $('#qDayAiGenerate').trigger('click');
            }
        });

        $('#qDayAiModal').on('hidden.bs.modal', function () {
            dayAiTargetCard = null;
            $('#qDayAiPrompt').val('');
            $('#qDayAiGenerate').prop('disabled', false).html('<i class="fas fa-bolt mr-1"></i> Generate');
        });
    }

    /* ------------------------------------------------------------------ */
    /* Image picker (uploads to server)                                    */
    /* ------------------------------------------------------------------ */
    function updateDayImagePreview($card, url) {
        var $wrap = $card.find('.q-img-preview-wrap');
        var $img = $card.find('.q-img-preview');
        var $empty = $card.find('.q-img-preview-empty');
        if (url) {
            $img.attr('src', absUrl(url)).show();
            $empty.hide();
            $wrap.addClass('has-image');
        } else {
            $img.attr('src', '').hide();
            $empty.show();
            $wrap.removeClass('has-image');
        }
    }

    window.qUpdateDayImagePreview = updateDayImagePreview;
    window.qQuotationAbsUrl = absUrl;

    var $imgInput = $('<input type="file" accept="image/*" style="display:none">').appendTo('body');
    var $imgTargetCard = null;

    $(document).on('click', '.q-choose-image', function () {
        $imgTargetCard = $(this).closest('.q-day-card');
        $imgInput.val('').trigger('click');
    });

    $imgInput.on('change', function () {
        if (!this.files || !this.files[0] || !$imgTargetCard) return;
        var fd = new FormData();
        fd.append('image', this.files[0]);
        var $card = $imgTargetCard;
        $.ajax({
            url: 'crm/ajax/upload_quotation_image.php',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (res && res.success && res.url) {
                $card.find('.q-day-image').val(res.url);
                updateDayImagePreview($card, res.url);
            } else {
                alert((res && res.message) || 'Upload failed.');
            }
        }).fail(function () {
            alert('Upload failed.');
        });
    });

    /* ------------------------------------------------------------------ */
    /* Custom cost rows + multi option pricing sheets                      */
    /* ------------------------------------------------------------------ */
    var qPricingOptionsState = {};

    function customCostRowHtml(data) {
        data = data || {};
        return '' +
            '<div class="form-group q-custom-cost mb-1">' +
            '<input type="text" class="form-control form-control-sm cc-label" placeholder="Label" value="' + esc(data.label) + '">' +
            '<div class="q-custom-cost-amt">' +
            '<input type="number" step="0.01" class="form-control form-control-sm cost-input q-cost cc-amount" value="' + esc(data.amount) + '">' +
            '<button type="button" class="btn btn-outline-danger btn-sm q-remove" data-remove=".q-custom-cost" title="Remove"><i class="fas fa-times"></i></button>' +
            '</div></div>';
    }

    function defaultPricingSheetState() {
        return {
            fixed: {
                flight_train: '',
                land: '',
                hotel: '',
                transport: '',
                visa: '',
                travel_insurance: ''
            },
            custom: [],
            user_edited: { flight_train: 0, hotel: 0 },
            profit_percent: '',
            profit_amount: '',
            price_per_adult: '',
            price_per_adult_edited: 0
        };
    }

    function collectSheetStateFromDom($sheet) {
        var fixed = {};
        $sheet.find('.q-cost').each(function () {
            var $c = $(this);
            if ($c.hasClass('cc-amount')) return;
            fixed[String($c.data('key') || '')] = $c.val();
        });
        var custom = [];
        $sheet.find('.q-custom-cost').each(function () {
            custom.push({
                label: $(this).find('.cc-label').val(),
                amount: $(this).find('.cc-amount').val()
            });
        });
        return {
            fixed: fixed,
            custom: custom,
            user_edited: {
                flight_train: $sheet.find('.q-cost[data-key="flight_train"]').attr('data-user-edited') === '1' ? 1 : 0,
                hotel: $sheet.find('.q-cost[data-key="hotel"]').attr('data-user-edited') === '1' ? 1 : 0
            },
            profit_percent: $sheet.find('.q-sheet-profit-percent').val() || '',
            profit_amount: $sheet.find('.q-sheet-profit-amount').val() || '',
            price_per_adult: $sheet.find('.q-sheet-price-per-adult').val() || '',
            price_per_adult_edited: $sheet.find('.q-sheet-price-per-adult').attr('data-user-edited') === '1' ? 1 : 0
        };
    }

    function snapshotPricingSheets() {
        $('#qPricingSheetsHost .q-pricing-option-sheet').each(function () {
            var id = String($(this).attr('data-cat-id') || '');
            if (!id) return;
            qPricingOptionsState[id] = collectSheetStateFromDom($(this));
        });
    }

    function hotelTotalForCategory(cat) {
        var hotelTotal = 0;
        (cat.hotels || []).forEach(function (h) {
            var v = parseFloat(h.rate);
            if (!isNaN(v)) hotelTotal += v;
        });
        return hotelTotal;
    }

    function pricingFixedCostKeys() {
        return [
            { key: 'flight_train', label: 'Flight / Train', icon: 'fas fa-plane' },
            { key: 'land', label: 'Land', icon: 'fas fa-map-marker-alt' },
            { key: 'hotel', label: 'Hotel', icon: 'fas fa-bed' },
            { key: 'transport', label: 'Transport', icon: 'fas fa-car' },
            { key: 'visa', label: 'Visa', icon: 'fas fa-passport' },
            { key: 'travel_insurance', label: 'Insurance', icon: 'fas fa-shield-alt' }
        ];
    }

    function pricingOptionBadge(idx, isActive) {
        if (isActive) {
            return '<span class="q-pricing-option-badge is-selected"><i class="fas fa-check"></i> Selected</span>';
        }
        if (idx === 0) {
            return '<span class="q-pricing-option-badge"><i class="fas fa-wallet"></i> Budget</span>';
        }
        if (idx === 1) {
            return '<span class="q-pricing-option-badge"><i class="fas fa-star"></i> Popular</span>';
        }
        return '';
    }

    function pricingOptionTitle(cat, idx) {
        var base = defaultHotelCategoryLabel(idx);
        var label = String(cat.label || '').trim();
        if (!label || /^Option\s+\d+$/i.test(label) || /^Option\s+0?\d+$/i.test(label)) {
            var stars = idx === 0 ? '3★' : (idx === 1 ? '4★' : 'Hotel');
            return base + ' – ' + stars + ' Hotel';
        }
        return label;
    }

    function pricingOptionColumnHtml(cat, state, idx, maxCustom) {
        cat = cat || {};
        state = state || defaultPricingSheetState();
        var fixed = state.fixed || {};
        var id = cat.id || ('opt_' + (idx + 1));
        var title = pricingOptionTitle(cat, idx);
        var isActive = String(id) === String(qActiveHotelCategoryId);
        maxCustom = Math.max(0, parseInt(maxCustom, 10) || 0);
        var html = '<div class="q-pricing-option-sheet' + (isActive ? ' is-active' : '') + '" data-cat-id="' + esc(id) + '">';
        html += '<div class="q-pricing-option-hd">';
        html += '<div class="q-pricing-option-hd-top">';
        html += '<span class="q-pricing-option-hd-ico"><i class="fas fa-building"></i></span>';
        html += '<div class="q-pricing-option-hd-meta">';
        html += '<h4>' + esc(title) + '</h4>';
        html += '<div class="q-pricing-option-hd-badges">';
        html += pricingOptionBadge(idx, isActive);
        if (!isActive) {
            html += '<button type="button" class="btn q-set-active-pricing" data-cat-id="' + esc(id) + '">Use this option</button>';
        }
        html += '</div></div></div>';
        html += '</div><div class="q-pricing-option-body">';
        pricingFixedCostKeys().forEach(function (row) {
            var synced = (row.key === 'flight_train' || row.key === 'hotel') ? ' q-cost-synced' : '';
            var edited = state.user_edited && parseInt(state.user_edited[row.key], 10) === 1 ? '1' : '0';
            html += '<div class="q-pricing-amount-cell">' +
                '<input type="number" step="0.01" class="form-control form-control-sm cost-input q-cost' + synced + '" data-key="' + row.key + '" value="' + esc(fixed[row.key] != null ? fixed[row.key] : '') + '" data-user-edited="' + edited + '" placeholder="0">' +
                '</div>';
        });
        html += '<div class="q-custom-cost-rows">';
        var customs = state.custom || [];
        var customCount = Math.max(1, maxCustom);
        for (var ci = 0; ci < customCount; ci++) {
            html += customCostRowHtml(customs[ci] || {});
        }
        html += '</div>';
        html += '<div class="q-pricing-amount-cell q-pricing-add-cell">' +
            '<button type="button" class="btn btn-outline-secondary btn-sm q-add-cost-row" title="Add extra cost"><i class="fas fa-plus"></i></button>' +
            '</div>';

        html += '<div class="q-sheet-profit-compact">' +
            '<span class="q-sheet-profit-compact-label">Profit %</span>' +
            '<input type="number" step="0.01" class="q-sheet-profit-percent q-sum-pct" placeholder="0" value="' + esc(state.profit_percent || '') + '" title="Profit %">' +
            '<span class="q-sheet-total-mini q-sum-total" data-display="total">₹ 0</span>' +
            '</div>';
        html += tourCostCardShellHtml();
        html += '<input type="hidden" class="q-sheet-total-cost" value="0">';
        html += '<input type="hidden" class="q-sheet-profit-amount" value="' + esc(state.profit_amount || '') + '">';
        html += '<span class="q-sum-profit d-none" data-display="profit">₹ 0</span>';
        html += '<span class="q-sum-selling d-none" data-display="selling">₹ 0</span>';
        html += '<input type="hidden" class="q-sheet-package-total" value="0">';
        html += '<input type="hidden" class="q-sheet-price-per-adult" value="' + esc(state.price_per_adult || '') + '"' +
            (parseInt(state.price_per_adult_edited, 10) === 1 ? ' data-user-edited="1"' : '') + '>';
        html += '<input type="hidden" class="q-sheet-quotation-total" value="0">';
        html += '<span class="q-sheet-adult-lbl d-none">1</span>';
        html += '</div></div>';
        return html;
    }

    function pricingLabelsColumnHtml(maxCustom) {
        maxCustom = Math.max(1, parseInt(maxCustom, 10) || 1);
        var html = '<div class="q-pricing-labels-col">';
        html += '<div class="q-pricing-labels-hd"></div>';
        html += '<div class="q-pricing-option-body">';
        pricingFixedCostKeys().forEach(function (row) {
            html += '<div class="q-pricing-row-label"><i class="' + row.icon + '" aria-hidden="true"></i><span>' + esc(row.label) + '</span></div>';
        });
        for (var i = 0; i < maxCustom; i++) {
            html += '<div class="q-pricing-row-label q-pricing-custom-label">' +
                (i === 0 ? '<i class="fas fa-ellipsis-h" aria-hidden="true"></i><span>Extra Costs</span>' : '') +
                '</div>';
        }
        html += '<div class="q-pricing-row-label q-pricing-add-label"></div>';
        html += '</div></div>';
        return html;
    }

    function renderPricingSheets() {
        snapshotPricingSheets();
        var data = collectHotelCategories();
        var $host = $('#qPricingSheetsHost');
        if (!$host.length) {
            return;
        }
        $host.empty();
        var cats = data.categories || [];
        if (!cats.length) {
            cats = [{ id: 'opt_1', label: defaultHotelCategoryLabel(0), hotels: [] }];
        }
        if (!qActiveHotelCategoryId || !cats.some(function (c) { return String(c.id) === String(qActiveHotelCategoryId); })) {
            qActiveHotelCategoryId = String(cats[0].id || '');
        }
        $host.css('--q-opt-count', String(cats.length));
        $host.toggleClass('is-single-option', cats.length === 1);
        var maxCustom = 0;
        cats.forEach(function (cat) {
            var st = qPricingOptionsState[cat.id] || defaultPricingSheetState();
            maxCustom = Math.max(maxCustom, (st.custom || []).length);
        });
        $host.append(pricingLabelsColumnHtml(maxCustom));
        cats.forEach(function (cat, idx) {
            var state = qPricingOptionsState[cat.id] || defaultPricingSheetState();
            $host.append(pricingOptionColumnHtml(cat, state, idx, maxCustom));
        });
        Object.keys(qPricingOptionsState).forEach(function (key) {
            if (!cats.some(function (c) { return String(c.id) === String(key); })) {
                delete qPricingOptionsState[key];
            }
        });
        renderTourCostRows();
        recalcCosts();
    }

    function sumNumericFields(selector) {
        var total = 0;
        $(selector).each(function () {
            var v = parseFloat($(this).val());
            if (!isNaN(v)) {
                total += v;
            }
        });
        return total;
    }

    function isSheetCostUserEdited($sheet, key) {
        return $sheet.find('.q-cost[data-key="' + key + '"]').attr('data-user-edited') === '1';
    }

    function syncSheetHotelFromCategory($sheet) {
        var catId = String($sheet.attr('data-cat-id') || '');
        var data = collectHotelCategories();
        var cat = data.categories.find(function (c) { return String(c.id) === catId; });
        if (!cat) return;
        if (!isSheetCostUserEdited($sheet, 'hotel')) {
            var hotelTotal = hotelTotalForCategory(cat);
            var count = (cat.hotels || []).length;
            $sheet.find('.q-cost[data-key="hotel"]').val(count ? hotelTotal.toFixed(2) : '');
        }
    }

    function syncSheetFlightFromServices($sheet) {
        if (!isSheetCostUserEdited($sheet, 'flight_train')) {
            var flightTotal = sumNumericFields('#qFlightRows .f-fare');
            $sheet.find('.q-cost[data-key="flight_train"]').val(
                $('#qFlightRows .q-flight-row').length ? flightTotal.toFixed(2) : ''
            );
        }
    }

    function formatInrDisplay(n) {
        var num = parseFloat(n);
        if (isNaN(num)) num = 0;
        return '₹ ' + money(num);
    }

    /* ------------------------------------------------------------------ */
    /* Tour Cost Pricing card (separate Total Cost)                        */
    /* ------------------------------------------------------------------ */
    var qTourCostState = {
        adult_rate: '',
        adult_rate_edited: 0,
        child_rates: [],
        infant_rate: '',
        gst_percent: 5
    };
    var qTourCostAutoSaveTimer = null;

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function defaultTourCostState() {
        return {
            adult_rate: '',
            adult_rate_edited: 0,
            child_rates: [],
            infant_rate: '',
            gst_percent: 5
        };
    }

    function readGuestCounts() {
        var adults = parseInt($('#q_adults').val(), 10);
        var children = parseInt($('#q_children').val(), 10);
        if (isNaN(adults) || adults < 1) adults = 1;
        if (isNaN(children) || children < 0) children = 0;
        return { adults: adults, children: children };
    }

    function ensureTourCostChildRates(childCount) {
        childCount = Math.max(0, parseInt(childCount, 10) || 0);
        if (!Array.isArray(qTourCostState.child_rates)) {
            qTourCostState.child_rates = [];
        }
        while (qTourCostState.child_rates.length < childCount) {
            qTourCostState.child_rates.push('');
        }
        if (qTourCostState.child_rates.length > childCount) {
            qTourCostState.child_rates = qTourCostState.child_rates.slice(0, childCount);
        }
    }

    function formatTourMoney(n) {
        var num = parseFloat(n);
        if (isNaN(num)) num = 0;
        return 'INR ' + num.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function tourCostCardShellHtml() {
        return '' +
            '<div class="q-tour-cost-card q-sheet-tour-cost" aria-label="Tour Cost Summary">' +
            '<div class="q-tour-cost-hd">' +
            '<div class="q-tour-cost-hd-left">' +
            '<span class="q-tour-cost-hd-ico" aria-hidden="true"><i class="fas fa-suitcase-rolling"></i></span>' +
            '<h4 class="q-tour-cost-title">Tour Cost Summary</h4>' +
            '</div>' +
            '<span class="q-tour-cost-autosave q-sheet-tour-autosave" title="Draft status">' +
            '<i class="fas fa-check"></i> Auto Saved' +
            '</span></div>' +
            '<div class="q-tour-cost-body q-sheet-tour-cost-rows"></div>' +
            '<div class="q-tour-cost-grand-wrap">' +
            '<div class="q-tour-cost-grand">' +
            '<div class="q-tour-cost-grand-left">' +
            '<span class="q-tour-cost-grand-ico" aria-hidden="true"><i class="fas fa-award"></i></span>' +
            '<div class="q-tour-cost-grand-text">' +
            '<span class="q-tour-cost-grand-label">Grand Total</span>' +
            '<span class="q-tour-cost-grand-sub">Total amount to be paid</span>' +
            '</div></div>' +
            '<span class="q-tour-cost-grand-divider" aria-hidden="true"></span>' +
            '<strong class="q-tour-cost-grand-amount q-sheet-tour-grand">INR 0.00</strong>' +
            '</div></div></div>';
    }

    function tourCostRowHtml(opts) {
        opts = opts || {};
        var icon = opts.icon || 'fas fa-user';
        var name = opts.name || '';
        var key = opts.key || '';
        var rate = opts.rate != null ? opts.rate : '';
        var qty = opts.qty != null ? opts.qty : '';
        var amountText = opts.amountText || 'INR 0.00';
        var editable = opts.editable !== false;
        var qtyEditable = !!opts.qtyEditable;
        var summary = !!opts.summary;
        var hideMeta = !!opts.hideMeta;
        var gstEditable = !!opts.gstEditable;
        var gstPct = opts.gstPct != null ? opts.gstPct : 5;

        var metaHtml = '';
        if (!hideMeta && editable) {
            metaHtml =
                '<span class="q-tour-cost-traveller-meta">' +
                '<span class="q-tour-cost-meta-prefix">INR</span>' +
                '<input type="number" step="0.01" min="0" class="form-control q-tour-rate-input q-tour-cost-rate-inline" data-tour-key="' + esc(key) + '" value="' + esc(rate) + '" placeholder="0">' +
                '<span class="q-tour-cost-meta-mul">×</span>' +
                (qtyEditable
                    ? '<input type="number" step="1" min="1" class="form-control q-tour-qty-input q-tour-cost-qty-inline" data-tour-qty="' + esc(key) + '" value="' + esc(qty) + '">'
                    : '<span class="q-tour-cost-meta-qty">' + esc(String(qty)) + '</span>') +
                '</span>';
        }

        var nameHtml;
        if (gstEditable) {
            nameHtml =
                '<span class="q-tour-cost-traveller-name">' +
                'GST @' +
                '<input type="number" step="0.01" min="0" max="100" class="form-control q-tour-gst-input q-tour-cost-gst-inline" value="' + esc(gstPct) + '" aria-label="GST percent">' +
                '%' +
                '</span>';
        } else {
            nameHtml = '<span class="q-tour-cost-traveller-name">' + esc(name) + '</span>';
        }

        var rowClass = 'q-tour-cost-row' + (summary ? ' is-summary' : '') + (gstEditable ? ' q-tour-gst-row' : '');

        return '' +
            '<div class="' + rowClass + '" data-tour-key="' + esc(key) + '">' +
            '<div class="q-tour-cost-traveller">' +
            '<span class="q-tour-cost-avatar" aria-hidden="true"><i class="' + icon + '"></i></span>' +
            '<div class="q-tour-cost-traveller-text">' +
            nameHtml +
            metaHtml +
            '</div></div>' +
            '<div class="q-tour-cost-amount" data-tour-amount="' + esc(key) + '">' + esc(amountText) + '</div>' +
            '</div>';
    }

    function getActivePricingSheet() {
        var $active = $('#qPricingSheetsHost .q-pricing-option-sheet.is-active');
        if (!$active.length) {
            $active = $('#qPricingSheetsHost .q-pricing-option-sheet').first();
        }
        return $active;
    }

    function getTourCostScope($fromEl) {
        if ($fromEl && $fromEl.length) {
            var $sheet = $fromEl.closest('.q-pricing-option-sheet');
            if ($sheet.length) {
                return $sheet.find('.q-sheet-tour-cost-rows');
            }
        }
        var $active = getActivePricingSheet();
        if ($active.length) {
            return $active.find('.q-sheet-tour-cost-rows');
        }
        return $('#qTourCostRows');
    }

    function tourCostRowsPresent() {
        return $('#qPricingSheetsHost .q-sheet-tour-cost-rows .q-tour-cost-row').length > 0 ||
            $('#qTourCostRows .q-tour-cost-row').length > 0;
    }

    function getSheetPackageBase($sheet) {
        var total = 0;
        $sheet.find('.q-cost').each(function () {
            var v = parseFloat($(this).val());
            if (!isNaN(v)) {
                total += v;
            }
        });
        var profit = 0;
        var pct = parseFloat($sheet.find('.q-sheet-profit-percent').val());
        var amt = parseFloat($sheet.find('.q-sheet-profit-amount').val());
        if (!isNaN(amt) && amt > 0) {
            profit = amt;
        } else if (!isNaN(pct) && pct > 0) {
            profit = total * pct / 100;
        }
        return total + profit;
    }

    function getSheetAdultRate($sheet, adults) {
        if (!$sheet || !$sheet.length) {
            return 0;
        }
        adults = adults || readGuestCounts().adults;
        var $ppa = $sheet.find('.q-sheet-price-per-adult');
        if ($ppa.attr('data-user-edited') === '1') {
            var edited = parseFloat($ppa.val());
            if (!isNaN(edited) && edited >= 0) {
                return edited;
            }
        }
        var pkgBase = getSheetPackageBase($sheet);
        if (pkgBase <= 0) {
            return 0;
        }
        var perAdult = adults > 0 ? (pkgBase / adults) : pkgBase;
        return Math.round(perAdult * 100) / 100;
    }

    function buildTourCostRowsHtml(adultRate) {
        var counts = readGuestCounts();
        ensureTourCostChildRates(counts.children);
        var hideGst = $('#q_hide_gst_note').is(':checked');
        var gstPct = hideGst ? 0 : (parseFloat(qTourCostState.gst_percent) || 5);
        var html = '';
        html += tourCostRowHtml({
            key: 'adult',
            icon: 'fas fa-user-friends',
            name: 'Adults',
            rate: adultRate != null ? adultRate : '',
            qty: counts.adults,
            qtyEditable: true,
            amountText: 'INR 0.00'
        });
        if (counts.children > 0) {
            for (var i = 0; i < counts.children; i++) {
                html += tourCostRowHtml({
                    key: 'child_' + i,
                    icon: 'fas fa-child',
                    name: counts.children === 1 ? 'Child' : ('Child ' + pad2(i + 1)),
                    rate: qTourCostState.child_rates[i] || '',
                    qty: 1,
                    amountText: 'INR 0.00'
                });
            }
        }
        html += tourCostRowHtml({
            key: 'infant',
            icon: 'fas fa-baby',
            name: 'Infant',
            rate: qTourCostState.infant_rate,
            qty: 1,
            amountText: 'INR 0.00'
        });
        html += tourCostRowHtml({
            key: 'subtotal',
            icon: 'fas fa-calculator',
            name: 'Subtotal',
            editable: false,
            hideMeta: true,
            summary: true,
            amountText: 'INR 0.00'
        });
        if (!hideGst) {
            html += tourCostRowHtml({
                key: 'gst',
                icon: 'fas fa-percentage',
                name: 'GST',
                editable: false,
                hideMeta: true,
                summary: true,
                gstEditable: true,
                gstPct: gstPct,
                amountText: 'INR 0.00'
            });
        }
        return html;
    }

    function renderTourCostRows() {
        var $sheets = $('#qPricingSheetsHost .q-pricing-option-sheet');
        var adults = readGuestCounts().adults;
        if ($sheets.length) {
            $sheets.each(function () {
                var $sheet = $(this);
                var adultRate = getSheetAdultRate($sheet, adults);
                $sheet.find('.q-sheet-tour-cost-rows').html(buildTourCostRowsHtml(adultRate > 0 ? String(adultRate) : ''));
            });
            return;
        }
        var $host = $('#qTourCostRows');
        if (!$host.length) {
            return;
        }
        var legacyRate = getSheetAdultRate(getActivePricingSheet(), adults);
        if (legacyRate <= 0 && qTourCostState.adult_rate) {
            legacyRate = parseFloat(qTourCostState.adult_rate) || 0;
        }
        $host.html(buildTourCostRowsHtml(legacyRate > 0 ? String(legacyRate) : ''));
    }

    function snapshotTourCostFromDom($fromEl) {
        var counts = readGuestCounts();
        ensureTourCostChildRates(counts.children);
        var $scope = getTourCostScope($fromEl);
        if (!$scope.length) {
            return;
        }
        for (var i = 0; i < counts.children; i++) {
            var $c = $scope.find('.q-tour-rate-input[data-tour-key="child_' + i + '"]');
            if ($c.length) {
                qTourCostState.child_rates[i] = $c.val() || '';
            }
        }
        var $inf = $scope.find('.q-tour-rate-input[data-tour-key="infant"]');
        if ($inf.length) {
            qTourCostState.infant_rate = $inf.val() || '';
        }
        var $gst = $scope.find('.q-tour-gst-input').first();
        if ($gst.length) {
            var g = parseFloat($gst.val());
            if (!isNaN(g) && g >= 0) {
                qTourCostState.gst_percent = g;
            }
        }
    }

    function collectTourCostPayload(adultRateOverride, $scopeEl) {
        snapshotTourCostFromDom($scopeEl);
        var counts = readGuestCounts();
        var adultRate;
        if (adultRateOverride != null && !isNaN(adultRateOverride)) {
            adultRate = adultRateOverride;
        } else {
            var $scope = getTourCostScope($scopeEl);
            adultRate = parseFloat($scope.find('.q-tour-rate-input[data-tour-key="adult"]').val());
            if (isNaN(adultRate)) {
                adultRate = 0;
            }
        }
        var infantRate = parseFloat(qTourCostState.infant_rate);
        if (isNaN(infantRate)) infantRate = 0;
        var childRates = [];
        var childTotal = 0;
        for (var i = 0; i < counts.children; i++) {
            var cr = parseFloat(qTourCostState.child_rates[i]);
            if (isNaN(cr)) cr = 0;
            childRates.push(cr);
            childTotal += cr;
        }
        var adultTotal = adultRate * counts.adults;
        var infantTotal = infantRate > 0 ? infantRate : 0;
        var subtotal = adultTotal + childTotal + infantTotal;
        var hideGst = $('#q_hide_gst_note').is(':checked');
        var gstPct = hideGst ? 0 : (parseFloat(qTourCostState.gst_percent) || 5);
        var gst = subtotal * gstPct / 100;
        var grand = subtotal + gst;
        var $sheet = $scopeEl && $scopeEl.length ? $scopeEl.closest('.q-pricing-option-sheet') : getActivePricingSheet();
        var adultEdited = $sheet.length && $sheet.find('.q-sheet-price-per-adult').attr('data-user-edited') === '1' ? 1 : 0;
        return {
            adult_rate: adultRate > 0 ? String(adultRate) : '',
            adult_rate_edited: adultEdited || qTourCostState.adult_rate_edited || 0,
            child_rates: qTourCostState.child_rates.slice(),
            infant_rate: qTourCostState.infant_rate,
            adults: counts.adults,
            children: counts.children,
            adult_amount: adultTotal,
            child_amount: childTotal,
            infant_amount: infantTotal,
            subtotal: subtotal,
            gst_percent: gstPct,
            gst_amount: gst,
            grand_total: grand,
            hide_gst: hideGst ? 1 : 0
        };
    }

    function updateTourCostDomFromPayload($scope, payload) {
        if (!$scope || !$scope.length || !payload) {
            return;
        }
        var counts = readGuestCounts();
        $scope.find('[data-tour-amount="adult"]').text(formatTourMoney(payload.adult_amount));
        for (var i = 0; i < counts.children; i++) {
            var cr = parseFloat(qTourCostState.child_rates[i]);
            if (isNaN(cr)) cr = 0;
            $scope.find('[data-tour-amount="child_' + i + '"]').text(formatTourMoney(cr));
        }
        $scope.find('[data-tour-amount="infant"]').text(formatTourMoney(payload.infant_amount));
        $scope.find('[data-tour-amount="subtotal"]').text(formatTourMoney(payload.subtotal));
        if (payload.hide_gst) {
            $scope.find('.q-tour-gst-row').hide();
        } else {
            $scope.find('.q-tour-gst-row').show();
            $scope.find('.q-tour-gst-input').each(function () {
                if (!$(this).is(':focus')) {
                    $(this).val(String(payload.gst_percent || 5));
                }
            });
            $scope.find('[data-tour-amount="gst"]').text(formatTourMoney(payload.gst_amount));
        }
    }

    function recalcTourCostForSheet($sheet) {
        if (!$sheet || !$sheet.length) {
            return null;
        }
        var $rows = $sheet.find('.q-sheet-tour-cost-rows');
        if (!$rows.length || !$rows.find('.q-tour-cost-row').length) {
            return null;
        }
        var adults = readGuestCounts().adults;
        var $ppa = $sheet.find('.q-sheet-price-per-adult');
        var $adultInput = $rows.find('.q-tour-rate-input[data-tour-key="adult"]');
        var adultRate;
        if ($ppa.attr('data-user-edited') === '1') {
            if ($adultInput.length && $adultInput.is(':focus')) {
                adultRate = parseFloat($adultInput.val());
            } else {
                adultRate = parseFloat($ppa.val());
                if ((isNaN(adultRate) || adultRate < 0) && $adultInput.length) {
                    adultRate = parseFloat($adultInput.val());
                }
            }
        } else {
            adultRate = getSheetAdultRate($sheet, adults);
            if ($adultInput.length && !$adultInput.is(':focus')) {
                $adultInput.val(adultRate > 0 ? String(adultRate) : '');
            }
            if (!$ppa.is(':focus')) {
                $ppa.val(adultRate > 0 ? String(adultRate) : '');
            }
        }
        if (isNaN(adultRate)) {
            adultRate = 0;
        }
        var payload = collectTourCostPayload(adultRate, $sheet);
        updateTourCostDomFromPayload($rows, payload);
        $sheet.find('.q-sheet-tour-grand').text(formatTourMoney(payload.grand_total));
        $sheet.find('.q-sheet-quotation-total').val(money(payload.grand_total));
        return payload;
    }

    function recalcTourCostCardLegacy() {
        var $host = $('#qTourCostRows');
        if (!$host.length || !$host.find('.q-tour-cost-row').length) {
            return null;
        }
        var payload = collectTourCostPayload(null, null);
        updateTourCostDomFromPayload($host, payload);
        $('#qTourCostGrand').text(formatTourMoney(payload.grand_total));
        return payload;
    }

    function recalcTourCostCard() {
        return recalcAllTourCostCards();
    }

    function recalcAllTourCostCards() {
        var $sheets = $('#qPricingSheetsHost .q-pricing-option-sheet');
        var activePayload = null;
        if ($sheets.length) {
            $sheets.each(function () {
                var $sheet = $(this);
                var payload = recalcTourCostForSheet($sheet);
                if ($sheet.hasClass('is-active')) {
                    activePayload = payload;
                }
            });
            if (!activePayload) {
                activePayload = recalcTourCostForSheet($sheets.first());
            }
        } else {
            activePayload = recalcTourCostCardLegacy();
        }
        if (activePayload) {
            var $active = getActivePricingSheet();
            $('#q_quotation_total').val(money(activePayload.grand_total));
            $('#q_package_total').val(money(activePayload.subtotal));
            $('#q_price_per_adult').val($active.length ? ($active.find('.q-sheet-price-per-adult').val() || '') : (activePayload.adult_rate || ''));
            qTourCostState.adult_rate = activePayload.adult_rate || '';
            $('#q_tour_cost_json').val(JSON.stringify(activePayload));
        }
        return activePayload;
    }

    function syncTourCostAdultRateFromSheet($sheet, force) {
        if (!$sheet || !$sheet.length) {
            $sheet = getActivePricingSheet();
        }
        if (!$sheet.length) {
            return;
        }
        var $ppa = $sheet.find('.q-sheet-price-per-adult');
        if (!force && $ppa.attr('data-user-edited') === '1') {
            return;
        }
        var $input = $sheet.find('.q-sheet-tour-cost-rows .q-tour-rate-input[data-tour-key="adult"]');
        if ($input.length && !$input.is(':focus')) {
            $input.val($ppa.val() || '');
        }
    }

    function markTourCostAutoSaved($badge) {
        if (!$badge || !$badge.length) {
            $badge = getActivePricingSheet().find('.q-sheet-tour-autosave');
        }
        if (!$badge.length) {
            $badge = $('#qTourCostAutoSave');
        }
        if (!$badge.length) {
            return;
        }
        $badge.addClass('is-on');
        if (qTourCostAutoSaveTimer) {
            clearTimeout(qTourCostAutoSaveTimer);
        }
        qTourCostAutoSaveTimer = setTimeout(function () {
            $badge.removeClass('is-on');
        }, 4000);
    }

    function applyTourCostState(state) {
        state = state || {};
        qTourCostState = defaultTourCostState();
        if (state.adult_rate != null) qTourCostState.adult_rate = state.adult_rate;
        if (state.adult_rate_edited != null) qTourCostState.adult_rate_edited = parseInt(state.adult_rate_edited, 10) ? 1 : 0;
        if (Array.isArray(state.child_rates)) qTourCostState.child_rates = state.child_rates.slice();
        if (state.infant_rate != null) qTourCostState.infant_rate = state.infant_rate;
        if (state.gst_percent != null) qTourCostState.gst_percent = state.gst_percent;
        var $active = getActivePricingSheet();
        if ($active.length && state.adult_rate != null) {
            $active.find('.q-sheet-price-per-adult').val(state.adult_rate);
            if (qTourCostState.adult_rate_edited) {
                $active.find('.q-sheet-price-per-adult').attr('data-user-edited', '1');
            }
        }
        renderTourCostRows();
        recalcAllTourCostCards();
    }

    function recalcOnePricingSheet($sheet, adults) {
        syncSheetFlightFromServices($sheet);
        syncSheetHotelFromCategory($sheet);

        var total = 0;
        $sheet.find('.q-cost').each(function () {
            var v = parseFloat($(this).val());
            if (!isNaN(v)) total += v;
        });
        $sheet.find('.q-sheet-total-cost').val(money(total));
        $sheet.find('.q-sum-total').text(formatInrDisplay(total));

        var profit = 0;
        var pct = parseFloat($sheet.find('.q-sheet-profit-percent').val());
        var amt = parseFloat($sheet.find('.q-sheet-profit-amount').val());
        if (!isNaN(amt) && amt > 0) {
            profit = amt;
        } else if (!isNaN(pct) && pct > 0) {
            profit = total * pct / 100;
        }
        var pkgBase = total + profit;
        $sheet.find('.q-sum-profit').text(formatInrDisplay(profit));
        $sheet.find('.q-sheet-adult-lbl').text(adults);
        $('#qPricingSheetsHost .q-matrix-adult-lbl').text(adults);
        $sheet.find('.q-sum-selling').text(formatInrDisplay(pkgBase));
        $sheet.find('.q-sheet-package-total').val(money(pkgBase));

        var $ppa = $sheet.find('.q-sheet-price-per-adult');
        var perAdult;
        if ($ppa.attr('data-user-edited') !== '1') {
            perAdult = adults > 0 ? (pkgBase / adults) : pkgBase;
            perAdult = Math.round(perAdult * 100) / 100;
            $ppa.val(pkgBase > 0 ? perAdult : '');
        } else {
            perAdult = parseFloat($ppa.val());
        }
        if (!isNaN(perAdult) && perAdult > 0) {
            $sheet.find('.q-sheet-quotation-total').val(money(perAdult * adults));
        } else {
            $sheet.find('.q-sheet-quotation-total').val(money(pkgBase));
        }
        if (tourCostRowsPresent() && $sheet.find('.q-sheet-tour-cost-rows .q-tour-cost-row').length) {
            recalcTourCostForSheet($sheet);
        }
    }

    function syncLegacyPricingFieldsFromActiveSheet() {
        var $active = getActivePricingSheet();
        if (!$active.length) {
            return;
        }
        $('#q_total_cost').val($active.find('.q-sheet-total-cost').val() || '0');
        $('#q_profit_percent').val($active.find('.q-sheet-profit-percent').val() || '');
        $('#q_profit_amount').val($active.find('.q-sheet-profit-amount').val() || '');
        var payload = recalcTourCostForSheet($active);
        if (payload) {
            $('#q_quotation_total').val(money(payload.grand_total));
            $('#q_package_total').val(money(payload.subtotal));
            $('#q_price_per_adult').val($active.find('.q-sheet-price-per-adult').val() || '');
            qTourCostState.adult_rate = payload.adult_rate || '';
            $('#q_tour_cost_json').val(JSON.stringify(payload));
        }
    }

    function recalcCosts() {
        var adults = readGuestCounts().adults;
        var $sheets = $('#qPricingSheetsHost .q-pricing-option-sheet');
        if (!$sheets.length) {
            if (!tourCostRowsPresent()) {
                renderTourCostRows();
            }
            recalcAllTourCostCards();
            return;
        }
        if (!tourCostRowsPresent()) {
            renderTourCostRows();
        }
        $sheets.each(function () {
            recalcOnePricingSheet($(this), adults);
        });
        $('#qPricingSheetsHost .q-pricing-option-sheet').removeClass('is-active');
        $('#qPricingSheetsHost .q-pricing-option-sheet[data-cat-id="' + String(qActiveHotelCategoryId || '').replace(/"/g, '\\"') + '"]').addClass('is-active');
        syncLegacyPricingFieldsFromActiveSheet();
    }

    function collectPricingOptionsPayload() {
        snapshotPricingSheets();
        var data = collectHotelCategories();
        var options = [];
        (data.categories || []).forEach(function (cat, idx) {
            var state = qPricingOptionsState[cat.id] || defaultPricingSheetState();
            var $sheet = $('#qPricingSheetsHost .q-pricing-option-sheet').filter(function () {
                return String($(this).attr('data-cat-id') || '') === String(cat.id);
            }).first();
            options.push({
                category_id: cat.id,
                label: cat.label || defaultHotelCategoryLabel(idx),
                fixed: state.fixed || {},
                custom: state.custom || [],
                user_edited: state.user_edited || {},
                profit_percent: state.profit_percent || '',
                profit_amount: state.profit_amount || '',
                price_per_adult: state.price_per_adult || '',
                price_per_adult_edited: state.price_per_adult_edited || 0,
                total_cost: $sheet.length ? String($sheet.find('.q-sheet-total-cost').val() || '').replace(/,/g, '') : '',
                package_total: $sheet.length ? String($sheet.find('.q-sheet-package-total').val() || '').replace(/,/g, '') : '',
                quotation_total: $sheet.length ? String($sheet.find('.q-sheet-quotation-total').val() || '').replace(/,/g, '') : '',
                hotel_total: hotelTotalForCategory(cat),
                hotel_count: (cat.hotels || []).length
            });
        });
        var activeId = String(qActiveHotelCategoryId || '');
        var active = options.find(function (o) { return String(o.category_id) === activeId; }) || options[0] || null;
        return {
            fixed: active ? (active.fixed || {}) : {},
            custom: active ? (active.custom || []) : [],
            user_edited: active ? (active.user_edited || {}) : {},
            options: options,
            active_option_id: active ? active.category_id : activeId,
            pricing_notes: $.trim($('#q_pricing_notes').val() || ''),
            tour_cost: collectTourCostPayload(),
            hotel_categories: {
                active_category_id: data.active_category_id,
                options: (function () {
                    var hc = {};
                    options.forEach(function (o) {
                        hc[o.category_id] = {
                            label: o.label,
                            hotel_total: o.hotel_total,
                            hotel_count: o.hotel_count
                        };
                    });
                    return hc;
                })()
            },
            itinerary_meta: collectItineraryMeta()
        };
    }

    function applyPricingNotes(notes) {
        $('#q_pricing_notes').val(notes != null ? String(notes) : '');
    }

    function rawNumber(id) {
        var v = ('' + $(id).val()).replace(/,/g, '');
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    /* ------------------------------------------------------------------ */
    /* USD converter                                                       */
    /* ------------------------------------------------------------------ */
    var $lastFocusedCost = null;
    $(document).on('focus', '#qPricingSheetsHost .q-cost, #qPricingSheetsHost .q-sheet-profit-percent, #qPricingSheetsHost .q-sheet-profit-amount, #qPricingSheetsHost .q-sheet-price-per-adult, .q-sheet-tour-cost-rows .q-tour-rate-input, .q-sheet-tour-cost-rows .q-tour-gst-input, .q-sheet-tour-cost-rows .q-tour-qty-input, #qTourCostRows .q-tour-rate-input, #qTourCostRows .q-tour-gst-input, #qTourCostRows .q-tour-qty-input', function () {
        $lastFocusedCost = $(this);
        qCalcUpdateTargetLabel();
    });

    $(document).on('input change', '.q-sheet-tour-cost-rows .q-tour-rate-input, .q-sheet-tour-cost-rows .q-tour-gst-input, #qTourCostRows .q-tour-rate-input, #qTourCostRows .q-tour-gst-input', function () {
        var $sheet = $(this).closest('.q-pricing-option-sheet');
        var key = String($(this).attr('data-tour-key') || '');
        snapshotTourCostFromDom($(this));
        if (key === 'adult' && $sheet.length) {
            $sheet.find('.q-sheet-price-per-adult').val($(this).val() || '').attr('data-user-edited', '1');
        }
        if ($(this).hasClass('q-tour-gst-input')) {
            var gstVal = $(this).val();
            $('.q-sheet-tour-cost-rows .q-tour-gst-input, #qTourCostRows .q-tour-gst-input').not(this).each(function () {
                if (!$(this).is(':focus')) {
                    $(this).val(gstVal);
                }
            });
        }
        if (key.indexOf('child_') === 0 || key === 'infant') {
            var val = $(this).val();
            var selector = '.q-tour-rate-input[data-tour-key="' + key.replace(/"/g, '\\"') + '"]';
            $('.q-sheet-tour-cost-rows ' + selector + ', #qTourCostRows ' + selector).not(this).each(function () {
                if (!$(this).is(':focus')) {
                    $(this).val(val);
                }
            });
        }
        if ($sheet.length) {
            var $targets = (key !== 'adult' || $(this).hasClass('q-tour-gst-input'))
                ? $('#qPricingSheetsHost .q-pricing-option-sheet')
                : $sheet;
            var activePayload = null;
            $targets.each(function () {
                var payload = recalcTourCostForSheet($(this));
                if ($(this).hasClass('is-active')) {
                    activePayload = payload;
                }
            });
            if (activePayload) {
                $('#q_quotation_total').val(money(activePayload.grand_total));
                $('#q_package_total').val(money(activePayload.subtotal));
                $('#q_price_per_adult').val($sheet.find('.q-sheet-price-per-adult').val() || '');
                qTourCostState.adult_rate = activePayload.adult_rate || '';
                $('#q_tour_cost_json').val(JSON.stringify(activePayload));
            }
        } else {
            recalcAllTourCostCards();
        }
        markTourCostAutoSaved($sheet.find('.q-sheet-tour-autosave'));
        saveFormDraftToStorage();
    });

    $(document).on('change input', '.q-sheet-tour-cost-rows .q-tour-qty-input[data-tour-qty="adult"], #qTourCostRows .q-tour-qty-input[data-tour-qty="adult"]', function () {
        var n = parseInt($(this).val(), 10);
        if (isNaN(n) || n < 1) n = 1;
        $('#q_adults').val(n).trigger('change');
    });

    $(document).on('change input', '#q_adults, #q_children', function () {
        snapshotTourCostFromDom();
        renderTourCostRows();
        recalcCosts();
    });

    $(document).on('change', '#q_hide_gst_note', function () {
        snapshotTourCostFromDom();
        renderTourCostRows();
        recalcAllTourCostCards();
    });

    /* ------------------------------------------------------------------ */
    /* Pricing calculator                                                  */
    /* ------------------------------------------------------------------ */
    var qCalc = { display: '0', prev: null, op: null, fresh: true, error: false };
    var qCalcFeedbackTimer = null;

    function qCalcOpSymbol(op) {
        if (op === '/') return '÷';
        if (op === '*') return '×';
        if (op === '-') return '−';
        if (op === '+') return '+';
        if (op === '%') return '%';
        return '';
    }

    function qCalcFormat(val) {
        var s = String(val);
        if (s === 'Error' || s === '' || s === 'NaN' || s === 'Infinity' || s === '-Infinity') return s === '' ? '0' : s;
        var neg = s.charAt(0) === '-';
        if (neg) s = s.slice(1);
        var parts = s.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return (neg ? '-' : '') + parts.join('.');
    }

    function qCalcUpdateUi() {
        var expr = '';
        if (qCalc.prev != null && qCalc.op) {
            expr = qCalcFormat(qCalc.prev) + ' ' + qCalcOpSymbol(qCalc.op);
        }
        $('#qCalcExpr').text(expr);
        $('#qCalcDisplay').val(qCalcFormat(qCalc.display));
        $('#qCalcKeys .q-calc-op').removeClass('is-active');
        if (qCalc.op && qCalc.fresh) {
            $('#qCalcKeys .q-calc-op[data-calc="' + qCalc.op + '"]').addClass('is-active');
        }
        $('#qCalcScreen').toggleClass('is-error', !!qCalc.error);
    }

    function qCalcSetDisplay(val, isError) {
        var s = String(val);
        if (s === '' || s === 'NaN' || s === 'Infinity' || s === '-Infinity') s = '0';
        qCalc.display = s;
        qCalc.error = !!isError;
        qCalcUpdateUi();
        if (isError) {
            window.setTimeout(function () {
                $('#qCalcScreen').removeClass('is-error');
            }, 320);
        }
    }

    function qCalcNum() {
        var n = parseFloat(String(qCalc.display).replace(/,/g, ''));
        return isNaN(n) ? 0 : n;
    }

    function qCalcCompute() {
        if (qCalc.prev == null || !qCalc.op) return qCalcNum();
        var a = qCalc.prev;
        var b = qCalcNum();
        var r = b;
        if (qCalc.op === '+') r = a + b;
        else if (qCalc.op === '-') r = a - b;
        else if (qCalc.op === '*') r = a * b;
        else if (qCalc.op === '/') r = b === 0 ? NaN : a / b;
        else if (qCalc.op === '%') r = a * (b / 100);
        if (isFinite(r)) {
            r = Math.round(r * 1e8) / 1e8;
            var out = String(r);
            if (out.indexOf('e') >= 0) out = r.toFixed(6).replace(/\.?0+$/, '');
            return parseFloat(out);
        }
        return r;
    }

    function qCalcFlash(msg) {
        var $fb = $('#qCalcFeedback');
        if (!$fb.length) return;
        $fb.text(msg || '').addClass('is-on');
        if (qCalcFeedbackTimer) window.clearTimeout(qCalcFeedbackTimer);
        qCalcFeedbackTimer = window.setTimeout(function () {
            $fb.removeClass('is-on').text('');
        }, 1400);
    }

    function qCalcCostLabel($el) {
        if (!$el || !$el.length) return 'Land';
        var key = String($el.attr('data-key') || '');
        var map = {
            flight_train: 'Flight/Train',
            land: 'Land',
            hotel: 'Hotel',
            transport: 'Transport',
            visa: 'Visa',
            travel_insurance: 'Insurance'
        };
        if (map[key]) return map[key];
        if ($el.hasClass('cc-amount') || $el.closest('.q-custom-cost').length) {
            var lbl = $.trim($el.closest('.q-custom-cost').find('.cc-label').val() || '');
            return lbl || 'Extra cost';
        }
        if ($el.hasClass('q-sheet-profit-percent')) return 'Profit %';
        if ($el.hasClass('q-sheet-profit-amount')) return 'Profit amt';
        if ($el.hasClass('q-sheet-price-per-adult')) return 'Price/Adult';
        if ($el.hasClass('q-tour-rate-input')) {
            var tk = String($el.attr('data-tour-key') || '');
            if (tk === 'adult') return 'Adult rate';
            if (tk.indexOf('child_') === 0) return 'Child rate';
            if (tk === 'infant') return 'Infant rate';
            return 'Tour rate';
        }
        return key ? key.replace(/_/g, ' ') : 'Cost field';
    }

    function qCalcResolveTarget() {
        var $target = $lastFocusedCost && $lastFocusedCost.length && $lastFocusedCost.closest('body').length
            ? $lastFocusedCost
            : $();
        if ($target.length && !$target.closest('#qPricingSheetsHost, .q-sheet-tour-cost-rows, #qTourCostRows').length) {
            $target = $();
        }
        if (!$target.length) {
            $target = $('#qPricingSheetsHost .q-pricing-option-sheet.is-active .q-cost[data-key="land"]').first();
        }
        if (!$target.length) {
            $target = $('#qPricingSheetsHost .q-cost[data-key="land"]').first();
        }
        return $target;
    }

    function qCalcUpdateTargetLabel() {
        var $t = qCalcResolveTarget();
        $('#qCalcTargetLabel').text(qCalcCostLabel($t));
    }

    function qCalcPress(key) {
        qCalc.error = false;
        if (key === 'C') {
            qCalc = { display: '0', prev: null, op: null, fresh: true, error: false };
            qCalcUpdateUi();
            return;
        }
        if (key === 'CE') {
            qCalcSetDisplay('0');
            qCalc.fresh = true;
            return;
        }
        if (key === 'BS') {
            if (qCalc.fresh || qCalc.display === 'Error') {
                qCalcSetDisplay('0');
                qCalc.fresh = true;
                return;
            }
            var next = String(qCalc.display);
            if (next.length <= 1 || (next.length === 2 && next.charAt(0) === '-')) {
                qCalcSetDisplay('0');
                qCalc.fresh = true;
            } else {
                qCalcSetDisplay(next.slice(0, -1));
            }
            return;
        }
        if (key === '±') {
            if (qCalc.display === '0' || qCalc.display === 'Error') return;
            qCalcSetDisplay(qCalc.display.charAt(0) === '-' ? qCalc.display.slice(1) : '-' + qCalc.display);
            qCalc.fresh = false;
            return;
        }
        if (key === '=') {
            var result = qCalcCompute();
            if (!isFinite(result)) {
                qCalcSetDisplay('Error', true);
                qCalc.prev = null;
                qCalc.op = null;
                qCalc.fresh = true;
                return;
            }
            qCalcSetDisplay(String(result));
            qCalc.prev = null;
            qCalc.op = null;
            qCalc.fresh = true;
            qCalcUpdateUi();
            return;
        }
        if (key === '+' || key === '-' || key === '*' || key === '/' || key === '%') {
            if (qCalc.display === 'Error') {
                qCalcSetDisplay('0');
            }
            if (qCalc.op && !qCalc.fresh) {
                var mid = qCalcCompute();
                if (!isFinite(mid)) {
                    qCalcSetDisplay('Error', true);
                    qCalc.prev = null;
                    qCalc.op = null;
                    qCalc.fresh = true;
                    return;
                }
                qCalcSetDisplay(String(mid));
            }
            qCalc.prev = qCalcNum();
            qCalc.op = key;
            qCalc.fresh = true;
            qCalcUpdateUi();
            return;
        }
        if (key === '.') {
            if (qCalc.display === 'Error') {
                qCalcSetDisplay('0.');
                qCalc.fresh = false;
                return;
            }
            if (qCalc.fresh) {
                qCalcSetDisplay('0.');
                qCalc.fresh = false;
                return;
            }
            if (qCalc.display.indexOf('.') >= 0) return;
            qCalcSetDisplay(qCalc.display + '.');
            return;
        }
        if (/^\d$/.test(key)) {
            if (qCalc.display === 'Error') {
                qCalcSetDisplay(key);
                qCalc.fresh = false;
                return;
            }
            if (qCalc.fresh || qCalc.display === '0') {
                qCalcSetDisplay(key);
                qCalc.fresh = false;
            } else if (String(qCalc.display).replace('-', '').replace('.', '').length >= 14) {
                return;
            } else {
                qCalcSetDisplay(qCalc.display + key);
            }
        }
    }

    function qCalcFillTarget() {
        var n = qCalcNum();
        if (!isFinite(n) || qCalc.display === 'Error') {
            qCalcSetDisplay('Error', true);
            qCalcFlash('Invalid');
            return;
        }
        var $target = qCalcResolveTarget();
        if (!$target.length) {
            qCalcFlash('No field');
            return;
        }
        $target.val(n.toFixed(2)).trigger('input');
        if ($target.hasClass('q-cost-synced')) {
            $target.attr('data-user-edited', '1');
        }
        $lastFocusedCost = $target;
        qCalcUpdateTargetLabel();
        recalcCosts();
        qCalcFlash('Filled');
        $target.addClass('q-calc-just-filled');
        window.setTimeout(function () { $target.removeClass('q-calc-just-filled'); }, 700);
    }

    function qCalcLoadFromTarget() {
        var $target = qCalcResolveTarget();
        if (!$target.length) {
            qCalcFlash('No field');
            return;
        }
        var raw = String($target.val() || '').replace(/,/g, '');
        var n = parseFloat(raw);
        if (isNaN(n)) {
            qCalcFlash('Empty');
            return;
        }
        qCalc = { display: String(n), prev: null, op: null, fresh: true, error: false };
        qCalcUpdateUi();
        qCalcUpdateTargetLabel();
        qCalcFlash('Loaded');
    }

    function qCalcCopyResult() {
        var text = String(qCalc.display || '0');
        if (text === 'Error') {
            qCalcFlash('Invalid');
            return;
        }
        var done = function () { qCalcFlash('Copied'); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                var $tmp = $('<input>').val(text).appendTo('body').select();
                try { document.execCommand('copy'); } catch (e) {}
                $tmp.remove();
                done();
            });
        } else {
            var $tmp = $('<input>').val(text).appendTo('body').select();
            try { document.execCommand('copy'); } catch (e2) {}
            $tmp.remove();
            done();
        }
    }

    // Keep legacy name used nowhere critical
    function qCalcFillLand() { qCalcFillTarget(); }

    /* ------------------------------------------------------------------ */
    /* Rich editors                                                        */
    /* ------------------------------------------------------------------ */
    function initRichEditors() {
        richEditors.forEach(function (field) {
            var $ta = $('#qed_' + field);
            var $section = $('#qbody_' + field);
            if ($section.is(':visible')) {
                initQuotationSummernote($ta, 160);
            }
        });
    }

    function syncAllEditors() {
        $('textarea.q-editor, #qItineraryDays .q-day-textarea').each(function () {
            var $ta = $(this);
            if ($.fn.summernote && $ta.data('summernote')) {
                $ta.val($ta.summernote('code'));
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Collect full payload                                                */
    /* ------------------------------------------------------------------ */
    function collectPayload() {
        syncAllEditors();
        syncLegacyPricingFieldsFromActiveSheet();
        var costSheetObj = collectPricingOptionsPayload();

        return {
            id: $('#q_id').val() || '',
            lead_id: $('#q_lead_id').val() || '',
            edit_from_version: $('#q_edit_from_version').val() || '',
            guest_name: $('[name=guest_name]').val(),
            reference_name: $('[name=reference_name]').val(),
            mobile_no: $('[name=mobile_no]').val(),
            email: $('[name=email]').val(),
            destination: $('[name=destination]').val(),
            tentative_date: $('#q_tentative_date').val(),
            no_of_nights: $('#q_nights').val(),
            no_of_adults: $('#q_adults').val(),
            no_of_children: $('#q_children').val(),
            flights_json: JSON.stringify(collectFlights()),
            hotels_json: JSON.stringify(collectHotelCategories()),
            itinerary_json: JSON.stringify(snapshotItinerary()),
            inclusion: $('#qed_inclusion').val(),
            exclusion: $('#qed_exclusion').val(),
            payment_policy: $('#qed_payment_policy').val(),
            cancellation_policy: $('#qed_cancellation_policy').val(),
            terms_conditions: $('#qed_terms_conditions').val(),
            other_details: $('#qed_other_details').val(),
            cost_sheet_json: JSON.stringify(costSheetObj),
            pricing_options: costSheetObj.options || [],
            active_option_id: costSheetObj.active_option_id || '',
            pricing_notes: costSheetObj.pricing_notes || '',
            total_cost: rawNumber('#q_total_cost'),
            profit_type: (parseFloat($('#q_profit_amount').val()) > 0) ? 'amount' : 'percent',
            profit_value: (parseFloat($('#q_profit_amount').val()) > 0) ? $('#q_profit_amount').val() : ($('#q_profit_percent').val() || 0),
            package_total: rawNumber('#q_package_total'),
            price_per_adult: $('#q_price_per_adult').val() || 0,
            quotation_total: rawNumber('#q_quotation_total'),
            without_itinerary: $('#q_without_itinerary').is(':checked') ? 1 : 0,
            hide_gst_note: $('#q_hide_gst_note').is(':checked') ? 1 : 0,
            wizard_step: qWizardCurrent
        };
    }

    /* ------------------------------------------------------------------ */
    /* Preview                                                             */
    /* ------------------------------------------------------------------ */
    var Q_PREVIEW_META = (typeof QUOTATION_PREVIEW_META === 'object' && QUOTATION_PREVIEW_META) ? QUOTATION_PREVIEW_META : {};
    var previewEditOrig = '';
    var previewDirty = false;

    function setPreviewDirty(isDirty) {
        previewDirty = !!isDirty;
        var $btn = $('#qPreviewSaveBtn');
        var $hint = $('#qPreviewUnsavedHint');
        if (!$btn.length) return;
        if (previewDirty) {
            $btn.removeClass('d-none').prop('disabled', false);
            $hint.removeClass('d-none');
        } else {
            $btn.addClass('d-none').prop('disabled', true);
            $hint.addClass('d-none');
        }
    }

    function previewVal(v, fallback) {
        var s = v == null ? '' : String(v).trim();
        return s !== '' ? s : (fallback || '—');
    }

    function previewHasHtmlContent(html) {
        if (!html) return false;
        return String(html).replace(/<[^>]*>/g, '').replace(/&nbsp;/gi, ' ').trim() !== '';
    }

    function previewEditable(text, path, opts) {
        opts = opts || {};
        var type = opts.type || 'text';
        var tag = opts.tag || (type === 'html' ? 'div' : 'span');
        var cls = 'q-preview-editable' + (opts.cls ? (' ' + opts.cls) : '');
        var display = text == null ? '' : String(text);
        if (type !== 'html' && $.trim(display) === '') {
            display = opts.placeholder != null ? opts.placeholder : '—';
        }
        var html = '<' + tag + ' class="' + cls + '" contenteditable="true"'
            + ' data-q-edit="' + esc(path) + '"'
            + ' data-q-type="' + esc(type) + '"'
            + ' spellcheck="false"';
        if (opts.multiline || type === 'html') {
            html += ' data-q-multiline="1"';
        }
        html += '>';
        if (type === 'html') {
            html += display || '<p><br></p>';
        } else {
            html += esc(display);
        }
        html += '</' + tag + '>';
        return html;
    }

    function previewTd(text, path, opts) {
        opts = opts || {};
        var type = opts.type || 'text';
        var tdCls = opts.tdCls ? (' class="' + opts.tdCls + '"') : '';
        var display = text == null ? '' : String(text);
        if ($.trim(display) === '') {
            display = opts.placeholder != null ? opts.placeholder : '—';
        }
        return '<td' + tdCls + '>' +
            previewEditable(display, path, {
                type: type,
                multiline: opts.multiline,
                placeholder: opts.placeholder,
                cls: 'q-preview-cell-edit'
            }) +
            '</td>';
    }

    function parsePreviewDate(str) {
        if (!str) return null;
        var s = String(str).trim();
        var m = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
        if (m) {
            var dd = parseInt(m[1], 10);
            var mm = parseInt(m[2], 10);
            var yy = parseInt(m[3], 10);
            if (m[3].length === 2) {
                yy += yy >= 70 ? 1900 : 2000;
            }
            var dSlash = new Date(yy, mm - 1, dd);
            if (!isNaN(dSlash.getTime()) && dSlash.getDate() === dd && dSlash.getMonth() === mm - 1) {
                return dSlash;
            }
        }
        var months = {
            jan: 0, feb: 1, mar: 2, apr: 3, may: 4, jun: 5,
            jul: 6, aug: 7, sep: 8, oct: 9, nov: 10, dec: 11
        };
        var m2 = s.match(/^(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{4})$/);
        if (m2) {
            var monKey = m2[2].slice(0, 3).toLowerCase();
            if (months[monKey] !== undefined) {
                var dFlight = new Date(parseInt(m2[3], 10), months[monKey], parseInt(m2[1], 10));
                if (!isNaN(dFlight.getTime())) return dFlight;
            }
        }
        var d = new Date(s + (s.indexOf('T') >= 0 ? '' : 'T00:00:00'));
        return isNaN(d.getTime()) ? null : d;
    }

    function toIsoDateFromPreview(str) {
        var d = parsePreviewDate(str);
        if (!d) return '';
        return d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
    }

    function formatPreviewSlashDate(str) {
        var d = parsePreviewDate(str);
        if (!d) return previewVal(str, '—');
        return String(d.getDate()).padStart(2, '0') + '/' +
            String(d.getMonth() + 1).padStart(2, '0') + '/' +
            d.getFullYear();
    }

    function formatPreviewDashDate(str) {
        var d = parsePreviewDate(str);
        if (!d) return previewVal(str, '—');
        return String(d.getDate()).padStart(2, '0') + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            d.getFullYear();
    }

    function formatPreviewLongDate(str) {
        var d = parsePreviewDate(str);
        if (!d) return previewVal(str, '—');
        var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    }

    function formatPreviewFlightDate(str) {
        var d = parsePreviewDate(str);
        if (!d) return previewVal(str, '—');
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    }

    function formatPreviewFlightDateTime(dateStr, timeStr) {
        var datePart = formatPreviewFlightDate(dateStr);
        var timePart = (timeStr || '').trim();
        if (datePart === '—' && !timePart) return '—';
        if (!timePart) return datePart;
        return datePart + ' | ' + timePart;
    }

    function previewDayMeta(baseStr, offset) {
        var d = parsePreviewDate(baseStr);
        if (!d) {
            return { dayName: '', dateDash: '' };
        }
        d.setDate(d.getDate() + offset);
        return {
            dayName: DAY_NAMES[d.getDay()].toUpperCase(),
            dateDash: String(d.getDate()).padStart(2, '0') + '-' +
                String(d.getMonth() + 1).padStart(2, '0') + '-' +
                d.getFullYear()
        };
    }

    function previewRefNo(p) {
        if (Q_PREVIEW_META.quotation_uid) {
            return Q_PREVIEW_META.quotation_uid;
        }
        if (p.id) {
            return 'QT-' + p.id;
        }
        var now = new Date();
        return 'MZQ/' + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0') + '/' +
            String(now.getFullYear()).slice(-2) + String(now.getMonth() + 1).padStart(2, '0');
    }

    function previewExpertInitial(name) {
        name = (name || '').trim();
        return name ? name.charAt(0).toUpperCase() : 'H';
    }

    function setEditorFieldValue(selector, htmlOrText, isHtml) {
        var $ta = $(selector);
        if (!$ta.length) return;
        var val = htmlOrText == null ? '' : String(htmlOrText);
        if ($.fn.summernote && $ta.data('summernote')) {
            try {
                $ta.summernote('code', isHtml ? val : esc(val));
            } catch (e) {
                $ta.val(val);
            }
        } else {
            $ta.val(val);
        }
    }

    function readPreviewEditableValue($el) {
        var type = $el.attr('data-q-type') || 'text';
        var raw;
        if (type === 'html') {
            raw = ($el.html() || '').trim();
        } else {
            raw = ($el.text() || '').trim();
        }
        if (raw === '—' || raw === '-' || raw === '–') {
            raw = '';
        }
        return raw;
    }

    function normalizePreviewMoney(str) {
        return String(str || '').replace(/INR/gi, '').replace(/[,\s₹]/g, '').trim();
    }

    function parsePreviewDateTimeLabel(str) {
        var s = String(str || '').trim();
        var datePart = s;
        var timePart = '';
        var pipe = s.split('|');
        if (pipe.length >= 2) {
            datePart = pipe[0].trim();
            timePart = pipe.slice(1).join('|').trim();
        } else {
            var m = s.match(/^(.+?)\s+(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)$/);
            if (m) {
                datePart = m[1].trim();
                timePart = m[2].trim();
            }
        }
        return {
            date: toIsoDateFromPreview(datePart) || datePart,
            time: timePart
        };
    }

    function parseFlightLabel(str) {
        var s = String(str || '').trim();
        var m = s.match(/^(.+?)\s*[-–—]\s*(.*)$/);
        if (m) {
            return { name: m[1].trim(), fl_tr_no: m[2].trim() };
        }
        return { name: s, fl_tr_no: '' };
    }

    function parseLayoverLabel(str) {
        var s = String(str || '').replace(/^Layover\s*/i, '').trim();
        var at = '';
        var time = '';
        var m = s.match(/^(?:at\s+)?([^:]+):\s*(.*)$/i);
        if (m) {
            at = m[1].trim();
            time = m[2].trim();
        } else {
            at = s;
        }
        return { layover_at: at, layover_time: time };
    }

    function parseNightsLabel(str) {
        var m = String(str || '').match(/(\d+)/);
        return m ? parseInt(m[1], 10) : 0;
    }

    function applyPreviewEdit($el) {
        if (!$el || !$el.length) return;
        var path = String($el.attr('data-q-edit') || '');
        if (!path) return;
        var type = $el.attr('data-q-type') || 'text';
        var value = readPreviewEditableValue($el);
        var parts = path.split('.');
        var needsItineraryRebuild = false;
        var needsCostRecalc = false;
        var needsPreviewRefresh = false;

        function setInput($input, val) {
            if (!$input || !$input.length) return;
            $input.val(val);
            $input.trigger('change');
        }

        if (parts[0] === 'flight' && parts.length >= 3) {
            var fi = parseInt(parts[1], 10) || 0;
            var fField = parts[2];
            var $fRow = $('#qFlightRows .q-flight-row').eq(fi);
            if (!$fRow.length) return;

            if (fField === 'dep_datetime') {
                var depParsed = parsePreviewDateTimeLabel(value);
                setInput($fRow.find('.f-dep-date'), depParsed.date);
                setInput($fRow.find('.f-dep-time'), depParsed.time);
            } else if (fField === 'arr_datetime') {
                var arrParsed = parsePreviewDateTimeLabel(value);
                setInput($fRow.find('.f-arr-date'), arrParsed.date);
                setInput($fRow.find('.f-arr-time'), arrParsed.time);
            } else if (fField === 'flight_label') {
                var labelParsed = parseFlightLabel(value);
                setInput($fRow.find('.f-name'), labelParsed.name);
                setInput($fRow.find('.f-fl-no'), labelParsed.fl_tr_no);
            } else if (fField === 'layover') {
                var layParsed = parseLayoverLabel(value);
                setInput($fRow.find('.f-layover-at'), layParsed.layover_at);
                setInput($fRow.find('.f-layover-time'), layParsed.layover_time);
            } else {
                var fMap = {
                    from: '.f-from',
                    to: '.f-to',
                    name: '.f-name',
                    fl_tr_no: '.f-fl-no',
                    dep_date: '.f-dep-date',
                    dep_time: '.f-dep-time',
                    arr_date: '.f-arr-date',
                    arr_time: '.f-arr-time',
                    layover_time: '.f-layover-time',
                    layover_at: '.f-layover-at'
                };
                var fSel = fMap[fField];
                if (!fSel) return;
                if (fField === 'dep_date' || fField === 'arr_date') {
                    value = toIsoDateFromPreview(value) || value;
                }
                setInput($fRow.find(fSel), value);
            }
            if (typeof refreshFlightLayovers === 'function') {
                refreshFlightLayovers();
            }
            return;
        }

        if (parts[0] === 'hotel' && parts.length >= 4) {
            var ci = parseInt(parts[1], 10) || 0;
            var ri = parseInt(parts[2], 10) || 0;
            var hField = parts[3];
            var $panel = getHotelCategoryPanels().eq(ci);
            var $hRow = $panel.find('.q-hotel-row').eq(ri);
            if (!$hRow.length) return;
            var hMap = {
                city: '.h-city',
                name: '.h-name',
                nights: '.h-nights',
                room_type: '.h-room',
                checkin: '.h-checkin',
                checkout: '.h-checkout',
                meal_plan: '.h-meal'
            };
            var hSel = hMap[hField];
            if (!hSel) return;
            if (hField === 'checkin' || hField === 'checkout') {
                value = toIsoDateFromPreview(value) || value;
            }
            setInput($hRow.find(hSel), value);
            return;
        }

        if (parts[0] === 'itinerary' && parts.length >= 3) {
            var di = parseInt(parts[1], 10) || 0;
            var iField = parts[2];
            var $day = $('#qItineraryDays .q-day-card').eq(di);
            if (!$day.length) {
                if (typeof scheduleItineraryRebuild === 'function') {
                    scheduleItineraryRebuild();
                }
                window.setTimeout(function () {
                    var $retryDay = $('#qItineraryDays .q-day-card').eq(di);
                    if (!$retryDay.length) return;
                    if (iField === 'title') {
                        setInput($retryDay.find('.q-day-title'), value);
                    } else if (iField === 'description') {
                        var $retryTa = $retryDay.find('.q-day-textarea');
                        if ($.fn.summernote && $retryTa.data('summernote')) {
                            try { $retryTa.summernote('code', value); } catch (e) { $retryTa.val(value); }
                        } else {
                            $retryTa.val(value);
                        }
                    }
                }, 180);
                return;
            }
            if (iField === 'title') {
                setInput($day.find('.q-day-title'), value);
            } else if (iField === 'description') {
                var $ta = $day.find('.q-day-textarea');
                if ($.fn.summernote && $ta.data('summernote')) {
                    try { $ta.summernote('code', value); } catch (e) { $ta.val(value); }
                } else {
                    $ta.val(value);
                }
            }
            return;
        }

        if (parts[0] === 'itinerary_meta' && parts.length >= 2) {
            var metaField = parts[1];
            if (metaField === 'rate') {
                setInput($('#qItinerarySupplierRows .q-itin-supplier-row').first().find('.q-itin-rate'), value);
            } else if (metaField === 'supplier') {
                var $firstSup = $('#qItinerarySupplierRows .q-itin-supplier-row').first().find('.q-itin-supplier');
                if ($firstSup.length) {
                    // Try match by name or id text.
                    var matched = false;
                    $firstSup.find('option').each(function () {
                        var $opt = $(this);
                        if (String($opt.attr('data-name') || $opt.text() || '').trim().toLowerCase() === String(value || '').trim().toLowerCase()) {
                            $firstSup.val($opt.attr('value')).trigger('change.select2');
                            matched = true;
                            return false;
                        }
                    });
                    if (!matched) {
                        setInput($firstSup, value);
                    }
                }
            }
            return;
        }

        switch (path) {
            case 'guest_name':
                setInput($('[name=guest_name]'), value);
                break;
            case 'mobile_no':
                setInput($('[name=mobile_no]'), value);
                break;
            case 'email':
                setInput($('[name=email]'), value);
                break;
            case 'destination':
                setInput($('[name=destination]'), value);
                $('#qPreviewPrintArea .q-preview-dest-title').text(value ? value.toUpperCase() : 'DESTINATION');
                break;
            case 'tentative_date':
                value = toIsoDateFromPreview(value) || value;
                setInput($('#q_tentative_date'), value);
                needsItineraryRebuild = true;
                needsPreviewRefresh = true;
                break;
            case 'no_of_nights':
                value = String(parseNightsLabel(value) || 0);
                setInput($('#q_nights'), value);
                needsItineraryRebuild = true;
                needsCostRecalc = true;
                needsPreviewRefresh = true;
                break;
            case 'no_of_adults':
                value = String(Math.max(1, parseInt(value, 10) || 1));
                setInput($('#q_adults'), value);
                needsCostRecalc = true;
                needsPreviewRefresh = true;
                break;
            case 'no_of_children':
                value = String(Math.max(0, parseInt(value, 10) || 0));
                setInput($('#q_children'), value);
                break;
            case 'inclusion':
                setEditorFieldValue('#qed_inclusion', value, true);
                break;
            case 'exclusion':
                setEditorFieldValue('#qed_exclusion', value, true);
                break;
            case 'payment_policy':
                setEditorFieldValue('#qed_payment_policy', value, true);
                break;
            case 'cancellation_policy':
                setEditorFieldValue('#qed_cancellation_policy', value, true);
                break;
            case 'terms_conditions':
                setEditorFieldValue('#qed_terms_conditions', value, true);
                break;
            case 'other_details':
                setEditorFieldValue('#qed_other_details', value, true);
                break;
            case 'price_per_adult':
                value = normalizePreviewMoney(value);
                setInput($('#q_price_per_adult'), value);
                $('#q_price_per_adult').attr('data-user-edited', '1');
                needsCostRecalc = true;
                needsPreviewRefresh = true;
                break;
            case 'quotation_total':
                value = normalizePreviewMoney(value);
                setInput($('#q_quotation_total'), value);
                needsPreviewRefresh = true;
                break;
            default:
                break;
        }

        if (needsCostRecalc && typeof recalcCosts === 'function') {
            recalcCosts();
        }
        if (needsItineraryRebuild) {
            scheduleItineraryRebuild();
        }
        if (needsPreviewRefresh) {
            window.setTimeout(function () {
                if ($('#qPreviewModal').hasClass('show')) {
                    refreshQuotationPreviewPreserveFocus();
                }
            }, 120);
        }
    }

    function refreshQuotationPreviewPreserveFocus() {
        var activePath = '';
        var $active = $('#qPreviewPrintArea .q-preview-editable:focus');
        if ($active.length) {
            activePath = $active.attr('data-q-edit') || '';
        }
        try {
            $('#qPreviewPrintArea').html(buildPreviewHtml(collectPayload()));
        } catch (err) {
            return;
        }
        if (activePath) {
            var $next = $('#qPreviewPrintArea .q-preview-editable[data-q-edit="' + activePath.replace(/"/g, '\\"') + '"]').first();
            if ($next.length) {
                $next.focus();
                try {
                    var range = document.createRange();
                    range.selectNodeContents($next.get(0));
                    range.collapse(false);
                    var sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                } catch (e2) { /* ignore */ }
            }
        }
    }

    function buildPreviewHtml(p) {
        var flights = JSON.parse(p.flights_json || '[]');
        var hotelsRaw = JSON.parse(p.hotels_json || '[]');
        var hotelsData = normalizeHotelsPrefill(hotelsRaw);
        var itinerary = JSON.parse(p.itinerary_json || '[]');
        var nights = parseInt(p.no_of_nights, 10);
        if (isNaN(nights) || nights < 0) nights = 0;
        var days = nights + 1;
        var adults = parseInt(p.no_of_adults, 10);
        if (isNaN(adults) || adults < 1) adults = 1;
        var children = parseInt(p.no_of_children, 10);
        if (isNaN(children) || children < 0) children = 0;
        var destination = previewVal(p.destination, 'Destination');
        var logoUrl = absUrl(Q_PREVIEW_META.logo || 'img/web-logo.png');
        var todayLong = formatPreviewLongDate(new Date().toISOString().slice(0, 10));
        var paxChildren = children > 0 ? String(children) : '0';
        var nightsLabel = nights + ' Nights / ' + days + ' Days';
        var durationLabel = nights + ' Nts / ' + days + ' Days';

        var html = '';

        html += '<div class="q-preview-head">';
        html += '<div class="q-preview-logo"><img src="' + esc(logoUrl) + '" alt="Multi Zone Travels"></div>';
        html += '<div class="q-preview-title-block">';
        html += '<h1>Quotation For ' + previewEditable(destination.toUpperCase(), 'destination', { cls: 'q-preview-dest-title' }) + '</h1>';
        html += '<div class="q-preview-duration">' +
            previewEditable(durationLabel, 'no_of_nights', { type: 'nights_label', placeholder: '0 Nts / 1 Days' }) +
            '</div>';
        html += '</div>';
        html += '<div class="q-preview-ref-block">';
        html += '<div class="ref">REF: ' + esc(previewRefNo(p)) + '</div>';
        html += '<div>Quotation Date: ' + esc(todayLong) + '</div>';
        html += '</div></div>';

        html += '<table class="q-preview-table"><thead><tr>' +
            '<th>Guest Name</th><th>Mobile No</th><th>Email Address</th>' +
            '</tr></thead><tbody><tr>' +
            previewTd(previewVal(p.guest_name), 'guest_name') +
            previewTd(previewVal(p.mobile_no), 'mobile_no') +
            previewTd(previewVal(p.email), 'email') +
            '</tr></tbody></table>';

        html += '<table class="q-preview-table"><thead><tr>' +
            '<th>Destination</th><th>No Of Nights</th><th>Tentative Date</th><th>No Of Adults</th><th>No Of Children</th>' +
            '</tr></thead><tbody><tr>' +
            previewTd(destination.toUpperCase(), 'destination', { tdCls: 'text-left' }) +
            previewTd(nightsLabel, 'no_of_nights', { type: 'nights_label' }) +
            previewTd(formatPreviewSlashDate(p.tentative_date), 'tentative_date', { type: 'date' }) +
            previewTd(String(adults), 'no_of_adults', { type: 'int' }) +
            previewTd(paxChildren, 'no_of_children', { type: 'int' }) +
            '</tr></tbody></table>';

        if (flights.length) {
            html += '<div class="q-preview-section-title">Flight Details</div>';
            html += '<table class="q-preview-table"><thead><tr>' +
                '<th>Departure</th><th>From</th><th>To</th><th>Arrival</th><th>Flight</th>' +
                '</tr></thead><tbody>';
            flights.forEach(function (f, fi) {
                var d = normalizeFlightData(f);
                var layoverText = '';
                if (d.layover_time || d.layover_at) {
                    layoverText = 'Layover at ' + (d.layover_at || '—') + ': ' + (d.layover_time || '—');
                    html += '<tr class="q-preview-layover"><td colspan="5">' +
                        previewEditable(layoverText, 'flight.' + fi + '.layover', {
                            type: 'layover',
                            cls: 'q-preview-cell-edit'
                        }) +
                        '</td></tr>';
                }
                var flightLabel = '';
                if (d.name && d.fl_tr_no) {
                    flightLabel = d.name + ' - ' + d.fl_tr_no;
                } else {
                    flightLabel = d.name || d.fl_tr_no || '';
                }
                html += '<tr>' +
                    previewTd(formatPreviewFlightDateTime(d.dep_date, d.dep_time), 'flight.' + fi + '.dep_datetime', { type: 'datetime' }) +
                    previewTd(previewVal(d.from), 'flight.' + fi + '.from', { tdCls: 'text-left' }) +
                    previewTd(previewVal(d.to), 'flight.' + fi + '.to', { tdCls: 'text-left' }) +
                    previewTd(formatPreviewFlightDateTime(d.arr_date || d.dep_date, d.arr_time), 'flight.' + fi + '.arr_datetime', { type: 'datetime' }) +
                    previewTd(previewVal(flightLabel), 'flight.' + fi + '.flight_label', { type: 'flight_label', tdCls: 'text-left' }) +
                    '</tr>';
            });
            html += '</tbody></table>';
        }

        var pricingOptions = Array.isArray(p.pricing_options) ? p.pricing_options : [];
        if (!pricingOptions.length && p.cost_sheet_json) {
            try {
                var csPrev = typeof p.cost_sheet_json === 'string' ? JSON.parse(p.cost_sheet_json) : p.cost_sheet_json;
                if (csPrev && Array.isArray(csPrev.options)) {
                    pricingOptions = csPrev.options;
                }
            } catch (e) { /* ignore */ }
        }
        var activeOptionId = String(p.active_option_id || hotelsData.active_category_id || '');
        var multiHotelOptions = hotelsData.categories.length > 1;

        function findPricingOptionForCategory(cat, catIdx) {
            if (!cat) return null;
            var byId = pricingOptions.find(function (o) {
                return String(o.category_id || o.id || '') === String(cat.id);
            });
            if (byId) return byId;
            if (pricingOptions[catIdx]) return pricingOptions[catIdx];
            return null;
        }

        function optionHasTourCost(opt) {
            if (!opt) return false;
            var ppa = parseFloat(opt.price_per_adult);
            var qt = parseFloat(String(opt.quotation_total || '').replace(/,/g, ''));
            var pkg = parseFloat(String(opt.package_total || '').replace(/,/g, ''));
            return (ppa > 0) || (qt > 0) || (pkg > 0);
        }

        function buildPreviewTourCostTable(opt, opts) {
            opts = opts || {};
            var editable = !!opts.editable;
            var tourCost = null;
            try {
                var csFull = typeof p.cost_sheet_json === 'string' ? JSON.parse(p.cost_sheet_json || '{}') : (p.cost_sheet || {});
                if (csFull && csFull.tour_cost) tourCost = csFull.tour_cost;
            } catch (e) { /* ignore */ }
            if (tourCost && parseFloat(tourCost.grand_total) > 0) {
                var blockTc = '<div class="q-preview-section-title">Tour Cost</div>';
                blockTc += '<table class="q-preview-table q-preview-cost"><tbody>';
                var adultRate = parseFloat(tourCost.adult_rate) || 0;
                var adultQty = parseInt(tourCost.adults, 10) || adults;
                if (adultRate > 0) {
                    blockTc += '<tr><td>INR ' + esc(money(adultRate)) + ' X ' + esc(adultQty) + ' Adults</td><td>' + esc(money(adultRate * adultQty)) + '</td></tr>';
                }
                (tourCost.child_rates || []).forEach(function (cr, ci) {
                    var rate = parseFloat(cr) || 0;
                    if (rate > 0) {
                        blockTc += '<tr><td>INR ' + esc(money(rate)) + ' X Child ' + pad2(ci + 1) + '</td><td>' + esc(money(rate)) + '</td></tr>';
                    }
                });
                var infantRate = parseFloat(tourCost.infant_rate) || 0;
                if (infantRate > 0) {
                    blockTc += '<tr><td>INR ' + esc(money(infantRate)) + ' X Infant</td><td>' + esc(money(infantRate)) + '</td></tr>';
                }
                if (!parseInt(tourCost.hide_gst, 10) && parseFloat(tourCost.gst_amount) > 0) {
                    blockTc += '<tr><td>GST (' + esc(String(tourCost.gst_percent || 5)) + '%)</td><td>' + esc(money(tourCost.gst_amount)) + '</td></tr>';
                }
                blockTc += '<tr><td><strong>Grand Total</strong></td><td><strong>' + esc(money(tourCost.grand_total)) + '</strong></td></tr>';
                blockTc += '</tbody></table>';
                if (opts.showGst && !parseInt(p.hide_gst_note, 10)) {
                    blockTc += '<div class="q-preview-gst-note">* GST as applicable will be charged extra.</div>';
                }
                return blockTc;
            }
            var ppa = parseFloat(opt && opt.price_per_adult);
            var qt = parseFloat(String((opt && opt.quotation_total) || '').replace(/,/g, ''));
            var pkg = parseFloat(String((opt && opt.package_total) || '').replace(/,/g, ''));
            if (isNaN(ppa)) ppa = 0;
            if (isNaN(qt)) qt = 0;
            if (isNaN(pkg)) pkg = 0;
            if (!(ppa > 0 || qt > 0 || pkg > 0)) {
                return '';
            }
            var adultTotal = ppa > 0 ? ppa * adults : (pkg > 0 ? pkg : qt);
            var totalVal = qt > 0 ? qt : (pkg > 0 ? pkg : adultTotal);
            var block = '<div class="q-preview-section-title">Tour Cost</div>';
            block += '<table class="q-preview-table q-preview-cost"><tbody>';
            if (ppa > 0) {
                block += '<tr><td>INR ';
                if (editable) {
                    block += previewEditable(money(ppa), 'price_per_adult', { type: 'money' });
                } else {
                    block += esc(money(ppa));
                }
                block += ' X ' + esc(adults) + ' Adults</td>' +
                    '<td>' + esc(money(ppa * adults)) + '</td></tr>';
            }
            block += '<tr><td><strong>Total</strong></td><td><strong>';
            if (editable) {
                block += previewEditable(money(totalVal), 'quotation_total', { type: 'money' });
            } else {
                block += esc(money(totalVal));
            }
            block += '</strong></td></tr>';
            block += '</tbody></table>';
            if (opts.showGst && !parseInt(p.hide_gst_note, 10)) {
                block += '<div class="q-preview-gst-note">* GST as applicable will be charged extra.</div>';
            }
            return block;
        }

        var hasHotels = hotelsData.categories.some(function (cat) {
            return (cat.hotels || []).length > 0;
        });
        if (hasHotels) {
            hotelsData.categories.forEach(function (cat, idx) {
                if (!(cat.hotels || []).length) {
                    return;
                }
                var title = 'Hotel Details';
                if (multiHotelOptions) {
                    title += ' — ' + (cat.label || defaultHotelCategoryLabel(idx));
                    if (String(cat.id) === activeOptionId || cat.id === hotelsData.active_category_id) {
                        title += ' (Recommended)';
                    }
                }
                html += '<div class="q-preview-section-title">' + esc(title) + '</div>';
                html += '<table class="q-preview-table"><thead><tr>' +
                    '<th>City</th><th>Hotel</th><th>Nts</th><th>Room Type</th><th>Check In</th><th>Check Out</th><th>Meals</th>' +
                    '</tr></thead><tbody>';
                (cat.hotels || []).forEach(function (h, hi) {
                    var d = normalizeHotelData(h);
                    var base = 'hotel.' + idx + '.' + hi + '.';
                    html += '<tr>' +
                        previewTd(previewVal(d.city), base + 'city', { tdCls: 'text-left' }) +
                        previewTd(previewVal(d.name), base + 'name', { tdCls: 'text-left' }) +
                        previewTd(previewVal(d.nights), base + 'nights', { type: 'int' }) +
                        previewTd(previewVal(d.room_type), base + 'room_type', { tdCls: 'text-left' }) +
                        previewTd(formatPreviewSlashDate(d.checkin), base + 'checkin', { type: 'date' }) +
                        previewTd(formatPreviewSlashDate(d.checkout), base + 'checkout', { type: 'date' }) +
                        previewTd(previewVal(d.meal_plan, 'CP'), base + 'meal_plan') +
                        '</tr>';
                });
                html += '</tbody></table>';

                // Multiple hotel options: show that option's Tour Cost right under its hotels.
                if (multiHotelOptions) {
                    var optForCat = findPricingOptionForCategory(cat, idx);
                    if (optionHasTourCost(optForCat)) {
                        var isActiveOpt = activeOptionId
                            ? String(optForCat.category_id || optForCat.id || cat.id) === activeOptionId
                            : String(cat.id) === String(hotelsData.active_category_id);
                        html += buildPreviewTourCostTable(optForCat, {
                            editable: isActiveOpt,
                            showGst: true
                        });
                    }
                }
            });
        }

        if (!parseInt(p.without_itinerary, 10)) {
            var itineraryHtml = '';
            var costSheetPreview = {};
            try {
                costSheetPreview = typeof p.cost_sheet_json === 'string'
                    ? JSON.parse(p.cost_sheet_json || '{}')
                    : (p.cost_sheet || {});
            } catch (previewCsErr) {
                costSheetPreview = {};
            }
            var itinMeta = costSheetPreview.itinerary_meta || {};
            var itinEntries = normalizeItinerarySupplierEntries(itinMeta).filter(function (item) {
                return (item.supplier && String(item.supplier).trim()) || (item.rate !== '' && item.rate != null);
            });
            if (itinEntries.length) {
                itineraryHtml += '<div class="q-preview-itinerary-meta small text-muted mb-2">';
                itinEntries.forEach(function (item, idx) {
                    if (idx > 0) {
                        itineraryHtml += '<br>';
                    }
                    var bits = [];
                    if (item.supplier) {
                        bits.push('Supplier: ' + esc(item.supplier));
                    }
                    if (item.rate !== '' && item.rate != null) {
                        if (idx === 0) {
                            bits.push('Rate: ' + previewEditable(String(item.rate), 'itinerary_meta.rate', {
                                placeholder: 'Rate'
                            }));
                        } else {
                            bits.push('Rate: ' + esc(String(item.rate)));
                        }
                    }
                    itineraryHtml += bits.join(' &nbsp;|&nbsp; ');
                });
                itineraryHtml += '</div>';
            }
            itinerary.forEach(function (day, di) {
                var dayTitle = day && day.title ? String(day.title).trim() : '';
                var dayDesc = day && day.description ? String(day.description).replace(/<[^>]*>/g, '').trim() : '';
                if (!dayTitle && !dayDesc) {
                    return;
                }
                var meta = previewDayMeta(p.tentative_date, di);
                var headPrefix = meta.dayName ? (meta.dayName + ' DAY ' + (di + 1)) : ('DAY ' + (di + 1));
                if (meta.dateDash) {
                    headPrefix += ' | ' + meta.dateDash;
                }
                itineraryHtml += '<div class="q-preview-day">';
                itineraryHtml += '<div class="q-preview-day-head">' + esc(headPrefix) + ' | ' +
                    previewEditable(previewVal(day.title, ''), 'itinerary.' + di + '.title', {
                        placeholder: 'Day title'
                    }) + '</div>';
                itineraryHtml += '<div class="q-preview-day-body">';
                itineraryHtml += previewEditable(day.description || '', 'itinerary.' + di + '.description', {
                    type: 'html',
                    multiline: true,
                    cls: 'q-preview-rich'
                });
                itineraryHtml += '</div></div>';
            });
            if (itineraryHtml) {
                html += '<div class="q-preview-section-title">Day Wise Itinerary</div>';
                html += itineraryHtml;
            }
        }

        if (previewHasHtmlContent(p.inclusion)) {
            html += '<div class="q-preview-section-title">Inclusions</div>';
            html += previewEditable(p.inclusion || '', 'inclusion', { type: 'html', multiline: true, cls: 'q-preview-rich' });
        }

        if (previewHasHtmlContent(p.exclusion)) {
            html += '<div class="q-preview-section-title">Exclusion</div>';
            html += previewEditable(p.exclusion || '', 'exclusion', { type: 'html', multiline: true, cls: 'q-preview-rich' });
        }

        // Single hotel option: keep Tour Cost in the original place (after inclusions/exclusions).
        if (!multiHotelOptions) {
            var pricePerAdult = parseFloat(p.price_per_adult);
            var quotationTotal = parseFloat(String(p.quotation_total || '').replace(/,/g, ''));
            var packageTotal = parseFloat(String(p.package_total || '').replace(/,/g, ''));
            if (isNaN(pricePerAdult)) pricePerAdult = 0;
            if (isNaN(quotationTotal)) quotationTotal = 0;
            if (isNaN(packageTotal)) packageTotal = 0;

            var singleOpt = pricingOptions[0] || {
                price_per_adult: pricePerAdult,
                quotation_total: quotationTotal,
                package_total: packageTotal
            };
            if (!optionHasTourCost(singleOpt) && (pricePerAdult > 0 || quotationTotal > 0 || packageTotal > 0)) {
                singleOpt = {
                    price_per_adult: pricePerAdult,
                    quotation_total: quotationTotal,
                    package_total: packageTotal
                };
            }
            if (optionHasTourCost(singleOpt) || pricePerAdult > 0 || quotationTotal > 0 || packageTotal > 0) {
                html += buildPreviewTourCostTable({
                    price_per_adult: parseFloat(singleOpt.price_per_adult) > 0 ? singleOpt.price_per_adult : pricePerAdult,
                    quotation_total: singleOpt.quotation_total || quotationTotal,
                    package_total: singleOpt.package_total || packageTotal
                }, {
                    editable: true,
                    showGst: true
                });
            }
        }

        var policyBlocks = [
            { key: 'payment_policy', title: 'Payment Policy', html: p.payment_policy },
            { key: 'cancellation_policy', title: 'Cancellation Policy', html: p.cancellation_policy },
            { key: 'terms_conditions', title: 'Terms & Conditions', html: p.terms_conditions },
            { key: 'other_details', title: 'Other Details', html: p.other_details }
        ];
        var policyHtml = '';
        policyBlocks.forEach(function (b) {
            if (!previewHasHtmlContent(b.html)) return;
            policyHtml += '<div class="q-preview-policy-block">';
            policyHtml += '<div class="q-preview-policy-title">' + esc(b.title) + '</div>';
            policyHtml += previewEditable(b.html || '', b.key, { type: 'html', multiline: true, cls: 'q-preview-rich' });
            policyHtml += '</div>';
        });
        if (policyHtml) {
            html += '<div class="q-preview-section-title">Quotation Terms &amp; Conditions</div>';
            html += policyHtml;
        }

        html += '<div class="q-preview-footer">';
        html += '<div class="q-preview-expert">';
        var expertPhoto = Q_PREVIEW_META.expert_photo ? absUrl(Q_PREVIEW_META.expert_photo) : '';
        var expertName = Q_PREVIEW_META.expert_name || 'Raju Gupta';
        var expertTitle = Q_PREVIEW_META.expert_title || 'Holiday Expert';
        if (expertPhoto) {
            html += '<div class="q-preview-expert-avatar"><img src="' + esc(expertPhoto) + '" alt="' + esc(expertName) + '"></div>';
        } else {
            html += '<div class="q-preview-expert-avatar">' + esc(previewExpertInitial(expertName)) + '</div>';
        }
        html += '<div class="q-preview-expert-name">' +
            '<span class="q-preview-expert-fullname">' + esc(expertName) + '</span>' +
            '<span class="q-preview-expert-sep">|</span>' +
            '<span class="q-preview-expert-role">' + esc(expertTitle) + '</span>' +
            '</div>';
        html += '<div class="q-preview-expert-lines">';
        if (Q_PREVIEW_META.phone) {
            html += '<div class="q-preview-phone-primary">' + esc(Q_PREVIEW_META.phone) + '</div>';
        }
        if (Q_PREVIEW_META.phone_alt) {
            html += '<div>' + esc(Q_PREVIEW_META.phone_alt) + '</div>';
        }
        if (Q_PREVIEW_META.email) {
            html += '<div>' + esc(Q_PREVIEW_META.email) + '</div>';
        }
        if (Q_PREVIEW_META.website) {
            html += '<div>' + esc(Q_PREVIEW_META.website) + '</div>';
        }
        if (Q_PREVIEW_META.address) {
            html += '<div>' + esc(Q_PREVIEW_META.address) + '</div>';
        }
        html += '</div></div>';
        html += '<div class="q-preview-services-bar">' +
            esc(Q_PREVIEW_META.services || 'FLIGHTS | HOTELS | HOLIDAYS | VISA | FOREX') +
            '</div>';
        var social = Array.isArray(Q_PREVIEW_META.social) ? Q_PREVIEW_META.social : [];
        if (social.length) {
            html += '<div class="q-preview-social">';
            social.forEach(function (s) {
                var cls = 'web';
                if (s.type === 'facebook') cls = 'fb';
                else if (s.type === 'twitter') cls = 'tw';
                else if (s.type === 'google') cls = 'gp';
                html += '<a class="' + cls + '" href="' + esc(s.url || '#') + '" target="_blank" rel="noopener">' +
                    '<i class="' + esc(s.icon || 'fas fa-globe') + '"></i></a>';
            });
            html += '</div>';
        }
        html += '</div>';

        return html;
    }

    window.qGetQuotationPreviewHtml = function () {
        try {
            return buildPreviewHtml(collectPayload());
        } catch (err) {
            return '';
        }
    };

    function openQuotationPreview() {
        var p;
        try {
            p = collectPayload();
        } catch (err) {
            alert('Could not prepare preview. ' + (err && err.message ? err.message : ''));
            return;
        }
        $('#qPreviewPrintArea').html(buildPreviewHtml(p));
        setPreviewDirty(false);
        $('#qPreviewModal').modal('show');
    }

    function flushPreviewActiveEdit() {
        var $active = $('#qPreviewPrintArea .q-preview-editable:focus');
        if ($active.length) {
            $active.blur();
        }
    }

    function initPreviewInlineEditing() {
        $(document).on('focus', '#qPreviewPrintArea .q-preview-editable', function () {
            previewEditOrig = readPreviewEditableValue($(this));
            $(this).addClass('is-editing');
            var text = ($(this).text() || '').trim();
            if (text === '—' || text === '-' || text === '–') {
                try {
                    var range = document.createRange();
                    range.selectNodeContents(this);
                    var sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                } catch (e) { /* ignore */ }
            }
        });

        $(document).on('input', '#qPreviewPrintArea .q-preview-editable', function () {
            var current = readPreviewEditableValue($(this));
            if (current !== previewEditOrig) {
                setPreviewDirty(true);
            }
        });

        $(document).on('blur', '#qPreviewPrintArea .q-preview-editable', function () {
            var $el = $(this);
            $el.removeClass('is-editing');
            var current = readPreviewEditableValue($el);
            applyPreviewEdit($el);
            if (current !== previewEditOrig) {
                setPreviewDirty(true);
            }
        });

        $(document).on('click', '#qPreviewPrintArea td, #qPreviewPrintArea .q-preview-day-head, #qPreviewPrintArea .q-preview-day-body, #qPreviewPrintArea .q-preview-policy-block, #qPreviewPrintArea .q-preview-rich', function (e) {
            var $target = $(e.target);
            if ($target.closest('.q-preview-editable').length) return;
            var $edit = $(this).find('.q-preview-editable').first();
            if ($edit.length) {
                e.preventDefault();
                $edit.focus();
            }
        });

        $(document).on('keydown', '#qPreviewPrintArea .q-preview-editable', function (e) {
            var $el = $(this);
            var multiline = $el.attr('data-q-multiline') === '1' || ($el.attr('data-q-type') || '') === 'html';
            if (e.key === 'Escape') {
                e.preventDefault();
                if (($el.attr('data-q-type') || '') === 'html') {
                    $el.html(previewEditOrig);
                } else {
                    $el.text(previewEditOrig || '—');
                }
                $el.blur();
                return;
            }
            if (e.key === 'Enter' && !multiline) {
                e.preventDefault();
                $el.blur();
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Lead lookup (guest name / mobile / email)                           */
    /* ------------------------------------------------------------------ */
    var leadLookupTimer = null;
    var leadLookupSeq = 0;
    var leadLookupCache = {};

    function hideAllLeadMenus() {
        $('.js-q-lead-menu').hide().empty();
    }

    function renderLeadMenu($menu, items, query) {
        $menu.empty();
        if (!items || !items.length) {
            $menu.append('<div class="q-lead-empty">No leads found' + (query ? ' for "' + esc(query) + '"' : '') + '</div>');
        } else {
            items.forEach(function (item) {
                var $btn = $('<button type="button" class="q-lead-item"></button>');
                $btn.append($('<span class="q-lead-item-title"></span>').text(item.label || item.guest_name || 'Lead'));
                if (item.sub_label) {
                    $btn.append($('<span class="q-lead-item-meta"></span>').text(item.sub_label));
                }
                $btn.data('lead', item);
                $menu.append($btn);
            });
        }
        $menu.show();
    }

    function searchLeadsForQuotation(query, callback) {
        var q = (query || '').trim();
        if (q.length < 2) {
            callback([]);
            return;
        }
        if (leadLookupCache[q]) {
            callback(leadLookupCache[q]);
            return;
        }
        var seq = ++leadLookupSeq;
        $.getJSON('crm/ajax/search_leads_for_quotation.php', { q: q, limit: 10 })
            .done(function (res) {
                if (seq !== leadLookupSeq) return;
                var items = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                leadLookupCache[q] = items;
                callback(items);
            })
            .fail(function () {
                if (seq !== leadLookupSeq) return;
                callback([]);
            });
    }

    function applyLeadSuggestion(lead) {
        if (!lead) return;
        if (lead.lead_id) {
            $('#q_lead_id').val(lead.lead_id);
        }
        $('[name=guest_name]').val(lead.guest_name || '');
        $('[name=mobile_no]').val(lead.mobile_no || '');
        $('[name=email]').val(lead.email || '');
        if (lead.reference_name) {
            $('[name=reference_name]').val(lead.reference_name);
        }
        if (lead.destination) {
            $('[name=destination]').val(lead.destination);
        }
        if (lead.tentative_date) {
            $('#q_tentative_date').val(lead.tentative_date);
        }
        if (lead.no_of_nights != null && lead.no_of_nights !== '') {
            $('#q_nights').val(parseInt(lead.no_of_nights, 10) || 0);
        }
        if (lead.no_of_adults != null && lead.no_of_adults !== '') {
            $('#q_adults').val(Math.max(1, parseInt(lead.no_of_adults, 10) || 1));
        }
        if (lead.no_of_children != null && lead.no_of_children !== '') {
            $('#q_children').val(Math.max(0, parseInt(lead.no_of_children, 10) || 0));
        }
        suspendItineraryRebuild();
        rebuildItinerary();
        resumeItineraryRebuild();
        recalcCosts();
    }

    function initLeadLookup() {
        $(document).on('input', '.js-q-lead-lookup', function () {
            var $input = $(this);
            var $menu = $input.closest('.q-lead-combobox').find('.js-q-lead-menu');
            var query = ($input.val() || '').trim();

            hideAllLeadMenus();
            if (query.length < 2) {
                return;
            }

            clearTimeout(leadLookupTimer);
            leadLookupTimer = setTimeout(function () {
                searchLeadsForQuotation(query, function (items) {
                    renderLeadMenu($menu, items, query);
                });
            }, 280);
        });

        $(document).on('click', '.js-q-lead-lookup', function () {
            var $input = $(this);
            var query = ($input.val() || '').trim();
            if (query.length < 2) {
                hideAllLeadMenus();
                return;
            }
            var $menu = $input.closest('.q-lead-combobox').find('.js-q-lead-menu');
            searchLeadsForQuotation(query, function (items) {
                renderLeadMenu($menu, items, query);
            });
        });

        $(document).on('mousedown', '.q-lead-item', function (e) {
            e.preventDefault();
            var lead = $(this).data('lead');
            hideAllLeadMenus();
            applyLeadSuggestion(lead);
        });

        $(document).on('blur', '.js-q-lead-lookup', function () {
            window.setTimeout(hideAllLeadMenus, 180);
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.q-lead-combobox').length) {
                hideAllLeadMenus();
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Prefill (edit mode)                                                 */
    /* ------------------------------------------------------------------ */
    function applyPrefill(p) {
        if (!p) return;
        suspendItineraryRebuild();
        $('#q_id').val(p.id || '');
        $('[name=guest_name]').val(p.guest_name || '');
        $('[name=reference_name]').val(p.reference_name || '');
        $('[name=mobile_no]').val(p.mobile_no || '');
        $('[name=email]').val(p.email || '');
        $('[name=destination]').val(p.destination || '');
        $('#q_tentative_date').val(p.tentative_date && p.tentative_date !== '0000-00-00' ? p.tentative_date : '');
        $('#q_nights').val(p.no_of_nights || 0);
        $('#q_adults').val(p.no_of_adults || 1);
        $('#q_children').val(p.no_of_children || 0);

        var draft = loadFormDraftFromStorage();
        var flights = Array.isArray(p.flights) ? p.flights.slice() : [];
        if ((!flights || !flights.length) && draft && Array.isArray(draft.flights) && draft.flights.length) {
            flights = draft.flights;
        }
        renderFlightList(flights);

        var cs = p.cost_sheet || {};
        qPricingOptionsState = {};
        var legacyPricing = null;
        if (Array.isArray(cs.options) && cs.options.length) {
            cs.options.forEach(function (opt) {
                var id = String(opt.category_id || opt.id || '');
                if (!id) return;
                qPricingOptionsState[id] = {
                    fixed: opt.fixed || {},
                    custom: Array.isArray(opt.custom) ? opt.custom : [],
                    user_edited: opt.user_edited || {},
                    profit_percent: opt.profit_percent != null ? opt.profit_percent : '',
                    profit_amount: opt.profit_amount != null ? opt.profit_amount : '',
                    price_per_adult: opt.price_per_adult != null ? opt.price_per_adult : '',
                    price_per_adult_edited: parseInt(opt.price_per_adult_edited, 10) === 1 ? 1 : 0
                };
            });
            if (cs.active_option_id) {
                qActiveHotelCategoryId = String(cs.active_option_id);
            }
        } else {
            legacyPricing = {
                fixed: cs.fixed || {},
                custom: Array.isArray(cs.custom) ? cs.custom : [],
                user_edited: cs.user_edited || cs.manual || {},
                profit_percent: '',
                profit_amount: '',
                price_per_adult: '',
                price_per_adult_edited: 0
            };
            if (p.profit_type === 'amount') {
                legacyPricing.profit_amount = p.profit_value || '';
            } else if (parseFloat(p.profit_value) > 0) {
                legacyPricing.profit_percent = p.profit_value;
            }
            var savedPpa = parseFloat(p.price_per_adult);
            if (savedPpa > 0) {
                legacyPricing.price_per_adult = p.price_per_adult;
                legacyPricing.price_per_adult_edited = 1;
            }
            if (parseInt(legacyPricing.user_edited.flight_train, 10) === 1 || (p.id && legacyPricing.fixed.flight_train !== undefined && legacyPricing.fixed.flight_train !== '')) {
                legacyPricing.user_edited.flight_train = 1;
            }
            if (parseInt(legacyPricing.user_edited.hotel, 10) === 1 || (p.id && legacyPricing.fixed.hotel !== undefined && legacyPricing.fixed.hotel !== '')) {
                legacyPricing.user_edited.hotel = 1;
            }
        }

        // Same draft preference as flights/itinerary — session hotel edits must survive refresh.
        var hotelsToLoad = pickPreferredHotels(
            p.hotels || [],
            draft && draft.hotels ? draft.hotels : null
        );
        if (draft && draft.cost_sheet && Array.isArray(draft.cost_sheet.options) && !Array.isArray(cs.options)) {
            draft.cost_sheet.options.forEach(function (opt) {
                var id = String(opt.category_id || opt.id || '');
                if (!id || qPricingOptionsState[id]) return;
                qPricingOptionsState[id] = {
                    fixed: opt.fixed || {},
                    custom: Array.isArray(opt.custom) ? opt.custom : [],
                    user_edited: opt.user_edited || {},
                    profit_percent: opt.profit_percent != null ? opt.profit_percent : '',
                    profit_amount: opt.profit_amount != null ? opt.profit_amount : '',
                    price_per_adult: opt.price_per_adult != null ? opt.price_per_adult : '',
                    price_per_adult_edited: parseInt(opt.price_per_adult_edited, 10) === 1 ? 1 : 0
                };
            });
            if (draft.active_option_id || draft.cost_sheet.active_option_id) {
                qActiveHotelCategoryId = String(draft.active_option_id || draft.cost_sheet.active_option_id);
            }
        }
        renderHotelCategories(hotelsToLoad);

        if (legacyPricing) {
            var hotelData = collectHotelCategories();
            var activeId = String(qActiveHotelCategoryId || (hotelData.categories[0] && hotelData.categories[0].id) || '');
            hotelData.categories.forEach(function (cat) {
                var st = JSON.parse(JSON.stringify(legacyPricing));
                if (String(cat.id) !== activeId) {
                    st.fixed = st.fixed || {};
                    st.fixed.hotel = '';
                    st.user_edited = st.user_edited || {};
                    st.user_edited.hotel = 0;
                    st.price_per_adult = '';
                    st.price_per_adult_edited = 0;
                    st.profit_percent = '';
                    st.profit_amount = '';
                }
                qPricingOptionsState[cat.id] = st;
            });
            renderPricingSheets();
        }

        if (parseInt(p.without_itinerary, 10)) $('#q_without_itinerary').prop('checked', true);
        if (parseInt(p.hide_gst_note, 10)) $('#q_hide_gst_note').prop('checked', true);

        var notesVal = '';
        if (cs.pricing_notes != null && String(cs.pricing_notes) !== '') {
            notesVal = cs.pricing_notes;
        } else if (p.pricing_notes != null) {
            notesVal = p.pricing_notes;
        } else if (draft && draft.cost_sheet && draft.cost_sheet.pricing_notes != null) {
            notesVal = draft.cost_sheet.pricing_notes;
        } else if (draft && draft.pricing_notes != null) {
            notesVal = draft.pricing_notes;
        }
        applyPricingNotes(notesVal);

        var tourCostSrc = null;
        if (cs.tour_cost && typeof cs.tour_cost === 'object') {
            tourCostSrc = cs.tour_cost;
        } else if (draft && draft.cost_sheet && draft.cost_sheet.tour_cost) {
            tourCostSrc = draft.cost_sheet.tour_cost;
        } else if (parseFloat(p.price_per_adult) > 0) {
            tourCostSrc = {
                adult_rate: p.price_per_adult,
                adult_rate_edited: 1,
                child_rates: [],
                infant_rate: ''
            };
        }
        if (tourCostSrc) {
            applyTourCostState(tourCostSrc);
        } else {
            renderTourCostRows();
        }

        var itineraryMeta = cs.itinerary_meta || {};
        if (draft && draft.cost_sheet && draft.cost_sheet.itinerary_meta) {
            itineraryMeta = draft.cost_sheet.itinerary_meta;
        }
        applyItineraryMeta(itineraryMeta);

        var itinerary = Array.isArray(p.itinerary) ? p.itinerary : [];
        if ((!itinerary || !itinerary.length) && draft && Array.isArray(draft.itinerary) && draft.itinerary.length) {
            itinerary = draft.itinerary;
        }
        rebuildItinerary(itinerary);
        richEditors.forEach(function (field) {
            if (p[field]) {
                setRichEditorValue('qed_' + field, p[field]);
            }
        });
        resumeItineraryRebuild();
        if (!tourCostRowsPresent()) {
            renderTourCostRows();
        }
        recalcCosts();
        saveFormDraftToStorage();
    }

    /* ------------------------------------------------------------------ */
    /* Step wizard (single-page scroll mode)                               */
    /* ------------------------------------------------------------------ */
    var Q_WIZARD_TOTAL = 6;
    var Q_WIZARD_SCROLL_MODE = true;
    var qWizardCurrent = 1;
    var qWizardMax = 1;
    var qWizardScrollTick = false;
    var qWizardScrollSpyObserver = null;

    function wizardIds() {
        var quotationId = 0;
        var leadId = 0;
        if (QUOTATION_PREFILL && typeof QUOTATION_PREFILL === 'object') {
            quotationId = parseInt(QUOTATION_PREFILL.id, 10) || 0;
            leadId = parseInt(QUOTATION_PREFILL.lead_id, 10) || 0;
        }
        if (!quotationId) {
            quotationId = parseInt($('#q_id').val() || $('[name="id"]').val() || 0, 10) || 0;
        }
        if (!leadId) {
            leadId = parseInt($('#q_lead_id').val() || $('[name="lead_id"]').val() || 0, 10) || 0;
        }
        return { quotationId: quotationId, leadId: leadId };
    }

    function wizardStorageKey() {
        var ids = wizardIds();
        if (ids.quotationId > 0) {
            return 'qWizardState:id:' + ids.quotationId;
        }
        if (ids.leadId > 0) {
            return 'qWizardState:lead:' + ids.leadId;
        }
        return 'qWizardState:new';
    }

    function formDraftStorageKey() {
        return wizardStorageKey() + ':formDraft';
    }

    function formDraftCandidateKeys() {
        var ids = wizardIds();
        var keys = [];
        if (ids.quotationId > 0) {
            keys.push('qWizardState:id:' + ids.quotationId + ':formDraft');
        }
        if (ids.leadId > 0) {
            keys.push('qWizardState:lead:' + ids.leadId + ':formDraft');
        }
        keys.push('qWizardState:new:formDraft');
        keys.push(formDraftStorageKey());
        return keys.filter(function (key, idx, arr) {
            return arr.indexOf(key) === idx;
        });
    }

    function hotelEntryFillScore(h) {
        if (!h || typeof h !== 'object') {
            return 0;
        }
        var score = 0;
        ['city', 'name', 'hotel_name', 'room_type', 'meal_plan', 'meal', 'checkin', 'check_in', 'checkout', 'check_out', 'rate', 'amount', 'rooms', 'nights', 'city_id', 'hotel_id'].forEach(function (key) {
            var v = h[key];
            if (v == null) return;
            var s = String(v).trim();
            if (s === '' || s === '0') return;
            score += 1;
        });
        return score;
    }

    function hotelCategoriesScore(raw) {
        var data = normalizeHotelsPrefill(raw);
        var hotelCount = 0;
        var fillScore = 0;
        (data.categories || []).forEach(function (cat) {
            (cat.hotels || []).forEach(function (h) {
                hotelCount += 1;
                fillScore += hotelEntryFillScore(h);
            });
        });
        return {
            optionCount: (data.categories || []).length,
            hotelCount: hotelCount,
            fillScore: fillScore,
            data: data
        };
    }

    function pickPreferredHotels(prefillHotels, draftHotels) {
        var fromDb = hotelCategoriesScore(prefillHotels || []);
        if (!draftHotels) {
            return fromDb.data;
        }
        var fromDraft = hotelCategoriesScore(draftHotels);
        var draftUseful = fromDraft.hotelCount > 0 || fromDraft.fillScore > 0 || fromDraft.optionCount > 1;
        if (!draftUseful) {
            return fromDb.data;
        }
        // Prefer session draft whenever it has equal/more filled hotel content.
        // (Do not let empty multi-option DB shape beat a filled single-option draft.)
        if (fromDb.hotelCount === 0 && fromDb.fillScore === 0) {
            return fromDraft.data;
        }
        if (fromDraft.fillScore > fromDb.fillScore) {
            return fromDraft.data;
        }
        if (fromDraft.fillScore === fromDb.fillScore && fromDraft.hotelCount >= fromDb.hotelCount) {
            return fromDraft.data;
        }
        if (fromDraft.hotelCount > fromDb.hotelCount) {
            return fromDraft.data;
        }
        return fromDb.data;
    }

    function saveFormDraftToStorage() {
        try {
            var hotels = collectHotelCategories();
            var flights = collectFlights();
            var itinerary = snapshotItinerary();
            var costSheet = null;
            var activeOptionId = qActiveHotelCategoryId || '';
            try {
                snapshotPricingSheets();
                costSheet = collectPricingOptionsPayload();
                activeOptionId = (costSheet && costSheet.active_option_id) || activeOptionId;
            } catch (pricingErr) { /* pricing must not block hotel/flight draft save */ }

            // If hotel UI isn't mounted yet, keep previously saved hotels.
            if (!getHotelCategoryPanels().length) {
                var existing = loadFormDraftFromStorage();
                if (existing && existing.hotels) {
                    var scoreOld = hotelCategoriesScore(existing.hotels);
                    if (scoreOld.hotelCount > 0 || scoreOld.fillScore > 0) {
                        hotels = existing.hotels;
                    }
                }
            }

            var payload = {
                saved_at: Date.now(),
                hotels: hotels,
                flights: flights,
                itinerary: itinerary,
                cost_sheet: costSheet,
                active_option_id: activeOptionId
            };
            var raw = JSON.stringify(payload);
            // Write to all candidate keys so lead/id/new URL switches don't lose hotel draft.
            formDraftCandidateKeys().forEach(function (key) {
                try {
                    sessionStorage.setItem(key, raw);
                } catch (eKey) {}
            });
        } catch (e) {}
    }

    function loadFormDraftFromStorage() {
        var best = null;
        formDraftCandidateKeys().forEach(function (key) {
            try {
                var raw = sessionStorage.getItem(key);
                if (!raw) {
                    return;
                }
                var parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object') {
                    return;
                }
                var ts = parseInt(parsed.saved_at, 10) || 0;
                var fill = hotelCategoriesScore(parsed.hotels || []).fillScore;
                if (!best) {
                    best = parsed;
                    best.saved_at = ts;
                    best._fill = fill;
                    return;
                }
                // Prefer newer drafts; if timestamps tie, prefer more filled hotel data.
                if (ts > best.saved_at || (ts === best.saved_at && fill > (best._fill || 0))) {
                    best = parsed;
                    best.saved_at = ts;
                    best._fill = fill;
                }
            } catch (e) {}
        });
        if (best) {
            delete best._fill;
        }
        return best;
    }

    function clearFormDraftFromStorage() {
        formDraftCandidateKeys().forEach(function (key) {
            try {
                sessionStorage.removeItem(key);
            } catch (e) {}
        });
    }

    function resolveHotelsForLoad(prefillHotels) {
        var draft = loadFormDraftFromStorage();
        return pickPreferredHotels(prefillHotels || [], draft && draft.hotels ? draft.hotels : null);
    }

    function saveWizardState() {
        try {
            sessionStorage.setItem(wizardStorageKey(), JSON.stringify({
                step: qWizardCurrent,
                max: qWizardMax
            }));
        } catch (e) {}
        saveFormDraftToStorage();
    }

    function loadWizardState() {
        try {
            var raw = sessionStorage.getItem(wizardStorageKey());
            if (!raw) {
                return null;
            }
            var data = JSON.parse(raw);
            if (!data || typeof data !== 'object') {
                return null;
            }
            var step = parseInt(data.step, 10) || 1;
            var max = parseInt(data.max, 10) || step;
            step = Math.max(1, Math.min(Q_WIZARD_TOTAL, step));
            max = Math.max(step, Math.min(Q_WIZARD_TOTAL, max));
            return { step: step, max: max };
        } catch (e) {
            return null;
        }
    }

    function validateWizardStep(step) {
        if (step === 1) {
            var guestName = $.trim($('[name=guest_name]').val());
            if (!guestName) {
                alert('Please enter the guest name.');
                $('[name=guest_name]').focus();
                return false;
            }
        }
        return true;
    }

    function getWizardSectionEl(step) {
        return document.getElementById('qWizardSection' + step) ||
            document.querySelector('.q-wizard-step[data-q-step="' + step + '"]');
    }

    function scrollToWizardStep(step, behavior) {
        var el = getWizardSectionEl(step);
        if (!el) {
            return;
        }
        qWizardScrollTick = true;
        el.scrollIntoView({
            behavior: behavior || 'smooth',
            block: 'start'
        });
        window.setTimeout(function () {
            qWizardScrollTick = false;
        }, behavior === 'auto' ? 50 : 650);
    }

    function syncUnlockedWizardSections() {
        if (!Q_WIZARD_SCROLL_MODE) {
            return;
        }
        $('.q-wizard-step').each(function () {
            var step = parseInt($(this).attr('data-q-step'), 10) || 0;
            var unlocked = step > 0 && step <= qWizardMax;
            $(this).toggleClass('is-unlocked', unlocked);
            $(this).toggleClass('is-last-unlocked', step === qWizardMax);
            // Hide Next on sections that are no longer the furthest unlocked,
            // except keep Next on every unlocked section before Pricing.
            var $nextBar = $(this).find('.q-section-next-bar');
            if ($nextBar.length) {
                $nextBar.toggle(unlocked && step < Q_WIZARD_TOTAL);
            }
        });
    }

    function initAllWizardSectionsVisible() {
        if (!Q_WIZARD_SCROLL_MODE) {
            return;
        }
        qWizardMax = Q_WIZARD_TOTAL;
        syncUnlockedWizardSections();
    }

    function ensureWizardSectionsReady() {
        var max = Math.max(1, Math.min(Q_WIZARD_TOTAL, qWizardMax || 1));
        for (var step = 1; step <= max; step++) {
            if (step === 4 || step === 6) {
                onWizardStepShown(step);
            }
        }
    }

    function updateWizardStepperUi(options) {
        options = options || {};
        $('.q-stepper-item').each(function () {
            var step = parseInt($(this).data('qStep'), 10);
            var $item = $(this);
            $item.removeClass('is-active is-complete is-locked');
            if (step < qWizardCurrent) {
                $item.addClass('is-complete');
            } else if (step === qWizardCurrent) {
                $item.addClass('is-active');
            } else if (step > qWizardMax) {
                $item.addClass('is-locked');
            }
            $item.attr('aria-current', step === qWizardCurrent ? 'step' : 'false');
            // Always keep every step visible in the top navigation.
            $item.show();
        });
        if (!Q_WIZARD_SCROLL_MODE) {
            $('#qWizardStepIndicator').text('Step ' + qWizardCurrent + ' of ' + Q_WIZARD_TOTAL);
            $('#qWizardPrev').css('visibility', qWizardCurrent <= 1 ? 'hidden' : 'visible');
            $('#qWizardNext').toggle(qWizardCurrent < Q_WIZARD_TOTAL);
        }
        if (Q_WIZARD_SCROLL_MODE) {
            syncUnlockedWizardSections();
        }
        if (options.save !== false) {
            saveWizardState();
        }
    }

    function onWizardStepShown(step) {
        if (step === 4) {
            refreshAiItineraryMeta();
            if (!$('#qItineraryDays .q-day-card').length) {
                rebuildItinerary(itineraryPreserveSeed.length ? itineraryPreserveSeed : undefined);
            } else {
                initItineraryEditors();
                refreshAllItineraryImagePreviews();
            }
        }
        if (step === 6) {
            if (!$('#qPricingSheetsHost .q-pricing-option-sheet').length) {
                renderPricingSheets();
            } else if (typeof recalcCosts === 'function') {
                recalcCosts();
            }
        }
    }

    function setWizardStep(step, scroll) {
        step = Math.max(1, Math.min(Q_WIZARD_TOTAL, step));
        if (step > qWizardMax) {
            return false;
        }
        if (!Q_WIZARD_SCROLL_MODE && step > qWizardCurrent && !validateWizardStep(qWizardCurrent)) {
            return false;
        }
        qWizardCurrent = step;
        if (qWizardCurrent > qWizardMax) {
            qWizardMax = qWizardCurrent;
        }
        if (Q_WIZARD_SCROLL_MODE) {
            $('.q-wizard-step').removeClass('is-active');
            $('.q-wizard-step[data-q-step="' + step + '"]').addClass('is-active');
            updateWizardStepperUi();
            onWizardStepShown(step);
            expandWizardSection(step);
            if (scroll !== false) {
                scrollToWizardStep(step);
            }
            return true;
        }
        $('.q-wizard-step').removeClass('is-active');
        $('.q-wizard-step[data-q-step="' + step + '"]').addClass('is-active');
        updateWizardStepperUi();
        onWizardStepShown(step);
        if (scroll !== false) {
            var wizardEl = document.getElementById('qWizard');
            if (wizardEl) {
                wizardEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        return true;
    }

    function advanceWizardFrom(fromStep) {
        fromStep = parseInt(fromStep, 10) || qWizardCurrent;
        fromStep = Math.max(1, Math.min(Q_WIZARD_TOTAL, fromStep));
        if (!validateWizardStep(fromStep)) {
            return false;
        }
        if (fromStep >= Q_WIZARD_TOTAL) {
            return setWizardStep(Q_WIZARD_TOTAL, true);
        }
        var next = fromStep + 1;
        var newlyUnlocked = next > qWizardMax;
        if (newlyUnlocked) {
            qWizardMax = next;
            syncUnlockedWizardSections();
            initWizardScrollSpy();
        }
        return setWizardStep(next, true);
    }

    function unlockAllWizardSteps() {
        qWizardMax = Q_WIZARD_TOTAL;
        syncUnlockedWizardSections();
        updateWizardStepperUi();
    }

    function initWizardScrollSpy() {
        if (!Q_WIZARD_SCROLL_MODE || !window.IntersectionObserver) {
            return;
        }
        if (qWizardScrollSpyObserver) {
            qWizardScrollSpyObserver.disconnect();
            qWizardScrollSpyObserver = null;
        }
        var sections = document.querySelectorAll('.q-wizard-step.is-unlocked[data-q-step]');
        if (!sections.length) {
            return;
        }
        qWizardScrollSpyObserver = new IntersectionObserver(function (entries) {
            if (qWizardScrollTick) {
                return;
            }
            var visible = [];
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    visible.push({
                        step: parseInt(entry.target.getAttribute('data-q-step'), 10) || 0,
                        ratio: entry.intersectionRatio,
                        top: entry.boundingClientRect.top
                    });
                }
            });
            if (!visible.length) {
                return;
            }
            visible.sort(function (a, b) {
                if (b.ratio !== a.ratio) {
                    return b.ratio - a.ratio;
                }
                return a.top - b.top;
            });
            var nextStep = visible[0].step;
            if (!nextStep || nextStep === qWizardCurrent || nextStep > qWizardMax) {
                return;
            }
            qWizardCurrent = nextStep;
            updateWizardStepperUi({ save: true });
        }, {
            root: null,
            rootMargin: '-96px 0px -58% 0px',
            threshold: [0.08, 0.2, 0.35, 0.5]
        });
        sections.forEach(function (section) {
            qWizardScrollSpyObserver.observe(section);
        });
    }

    function restoreWizardStepOnLoad() {
        var saved = loadWizardState();
        var startStep = 1;
        var maxStep = 1;
        var isExistingSaved = !!(QUOTATION_PREFILL && QUOTATION_PREFILL.id && QUOTATION_PREFILL.status !== 'draft');

        if (isExistingSaved) {
            maxStep = Q_WIZARD_TOTAL;
            startStep = saved && saved.step ? saved.step : 1;
        } else if (saved) {
            startStep = saved.step;
            maxStep = saved.max || saved.step || 1;
        } else if (QUOTATION_PREFILL && QUOTATION_PREFILL.status === 'draft') {
            startStep = parseInt(QUOTATION_PREFILL.wizard_step, 10) || 1;
            maxStep = startStep;
        }

        startStep = Math.max(1, Math.min(Q_WIZARD_TOTAL, startStep));
        maxStep = Math.max(1, Math.min(Q_WIZARD_TOTAL, maxStep));
        if (startStep > maxStep) {
            maxStep = startStep;
        }

        qWizardCurrent = startStep;
        qWizardMax = maxStep;
        syncUnlockedWizardSections();
        ensureWizardSectionsReady();
        updateWizardStepperUi({ save: false });

        if (Q_WIZARD_SCROLL_MODE) {
            initWizardScrollSpy();
            expandWizardSection(startStep);
            window.setTimeout(function () {
                scrollToWizardStep(startStep, 'auto');
            }, 120);
        } else {
            setWizardStep(startStep, false);
        }
    }

    function initQuotationWizard() {
        if (!$('#qWizard').length) {
            return;
        }
        if (Q_WIZARD_SCROLL_MODE) {
            qWizardMax = Math.max(1, qWizardMax || 1);
            syncUnlockedWizardSections();
        }
        updateWizardStepperUi({ save: false });
        if (!Q_WIZARD_SCROLL_MODE) {
            $('#qWizardNext').on('click', function () {
                setWizardStep(qWizardCurrent + 1);
            });
            $('#qWizardPrev').on('click', function () {
                setWizardStep(qWizardCurrent - 1, false);
            });
        }
        $(document).on('click', '.q-section-next-btn', function () {
            var from = parseInt($(this).attr('data-q-next-from'), 10) || qWizardCurrent;
            advanceWizardFrom(from);
        });
        $(document).on('click', '.q-qty-btn', function () {
            var targetId = String($(this).attr('data-qty-target') || '');
            var dir = parseInt($(this).attr('data-qty-dir'), 10) || 0;
            var $input = $('#' + targetId);
            if (!$input.length || !dir) {
                return;
            }
            var min = parseInt($input.attr('min'), 10);
            if (isNaN(min)) min = 0;
            var maxAttr = $input.attr('max');
            var max = maxAttr != null && maxAttr !== '' ? parseInt(maxAttr, 10) : null;
            var val = parseInt($input.val(), 10);
            if (isNaN(val)) val = min;
            val += dir;
            if (val < min) val = min;
            if (max != null && !isNaN(max) && val > max) val = max;
            $input.val(val).trigger('change').trigger('input');
        });
        $(document).on('click', '.q-stepper-item:not(.is-locked)', function () {
            var target = parseInt($(this).data('qStep'), 10);
            if (!target) {
                return;
            }
            if (target > qWizardMax) {
                $(this).blur();
                return;
            }
            if (target === qWizardCurrent && Q_WIZARD_SCROLL_MODE) {
                scrollToWizardStep(target);
                expandWizardSection(target);
                $(this).blur();
                return;
            }
            if (!Q_WIZARD_SCROLL_MODE && target > qWizardCurrent && !validateWizardStep(qWizardCurrent)) {
                $(this).blur();
                return;
            }
            setWizardStep(target, true);
            $(this).blur();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Destination picker                                                  */
    /* ------------------------------------------------------------------ */
    function initDestinationPicker() {
        var $picker = $('#qDestPicker');
        var $input = $('#qDestinationInput');
        var $menu = $picker.find('.js-q-dest-menu');
        var $toggle = $picker.find('.js-q-dest-toggle');
        if (!$picker.length || !$input.length || !$menu.length) {
            return;
        }

        var destinations = Array.isArray(window.QUOTATION_DESTINATIONS)
            ? window.QUOTATION_DESTINATIONS.slice()
            : [];
        var activeIndex = -1;

        function positionMenu() {
            var rect = $input[0].getBoundingClientRect();
            var viewportH = window.innerHeight || document.documentElement.clientHeight || 0;
            var spaceBelow = viewportH - rect.bottom - 8;
            var spaceAbove = rect.top - 8;
            var maxHeight = 240;
            var openUp = spaceBelow < 160 && spaceAbove > spaceBelow;
            var height = Math.max(120, Math.min(maxHeight, openUp ? spaceAbove : spaceBelow));
            var width = Math.max(rect.width, 220);

            $menu.css({
                position: 'fixed',
                left: rect.left + 'px',
                width: width + 'px',
                maxHeight: height + 'px',
                top: openUp ? 'auto' : (rect.bottom + 4) + 'px',
                bottom: openUp ? (viewportH - rect.top + 4) + 'px' : 'auto',
                zIndex: 2000
            });
        }

        function closeMenu() {
            $menu.hide().empty();
            $picker.removeClass('is-open');
            $input.attr('aria-expanded', 'false');
            activeIndex = -1;
        }

        function openMenu() {
            renderMenu(String($input.val() || ''));
            positionMenu();
            $menu.show();
            $picker.addClass('is-open');
            $input.attr('aria-expanded', 'true');
        }

        function filteredDestinations(query) {
            var q = String(query || '').trim().toLowerCase();
            if (!q) {
                return destinations.slice(0, 50);
            }
            return destinations.filter(function (name) {
                return String(name).toLowerCase().indexOf(q) >= 0;
            }).slice(0, 50);
        }

        function renderMenu(query) {
            var items = filteredDestinations(query);
            $menu.empty();
            activeIndex = -1;

            if (!items.length) {
                var typed = String(query || '').trim();
                if (typed) {
                    $menu.append(
                        $('<div class="q-dest-empty"></div>').html(
                            'No match. Press Enter to use <strong>' + esc(typed) + '</strong>'
                        )
                    );
                } else {
                    $menu.append($('<div class="q-dest-empty"></div>').text('No destinations found'));
                }
                return;
            }

            items.forEach(function (name, index) {
                var $btn = $('<button type="button" class="q-dest-item" role="option"></button>')
                    .attr('data-index', index)
                    .attr('data-value', name)
                    .append($('<i class="fas fa-map-marker-alt"></i>'))
                    .append($('<span></span>').text(name));
                $menu.append($btn);
            });
        }

        function selectDestination(name) {
            $input.val(name).trigger('change').trigger('input');
            closeMenu();
        }

        function highlightActive() {
            var $items = $menu.find('.q-dest-item');
            $items.removeClass('is-active');
            if (activeIndex >= 0 && activeIndex < $items.length) {
                var $active = $items.eq(activeIndex).addClass('is-active');
                var el = $active.get(0);
                if (el && el.scrollIntoView) {
                    el.scrollIntoView({ block: 'nearest' });
                }
            }
        }

        $input.on('focus', function () {
            openMenu();
        });

        $input.on('input', function () {
            openMenu();
        });

        $toggle.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if ($menu.is(':visible')) {
                closeMenu();
            } else {
                $input.trigger('focus');
                openMenu();
            }
        });

        $menu.on('mousedown', '.q-dest-item', function (e) {
            e.preventDefault();
            selectDestination(String($(this).data('value') || ''));
        });

        $input.on('keydown', function (e) {
            var $items = $menu.find('.q-dest-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!$menu.is(':visible')) {
                    openMenu();
                }
                activeIndex = Math.min($items.length - 1, activeIndex + 1);
                highlightActive();
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(0, activeIndex - 1);
                highlightActive();
                return;
            }
            if (e.key === 'Enter') {
                if ($menu.is(':visible') && activeIndex >= 0 && activeIndex < $items.length) {
                    e.preventDefault();
                    selectDestination(String($items.eq(activeIndex).data('value') || ''));
                    return;
                }
                closeMenu();
                return;
            }
            if (e.key === 'Escape') {
                closeMenu();
            }
        });

        $(document).on('mousedown.qDestPicker', function (e) {
            if (!$(e.target).closest('#qDestPicker, .js-q-dest-menu').length) {
                closeMenu();
            }
        });

        function onViewportChange() {
            if ($menu.is(':visible')) {
                positionMenu();
            }
        }

        $(window).on('resize.qDestPicker', onViewportChange);
        document.addEventListener('scroll', onViewportChange, true);
    }

    /* ------------------------------------------------------------------ */
    /* Wire events                                                         */
    /* ------------------------------------------------------------------ */
    $(function () {
        initQuotationWizard();
        initRichEditors();
        initLeadLookup();
        initDestinationPicker();
        initPackageSuggest();
        initAISuggestDay();
        initPreviewInlineEditing();

        function addFlightSegment(data) {
            var $row = $(flightRowHtml(data || {}));
            $('#qFlightRows').append($row);
            qInitSupplierSelect2($row.find('.f-supplier'), { placeholder: 'Select' });
            renumberFlightRows();
            recalcCosts();
        }

        $('#qAddFlight, #qAddFlightSegment').on('click', function () {
            addFlightSegment({});
        });

        function setFlightActionActive($el) {
            var $actions = $('.q-flight-actions .q-flight-btn');
            $actions.removeClass('is-active q-flight-btn-red').addClass('q-flight-btn-outline');
            $el.removeClass('q-flight-btn-outline').addClass('q-flight-btn-red is-active');
        }
        $(document).on('click', '.q-flight-actions .q-flight-btn', function () {
            setFlightActionActive($(this));
        });
        window.qQuotationAddFlightRow = function (data) {
            addFlightSegment(data || {});
        };
        window.qQuotationAddFlightJourney = function (rows, opts) {
            appendFlightJourneyCard(rows || [], opts || {});
            qInitSupplierSelect2In($('#qFlightRows'));
            renumberFlightRows();
            recalcCosts();
        };

        $('#qUploadSsInput').on('change', function () {
            var file = this.files && this.files[0];
            $('#qUploadSsLabel').text(file ? ('Screenshot attached: ' + file.name) : '');
        });
        window.qQuotationAddHotelRow = function (data) {
            ensureHotelCategoriesReady();
            var $panel = getHotelCategoryPanels().filter('[data-cat-id="' + qActiveHotelCategoryId + '"]').first();
            if (!$panel.length) {
                $panel = getHotelCategoryPanels().first();
            }
            var $row = $(hotelRowHtml(data || {}));
            $panel.find('.q-hotel-rows').append($row);
            initHotelRow($row);
            qInitSupplierSelect2($row.find('.h-supplier'), { placeholder: 'Select supplier' });
            recalcCosts();
            saveFormDraftToStorage();
        };
        $(document).on('input change', '#qFlightRows .f-from, #qFlightRows .f-to, #qFlightRows .f-dep-date, #qFlightRows .f-dep-time, #qFlightRows .f-arr-date, #qFlightRows .f-arr-time', function () {
            refreshFlightLayovers();
        });

        $(document).on('change', '#qFlightRows .f-supplier', function () {
            var $sel = $(this);
            var prev = $sel.data('prevSupplierVal');
            if (typeof prev === 'undefined') {
                prev = '';
            }
            if (String($sel.val() || '') === '__create__') {
                $sel.val(prev || '').trigger('change.select2');
                openFlightSupplierCreateModal($sel);
                return;
            }
            $sel.data('prevSupplierVal', $sel.val() || '');
        });

        $(document).on('focus', '#qFlightRows .f-supplier', function () {
            $(this).data('prevSupplierVal', $(this).val() || '');
        });

        window.qOnFlightSupplierCreated = function (payload) {
            payload = payload || {};
            var id = parseInt(payload.id, 10) || 0;
            var name = String(payload.name || '').trim();
            if (id < 1 || !name) {
                return;
            }
            upsertFlightSupplierInList(id, name);
            // Also surface in hotel/itinerary supplier list when useful later.
            upsertHotelSupplierInList(id, name);
            refreshItinerarySupplierSelect();
            var prefer = {
                $el: qFlightSupplierCreateTarget,
                id: id,
                name: name
            };
            refreshAllFlightSupplierSelects(prefer);
            qFlightSupplierCreateTarget = null;
            window.qSupplierCreateContext = 'mail';
            saveFormDraftToStorage();
        };

        window.qOnItinerarySupplierCreated = function (payload) {
            payload = payload || {};
            var id = parseInt(payload.id, 10) || 0;
            var name = String(payload.name || '').trim();
            if (id < 1 || !name) {
                return;
            }
            upsertHotelSupplierInList(id, name);
            refreshItinerarySupplierSelect({
                $el: qItinerarySupplierCreateTarget,
                id: id,
                name: name
            });
            refreshAllHotelSupplierSelects();
            qItinerarySupplierCreateTarget = null;
            window.qSupplierCreateContext = 'mail';
            saveFormDraftToStorage();
        };

        window.qOnHotelSupplierCreated = function (payload) {
            payload = payload || {};
            var id = parseInt(payload.id, 10) || 0;
            var name = String(payload.name || '').trim();
            if (id < 1 || !name) {
                return;
            }
            upsertHotelSupplierInList(id, name);
            refreshItinerarySupplierSelect();
            refreshAllHotelSupplierSelects({
                $el: qHotelSupplierCreateTarget,
                id: id,
                name: name
            });
            qHotelSupplierCreateTarget = null;
            window.qSupplierCreateContext = 'mail';
            saveFormDraftToStorage();
        };

        $(document).on('change', '.q-hotel-rows .h-supplier', function () {
            var $sel = $(this);
            var prev = $sel.data('prevSupplierVal');
            if (typeof prev === 'undefined') {
                prev = '';
            }
            if (String($sel.val() || '') === '__create__') {
                $sel.val(prev || '').trigger('change.select2');
                openHotelSupplierCreateModal($sel);
                return;
            }
            $sel.data('prevSupplierVal', $sel.val() || '');
        });

        $(document).on('focus', '.q-hotel-rows .h-supplier', function () {
            $(this).data('prevSupplierVal', $(this).val() || '');
        });

        $(document).on('change', '#qItinerarySupplierRows .q-itin-supplier', function () {
            var $sel = $(this);
            var prev = $sel.data('prevSupplierVal');
            if (typeof prev === 'undefined') {
                prev = '';
            }
            if (String($sel.val() || '') === '__create__') {
                $sel.val(prev || '').trigger('change.select2');
                openItinerarySupplierCreateModal($sel);
                return;
            }
            $sel.data('prevSupplierVal', $sel.val() || '');
            saveFormDraftToStorage();
        });

        $(document).on('focus', '#qItinerarySupplierRows .q-itin-supplier', function () {
            $(this).data('prevSupplierVal', $(this).val() || '');
        });

        $(document).on('input change', '#qItinerarySupplierRows .q-itin-rate', function () {
            saveFormDraftToStorage();
        });

        $('#qAddItinerarySupplier').on('click', function () {
            addItinerarySupplierRow({});
            saveFormDraftToStorage();
        });

        $(document).on('click', '.q-itin-supplier-remove', function () {
            var $rows = $('#qItinerarySupplierRows .q-itin-supplier-row');
            if ($rows.length <= 1) {
                return;
            }
            var $row = $(this).closest('.q-itin-supplier-row');
            qDestroySupplierSelect2($row.find('.q-itin-supplier'));
            $row.remove();
            refreshItinerarySupplierRemoveState();
            saveFormDraftToStorage();
        });

        $('#qSupplierCreateModal').on('hidden.bs.modal', function () {
            if (window.qSupplierCreateContext === 'flight'
                || window.qSupplierCreateContext === 'itinerary'
                || window.qSupplierCreateContext === 'hotel') {
                window.qSupplierCreateContext = 'mail';
            }
            qFlightSupplierCreateTarget = null;
            qItinerarySupplierCreateTarget = null;
            qHotelSupplierCreateTarget = null;
        });

        $(document).on('click', '.q-flight-swap', function () {
            var $row = $(this).closest('.q-flight-row');
            var $from = $row.find('.f-from');
            var $to = $row.find('.f-to');
            var tmp = $from.val();
            $from.val($to.val());
            $to.val(tmp);
            refreshFlightLayovers();
        });
        $('#qSearchTrain').on('click', function () { alert('Live train search is not configured. Use "Add Flight /Train" to enter details manually.'); });
        $('#qAddHotelCategory').on('click', function () {
            if (getHotelCategoryPanels().length >= Q_MAX_HOTEL_OPTIONS) {
                refreshHotelCategoryTabs();
                return;
            }
            addHotelCategory();
            saveFormDraftToStorage();
        });
        $(document).on('click', '.q-hotel-cat-tab', function () {
            setActiveHotelCategory($(this).attr('data-cat-id'));
            saveFormDraftToStorage();
        });
        $(document).on('input', '.q-hotel-cat-label', function () {
            refreshHotelCategoryTabs();
            renderPricingSheets();
            saveFormDraftToStorage();
        });
        $(document).on('click', '.q-add-hotel-in-cat', function () {
            var $panel = $(this).closest('.q-hotel-category');
            qActiveHotelCategoryId = String($panel.attr('data-cat-id') || '');
            refreshHotelCategoryTabs();
            var $row = $(hotelRowHtml({}));
            $panel.find('.q-hotel-rows').append($row);
            initHotelRow($row);
            qInitSupplierSelect2($row.find('.h-supplier'), { placeholder: 'Select supplier' });
            renderPricingSheets();
            saveFormDraftToStorage();
        });
        $(document).on('click', '.q-remove-hotel-cat', function () {
            if (getHotelCategoryPanels().length <= 1) {
                return;
            }
            if (!window.confirm('Remove this hotel option and its hotels?')) {
                return;
            }
            var $panel = $(this).closest('.q-hotel-category');
            var removedId = String($panel.attr('data-cat-id') || '');
            snapshotPricingSheets();
            $panel.remove();
            if (qPricingOptionsState[removedId]) {
                delete qPricingOptionsState[removedId];
            }
            renumberHotelCategoryLabels();
            if (qActiveHotelCategoryId === removedId || !getHotelCategoryPanels().filter('[data-cat-id="' + String(qActiveHotelCategoryId).replace(/"/g, '\\"') + '"]').length) {
                qActiveHotelCategoryId = String(getHotelCategoryPanels().first().attr('data-cat-id') || '');
            }
            refreshHotelCategoryTabs();
            renderPricingSheets();
            saveFormDraftToStorage();
        });
        $(document).on('click', '.q-set-active-pricing', function () {
            setActiveHotelCategory($(this).attr('data-cat-id'));
            saveFormDraftToStorage();
        });
        $(document).on('click', '.q-add-cost-row', function () {
            var $sheet = $(this).closest('.q-pricing-option-sheet');
            var id = String($sheet.attr('data-cat-id') || '');
            snapshotPricingSheets();
            if (!id) {
                return;
            }
            if (!qPricingOptionsState[id]) {
                qPricingOptionsState[id] = defaultPricingSheetState();
            }
            qPricingOptionsState[id].custom = qPricingOptionsState[id].custom || [];
            qPricingOptionsState[id].custom.push({ label: '', amount: '' });
            renderPricingSheets();
        });
        $(document).on('input change', '#qHotelCategories .q-hotel-row input, #qHotelCategories .q-hotel-row select', function () {
            saveFormDraftToStorage();
        });
        $(document).on('input change', '#qFlightRows input, #qFlightRows select', function () {
            saveFormDraftToStorage();
        });
        $(window).on('beforeunload', function () {
            saveFormDraftToStorage();
        });
        $(document).on('change input', '[name=destination]', function () {
            hideHotelMenus();
        });
        var qHotelCityTimer = null;
        var qHotelNameTimer = null;

        $(document).on('focus click', '.h-city', function () {
            showHotelCitySuggestions($(this).closest('.q-hotel-row'));
        });
        $(document).on('input', '.h-city', function () {
            var $input = $(this);
            var $row = $input.closest('.q-hotel-row');
            $row.find('.h-city-id').val('');
            $row.find('.h-hotel-id').val('');
            clearTimeout(qHotelCityTimer);
            qHotelCityTimer = setTimeout(function () {
                showHotelCitySuggestions($row);
            }, 250);
        });
        $(document).on('mousedown', '.q-hotel-city-pick', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.q-hotel-row');
            var cityId = $(this).data('id');
            var cityName = $(this).attr('data-name') || $(this).text();
            $row.find('.h-city-id').val(cityId);
            $row.find('.h-city').val(cityName);
            hideHotelMenus($row);
            if ($.trim($row.find('.h-name').val())) {
                showHotelNameSuggestions($row);
            }
            saveFormDraftToStorage();
        });

        $(document).on('mousedown', '.q-hotel-city-create', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $row = $(this).closest('.q-hotel-row');
            var name = $(this).attr('data-name') || $.trim($row.find('.h-city').val());
            hideHotelMenus($row);
            openQCityCreateModal(name, $row);
        });

        $(document).on('mousedown', '.q-hotel-name-create', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $row = $(this).closest('.q-hotel-row');
            var name = $(this).attr('data-name') || $.trim($row.find('.h-name').val());
            hideHotelMenus($row);
            openQHotelCreateModal(name, $row);
        });

        $(document).on('change', '#qCityCreateCountry', function () {
            loadQCityCreateStates($(this).val());
        });

        $('#qCityCreateForm').on('submit', function (e) {
            e.preventDefault();
            saveQCityCreateForm();
        });

        $('#qCityCreateModal').on('shown.bs.modal', function () {
            $('#qCityCreateName').trigger('focus');
        }).on('hidden.bs.modal', function () {
            qCityCreateTargetRow = null;
            resetQCityCreateForm();
        });

        $('#qHotelCreateForm').on('submit', function (e) {
            e.preventDefault();
            saveQHotelCreateForm();
        });

        $('#qHotelCreateModal').on('shown.bs.modal', function () {
            $('#qHotelCreateName').trigger('focus');
        }).on('hidden.bs.modal', function () {
            qHotelCreateTargetRow = null;
            resetQHotelCreateForm();
        });

        $(document).on('focus click', '.h-name', function () {
            showHotelNameSuggestions($(this).closest('.q-hotel-row'));
        });
        $(document).on('input', '.h-name', function () {
            var $row = $(this).closest('.q-hotel-row');
            $row.find('.h-hotel-id').val('');
            clearTimeout(qHotelNameTimer);
            qHotelNameTimer = setTimeout(function () {
                showHotelNameSuggestions($row);
            }, 250);
        });
        $(document).on('mousedown', '.q-hotel-name-pick', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.q-hotel-row');
            var hotelId = parseInt($(this).data('id'), 10);
            var cache = getHotelRowCache($row);
            var hotel = (cache.hotels || []).find(function (h) { return parseInt(h.id, 10) === hotelId; });
            if (hotel) {
                applyHotelMasterToRow($row, hotel, { forceFill: true });
            }
            hideHotelMenus($row);
            recalcCosts();
            saveFormDraftToStorage();
        });

        var qHotelRoomTimer = null;
        var qHotelMealTimer = null;

        $(document).on('focus click', '.h-room', function () {
            showHotelRoomSuggestions($(this).closest('.q-hotel-row'));
        });
        $(document).on('input', '.h-room', function () {
            var $row = $(this).closest('.q-hotel-row');
            clearTimeout(qHotelRoomTimer);
            qHotelRoomTimer = setTimeout(function () {
                showHotelRoomSuggestions($row);
            }, 200);
        });
        $(document).on('mousedown', '.q-hotel-room-pick', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.q-hotel-row');
            var room = $(this).data('room') || {};
            $row.find('.h-room').val(room.type || $(this).find('span').first().text() || '');
            var price = parseFloat(room.price);
            if (!isNaN(price) && price > 0) {
                $row.find('.h-rate').val(Math.round(price));
            }
            hideHotelMenus($row);
            recalcCosts();
            saveFormDraftToStorage();
        });

        $(document).on('focus click', '.h-meal', function () {
            showHotelMealSuggestions($(this).closest('.q-hotel-row'));
        });
        $(document).on('input', '.h-meal', function () {
            var $row = $(this).closest('.q-hotel-row');
            clearTimeout(qHotelMealTimer);
            qHotelMealTimer = setTimeout(function () {
                showHotelMealSuggestions($row);
            }, 200);
        });
        $(document).on('mousedown', '.q-hotel-meal-pick', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.q-hotel-row');
            var meal = $(this).data('meal') || {};
            $row.find('.h-meal').val(meal.name || $(this).find('span').first().text() || '');
            hideHotelMenus($row);
            saveFormDraftToStorage();
        });

        $(document).on('change', '.h-checkin, .h-checkout', function () {
            var $row = $(this).closest('.q-hotel-row');
            var inDt = $row.find('.h-checkin').val();
            var outDt = $row.find('.h-checkout').val();
            if (inDt && outDt && window.moment) {
                var nights = moment(outDt).diff(moment(inDt), 'days');
                if (nights > 0) {
                    $row.find('.h-nights').val(nights);
                }
            }
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.q-hotel-combo').length) {
                hideHotelMenus();
            }
        });

        var pricingNumberSelector = '.q-wizard-step[data-q-step="6"] input[type="number"]';

        function sanitizePricingNumberValue(raw) {
            var value = String(raw == null ? '' : raw);
            value = value.replace(/[^\d.]/g, '');
            var firstDot = value.indexOf('.');
            if (firstDot >= 0) {
                value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
            }
            return value;
        }

        $(document).on('keydown', pricingNumberSelector, function (e) {
            if (e.ctrlKey || e.metaKey || e.altKey) {
                return;
            }
            var key = e.key;
            if (
                key === 'Backspace' || key === 'Delete' || key === 'Tab' || key === 'Escape' ||
                key === 'Enter' || key === 'ArrowLeft' || key === 'ArrowRight' ||
                key === 'ArrowUp' || key === 'ArrowDown' || key === 'Home' || key === 'End'
            ) {
                return;
            }
            if (key === '.' || key === 'Decimal') {
                if (String($(this).val() || '').indexOf('.') >= 0) {
                    e.preventDefault();
                }
                return;
            }
            if (!/^\d$/.test(key)) {
                e.preventDefault();
            }
        });

        $(document).on('input', pricingNumberSelector, function () {
            var $input = $(this);
            var cleaned = sanitizePricingNumberValue($input.val());
            if (String($input.val()) !== cleaned) {
                $input.val(cleaned);
            }
        });

        $(document).on('paste', pricingNumberSelector, function (e) {
            e.preventDefault();
            var pasted = '';
            if (e.originalEvent && e.originalEvent.clipboardData) {
                pasted = e.originalEvent.clipboardData.getData('text');
            } else if (window.clipboardData) {
                pasted = window.clipboardData.getData('Text');
            }
            var cleaned = sanitizePricingNumberValue(pasted);
            var input = this;
            var start = input.selectionStart || 0;
            var end = input.selectionEnd || 0;
            var current = String($(input).val() || '');
            var next = sanitizePricingNumberValue(current.slice(0, start) + cleaned + current.slice(end));
            $(input).val(next).trigger('input');
        });

        $(document).on('input', '.q-cost-synced', function () {
            $(this).attr('data-user-edited', '1');
        });

        $(document).on('input change', '.f-fare, .h-rate, .h-rooms, .h-nights', function () {
            if ($(this).hasClass('h-rate')) {
                var raw = String($(this).val() || '');
                if (raw !== '') {
                    var n = parseInt(raw.replace(/[^\d-]/g, ''), 10);
                    if (isNaN(n) || n < 0) {
                        $(this).val('');
                    } else if (String(n) !== raw) {
                        $(this).val(n);
                    }
                }
            }
            recalcCosts();
        });

        $(document).on('input change', '.q-cost', recalcCosts);
        $(document).on('input change', '.q-sheet-profit-percent, .q-sheet-profit-amount', recalcCosts);
        $(document).on('input', '.q-sheet-profit-percent', function () {
            if ($(this).val()) {
                $(this).closest('.q-pricing-option-sheet').find('.q-sheet-profit-amount').val('');
            }
        });
        $(document).on('input', '.q-sheet-profit-amount', function () {
            if ($(this).val()) {
                $(this).closest('.q-pricing-option-sheet').find('.q-sheet-profit-percent').val('');
            }
        });
        $(document).on('input', '.q-sheet-price-per-adult', function () {
            if ($.trim($(this).val()) === '') {
                $(this).removeAttr('data-user-edited');
            } else {
                $(this).attr('data-user-edited', '1');
            }
            recalcCosts();
        });

        $(document).on('click', '.q-flight-journey-delete', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $card = $(this).closest('.q-flight-journey-card');
            if (!$card.length) {
                return;
            }
            $card.find('.f-supplier').each(function () {
                qDestroySupplierSelect2($(this));
            });
            $card.remove();
            renumberFlightRows();
            recalcCosts();
        });

        $(document).on('click', '.q-remove', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var selector = $(this).attr('data-remove') || '.q-repeat-row';
            var $row = $(this).closest(selector);
            if (!$row.length) {
                $row = $(this).closest('.q-repeat-row');
            }
            var isCustomCost = $row.hasClass('q-custom-cost') || selector === '.q-custom-cost';
            if (isCustomCost) {
                var sheetId = String($row.closest('.q-pricing-option-sheet').attr('data-cat-id') || '');
                $row.remove();
                snapshotPricingSheets();
                if (sheetId && qPricingOptionsState[sheetId]) {
                    qPricingOptionsState[sheetId].custom = (qPricingOptionsState[sheetId].custom || []).filter(function (c) {
                        return $.trim(c.label || '') !== '' || $.trim(String(c.amount || '')) !== '';
                    });
                }
                renderPricingSheets();
                return;
            }
            $row.find('.f-supplier, .h-supplier').each(function () {
                qDestroySupplierSelect2($(this));
            });
            $row.remove();
            renumberFlightRows();
            recalcCosts();
        });

        $('#q_tentative_date').on('change', function () { scheduleItineraryRebuild(); });
        $('#q_nights').on('input change', function () { scheduleItineraryRebuild(); });
        $('#q_adults').on('input change', recalcCosts);

        $('#qConvertUsd').on('click', function () {
            var usd = parseFloat($('#q_usd_amount').val());
            var rate = parseFloat($('#q_usd_rate').val());
            if (isNaN(usd) || isNaN(rate)) { alert('Enter both USD amount and rate.'); return; }
            var inr = usd * rate;
            var resultText = '₹ ' + money(inr) + ' INR';
            $('#qUsdResultText').text(resultText);
            $('#qUsdResult').removeClass('is-empty');
            $('#qUsdCopyResult').show().data('copy', String(inr.toFixed(2)));
            var $target = $lastFocusedCost && $lastFocusedCost.length ? $lastFocusedCost : $();
            if (!$target.length) {
                $target = $('#qPricingSheetsHost .q-pricing-option-sheet.is-active .q-cost[data-key="land"]').first();
            }
            if (!$target.length) {
                $target = $('#qPricingSheetsHost .q-cost[data-key="land"]').first();
            }
            if (!$target.length) {
                alert('No cost field available to fill.');
                return;
            }
            $target.val(inr.toFixed(2));
            if ($target.hasClass('q-cost-synced')) {
                $target.attr('data-user-edited', '1');
            }
            recalcCosts();
        });

        $('#qUsdCopyResult').on('click', function () {
            var val = String($(this).data('copy') || '');
            if (!val || !navigator.clipboard) return;
            navigator.clipboard.writeText(val).catch(function () {});
        });

        $(document).on('click', '#qCalcKeys [data-calc]', function () {
            var key = String($(this).attr('data-calc') || '');
            var $btn = $(this);
            $btn.addClass('is-pressed');
            window.setTimeout(function () { $btn.removeClass('is-pressed'); }, 90);
            qCalcPress(key);
            $('#qCalcPanel').addClass('is-focused').focus();
        });
        $('#qCalcCopy').on('click', function () { qCalcCopyResult(); });
        $('#qCalcUseField, #qCalcUseLand').on('click', function () { qCalcFillTarget(); });
        $('#qCalcLoad').on('click', function () { qCalcLoadFromTarget(); });
        $('#qCalcPanel').on('focusin', function () {
            $(this).addClass('is-focused');
            qCalcUpdateTargetLabel();
        }).on('focusout', function (e) {
            var $panel = $(this);
            window.setTimeout(function () {
                if (!$panel.has(document.activeElement).length && document.activeElement !== $panel[0]) {
                    $panel.removeClass('is-focused');
                }
            }, 0);
        });
        $(document).on('keydown', function (e) {
            var $panel = $('#qCalcPanel');
            if (!$panel.length || !$panel.hasClass('is-focused')) return;
            var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
            if (tag === 'textarea' || (tag === 'input' && !$(e.target).hasClass('q-calc-display'))) return;

            var key = e.key;
            var map = {
                Enter: '=',
                '=': '=',
                Escape: 'C',
                Backspace: 'BS',
                Delete: 'CE',
                '%': '%',
                '+': '+',
                '-': '-',
                '*': '*',
                '/': '/',
                '.': '.',
                ',': '.'
            };
            var calcKey = null;
            if (/^\d$/.test(key)) calcKey = key;
            else if (map[key] != null) calcKey = map[key];
            else if (key === 'c' || key === 'C') calcKey = 'C';

            if (!calcKey) return;
            e.preventDefault();
            var $btn = $('#qCalcKeys [data-calc="' + calcKey.replace(/"/g, '\\"') + '"]').first();
            if ($btn.length) {
                $btn.addClass('is-pressed');
                window.setTimeout(function () { $btn.removeClass('is-pressed'); }, 90);
            }
            qCalcPress(calcKey);
        });
        qCalcUpdateUi();
        qCalcUpdateTargetLabel();
        $(document).on('input change', '#q_pricing_notes', function () {
            saveFormDraftToStorage();
        });

        $('#qPreviewBtn').on('click', function () {
            openQuotationPreview();
        });

        $('#qLoadTermsMasterBtn').on('click', function () {
            if (!window.confirm('Replace all Terms & Policies fields with master content?')) {
                return;
            }
            loadTermsFromMaster();
        });

        $('#qPreviewPrintBtn').on('click', function () {
            var $area = $('#qPreviewPrintArea');
            if (!$area.length || !$area.html()) {
                return;
            }
            flushPreviewActiveEdit();

            var $clone = $area.clone();
            $clone.find('.q-preview-editable').each(function () {
                var $el = $(this);
                $el.removeAttr('contenteditable')
                    .removeAttr('spellcheck')
                    .removeClass('is-editing q-preview-editable q-preview-cell-edit')
                    .removeAttr('data-q-edit')
                    .removeAttr('data-q-type')
                    .removeAttr('data-q-multiline');
            });

            var styles = '';
            $('style').each(function () {
                var txt = $(this).html() || '';
                if (txt.indexOf('.q-preview-') >= 0) {
                    styles = txt;
                }
            });
            // Remove page-level @media print rules (they hide everything outside #qPreviewPrintArea
            // and can blank the popup if matched incorrectly). Rebuild print styles below.
            (function stripPrintMedia() {
                var lower = styles.toLowerCase();
                var idx = lower.indexOf('@media print');
                while (idx >= 0) {
                    var brace = styles.indexOf('{', idx);
                    if (brace < 0) break;
                    var depth = 0;
                    var end = -1;
                    var i;
                    for (i = brace; i < styles.length; i++) {
                        if (styles.charAt(i) === '{') depth++;
                        else if (styles.charAt(i) === '}') {
                            depth--;
                            if (depth === 0) {
                                end = i + 1;
                                break;
                            }
                        }
                    }
                    if (end < 0) break;
                    styles = styles.slice(0, idx) + styles.slice(end);
                    lower = styles.toLowerCase();
                    idx = lower.indexOf('@media print');
                }
            })();

            var printWin = window.open('', '_blank');
            if (!printWin) {
                alert('Please allow pop-ups to print.');
                return;
            }
            printWin.document.open();
            printWin.document.write(
                '<!DOCTYPE html><html><head><title>Quotation Preview</title><meta charset="utf-8">' +
                '<link rel="stylesheet" href="' + esc(absUrl('plugins/fontawesome-free/css/all.min.css')) + '">' +
                '<style>' +
                styles +
                'html,body{margin:0;padding:0;background:#fff;}' +
                '.q-preview-doc{padding:18px 22px;max-width:900px;margin:0 auto;}' +
                '@media print{' +
                'body{margin:0;padding:0;background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
                '.q-preview-doc{padding:12px 16px;}' +
                '.q-preview-editable,.q-preview-cell-edit{background:transparent!important;outline:none!important;box-shadow:none!important;}' +
                '.q-preview-services-bar,.q-preview-social a{-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
                '}' +
                '</style></head><body>' +
                '<div id="qPreviewPrintArea" class="q-preview-doc">' + $clone.html() + '</div>' +
                '</body></html>'
            );
            printWin.document.close();

            var triggerPrint = function () {
                try {
                    printWin.focus();
                    printWin.print();
                } catch (err) { /* ignore */ }
            };
            window.setTimeout(triggerPrint, 700);
        });

        function postQuotationSave(p, saveMode, $btn, btnDefaultHtml, onSuccess) {
            p.save_mode = saveMode;
            // Keep hotels_json / flights_json / itinerary_json as raw JSON strings
            // so PHP receives the multi-option hotel shape intact.
            if (p.hotels_json && typeof p.hotels_json !== 'string') {
                p.hotels_json = JSON.stringify(p.hotels_json);
            }
            if (p.flights_json && typeof p.flights_json !== 'string') {
                p.flights_json = JSON.stringify(p.flights_json);
            }
            if (p.itinerary_json && typeof p.itinerary_json !== 'string') {
                p.itinerary_json = JSON.stringify(p.itinerary_json);
            }
            if (p.cost_sheet_json && typeof p.cost_sheet_json !== 'string') {
                p.cost_sheet_json = JSON.stringify(p.cost_sheet_json);
            }
            var saveUrl = absUrl($('#quotationForm').attr('data-save-url') || 'crm/ajax/save_quotation.php');
            $.ajax({
                url: saveUrl,
                type: 'POST',
                data: p,
                traditional: true,
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .done(function (res) {
                    if (res && res.success) {
                        if (res.id) {
                            $('#q_id').val(res.id);
                        }
                        $('#q_edit_from_version').remove();
                        if (typeof onSuccess === 'function') {
                            onSuccess(res);
                        }
                    } else {
                        $('#qAlert').html('<div class="alert alert-danger">' + esc((res && res.message) || 'Could not save.') + '</div>');
                        window.scrollTo(0, 0);
                    }
                })
                .fail(function (xhr) {
                    var msg = 'Could not save. Please try again.';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    } else if (xhr && xhr.responseText) {
                        var text = $.trim(xhr.responseText);
                        if (text.indexOf('{') === 0) {
                            try {
                                var parsed = JSON.parse(text);
                                if (parsed && parsed.message) {
                                    msg = parsed.message;
                                }
                            } catch (e) { /* ignore */ }
                        } else if (text !== '') {
                            msg = text.substring(0, 240);
                        }
                    }
                    $('#qAlert').html('<div class="alert alert-danger">' + esc(msg) + '</div>');
                    window.scrollTo(0, 0);
                })
                .always(function () {
                    if ($btn && $btn.length) {
                        $btn.prop('disabled', false).html(btnDefaultHtml);
                    }
                });
        }

        function saveQuotationDraft() {
            var p;
            try {
                p = collectPayload();
            } catch (err) {
                alert('Could not prepare draft data. ' + (err && err.message ? err.message : ''));
                return;
            }
            var $btn = $('#qSaveDraftBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');
            postQuotationSave(p, 'draft', $btn, '<i class="fas fa-bookmark mr-1"></i> Save Draft', function (res) {
                var successHtml = esc(res.message || 'Draft saved.');
                if (res.quotation_uid) {
                    successHtml += ' (' + esc(res.quotation_uid) + ')';
                }
                $('#qAlert').html('<div class="alert alert-success">' + successHtml + '</div>');
                window.scrollTo(0, 0);
                if (res.id) {
                    $('#q_id').val(res.id);
                }
                markTourCostAutoSaved();
                saveFormDraftToStorage();
                if (res.id && !window.location.search.match(/[?&]id=/)) {
                    var nextUrl = absUrl('crm/quotation_generator.php?id=' + encodeURIComponent(res.id));
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState({}, '', nextUrl);
                    }
                    saveFormDraftToStorage();
                }
            });
        }

        function saveQuotation($btnOverride) {
            var p;
            try {
                p = collectPayload();
            } catch (err) {
                alert('Could not prepare quotation data. ' + (err && err.message ? err.message : ''));
                return;
            }
            if (!p.guest_name) {
                alert('Please enter the guest name.');
                expandWizardSection(1);
                setWizardStep(1);
                return;
            }
            var $btn = ($btnOverride && $btnOverride.length) ? $btnOverride : $('#qSaveBtn');
            var btnDefaultHtml = $btn.is('#qPreviewSaveBtn')
                ? '<i class="fas fa-save mr-1"></i>Save Changes'
                : '<i class="fas fa-save mr-1"></i>Save Quotation';
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');
            postQuotationSave(p, 'publish', $btn, btnDefaultHtml, function (res) {
                setPreviewDirty(false);
                var successHtml = esc(res.message || 'Quotation saved.');
                if (res.quotation_uid) {
                    successHtml += ' (' + esc(res.quotation_uid) + ')';
                }
                if (res.version) {
                    successHtml += ' — now at <strong>v' + esc(String(res.version)) + '</strong>.';
                }
                $('#qAlert').html('<div class="alert alert-success">' + successHtml + '</div>');
                window.scrollTo(0, 0);
                if (res.id) {
                    $('#q_id').val(res.id);
                }
                saveFormDraftToStorage();
                if ($('#qPreviewModal').hasClass('show')) {
                    $('#qPreviewModal').modal('hide');
                }
                setTimeout(function () {
                    if (res.id) {
                        window.location.href = absUrl('crm/quotation_generator.php?id=' + encodeURIComponent(res.id));
                    } else {
                        window.location.href = absUrl('crm/quotation-generator-list.php');
                    }
                }, 900);
            });
        }

        window.qSaveQuotation = saveQuotation;

        $('#quotationForm').on('submit', function (e) {
            e.preventDefault();
            saveQuotation();
        });
        $('#qSaveBtn').on('click', function (e) {
            e.preventDefault();
            saveQuotation();
        });
        $('#qSaveDraftBtn').on('click', function (e) {
            e.preventDefault();
            saveQuotationDraft();
        });
        $('#qPreviewSaveBtn').on('click', function (e) {
            e.preventDefault();
            flushPreviewActiveEdit();
            window.setTimeout(function () {
                saveQuotation($('#qPreviewSaveBtn'));
            }, 80);
        });
        $('#qPreviewModal').on('hide.bs.modal', function (e) {
            if (!previewDirty) return;
            if (!window.confirm('You have unsaved preview edits. Close without saving?')) {
                e.preventDefault();
            }
        });
        $('#qPreviewModal').on('hidden.bs.modal', function () {
            setPreviewDirty(false);
        });

        $('#qVersionSelect').on('change', function () {
            var href = $.trim(String($(this).val() || ''));
            if (!href) {
                return;
            }
            window.location.href = absUrl(href);
        });

        $(document).on('click', '.js-q-side-collapse', function () {
            var $card = $(this).closest('.q-side-card');
            var isCollapsed = $card.hasClass('is-collapsed');
            $card.toggleClass('is-collapsed', !isCollapsed);
            $(this).attr('aria-expanded', isCollapsed ? 'true' : 'false');
        });

        (function initLeadSidebarCollapse() {
            var $layout = $('#qPageLayout');
            var $toggle = $('.js-q-lead-sidebar-toggle');
            if (!$layout.length || !$toggle.length) {
                return;
            }
            var storageKey = 'qLeadSidebarCollapsed';
            function applyCollapsed(collapsed) {
                $layout.toggleClass('is-lead-sidebar-collapsed', !!collapsed);
                $toggle.attr('aria-expanded', collapsed ? 'false' : 'true');
                $toggle.attr('title', collapsed ? 'Expand lead panel' : 'Collapse lead panel');
                try {
                    window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
                } catch (e) { /* ignore */ }
            }
            var saved = '';
            try {
                saved = window.localStorage.getItem(storageKey) || '';
            } catch (e) { /* ignore */ }
            if (saved === '1') {
                applyCollapsed(true);
            }
            $toggle.on('click', function () {
                applyCollapsed(!$layout.hasClass('is-lead-sidebar-collapsed'));
            });
        })();

        if (QUOTATION_PREFILL && (QUOTATION_PREFILL.id || QUOTATION_PREFILL.guest_name || QUOTATION_PREFILL.status === 'draft')) {
            applyPrefill(QUOTATION_PREFILL);
            if (QUOTATION_PREFILL.status === 'draft') {
                var draftStep = parseInt(QUOTATION_PREFILL.wizard_step, 10) || 1;
                qWizardMax = Math.max(draftStep, qWizardMax || 1);
            } else if (QUOTATION_PREFILL.id) {
                // Existing saved quotation: show all sections for review/edit.
                qWizardMax = Q_WIZARD_TOTAL;
            }
            restoreWizardStepOnLoad();
            if (QUOTATION_PREFILL.editing_from_version) {
                var archivedVer = QUOTATION_PREFILL.editing_from_version;
                $('#qAlert').html(
                    '<div class="alert alert-info mb-3">You are editing <strong>version v' + esc(String(archivedVer)) + '</strong>. '
                    + 'Saving will publish a new latest version while keeping earlier versions unchanged.</div>'
                );
            }
        } else {
            var localDraft = loadFormDraftFromStorage();
            if (localDraft) {
                if (Array.isArray(localDraft.flights) && localDraft.flights.length) {
                    renderFlightList(localDraft.flights);
                }
                if (localDraft.cost_sheet && Array.isArray(localDraft.cost_sheet.options)) {
                    qPricingOptionsState = {};
                    localDraft.cost_sheet.options.forEach(function (opt) {
                        var id = String(opt.category_id || opt.id || '');
                        if (!id) return;
                        qPricingOptionsState[id] = {
                            fixed: opt.fixed || {},
                            custom: Array.isArray(opt.custom) ? opt.custom : [],
                            user_edited: opt.user_edited || {},
                            profit_percent: opt.profit_percent != null ? opt.profit_percent : '',
                            profit_amount: opt.profit_amount != null ? opt.profit_amount : '',
                            price_per_adult: opt.price_per_adult != null ? opt.price_per_adult : '',
                            price_per_adult_edited: parseInt(opt.price_per_adult_edited, 10) === 1 ? 1 : 0
                        };
                    });
                    if (localDraft.active_option_id || localDraft.cost_sheet.active_option_id) {
                        qActiveHotelCategoryId = String(localDraft.active_option_id || localDraft.cost_sheet.active_option_id);
                    }
                }
                if (localDraft.cost_sheet && localDraft.cost_sheet.pricing_notes != null) {
                    applyPricingNotes(localDraft.cost_sheet.pricing_notes);
                } else if (localDraft.pricing_notes != null) {
                    applyPricingNotes(localDraft.pricing_notes);
                }
                if (localDraft.cost_sheet && localDraft.cost_sheet.tour_cost) {
                    applyTourCostState(localDraft.cost_sheet.tour_cost);
                }
                renderHotelCategories(resolveHotelsForLoad((localDraft && localDraft.hotels) || []));
                if (Array.isArray(localDraft.itinerary) && localDraft.itinerary.length) {
                    rebuildItinerary(localDraft.itinerary);
                } else {
                    rebuildItinerary();
                }
                if (localDraft.cost_sheet && localDraft.cost_sheet.itinerary_meta) {
                    applyItineraryMeta(localDraft.cost_sheet.itinerary_meta);
                } else {
                    ensureItinerarySupplierRows();
                }
            } else {
                rebuildItinerary();
                ensureItinerarySupplierRows();
            }
            ensureHotelCategoriesReady();
            renderPricingSheets();
            if (!tourCostRowsPresent()) {
                renderTourCostRows();
            }
            recalcCosts();
            restoreWizardStepOnLoad();
            saveFormDraftToStorage();
        }
        ensureHotelCategoriesReady();
        ensureItinerarySupplierRows();
        if (!$('#qPricingSheetsHost .q-pricing-option-sheet').length) {
            renderPricingSheets();
        }
        if (!tourCostRowsPresent()) {
            renderTourCostRows();
        }
        qInitSupplierSelect2In();
    });

})(jQuery);
