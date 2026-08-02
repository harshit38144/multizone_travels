<?php include('admin/connection.php'); ?>
<?php
$pageTitle = 'Privacy Policy';
$breadcrumbActive = 'Privacy Policy';
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

        .policy-card h3 {
            font-size: 1.1rem;
            margin-top: 18px;
            color: #333;
        }

        .policy-card ul {
            padding-left: 20px;
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
                        At Multizone Travels, your privacy matters to us. We are dedicated to keeping your personal
                        information safe and using it responsibly. This Privacy Policy explains what information we collect,
                        how we use it, and the choices you have regarding your data when using our website, travel services,
                        or contacting us.
                    </p>

                    <h2>1. Information We Collect</h2>
                    <p>To provide smooth and secure travel services, we may collect certain details from you, including:</p>

                    <h3>Personal Details</h3>
                    <p>Your name, mobile number, email address, home address, and emergency contact details.</p>

                    <h3>Travel and Booking Information</h3>
                    <p>
                        Tour packages, hotel bookings, transport preferences, travel dates, payment details, and related
                        booking information.
                    </p>

                    <h3>Identity Documents</h3>
                    <p>
                        For selected services, we may require passport copies, ID proofs, or other travel-related documents
                        as required by airlines, hotels, or government authorities.
                    </p>

                    <h3>Website Usage Information</h3>
                    <p>
                        Details such as your IP address, browser type, device information, and website activity to help us
                        improve user experience.
                    </p>

                    <h3>Communication Data</h3>
                    <p>
                        Information shared when you contact us through calls, emails, WhatsApp, social media, or inquiry forms.
                    </p>

                    <h2>2. How We Use Your Information</h2>
                    <p>Your information is used to:</p>
                    <ul>
                        <li>Confirm and manage your bookings.</li>
                        <li>Provide travel assistance and customer support.</li>
                        <li>Send trip updates, invoices, confirmations, or important notifications.</li>
                        <li>Improve our services and website performance.</li>
                        <li>Share travel offers, deals, and promotional updates.</li>
                        <li>Maintain safety, verification, and legal compliance.</li>
                    </ul>

                    <h2>3. Sharing of Information</h2>
                    <p>We value your trust and do not sell your personal information.</p>
                    <p>However, your information may be shared with:</p>
                    <ul>
                        <li>Airlines, hotels, transport providers, and tour partners for booking purposes.</li>
                        <li>Secure payment service providers for transaction processing.</li>
                        <li>Government or legal authorities if required under applicable laws.</li>
                        <li>Emergency services or contacts in urgent situations.</li>
                    </ul>

                    <h2>4. Data Protection</h2>
                    <p>
                        We take appropriate technical and security measures to protect your data against unauthorized access,
                        loss, or misuse. While we work hard to secure your information, no online platform can guarantee
                        complete security.
                    </p>

                    <h2>5. Your Rights and Choices</h2>
                    <p>You have the right to:</p>
                    <ul>
                        <li>Review or update your personal details.</li>
                        <li>Request removal of your information where applicable.</li>
                        <li>Unsubscribe from promotional communications anytime.</li>
                        <li>Ask questions regarding how your data is handled.</li>
                    </ul>
                    <p>For any such requests, feel free to contact us directly.</p>

                    <h2>6. Cookies Policy</h2>
                    <p>
                        Our website may use cookies and similar technologies to improve website functionality and personalize
                        your browsing experience. You can disable cookies through your browser settings if preferred.
                    </p>

                    <h2>7. External Website Links</h2>
                    <p>
                        Our website may contain links to third-party websites or travel partners. We are not responsible for
                        their content or privacy practices. We recommend reviewing their privacy policies before sharing
                        information.
                    </p>

                    <h2>8. Updates to This Policy</h2>
                    <p>
                        Multizone Travels reserves the right to update or modify this Privacy Policy at any time. Any changes
                        will be updated on this page with the latest revision date.
                    </p>

                    <h2>9. Contact Information</h2>
                    <p>For questions, support, or privacy-related concerns, please contact us:</p>
                    <p>
                        <strong>Multizone Travels</strong><br>
                        Phone: +91 9709100140<br>
                        Email: info@multizonetravels.com<br>
                    </p>
                </div>
            </div>
        </section>
    </main>

    <?php include('footer.php'); ?>
    <?php include('footerlinks.php'); ?>

</body>

</html>
