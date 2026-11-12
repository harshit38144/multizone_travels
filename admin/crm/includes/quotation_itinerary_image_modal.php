<!-- Itinerary image search modal -->
<div class="modal fade qii-image-search" id="qiiImageModal" tabindex="-1" role="dialog" aria-labelledby="qiiImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered qii-modal-dialog" role="document">
        <div class="modal-content qii-modal-content">
            <div class="modal-header qii-modal-hd">
                <div class="qii-hd-main">
                    <div class="qii-hd-icon" aria-hidden="true"><i class="fas fa-images"></i></div>
                    <div class="qii-hd-copy">
                        <h5 class="modal-title qii-hd-title mb-0" id="qiiImageModalLabel">Search Itinerary Image</h5>
                        <p class="qii-hd-sub mb-0" id="qiiImageModalSub">Find a photo for this day</p>
                    </div>
                </div>
                <div class="qii-hd-actions">
                    <div class="qii-provider-switch" role="tablist" aria-label="Image search provider">
                        <span class="qii-provider-indicator" aria-hidden="true"></span>
                        <button type="button" class="qii-provider-btn is-active" role="tab" aria-selected="true" data-provider="pexels" id="qiiProviderPexels">
                            Pexels
                        </button>
                        <button type="button" class="qii-provider-btn" role="tab" aria-selected="false" data-provider="unsplash" id="qiiProviderUnsplash">
                            Unsplash
                        </button>
                    </div>
                    <button type="button" class="close qii-close-btn" data-dismiss="modal" aria-label="Close">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="modal-body qii-search-body">
                <div class="qii-search-toolbar">
                    <div class="qii-search-bar">
                        <input type="text" class="form-control qii-search-input" id="qiiSearchInput" placeholder="Search destination, landmark, activity..." autocomplete="off">
                        <button type="button" class="btn qii-search-btn" id="qiiSearchBtn"><i class="fas fa-search"></i></button>
                    </div>
                    <div class="qii-source-note" id="qiiSourceNote" aria-live="polite"></div>
                </div>
                <div class="qii-results-panel" id="qiiResultsPanel">
                    <div class="qii-results-grid" id="qiiResultsGrid"></div>
                    <div class="qii-load-more-wrap" id="qiiLoadMoreWrap" hidden>
                        <button type="button" class="btn qii-load-more-btn" id="qiiLoadMoreBtn">
                            <span class="qii-load-more-label">Load More</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer qii-modal-ft">
                <button type="button" class="btn qii-btn-ghost" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
