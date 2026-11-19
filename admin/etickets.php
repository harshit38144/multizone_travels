<?php
include "connection.php";

$editData = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $res = mysqli_query($conn, "SELECT * FROM saved_tickets WHERE id=$edit_id");
    if ($res) {
        $editData = mysqli_fetch_assoc($res);
    }
}

$banner = "img/a8.png";

// Ensure image_master exists (local/dev DBs may be missing it)
$conn->query("CREATE TABLE IF NOT EXISTS `image_master` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Active',
  `created_by` VARCHAR(100) DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `ticket_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `important_info` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$tsCheck = mysqli_query($conn, "SELECT id FROM ticket_settings LIMIT 1");
if ($tsCheck && mysqli_num_rows($tsCheck) === 0) {
    mysqli_query($conn, "INSERT INTO ticket_settings (id, important_info) VALUES (1, '<li>Please carry a valid photo ID.</li><li>Report at the airport at least 2 hours before departure.</li>')");
}

$paxJsonCol = mysqli_query($conn, "SHOW COLUMNS FROM `saved_tickets` LIKE 'passengers_json'");
if ($paxJsonCol && mysqli_num_rows($paxJsonCol) === 0) {
    mysqli_query($conn, "ALTER TABLE `saved_tickets` ADD `passengers_json` LONGTEXT NULL AFTER `passenger_names`");
}

$editPassengers = [];
if ($editData && !empty($editData['passengers_json'])) {
    $decodedPassengers = json_decode((string) $editData['passengers_json'], true);
    if (is_array($decodedPassengers)) {
        $editPassengers = $decodedPassengers;
    }
}

$sql = "SELECT image FROM image_master 
WHERE status='Active' 
AND is_deleted=0
LIMIT 1";

$res = mysqli_query($conn, $sql);

if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    $banner = "uploads/images/" . $row['image'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi Zone Travels - Flight E-Ticket</title>
    <?php include 'includes/header-links.php'; ?>
    <style>
        /* A4 Sheet Style - exactly like image */
        .ticket {
            max-width: 210mm;
            width: 100%;
            background: white;
            padding: 15px 20px 20px 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            margin: 0 auto;
        }

        /* Header section */
        .header {
            /* display: flex; */
            /* justify-content: space-between; */
            /* align-items: center; */
            border-bottom: 2px solid #e31b23;
            padding-bottom: 12px;
            margin-bottom: 18px;
            /* text-align: center; */
        }

        /* .header .col-md-4 {
            display: flex;
            justify-content: center;
            align-items: center;
        } */

        .logo {
            font-size: 22px;
            font-weight: bold;
            color: #1a1a2e;
        }

        .logo span {
            color: #e31b23;
        }

        .booked-on {
            font-size: 13px;
            color: #333;
        }

        /* Support Row - exactly as image */
        .support-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            align-items: stretch;
        }

        .support-box {
            text-align: center;
            flex: 1;
            border-right: 1px solid #ccc;
            padding: 5px 0;
        }

        .support-box:last-child {
            border-right: none;
        }

        .support-box .title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #e31b23;
        }

        .phone {
            font-size: 18px;
            font-weight: bold;
            color: #000000ff;
            white-space: nowrap;
            display: inline-block;
        }

        #pnrBox {
            border: 2px solid #999;
            padding: 4px 35px;
            text-align: center;
            font-weight: bold;
            font-size: 20px;
            color: #e31b23;
            background: white;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Flight Section Title */
        .flight-title {
            background: #e31b23;
            color: white;
            padding: 3px 12px;
            font-weight: bold;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            /* margin-top: 5px; */
            align-items: center;
        }

        .verify-text {
            font-size: 11px;
            font-weight: normal;
        }

        /* Passenger Table */
        .passenger-head {
            background: #666;
            color: white;
            padding: 3px 12px;
            font-weight: bold;
            font-size: 13px;
            margin-top: 15px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .data-table th,
        .data-table td {
            /* border: 1px solid #ccc; */
            padding: 3px 10px;
            text-align: left;
        }

        .data-table th {
            background: #d2d2d2;
            font-weight: bold;
        }

        #passengerTable .col-sr-no {
            width: 42px;
            max-width: 42px;
            white-space: nowrap;
            text-align: center;
            padding-left: 4px;
            padding-right: 4px;
        }

        /* New Flight Details Card Styles */
        .flight-container {
            margin-top: 10px;
            border: 1px solid #e31b23;
        }

        .flight-card {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            border-bottom: 1px solid #eee;
        }

        .flight-card:last-child {
            border-bottom: none;
        }

        .flight-card-header {
            background: #d2d2d2;
            display: grid;
            grid-template-columns: 150px 1fr 1fr auto;
            gap: 8px 20px;
            align-items: center;
            padding: 4px 15px;
            font-size: 13px;
        }

        .flight-card-header .flight-num {
            color: #e31b23;
            font-weight: bold;
            padding-left: 10px;
        }

        .flight-card-header .header-label {
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
            padding-left: 6px;
        }

        .flight-card-body {
            padding: 4px 15px;
            display: grid;
            grid-template-columns: 150px 1fr 1fr auto;
            gap: 8px 20px;
            align-items: flex-start;
        }

        .flight-info-group {
            min-width: 0;
        }

        .flight-info-group.airline-info {
            max-width: 125px;
            padding-left: 10px;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .flight-info-group.location-info {
            padding-left: 8px;
        }

        .airline-info .airline-name {
            font-weight: bold;
            font-size: 14px;
            line-height: 1.25;
            margin-bottom: 2px;
        }

        .airline-info .terminal {
            font-size: 11px;
            color: #666;
        }

        .airline-info .flight-code {
            font-weight: bold;
            font-size: 13px;
            margin-top: 2px;
        }

        .location-info .city {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .location-info .datetime {
            font-weight: bold;
            font-size: 13px;
        }

        .flight-meta-right {
            text-align: left;
            border-left: 1px solid #ccc;
            padding-left: 12px;
            min-width: 88px;
        }

        .flight-meta-right .stops {
            font-size: 13px;
            color: #333;
        }

        .flight-meta-right .duration {
            font-size: 12px;
            color: #666;
        }

        .baggage-info {
            padding: 0 15px 3px 25px;
            font-size: 11px;
            color: #555;
        }

        /* Flight search loading popup (namespaced loader) */
        #flightSearchLoadingOverlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 20050;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.48);
            padding: 1rem;
        }
        #flightSearchLoadingOverlay.is-visible {
            display: flex;
        }
        body.flight-search-loading {
            overflow: hidden;
        }
        .flight-search-popup {
            width: min(640px, 94vw);
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28);
            padding: 0;
            margin: 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .flight-search-popup-stage {
            position: relative;
            height: 220px;
            margin: 0;
            padding: 0;
            overflow: hidden;
            border-radius: 0;
            background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
        }
        .flight-search-popup-title {
            margin: 0;
            padding: 0;
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.01em;
            background: #fff;
            line-height: 2.75rem;
        }
        .flight-search-loader {
            position: absolute;
            top: 50%;
            margin-left: -50px;
            left: 50%;
            animation: flight-search-speeder 0.4s linear infinite;
            z-index: 2;
        }
        .flight-search-loader > span {
            height: 5px;
            width: 35px;
            background: #000;
            position: absolute;
            top: -19px;
            left: 60px;
            border-radius: 2px 10px 1px 0;
        }
        .flight-search-base span {
            position: absolute;
            width: 0;
            height: 0;
            border-top: 6px solid transparent;
            border-right: 100px solid #000;
            border-bottom: 6px solid transparent;
        }
        .flight-search-base span:before {
            content: "";
            height: 22px;
            width: 22px;
            border-radius: 50%;
            background: #000;
            position: absolute;
            right: -110px;
            top: -16px;
        }
        .flight-search-base span:after {
            content: "";
            position: absolute;
            width: 0;
            height: 0;
            border-top: 0 solid transparent;
            border-right: 55px solid #000;
            border-bottom: 16px solid transparent;
            top: -16px;
            right: -98px;
        }
        .flight-search-face {
            position: absolute;
            height: 12px;
            width: 20px;
            background: #000;
            border-radius: 20px 20px 0 0;
            transform: rotate(-40deg);
            right: -125px;
            top: -15px;
        }
        .flight-search-face:after {
            content: "";
            height: 12px;
            width: 12px;
            background: #000;
            right: 4px;
            top: 7px;
            position: absolute;
            transform: rotate(40deg);
            transform-origin: 50% 50%;
            border-radius: 0 0 0 2px;
        }
        .flight-search-loader > span > span:nth-child(1),
        .flight-search-loader > span > span:nth-child(2),
        .flight-search-loader > span > span:nth-child(3),
        .flight-search-loader > span > span:nth-child(4) {
            width: 30px;
            height: 1px;
            background: #000;
            position: absolute;
            animation: flight-search-fazer1 0.2s linear infinite;
        }
        .flight-search-loader > span > span:nth-child(2) {
            top: 3px;
            animation: flight-search-fazer2 0.4s linear infinite;
        }
        .flight-search-loader > span > span:nth-child(3) {
            top: 1px;
            animation: flight-search-fazer3 0.4s linear infinite;
            animation-delay: -1s;
        }
        .flight-search-loader > span > span:nth-child(4) {
            top: 4px;
            animation: flight-search-fazer4 1s linear infinite;
            animation-delay: -1s;
        }
        @keyframes flight-search-fazer1 {
            0% { left: 0; }
            100% { left: -80px; opacity: 0; }
        }
        @keyframes flight-search-fazer2 {
            0% { left: 0; }
            100% { left: -100px; opacity: 0; }
        }
        @keyframes flight-search-fazer3 {
            0% { left: 0; }
            100% { left: -50px; opacity: 0; }
        }
        @keyframes flight-search-fazer4 {
            0% { left: 0; }
            100% { left: -150px; opacity: 0; }
        }
        @keyframes flight-search-speeder {
            0% { transform: translate(2px, 1px) rotate(0deg); }
            10% { transform: translate(-1px, -3px) rotate(-1deg); }
            20% { transform: translate(-2px, 0px) rotate(1deg); }
            30% { transform: translate(1px, 2px) rotate(0deg); }
            40% { transform: translate(1px, -1px) rotate(1deg); }
            50% { transform: translate(-1px, 3px) rotate(-1deg); }
            60% { transform: translate(-1px, 1px) rotate(0deg); }
            70% { transform: translate(3px, 1px) rotate(-1deg); }
            80% { transform: translate(-2px, -1px) rotate(1deg); }
            90% { transform: translate(2px, 1px) rotate(0deg); }
            100% { transform: translate(1px, -2px) rotate(-1deg); }
        }
        .flight-search-longfazers {
            position: absolute;
            width: 100%;
            height: 100%;
            left: 0;
            top: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: 1;
        }
        .flight-search-longfazers span {
            position: absolute;
            height: 2px;
            width: 20%;
            background: #000;
        }
        .flight-search-longfazers span:nth-child(1) {
            top: 20%;
            animation: flight-search-lf 0.6s linear infinite;
            animation-delay: -5s;
        }
        .flight-search-longfazers span:nth-child(2) {
            top: 40%;
            animation: flight-search-lf2 0.8s linear infinite;
            animation-delay: -1s;
        }
        .flight-search-longfazers span:nth-child(3) {
            top: 60%;
            animation: flight-search-lf3 0.6s linear infinite;
        }
        .flight-search-longfazers span:nth-child(4) {
            top: 80%;
            animation: flight-search-lf4 0.5s linear infinite;
            animation-delay: -3s;
        }
        @keyframes flight-search-lf {
            0% { left: 200%; }
            100% { left: -200%; opacity: 0; }
        }
        @keyframes flight-search-lf2 {
            0% { left: 200%; }
            100% { left: -200%; opacity: 0; }
        }
        @keyframes flight-search-lf3 {
            0% { left: 200%; }
            100% { left: -100%; opacity: 0; }
        }
        @keyframes flight-search-lf4 {
            0% { left: 200%; }
            100% { left: -100%; opacity: 0; }
        }

        /* Selected-flight animated ticket (Uiverse Cobp/clever-shrimp-61, namespaced) */
        #mzFlightTicketOverlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 20100;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.55);
            padding: 1rem;
            opacity: 0;
            transition: opacity 0.35s ease;
        }
        #mzFlightTicketOverlay.is-visible {
            display: flex;
            opacity: 1;
        }
        #mzFlightTicketOverlay.is-leaving {
            opacity: 0;
        }
        body.mz-flight-ticket-busy {
            overflow: hidden;
        }
        .mz-ft-ticket-wrap {
            perspective: 1000px;
            transform: scale(0.72);
            opacity: 0;
            transition: transform 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.25), opacity 0.35s ease;
        }
        #mzFlightTicketOverlay.is-visible .mz-ft-ticket-wrap {
            transform: scale(1.35);
            opacity: 1;
        }
        @media (max-width: 576px) {
            #mzFlightTicketOverlay.is-visible .mz-ft-ticket-wrap {
                transform: scale(1.05);
            }
        }
        .mz-ft-ticket-card {
            position: relative;
            height: 150px;
            width: 320px;
            display: flex;
            color: #2d2d2d;
            background-color: #ffffff;
            border-radius: 1rem;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.25);
            z-index: 10;
            gap: 1rem;
            animation: mz-ft-animation-card 10s infinite;
            overflow: hidden;
        }
        .mz-ft-ticket-card:hover {
            animation-play-state: paused;
            transform: translateY(-30px);
        }
        .mz-ft-ticket-card:hover .mz-ft-ticket-content::after,
        .mz-ft-ticket-card:hover .mz-ft-ticket-content::before,
        .mz-ft-ticket-card:hover .mz-ft-ticket-icons {
            animation-play-state: paused;
        }
        .mz-ft-ticket-separator {
            position: absolute;
            top: 0;
            left: 50px;
            width: 16px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            z-index: 10;
        }
        .mz-ft-ticket-span-lines {
            position: relative;
            display: flex;
            height: 100%;
            border-left: 2px dashed #e8e8e8;
        }
        .mz-ft-ticket-span-lines::after {
            content: "";
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 16px;
            height: 16px;
            border-radius: 16px;
            background-color: #e8e8e8;
        }
        .mz-ft-ticket-span-lines::before {
            content: "";
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 16px;
            height: 16px;
            border-radius: 1rem;
            background-color: #e8e8e8;
        }
        .mz-ft-ticket-content {
            position: relative;
            justify-content: space-between;
            width: 100%;
            display: flex;
        }
        .mz-ft-ticket-content::after {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            background-image: linear-gradient(90deg, rgba(0, 0, 0, 0), rgba(0, 0, 0, 0.8));
            transform: translateX(200px) scale(1.5);
            filter: blur(10px);
            width: 200px;
            height: 100%;
            animation: mz-ft-shadow-card 10s infinite;
        }
        .mz-ft-ticket-content::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100px;
            background-image: linear-gradient(90deg, rgba(0, 0, 0, 0), #ffffff, rgba(0, 0, 0, 0));
            transform: translateX(-100px) scale(1.5) rotate(20deg);
            width: 80px;
            height: 100%;
            animation: mz-ft-light-card 10s infinite;
        }
        .mz-ft-ticket-content-data {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 100%;
            padding: 0.625rem 0.75rem;
            gap: 0.25rem;
        }
        .mz-ft-ticket-data-flex,
        .mz-ft-ticket-data-flex-col {
            width: 100%;
            display: flex;
            justify-content: space-between;
            gap: 0.25rem;
        }
        .mz-ft-ticket-data-flex-col {
            padding-bottom: 0.25rem;
            flex-direction: column;
        }
        .mz-ft-ticket-data {
            font-size: 10px;
            line-height: 12px;
        }
        .mz-ft-ticket-data .mz-ft-ticket-title {
            color: #aeaeae;
            margin: 0;
        }
        .mz-ft-ticket-data .mz-ft-ticket-subtitle {
            font-weight: 500;
            color: #212121;
            margin: 0;
            min-height: 12px;
        }
        .mz-ft-ticket-destination {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .mz-ft-ticket-dest {
            display: flex;
            flex-direction: column;
        }
        .mz-ft-ticket-dest .mz-ft-ticket-country {
            font-size: 10px;
            line-height: 1;
            color: #aeaeae;
            margin: 0;
        }
        .mz-ft-ticket-dest .mz-ft-ticket-acronym {
            font-weight: 700;
            color: #2d2d2d;
            margin: 0;
        }
        .mz-ft-ticket-dest .mz-ft-ticket-hour {
            font-size: 10px;
            line-height: 12px;
            display: flex;
            align-items: center;
            gap: 4px;
            color: #2d2d2d;
            margin: 0;
        }
        .mz-ft-ticket-icons {
            width: fit-content;
            padding: 0.625rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            animation: mz-ft-color-card 10s infinite;
            z-index: -1;
        }
        .mz-ft-ticket-icon {
            color: #ffffff;
        }
        @keyframes mz-ft-animation-card {
            0% { transform: translateZ(0px); }
            25% { transform: translateX(-10px) rotateY(-20deg); }
            75% { transform: translateX(10px) rotateY(20deg); }
            100% { transform: translateZ(0px); }
        }
        @keyframes mz-ft-light-card {
            25% { transform: translateX(450px) scale(1.5) rotate(25deg); }
            50% { transform: translateX(-100px) scale(1.5) rotate(25deg); }
        }
        @keyframes mz-ft-shadow-card {
            35% { transform: translateX(200px) scale(1.5); }
            75% { transform: translateX(0px) scale(1.5); }
        }
        @keyframes mz-ft-color-card {
            0% { background-color: #ffbd44; }
            10% { background-color: #fb6902; }
            15%, 25% { background-color: #ff4141; }
            35% { background-color: #fb6902; }
            40% { background-color: #ffbd44; }
            50% { background-color: #fb6902; }
            55%, 75% { background-color: #ff4141; }
            75% { background-color: #fb6902; }
            100% { background-color: #ffbd44; }
        }
        @keyframes mz-ft-eticket-flash {
            0% { box-shadow: 0 0 0 0 rgba(227, 27, 35, 0.45); }
            60% { box-shadow: 0 0 0 10px rgba(227, 27, 35, 0); }
            100% { box-shadow: none; }
        }
        .mz-ft-eticket-populated {
            animation: mz-ft-eticket-flash 1.1s ease;
        }
        @media (prefers-reduced-motion: reduce) {
            .mz-ft-ticket-card,
            .mz-ft-ticket-content::after,
            .mz-ft-ticket-content::before,
            .mz-ft-ticket-icons {
                animation: none !important;
            }
            .mz-ft-ticket-wrap,
            #mzFlightTicketOverlay {
                transition: opacity 0.2s ease !important;
            }
            #mzFlightTicketOverlay.is-visible .mz-ft-ticket-wrap {
                transform: scale(1.15);
            }
        }

        /* Two column layout for Payment & Contact */
        .two-columns {
            display: flex;
            gap: 45px;
            margin: 20px 0;
            justify-content: space-between;
        }

        .payment-box,
        .contact-box {
            flex: 1;
            border: 1px solid #ccc;
            padding: 3px 15px;
            max-width: 100%;
        }

        .data-table th {
            font-weight: 500 !important;
        }

        /* .data-table td {
            font-weight: bold;
        } */

        .box-header {
            font-weight: bold;
            border-bottom: 1px solid #ccc;
            padding-bottom: 6px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }

        .amount-line {
            /* padding: 5px 0; */
            font-size: 13px;
            display: flex;
            justify-content: space-between;
        }

        .total-line {
            margin-top: 8px;
            padding-top: 6px;
            font-weight: bold;
            border-top: 1px dashed #aaa;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
        }

        .contact-detail {
            font-size: 13px;
            margin: 8px 0;
            display: flex;
    justify-content: space-between;
        }

        .contact-detail strong {
            font-weight: 600;
        }

        /* Important Information */
        .important-title {
            font-weight: bold;
            margin: 18px 0 8px 0;
            font-size: 14px;
        }

        .important-list {
            font-size: 12.5px;
            padding-left: 0px;
            color: #222;
            line-height: 1.45;
        }

        .important-list li {
            margin: 5px 0;
        }

        /* Hotel Banner - exactly like image */
        .hotel-banner {
            /* background: #3d3d3d; */
            /* background-image: url(img/a8.png); */
            background-size: cover;
            background-position: center;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            margin: 20px 0;
            color: white;
            position: relative;
            min-height: 150px;
        }

        .hotel-banner::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
        }

        .hotel-left {
            position: relative;
            z-index: 2;
        }

        .hotel-images {
            position: relative;
            z-index: 2;
        }

        .hotel-left h2 {
            font-size: 28px;
            font-weight: 700;
            line-height: 1.2;
        }

        .hotel-left h2 span {
            color: #f5a623;
        }

        .hotel-call {
            background: #f5a623;
            display: inline-block;
            margin-top: 12px;
            padding: 8px 15px;
            font-weight: 600;
        }

        .hotel-call span {
            background: black;
            color: white;
            padding: 4px 8px;
            margin-right: 10px;
            border-radius: 4px;
            display: inline-block;
            vertical-align: middle;
        }

        #bannerMobile {
            display: inline-block;
            vertical-align: middle;
        }

        /* .hotel-images {
            display: flex;
            gap: 10px;
        } */

        .hotel-images img {
            width: 120px;
            height: 90px;
            object-fit: cover;
            border: 3px solid white;
        }

        /* Footer */
        .footer {
            background: #e31b23;
            color: white;
            padding: 8px 15px;
            display: flex;
            justify-content: space-between;
        }

        .footer-left {
            font-weight: 600;
            font-size: 15px;
        }

        .footer-right {
            font-size: 13px;
        }

        /* Disclaimer */
        .disclaimer {
            font-size: 9px;
            text-align: center;
            margin-top: 12px;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 8px;
        }

        /* Control Panel (simple, no extra colors) */
        .control-panel {
            max-width: 210mm;
            margin: 25px auto 0;
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ccc;
            font-family: 'Open Sans', sans-serif;
        }

        .control-panel h4 {
            margin-bottom: 12px;
            color: #333;
            font-size: 16px;
        }

        .form-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .form-group {
            flex: 1;
            min-width: 140px;
        }

        .form-group label {
            /* font-size: 12px; */
            display: block;
            margin-bottom: 3px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #aaa;
            font-size: 12px;
        }

        button {
            background: #e31b23;
            color: white;
            border: none;
            padding: 6px 15px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 20px;
        }

        .flight-result {
            margin-top: 15px;
            max-height: 200px;
            overflow-y: auto;
        }

        .flight-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .flight-table th,
        .flight-table td {
            border: 1px solid #ccc;
            padding: 5px;
            text-align: left;
        }

        .select-flight {
            background: #e31b23;
            color: white;
            border: none;
            padding: 3px 8px;
            cursor: pointer;
            font-size: 10px;
        }

        .banner-card {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 3px solid transparent;
            transition: 0.3s;
        }

        .banner-card img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }

        .banner-card:hover {
            transform: scale(1.05);
            border: 3px solid #e31b23;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .banner-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            text-align: center;
            padding: 5px;
            font-size: 12px;
        }

        .selected-banner {
            border: 3px solid #28a745 !important;
        }

        #ticket {
            width: 100%;
            max-width: 210mm;
            margin: auto;
        }

        .ticket {
            width: 100%;
            max-width: 210mm;
        }

        .passenger-head,
        .flight-title,
        .support-row,
        .two-columns {
            page-break-inside: avoid;
        }

        .ticket {
            padding-bottom: 40px;
        }

        .ticket {
            padding: 15px;
            box-sizing: border-box;
        }

        @media print {

            /* Hide elements that shouldn't print */
            nav,
            .main-header,
            .main-sidebar,
            .main-footer,
            footer {
                display: none !important;
            }

            /* Hide the Control Panel column */
            .row>.col-md-6:first-child {
                display: none !important;
            }

            /* Make the Ticket column full width */
            .row>.col-md-6:last-child {
                flex: 0 0 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Remove layout margins/paddings */
            .wrapper,
            .content-wrapper,
            .content,
            .container-fluid,
            body,
            html {
                margin: 0 !important;
                padding: 0 !important;
                background-color: white !important;
            }

            .ticket {
                margin: 0 auto !important;
                /* Slightly more space from the top edge for printing */
                padding: 8px 8px 18mm 8px !important;
                box-sizing: border-box !important;
                box-shadow: none !important;
                border: none !important;
                width: 100% !important;
                max-width: 100% !important;
                min-height: auto !important;
                display: block !important;
            }

            /* Tighten up spacing to fit on exactly one page */
            .header {
                padding-bottom: 5px !important;
                margin-bottom: 10px !important;
            }

            .support-row {
                margin-bottom: 10px !important;
            }

            .two-columns {
                margin: 10px 0 !important;
                gap: 25px !important;
            }

            .hotel-banner {
                margin: 10px 0 !important;
                padding: 10px !important;
                min-height: 110px !important;
            }

            .passenger-head {
                margin-top: 10px !important;
            }

            .important-title {
                margin: 10px 0 5px 0 !important;
            }

            .footer {
                position: fixed !important;
                left: 0mm !important;
                right: 0mm !important;
                bottom: 0 !important;
                padding: 5px 8px !important;
                box-sizing: border-box !important;
                width: 100% !important;
                margin-top: 0 !important;
                z-index: 9999 !important;
            }

            .important-list {
                font-size: 11px !important;
                margin-bottom: 5px !important;
            }

            .important-list li {
                margin: 2px 0 !important;
            }

            body {
                zoom: 1 !important;
            }

            .flight-title,
            .hotel-banner,
            .passenger-head,
            .header,
            .flight-card-header,
            .data-table th,
            .footer {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }


            .fa-trash {
                display: none;
            }

        }

        @page {
            size: A4 portrait;
            margin: 6mm 8mm 12mm 8mm;
        }

        /* PDF capture clone — mirror @media print layout */
        .ticket-pdf-capture {
            margin: 0 !important;
            padding: 8px 8px 18mm 8px !important;
            box-sizing: border-box !important;
            box-shadow: none !important;
            border: none !important;
            width: 194mm !important;
            max-width: 194mm !important;
            min-height: auto !important;
            display: block !important;
            background: #fff !important;
        }

        .ticket-pdf-capture .header {
            padding-bottom: 5px !important;
            margin-bottom: 10px !important;
        }

        .ticket-pdf-capture .support-row {
            margin-bottom: 10px !important;
        }

        .ticket-pdf-capture .two-columns {
            margin: 10px 0 !important;
            gap: 25px !important;
        }

        .ticket-pdf-capture .hotel-banner {
            margin: 10px 0 !important;
            padding: 10px !important;
            min-height: 110px !important;
        }

        .ticket-pdf-capture .passenger-head {
            margin-top: 10px !important;
        }

        .ticket-pdf-capture .important-title {
            margin: 10px 0 5px 0 !important;
        }

        .ticket-pdf-capture .important-list {
            font-size: 11px !important;
            margin-bottom: 5px !important;
        }

        .ticket-pdf-capture .important-list li {
            margin: 2px 0 !important;
        }

        .ticket-pdf-capture .phone {
            white-space: nowrap !important;
            display: inline-block !important;
        }

        .ticket-pdf-capture .footer {
            position: static !important;
            width: 100% !important;
            margin-top: 0 !important;
            padding: 5px 8px !important;
            box-sizing: border-box !important;
        }

        .ticket-pdf-capture .flight-title,
        .ticket-pdf-capture .hotel-banner,
        .ticket-pdf-capture .passenger-head,
        .ticket-pdf-capture .header,
        .ticket-pdf-capture .flight-card-header,
        .ticket-pdf-capture .data-table th,
        .ticket-pdf-capture .footer {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .ticket-pdf-capture .fa-trash {
            display: none !important;
        }

        .hotel-banner {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .ticket,
        .support-row,
        .two-columns,
        .hotel-banner,
        .passenger-head,
        .flight-title {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* body {
            overflow-x: hidden;
        } */

        body {
            overflow-x: visible;
            font-family: 'Open Sans', sans-serif;
        }

        #pnrInput {
            text-transform: uppercase;
        }

        .flight-filter-btn,
        .flight-sort-btn {
            margin: 0px;
        }
    </style>
    <link rel="stylesheet" href="plugins/jquery-ui/jquery-ui.min.css">
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">



        <div class="">
            <?php include 'includes/page-header.php'; ?>

            <style>
                .datess input {
                    font-size: 14px;
                }

                .onlywidth input {
                    width: 80%;
                }

                input[type="date"]::-webkit-calendar-picker-indicator {
                    opacity: 0;
                    display: none;
                }

                /* .custom-date {
                    background: url('img/calendar.png') no-repeat right 10px center;
                    background-size: 18px;
                    padding-right: 35px;
                } */

                .date-wrapper {
                    position: relative;
                }

                .date-wrapper input {
                    padding-right: 40px;
                    /* space for icon */
                }

                .calendar-icon {
                    position: absolute;
                    right: 48px;
                    top: 50%;
                    transform: translateY(-50%);
                    width: 18px;
                    cursor: pointer;
                }

                .icononward {
                    right: 12px !important;
                }

                .card-body {
                    background: #e5e5e5 !important;
                    border: 2px solid #c5c5c5 !important;
                }
            </style>

            <!-- AI E-Ticket Auto-Fill -->
            <style>
                .et-ai-box {
                    margin-bottom: 14px;
                }

                .et-ai-title {
                    font-weight: 700;
                    font-size: 13px;
                    color: #334155;
                    margin-bottom: 6px;
                }

                .et-ai-title i {
                    color: #4f46e5;
                    margin-right: 5px;
                }

                .et-ai-field {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    min-height: 42px;
                    padding: 6px 8px 6px 10px;
                    background: #f8fafc;
                    border: 1px solid #d0d7e2;
                    border-radius: 8px;
                    transition: border-color .15s, background .15s, box-shadow .15s;
                }

                .et-ai-field:hover,
                .et-ai-field:focus,
                .et-ai-field:focus-within {
                    border-color: #94a3b8;
                    background: #fff;
                    outline: 0;
                    box-shadow: 0 0 0 2px rgba(148, 163, 184, .18);
                }

                .et-ai-field.is-drag,
                .et-ai-field.is-paste {
                    border-color: #6366f1;
                    background: #eef2ff;
                    box-shadow: 0 0 0 2px rgba(99, 102, 241, .18);
                }

                .et-ai-field.is-busy .et-ai-upload-btn,
                .et-ai-field.is-busy .et-ai-replace-btn {
                    pointer-events: none;
                    opacity: .65;
                }

                .et-ai-empty {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    width: 100%;
                    min-width: 0;
                }

                .et-ai-field.has-preview .et-ai-empty {
                    display: none;
                }

                .et-ai-placeholder {
                    flex: 1;
                    min-width: 0;
                    font-size: 12.5px;
                    color: #94a3b8;
                    line-height: 1.35;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .et-ai-upload-btn,
                .et-ai-replace-btn {
                    flex-shrink: 0;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    border: 1px solid #c7d2fe;
                    background: #fff;
                    color: #4338ca;
                    border-radius: 6px;
                    padding: 6px 10px;
                    font-size: 12px;
                    font-weight: 600;
                    line-height: 1.2;
                    cursor: pointer;
                    white-space: nowrap;
                }

                .et-ai-upload-btn:hover,
                .et-ai-upload-btn:focus,
                .et-ai-replace-btn:hover,
                .et-ai-replace-btn:focus {
                    background: #eef2ff;
                    border-color: #818cf8;
                    outline: 0;
                }

                .et-ai-preview {
                    display: none;
                    align-items: center;
                    gap: 10px;
                    width: 100%;
                    min-width: 0;
                }

                .et-ai-field.has-preview .et-ai-preview {
                    display: flex;
                }

                .et-ai-preview-thumb-wrap {
                    width: 80px;
                    height: 80px;
                    flex-shrink: 0;
                    border-radius: 6px;
                    border: 1px solid #e2e8f0;
                    background: #fff;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    overflow: hidden;
                }

                .et-ai-preview-thumb {
                    width: 80px;
                    height: 80px;
                    object-fit: contain;
                    display: block;
                }

                .et-ai-preview-pdf {
                    display: none;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    gap: 4px;
                    width: 100%;
                    height: 100%;
                    color: #dc2626;
                    font-size: 11px;
                    font-weight: 600;
                }

                .et-ai-preview-pdf i {
                    font-size: 28px;
                }

                .et-ai-preview-thumb-wrap.is-pdf .et-ai-preview-thumb {
                    display: none;
                }

                .et-ai-preview-thumb-wrap.is-pdf .et-ai-preview-pdf {
                    display: flex;
                }

                .et-ai-preview-meta {
                    flex: 1;
                    min-width: 0;
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                }

                .et-ai-preview-name {
                    font-size: 12.5px;
                    font-weight: 600;
                    color: #0f172a;
                    line-height: 1.3;
                    word-break: break-word;
                }

                .et-ai-preview-actions {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    flex-wrap: wrap;
                }

                .et-ai-clear-btn {
                    width: 28px;
                    height: 28px;
                    border-radius: 6px;
                    border: 1px solid #e2e8f0;
                    background: #fff;
                    color: #64748b;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    padding: 0;
                    line-height: 1;
                }

                .et-ai-clear-btn:hover,
                .et-ai-clear-btn:focus {
                    border-color: #fca5a5;
                    color: #dc2626;
                    background: #fef2f2;
                    outline: 0;
                }

                .et-ai-status {
                    margin-top: 6px;
                    border-radius: 6px;
                    padding: 6px 8px;
                    font-size: 12px;
                    display: flex;
                    gap: 7px;
                    align-items: flex-start;
                    line-height: 1.35;
                }

                .et-ai-status > i {
                    margin-top: 1px;
                    flex-shrink: 0;
                }

                .et-ai-status .et-ai-msg {
                    flex: 1;
                    min-width: 0;
                }

                .et-ai-status.is-uploading,
                .et-ai-status.is-processing {
                    background: #eff6ff;
                    color: #1e40af;
                    border: 1px solid #bfdbfe;
                }

                .et-ai-status.is-success {
                    background: #f0fdf4;
                    color: #166534;
                    border: 1px solid #bbf7d0;
                }

                .et-ai-status.is-warning {
                    background: #fffbeb;
                    color: #92400e;
                    border: 1px solid #fde68a;
                }

                .et-ai-status.is-error {
                    background: #fef2f2;
                    color: #991b1b;
                    border: 1px solid #fecaca;
                }

                .et-ai-progress {
                    height: 3px;
                    background: #dbeafe;
                    border-radius: 3px;
                    margin-top: 4px;
                    overflow: hidden;
                }

                .et-ai-progress > span {
                    display: block;
                    height: 100%;
                    background: #3b82f6;
                    width: 0;
                    transition: width .2s;
                }

                .et-ai-list {
                    margin-top: 3px;
                    font-size: 11.5px;
                    opacity: .95;
                }

                .control-panel-wrapper .et-ai-filled {
                    background: #fefce8;
                    border-color: #facc15;
                    box-shadow: 0 0 0 2px rgba(250, 204, 21, .18);
                }

                @media (max-width: 576px) {
                    .et-ai-empty {
                        flex-direction: column;
                        align-items: stretch;
                    }

                    .et-ai-placeholder {
                        white-space: normal;
                    }

                    .et-ai-upload-btn {
                        justify-content: center;
                    }

                    .et-ai-field.has-preview {
                        align-items: flex-start;
                    }
                }
            </style>

            <section class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-6">
                            <!-- CONTROL PANEL -->
                            <div class="control-panel-wrapper"
                                style="background: #f4f6f9; padding: 20px; font-family: 'Segoe UI', Arial, sans-serif; border: 1px solid #ddd; margin-bottom: 30px;">

                                <!-- AI E-Ticket Auto-Fill -->
                                <div class="et-ai-box" id="etAiBox">
                                    <div class="et-ai-title"><i class="fas fa-magic"></i>Auto-fill from e-ticket</div>
                                    <div class="et-ai-field" id="etAiDrop" tabindex="0"
                                        aria-label="Upload, drag and drop, or paste e-ticket">
                                        <div class="et-ai-empty" id="etAiEmpty">
                                            <span class="et-ai-placeholder">Upload, drag &amp; drop, or paste e-ticket image (Ctrl + V)</span>
                                            <button type="button" class="et-ai-upload-btn" id="etAiUploadBtn">
                                                <i class="fas fa-upload"></i> Upload E-ticket
                                            </button>
                                        </div>
                                        <div class="et-ai-preview" id="etAiPreview" aria-hidden="true">
                                            <div class="et-ai-preview-thumb-wrap" id="etAiThumbWrap">
                                                <img class="et-ai-preview-thumb" id="etAiPreviewImg" alt="E-ticket preview">
                                                <div class="et-ai-preview-pdf" id="etAiPreviewPdf" aria-hidden="true">
                                                    <i class="fas fa-file-pdf"></i>
                                                    <span>PDF</span>
                                                </div>
                                            </div>
                                            <div class="et-ai-preview-meta">
                                                <div class="et-ai-preview-name" id="etAiPreviewName"></div>
                                                <div class="et-ai-preview-actions">
                                                    <button type="button" class="et-ai-replace-btn" id="etAiReplaceBtn">
                                                        <i class="fas fa-sync-alt"></i> Replace
                                                    </button>
                                                    <button type="button" class="et-ai-clear-btn" id="etAiClearBtn"
                                                        title="Remove" aria-label="Remove e-ticket">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="file" id="etAiFile" class="d-none"
                                        accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
                                    <div class="et-ai-status d-none" id="etAiStatus" role="status" aria-live="polite"></div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="row">
                                            <div class="col-md-12 form-group onlywidth datess">
                                                <label>Booking Date</label>
                                                <div class="date-wrapper">
                                                    <input type="date" class="form-control" id="bookingDate"
                                                        value="<?php echo date('Y-m-d'); ?>">
                                                    <img src="img/calendar.png" class="calendar-icon"
                                                        onclick="openPicker('bookingDate')">
                                                </div>
                                            </div>
                                            <div class="col-md-12 form-group onlywidth">
                                                <label>PNR</label>
                                                <input type="text" class="form-control" id="pnrInput" placeholder="">
                                            </div>
                                            <div class="col-md-12">
                                                <div class="form-group onlywidth mb-3">
                                                    <label>No of Pax</label>
                                                    <input type="number" class="form-control" id="paxCount" value="1"
                                                        min="1">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <!-- Search Flight Box -->
                                        <div class="card mb-3"
                                            style="box-shadow: none; border-radius: 0; background: transparent;">
                                            <div class="card-header"
                                                style="background:#f4f6f9; font-weight:bold; border: 1px solid #ddd; border-bottom: none; border-radius: 0;">
                                                Search Flight
                                            </div>
                                            <div class="card-body"
                                                style="background:#fff; border: 1px solid #ddd; padding: 15px;">
                                                <div class="row mb-2">

                                                    <div class="col-md-3">
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio"
                                                                name="flightType" id="domestic" value="domestic"
                                                                checked>
                                                            <label class="form-check-label"
                                                                for="domestic">Domestic</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio"
                                                                name="flightType" id="international"
                                                                value="international">
                                                            <label class="form-check-label"
                                                                for="international">International</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row mb-2">
                                                    <div class="col-md-3">
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="tripType"
                                                                id="oneway" value="oneway" checked>
                                                            <label class="form-check-label" for="oneway">One
                                                                way</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="tripType"
                                                                id="roundtrip" value="roundtrip">
                                                            <label class="form-check-label" for="roundtrip">Round
                                                                Trip</label>
                                                        </div>
                                                    </div>
                                                </div>



                                                <!-- <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="tripType"
                                                                id="oneway" value="oneway" checked>
                                                            <label class="form-check-label" for="oneway">One way</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-check form-check-inline"
                                                            style="margin-left: 26px;">
                                                            <input class="form-check-input" type="radio" name="tripType"
                                                                id="roundtrip" value="roundtrip">
                                                            <label class="form-check-label" for="roundtrip">Round
                                                                Trip</label>
                                                        </div>
                                                    </div>
                                                </div> -->

                                                <div class="row">
                                                    <div class="col-md-3 form-group datess">
                                                        <label>Onward Date</label>
                                                        <div class="date-wrapper">
                                                            <input type="date" class="form-control" id="apiDate"
                                                                value="<?php echo date('Y-m-d'); ?>"
                                                                min="<?php echo date('Y-m-d'); ?>"
                                                                style="padding: 6px 5px;">
                                                            <img src="img/calendar.png" class="calendar-icon icononward"
                                                                onclick="openPicker('apiDate')">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 form-group" id="returnDateContainer"
                                                        style="display:none;">
                                                        <label>Return Date</label>
                                                        <div class="date-wrapper datess">
                                                            <input type="date" class="form-control" id="apiReturnDate"
                                                                value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                                                min="<?php echo date('Y-m-d'); ?>"
                                                                style="padding: 6px 5px;">
                                                            <img src="img/calendar.png" class="calendar-icon icononward"
                                                                onclick="openPicker('apiReturnDate')">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 form-group" style="position:relative;">
                                                        <label>From</label>
                                                        <input type="text" class="form-control" id="apiFrom"
                                                            autocomplete="off" placeholder="Start typing a city...">
                                                        <div id="apiFromSuggest"
                                                            style="display:none; position:absolute; z-index:1000; width:95%; background:#fff; border:1px solid #ccc; border-radius:4px; max-height:250px; overflow-y:auto; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 form-group" style="position:relative;">
                                                        <label>To</label>
                                                        <input type="text" class="form-control" id="apiTo"
                                                            autocomplete="off" placeholder="Start typing a city...">
                                                        <div id="apiToSuggest"
                                                            style="display:none; position:absolute; z-index:1000; width:95%; background:#fff; border:1px solid #ccc; border-radius:4px; max-height:250px; overflow-y:auto; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                                                        </div>
                                                    </div>

                                                </div>
                                                <!-- <div class="row mb-3">
                                                    <div class="col-md-6 form-group">
                                                        <label>Onward Date</label>
                                                        <input type="date" class="form-control" id="apiDate"
                                                            value="2026-04-18">
                                                    </div>
                                                </div> -->

                                                <button class="btn btn-primary" id="searchFlightsBtn"
                                                    style="background-color:#6ba2c7; border-color:#6ba2c7; margin: 0px;">Search</button>
                                            </div>
                                        </div>

                                        <!-- API FLIGHT RESULTS (Now shifted to a Popup Modal but we keep a structural anchor here if needed) -->
                                        <!-- <div class="row mb-3">
                                            <div class="col-md-12 text-center" id="searchLoadingIndicator"
                                                style="display:none;">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="sr-only">Loading...</span>
                                                </div>
                                                <p class="mt-2 text-muted">Searching for flights...</p>
                                            </div>
                                        </div> -->
                                    </div>
                                </div>



                                <!-- <div class="form-group mb-3">
                                    <label>No of Pax</label>
                                    <input type="number" class="form-control" id="paxCount" value="1" min="1"
                                        style="width: 100%;">
                                </div> -->

                                <!-- Pax Details Box -->
                                <div id="paxDetailsContainer"
                                    style="background:#ebebeb; padding:15px; border:1px solid #ddd; margin-bottom:15px;">
                                    <!-- Dynamically generated pax rows go here -->
                                </div>

                                <!-- Fare details -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="row">

                                            <div class="col-md-12">
                                                <div class="form-group row m-0">
                                                    <label class="col-md-4 col-form-label">Base</label>

                                                    <div class="col-md-4">
                                                        <input type="text" class="form-control"
                                                            style="text-align: right;height: 28px;" id="baseInput">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="form-group row m-0">
                                                    <label class="col-md-4 col-form-label">Taxes & Fees</label>

                                                    <div class="col-md-4">
                                                        <input type="text" class="form-control"
                                                            style="text-align: right;height: 28px;" id="taxInput">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="form-group row m-0">
                                                    <label class="col-md-4 col-form-label">Total</label>

                                                    <div class="col-md-4">
                                                        <input type="text" class="form-control"
                                                            style="text-align: right;height: 28px;" id="totalInput">
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row">

                                            <div class="col-md-12">
                                                <div class="form-group row">
                                                    <label class="col-md-3 col-form-label">Mobile No</label>
                                                    <div class="col-md-9">
                                                        <input type="text" class="form-control" id="mobileInput"
                                                            value="9709100140">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="form-group row">
                                                    <label class="col-md-3 col-form-label">Email Id</label>
                                                    <div class="col-md-9">
                                                        <input type="text" class="form-control" id="emailinput"
                                                            value="info@multizonetravels.com">
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>

                                <!-- Mobile No -->
                                <!-- <div class="row mb-3">
                                    <div class="col-md-4 form-group">
                                        <label>Mobile No</label>
                                        <input type="text" class="form-control" id="mobileInput" value="9709100140">
                                    </div>
                                </div> -->

                                <!-- Checkboxes -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="hideFare">
                                            <label class="form-check-label" for="hideFare">Hide Fare</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="nonRefundable">
                                            <label class="form-check-label" for="nonRefundable">Non Refundable</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="useStaticBanner"
                                                checked>
                                            <label class="form-check-label" for="useStaticBanner">Use Static
                                                Banner</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Image Upload -->
                                <div class="row">

                                    <!-- Upload From Device -->
                                    <div class="col-md-6 form-group">
                                        <label>Upload Banner</label>

                                        <div class="input-group">
                                            <div class="custom-file">
                                                <input type="file" class="custom-file-input" id="bannerUpload"
                                                    accept="image/*">
                                                <label class="custom-file-label" for="bannerUpload">Choose Image</label>
                                            </div>

                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-secondary m-0"
                                                    onclick="uploadBanner()">Upload</button>
                                            </div>
                                        </div>

                                        <small class="text-muted">592 × 158 px recommended</small>
                                    </div>


                                    <!-- Select From Master -->
                                    <div class="col-md-6 form-group">
                                        <label>Select From Image Master</label>

                                        <button class="btn btn-info m-0" data-toggle="modal" data-target="#bannerModal">
                                            Choose From Master
                                        </button>

                                    </div>

                                </div>

                                <button class="btn btn-success" onclick="saveTicketOnPrint()"
                                    style="margin-left: 0;">Print Ticket</button>
                                <button class="btn btn-primary" onclick="downloadTicket()"
                                    style="margin-left: 8px;">Download Ticket</button>

                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- MAIN TICKET - EXACTLY LIKE IMAGE -->
                            <div class="ticket" id="ticket">
                                <!-- Header -->
                                <div class="header" style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="text-align: left; flex: 1;">
                                        <div class="logo"><img src="img/awer.png" width="150px" alt=""></div>
                                    </div>
                                    <div style="text-align: center; flex: 1;">
                                        <div class="booked-on">Booked on: <span id="displayDate"><?php echo date('d-m-Y'); ?></span></div>
                                    </div>
                                    <div style="text-align: right; flex: 1; display: flex; justify-content: flex-end;">
                                        <img id="pnrBarcode" src="" style="display:none; max-width: 200px; height: 52px; width: 175px;" alt="Barcode">
                                    </div>
                                </div>

                                <!-- Support & PNR Row -->
                                <div class="support-row">
                                    <div class="support-box" id="airlineLogoBox"
                                        style="display:none; align-items: center; justify-content: center; border-right: 1px solid #ccc;">
                                        <img id="airlineLogo" src="" style="max-height: 45px; max-width: 110px;">
                                    </div>
                                    <div class="support-box">
                                        <div class="title">24x7 Support</div>
                                        <div class="phone">📞 +91 97094 00140</div>
                                    </div>
                                    <div class="support-box" style="border-right: none;">
                                        <div class="title" style="margin-left: -40px;">Agency</div>
                                        <div class="phone">📞 +91 97080 00140</div>
                                    </div>
                                    <div class="support-box" id="pnrBox"
                                        style="flex-direction: column; margin-left: 10px; flex: 0.5;">
                                        <div class="title" style="color: #e31b23;">PNR</div>
                                        <div class="phone" id="pnrText" style="font-size: 24px;"></div>
                                    </div>
                                </div>

                                <!-- Onward Flight Details -->
                                <div class="flight-title onward-flight-title">
                                    ✈ Onward Flight Details
                                    <span class="verify-text">*Please verify flight times with the airlines prior to
                                        departure</span>
                                </div>
                                <div class="flight-container" id="flightDetailsContainer">
                                    <!-- Flight details will be injected here -->
                                </div>

                                <!-- Return Flight Details -->
                                <div class="flight-title return-flight-title" style="display:none; margin-top: 15px;">
                                    ✈ Return Flight Details
                                    <span class="verify-text">*Please verify flight times with the airlines prior to
                                        departure</span>
                                </div>
                                <div class="flight-container" id="returnFlightDetailsContainer" style="display:none;">
                                    <!-- Return Flight details will be injected here -->
                                </div>

                                <!-- Passenger Details Table -->
                                <div class="passenger-head"><img src="img/group.png" width="17px" class="mb-1" alt=""> Passenger(s) Details</div>
                                <table class="data-table" id="passengerTable">
                                    <thead id="passengerTableHead">
                                        <tr style="background-color: #d2d2d2;">
                                            <th class="col-sr-no">Sr No.</th>
                                        </tr>
                                    </thead>
                                    <tbody id="passengerBody">
                                        <tr>
                                            <td class="col-sr-no">1</td>
                                            <td>MR</td>
                                            <td>Adult</td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Payment & Contact Details -->
                                <div class="two-columns">
                                    <div class="payment-box">
                                        <div class="box-header">
                                            Payment Details
                                            <span>Amount (INR)</span>
                                        </div>
                                        <div class="amount-line"><span>Base Fare</span><span id="baseAmount">0</span>
                                        </div>
                                        <div class="amount-line"><span>Taxes & Fees</span><span id="taxAmount">0</span>
                                        </div>
                                        <div class="total-line"><span>Total</span><span id="totalAmount">0</span></div>
                                    </div>
                                    <div class="contact-box">
                                        <div class="box-header">Customer Contact Details</div>
                                        <div class="contact-detail">Contact No: <span
                                                id="contactNo">9709100140</span>
                                        </div>
                                        <div class="contact-detail">Email: <span
                                                id="emailId">info@multizonetravels.com</span></div>
                                    </div>
                                </div>

                                <div id="nonRefundableText"
                                    style="display:none; font-weight: bold; margin-top: 18px; font-size: 14px;">
                                    <span style="color: #333;">Note:</span> <span style="color: #e31b23;">This ticket is
                                        100% Non-Refundable & Non-Changeable</span>
                                </div>
                                <!-- Important Information -->
                                <div class="important-title">Important Information</div>

                                <?php
                                $q = mysqli_query($conn, "SELECT important_info FROM ticket_settings LIMIT 1");
                                $data = ($q) ? mysqli_fetch_assoc($q) : null;
                                ?>

                                <ul class="important-list">

                                    <?php echo !empty($data['important_info']) ? $data['important_info'] : ''; ?>

                                </ul>

                                <!-- Looking for Hotels Banner -->
                                <div class="hotel-banner" id="hotelBanner"
                                    style="background-image:url('<?php echo $banner; ?>');">

                                    <div class="hotel-left">
                                        <h2>LOOKING FOR <br><span id="hotelCity">Mumbai</span> HOTELS?</h2>

                                        <div class="hotel-call">
                                            <span>Call now</span>
                                            <b id="bannerMobile">9709400140</b>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div class="footer">
                                    <div class="footer-left">FLIGHTS | HOTELS | HOLIDAYS | VISA | FOREX</div>
                                    <div class="footer-right">Dibadih, Doranda, Ranchi-834002</div>
                                </div>
                                <!-- <div class="disclaimer">This is a computer generated E-Ticket. No signature required.</div> -->
                            </div>
                        </div>
                    </div>


                </div>
            </section>
        </div>



        <!-- Banner Select Modal -->

        <div class="modal fade" id="bannerModal">

            <div class="modal-dialog modal-lg">

                <div class="modal-content">

                    <div class="modal-header">
                        <h5>Select Banner</h5>
                        <button class="close" data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body">

                        <div class="row">

                            <?php

                            $sql = "SELECT image FROM image_master 
        WHERE status='Active' 
        AND is_deleted=0";
                            $res = mysqli_query($conn, $sql);

                            while ($res && ($row = mysqli_fetch_assoc($res))) {

                                $img = "uploads/images/" . $row['image'];

                                ?>

                                <div class="col-md-3 mb-4">

                                    <div class="banner-card banner-option" data-img="<?php echo $img; ?>">
                                        <img src="<?php echo $img; ?>">
                                        <div class="banner-overlay">
                                            Select
                                        </div>
                                    </div>

                                </div>

                            <?php } ?>

                        </div>

                    </div>

                </div>

            </div>

        </div> <!-- /.modal -->

        <!-- Flight Searching Popup -->
        <div id="flightSearchLoadingOverlay" role="dialog" aria-modal="true" aria-labelledby="flightSearchLoadingTitle" aria-hidden="true">
            <div class="flight-search-popup">
                <div class="flight-search-popup-stage" aria-hidden="true">
                    <div class="flight-search-loader">
                        <span><span></span><span></span><span></span><span></span></span>
                        <div class="flight-search-base">
                            <span></span>
                            <div class="flight-search-face"></div>
                        </div>
                    </div>
                    <div class="flight-search-longfazers">
                        <span></span><span></span><span></span><span></span>
                    </div>
                </div>
                <h5 class="flight-search-popup-title" id="flightSearchLoadingTitle">Searching for Available Flights...</h5>
            </div>
        </div>

        <!-- Selected flight animated ticket (Uiverse) -->
        <div id="mzFlightTicketOverlay" aria-hidden="true">
            <div class="mz-ft-ticket-wrap">
                <div class="mz-ft-ticket-card">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="150" viewBox="0 0 64 150" fill="black" aria-hidden="true">
                        <path d="M44 138V136.967H20V138H44Z"></path>
                        <path d="M44 13.0328V12H20V13.0328H44Z"></path>
                        <path d="M44 14.0656V13.5492H20V14.0656H44Z"></path>
                        <path d="M44 15.6148V15.0984H20V15.6148H44Z"></path>
                        <path d="M44 18.1967V17.6803H20V18.1967H44Z"></path>
                        <path d="M44 20.2623V19.2295H20V20.2623H44Z"></path>
                        <path d="M44 22.8443V22.3279H20V22.8443H44Z"></path>
                        <path d="M44 23.877V23.3607H20V23.877H44Z"></path>
                        <path d="M44 26.9754V24.9098H20V26.9754H44Z"></path>
                        <path d="M44 28.0082V27.4918H20V28.0082H44Z"></path>
                        <path d="M44 29.5574V29.041H20V29.5574H44Z"></path>
                        <path d="M44 32.6557V30.5902H20V32.6557H44Z"></path>
                        <path d="M44 33.6885V33.1721H20V33.6885H44Z"></path>
                        <path d="M44 35.2376V34.7213H20V35.2376H44Z"></path>
                        <path d="M44 36.2705V35.7541H20V36.2705H44Z"></path>
                        <path d="M44 39.3689V37.3033H20V39.3689H44Z"></path>
                        <path d="M44 40.918V40.4015H20V40.918H44Z"></path>
                        <path d="M44 43.5001V41.4345H20V43.5001H44Z"></path>
                        <path d="M44 45.0492V44.5327H20V45.0492H44Z"></path>
                        <path d="M44 47.631V46.0819H20V47.631H44Z"></path>
                        <path d="M44 49.1804V48.664H20V49.1804H44Z"></path>
                        <path d="M44 51.2458V50.2131H20V51.2458H44Z"></path>
                        <path d="M44 52.2787V51.7622H20V52.2787H44Z"></path>
                        <path d="M44 54.3443V52.7952H20V54.3443H44Z"></path>
                        <path d="M44 56.4099V55.377H20V56.4099H44Z"></path>
                        <path d="M44 57.959V57.4426H20V57.959H44Z"></path>
                        <path d="M44 60.0247V58.4753H20V60.0247H44Z"></path>
                        <path d="M44 62.09V61.0573H20V62.09H44Z"></path>
                        <path d="M44 63.6394V63.123H20V63.6394H44Z"></path>
                        <path d="M44 66.7377V64.6721H20V66.7377H44Z"></path>
                        <path d="M44 68.2868V67.7704H20V68.2868H44Z"></path>
                        <path d="M44 69.3198V68.8033H20V69.3198H44Z"></path>
                        <path d="M44 72.4181V71.3851H20V72.4181H44Z"></path>
                        <path d="M44 73.4508V72.9345H20V73.4508H44Z"></path>
                        <path d="M44 76.5493V74.4837H20V76.5493H44Z"></path>
                        <path d="M44 77.5819V77.0655H20V77.5819H44Z"></path>
                        <path d="M44 79.1311V78.6146H20V79.1311H44Z"></path>
                        <path d="M44 80.6802V80.164H20V80.6802H44Z"></path>
                        <path d="M44 82.2294V81.1967H20V82.2294H44Z"></path>
                        <path d="M44 83.7788V83.2623H20V83.7788H44Z"></path>
                        <path d="M44 86.3606V85.8441H20V86.3606H44Z"></path>
                        <path d="M44 87.9097V87.3935H20V87.9097H44Z"></path>
                        <path d="M44 91.0083V88.9427H20V91.0083H44Z"></path>
                        <path d="M44 92.041V91.5245H20V92.041H44Z"></path>
                        <path d="M44 94.623V92.5574H20V94.623H44Z"></path>
                        <path d="M44 96.1722V95.6557H20V96.1722H44Z"></path>
                        <path d="M44 97.7213V97.2048H20V97.7213H44Z"></path>
                        <path d="M44 99.2704V98.2378H20V99.2704H44Z"></path>
                        <path d="M44 100.82V100.303H20V100.82H44Z"></path>
                        <path d="M44 103.402V102.885H20V103.402H44Z"></path>
                        <path d="M44 105.467V104.434H20V105.467H44Z"></path>
                        <path d="M44 108.049V106.5H20V108.049H44Z"></path>
                        <path d="M44 109.082V108.566H20V109.082H44Z"></path>
                        <path d="M44 112.18V111.148H20V112.18H44Z"></path>
                        <path d="M44 113.213V112.697H20V113.213H44Z"></path>
                        <path d="M44 114.762V114.246H20V114.762H44Z"></path>
                        <path d="M44 118.377V116.311H20V118.377H44Z"></path>
                        <path d="M44 119.41V118.893H20V119.41H44Z"></path>
                        <path d="M44 120.442V119.926H20V120.442H44Z"></path>
                        <path d="M44 122.508V120.959H20V122.508H44Z"></path>
                        <path d="M44 124.574V123.541H20V124.574H44Z"></path>
                        <path d="M44 127.672V125.607H20V127.672H44Z"></path>
                        <path d="M44 128.705V128.188H20V128.705H44Z"></path>
                        <path d="M44 130.254V129.738H20V130.254H44Z"></path>
                        <path d="M44 132.32V131.287H20V132.32H44Z"></path>
                        <path d="M44 135.418V133.869H20V135.418H44Z"></path>
                        <path d="M44 136.451V135.934H20V136.451H44Z"></path>
                    </svg>
                    <div class="mz-ft-ticket-separator">
                        <span class="mz-ft-ticket-span-lines"></span>
                    </div>
                    <div class="mz-ft-ticket-content">
                        <div class="mz-ft-ticket-content-data">
                            <div class="mz-ft-ticket-destination">
                                <div class="mz-ft-ticket-dest mz-ft-ticket-dest-start">
                                    <p class="mz-ft-ticket-country" id="mzFtDepCity">—</p>
                                    <p class="mz-ft-ticket-acronym" id="mzFtDepCode">—</p>
                                    <p class="mz-ft-ticket-hour">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 1024 1024" aria-hidden="true">
                                            <path fill="currentColor" d="M768 256H353.6a32 32 0 1 1 0-64H800a32 32 0 0 1 32 32v448a32 32 0 0 1-64 0z"></path>
                                            <path fill="currentColor" d="M777.344 201.344a32 32 0 0 1 45.312 45.312l-544 544a32 32 0 0 1-45.312-45.312z"></path>
                                        </svg>
                                        <span id="mzFtDepTime">—</span>
                                    </p>
                                </div>
                                <svg style="flex-shrink: 0;" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="none" stroke="#aeaeae" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="m18 8l4 4l-4 4M2 12h20"></path>
                                </svg>
                                <div class="mz-ft-ticket-dest mz-ft-ticket-dest-end">
                                    <p class="mz-ft-ticket-country" id="mzFtArrCity">—</p>
                                    <p class="mz-ft-ticket-acronym" id="mzFtArrCode">—</p>
                                    <p class="mz-ft-ticket-hour">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 1024 1024" aria-hidden="true">
                                            <path fill="currentColor" d="M352 768a32 32 0 1 0 0 64h448a32 32 0 0 0 32-32V352a32 32 0 0 0-64 0v416z"></path>
                                            <path fill="currentColor" d="M777.344 822.656a32 32 0 0 0 45.312-45.312l-544-544a32 32 0 0 0-45.312 45.312z"></path>
                                        </svg>
                                        <span id="mzFtArrTime">—</span>
                                    </p>
                                </div>
                            </div>
                            <div style="border-bottom: 2px solid #e8e8e8"></div>
                            <div class="mz-ft-ticket-data-flex-col">
                                <div class="mz-ft-ticket-data-flex">
                                    <div class="mz-ft-ticket-data">
                                        <p class="mz-ft-ticket-title">Airline</p>
                                        <p class="mz-ft-ticket-subtitle" id="mzFtAirline"></p>
                                    </div>
                                    <div class="mz-ft-ticket-data mz-ft-ticket-passenger">
                                        <p class="mz-ft-ticket-title">Passenger</p>
                                        <p class="mz-ft-ticket-subtitle" id="mzFtPassenger"></p>
                                    </div>
                                </div>
                                <div class="mz-ft-ticket-data-flex">
                                    <div class="mz-ft-ticket-data">
                                        <p class="mz-ft-ticket-title">Flight</p>
                                        <p class="mz-ft-ticket-subtitle" id="mzFtFlightNo"></p>
                                    </div>
                                    <div class="mz-ft-ticket-data">
                                        <p class="mz-ft-ticket-title">Gate</p>
                                        <p class="mz-ft-ticket-subtitle" id="mzFtGate"></p>
                                    </div>
                                    <div class="mz-ft-ticket-data">
                                        <p class="mz-ft-ticket-title">Seat</p>
                                        <p class="mz-ft-ticket-subtitle" id="mzFtSeat"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mz-ft-ticket-icons">
                            <div class="mz-ft-ticket-icon mz-ft-ticket-icon-plane">
                                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="currentColor" d="M20.56 3.91c.59.59.59 1.54 0 2.12l-3.89 3.89l2.12 9.19l-1.41 1.42l-3.88-7.43L9.6 17l.36 2.47l-1.07 1.06l-1.76-3.18l-3.19-1.77L5 14.5l2.5.37L11.37 11L3.94 7.09l1.42-1.41l9.19 2.12l3.89-3.89c.56-.58 1.56-.58 2.12 0"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flight Search Results Modal -->
        <div class="modal fade" id="flightsModal" tabindex="-1" role="dialog" aria-labelledby="flightsModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document" style="max-width: 900px;">
                <div class="modal-content">
                    <div class="modal-header text-center d-block position-relative"
                        style="border-bottom: 2px solid #f4f6f9;">
                        <h5 class="modal-title w-100" id="flightsModalTitle" style="font-size:1.1rem; color:#555;">
                            IXR - DEL | 18/04/2026
                        </h5>
                        <button type="button" class="close position-absolute" data-dismiss="modal" aria-label="Close"
                            style="right: 15px; top: 15px;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body"
                        style="background-color: #f4f6f9; max-height: 70vh; overflow-y: auto; padding: 20px;">
                        <div id="flightsModalBody">
                            <!-- Flight Cards injected here dynamically -->
                        </div>
                    </div>
                    <div class="modal-footer" style="background-color: #fff; border-top: 1px solid #eee;">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'includes/footer-links.php'; ?>
    </div>

    <script>
        function openPicker(id) {
            const input = document.getElementById(id);

            if (input && input.showPicker) {
                input.showPicker(); // Chrome, Edge
            } else if (input) {
                input.focus(); // fallback
            }
        }
    </script>


    <script>

        $(document).on("click", ".banner-option", function () {

            var img = $(this).data("img");

            // uncheck static banner
            $("#useStaticBanner").prop("checked", false);

            // apply selected banner
            $("#hotelBanner").css(
                "background-image",
                "url('" + img + "')"
            );

            // hide the hotel text
            $(".hotel-left").hide();

            // highlight selected banner
            $(".banner-card").removeClass("selected-banner");
            $(this).addClass("selected-banner");

            // close modal smoothly
            setTimeout(function () {
                $("#bannerModal").modal("hide");
            }, 200);

        });

        // $("#pnrInput").keyup(function () {
        //     var val = $(this).val();
        //     $("#pnrText").text(val);
        //     if (val.length > 0) {
        //         // Dynamically fetch barcode from free API similar to user's implementation
        //         $("#pnrBarcode").attr("src", "https://barcode.tec-it.com/barcode.ashx?data=" + encodeURIComponent(val) + "&code=Code128&dpi=96").show();
        //     } else {
        //         $("#pnrBarcode").hide();
        //     }
        // });

        $("#pnrInput").on("input", function () {

            var val = $(this).val().toUpperCase().trim();
            $(this).val(val);

            // Show PNR text
            $("#pnrText").text(val);

            if (val.length > 0) {

                var barcodeURL =
                    "https://bwipjs-api.metafloor.com/?bcid=code128&text=" +
                    encodeURIComponent(val) +
                    "&scale=3&rotate=N&height=10";

                $("#pnrBarcode").attr("src", barcodeURL).show();

            } else {
                $("#pnrBarcode").hide();
            }

        });

        $("#mobileInput").keyup(function () {
            $("#contactNo").text($(this).val());
        });
        $("#emailinput").keyup(function () {
            $("#emailId").text($(this).val());
        });

        $("#baseInput, #taxInput, #totalInput, #bookingDate").on("input change keyup", function () {
            updateTicketData();
        });

        $("#hideFare, #nonRefundable").on("change", function () {
            updateTicketData();
        });

        $("#baseInput, #totalInput").on("input keyup", function () {
            if ($("#totalInput").val().trim() !== "") {
                var base = parseFloat($("#baseInput").val()) || 0;
                var total = parseFloat($("#totalInput").val()) || 0;
                var tax = total - base;
                $("#taxInput").val(tax >= 0 ? tax : 0);
                updateTicketData();
            }
        });

        function uploadBanner() {

            var input = document.getElementById("bannerUpload");

            if (!input.files || !input.files[0]) {
                alert("Please select an image first.");
                return;
            }

            $("#useStaticBanner").prop("checked", false);

            var reader = new FileReader();

            reader.onload = function (e) {

                $("#hotelBanner").css(
                    "background-image",
                    "url('" + e.target.result + "')"
                );

                $(".hotel-left").hide();

            };

            reader.readAsDataURL(input.files[0]);
        }

        $("#bannerUpload").on("change", function () {

            var fileName = $(this).val().split("\\").pop();
            $(this).next(".custom-file-label").html(fileName);

        });

        $("#mobileInput2").on("input", function () {

            $("#bannerMobile").text($(this).val());

        });

        function getPaxRowHtml(i) {
            var lblType = i === 1 ? "<label>Type</label>" : "";
            var lblInitial = i === 1 ? "<label>Initial</label>" : "";
            var lblName = i === 1 ? "<label>Full Name</label>" : "";
            var lblMeal = i === 1 ? "<label>Meal</label>" : "";
            var lblSeat = i === 1 ? "<label>Seat</label>" : "";
            var lblTicket = i === 1 ? "<label>Ticket</label>" : "";
            var lblServices = i === 1 ? "<label>Services</label>" : "";

            var actionBtn = i === 1
                ? `<button type="button" class="btn btn-sm btn-success add-pax-btn" style="padding: 0 8px; height: 28px;margin: 0px;padding-bottom: 32px;font-size: 21px;" title="Add Passenger">+</button>`
                : `<button type="button" class="btn btn-sm btn-danger remove-pax-btn" style="padding: 0 10px; height: 28px;margin: 0px;padding-bottom: 32px;font-size: 21px;" title="Remove Passenger">-</button>`;

            return `
            <div class="pax-group" id="paxGroup_${i}" style="margin-bottom: 10px;">
                <div class="row pax-row align-items-center mb-2">
                   
                    <div class="col-md-2" style="max-width: 12.666667%;">
                        ${lblType}
                        <select class="form-control pax-type" style="padding: 0px; width: 70px;">
                            <option>Adult</option>
                            <option>Child</option>
                            <option>Infant</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        ${lblInitial}
                        <select class="form-control pax-initial" style="padding: 0px; width: 51px;">
                            <option>Mr</option>
                            <option>Mrs</option>
                            <option>Ms</option>
                            <option>Mstr</option>
                            <option>Miss</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        ${lblName}
                        <input type="text" class="form-control pax-name" style="padding: 0px 0px 0px 6px; width: 158px;">
                    </div>
                    <div class="col-md-1">
                        ${lblMeal}
                        <input type="text" class="form-control pax-meal" style="padding: 0px 0px 0px 6px; width: 52px;">
                    </div>
                     <div class="col-md-1">
                        ${lblSeat}
                        <input type="text" class="form-control pax-seat" style="padding: 0px 0px 0px 6px;">
                    </div>
                    <div class="col-md-2">
                        ${lblTicket}
                        <input type="text" class="form-control pax-ticket" style="padding: 0px 0px 0px 6px; width: 100px;">
                    </div>
                    <div class="col-md-2">
                        ${lblServices}
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <input type="text" class="form-control pax-services" style="padding: 0px 0px 0px 6px;">
                            ${actionBtn}
                        </div>
                    </div>
                </div>
            </div>`;
        }

        function renderPaxForms() {
            var count = parseInt($("#paxCount").val()) || 1;
            var currentCount = $(".pax-group").length;

            if (currentCount === 0) {
                var html = '';
                for (var i = 1; i <= count; i++) {
                    html += getPaxRowHtml(i);
                }
                $("#paxDetailsContainer").html(html);
            } else {
                if (count > currentCount) {
                    for (var i = currentCount + 1; i <= count; i++) {
                        $("#paxDetailsContainer").append(getPaxRowHtml(i));
                    }
                } else if (count < currentCount) {
                    for (var i = currentCount; i > count; i--) {
                        $(".pax-group").last().remove();
                    }
                }
            }
        }

        $("#paxCount").on("change input", function () {
            renderPaxForms();
            updateTicketData();
        });

        $(document).on('click', '.add-pax-btn', function () {
            var currentCount = parseInt($("#paxCount").val()) || 1;
            $("#paxCount").val(currentCount + 1).trigger('change');
        });

        $(document).on('click', '.remove-pax-btn', function () {
            $(this).closest('.pax-group').remove();
            var newCount = $(".pax-group").length;
            $("#paxCount").val(newCount);
            updateTicketData();
        });

        // Initial render
        renderPaxForms();

        // Real-time update for passenger details
        $(document).on("input change", ".pax-name, .pax-initial, .pax-type, .pax-meal, .pax-seat, .pax-ticket, .pax-services", function () {
            updateTicketData();
        });

        function upperCaseText(value) {
            return ((value || "") + "").toUpperCase();
        }

        function isUnsetLabel(value) {
            var norm = String(value || "").toLowerCase().replace(/[^a-z]+/g, "");
            return norm !== "" && /^(?:notselect(?:ed)?|unselected|noselection|notassigned|notavailable)+$/.test(norm);
        }

        function cleanPaxField(value, isTicket) {
            var v = String(value || "").trim();
            if (!v || isUnsetLabel(v)) {
                return "";
            }
            if (isTicket) {
                var pnr = upperCaseText($("#pnrInput").val() || "").replace(/\s+/g, "");
                var ticket = upperCaseText(v).replace(/\s+/g, "");
                if (pnr && ticket === pnr) {
                    return "";
                }
            }
            return v;
        }

        $(document).on("input blur", ".pax-meal, .pax-seat, .pax-services, .pax-ticket", function () {
            var formatted = upperCaseText($(this).val());
            if ($(this).val() !== formatted) {
                $(this).val(formatted);
                updateTicketData();
            }
        });

        // Push data to ticket before download
        function updateTicketData() {
            // Update booking date
            var bDate = $("#bookingDate").val();
            if (bDate) {
                var p = bDate.split('-');
                if (p.length === 3) {
                    $("#displayDate").text(p[2] + "-" + p[1] + "-" + p[0]);
                } else {
                    $("#displayDate").text(bDate);
                }
            } else {
                $("#displayDate").text("<?php echo date('d-m-Y'); ?>");
            }

            // Build Pax Table on e-ticket only — show columns you actually filled (form unchanged)
            var paxRows = [];
            $(".pax-group").each(function (index) {
                var initial = (($(this).find(".pax-initial").val() || "") + "").trim();
                var name = (($(this).find(".pax-name").val() || "") + "").trim();
                var type = (($(this).find(".pax-type").val() || "") + "").trim();
                var ticket = upperCaseText(cleanPaxField((($(this).find(".pax-ticket").val() || "") + "").trim(), true));
                var seat = upperCaseText(cleanPaxField((($(this).find(".pax-seat").val() || "") + "").trim(), false));
                var meal = upperCaseText(cleanPaxField((($(this).find(".pax-meal").val() || "") + "").trim(), false));
                var services = upperCaseText(cleanPaxField((($(this).find(".pax-services").val() || "") + "").trim(), false));
                var fullName = name ? (initial + " " + name).trim() : "";

                paxRows.push({
                    sr: String(index + 1),
                    name: fullName,
                    type: type,
                    ticket: ticket,
                    seat: seat,
                    meal: meal,
                    services: services
                });
            });

            var smsParts = [];
            if (paxRows.some(function (r) { return (r.seat || "").trim() !== ""; })) {
                smsParts.push({ key: 'seat', label: 'Seat' });
            }
            if (paxRows.some(function (r) { return (r.meal || "").trim() !== ""; })) {
                smsParts.push({ key: 'meal', label: 'Meal' });
            }
            if (paxRows.some(function (r) { return (r.services || "").trim() !== ""; })) {
                smsParts.push({ key: 'services', label: 'Services' });
            }

            var colDefs = [
                { key: 'sr', label: 'Sr No.', always: true },
                { key: 'name', label: 'Passenger(s) Name' },
                { key: 'type', label: 'Type' },
                { key: 'ticket', label: 'Ticket No' }
            ];
            if (smsParts.length > 0) {
                colDefs.push({
                    key: 'seatMealServices',
                    label: smsParts.map(function (p) { return p.label; }).join('/'),
                    smsParts: smsParts
                });
            }

            var activeCols = colDefs.filter(function (col) {
                if (col.always) return true;
                if (col.key === 'seatMealServices') return true;
                return paxRows.some(function (row) {
                    return (row[col.key] || "").trim() !== "";
                });
            });

            var headHtml = '<tr style="background-color: #d2d2d2;">';
            activeCols.forEach(function (col) {
                var thClass = col.key === 'sr' ? ' class="col-sr-no"' : '';
                headHtml += '<th' + thClass + '>' + col.label + '</th>';
            });
            headHtml += '</tr>';
            $("#passengerTableHead").html(headHtml);

            var tbody = $("#passengerBody");
            tbody.empty();
            if (paxRows.length === 0) {
                tbody.append('<tr><td colspan="' + activeCols.length + '" class="text-muted">—</td></tr>');
            } else {
                paxRows.forEach(function (row) {
                    var tr = '<tr>';
                    activeCols.forEach(function (col) {
                        var cell = "";
                        if (col.key === 'seatMealServices' && col.smsParts) {
                            var vals = [];
                            col.smsParts.forEach(function (p) {
                                if ((row[p.key] || "").trim() !== "") {
                                    vals.push(upperCaseText(row[p.key]));
                                }
                            });
                            cell = vals.join(' / ');
                        } else {
                            cell = row[col.key] || "";
                        }
                        if (col.key === 'sr') {
                            tr += '<td class="col-sr-no">' + cell + '</td>';
                        } else if (col.key === 'name') {
                            tr += '<td style="font-weight:bold;text-transform:uppercase;">' + cell + '</td>';
                        } else if (col.key === 'ticket') {
                            tr += '<td style="text-transform:uppercase;">' + String(cell).toUpperCase() + '</td>';
                        } else {
                            tr += '<td>' + cell + '</td>';
                        }
                    });
                    tr += '</tr>';
                    tbody.append(tr);
                });
            }

            // Handle Checkboxes
            if ($("#hideFare").is(":checked")) {
                $(".payment-box").hide();
            } else {
                $(".payment-box").show();
                var bVal = parseFloat($("#baseInput").val()) || 0;
                var tVal = parseFloat($("#taxInput").val()) || 0;
                var totVal = parseFloat($("#totalInput").val()) || 0;
                $("#baseAmount").text(bVal.toFixed(2));
                $("#taxAmount").text(tVal.toFixed(2));
                $("#totalAmount").text(totVal.toFixed(2));
            }

            var onwardTitle = "✈ Onward Flight Details";
            var returnTitle = "✈ Return Flight Details";
            var badgeHtml = "";
            if ($("#nonRefundable").is(":checked")) {
                badgeHtml = " ";
                $("#nonRefundableText").show();
            } else {
                $("#nonRefundableText").hide();
            }
            $(".onward-flight-title").html(onwardTitle + badgeHtml + "<span class='verify-text'>*Please verify flight times with the airlines prior to departure</span>");
            $(".return-flight-title").html(returnTitle + badgeHtml + "<span class='verify-text'>*Please verify flight times with the airlines prior to departure</span>");



        }

        var staticBanner = "img/static-banner.jpg";

        $(document).ready(function () {

            if ($("#useStaticBanner").is(":checked")) {

                $("#hotelBanner").css(
                    "background-image",
                    "url('" + staticBanner + "')"
                );
                $(".hotel-left").show();

            } else {
                $(".hotel-left").hide();
            }

        });

        $("#useStaticBanner").change(function () {

            var staticBanner = "img/static-banner.jpg";

            if ($(this).is(":checked")) {

                $("#hotelBanner").css(
                    "background-image",
                    "url('" + staticBanner + "')"
                );
                $(".hotel-left").show();

            } else {
                $(".hotel-left").hide();
            }

        });



        // Refresh page after each print is completed/cancelled.
        window.addEventListener("afterprint", function () {
            if (window._ticketPrintTriggered) {
                window._ticketPrintTriggered = false;
                window.location.reload();
            }
        });

        function formatTicketTravelDate(dateVal) {
            if (!dateVal) {
                return "";
            }
            if (typeof moment !== "undefined" && moment(dateVal).isValid()) {
                return moment(dateVal).format("DD MMM").toUpperCase();
            }
            var parsed = new Date(dateVal);
            if (isNaN(parsed.getTime())) {
                return "";
            }
            var months = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];
            return String(parsed.getDate()).padStart(2, "0") + " " + months[parsed.getMonth()];
        }

        function getAirportCode($input, fallback) {
            var code = ($input.attr("data-code") || $input.val() || fallback || "").toString().trim().toUpperCase();
            if (code.indexOf("(") !== -1) {
                var match = code.match(/\(([A-Z0-9]{3})\)/);
                if (match) {
                    code = match[1];
                }
            }
            return code.substring(0, 3);
        }

        function buildTicketFilename() {
            var firstPax = $(".pax-group").first();
            var title = (($(firstPax).find(".pax-initial").val() || "Mr") + "").trim().toUpperCase();
            var fullName = (($(firstPax).find(".pax-name").val() || "") + "").trim().toUpperCase();
            var nameParts = fullName.split(/\s+/).filter(Boolean);
            var nameAbbr = (nameParts[0] || "PAX").substring(0, 3);

            var pax = parseInt($("#paxCount").val(), 10) || 1;
            var paxPadded = String(pax).padStart(2, "0");

            var from = getAirportCode($("#apiFrom"), "DEL");
            var to = getAirportCode($("#apiTo"), "CCU");
            var travelDate = formatTicketTravelDate($("#apiDate").val());

            var filename = title + " " + nameAbbr + "-" + paxPadded + " " + from + "–" + to;
            if (travelDate) {
                filename += " " + travelDate;
            }

            filename = filename.replace(/[\\/:*?"<>|]/g, "").replace(/\s+/g, " ").trim();
            return filename + ".pdf";
        }

        var TICKET_PDF_CONTENT_WIDTH_MM = 194;

        function createTicketPdfCaptureNode() {
            var existingStage = document.getElementById("ticketPdfCaptureStage");
            if (existingStage) {
                existingStage.remove();
            }

            var source = document.getElementById("ticket");
            var stage = document.createElement("div");
            stage.id = "ticketPdfCaptureStage";
            stage.style.cssText = "position:fixed;left:-20000px;top:0;z-index:-1;width:" + TICKET_PDF_CONTENT_WIDTH_MM + "mm;overflow:visible;background:#fff;";

            var clone = source.cloneNode(true);
            clone.removeAttribute("id");
            clone.classList.add("ticket-pdf-capture");
            clone.style.width = TICKET_PDF_CONTENT_WIDTH_MM + "mm";
            clone.style.maxWidth = TICKET_PDF_CONTENT_WIDTH_MM + "mm";
            clone.style.margin = "0";
            clone.style.boxShadow = "none";

            stage.appendChild(clone);
            document.body.appendChild(stage);

            return { stage: stage, element: clone };
        }

        function removeTicketPdfCaptureNode(capture) {
            if (capture && capture.stage && capture.stage.parentNode) {
                capture.stage.parentNode.removeChild(capture.stage);
            }
        }

        function getTicketPdfOptions(filename) {
            return {
                margin: [6, 8, 12, 8],
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    scrollX: 0,
                    scrollY: 0,
                    backgroundColor: '#ffffff',
                    logging: false,
                    onclone: function (clonedDoc) {
                        var ticket = clonedDoc.querySelector(".ticket-pdf-capture");
                        if (!ticket) {
                            return;
                        }
                        ticket.style.width = TICKET_PDF_CONTENT_WIDTH_MM + "mm";
                        ticket.style.maxWidth = TICKET_PDF_CONTENT_WIDTH_MM + "mm";
                        ticket.style.margin = "0";
                        ticket.style.padding = "8px 8px 18mm 8px";
                        ticket.style.boxSizing = "border-box";
                        ticket.style.boxShadow = "none";
                        ticket.style.display = "block";
                        ticket.style.background = "#fff";
                    }
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
            };
        }

        function collectPassengerNames() {
            return collectPassengerDetails().map(function (p) {
                if (!p.name) {
                    return "";
                }
                return ((p.initial || "") + " " + p.name).trim();
            }).filter(Boolean);
        }

        function collectPassengerDetails() {
            var rows = [];
            $(".pax-group").each(function () {
                rows.push({
                    initial: ($(this).find(".pax-initial").val() || "").trim(),
                    name: ($(this).find(".pax-name").val() || "").trim(),
                    type: ($(this).find(".pax-type").val() || "").trim(),
                    meal: ($(this).find(".pax-meal").val() || "").trim(),
                    seat: ($(this).find(".pax-seat").val() || "").trim(),
                    ticket: ($(this).find(".pax-ticket").val() || "").trim(),
                    services: ($(this).find(".pax-services").val() || "").trim()
                });
            });
            return rows;
        }

        function persistTicketPdf(pdfBlob, filename, done) {
            var reader = new FileReader();
            reader.readAsDataURL(pdfBlob);
            reader.onloadend = function () {
                var pdfBase64 = reader.result;
                var names = collectPassengerNames();
                var from = getAirportCode($("#apiFrom"), "DEL");
                var to = getAirportCode($("#apiTo"), "CCU");
                var sector = from + " - " + to;
                var airlines = [];
                $(".airline-name").each(function () {
                    var txt = $(this).text().trim();
                    if (txt && airlines.indexOf(txt) === -1) {
                        airlines.push(txt);
                    }
                });
                var airline = airlines.length > 0 ? airlines.join(", ") : "-";

                $.post("ajax/save_ticket.php", {
                    edit_id: typeof window.editTicketId !== 'undefined' ? window.editTicketId : 0,
                    pnr: $("#pnrInput").val(),
                    date: $("#bookingDate").val(),
                    pax: $("#paxCount").val(),
                    base: $("#baseInput").val(),
                    tax: $("#taxInput").val(),
                    total: $("#totalInput").val(),
                    passenger_names: names.join(", "),
                    passengers_json: JSON.stringify(collectPassengerDetails()),
                    sector: sector,
                    airline: airline,
                    departure_date: $("#apiDate").val(),
                    arrival_date: $("#apiReturnDate").val(),
                    flight_html: $("#flightDetailsContainer").html(),
                    return_flight_html: $("input[name='tripType']:checked").val() === "roundtrip" ? $("#returnFlightDetailsContainer").html() : "",
                    pdf_data: pdfBase64,
                    pdf_filename: filename
                }).always(function () {
                    if (typeof done === "function") {
                        done();
                    }
                });
            };
        }

        function generateTicketPdfBlob(done) {
            updateTicketData();
            var filename = buildTicketFilename();
            var capture = createTicketPdfCaptureNode();

            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    html2pdf().set(getTicketPdfOptions(filename)).from(capture.element).output('blob').then(function (pdfBlob) {
                        removeTicketPdfCaptureNode(capture);
                        done(pdfBlob, filename);
                    }).catch(function (err) {
                        removeTicketPdfCaptureNode(capture);
                        console.error("Ticket PDF generation failed:", err);
                        alert("Could not generate ticket PDF. Please try again.");
                    });
                });
            });
        }

        function saveTicketOnPrint() {
            generateTicketPdfBlob(function (pdfBlob, filename) {
                persistTicketPdf(pdfBlob, filename, function () {
                    document.title = filename.replace(/\.pdf$/i, "");
                    window._ticketPrintTriggered = true;
                    window.print();
                });
            });
        }

        function downloadTicket() {
            generateTicketPdfBlob(function (pdfBlob, filename) {
                persistTicketPdf(pdfBlob, filename, function () {
                    var link = document.createElement("a");
                    link.href = URL.createObjectURL(pdfBlob);
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    setTimeout(function () {
                        URL.revokeObjectURL(link.href);
                    }, 1000);
                });
            });
        }

        // Removed setupAutosuggest to prevent double dropdown with custom initAirportAutosuggest

        $("#apiTo").on("change blur", function () {
            var val = $(this).val();
            if (val) {
                var city = $(this).attr("data-city");
                // If they have a valid city stored, use it, else fallback to what they typed
                var display = city ? city : val;
                display = display.split(',')[0].replace(/\(.*?\)/g, '').trim();
                $("#hotelCity").text(display.toUpperCase());
            } else {
                $("#hotelCity").text("MUMBAI");
                $(this).removeAttr("data-city");
            }
        });

        function parseViaSearchResponse(res) {
            if (typeof res === "string") {
                try { res = JSON.parse(res); } catch (err) { res = null; }
            }

            var flights = [];
            var rFlights = [];
            if (!res) {
                return { flights: flights, rFlights: rFlights };
            }

            var source = (res.data && (res.data.onwardJourneys || res.data.returnJourneys || res.data.combinedJourneys))
                ? res.data
                : res;

            if (source.onwardJourneys && source.onwardJourneys.length > 0) {
                source.onwardJourneys.forEach(function (journey) {
                    flights.push({ journey: journey, primaryFlight: (journey.flights && journey.flights.length > 0) ? journey.flights[0] : {} });
                });
            }
            if (source.returnJourneys && source.returnJourneys.length > 0) {
                source.returnJourneys.forEach(function (journey) {
                    rFlights.push({ journey: journey, primaryFlight: (journey.flights && journey.flights.length > 0) ? journey.flights[0] : {} });
                });
            } else if (source.combinedJourneys && source.combinedJourneys.length > 0) {
                source.combinedJourneys.forEach(function (journey) {
                    flights.push({ journey: journey, primaryFlight: (journey.flights && journey.flights.length > 0) ? journey.flights[0] : {} });
                });
            } else if (res.flights) {
                flights = res.flights;
            }

            return { flights: flights, rFlights: rFlights };
        }

        function buildViaSearchPayload(sectorInfos, isDomestic, pax) {
            return {
                sectorInfos: sectorInfos,
                prefAirlines: [{ code: 'ALL', name: 'ALL' }],
                "class": 'ALL',
                paxCount: { adt: parseInt(pax, 10) || 1, chd: 0, inf: 0 },
                route: 'ALL',
                disc: false,
                multiHop: false,
                multiCity: false,
                senior: false,
                special: false,
                domestic: !!isDomestic,
                isOfflineSearch: false,
                isPaxWiseCommission: false
            };
        }

        function showFlightSearchPopup() {
            var $overlay = $("#flightSearchLoadingOverlay");
            $overlay.addClass("is-visible").attr("aria-hidden", "false");
            $("body").addClass("flight-search-loading");
        }

        function hideFlightSearchPopup() {
            var $overlay = $("#flightSearchLoadingOverlay");
            $overlay.removeClass("is-visible").attr("aria-hidden", "true");
            $("body").removeClass("flight-search-loading");
            $("#searchFlightsBtn").text("Search").prop("disabled", false);
            $("#searchLoadingIndicator").hide();
        }

        // Also update when clicking Search to ensure it's synced
        $("#searchFlightsBtn").click(function (e) {
            e.preventDefault();

            if ($(this).prop("disabled")) {
                return;
            }

            var fromVal = $("#apiFrom").val().trim();
            var toVal = $("#apiTo").val().trim();

            if (toVal) {
                var city = $("#apiTo").attr("data-city") || toVal;
                city = city.split(',')[0].replace(/\(.*?\)/g, '').trim();
                $("#hotelCity").text(city.toUpperCase());
            }
            var date = $("#apiDate").val().trim();
            var isInternational = $("input[name='flightType']:checked").val() === "international";
            var from = getAirportCode($("#apiFrom"), "");
            var to = getAirportCode($("#apiTo"), "");

            var fromCity = $("#apiFrom").attr("data-city") || fromVal;
            fromCity = fromCity.split(',')[0].replace(/\(.*?\)/g, '').trim();

            var toCity = $("#apiTo").attr("data-city") || toVal;
            toCity = toCity.split(',')[0].replace(/\(.*?\)/g, '').trim();
            var pax = parseInt($("#paxCount").val()) || 1;
            var tType = $("input[name='tripType']:checked").val() === "roundtrip" ? "ROUNDTRIP" : "ONEWAY";
            var returnDate = $("#apiReturnDate").val().trim();

            if (!from || !to || from.length !== 3 || to.length !== 3 || !date || (tType === "ROUNDTRIP" && !returnDate)) {
                alert("Please fill all required search fields and select airports from the suggestions");
                return;
            }

            var sectorInfos = [{
                src: { code: from, name: fromCity || from, city: fromCity || from },
                dest: { code: to, name: toCity || to, city: toCity || to },
                date: moment(date).format('YYYY-MM-DD'),
                debug: false
            }];

            if (tType === "ROUNDTRIP") {
                sectorInfos.push({
                    src: { code: to, name: toCity || to, city: toCity || to },
                    dest: { code: from, name: fromCity || from, city: fromCity || from },
                    date: moment(returnDate).format('YYYY-MM-DD'),
                    debug: false
                });
            }

            var isDomesticSearch = !isInternational;
            var useDualIntlRoundTrip = tType === "ROUNDTRIP" && isInternational;

            function handleFlightSearchSuccess(res, preParsed) {

                    /** Default cabin allowance for every flight-search result. */
                    var DEFAULT_CABIN_BAGGAGE = "07 Kg";
                    var DEFAULT_CHECKIN_BAGGAGE = "15 Kg";

                    function normalizeBagWeight(val) {
                        if (val == null || val === "") return "";
                        if (typeof val === "object") {
                            var pax = val.adt || val.chd || val.inf || val;
                            if (pax && typeof pax === "object") {
                                var qty = pax.qty != null ? pax.qty : (pax.weight != null ? pax.weight : "");
                                var desc = pax.desc || pax.unit || "Kg";
                                if (qty === "" || qty == null) return "";
                                var unit = /kg/i.test(String(desc)) ? "Kg" : String(desc);
                                return String(qty).replace(/\s*kg$/i, "").trim() + " " + unit;
                            }
                            if (val.qty != null || val.weight != null) {
                                return String(val.qty != null ? val.qty : val.weight).replace(/\s*kg$/i, "").trim() + " Kg";
                            }
                            return "";
                        }
                        var s = String(val).trim();
                        if (!s || /^(n\/?a|nil|none|-|null|undefined|—)$/i.test(s)) return "";
                        if (/^\d+(\.\d+)?$/.test(s)) return s + " Kg";
                        return s.replace(/\s*kgs?\s*$/i, " Kg");
                    }

                    function parseCombinedBaggage(text) {
                        var out = { cabin: "", checkin: "" };
                        var str = String(text || "").trim();
                        if (!str) return out;
                        var cabinMatch = str.match(/(?:cabin|hand|carry[\s-]?on)\s*(?:bag(?:gage)?|baggage)?\s*[:\-]?\s*([^|/·•]+)/i);
                        var checkMatch = str.match(/(?:check[\s-]?in|checked|hold)\s*(?:bag(?:gage)?|baggage)?\s*[:\-]?\s*([^|/·•]+)/i);
                        if (cabinMatch) out.cabin = normalizeBagWeight(cabinMatch[1]);
                        if (checkMatch) out.checkin = normalizeBagWeight(checkMatch[1]);
                        if (!out.cabin && !out.checkin && /\d/.test(str)) {
                            if (/cabin|hand|carry/i.test(str) && !/check/i.test(str)) {
                                out.cabin = normalizeBagWeight(str);
                            } else {
                                out.checkin = normalizeBagWeight(str);
                            }
                        }
                        return out;
                    }

                    function pickBagField(obj, keys) {
                        if (!obj || typeof obj !== "object") return "";
                        for (var i = 0; i < keys.length; i++) {
                            if (obj[keys[i]] != null && obj[keys[i]] !== "") {
                                var n = normalizeBagWeight(obj[keys[i]]);
                                if (n) return n;
                            }
                        }
                        return "";
                    }

                    /**
                     * Build baggage label for search results.
                     * Cabin always defaults to 07 Kg when the API omits it.
                     */
                    function formatSearchBaggage(sources) {
                        var cabinKeys = [
                            "hand_baggage", "handBaggage", "handBag", "cabinBaggage", "cabin_baggage",
                            "cBag", "cabinBag", "carryOnBaggage", "carry_on_baggage", "CabinBaggage"
                        ];
                        var checkinKeys = [
                            "checkin_baggage", "checkinBaggage", "checkInBaggage", "check_in_baggage",
                            "iBag", "checkInBag", "checkedBaggage", "checked_baggage", "frBag", "CheckInBaggage"
                        ];
                        var cabin = "";
                        var checkin = "";
                        var list = Array.isArray(sources) ? sources : [sources];
                        list.forEach(function (node) {
                            if (!node || typeof node !== "object") return;
                            if (!cabin) cabin = pickBagField(node, cabinKeys);
                            if (!checkin) checkin = pickBagField(node, checkinKeys);
                            var amenity = node.amenities && node.amenities.baggage;
                            if (amenity && typeof amenity === "object") {
                                if (!cabin) cabin = normalizeBagWeight(amenity.cabin || amenity.hand || amenity.cabinBaggage);
                                if (!checkin) checkin = normalizeBagWeight(amenity.checkin || amenity.checkIn || amenity.checked);
                            }
                            if ((!cabin || !checkin) && typeof node.baggage === "string") {
                                var parsed = parseCombinedBaggage(node.baggage);
                                if (!cabin && parsed.cabin) cabin = parsed.cabin;
                                if (!checkin && parsed.checkin) checkin = parsed.checkin;
                            }
                        });
                        if (!cabin) cabin = DEFAULT_CABIN_BAGGAGE;
                        if (!checkin) checkin = DEFAULT_CHECKIN_BAGGAGE;
                        return {
                            hand_baggage: cabin,
                            checkin_baggage: checkin,
                            baggage: "Cabin: " + cabin + " | Check-in: " + checkin
                        };
                    }

                    function createFlightCard(item, overallDepCode, overallArrCode, overallDepCityName, overallArrCityName) {
                        var journey = item.journey || item;
                        var flightsArray = (journey.flights && journey.flights.length > 0) ? journey.flights : [item.primaryFlight || item];

                        var primaryF = item.primaryFlight || flightsArray[0] || item;
                        var pAirlineObj = primaryF.carrier || primaryF.airline || {};
                        var pFname = pAirlineObj.name || primaryF.airlineName || primaryF.carrierName || "Airline";
                        var pFno = (pAirlineObj.code || "FL") + "-" + (primaryF.flightNo || primaryF.flightNumber || "101");
                        var pCarrierCode = pAirlineObj.code || "6E";
                        var stops = flightsArray.length > 1 ? (flightsArray.length - 1) : 0;
                        var stopsStr = stops === 0 ? "Non Stop" : stops + " Stop(s)";

                        var pDurationRaw = primaryF.flyTime || primaryF.duration || 150;
                        var pDuration = typeof pDurationRaw === 'number' ? Math.floor(pDurationRaw / 60) + " hrs " + (pDurationRaw % 60) + " min" : pDurationRaw;

                        var faresObj = journey.fares || {};
                        var paxFares = (faresObj.paxFares && faresObj.paxFares.adt) ? faresObj.paxFares.adt : {};
                        var totObj = paxFares.total || faresObj.totalFare || primaryF.fareDetails || {};
                        var baseObj = paxFares.base || {};
                        var taxObj = paxFares.tax || {};

                        var base = Math.round(parseFloat(baseObj.amount || baseObj.baseFare || 3500) || 0);
                        var tax = Math.round(parseFloat(taxObj.amount || taxObj.taxes || 500) || 0);
                        var tot = Math.round(parseFloat(totObj.amount || totObj.total || (base + tax)) || 0);

                        var dTimeRaw = (primaryF.depDetail && primaryF.depDetail.time) ? primaryF.depDetail.time : (primaryF.departureTime || primaryF.depTime || "08:00 AM");
                        var aTimeRaw = (primaryF.arrDetail && primaryF.arrDetail.time) ? primaryF.arrDetail.time : (primaryF.arrivalTime || primaryF.arrTime || "10:30 AM");
                        var dTime = moment(dTimeRaw).isValid() ? moment(dTimeRaw).format('DD MMM YYYY | hh:mm A') : dTimeRaw;
                        var aTime = moment(aTimeRaw).isValid() ? moment(aTimeRaw).format('DD MMM YYYY | hh:mm A') : aTimeRaw;

                        var bagInfo = formatSearchBaggage([
                            primaryF,
                            journey,
                            faresObj,
                            paxFares,
                            faresObj.totalFare,
                            item
                        ]);

                        var fData = {
                            fname: pFname, fno: pFno, dTime: dTime, aTime: aTime,
                            base: base, tax: tax, tot: tot,
                            terminal: primaryF.depDetail?.terminal || "T1",
                            stops: stopsStr, duration: pDuration,
                            baggage: bagInfo.baggage,
                            hand_baggage: bagInfo.hand_baggage,
                            checkin_baggage: bagInfo.checkin_baggage,
                            carrierCode: pCarrierCode,
                            segments: flightsArray
                        };
                        var encodedData = encodeURIComponent(JSON.stringify(fData));

                        var dTimeRawUnix = moment(dTimeRaw).valueOf();
                        var aTimeRawUnix = moment(aTimeRaw).valueOf();

                        var cardHtml = `<div class="card mb-2 select-flight-card flight-card-hover" data-flight='${encodedData}' data-duration="${pDurationRaw}" data-dtime="${dTimeRawUnix}" data-atime="${aTimeRawUnix}" data-stops="${stops}" data-airline="${pFname}" data-price="${tot}" style="border: 1px solid #e9ecef; box-shadow: none; border-radius:4px; cursor:pointer; background-color: #f8f9fa;">
                            <div class="card-body p-3">`;

                        flightsArray.forEach(function (f, index) {
                            var airlineObj = f.carrier || f.airline || {};
                            var fname = airlineObj.name || f.airlineName || f.carrierName || "Airline";
                            var fno = (airlineObj.code || "FL") + "-" + (f.flightNo || f.flightNumber || "101");
                            var carrierCode = airlineObj.code || "6E";
                            var logoUrl = "ajax/image_proxy.php?url=" + encodeURIComponent("https://images.kiwi.com/airlines/64/" + carrierCode + ".png");

                            var dTimeRaw = (f.depDetail && f.depDetail.time) ? f.depDetail.time : (f.departureTime || f.depTime || "08:00 AM");
                            var aTimeRaw = (f.arrDetail && f.arrDetail.time) ? f.arrDetail.time : (f.arrivalTime || f.arrTime || "10:30 AM");
                            var dTime = moment(dTimeRaw).isValid() ? moment(dTimeRaw).format('DD MMM YYYY | hh:mm A') : dTimeRaw;
                            var aTime = moment(aTimeRaw).isValid() ? moment(aTimeRaw).format('DD MMM YYYY | hh:mm A') : aTimeRaw;

                            var durationRaw = f.flyTime || f.duration || 150;
                            var duration = typeof durationRaw === 'number' ? Math.floor(durationRaw / 60) + " hrs " + (durationRaw % 60) + " min" : durationRaw;

                            var legDepCity = (f.depDetail && f.depDetail.name) ? f.depDetail.name : (index === 0 ? overallDepCityName : "City");
                            var legDepCode = (f.depDetail && f.depDetail.code) ? f.depDetail.code : (index === 0 ? overallDepCode : "XXX");
                            var legArrCity = (f.arrDetail && f.arrDetail.name) ? f.arrDetail.name : (index === flightsArray.length - 1 ? overallArrCityName : "City");
                            var legArrCode = (f.arrDetail && f.arrDetail.code) ? f.arrDetail.code : (index === flightsArray.length - 1 ? overallArrCode : "XXX");

                            var depTerminal = (f.depDetail && f.depDetail.terminal) ? "Terminal " + f.depDetail.terminal : "Terminal 1";
                            var arrTerminal = (f.arrDetail && f.arrDetail.terminal) ? "Terminal " + f.arrDetail.terminal : "Terminal 1";

                            var borderBottom = index < flightsArray.length - 1 ? "border-bottom: 1px dashed #ddd; padding-bottom: 10px; margin-bottom: 10px;" : "";

                            cardHtml += `
                                <div class="row align-items-center" style="${borderBottom}">
                                    <div class="col-md-4 d-flex align-items-center" style="padding-right:0;">
                                        <img src="${logoUrl}" alt="${carrierCode}" style="width:30px; height:30px; object-fit:contain; margin-right:8px;">
                                        <div>
                                            <div style="font-weight:700; font-size:12px; color:#333; line-height:1.2;">${fname}</div>
                                            <div class="text-muted" style="font-size:11px;">${fno}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3" style="padding-left:5px; padding-right:5px;">
                                        <div style="font-weight:700; font-size:12px; color:#333;">${legDepCity} (${legDepCode})</div>
                                        <div class="text-muted" style="font-size:10px;">${depTerminal}</div>
                                        <div class="text-muted" style="font-size:11px;">${dTime}</div>
                                    </div>
                                    <div class="col-md-3" style="padding-left:5px; padding-right:5px;">
                                        <div style="font-weight:700; font-size:12px; color:#333;">${legArrCity} (${legArrCode})</div>
                                        <div class="text-muted" style="font-size:10px;">${arrTerminal}</div>
                                        <div class="text-muted" style="font-size:11px;">${aTime}</div>
                                    </div>
                                    <div class="col-md-2 text-left" style="padding-left:5px;">
                                        <div style="font-weight:700; font-size:12px; color:#333;">${duration}</div>
                                        <div class="text-muted" style="font-size:11px;">Non Stop</div>
                                        ${index === 0 ? `<div style="font-weight:bold; font-size:14px; color:#e31b23; margin-top:3px;">₹${Math.round(tot).toLocaleString('en-IN')}</div>` : ''}
                                    </div>
                                </div>`;
                        });

                        cardHtml += `</div></div>`;
                        return cardHtml;
                    }

                    console.log("API Result:", res);
                    var parsedResult = preParsed || parseViaSearchResponse(res);
                    var flights = parsedResult.flights || [];
                    var rFlights = parsedResult.rFlights || [];

                    var modalBody = $("#flightsModalBody");
                    modalBody.empty();

                    var sortHtml = `
                    <div class="d-flex justify-content-end align-items-center w-100 mt-2 pb-2" style="font-size: 14px; border-bottom: 1px solid #eee;">
                        <label class="mb-0 mr-2" style="font-weight: 600; color: #555; font-size: 13px;"><i class="fa fa-sort-amount-desc"></i> Sort By:</label>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary flight-sort-btn" data-sort="price" data-order="asc">Price <i class="fa fa-sort"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary flight-sort-btn" data-sort="duration" data-order="asc">Duration <i class="fa fa-sort"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary flight-sort-btn" data-sort="dtime" data-order="asc">Departure <i class="fa fa-sort"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary flight-sort-btn" data-sort="atime" data-order="asc">Arrival <i class="fa fa-sort"></i></button>
                        </div>
                    </div>
                    <div class="w-100 pt-2 pb-2 mb-2" style="font-size: 13px; border-bottom: 1px solid #ddd;">
                        <div class="row m-0">
                            <div class="col-md-5 p-0 d-flex align-items-center">
                                <label class="mb-0 mr-2" style="font-weight: 600; color: #555;"><i class="fa fa-filter"></i> Stops:</label>
                                <div class="btn-group filter-group-stops" role="group">
                                    <button type="button" class="btn btn-sm btn-secondary active flight-filter-btn" data-filter="stops" data-value="all">All</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flight-filter-btn" data-filter="stops" data-value="0">Non-Stop</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flight-filter-btn" data-filter="stops" data-value="1">1 Stop</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flight-filter-btn" data-filter="stops" data-value="2">2+ Stops</button>
                                </div>
                            </div>
                            <div class="col-md-7 p-0 d-flex align-items-center justify-content-end">
                                <label class="mb-0 mr-2" style="font-weight: 600; color: #555;"><i class="fa fa-clock-o"></i> Time:</label>
                                <div class="btn-group filter-group-time" role="group">
                                    <button type="button" class="btn btn-sm btn-secondary active flight-filter-btn" data-filter="time" data-value="all">All</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flight-filter-btn" data-filter="time" data-value="morning">Morning</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flight-filter-btn" data-filter="time" data-value="afternoon">Afternoon</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flight-filter-btn" data-filter="time" data-value="evening">Evening</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flight-filter-btn" data-filter="time" data-value="night">Night</button>
                                </div>
                            </div>
                        </div>
                    </div>`;

                    if (tType === "ROUNDTRIP") {
                        $("#flightsModal .modal-dialog").css("max-width", "1200px");
                        var rightTitle = `${to} - ${from} <span style='color:#ccc'>|</span> ${moment(returnDate).format('DD/MM/YYYY')}`;
                        var leftTitle = `${from} - ${to} <span style='color:#ccc'>|</span> ${moment(date).format('DD/MM/YYYY')}`;
                        $("#flightsModalTitle").html(`<div class="row w-100 m-0"><div class="col-md-6 text-center">${leftTitle}</div><div class="col-md-6 text-center" style="border-left:1px solid #ccc;">${rightTitle}</div></div>${sortHtml}`);

                        var html = '<div class="row mt-2"><div class="col-md-6" id="onwardCol"></div><div class="col-md-6" id="returnCol"></div></div>';
                        modalBody.html(html);

                        if (flights.length > 0) {
                            flights.forEach(f => $("#onwardCol").append(createFlightCard(f, from, to, fromCity, toCity)));
                        } else {
                            $("#onwardCol").html('<div class="alert alert-info">No onward flights found.</div>');
                        }

                        if (rFlights.length > 0) {
                            rFlights.forEach(f => $("#returnCol").append(createFlightCard(f, to, from, toCity, fromCity)));
                        } else {
                            $("#returnCol").html('<div class="alert alert-info">No return flights found.</div>');
                        }
                    } else {
                        $("#flightsModal .modal-dialog").css("max-width", "900px");
                        $("#flightsModalTitle").html(`<div class="w-100 text-center">${from} - ${to} <span style='color:#ccc'>|</span> ${moment(date).format('DD/MM/YYYY')}</div>${sortHtml}`);

                        if (flights.length > 0) {
                            flights.forEach(f => modalBody.append(createFlightCard(f, from, to, fromCity, toCity)));
                        } else {
                            modalBody.html('<div class="alert alert-info"><i class="fa fa-info-circle"></i> No flights found for this route and date. Please try a different search.</div>');
                        }
                    }

                    $("#flightsModal").modal("show");
            }

            function handleFlightSearchError(err) {
                    hideFlightSearchPopup();

                    $("#flightsModalTitle").html(`${from} - ${to} <span style='color:#ccc'>|</span> ${date}`);
                    var modalBody = $("#flightsModalBody");
                    modalBody.empty();

                    var errMsg = (err.responseJSON && err.responseJSON.err && err.responseJSON.err.title)
                        ? err.responseJSON.err.title
                        : "Could not connect to flight search. Please try again.";

                    modalBody.html(`
                        <div class="alert alert-danger" role="alert">
                            <h5 class="alert-heading"><i class="fa fa-exclamation-triangle"></i> Search Failed</h5>
                            <p style="margin-bottom:0; font-size:13px;">${errMsg}</p>
                        </div>
                    `);

                    $("#flightsModal").modal("show");
                    console.error("API proxy failed:", err);
            }

            $("#searchFlightsBtn").text("Searching...").prop("disabled", true);
            $("#searchLoadingIndicator").show();
            showFlightSearchPopup();

            if (useDualIntlRoundTrip) {
                var onwardPayload = buildViaSearchPayload([sectorInfos[0]], false, pax);
                var returnPayload = buildViaSearchPayload([sectorInfos[1]], false, pax);

                $.when(
                    $.ajax({
                        url: "ajax/via_search.php",
                        type: "POST",
                        contentType: "application/json",
                        data: JSON.stringify(onwardPayload)
                    }),
                    $.ajax({
                        url: "ajax/via_search.php",
                        type: "POST",
                        contentType: "application/json",
                        data: JSON.stringify(returnPayload)
                    })
                ).done(function (onwardRes, returnRes) {
                    hideFlightSearchPopup();

                    var onwardParsed = parseViaSearchResponse(onwardRes[0]);
                    var returnParsed = parseViaSearchResponse(returnRes[0]);
                    handleFlightSearchSuccess(null, {
                        flights: onwardParsed.flights,
                        rFlights: returnParsed.flights
                    });
                }).fail(function (err) {
                    handleFlightSearchError(err);
                });
                return;
            }

            $.ajax({
                url: "ajax/via_search.php",
                type: "POST",
                contentType: "application/json",
                data: JSON.stringify(buildViaSearchPayload(sectorInfos, isDomesticSearch, pax)),
                success: function (res) {
                    hideFlightSearchPopup();
                    handleFlightSearchSuccess(res);
                },
                error: handleFlightSearchError
            });
        });

        // ==========================================
        //         AIRPORT AUTOSUGGEST LOGIC
        // ==========================================

        function initAirportAutosuggest(inputId, suggestDivId) {
            var timeoutId = null;
            var reqSeq = 0;
            var inputEl = $("#" + inputId);
            var suggestObj = $("#" + suggestDivId);
            if (!inputEl.length || !suggestObj.length) return;

            function escHtml(v) {
                return $("<div>").text(v == null ? "" : String(v)).html();
            }

            function hideSuggest() {
                suggestObj
                    .removeClass("et-airport-suggest-open")
                    .hide()
                    .css({
                        position: "",
                        left: "",
                        top: "",
                        width: "",
                        right: "",
                        zIndex: "",
                        maxHeight: "",
                        visibility: ""
                    });
                var $field = inputEl.closest(".form-group");
                if ($field.length && suggestObj.parent()[0] !== $field[0]) {
                    $field.append(suggestObj);
                }
            }

            function positionSuggest() {
                if (!inputEl.length || !suggestObj.length) return;
                var rect = inputEl[0].getBoundingClientRect();
                var width = Math.max(rect.width, 240);
                var left = Math.min(rect.left, window.innerWidth - width - 8);
                left = Math.max(8, left);
                var top = rect.bottom + 4;
                var maxH = Math.min(260, Math.max(120, window.innerHeight - top - 12));
                if (suggestObj.parent()[0] !== document.body) {
                    suggestObj.appendTo(document.body);
                }
                suggestObj
                    .addClass("et-airport-suggest-open")
                    .css({
                        position: "fixed",
                        left: left + "px",
                        top: top + "px",
                        width: width + "px",
                        right: "auto",
                        zIndex: 20080,
                        maxHeight: maxH + "px",
                        display: "block",
                        visibility: "visible",
                        background: "#fff",
                        border: "1px solid #ccc",
                        borderRadius: "4px",
                        overflowY: "auto",
                        boxShadow: "0 4px 12px rgba(0,0,0,0.12)"
                    })
                    .show();
            }

            function normalizeItems(res) {
                var items = [];
                if (!res) return items;
                if (Array.isArray(res.r)) items = res.r;
                else if (Array.isArray(res)) items = res;
                else if (res.r && typeof res.r === "object") items = [res.r];
                return items;
            }

            function renderItems(items) {
                suggestObj.empty();
                var html = "";
                var count = 0;
                for (var i = 0; i < items.length; i++) {
                    var item = items[i] || {};
                    var code = item.iata || item.code || "";
                    var city = item.ct || item.cName || item.city || "";
                    var country = item.cnty || item.countryName || item.country || "";
                    var airportName = item.n || item.name || "";
                    if (!city && airportName) city = airportName;
                    var fullStr = city
                        ? (country ? (city + ", " + country + " (" + code + ")") : (city + " (" + code + ")"))
                        : code;
                    if (code && city) {
                        html += '<div class="suggest-item p-2 border-bottom autocomplete-hover" style="cursor:pointer; font-size:14px;" data-code="' + escHtml(code) + '" data-full="' + escHtml(fullStr) + '" data-city="' + escHtml(city) + '">' +
                            '<div class="d-flex justify-content-between">' +
                            '<div><i class="fa fa-plane text-muted mr-1"></i> ' + escHtml(city) +
                            (country ? (' <small class="text-muted">' + escHtml(country) + '</small>') : '') +
                            (airportName && airportName !== city ? ('<div class="text-muted" style="font-size:11px;padding-left:18px;">' + escHtml(airportName) + '</div>') : '') +
                            '</div>' +
                            '<div class="font-weight-bold text-primary">' + escHtml(code) + '</div>' +
                            '</div></div>';
                        count++;
                    }
                }
                if (count > 0) {
                    suggestObj.html(html);
                    positionSuggest();
                } else {
                    hideSuggest();
                }
            }

            function fetchLocalAirports(query, seq) {
                $.ajax({
                    url: "ajax/search_airports.php",
                    type: "GET",
                    dataType: "json",
                    data: { q: query },
                    success: function (res) {
                        if (seq !== reqSeq) return;
                        renderItems(normalizeItems(res));
                    },
                    error: function () {
                        if (seq !== reqSeq) return;
                        hideSuggest();
                    }
                });
            }

            function fetchSuggestions(query) {
                var seq = ++reqSeq;
                $.ajax({
                    url: "ajax/mmt_autosuggest.php",
                    type: "GET",
                    dataType: "json",
                    data: { q: query },
                    success: function (res) {
                        if (seq !== reqSeq) return;
                        var items = normalizeItems(res);
                        if (items.length) {
                            renderItems(items);
                            return;
                        }
                        fetchLocalAirports(query, seq);
                    },
                    error: function () {
                        if (seq !== reqSeq) return;
                        fetchLocalAirports(query, seq);
                    }
                });
            }

            inputEl.on("input keyup", function () {
                var query = $(this).val().trim();
                if (query.length < 2) {
                    hideSuggest();
                    return;
                }
                clearTimeout(timeoutId);
                timeoutId = setTimeout(function () {
                    fetchSuggestions(query);
                }, 250);
            });

            inputEl.on("focus", function () {
                if (suggestObj.children(".suggest-item").length) {
                    positionSuggest();
                }
            });

            $(document).off("mousedown.etAirport" + inputId).on("mousedown.etAirport" + inputId, "#" + suggestDivId + " .suggest-item", function (e) {
                e.preventDefault();
                e.stopPropagation();
                var code = $(this).attr("data-code") || $(this).data("code");
                var fullText = $(this).attr("data-full") || $(this).data("full");
                var city = $(this).attr("data-city") || $(this).data("city");
                inputEl.attr("data-code", code);
                if (city) inputEl.attr("data-city", city);
                inputEl.val(fullText ? fullText : code);
                hideSuggest();
                inputEl.trigger("change");
            });

            $(document).off("mousedown.etAirportDoc" + inputId).on("mousedown.etAirportDoc" + inputId, function (e) {
                if (!$(e.target).closest("#" + inputId).length && !$(e.target).closest("#" + suggestDivId).length) {
                    hideSuggest();
                }
            });

            $(window).off("resize.etAirport" + inputId + " scroll.etAirport" + inputId)
                .on("resize.etAirport" + inputId + " scroll.etAirport" + inputId, function () {
                    if (suggestObj.hasClass("et-airport-suggest-open")) {
                        positionSuggest();
                    }
                });
        }

        initAirportAutosuggest("apiFrom", "apiFromSuggest");
        initAirportAutosuggest("apiTo", "apiToSuggest");

        // Use proper delegated click handler to prevent inline JS escaping bugs
        window.selectedOnwardFare = null;
        window.selectedReturnFare = null;

        var mzFlightTicketBusy = false;
        var mzFlightTicketTimer = null;

        function mzCityLabel(name, code) {
            var city = String(name || "").replace(/\(.*?\)/g, "").split(",")[0].trim();
            return city || code || "—";
        }

        function mzClockFromRaw(raw, fallbackDisplay) {
            if (raw && moment(raw).isValid()) {
                return moment(raw).format("hh:mm A");
            }
            var m = String(fallbackDisplay || "").match(/\|\s*(.+)$/);
            if (m) return m[1].trim();
            if (raw) return String(raw);
            return "—";
        }

        function mzFillAnimatedTicket(f, ctx) {
            var segments = f.segments && f.segments.length ? f.segments : null;
            var first = segments ? segments[0] : null;
            var last = segments ? segments[segments.length - 1] : null;

            var depCode = first && first.depDetail && first.depDetail.code
                ? first.depDetail.code
                : (ctx.depCode || "—");
            var arrCode = last && last.arrDetail && last.arrDetail.code
                ? last.arrDetail.code
                : (ctx.arrCode || "—");
            var depCity = first && first.depDetail && first.depDetail.name
                ? first.depDetail.name
                : (ctx.depCity || depCode);
            var arrCity = last && last.arrDetail && last.arrDetail.name
                ? last.arrDetail.name
                : (ctx.arrCity || arrCode);

            var depRaw = first
                ? ((first.depDetail && first.depDetail.time) || first.departureTime || first.depTime)
                : null;
            var arrRaw = last
                ? ((last.arrDetail && last.arrDetail.time) || last.arrivalTime || last.arrTime)
                : null;

            var airline = f.fname || "";
            var flightNo = f.fno || "";
            if (first) {
                var aObj = first.carrier || first.airline || {};
                if (!airline) airline = aObj.name || first.airlineName || first.carrierName || "";
                if (!flightNo) {
                    flightNo = (aObj.code || "FL") + "-" + (first.flightNo || first.flightNumber || "");
                }
            }

            $("#mzFtDepCity").text(mzCityLabel(depCity, depCode));
            $("#mzFtDepCode").text(depCode || "—");
            $("#mzFtDepTime").text(mzClockFromRaw(depRaw, f.dTime));
            $("#mzFtArrCity").text(mzCityLabel(arrCity, arrCode));
            $("#mzFtArrCode").text(arrCode || "—");
            $("#mzFtArrTime").text(mzClockFromRaw(arrRaw, f.aTime));
            $("#mzFtAirline").text(airline || "");
            $("#mzFtFlightNo").text(flightNo || "");
            // Unavailable fields stay blank — do not invent PNR / gate / seat / passenger
            $("#mzFtPassenger").text("");
            $("#mzFtGate").text("");
            $("#mzFtSeat").text("");
        }

        function showMzFlightTicketThen(f, ctx, done) {
            if (mzFlightTicketBusy) return;
            mzFlightTicketBusy = true;
            mzFillAnimatedTicket(f, ctx || {});

            var $ov = $("#mzFlightTicketOverlay");
            $ov.removeClass("is-leaving").attr("aria-hidden", "false").css("display", "flex");
            // Force reflow so enter transition plays
            void $ov[0].offsetWidth;
            $ov.addClass("is-visible");
            $("body").addClass("mz-flight-ticket-busy");

            var reduce = false;
            try {
                reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
            } catch (eRm) { /* ignore */ }
            var holdMs = reduce ? 450 : 3000;

            clearTimeout(mzFlightTicketTimer);
            mzFlightTicketTimer = setTimeout(function () {
                $ov.addClass("is-leaving");
                setTimeout(function () {
                    $ov.removeClass("is-visible is-leaving").css("display", "none").attr("aria-hidden", "true");
                    $("body").removeClass("mz-flight-ticket-busy");
                    mzFlightTicketBusy = false;
                    if (typeof done === "function") done();
                }, reduce ? 180 : 380);
            }, holdMs);
        }

        function highlightEticketFlight(container) {
            var $target = $(container);
            if (!$target.length) return;
            $target.removeClass("mz-ft-eticket-populated");
            void $target[0].offsetWidth;
            $target.addClass("mz-ft-eticket-populated");
            setTimeout(function () {
                $target.removeClass("mz-ft-eticket-populated");
            }, 1200);
        }

        function applySelectedFlightFromCard($card) {
            var rawData = $card.attr("data-flight");
            var f = JSON.parse(decodeURIComponent(rawData));

            var isReturn = $card.closest("#returnCol").length > 0;
            var isRoundTrip = $("input[name='tripType']:checked").val() === "roundtrip";

            var container = isReturn ? $("#returnFlightDetailsContainer") : $("#flightDetailsContainer");
            var depCode = isReturn ? getAirportCode($("#apiTo"), "BOM") : getAirportCode($("#apiFrom"), "DEL");
            var arrCode = isReturn ? getAirportCode($("#apiFrom"), "DEL") : getAirportCode($("#apiTo"), "BOM");

            var cardHtml = "";
            var segments = f.segments && f.segments.length > 0 ? f.segments : null;

            if (segments) {
                segments.forEach(function (leg, index) {
                    var airlineObj = leg.carrier || leg.airline || {};
                    var fname = airlineObj.name || leg.airlineName || leg.carrierName || f.fname;
                    var fno = (airlineObj.code || "FL") + "-" + (leg.flightNo || leg.flightNumber || f.fno.split('-')[1] || "101");

                    var dTimeRaw = (leg.depDetail && leg.depDetail.time) ? leg.depDetail.time : (leg.departureTime || leg.depTime);
                    var aTimeRaw = (leg.arrDetail && leg.arrDetail.time) ? leg.arrDetail.time : (leg.arrivalTime || leg.arrTime);

                    var dTime = dTimeRaw && moment(dTimeRaw).isValid() ? moment(dTimeRaw).format('DD MMM YYYY | hh:mm A') : (f.dTime || "");
                    var aTime = aTimeRaw && moment(aTimeRaw).isValid() ? moment(aTimeRaw).format('DD MMM YYYY | hh:mm A') : (f.aTime || "");

                    var durationRaw = leg.flyTime || leg.duration || 150;
                    var duration = typeof durationRaw === 'number' ? Math.floor(durationRaw / 60) + " hrs " + (durationRaw % 60) + " min" : durationRaw;

                    var legDepCity = (leg.depDetail && leg.depDetail.name) ? leg.depDetail.name : (index === 0 ? ($("#apiFrom").attr("data-city") || "City") : "City");
                    var legDepCode = (leg.depDetail && leg.depDetail.code) ? leg.depDetail.code : (index === 0 ? depCode : "XXX");
                    var legArrCity = (leg.arrDetail && leg.arrDetail.name) ? leg.arrDetail.name : (index === segments.length - 1 ? ($("#apiTo").attr("data-city") || "City") : "City");
                    var legArrCode = (leg.arrDetail && leg.arrDetail.code) ? leg.arrDetail.code : (index === segments.length - 1 ? arrCode : "XXX");

                    var depTerminal = (leg.depDetail && leg.depDetail.terminal) ? "Terminal " + leg.depDetail.terminal : "";
                    var arrTerminal = (leg.arrDetail && leg.arrDetail.terminal) ? "Terminal " + leg.arrDetail.terminal : "";

                    cardHtml += `
                    <div class="flight-card" style="${index > 0 ? 'margin-top: 0px;' : ''}">
                        <div class="flight-card-header">
                            <div class="flight-num">Flight ${index + 1}</div>
                            <div class="header-label"><img src="img/flight.png" style="width:10px!important;"> Departing</div>
                            <div class="header-label"><img src="img/flight1.png" style="width:10px!important;"> Arriving</div>
                            ${index === 0 ? '<div style="cursor:pointer;" class="trash-icon-container"><i class="fa fa-trash text-muted"></i></div>' : '<div style="width: 14px;"></div>'}
                        </div>
                        <div class="flight-card-body">
                            <div class="flight-info-group airline-info">
                                <div class="airline-name">${fname}</div>
                                <div class="flight-code">${fno}</div>
                            </div>
                            <div class="flight-info-group location-info">
                            <div class="d-flex align-items-center">
                                <div class="city">${legDepCity} (${legDepCode})</div>
                                ${depTerminal ? '<div class="terminal" style="font-size:12px; color:#666;">' + depTerminal + '</div>' : ''}</div>
                                <div class="datetime">${dTime}</div>
                            </div>
                            <div class="flight-info-group location-info">
                            <div class="d-flex align-items-center">
                                <div class="city">${legArrCity} (${legArrCode})</div>
                                ${arrTerminal ? '<div class="terminal" style="font-size:12px; color:#666;">' + arrTerminal + '</div>' : ''}</div>
                                <div class="datetime">${aTime}</div>
                            </div>
                            <div class="flight-meta-right">
                                <div class="stops">Non Stop</div>
                                <div class="duration">${duration}</div>
                            </div>
                        </div>
                        <div class="baggage-info" style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="flex: 1; text-align: left;">${(function () {
                                var bag = String(f.baggage || "").trim();
                                if (bag && !/cabin\s*:/i.test(bag)) {
                                    bag = "Cabin: 07 Kg | " + bag;
                                }
                                if (!bag) {
                                    var cabin = (f.hand_baggage || "").trim() || "07 Kg";
                                    var checkin = (f.checkin_baggage || "").trim();
                                    bag = "Cabin: " + cabin + (checkin ? (" | Check-in: " + checkin) : "");
                                }
                                return bag;
                            })()}</div>`;

                    var nextLeg = segments[index + 1];
                    if (nextLeg) {
                        var nextDTimeRaw = (nextLeg.depDetail && nextLeg.depDetail.time) ? nextLeg.depDetail.time : (nextLeg.departureTime || nextLeg.depTime);
                        if (aTimeRaw && nextDTimeRaw) {
                            var layoverMs = moment(nextDTimeRaw).valueOf() - moment(aTimeRaw).valueOf();
                            if (layoverMs > 0) {
                                var layoverMins = Math.floor(layoverMs / 60000);
                                var layoverStr = Math.floor(layoverMins / 60) + " hrs " + (layoverMins % 60) + " min";
                                cardHtml += `
                                <div style="flex: 1; text-align: center; font-size: 12px; font-weight: 600; color: #555;">
                                    <i class="fa fa-clock-o"></i> Layover: ${layoverStr}
                                </div>
                                <div style="flex: 1;"></div>`;
                            }
                        }
                    }

                    cardHtml += `
                        </div>
                    </div>`;
                });
            } else {
                var singleBagCabin = (f.hand_baggage || "").trim() || "07 Kg";
                var singleBagCheckin = (f.checkin_baggage || "").trim();
                var singleBagText = f.baggage || ("Cabin: " + singleBagCabin + (singleBagCheckin ? (" | Check-in: " + singleBagCheckin) : ""));
                if (singleBagText && !/cabin\s*:/i.test(singleBagText)) {
                    singleBagText = "Cabin: " + singleBagCabin + " | " + singleBagText;
                }
                cardHtml = `
                <div class="flight-card">
                    <div class="flight-card-header">
                        <div class="flight-num">Flight 1</div>
                        <div class="header-label">✈ Departing</div>
                        <div class="header-label">✈ Arriving</div>
                        <div style="cursor:pointer;"><i class="fa fa-trash text-muted"></i></div>
                    </div>
                    <div class="flight-card-body">
                        <div class="flight-info-group airline-info">
                            <div class="airline-name">${f.fname}</div>
                            <div class="flight-code">${f.fno}</div>
                        </div>
                        <div class="flight-info-group location-info">
                            <div class="city">${depCode}</div>
                            ${f.terminal && f.terminal !== "undefined" ? '<div class="terminal" style="font-size:12px; color:#666;">Terminal ' + f.terminal + '</div>' : ''}
                            <div class="datetime">${f.dTime}</div>
                        </div>
                        <div class="flight-info-group location-info">
                            <div class="city">${arrCode}</div>
                            <div class="datetime">${f.aTime}</div>
                        </div>
                        <div class="flight-meta-right">
                            <div class="stops">${f.stops}</div>
                            <div class="duration">${f.duration}</div>
                        </div>
                    </div>
                    <div class="baggage-info">
                        ${singleBagText}
                    </div>
                </div>`;
            }
            container.html(cardHtml);
            highlightEticketFlight(container);

            // Populate Input fields and handle modal
            if (isRoundTrip) {
                if (isReturn) {
                    window.selectedReturnFare = f;
                } else {
                    window.selectedOnwardFare = f;
                }

                var base = (window.selectedOnwardFare ? window.selectedOnwardFare.base : 0) + (window.selectedReturnFare ? window.selectedReturnFare.base : 0);
                var tax = (window.selectedOnwardFare ? window.selectedOnwardFare.tax : 0) + (window.selectedReturnFare ? window.selectedReturnFare.tax : 0);
                var tot = (window.selectedOnwardFare ? window.selectedOnwardFare.tot : 0) + (window.selectedReturnFare ? window.selectedReturnFare.tot : 0);

                $("#baseInput").val(base).trigger("keyup");
                $("#taxInput").val(tax).trigger("keyup");
                $("#totalInput").val(tot).trigger("keyup");

                var logoUrl = "ajax/image_proxy.php?url=" + encodeURIComponent("https://pics.avs.io/200/100/" + f.carrierCode + ".png");
                $("#airlineLogo").attr("src", logoUrl);
                $("#airlineLogoBox").css("display", "flex");

                $card.closest(".col-md-6").find(".select-flight-card").css("border", "1px solid #e9ecef").removeClass("border-success");
                $card.css("border", "2px solid #28a745").addClass("border-success");

                if (window.selectedOnwardFare && window.selectedReturnFare) {
                    setTimeout(function () { $("#flightsModal").modal("hide"); }, 500);
                }
            } else {
                $("#flightsModal").modal("hide");

                window.selectedOnwardFare = f;
                window.selectedReturnFare = null;

                $("#baseInput").val(f.base).trigger("keyup");
                $("#taxInput").val(f.tax).trigger("keyup");
                $("#totalInput").val(f.tot).trigger("keyup");

                var logoUrl2 = "ajax/image_proxy.php?url=" + encodeURIComponent("https://pics.avs.io/200/100/" + f.carrierCode + ".png");
                $("#airlineLogo").attr("src", logoUrl2);
                $("#airlineLogoBox").css("display", "flex");
            }
        }

        $(document).on("click", ".select-flight-card", function () {
            if (mzFlightTicketBusy) return;

            var $card = $(this);
            var rawData = $card.attr("data-flight");
            if (!rawData) return;

            var f;
            try {
                f = JSON.parse(decodeURIComponent(rawData));
            } catch (eParse) {
                return;
            }

            var isReturn = $card.closest("#returnCol").length > 0;
            var depCode = isReturn ? getAirportCode($("#apiTo"), "BOM") : getAirportCode($("#apiFrom"), "DEL");
            var arrCode = isReturn ? getAirportCode($("#apiFrom"), "DEL") : getAirportCode($("#apiTo"), "BOM");
            var depCity = isReturn
                ? (($("#apiTo").attr("data-city") || "").split(",")[0].replace(/\(.*?\)/g, "").trim() || depCode)
                : (($("#apiFrom").attr("data-city") || "").split(",")[0].replace(/\(.*?\)/g, "").trim() || depCode);
            var arrCity = isReturn
                ? (($("#apiFrom").attr("data-city") || "").split(",")[0].replace(/\(.*?\)/g, "").trim() || arrCode)
                : (($("#apiTo").attr("data-city") || "").split(",")[0].replace(/\(.*?\)/g, "").trim() || arrCode);

            showMzFlightTicketThen(f, {
                depCode: depCode,
                arrCode: arrCode,
                depCity: depCity,
                arrCity: arrCity
            }, function () {
                applySelectedFlightFromCard($card);
            });
        });

        $(document).on("click", ".fa-trash", function () {
            $(this).closest(".flight-container").empty();
            if ($("#flightDetailsContainer").is(":empty") && $("#returnFlightDetailsContainer").is(":empty")) {
                $("#airlineLogoBox").hide();
                window.selectedOnwardFare = null;
                window.selectedReturnFare = null;
                $("#baseInput").val(0).trigger("keyup");
                $("#taxInput").val(0).trigger("keyup");
                $("#totalInput").val(0).trigger("keyup");
            }
        });

        $("input[name='tripType']").change(function () {
            if ($(this).val() === "roundtrip") {
                $("#returnDateContainer").show();
                $(".return-flight-title").show();
                $("#returnFlightDetailsContainer").show();
            } else {
                $("#returnDateContainer").hide();
                $(".return-flight-title").hide();
                $("#returnFlightDetailsContainer").hide().empty();
            }
        });

        // Date Validation Logic
        $("#apiDate").on("change", function () {
            var onwardDate = $(this).val();
            if (onwardDate) {
                $("#apiReturnDate").attr("min", onwardDate);
                if ($("#apiReturnDate").val() < onwardDate) {
                    $("#apiReturnDate").val(onwardDate);
                }
            }
        });

        // Flight Sorting Logic
        $(document).on("click", ".flight-sort-btn", function () {
            var $btn = $(this);
            var sortType = $btn.attr("data-sort");
            var currentOrder = $btn.attr("data-order");
            var newOrder = "asc";

            if ($btn.hasClass("active")) {
                newOrder = currentOrder === "asc" ? "desc" : "asc";
            }

            $(".flight-sort-btn").attr("data-order", "asc").removeClass("active btn-secondary").addClass("btn-outline-secondary").find("i").attr("class", "fa fa-sort");

            $btn.attr("data-order", newOrder).removeClass("btn-outline-secondary").addClass("active btn-secondary");
            if (newOrder === "asc") {
                $btn.find("i").attr("class", "fa fa-sort-amount-asc");
            } else {
                $btn.find("i").attr("class", "fa fa-sort-amount-desc");
            }

            function sortCards(containerId) {
                var $container = $(containerId);
                var $cards = $container.find(".select-flight-card");
                if ($cards.length === 0) return;

                $cards.sort(function (a, b) {
                    var valA = parseInt($(a).attr("data-" + sortType));
                    var valB = parseInt($(b).attr("data-" + sortType));

                    if (newOrder === "asc") {
                        return valA - valB;
                    } else {
                        return valB - valA;
                    }
                });

                $cards.detach().appendTo($container);
            }

            if ($("#onwardCol").length) {
                sortCards("#onwardCol");
                sortCards("#returnCol");
            } else {
                sortCards("#flightsModalBody");
            }
        });

        // Flight Filtering Logic
        $(document).on("click", ".flight-filter-btn", function () {
            var $btn = $(this);

            $btn.siblings().removeClass("active btn-secondary").addClass("btn-outline-secondary");
            $btn.removeClass("btn-outline-secondary").addClass("active btn-secondary");

            applyFlightFilters();
        });

        function applyFlightFilters() {
            var stopFilter = $(".filter-group-stops .active").data("value");
            var timeFilter = $(".filter-group-time .active").data("value");

            function filterCards(containerId) {
                var $container = $(containerId);
                if ($container.length === 0) return;

                $container.find(".select-flight-card").each(function () {
                    var $card = $(this);
                    var showCard = true;

                    if (stopFilter !== "all" && showCard) {
                        var cardStops = parseInt($card.attr("data-stops"));
                        if (stopFilter == "0" && cardStops !== 0) showCard = false;
                        if (stopFilter == "1" && cardStops !== 1) showCard = false;
                        if (stopFilter == "2" && cardStops < 2) showCard = false;
                    }

                    if (timeFilter !== "all" && showCard) {
                        var dTimeUnix = parseInt($card.attr("data-dtime"));
                        var hour = moment(dTimeUnix).hour();

                        if (timeFilter === "morning" && (hour < 6 || hour >= 12)) showCard = false;
                        if (timeFilter === "afternoon" && (hour < 12 || hour >= 18)) showCard = false;
                        if (timeFilter === "evening" && (hour < 18 || hour > 23)) showCard = false;
                        if (timeFilter === "night" && (hour > 5)) showCard = false;
                    }

                    if (showCard) {
                        $card.show();
                    } else {
                        $card.hide();
                    }
                });
            }

            if ($("#onwardCol").length) {
                filterCards("#onwardCol");
                filterCards("#returnCol");
            } else {
                filterCards("#flightsModalBody");
            }
        }

    </script>

    <script>

        document.querySelectorAll("#apiToSuggest div").forEach(function (item) {

            item.addEventListener("click", function () {

                let cityName = this.innerText;

                document.getElementById("apiTo").value = cityName;

                document.getElementById("hotelCity").innerText = cityName;

                document.getElementById("apiToSuggest").style.display = "none";

            });

        });

    </script>

    <!-- AI E-Ticket Auto-Fill -->
    <script>
        (function ($) {
            "use strict";

            var etAiXhr = null;
            var etAiPreviewUrl = null;

            function esc(v) {
                return $("<div>").text(v == null ? "" : v).html();
            }

            function mark(sel) {
                $(sel).addClass("et-ai-filled");
            }

            function clearPreview() {
                if (etAiPreviewUrl) {
                    try { URL.revokeObjectURL(etAiPreviewUrl); } catch (e) { /* ignore */ }
                    etAiPreviewUrl = null;
                }
                $("#etAiDrop").removeClass("has-preview");
                $("#etAiThumbWrap").removeClass("is-pdf");
                $("#etAiPreviewImg").removeAttr("src").show();
                $("#etAiPreviewPdf").attr("aria-hidden", "true");
                $("#etAiPreviewName").text("");
                $("#etAiPreview").attr("aria-hidden", "true");
                $("#etAiFile").val("");
            }

            function resetEtAi() {
                if (etAiXhr) {
                    try { etAiXhr.abort(); } catch (e) { /* ignore */ }
                    etAiXhr = null;
                }
                clearPreview();
                setStatus(null);
            }

            function showPreview(file, fileLabel) {
                if (etAiPreviewUrl) {
                    try { URL.revokeObjectURL(etAiPreviewUrl); } catch (e) { /* ignore */ }
                    etAiPreviewUrl = null;
                }
                if (!file) {
                    clearPreview();
                    return;
                }
                var type = String(file.type || "").toLowerCase();
                var label = fileLabel || uploadFileName(file);
                var isPdf = type === "application/pdf" || /\.pdf$/i.test(label);
                $("#etAiPreviewName").text(label);
                if (isPdf) {
                    $("#etAiThumbWrap").addClass("is-pdf");
                    $("#etAiPreviewImg").removeAttr("src");
                    $("#etAiPreviewPdf").attr("aria-hidden", "false");
                } else {
                    $("#etAiThumbWrap").removeClass("is-pdf");
                    $("#etAiPreviewPdf").attr("aria-hidden", "true");
                    etAiPreviewUrl = URL.createObjectURL(file);
                    $("#etAiPreviewImg").attr("src", etAiPreviewUrl).show();
                }
                $("#etAiDrop").addClass("has-preview");
                $("#etAiPreview").attr("aria-hidden", "false");
            }

            function setStatus(state, html) {
                var icons = {
                    uploading: "fas fa-circle-notch fa-spin",
                    processing: "fas fa-circle-notch fa-spin",
                    success: "fas fa-check-circle",
                    warning: "fas fa-exclamation-triangle",
                    error: "fas fa-times-circle"
                };
                var $s = $("#etAiStatus");
                if (!state) {
                    $s.addClass("d-none").empty()
                        .removeClass("is-uploading is-processing is-success is-warning is-error");
                    $("#etAiDrop").removeClass("is-busy");
                    return;
                }
                $s.removeClass("d-none is-uploading is-processing is-success is-warning is-error")
                    .addClass("is-" + state)
                    .html('<i class="' + icons[state] + '"></i><div class="et-ai-msg">' + html + "</div>");
                $("#etAiDrop").toggleClass("is-busy", state === "uploading" || state === "processing");
            }

            function openEtAiPicker() {
                $("#etAiFile").val("").trigger("click");
            }

            function mimeToExt(mime) {
                mime = String(mime || "").toLowerCase();
                if (mime === "image/jpeg" || mime === "image/jpg") {
                    return "jpg";
                }
                if (mime === "image/png") {
                    return "png";
                }
                if (mime === "image/webp") {
                    return "webp";
                }
                if (mime === "application/pdf") {
                    return "pdf";
                }
                return "";
            }

            function isAllowedEticketFile(file) {
                if (!file) {
                    return false;
                }
                var type = String(file.type || "").toLowerCase();
                if (type === "image/jpg") {
                    type = "image/jpeg";
                }
                if (["image/jpeg", "image/png", "image/webp", "application/pdf"].indexOf(type) >= 0) {
                    return true;
                }
                var ext = String(file.name || "").split(".").pop().toLowerCase();
                return ["jpg", "jpeg", "png", "webp", "pdf"].indexOf(ext) >= 0;
            }

            function uploadFileName(file) {
                var name = String((file && file.name) || "").trim();
                if (name && name.indexOf(".") > 0 && name !== "blob" && name !== "image") {
                    return name;
                }
                var ext = mimeToExt(file && file.type) || "png";
                return "pasted-eticket." + ext;
            }

            function fileFromClipboard(e) {
                var data = (e.originalEvent || e).clipboardData;
                if (!data) {
                    return null;
                }
                var items = data.items;
                if (items) {
                    for (var i = 0; i < items.length; i++) {
                        if (items[i].type && items[i].type.indexOf("image/") === 0) {
                            return items[i].getAsFile();
                        }
                    }
                }
                var files = data.files;
                if (files && files.length) {
                    for (var j = 0; j < files.length; j++) {
                        var type = String(files[j].type || "").toLowerCase();
                        if (type.indexOf("image/") === 0 || type === "application/pdf") {
                            return files[j];
                        }
                    }
                }
                return null;
            }

            function handlePasteEvent(e, requireImage) {
                if ($("#etAiDrop").hasClass("is-busy")) {
                    return false;
                }
                var file = fileFromClipboard(e);
                if (!file) {
                    if (requireImage) {
                        e.preventDefault();
                        setStatus("error", "Clipboard has no image. Copy a ticket screenshot or image, then press Ctrl + V here.");
                    }
                    return false;
                }
                e.preventDefault();
                uploadFile(file);
                return true;
            }

            function placeLabel(city, code) {
                if (city && code) {
                    return esc(city) + " (" + esc(code) + ")";
                }
                return esc(city || code || "");
            }

            function segMoment(dateVal, timeVal) {
                if (!dateVal) {
                    return null;
                }
                var m = moment(dateVal + " " + (timeVal || "00:00"), "YYYY-MM-DD HH:mm", true);
                return m.isValid() ? m : null;
            }

            function formatSegDateTime(dateVal, timeVal) {
                var m = segMoment(dateVal, timeVal);
                if (!m) {
                    return "";
                }
                return timeVal ? m.format("DD MMM YYYY | hh:mm A") : m.format("DD MMM YYYY");
            }

            function minutesLabel(mins) {
                if (!mins || mins <= 0) {
                    return "";
                }
                return Math.floor(mins / 60) + " hrs " + (mins % 60) + " min";
            }

            // "1h 20m", "2 hrs 40 min", "0:55" -> minutes
            function parseDurationMinutes(text) {
                text = String(text || "").toLowerCase();
                var hm = text.match(/(\d+)\s*(?:h|hr|hrs|hour|hours)\D*(\d+)?/);
                if (hm) {
                    return (parseInt(hm[1], 10) * 60) + (parseInt(hm[2] || "0", 10));
                }
                var only = text.match(/(\d+)\s*(?:m|min|mins|minutes)/);
                if (only) {
                    return parseInt(only[1], 10);
                }
                var colon = text.match(/^(\d{1,2}):(\d{2})$/);
                return colon ? (parseInt(colon[1], 10) * 60) + parseInt(colon[2], 10) : 0;
            }

            function segDuration(seg) {
                // International tickets print elapsed flying time. Local arrival minus
                // local departure ignores time zones (BOM 11:45–SIN 19:50 is 8h 05m
                // on the clock but 5h 35m in the air).
                var printed = parseDurationMinutes(seg.duration);
                if (printed > 0) {
                    return esc(minutesLabel(printed));
                }
                var dep = segMoment(seg.dep_date, seg.dep_time);
                var arr = segMoment(seg.arr_date, seg.arr_time);
                if (dep && arr && arr.isAfter(dep)) {
                    return esc(minutesLabel(arr.diff(dep, "minutes")));
                }
                return esc(seg.duration || "");
            }

            function baggageText(seg) {
                var parts = [];
                var cabin = (seg && seg.hand_baggage) ? String(seg.hand_baggage).trim() : "";
                var checkin = (seg && seg.checkin_baggage) ? String(seg.checkin_baggage).trim() : "";
                // Flight search / ticket display: cabin defaults to 07 Kg when omitted
                if (!cabin) {
                    cabin = "07 Kg";
                }
                parts.push("Cabin: " + esc(cabin));
                if (checkin) {
                    parts.push("Check-in: " + esc(checkin));
                }
                return parts.join(" | ");
            }

            /** Same markup the flight search produces, so the ticket preview renders identically. */
            function flightCardsHtml(segments) {
                var html = "";
                segments.forEach(function (seg, index) {
                    var duration = segDuration(seg);
                    var depTerminal = seg.from_terminal ? "Terminal " + esc(seg.from_terminal) : "";
                    var arrTerminal = seg.to_terminal ? "Terminal " + esc(seg.to_terminal) : "";
                    var baggage = baggageText(seg);

                    html += '<div class="flight-card"' + (index > 0 ? ' style="margin-top: 0px;"' : "") + ">" +
                        '<div class="flight-card-header">' +
                        '<div class="flight-num">Flight ' + (index + 1) + "</div>" +
                        '<div class="header-label"><img src="img/flight.png" style="width:10px!important;"> Departing</div>' +
                        '<div class="header-label"><img src="img/flight1.png" style="width:10px!important;"> Arriving</div>' +
                        (index === 0
                            ? '<div style="cursor:pointer;" class="trash-icon-container"><i class="fa fa-trash text-muted"></i></div>'
                            : '<div style="width: 14px;"></div>') +
                        "</div>" +
                        '<div class="flight-card-body">' +
                        '<div class="flight-info-group airline-info">' +
                        '<div class="airline-name">' + esc(seg.airline || "") + "</div>" +
                        '<div class="flight-code">' + esc(seg.flight_number || "") + "</div>" +
                        "</div>" +
                        '<div class="flight-info-group location-info">' +
                        '<div class="d-flex align-items-center">' +
                        '<div class="city">' + placeLabel(seg.from_city, seg.from_code) + "</div>" +
                        (depTerminal ? '<div class="terminal" style="font-size:12px; color:#666;">' + depTerminal + "</div>" : "") +
                        "</div>" +
                        '<div class="datetime">' + formatSegDateTime(seg.dep_date, seg.dep_time) + "</div>" +
                        "</div>" +
                        '<div class="flight-info-group location-info">' +
                        '<div class="d-flex align-items-center">' +
                        '<div class="city">' + placeLabel(seg.to_city, seg.to_code) + "</div>" +
                        (arrTerminal ? '<div class="terminal" style="font-size:12px; color:#666;">' + arrTerminal + "</div>" : "") +
                        "</div>" +
                        '<div class="datetime">' + formatSegDateTime(seg.arr_date, seg.arr_time) + "</div>" +
                        "</div>" +
                        '<div class="flight-meta-right">' +
                        '<div class="stops">Non Stop</div>' +
                        '<div class="duration">' + duration + "</div>" +
                        "</div>" +
                        "</div>" +
                        '<div class="baggage-info" style="display: flex; align-items: center; justify-content: space-between;">' +
                        '<div style="flex: 1; text-align: left;">' + baggage + "</div>";

                    var next = segments[index + 1];
                    if (next) {
                        var arr = segMoment(seg.arr_date, seg.arr_time);
                        var nextDep = segMoment(next.dep_date, next.dep_time);
                        if (arr && nextDep) {
                            var layover = minutesLabel(nextDep.diff(arr, "minutes"));
                            if (layover) {
                                html += '<div style="flex: 1; text-align: center; font-size: 12px; font-weight: 600; color: #555;">' +
                                    '<i class="fa fa-clock-o"></i> Layover: ' + esc(layover) + "</div>" +
                                    '<div style="flex: 1;"></div>';
                            }
                        }
                    }

                    html += "</div></div>";
                });
                return html;
            }

            function setAirport(sel, city, code) {
                var $el = $(sel);
                if (!city && !code) {
                    return false;
                }
                $el.val(city && code ? (city + " (" + code + ")") : (city || code));
                if (code) {
                    $el.attr("data-code", code);
                }
                if (city) {
                    $el.attr("data-city", city);
                }
                mark(sel);
                return true;
            }

            function fillPassengers(passengers) {
                if (!passengers.length) {
                    return 0;
                }
                $("#paxCount").val(passengers.length);
                renderPaxForms();
                mark("#paxCount");

                var filledRows = 0;
                passengers.forEach(function (p, index) {
                    var $row = $(".pax-group").eq(index);
                    if (!$row.length) {
                        return;
                    }
                    var set = function (cls, val) {
                        if (!val) {
                            return;
                        }
                        var $f = $row.find(cls);
                        if (cls === ".pax-type" || cls === ".pax-initial") {
                            var exists = $f.find("option").filter(function () {
                                return $(this).text().trim() === val || this.value === val;
                            }).length > 0;
                            if (!exists) {
                                return;
                            }
                        }
                        $f.val(val).addClass("et-ai-filled");
                    };
                    set(".pax-type", p.type);
                    set(".pax-initial", p.title);
                    set(".pax-name", p.name);
                    set(".pax-meal", cleanPaxField(p.meal, false));
                    set(".pax-seat", cleanPaxField(p.seat, false));
                    set(".pax-ticket", cleanPaxField(p.ticket, true));
                    set(".pax-services", cleanPaxField(p.services, false));
                    filledRows++;
                });
                return filledRows;
            }

            function applyTicket(ticket) {
                var f = ticket.fields || {};
                var onward = ticket.onward_segments || [];
                var back = ticket.return_segments || [];
                var passengers = ticket.passengers || [];
                var done = [];
                var blank = [];

                if (f.pnr) {
                    $("#pnrInput").val(f.pnr).trigger("input");
                    mark("#pnrInput");
                    done.push("PNR");
                } else {
                    blank.push("PNR");
                }

                if (f.booking_date) {
                    $("#bookingDate").val(f.booking_date);
                    mark("#bookingDate");
                    done.push("Booking Date");
                }

                // Trip type must be set before injecting cards — One way empties the return container.
                if (f.trip_type === "roundtrip" || back.length) {
                    $("#roundtrip").prop("checked", true).trigger("change");
                    done.push("Round Trip");
                } else if (f.trip_type === "oneway") {
                    $("#oneway").prop("checked", true).trigger("change");
                    done.push("One Way");
                }
                if (f.journey_type === "international") {
                    $("#international").prop("checked", true);
                    done.push("International");
                } else if (f.journey_type === "domestic") {
                    $("#domestic").prop("checked", true);
                    done.push("Domestic");
                }

                var routeParts = [];
                if (setAirport("#apiFrom", f.from_city, f.from_code)) {
                    routeParts.push(f.from_code || f.from_city);
                }
                if (setAirport("#apiTo", f.to_city, f.to_code)) {
                    routeParts.push(f.to_code || f.to_city);
                    $("#apiTo").trigger("change");
                }
                if (routeParts.length) {
                    done.push("Route (" + routeParts.join(" → ") + ")");
                } else {
                    blank.push("From / To");
                }

                if (f.onward_date) {
                    $("#apiDate").val(f.onward_date);
                    mark("#apiDate");
                    done.push("Onward Date");
                } else {
                    blank.push("Onward Date");
                }
                if (f.return_date) {
                    $("#apiReturnDate").val(f.return_date);
                    mark("#apiReturnDate");
                    done.push("Return Date");
                }

                var paxFilled = fillPassengers(passengers);
                if (paxFilled) {
                    done.push("Passengers (" + paxFilled + ")");
                } else {
                    blank.push("Passenger details");
                }

                if (onward.length) {
                    $("#flightDetailsContainer").html(flightCardsHtml(onward));
                    done.push("Onward flight (" + onward.length + (onward.length === 1 ? " leg)" : " legs)"));
                } else {
                    blank.push("Onward flight details");
                }
                if (back.length) {
                    $("#returnFlightDetailsContainer").html(flightCardsHtml(back)).show();
                    $(".return-flight-title").show();
                    $("#returnDateContainer").show();
                    done.push("Return flight (" + back.length + (back.length === 1 ? " leg)" : " legs)"));
                }

                var carrier = f.carrier_code || (onward[0] && onward[0].carrier_code) || "";
                if (carrier) {
                    var logoUrl = "ajax/image_proxy.php?url=" +
                        encodeURIComponent("https://pics.avs.io/200/100/" + carrier + ".png");
                    $("#airlineLogo").attr("src", logoUrl);
                    $("#airlineLogoBox").css("display", "flex");
                }

                // Values are set without firing input so the tax auto-calc keeps the printed tax.
                var fareParts = [];
                if (f.base_fare) {
                    $("#baseInput").val(f.base_fare);
                    mark("#baseInput");
                    fareParts.push("Base");
                }
                if (f.tax) {
                    $("#taxInput").val(f.tax);
                    mark("#taxInput");
                    fareParts.push("Taxes");
                }
                if (f.total_fare) {
                    $("#totalInput").val(f.total_fare);
                    mark("#totalInput");
                    fareParts.push("Total");
                }
                if (fareParts.length) {
                    done.push("Fare (" + fareParts.join(", ") + ")");
                } else {
                    blank.push("Fare details");
                }

                if (f.mobile) {
                    $("#mobileInput").val(f.mobile);
                    $("#contactNo").text(f.mobile);
                    mark("#mobileInput");
                    done.push("Mobile No");
                }
                if (f.email) {
                    $("#emailinput").val(f.email);
                    $("#emailId").text(f.email);
                    mark("#emailinput");
                    done.push("Email Id");
                }

                updateTicketData();
                return { done: done, blank: blank };
            }

            function handleResponse(res, fileLabel) {
                var ticket = res.ticket || {};
                var filled = Array.isArray(ticket.filled) ? ticket.filled : [];

                if (!ticket.is_eticket || !filled.length) {
                    setStatus("warning", esc(res.message || "No details could be read from this file."));
                    return;
                }

                var applied = applyTicket(ticket);
                var lowConfidence = ticket.confidence === "low";
                var html = "<strong>" + applied.done.length + " detail" +
                    (applied.done.length === 1 ? "" : "s") + " filled</strong> from " + esc(fileLabel) +
                    ". Review highlighted fields before saving.";
                if (applied.blank.length) {
                    html += '<div class="et-ai-list">Left blank: ' + esc(applied.blank.join(", ")) + "</div>";
                }
                if (lowConfidence) {
                    html += '<div class="et-ai-list">Low confidence — check every field carefully.</div>';
                }

                setStatus(lowConfidence || applied.blank.length ? "warning" : "success", html);
            }

            function uploadFile(file) {
                if (!file) {
                    return;
                }
                if (!isAllowedEticketFile(file)) {
                    setStatus("error", "Only JPG, JPEG, PNG, WEBP or PDF e-tickets are allowed.");
                    return;
                }
                if (file.size > 8 * 1024 * 1024) {
                    setStatus("error", "File size exceeds 8 MB. Please use a smaller file.");
                    return;
                }
                if (etAiXhr) {
                    try { etAiXhr.abort(); } catch (e) { /* ignore */ }
                }

                var fileLabel = uploadFileName(file);
                showPreview(file, fileLabel);

                var fd = new FormData();
                fd.append("file", file, fileLabel);

                setStatus("uploading", "Uploading… <span class=\"js-et-ai-pct\">0%</span>" +
                    '<div class="et-ai-progress"><span></span></div>');

                var xhr = new XMLHttpRequest();
                etAiXhr = xhr;
                xhr.open("POST", "ajax/scan_eticket.php", true);
                xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
                xhr.upload.onprogress = function (e) {
                    if (!e.lengthComputable) {
                        return;
                    }
                    var pct = Math.round((e.loaded / e.total) * 100);
                    $("#etAiStatus .js-et-ai-pct").text(pct + "%");
                    $("#etAiStatus .et-ai-progress > span").css("width", pct + "%");
                };
                xhr.upload.onload = function () {
                    if (etAiXhr === xhr) {
                        setStatus("processing", "Reading e-ticket with AI…");
                    }
                };
                xhr.onerror = function () {
                    if (etAiXhr === xhr) {
                        etAiXhr = null;
                        setStatus("error", "Upload failed. Check your connection and try again.");
                    }
                };
                xhr.onload = function () {
                    if (etAiXhr !== xhr) {
                        return;
                    }
                    etAiXhr = null;
                    var res = null;
                    try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
                    if (!res || !res.success) {
                        setStatus("error", esc((res && res.message) ||
                            ("Could not read the e-ticket (HTTP " + xhr.status + ").")));
                        return;
                    }
                    handleResponse(res, fileLabel);
                };
                xhr.send(fd);
            }

            $(document).on("click", "#etAiUploadBtn, #etAiReplaceBtn", function (e) {
                e.preventDefault();
                e.stopPropagation();
                openEtAiPicker();
            });
            $(document).on("click", "#etAiClearBtn", function (e) {
                e.preventDefault();
                e.stopPropagation();
                resetEtAi();
            });
            $(document).on("keydown", "#etAiDrop", function (e) {
                if (e.key !== "Enter" && e.key !== " ") {
                    return;
                }
                if ($(e.target).closest("button").length) {
                    return;
                }
                e.preventDefault();
                openEtAiPicker();
            });
            $(document).on("change", "#etAiFile", function () {
                uploadFile(this.files && this.files[0]);
            });
            $(document).on("dragover dragenter", "#etAiDrop", function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass("is-drag");
            });
            $(document).on("dragleave dragend", "#etAiDrop", function (e) {
                e.preventDefault();
                $(this).removeClass("is-drag");
            });
            $(document).on("drop", "#etAiDrop", function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass("is-drag");
                var dt = e.originalEvent && e.originalEvent.dataTransfer;
                if (dt && dt.files && dt.files[0]) {
                    uploadFile(dt.files[0]);
                }
            });
            $(document).on("paste", "#etAiBox", function (e) {
                handlePasteEvent(e, true);
            });
            $(document).on("paste", function (e) {
                if ($(e.target).closest("#etAiBox").length) {
                    return;
                }
                if ($(e.target).closest("input, textarea, select, [contenteditable='true']").length) {
                    return;
                }
                if (!$("#etAiBox").length || !$("#etAiBox").is(":visible")) {
                    return;
                }
                handlePasteEvent(e, false);
            });
            $(document).on("focusin", "#etAiDrop", function () {
                $(this).addClass("is-paste");
            });
            $(document).on("focusout", "#etAiDrop", function () {
                $(this).removeClass("is-paste");
            });
            $(document).on("input change", ".et-ai-filled", function (e) {
                if (e.originalEvent) {
                    $(this).removeClass("et-ai-filled");
                }
            });
        })(jQuery);
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <?php if ($editData): ?>
    <script>
    $(document).ready(function() {
        // Pre-fill fields
        $("#bookingDate").val("<?php echo date('Y-m-d', strtotime($editData['booking_date'])); ?>").trigger('input');
        $("#pnrInput").val("<?php echo addslashes($editData['pnr']); ?>").trigger('input');
        $("#paxCount").val("<?php echo $editData['pax_count']; ?>").trigger('change');
        
        setTimeout(function() {
            $("#baseInput").val("<?php echo $editData['base_fare']; ?>").trigger('input');
            $("#taxInput").val("<?php echo $editData['tax']; ?>").trigger('input');
            $("#totalInput").val("<?php echo $editData['total_fare']; ?>").trigger('input');

            // Fill passenger details
            var savedPassengers = <?php echo json_encode($editPassengers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            if (savedPassengers.length) {
                savedPassengers.forEach(function (p, index) {
                    var $row = $(".pax-group").eq(index);
                    if (!$row.length) {
                        return;
                    }
                    $row.find(".pax-initial").val(p.initial || "Mr");
                    $row.find(".pax-name").val(p.name || "");
                    $row.find(".pax-type").val(p.type || "Adult");
                    $row.find(".pax-meal").val(cleanPaxField(p.meal || "", false));
                    $row.find(".pax-seat").val(cleanPaxField(p.seat || "", false));
                    $row.find(".pax-ticket").val(cleanPaxField(p.ticket || "", true));
                    $row.find(".pax-services").val(cleanPaxField(p.services || "", false));
                });
            } else {
            <?php
            if (!empty($editData['passenger_names'])) {
                $names = array_map('trim', explode(',', $editData['passenger_names']));
                foreach ($names as $index => $fullName) {
                    $parts = preg_split('/\s+/', trim($fullName), 2);
                    $initial = isset($parts[0]) ? $parts[0] : '';
                    $name = isset($parts[1]) ? $parts[1] : '';
                    echo "$('.pax-group').eq($index).find('.pax-initial').val('".addslashes($initial)."');\n";
                    echo "$('.pax-group').eq($index).find('.pax-name').val('".addslashes($name)."');\n";
                }
            }
            ?>
            }
            updateTicketData();

            // Extract Sector if possible
            <?php
            $sector = $editData['sector'];
            if (!empty($sector) && strpos($sector, ' - ') !== false) {
                list($from, $to) = explode(' - ', $sector);
                echo "$('#apiFrom').val('".addslashes($from)."').attr('data-code', '".addslashes($from)."');\n";
                echo "$('#apiTo').val('".addslashes($to)."').attr('data-code', '".addslashes($to)."');\n";
                echo "$('#hotelCity').text('".addslashes(strtoupper($to))."');\n";
            }
            ?>
            
            <?php if (!empty($editData['departure_date'])): ?>
            $("#apiDate").val("<?php echo addslashes($editData['departure_date']); ?>");
            <?php endif; ?>

            // Restore Flight HTML
            <?php if (!empty($editData['flight_html'])): ?>
                $("#flightDetailsContainer").html(`<?php echo str_replace('`', '\`', $editData['flight_html']); ?>`);
            <?php endif; ?>

            <?php if (!empty($editData['return_flight_html'])): ?>
                $("#returnFlightDetailsContainer").html(`<?php echo str_replace('`', '\`', $editData['return_flight_html']); ?>`);
            <?php endif; ?>

            var hasReturnFlight = $("#returnFlightDetailsContainer .flight-card").length > 0;
            if (hasReturnFlight) {
                $("#roundtrip").prop("checked", true);
                $("#returnDateContainer").show();
                $(".return-flight-title").show();
                $("#returnFlightDetailsContainer").show();
                <?php if (!empty($editData['arrival_date'])): ?>
                $("#apiReturnDate").val("<?php echo addslashes($editData['arrival_date']); ?>");
                <?php endif; ?>
            } else {
                $("#oneway").prop("checked", true);
                $("#returnDateContainer").hide();
                $(".return-flight-title").hide();
                $("#returnFlightDetailsContainer").hide().empty();
            }

            var flightCode = $("#flightDetailsContainer .flight-code").first().text().trim();
            if (flightCode) {
                var carrierCode = flightCode.split("-")[0].trim();
                if (carrierCode) {
                    var logoUrl = "ajax/image_proxy.php?url=" + encodeURIComponent("https://pics.avs.io/200/100/" + carrierCode + ".png");
                    $("#airlineLogo").attr("src", logoUrl);
                    $("#airlineLogoBox").css("display", "flex");
                }
            }
        }, 500);

        window.editTicketId = <?php echo $editData['id']; ?>;
    });
    </script>
    <?php endif; ?>
</body>
</html>