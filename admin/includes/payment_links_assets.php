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

    .gateway-radio-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    .gateway-radio-card {
        position: relative;
        flex: 1 1 220px;
        max-width: 280px;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.2rem;
        padding: 0.9rem 1rem 0.9rem 3.25rem;
        border: 2px solid #d1d5db;
        border-radius: 10px;
        background: #fff;
        cursor: pointer;
        transition: border-color .15s, box-shadow .15s, background .15s;
        margin-bottom: 0;
    }
    .gateway-radio-card:hover {
        border-color: #94a3b8;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
    }
    .gateway-radio-card.is-selected,
    .gateway-radio-card:has(input:checked) {
        border-color: #2563eb;
        background: #f8fbff;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .gateway-radio-card.gateway-radio-phonepe.is-selected,
    .gateway-radio-card.gateway-radio-phonepe:has(input:checked) {
        border-color: #5f259f;
        background: #faf5ff;
        box-shadow: 0 0 0 3px rgba(95, 37, 159, 0.12);
    }
    .gateway-radio-card.gateway-radio-payu.is-selected,
    .gateway-radio-card.gateway-radio-payu:has(input:checked) {
        border-color: #0d9488;
        background: #f0fdfa;
        box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
    }
    .gateway-radio-card input[type="radio"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .gateway-radio-check {
        position: absolute;
        top: 0.85rem;
        left: 0.85rem;
        width: 1.15rem;
        height: 1.15rem;
        border: 2px solid #cbd5e1;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.55rem;
        color: transparent;
        background: #fff;
    }
    .gateway-radio-card:has(input:checked) .gateway-radio-check {
        border-color: currentColor;
        color: #fff;
        background: #2563eb;
    }
    .gateway-radio-card.gateway-radio-phonepe:has(input:checked) .gateway-radio-check {
        background: #5f259f;
        border-color: #5f259f;
    }
    .gateway-radio-card.gateway-radio-payu:has(input:checked) .gateway-radio-check {
        background: #0d9488;
        border-color: #0d9488;
    }
    .gateway-radio-icon {
        font-size: 1.1rem;
        color: #475569;
        margin-bottom: 0.15rem;
    }
    .gateway-radio-title {
        font-weight: 700;
        color: #1e293b;
        line-height: 1.2;
    }
    .gateway-radio-hint {
        font-size: 0.78rem;
        color: #64748b;
        line-height: 1.3;
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
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function escHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    var contactLookupTimer = null;
    var contactLookupSeq = 0;
    var contactLookupCache = {};

    function hideAllContactMenus() {
        document.querySelectorAll('.js-pay-contact-menu').forEach(function (menu) {
            menu.style.display = 'none';
            menu.innerHTML = '';
        });
    }

    function applyContactSuggestion(contact) {
        if (!contact) return;
        var nameInput = document.querySelector('input[name="name"]');
        var emailInput = document.querySelector('input[name="email"]');
        var mobileInput = document.querySelector('input[name="mobile"]');
        if (nameInput) nameInput.value = contact.name || '';
        if (emailInput) emailInput.value = contact.email || '';
        if (mobileInput) mobileInput.value = contact.mobile || '';
    }

    function renderContactMenu(menu, items, query) {
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
                    hideAllContactMenus();
                    applyContactSuggestion(item);
                });
                menu.appendChild(btn);
            });
        }
        menu.style.display = 'block';
    }

    function searchContactsForPayment(query, callback) {
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
        var searchUrl = document.body.getAttribute('data-pay-contact-search-url') || 'ajax/search_contacts_for_payment.php';
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
        var query = (input.value || '').trim();
        hideAllContactMenus();
        if (query.length < 2) return;
        clearTimeout(contactLookupTimer);
        contactLookupTimer = setTimeout(function () {
            searchContactsForPayment(query, function (items) {
                renderContactMenu(menu, items, query);
            });
        }, 280);
    }

    document.querySelectorAll('.js-pay-contact-lookup').forEach(function (input) {
        input.addEventListener('input', function () {
            runContactLookup(input);
        });
        input.addEventListener('click', function () {
            runContactLookup(input);
        });
        input.addEventListener('blur', function () {
            setTimeout(hideAllContactMenus, 180);
        });
    });

    document.querySelectorAll('.gateway-radio-card input[type="radio"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.gateway-radio-card').forEach(function (card) {
                card.classList.remove('is-selected');
            });
            if (radio.checked && radio.closest('.gateway-radio-card')) {
                radio.closest('.gateway-radio-card').classList.add('is-selected');
            }
        });
    });

document.querySelectorAll('.copy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
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
});
</script>
