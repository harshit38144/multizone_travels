/**
 * TRAVEL WEBSITE - MAIN JAVASCRIPT
 */

(function($) {
    'use strict';

    // Document Ready
    $(document).ready(function() {

        // ========== STICKY HEADER ==========
        $(window).on('scroll', function() {
            if ($(this).scrollTop() > 100) {
                $('.main-header').addClass('sticky');
                $('#backToTop').fadeIn();
            } else {
                $('.main-header').removeClass('sticky');
                $('#backToTop').fadeOut();
            }
        });

        // ========== BACK TO TOP ==========
        $('#backToTop').on('click', function(e) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: 0
            }, 800);
        });

        // ========== SMOOTH SCROLLING ==========
        $('a[href^="#"]').on('click', function(e) {
            e.preventDefault();
            var target = $(this.hash);
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 80
                }, 800);
            }
        });

        // Helper: hide the navbar collapse using Bootstrap 5 API
        function closeMobileNav() {
            var collapseEl = document.getElementById('mainNav');
            if (collapseEl) {
                var bsCollapse = bootstrap.Collapse.getInstance(collapseEl);
                if (bsCollapse) bsCollapse.hide();
            }
        }

        // ========== MOBILE MENU CLOSE ON CLICK ==========
        // Only close menu for non-dropdown nav links
        $('.navbar-nav .nav-link:not(.dropdown-toggle)').on('click', function() {
            if ($(window).width() < 992) {
                closeMobileNav();
            }
        });

        // ========== MOBILE DROPDOWN HANDLING ==========
        // Close mobile menu when a dropdown item is clicked
        // Bootstrap 5 handles the dropdown toggle natively via data-bs-toggle="dropdown"
        $('.dropdown-menu .dropdown-item').on('click', function() {
            if ($(window).width() < 992) {
                closeMobileNav();
            }
        });

        // ========== TESTIMONIALS CAROUSEL ==========
        if ($('.testimonials-slider').length) {
            $('.testimonials-slider').owlCarousel({
                loop: true,
                margin: 30,
                nav: true,
                dots: true,
                autoplay: true,
                autoplayTimeout: 5000,
                autoplayHoverPause: true,
                navText: ["<i class='fas fa-chevron-left'></i>", "<i class='fas fa-chevron-right'></i>"],
                responsive: {
                    0: {
                        items: 1
                    },
                    768: {
                        items: 2
                    },
                    1024: {
                        items: 3
                    }
                }
            });
        }

        // ========== HERO SLIDER AUTO PLAY ==========
        if ($('#mainSlider').length) {
            $('#mainSlider').carousel({
                interval: 5000,
                pause: 'hover',
                wrap: true
            });
        }

        // ========== PRICE RANGE SLIDER ==========
        if ($('#priceRange').length) {
            var priceSlider = document.getElementById('priceRange');
            noUiSlider.create(priceSlider, {
                start: [0, 5000],
                connect: true,
                range: {
                    'min': 0,
                    'max': 10000
                },
                format: {
                    to: function(value) {
                        return Math.round(value);
                    },
                    from: function(value) {
                        return Number(value);
                    }
                }
            });

            priceSlider.noUiSlider.on('update', function(values, handle) {
                $('#minPrice').text('$' + values[0]);
                $('#maxPrice').text('$' + values[1]);
            });
        }

        // ========== IMAGE LIGHTBOX ==========
        if ($.fn.magnificPopup) {
            $('.image-gallery').magnificPopup({
                delegate: 'a',
                type: 'image',
                gallery: {
                    enabled: true,
                    navigateByImgClick: true,
                    preload: [0, 1]
                }
            });
        }

        // ========== COUNTER ANIMATION ==========
        if ($('.counter').length) {
            $('.counter').counterUp({
                delay: 10,
                time: 2000
            });
        }

        // ========== TOOLTIP INITIALIZATION ==========
        if ($.fn.tooltip) {
            $('[data-bs-toggle="tooltip"]').tooltip();
        }

        // ========== POPOVER INITIALIZATION ==========
        if ($.fn.popover) {
            $('[data-bs-toggle="popover"]').popover();
        }

        // ========== FORM VALIDATION ==========
        $('form').on('submit', function(e) {
            var form = $(this);

            // Remove previous error messages
            form.find('.error-message').remove();

            // Validate required fields
            var isValid = true;
            form.find('[required]').each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });

            // Validate email
            var emailFields = form.find('[type="email"]');
            emailFields.each(function() {
                var email = $(this).val();
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (email && !emailRegex.test(email)) {
                    isValid = false;
                    $(this).addClass('is-invalid');
                }
            });

            if (!isValid) {
                e.preventDefault();
                return false;
            }
        });

        // Remove invalid class on input
        $('input, textarea, select').on('input change', function() {
            $(this).removeClass('is-invalid');
        });

        // ========== PACKAGE FILTERS ==========
        $('.filter-checkbox').on('change', function() {
            applyFilters();
        });

        $('#sortBy').on('change', function() {
            applyFilters();
        });

        function applyFilters() {
            var filters = {
                categories: [],
                destinations: [],
                duration: [],
                priceMin: 0,
                priceMax: 10000,
                sortBy: $('#sortBy').val()
            };

            // Collect filter values
            $('.filter-checkbox:checked').each(function() {
                var type = $(this).data('filter-type');
                var value = $(this).val();
                if (filters[type]) {
                    filters[type].push(value);
                }
            });

            // AJAX request to filter packages
            $.ajax({
                url: 'ajax/filter-packages.php',
                method: 'POST',
                data: filters,
                beforeSend: function() {
                    $('.packages-grid').html('<div class="text-center"><div class="spinner-border" role="status"></div></div>');
                },
                success: function(response) {
                    $('.packages-grid').html(response);
                },
                error: function() {
                    $('.packages-grid').html('<div class="alert alert-danger">Error loading packages</div>');
                }
            });
        }

        // ========== SEARCH AUTOCOMPLETE ==========
        if ($('#destinationSearch').length) {
            $('#destinationSearch').autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: 'ajax/search-destinations.php',
                        dataType: 'json',
                        data: {
                            term: request.term
                        },
                        success: function(data) {
                            response(data);
                        }
                    });
                },
                minLength: 2
            });
        }

        // ========== LAZY LOADING IMAGES ==========
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img.lazy').forEach(img => {
                imageObserver.observe(img);
            });
        }

        // ========== PREVENT MULTIPLE FORM SUBMISSIONS ==========
        $('form').on('submit', function() {
            var submitBtn = $(this).find('[type="submit"]');
            submitBtn.prop('disabled', true);
            submitBtn.html('<span class="spinner-border spinner-border-sm me-2"></span>Processing...');

            setTimeout(function() {
                submitBtn.prop('disabled', false);
                submitBtn.html(submitBtn.data('original-text') || 'Submit');
            }, 3000);
        });

        // ========== READ MORE / READ LESS ==========
        $('.read-more-btn').on('click', function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            $(target).toggleClass('expanded');
            $(this).text($(target).hasClass('expanded') ? 'Read Less' : 'Read More');
        });

        // ========== COPY TO CLIPBOARD ==========
        $('.copy-btn').on('click', function() {
            var text = $(this).data('copy-text');
            navigator.clipboard.writeText(text).then(function() {
                alert('Copied to clipboard!');
            });
        });

        // ========== PRINT PAGE ==========
        $('.print-btn').on('click', function(e) {
            e.preventDefault();
            window.print();
        });

        // ========== SHARE BUTTONS ==========
        $('.share-facebook').on('click', function(e) {
            e.preventDefault();
            var url = encodeURIComponent(window.location.href);
            window.open('https://www.facebook.com/sharer/sharer.php?u=' + url, '_blank', 'width=600,height=400');
        });

        $('.share-twitter').on('click', function(e) {
            e.preventDefault();
            var url = encodeURIComponent(window.location.href);
            var text = encodeURIComponent(document.title);
            window.open('https://twitter.com/intent/tweet?url=' + url + '&text=' + text, '_blank', 'width=600,height=400');
        });

        $('.share-whatsapp').on('click', function(e) {
            e.preventDefault();
            var url = encodeURIComponent(window.location.href);
            window.open('https://wa.me/?text=' + url, '_blank');
        });

        // ========== CONSOLE LOG FOR DEBUGGING ==========
        console.log('Travel Website Scripts Loaded Successfully!');

    }); // End Document Ready

    // ========== WINDOW LOAD ==========
    $(window).on('load', function() {
        // Hide preloader
        $('.preloader').fadeOut();

        // Initialize masonry layout if exists
        if ($('.masonry-grid').length) {
            $('.masonry-grid').masonry({
                itemSelector: '.grid-item',
                columnWidth: '.grid-sizer',
                percentPosition: true
            });
        }
    });

    // ========== WINDOW RESIZE ==========
    $(window).on('resize', function() {
        // Adjust layouts on resize
    });

})(jQuery);
