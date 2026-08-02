<?php include('admin/connection.php'); ?>
<?php
$pageTitle = 'Refund & Cancellation Policy';
$breadcrumbActive = 'Refund & Cancellation Policy';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= $pageTitle ?> - Multizone Travels</title>

    <?php include('headerlinks.php'); ?>
    <style>
        .policy-section {
            background: #fff;
        }

        .policy-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 35px;
        }

        .policy-card p,
        .policy-card li {
            color: #555;
            line-height: 1.8;
        }

        .policy-card h2 {
            font-size: 1.45rem;
            margin-top: 28px;
            color: #222;
        }

        .policy-table-wrap {
            margin: 14px 0 24px;
            overflow-x: auto;
        }

        .policy-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 620px;
        }

        .policy-table th,
        .policy-table td {
            border: 1px solid #e5e7eb;
            padding: 12px 14px;
            text-align: left;
            vertical-align: top;
        }

        .policy-table th {
            background: #f8f9fa;
            color: #222;
            font-weight: 600;
        }

        .policy-table td {
            color: #555;
        }

        @media (max-width: 767px) {
            .policy-card {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

    <?php include('header1.php'); ?>
    <?php include('enquiry_modal.php'); ?>

    <main>
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

        <section class="policy-section py-5">
            <div class="container py-2 py-md-4">
                <div class="policy-card" data-aos="fade-up">
                    <p>
                        At Multizone Travels, we understand that travel plans may change due to unexpected situations.
                        Our refund and cancellation policy is designed to be fair and transparent for all travelers.
                    </p>

                    <h2>Domestic &amp; International Tour Packages</h2>
                    <div class="policy-table-wrap">
                        <table class="policy-table">
                            <thead>
                                <tr>
                                    <th>Cancellation Timeline</th>
                                    <th>Refund Eligibility</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>30 days or more before departure</td>
                                    <td>90% refund of total booking amount</td>
                                </tr>
                                <tr>
                                    <td>15-29 days before departure</td>
                                    <td>60% refund of total booking amount</td>
                                </tr>
                                <tr>
                                    <td>7-14 days before departure</td>
                                    <td>30% refund of total booking amount</td>
                                </tr>
                                <tr>
                                    <td>Less than 7 days before departure</td>
                                    <td>No refund applicable</td>
                                </tr>
                                <tr>
                                    <td>No-show on departure day</td>
                                    <td>No refund applicable</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h2>Hotel Bookings</h2>
                    <div class="policy-table-wrap">
                        <table class="policy-table">
                            <thead>
                                <tr>
                                    <th>Booking Type</th>
                                    <th>Refund Policy</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Cancellation made within allowed hotel policy period</td>
                                    <td>Refund as per hotel cancellation rules</td>
                                </tr>
                                <tr>
                                    <td>Last-minute cancellation</td>
                                    <td>Charges may apply</td>
                                </tr>
                                <tr>
                                    <td>Non-refundable hotel deals</td>
                                    <td>No refund applicable</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    

                    <h2>Special Offers &amp; Seasonal Packages</h2>
                    <div class="policy-table-wrap">
                        <table class="policy-table">
                            <thead>
                                <tr>
                                    <th>Package Type</th>
                                    <th>Refund Policy</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Festival / Holiday packages</td>
                                    <td>Limited or no refund</td>
                                </tr>
                                <tr>
                                    <td>Group departures</td>
                                    <td>Refund subject to vendor confirmation</td>
                                </tr>
                                <tr>
                                    <td>Promotional offers &amp; discounted packages</td>
                                    <td>Non-refundable</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h2>If Multizone Travels Cancels a Trip</h2>
                    <p>
                        In rare situations where we must cancel a trip due to unavoidable circumstances such as weather
                        conditions, natural disasters, government restrictions, operational issues, or insufficient bookings:
                    </p>
                    <ul>
                        <li>Customers may receive an alternate travel option or rescheduled departure.</li>
                        <li>Eligible refunds will be processed after deducting non-recoverable expenses, if any.</li>
                        <li>Multizone Travels will not be responsible for additional personal expenses such as shopping, insurance, or missed connections.</li>
                    </ul>

                    <h2>Important Notes</h2>
                    <ul>
                        <li>Cancellation requests must be submitted through email or written communication only.</li>
                        <li>Refund processing may take 7-14 working days depending on the payment method and banking process.</li>
                        <li>Service charges, transaction fees, and insurance charges are generally non-refundable.</li>
                        <li>Refund policies may vary for customized or third-party travel services.</li>
                        <li>Date changes or rescheduling requests are subject to availability and additional charges.</li>
                    </ul>

                    <h2>Contact Us</h2>
                    <p>For cancellation requests or refund-related queries, contact:</p>
                    <p>
                        <strong>Multizone Travels</strong><br>
                        Phone: +91 9709100140<br>
                        Email: info@multizonetravels.com
                    </p>
                </div>
            </div>
        </section>
    </main>

    <?php include('footer.php'); ?>
    <?php include('footerlinks.php'); ?>

</body>

</html>
