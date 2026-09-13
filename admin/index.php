<?php
session_start();
include 'connection.php';
require_once __DIR__ . '/includes/login_lockout.php';
$msg = "";
$msg_type = "error";
if (isset($_SESSION['msg'])) {
  $msg = $_SESSION['msg'];
  $msg_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : (strpos($msg, 'sent') !== false || strpos($msg, 'reset') !== false ? 'success' : 'error');
  unset($_SESSION['msg']);
  unset($_SESSION['msg_type']);
}

$lockoutRemaining = 0;
if (!empty($_SESSION['login_lockout_until'])) {
  $lockoutRemaining = max(0, (int) $_SESSION['login_lockout_until'] - time());
  if ($lockoutRemaining <= 0) {
    unset($_SESSION['login_lockout_until'], $_SESSION['login_lockout_seconds']);
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Multizone Travels — Admin Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php include 'includes/header-links.php'; ?>
  <?php include 'includes/footer-links.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --red: #e31e24;
      --red-dark: #c4181e;
      --red-soft: rgba(227, 30, 36, 0.14);
      --ink: #0a0a0c;
      --text: #141414;
      --muted: #6b7280;
      --line: #e6e8ec;
      --panel: #f4f5f7;
      --card: #ffffff;
      --font: "Manrope", sans-serif;
    }

    html, body {
      min-height: 100%;
      height: 100%;
    }

    body.login-body,
    body {
      font-family: var(--font) !important;
      background: var(--panel) !important;
      color: var(--text);
      overflow-x: hidden;
    }

    /* Kill AdminLTE chrome on this page */
    body .wrapper,
    body .main-header,
    body .main-sidebar,
    body .main-footer,
    body .content-wrapper {
      display: none !important;
    }

    .login-shell {
      min-height: 100vh;
      min-height: 100dvh;
      display: grid;
      grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
      background: var(--panel);
    }

    /* ========== LEFT ========== */
    .hero {
      position: relative;
      background:
        #050506 url("img/login_bg.png") center center / cover no-repeat;
      color: #fff;
      padding: 0;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      isolation: isolate;
      min-height: 100%;
    }

    .hero__img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center center;
      display: block;
      z-index: 0;
      pointer-events: none;
      user-select: none;
    }

    .hero__sr {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }

    .hero-logo {
      position: absolute;
      inset: 0;
      z-index: 2;
      display: block;
      text-indent: -9999px;
      overflow: hidden;
    }

    /* ========== RIGHT ========== */
    .stage {
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: clamp(1.5rem, 4vw, 2.75rem) 1.25rem 1.25rem;
      min-height: 100vh;
      min-height: 100dvh;
      background:
        radial-gradient(circle at 50% 42%, rgba(255,255,255,0.9) 0%, transparent 42%),
        repeating-radial-gradient(
          circle at 50% 42%,
          transparent 0,
          transparent 22px,
          rgba(0, 0, 0, 0.028) 23px,
          transparent 24px
        ),
        var(--panel);
    }

    .card {
      width: 100%;
      max-width: 430px;
      background: var(--card);
      border-radius: 1.5rem;
      padding: 2.4rem 2.2rem 2rem;
      box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.04),
        0 18px 48px rgba(15, 23, 42, 0.10);
      position: relative;
      z-index: 1;
      animation: cardIn 0.55s ease both;
    }

    @keyframes cardIn {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .card__brand {
      display: flex;
      justify-content: center;
      margin-bottom: 1.35rem;
    }

    .card__brand img {
      height: 48px;
      width: auto;
      max-width: 220px;
      object-fit: contain;
    }

    .card__brand-text {
      display: none;
      text-align: center;
      font-family: var(--font);
      font-weight: 800;
      font-size: 1.45rem;
      color: var(--red);
      letter-spacing: -0.01em;
      line-height: 1.05;
    }

    .card__brand-text small {
      display: block;
      margin-top: 0.15rem;
      font-size: 0.68rem;
      letter-spacing: 0.28em;
      font-weight: 700;
    }

    .card__head {
      text-align: center;
      margin-bottom: 1.75rem;
    }

    .card__head h1 {
      font-family: var(--font);
      font-size: 1.75rem;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: #111;
      margin: 0 0 0.45rem;
    }

    .card__head p {
      margin: 0;
      font-size: 0.9rem;
      line-height: 1.45;
      color: var(--muted);
      font-weight: 500;
    }

    .card__head p em {
      font-style: normal;
      color: var(--red);
      font-weight: 700;
    }

    .login-lockout-banner {
      display: none;
      margin: 0 0 1rem;
      padding: 0.8rem 0.95rem;
      border-radius: 0.75rem;
      border: 1px solid rgba(227, 30, 36, 0.22);
      background: rgba(227, 30, 36, 0.07);
      color: #9f1239;
      font-size: 0.84rem;
      line-height: 1.45;
      font-weight: 500;
    }

    .login-lockout-banner.is-visible { display: block; }
    .login-lockout-banner .lock-count {
      font-variant-numeric: tabular-nums;
      font-weight: 800;
    }

    #loginForm.is-locked .btn-login {
      opacity: 0.55;
      cursor: not-allowed;
      pointer-events: none;
    }

    #loginForm.is-locked input { opacity: 0.85; }

    .field { margin-bottom: 1.05rem; }

    .field > label {
      display: block;
      font-size: 0.84rem;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 0.48rem;
    }

    .field-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }

    .field-wrap .f-icon {
      position: absolute;
      left: 0.5rem;
      width: 2.05rem;
      height: 2.05rem;
      border-radius: 0.45rem;
      background: var(--red);
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.82rem;
      pointer-events: none;
      z-index: 2;
      box-shadow: 0 4px 10px rgba(227, 30, 36, 0.28);
    }

    .field-wrap input[type="text"],
    .field-wrap input[type="password"],
    .field-wrap input[type="email"] {
      width: 100%;
      height: 3.15rem;
      padding: 0 2.8rem 0 3.2rem;
      border: 1px solid var(--line);
      border-radius: 0.75rem;
      background: #fff;
      color: #111;
      font-size: 0.94rem;
      font-family: var(--font);
      font-weight: 500;
      outline: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .field-wrap input:focus {
      border-color: rgba(227, 30, 36, 0.55);
      box-shadow: 0 0 0 4px var(--red-soft);
    }

    .field-wrap input::placeholder { color: #9ca3af; font-weight: 500; }

    .toggle-pw {
      position: absolute;
      right: 0.95rem;
      color: #9ca3af;
      cursor: pointer;
      font-size: 1rem;
      z-index: 2;
      transition: color 0.2s ease;
    }

    .toggle-pw:hover { color: var(--red); }

    .row-options {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin: 0.2rem 0 1.35rem;
      flex-wrap: wrap;
    }

    .remember {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.88rem;
      color: #4b5563;
      font-weight: 500;
      cursor: pointer;
      user-select: none;
    }

    .remember input {
      appearance: none;
      -webkit-appearance: none;
      width: 1.1rem;
      height: 1.1rem;
      border: 1.5px solid #d1d5db;
      border-radius: 0.3rem;
      display: inline-grid;
      place-content: center;
      cursor: pointer;
      background: #fff;
      margin: 0;
      transition: 0.15s ease;
    }

    .remember input::before {
      content: "";
      width: 0.58rem;
      height: 0.58rem;
      transform: scale(0);
      transition: transform 0.12s ease;
      box-shadow: inset 1em 1em #fff;
      clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
    }

    .remember input:checked {
      background: var(--red);
      border-color: var(--red);
    }

    .remember input:checked::before { transform: scale(1); }

    .forgot-link {
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--red);
      text-decoration: none;
    }

    .forgot-link:hover {
      color: var(--red-dark);
      text-decoration: underline;
    }

    .btn-login {
      width: 100%;
      height: 3.15rem;
      border: 0;
      border-radius: 0.75rem;
      background: linear-gradient(180deg, #ef2a30 0%, var(--red) 100%);
      color: #fff;
      font-family: var(--font);
      font-size: 1rem;
      font-weight: 800;
      letter-spacing: 0.01em;
      cursor: pointer;
      box-shadow: 0 12px 28px rgba(227, 30, 36, 0.35);
      transition: transform 0.15s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }

    .btn-login:hover {
      filter: brightness(1.03);
      transform: translateY(-1px);
      box-shadow: 0 16px 34px rgba(227, 30, 36, 0.4);
    }

    .btn-login:active { transform: translateY(0); }

    .site-link {
      text-align: center;
      margin-top: 1.2rem;
      font-size: 0.84rem;
      color: var(--muted);
      font-weight: 500;
    }

    .site-link a {
      color: var(--red);
      font-weight: 700;
      text-decoration: none;
    }

    .site-link a:hover { text-decoration: underline; }

    .stage-foot {
      margin-top: 2.1rem;
      color: #9ca3af;
      font-size: 0.8rem;
      font-weight: 600;
      letter-spacing: 0.01em;
    }

    /* Modal */
    .modal-content {
      border: 0;
      border-radius: 1rem;
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
      overflow: hidden;
    }
    .modal-header {
      border-bottom: 1px solid var(--line);
      padding: 1.1rem 1.35rem;
    }
    .modal-footer {
      border-top: 1px solid var(--line);
      padding: 1rem 1.35rem;
    }
    .modal-title {
      font-family: var(--font);
      font-size: 1.05rem;
      font-weight: 800;
    }
    .modal-content .form-control {
      height: 2.85rem;
      border-radius: 0.7rem;
      border: 1px solid var(--line);
      font-family: var(--font);
    }
    .modal-content .form-control:focus {
      border-color: rgba(227, 30, 36, 0.55);
      box-shadow: 0 0 0 4px var(--red-soft);
    }
    .modal-content .form-label {
      font-size: 0.84rem;
      font-weight: 700;
      color: #1f2937;
    }
    .btn-cancel-modal {
      background: #f3f4f6;
      border: 1px solid var(--line);
      color: #4b5563;
      border-radius: 0.6rem;
      padding: 0.55rem 1.1rem;
      font-size: 0.88rem;
      font-weight: 700;
      font-family: var(--font);
      cursor: pointer;
    }
    .btn-send-reset {
      background: var(--red);
      border: 0;
      color: #fff;
      border-radius: 0.6rem;
      padding: 0.55rem 1.15rem;
      font-size: 0.88rem;
      font-weight: 800;
      font-family: var(--font);
      cursor: pointer;
      box-shadow: 0 8px 18px rgba(227, 30, 36, 0.28);
    }

    @media (max-width: 980px) {
      .login-shell { grid-template-columns: 1fr; }
      .hero {
        min-height: 42vh;
        min-height: 38dvh;
      }
      .stage {
        min-height: auto;
        padding-top: 1.5rem;
        padding-bottom: 1.5rem;
      }
      .card { padding: 1.85rem 1.4rem 1.55rem; }
    }

    @media (max-width: 520px) {
      .hero {
        min-height: 34vh;
      }
      .row-options {
        flex-direction: column;
        align-items: flex-start;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .card { animation: none !important; }
    }
  </style>
</head>
<body class="login-body">
  <div class="login-shell">

    <aside class="hero" aria-label="Multi Zone Travels welcome">
      <img class="hero__img" src="img/login_bg.png" alt="">
      <div class="hero__sr">
        <p>Multi Zone Travels. Welcome Back. Manage your travel business from one intelligent dashboard.</p>
      </div>
      <a class="hero-logo" href="../index.php" title="Multi Zone Travels">Multi Zone Travels</a>
    </aside>

    <main class="stage">
      <div class="card" id="loginCard">
        <div class="card__brand">
          <img src="img/web-logo.png" alt="Multi Zone Travels"
               onerror="this.style.display='none'; var t=this.nextElementSibling; if(t){t.style.display='block';}">
          <div class="card__brand-text">Multi Zone<small>TRAVELS</small></div>
        </div>

        <div class="card__head">
          <h1>Dashboard</h1>
          <p>Sign in to your account to continue to your <em>dashboard.</em></p>
        </div>

        <div id="loginLockoutBanner" class="login-lockout-banner<?= $lockoutRemaining > 0 ? ' is-visible' : '' ?>" role="alert" aria-live="polite">
          <i class="fas fa-hourglass-half mr-1"></i>
          Too many failed attempts. Try again in <span class="lock-count" id="lockCountdown"><?= (int) $lockoutRemaining ?></span>s.
        </div>

        <form id="loginForm" action="action.php" method="post" class="<?= $lockoutRemaining > 0 ? 'is-locked' : '' ?>">
          <div class="field">
            <label for="username">Email</label>
            <div class="field-wrap">
              <span class="f-icon" aria-hidden="true"><i class="far fa-envelope"></i></span>
              <input type="text" name="name" id="username" placeholder="Enter your email" required autocomplete="username" <?= $lockoutRemaining > 0 ? 'readonly' : '' ?>>
            </div>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <div class="field-wrap">
              <span class="f-icon" aria-hidden="true"><i class="fas fa-lock"></i></span>
              <input type="password" name="pass" id="password" placeholder="Enter your password" required autocomplete="current-password" <?= $lockoutRemaining > 0 ? 'readonly' : '' ?>>
              <i class="far fa-eye toggle-pw" id="togglePassword" title="Show password" role="button" tabindex="0" aria-label="Show password"></i>
            </div>
          </div>

          <div class="row-options">
            <label class="remember">
              <input type="checkbox" name="remember" value="1" <?= $lockoutRemaining > 0 ? 'disabled' : '' ?>>
              Remember Me
            </label>
            <a href="#" class="forgot-link" data-toggle="modal" data-target="#forgotPasswordModal">Forgot Password?</a>
          </div>

          <button type="submit" name="adminlogin" class="btn-login" id="btnLoginSubmit" <?= $lockoutRemaining > 0 ? 'disabled' : '' ?>>
            <span id="btnLoginLabel">Sign In</span>
          </button>
        </form>

        <div class="site-link">
          Not admin? <a href="../index.php">Return to Website</a>
        </div>
      </div>

      <div class="stage-foot">© Multi Zone Travels</div>
    </main>
  </div>

  <div class="modal fade" id="forgotPasswordModal" tabindex="-1" role="dialog" aria-labelledby="fpmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="fpmLabel"><i class="fas fa-key mr-2" style="color:#e31e24;"></i> Reset Password</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
        </div>
        <form id="forgotPasswordForm" action="action.php" method="post">
          <div class="modal-body p-4">
            <p class="text-muted mb-4" style="color:#6b7280;">Enter your registered email address and we'll send you a reset link.</p>
            <div class="mb-3">
              <label class="form-label" for="resetEmail">Email Address</label>
              <input type="email" name="reset_email" id="resetEmail" class="form-control" placeholder="admin@example.com" required>
            </div>
          </div>
          <div class="modal-footer d-flex justify-content-end" style="gap:0.5rem;">
            <button type="button" class="btn-cancel-modal" data-dismiss="modal">Cancel</button>
            <button type="submit" id="btnForgotSubmit" name="forgot_password" class="btn-send-reset">
              <i class="fas fa-paper-plane mr-1"></i> Send Reset Link
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    (function () {
      var toggle = document.getElementById('togglePassword');
      if (!toggle) return;
      function flip() {
        var inp = document.getElementById('password');
        var show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        toggle.classList.toggle('fa-eye', !show);
        toggle.classList.toggle('fa-eye-slash', show);
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        toggle.setAttribute('title', show ? 'Hide password' : 'Show password');
      }
      toggle.addEventListener('click', flip);
      toggle.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          flip();
        }
      });
    })();

    document.getElementById('loginForm').addEventListener('submit', function (e) {
      if (this.classList.contains('is-locked')) {
        e.preventDefault();
        return;
      }
      var btn = document.getElementById('btnLoginSubmit');
      btn.style.opacity = '.75';
      btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Signing in...';
    });

    (function () {
      var remaining = <?= (int) $lockoutRemaining ?>;
      if (remaining <= 0) return;

      var form = document.getElementById('loginForm');
      var banner = document.getElementById('loginLockoutBanner');
      var countEl = document.getElementById('lockCountdown');
      var btn = document.getElementById('btnLoginSubmit');
      var userInp = document.getElementById('username');
      var passInp = document.getElementById('password');

      function unlockForm() {
        form.classList.remove('is-locked');
        banner.classList.remove('is-visible');
        btn.disabled = false;
        btn.style.opacity = '';
        btn.innerHTML = '<span id="btnLoginLabel">Sign In</span>';
        userInp.readOnly = false;
        passInp.readOnly = false;
        var remember = form.querySelector('input[name="remember"]');
        if (remember) remember.disabled = false;
      }

      function tick() {
        if (remaining <= 0) {
          unlockForm();
          return;
        }
        countEl.textContent = String(remaining);
        btn.innerHTML = '<i class="fas fa-hourglass-half mr-2"></i> Wait ' + remaining + 's';
        remaining -= 1;
        setTimeout(tick, 1000);
      }
      tick();
    })();

    document.getElementById('forgotPasswordForm').addEventListener('submit', function () {
      var btn = document.getElementById('btnForgotSubmit');
      btn.style.opacity = '.75';
      btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sending...';
    });

    <?php if ($msg != ""): ?>
    document.addEventListener('DOMContentLoaded', function () {
      Swal.fire({
        icon: '<?php echo $msg_type; ?>',
        title: '<?php echo $msg_type === "error" ? "Access Denied" : "Notification"; ?>',
        text: <?= json_encode($msg, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        background: '#ffffff',
        color: '#111111',
        confirmButtonColor: '#e31e24',
        confirmButtonText: 'OK'
      });
    });
    <?php endif; ?>
  </script>
</body>
</html>
