<?php
session_start();
include 'connection.php';
$msg = "";
$msg_type = "error";
if (isset($_SESSION['msg'])) {
  $msg = $_SESSION['msg'];
  $msg_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : (strpos($msg, 'sent') !== false || strpos($msg, 'reset') !== false ? 'success' : 'error');
  unset($_SESSION['msg']);
  unset($_SESSION['msg_type']);
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
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    :root {
      --admin-font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
      --primary:   #6366f1;
      --primary-d: #4338ca;
      --accent:    #06b6d4;
      --gold:      #f59e0b;
      --dark:      #0a0a1a;
      --card-bg:   rgba(255,255,255,0.04);
      --border:    rgba(255,255,255,0.10);
      --text:      #e2e8f0;
      --muted:     #64748b;
    }

    body {
      font-family: var(--admin-font-family);
      background: var(--dark);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow-x: hidden;
      overflow-y: auto;
      color: var(--text);
    }

    /* ── Canvas particles ── */
    #particles { position:fixed; inset:0; z-index:0; pointer-events:none; }

    /* ── Ambient glows ── */
    .glow { position:fixed; border-radius:50%; filter:blur(120px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:rgba(99,102,241,0.18); top:-150px; left:-150px; }
    .glow-2 { width:500px; height:500px; background:rgba(6,182,212,0.12); bottom:-100px; right:-100px; }
    .glow-3 { width:350px; height:350px; background:rgba(245,158,11,0.08); top:40%; left:40%; transform:translate(-50%,-50%); }

    /* ── Floating travel images ── */
    .float-img {
      position:fixed; z-index:999; pointer-events:none;
      filter:drop-shadow(0 20px 40px rgba(0,0,0,0.6));
    }
    .fi-1 { top:6%;  left:3%;  width:160px; animation:fi1 14s ease-in-out infinite; }
    .fi-2 { top:8%;  right:3%; width:190px; animation:fi2 17s ease-in-out infinite; }
    .fi-3 { bottom:5%; left:4%; width:200px; animation:fi3 15s ease-in-out infinite; }
    .fi-4 { bottom:8%; right:3%; width:170px; animation:fi4 16s ease-in-out infinite; }

    @keyframes fi1 { 0%,100%{transform:translateY(0) rotate(-4deg) scale(1)}   50%{transform:translateY(-28px) rotate(4deg) scale(1.04)} }
    @keyframes fi2 { 0%,100%{transform:translateY(0) rotate(3deg) scale(1)}    50%{transform:translateY(24px) rotate(-8deg) scale(.96)} }
    @keyframes fi3 { 0%,100%{transform:translateY(0) rotate(2deg) scale(1)}    50%{transform:translateY(-18px) rotate(-3deg) scale(1.03)} }
    @keyframes fi4 { 0%,100%{transform:translateY(0) rotate(-2deg) scale(1)}   50%{transform:translateY(30px) rotate(6deg) scale(1.06)} }

    /* ── Main wrapper ── */
    .wrapper {
      position:relative; z-index:10;
      display:flex;
      width:900px; max-width:96vw;
      min-height:560px;
      border-radius:28px;
      overflow:hidden;
      box-shadow:
        0 40px 80px rgba(0,0,0,0.6),
        0 0 0 1px rgba(255,255,255,0.07),
        inset 0 1px 0 rgba(255,255,255,0.08);
      transform-style:preserve-3d;
    }

    /* ── Left panel ── */
    .panel-left {
      flex:1.1;
      background:
        url('../images/trty.jpg') center center / cover no-repeat;
      padding:50px 40px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      position:relative;
      overflow:hidden;
    }
    .panel-left::before {
      content:'';
      position:absolute; inset:0;
      background:
        linear-gradient(160deg, rgba(10,8,40,0.82) 0%, rgba(5,20,50,0.75) 50%, rgba(0,10,30,0.88) 100%),
        radial-gradient(ellipse at 30% 20%, rgba(99,102,241,0.30) 0%, transparent 60%),
        radial-gradient(ellipse at 70% 80%, rgba(6,182,212,0.20) 0%, transparent 55%);
      pointer-events:none;
    }

    /* grid pattern overlay */
    .panel-left::after {
      content:'';
      position:absolute; inset:0;
      background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
      background-size:40px 40px;
      pointer-events:none;
    }

    .brand { position:relative; z-index:2; }
    .brand-logo {
      display:flex; align-items:center; gap:12px; margin-bottom:48px;
    }
    .logo-icon {
      width:44px; height:44px;
      background:linear-gradient(135deg, var(--primary), var(--accent));
      border-radius:12px;
      display:flex; align-items:center; justify-content:center;
      font-size:20px;
      box-shadow:0 8px 20px rgba(99,102,241,0.4);
    }
    .logo-text { font-size:18px; font-weight:700; letter-spacing:.5px; }
    .logo-text span { color:var(--accent); }

    .panel-headline {
      position:relative; z-index:2;
    }
    .panel-headline h1 {
      font-family: var(--admin-font-family);
      font-size:38px; font-weight:700;
      line-height:1.2;
      margin-bottom:16px;
      background:linear-gradient(135deg, #fff 30%, #a5b4fc 100%);
      -webkit-background-clip:text; -webkit-text-fill-color:transparent;
    }
    .panel-headline p {
      font-size:14px; color:#94a3b8; line-height:1.7;
      max-width:280px;
    }

    .feature-list {
      position:relative; z-index:2;
      list-style:none; margin-top:40px;
      display:flex; flex-direction:column; gap:14px;
    }
    .feature-list li {
      display:flex; align-items:center; gap:12px;
      font-size:13px; color:#94a3b8;
    }
    .feature-icon {
      width:32px; height:32px; border-radius:8px;
      background:rgba(99,102,241,0.15);
      border:1px solid rgba(99,102,241,0.3);
      display:flex; align-items:center; justify-content:center;
      font-size:14px; flex-shrink:0;
    }

    .panel-footer { position:relative; z-index:2; margin-top:auto; }
    .panel-footer p { font-size:11px; color:#334155; }

    /* ── Right panel (form) ── */
    .panel-right {
      flex:1;
      background: rgba(8,10,26,0.92);
      backdrop-filter:blur(30px);
      padding:50px 40px;
      display:flex;
      flex-direction:column;
      justify-content:center;
      position:relative;
      border-left:1px solid rgba(255,255,255,0.06);
    }

    /* top-right corner glow */
    .panel-right::before {
      content:'';
      position:absolute; top:-80px; right:-80px;
      width:250px; height:250px;
      background:radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);
      pointer-events:none;
    }

    .form-title { margin-bottom:32px; }
    .form-title .badge-admin {
      display:inline-flex; align-items:center; gap:6px;
      font-size:11px; font-weight:600; letter-spacing:1.2px;
      text-transform:uppercase;
      color:var(--accent);
      background:rgba(6,182,212,0.1);
      border:1px solid rgba(6,182,212,0.25);
      border-radius:20px; padding:4px 12px;
      margin-bottom:14px;
    }
    .form-title h2 {
      font-size:26px; font-weight:700; color:#fff;
      margin-bottom:6px; letter-spacing:-.3px;
    }
    .form-title p { font-size:13px; color:var(--muted); }

    /* form fields */
    .field { margin-bottom:20px; }
    .field label {
      display:block; font-size:12px; font-weight:500;
      color:#94a3b8; margin-bottom:8px; letter-spacing:.3px;
    }
    .field-wrap {
      position:relative; display:flex; align-items:center;
    }
    .field-wrap .f-icon {
      position:absolute; left:14px;
      color:#475569; font-size:14px; pointer-events:none;
      transition:color .3s;
    }
    .field-wrap:focus-within .f-icon { color:var(--primary); }
    .field-wrap input {
      width:100%;
      padding:13px 14px 13px 42px;
      background:rgba(255,255,255,0.04);
      border:1px solid rgba(255,255,255,0.08);
      border-radius:12px;
      color:#e2e8f0; font-size:14px;
      font-family: var(--admin-font-family);
      outline:none;
      transition:all .3s ease;
    }
    .field-wrap input:focus {
      border-color:rgba(99,102,241,0.6);
      background:rgba(99,102,241,0.06);
      box-shadow:0 0 0 4px rgba(99,102,241,0.12);
    }
    .field-wrap input::placeholder { color:#334155; }
    .toggle-pw {
      position:absolute; right:14px;
      color:#475569; cursor:pointer; font-size:14px;
      transition:color .3s;
    }
    .toggle-pw:hover { color:#94a3b8; }

    .row-fp {
      display:flex; justify-content:flex-end; margin-top:8px;
    }
    .row-fp a {
      font-size:12px; color:var(--primary);
      text-decoration:none; transition:color .3s;
    }
    .row-fp a:hover { color:#a5b4fc; }

    /* login button */
    .btn-login {
      width:100%; padding:14px;
      background:linear-gradient(135deg, var(--primary) 0%, var(--primary-d) 100%);
      border:none; border-radius:12px;
      color:#fff; font-size:15px; font-weight:600;
      font-family: var(--admin-font-family);
      cursor:pointer; margin-top:8px;
      position:relative; overflow:hidden;
      transition:all .3s ease;
      box-shadow:0 10px 30px rgba(99,102,241,0.35);
      letter-spacing:.3px;
    }
    .btn-login::before {
      content:'';
      position:absolute; inset:0;
      background:linear-gradient(135deg, rgba(255,255,255,0.15) 0%, transparent 100%);
      opacity:0; transition:opacity .3s;
    }
    .btn-login:hover::before { opacity:1; }
    .btn-login:hover {
      transform:translateY(-2px);
      box-shadow:0 18px 40px rgba(99,102,241,0.45);
    }
    .btn-login:active { transform:translateY(0); }

    /* divider */
    .or-divider {
      display:flex; align-items:center; gap:12px;
      margin:22px 0; color:#334155; font-size:12px;
    }
    .or-divider::before, .or-divider::after {
      content:''; flex:1; height:1px; background:rgba(255,255,255,0.07);
    }

    /* social row */
    .social-row {
      display:flex; gap:10px; margin-bottom:28px;
    }
    .s-btn {
      flex:1; padding:11px;
      background:rgba(255,255,255,0.04);
      border:1px solid rgba(255,255,255,0.08);
      border-radius:11px;
      color:#94a3b8; font-size:13px;
      display:flex; align-items:center; justify-content:center; gap:8px;
      cursor:pointer; transition:all .3s; text-decoration:none;
    }
    .s-btn:hover {
      background:rgba(255,255,255,0.08);
      border-color:rgba(255,255,255,0.15);
      color:#fff;
    }
    .s-btn i { font-size:15px; }

    /* bottom link */
    .bottom-link {
      text-align:center; margin-top:24px;
      font-size:13px; color:#475569;
    }
    .bottom-link a {
      color:#94a3b8; font-weight:600; text-decoration:none; transition:color .3s;
    }
    .bottom-link a:hover { color:#fff; }

    /* ── Modal ── */
    .modal-content {
      background:#0f1524;
      color:#e2e8f0;
      border:1px solid rgba(255,255,255,0.08);
      border-radius:20px;
    }
    .modal-header { border-bottom:1px solid rgba(255,255,255,0.07); padding:20px 24px; }
    .modal-footer { border-top:1px solid rgba(255,255,255,0.07); padding:16px 24px; }
    .modal-title { font-size:16px; font-weight:600; }
    .modal-content .form-control {
      background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.09);
      color:#e2e8f0; border-radius:10px; padding:11px 14px; font-size:14px;
    }
    .modal-content .form-control:focus {
      background:rgba(99,102,241,0.08); border-color:rgba(99,102,241,0.5);
      box-shadow:0 0 0 3px rgba(99,102,241,0.12); color:#fff; outline:none;
    }
    .modal-content .form-label { font-size:12px; color:#94a3b8; margin-bottom:6px; display:block; font-weight:500; }
    .modal-content .text-muted { color:#64748b !important; font-size:13px; }
    .close { color:#64748b; opacity:1; text-shadow:none; font-size:20px; }
    .close:hover { color:#e2e8f0; }
    .btn-cancel-modal {
      background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1);
      color:#94a3b8; border-radius:9px; padding:9px 20px; font-size:13px;
      cursor:pointer; transition:all .3s; font-family: var(--admin-font-family);
    }
    .btn-cancel-modal:hover { background:rgba(255,255,255,0.1); color:#fff; }
    .btn-send-reset {
      background:linear-gradient(135deg, var(--primary), var(--primary-d));
      border:none; color:#fff; border-radius:9px; padding:9px 22px;
      font-size:13px; font-weight:600; cursor:pointer;
      box-shadow:0 6px 16px rgba(99,102,241,0.3); font-family: var(--admin-font-family);
      transition:all .3s;
    }
    .btn-send-reset:hover { transform:translateY(-1px); box-shadow:0 10px 24px rgba(99,102,241,0.4); }

    /* ── Mobile brand bar (shown only on mobile) ── */
    .mobile-brand {
      display:none;
      align-items:center; gap:10px;
      margin-bottom:28px;
    }
    .mobile-brand .logo-icon {
      width:38px; height:38px; border-radius:10px;
      background:linear-gradient(135deg, var(--primary), var(--accent));
      display:flex; align-items:center; justify-content:center;
      font-size:17px;
      box-shadow:0 6px 16px rgba(99,102,241,0.4);
      flex-shrink:0;
    }
    .mobile-brand .logo-text { font-size:16px; font-weight:700; }
    .mobile-brand .logo-text span { color:var(--accent); }

    /* ── Responsive: tablet ── */
    @media (max-width:860px) {
      .wrapper { width:95vw; }
      .panel-left { padding:40px 28px; }
      .panel-right { padding:40px 28px; }
      .panel-headline h1 { font-size:30px; }
    }

    /* ── Responsive: mobile ── */
    @media (max-width:640px) {
      body { align-items:flex-start; padding:0; }

      .wrapper {
        flex-direction:column;
        width:100%;
        min-height:100vh;
        border-radius:0;
        box-shadow:none;
      }

      /* hide left panel on mobile */
      .panel-left { display:none; }

      .panel-right {
        flex:1;
        padding:36px 24px 40px;
        border-left:none;
        min-height:100vh;
        justify-content:flex-start;
        /* travel bg behind form on mobile */
        background:
          linear-gradient(160deg, rgba(10,8,40,0.94) 0%, rgba(5,20,50,0.90) 100%),
          url('../images/trty.jpg') center center / cover no-repeat;
      }

      /* show brand on mobile inside form panel */
      .mobile-brand { display:flex; }

      /* reduce form title spacing */
      .form-title { margin-bottom:24px; }
      .form-title h2 { font-size:22px; }

      /* inputs slightly larger tap targets */
      .field-wrap input { padding:14px 14px 14px 44px; font-size:15px; }
      .f-icon { font-size:15px; }

      .btn-login { padding:15px; font-size:15px; }

      .social-row { margin-bottom:20px; }
      .or-divider { margin:16px 0; }
      .bottom-link { margin-top:20px; }

      /* floating images: smaller, corners only, lower opacity */
      .float-img { opacity:.55; }
      .fi-1 { width:90px;  top:2%;  left:1%; }
      .fi-2 { width:100px; top:2%;  right:1%; }
      .fi-3 { width:85px;  bottom:2%; left:1%; }
      .fi-4 { width:90px;  bottom:2%; right:1%; }

      /* modal full-width on mobile */
      .modal-dialog { margin:12px; }
      .modal-content { border-radius:16px; }
    }

    /* ── Responsive: extra small ── */
    @media (max-width:380px) {
      .panel-right { padding:28px 18px 32px; }
      .form-title h2 { font-size:20px; }
      .field-wrap input { font-size:14px; }
      .fi-1, .fi-2, .fi-3, .fi-4 { width:70px; }
    }
  </style>
</head>

<body>
  <canvas id="particles"></canvas>
  <div class="glow glow-1"></div>
  <div class="glow glow-2"></div>
  <div class="glow glow-3"></div>

  <!-- Floating travel images -->
  <img src="../images/a1.png" class="float-img fi-1" alt="">
  <img src="../images/a2.png" class="float-img fi-2" alt="">
  <img src="../images/a3.png" class="float-img fi-3" alt="">
  <img src="../images/a4.png" class="float-img fi-4" alt="">

  <div class="wrapper" id="loginCard">

    <!-- ── LEFT PANEL ── -->
    <div class="panel-left">
      <div class="brand">
        <div class="brand-logo">
          <div class="logo-icon"><i class="fas fa-globe-americas" style="color:#fff;"></i></div>
          <div class="logo-text">Multizone <span>Travels</span></div>
        </div>
        <div class="panel-headline">
          <h1>Your Journey<br>Starts Here.</h1>
          <p>Manage tours, bookings, and destinations — all from one powerful admin portal.</p>
        </div>
        <ul class="feature-list">
          <li>
            <div class="feature-icon"><i class="fas fa-chart-line" style="color:#6366f1;"></i></div>
            Real-time booking analytics
          </li>
          <li>
            <div class="feature-icon"><i class="fas fa-map-marked-alt" style="color:#06b6d4;"></i></div>
            Manage destinations &amp; tours
          </li>
          <li>
            <div class="feature-icon"><i class="fas fa-users" style="color:#f59e0b;"></i></div>
            Full customer management
          </li>
        </ul>
      </div>
      <div class="panel-footer">
        <p>&copy; <?php echo date('Y'); ?> Multizone Travels. All rights reserved.</p>
      </div>
    </div>

    <!-- ── RIGHT PANEL ── -->
    <div class="panel-right">

      <!-- Brand shown only on mobile -->
      <div class="mobile-brand">
        <div class="logo-icon"><i class="fas fa-globe-americas" style="color:#fff;"></i></div>
        <div class="logo-text">Multizone <span>Travels</span></div>
      </div>

      <div class="form-title">
        <div class="badge-admin"><i class="fas fa-shield-alt"></i> Admin Portal</div>
        <h2>Welcome back</h2>
        <p>Sign in to access your dashboard</p>
      </div>

      <!-- Social buttons -->
      <!-- <div class="social-row">
        <a href="#" class="s-btn" onclick="return false;" title="Google">
          <i class="fab fa-google" style="color:#ea4335;"></i> Google
        </a>
        <a href="#" class="s-btn" onclick="return false;" title="Telegram">
          <i class="fab fa-telegram-plane" style="color:#2AABEE;"></i> Telegram
        </a>
      </div> -->

      <!-- <div class="or-divider"><span>or sign in with credentials</span></div> -->

      <form id="loginForm" action="action.php" method="post">
        <div class="field">
          <label>Username / Email</label>
          <div class="field-wrap">
            <i class="far fa-envelope f-icon"></i>
            <input type="text" name="name" id="username" placeholder="name@example.com" required autocomplete="username">
          </div>
        </div>

        <div class="field">
          <label>Password</label>
          <div class="field-wrap">
            <i class="fas fa-lock f-icon"></i>
            <input type="password" name="pass" id="password" placeholder="Enter your password" required autocomplete="current-password">
            <i class="far fa-eye toggle-pw" id="togglePassword"></i>
          </div>
          <div class="row-fp">
            <a href="#" data-toggle="modal" data-target="#forgotPasswordModal">Forgot password?</a>
          </div>
        </div>

        <button type="submit" name="adminlogin" class="btn-login" id="btnLoginSubmit">
          <i class="fas fa-sign-in-alt mr-2"></i> Sign In
        </button>
      </form>

      <div class="bottom-link">
        Not admin? <a href="../index.php"><i class="fas fa-arrow-left" style="font-size:11px;"></i> Return to Website</a>
      </div>
    </div>
  </div>

  <!-- ── Forgot Password Modal ── -->
  <div class="modal fade" id="forgotPasswordModal" tabindex="-1" role="dialog" aria-labelledby="fpmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="fpmLabel"><i class="fas fa-key mr-2" style="color:#6366f1;"></i> Reset Password</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
        </div>
        <form id="forgotPasswordForm" action="action.php" method="post">
          <div class="modal-body p-4">
            <p class="text-muted mb-4">Enter your registered email address and we'll send you a reset link.</p>
            <div class="mb-3">
              <label class="form-label" for="resetEmail"><i class="fas fa-envelope mr-1" style="color:#6366f1;"></i> Email Address</label>
              <input type="email" name="reset_email" id="resetEmail" class="form-control" placeholder="admin@example.com" required>
            </div>
          </div>
          <div class="modal-footer d-flex justify-content-end gap-2">
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
    /* ── Particle canvas ── */
    (function(){
      const canvas = document.getElementById('particles');
      const ctx = canvas.getContext('2d');
      let W, H, pts;

      function resize(){
        W = canvas.width  = window.innerWidth;
        H = canvas.height = window.innerHeight;
        init();
      }

      function init(){
        pts = Array.from({length:90}, () => ({
          x: Math.random()*W, y: Math.random()*H,
          r: Math.random()*1.4+0.4,
          vx:(Math.random()-.5)*.35, vy:(Math.random()-.5)*.35,
          a: Math.random()*.7+.15
        }));
      }

      function draw(){
        ctx.clearRect(0,0,W,H);
        pts.forEach(p => {
          p.x += p.vx; p.y += p.vy;
          if(p.x<0||p.x>W) p.vx*=-1;
          if(p.y<0||p.y>H) p.vy*=-1;
          ctx.beginPath();
          ctx.arc(p.x, p.y, p.r, 0, Math.PI*2);
          ctx.fillStyle = `rgba(99,102,241,${p.a})`;
          ctx.fill();
        });
        /* draw connecting lines */
        for(let i=0;i<pts.length;i++){
          for(let j=i+1;j<pts.length;j++){
            const dx=pts[i].x-pts[j].x, dy=pts[i].y-pts[j].y;
            const dist=Math.sqrt(dx*dx+dy*dy);
            if(dist<120){
              ctx.beginPath();
              ctx.moveTo(pts[i].x,pts[i].y);
              ctx.lineTo(pts[j].x,pts[j].y);
              ctx.strokeStyle=`rgba(99,102,241,${.12*(1-dist/120)})`;
              ctx.lineWidth=.6;
              ctx.stroke();
            }
          }
        }
        requestAnimationFrame(draw);
      }
      window.addEventListener('resize', resize);
      resize(); draw();
    })();

    /* ── 3D card tilt (desktop only) ── */
    (function(){
      if (window.matchMedia('(hover: none)').matches) return; // skip touch devices
      const card = document.getElementById('loginCard');
      document.addEventListener('mousemove', e => {
        if (window.innerWidth <= 640) return;
        const rect = card.getBoundingClientRect();
        const cx = rect.left + rect.width/2;
        const cy = rect.top  + rect.height/2;
        const dx = (e.clientX - cx) / (window.innerWidth/2);
        const dy = (e.clientY - cy) / (window.innerHeight/2);
        card.style.transition = 'transform .1s ease';
        card.style.transform = `perspective(1200px) rotateY(${dx*4}deg) rotateX(${-dy*3}deg)`;
      });
      document.addEventListener('mouseleave', () => {
        card.style.transform = 'perspective(1200px) rotateY(0deg) rotateX(0deg)';
        card.style.transition = 'transform .6s ease';
      });
    })();

    /* ── Toggle password ── */
    document.getElementById('togglePassword').addEventListener('click', function(){
      const inp = document.getElementById('password');
      inp.type = inp.type === 'password' ? 'text' : 'password';
      this.classList.toggle('fa-eye');
      this.classList.toggle('fa-eye-slash');
    });

    /* ── Login submit loading ── */
    document.getElementById('loginForm').addEventListener('submit', function(){
      const btn = document.getElementById('btnLoginSubmit');
      btn.style.opacity = '.75';
      btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Signing in...';
    });

    document.getElementById('forgotPasswordForm').addEventListener('submit', function(){
      const btn = document.getElementById('btnForgotSubmit');
      btn.style.opacity = '.75';
      btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sending...';
    });

    /* ── SweetAlert messages ── */
    <?php if ($msg != ""): ?>
    document.addEventListener('DOMContentLoaded', function(){
      Swal.fire({
        icon: '<?php echo $msg_type; ?>',
        title: '<?php echo $msg_type === "error" ? "Access Denied" : "Notification"; ?>',
        text: '<?php echo addslashes($msg); ?>',
        background: '#0f1524',
        color: '#e2e8f0',
        confirmButtonColor: '#6366f1',
        confirmButtonText: 'OK',
        borderRadius: '16px'
      });
    });
    <?php endif; ?>
  </script>
</body>
</html>
