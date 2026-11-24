(function ($) {
    'use strict';

    var contacts = [];
    var selectedPlaces = [];
    var cityTimer = null;
    var placeTimer = null;
    var cityRequest = null;
    var table = null;
    var assocState = { supplierId: 0, page: 1, perPage: 10, timer: null, xhr: null };

    // admin root URL (page lives at .../admin/crm/suppliers.php)
    var ADMIN_BASE = location.href.replace(/[?#].*$/, '').replace(/\/crm\/[^\/]*$/, '/');

    function absUrl(path) {
        if (!path) {
            return '';
        }
        if (/^(https?:)?\/\//i.test(path) || /^data:/i.test(path)) {
            return path;
        }
        return ADMIN_BASE + path.replace(/^\//, '');
    }

    function postJson(url, data) {
        return $.ajax({
            url: url,
            type: 'POST',
            data: data,
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
    }

    function ajaxErrorMessage(xhr, fallback) {
        fallback = fallback || 'Request failed.';
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            return xhr.responseJSON.message;
        }
        if (xhr && xhr.responseText) {
            var text = $.trim(xhr.responseText);
            if (text.indexOf('{') === 0) {
                try {
                    var parsed = JSON.parse(text);
                    if (parsed && parsed.message) {
                        return parsed.message;
                    }
                } catch (e) { /* ignore */ }
            }
            if (text.length && text.length < 300) {
                return text;
            }
        }
        return fallback;
    }

    function showAlert(msg, type) {
        type = type || 'success';
        $('#supplierAlert').html(
            '<div class="alert alert-' + type + ' alert-dismissible fade show">' +
            msg +
            '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>'
        );
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function moneyInr(n) {
        n = parseFloat(n);
        if (isNaN(n)) n = 0;
        return '₹ ' + Math.round(n).toLocaleString('en-IN');
    }

    function fmtDate(iso) {
        var s = String(iso || '').trim();
        var m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!m) return s || '—';
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return parseInt(m[3], 10) + ' ' + (months[parseInt(m[2], 10) - 1] || m[2]) + ' ' + m[1];
    }

    function renderAssocSummary(summary) {
        summary = summary || {};
        var cards = [
            { k: 'Total Associated Tours', v: summary.total_associated_tours || 0 },
            { k: 'Total Services Provided', v: summary.total_services || 0 },
            { k: 'Total Business Value', v: moneyInr(summary.total_business_value) },
            { k: 'Total Paid Amount', v: moneyInr(summary.total_paid) },
            { k: 'Total Outstanding', v: moneyInr(summary.total_outstanding), out: true },
            { k: 'Pending Payments', v: summary.pending_payments || 0, out: true }
        ];
        $('#spAssocSummary').html(cards.map(function (c) {
            return '<div class="sp-assoc-card' + (c.out ? ' is-out' : '') + '"><div class="k">' +
                escapeHtml(c.k) + '</div><div class="v">' + escapeHtml(String(c.v)) + '</div></div>';
        }).join(''));
    }

    function renderAssocPager(pagination) {
        pagination = pagination || {};
        var page = parseInt(pagination.page, 10) || 1;
        var pages = parseInt(pagination.pages, 10) || 1;
        var total = parseInt(pagination.total, 10) || 0;
        $('#spAssocCount').text(total === 1 ? '1 service' : (total + ' services'));
        if (pages <= 1) {
            $('#spAssocPager').empty();
            return;
        }
        $('#spAssocPager').html(
            '<button type="button" class="btn btn-sm btn-outline-secondary js-sp-assoc-page" data-page="' +
            (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>&lsaquo;</button>' +
            '<span class="small text-muted px-1">' + page + ' / ' + pages + '</span>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary js-sp-assoc-page" data-page="' +
            (page + 1) + '"' + (page >= pages ? ' disabled' : '') + '>&rsaquo;</button>'
        );
    }

    function renderAssocTable(rows) {
        rows = rows || [];
        if (!rows.length) {
            $('#spAssocTableWrap').html(
                '<div class="sp-assoc-empty"><i class="fas fa-link mb-2 d-block" style="font-size:1.4rem;opacity:.45"></i>' +
                'No tours or services associated with this supplier yet.</div>'
            );
            return;
        }
        var html = '<table class="sp-assoc-table"><thead><tr>' +
            '<th>Booking ID</th><th>Guest Name</th><th>Destination</th><th>Travel Date</th>' +
            '<th>Assigned Service</th><th>Total Cost</th><th>Paid</th><th>Outstanding</th>' +
            '<th>Payment</th><th>Booking Status</th><th>Actions</th>' +
            '</tr></thead><tbody>';
        var lastBooking = null;
        rows.forEach(function (r) {
            var bookingId = parseInt(r.booking_id, 10) || 0;
            var groupStart = lastBooking !== bookingId;
            lastBooking = bookingId;
            html += '<tr' + (groupStart ? ' class="is-group-start"' : '') + '>' +
                '<td><strong>' + escapeHtml(r.code || ('#' + bookingId)) + '</strong></td>' +
                '<td>' + escapeHtml(r.guest || '—') + '</td>' +
                '<td>' + escapeHtml(r.destination || '—') + '</td>' +
                '<td>' + escapeHtml(fmtDate(r.travel_date)) + '</td>' +
                '<td>' + escapeHtml(r.service_label || '—') + '</td>' +
                '<td>' + escapeHtml(moneyInr(r.total)) + '</td>' +
                '<td>' + escapeHtml(moneyInr(r.paid)) + '</td>' +
                '<td>' + escapeHtml(moneyInr(r.outstanding)) + '</td>' +
                '<td><span class="sp-pay-pill ' + escapeHtml(r.payment_cls || 'is-unpaid') + '">' +
                escapeHtml(r.payment_label || 'Unpaid') + '</span></td>' +
                '<td>' + escapeHtml(r.booking_status || '—') + '</td>' +
                '<td><button type="button" class="btn btn-sm js-sp-view-tour" data-id="' +
                escapeHtml(String(bookingId)) + '"><i class="far fa-eye mr-1"></i>View</button></td>' +
                '</tr>';
        });
        html += '</tbody></table>';
        $('#spAssocTableWrap').html(html);
    }

    function loadAssociatedTours() {
        var id = assocState.supplierId;
        if (!id) return;
        if (assocState.xhr && typeof assocState.xhr.abort === 'function') {
            try { assocState.xhr.abort(); } catch (e) { /* ignore */ }
        }
        $('#spAssocTableWrap').html('<div class="text-muted text-center py-3">Loading associated tours…</div>');
        assocState.xhr = $.getJSON(absUrl('crm/ajax/supplier_associated_tours.php'), {
            supplier_id: id,
            q: $('#spTourSearch').val() || '',
            destination: $('#spTourDest').val() || '',
            date_from: $('#spTourFrom').val() || '',
            date_to: $('#spTourTo').val() || '',
            tour_status: $('#spTourStatus').val() || '',
            payment_status: $('#spTourPay').val() || '',
            page: assocState.page,
            per_page: assocState.perPage
        }).done(function (res) {
            if (!res || !res.success) {
                $('#spAssocTableWrap').html(
                    '<div class="sp-assoc-empty text-danger">' +
                    escapeHtml((res && res.message) || 'Could not load associated tours.') + '</div>'
                );
                renderAssocSummary({});
                renderAssocPager({});
                return;
            }
            if (res.supplier) {
                $('#spPortfolioTitle').text(res.supplier.name || 'Supplier Portfolio');
                $('#spPortfolioSub').text(
                    (res.supplier.company_name && res.supplier.company_name !== res.supplier.name)
                        ? res.supplier.company_name
                        : 'Associated tours and service payments'
                );
            }
            renderAssocSummary(res.summary || {});
            renderAssocTable(res.rows || []);
            renderAssocPager(res.pagination || {});
        }).fail(function (xhr) {
            if (xhr && xhr.statusText === 'abort') return;
            $('#spAssocTableWrap').html(
                '<div class="sp-assoc-empty text-danger">Could not load associated tours.</div>'
            );
        });
    }

    function scheduleAssocReload() {
        if (assocState.timer) {
            window.clearTimeout(assocState.timer);
        }
        assocState.timer = window.setTimeout(function () {
            assocState.timer = null;
            assocState.page = 1;
            loadAssociatedTours();
        }, 220);
    }

    function openSupplierPortfolio(id, name) {
        id = parseInt(id, 10) || 0;
        if (!id) return;
        assocState.supplierId = id;
        assocState.page = 1;
        $('#spPortfolioId').val(id);
        $('#spPortfolioTitle').text(name || 'Supplier Portfolio');
        $('#spPortfolioSub').text('Associated tours and service payments');
        $('#spTourSearch, #spTourDest, #spTourFrom, #spTourTo').val('');
        $('#spTourStatus, #spTourPay').val('');
        renderAssocSummary({});
        $('#spAssocTableWrap').html('<div class="text-muted text-center py-3">Loading associated tours…</div>');
        $('#spAssocPager').empty();
        $('#supplierPortfolioModal').modal('show');
        loadAssociatedTours();
    }

    function defaultContact() {
        return {
            contact_name: '',
            designation: '',
            email: '',
            mobile: '',
            is_primary: 0
        };
    }

    function ensurePrimaryContact() {
        if (!contacts.length) {
            return;
        }
        var hasPrimary = contacts.some(function (c) { return !!c.is_primary; });
        if (!hasPrimary) {
            contacts[0].is_primary = 1;
        }
        var seen = false;
        contacts = contacts.map(function (c) {
            if (c.is_primary && !seen) {
                seen = true;
                return c;
            }
            return Object.assign({}, c, { is_primary: 0 });
        });
    }

    function contactRowHtml(c, isFirst) {
        c = c || defaultContact();
        var actionClass = isFirst ? 'js-add-contact-row' : 'js-remove-contact-row is-remove';
        var actionIcon = isFirst ? 'fa-plus' : 'fa-minus';
        var actionTitle = isFirst ? 'Add contact' : 'Remove contact';
        var labelOrSpacer = function (labelHtml) {
            return isFirst ? labelHtml : '<label class="contact-action-label">&nbsp;</label>';
        };
        return '' +
            '<div class="form-row contact-row align-items-end">' +
            '<div class="form-group col-md-4">' +
            labelOrSpacer('<label>Contact Name</label>') +
            '<input type="text" class="form-control js-contact-name" value="' + escapeHtml(c.contact_name || '') + '" autocomplete="off">' +
            '</div>' +
            '<div class="form-group col-md-4">' +
            labelOrSpacer('<label>Email <span class="text-danger">*</span></label>') +
            '<input type="email" class="form-control js-contact-email" value="' + escapeHtml(c.email || '') + '" autocomplete="off">' +
            '</div>' +
            '<div class="form-group col-md-3">' +
            labelOrSpacer('<label>Mobile No</label>') +
            '<input type="text" class="form-control js-contact-mobile" value="' + escapeHtml(c.mobile || '') + '" autocomplete="tel">' +
            '</div>' +
            '<div class="form-group col-md-1 contact-action-wrap">' +
            '<label class="contact-action-label">&nbsp;</label>' +
            '<button type="button" class="btn btn-contact-action ' + actionClass + '" title="' + actionTitle + '">' +
            '<i class="fas ' + actionIcon + '"></i></button>' +
            '</div></div>';
    }

    function renderContactRows() {
        ensurePrimaryContact();
        var rows = contacts.length ? contacts : [defaultContact()];
        var html = '';
        rows.forEach(function (c, idx) {
            html += contactRowHtml(c, idx === 0);
        });
        $('#supplierContactRows').html(html);
    }

    function syncContactsFromDom() {
        var next = [];
        $('#supplierContactRows .contact-row').each(function (idx) {
            var $row = $(this);
            var row = {
                contact_name: ($row.find('.js-contact-name').val() || '').trim(),
                designation: '',
                email: ($row.find('.js-contact-email').val() || '').trim(),
                mobile: ($row.find('.js-contact-mobile').val() || '').trim(),
                is_primary: idx === 0 ? 1 : 0
            };
            if (row.contact_name || row.email || row.mobile) {
                next.push(row);
            }
        });
        contacts = next;
        ensurePrimaryContact();
    }

    function updateCityClearState() {
        var has = !!($.trim($('#supplierCitySearch').val() || ''));
        $('#supplierCityWrap').toggleClass('has-city', has);
    }

    function renderServiceSummary() {
        var labels = window.SUPPLIER_SERVICE_MAP || {};
        var selected = [];
        $('.js-supplier-of:checked').each(function () {
            selected.push(labels[$(this).val()] || $(this).val());
        });
        var $summary = $('#supplierServicesSummary');
        if (!selected.length) {
            $summary.html('<span class="supplier-services-placeholder">Select</span>');
            return;
        }
        $summary.text(selected.join(' , '));
    }

    function renderTypeSummary() {}

    function resetSupplierForm() {
        $('#supplierId').val('0');
        $('#supplierName').val('');
        $('#supplierWebsite').val('');
        $('#supplierCityId').val('0');
        $('#supplierCityName').val('');
        $('#supplierCountryName').val('');
        $('#supplierCitySearch').val('');
        $('#supplierAddress').val('');
        $('#supplierActive').val('1');
        $('#placeSearch').val('');
        $('.js-supplier-of').prop('checked', false);
        contacts = [defaultContact()];
        selectedPlaces = [];
        renderContactRows();
        renderServiceSummary();
        renderPlaceTags();
        updateCityClearState();
        hideSearchDropdown($('#citySearchDropdown'));
        hideSearchDropdown($('#placeSearchDropdown'));
        $('#supplierOfPanel').removeClass('is-open');
        $('#supplierServicesToggle').attr('aria-expanded', 'false');
    }

    function openModal(title) {
        var isEdit = title === 'Edit Supplier';
        $('#supplierModalTitle').text(title || 'Add Supplier');
        $('#btnSaveSupplier').text(isEdit ? 'Update' : 'Save');
        $('#supplierModal').modal('show');
    }

    function loadSupplier(id) {
        $.getJSON(absUrl('crm/ajax/get_supplier.php'), { id: id })
            .done(function (res) {
                if (!res || !res.success || !res.data) {
                    showAlert((res && res.message) || 'Could not load supplier.', 'danger');
                    return;
                }
                var d = res.data;
                resetSupplierForm();
                $('#supplierId').val(d.id);
                $('#supplierName').val(d.name || '');
                $('#supplierWebsite').val(d.website || '');
                $('#supplierCityId').val(d.city_id || 0);
                $('#supplierCityName').val(d.city_name || '');
                $('#supplierCountryName').val(d.country_name || '');
                var cityLine = d.city_name || '';
                if (d.country_name) {
                    cityLine = cityLine ? (cityLine + ', ' + d.country_name) : d.country_name;
                }
                $('#supplierCitySearch').val(cityLine);
                $('#supplierAddress').val(d.physical_address || '');
                $('#supplierActive').val(Number(d.is_active) === 1 ? '1' : '0');
                contacts = (d.contacts && d.contacts.length) ? d.contacts : [defaultContact()];
                selectedPlaces = d.places || [];
                (d.supplier_of || []).forEach(function (key) {
                    $('.js-supplier-of[value="' + key + '"]').prop('checked', true);
                });
                renderContactRows();
                renderServiceSummary();
                renderPlaceTags();
                updateCityClearState();
                openModal('Edit Supplier');
            })
            .fail(function () {
                showAlert('Could not load supplier.', 'danger');
            });
    }

    function saveSupplier() {
        syncContactsFromDom();
        var name = $('#supplierName').val().trim();
        if (!name) {
            alert('Supplier name is required.');
            return;
        }

        var supplierOf = [];
        $('.js-supplier-of:checked').each(function () {
            supplierOf.push($(this).val());
        });
        // Supplier Of doubles as supplier type in the reference UI
        var selectedTypes = supplierOf.slice();
        if (!selectedTypes.length) {
            alert('Please select at least one item in Supplier Of.');
            $('#supplierServicesToggle').trigger('focus');
            return;
        }

        if (!contacts.length) {
            alert('At least one contact is required.');
            return;
        }
        var hasEmail = contacts.some(function (c) { return (c.email || '') !== ''; });
        if (!hasEmail) {
            alert('At least one contact email is required.');
            return;
        }

        ensurePrimaryContact();
        var primary = contacts.find(function (c) { return !!c.is_primary; }) || contacts[0];
        if (primary && !primary.contact_name) {
            primary.contact_name = name;
        }

        var cityName = $('#supplierCityName').val().trim();
        var typedCity = $('#supplierCitySearch').val().trim();
        var cityId = $('#supplierCityId').val();
        var countryName = $('#supplierCountryName').val().trim();
        if (!cityName && typedCity) {
            var cityParts = typedCity.split(',').map(function (part) { return part.trim(); }).filter(Boolean);
            cityName = cityParts.shift() || typedCity;
            countryName = countryName || cityParts.join(', ');
            cityId = 0;
        }

        var payload = {
            id: $('#supplierId').val(),
            name: name,
            company_name: '',
            supplier_types_json: JSON.stringify(selectedTypes),
            website: $('#supplierWebsite').val().trim(),
            city_id: cityId,
            city_name: cityName,
            country_name: countryName,
            physical_address: $('#supplierAddress').val().trim(),
            internal_notes: '',
            is_active: $('#supplierActive').val() === '0' ? 0 : 1,
            contacts_json: JSON.stringify(contacts),
            supplier_of_json: JSON.stringify(supplierOf),
            places_json: JSON.stringify(selectedPlaces)
        };
        var saveUrl = absUrl($('#supplierForm').attr('data-save-url') || 'crm/ajax/save_supplier.php');
        $('#btnSaveSupplier').prop('disabled', true);
        postJson(saveUrl, payload)
            .done(function (res) {
                if (!res || !res.success) {
                    alert((res && res.message) || 'Could not save supplier.');
                    return;
                }
                $('#supplierModal').modal('hide');
                showAlert(res.message || 'Supplier saved.');
                setTimeout(function () { window.location.reload(); }, 400);
            })
            .fail(function (xhr) {
                alert(ajaxErrorMessage(xhr, 'Could not save supplier.'));
            })
            .always(function () {
                $('#btnSaveSupplier').prop('disabled', false);
            });
    }

    function renderPlaceTags() {
        var $wrap = $('#placeTags');
        if (!$wrap.length) {
            return;
        }
        $wrap.empty();
        if (!selectedPlaces.length) {
            $wrap.html('<span class="place-tag empty-tag">No place selected</span>');
            return;
        }
        selectedPlaces.forEach(function (p, idx) {
            var label = p.label || p.name || '';
            $wrap.append(
                '<span class="place-tag" data-idx="' + idx + '">' +
                escapeHtml(label) +
                ' <button type="button" class="js-remove-place" title="Remove">&times;</button></span>'
            );
        });
    }

    function deleteSupplier(id) {
        if (!confirm('Delete this supplier?')) {
            return;
        }
        postJson(absUrl('crm/ajax/delete_supplier.php'), { id: id })
            .done(function (res) {
                if (!res || !res.success) {
                    alert((res && res.message) || 'Could not delete supplier.');
                    return;
                }
                showAlert(res.message || 'Supplier deleted.');
                $('tr[data-id="' + id + '"]').remove();
                if (table) {
                    table.draw();
                }
            })
            .fail(function (xhr) {
                alert(ajaxErrorMessage(xhr, 'Could not delete supplier.'));
            });
    }

    function formatCityLabel(row) {
        var label = row.name || '';
        if (row.state_name) {
            label += ', ' + row.state_name;
        }
        if (row.country_name) {
            label += ', ' + row.country_name;
        }
        return label;
    }

    function highlightMatch(text, query) {
        var safeText = escapeHtml(text || '');
        var term = String(query || '').trim();
        if (!term) {
            return safeText;
        }
        var re = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
        return safeText.replace(re, '<strong>$1</strong>');
    }

    function restoreSearchDropdown($dd) {
        if (!$dd || !$dd.length) {
            return;
        }
        var $origin = $dd.data('origin-parent');
        if ($origin && $origin.length) {
            $dd.appendTo($origin);
        }
        $dd.removeClass('is-floating').css({ top: '', left: '', width: '', right: '' });
    }

    function positionSearchDropdown($input, $dd) {
        if (!$input.length || !$dd.length || !$dd.is(':visible')) {
            return;
        }
        var el = $input[0];
        if (!el || typeof el.getBoundingClientRect !== 'function') {
            return;
        }

        if (!$dd.data('origin-parent')) {
            $dd.data('origin-parent', $dd.parent());
        }
        if (!$dd.parent().is('body')) {
            $dd.appendTo('body');
        }

        var rect = el.getBoundingClientRect();
        $dd.addClass('is-floating').css({
            top: Math.round(rect.bottom + 2) + 'px',
            left: Math.round(rect.left) + 'px',
            width: Math.max(Math.round(rect.width), 220) + 'px',
            right: 'auto'
        });
    }

    function hideSearchDropdown($dd) {
        if ($dd && $dd.length) {
            $dd.hide().empty();
            restoreSearchDropdown($dd);
        }
    }

    function searchCities(q) {
        if (cityRequest) {
            cityRequest.abort();
            cityRequest = null;
        }

        var $dd = $('#citySearchDropdown');
        var $input = $('#supplierCitySearch');

        cityRequest = $.getJSON(absUrl('crm/ajax/search_cities.php'), { q: q, limit: 20 });

        cityRequest.done(function (res) {
            $dd.empty();
            if (!res || !res.success || !res.data || !res.data.length) {
                $dd.append('<div class="item is-empty">No cities found</div>');
                $dd.show();
                positionSearchDropdown($input, $dd);
                return;
            }
            res.data.forEach(function (row) {
                var label = formatCityLabel(row);
                $dd.append(
                    '<div class="item" data-id="' + row.id + '" data-name="' + escapeHtml(row.name) + '" data-country="' + escapeHtml(row.country_name || '') + '">' +
                    highlightMatch(label, q) + '</div>'
                );
            });
            $dd.show();
            positionSearchDropdown($input, $dd);
        }).fail(function (xhr) {
            if (xhr && xhr.statusText === 'abort') {
                return;
            }
            $dd.empty().append('<div class="item is-empty">' + escapeHtml(ajaxErrorMessage(xhr, 'City search failed.')) + '</div>');
            $dd.show();
            positionSearchDropdown($input, $dd);
        }).always(function () {
            cityRequest = null;
        });
    }

    function searchPlaces(q) {
        $.ajax({
            url: absUrl('crm/ajax/search_places.php'),
            type: 'GET',
            dataType: 'json',
            data: { q: q, limit: 20 },
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function (res) {
            var $dd = $('#placeSearchDropdown');
            var $input = $('#placeSearch');
            $dd.empty();
            if (!res || !res.success) {
                $dd.append('<div class="item is-empty">' + escapeHtml((res && res.message) || 'Could not search places.') + '</div>');
                $dd.show();
                positionSearchDropdown($input, $dd);
                return;
            }
            if (!res.data || !res.data.length) {
                $dd.append('<div class="item is-empty">No places found</div>');
                $dd.show();
                positionSearchDropdown($input, $dd);
                return;
            }
            var added = 0;
            res.data.forEach(function (row) {
                var exists = selectedPlaces.some(function (p) {
                    return String(p.id) === String(row.id);
                });
                if (exists) {
                    return;
                }
                added += 1;
                $dd.append(
                    '<div class="item" data-id="' + row.id + '" data-name="' + escapeHtml(row.name) + '" data-country="' + escapeHtml(row.country || '') + '" data-label="' + escapeHtml(row.label || row.name) + '">' +
                    escapeHtml(row.label || row.name) + '</div>'
                );
            });
            if (!added) {
                $dd.append('<div class="item is-empty">No places found</div>');
            }
            $dd.show();
            positionSearchDropdown($input, $dd);
        }).fail(function (xhr) {
            var $dd = $('#placeSearchDropdown');
            var $input = $('#placeSearch');
            $dd.empty().append('<div class="item is-empty">' + escapeHtml(ajaxErrorMessage(xhr, 'Place search failed.')) + '</div>');
            $dd.show();
            positionSearchDropdown($input, $dd);
        });
    }

    function initDataTable() {
        if (!$.fn.DataTable || !$('#suppliersTable').length) {
            return;
        }
        if ($.fn.DataTable.isDataTable('#suppliersTable')) {
            table = $('#suppliersTable').DataTable();
            return;
        }

        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            if (settings.nTable.id !== 'suppliersTable') {
                return true;
            }
            var type = $('#filterType').val();
            var city = $('#filterCity').val();
            var status = $('#filterStatus').val();
            var api = new $.fn.dataTable.Api(settings);
            var row = api.row(dataIndex).node();
            if (!row) {
                return true;
            }
            if (type) {
                var rowTypes = String($(row).attr('data-supplier-type') || '').split(',').map(function (v) {
                    return $.trim(v);
                }).filter(Boolean);
                if (rowTypes.indexOf(type) < 0) {
                    return false;
                }
            }
            if (city && ($(row).attr('data-city') || '') !== city) {
                return false;
            }
            if (status && ($(row).attr('data-status') || '') !== status) {
                return false;
            }
            return true;
        });

        table = $('#suppliersTable').DataTable({
            responsive: false,
            dom: '<"supplier-table-scroll"t><"supplier-table-footer"i<"supplier-table-footer-tools"lp>>',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
            order: [],
            columnDefs: [
                { orderable: false, targets: [2, 8] }
            ],
            language: {
                emptyTable: 'No suppliers yet. Click Add Supplier to create one.',
                info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                lengthMenu: '_MENU_ / page',
                paginate: {
                    previous: '<i class="fas fa-chevron-left"></i>',
                    next: '<i class="fas fa-chevron-right"></i>'
                }
            }
        });
    }

    function initServicePopovers() {
        if (!$.fn.popover) {
            return;
        }
        $('.sup-services-more').each(function () {
            var $button = $(this);
            if ($button.data('bs.popover')) {
                return;
            }
            $button.popover({
                container: 'body',
                placement: 'auto',
                trigger: 'hover focus',
                html: true,
                sanitize: false,
                template: '<div class="popover supplier-services-popover" role="tooltip"><div class="arrow"></div><div class="popover-body"></div></div>'
            });
        });
    }

    $(function () {
        renderContactRows();
        initServicePopovers();

        $('#btnAddSupplier').on('click', function () {
            resetSupplierForm();
            openModal('Add Supplier');
        });

        $('#btnSaveSupplier').on('click', saveSupplier);

        $(document).on('click', '.js-view-supplier', function () {
            var $row = $(this).closest('tr');
            var id = $row.data('id');
            var name = $.trim($row.find('.sup-name').first().text()) || 'Supplier Portfolio';
            if (id) {
                openSupplierPortfolio(id, name);
            }
        });

        $('#spPortfolioEditBtn').on('click', function () {
            var id = parseInt($('#spPortfolioId').val(), 10) || 0;
            if (!id) return;
            $('#supplierPortfolioModal').modal('hide');
            loadSupplier(id);
        });

        $('#spTourSearch, #spTourDest').on('input', scheduleAssocReload);
        $('#spTourFrom, #spTourTo, #spTourStatus, #spTourPay').on('change', scheduleAssocReload);
        $(document).on('click', '.js-sp-assoc-page:not(:disabled)', function () {
            assocState.page = parseInt($(this).attr('data-page'), 10) || 1;
            loadAssociatedTours();
        });
        $(document).on('click', '.js-sp-view-tour', function () {
            var id = parseInt($(this).attr('data-id'), 10) || 0;
            if (!id) return;
            if (window.CrmConfirmTour && typeof window.CrmConfirmTour.open === 'function') {
                window.CrmConfirmTour.open(id, null);
            } else {
                window.alert('Confirm Tour is not available on this page.');
            }
        });
        $(document).on('crm:tour-updated', function () {
            if ($('#supplierPortfolioModal').hasClass('show') && assocState.supplierId) {
                loadAssociatedTours();
            }
        });

        $(document).on('click', '.js-delete-supplier', function () {
            var id = $(this).closest('tr').data('id');
            if (id) {
                deleteSupplier(id);
            }
        });

        $(document).on('click', '.js-add-contact-row', function () {
            syncContactsFromDom();
            contacts.push(defaultContact());
            renderContactRows();
        });

        $(document).on('click', '.js-remove-contact-row', function () {
            syncContactsFromDom();
            var idx = $(this).closest('.contact-row').index();
            if (idx >= 0 && contacts.length > 1) {
                contacts.splice(idx, 1);
            } else if (contacts.length === 1) {
                contacts = [defaultContact()];
            }
            renderContactRows();
        });

        $('#supplierCitySearch').on('focus', function () {
            var q = $(this).val().trim();
            if (q.length >= 2) {
                searchCities(q);
            }
        });

        $('#supplierCitySearch').on('input', function () {
            var q = $(this).val().trim();
            $('#supplierCityId').val('0');
            $('#supplierCityName').val('');
            $('#supplierCountryName').val('');
            updateCityClearState();
            clearTimeout(cityTimer);
            if (q.length < 2) {
                hideSearchDropdown($('#citySearchDropdown'));
                return;
            }
            cityTimer = setTimeout(function () { searchCities(q); }, 250);
        });

        $('#supplierModal .supplier-modal-body').on('scroll', function () {
            positionSearchDropdown($('#supplierCitySearch'), $('#citySearchDropdown'));
            positionSearchDropdown($('#placeSearch'), $('#placeSearchDropdown'));
        });

        $(window).on('resize scroll', function () {
            if ($('#supplierModal').hasClass('show')) {
                positionSearchDropdown($('#supplierCitySearch'), $('#citySearchDropdown'));
                positionSearchDropdown($('#placeSearch'), $('#placeSearchDropdown'));
            }
        });

        $('#supplierCityClear').on('click', function () {
            $('#supplierCitySearch').val('');
            $('#supplierCityId').val('0');
            $('#supplierCityName').val('');
            $('#supplierCountryName').val('');
            hideSearchDropdown($('#citySearchDropdown'));
            updateCityClearState();
        });

        $(document).on('mousedown', '#citySearchDropdown .item:not(.is-empty)', function (e) {
            e.preventDefault();
            var $item = $(this);
            $('#supplierCityId').val($item.data('id'));
            $('#supplierCityName').val($item.data('name'));
            $('#supplierCountryName').val($item.data('country'));
            $('#supplierCitySearch').val($item.text());
            hideSearchDropdown($('#citySearchDropdown'));
            updateCityClearState();
        });

        $('#placeSearch').on('input', function () {
            var q = $(this).val().trim();
            clearTimeout(placeTimer);
            if (q.length < 1) {
                $('#placeSearchDropdown').hide().empty();
                return;
            }
            placeTimer = setTimeout(function () { searchPlaces(q); }, 250);
        });

        $(document).on('mousedown', '#placeSearchDropdown .item:not(.is-empty)', function (e) {
            e.preventDefault();
            selectedPlaces.push({
                id: parseInt($(this).data('id'), 10) || 0,
                name: String($(this).data('name') || ''),
                country: String($(this).data('country') || ''),
                label: String($(this).data('label') || $(this).data('name') || '')
            });
            $('#placeSearch').val('');
            hideSearchDropdown($('#placeSearchDropdown'));
            renderPlaceTags();
        });

        $(document).on('click', '.js-remove-place', function () {
            var idx = parseInt($(this).closest('.place-tag').attr('data-idx'), 10);
            if (!isNaN(idx)) {
                selectedPlaces.splice(idx, 1);
            }
            renderPlaceTags();
        });

        $('#filterType, #filterCity, #filterStatus').on('change', function () {
            if (table) {
                table.draw();
            }
        });

        $('#supplierServicesToggle').on('click', function () {
            var $panel = $('#supplierOfPanel');
            var open = !$panel.hasClass('is-open');
            $panel.toggleClass('is-open', open);
            $(this).attr('aria-expanded', open ? 'true' : 'false');
        });

        $(document).on('change', '.js-supplier-of', renderServiceSummary);

        $('#supplierTableSearch').on('input', function () {
            if (table) {
                table.search($(this).val()).draw();
            }
        });

        $('#btnResetFilters').on('click', function () {
            $('#filterType, #filterCity, #filterStatus').val('');
            $('#supplierTableSearch').val('');
            if (table) {
                table.search('').draw();
            }
        });

        $('#btnExportSuppliers').on('click', function () {
            if (!table) {
                return;
            }
            var rows = table.rows({ search: 'applied' }).nodes().toArray();
            var csv = [['Supplier Name', 'City', 'Services', 'Contact Person', 'Mobile', 'Email', 'Status', 'Last Updated']];
            rows.forEach(function (row) {
                var cells = $(row).find('td');
                csv.push([
                    $(cells[0]).text().trim(),
                    $(cells[1]).text().trim(),
                    $(cells[2]).text().trim(),
                    $(cells[3]).text().trim(),
                    $(cells[4]).text().trim(),
                    $(cells[5]).text().trim(),
                    $(cells[6]).text().trim(),
                    $(cells[7]).text().trim()
                ]);
            });
            var body = csv.map(function (line) {
                return line.map(function (value) {
                    return '"' + String(value || '').replace(/"/g, '""') + '"';
                }).join(',');
            }).join('\r\n');
            var blob = new Blob([body], { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = 'suppliers.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.search-wrap, #citySearchDropdown, #placeSearchDropdown').length) {
                hideSearchDropdown($('#citySearchDropdown'));
                hideSearchDropdown($('#placeSearchDropdown'));
            }
            if (!$(e.target).closest('.supplier-of-field').length) {
                $('#supplierOfPanel').removeClass('is-open');
                $('#supplierServicesToggle').attr('aria-expanded', 'false');
            }
        });

        $('#supplierModal').on('hidden.bs.modal', function () {
            if (cityRequest) {
                cityRequest.abort();
                cityRequest = null;
            }
            hideSearchDropdown($('#citySearchDropdown'));
            hideSearchDropdown($('#placeSearchDropdown'));
            $('#supplierOfPanel').removeClass('is-open');
            $('#supplierServicesToggle').attr('aria-expanded', 'false');
        });

        try {
            initDataTable();
            if (table) {
                table.on('draw', initServicePopovers);
            }
        } catch (err) {
            console.error('Suppliers table init failed:', err);
        }
    });
}(jQuery));
