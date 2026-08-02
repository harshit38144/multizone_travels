/* Itinerary image search (Pexels / Wikimedia) */
(function ($) {
    'use strict';

    var qiiTargetCard = null;
    var qiiSearchTimer = null;

    function qiiAbsUrl(u) {
        if (typeof window.qQuotationAbsUrl === 'function') {
            return window.qQuotationAbsUrl(u);
        }
        if (!u) return '';
        if (/^(https?:)?\/\//i.test(u) || /^data:/i.test(u)) return u;
        return u;
    }

    function qiiEsc(text) {
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    function qiiUpdatePreview($card, url) {
        if (typeof window.qUpdateDayImagePreview === 'function') {
            window.qUpdateDayImagePreview($card, url);
            return;
        }
        var $wrap = $card.find('.q-img-preview-wrap');
        var $img = $card.find('.q-img-preview');
        var $empty = $card.find('.q-img-preview-empty');
        if (url) {
            $img.attr('src', qiiAbsUrl(url)).show();
            $empty.hide();
            $wrap.addClass('has-image');
        } else {
            $img.attr('src', '').hide();
            $empty.show();
            $wrap.removeClass('has-image');
        }
    }

    function qiiDefaultQuery($card) {
        var parts = [];
        var dest = ($('[name=destination]').val() || '').trim();
        var title = ($card.find('.q-day-title').val() || '').trim();
        var head = ($card.find('.q-day-head').text() || '').trim();
        if (dest) parts.push(dest.split(',')[0]);
        if (title) parts.push(title);
        if (!parts.length && head) {
            parts.push(head.replace(/Day \d+/i, '').replace(/[\d\-\/]/g, ' ').trim());
        }
        return parts.join(' ').replace(/\s+/g, ' ').trim();
    }

    function qiiRenderResults(images, meta) {
        var $grid = $('#qiiResultsGrid');
        var $note = $('#qiiSourceNote');
        $grid.empty();

        if (meta && meta.source) {
            var srcLabel = meta.source === 'pexels' ? 'Pexels' : 'Wikimedia Commons';
            $note.text('Showing results from ' + srcLabel + (meta.query ? ' for "' + meta.query + '"' : '')).show();
        } else {
            $note.hide();
        }

        if (!images || !images.length) {
            $grid.html('<div class="qii-empty"><i class="fas fa-image"></i>No images found. Try another search term.</div>');
            return;
        }

        images.forEach(function (img, idx) {
            var $btn = $('<button type="button" class="qii-img-item"></button>').attr('data-index', idx);
            $('<img class="qii-img-thumb" alt="">').attr('src', img.thumb || img.url).appendTo($btn);
            $('<span class="qii-img-caption"></span>').text(img.title || 'Image').appendTo($btn);
            $grid.append($btn);
        });
        $grid.data('images', images);
    }

    function qiiRunSearch(query) {
        query = (query || '').trim();
        if (query.length < 2) {
            $('#qiiResultsGrid').html('<div class="qii-empty text-muted">Type at least 2 characters to search.</div>');
            return;
        }

        $('#qiiResultsGrid').html('<div class="qii-loading"><i class="fas fa-circle-notch fa-spin mr-1"></i> Searching...</div>');
        $('#qiiSourceNote').hide();

        $.getJSON('crm/ajax/search_itinerary_images.php', { q: query, limit: 12 })
            .done(function (res) {
                if (!res || !res.success) {
                    qiiRenderResults([], {});
                    return;
                }
                qiiRenderResults(res.images || [], { source: res.source, query: query });
            })
            .fail(function () {
                $('#qiiResultsGrid').html('<div class="qii-empty text-danger">Search failed. Please try again.</div>');
            });
    }

    function qiiOpenModal($card) {
        qiiTargetCard = $card;
        var query = qiiDefaultQuery($card);
        var dayHead = ($card.find('.q-day-head').text() || '').trim();
        $('#qiiImageModalSub').text(dayHead ? ('Choose a photo for: ' + dayHead) : 'Choose a photo for this day');
        $('#qiiSearchInput').val(query);
        $('#qiiResultsGrid').empty();
        $('#qiiSourceNote').hide();
        $('#qiiImageModal').modal('show');
        if (query.length >= 2) {
            qiiRunSearch(query);
        }
    }

    function qiiSelectImage(img) {
        if (!qiiTargetCard || !img || !img.url) {
            return;
        }

        var $card = qiiTargetCard;
        var $grid = $('#qiiResultsGrid');
        $grid.html('<div class="qii-loading"><i class="fas fa-circle-notch fa-spin mr-1"></i> Saving image...</div>');

        $.ajax({
            url: 'crm/ajax/import_itinerary_image.php',
            type: 'POST',
            dataType: 'json',
            data: { url: img.url }
        }).done(function (res) {
            if (res && res.success && res.url) {
                $card.find('.q-day-image').val(res.url);
                qiiUpdatePreview($card, res.url);
                $('#qiiImageModal').modal('hide');
            } else {
                alert((res && res.message) ? res.message : 'Could not save image.');
                qiiRunSearch($('#qiiSearchInput').val());
            }
        }).fail(function () {
            alert('Could not save image.');
            qiiRunSearch($('#qiiSearchInput').val());
        });
    }

    $(function () {
        $(document).on('click', '.q-search-day-image', function (e) {
            e.preventDefault();
            qiiOpenModal($(this).closest('.q-day-card'));
        });

        $(document).on('click', '.q-clear-day-image', function (e) {
            e.preventDefault();
            var $card = $(this).closest('.q-day-card');
            $card.find('.q-day-image').val('');
            qiiUpdatePreview($card, '');
        });

        $('#qiiSearchBtn').on('click', function () {
            qiiRunSearch($('#qiiSearchInput').val());
        });

        $('#qiiSearchInput').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                qiiRunSearch($(this).val());
            }
        });

        $('#qiiSearchInput').on('input', function () {
            clearTimeout(qiiSearchTimer);
            var q = $(this).val();
            qiiSearchTimer = setTimeout(function () {
                if ((q || '').trim().length >= 2) {
                    qiiRunSearch(q);
                }
            }, 450);
        });

        $(document).on('click', '.qii-img-item', function () {
            var idx = parseInt($(this).data('index'), 10);
            var images = $('#qiiResultsGrid').data('images') || [];
            qiiSelectImage(images[idx]);
        });
    });
})(jQuery);
