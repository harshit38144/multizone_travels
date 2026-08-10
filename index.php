<?php include('admin/connection.php'); ?>
<?php
$orderedSections = [];
if (isset($conn)) {
    $hs = $conn->query("SELECT * FROM homepage_sections ORDER BY display_order ASC");
    if ($hs) {
        while ($r = $hs->fetch_assoc()) {
            $orderedSections[] = $r;
        }
    }
}
// Fallback order if table is empty or not yet created
if (empty($orderedSections)) {
    $orderedSections = [
        ['section_key' => 'slider',               'is_active' => 1, 'bg_color' => 'transparent', 'section_heading' => null, 'section_subheading' => null],
        ['section_key' => 'trust_badges',          'is_active' => 1, 'bg_color' => '#000000', 'section_heading' => null, 'section_subheading' => null],
        ['section_key' => 'group_departures',      'is_active' => 1, 'bg_color' => '#fff5f5', 'section_heading' => null, 'section_subheading' => null],
        ['section_key' => 'categories',            'is_active' => 1, 'bg_color' => '#ffffff', 'section_heading' => null, 'section_subheading' => null],
        ['section_key' => 'trending_destinations', 'is_active' => 1, 'bg_color' => 'transparent', 'section_heading' => null, 'section_subheading' => null],
        ['section_key' => 'trending_packages',     'is_active' => 1, 'bg_color' => '#fcfcfc', 'section_heading' => null, 'section_subheading' => null],
        ['section_key' => 'features',              'is_active' => 1, 'bg_color' => '#ffffff', 'section_heading' => null, 'section_subheading' => null],
        ['section_key' => 'secondary_features',    'is_active' => 1, 'bg_color' => '#f8fafc', 'section_heading' => null, 'section_subheading' => null],
        ['section_key' => 'budget_filter',         'is_active' => 1, 'bg_color' => '#ffffff', 'section_heading' => null, 'section_subheading' => null],
        ['section_key' => 'live_counter',          'is_active' => 1, 'bg_color' => '#ffffff', 'section_heading' => null, 'section_subheading' => null],
        ['section_key' => 'instagram_reels',       'is_active' => 1, 'bg_color' => '#fdf6f0', 'section_heading' => null, 'section_subheading' => null],
    ];
}

/** When DB headings are empty/null, homepage uses these defaults (same as Homepage Sections seeded copy). */
$homepage_section_head_defaults = [
    'slider'               => ['', ''],
    'trust_badges'         => ['', ''],
    'group_departures'      => ['Group Departures', 'Fixed departure group tours with guaranteed dates'],
    'categories'            => ['Explore by Categories', 'Find your perfect vacation'],
    'trending_destinations' => ['TRENDING DESTINATIONS', 'Explore our most popular holiday destinations'],
    'trending_packages'     => ['Trending Packages', 'Handpicked tours for you'],
    'features'              => ['Why Plan Your Travel With Us?', 'Experience the difference with our exceptional services'],
    'secondary_features'    => ['Travel With Confidence', 'Everything you need for a smooth trip—from the first quote to the flight home.'],
    'budget_filter'         => ['HOLIDAYS FOR EVERY Budget', 'Choose your perfect getaway within your budget range'],
    'live_counter'          => ['Adrenaliverse Live: Journeys In Motion', 'See where the world is travelling right now.'],
    'instagram_reels'       => ['LOVE FROM THE GRAM ❤', 'Real stories from real travellers'],
];

$testimonials = [];
if (isset($conn)) {
    $conn->query("CREATE TABLE IF NOT EXISTS `testimonials` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `client_name` VARCHAR(120) NOT NULL,
        `client_location` VARCHAR(120) DEFAULT NULL,
        `client_image` VARCHAR(255) DEFAULT NULL,
        `testimonial_text` TEXT NOT NULL,
        `rating` TINYINT(1) NOT NULL DEFAULT 5,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `display_order` INT NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $tsCountRes = $conn->query("SELECT COUNT(*) AS c FROM testimonials");
    $tsCount = $tsCountRes ? (int)$tsCountRes->fetch_assoc()['c'] : 0;
    if ($tsCount === 0) {
        $conn->query("INSERT INTO testimonials (client_name, client_location, client_image, testimonial_text, rating, is_active, display_order) VALUES
            ('Ajay', 'Dubai', 'images/customer_68f093a55a3ab_1760596901.png', 'Excellent management by Adnan', 5, 1, 1),
            ('Rahul', 'Goa', 'images/customer_68f09a14b52f6_1760598548.jpg', 'Enjoyed alot', 4, 1, 2)");
    }

    $tsRes = $conn->query("SELECT * FROM testimonials WHERE is_active=1 ORDER BY display_order ASC, id DESC");
    if ($tsRes) {
        while ($row = $tsRes->fetch_assoc()) {
            if (!empty($row['client_image']) && strpos($row['client_image'], 'uploads/') === 0) {
                $row['client_image'] = 'admin/' . $row['client_image'];
            }
            $testimonials[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title>Home1 - Multizone Travels</title>

    <?php include('headerlinks.php'); ?>
</head>

<body>

    <?php include('header.php'); ?>

    <!-- Multi-Step Enquiry Modal -->
    <?php include('enquiry_modal.php'); ?>

    <!-- Main Content Wrapper -->
    <main>


        <?php foreach ($orderedSections as $section):
            $sk  = $section['section_key'];
            $sBg = !empty($section['bg_color']) && $section['bg_color'] !== 'transparent' ? $section['bg_color'] : null;
            $rawHead = array_key_exists('section_heading', $section) ? $section['section_heading'] : null;
            $rawSub  = array_key_exists('section_subheading', $section) ? $section['section_subheading'] : null;
            $defPair = $homepage_section_head_defaults[$sk] ?? ['', ''];
            $noHeadlineDefault = in_array($sk, ['slider', 'trust_badges'], true);
            if ($rawHead === null) {
                $secHeading = $noHeadlineDefault ? '' : ($defPair[0] ?? '');
            } else {
                $secHeading = trim((string)$rawHead);
            }
            if ($rawSub === null) {
                $secSubheading = $noHeadlineDefault ? '' : ($defPair[1] ?? '');
            } else {
                $secSubheading = trim((string)$rawSub);
            }
            if (!$section['is_active']) continue;
        ?>

        <?php if ($sk === 'slider'): ?>
        <!-- Hero Slider -->
        <?php if ($secHeading !== '' || $secSubheading !== ''): ?>
        <div class="container text-center hero-section-editorial-head pt-4 pb-0">
            <?php if ($secHeading !== ''): ?>
                <h2 class="h4 mb-1 fw-semibold"><?= htmlspecialchars($secHeading, ENT_QUOTES, 'UTF-8') ?></h2>
            <?php endif; ?>
            <?php if ($secSubheading !== ''): ?>
                <p class="text-muted small mb-0"><?= htmlspecialchars($secSubheading, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <section class="hero-slider-new">
            <div id="mainSlider" class="carousel slide carousel-fade" data-bs-ride="false" data-bs-interval="false">
                <div class="carousel-inner">
                    <?php
                    $sliders = [];
                    if (isset($conn)) {
                        $sl_result = $conn->query("SELECT * FROM sliders WHERE is_active = 1 ORDER BY display_order ASC");
                        if ($sl_result && $sl_result->num_rows > 0) {
                            while ($sl_row = $sl_result->fetch_assoc()) {
                                $sliders[] = $sl_row;
                            }
                        }
                    }

                    if (!empty($sliders)):
                        foreach ($sliders as $sl_index => $sl):
                            $isActive = ($sl_index === 0) ? 'active' : '';
                            $opacity = isset($sl['overlay_opacity']) ? intval($sl['overlay_opacity']) / 100 : 0.4;
                            $heading = !empty($sl['heading']) ? $sl['heading'] : '';
                            $subheading = !empty($sl['subheading']) ? $sl['subheading'] : '';
                            $btnText = !empty($sl['button_text']) ? $sl['button_text'] : '';
                            $btnLink = !empty($sl['button_link']) ? $sl['button_link'] : '#';
                            // Fix path: files are stored relative to admin/, prefix for root access
                            $mediaPath = !empty($sl['media_file']) ? 'admin/' . ltrim($sl['media_file'], '/') : '';
                            ?>
                            <div class="carousel-item <?= $isActive ?>">
                                <?php if ($sl['media_type'] === 'video'): ?>
                                    <video class="d-block w-100 slider-video" autoplay muted loop playsinline>
                                        <source src="<?= htmlspecialchars($mediaPath) ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php else: ?>
                                    <img src="<?= htmlspecialchars($mediaPath) ?>" class="d-block w-100" alt="Slider Image"
                                        style="width:100%;height:100%;object-fit:cover;">
                                <?php endif; ?>

                                <div class="carousel-overlay" style="opacity: <?= $opacity ?> ;"></div>
                                <div class="carousel-caption-new">
                                    <div class="container">
                                        <?php if (!empty($heading)): ?>
                                            <h1 class="slider-heading" data-aos="fade-up">
                                                <?= $heading ?>
                                            </h1>
                                        <?php endif; ?>

                                        <?php if (!empty($subheading)): ?>
                                            <p class="slider-subheading" data-aos="fade-up" data-aos-delay="50">
                                                <?= $subheading ?>
                                            </p>
                                        <?php endif; ?>



                                        <!-- Search Box on every slide -->
                                        <div class="slider-search-box" data-aos="fade-up" data-aos-delay="100">
                                            <form action="search.php" method="GET">
                                                <div class="search-input-wrapper">
                                                    <i class="fas fa-search search-icon-left"></i>
                                                    <input type="text" class="search-input" name="q"
                                                        placeholder="Search packages, destinations, countries..."
                                                        autocomplete="off" id="slider-search-input-<?= $sl_index ?>"
                                                        required="">
                                                    <div class="search-suggestions"></div>
                                                </div>
                                            </form>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <!-- Fallback slide if no sliders in DB -->
                        <div class="carousel-item active">
                            <video class="d-block w-100 slider-video" autoplay muted loop playsinline>
                                <source src="media/slider_video_68fb33ebcd62d.mp4" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                            <div class="carousel-overlay" style="opacity: 0.4;"></div>
                            <div class="carousel-caption-new">
                                <div class="container">
                                    <h1 class="slider-heading" data-aos="fade-up">
                                        Your <span style="background:linear-gradient(135deg,var(--gradient-start-color),var(--gradient-end-color));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"> Destination </span> Treat
                                    </h1>
                                    <p class="slider-subheading" data-aos="fade-up" data-aos-delay="50">
                                        <strong> search</strong> your favorite Vacation Place
                                    </p>
                                    <!-- Search Box Integrated in Slider -->
                                    <div class="slider-search-box" data-aos="fade-up" data-aos-delay="100">
                                        <form action="search.php" method="GET">
                                            <div class="search-input-wrapper">
                                                <i class="fas fa-search search-icon-left"></i>
                                                <input type="text" class="search-input" name="q"
                                                    placeholder="Search packages, destinations, countries..."
                                                    autocomplete="off" id="slider-search-input" required="">
                                                <div id="search-suggestions" class="search-suggestions"></div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (count($sliders) > 1): ?>
                    <!-- Carousel Controls (only shown if multiple slides) -->
                    <button class="carousel-control-prev" type="button" data-bs-target="#mainSlider" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#mainSlider" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                <?php endif; ?>
            </div>
        </section>
        <?php elseif ($sk === 'trust_badges'): ?>
        <div style="background-color: <?= $sBg ?? '#000000' ?>"> <!-- Trust Badges Bar -->
            <section class="trust-badges-bar">
                <div class="container">
                    <?php if ($secHeading !== '' || $secSubheading !== ''): ?>
                        <div class="text-center pb-3 pt-3">
                            <?php if ($secHeading !== ''): ?>
                                <h2 class="h5 mb-1 text-white"><?= htmlspecialchars($secHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                            <?php endif; ?>
                            <?php if ($secSubheading !== ''): ?>
                                <p class="small text-white-50 mb-0"><?= htmlspecialchars($secSubheading, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="badges-wrapper">
                        <div class="badge-item">
                            <div class="badge-icon">
                                <i class="fab fa-google" style="color: #4CAF50; font-size: 22px;"></i>
                            </div>
                            <span class="badge-text">4.6 <i class="fas fa-star"></i> rated</span>
                        </div>

                        <div class="badge-item">
                            <div class="badge-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <span class="badge-text">100% Customised Trips</span>
                        </div>

                        <!-- <div class="badge-item">
                            <div class="badge-icon">
                                <i class="fas fa-passport"></i>
                            </div>
                            <span class="badge-text">98% Visa Success Rate</span>
                        </div> -->

                        <div class="badge-item">
                            <div class="badge-icon">
                                <i class="fas fa-headset"></i>
                            </div>
                            <span class="badge-text">24x7 Concierge</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <?php elseif ($sk === 'categories'): ?>
        <div style="background-color: <?= $sBg ?? '#ffffff' ?>"> <!-- Categories Section -->
            <section class="categories-section py-3">
                <div class="container">
                    <div class="section-header text-center mb-3" data-aos="fade-up">
                        <h2 class="section-title mt-0"><?= htmlspecialchars($secHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                        <?php if ($secSubheading !== ''): ?>
                            <p class="section-subtitle"><?= htmlspecialchars($secSubheading, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="category-grid">
                        <?php
                        $cat_result = $conn->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order ASC LIMIT 6");
                        if ($cat_result && $cat_result->num_rows > 0):
                            $cat_index = 0;
                            while ($cat = $cat_result->fetch_assoc()):
                                $cat_index++;
                                $cat_pos    = 'cat-pos-' . $cat_index; // cycles: cat-pos-1, cat-pos-2 ...
                                $cat_delay  = ($cat_index - 1) * 100;
                                $cat_img    = !empty($cat['image']) ? 'admin/' . ltrim($cat['image'], '/') : '';
                                $cat_link   = !empty($cat['slug']) ? 'b1.php?category=' . urlencode($cat['slug']) : 'b1.php';
                                $cat_name   = htmlspecialchars($cat['name']);
                                $cat_desc   = !empty($cat['description']) ? htmlspecialchars($cat['description']) : '';
                        ?>
                        <a href="<?= $cat_link ?>" class="category-card-new <?= $cat_pos ?>" data-aos="fade-up" data-aos-delay="<?= $cat_delay ?>">
                            <?php if (!empty($cat_img)): ?>
                                <img src="<?= $cat_img ?>" alt="<?= $cat_name ?>">
                            <?php else: ?>
                                <div class="cat-no-img-placeholder"><i class="fas fa-image fa-3x"></i></div>
                            <?php endif; ?>
                            <div class="category-card-overlay">
                                <div class="category-card-content">
                                    <h3><?= $cat_name ?></h3>
                                    <?php if (!empty($cat_desc)): ?>
                                        <p><?= $cat_desc ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                        <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5 w-100">
                                <i class="fas fa-th-large fa-3x text-muted mb-3 d-block"></i>
                                <p class="text-muted">No categories found. Add categories from the admin panel.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
        <?php elseif ($sk === 'group_departures'): ?>
        <div style="background-color: <?= $sBg ?? '#fff5f5' ?>"> <!-- Group Departures Section -->
            <section class="group-departures-section py-5">
                <div class="container">
                    <div class="section-header-with-filters mb-4" data-aos="fade-up">
                        <div>
                            <h2 class="section-title mt-0"><?= htmlspecialchars($secHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                            <?php if ($secSubheading !== ''): ?>
                                <p class="section-subtitle mt-3 mb-0"><?= htmlspecialchars($secSubheading, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                        <a href="#" class="btn btn-outline-primary btn-sm">
                            View All <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>

                    <?php
                        $gdCards = [];
                        if ($conn) {
                            $gdSql = "SELECT * FROM group_departures WHERE status='Published' ORDER BY created_at DESC";
                            $gdRes = $conn->query($gdSql);
                            if ($gdRes && $gdRes->num_rows > 0) {
                                while ($r = $gdRes->fetch_assoc()) {
                                    $gdCards[] = $r;
                                }
                            }
                        }
                        $gdCarouselId = 'gdDeparturesCarousel';
                    ?>
                    <?php if (empty($gdCards)): ?>
                        <div class="gd-empty-state text-center py-5 px-3 rounded-3 bg-white shadow-sm" data-aos="fade-up" data-aos-delay="100">
                            <p class="text-muted mb-0">No group departures currently available. Please check back later.</p>
                        </div>
                    <?php else: ?>
                        <div id="<?= htmlspecialchars($gdCarouselId, ENT_QUOTES, 'UTF-8') ?>" class="carousel slide gd-departures-carousel" data-bs-ride="false" data-bs-interval="false" data-aos="fade-up" data-aos-delay="100">
                            <div class="carousel-inner">
                                <?php foreach ($gdCards as $idx => $gd):
                                    $gd_id = $gd['id'];
                                    $link = "b6.php?id=" . $gd_id;
                                    $badgesHtml = '';
                                    if (!empty($gd['is_flight_included'])) {
                                        $badgesHtml .= '<span class="gd-badge gd-badge-flight"><i class="fas fa-plane"></i> Flights included</span> ';
                                    }
                                    if (!empty($gd['ex_city'])) {
                                        $badgesHtml .= '<span class="gd-badge gd-badge-city">Ex-' . htmlspecialchars($gd['ex_city']) . '</span> ';
                                    }
                                    if (!empty($gd['duration_nights']) || !empty($gd['duration_days'])) {
                                        $badgesHtml .= '<span class="gd-badge gd-badge-duration">' . (int)$gd['duration_nights'] . 'N & ' . (int)$gd['duration_days'] . 'D</span>';
                                    }
                                    $seatsAvailable = (int)$gd['seats_available'];
                                    $totalSeats = (int)$gd['total_seats'];
                                    $seatPercent = $totalSeats > 0 ? (100 - (($seatsAvailable / $totalSeats) * 100)) : 0;
                                    $isLowSeats = $seatsAvailable <= 10;
                                    $seatDotClass = $isLowSeats ? 'gd-seats-low' : '';
                                    $seatTextClass = $isLowSeats ? 'text-danger fw-bold' : '';
                                    $seatBarClass = $isLowSeats ? 'bg-danger' : '';
                                    $seatTextPrefix = $isLowSeats ? 'Only ' : '';
                                ?>
                                <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>">
                                    <div class="gd-feature-card shadow rounded-3 overflow-hidden bg-white mx-auto border border-light-subtle">
                                        <div class="row g-0 align-items-stretch">
                                            <div class="col-md-5 col-lg-4 gd-feature-card-media">
                                                <div class="gd-card-image h-100">
                                                    <?php if (!empty($gd['featured_image']) && file_exists('admin/' . $gd['featured_image'])): ?>
                                                        <img src="admin/<?= htmlspecialchars($gd['featured_image']) ?>" alt="<?= htmlspecialchars($gd['title']) ?>">
                                                    <?php else: ?>
                                                        <div class="gd-card-placeholder"><i class="fas fa-image fa-3x text-muted"></i></div>
                                                    <?php endif; ?>
                                                    <div class="gd-card-badges"><?= $badgesHtml ?></div>
                                                    <?php if (!empty($gd['is_newly_launched'])): ?>
                                                        <div class="gd-newly-launched"><i class="fas fa-circle"></i> Newly Launched</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-7 col-lg-8 gd-feature-card-body-wrap">
                                                <div class="gd-card-body gd-feature-card-body">
                                                    <?php if (!empty($gd['destination_cities'])): ?>
                                                        <div class="gd-card-destinations text-muted small"><?= htmlspecialchars($gd['destination_cities']) ?></div>
                                                    <?php endif; ?>
                                                    <h3 class="gd-card-title gd-feature-title">
                                                        <?= htmlspecialchars($gd['title']) ?>
                                                        <?php if (!empty($gd['star_rating'])): ?>
                                                            <span class="gd-rating"><?= htmlspecialchars($gd['star_rating']) ?></span>
                                                        <?php endif; ?>
                                                    </h3>

                                                    <div class="gd-card-info">
                                                        <i class="fas fa-calendar-alt"></i>
                                                        <?= htmlspecialchars($gd['departure_day']) ?> Departures<?php if (!empty($gd['departure_months'])): ?> | <?= htmlspecialchars($gd['departure_months']) ?><?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($gd['ex_city'])): ?>
                                                        <div class="gd-card-info">
                                                            <i class="fas fa-map-marker-alt"></i>
                                                            From <?= htmlspecialchars($gd['ex_city']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($gd['is_flight_included']) || !empty($gd['is_meals_included']) || !empty($gd['experiences'])): ?>
                                                        <div class="gd-card-info">
                                                            <i class="fas fa-check-circle"></i> Multiple Inclusions
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="gd-card-info">
                                                            <i class="fas fa-shuttle-van"></i> Transfers included
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="gd-seats-info mt-2">
                                                        <span class="gd-seats-dot <?= $seatDotClass ?>"></span>
                                                        <span class="<?= $seatTextClass ?>"><?= $seatTextPrefix ?><?= $seatsAvailable ?> seats left</span>
                                                        <span class="text-muted ms-2"><?= $totalSeats ?> total</span>
                                                        <div class="gd-seats-bar mt-1">
                                                            <div class="gd-seats-bar-fill <?= $seatBarClass ?>" style="width: <?= (float)$seatPercent ?>%"></div>
                                                        </div>
                                                    </div>

                                                    <div class="gd-card-footer mt-4 flex-column flex-sm-row align-items-start align-items-sm-end gap-3 border-0 pt-0 mt-auto">
                                                        <div class="gd-price">
                                                            <span class="gd-price-label">starting from</span>
                                                            <?php if (((float)$gd['price']) > ((float)$gd['discounted_price']) && ((float)$gd['discounted_price']) > 0): ?>
                                                                <span class="gd-price-old">Rs.<?= number_format((float)$gd['price']) ?></span>
                                                                <span class="gd-price-current">Rs.<?= number_format((float)$gd['discounted_price']) ?></span>
                                                            <?php else: ?>
                                                                <span class="gd-price-current">Rs.<?= number_format((float)$gd['price']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" class="btn gd-view-btn d-inline-flex align-items-center px-4">View Details<i class="fas fa-arrow-right ms-2 small"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if (count($gdCards) > 1): ?>
                                <div class="carousel-indicators gd-carousel-indicators">
                                    <?php foreach ($gdCards as $dotIdx => $_): ?>
                                        <button type="button" data-bs-target="#<?= htmlspecialchars($gdCarouselId, ENT_QUOTES, 'UTF-8') ?>" data-bs-slide-to="<?= (int)$dotIdx ?>" <?= $dotIdx === 0 ? 'class="active" aria-current="true"' : '' ?> aria-label="Slide <?= (int)$dotIdx + 1 ?>"></button>
                                    <?php endforeach; ?>
                                </div>
                                <button class="carousel-control-prev gd-carousel-controls" type="button" data-bs-target="#<?= htmlspecialchars($gdCarouselId, ENT_QUOTES, 'UTF-8') ?>" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Previous</span>
                                </button>
                                <button class="carousel-control-next gd-carousel-controls" type="button" data-bs-target="#<?= htmlspecialchars($gdCarouselId, ENT_QUOTES, 'UTF-8') ?>" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Next</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <style>
                /* Group Departures — one full-width card per slide */
                .gd-departures-carousel {
                    position: relative;
                    padding-bottom: 0;
                }

                .gd-departures-carousel .carousel-inner {
                    overflow: hidden;
                    border-radius: 0.75rem;
                }

                .gd-departures-carousel .carousel-item {
                    height: 100%;
                }

                .gd-feature-card {
                    max-width: 100%;
                }

                .gd-feature-card-media {
                    background: #e9ecef;
                    min-height: 220px;
                }

                @media (min-width: 768px) {
                    .gd-feature-card-media {
                        min-height: 340px;
                    }
                }

                .gd-departures-carousel .gd-card-image {
                    position: relative;
                    height: 100%;
                    min-height: 220px;
                    overflow: hidden;
                }

                @media (min-width: 768px) {
                    .gd-departures-carousel .gd-card-image {
                        min-height: 340px;
                    }
                }

                .gd-departures-carousel .gd-card-image img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }

                .gd-feature-card-body {
                    padding: 1.5rem !important;
                }

                @media (min-width: 992px) {
                    .gd-feature-card-body {
                        padding: 2rem 2.25rem !important;
                    }
                }

                .gd-feature-title {
                    font-size: clamp(1.15rem, 2.5vw, 1.5rem);
                    line-height: 1.35;
                    flex-wrap: wrap;
                    margin-top: 0.35rem !important;
                }

                .gd-feature-card-body-wrap .gd-card-body {
                    height: 100%;
                }

                .gd-carousel-controls {
                    width: 3rem;
                    height: 3rem;
                    top: 45%;
                    bottom: auto;
                    transform: translateY(-50%);
                    border-radius: 50%;
                    background: #fff !important;
                    opacity: 1 !important;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
                    filter: none;
                    z-index: 3;
                }

                .gd-carousel-controls .carousel-control-prev-icon,
                .gd-carousel-controls .carousel-control-next-icon {
                    filter: invert(0.55);
                    width: 1.25rem;
                    height: 1.25rem;
                }

                .gd-carousel-controls.carousel-control-prev {
                    left: max(0.5rem, calc(0.5vw + 4px));
                }

                .gd-carousel-controls.carousel-control-next {
                    right: max(0.5rem, calc(0.5vw + 4px));
                }

                .gd-carousel-indicators {
                    position: static;
                    justify-content: center;
                    gap: 0.5rem;
                    margin: 1rem 0 0;
                }

                .gd-carousel-indicators [data-bs-target] {
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    background-color: rgba(26, 26, 46, 0.25);
                    border: none;
                    margin: 0;
                }

                .gd-carousel-indicators [data-bs-target].active {
                    background-color: #6c5ce7;
                    opacity: 1;
                }

                .gd-departures-carousel .gd-card-placeholder {
                    width: 100%;
                    height: 100%;
                    min-height: 220px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #f0f0f0;
                }

                @media (min-width: 768px) {
                    .gd-departures-carousel .gd-card-placeholder {
                        min-height: 340px;
                    }
                }

                .gd-card-badges {
                    position: absolute;
                    top: 12px;
                    left: 12px;
                    display: flex;
                    gap: 6px;
                    flex-wrap: wrap;
                    max-width: calc(100% - 24px);
                }

                .gd-badge {
                    font-size: 11px;
                    font-weight: 600;
                    padding: 4px 10px;
                    border-radius: 20px;
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                }

                .gd-badge-flight {
                    background: #fff;
                    color: #333;
                    border: 1px solid rgba(0, 0, 0, 0.08);
                }

                .gd-badge-city {
                    background: #f0932b;
                    color: #fff;
                }

                .gd-badge-duration {
                    background: #6c5ce7;
                    color: #fff;
                }

                .gd-newly-launched {
                    position: absolute;
                    bottom: 12px;
                    left: 12px;
                    background: rgba(0, 180, 80, 0.92);
                    color: #fff;
                    font-size: 11px;
                    font-weight: 600;
                    padding: 5px 12px;
                    border-radius: 20px;
                }

                .gd-newly-launched i {
                    font-size: 8px;
                    margin-right: 4px;
                    vertical-align: middle;
                }

                .gd-card-body {
                    display: flex;
                    flex-direction: column;
                    flex-grow: 1;
                }

                .gd-card-title {
                    font-weight: 700;
                    color: #1a1a2e;
                    margin: 4px 0 12px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                .gd-rating {
                    background: #f39c12;
                    color: #fff;
                    font-size: 11px;
                    padding: 2px 8px;
                    border-radius: 4px;
                    font-weight: 700;
                }

                .gd-card-info {
                    font-size: 0.9375rem;
                    color: #555;
                    margin-bottom: 0.35rem;
                }

                .gd-card-info i {
                    width: 1.15rem;
                    color: #888;
                    margin-right: 2px;
                }

                .gd-seats-info {
                    font-size: 13px;
                }

                .gd-seats-dot {
                    display: inline-block;
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    background: #27ae60;
                    margin-right: 4px;
                }

                .gd-seats-dot.gd-seats-low {
                    background: #e74c3c;
                }

                .gd-seats-bar {
                    height: 4px;
                    background: #eee;
                    border-radius: 2px;
                    overflow: hidden;
                    max-width: 280px;
                }

                .gd-seats-bar-fill {
                    height: 100%;
                    background: #e74c3c;
                    border-radius: 2px;
                    transition: width 0.5s ease;
                }

                .gd-card-footer {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                }

                .gd-price-label {
                    display: block;
                    font-size: 11px;
                    color: #999;
                }

                .gd-price-old {
                    font-size: 13px;
                    color: #999;
                    text-decoration: line-through;
                    margin-right: 4px;
                }

                .gd-price-current {
                    font-size: 1.35rem;
                    font-weight: 800;
                    color: #1a1a2e;
                }

                .gd-view-btn {
                    background: linear-gradient(135deg, #6c5ce7 0%, #5b4dcf 100%);
                    color: #fff !important;
                    font-size: 14px;
                    font-weight: 600;
                    padding: 0.55rem 1.25rem;
                    border-radius: 8px;
                    border: none;
                    white-space: nowrap;
                    box-shadow: 0 8px 20px rgba(108, 92, 231, 0.3);
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                }

                .gd-view-btn:hover {
                    transform: translateY(-1px);
                    color: #fff !important;
                    box-shadow: 0 12px 28px rgba(108, 92, 231, 0.35);
                }
            </style>
        </div>
        <?php elseif ($sk === 'trending_destinations'): ?>
        <div style="background-color: <?= $sBg ?? '#fdf6f0' ?>"> <!-- Trending Destinations Section -->
            <section class="trending-destinations-section py-5">
                <div class="container">
                    <div class="section-header-with-filters mb-4" data-aos="fade-up">
                        <div>
                            <h2 class="section-title mt-0"><?= htmlspecialchars($secHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                            <?php if ($secSubheading !== ''): ?>
                                <p class="section-subtitle mt-3 mb-0"><?= htmlspecialchars($secSubheading, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="duration-filters">
                            <button class="duration-filter-btn active" data-duration="all">All</button>
                            <button class="duration-filter-btn" data-duration="5-8">5-8 Days</button>
                            <button class="duration-filter-btn" data-duration="10+">10+ Days</button>
                            <button class="duration-filter-btn" data-duration="<5">Less than 5 Days</button>
                        </div>
                    </div>

                    <div class="trending-destinations-wrapper" data-aos="fade-up" data-aos-delay="100">
                        <button class="scroll-arrow scroll-left" id="scrollLeft"
                            style="opacity: 0.3; cursor: not-allowed;">
                            <i class="fas fa-chevron-left"></i>
                        </button>

                        <div class="trending-destinations-scroll" id="destinationsScroll">
                            <?php
                            $dest_query = "SELECT * FROM destinations WHERE is_active=1 ORDER BY display_order ASC";
                            $dest_result = $conn->query($dest_query);
                            if ($dest_result && $dest_result->num_rows > 0) {
                                $delay = 0;
                                while ($dest = $dest_result->fetch_assoc()) {
                                    $dest_id = $dest['id'];
                                    
                                    // Count related packages
                                    $pkg_cnt_query = "SELECT COUNT(*) as cnt FROM package_destination_map pdm JOIN packages p ON pdm.package_id = p.id WHERE pdm.destination_id = $dest_id AND p.status='Published'";
                                    $cnt_res = $conn->query($pkg_cnt_query);
                                    $pkg_cnt = $cnt_res->fetch_assoc()['cnt'] ?? 0;
                                    
                                    $dest_img = !empty($dest['image']) ? 'admin/' . ltrim($dest['image'], '/') : 'images/default-dest.jpg';
                                    $dest_name = htmlspecialchars($dest['name']);
                                    $dest_slug = urlencode($dest['slug']);
                                    
                                    // Find duration classes
                                    $duration_classes = [];
                                    $dur_stmt = $conn->query("SELECT duration_days FROM package_destination_map pdm JOIN packages p ON pdm.package_id = p.id WHERE pdm.destination_id = $dest_id AND p.status='Published'");
                                    if ($dur_stmt) {
                                        while($row = $dur_stmt->fetch_assoc()) {
                                            $d = (int)$row['duration_days'];
                                            if ($d < 5) $duration_classes[] = 'duration-lt5';
                                            elseif ($d >= 5 && $d < 10) $duration_classes[] = 'duration-5-8';
                                            else $duration_classes[] = 'duration-10plus';
                                        }
                                    }
                                    $class_str = implode(' ', array_unique($duration_classes));
                                    
                                    // Simulated stats for aesthetic purposes 
                                    $departures = rand(10, 30);
                                    $guests = rand(50, 200);
                                    ?>
                                    <a href="b5.php?slug=<?= $dest_slug ?>"
                                        class="trending-destination-card all <?= $class_str ?>" data-aos="zoom-in"
                                        data-aos-delay="<?= $delay ?>">
                                        <img src="<?= htmlspecialchars($dest_img) ?>" alt="<?= $dest_name ?>">
                                        <div class="destination-overlay"></div>
                                        <div class="destination-card-content">
                                            <h3 class="destination-name"><?= $dest_name ?></h3>
                                            <div class="destination-stats">
                                                <div class="stat-item">
                                                    <span class="stat-value"><?= $pkg_cnt ?> Packages</span>
                                                    <span class="stat-separator">|</span>
                                                    <span class="stat-value"><?= $departures ?> Departures</span>
                                                </div>
                                                <div class="stat-item">
                                                    <span class="stat-value"><?= $guests ?> + Guests Travelled</span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    <?php
                                    $delay += 100;
                                    if ($delay > 400) $delay = 0;
                                }
                            }
                            ?>
                        </div>

                        <button class="scroll-arrow scroll-right" id="scrollRight"
                            style="opacity: 0.3; cursor: not-allowed;">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </section>
        </div>
        <?php elseif ($sk === 'trending_packages'): ?>
        <section class="packages-section py-5" style="background-color: <?= $sBg ?? '#fcfcfc' ?>">
            <div class="container">
                <div class="section-header text-center mb-5" data-aos="fade-up">
                    <h2 class="section-title mt-0"><?= htmlspecialchars($secHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php if ($secSubheading !== ''): ?>
                        <p class="section-subtitle"><?= htmlspecialchars($secSubheading, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>

                <div class="row packages-row-gap">
                <?php
                // Fetch trending + published packages from admin panel
                $pkg_result = $conn->query("
                    SELECT p.*,
                        (SELECT GROUP_CONCAT(d.name SEPARATOR ', ') 
                         FROM destinations d 
                         JOIN package_destination_map pdm ON d.id = pdm.destination_id 
                         WHERE pdm.package_id = p.id) as dest_names
                    FROM packages p
                    WHERE p.status = 'Published' AND p.is_trending = 1
                    ORDER BY p.id DESC
                    LIMIT 8
                ");

                // Avatar letters pool for fake social proof
                $avatars = ['A','B','C','D','E','F','G','H','M','N','P','R','S','V'];
                $fake_names = [
                    'Arul booked from Chennai', 'Neha booked from Visakhapatnam',
                    'Rohan viewed from Indore', 'Sachin viewed from New Delhi',
                    'Vikram viewed from Nagpur', 'Divya booked from Kochi',
                    'Meera viewed from Pune', 'Priya booked from Hyderabad'
                ];
                $fake_times = ['2d ago','12hr ago','17hr ago','8hr ago','4d ago','1d ago','3hr ago','6hr ago'];
                $fake_booked = [true, true, false, false, false, true, false, true];

                if ($pkg_result && $pkg_result->num_rows > 0):
                    $pkg_idx = 0;
                    while ($pkg = $pkg_result->fetch_assoc()):
                        $pkg_idx++;
                        $pkg_delay  = ($pkg_idx - 1) * 50;

                        // Image path: stored relative to admin/, prefix for root access
                        $pkg_img = !empty($pkg['featured_image']) ? 'admin/' . ltrim($pkg['featured_image'], '/') : '';

                        // Discount calculation
                        $has_discount = ($pkg['original_price'] > 0 && $pkg['sale_price'] < $pkg['original_price']);
                        $disc_pct = $has_discount ? round((($pkg['original_price'] - $pkg['sale_price']) / $pkg['original_price']) * 100) : 0;

                        // Duration
                        $duration_label = $pkg['duration_days'] . 'D / ' . $pkg['duration_nights'] . 'N';

                        // Destination display
                        $dest_display = !empty($pkg['dest_names']) ? htmlspecialchars($pkg['dest_names']) : '';

                        // Social proof placeholders
                        $av_idx   = ($pkg_idx - 1) % count($avatars);
                        $av_letter= $avatars[$av_idx];
                        $av_name  = $fake_names[$av_idx];
                        $av_time  = $fake_times[$av_idx];
                        $av_booked= $fake_booked[$av_idx];
                        $banner_class = $av_booked ? 'customer-activity-banner-booked' : 'customer-activity-banner';
                ?>
                    <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="<?= $pkg_delay ?>">
                        <div class="package-card">
                            <!-- Social Proof Banner -->
                            <div class="<?= $banner_class ?>">
                                <div class="customer-avatar"><?= $av_letter ?></div>
                                <div class="customer-info">
                                    <span class="customer-name"><?= htmlspecialchars($av_name) ?></span>
                                    <span class="customer-time">• <?= $av_time ?></span>
                                </div>
                            </div>

                            <!-- Package Image -->
                            <div class="package-image">
                                <?php if (!empty($pkg_img)): ?>
                                    <img src="<?= htmlspecialchars($pkg_img) ?>" alt="<?= htmlspecialchars($pkg['title']) ?>">
                                <?php else: ?>
                                    <div style="width:100%;height:220px;background:#f0f2f5;display:flex;align-items:center;justify-content:center;">
                                        <i class="fas fa-image fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>

                                <?php if ($has_discount): ?>
                                    <span class="discount-badge"><?= $disc_pct ?>% OFF</span>
                                <?php endif; ?>
                                <span class="trending-badge"><i class="fas fa-fire"></i> Trending</span>
                            </div>

                            <div class="package-content">
                                <?php if (!empty($dest_display)): ?>
                                <div class="package-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= $dest_display ?>
                                </div>
                                <?php endif; ?>

                                <h4 class="package-title">
                                    <a href="b3.php?id=<?= $pkg['id'] ?>">
                                        <?= htmlspecialchars($pkg['title']) ?>
                                    </a>
                                </h4>

                                <div class="package-meta">
                                    <span><i class="fas fa-clock"></i> <?= $duration_label ?></span>
                                </div>

                                <div class="package-footer">
                                    <div class="package-price">
                                        <?php if ($has_discount): ?>
                                            <span class="old-price">Rs. <?= number_format($pkg['original_price'], 0) ?></span>
                                        <?php endif; ?>
                                        <span class="new-price">Rs. <?= number_format($pkg['sale_price'], 0) ?></span>
                                    </div>
                                    <a href="b3.php?id=<?= $pkg['id'] ?>"
                                        class="btn btn-sm btn-primary">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
                <?php else: ?>
                    <!-- Empty state when no trending packages are published -->
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-suitcase fa-3x text-muted mb-3 d-block"></i>
                        <p class="text-muted">No trending packages available right now. Check back soon!</p>
                    </div>
                <?php endif; ?>
                </div>

                <div class="text-center mt-5" data-aos="fade-up">
                    <a href="b1.php" class="btn btn-primary btn-lg">
                        View All Packages <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </section>
        <?php elseif ($sk === 'features'): ?>
        <?php
            $homeFeatures = [];
            if ($conn) {
                $feat_result = $conn->query("SELECT * FROM features WHERE is_active = 1 ORDER BY display_order ASC");
                if ($feat_result && $feat_result->num_rows > 0) {
                    while ($feat = $feat_result->fetch_assoc()) {
                        $homeFeatures[] = $feat;
                    }
                }
            }
            $featuresBgStyle = htmlspecialchars($sBg ?? '#ffffff', ENT_QUOTES, 'UTF-8');
        ?>

        <!-- Primary: Why Plan Your Travel With Us? (admin Features) -->
        <div style="background-color: <?= $featuresBgStyle ?>">
            <section class="features-section py-5">
                <div class="container">
                    <div class="section-header text-center mb-5" data-aos="fade-up">
                        <h2 class="section-title mt-0"><?= htmlspecialchars($secHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                        <?php if ($secSubheading !== ''): ?>
                            <p class="section-subtitle"><?= htmlspecialchars($secSubheading, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="row g-4">
                        <?php if (!empty($homeFeatures)):
                            foreach ($homeFeatures as $feat_index => $feat):
                                $feat_delay = $feat_index * 50;
                        ?>
                        <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="<?= $feat_delay ?>">
                            <div class="feature-box">
                                <div class="feature-icon-circle">
                                    <?php if ($feat['icon_type'] === 'image' && !empty($feat['icon_image'])): ?>
                                        <img src="admin/<?= ltrim(htmlspecialchars($feat['icon_image']), '/') ?>"
                                             alt="<?= htmlspecialchars($feat['title']) ?>"
                                             style="width:100%;height:100%;object-fit:contain;">
                                    <?php else: ?>
                                        <i class="<?= htmlspecialchars($feat['icon_class'] ?: 'fas fa-star') ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <h4 class="feature-title"><?= htmlspecialchars($feat['title']) ?></h4>
                                <p class="feature-description"><?= htmlspecialchars($feat['description']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <!-- Fallback: hardcoded features if DB is empty -->
                        <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="0">
                            <div class="feature-box">
                                <div class="feature-icon-circle"><i class="fas fa-certificate"></i></div>
                                <h4 class="feature-title">IATA Accredited</h4>
                                <p class="feature-description">We are a leading IATA accredited travel consultancy with 20+ years of travel management expertise</p>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="50">
                            <div class="feature-box">
                                <div class="feature-icon-circle"><i class="fas fa-users-cog"></i></div>
                                <h4 class="feature-title">Experienced Team</h4>
                                <p class="feature-description">The team comprises 30 experienced & passionate travel professionals</p>
                            </div>
                        </div>
                        <!-- <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                            <div class="feature-box">
                                <div class="feature-icon-circle"><i class="fas fa-passport"></i></div>
                                <h4 class="feature-title">One Stop Consultancy</h4>
                                <p class="feature-description">We offer the entire gamut of travel services including visa processing, passport consultancy and lost baggage services</p>
                            </div>
                        </div> -->
                        <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="150">
                            <div class="feature-box">
                                <div class="feature-icon-circle"><i class="fas fa-umbrella-beach"></i></div>
                                <h4 class="feature-title">Customised Holidays</h4>
                                <p class="feature-description">Our speciality is customised holidays with the motto of "crafting memorable experiences" for our clients</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>

        <?php elseif ($sk === 'secondary_features'): ?>
        <?php
            $homeSecondaryFeatures = [];
            if ($conn) {
                $hasItems = @$conn->query("SHOW TABLES LIKE 'homepage_secondary_features'");
                if ($hasItems && $hasItems->num_rows > 0) {
                    $secQ = $conn->query("SELECT * FROM homepage_secondary_features WHERE is_active = 1 ORDER BY display_order ASC");
                    if ($secQ && $secQ->num_rows > 0) {
                        while ($sf = $secQ->fetch_assoc()) {
                            $homeSecondaryFeatures[] = $sf;
                        }
                    }
                }
            }
            if (empty($homeSecondaryFeatures)) {
                $homeSecondaryFeatures = [
                    ['title' => 'Transparent pricing', 'description' => 'No hidden fees—see what you pay upfront and what is included in every package.', 'icon_type' => 'fontawesome', 'icon_class' => 'fas fa-receipt', 'icon_image' => ''],
                    ['title' => 'Flexible itineraries', 'description' => 'Adjust dates, pacing, and hotels so the trip fits your schedule and comfort.', 'icon_type' => 'fontawesome', 'icon_class' => 'fas fa-route', 'icon_image' => ''],
                    ['title' => 'Dedicated trip support', 'description' => 'Reach our team before and during travel for changes, questions, or emergencies.', 'icon_type' => 'fontawesome', 'icon_class' => 'fas fa-headset', 'icon_image' => ''],
                    ['title' => 'Trusted partners worldwide', 'description' => 'Hotels, airlines, and ground teams vetted for quality, safety, and service.', 'icon_type' => 'fontawesome', 'icon_class' => 'fas fa-handshake', 'icon_image' => ''],
                ];
            }
            $secondaryStripBg = htmlspecialchars($sBg ?? '#f8fafc', ENT_QUOTES, 'UTF-8');
        ?>
        <div style="background-color: <?= $secondaryStripBg ?>">
            <section class="features-section py-5">
                <div class="container">
                    <div class="section-header text-center mb-5" data-aos="fade-up">
                        <h2 class="section-title mt-0"><?= htmlspecialchars($secHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                        <?php if ($secSubheading !== ''): ?>
                            <p class="section-subtitle"><?= htmlspecialchars($secSubheading, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="row g-4">
                        <?php foreach ($homeSecondaryFeatures as $si => $feat):
                            $sdelay = $si * 50;
                            $isImg = (($feat['icon_type'] ?? 'fontawesome') === 'image') && !empty($feat['icon_image']);
                        ?>
                        <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="<?= $sdelay ?>">
                            <div class="feature-box">
                                <div class="feature-icon-circle">
                                    <?php if ($isImg): ?>
                                        <img src="admin/<?= ltrim(htmlspecialchars($feat['icon_image']), '/') ?>"
                                             alt="<?= htmlspecialchars($feat['title']) ?>"
                                             style="width:100%;height:100%;object-fit:contain;">
                                    <?php else: ?>
                                        <i class="<?= htmlspecialchars($feat['icon_class'] ?: 'fas fa-star') ?>"></i>
                                    <?php endif; ?>
                                </div>
                                <h4 class="feature-title"><?= htmlspecialchars($feat['title']) ?></h4>
                                <p class="feature-description"><?= htmlspecialchars($feat['description']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>

        <?php elseif ($sk === 'budget_filter'): ?>
        <div style="background-color: <?= $sBg ?? '#ffffff' ?>"> <!-- Budget Filter Section - Premium Design -->
            <section class="budget-filter-section-premium">
                <!-- Background Decorative Elements -->
                <div class="budget-bg-pattern"></div>

                <div class="container">
                    <!-- Section Header -->
                    <div class="budget-section-header" data-aos="fade-up">
                        <?php
                        $budgetTitleHtml = '';
                        $bw = preg_split('/\s+/u', $secHeading, -1, PREG_SPLIT_NO_EMPTY);
                        if (count($bw) > 1 && strcasecmp((string)end($bw), 'Budget') === 0) {
                            $lastW = array_pop($bw);
                            $budgetTitleHtml = htmlspecialchars(implode(' ', $bw), ENT_QUOTES, 'UTF-8')
                                . ' <span class="budget-highlight">' . htmlspecialchars((string)$lastW, ENT_QUOTES, 'UTF-8') . '</span>';
                        } else {
                            $budgetTitleHtml = htmlspecialchars($secHeading, ENT_QUOTES, 'UTF-8');
                        }
                        ?>
                        <h2 class="budget-main-title"><?= $budgetTitleHtml ?></h2>
                        <?php if ($secSubheading !== ''): ?>
                            <p class="budget-subtitle"><?= htmlspecialchars($secSubheading, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="budget-premium-wrapper">
                        <!-- Left Side: Animated Illustration -->
                        <?php
                        $budgetFilterImage = 'images/Plan_by_Budget.png';
                        $bfImageQuery = $conn ? $conn->query("SELECT section_image FROM homepage_sections WHERE section_key='budget_filter'") : null;
                        if ($bfImageQuery && $bfImageQuery->num_rows > 0) {
                            $bfImageData = $bfImageQuery->fetch_assoc();
                            if (!empty($bfImageData['section_image'])) {
                                $budgetFilterImage = 'admin/' . $bfImageData['section_image'];
                            }
                        }
                        ?>
                        <div class="budget-illustration-container" data-aos="fade-right" data-aos-delay="100">
                            <div class="illustration-backdrop"></div>
                            <img src="<?= htmlspecialchars($budgetFilterImage) ?>" alt="Plan by Budget" class="budget-hero-image">
                            <div class="illustration-glow"></div>
                        </div>

                        <!-- Right Side: Premium Budget Cards -->
                        <div class="budget-cards-container" data-aos="fade-left" data-aos-delay="200">
                            <div class="budget-cards-grid">
                                <?php
                                $budgetSql = "SELECT * FROM budget_cards WHERE is_active=1 ORDER BY display_order ASC";
                                $budgetResult = $conn ? $conn->query($budgetSql) : null;
                                
                                if ($budgetResult && $budgetResult->num_rows > 0) {
                                    $delay = 150;
                                    while ($bc = $budgetResult->fetch_assoc()) {
                                        // Auto-generate link based on numeric amount
                                        $numeric_amount = preg_replace('/[^0-9]/', '', $bc['amount']);
                                        $auto_link = "b1.php?budget=" . urlencode($numeric_amount);
                                        ?>
                                        <a href="<?= $auto_link ?>"
                                            class="budget-premium-card <?= htmlspecialchars($bc['color_class']) ?>" data-aos="zoom-in" data-aos-delay="<?= $delay ?>">
                                            <div class="card-shine"></div>
                                            <div class="card-icon-wrapper">
                                                <?php if(isset($bc['icon_type']) && $bc['icon_type'] == 'image' && !empty($bc['icon_image'])): ?>
                                                    <img src="admin/<?= htmlspecialchars($bc['icon_image']) ?>" alt="icon" style="width: 32px; height: 32px; object-fit: contain;">
                                                <?php else: ?>
                                                    <i class="fas <?= htmlspecialchars($bc['icon']) ?>"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-label"><?= htmlspecialchars($bc['label']) ?></div>
                                            <div class="card-amount"><?= htmlspecialchars($bc['amount']) ?></div>
                                            <div class="card-description"><?= htmlspecialchars($bc['description']) ?></div>
                                            <div class="card-arrow"><i class="fas fa-arrow-right"></i></div>
                                        </a>
                                        <?php
                                        $delay += 50;
                                    }
                                } else {
                                    // Fallback to original static cards if no database results or table not created
                                    ?>
                                    <!-- Budget Card 1 -->
                                    <a href="b1.php?budget=50000"
                                        class="budget-premium-card card-blue" data-aos="zoom-in" data-aos-delay="150">
                                        <div class="card-shine"></div>
                                        <div class="card-icon-wrapper">
                                            <i class="fas fa-plane"></i>
                                        </div>
                                        <div class="card-label">BELOW</div>
                                        <div class="card-amount">Rs. 50,000</div>
                                        <div class="card-description">Budget Friendly Tours</div>
                                        <div class="card-arrow"><i class="fas fa-arrow-right"></i></div>
                                    </a>

                                    <!-- Budget Card 2 -->
                                    <a href="b1.php?budget=100000"
                                        class="budget-premium-card card-green" data-aos="zoom-in" data-aos-delay="200">
                                        <div class="card-shine"></div>
                                        <div class="card-icon-wrapper">
                                            <i class="fas fa-umbrella-beach"></i>
                                        </div>
                                        <div class="card-label">BELOW</div>
                                        <div class="card-amount">Rs. 1,00,000</div>
                                        <div class="card-description">Value Packages</div>
                                        <div class="card-arrow"><i class="fas fa-arrow-right"></i></div>
                                    </a>

                                    <!-- Budget Card 3 -->
                                    <a href="b1.php?budget=200000"
                                        class="budget-premium-card card-yellow" data-aos="zoom-in" data-aos-delay="250">
                                        <div class="card-shine"></div>
                                        <div class="card-icon-wrapper">
                                            <i class="fas fa-ship"></i>
                                        </div>
                                        <div class="card-label">BELOW</div>
                                        <div class="card-amount">Rs. 2,00,000</div>
                                        <div class="card-description">Premium Escapes</div>
                                        <div class="card-arrow"><i class="fas fa-arrow-right"></i></div>
                                    </a>

                                    <!-- Budget Card 4 -->
                                    <a href="b1.php?budget=300000"
                                        class="budget-premium-card card-pink" data-aos="zoom-in" data-aos-delay="300">
                                        <div class="card-shine"></div>
                                        <div class="card-icon-wrapper">
                                            <i class="fas fa-crown"></i>
                                        </div>
                                        <div class="card-label">BELOW</div>
                                        <div class="card-amount">Rs. 3,00,000</div>
                                        <div class="card-description">Ultra Luxury</div>
                                        <div class="card-arrow"><i class="fas fa-arrow-right"></i></div>
                                    </a>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <?php elseif ($sk === 'live_counter'): ?>
        <div style="background-color: <?= $sBg ?? '#ffffff' ?>"> <!-- Live Counter Section -->
            <section class="live-counter-section">
                <div class="container">
                    <h2 class="counter-heading" data-aos="fade-up"><?= htmlspecialchars($secHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php if ($secSubheading !== ''): ?>
                        <p class="counter-subheading" data-aos="fade-up" data-aos-delay="100"><?= htmlspecialchars($secSubheading, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>

                    <?php
                        // Live homepage counters (max 4) managed from the admin panel.
                        $liveCounters = [];
                        $fallbackLabels = ["Trips Sold", "No. of Traveler's", "Happy Customers", "Countries Covered"];

                        $sql = "SELECT title, counter_value FROM live_counters WHERE is_active=1 ORDER BY display_order ASC LIMIT 4";
                        $result = $conn ? $conn->query($sql) : null;

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $rawTarget = (string)($row['counter_value'] ?? '0');
                                // Keep only digits so the JS counter works even if admin enters formatted values like "10,000".
                                $target = (int)preg_replace('/[^0-9]/', '', $rawTarget);
                                $liveCounters[] = [
                                    'title' => (string)($row['title'] ?? ''),
                                    'target' => $target,
                                ];
                            }
                        }
                    ?>

                    <div class="counter-grid">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                            <?php
                                $title = $fallbackLabels[$i];
                                $target = 0;
                                if (!empty($liveCounters[$i])) {
                                    if (!empty(trim((string)$liveCounters[$i]['title']))) {
                                        $title = $liveCounters[$i]['title'];
                                    }
                                    $target = (int)$liveCounters[$i]['target'];
                                }
                            ?>
                            <div class="counter-item" data-aos="zoom-in" data-aos-delay="<?= $i * 100 ?>">
                                <div class="counter-number" data-target="<?= $target ?>"><?= number_format($target) ?></div>
                                <div class="counter-label"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </section>
        </div>
        <?php elseif ($sk === 'instagram_reels'): ?>
        <div style="background-color: <?= $sBg ?? '#fdf6f0' ?>"> <!-- Instagram Reels Section -->
            <section class="instagram-reels-section py-5">
                <div class="container">
                    <?php
                        $instagramReels = [];
                        $reelsSql = "SELECT reel_url, caption, thumbnail FROM instagram_reels WHERE is_active=1 ORDER BY display_order ASC";
                        $reelsResult = $conn ? $conn->query($reelsSql) : null;

                        if ($reelsResult && $reelsResult->num_rows > 0) {
                            while ($row = $reelsResult->fetch_assoc()) {
                                $reelUrl = trim((string)($row['reel_url'] ?? ''));
                                $caption = trim((string)($row['caption'] ?? ''));
                                $thumbnail = str_replace('\\', '/', trim((string)($row['thumbnail'] ?? '')));
                                $embedUrl = '';

                                if ($reelUrl !== '') {
                                    $normalizedUrl = preg_replace('#/+$#', '', $reelUrl);

                                    if (strpos($normalizedUrl, '/embed') !== false) {
                                        $embedUrl = $normalizedUrl;
                                    } else {
                                        $urlParts = parse_url($normalizedUrl);
                                        $path = isset($urlParts['path']) ? trim($urlParts['path'], '/') : '';
                                        $segments = $path !== '' ? explode('/', $path) : [];
                                        $mediaType = $segments[0] ?? '';
                                        $mediaCode = $segments[1] ?? '';

                                        if (in_array($mediaType, ['reel', 'p', 'tv'], true) && $mediaCode !== '') {
                                            $embedUrl = 'https://www.instagram.com/' . $mediaType . '/' . $mediaCode . '/embed/';
                                        }

                                        // Fallback: extract code using regex (handles extra path/query edge-cases)
                                        if ($embedUrl === '' && preg_match('#instagram\.com/(reel|p|tv)/([^/?#]+)/?#i', $normalizedUrl, $m)) {
                                            $mediaType = $m[1] ?? '';
                                            $mediaCode = $m[2] ?? '';
                                            if ($mediaType !== '' && $mediaCode !== '') {
                                                $embedUrl = 'https://www.instagram.com/' . $mediaType . '/' . $mediaCode . '/embed/';
                                            }
                                        }
                                    }
                                }

                                if ($embedUrl !== '') {
                                    // Fix thumbnail path if the file was uploaded from a different working dir.
                                    $thumbWeb = $thumbnail;
                                    if ($thumbWeb !== '' && !preg_match('#^https?://#i', $thumbWeb)) {
                                        $thumbRel = ltrim($thumbWeb, '/');
                                        $absDefault = __DIR__ . '/' . $thumbRel;
                                        $absAdmin = __DIR__ . '/admin/' . $thumbRel;
                                        if (!file_exists($absDefault) && file_exists($absAdmin)) {
                                            $thumbWeb = 'admin/' . $thumbRel;
                                        }
                                    }

                                    $instagramReels[] = [
                                        'embed_url' => $embedUrl,
                                        'caption' => $caption !== '' ? $caption : 'Instagram Reel',
                                        'thumbnail' => $thumbWeb,
                                    ];
                                }
                            }
                        }

                        if (empty($instagramReels)) {
                            $instagramReels = [
                                [
                                    'embed_url' => 'https://www.instagram.com/p/DVGCfx7iN6u/embed/',
                                    'caption' => 'Phu Quoc Arrival',
                                    'thumbnail' => 'images/reel_69c75f1a1c0c9_1774673690.png',
                                ],
                                [
                                    'embed_url' => 'https://www.instagram.com/p/DWtaiEmCOM7/embed/',
                                    'caption' => 'Fun Travel',
                                    'thumbnail' => '',
                                ],
                            ];
                        }
                    ?>
                    <div class="text-center mb-4" data-aos="fade-up">
                        <h2 class="section-title mt-0"><?= htmlspecialchars($secHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                        <?php if ($secSubheading !== ''): ?>
                            <p class="section-subtitle mt-3 mb-0"><?= htmlspecialchars($secSubheading, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="ig-scroll-wrapper" data-aos="fade-up" data-aos-delay="100">
                        <button class="scroll-arrow scroll-left" id="igScrollLeft" style="opacity: 0.3;">
                            <i class="fas fa-chevron-left"></i>
                        </button>

                        <div class="ig-scroll-track" id="igScroll">
                            <?php foreach ($instagramReels as $reel): ?>
                                <div class="ig-card"
                                    data-embed="<?= htmlspecialchars($reel['embed_url'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-caption="<?= htmlspecialchars($reel['caption'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if (!empty($reel['thumbnail'])): ?>
                                        <img src="<?= htmlspecialchars($reel['thumbnail'], ENT_QUOTES, 'UTF-8') ?>"
                                            alt="<?= htmlspecialchars($reel['caption'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?php else: ?>
                                        <div class="ig-card-placeholder">
                                            <i class="fab fa-instagram"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ig-card-overlay">
                                        <div class="ig-play-btn"><i class="fas fa-play"></i></div>
                                    </div>
                                    <div class="ig-card-caption"><?= htmlspecialchars($reel['caption'], ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button class="scroll-arrow scroll-right" id="igScrollRight" style="opacity: 0.3;">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Reel Play Modal -->
            <div class="modal fade" id="igReelModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
                    <div class="modal-content bg-black border-0">
                        <div class="modal-header border-0 pb-0">
                            <span class="text-white fw-semibold" id="igModalCaption"></span>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-0">
                            <div id="igEmbedContainer" style="min-height:560px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <style>
                /* Instagram Reels Section */
                .instagram-reels-section {
                    background: transparent;
                }

                /* Scroll layout */
                .ig-scroll-wrapper {
                    position: relative;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                .ig-scroll-track {
                    display: flex;
                    gap: 16px;
                    overflow-x: auto;
                    scroll-behavior: smooth;
                    scrollbar-width: none;
                    -ms-overflow-style: none;
                    padding: 10px 4px 16px;
                    flex: 1;
                }

                .ig-scroll-track::-webkit-scrollbar {
                    display: none;
                }

                /* Card */
                .ig-card {
                    flex: 0 0 200px;
                    height: 356px;
                    border-radius: 10px;
                    overflow: hidden;
                    position: relative;
                    cursor: pointer;
                    background: #1a1a1a;
                    transition: transform 0.3s, box-shadow 0.3s;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
                }

                .ig-card:hover {
                    transform: translateY(-6px) scale(1.02);
                    box-shadow: 0 12px 40px rgba(225, 48, 108, 0.35);
                }

                .ig-card img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    display: block;
                }

                .ig-card-placeholder {
                    width: 100%;
                    height: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: linear-gradient(135deg, #833ab4, #fd1d1d, #fcb045);
                    font-size: 48px;
                    color: #fff;
                }

                /* Overlay & play button */
                .ig-card-overlay {
                    position: absolute;
                    inset: 0;
                    background: rgba(0, 0, 0, 0.25);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0;
                    transition: opacity 0.3s;
                    border-radius: inherit;
                }

                .ig-card:hover .ig-card-overlay {
                    opacity: 1;
                }

                .ig-play-btn {
                    width: 56px;
                    height: 56px;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.9);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 20px;
                    color: #e1306c;
                    padding-left: 4px;
                }

                /* Caption */
                .ig-card-caption {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    padding: 24px 12px 14px;
                    background: linear-gradient(transparent, rgba(0, 0, 0, 0.75));
                    color: #fff;
                    font-size: 12px;
                    font-weight: 600;
                    text-align: center;
                    line-height: 1.3;
                }

                /* Arrow buttons inherit .scroll-arrow from main CSS */
                @media (max-width: 767px) {
                    .ig-card {
                        flex: 0 0 160px;
                        height: 285px;
                        border-radius: 40px 40px 14px 14px;
                    }
                }
            </style>

            <script>
                (function () {
                    // Scroll arrows
                    var track = document.getElementById('igScroll');
                    var btnLeft = document.getElementById('igScrollLeft');
                    var btnRight = document.getElementById('igScrollRight');
                    if (track && btnLeft && btnRight) {
                        var amt = track.offsetWidth * 0.75;
                        btnLeft.addEventListener('click', function () { track.scrollBy({ left: -amt, behavior: 'smooth' }); });
                        btnRight.addEventListener('click', function () { track.scrollBy({ left: amt, behavior: 'smooth' }); });
                        function updateArrows() {
                            btnLeft.style.opacity = track.scrollLeft > 0 ? '1' : '0.3';
                            btnRight.style.opacity = track.scrollLeft < (track.scrollWidth - track.clientWidth - 5) ? '1' : '0.3';
                        }
                        track.addEventListener('scroll', updateArrows);
                        updateArrows();
                    }

                    // Card hover and click interactions
                    document.querySelectorAll('.ig-card').forEach(function (card) {
                        let hoverTimer;

                        // Hover → inject iframe to play inline
                        card.addEventListener('mouseenter', function () {
                            var embedUrl = this.dataset.embed;
                            if (!embedUrl) return;
                            
                            // Try to append autoplay parameter (Note: Instagram may still block this based on their cross-origin policies)
                            var autoPlayUrl = embedUrl + (embedUrl.includes('?') ? '&' : '?') + 'autoplay=1&mute=1';
                            
                            // Slight delay to prevent accidental hovers from loading heavy iframes
                            hoverTimer = setTimeout(() => {
                                if (!this.querySelector('iframe')) {
                                    // Hide overlay and thumbnail
                                    let overlay = this.querySelector('.ig-card-overlay');
                                    let img = this.querySelector('img');
                                    let placeholder = this.querySelector('.ig-card-placeholder');
                                    
                                    if (overlay) overlay.style.display = 'none';
                                    if (img) img.style.display = 'none';
                                    if (placeholder) placeholder.style.display = 'none';
                                    
                                    // Inject iframe covering the card
                                    this.insertAdjacentHTML('beforeend', 
                                        '<iframe src="' + autoPlayUrl + '" width="100%" height="100%" frameborder="0" scrolling="no" allowtransparency="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture" allowfullscreen="allowfullscreen" style="position:absolute; top:0; left:0; width:100%; height:100%; z-index:5; border-radius:inherit; border:none; background:#000;"></iframe>'
                                    );
                                }
                            }, 300); // 300ms delay
                        });

                        card.addEventListener('mouseleave', function () {
                            clearTimeout(hoverTimer);
                            let iframe = this.querySelector('iframe');
                            if (iframe) {
                                iframe.remove();
                                // Restore overlay and thumbnail
                                let overlay = this.querySelector('.ig-card-overlay');
                                let img = this.querySelector('img');
                                let placeholder = this.querySelector('.ig-card-placeholder');
                                
                                if (overlay) overlay.style.display = '';
                                if (img) img.style.display = '';
                                if (placeholder) placeholder.style.display = '';
                            }
                        });

                        // Click → open modal with embed
                        card.addEventListener('click', function () {
                            var embedUrl = this.dataset.embed;
                            var caption = this.dataset.caption;
                            if (!embedUrl) return;

                            document.getElementById('igModalCaption').textContent = caption;
                            document.getElementById('igEmbedContainer').innerHTML =
                                '<iframe ' +
                                'src="' + embedUrl + '" ' +
                                'width="100%" height="560" ' +
                                'frameborder="0" scrolling="no" ' +
                                'allowtransparency="true" ' +
                                'allow="autoplay; clipboard-write; encrypted-media; picture-in-picture" ' +
                                'allowfullscreen="allowfullscreen"></iframe>';

                            var modal = new bootstrap.Modal(document.getElementById('igReelModal'));
                            modal.show();
                        });
                    });

                    // Clear iframe on modal close to stop video
                    document.getElementById('igReelModal').addEventListener('hidden.bs.modal', function () {
                        document.getElementById('igEmbedContainer').innerHTML = '';
                    });
                })();
            </script>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>

        <!-- Testimonials Section (always visible, not in section order) -->
        <section class="testimonial-ui-section">
            <style>
                .testimonial-ui-section {
                    background: #f3f4f8;
                    padding: 70px 0 84px;
                }
                .testimonial-ui-kicker {
                    margin: 0 0 14px;
                    text-align: center;
                    color: #e28aa1;
                    font-size: 18px;
                    font-weight: 500;
                    letter-spacing: 0.2px;
                }
                .testimonial-ui-title {
                    margin: 0;
                    text-align: center;
                    color: #3f4452;
                    font-weight: 700;
                    font-size: 48px;
                    line-height: 1.1;
                }
                .testimonial-ui-grid {
                    margin-top: 86px;
                    row-gap: 40px;
                }
                .testimonial-ui-card {
                    position: relative;
                    background: #ffffff;
                    border-radius: 14px;
                    box-shadow: 0 10px 30px rgba(48, 52, 65, 0.08);
                    min-height: 388px;
                    padding: 98px 26px 26px;
                    text-align: center;
                }
                .testimonial-ui-avatar {
                    position: absolute;
                    top: -36px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 112px;
                    height: 112px;
                    border-radius: 50%;
                    overflow: hidden;
                    border: 8px solid #d8dde5;
                    background: #d8dde5;
                }
                .testimonial-ui-avatar img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                .testimonial-ui-name {
                    margin: 0;
                    font-size: 31px;
                    line-height: 1.15;
                    font-weight: 700;
                    color: #363b47;
                    letter-spacing: -0.2px;
                }
                .testimonial-ui-company {
                    margin: 8px 0 0;
                    font-size: 15px;
                    color: #9ea4b0;
                    font-weight: 500;
                }
                .testimonial-ui-text {
                    margin: 24px 0 30px;
                    color: #7e8594;
                    font-size: 15px;
                    line-height: 1.92;
                    min-height: 115px;
                }
                .testimonial-ui-stars {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 3px;
                }
                .testimonial-ui-stars i {
                    font-size: 14px;
                    color: #ff5c86;
                }
                .testimonial-ui-stars i.off {
                    color: #d5dae3;
                }
                @media (max-width: 1199.98px) {
                    .testimonial-ui-title { font-size: 40px; }
                    .testimonial-ui-name { font-size: 27px; }
                }
                @media (max-width: 991.98px) {
                    .testimonial-ui-title { font-size: 34px; }
                }
                @media (max-width: 767.98px) {
                    .testimonial-ui-section { padding: 56px 0 70px; }
                    .testimonial-ui-kicker { font-size: 16px; }
                    .testimonial-ui-title { font-size: 28px; }
                    .testimonial-ui-grid { margin-top: 62px; }
                    .testimonial-ui-card { min-height: 360px; }
                    .testimonial-ui-name { font-size: 24px; }
                    .testimonial-ui-text { font-size: 14px; line-height: 1.8; }
                }
            </style>
            <div class="container">
                <p class="testimonial-ui-kicker">Testimonial</p>
                <h2 class="testimonial-ui-title">We Care About Our Customers<br>Experience Too</h2>

                <div class="row testimonial-ui-grid">
                    <?php if (!empty($testimonials)): ?>
                        <?php foreach ($testimonials as $ts): ?>
                            <?php
                            $rating = (int)($ts['rating'] ?? 5);
                            if ($rating < 1) $rating = 1;
                            if ($rating > 5) $rating = 5;
                            $imgPath = !empty($ts['client_image']) ? $ts['client_image'] : 'images/default-dest.jpg';
                            $name = htmlspecialchars($ts['client_name'] ?? 'Client', ENT_QUOTES, 'UTF-8');
                            $company = trim((string)($ts['client_location'] ?? ''));
                            if ($company === '') $company = 'Google Inc.';
                            ?>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <article class="testimonial-ui-card">
                                    <div class="testimonial-ui-avatar">
                                        <img src="<?= htmlspecialchars($imgPath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $name ?>">
                                    </div>
                                    <h6 class="testimonial-ui-name"><?= $name ?></h6>
                                    <p class="testimonial-ui-company"><?= htmlspecialchars($company, ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="testimonial-ui-text"><?= htmlspecialchars($ts['testimonial_text'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                    <div class="testimonial-ui-stars" aria-label="<?= $rating ?> star rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?= ($i <= $rating) ? '' : 'off' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <article class="testimonial-ui-card">
                                <div class="testimonial-ui-avatar">
                                    <img src="images/default-dest.jpg" alt="Client">
                                </div>
                                <h6 class="testimonial-ui-name">Happy Client</h6>
                                <p class="testimonial-ui-company">Google Inc.</p>
                                <p class="testimonial-ui-text">Your feedback will appear here soon.</p>
                                <div class="testimonial-ui-stars" aria-label="5 star rating">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                </div>
                            </article>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <script>
            // Counter Animation - Using vanilla JavaScript, no jQuery dependency
            (function () {
                let counterAnimated = false;

                function animateCounter(element) {
                    const targetStr = element.getAttribute('data-target') || '';
                    const target = parseInt(String(targetStr).replace(/[^0-9]/g, ''), 10);
                    console.log('Animating counter with target:', target);

                    if (!target || isNaN(target)) {
                        console.log('Invalid target value');
                        return;
                    }

                    const duration = 2000;
                    const increment = target / (duration / 16);
                    let current = 0;

                    const counterItem = element.closest('.counter-item');
                    if (counterItem) {
                        counterItem.classList.add('counting');
                    }

                    const timer = setInterval(function () {
                        current += increment;
                        if (current >= target) {
                            current = target;
                            clearInterval(timer);
                            if (counterItem) {
                                counterItem.classList.remove('counting');
                            }
                        }
                        element.textContent = Math.floor(current).toLocaleString();
                    }, 16);
                }

                function startCounterAnimations() {
                    if (counterAnimated) return;

                    counterAnimated = true;
                    console.log('Starting counter animations...');

                    const counters = document.querySelectorAll('.counter-number');
                    console.log('Found counters:', counters.length);

                    counters.forEach(function (counter, index) {
                        console.log('Counter ' + index + ' data-target:', counter.getAttribute('data-target'));
                        setTimeout(function () {
                            animateCounter(counter);
                        }, index * 100);
                    });
                }

                function checkAndStartCounters() {
                    if (counterAnimated) return;

                    const counterSection = document.querySelector('.live-counter-section');
                    if (counterSection) {
                        const rect = counterSection.getBoundingClientRect();
                        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;

                        console.log('Checking counters - Section top:', rect.top, 'Viewport height:', viewportHeight);

                        if (rect.top <= viewportHeight && rect.bottom >= 0) {
                            console.log('Counter section is in view - starting animations');
                            startCounterAnimations();
                        }
                    }
                }

                // Wait for DOM to be ready
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function () {
                        console.log('DOM Content Loaded');
                        setTimeout(checkAndStartCounters, 300);
                    });
                } else {
                    console.log('DOM already loaded');
                    setTimeout(checkAndStartCounters, 300);
                }

                // Also check on scroll
                window.addEventListener('scroll', checkAndStartCounters);

                // And use Intersection Observer if available
                if (typeof IntersectionObserver !== 'undefined') {
                    const observer = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting && !counterAnimated) {
                                console.log('Counter section is in view (Observer)');
                                startCounterAnimations();
                            }
                        });
                    }, { threshold: 0.1, rootMargin: '0px' });

                    const counterSection = document.querySelector('.live-counter-section');
                    if (counterSection) {
                        observer.observe(counterSection);
                    }
                }
            })();

            // Trending Destinations Filter and Scroll Functionality
            document.addEventListener('DOMContentLoaded', function () {
                console.log('DOM Loaded - Initializing trending filters...');

                const filterButtons = document.querySelectorAll('.duration-filter-btn');
                const destinationCards = document.querySelectorAll('.trending-destination-card');
                const scrollContainer = document.getElementById('destinationsScroll');
                const scrollLeft = document.getElementById('scrollLeft');
                const scrollRight = document.getElementById('scrollRight');

                console.log('Filter buttons found:', filterButtons.length);
                console.log('Destination cards found:', destinationCards.length);

                // Filter functionality
                filterButtons.forEach(button => {
                    button.addEventListener('click', function (e) {
                        e.preventDefault();
                        console.log('Filter clicked:', this.getAttribute('data-duration'));

                        // Update active state
                        filterButtons.forEach(btn => btn.classList.remove('active'));
                        this.classList.add('active');

                        const filter = this.getAttribute('data-duration');

                        // Show/hide cards based on filter
                        let visibleCount = 0;
                        destinationCards.forEach(card => {
                            const cardClasses = card.className;
                            console.log('Card classes:', cardClasses);

                            if (filter === 'all') {
                                card.classList.remove('hidden');
                                card.style.display = 'flex';
                                visibleCount++;
                            } else if (filter === '5-8') {
                                if (card.classList.contains('duration-5-8')) {
                                    card.classList.remove('hidden');
                                    card.style.display = 'flex';
                                    visibleCount++;
                                } else {
                                    card.classList.add('hidden');
                                    card.style.display = 'none';
                                }
                            } else if (filter === '10+') {
                                if (card.classList.contains('duration-10plus')) {
                                    card.classList.remove('hidden');
                                    card.style.display = 'flex';
                                    visibleCount++;
                                } else {
                                    card.classList.add('hidden');
                                    card.style.display = 'none';
                                }
                            } else if (filter === '<5') {
                                if (card.classList.contains('duration-lt5')) {
                                    card.classList.remove('hidden');
                                    card.style.display = 'flex';
                                    visibleCount++;
                                } else {
                                    card.classList.add('hidden');
                                    card.style.display = 'none';
                                }
                            }
                        });

                        console.log('Visible cards after filter:', visibleCount);

                        // Reset scroll position after filtering
                        if (scrollContainer) {
                            scrollContainer.scrollLeft = 0;
                        }
                    });
                });

                // Scroll functionality
                if (scrollLeft && scrollRight && scrollContainer) {
                    scrollLeft.addEventListener('click', function (e) {
                        e.preventDefault();
                        scrollContainer.scrollBy({
                            left: -245,
                            behavior: 'smooth'
                        });
                    });

                    scrollRight.addEventListener('click', function (e) {
                        e.preventDefault();
                        scrollContainer.scrollBy({
                            left: 245,
                            behavior: 'smooth'
                        });
                    });

                    // Update arrow visibility based on scroll position
                    function updateArrows() {
                        if (scrollContainer.scrollLeft === 0) {
                            scrollLeft.style.opacity = '0.3';
                            scrollLeft.style.cursor = 'not-allowed';
                        } else {
                            scrollLeft.style.opacity = '1';
                            scrollLeft.style.cursor = 'pointer';
                        }

                        if (scrollContainer.scrollLeft >= scrollContainer.scrollWidth - scrollContainer.clientWidth - 10) {
                            scrollRight.style.opacity = '0.3';
                            scrollRight.style.cursor = 'not-allowed';
                        } else {
                            scrollRight.style.opacity = '1';
                            scrollRight.style.cursor = 'pointer';
                        }
                    }

                    scrollContainer.addEventListener('scroll', updateArrows);
                    updateArrows(); // Initial check
                }

                console.log('Trending filters initialized successfully');
            });

            $(document).ready(function () {
                // Initialize testimonials carousel only if slider variant is used
                if ($('.testimonials-slider.owl-carousel').length) {
                    $('.testimonials-slider.owl-carousel').owlCarousel({
                        loop: true,
                        margin: 30,
                        nav: true,
                        dots: true,
                        autoplay: true,
                        autoplayTimeout: 6000,
                        autoplayHoverPause: true,
                        smartSpeed: 800,
                        animateOut: 'fadeOut',
                        animateIn: 'fadeIn',
                        navText: ['<i class="fas fa-chevron-left"></i>', '<i class="fas fa-chevron-right"></i>'],
                        responsive: {
                            0: {
                                items: 1,
                                nav: false
                            },
                            768: {
                                items: 2,
                                nav: true
                            },
                            1024: {
                                items: 3,
                                nav: true
                            }
                        }
                    });
                }

                // Search Autocomplete
                const searchInput = document.getElementById('slider-search-input');
                const suggestionsContainer = document.getElementById('search-suggestions');
                let searchTimeout;

                if (searchInput && suggestionsContainer) {
                    searchInput.addEventListener('input', function () {
                        clearTimeout(searchTimeout);
                        const query = this.value.trim();

                        if (query.length < 2) {
                            suggestionsContainer.classList.remove('active');
                            suggestionsContainer.innerHTML = '';
                            return;
                        }

                        searchTimeout = setTimeout(() => {
                            fetch(`api/search-autocomplete.php?q=${encodeURIComponent(query)}`)
                                .then(response => response.json())
                                .then(results => {
                                    if (results.length === 0) {
                                        suggestionsContainer.innerHTML = '<div class="no-suggestions">No results found. Try a different search term.</div>';
                                        suggestionsContainer.classList.add('active');
                                        return;
                                    }

                                    let html = '';
                                    results.forEach(item => {
                                        const iconClass = item.type;
                                        let icon = '';

                                        if (item.image) {
                                            icon = `<img src="${item.image}" alt="${item.name}">`;
                                        } else {
                                            const iconType = item.type === 'package' ? 'fa-box' :
                                                item.type === 'destination' ? 'fa-map-marker-alt' :
                                                    'fa-tags';
                                            icon = `<i class="fas ${iconType}"></i>`;
                                        }

                                        html += `
                                <a href="${item.url}" class="suggestion-item">
                                    <div class="suggestion-icon ${iconClass}">
                                        ${icon}
                                    </div>
                                    <div class="suggestion-content">
                                        <div class="suggestion-name">${item.name}</div>
                                        <div class="suggestion-meta">${item.meta}</div>
                                    </div>
                                    <i class="fas fa-arrow-right suggestion-arrow"></i>
                                </a>
                            `;
                                    });

                                    suggestionsContainer.innerHTML = html;
                                    suggestionsContainer.classList.add('active');
                                })
                                .catch(error => {
                                    console.error('Search error:', error);
                                });
                        }, 300);
                    });

                    // Close suggestions when clicking outside
                    document.addEventListener('click', function (e) {
                        if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                            suggestionsContainer.classList.remove('active');
                        }
                    });

                    // Show suggestions when input is focused and has value
                    searchInput.addEventListener('focus', function () {
                        if (this.value.trim().length >= 2 && suggestionsContainer.innerHTML) {
                            suggestionsContainer.classList.add('active');
                        }
                    });
                }
            });
        </script>

    </main>
    <!-- End Main Content -->

    <?php include('footer.php'); ?>
    <?php include('footerlinks.php'); ?>

</body>

</html>