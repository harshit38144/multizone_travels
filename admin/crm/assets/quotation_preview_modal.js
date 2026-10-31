/* Shared Quotation Preview modal — same behavior as leads.php */
(function ($) {
    'use strict';

    var leadQPreviewEditHref = '';
    var leadQPreviewDirty = false;
    var leadQPreviewSaveBtnHtml = '<i class="fas fa-save mr-1"></i>Save Changes';

    function setLeadQPreviewDirty(isDirty) {
        leadQPreviewDirty = !!isDirty;
        var $btn = $('#leadQPreviewSaveBtn');
        var $hint = $('#leadQPreviewUnsavedHint');
        if (leadQPreviewDirty) {
            $btn.removeClass('d-none').prop('disabled', false).html(leadQPreviewSaveBtnHtml);
            $hint.removeClass('d-none');
        } else {
            $btn.addClass('d-none').prop('disabled', true).html(leadQPreviewSaveBtnHtml);
            $hint.addClass('d-none');
        }
    }

    function openLeadQuotationPreview(quotationId, editHref) {
        quotationId = Number(quotationId || 0);
        editHref = String(editHref || '').trim();
        if (quotationId <= 0) {
            window.alert('Quotation not found.');
            return;
        }
        if (!editHref) {
            editHref = 'crm/quotation_generator.php?id=' + quotationId;
        }
        leadQPreviewEditHref = editHref;
        setLeadQPreviewDirty(false);
        $('#leadQPreviewEditBtn').attr('href', editHref);
        $('#leadQPreviewLoading').removeClass('d-none');
        $('#leadQPreviewFrame').addClass('d-none').attr('src', 'about:blank');
        if (!$('#leadQuotationPreviewModal').parent().is('body')) {
            $('#leadQuotationPreviewModal').appendTo('body');
        }
        $('#leadQuotationPreviewModal').modal('show');

        var frameSrc = 'crm/quotation_generator.php?id=' + encodeURIComponent(String(quotationId))
            + '&preview=1&preview_only=1&mz_embed=1';
        window.setTimeout(function () {
            $('#leadQPreviewFrame').attr('src', frameSrc);
        }, 40);
    }

    window.CrmQuotationPreview = {
        open: openLeadQuotationPreview
    };

    $(function () {
        if ($('#leadQuotationPreviewModal').length && !$('#leadQuotationPreviewModal').parent().is('body')) {
            $('#leadQuotationPreviewModal').appendTo('body');
        }

        $(document).on('click', '.js-lead-q-preview', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openLeadQuotationPreview($(this).attr('data-quotation-id'), $(this).attr('data-edit-href'));
        });

        $('#leadQPreviewFrame').on('load', function () {
            var src = String($(this).attr('src') || '');
            if (!src || src === 'about:blank') {
                return;
            }
            $('#leadQPreviewLoading').addClass('d-none');
            $(this).removeClass('d-none');
        });

        window.addEventListener('message', function (ev) {
            var data = ev && ev.data;
            if (!data || typeof data !== 'object') {
                return;
            }
            if (data.type === 'mz-quotation-preview-ready') {
                $('#leadQPreviewLoading').addClass('d-none');
                $('#leadQPreviewFrame').removeClass('d-none');
                return;
            }
            if (data.type !== 'mz-quotation-preview-state') {
                return;
            }
            if (data.saving) {
                $('#leadQPreviewSaveBtn')
                    .removeClass('d-none')
                    .prop('disabled', true)
                    .html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');
                return;
            }
            if (data.saveError) {
                setLeadQPreviewDirty(true);
                return;
            }
            if (data.saved) {
                setLeadQPreviewDirty(false);
                var okMsg = data.message || 'Quotation saved.';
                if (data.version) {
                    okMsg += ' (v' + data.version + ')';
                }
                if (window.toastr && typeof window.toastr.success === 'function') {
                    window.toastr.success(okMsg);
                } else {
                    window.alert(okMsg);
                }
                return;
            }
            if (typeof data.dirty === 'boolean') {
                setLeadQPreviewDirty(data.dirty);
            }
        });

        $('#leadQPreviewSaveBtn').on('click', function (e) {
            e.preventDefault();
            var frame = document.getElementById('leadQPreviewFrame');
            try {
                if (frame && frame.contentWindow && typeof frame.contentWindow.qSavePreviewOnly === 'function') {
                    frame.contentWindow.qSavePreviewOnly();
                    return;
                }
                if (frame && frame.contentWindow) {
                    frame.contentWindow.postMessage({ type: 'mz-quotation-preview-save' }, '*');
                    return;
                }
            } catch (err) { /* ignore */ }
            window.alert('Could not save preview changes. Please try again.');
        });

        $('#leadQPreviewPrintBtn').on('click', function (e) {
            e.preventDefault();
            var frame = document.getElementById('leadQPreviewFrame');
            try {
                if (frame && frame.contentWindow && typeof frame.contentWindow.qPrintPreviewOnly === 'function') {
                    frame.contentWindow.qPrintPreviewOnly();
                    return;
                }
                if (frame && frame.contentWindow) {
                    frame.contentWindow.postMessage({ type: 'mz-quotation-preview-print' }, '*');
                    return;
                }
            } catch (err) { /* ignore */ }
            window.alert('Could not print preview. Please try again.');
        });

        $('#leadQuotationPreviewModal').on('hide.bs.modal', function (e) {
            if (!leadQPreviewDirty) {
                return;
            }
            if (!window.confirm('You have unsaved preview edits. Close without saving?')) {
                e.preventDefault();
            }
        });

        $('#leadQuotationPreviewModal').on('hidden.bs.modal', function () {
            $('#leadQPreviewFrame').addClass('d-none').attr('src', 'about:blank');
            $('#leadQPreviewLoading').removeClass('d-none');
            leadQPreviewEditHref = '';
            setLeadQPreviewDirty(false);
        });

        $(document).on('click', '#leadQPreviewEditBtn', function (e) {
            var href = String($(this).attr('href') || leadQPreviewEditHref || '').trim();
            if (!href || href === '#') {
                return;
            }
            e.preventDefault();
            try {
                if (window.parent && window.parent !== window && window.parent.MZTabWorkspace
                    && typeof window.parent.MZTabWorkspace.open === 'function') {
                    window.parent.MZTabWorkspace.open(href, 'Edit Quotation', { pushHistory: true });
                    $('#leadQuotationPreviewModal').modal('hide');
                    return;
                }
            } catch (err) { /* ignore */ }
            window.location.href = href;
        });
    });
})(jQuery);
