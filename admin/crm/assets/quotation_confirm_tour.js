/* Confirm Tour modal — quotation list + leads book */
(function ($) {
    'use strict';

    var serviceMap = {};
    var activeQuotationId = 0;
    var activePackageTotal = null;
    var activeCustomerPaid = null;
    var activeTripInfo = null;

    function tripDateLabel(ymd) {
        var m = String(ymd || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!m) {
            return '';
        }
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return m[3] + ' ' + (months[parseInt(m[2], 10) - 1] || m[2]) + ' ' + m[1];
    }

    function customerDue() {
        return Math.max(0, Math.round(((activePackageTotal || 0) - (activeCustomerPaid || 0)) * 100) / 100);
    }
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
            '#confirmTourModal .ct-detail-row.ct-row-highlight{' +
            'background:linear-gradient(90deg,#fff7ed 0%,#ffedd5 55%,#fff 100%)!important;' +
            'box-shadow:inset 3px 0 0 #ea580c,0 0 0 1px rgba(234,88,12,.28);' +
            'animation:ctRowPulse 1.1s ease-in-out 2;}' +
            '#confirmTourModal .ct-chip.ct-chip-highlight{' +
            'background:#ffedd5!important;border-color:#fb923c!important;color:#c2410c!important;' +
            'box-shadow:0 0 0 2px rgba(251,146,60,.28);}' +
            '@keyframes ctRowPulse{' +
            '0%,100%{transform:translateX(0);}' +
            '50%{transform:translateX(2px);}}' +
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

    function uidService() {
        return 's_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 7);
    }

    function money2(n) {
        n = parseFloat(n);
        return (isNaN(n) ? 0 : n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function amountInputValue(v) {
        if (v === '' || v === null || typeof v === 'undefined') {
            return '';
        }
        return money2(parseNum(v));
    }

    function ensureVoucherBtnStyles() {
        if (document.getElementById('ctVoucherBtnStyles')) {
            return;
        }
        var t = '#confirmTourModal .ct-svc-table';
        var m = '#confirmTourModal';
        $('<style id="ctVoucherBtnStyles">').text(
            m + ' .ct-svc-section-head{display:flex;align-items:center;gap:.8rem;margin:1.1rem 0 .7rem}' +
            m + ' .ct-svc-section-icon{font-size:1.55rem;color:#e11d48;width:32px;text-align:center}' +
            m + ' .ct-svc-section-title{font-size:1.08rem;font-weight:700;color:#0f172a;line-height:1.2}' +
            m + ' .ct-svc-section-sub{font-size:.8rem;color:#64748b;margin-top:.1rem}' +
            t + '{border:1px solid #e2e8f0;border-radius:12px;overflow-x:auto;background:#fff;margin-top:.25rem}' +
            t + ' .ct-detail-head,' + t + ' .ct-detail-row{display:grid;' +
            'grid-template-columns:34px 140px minmax(140px,1fr) 110px 100px 96px 108px 108px 186px;' +
            'gap:.5rem;align-items:center;min-width:1090px;padding:0 1rem;margin:0;border:0;border-radius:0}' +
            t + ' .ct-detail-head{background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.78rem;font-weight:600;color:#334155;padding-top:.75rem;padding-bottom:.75rem}' +
            t + ' .ct-detail-head>div:nth-child(1){text-align:center}' +
            t + ' #ctDetailRows{counter-reset:ctSvcRow}' +
            t + ' .ct-detail-row{counter-increment:ctSvcRow;padding-top:.45rem;padding-bottom:.45rem;border-top:1px solid #eef2f7;background:#fff}' +
            t + ' .ct-detail-row:first-child{border-top:0}' +
            t + ' .ct-detail-row:hover{background:#fafcff}' +
            t + ' .ct-row-no{text-align:center;font-size:.84rem;color:#334155}' +
            t + ' .ct-row-no::before{content:counter(ctSvcRow)}' +
            t + ' #ctDetailRows:empty{display:block;min-width:1080px;padding:1.1rem;text-align:center;color:#94a3b8;font-size:.82rem}' +
            t + ' #ctDetailRows:empty::after{content:"No services yet. Click a service in What is Included to add it."}' +
            t + ' .ct-detail-label{display:flex;align-items:center;gap:.6rem;font-size:.86rem;font-weight:600;color:#0f172a;padding:0;min-width:0}' +
            t + ' .ct-detail-label>span:last-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}' +
            t + ' .ct-svc-icon{flex:0 0 32px;width:32px;height:32px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:.9rem;background:#f1f5f9;color:#475569}' +
            t + ' .ct-svc-icon.is-hotels{background:#eff6ff;color:#2563eb}' +
            t + ' .ct-svc-icon.is-flight{background:#eef2ff;color:#4f46e5}' +
            t + ' .ct-svc-icon.is-land_package{background:#fff7ed;color:#ea580c}' +
            t + ' .ct-svc-icon.is-visa{background:#f5f3ff;color:#7c3aed}' +
            t + ' .ct-svc-icon.is-transfers{background:#f0fdfa;color:#0d9488}' +
            t + ' .ct-svc-icon.is-travel_insurance{background:#f0fdf4;color:#16a34a}' +
            t + ' .ct-svc-icon.is-forex{background:#fffbeb;color:#d97706}' +
            t + ' .ct-svc-icon.is-train{background:#f0f9ff;color:#0284c7}' +
            t + ' .ct-svc-icon.is-tours{background:#fdf2f8;color:#db2777}' +
            t + ' .ct-svc-icon.is-cruise{background:#ecfeff;color:#0891b2}' +
            t + ' .ct-detail-field .form-control{height:32px;font-size:.84rem;color:#0f172a;padding:.25rem .45rem;border:1px solid transparent;border-radius:6px;background:transparent;box-shadow:none}' +
            t + ' .ct-detail-field .form-control:hover{border-color:#e2e8f0}' +
            t + ' .ct-detail-field .form-control:focus{border-color:#93c5fd;background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.12)}' +
            t + ' .ct-detail-field .form-control::placeholder{color:#94a3b8}' +
            t + ' .ct-num .form-control{text-align:right;font-variant-numeric:tabular-nums}' +
            t + ' .ct-detail-row.has-paid .ct-paid{color:#16a34a;font-weight:500}' +
            t + ' .ct-balance-wrap{text-align:right;padding:0}' +
            t + ' .ct-balance-val{display:inline-block;min-width:92px;padding:.34rem .75rem;border-radius:8px;border:0;font-size:.84rem;font-weight:600;text-align:center;font-variant-numeric:tabular-nums;background:#fee2e2;color:#dc2626}' +
            t + ' .ct-detail-actions{display:flex;justify-content:flex-start;gap:.35rem;padding:0}' +
            t + ' .ct-act-btn{position:relative;width:30px;height:30px;padding:0;border:0;border-radius:7px;background:#f1f5f9;color:#475569;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;transition:filter .12s,transform .12s}' +
            t + ' .ct-act-btn:hover{filter:brightness(.95);transform:translateY(-1px)}' +
            t + ' .ct-act-btn.ct-pay-row{background:#dcfce7;color:#16a34a}' +
            t + ' .ct-act-btn.ct-reminders{background:#f1f5f9;color:#475569}' +
            t + ' .ct-act-btn.ct-view-row{background:#eff6ff;color:#2563eb}' +
            t + ' .ct-status-wrap{padding:0}' +
            '.ct-status-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.28rem .6rem;border-radius:999px;font-size:.74rem;font-weight:600;white-space:nowrap;line-height:1.2}' +
            '.ct-status-pill::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor}' +
            '.ct-status-pill.is-paid{background:#dcfce7;color:#15803d}' +
            '.ct-status-pill.is-partial{background:#fef3c7;color:#b45309}' +
            '.ct-status-pill.is-unpaid{background:#fee2e2;color:#dc2626}' +
            t + ' .ct-act-btn.ct-remove-row{background:#fef2f2;color:#dc2626}' +
            t + ' .ct-act-btn.ct-svc-voucher-act{background:#f5f3ff;color:#7c3aed}' +
            t + ' .ct-act-btn.ct-svc-voucher-act.is-attached{background:#ecfdf5;color:#15803d}' +
            t + ' .ct-svc-voucher-count{position:absolute;top:-5px;right:-5px;min-width:16px;height:16px;padding:0 4px;border-radius:999px;background:#16a34a;color:#fff;font-size:.6rem;font-weight:700;line-height:16px;text-align:center;box-shadow:0 0 0 2px #fff}' +
            t + ' .ct-svc-voucher{display:inline-flex;align-items:center;gap:.45rem;height:30px;padding:0 .35rem;border:0;border-radius:6px;background:transparent;color:#1d4ed8;font-size:.82rem;line-height:1}' +
            t + ' .ct-svc-voucher:hover{background:#eff6ff}' +
            t + ' .ct-svc-voucher .fa-paperclip{color:#475569;font-size:.9rem}' +
            t + ' .ct-svc-voucher .fa-chevron-down{font-size:.62rem;color:#1d4ed8;margin-left:.15rem}' +
            m + ' .ct-svc-footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.9rem;padding:.9rem 1.1rem;border-top:1px solid #e2e8f0;background:#fff}' +
            m + ' .ct-svc-footer>*{margin:0}' +
            m + ' .ct-svc-summary{display:flex;flex-wrap:wrap;gap:.75rem;flex:1 1 auto}' +
            m + ' .ct-sum-card{display:flex;align-items:center;gap:.7rem;min-width:170px;flex:1 1 0;padding:.65rem .85rem;border-radius:10px}' +
            m + ' .ct-sum-icon{flex:0 0 36px;width:36px;height:36px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;background:rgba(255,255,255,.75)}' +
            m + ' .ct-sum-label{display:block;font-size:.72rem;line-height:1.2}' +
            m + ' .ct-sum-val{display:block;font-size:1.12rem;font-weight:700;line-height:1.3;font-variant-numeric:tabular-nums;white-space:nowrap}' +
            m + ' .ct-sum-card.is-total{background:#eff6ff}' + m + ' .ct-sum-card.is-total .ct-sum-icon,' + m + ' .ct-sum-card.is-total .ct-sum-label{color:#2563eb}' +
            m + ' .ct-sum-card.is-total .ct-sum-val{color:#0f172a}' +
            m + ' .ct-sum-card.is-paid{background:#f0fdf4}' + m + ' .ct-sum-card.is-paid .ct-sum-icon,' + m + ' .ct-sum-card.is-paid .ct-sum-label{color:#16a34a}' +
            m + ' .ct-sum-card.is-paid .ct-sum-val{color:#0f172a}' +
            m + ' .ct-sum-card.is-due{background:#fef2f2}' + m + ' .ct-sum-card.is-due .ct-sum-icon,' + m + ' .ct-sum-card.is-due .ct-sum-label{color:#dc2626}' +
            m + ' .ct-sum-card.is-due .ct-sum-val{color:#b91c1c}' +
            m + ' .ct-sum-card.is-payments{background:#f5f3ff}' + m + ' .ct-sum-card.is-payments .ct-sum-icon,' + m + ' .ct-sum-card.is-payments .ct-sum-label{color:#7c3aed}' +
            m + ' .ct-sum-card.is-payments .ct-sum-val{color:#0f172a}' +
            m + ' .ct-svc-footer-actions{display:flex;align-items:center;gap:.6rem;padding-left:.9rem;border-left:1px solid #e2e8f0}' +
            m + ' .ct-btn-cancel{height:44px;min-width:92px;padding:0 1.2rem;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#1e293b;font-weight:500}' +
            m + ' .ct-btn-cancel:hover{background:#f8fafc}' +
            m + ' .ct-btn-save{height:44px;padding:0 1.3rem;border:0;border-radius:10px;background:linear-gradient(135deg,#ef4444,#e11d48);color:#fff;font-weight:600;box-shadow:0 6px 16px rgba(225,29,72,.28)}' +
            m + ' .ct-btn-save:hover{color:#fff;filter:brightness(.96)}' +
            m + ' .ct-btn-save:disabled{opacity:.65;box-shadow:none}' +
            '@media (max-width:991.98px){' + m + ' .ct-svc-footer-actions{border-left:0;padding-left:0;margin-left:auto}' +
            m + ' .ct-sum-card{min-width:calc(50% - .4rem)}}'
        ).appendTo('head');
    }

    var SERVICE_ICONS = {
        hotels: 'fa-hotel',
        flight: 'fa-plane',
        land_package: 'fa-umbrella-beach',
        visa: 'fa-passport',
        transfers: 'fa-car',
        travel_insurance: 'fa-shield-alt',
        forex: 'fa-exchange-alt',
        train: 'fa-train',
        tours: 'fa-binoculars',
        cruise: 'fa-ship'
    };

    function paymentStatus(total, paid) {
        if (paid > 0 && paid >= total - 0.005) {
            return { cls: 'is-paid', text: 'Paid' };
        }
        if (paid > 0) {
            return { cls: 'is-partial', text: 'Partially Paid' };
        }
        return { cls: 'is-unpaid', text: 'Unpaid' };
    }

    function statusPillHtml(total, paid) {
        var s = paymentStatus(total, paid);
        return '<span class="ct-status-pill ' + s.cls + '">' + s.text + '</span>';
    }

    function payBtnHtml(balance) {
        var clear = balance <= 0;
        var title = clear ? 'Fully paid — view payments' : 'Add payment';
        return '<button type="button" class="ct-act-btn ct-pay-row" title="' + title + '" aria-label="' + title + '">' +
            '<i class="fas ' + (clear ? 'fa-wallet' : 'fa-rupee-sign') + '"></i></button>';
    }

    function voucherBtnHtml(count) {
        count = parseInt(count, 10) || 0;
        var text = count === 1 ? '1 file' : (count + ' files');
        var title = count ? (text + ' attached — click to manage vouchers') : 'Attach service vouchers';
        return '<button type="button" class="ct-svc-voucher' + (count ? ' is-attached' : '') + '"' +
            ' title="' + title + '" aria-label="' + title + '">' +
            '<i class="fas fa-paperclip"></i><span>' + text + '</span><i class="fas fa-chevron-down"></i>' +
            '</button>';
    }

    function voucherActionBtnHtml(count) {
        count = parseInt(count, 10) || 0;
        var title = count ? (count + ' voucher' + (count === 1 ? '' : 's') + ' attached') : 'Attach service vouchers';
        return '<button type="button" class="ct-act-btn ct-svc-voucher-act' + (count ? ' is-attached' : '') + '"' +
            ' title="' + title + '" aria-label="' + title + '"><i class="fas fa-paperclip"></i>' +
            (count ? '<span class="ct-svc-voucher-count">' + count + '</span>' : '') +
            '</button>';
    }

    function setRowVoucherCount(uid, count) {
        var $row = $('#ctDetailRows .ct-detail-row').filter(function () {
            return String($(this).attr('data-uid')) === String(uid);
        });
        $row.attr('data-vouchers', String(parseInt(count, 10) || 0));
        $row.find('.ct-svc-voucher').replaceWith(voucherBtnHtml(count));
        $row.find('.ct-svc-voucher-act').replaceWith(voucherActionBtnHtml(count));
    }

    function rowHtml(row) {
        row = row || {};
        ensureVoucherBtnStyles();
        var key = row.key || '';
        var uid = row.uid || uidService();
        var voucherCount = parseInt(row.voucher_count, 10) || 0;
        var paymentCount = parseInt(row.payment_count, 10) || 0;
        var label = row.label || serviceMap[key] || key;
        var total = row.total != null ? row.total : '';
        var paid = row.paid != null ? row.paid : '';
        var balanceNum = Math.max(0, parseNum(total) - parseNum(paid));

        return '' +
            '<div class="ct-detail-row' + (parseNum(paid) > 0 ? ' has-paid' : '') + '" data-key="' + esc(key) + '" data-uid="' + esc(uid) + '" data-vouchers="' + voucherCount + '" data-payments="' + paymentCount + '">' +
            '<div class="ct-row-no" aria-hidden="true"></div>' +
            '<div class="ct-detail-label"><span class="ct-svc-icon is-' + esc(key) + '"><i class="fas ' + (SERVICE_ICONS[key] || 'fa-concierge-bell') + '"></i></span>' +
            '<span>' + esc(label) + '</span></div>' +
            '<div class="ct-detail-field ct-supplier-wrap">' +
            '<input type="text" class="form-control ct-supplier" placeholder="Type supplier name" ' +
            'autocomplete="off" spellcheck="false" value="' + esc(row.supplier || '') + '">' +
            '</div>' +
            '<div class="ct-detail-attach">' + voucherBtnHtml(voucherCount) + '</div>' +
            '<div class="ct-detail-field ct-num"><input type="text" inputmode="decimal" class="form-control ct-total" placeholder="0.00" aria-label="Total" value="' + esc(amountInputValue(total)) + '"></div>' +
            '<div class="ct-detail-field ct-num"><input type="text" inputmode="decimal" class="form-control ct-paid" placeholder="0.00" aria-label="Paid" value="' + esc(amountInputValue(paid)) + '"></div>' +
            '<div class="ct-balance-wrap">' +
            '<span class="ct-balance-val' + (balanceNum <= 0 ? ' is-clear' : '') + '">' + money2(balanceNum) + '</span>' +
            '</div>' +
            '<div class="ct-status-wrap">' + statusPillHtml(parseNum(total), parseNum(paid)) + '</div>' +
            '<div class="ct-detail-actions">' +
            payBtnHtml(balanceNum) +
            '<button type="button" class="ct-act-btn ct-reminders" title="Reminders" aria-label="Reminders"><i class="far fa-file-alt"></i></button>' +
            '<button type="button" class="ct-act-btn ct-view-row" title="View payment details" aria-label="View payment details"><i class="far fa-eye"></i></button>' +
            '<button type="button" class="ct-act-btn ct-remove-row" title="Remove" aria-label="Remove"><i class="far fa-trash-alt"></i></button>' +
            voucherActionBtnHtml(voucherCount) +
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
        var balance = Math.max(0, total - paid);
        $row.find('.ct-balance-val').text(money2(balance)).toggleClass('is-clear', balance <= 0);
        $row.toggleClass('has-paid', paid > 0);
        $row.find('.ct-pay-row').replaceWith(payBtnHtml(balance));
        $row.find('.ct-status-wrap').html(statusPillHtml(total, paid));
    }

    function collectServices() {
        var list = [];
        $('#ctDetailRows .ct-detail-row').each(function () {
            var $row = $(this);
            list.push({
                uid: String($row.attr('data-uid') || ''),
                key: $row.data('key'),
                label: serviceMap[$row.data('key')] || $row.data('key'),
                supplier: $row.find('.ct-supplier').val(),
                total: parseNum($row.find('.ct-total').val()),
                paid: parseNum($row.find('.ct-paid').val())
            });
        });
        return list;
    }

    var travellersState = [];
    var travellerDocsDraft = [];
    var savedGuestsState = [];

    function initialsFromName(name) {
        name = String(name || '').replace(/^(Mr|Mrs|Ms|Master|Miss|Mstr)\.?\s+/i, '').trim();
        if (!name) {
            return '—';
        }
        var parts = name.split(/\s+/);
        var a = (parts[0] || '').charAt(0);
        var b = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
        return (a + b).toUpperCase() || '—';
    }

    function formatPassportExpiry(raw) {
        var s = String(raw || '').trim();
        if (!s) {
            return '';
        }
        var m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!m) {
            return s;
        }
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var mi = parseInt(m[2], 10) - 1;
        return 'Expires ' + parseInt(m[3], 10) + ' ' + (months[mi] || m[2]) + ' ' + m[1];
    }

    function setPrimaryContact(name, phone, email) {
        name = String(name || '').trim();
        phone = String(phone || '').trim();
        email = String(email || '').trim();
        $('#ctGuestName').val(name);
        $('#ctMobileNo').val(phone);
        $('#ctEmail').val(email);
        $('#ctPrimaryName').text(name || '—');
        $('#ctPrimaryPhone').text(phone || '—');
        $('#ctPrimaryEmail').text(email || '—');
        $('#ctPrimaryAvatar').text(initialsFromName(name));
    }

    function setGuestAttachment(name, path) {
        name = String(name || '').trim();
        path = String(path || '').trim();
        $('#ctGuestAttachmentName').val(name);
        $('#ctGuestAttachmentPath').val(path);
        var $btn = $('#ctGuestAttachBtn');
        if (name || path) {
            $btn.addClass('is-attached')
                .attr('title', 'Attachment: ' + (name || path))
                .attr('aria-label', 'Attachment: ' + (name || path));
        } else {
            $btn.removeClass('is-attached')
                .attr('title', 'Add attachment')
                .attr('aria-label', 'Add attachment');
        }
    }

    function hidePrimaryEditPanel() {
        $('#ctPrimaryEditPanel').addClass('d-none');
        $('#ctGuestEditBtn').removeClass('is-editing');
    }

    function showPrimaryEditPanel() {
        $('#ctGuestNameEdit').val($('#ctGuestName').val());
        $('#ctMobileNoEdit').val($('#ctMobileNo').val());
        $('#ctEmailEdit').val($('#ctEmail').val());
        $('#ctPrimaryEditPanel').removeClass('d-none');
        $('#ctGuestEditBtn').addClass('is-editing');
        $('#ctGuestNameEdit').trigger('focus');
    }

    function uidTraveller() {
        return 't_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 7);
    }

    function normalizeTraveller(row) {
        row = row || {};
        var type = String(row.type || 'adult').toLowerCase();
        if (type !== 'adult' && type !== 'child' && type !== 'infant') {
            type = 'adult';
        }
        var age = row.age;
        if (age === '' || age === null || typeof age === 'undefined') {
            age = null;
        } else {
            age = Math.max(0, parseInt(age, 10) || 0);
        }
        var docs = Array.isArray(row.documents) ? row.documents.map(function (d) {
            return {
                name: String((d && d.name) || '').trim(),
                path: String((d && d.path) || '').trim()
            };
        }).filter(function (d) { return d.name || d.path; }) : [];
        return {
            id: String(row.id || uidTraveller()),
            name: String(row.name || '').trim(),
            type: type,
            age: age,
            passport_number: String(row.passport_number || '').trim(),
            passport_expiry: String(row.passport_expiry || '').trim(),
            relation: String(row.relation || '').trim(),
            mobile: String(row.mobile || '').trim(),
            email: String(row.email || '').trim(),
            dob: /^\d{4}-\d{2}-\d{2}$/.test(String(row.dob || '')) ? String(row.dob) : '',
            gender: String(row.gender || '').trim(),
            nationality: String(row.nationality || '').trim(),
            id_type: String(row.id_type || '').trim(),
            id_number: String(row.id_number || '').trim(),
            address: String(row.address || '').trim(),
            city: String(row.city || '').trim(),
            state: String(row.state || '').trim(),
            country: String(row.country || '').trim(),
            pincode: String(row.pincode || '').trim(),
            documents: docs
        };
    }

    var TRAVELLER_DETAIL_FIELDS = {
        dob: '#ctTravellerDob',
        gender: '#ctTravellerGender',
        nationality: '#ctTravellerNationality',
        id_type: '#ctTravellerIdType',
        id_number: '#ctTravellerIdNumber',
        address: '#ctTravellerAddress',
        city: '#ctTravellerCity',
        state: '#ctTravellerState',
        country: '#ctTravellerCountry',
        pincode: '#ctTravellerPincode'
    };

    function ageFromDob(dob) {
        var m = String(dob || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!m) {
            return null;
        }
        var now = new Date();
        var age = now.getFullYear() - parseInt(m[1], 10);
        var monthDiff = (now.getMonth() + 1) - parseInt(m[2], 10);
        if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < parseInt(m[3], 10))) {
            age--;
        }
        return age >= 0 && age < 130 ? age : null;
    }

    function travellerTypeForAge(age) {
        if (age === null) {
            return '';
        }
        return age < 2 ? 'infant' : (age < 12 ? 'child' : 'adult');
    }

    function travellerNameKey(name) {
        return String(name || '').trim().toLowerCase()
            .replace(/^(mr|mrs|ms|miss|master|dr)\.?\s+/, '')
            .replace(/\s+/g, ' ');
    }

    function setSavedGuests(list) {
        savedGuestsState = (Array.isArray(list) ? list : []).map(normalizeTraveller).filter(function (g) {
            return g.name !== '';
        });
    }

    function availableSavedGuests() {
        var inPax = {};
        travellersState.forEach(function (t) {
            inPax[String(t.id)] = true;
            inPax['name:' + travellerNameKey(t.name)] = true;
        });
        return savedGuestsState.filter(function (g) {
            return !inPax[String(g.id)] && !inPax['name:' + travellerNameKey(g.name)];
        });
    }

    function ensurePaxBadgeStyles() {
        if (document.getElementById('ctPaxBadgeStyles')) {
            return;
        }
        $('<style id="ctPaxBadgeStyles">').text(
            '#confirmTourModal .ct-pax-name-wrap{position:relative;display:inline-block;padding-right:14px;padding-top:4px}' +
            '#confirmTourModal .ct-pax-name-wrap .ct-pax-name{display:inline}' +
            '#confirmTourModal .ct-pax-rel-badge{position:absolute;top:-5px;right:-6px;min-width:18px;height:18px;padding:0 4px;' +
            'border-radius:999px;background:#e11d48;color:#fff;font-size:.66rem;font-weight:700;line-height:18px;text-align:center;' +
            'box-shadow:0 0 0 2px #fff;cursor:default;user-select:none}' +
            '#confirmTourModal .ct-row-btn-disabled-wrap{display:inline-flex;cursor:not-allowed}' +
            '#confirmTourModal .ct-row-btn.is-disabled,#confirmTourModal .ct-row-btn.is-disabled:hover{' +
            'opacity:.4;background:#f1f5f9;border-color:#e2e8f0;color:#94a3b8;pointer-events:none;box-shadow:none}'
        ).appendTo('head');
    }

    function ensurePrimaryStats() {
        var $card = $('#ctPrimaryCard');
        if (!$card.length || $card.find('.ct-primary-stats').length) {
            return;
        }
        if (!document.getElementById('ctPrimaryStatsStyles')) {
            $('<style id="ctPrimaryStatsStyles">').text(
                '#confirmTourModal .ct-primary-card{flex-wrap:wrap}' +
                '#confirmTourModal .ct-primary-body{flex:1 1 220px}' +
                '#confirmTourModal .ct-primary-stats{flex:0 1 auto;display:flex;align-items:stretch;background:rgba(255,255,255,.75);' +
                'border:1px solid #fecdd3;border-radius:10px;padding:.45rem .25rem;margin-left:auto}' +
                '#confirmTourModal .ct-pstat{display:flex;align-items:center;gap:.5rem;padding:0 .8rem;border-left:1px solid #fbcfe8;white-space:nowrap}' +
                '#confirmTourModal .ct-pstat:first-child{border-left:0}' +
                '#confirmTourModal .ct-pstat-icon{font-size:1.05rem;color:#be123c;width:20px;text-align:center}' +
                '#confirmTourModal .ct-pstat-label{display:block;font-size:.66rem;color:#64748b;line-height:1.1}' +
                '#confirmTourModal .ct-pstat-val{display:block;font-size:.92rem;font-weight:700;color:#0f172a;line-height:1.25}' +
                '#confirmTourModal .ct-pstat-group{display:flex}' +
                '#confirmTourModal .ct-pstat-group+.ct-pstat-group{border-left:2px solid #fda4af;margin-left:.1rem}' +
                '#confirmTourModal .ct-pstat.is-destination .ct-pstat-val,#confirmTourModal .ct-pstat.is-departure .ct-pstat-val{max-width:150px;overflow:hidden;text-overflow:ellipsis}' +
                '#confirmTourModal .ct-pstat.is-paid .ct-pstat-val{color:#15803d}' +
                '#confirmTourModal .ct-pstat.is-due .ct-pstat-val{color:#dc2626}' +
                '#confirmTourModal .ct-pstat.is-due.is-clear .ct-pstat-val{color:#15803d}' +
                '#confirmTourModal .ct-pstat.is-paid,#confirmTourModal .ct-pstat.is-due{cursor:pointer;border-radius:6px;transition:background .12s}' +
                '#confirmTourModal .ct-pstat.is-paid:hover,#confirmTourModal .ct-pstat.is-due:hover{background:rgba(255,241,242,.9)}' +
                '#confirmTourModal .ct-pstat-pay-wrap{display:flex;align-items:center;padding:0 .45rem 0 .6rem;border-left:1px solid #fbcfe8}' +
                '#confirmTourModal .ct-pstat-pay{width:32px;height:32px;padding:0;border:0;border-radius:8px;background:#16a34a;color:#fff;font-size:.9rem;display:inline-flex;align-items:center;justify-content:center}' +
                '#confirmTourModal .ct-pstat-pay:hover{filter:brightness(.95)}' +
                '@media (max-width:991.98px){#confirmTourModal .ct-primary-stats{margin-left:0;width:100%;flex-wrap:wrap;row-gap:.45rem}' +
                '#confirmTourModal .ct-pstat-group{flex:1 1 100%}#confirmTourModal .ct-pstat-group+.ct-pstat-group{border-left:0;margin-left:0;border-top:1px solid #fbcfe8;padding-top:.45rem}' +
                '#confirmTourModal .ct-pstat{flex:1 1 0}}'
            ).appendTo('head');
        }
        var stat = function (cls, icon, label) {
            return '<div class="ct-pstat ' + cls + '"><i class="fas ' + icon + ' ct-pstat-icon"></i>' +
                '<div><span class="ct-pstat-label">' + label + '</span><span class="ct-pstat-val js-pstat-val">0</span></div></div>';
        };
        $card.append(
            '<div class="ct-primary-stats" aria-label="Tour summary">' +
            '<div class="ct-pstat-group">' +
            stat('is-guests', 'fa-users', 'Guests') +
            stat('is-destination', 'fa-map-marker-alt', 'Destination') +
            stat('is-travel-date', 'fa-calendar-alt', 'Date of Travel') +
            stat('is-departure', 'fa-plane-departure', 'Departure City') +
            '</div><div class="ct-pstat-group">' +
            stat('is-total', 'fa-rupee-sign', 'Package Total') +
            stat('is-paid', 'fa-check-circle', 'Paid') +
            stat('is-due', 'fa-hourglass-half', 'Due') +
            '<div class="ct-pstat-pay-wrap"><button type="button" class="ct-pstat-pay js-cust-pay-add" title="Receive Payment" aria-label="Receive Payment">' +
            '<i class="far fa-eye"></i></button></div>' +
            '</div></div>'
        );
    }

    function updatePrimaryStats() {
        ensurePrimaryStats();
        var total = 0;
        var paid = 0;
        var due = 0;
        var payments = 0;
        $('#ctDetailRows .ct-detail-row').each(function () {
            var $row = $(this);
            var t = parseNum($row.find('.ct-total').val());
            var p = parseNum($row.find('.ct-paid').val());
            total += t;
            paid += p;
            due += Math.max(0, t - p);
            payments += Math.max(parseInt($row.attr('data-payments'), 10) || 0, p > 0 ? 1 : 0);
        });
        var $stats = $('#ctPrimaryCard .ct-primary-stats');
        var set = function (cls, text) {
            $stats.find('.ct-pstat.' + cls + ' .js-pstat-val').text(text);
        };
        set('is-guests', String(travellersState.length));
        var trip = activeTripInfo || {};
        var tripVal = function (cls, text) {
            $stats.find('.ct-pstat.' + cls + ' .js-pstat-val').text(text || '—').attr('title', text || '');
        };
        tripVal('is-destination', trip.destination);
        tripVal('is-travel-date', tripDateLabel(trip.tentative_date));
        tripVal('is-departure', trip.departure_city);
        var loaded = activePackageTotal !== null && activeCustomerPaid !== null;
        var custDue = customerDue();
        set('is-total', activePackageTotal === null ? '—' : '\u20B9' + money(activePackageTotal));
        set('is-paid', activeCustomerPaid === null ? '—' : '\u20B9' + money(activeCustomerPaid));
        set('is-due', loaded ? '\u20B9' + money(custDue) : '—');
        $stats.find('.ct-pstat.is-due').toggleClass('is-clear', loaded && custDue <= 0);
        $stats.find('.ct-pstat.is-paid, .ct-pstat.is-due').attr('title', 'View customer payments');

        var $sum = $('#ctSvcSummary');
        if ($sum.length) {
            $sum.find('.js-sum-total').text('\u20B9 ' + money2(total));
            $sum.find('.js-sum-paid').text('\u20B9 ' + money2(paid));
            $sum.find('.js-sum-due').text('\u20B9 ' + money2(due));
            $sum.find('.js-sum-payments').text(String(payments));
        }
    }

    function renderTravellers() {
        ensurePaxBadgeStyles();
        updatePrimaryStats();
        var $tbody = $('#ctPaxRows').empty();
        if (!travellersState.length) {
            $tbody.append('<tr class="ct-pax-empty"><td colspan="7">No travellers yet. Click Add Guest.</td></tr>');
            return;
        }
        travellersState.forEach(function (t, idx) {
            var typeLabel = t.type === 'child' ? 'Child' : (t.type === 'infant' ? 'Infant' : 'Adult');
            var typeClass = 'is-' + t.type;
            var passportHtml = t.passport_number
                ? ('<span class="ct-pax-passport-no">' + esc(t.passport_number) + '</span>' +
                    (t.passport_expiry
                        ? '<span class="ct-pax-passport-exp">' + esc(formatPassportExpiry(t.passport_expiry)) + '</span>'
                        : ''))
                : '<span class="text-muted">—</span>';
            var docCount = (t.documents && t.documents.length) ? t.documents.length : 0;
            var docLabel = docCount === 1 ? '1 file' : (docCount + ' files');
            var isPrimary = idx === 0;
            var subLabel = isPrimary ? 'Primary Contact' : (t.relation || '');
            var badgeLetter = subLabel.charAt(0).toUpperCase();
            $tbody.append(
                '<tr data-traveller-id="' + esc(t.id) + '">' +
                '<td>' + (idx + 1) + '</td>' +
                '<td><span class="ct-pax-name-wrap"><span class="ct-pax-name">' + esc(t.name) + '</span>' +
                (badgeLetter
                    ? '<span class="ct-pax-rel-badge' + (isPrimary ? ' is-primary' : '') + '" title="' + esc(subLabel) + '"' +
                        ' aria-label="' + esc(subLabel) + '">' + esc(badgeLetter) + '</span>'
                    : '') +
                '</span></td>' +
                '<td><span class="ct-pax-type ' + typeClass + '">' + esc(typeLabel) + '</span></td>' +
                '<td>' + (t.age != null ? esc(String(t.age)) : '—') + '</td>' +
                '<td>' + passportHtml + '</td>' +
                '<td><button type="button" class="ct-pax-docs js-ct-pax-docs" title="Documents">' +
                '<i class="fas fa-paperclip"></i> ' + esc(docLabel) + '</button></td>' +
                '<td><div class="ct-pax-actions">' +
                '<button type="button" class="ct-row-btn js-ct-pax-edit" title="Edit"><i class="fas fa-pen"></i></button>' +
                '<button type="button" class="ct-row-btn js-ct-pax-attach' + (docCount ? ' is-attached' : '') + '" title="Add attachment"><i class="fas fa-paperclip"></i></button>' +
                (isPrimary
                    ? '<span class="ct-row-btn-disabled-wrap" title="Primary contact cannot be removed">' +
                        '<button type="button" class="ct-row-btn is-disabled" disabled aria-disabled="true" tabindex="-1" aria-label="Primary contact cannot be removed">' +
                        '<i class="fas fa-trash-alt"></i></button></span>'
                    : '<button type="button" class="ct-row-btn ct-guest-clear js-ct-pax-delete" title="Remove from this tour"><i class="fas fa-trash-alt"></i></button>') +
                '</div></td></tr>'
            );
        });
    }

    function fillTravellerForm(traveller, isPrimary) {
        ensureTravellerScanUi();
        traveller = traveller ? normalizeTraveller(traveller) : null;
        fillTravellerDetailFields(traveller);
        clearOcrHighlights();
        travellerDocsDraft = traveller ? (traveller.documents || []).slice() : [];
        $('#ctTravellerEditId').val(traveller ? traveller.id : '');
        $('#ctTravellerName').val(traveller ? traveller.name : '');
        $('#ctTravellerType').val(traveller ? traveller.type : 'adult');
        $('#ctTravellerAge').val(traveller && traveller.age != null ? traveller.age : '');
        $('#ctTravellerPassport').val(traveller ? traveller.passport_number : '');
        $('#ctTravellerPassportExpiry').val(traveller ? traveller.passport_expiry : '');
        var mobile = traveller ? traveller.mobile : '';
        var email = traveller ? traveller.email : '';
        if (isPrimary) {
            mobile = mobile || $('#ctMobileNo').val() || '';
            email = email || $('#ctEmail').val() || '';
        }
        $('#ctTravellerMobile').val(mobile);
        $('#ctTravellerEmail').val(email);
        var relation = traveller && traveller.relation && traveller.relation !== 'Self' ? traveller.relation : 'Relative';
        if (!$('#ctTravellerRelation option[value="' + relation.replace(/"/g, '') + '"]').length) {
            $('#ctTravellerRelation').append($('<option>').val(relation).text(relation));
        }
        $('#ctTravellerRelation').val(relation);
        updateTravellerDocsHint();
    }

    function openTravellerModal(traveller) {
        var idx = traveller ? findTravellerIndex(traveller.id) : -1;
        // First PAX slot is always the Primary Contact (also when adding into an empty list).
        var isPrimary = traveller ? idx === 0 : travellersState.length === 0;
        $('#ctTravellerModal').data('isPrimary', isPrimary);
        fillTravellerForm(traveller, isPrimary);
        resetTravellerScan(traveller);
        $('#ctTravellerPrimaryNote').toggleClass('d-none', !isPrimary);
        $('#ctTravellerRelationWrap').toggleClass('d-none', isPrimary);

        var saved = (!traveller && !isPrimary) ? availableSavedGuests() : [];
        var $sel = $('#ctTravellerSaved').empty();
        if (saved.length) {
            $sel.append('<option value="">— New guest —</option>');
            saved.forEach(function (g) {
                $sel.append($('<option>').val(g.id).text(g.name + (g.relation ? ' (' + g.relation + ')' : '')));
            });
        }
        $('#ctTravellerSavedWrap').toggleClass('d-none', !saved.length);

        $('#ctTravellerModalLabel').text(isPrimary && traveller ? 'Edit Primary Contact' : (traveller ? 'Edit Traveller' : 'Add Guest'));
        $('#ctTravellerModal').modal('show');
    }

    function updateTravellerDocsHint() {
        var n = travellerDocsDraft.length;
        $('#ctTravellerDocsHint').text(n ? (n + ' file' + (n === 1 ? '' : 's') + ' attached') : 'No files');
    }

    function findTravellerIndex(id) {
        id = String(id || '');
        for (var i = 0; i < travellersState.length; i++) {
            if (String(travellersState[i].id) === id) {
                return i;
            }
        }
        return -1;
    }

    // ---- Add Guest: scan ID document → AI auto-fill ----
    var scanState = { draftId: '', isNew: false, saved: false, uploads: [], xhr: null, verifyRequired: false };

    function docKey(v) {
        return String(v || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    }

    function ensureTravellerScanUi() {
        var $body = $('#ctTravellerModal .modal-body');
        if (!$body.length || $('#ctScanBox').length) {
            return;
        }
        if (!document.getElementById('ctScanStyles')) {
            $('<style id="ctScanStyles">').text(
                '#ctTravellerModal .modal-dialog{max-width:620px}' +
                '#ctTravellerModal .modal-body{max-height:calc(100vh - 170px);overflow-y:auto}' +
                '.ct-scan-box{border:1px solid #c7d2fe;background:linear-gradient(135deg,#eef2ff,#f8fafc);border-radius:12px;padding:.75rem .85rem;margin-bottom:1rem}' +
                '.ct-scan-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;margin-bottom:.6rem}' +
                '.ct-scan-title{font-weight:700;font-size:.9rem;color:#312e81}.ct-scan-title i{color:#4f46e5;margin-right:.3rem}' +
                '.ct-scan-sub{font-size:.72rem;color:#64748b;line-height:1.35;margin-top:.15rem}' +
                '.ct-scan-hint{width:150px;flex:0 0 150px;font-size:.78rem}' +
                '.ct-scan-drop{display:flex;align-items:center;justify-content:center;gap:.5rem;border:1.5px dashed #a5b4fc;border-radius:10px;' +
                'padding:.7rem;background:#fff;color:#4338ca;font-size:.82rem;cursor:pointer;transition:all .15s}' +
                '.ct-scan-drop:hover,.ct-scan-drop:focus,.ct-scan-drop.is-drag{border-color:#4f46e5;background:#eef2ff;outline:0}' +
                '.ct-scan-drop i{font-size:1.15rem}.ct-scan-drop.is-busy{pointer-events:none;opacity:.55}' +
                '.ct-scan-status{margin-top:.6rem;border-radius:9px;padding:.55rem .7rem;font-size:.8rem;display:flex;gap:.55rem;align-items:flex-start}' +
                '.ct-scan-status>i{margin-top:.15rem}.ct-scan-status .ct-scan-msg{flex:1;min-width:0}' +
                '.ct-scan-status.is-uploading,.ct-scan-status.is-processing{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe}' +
                '.ct-scan-status.is-success{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}' +
                '.ct-scan-status.is-warning{background:#fffbeb;color:#92400e;border:1px solid #fde68a}' +
                '.ct-scan-status.is-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}' +
                '.ct-scan-progress{height:5px;background:#dbeafe;border-radius:4px;margin-top:.35rem;overflow:hidden}' +
                '.ct-scan-progress>span{display:block;height:100%;background:#3b82f6;width:0;transition:width .2s}' +
                '.ct-scan-meta{font-size:.72rem;opacity:.85;margin-top:.15rem}.ct-scan-meta a{font-weight:600}' +
                '.ct-scan-note{margin-top:.35rem;padding:.35rem .5rem;border-radius:7px;font-size:.75rem;background:rgba(255,255,255,.7)}' +
                '.ct-scan-note.is-warn{color:#9a3412;border:1px solid #fed7aa}.ct-scan-note.is-info{color:#1e3a8a;border:1px solid #bfdbfe}' +
                '.ct-scan-retry{border:0;background:none;color:inherit;text-decoration:underline;font-weight:600;padding:0;font-size:.78rem}' +
                '#ctTravellerModal .ct-ocr-filled{background:#fefce8;border-color:#facc15;box-shadow:0 0 0 2px rgba(250,204,21,.18)}' +
                '.ct-trv-more{border-top:1px solid #e2e8f0;margin-top:.25rem;padding-top:.6rem;margin-bottom:.6rem}' +
                '.ct-trv-more-toggle{border:0;background:none;padding:0;font-weight:600;font-size:.82rem;color:#334155}' +
                '.ct-trv-more-toggle i{transition:transform .15s;margin-right:.35rem;font-size:.7rem}' +
                '.ct-trv-more.is-open .ct-trv-more-toggle i{transform:rotate(180deg)}' +
                '.ct-trv-more-body{margin-top:.6rem}' +
                '.ct-scan-verify{border:1px solid #fde68a;background:#fffbeb;border-radius:9px;padding:.5rem .7rem;font-size:.8rem;color:#78350f;margin-bottom:.6rem}' +
                '.ct-scan-verify label{display:flex;gap:.5rem;align-items:flex-start;cursor:pointer;font-weight:500}' +
                '.ct-scan-verify input{margin-top:.2rem}' +
                '.ct-scan-verify.is-invalid{border-color:#f87171;background:#fef2f2;animation:ctShake .3s}' +
                '@keyframes ctShake{25%{transform:translateX(-4px)}75%{transform:translateX(4px)}}'
            ).appendTo('head');
        }

        var hintOptions = [
            ['auto', 'Auto-detect'], ['passport', 'Passport'], ['aadhaar', 'Aadhaar Card'], ['pan', 'PAN Card'],
            ['visa', 'Visa'], ['driving_licence', 'Driving Licence'], ['voter_id', 'Voter ID'], ['other', 'Other ID']
        ].map(function (o) { return '<option value="' + o[0] + '">' + o[1] + '</option>'; }).join('');

        var $scan = $(
            '<div class="ct-scan-box" id="ctScanBox">' +
            '<div class="ct-scan-head"><div>' +
            '<div class="ct-scan-title"><i class="fas fa-magic"></i>Auto-fill from ID document</div>' +
            '<div class="ct-scan-sub">Upload a Passport, Aadhaar, PAN, Visa or other ID (JPG, PNG or PDF, max 4 MB). ' +
            'Details are read with AI and stored privately — please verify them before saving.</div></div>' +
            '<select class="form-control form-control-sm ct-scan-hint" id="ctScanHint" title="Document type">' + hintOptions + '</select></div>' +
            '<div class="ct-scan-drop" id="ctScanDrop" tabindex="0" role="button" aria-label="Upload ID document">' +
            '<i class="fas fa-id-card"></i><span>Drop document here or <u>browse</u></span></div>' +
            '<input type="file" id="ctScanFile" class="d-none" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">' +
            '<div class="ct-scan-status d-none" id="ctScanStatus" role="status" aria-live="polite"></div>' +
            '</div>'
        );
        var $saved = $('#ctTravellerSavedWrap');
        if ($saved.length) {
            $saved.after($scan);
        } else {
            $body.prepend($scan);
        }

        var field = function (col, id, label, control) {
            return '<div class="form-group ' + col + '"><label class="ct-label" for="' + id + '">' + label + '</label>' + control + '</div>';
        };
        var input = function (id, extra) {
            return '<input type="text" class="form-control" id="' + id + '" autocomplete="off"' + (extra || '') + '>';
        };
        var $more = $(
            '<div class="ct-trv-more" id="ctTravellerMore">' +
            '<button type="button" class="ct-trv-more-toggle" id="ctTravellerMoreToggle" aria-expanded="false">' +
            '<i class="fas fa-chevron-down"></i>Identity &amp; address details</button>' +
            '<div class="ct-trv-more-body" id="ctTravellerMoreBody" style="display:none">' +
            '<div class="form-row">' +
            field('col-md-4', 'ctTravellerDob', 'Date of Birth', '<input type="date" class="form-control" id="ctTravellerDob">') +
            field('col-md-4', 'ctTravellerGender', 'Gender',
                '<select class="form-control" id="ctTravellerGender"><option value="">—</option>' +
                '<option>Male</option><option>Female</option><option>Other</option></select>') +
            field('col-md-4', 'ctTravellerNationality', 'Nationality', input('ctTravellerNationality', ' placeholder="e.g. Indian"')) +
            '</div><div class="form-row">' +
            field('col-md-5', 'ctTravellerIdType', 'ID Type', input('ctTravellerIdType', ' list="ctTravellerIdTypeList" placeholder="e.g. Aadhaar Card"') +
                '<datalist id="ctTravellerIdTypeList"><option value="Passport"><option value="Aadhaar Card"><option value="PAN Card">' +
                '<option value="Visa"><option value="Driving Licence"><option value="Voter ID"></datalist>') +
            field('col-md-7', 'ctTravellerIdNumber', 'Document Number', input('ctTravellerIdNumber')) +
            '</div><div class="form-row">' +
            field('col-12', 'ctTravellerAddress', 'Address', input('ctTravellerAddress', ' placeholder="House / street / locality"')) +
            '</div><div class="form-row">' +
            field('col-md-3', 'ctTravellerCity', 'City', input('ctTravellerCity')) +
            field('col-md-3', 'ctTravellerState', 'State', input('ctTravellerState')) +
            field('col-md-3', 'ctTravellerCountry', 'Country', input('ctTravellerCountry')) +
            field('col-md-3', 'ctTravellerPincode', 'Pincode', input('ctTravellerPincode', ' inputmode="numeric"')) +
            '</div></div></div>'
        );
        var $passportRow = $('#ctTravellerPassport').closest('.form-row');
        if ($passportRow.length) {
            $passportRow.after($more);
        } else {
            $body.append($more);
        }
        $more.after(
            '<div class="ct-scan-verify d-none" id="ctScanVerifyWrap"><label>' +
            '<input type="checkbox" id="ctScanVerify">' +
            '<span>I have checked the auto-filled details against the document and they are correct.</span></label></div>'
        );
    }

    function setTravellerMoreOpen(open) {
        var $wrap = $('#ctTravellerMore');
        $wrap.toggleClass('is-open', !!open);
        $('#ctTravellerMoreToggle').attr('aria-expanded', open ? 'true' : 'false');
        $('#ctTravellerMoreBody')[open ? 'slideDown' : 'slideUp'](150);
    }

    function fillTravellerDetailFields(traveller) {
        var any = false;
        Object.keys(TRAVELLER_DETAIL_FIELDS).forEach(function (key) {
            var val = traveller ? String(traveller[key] || '') : '';
            if (key === 'gender' && val && !$(TRAVELLER_DETAIL_FIELDS.gender + ' option').filter(function () {
                return this.value === val;
            }).length) {
                val = '';
            }
            $(TRAVELLER_DETAIL_FIELDS[key]).val(val);
            any = any || val !== '';
        });
        $('#ctTravellerMoreBody').stop(true, true).toggle(any);
        $('#ctTravellerMore').toggleClass('is-open', any);
        $('#ctTravellerMoreToggle').attr('aria-expanded', any ? 'true' : 'false');
    }

    function readTravellerDetailFields() {
        var out = {};
        Object.keys(TRAVELLER_DETAIL_FIELDS).forEach(function (key) {
            out[key] = $.trim(String($(TRAVELLER_DETAIL_FIELDS[key]).val() || ''));
        });
        return out;
    }

    function setScanStatus(state, html) {
        var icons = {
            uploading: 'fas fa-circle-notch fa-spin',
            processing: 'fas fa-circle-notch fa-spin',
            success: 'fas fa-check-circle',
            warning: 'fas fa-exclamation-triangle',
            error: 'fas fa-times-circle'
        };
        var $s = $('#ctScanStatus');
        if (!state) {
            $s.addClass('d-none').empty().removeClass('is-uploading is-processing is-success is-warning is-error');
            return;
        }
        $s.removeClass('d-none is-uploading is-processing is-success is-warning is-error')
            .addClass('is-' + state)
            .html('<i class="' + icons[state] + '"></i><div class="ct-scan-msg">' + html + '</div>');
        $('#ctScanDrop').toggleClass('is-busy', state === 'uploading' || state === 'processing');
    }

    function setScanVerifyRequired(required) {
        scanState.verifyRequired = !!required;
        $('#ctScanVerify').prop('checked', false);
        $('#ctScanVerifyWrap').toggleClass('d-none', !required).removeClass('is-invalid');
    }

    function clearOcrHighlights() {
        $('#ctTravellerModal .ct-ocr-filled').removeClass('ct-ocr-filled');
    }

    function currentScanTravellerId() {
        return String($('#ctTravellerEditId').val() || scanState.draftId || '');
    }

    function resetTravellerScan(traveller) {
        if (scanState.xhr) {
            try { scanState.xhr.abort(); } catch (e) { /* ignore */ }
        }
        scanState = {
            draftId: traveller ? String(traveller.id) : uidTraveller(),
            isNew: !traveller,
            saved: false,
            uploads: [],
            xhr: null,
            verifyRequired: false
        };
        $('#ctScanFile').val('');
        $('#ctScanHint').val('auto');
        setScanStatus(null);
        setScanVerifyRequired(false);
        clearOcrHighlights();
    }

    /** New guest closed without saving: remove documents uploaded against the throw-away draft id. */
    function cleanupScanDraft() {
        if (scanState.xhr) {
            try { scanState.xhr.abort(); } catch (e) { /* ignore */ }
            scanState.xhr = null;
        }
        if (scanState.saved || !scanState.isNew || !activeQuotationId) {
            scanState.uploads = [];
            return;
        }
        var draftId = scanState.draftId;
        var quotationId = activeQuotationId;
        scanState.uploads.forEach(function (u) {
            if (u.traveller_id !== draftId) {
                return;
            }
            $.ajax({
                url: 'crm/ajax/traveller_documents.php',
                type: 'POST',
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: { action: 'delete', quotation_id: quotationId, traveller_id: draftId, document_type: u.type }
            });
        });
        scanState.uploads = [];
    }

    function applyScanFields(fields, filled) {
        clearOcrHighlights();
        var mark = function (sel) {
            $(sel).addClass('ct-ocr-filled');
        };
        var setVal = function (sel, val) {
            if (val === '' || val === null || typeof val === 'undefined') {
                return false;
            }
            $(sel).val(val);
            mark(sel);
            return true;
        };
        var isFilled = function (key) {
            return (filled || []).indexOf(key) >= 0;
        };
        if (isFilled('name')) {
            setVal('#ctTravellerName', fields.name);
        }
        if (isFilled('passport_number')) {
            setVal('#ctTravellerPassport', fields.passport_number);
        }
        if (isFilled('passport_expiry')) {
            setVal('#ctTravellerPassportExpiry', fields.passport_expiry);
        }
        var detailsFilled = false;
        Object.keys(TRAVELLER_DETAIL_FIELDS).forEach(function (key) {
            if (isFilled(key) && setVal(TRAVELLER_DETAIL_FIELDS[key], fields[key])) {
                detailsFilled = true;
            }
        });
        if (isFilled('dob')) {
            var age = ageFromDob(fields.dob);
            if (age !== null) {
                setVal('#ctTravellerAge', age);
                var type = travellerTypeForAge(age);
                if (type) {
                    setVal('#ctTravellerType', type);
                }
            }
        }
        if (detailsFilled && !$('#ctTravellerMore').hasClass('is-open')) {
            setTravellerMoreOpen(true);
        }
    }

    function scanMatchNotes(res, fields) {
        var notes = [];
        var isPrimary = !!$('#ctTravellerModal').data('isPrimary');
        var editingId = String($('#ctTravellerEditId').val() || '');
        var keys = [docKey(fields.passport_number), docKey(fields.id_number)].filter(function (k) { return k.length >= 5; });
        var nameKey = travellerNameKey(fields.name);

        var inPax = null;
        travellersState.forEach(function (t) {
            if (inPax || String(t.id) === editingId) {
                return;
            }
            var theirs = [docKey(t.passport_number), docKey(t.id_number)];
            var byDoc = keys.some(function (k) { return theirs.indexOf(k) >= 0; });
            if (byDoc || (nameKey && travellerNameKey(t.name) === nameKey)) {
                inPax = t;
            }
        });
        if (inPax) {
            notes.push({
                cls: 'is-warn',
                html: '<i class="fas fa-user-friends mr-1"></i><strong>' + esc(inPax.name) +
                    '</strong> is already in the travellers list. Check that you are not adding the same person twice.'
            });
            return notes;
        }

        var match = res.match;
        if (!match || !match.id || match.id === editingId) {
            return notes;
        }
        if (/^primary_/.test(match.id) && !isPrimary) {
            notes.push({
                cls: 'is-warn',
                html: '<i class="fas fa-user-check mr-1"></i>This document matches the Primary Contact <strong>' +
                    esc(match.name) + '</strong> (by ' + esc(match.by) + ').'
            });
        } else if (/^family_/.test(match.id) && !isPrimary) {
            notes.push({
                cls: 'is-info',
                html: '<i class="fas fa-link mr-1"></i>Matches saved contact <strong>' + esc(match.name) + '</strong>' +
                    (match.relation ? ' (' + esc(match.relation) + ')' : '') + ' by ' + esc(match.by) +
                    '. Saving will update that contact instead of creating a duplicate.'
            });
            if (match.relation && $('#ctTravellerRelation option').filter(function () { return this.value === match.relation; }).length) {
                $('#ctTravellerRelation').val(match.relation);
            }
        }
        return notes;
    }

    function uploadScanFile(file) {
        if (!file) {
            return;
        }
        if (!activeQuotationId) {
            setScanStatus('error', 'Open a quotation before uploading documents.');
            return;
        }
        var ext = String(file.name || '').split('.').pop().toLowerCase();
        if (['jpg', 'jpeg', 'png', 'pdf'].indexOf(ext) < 0) {
            setScanStatus('error', 'Only JPG, PNG or PDF files are allowed.');
            return;
        }
        if (file.size > 4 * 1024 * 1024) {
            setScanStatus('error', 'File size exceeds 4 MB. Please upload a smaller file.');
            return;
        }
        if (scanState.xhr) {
            try { scanState.xhr.abort(); } catch (e) { /* ignore */ }
        }

        var travellerId = currentScanTravellerId();
        var fd = new FormData();
        fd.append('quotation_id', String(activeQuotationId));
        fd.append('traveller_id', travellerId);
        fd.append('hint', String($('#ctScanHint').val() || 'auto'));
        fd.append('file', file);

        var fileLabel = esc(file.name || 'document');
        setScanVerifyRequired(false);
        setScanStatus('uploading', 'Uploading <strong>' + fileLabel + '</strong>… <span class="js-scan-pct">0%</span>' +
            '<div class="ct-scan-progress"><span></span></div>');

        var xhr = new XMLHttpRequest();
        scanState.xhr = xhr;
        xhr.open('POST', 'crm/ajax/scan_traveller_document.php', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.onprogress = function (e) {
            if (!e.lengthComputable) {
                return;
            }
            var pct = Math.round((e.loaded / e.total) * 100);
            $('#ctScanStatus .js-scan-pct').text(pct + '%');
            $('#ctScanStatus .ct-scan-progress > span').css('width', pct + '%');
        };
        xhr.upload.onload = function () {
            if (scanState.xhr === xhr) {
                setScanStatus('processing', 'Reading <strong>' + fileLabel + '</strong> with AI…' +
                    '<div class="ct-scan-meta">This usually takes a few seconds.</div>');
            }
        };
        xhr.onerror = function () {
            if (scanState.xhr === xhr) {
                scanState.xhr = null;
                setScanStatus('error', 'Upload failed. Check your connection and ' +
                    '<button type="button" class="ct-scan-retry js-scan-retry">try again</button>.');
            }
        };
        xhr.onload = function () {
            if (scanState.xhr !== xhr) {
                return;
            }
            scanState.xhr = null;
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
            if (!res || !res.success) {
                setScanStatus('error', esc((res && res.message) || ('Could not process the document (HTTP ' + xhr.status + ').')) +
                    ' <button type="button" class="ct-scan-retry js-scan-retry">Try again</button>');
                return;
            }
            handleScanResponse(res, travellerId);
        };
        xhr.send(fd);
    }

    function handleScanResponse(res, travellerId) {
        if (res.document_type) {
            scanState.uploads.push({ traveller_id: travellerId, type: res.document_type });
        }
        if (Array.isArray(res.summary)) {
            scanState.summary = res.summary;
            scanState.summaryFor = travellerId;
            var idx = findTravellerIndex(travellerId);
            if (idx >= 0) {
                travellersState[idx].documents = res.summary;
                renderTravellers();
            }
        }

        var doc = res.document || {};
        var ocr = res.ocr || {};
        var fields = ocr.fields || {};
        var filled = Array.isArray(ocr.filled) ? ocr.filled : [];
        var stored = '<div class="ct-scan-meta"><i class="fas fa-lock mr-1"></i>' + esc(doc.label || 'Document') +
            ' stored securely' + (doc.url ? ' · <a href="' + esc(doc.url) + '" target="_blank" rel="noopener">View</a>' : '') + '</div>';

        if (!ocr.ok || !filled.length || ocr.is_document === false) {
            setScanStatus(ocr.ok ? 'warning' : 'error', esc(res.message || 'Could not read the document.') + stored +
                ' <button type="button" class="ct-scan-retry js-scan-retry">Upload another file</button>');
            return;
        }

        applyScanFields(fields, filled);
        var notes = scanMatchNotes(res, fields);
        var lowConfidence = ocr.confidence === 'low';
        var html = '<strong>' + esc(ocr.document_label || 'Document') + ' read successfully.</strong> ' +
            filled.length + ' field' + (filled.length === 1 ? '' : 's') + ' auto-filled (highlighted). Please verify before saving.' +
            (lowConfidence ? '<div class="ct-scan-meta"><i class="fas fa-exclamation-circle mr-1"></i>Low read confidence — check every field carefully.</div>' : '') +
            stored +
            notes.map(function (n) { return '<div class="ct-scan-note ' + n.cls + '">' + n.html + '</div>'; }).join('');
        setScanStatus(lowConfidence || notes.some(function (n) { return n.cls === 'is-warn'; }) ? 'warning' : 'success', html);
        setScanVerifyRequired(true);
    }

    function bindTravellerScanEvents() {
        $(document).on('click keydown', '#ctScanDrop', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            e.preventDefault();
            $('#ctScanFile').val('').trigger('click');
        });
        $(document).on('click', '#ctScanStatus .js-scan-retry', function () {
            $('#ctScanFile').val('').trigger('click');
        });
        $(document).on('change', '#ctScanFile', function () {
            var file = this.files && this.files[0];
            uploadScanFile(file);
        });
        $(document).on('dragover dragenter', '#ctScanDrop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('is-drag');
        });
        $(document).on('dragleave dragend', '#ctScanDrop', function (e) {
            e.preventDefault();
            $(this).removeClass('is-drag');
        });
        $(document).on('drop', '#ctScanDrop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('is-drag');
            var dt = e.originalEvent && e.originalEvent.dataTransfer;
            if (dt && dt.files && dt.files[0]) {
                uploadScanFile(dt.files[0]);
            }
        });
        $(document).on('click', '#ctTravellerMoreToggle', function () {
            setTravellerMoreOpen(!$('#ctTravellerMore').hasClass('is-open'));
        });
        $(document).on('input change', '#ctTravellerModal .ct-ocr-filled', function (e) {
            if (e.originalEvent) {
                $(this).removeClass('ct-ocr-filled');
            }
        });
        $(document).on('change', '#ctTravellerDob', function () {
            var age = ageFromDob($(this).val());
            if (age !== null) {
                $('#ctTravellerAge').val(age);
                var type = travellerTypeForAge(age);
                if (type) {
                    $('#ctTravellerType').val(type);
                }
            }
        });
        $(document).on('change', '#ctScanVerify', function () {
            $('#ctScanVerifyWrap').removeClass('is-invalid');
        });
    }

    function renderRows(services) {
        hideSupplierSuggest();
        $('#ctDetailRows').empty();
        (services || []).forEach(function (svc) {
            $('#ctDetailRows').append(rowHtml(svc));
        });
        syncChipStates();
        updatePrimaryStats();
    }

    var highlightServiceTimer = null;

    function clearServiceHighlight() {
        if (highlightServiceTimer) {
            window.clearTimeout(highlightServiceTimer);
            highlightServiceTimer = null;
        }
        $('#ctDetailRows .ct-detail-row').removeClass('ct-row-highlight');
        $('#ctIncludedChips .ct-chip').removeClass('ct-chip-highlight');
    }

    function highlightServiceInModal(serviceKey) {
        clearServiceHighlight();
        serviceKey = String(serviceKey || '').trim();
        if (!serviceKey) {
            return;
        }
        var $rows = $('#ctDetailRows .ct-detail-row[data-key="' + serviceKey + '"]');
        var $chip = $('#ctIncludedChips .ct-chip[data-key="' + serviceKey + '"]');
        if ($rows.length) {
            $rows.addClass('ct-row-highlight');
            try {
                var el = $rows.get(0);
                if (el && typeof el.scrollIntoView === 'function') {
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            } catch (err) {}
        }
        if ($chip.length) {
            $chip.addClass('ct-chip-highlight');
        }
        highlightServiceTimer = window.setTimeout(function () {
            highlightServiceTimer = null;
            $('#ctDetailRows .ct-detail-row').removeClass('ct-row-highlight');
            $('#ctIncludedChips .ct-chip').removeClass('ct-chip-highlight');
        }, 2800);
    }

    function openModal(quotationId, $triggerRow, highlightKey) {
        activeQuotationId = quotationId;
        activePackageTotal = null;
        activeCustomerPaid = null;
        activeTripInfo = null;
        activeRow = $triggerRow;
        $('#ctQuotationId').val(quotationId);
        setPrimaryContact('', '', '');
        setGuestAttachment('', '');
        hidePrimaryEditPanel();
        travellersState = [];
        savedGuestsState = [];
        renderTravellers();
        clearServiceHighlight();
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
                var pkg = parseFloat(q.package_total);
                activePackageTotal = isNaN(pkg) ? 0 : pkg;
                var custPaid = parseFloat(q.customer_paid);
                activeCustomerPaid = isNaN(custPaid) ? 0 : custPaid;
                activeTripInfo = {
                    destination: String(q.destination || ''),
                    tentative_date: String(q.tentative_date || ''),
                    departure_city: String(q.departure_city || '')
                };
                setPrimaryContact(
                    confirm.guest_name || q.guest_name || '',
                    confirm.mobile_no || q.mobile_no || '',
                    confirm.email || q.email || ''
                );
                setGuestAttachment(confirm.guest_attachment_name || '', confirm.guest_attachment_path || '');
                travellersState = (confirm.travellers || []).map(normalizeTraveller).filter(function (t) {
                    return t.name !== '';
                });
                setSavedGuests(res.saved_guests);
                renderTravellers();
                renderRows(confirm.services || []);
                if (highlightKey) {
                    window.setTimeout(function () {
                        highlightServiceInModal(highlightKey);
                    }, 80);
                }
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
        if (res.booking_status_html) {
            activeRow.find('.js-booking-status').html(res.booking_status_html);
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

        var confirm = (res && res.confirm) ? res.confirm : {};
        var lead = (res && res.lead) ? res.lead : {};
        var guest = String(lead.customer_name || confirm.guest_name || $('#ctGuestName').val() || '').trim();
        var mobile = String(lead.customer_phone || confirm.mobile_no || $('#ctMobileNo').val() || '').trim();
        var email = String(lead.customer_email || confirm.email || $('#ctEmail').val() || '').trim();
        var leadId = parseInt(lead.id || res.lead_id || activeRow.attr('data-lead-id') || 0, 10) || 0;

        if (guest) {
            activeRow.find('.js-q-guest-name').text(guest);
        }
        if (mobile) {
            activeRow.find('.js-q-guest-mobile').text(mobile);
        }

        // Leads table row (opened from Status / Book on leads.php)
        var $guestText = activeRow.find('.lead-name-text');
        if ($guestText.length && guest) {
            $guestText.text(guest);
            $guestText.attr('title', 'Phone: ' + (mobile || '—') + '\nEmail: ' + (email || '—'));
            var letters = guest.replace(/[^A-Za-z]/g, '');
            var initials = (letters.charAt(0) + letters.charAt(1)).toUpperCase();
            var $initials = activeRow.find('.lead-guest-initials');
            if (initials) {
                if ($initials.length) {
                    $initials.text(initials);
                } else {
                    activeRow.find('.lead-name').prepend(
                        '<span class="lead-guest-initials" aria-hidden="true">' + esc(initials) + '</span>'
                    );
                }
            }
        }
        activeRow.find('[data-lead-email]').attr('data-lead-email', email);
        activeRow.find('[data-lead-phone]').attr('data-lead-phone', mobile);

        var guestPayload = {
            lead_id: leadId,
            customer_name: guest,
            customer_phone: mobile,
            customer_email: email,
            ts: Date.now()
        };
        try {
            $(document).trigger('crm:lead-guest-updated', [guestPayload]);
        } catch (err) {}
        try {
            window.localStorage.setItem('crm_lead_guest_updated', JSON.stringify(guestPayload));
        } catch (err) {}
        try {
            if (window.BroadcastChannel) {
                var bc = new BroadcastChannel('crm_lead_guest');
                bc.postMessage(guestPayload);
                bc.close();
            }
        } catch (err) {}
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

        $(document).on('click', '.js-booking-status-open', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $icon = $(this);
            var $cell = $icon.closest('.js-booking-status');
            var id = parseInt($cell.attr('data-id') || $cell.data('id') || 0, 10) || 0;
            if (!id) {
                id = parseInt($icon.closest('tr').find('.js-q-book').data('id') || 0, 10) || 0;
            }
            if (!id) {
                return;
            }
            var highlightKey = String($icon.attr('data-key') || $icon.data('key') || '').trim();
            openModal(id, $icon.closest('tr'), highlightKey);
        });

        $(document).on('keydown', '.js-booking-status-open', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            e.preventDefault();
            $(this).trigger('click');
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
            updatePrimaryStats();
        });

        $(document).on('input', '.ct-total, .ct-paid', function () {
            recalcRowBalance($(this).closest('.ct-detail-row'));
        });

        $(document).on('input change', '#ctDetailRows .ct-total, #ctDetailRows .ct-paid, #ctDetailRows .ct-supplier', function () {
            updatePrimaryStats();
        });

        // Amounts show as 9,000.00; while editing they are plain numbers.
        $(document).on('focus', '#ctDetailRows .ct-total, #ctDetailRows .ct-paid', function () {
            var v = String($(this).val() || '').replace(/,/g, '');
            if (v !== '') {
                var n = parseFloat(v);
                $(this).val(isNaN(n) ? '' : String(Math.round(n * 100) / 100));
            }
            var el = this;
            window.setTimeout(function () {
                try { el.select(); } catch (err) {}
            }, 0);
        });

        $(document).on('input', '#ctDetailRows .ct-total, #ctDetailRows .ct-paid', function () {
            var v = String($(this).val() || '');
            var clean = v.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
            if (clean !== v) {
                $(this).val(clean);
                recalcRowBalance($(this).closest('.ct-detail-row'));
            }
        });

        $(document).on('blur', '#ctDetailRows .ct-total, #ctDetailRows .ct-paid', function () {
            $(this).val(amountInputValue($.trim(String($(this).val() || ''))));
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
            clearServiceHighlight();
            hidePrimaryEditPanel();
        });

        $(document).on('click', '.ct-remove-row', function () {
            var $row = $(this).closest('.ct-detail-row');
            var removeRow = function () {
                $row.remove();
                syncChipStates();
                hideSupplierSuggest();
                updatePrimaryStats();
            };
            var vouchers = parseInt($row.attr('data-vouchers'), 10) || 0;
            var payments = parseInt($row.attr('data-payments'), 10) || 0;
            if (!vouchers && !payments) {
                removeRow();
                return;
            }
            var label = $.trim($row.find('.ct-detail-label').text()) || 'this service';
            var parts = [];
            if (vouchers) {
                parts.push('<strong>' + vouchers + ' voucher file' + (vouchers === 1 ? '' : 's') + '</strong>');
            }
            if (payments) {
                parts.push('<strong>' + payments + ' payment record' + (payments === 1 ? '' : 's') + '</strong>');
            }
            showConfirmDialog({
                variant: 'danger',
                icon: 'fa-trash-alt',
                title: 'Remove ' + label + '?',
                message: 'This service has ' + parts.join(' and ') + '. ' +
                    'They will be permanently deleted when you click Save.',
                confirmText: 'Remove Service',
                onConfirm: removeRow
            });
        });

        $(document).on('click', '.ct-reminders', function () {
            alert('Reminders will be available in a future update.');
        });

        $('#ctGuestEditBtn').on('click', function () {
            if ($('#ctPrimaryEditPanel').hasClass('d-none')) {
                showPrimaryEditPanel();
            } else {
                hidePrimaryEditPanel();
            }
        });

        $('#ctPrimaryEditCancel').on('click', function () {
            hidePrimaryEditPanel();
        });

        $('#ctPrimaryEditApply').on('click', function () {
            var name = $.trim($('#ctGuestNameEdit').val());
            if (!name) {
                alert('Guest name is required.');
                $('#ctGuestNameEdit').trigger('focus');
                return;
            }
            setPrimaryContact(name, $('#ctMobileNoEdit').val(), $('#ctEmailEdit').val());
            hidePrimaryEditPanel();
            // Keep first traveller name aligned with primary when it was the primary slot.
            if (travellersState.length && String(travellersState[0].id).indexOf('primary_') === 0) {
                travellersState[0].name = name;
                renderTravellers();
            }
        });

        $('#ctGuestClearBtn').on('click', function () {
            if (!window.confirm('Clear primary contact name, mobile, email and attachment?')) {
                return;
            }
            setPrimaryContact('', '', '');
            setGuestAttachment('', '');
            hidePrimaryEditPanel();
            showPrimaryEditPanel();
        });

        $('#ctGuestAttachBtn').on('click', function (e) {
            var path = String($('#ctGuestAttachmentPath').val() || '').trim();
            if (path && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                window.open(path, '_blank');
                return;
            }
            $('#ctGuestAttachFile').trigger('click');
        });

        $('#ctGuestAttachFile').on('change', function () {
            var input = this;
            var file = input.files && input.files[0] ? input.files[0] : null;
            if (!file) {
                return;
            }
            if (!activeQuotationId) {
                alert('Open a quotation before attaching files.');
                input.value = '';
                return;
            }
            var $btn = $('#ctGuestAttachBtn').prop('disabled', true).attr('title', 'Uploading…');
            var formData = new FormData();
            formData.append('quotation_id', String(activeQuotationId));
            formData.append('attachment', file);
            $.ajax({
                url: 'crm/ajax/upload_quotation_confirm_attachment.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .done(function (res) {
                    if (!res || !res.success || !res.attachment) {
                        alert((res && res.message) || 'Could not upload attachment.');
                        return;
                    }
                    setGuestAttachment(
                        res.attachment.original_name || file.name || '',
                        res.attachment.file_path || ''
                    );
                })
                .fail(function () {
                    alert('Could not upload attachment. Please try again.');
                })
                .always(function () {
                    $btn.prop('disabled', false);
                    input.value = '';
                });
        });

        var persistSeq = 0;

        function applySavedConfirm(res) {
            var confirm = res.confirm || {};
            if (Array.isArray(confirm.travellers)) {
                travellersState = confirm.travellers.map(normalizeTraveller).filter(function (t) {
                    return t.name !== '';
                });
            }
            if (Array.isArray(res.saved_guests)) {
                setSavedGuests(res.saved_guests);
            }
            if (confirm.guest_name) {
                setPrimaryContact(confirm.guest_name, confirm.mobile_no || '', confirm.email || '');
            }
            renderTravellers();
            if (paxDocsIndex >= 0) {
                renderPaxDocsList();
            }
        }

        function persistTravellers() {
            if (!activeQuotationId) {
                return;
            }
            var guest = $.trim($('#ctGuestName').val());
            if (!guest && travellersState[0] && travellersState[0].name) {
                guest = travellersState[0].name;
                setPrimaryContact(guest, $('#ctMobileNo').val(), $('#ctEmail').val());
            }
            if (!guest) {
                return;
            }
            var seq = ++persistSeq;
            var quotationId = activeQuotationId;
            $.ajax({
                url: 'crm/ajax/save_quotation_confirm.php',
                type: 'POST',
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: {
                    id: quotationId,
                    mode: 'travellers',
                    guest_name: guest,
                    mobile_no: $('#ctMobileNo').val(),
                    email: $('#ctEmail').val(),
                    guest_attachment_name: $('#ctGuestAttachmentName').val(),
                    guest_attachment_path: $('#ctGuestAttachmentPath').val(),
                    travellers_json: JSON.stringify(travellersState)
                }
            })
                .done(function (res) {
                    if (!res || !res.success) {
                        alert((res && res.message) || 'Could not save traveller.');
                        return;
                    }
                    updateListRow(res);
                    // Skip stale responses so a newer in-flight edit isn't overwritten.
                    if (seq !== persistSeq || quotationId !== activeQuotationId) {
                        return;
                    }
                    applySavedConfirm(res);
                })
                .fail(function () {
                    alert('Could not save traveller. Please try again.');
                });
        }

        $('#ctTravellerModal').on('hidden.bs.modal', function () {
            cleanupScanDraft();
            if ($('#confirmTourModal').hasClass('show')) {
                $('body').addClass('modal-open');
            }
        });

        bindTravellerScanEvents();

        $('#ctAddGuestBtn').on('click', function () {
            openTravellerModal(null);
        });

        $('#ctTravellerSaved').on('change', function () {
            var id = String($(this).val() || '');
            var guest = null;
            for (var i = 0; i < savedGuestsState.length; i++) {
                if (String(savedGuestsState[i].id) === id) {
                    guest = savedGuestsState[i];
                    break;
                }
            }
            // Picking a saved contact replaces the form, so any scan done for the draft is discarded.
            cleanupScanDraft();
            fillTravellerForm(guest, false);
            setScanStatus(null);
            setScanVerifyRequired(false);
        });

        $(document).on('click', '.js-ct-pax-edit', function () {
            var id = $(this).closest('tr').attr('data-traveller-id');
            var idx = findTravellerIndex(id);
            if (idx < 0) {
                return;
            }
            openTravellerModal(travellersState[idx]);
        });

        $(document).on('click', '.js-ct-pax-delete', function () {
            var id = $(this).closest('tr').attr('data-traveller-id');
            if (findTravellerIndex(id) === 0) {
                alert('The Primary Contact cannot be removed from the tour.');
                return;
            }
            if (!window.confirm('Remove this traveller from this tour? They stay saved under the Primary Contact for future tours.')) {
                return;
            }
            travellersState = travellersState.filter(function (t) {
                return String(t.id) !== String(id);
            });
            renderTravellers();
            persistTravellers();
        });

        // ---- Traveller Attachments: 6 fixed document slots ----
        var DOC_TYPES = [
            { key: 'profile_photo', label: 'Profile Photo', icon: 'fa-user-circle', pdf: false },
            { key: 'aadhaar', label: 'Aadhaar Card', icon: 'fa-id-card', pdf: true },
            { key: 'pan', label: 'PAN Card', icon: 'fa-address-card', pdf: true },
            { key: 'passport', label: 'Passport', icon: 'fa-passport', pdf: true },
            { key: 'visa', label: 'Visa', icon: 'fa-stamp', pdf: true },
            { key: 'other', label: 'Other Document', icon: 'fa-file-alt', pdf: true }
        ];
        var DOC_MAX_BYTES = 4 * 1024 * 1024;
        var DOC_SIZE_ERROR = 'File size exceeds 4 MB. Please upload a smaller file.';
        // Row index is tracked because the traveller id can change (t_xxx → family_N) after the server links the guest.
        var paxDocsIndex = -1;
        var paxDocsTravellerId = '';
        var docState = {};
        var docsCache = {};

        function docTypeInfo(key) {
            for (var i = 0; i < DOC_TYPES.length; i++) {
                if (DOC_TYPES[i].key === key) {
                    return DOC_TYPES[i];
                }
            }
            return null;
        }

        function ensureDocStyles() {
            if (document.getElementById('ctDocStyles')) {
                return;
            }
            var css = '' +
                '#ctPaxDocsModal{z-index:1080}' +
                '#ctPaxDocsModal .modal-body{background:#f8fafc}' +
                '#ctDocCards.is-loading{opacity:.55;pointer-events:none}' +
                '.ct-doc-card{position:relative;height:100%;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 1px 3px rgba(15,23,42,.06),0 4px 12px rgba(15,23,42,.04);padding:.85rem .9rem;transition:border-color .15s,box-shadow .15s}' +
                '.ct-doc-card:hover{box-shadow:0 2px 6px rgba(15,23,42,.08),0 8px 20px rgba(15,23,42,.06)}' +
                '.ct-doc-card.is-uploaded{border-color:#bbf7d0}' +
                '.ct-doc-card.is-failed{border-color:#fecaca}' +
                '.ct-doc-card.is-uploading{border-color:#bfdbfe}' +
                '.ct-doc-main{display:flex;align-items:center;gap:.75rem}' +
                '.ct-doc-icon{flex:0 0 46px;width:46px;height:46px;border-radius:10px;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:1.25rem;overflow:hidden}' +
                '.ct-doc-card.is-uploaded .ct-doc-icon{background:#ecfdf5;color:#059669}' +
                '.ct-doc-card.is-failed .ct-doc-icon{background:#fef2f2;color:#dc2626}' +
                '.ct-doc-icon img{width:100%;height:100%;object-fit:cover}' +
                '.ct-doc-body{flex:1 1 auto;min-width:0}' +
                '.ct-doc-title{font-weight:700;color:#0f172a;font-size:.92rem;line-height:1.2}' +
                '.ct-doc-status{display:inline-flex;align-items:center;gap:.3rem;margin-top:.25rem;font-size:.68rem;font-weight:600;padding:.2rem .5rem;border-radius:999px}' +
                '.ct-doc-file{margin-top:.25rem;font-size:.76rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
                '.ct-doc-file strong{color:#334155;font-weight:600}' +
                '.ct-doc-actions{display:flex;gap:.3rem;flex-shrink:0}' +
                '.ct-doc-actions .btn-icon{width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}' +
                '.ct-doc-progress{margin-top:.7rem}' +
                '.ct-doc-progress-label{display:flex;justify-content:space-between;font-size:.72rem;color:#475569;margin-bottom:.25rem}' +
                '.ct-doc-progress .progress{height:.5rem;border-radius:999px;background:#e2e8f0}' +
                '.ct-doc-error{margin-top:.5rem;font-size:.74rem;color:#dc2626}' +
                '#ctToastWrap{position:fixed;top:1rem;right:1rem;z-index:2000;display:flex;flex-direction:column;gap:.5rem;max-width:340px}' +
                '.ct-toast{padding:.6rem .85rem;border-radius:10px;color:#fff;font-size:.85rem;box-shadow:0 6px 18px rgba(15,23,42,.18);opacity:0;transform:translateY(-6px);transition:opacity .2s,transform .2s}' +
                '.ct-toast.show{opacity:1;transform:none}' +
                '.ct-toast.is-success{background:#16a34a}.ct-toast.is-error{background:#dc2626}.ct-toast.is-info{background:#2563eb}' +
                '@media (max-width:575.98px){.ct-doc-main{flex-wrap:wrap}.ct-doc-actions{width:100%;justify-content:flex-end}}';
            $('<style id="ctDocStyles">').text(css).appendTo('head');
        }

        function showToast(message, type) {
            if (!$('#ctToastWrap').length) {
                $('body').append('<div id="ctToastWrap" aria-live="polite"></div>');
            }
            var $t = $('<div class="ct-toast is-' + (type || 'info') + '">').text(message).appendTo('#ctToastWrap');
            window.setTimeout(function () { $t.addClass('show'); }, 10);
            window.setTimeout(function () {
                $t.removeClass('show');
                window.setTimeout(function () { $t.remove(); }, 250);
            }, 3200);
        }

        function docsCacheKey(travellerId) {
            return activeQuotationId + '|' + travellerId;
        }

        function resetDocState(docs) {
            docState = {};
            DOC_TYPES.forEach(function (t) {
                docState[t.key] = { status: 'none', doc: null, pct: 0, error: '', lastFile: null, justCompleted: false };
            });
            (docs || []).forEach(function (d) {
                if (docState[d.document_type]) {
                    docState[d.document_type].status = 'uploaded';
                    docState[d.document_type].doc = d;
                }
            });
        }

        function docAccept(info) {
            return info.pdf ? '.jpg,.jpeg,.png,.webp,.pdf' : '.jpg,.jpeg,.png,.webp';
        }

        function docStatusBadge(st) {
            switch (st.status) {
                case 'uploading':
                    return '<span class="ct-doc-status badge-primary"><i class="fas fa-spinner fa-spin"></i> Uploading</span>';
                case 'uploaded':
                    return '<span class="ct-doc-status badge-success"><i class="fas fa-check-circle"></i> Uploaded</span>';
                case 'failed':
                    return '<span class="ct-doc-status badge-danger"><i class="fas fa-exclamation-circle"></i> Failed</span>';
            }
            return '<span class="ct-doc-status badge-secondary">Not Uploaded</span>';
        }

        function docCardHtml(info) {
            var st = docState[info.key];
            var doc = st.doc;
            var iconHtml = (doc && doc.thumb_url)
                ? '<img src="' + esc(doc.thumb_url) + '" alt="" loading="lazy" decoding="async">'
                : '<i class="fas ' + info.icon + '"></i>';
            var fileHtml;
            if (doc && st.status !== 'uploading') {
                fileHtml = '<strong>' + esc(doc.file_name) + '</strong> · ' + esc(String(doc.file_size_kb)) + ' KB';
            } else {
                fileHtml = info.pdf ? 'JPG, PNG, WEBP or PDF · max 4 MB' : 'JPG, PNG or WEBP · max 4 MB';
            }

            var actions = '';
            if (st.status === 'uploading') {
                actions = '<button type="button" class="btn btn-light btn-icon" disabled><i class="fas fa-spinner fa-spin"></i></button>';
            } else if (doc) {
                actions =
                    '<button type="button" class="btn btn-outline-primary btn-icon js-doc-view" title="View"><i class="fas fa-eye"></i></button>' +
                    '<a class="btn btn-outline-success btn-icon" href="' + esc(doc.url + '&download=1') + '" download title="Download"><i class="fas fa-download"></i></a>' +
                    '<button type="button" class="btn btn-outline-secondary btn-icon js-doc-replace" title="Replace"><i class="fas fa-sync-alt"></i></button>' +
                    '<button type="button" class="btn btn-outline-danger btn-icon js-doc-delete" title="Delete"><i class="fas fa-trash-alt"></i></button>';
            } else if (st.status === 'failed') {
                actions =
                    (st.lastFile ? '<button type="button" class="btn btn-danger btn-sm js-doc-retry"><i class="fas fa-redo mr-1"></i>Retry</button>' : '') +
                    '<button type="button" class="btn btn-outline-primary btn-sm js-doc-pick"><i class="fas fa-upload mr-1"></i>Upload</button>';
            } else {
                actions = '<button type="button" class="btn btn-primary btn-sm js-doc-pick"><i class="fas fa-upload mr-1"></i>Upload</button>';
            }

            var showProgress = st.status === 'uploading' || st.justCompleted;
            var pct = st.justCompleted ? 100 : st.pct;
            var barClass = st.justCompleted ? 'bg-success' : 'progress-bar-striped progress-bar-animated';
            var progressText = st.justCompleted
                ? 'Upload Completed'
                : (pct >= 100 ? 'Compressing ' + info.label + '…' : 'Uploading ' + info.label + '...');
            var pctText = st.justCompleted ? '100%' : (pct + '% Uploaded');

            return '<div class="col-md-6 mb-3">' +
                '<div class="ct-doc-card is-' + st.status + '" data-type="' + info.key + '">' +
                '<div class="ct-doc-main">' +
                '<div class="ct-doc-icon">' + iconHtml + '</div>' +
                '<div class="ct-doc-body">' +
                '<div class="ct-doc-title">' + esc(info.label) + '</div>' +
                docStatusBadge(st) +
                '<div class="ct-doc-file" title="' + esc(doc ? doc.file_name : '') + '">' + fileHtml + '</div>' +
                '</div>' +
                '<div class="ct-doc-actions">' + actions + '</div>' +
                '</div>' +
                '<div class="ct-doc-progress' + (showProgress ? '' : ' d-none') + '">' +
                '<div class="ct-doc-progress-label"><span class="js-doc-progress-text">' + esc(progressText) + '</span><span class="js-doc-pct">' + esc(pctText) + '</span></div>' +
                '<div class="progress"><div class="progress-bar ' + barClass + '" role="progressbar" style="width:' + pct + '%" aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100"></div></div>' +
                '</div>' +
                (st.status === 'failed' && st.error ? '<div class="ct-doc-error"><i class="fas fa-info-circle mr-1"></i>' + esc(st.error) + '</div>' : '') +
                '<input type="file" class="d-none js-doc-file" accept="' + docAccept(info) + '">' +
                '</div></div>';
        }

        function renderDocCard(key) {
            var info = docTypeInfo(key);
            var $old = $('#ctDocCards .ct-doc-card[data-type="' + key + '"]').parent();
            if (info && $old.length) {
                $old.replaceWith(docCardHtml(info));
            }
        }

        function renderPaxDocsList() {
            // Keep the popup bound to the row after the server re-links ids.
            if (paxDocsIndex >= 0 && travellersState[paxDocsIndex]) {
                paxDocsTravellerId = String(travellersState[paxDocsIndex].id);
            }
            $('#ctDocCards').html(DOC_TYPES.map(docCardHtml).join(''));
        }

        function updateDocProgress(key, pct) {
            var st = docState[key];
            var info = docTypeInfo(key);
            st.pct = pct;
            var $card = $('#ctDocCards .ct-doc-card[data-type="' + key + '"]');
            $card.find('.progress-bar').css('width', pct + '%').attr('aria-valuenow', pct);
            $card.find('.js-doc-pct').text(pct + '% Uploaded');
            $card.find('.js-doc-progress-text').text(pct >= 100 ? 'Compressing ' + info.label + '…' : 'Uploading ' + info.label + '...');
        }

        function applyDocsSummary(summary) {
            var t = travellersState[paxDocsIndex];
            if (t && Array.isArray(summary)) {
                t.documents = summary;
                renderTravellers();
            }
        }

        function loadTravellerDocs(travellerId) {
            var cacheKey = docsCacheKey(travellerId);
            if (docsCache[cacheKey]) {
                resetDocState(docsCache[cacheKey]);
                renderPaxDocsList();
            } else {
                resetDocState([]);
                renderPaxDocsList();
                $('#ctDocCards').addClass('is-loading');
            }
            $.getJSON('crm/ajax/traveller_documents.php', {
                action: 'list',
                quotation_id: activeQuotationId,
                traveller_id: travellerId
            })
                .done(function (res) {
                    if (travellerId !== paxDocsTravellerId) {
                        return;
                    }
                    if (!res || !res.success) {
                        showToast((res && res.message) || 'Could not load documents.', 'error');
                        return;
                    }
                    docsCache[cacheKey] = res.documents || [];
                    resetDocState(docsCache[cacheKey]);
                    renderPaxDocsList();
                    applyDocsSummary(res.summary);
                })
                .fail(function () {
                    showToast('Could not load documents. Please try again.', 'error');
                })
                .always(function () {
                    $('#ctDocCards').removeClass('is-loading');
                });
        }

        function openPaxDocsModal(travellerId) {
            var idx = findTravellerIndex(travellerId);
            if (idx < 0 || !activeQuotationId) {
                return;
            }
            ensureDocStyles();
            paxDocsIndex = idx;
            paxDocsTravellerId = String(travellersState[idx].id);
            var t = travellersState[idx];
            $('#ctPaxDocsMeta').text('Traveller ' + (idx + 1) + ' · ' + t.name + (idx === 0 ? ' (Primary Contact)' : ''));
            loadTravellerDocs(paxDocsTravellerId);
            $('#ctPaxDocsModal').modal('show');
        }

        function validateDocFile(info, file) {
            if (file.size > DOC_MAX_BYTES) {
                return DOC_SIZE_ERROR;
            }
            var ext = String(file.name.split('.').pop() || '').toLowerCase();
            var allowed = info.pdf ? ['jpg', 'jpeg', 'png', 'webp', 'pdf'] : ['jpg', 'jpeg', 'png', 'webp'];
            if (allowed.indexOf(ext) === -1) {
                return info.pdf ? 'Only JPG, JPEG, PNG, WEBP or PDF files are allowed.' : 'Only JPG, JPEG, PNG or WEBP images are allowed.';
            }
            return '';
        }

        function uploadTravellerDoc(key, file) {
            var info = docTypeInfo(key);
            var st = docState[key];
            if (!info || !st || st.status === 'uploading') {
                return;
            }
            var error = validateDocFile(info, file);
            if (error) {
                st.status = st.doc ? 'uploaded' : 'failed';
                st.error = error;
                st.lastFile = null;
                renderDocCard(key);
                showToast(error, 'error');
                return;
            }
            var travellerId = paxDocsTravellerId;
            var quotationId = activeQuotationId;
            st.status = 'uploading';
            st.pct = 0;
            st.error = '';
            st.lastFile = file;
            st.justCompleted = false;
            renderDocCard(key);

            var fd = new FormData();
            fd.append('action', 'upload');
            fd.append('quotation_id', String(quotationId));
            fd.append('traveller_id', travellerId);
            fd.append('document_type', key);
            fd.append('file', file);

            $.ajax({
                url: 'crm/ajax/traveller_documents.php',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                xhr: function () {
                    var xhr = $.ajaxSettings.xhr();
                    if (xhr.upload) {
                        xhr.upload.addEventListener('progress', function (e) {
                            if (e.lengthComputable && travellerId === paxDocsTravellerId) {
                                updateDocProgress(key, Math.min(100, Math.round((e.loaded / e.total) * 100)));
                            }
                        });
                    }
                    return xhr;
                }
            })
                .done(function (res) {
                    if (travellerId !== paxDocsTravellerId) {
                        return;
                    }
                    if (!res || !res.success || !res.document) {
                        st.status = 'failed';
                        st.error = (res && res.message) || 'Upload failed.';
                        renderDocCard(key);
                        showToast(st.error, 'error');
                        return;
                    }
                    docsCache[docsCacheKey(travellerId)] = res.documents || [];
                    st.status = 'uploaded';
                    st.doc = res.document;
                    st.lastFile = null;
                    st.justCompleted = true;
                    renderDocCard(key);
                    applyDocsSummary(res.summary);
                    var msg = info.label + ' uploaded (' + res.document.file_size_kb + ' KB).';
                    if (res.pdf_compressed === false) {
                        msg = info.label + ' uploaded (' + res.document.file_size_kb + ' KB, PDF stored without compression).';
                    }
                    showToast(msg, 'success');
                    window.setTimeout(function () {
                        if (docState[key] === st && st.justCompleted) {
                            st.justCompleted = false;
                            renderDocCard(key);
                        }
                    }, 2500);
                })
                .fail(function (xhr) {
                    if (travellerId !== paxDocsTravellerId) {
                        return;
                    }
                    var msg = 'Upload failed. Please try again.';
                    if (xhr && xhr.status === 413) {
                        msg = DOC_SIZE_ERROR;
                    } else if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    st.status = 'failed';
                    st.error = msg;
                    renderDocCard(key);
                    showToast(msg, 'error');
                });
        }

        $(document).on('click', '.js-ct-pax-docs, .js-ct-pax-attach', function () {
            openPaxDocsModal($(this).closest('tr').attr('data-traveller-id'));
        });

        $('#ctPaxDocsModal').on('shown.bs.modal', function () {
            $('.modal-backdrop').last().css('z-index', 1075);
        });

        $('#ctPaxDocsModal').on('hidden.bs.modal', function () {
            paxDocsIndex = -1;
            paxDocsTravellerId = '';
            if ($('#confirmTourModal').hasClass('show')) {
                $('body').addClass('modal-open');
            }
        });

        $(document).on('click', '#ctDocCards .js-doc-pick', function () {
            $(this).closest('.ct-doc-card').find('.js-doc-file').val('').trigger('click');
        });

        $(document).on('change', '#ctDocCards .js-doc-file', function () {
            var file = this.files && this.files[0] ? this.files[0] : null;
            if (file) {
                uploadTravellerDoc(String($(this).closest('.ct-doc-card').attr('data-type')), file);
            }
        });

        $(document).on('click', '#ctDocCards .js-doc-retry', function () {
            var key = String($(this).closest('.ct-doc-card').attr('data-type'));
            if (docState[key] && docState[key].lastFile) {
                uploadTravellerDoc(key, docState[key].lastFile);
            }
        });

        $(document).on('click', '#ctDocCards .js-doc-delete', function () {
            var key = String($(this).closest('.ct-doc-card').attr('data-type'));
            var info = docTypeInfo(key);
            var st = docState[key];
            if (!info || !st || !st.doc) {
                return;
            }
            showConfirmDialog({
                variant: 'danger',
                icon: 'fa-trash-alt',
                title: 'Delete ' + info.label + '?',
                message: '<strong>' + esc(st.doc.file_name) + '</strong> will be permanently removed for this traveller. This cannot be undone.',
                confirmText: 'Yes, Delete',
                onConfirm: function () {
                    deleteTravellerDoc(key);
                }
            });
        });

        $(document).on('click', '#ctDocCards .js-doc-replace', function () {
            var $input = $(this).closest('.ct-doc-card').find('.js-doc-file');
            var key = String($(this).closest('.ct-doc-card').attr('data-type'));
            var info = docTypeInfo(key);
            var st = docState[key];
            if (!info || !st || !st.doc) {
                return;
            }
            showConfirmDialog({
                variant: 'warning',
                icon: 'fa-sync-alt',
                title: 'Replace ' + info.label + '?',
                message: 'The current file <strong>' + esc(st.doc.file_name) + '</strong> will be replaced by the file you choose next.',
                confirmText: 'Choose New File',
                onConfirm: function () {
                    // Called inside the confirm button's click so the browser allows opening the file picker.
                    $input.val('').trigger('click');
                }
            });
        });

        function deleteTravellerDoc(key) {
            var info = docTypeInfo(key);
            var st = docState[key];
            if (!info || !st || !st.doc) {
                return;
            }
            var travellerId = paxDocsTravellerId;
            var $btn = $('#ctDocCards .ct-doc-card[data-type="' + key + '"] .js-doc-delete').prop('disabled', true);
            $.ajax({
                url: 'crm/ajax/traveller_documents.php',
                type: 'POST',
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: {
                    action: 'delete',
                    quotation_id: activeQuotationId,
                    traveller_id: travellerId,
                    document_type: key
                }
            })
                .done(function (res) {
                    if (!res || !res.success) {
                        showToast((res && res.message) || 'Could not delete document.', 'error');
                        $btn.prop('disabled', false);
                        return;
                    }
                    docsCache[docsCacheKey(travellerId)] = res.documents || [];
                    if (travellerId !== paxDocsTravellerId) {
                        return;
                    }
                    st.status = 'none';
                    st.doc = null;
                    st.error = '';
                    renderDocCard(key);
                    applyDocsSummary(res.summary);
                    showToast(info.label + ' deleted.', 'success');
                })
                .fail(function () {
                    showToast('Could not delete document. Please try again.', 'error');
                    $btn.prop('disabled', false);
                });
        }

        /**
         * Styled Bootstrap confirmation (replaces window.confirm). Stacks above the attachments modal.
         * opts: { title, message (HTML), confirmText, cancelText, variant: danger|warning|primary, icon, onConfirm }
         */
        function showConfirmDialog(opts) {
            opts = opts || {};
            if (!$('#ctConfirmModal').length) {
                $('<style id="ctConfirmStyles">').text(
                    '#ctConfirmModal{z-index:1100}' +
                    '#ctConfirmModal .modal-dialog{max-width:420px}' +
                    '#ctConfirmModal .modal-content{border:0;border-radius:14px;box-shadow:0 20px 50px rgba(15,23,42,.25)}' +
                    '#ctConfirmModal .modal-body{padding:1.6rem 1.5rem 1.1rem;text-align:center}' +
                    '#ctConfirmModal .ct-cf-icon{width:60px;height:60px;border-radius:50%;margin:0 auto .9rem;display:flex;align-items:center;justify-content:center;font-size:1.5rem}' +
                    '#ctConfirmModal .ct-cf-icon.is-danger{background:#fef2f2;color:#dc2626}' +
                    '#ctConfirmModal .ct-cf-icon.is-warning{background:#fffbeb;color:#d97706}' +
                    '#ctConfirmModal .ct-cf-icon.is-primary{background:#eff6ff;color:#2563eb}' +
                    '#ctConfirmModal .ct-cf-title{font-size:1.1rem;font-weight:700;color:#0f172a;margin-bottom:.4rem}' +
                    '#ctConfirmModal .ct-cf-msg{font-size:.9rem;color:#475569;word-break:break-word}' +
                    '#ctConfirmModal .modal-footer{border-top:0;justify-content:center;gap:.5rem;padding:0 1.5rem 1.4rem}' +
                    '#ctConfirmModal .modal-footer .btn{min-width:120px;border-radius:8px}'
                ).appendTo('head');
                $('body').append(
                    '<div class="modal fade" id="ctConfirmModal" tabindex="-1" role="dialog" aria-labelledby="ctConfirmTitle" aria-hidden="true">' +
                    '<div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">' +
                    '<div class="modal-body"><div class="ct-cf-icon"><i class="fas"></i></div>' +
                    '<div class="ct-cf-title" id="ctConfirmTitle"></div><div class="ct-cf-msg"></div></div>' +
                    '<div class="modal-footer">' +
                    '<button type="button" class="btn btn-light js-cf-cancel" data-dismiss="modal"></button>' +
                    '<button type="button" class="btn js-cf-ok"></button>' +
                    '</div></div></div></div>'
                );
                $('#ctConfirmModal')
                    .on('shown.bs.modal', function () {
                        $('.modal-backdrop').last().css('z-index', 1095);
                        $(this).find('.js-cf-ok').trigger('focus');
                    })
                    .on('hidden.bs.modal', function () {
                        $(this).data('onConfirm', null);
                        if ($('.modal.show').length) {
                            $('body').addClass('modal-open');
                        }
                    })
                    .on('click', '.js-cf-ok', function () {
                        var cb = $('#ctConfirmModal').data('onConfirm');
                        $('#ctConfirmModal').data('onConfirm', null);
                        if (typeof cb === 'function') {
                            cb();
                        }
                        $('#ctConfirmModal').modal('hide');
                    });
            }
            var variant = opts.variant || 'primary';
            var $m = $('#ctConfirmModal');
            $m.find('.ct-cf-icon').attr('class', 'ct-cf-icon is-' + variant)
                .find('i').attr('class', 'fas ' + (opts.icon || 'fa-question'));
            $m.find('.ct-cf-title').text(opts.title || 'Are you sure?');
            $m.find('.ct-cf-msg').html(opts.message || '');
            $m.find('.js-cf-cancel').text(opts.cancelText || 'Cancel');
            $m.find('.js-cf-ok').attr('class', 'btn btn-' + variant + ' js-cf-ok').text(opts.confirmText || 'Confirm');
            $m.data('onConfirm', opts.onConfirm || null);
            $m.modal('show');
        }

        // ---- Document preview (images: zoom + drag/pan, PDFs: PDF.js) ----
        var PDFJS_SRC = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
        var PDFJS_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        var ZOOM_MIN = 0.25;
        var ZOOM_MAX = 4;
        var pdfJsPromise = null;
        var previewBlobCache = {};
        var preview = {
            seq: 0, kind: '', doc: null, zoom: 1,
            img: null, natW: 0, natH: 0, panX: 0, panY: 0,
            pdf: null, pdfPages: [], renderTimer: null, renderSeq: 0,
            xhr: null, modalShown: false, pendingLayout: null
        };

        /** Run layout work (fit/centre) only once the modal is fully open and the viewer has real dimensions. */
        function whenViewerReady(fn) {
            var $body = $('#ctDocPreviewModal .modal-body');
            if (preview.modalShown && $body.width() > 0 && $body.height() > 0) {
                preview.pendingLayout = null;
                fn();
            } else {
                preview.pendingLayout = fn;
            }
        }

        function ensurePreviewModal() {
            if ($('#ctDocPreviewModal').length) {
                return;
            }
            var css = '' +
                '#ctDocPreviewModal{z-index:1090}' +
                '#ctDocPreviewModal .modal-dialog{max-width:min(1100px,96vw)}' +
                '#ctDocPreviewModal .modal-content{height:min(88vh,900px);border-radius:12px;overflow:hidden}' +
                '#ctDocPreviewModal .modal-header{align-items:center;gap:.5rem}' +
                '#ctDocPreviewModal .ct-pv-title{min-width:0}' +
                '#ctDocPreviewModal .ct-pv-title h5{font-size:1rem;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
                '#ctDocPreviewModal .ct-pv-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem;padding:.5rem .75rem;border-bottom:1px solid #e2e8f0;background:#fff}' +
                '#ctDocPreviewModal .ct-pv-toolbar .btn{min-width:34px}' +
                '#ctDocPreviewModal .ct-pv-zoom{min-width:58px;text-align:center;font-weight:700;font-size:.85rem;color:#0f172a}' +
                '#ctDocPreviewModal .ct-pv-spacer{flex:1 1 auto}' +
                '#ctDocPreviewModal .modal-body{position:relative;padding:0;background:#1e293b;overflow:hidden;flex:1 1 auto}' +
                '#ctDocPreviewModal .ct-pv-stage{position:absolute;inset:0;overflow:hidden;cursor:grab;touch-action:none}' +
                '#ctDocPreviewModal .ct-pv-stage.is-dragging{cursor:grabbing}' +
                '#ctDocPreviewModal .ct-pv-stage img{position:absolute;left:0;top:0;transform-origin:0 0;user-select:none;-webkit-user-drag:none;max-width:none;will-change:transform;box-shadow:0 8px 30px rgba(0,0,0,.35)}' +
                '#ctDocPreviewModal .ct-pv-stage.is-smooth img{transition:transform .18s ease-out}' +
                '#ctDocPreviewModal .ct-pv-pdf{position:absolute;inset:0;overflow:auto;padding:16px;cursor:grab}' +
                '#ctDocPreviewModal .ct-pv-pdf.is-dragging{cursor:grabbing}' +
                '#ctDocPreviewModal .ct-pv-pdf canvas{display:block;margin:0 auto 16px;background:#fff;box-shadow:0 8px 30px rgba(0,0,0,.35)}' +
                '#ctDocPreviewModal .ct-pv-loading,#ctDocPreviewModal .ct-pv-error{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.6rem;color:#e2e8f0;font-size:.9rem;background:#1e293b;z-index:2}' +
                '#ctDocPreviewModal .ct-pv-error{color:#fecaca}' +
                '@media (max-width:575.98px){#ctDocPreviewModal .modal-content{height:92vh}#ctDocPreviewModal .ct-pv-label{display:none}}';
            $('<style id="ctDocPreviewStyles">').text(css).appendTo('head');
            $('body').append(
                '<div class="modal fade" id="ctDocPreviewModal" tabindex="-1" role="dialog" aria-labelledby="ctDocPreviewTitle" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">' +
                '<div class="modal-header py-2">' +
                '<div class="ct-pv-title"><h5 id="ctDocPreviewTitle">Document</h5><div class="small text-muted" id="ctDocPreviewMeta"></div></div>' +
                '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
                '</div>' +
                '<div class="ct-pv-toolbar">' +
                '<button type="button" class="btn btn-sm btn-outline-secondary js-pv-zoom-out" title="Zoom Out"><i class="fas fa-minus"></i></button>' +
                '<span class="ct-pv-zoom" id="ctDocPreviewZoom">100%</span>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary js-pv-zoom-in" title="Zoom In"><i class="fas fa-plus"></i></button>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary js-pv-reset" title="Reset Zoom (100%)"><i class="fas fa-undo"></i><span class="ct-pv-label ml-1">Reset</span></button>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary js-pv-fit" title="Fit to Screen"><i class="fas fa-expand"></i><span class="ct-pv-label ml-1">Fit</span></button>' +
                '<span class="ct-pv-spacer"></span>' +
                '<a class="btn btn-sm btn-success" id="ctDocPreviewDownload" href="#" download><i class="fas fa-download"></i><span class="ct-pv-label ml-1">Download Document</span></a>' +
                '</div>' +
                '<div class="modal-body">' +
                '<div class="ct-pv-stage d-none" id="ctDocPreviewStage"></div>' +
                '<div class="ct-pv-pdf d-none" id="ctDocPreviewPdf"></div>' +
                '<div class="ct-pv-loading" id="ctDocPreviewLoading"><i class="fas fa-spinner fa-spin fa-2x"></i><span>Loading preview…</span></div>' +
                '<div class="ct-pv-error d-none" id="ctDocPreviewError"></div>' +
                '</div>' +
                '</div></div></div>'
            );
            bindPreviewEvents();
        }

        function loadPdfJs() {
            if (window.pdfjsLib) {
                return $.Deferred().resolve(window.pdfjsLib).promise();
            }
            if (!pdfJsPromise) {
                var d = $.Deferred();
                var s = document.createElement('script');
                s.src = PDFJS_SRC;
                s.async = true;
                s.onload = function () {
                    if (window.pdfjsLib) {
                        window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER;
                        d.resolve(window.pdfjsLib);
                    } else {
                        d.reject();
                    }
                };
                s.onerror = function () {
                    pdfJsPromise = null;
                    d.reject();
                };
                document.head.appendChild(s);
                pdfJsPromise = d.promise();
            }
            return pdfJsPromise;
        }

        function fetchPreviewBlob(url) {
            var d = $.Deferred();
            if (previewBlobCache[url]) {
                return d.resolve(previewBlobCache[url]).promise();
            }
            var xhr = new XMLHttpRequest();
            preview.xhr = xhr;
            xhr.open('GET', url, true);
            xhr.responseType = 'blob';
            xhr.onload = function () {
                if (xhr.status === 200 && xhr.response) {
                    previewBlobCache[url] = xhr.response;
                    d.resolve(xhr.response);
                } else {
                    d.reject(xhr.status === 404 ? 'Document not found.' : 'Could not load the document.');
                }
            };
            xhr.onerror = function () { d.reject('Could not load the document.'); };
            xhr.onabort = function () { d.reject(null); };
            xhr.send();
            return d.promise();
        }

        function setPreviewState(state, message) {
            $('#ctDocPreviewLoading').toggleClass('d-none', state !== 'loading');
            $('#ctDocPreviewError').toggleClass('d-none', state !== 'error')
                .html(state === 'error' ? '<i class="fas fa-exclamation-triangle fa-2x"></i><span>' + esc(message || 'Could not load the document.') + '</span>' : '');
            $('#ctDocPreviewStage').toggleClass('d-none', !(state === 'ready' && preview.kind === 'image'));
            $('#ctDocPreviewPdf').toggleClass('d-none', !(state === 'ready' && preview.kind === 'pdf'));
            $('#ctDocPreviewModal .ct-pv-toolbar .btn').not('#ctDocPreviewDownload').prop('disabled', state !== 'ready');
        }

        function clampZoom(z) {
            return Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, Math.round(z * 100) / 100));
        }

        function updateZoomLabel() {
            $('#ctDocPreviewZoom').text(Math.round(preview.zoom * 100) + '%');
            $('#ctDocPreviewModal .js-pv-zoom-out').prop('disabled', preview.zoom <= ZOOM_MIN);
            $('#ctDocPreviewModal .js-pv-zoom-in').prop('disabled', preview.zoom >= ZOOM_MAX);
        }

        /**
         * Keep the image inside the viewer: centred on an axis where it is smaller than the viewer,
         * otherwise its edges can't be dragged past the viewer edges.
         */
        function clampImagePan() {
            var $stage = $('#ctDocPreviewStage');
            var sw = $stage.width();
            var sh = $stage.height();
            var w = preview.natW * preview.zoom;
            var h = preview.natH * preview.zoom;
            preview.panX = w <= sw ? (sw - w) / 2 : Math.min(0, Math.max(sw - w, preview.panX));
            preview.panY = h <= sh ? (sh - h) / 2 : Math.min(0, Math.max(sh - h, preview.panY));
        }

        function applyImageTransform(smooth) {
            if (!preview.img) {
                return;
            }
            clampImagePan();
            $('#ctDocPreviewStage').toggleClass('is-smooth', !!smooth);
            preview.img.style.transform = 'translate(' + preview.panX + 'px,' + preview.panY + 'px) scale(' + preview.zoom + ')';
            updateZoomLabel();
        }

        function centerImage() {
            var $stage = $('#ctDocPreviewStage');
            preview.panX = ($stage.width() - preview.natW * preview.zoom) / 2;
            preview.panY = ($stage.height() - preview.natH * preview.zoom) / 2;
        }

        function imageFitZoom() {
            var $stage = $('#ctDocPreviewStage');
            var pad = 24;
            return clampZoom(Math.min(1, ($stage.width() - pad) / preview.natW, ($stage.height() - pad) / preview.natH));
        }

        /** Zoom around a point in stage coordinates so the spot under the cursor stays put. */
        function zoomImageAt(newZoom, cx, cy, smooth) {
            newZoom = clampZoom(newZoom);
            var $stage = $('#ctDocPreviewStage');
            if (cx == null) {
                cx = $stage.width() / 2;
                cy = $stage.height() / 2;
            }
            var ratio = newZoom / preview.zoom;
            preview.panX = cx - (cx - preview.panX) * ratio;
            preview.panY = cy - (cy - preview.panY) * ratio;
            preview.zoom = newZoom;
            applyImageTransform(smooth);
        }

        function pdfFitZoom() {
            var first = preview.pdfPages[0];
            if (!first) {
                return 1;
            }
            var $box = $('#ctDocPreviewPdf');
            return clampZoom(Math.min(($box.width() - 40) / first.width, ($box.height() - 40) / first.height));
        }

        function renderPdfPages() {
            var seq = ++preview.renderSeq;
            var zoom = preview.zoom;
            var dpr = Math.min(window.devicePixelRatio || 1, 2);
            var chain = $.Deferred().resolve().promise();
            preview.pdfPages.forEach(function (p) {
                chain = chain.then(function () {
                    if (seq !== preview.renderSeq) {
                        return null;
                    }
                    var viewport = p.page.getViewport({ scale: zoom * dpr });
                    var canvas = p.canvas;
                    canvas.width = Math.floor(viewport.width);
                    canvas.height = Math.floor(viewport.height);
                    canvas.style.width = Math.floor(viewport.width / dpr) + 'px';
                    canvas.style.height = Math.floor(viewport.height / dpr) + 'px';
                    if (p.task) {
                        p.task.cancel();
                    }
                    p.task = p.page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport });
                    var d = $.Deferred();
                    p.task.promise.then(function () { d.resolve(); }, function () { d.resolve(); });
                    return d.promise();
                });
            });
            updateZoomLabel();
        }

        function setPdfZoom(z, immediate) {
            var boxEl = document.getElementById('ctDocPreviewPdf');
            var relX = boxEl.scrollWidth ? (boxEl.scrollLeft + boxEl.clientWidth / 2) / boxEl.scrollWidth : 0.5;
            var relY = boxEl.scrollHeight ? (boxEl.scrollTop + boxEl.clientHeight / 2) / boxEl.scrollHeight : 0;
            preview.zoom = clampZoom(z);
            updateZoomLabel();
            window.clearTimeout(preview.renderTimer);
            // Instant visual feedback via CSS size, crisp re-render once zooming settles.
            preview.pdfPages.forEach(function (p) {
                p.canvas.style.width = Math.floor(p.width * preview.zoom) + 'px';
                p.canvas.style.height = Math.floor(p.height * preview.zoom) + 'px';
            });
            // Keep the same spot centred while the pages change size.
            boxEl.scrollLeft = relX * boxEl.scrollWidth - boxEl.clientWidth / 2;
            boxEl.scrollTop = relY * boxEl.scrollHeight - boxEl.clientHeight / 2;
            if (immediate) {
                renderPdfPages();
            } else {
                preview.renderTimer = window.setTimeout(renderPdfPages, 160);
            }
        }

        function setZoom(z, cx, cy) {
            if (preview.kind === 'image') {
                zoomImageAt(z, cx, cy, cx == null);
            } else if (preview.kind === 'pdf') {
                setPdfZoom(z);
            }
        }

        function nextZoomStep(dir) {
            var steps = [0.25, 0.33, 0.5, 0.67, 0.75, 0.9, 1, 1.1, 1.25, 1.5, 1.75, 2, 2.5, 3, 3.5, 4];
            var z = preview.zoom;
            if (dir > 0) {
                for (var i = 0; i < steps.length; i++) {
                    if (steps[i] > z + 0.001) { return steps[i]; }
                }
                return ZOOM_MAX;
            }
            for (var j = steps.length - 1; j >= 0; j--) {
                if (steps[j] < z - 0.001) { return steps[j]; }
            }
            return ZOOM_MIN;
        }

        function fitToScreen() {
            if (preview.kind === 'image') {
                preview.zoom = imageFitZoom();
                centerImage();
                applyImageTransform(true);
            } else if (preview.kind === 'pdf') {
                setPdfZoom(pdfFitZoom(), true);
            }
        }

        function resetZoom() {
            if (preview.kind === 'image') {
                preview.zoom = 1;
                centerImage();
                applyImageTransform(true);
            } else if (preview.kind === 'pdf') {
                setPdfZoom(1, true);
            }
        }

        function showImagePreview(blob, seq) {
            var url = URL.createObjectURL(blob);
            var img = new Image();
            img.alt = preview.doc ? preview.doc.file_name : '';
            img.draggable = false;
            img.onload = function () {
                if (seq !== preview.seq) {
                    URL.revokeObjectURL(url);
                    return;
                }
                preview.img = img;
                preview.natW = img.naturalWidth;
                preview.natH = img.naturalHeight;
                img.style.visibility = 'hidden';
                $('#ctDocPreviewStage').empty().append(img);
                setPreviewState('ready');
                whenViewerReady(function () {
                    if (seq !== preview.seq) {
                        return;
                    }
                    preview.zoom = imageFitZoom();
                    centerImage();
                    applyImageTransform(false);
                    img.style.visibility = '';
                });
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                if (seq === preview.seq) {
                    setPreviewState('error', 'This image could not be displayed.');
                }
            };
            img.src = url;
        }

        function showPdfPreview(blob, seq) {
            Promise.all([
                Promise.resolve(loadPdfJs()),
                blob.arrayBuffer ? blob.arrayBuffer() : new Response(blob).arrayBuffer()
            ])
                .then(function (results) {
                    var pdfjsLib = results[0];
                    var data = results[1];
                    if (seq !== preview.seq) {
                        return;
                    }
                    pdfjsLib.getDocument({ data: new Uint8Array(data) }).promise.then(function (pdf) {
                        if (seq !== preview.seq) {
                            pdf.destroy();
                            return;
                        }
                        preview.pdf = pdf;
                        var $box = $('#ctDocPreviewPdf').empty();
                        var pagePromises = [];
                        for (var n = 1; n <= pdf.numPages; n++) {
                            pagePromises.push(pdf.getPage(n));
                        }
                        Promise.all(pagePromises).then(function (pages) {
                            if (seq !== preview.seq) {
                                return;
                            }
                            preview.pdfPages = pages.map(function (page) {
                                var vp = page.getViewport({ scale: 1 });
                                var canvas = document.createElement('canvas');
                                $box.append(canvas);
                                return { page: page, canvas: canvas, width: vp.width, height: vp.height, task: null };
                            });
                            setPreviewState('ready');
                            whenViewerReady(function () {
                                if (seq !== preview.seq) {
                                    return;
                                }
                                preview.zoom = pdfFitZoom();
                                renderPdfPages();
                            });
                        });
                    }, function () {
                        if (seq === preview.seq) {
                            setPreviewState('error', 'This PDF could not be displayed.');
                        }
                    });
                })
                .then(null, function () {
                    if (seq === preview.seq) {
                        setPreviewState('error', 'PDF viewer could not be loaded. Use Download Document instead.');
                    }
                });
        }

        function cleanupPreview() {
            preview.seq++;
            if (preview.xhr && preview.xhr.readyState !== 4) {
                preview.xhr.abort();
            }
            window.clearTimeout(preview.renderTimer);
            if (preview.img && preview.img.src.indexOf('blob:') === 0) {
                URL.revokeObjectURL(preview.img.src);
            }
            preview.pdfPages.forEach(function (p) {
                if (p.task) { p.task.cancel(); }
            });
            if (preview.pdf) {
                preview.pdf.destroy();
            }
            preview.img = null;
            preview.pdf = null;
            preview.pdfPages = [];
            preview.kind = '';
            preview.pendingLayout = null;
            $('#ctDocPreviewStage, #ctDocPreviewPdf').empty();
        }

        function openDocPreview(doc) {
            if (!doc || !doc.url) {
                return;
            }
            ensurePreviewModal();
            cleanupPreview();
            var seq = preview.seq;
            preview.doc = doc;
            preview.zoom = 1;
            preview.kind = doc.mime_type === 'application/pdf' ? 'pdf' : 'image';
            var owner = travellersState[paxDocsIndex];
            $('#ctDocPreviewTitle').text(doc.label || 'Document');
            $('#ctDocPreviewMeta').text((owner ? owner.name + ' · ' : '') + doc.file_name + ' · ' + doc.file_size_kb + ' KB');
            $('#ctDocPreviewDownload').attr('href', doc.url + '&download=1');
            updateZoomLabel();
            setPreviewState('loading');
            $('#ctDocPreviewModal').modal('show');

            fetchPreviewBlob(doc.url)
                .done(function (blob) {
                    if (seq !== preview.seq) {
                        return;
                    }
                    if (preview.kind === 'pdf') {
                        showPdfPreview(blob, seq);
                    } else {
                        showImagePreview(blob, seq);
                    }
                })
                .fail(function (msg) {
                    if (msg && seq === preview.seq) {
                        setPreviewState('error', msg);
                    }
                });
        }

        function bindPreviewEvents() {
            var $modal = $('#ctDocPreviewModal');
            $modal.on('shown.bs.modal', function () {
                $('.modal-backdrop').last().css('z-index', 1085);
                preview.modalShown = true;
                if (preview.pendingLayout) {
                    whenViewerReady(preview.pendingLayout);
                }
            });
            $modal.on('hide.bs.modal', function () {
                preview.modalShown = false;
            });
            $modal.on('hidden.bs.modal', function () {
                cleanupPreview();
                if ($('#ctPaxDocsModal').hasClass('show') || $('#confirmTourModal').hasClass('show')) {
                    $('body').addClass('modal-open');
                }
            });
            $modal.on('click', '.js-pv-zoom-in', function () { setZoom(nextZoomStep(1)); });
            $modal.on('click', '.js-pv-zoom-out', function () { setZoom(nextZoomStep(-1)); });
            $modal.on('click', '.js-pv-reset', resetZoom);
            $modal.on('click', '.js-pv-fit', fitToScreen);

            var stage = document.getElementById('ctDocPreviewStage');
            var pdfBox = document.getElementById('ctDocPreviewPdf');

            stage.addEventListener('wheel', function (e) {
                if (preview.kind !== 'image') { return; }
                e.preventDefault();
                var rect = stage.getBoundingClientRect();
                var factor = e.deltaY < 0 ? 1.12 : 1 / 1.12;
                zoomImageAt(preview.zoom * factor, e.clientX - rect.left, e.clientY - rect.top, false);
            }, { passive: false });

            pdfBox.addEventListener('wheel', function (e) {
                if (preview.kind !== 'pdf' || !(e.ctrlKey || e.metaKey)) { return; }
                e.preventDefault();
                setPdfZoom(preview.zoom * (e.deltaY < 0 ? 1.1 : 1 / 1.1));
            }, { passive: false });

            // Drag / pan (mouse + touch) with pinch-to-zoom on touch screens.
            var pointers = {};
            var drag = null;
            var pinch = null;

            function pinchDistance() {
                var ids = Object.keys(pointers);
                var a = pointers[ids[0]];
                var b = pointers[ids[1]];
                return { d: Math.hypot(a.x - b.x, a.y - b.y), x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
            }

            function onDown(e) {
                if (!preview.kind || (e.pointerType === 'mouse' && e.button !== 0)) { return; }
                var el = e.currentTarget;
                pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
                el.setPointerCapture(e.pointerId);
                el.classList.add('is-dragging');
                if (Object.keys(pointers).length === 2) {
                    var p = pinchDistance();
                    pinch = { d: p.d, zoom: preview.zoom };
                    drag = null;
                } else {
                    drag = {
                        x: e.clientX, y: e.clientY,
                        panX: preview.panX, panY: preview.panY,
                        left: pdfBox.scrollLeft, top: pdfBox.scrollTop
                    };
                }
            }

            function onMove(e) {
                if (!pointers[e.pointerId]) { return; }
                pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
                if (pinch && Object.keys(pointers).length === 2) {
                    var p = pinchDistance();
                    var z = pinch.zoom * (p.d / pinch.d);
                    if (preview.kind === 'image') {
                        var rect = stage.getBoundingClientRect();
                        zoomImageAt(z, p.x - rect.left, p.y - rect.top, false);
                    } else {
                        setPdfZoom(z);
                    }
                    return;
                }
                if (!drag) { return; }
                var dx = e.clientX - drag.x;
                var dy = e.clientY - drag.y;
                if (preview.kind === 'image') {
                    preview.panX = drag.panX + dx;
                    preview.panY = drag.panY + dy;
                    applyImageTransform(false);
                } else {
                    pdfBox.scrollLeft = drag.left - dx;
                    pdfBox.scrollTop = drag.top - dy;
                }
            }

            function onUp(e) {
                delete pointers[e.pointerId];
                if (Object.keys(pointers).length < 2) { pinch = null; }
                if (!Object.keys(pointers).length) {
                    drag = null;
                    e.currentTarget.classList.remove('is-dragging');
                }
            }

            [stage, pdfBox].forEach(function (el) {
                el.addEventListener('pointerdown', onDown);
                el.addEventListener('pointermove', onMove);
                el.addEventListener('pointerup', onUp);
                el.addEventListener('pointercancel', onUp);
            });

            stage.addEventListener('dblclick', function (e) {
                if (preview.kind !== 'image') { return; }
                var rect = stage.getBoundingClientRect();
                zoomImageAt(preview.zoom < 1.5 ? 2 : imageFitZoom(), e.clientX - rect.left, e.clientY - rect.top, true);
            });

            var resizeTimer = null;
            $(window).on('resize', function () {
                if (!$modal.hasClass('show') || preview.kind !== 'image' || !preview.img) { return; }
                window.clearTimeout(resizeTimer);
                resizeTimer = window.setTimeout(function () {
                    centerImage();
                    applyImageTransform(false);
                }, 120);
            });

            $(document).on('keydown', function (e) {
                if (!$modal.hasClass('show') || !preview.kind) { return; }
                if (e.key === '+' || e.key === '=') { setZoom(nextZoomStep(1)); e.preventDefault(); }
                else if (e.key === '-') { setZoom(nextZoomStep(-1)); e.preventDefault(); }
                else if (e.key === '0') { resetZoom(); e.preventDefault(); }
            });
        }

        $(document).on('click', '#ctDocCards .js-doc-view', function () {
            var key = String($(this).closest('.ct-doc-card').attr('data-type'));
            var st = docState[key];
            if (st && st.doc) {
                openDocPreview(st.doc);
            }
        });

        // ---- Service vouchers (Fill details → paperclip per service row) ----
        var SV_MAX_FILES = 10;
        var svState = { uid: '', key: '', label: '', supplier: '', vouchers: [] };
        var svQueue = [];
        var svBusy = false;
        var svSeq = 0;

        function formatVoucherDate(raw) {
            var m = String(raw || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
            if (!m) {
                return '';
            }
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return parseInt(m[3], 10) + ' ' + (months[parseInt(m[2], 10) - 1] || m[2]) + ' ' + m[1] + ', ' + m[4] + ':' + m[5];
        }

        function ensureVoucherModal() {
            if ($('#ctSvcVoucherModal').length) {
                return;
            }
            ensureDocStyles();
            $('<style id="ctSvcVoucherStyles">').text(
                '#ctSvcVoucherModal{z-index:1080}' +
                '#ctSvcVoucherModal .modal-content{border:0;border-radius:14px;overflow:hidden}' +
                '#ctSvcVoucherModal .modal-body{background:#f8fafc}' +
                '#ctSvcVoucherModal .ct-sv-sub{font-size:.8rem;color:#64748b;margin-top:.15rem}' +
                '.ct-sv-drop{border:2px dashed #cbd5e1;border-radius:12px;background:#fff;padding:1.3rem 1rem;text-align:center;cursor:pointer;transition:border-color .15s,background .15s}' +
                '.ct-sv-drop:hover,.ct-sv-drop:focus,.ct-sv-drop.is-over{border-color:#3b82f6;background:#eff6ff;outline:0}' +
                '.ct-sv-drop.is-full{opacity:.6;cursor:not-allowed}' +
                '.ct-sv-drop>i{font-size:1.8rem;color:#3b82f6}' +
                '.ct-sv-drop-title{font-weight:600;color:#0f172a;margin-top:.35rem;font-size:.92rem}' +
                '.ct-sv-drop-hint{font-size:.76rem;color:#64748b;margin-top:.15rem}' +
                '.ct-sv-list-head{display:flex;justify-content:space-between;align-items:center;margin:1rem 0 .5rem;font-size:.78rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.03em}' +
                '#ctSvcVoucherList.is-loading{opacity:.55;pointer-events:none}' +
                '.ct-sv-item{display:flex;align-items:center;gap:.75rem;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:.6rem .75rem;margin-bottom:.5rem;box-shadow:0 1px 3px rgba(15,23,42,.05)}' +
                '.ct-sv-item.is-uploading{border-color:#bfdbfe}.ct-sv-item.is-failed{border-color:#fecaca}' +
                '.ct-sv-thumb{flex:0 0 44px;width:44px;height:44px;border-radius:10px;background:#eef2ff;color:#4f46e5;display:flex;align-items:center;justify-content:center;font-size:1.2rem;overflow:hidden}' +
                '.ct-sv-thumb.is-pdf{background:#fef2f2;color:#dc2626}' +
                '.ct-sv-thumb img{width:100%;height:100%;object-fit:cover}' +
                '.ct-sv-body{flex:1 1 auto;min-width:0}' +
                '.ct-sv-name{font-weight:600;color:#0f172a;font-size:.86rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
                '.ct-sv-meta{font-size:.74rem;color:#64748b;margin-top:.1rem}' +
                '.ct-sv-item .progress{height:.4rem;border-radius:999px;background:#e2e8f0;margin-top:.35rem}' +
                '.ct-sv-err{font-size:.74rem;color:#dc2626;margin-top:.15rem}' +
                '.ct-sv-empty{text-align:center;color:#94a3b8;font-size:.85rem;padding:1.2rem 0}' +
                '@media (max-width:575.98px){.ct-sv-item{flex-wrap:wrap}.ct-sv-item .ct-doc-actions{width:100%;justify-content:flex-end}}'
            ).appendTo('head');

            $('body').append(
                '<div class="modal fade" id="ctSvcVoucherModal" tabindex="-1" role="dialog" aria-labelledby="ctSvcVoucherTitle" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered modal-lg" role="document"><div class="modal-content">' +
                '<div class="modal-header"><div>' +
                '<h5 class="modal-title" id="ctSvcVoucherTitle"><i class="fas fa-ticket-alt mr-2 text-primary"></i>Service Vouchers</h5>' +
                '<div class="ct-sv-sub" id="ctSvcVoucherMeta"></div></div>' +
                '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>' +
                '<div class="modal-body">' +
                '<div class="ct-sv-drop" id="ctSvcVoucherDrop" tabindex="0" role="button" aria-label="Upload vouchers">' +
                '<i class="fas fa-cloud-upload-alt"></i>' +
                '<div class="ct-sv-drop-title">Click to upload or drag &amp; drop voucher files</div>' +
                '<div class="ct-sv-drop-hint">JPG, PNG, WEBP or PDF · max 4 MB each · up to ' + SV_MAX_FILES + ' files per service</div>' +
                '<input type="file" id="ctSvcVoucherFile" class="d-none" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">' +
                '</div>' +
                '<div class="ct-sv-list-head"><span>Attached vouchers</span><span id="ctSvcVoucherCount"></span></div>' +
                '<div id="ctSvcVoucherList"></div>' +
                '</div>' +
                '<div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Close</button></div>' +
                '</div></div></div>'
            );
            bindVoucherEvents();
        }

        function voucherPending() {
            return svQueue.filter(function (u) {
                return u.uid === svState.uid && u.status !== 'failed';
            }).length;
        }

        function voucherItemHtml(v) {
            var isPdf = v.mime_type === 'application/pdf';
            var thumb = v.thumb_url
                ? '<img src="' + esc(v.thumb_url) + '" alt="" loading="lazy" decoding="async">'
                : '<i class="fas ' + (isPdf ? 'fa-file-pdf' : 'fa-file-image') + '"></i>';
            var when = formatVoucherDate(v.uploaded_at);
            return '<div class="ct-sv-item" data-id="' + v.id + '">' +
                '<div class="ct-sv-thumb' + (isPdf ? ' is-pdf' : '') + '">' + thumb + '</div>' +
                '<div class="ct-sv-body"><div class="ct-sv-name" title="' + esc(v.file_name) + '">' + esc(v.file_name) + '</div>' +
                '<div class="ct-sv-meta">' + (isPdf ? 'PDF' : 'Image') + ' · ' + esc(String(v.file_size_kb)) + ' KB' + (when ? ' · ' + esc(when) : '') + '</div></div>' +
                '<div class="ct-doc-actions">' +
                '<button type="button" class="btn btn-outline-primary btn-icon js-sv-view" title="View"><i class="fas fa-eye"></i></button>' +
                '<a class="btn btn-outline-success btn-icon" href="' + esc(v.url + '&download=1') + '" download title="Download"><i class="fas fa-download"></i></a>' +
                '<button type="button" class="btn btn-outline-danger btn-icon js-sv-delete" title="Delete"><i class="fas fa-trash-alt"></i></button>' +
                '</div></div>';
        }

        function voucherUploadHtml(u) {
            var failed = u.status === 'failed';
            var text = failed ? 'Failed' : (u.status === 'queued' ? 'Waiting…' : (u.pct >= 100 ? 'Compressing…' : u.pct + '% Uploaded'));
            return '<div class="ct-sv-item is-' + (failed ? 'failed' : 'uploading') + '" data-upload="' + u.id + '">' +
                '<div class="ct-sv-thumb"><i class="fas ' + (failed ? 'fa-exclamation-circle text-danger' : 'fa-spinner fa-spin') + '"></i></div>' +
                '<div class="ct-sv-body"><div class="ct-sv-name">' + esc(u.file.name) + '</div>' +
                '<div class="ct-sv-meta js-sv-up-text">' + esc(text) + '</div>' +
                (failed
                    ? '<div class="ct-sv-err">' + esc(u.error) + '</div>'
                    : '<div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:' + u.pct + '%"></div></div>') +
                '</div>' +
                (failed ? '<div class="ct-doc-actions"><button type="button" class="btn btn-light btn-icon js-sv-dismiss" title="Dismiss"><i class="fas fa-times"></i></button></div>' : '') +
                '</div>';
        }

        function renderVoucherList() {
            var uploads = svQueue.filter(function (u) { return u.uid === svState.uid; });
            var html = svState.vouchers.map(voucherItemHtml).join('') + uploads.map(voucherUploadHtml).join('');
            $('#ctSvcVoucherList').html(html || '<div class="ct-sv-empty"><i class="fas fa-folder-open mr-1"></i>No vouchers attached yet.</div>');
            var n = svState.vouchers.length;
            $('#ctSvcVoucherCount').text(n + ' / ' + SV_MAX_FILES);
            $('#ctSvcVoucherDrop').toggleClass('is-full', n + voucherPending() >= SV_MAX_FILES);
        }

        function openVoucherModal($row) {
            if (!activeQuotationId) {
                return;
            }
            ensureVoucherModal();
            svSeq++;
            var seq = svSeq;
            svState.uid = String($row.attr('data-uid') || '');
            svState.key = String($row.attr('data-key') || '');
            svState.label = $.trim($row.find('.ct-detail-label').text()) || svState.key;
            svState.supplier = $.trim($row.find('.ct-supplier').val());
            svState.vouchers = [];
            $('#ctSvcVoucherMeta').text(svState.label + (svState.supplier ? ' · ' + svState.supplier : ''));
            renderVoucherList();
            $('#ctSvcVoucherList').addClass('is-loading');
            $('#ctSvcVoucherModal').modal('show');

            $.getJSON('crm/ajax/service_vouchers.php', {
                action: 'list',
                quotation_id: activeQuotationId,
                service_uid: svState.uid
            })
                .done(function (res) {
                    if (seq !== svSeq) {
                        return;
                    }
                    if (!res || !res.success) {
                        showToast((res && res.message) || 'Could not load vouchers.', 'error');
                        return;
                    }
                    svState.vouchers = res.vouchers || [];
                    renderVoucherList();
                    setRowVoucherCount(svState.uid, svState.vouchers.length);
                })
                .fail(function () {
                    if (seq === svSeq) {
                        showToast('Could not load vouchers. Please try again.', 'error');
                    }
                })
                .always(function () {
                    if (seq === svSeq) {
                        $('#ctSvcVoucherList').removeClass('is-loading');
                    }
                });
        }

        function queueVoucherFiles(fileList) {
            var files = [];
            for (var i = 0; i < (fileList ? fileList.length : 0); i++) {
                files.push(fileList[i]);
            }
            var room = SV_MAX_FILES - svState.vouchers.length - voucherPending();
            var skipped = 0;
            files.forEach(function (file) {
                var error = validateDocFile({ pdf: true }, file);
                if (error) {
                    showToast(file.name + ': ' + error, 'error');
                    return;
                }
                if (room <= 0) {
                    skipped++;
                    return;
                }
                room--;
                svQueue.push({
                    id: 'u' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6),
                    uid: svState.uid,
                    key: svState.key,
                    quotationId: activeQuotationId,
                    file: file,
                    pct: 0,
                    status: 'queued',
                    error: ''
                });
            });
            if (skipped) {
                showToast('Only ' + SV_MAX_FILES + ' vouchers are allowed per service; ' + skipped + ' file' + (skipped === 1 ? ' was' : 's were') + ' skipped.', 'error');
            }
            renderVoucherList();
            pumpVoucherUploads();
        }

        function pumpVoucherUploads() {
            if (svBusy) {
                return;
            }
            var u = null;
            for (var i = 0; i < svQueue.length; i++) {
                if (svQueue[i].status === 'queued') {
                    u = svQueue[i];
                    break;
                }
            }
            if (!u) {
                return;
            }
            svBusy = true;
            u.status = 'uploading';
            if (u.uid === svState.uid) {
                renderVoucherList();
            }

            var fd = new FormData();
            fd.append('action', 'upload');
            fd.append('quotation_id', String(u.quotationId));
            fd.append('service_uid', u.uid);
            fd.append('service_key', u.key);
            fd.append('file', u.file);

            var finish = function (errorMsg) {
                if (errorMsg) {
                    u.status = 'failed';
                    u.error = errorMsg;
                    showToast(u.file.name + ': ' + errorMsg, 'error');
                } else {
                    svQueue = svQueue.filter(function (q) { return q !== u; });
                }
                if (u.uid === svState.uid) {
                    renderVoucherList();
                }
                svBusy = false;
                pumpVoucherUploads();
            };

            $.ajax({
                url: 'crm/ajax/service_vouchers.php',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                xhr: function () {
                    var xhr = $.ajaxSettings.xhr();
                    if (xhr.upload) {
                        xhr.upload.addEventListener('progress', function (e) {
                            if (!e.lengthComputable) {
                                return;
                            }
                            u.pct = Math.min(100, Math.round((e.loaded / e.total) * 100));
                            var $item = $('#ctSvcVoucherList [data-upload="' + u.id + '"]');
                            $item.find('.progress-bar').css('width', u.pct + '%');
                            $item.find('.js-sv-up-text').text(u.pct >= 100 ? 'Compressing…' : u.pct + '% Uploaded');
                        });
                    }
                    return xhr;
                }
            })
                .done(function (res) {
                    if (!res || !res.success || !res.voucher) {
                        finish((res && res.message) || 'Upload failed.');
                        return;
                    }
                    if (u.quotationId === activeQuotationId) {
                        setRowVoucherCount(u.uid, res.count);
                    }
                    if (u.uid === svState.uid) {
                        svState.vouchers = res.vouchers || [];
                    }
                    showToast(res.voucher.file_name + ' uploaded (' + res.voucher.file_size_kb + ' KB' +
                        (res.pdf_compressed === false ? ', PDF stored without compression' : '') + ').', 'success');
                    finish('');
                })
                .fail(function (xhr) {
                    var msg = 'Upload failed. Please try again.';
                    if (xhr && xhr.status === 413) {
                        msg = DOC_SIZE_ERROR;
                    } else if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    finish(msg);
                });
        }

        function findVoucher(id) {
            for (var i = 0; i < svState.vouchers.length; i++) {
                if (String(svState.vouchers[i].id) === String(id)) {
                    return svState.vouchers[i];
                }
            }
            return null;
        }

        function deleteVoucher(v) {
            var uid = svState.uid;
            var quotationId = activeQuotationId;
            $('#ctSvcVoucherList .ct-sv-item[data-id="' + v.id + '"] .js-sv-delete').prop('disabled', true);
            $.ajax({
                url: 'crm/ajax/service_vouchers.php',
                type: 'POST',
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: { action: 'delete', quotation_id: quotationId, service_uid: uid, voucher_id: v.id }
            })
                .done(function (res) {
                    if (!res || !res.success) {
                        showToast((res && res.message) || 'Could not delete voucher.', 'error');
                        $('#ctSvcVoucherList .ct-sv-item[data-id="' + v.id + '"] .js-sv-delete').prop('disabled', false);
                        return;
                    }
                    if (quotationId === activeQuotationId) {
                        setRowVoucherCount(uid, res.count);
                    }
                    if (uid === svState.uid) {
                        svState.vouchers = res.vouchers || [];
                        renderVoucherList();
                    }
                    showToast(v.file_name + ' deleted.', 'success');
                })
                .fail(function () {
                    showToast('Could not delete voucher. Please try again.', 'error');
                    $('#ctSvcVoucherList .ct-sv-item[data-id="' + v.id + '"] .js-sv-delete').prop('disabled', false);
                });
        }

        function bindVoucherEvents() {
            var $modal = $('#ctSvcVoucherModal');
            var $drop = $('#ctSvcVoucherDrop');

            $modal.on('shown.bs.modal', function () {
                $('.modal-backdrop').last().css('z-index', 1075);
            });
            $modal.on('hidden.bs.modal', function () {
                svSeq++;
                svState.uid = '';
                if ($('#confirmTourModal').hasClass('show')) {
                    $('body').addClass('modal-open');
                }
            });

            $drop.on('click', function (e) {
                if ($(e.target).is('#ctSvcVoucherFile') || $drop.hasClass('is-full')) {
                    return;
                }
                $('#ctSvcVoucherFile').val('').trigger('click');
            });
            $drop.on('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $drop.trigger('click');
                }
            });
            $drop.on('dragenter dragover', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $drop.addClass('is-over');
            });
            $drop.on('dragleave dragend', function (e) {
                e.preventDefault();
                $drop.removeClass('is-over');
            });
            $drop.on('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $drop.removeClass('is-over');
                var dt = e.originalEvent && e.originalEvent.dataTransfer;
                if (dt && dt.files && dt.files.length) {
                    if ($drop.hasClass('is-full')) {
                        showToast('Only ' + SV_MAX_FILES + ' vouchers are allowed per service.', 'error');
                        return;
                    }
                    queueVoucherFiles(dt.files);
                }
            });
            $('#ctSvcVoucherFile').on('change', function () {
                queueVoucherFiles(this.files);
                this.value = '';
            });

            $modal.on('click', '.js-sv-view', function () {
                var v = findVoucher($(this).closest('.ct-sv-item').attr('data-id'));
                if (v) {
                    openDocPreview($.extend({}, v, { label: svState.label + ' Voucher' }));
                }
            });
            $modal.on('click', '.js-sv-delete', function () {
                var v = findVoucher($(this).closest('.ct-sv-item').attr('data-id'));
                if (!v) {
                    return;
                }
                showConfirmDialog({
                    variant: 'danger',
                    icon: 'fa-trash-alt',
                    title: 'Delete voucher?',
                    message: '<strong>' + esc(v.file_name) + '</strong> will be permanently removed from ' + esc(svState.label) + '. This cannot be undone.',
                    confirmText: 'Yes, Delete',
                    onConfirm: function () {
                        deleteVoucher(v);
                    }
                });
            });
            $modal.on('click', '.js-sv-dismiss', function () {
                var id = String($(this).closest('.ct-sv-item').attr('data-upload'));
                svQueue = svQueue.filter(function (q) { return q.id !== id; });
                renderVoucherList();
            });
        }

        $(document).on('click', '#ctDetailRows .ct-svc-voucher, #ctDetailRows .ct-svc-voucher-act', function () {
            openVoucherModal($(this).closest('.ct-detail-row'));
        });

        var SP_DEFAULT_METHODS = ['Cash', 'UPI', 'Bank Transfer', 'Cheque', 'Card', 'Other'];
        var spState = { uid: '', key: '', quotationId: 0, payments: [], methods: SP_DEFAULT_METHODS.slice(), loaded: false, busy: false };
        var spSeq = 0;
        var spUseUiDate = false;

        function spTodayYmd() {
            var d = new Date();
            return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
        }

        function spFormatDate(ymd) {
            var m = String(ymd || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (!m) {
                return '—';
            }
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return m[3] + ' ' + (months[parseInt(m[2], 10) - 1] || m[2]) + ' ' + m[1];
        }

        function spFindRow(uid) {
            return $('#ctDetailRows .ct-detail-row').filter(function () {
                return String($(this).attr('data-uid')) === String(uid);
            }).first();
        }

        function spRowFigures($row) {
            var total = parseNum($row.find('.ct-total').val());
            var paid = parseNum($row.find('.ct-paid').val());
            return {
                label: $.trim($row.find('.ct-detail-label').text()) || spState.key,
                supplier: $.trim(String($row.find('.ct-supplier').val() || '')),
                total: total,
                paid: paid,
                balance: Math.max(0, total - paid),
                vouchers: parseInt($row.attr('data-vouchers'), 10) || 0
            };
        }

        function ensurePayModalStyles() {
            if (document.getElementById('ctSvcPayStyles')) {
                return;
            }
            var p = '.ct-pay-modal';
            $('<style id="ctSvcPayStyles">').text(
                p + '{z-index:1080}' +
                p + ' .modal-content{border:0;border-radius:14px;overflow:hidden}' +
                p + ' .modal-header{align-items:center;border-bottom:1px solid #e2e8f0;padding:.9rem 1.2rem}' +
                p + ' .modal-body{background:#f8fafc;padding:1.1rem 1.2rem}' +
                p + ' .ct-sp-head{display:flex;align-items:center;gap:.8rem;min-width:0}' +
                p + ' .ct-sp-head .ct-svc-icon{flex:0 0 42px;width:42px;height:42px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-size:1.1rem;background:#f1f5f9;color:#475569}' +
                p + ' .ct-sp-head .ct-svc-icon.is-hotels{background:#eff6ff;color:#2563eb}' +
                p + ' .ct-sp-head .ct-svc-icon.is-flight{background:#eef2ff;color:#4f46e5}' +
                p + ' .ct-sp-head .ct-svc-icon.is-land_package{background:#fff7ed;color:#ea580c}' +
                p + ' .ct-sp-head .ct-svc-icon.is-visa{background:#f5f3ff;color:#7c3aed}' +
                p + ' .ct-sp-head .ct-svc-icon.is-transfers{background:#f0fdfa;color:#0d9488}' +
                p + ' .ct-sp-head .ct-svc-icon.is-travel_insurance{background:#f0fdf4;color:#16a34a}' +
                p + ' .ct-sp-head .ct-svc-icon.is-forex{background:#fffbeb;color:#d97706}' +
                p + ' .ct-sp-head .ct-svc-icon.is-train{background:#f0f9ff;color:#0284c7}' +
                p + ' .ct-sp-head .ct-svc-icon.is-tours{background:#fdf2f8;color:#db2777}' +
                p + ' .ct-sp-head .ct-svc-icon.is-cruise{background:#ecfeff;color:#0891b2}' +
                p + ' .modal-title{font-size:1.05rem;font-weight:700;color:#0f172a;line-height:1.2}' +
                p + ' .ct-sp-sub{font-size:.8rem;color:#64748b;margin-top:.15rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
                p + ' .ct-sp-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem}' +
                p + ' .ct-sp-card{border-radius:10px;padding:.65rem .8rem;background:#fff;border:1px solid #e2e8f0}' +
                p + ' .ct-sp-card-label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.03em}' +
                p + ' .ct-sp-card-val{font-size:1.08rem;font-weight:700;color:#0f172a;font-variant-numeric:tabular-nums;margin-top:.15rem;white-space:nowrap}' +
                p + ' .ct-sp-card.is-total{background:#eff6ff;border-color:#dbeafe}' + p + ' .ct-sp-card.is-total .ct-sp-card-label{color:#2563eb}' +
                p + ' .ct-sp-card.is-paid{background:#f0fdf4;border-color:#dcfce7}' + p + ' .ct-sp-card.is-paid .ct-sp-card-label{color:#16a34a}' +
                p + ' .ct-sp-card.is-due{background:#fef2f2;border-color:#fee2e2}' + p + ' .ct-sp-card.is-due .ct-sp-card-label{color:#dc2626}' +
                p + ' .ct-sp-card.is-due .ct-sp-card-val{color:#b91c1c}' +
                p + ' .ct-sp-card.is-status .ct-sp-card-label{color:#64748b}' +
                p + ' .ct-sp-card.is-status .ct-sp-card-val{font-size:.9rem;padding-top:.15rem}' +
                p + ' .ct-sp-progress{display:flex;align-items:center;gap:.7rem;margin:.8rem 0 0}' +
                p + ' .ct-sp-progress .progress{flex:1 1 auto;height:.5rem;border-radius:999px;background:#e2e8f0}' +
                p + ' .ct-sp-progress .progress-bar{background:#16a34a;border-radius:999px;transition:width .3s}' +
                p + ' .ct-sp-progress span{font-size:.76rem;font-weight:600;color:#475569;white-space:nowrap}' +
                p + ' .ct-sp-section{background:#fff;border:1px solid #e2e8f0;border-radius:12px;margin-top:.9rem}' +
                p + ' .ct-sp-section-head{display:flex;align-items:center;justify-content:space-between;gap:.6rem;padding:.7rem .9rem;border-bottom:1px solid #eef2f7}' +
                p + ' .ct-sp-section-title{font-size:.8rem;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.03em}' +
                p + ' .ct-sp-details{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem 1rem;padding:.8rem .9rem}' +
                p + ' .ct-sp-details dt{font-size:.72rem;font-weight:500;color:#64748b;margin:0}' +
                p + ' .ct-sp-details dd{font-size:.86rem;font-weight:600;color:#0f172a;margin:.1rem 0 0;word-break:break-word}' +
                p + ' .ct-sp-add-btn{height:32px;padding:0 .8rem;border:0;border-radius:8px;background:#16a34a;color:#fff;font-size:.8rem;font-weight:600}' +
                p + ' .ct-sp-add-btn:hover{filter:brightness(.95)}' +
                p + ' .ct-sp-add-btn:disabled{opacity:.55}' +
                p + ' .ct-sp-form{padding:.85rem .9rem;background:#f0fdf4;border-bottom:1px solid #dcfce7}' +
                p + ' .ct-sp-form label{font-size:.74rem;font-weight:600;color:#334155;margin-bottom:.2rem}' +
                p + ' .ct-sp-form .form-control{height:36px;font-size:.85rem;border-radius:8px}' +
                p + ' .ct-sp-form textarea.form-control{height:auto;min-height:56px}' +
                p + ' .ct-sp-form .input-group-text{font-size:.85rem;border-radius:8px 0 0 8px;background:#fff}' +
                p + ' .ct-sp-form .ct-sp-hint{font-size:.72rem;color:#64748b;margin-top:.2rem}' +
                p + ' .ct-sp-form .ct-sp-error{font-size:.78rem;color:#dc2626;margin-right:auto}' +
                p + ' .ct-sp-form-actions{display:flex;align-items:center;justify-content:flex-end;gap:.5rem;flex-wrap:wrap}' +
                p + ' .ct-sp-form-actions .btn{height:36px;border-radius:8px;font-size:.84rem;font-weight:600;padding:0 1rem}' +
                p + ' .ct-sp-table{margin:0;font-size:.84rem}' +
                p + ' .ct-sp-table th{font-size:.72rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.03em;background:#f8fafc;border-top:0;border-bottom:1px solid #e2e8f0;white-space:nowrap;padding:.55rem .75rem}' +
                p + ' .ct-sp-table td{vertical-align:middle;padding:.55rem .75rem;border-top:1px solid #eef2f7;color:#0f172a}' +
                p + ' .ct-sp-table .ct-sp-amt{text-align:right;font-weight:600;color:#16a34a;font-variant-numeric:tabular-nums;white-space:nowrap}' +
                p + ' .ct-sp-table .ct-sp-date{white-space:nowrap}' +
                p + ' .ct-sp-table .ct-sp-by{display:block;font-size:.7rem;color:#94a3b8}' +
                p + ' .ct-sp-table .ct-sp-notes{max-width:220px;color:#475569;white-space:pre-line;word-break:break-word}' +
                p + ' .ct-sp-table .ct-sp-muted{color:#94a3b8}' +
                p + ' .ct-sp-table tr.is-legacy td{background:#fffbeb;color:#92400e;font-style:italic}' +
                p + ' .ct-sp-method{display:inline-block;padding:.15rem .5rem;border-radius:6px;background:#eef2ff;color:#4338ca;font-size:.74rem;font-weight:600;white-space:nowrap}' +
                p + ' .ct-sp-del{width:28px;height:28px;padding:0;border:0;border-radius:7px;background:#fef2f2;color:#dc2626;font-size:.8rem}' +
                p + ' .ct-sp-del:hover{filter:brightness(.95)}' +
                p + ' .ct-sp-empty{text-align:center;color:#94a3b8;font-size:.85rem;padding:1.3rem .5rem}' +
                p + ' .ct-sp-history.is-loading{opacity:.55;pointer-events:none}' +
                p + ' .ct-sp-card.is-over{background:#fffbeb;border-color:#fde68a}' + p + ' .ct-sp-card.is-over .ct-sp-card-label{color:#b45309}' +
                p + ' .ct-sp-head .ct-svc-icon.is-customer{background:#fff1f2;color:#e11d48}' +
                '@media (max-width:767.98px){' + p + ' .ct-sp-cards{grid-template-columns:repeat(2,minmax(0,1fr))}' + p + ' .ct-sp-details{grid-template-columns:repeat(2,minmax(0,1fr))}}'
            ).appendTo('head');
        }

        function ensurePaymentModal() {
            if ($('#ctSvcPayModal').length) {
                return;
            }
            ensurePayModalStyles();
            $('body').append(
                '<div class="modal fade ct-pay-modal" id="ctSvcPayModal" tabindex="-1" role="dialog" aria-labelledby="ctSvcPayTitle" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg" role="document"><div class="modal-content">' +
                '<div class="modal-header"><div class="ct-sp-head">' +
                '<span class="ct-svc-icon" id="ctSvcPayIcon"><i class="fas fa-concierge-bell"></i></span>' +
                '<div style="min-width:0"><h5 class="modal-title" id="ctSvcPayTitle">Service</h5><div class="ct-sp-sub" id="ctSvcPaySub"></div></div></div>' +
                '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>' +
                '<div class="modal-body">' +
                '<div class="ct-sp-cards">' +
                '<div class="ct-sp-card is-total"><div class="ct-sp-card-label">Total Amount</div><div class="ct-sp-card-val js-sp-total">—</div></div>' +
                '<div class="ct-sp-card is-paid"><div class="ct-sp-card-label">Total Paid</div><div class="ct-sp-card-val js-sp-paid">—</div></div>' +
                '<div class="ct-sp-card is-due"><div class="ct-sp-card-label">Balance</div><div class="ct-sp-card-val js-sp-balance">—</div></div>' +
                '<div class="ct-sp-card is-status"><div class="ct-sp-card-label">Payment Status</div><div class="ct-sp-card-val js-sp-status"></div></div>' +
                '</div>' +
                '<div class="ct-sp-progress"><div class="progress"><div class="progress-bar js-sp-bar" role="progressbar" style="width:0%"></div></div><span class="js-sp-pct">0% paid</span></div>' +

                '<div class="ct-sp-section"><div class="ct-sp-section-head"><span class="ct-sp-section-title">Payment Details</span></div>' +
                '<dl class="ct-sp-details">' +
                '<div><dt>Service</dt><dd class="js-sp-d-service"></dd></div>' +
                '<div><dt>Supplier</dt><dd class="js-sp-d-supplier"></dd></div>' +
                '<div><dt>Payments Recorded</dt><dd class="js-sp-d-count"></dd></div>' +
                '<div><dt>Last Payment</dt><dd class="js-sp-d-last"></dd></div>' +
                '<div><dt>Last Payment Method</dt><dd class="js-sp-d-method"></dd></div>' +
                '<div><dt>Vouchers Attached</dt><dd class="js-sp-d-vouchers"></dd></div>' +
                '</dl></div>' +

                '<div class="ct-sp-section">' +
                '<div class="ct-sp-section-head"><span class="ct-sp-section-title">Payment History</span>' +
                '<button type="button" class="ct-sp-add-btn" id="ctSpAddToggle"><i class="fas fa-plus mr-1"></i>Add Payment</button></div>' +
                '<form class="ct-sp-form d-none" id="ctSpForm" novalidate autocomplete="off">' +
                '<div class="form-row">' +
                '<div class="form-group col-6 col-md-4"><label for="ctSpDateText">Payment Date <span class="text-danger">*</span></label>' +
                '<input type="text" class="form-control" id="ctSpDateText" placeholder="dd/mm/yyyy"><input type="hidden" id="ctSpDate"></div>' +
                '<div class="form-group col-6 col-md-4"><label for="ctSpAmount">Amount <span class="text-danger">*</span></label>' +
                '<div class="input-group"><div class="input-group-prepend"><span class="input-group-text">\u20B9</span></div>' +
                '<input type="text" inputmode="decimal" class="form-control" id="ctSpAmount" placeholder="0.00"></div>' +
                '<div class="ct-sp-hint js-sp-amount-hint"></div></div>' +
                '<div class="form-group col-12 col-md-4"><label for="ctSpMethod">Payment Method <span class="text-danger">*</span></label>' +
                '<select class="form-control" id="ctSpMethod"></select></div>' +
                '<div class="form-group col-12 col-md-4"><label for="ctSpReference">Reference / Transaction No.</label>' +
                '<input type="text" class="form-control" id="ctSpReference" maxlength="120" placeholder="Optional"></div>' +
                '<div class="form-group col-12 col-md-8"><label for="ctSpNotes">Notes</label>' +
                '<textarea class="form-control" id="ctSpNotes" rows="2" maxlength="500" placeholder="Optional"></textarea></div>' +
                '</div>' +
                '<div class="ct-sp-form-actions"><span class="ct-sp-error" id="ctSpError"></span>' +
                '<button type="button" class="btn btn-light" id="ctSpCancel">Cancel</button>' +
                '<button type="submit" class="btn btn-success" id="ctSpSave"><i class="fas fa-check mr-1"></i>Save Payment</button></div>' +
                '</form>' +
                '<div class="ct-sp-history" id="ctSpHistory"></div>' +
                '</div>' +
                '</div>' +
                '<div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Close</button></div>' +
                '</div></div></div>'
            );

            spUseUiDate = !!($.datepicker && $.fn.datepicker);
            if (spUseUiDate) {
                $('#ctSpDateText').datepicker({
                    dateFormat: 'dd/mm/yy',
                    altField: '#ctSpDate',
                    altFormat: 'yy-mm-dd',
                    maxDate: 0,
                    beforeShow: function () {
                        window.setTimeout(function () { $('#ui-datepicker-div').css('z-index', 2200); }, 0);
                    }
                });
            } else {
                $('#ctSpDateText').attr({ type: 'date', placeholder: '' });
            }
            bindPaymentEvents();
        }

        function spReadDate() {
            if (!spUseUiDate) {
                return String($('#ctSpDateText').val() || '');
            }
            var text = $.trim(String($('#ctSpDateText').val() || ''));
            if (!text) {
                return '';
            }
            try {
                var d = $.datepicker.parseDate('dd/mm/yy', text);
                return d ? $.datepicker.formatDate('yy-mm-dd', d) : '';
            } catch (err) {
                return '';
            }
        }

        function spSetDate(ymd) {
            if (spUseUiDate) {
                var m = String(ymd).match(/^(\d{4})-(\d{2})-(\d{2})$/);
                $('#ctSpDateText').datepicker('setDate', m ? new Date(+m[1], +m[2] - 1, +m[3]) : null);
            } else {
                $('#ctSpDateText').val(ymd).attr('max', spTodayYmd());
            }
        }

        function spFillMethods() {
            var $sel = $('#ctSpMethod');
            var current = $sel.val();
            $sel.html(spState.methods.map(function (m) {
                return '<option value="' + esc(m) + '">' + esc(m) + '</option>';
            }).join(''));
            if (current && spState.methods.indexOf(current) >= 0) {
                $sel.val(current);
            }
        }

        function renderPaymentModal() {
            var $row = spFindRow(spState.uid);
            if (!$row.length) {
                return;
            }
            var f = spRowFigures($row);
            var status = paymentStatus(f.total, f.paid);
            var pct = f.total > 0 ? Math.min(100, Math.round((f.paid / f.total) * 100)) : (f.paid > 0 ? 100 : 0);

            $('#ctSvcPayIcon').attr('class', 'ct-svc-icon is-' + spState.key)
                .html('<i class="fas ' + (SERVICE_ICONS[spState.key] || 'fa-concierge-bell') + '"></i>');
            $('#ctSvcPayTitle').text(f.label);
            $('#ctSvcPaySub').text(f.supplier ? 'Supplier: ' + f.supplier : 'No supplier set');

            var $m = $('#ctSvcPayModal');
            $m.find('.js-sp-total').text('\u20B9 ' + money2(f.total));
            $m.find('.js-sp-paid').text('\u20B9 ' + money2(f.paid));
            $m.find('.js-sp-balance').text('\u20B9 ' + money2(f.balance));
            $m.find('.js-sp-status').html(statusPillHtml(f.total, f.paid));
            $m.find('.js-sp-bar').css('width', pct + '%').attr('aria-valuenow', pct);
            $m.find('.js-sp-pct').text(pct + '% paid');

            var list = spState.payments;
            var last = list[0] || null;
            $m.find('.js-sp-d-service').text(f.label);
            $m.find('.js-sp-d-supplier').text(f.supplier || '—');
            $m.find('.js-sp-d-count').text(spState.loaded ? String(list.length) : '…');
            $m.find('.js-sp-d-last').text(last ? spFormatDate(last.payment_date) + ' · \u20B9 ' + money2(last.amount) : '—');
            $m.find('.js-sp-d-method').text(last ? last.method : '—');
            $m.find('.js-sp-d-vouchers').text(String(f.vouchers));

            $('#ctSpAddToggle').prop('disabled', spState.busy);
            $m.find('.js-sp-amount-hint').text(f.total > 0 ? 'Balance due: \u20B9 ' + money2(f.balance) : 'Set the service Total first.');
            renderPaymentHistory(f);
        }

        function renderPaymentHistory(f) {
            var $wrap = $('#ctSpHistory');
            if (!spState.loaded) {
                $wrap.html('<div class="ct-sp-empty"><i class="fas fa-spinner fa-spin mr-1"></i>Loading payments…</div>');
                return;
            }
            var list = spState.payments;
            var recorded = list.reduce(function (sum, p) { return sum + (parseFloat(p.amount) || 0); }, 0);
            var legacy = Math.round((f.paid - recorded) * 100) / 100;
            if (!list.length && legacy <= 0) {
                $wrap.html('<div class="ct-sp-empty"><i class="far fa-credit-card mr-1"></i>No payments recorded yet. Click <strong>Add Payment</strong> to record one.</div>');
                return;
            }
            var rows = list.map(function (p) {
                return '<tr data-id="' + p.id + '">' +
                    '<td class="ct-sp-date">' + esc(spFormatDate(p.payment_date)) +
                    (p.created_by ? '<span class="ct-sp-by">by ' + esc(p.created_by) + '</span>' : '') + '</td>' +
                    '<td class="ct-sp-amt">\u20B9 ' + money2(p.amount) + '</td>' +
                    '<td><span class="ct-sp-method">' + esc(p.method || '—') + '</span></td>' +
                    '<td>' + (p.reference ? esc(p.reference) : '<span class="ct-sp-muted">—</span>') + '</td>' +
                    '<td class="ct-sp-notes">' + (p.notes ? esc(p.notes) : '<span class="ct-sp-muted">—</span>') + '</td>' +
                    '<td class="text-right"><button type="button" class="ct-sp-del js-sp-del" title="Delete payment" aria-label="Delete payment"><i class="far fa-trash-alt"></i></button></td>' +
                    '</tr>';
            }).join('');
            if (legacy > 0.005) {
                rows += '<tr class="is-legacy"><td class="ct-sp-date">—</td>' +
                    '<td class="ct-sp-amt">\u20B9 ' + money2(legacy) + '</td>' +
                    '<td colspan="4">Paid amount entered earlier without payment details</td></tr>';
            }
            $wrap.html(
                '<div class="table-responsive"><table class="table ct-sp-table"><thead><tr>' +
                '<th>Date</th><th class="text-right">Amount</th><th>Method</th><th>Reference / Txn No.</th><th>Notes</th><th></th>' +
                '</tr></thead><tbody>' + rows + '</tbody></table></div>'
            );
        }

        function spShowForm(show) {
            var $form = $('#ctSpForm');
            $('#ctSpError').text('');
            if (!show) {
                $form.addClass('d-none');
                $('#ctSpAddToggle').removeClass('d-none');
                return;
            }
            var $row = spFindRow(spState.uid);
            var f = $row.length ? spRowFigures($row) : { balance: 0 };
            spFillMethods();
            spSetDate(spTodayYmd());
            $('#ctSpAmount').val(f.balance > 0 ? money2(f.balance) : '');
            $('#ctSpReference, #ctSpNotes').val('');
            $form.removeClass('d-none');
            $('#ctSpAddToggle').addClass('d-none');
            window.setTimeout(function () { $('#ctSpAmount').trigger('focus').trigger('select'); }, 50);
        }

        function spApplyPaidDelta(uid, delta, payments) {
            var $row = spFindRow(uid);
            if (!$row.length) {
                return;
            }
            var paid = Math.max(0, Math.round((parseNum($row.find('.ct-paid').val()) + delta) * 100) / 100);
            $row.find('.ct-paid').val(amountInputValue(paid));
            $row.attr('data-payments', String(payments.length));
            recalcRowBalance($row);
            updatePrimaryStats();
        }

        function openPaymentModal($row, withForm) {
            if (!activeQuotationId || !$row.length) {
                return;
            }
            ensurePaymentModal();
            spSeq++;
            var seq = spSeq;
            spState.uid = String($row.attr('data-uid') || '');
            spState.key = String($row.attr('data-key') || '');
            spState.quotationId = activeQuotationId;
            spState.payments = [];
            spState.loaded = false;
            spState.busy = false;
            spShowForm(false);
            renderPaymentModal();
            $('#ctSvcPayModal').modal('show');
            if (withForm) {
                spShowForm(true);
            }

            $.getJSON('crm/ajax/service_payments.php', {
                action: 'list',
                quotation_id: spState.quotationId,
                service_uid: spState.uid
            })
                .done(function (res) {
                    if (seq !== spSeq) {
                        return;
                    }
                    if (!res || !res.success) {
                        showToast((res && res.message) || 'Could not load payments.', 'error');
                        return;
                    }
                    spState.payments = res.payments || [];
                    if (res.methods && res.methods.length) {
                        spState.methods = res.methods;
                        spFillMethods();
                    }
                    spState.loaded = true;
                    spFindRow(spState.uid).attr('data-payments', String(spState.payments.length));
                    updatePrimaryStats();
                    renderPaymentModal();
                })
                .fail(function () {
                    if (seq === spSeq) {
                        $('#ctSpHistory').html('<div class="ct-sp-empty text-danger"><i class="fas fa-exclamation-circle mr-1"></i>Could not load payments. Close and try again.</div>');
                    }
                });
        }

        function submitPayment() {
            if (spState.busy) {
                return;
            }
            var $row = spFindRow(spState.uid);
            if (!$row.length) {
                return;
            }
            var f = spRowFigures($row);
            var $err = $('#ctSpError').text('');
            var amount = Math.round(parseNum($('#ctSpAmount').val()) * 100) / 100;
            var date = spReadDate();
            var method = String($('#ctSpMethod').val() || '');

            if (f.total <= 0) {
                $err.text('Enter the Total amount for this service in the table first.');
                return;
            }
            if (!(amount > 0)) {
                $err.text('Enter a payment amount greater than 0.');
                $('#ctSpAmount').trigger('focus');
                return;
            }
            if (amount > f.balance + 0.005) {
                $err.text('Amount is more than the balance due (\u20B9 ' + money2(f.balance) + ').');
                $('#ctSpAmount').trigger('focus');
                return;
            }
            if (!date) {
                $err.text('Enter a valid payment date.');
                $('#ctSpDateText').trigger('focus');
                return;
            }
            if (date > spTodayYmd()) {
                $err.text('Payment date cannot be in the future.');
                return;
            }
            if (!method) {
                $err.text('Choose a payment method.');
                return;
            }

            var uid = spState.uid;
            var quotationId = spState.quotationId;
            var $btn = $('#ctSpSave').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving…');
            spState.busy = true;
            $.ajax({
                url: 'crm/ajax/service_payments.php',
                type: 'POST',
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: {
                    action: 'add',
                    quotation_id: quotationId,
                    service_uid: uid,
                    service_key: spState.key,
                    amount: amount.toFixed(2),
                    payment_date: date,
                    method: method,
                    reference: $.trim(String($('#ctSpReference').val() || '')),
                    notes: $.trim(String($('#ctSpNotes').val() || ''))
                }
            })
                .done(function (res) {
                    if (!res || !res.success) {
                        $err.text((res && res.message) || 'Could not save payment.');
                        return;
                    }
                    if (quotationId === activeQuotationId) {
                        spApplyPaidDelta(uid, parseFloat(res.amount) || amount, res.payments || []);
                    }
                    if (uid === spState.uid) {
                        spState.payments = res.payments || [];
                        spState.loaded = true;
                        spShowForm(false);
                        renderPaymentModal();
                    }
                    showToast(res.message || 'Payment recorded.', 'success');
                })
                .fail(function (xhr) {
                    $err.text((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Could not save payment. Please try again.');
                })
                .always(function () {
                    spState.busy = false;
                    $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Save Payment');
                    $('#ctSpAddToggle').prop('disabled', false);
                });
        }

        function deletePayment(p) {
            var uid = spState.uid;
            var quotationId = spState.quotationId;
            var $btn = $('#ctSpHistory tr[data-id="' + p.id + '"] .js-sp-del').prop('disabled', true);
            $.ajax({
                url: 'crm/ajax/service_payments.php',
                type: 'POST',
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: { action: 'delete', quotation_id: quotationId, service_uid: uid, payment_id: p.id }
            })
                .done(function (res) {
                    if (!res || !res.success) {
                        showToast((res && res.message) || 'Could not delete payment.', 'error');
                        $btn.prop('disabled', false);
                        return;
                    }
                    if (quotationId === activeQuotationId) {
                        spApplyPaidDelta(uid, -(parseFloat(res.amount) || 0), res.payments || []);
                    }
                    if (uid === spState.uid) {
                        spState.payments = res.payments || [];
                        renderPaymentModal();
                    }
                    showToast('Payment deleted.', 'success');
                })
                .fail(function () {
                    showToast('Could not delete payment. Please try again.', 'error');
                    $btn.prop('disabled', false);
                });
        }

        function bindPaymentEvents() {
            var $modal = $('#ctSvcPayModal');
            $modal.on('shown.bs.modal', function () {
                $('.modal-backdrop').last().css('z-index', 1075);
            });
            $modal.on('hidden.bs.modal', function () {
                spSeq++;
                spState.uid = '';
                if (spUseUiDate) {
                    $('#ctSpDateText').datepicker('hide');
                }
                if ($('#confirmTourModal').hasClass('show')) {
                    $('body').addClass('modal-open');
                }
            });
            $('#ctSpAddToggle').on('click', function () {
                spShowForm(true);
            });
            $('#ctSpCancel').on('click', function () {
                spShowForm(false);
            });
            $('#ctSpForm').on('submit', function (e) {
                e.preventDefault();
                submitPayment();
            });
            $('#ctSpAmount').on('blur', function () {
                var v = $.trim(String($(this).val() || ''));
                $(this).val(v === '' ? '' : money2(parseNum(v)));
            });
            $modal.on('click', '.js-sp-del', function () {
                var id = String($(this).closest('tr').attr('data-id'));
                var p = null;
                for (var i = 0; i < spState.payments.length; i++) {
                    if (String(spState.payments[i].id) === id) {
                        p = spState.payments[i];
                        break;
                    }
                }
                if (!p) {
                    return;
                }
                showConfirmDialog({
                    variant: 'danger',
                    icon: 'fa-trash-alt',
                    title: 'Delete payment?',
                    message: 'The payment of <strong>\u20B9 ' + money2(p.amount) + '</strong> on ' + esc(spFormatDate(p.payment_date)) +
                        ' will be removed and the Paid amount reduced. This cannot be undone.',
                    confirmText: 'Yes, Delete',
                    onConfirm: function () {
                        deletePayment(p);
                    }
                });
            });
        }

        var cpState = { quotationId: 0, payments: [], methods: SP_DEFAULT_METHODS.slice(), loaded: false, busy: false };
        var cpSeq = 0;
        var cpUseUiDate = false;

        function ensureCustomerPayModal() {
            if ($('#ctCustPayModal').length) {
                return;
            }
            ensurePayModalStyles();
            $('body').append(
                '<div class="modal fade ct-pay-modal" id="ctCustPayModal" tabindex="-1" role="dialog" aria-labelledby="ctCustPayTitle" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg" role="document"><div class="modal-content">' +
                '<div class="modal-header"><div class="ct-sp-head">' +
                '<span class="ct-svc-icon is-customer"><i class="fas fa-user"></i></span>' +
                '<div style="min-width:0"><h5 class="modal-title" id="ctCustPayTitle">Customer Payments</h5><div class="ct-sp-sub" id="ctCustPaySub"></div></div></div>' +
                '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>' +
                '<div class="modal-body">' +
                '<div class="ct-sp-cards">' +
                '<div class="ct-sp-card is-total"><div class="ct-sp-card-label">Total Amount</div><div class="ct-sp-card-val js-cp-total">—</div></div>' +
                '<div class="ct-sp-card is-paid"><div class="ct-sp-card-label">Paid Amount</div><div class="ct-sp-card-val js-cp-paid">—</div></div>' +
                '<div class="ct-sp-card is-due"><div class="ct-sp-card-label">Due Amount</div><div class="ct-sp-card-val js-cp-due">—</div></div>' +
                '<div class="ct-sp-card is-status"><div class="ct-sp-card-label">Payment Status</div><div class="ct-sp-card-val js-cp-status"></div></div>' +
                '</div>' +
                '<div class="ct-sp-progress"><div class="progress"><div class="progress-bar js-cp-bar" role="progressbar" style="width:0%"></div></div><span class="js-cp-pct">0% paid</span></div>' +
                '<div class="alert alert-warning py-2 px-3 mt-2 mb-0 small d-none js-cp-over"></div>' +
                '<div class="ct-sp-section">' +
                '<div class="ct-sp-section-head"><span class="ct-sp-section-title">Payments Received</span>' +
                '<button type="button" class="ct-sp-add-btn" id="ctCpAddToggle"><i class="fas fa-plus mr-1"></i>Add Payment</button></div>' +
                '<form class="ct-sp-form d-none" id="ctCpForm" novalidate autocomplete="off">' +
                '<div class="form-row">' +
                '<div class="form-group col-6 col-md-4"><label for="ctCpDateText">Payment Date <span class="text-danger">*</span></label>' +
                '<input type="text" class="form-control" id="ctCpDateText" placeholder="dd/mm/yyyy"></div>' +
                '<div class="form-group col-6 col-md-4"><label for="ctCpAmount">Amount <span class="text-danger">*</span></label>' +
                '<div class="input-group"><div class="input-group-prepend"><span class="input-group-text">\u20B9</span></div>' +
                '<input type="text" inputmode="decimal" class="form-control" id="ctCpAmount" placeholder="0.00"></div>' +
                '<div class="ct-sp-hint js-cp-amount-hint"></div></div>' +
                '<div class="form-group col-12 col-md-4"><label for="ctCpMethod">Payment Method <span class="text-danger">*</span></label>' +
                '<select class="form-control" id="ctCpMethod"></select></div>' +
                '<div class="form-group col-12 col-md-4"><label for="ctCpReference">Reference / Transaction No.</label>' +
                '<input type="text" class="form-control" id="ctCpReference" maxlength="120" placeholder="Optional"></div>' +
                '<div class="form-group col-12 col-md-8"><label for="ctCpNotes">Notes</label>' +
                '<textarea class="form-control" id="ctCpNotes" rows="2" maxlength="500" placeholder="Optional"></textarea></div>' +
                '</div>' +
                '<div class="ct-sp-form-actions"><span class="ct-sp-error" id="ctCpError"></span>' +
                '<button type="button" class="btn btn-light" id="ctCpCancel">Cancel</button>' +
                '<button type="submit" class="btn btn-success" id="ctCpSave"><i class="fas fa-check mr-1"></i>Save Payment</button></div>' +
                '</form>' +
                '<div class="ct-sp-history" id="ctCpHistory"></div>' +
                '</div>' +
                '</div>' +
                '<div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Close</button></div>' +
                '</div></div></div>'
            );

            cpUseUiDate = !!($.datepicker && $.fn.datepicker);
            if (cpUseUiDate) {
                $('#ctCpDateText').datepicker({
                    dateFormat: 'dd/mm/yy',
                    maxDate: 0,
                    beforeShow: function () {
                        window.setTimeout(function () { $('#ui-datepicker-div').css('z-index', 2200); }, 0);
                    }
                });
            } else {
                $('#ctCpDateText').attr({ type: 'date', placeholder: '' });
            }
            bindCustomerPayEvents();
        }

        function cpReadDate() {
            var raw = $.trim(String($('#ctCpDateText').val() || ''));
            if (!cpUseUiDate || !raw) {
                return raw;
            }
            try {
                var d = $.datepicker.parseDate('dd/mm/yy', raw);
                return d ? $.datepicker.formatDate('yy-mm-dd', d) : '';
            } catch (err) {
                return '';
            }
        }

        function cpSetDate(ymd) {
            if (cpUseUiDate) {
                var m = String(ymd).match(/^(\d{4})-(\d{2})-(\d{2})$/);
                $('#ctCpDateText').datepicker('setDate', m ? new Date(+m[1], +m[2] - 1, +m[3]) : null);
            } else {
                $('#ctCpDateText').val(ymd).attr('max', spTodayYmd());
            }
        }

        function cpFillMethods() {
            var $sel = $('#ctCpMethod');
            var current = $sel.val();
            $sel.html(cpState.methods.map(function (m) {
                return '<option value="' + esc(m) + '">' + esc(m) + '</option>';
            }).join(''));
            if (current && cpState.methods.indexOf(current) >= 0) {
                $sel.val(current);
            }
        }

        function cpApplyTotals(res) {
            var paid = parseFloat(res && res.paid_total);
            var pkg = parseFloat(res && res.package_total);
            if (!isNaN(paid)) {
                activeCustomerPaid = paid;
            }
            if (!isNaN(pkg)) {
                activePackageTotal = pkg;
            }
            updatePrimaryStats();
        }

        function renderCustomerPayModal() {
            var total = activePackageTotal || 0;
            var paid = activeCustomerPaid || 0;
            var due = customerDue();
            var excess = Math.round((paid - total) * 100) / 100;
            var pct = total > 0 ? Math.min(100, Math.round((paid / total) * 100)) : (paid > 0 ? 100 : 0);
            var guest = $.trim(String($('#ctGuestName').val() || ''));
            var $m = $('#ctCustPayModal');

            $('#ctCustPaySub').text(guest ? 'Primary Contact: ' + guest : 'Primary Contact');
            $m.find('.js-cp-total').text('\u20B9 ' + money2(total));
            $m.find('.js-cp-paid').text('\u20B9 ' + money2(paid));
            $m.find('.js-cp-due').text('\u20B9 ' + money2(due));
            $m.find('.js-cp-status').html(statusPillHtml(total, paid));
            $m.find('.js-cp-bar').css('width', pct + '%').attr('aria-valuenow', pct);
            $m.find('.js-cp-pct').text(pct + '% paid');
            $m.find('.js-cp-over').toggleClass('d-none', !(excess > 0.005))
                .html(excess > 0.005 ? '<i class="fas fa-info-circle mr-1"></i>Received <strong>\u20B9 ' + money2(excess) + '</strong> more than the Total Amount.' : '');
            $m.find('.js-cp-amount-hint').text('Due amount: \u20B9 ' + money2(due));
            $('#ctCpAddToggle').prop('disabled', cpState.busy);

            var $wrap = $('#ctCpHistory');
            if (!cpState.loaded) {
                $wrap.html('<div class="ct-sp-empty"><i class="fas fa-spinner fa-spin mr-1"></i>Loading payments…</div>');
                return;
            }
            if (!cpState.payments.length) {
                $wrap.html('<div class="ct-sp-empty"><i class="far fa-credit-card mr-1"></i>No payments received yet. Click <strong>Add Payment</strong> to record one.</div>');
                return;
            }
            var rows = cpState.payments.map(function (p) {
                return '<tr data-id="' + p.id + '">' +
                    '<td class="ct-sp-date">' + esc(spFormatDate(p.payment_date)) +
                    (p.created_by ? '<span class="ct-sp-by">by ' + esc(p.created_by) + '</span>' : '') + '</td>' +
                    '<td class="ct-sp-amt">\u20B9 ' + money2(p.amount) + '</td>' +
                    '<td><span class="ct-sp-method">' + esc(p.method || '—') + '</span></td>' +
                    '<td>' + (p.reference ? esc(p.reference) : '<span class="ct-sp-muted">—</span>') + '</td>' +
                    '<td class="ct-sp-notes">' + (p.notes ? esc(p.notes) : '<span class="ct-sp-muted">—</span>') + '</td>' +
                    '<td class="text-right"><button type="button" class="ct-sp-del js-cp-del" title="Delete payment" aria-label="Delete payment"><i class="far fa-trash-alt"></i></button></td>' +
                    '</tr>';
            }).join('');
            $wrap.html(
                '<div class="table-responsive"><table class="table ct-sp-table"><thead><tr>' +
                '<th>Date</th><th class="text-right">Amount</th><th>Method</th><th>Reference / Txn No.</th><th>Notes</th><th></th>' +
                '</tr></thead><tbody>' + rows + '</tbody></table></div>'
            );
        }

        function cpShowForm(show) {
            $('#ctCpError').text('');
            if (!show) {
                $('#ctCpForm').addClass('d-none');
                $('#ctCpAddToggle').removeClass('d-none');
                return;
            }
            var due = customerDue();
            cpFillMethods();
            cpSetDate(spTodayYmd());
            $('#ctCpAmount').val(due > 0 ? money2(due) : '');
            $('#ctCpReference, #ctCpNotes').val('');
            $('#ctCpForm').removeClass('d-none');
            $('#ctCpAddToggle').addClass('d-none');
            window.setTimeout(function () { $('#ctCpAmount').trigger('focus').trigger('select'); }, 50);
        }

        function openCustomerPayModal(withForm) {
            if (!activeQuotationId) {
                return;
            }
            ensureCustomerPayModal();
            cpSeq++;
            var seq = cpSeq;
            cpState.quotationId = activeQuotationId;
            cpState.payments = [];
            cpState.loaded = false;
            cpState.busy = false;
            cpShowForm(false);
            renderCustomerPayModal();
            $('#ctCustPayModal').modal('show');
            if (withForm) {
                cpShowForm(true);
            }
            $.getJSON('crm/ajax/customer_payments.php', { action: 'list', quotation_id: cpState.quotationId })
                .done(function (res) {
                    if (seq !== cpSeq) {
                        return;
                    }
                    if (!res || !res.success) {
                        showToast((res && res.message) || 'Could not load payments.', 'error');
                        return;
                    }
                    cpState.payments = res.payments || [];
                    if (res.methods && res.methods.length) {
                        cpState.methods = res.methods;
                        cpFillMethods();
                    }
                    cpState.loaded = true;
                    cpApplyTotals(res);
                    renderCustomerPayModal();
                })
                .fail(function () {
                    if (seq === cpSeq) {
                        $('#ctCpHistory').html('<div class="ct-sp-empty text-danger"><i class="fas fa-exclamation-circle mr-1"></i>Could not load payments. Close and try again.</div>');
                    }
                });
        }

        function submitCustomerPayment() {
            if (cpState.busy) {
                return;
            }
            var $err = $('#ctCpError').text('');
            var amount = Math.round(parseNum($('#ctCpAmount').val()) * 100) / 100;
            var date = cpReadDate();
            var method = String($('#ctCpMethod').val() || '');
            if (!(amount > 0)) {
                $err.text('Enter a payment amount greater than 0.');
                $('#ctCpAmount').trigger('focus');
                return;
            }
            if (!date) {
                $err.text('Enter a valid payment date.');
                $('#ctCpDateText').trigger('focus');
                return;
            }
            if (date > spTodayYmd()) {
                $err.text('Payment date cannot be in the future.');
                return;
            }
            if (!method) {
                $err.text('Choose a payment method.');
                return;
            }
            var quotationId = cpState.quotationId;
            var $btn = $('#ctCpSave').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving…');
            cpState.busy = true;
            $.ajax({
                url: 'crm/ajax/customer_payments.php',
                type: 'POST',
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: {
                    action: 'add',
                    quotation_id: quotationId,
                    amount: amount.toFixed(2),
                    payment_date: date,
                    method: method,
                    reference: $.trim(String($('#ctCpReference').val() || '')),
                    notes: $.trim(String($('#ctCpNotes').val() || ''))
                }
            })
                .done(function (res) {
                    if (!res || !res.success) {
                        $err.text((res && res.message) || 'Could not save payment.');
                        return;
                    }
                    if (quotationId !== activeQuotationId) {
                        return;
                    }
                    cpState.payments = res.payments || [];
                    cpState.loaded = true;
                    cpApplyTotals(res);
                    cpShowForm(false);
                    renderCustomerPayModal();
                    showToast(res.message || 'Payment received.', 'success');
                })
                .fail(function (xhr) {
                    $err.text((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Could not save payment. Please try again.');
                })
                .always(function () {
                    cpState.busy = false;
                    $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Save Payment');
                    $('#ctCpAddToggle').prop('disabled', false);
                });
        }

        function deleteCustomerPayment(p) {
            var quotationId = cpState.quotationId;
            var $btn = $('#ctCpHistory tr[data-id="' + p.id + '"] .js-cp-del').prop('disabled', true);
            $.ajax({
                url: 'crm/ajax/customer_payments.php',
                type: 'POST',
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                data: { action: 'delete', quotation_id: quotationId, payment_id: p.id }
            })
                .done(function (res) {
                    if (!res || !res.success) {
                        showToast((res && res.message) || 'Could not delete payment.', 'error');
                        $btn.prop('disabled', false);
                        return;
                    }
                    if (quotationId !== activeQuotationId) {
                        return;
                    }
                    cpState.payments = res.payments || [];
                    cpApplyTotals(res);
                    renderCustomerPayModal();
                    showToast('Payment deleted.', 'success');
                })
                .fail(function () {
                    showToast('Could not delete payment. Please try again.', 'error');
                    $btn.prop('disabled', false);
                });
        }

        function bindCustomerPayEvents() {
            var $modal = $('#ctCustPayModal');
            $modal.on('shown.bs.modal', function () {
                $('.modal-backdrop').last().css('z-index', 1075);
            });
            $modal.on('hidden.bs.modal', function () {
                cpSeq++;
                if (cpUseUiDate) {
                    $('#ctCpDateText').datepicker('hide');
                }
                if ($('#confirmTourModal').hasClass('show')) {
                    $('body').addClass('modal-open');
                }
            });
            $('#ctCpAddToggle').on('click', function () {
                cpShowForm(true);
            });
            $('#ctCpCancel').on('click', function () {
                cpShowForm(false);
            });
            $('#ctCpForm').on('submit', function (e) {
                e.preventDefault();
                submitCustomerPayment();
            });
            $('#ctCpAmount').on('blur', function () {
                var v = $.trim(String($(this).val() || ''));
                $(this).val(v === '' ? '' : money2(parseNum(v)));
            });
            $modal.on('click', '.js-cp-del', function () {
                var id = String($(this).closest('tr').attr('data-id'));
                var p = null;
                for (var i = 0; i < cpState.payments.length; i++) {
                    if (String(cpState.payments[i].id) === id) {
                        p = cpState.payments[i];
                        break;
                    }
                }
                if (!p) {
                    return;
                }
                showConfirmDialog({
                    variant: 'danger',
                    icon: 'fa-trash-alt',
                    title: 'Delete payment?',
                    message: 'The customer payment of <strong>\u20B9 ' + money2(p.amount) + '</strong> on ' + esc(spFormatDate(p.payment_date)) +
                        ' will be removed and the Paid Amount reduced. This cannot be undone.',
                    confirmText: 'Yes, Delete',
                    onConfirm: function () {
                        deleteCustomerPayment(p);
                    }
                });
            });
        }

        $(document).on('click', '#ctPrimaryCard .js-cust-pay-add', function (e) {
            e.stopPropagation();
            openCustomerPayModal(true);
        });

        $(document).on('click', '#ctPrimaryCard .ct-pstat.is-paid, #ctPrimaryCard .ct-pstat.is-due', function () {
            openCustomerPayModal(false);
        });

        $(document).on('click', '#ctDetailRows .ct-view-row', function () {
            openPaymentModal($(this).closest('.ct-detail-row'), false);
        });

        $(document).on('click', '#ctDetailRows .ct-pay-row', function () {
            var $row = $(this).closest('.ct-detail-row');
            openPaymentModal($row, spRowFigures($row).balance > 0);
        });

        $('#ctTravellerAttachBtn').on('click', function () {
            $('#ctTravellerAttachFile').trigger('click');
        });

        $('#ctTravellerAttachFile').on('change', function () {
            var input = this;
            var file = input.files && input.files[0] ? input.files[0] : null;
            if (!file) {
                return;
            }
            if (!activeQuotationId) {
                alert('Open a quotation before attaching files.');
                input.value = '';
                return;
            }
            var formData = new FormData();
            formData.append('quotation_id', String(activeQuotationId));
            formData.append('attachment', file);
            $('#ctTravellerAttachBtn').prop('disabled', true);
            $.ajax({
                url: 'crm/ajax/upload_quotation_confirm_attachment.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .done(function (res) {
                    if (!res || !res.success || !res.attachment) {
                        alert((res && res.message) || 'Could not upload attachment.');
                        return;
                    }
                    travellerDocsDraft.push({
                        name: res.attachment.original_name || file.name || '',
                        path: res.attachment.file_path || ''
                    });
                    updateTravellerDocsHint();
                })
                .fail(function () {
                    alert('Could not upload attachment. Please try again.');
                })
                .always(function () {
                    $('#ctTravellerAttachBtn').prop('disabled', false);
                    input.value = '';
                });
        });

        $('#ctTravellerSaveBtn').on('click', function () {
            var name = $.trim($('#ctTravellerName').val());
            if (!name) {
                alert('Guest name is required.');
                $('#ctTravellerName').trigger('focus');
                return;
            }
            if (scanState.xhr) {
                alert('Please wait until the document has been read.');
                return;
            }
            if (scanState.verifyRequired && !$('#ctScanVerify').is(':checked')) {
                var $verify = $('#ctScanVerifyWrap').removeClass('is-invalid');
                window.setTimeout(function () { $verify.addClass('is-invalid'); }, 10);
                $verify[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            var isPrimary = !!$('#ctTravellerModal').data('isPrimary');
            var details = readTravellerDetailFields();
            var ageRaw = $.trim($('#ctTravellerAge').val());
            if (ageRaw === '' && details.dob) {
                var dobAge = ageFromDob(details.dob);
                ageRaw = dobAge === null ? '' : String(dobAge);
            }
            var traveller = normalizeTraveller($.extend({
                id: $('#ctTravellerEditId').val() || scanState.draftId || uidTraveller(),
                name: name,
                type: $('#ctTravellerType').val() || 'adult',
                age: ageRaw === '' ? null : ageRaw,
                passport_number: $('#ctTravellerPassport').val(),
                passport_expiry: $('#ctTravellerPassportExpiry').val(),
                relation: isPrimary ? 'Self' : $('#ctTravellerRelation').val(),
                mobile: $('#ctTravellerMobile').val(),
                email: $('#ctTravellerEmail').val(),
                documents: travellerDocsDraft
            }, details));
            var idx = findTravellerIndex(traveller.id);
            var key = travellerNameKey(name);
            var duplicate = travellersState.some(function (t, i) {
                return i !== idx && travellerNameKey(t.name) === key;
            });
            if (duplicate) {
                alert('"' + name + '" is already in the travellers list.');
                $('#ctTravellerName').trigger('focus');
                return;
            }
            if (scanState.summary && String(traveller.id) === String(scanState.summaryFor)) {
                traveller.documents = scanState.summary;
            }
            if (idx >= 0) {
                travellersState[idx] = traveller;
            } else {
                travellersState.push(traveller);
            }
            if (travellersState[0] && travellersState[0].id === traveller.id) {
                setPrimaryContact(
                    traveller.name,
                    traveller.mobile || $('#ctMobileNo').val(),
                    traveller.email || $('#ctEmail').val()
                );
            }
            scanState.saved = true;
            renderTravellers();
            persistTravellers();
            $('#ctTravellerModal').modal('hide');
        });

        $('#ctSaveBtn').on('click', function () {
            var guest = $.trim($('#ctGuestName').val());
            if (!guest && travellersState[0] && travellersState[0].name) {
                guest = travellersState[0].name;
                setPrimaryContact(guest, $('#ctMobileNo').val(), $('#ctEmail').val());
            }
            if (!guest) {
                alert('Add at least one traveller in Travellers (PAX).');
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
                    email: $('#ctEmail').val(),
                    guest_attachment_name: $('#ctGuestAttachmentName').val(),
                    guest_attachment_path: $('#ctGuestAttachmentPath').val(),
                    travellers_json: JSON.stringify(travellersState),
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
