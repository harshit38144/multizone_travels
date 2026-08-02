(function () {
    'use strict';

    /** Resolve ajax URL from current page path — ignores <base href> */
    function mailAjaxUrl(file) {
        var path = window.location.pathname || '';
        if (path.indexOf('/mail/') !== -1) {
            return path.replace(/[^/]*$/, '') + 'ajax/' + file;
        }
        return 'mail/ajax/' + file;
    }

    function mailInboxUrl(params) {
        params = params || {};
        var path = window.location.pathname || 'mail/inbox.php';
        if (path.indexOf('/mail/') === -1) {
            path = 'mail/inbox.php';
        } else {
            path = path.replace(/[^/]*$/, 'inbox.php');
        }
        var q = [];
        if (params.folder) {
            q.push('folder=' + encodeURIComponent(params.folder));
        }
        if (params.q) {
            q.push('q=' + encodeURIComponent(params.q));
        }
        if (params.filter && params.filter !== 'all') {
            q.push('filter=' + encodeURIComponent(params.filter));
        }
        if (params.page && params.page > 1) {
            q.push('page=' + encodeURIComponent(params.page));
        }
        return path + (q.length ? '?' + q.join('&') : '');
    }

    function ajaxErrorMessage(xhr, status, fallback) {
        if (xhr.responseJSON && xhr.responseJSON.message) {
            return xhr.responseJSON.message;
        }
        if (status === 'timeout') {
            return 'Sync timed out. Please try again.';
        }
        if (xhr.status === 404) {
            return 'Sync endpoint not found. Contact administrator.';
        }
        if (xhr.status === 401) {
            return 'Session expired. Please log in again.';
        }
        var txt = (xhr.responseText || '').trim();
        if (txt.indexOf('{') === 0) {
            try {
                var parsed = JSON.parse(txt);
                if (parsed.message) {
                    return parsed.message;
                }
            } catch (ignore) {}
        }
        if (txt.length > 0 && txt.length < 400 && txt.indexOf('<') === -1) {
            return txt;
        }
        if (xhr.status) {
            return fallback + ' (HTTP ' + xhr.status + ').';
        }
        return fallback;
    }

    var $composeModal = $('#mailComposeModal');
    var $composeForm = $('#mailComposeForm');
    var $composeDialog = $composeModal.find('.mail-compose-dialog');
    var composeEditorReady = false;

    function initComposeEditor() {
        if (composeEditorReady || !$('#composeBody').length) {
            return;
        }
        if ($.fn.summernote) {
            $('#composeBody').summernote({
                height: 200,
                placeholder: 'Write your message',
                toolbar: [
                    ['style', ['bold', 'italic', 'underline']],
                    ['para', ['ul', 'ol']],
                    ['insert', ['link']],
                    ['view', ['codeview']]
                ]
            });
        }
        composeEditorReady = true;
    }

    function resetComposeForm() {
        if (composeEditorReady && $.fn.summernote) {
            $('#composeBody').summernote('reset');
        } else {
            $('#composeBody').val('');
        }
        $('#composeCc, #composeBcc').val('');
        $('#composeAttachment').val('');
        $('#composeAttachLabel').text('No attachment selected');
        $composeForm.find('.is-invalid').removeClass('is-invalid');
    }

    function openComposeModal() {
        initComposeEditor();
        $composeModal.modal('show');
    }

    $('#mailComposeOpen').on('click', function () {
        openComposeModal();
    });

    $composeModal.on('shown.bs.modal', function () {
        initComposeEditor();
        var $to = $('#composeTo');
        if ($to.val() === '') {
            $to.trigger('focus');
        } else {
            $('#composeSubject').trigger('focus');
        }
    });

    $composeModal.on('hidden.bs.modal', function () {
        $composeDialog.removeClass('mail-compose-expanded');
        $('#mailComposeExpand i').removeClass('fa-compress').addClass('fa-expand');
    });

    $('#mailComposeExpand').on('click', function () {
        $composeDialog.toggleClass('mail-compose-expanded');
        $(this).find('i').toggleClass('fa-expand fa-compress');
    });

    $('#composeAttachBtn').on('click', function () {
        $('#composeAttachment').trigger('click');
    });

    $('#composeAttachment').on('change', function () {
        var file = this.files && this.files[0];
        $('#composeAttachLabel').text(file ? file.name : 'No attachment selected');
    });

    $composeForm.on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#composeSendBtn');
        if ($('#composeSenderId').length && !$('#composeSenderId').val()) {
            alert('Please select a sender email from Email Master.');
            return;
        }
        $btn.prop('disabled', true);

        var formData = new FormData(this);
        if (composeEditorReady && $.fn.summernote) {
            formData.set('body', $('#composeBody').summernote('code'));
        }

        $.ajax({
            url: mailAjaxUrl('send_message.php'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function (res) {
            if (res && res.success) {
                $composeModal.modal('hide');
                resetComposeForm();
                alert('Email sent successfully.');
            } else {
                alert((res && res.message) || 'Failed to send email.');
            }
        }).fail(function (xhr, status) {
            alert(ajaxErrorMessage(xhr, status, 'Request failed'));
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    if (window.mailOpenComposeOnLoad) {
        $(function () {
            openComposeModal();
        });
    }

    var mailSyncRequest = null;

    function syncMailInbox() {
        if (mailSyncRequest) {
            return;
        }

        var folder = window.mailCurrentFolder || 'INBOX';
        var filter = window.mailCurrentFilter || 'all';
        var $btns = $('.mail-sync-btn');
        var $status = $('#mailSyncStatus');

        $btns.prop('disabled', true);
        $btns.find('i').addClass('fa-spin');
        $status.text('Syncing in background — you can leave this page.');

        mailSyncRequest = $.ajax({
            url: mailAjaxUrl('sync_folder.php'),
            type: 'POST',
            data: { sync_all: 1, folder: folder, limit: 30 },
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            timeout: 300000
        }).done(function (res) {
            if (!document.body || !document.getElementById('mailList')) {
                return;
            }
            if (res && res.success) {
                $status.text((res.message || 'Sync complete.') + ' Refreshing...');
                window.setTimeout(function () {
                    if (document.getElementById('mailList')) {
                        window.location.href = mailInboxUrl({ folder: folder, filter: filter });
                    }
                }, 400);
                return;
            }
            alert((res && res.message) || 'Sync failed.');
            $status.text('');
        }).fail(function (xhr, status) {
            if (status === 'abort') {
                return;
            }
            if (xhr.status === 401) {
                window.location.href = '../index.php';
                return;
            }
            if (document.getElementById('mailList')) {
                alert(ajaxErrorMessage(xhr, status, 'Sync request failed'));
            }
            $status.text('');
        }).always(function () {
            mailSyncRequest = null;
            $btns.prop('disabled', false);
            $btns.find('i').removeClass('fa-spin');
        });
    }

    $(document).on('click', '.mail-sync-btn', function (e) {
        e.preventDefault();
        syncMailInbox();
    });

    $('#mailReadFilter').on('change', function () {
        var folder = window.mailCurrentFolder || 'INBOX';
        window.location.href = mailInboxUrl({
            folder: folder,
            filter: $(this).val() || 'all'
        });
    });

    $('#mailSelectAll').on('change', function () {
        $('.mail-item-check').prop('checked', $(this).is(':checked'));
    });

    $('#mailDeleteBtn').on('click', function () {
        var ids = [];
        $('.mail-item-check:checked').each(function () {
            ids.push($(this).val());
        });
        if (!ids.length) {
            alert('Select at least one message.');
            return;
        }
        if (!confirm('Delete selected messages from CRM cache?')) {
            return;
        }
        $.post(mailAjaxUrl('delete_messages.php'), { ids: ids }, function (res) {
            if (res && res.success) {
                location.reload();
            } else {
                alert((res && res.message) || 'Delete failed.');
            }
        }, 'json').fail(function () {
            alert('Request failed.');
        });
    });

    $('.mail-star').on('click', function (e) {
        e.stopPropagation();
        var $el = $(this);
        var id = $el.data('id');
        $.post(mailAjaxUrl('toggle_star.php'), { id: id }, function (res) {
            if (res && res.success) {
                $el.toggleClass('starred', !!res.starred);
            }
        }, 'json');
    });

    $('.mail-row').on('click', function (e) {
        if ($(e.target).is('input, .mail-star, .mail-star *')) {
            return;
        }
        var id = $(this).data('id');
        $('#mailViewSubject').text('Loading...');
        $('#mailViewFrom').html('');
        $('#mailViewBody').html('<p class="text-muted mb-0"><i class="fas fa-spinner fa-spin"></i> Loading message...</p>');
        $('#mailViewModal').modal('show');

        $.ajax({
            url: mailAjaxUrl('get_message.php'),
            data: { id: id },
            dataType: 'json',
            timeout: 120000
        }).done(function (res) {
            if (!res || !res.success) {
                alert((res && res.message) || 'Could not load message.');
                return;
            }
            $('#mailViewSubject').text(res.message.subject || '(No subject)');
            var from = res.message.from_name || res.message.from_email || '';
            if (res.message.from_name && res.message.from_email) {
                from = res.message.from_name + ' <' + res.message.from_email + '>';
            }
            $('#mailViewFrom').html('<strong>From:</strong> ' + $('<div>').text(from).html());
            var body = res.message.body_html || res.message.body_text || '';
            if (res.message.body_html) {
                $('#mailViewBody').html(body);
            } else {
                $('#mailViewBody').text(body);
            }
        }).fail(function (xhr, status) {
            $('#mailViewModal').modal('hide');
            alert(ajaxErrorMessage(xhr, status, 'Could not load message'));
        });
    });
})();
