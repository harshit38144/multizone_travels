<?php
session_start();
include('admin/connection.php');

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug)) {
    header("Location: index.php");
    exit;
}

// Fetch destination by slug
$stmt = $conn->prepare("SELECT * FROM destinations WHERE slug = ? AND is_active = 1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$dest_res = $stmt->get_result();

if ($dest_res->num_rows === 0) {
    header("Location: index.php");
    exit;
}

$dest = $dest_res->fetch_assoc();
$dest_id = $dest['id'];
$d_name = htmlspecialchars($dest['name']);
$d_image = !empty($dest['image']) ? 'admin/' . ltrim($dest['image'], '/') : 'images/default-dest.jpg';
$d_country = htmlspecialchars($dest['country'] ?? '');
$d_region = htmlspecialchars($dest['region'] ?? '');
$d_desc = $dest['description'] ?? '';
$d_how_to_reach = $dest['how_to_reach'] ?? '';
$d_best_time = $dest['best_time_to_visit'] ?? '';

$location_str = $d_country;
if (!empty($d_region)) {
    $location_str = empty($location_str) ? $d_region : $location_str . ' - ' . $d_region;
}

// Fetch related packages
$pkg_stmt = $conn->prepare("
    SELECT p.* 
    FROM packages p
    JOIN package_destination_map pdm ON p.id = pdm.package_id
    WHERE pdm.destination_id = ? AND p.status = 'Published'
    ORDER BY p.id DESC
");
$pkg_stmt->bind_param("i", $dest_id);
$pkg_stmt->execute();
$pkg_res = $pkg_stmt->get_result();
$package_count = $pkg_res->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <title>Multizone Travels</title>
    <meta name="description" content="Explore packages for <?= $d_name ?>, <?= $location_str ?>">
    
    <?php include('headerlinks.php'); ?>
</head>
<body>

    <?php include('header1.php'); ?>

    <!-- Main Content Wrapper -->
    <main>

        <style>
        .destination-hero {
            position: relative;
            height: 300px;
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 0 !important;
        }
        
        .destination-hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
        }
        
        .destination-hero-content {
            position: relative;
            z-index: 2;
            color: white;
            text-align: center;
        }
        
        .destination-hero-content h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .destination-info {
            padding: 30px 0 !important;
            margin-top: 0 !important;
        }
        
        .info-box {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .info-box h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .info-box .icon {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .destination-hero {
                height: 250px;
            }
        
            .destination-hero-content h1 {
                font-size: 28px;
                margin-bottom: 8px;
            }
        
            .destination-info {
                padding: 25px 0 !important;
            }
        }
        </style>

        <!-- Destination Hero -->
        <section class="destination-hero" style="background-image: url('<?= htmlspecialchars($d_image) ?>');">
            <div class="destination-hero-overlay"></div>
            <div class="destination-hero-content">
                <div class="container">
                    <h1><?= $d_name ?></h1>
                    <?php if (!empty($location_str)): ?>
                        <p style="font-size: 20px;">
                            <i class="fas fa-map-marker-alt"></i> <?= $location_str ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Destination Info -->
        <section class="destination-info bg-light">
            <div class="container">
                <div class="row">
                    <div class="col-lg-8">
                        <?php if (!empty($d_desc)): ?>
                            <div class="info-box">
                                <h3><i class="fas fa-info-circle icon"></i> About <?= $d_name ?></h3>
                                <div><?= $d_desc ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($d_how_to_reach)): ?>
                            <div class="info-box">
                                <h3><i class="fas fa-plane icon"></i> How to Reach</h3>
                                <div><?= $d_how_to_reach ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-4">
                        <?php if (!empty($d_best_time)): ?>
                            <div class="info-box">
                                <h3><i class="fas fa-calendar-alt icon"></i> Best Time to Visit</h3>
                                <p><?= htmlspecialchars($d_best_time) ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-box">
                            <h3><i class="fas fa-box icon"></i> Available Packages</h3>
                            <p style="font-size: 36px; font-weight: 700; color: var(--primary-color); margin: 0;">
                                <?= $package_count ?>
                            </p>
                            <p>Tour packages available</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Packages for this Destination -->
        <section class="packages-section py-5">
            <div class="container">
                <div class="section-header text-center mb-5" data-aos="fade-up">
                    <h2 class="section-title">Tour Packages for <?= $d_name ?></h2>
                    <p class="section-subtitle">Choose from our best packages</p>
                </div>

                <div class="row g-4">
                    <?php if ($package_count > 0): ?>
                        <?php while($pkg = $pkg_res->fetch_assoc()): 
                            $pkg_img = !empty($pkg['featured_image']) ? 'admin/'.ltrim($pkg['featured_image'], '/') : 'images/default-package.jpg';
                            
                            $price_html = '';
                            $discount_badge = '';
                            if ($pkg['sale_price'] > 0) {
                                $new_price = number_format($pkg['sale_price']);
                                if ($pkg['original_price'] > $pkg['sale_price']) {
                                    $old_price = number_format($pkg['original_price']);
                                    $discount = round((($pkg['original_price'] - $pkg['sale_price']) / $pkg['original_price']) * 100);
                                    $price_html = '<span class="old-price">Rs. '.$old_price.'</span><span class="new-price">Rs. '.$new_price.'</span>';
                                    if ($discount > 0) {
                                        $discount_badge = '<span class="discount-badge">'.$discount.'% OFF</span>';
                                    }
                                } else {
                                    $price_html = '<span class="new-price">Rs. '.$new_price.'</span>';
                                }
                            } elseif ($pkg['original_price'] > 0) {
                                $new_price = number_format($pkg['original_price']);
                                $price_html = '<span class="new-price">Rs. '.$new_price.'</span>';
                            } else {
                                $price_html = '<span class="new-price">Price on Request</span>';
                            }
                        ?>
                            <div class="col-lg-3 col-md-6" data-aos="fade-up">
                                <div class="package-card">
                                    <div class="package-image">
                                        <img src="<?= htmlspecialchars($pkg_img) ?>" alt="<?= htmlspecialchars($pkg['title']) ?>">
                                        <?= $discount_badge ?>
                                    </div>
                                    
                                    <div class="package-content">
                                        <div class="package-location">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?= $d_name ?>
                                        </div>

                                        <h4 class="package-title">
                                            <a href="b3.php?id=<?= $pkg['id'] ?>">
                                                <?= htmlspecialchars($pkg['title']) ?>
                                            </a>
                                        </h4>

                                        <div class="package-meta">
                                            <span><i class="fas fa-clock"></i> <?= $pkg['duration_days'] ?>D / <?= $pkg['duration_nights'] ?>N</span>
                                        </div>

                                        <div class="package-footer">
                                            <div class="package-price">
                                                <?= $price_html ?>
                                            </div>
                                            <a href="b3.php?id=<?= $pkg['id'] ?>" class="btn btn-sm btn-primary">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-5">
                            <h4 class="text-muted">No tour packages available for <?= $d_name ?> at the moment.</h4>
                            <p>Please check back later or contact us for custom packages.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    </main>
    <!-- End Main Content -->

    <?php include('footer.php'); ?>
    <?php include('footerlinks.php'); ?>

</body>
</html>