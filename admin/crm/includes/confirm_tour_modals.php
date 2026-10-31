<?php
/** Shared Confirm Tour modals (leads + bookings). */
?>
        <!-- Confirm Tour (Book) modal -->
        <div class="modal fade" id="confirmTourModal" tabindex="-1" role="dialog" aria-labelledby="confirmTourModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header py-2 ct-modal-header">
                        <div>
                            <h5 class="modal-title mb-0" id="confirmTourModalLabel">Confirm Tour</h5>
                            <!-- <p class="ct-subtitle mb-0">Review traveller details, supplier services and confirm the tour.</p> -->
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="ctQuotationId" value="">
                        <input type="hidden" id="ctGuestName" value="">
                        <input type="hidden" id="ctMobileNo" value="">
                        <input type="hidden" id="ctEmail" value="">
                        <input type="hidden" id="ctGuestAttachmentName" value="">
                        <input type="hidden" id="ctGuestAttachmentPath" value="">

                        <div class="ct-confirm-hero">
                        <div class="ct-primary-card" id="ctPrimaryCard">
                                <div class="ct-primary-head">
                                    <div class="ct-primary-avatar" id="ctPrimaryAvatar">—</div>
                                    <div class="ct-primary-identity">
                                        <div class="ct-primary-line">
                                            <div class="ct-primary-name" id="ctPrimaryName">—</div>
                                            <span class="ct-primary-badge">Primary Traveller</span>
                                </div>
                                <div class="ct-primary-meta">
                                            <span><i class="fas fa-phone-alt"></i><span id="ctPrimaryPhone">—</span></span>
                                            <span><i class="fas fa-envelope"></i><span id="ctPrimaryEmail">—</span></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="ct-primary-stats" aria-label="Tour summary">
                                    <div class="ct-pstat is-destination">
                                        <span class="ct-pstat-icon"><i class="fas fa-map-marker-alt"></i></span>
                                        <div>
                                            <span class="ct-pstat-label">Destination</span>
                                            <span class="ct-pstat-val js-pstat-val">—</span>
                                        </div>
                                    </div>
                                    <div class="ct-pstat is-travel-date">
                                        <span class="ct-pstat-icon"><i class="far fa-calendar-alt"></i></span>
                                        <div>
                                            <span class="ct-pstat-label">Travel Date</span>
                                            <span class="ct-pstat-val js-pstat-val">—</span>
                                        </div>
                                    </div>
                                    <div class="ct-pstat is-departure">
                                        <span class="ct-pstat-icon"><i class="fas fa-plane"></i></span>
                                        <div>
                                            <span class="ct-pstat-label">Ex-City</span>
                                            <span class="ct-pstat-val js-pstat-val">—</span>
                                        </div>
                                    </div>
                                    <div class="ct-pstat is-pax">
                                        <span class="ct-pstat-icon"><i class="fas fa-user-friends"></i></span>
                                        <div>
                                            <span class="ct-pstat-label">PAX</span>
                                            <span class="ct-pstat-val js-pstat-val">—</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="ct-pay-summary" id="ctPaySummary">
                                <div class="ct-pay-head">
                                    <span class="ct-pay-head-icon" aria-hidden="true">
                                        <svg viewBox="0 0 16 16" fill="currentColor">
                                            <rect x="1" y="9" width="3.2" height="6" rx="0.7"></rect>
                                            <rect x="6.4" y="5" width="3.2" height="10" rx="0.7"></rect>
                                            <rect x="11.8" y="1" width="3.2" height="14" rx="0.7"></rect>
                                        </svg>
                                    </span>
                                    Customer Payment
                                </div>
                                <div class="ct-pay-figures">
                                    <div class="ct-pay-fig">
                                        <span class="ct-pay-label">Total Amount</span>
                                        <span class="ct-pay-val js-pay-total">—</span>
                                    </div>
                                    <div class="ct-pay-fig">
                                        <span class="ct-pay-label">Paid Amount</span>
                                        <span class="ct-pay-val js-pay-paid">—</span>
                                    </div>
                                    <div class="ct-pay-fig">
                                        <span class="ct-pay-label">Outstanding</span>
                                        <span class="ct-pay-val is-due js-pay-due">—</span>
                                    </div>
                                </div>
                                <div class="ct-pay-actions">
                                    <button type="button" class="ct-pay-add js-cust-pay-add">
                                        <i class="fas fa-plus"></i> Add Payment
                                    </button>
                                    <button type="button" class="ct-pay-schedule js-cust-pay-view">
                                        <i class="far fa-calendar-alt"></i> View Schedule
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="ct-primary-edit d-none" id="ctPrimaryEditPanel">
                            <div class="row">
                                <div class="col-md-4 form-group mb-2">
                                    <label class="ct-label" for="ctGuestNameEdit">Guest Name</label>
                                    <input type="text" class="form-control" id="ctGuestNameEdit" autocomplete="off">
                                </div>
                                <div class="col-md-4 form-group mb-2">
                                    <label class="ct-label" for="ctMobileNoEdit">Mobile No</label>
                                    <input type="text" class="form-control" id="ctMobileNoEdit" autocomplete="off">
                                </div>
                                <div class="col-md-4 form-group mb-2">
                                    <label class="ct-label" for="ctEmailEdit">Email</label>
                                    <input type="email" class="form-control" id="ctEmailEdit" autocomplete="off">
                                </div>
                            </div>
                            <div class="text-right">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="ctPrimaryEditCancel">Cancel</button>
                                <button type="button" class="btn btn-sm btn-primary" id="ctPrimaryEditApply">Apply</button>
                            </div>
                        </div>

                        <div class="ct-pax-section">
                            <div class="ct-pax-head">
                                <div class="ct-pax-head-main">
                                    <span class="ct-pax-head-icon" aria-hidden="true"><i class="fas fa-user-friends"></i></span>
                                <div>
                                    <div class="ct-pax-title">Travellers (PAX)</div>
                                        <!-- <div class="ct-pax-sub">Review traveller details and confirm the travellers for this tour.</div> -->
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm ct-add-guest-btn" id="ctAddGuestBtn">
                                    <i class="fas fa-plus mr-1"></i> Add Traveller
                                </button>
                            </div>
                            <div class="ct-pax-table-wrap">
                                <table class="ct-pax-table" id="ctPaxTable">
                                    <thead>
                                        <tr>
                                            <th style="width:42px;">#</th>
                                            <th>Name</th>
                                            <th style="width:90px;">Type</th>
                                            <th style="width:70px;">Age</th>
                                            <th>Passport</th>
                                            <th style="width:120px;">Documents</th>
                                            <th style="width:120px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ctPaxRows">
                                        <tr class="ct-pax-empty"><td colspan="7">No travellers yet. Click Add Traveller.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ct-included-block">
                            <div class="ct-included-head">
                                <span class="ct-included-icon" aria-hidden="true"><i class="fas fa-gift"></i></span>
                                <div>
                                    <div class="ct-included-title">What is Included</div>
                                    <!-- <div class="ct-included-sub">Manage supplier services, costs and payments for this tour.</div> -->
                                </div>
                            </div>
                        <div class="ct-included" id="ctIncludedChips"></div>
                        </div>

                        <div class="ct-svc-section-head">
                            <span class="ct-svc-section-icon"><i class="fas fa-database"></i></span>
                            <div>
                                <div class="ct-svc-section-title">Supplier &amp; Services</div>
                                <!-- <div class="ct-svc-section-sub">Manage suppliers, costs and documents for each service.</div> -->
                            </div>
                        </div>
                        <div class="ct-svc-table ct-svc-v2">
                            <div class="ct-detail-head">
                                <div>Service</div>
                                <div>Supplier</div>
                                <div>Attachments</div>
                                <div class="text-right">Total Cost (&#8377;)</div>
                                <div class="text-right">Paid (&#8377;)</div>
                                <div>Status</div>
                                <div>Actions</div>
                            </div>
                            <div id="ctDetailRows"></div>
                        </div>
                    </div>
                    <div class="modal-footer ct-svc-footer ct-svc-footer-v2">
                        <div class="ct-foot-pay" id="ctSvcSummary">
                            <div class="ct-foot-pay-title">
                                <svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true">
                                    <rect x="1" y="9" width="3.2" height="6" rx="0.7"></rect>
                                    <rect x="6.4" y="5" width="3.2" height="10" rx="0.7"></rect>
                                    <rect x="11.8" y="1" width="3.2" height="14" rx="0.7"></rect>
                                </svg>
                                Supplier Payment
                            </div>
                            <div class="ct-foot-metric">
                                <span class="ct-foot-label">Total Amount</span>
                                <span class="ct-foot-val js-foot-total">—</span>
                            </div>
                            <div class="ct-foot-metric">
                                <span class="ct-foot-label">Paid Amount</span>
                                <span class="ct-foot-val is-paid js-foot-paid">—</span>
                            </div>
                            <div class="ct-foot-metric">
                                <span class="ct-foot-label">Outstanding</span>
                                <span class="ct-foot-val is-due js-foot-due">—</span>
                            </div>
                        </div>
                        <div class="ct-svc-footer-actions">
                            <button type="button" class="btn ct-btn-cancel" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn ct-btn-save" id="ctSaveBtn"><i class="fas fa-suitcase mr-2"></i>Save Tour</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="askConfirmTourPopup" tabindex="-1" role="dialog" aria-labelledby="askConfirmTourPopupLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 420px;">
                <div class="modal-content" style="border: 0; border-radius: 16px; overflow: hidden;">
                    <div class="modal-body text-center" style="padding: 1.6rem 1.4rem 1.3rem;">
                        <div style="width: 58px; height: 58px; margin: 0 auto 0.8rem; border-radius: 50%; background: #fff1f2; color: #e11d48; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                            <i class="fas fa-suitcase-rolling"></i>
                        </div>
                        <h5 class="mb-2" id="askConfirmTourPopupLabel" style="font-weight: 800;">Confirm Tour</h5>
                        <p class="text-muted mb-3" style="font-size: 0.92rem;">Do you want to confirm this tour?</p>
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" id="askConfirmTourNo" style="min-width: 110px; border-radius: 10px; font-weight: 700;">No</button>
                        <button type="button" class="btn btn-danger ml-2" id="askConfirmTourYes" style="min-width: 110px; border-radius: 10px; font-weight: 700; background: #e11d48; border-color: #e11d48;">Yes</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="tourConfirmedPopup" tabindex="-1" role="dialog" aria-labelledby="tourConfirmedPopupLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 420px;">
                <div class="modal-content" style="border: 0; border-radius: 16px; overflow: hidden;">
                    <div class="modal-body text-center" style="padding: 1.6rem 1.4rem 1.3rem;">
                        <div style="width: 58px; height: 58px; margin: 0 auto 0.8rem; border-radius: 50%; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                            <i class="fas fa-check"></i>
                        </div>
                        <h5 class="mb-3" id="tourConfirmedPopupLabel" style="font-weight: 800;">Tour Confirmed</h5>
                        <button type="button" class="btn btn-outline-secondary" id="tourConfirmedOk" style="min-width: 110px; border-radius: 10px; font-weight: 700;">OK</button>
                        <button type="button" class="btn btn-danger ml-2" id="tourConfirmedShow" style="min-width: 140px; border-radius: 10px; font-weight: 700; background: #e11d48; border-color: #e11d48;">Show Booking</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm Tour — traveller documents -->
        <div class="modal fade" id="ctPaxDocsModal" tabindex="-1" role="dialog" aria-labelledby="ctPaxDocsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <div>
                            <h5 class="modal-title mb-0" id="ctPaxDocsModalLabel"><i class="fas fa-paperclip mr-1 text-muted"></i> Traveller Attachments</h5>
                            <div class="small text-muted" id="ctPaxDocsMeta"></div>
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="ct-docs-note small text-muted mb-3">
                            <i class="fas fa-compress-alt mr-1"></i> One file per document, max 4 MB. Files are compressed automatically before saving.
                        </div>
                        <div class="row" id="ctDocCards"></div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm Tour — traveller edit -->
        <div class="modal fade" id="ctTravellerModal" tabindex="-1" role="dialog" aria-labelledby="ctTravellerModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h5 class="modal-title" id="ctTravellerModalLabel">Traveller</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="ctTravellerEditId" value="">
                        <div class="alert alert-info py-2 px-3 small d-none" id="ctTravellerPrimaryNote">
                            <i class="fas fa-user-check mr-1"></i> Primary Contact — changes also update the lead and contact profile.
                        </div>
                        <div class="form-group d-none" id="ctTravellerSavedWrap">
                            <label class="ct-label" for="ctTravellerSaved">Select saved family / friend</label>
                            <select class="form-control" id="ctTravellerSaved"></select>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-7">
                                <label class="ct-label" for="ctTravellerName">Guest Name</label>
                                <input type="text" class="form-control" id="ctTravellerName" autocomplete="off">
                            </div>
                            <div class="form-group col-md-5" id="ctTravellerRelationWrap">
                                <label class="ct-label" for="ctTravellerRelation">Relation</label>
                                <select class="form-control" id="ctTravellerRelation">
                                    <option value="Spouse">Spouse</option>
                                    <option value="Parent">Parent</option>
                                    <option value="Sibling">Sibling</option>
                                    <option value="Son">Son</option>
                                    <option value="Daughter">Daughter</option>
                                    <option value="Friend">Friend</option>
                                    <option value="Relative" selected>Relative</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="ct-label" for="ctTravellerMobile">Mobile</label>
                                <input type="text" class="form-control" id="ctTravellerMobile" autocomplete="off">
                            </div>
                            <div class="form-group col-md-6">
                                <label class="ct-label" for="ctTravellerEmail">Email</label>
                                <input type="email" class="form-control" id="ctTravellerEmail" autocomplete="off">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="ct-label" for="ctTravellerType">Type</label>
                                <select class="form-control" id="ctTravellerType">
                                    <option value="adult">Adult</option>
                                    <option value="child">Child</option>
                                    <option value="infant">Infant</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="ct-label" for="ctTravellerAge">Age</label>
                                <input type="number" min="0" max="120" class="form-control" id="ctTravellerAge" placeholder="Years">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="ct-label" for="ctTravellerPassport">Passport No</label>
                                <input type="text" class="form-control" id="ctTravellerPassport" autocomplete="off">
                            </div>
                            <div class="form-group col-md-6">
                                <label class="ct-label" for="ctTravellerPassportExpiry">Passport Expiry</label>
                                <input type="date" class="form-control" id="ctTravellerPassportExpiry">
                            </div>
                        </div>
                        <div class="small text-muted mb-0">
                            <i class="fas fa-paperclip mr-1"></i> Upload documents with the attachment button in the travellers list.
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary btn-sm" id="ctTravellerSaveBtn">Save Traveller</button>
                    </div>
                </div>
            </div>
        </div>
