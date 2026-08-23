<?php
$mzHeaderUserName = trim((string) ($_SESSION['name'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin'));
if ($mzHeaderUserName === '') {
  $mzHeaderUserName = 'Admin';
}
$mzHeaderInitials = '';
$mzNameParts = preg_split('/\s+/', $mzHeaderUserName) ?: [];
foreach ($mzNameParts as $mzPart) {
  $mzPart = trim((string) $mzPart);
  if ($mzPart === '') {
    continue;
  }
  $mzHeaderInitials .= strtoupper(substr($mzPart, 0, 1));
  if (strlen($mzHeaderInitials) >= 2) {
    break;
  }
}
if ($mzHeaderInitials === '') {
  $mzHeaderInitials = 'A';
}
$mzHeaderRoleLabel = ((string) ($_SESSION['role'] ?? '') === '1') ? 'Administrator' : 'Team Member';
?>
<!-- Navbar -->
<style>
  .main-header.mz-topbar {
    --mz-top-red: #c41e20;
    --mz-top-red-deep: #9f1517;
    --mz-top-ink: #ffffff;
    background: linear-gradient(105deg, var(--mz-top-red) 0%, #d42729 48%, var(--mz-top-red-deep) 100%) !important;
    border-bottom: 0 !important;
    box-shadow: 0 2px 14px rgba(15, 23, 42, 0.18);
    min-height: 3.4rem;
    padding: 0 0.65rem 0 0.35rem;
    align-items: center;
  }

  .main-header.mz-topbar .navbar-nav {
    align-items: center;
  }

  .main-header.mz-topbar .nav-link {
    color: rgba(255, 255, 255, 0.92) !important;
    transition: background-color 0.15s ease, color 0.15s ease, transform 0.15s ease;
  }

  .main-header.mz-topbar .mz-topbar-toggle {
    width: 2.35rem;
    height: 2.35rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.55rem;
    margin: 0 0.15rem;
    font-size: 1rem;
  }

  .main-header.mz-topbar .mz-topbar-toggle:hover {
    background: rgba(255, 255, 255, 0.16);
    color: #fff !important;
  }

  .main-header.mz-topbar .mz-topbar-brand {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.35rem 0.7rem 0.35rem 0.45rem;
    border-radius: 0.65rem;
    text-decoration: none !important;
    color: #fff !important;
    line-height: 1.15;
  }

  .main-header.mz-topbar .mz-topbar-brand:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #fff !important;
  }

  .main-header.mz-topbar .mz-topbar-brand-mark {
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 0.45rem;
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.28);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    flex-shrink: 0;
  }

  .main-header.mz-topbar .mz-topbar-brand-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
  }

  .main-header.mz-topbar .mz-topbar-brand-title {
    font-size: 0.92rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    white-space: nowrap;
  }

  .main-header.mz-topbar .mz-topbar-brand-sub {
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    opacity: 0.78;
    white-space: nowrap;
  }

  .main-header.mz-topbar .mz-topbar-divider {
    width: 1px;
    height: 1.45rem;
    background: rgba(255, 255, 255, 0.28);
    margin: 0 0.45rem;
    display: none;
  }

  @media (min-width: 576px) {
    .main-header.mz-topbar .mz-topbar-divider {
      display: block;
    }
  }

  .main-header.mz-topbar .mz-topbar-link {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    height: 2.2rem;
    padding: 0 0.75rem !important;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.95) !important;
  }

  .main-header.mz-topbar .mz-topbar-link i {
    font-size: 0.78rem;
    opacity: 0.9;
  }

  .main-header.mz-topbar .mz-topbar-link:hover {
    background: rgba(255, 255, 255, 0.16);
    color: #fff !important;
  }

  .main-header.mz-topbar .profile-dropdown > .nav-link {
    cursor: pointer;
  }

  .main-header.mz-topbar .mz-user-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    height: 2.35rem;
    padding: 0.2rem 0.55rem 0.2rem 0.25rem !important;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.18);
    margin-right: 0.25rem;
  }

  .main-header.mz-topbar .mz-user-trigger:hover,
  .main-header.mz-topbar .profile-dropdown.show > .mz-user-trigger {
    background: rgba(255, 255, 255, 0.2);
    color: #fff !important;
  }

  .main-header.mz-topbar .mz-user-avatar {
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 50%;
    background: #fff;
    color: #b91c1c;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
  }

  .main-header.mz-topbar .mz-user-meta {
    display: none;
    flex-direction: column;
    min-width: 0;
    line-height: 1.15;
    text-align: left;
  }

  @media (min-width: 768px) {
    .main-header.mz-topbar .mz-user-meta {
      display: flex;
    }
  }

  .main-header.mz-topbar .mz-user-name {
    font-size: 0.78rem;
    font-weight: 700;
    max-width: 9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .main-header.mz-topbar .mz-user-role {
    font-size: 0.62rem;
    font-weight: 600;
    opacity: 0.78;
    letter-spacing: 0.03em;
    text-transform: uppercase;
  }

  .main-header.mz-topbar .mz-user-caret {
    font-size: 0.65rem;
    opacity: 0.8;
    margin-left: 0.1rem;
  }

  .main-header.mz-topbar .profile-dropdown:hover > .dropdown-menu,
  .main-header.mz-topbar .profile-dropdown.show > .dropdown-menu {
    display: block;
  }

  .main-header.mz-topbar .profile-dropdown .dropdown-menu {
    margin-top: 0.45rem;
    min-width: 13.5rem;
    padding: 0.4rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14);
    overflow: hidden;
  }

  .main-header.mz-topbar .mz-dropdown-head {
    padding: 0.65rem 0.75rem 0.7rem;
    border-bottom: 1px solid #eef2f7;
    margin-bottom: 0.3rem;
  }

  .main-header.mz-topbar .mz-dropdown-head-name {
    display: block;
    font-size: 0.86rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.25;
  }

  .main-header.mz-topbar .mz-dropdown-head-role {
    display: block;
    font-size: 0.7rem;
    color: #64748b;
    margin-top: 0.1rem;
  }

  .main-header.mz-topbar .profile-dropdown .dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.55rem 0.7rem;
    border-radius: 0.5rem;
    font-size: 0.84rem;
    font-weight: 600;
    color: #334155;
  }

  .main-header.mz-topbar .profile-dropdown .dropdown-item i {
    width: 1.1rem;
    text-align: center;
    color: #64748b;
  }

  .main-header.mz-topbar .profile-dropdown .dropdown-item:hover,
  .main-header.mz-topbar .profile-dropdown .dropdown-item:focus {
    background: #f1f5f9;
    color: #0f172a;
  }

  .main-header.mz-topbar .profile-dropdown .dropdown-item:hover i,
  .main-header.mz-topbar .profile-dropdown .dropdown-item:focus i {
    color: #c41e20;
  }

  .main-header.mz-topbar .mz-logout-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    height: 2.2rem;
    padding: 0 0.8rem !important;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
    background: rgba(15, 23, 42, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.16);
    color: #fff !important;
  }

  .main-header.mz-topbar .mz-logout-btn:hover {
    background: rgba(15, 23, 42, 0.3);
    color: #fff !important;
  }

  .main-header.mz-topbar .mz-logout-btn i {
    font-size: 0.85rem;
  }
</style>

<nav class="main-header navbar navbar-expand navbar-dark mz-topbar">
  <!-- Left -->
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link mz-topbar-toggle" data-widget="pushmenu" data-enable-remember="true" href="#" role="button" title="Toggle sidebar" aria-label="Toggle sidebar">
        <i class="fas fa-bars"></i>
      </a>
    </li>
    <li class="nav-item d-none d-sm-flex align-items-center">
      <span class="mz-topbar-divider" aria-hidden="true"></span>
      <a href="dashboard.php" class="mz-topbar-brand" title="Go to dashboard">
        <span class="mz-topbar-brand-mark">MZ</span>
        <span class="mz-topbar-brand-text">
          <span class="mz-topbar-brand-title">Multizone Travels</span>
          <span class="mz-topbar-brand-sub">Admin Panel</span>
        </span>
      </a>
    </li>
    <li class="nav-item d-none d-md-inline-block">
      <a href="dashboard.php" class="nav-link mz-topbar-link">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
    </li>
  </ul>

  <!-- Right -->
  <ul class="navbar-nav ml-auto">
    <li class="nav-item d-flex align-items-center">
      <button type="button" class="nav-link mz-theme-toggle" id="mzThemeToggle" title="Switch to dark mode" aria-label="Switch to dark mode" aria-pressed="false">
        <i class="fas fa-moon"></i>
      </button>
    </li>
    <li class="nav-item dropdown profile-dropdown">
      <a href="#" class="nav-link mz-user-trigger" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" title="Account">
        <span class="mz-user-avatar"><?= htmlspecialchars($mzHeaderInitials, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="mz-user-meta">
          <span class="mz-user-name"><?= htmlspecialchars($mzHeaderUserName, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="mz-user-role"><?= htmlspecialchars($mzHeaderRoleLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </span>
        <i class="fas fa-chevron-down mz-user-caret d-none d-md-inline"></i>
      </a>
      <div class="dropdown-menu dropdown-menu-right">
        <div class="mz-dropdown-head">
          <span class="mz-dropdown-head-name"><?= htmlspecialchars($mzHeaderUserName, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="mz-dropdown-head-role"><?= htmlspecialchars($mzHeaderRoleLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <a href="user_profile.php" class="dropdown-item">
          <i class="fas fa-user"></i> Profile
        </a>
        <a href="site_settings.php" class="dropdown-item">
          <i class="fas fa-cog"></i> Settings
        </a>
      </div>
    </li>
    <li class="nav-item">
      <a href="logout.php" class="nav-link mz-logout-btn" title="Logout">
        <i class="fas fa-sign-out-alt"></i>
        <span class="d-none d-md-inline">Logout</span>
      </a>
    </li>
  </ul>
</nav>
<!-- /.navbar -->
