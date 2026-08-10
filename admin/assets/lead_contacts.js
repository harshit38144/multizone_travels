/* Lead Contacts — leads + manual contacts, profile & family */
(function ($) {
    'use strict';

    var ATTACH_FIELDS = [
        { key: 'photo', input: 'pan_photo', label: 'Document 1' },
        { key: 'id_proof_front', input: 'aadhar_photo', label: 'Document 2' },
        { key: 'id_proof_back', input: 'other_document', label: 'Document 3' }
    ];

    var activeRef = { source: 'lead', refId: 0, name: '', phone: '', email: '' };
    var familyCache = [];
    var profileCache = null;
    var contactCache = null;
    var pendingAttachments = []; // { id, file, previewUrl, name, isPdf }
    var rowPendingMap = {}; // rowKey -> pendingAttachments[]
    var attachContext = { mode: 'form', memberId: 0, rowKey: '', editPrimary: false };
    var attachReplaceContext = null; // { input, key, memberId, isPrimary }
    var viewerContext = { url: '', name: '', isPdf: false };
    var paxRowSeq = 0;
    var editingMemberId = 0;
    var editingPrimary = false;
    var RELATION_OPTS = ['Self', 'Spouse', 'Parent', 'Sibling', 'Son', 'Daughter', 'Friend', 'Relative', 'Other'];
    var LEGACY_AGE_TYPES = { Adult: 1, Child: 1, Infant: 1 };
    var TITLE_OPTS = ['Mr', 'Mrs', 'Ms', 'Master', 'Miss'];

    function esc(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function showAlert(msg, type) {
        $('#lcAlert').html('<div class="alert alert-' + (type || 'success') + ' alert-dismissible fade show">' +
            esc(msg) + '<button type="button" class="close" data-dismiss="alert">&times;</button></div>');
        window.scrollTo(0, 0);
    }

    function parseFailMessage(xhr, fallback) {
        var msg = fallback || 'Network error. Please try again.';
        try {
            var j = JSON.parse(xhr.responseText);
            if (j && j.message) {
                return j.message;
            }
        } catch (e) { /* ignore */ }
        if (xhr && xhr.responseText) {
            var raw = String(xhr.responseText).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
            if (raw) {
                return raw.slice(0, 220);
            }
        }
        if (xhr && xhr.status) {
            return msg + ' (HTTP ' + xhr.status + ')';
        }
        return msg;
    }

    function toIsoDate(val) {
        val = String(val == null ? '' : val).trim();
        if (!val || val === '0000-00-00') return '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(val)) {
            return val;
        }
        var m = val.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2}|\d{4})$/);
        if (!m) return '';
        var dd = parseInt(m[1], 10);
        var mm = parseInt(m[2], 10);
        var yyRaw = m[3];
        var yyyy;
        if (yyRaw.length === 2) {
            var yy = parseInt(yyRaw, 10);
            var now = new Date();
            var century = Math.floor(now.getFullYear() / 100) * 100;
            yyyy = century + yy;
            var probe = new Date(yyyy, mm - 1, dd);
            if (probe.getTime() > now.getTime()) {
                yyyy -= 100;
            }
        } else {
            yyyy = parseInt(yyRaw, 10);
        }
        if (mm < 1 || mm > 12 || dd < 1 || dd > 31 || yyyy < 1900) return '';
        var d = new Date(yyyy, mm - 1, dd);
        if (d.getFullYear() !== yyyy || d.getMonth() !== mm - 1 || d.getDate() !== dd) return '';
        return yyyy + '-' + ('0' + mm).slice(-2) + '-' + ('0' + dd).slice(-2);
    }

    function fmtDate(d) {
        var iso = toIsoDate(d);
        if (!iso) return '';
        var p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0].slice(-2);
    }

    function ageFromDob(dob) {
        var iso = toIsoDate(dob);
        if (!iso) return '';
        var parts = iso.split('-');
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) - 1;
        var d = parseInt(parts[2], 10);
        var birth = new Date(y, m, d);
        if (isNaN(birth.getTime())) return '';
        var now = new Date();
        var age = now.getFullYear() - birth.getFullYear();
        var md = now.getMonth() - birth.getMonth();
        if (md < 0 || (md === 0 && now.getDate() < birth.getDate())) age--;
        if (age < 0 || age > 130) return '';
        return age + (age === 1 ? ' yr' : ' yrs');
    }

    function setPreview($form, field, url) {
        var $box = $form.find('.lc-preview-' + field);
        if (!url) {
            $box.empty();
            return;
        }
        if (/\.pdf$/i.test(url)) {
            $box.html('<a href="' + esc(url) + '" target="_blank">Current file (PDF)</a>');
        } else {
            $box.html('<a href="' + esc(url) + '" target="_blank"><img src="' + esc(url) + '" alt=""></a>');
        }
    }

    function setProfilePhotoPreview(url, localBlob) {
        var $preview = $('#lcProfilePhotoPreview');
        var $img = $preview.find('.lc-profile-photo-img');
        var $ph = $preview.find('.lc-profile-photo-placeholder');
        var $clear = $('#lcProfilePhotoClear');
        var src = String(localBlob || url || '').trim();
        if (src && src !== 'null' && src !== 'undefined') {
            $img.attr('src', src).removeClass('d-none');
            $ph.addClass('d-none');
            $clear.removeClass('d-none');
        } else {
            $img.attr('src', '').addClass('d-none');
            $ph.removeClass('d-none');
            $clear.addClass('d-none');
        }
    }

    function resetProfilePhotoField() {
        $('#lcProfilePhotoInput').val('');
        $('#lcClearProfilePhoto').val('');
        setProfilePhotoPreview('');
    }

    function updateContactRowAvatar($row, photoUrl, name) {
        if (!$row || !$row.length) {
            return;
        }
        var $avatar = $row.find('.lc-avatar').first();
        if (!$avatar.length) {
            return;
        }
        photoUrl = String(photoUrl || '').trim();
        name = String(name || $row.find('.lc-contact-name').text() || 'Profile photo').trim();
        $row.attr('data-photo', photoUrl);
        if (photoUrl) {
            var $btn = $('<button type="button" class="lc-avatar has-photo js-lc-avatar-view" title="View profile photo"></button>');
            $btn.attr('data-photo', photoUrl).attr('data-name', name);
            $btn.html('<img src="' + esc(photoUrl) + '" alt="">');
            $avatar.replaceWith($btn);
        } else {
            var $span = $('<span class="lc-avatar"></span>').text(initialsFromName(name));
            $avatar.replaceWith($span);
        }
    }

    function personName(data) {
        data = data || {};
        var n = trimJoin([data.first_name, data.last_name]);
        return trimJoin([data.title, n]);
    }

    function trimJoin(parts) {
        return parts.filter(function (p) { return p && String(p).trim() !== ''; }).join(' ').trim();
    }

    function initialsFromName(name) {
        name = String(name || '').trim();
        if (!name) return 'C';
        var parts = name.replace(/^(Mr|Mrs|Ms|Master|Miss|Mstr)\.?\s+/i, '').split(/\s+/);
        var a = (parts[0] || 'C').charAt(0);
        var b = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
        return (a + b).toUpperCase();
    }

    function setActiveRef(source, refId, meta) {
        meta = meta || {};
        activeRef = {
            source: source || 'lead',
            refId: parseInt(refId, 10) || 0,
            name: meta.name || activeRef.name || '',
            phone: meta.phone || activeRef.phone || '',
            email: meta.email || activeRef.email || ''
        };
        $('#lcFamilySource, #lcProfileSource').val(activeRef.source);
        $('#lcFamilyRefId, #lcProfileRefId').val(activeRef.refId);
    }

    function btnRef($el) {
        return {
            source: $el.data('source') || 'lead',
            refId: parseInt($el.data('ref-id'), 10) || 0,
            name: $el.data('name') || '',
            phone: $el.data('phone') || '',
            email: $el.data('email') || ''
        };
    }

    function fillPersonForm($form, data, fallback) {
        data = data || {};
        fallback = fallback || {};

        var name = trimJoin([data.first_name, data.last_name]);
        if (!name && fallback.customer_name) {
            name = String(fallback.customer_name).trim();
        }

        var email = data.email || fallback.customer_email || '';
        var mobileRaw = data.mobile || fallback.customer_phone || '';
        var mobiles = String(mobileRaw).split(/\s*[\/|,;]\s*/).map(function (m) {
            return $.trim(m);
        }).filter(Boolean);
        if (!mobiles.length) {
            mobiles = [''];
        }

        $form.find('[name=title]').val(data.title || '');
        $form.find('[name=name]').val(name);
        $form.find('[name=website]').val(data.website || '');
        $form.find('[name=email]').val(email);
        $form.find('[name=date_of_birth]').val(data.date_of_birth && data.date_of_birth !== '0000-00-00' ? data.date_of_birth : '');
        $form.find('[name=gender]').val(data.gender || '');
        $form.find('[name=address]').val(data.address_line1 || '');
        $form.find('[name=city]').val(data.city || '');
        $form.find('#lcPersonMobile').val(mobileRaw);

        resetContactRows(mobiles.map(function (m, idx) {
            return {
                name: idx === 0 ? name : '',
                email: idx === 0 ? email : '',
                mobile: m
            };
        }));

        $form.find('input[type=file]').val('');
        $('#lcClearProfilePhoto').val('');
        setProfilePhotoPreview(data.profile_photo || '');
        setPreview($form, 'pan', data.photo);
        setPreview($form, 'aadhar', data.id_proof_front);
        setPreview($form, 'other', data.id_proof_back);
    }

    function contactRowHtml(row, isFirst) {
        row = row || {};
        var actionClass = isFirst ? 'js-lc-contact-add' : 'js-lc-contact-remove is-remove';
        var actionIcon = isFirst ? 'fa-plus' : 'fa-minus';
        var actionTitle = isFirst ? 'Add contact' : 'Remove contact';
        return '' +
            '<div class="form-row lc-contact-row align-items-end">' +
            '<div class="form-group col-md-4">' +
            (isFirst ? '<label>Contact Name</label>' : '<label class="lc-contact-action-label">&nbsp;</label>') +
            '<input type="text" class="form-control js-lc-c-name" placeholder="Contact name" value="' + esc(row.name || '') + '" autocomplete="off">' +
            '</div>' +
            '<div class="form-group col-md-4">' +
            (isFirst ? '<label>Email <span class="text-danger">*</span></label>' : '<label class="lc-contact-action-label">&nbsp;</label>') +
            '<input type="email" class="form-control js-lc-c-email" placeholder="Email" value="' + esc(row.email || '') + '" autocomplete="off">' +
            '</div>' +
            '<div class="form-group col-md-3">' +
            (isFirst ? '<label>Mobile No</label>' : '<label class="lc-contact-action-label">&nbsp;</label>') +
            '<input type="text" class="form-control js-lc-c-mobile" placeholder="Mobile number" value="' + esc(row.mobile || '') + '" autocomplete="tel">' +
            '</div>' +
            '<div class="form-group col-md-1 lc-contact-action-wrap">' +
            '<label class="lc-contact-action-label">&nbsp;</label>' +
            '<button type="button" class="btn lc-btn-contact-action ' + actionClass + '" title="' + actionTitle + '">' +
            '<i class="fas ' + actionIcon + '"></i></button>' +
            '</div></div>';
    }

    function resetContactRows(rows) {
        rows = rows && rows.length ? rows : [{ name: '', email: '', mobile: '' }];
        var html = '';
        rows.forEach(function (row, idx) {
            html += contactRowHtml(row, idx === 0);
        });
        $('#lcContactRows').html(html);
    }

    function syncContactRowFields() {
        var $rows = $('#lcContactRows .lc-contact-row');
        var $first = $rows.first();
        var topName = $.trim($('#lcProfileForm [name=name]').val() || '');
        var rowName = $.trim($first.find('.js-lc-c-name').val() || '');
        var rowEmail = $.trim($first.find('.js-lc-c-email').val() || '');

        if (!rowEmail) {
            $rows.each(function () {
                if (!rowEmail) {
                    rowEmail = $.trim($(this).find('.js-lc-c-email').val() || '');
                }
            });
        }

        if (!rowName && topName) {
            $first.find('.js-lc-c-name').val(topName);
        }

        $('#lcPersonEmail').val(rowEmail);

        var mobiles = [];
        $rows.each(function () {
            var m = $.trim($(this).find('.js-lc-c-mobile').val() || '');
            if (m) {
                mobiles.push(m);
            }
        });
        $('#lcPersonMobile').val(mobiles.join(' / '));
    }

    var citySearchTimer = null;
    function searchContactCities(q) {
        $.getJSON('crm/ajax/search_cities.php', { q: q, limit: 20 })
            .done(function (res) {
                var $dd = $('#lcCitySearchDropdown');
                $dd.empty();
                if (!res || !res.success || !res.data || !res.data.length) {
                    $dd.hide();
                    return;
                }
                res.data.forEach(function (row) {
                    var label = row.name;
                    if (row.country_name) {
                        label += ', ' + row.country_name;
                    }
                    $dd.append(
                        '<div class="item" data-label="' + esc(label) + '">' + esc(label) + '</div>'
                    );
                });
                $dd.show();
            });
    }

    function loadContactDetails(source, refId) {
        return $.getJSON('ajax/get_contact_details.php', { contact_source: source, ref_id: refId });
    }

    function updateFamilyCount(source, refId, count) {
        var $row = $('tr[data-source="' + source + '"][data-ref-id="' + refId + '"]');
        $row.find('[data-family-count]').text(count).toggleClass('empty', !count);
        var label = count === 1 ? '1 member' : (count + ' members');
        $('#lcMembersCount').text(label);
    }

    function primaryDisplayName() {
        var fromProfile = personName(profileCache || {});
        if (fromProfile) return fromProfile;
        if (contactCache && contactCache.customer_name) return contactCache.customer_name;
        return activeRef.name || '—';
    }

    function fileNameFromUrl(url) {
        if (!url) return 'Attachment';
        try {
            var path = String(url).split('?')[0];
            var base = path.substring(path.lastIndexOf('/') + 1);
            return decodeURIComponent(base) || 'Attachment';
        } catch (e) {
            return 'Attachment';
        }
    }

    function memberAttachments(member) {
        member = member || {};
        var list = [];
        ATTACH_FIELDS.forEach(function (slot) {
            var url = member[slot.key];
            if (url) {
                list.push({
                    key: slot.key,
                    input: slot.input,
                    label: slot.label,
                    url: url,
                    name: fileNameFromUrl(url),
                    isPdf: /\.pdf$/i.test(url),
                    pending: false
                });
            }
        });
        return list;
    }

    function downloadAttachment(url, filename) {
        if (!url) return;
        var link = document.createElement('a');
        link.href = url;
        link.download = filename || fileNameFromUrl(url);
        link.target = '_blank';
        link.rel = 'noopener';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function openAttachViewer(item) {
        item = item || {};
        var url = item.url || '';
        var name = item.name || fileNameFromUrl(url) || 'Attachment';
        var isPdf = !!item.isPdf || /\.pdf$/i.test(name) || /\.pdf$/i.test(url);
        var titleHtml = item.titleHtml || '<i class="far fa-eye mr-2"></i>View attachment';
        viewerContext = { url: url, name: name, isPdf: isPdf };
        $('#lcAttachViewerModal .modal-title').html(titleHtml);
        $('#lcViewerFileName').text(name);
        var $frame = $('#lcViewerFrame').empty();
        if (!url) {
            $frame.html('<div class="lc-viewer-pdf-fallback">File preview unavailable.</div>');
        } else if (isPdf) {
            $frame.html(
                '<iframe src="' + esc(url) + '#toolbar=1" title="' + esc(name) + '"></iframe>' +
                '<div class="lc-viewer-pdf-fallback d-none" id="lcViewerPdfFallback">' +
                '<p class="mb-2">PDF preview may be blocked by the browser.</p>' +
                '<a class="btn btn-light btn-sm" href="' + esc(url) + '" target="_blank" rel="noopener">Open in new tab</a>' +
                '</div>'
            );
        } else {
            $frame.html('<img src="' + esc(url) + '" alt="' + esc(name) + '">');
        }
        $('#lcAttachViewerModal').modal('show');
    }

    function revokePendingList(list) {
        (list || []).forEach(function (item) {
            if (item.previewUrl) {
                try { URL.revokeObjectURL(item.previewUrl); } catch (e) {}
            }
        });
    }

    function clearPendingAttachments() {
        // Wipe only the active pending list (member-mode uploads).
        revokePendingList(pendingAttachments);
        pendingAttachments = [];
        if (attachContext.mode === 'form' && attachContext.rowKey) {
            rowPendingMap[attachContext.rowKey] = pendingAttachments;
        }
        syncAttachBtnState();
    }

    function clearAllFormPending() {
        Object.keys(rowPendingMap).forEach(function (k) {
            revokePendingList(rowPendingMap[k]);
        });
        rowPendingMap = {};
        pendingAttachments = [];
        attachContext.rowKey = '';
        syncAttachBtnState();
    }

    function existingPersonForRow($row) {
        if ($row && $row.attr('data-edit-mode') === 'primary') {
            return profileCache || {};
        }
        var memberId = parseInt(($row && $row.attr('data-member-id')) || 0, 10) || 0;
        if (memberId > 0) {
            return familyCache.find(function (m) { return parseInt(m.id, 10) === memberId; }) || {};
        }
        return {};
    }

    function syncAttachBtnState() {
        $('#lcPaxRows .lc-pax-row').each(function () {
            var $row = $(this);
            var key = String($row.data('row-key') || '');
            var count = (rowPendingMap[key] || []).length;
            count += memberAttachments(existingPersonForRow($row)).length;
            $row.find('.lc-attach-btn').toggleClass('has-file', count > 0);
            if (count > 0) {
                $row.find('.lc-pax-attach-name').removeClass('d-none')
                    .text(count + ' attachment' + (count > 1 ? 's' : '') + ' ready');
            } else {
                $row.find('.lc-pax-attach-name').addClass('d-none').text('');
            }
        });
    }

    function currentAttachPerson() {
        var memberId = attachContext.memberId || 0;
        if (attachContext.mode === 'member' && memberId > 0) {
            return familyCache.find(function (m) { return parseInt(m.id, 10) === memberId; }) || null;
        }
        if (attachContext.mode === 'form') {
            if (attachContext.editPrimary) return profileCache || null;
            if (memberId > 0) {
                return familyCache.find(function (m) { return parseInt(m.id, 10) === memberId; }) || null;
            }
        }
        return null;
    }

    function renderAttachModal() {
        var $grid = $('#lcAttachGrid').empty();
        var items = [];
        var person = currentAttachPerson();
        if (person) {
            items = items.concat(memberAttachments(person));
        }

        pendingAttachments.forEach(function (p) {
            items.push({
                key: 'pending_' + p.id,
                label: 'New file',
                url: p.previewUrl || '',
                name: p.name || 'New file',
                isPdf: p.isPdf,
                pending: true,
                pendingId: p.id
            });
        });

        if (!items.length) {
            $grid.html('<div class="lc-attach-empty">No attachments yet. Click &ldquo;Add attachment&rdquo; below.</div>');
        } else {
            items.forEach(function (item) {
                var fileName = item.name || item.label || 'Attachment';
                var html = '<div class="lc-attach-tile" data-pending-id="' + esc(String(item.pendingId || '')) + '"'
                    + ' data-field="' + esc(item.key || '') + '"'
                    + ' data-input="' + esc(item.input || '') + '"'
                    + ' data-url="' + esc(item.url || '') + '"'
                    + ' data-name="' + esc(fileName) + '"'
                    + ' data-is-pdf="' + (item.isPdf ? '1' : '0') + '">';
                html += '<div class="lc-attach-tile-preview js-lc-attach-view" title="View">';
                if (item.isPdf) {
                    html += '<div class="lc-pdf-box"><i class="fas fa-file-pdf fa-2x mb-1"></i><div>' + esc(fileName) + '</div></div>';
                } else if (item.url) {
                    html += '<img src="' + esc(item.url) + '" alt="' + esc(fileName) + '">';
                } else {
                    html += '<span class="text-muted">File</span>';
                }
                html += '</div><div class="lc-attach-tile-meta">';
                html += '<span class="lc-attach-file-name" title="' + esc(fileName) + '">' + esc(fileName) + '</span>';
                html += '<div class="lc-attach-actions">';
                if (item.url || item.pending) {
                    html += '<button type="button" class="btn js-lc-attach-download" title="Download"><i class="fas fa-download"></i></button>';
                }
                if (item.pending) {
                    html += '<button type="button" class="btn js-lc-remove-pending" data-id="' + esc(String(item.pendingId)) + '" title="Delete"><i class="fas fa-trash"></i></button>';
                } else {
                    html += '<button type="button" class="btn js-lc-attach-edit" title="Edit / Replace"><i class="fas fa-pen"></i></button>';
                    html += '<button type="button" class="btn js-lc-attach-delete" title="Delete"><i class="fas fa-trash"></i></button>';
                }
                html += '</div></div></div>';
                $grid.append(html);
            });
        }

        var used = items.length;
        var left = Math.max(0, 3 - used);
        $('#lcAttachHint').text(left > 0
            ? ('You can add ' + left + ' more file' + (left > 1 ? 's' : '') + ' (max 3).')
            : 'Maximum of 3 attachments reached.');
        $('#lcAttachAddBtn').prop('disabled', left <= 0 || !!attachReplaceContext);
        $('#lcAttachErr').addClass('d-none').text('');
    }

    function applyAttachSaveResult(res) {
        if (res.family) familyCache = res.family;
        if (res.profile) profileCache = res.profile;
        bindMembersListActions();
        renderAttachModal();
        syncAttachBtnState();
    }

    function deleteSavedAttachment(field) {
        if (!field || !activeRef.refId) return;
        if (!confirm('Delete this attachment?')) return;
        var memberId = attachContext.memberId || 0;
        if (attachContext.editPrimary || (attachContext.mode === 'form' && !memberId)) {
            memberId = 0;
        }
        if (attachContext.mode === 'member') {
            memberId = attachContext.memberId || 0;
        }
        $('#lcAttachErr').addClass('d-none').text('');
        $.post('ajax/clear_contact_attachment.php', {
            contact_source: activeRef.source,
            ref_id: activeRef.refId,
            member_id: memberId,
            field: field
        }, null, 'json').done(function (res) {
            if (!res || !res.success) {
                $('#lcAttachErr').removeClass('d-none').text((res && res.message) || 'Could not delete attachment.');
                return;
            }
            applyAttachSaveResult(res);
            showAlert(res.message || 'Attachment removed.');
        }).fail(function (xhr) {
            $('#lcAttachErr').removeClass('d-none').text(parseFailMessage(xhr));
        });
    }

    function startReplaceAttachment(field, inputName) {
        if (!field || !inputName) return;
        var memberId = attachContext.memberId || 0;
        var isPrimary = !!attachContext.editPrimary || (attachContext.mode === 'form' && !memberId);
        if (attachContext.mode === 'member') {
            isPrimary = false;
            memberId = attachContext.memberId || 0;
        }
        attachReplaceContext = {
            input: inputName,
            key: field,
            memberId: memberId,
            isPrimary: isPrimary
        };
        $('#lcAttachFileInput').prop('multiple', false).val('');
        // If the file dialog is cancelled, clear replace mode
        $(window).one('focus', function () {
            setTimeout(function () {
                if (attachReplaceContext && !$('#lcAttachFileInput').val()) {
                    attachReplaceContext = null;
                    $('#lcAttachFileInput').prop('multiple', true);
                    renderAttachModal();
                }
            }, 400);
        });
        $('#lcAttachFileInput').trigger('click');
    }

    function replaceSavedAttachment(file) {
        var ctx = attachReplaceContext;
        attachReplaceContext = null;
        $('#lcAttachFileInput').prop('multiple', true);
        if (!ctx || !file || !activeRef.refId) {
            renderAttachModal();
            return;
        }

        var person = currentAttachPerson() || {};
        var fd = new FormData();
        fd.append('contact_source', activeRef.source);
        fd.append('ref_id', activeRef.refId);
        fd.append('title', person.title || '');
        fd.append('name', trimJoin([person.first_name, person.last_name]) || primaryDisplayName());
        fd.append('mobile', person.mobile || '');
        fd.append('email', person.email || '');
        fd.append('date_of_birth', person.date_of_birth || '');
        fd.append('gender', person.gender || '');
        fd.append('address', person.address_line1 || '');
        fd.append(ctx.input, file, file.name);

        var url = 'ajax/save_contact_profile.php';
        if (!ctx.isPrimary && ctx.memberId > 0) {
            url = 'ajax/save_contact_family.php';
            fd.append('member_id', ctx.memberId);
            fd.append('relation', person.relation || 'Spouse');
        }

        $('#lcAttachAddBtn').prop('disabled', true);
        $('#lcAttachErr').addClass('d-none').text('');
        $.ajax({
            url: url,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                $('#lcAttachErr').removeClass('d-none').text((res && res.message) || 'Could not replace attachment.');
                return;
            }
            if (res.family) familyCache = res.family;
            if (res.profile) profileCache = res.profile;
            applyAttachSaveResult(res);
            showAlert(res.message || 'Attachment updated.');
        }).fail(function (xhr) {
            $('#lcAttachErr').removeClass('d-none').text(parseFailMessage(xhr));
        }).always(function () {
            $('#lcAttachAddBtn').prop('disabled', false);
            renderAttachModal();
        });
    }

    function openAttachModal(opts) {
        opts = opts || {};
        attachContext = {
            mode: opts.mode || 'form',
            memberId: parseInt(opts.memberId, 10) || 0,
            rowKey: opts.rowKey ? String(opts.rowKey) : '',
            editPrimary: !!opts.editPrimary
        };
        if (attachContext.mode === 'form' && attachContext.rowKey) {
            if (!rowPendingMap[attachContext.rowKey]) {
                rowPendingMap[attachContext.rowKey] = [];
            }
            pendingAttachments = rowPendingMap[attachContext.rowKey];
        } else if (attachContext.mode === 'member') {
            pendingAttachments = [];
        }
        var title = opts.name || 'Member';
        $('#lcAttachModalName').text(title);
        renderAttachModal();
        $('#lcAttachModal').modal('show');
    }

    function appendPendingFiles(fileList) {
        var files = Array.prototype.slice.call(fileList || []);
        if (!files.length) return;

        var existingCount = pendingAttachments.length;
        if (attachContext.mode === 'form') {
            if (attachContext.editPrimary) {
                existingCount += memberAttachments(profileCache || {}).length;
            } else {
                var editId = attachContext.memberId || 0;
                if (editId > 0) {
                    var m = familyCache.find(function (x) { return parseInt(x.id, 10) === editId; });
                    existingCount += memberAttachments(m).length;
                }
            }
        } else if (attachContext.memberId > 0) {
            var mem = familyCache.find(function (x) { return parseInt(x.id, 10) === attachContext.memberId; });
            existingCount += memberAttachments(mem).length;
        }

        var room = Math.max(0, 3 - existingCount);
        if (room <= 0) {
            $('#lcAttachErr').removeClass('d-none').text('Maximum of 3 attachments allowed.');
            return;
        }

        files.slice(0, room).forEach(function (file) {
            var isPdf = /\.pdf$/i.test(file.name);
            var previewUrl = '';
            if (!isPdf && file.type && file.type.indexOf('image/') === 0) {
                previewUrl = URL.createObjectURL(file);
            }
            pendingAttachments.push({
                id: Date.now() + '_' + Math.random().toString(36).slice(2, 7),
                file: file,
                previewUrl: previewUrl,
                name: file.name,
                isPdf: isPdf
            });
        });

        if (files.length > room) {
            $('#lcAttachErr').removeClass('d-none').text('Only ' + room + ' more file(s) could be added (max 3).');
        }
        if (attachContext.mode === 'form' && attachContext.rowKey) {
            rowPendingMap[attachContext.rowKey] = pendingAttachments;
        }
        renderAttachModal();
        syncAttachBtnState();
    }

    function applyPendingToFormData(fd, existingPerson, pendingList) {
        existingPerson = existingPerson || {};
        pendingList = pendingList || [];
        var usedKeys = {};
        memberAttachments(existingPerson).forEach(function (a) { usedKeys[a.input] = true; });
        pendingList.forEach(function (item) {
            var slot = ATTACH_FIELDS.find(function (f) { return !usedKeys[f.input]; });
            if (!slot || !item.file) return;
            fd.append(slot.input, item.file, item.file.name);
            usedKeys[slot.input] = true;
        });
    }

    function setFooterAddMode() {
        $('#lcFamilyModalAddBtn')
            .attr('data-mode', 'add')
            .prop('disabled', false)
            .html('<i class="fas fa-user-plus mr-1"></i><span class="lc-footer-btn-label">Add family member</span>');
    }

    function setFooterSaveMode() {
        $('#lcFamilyModalAddBtn')
            .attr('data-mode', 'save')
            .prop('disabled', false)
            .html('<i class="fas fa-save mr-1"></i><span class="lc-footer-btn-label">Save</span>');
    }

    function optionHtml(list, selected) {
        return list.map(function (v) {
            return '<option value="' + esc(v) + '"' + (v === selected ? ' selected' : '') + '>' + esc(v) + '</option>';
        }).join('');
    }

    function refreshPaxRowChrome() {
        var $rows = $('#lcPaxRows .lc-pax-row');
        $rows.each(function (i) {
            var $row = $(this);
            $row.toggleClass('is-extra', i > 0);
            var $btn = $row.find('.js-lc-pax-row-action');
            if (editingMemberId || editingPrimary) {
                $btn.addClass('d-none');
                return;
            }
            $btn.removeClass('d-none');
            if (i === $rows.length - 1) {
                $btn.removeClass('is-remove').attr('title', 'Add another row')
                    .attr('data-action', 'add').html('<i class="fas fa-plus"></i>');
            } else {
                $btn.addClass('is-remove').attr('title', 'Remove row')
                    .attr('data-action', 'remove').html('<i class="fas fa-minus"></i>');
            }
        });
        syncAttachBtnState();
    }

    function normalizeRelation(rel, isPrimary) {
        rel = String(rel || '').trim();
        if (!rel || LEGACY_AGE_TYPES[rel]) {
            return isPrimary ? 'Self' : 'Spouse';
        }
        return rel;
    }

    function updateRowAge($row) {
        var age = ageFromDob($row.find('.js-lc-dob').val());
        $row.find('.js-lc-age').text(age ? '(' + age + ')' : '');
    }

    function buildPaxRow(data, isExtra) {
        data = data || {};
        paxRowSeq += 1;
        var key = 'r' + paxRowSeq;
        var memberId = data.id || '';
        var isPrimaryRow = data._isPrimary === true;
        var rel = normalizeRelation(data.relation, isPrimaryRow);
        var title = data.title || 'Mr';
        var name = data.name || trimJoin([data.first_name, data.last_name]) || '';
        var mobile = data.mobile || '';
        var email = data.email || '';
        var dobIso = toIsoDate(data.date_of_birth || '');
        var ageLabel = ageFromDob(dobIso);
        if (RELATION_OPTS.indexOf(rel) === -1) {
            RELATION_OPTS.push(rel);
        }
        if (TITLE_OPTS.indexOf(title) === -1 && title) {
            TITLE_OPTS.push(title);
        }

        var html = '<div class="lc-pax-row' + (isExtra ? ' is-extra' : '') + '" data-row-key="' + key + '" data-member-id="' + esc(String(memberId)) + '">';
        html += '<div><label>Relation</label><select class="form-control js-lc-relation">' + optionHtml(RELATION_OPTS, rel) + '</select></div>';
        html += '<div><label>Initial</label><select class="form-control js-lc-title">' + optionHtml(TITLE_OPTS, title) + '</select></div>';
        html += '<div class="lc-pax-name"><label>Name</label><input type="text" class="form-control js-lc-name" placeholder="Full name" value="' + esc(name) + '"></div>';
        html += '<div class="lc-pax-dob"><label>DOB <span class="lc-pax-age js-lc-age">' + (ageLabel ? '(' + esc(ageLabel) + ')' : '') + '</span></label>';
        html += '<input type="date" class="form-control js-lc-dob" value="' + esc(dobIso) + '"></div>';
        html += '<div><label>Mobile</label><input type="text" class="form-control js-lc-mobile" placeholder="Mobile" value="' + esc(mobile) + '"></div>';
        html += '<div class="lc-pax-email"><label>Email</label><input type="email" class="form-control js-lc-email" placeholder="Email" value="' + esc(email) + '"></div>';
        html += '<div><label>&nbsp;</label><button type="button" class="btn lc-attach-btn" title="Attachments"><i class="fas fa-paperclip"></i></button></div>';
        html += '<div><label>&nbsp;</label><button type="button" class="btn lc-pax-add-submit js-lc-pax-row-action" data-action="add" title="Add another row"><i class="fas fa-plus"></i></button></div>';
        html += '<p class="lc-pax-attach-name d-none"></p>';
        html += '</div>';

        rowPendingMap[key] = rowPendingMap[key] || [];
        return $(html);
    }

    function addPaxRow(data, isExtra) {
        var $row = buildPaxRow(data, !!isExtra);
        $('#lcPaxRows').append($row);
        refreshPaxRowChrome();
        return $row;
    }

    function hideInlineFamilyForm() {
        editingMemberId = 0;
        editingPrimary = false;
        $('#lcInlineAddWrap').removeClass('is-open');
        $('#lcPaxRows').empty();
        $('#lcFamilyFormErr').addClass('d-none').text('');
        $('.lc-pax-add-title').html('<i class="fas fa-user-plus"></i> Add Family Member');
        clearAllFormPending();
        setFooterAddMode();
    }

    function primaryAsFormData() {
        var p = profileCache || {};
        var c = contactCache || {};
        var display = primaryDisplayName();
        var name = display && display !== '—' ? display : '';
        // Strip title prefix from display name if title is also set separately
        var title = p.title || 'Mr';
        if (name) {
            name = String(name).replace(/^(Mr|Mrs|Ms|Master|Miss|Mstr)\.?\s+/i, '').trim();
        }
        if (!name) {
            name = trimJoin([p.first_name, p.last_name]);
        }
        return {
            relation: 'Self',
            title: title,
            first_name: name,
            last_name: '',
            mobile: p.mobile || c.customer_phone || activeRef.phone || '',
            email: p.email || c.customer_email || activeRef.email || '',
            date_of_birth: p.date_of_birth || '',
            photo: p.photo || '',
            id_proof_front: p.id_proof_front || '',
            id_proof_back: p.id_proof_back || '',
            _isPrimary: true
        };
    }

    function scrollFamilyModalToForm() {
        var $body = $('#lcFamilyModal .modal-body');
        var wrap = $('#lcInlineAddWrap').get(0);
        var body = $body.get(0);
        if (!body || !wrap || !$('#lcInlineAddWrap').hasClass('is-open')) return;
        // modal-dialog-scrollable scrolls .modal-body, not the window
        setTimeout(function () {
            var bodyRect = body.getBoundingClientRect();
            var wrapRect = wrap.getBoundingClientRect();
            var nextTop = body.scrollTop + (wrapRect.top - bodyRect.top) - 10;
            if (typeof body.scrollTo === 'function') {
                body.scrollTo({ top: Math.max(0, nextTop), behavior: 'smooth' });
            } else {
                body.scrollTop = Math.max(0, nextTop);
            }
        }, 80);
    }

    function showInlinePrimaryForm() {
        hideInlineFamilyForm();
        editingPrimary = true;
        $('.lc-pax-add-title').html('<i class="fas fa-user-edit"></i> Edit Primary Contact');
        var $row = addPaxRow(primaryAsFormData(), false);
        $row.attr('data-edit-mode', 'primary');
        $('#lcInlineAddWrap').addClass('is-open');
        setFooterSaveMode();
        refreshPaxRowChrome();
        syncAttachBtnState();
        scrollFamilyModalToForm();
        $('#lcPaxRows .js-lc-name').first().focus();
    }

    function showInlineFamilyForm(member) {
        hideInlineFamilyForm();
        editingMemberId = member && member.id ? parseInt(member.id, 10) : 0;
        if (editingMemberId) {
            $('.lc-pax-add-title').html('<i class="fas fa-user-edit"></i> Edit Family Member');
            addPaxRow(member, false);
        } else {
            $('.lc-pax-add-title').html('<i class="fas fa-user-plus"></i> Add Family Member');
            addPaxRow(null, false);
        }
        $('#lcInlineAddWrap').addClass('is-open');
        setFooterSaveMode();
        scrollFamilyModalToForm();
        $('#lcPaxRows .js-lc-name').first().focus();
    }

    function saveOnePaxRow($row) {
        var name = $.trim($row.find('.js-lc-name').val() || '');
        var memberId = parseInt($row.attr('data-member-id'), 10) || 0;
        var key = String($row.data('row-key') || '');
        var pending = rowPendingMap[key] || [];
        var isPrimary = $row.attr('data-edit-mode') === 'primary' || editingPrimary;
        var existing = existingPersonForRow($row);

        var fd = new FormData();
        fd.append('contact_source', activeRef.source);
        fd.append('ref_id', activeRef.refId);
        fd.append('title', $row.find('.js-lc-title').val() || '');
        fd.append('name', name);
        fd.append('mobile', $row.find('.js-lc-mobile').val() || '');
        fd.append('email', $row.find('.js-lc-email').val() || '');
        fd.append('date_of_birth', toIsoDate($row.find('.js-lc-dob').val()) || '');
        applyPendingToFormData(fd, existing, pending);

        if (isPrimary) {
            // Preserve profile fields not shown in the inline row
            fd.append('gender', (existing && existing.gender) || '');
            fd.append('address', (existing && existing.address_line1) || '');

            return $.ajax({
                url: 'ajax/save_contact_profile.php',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).then(function (res) {
                if (!res || !res.success) {
                    return $.Deferred().reject((res && res.message) || 'Save failed.').promise();
                }
                profileCache = res.profile || profileCache;
                if (profileCache) {
                    activeRef.name = personName(profileCache) || name;
                    activeRef.phone = profileCache.mobile || activeRef.phone;
                    activeRef.email = profileCache.email || activeRef.email;
                } else {
                    activeRef.name = name;
                    activeRef.phone = $row.find('.js-lc-mobile').val() || activeRef.phone;
                    activeRef.email = $row.find('.js-lc-email').val() || activeRef.email;
                }
                var $contactRow = $('tr[data-source="' + activeRef.source + '"][data-ref-id="' + activeRef.refId + '"]');
                if ($contactRow.length) {
                    $contactRow.find('.lc-contact-name').text(activeRef.name);
                    if (activeRef.phone) $contactRow.children('td').eq(3).text(activeRef.phone);
                    if (activeRef.email) $contactRow.find('.lc-contact-email').text(activeRef.email);
                    $contactRow.attr('data-profile', 'complete');
                    $contactRow.find('.lc-profile-status').removeClass('pending').text('Completed');
                }
                revokePendingList(pending);
                rowPendingMap[key] = [];
                return res;
            }, function (xhr) {
                return $.Deferred().reject(parseFailMessage(xhr)).promise();
            });
        }

        fd.append('member_id', memberId || '');
        fd.append('relation', $row.find('.js-lc-relation').val() || 'Spouse');
        fd.append('gender', (existing && existing.gender) || '');
        fd.append('address', (existing && existing.address_line1) || '');

        return $.ajax({
            url: 'ajax/save_contact_family.php',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).then(function (res) {
            if (!res || !res.success) {
                return $.Deferred().reject((res && res.message) || 'Save failed.').promise();
            }
            familyCache = res.family || [];
            revokePendingList(pending);
            rowPendingMap[key] = [];
            return res;
        }, function (xhr) {
            return $.Deferred().reject(parseFailMessage(xhr)).promise();
        });
    }

    function saveAllPaxRows() {
        var $rows = $('#lcPaxRows .lc-pax-row');
        var toSave = [];
        $rows.each(function () {
            var $row = $(this);
            var name = $.trim($row.find('.js-lc-name').val() || '');
            var mobile = $.trim($row.find('.js-lc-mobile').val() || '');
            var email = $.trim($row.find('.js-lc-email').val() || '');
            if (!name && !mobile && !email) return;
            toSave.push($row);
        });

        if (!toSave.length) {
            $('#lcFamilyFormErr').removeClass('d-none').text(
                editingPrimary ? 'Please enter the contact name.' : 'Please enter at least one family member name.'
            );
            $('#lcPaxRows .js-lc-name').first().focus();
            return;
        }

        for (var i = 0; i < toSave.length; i++) {
            if (!$.trim(toSave[i].find('.js-lc-name').val() || '')) {
                $('#lcFamilyFormErr').removeClass('d-none').text('Please enter the name for every filled row.');
                toSave[i].find('.js-lc-name').focus();
                return;
            }
        }

        var $btn = $('#lcFamilyModalAddBtn').prop('disabled', true);
        $('#lcFamilyFormErr').addClass('d-none').text('');
        var wasPrimary = editingPrimary;

        var chain = $.Deferred().resolve().promise();
        toSave.forEach(function ($row) {
            chain = chain.then(function () {
                return saveOnePaxRow($row);
            });
        });

        chain.done(function () {
            bindMembersListActions();
            hideInlineFamilyForm();
            showAlert(wasPrimary
                ? 'Primary contact updated.'
                : (toSave.length > 1 ? 'Family members saved.' : 'Family member saved.'));
        }).fail(function (msg) {
            $('#lcFamilyFormErr').removeClass('d-none').text(msg || 'Save failed.');
            bindMembersListActions();
        }).always(function () {
            $btn.prop('disabled', false);
            if ($('#lcInlineAddWrap').hasClass('is-open')) {
                setFooterSaveMode();
            } else {
                setFooterAddMode();
            }
        });
    }

    function renderPrimaryCard() {
        var primary = profileCache || {};
        var contact = contactCache || {};
        var name = primaryDisplayName();
        var mobile = primary.mobile || contact.customer_phone || activeRef.phone || '';
        var email = primary.email || contact.customer_email || activeRef.email || '';
        var html = '';
        html += '<div class="lc-primary-card">';
        if (primary.profile_photo) {
            html += '<button type="button" class="lc-primary-avatar has-photo js-lc-avatar-view" title="View profile photo" data-photo="' + esc(primary.profile_photo) + '" data-name="' + esc(name) + '">';
            html += '<img src="' + esc(primary.profile_photo) + '" alt=""></button>';
        } else {
            html += '<span class="lc-primary-avatar">' + esc(initialsFromName(name)) + '</span>';
        }
        html += '<div class="lc-primary-meta">';
        html += '<div class="lc-primary-badge"><i class="fas fa-crown"></i> Primary Contact</div>';
        html += '<h4 class="lc-primary-name">' + esc(name) + ' <span class="lc-verified" title="Verified"><i class="fas fa-check"></i></span></h4>';
        html += '<div class="lc-primary-details">';
        html += '<span><i class="fas fa-phone-alt"></i>' + esc(mobile || 'No mobile') + '</span>';
        html += '<span><i class="fas fa-envelope"></i>' + esc(email || 'No email') + '</span>';
        var ageLabel = ageFromDob(primary.date_of_birth);
        if (ageLabel) {
            html += '<span><i class="fas fa-birthday-cake"></i>' + esc(ageLabel) + '</span>';
        }
        html += '</div></div>';
        html += '<div class="lc-primary-actions">';
        html += '<button type="button" class="btn btn-sm js-lc-edit-primary" title="Edit contact"><i class="fas fa-pen mr-1"></i>Edit</button>';
        if (activeRef.source === 'manual') {
            html += '<button type="button" class="btn btn-sm js-lc-del-primary" title="Delete contact"><i class="fas fa-trash mr-1"></i>Delete</button>';
        }
        html += '</div></div>';
        $('#lcPrimaryCard').html(html);
    }

    function thumbHtml(url) {
        if (!url) return '';
        if (/\.pdf$/i.test(url)) {
            return '<a href="' + esc(url) + '" target="_blank" rel="noopener" class="lc-thumb-pdf" title="PDF"><i class="fas fa-file-pdf"></i></a>';
        }
        return '<a href="' + esc(url) + '" target="_blank" rel="noopener"><img src="' + esc(url) + '" alt=""></a>';
    }

    function renderMembersList() {
        renderPrimaryCard();
        var $body = $('#lcFamilyListBody');
        var count = familyCache ? familyCache.length : 0;
        updateFamilyCount(activeRef.source, activeRef.refId, count);

        if (!count) {
            $body.html('<div class="lc-member-empty">No family members yet. Click <strong>Add family member</strong> below.</div>');
            return;
        }

        var html = '';
        familyCache.forEach(function (m) {
            var name = personName(m) || '—';
            var bits = [];
            if (m.relation) bits.push(m.relation);
            var ageLabel = ageFromDob(m.date_of_birth);
            if (ageLabel) bits.push(ageLabel);
            if (m.mobile) bits.push(m.mobile);
            if (m.email) bits.push(m.email);
            var atts = memberAttachments(m);
            html += '<div class="lc-member-item" data-member-id="' + m.id + '">';
            html += '<span class="lc-member-avatar">' + esc(initialsFromName(name)) + '</span>';
            html += '<div class="lc-member-info">';
            html += '<div class="lc-member-name">' + esc(name) + '</div>';
            html += '<div class="lc-member-sub">' + esc(bits.join(' · ') || 'Family member') + '</div>';
            if (atts.length) {
                html += '<div class="lc-member-thumbs">';
                atts.forEach(function (a) { html += thumbHtml(a.url); });
                html += '</div>';
            }
            html += '</div>';
            html += '<div class="lc-member-actions">';
            html += '<button type="button" class="btn btn-sm js-lc-member-view" title="View attachments"><i class="far fa-eye"></i></button>';
            html += '<button type="button" class="btn btn-sm js-lc-edit-family" title="Edit"><i class="fas fa-pen"></i></button>';
            html += '<button type="button" class="btn btn-sm js-lc-del-family" data-id="' + m.id + '" title="Delete"><i class="fas fa-trash"></i></button>';
            html += '</div></div>';
        });
        $body.html(html);
    }

    function bindMembersListActions() {
        renderMembersList();
        $('#lcFamilyListBody .js-lc-edit-family').off('click').on('click', function () {
            var id = parseInt($(this).closest('.lc-member-item').data('member-id'), 10);
            var member = familyCache.find(function (m) { return parseInt(m.id, 10) === id; });
            if (member) showInlineFamilyForm(member);
        });
        $('#lcFamilyListBody .js-lc-member-view, #lcFamilyListBody .js-lc-member-attach').off('click').on('click', function () {
            var id = parseInt($(this).closest('.lc-member-item').data('member-id'), 10);
            var member = familyCache.find(function (m) { return parseInt(m.id, 10) === id; });
            pendingAttachments = [];
            attachContext.rowKey = '';
            openAttachModal({
                mode: 'member',
                memberId: id,
                name: member ? personName(member) : 'Member'
            });
        });
        $('#lcPrimaryCard .js-lc-edit-primary').off('click').on('click', function () {
            openProfileModal(activeRef.source, activeRef.refId, activeRef.name, activeRef.phone, activeRef.email, false);
        });
        $('#lcPrimaryCard .js-lc-del-primary').off('click').on('click', function () {
            deleteManualContact(activeRef.refId, true);
        });
    }

    function openProfileModal(source, refId, name, phone, email, isNew) {
        setActiveRef(source, refId, { name: name, phone: phone, email: email });
        $('#lcProfileLeadName').text(isNew ? 'New contact' : (name || ''));
        $('#lcProfileModalTitle').text(isNew ? 'Add Contact' : 'Edit Contact');
        $('#lcProfileErr').addClass('d-none').text('');
        $('#lcProfileSaveBtn').text(isNew ? 'Save' : 'Update');
        $('#lcCitySearchDropdown').hide().empty();

        var $form = $('#lcProfileForm');
        if (isNew) {
            $form[0].reset();
            resetProfilePhotoField();
            setPreview($form, 'pan', '');
            setPreview($form, 'aadhar', '');
            setPreview($form, 'other', '');
            resetContactRows([{ name: '', email: '', mobile: '' }]);
            $('#lcPersonMobile').val('');
            $('#lcPersonEmail').val('');
            $('#lcProfileModal').modal('show');
            return;
        }

        loadContactDetails(source, refId).done(function (res) {
            if (!res || !res.success) {
                showAlert((res && res.message) || 'Could not load contact.', 'danger');
                return;
            }
            var fallback = res.contact || res.lead || {
                customer_name: name,
                customer_phone: phone,
                customer_email: email
            };
            profileCache = res.profile || null;
            contactCache = fallback;
            fillPersonForm($form, res.profile, fallback);
            $('#lcProfileModal').modal('show');
        }).fail(function (xhr) {
            showAlert(parseFailMessage(xhr, 'Could not load contact details.'), 'danger');
        });
    }

    function openMembersModal(source, refId, name, phone, email, opts) {
        opts = opts || {};
        setActiveRef(source, refId, { name: name, phone: phone, email: email });
        $('#lcFamilyLeadName').text(name || '');
        $('#lcPrimaryCard').html('<div class="text-muted text-center py-3">Loading…</div>');
        $('#lcFamilyListBody').html('');
        hideInlineFamilyForm();
        $('#lcFamilyModal').modal('show');

        loadContactDetails(source, refId).done(function (res) {
            if (!res || !res.success) {
                $('#lcPrimaryCard').html('<div class="text-danger text-center py-3">Could not load contact details.</div>');
                return;
            }
            profileCache = res.profile || null;
            contactCache = res.contact || res.lead || {
                customer_name: name,
                customer_phone: phone,
                customer_email: email
            };
            familyCache = res.family || [];
            bindMembersListActions();
            var displayName = primaryDisplayName();
            if (displayName && displayName !== '—') {
                $('#lcFamilyLeadName').text(displayName);
                activeRef.name = displayName;
            }
            if (opts.editPrimary) {
                showInlinePrimaryForm();
                // Extra pass after layout settles (long member lists)
                setTimeout(scrollFamilyModalToForm, 200);
            } else {
                hideInlineFamilyForm();
            }
        }).fail(function () {
            $('#lcPrimaryCard').html('<div class="text-danger text-center py-3">Could not load contact details.</div>');
        });
    }

    function refreshMembersModalIfOpen() {
        if (!$('#lcFamilyModal').hasClass('show') || !activeRef.refId) {
            return;
        }
        loadContactDetails(activeRef.source, activeRef.refId).done(function (res) {
            if (!res || !res.success) return;
            profileCache = res.profile || null;
            contactCache = res.contact || res.lead || contactCache;
            familyCache = res.family || [];
            bindMembersListActions();
        });
    }

    function saveMemberAttachmentsOnly() {
        var memberId = attachContext.memberId || 0;
        if (!memberId || !pendingAttachments.length) {
            $('#lcAttachModal').modal('hide');
            return;
        }
        var member = familyCache.find(function (m) { return parseInt(m.id, 10) === memberId; });
        if (!member) {
            $('#lcAttachErr').removeClass('d-none').text('Member not found.');
            return;
        }

        var fd = new FormData();
        fd.append('contact_source', activeRef.source);
        fd.append('ref_id', activeRef.refId);
        fd.append('member_id', memberId);
        fd.append('relation', member.relation || 'Spouse');
        fd.append('title', member.title || '');
        fd.append('name', trimJoin([member.first_name, member.last_name]));
        fd.append('mobile', member.mobile || '');
        fd.append('email', member.email || '');
        fd.append('date_of_birth', member.date_of_birth || '');
        fd.append('gender', member.gender || '');
        fd.append('address', member.address_line1 || '');

        var usedKeys = {};
        memberAttachments(member).forEach(function (a) { usedKeys[a.input] = true; });
        pendingAttachments.forEach(function (item) {
            var slot = ATTACH_FIELDS.find(function (f) { return !usedKeys[f.input]; });
            if (!slot || !item.file) return;
            fd.append(slot.input, item.file, item.file.name);
            usedKeys[slot.input] = true;
        });

        $('#lcAttachAddBtn').prop('disabled', true);
        $.ajax({
            url: 'ajax/save_contact_family.php',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                $('#lcAttachErr').removeClass('d-none').text((res && res.message) || 'Could not save attachments.');
                return;
            }
            familyCache = res.family || [];
            clearPendingAttachments();
            bindMembersListActions();
            renderAttachModal();
            showAlert(res.message || 'Attachments saved.');
        }).fail(function (xhr) {
            $('#lcAttachErr').removeClass('d-none').text(parseFailMessage(xhr));
        }).always(function () {
            $('#lcAttachAddBtn').prop('disabled', false);
        });
    }

    function deleteManualContact(id, closeMembersModal) {
        id = parseInt(id, 10) || 0;
        if (!id) return;
        if (!confirm('Delete this contact and all family members?')) return;
        $.post('ajax/delete_contact.php', { contact_id: id }, null, 'json')
            .done(function (res) {
                if (!res || !res.success) {
                    showAlert((res && res.message) || 'Could not delete.', 'danger');
                    return;
                }
                if (closeMembersModal) {
                    $('#lcFamilyModal').modal('hide');
                }
                $('tr[data-source="manual"][data-ref-id="' + id + '"]').fadeOut(200, function () {
                    $(this).remove();
                    renderContactTable();
                });
                showAlert(res.message || 'Contact deleted.');
            })
            .fail(function (xhr) {
                showAlert(parseFailMessage(xhr, 'Could not delete contact.'), 'danger');
            });
    }

    var contactPage = 1;
    var contactsPerPage = 10;

    function filteredContactRows() {
        var query = String($('#lcContactSearch').val() || '').toLowerCase().trim();
        var source = String($('#lcSourceFilter').val() || '');
        var profile = String($('#lcProfileFilter').val() || '');
        var family = String($('#lcFamilyFilter').val() || '');
        var dateRange = parseInt($('#lcDateFilter').val(), 10) || 0;
        var now = Math.floor(Date.now() / 1000);
        var minCreated = dateRange > 0 ? now - (dateRange * 86400) : 0;

        var $rows = $('#contactsTable tbody tr[data-source]').filter(function () {
            var $row = $(this);
            var created = parseInt($row.attr('data-created'), 10) || 0;
            return (query === '' || String($row.attr('data-search') || '').indexOf(query) !== -1)
                && (source === '' || String($row.attr('data-source')) === source)
                && (profile === '' || String($row.attr('data-profile')) === profile)
                && (family === '' || String($row.attr('data-family')) === family)
                && (minCreated === 0 || created >= minCreated);
        }).get();

        var sort = String($('#lcSortFilter').val() || 'newest');
        $rows.sort(function (a, b) {
            var $a = $(a);
            var $b = $(b);
            if (sort === 'oldest') {
                return (parseInt($a.attr('data-created'), 10) || 0) - (parseInt($b.attr('data-created'), 10) || 0);
            }
            if (sort === 'name_asc') {
                return String($a.attr('data-name') || '').localeCompare(String($b.attr('data-name') || ''));
            }
            if (sort === 'name_desc') {
                return String($b.attr('data-name') || '').localeCompare(String($a.attr('data-name') || ''));
            }
            if (sort === 'family_desc') {
                return (parseInt($b.attr('data-family-count'), 10) || 0) - (parseInt($a.attr('data-family-count'), 10) || 0);
            }
            return (parseInt($b.attr('data-created'), 10) || 0) - (parseInt($a.attr('data-created'), 10) || 0);
        });

        return $( $rows );
    }

    function updateSummaryStrip($rows) {
        var total = $rows.length;
        var withFamily = 0;
        var profiled = 0;
        var recent = 0;
        var weekAgo = Math.floor(Date.now() / 1000) - (7 * 86400);
        $rows.each(function () {
            var $row = $(this);
            if (String($row.attr('data-family')) === 'with') withFamily++;
            if (String($row.attr('data-profile')) === 'complete') profiled++;
            if ((parseInt($row.attr('data-created'), 10) || 0) >= weekAgo) recent++;
        });
        $('#lcSumTotal').text(total.toLocaleString('en-IN'));
        $('#lcSumFamily').text(withFamily.toLocaleString('en-IN'));
        $('#lcSumProfiled').text(profiled.toLocaleString('en-IN'));
        $('#lcSumRecent').text(recent.toLocaleString('en-IN'));
    }

    function renderContactTable() {
        $('#lcFilteredEmpty').remove();
        contactsPerPage = parseInt($('#lcPerPage').val(), 10) || 10;
        var $allRows = $('#contactsTable tbody tr[data-source]');
        var $rows = filteredContactRows();
        updateSummaryStrip($rows);

        var total = $rows.length;
        var pages = Math.max(1, Math.ceil(total / contactsPerPage));
        if (contactPage > pages) contactPage = pages;
        if (contactPage < 1) contactPage = 1;
        var start = (contactPage - 1) * contactsPerPage;
        var end = Math.min(start + contactsPerPage, total);

        $allRows.hide();
        // Re-append in sorted order within tbody for consistent paging display
        var $tbody = $('#contactsTable tbody');
        $rows.each(function () { $tbody.append(this); });
        $rows.slice(start, end).show();

        if ($allRows.length && total === 0) {
            $tbody.append('<tr id="lcFilteredEmpty"><td colspan="10" class="text-center text-muted py-4">No contacts match the selected filters.</td></tr>');
        }
        $('#lcContactInfo').text(total
            ? 'Showing ' + (start + 1) + ' to ' + end + ' of ' + total + ' contacts'
            : 'No contacts match these filters');

        var html = '';
        html += '<button type="button" class="lc-page-btn" data-page="1" title="First"' + (contactPage <= 1 ? ' disabled' : '') + '><i class="fas fa-angle-double-left"></i></button>';
        html += '<button type="button" class="lc-page-btn" data-page="' + (contactPage - 1) + '" title="Previous"' + (contactPage <= 1 ? ' disabled' : '') + '><i class="fas fa-angle-left"></i></button>';
        var first = Math.max(1, contactPage - 2);
        var last = Math.min(pages, first + 4);
        first = Math.max(1, last - 4);
        for (var page = first; page <= last; page++) {
            html += '<button type="button" class="lc-page-btn' + (page === contactPage ? ' active' : '') + '" data-page="' + page + '">' + page + '</button>';
        }
        html += '<button type="button" class="lc-page-btn" data-page="' + (contactPage + 1) + '" title="Next"' + (contactPage >= pages ? ' disabled' : '') + '><i class="fas fa-angle-right"></i></button>';
        html += '<button type="button" class="lc-page-btn" data-page="' + pages + '" title="Last"' + (contactPage >= pages ? ' disabled' : '') + '><i class="fas fa-angle-double-right"></i></button>';
        $('#lcContactPagination').html(html);
        $('#lcSelectAll').prop('checked', false);
    }

    $(function () {
        $('#lcAddContactBtn').on('click', function () {
            openProfileModal('manual', 0, '', '', '', true);
        });

        $(document).on('click', '.js-lc-view', function () {
            $('.lc-more').removeClass('open');
            var r = btnRef($(this));
            openMembersModal(r.source, r.refId, r.name, r.phone, r.email);
        });

        $(document).on('click', '.js-lc-avatar-view', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var url = String($(this).attr('data-photo') || $(this).closest('tr').attr('data-photo') || '').trim();
            if (!url) {
                return;
            }
            var name = String($(this).attr('data-name') || $(this).closest('tr').find('.lc-contact-name').text() || 'Profile photo').trim();
            openAttachViewer({
                url: url,
                name: name || 'Profile photo',
                isPdf: false,
                titleHtml: '<i class="far fa-user-circle mr-2"></i>Profile photo'
            });
        });

        $(document).on('click', '.js-lc-edit', function () {
            $('.lc-more').removeClass('open');
            var r = btnRef($(this));
            openProfileModal(r.source, r.refId, r.name, r.phone, r.email, false);
        });

        $(document).on('click', '.js-lc-docs', function () {
            $('.lc-more').removeClass('open');
            var r = btnRef($(this));
            openMembersModal(r.source, r.refId, r.name, r.phone, r.email);
        });

        $(document).on('click', '.js-lc-more', function (e) {
            e.stopPropagation();
            var $wrap = $(this).closest('.lc-more');
            $('.lc-more').not($wrap).removeClass('open');
            $wrap.toggleClass('open');
        });

        $(document).on('click', function () {
            $('.lc-more').removeClass('open');
        });

        $('#lcSelectAll').on('change', function () {
            var checked = $(this).is(':checked');
            $('#contactsTable tbody tr[data-source]:visible .lc-row-check').prop('checked', checked);
        });

        $('#lcRefreshContacts').on('click', function () {
            window.location.reload();
        });

        $('#lcFamilyModalAddBtn').on('click', function () {
            var mode = $(this).attr('data-mode') || 'add';
            if (mode === 'save') {
                saveAllPaxRows();
            } else {
                showInlineFamilyForm(null);
            }
        });

        $(document).on('change input', '#lcPaxRows .js-lc-dob', function () {
            updateRowAge($(this).closest('.lc-pax-row'));
        });

        $(document).on('click', '#lcPaxRows .js-lc-pax-row-action', function () {
            var action = $(this).attr('data-action') || 'add';
            if (action === 'remove') {
                var $row = $(this).closest('.lc-pax-row');
                var key = String($row.data('row-key') || '');
                revokePendingList(rowPendingMap[key]);
                delete rowPendingMap[key];
                $row.remove();
                refreshPaxRowChrome();
                return;
            }
            if (editingMemberId || editingPrimary) return;
            addPaxRow(null, true);
            $('#lcPaxRows .js-lc-name').last().focus();
        });

        $(document).on('click', '#lcPaxRows .lc-attach-btn', function () {
            var $row = $(this).closest('.lc-pax-row');
            openAttachModal({
                mode: 'form',
                memberId: parseInt($row.attr('data-member-id'), 10) || 0,
                rowKey: String($row.data('row-key') || ''),
                editPrimary: $row.attr('data-edit-mode') === 'primary' || editingPrimary,
                name: $.trim($row.find('.js-lc-name').val()) || (editingPrimary ? 'Primary contact' : 'New member')
            });
        });

        $('#lcAttachAddBtn').on('click', function () {
            attachReplaceContext = null;
            $('#lcAttachFileInput').prop('multiple', true).val('').trigger('click');
        });

        $('#lcAttachFileInput').on('change', function () {
            var files = this.files;
            if (attachReplaceContext) {
                var file = files && files[0] ? files[0] : null;
                $(this).val('');
                replaceSavedAttachment(file);
                return;
            }
            appendPendingFiles(files);
            $(this).val('');
            if (attachContext.mode === 'member' && attachContext.memberId > 0 && pendingAttachments.length) {
                saveMemberAttachmentsOnly();
            }
        });

        $(document).on('click', '#lcAttachGrid .js-lc-attach-view', function () {
            var $tile = $(this).closest('.lc-attach-tile');
            openAttachViewer({
                url: $tile.attr('data-url') || '',
                name: $tile.attr('data-name') || 'Attachment',
                isPdf: $tile.attr('data-is-pdf') === '1'
            });
        });

        $(document).on('click', '#lcAttachGrid .js-lc-attach-download', function (e) {
            e.stopPropagation();
            var $tile = $(this).closest('.lc-attach-tile');
            var url = $tile.attr('data-url') || '';
            var name = $tile.attr('data-name') || fileNameFromUrl(url);
            if (!url) {
                var pendingId = String($tile.attr('data-pending-id') || '');
                var pending = pendingAttachments.find(function (p) { return String(p.id) === pendingId; });
                if (pending && pending.file) {
                    url = URL.createObjectURL(pending.file);
                    downloadAttachment(url, pending.name || name);
                    setTimeout(function () { try { URL.revokeObjectURL(url); } catch (err) {} }, 1500);
                    return;
                }
            }
            downloadAttachment(url, name);
        });

        $(document).on('click', '#lcAttachGrid .js-lc-attach-edit', function (e) {
            e.stopPropagation();
            var $tile = $(this).closest('.lc-attach-tile');
            startReplaceAttachment($tile.attr('data-field') || '', $tile.attr('data-input') || '');
        });

        $(document).on('click', '#lcAttachGrid .js-lc-attach-delete', function (e) {
            e.stopPropagation();
            deleteSavedAttachment($(this).closest('.lc-attach-tile').attr('data-field') || '');
        });

        $('#lcViewerDownloadBtn, #lcViewerDownloadBtnFooter').on('click', function () {
            downloadAttachment(viewerContext.url, viewerContext.name);
        });

        $(document).on('click', '.js-lc-remove-pending', function () {
            var id = String($(this).data('id') || '');
            pendingAttachments = pendingAttachments.filter(function (p) {
                if (p.id === id) {
                    if (p.previewUrl) {
                        try { URL.revokeObjectURL(p.previewUrl); } catch (e) {}
                    }
                    return false;
                }
                return true;
            });
            if (attachContext.mode === 'form' && attachContext.rowKey) {
                rowPendingMap[attachContext.rowKey] = pendingAttachments;
            }
            renderAttachModal();
            syncAttachBtnState();
        });

        $(document).on('click', '.js-lc-delete-contact', function () {
            $('.lc-more').removeClass('open');
            deleteManualContact($(this).data('ref-id'), false);
        });

        $('#lcProfileForm').on('submit', function (e) {
            e.preventDefault();
            syncContactRowFields();
            var email = $.trim($('#lcPersonEmail').val() || '');
            if (!email) {
                $('#lcProfileErr').removeClass('d-none').text('Email is required.');
                $('#lcContactRows .js-lc-c-email').first().focus();
                return;
            }
            var $btn = $('#lcProfileSaveBtn').prop('disabled', true);
            $('#lcProfileErr').addClass('d-none').text('');
            $.ajax({
                url: 'ajax/save_contact_profile.php',
                type: 'POST',
                data: new FormData(this),
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function (res) {
                if (!res || !res.success) {
                    $('#lcProfileErr').removeClass('d-none').text((res && res.message) || 'Save failed.');
                    return;
                }
                if (res.reload) {
                    window.location.reload();
                    return;
                }
                var src = res.source || $('#lcProfileSource').val();
                var rid = res.ref_id || $('#lcProfileRefId').val();
                var $contactRow = $('tr[data-source="' + src + '"][data-ref-id="' + rid + '"]');
                $contactRow.attr('data-profile', 'complete');
                $contactRow.find('.lc-profile-status').removeClass('pending').text('Completed');
                var savedName = $.trim($('#lcProfileForm [name=name]').val() || '');
                if (savedName) {
                    $contactRow.find('.lc-contact-name').text(savedName);
                    activeRef.name = savedName;
                    $('#lcFamilyLeadName').text(savedName);
                }
                var savedMobile = $.trim($('#lcPersonMobile').val() || '');
                if (savedMobile) {
                    $contactRow.children('td').eq(3).text(savedMobile);
                    activeRef.phone = savedMobile;
                }
                var savedEmail = $.trim($('#lcPersonEmail').val() || '');
                if (savedEmail) {
                    $contactRow.find('.lc-contact-email').text(savedEmail);
                    activeRef.email = savedEmail;
                }
                if (res.profile) {
                    profileCache = res.profile;
                    updateContactRowAvatar($contactRow, res.profile.profile_photo || '', savedName || activeRef.name);
                }
                $('#lcProfileModal').modal('hide');
                showAlert(res.message || 'Saved.');
                renderContactTable();
                refreshMembersModalIfOpen();
            }).fail(function (xhr) {
                $('#lcProfileErr').removeClass('d-none').text(parseFailMessage(xhr));
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        $(document).on('click', '#lcContactRows .js-lc-contact-add', function () {
            $('#lcContactRows').append(contactRowHtml({ name: '', email: '', mobile: '' }, false));
        });

        $('#lcProfilePhotoInput').on('change', function () {
            var file = this.files && this.files[0] ? this.files[0] : null;
            $('#lcClearProfilePhoto').val('');
            if (!file) {
                return;
            }
            if (file.type && file.type.indexOf('image/') !== 0) {
                showAlert('Please choose an image file for the profile photo.', 'danger');
                $(this).val('');
                return;
            }
            var reader = new FileReader();
            reader.onload = function (ev) {
                setProfilePhotoPreview('', ev.target.result);
            };
            reader.readAsDataURL(file);
        });

        $('#lcProfilePhotoClear').on('click', function () {
            $('#lcProfilePhotoInput').val('');
            $('#lcClearProfilePhoto').val('1');
            setProfilePhotoPreview('');
        });

        $(document).on('click', '#lcContactRows .js-lc-contact-remove', function () {
            $(this).closest('.lc-contact-row').remove();
            if (!$('#lcContactRows .lc-contact-row').length) {
                resetContactRows([{ name: '', email: '', mobile: '' }]);
            }
        });
        $(document).on('input', '#lcProfileForm [name=name]', function () {
            var $firstName = $('#lcContactRows .lc-contact-row').first().find('.js-lc-c-name');
            if (!$firstName.data('touched')) {
                $firstName.val($(this).val());
            }
        });
        $(document).on('input', '#lcContactRows .js-lc-c-name', function () {
            $(this).data('touched', true);
        });
        $(document).on('input', '#lcPersonCity', function () {
            var q = $.trim($(this).val() || '');
            clearTimeout(citySearchTimer);
            if (q.length < 2) {
                $('#lcCitySearchDropdown').hide().empty();
                return;
            }
            citySearchTimer = setTimeout(function () {
                searchContactCities(q);
            }, 250);
        });
        $(document).on('click', '#lcCitySearchDropdown .item', function () {
            $('#lcPersonCity').val($(this).data('label') || $(this).text());
            $('#lcCitySearchDropdown').hide().empty();
        });
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.lc-city-wrap').length) {
                $('#lcCitySearchDropdown').hide();
            }
        });

        $(document).on('click', '.js-lc-del-family', function () {
            if (!confirm('Remove this family member?')) return;
            var memberId = $(this).data('id');
            $.post('ajax/delete_contact_family.php', {
                member_id: memberId,
                contact_source: activeRef.source,
                ref_id: activeRef.refId
            }, null, 'json')
                .done(function (res) {
                    if (!res || !res.success) {
                        showAlert((res && res.message) || 'Could not delete.', 'danger');
                        return;
                    }
                    familyCache = res.family || [];
                    bindMembersListActions();
                    if (String(editingMemberId) === String(memberId)) {
                        hideInlineFamilyForm();
                    }
                    showAlert(res.message || 'Removed.');
                })
                .fail(function (xhr) {
                    showAlert(parseFailMessage(xhr, 'Could not delete family member.'), 'danger');
                });
        });

        $('#lcProfileModal').on('hidden.bs.modal', function () {
            if ($('#lcFamilyModal').hasClass('show')) {
                $('body').addClass('modal-open');
            }
        });
        $('#lcAttachModal').on('hidden.bs.modal', function () {
            attachReplaceContext = null;
            $('#lcAttachFileInput').prop('multiple', true);
            if ($('#lcFamilyModal').hasClass('show') || $('#lcAttachViewerModal').hasClass('show')) {
                $('body').addClass('modal-open');
            }
            if (attachContext.mode === 'form') {
                syncAttachBtnState();
            } else {
                clearPendingAttachments();
            }
        });

        $('#lcAttachViewerModal').on('hidden.bs.modal', function () {
            $('#lcViewerFrame').empty();
            viewerContext = { url: '', name: '', isPdf: false };
            if ($('#lcAttachModal').hasClass('show') || $('#lcFamilyModal').hasClass('show')) {
                $('body').addClass('modal-open');
            }
        });

        $('#lcFamilyModal').on('hidden.bs.modal', function () {
            hideInlineFamilyForm();
            $('#lcAttachModal').modal('hide');
            $('#lcAttachViewerModal').modal('hide');
        });

        $('#lcContactSearch').on('input', function () {
            contactPage = 1;
            renderContactTable();
        });
        $('#lcSourceFilter, #lcProfileFilter, #lcFamilyFilter, #lcDateFilter, #lcSortFilter, #lcPerPage').on('change', function () {
            contactPage = 1;
            renderContactTable();
        });
        $(document).on('click', '.lc-page-btn:not(:disabled)', function () {
            contactPage = parseInt($(this).attr('data-page'), 10) || 1;
            renderContactTable();
        });

        $('#lcExportContacts').on('click', function () {
            var rows = [['Contact ID', 'Name', 'Email', 'Mobile', 'Source', 'Family Members', 'Added On', 'Profile Status', 'Last Activity']];
            filteredContactRows().each(function () {
                var cells = $(this).children('td');
                rows.push([
                    cells.eq(1).text().trim(),
                    cells.eq(2).find('.lc-contact-name').text().trim(),
                    cells.eq(2).find('.lc-contact-email').text().trim(),
                    cells.eq(3).text().trim(),
                    cells.eq(4).text().trim(),
                    cells.eq(5).text().trim(),
                    cells.eq(6).text().trim(),
                    cells.eq(7).text().trim(),
                    cells.eq(8).text().trim()
                ]);
            });
            var csv = rows.map(function (row) {
                return row.map(function (value) {
                    return '"' + String(value || '').replace(/"/g, '""') + '"';
                }).join(',');
            }).join('\r\n');
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = 'contacts.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });

        renderContactTable();
    });

})(jQuery);
