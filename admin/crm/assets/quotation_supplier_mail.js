(function () {
    'use strict';

    var catalog = [];
    var destinationNameToId = {};
    var mailTemplate = { subject: '', body_html: '', meta: {} };
    var editorReady = false;
    var supplierList = [];
    var selectedSuppliers = [];
    var isSending = false;
    var skipComposeReset = false;
    var statusRecipients = [];
    var pendingSendPayload = null;

    var MONTH_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var MONTH_LONG = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    function pad2(n) {
        n = parseInt(n, 10) || 0;
        return (n < 10 ? '0' : '') + n;
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function parseTravelDateParts(value) {
        var v = String(value || '').trim();
        if (!v) {
            return { short: '', long: '' };
        }
        var d = null;
        var dmy = v.match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
        var iso = v.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (dmy) {
            d = new Date(parseInt(dmy[3], 10), parseInt(dmy[2], 10) - 1, parseInt(dmy[1], 10));
        } else if (iso) {
            d = new Date(parseInt(iso[1], 10), parseInt(iso[2], 10) - 1, parseInt(iso[3], 10));
        } else {
            var parsed = Date.parse(v);
            if (!isNaN(parsed)) {
                d = new Date(parsed);
            }
        }
        if (!d || isNaN(d.getTime())) {
            return { short: '', long: '' };
        }
        var day = pad2(d.getDate());
        var mi = d.getMonth();
        return {
            short: day + ' ' + MONTH_SHORT[mi],
            long: day + ' ' + MONTH_LONG[mi]
        };
    }

    function formatHotelStars(categories) {
        if (!Array.isArray(categories)) {
            categories = categories ? [categories] : [];
        }
        var stars = [];
        categories.forEach(function (cat) {
            cat = String(cat || '').trim();
            if (!cat) {
                return;
            }
            var m = cat.match(/(\d+)/);
            var label = m ? (m[1] + '★') : cat;
            if (stars.indexOf(label) < 0) {
                stars.push(label);
            }
        });
        return stars.length ? stars.join(', ') : '—';
    }

    function splitDestinations(text) {
        return String(text || '')
            .split(/[,;|]+/)
            .map(function (part) { return part.trim(); })
            .filter(function (part) { return part && part !== '—'; });
    }

    function buildSupplierMailContent() {
        var meta = mailTemplate.meta || {};
        var initial = String(meta.guest_initial || '').trim();
        var guestName = String($('input[name="guest_name"]').val() || meta.guest_name || '').trim();
        var guestLabel = [initial, guestName].filter(Boolean).join(' ') || 'Guest';

        var adults = parseInt(meta.adults, 10);
        var children = parseInt(meta.children, 10);
        if (isNaN(adults) || adults < 0) {
            adults = 0;
        }
        if (isNaN(children) || children < 0) {
            children = 0;
        }
        if (adults < 1 && children < 1) {
            adults = 1;
        }
        var paxCount = adults > 0 ? adults : Math.max(1, children);

        var destinationText = String($('input[name="destination"]').val() || meta.destination || '').trim();
        var destinations = splitDestinations(destinationText);
        if (!destinations.length && Array.isArray(meta.destinations)) {
            destinations = meta.destinations.slice();
        }
        var destLabel = destinations.length ? destinations[0] : '';
        var cities = destinations.length || parseInt(meta.cities, 10) || 0;

        var nights = parseInt($('input[name="no_of_nights"]').val(), 10);
        if (isNaN(nights) || nights < 0) {
            nights = parseInt(meta.nights, 10) || 0;
        }

        var dates = parseTravelDateParts(meta.travel_date);
        var hotelLabel = meta.hotel_label || formatHotelStars(meta.hotel_categories);

        var subjectParts = [guestLabel + '*' + pad2(paxCount)];
        var destNights = (destLabel + (nights > 0 ? ' ' + pad2(nights) + ' Nts' : '')).trim();
        if (destNights) {
            subjectParts.push(destNights);
        }
        if (dates.short) {
            subjectParts.push(dates.short);
        }

        var paxLine = pad2(paxCount) + ' Adult' + (paxCount === 1 ? '' : 's');
        if (children > 0 && adults > 0) {
            paxLine = pad2(adults) + ' Adult' + (adults === 1 ? '' : 's')
                + ' + ' + pad2(children) + ' Child' + (children === 1 ? '' : 'ren');
        } else if (children > 0 && adults < 1) {
            paxLine = pad2(children) + ' Child' + (children === 1 ? '' : 'ren');
        }

        var duration = '—';
        if (nights > 0) {
            duration = pad2(nights) + '–' + pad2(nights + 1) + ' Nights';
            if (cities > 0) {
                duration += ' (' + pad2(cities) + ' ' + (cities === 1 ? 'City' : 'Cities') + ')';
            }
        }

        var lines = [
            'Dear Team,',
            '',
            'Kindly quote your best B2B rates for the below requirement:',
            '',
            'Date of Travel (DOT): ' + (dates.long || '—'),
            'Duration: ' + duration,
            'Pax: ' + paxLine,
            'Hotel Category: ' + (hotelLabel || '—'),
            '',
            'Kindly share the suggested itinerary, hotel options, inclusions, exclusions, and your best possible costing at the earliest.',
            '',
            'Looking forward to your prompt response.',
            '',
            'Warm regards'
        ];

        var bodyHtml = '<div style="line-height:1.45;margin:0;padding:0;">' + lines.map(function (line) {
            return line === '' ? '<br>' : escapeHtml(line);
        }).join('<br>') + '</div>';

        return {
            subject: subjectParts.join(' | '),
            body_html: bodyHtml
        };
    }

    function applySupplierMailTemplate() {
        var content = buildSupplierMailContent();
        $('#qSupplierMailSubject').val(content.subject);
        if (editorReady && $.fn.summernote) {
            $('#qSupplierMailBody').summernote('code', content.body_html);
        } else {
            $('#qSupplierMailBody').val(content.body_html);
        }
    }

    function parseDestinationParts(text) {
        return String(text || '')
            .split(/[,;|]+/)
            .map(function (part) { return part.trim(); })
            .filter(Boolean);
    }

    function resolveDestinationId(name) {
        var key = String(name || '').trim().toLowerCase();
        if (!key || !Object.prototype.hasOwnProperty.call(destinationNameToId, key)) {
            return 0;
        }
        return parseInt(destinationNameToId[key], 10) || 0;
    }

    function destinationPlacesFromText(destinationText) {
        return parseDestinationParts(destinationText).map(function (name) {
            return {
                id: resolveDestinationId(name),
                name: name,
                country: ''
            };
        });
    }

    function supplierMatchesDestination(supplier, destinationText) {
        var destLower = String(destinationText || '').trim().toLowerCase();
        if (!destLower || destLower === '—') {
            return false;
        }
        var destParts = parseDestinationParts(destLower).map(function (p) { return p.toLowerCase(); });
        var destIds = [];
        parseDestinationParts(destinationText).forEach(function (part) {
            var id = resolveDestinationId(part);
            if (id > 0) {
                destIds.push(id);
            }
        });
        var places = supplier.places || [];
        if (!places.length) {
            return false;
        }
        return places.some(function (place) {
            var placeId = parseInt(place.id, 10) || 0;
            var placeName = String(place.name || '').trim().toLowerCase();
            if (placeId > 0 && destIds.indexOf(placeId) >= 0) {
                return true;
            }
            if (!placeName) {
                return false;
            }
            if (destParts.indexOf(placeName) >= 0) {
                return true;
            }
            return destParts.some(function (part) {
                return placeName.indexOf(part) >= 0 || part.indexOf(placeName) >= 0;
            }) || destLower.indexOf(placeName) >= 0;
        });
    }

    function filterSuppliersFromCatalog(destinationText) {
        return catalog.filter(function (supplier) {
            return supplierMatchesDestination(supplier, destinationText);
        });
    }

    function currentDestination() {
        var $input = $('input[name="destination"]');
        return $input.length ? String($input.val() || '').trim() : '';
    }

    function supplierDisplayName(supplier) {
        var name = String(supplier.name || '').trim();
        if (name) {
            return name;
        }
        return String(supplier.email || 'Supplier');
    }

    function supplierOptionLabel(supplier) {
        var name = supplierDisplayName(supplier);
        var email = String(supplier.email || '').trim();
        return name + (email ? ' · ' + email : '');
    }

    function supplierKey(supplier) {
        return String(supplier.id || '') + '|' + String(supplier.email || '').trim().toLowerCase();
    }

    function isSupplierSelected(supplier) {
        return selectedSuppliers.some(function (item) {
            return supplierKey(item) === supplierKey(supplier);
        });
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '').trim());
    }

    function updateToTriggerText() {
        var $text = $('#qSupplierMailToTriggerText');
        var count = selectedSuppliers.length;
        if (!count) {
            $text.text('Select recipients').addClass('is-placeholder');
            return;
        }
        $text.removeClass('is-placeholder');
        if (count === 1) {
            var only = selectedSuppliers[0];
            $text.text(String(only.email || only.name || '1 selected'));
            return;
        }
        $text.text(count + ' recipients selected');
    }

    function renderRecipientBadges() {
        var $wrap = $('#qSupplierMailRecipientBadges');
        $wrap.empty();
        updateToTriggerText();

        selectedSuppliers.forEach(function (supplier) {
            var email = String(supplier.email || '').trim();
            var name = String(supplier.name || email);
            var status = String(supplier.sendStatus || '');
            var $badge = $('<span class="q-supplier-mail-recipient-badge"></span>')
                .attr('data-supplier-key', supplierKey(supplier))
                .attr('data-email', email);

            if (status === 'sent') {
                $badge.addClass('badge-sent');
            } else if (status === 'failed') {
                $badge.addClass('badge-failed');
            }

            $badge.append(
                $('<span class="badge-text"></span>').text(email || name)
            );

            if (status === 'sent') {
                $badge.append(
                    $('<span class="badge-status-icon badge-status-sent" title="Sent"><i class="fas fa-check"></i></span>')
                );
            } else if (status === 'failed') {
                $badge.append(
                    $('<span class="badge-status-icon badge-status-failed"></span>')
                        .attr('title', supplier.sendMessage || 'Failed to send')
                        .append($('<i class="fas fa-times"></i>'))
                );
            } else if (status === 'sending') {
                $badge.append(
                    $('<span class="badge-status-icon badge-status-sending" title="Sending..."><i class="fas fa-spinner fa-spin"></i></span>')
                );
            }

            if (status !== 'sending') {
                $badge.append(
                    $('<button type="button" class="badge-remove" title="Remove">&times;</button>')
                        .attr('data-supplier-key', supplierKey(supplier))
                );
            }

            $wrap.append($badge);
        });
    }

    function menuRecipients() {
        var items = [];
        var seen = {};

        supplierList.forEach(function (supplier) {
            var email = String(supplier.email || '').trim();
            if (!email) {
                return;
            }
            var key = supplierKey(supplier);
            seen[key] = true;
            items.push({
                id: String(supplier.id || ''),
                name: supplierDisplayName(supplier),
                email: email,
                key: key,
                isCustom: false
            });
        });

        selectedSuppliers.forEach(function (supplier) {
            var key = supplierKey(supplier);
            if (seen[key]) {
                return;
            }
            items.push({
                id: String(supplier.id || ''),
                name: String(supplier.name || supplier.email || ''),
                email: String(supplier.email || '').trim(),
                key: key,
                isCustom: true
            });
        });

        return items;
    }

    function renderSupplierMenu() {
        var $list = $('#qSupplierMailToMenuList');
        var $empty = $('#qSupplierMailSupplierEmpty');
        $list.empty();

        var items = menuRecipients();
        var catalogItems = items.filter(function (item) { return !item.isCustom; });

        if (!catalogItems.length && !items.length) {
            $empty.removeClass('d-none');
        } else {
            $empty.addClass('d-none');
        }

        items.forEach(function (item) {
            var checked = isSupplierSelected(item);
            var $label = $('<label class="q-supplier-mail-to-option"></label>');
            var $checkbox = $('<input type="checkbox" class="js-q-supplier-mail-check">')
                .prop('checked', checked)
                .attr('data-supplier-key', item.key)
                .attr('data-supplier-id', item.id)
                .attr('data-supplier-name', item.name)
                .attr('data-supplier-email', item.email)
                .attr('data-is-custom', item.isCustom ? '1' : '0');

            var $text = $('<span class="q-supplier-mail-to-option-text"></span>');
            $text.append($('<span class="q-supplier-mail-to-option-name"></span>').text(item.name || item.email));
            $text.append($('<span class="q-supplier-mail-to-option-email"></span>').text(item.email));

            $label.append($checkbox).append($text);
            $list.append($label);
        });

        updateToTriggerText();
    }

    function addSelectedSupplier(supplier) {
        if (!supplier || !String(supplier.email || '').trim() || isSupplierSelected(supplier)) {
            return;
        }
        selectedSuppliers.push({
            id: String(supplier.id || ''),
            name: supplierDisplayName(supplier),
            email: String(supplier.email || '').trim()
        });
        renderRecipientBadges();
        renderSupplierMenu();
    }

    function removeSelectedSupplier(key) {
        selectedSuppliers = selectedSuppliers.filter(function (supplier) {
            return supplierKey(supplier) !== key;
        });
        renderRecipientBadges();
        renderSupplierMenu();
    }

    function toggleSelectedSupplier(supplier, checked) {
        if (checked) {
            addSelectedSupplier(supplier);
            return;
        }
        removeSelectedSupplier(supplierKey(supplier));
    }

    function clearSelectedSuppliers() {
        selectedSuppliers = [];
        renderRecipientBadges();
        renderSupplierMenu();
    }

    function closeToMenu() {
        $('#qSupplierMailToMenu').hide();
        $('#qSupplierMailToPicker').removeClass('is-open');
        $('#qSupplierMailToTrigger').attr('aria-expanded', 'false');
    }

    function openToMenu() {
        $('#qSupplierMailToMenu').show();
        $('#qSupplierMailToPicker').addClass('is-open');
        $('#qSupplierMailToTrigger').attr('aria-expanded', 'true');
        renderSupplierMenu();
    }

    function toggleToMenu() {
        if ($('#qSupplierMailToMenu').is(':visible')) {
            closeToMenu();
        } else {
            openToMenu();
        }
    }

    function addCustomEmailFromInput() {
        var email = String($('#qSupplierMailCustomEmail').val() || '').trim();
        if (!email) {
            return;
        }
        if (!isValidEmail(email)) {
            alert('Please enter a valid email address.');
            return;
        }
        addSelectedSupplier({
            id: '',
            name: email,
            email: email
        });
        $('#qSupplierMailCustomEmail').val('');
    }

    function applySupplierList(list, destinationText, options) {
        var opts = options || {};
        var preserveSelection = !!opts.preserveSelection;
        var autoSelectId = parseInt(opts.autoSelectId, 10) || 0;
        var $empty = $('#qSupplierMailSupplierEmpty');

        supplierList = Array.isArray(list) ? list.slice() : [];

        if (!preserveSelection) {
            clearSelectedSuppliers();
        } else {
            renderRecipientBadges();
            renderSupplierMenu();
        }

        var withEmail = supplierList.filter(function (supplier) {
            return String(supplier.email || '').trim() !== '';
        });

        if (!destinationText) {
            $empty.addClass('d-none');
            renderSupplierMenu();
            return;
        }

        if (!withEmail.length) {
            $empty.removeClass('d-none');
            renderSupplierMenu();
            if (autoSelectId > 0) {
                return;
            }
            return;
        }

        $empty.addClass('d-none');
        renderSupplierMenu();

        if (autoSelectId > 0) {
            var newSupplier = null;
            supplierList.forEach(function (supplier) {
                if (parseInt(supplier.id, 10) === autoSelectId) {
                    newSupplier = supplier;
                }
            });
            if (newSupplier) {
                addSelectedSupplier(newSupplier);
            }
        }
    }

    function loadSuppliersForDestination(destinationText, options) {
        destinationText = String(destinationText || '').trim();
        $('#qSupplierMailSupplierEmpty').addClass('d-none');
        $('#qSupplierMailToMenuList').html(
            '<div class="text-muted small px-3 py-2"><i class="fas fa-spinner fa-spin mr-1"></i>Loading suppliers…</div>'
        );

        $.ajax({
            url: 'crm/ajax/suppliers_for_destination.php',
            type: 'GET',
            dataType: 'json',
            data: { destination: destinationText },
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function (res) {
            if (res && res.success && Array.isArray(res.suppliers)) {
                applySupplierList(res.suppliers, destinationText, options);
                return;
            }
            applySupplierList(filterSuppliersFromCatalog(destinationText), destinationText, options);
        }).fail(function () {
            applySupplierList(filterSuppliersFromCatalog(destinationText), destinationText, options);
        });
    }

    function mergeSupplierIntoCatalog(supplier) {
        if (!supplier || !supplier.email) {
            return;
        }
        var id = parseInt(supplier.id, 10) || 0;
        var found = false;
        catalog = catalog.map(function (item) {
            if (parseInt(item.id, 10) === id) {
                found = true;
                return supplier;
            }
            return item;
        });
        if (!found) {
            catalog.push(supplier);
        }
    }

    function openCreateSupplierModal() {
        window.qSupplierCreateContext = 'mail';
        var destination = currentDestination();
        if (!destination) {
            alert('Please enter a destination on the quotation form first.');
            return;
        }
        $('#qSupplierCreateForm')[0].reset();
        $('#qScDestination').val(destination);
        $('#qScDestination').closest('.form-group').find('.form-text').text(
            'Supplier will be linked to the quotation destination.'
        );
        $('#qSupplierCreateModal').modal('show');
    }

    function saveNewSupplier() {
        var context = String(window.qSupplierCreateContext || 'mail');
        var destination = String($('#qScDestination').val() || currentDestination() || '').trim();
        var name = String($('#qScName').val() || '').trim();
        var contactName = String($('#qScContactName').val() || '').trim();
        var email = String($('#qScEmail').val() || '').trim();
        var mobile = String($('#qScMobile').val() || '').trim();

        if (!name) {
            alert('Supplier name is required.');
            return;
        }
        if (!email) {
            alert('Contact email is required.');
            return;
        }
        if (context === 'mail' && !destination) {
            alert('Please enter a destination on the quotation form first.');
            return;
        }

        var types = ['land_package'];
        var supplierOf = ['land_package'];
        if (context === 'flight') {
            types = ['flight', 'train'];
            supplierOf = ['flight', 'train'];
        } else if (context === 'itinerary' || context === 'hotel') {
            // Appears in Itinerary and Hotel supplier dropdowns after save / reload.
            types = ['land_package', 'hotels'];
            supplierOf = ['land_package', 'hotels'];
        }
        var places = destination ? destinationPlacesFromText(destination) : [];
        var cityName = '';
        var cityId = 0;
        var countryName = '';
        if (places.length) {
            cityName = String(places[0].name || '').trim();
            cityId = parseInt(places[0].id, 10) || 0;
            countryName = String(places[0].country || '').trim();
        }
        if (!cityName && destination) {
            cityName = parseDestinationParts(destination)[0] || destination;
        }
        // Place ids from destination lookup are destination IDs, not cities.id —
        // city_id is resolved on the server from city_name.
        cityId = 0;

        var $btn = $('#qScSaveBtn');
        $btn.prop('disabled', true);

        $.ajax({
            url: 'crm/ajax/save_supplier.php',
            type: 'POST',
            dataType: 'json',
            data: {
                id: 0,
                name: name,
                website: '',
                city_id: cityId,
                city_name: cityName,
                country_name: countryName,
                physical_address: '',
                is_active: 1,
                supplier_types_json: JSON.stringify(types),
                supplier_type: JSON.stringify(types),
                contacts_json: JSON.stringify([{
                    contact_name: contactName,
                    email: email,
                    mobile: mobile,
                    is_primary: 1
                }]),
                supplier_of_json: JSON.stringify(supplierOf),
                places_json: JSON.stringify(places)
            },
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function (res) {
            if (!res || !res.success) {
                alert((res && res.message) || 'Could not save supplier.');
                return;
            }

            var newId = parseInt(res.id, 10) || 0;
            var newName = name;
            if (res.supplier && res.supplier.name) {
                newName = String(res.supplier.name);
            }

            $('#qSupplierCreateModal').modal('hide');

            if (context === 'flight' && typeof window.qOnFlightSupplierCreated === 'function') {
                window.qOnFlightSupplierCreated({
                    id: newId,
                    name: newName,
                    supplier: res.supplier || null
                });
                window.qSupplierCreateContext = 'mail';
                return;
            }

            if (context === 'itinerary' && typeof window.qOnItinerarySupplierCreated === 'function') {
                window.qOnItinerarySupplierCreated({
                    id: newId,
                    name: newName,
                    supplier: res.supplier || null
                });
                window.qSupplierCreateContext = 'mail';
                return;
            }

            if (context === 'hotel' && typeof window.qOnHotelSupplierCreated === 'function') {
                window.qOnHotelSupplierCreated({
                    id: newId,
                    name: newName,
                    supplier: res.supplier || null
                });
                window.qSupplierCreateContext = 'mail';
                return;
            }

            if (res.supplier) {
                mergeSupplierIntoCatalog(res.supplier);
            }

            if (res.supplier) {
                var dest = currentDestination();
                var existing = supplierList.some(function (s) {
                    return parseInt(s.id, 10) === newId;
                });
                if (!existing) {
                    supplierList.push(res.supplier);
                }
                applySupplierList(supplierList, dest, {
                    preserveSelection: true,
                    autoSelectId: newId
                });
            } else {
                loadSuppliersForDestination(currentDestination(), {
                    preserveSelection: true,
                    autoSelectId: newId
                });
            }
            window.qSupplierCreateContext = 'mail';
        }).fail(function (xhr) {
            var msg = 'Could not save supplier.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            alert(msg);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    }

    function getSelectedSuppliers() {
        return selectedSuppliers.map(function (supplier) {
            return {
                id: String(supplier.id || ''),
                name: String(supplier.name || supplier.email),
                email: String(supplier.email || '').trim()
            };
        }).filter(function (supplier) {
            return supplier.email !== '';
        });
    }

    function clearRecipientSendStatus() {
        selectedSuppliers.forEach(function (supplier) {
            supplier.sendStatus = '';
            supplier.sendMessage = '';
        });
    }

    function captureSendPayload() {
        var body = '';
        if (editorReady && $.fn.summernote) {
            body = $('#qSupplierMailBody').summernote('code');
        } else {
            body = String($('#qSupplierMailBody').val() || '');
        }

        var attachmentInput = document.getElementById('qSupplierMailAttachment');
        var attachmentFile = attachmentInput && attachmentInput.files && attachmentInput.files[0]
            ? attachmentInput.files[0]
            : null;

        return {
            subject: String($('#qSupplierMailSubject').val() || '').trim(),
            sender_id: String($('#qSupplierMailSender').val() || '').trim(),
            body: body,
            attachment: attachmentFile
        };
    }

    function buildFormDataForRecipient(toEmail) {
        var payload = pendingSendPayload || {};
        var formData = new FormData();
        formData.set('to', toEmail);
        formData.set('subject', payload.subject || '');
        formData.set('body', payload.body || '');
        if (payload.sender_id) {
            formData.set('sender_id', payload.sender_id);
        }
        if (payload.attachment) {
            formData.set('attachment', payload.attachment);
        }
        return formData;
    }

    function sendOneEmail(toEmail) {
        return $.ajax({
            url: 'mail/ajax/send_message.php',
            type: 'POST',
            data: buildFormDataForRecipient(toEmail),
            processData: false,
            contentType: false,
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
    }

    function renderStatusModalBadges() {
        var $wrap = $('#qSupplierMailStatusBadges');
        $wrap.empty();

        var count = statusRecipients.length;
        var summary = count === 1
            ? '1 recipient selected'
            : (count + ' recipients selected');
        $('#qSupplierMailStatusSummary').text(summary);

        statusRecipients.forEach(function (recipient) {
            var email = String(recipient.email || '').trim();
            var status = String(recipient.sendStatus || 'sending');
            var $badge = $('<span class="q-supplier-mail-status-badge"></span>')
                .attr('data-email', email);

            if (status === 'sent') {
                $badge.addClass('badge-sent');
            } else if (status === 'failed') {
                $badge.addClass('badge-failed');
            } else {
                $badge.addClass('badge-sending');
            }

            $badge.append($('<span class="badge-text"></span>').text(email));

            if (status === 'sent') {
                $badge.append(
                    $('<span class="badge-status-icon" title="Sent"><i class="fas fa-check"></i></span>')
                );
            } else if (status === 'failed') {
                $badge.append(
                    $('<span class="badge-status-icon"></span>')
                        .attr('title', recipient.sendMessage || 'Failed to send')
                        .append($('<i class="fas fa-times"></i>'))
                );
            } else {
                $badge.append(
                    $('<span class="badge-status-icon" title="Sending..."><i class="fas fa-spinner fa-spin"></i></span>')
                );
            }

            $wrap.append($badge);
        });
    }

    function updateStatusRecipient(email, state, message) {
        statusRecipients.forEach(function (recipient) {
            if (String(recipient.email || '').trim() === email) {
                recipient.sendStatus = state;
                recipient.sendMessage = message || '';
            }
        });
        renderStatusModalBadges();
    }

    function openStatusModal(recipients) {
        statusRecipients = recipients.map(function (recipient) {
            return {
                id: String(recipient.id || ''),
                name: String(recipient.name || recipient.email || ''),
                email: String(recipient.email || '').trim(),
                sendStatus: 'sending',
                sendMessage: ''
            };
        });

        $('#qSupplierMailStatusTitle').text('Sending email…');
        $('#qSupplierMailStatusCloseBtn').addClass('d-none');
        $('#qSupplierMailStatusHeaderClose').addClass('d-none');
        renderStatusModalBadges();
        $('#qSupplierMailStatusModal').modal('show');
    }

    function finishStatusModal(sent, failed) {
        isSending = false;
        pendingSendPayload = null;

        var title = 'Email sent';
        if (failed > 0 && sent > 0) {
            title = 'Sent with some failures';
        } else if (failed > 0 && sent === 0) {
            title = 'Failed to send';
        } else if (sent === 1) {
            title = 'Email sent';
        } else if (sent > 1) {
            title = 'Emails sent';
        }

        $('#qSupplierMailStatusTitle').text(title);
        $('#qSupplierMailStatusCloseBtn').removeClass('d-none');
        $('#qSupplierMailStatusHeaderClose').removeClass('d-none');
    }

    function sendSequentially(recipients) {
        var index = 0;
        var sent = 0;
        var failed = 0;

        function next() {
            if (index >= recipients.length) {
                finishStatusModal(sent, failed);
                return;
            }

            var recipient = recipients[index];
            index += 1;
            updateStatusRecipient(recipient.email, 'sending');

            sendOneEmail(recipient.email)
                .done(function (res) {
                    if (res && res.success) {
                        sent += 1;
                        updateStatusRecipient(recipient.email, 'sent');
                    } else {
                        failed += 1;
                        updateStatusRecipient(recipient.email, 'failed', (res && res.message) || 'Send failed');
                    }
                    next();
                })
                .fail(function (xhr) {
                    failed += 1;
                    var msg = 'Request failed.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    updateStatusRecipient(recipient.email, 'failed', msg);
                    next();
                });
        }

        next();
    }

    function startSendFlow(recipients) {
        pendingSendPayload = captureSendPayload();
        skipComposeReset = true;
        isSending = true;

        $('#qSupplierMailModal').one('hidden.bs.modal', function () {
            skipComposeReset = false;
            openStatusModal(recipients);
            sendSequentially(recipients);
        });
        $('#qSupplierMailModal').modal('hide');
    }

    function initEditor() {
        if (editorReady || !$('#qSupplierMailBody').length || !$.fn.summernote) {
            return;
        }
        $('#qSupplierMailBody').summernote({
            height: 240,
            placeholder: 'Compose email',
            disableResizeEditor: true,
            toolbar: [
                ['style', ['bold', 'italic', 'underline']],
                ['para', ['ul', 'ol']],
                ['insert', ['link']]
            ]
        });

        var $editor = $('#qSupplierMailBody').next('.note-editor');
        var $toolbar = $editor.find('.note-toolbar').first();
        var $host = $('#qGmailFormatToolbar');
        if ($toolbar.length && $host.length) {
            $host.empty().append($toolbar);
        }

        editorReady = true;
    }

    function resetForm() {
        isSending = false;
        selectedSuppliers = [];
        supplierList = [];
        clearRecipientSendStatus();
        closeToMenu();
        $('#qSupplierMailRecipientBadges').empty();
        $('#qSupplierMailToMenuList').empty();
        $('#qSupplierMailCustomEmail').val('');
        $('#qSupplierMailSupplierEmpty').addClass('d-none');
        updateToTriggerText();
        $('#qSupplierMailSendBtn').prop('disabled', false).removeClass('d-none').text('Send');
        $('#qSupplierMailCloseBtn').addClass('d-none');
        $('#qSupplierMailDiscardBtn').removeClass('d-none');
        if (editorReady && $.fn.summernote) {
            $('#qSupplierMailBody').summernote('reset');
        } else {
            $('#qSupplierMailBody').val('');
        }
        $('#qSupplierMailAttachment').val('');
        $('#qSupplierMailAttachLabel').text('');
    }

    var pendingComposeOptions = null;
    var useCustomCompose = false;

    function textToMailHtml(text) {
        var lines = String(text || '').split(/\r\n|\r|\n/);
        return '<div style="line-height:1.45;margin:0;padding:0;">' + lines.map(function (line) {
            return line === '' ? '<br>' : escapeHtml(line);
        }).join('<br>') + '</div>';
    }

    function setMailBodyHtml(html) {
        html = html == null ? '' : String(html);
        if (editorReady && $.fn.summernote) {
            $('#qSupplierMailBody').summernote('code', html);
        } else {
            $('#qSupplierMailBody').val(html);
        }
    }

    function applyCustomCompose(options) {
        options = options || {};
        if (options.subject != null) {
            $('#qSupplierMailSubject').val(String(options.subject));
        }

        if (options.bodyHtml != null) {
            setMailBodyHtml(options.bodyHtml);
        } else if (options.bodyText != null) {
            setMailBodyHtml(textToMailHtml(options.bodyText));
        }

        if (Array.isArray(options.recipients)) {
            selectedSuppliers = [];
            options.recipients.forEach(function (recipient) {
                if (!recipient) {
                    return;
                }
                var email = String(recipient.email || '').trim();
                if (!email || !isValidEmail(email)) {
                    return;
                }
                selectedSuppliers.push({
                    id: String(recipient.id || ''),
                    name: String(recipient.name || email),
                    email: email,
                    sendStatus: '',
                    sendMessage: ''
                });
            });
            renderRecipientBadges();
        }

        var dest = options.destination != null ? String(options.destination || '').trim() : currentDestination();
        loadSuppliersForDestination(dest, { preserveSelection: true });
    }

    function openModal(options) {
        options = options && typeof options === 'object' ? options : null;
        pendingComposeOptions = options;
        useCustomCompose = !!(options && (
            options.skipTemplate
            || options.bodyHtml != null
            || options.bodyText != null
            || options.subject != null
            || Array.isArray(options.recipients)
        ));

        initEditor();
        if (useCustomCompose) {
            applyCustomCompose(options || {});
        } else {
            loadSuppliersForDestination(currentDestination());
            applySupplierMailTemplate();
        }
        updateSenderDisplay();
        $('#qSupplierMailSendBtn').prop('disabled', false).removeClass('d-none').text('Send');
        $('#qSupplierMailCloseBtn').addClass('d-none');
        $('#qSupplierMailDiscardBtn').removeClass('d-none');
        $('#qSupplierMailModal').modal('show');
    }

    function updateSenderDisplay() {
        var $sel = $('#qSupplierMailSender');
        if (!$sel.length) {
            return;
        }
        var $opt = $sel.find('option:selected');
        var name = String($opt.data('from-name') || '').trim();
        var email = String($opt.data('from-email') || '').trim();
        var initial = (name || email || 'C').charAt(0).toUpperCase();
        $('#qSupplierMailAvatar').text(initial);
    }

    window.QSupplierMail = {
        init: function (options) {
            catalog = Array.isArray(options.suppliers) ? options.suppliers : [];
            destinationNameToId = options.destinationNameToId || {};
            mailTemplate = options.mailTemplate && typeof options.mailTemplate === 'object'
                ? options.mailTemplate
                : { subject: '', body_html: '', meta: {} };
            if (!mailTemplate.meta || typeof mailTemplate.meta !== 'object') {
                mailTemplate.meta = {};
            }

            $('#qSendMailBtn').on('click', function () {
                openModal();
            });

            $(document).on('change', '#qSupplierMailSender', updateSenderDisplay);

            $('#qSupplierMailCreateBtn').on('click', function () {
                openCreateSupplierModal();
            });

            $('#qSupplierCreateForm').on('submit', function (e) {
                e.preventDefault();
                saveNewSupplier();
            });

            $('#qSupplierCreateModal').on('show.bs.modal', function () {
                if ($('#qSupplierMailModal').hasClass('show')) {
                    var zIndex = 1050 + ($('.modal:visible').length * 10);
                    $(this).css('z-index', zIndex + 5);
                    window.setTimeout(function () {
                        $('.modal-backdrop').not('.modal-stack').last()
                            .css('z-index', zIndex)
                            .addClass('modal-stack');
                    }, 0);
                }
            });

            $('#qSupplierCreateModal').on('hidden.bs.modal', function () {
                if ($('#qSupplierMailModal').hasClass('show')) {
                    $('body').addClass('modal-open');
                }
            });

            $('#qSupplierMailToTrigger').on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (isSending) {
                    return;
                }
                toggleToMenu();
            });

            $(document).on('change', '.js-q-supplier-mail-check', function () {
                if (isSending) {
                    $(this).prop('checked', !$(this).prop('checked'));
                    return;
                }
                toggleSelectedSupplier({
                    id: String($(this).data('supplier-id') || ''),
                    name: String($(this).data('supplier-name') || ''),
                    email: String($(this).data('supplier-email') || '')
                }, $(this).is(':checked'));
            });

            $('#qSupplierMailCustomAddBtn').on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (!isSending) {
                    addCustomEmailFromInput();
                }
            });

            $('#qSupplierMailCustomEmail').on('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    if (!isSending) {
                        addCustomEmailFromInput();
                    }
                }
            });

            $('#qSupplierMailToMenu').on('click', function (e) {
                e.stopPropagation();
            });

            $(document).on('click.qSupplierMailTo', function (e) {
                if (!$(e.target).closest('#qSupplierMailToPicker').length) {
                    closeToMenu();
                }
            });

            $(document).on('click', '.q-supplier-mail-recipient-badge .badge-remove', function () {
                removeSelectedSupplier(String($(this).data('supplier-key') || ''));
            });

            $('input[name="destination"]').on('change input', function () {
                if ($('#qSupplierMailModal').hasClass('show') && !isSending) {
                    loadSuppliersForDestination(currentDestination());
                }
            });

            $('#qSupplierMailModal').on('shown.bs.modal', function () {
                if (useCustomCompose && pendingComposeOptions) {
                    applyCustomCompose(pendingComposeOptions);
                    pendingComposeOptions = null;
                    return;
                }
                loadSuppliersForDestination(currentDestination());
                applySupplierMailTemplate();
            });

            $('#qSupplierMailAttachBtn').on('click', function () {
                $('#qSupplierMailAttachment').trigger('click');
            });

            $('#qSupplierMailAttachment').on('change', function () {
                var file = this.files && this.files[0];
                $('#qSupplierMailAttachLabel').text(file ? file.name : '');
            });

            $('#qSupplierMailForm').on('submit', function (e) {
                e.preventDefault();
                if (isSending) {
                    return;
                }

                var subject = String($('#qSupplierMailSubject').val() || '').trim();
                var recipients = getSelectedSuppliers();

                if (!recipients.length) {
                    alert('Please select at least one recipient.');
                    return;
                }

                if (!subject) {
                    alert('Subject is required.');
                    return;
                }
                if (!$('#qSupplierMailSender').length || !$('#qSupplierMailSender').val()) {
                    alert('Please select a sender email from Email Master.');
                    return;
                }

                $('#qSupplierMailSendBtn').prop('disabled', true).text('Sending...');
                startSendFlow(recipients);
            });

            $('#qSupplierMailModal').on('hidden.bs.modal', function () {
                pendingComposeOptions = null;
                useCustomCompose = false;
                if (skipComposeReset) {
                    return;
                }
                resetForm();
                $('#qSupplierCreateModal').modal('hide');
            });

            $('#qSupplierMailStatusModal').on('hidden.bs.modal', function () {
                statusRecipients = [];
                pendingSendPayload = null;
                isSending = false;
                resetForm();
                $('#qSupplierMailStatusBadges').empty();
                $('#qSupplierMailStatusCloseBtn').addClass('d-none');
                $('#qSupplierMailStatusHeaderClose').addClass('d-none');
            });
        },

        open: function (options) {
            openModal(options || {});
        }
    };
})();
