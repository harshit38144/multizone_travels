/* Confirm Tour modal — quotation list */
(function ($) {
    'use strict';

    var serviceMap = {};
    var activeQuotationId = 0;
    var activeRow = null;

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
            '<div class="ct-detail-field"><input type="text" class="form-control ct-supplier" placeholder="Supplier" value="' + esc(row.supplier || '') + '"></div>' +
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
            $bookBtn.removeClass('btn-book').addClass('btn-confirmed').text('Confirmed');
        } else {
            $bookBtn.removeClass('btn-confirmed').addClass('btn-book').text('Book');
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

        $(document).on('click', '.ct-remove-row', function () {
            $(this).closest('.ct-detail-row').remove();
            syncChipStates();
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
