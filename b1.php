<?php
include('admin/connection.php');

$pageTitle = "Packages";
$breadcrumbActive = "Packages";
$filterTitle = "All Packages";
$filterDesc = "Explore our wide range of tour packages.";

$category_slug = isset($_GET['category']) ? $_GET['category'] : '';
$destination_slug = isset($_GET['destination']) ? $_GET['destination'] : '';
$duration = isset($_GET['duration']) ? $_GET['duration'] : '';
$budget = isset($_GET['budget']) ? (int)$_GET['budget'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

if (!empty($category_slug)) {
    $catStmt = $conn->prepare("SELECT name, description FROM categories WHERE slug = ?");
    $catStmt->bind_param("s", $category_slug);
    $catStmt->execute();
    $catRes = $catStmt->get_result();
    if ($catRow = $catRes->fetch_assoc()) {
        $pageTitle = $catRow['name'] . " Packages";
        $breadcrumbActive = $catRow['name'];
        $filterTitle = $catRow['name'];
        $filterDesc = !empty($catRow['description']) ? $catRow['description'] : $catRow['name'] . " Packages...";
    }
}

if (!empty($destination_slug)) {
    $destStmt = $conn->prepare("SELECT name FROM destinations WHERE slug = ?");
    $destStmt->bind_param("s", $destination_slug);
    $destStmt->execute();
    $destRes = $destStmt->get_result();
    if ($destRow = $destRes->fetch_assoc()) {
        $pageTitle = $destRow['name'] . " Packages";
        $breadcrumbActive = $destRow['name'];
        $filterTitle = $destRow['name'];
        $filterDesc = "Explore packages for " . $destRow['name'];
    }
}

if ($budget > 0) {
    $formatted_budget = number_format($budget);
    $pageTitle = "Packages Under Rs. " . $formatted_budget;
    $breadcrumbActive = "Budget: " . $formatted_budget;
    $filterTitle = "Budget Friendly Packages";
    $filterDesc = "Explore our best packages within your budget of Rs. " . $formatted_budget . ".";
}

if ($type === 'featured') {
    $pageTitle = "Featured Packages";
    $breadcrumbActive = "Featured";
    $filterTitle = "Featured Packages";
    $filterDesc = "Explore our handpicked featured packages.";
} elseif ($type === 'trending') {
    $pageTitle = "Trending Packages";
    $breadcrumbActive = "Trending";
    $filterTitle = "Trending Packages";
    $filterDesc = "Explore our most popular trending packages.";
}

// Build query for packages
$pkgQuery = "SELECT p.* FROM packages p WHERE p.status = 'Published'";

if (!empty($category_slug)) {
    $pkgQuery .= " AND p.id IN (SELECT pcm.package_id FROM package_category_map pcm JOIN categories c ON pcm.category_id = c.id WHERE c.slug = '" . $conn->real_escape_string($category_slug) . "')";
}

if (!empty($destination_slug)) {
    $pkgQuery .= " AND p.id IN (SELECT pdm.package_id FROM package_destination_map pdm JOIN destinations d ON pdm.destination_id = d.id WHERE d.slug = '" . $conn->real_escape_string($destination_slug) . "')";
}

if ($duration == '1-3') {
    $pkgQuery .= " AND p.duration_days BETWEEN 1 AND 3";
} elseif ($duration == '4-6') {
    $pkgQuery .= " AND p.duration_days BETWEEN 4 AND 6";
} elseif ($duration == '7+') {
    $pkgQuery .= " AND p.duration_days >= 7";
}

if ($budget > 0) {
    $pkgQuery .= " AND p.sale_price <= " . $budget;
}

if ($type === 'featured') {
    $pkgQuery .= " AND p.is_featured = 1";
} elseif ($type === 'trending') {
    $pkgQuery .= " AND p.is_trending = 1";
}

$pkgQuery .= " ORDER BY p.id DESC";
$pkgResult = $conn->query($pkgQuery);
$totalPackages = $pkgResult ? $pkgResult->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= isset($pageTitle) ? $pageTitle : 'Packages' ?> - Multizone Travels</title>

    <?php include('headerlinks.php'); ?>
</head>

<body>

    <?php include('header1.php'); ?>

    <!-- Multi-Step Enquiry Modal -->
    <?php include('enquiry_modal.php'); ?>

    <!-- Main Content Wrapper -->
    <main>
        <!-- Page Header -->
        <section class="page-header">
            <div class="page-header-overlay"></div>
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="page-title" data-aos="fade-up"><?= $pageTitle ?></h1>
                        <nav aria-label="breadcrumb" data-aos="fade-up" data-aos-delay="100">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                                <li class="breadcrumb-item active"><?= $breadcrumbActive ?></li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </section>

        <!-- Category/Destination/Country Info -->
        <section class="filter-info-section py-4 bg-light">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3><?= $filterTitle ?></h3>
                        <div class="text-muted"><?= $filterDesc ?></div>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <p class="mb-0 text-muted"><?= $totalPackages ?> Packages Found</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Search and Filter Section -->
        <section class="search-filter-section py-4">
            <div class="container">
                <form method="GET" action="b1.php">
                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <select class="form-select" name="category" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php
                                $catList = $conn->query("SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY display_order ASC");
                                while ($c = $catList->fetch_assoc()) {
                                    $selected = ($category_slug == $c['slug']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($c['slug']) . '" ' . $selected . '>' . htmlspecialchars($c['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <select class="form-select" name="destination" onchange="this.form.submit()">
                                <option value="">All Destinations</option>
                                <?php
                                $destList = $conn->query("SELECT name, slug FROM destinations WHERE is_active = 1 ORDER BY display_order ASC");
                                while ($d = $destList->fetch_assoc()) {
                                    $selected = ($destination_slug == $d['slug']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($d['slug']) . '" ' . $selected . '>' . htmlspecialchars($d['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <select class="form-select" name="duration" onchange="this.form.submit()">
                                <option value="">Duration</option>
                                <option value="1-3" <?= ($duration == '1-3') ? 'selected' : '' ?>>1-3 Days</option>
                                <option value="4-6" <?= ($duration == '4-6') ? 'selected' : '' ?>>4-6 Days</option>
                                <option value="7+" <?= ($duration == '7+') ? 'selected' : '' ?>>7+ Days</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <input type="date" class="form-control" name="date" value="<?= isset($_GET['date']) ? htmlspecialchars($_GET['date']) : '' ?>">
                        </div>
                        <div class="col-lg-2 col-md-12">
                            <div class="d-grid gap-2 d-md-flex">
                                <button type="submit" class="btn btn-primary flex-fill">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="b1.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- Packages Grid Section -->
        <section class="packages-listing-section py-5">
            <div class="container">
                <div class="row g-4">
                    <?php
                    if ($totalPackages > 0) {
                        while ($pkg = $pkgResult->fetch_assoc()) {
                            $pkg_id = $pkg['id'];
                            $pkg_title = htmlspecialchars($pkg['title']);
                            $pkg_image = !empty($pkg['featured_image']) ? 'admin/' . $pkg['featured_image'] : 'images/default-package.jpg';
                            $pkg_link = "b3.php?id=" . $pkg_id;
                            
                            // Get first destination
                            $dest_name = "Multiple Destinations";
                            $dRes = $conn->query("SELECT d.name FROM destinations d JOIN package_destination_map pdm ON d.id = pdm.destination_id WHERE pdm.package_id = $pkg_id LIMIT 1");
                            if ($dRes && $dRow = $dRes->fetch_assoc()) {
                                $dest_name = htmlspecialchars($dRow['name']);
                            }
                            
                            $duration_days = $pkg['duration_days'];
                            $duration_nights = $pkg['duration_nights'];
                            $difficulty = !empty($pkg['difficulty_level']) ? htmlspecialchars($pkg['difficulty_level']) : 'Moderate';
                            
                            $old_price = $pkg['original_price'];
                            $new_price = $pkg['sale_price'];
                            $discount = 0;
                            if ($old_price > 0 && $new_price > 0 && $old_price > $new_price) {
                                $discount = round((($old_price - $new_price) / $old_price) * 100);
                            }
                            ?>
                            <div class="col-lg-3 col-md-6" data-aos="fade-up">
                                <div class="package-card">
                                    <div class="package-image">
                                        <img src="<?= $pkg_image ?>" alt="<?= $pkg_title ?>">
                                        <?php if ($discount > 0): ?>
                                            <span class="discount-badge"><?= $discount ?>% OFF</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="package-content">
                                        <div class="package-location">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?= $dest_name ?>
                                        </div>

                                        <h4 class="package-title">
                                            <a href="<?= $pkg_link ?>">
                                                <?= $pkg_title ?> </a>
                                        </h4>

                                        <div class="package-meta">
                                            <span><i class="fas fa-clock"></i> <?= $duration_days ?>D / <?= $duration_nights ?>N</span>
                                            <span><i class="fas fa-hiking"></i> <?= $difficulty ?></span>
                                        </div>

                                        <div class="package-footer">
                                            <div class="package-price">
                                                <?php if ($old_price > 0): ?>
                                                    <span class="old-price">Rs. <?= number_format($old_price) ?></span>
                                                <?php endif; ?>
                                                <span class="new-price">Rs. <?= number_format($new_price) ?></span>
                                            </div>
                                            <a href="<?= $pkg_link ?>" class="btn btn-sm btn-primary">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<div class="col-12 text-center py-5"><h4>No packages found matching your criteria.</h4></div>';
                    }
                    ?>
                </div>
            </div>
        </section>

        <!-- Call to Action -->
        <section class="cta-section py-5">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-8" data-aos="fade-right">
                        <h2 class="text-white mb-3">Can't Find What You're Looking For?</h2>
                        <p class="text-white mb-0">Contact us for custom tour packages tailored to your needs!</p>
                    </div>
                    <div class="col-lg-4 text-lg-end" data-aos="fade-left">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#enquiryModal" class="btn btn-light btn-lg">
                            <i class="fas fa-paper-plane me-2"></i> Send Enquiry
                        </a>
                    </div>
                </div>
            </div>
        </section>

    </main>
    <!-- End Main Content -->

    <?php include('footer.php'); ?>
    <?php include('footerlinks.php'); ?>



</body>

</html>