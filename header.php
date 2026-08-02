<!-- Main Header with Logo and Menu -->
<header class="main-header-new">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand-new" href="index.php">
                <?php if (!empty($siteSettings['logo_path']) && file_exists('admin/' . $siteSettings['logo_path'])): ?>
                    <img src="admin/<?= htmlspecialchars($siteSettings['logo_path']) ?>" alt="<?= htmlspecialchars($siteSettings['site_title'] ?? 'Multizone Travels') ?>" class="logo-new">
                <?php else: ?>
                    <img src="images/awer.png" alt="Multizone Travels" class="logo-new">
                <?php endif; ?>
            </a>

            <!-- Mobile Toggle -->
            <button class="navbar-toggler mobile-toggle" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Navigation Menu -->
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link nav-link-new" href="index.php">Home</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link nav-link-new" href="b1.php?type=trending">
                            Trending Packages </a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link nav-link-new dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            Explore Destinations
                        </a>
                        <ul class="dropdown-menu dropdown-menu-new">
                            <?php
                            $headerCatRes = $conn->query("SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY display_order ASC LIMIT 10");
                            if ($headerCatRes) {
                                while ($hc = $headerCatRes->fetch_assoc()) {
                                    echo '<li><a class="dropdown-item" href="b1.php?category=' . htmlspecialchars($hc['slug']) . '">' . htmlspecialchars($hc['name']) . '</a></li>';
                                }
                            }
                            ?>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link nav-link-new dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            Holiday Tour Packages
                        </a>
                        <ul class="dropdown-menu dropdown-menu-new">
                            <li><a class="dropdown-item" href="b1.php">All Packages</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php
                            $headerPkgRes = $conn->query("SELECT id, title FROM packages WHERE status = 'Published' ORDER BY is_trending DESC, id DESC LIMIT 10");
                            if ($headerPkgRes) {
                                while ($hp = $headerPkgRes->fetch_assoc()) {
                                    echo '<li><a class="dropdown-item" href="b3.php?id=' . $hp['id'] . '">' . htmlspecialchars($hp['title']) . '</a></li>';
                                }
                            }
                            ?>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link nav-link-new" href="about-us.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-new nav-privilege-portal" href="privilege_login.php">
                            <i class="fas fa-id-card me-1"></i> Privilege login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn-enquiry-new" href="#" data-bs-toggle="modal" data-bs-target="#enquiryModal">
                            Enquire Now
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>