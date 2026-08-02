<?php
include('admin/connection.php');

$aboutDefaults = [
    'hero_title' => 'About Us',
    'hero_subtitle' => 'About Us',
    'about_image' => 'images/dest_68fb2a7f66c78_1761290879.jpg',
    'experience_years' => '10+',
    'experience_text' => 'Years of Experience',
    'section_title' => 'Welcome to Multizone Travels',
    'lead_text' => 'Your trusted partner for creating unforgettable travel memories across the globe.',
    'description_1' => "At Multizone Travels, we believe that traveling is more than just visiting new places; it's about experiencing different cultures, creating lifelong memories, and discovering yourself along the way. With years of experience in the travel industry, we have curated the perfect blend of destinations, itineraries, and experiences to cater to every traveler's dream.",
    'description_2' => "Whether you're looking for a romantic honeymoon, a thrilling adventure, a relaxing family vacation, or a customized group tour, our team of travel experts is dedicated to planning the perfect getaway for you.",
    'stat_1_number' => '50+',
    'stat_1_label' => 'Destinations',
    'stat_2_number' => '10,000+',
    'stat_2_label' => 'Happy Travelers',
    'why_title' => 'Why Choose Us',
    'why_subtitle' => 'What makes us the best choice for your travel needs',
    'why_1_title' => 'Expert Guidance',
    'why_1_text' => 'Our travel experts have in-depth knowledge of destinations to provide you with the best recommendations and itineraries.',
    'why_2_title' => 'Best Value',
    'why_2_text' => 'We offer competitive pricing without compromising on quality, ensuring you get the best value for your money.',
    'why_3_title' => '24/7 Support',
    'why_3_text' => 'We are always here for you. Our dedicated support team is available round the clock to assist you during your trip.',
    'cta_title' => 'Ready to Start Your Journey?',
    'cta_text' => "Contact us today and let's plan your dream vacation together!",
    'cta_button_text' => 'Send Enquiry'
];

$aboutPageData = $aboutDefaults;
$checkAboutTable = $conn->query("SHOW TABLES LIKE 'about_us_settings'");
if ($checkAboutTable && $checkAboutTable->num_rows > 0) {
    $aboutRes = $conn->query("SELECT * FROM about_us_settings WHERE id=1 LIMIT 1");
    if ($aboutRes && $aboutRes->num_rows > 0) {
        $dbAbout = $aboutRes->fetch_assoc();
        foreach ($aboutDefaults as $key => $defaultValue) {
            if (isset($dbAbout[$key]) && $dbAbout[$key] !== '') {
                $aboutPageData[$key] = $dbAbout[$key];
            }
        }
    }
}

$aboutImagePath = trim((string)($aboutPageData['about_image'] ?? ''));
if ($aboutImagePath !== '' && strpos($aboutImagePath, 'uploads/about/') === 0) {
    $aboutImagePath = 'admin/' . $aboutImagePath;
}
if ($aboutImagePath === '' || !file_exists(__DIR__ . '/' . $aboutImagePath)) {
    $aboutImagePath = $aboutDefaults['about_image'];
}

$pageTitle = $aboutPageData['hero_title'];
$breadcrumbActive = $aboutPageData['hero_subtitle'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= isset($pageTitle) ? $pageTitle : 'About Us' ?> - Multizone Travels</title>

    <?php include('headerlinks.php'); ?>
    <style>
        .about-section {
            background: #fff;
            position: relative;
        }
        .about-image-wrapper {
            position: relative;
            padding-right: 30px;
            padding-bottom: 30px;
            z-index: 1;
        }
        .about-image-wrapper::after {
            content: '';
            position: absolute;
            right: 0;
            bottom: 0;
            width: 80%;
            height: 80%;
            background: var(--primary-color);
            border-radius: 10px;
            z-index: -1;
            opacity: 0.1;
        }
        .about-img {
            position: relative;
            z-index: 1;
            border-radius: 10px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            width: 100%;
            height: auto;
            object-fit: cover;
        }
        .experience-badge {
            position: absolute;
            bottom: 10%;
            left: -20px;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid var(--primary-color);
        }
        .experience-badge h4 {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 28px;
            margin: 0;
        }
        .experience-badge p {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #555;
            line-height: 1.2;
        }
        .about-content h2 {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }
        .about-content .lead {
            color: var(--primary-color) !important;
            font-weight: 600;
            font-size: 18px;
        }
        .about-content p {
            color: #666;
            line-height: 1.8;
            margin-bottom: 20px;
        }
        .stats-box {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .stats-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .stats-icon {
            width: 50px;
            height: 50px;
            background: rgba(27, 130, 13, 0.1);
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .stats-info h5 {
            margin: 0;
            font-weight: 700;
            font-size: 22px;
            color: #333;
        }
        .stats-info span {
            color: #777;
            font-size: 13px;
            font-weight: 500;
        }
        
        .why-choose-card {
            background: #fff;
            padding: 40px 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        .why-choose-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 0;
            background: var(--primary-color);
            transition: all 0.4s ease;
            z-index: -1;
            opacity: 0.05;
        }
        .why-choose-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        .why-choose-card:hover::before {
            height: 100%;
        }
        .why-icon {
            width: 70px;
            height: 70px;
            background: rgba(27, 130, 13, 0.1);
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin: 0 auto 25px;
            transition: all 0.3s ease;
        }
        .why-choose-card:hover .why-icon {
            background: var(--primary-color);
            color: #fff;
            transform: scale(1.1);
        }
        .why-choose-card h4 {
            font-weight: 700;
            margin-bottom: 15px;
            color: #333;
        }
        .why-choose-card p {
            color: #666;
            line-height: 1.6;
            margin: 0;
        }
    </style>
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

        <!-- About Us Content -->
        <section class="about-section py-5">
            <div class="container py-4">
                <div class="row align-items-center">
                    <div class="col-lg-6 mb-5 mb-lg-0" data-aos="fade-right">
                        <div class="about-image-wrapper">
                            <img src="<?= htmlspecialchars($aboutImagePath) ?>" alt="About Multizone Travels" class="about-img" onerror="this.onerror=null;this.src='<?= htmlspecialchars($aboutDefaults['about_image']) ?>';">
                            <div class="experience-badge d-none d-md-flex">
                                <h4><?= htmlspecialchars($aboutPageData['experience_years']) ?></h4>
                                <p><?= nl2br(htmlspecialchars($aboutPageData['experience_text'])) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 ps-lg-5" data-aos="fade-left">
                        <div class="about-content">
                            <h2 class="section-title mb-3"><?= htmlspecialchars($aboutPageData['section_title']) ?></h2>
                            <p class="lead mb-4"><?= htmlspecialchars($aboutPageData['lead_text']) ?></p>
                            <p><?= htmlspecialchars($aboutPageData['description_1']) ?></p>
                            <p><?= htmlspecialchars($aboutPageData['description_2']) ?></p>
                            
                            <div class="row mt-5">
                                <div class="col-sm-6 mb-3 mb-sm-0">
                                    <div class="stats-box">
                                        <div class="stats-icon">
                                            <i class="fas fa-globe-americas"></i>
                                        </div>
                                        <div class="stats-info">
                                            <h5><?= htmlspecialchars($aboutPageData['stat_1_number']) ?></h5>
                                            <span><?= htmlspecialchars($aboutPageData['stat_1_label']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="stats-box">
                                        <div class="stats-icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="stats-info">
                                            <h5><?= htmlspecialchars($aboutPageData['stat_2_number']) ?></h5>
                                            <span><?= htmlspecialchars($aboutPageData['stat_2_label']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Why Choose Us Section -->
        <section class="why-choose-us py-5 bg-light">
            <div class="container py-4">
                <div class="text-center mb-5" data-aos="fade-up">
                    <h2 class="section-title"><?= htmlspecialchars($aboutPageData['why_title']) ?></h2>
                    <p class="section-subtitle"><?= htmlspecialchars($aboutPageData['why_subtitle']) ?></p>
                </div>
                <div class="row g-4">
                    <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="why-choose-card text-center">
                            <div class="why-icon">
                                <i class="fas fa-map-marked-alt"></i>
                            </div>
                            <h4><?= htmlspecialchars($aboutPageData['why_1_title']) ?></h4>
                            <p><?= htmlspecialchars($aboutPageData['why_1_text']) ?></p>
                        </div>
                    </div>
                    <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="why-choose-card text-center">
                            <div class="why-icon">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <h4><?= htmlspecialchars($aboutPageData['why_2_title']) ?></h4>
                            <p><?= htmlspecialchars($aboutPageData['why_2_text']) ?></p>
                        </div>
                    </div>
                    <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                        <div class="why-choose-card text-center">
                            <div class="why-icon">
                                <i class="fas fa-headset"></i>
                            </div>
                            <h4><?= htmlspecialchars($aboutPageData['why_3_title']) ?></h4>
                            <p><?= htmlspecialchars($aboutPageData['why_3_text']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Call to Action -->
        <section class="cta-section py-5">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-8" data-aos="fade-right">
                        <h2 class="text-black mb-3"><?= htmlspecialchars($aboutPageData['cta_title']) ?></h2>
                        <p class="text-black mb-0"><?= htmlspecialchars($aboutPageData['cta_text']) ?></p>
                    </div>
                    <div class="col-lg-4 text-lg-end" data-aos="fade-left">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#enquiryModal" class="btn btn-light btn-lg">
                            <i class="fas fa-paper-plane me-2"></i> <?= htmlspecialchars($aboutPageData['cta_button_text']) ?>
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