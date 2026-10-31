<?php
/** Shared Quotation Preview modal (leads + bookings). */
?>
        <div class="modal fade" id="leadQuotationPreviewModal" tabindex="-1" role="dialog"
            aria-labelledby="leadQuotationPreviewModalLabel" aria-hidden="true" data-backdrop="static">
            <div class="modal-dialog modal-dialog-centered qp-a4-dialog" role="document">
                <div class="modal-content qp-modal-content">
                    <div class="modal-header qp-modal-header">
                        <div class="qp-modal-title-wrap">
                            <h5 class="modal-title mb-0" id="leadQuotationPreviewModalLabel">
                                <i class="fas fa-eye mr-2"></i>Quotation Preview
                            </h5>
                            <small class="qp-modal-hint">Click any text to edit directly</small>
                            <small class="qp-modal-unsaved d-none ml-2" id="leadQPreviewUnsavedHint">
                                <i class="fas fa-circle" style="font-size:7px;vertical-align:middle;"></i> Unsaved changes
                            </small>
                        </div>
                        <button type="button" class="close qp-modal-close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="lead-q-preview-loading" id="leadQPreviewLoading">
                            <i class="fas fa-spinner fa-spin"></i> Loading preview…
                        </div>
                        <iframe class="lead-q-preview-frame d-none" id="leadQPreviewFrame"
                            title="Quotation Preview" src="about:blank"></iframe>
                    </div>
                    <div class="modal-footer qp-modal-footer">
                        <button type="button" class="btn qp-btn-close btn-sm" data-dismiss="modal">Close</button>
                        <a href="#" class="btn qp-btn-edit btn-sm" id="leadQPreviewEditBtn" title="Open full quotation editor">
                            <i class="fas fa-edit mr-1"></i> Edit Quotation
                        </a>
                        <button type="button" class="btn btn-success btn-sm d-none" id="leadQPreviewSaveBtn" disabled>
                            <i class="fas fa-save mr-1"></i>Save Changes
                        </button>
                        <button type="button" class="btn qp-btn-print btn-sm" id="leadQPreviewPrintBtn">
                            <i class="fas fa-print mr-1"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
