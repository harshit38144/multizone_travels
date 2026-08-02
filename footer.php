<?php
if (!isset($siteSettings))
    $siteSettings = [];

/** Fallback when DB column is unset or blank (shows legacy copy). WhatsApp uses separate NULL vs '' rules in footerlinks.php. */
$footerFmt = function ($value, $default) {
    $value = isset($value) ? trim((string) $value) : '';
    return $value === '' ? $default : $value;
};
$footerAddress = $footerFmt($siteSettings['footer_address'] ?? null, 'san francisco, New york, LA');
$footerPhone = $footerFmt($siteSettings['footer_phone'] ?? null, '+91 9709400140');
$footerEmail = $footerFmt($siteSettings['footer_email'] ?? null, 'info@yourdomain.com');
$footerHours = $footerFmt($siteSettings['footer_working_hours'] ?? null, 'Mon - Sat: 10:00 AM - 6:00 PM');
$newsletterHeading = $footerFmt($siteSettings['footer_newsletter_heading'] ?? null, 'Subscribe Newsletter');
$newsletterPlaceholder = $footerFmt($siteSettings['footer_newsletter_placeholder'] ?? null, 'Your Email');
$footerTelHref = 'tel:' . preg_replace('/[^0-9+]/', '', $footerPhone);
$footerMailHref = 'mailto:' . $footerEmail;

$popularDestinations = [];
if (isset($conn) && $conn && !$conn->connect_errno) {
    $destTable = @$conn->query("SHOW TABLES LIKE 'destinations'");
    if ($destTable && $destTable->num_rows > 0) {
        $fdLimit = 6;
        $fdStmt = $conn->prepare("SELECT name, slug FROM destinations WHERE is_active = 1 ORDER BY display_order ASC, name ASC LIMIT ?");
        if ($fdStmt) {
            $fdStmt->bind_param('i', $fdLimit);
            $fdStmt->execute();
            $fdRes = $fdStmt->get_result();
            if ($fdRes) {
                while ($fdRow = $fdRes->fetch_assoc()) {
                    $popularDestinations[] = $fdRow;
                }
            }
            $fdStmt->close();
        }
    }
}
?>
<!-- Footer -->
<footer class="site-footer">
    <div class="footer-top">
        <div class="container">
            <div class="row">
                <!-- About Column -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-title">About Us</h5>
                    <?php if (!empty($siteSettings['logo_path']) && file_exists('admin/' . $siteSettings['logo_path'])): ?>
                        <img src="admin/<?= htmlspecialchars($siteSettings['logo_path']) ?>"
                            alt="<?= htmlspecialchars($siteSettings['site_title'] ?? 'Multizone Travels') ?>"
                            class="footer-logo mb-3">
                    <?php else: ?>
                        <img src="images/awer.png" alt="Multizone Travels" class="footer-logo mb-3">
                    <?php endif; ?>
                    <p><?= !empty($siteSettings['footer_about_text']) ? htmlspecialchars($siteSettings['footer_about_text']) : 'We are committed to providing the best travel experiences to our customers.' ?>
                    </p>
                    <div class="footer-social">
                        <?php if (!empty($siteSettings['facebook_url'])): ?>
                            <a href="<?= htmlspecialchars($siteSettings['facebook_url']) ?>" target="_blank"><i
                                    class="fab fa-facebook-f"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($siteSettings['instagram_url'])): ?>
                            <a href="<?= htmlspecialchars($siteSettings['instagram_url']) ?>" target="_blank"><i
                                    class="fab fa-instagram"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($siteSettings['twitter_url'])): ?>
                            <a href="<?= htmlspecialchars($siteSettings['twitter_url']) ?>" target="_blank"><i
                                    class="fab fa-twitter"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($siteSettings['linkedin_url'])): ?>
                            <a href="<?= htmlspecialchars($siteSettings['linkedin_url']) ?>" target="_blank"><i
                                    class="fab fa-linkedin-in"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($siteSettings['youtube_url'])): ?>
                            <a href="<?= htmlspecialchars($siteSettings['youtube_url']) ?>" target="_blank"><i
                                    class="fab fa-youtube"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="col-lg-3 col-md-6 mb-4 footer-quick-links-col">
                    <h5 class="footer-title">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="index.php"><i class="fas fa-angle-right"></i> Home</a></li>
                        <li>
                            <a href="about-us.php">
                                <i class="fas fa-angle-right"></i> About Us </a>
                        </li>
                        <?php
                        $footerPages = $conn->query("SELECT title, slug FROM pages WHERE is_published = 1 ORDER BY id ASC");
                        if ($footerPages && $footerPages->num_rows > 0) {
                            while ($fp = $footerPages->fetch_assoc()) {
                                echo '<li><a href="page.php?slug=' . htmlspecialchars($fp['slug']) . '"><i class="fas fa-angle-right"></i> ' . htmlspecialchars($fp['title']) . '</a></li>';
                            }
                        }
                        ?>
                    </ul>
                    <div class="footer-pay-cta">
                        <a href="pay.php" class="footer-pay-pill" title="Pay online securely">
                            <i class="fas fa-indian-rupee-sign" aria-hidden="true"></i>
                            <span>Pay Now</span>
                        </a>
                    </div>
                </div>

                <!-- Popular Destinations -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-title">Popular Destinations</h5>
                    <ul class="footer-links">
                        <?php foreach ($popularDestinations as $fd): ?>
                            <?php
                            $fdSlug = trim((string) ($fd['slug'] ?? ''));
                            $fdName = htmlspecialchars((string) ($fd['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $fdHref = $fdSlug !== '' ? 'b5.php?slug=' . rawurlencode($fdSlug) : '#';
                            ?>
                            <li>
                                <a href="<?= htmlspecialchars($fdHref, ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fas fa-map-marker-alt"></i> <?= $fdName ?> </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-title">Contact Info</h5>
                    <ul class="footer-contact">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= htmlspecialchars($footerAddress) ?></span>
                        </li>
                        <li>
                            <i class="fas fa-phone"></i>
                            <a href="<?= htmlspecialchars($footerTelHref) ?>"><?= htmlspecialchars($footerPhone) ?></a>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <a href="<?= htmlspecialchars($footerMailHref) ?>"><?= htmlspecialchars($footerEmail) ?></a>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span><?= htmlspecialchars($footerHours) ?></span>
                        </li>
                    </ul>

                    <!-- Newsletter -->
                    <div class="newsletter-box mt-3">
                        <h6><?= htmlspecialchars($newsletterHeading) ?></h6>
                        <form id="newsletterForm" class="newsletter-form">
                            <div class="input-group">
                                <input type="email" class="form-control" name="email"
                                    placeholder="<?= htmlspecialchars($newsletterPlaceholder) ?>" required="">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                            <div id="newsletter-message" class="mt-2"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 text-center text-md-start">
                    <p class="mb-0">
                        <?= !empty($siteSettings['copyright_text']) ? htmlspecialchars($siteSettings['copyright_text']) : '© 2026 Multizone Travels. All Rights Reserved.' ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</footer>

<a href="pay.php" class="pay-float" title="Pay Online" aria-label="Pay Online"
    style="position:fixed;bottom:24px;right:92px;width:56px;height:56px;z-index:9999;display:flex;align-items:center;justify-content:center;border-radius:50%;background:linear-gradient(135deg,#3498db,#2ecc71);color:#fff;font-size:24px;box-shadow:0 4px 14px rgba(0,0,0,.25);text-decoration:none;">
    <i class="fas fa-wallet"></i>
</a>

