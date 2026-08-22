/* Confirm Tour modal — quotation list + leads book */
(function ($) {
    'use strict';

    var serviceMap = {};
    var activeQuotationId = 0;
    var activeRow = null;
    var supplierSuggestTimer = null;
    var supplierSuggestXhr = null;
    var $supplierMenu = null;
    var $activeSupplierInput = null;

    function esc(str) {
        return $('<div>').text(str == null ? '' : str).html();
    }

    function money(n) {
        n = parseFloat(n);
        if (isNaN(n)) {
            return '0';
        }
        return n.toLocaleString('en-IN', { maximumFractionDigits: 2 });
    }

    function parseNum(v) {
        var n = parseFloat(('' + v).replace(/,/g, ''));
        return isNaN(n) ? 0 : n;
    }

    function ensureSupplierSuggestStyles() {
        if ($('#ctSupplierSuggestStyles').length) {
            return;
        }
        $('head').append(
            '<style id="ctSupplierSuggestStyles">' +
            '#confirmTourModal .ct-supplier-wrap{position:relative;}' +
            '#ctSupplierSuggestMenu{' +
            'position:absolute;z-index:1080;display:none;max-height:220px;overflow:auto;' +
            'min-width:180px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;' +
            'box-shadow:0 10px 28px rgba(15,23,42,.14);padding:.25rem 0;}' +
            '#ctSupplierSuggestMenu .ct-supplier-item{' +
            'display:block;width:100%;text-align:left;border:0;background:transparent;' +
            'padding:.45rem .7rem;font-size:.82rem;color:#1e293b;cursor:pointer;}' +
            '#ctSupplierSuggestMenu .ct-supplier-item:hover,' +
            '#ctSupplierSuggestMenu .ct-supplier-item.is-active{background:#eff6ff;color:#1d4ed8;}' +
            '#ctSupplierSuggestMenu .ct-supplier-item-sub{display:block;font-size:.72rem;color:#94a3b8;margin-top:.1rem;}' +
            '#ctSupplierSuggestMenu .ct-supplier-empty{padding:.55rem .7rem;font-size:.8rem;color:#94a3b8;}' +
            '</style>'
        );
    }

    function ensureSupplierMenu() {
        ensureSupplierSuggestStyles();
        if (!$supplierMenu || !$supplierMenu.length) {
            $supplierMenu = $('<div id="ctSupplierSuggestMenu" role="listbox" aria-label="Supplier suggestions"></div>');
            $('body').append($supplierMenu);
        }
        return $supplierMenu;
    }

    function hideSupplierSuggest() {
        if (supplierSuggestTimer) {
            window.clearTimeout(supplierSuggestTimer);
            supplierSuggestTimer = null;
        }
        if (supplierSuggestXhr && typeof supplierSuggestXhr.abort === 'function') {
            try { supplierSuggestXhr.abort(); } catch (err) {}
            supplierSuggestXhr = null;
        }
        if ($supplierMenu && $supplierMenu.length) {
            $supplierMenu.hide().empty();
        }
        $activeSupplierInput = null;
    }

    function positionSupplierMenu($input) {
        var $menu = ensureSupplierMenu();
        var rect = $input[0].getBoundingClientRect();
        var top = rect.bottom + window.scrollY + 2;
        var left = rect.left + window.scrollX;
        var width = Math.max(rect.width, 200);
        $menu.css({
            top: top + 'px',
            left: left + 'px',
            width: width + 'px'
        });
    }

    function renderSupplierSuggest(items, query) {
        var $menu = ensureSupplierMenu();
        if (!items.length) {
            $menu.html('<div class="ct-supplier-empty">No matching suppliers</div>').show();
            return;
        }
        var html = items.map(function (item, idx) {
            var name = String(item.name || '');
            var company = String(item.company_name || '');
            var sub = company && company.toLowerCase() !== name.toLowerCase()
                ? '<span class="ct-supplier-item-sub">' + esc(company) + '</span>'
                : '';
            return '' +
                '<button type="button" class="ct-supplier-item' + (idx === 0 ? ' is-active' : '') + '"' +
                ' data-name="' + esc(name) + '" role="option">' +
                esc(name) + sub +
                '</button>';
        }).join('');
        $menu.html(html).show();
    }

    function fetchSupplierSuggest($input) {
        var query = String($input.val() || '').trim();
        var serviceKey = String($input.closest('.ct-detail-row').attr('data-key') || '');
        $activeSupplierInput = $input;
        positionSupplierMenu($input);

        if (supplierSuggestXhr && typeof supplierSuggestXhr.abort === 'function') {
            try { supplierSuggestXhr.abort(); } catch (err) {}
        }

        supplierSuggestXhr = $.ajax({
            url: 'crm/ajax/search_suppliers.php',
            method: 'GET',
            dataType: 'json',
            data: {
                q: query,
                service: serviceKey,
                limit: 20
            }
        }).done(function (res) {
            if (!$activeSupplierInput || !$activeSupplierInput.is($input)) {
                return;
            }
            var list = (res && res.success && Array.isArray(res.suppliers)) ? res.suppliers : [];
            renderSupplierSuggest(list, query);
            positionSupplierMenu($input);
        }).fail(function (xhr) {
            if (xhr && xhr.statusText === 'abort') {
                return;
            }
            hideSupplierSuggest();
        });
    }

    function scheduleSupplierSuggest($input) {
        if (supplierSuggestTimer) {
            window.clearTimeout(supplierSuggestTimer);
        }
        supplierSuggestTimer = window.setTimeout(function () {
            supplierSuggestTimer = null;
            fetchSupplierSuggest($input);
        }, 160);
    }

    function selectSupplierSuggestion(name) {
        if ($activeSupplierInput && $activeSupplierInput.length) {
            $activeSupplierInput.val(name).trigger('change');
        }
        hideSupplierSuggest();
    }

    function rowHtml(row) {
        row = row || {};
        var key = row.key || '';
        var label = row.label || serviceMap[key] || key;
        var total = row.total != null ? row.total : '';
        var paid = row.paid != null ? row.paid : '';
        var balance = money(Math.max(0, parseNum(total) - parseNum(paid)));

        return '' +
            '<div class="ct-detail-row" data-key="' + esc(key) + '">' +
            '<div class="ct-detail-label">' + esc(label) + '</div>' +
            '<div class="ct-detail-field ct-supplier-wrap">' +
            '<input type="text" class="form-control ct-supplier" placeholder="Type supplier name" ' +
            'autocomplete="off" spellcheck="false" value="' + esc(row.supplier || '') + '">' +
            '</div>' +
            '<div class="ct-detail-field"><input type="number" step="0.01" class="form-control ct-total" placeholder="Total" value="' + esc(total) + '"></div>' +
            '<div class="ct-detail-field"><input type="number" step="0.01" class="form-control ct-paid" placeholder="Paid" value="' + esc(paid) + '"></div>' +
            '<div class="ct-detail-field ct-balance-wrap">' +
            '<span class="ct-balance-label">Balance</span>' +
            '<span class="ct-balance-val">' + balance + '</span>' +
            '</div>' +
            '<div class="ct-detail-actions">' +
            '<button type="button" class="btn btn-sm btn-primary ct-reminders">Reminders</button>' +
            '<button type="button" class="btn btn-sm btn-light ct-remove-row" title="Remove"><i class="fas fa-trash-alt"></i></button>' +
            '</div>' +
            '</div>';
    }

    function syncChipStates() {
        $('#ctIncludedChips .ct-chip').each(function () {
            var key = $(this).data('key');
            var active = $('#ctDetailRows .ct-detail-row[data-key="' + key + '"]').length > 0;
            $(this).toggleClass('active', active);
        });
    }

    function recalcRowBalance($row) {
        var total = parseNum($row.find('.ct-total').val());
        var paid = parseNum($row.find('.ct-paid').val());
        $row.find('.ct-balance-val').text(money(Math.max(0, total - paid)));
    }

    function collectServices() {
        var list = [];
        $('#ctDetailRows .ct-detail-row').each(function () {
            var $row = $(this);
            list.push({
                key: $row.data('key'),
                label: serviceMap[$row.data('key')] || $row.data('key'),
                supplier: $row.find('.ct-supplier').val(),
                total: parseNum($row.find('.ct-total').val()),
                paid: parseNum($row.find('.ct-paid').val())
            });
        });
        return list;
    }

    function renderRows(services) {
        hideSupplierSuggest();
        $('#ctDetailRows').empty();
        (services || []).forEach(function (svc) {
            $('#ctDetailRows').append(rowHtml(svc));
        });
        syncChipStates();
    }

    function openModal(quotationId, $triggerRow) {
        activeQuotationId = quotationId;
        activeRow = $triggerRow;
        $('#ctQuotationId').val(quotationId);
        $('#ctGuestName').val('');
        $('#ctMobileNo').val('');
        renderRows([]);
        $('#confirmTourModal').modal('show');

        $.getJSON('crm/ajax/get_quotation_confirm.php', { id: quotationId })
            .done(function (res) {
                if (!res || !res.success) {
                    alert((res && res.message) || 'Could not load quotation.');
                    return;
                }
                serviceMap = res.services || {};
                var q = res.quotation || {};
                var confirm = res.confirm || {};
                $('#ctGuestName').val(confirm.guest_name || q.guest_name || '');
                $('#ctMobileNo').val(confirm.mobile_no || q.mobile_no || '');
                renderRows(confirm.services || []);
            })
            .fail(function () {
                alert('Could not load quotation details.');
            });
    }

    function updateListRow(res) {
        if (!activeRow || !activeRow.length) {
            return;
        }
        if (res.status_html) {
            activeRow.find('.js-q-status').html(res.status_html);
        }
        var $bookBtn = activeRow.find('.js-q-book');
        if (parseInt(res.tour_confirmed, 10) === 1) {
            if ($bookBtn.hasClass('btn-icon')) {
                $bookBtn.removeClass('btn-book').addClass('btn-confirmed')
                    .attr('title', 'Tour Confirmed')
                    .attr('aria-label', 'Tour Confirmed')
                    .html('<i class="fas fa-check"></i>');
            } else {
                $bookBtn.removeClass('btn-book').addClass('btn-confirmed').text('Confirmed');
            }
        } else {
            if ($bookBtn.hasClass('btn-icon')) {
                $bookBtn.removeClass('btn-confirmed').addClass('btn-book')
                    .attr('title', 'Book')
                    .attr('aria-label', 'Book quotation')
                    .html('<i class="fas fa-book"></i>');
            } else {
                $bookBtn.removeClass('btn-confirmed').addClass('btn-book').text('Book');
            }
        }
        var guest = $('#ctGuestName').val();
        var mobile = $('#ctMobileNo').val();
        if (guest) {
            activeRow.find('.js-q-guest-name').text(guest);
        }
        if (mobile) {
            activeRow.find('.js-q-guest-mobile').text(mobile);
        }
    }

    $(function () {
        ensureSupplierSuggestStyles();

        var chipsHtml = '';
        Object.keys({
            visa: 'Visa',
            hotels: 'Hotels',
            land_package: 'Land Package',
            forex: 'Forex',
            train: 'Train',
            flight: 'Flight',
            travel_insurance: 'Travel Insurance',
            transfers: 'Transfers',
            tours: 'Tours',
            cruise: 'Cruise'
        }).forEach(function (key) {
            var label = {
                visa: 'Visa',
                hotels: 'Hotels',
                land_package: 'Land Package',
                forex: 'Forex',
                train: 'Train',
                flight: 'Flight',
                travel_insurance: 'Travel Insurance',
                transfers: 'Transfers',
                tours: 'Tours',
                cruise: 'Cruise'
            }[key];
            chipsHtml += '<button type="button" class="ct-chip" data-key="' + key + '">' + esc(label) + ' +</button>';
        });
        $('#ctIncludedChips').html(chipsHtml);

        $(document).on('click', '.js-q-book', function (e) {
            e.preventDefault();
            var id = $(this).data('id');
            openModal(id, $(this).closest('tr'));
        });

        $(document).on('click', '#ctIncludedChips .ct-chip', function () {
            var key = $(this).data('key');
            if ($('#ctDetailRows .ct-detail-row[data-key="' + key + '"]').length) {
                return;
            }
            $('#ctDetailRows').append(rowHtml({
                key: key,
                label: serviceMap[key] || $(this).text().replace(/\s*\+\s*$/, '')
            }));
            syncChipStates();
        });

        $(document).on('input', '.ct-total, .ct-paid', function () {
            recalcRowBalance($(this).closest('.ct-detail-row'));
        });

        $(document).on('focus input', '#confirmTourModal .ct-supplier', function () {
            scheduleSupplierSuggest($(this));
        });

        $(document).on('keydown', '#confirmTourModal .ct-supplier', function (e) {
            var $menu = ensureSupplierMenu();
            if (!$menu.is(':visible')) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    scheduleSupplierSuggest($(this));
                }
                return;
            }
            var $items = $menu.find('.ct-supplier-item');
            if (!$items.length) {
                return;
            }
            var $active = $items.filter('.is-active');
            var idx = $items.index($active);
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                idx = idx < 0 ? 0 : Math.min($items.length - 1, idx + 1);
                $items.removeClass('is-active').eq(idx).addClass('is-active');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                idx = idx <= 0 ? 0 : idx - 1;
                $items.removeClass('is-active').eq(idx).addClass('is-active');
            } else if (e.key === 'Enter') {
                if ($active.length) {
                    e.preventDefault();
                    selectSupplierSuggestion(String($active.attr('data-name') || ''));
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                hideSupplierSuggest();
            }
        });

        $(document).on('mousedown', '#ctSupplierSuggestMenu .ct-supplier-item', function (e) {
            e.preventDefault();
            selectSupplierSuggestion(String($(this).attr('data-name') || ''));
        });

        $(document).on('blur', '#confirmTourModal .ct-supplier', function () {
            window.setTimeout(function () {
                if (!$('#ctSupplierSuggestMenu:hover').length) {
                    hideSupplierSuggest();
                }
            }, 120);
        });

        $(window).on('resize scroll', function () {
            if ($activeSupplierInput && $activeSupplierInput.length && $supplierMenu && $supplierMenu.is(':visible')) {
                positionSupplierMenu($activeSupplierInput);
            }
        });

        $('#confirmTourModal').on('hidden.bs.modal', function () {
            hideSupplierSuggest();
        });

        $(document).on('click', '.ct-remove-row', function () {
            $(this).closest('.ct-detail-row').remove();
            syncChipStates();
            hideSupplierSuggest();
        });

        $(document).on('click', '.ct-reminders', function () {
            alert('Reminders will be available in a future update.');
        });

        $('#ctSaveBtn').on('click', function () {
            var guest = $.trim($('#ctGuestName').val());
            if (!guest) {
                alert('Guest name is required.');
                return;
            }
            var $btn = $(this).prop('disabled', true);
            $.ajax({
                url: 'crm/ajax/save_quotation_confirm.php',
                type: 'POST',
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: {
                    id: activeQuotationId,
                    guest_name: guest,
                    mobile_no: $('#ctMobileNo').val(),
                    services_json: JSON.stringify(collectServices())
                }
            })
                .done(function (res) {
                    if (res && res.success) {
                        updateListRow(res);
                        $('#confirmTourModal').modal('hide');
                    } else {
                        alert((res && res.message) || 'Could not save.');
                    }
                })
                .fail(function () {
                    alert('Could not save. Please try again.');
                })
                .always(function () {
                    $btn.prop('disabled', false);
                });
        });
    });
})(jQuery);
