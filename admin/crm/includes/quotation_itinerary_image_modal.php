<!-- Itinerary image search modal -->
<div class="modal fade qii-image-search" id="qiiImageModal" tabindex="-1" role="dialog" aria-labelledby="qiiImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered qii-modal-dialog" role="document">
        <div class="modal-content qii-modal-content">
            <div class="modal-header qii-modal-hd">
                <div class="qii-hd-main">
                    <div class="qii-hd-icon"><i class="fas fa-images"></i></div>
                    <div>
                        <h5 class="modal-title mb-0" id="qiiImageModalLabel">Search Itinerary Image</h5>
                        <p class="qii-hd-sub mb-0" id="qiiImageModalSub">Find a photo for this day</p>
                    </div>
                </div>
                <button type="button" class="close qii-close-btn" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body qii-search-body">
                <div class="qii-search-bar">
                    <input type="text" class="form-control qii-search-input" id="qiiSearchInput" placeholder="Search destination, landmark, activity..." autocomplete="off">
                    <button type="button" class="btn qii-search-btn" id="qiiSearchBtn"><i class="fas fa-search"></i></button>
                </div>
                <div class="qii-source-note" id="qiiSourceNote"></div>
                <div class="qii-results-grid" id="qiiResultsGrid"></div>
            </div>
            <div class="modal-footer qii-modal-ft">
                <button type="button" class="btn qii-btn-ghost" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
