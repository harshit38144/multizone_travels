<?php
include('admin/connection.php');

$hasSectionCardsTable = false;
$cardsTableCheck = $conn->query("SHOW TABLES LIKE 'page_section_cards'");
if ($cardsTableCheck && $cardsTableCheck->num_rows > 0) {
    $hasSectionCardsTable = true;
}

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug)) {
    header("Location: index.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM pages WHERE slug = ? AND is_published = 1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    header("Location: index.php");
    exit;
}

$page = $res->fetch_assoc();
$pageTitle = !empty($page['meta_title']) ? $page['meta_title'] : $page['title'];
$metaDesc = $page['meta_description'];
$breadcrumbActive = $page['title'];
$featured_image = !empty($page['featured_image']) ? $page['featured_image'] : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <?php if(!empty($metaDesc)): ?>
    <meta name="description" content="<?= htmlspecialchars($metaDesc) ?>">
    <?php endif; ?>

    <title><?= htmlspecialchars($pageTitle) ?> - Multizone Travels</title>

    <?php include('headerlinks.php'); ?>
    
    <style>
        .dynamic-page-content {
            font-size: 16px;
            line-height: 1.8;
            color: #666;
        }
        .dynamic-page-content h2, .dynamic-page-content h3, .dynamic-page-content h4 {
            color: #333;
            margin-top: 30px;
            margin-bottom: 15px;
            font-weight: 700;
        }
        .dynamic-page-content img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            margin: 20px 0;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
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
        .about-image-wrapper.reverse::after {
            right: auto;
            left: 0;
        }
        .about-image-wrapper.reverse {
            padding-right: 0;
            padding-left: 30px;
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
        .page-featured-img {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }
        .dynamic-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            padding: 20px;
            height: 100%;
        }
        .dynamic-card h3 {
            font-size: 22px;
            margin-bottom: 12px;
            color: #222;
            font-weight: 700;
        }
        .dynamic-card .card-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 14px;
            box-shadow: none;
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
                        <h1 class="page-title" data-aos="fade-up"><?= htmlspecialchars($page['title']) ?></h1>
                        <nav aria-label="breadcrumb" data-aos="fade-up" data-aos-delay="100">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                                <li class="breadcrumb-item active"><?= htmlspecialchars($breadcrumbActive) ?></li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </section>

        <!-- Page Content -->
        <?php if(!empty($page['content']) || (!empty($featured_image) && file_exists($featured_image))): ?>
        <section class="py-5">
            <div class="container py-4">
                <div class="row align-items-center">
                    <?php if(!empty($featured_image) && file_exists($featured_image)): ?>
                        <div class="col-lg-6 mb-5 mb-lg-0" data-aos="fade-right">
                            <div class="about-image-wrapper">
                                <img src="<?= htmlspecialchars($featured_image) ?>" alt="<?= htmlspecialchars($page['title']) ?>" class="about-img">
                            </div>
                        </div>
                        <div class="col-lg-6 ps-lg-5" data-aos="fade-left">
                            <div class="dynamic-page-content">
                                <?= $page['content'] ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="col-lg-10 mx-auto" data-aos="fade-up">
                            <div class="dynamic-page-content text-center">
                                <?= $page['content'] ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php
        // Check if main page has featured image to determine starting layout for sections
        $has_featured_image = (!empty($featured_image) && file_exists($featured_image));
        
        // Fetch page sections
        $sec_res = $conn->query("SELECT * FROM page_sections WHERE page_id = {$page['id']} ORDER BY display_order ASC");
        if ($sec_res && $sec_res->num_rows > 0) {
            $sections = [];
            while ($row = $sec_res->fetch_assoc()) {
                $sections[] = $row;
            }
            $sec_count = $has_featured_image ? 1 : 0; // If main page has image on left, start sections with image on right

            $cardGroupOpen = false;
            $cardGroupKey = '';
            foreach ($sections as $sec) {
                $layoutType = $sec['layout_type'] ?? 'split';
                $cardsPerRow = isset($sec['cards_per_row']) ? (int)$sec['cards_per_row'] : 3;
                if (!in_array($cardsPerRow, [2, 3, 4, 5])) {
                    $cardsPerRow = 3;
                }
                $cardType = $sec['card_content_type'] ?? 'both';
                if (!in_array($cardType, ['text', 'image', 'both'])) {
                    $cardType = 'both';
                }

                if ($layoutType === 'cards') {
                    $groupKey = $cardsPerRow . '|' . $cardType;
                    if (!$cardGroupOpen || $groupKey !== $cardGroupKey) {
                        if ($cardGroupOpen) {
                            echo '</div></div></section>';
                        }
                        $cardGroupOpen = true;
                        $cardGroupKey = $groupKey;
                        echo '<section class="py-5 bg-light"><div class="container py-4"><div class="row g-4">';
                    }

                    $colClass = 'col-md-4';
                    if ($cardsPerRow === 2) {
                        $colClass = 'col-md-6';
                    } elseif ($cardsPerRow === 4) {
                        $colClass = 'col-md-6 col-lg-3';
                    } elseif ($cardsPerRow === 5) {
                        $colClass = 'col-6 col-md-4 col-lg';
                    }
                    $sectionCards = [];
                    if ($hasSectionCardsTable) {
                        $cardsRes = $conn->query("SELECT * FROM page_section_cards WHERE section_id=" . (int)$sec['id'] . " ORDER BY display_order ASC, id ASC");
                        if ($cardsRes) {
                            while ($c = $cardsRes->fetch_assoc()) {
                                $sectionCards[] = $c;
                            }
                        }
                    }
                    if (empty($sectionCards)) {
                        $sectionCards[] = [
                            'card_title' => $sec['title'],
                            'card_content' => $sec['content'],
                            'card_image' => $sec['image']
                        ];
                    }

                    foreach ($sectionCards as $card):
                    ?>
                    <div class="<?= $colClass ?>" data-aos="fade-up">
                        <div class="dynamic-card">
                            <?php if (($cardType === 'image' || $cardType === 'both') && !empty($card['card_image']) && file_exists($card['card_image'])): ?>
                                <img src="<?= htmlspecialchars($card['card_image']) ?>" alt="<?= htmlspecialchars($card['card_title'] ?? '') ?>" class="card-image">
                            <?php endif; ?>
                            <?php if ($cardType !== 'image' && !empty($card['card_title'])): ?>
                                <h3><?= htmlspecialchars($card['card_title']) ?></h3>
                            <?php endif; ?>
                            <?php if ($cardType !== 'image'): ?>
                                <div class="dynamic-page-content">
                                    <?= $card['card_content'] ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach;
                    continue;
                }

                if ($cardGroupOpen) {
                    echo '</div></div></section>';
                    $cardGroupOpen = false;
                    $cardGroupKey = '';
                }

                $bg_class = ($sec_count % 2 == 0) ? 'bg-white' : 'bg-light';
                $is_reverse = ($sec_count % 2 != 0); // true means image on right for desktop
                ?>
                <section class="py-5 <?= $bg_class ?>">
                    <div class="container py-4">
                        <div class="row align-items-center <?= $is_reverse ? 'flex-lg-row-reverse' : '' ?>">
                            <?php if(!empty($sec['image']) && file_exists($sec['image'])): ?>
                                <div class="col-lg-6 mb-5 mb-lg-0" data-aos="<?= $is_reverse ? 'fade-left' : 'fade-right' ?>">
                                    <div class="about-image-wrapper <?= $is_reverse ? 'reverse' : '' ?>">
                                        <img src="<?= htmlspecialchars($sec['image']) ?>" alt="<?= htmlspecialchars($sec['title']) ?>" class="about-img">
                                    </div>
                                </div>
                                <div class="col-lg-6 <?= $is_reverse ? 'pe-lg-5' : 'ps-lg-5' ?>" data-aos="<?= $is_reverse ? 'fade-right' : 'fade-left' ?>">
                            <?php else: ?>
                                <div class="col-12 text-center" data-aos="fade-up">
                            <?php endif; ?>
                            
                                <?php if(!empty($sec['title'])): ?>
                                    <h2 class="section-title mb-4"><?= htmlspecialchars($sec['title']) ?></h2>
                                <?php endif; ?>
                                
                                <div class="dynamic-page-content">
                                    <?= $sec['content'] ?>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </section>
                <?php
                $sec_count++;
            }

            if ($cardGroupOpen) {
                echo '</div></div></section>';
            }
        }
        ?>

        <!-- Call to Action -->
        <section class="cta-section py-5">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-8" data-aos="fade-right">
                        <h2 class="text-black mb-3">Ready to Start Your Journey?</h2>
                        <p class="text-black mb-0">Contact us today and let's plan your dream vacation together!</p>
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