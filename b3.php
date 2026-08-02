<?php
include('admin/connection.php');
$pkg_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$pkg_id) {
    header('Location: index.php');
    exit;
}
$pkg = null;
$r = $conn->query("SELECT * FROM packages WHERE id=$pkg_id AND status='Published'");
if ($r && $r->num_rows > 0) {
    $pkg = $r->fetch_assoc();
} else {
    header('Location: index.php');
    exit;
}
$conn->query("UPDATE packages SET views=views+1 WHERE id=$pkg_id");
$cats = [];
$cr = $conn->query("SELECT c.name,c.slug FROM categories c JOIN package_category_map pcm ON c.id=pcm.category_id WHERE pcm.package_id=$pkg_id");
if ($cr)
    while ($rr = $cr->fetch_assoc())
        $cats[] = $rr;
$dests = [];
$dr = $conn->query("SELECT d.* FROM destinations d JOIN package_destination_map pdm ON d.id=pdm.destination_id WHERE pdm.package_id=$pkg_id");
if ($dr)
    while ($rr = $dr->fetch_assoc())
        $dests[] = $rr;
$itins = [];
$ir = $conn->query("SELECT * FROM package_itineraries WHERE package_id=$pkg_id ORDER BY day_number ASC");
if ($ir)
    while ($rr = $ir->fetch_assoc())
        $itins[] = $rr;
$pkg_img = !empty($pkg['featured_image']) ? 'admin/' . ltrim($pkg['featured_image'], '/') : '';
$has_disc = ($pkg['original_price'] > 0 && $pkg['sale_price'] < $pkg['original_price']);
$disc_pct = $has_disc ? round((($pkg['original_price'] - $pkg['sale_price']) / $pkg['original_price']) * 100) : 0;
$dur = $pkg['duration_days'] . 'D / ' . $pkg['duration_nights'] . 'N';
$dest_str = implode(', ', array_column($dests, 'name'));
$page_title = !empty($pkg['meta_title']) ? $pkg['meta_title'] : $pkg['title'];
$page_desc = !empty($pkg['meta_description']) ? $pkg['meta_description'] : 'Book this amazing travel package with Multizone Travels.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= htmlspecialchars($page_title) ?> - Multizone Travels</title>



    <?php include('headerlinks.php'); ?>
</head>

<body>

    <?php include('header1.php'); ?>

    <!-- Multi-Step Enquiry Modal -->
    <?php include('enquiry_modal.php'); ?>

    <!-- Main Content Wrapper -->
    <main>



        <!-- Page Header -->
        <section class="page-header package-header">
            <div class="page-header-overlay"></div>
            <div class="page-header-bg"
                style="background-image: url('<?= htmlspecialchars($pkg_img) ?>');background-size:cover;background-position:center;">
            </div>
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <nav aria-label="breadcrumb" data-aos="fade-up">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                                <li class="breadcrumb-item"><a href="b1.php">Packages</a></li>
                                <?php foreach ($cats as $cat): ?>
                                    <li class="breadcrumb-item"><a
                                            href="b1.php?category=<?= urlencode($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></a>
                                    </li>
                                <?php endforeach; ?>
                                <li class="breadcrumb-item active"><?= htmlspecialchars($pkg['title']) ?></li>
                            </ol>
                        </nav>
                        <h1 class="page-title" data-aos="fade-up" data-aos-delay="100">
                            <?= htmlspecialchars($pkg['title']) ?>
                        </h1>
                        <div class="package-header-meta" data-aos="fade-up" data-aos-delay="200">
                            <?php if (!empty($dest_str)): ?><span><i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($dest_str) ?></span><?php endif; ?>
                            <span><i class="fas fa-clock"></i> <?= $pkg['duration_days'] ?> Days /
                                <?= $pkg['duration_nights'] ?> Nights</span>
                            <?php if ($pkg['group_size_max'] > 0): ?><span><i class="fas fa-users"></i> Up to
                                    <?= $pkg['group_size_max'] ?> people</span><?php endif; ?>
                            <span><i class="fas fa-eye"></i> <?= $pkg['views'] ?> Views</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Package Details Section -->
        <section class="package-details-section py-5">
            <div class="container">
                <div class="row">
                    <!-- Main Content -->
                    <div class="col-lg-8">

                        <!-- Package Images Gallery -->
                        <div class="package-gallery mb-4" data-aos="fade-up">
                            <img src="<?= htmlspecialchars($pkg_img) ?>" alt="<?= htmlspecialchars($pkg['title']) ?>"
                                class="img-fluid rounded w-100">
                        </div>

                        <!-- Know More About Destinations -->
                        <?php if (!empty($dests)): ?>
                            <div class="destination-info-section mb-4" data-aos="fade-up">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-gradient-primary text-white py-3">
                                        <h3 class="mb-0"><i class="fas fa-map-marked-alt me-2"></i> Know More About
                                            Destination</h3>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-4">
                                            <?php foreach ($dests as $dest):
                                                $d_img = !empty($dest['image']) ? 'admin/' . ltrim($dest['image'], '/') : '';
                                                $d_name = htmlspecialchars($dest['name'] ?? '');
                                                $d_desc = $dest['description'] ?? ($dest['excerpt'] ?? '');
                                                $d_best = $dest['best_time'] ?? ($dest['best_time_to_visit'] ?? '');
                                                $d_region = $dest['region'] ?? ($dest['country'] ?? '');
                                                $d_slug = $dest['slug'] ?? '';
                                                ?>
                                                <div class="col-md-12">
                                                    <div class="destination-card h-100">
                                                        <div class="destination-card-inner">
                                                            <div class="d-flex align-items-start">
                                                                <?php if (!empty($d_img)): ?>
                                                                    <div class="destination-thumbnail me-3"><img
                                                                            src="<?= htmlspecialchars($d_img) ?>"
                                                                            alt="<?= $d_name ?>" class="rounded"></div>
                                                                <?php endif; ?>
                                                                <div class="flex-grow-1">
                                                                    <h4 class="destination-title mb-2"><i
                                                                            class="fas fa-location-dot text-danger me-1"></i><?= $d_name ?>
                                                                    </h4>
                                                                    <?php if (!empty($d_region)): ?>
                                                                        <p class="mb-2 text-muted"><i
                                                                                class="fas fa-globe me-1"></i><strong><?= $d_name ?></strong><span
                                                                                class="ms-1">|
                                                                                <?= htmlspecialchars($d_region) ?></span></p>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($d_desc)): ?>
                                                                        <p class="destination-excerpt mb-3">
                                                                            <?= htmlspecialchars(mb_substr(strip_tags($d_desc), 0, 250)) ?>...
                                                                        </p><?php endif; ?>
                                                                    <?php if (!empty($d_best)): ?>
                                                                        <div class="destination-meta mb-3">
                                                                            <div class="meta-item"><i
                                                                                    class="fas fa-calendar-alt text-primary"></i><span
                                                                                    class="ms-1"><strong>Best Time:</strong>
                                                                                    <?= htmlspecialchars($d_best) ?></span></div>
                                                                        </div><?php endif; ?>
                                                                    <?php if (!empty($d_slug)): ?><a
                                                                            href="b5.php?slug=<?= urlencode($d_slug) ?>"
                                                                            class="btn btn-primary btn-sm destination-btn"><i
                                                                                class="fas fa-arrow-right me-1"></i> Explore
                                                                            <?= $d_name ?></a><?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Highlights -->
                        <?php if (!empty($pkg['highlights'])): ?>
                            <div class="package-highlights card mb-4" data-aos="fade-up">
                                <div class="card-body">
                                    <h3 class="card-title mb-3"><i class="fas fa-star text-warning"></i> Highlights</h3>
                                    <div class="highlights-content"><?= $pkg['highlights'] ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Itinerary -->
                        <?php if (!empty($itins)): ?>
                            <div class="package-itinerary card mb-4" data-aos="fade-up">
                                <div class="card-body">
                                    <h3 class="card-title mb-4"><i class="fas fa-route text-success"></i> Day-by-Day
                                        Itinerary</h3>
                                    <div class="accordion" id="itineraryAccordion">
                                        <?php foreach ($itins as $di => $itin):
                                            $exp = ($di === 0) ? 'show' : '';
                                            $col = ($di === 0) ? '' : 'collapsed'; ?>
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="itin_h<?= $itin['id'] ?>">
                                                    <button class="accordion-button <?= $col ?>" type="button"
                                                        data-bs-toggle="collapse" data-bs-target="#itin_c<?= $itin['id'] ?>"
                                                        aria-expanded="<?= $di === 0 ? 'true' : 'false' ?>">
                                                        <strong>Day
                                                            <?= $itin['day_number'] ?>:</strong>&nbsp;<?= htmlspecialchars($itin['title']) ?>
                                                    </button>
                                                </h2>
                                                <div id="itin_c<?= $itin['id'] ?>"
                                                    class="accordion-collapse collapse <?= $exp ?>"
                                                    data-bs-parent="#itineraryAccordion">
                                                    <div class="accordion-body">
                                                        <?php if (!empty($itin['description'])): ?>
                                                            <p><?= nl2br(htmlspecialchars($itin['description'])) ?></p>
                                                        <?php endif; ?>
                                                        <div class="row g-3">
                                                            <?php if (!empty($itin['meals'])): ?>
                                                                <div class="col-md-4"><strong><i
                                                                            class="fas fa-utensils text-danger"></i>
                                                                        Meals:</strong><br><?= htmlspecialchars($itin['meals']) ?>
                                                                </div><?php endif; ?>
                                                            <?php if (!empty($itin['accommodation'])): ?>
                                                                <div class="col-md-4"><strong><i
                                                                            class="fas fa-bed text-primary"></i>
                                                                        Accommodation:</strong><br><?= htmlspecialchars($itin['accommodation']) ?>
                                                                </div><?php endif; ?>
                                                            <?php if (!empty($itin['activities'])): ?>
                                                                <div class="col-md-12 mt-2"><strong><i
                                                                            class="fas fa-running text-success"></i>
                                                                        Activities:</strong><br><?= htmlspecialchars($itin['activities']) ?>
                                                                </div><?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>


                        <!-- Inclusions & Exclusions -->
                        <div class="row">
                            <div class="col-md-6 mb-4" data-aos="fade-up">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h3 class="card-title mb-3"><i class="fas fa-check-circle text-success"></i>
                                            Inclusions</h3>
                                        <div class="inclusions-content"><?= $pkg['inclusions'] ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-4" data-aos="fade-up">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h3 class="card-title mb-3"><i class="fas fa-times-circle text-danger"></i>
                                            Exclusions</h3>
                                        <div class="exclusions-content"><?= $pkg['exclusions'] ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">

                        <!-- Sticky Sidebar Container -->
                        <div class="sticky-sidebar">

                            <!-- Booking Card -->
                            <div class="card booking-card mb-4" data-aos="fade-up">
                                <div class="card-body">
                                    <div class="price-section mb-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <?php if ($has_disc): ?><span
                                                        class="old-price d-block text-muted text-decoration-line-through">Rs.
                                                        <?= number_format($pkg['original_price'], 0) ?></span><?php endif; ?>
                                                <span class="new-price h2 mb-0 text-primary">Rs.
                                                    <?= number_format($pkg['sale_price'], 0) ?></span>
                                            </div>
                                            <?php if ($has_disc): ?><span class="badge bg-danger fs-6"><?= $disc_pct ?>%
                                                    OFF</span><?php endif; ?>
                                        </div>
                                        <small class="text-muted d-block mt-1">Per Person</small>
                                    </div>
                                    <hr>
                                    <div class="package-info-list mb-3">
                                        <div class="info-item d-flex justify-content-between mb-2">
                                            <span><i class="fas fa-clock text-primary"></i> Duration</span>
                                            <strong><?= $dur ?></strong>
                                        </div>
                                        <?php if ($pkg['group_size_max'] > 0): ?>
                                            <div class="info-item d-flex justify-content-between mb-2">
                                                <span><i class="fas fa-users text-primary"></i> Group Size</span>
                                                <strong><?= $pkg['group_size_min'] ?>–<?= $pkg['group_size_max'] ?>
                                                    People</strong>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($cats)): ?>
                                            <div class="info-item mb-2">
                                                <span class="d-block mb-1"><i class="fas fa-tag text-primary"></i>
                                                    Categories</span>
                                                <div><?php foreach ($cats as $cat): ?><a
                                                            href="b1.php?category=<?= urlencode($cat['slug']) ?>"
                                                            class="badge bg-primary text-white text-decoration-none me-1 mb-1"><?= htmlspecialchars($cat['name']) ?></a><?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($dests)): ?>
                                            <div class="info-item mb-2">
                                                <span class="d-block mb-1"><i
                                                        class="fas fa-map-marker-alt text-primary"></i> Destinations</span>
                                                <div><?php foreach ($dests as $dest): ?><span
                                                            class="badge bg-info text-dark me-1 mb-1"><?= htmlspecialchars($dest['name']) ?></span><?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <a href="#" class="btn btn-primary btn-lg" data-bs-toggle="modal"
                                            data-bs-target="#enquiryModal">
                                            <i class="fas fa-paper-plane"></i> Book Now
                                        </a>
                                        <a href="#" class="btn btn-outline-primary" data-bs-toggle="modal"
                                            data-bs-target="#enquiryModal">
                                            <i class="fas fa-question-circle"></i> Send Enquiry
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Need Help Card -->
                            <div class="card mb-4" data-aos="fade-up" data-aos-delay="100">
                                <div class="card-body text-center">
                                    <i class="fas fa-headset fa-3x text-primary mb-3"></i>
                                    <h5>Need Help?</h5>
                                    <p class="text-muted mb-3">Our travel experts are ready to assist you</p>
                                    <a href="tel:+91 9709400140" class="btn btn-outline-primary w-100 mb-2">
                                        <i class="fas fa-phone"></i> +91 9709400140 </a>
                                    <a href="https://wa.me/1125425642" class="btn btn-success w-100" target="_blank">
                                        <i class="fab fa-whatsapp"></i> WhatsApp Us
                                    </a>
                                </div>
                            </div>

                            <!-- Share Card -->
                            <div class="card" data-aos="fade-up" data-aos-delay="200">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Share This Package</h5>
                                    <div class="d-grid gap-2">
                                        <a href="#"
                                            target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="fab fa-facebook-f"></i> Facebook
                                        </a>
                                        <a href="#"
                                            target="_blank" class="btn btn-outline-info btn-sm">
                                            <i class="fab fa-twitter"></i> Twitter
                                        </a>
                                        <a href="#"
                                            target="_blank" class="btn btn-outline-success btn-sm">
                                            <i class="fab fa-whatsapp"></i> WhatsApp
                                        </a>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <!-- End Sticky Sidebar Container -->

                    </div>
                </div>
            </div>
        </section>

        <!-- Related Packages -->
        <?php
        $rel = $conn->query("SELECT p.*, (SELECT GROUP_CONCAT(d.name SEPARATOR ', ') FROM destinations d JOIN package_destination_map pdm ON d.id=pdm.destination_id WHERE pdm.package_id=p.id) as dest_names FROM packages p WHERE p.status='Published' AND p.id != $pkg_id ORDER BY p.is_trending DESC, p.id DESC LIMIT 4");
        if ($rel && $rel->num_rows > 0):
            ?>
            <section class="related-packages-section py-5 bg-light">
                <div class="container">
                    <div class="section-header text-center mb-5" data-aos="fade-up">
                        <h2 class="section-title">You May Also Like</h2>
                        <p class="section-subtitle">Similar packages you might be interested in</p>
                    </div>
                    <div class="row g-4">
                        <?php while ($rp = $rel->fetch_assoc()):
                            $rp_img = !empty($rp['featured_image']) ? 'admin/' . ltrim($rp['featured_image'], '/') : '';
                            $rp_disc = ($rp['original_price'] > 0 && $rp['sale_price'] < $rp['original_price']);
                            $rp_pct = $rp_disc ? round((($rp['original_price'] - $rp['sale_price']) / $rp['original_price']) * 100) : 0;
                            ?>
                            <div class="col-lg-3 col-md-6" data-aos="fade-up">
                                <div class="package-card">
                                    <div class="package-image">
                                        <?php if (!empty($rp_img)): ?><img src="<?= htmlspecialchars($rp_img) ?>"
                                                alt="<?= htmlspecialchars($rp['title']) ?>"><?php else: ?>
                                            <div
                                                style="height:200px;background:#f0f2f5;display:flex;align-items:center;justify-content:center;">
                                                <i class="fas fa-image fa-3x text-muted"></i>
                                            </div><?php endif; ?>
                                        <?php if ($rp_disc): ?><span class="discount-badge"><?= $rp_pct ?>%
                                                OFF</span><?php endif; ?>
                                        <?php if ($rp['is_trending']): ?><span class="trending-badge"><i
                                                    class="fas fa-fire"></i>
                                                Trending</span><?php endif; ?>
                                    </div>
                                    <div class="package-content">
                                        <?php if (!empty($rp['dest_names'])): ?>
                                            <div class="package-location"><i class="fas fa-map-marker-alt"></i>
                                                <?= htmlspecialchars($rp['dest_names']) ?></div><?php endif; ?>
                                        <h4 class="package-title"><a
                                                href="b3.php?id=<?= $rp['id'] ?>"><?= htmlspecialchars($rp['title']) ?></a></h4>
                                        <div class="package-meta"><span><i class="fas fa-clock"></i>
                                                <?= $rp['duration_days'] ?>D / <?= $rp['duration_nights'] ?>N</span></div>
                                        <div class="package-footer">
                                            <div class="package-price">
                                                <?php if ($rp_disc): ?><span class="old-price">Rs.
                                                        <?= number_format($rp['original_price'], 0) ?></span><?php endif; ?>
                                                <span class="new-price">Rs. <?= number_format($rp['sale_price'], 0) ?></span>
                                            </div>
                                            <a href="b3.php?id=<?= $rp['id'] ?>" class="btn btn-sm btn-primary">View Details</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>


        <style>
            /* ============================================
   Destination Info Section Styles
   ============================================ */
            .bg-gradient-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }

            .destination-card {
                background: #fff;
                border-radius: 12px;
                padding: 20px;
                border: 1px solid #e8e8e8;
                transition: all 0.3s ease;
                height: 100%;
            }

            .destination-card:hover {
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
                transform: translateY(-5px);
                border-color: #667eea;
            }

            .destination-thumbnail {
                width: 120px;
                min-width: 120px;
                height: 120px;
                overflow: hidden;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }

            .destination-thumbnail img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.3s ease;
            }

            .destination-card:hover .destination-thumbnail img {
                transform: scale(1.1);
            }

            .destination-title {
                font-size: 1.5rem;
                font-weight: 700;
                color: #2d3748;
                margin-bottom: 0.5rem;
            }

            .destination-excerpt {
                font-size: 0.95rem;
                line-height: 1.6;
                color: #4a5568;
            }

            .destination-meta {
                padding: 12px;
                background: #f7fafc;
                border-radius: 8px;
                border-left: 3px solid #667eea;
            }

            .destination-meta .meta-item {
                font-size: 0.9rem;
                color: #2d3748;
            }

            .destination-btn {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                padding: 10px 20px;
                border-radius: 6px;
                font-weight: 600;
                transition: all 0.3s ease;
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            }

            .destination-btn:hover {
                transform: translateX(5px);
                box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
                background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            }

            .destination-info-section .card-header h3 {
                font-size: 1.5rem;
                font-weight: 700;
            }

            /* Responsive adjustments */
            @media (max-width: 768px) {
                .destination-thumbnail {
                    width: 100px;
                    min-width: 100px;
                    height: 100px;
                    margin-bottom: 1rem;
                }

                .destination-title {
                    font-size: 1.25rem;
                }

                .destination-card .d-flex {
                    flex-direction: column;
                }

                .destination-thumbnail {
                    margin-right: 0 !important;
                    margin-bottom: 15px;
                    width: 100%;
                    max-width: 200px;
                    height: 150px;
                }
            }

            /* ============================================
   Pure CSS Sticky Sidebar - Simple and Effective
   ============================================ */
            .sticky-sidebar {
                position: -webkit-sticky;
                position: sticky;
                top: 90px;
                /* Space from top when stuck */
                align-self: flex-start;
                /* Important for sticky to work in flexbox */
                z-index: 99;
            }

            /* Make cards more compact by default */
            .sticky-sidebar .card {
                margin-bottom: 1rem;
            }

            .sticky-sidebar .card:last-child {
                margin-bottom: 0;
            }

            .sticky-sidebar .card-body {
                padding: 1.25rem;
            }

            /* Optimize booking card spacing */
            .sticky-sidebar .price-section {
                margin-bottom: 1rem;
            }

            .sticky-sidebar .package-info-list .info-item {
                margin-bottom: 0.5rem;
                font-size: 0.95rem;
            }

            .sticky-sidebar .package-info-list .info-item i {
                width: 18px;
            }

            /* Compact help section */
            .sticky-sidebar .fa-headset {
                font-size: 2.5rem;
            }

            .sticky-sidebar h5 {
                font-size: 1.1rem;
                margin-bottom: 0.75rem;
            }

            .sticky-sidebar p {
                font-size: 0.95rem;
            }

            /* Share section buttons more compact */
            .sticky-sidebar .btn-sm {
                font-size: 0.85rem;
                padding: 0.5rem 1rem;
            }

            /* Mobile - disable sticky */
            @media (max-width: 991px) {
                .sticky-sidebar {
                    position: relative !important;
                    top: 0 !important;
                }
            }

            /* Package Gallery Image Size Reduction (40% smaller) */
            .package-gallery img {
                max-height: 350px;
                /* Reduced from ~600px typical height */
                object-fit: cover;
                width: 100%;
                cursor: pointer;
                transition: transform 0.3s ease;
            }

            .package-gallery img:hover {
                transform: scale(1.02);
            }

            /* First image (main image) slightly larger but still reduced */
            .package-gallery .row>div:first-child img {
                max-height: 400px;
            }

            /* Smaller images in grid */
            .package-gallery .row>div:not(:first-child) img {
                max-height: 280px;
            }

            /* Mobile adjustments */
            @media (max-width: 768px) {
                .package-gallery img {
                    max-height: 250px;
                }

                .package-gallery .row>div:first-child img {
                    max-height: 280px;
                }

                .package-gallery .row>div:not(:first-child) img {
                    max-height: 200px;
                }
            }
        </style>

        <script>
            // Pure CSS sticky is handling the sidebar behavior
            // No JavaScript needed for basic sticky functionality
            // CSS position: sticky handles everything automatically
        </script>

    </main>
    <!-- End Main Content -->

    <?php include('footer.php'); ?>
    <?php include('footerlinks.php'); ?>



</body>

</html>