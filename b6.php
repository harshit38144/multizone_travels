<?php
include('admin/connection.php');

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $gdSql = "SELECT * FROM group_departures WHERE id = $id AND status='Published'";
    $gdResult = $conn->query($gdSql);
    if ($gdResult && $gdResult->num_rows > 0) {
        $gd = $gdResult->fetch_assoc();

        // Fetch Dates
        $dates = [];
        $dateSql = "SELECT departure_date FROM group_departure_dates WHERE group_departure_id = $id AND departure_date >= CURDATE() ORDER BY departure_date ASC";
        $dateRes = $conn->query($dateSql);
        if ($dateRes) {
            while ($row = $dateRes->fetch_assoc()) {
                $dates[] = $row['departure_date'];
            }
        }

        // Fetch Hotels
        $hotels = [];
        $hotelSql = "SELECT * FROM group_departure_hotels WHERE group_departure_id = $id";
        $hotelRes = $conn->query($hotelSql);
        if ($hotelRes) {
            while ($row = $hotelRes->fetch_assoc()) {
                $hotels[] = $row;
            }
        }

        // Decode gallery
        $gallery = !empty($gd['gallery_images']) ? json_decode($gd['gallery_images'], true) : [];
        if (!is_array($gallery))
            $gallery = [];
        if (!empty($gd['featured_image'])) {
            array_unshift($gallery, $gd['featured_image']);
        }
    } else {
        header("Location: index.php");
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title>Multizone Travels</title>



    <?php include('headerlinks.php'); ?>
</head>

<body>

    <!-- Main Header with Logo and Menu -->
    <?php include('header1.php'); ?>

    <!-- Multi-Step Enquiry Modal -->
    <?php include('enquiry_modal.php'); ?>

    <!-- Main Content Wrapper -->
    <main>



        <style>
            /* Group Departure Detail Page Styles */
            .gd-detail-hero {
                position: relative;
                background: #1a1a2e;
            }

            .gd-gallery-main {
                height: 400px;
                border-radius: 12px;
                overflow: hidden;
            }

            .gd-gallery-main img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .gd-gallery-thumbs {
                display: flex;
                gap: 8px;
                margin-top: 8px;
            }

            .gd-gallery-thumb {
                width: 120px;
                height: 80px;
                border-radius: 8px;
                overflow: hidden;
                cursor: pointer;
                border: 2px solid transparent;
                transition: border-color 0.3s;
            }

            .gd-gallery-thumb.active,
            .gd-gallery-thumb:hover {
                border-color: #f39c12;
            }

            .gd-gallery-thumb img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .gd-detail-title {
                font-size: 24px;
                font-weight: 800;
                color: #1a1a2e;
                margin-bottom: 5px;
            }

            .gd-detail-price-section {
                display: flex;
                align-items: baseline;
                gap: 10px;
                margin: 10px 0;
            }

            .gd-detail-price-old {
                font-size: 16px;
                color: #999;
                text-decoration: line-through;
            }

            .gd-detail-price-current {
                font-size: 28px;
                font-weight: 800;
                color: #1a1a2e;
            }

            .gd-detail-price-label {
                font-size: 13px;
                color: #999;
            }

            .gd-detail-badges {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
                margin: 10px 0;
            }

            .gd-detail-badge {
                font-size: 12px;
                padding: 4px 12px;
                border-radius: 20px;
                background: #f0f0f0;
                color: #555;
            }

            .gd-detail-badge i {
                margin-right: 4px;
            }

            .gd-features-row {
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
                margin: 15px 0;
                font-size: 13px;
                color: #555;
            }

            .gd-features-row span i {
                margin-right: 5px;
                color: #888;
            }

            /* Booking sidebar */
            .gd-booking-card {
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
                padding: 25px;
                position: sticky;
                top: 100px;
            }

            .gd-booking-card h5 {
                font-weight: 700;
                margin-bottom: 15px;
            }

            .gd-seats-notice {
                font-size: 13px;
                color: #e74c3c;
                font-weight: 600;
                margin-bottom: 10px;
            }

            .gd-booking-date {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 10px 15px;
                margin-bottom: 15px;
            }

            .gd-booking-date .date-label {
                font-size: 12px;
                color: #666;
            }

            .gd-booking-date .date-value {
                font-weight: 700;
                font-size: 16px;
            }

            .gd-booking-date .seats-left {
                font-size: 12px;
                color: #27ae60;
                font-weight: 600;
            }

            .gd-traveler-counter {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 10px;
            }

            .gd-traveler-counter label {
                font-size: 13px;
                flex-grow: 1;
            }

            .gd-counter-btn {
                width: 30px;
                height: 30px;
                border: 1px solid #ddd;
                border-radius: 50%;
                background: #fff;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .gd-counter-value {
                width: 40px;
                text-align: center;
                font-weight: 700;
            }

            .gd-price-summary {
                border-top: 1px solid #eee;
                padding-top: 15px;
                margin-top: 15px;
            }

            .gd-price-summary .row {
                margin-bottom: 5px;
            }

            .gd-enquire-btn {
                background: #1a1a2e;
                color: #fff;
                width: 100%;
                padding: 12px;
                border: none;
                border-radius: 8px;
                font-weight: 700;
                font-size: 16px;
                cursor: pointer;
                margin-top: 15px;
            }

            .gd-enquire-btn:hover {
                background: #16213e;
                color: #fff;
            }

            /* Content sections */
            .gd-section-card {
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 2px 15px rgba(0, 0, 0, 0.06);
                margin-bottom: 20px;
                overflow: hidden;
            }

            .gd-section-header {
                padding: 15px 20px;
                border-bottom: 1px solid #f0f0f0;
                cursor: pointer;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .gd-section-header h5 {
                margin: 0;
                font-weight: 700;
                font-size: 16px;
            }

            .gd-section-header i.toggle-icon {
                transition: transform 0.3s;
            }

            .gd-section-body {
                padding: 20px;
            }

            .gd-inclusions-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0 30px;
            }

            .gd-inclusion-item,
            .gd-exclusion-item {
                padding: 6px 0;
                font-size: 14px;
            }

            .gd-inclusion-item i {
                color: #27ae60;
                margin-right: 8px;
            }

            .gd-exclusion-item i {
                color: #e74c3c;
                margin-right: 8px;
            }

            /* Flight card */
            .gd-flight-card {
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
            }

            .gd-flight-item {
                flex: 1;
                min-width: 200px;
                background: #f8f9fa;
                border-radius: 10px;
                padding: 15px;
                border-left: 4px solid;
            }

            .gd-flight-item.onward {
                border-color: #3498db;
            }

            .gd-flight-item.return {
                border-color: #e74c3c;
            }

            .gd-flight-item .flight-label {
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                margin-bottom: 5px;
            }

            .gd-flight-item.onward .flight-label {
                color: #3498db;
            }

            .gd-flight-item.return .flight-label {
                color: #e74c3c;
            }

            .gd-flight-item .flight-name {
                font-weight: 700;
            }

            .gd-flight-item .flight-route {
                font-size: 13px;
                color: #666;
            }

            /* Hotel card */
            .gd-hotel-item {
                display: flex;
                align-items: flex-start;
                gap: 15px;
                padding: 12px 0;
                border-bottom: 1px solid #f0f0f0;
            }

            .gd-hotel-item:last-child {
                border-bottom: none;
            }

            .gd-hotel-icon {
                width: 40px;
                height: 40px;
                background: #f0f0f0;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .gd-hotel-city-badge {
                background: #3498db;
                color: #fff;
                font-size: 11px;
                font-weight: 700;
                padding: 2px 8px;
                border-radius: 4px;
                margin-left: 8px;
            }

            .gd-hotel-name {
                font-weight: 700;
                font-size: 14px;
            }

            .gd-hotel-room {
                font-size: 13px;
                color: #666;
            }

            .gd-hotel-meal {
                font-size: 12px;
                color: #888;
            }

            .gd-hotel-meal i {
                margin-right: 4px;
            }

            /* Itinerary */
            .gd-itinerary-item {
                position: relative;
                padding-left: 30px;
                padding-bottom: 20px;
                border-left: 2px solid #e0e0e0;
                margin-left: 10px;
            }

            .gd-itinerary-item:last-child {
                border-left: none;
            }

            .gd-itinerary-dot {
                position: absolute;
                left: -8px;
                top: 0;
                width: 14px;
                height: 14px;
                border-radius: 50%;
                background: #f39c12;
                border: 2px solid #fff;
                box-shadow: 0 0 0 2px #f39c12;
            }

            .gd-itinerary-day {
                font-weight: 800;
                color: #1a1a2e;
                font-size: 15px;
                margin-bottom: 5px;
            }

            .gd-itinerary-desc {
                font-size: 14px;
                color: #555;
                line-height: 1.6;
            }

            @media (max-width: 768px) {
                .gd-gallery-main {
                    height: 250px;
                }

                .gd-inclusions-grid {
                    grid-template-columns: 1fr;
                }

                .gd-booking-card {
                    position: static;
                    margin-top: 20px;
                }
            }
        </style>

        <!-- Gallery Section -->
        <section class="gd-detail-hero py-4" style="background:#f8f9fa;">
            <div class="container">
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Group
                                Departures</a></li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($gd['title']) ?></li>
                    </ol>
                </nav>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Main Gallery -->
                        <div class="gd-gallery-main" id="mainGallery">
                            <?php if (!empty($gallery)): ?>
                                <img src="admin/<?= htmlspecialchars($gallery[0]) ?>"
                                    alt="<?= htmlspecialchars($gd['title']) ?>">
                            <?php else: ?>
                                <div class="gd-card-placeholder"
                                    style="height:100%; display:flex; align-items:center; justify-content:center; background:#eee;">
                                    <i class="fas fa-image fa-3x text-muted"></i></div>
                            <?php endif; ?>
                        </div>

                        <?php if (count($gallery) > 1): ?>
                            <div class="gd-gallery-thumbs">
                                <?php foreach ($gallery as $idx => $img): ?>
                                    <div class="gd-gallery-thumb <?= $idx === 0 ? 'active' : '' ?>"
                                        onclick="changeImage('admin/<?= htmlspecialchars($img) ?>', this)">
                                        <img src="admin/<?= htmlspecialchars($img) ?>" alt="thumb">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Booking Sidebar -->
                    <div class="col-lg-4">
                        <div class="gd-booking-card">
                            <h5>Choose Your Departure</h5>

                            <?php if ($gd['seats_available'] <= 15): ?>
                                <div class="gd-seats-notice">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Only <?= $gd['seats_available'] ?> seats left!
                                </div>
                            <?php endif; ?>

                            <div class="gd-booking-date">
                                <div class="date-label">Departure Dates</div>
                                <div class="d-flex flex-wrap gap-2 mt-1">
                                    <?php foreach ($dates as $d): ?>
                                        <span class="badge bg-primary" style="font-size:13px;padding:6px 10px;">
                                            <?= date('d M Y', strtotime($d)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="seats-left mt-1"><?= $gd['seats_available'] ?> seats left</div>
                            </div>

                            <!-- Traveler counter -->
                            <div class="mb-3">
                                <label class="form-label fw-bold" style="font-size:13px;">Number of Travelers</label>
                                <div class="gd-traveler-counter">
                                    <label>Traveler</label>
                                    <button type="button" class="gd-counter-btn"
                                        onclick="updateCount('travelers', -1)">-</button>
                                    <span class="gd-counter-value" id="travelers-count">2</span>
                                    <button type="button" class="gd-counter-btn"
                                        onclick="updateCount('travelers', 1)">+</button>
                                </div>
                                <div class="gd-traveler-counter">
                                    <label>Infant</label>
                                    <button type="button" class="gd-counter-btn"
                                        onclick="updateCount('infants', -1)">-</button>
                                    <span class="gd-counter-value" id="infants-count">0</span>
                                    <button type="button" class="gd-counter-btn"
                                        onclick="updateCount('infants', 1)">+</button>
                                </div>
                            </div>

                            <?php
                            $price = $gd['price'];
                            $discounted = $gd['discounted_price'];
                            $currentPrice = ($discounted > 0 && $discounted < $price) ? $discounted : $price;
                            ?>
                            <!-- Price summary -->
                            <div class="gd-price-summary">
                                <div class="d-flex justify-content-between mb-1">
                                    <span style="font-size:13px;color:#666;">Price per person</span>
                                    <span style="font-weight:700;">Rs.<?= number_format($currentPrice) ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span style="font-weight:700;">Total (<span id="total-pax">2</span> pax)</span>
                                    <span style="font-weight:800;font-size:18px;color:#1a1a2e;" id="total-price"
                                        data-unit-price="<?= $currentPrice ?>">
                                        Rs.<?= number_format($currentPrice * 2) ?>
                                    </span>
                                </div>
                            </div>

                            <a href="#" class="gd-enquire-btn d-block text-center text-decoration-none"
                                data-bs-toggle="modal" data-bs-target="#enquiryModal" onclick="prefillEnquiry()">Enquire
                                Now</a>

                            <div class="text-center mt-3">
                                <a href="https://wa.me/?text=Check+out+this+group+departure%3A+<?= urlencode($gd['title']) ?>+-+<?= urlencode("b6.php?id=" . $id) ?>"
                                    target="_blank" class="btn btn-outline-success btn-sm w-100 mb-2">
                                    <i class="fab fa-whatsapp"></i> Share with Customer
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Title & Quick Info -->
        <section class="py-4">
            <div class="container">
                <div class="row">
                    <div class="col-lg-8">
                        <h1 class="gd-detail-title"><?= htmlspecialchars($gd['title']) ?></h1>

                        <div class="gd-detail-price-section">
                            <span class="gd-detail-price-label">Starting from</span>
                            <?php if ($discounted > 0 && $discounted < $price): ?>
                                <span class="gd-detail-price-old">Rs.<?= number_format($price) ?></span>
                            <?php endif; ?>
                            <span class="gd-detail-price-current">Rs.<?= number_format($currentPrice) ?></span>
                            <span class="gd-detail-price-label">per person</span>
                        </div>

                        <div class="gd-detail-badges">
                            <?php if ($gd['duration_nights'] > 0 || $gd['duration_days'] > 0): ?>
                                <span class="gd-detail-badge" style="background:#e8f4fd;color:#2980b9;">
                                    <?= $gd['duration_nights'] ?>N/<?= $gd['duration_days'] ?>D
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($gd['city_nights_breakdown'])): ?>
                                <span class="gd-detail-badge"><?= htmlspecialchars($gd['city_nights_breakdown']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="gd-features-row">
                            <?php if ($gd['is_flight_included']): ?>
                                <span><i class="fas fa-plane"></i> Flight Included</span>
                            <?php endif; ?>
                            <?php if ($gd['is_meals_included']): ?>
                                <span><i class="fas fa-utensils"></i> Meals Included</span>
                            <?php endif; ?>
                            <?php if (!empty($gd['departure_day'])): ?>
                                <span><i class="fas fa-calendar"></i> <?= htmlspecialchars($gd['departure_day']) ?>
                                    Departures</span>
                            <?php endif; ?>
                        </div>

                        <!-- What's inside the package -->
                        <div class="gd-section-card">
                            <div class="gd-section-header" data-bs-toggle="collapse"
                                data-bs-target="#inclusionsSection">
                                <h5>What's inside the package?</h5>
                                <i class="fas fa-chevron-up toggle-icon"></i>
                            </div>
                            <div class="collapse show" id="inclusionsSection">
                                <div class="gd-section-body">
                                    <div class="gd-inclusions-grid">
                                        <?php if (!empty($gd['inclusions'])): ?>
                                            <div>
                                                <h6 class="fw-bold mb-3" style="color:#27ae60;">Inclusions</h6>
                                                <div class="gd-inclusions-list">
                                                    <?= $gd['inclusions'] ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($gd['exclusions'])): ?>
                                            <div>
                                                <h6 class="fw-bold mb-3" style="color:#e74c3c;">Exclusions</h6>
                                                <div class="gd-exclusions-list">
                                                    <?= $gd['exclusions'] ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Flight Details -->
                        <?php if ($gd['is_flight_included'] && (!empty($gd['onward_flight_name']) || !empty($gd['return_flight_name']))): ?>
                            <div class="gd-section-card">
                                <div class="gd-section-header" data-bs-toggle="collapse" data-bs-target="#flightSection">
                                    <h5><i class="fas fa-plane me-2"></i> Flight Details</h5>
                                    <i class="fas fa-chevron-up toggle-icon"></i>
                                </div>
                                <div class="collapse show" id="flightSection">
                                    <div class="gd-section-body">
                                        <div class="gd-flight-card">
                                            <?php if (!empty($gd['onward_flight_name'])): ?>
                                                <div class="gd-flight-item onward">
                                                    <div class="flight-label"><i class="fas fa-plane-departure"></i> Onward
                                                    </div>
                                                    <div class="flight-name"><?= htmlspecialchars($gd['onward_flight_name']) ?>
                                                    </div>
                                                    <div class="flight-route"><?= htmlspecialchars($gd['onward_route']) ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($gd['return_flight_name'])): ?>
                                                <div class="gd-flight-item return">
                                                    <div class="flight-label"><i class="fas fa-plane-arrival"></i> Return</div>
                                                    <div class="flight-name"><?= htmlspecialchars($gd['return_flight_name']) ?>
                                                    </div>
                                                    <div class="flight-route"><?= htmlspecialchars($gd['return_route']) ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Hotels & Meal Plan -->
                        <?php if (!empty($hotels)): ?>
                            <div class="gd-section-card">
                                <div class="gd-section-header" data-bs-toggle="collapse" data-bs-target="#hotelSection">
                                    <h5><i class="fas fa-hotel me-2"></i> Hotels & Meals</h5>
                                    <i class="fas fa-chevron-up toggle-icon"></i>
                                </div>
                                <div class="collapse show" id="hotelSection">
                                    <div class="gd-section-body">
                                        <?php foreach ($hotels as $h): ?>
                                            <div class="mb-3 border-bottom pb-2">
                                                <div class="gd-hotel-name"><?= htmlspecialchars($h['hotel_name']) ?>
                                                    (<?= htmlspecialchars($h['city']) ?> - <?= $h['nights'] ?> Nights)</div>
                                                <div class="gd-hotel-room">Room: <?= htmlspecialchars($h['room_type']) ?></div>
                                                <div class="gd-hotel-meal"><i class="fas fa-utensils"></i> Meal Plan:
                                                    <?= htmlspecialchars($h['meal_plan']) ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Itinerary -->
                        <?php if (!empty($gd['highlights'])): ?>
                            <div class="gd-section-card">
                                <div class="gd-section-header" data-bs-toggle="collapse" data-bs-target="#itinerarySection">
                                    <h5><i class="fas fa-map-marked-alt me-2"></i> Tour Itinerary</h5>
                                    <i class="fas fa-chevron-up toggle-icon"></i>
                                </div>
                                <div class="collapse show" id="itinerarySection">
                                    <div class="gd-section-body">
                                        <div class="gd-itinerary-desc">
                                            <?= $gd['highlights'] ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Payment & Cancellation Policy -->
                        <div class="gd-section-card">
                            <div class="gd-section-header" data-bs-toggle="collapse" data-bs-target="#policySection">
                                <h5><i class="fas fa-file-contract me-2"></i> Payment & Cancellation Policy</h5>
                                <i class="fas fa-chevron-up toggle-icon"></i>
                            </div>
                            <div class="collapse show" id="policySection">
                                <div class="gd-section-body">
                                    <!-- Assuming policy is standard or from settings. For now we will keep static or if there are settings, you can add them. -->
                                    <h6 class="fw-bold">Payment Policy</h6>
                                    <div class="mb-3">30% advance to confirm booking. Balance 30 days before departure.
                                    </div>
                                    <h6 class="fw-bold">Cancellation Policy</h6>
                                    <div>Cancellation 30+ days: 10% charge. 15-29 days: 25% charge. Less than 15 days:
                                        50% charge. No show: 100% charge.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Enquiry Section -->
                        <div class="gd-section-card" id="enquiry-section">
                            <div class="gd-section-header">
                                <h5><i class="fas fa-envelope me-2"></i> Interested in This Package?</h5>
                            </div>
                            <div class="gd-section-body text-center py-4">
                                <p class="text-muted mb-3">Send us an enquiry and our travel experts will get back to
                                    you shortly.</p>
                                <div class="d-grid gap-2" style="max-width: 400px; margin: 0 auto;">
                                    <a href="#" class="btn btn-primary btn-lg" data-bs-toggle="modal"
                                        data-bs-target="#enquiryModal" onclick="prefillEnquiry()">
                                        <i class="fas fa-paper-plane me-2"></i> Send Enquiry
                                    </a>
                                    <a href="https://wa.me/1125425642?text=Hi%2C+I+am+interested+in+the+group+departure%3A+<?= urlencode($gd['title']) ?>"
                                        target="_blank" class="btn btn-success btn-lg">
                                        <i class="fab fa-whatsapp me-2"></i> WhatsApp Us
                                    </a>
                                    <a href="tel:+91 9709400140" class="btn btn-outline-primary btn-lg">
                                        <i class="fas fa-phone me-2"></i> +91 9709400140
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <script>
            // Gallery image switcher
            function changeImage(src, thumb) {
                var mainGallery = document.getElementById('mainGallery');
                mainGallery.innerHTML = '<img src="' + src + '" alt="Image">';
                document.querySelectorAll('.gd-gallery-thumb').forEach(t => t.classList.remove('active'));
                thumb.classList.add('active');
            }

            // Traveler counter
            var pricePerPerson = <?= $currentPrice ?>;

            function updateCount(type, delta) {
                var el = document.getElementById(type + '-count');
                var current = parseInt(el.textContent);
                var newVal = Math.max(type === 'travelers' ? 1 : 0, current + delta);
                el.textContent = newVal;

                var travelers = parseInt(document.getElementById('travelers-count').textContent);
                document.getElementById('total-pax').textContent = travelers;
                document.getElementById('total-price').innerHTML = 'Rs.' + (pricePerPerson * travelers).toLocaleString('en-IN');
            }

            // Collapsible sections
            document.querySelectorAll('.gd-section-header[data-bs-toggle="collapse"]').forEach(function (header) {
                header.addEventListener('click', function () {
                    var icon = this.querySelector('.toggle-icon');
                    if (icon) {
                        icon.classList.toggle('fa-chevron-up');
                        icon.classList.toggle('fa-chevron-down');
                    }
                });
            });

            // Pre-fill the shared enquiry modal with group departure info
            function prefillEnquiry() {
                var destField = document.getElementById('enq-destination');
                var msgField = document.getElementById('enq-message');
                var titleField = document.getElementById('enq-package-title');
                var urlField = document.getElementById('enq-package-url');
                var imageField = document.getElementById('enq-package-image');

                if (destField) destField.value = '<?= addslashes(htmlspecialchars($gd['destination_cities'])) ?>';
                if (msgField && !msgField.value) msgField.value = 'Group Departure: <?= addslashes(htmlspecialchars($gd['title'])) ?>';
                if (titleField) titleField.value = '<?= addslashes(htmlspecialchars($gd['title'])) ?>';
                if (urlField) urlField.value = window.location.href;
                <?php if(!empty($gallery) && isset($gallery[0])): ?>
                if (imageField) imageField.value = 'admin/<?= addslashes($gallery[0]) ?>';
                <?php endif; ?>
            }
        </script>

    </main>
    <!-- End Main Content -->

    <?php include('footer.php'); ?>
    <?php include('footerlinks.php'); ?>



</body></html>