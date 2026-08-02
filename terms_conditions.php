<?php include('admin/connection.php'); ?>
<?php
$pageTitle = 'Terms & Conditions';
$breadcrumbActive = 'Terms & Conditions';
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
        .terms-section {
            background: #fff;
        }

        .terms-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 35px;
        }

        .terms-card p,
        .terms-card li {
            color: #555;
            line-height: 1.8;
        }

        .terms-card h2 {
            font-size: 1.45rem;
            margin-top: 28px;
            color: #222;
        }

        .terms-card h3 {
            font-size: 1.1rem;
            margin-top: 18px;
            color: #333;
        }

        .terms-card ul {
            padding-left: 20px;
        }

        @media (max-width: 767px) {
            .terms-card {
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

        <section class="terms-section py-5">
            <div class="container py-2 py-md-4">
                <div class="terms-card" data-aos="fade-up">
                    <p>
                        Welcome to Multizone Travels. By accessing our services, booking travel packages, or using our website,
                        you agree to comply with the following Terms &amp; Conditions. Please read them carefully before making
                        any booking.
                    </p>

                    <h2>1. Booking Confirmation &amp; Payments</h2>
                    <ul>
                        <li>All travel bookings are subject to availability and confirmation.</li>
                        <li>A booking will be considered confirmed only after receiving the required advance payment.</li>
                        <li>The remaining balance must be paid within the timeline mentioned during booking.</li>
                        <li>Payments can be made through approved payment methods such as UPI, bank transfer, cards, or online payment gateways.</li>
                        <li>Failure to complete payment within the specified time may result in cancellation of the booking.</li>
                    </ul>

                    <h2>2. Cancellation &amp; Refunds</h2>
                    <h3>Customer Cancellation</h3>
                    <ul>
                        <li>Cancellation requests must be submitted through email or written communication.</li>
                        <li>Refund eligibility may vary depending on the travel package, hotel, airline, or service provider policies.</li>
                        <li>Any applicable cancellation charges will be deducted before processing refunds.</li>
                        <li>No refund will be provided for unused services, missed departures, or no-shows.</li>
                    </ul>
                    <h3>Refund Processing</h3>
                    <ul>
                        <li>Approved refunds will generally be processed within 7-14 working days.</li>
                        <li>Processing timelines may vary depending on banks or payment gateways.</li>
                    </ul>

                    <h2>3. Changes, Delays &amp; Trip Modifications</h2>
                    <p>
                        Multizone Travels reserves the right to modify, reschedule, or cancel any booking due to circumstances
                        beyond our control, including:
                    </p>
                    <ul>
                        <li>Weather conditions</li>
                        <li>Natural disasters</li>
                        <li>Government restrictions</li>
                        <li>Operational issues</li>
                        <li>Transportation delays</li>
                        <li>Political unrest or emergencies</li>
                    </ul>
                    <p>
                        In such situations, we will try our best to provide suitable alternatives or refunds wherever applicable.
                        However, additional compensation will not be guaranteed.
                    </p>

                    <h2>4. Traveler Responsibilities</h2>
                    <ul>
                        <li>Travelers must provide accurate personal and travel-related information during booking.</li>
                        <li>It is the customer&rsquo;s responsibility to carry valid identification documents, passports, visas, permits, or other required documents.</li>
                        <li>Customers are responsible for reaching departure points on time.</li>
                        <li>Travelers are expected to follow local laws, hotel rules, transport regulations, and tour guidelines throughout the trip.</li>
                    </ul>

                    <h2>5. Health &amp; Safety</h2>
                    <ul>
                        <li>Travelers should ensure they are medically fit for the selected travel activities or destinations.</li>
                        <li>Any medical condition, allergy, or special assistance requirement should be informed to us before travel.</li>
                        <li>Multizone Travels will not be responsible for health-related complications arising during the trip.</li>
                    </ul>

                    <h2>6. Conduct During Travel</h2>
                    <ul>
                        <li>Any unlawful, abusive, violent, or inappropriate behavior during the trip may result in immediate removal from the tour without any refund.</li>
                        <li>Damage caused to hotel property, vehicles, or public property by travelers will be their sole responsibility.</li>
                    </ul>

                    <h2>7. Personal Belongings</h2>
                    <p>
                        Travelers are solely responsible for their luggage, valuables, documents, and personal belongings during
                        the journey. Multizone Travels shall not be liable for any loss, theft, or damage.
                    </p>

                    <h2>8. Travel Insurance</h2>
                    <p>We strongly recommend that all travelers obtain suitable travel insurance covering:</p>
                    <ul>
                        <li>Medical emergencies</li>
                        <li>Accidents</li>
                        <li>Trip cancellations</li>
                        <li>Loss of baggage</li>
                        <li>Delays or interruptions</li>
                    </ul>
                    <p>Any expenses arising from such situations will be borne by the traveler.</p>

                    <h2>9. Use of Photos &amp; Media</h2>
                    <p>
                        Photos or videos captured during tours may be used by Multizone Travels for marketing, social media, or
                        promotional purposes.
                    </p>
                    <p>If you do not wish to appear in such content, please inform us in advance in writing.</p>

                    <h2>10. Limitation of Liability</h2>
                    <p>
                        While we strive to provide a smooth and enjoyable travel experience, Multizone Travels acts only as a
                        booking facilitator for hotels, airlines, transport operators, and other service providers.
                    </p>
                    <p>We shall not be held responsible for:</p>
                    <ul>
                        <li>Delays, cancellations, or disruptions caused by third parties</li>
                        <li>Injury, illness, accidents, or loss during travel</li>
                        <li>Natural disasters or unforeseen circumstances</li>
                        <li>Additional expenses caused by travel interruptions</li>
                    </ul>
                    <p>By booking with us, you acknowledge and accept these risks.</p>

                    <h2>11. Governing Law</h2>
                    <p>
                        These Terms &amp; Conditions shall be governed under the laws of India. Any disputes arising in relation to
                        our services shall fall under the jurisdiction of the applicable courts.
                    </p>

                    <h2>12. Contact Us</h2>
                    <p>For any questions, assistance, or clarification regarding these Terms &amp; Conditions, please contact:</p>
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
