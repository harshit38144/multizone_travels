<!-- Meta Tags -->
<?php if (!empty($siteSettings['meta_description'])): ?>
<meta name="description" content="<?= htmlspecialchars($siteSettings['meta_description']) ?>">
<?php endif; ?>
<?php if (!empty($siteSettings['meta_keywords'])): ?>
<meta name="keywords" content="<?= htmlspecialchars($siteSettings['meta_keywords']) ?>">
<?php endif; ?>

<!-- Favicon -->
<?php if (!empty($siteSettings['favicon_path']) && file_exists('admin/' . $siteSettings['favicon_path'])): ?>
<link rel="icon" href="admin/<?= htmlspecialchars($siteSettings['favicon_path']) ?>" type="image/x-icon">
<?php else: ?>
<link rel="icon" href="images/icons1.png" type="image/x-icon">
<?php endif; ?>

<!-- Bootstrap CSS -->
<link href="css/bootstrap.min.css" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="css/all.min.css">

<!-- Owl Carousel CSS -->
<link rel="stylesheet" href="css/owl.carousel.min.css">
<link rel="stylesheet" href="css/owl.theme.default.min.css">

<!-- AOS Animation CSS -->
<link href="css/aos.css" rel="stylesheet">

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
<link
    href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=PT+Sans:wght@300;400;500;600&display=swap"
    rel="stylesheet">

<!-- Custom CSS -->
<link rel="stylesheet" href="css/style.css?v=20260516d">
<link rel="stylesheet" href="css/site-modals.css">

<!-- Dynamic Theme Colors & Fonts -->
<style>
    :root {
        --primary-color: <?= !empty($siteSettings['primary_color']) ? htmlspecialchars($siteSettings['primary_color']) : '#1b820d' ?>;
        --secondary-color: <?= !empty($siteSettings['secondary_color']) ? htmlspecialchars($siteSettings['secondary_color']) : '#2c00a3' ?>;
        --accent-color: <?= !empty($siteSettings['accent_color']) ? htmlspecialchars($siteSettings['accent_color']) : '#fdafaf' ?>;
        --gradient-start-color: #f8fb51;
        --gradient-end-color: <?= !empty($siteSettings['primary_color']) ? htmlspecialchars($siteSettings['primary_color']) : '#24a800' ?>;
        --button-gradient-start: <?= !empty($siteSettings['secondary_color']) ? htmlspecialchars($siteSettings['secondary_color']) : '#0c09ae' ?>;
        --button-gradient-end: #948f8f;
        --header-scroll-color: <?= !empty($siteSettings['header_scroll_color']) ? htmlspecialchars($siteSettings['header_scroll_color']) : '#000000' ?>;
        --header-text-color: <?= !empty($siteSettings['header_text_color']) ? htmlspecialchars($siteSettings['header_text_color']) : '#ffffff' ?>;
        --heading-font: 'Montserrat', sans-serif;
        --body-font: 'PT Sans', sans-serif;
    }

    /* Apply fonts globally */
    body {
        font-family: var(--body-font) !important;
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    h6,
    .section-title,
    .slider-heading,
    .hero-title,
    .package-title,
    .category-card h4,
    .navbar-brand-new,
    .brand-text-new,
    .feature-card h5,
    .footer-title {
        font-family: var(--heading-font) !important;
    }
    
    /* Header colors — home (transparent → scroll) and inner pages (main-header-solid) */
    .main-header-new .nav-link-new,
    .main-header-new .navbar-brand-new,
    .main-header-solid .nav-link-new,
    .main-header-solid .navbar-brand-new,
    .main-header-solid .brand-text-new,
    .main-header-solid .dropdown-toggle.nav-link-new {
        color: var(--header-text-color) !important;
    }
    .main-header-new .mobile-toggle,
    .main-header-new .mobile-toggle i,
    .main-header-solid .mobile-toggle,
    .main-header-solid .mobile-toggle i {
        color: var(--header-text-color) !important;
    }
    .main-header-solid,
    .main-header-solid.scrolled {
        background: var(--header-scroll-color) !important;
    }
    .main-header-new.scrolled .nav-link-new:hover,
    .main-header-solid .nav-link-new:hover {
        color: var(--header-text-color) !important;
    }
    .main-header-new:not(.main-header-solid).scrolled .nav-link-new:hover {
        color: var(--primary-color) !important;
    }
</style>