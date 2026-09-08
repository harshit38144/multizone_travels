<style>
    .page-bg { background: #f4f6f9; }
    .card-header-pay {
        background: linear-gradient(135deg, #1e3a5f 0%, #3498db 55%, #2ecc71 100%);
        color: #fff;
        padding: 16px 24px;
        border-radius: 12px 12px 0 0;
    }
    .main-card { border: none; border-radius: 12px; box-shadow: 0 2px 15px rgba(0,0,0,0.06); }
    .link-box {
        background: #f0f7ff;
        border: 1px dashed #3498db;
        border-radius: 10px;
        padding: 1rem;
        word-break: break-all;
    }
    .copy-btn { cursor: pointer; }
    .badge-paid { background: #198754; color: #fff; }
    .badge-active { background: #0d6efd; color: #fff; }
    .badge-cancelled { background: #6c757d; color: #fff; }
    .badge-gateway-phonepe { background: #5f259f; color: #fff; }
    .badge-gateway-payu { background: #0d9488; color: #fff; }
    .btn-add-pay {
        background: #fff;
        color: #1e3a5f;
        border: none;
        border-radius: 6px;
        padding: 6px 16px;
        font-weight: 600;
        font-size: 14px;
    }
    .btn-add-pay:hover { background: #f0f7ff; color: #1e3a5f; }

    /* ===== Payment Links list page ===== */
    .pay-links-page {
        padding-top: 1.25rem;
        padding-bottom: 2rem;
    }
    .pay-links-page-hd {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
        flex-wrap: wrap;
    }
    .pay-links-page-hd-left {
        display: flex;
        align-items: center;
        gap: 0.85rem;
    }
    .pay-links-page-icon {
        width: 2.75rem;
        height: 2.75rem;
        border-radius: 12px;
        background: #ffe4e6;
        color: #c41e20;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex: 0 0 auto;
    }
    .pay-links-page-title {
        margin: 0;
        font-size: 1.55rem;
        font-weight: 800;
        color: #111827;
        line-height: 1.2;
    }
    .pay-links-page-sub {
        margin-top: 0.2rem;
        color: #6b7280;
        font-size: 0.9rem;
    }
    .pay-links-breadcrumb {
        background: transparent;
        padding: 0;
        font-size: 0.875rem;
    }
    .pay-links-breadcrumb .breadcrumb-item + .breadcrumb-item::before {
        color: #9ca3af;
    }
    .pay-links-breadcrumb a {
        color: #2563eb;
    }
    .pay-links-breadcrumb .active {
        color: #6b7280;
    }
    .pay-links-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
        margin-bottom: 1.15rem;
    }
    .pay-stat-card {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 1rem 1.1rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .pay-stat-card-active {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }
    .pay-stat-icon {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }
    .pay-stat-icon-total {
        background: #f3f4f6;
        color: #4b5563;
    }
    .pay-stat-icon-active {
        background: #dcfce7;
        color: #16a34a;
    }
    .pay-stat-icon-paid {
        background: #dbeafe;
        color: #2563eb;
    }
    .pay-stat-label {
        font-size: 0.8rem;
        color: #6b7280;
        font-weight: 600;
    }
    .pay-stat-value {
        font-size: 1.55rem;
        font-weight: 800;
        color: #111827;
        line-height: 1.15;
    }
    .pay-stat-hint {
        font-size: 0.78rem;
        color: #9ca3af;
    }
    .pay-links-toolbar {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    .pay-links-search {
        position: relative;
        flex: 1 1 280px;
        min-width: 220px;
    }
    .pay-links-search > i {
        position: absolute;
        left: 0.9rem;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        pointer-events: none;
    }
    .pay-links-search .form-control {
        padding-left: 2.35rem;
        border-radius: 10px;
        border-color: #d1d5db;
        min-height: 42px;
        background: #fff;
    }
    .pay-links-search .form-control:focus {
        border-color: #c41e20;
        box-shadow: 0 0 0 0.15rem rgba(196, 30, 32, 0.12);
    }
    .pay-links-filter-btn {
        border: 1px solid #d1d5db;
        background: #fff;
        color: #374151;
        border-radius: 10px;
        min-height: 42px;
        font-weight: 600;
        padding: 0.45rem 0.9rem;
    }
    .pay-links-create-btn {
        background: #c41e20;
        border-color: #c41e20;
        color: #fff !important;
        border-radius: 10px;
        min-height: 42px;
        font-weight: 700;
        padding: 0.5rem 1rem;
        box-shadow: 0 6px 14px rgba(196, 30, 32, 0.2);
        white-space: nowrap;
    }
    .pay-links-create-btn:hover {
        background: #a8181a;
        border-color: #a8181a;
        color: #fff !important;
    }
    .pay-links-table-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .pay-links-table {
        margin-bottom: 0;
    }
    .pay-links-table thead th {
        background: #f9fafb;
        border-top: 0;
        border-bottom: 1px solid #e5e7eb;
        color: #6b7280;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 0.85rem 1rem;
        white-space: nowrap;
    }
    .pay-links-table tbody td {
        vertical-align: middle;
        border-top: 1px solid #f1f5f9;
        padding: 0.95rem 1rem;
        color: #374151;
        font-size: 0.9rem;
    }
    .pay-customer-cell {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        min-width: 180px;
    }
    .pay-avatar {
        width: 2.15rem;
        height: 2.15rem;
        border-radius: 999px;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.85rem;
        flex: 0 0 auto;
    }
    .pay-customer-meta {
        display: flex;
        flex-direction: column;
        min-width: 0;
        line-height: 1.25;
    }
    .pay-customer-meta strong {
        color: #111827;
        font-size: 0.92rem;
    }
    .pay-customer-meta small {
        color: #9ca3af;
        font-size: 0.78rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 220px;
    }
    .pay-gateway-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.28rem 0.65rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
        color: #fff;
        white-space: nowrap;
    }
    .pay-gateway-phonepe { background: #5f259f; }
    .pay-gateway-payu { background: #0d9488; }
    .pay-remarks-cell {
        max-width: 180px;
        color: #6b7280;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .pay-amount-cell {
        font-weight: 800;
        color: #111827;
        white-space: nowrap;
    }
    .pay-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.28rem 0.7rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .pay-status-dot {
        width: 0.45rem;
        height: 0.45rem;
        border-radius: 50%;
        background: currentColor;
    }
    .pay-status-pill.is-active {
        background: #dcfce7;
        color: #15803d;
    }
    .pay-status-pill.is-paid {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .pay-status-pill.is-cancelled {
        background: #fee2e2;
        color: #b91c1c;
    }
    .pay-date-cell {
        color: #6b7280;
        white-space: nowrap;
        font-size: 0.84rem;
    }
    .pay-actions {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .pay-action-btn {
        width: 2rem;
        height: 2rem;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #6b7280;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        line-height: 1;
    }
    .pay-action-btn:hover {
        background: #f9fafb;
        color: #111827;
        text-decoration: none;
    }
    .pay-action-btn.is-danger {
        color: #dc2626;
    }
    .pay-action-btn.is-danger:hover {
        background: #fef2f2;
        color: #b91c1c;
    }
    .pay-links-table-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding: 0.85rem 1rem;
        border-top: 1px solid #eef2f7;
        background: #fff;
    }
    .pay-links-showing {
        color: #9ca3af;
        font-size: 0.84rem;
    }
    .pay-links-pager {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .pay-page-btn {
        min-width: 2rem;
        height: 2rem;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #6b7280;
        font-weight: 700;
        font-size: 0.85rem;
        padding: 0 0.55rem;
    }
    .pay-page-btn.is-active {
        background: #c41e20;
        border-color: #c41e20;
        color: #fff;
    }
    .pay-page-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }
    @media (max-width: 991.98px) {
        .pay-links-stats {
            grid-template-columns: 1fr;
        }
    }

    .gateway-radio-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    .gateway-radio-card {
        position: relative;
        flex: 1 1 240px;
        max-width: 100%;
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 0.75rem;
        padding: 0.95rem 1rem 0.95rem 2.85rem;
        border: 1.5px solid #d8dee6;
        border-radius: 12px;
        background: #fff;
        cursor: pointer;
        transition: border-color .15s, box-shadow .15s, background .15s;
        margin-bottom: 0;
    }
    .gateway-radio-card:hover {
        border-color: #b8c0cc;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
    }
    .gateway-radio-card.is-selected,
    .gateway-radio-card:has(input:checked) {
        border-color: #c41e20;
        background: #fff5f5;
        box-shadow: 0 0 0 3px rgba(196, 30, 32, 0.08);
    }
    .gateway-radio-card.gateway-radio-phonepe.is-selected,
    .gateway-radio-card.gateway-radio-phonepe:has(input:checked),
    .gateway-radio-card.gateway-radio-payu.is-selected,
    .gateway-radio-card.gateway-radio-payu:has(input:checked) {
        border-color: #c41e20;
        background: #fff5f5;
        box-shadow: 0 0 0 3px rgba(196, 30, 32, 0.08);
    }
    .gateway-radio-card input[type="radio"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .gateway-radio-check {
        position: absolute;
        top: 50%;
        left: 0.95rem;
        width: 1.1rem;
        height: 1.1rem;
        margin-top: -0.55rem;
        border: 2px solid #c5ccd6;
        border-radius: 50%;
        background: #fff;
    }
    .gateway-radio-card:has(input:checked) .gateway-radio-check {
        border-color: #c41e20;
        background: #c41e20;
        box-shadow: inset 0 0 0 3px #fff;
    }
    .gateway-radio-icon {
        width: 2rem;
        height: 2rem;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f3f4f6;
        color: #4b5563;
        font-size: 0.95rem;
        flex: 0 0 auto;
    }
    .gateway-radio-card:has(input:checked) .gateway-radio-icon {
        background: #ffe4e4;
        color: #c41e20;
    }
    .gateway-radio-copy {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
        min-width: 0;
    }
    .gateway-radio-title {
        font-weight: 700;
        color: #1f2937;
        line-height: 1.2;
        font-size: 0.95rem;
    }
    .gateway-radio-hint {
        font-size: 0.78rem;
        color: #6b7280;
        line-height: 1.3;
    }

    .pay-link-create-ui .pay-link-form-panel {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 1.15rem 1.2rem 1.25rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .pay-link-create-ui .pay-link-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.4rem;
    }
    .pay-link-create-ui .pay-label-optional {
        color: #9ca3af;
        font-weight: 500;
    }
    .pay-link-create-ui .pay-input-icon-wrap {
        position: relative;
    }
    .pay-link-create-ui .pay-input-icon {
        position: absolute;
        left: 0.85rem;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        font-size: 0.9rem;
        z-index: 2;
        pointer-events: none;
    }
    .pay-link-create-ui .pay-input-icon-wrap-textarea .pay-input-icon {
        top: 0.85rem;
        transform: none;
    }
    .pay-link-create-ui .pay-input-icon-rupee {
        font-weight: 700;
        font-style: normal;
        color: #c41e20;
    }
    .pay-link-create-ui .pay-input-iconed {
        padding-left: 2.35rem;
        border-radius: 10px;
        border-color: #d1d5db;
        min-height: 42px;
        box-shadow: none;
    }
    .pay-link-create-ui textarea.pay-input-iconed {
        min-height: 110px;
        padding-top: 0.7rem;
        resize: vertical;
    }
    .pay-link-create-ui .pay-input-iconed:focus {
        border-color: #c41e20;
        box-shadow: 0 0 0 0.15rem rgba(196, 30, 32, 0.15);
    }
    .pay-link-create-ui .pay-contacts-hint {
        display: block;
        margin-top: 0.35rem;
        font-size: 0.78rem;
        color: #6b7280;
    }
    .pay-link-create-ui .pay-contacts-hint a {
        color: #c41e20;
        font-weight: 600;
    }
    .pay-link-create-ui .pay-amount-panel {
        height: 100%;
        background: #fff5f5;
        border: 1px solid #f3c6c6;
        border-radius: 12px;
        padding: 0.85rem 0.9rem 1rem;
    }
    .pay-link-create-ui .pay-link-form-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        margin-top: 1rem;
    }
    .pay-link-create-ui .pay-btn-back {
        background: #fff;
        border: 1px solid #d1d5db;
        color: #4b5563;
        border-radius: 10px;
        font-weight: 600;
        padding: 0.55rem 1rem;
    }
    .pay-link-create-ui .pay-btn-back:hover {
        background: #f9fafb;
        color: #111827;
        border-color: #9ca3af;
    }
    .pay-link-create-ui .pay-btn-create {
        background: #c41e20;
        border-color: #c41e20;
        color: #fff;
        border-radius: 10px;
        font-weight: 700;
        padding: 0.6rem 1.15rem;
        box-shadow: 0 6px 14px rgba(196, 30, 32, 0.22);
    }
    .pay-link-create-ui .pay-btn-create:hover {
        background: #a8181a;
        border-color: #a8181a;
        color: #fff;
    }

    .pay-contact-combobox { position: relative; }
    .pay-contact-menu {
        position: absolute;
        top: calc(100% + 2px);
        left: 0;
        right: 0;
        z-index: 1060;
        max-height: 220px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
    }
    .pay-contact-item {
        display: block;
        width: 100%;
        padding: 0.45rem 0.65rem;
        border: 0;
        background: transparent;
        color: #334155;
        text-align: left;
        cursor: pointer;
    }
    .pay-contact-item:hover,
    .pay-contact-item:focus {
        background: #f1f5f9;
        outline: none;
    }
    .pay-contact-item-title {
        display: block;
        font-weight: 600;
        font-size: 0.8125rem;
    }
    .pay-contact-item-meta {
        display: block;
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 0.1rem;
    }
    .pay-contact-empty {
        padding: 0.55rem 0.65rem;
        font-size: 0.78rem;
        color: #64748b;
    }

    #payLinkCreateModal .modal-dialog {
        max-width: min(920px, 96vw);
        margin: 1.25rem auto;
    }
    #payLinkCreateModal .modal-content {
        border: 0;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 18px 48px rgba(15, 23, 42, 0.22);
        background: #f7f8fa;
    }
    #payLinkCreateModal .modal-header {
        background: #c41e20;
        color: #fff;
        border-bottom: 0;
        padding: 0.95rem 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    #payLinkCreateModal .modal-header .modal-title {
        font-size: 1.08rem;
        font-weight: 700;
        letter-spacing: 0.01em;
    }
    #payLinkCreateModal .modal-header .close {
        color: #fff;
        text-shadow: none;
        opacity: 0.9;
        margin: -0.4rem -0.2rem -0.4rem 0.75rem;
        padding: 0.4rem;
        order: 3;
    }
    #payLinkCreateModal .pay-link-modal-hd {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex: 1 1 auto;
        min-width: 0;
        gap: 1rem;
    }
    #payLinkCreateModal .pay-link-modal-sub {
        font-size: 0.82rem;
        opacity: 0.92;
        font-weight: 500;
        white-space: nowrap;
    }
    #payLinkCreateModal .modal-body {
        padding: 1.15rem 1.2rem 1.25rem;
        max-height: calc(100vh - 7.5rem);
        overflow-y: auto;
        background: #f7f8fa;
    }
    #payLinkCreateModal .pay-link-create-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.55rem;
        min-height: 180px;
        color: #64748b;
        font-weight: 600;
    }
    @media (max-width: 767.98px) {
        #payLinkCreateModal .pay-link-modal-sub {
            display: none;
        }
        .pay-link-create-ui .pay-amount-panel {
            margin-top: 0.75rem;
        }
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function escHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    var contactLookupTimer = null;
    var contactLookupSeq = 0;
    var contactLookupCache = {};

    function hideAllContactMenus(scope) {
        var root = scope || document;
        root.querySelectorAll('.js-pay-contact-menu').forEach(function (menu) {
            menu.style.display = 'none';
            menu.innerHTML = '';
        });
    }

    function applyContactSuggestion(contact, scope) {
        if (!contact) return;
        var root = scope || document;
        var nameInput = root.querySelector('input[name="name"]');
        var emailInput = root.querySelector('input[name="email"]');
        var mobileInput = root.querySelector('input[name="mobile"]');
        if (nameInput) nameInput.value = contact.name || '';
        if (emailInput) emailInput.value = contact.email || '';
        if (mobileInput) mobileInput.value = contact.mobile || '';
    }

    function renderContactMenu(menu, items, query, formRoot) {
        menu.innerHTML = '';
        if (!items || !items.length) {
            menu.innerHTML = '<div class="pay-contact-empty">No contacts found' + (query ? ' for "' + escHtml(query) + '"' : '') + '</div>';
        } else {
            items.forEach(function (item) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pay-contact-item';
                btn.innerHTML = '<span class="pay-contact-item-title">' + escHtml(item.label || item.name || 'Contact') + '</span>'
                    + (item.sub_label ? '<span class="pay-contact-item-meta">' + escHtml(item.sub_label) + '</span>' : '');
                btn.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    hideAllContactMenus(formRoot);
                    applyContactSuggestion(item, formRoot);
                });
                menu.appendChild(btn);
            });
        }
        menu.style.display = 'block';
    }

    function resolveContactSearchUrl(input) {
        var embed = input.closest('.pay-link-create-embed');
        if (embed && embed.getAttribute('data-pay-contact-search-url')) {
            return embed.getAttribute('data-pay-contact-search-url');
        }
        var modal = input.closest('#payLinkCreateModal');
        if (modal && modal.getAttribute('data-pay-contact-search-url')) {
            return modal.getAttribute('data-pay-contact-search-url');
        }
        return document.body.getAttribute('data-pay-contact-search-url') || 'ajax/search_contacts_for_payment.php';
    }

    function searchContactsForPayment(query, searchUrl, callback) {
        var q = (query || '').trim();
        if (q.length < 2) {
            callback([]);
            return;
        }
        if (contactLookupCache[q]) {
            callback(contactLookupCache[q]);
            return;
        }
        var seq = ++contactLookupSeq;
        fetch(searchUrl + '?q=' + encodeURIComponent(q) + '&limit=10', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (res) { return res.json(); })
            .then(function (res) {
                if (seq !== contactLookupSeq) return;
                var items = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                contactLookupCache[q] = items;
                callback(items);
            })
            .catch(function () {
                if (seq !== contactLookupSeq) return;
                callback([]);
            });
    }

    function runContactLookup(input) {
        var combobox = input.closest('.pay-contact-combobox');
        if (!combobox) return;
        var menu = combobox.querySelector('.js-pay-contact-menu');
        if (!menu) return;
        var formRoot = input.closest('form') || document;
        var query = (input.value || '').trim();
        hideAllContactMenus(formRoot);
        if (query.length < 2) return;
        clearTimeout(contactLookupTimer);
        contactLookupTimer = setTimeout(function () {
            searchContactsForPayment(query, resolveContactSearchUrl(input), function (items) {
                renderContactMenu(menu, items, query, formRoot);
            });
        }, 280);
    }

    document.addEventListener('input', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('js-pay-contact-lookup')) {
            runContactLookup(e.target);
        }
    });
    document.addEventListener('click', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('js-pay-contact-lookup')) {
            runContactLookup(e.target);
        }
    });
    document.addEventListener('focusin', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('js-pay-contact-lookup')) {
            runContactLookup(e.target);
        }
    });
    document.addEventListener('focusout', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('js-pay-contact-lookup')) {
            setTimeout(function () {
                var formRoot = e.target.closest('form') || document;
                hideAllContactMenus(formRoot);
            }, 180);
        }
    });

    document.addEventListener('change', function (e) {
        if (!e.target || e.target.type !== 'radio' || e.target.name !== 'payment_gateway') {
            return;
        }
        var group = e.target.closest('.gateway-radio-group') || document;
        group.querySelectorAll('.gateway-radio-card').forEach(function (card) {
            card.classList.remove('is-selected');
        });
        if (e.target.checked && e.target.closest('.gateway-radio-card')) {
            e.target.closest('.gateway-radio-card').classList.add('is-selected');
        }
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.copy-btn');
        if (!btn) return;
        var text = btn.getAttribute('data-copy') || '';
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                alert('Link copied to clipboard.');
            });
        } else {
            var ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            alert('Link copied to clipboard.');
        }
    });
});
</script>
