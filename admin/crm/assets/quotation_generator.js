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
        var iso = normalizeLegacyDateInput(baseStr);
        if (!iso) return '';
        var d = new Date(iso + 'T00:00:00');
        if (isNaN(d.getTime())) return '';
        d.setDate(d.getDate() + offset);
        var dd = String(d.getDate()).padStart(2, '0');
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var yyyy = d.getFullYear();
        return dd + '/' + mm + '/' + yyyy + ' ' + DAY_NAMES[d.getDay()];
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

    /** Display format used across Quotation Generator inputs. */
    function formatDisplayDate(val) {
        var iso = normalizeLegacyDateInput(val);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
            return String(val || '').trim();
        }
        var parts = iso.split('-');
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function setDateInputValue($input, val) {
        if (!$input || !$input.length) {
            return;
        }
        var display = val ? formatDisplayDate(val) : '';
        $input.val(display);
        if ($input.hasClass('hasDatepicker') && $.fn.datepicker) {
            try {
                $input.datepicker('setDate', display || null);
            } catch (e) { /* ignore */ }
        }
    }

    function initQuotationDatePickers($root) {
        if (!$.fn.datepicker) {
            return;
        }
        var $scope = $root && $root.length ? $root : $('.crm-quotation-gen');
        if (!$scope.length) {
            $scope = $(document);
        }
        $scope.find('input.js-q-date-input').each(function () {
            var $input = $(this);
            var current = formatDisplayDate($input.val());
            if (current !== String($input.val() || '').trim()) {
                $input.val(current);
            }
            if ($input.hasClass('hasDatepicker')) {
                try {
                    $input.datepicker('destroy');
                } catch (e) { /* ignore */ }
            }
            $input.attr({
                placeholder: 'dd/mm/yyyy',
                autocomplete: 'off',
                inputmode: 'numeric'
            });
            $input.datepicker({
                dateFormat: 'dd/mm/yy',
                changeMonth: true,
                changeYear: true,
                yearRange: 'c-5:c+15',
                showButtonPanel: true,
                closeText: 'Done',
                currentText: 'Today',
                prevText: '',
                nextText: '',
                beforeShow: function (input, inst) {
                    inst.dpDiv.addClass('crm-q-datepicker');
                    inst.dpDiv.css({ zIndex: 2200 });
                },
                onSelect: function () {
                    $(this).trigger('change');
                },
                onClose: function () {
                    $(this).blur();
                }
            });
            if (current) {
                try {
                    $input.datepicker('setDate', current);
                } catch (e2) { /* ignore */ }
            }
        });
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
                initQuotationSummernote($('#' + editorId), 220, itineraryDaySummernoteToolbar());
                updateDayCharCount($body.closest('.q-day-card'));
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
        var onDone = typeof arguments[2] === 'function' ? arguments[2] : null;
        if (shouldOpen) {
            $head.removeClass('collapsed').attr('aria-expanded', 'true');
            $body.stop(true, true).slideDown(150, function () {
                onAccordionBodyShown($body);
                if (typeof syncUnlockedWizardSections === 'function') {
                    syncUnlockedWizardSections();
                }
                if (onDone) {
                    onDone();
                }
            });
        } else {
            $head.addClass('collapsed').attr('aria-expanded', 'false');
            $body.stop(true, true).slideUp(150, function () {
                if (typeof syncUnlockedWizardSections === 'function') {
                    syncUnlockedWizardSections();
                }
                if (onDone) {
                    onDone();
                }
            });
        }
    }

    function expandWizardSection(step, onDone) {
        step = parseInt(step, 10);
        if (isNaN(step) || step < 1) {
            if (typeof onDone === 'function') {
                onDone();
            }
            return;
        }
        var $body = $('#qSectionBody' + step);
        var $head = $body.prev('.q-section-accordion-head');
        if (!$head.length) {
            $head = $('#qWizardSection' + step).find('.q-section-accordion-head').first();
            $body = $('#qSectionBody' + step);
        }
        if ($head.length && $body.length && $head.hasClass('collapsed')) {
            toggleAccordionHead($head, true, onDone);
        } else if (typeof onDone === 'function') {
            onDone();
        }
    }

    function expandDayCard($card) {
        if (!$card || !$card.length) {
            return;
        }
        var idx = parseInt($card.attr('data-day-index'), 10);
        if (isNaN(idx)) {
            idx = $('#qItineraryDays .q-day-card').index($card);
        }
        showItineraryDay(idx);
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

    $(document).on('keydown', '.q-terms-item-head', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleAccordionHead($(this));
        }
    });

    $(document).on('click', '.q-accordion-head:not(.q-section-accordion-head):not(.q-day-head)', function () {
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
            hand_baggage: data.hand_baggage || data.handBaggage || data.cabin_baggage || data.cabinBaggage || '',
            checkin_baggage: data.checkin_baggage || data.checkinBaggage || data.check_in_baggage || data.checkInBaggage || '',
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
        var dateStr = normalizeLegacyDateInput(dateVal);
        var timeStr = String(timeVal || '').trim();
        if (!dateStr) {
            return null;
        }
        if (!timeStr) {
            timeStr = '00:00';
        }
        var m = moment(dateStr + ' ' + timeStr, ['YYYY-MM-DD HH:mm', 'YYYY-MM-DD HH:mm:ss', 'DD/MM/YYYY HH:mm'], true);
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
        initQuotationDatePickers($('#qFlightRows'));
        refreshFlightLayovers();
        scheduleSyncReturnAirfareInclusion();
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
        if (!$selection) {
            return;
        }
        var $search = $selection.find('.select2-search__field, .q-supplier-inline-search-field');
        if ($search.length) {
            if ($dropdown && $dropdown.length) {
                var $host = $dropdown.find('.select2-search--dropdown');
                if ($host.length) {
                    $host.append($search);
                } else {
                    $search.remove();
                }
            } else {
                $search.remove();
            }
        }
        if ($dropdown && $dropdown.length) {
            $dropdown.find('.q-supplier-inline-search-field').removeClass('q-supplier-inline-search-field');
            $dropdown.removeClass('q-supplier-inline-search');
        }
        $search.removeClass('q-supplier-inline-search-field');
        $selection.removeClass('q-supplier-searching');
        $selection.find('.select2-selection__rendered').removeClass('q-supplier-search-host');
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
        $sel.off('select2:opening.qSupplierPrev select2:open.qCreateFooter select2:open.qInlineSearch select2:closing.qInlineSearch select2:close.qInlineSearch select2:select.qCloseOnPick');
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
        $sel.off('select2:closing.qInlineSearch select2:close.qInlineSearch')
            .on('select2:closing.qInlineSearch select2:close.qInlineSearch', function () {
                qRestoreSupplierSearchField($(this));
            });
        $sel.off('select2:select.qCloseOnPick').on('select2:select.qCloseOnPick', function (e) {
            var $this = $(this);
            var pickedId = '';
            if (e && e.params && e.params.data) {
                pickedId = String(e.params.data.id || '');
            } else {
                pickedId = String($this.val() || '');
            }
            qRestoreSupplierSearchField($this);
            // Always close after a real pick (Create is handled by change handler).
            window.setTimeout(function () {
                qRestoreSupplierSearchField($this);
                try {
                    if ($this.data('select2')) {
                        $this.select2('close');
                    }
                } catch (err) { /* ignore */ }
                // Ensure selection label is visible (inline search can leave placeholder overlay).
                var $selection = $this.data('select2') && $this.data('select2').$selection;
                if ($selection) {
                    $selection.removeClass('q-supplier-searching');
                    $selection.find('.select2-selection__rendered').removeClass('q-supplier-search-host');
                    $selection.find('.select2-search__field, .q-supplier-inline-search-field').remove();
                }
                if (pickedId && pickedId !== '__create__') {
                    $this.trigger('change');
                }
            }, 0);
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
            qInitSupplierSelect2($(this), { placeholder: 'Select' });
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

    function setFlightRowFareSupplierEnabled($row, enabled) {
        if (!$row || !$row.length) {
            return;
        }
        var $fare = $row.find('.f-fare');
        var $sup = $row.find('.f-supplier');
        $fare.prop('disabled', !enabled);
        $sup.prop('disabled', !enabled);
        $row.find('.q-ft-col-fare, .q-ft-col-supplier').toggleClass('is-connecting-locked', !enabled);
        $row.toggleClass('is-connecting-follow-on', !enabled);
        if (!enabled) {
            var fareNum = parseFloat($fare.val());
            if ($fare.val() === '' || isNaN(fareNum) || fareNum === 0) {
                $fare.val('0.00');
            }
        }
        if ($sup.data('select2')) {
            try {
                $sup.trigger('change.select2');
            } catch (e) { /* ignore */ }
        }
    }

    function isConnectingSecondSegment($prev, $row) {
        if (!$prev || !$prev.length || !$row || !$row.length) {
            return false;
        }
        if (isFlightJourneyBoundary($prev, $row)) {
            return false;
        }
        var prevTo = String($prev.find('.f-to').val() || '').trim();
        var curFrom = String($row.find('.f-from').val() || '').trim();
        if (!prevTo || !curFrom) {
            return false;
        }
        return flightPlacesConnect(prevTo, curFrom);
    }

    function syncConnectingSegmentFareSupplier() {
        // Lock Rate/Supplier only on the true connecting 2nd segment (follow-on after first leg).
        // First segment and any other / later / standalone flights stay editable.
        $('#qFlightRows .q-flight-journey-card').each(function () {
            var $rows = $(this).find('.q-flight-row');
            $rows.each(function (idx) {
                var $row = $(this);
                var lock = false;
                if (idx === 1) {
                    lock = isConnectingSecondSegment($rows.eq(0), $row);
                }
                setFlightRowFareSupplierEnabled($row, !lock);
            });
        });
        $('#qFlightRows > .q-flight-row').each(function () {
            setFlightRowFareSupplierEnabled($(this), true);
        });
    }

    function renumberFlightRows() {
        refreshFlightLayovers();
    }

    function parseFlightDateTime(dateVal, timeVal) {
        var dateStr = normalizeLegacyDateInput(dateVal);
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

    function extractFlightCityName(place) {
        var s = String(place || '').trim();
        if (!s) {
            return '';
        }
        s = s.replace(/\s*\(([A-Za-z0-9]{3})\)\s*$/, '').trim();
        if (!s) {
            return '';
        }
        if (/^[A-Za-z]{3}$/.test(s)) {
            return s.toUpperCase();
        }
        return s;
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

    /** Ordered unique places along all flight segments (from → to → …). */
    function collectFlightRoutePlaces() {
        var places = [];
        $('#qFlightRows .q-flight-row').each(function () {
            var from = String($(this).find('.f-from').val() || '').trim();
            var to = String($(this).find('.f-to').val() || '').trim();
            if (from) {
                if (!places.length || !flightPlacesConnect(places[places.length - 1], from)) {
                    places.push(from);
                }
            }
            if (to) {
                if (!places.length || !flightPlacesConnect(places[places.length - 1], to)) {
                    places.push(to);
                }
            }
        });
        return places;
    }

    /** Auto inclusion line: return Ex-city, or full routing when endpoints differ. */
    function getAutoAirfareInclusionText() {
        var places = collectFlightRoutePlaces();
        if (places.length < 2) {
            return '';
        }
        var first = places[0];
        var last = places[places.length - 1];
        if (flightPlacesConnect(first, last)) {
            var city = extractFlightCityName(first) || extractFlightCityName(last);
            return city ? ('Return airfare Ex-' + city) : '';
        }
        var names = [];
        places.forEach(function (place) {
            var name = extractFlightCityName(place);
            if (name) {
                names.push(name);
            }
        });
        if (names.length < 2) {
            return '';
        }
        return 'Airfare : ' + names.join(' -> ');
    }

    function stripAutoReturnAirfareHtml(html) {
        html = String(html || '');
        html = html.replace(/<(p|div|li)([^>]*)\sdata-q-auto-return-airfare(?:=["'][^"']*["'])?([^>]*)>[\s\S]*?<\/\1>/gi, '');
        html = html.replace(/<(p|div|li)([^>]*)\sdata-q-auto-accommodation(?:=["'][^"']*["'])?([^>]*)>[\s\S]*?<\/\1>/gi, '');
        html = html.replace(/<(p|div|li|h[1-6]|ul)([^>]*)\sdata-q-auto-sightseeing(?:=["'][^"']*["'])?([^>]*)>[\s\S]*?<\/\1>/gi, '');
        html = html.replace(/<(p|div|li|h[1-6]|ul)([^>]*)\sdata-q-auto-meals(?:=["'][^"']*["'])?([^>]*)>[\s\S]*?<\/\1>/gi, '');
        html = html.replace(/^\s*<(p|li)(?:\s[^>]*)?>\s*(?:<(?:strong|b|em|span)(?:\s[^>]*)?>\s*)*Return\s+airfare\s+Ex-[^<]*(?:<\/(?:strong|b|em|span)>\s*)*<\/\1>/i, '');
        html = html.replace(/^\s*<(p|li)(?:\s[^>]*)?>\s*(?:<(?:strong|b|em|span)(?:\s[^>]*)?>\s*)*Airfare\s*(?::|Ex-)[^<]*(?:<\/(?:strong|b|em|span)>\s*)*<\/\1>/i, '');
        html = html.replace(/^\s*<(p|li)(?:\s[^>]*)?>\s*\d+\s+nights?\s+accommodation\s+in[\s\S]*?<\/\1>/i, '');
        while (/\d+\s+nights?\s+accommodation\s+in/i.test(html)) {
            var before = html;
            html = html.replace(/^\s*<(p|li)(?:\s[^>]*)?>[\s\S]*?\d+\s+nights?\s+accommodation\s+in[\s\S]*?<\/\1>/i, '');
            if (html === before) {
                break;
            }
        }
        html = html.replace(/^(?:\s*<p>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>)+/i, '');
        return html;
    }

    function buildAutoReturnAirfareHtml(text) {
        text = String(text || '').trim();
        if (!text) {
            return '';
        }
        return '<p data-q-auto-return-airfare="1">' + esc(text) + '</p>';
    }

    function padTourNightsLabel(nights) {
        nights = parseInt(nights, 10) || 0;
        if (nights < 0) {
            nights = 0;
        }
        return nights < 10 ? ('0' + nights) : String(nights);
    }

    /** Active hotel option rows → inclusion lines. */
    function getAutoAccommodationInclusionHtml() {
        var hotels = [];
        try {
            hotels = typeof collectHotels === 'function' ? (collectHotels() || []) : [];
        } catch (e) {
            hotels = [];
        }
        var html = '';
        hotels.forEach(function (h) {
            var nights = parseInt(h && h.nights, 10);
            if (isNaN(nights) || nights <= 0) {
                return;
            }
            var city = String((h && h.city) || '').trim();
            var hotelName = String((h && h.name) || '').trim();
            if (!city || !hotelName) {
                return;
            }
            html += '<p data-q-auto-accommodation="1">' +
                esc(padTourNightsLabel(nights)) + ' nights accommodation in <strong>' + esc(city) + '</strong> at <strong>' + esc(hotelName) + '</strong>.</p>';
        });
        return html;
    }

    function isGenericItineraryDayTitle(title) {
        title = String(title || '').trim();
        if (!title) {
            return true;
        }
        if (/^day\s*\d+\b/i.test(title)) {
            return true;
        }
        if (/^(arrival|departure|check[\s-]?in|check[\s-]?out|free day|leisure day|travel day|transfer)$/i.test(title)) {
            return true;
        }
        return false;
    }

    function extractActivitiesFromDayDescription(html) {
        var text = String(html || '');
        var items = [];
        var m = text.match(/Activities[:\s]*<\/?(?:strong|b|span)[^>]*>\s*([^<]+)/i)
            || text.match(/Activities[:\s]+([^<\n]+)/i);
        if (m && m[1]) {
            String(m[1]).split(/\s*[;•|\n]+\s*/).forEach(function (part) {
                var t = String(part || '').replace(/<\/?[^>]+>/g, '').trim();
                if (t) {
                    items.push(t);
                }
            });
        }
        return items;
    }

    function formatSightseeingInclusionLine(text) {
        text = String(text || '').trim().replace(/\.+$/, '');
        if (!text) {
            return '';
        }
        var withMeal = text.match(/^(.*?)(\s+(?:with|&)\s+(?:Lunch|Dinner|Breakfast|Meal)(?:\s.*)?)$/i);
        if (withMeal) {
            return '<strong>' + esc(withMeal[1].trim()) + '</strong>' + esc(withMeal[2]) + '.';
        }
        var covering = text.match(/^(.*?covering\s+)(.+)$/i);
        if (covering) {
            return esc(covering[1]) + '<strong>' + esc(covering[2].replace(/\.+$/, '')) + '</strong>.';
        }
        return '<strong>' + esc(text) + '</strong>.';
    }

    function readItineraryDaysForInclusions() {
        var data = [];
        $('#qItineraryDays .q-day-card').each(function () {
            var $c = $(this);
            data.push({
                title: String($c.find('.q-day-title').val() || '').trim(),
                description: readSummernoteHtml($c.find('.q-day-textarea'))
            });
        });
        return data;
    }

    /** Itinerary day titles / Activities fields → inclusion lines. */
    function getAutoSightseeingInclusionHtml() {
        var days = readItineraryDaysForInclusions();
        var items = [];
        var seen = {};
        days.forEach(function (day) {
            var fromDesc = extractActivitiesFromDayDescription(day.description);
            var candidates = fromDesc.length ? fromDesc : (isGenericItineraryDayTitle(day.title) ? [] : [day.title]);
            candidates.forEach(function (raw) {
                var t = String(raw || '').replace(/\s+/g, ' ').trim();
                if (!t || isGenericItineraryDayTitle(t)) {
                    return;
                }
                var key = t.toLowerCase();
                if (seen[key]) {
                    return;
                }
                seen[key] = true;
                items.push(t);
            });
        });
        if (!items.length) {
            return '';
        }
        var html = '<p data-q-auto-sightseeing="1"><strong>Sightseeing &amp; Activities</strong></p>';
        items.forEach(function (item) {
            html += '<p data-q-auto-sightseeing="1">' + formatSightseeingInclusionLine(item) + '</p>';
        });
        return html;
    }

    function cleanTourNameForMealLine(name) {
        return String(name || '')
            .replace(/\s*[–-]\s*Regular Seat\s*$/i, '')
            .replace(/\s+by\s+Speed Boat\b.*$/i, '')
            .replace(/\s+by\s+Big Boat\b.*$/i, '')
            .replace(/\s+by\s+Boat\b.*$/i, '')
            .replace(/\s+with\s+Sea Canoe\b.*$/i, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function mealLinesFromActivityTitle(title) {
        title = String(title || '').replace(/\s+/g, ' ').trim();
        if (!title) {
            return [];
        }
        var lines = [];
        var lunch = title.match(/^(.+?)\s+(?:with|&)\s+Lunch\b/i);
        if (lunch) {
            var lunchName = cleanTourNameForMealLine(lunch[1]);
            if (lunchName) {
                lines.push('Lunch during ' + lunchName + '.');
            }
        }
        var dinner = title.match(/^(.+?)\s+(?:with|&)\s+Dinner\b/i);
        if (dinner) {
            var dinnerName = cleanTourNameForMealLine(dinner[1]);
            if (dinnerName) {
                if (/show|magic|carnival|theatre|theater|cruise|cabaret/i.test(dinnerName)) {
                    lines.push('Dinner with ' + dinnerName + '.');
                } else {
                    lines.push('Dinner during ' + dinnerName + '.');
                }
            }
        }
        var breakfast = title.match(/^(.+?)\s+(?:with|&)\s+Breakfast\b/i);
        if (breakfast) {
            var bfName = cleanTourNameForMealLine(breakfast[1]);
            if (bfName) {
                lines.push('Breakfast during ' + bfName + '.');
            }
        }
        return lines;
    }

    function extractMealsFieldFromDescription(html) {
        var text = String(html || '');
        var m = text.match(/Meals?[:\s]*<\/?(?:strong|b|span)[^>]*>\s*([^<]+)/i)
            || text.match(/Meals?[:\s]+([^<\n]+)/i);
        if (!m || !m[1]) {
            return [];
        }
        var raw = String(m[1]).replace(/<\/?[^>]+>/g, '').replace(/\s+/g, ' ').trim();
        if (!raw || /^(none|-|n\/a|nil)$/i.test(raw)) {
            return [];
        }
        // Skip plain hotel breakfast labels already covered by hotel meal plan.
        if (/^(breakfast|bf|cp)(\s*[+&].*)?$/i.test(raw)) {
            return [];
        }
        return [raw.replace(/\.+$/, '') + '.'];
    }

    function getHotelMealInclusionLines() {
        var hotels = [];
        try {
            hotels = typeof collectHotels === 'function' ? (collectHotels() || []) : [];
        } catch (e) {
            hotels = [];
        }
        var hasBreakfast = false;
        var hasLunch = false;
        var hasDinner = false;
        hotels.forEach(function (h) {
            var nights = parseInt(h && h.nights, 10);
            if (isNaN(nights) || nights <= 0) {
                return;
            }
            var code = '';
            try {
                code = String(typeof qpHotelMealCode === 'function' ? qpHotelMealCode(h.meal_plan) : '').toUpperCase();
            } catch (err) {
                code = String(h.meal_plan || '').trim().toUpperCase();
            }
            if (!code || code === 'EP') {
                return;
            }
            if (code === 'CP' || code === 'MAP' || code === 'AP' || code === 'AI') {
                hasBreakfast = true;
            }
            if (code === 'AP' || code === 'AI') {
                hasLunch = true;
            }
            if (code === 'MAP' || code === 'AP' || code === 'AI') {
                hasDinner = true;
            }
        });
        var lines = [];
        if (hasBreakfast) {
            lines.push('Daily breakfast at the hotel.');
        }
        if (hasLunch) {
            lines.push('Daily lunch at the hotel.');
        }
        if (hasDinner) {
            lines.push('Daily dinner at the hotel.');
        }
        return lines;
    }

    function getItineraryMealInclusionLines() {
        var days = readItineraryDaysForInclusions();
        var lines = [];
        var seen = {};
        var add = function (line) {
            line = String(line || '').replace(/\s+/g, ' ').trim();
            if (!line) {
                return;
            }
            if (!/\.$/.test(line)) {
                line += '.';
            }
            var key = line.toLowerCase();
            if (seen[key]) {
                return;
            }
            seen[key] = true;
            lines.push(line);
        };
        days.forEach(function (day) {
            var fromDesc = extractActivitiesFromDayDescription(day.description);
            var candidates = fromDesc.length ? fromDesc : (day.title ? [day.title] : []);
            candidates.forEach(function (title) {
                mealLinesFromActivityTitle(title).forEach(add);
            });
            extractMealsFieldFromDescription(day.description).forEach(add);
        });
        return lines;
    }

    /** Hotel meal plans + itinerary tour meals → inclusion block. */
    function getAutoMealsInclusionHtml() {
        var lines = getHotelMealInclusionLines().concat(getItineraryMealInclusionLines());
        if (!lines.length) {
            return '';
        }
        var html = '<p data-q-auto-meals="1"><strong>Meals</strong></p>';
        lines.forEach(function (line) {
            html += '<p data-q-auto-meals="1">' + esc(line) + '</p>';
        });
        return html;
    }

    function buildAutoInclusionPrefixHtml() {
        var parts = '';
        var air = getAutoAirfareInclusionText();
        if (air) {
            parts += buildAutoReturnAirfareHtml(air);
        }
        parts += getAutoAccommodationInclusionHtml();
        parts += getAutoSightseeingInclusionHtml();
        parts += getAutoMealsInclusionHtml();
        return parts;
    }

    var qReturnAirfareSyncTimer = null;
    var qAutoInclusionSyncing = false;

    function isInclusionEditorFocused() {
        var $ta = $('#qed_inclusion');
        if (!$ta.length) {
            return false;
        }
        var active = document.activeElement;
        if (!active) {
            return false;
        }
        if ($ta[0] === active) {
            return true;
        }
        // Only treat the rich-text editable as focused (not the whole inclusions panel).
        var $editable = $ta.next('.note-editor').find('.note-editable');
        if ($editable.length && $editable[0] === active) {
            return true;
        }
        if ($editable.length && $editable[0].contains(active)) {
            return true;
        }
        return false;
    }

    function syncReturnAirfareInclusion(force) {
        var $ta = $('#qed_inclusion');
        if (!$ta.length) {
            return;
        }
        if (!force && isInclusionEditorFocused()) {
            return;
        }
        var current = readSummernoteHtml($ta);
        // Keep AI-generated inclusions intact until the user regenerates/clears them.
        if (/data-q-ai-inclusions/i.test(String(current || ''))) {
            return;
        }
        var prefix = buildAutoInclusionPrefixHtml();
        var cleaned = stripAutoReturnAirfareHtml(current);
        var next = prefix + cleaned;
        var norm = function (h) {
            return String(h || '').replace(/\s+/g, ' ').trim();
        };
        if (norm(next) === norm(current)) {
            return;
        }
        qAutoInclusionSyncing = true;
        try {
            if ($.fn.summernote && $ta.data('summernote')) {
                $ta.summernote('code', next || '<p><br></p>');
            } else {
                $ta.val(next);
            }
        } finally {
            window.setTimeout(function () {
                qAutoInclusionSyncing = false;
            }, 0);
        }
    }

    function scheduleSyncReturnAirfareInclusion(force) {
        clearTimeout(qReturnAirfareSyncTimer);
        qReturnAirfareSyncTimer = setTimeout(function () {
            if (qAutoInclusionSyncing) {
                scheduleSyncReturnAirfareInclusion(force);
                return;
            }
            if (!force && isInclusionEditorFocused()) {
                return;
            }
            syncReturnAirfareInclusion(!!force);
        }, 120);
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
        syncConnectingSegmentFareSupplier();
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
            '<input type="text" class="form-control form-control-sm f-dep-date js-q-date-input" value="' + esc(formatDisplayDate(d.dep_date)) + '" placeholder="dd/mm/yyyy" autocomplete="off" title="Departure date">' +
            '<input type="time" class="form-control form-control-sm f-dep-time" value="' + esc(d.dep_time) + '" title="Departure time">' +
            '</div></div>' +
            '<div class="q-ft-col q-ft-col-arrive">' +
            '<span class="q-ft-label">Arrival</span>' +
            '<div class="q-flight-datetime">' +
            '<input type="text" class="form-control form-control-sm f-arr-date js-q-date-input" value="' + esc(formatDisplayDate(d.arr_date)) + '" placeholder="dd/mm/yyyy" autocomplete="off" title="Arrival date">' +
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
            '<input type="hidden" class="f-hand-bag" value="' + esc(d.hand_baggage) + '">' +
            '<input type="hidden" class="f-checkin-bag" value="' + esc(d.checkin_baggage) + '">' +
            '</div>' +
            '</div>';
    }

    function collectFlights() {
        var out = [];
        $('#qFlightRows .q-flight-row').each(function () {
            var $r = $(this);
            var depDate = normalizeLegacyDateInput($r.find('.f-dep-date').val());
            var depTime = $r.find('.f-dep-time').val();
            var arrDate = normalizeLegacyDateInput($r.find('.f-arr-date').val());
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
                hand_baggage: $r.find('.f-hand-bag').val(),
                checkin_baggage: $r.find('.f-checkin-bag').val(),
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
        var emptyLabel = options.emptyLabel || 'Select supplier';
        var html = '<option value="">' + emptyLabel + '</option>';
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
            '<tr class="q-itin-supplier-row">' +
            '<td class="q-itin-sup-idx">1</td>' +
            '<td>' +
            '<select class="form-control form-control-sm q-itin-supplier">' +
            hotelSupplierOptionsHtml(data.supplier_id, data.supplier || data.supplier_name, { allowCreate: true }) +
            '</select>' +
            '</td>' +
            '<td>' +
            '<input type="number" min="0" step="1" inputmode="numeric" class="form-control form-control-sm q-itin-rate" value="' + esc(rate) + '" placeholder="0">' +
            '</td>' +
            '<td>' +
            '<button type="button" class="btn btn-sm q-itin-supplier-remove" title="Remove supplier rate">' +
            '<i class="fas fa-trash-alt"></i></button>' +
            '</td>' +
            '</tr>';
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
        $rows.each(function (i) {
            var $row = $(this);
            $row.find('.q-itin-sup-idx').text(String(i + 1));
            $row.find('.q-itin-supplier-remove').prop('disabled', !canRemove).toggle(canRemove);
        });
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
            $sel.html(hotelSupplierOptionsHtml(curVal, curName, { allowCreate: true, emptyLabel: 'Select' }));
            if (preferSelect && preferSelect.$el && preferSelect.$el[0] === $sel[0] && preferSelect.id) {
                $sel.val(String(preferSelect.id));
            }
            qInitSupplierSelect2($sel, { placeholder: 'Select' });
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
            star_category: data.star_category || data.stars || '',
            country: data.country || data.country_name || '',
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

    function parseHotelIsoDate(str) {
        var iso = normalizeLegacyDateInput(str);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
            return null;
        }
        var d = new Date(iso + 'T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function formatHotelIsoDate(d) {
        if (!d || isNaN(d.getTime())) {
            return '';
        }
        var dd = String(d.getDate()).padStart(2, '0');
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    function addHotelDays(iso, days) {
        var d = parseHotelIsoDate(iso);
        if (!d) {
            return '';
        }
        var n = parseInt(days, 10);
        if (isNaN(n)) {
            n = 0;
        }
        d.setDate(d.getDate() + n);
        return formatHotelIsoDate(d);
    }

    function hotelNightsBetween(checkin, checkout) {
        var a = parseHotelIsoDate(checkin);
        var b = parseHotelIsoDate(checkout);
        if (!a || !b) {
            return null;
        }
        return Math.round((b.getTime() - a.getTime()) / 86400000);
    }

    /** Check-Out = Check-In + Nights */
    function syncHotelCheckoutFromNights($row) {
        if (!$row || !$row.length) {
            return;
        }
        var checkin = $row.find('.h-checkin').val();
        var nights = parseInt($row.find('.h-nights').val(), 10);
        if (!checkin || isNaN(nights) || nights < 0) {
            return;
        }
        if (nights === 0) {
            return;
        }
        var checkout = addHotelDays(checkin, nights);
        if (checkout) {
            setDateInputValue($row.find('.h-checkout'), checkout);
        }
    }

    /** Nights = Check-Out − Check-In */
    function syncHotelNightsFromDates($row) {
        if (!$row || !$row.length) {
            return;
        }
        var nights = hotelNightsBetween($row.find('.h-checkin').val(), $row.find('.h-checkout').val());
        if (nights == null || nights < 0) {
            return;
        }
        $row.find('.h-nights').val(String(nights));
    }

    function getQuotationTravelDate() {
        return normalizeLegacyDateInput($('#q_tentative_date').val() || '');
    }

    function getPreviousHotelCheckout($panel) {
        if (!$panel || !$panel.length) {
            return '';
        }
        var $prev = $panel.find('.q-hotel-row').last();
        if (!$prev.length) {
            return '';
        }
        syncHotelCheckoutFromNights($prev);
        var checkout = normalizeLegacyDateInput($prev.find('.h-checkout').val() || '');
        if (checkout) {
            return checkout;
        }
        return normalizeLegacyDateInput($prev.find('.h-checkin').val() || '');
    }

    /** Defaults for a newly added hotel row in the active option. */
    function buildNewHotelRowDefaults($panel) {
        var isFirst = !$panel || !$panel.find('.q-hotel-row').length;
        var checkin = '';
        if (isFirst) {
            // First hotel Check-In = Travel Date (Tentative Date from Tour Information).
            checkin = getQuotationTravelDate();
        } else {
            checkin = getPreviousHotelCheckout($panel);
            if (!checkin) {
                checkin = getQuotationTravelDate();
            }
        }
        var nights = 1;
        var checkout = checkin ? addHotelDays(checkin, nights) : '';
        return {
            checkin: checkin,
            nights: nights,
            checkout: checkout
        };
    }

    /** Keep each option's first hotel Check-In aligned with Travel Date. */
    function syncFirstHotelCheckinFromTravelDate(force) {
        var travelDate = getQuotationTravelDate();
        if (!travelDate) {
            return;
        }
        getHotelCategoryPanels().each(function () {
            var $first = $(this).find('.q-hotel-row').first();
            if (!$first.length) {
                return;
            }
            var cur = normalizeLegacyDateInput($first.find('.h-checkin').val() || '');
            if (!force && cur) {
                return;
            }
            setDateInputValue($first.find('.h-checkin'), travelDate);
            var nights = parseInt($first.find('.h-nights').val(), 10);
            if (isNaN(nights) || nights < 1) {
                nights = 1;
                $first.find('.h-nights').val(String(nights));
            }
            syncHotelCheckoutFromNights($first);
        });
    }

    function hotelFieldWrap(opts) {
        opts = opts || {};
        var extraClass = opts.extraClass || '';
        var ico = opts.ico;
        var caret = opts.caret !== false;
        var control = opts.control || '';
        var stepper = !!opts.stepper;
        if (stepper) {
            return '' +
                '<div class="q-hotel-field q-hotel-combo ' + extraClass + ' q-hotel-field-no-ico q-hotel-field-stepper">' +
                '<div class="q-hotel-stepper-control">' +
                '<button type="button" class="q-hotel-step-btn" data-hotel-step="-1" title="Decrease" aria-label="Decrease">−</button>' +
                control +
                '<button type="button" class="q-hotel-step-btn" data-hotel-step="1" title="Increase" aria-label="Increase">+</button>' +
                '</div>' +
                '</div>';
        }
        var icoHtml = ico
            ? ('<i class="q-hotel-ico fas ' + ico + '" aria-hidden="true"></i>')
            : '';
        return '' +
            '<div class="q-hotel-field q-hotel-combo ' + extraClass + (ico ? '' : ' q-hotel-field-no-ico') + '">' +
            '<div class="q-hotel-input-wrap">' +
            icoHtml +
            control +
            (caret ? '<i class="q-hotel-caret fas fa-chevron-down" aria-hidden="true"></i>' : '') +
            '</div>' +
            (opts.menuClass ? ('<div class="q-hotel-menu ' + opts.menuClass + '" style="display:none;"></div>') : '') +
            '</div>';
    }

    function hotelRowHtml(data) {
        var d = normalizeHotelData(data);
        return '' +
            '<div class="q-hotel-row">' +
            '<input type="hidden" class="h-city-id" value="' + esc(d.city_id) + '">' +
            '<input type="hidden" class="h-hotel-id" value="' + esc(d.hotel_id) + '">' +
            '<input type="hidden" class="h-star-category" value="' + esc(d.star_category) + '">' +
            '<input type="hidden" class="h-country" value="' + esc(d.country) + '">' +
            '<div class="q-hotel-fields">' +
            '<div class="q-hotel-accom">' +
            hotelFieldWrap({
                extraClass: 'q-hotel-field-city',
                ico: 'fa-map-marker-alt',
                menuClass: 'q-hotel-city-menu',
                control: '<input type="text" class="form-control h-city" value="' + esc(d.city) + '" autocomplete="off" placeholder="City">'
            }) +
            hotelFieldWrap({
                extraClass: 'q-hotel-field-hotel',
                ico: 'fa-hotel',
                menuClass: 'q-hotel-name-menu',
                control: '<input type="text" class="form-control h-name" value="' + esc(d.name) + '" autocomplete="off" placeholder="Hotel">'
            }) +
            hotelFieldWrap({
                extraClass: 'q-hotel-field-room',
                ico: 'fa-bed',
                menuClass: 'q-hotel-room-menu',
                control: '<input type="text" class="form-control h-room" value="' + esc(d.room_type) + '" autocomplete="off" placeholder="Room Type">'
            }) +
            hotelFieldWrap({
                extraClass: 'q-hotel-field-rooms',
                ico: false,
                caret: false,
                stepper: true,
                control: '<input type="number" min="0" class="form-control h-rooms" value="' + esc(d.rooms !== '' && d.rooms != null ? d.rooms : '0') + '" placeholder="0">'
            }) +
            hotelFieldWrap({
                extraClass: 'q-hotel-field-meal',
                ico: 'fa-utensils',
                menuClass: 'q-hotel-meal-menu',
                control: '<input type="text" class="form-control h-meal" value="' + esc(d.meal_plan) + '" autocomplete="off" placeholder="EP / CP / MAP / AP">'
            }) +
            hotelFieldWrap({
                extraClass: 'q-hotel-field-nts',
                ico: false,
                caret: false,
                stepper: true,
                control: '<input type="number" min="0" class="form-control h-nights" value="' + esc(d.nights !== '' && d.nights != null ? d.nights : '0') + '" placeholder="0">'
            }) +
            hotelFieldWrap({
                extraClass: 'q-hotel-field-date q-hotel-field-checkin',
                ico: false,
                caret: false,
                control: '<input type="text" class="form-control h-checkin js-q-date-input" value="' + esc(formatDisplayDate(d.checkin)) + '" placeholder="dd/mm/yyyy" autocomplete="off">'
            }) +
            hotelFieldWrap({
                extraClass: 'q-hotel-field-date q-hotel-field-checkout',
                ico: false,
                caret: false,
                control: '<input type="text" class="form-control h-checkout js-q-date-input" value="' + esc(formatDisplayDate(d.checkout)) + '" placeholder="dd/mm/yyyy" autocomplete="off">'
            }) +
            '</div>' +
            '<div class="q-hotel-commercial">' +
            '<div class="q-hotel-field q-hotel-field-rate">' +
            '<div class="q-hotel-rate-box">' +
            '<span class="q-hotel-rate-prefix" aria-hidden="true">₹</span>' +
            '<input type="number" min="0" step="1" inputmode="numeric" class="form-control h-rate" value="' + esc(d.rate) + '" placeholder="0">' +
            '</div></div>' +
            '<div class="q-hotel-field q-hotel-field-supplier">' +
            '<select class="form-control form-control-sm h-supplier">' +
            hotelSupplierOptionsHtml(d.supplier_id, d.supplier, { allowCreate: true, emptyLabel: 'Select' }) +
            '</select></div>' +
            '<div class="q-hotel-field q-hotel-field-action">' +
            '<button type="button" class="btn q-hotel-row-more" title="More options" aria-label="More options"><i class="fas fa-ellipsis-v" aria-hidden="true"></i></button>' +
            '<button type="button" class="btn q-hotel-row-remove q-remove" data-remove=".q-hotel-row" title="Remove hotel" aria-label="Remove hotel"><i class="fas fa-trash-alt"></i></button>' +
            '</div>' +
            '</div>' +
            '</div></div>';
    }

    function hotelCategoryPanelHtml(cat) {
        cat = cat || {};
        var id = cat.id || nextHotelCategoryId();
        var label = cat.label || defaultHotelCategoryLabel(0);
        return '' +
            '<div class="q-hotel-category" data-cat-id="' + esc(id) + '">' +
            '<input type="hidden" class="q-hotel-cat-label" value="' + esc(label) + '">' +
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
        $('#qRemoveHotelCategory').prop('disabled', !canRemove).toggle(canRemove);
    }

    function setActiveHotelCategory(catId) {
        qActiveHotelCategoryId = String(catId || '');
        refreshHotelCategoryTabs();
        renderPricingSheets();
        scheduleItineraryMetaFromHotels();
        scheduleSyncReturnAirfareInclusion();
    }

    function collectHotelsFromPanel($panel) {
        var out = [];
        $panel.find('.q-hotel-rows .q-hotel-row').each(function () {
            var $r = $(this);
            var hotelId = parseInt($r.find('.h-hotel-id').val(), 10) || 0;
            var checkin = normalizeLegacyDateInput($r.find('.h-checkin').val());
            var checkout = normalizeLegacyDateInput($r.find('.h-checkout').val());
            var rate = $r.find('.h-rate').val();
            var supplierId = parseInt($r.find('.h-supplier').val(), 10) || 0;
            var $hSup = $r.find('.h-supplier');
            var supplierName = $.trim($hSup.find('option:selected').attr('data-name') || $hSup.find('option:selected').text() || '');
            if (!supplierId || supplierName.indexOf('Create new') === 0 || supplierName === 'Select supplier' || supplierName === 'Select') {
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
                star_category: $r.find('.h-star-category').val() || '',
                country: $r.find('.h-country').val() || '',
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
                qInitSupplierSelect2($row.find('.h-supplier'), { placeholder: 'Select' });
            });
            $wrap.append($panel);
        });
        qActiveHotelCategoryId = data.active_category_id || String($wrap.find('.q-hotel-category').first().attr('data-cat-id') || '');
        refreshHotelCategoryTabs();
        renderPricingSheets();
        scheduleSyncReturnAirfareInclusion();
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
        $menu.find('.q-hotel-menu-create-footer').remove();
        $menu.append(
            $('<button type="button" class="q-hotel-menu-create-footer q-hotel-city-create"></button>')
                .attr('data-name', typed || '')
                .html('<i class="fas fa-plus-circle" aria-hidden="true"></i><span>Create' +
                    (typed ? ' "' + esc(typed) + '"' : '') + '</span>')
        );
    }

    function showHotelCitySuggestions($row) {
        var $input = $row.find('.h-city');
        var $menu = $row.find('.q-hotel-city-menu');
        var typed = $.trim($input.val());
        var dest = getQuotationDestinationFilter();

        if (!dest.name) {
            var $emptyList = $('<div class="q-hotel-menu-list"></div>').append(
                '<div class="q-hotel-menu-empty">Set Destination in Tour Information (Step 1) to see cities from City Master</div>'
            );
            $menu.empty().append($emptyList);
            appendCityCreateAction($menu, typed);
            $menu.show();
            return;
        }

        fetchCityMasterCities(typed, function (cities) {
            var $list = $('<div class="q-hotel-menu-list"></div>');
            if (!cities.length) {
                if (typed) {
                    $list.append(
                        '<div class="q-hotel-menu-empty">No cities found for "' + esc(typed) + '"</div>'
                    );
                } else {
                    $list.append(
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
                        .attr('data-name', c.name)
                        .attr('data-country', c.country_name || '');
                    $btn.append($('<span></span>').text(label));
                    if (sub) {
                        $btn.append($('<span class="q-hotel-menu-sub"></span>').text(sub));
                    }
                    $list.append($btn);
                });
            }
            $menu.empty().append($list);
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
        if (hotel.star_category != null) {
            $row.find('.h-star-category').val(hotel.star_category || '');
        }
        if (hotel.country_name || hotel.country) {
            $row.find('.h-country').val(hotel.country_name || hotel.country || '');
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
        scheduleItineraryMetaFromHotels();
        scheduleSyncReturnAirfareInclusion();
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

    var HOTEL_MEAL_PLAN_OPTIONS = [
        { name: 'EP' },
        { name: 'CP' },
        { name: 'MAP' },
        { name: 'AP' }
    ];

    function getHotelMealPlanOptions(typed) {
        // Always show standard plans in fixed order: EP, CP, MAP, AP.
        return HOTEL_MEAL_PLAN_OPTIONS.slice();
    }

    function showHotelMealSuggestions($row) {
        var $menu = $row.find('.q-hotel-meal-menu');
        var typed = $.trim($row.find('.h-meal').val());
        var meals = getHotelMealPlanOptions(typed);
        var typedUpper = typed.toUpperCase();
        var exactMatch = HOTEL_MEAL_PLAN_OPTIONS.some(function (m) {
            return String(m.name || '').toUpperCase() === typedUpper;
        });

        $menu.empty();
        meals.forEach(function (m, idx) {
            var $btn = $('<button type="button" class="q-hotel-menu-item q-hotel-meal-pick"></button>')
                .attr('data-index', idx)
                .data('meal', m);
            $btn.append($('<span></span>').text(m.name || ('Meal ' + (idx + 1))));
            $menu.append($btn);
        });
        if (typed && !exactMatch) {
            $menu.append(
                $('<button type="button" class="q-hotel-menu-item q-hotel-meal-pick"></button>')
                    .data('meal', { name: typed })
                    .append($('<span></span>').text(typed))
            );
        }
        $menu.show();
    }

    function appendHotelCreateAction($menu, typed) {
        $menu.find('.q-hotel-menu-create-footer').remove();
        $menu.append(
            $('<button type="button" class="q-hotel-menu-create-footer q-hotel-name-create"></button>')
                .attr('data-name', typed || '')
                .html('<i class="fas fa-plus-circle" aria-hidden="true"></i><span>Create' +
                    (typed ? ' "' + esc(typed) + '"' : '') + '</span>')
        );
    }

    function showHotelNameSuggestions($row) {
        var $menu = $row.find('.q-hotel-name-menu');
        var typed = $.trim($row.find('.h-name').val());
        var cityId = parseInt($row.find('.h-city-id').val(), 10) || 0;
        var dest = getQuotationDestinationFilter();

        if (!dest.name) {
            var $emptyList = $('<div class="q-hotel-menu-list"></div>').append(
                '<div class="q-hotel-menu-empty">Set Destination in Tour Information (Step 1) to see hotels from Hotel Master</div>'
            );
            $menu.empty().append($emptyList);
            appendHotelCreateAction($menu, typed);
            $menu.show();
            return;
        }

        fetchQuotationHotelsSearch(typed, cityId, function (hotels) {
            var $list = $('<div class="q-hotel-menu-list"></div>');
            var cache = getHotelRowCache($row);
            cache.hotels = hotels;
            setHotelRowCache($row, cache);

            if (!hotels.length) {
                if (typed) {
                    $list.append(
                        '<div class="q-hotel-menu-empty">No hotels found for "' + esc(typed) + '"</div>'
                    );
                } else {
                    $list.append(
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
                    $list.append($btn);
                });
            }
            $menu.empty().append($list);
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
        if ($row && $row.length) {
            initQuotationDatePickers($row);
        }
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

    function itineraryDaySummernoteToolbar() {
        return [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['para', ['ul', 'ol']],
            ['insert', ['link', 'picture']]
        ];
    }

    function initQuotationSummernote($ta, height, toolbar) {
        if (!$ta || !$ta.length || !$.fn.summernote) {
            return;
        }
        if ($ta.data('summernote')) {
            return;
        }
        var opts = {
            height: height || 200,
            toolbar: toolbar || quotationSummernoteToolbar(),
            dialogsInBody: true
        };
        if ($ta.hasClass('q-day-textarea')) {
            opts.callbacks = {
                onInit: function () {
                    updateDayCharCount($ta.closest('.q-day-card'));
                },
                onKeyup: function () {
                    updateDayCharCount($ta.closest('.q-day-card'));
                },
                onChange: function () {
                    updateDayCharCount($ta.closest('.q-day-card'));
                    scheduleSyncReturnAirfareInclusion(true);
                }
            };
        }
        $ta.summernote(opts);
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

    function stripHtmlText(html) {
        return String(html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function inferDayOvernightValue(day, fallbackDest) {
        day = day || {};
        var explicit = String(day.overnight || day.overnight_stay || day.stay || '').trim();
        if (explicit) {
            return explicit;
        }
        var desc = String(day.description || day.todo || '');
        var m = desc.match(/Accommodation[:\s]*<\/?(?:strong|b|span)[^>]*>\s*([^<\n]+)/i)
            || desc.match(/Accommodation[:\s]+([^<\n]+)/i)
            || desc.match(/Overnight(?:\s+Stay)?[:\s]+([^<\n]+)/i);
        if (m && m[1]) {
            return String(m[1]).replace(/<\/?[^>]+>/g, '').trim();
        }
        var dest = String(fallbackDest || '').trim();
        if (dest) {
            return dest.split(',')[0].trim();
        }
        return '';
    }

    function inferDayMealValue(day) {
        day = day || {};
        var explicit = String(day.meal || day.meals || day.meal_plan || '').trim();
        if (explicit) {
            return explicit;
        }
        var desc = String(day.description || day.todo || '');
        var m = desc.match(/Meals?[:\s]*<\/?(?:strong|b|span)[^>]*>\s*([^<\n]+)/i)
            || desc.match(/Meals?[:\s]+([^<\n]+)/i);
        if (m && m[1]) {
            return String(m[1]).replace(/<\/?[^>]+>/g, '').trim();
        }
        var text = stripHtmlText(desc).toLowerCase();
        var meals = [];
        if (/breakfast|\bbb\b|\bcp\b/.test(text)) meals.push('Breakfast');
        if (/lunch/.test(text)) meals.push('Lunch');
        if (/dinner/.test(text)) meals.push('Dinner');
        return meals.join(' · ');
    }

    function normalizeDayServiceMode(val) {
        var v = String(val || '').trim().toLowerCase();
        if (v === 'private' || v === 'sic') {
            return v;
        }
        return '';
    }

    function dayServiceModeLabel(val) {
        if (val === 'private') {
            return 'Private';
        }
        if (val === 'sic') {
            return 'SIC';
        }
        return '';
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
            image: day.image || day.img || day.image_url || '',
            overnight: String(day.overnight || day.overnight_stay || day.stay || '').trim(),
            meal: String(day.meal || day.meals || day.meal_plan || '').trim(),
            tours: normalizeDayServiceMode(day.tours || day.tour_type || day.tour_mode),
            transfers: normalizeDayServiceMode(day.transfers || day.transfer_type || day.transfer_mode)
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
                image: cur.image || saved.image,
                overnight: cur.overnight || saved.overnight,
                meal: cur.meal || saved.meal,
                tours: cur.tours || saved.tours,
                transfers: cur.transfers || saved.transfers
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

    function updateDayCharCount($card) {
        if (!$card || !$card.length) {
            return;
        }
        var html = readSummernoteHtml($card.find('.q-day-textarea'));
        var len = stripHtmlText(html).length;
        var max = 2000;
        $card.find('.q-day-char-count').text(len + ' / ' + max);
    }

    function syncDayMetaDisplay($card) {
        if (!$card || !$card.length) {
            return;
        }
        var overnight = ($card.find('.q-day-overnight').val() || '').trim();
        var meal = ($card.find('.q-day-meal').val() || '').trim();
        var $overnightVal = $card.find('.q-day-meta-card[data-meta="overnight"] .q-day-meta-value');
        var $mealVal = $card.find('.q-day-meta-card[data-meta="meal"] .q-day-meta-value');
        if (!$overnightVal.hasClass('is-editing')) {
            $overnightVal.text(overnight || '—').toggleClass('is-empty', !overnight);
        }
        if (!$mealVal.hasClass('is-editing')) {
            $mealVal.text(meal || '—').toggleClass('is-empty', !meal);
        }
    }

    var itineraryHotelMetaTimer = null;

    function formatItineraryMealFromHotel(mealPlan, roomType) {
        var info = qpHotelMealLabels(mealPlan, roomType || '');
        var meals = String((info && info.meals) || '').trim();
        if (!meals || /^none$/i.test(meals)) {
            return '';
        }
        return meals.replace(/\s*&\s*/g, ' + ');
    }

    function hotelOvernightLabel(hotel) {
        hotel = hotel || {};
        var city = String(hotel.city || '').trim();
        if (city) {
            return city.split(',')[0].trim();
        }
        return '';
    }

    function mealLabelForHotelStayDay(mealPlan, roomType, dayOffset, nights) {
        var code = String(qpHotelMealCode(mealPlan) || '').toUpperCase();
        var full = formatItineraryMealFromHotel(mealPlan, roomType);
        nights = parseInt(nights, 10) || 0;
        dayOffset = parseInt(dayOffset, 10) || 0;

        // Checkout morning: breakfast only when the plan includes it (no overnight).
        if (nights > 0 && dayOffset === nights) {
            if (code === 'EP' || !code) {
                return '';
            }
            if (code === 'CP' || code === 'MAP' || code === 'AP' || code === 'AI') {
                return 'Breakfast';
            }
            return full;
        }

        // Overnight days covered by check-in → night before checkout.
        return full;
    }

    function buildItineraryHotelMetaByDay(totalDays) {
        totalDays = parseInt(totalDays, 10) || 0;
        var byDay = [];
        var i;
        for (i = 0; i < totalDays; i++) {
            byDay.push({ overnight: '', meal: '' });
        }
        if (totalDays <= 0) {
            return byDay;
        }

        var hotels = collectHotels() || [];
        var baseDate = normalizeLegacyDateInput($('#q_tentative_date').val() || '');
        var dateMap = {};
        var hasDatedCoverage = false;
        var sequential = [];

        hotels.forEach(function (raw) {
            var d = normalizeHotelData(raw);
            var nights = parseInt(d.nights, 10);
            if (isNaN(nights) || nights < 0) {
                nights = 0;
            }
            if (d.checkin && d.checkout) {
                var fromDates = hotelNightsBetween(d.checkin, d.checkout);
                if (fromDates > 0) {
                    nights = fromDates;
                }
            }
            if (!nights && !d.checkin) {
                return;
            }
            if (!nights && d.checkin && d.checkout) {
                nights = hotelNightsBetween(d.checkin, d.checkout) || 0;
            }
            if (!nights) {
                return;
            }

            var overnight = hotelOvernightLabel(d);
            var n;
            for (n = 0; n < nights; n++) {
                sequential.push({
                    overnight: overnight,
                    meal: mealLabelForHotelStayDay(d.meal_plan, d.room_type, n, nights)
                });
            }

            if (d.checkin) {
                hasDatedCoverage = true;
                for (n = 0; n < nights; n++) {
                    var overnightIso = addHotelDays(d.checkin, n);
                    if (overnightIso) {
                        dateMap[overnightIso] = {
                            overnight: overnight,
                            meal: mealLabelForHotelStayDay(d.meal_plan, d.room_type, n, nights)
                        };
                    }
                }
                // Checkout morning meal (no overnight).
                var checkoutIso = d.checkout || addHotelDays(d.checkin, nights);
                if (checkoutIso) {
                    var checkoutMeal = mealLabelForHotelStayDay(d.meal_plan, d.room_type, nights, nights);
                    if (checkoutMeal) {
                        if (!dateMap[checkoutIso]) {
                            dateMap[checkoutIso] = { overnight: '', meal: checkoutMeal };
                        } else if (!dateMap[checkoutIso].meal) {
                            dateMap[checkoutIso].meal = checkoutMeal;
                        }
                    }
                }
            }
        });

        if (hasDatedCoverage && baseDate) {
            for (i = 0; i < totalDays; i++) {
                var dayIso = addHotelDays(baseDate, i);
                if (dayIso && dateMap[dayIso]) {
                    byDay[i] = {
                        overnight: dateMap[dayIso].overnight || '',
                        meal: dateMap[dayIso].meal || ''
                    };
                }
            }
            return byDay;
        }

        for (i = 0; i < totalDays && i < sequential.length; i++) {
            byDay[i] = {
                overnight: sequential[i].overnight || '',
                meal: sequential[i].meal || ''
            };
        }
        // Sequential fallback: add checkout-morning breakfast after last overnight.
        if (sequential.length && sequential.length < totalDays) {
            var lastHotel = null;
            hotels.forEach(function (raw) {
                var d = normalizeHotelData(raw);
                if (d.meal_plan) {
                    lastHotel = d;
                }
            });
            if (lastHotel) {
                var checkoutMealOnly = mealLabelForHotelStayDay(
                    lastHotel.meal_plan,
                    lastHotel.room_type,
                    sequential.length,
                    sequential.length
                );
                if (checkoutMealOnly && !byDay[sequential.length].meal) {
                    byDay[sequential.length] = {
                        overnight: '',
                        meal: checkoutMealOnly
                    };
                }
            }
        }
        return byDay;
    }

    function syncItineraryMetaFromHotels() {
        var $cards = $('#qItineraryDays .q-day-card');
        if (!$cards.length) {
            return;
        }
        var byDay = buildItineraryHotelMetaByDay($cards.length);
        $cards.each(function () {
            var $card = $(this);
            if ($card.find('.q-day-meta-value.is-editing').length) {
                return;
            }
            var idx = parseInt($card.attr('data-day-index'), 10);
            if (isNaN(idx) || idx < 0) {
                idx = 0;
            }
            var meta = byDay[idx] || { overnight: '', meal: '' };
            $card.find('.q-day-overnight').val(meta.overnight || '');
            $card.find('.q-day-meal').val(meta.meal || '');
            syncDayMetaDisplay($card);
        });
        itineraryPreserveSeed = snapshotItinerary();
        scheduleSyncReturnAirfareInclusion();
    }

    function scheduleItineraryMetaFromHotels() {
        clearTimeout(itineraryHotelMetaTimer);
        itineraryHotelMetaTimer = setTimeout(function () {
            syncItineraryMetaFromHotels();
        }, 120);
    }

    function readDayMetaValue($el) {
        var text = ($el.text() || '').replace(/\u00a0/g, ' ').trim();
        if (text === '—' || text === '-' || text === '–') {
            return '';
        }
        return text;
    }

    function commitDayMetaValue($el) {
        if (!$el || !$el.length) {
            return;
        }
        var $card = $el.closest('.q-day-card');
        var $meta = $el.closest('.q-day-meta-card');
        var kind = $meta.attr('data-meta');
        var value = readDayMetaValue($el);
        if (kind === 'meal') {
            $card.find('.q-day-meal').val(value);
        } else {
            $card.find('.q-day-overnight').val(value);
        }
        $el.removeClass('is-editing');
        $el.text(value || '—').toggleClass('is-empty', !value);
        itineraryPreserveSeed = snapshotItinerary();
        scheduleSyncReturnAirfareInclusion();
    }

    function beginDayMetaEdit($meta) {
        if (!$meta || !$meta.length) {
            return;
        }
        var $el = $meta.find('.q-day-meta-value').first();
        if (!$el.length) {
            return;
        }
        var current = readDayMetaValue($el);
        $el.addClass('is-editing').removeClass('is-empty').text(current);
        $el.trigger('focus');
        try {
            var range = document.createRange();
            range.selectNodeContents($el[0]);
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        } catch (e) { /* ignore */ }
    }

    function fmtItineraryDayHeading(baseDate, offset, dayNum) {
        if (!baseDate) {
            return 'Day ' + dayNum;
        }
        var iso = normalizeLegacyDateInput(baseDate);
        if (!iso) {
            return 'Day ' + dayNum;
        }
        var d = new Date(iso + 'T00:00:00');
        if (isNaN(d.getTime())) {
            return 'Day ' + dayNum;
        }
        d.setDate(d.getDate() + offset);
        var dd = String(d.getDate()).padStart(2, '0');
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var yyyy = d.getFullYear();
        return dd + '/' + mm + '/' + yyyy + ' | ' + DAY_NAMES[d.getDay()] + ' – Day ' + dayNum;
    }

    function getItineraryDestinationLabel() {
        return ($('[name=destination]').val() || '').trim();
    }

    var activeItineraryDayIndex = 0;

    function syncItineraryDayNav($card) {
        if (!$card || !$card.length) {
            return;
        }
        var total = $('#qItineraryDays .q-day-card').length;
        var idx = parseInt($card.attr('data-day-index'), 10) || 0;
        $card.find('.q-day-prev').prop('disabled', idx <= 0);
        $card.find('.q-day-next').prop('disabled', idx >= total - 1);
    }

    function showItineraryDay(index) {
        var $cards = $('#qItineraryDays .q-day-card');
        if (!$cards.length) {
            return;
        }
        index = parseInt(index, 10);
        if (isNaN(index) || index < 0) {
            index = 0;
        }
        if (index >= $cards.length) {
            index = $cards.length - 1;
        }
        activeItineraryDayIndex = index;
        $cards.each(function (i) {
            var $c = $(this);
            var active = i === index;
            $c.toggleClass('is-active', active);
            $c.attr('aria-hidden', active ? 'false' : 'true');
            if (active) {
                syncItineraryDayNav($c);
                var editorId = $c.attr('data-editor-id');
                if (editorId) {
                    initQuotationSummernote($('#' + editorId), 220, itineraryDaySummernoteToolbar());
                    updateDayCharCount($c);
                }
            }
        });
    }

    function initItineraryEditors() {
        var $active = $('#qItineraryDays .q-day-card.is-active');
        if (!$active.length) {
            $active = $('#qItineraryDays .q-day-card').first();
            $active.addClass('is-active');
        }
        if ($active.length) {
            var editorId = $active.attr('data-editor-id');
            if (editorId) {
                initQuotationSummernote($('#' + editorId), 220, itineraryDaySummernoteToolbar());
                updateDayCharCount($active);
            }
            syncItineraryDayNav($active);
        }
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
                image: imageVal,
                overnight: ($c.find('.q-day-overnight').val() || '').trim(),
                meal: ($c.find('.q-day-meal').val() || '').trim(),
                tours: normalizeDayServiceMode($c.find('.q-day-tours').val()),
                transfers: normalizeDayServiceMode($c.find('.q-day-transfers').val())
            });
        });
        itineraryPreserveSeed = normalizeItineraryList(data);
        return data;
    }

    function dayServiceDropdownHtml(kind, label, selected) {
        selected = normalizeDayServiceMode(selected);
        var selLabel = dayServiceModeLabel(selected);
        var btnText = selLabel ? (label + ' · ' + selLabel) : label;
        var isSet = selected ? ' is-set' : '';
        return '' +
            '<div class="dropdown q-day-svc-dd" data-svc="' + esc(kind) + '">' +
            '<button type="button" class="btn q-day-svc-btn' + isSet + '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="' + esc(label) + '">' +
            '<span class="q-day-svc-btn-label">' + esc(btnText) + '</span>' +
            '<i class="fas fa-chevron-down q-day-svc-caret" aria-hidden="true"></i>' +
            '</button>' +
            '<div class="dropdown-menu dropdown-menu-right q-day-svc-menu">' +
            '<a class="dropdown-item q-day-svc-option' + (selected === 'private' ? ' active' : '') + '" href="#" data-svc-value="private">' +
            '<i class="fas fa-check q-day-svc-check" aria-hidden="true"></i>Private</a>' +
            '<a class="dropdown-item q-day-svc-option' + (selected === 'sic' ? ' active' : '') + '" href="#" data-svc-value="sic">' +
            '<i class="fas fa-check q-day-svc-check" aria-hidden="true"></i>SIC <span class="q-day-svc-option-sub">(Seat in Coach)</span></a>' +
            '</div></div>';
    }

    function syncDayServiceControl($card, kind) {
        if (!$card || !$card.length) {
            return;
        }
        var $input = $card.find('.q-day-' + kind);
        var val = normalizeDayServiceMode($input.val());
        $input.val(val);
        var label = kind === 'transfers' ? 'Transfers' : 'Tours';
        var selLabel = dayServiceModeLabel(val);
        var $dd = $card.find('.q-day-svc-dd[data-svc="' + kind + '"]');
        var $btn = $dd.find('.q-day-svc-btn');
        $btn.toggleClass('is-set', !!val);
        $btn.find('.q-day-svc-btn-label').text(selLabel ? (label + ' · ' + selLabel) : label);
        $dd.find('.q-day-svc-option').each(function () {
            var optVal = String($(this).attr('data-svc-value') || '');
            $(this).toggleClass('active', optVal === val);
        });
    }

    function syncDayServiceControls($card) {
        syncDayServiceControl($card, 'tours');
        syncDayServiceControl($card, 'transfers');
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
        var destLabel = getItineraryDestinationLabel();
        var preferredActive = activeItineraryDayIndex;
        for (var i = 0; i < totalDays; i++) {
            var editorId = 'q_itin_day_' + rebuildToken + '_' + i;
            itineraryEditorIds.push(editorId);
            var prev = normalizeItineraryDay(existing[i] || {});
            var overnightVal = prev.overnight || inferDayOvernightValue(prev, destLabel);
            var mealVal = prev.meal || inferDayMealValue(prev);
            var heading = fmtItineraryDayHeading(baseDate, i, i + 1);
            var imgVal = prev.image || '';
            var imgCountLabel = imgVal ? '1 Image' : '0 Image';
            var dayMenuItems = '<a class="dropdown-item q-view-full-itinerary" href="#"><i class="fas fa-list-ul mr-2"></i>View full itinerary</a><div class="dropdown-divider"></div>';
            for (var m = 0; m < totalDays; m++) {
                dayMenuItems += '<a class="dropdown-item q-day-jump" href="#" data-day-index="' + m + '">Day ' + (m + 1) + '</a>';
            }
            var toursVal = prev.tours || '';
            var transfersVal = prev.transfers || '';
            var $card = $(
                '<div class="q-day-card" data-day-index="' + i + '" data-editor-id="' + editorId + '" aria-hidden="true">' +
                '<div class="q-day-toolbar">' +
                '<div class="q-day-toolbar-left">' +
                '<span class="q-day-calendar-icon" aria-hidden="true"><i class="fas fa-calendar-alt"></i></span>' +
                '<div class="q-day-toolbar-text">' +
                '<div class="q-day-head-label"></div>' +
                '<div class="q-day-head-sub"></div>' +
                '</div>' +
                '</div>' +
                '<div class="q-day-toolbar-right">' +
                dayServiceDropdownHtml('tours', 'Tours', toursVal) +
                dayServiceDropdownHtml('transfers', 'Transfers', transfersVal) +
                '<button type="button" class="btn q-day-ai-btn" title="AI Suggest this day"><i class="fas fa-magic mr-1"></i>AI Suggest</button>' +
                '<button type="button" class="btn q-day-nav-btn q-day-prev"><i class="fas fa-chevron-left"></i><span>Previous Day</span></button>' +
                '<button type="button" class="btn q-day-nav-btn q-day-nav-primary q-day-next"><span>Next Day</span><i class="fas fa-chevron-right"></i></button>' +
                '<button type="button" class="btn q-day-nav-btn q-view-full-itinerary" title="View full itinerary" aria-label="View full itinerary"><i class="fas fa-eye"></i></button>' +
                '<div class="dropdown q-day-more-wrap">' +
                '<button type="button" class="btn q-day-more-btn" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="More">' +
                '<i class="fas fa-ellipsis-v"></i></button>' +
                '<div class="dropdown-menu dropdown-menu-right">' + dayMenuItems + '</div>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '<div class="q-day-body">' +
                '<div class="q-day-main-grid">' +
                '<div class="q-day-content-col">' +
                '<div class="q-floating-field">' +
                '<label class="q-floating-label">Title <span class="text-danger">*</span></label>' +
                '<input type="text" class="form-control q-day-title" placeholder="Day title" required>' +
                '</div>' +
                '<div class="q-day-editor-wrap">' +
                '<textarea class="form-control q-day-textarea"></textarea>' +
                '<div class="q-day-char-count">0 / 2000</div>' +
                '</div>' +
                '</div>' +
                '<div class="q-day-image-panel">' +
                '<div class="q-day-image-hd">' +
                '<div class="q-day-image-hd-left"><i class="fas fa-image"></i><span>Destination Image</span></div>' +
                '<span class="q-day-image-count">' + esc(imgCountLabel) + '</span>' +
                '</div>' +
                '<div class="q-img-preview-wrap">' +
                '<img class="q-img-preview" alt="Day image preview">' +
                '<div class="q-img-preview-empty text-muted small">No image selected</div>' +
                '</div>' +
                '<div class="q-day-image-actions">' +
                '<button type="button" class="btn q-day-img-btn q-choose-image"><i class="fas fa-upload mr-1"></i>Upload</button>' +
                '<button type="button" class="btn q-day-img-btn q-search-day-image"><i class="fas fa-search mr-1"></i>Search Online</button>' +
                '<button type="button" class="btn q-day-img-btn q-day-img-remove q-clear-day-image"><i class="fas fa-trash-alt mr-1"></i>Remove</button>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '<div class="q-day-meta-row">' +
                '<div class="q-day-meta-card" data-meta="overnight">' +
                '<div class="q-day-meta-icon"><i class="fas fa-bed"></i></div>' +
                '<div class="q-day-meta-text">' +
                '<span class="q-day-meta-label">Overnight Stay</span>' +
                '<span class="q-day-meta-value" contenteditable="true" spellcheck="false" role="textbox" tabindex="0"></span>' +
                '<input type="hidden" class="q-day-overnight">' +
                '</div>' +
                '<div class="q-day-meta-actions">' +
                '<button type="button" class="btn q-day-meta-edit"><i class="fas fa-pen"></i> Edit</button>' +
                '<button type="button" class="btn q-day-meta-remove"><i class="fas fa-trash-alt"></i> Remove</button>' +
                '</div>' +
                '</div>' +
                '<div class="q-day-meta-card" data-meta="meal">' +
                '<div class="q-day-meta-icon"><i class="fas fa-utensils"></i></div>' +
                '<div class="q-day-meta-text">' +
                '<span class="q-day-meta-label">Meal</span>' +
                '<span class="q-day-meta-value" contenteditable="true" spellcheck="false" role="textbox" tabindex="0"></span>' +
                '<input type="hidden" class="q-day-meal">' +
                '</div>' +
                '<div class="q-day-meta-actions">' +
                '<button type="button" class="btn q-day-meta-edit"><i class="fas fa-pen"></i> Edit</button>' +
                '<button type="button" class="btn q-day-meta-remove"><i class="fas fa-trash-alt"></i> Remove</button>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '<input type="hidden" class="q-day-image">' +
                '<input type="hidden" class="q-day-tours" value="' + esc(toursVal) + '">' +
                '<input type="hidden" class="q-day-transfers" value="' + esc(transfersVal) + '">' +
                '</div></div>'
            );
            $card.find('.q-day-head-label').text(heading);
            $card.find('.q-day-head-sub').text(destLabel || 'Destination');
            $card.find('.q-day-title').val(prev.title || '');
            $card.find('.q-day-textarea').attr('id', editorId).val(prev.description || '');
            $card.find('.q-day-overnight').val(overnightVal);
            $card.find('.q-day-meal').val(mealVal);
            $card.find('.q-day-image').val(imgVal);
            $card.find('.q-day-tours').val(toursVal);
            $card.find('.q-day-transfers').val(transfersVal);
            syncDayMetaDisplay($card);
            syncDayServiceControls($card);
            updateDayImagePreview($card, imgVal);
            $wrap.append($card);
        }

        rebuildItinerarySeq += 1;
        var seq = rebuildItinerarySeq;
        if (preferredActive >= totalDays) {
            preferredActive = Math.max(0, totalDays - 1);
        }
        showItineraryDay(preferredActive);
        syncItineraryMetaFromHotels();
        window.setTimeout(function () {
            if (seq !== rebuildItinerarySeq) {
                return;
            }
            if ($('#qSectionBody4').is(':visible')) {
                initItineraryEditors();
            }
            scheduleSyncReturnAirfareInclusion();
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

    function loadTermsFromMaster(fields) {
        var master = (typeof QUOTATION_TERMS_MASTER === 'object' && QUOTATION_TERMS_MASTER) ? QUOTATION_TERMS_MASTER : {};
        var list = Array.isArray(fields) && fields.length ? fields : richEditors;
        list.forEach(function (field) {
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

        if (parseFloat(pkg.sale_price) > 0 && getItineraryLandTotal() <= 0 && rawNumber('.q-cost[data-key="land"]') <= 0) {
            $('.q-cost[data-key="land"]').val(parseFloat(pkg.sale_price).toFixed(2));
        }

        if (pkg.inclusion && !readSummernoteHtml($('#qed_inclusion')).trim()) {
            setRichEditorValue('qed_inclusion', plainTextToHtml(pkg.inclusion));
        }
        if (pkg.exclusion && !readSummernoteHtml($('#qed_exclusion')).trim()) {
            setRichEditorValue('qed_exclusion', plainTextToHtml(pkg.exclusion));
        }

        $('#q_without_itinerary').prop('checked', false);
        syncTourCostOptUiFromMasters();
        rebuildItinerary(pkg.itinerary || []);
        recalcCosts();

        expandWizardSection(4);
        window.setTimeout(initItineraryEditors, 120);

        showItineraryLoadedPopup(pkg.title || pkg.label || 'Package');
    }

    function showItineraryLoadedPopup(packageTitle) {
        var title = String(packageTitle || 'Package').trim() || 'Package';
        $('#qItineraryLoadedMsg').html(
            'Itinerary loaded from package <strong>' + esc(title) + '</strong>.'
        );
        var $modal = $('#qItineraryLoadedModal');
        if (!$modal.length) {
            $('#qAlert').html(
                '<div class="alert alert-success">Itinerary loaded from package <strong>' + esc(title) + '</strong>.</div>'
            );
            return;
        }
        $modal.modal('show');
        window.clearTimeout(showItineraryLoadedPopup._timer);
        showItineraryLoadedPopup._timer = window.setTimeout(function () {
            if ($modal.hasClass('show')) {
                $modal.modal('hide');
            }
        }, 3500);
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

    function formatPackageUpdated(raw) {
        if (!raw) {
            return '';
        }
        if (typeof moment === 'function') {
            var m = moment(raw);
            if (m.isValid()) {
                return m.format('DD MMM YYYY');
            }
        }
        var d = new Date(raw);
        if (isNaN(d.getTime())) {
            return '';
        }
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return String(d.getDate()).padStart(2, '0') + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    }

    function packageDurationLabel(pkg) {
        var nights = Math.max(0, parseInt(pkg.duration_nights, 10) || 0);
        var days = Math.max(0, parseInt(pkg.duration_days, 10) || 0);
        if (days <= 0 && nights > 0) {
            days = nights + 1;
        }
        if (nights <= 0 && days <= 0) {
            return '';
        }
        return nights + 'N / ' + Math.max(1, days) + 'D';
    }

    function packageTierLabel(pkg) {
        return (pkg.category || pkg.tier || pkg.status || '').toString().trim();
    }

    function updateSelectedPackageCard(pkg) {
        selectedPackageForItinerary = pkg || null;
        var $empty = $('#qSelectedPackageEmpty');
        var $filled = $('#qSelectedPackageFilled');
        var $btn = $('#qApplyPackageItinerary');
        if (!pkg || !pkg.id) {
            $empty.css('display', 'flex');
            $filled.hide();
            $btn.prop('disabled', true);
            return;
        }
        $empty.hide();
        $filled.css('display', 'flex');
        $('#qSelectedPackageTitle').text(pkg.title || pkg.label || 'Package');
        var metaHtml = '';
        if (pkg.destination) {
            metaHtml += '<span><i class="fas fa-map-marker-alt"></i> ' + esc(pkg.destination) + '</span>';
        }
        var dur = packageDurationLabel(pkg);
        if (dur) {
            var nights = Math.max(0, parseInt(pkg.duration_nights, 10) || 0);
            var days = Math.max(0, parseInt(pkg.duration_days, 10) || 0);
            if (days <= 0 && nights > 0) {
                days = nights + 1;
            }
            metaHtml += '<span><i class="fas fa-moon"></i> ' + esc(nights + ' Nights / ' + Math.max(1, days) + ' Days') + '</span>';
        }
        var tier = packageTierLabel(pkg);
        if (tier) {
            metaHtml += '<span><i class="fas fa-tag"></i> ' + esc(tier) + '</span>';
        }
        $('#qSelectedPackageMeta').html(metaHtml);
        var updated = formatPackageUpdated(pkg.updated_at);
        $('#qSelectedPackageUpdated').html(
            updated
                ? '<i class="far fa-clock" aria-hidden="true"></i> Last Updated: ' + esc(updated)
                : ''
        );
        var img = String(pkg.featured_image || pkg.image || '').trim();
        var $img = $('#qSelectedPackageImage');
        var $fallback = $('#qSelectedPackageImageFallback');
        if (img) {
            $img.attr('src', absUrl(img)).show();
            $fallback.hide();
        } else {
            $img.attr('src', '').hide();
            $fallback.show();
        }
        $btn.prop('disabled', false);
    }

    function renderPackageMenu($menu, items, query) {
        $menu.empty();
        if (!items || !items.length) {
            $menu.append('<div class="q-itin-pkg-empty">No packages found' + (query ? ' for "' + esc(query) + '"' : '') + '</div>');
        } else {
            items.forEach(function (item) {
                var $btn = $('<button type="button" class="q-itin-pkg-item"></button>');
                $btn.append('<i class="fas fa-map-marker-alt q-itin-pkg-item-pin" aria-hidden="true"></i>');
                var $text = $('<span class="q-itin-pkg-item-text"></span>');
                $text.append($('<span class="q-itin-pkg-item-title"></span>').text(item.label || item.title || 'Package'));
                var dayTitles = Array.isArray(item.day_titles) ? item.day_titles : [];
                if (dayTitles.length) {
                    var dayLine = dayTitles.map(function (t, i) {
                        return 'Day ' + (i + 1) + ': ' + String(t || '').trim();
                    }).filter(Boolean).join(' · ');
                    if (dayLine) {
                        $text.append($('<span class="q-itin-pkg-item-days"></span>').text(dayLine));
                    }
                }
                $btn.append($text);
                $btn.data('package', item);
                if (selectedPackageForItinerary && selectedPackageForItinerary.id === item.id) {
                    $btn.addClass('is-active');
                }
                $menu.append($btn);
            });
        }
        $menu.show();
    }

    function syncPackageSearchClearBtn() {
        var val = ($('.js-q-package-search').first().val() || '').trim();
        $('#qItinPkgSearchClear').toggle(!!val);
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

    function filterAndRenderPackageMenu($menu, query) {
        searchPackagesForQuotation(query, function (items) {
            renderPackageMenu($menu, items, query);
        });
    }

    function initPackageSuggest() {
        $(document).on('input focus', '.js-q-package-search', function () {
            var $input = $(this);
            var $menu = $input.closest('.q-itin-search-primary, .q-itin-pkg-search-wrap, .q-lead-combobox').find('.js-q-package-menu');
            if (!$menu.length) {
                $menu = $input.closest('.q-itin-pkg-search-card').find('.js-q-package-menu');
            }
            var query = ($input.val() || '').trim();
            syncPackageSearchClearBtn();

            hideAllPackageMenus();
            clearTimeout(packageLookupTimer);
            packageLookupTimer = setTimeout(function () {
                filterAndRenderPackageMenu($menu, query);
            }, 220);
        });

        $(document).on('click', '#qItinPkgSearchClear', function (e) {
            e.preventDefault();
            var $input = $('.js-q-package-search').first();
            $input.val('').trigger('focus');
            syncPackageSearchClearBtn();
            hideAllPackageMenus();
        });

        $(document).on('click', '.js-q-package-menu .q-itin-pkg-item, .js-q-package-menu .q-lead-item', function () {
            var pkg = $(this).data('package');
            if (!pkg) {
                return;
            }
            var label = pkg.label || pkg.title || '';
            $('.js-q-package-search').val(label);
            syncPackageSearchClearBtn();
            hideAllPackageMenus();
            updateSelectedPackageCard(pkg);
            $(this).closest('.js-q-package-menu').find('.q-itin-pkg-item').removeClass('is-active');
            $(this).addClass('is-active');
        });

        $(document).on('click', '#qClearSelectedPackage', function (e) {
            e.preventDefault();
            updateSelectedPackageCard(null);
            $('.q-itin-pkg-menu .q-itin-pkg-item').removeClass('is-active');
        });

        $(document).on('click', function (e) {
            if ($(e.target).closest('.q-itin-search-primary, .q-itin-pkg-search-wrap, .q-lead-combobox').length) {
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
                    $btn.prop('disabled', !selectedPackageForItinerary).html('<i class="fas fa-sign-in-alt mr-1"></i> Load Itinerary');
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
        syncTourCostOptUiFromMasters();
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
                    start_date: normalizeLegacyDateInput($('#q_tentative_date').val() || ''),
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
        var overnight = String(day.overnight || day.overnight_stay || '').trim();
        var meal = String(day.meal || day.meals || '').trim();
        if (!overnight) {
            overnight = inferDayOvernightValue({ description: description, overnight: overnight }, getItineraryDestinationLabel());
        }
        if (!meal) {
            meal = inferDayMealValue({ description: description, meal: meal });
        }
        $card.find('.q-day-overnight').val(overnight);
        $card.find('.q-day-meal').val(meal);
        syncDayMetaDisplay($card);
        updateDayCharCount($card);
        itineraryPreserveSeed = snapshotItinerary();
        scheduleSyncReturnAirfareInclusion();
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
        var $btns = $card.find('.q-day-ai-btn').prop('disabled', true);

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
        });
    }

    /* ------------------------------------------------------------------ */
    /* AI inclusions generator                                             */
    /* ------------------------------------------------------------------ */
    var pendingAiInclusionsHtml = '';

    function collectAiInclusionsContext() {
        var flights = [];
        try {
            flights = (typeof collectFlights === 'function' ? collectFlights() : []) || [];
        } catch (e1) {
            flights = [];
        }
        var hotels = [];
        try {
            hotels = (typeof collectHotels === 'function' ? collectHotels() : []) || [];
        } catch (e2) {
            hotels = [];
        }
        var itinerary = [];
        try {
            itinerary = (typeof snapshotItinerary === 'function' ? snapshotItinerary() : []) || [];
        } catch (e3) {
            itinerary = [];
        }

        return {
            guest: {
                guest_name: String($('[name=guest_name]').val() || '').trim(),
                adults: parseInt($('#q_adults').val(), 10) || 0,
                children: parseInt($('#q_children').val(), 10) || 0,
                mobile_no: String($('[name=mobile_no]').val() || '').trim(),
                email: String($('[name=email]').val() || '').trim()
            },
            tour: {
                destination: String($('[name=destination]').val() || '').trim(),
                nights: parseInt($('#q_nights').val(), 10) || 0,
                tentative_date: String($('#q_tentative_date').val() || '').trim(),
                package_name: String($('#q_header_text').val() || '').trim()
            },
            flights: flights.map(function (f) {
                return {
                    from: f.from || '',
                    to: f.to || '',
                    name: f.name || '',
                    fl_tr_no: f.fl_tr_no || '',
                    dep_date: f.dep_date || '',
                    dep_time: f.dep_time || '',
                    arr_date: f.arr_date || '',
                    arr_time: f.arr_time || '',
                    hand_baggage: f.hand_baggage || '',
                    checkin_baggage: f.checkin_baggage || ''
                };
            }),
            hotels: hotels.map(function (h) {
                return {
                    name: h.name || '',
                    city: h.city || '',
                    nights: h.nights || 0,
                    room_type: h.room_type || '',
                    meal_plan: h.meal_plan || '',
                    star_category: h.star_category || ''
                };
            }),
            itinerary: (itinerary || []).map(function (d, i) {
                return {
                    day: i + 1,
                    title: d.title || '',
                    description: d.description || '',
                    overnight: d.overnight || '',
                    meal: d.meal || ''
                };
            })
        };
    }

    function formatAiInclusionsContextText(ctx) {
        ctx = ctx || {};
        var lines = [];
        var g = ctx.guest || {};
        var t = ctx.tour || {};

        lines.push('=== GUESTS / PAX ===');
        lines.push('Guest: ' + (g.guest_name || '(not set)'));
        lines.push('Adults: ' + (g.adults || 0) + ' | Children: ' + (g.children || 0));
        if (g.mobile_no) {
            lines.push('Mobile: ' + g.mobile_no);
        }
        if (g.email) {
            lines.push('Email: ' + g.email);
        }

        lines.push('');
        lines.push('=== TOUR / PACKAGE ===');
        lines.push('Destination: ' + (t.destination || '(not set)'));
        lines.push('Nights: ' + (t.nights || 0) + ' (Days: ' + ((parseInt(t.nights, 10) || 0) + 1) + ')');
        if (t.tentative_date) {
            lines.push('Travel date: ' + t.tentative_date);
        }
        if (t.package_name) {
            lines.push('Package / header: ' + t.package_name);
        }

        lines.push('');
        lines.push('=== FLIGHTS / TRAIN ===');
        if (!(ctx.flights || []).length) {
            lines.push('(none entered)');
        } else {
            (ctx.flights || []).forEach(function (f, i) {
                lines.push(
                    (i + 1) + '. ' + (f.from || '?') + ' → ' + (f.to || '?') +
                    (f.name || f.fl_tr_no ? (' | ' + [f.name, f.fl_tr_no].filter(Boolean).join(' ')) : '') +
                    (f.dep_date ? (' | Dep ' + f.dep_date + (f.dep_time ? (' ' + f.dep_time) : '')) : '') +
                    (f.hand_baggage || f.checkin_baggage
                        ? (' | Bags: ' + [f.hand_baggage ? ('cabin ' + f.hand_baggage) : '', f.checkin_baggage ? ('check-in ' + f.checkin_baggage) : ''].filter(Boolean).join(', '))
                        : '')
                );
            });
        }

        lines.push('');
        lines.push('=== HOTELS ===');
        if (!(ctx.hotels || []).length) {
            lines.push('(none entered)');
        } else {
            (ctx.hotels || []).forEach(function (h, i) {
                lines.push(
                    (i + 1) + '. ' + (h.name || '(hotel)') +
                    (h.city ? (' — ' + h.city) : '') +
                    (h.nights ? (' | ' + h.nights + ' night(s)') : '') +
                    (h.room_type ? (' | ' + h.room_type) : '') +
                    (h.meal_plan ? (' | Meal: ' + h.meal_plan) : '') +
                    (h.star_category ? (' | ' + h.star_category) : '')
                );
            });
        }

        lines.push('');
        lines.push('=== ITINERARY ===');
        if (!(ctx.itinerary || []).length) {
            lines.push('(none entered)');
        } else {
            (ctx.itinerary || []).forEach(function (d) {
                var desc = String(d.description || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                if (desc.length > 220) {
                    desc = desc.slice(0, 220).replace(/\s+\S*$/, '') + '…';
                }
                lines.push('Day ' + (d.day || '') + ': ' + (d.title || '(untitled)'));
                if (desc) {
                    lines.push('  ' + desc);
                }
                if (d.overnight) {
                    lines.push('  Overnight: ' + d.overnight);
                }
                if (d.meal) {
                    lines.push('  Meals: ' + d.meal);
                }
            });
        }

        return lines.join('\n');
    }

    function refreshAiInclusionsChips(ctx) {
        ctx = ctx || collectAiInclusionsContext();
        var chips = [];
        var dest = (ctx.tour && ctx.tour.destination) || '';
        var nights = (ctx.tour && ctx.tour.nights) || 0;
        var adults = (ctx.guest && ctx.guest.adults) || 0;
        var children = (ctx.guest && ctx.guest.children) || 0;
        var flights = (ctx.flights || []).length;
        var hotels = (ctx.hotels || []).length;
        var days = (ctx.itinerary || []).length;

        if (dest) {
            chips.push('<span class="q-ai-chip"><i class="fas fa-map-marker-alt"></i>' + esc(dest) + '</span>');
        } else {
            chips.push('<span class="q-ai-chip is-warn"><i class="fas fa-exclamation-circle"></i>No destination</span>');
        }
        chips.push('<span class="q-ai-chip"><i class="far fa-calendar-alt"></i>' + nights + 'N / ' + (nights + 1) + 'D</span>');
        chips.push('<span class="q-ai-chip"><i class="fas fa-users"></i>' + adults + 'A' + (children ? (', ' + children + 'C') : '') + '</span>');
        chips.push('<span class="q-ai-chip' + (flights ? '' : ' is-warn') + '"><i class="fas fa-plane"></i>' + flights + ' flight' + (flights === 1 ? '' : 's') + '</span>');
        chips.push('<span class="q-ai-chip' + (hotels ? '' : ' is-warn') + '"><i class="fas fa-hotel"></i>' + hotels + ' hotel' + (hotels === 1 ? '' : 's') + '</span>');
        chips.push('<span class="q-ai-chip' + (days ? '' : ' is-warn') + '"><i class="fas fa-route"></i>' + days + ' day' + (days === 1 ? '' : 's') + '</span>');
        $('#qAiInclChips').html(chips.join(''));
    }

    function aiInclusionsHtmlToEditableText(html) {
        var $tmp = $('<div>').html(html || '');
        var lines = [];
        var $secs = $tmp.find('.q-ai-incl-sec');
        if ($secs.length) {
            $secs.each(function () {
                var title = String($(this).find('.q-ai-incl-sec-title').text() || '').replace(/\s+/g, ' ').trim();
                if (title) {
                    lines.push(title.toUpperCase());
                }
                $(this).find('ul li').each(function () {
                    var t = String($(this).text() || '').replace(/\s+/g, ' ').trim();
                    if (t) {
                        lines.push('• ' + t);
                    }
                });
                lines.push('');
            });
            while (lines.length && lines[lines.length - 1] === '') {
                lines.pop();
            }
            return lines.join('\n');
        }
        $tmp.find('li').each(function () {
            var t = String($(this).text() || '').replace(/\s+/g, ' ').trim();
            if (t) {
                lines.push('• ' + t);
            }
        });
        if (!lines.length) {
            $tmp.find('p').each(function () {
                var t = String($(this).text() || '').replace(/\s+/g, ' ').trim();
                if (t) {
                    lines.push('• ' + t);
                }
            });
        }
        if (!lines.length) {
            var plain = String($tmp.text() || '').replace(/\s+/g, ' ').trim();
            if (plain) {
                lines.push('• ' + plain);
            }
        }
        return lines.join('\n');
    }

    function aiInclusionsSectionKeyFromTitle(title) {
        title = String(title || '').toLowerCase();
        if (/air\s*fare|flight/.test(title)) return 'airfare';
        if (/accommodation|hotel|stay/.test(title)) return 'accommodation';
        if (/meal|food|breakfast|lunch|dinner/.test(title)) return 'meals';
        if (/sight|activit|tour|excursion/.test(title)) return 'sightseeing';
        if (/transfer|transport/.test(title)) return 'transfers';
        return 'other';
    }

    function aiInclusionsEditableTextToHtml(text) {
        var meta = {
            airfare: { label: 'Airfare', icon: 'fas fa-plane' },
            accommodation: { label: 'Accommodation', icon: 'fas fa-hotel' },
            meals: { label: 'Meals', icon: 'fas fa-utensils' },
            sightseeing: { label: 'Sightseeing & Activities', icon: 'fas fa-ticket-alt' },
            transfers: { label: 'Transfers & Transportation', icon: 'fas fa-shuttle-van' },
            other: { label: 'Other Inclusions', icon: 'fas fa-clipboard-list' }
        };
        var order = ['airfare', 'accommodation', 'meals', 'sightseeing', 'transfers', 'other'];
        var sections = {};
        order.forEach(function (k) { sections[k] = []; });
        var current = 'other';
        var lines = String(text || '').split(/\r?\n/);
        var hasHeadings = false;

        lines.forEach(function (raw) {
            var line = String(raw || '').trim();
            if (!line) {
                return;
            }
            var heading = line.replace(/^[\s#]+/, '');
            var isHeading = !/^[\s•\-\*]/.test(line)
                && (
                    /^(airfare|accommodation|meals|sightseeing|transfers|other)/i.test(heading)
                    || (/^[A-Z0-9 &]+$/.test(heading) && heading.length < 48)
                );
            if (isHeading && !/^•/.test(line)) {
                hasHeadings = true;
                current = aiInclusionsSectionKeyFromTitle(heading);
                return;
            }
            line = line.replace(/^[\s•\-\*\d\.\)\(]+/, '').trim();
            if (line) {
                sections[current].push(line);
            }
        });

        if (!hasHeadings) {
            // Flat list → Other
            sections = { airfare: [], accommodation: [], meals: [], sightseeing: [], transfers: [], other: [] };
            lines.forEach(function (raw) {
                var line = String(raw || '').replace(/^[\s•\-\*\d\.\)\(]+/, '').trim();
                if (line) {
                    sections.other.push(line);
                }
            });
        }

        var html = '';
        order.forEach(function (key) {
            var items = sections[key] || [];
            if (!items.length) {
                return;
            }
            var info = meta[key];
            html += '<div class="q-ai-incl-sec" data-sec="' + key + '">';
            html += '<p class="q-ai-incl-sec-title"><i class="' + info.icon + '" aria-hidden="true"></i> <strong>' + esc(info.label) + '</strong></p>';
            html += '<ul>';
            items.forEach(function (item) {
                html += '<li>' + esc(item) + '</li>';
            });
            html += '</ul></div>';
        });
        if (!html) {
            return '';
        }
        return '<div data-q-ai-inclusions="1" class="q-ai-incl-doc">' + html + '</div>';
    }

    function resetAiInclusionsResultUi() {
        pendingAiInclusionsHtml = '';
        $('#qAiInclResult').val('');
        $('#qAiInclResultWrap').addClass('d-none');
        $('#qAiInclRegenerate, #qAiInclUse').addClass('d-none');
        $('#qAiInclSourceBadge').text('');
        $('#qAiInclError, #qAiInclSuccess').addClass('d-none').text('');
    }

    function openAiInclusionsModal() {
        var ctx = collectAiInclusionsContext();
        refreshAiInclusionsChips(ctx);
        $('#qAiInclContext').val(formatAiInclusionsContextText(ctx));
        $('#qAiInclNotes').val('');
        resetAiInclusionsResultUi();
        $('#qAiInclGenerate').prop('disabled', false).html('<i class="fas fa-bolt mr-1"></i> Generate Inclusions');
        $('#qAiInclusionsModal').modal('show');
        window.setTimeout(function () {
            $('#qAiInclContext').trigger('focus');
        }, 350);
    }

    function requestAiInclusionsGenerate() {
        var ctx = collectAiInclusionsContext();
        var contextText = String($('#qAiInclContext').val() || '').trim();
        var notes = String($('#qAiInclNotes').val() || '').trim();

        if (!contextText) {
            $('#qAiInclError').removeClass('d-none').text('Booking context is empty. Add guest/tour/hotel/flight/itinerary details first.');
            return;
        }

        $('#qAiInclError, #qAiInclSuccess').addClass('d-none').text('');
        var $btn = $('#qAiInclGenerate').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Generating...');
        $('#qAiInclRegenerate').prop('disabled', true);

        $.ajax({
            url: absUrl('crm/ajax/ai_suggest_inclusions.php'),
            type: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: JSON.stringify({
                context: ctx,
                context_text: contextText,
                notes: notes
            })
        }).done(function (res) {
            if (!(res && res.success && res.inclusions_html)) {
                $('#qAiInclError').removeClass('d-none').text((res && res.message) ? res.message : 'Could not generate inclusions.');
                return;
            }
            pendingAiInclusionsHtml = res.inclusions_html;
            $('#qAiInclResult').val(aiInclusionsHtmlToEditableText(res.inclusions_html));
            $('#qAiInclResultWrap').removeClass('d-none');
            $('#qAiInclRegenerate, #qAiInclUse').removeClass('d-none');
            var src = res.source === 'ai' ? 'Gemini AI' : 'Booking facts';
            if (res.instant_mode) {
                src += ' (AI off)';
            }
            $('#qAiInclSourceBadge').text(src);
            var okMsg = res.message || 'Inclusions generated. Review or edit, then click Use / Insert.';
            $('#qAiInclSuccess').removeClass('d-none').text(okMsg);
        }).fail(function (xhr) {
            var msg = 'Could not generate inclusions.';
            try {
                var j = JSON.parse(xhr.responseText);
                if (j && j.message) {
                    msg = j.message;
                }
            } catch (e) { /* ignore */ }
            $('#qAiInclError').removeClass('d-none').text(msg);
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="fas fa-bolt mr-1"></i> Generate Inclusions');
            $('#qAiInclRegenerate').prop('disabled', false);
        });
    }

    function applyAiInclusionsToEditor() {
        var edited = String($('#qAiInclResult').val() || '').trim();
        var html = edited ? aiInclusionsEditableTextToHtml(edited) : pendingAiInclusionsHtml;
        if (!html) {
            $('#qAiInclError').removeClass('d-none').text('Nothing to insert. Generate inclusions first.');
            return;
        }

        var existing = (readSummernoteHtml($('#qed_inclusion')) || '').replace(/<[^>]*>/g, '').trim();
        if (existing && !window.confirm('Replace the current Inclusions content with the AI list?')) {
            return;
        }

        setRichEditorValue('qed_inclusion', html);
        // Expand inclusion accordion so the user sees the result.
        var $body = $('#qbody_inclusion');
        if ($body.length && !$body.is(':visible')) {
            $body.closest('.q-terms-item').find('.q-terms-item-head').trigger('click');
        }
        $('#qAiInclusionsModal').modal('hide');
        $('#qAlert').html('<div class="alert alert-success"><i class="fas fa-check-circle mr-1"></i> AI inclusions inserted into the Inclusions field.</div>');
        try {
            saveFormDraftToStorage();
        } catch (e) { /* ignore */ }
    }

    function openFullItineraryModal() {
        var days = snapshotItinerary();
        var baseDate = $('#q_tentative_date').val();
        var dest = getItineraryDestinationLabel();
        var $list = $('#qFullItineraryList').empty();
        var currentIdx = activeItineraryDayIndex;

        $('#qFullItineraryModalSub').text(
            (dest ? dest + ' · ' : '') +
            (days.length ? (days.length + (days.length === 1 ? ' day' : ' days')) : 'No days yet') +
            ' — click a day to edit'
        );

        if (!days.length) {
            $list.append('<div class="q-full-itin-empty">No itinerary days yet. Set nights or load a package first.</div>');
            $('#qFullItineraryModal').modal('show');
            return;
        }

        days.forEach(function (day, i) {
            day = normalizeItineraryDay(day || {});
            var heading = fmtItineraryDayHeading(baseDate, i, i + 1);
            var dateOnly = heading.indexOf('–') >= 0 ? heading.split('–')[0].trim() : heading;
            var title = (day.title || '').trim() || ('Day ' + (i + 1));
            var desc = stripHtmlText(day.description || '');
            if (desc.length > 180) {
                desc = desc.slice(0, 180).replace(/\s+\S*$/, '') + '…';
            }
            var overnight = (day.overnight || '').trim() || inferDayOvernightValue(day, dest);
            var meal = (day.meal || '').trim() || inferDayMealValue(day);
            var dayImage = (day.image || '').trim();
            var metaHtml = '';
            if (overnight) {
                metaHtml += '<span><i class="fas fa-bed"></i> ' + esc(overnight) + '</span>';
            }
            if (meal) {
                metaHtml += '<span><i class="fas fa-utensils"></i> ' + esc(meal) + '</span>';
            }
            var $item = $(
                '<button type="button" class="q-full-itin-item' + (i === currentIdx ? ' is-current' : '') + (dayImage ? ' has-photo' : '') + '" data-day-index="' + i + '">' +
                '<div class="q-full-itin-item-body">' +
                '<div class="q-full-itin-item-top">' +
                '<span class="q-full-itin-item-day">Day ' + (i + 1) + '</span>' +
                '<span class="q-full-itin-item-date"></span>' +
                '</div>' +
                '<div class="q-full-itin-item-title"></div>' +
                (desc ? '<p class="q-full-itin-item-desc"></p>' : '') +
                (metaHtml ? '<div class="q-full-itin-item-meta">' + metaHtml + '</div>' : '') +
                '</div>' +
                (dayImage
                    ? '<div class="q-full-itin-item-photo"><img alt=""></div>'
                    : '') +
                '</button>'
            );
            $item.find('.q-full-itin-item-date').text(dateOnly);
            $item.find('.q-full-itin-item-title').text(title);
            if (desc) {
                $item.find('.q-full-itin-item-desc').text(desc);
            }
            if (dayImage) {
                $item.find('.q-full-itin-item-photo img').attr({
                    src: absUrl(dayImage),
                    alt: title
                });
            }
            $list.append($item);
        });

        $('#qFullItineraryModal').modal('show');
    }

    function initAISuggestDay() {
        $(document).on('click', '.q-day-ai-btn', function (e) {
            e.preventDefault();
            openDayAiModal($(this).closest('.q-day-card'));
        });

        $(document).on('click', '.q-day-svc-option', function (e) {
            e.preventDefault();
            var $opt = $(this);
            var $dd = $opt.closest('.q-day-svc-dd');
            var $card = $opt.closest('.q-day-card');
            var kind = String($dd.attr('data-svc') || '');
            var val = normalizeDayServiceMode($opt.attr('data-svc-value'));
            if (!kind || !$card.length) {
                return;
            }
            var $input = $card.find('.q-day-' + kind);
            // Toggle off if the same option is clicked again.
            if (normalizeDayServiceMode($input.val()) === val) {
                val = '';
            }
            $input.val(val);
            syncDayServiceControl($card, kind);
            try {
                saveFormDraftToStorage();
            } catch (err) { /* ignore */ }
        });

        $(document).on('click', '.q-day-prev', function (e) {
            e.preventDefault();
            var idx = parseInt($(this).closest('.q-day-card').attr('data-day-index'), 10) || 0;
            showItineraryDay(idx - 1);
        });

        $(document).on('click', '.q-day-next', function (e) {
            e.preventDefault();
            var idx = parseInt($(this).closest('.q-day-card').attr('data-day-index'), 10) || 0;
            showItineraryDay(idx + 1);
        });

        $(document).on('click', '.q-day-jump', function (e) {
            e.preventDefault();
            var idx = parseInt($(this).attr('data-day-index'), 10);
            showItineraryDay(idx);
        });

        $(document).on('click', '.q-view-full-itinerary, #qViewFullItineraryBtn', function (e) {
            e.preventDefault();
            openFullItineraryModal();
        });

        $(document).on('click', '.q-full-itin-item', function (e) {
            e.preventDefault();
            var idx = parseInt($(this).attr('data-day-index'), 10);
            $('#qFullItineraryModal').modal('hide');
            expandWizardSection(4);
            showItineraryDay(idx);
            window.setTimeout(function () {
                var $card = $('#qItineraryDays .q-day-card.is-active');
                if ($card.length && $card[0].scrollIntoView) {
                    $card[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }, 180);
        });

        $(document).on('click', '.q-day-meta-edit', function (e) {
            e.preventDefault();
            beginDayMetaEdit($(this).closest('.q-day-meta-card'));
        });

        $(document).on('focus', '.q-day-meta-value', function () {
            var $el = $(this);
            $el.addClass('is-editing');
            var text = ($el.text() || '').trim();
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

        $(document).on('blur', '.q-day-meta-value', function () {
            commitDayMetaValue($(this));
        });

        $(document).on('keydown', '.q-day-meta-value', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).blur();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                var $el = $(this);
                var $card = $el.closest('.q-day-card');
                var kind = $el.closest('.q-day-meta-card').attr('data-meta');
                var original = kind === 'meal'
                    ? ($card.find('.q-day-meal').val() || '')
                    : ($card.find('.q-day-overnight').val() || '');
                $el.removeClass('is-editing').text(original.trim() || '—').toggleClass('is-empty', !original.trim());
                $el.blur();
            }
        });

        $(document).on('click', '.q-day-meta-remove', function (e) {
            e.preventDefault();
            var $card = $(this).closest('.q-day-card');
            var $meta = $(this).closest('.q-day-meta-card');
            var kind = $meta.attr('data-meta');
            if (kind === 'meal') {
                $card.find('.q-day-meal').val('');
            } else {
                $card.find('.q-day-overnight').val('');
            }
            $meta.find('.q-day-meta-value').removeClass('is-editing');
            syncDayMetaDisplay($card);
            itineraryPreserveSeed = snapshotItinerary();
        });

        $(document).on('change input', '[name=destination]', function () {
            var dest = getItineraryDestinationLabel();
            $('#qItineraryDays .q-day-head-sub').text(dest || 'Destination');
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

        $(document).on('click', '#qAiInclusionsInlineBtn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openAiInclusionsModal();
        });

        $('#qAiInclGenerate, #qAiInclRegenerate').on('click', function () {
            requestAiInclusionsGenerate();
        });

        $('#qAiInclUse').on('click', function () {
            applyAiInclusionsToEditor();
        });

        $('#qAiInclusionsModal').on('hidden.bs.modal', function () {
            $('#qAiInclGenerate').prop('disabled', false).html('<i class="fas fa-bolt mr-1"></i> Generate Inclusions');
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
            $card.find('.q-day-image-count').text('1 Image');
        } else {
            $img.attr('src', '').hide();
            $empty.show();
            $wrap.removeClass('has-image');
            $card.find('.q-day-image-count').text('0 Image');
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
    var qExtraCostTargetCatId = '';

    function openExtraCostModal(catId) {
        qExtraCostTargetCatId = String(catId || '');
        if (!qExtraCostTargetCatId) {
            return;
        }
        $('#qExtraCostError').addClass('d-none').text('');
        $('#qExtraCostName').val('');
        $('#qExtraCostAmount').val('');
        $('#qExtraCostModal').modal('show');
    }

    function saveExtraCostFromModal() {
        var id = String(qExtraCostTargetCatId || '');
        var name = $.trim($('#qExtraCostName').val() || '');
        var amountRaw = $.trim($('#qExtraCostAmount').val() || '');
        var amount = parseFloat(amountRaw);
        var $err = $('#qExtraCostError');
        if (!id) {
            $err.removeClass('d-none').text('No pricing option selected.');
            return;
        }
        if (!name) {
            $err.removeClass('d-none').text('Please enter a name.');
            $('#qExtraCostName').trigger('focus');
            return;
        }
        if (amountRaw === '' || isNaN(amount) || amount < 0) {
            $err.removeClass('d-none').text('Please enter a valid amount.');
            $('#qExtraCostAmount').trigger('focus');
            return;
        }
        snapshotPricingSheets();
        if (!qPricingOptionsState[id]) {
            qPricingOptionsState[id] = defaultPricingSheetState();
        }
        qPricingOptionsState[id].custom = (qPricingOptionsState[id].custom || []).filter(customCostRowHasData);
        qPricingOptionsState[id].custom.push({
            label: formatExtraCostDisplayName(name),
            amount: Math.round(amount * 100) / 100
        });
        $('#qExtraCostModal').modal('hide');
        // Skip DOM snapshot — new row exists only in state until re-render.
        renderPricingSheets({ skipSnapshot: true });
        saveFormDraftToStorage();
    }

    function formatExtraCostDisplayName(name) {
        name = String(name || '').trim().replace(/\s+/g, ' ');
        if (!name) {
            return '';
        }
        return name.replace(/\b([a-z])/g, function (m, c) {
            return c.toUpperCase();
        });
    }

    function customCostRowHtml(data) {
        data = data || {};
        if (!customCostRowHasData(data)) {
            return '<div class="q-custom-cost q-custom-cost-empty q-pricing-amount-cell" aria-hidden="true"></div>';
        }
        var label = String(data.label || '').trim();
        var amount = data.amount != null ? data.amount : '';
        return '' +
            '<div class="q-pricing-amount-cell has-supplier has-ico has-remove q-custom-cost">' +
            '<span class="q-pricing-row-ico" aria-hidden="true"><i class="fas fa-tag"></i></span>' +
            '<input type="text" class="q-pricing-supplier-name cc-label" placeholder="Cost name" value="' + esc(label) + '" title="' + esc(label) + '" aria-label="Extra cost name">' +
            '<div class="q-pricing-amount-input-wrap">' +
            '<span class="q-pricing-inr" aria-hidden="true">₹</span>' +
            '<input type="number" step="0.01" class="form-control form-control-sm cost-input q-cost cc-amount" value="' + esc(amount) + '" placeholder="0" aria-label="Extra cost amount">' +
            '</div>' +
            '<div class="q-pricing-amount-action">' +
            '<button type="button" class="btn q-pricing-supplier-remove q-custom-cost-remove q-remove" data-remove=".q-custom-cost" title="Remove extra cost" aria-label="Remove">' +
            '<i class="fas fa-times" aria-hidden="true"></i></button>' +
            '</div>' +
            '</div>';
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
            fixed_parts: {},
            custom: [],
            user_edited: { flight_train: 0, hotel: 0, land: 0 },
            profit_percent: '',
            profit_amount: '',
            price_per_adult: '',
            price_per_adult_edited: 0
        };
    }

    function collectSheetStateFromDom($sheet) {
        var fixed = {};
        var fixedParts = {};
        var editedFlags = {};
        $sheet.find('.q-cost').each(function () {
            var $c = $(this);
            if ($c.hasClass('cc-amount')) return;
            var key = String($c.data('key') || '');
            if (!key) return;
            if ($c.attr('type') === 'hidden') {
                if (fixed[key] == null || fixed[key] === '') {
                    fixed[key] = $c.val();
                }
                if ($c.attr('data-user-edited') === '1') {
                    editedFlags[key] = 1;
                }
                return;
            }
            if (!fixedParts[key]) {
                fixedParts[key] = [];
            }
            var partIdx = parseInt($c.attr('data-part-index'), 10);
            if (isNaN(partIdx) || partIdx < 0) {
                partIdx = fixedParts[key].length;
            }
            fixedParts[key][partIdx] = $c.val();
            if ($c.attr('data-user-edited') === '1') {
                editedFlags[key] = 1;
            }
        });
        Object.keys(fixedParts).forEach(function (key) {
            var sum = 0;
            var hasAny = false;
            (fixedParts[key] || []).forEach(function (val) {
                var n = parseFloat(val);
                if (!isNaN(n)) {
                    sum += n;
                    hasAny = true;
                }
            });
            fixed[key] = hasAny ? String(Math.round(sum * 100) / 100) : '';
        });
        var custom = [];
        $sheet.find('.q-custom-cost').each(function () {
            if ($(this).hasClass('q-custom-cost-empty')) {
                return;
            }
            custom.push({
                label: $(this).find('.cc-label').val(),
                amount: $(this).find('.cc-amount').val()
            });
        });
        return {
            fixed: fixed,
            fixed_parts: fixedParts,
            custom: custom,
            user_edited: {
                flight_train: editedFlags.flight_train ? 1 : 0,
                hotel: editedFlags.hotel ? 1 : 0,
                land: editedFlags.land ? 1 : 0
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
            { key: 'land', label: 'Land', icon: 'fas fa-bus' },
            { key: 'hotel', label: 'Hotel', icon: 'fas fa-bed' },
            { key: 'transport', label: 'Transport', icon: 'fas fa-mountain' },
            { key: 'visa', label: 'Visa', icon: 'fas fa-passport' },
            { key: 'travel_insurance', label: 'Insurance', icon: 'fas fa-shield-alt' }
        ];
    }

    function pricingCostHasAmount(val) {
        var n = parseFloat(val);
        return !isNaN(n) && n > 0;
    }

    function getItineraryLandTotal() {
        try {
            var meta = typeof collectItineraryMeta === 'function' ? collectItineraryMeta() : {};
            var entries = typeof normalizeItinerarySupplierEntries === 'function'
                ? normalizeItinerarySupplierEntries(meta)
                : [];
            var total = 0;
            (entries || []).forEach(function (item) {
                var rate = parseFloat(item && item.rate);
                if (!isNaN(rate) && rate > 0) {
                    total += rate;
                }
            });
            return total;
        } catch (e) {
            return 0;
        }
    }

    function customCostRowHasData(row) {
        if (!row) {
            return false;
        }
        var label = String(row.label || '').trim();
        return !!label || pricingCostHasAmount(row.amount);
    }

    /** Only show pricing rows that already have rates from earlier sections (or saved amounts). */
    function getVisiblePricingFixedKeys(cats, statesByCatId) {
        cats = cats || [];
        statesByCatId = statesByCatId || {};
        var flightTotal = sumNumericFields('#qFlightRows .f-fare');
        var hasFlightRate = flightTotal > 0;
        var landFromItin = getItineraryLandTotal() > 0;
        var anyHotelRate = cats.some(function (cat) {
            return hotelTotalForCategory(cat) > 0;
        });
        var saved = {};
        pricingFixedCostKeys().forEach(function (row) {
            saved[row.key] = false;
        });
        cats.forEach(function (cat) {
            var st = statesByCatId[cat.id] || qPricingOptionsState[cat.id] || defaultPricingSheetState();
            var fixed = st.fixed || {};
            Object.keys(saved).forEach(function (key) {
                if (pricingCostHasAmount(fixed[key])) {
                    saved[key] = true;
                }
            });
        });
        Object.keys(qPricingOptionsState || {}).forEach(function (id) {
            var fixed = (qPricingOptionsState[id] || {}).fixed || {};
            Object.keys(saved).forEach(function (key) {
                if (pricingCostHasAmount(fixed[key])) {
                    saved[key] = true;
                }
            });
        });

        return pricingFixedCostKeys().filter(function (row) {
            if (row.key === 'flight_train') {
                return saved.flight_train || hasFlightRate;
            }
            if (row.key === 'hotel') {
                return saved.hotel || anyHotelRate;
            }
            if (row.key === 'land') {
                return saved.land || landFromItin;
            }
            return !!saved[row.key];
        });
    }

    function getVisibleCustomCosts(state) {
        return ((state && state.custom) || []).filter(customCostRowHasData);
    }

    /** Extra costs shown in the matrix (filled rows only; add via modal). */
    function getRenderableCustomCosts(state) {
        return getVisibleCustomCosts(state);
    }

    function getMatrixCustomLabels(cats, maxCustom) {
        maxCustom = Math.max(0, parseInt(maxCustom, 10) || 0);
        cats = cats || [];
        var labels = [];
        var activeCustoms = getVisibleCustomCosts(qPricingOptionsState[qActiveHotelCategoryId] || {});
        for (var i = 0; i < maxCustom; i++) {
            var label = '';
            if (activeCustoms[i] && String(activeCustoms[i].label || '').trim()) {
                label = String(activeCustoms[i].label).trim();
            }
            if (!label) {
                cats.some(function (cat) {
                    var row = getVisibleCustomCosts(qPricingOptionsState[cat.id] || {})[i];
                    if (row && String(row.label || '').trim()) {
                        label = String(row.label).trim();
                        return true;
                    }
                    return false;
                });
            }
            labels.push(label || 'Extra Cost');
        }
        return labels;
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

    function uniquePricingSupplierNames(names) {
        var seen = {};
        var out = [];
        (names || []).forEach(function (name) {
            name = String(name || '').trim();
            if (!name) {
                return;
            }
            var key = name.toLowerCase();
            if (seen[key]) {
                return;
            }
            seen[key] = 1;
            out.push(name);
        });
        return out;
    }

    function readPricingSupplierNameFromSelect($sel) {
        if (!$sel || !$sel.length) {
            return '';
        }
        var supplierVal = String($sel.val() || '').trim();
        if (!supplierVal || supplierVal === '__create__') {
            return '';
        }
        var supplierName = String($sel.find('option:selected').attr('data-name') || $sel.find('option:selected').text() || '').trim();
        if (!supplierName || supplierName === 'Select' || supplierName === 'Select supplier' || supplierName.indexOf('Create new') === 0) {
            return '';
        }
        return supplierName;
    }

    function pricingSupplierRateEntriesForKey(key, cat) {
        var entries = [];
        if (key === 'flight_train') {
            collectFlights().forEach(function (f, idx) {
                var name = String((f && f.supplier) || '').trim();
                var rateRaw = f && f.fare;
                var rateNum = parseFloat(rateRaw);
                var hasRate = !isNaN(rateNum) && rateNum > 0;
                if (!hasRate && !name) {
                    return;
                }
                entries.push({
                    name: name,
                    rate: hasRate ? rateNum.toFixed(2) : '',
                    sourceIndex: idx
                });
            });
            return entries;
        }
        if (key === 'hotel') {
            var hotels = (cat && Array.isArray(cat.hotels)) ? cat.hotels : [];
            hotels.forEach(function (h, idx) {
                var name = String((h && (h.hotel_name || h.name || h.supplier || h.supplier_name)) || '').trim();
                var rateNum = parseFloat(h && h.rate);
                var hasRate = !isNaN(rateNum) && rateNum > 0;
                if (!hasRate && !name) {
                    return;
                }
                entries.push({
                    name: name,
                    rate: hasRate ? rateNum.toFixed(2) : '',
                    sourceIndex: idx
                });
            });
            return entries;
        }
        if (key === 'land') {
            $('#qItinerarySupplierRows .q-itin-supplier-row').each(function (idx) {
                var $row = $(this);
                var name = readPricingSupplierNameFromSelect($row.find('.q-itin-supplier'));
                var rateRaw = $.trim($row.find('.q-itin-rate').val() || '');
                var rateNum = parseFloat(rateRaw);
                var hasRate = !isNaN(rateNum) && rateNum > 0;
                if (!hasRate && !name) {
                    return;
                }
                entries.push({
                    name: name,
                    rate: hasRate ? String(Math.round(rateNum)) : '',
                    sourceIndex: idx
                });
            });
            if (!entries.length && $('#q_itinerary_supplier').length) {
                var legacyName = readPricingSupplierNameFromSelect($('#q_itinerary_supplier'));
                if (legacyName) {
                    entries.push({ name: legacyName, rate: '', sourceIndex: 0, legacy: true });
                }
            }
            return entries;
        }
        return entries;
    }

    function pricingSupplierNamesForKey(key, cat) {
        return uniquePricingSupplierNames(pricingSupplierRateEntriesForKey(key, cat).map(function (e) {
            return e.name;
        }));
    }

    function getPricingKeySlotCounts(cats, visibleKeys) {
        var counts = {};
        (visibleKeys || []).forEach(function (row) {
            var max = 1;
            (cats || []).forEach(function (cat) {
                var n = pricingSupplierRateEntriesForKey(row.key, cat).length;
                if (n > max) {
                    max = n;
                }
            });
            counts[row.key] = max;
        });
        return counts;
    }

    function pricingAmountCellHtml(opts) {
        opts = opts || {};
        var key = opts.key || '';
        var value = opts.value != null ? opts.value : '';
        var edited = opts.edited ? '1' : '0';
        var synced = opts.synced ? ' q-cost-synced' : '';
        var supplierName = String(opts.supplierName || '').trim();
        var icon = String(opts.icon || '').trim();
        var partIndex = opts.partIndex != null ? String(opts.partIndex) : '0';
        var sourceIndex = opts.sourceIndex;
        var hasSource = sourceIndex != null && sourceIndex !== '' && !isNaN(parseInt(sourceIndex, 10));
        var hasSupplier = !!supplierName;
        var removable = !!(opts.removable && hasSource);
        var html = '<div class="q-pricing-amount-cell' +
            (hasSupplier ? ' has-supplier' : '') +
            (icon ? ' has-ico' : '') +
            (removable ? ' has-remove' : '') +
            '" data-cost-key="' + esc(key) + '" data-part-index="' + esc(partIndex) + '"' +
            (removable ? ' data-source-index="' + esc(String(sourceIndex)) + '"' : '') + '>';
        if (icon) {
            html += '<span class="q-pricing-row-ico" aria-hidden="true"><i class="' + esc(icon) + '"></i></span>';
        }
        if (hasSupplier) {
            html += '<span class="q-pricing-supplier-name" title="' + esc(supplierName) + '">' + esc(supplierName) + '</span>';
        }
        html += '<div class="q-pricing-amount-input-wrap">';
        html += '<span class="q-pricing-inr" aria-hidden="true">₹</span>';
        html += '<input type="number" step="0.01" class="form-control form-control-sm cost-input q-cost' + synced + '" data-key="' + esc(key) + '" data-part-index="' + esc(partIndex) + '" value="' + esc(value) + '" data-user-edited="' + edited + '" placeholder="0">';
        html += '</div>';
        html += '<div class="q-pricing-amount-action">';
        if (removable) {
            html += '<button type="button" class="btn q-pricing-supplier-remove" title="Remove supplier and rate" aria-label="Remove supplier and rate">' +
                '<i class="fas fa-times" aria-hidden="true"></i></button>';
        }
        html += '</div>';
        html += '</div>';
        return html;
    }

    function clearPricingKeySyncedEdits(key, catId) {
        function clearState(st) {
            if (!st) {
                return;
            }
            if (!st.user_edited) {
                st.user_edited = {};
            }
            st.user_edited[key] = 0;
            if (st.fixed_parts && Object.prototype.hasOwnProperty.call(st.fixed_parts, key)) {
                delete st.fixed_parts[key];
            }
            if (st.fixed) {
                st.fixed[key] = '';
            }
        }
        if (key === 'hotel' && catId && qPricingOptionsState[catId]) {
            clearState(qPricingOptionsState[catId]);
            return;
        }
        Object.keys(qPricingOptionsState || {}).forEach(function (id) {
            clearState(qPricingOptionsState[id]);
        });
    }

    function clearPricingSupplierSelect($sel) {
        if (!$sel || !$sel.length) {
            return;
        }
        $sel.val('').trigger('change');
        $sel.data('prevSupplierVal', '');
    }

    /** Remove supplier + rate from the source section that feeds a Pricing row. */
    function removePricingSupplierSource(key, sourceIndex, catId) {
        sourceIndex = parseInt(sourceIndex, 10);
        if (isNaN(sourceIndex) || sourceIndex < 0) {
            return false;
        }
        snapshotPricingSheets();

        if (key === 'flight_train') {
            var $fRow = $('#qFlightRows .q-flight-row').eq(sourceIndex);
            if (!$fRow.length) {
                return false;
            }
            $fRow.find('.f-fare').val('');
            clearPricingSupplierSelect($fRow.find('.f-supplier'));
            clearPricingKeySyncedEdits(key);
            return true;
        }

        if (key === 'hotel') {
            var catSel = String(catId || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
            var $panel = catSel
                ? getHotelCategoryPanels().filter('[data-cat-id="' + catSel + '"]').first()
                : $();
            if (!$panel.length) {
                $panel = getHotelCategoryPanels().filter('.is-active').first();
            }
            if (!$panel.length) {
                $panel = getHotelCategoryPanels().first();
            }
            var $hRows = $panel.find('.q-hotel-rows .q-hotel-row');
            if (!$hRows.length) {
                $hRows = $panel.find('.q-hotel-row');
            }
            var $hRow = $hRows.eq(sourceIndex);
            if (!$hRow.length) {
                return false;
            }
            $hRow.find('.h-supplier').each(function () {
                qDestroySupplierSelect2($(this));
            });
            if ($hRows.length > 1) {
                $hRow.remove();
            } else {
                $hRow.find('.h-rate').val('');
                $hRow.find('.h-name').val('');
                $hRow.find('.h-hotel-id').val('');
                clearPricingSupplierSelect($hRow.find('.h-supplier'));
            }
            clearPricingKeySyncedEdits(key, catId);
            if (typeof scheduleItineraryMetaFromHotels === 'function') {
                scheduleItineraryMetaFromHotels();
            }
            return true;
        }

        if (key === 'land') {
            var $iRows = $('#qItinerarySupplierRows .q-itin-supplier-row');
            var $iRow = $iRows.eq(sourceIndex);
            if ($iRow.length) {
                if ($iRows.length > 1) {
                    qDestroySupplierSelect2($iRow.find('.q-itin-supplier'));
                    $iRow.remove();
                    refreshItinerarySupplierRemoveState();
                } else {
                    $iRow.find('.q-itin-rate').val('');
                    clearPricingSupplierSelect($iRow.find('.q-itin-supplier'));
                }
                clearPricingKeySyncedEdits(key);
                return true;
            }
            if ($('#q_itinerary_supplier').length) {
                clearPricingSupplierSelect($('#q_itinerary_supplier'));
                clearPricingKeySyncedEdits(key);
                return true;
            }
            return false;
        }

        return false;
    }

    function pricingPartValueForRender(state, key, partIndex, entryRate, slots) {
        var edited = state.user_edited && parseInt(state.user_edited[key], 10) === 1;
        var parts = (state.fixed_parts && state.fixed_parts[key]) || null;
        if (edited && parts && parts[partIndex] != null && parts[partIndex] !== '') {
            return parts[partIndex];
        }
        if (edited && (!parts || !parts.length) && partIndex === 0) {
            return state.fixed && state.fixed[key] != null ? state.fixed[key] : '';
        }
        if (!edited && entryRate != null && entryRate !== '') {
            return entryRate;
        }
        if (edited && parts && parts.length) {
            return parts[partIndex] != null ? parts[partIndex] : '';
        }
        if (!edited && partIndex === 0 && (!slots || slots <= 1) && state.fixed && state.fixed[key] != null) {
            return state.fixed[key];
        }
        return entryRate != null ? entryRate : '';
    }

    function pricingOptionColumnHtml(cat, state, idx, maxCustom, visibleKeys, slotCounts) {
        cat = cat || {};
        state = state || defaultPricingSheetState();
        var fixed = state.fixed || {};
        var id = cat.id || ('opt_' + (idx + 1));
        var title = pricingOptionTitle(cat, idx);
        var isActive = String(id) === String(qActiveHotelCategoryId);
        maxCustom = Math.max(0, parseInt(maxCustom, 10) || 0);
        visibleKeys = visibleKeys || pricingFixedCostKeys();
        slotCounts = slotCounts || {};
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
        visibleKeys.forEach(function (row) {
            var synced = (row.key === 'flight_train' || row.key === 'hotel' || row.key === 'land');
            var edited = state.user_edited && parseInt(state.user_edited[row.key], 10) === 1;
            var entries = pricingSupplierRateEntriesForKey(row.key, cat);
            var slots = Math.max(1, parseInt(slotCounts[row.key], 10) || Math.max(1, entries.length || 1));
            var i;
            for (i = 0; i < slots; i++) {
                var entry = entries[i] || { name: '', rate: '' };
                var canRemove = synced && entry.sourceIndex != null && entry.sourceIndex !== '';
                html += pricingAmountCellHtml({
                    key: row.key,
                    value: pricingPartValueForRender(state, row.key, i, entry.rate, slots),
                    edited: edited,
                    synced: synced,
                    supplierName: entry.name || '',
                    icon: row.icon || '',
                    partIndex: i,
                    sourceIndex: entry.sourceIndex,
                    removable: canRemove
                });
            }
        });
        // Keep hidden inputs for non-visible fixed keys so saved values are not lost on re-render.
        pricingFixedCostKeys().forEach(function (row) {
            if (visibleKeys.some(function (v) { return v.key === row.key; })) {
                return;
            }
            var edited = state.user_edited && parseInt(state.user_edited[row.key], 10) === 1 ? '1' : '0';
            html += '<input type="hidden" class="q-cost" data-key="' + row.key + '" value="' + esc(fixed[row.key] != null ? fixed[row.key] : '') + '" data-user-edited="' + edited + '">';
        });
        html += '<div class="q-custom-cost-rows">';
        var customs = getRenderableCustomCosts(state);
        for (var ci = 0; ci < maxCustom; ci++) {
            html += customCostRowHtml(customs[ci] || {});
        }
        html += '</div>';
        // Spacer only — Add Extra Cost (+) lives on the Extra Costs label, not in hotel options.
        if (maxCustom === 0) {
            html += '<div class="q-pricing-amount-cell q-pricing-add-cell is-spacer" aria-hidden="true"></div>';
        }

        html += '<div class="q-sheet-profit-block">';
        html += '<div class="q-profit-line q-profit-total-line">' +
            '<span class="q-profit-line-label">Total Cost</span>' +
            '<div class="q-profit-readonly q-sum-total" data-display="total">0</div>' +
            '</div>';
        html += '<div class="q-profit-line q-profit-add-line">' +
            '<span class="q-profit-line-label">Add Profit</span>' +
            '<div class="q-profit-inputs">' +
            '<div class="q-profit-row">' +
            '<div class="q-profit-pct-group">' +
            '<input type="number" step="0.01" min="0" class="form-control q-sheet-profit-percent q-sum-pct" placeholder="0" value="' + esc(state.profit_percent || '') + '" title="Profit %">' +
            '<span class="q-profit-pct-suffix" aria-hidden="true">%</span>' +
            '</div>' +
            '<span class="q-profit-or">OR</span>' +
            '<input type="number" step="0.01" min="0" class="form-control form-control-sm q-sheet-profit-amount" placeholder="Amount" value="' + esc(state.profit_amount || '') + '" title="Profit amount">' +
            '</div>' +
            '<div class="q-profit-calc-hint q-sum-profit" data-display="profit"></div>' +
            '</div></div>';
        html += '<div class="q-profit-line q-profit-package-line">' +
            '<span class="q-profit-line-label">Package Total</span>' +
            '<div class="q-profit-readonly q-sum-selling" data-display="selling">0</div>' +
            '</div>';
        html += '</div>';
        html += tourCostCardShellHtml(id);
        html += '<input type="hidden" class="q-sheet-total-cost" value="0">';
        html += '<input type="hidden" class="q-sheet-package-total" value="0">';
        html += '<input type="hidden" class="q-sheet-price-per-adult" value="' + esc(state.price_per_adult || '') + '"' +
            (parseInt(state.price_per_adult_edited, 10) === 1 ? ' data-user-edited="1"' : '') + '>';
        html += '<input type="hidden" class="q-sheet-quotation-total" value="0">';
        html += '<span class="q-sheet-adult-lbl d-none">1</span>';
        html += '</div></div>';
        return html;
    }

    function getPricingSlotSignature(cats) {
        cats = cats || (collectHotelCategories().categories || []);
        if (!cats.length) {
            cats = [{ id: 'opt_1', hotels: [] }];
        }
        var visibleKeys = getVisiblePricingFixedKeys(cats, qPricingOptionsState);
        var slotCounts = getPricingKeySlotCounts(cats, visibleKeys);
        var parts = visibleKeys.map(function (row) {
            var names = [];
            (cats || []).forEach(function (cat) {
                pricingSupplierRateEntriesForKey(row.key, cat).forEach(function (e) {
                    names.push(String((e && e.name) || ''));
                });
            });
            return row.key + ':' + (slotCounts[row.key] || 1) + '[' + names.join(',') + ']';
        });
        return getPricingVisibilitySignature(cats) + '|' + parts.join('|');
    }

    function refreshPricingSupplierNames() {
        if (qPricingRenderLock) {
            return;
        }
        var data = collectHotelCategories();
        var cats = data.categories || [];
        var sig = getPricingSlotSignature(cats);
        if (sig !== qPricingSlotSig) {
            renderPricingSheets();
        }
    }

    function pricingExtraCostsAddBtnHtml() {
        return '<button type="button" class="btn q-add-cost-row" title="Add extra cost" aria-label="Add extra cost">' +
            '<i class="fas fa-plus" aria-hidden="true"></i></button>';
    }

    function pricingLabelsColumnHtml(maxCustom, visibleKeys, customLabels, slotCounts) {
        maxCustom = Math.max(0, parseInt(maxCustom, 10) || 0);
        visibleKeys = visibleKeys || pricingFixedCostKeys();
        slotCounts = slotCounts || {};
        var html = '<div class="q-pricing-labels-col">';
        html += '<div class="q-pricing-labels-hd"></div>';
        html += '<div class="q-pricing-option-body">';
        visibleKeys.forEach(function (row) {
            var slots = Math.max(1, parseInt(slotCounts[row.key], 10) || 1);
            var i;
            for (i = 0; i < slots; i++) {
                if (i === 0) {
                    html += '<div class="q-pricing-row-label" data-cost-key="' + esc(row.key) + '"><i class="' + row.icon + '" aria-hidden="true"></i><span>' + esc(row.label) + '</span></div>';
                } else {
                    html += '<div class="q-pricing-row-label is-continuation" data-cost-key="' + esc(row.key) + '" aria-hidden="true"></div>';
                }
            }
        });
        for (var i = 0; i < maxCustom; i++) {
            if (i === 0) {
                html += '<div class="q-pricing-row-label q-pricing-custom-label has-add-cost">' +
                    '<i class="fas fa-ellipsis-h" aria-hidden="true"></i><span>Extra Costs</span>' +
                    pricingExtraCostsAddBtnHtml() +
                    '</div>';
            } else {
                html += '<div class="q-pricing-row-label q-pricing-custom-label"></div>';
            }
        }
        if (maxCustom === 0) {
            html += '<div class="q-pricing-row-label q-pricing-add-label has-add-cost">' +
                '<i class="fas fa-ellipsis-h" aria-hidden="true"></i><span>Extra Costs</span>' +
                pricingExtraCostsAddBtnHtml() +
                '</div>';
        }
        html += '</div></div>';
        return html;
    }

    var qPricingSlotSig = '';

    function renderPricingSheets(opts) {
        opts = opts || {};
        if (qPricingRenderLock) {
            return;
        }
        qPricingRenderLock = true;
        try {
            if (!opts.skipSnapshot) {
                snapshotPricingSheets();
            }
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
            // Keep state keyed to current category ids (also match loose string/number ids).
            var catIds = {};
            cats.forEach(function (cat) {
                catIds[String(cat.id)] = true;
            });
            // If save targeted an id that isn't in cats (stale), merge into active sheet.
            Object.keys(qPricingOptionsState).forEach(function (key) {
                if (!catIds[String(key)]) {
                    var orphan = qPricingOptionsState[key];
                    var activeId = String(qActiveHotelCategoryId || cats[0].id);
                    if (orphan && orphan.custom && orphan.custom.length && qPricingOptionsState[activeId]) {
                        qPricingOptionsState[activeId].custom = (qPricingOptionsState[activeId].custom || [])
                            .concat(orphan.custom)
                            .filter(customCostRowHasData);
                    } else if (orphan && orphan.custom && orphan.custom.length && !qPricingOptionsState[activeId]) {
                        qPricingOptionsState[activeId] = orphan;
                    }
                    delete qPricingOptionsState[key];
                }
            });
            $host.css('--q-opt-count', String(cats.length));
            $host.toggleClass('is-single-option', cats.length === 1);
            var maxCustom = 0;
            cats.forEach(function (cat) {
                var st = qPricingOptionsState[cat.id] || defaultPricingSheetState();
                maxCustom = Math.max(maxCustom, getRenderableCustomCosts(st).length);
            });
            var visibleKeys = getVisiblePricingFixedKeys(cats, qPricingOptionsState);
            var slotCounts = getPricingKeySlotCounts(cats, visibleKeys);
            var customLabels = getMatrixCustomLabels(cats, maxCustom);
            qPricingVisSig = getPricingVisibilitySignature(cats);
            qPricingSlotSig = getPricingSlotSignature(cats);
            $host.append(pricingLabelsColumnHtml(maxCustom, visibleKeys, customLabels, slotCounts));
            cats.forEach(function (cat, idx) {
                var state = qPricingOptionsState[cat.id] || defaultPricingSheetState();
                $host.append(pricingOptionColumnHtml(cat, state, idx, maxCustom, visibleKeys, slotCounts));
            });
            renderTourCostRows();
            recalcCosts();
            syncTourCostOptUiFromMasters();
        } finally {
            qPricingRenderLock = false;
        }
    }

    var qPricingVisSig = '';
    var qPricingRenderLock = false;

    function getPricingVisibilitySignature(cats) {
        cats = cats || (collectHotelCategories().categories || []);
        if (!cats.length) {
            cats = [{ id: 'opt_1', hotels: [] }];
        }
        var keys = getVisiblePricingFixedKeys(cats, qPricingOptionsState).map(function (r) {
            return r.key;
        }).join(',');
        var custom = 0;
        cats.forEach(function (cat) {
            custom = Math.max(custom, getRenderableCustomCosts(qPricingOptionsState[cat.id] || {}).length);
        });
        return keys + '#' + custom;
    }

    function refreshPricingVisibilityIfNeeded() {
        if (qPricingRenderLock) {
            return;
        }
        var data = collectHotelCategories();
        var cats = data.categories || [];
        var sig = getPricingSlotSignature(cats);
        if (sig !== qPricingSlotSig) {
            renderPricingSheets();
        }
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
        var edited = false;
        $sheet.find('.q-cost[data-key="' + key + '"]').each(function () {
            if ($(this).attr('data-user-edited') === '1') {
                edited = true;
            }
        });
        return edited;
    }

    function syncSheetCostPartsFromEntries($sheet, key, cat) {
        if (isSheetCostUserEdited($sheet, key)) {
            return;
        }
        var entries = pricingSupplierRateEntriesForKey(key, cat);
        var $inputs = $sheet.find('.q-cost[data-key="' + key + '"]').filter(function () {
            return $(this).attr('type') !== 'hidden';
        });
        if (!$inputs.length) {
            return;
        }
        if (entries.length) {
            $inputs.each(function (i) {
                var entry = entries[i];
                $(this).val(entry && entry.rate ? entry.rate : '');
            });
            return;
        }
        if ($inputs.length === 1) {
            var fallback = 0;
            if (key === 'hotel') {
                fallback = hotelTotalForCategory(cat || {});
            } else if (key === 'flight_train') {
                fallback = sumNumericFields('#qFlightRows .f-fare');
            } else if (key === 'land') {
                fallback = getItineraryLandTotal();
            }
            $inputs.first().val(fallback > 0 ? (key === 'land' ? String(Math.round(fallback)) : fallback.toFixed(2)) : '');
        } else {
            $inputs.val('');
        }
    }

    function syncSheetHotelFromCategory($sheet) {
        var catId = String($sheet.attr('data-cat-id') || '');
        var data = collectHotelCategories();
        var cat = (data.categories || []).find(function (c) { return String(c.id) === catId; });
        if (!cat) return;
        syncSheetCostPartsFromEntries($sheet, 'hotel', cat);
    }

    function syncSheetFlightFromServices($sheet) {
        var catId = String($sheet.attr('data-cat-id') || '');
        var data = collectHotelCategories();
        var cat = (data.categories || []).find(function (c) { return String(c.id) === catId; }) || { id: catId, hotels: [] };
        syncSheetCostPartsFromEntries($sheet, 'flight_train', cat);
    }

    function syncSheetLandFromItinerary($sheet) {
        var catId = String($sheet.attr('data-cat-id') || '');
        var data = collectHotelCategories();
        var cat = (data.categories || []).find(function (c) { return String(c.id) === catId; }) || { id: catId, hotels: [] };
        syncSheetCostPartsFromEntries($sheet, 'land', cat);
    }

    function formatInrDisplay(n) {
        var num = parseFloat(n);
        if (isNaN(num)) num = 0;
        return '₹ ' + money(num);
    }

    /* ------------------------------------------------------------------ */
    /* Tour Cost Pricing card (Package Total distributed among travelers) */
    /* ------------------------------------------------------------------ */
    var qTourCostState = {
        adult_rate: '',
        adult_rate_edited: 0,
        child_rates: [],
        infant_rate: '',
        gst_percent: 5,
        children_ages: [],
        child_qtys: []
    };
    var qTourCostAutoSaveTimer = null;
    /** When true, #q_children handlers must not expand/rebuild child age rows. */
    var qTourCostChildQtySync = false;

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function defaultTourCostState() {
        return {
            adult_rate: '',
            adult_rate_edited: 0,
            child_rates: [],
            infant_rate: '',
            gst_percent: 5,
            children_ages: [],
            child_qtys: []
        };
    }

    function roundTourMoney(n) {
        var num = parseFloat(n);
        if (isNaN(num)) {
            num = 0;
        }
        return Math.round(num * 100) / 100;
    }

    function readGuestCounts() {
        var adults = parseInt($('#q_adults').val(), 10);
        var children = parseInt($('#q_children').val(), 10);
        if (isNaN(adults) || adults < 1) adults = 1;
        if (isNaN(children) || children < 0) children = 0;
        return { adults: adults, children: children };
    }

    function parseTourJsonArray(raw) {
        if (Array.isArray(raw)) {
            return raw.slice();
        }
        if (raw == null || raw === '') {
            return [];
        }
        if (typeof raw === 'string') {
            try {
                var parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        }
        return [];
    }

    function normalizeChildrenAges(raw, childCount) {
        childCount = Math.max(0, parseInt(childCount, 10) || 0);
        var ages = [];
        parseTourJsonArray(raw).forEach(function (age) {
            var n = parseInt(age, 10);
            ages.push(isNaN(n) || n < 0 ? 0 : n);
        });
        while (ages.length < childCount) {
            ages.push(0);
        }
        if (ages.length > childCount) {
            ages = ages.slice(0, childCount);
        }
        return ages;
    }

    /** Child cost rows: one UI row per age group, with editable qty (not one row per traveler). */
    function readChildCostRows() {
        var ages = parseTourJsonArray($('#q_children_ages').val() || '[]');
        if (!ages.length && Array.isArray(qTourCostState.children_ages)) {
            ages = qTourCostState.children_ages.slice();
        }
        var qtys = parseTourJsonArray($('#q_children_qtys').val() || '[]');
        if (!qtys.length && Array.isArray(qTourCostState.child_qtys)) {
            qtys = qTourCostState.child_qtys.slice();
        }
        var rows = [];
        var i;
        if (ages.length) {
            for (i = 0; i < ages.length; i++) {
                var age = parseInt(ages[i], 10);
                if (isNaN(age) || age < 0) {
                    age = 0;
                }
                var qty = parseInt(qtys[i], 10);
                if (isNaN(qty) || qty < 1) {
                    qty = 1;
                }
                rows.push({ age: age, qty: qty });
            }
        } else {
            var n = readGuestCounts().children;
            for (i = 0; i < n; i++) {
                rows.push({ age: 0, qty: 1 });
            }
        }
        return rows;
    }

    function writeChildCostRows(rows, opts) {
        opts = opts || {};
        rows = Array.isArray(rows) ? rows : [];
        var ages = [];
        var qtys = [];
        var total = 0;
        rows.forEach(function (r) {
            var age = parseInt(r && r.age, 10);
            if (isNaN(age) || age < 0) {
                age = 0;
            }
            var qty = parseInt(r && r.qty, 10);
            if (isNaN(qty) || qty < 1) {
                qty = 1;
            }
            ages.push(age);
            qtys.push(qty);
            total += qty;
        });
        qTourCostState.children_ages = ages.slice();
        qTourCostState.child_qtys = qtys.slice();
        $('#q_children_ages').val(JSON.stringify(ages));
        $('#q_children_qtys').val(JSON.stringify(qtys));
        if (!opts.skipChildrenField) {
            $('#q_children').val(String(total));
        }
        return { ages: ages, qtys: qtys, totalChildren: total, rows: rows };
    }

    function readChildrenAges() {
        var rows = readChildCostRows();
        writeChildCostRows(rows, { skipChildrenField: true });
        return rows.map(function (r) { return r.age; });
    }

    function writeChildrenAges(ages) {
        ages = parseTourJsonArray(ages);
        var existing = readChildCostRows();
        var rows = [];
        var i;
        for (i = 0; i < ages.length; i++) {
            var age = parseInt(ages[i], 10);
            if (isNaN(age) || age < 0) {
                age = 0;
            }
            var qty = existing[i] && existing[i].qty ? existing[i].qty : 1;
            rows.push({ age: age, qty: qty });
        }
        return writeChildCostRows(rows).ages;
    }

    function syncChildRowsToGuestChildrenCount() {
        var target = readGuestCounts().children;
        var rows = readChildCostRows();
        var total = 0;
        rows.forEach(function (r) { total += r.qty; });
        while (total < target) {
            rows.push({ age: 0, qty: 1 });
            total++;
        }
        while (total > target && rows.length) {
            var last = rows[rows.length - 1];
            if (last.qty > 1) {
                last.qty -= 1;
                total -= 1;
            } else {
                rows.pop();
                total -= 1;
            }
        }
        writeChildCostRows(rows, { skipChildrenField: true });
        return rows;
    }

    function ensureTourCostChildRates(childCount) {
        childCount = Math.max(0, parseInt(childCount, 10) || 0);
        if (!Array.isArray(qTourCostState.child_rates)) {
            qTourCostState.child_rates = [];
        }
        var rows = readChildCostRows();
        while (qTourCostState.child_rates.length < rows.length) {
            qTourCostState.child_rates.push('');
        }
        if (qTourCostState.child_rates.length > rows.length) {
            qTourCostState.child_rates = qTourCostState.child_rates.slice(0, rows.length);
        }
    }

    /** Split total into `count` shares (2dp) that always sum exactly to total. */
    function distributeEqualShares(total, count) {
        total = roundTourMoney(total);
        count = Math.max(0, parseInt(count, 10) || 0);
        if (count <= 0) {
            return [];
        }
        var cents = Math.round(total * 100);
        var base = Math.floor(cents / count);
        var rem = cents - (base * count);
        var out = [];
        var i;
        for (i = 0; i < count; i++) {
            out.push((base + (i < rem ? 1 : 0)) / 100);
        }
        return out;
    }

    function buildPackageTravelerBreakdown(packageTotal, adults, childQtys) {
        adults = Math.max(1, parseInt(adults, 10) || 1);
        childQtys = Array.isArray(childQtys) ? childQtys : [];
        var childTotal = 0;
        var normalizedQtys = [];
        childQtys.forEach(function (q) {
            var qty = parseInt(q, 10);
            if (isNaN(qty) || qty < 0) {
                qty = 0;
            }
            normalizedQtys.push(qty);
            childTotal += qty;
        });
        var totalTravelers = Math.max(1, adults + childTotal);
        packageTotal = roundTourMoney(packageTotal);
        var shares = distributeEqualShares(packageTotal, totalTravelers);
        var adultShares = shares.slice(0, adults);
        var adultAmount = roundTourMoney(adultShares.reduce(function (sum, v) { return sum + v; }, 0));
        var childAmounts = [];
        var idx = adults;
        normalizedQtys.forEach(function (qty) {
            var part = shares.slice(idx, idx + qty);
            var amt = roundTourMoney(part.reduce(function (sum, v) { return sum + v; }, 0));
            childAmounts.push(amt);
            idx += qty;
        });
        var perPerson = roundTourMoney(packageTotal / totalTravelers);
        return {
            package_total: packageTotal,
            total_travelers: totalTravelers,
            per_person: perPerson,
            adult_amount: adultAmount,
            child_amounts: childAmounts,
            child_qtys: normalizedQtys,
            adult_rate: perPerson,
            child_rate: perPerson
        };
    }

    function formatTourMoney(n) {
        var num = parseFloat(n);
        if (isNaN(num)) num = 0;
        return 'INR ' + num.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatTourRateValue(n) {
        var num = roundTourMoney(n);
        if (!num) {
            return '';
        }
        return String(num);
    }

    function tourCostOptsHtml(suffix) {
        var sid = String(suffix || 'x').replace(/[^a-zA-Z0-9_-]/g, '_');
        var wi = $('#q_without_itinerary').is(':checked') ? ' checked' : '';
        var hg = $('#q_hide_gst_note').is(':checked') ? ' checked' : '';
        return '' +
            '<div class="q-tour-cost-opts" role="group" aria-label="Quotation display options">' +
            '<label class="q-tour-opt-check" for="q_tour_opt_wi_' + sid + '">' +
            '<input type="checkbox" class="q-tour-opt-input q-tour-opt-without-itinerary" id="q_tour_opt_wi_' + sid + '"' + wi + '>' +
            '<span class="q-tour-opt-box" aria-hidden="true"></span>' +
            '<span class="q-tour-opt-text">Without Itinerary</span>' +
            '</label>' +
            '<label class="q-tour-opt-check" for="q_tour_opt_hg_' + sid + '">' +
            '<input type="checkbox" class="q-tour-opt-input q-tour-opt-hide-gst" id="q_tour_opt_hg_' + sid + '"' + hg + '>' +
            '<span class="q-tour-opt-box" aria-hidden="true"></span>' +
            '<span class="q-tour-opt-text">Hide GST Note</span>' +
            '</label>' +
            '</div>';
    }

    function syncTourCostOptUiFromMasters() {
        var wi = $('#q_without_itinerary').is(':checked');
        var hg = $('#q_hide_gst_note').is(':checked');
        $('.q-tour-opt-without-itinerary').prop('checked', wi);
        $('.q-tour-opt-hide-gst').prop('checked', hg);
    }

    function tourCostCardShellHtml(suffix) {
        return '' +
            '<div class="q-tour-cost-card q-sheet-tour-cost" aria-label="Tour Cost Summary">' +
            '<div class="q-tour-cost-hd">' +
            '<div class="q-tour-cost-hd-left">' +
            '<span class="q-tour-cost-hd-ico" aria-hidden="true"><i class="fas fa-suitcase-rolling"></i></span>' +
            '<div class="q-tour-cost-hd-copy">' +
            '<h4 class="q-tour-cost-title">Tour Cost Summary</h4>' +
            '<p class="q-tour-cost-sub">Total cost breakdown of your selected tour</p>' +
            '</div></div>' +
            '<span class="q-tour-cost-autosave q-sheet-tour-autosave" title="Draft status">' +
            '<i class="fas fa-check"></i> Auto Saved' +
            '</span></div>' +
            '<div class="q-tour-cost-body q-sheet-tour-cost-rows"></div>' +
            '<div class="q-tour-cost-grand-wrap">' +
            '<div class="q-tour-cost-grand">' +
            '<div class="q-tour-cost-grand-left">' +
            '<span class="q-tour-cost-grand-ico" aria-hidden="true">₹</span>' +
            '<div class="q-tour-cost-grand-text">' +
            '<span class="q-tour-cost-grand-label">Grand Total</span>' +
            '<span class="q-tour-cost-grand-sub">Total amount to be paid</span>' +
            '</div></div>' +
            '<span class="q-tour-cost-grand-divider" aria-hidden="true"></span>' +
            '<strong class="q-tour-cost-grand-amount q-sheet-tour-grand">INR 0.00</strong>' +
            '</div></div>' +
            tourCostOptsHtml(suffix) +
            '</div>';
    }

    function tourCostRowSubtitle(key, name) {
        return '';
    }

    function tourCostChildLabel(index, age) {
        var ageNum = parseInt(age, 10);
        if (!isNaN(ageNum) && ageNum > 0) {
            return 'Child – ' + ageNum + ' Yrs';
        }
        return 'Child – Yrs';
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
        var rateReadonly = opts.rateReadonly !== false;
        var qtyEditable = !!opts.qtyEditable;
        var ageEditable = !!opts.ageEditable;
        var ageVal = opts.age != null ? opts.age : '';
        var summary = !!opts.summary;
        var hideMeta = !!opts.hideMeta;
        var gstEditable = !!opts.gstEditable;
        var gstPct = opts.gstPct != null ? opts.gstPct : 5;
        var removable = !!opts.removable;
        var removeIndex = opts.removeIndex != null ? String(opts.removeIndex) : '';
        var subtitle = '';

        var controlsHtml = '';
        if (!hideMeta && (editable || rateReadonly)) {
            controlsHtml =
                '<div class="q-tour-cost-controls">' +
                '<span class="q-tour-cost-rate-group">' +
                (rateReadonly
                    ? '<span class="q-tour-cost-meta-rate" data-tour-rate="' + esc(key) + '">' + esc(rate !== '' ? String(rate) : '0') + '</span>'
                    : '<input type="number" step="0.01" min="0" class="form-control q-tour-rate-input q-tour-cost-rate-inline" data-tour-key="' + esc(key) + '" value="' + esc(rate) + '" placeholder="0">') +
                '</span>' +
                '<span class="q-tour-cost-meta-mul">×</span>' +
                (qtyEditable
                    ? '<span class="q-tour-cost-qty-group">' +
                    '<input type="number" step="1" min="1" class="form-control q-tour-qty-input q-tour-cost-qty-inline" data-tour-qty="' + esc(key) + '" value="' + esc(qty) + '" aria-label="Quantity">' +
                    '</span>'
                    : '<span class="q-tour-cost-meta-qty">' + esc(String(qty)) + '</span>') +
                '</div>';
        } else if (gstEditable) {
            controlsHtml =
                '<div class="q-tour-cost-controls q-tour-cost-controls-gst">' +
                '<span class="q-tour-cost-gst-group">' +
                '<input type="number" step="0.01" min="0" max="100" class="form-control q-tour-gst-input q-tour-cost-gst-inline" value="' + esc(gstPct) + '" aria-label="GST percent">' +
                '<span class="q-tour-cost-gst-suffix" aria-hidden="true">%</span>' +
                '</span></div>';
        }

        var nameHtml;
        if (ageEditable) {
            var ageShow = parseInt(ageVal, 10);
            nameHtml =
                '<span class="q-tour-cost-traveller-name q-tour-cost-child-age-label">' +
                '<span class="q-tour-cost-child-name">Child</span>' +
                '<input type="number" step="1" min="0" max="17" class="form-control q-tour-age-input" data-tour-age="' + esc(key) + '" value="' +
                esc(!isNaN(ageShow) && ageShow > 0 ? String(ageShow) : '') +
                '" placeholder="—" aria-label="Child age">' +
                '<span class="q-tour-cost-child-age-unit">Y</span>' +
                '</span>';
        } else {
            nameHtml = '<span class="q-tour-cost-traveller-name">' + esc(name) + '</span>';
        }

        var rowClass = 'q-tour-cost-row' +
            (summary ? ' is-summary' : '') +
            (gstEditable ? ' q-tour-gst-row' : '') +
            (removable ? ' has-remove' : '') +
            (ageEditable ? ' has-child-age' : '');

        return '' +
            '<div class="' + rowClass + '" data-tour-key="' + esc(key) + '"' +
            (removable ? ' data-child-index="' + esc(removeIndex) + '"' : '') + '>' +
            '<div class="q-tour-cost-traveller">' +
            '<span class="q-tour-cost-avatar" aria-hidden="true"><i class="' + icon + '"></i></span>' +
            '<div class="q-tour-cost-traveller-text">' +
            nameHtml +
            (subtitle ? '<span class="q-tour-cost-traveller-sub">' + esc(subtitle) + '</span>' : '') +
            '</div></div>' +
            controlsHtml +
            '<div class="q-tour-cost-amount" data-tour-amount="' + esc(key) + '">' + esc(amountText) + '</div>' +
            '<div class="q-tour-cost-action">' +
            (removable
                ? '<button type="button" class="btn q-tour-child-remove" title="Remove child cost" aria-label="Remove child cost" data-child-index="' + esc(removeIndex) + '">' +
                '<i class="fas fa-times" aria-hidden="true"></i></button>'
                : '') +
            '</div>' +
            '</div>';
    }

    function removeTourCostChildAtIndex(childIndex) {
        childIndex = parseInt(childIndex, 10);
        if (isNaN(childIndex) || childIndex < 0) {
            return false;
        }
        var rows = readChildCostRows();
        if (childIndex >= rows.length) {
            return false;
        }
        rows.splice(childIndex, 1);
        qTourCostChildQtySync = true;
        try {
            writeChildCostRows(rows);
        } finally {
            qTourCostChildQtySync = false;
        }
        if (Array.isArray(qTourCostState.child_rates) && qTourCostState.child_rates.length > childIndex) {
            qTourCostState.child_rates.splice(childIndex, 1);
        }
        snapshotTourCostFromDom();
        renderTourCostRows();
        recalcCosts();
        saveFormDraftToStorage();
        return true;
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
        return roundTourMoney(total + profit);
    }

    function getSheetPerPersonRate($sheet, adults, children) {
        if (!$sheet || !$sheet.length) {
            return 0;
        }
        var counts = readGuestCounts();
        adults = adults != null ? adults : counts.adults;
        children = children != null ? children : counts.children;
        var pkgBase = getSheetPackageBase($sheet);
        if (pkgBase <= 0) {
            return 0;
        }
        var totalTravelers = Math.max(1, (parseInt(adults, 10) || 0) + (parseInt(children, 10) || 0));
        return roundTourMoney(pkgBase / totalTravelers);
    }

    /** @deprecated alias for older call sites */
    function getSheetAdultRate($sheet, adults) {
        return getSheetPerPersonRate($sheet, adults, readGuestCounts().children);
    }

    function buildTourCostRowsHtml(perPersonRate) {
        var counts = readGuestCounts();
        var childRows = readChildCostRows();
        qTourCostChildQtySync = true;
        try {
            writeChildCostRows(childRows);
        } finally {
            qTourCostChildQtySync = false;
        }
        counts = readGuestCounts();
        ensureTourCostChildRates(childRows.length);
        var hideGst = $('#q_hide_gst_note').is(':checked');
        var gstPct = hideGst ? 0 : (parseFloat(qTourCostState.gst_percent) || 5);
        var rateStr = perPersonRate != null && perPersonRate !== '' ? String(perPersonRate) : '';
        var html = '';
        html += tourCostRowHtml({
            key: 'adult',
            icon: 'fas fa-user-friends',
            name: 'Adults',
            rate: rateStr,
            qty: counts.adults,
            qtyEditable: true,
            rateReadonly: true,
            editable: true,
            amountText: 'INR 0.00'
        });
        childRows.forEach(function (row, i) {
            html += tourCostRowHtml({
                key: 'child_' + i,
                icon: 'fas fa-child',
                name: tourCostChildLabel(i, row.age),
                age: row.age,
                ageEditable: true,
                rate: rateStr,
                qty: row.qty,
                qtyEditable: true,
                rateReadonly: true,
                editable: true,
                amountText: 'INR 0.00',
                removable: true,
                removeIndex: i
            });
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
        var counts = readGuestCounts();
        if ($sheets.length) {
            $sheets.each(function () {
                var $sheet = $(this);
                var perPerson = getSheetPerPersonRate($sheet, counts.adults, counts.children);
                $sheet.find('.q-sheet-tour-cost-rows').html(buildTourCostRowsHtml(perPerson > 0 ? formatTourRateValue(perPerson) : ''));
            });
            return;
        }
        var $host = $('#qTourCostRows');
        if (!$host.length) {
            return;
        }
        var legacyRate = getSheetPerPersonRate(getActivePricingSheet(), counts.adults, counts.children);
        if (legacyRate <= 0 && qTourCostState.adult_rate) {
            legacyRate = parseFloat(qTourCostState.adult_rate) || 0;
        }
        $host.html(buildTourCostRowsHtml(legacyRate > 0 ? formatTourRateValue(legacyRate) : ''));
    }

    function snapshotTourCostFromDom($fromEl) {
        var $scope = getTourCostScope($fromEl);
        if ($scope.length) {
            var rows = readChildCostRows();
            var changed = false;
            rows.forEach(function (row, i) {
                var $qty = $scope.find('.q-tour-qty-input[data-tour-qty="child_' + i + '"]');
                if ($qty.length) {
                    var rawQty = String($qty.val() == null ? '' : $qty.val()).trim();
                    if (rawQty !== '') {
                        var q = parseInt(rawQty, 10);
                        if (!isNaN(q) && q >= 1 && q !== row.qty) {
                            row.qty = q;
                            changed = true;
                        }
                    }
                }
                var $age = $scope.find('.q-tour-age-input[data-tour-age="child_' + i + '"]');
                if ($age.length) {
                    var rawAge = String($age.val() == null ? '' : $age.val()).trim();
                    if (rawAge !== '') {
                        var a = parseInt(rawAge, 10);
                        if (!isNaN(a) && a >= 0 && a !== row.age) {
                            row.age = a;
                            changed = true;
                        }
                    }
                }
            });
            if (changed) {
                qTourCostChildQtySync = true;
                try {
                    writeChildCostRows(rows);
                } finally {
                    qTourCostChildQtySync = false;
                }
            }
            ensureTourCostChildRates(rows.length);
            var $gst = $scope.find('.q-tour-gst-input').first();
            if ($gst.length) {
                var g = parseFloat($gst.val());
                if (!isNaN(g) && g >= 0) {
                    qTourCostState.gst_percent = g;
                }
            }
            return;
        }
        ensureTourCostChildRates(readChildCostRows().length);
    }

    function collectTourCostPayload(packageTotalOverride, $scopeEl) {
        snapshotTourCostFromDom($scopeEl);
        var counts = readGuestCounts();
        var childRows = readChildCostRows();
        var childQtys = childRows.map(function (r) { return r.qty; });
        var ages = childRows.map(function (r) { return r.age; });
        var $sheet = $scopeEl && $scopeEl.length ? $scopeEl.closest('.q-pricing-option-sheet') : getActivePricingSheet();
        var packageTotal;
        if (packageTotalOverride != null && !isNaN(packageTotalOverride)) {
            packageTotal = roundTourMoney(packageTotalOverride);
        } else if ($sheet.length) {
            packageTotal = getSheetPackageBase($sheet);
        } else {
            packageTotal = roundTourMoney(String($('#q_package_total').val() || '').replace(/,/g, ''));
        }
        var breakdown = buildPackageTravelerBreakdown(packageTotal, counts.adults, childQtys);
        var hideGst = $('#q_hide_gst_note').is(':checked');
        var gstPct = hideGst ? 0 : (parseFloat(qTourCostState.gst_percent) || 5);
        var gst = roundTourMoney(breakdown.package_total * gstPct / 100);
        var grand = roundTourMoney(breakdown.package_total + gst);
        var childRates = [];
        var i;
        for (i = 0; i < childRows.length; i++) {
            childRates.push(String(breakdown.child_amounts[i] != null ? breakdown.child_amounts[i] : 0));
        }
        qTourCostState.adult_rate = breakdown.per_person > 0 ? String(breakdown.per_person) : '';
        qTourCostState.child_rates = childRates.slice();
        qTourCostState.child_qtys = childQtys.slice();
        qTourCostState.children_ages = ages.slice();
        return {
            adult_rate: breakdown.per_person > 0 ? String(breakdown.per_person) : '',
            adult_rate_edited: 0,
            child_rates: childRates,
            children_ages: ages.slice(),
            child_qtys: childQtys.slice(),
            infant_rate: '',
            adults: counts.adults,
            children: breakdown.total_travelers - counts.adults,
            total_travelers: breakdown.total_travelers,
            per_person: breakdown.per_person,
            adult_amount: breakdown.adult_amount,
            child_amount: roundTourMoney(breakdown.child_amounts.reduce(function (s, v) { return s + v; }, 0)),
            child_amounts: breakdown.child_amounts.slice(),
            infant_amount: 0,
            package_total: breakdown.package_total,
            subtotal: breakdown.package_total,
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
        var childRows = readChildCostRows();
        var perPerson = payload.per_person != null ? payload.per_person : payload.adult_rate;
        $scope.find('[data-tour-rate="adult"]').text(perPerson != null && perPerson !== '' ? String(roundTourMoney(perPerson)) : '0');
        $scope.find('[data-tour-amount="adult"]').text(formatTourMoney(payload.adult_amount));
        var childAmounts = Array.isArray(payload.child_amounts) ? payload.child_amounts : [];
        var i;
        for (i = 0; i < childRows.length; i++) {
            var childAmt = childAmounts[i] != null ? childAmounts[i] : 0;
            $scope.find('[data-tour-rate="child_' + i + '"]').text(perPerson != null && perPerson !== '' ? String(roundTourMoney(perPerson)) : '0');
            $scope.find('[data-tour-amount="child_' + i + '"]').text(formatTourMoney(childAmt));
            var $qty = $scope.find('.q-tour-qty-input[data-tour-qty="child_' + i + '"]');
            if ($qty.length && !$qty.is(':focus')) {
                $qty.val(String(childRows[i].qty));
            }
            var $age = $scope.find('.q-tour-age-input[data-tour-age="child_' + i + '"]');
            if ($age.length && !$age.is(':focus')) {
                var ageNum = parseInt(childRows[i].age, 10);
                $age.val(!isNaN(ageNum) && ageNum > 0 ? String(ageNum) : '');
            }
        }
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
        var counts = readGuestCounts();
        var packageTotal = getSheetPackageBase($sheet);
        var perPerson = getSheetPerPersonRate($sheet, counts.adults, counts.children);
        var $ppa = $sheet.find('.q-sheet-price-per-adult');
        if (!$ppa.is(':focus')) {
            $ppa.val(perPerson > 0 ? String(perPerson) : '').removeAttr('data-user-edited');
        }
        var payload = collectTourCostPayload(packageTotal, $sheet);
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
            $('#q_package_total').val(money(activePayload.package_total != null ? activePayload.package_total : activePayload.subtotal));
            $('#q_price_per_adult').val($active.length ? ($active.find('.q-sheet-price-per-adult').val() || '') : (activePayload.per_person || activePayload.adult_rate || ''));
            qTourCostState.adult_rate = activePayload.adult_rate || '';
            if (Array.isArray(activePayload.children_ages) || Array.isArray(activePayload.child_qtys)) {
                var rows = [];
                var ages = Array.isArray(activePayload.children_ages) ? activePayload.children_ages : readChildCostRows().map(function (r) { return r.age; });
                var qtys = Array.isArray(activePayload.child_qtys) ? activePayload.child_qtys : [];
                var ri;
                for (ri = 0; ri < ages.length; ri++) {
                    rows.push({
                        age: ages[ri],
                        qty: qtys[ri] != null ? qtys[ri] : 1
                    });
                }
                qTourCostChildQtySync = true;
                try {
                    writeChildCostRows(rows, { skipChildrenField: true });
                    var totalKids = rows.reduce(function (s, r) { return s + (parseInt(r.qty, 10) || 0); }, 0);
                    $('#q_children').val(String(totalKids));
                } finally {
                    qTourCostChildQtySync = false;
                }
            }
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
        var counts = readGuestCounts();
        var perPerson = getSheetPerPersonRate($sheet, counts.adults, counts.children);
        var $ppa = $sheet.find('.q-sheet-price-per-adult');
        if (!$ppa.is(':focus')) {
            $ppa.val(perPerson > 0 ? String(perPerson) : '').removeAttr('data-user-edited');
        }
        $sheet.find('.q-sheet-tour-cost-rows [data-tour-rate="adult"], .q-sheet-tour-cost-rows [data-tour-rate^="child_"]').each(function () {
            $(this).text(perPerson > 0 ? String(perPerson) : '0');
        });
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
        if (state.adult_rate_edited != null) qTourCostState.adult_rate_edited = 0;
        if (Array.isArray(state.child_rates)) qTourCostState.child_rates = state.child_rates.slice();
        if (state.infant_rate != null) qTourCostState.infant_rate = state.infant_rate;
        if (state.gst_percent != null) qTourCostState.gst_percent = state.gst_percent;
        if (Array.isArray(state.children_ages) || Array.isArray(state.child_qtys)) {
            var agesApply = Array.isArray(state.children_ages) ? state.children_ages : [];
            var qtysApply = Array.isArray(state.child_qtys) ? state.child_qtys : [];
            var rowsApply = [];
            var ai;
            for (ai = 0; ai < agesApply.length; ai++) {
                rowsApply.push({
                    age: agesApply[ai],
                    qty: qtysApply[ai] != null ? qtysApply[ai] : 1
                });
            }
            qTourCostChildQtySync = true;
            try {
                writeChildCostRows(rowsApply);
            } finally {
                qTourCostChildQtySync = false;
            }
        }
        var $active = getActivePricingSheet();
        if ($active.length && (state.per_person != null || state.adult_rate != null)) {
            var pp = state.per_person != null ? state.per_person : state.adult_rate;
            $active.find('.q-sheet-price-per-adult').val(pp).removeAttr('data-user-edited');
        }
        renderTourCostRows();
        recalcAllTourCostCards();
    }

    function recalcOnePricingSheet($sheet, adults) {
        syncSheetFlightFromServices($sheet);
        syncSheetHotelFromCategory($sheet);
        syncSheetLandFromItinerary($sheet);

        var total = 0;
        $sheet.find('.q-cost').each(function () {
            var v = parseFloat($(this).val());
            if (!isNaN(v)) total += v;
        });
        $sheet.find('.q-sheet-total-cost').val(money(total));
        $sheet.find('.q-sum-total').text(money(total));

        var profit = 0;
        var pct = parseFloat($sheet.find('.q-sheet-profit-percent').val());
        var amt = parseFloat($sheet.find('.q-sheet-profit-amount').val());
        var usingAmount = !isNaN(amt) && amt > 0;
        if (usingAmount) {
            profit = amt;
        } else if (!isNaN(pct) && pct > 0) {
            profit = total * pct / 100;
        }
        var pkgBase = total + profit;
        var $profitHint = $sheet.find('.q-sum-profit');
        if (profit > 0) {
            $profitHint.text('Rs ' + money(profit)).addClass('is-visible');
        } else {
            $profitHint.text('').removeClass('is-visible');
        }
        $sheet.find('.q-sheet-adult-lbl').text(adults);
        $('#qPricingSheetsHost .q-matrix-adult-lbl').text(adults);
        $sheet.find('.q-sum-selling').text(money(pkgBase));
        $sheet.find('.q-sheet-package-total').val(money(pkgBase));

        var counts = readGuestCounts();
        var totalTravelers = Math.max(1, counts.adults + counts.children);
        var $ppa = $sheet.find('.q-sheet-price-per-adult');
        var perPerson = Math.round((pkgBase / totalTravelers) * 100) / 100;
        if (!$ppa.is(':focus')) {
            $ppa.val(pkgBase > 0 ? perPerson : '').removeAttr('data-user-edited');
        }
        $sheet.find('.q-sheet-quotation-total').val(money(pkgBase));
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
            $('#q_package_total').val(money(payload.package_total != null ? payload.package_total : payload.subtotal));
            $('#q_price_per_adult').val($active.find('.q-sheet-price-per-adult').val() || '');
            qTourCostState.adult_rate = payload.adult_rate || '';
            if (Array.isArray(payload.children_ages) || Array.isArray(payload.child_qtys)) {
                var agesLeg = Array.isArray(payload.children_ages) ? payload.children_ages : readChildCostRows().map(function (r) { return r.age; });
                var qtysLeg = Array.isArray(payload.child_qtys) ? payload.child_qtys : [];
                var rowsLeg = [];
                var li;
                for (li = 0; li < agesLeg.length; li++) {
                    rowsLeg.push({
                        age: agesLeg[li],
                        qty: qtysLeg[li] != null ? qtysLeg[li] : 1
                    });
                }
                qTourCostChildQtySync = true;
                try {
                    writeChildCostRows(rowsLeg, { skipChildrenField: true });
                    var kidsLeg = rowsLeg.reduce(function (s, r) { return s + (parseInt(r.qty, 10) || 0); }, 0);
                    $('#q_children').val(String(kidsLeg));
                } finally {
                    qTourCostChildQtySync = false;
                }
            }
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
        refreshPricingVisibilityIfNeeded();
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

    $(document).on('input change', '.q-sheet-tour-cost-rows .q-tour-gst-input, #qTourCostRows .q-tour-gst-input', function () {
        var $sheet = $(this).closest('.q-pricing-option-sheet');
        snapshotTourCostFromDom($(this));
        var gstVal = $(this).val();
        $('.q-sheet-tour-cost-rows .q-tour-gst-input, #qTourCostRows .q-tour-gst-input').not(this).each(function () {
            if (!$(this).is(':focus')) {
                $(this).val(gstVal);
            }
        });
        if ($sheet.length) {
            var activePayload = null;
            $('#qPricingSheetsHost .q-pricing-option-sheet').each(function () {
                var payload = recalcTourCostForSheet($(this));
                if ($(this).hasClass('is-active')) {
                    activePayload = payload;
                }
            });
            if (activePayload) {
                $('#q_quotation_total').val(money(activePayload.grand_total));
                $('#q_package_total').val(money(activePayload.package_total != null ? activePayload.package_total : activePayload.subtotal));
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

    function applyTourCostChildQty($input, newQty) {
        var key = String($input.attr('data-tour-qty') || '');
        var m = key.match(/^child_(\d+)$/);
        if (!m) {
            return;
        }
        var childIndex = parseInt(m[1], 10);
        var rows = readChildCostRows();
        if (childIndex < 0 || childIndex >= rows.length) {
            return;
        }
        newQty = Math.max(1, parseInt(newQty, 10) || 1);
        rows[childIndex].qty = newQty;
        $input.val(String(newQty));
        qTourCostChildQtySync = true;
        try {
            writeChildCostRows(rows);
            recalcCosts();
        } finally {
            qTourCostChildQtySync = false;
        }
        markTourCostAutoSaved($input.closest('.q-pricing-option-sheet').find('.q-sheet-tour-autosave'));
        saveFormDraftToStorage();
    }

    function applyTourCostChildAge($input, newAge) {
        var key = String($input.attr('data-tour-age') || '');
        var m = key.match(/^child_(\d+)$/);
        if (!m) {
            return;
        }
        var childIndex = parseInt(m[1], 10);
        var rows = readChildCostRows();
        if (childIndex < 0 || childIndex >= rows.length) {
            return;
        }
        newAge = parseInt(newAge, 10);
        if (isNaN(newAge) || newAge < 0) {
            newAge = 0;
        }
        if (newAge > 17) {
            newAge = 17;
        }
        rows[childIndex].age = newAge;
        if (newAge > 0) {
            $input.val(String(newAge));
        }
        qTourCostChildQtySync = true;
        try {
            writeChildCostRows(rows, { skipChildrenField: true });
        } finally {
            qTourCostChildQtySync = false;
        }
        markTourCostAutoSaved($input.closest('.q-pricing-option-sheet').find('.q-sheet-tour-autosave'));
        saveFormDraftToStorage();
    }

    $(document).on('change input', '.q-sheet-tour-cost-rows .q-tour-qty-input[data-tour-qty="adult"], #qTourCostRows .q-tour-qty-input[data-tour-qty="adult"]', function () {
        var n = parseInt($(this).val(), 10);
        if (isNaN(n) || n < 1) n = 1;
        $(this).val(String(n));
        $('#q_adults').val(n).trigger('change');
    });

    // Child qty: allow empty while typing — never delete the row (use × to remove).
    $(document).on('input', '.q-sheet-tour-cost-rows .q-tour-qty-input[data-tour-qty^="child_"], #qTourCostRows .q-tour-qty-input[data-tour-qty^="child_"]', function () {
        var raw = String($(this).val() == null ? '' : $(this).val()).trim();
        if (raw === '') {
            return;
        }
        var newQty = parseInt(raw, 10);
        if (isNaN(newQty) || newQty < 1) {
            return;
        }
        applyTourCostChildQty($(this), newQty);
    });

    $(document).on('blur', '.q-sheet-tour-cost-rows .q-tour-qty-input[data-tour-qty^="child_"], #qTourCostRows .q-tour-qty-input[data-tour-qty^="child_"]', function () {
        var $input = $(this);
        var key = String($input.attr('data-tour-qty') || '');
        var m = key.match(/^child_(\d+)$/);
        if (!m) {
            return;
        }
        var childIndex = parseInt(m[1], 10);
        var raw = String($input.val() == null ? '' : $input.val()).trim();
        var newQty = parseInt(raw, 10);
        if (raw === '' || isNaN(newQty) || newQty < 1) {
            var rows = readChildCostRows();
            newQty = (rows[childIndex] && rows[childIndex].qty) ? rows[childIndex].qty : 1;
            if (isNaN(newQty) || newQty < 1) {
                newQty = 1;
            }
            $input.val(String(newQty));
        }
        applyTourCostChildQty($input, newQty);
    });

    $(document).on('input change', '.q-sheet-tour-cost-rows .q-tour-age-input, #qTourCostRows .q-tour-age-input', function () {
        var raw = String($(this).val() == null ? '' : $(this).val()).trim();
        if (raw === '') {
            return;
        }
        var age = parseInt(raw, 10);
        if (isNaN(age) || age < 0) {
            return;
        }
        applyTourCostChildAge($(this), age);
    });

    $(document).on('blur', '.q-sheet-tour-cost-rows .q-tour-age-input, #qTourCostRows .q-tour-age-input', function () {
        var $input = $(this);
        var key = String($input.attr('data-tour-age') || '');
        var m = key.match(/^child_(\d+)$/);
        if (!m) {
            return;
        }
        var childIndex = parseInt(m[1], 10);
        var raw = String($input.val() == null ? '' : $input.val()).trim();
        var age = parseInt(raw, 10);
        if (raw === '' || isNaN(age) || age < 0) {
            var rows = readChildCostRows();
            age = (rows[childIndex] && rows[childIndex].age != null) ? rows[childIndex].age : 0;
            if (!isNaN(age) && age > 0) {
                $input.val(String(age));
            } else {
                $input.val('');
                age = 0;
            }
        }
        applyTourCostChildAge($input, age);
    });

    $(document).on('click', '.q-tour-child-remove', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var idx = $(this).attr('data-child-index');
        if (idx == null || idx === '') {
            idx = $(this).closest('.q-tour-cost-row').attr('data-child-index');
        }
        removeTourCostChildAtIndex(idx);
    });

    $(document).on('change input', '#q_adults', function () {
        if (qTourCostChildQtySync) {
            return;
        }
        snapshotTourCostFromDom();
        renderTourCostRows();
        recalcCosts();
    });

    $(document).on('change input', '#q_children', function () {
        if (qTourCostChildQtySync) {
            return;
        }
        snapshotTourCostFromDom();
        syncChildRowsToGuestChildrenCount();
        renderTourCostRows();
        recalcCosts();
    });

    $(document).on('change', '#q_hide_gst_note', function () {
        syncTourCostOptUiFromMasters();
        snapshotTourCostFromDom();
        renderTourCostRows();
        recalcAllTourCostCards();
    });

    $(document).on('change', '.q-tour-opt-without-itinerary', function () {
        var checked = $(this).is(':checked');
        $('#q_without_itinerary').prop('checked', checked);
        syncTourCostOptUiFromMasters();
    });

    $(document).on('change', '.q-tour-opt-hide-gst', function () {
        var checked = $(this).is(':checked');
        $('#q_hide_gst_note').prop('checked', checked);
        syncTourCostOptUiFromMasters();
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
        var headerText = String($('#q_header_text').val() || '').trim();
        if (costSheetObj && typeof costSheetObj === 'object') {
            costSheetObj.header_text = headerText;
        }

        return {
            id: $('#q_id').val() || '',
            lead_id: $('#q_lead_id').val() || '',
            edit_from_version: $('#q_edit_from_version').val() || '',
            guest_name: $('[name=guest_name]').val(),
            reference_name: $('[name=reference_name]').val(),
            mobile_no: $('[name=mobile_no]').val(),
            email: $('[name=email]').val(),
            destination: $('[name=destination]').val(),
            header_text: headerText,
            tentative_date: normalizeLegacyDateInput($('#q_tentative_date').val()),
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
    var previewHotelPillTimer = null;
    var previewDirty = false;

    function isPreviewOnlyMode() {
        return !!(document.body && document.body.classList.contains('q-preview-only'))
            || /[?&]preview_only=1(?:&|$)/.test(window.location.search || '');
    }

    function notifyParentPreviewState(extra) {
        try {
            if (!window.parent || window.parent === window) {
                return;
            }
            var payload = $.extend({
                type: 'mz-quotation-preview-state',
                dirty: previewDirty
            }, extra || {});
            window.parent.postMessage(payload, '*');
        } catch (e) { /* ignore */ }
    }

    function setPreviewDirty(isDirty) {
        previewDirty = !!isDirty;
        var $btn = $('#qPreviewSaveBtn');
        var $hint = $('#qPreviewUnsavedHint');
        if ($btn.length) {
            if (previewDirty) {
                $btn.removeClass('d-none').prop('disabled', false);
                $hint.removeClass('d-none');
            } else {
                $btn.addClass('d-none').prop('disabled', true);
                $hint.addClass('d-none');
            }
        }
        notifyParentPreviewState();
    }

    function previewVal(v, fallback) {
        var s = v == null ? '' : String(v).trim();
        return s !== '' ? s : (fallback || '—');
    }

    function previewHasHtmlContent(html) {
        if (!html) return false;
        return String(html).replace(/<[^>]*>/g, '').replace(/&nbsp;/gi, ' ').trim() !== '';
    }

    function qpTermsItemHtml(innerHtml) {
        return '<li class="qp-terms-item">' +
            '<span class="qp-terms-check" contenteditable="false" aria-hidden="true"><i class="fas fa-check"></i></span>' +
            '<div class="qp-terms-item-body">' + innerHtml + '</div>' +
            '</li>';
    }

    function qpFormatTermsChecklistHtml(html) {
        var raw = String(html || '').trim();
        if (!raw) {
            return '<ul class="qp-terms-check-list">' + qpTermsItemHtml('<br>') + '</ul>';
        }
        var $wrap = $('<div>').html(raw);
        var items = [];

        $wrap.find('li').each(function () {
            var $li = $(this);
            if ($li.parents('li').length) {
                return;
            }
            var $clone = $li.clone();
            $clone.find('.qp-terms-check').remove();
            var $body = $clone.children('.qp-terms-item-body').first();
            var inner = $body.length ? $body.html() : $clone.html();
            if (String($clone.text() || '').replace(/\u00a0/g, ' ').trim() === '' && !$clone.find('img').length) {
                return;
            }
            items.push(inner);
        });

        if (!items.length) {
            $wrap.children('p, div').each(function () {
                var $el = $(this);
                if ($el.is('ul, ol, .qp-terms-check-list')) {
                    return;
                }
                var inner = $el.html();
                if (String($el.text() || '').replace(/\u00a0/g, ' ').trim() === '' && !$el.find('img').length) {
                    return;
                }
                items.push(inner);
            });
        }

        if (!items.length) {
            var chunks = String($wrap.html() || '').split(/<br\s*\/?>/i);
            chunks.forEach(function (chunk) {
                var text = $('<div>').html(chunk).text().replace(/\u00a0/g, ' ').trim();
                if (text) {
                    items.push($('<div>').text(text).html());
                }
            });
        }

        if (!items.length) {
            items.push(raw);
        }

        var out = '<ul class="qp-terms-check-list">';
        items.forEach(function (item) {
            out += qpTermsItemHtml(item);
        });
        out += '</ul>';
        return out;
    }

    function qpCleanTermsChecklistHtml(html) {
        var $wrap = $('<div>').html(String(html || ''));
        $wrap.find('.qp-terms-check').remove();
        if ($wrap.find('.qp-terms-check-list, .qp-terms-item').length) {
            var items = [];
            $wrap.find('.qp-terms-item').each(function () {
                var $li = $(this);
                var $body = $li.children('.qp-terms-item-body').first();
                var inner = $body.length ? $body.html() : $li.html();
                if (String($('<div>').html(inner).text() || '').replace(/\u00a0/g, ' ').trim() === '' &&
                    !$('<div>').html(inner).find('img').length) {
                    return;
                }
                items.push('<li>' + inner + '</li>');
            });
            if (items.length) {
                return '<ul>' + items.join('') + '</ul>';
            }
        }
        return $wrap.html();
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

    function qpHotelSectionHead(title) {
        title = title || 'Hotel Details';
        return '<div class="qp-hotel-sec-head">' +
            '<div class="qp-hotel-sec-top">' +
            '<div class="qp-hotel-sec-left">' +
            '<span class="qp-hotel-sec-icon" aria-hidden="true"><i class="fas fa-bed"></i></span>' +
            '<span class="qp-hotel-sec-title">' + esc(title) + '</span>' +
            '</div>' +
            '<div class="qp-hotel-sec-rule" aria-hidden="true"></div>' +
            '<div class="qp-hotel-sec-slogan">' +
            'YOUR JOURNEY <span class="qp-hotel-slogan-dot">•</span> ' +
            'OUR CARE <span class="qp-hotel-slogan-dot">•</span> ' +
            'MEMORABLE STAYS' +
            '</div>' +
            '</div>' +
            '</div>';
    }

    function qpParseHotelStarCount(starCategory) {
        var raw = String(starCategory || '').trim();
        if (!raw) {
            return 0;
        }
        var m = raw.match(/(\d+(?:\.\d+)?)/);
        if (!m) {
            return 0;
        }
        var n = Math.round(parseFloat(m[1]));
        if (isNaN(n) || n <= 0) {
            return 0;
        }
        return Math.min(5, n);
    }

    function qpHotelStarsHtml(starCategory) {
        var count = qpParseHotelStarCount(starCategory);
        if (count <= 0) {
            return '';
        }
        var html = '<div class="qp-hotel-stars" aria-label="' + count + ' star">';
        for (var i = 0; i < count; i++) {
            html += '<i class="fas fa-star" aria-hidden="true"></i>';
        }
        html += '</div>';
        return html;
    }

    function qpHotelMealCode(mealPlan) {
        var meal = String(mealPlan || '').trim();
        if (!meal) {
            return '';
        }
        var codeMatch = meal.match(/^\s*(EP|CP|MAP|AP|AI)\b/i);
        if (codeMatch) {
            return codeMatch[1].toUpperCase();
        }
        var key = meal.toLowerCase().replace(/\./g, '').replace(/\s+/g, ' ');
        if (/^(ep|european|ro|room only|none|-|nil|n\/a)(\b|$)/i.test(key)) {
            return 'EP';
        }
        if (/^(cp|continental)(\b|$)/i.test(key) || /^breakfast$/i.test(key)) {
            return 'CP';
        }
        if (/^(map|modified american|half board)(\b|$)/i.test(key) || /breakfast\s*&\s*dinner|bf\s*\+\s*d/i.test(key)) {
            return 'MAP';
        }
        if (/^(ap|american plan|full board)(\b|$)/i.test(key) || /all meals|bf\s*\+\s*l\s*\+\s*d/i.test(key)) {
            return 'AP';
        }
        if (/^(ai|all inclusive)(\b|$)/i.test(key)) {
            return 'AI';
        }
        if (/^(breakfast\s*&\s*dinner)$/i.test(key)) {
            return 'MAP';
        }
        if (/^(all meals)$/i.test(key)) {
            return 'AP';
        }
        if (/^[a-z]{1,4}$/i.test(meal)) {
            return meal.toUpperCase();
        }
        return meal;
    }

    function qpHotelMealLabels(mealPlan, roomType) {
        var meal = String(mealPlan || '').trim();
        var room = String(roomType || '').trim();
        var code = qpHotelMealCode(meal);
        var key = code.toLowerCase();
        var roomOnly = !meal || key === 'ep';
        if (roomOnly) {
            return {
                roomPrimary: 'Room Only',
                roomSecondary: room,
                meals: 'None',
                code: code || 'EP'
            };
        }
        var mealMap = {
            cp: 'Breakfast',
            map: 'Breakfast & Dinner',
            ap: 'All Meals',
            ai: 'All Inclusive'
        };
        return {
            roomPrimary: room || '—',
            roomSecondary: '',
            meals: mealMap[key] || meal,
            code: code || meal
        };
    }

    function qpHotelMealLegendForm(mealPlan) {
        var code = qpHotelMealCode(mealPlan);
        if (!code) {
            return '';
        }
        var legendMap = {
            EP: 'No Meals',
            CP: 'Breakfast',
            MAP: 'Breakfast & Dinner',
            AP: 'Breakfast, Lunch & Dinner',
            AI: 'All Inclusive'
        };
        var upper = String(code).toUpperCase();
        return legendMap[upper] || '';
    }

    function buildPreviewMealPlanLegendHtml(hotelsData) {
        var seen = {};
        var items = [];
        var cats = (hotelsData && hotelsData.categories) ? hotelsData.categories : [];
        cats.forEach(function (cat) {
            (cat.hotels || []).forEach(function (h) {
                var d = normalizeHotelData(h);
                var code = qpHotelMealCode(d.meal_plan);
                if (!code) {
                    return;
                }
                var key = String(code).toUpperCase();
                if (seen[key]) {
                    return;
                }
                seen[key] = true;
                var mealName = qpHotelMealLegendForm(code);
                items.push({
                    code: key,
                    name: mealName || key
                });
            });
        });
        if (!items.length) {
            return '';
        }
        var parts = items.map(function (it) {
            return '<span class="qp-meal-legend-item"><strong>' + esc(it.name) + '</strong></span>';
        });
        return '<div class="qp-meal-legend">' +
            '<span class="qp-meal-legend-label">Meal Plan:</span> ' +
            parts.join('<span class="qp-meal-legend-dot"> • </span>') +
            '</div>';
    }

    function qpHotelOptionsTabsHtml(categories, activeIdx) {
        categories = categories || [];
        if (categories.length <= 1) {
            return '';
        }
        var html = '<div class="qp-hotel-option-tabs">';
        categories.forEach(function (cat, i) {
            var label = cat.label || defaultHotelCategoryLabel(i);
            html += '<span class="qp-hotel-option-tab' + (i === activeIdx ? ' is-active' : '') + '">' +
                esc(label) + '</span>';
        });
        html += '</div>';
        return html;
    }

    function buildPreviewHotelCardsHtml(hotels, catIdx) {
        hotels = hotels || [];
        if (!hotels.length) {
            return '';
        }

        function qpHotelValueCell(valueHtml, extraClass) {
            return '<div class="qp-hotel-field' + (extraClass ? ' ' + extraClass : '') + '">' +
                '<div class="qp-hotel-field-value">' + valueHtml + '</div>' +
                '</div>';
        }

        function qpHotelHeadCell(label, extraClass, iconFa) {
            var iconHtml = iconFa
                ? ('<i class="' + esc(iconFa) + ' qp-hotel-ico" aria-hidden="true"></i>')
                : '';
            return '<div class="qp-hotel-field' + (extraClass ? ' ' + extraClass : '') + '">' +
                iconHtml + '<span>' + esc(label) + '</span>' +
                '</div>';
        }

        var html = '<div class="qp-hotel-panel">';
        html += '<div class="qp-hotel-col-head" aria-hidden="true">' +
            qpHotelHeadCell('City', 'qp-hotel-field-city', 'fas fa-map-marker-alt') +
            qpHotelHeadCell('Hotel', 'qp-hotel-field-hotel') +
            qpHotelHeadCell('Nts', 'qp-hotel-field-nights', 'fas fa-moon') +
            qpHotelHeadCell('Room Type', 'qp-hotel-field-room', 'fas fa-bed') +
            qpHotelHeadCell('Check-In', 'qp-hotel-field-date', 'far fa-calendar-alt') +
            qpHotelHeadCell('Check-Out', 'qp-hotel-field-date', 'far fa-calendar-alt') +
            qpHotelHeadCell('Meals', 'qp-hotel-field-meals', 'fas fa-utensils') +
            '</div>';
        html += '<div class="qp-hotel-body">';

        hotels.forEach(function (h, hi) {
            var d = normalizeHotelData(h);
            var base = 'hotel.' + catIdx + '.' + hi + '.';
            var mealInfo = qpHotelMealLabels(d.meal_plan, d.room_type);
            var mealCode = qpHotelMealCode(d.meal_plan) || mealInfo.code || '';
            var cityText = String(d.city || '').trim();
            var starsHtml = qpHotelStarsHtml(d.star_category);
            var roomsNum = parseInt(d.rooms, 10);
            var roomsPad = (!isNaN(roomsNum) && roomsNum >= 0) ? pad2(roomsNum) : (d.rooms !== '' && d.rooms != null ? String(d.rooms) : '00');
            var roomTypeText = '';
            if (mealInfo.roomPrimary === 'Room Only') {
                roomTypeText = d.room_type ? String(d.room_type) : 'Room Only';
            } else {
                roomTypeText = String(d.room_type || mealInfo.roomPrimary || '—');
            }

            var cityValueHtml = '<div class="qp-hotel-primary qp-hotel-city-name">' +
                previewEditable(previewVal(cityText).toUpperCase(), base + 'city', { cls: 'q-preview-cell-edit' }) +
                '</div>';

            var roomCombinedHtml =
                '<div class="qp-hotel-room-combined">' +
                '<span class="qp-hotel-rooms-count">' +
                previewEditable(roomsPad, base + 'rooms', { type: 'int', cls: 'q-preview-cell-edit' }) +
                '</span>' +
                '<span class="qp-hotel-room-type-text">' +
                previewEditable(previewVal(roomTypeText), base + 'room_type', { cls: 'q-preview-cell-edit' }) +
                '</span>' +
                '</div>';

            html += '<div class="qp-hotel-row-card">';
            html += '<div class="qp-hotel-row-fields">';
            html += qpHotelValueCell(cityValueHtml, 'qp-hotel-field-city');
            html += qpHotelValueCell(
                '<div class="qp-hotel-primary">' +
                previewEditable(previewVal(d.name), base + 'name', { cls: 'q-preview-cell-edit' }) +
                '</div>' + starsHtml,
                'qp-hotel-field-hotel'
            );
            html += qpHotelValueCell(
                '<div class="qp-hotel-primary">' +
                previewEditable(previewVal(d.nights, '0'), base + 'nights', { type: 'int', cls: 'q-preview-cell-edit' }) +
                '</div>',
                'qp-hotel-field-nights'
            );
            html += qpHotelValueCell(roomCombinedHtml, 'qp-hotel-field-room');
            html += qpHotelValueCell(
                '<div class="qp-hotel-primary">' +
                previewEditable(formatPreviewFlightDate(d.checkin), base + 'checkin', { type: 'date', cls: 'q-preview-cell-edit' }) +
                '</div>',
                'qp-hotel-field-date'
            );
            html += qpHotelValueCell(
                '<div class="qp-hotel-primary">' +
                previewEditable(formatPreviewFlightDate(d.checkout), base + 'checkout', { type: 'date', cls: 'q-preview-cell-edit' }) +
                '</div>',
                'qp-hotel-field-date'
            );
            html += qpHotelValueCell(
                '<div class="qp-hotel-primary">' +
                previewEditable(previewVal(mealCode, '—'), base + 'meal_plan', { cls: 'q-preview-cell-edit' }) +
                '</div>',
                'qp-hotel-field-meals'
            );
            html += '</div>';
            html += '</div>';
        });

        html += '</div></div>';
        return html;
    }

    function qpFlightPlaneSvg(cls, size, orient) {
        size = size || 14;
        orient = orient || 'right';
        var transform = orient === 'right' ? ' transform="rotate(90 12 12)"' : '';
        return '<svg class="' + (cls || '') + '" viewBox="0 0 24 24" width="' + size + '" height="' + size + '" aria-hidden="true" focusable="false">' +
            '<path fill="currentColor" d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"' + transform + '/>' +
            '</svg>';
    }

    function qpFlightSectionHead() {
        return '<div class="qp-flight-sec-head">' +
            '<div class="qp-flight-sec-top">' +
            '<div class="qp-flight-sec-left">' +
            '<span class="qp-flight-sec-icon" aria-hidden="true">' + qpFlightPlaneSvg('qp-flight-sec-icon-svg', 15, 'up') + '</span>' +
            '<span class="qp-flight-sec-title">Flight Details</span>' +
            '</div>' +
            '<div class="qp-flight-sec-rule" aria-hidden="true"></div>' +
            '<div class="qp-flight-sec-slogan-wrap">' +
            '<div class="qp-flight-sec-slogan">TAILORED FOR A HIGHER TOMORROW</div>' +
            '</div>' +
            '</div>' +
            '</div>';
    }

    function qpParseFlightPlace(place) {
        place = String(place || '').trim();
        if (!place) {
            return { city: '—', code: '—' };
        }
        var m = place.match(/^(.+?)\s*\(([A-Za-z]{3})\)\s*$/);
        if (m) {
            return { city: m[1].trim(), code: m[2].toUpperCase() };
        }
        m = place.match(/^([A-Za-z]{3})$/);
        if (m) {
            return { city: m[1].toUpperCase(), code: m[1].toUpperCase() };
        }
        m = place.match(/\(([A-Za-z]{3})\)/);
        if (m) {
            return {
                city: place.replace(/\s*\([A-Za-z]{3}\)\s*$/, '').trim() || m[1].toUpperCase(),
                code: m[1].toUpperCase()
            };
        }
        var codeGuess = place.replace(/[^A-Za-z]/g, '').slice(0, 3).toUpperCase();
        return { city: place, code: codeGuess || place.slice(0, 3).toUpperCase() };
    }

    function qpFormatTime12(timeStr) {
        var raw = String(timeStr || '').trim();
        if (!raw) {
            return '—';
        }
        var m = raw.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/i);
        if (!m) {
            return raw;
        }
        var h = parseInt(m[1], 10);
        var min = m[2];
        var ampm = (m[3] || '').toUpperCase();
        if (!ampm) {
            ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            if (h === 0) {
                h = 12;
            }
        } else {
            h = h % 12;
            if (h === 0) {
                h = 12;
            }
        }
        return h + ':' + min + ' ' + ampm;
    }

    function qpFormatFlightDayDate(dateStr) {
        var d = parsePreviewDate(dateStr);
        if (!d) {
            return '—';
        }
        var days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return days[d.getDay()] + ', ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    }

    function qpFlightAirlineName(d) {
        return String((d && d.name) || '').trim();
    }

    function qpFlightNumber(d) {
        return String((d && (d.fl_tr_no || d.pnr || d.fno)) || '').trim();
    }

    function qpFlightOperatedBy(name) {
        name = String(name || '').trim();
        if (!name) {
            return '';
        }
        if (/airline/i.test(name)) {
            return 'Operated by ' + name;
        }
        return 'Operated by ' + name + ' Airlines';
    }

    function qpFormatDurationShort(totalMinutes) {
        var mins = parseInt(totalMinutes, 10);
        if (isNaN(mins) || mins <= 0) {
            return '';
        }
        var h = Math.floor(mins / 60);
        var m = mins % 60;
        if (h > 0 && m > 0) {
            return h + 'h ' + m + 'm';
        }
        if (h > 0) {
            return h + 'h';
        }
        return m + 'm';
    }

    function qpFormatLayoverBadge(totalMinutes, fallback) {
        var mins = parseInt(totalMinutes, 10);
        if (!isNaN(mins) && mins > 0) {
            var h = Math.floor(mins / 60);
            var m = mins % 60;
            var parts = [];
            if (h > 0) {
                parts.push(String(h).padStart(2, '0') + ' Hr');
            }
            if (m > 0 || h === 0) {
                parts.push(String(m).padStart(2, '0') + ' Min');
            }
            return parts.join(' ');
        }
        return String(fallback || '').trim();
    }

    function qpCalcSegmentTravelMinutes(seg) {
        seg = seg || {};
        var start = parseFlightDateTimeMoment(seg.dep_date, seg.dep_time);
        var end = parseFlightDateTimeMoment(seg.arr_date || seg.dep_date, seg.arr_time || seg.dep_time);
        if (!start || !end) {
            return 0;
        }
        var mins = end.diff(start, 'minutes');
        if (mins < 0) {
            mins += 24 * 60;
        }
        return mins > 0 ? mins : 0;
    }

    function qpCalcJourneyTravelTime(rows) {
        if (!rows || !rows.length) {
            return '';
        }
        var first = rows[0];
        var last = rows[rows.length - 1];
        var start = parseFlightDateTimeMoment(first.dep_date, first.dep_time);
        var end = parseFlightDateTimeMoment(last.arr_date || last.dep_date, last.arr_time || last.dep_time);
        if (!start || !end) {
            return '';
        }
        var mins = end.diff(start, 'minutes');
        if (mins < 0) {
            mins += 24 * 60;
        }
        return qpFormatDurationShort(mins);
    }

    function qpFlightPlaceLine(place) {
        var p = qpParseFlightPlace(place);
        if (p.city === '—' && p.code === '—') {
            return '—';
        }
        if (p.code && p.code !== '—' && p.city && p.city !== '—') {
            return p.city + ' (' + p.code + ')';
        }
        return p.city || p.code || '—';
    }

    function qpFlightEndpointBlockHtml(label, place, dateStr, timeStr, editPath) {
        return '<div class="qp-flight-endpoint">' +
            '<div class="qp-flight-loc-lbl">' + esc(label) + '</div>' +
            '<div class="qp-flight-endpoint-body">' +
            '<div class="qp-flight-endpoint-meta">' +
            '<div class="qp-flight-loc-city">' + esc(qpFlightPlaceLine(place)) + '</div>' +
            '<div class="qp-flight-sched-date">' + esc(qpFormatFlightDayDate(dateStr)) + '</div>' +
            '</div>' +
            '<div class="qp-flight-sched-time">' +
            previewEditable(qpFormatTime12(timeStr), editPath, {
                type: 'datetime',
                cls: 'q-preview-cell-edit qp-flight-time-edit'
            }) +
            '</div>' +
            '</div>' +
            '</div>';
    }

    function qpFlightLayoverHtml(prev, cur) {
        var mins = calcLayoverMinutesBetweenData(prev, cur);
        var duration = qpFormatLayoverBadge(mins, cur && cur.layover_time);
        if (!duration) {
            return '';
        }
        return '<div class="qp-flight-layover-wrap">' +
            '<div class="qp-flight-layover-line" aria-hidden="true"></div>' +
            '<div class="qp-flight-layover">' +
            '<i class="far fa-clock" aria-hidden="true"></i>' +
            '<span class="qp-flight-layover-text">Layover <span class="qp-flight-layover-dot">•</span> ' +
            esc(duration) + '</span>' +
            '</div>' +
            '</div>';
    }

    function qpFlightSegmentCardHtml(seg, _segIndex, flatIndex) {
        seg = normalizeFlightData(seg);
        var airline = qpFlightAirlineName(seg);
        var flightNo = qpFlightNumber(seg);
        var operated = qpFlightOperatedBy(airline);
        var durMins = qpCalcSegmentTravelMinutes(seg);
        var duration = qpFormatDurationShort(durMins);
        var labelPath = 'flight.' + flatIndex + '.flight_label';
        var displayAirline = airline || 'Flight';
        var displayNo = flightNo || '—';

        var html = '<div class="qp-flight-seg-card">';
        html += '<div class="qp-flight-seg-accent" aria-hidden="true"></div>';
        html += '<div class="qp-flight-seg-inner">';
        html += '<div class="qp-flight-seg-row">';

        html += '<div class="qp-flight-airline-col">';
        html += '<div class="qp-flight-airline-name">' +
            '<span class="qp-flight-airline-text">' +
            previewEditable(displayAirline, labelPath, {
                type: 'flight_label',
                cls: 'q-preview-cell-edit qp-flight-airline-edit'
            }) +
            '</span>' +
            qpFlightPlaneSvg('qp-flight-airline-plane', 12, 'right') +
            '</div>';
        html += '<div class="qp-flight-no">' +
            previewEditable(displayNo, 'flight.' + flatIndex + '.fl_tr_no', {
                type: 'text',
                cls: 'q-preview-cell-edit qp-flight-no-edit'
            }) +
            '</div>';
        if (operated) {
            html += '<div class="qp-flight-operated">' + esc(operated) + '</div>';
        }
        if (seg.hand_baggage || seg.checkin_baggage) {
            html += '<div class="qp-flight-baggage">';
            if (seg.hand_baggage) {
                html += '<span><i class="fas fa-briefcase" aria-hidden="true"></i> Hand ' +
                    previewEditable(seg.hand_baggage, 'flight.' + flatIndex + '.hand_baggage', {
                        type: 'text',
                        cls: 'q-preview-cell-edit'
                    }) + '</span>';
            }
            if (seg.checkin_baggage) {
                html += '<span><i class="fas fa-suitcase" aria-hidden="true"></i> Check-in ' +
                    previewEditable(seg.checkin_baggage, 'flight.' + flatIndex + '.checkin_baggage', {
                        type: 'text',
                        cls: 'q-preview-cell-edit'
                    }) + '</span>';
            }
            html += '</div>';
        }
        html += '</div>';

        html += qpFlightEndpointBlockHtml(
            'From',
            seg.from,
            seg.dep_date,
            seg.dep_time,
            'flight.' + flatIndex + '.dep_datetime'
        );

        html += '<div class="qp-flight-mid-col" aria-hidden="true">';
        html += '<div class="qp-flight-dur">' +
            '<i class="far fa-clock" aria-hidden="true"></i>' +
            '<span>' + esc(duration || '—') + '</span>' +
            '</div>';
        html += '<div class="qp-flight-path">' +
            '<span class="qp-flight-path-line"></span>' +
            qpFlightPlaneSvg('qp-flight-path-plane', 12, 'right') +
            '<span class="qp-flight-path-line"></span>' +
            '</div>';
        html += '<div class="qp-flight-stop-pill">Non-stop</div>';
        html += '</div>';

        html += qpFlightEndpointBlockHtml(
            'To',
            seg.to,
            seg.arr_date || seg.dep_date,
            seg.arr_time,
            'flight.' + flatIndex + '.arr_datetime'
        );

        html += '</div></div></div>';
        return html;
    }

    function buildPreviewFlightCardsHtml(flights) {
        var groups = groupFlightsForDisplay(flights || []);
        if (!groups.length) {
            return '';
        }
        var flatIndex = 0;
        var html = '<div class="qp-flight-cards">';
        groups.forEach(function (group) {
            var rows = (group.rows || []).map(normalizeFlightData);
            if (!rows.length) {
                return;
            }
            html += '<div class="qp-flight-journey">';
            rows.forEach(function (seg, si) {
                if (si > 0) {
                    html += qpFlightLayoverHtml(rows[si - 1], seg);
                }
                html += qpFlightSegmentCardHtml(seg, si, flatIndex);
                flatIndex += 1;
            });
            html += '</div>';
        });
        html += '</div>';
        return html;
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
            dateDash: String(d.getDate()).padStart(2, '0') + '/' +
                String(d.getMonth() + 1).padStart(2, '0') + '/' +
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
            if ($el.hasClass('qp-terms-rich') || $el.find('.qp-terms-check-list, .qp-terms-check').length) {
                raw = qpCleanTermsChecklistHtml(raw);
            }
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
            date: formatDisplayDate(toIsoDateFromPreview(datePart) || datePart) || datePart,
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
            if ($input.hasClass('js-q-date-input')) {
                setDateInputValue($input, val);
            } else {
                $input.val(val);
            }
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
                if (labelParsed.fl_tr_no) {
                    setInput($fRow.find('.f-fl-no'), labelParsed.fl_tr_no);
                }
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
                    hand_baggage: '.f-hand-bag',
                    checkin_baggage: '.f-checkin-bag',
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
                rooms: '.h-rooms',
                room_type: '.h-room',
                checkin: '.h-checkin',
                checkout: '.h-checkout',
                meal_plan: '.h-meal',
                rate: '.h-rate',
                supplier: '.h-supplier'
            };
            var hSel = hMap[hField];
            if (!hSel) return;
            if (hField === 'checkin' || hField === 'checkout') {
                value = toIsoDateFromPreview(value) || value;
            }
            if (hField === 'rate') {
                value = String(value || '').replace(/INR/gi, '').replace(/[₹,\s]/g, '');
            }
            if (hField === 'supplier') {
                var $sup = $hRow.find('.h-supplier');
                if ($sup.length) {
                    var matchedSup = false;
                    $sup.find('option').each(function () {
                        var t = String($(this).attr('data-name') || $(this).text() || '').trim();
                        if (t.toLowerCase() === String(value || '').trim().toLowerCase()) {
                            $sup.val($(this).attr('value')).trigger('change');
                            matchedSup = true;
                            return false;
                        }
                    });
                    if (!matchedSup) {
                        // Keep display text editable without forcing unknown option.
                    }
                }
                return;
            }
            setInput($hRow.find(hSel), value);
            if (hField === 'nights') {
                $hRow.find('.h-nights').trigger('change');
            }
            if (hField === 'city' || hField === 'nights') {
                window.setTimeout(function () {
                    if ($('#qPreviewModal').hasClass('show')) {
                        refreshPreviewHotelRoutePills();
                    }
                }, 0);
            }
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
            case 'header_text':
                setInput($('#q_header_text'), value === '—' || value === 'Add heading text' ? '' : value);
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

    function refreshPreviewHotelRoutePills() {
        var $area = $('#qPreviewPrintArea');
        if (!$area.length) {
            return;
        }
        var p;
        try {
            p = collectPayload();
        } catch (err) {
            return;
        }
        var hotelsData = normalizeHotelsPrefill(JSON.parse(p.hotels_json || '[]'));
        var pillsHtml = buildPreviewHotelRoutePillsHtml(
            hotelsData,
            String(p.active_option_id || hotelsData.active_category_id || '')
        );
        var $existing = $area.find('.qp-route-pills');
        if ($existing.length) {
            if (pillsHtml) {
                $existing.replaceWith(pillsHtml);
            } else {
                $existing.remove();
            }
            return;
        }
        if (pillsHtml) {
            var $headTop = $area.find('.qp-doc-head-top').first();
            if ($headTop.length) {
                $headTop.after(pillsHtml);
            } else {
                $area.find('.qp-duration-wrap').first().after(pillsHtml);
            }
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

    function qpFormatDestTitle(dest) {
        dest = String(dest || '').trim();
        if (!dest) {
            return 'DESTINATION';
        }
        return dest.toUpperCase();
    }

    function qpMountainSvg() {
        return '<svg class="qp-mtn-svg" viewBox="0 0 80 40" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
            '<circle cx="62" cy="10" r="7" fill="#f5a623"/>' +
            '<path d="M2 38 L22 12 L32 24 L46 6 L78 38 Z" fill="#c4121a"/>' +
            '<path d="M2 38 L18 18 L28 28 L40 14 L58 38 Z" fill="#e11d2e" opacity="0.85"/>' +
            '</svg>';
    }

    function qpItinMountainSvg() {
        return '<svg class="qp-itin-mtn" viewBox="0 0 120 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' +
            '<circle cx="98" cy="12" r="8" fill="none" stroke="#f3b4b8" stroke-width="1.6"/>' +
            '<path d="M4 44 L32 14 L46 28 L68 6 L116 44" fill="none" stroke="#f0a6ab" stroke-width="1.7" stroke-linejoin="round"/>' +
            '<path d="M14 44 L40 22 L54 34 L76 12 L108 44" fill="none" stroke="#e88990" stroke-width="1.4" stroke-linejoin="round"/>' +
            '</svg>';
    }

    function qpItinSectionHead() {
        return '<div class="qp-itin-head">' +
            '<div class="qp-itin-head-left">' +
            '<span class="qp-itin-vbar" aria-hidden="true"></span>' +
            '<div class="qp-itin-head-copy">' +
            '<div class="qp-itin-title">' +
            '<span class="qp-itin-black">DAY WISE</span> ' +
            '<span class="qp-itin-red">ITINERARY</span>' +
            '</div>' +
            '<div class="qp-itin-rule" aria-hidden="true"><span class="qp-itin-rule-accent"></span></div>' +
            '</div>' +
            '</div>' +
            '<div class="qp-itin-art" aria-hidden="true">' + qpItinMountainSvg() + '</div>' +
            '</div>';
    }

    function qpItinDateLabel(baseStr, offset) {
        var d = parsePreviewDate(baseStr);
        if (!d) {
            return '';
        }
        d.setDate(d.getDate() + offset);
        var dd = String(d.getDate()).padStart(2, '0');
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        return dd + '-' + mm + '-' + d.getFullYear() + ' ' + DAY_NAMES[d.getDay()];
    }

    function qpExtractDayImage(day) {
        var img = String((day && (day.image || day.img || day.image_url)) || '').trim();
        if (img) {
            return absUrl(img);
        }
        var desc = String((day && day.description) || '');
        var m = desc.match(/<img[^>]+src=["']([^"']+)["']/i);
        if (m && m[1]) {
            return absUrl(m[1]);
        }
        return '';
    }

    function qpItinMealLabel(day) {
        var mealVal = String((day && (day.meal || day.meals || day.meal_plan)) || '').trim();
        if (mealVal) {
            return mealVal.replace(/\s*[·|,/]\s*/g, ' + ');
        }
        var text = ((day && day.title) || '') + ' ' + String((day && day.description) || '').replace(/<[^>]*>/g, ' ');
        text = text.toLowerCase();
        var meals = [];
        if (/breakfast|\bbb\b|\bcp\b/.test(text)) meals.push('Breakfast');
        if (/lunch/.test(text)) meals.push('Lunch');
        if (/dinner/.test(text)) meals.push('Dinner');
        return meals.join(' + ');
    }

    function qpItinOvernightLabel(day) {
        var overnight = String((day && (day.overnight || day.overnight_stay || day.stay)) || '').trim();
        if (overnight) {
            return overnight;
        }
        var text = ((day && day.title) || '') + ' ' + String((day && day.description) || '').replace(/<[^>]*>/g, ' ');
        if (/overnight|night stay|stay overnight|hotel stay|check[- ]?in/i.test(text)) {
            return 'Included';
        }
        return '';
    }

    function qpItinPillsHtml(day) {
        var overnight = qpItinOvernightLabel(day);
        var meal = qpItinMealLabel(day);
        if (!overnight && !meal) {
            return '';
        }
        var html = '<div class="qp-day-pills">';
        if (overnight) {
            html += '<span class="qp-pill qp-pill-overnight">' +
                '<i class="fas fa-bed" aria-hidden="true"></i>' +
                '<span class="qp-pill-text">Overnight Stay: <strong>' + esc(overnight) + '</strong></span>' +
                '</span>';
        }
        if (overnight && meal) {
            html += '<span class="qp-day-pill-sep" aria-hidden="true"></span>';
        }
        if (meal) {
            html += '<span class="qp-pill qp-pill-meal">' +
                '<i class="fas fa-utensils" aria-hidden="true"></i>' +
                '<span class="qp-pill-text">Meal: <strong>' + esc(meal) + '</strong></span>' +
                '</span>';
        }
        html += '</div>';
        return html;
    }

    function qpAccreditationsPlaneSvg() {
        return '<svg class="qp-acc-plane" viewBox="0 0 120 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' +
            '<path d="M8 36 C28 28, 48 18, 72 14 S108 10, 114 8" fill="none" stroke="#e11d2e" stroke-width="1.4" stroke-dasharray="2.2 3.2" stroke-linecap="round"/>' +
            '<g transform="translate(102 2) rotate(28)">' +
            '<path fill="#9ca3af" d="M2 10 L14 6 L16 8 L8 12 L14 14 L6 16 L4 12 L2 14 Z"/>' +
            '</g>' +
            '</svg>';
    }

    function buildPreviewAccreditationsHtml() {
        var stats = [
            { tone: 'red', icon: 'fas fa-suitcase-rolling', value: '1800+', label: 'Trips Sold' },
            { tone: 'gold', icon: 'fas fa-users', value: '6400+', label: 'Happy Travellers' },
            { tone: 'blue', icon: 'fas fa-map-marker-alt', value: '50+', label: 'Destinations Covered' },
            { tone: 'green', icon: 'fas fa-flag', value: '24', label: 'Group Tours' },
            { tone: 'maroon', icon: 'fas fa-award', value: '19+', label: 'Years of Experience' }
        ];

        var html = '<div class="qp-sec qp-sec-acc">';
        html += '<div class="qp-acc-wrap">';
        html += '<div class="qp-acc-head">' +
            '<div class="qp-acc-eyebrow"><span class="qp-acc-eyebrow-line" aria-hidden="true"></span>' +
            '<span class="qp-acc-eyebrow-text">OUR JOURNEY SO FAR</span>' +
            '<span class="qp-acc-eyebrow-line" aria-hidden="true"></span></div>' +
            '<div class="qp-acc-title">Adrenaliverse Live: <span class="qp-acc-title-accent">Journeys In Motion</span></div>' +
            '<div class="qp-acc-sub">See where the world is travelling right now.</div>' +
            '</div>';
        html += '<div class="qp-acc-grid">';
        stats.forEach(function (item) {
            html += '<div class="qp-acc-card tone-' + esc(item.tone) + '">' +
                '<div class="qp-acc-ico" aria-hidden="true"><i class="' + esc(item.icon) + '"></i></div>' +
                '<div class="qp-acc-value">' + esc(item.value) + '</div>' +
                '<div class="qp-acc-label">' + esc(item.label) + '</div>' +
                '<div class="qp-acc-card-rule" aria-hidden="true"></div>' +
                '</div>';
        });
        html += '</div></div></div>';
        return html;
    }

    function qpGoogleLogoSvg(size) {
        size = size || 18;
        return '<svg class="qp-rev-google-logo" viewBox="0 0 24 24" width="' + size + '" height="' + size + '" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">' +
            '<path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>' +
            '<path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>' +
            '<path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>' +
            '<path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>' +
            '</svg>';
    }

    function qpReviewStarsHtml(count) {
        count = count || 5;
        var html = '<div class="qp-rev-stars" aria-hidden="true">';
        for (var i = 0; i < count; i++) {
            html += '<i class="fas fa-star"></i>';
        }
        html += '</div>';
        return html;
    }

    function qpRatingStarsHtml(rating) {
        rating = Math.max(0, Math.min(5, Number(rating) || 0));
        var html = '<span class="qp-rev-rating-stars" aria-hidden="true">';
        for (var i = 1; i <= 5; i++) {
            if (rating >= i) {
                html += '<i class="fas fa-star"></i>';
            } else if (rating >= i - 0.5) {
                html += '<i class="fas fa-star-half-alt"></i>';
            } else {
                html += '<i class="far fa-star"></i>';
            }
        }
        html += '</span>';
        return html;
    }

    function qpReviewAvatarHtml(name, tone) {
        var initials = String(name || 'G').trim().split(/\s+/).map(function (p) {
            return p.charAt(0);
        }).join('').slice(0, 2).toUpperCase();
        return '<div class="qp-rev-avatar tone-' + (tone || 'a') + '" aria-hidden="true">' + esc(initials) + '</div>';
    }

    function buildPreviewReviewsHtml() {
        var reviews = [
            {
                text: 'Everything was perfectly planned from hotels to transfers. A truly hassle-free and memorable trip!',
                name: 'Riya Sharma',
                place: 'Bali, Indonesia',
                tone: 'a'
            },
            {
                text: 'Excellent service and attention to detail. The team made our Europe trip smooth and special.',
                name: 'Arjun Mehta',
                place: 'Paris, France',
                tone: 'b'
            },
            {
                text: 'Well-organized itinerary, great hotels and 24/7 support. Highly recommend for a premium experience!',
                name: 'Sneha Kapoor',
                place: 'Switzerland',
                tone: 'c'
            }
        ];
        var googleHref = 'https://www.google.com/search?q=Multizone+Travels+reviews';

        var html = '<div class="qp-sec qp-sec-reviews">';
        html += '<div class="qp-rev-wrap">';
        html += '<div class="qp-rev-head">' +
            '<div class="qp-rev-eyebrow">' +
            '<span class="qp-rev-eyebrow-line" aria-hidden="true"></span>' +
            '<span class="qp-rev-brand" aria-hidden="true">' + qpGoogleLogoSvg(18) + '</span>' +
            '<span class="qp-rev-eyebrow-line" aria-hidden="true"></span>' +
            '</div>' +
            '<div class="qp-rev-title">Trusted by <span class="qp-rev-title-accent">Happy Travellers</span></div>' +
            '<div class="qp-rev-badge">' +
            '<span class="qp-rev-badge-label">Google Reviews</span>' +
            '<span class="qp-rev-badge-sep" aria-hidden="true"></span>' +
            '<span class="qp-rev-badge-score">4.6</span>' +
            qpRatingStarsHtml(4.6) +
            '<span class="qp-rev-badge-sep" aria-hidden="true"></span>' +
            '<span class="qp-rev-badge-verified">' +
            '<span class="qp-rev-shield" aria-hidden="true"><i class="fas fa-check"></i></span>' +
            '<span>Verified Google Reviews</span>' +
            '</span>' +
            '</div>' +
            '</div>';

        html += '<div class="qp-rev-grid">';
        reviews.forEach(function (r) {
            html += '<div class="qp-rev-card">' +
                '<div class="qp-rev-person">' +
                qpReviewAvatarHtml(r.name, r.tone) +
                '<div class="qp-rev-person-meta">' +
                '<div class="qp-rev-name">' + esc(r.name) + '</div>' +
                '<div class="qp-rev-place">' + esc(r.place) + '</div>' +
                '</div>' +
                '</div>' +
                '<div class="qp-rev-text">&ldquo;' + esc(r.text) + '&rdquo;</div>' +
                '<div class="qp-rev-verified">' +
                
                '</div>' +
                '</div>';
        });
        html += '</div>';

        html += '<a class="qp-rev-more-btn" href="' + esc(googleHref) + '" target="_blank" rel="noopener noreferrer">' +
            '<span class="qp-rev-more-g" aria-hidden="true">' + qpGoogleLogoSvg(14) + '</span>' +
            '<span>Read more reviews on Google</span>' +
            '<i class="fas fa-chevron-right" aria-hidden="true"></i>' +
            '</a>';

        html += '</div></div>';
        return html;
    }

    function buildPreviewMembershipsHtml() {
        var items = [
            {
                img: 'crm/assets/accreditations/YI.png',
                title: 'Young Indians',
                desc: 'Confederation of Indian Industry (CII), Youth Leadership Network'
            },
            {
                img: 'crm/assets/accreditations/BNI_Logo.jpg',
                title: 'BNI Member',
                desc: 'Business Network International, Business Referral Organization'
            },
            {
                img: 'crm/assets/accreditations/FJCCI-Logo.webp',
                title: 'FJCCI Member',
                desc: 'Federation of Jharkhand Chamber of Commerce & Industries'
            },
            {
                img: 'crm/assets/accreditations/Tia.png',
                title: 'Tourism India Alliance',
                desc: 'National Travel & Tourism Industry Association'
            }
        ];

        var html = '<div class="qp-sec qp-sec-memberships">';
        html += '<div class="qp-mem-head">' +
            '<div class="qp-mem-heading">Global Accreditations</div>' +
            '<div class="qp-mem-heading-rule" aria-hidden="true"></div>' +
            '</div>';
        html += '<div class="qp-mem-row">';
        items.forEach(function (item) {
            html += '<div class="qp-mem-cell">' +
                '<img class="qp-mem-logo" src="' + esc(absUrl(item.img)) + '" alt="' + esc(item.title) + '" loading="eager" decoding="sync">' +
                '<div class="qp-mem-copy">' +
                '<div class="qp-mem-title">' + esc(item.title) + '</div>' +
                '<div class="qp-mem-desc">' + esc(item.desc) + '</div>' +
                '</div>' +
                '</div>';
        });
        html += '</div></div>';
        return html;
    }

    function buildPreviewTrustStatsHtml() {
        return '';
    }

    function buildPreviewSupportHtml() {
        var phone = String(Q_PREVIEW_META.phone || '+91 9709400140').trim();
        var phoneAlt = String(Q_PREVIEW_META.phone_alt || '+91 9709100140').trim();
        var website = String(Q_PREVIEW_META.website || 'www.multizonetravels.com').trim().replace(/^https?:\/\//i, '');
        var email = String(Q_PREVIEW_META.email || 'info@multizonetravels.com').trim();
        var address = String(Q_PREVIEW_META.address || 'ByPass Road, Dibadih, Ranchi-834002').trim();

        function formatPhone(p) {
            p = String(p || '').replace(/\s+/g, ' ').trim();
            var m = p.match(/^(\+?\d{1,3})\s*(\d{5})\s*(\d{5})$/);
            if (m) {
                return m[1] + ' ' + m[2] + ' ' + m[3];
            }
            m = p.match(/^(\+?\d{1,3})\s*(\d{10})$/);
            if (m) {
                return m[1] + ' ' + m[2].slice(0, 5) + ' ' + m[2].slice(5);
            }
            return p;
        }

        var addressLines = [];
        if (/Dibadih|Dibdih/i.test(address)) {
            addressLines = ['ByPass Road, Dibadih,', 'Ranchi-834002'];
        } else {
            var parts = address.split(/,\s*/);
            if (parts.length >= 2) {
                addressLines = [parts.slice(0, Math.ceil(parts.length / 2)).join(', ') + (parts.length > 2 ? ',' : ''), parts.slice(Math.ceil(parts.length / 2)).join(', ')];
            } else {
                addressLines = [address];
            }
        }

        return '<div class="qp-sec qp-sec-support">' +
            '<div class="qp-support-footer">' +
            '<div class="qp-support-row">' +
            '<div class="qp-support-cell">' +
            '<span class="qp-support-ico is-red" aria-hidden="true"><i class="fas fa-phone-alt"></i></span>' +
            '<div class="qp-support-copy">' +
            '<span>' + esc(formatPhone(phone)) + '</span>' +
            '<span>' + esc(formatPhone(phoneAlt)) + '</span>' +
            '</div>' +
            '</div>' +
            '<div class="qp-support-cell">' +
            '<span class="qp-support-ico is-dark" aria-hidden="true"><i class="fas fa-globe"></i></span>' +
            '<div class="qp-support-copy">' +
            '<span>' + esc(website) + '</span>' +
            '<span>' + esc(email) + '</span>' +
            '</div>' +
            '</div>' +
            '<div class="qp-support-cell">' +
            '<span class="qp-support-ico is-red" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></span>' +
            '<div class="qp-support-copy">' +
            addressLines.map(function (line) {
                return '<span>' + esc(line) + '</span>';
            }).join('') +
            '</div>' +
            '</div>' +
            '</div>' +
            '<div class="qp-support-bar" aria-hidden="true"></div>' +
            '</div>' +
            '</div>';
    }

    function buildPreviewFooterHtml() {
        return '';
    }

    function qpSectionHead(iconFa, title, slogan) {
        return '<div class="qp-sec-head">' +
            '<div class="qp-sec-head-left">' +
            '<span class="qp-sec-icon"><i class="' + esc(iconFa) + '"></i></span>' +
            '<span class="qp-sec-title">' + esc(title) + '</span>' +
            '<span class="qp-sec-line"></span>' +
            '</div>' +
            (slogan ? '<div class="qp-sec-slogan">' + esc(slogan) + '</div>' : '') +
            '</div>';
    }

    function qpInfoCell(label, valueHtml, extraClass) {
        return '<div class="qp-info-cell' + (extraClass ? (' ' + extraClass) : '') + '">' +
            '<span class="qp-info-label">' + esc(label) + '</span>' +
            '<div class="qp-info-value">' + valueHtml + '</div>' +
            '</div>';
    }

    function inferItineraryPills(day) {
        var overnight = String((day && (day.overnight || day.overnight_stay)) || '').trim();
        var mealVal = String((day && (day.meal || day.meals)) || '').trim();
        var text = ((day && day.title) || '') + ' ' + String((day && day.description) || '').replace(/<[^>]*>/g, ' ');
        text = text.toLowerCase();
        var pills = [];
        if (overnight || /overnight|night stay|stay overnight|hotel stay|check[- ]?in/.test(text)) {
            pills.push({ cls: 'qp-pill-overnight', icon: 'fas fa-moon', label: overnight ? ('Overnight · ' + overnight) : 'Overnight' });
        }
        var meals = [];
        if (mealVal) {
            meals.push(mealVal);
        } else {
            if (/breakfast|\bbb\b|\bcp\b/.test(text)) meals.push('Breakfast');
            if (/lunch/.test(text)) meals.push('Lunch');
            if (/dinner/.test(text)) meals.push('Dinner');
            if (!meals.length && /\b(map|ap|ai)\b|meal plan|meals? included/.test(text)) {
                meals.push('Meals');
            }
        }
        if (meals.length) {
            pills.push({ cls: 'qp-pill-meal', icon: 'fas fa-utensils', label: meals.join(' · ') });
        }
        return pills;
    }

    function qpSocialClass(type) {
        type = String(type || '').toLowerCase();
        if (type === 'facebook') return 'fb';
        if (type === 'twitter' || type === 'x') return 'tw';
        if (type === 'linkedin') return 'li';
        if (type === 'instagram') return 'ig';
        if (type === 'youtube') return 'yt';
        if (type === 'google' || type === 'googleplus' || type === 'google-plus') return 'gp';
        return 'web';
    }

    function qpFormatHotelStayPill(nights, city) {
        var n = parseInt(nights, 10);
        if (isNaN(n) || n < 0) {
            n = 0;
        }
        var cityName = String(city || '').trim();
        if (!cityName) {
            return '';
        }
        var unit = n === 1 ? 'Nt' : 'Nts';
        var num = String(n).padStart(2, '0');
        return num + ' ' + unit + ' ' + cityName;
    }

    function buildPreviewHotelRoutePillsHtml(hotelsData, activeOptionId) {
        hotelsData = hotelsData || { categories: [] };
        var cats = hotelsData.categories || [];
        var hotels = [];
        var activeId = String(activeOptionId || hotelsData.active_category_id || '');
        var activeCat = null;
        cats.forEach(function (cat) {
            if (!activeCat && String(cat.id) === activeId && (cat.hotels || []).length) {
                activeCat = cat;
            }
        });
        if (!activeCat) {
            cats.forEach(function (cat) {
                if (!activeCat && (cat.hotels || []).length) {
                    activeCat = cat;
                }
            });
        }
        if (activeCat) {
            hotels = activeCat.hotels || [];
        }
        var pills = [];
        hotels.forEach(function (h) {
            var d = normalizeHotelData(h);
            var label = qpFormatHotelStayPill(d.nights, d.city);
            if (label) {
                pills.push(label);
            }
        });
        if (!pills.length) {
            return '';
        }
        var html = '<div class="qp-route-pills">';
        pills.forEach(function (label, i) {
            if (i > 0) {
                html += '<span class="qp-route-arrow" aria-hidden="true">→</span>';
            }
            html += '<span class="qp-route-pill">' + esc(label) + '</span>';
        });
        html += '</div>';
        return html;
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
        var destTitle = qpFormatDestTitle(destination);
        var logoUrl = absUrl(Q_PREVIEW_META.logo || 'img/MZ_LOGO_1.jpeg');
        var todayLong = formatPreviewLongDate(new Date().toISOString().slice(0, 10));
        var paxChildren = children > 0 ? String(children) : '0';
        var nightsLabel = nights + ' Nights / ' + days + ' Days';

        var html = '';

        /* —— 1. Header —— */
        html += '<div class="qp-doc-head q-preview-head">';
        html += '<div class="qp-doc-head-top">';
        html += '<div class="qp-logo q-preview-logo"><img src="' + esc(logoUrl) + '" alt="Multi Zone Travels"></div>';
        html += '<div class="qp-title-block q-preview-title-block">';
        html += '<div class="qp-header-text">' +
            previewEditable(
                String(p.header_text || '').trim(),
                'header_text',
                { cls: 'q-preview-header-text', placeholder: 'Add heading text' }
            ) +
            '</div>';
        html += '<h1>' +
            previewEditable(destTitle, 'destination', { cls: 'q-preview-dest-title qp-dest-red' }) +
            '</h1>';
        html += '<div class="qp-duration-wrap">' +
            '<span class="qp-duration-line"></span>' +
            '<div class="qp-duration q-preview-duration">' +
            previewEditable(nightsLabel, 'no_of_nights', { type: 'nights_label', placeholder: '0 Nights / 1 Days' }) +
            '</div>' +
            '<span class="qp-duration-line"></span>' +
            '</div>';
        html += '</div>';
        html += '<div class="qp-ref-card q-preview-ref-block">';
        html += '<div class="qp-ref-row">' +
            '<i class="fas fa-file-alt qp-ref-ico" aria-hidden="true"></i>' +
            '<div class="qp-ref-text">' +
            '<div class="qp-ref-label">Reference No.</div>' +
            '<div class="qp-ref-value ref">REF: ' + esc(previewRefNo(p)) + '</div>' +
            '</div></div>';
        html += '<div class="qp-ref-row">' +
            '<i class="fas fa-calendar-alt qp-ref-ico" aria-hidden="true"></i>' +
            '<div class="qp-ref-text">' +
            '<div class="qp-ref-label">Quotation Date</div>' +
            '<div class="qp-ref-value">' + esc(todayLong) + '</div>' +
            '</div></div>';
        html += '</div>';
        html += '</div>';
        html += buildPreviewHotelRoutePillsHtml(hotelsData, String(p.active_option_id || hotelsData.active_category_id || ''));
        html += '</div>';

        /* —— 2. Guest Information —— */
        html += '<div class="qp-sec">';
        html += qpSectionHead('fas fa-user', 'Guest Information', 'TRAVEL MORE • EXPLORE BEYOND');
        html += '<div class="qp-info-card qp-cols-3">';
        html += qpInfoCell('Guest Name', previewEditable(previewVal(p.guest_name), 'guest_name', { cls: 'q-preview-cell-edit' }));
        html += qpInfoCell('Mobile Number', previewEditable(previewVal(p.mobile_no), 'mobile_no', { cls: 'q-preview-cell-edit' }));
        html += qpInfoCell('Email Address', previewEditable(previewVal(p.email), 'email', { cls: 'q-preview-cell-edit' }));
        html += '</div></div>';

        /* —— 3. Travel Details —— */
        html += '<div class="qp-sec">';
        html += qpSectionHead('fas fa-plane', 'Travel Details', 'DISCOVER • EXPERIENCE • BELONG');
        html += '<div class="qp-info-card qp-cols-5 qp-travel-details">';
        html += qpInfoCell('Destination', previewEditable(destination.toUpperCase(), 'destination', { cls: 'q-preview-cell-edit' }), 'qp-cell-wide');
        html += qpInfoCell('No Of Nights', previewEditable(nightsLabel, 'no_of_nights', { type: 'nights_label', cls: 'q-preview-cell-edit' }), 'qp-cell-grow');
        html += qpInfoCell('Tentative Date', previewEditable(formatPreviewSlashDate(p.tentative_date), 'tentative_date', { type: 'date', cls: 'q-preview-cell-edit' }), 'qp-cell-date');
        html += qpInfoCell('Adults', previewEditable(String(adults), 'no_of_adults', { type: 'int', cls: 'q-preview-cell-edit' }), 'qp-cell-narrow');
        html += qpInfoCell('Children', previewEditable(paxChildren, 'no_of_children', { type: 'int', cls: 'q-preview-cell-edit' }), 'qp-cell-narrow');
        html += '</div></div>';

        /* —— 4. Flight Details —— */
        if (flights.length) {
            html += '<div class="qp-sec qp-sec-flights">';
            html += qpFlightSectionHead();
            html += buildPreviewFlightCardsHtml(flights);
            html += '</div>';
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

        function qpTourCostRowHtml(iconHtml, particularHtml, amountHtml, rowClass) {
            return '<div class="qp-tc-row' + (rowClass ? (' ' + rowClass) : '') + '">' +
                '<div class="qp-tc-row-left">' +
                '<span class="qp-tc-row-ico" aria-hidden="true">' + iconHtml + '</span>' +
                '<span class="qp-tc-row-label">' + particularHtml + '</span>' +
                '</div>' +
                '<div class="qp-tc-row-amt">' + amountHtml + '</div>' +
                '</div>';
        }

        function qpTourCostNotesCardHtml() {
            var notes = [
                'Rates subject to availability.',
                'Valid at quotation time only.',
                'Final rates to be re-checked on confirmation.',
                'Confirm early to secure booking.'
            ];
            var list = notes.map(function (text, i) {
                return '<li class="qp-notes-item">' +
                    '<span class="qp-notes-num" aria-hidden="true">' + (i + 1) + '</span>' +
                    '<span class="qp-notes-text">' + esc(text) + '</span>' +
                    '</li>';
            }).join('');
            return '<div class="qp-notes-card">' +
                '<div class="qp-notes-hd">' +
                '<span class="qp-notes-hd-ico" aria-hidden="true"><i class="fas fa-clipboard-list"></i></span>' +
                '<span class="qp-notes-hd-divider" aria-hidden="true"></span>' +
                '<div class="qp-notes-hd-text">' +
                '<h3 class="qp-notes-title">Notes</h3>' +
                '<div class="qp-notes-sub">IMPORTANT INFORMATION</div>' +
                '</div></div>' +
                '<ul class="qp-notes-list">' + list + '</ul>' +
                '</div>';
        }

        function wrapTourCostBlock(rowsHtml, grandHtml) {
            var out = '<div class="qp-cost-notes-row">';
            out += '<div class="qp-tour-card">';
            out += '<div class="qp-tour-hd">' +
                '<div class="qp-tour-hd-left">' +
                '<span class="qp-tour-hd-ico" aria-hidden="true">₹</span>' +
                '<span class="qp-tour-hd-divider" aria-hidden="true"></span>' +
                '<div class="qp-tour-hd-text">' +
                '<h3 class="qp-tour-title">Tour Cost</h3>' +
                '<div class="qp-tour-sub"></div>' +
                '</div></div>' +
                '<div class="qp-tour-tagline">TRAVEL <span></span> EXPLORE <span></span> CREATE MEMORIES</div>' +
                '</div>';
            out += '<div class="qp-tc-thead"><span>PARTICULARS</span><span>AMOUNT (INR)</span></div>';
            out += '<div class="qp-tc-body">' + rowsHtml + '</div>';
            out += grandHtml;
            out += '</div>';
            out += qpTourCostNotesCardHtml();
            out += '</div>';
            return out;
        }

        function qpTourGrandHtml(amountHtml, inclusiveGst) {
            return '<div class="qp-tc-grand">' +
                '<div class="qp-tc-grand-left">' +
                '<div class="qp-tc-grand-label">Grand Total</div>' +
                (inclusiveGst ? '<div class="qp-tc-grand-sub">INCLUSIVE OF GST</div>' : '<div class="qp-tc-grand-sub">TOTAL AMOUNT</div>') +
                '</div>' +
                '<span class="qp-tc-grand-divider" aria-hidden="true"></span>' +
                '<strong class="qp-tc-grand-amt">' + amountHtml + '</strong>' +
                '</div>';
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
                var rowsTc = '';
                var adultRate = parseFloat(tourCost.per_person != null ? tourCost.per_person : tourCost.adult_rate) || 0;
                var adultQty = parseInt(tourCost.adults, 10) || adults;
                var adultAmt = parseFloat(tourCost.adult_amount);
                if (isNaN(adultAmt)) adultAmt = adultRate * adultQty;
                if (adultRate > 0 || adultAmt > 0) {
                    rowsTc += qpTourCostRowHtml(
                        '<i class="fas fa-users"></i>',
                        'INR ' + esc(money(adultRate)) + ' × ' + esc(adultQty) + ' Adults',
                        esc(money(adultAmt))
                    );
                }
                var childAmounts = Array.isArray(tourCost.child_amounts) ? tourCost.child_amounts : null;
                var childRates = tourCost.child_rates || [];
                var childAges = Array.isArray(tourCost.children_ages) ? tourCost.children_ages : [];
                var childQtysPrev = Array.isArray(tourCost.child_qtys) ? tourCost.child_qtys : null;
                var childRowCount = childQtysPrev && childQtysPrev.length
                    ? childQtysPrev.length
                    : (childAges.length || (childAmounts ? childAmounts.length : childRates.length));
                var ci;
                for (ci = 0; ci < childRowCount; ci++) {
                    var cQty = childQtysPrev && childQtysPrev[ci] != null
                        ? Math.max(1, parseInt(childQtysPrev[ci], 10) || 1)
                        : 1;
                    var cAmt = childAmounts && childAmounts[ci] != null
                        ? parseFloat(childAmounts[ci])
                        : parseFloat(childRates[ci]);
                    if (isNaN(cAmt)) cAmt = adultRate * cQty;
                    var ageNum = parseInt(childAges[ci], 10);
                    var childLabel = (!isNaN(ageNum) && ageNum > 0)
                        ? ('Child – ' + ageNum + ' Yrs')
                        : ('Child – Yrs');
                    rowsTc += qpTourCostRowHtml(
                        '<i class="far fa-user"></i>',
                        'INR ' + esc(money(adultRate > 0 ? adultRate : (cQty > 0 ? (cAmt / cQty) : cAmt))) + ' × ' + esc(cQty) + ' ' + esc(childLabel),
                        esc(money(cAmt))
                    );
                }
                var showGst = !parseInt(tourCost.hide_gst, 10) && parseFloat(tourCost.gst_amount) > 0;
                if (showGst) {
                    rowsTc += qpTourCostRowHtml(
                        '<i class="fas fa-file-invoice-dollar"></i>',
                        'GST @ ' + esc(String(tourCost.gst_percent || 5)) + '%',
                        esc(money(tourCost.gst_amount)),
                        'is-gst'
                    );
                }
                return wrapTourCostBlock(rowsTc, qpTourGrandHtml(esc(money(tourCost.grand_total)), showGst));
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
            var packageTotal = pkg > 0 ? pkg : (qt > 0 ? qt : (ppa * adults));
            var childCountFb = parseInt(p.no_of_children, 10) || 0;
            var totalTravelers = Math.max(1, adults + childCountFb);
            var perPerson = Math.round((packageTotal / totalTravelers) * 100) / 100;
            var rows = '';
            rows += qpTourCostRowHtml(
                '<i class="fas fa-users"></i>',
                'INR ' + (editable ? previewEditable(money(perPerson), 'price_per_adult', { type: 'money' }) : esc(money(perPerson))) +
                    ' × ' + esc(adults) + ' Adults',
                esc(money(Math.round(perPerson * adults * 100) / 100))
            );
            var agesFb = Array.isArray(p.children_ages) ? p.children_ages : [];
            var cfi;
            for (cfi = 0; cfi < childCountFb; cfi++) {
                var ageFb = parseInt(agesFb[cfi], 10);
                var lblFb = (!isNaN(ageFb) && ageFb > 0) ? ('Child – Age ' + ageFb) : 'Child – Age —';
                rows += qpTourCostRowHtml(
                    '<i class="far fa-user"></i>',
                    'INR ' + esc(money(perPerson)) + ' × 1 ' + esc(lblFb),
                    esc(money(perPerson))
                );
            }
            var totalPart = editable
                ? previewEditable(money(packageTotal), 'quotation_total', { type: 'money' })
                : esc(money(qt > 0 ? qt : packageTotal));
            return wrapTourCostBlock(rows, qpTourGrandHtml(totalPart, false));
        }

        /* —— 5. Hotel Details —— */
        var hotelCatsWithRows = [];
        hotelsData.categories.forEach(function (cat, idx) {
            if ((cat.hotels || []).length) {
                hotelCatsWithRows.push({ cat: cat, idx: idx });
            }
        });
        if (hotelCatsWithRows.length) {
            html += '<div class="qp-sec qp-sec-hotels">';
            html += qpHotelSectionHead();
            hotelCatsWithRows.forEach(function (item, viewIdx) {
                var cat = item.cat;
                var idx = item.idx;
                if (multiHotelOptions) {
                    var optLabel = cat.label || defaultHotelCategoryLabel(idx);
                    html += '<div class="qp-hotel-option-tabs">' +
                        '<span class="qp-hotel-option-tab is-active">' + esc(optLabel) + '</span>' +
                        '</div>';
                }
                html += buildPreviewHotelCardsHtml(cat.hotels || [], idx);

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
            html += '</div>';
            html += buildPreviewMealPlanLegendHtml(hotelsData);
        }

        /* —— 5b. Tour Cost (directly after Hotel Details) —— */
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

        /* —— 6. Day Wise Itinerary —— */
        if (!parseInt(p.without_itinerary, 10)) {
            var itineraryHtml = '';
            itinerary.forEach(function (day, di) {
                var dayTitle = day && day.title ? String(day.title).trim() : '';
                var dayDesc = day && day.description ? String(day.description).replace(/<[^>]*>/g, '').trim() : '';
                if (!dayTitle && !dayDesc) {
                    return;
                }
                var dateLabel = qpItinDateLabel(p.tentative_date, di);
                var dayImage = qpExtractDayImage(day);
                var descHtml = day.description || '';

                itineraryHtml += '<div class="q-preview-day qp-day">';
                itineraryHtml += '<div class="q-preview-day-head qp-day-head">';
                itineraryHtml += '<span class="qp-day-badge">DAY ' + (di + 1) + '</span>';
                if (dateLabel) {
                    itineraryHtml += '<span class="qp-day-sep" aria-hidden="true"></span>';
                    itineraryHtml += '<span class="qp-day-meta"><i class="far fa-calendar-alt" aria-hidden="true"></i>' +
                        '<span>' + esc(dateLabel) + '</span></span>';
                }
                itineraryHtml += '<span class="qp-day-sep" aria-hidden="true"></span>';
                itineraryHtml += '<div class="qp-day-title"><i class="fas fa-map-marker-alt" aria-hidden="true"></i>' +
                    previewEditable(previewVal(day.title, ''), 'itinerary.' + di + '.title', {
                        placeholder: 'Day title',
                        cls: 'q-preview-cell-edit qp-day-title-edit'
                    }) + '</div>';
                itineraryHtml += '</div>';

                itineraryHtml += '<div class="q-preview-day-body qp-day-body">';
                itineraryHtml += '<div class="qp-day-main' + (dayImage ? ' has-photo' : '') + '">';
                itineraryHtml += '<div class="qp-day-content">';
                itineraryHtml += previewEditable(descHtml, 'itinerary.' + di + '.description', {
                    type: 'html',
                    multiline: true,
                    cls: 'q-preview-rich qp-day-desc'
                });
                itineraryHtml += qpItinPillsHtml(day);
                itineraryHtml += '</div>';
                if (dayImage) {
                    itineraryHtml += '<div class="qp-day-photo">' +
                        '<img src="' + esc(dayImage) + '" alt="' + esc(dayTitle || ('Day ' + (di + 1))) + '">' +
                        '</div>';
                }
                itineraryHtml += '</div></div></div>';
            });
            if (itineraryHtml) {
                html += '<div class="qp-sec qp-sec-itinerary">';
                html += qpItinSectionHead();
                html += itineraryHtml;
                html += '</div>';
            }
        }

        /* —— 7. Inclusions —— */
        if (previewHasHtmlContent(p.inclusion)) {
            html += '<div class="qp-sec qp-sec-incl">';
            html += '<div class="qp-incl-card">';
            html += '<div class="qp-incl-head">' +
                '<div class="qp-incl-head-left">' +
                '<span class="qp-incl-vbar" aria-hidden="true"></span>' +
                '<span class="qp-incl-icon" aria-hidden="true"><i class="fas fa-check-circle"></i></span>' +
                '<div class="qp-incl-head-copy">' +
                '<div class="qp-incl-title">INCLUSIONS</div>' +
                '<div class="qp-incl-sub">WHAT\'S INCLUDED IN YOUR JOURNEY</div>' +
                '</div>' +
                '</div>' +
                '<div class="qp-incl-slogan">' +
                'JOURNEYS <span class="qp-incl-slogan-dot">•</span> ' +
                'CARE <span class="qp-incl-slogan-dot">•</span> ' +
                'MEMORIES' +
                '</div>' +
                '</div>';
            html += '<div class="qp-incl-body">';
            html += previewEditable(p.inclusion || '', 'inclusion', {
                type: 'html',
                multiline: true,
                cls: 'q-preview-rich qp-incl-edit'
            });
            html += '</div></div></div>';
        }

        /* —— 8. Terms —— */
        var policyBlocks = [
            { key: 'payment_policy', title: 'Payment Policy', html: p.payment_policy },
            { key: 'cancellation_policy', title: 'Cancellation Policy', html: p.cancellation_policy },
            { key: 'terms_conditions', title: 'Terms & Conditions', html: p.terms_conditions },
            { key: 'other_details', title: 'Other Details', html: p.other_details }
        ];
        var visiblePolicies = [];
        policyBlocks.forEach(function (b) {
            if (previewHasHtmlContent(b.html)) {
                visiblePolicies.push(b);
            }
        });
        if (visiblePolicies.length) {
            var multiPolicies = visiblePolicies.length > 1;
            var termsMoreHref = '';
            var site = String(Q_PREVIEW_META.website || 'www.multizonetravels.com').trim();
            if (site) {
                termsMoreHref = /^(https?:)?\/\//i.test(site) ? site : ('https://' + site.replace(/^\/+/, ''));
            }
            var policyHtml = '';
            visiblePolicies.forEach(function (b) {
                policyHtml += '<div class="q-preview-policy-block qp-policy-block">';
                if (multiPolicies) {
                    policyHtml += '<div class="q-preview-policy-title qp-policy-title">' + esc(b.title) + '</div>';
                }
                policyHtml += previewEditable(qpFormatTermsChecklistHtml(b.html || ''), b.key, {
                    type: 'html',
                    multiline: true,
                    cls: 'q-preview-rich qp-terms-rich'
                });
                policyHtml += '</div>';
            });

            html += '<div class="qp-sec qp-sec-terms">';
            html += '<div class="qp-terms-card">';
            html += '<div class="qp-terms-head">' +
                '<span class="qp-terms-vbar" aria-hidden="true"></span>' +
                '<span class="qp-terms-icon" aria-hidden="true"><i class="fas fa-file-alt"></i></span>' +
                '<div class="qp-terms-head-copy">' +
                '<div class="qp-terms-title">QUOTATION TERMS &amp; CONDITIONS</div>' +
                '<div class="qp-terms-sub">PLEASE READ CAREFULLY BEFORE CONFIRMING YOUR BOOKING</div>' +
                '</div>' +
                '</div>';
            html += '<div class="qp-terms-body">' + policyHtml + '</div>';
            if (termsMoreHref) {
                html += '<div class="qp-terms-foot">' +
                    '<a class="qp-terms-more" href="' + esc(termsMoreHref) + '" target="_blank" rel="noopener noreferrer">' +
                    '<span class="qp-terms-more-label">For more details click </span>' +
                    '<strong class="qp-terms-more-here">here</strong>' +
                    '<i class="fas fa-external-link-alt" aria-hidden="true"></i>' +
                    '</a>' +
                    '</div>';
            }
            html += '</div></div>';
        }

        /* —— 9. Exclusions —— */
        if (previewHasHtmlContent(p.exclusion)) {
            html += '<div class="qp-sec">';
            html += '<div class="qp-excl-head"><span class="qp-bar"></span><h3><i class="fas fa-ban"></i> Exclusions</h3></div>';
            html += previewEditable(p.exclusion || '', 'exclusion', {
                type: 'html',
                multiline: true,
                cls: 'q-preview-rich qp-excl-edit'
            });
            html += '</div>';
        }

        /* —— Last print page: Journey + Reviews + Memberships + Footer —— */
        html += '<div class="qp-print-last-page">';
        html += '<div class="qp-last-main"><div class="qp-last-main-inner">';
        html += buildPreviewAccreditationsHtml();
        html += buildPreviewReviewsHtml();
        html += '</div></div>';
        html += '<div class="qp-last-foot"><div class="qp-last-foot-inner">';
        html += buildPreviewMembershipsHtml();
        html += buildPreviewSupportHtml();
        html += '</div></div>';
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
            var $el = $(this);
            var current = readPreviewEditableValue($el);
            if (current !== previewEditOrig) {
                setPreviewDirty(true);
            }
            var path = String($el.attr('data-q-edit') || '');
            if (/^hotel\.\d+\.\d+\.(city|nights)$/.test(path)) {
                clearTimeout(previewHotelPillTimer);
                previewHotelPillTimer = window.setTimeout(function () {
                    applyPreviewEdit($el);
                    refreshPreviewHotelRoutePills();
                }, 120);
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

        $(document).on('click', '#qPreviewPrintArea td, #qPreviewPrintArea .qp-info-cell, #qPreviewPrintArea .q-preview-day-head, #qPreviewPrintArea .qp-day-head, #qPreviewPrintArea .q-preview-day-body, #qPreviewPrintArea .qp-day-body, #qPreviewPrintArea .q-preview-policy-block, #qPreviewPrintArea .qp-policy-block, #qPreviewPrintArea .q-preview-rich', function (e) {
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
            setDateInputValue($('#q_tentative_date'), lead.tentative_date);
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
        if (Array.isArray(lead.children_ages)) {
            writeChildrenAges(lead.children_ages);
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
        var headerTextPrefill = '';
        if (p.header_text) {
            headerTextPrefill = String(p.header_text);
        } else if (p.cost_sheet && p.cost_sheet.header_text) {
            headerTextPrefill = String(p.cost_sheet.header_text);
        }
        $('#q_header_text').val(headerTextPrefill);
        setDateInputValue(
            $('#q_tentative_date'),
            p.tentative_date && p.tentative_date !== '0000-00-00' ? p.tentative_date : ''
        );
        $('#q_nights').val(p.no_of_nights || 0);
        $('#q_adults').val(p.no_of_adults || 1);
        $('#q_children').val(p.no_of_children || 0);
        if (Array.isArray(p.children_ages)) {
            writeChildrenAges(p.children_ages);
        } else if (p.cost_sheet && p.cost_sheet.tour_cost && Array.isArray(p.cost_sheet.tour_cost.children_ages)) {
            writeChildrenAges(p.cost_sheet.tour_cost.children_ages);
        } else {
            writeChildrenAges([]);
        }

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
        syncTourCostOptUiFromMasters();

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
        initQuotationDatePickers();
        recalcCosts();
        saveFormDraftToStorage();
    }

    /* ------------------------------------------------------------------ */
    /* Step wizard (single-page scroll mode)                               */
    /* ------------------------------------------------------------------ */
    var Q_WIZARD_TOTAL = 7;
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
            if (!qAutoInclusionSyncing) {
                scheduleSyncReturnAirfareInclusion();
            }
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

    function getFixedTopChromeHeight() {
        var h = 0;
        var header = document.querySelector('.main-header');
        if (header) {
            var hs = window.getComputedStyle(header);
            if (hs.position === 'fixed' || hs.position === 'sticky') {
                h += header.getBoundingClientRect().height || 0;
            }
        }
        return h;
    }

    function getWizardStickyStepperHeight() {
        var stepper = document.querySelector('#qWizard.is-scroll-mode .q-stepper, .q-wizard.is-scroll-mode .q-stepper');
        if (!stepper) {
            return 0;
        }
        return stepper.getBoundingClientRect().height || $(stepper).outerHeight() || 0;
    }

    function syncWizardStickyOffset() {
        var chrome = getFixedTopChromeHeight();
        var stepperH = getWizardStickyStepperHeight();
        var root = document.querySelector('.crm-quotation-gen') || document.documentElement;
        root.style.setProperty('--q-wizard-chrome-offset', chrome + 'px');
        root.style.setProperty('--q-wizard-stepper-offset', stepperH + 'px');
        root.style.setProperty('--q-wizard-scroll-offset', (chrome + stepperH + 10) + 'px');
        return chrome + stepperH + 10;
    }

    function getWizardScrollOffset() {
        return syncWizardStickyOffset();
    }

    function scrollToWizardStep(step, behavior) {
        var el = getWizardSectionEl(step);
        if (!el) {
            return;
        }
        var smooth = behavior !== 'auto';
        qWizardScrollTick = true;

        function pageY() {
            return window.pageYOffset || document.documentElement.scrollTop || 0;
        }

        function targetY() {
            var offset = getWizardScrollOffset();
            var y = el.getBoundingClientRect().top + pageY() - offset;
            return y < 0 ? 0 : y;
        }

        function applyScroll(useSmooth) {
            var y = targetY();
            try {
                window.scrollTo({
                    top: y,
                    behavior: useSmooth ? 'smooth' : 'auto'
                });
            } catch (e) {
                window.scrollTo(0, y);
            }
        }

        expandWizardSection(step, function () {
            // First scroll after accordion is open.
            applyScroll(smooth);
            // Correct once layout/fonts settle (sticky height can change).
            window.setTimeout(function () {
                var drift = Math.abs(targetY() - pageY());
                if (drift > 6) {
                    applyScroll(false);
                }
                window.setTimeout(function () {
                    qWizardScrollTick = false;
                }, smooth ? 350 : 40);
            }, smooth ? 220 : 40);
        });
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
                var $body = $('#qSectionBody' + step);
                var bodyOpen = $body.length ? $body.is(':visible') : false;
                // Next lives inside the open section body only (no stack of buttons when collapsed).
                $nextBar.toggle(unlocked && step < Q_WIZARD_TOTAL && bodyOpen);
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
            if (step === 4 || step === 7) {
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
        if (step === 7) {
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
            if (scroll !== false) {
                scrollToWizardStep(step);
            } else {
                expandWizardSection(step);
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
            rootMargin: '-' + Math.max(80, Math.round(getWizardScrollOffset())) + 'px 0px -55% 0px',
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
            syncWizardStickyOffset();
            $(window).off('resize.qWizardSticky').on('resize.qWizardSticky', function () {
                syncWizardStickyOffset();
            });
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
        scheduleSyncReturnAirfareInclusion();
        initLeadLookup();
        initDestinationPicker();
        initPackageSuggest();
        initAISuggestDay();
        initPreviewInlineEditing();
        initQuotationDatePickers();

        function addFlightSegment(data, opts) {
            opts = opts || {};
            var rowData = data || {};
            var $row = $(flightRowHtml(rowData));
            var $target = $('#qFlightRows');
            // "Add Another Segment" continues a journey only when the last card is already a multi-leg connection.
            if (opts.continueJourney) {
                var $lastJourney = $('#qFlightRows .q-flight-journey-card').last();
                if ($lastJourney.length && $lastJourney.find('.q-flight-row').length >= 1) {
                    $target = $lastJourney.find('.q-flight-journey-body').first();
                    $row.find('.f-journey-start').val('0');
                }
            }
            $target.append($row);
            initQuotationDatePickers($row);
            qInitSupplierSelect2($row.find('.f-supplier'), { placeholder: 'Select' });
            renumberFlightRows();
            recalcCosts();
            scheduleSyncReturnAirfareInclusion();
        }

        $('#qAddFlight').on('click', function () {
            addFlightSegment({});
        });

        $('#qAddFlightSegment').on('click', function () {
            addFlightSegment({}, { continueJourney: true });
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
            initQuotationDatePickers($('#qFlightRows'));
            renumberFlightRows();
            recalcCosts();
            scheduleSyncReturnAirfareInclusion();
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
            var rowData = data || {};
            if (!rowData.checkin && !rowData.check_in) {
                rowData = $.extend({}, buildNewHotelRowDefaults($panel), rowData);
            }
            var $row = $(hotelRowHtml(rowData));
            $panel.find('.q-hotel-rows').append($row);
            initHotelRow($row);
            qInitSupplierSelect2($row.find('.h-supplier'), { placeholder: 'Select' });
            syncHotelCheckoutFromNights($row);
            recalcCosts();
            saveFormDraftToStorage();
            scheduleItineraryMetaFromHotels();
            scheduleSyncReturnAirfareInclusion();
        };
        $(document).on('input change', '#qFlightRows .f-from, #qFlightRows .f-to, #qFlightRows .f-dep-date, #qFlightRows .f-dep-time, #qFlightRows .f-arr-date, #qFlightRows .f-arr-time', function () {
            refreshFlightLayovers();
            if ($(this).hasClass('f-from') || $(this).hasClass('f-to')) {
                scheduleSyncReturnAirfareInclusion(true);
            }
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
            refreshPricingSupplierNames();
            saveFormDraftToStorage();
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
            refreshPricingSupplierNames();
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
            refreshPricingSupplierNames();
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
            refreshPricingSupplierNames();
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
            refreshPricingSupplierNames();
            saveFormDraftToStorage();
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
            refreshPricingSupplierNames();
            saveFormDraftToStorage();
        });

        $(document).on('focus', '#qItinerarySupplierRows .q-itin-supplier', function () {
            $(this).data('prevSupplierVal', $(this).val() || '');
        });

        $(document).on('input change', '#qItinerarySupplierRows .q-itin-rate', function () {
            recalcCosts();
            saveFormDraftToStorage();
        });

        $('#qAddItinerarySupplier').on('click', function () {
            addItinerarySupplierRow({});
            recalcCosts();
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
            recalcCosts();
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
        $('#qAddHotelBtn').on('click', function () {
            var $panel = getHotelCategoryPanels().filter('.is-active').first();
            if (!$panel.length) {
                $panel = getHotelCategoryPanels().first();
            }
            if (!$panel.length) {
                addHotelCategory();
                $panel = getHotelCategoryPanels().first();
            }
            qActiveHotelCategoryId = String($panel.attr('data-cat-id') || '');
            refreshHotelCategoryTabs();
            var $row = $(hotelRowHtml(buildNewHotelRowDefaults($panel)));
            $panel.find('.q-hotel-rows').append($row);
            initHotelRow($row);
            qInitSupplierSelect2($row.find('.h-supplier'), { placeholder: 'Select' });
            syncHotelCheckoutFromNights($row);
            renderPricingSheets();
            saveFormDraftToStorage();
        });

        $(document).on('click', '#qHotelCategories .q-hotel-step-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var step = parseInt($(this).attr('data-hotel-step'), 10) || 0;
            var $control = $(this).closest('.q-hotel-stepper-control');
            var $input = $control.find('input.h-rooms, input.h-nights').first();
            if (!$input.length) {
                $input = $(this).closest('.q-hotel-field').find('input.h-rooms, input.h-nights').first();
            }
            if (!$input.length) {
                return;
            }
            var min = parseInt($input.attr('min'), 10);
            if (isNaN(min)) {
                min = 0;
            }
            var cur = parseInt($input.val(), 10);
            if (isNaN(cur)) {
                cur = 0;
            }
            var next = Math.max(min, cur + step);
            $input.val(String(next)).trigger('change').trigger('input');
            if ($input.hasClass('h-nights')) {
                syncHotelCheckoutFromNights($input.closest('.q-hotel-row'));
            }
            renderPricingSheets();
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
        $('#qRemoveHotelCategory').on('click', function () {
            if (getHotelCategoryPanels().length <= 1) {
                return;
            }
            if (!window.confirm('Remove this hotel option and its hotels?')) {
                return;
            }
            var $panel = getHotelCategoryPanels().filter('.is-active').first();
            if (!$panel.length) {
                return;
            }
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
            if (!id) {
                var $active = getActivePricingSheet();
                id = String($active.attr('data-cat-id') || '');
            }
            if (!id) {
                return;
            }
            openExtraCostModal(id);
        });
        $(document).on('click', '#qPricingSheetsHost .q-pricing-supplier-remove', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $cell = $(this).closest('.q-pricing-amount-cell');
            var key = String($cell.attr('data-cost-key') || '');
            var sourceIndex = $cell.attr('data-source-index');
            var catId = String($cell.closest('.q-pricing-option-sheet').attr('data-cat-id') || '');
            if (!key || sourceIndex == null || sourceIndex === '') {
                return;
            }
            if (!removePricingSupplierSource(key, sourceIndex, catId)) {
                return;
            }
            renderPricingSheets({ skipSnapshot: true });
            recalcCosts();
            saveFormDraftToStorage();
        });
        $(document).on('submit', '#qExtraCostForm', function (e) {
            e.preventDefault();
            saveExtraCostFromModal();
        });
        $(document).on('shown.bs.modal', '#qExtraCostModal', function () {
            $('#qExtraCostName').trigger('focus');
        });
        $(document).on('hidden.bs.modal', '#qExtraCostModal', function () {
            qExtraCostTargetCatId = '';
            $('#qExtraCostForm')[0].reset();
            $('#qExtraCostError').addClass('d-none').text('');
        });
        $(document).on('input change', '#qFlightRows input, #qFlightRows select, #qHotelCategories input, #qHotelCategories select, #qItineraryDays .q-day-title, #qItineraryDays .q-day-overnight, #qItineraryDays .q-day-meal, #q_nights, #q_tentative_date', function () {
            scheduleSyncReturnAirfareInclusion(true);
        });
        $(document).on('focusin', '#qFlightRows, #qHotelCategories, #qItineraryDays, #q_nights, #q_tentative_date', function () {
            scheduleSyncReturnAirfareInclusion(true);
        });
        $(document).on('blur', '#qed_inclusion, #qbody_inclusion .note-editable', function () {
            scheduleSyncReturnAirfareInclusion(true);
        });
        $(document).on('click', '.q-inclusions-section .q-section-accordion-head, .q-inclusions-section .q-terms-item-head[data-target="#qbody_inclusion"]', function () {
            window.setTimeout(function () {
                syncReturnAirfareInclusion(true);
            }, 80);
        });
        $(document).on('input change', '#qHotelCategories .q-hotel-row input, #qHotelCategories .q-hotel-row select', function () {
            saveFormDraftToStorage();
            scheduleItineraryMetaFromHotels();
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
            $row.find('.h-star-category').val('');
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
            var countryName = $(this).attr('data-country') || '';
            $row.find('.h-city-id').val(cityId);
            $row.find('.h-city').val(cityName);
            if (countryName) {
                $row.find('.h-country').val(countryName);
            }
            hideHotelMenus($row);
            if ($.trim($row.find('.h-name').val())) {
                showHotelNameSuggestions($row);
            }
            saveFormDraftToStorage();
            scheduleItineraryMetaFromHotels();
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
            scheduleItineraryMetaFromHotels();
        });

        $(document).on('change input', '.h-nights', function () {
            var $row = $(this).closest('.q-hotel-row');
            syncHotelCheckoutFromNights($row);
            saveFormDraftToStorage();
        });

        $(document).on('change', '.h-checkin', function () {
            var $row = $(this).closest('.q-hotel-row');
            var nights = parseInt($row.find('.h-nights').val(), 10);
            if (!isNaN(nights) && nights > 0) {
                syncHotelCheckoutFromNights($row);
            } else {
                syncHotelNightsFromDates($row);
            }
            saveFormDraftToStorage();
        });

        $(document).on('change', '.h-checkout', function () {
            var $row = $(this).closest('.q-hotel-row');
            syncHotelNightsFromDates($row);
            saveFormDraftToStorage();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.q-hotel-combo').length) {
                hideHotelMenus();
            }
        });

        var pricingNumberSelector = '.q-wizard-step[data-q-step="7"] input[type="number"]';

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
        $(document).on('input change', '.cc-label', function () {
            saveFormDraftToStorage();
        });
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
            var wasHotelRow = $row.hasClass('q-hotel-row') || selector === '.q-hotel-row';
            var wasFlightRow = $row.hasClass('q-flight-row') || selector === '.q-flight-row';
            $row.remove();
            renumberFlightRows();
            recalcCosts();
            if (wasHotelRow) {
                scheduleItineraryMetaFromHotels();
                scheduleSyncReturnAirfareInclusion();
            }
            if (wasFlightRow) {
                scheduleSyncReturnAirfareInclusion();
            }
        });

        $('#q_tentative_date').on('change', function () {
            // First hotel Check-In follows Travel Date when date is set/changed.
            syncFirstHotelCheckinFromTravelDate(true);
            scheduleItineraryRebuild();
            saveFormDraftToStorage();
        });
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

        $('#qPreviewEditBtn').on('click', function () {
            $('#qPreviewModal').modal('hide');
            try {
                var url = new URL(window.location.href);
                if (url.searchParams.has('preview')) {
                    url.searchParams.delete('preview');
                    window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
                }
            } catch (e) { /* ignore */ }
            $('html, body').animate({ scrollTop: 0 }, 200);
            window.setTimeout(function () {
                var $focus = $('#q_guest_name, #qGuestName, input[name="guest_name"]').filter(':visible').first();
                if ($focus.length) {
                    $focus.trigger('focus');
                }
            }, 250);
        });

        $('#qLoadTermsMasterBtn').on('click', function () {
            if (!window.confirm('Replace Terms & Policies fields with master content?')) {
                return;
            }
            loadTermsFromMaster(['exclusion', 'payment_policy', 'cancellation_policy', 'terms_conditions', 'other_details']);
        });

        $('#qPreviewPrintBtn').on('click', function () {
            if (typeof window.qPrintPreviewOnly === 'function') {
                window.qPrintPreviewOnly();
            }
        });

        window.qPrintPreviewOnly = function () {
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

            // Neutralize A4 “card” chrome copied from the modal (border/shadow/min-height/margins)
            styles = styles
                .replace(/box-shadow\s*:[^;]+;/gi, 'box-shadow:none!important;')
                .replace(/min-height\s*:\s*var\(--qp-page-h\)\s*;/gi, 'min-height:0!important;')
                .replace(/min-height\s*:\s*297mm\s*;/gi, 'min-height:0!important;');

            var printBleedCss =
                /* Last rules win — force full-bleed print, no outer frame/margin */
                '@page{size:A4;margin:0}' +
                '@page qp-last{size:A4;margin:0}' +
                '@page :first{margin:0}' +
                '@page :left{margin:0}' +
                '@page :right{margin:0}' +
                '@page :last{margin:0}' +
                'html,body{' +
                'margin:0!important;padding:0!important;background:#fff!important;' +
                'width:100%!important;max-width:none!important;min-width:0!important;' +
                '}' +
                'body.q-preview-print,body.q-preview-only{' +
                'margin:0!important;padding:0!important;background:#fff!important;' +
                'width:100%!important;min-width:0!important;' +
                '}' +
                '#qPreviewPrintArea.q-preview-doc,#qPreviewPrintArea,.q-preview-doc{' +
                'margin:0!important;' +
                'padding:5mm 5mm 0!important;' +
                'border:0!important;outline:0!important;' +
                'box-shadow:none!important;-webkit-box-shadow:none!important;' +
                'border-radius:0!important;' +
                'width:100%!important;max-width:none!important;min-width:0!important;' +
                'min-height:0!important;height:auto!important;' +
                'background:#fff!important;' +
                'box-sizing:border-box!important;' +
                'overflow:visible!important;' +
                'position:static!important;left:auto!important;top:auto!important;' +
                '}' +
                '.qp-sec-memberships,.qp-sec-support{' +
                'margin-left:-5mm!important;margin-right:-5mm!important;' +
                'width:calc(100% + 10mm)!important;max-width:none!important;' +
                '}' +
                '@media print{' +
                '@page{size:A4;margin:0}' +
                'html,body{' +
                'margin:0!important;padding:0!important;background:#fff!important;' +
                'width:100%!important;max-width:none!important;' +
                '-webkit-print-color-adjust:exact;print-color-adjust:exact;' +
                '}' +
                '#qPreviewPrintArea.q-preview-doc,#qPreviewPrintArea,.q-preview-doc{' +
                'margin:0!important;padding:5mm 5mm 0!important;' +
                'border:0!important;box-shadow:none!important;' +
                'width:100%!important;max-width:none!important;min-width:0!important;' +
                'min-height:0!important;background:#fff!important;' +
                'box-sizing:border-box!important;overflow:visible!important;' +
                '}' +
                '.qp-print-last-page{' +
                'page:qp-last!important;break-before:page!important;page-break-before:always!important;' +
                'display:table!important;width:100%!important;' +
                'height:292mm!important;min-height:292mm!important;max-height:292mm!important;' +
                'table-layout:fixed!important;border-collapse:collapse!important;' +
                'margin:0!important;padding:0!important;' +
                '}' +
                '.qp-last-main{display:table-row!important;height:100%!important;}' +
                '.qp-last-main-inner{display:table-cell!important;vertical-align:top!important;height:100%!important;}' +
                '.qp-last-foot{display:table-row!important;height:1px!important;}' +
                '.qp-last-foot-inner{display:table-cell!important;vertical-align:bottom!important;}' +
                '.qp-print-last-page .qp-sec-memberships,.qp-print-last-page .qp-sec-support{' +
                'margin-left:-5mm!important;margin-right:-5mm!important;' +
                'width:calc(100% + 10mm)!important;margin-top:0!important;margin-bottom:0!important;' +
                '}' +
                '.qp-support-footer{margin-bottom:0!important;padding-bottom:0!important;' +
                'border-bottom:18px solid #e11d2e!important;}' +
                '.q-preview-day,.qp-day,.qp-rev-card,.qp-hotel-row-card,.qp-flight-seg-card,' +
                '.qp-info-card,.qp-acc-card,.qp-tour-card,.qp-notes-card,.qp-terms-card{' +
                'break-inside:avoid!important;page-break-inside:avoid!important;' +
                '}' +
                '}';

            var printWin = window.open('', '_blank');
            if (!printWin) {
                alert('Please allow pop-ups to print.');
                return;
            }
            printWin.document.open();
            printWin.document.write(
                '<!DOCTYPE html><html><head><title>Quotation Preview</title><meta charset="utf-8">' +
                '<meta name="viewport" content="width=device-width,initial-scale=1">' +
                '<link rel="stylesheet" href="' + esc(absUrl('plugins/fontawesome-free/css/all.min.css')) + '">' +
                '<style>' +
                styles +
                'html,body{margin:0!important;padding:0!important;background:#fff!important;}' +
                'body.q-preview-print{margin:0!important;padding:0!important;background:#fff!important;}' +
                '.q-preview-doc{' +
                'box-sizing:border-box!important;' +
                'width:100%!important;max-width:none!important;min-width:0!important;' +
                'min-height:0!important;' +
                'margin:0!important;' +
                'padding:5mm 5mm 0!important;' +
                'background:#fff!important;' +
                'border:0!important;' +
                'border-radius:0!important;' +
                'box-shadow:none!important;' +
                'overflow:visible!important;' +
                '-webkit-print-color-adjust:exact!important;' +
                'print-color-adjust:exact!important;' +
                '}' +
                '.qp-sec-memberships,.qp-sec-support{' +
                'margin-left:-5mm!important;' +
                'margin-right:-5mm!important;' +
                'width:calc(100% + 10mm)!important;' +
                'max-width:none!important;' +
                '}' +
                '.qp-sec-support{margin-bottom:0!important;break-inside:avoid!important;page-break-inside:avoid!important;}' +
                '.qp-support-footer{' +
                'border-top:1px solid #d1d5db!important;' +
                'border-bottom:18px solid #e11d2e!important;' +
                'break-inside:avoid!important;' +
                'page-break-inside:avoid!important;' +
                'margin-bottom:0!important;' +
                '-webkit-print-color-adjust:exact!important;' +
                'print-color-adjust:exact!important;' +
                '}' +
                '.qp-support-bar{display:none!important;}' +
                '.qp-support-ico.is-red{background:#e11d2e!important;}' +
                '.qp-support-ico.is-dark{background:#1f2937!important;}' +
                '.qp-acc-grid{display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:6px!important;}' +
                '.qp-rev-grid{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;}' +
                '.qp-mem-row{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;}' +
                '.qp-mem-cell,.qp-mem-cell+.qp-mem-cell{border:0!important;border-left:0!important;}' +
                '.qp-mem-logo{display:block!important;visibility:visible!important;width:52px!important;height:52px!important;object-fit:contain!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}' +
                '.qp-sec-memberships{border:0!important;border-top:0!important;}' +
                '.qp-print-last-page{' +
                'page:qp-last!important;' +
                'break-before:page!important;' +
                'page-break-before:always!important;' +
                'display:table!important;' +
                'width:100%!important;' +
                'height:292mm!important;' +
                'min-height:292mm!important;' +
                'max-height:292mm!important;' +
                'table-layout:fixed!important;' +
                'border-collapse:collapse!important;' +
                'box-sizing:border-box!important;' +
                'margin:0!important;' +
                'padding:0!important;' +
                '}' +
                '.qp-last-main{display:table-row!important;height:100%!important;}' +
                '.qp-last-main-inner{display:table-cell!important;vertical-align:top!important;height:100%!important;}' +
                '.qp-last-foot{display:table-row!important;height:1px!important;}' +
                '.qp-last-foot-inner{display:table-cell!important;vertical-align:bottom!important;}' +
                '.qp-print-last-page .qp-sec-acc{margin-top:2mm!important;padding-top:0!important;}' +
                '.qp-print-last-page .qp-sec-reviews{margin-top:4mm!important;padding-top:2mm!important;margin-bottom:0!important;}' +
                '.qp-print-last-page .qp-sec-memberships{margin-top:0!important;margin-bottom:0!important;padding-bottom:0!important;}' +
                '.qp-print-last-page .qp-sec-support{margin-top:0!important;margin-bottom:0!important;padding-bottom:0!important;break-inside:avoid!important;page-break-inside:avoid!important;}' +
                '@page{size:A4;margin:0;}' +
                '@page qp-last{size:A4;margin:0;}' +
                '@page :last{margin:0;}' +
                '@media print{' +
                'html,body{margin:0!important;padding:0!important;background:#fff!important;width:100%!important;max-width:none!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
                '#qPreviewPrintArea.q-preview-doc,#qPreviewPrintArea,.q-preview-doc{' +
                'position:static!important;' +
                'box-sizing:border-box!important;' +
                'width:100%!important;' +
                'min-width:0!important;' +
                'max-width:none!important;' +
                'min-height:0!important;' +
                'height:auto!important;' +
                'margin:0!important;' +
                'padding:5mm 5mm 0!important;' +
                'border:0!important;' +
                'box-shadow:none!important;' +
                'overflow:visible!important;' +
                '-webkit-box-decoration-break:clone!important;' +
                'box-decoration-break:clone!important;' +
                '-webkit-print-color-adjust:exact!important;' +
                'print-color-adjust:exact!important;' +
                '}' +
                '.qp-cost-notes-row,.qp-terms-card,.qp-sec-excl,.qp-itin-head{' +
                'margin-top:8mm!important;' +
                '}' +
                '.qp-print-last-page{' +
                'page:qp-last!important;' +
                'break-before:page!important;' +
                'page-break-before:always!important;' +
                'display:table!important;' +
                'width:100%!important;' +
                'height:292mm!important;' +
                'min-height:292mm!important;' +
                'max-height:292mm!important;' +
                'table-layout:fixed!important;' +
                'border-collapse:collapse!important;' +
                'margin:0!important;' +
                'padding:0!important;' +
                '}' +
                '.qp-last-main{display:table-row!important;height:100%!important;}' +
                '.qp-last-main-inner{display:table-cell!important;vertical-align:top!important;height:100%!important;}' +
                '.qp-last-foot{display:table-row!important;height:1px!important;}' +
                '.qp-last-foot-inner{display:table-cell!important;vertical-align:bottom!important;}' +
                '.qp-print-last-page .qp-sec-acc{margin-top:2mm!important;padding-top:0!important;}' +
                '.qp-print-last-page .qp-sec-reviews{margin-top:4mm!important;padding-top:2mm!important;margin-bottom:0!important;}' +
                '.qp-print-last-page .qp-sec-memberships{margin-top:0!important;margin-bottom:0!important;padding-bottom:0!important;}' +
                '.qp-print-last-page .qp-sec-support{margin-top:0!important;margin-bottom:0!important;padding-bottom:0!important;break-inside:avoid!important;page-break-inside:avoid!important;}' +
                '.qp-sec-memberships,.qp-sec-support{' +
                'margin-left:-5mm!important;' +
                'margin-right:-5mm!important;' +
                'width:calc(100% + 10mm)!important;' +
                '}' +
                '.qp-sec-memberships{border:0!important;}' +
                '.qp-mem-cell,.qp-mem-cell+.qp-mem-cell{border:0!important;border-left:0!important;}' +
                '.qp-mem-logo{display:block!important;visibility:visible!important;width:52px!important;height:52px!important;object-fit:contain!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}' +
                '.qp-sec-support{margin-bottom:0!important;break-inside:avoid!important;page-break-inside:avoid!important;}' +
                '.qp-support-footer{border-bottom:18px solid #e11d2e!important;break-inside:avoid!important;page-break-inside:avoid!important;margin-bottom:0!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}' +
                '.qp-support-bar{display:none!important;}' +
                '.q-preview-editable,.q-preview-cell-edit{background:transparent!important;outline:none!important;box-shadow:none!important;}' +
                '}' +
                /* Force desktop A4 grids even if a screen media query still matches */
                '.q-preview-doc .qp-hotel-col-head,' +
                '.q-preview-doc .qp-hotel-row-fields{' +
                'display:grid!important;' +
                'grid-template-columns:minmax(0,1fr) minmax(0,2.2fr) minmax(0,.42fr) minmax(0,1fr) minmax(0,.9fr) minmax(0,.9fr) minmax(0,.5fr)!important;' +
                '}' +
                '.q-preview-doc .qp-hotel-field-hotel,' +
                '.q-preview-doc .qp-hotel-field-city,' +
                '.q-preview-doc .qp-hotel-field-room{grid-column:auto!important;}' +
                '.q-preview-doc .qp-hotel-col-head{display:grid!important;}' +
                '.q-preview-doc .qp-hotel-sec-rule{display:block!important;}' +
                '.q-preview-doc .qp-hotel-sec-slogan{width:auto!important;text-align:right!important;white-space:nowrap!important;}' +
                '.q-preview-doc .qp-acc-grid{display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:6px!important;}' +
                '.q-preview-doc .qp-acc-card{min-width:0!important;}' +
                '.q-preview-doc .qp-rev-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important;}' +
                '.q-preview-doc .qp-info-card.qp-cols-3{grid-template-columns:repeat(3,1fr)!important;}' +
                '.q-preview-doc .qp-info-card.qp-cols-5{grid-template-columns:repeat(5,1fr)!important;}' +
                '.q-preview-doc .qp-info-card.qp-cols-5.qp-travel-details{' +
                'display:flex!important;flex-wrap:nowrap!important;align-items:stretch!important;' +
                'grid-template-columns:none!important;' +
                '}' +
                '.q-preview-doc .qp-travel-details .qp-info-cell,' +
                '.q-preview-doc .qp-travel-details .qp-info-cell.qp-cell-wide,' +
                '.q-preview-doc .qp-travel-details .qp-info-cell.qp-cell-grow,' +
                '.q-preview-doc .qp-travel-details .qp-info-cell.qp-cell-date,' +
                '.q-preview-doc .qp-travel-details .qp-info-cell.qp-cell-narrow{' +
                'flex:1 1 0!important;width:auto!important;min-width:0!important;max-width:none!important;' +
                'border-right:1px solid #d7e0ea!important;border-bottom:none!important;' +
                '}' +
                '.q-preview-doc .qp-travel-details .qp-info-cell.qp-cell-wide{flex:2.2 1 0!important;min-width:9em!important;}' +
                '.q-preview-doc .qp-travel-details .qp-info-cell.qp-cell-grow{flex:2 1 0!important;min-width:9em!important;}' +
                '.q-preview-doc .qp-travel-details .qp-info-cell.qp-cell-date{flex:0 0 auto!important;width:max-content!important;min-width:6.5em!important;}' +
                '.q-preview-doc .qp-travel-details .qp-info-cell.qp-cell-narrow{flex:0 0 auto!important;width:max-content!important;}' +
                '.q-preview-doc .qp-info-cell{border-right:1px solid #d7e0ea!important;border-bottom:none!important;}' +
                '.q-preview-doc .qp-info-cell:last-child{border-right:none!important;}' +
                '.q-preview-doc .qp-doc-head-top{flex-direction:row!important;align-items:flex-start!important;text-align:left!important;}' +
                '.q-preview-doc .qp-cost-notes-row{flex-direction:row!important;}' +
                '.q-preview-doc .qp-flight-sec-rule{display:block!important;}' +
                '.q-preview-doc .qp-flight-seg-card{min-width:0!important;}' +
                '.q-preview-doc .qp-day-main.has-photo{grid-template-columns:minmax(0,1fr) 180px!important;}' +
                '.q-preview-doc .qp-trust-stats{grid-template-columns:repeat(4,minmax(0,1fr))!important;}' +
                '.q-preview-doc .qp-foot-contacts{grid-template-columns:repeat(4,minmax(0,1fr))!important;}' +
                printBleedCss +
                '</style></head><body class="q-preview-print q-preview-only">' +
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

            // Wait for accreditation / logo images to load before printing
            (function waitForPrintImages() {
                var doc = printWin.document;
                var imgs = doc ? Array.prototype.slice.call(doc.images || []) : [];
                if (!imgs.length) {
                    window.setTimeout(triggerPrint, 400);
                    return;
                }
                var pending = imgs.length;
                var finished = false;
                var finish = function () {
                    if (finished) return;
                    finished = true;
                    window.setTimeout(triggerPrint, 150);
                };
                var onOne = function () {
                    pending -= 1;
                    if (pending <= 0) finish();
                };
                imgs.forEach(function (img) {
                    try {
                        img.loading = 'eager';
                        if (img.complete && img.naturalWidth > 0) {
                            onOne();
                            return;
                        }
                        img.addEventListener('load', onOne);
                        img.addEventListener('error', onOne);
                    } catch (e) {
                        onOne();
                    }
                });
                window.setTimeout(finish, 6000);
            })();
        };

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
                        var failMsg = (res && res.message) || 'Could not save.';
                        if (isPreviewOnlyMode()) {
                            notifyParentPreviewState({ saveError: true, message: failMsg, dirty: true });
                            window.alert(failMsg);
                        } else {
                            $('#qAlert').html('<div class="alert alert-danger">' + esc(failMsg) + '</div>');
                            window.scrollTo(0, 0);
                        }
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
                    if (isPreviewOnlyMode()) {
                        notifyParentPreviewState({ saveError: true, message: msg, dirty: true });
                        window.alert(msg);
                    } else {
                        $('#qAlert').html('<div class="alert alert-danger">' + esc(msg) + '</div>');
                        window.scrollTo(0, 0);
                    }
                })
                .always(function () {
                    if ($btn && $btn.length) {
                        $btn.prop('disabled', false).html(btnDefaultHtml);
                    }
                    if (isPreviewOnlyMode()) {
                        notifyParentPreviewState({ saving: false });
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

        function saveQuotation($btnOverride, options) {
            options = options || {};
            var previewOnlySave = !!options.previewOnly || isPreviewOnlyMode();
            var p;
            try {
                p = collectPayload();
            } catch (err) {
                alert('Could not prepare quotation data. ' + (err && err.message ? err.message : ''));
                return;
            }
            if (!p.guest_name) {
                alert('Please enter the guest name.');
                if (!previewOnlySave) {
                    expandWizardSection(1);
                    setWizardStep(1);
                }
                return;
            }
            var $btn = ($btnOverride && $btnOverride.length) ? $btnOverride : $('#qSaveBtn');
            var btnDefaultHtml = ($btn.length && $btn.is('#qPreviewSaveBtn')) || previewOnlySave
                ? '<i class="fas fa-save mr-1"></i>Save Changes'
                : '<i class="fas fa-save mr-1"></i>Save Quotation';
            if ($btn.length) {
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');
            }
            if (previewOnlySave) {
                notifyParentPreviewState({ saving: true, dirty: true });
            }
            postQuotationSave(p, 'publish', $btn, btnDefaultHtml, function (res) {
                setPreviewDirty(false);
                if (res.id) {
                    $('#q_id').val(res.id);
                }
                saveFormDraftToStorage();

                // Embedded Leads preview: save in place, do not redirect away.
                if (previewOnlySave) {
                    notifyParentPreviewState({
                        saved: true,
                        dirty: false,
                        message: res.message || 'Quotation saved.',
                        id: res.id || null,
                        version: res.version || null,
                        quotation_uid: res.quotation_uid || null
                    });
                    return;
                }

                var successHtml = esc(res.message || 'Quotation saved.');
                if (res.quotation_uid) {
                    successHtml += ' (' + esc(res.quotation_uid) + ')';
                }
                if (res.version) {
                    successHtml += ' — now at <strong>v' + esc(String(res.version)) + '</strong>.';
                }
                $('#qAlert').html('<div class="alert alert-success">' + successHtml + '</div>');
                window.scrollTo(0, 0);
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

        window.qSavePreviewOnly = function () {
            flushPreviewActiveEdit();
            window.setTimeout(function () {
                var $previewBtn = $('#qPreviewSaveBtn');
                saveQuotation($previewBtn.length ? $previewBtn : $(), { previewOnly: true });
            }, 80);
        };

        window.qIsPreviewDirty = function () {
            return !!previewDirty;
        };

        window.addEventListener('message', function (ev) {
            var data = ev && ev.data;
            if (!data || typeof data !== 'object') {
                return;
            }
            if (data.type === 'mz-quotation-preview-print') {
                if (typeof window.qPrintPreviewOnly === 'function') {
                    window.qPrintPreviewOnly();
                }
                return;
            }
            if (data.type !== 'mz-quotation-preview-save') {
                return;
            }
            if (typeof window.qSavePreviewOnly === 'function') {
                window.qSavePreviewOnly();
            }
        });

        $('#quotationForm').on('submit', function (e) {
            e.preventDefault();
            saveQuotation();
        });
        $('#qSaveBtn').on('click', function (e) {
            e.preventDefault();
            flushPreviewActiveEdit();
            window.setTimeout(function () {
                saveQuotation($('#qSaveBtn'));
            }, 80);
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

        // When Guests are updated on the linked lead, refresh Adults/Children here
        window.addEventListener('message', function (ev) {
            var data = ev && ev.data;
            if (!data || data.type !== 'mz-data-changed' || data.resource !== 'leads') {
                return;
            }
            var myLeadId = parseInt($('#q_lead_id').val(), 10) || 0;
            var msgLeadId = parseInt(data.lead_id, 10) || 0;
            if (!myLeadId || !msgLeadId || myLeadId !== msgLeadId) {
                return;
            }
            if (data.no_of_adults != null && data.no_of_adults !== '') {
                var adults = Math.max(1, parseInt(data.no_of_adults, 10) || 1);
                $('#q_adults').val(adults).trigger('change');
            }
            if (data.no_of_children != null && data.no_of_children !== '') {
                var children = Math.max(0, parseInt(data.no_of_children, 10) || 0);
                $('#q_children').val(children).trigger('change');
            }
            if (Array.isArray(data.children_ages)) {
                writeChildrenAges(data.children_ages);
                renderTourCostRows();
                recalcCosts();
            }
        });

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
            initQuotationDatePickers();
            saveFormDraftToStorage();
        }
        ensureHotelCategoriesReady();
        ensureItinerarySupplierRows();
        initQuotationDatePickers();
        // Fill empty first-hotel Check-In from Travel Date (Tentative Date).
        syncFirstHotelCheckinFromTravelDate(false);
        if (!$('#qPricingSheetsHost .q-pricing-option-sheet').length) {
            renderPricingSheets();
        }
        if (!tourCostRowsPresent()) {
            renderTourCostRows();
        }
        qInitSupplierSelect2In();

        // Open preview automatically when arriving from Leads view icon (quoted stage).
        (function autoOpenPreviewFromQuery() {
            var wantsPreview = false;
            var previewOnly = false;
            try {
                var params = new URLSearchParams(window.location.search || '');
                wantsPreview = params.get('preview') === '1' || params.get('preview') === 'true'
                    || params.get('preview_only') === '1';
                previewOnly = params.get('preview_only') === '1' || document.body.classList.contains('q-preview-only');
            } catch (e) {
                wantsPreview = /[?&]preview(?:_only)?=1(?:&|$)/.test(window.location.search || '');
                previewOnly = /[?&]preview_only=1(?:&|$)/.test(window.location.search || '')
                    || document.body.classList.contains('q-preview-only');
            }
            if (!wantsPreview) {
                return;
            }

            function renderPreviewOnlyPage() {
                var html;
                try {
                    html = buildPreviewHtml(collectPayload());
                } catch (err) {
                    html = '<div style="padding:1.5rem;color:#b91c1c;">Could not prepare preview.</div>';
                }

                // Remove AdminLTE shell height entirely — it was causing a huge blank gap
                // above the quotation inside the Leads iframe modal.
                $('.wrapper').hide();
                $('#qPreviewModal').hide();
                $('.modal-backdrop').remove();
                $('body')
                    .addClass('q-preview-only')
                    .removeClass('modal-open layout-fixed sidebar-mini sidebar-collapse hold-transition')
                    .css({ paddingRight: '', overflow: 'auto', height: 'auto', minHeight: 0 });

                var $mount = $('#qPreviewPrintArea');
                if (!$mount.length) {
                    $mount = $('<div id="qPreviewPrintArea" class="q-preview-doc"></div>');
                    $('body').prepend($mount);
                } else if ($mount.closest('.wrapper, #qPreviewModal').length) {
                    $mount = $mount.detach();
                    $('body').prepend($mount);
                }
                $mount.attr('id', 'qPreviewPrintArea').addClass('q-preview-doc').html(html).show();

                // Notify parent Leads modal that preview content is ready.
                try {
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage({ type: 'mz-quotation-preview-ready' }, '*');
                    }
                } catch (e2) { /* ignore */ }
            }

            window.setTimeout(function () {
                try {
                    if (previewOnly) {
                        renderPreviewOnlyPage();
                    } else {
                        openQuotationPreview();
                    }
                } catch (err) { /* ignore */ }
            }, previewOnly ? 150 : 350);
        })();
    });

})(jQuery);
