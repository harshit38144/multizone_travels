<?php
session_start();
include __DIR__ . '/admin/connection.php';

if (!empty($_SESSION['privilege_traveller_id'])) {
    header('Location: privilege_profile.php');
    exit;
}

if (empty($_SESSION['priv_csrf'])) {
    $_SESSION['priv_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['priv_csrf'];
$pageTitle = 'Privilege member login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Multizone Travels</title>
    <?php include __DIR__ . '/headerlinks.php'; ?>
    <link rel="stylesheet" href="css/privilege-portal.css">
</head>
<body>
    <?php include __DIR__ . '/header1.php'; ?>
    <?php include __DIR__ . '/enquiry_modal.php'; ?>

    <main>
        <section class="page-header">
            <div class="page-header-overlay"></div>
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <h1 class="page-title" data-aos="fade-up">Privilege member</h1>
                        <nav aria-label="breadcrumb" data-aos="fade-up" data-aos-delay="100">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                                <li class="breadcrumb-item active">Login</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-5 bg-light">
            <div class="container">
                <div class="priv-portal-wrap">
                    <div class="card priv-portal-card shadow-sm">
                        <div class="card-header">
                            <i class="fas fa-id-card me-2"></i> Sign in with email
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-4">Enter the email registered on your privilege card. We will send you a one-time code valid for 10 minutes.</p>

                            <div id="privAlert" class="alert priv-portal-alert d-none" role="alert"></div>

                            <div id="stepEmail">
                                <label class="form-label fw-semibold" for="privEmail">Email address</label>
                                <input type="email" class="form-control mb-3" id="privEmail" autocomplete="email" placeholder="you@example.com">
                                <button type="button" class="btn btn-primary w-100" id="btnSendOtp">Send verification code</button>
                            </div>

                            <div id="stepOtp" class="d-none">
                                <p class="small text-muted mb-2">Code sent to <strong id="privEmailShown"></strong></p>
                                <label class="form-label fw-semibold" for="privOtp">6-digit code</label>
                                <input type="text" class="form-control mb-3" id="privOtp" inputmode="numeric" maxlength="8" placeholder="000000" autocomplete="one-time-code">
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-primary" id="btnVerifyOtp">Verify and continue</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnBackEmail">Use a different email</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>
    <?php include __DIR__ . '/footerlinks.php'; ?>

    <script>
    (function () {
        var csrf = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        var api = 'ajax/privilege-otp.php';
        var alertEl = document.getElementById('privAlert');
        var stepEmail = document.getElementById('stepEmail');
        var stepOtp = document.getElementById('stepOtp');

        function showAlert(type, msg) {
            alertEl.className = 'alert priv-portal-alert ' + (type === 'success' ? 'alert-success' : 'alert-danger');
            alertEl.textContent = msg;
            alertEl.classList.remove('d-none');
        }
        function hideAlert() {
            alertEl.classList.add('d-none');
        }

        document.getElementById('btnSendOtp').addEventListener('click', function () {
            hideAlert();
            var email = (document.getElementById('privEmail').value || '').trim();
            if (!email) {
                showAlert('err', 'Please enter your email.');
                return;
            }
            var btn = this;
            btn.disabled = true;
            fetch(api, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'send_otp', email: email, csrf: csrf })
            }).then(function (r) { return r.json(); }).then(function (data) {
                btn.disabled = false;
                if (data.ok) {
                    document.getElementById('privEmailShown').textContent = email;
                    stepEmail.classList.add('d-none');
                    stepOtp.classList.remove('d-none');
                    showAlert('success', data.message || 'Code sent.');
                } else {
                    showAlert('err', data.message || 'Something went wrong.');
                }
            }).catch(function () {
                btn.disabled = false;
                showAlert('err', 'Network error. Try again.');
            });
        });

        document.getElementById('btnVerifyOtp').addEventListener('click', function () {
            hideAlert();
            var email = (document.getElementById('privEmail').value || '').trim();
            var otp = (document.getElementById('privOtp').value || '').replace(/\D/g, '');
            if (otp.length !== 6) {
                showAlert('err', 'Enter the 6-digit code from your email.');
                return;
            }
            var btn = this;
            btn.disabled = true;
            fetch(api, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'verify_otp', email: email, otp: otp, csrf: csrf })
            }).then(function (r) { return r.json(); }).then(function (data) {
                btn.disabled = false;
                if (data.ok && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    showAlert('err', data.message || 'Verification failed.');
                }
            }).catch(function () {
                btn.disabled = false;
                showAlert('err', 'Network error. Try again.');
            });
        });

        document.getElementById('btnBackEmail').addEventListener('click', function () {
            hideAlert();
            stepOtp.classList.add('d-none');
            stepEmail.classList.remove('d-none');
            document.getElementById('privOtp').value = '';
        });
    })();
    </script>
</body>
</html>
