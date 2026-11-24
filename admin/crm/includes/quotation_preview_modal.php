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
                        <a href="#" class="btn qp-btn-edit btn-sm" id="leadQPreviewEditBtn" title="Open full quotation editor">
                            <i class="fas fa-edit mr-1"></i> Edit Quotation
                        </a>
                        <button type="button" class="btn btn-success btn-sm d-none" id="leadQPreviewSaveBtn" disabled>
                            <i class="fas fa-save mr-1"></i>Save Changes
                        </button>
                        <button type="button" class="qp-dl-btn" id="leadQPreviewDownloadBtn" title="Download PDF">
                            <span class="qp-dl-btn__text">Download</span>
                            <span class="qp-dl-btn__icon" aria-hidden="true">
                                <svg class="qp-dl-btn__svg" data-name="Layer 2" viewBox="0 0 35 35" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M17.5,22.131a1.249,1.249,0,0,1-1.25-1.25V2.187a1.25,1.25,0,0,1,2.5,0V20.881A1.25,1.25,0,0,1,17.5,22.131Z"></path>
                                    <path d="M17.5,22.693a3.189,3.189,0,0,1-2.262-.936L8.487,15.006a1.249,1.249,0,0,1,1.767-1.767l6.751,6.751a.7.7,0,0,0,.99,0l6.751-6.751a1.25,1.25,0,0,1,1.768,1.767l-6.752,6.751A3.191,3.191,0,0,1,17.5,22.693Z"></path>
                                    <path d="M31.436,34.063H3.564A3.318,3.318,0,0,1,.25,30.749V22.011a1.25,1.25,0,0,1,2.5,0v8.738a.815.815,0,0,0,.814.814H31.436a.815.815,0,0,0,.814-.814V22.011a1.25,1.25,0,1,1,2.5,0v8.738A3.318,3.318,0,0,1,31.436,34.063Z"></path>
                                </svg>
                            </span>
                        </button>
                        <button type="button" class="qp-print-btn" id="leadQPreviewPrintBtn" title="Print">
                            <span class="qp-printer-wrapper">
                                <span class="qp-printer-container">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 92 75" aria-hidden="true" focusable="false">
                                        <path
                                            stroke-width="5"
                                            stroke="black"
                                            d="M12 37.5H80C85.2467 37.5 89.5 41.7533 89.5 47V69C89.5 70.933 87.933 72.5 86 72.5H6C4.067 72.5 2.5 70.933 2.5 69V47C2.5 41.7533 6.75329 37.5 12 37.5Z"
                                        ></path>
                                        <mask fill="white" id="qp-print-mask-shared">
                                            <path
                                                d="M12 12C12 5.37258 17.3726 0 24 0H57C70.2548 0 81 10.7452 81 24V29H12V12Z"
                                            ></path>
                                        </mask>
                                        <path
                                            mask="url(#qp-print-mask-shared)"
                                            fill="black"
                                            d="M7 12C7 2.61116 14.6112 -5 24 -5H57C73.0163 -5 86 7.98374 86 24H76C76 13.5066 67.4934 5 57 5H24C20.134 5 17 8.13401 17 12H7ZM81 29H12H81ZM7 29V12C7 2.61116 14.6112 -5 24 -5V5C20.134 5 17 8.13401 17 12V29H7ZM57 -5C73.0163 -5 86 7.98374 86 24V29H76V24C76 13.5066 67.4934 5 57 5V-5Z"
                                        ></path>
                                        <circle fill="black" r="3" cy="49" cx="78"></circle>
                                    </svg>
                                </span>
                                <span class="qp-printer-page-wrapper">
                                    <span class="qp-printer-page"></span>
                                </span>
                            </span>
                            Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
