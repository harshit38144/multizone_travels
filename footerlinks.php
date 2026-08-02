<!-- WhatsApp Floating Button (digits from Site Settings; empty hides; NULL keeps legacy default) -->
<?php
if (!isset($siteSettings)) {
    $siteSettings = [];
}
$whatsappRaw = $siteSettings['whatsapp_phone'] ?? null;
if ($whatsappRaw === null) {
    $whatsappDigits = '1125425642';
} elseif (trim((string) $whatsappRaw) === '') {
    $whatsappDigits = '';
} else {
    $whatsappDigits = preg_replace('/\D+/', '', (string) $whatsappRaw);
}
?>
<?php if ($whatsappDigits !== ''): ?>
    <a href="https://wa.me/<?= htmlspecialchars($whatsappDigits) ?>" class="whatsapp-float" target="_blank"
        rel="noopener noreferrer" title="Chat on WhatsApp" aria-label="Chat on WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>
<?php endif; ?>

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTop">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- jQuery -->
<script src="js/jquery-3.7.0.min.js"></script>

<!-- Bootstrap JS -->
<script src="js/bootstrap.bundle.min.js"></script>

<!-- Owl Carousel JS -->
<script src="js/owl.carousel.min.js"></script>

<!-- AOS Animation JS -->
<script src="js/aos.js"></script>

<!-- Custom JS -->
<script src="js/main.js"></script>

<script>
    // Initialize AOS
    AOS.init({
        duration: 800,
        once: true
    });

    // Sticky Header on Scroll
    $(window).on('scroll', function () {
        if ($(window).scrollTop() > 100) {
            $('.main-header-new').addClass('scrolled');
        } else {
            $('.main-header-new').removeClass('scrolled');
        }
    });
    $(window).on('scroll', function () {
        if ($(window).scrollTop() > 100) {
            $('.nav-link-new').addClass('scrolled');
        } else {
            $('.nav-link-new').removeClass('scrolled');
        }
    });

    // Multi-Step Enquiry Wizard
    let currentStep = 1;
    const totalSteps = 4;

    function showStep(step) {
        $('.wizard-form-step').removeClass('active');
        $(`.wizard-form-step[data-step="${step}"]`).addClass('active');

        $('.wizard-step').removeClass('active completed');
        for (let i = 1; i < step; i++) {
            $(`.wizard-step[data-step="${i}"]`).addClass('completed');
        }
        $(`.wizard-step[data-step="${step}"]`).addClass('active');

        // Show/Hide navigation buttons
        if (step === 1) {
            $('#prevBtn').hide();
        } else {
            $('#prevBtn').show();
        }

        if (step === totalSteps) {
            $('#nextBtn').hide();
            $('#submitBtn').show();
            updateReviewSection();
        } else {
            $('#nextBtn').show();
            $('#submitBtn').hide();
        }
    }

    function validateStep(step) {
        let isValid = true;
        const currentStepEl = $(`.wizard-form-step[data-step="${step}"]`);

        // Clear previous validation
        currentStepEl.find('.is-invalid').removeClass('is-invalid');

        // Validate required fields in current step
        currentStepEl.find('input[required], select[required], textarea[required]').each(function () {
            if (!$(this).val()) {
                $(this).addClass('is-invalid');
                isValid = false;
            }
        });

        // Step 3: Validate at least one service is selected
        if (step === 3) {
            const checkedServices = $('.service-checkbox:checked').length;
            if (checkedServices === 0) {
                $('#services-error').show();
                isValid = false;
            } else {
                $('#services-error').hide();
            }
        }

        return isValid;
    }

    function updateReviewSection() {
        // Personal Info
        $('#review-name').text('Name: ' + $('#enq-name').val());
        $('#review-email').text('Email: ' + $('#enq-email').val());
        $('#review-phone').text('Phone: ' + $('#enq-phone').val());

        // Travel Details
        $('#review-destination').text('Destination: ' + $('#enq-destination').val());
        $('#review-travel-date').text('Travel Date: ' + $('#enq-travel-date').val());

        const rooms = $('#enq-rooms').val();
        const adults = $('#enq-adults').val();
        const children = $('#enq-children').val();
        $('#review-travelers').text(`Travelers: ${rooms} room(s), ${adults} adult(s), ${children} child(ren)`);

        const budget = $('#enq-budget').val();
        $('#review-budget').text('Budget: ' + (budget || 'Not specified'));

        // Services
        const services = [];
        $('.service-checkbox:checked').each(function () {
            const label = $('label[for="' + $(this).attr('id') + '"]').text().trim();
            services.push(label);
        });
        $('#review-services').text(services.join(', '));

        // Message
        const message = $('#enq-message').val();
        if (message) {
            $('#review-message-section').show();
            $('#review-message').text(message);
        } else {
            $('#review-message-section').hide();
        }
    }

    // Children ages dynamic fields
    $('#enq-children').on('change', function () {
        const childCount = parseInt($(this).val()) || 0;
        const container = $('#children-ages-container');
        container.empty();

        if (childCount > 0) {
            for (let i = 0; i < childCount; i++) {
                container.append(`
                        <div class="mb-2">
                            <label class="form-label small">Child ${i + 1} Age</label>
                            <input type="number" class="form-control form-control-sm" name="child_age_${i}" min="0" max="17" value="5">
                        </div>
                    `);
            }
        }
    });

    // Next button
    $('#nextBtn').on('click', function () {
        if (validateStep(currentStep)) {
            currentStep++;
            if (currentStep > totalSteps) currentStep = totalSteps;
            showStep(currentStep);
        }
    });

    // Previous button
    $('#prevBtn').on('click', function () {
        currentStep--;
        if (currentStep < 1) currentStep = 1;
        showStep(currentStep);
    });

    // Reset wizard on modal close
    $('#enquiryModal').on('hidden.bs.modal', function () {
        currentStep = 1;
        showStep(1);
        $('#enquiryForm')[0].reset();
        $('#enquiry-message').html('');
        $('#children-ages-container').empty();
    });

    // CRM Integration: Load Countries and Cities
    let crmAvailable = false;

    // Check CRM availability and load countries when modal opens
    $('#enquiryModal').on('shown.bs.modal', function () {
        loadDepartingCountries();
    });

    function loadDepartingCountries() {
        $.ajax({
            url: 'ajax/get_countries.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data.length > 0) {
                    crmAvailable = true;
                    $('#departing-location-section').show();

                    const countrySelect = $('#enq-departing-country');
                    countrySelect.empty();
                    countrySelect.append('<option value="">Select Country</option>');

                    let indiaId = null;

                    response.data.forEach(function (country) {
                        const option = $('<option></option>')
                            .attr('value', country.id)
                            .text(country.country_name);

                        // Check if this is India
                        if (country.country_name === 'India') {
                            indiaId = country.id;
                            option.prop('selected', true);
                        }

                        countrySelect.append(option);
                    });

                    // If India is found, load its cities by default
                    if (indiaId) {
                        loadDepartingCities(indiaId, 'Mumbai');
                    }
                } else {
                    crmAvailable = false;
                    $('#departing-location-section').hide();
                }
            },
            error: function () {
                crmAvailable = false;
                $('#departing-location-section').hide();
            }
        });
    }

    function loadDepartingCities(countryId, defaultCity = null) {
        if (!countryId || countryId <= 0) {
            return;
        }

        const citySelect = $('#enq-departing-city');
        citySelect.prop('disabled', true);
        citySelect.empty();
        citySelect.append('<option value="">Loading cities...</option>');

        $.ajax({
            url: 'ajax/get_cities_by_country.php?country_id=' + countryId,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                citySelect.empty();
                citySelect.append('<option value="">Select City</option>');

                if (response.success && response.data.length > 0) {
                    response.data.forEach(function (city) {
                        const cityText = city.city_name + (city.airport_code ? ' (' + city.airport_code + ')' : '');
                        const option = $('<option></option>')
                            .attr('value', city.id)
                            .text(cityText);

                        // Select default city if specified (e.g., Mumbai)
                        if (defaultCity && city.city_name.toLowerCase() === defaultCity.toLowerCase()) {
                            option.prop('selected', true);
                        }

                        citySelect.append(option);
                    });

                    citySelect.prop('disabled', false);
                } else {
                    citySelect.append('<option value="">No cities found</option>');
                }
            },
            error: function () {
                citySelect.empty();
                citySelect.append('<option value="">Error loading cities</option>');
            }
        });
    }

    // Handle country change
    $('#enq-departing-country').on('change', function () {
        const countryId = $(this).val();
        if (countryId) {
            loadDepartingCities(countryId);
        } else {
            $('#enq-departing-city').prop('disabled', true);
            $('#enq-departing-city').empty();
            $('#enq-departing-city').append('<option value="">Select City</option>');
        }
    });

    // Update review section to include departing location if CRM is available
    const originalUpdateReviewSection = updateReviewSection;
    updateReviewSection = function () {
        // Call original function
        originalUpdateReviewSection();

        // Add departing location if CRM is available
        if (crmAvailable) {
            const countryText = $('#enq-departing-country option:selected').text();
            const cityText = $('#enq-departing-city option:selected').text();

            if (countryText && countryText !== 'Select Country') {
                const departingText = cityText && cityText !== 'Select City'
                    ? `Departing: ${cityText}, ${countryText}`
                    : `Departing Country: ${countryText}`;

                // Add after travel date in review
                $('#review-travel-date').after(`<p class="mb-1" id="review-departing">${departingText}</p>`);
            }
        }
    };

    // Enquiry Form Submission
    $('#enquiryForm').on('submit', function (e) {
        e.preventDefault();

        // Show loading message
        $('#enquiry-message').html('<div class="alert alert-info">Submitting your enquiry...</div>');

        $.ajax({
            url: 'ajax/enquiry-submit.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#enquiry-message').html('');
                    $('#enquiryModal').one('hidden.bs.modal', function () {
                        var sentEl = document.getElementById('enquirySentModal');
                        if (sentEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            bootstrap.Modal.getOrCreateInstance(sentEl).show();
                        } else {
                            $('#enquirySentModal').modal('show');
                        }
                    });
                    $('#enquiryModal').modal('hide');
                } else {
                    $('#enquiry-message').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error Details:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });

                // Try to parse the response anyway
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        $('#enquiry-message').html('');
                        $('#enquiryModal').one('hidden.bs.modal', function () {
                            var sentEl = document.getElementById('enquirySentModal');
                            if (sentEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                bootstrap.Modal.getOrCreateInstance(sentEl).show();
                            } else {
                                $('#enquirySentModal').modal('show');
                            }
                        });
                        $('#enquiryModal').modal('hide');
                    } else {
                        $('#enquiry-message').html('<div class="alert alert-danger">' + response.message + '</div>');
                    }
                } catch (e) {
                    // If we can't parse JSON, show detailed error
                    console.error('JSON Parse Error:', e);
                    console.log('Raw Response:', xhr.responseText);
                    $('#enquiry-message').html('<div class="alert alert-danger">Error submitting enquiry. Please try again or contact support.</div>');
                }
            }
        });
    });

    // Newsletter Form Submission
    $('#newsletterForm').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax/newsletter-subscribe.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#newsletter-message').html('<small class="text-success">' + response.message + '</small>');
                    $('#newsletterForm')[0].reset();
                } else {
                    $('#newsletter-message').html('<small class="text-danger">' + response.message + '</small>');
                }
            },
            error: function () {
                $('#newsletter-message').html('<small class="text-danger">Error subscribing</small>');
            }
        });
    });
</script>