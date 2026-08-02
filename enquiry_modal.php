<!-- Multi-Step Enquiry Modal -->
<div class="modal fade" id="enquiryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-paper-plane"></i> Send Enquiry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Progress Steps -->
                <div class="enquiry-wizard-steps mb-4">
                    <div class="wizard-step active" data-step="1">
                        <div class="step-number">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="wizard-step-line"></div>
                    <div class="wizard-step" data-step="2">
                        <div class="step-number">2</div>
                        <div class="step-label">Travel Details</div>
                    </div>
                    <div class="wizard-step-line"></div>
                    <div class="wizard-step" data-step="3">
                        <div class="step-number">3</div>
                        <div class="step-label">Services</div>
                    </div>
                    <div class="wizard-step-line"></div>
                    <div class="wizard-step" data-step="4">
                        <div class="step-number">4</div>
                        <div class="step-label">Review</div>
                    </div>
                </div>

                <form id="enquiryForm">
                    <!-- Step 1: Personal Information -->
                    <div class="wizard-form-step active" data-step="1">
                        <h6 class="mb-3">Personal Information</h6>
                        <div class="mb-3">
                            <label class="form-label">Your Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="enq-name"
                                placeholder="Enter your full name" required="">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="enq-email"
                                placeholder="Enter your email" required="">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="phone" id="enq-phone"
                                placeholder="Enter your phone number" required="">
                        </div>
                    </div>

                    <!-- Step 2: Travel Details -->
                    <div class="wizard-form-step" data-step="2">
                        <h6 class="mb-3">Travel Details</h6>
                        <div class="mb-3 enq-destination-wrap position-relative">
                            <label class="form-label" for="enq-destination">Destination <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="destination" id="enq-destination"
                                placeholder="Where do you want to go?" required=""
                                autocomplete="off" autocorrect="off" spellcheck="false">
                            <div id="enq-destination-suggestions" class="enq-destination-suggestions" role="listbox" aria-hidden="true"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="enq-travel-date">Travel Date <span class="text-danger">*</span></label>
                            <div class="enq-travel-date-field">
                                <input type="date" class="form-control" name="travel_date" id="enq-travel-date"
                                    required="">
                            </div>
                        </div>

                        <!-- Departing Country and City (CRM Integration) -->
                        <div id="departing-location-section" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Departing Country</label>
                                    <select class="form-control" name="departing_country_id"
                                        id="enq-departing-country">
                                        <option value="">Select Country</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Departing City</label>
                                    <select class="form-control" name="departing_city_id" id="enq-departing-city"
                                        disabled="">
                                        <option value="">Select City</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Rooms <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="rooms" id="enq-rooms" min="1"
                                    value="1" required="">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Adults <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="adults" id="enq-adults" min="1"
                                    value="2" required="">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Children</label>
                                <input type="number" class="form-control" name="children" id="enq-children" min="0"
                                    value="0">
                            </div>
                        </div>
                        <div id="children-ages-container"></div>
                        <div class="mb-3">
                            <label class="form-label">Budget (Optional)</label>
                            <select class="form-control" name="budget" id="enq-budget">
                                <option value="">Select Budget Range</option>
                                <option value="Below 50000">Below Rs. 50,000</option>
                                <option value="50000-100000">Rs. 50,000 - 1,00,000</option>
                                <option value="100000-200000">Rs. 1,00,000 - 2,00,000</option>
                                <option value="200000-300000">Rs. 2,00,000 - 3,00,000</option>
                                <option value="Above 300000">Above Rs. 3,00,000</option>
                            </select>
                        </div>
                    </div>

                    <!-- Step 3: Services & Preferences -->
                    <div class="wizard-form-step" data-step="3">
                        <h6 class="mb-3">Services Required <span class="text-danger">*</span></h6>
                        <p class="text-muted small mb-3">Select at least one service</p>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input service-checkbox" type="checkbox"
                                        name="services[]" value="hotel" id="service-hotel" checked="">
                                    <label class="form-check-label" for="service-hotel">
                                        <i class="fas fa-hotel me-2"></i> Hotel Accommodation
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input service-checkbox" type="checkbox"
                                        name="services[]" value="flight" id="service-flight">
                                    <label class="form-check-label" for="service-flight">
                                        <i class="fas fa-plane me-2"></i> Flight Booking
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input service-checkbox" type="checkbox"
                                        name="services[]" value="sightseeing" id="service-sightseeing" checked="">
                                    <label class="form-check-label" for="service-sightseeing">
                                        <i class="fas fa-binoculars me-2"></i> Sightseeing Tours
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input service-checkbox" type="checkbox"
                                        name="services[]" value="vehicle" id="service-vehicle">
                                    <label class="form-check-label" for="service-vehicle">
                                        <i class="fas fa-car me-2"></i> Vehicle/Transport
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input service-checkbox" type="checkbox"
                                        name="services[]" value="cruise" id="service-cruise">
                                    <label class="form-check-label" for="service-cruise">
                                        <i class="fas fa-ship me-2"></i> Cruise
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="invalid-feedback d-block" id="services-error" style="display: none !important;">
                            Please select at least one service
                        </div>
                        <div class="mb-3 mt-3">
                            <label class="form-label">Additional Requirements</label>
                            <textarea class="form-control" name="message" id="enq-message" rows="3"
                                placeholder="Any special requests or requirements?"></textarea>
                        </div>
                    </div>

                    <!-- Step 4: Review & Submit -->
                    <div class="wizard-form-step" data-step="4">
                        <h6 class="mb-3">Review Your Enquiry</h6>
                        <div class="review-section">
                            <div class="review-item">
                                <strong>Personal Information:</strong>
                                <p class="mb-1" id="review-name"></p>
                                <p class="mb-1" id="review-email"></p>
                                <p class="mb-1" id="review-phone"></p>
                            </div>
                            <hr>
                            <div class="review-item">
                                <strong>Travel Details:</strong>
                                <p class="mb-1" id="review-destination"></p>
                                <p class="mb-1" id="review-travel-date"></p>
                                <p class="mb-1" id="review-travelers"></p>
                                <p class="mb-1" id="review-budget"></p>
                            </div>
                            <hr>
                            <div class="review-item">
                                <strong>Services Required:</strong>
                                <p class="mb-1" id="review-services"></p>
                            </div>
                            <div class="review-item" id="review-message-section" style="display: none;">
                                <hr>
                                <strong>Additional Requirements:</strong>
                                <p class="mb-1" id="review-message"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden fields for form data -->
                    <input type="hidden" name="received_by" value="website">
                    <input type="hidden" name="type" value="Fresh">
                    <input type="hidden" name="stage" value="New Lead">
                    <input type="hidden" name="package_url" id="enq-package-url" value="">
                    <input type="hidden" name="package_image" id="enq-package-image" value="">
                    <input type="hidden" name="package_title" id="enq-package-title" value="">

                    <!-- Navigation Buttons -->
                    <div class="wizard-form-navigation mt-4">
                        <button type="button" class="btn btn-secondary" id="prevBtn" style="display: none;">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="button" class="btn btn-primary" id="nextBtn">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                        <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;">
                            <i class="fas fa-paper-plane"></i> Submit Enquiry
                        </button>
                    </div>

                    <div id="enquiry-message" class="mt-3"></div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Success popup after enquiry is submitted -->
<div class="modal fade" id="enquirySentModal" tabindex="-1" aria-labelledby="enquirySentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body text-center px-4 py-4 pt-5">
                <div class="text-success mb-3">
                    <i class="fas fa-check-circle fa-4x"></i>
                </div>
                <h5 class="fw-bold mb-2" id="enquirySentModalLabel">Enquiry Sent</h5>
                <p class="text-muted small mb-0">Thank you! Our team will get back to you shortly.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pb-4 pt-0">
                <button type="button" class="btn btn-primary px-4 rounded-pill" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Wizard Steps Styling */
    .enquiry-wizard-steps {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 20px;
    }

    .wizard-step {
        text-align: center;
        flex: 0 0 80px;
    }

    .wizard-step .step-number {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #e9ecef;
        color: #6c757d;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 8px;
        font-weight: bold;
        transition: all 0.3s;
    }

    .wizard-step.active .step-number,
    .wizard-step.completed .step-number {
        background: #0d6efd;
        color: white;
    }

    .wizard-step .step-label {
        font-size: 12px;
        color: #6c757d;
        font-weight: 500;
    }

    .wizard-step.active .step-label {
        color: #0d6efd;
        font-weight: 600;
    }

    .wizard-step-line {
        flex: 1;
        height: 2px;
        background: #e9ecef;
        margin: 0 10px;
        margin-bottom: 28px;
    }

    /* Form Steps */
    .wizard-form-step {
        display: none;
    }

    .wizard-form-step.active {
        display: block;
        animation: fadeIn 0.3s;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .wizard-form-navigation {
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }

    .wizard-form-navigation button {
        flex: 1;
    }

    .review-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
    }

    .review-item strong {
        color: #0d6efd;
        display: block;
        margin-bottom: 8px;
    }

    .review-item p {
        color: #495057;
        margin-left: 10px;
    }

    .form-check-label {
        cursor: pointer;
        user-select: none;
    }

    .enq-destination-suggestions {
        display: none;
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% - 0.25rem);
        margin-top: 2px;
        max-height: min(280px, 42vh);
        overflow-y: auto;
        background: #fff;
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 10px;
        box-shadow: 0 12px 36px rgba(26, 26, 46, 0.14);
        z-index: 1065;
    }

    .enq-destination-suggestions.active {
        display: block;
    }

    #enquiryModal.modal .enq-destination-wrap {
        z-index: 2;
    }

    .enq-dest-suggest-item {
        display: block;
        width: 100%;
        text-align: left;
        padding: 10px 14px;
        margin: 0;
        border: none;
        border-bottom: 1px solid #f1f3f5;
        background: #fff;
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .enq-dest-suggest-item:last-child {
        border-bottom: none;
    }

    .enq-dest-suggest-item:hover,
    .enq-dest-suggest-item:focus-visible {
        background: #f8fafc;
        outline: none;
    }

    .enq-dest-suggest-name {
        font-weight: 600;
        font-size: 14px;
        color: #1a1a2e;
        line-height: 1.3;
    }

    .enq-dest-suggest-meta {
        font-size: 12px;
        color: #868e96;
        margin-top: 2px;
        line-height: 1.35;
    }

    .enq-dest-suggest-empty {
        padding: 14px;
        font-size: 13px;
        color: #868e96;
        margin: 0;
    }

    .form-check-input:checked+.form-check-label {
        color: #0d6efd;
        font-weight: 500;
    }

    /* Travel date: full row opens native date picker (Chromium/WebKit calendar icon stretched) */
    .enq-travel-date-field {
        position: relative;
        display: block;
    }

    .enq-travel-date-field input[type="date"].form-control {
        width: 100%;
        cursor: pointer;
        position: relative;
    }

    .enq-travel-date-field input[type="date"].form-control::-webkit-calendar-picker-indicator {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
        cursor: pointer;
        opacity: 0;
        z-index: 1;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // When the enquiry modal is about to be shown
    var enquiryModal = document.getElementById('enquiryModal');
    if (enquiryModal) {
        enquiryModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            var button = event.relatedTarget;
            if (!button) return;
            
            var title = button.getAttribute('data-package-title');
            var url = button.getAttribute('data-package-url');
            var image = button.getAttribute('data-package-image');
            var destination = button.getAttribute('data-package-destination');
            
            // If explicit attributes aren't present, try to extract them from the closest card
            var card = button.closest('.package-card, .gd-card');
            if (card && !title) {
                var titleElem = card.querySelector('.package-title a, .gd-card-title');
                if (titleElem) title = titleElem.innerText.trim();
                
                var linkElem = card.querySelector('.package-title a, .package-footer a, .gd-view-btn');
                if (linkElem) url = linkElem.getAttribute('href');
                
                var imgElem = card.querySelector('img');
                if (imgElem) image = imgElem.getAttribute('src');
                
                var destElem = card.querySelector('.package-location, .gd-card-destinations');
                if (destElem) destination = destElem.innerText.trim();
            }
            
            // Populate hidden inputs
            if (title) document.getElementById('enq-package-title').value = title;
            
            if (url) {
                // Ensure absolute URL
                if (!url.startsWith('http')) {
                    url = window.location.origin + (url.startsWith('/') ? '' : '/') + url;
                }
                document.getElementById('enq-package-url').value = url;
            } else if (!document.getElementById('enq-package-url').value) {
                document.getElementById('enq-package-url').value = window.location.href;
            }
            
            if (image) {
                // Ensure absolute URL
                if (!image.startsWith('http')) {
                    image = window.location.origin + (image.startsWith('/') ? '' : '/') + image;
                }
                document.getElementById('enq-package-image').value = image;
            }
            
            if (destination && !document.getElementById('enq-destination').value) {
                document.getElementById('enq-destination').value = destination;
            }
            
            if (title && !document.getElementById('enq-message').value) {
                document.getElementById('enq-message').value = 'Enquiring about: ' + title;
            }
        });
    }

    (function initEnquiryDestinationSuggest() {
        var input = document.getElementById('enq-destination');
        var box = document.getElementById('enq-destination-suggestions');
        var wrap = input ? input.closest('.enq-destination-wrap') : null;
        if (!input || !box || !wrap) return;

        var debounceT = null;
        var lastController = null;

        function hideBox() {
            box.classList.remove('active');
            box.setAttribute('aria-hidden', 'true');
            box.innerHTML = '';
        }

        function renderItems(arr) {
            box.innerHTML = '';
            if (!arr.length) {
                var empty = document.createElement('p');
                empty.className = 'enq-dest-suggest-empty';
                empty.textContent = 'No destinations match. You can still type your own place.';
                box.appendChild(empty);
            } else {
                arr.forEach(function (it) {
                    if (!it || !it.name) return;
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'enq-dest-suggest-item';
                    btn.setAttribute('role', 'option');
                    var n = document.createElement('div');
                    n.className = 'enq-dest-suggest-name';
                    n.textContent = it.name;
                    btn.appendChild(n);
                    if (it.meta) {
                        var m = document.createElement('div');
                        m.className = 'enq-dest-suggest-meta';
                        m.textContent = it.meta;
                        btn.appendChild(m);
                    }
                    btn.addEventListener('click', function () {
                        input.value = it.name;
                        hideBox();
                        input.focus();
                    });
                    box.appendChild(btn);
                });
            }
            box.classList.add('active');
            box.setAttribute('aria-hidden', 'false');
        }

        function fetchSuggest(q) {
            if (lastController) lastController.abort();
            lastController = typeof AbortController !== 'undefined' ? new AbortController() : null;
            var opts = lastController ? { signal: lastController.signal } : {};
            var url = 'api/destination-suggestions.php?q=' + encodeURIComponent(q);
            fetch(url, opts)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    renderItems(data.items || []);
                })
                .catch(function () {
                    if (lastController && lastController.signal && lastController.signal.aborted) return;
                    hideBox();
                });
        }

        function scheduleFetch() {
            var q = input.value.trim();
            clearTimeout(debounceT);
            debounceT = setTimeout(function () {
                fetchSuggest(q);
            }, q ? 160 : 0);
        }

        input.addEventListener('focus', scheduleFetch);
        input.addEventListener('input', scheduleFetch);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') hideBox();
        });

        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) hideBox();
        });

        var modalEl = document.getElementById('enquiryModal');
        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', hideBox);
        }
    })();
});
</script>