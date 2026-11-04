/* Itinerary image search (Pexels / Wikimedia) */
(function ($) {
    'use strict';

    var qiiTargetCard = null;
    var qiiSearchTimer = null;
    var qiiPage = 1;
    var qiiPerPage = 18;
    var qiiQuery = '';
    var qiiHasMore = false;
    var qiiLoading = false;
    var qiiImages = [];
    var qiiSeenKeys = {};

    function qiiAbsUrl(u) {
        if (typeof window.qQuotationAbsUrl === 'function') {
            return window.qQuotationAbsUrl(u);
        }
        if (!u) return '';
        if (/^(https?:)?\/\//i.test(u) || /^data:/i.test(u)) return u;
        return u;
    }

    function qiiImageKey(img) {
        if (!img) {
            return '';
        }
        if (img.id) {
            return 'id:' + String(img.id);
        }
        return 'url:' + String(img.url || img.thumb || '');
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

    function qiiSetLoadMoreVisible(show) {
        var $wrap = $('#qiiLoadMoreWrap');
        if (!$wrap.length) {
            return;
        }
        if (show) {
            $wrap.prop('hidden', false).show();
        } else {
            $wrap.prop('hidden', true).hide();
        }
    }

    function qiiSetLoadMoreLoading(isLoading) {
        var $btn = $('#qiiLoadMoreBtn');
        if (!$btn.length) {
            return;
        }
        qiiLoading = !!isLoading;
        $btn.prop('disabled', qiiLoading);
        if (qiiLoading) {
            $btn.html('<i class="fas fa-circle-notch fa-spin mr-1" aria-hidden="true"></i><span class="qii-load-more-label">Loading…</span>');
        } else {
            $btn.html('<span class="qii-load-more-label">Load More</span>');
        }
    }

    function qiiAppendImages(images) {
        var $grid = $('#qiiResultsGrid');
        var added = [];
        (images || []).forEach(function (img) {
            var key = qiiImageKey(img);
            if (!key || qiiSeenKeys[key]) {
                return;
            }
            qiiSeenKeys[key] = true;
            var idx = qiiImages.length;
            qiiImages.push(img);
            added.push(img);
            var $btn = $('<button type="button" class="qii-img-item"></button>').attr('data-index', idx);
            $('<img class="qii-img-thumb" alt="">').attr('src', img.thumb || img.url).appendTo($btn);
            $('<span class="qii-img-caption"></span>').text(img.title || 'Image').appendTo($btn);
            $grid.append($btn);
        });
        $grid.data('images', qiiImages);
        return added;
    }

    function qiiRenderResults(images, meta, append) {
        var $grid = $('#qiiResultsGrid');
        var $note = $('#qiiSourceNote');
        meta = meta || {};

        if (!append) {
            $grid.empty();
            qiiImages = [];
            qiiSeenKeys = {};
        } else {
            $grid.find('.qii-empty, .qii-loading').remove();
        }

        if (meta.source) {
            var srcLabel = meta.source === 'pexels' ? 'Pexels' : 'Wikimedia Commons';
            $note.text('Showing results from ' + srcLabel + (meta.query ? ' for "' + meta.query + '"' : '')).show();
        } else if (!append) {
            $note.hide();
        }

        var added = qiiAppendImages(images);
        qiiHasMore = !!meta.has_more;

        if (!qiiImages.length) {
            $grid.html('<div class="qii-empty"><i class="fas fa-image"></i>No images found. Try another search term.</div>');
            qiiSetLoadMoreVisible(false);
            return;
        }

        if (append && !added.length && !qiiHasMore) {
            qiiSetLoadMoreVisible(false);
            return;
        }

        qiiSetLoadMoreVisible(qiiHasMore);
    }

    function qiiFetchPage(query, page, append) {
        query = (query || '').trim();
        page = Math.max(1, parseInt(page, 10) || 1);

        if (query.length < 2) {
            if (!append) {
                $('#qiiResultsGrid').html('<div class="qii-empty text-muted">Type at least 2 characters to search.</div>');
                qiiSetLoadMoreVisible(false);
            }
            return;
        }

        if (qiiLoading) {
            return;
        }

        qiiLoading = true;
        if (append) {
            qiiSetLoadMoreLoading(true);
        } else {
            qiiSetLoadMoreVisible(false);
            $('#qiiResultsGrid').html('<div class="qii-loading"><i class="fas fa-circle-notch fa-spin mr-1"></i> Searching...</div>');
            $('#qiiSourceNote').hide();
        }

        $.getJSON(qiiAbsUrl('crm/ajax/search_itinerary_images.php'), {
            q: query,
            limit: qiiPerPage,
            page: page
        })
            .done(function (res) {
                if (!res || !res.success) {
                    if (!append) {
                        qiiRenderResults([], {});
                    } else {
                        qiiHasMore = false;
                        qiiSetLoadMoreVisible(false);
                    }
                    return;
                }
                qiiPage = parseInt(res.page, 10) || page;
                qiiQuery = query;
                qiiRenderResults(res.images || [], {
                    source: res.source,
                    query: query,
                    has_more: !!res.has_more
                }, !!append);
            })
            .fail(function () {
                if (!append) {
                    $('#qiiResultsGrid').html('<div class="qii-empty text-danger">Search failed. Please try again.</div>');
                    qiiSetLoadMoreVisible(false);
                } else {
                    alert('Could not load more images. Please try again.');
                }
            })
            .always(function () {
                qiiLoading = false;
                qiiSetLoadMoreLoading(false);
                if (!qiiHasMore) {
                    qiiSetLoadMoreVisible(false);
                }
            });
    }

    function qiiRunSearch(query) {
        qiiPage = 1;
        qiiHasMore = false;
        qiiImages = [];
        qiiSeenKeys = {};
        qiiFetchPage(query, 1, false);
    }

    function qiiLoadMore() {
        if (qiiLoading || !qiiHasMore) {
            return;
        }
        var nextPage = (parseInt(qiiPage, 10) || 1) + 1;
        qiiFetchPage(qiiQuery || $('#qiiSearchInput').val(), nextPage, true);
    }

    function qiiOpenModal($card) {
        qiiTargetCard = $card;
        var query = qiiDefaultQuery($card);
        var dayHead = ($card.find('.q-day-head').text() || '').trim();
        $('#qiiImageModalSub').text(dayHead ? ('Choose a photo for: ' + dayHead) : 'Choose a photo for this day');
        $('#qiiSearchInput').val(query);
        $('#qiiResultsGrid').empty();
        $('#qiiSourceNote').hide();
        qiiSetLoadMoreVisible(false);
        qiiPage = 1;
        qiiHasMore = false;
        qiiImages = [];
        qiiSeenKeys = {};
        $('#qiiImageModal').modal('show');
        if (query.length >= 2) {
            qiiRunSearch(query);
        }
    }

    function qiiAjaxErrorMessage(xhr, fallback) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
            return xhr.responseJSON.message;
        }
        if (xhr && typeof xhr.responseText === 'string' && xhr.responseText) {
            try {
                var parsed = JSON.parse(xhr.responseText);
                if (parsed && parsed.message) {
                    return parsed.message;
                }
            } catch (e) { /* ignore */ }
        }
        return fallback || 'Could not save image.';
    }

    function qiiSelectImage(img) {
        if (!qiiTargetCard || !img || !img.url) {
            return;
        }

        var $card = qiiTargetCard;
        var $grid = $('#qiiResultsGrid');
        qiiSetLoadMoreVisible(false);
        $grid.html('<div class="qii-loading"><i class="fas fa-circle-notch fa-spin mr-1"></i> Saving image...</div>');

        $.ajax({
            url: qiiAbsUrl('crm/ajax/import_itinerary_image.php'),
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
        }).fail(function (xhr) {
            alert(qiiAjaxErrorMessage(xhr, 'Could not save image.'));
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

        $('#qiiLoadMoreBtn').on('click', function (e) {
            e.preventDefault();
            qiiLoadMore();
        });

        $(document).on('click', '.qii-img-item', function () {
            var idx = parseInt($(this).data('index'), 10);
            var images = $('#qiiResultsGrid').data('images') || qiiImages || [];
            qiiSelectImage(images[idx]);
        });
    });
})(jQuery);
