/* Itinerary image search — combined Pexels + Unsplash (alternating batches of 6) */
(function ($) {
    'use strict';

    var qiiTargetCard = null;
    var qiiSearchTimer = null;
    var qiiQuery = '';
    var qiiLoading = false;
    var qiiImages = [];
    var qiiSeenKeys = {};
    var qiiRequestSeq = 0;
    var qiiBatchSize = 6;
    var qiiProviders = ['pexels', 'unsplash'];
    var qiiNextProviderIndex = 0;
    var qiiProviderState = {
        pexels: { page: 0, hasMore: true },
        unsplash: { page: 0, hasMore: true }
    };

    function qiiAbsUrl(u) {
        if (typeof window.qQuotationAbsUrl === 'function') {
            return window.qQuotationAbsUrl(u);
        }
        if (!u) return '';
        if (/^(https?:)?\/\//i.test(u) || /^data:/i.test(u)) return u;
        return u;
    }

    function qiiProviderLabel(source) {
        source = String(source || '').toLowerCase();
        if (source === 'unsplash') return 'Unsplash';
        if (source === 'pexels') return 'Pexels';
        if (source === 'wikimedia') return 'Wikimedia Commons';
        return 'Image library';
    }

    function qiiResetProviderState() {
        qiiNextProviderIndex = 0;
        qiiProviderState = {
            pexels: { page: 0, hasMore: true },
            unsplash: { page: 0, hasMore: true }
        };
    }

    function qiiHasMoreAny() {
        return !!(qiiProviderState.pexels.hasMore || qiiProviderState.unsplash.hasMore);
    }

    function qiiSetSourceNote(text) {
        var $note = $('#qiiSourceNote');
        if (!$note.length) {
            return;
        }
        var value = String(text || '').trim();
        if (value) {
            $note.text(value).removeClass('is-empty').show();
        } else {
            $note.html('&nbsp;').addClass('is-empty').show();
        }
    }

    function qiiSkeletonHtml(count) {
        count = Math.max(1, parseInt(count, 10) || qiiBatchSize);
        var html = '';
        var i;
        for (i = 0; i < count; i++) {
            html +=
                '<div class="qii-skel-card" aria-hidden="true">' +
                    '<div class="qii-skel-thumb"></div>' +
                '</div>';
        }
        return html;
    }

    function qiiShowSkeleton(count) {
        $('#qiiResultsGrid').html(qiiSkeletonHtml(count));
    }

    function qiiAppendSkeleton(count) {
        $('#qiiResultsGrid').append(qiiSkeletonHtml(count));
    }

    function qiiClearSkeleton() {
        $('#qiiResultsGrid').find('.qii-skel-card').remove();
    }

    function qiiSetInfiniteLoading(show) {
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

    function qiiImageKey(img) {
        if (!img) {
            return '';
        }
        if (img.id) {
            return String(img.source || '') + ':id:' + String(img.id);
        }
        return String(img.source || '') + ':url:' + String(img.url || img.thumb || '');
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

            var altText = String(img.title || 'Image').trim() || 'Image';
            var $btn = $('<button type="button" class="qii-img-item"></button>')
                .attr('data-index', idx)
                .attr('title', altText)
                .attr('aria-label', altText)
                .attr('data-source', img.source || '');
            $('<img class="qii-img-thumb" alt="">')
                .attr('src', img.thumb || img.url)
                .attr('alt', altText)
                .appendTo($btn);
            $grid.append($btn);
        });
        $grid.data('images', qiiImages);
        return added;
    }

    function qiiFetchProvider(query, provider, page) {
        return $.getJSON(qiiAbsUrl('crm/ajax/search_itinerary_images.php'), {
            q: query,
            limit: qiiBatchSize,
            page: page,
            provider: provider
        }).then(
            function (res) {
                return {
                    provider: provider,
                    ok: !!(res && (res.success || (res.images && res.images.length))),
                    images: (res && res.images) ? res.images : [],
                    page: (res && parseInt(res.page, 10)) || page,
                    has_more: !!(res && res.has_more),
                    source: (res && res.source) || provider,
                    message: (res && res.message) || ''
                };
            },
            function () {
                return {
                    provider: provider,
                    ok: false,
                    images: [],
                    page: page,
                    has_more: false,
                    source: provider,
                    message: 'Search failed on ' + qiiProviderLabel(provider) + '.'
                };
            }
        );
    }

    function qiiApplyProviderResult(result) {
        var provider = result.provider;
        var state = qiiProviderState[provider];
        if (!state) {
            return [];
        }
        state.page = result.page || state.page;
        // No more if failed, empty, or API says no more.
        state.hasMore = !!(result.ok && result.images && result.images.length && result.has_more);
        if (!result.ok || !(result.images && result.images.length)) {
            state.hasMore = false;
        }
        return qiiAppendImages(result.images || []);
    }

    function qiiUpdateCombinedNote(query, errors) {
        var sources = [];
        if (qiiImages.some(function (img) { return String(img.source || '') === 'pexels' || String(img.source || '') === 'wikimedia'; })) {
            sources.push(qiiImages.some(function (img) { return String(img.source || '') === 'wikimedia'; }) &&
                !qiiImages.some(function (img) { return String(img.source || '') === 'pexels'; })
                ? 'Wikimedia'
                : 'Pexels');
        }
        if (qiiImages.some(function (img) { return String(img.source || '') === 'unsplash'; })) {
            sources.push('Unsplash');
        }
        if (!sources.length) {
            if (errors && errors.length) {
                qiiSetSourceNote(errors.join(' '));
            } else {
                qiiSetSourceNote('');
            }
            return;
        }
        var note = 'Showing results from ' + sources.join(' & ') + (query ? ' for "' + query + '"' : '');
        if (errors && errors.length === 1) {
            note += ' · ' + errors[0];
        }
        qiiSetSourceNote(note);
    }

    function qiiRunSearch(query) {
        query = (query || '').trim();
        if (query.length < 2) {
            $('#qiiResultsGrid').html('<div class="qii-empty text-muted">Type at least 2 characters to search.</div>');
            qiiSetSourceNote('');
            qiiSetInfiniteLoading(false);
            return;
        }

        var reqId = ++qiiRequestSeq;
        qiiQuery = query;
        qiiLoading = true;
        qiiImages = [];
        qiiSeenKeys = {};
        qiiResetProviderState();
        qiiSetInfiniteLoading(false);
        qiiShowSkeleton(window.matchMedia && window.matchMedia('(max-width: 575px)').matches ? 4 : 12);
        qiiSetSourceNote('Searching Pexels & Unsplash…');
        var $panel = $('#qiiResultsPanel');
        if ($panel.length) {
            $panel.scrollTop(0);
        }

        // Initial load: fetch first batch from both providers in parallel, render Pexels then Unsplash.
        $.when(
            qiiFetchProvider(query, 'pexels', 1),
            qiiFetchProvider(query, 'unsplash', 1)
        ).done(function (pexelsRes, unsplashRes) {
            if (reqId !== qiiRequestSeq) {
                return;
            }
            qiiClearSkeleton();
            $('#qiiResultsGrid').find('.qii-empty, .qii-loading').remove();

            var errors = [];
            if (!pexelsRes.ok && pexelsRes.message) {
                errors.push(pexelsRes.message);
            }
            if (!unsplashRes.ok && unsplashRes.message) {
                errors.push(unsplashRes.message);
            }

            qiiApplyProviderResult(pexelsRes);
            qiiApplyProviderResult(unsplashRes);
            // Next append starts with Pexels page 2 (after both first batches).
            qiiNextProviderIndex = 0;

            if (!qiiImages.length) {
                var errText = errors.length
                    ? errors.join(' ')
                    : 'No images found. Try another search term.';
                $('#qiiResultsGrid').html(
                    '<div class="qii-empty' + (errors.length ? ' text-danger' : '') + '">' +
                    '<i class="fas fa-' + (errors.length ? 'exclamation-circle' : 'image') + '"></i>' +
                    $('<div>').text(errText).html() +
                    '</div>'
                );
                qiiSetSourceNote('');
                qiiSetInfiniteLoading(false);
                return;
            }

            qiiUpdateCombinedNote(query, errors.filter(function (e, i, arr) {
                // Soft-warn only when one provider failed but the other returned images.
                return arr.length === 1;
            }));
        }).always(function () {
            if (reqId !== qiiRequestSeq) {
                return;
            }
            qiiLoading = false;
            qiiMaybeLoadMore();
        });
    }

    function qiiPickNextProvider() {
        var i;
        for (i = 0; i < qiiProviders.length; i++) {
            var idx = (qiiNextProviderIndex + i) % qiiProviders.length;
            var provider = qiiProviders[idx];
            if (qiiProviderState[provider] && qiiProviderState[provider].hasMore) {
                qiiNextProviderIndex = (idx + 1) % qiiProviders.length;
                return provider;
            }
        }
        return null;
    }

    function qiiLoadNextBatch() {
        if (qiiLoading || !qiiQuery) {
            return;
        }
        var provider = qiiPickNextProvider();
        if (!provider) {
            qiiSetInfiniteLoading(false);
            return;
        }

        var reqId = qiiRequestSeq;
        var nextPage = (parseInt(qiiProviderState[provider].page, 10) || 0) + 1;
        qiiLoading = true;
        qiiAppendSkeleton(qiiBatchSize);
        qiiSetInfiniteLoading(true);

        qiiFetchProvider(qiiQuery, provider, nextPage).done(function (result) {
            if (reqId !== qiiRequestSeq) {
                return;
            }
            qiiClearSkeleton();
            qiiApplyProviderResult(result);
            qiiUpdateCombinedNote(qiiQuery, []);
        }).always(function () {
            if (reqId !== qiiRequestSeq) {
                return;
            }
            qiiLoading = false;
            qiiSetInfiniteLoading(false);
            // Keep filling if still near bottom and more remains.
            window.setTimeout(function () {
                qiiMaybeLoadMore();
            }, 50);
        });
    }

    function qiiMaybeLoadMore() {
        if (qiiLoading || !qiiHasMoreAny() || !qiiImages.length) {
            return;
        }
        var panel = document.getElementById('qiiResultsPanel');
        if (!panel) {
            return;
        }
        var remaining = panel.scrollHeight - panel.scrollTop - panel.clientHeight;
        if (remaining < 220) {
            qiiLoadNextBatch();
        }
    }

    function qiiOpenModal($card) {
        qiiTargetCard = $card;
        var query = qiiDefaultQuery($card);
        var dayHead = ($card.find('.q-day-head').text() || '').trim();
        $('#qiiImageModalSub').text(dayHead ? ('Choose a photo for: ' + dayHead) : 'Choose a photo for this day');
        $('#qiiSearchInput').val(query);
        qiiSetInfiniteLoading(false);
        qiiImages = [];
        qiiSeenKeys = {};
        qiiResetProviderState();
        if (query.length >= 2) {
            qiiShowSkeleton(window.matchMedia && window.matchMedia('(max-width: 575px)').matches ? 4 : 12);
            qiiSetSourceNote('Searching Pexels & Unsplash…');
        } else {
            $('#qiiResultsGrid').html('<div class="qii-empty text-muted">Type at least 2 characters to search.</div>');
            qiiSetSourceNote('');
        }
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
        qiiSetInfiniteLoading(false);
        $grid.html('<div class="qii-loading"><i class="fas fa-circle-notch fa-spin mr-1"></i> Saving image...</div>');

        $.ajax({
            url: qiiAbsUrl('crm/ajax/import_itinerary_image.php'),
            type: 'POST',
            dataType: 'json',
            data: {
                url: img.url,
                source: img.source || '',
                download_location: img.download_location || ''
            }
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

        $('#qiiResultsPanel').on('scroll', function () {
            qiiMaybeLoadMore();
        });

        $(document).on('click', '.qii-img-item', function () {
            var idx = parseInt($(this).data('index'), 10);
            var images = $('#qiiResultsGrid').data('images') || qiiImages || [];
            qiiSelectImage(images[idx]);
        });
    });
})(jQuery);
