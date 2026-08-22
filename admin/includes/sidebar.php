<?php
// Detect current page (CRM files live in admin/crm/ — use a stable prefix so names like index.php do not clash)
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
if (strpos($scriptPath, '/admin/crm/') !== false) {
  $page = 'crm_' . basename($_SERVER['SCRIPT_NAME'], '.php');
} elseif (strpos($scriptPath, '/admin/mail/') !== false) {
  $page = 'mail_' . basename($_SERVER['SCRIPT_NAME'], '.php');
} else {
  $page = basename($_SERVER['SCRIPT_NAME'], '.php');
}

// Menu Groups
$eticketsPages = ['etickets', 'eticketslist', 'e-ticket-master'];
$crmPages = ['crm_leads', 'crm_lead_add', 'crm_lead_intake_pending'];
$mastersPages = ['crm_city_master', 'crm_city_create', 'crm_lead_source_master', 'crm_hotel_master', 'crm_hotel_create', 'crm_airline_master', 'crm_airline_create', 'crm_cruise_master', 'crm_cruise_create', 'crm_cruise_view', 'crm_vehicle_master', 'crm_vehicle_create', 'crm_vehicle_view', 'crm_addon_service_master', 'crm_addon_service_view', 'crm_sightseeing_master', 'crm_sightseeing_create', 'crm_sightseeing_destination', 'crm_sightseeing_view', 'crm_testimonial_master', 'crm_testimonial_create', 'crm_quotation_terms_master', 'geo_import'];
$quotationsPages = ['crm_quotation_templates', 'crm_quotation-generator-list', 'crm_quotation_generator'];
$bookingsPages = ['crm_bookings', 'crm_booking_add'];
$crmCustomersPages = ['crm_customers', 'crm_customer_add'];
$suppliersPages = ['crm_suppliers'];
$crmUsersPages = ['crm_users', 'crm_permission_templates'];
$crmReportsPages = ['crm_reports'];
$crmOfficePages = ['crm_office_settings', 'crm_email_master'];
$crmAccountPages = ['crm_subscription'];
$contentPages = ['sliders', 'homepage_sections', 'pages', 'about_us', 'instagram_reels', 'testimonials', 'budget_cards', 'secondary_features', 'secondary_feature_form', 'live_counters', 'features'];
$packagePages = ['categories', 'destinations', 'packages', 'group_departures'];
$customerPages = ['queries', 'testimonials', 'live_counters'];
$settingsPages = ['site_settings', 'menu_manager'];
$paymentPages = ['payment_links', 'payment_link_create'];
$mailPages = ['mail_inbox'];

$websiteParentPages = array_merge($contentPages, $packagePages, $settingsPages, ['privilege']);
$crmParentPages = array_merge(
  $crmPages,
  $mastersPages,
  $quotationsPages,
  $bookingsPages,
  $crmCustomersPages,
  $suppliersPages,
  $crmUsersPages,
  $crmReportsPages,
  $crmOfficePages,
  $crmAccountPages,
  ['user_profile']
);
?>

<style>
  .main-sidebar {
    background-color: #f4f6f9 !important;
  }

  [class*=sidebar-dark-] .nav-sidebar>.nav-item>.nav-link {
    color: #2c2c2c !important;
    background-color: transparent;
    border-radius: 6px;
    margin: 2px 8px;
    transition: all 0.3s ease;
  }

  [class*=sidebar-dark-] .nav-sidebar>.nav-item>.nav-link:hover {
    background-color: #dbe7ff !important;
    color: #0d2cff !important;
  }

  [class*=sidebar-dark-] .nav-sidebar>.nav-item>.nav-link.active {
    background-color: #d01f20 !important;
    color: #ffffff !important;
    font-weight: 600;
  }

  .nav-treeview>.nav-item>.nav-link {
    color: #444 !important;
    margin-left: 15px;
    border-radius: 6px;
  }

  .nav-treeview>.nav-item>.nav-link:hover {
    background-color: #e0e7ff !important;
    color: #1e3a8a !important;
  }

  .nav-treeview>.nav-item>.nav-link.active {
    background-color: #c7d2fe !important;
    color: #1e3a8a !important;
    font-weight: 600;
  }

  .nav-item.menu-open>.nav-link {
    background-color: #e5e7ff !important;
    color: #1e40af !important;
  }

  .nav-icon {
    color: inherit !important;
  }

  .user-panel.mz-sidebar-brand {
    display: block !important;
    padding: 0.65rem 0.35rem 0.85rem;
    margin: 0.5rem 0 0.5rem;
    border-bottom: 1px solid #dee2e6;
    overflow: visible;
  }

  .mz-sidebar-brand .mz-brand-link {
    display: block;
    text-align: center;
    line-height: 0;
  }

  .mz-sidebar-brand .sidebar-logo-xl {
    width: 10.1rem;
    max-width: calc(100% - 0.5rem);
    height: auto;
    display: inline-block;
  }

  .mz-sidebar-brand .sidebar-logo-xs {
    display: none;
    width: 2.5rem;
    height: 2.5rem;
    object-fit: contain;
    margin: 0 auto;
  }

  @media (min-width: 992px) {
    body.sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .mz-sidebar-brand .sidebar-logo-xl {
      display: none !important;
      visibility: hidden !important;
    }

    body.sidebar-mini.sidebar-collapse .main-sidebar:not(:hover):not(.sidebar-focused) .mz-sidebar-brand .sidebar-logo-xs {
      display: inline-block !important;
      visibility: visible !important;
      opacity: 1 !important;
      animation: none !important;
    }

    body.sidebar-mini.sidebar-collapse .main-sidebar:hover .mz-sidebar-brand .sidebar-logo-xs,
    body.sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .mz-sidebar-brand .sidebar-logo-xs {
      display: none !important;
    }

    body.sidebar-mini.sidebar-collapse .main-sidebar:hover .mz-sidebar-brand .sidebar-logo-xl,
    body.sidebar-mini.sidebar-collapse .main-sidebar.sidebar-focused .mz-sidebar-brand .sidebar-logo-xl {
      display: inline-block !important;
      visibility: visible !important;
      opacity: 1 !important;
      animation: none !important;
    }
  }

  .sidebar .nav-header.crm-account-label {
    padding: 0.75rem 1rem 0.35rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: #868e96 !important;
    text-transform: uppercase;
  }
</style>


<!-- SIDEBAR -->
<aside class="main-sidebar sidebar-dark-primary elevation-4" style="background:#fafbfc;">

  <div class="sidebar">

    <div class="user-panel mz-sidebar-brand mt-2 pb-2 mb-2">
      <a href="dashboard.php" class="mz-brand-link">
        <?php
        $adminSiteSettings = isset($siteSettings) && is_array($siteSettings) ? $siteSettings : [];
        $sidebarLogoXl = adminPanelBrandFromSettings($adminSiteSettings['logo_path'] ?? '', 'img/web-logo.png');
        $sidebarLogoXs = adminPanelBrandFromSettings($adminSiteSettings['favicon_path'] ?? '', 'img/icons1.png');
        $sidebarLogoFallbackXl = adminPanelBrandUrl('img/web-logo.png');
        $sidebarLogoFallbackXs = adminPanelBrandUrl('img/icons1.png');
        ?>
        <img src="<?= htmlspecialchars($sidebarLogoXl, ENT_QUOTES, 'UTF-8') ?>" alt="Multi Zone Travels" class="sidebar-logo-xl"
            onerror="if(this.dataset.fb!=='1'){this.dataset.fb='1';this.src='<?= htmlspecialchars($sidebarLogoFallbackXl, ENT_QUOTES, 'UTF-8') ?>';}">
        <img src="<?= htmlspecialchars($sidebarLogoXs, ENT_QUOTES, 'UTF-8') ?>" alt="Multi Zone Travels" class="sidebar-logo-xs"
            onerror="if(this.dataset.fb!=='1'){this.dataset.fb='1';this.src='<?= htmlspecialchars($sidebarLogoFallbackXs, ENT_QUOTES, 'UTF-8') ?>';}">
      </a>
    </div>

    <nav class="mt-2">

      <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu"
        data-accordion="false">

        <!-- Dashboard -->
        <li class="nav-item">
          <a href="dashboard.php" class="nav-link <?= ($page == 'dashboard') ? 'active' : '' ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard</p>
          </a>
        </li>

        <!-- Payments -->
        <!-- <li class="nav-item">
          <a href="payment_links.php" class="nav-link <?= in_array($page, $paymentPages, true) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-link"></i>
            <p>Payment Links</p>
          </a>
        </li> -->

        <!-- E-Tickets -->
        <li class="nav-item has-treeview <?= in_array($page, $eticketsPages) ? 'menu-open' : '' ?>">

          <a href="#" class="nav-link <?= in_array($page, $eticketsPages) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-plane"></i>
            <p>
              E-Tickets
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>

          <ul class="nav nav-treeview">

            <li class="nav-item">
              <a href="etickets.php" class="nav-link <?= ($page == 'etickets') ? 'active' : '' ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>New E-Tickets</p>
              </a>
            </li>

            <li class="nav-item">
              <a href="eticketslist.php" class="nav-link <?= ($page == 'eticketslist') ? 'active' : '' ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>All E-Tickets</p>
              </a>
            </li>

            <li class="nav-item">
              <a href="e-ticket-master.php" class="nav-link <?= ($page == 'e-ticket-master') ? 'active' : '' ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>E-Tickets Master</p>
              </a>
            </li>
          </ul>
        </li>



        <!-- Website -->
        <li class="nav-item has-treeview <?= in_array($page, $websiteParentPages, true) ? 'menu-open' : '' ?>">
          <a href="#" class="nav-link <?= in_array($page, $websiteParentPages, true) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-globe"></i>
            <p>
              Website
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <!-- Page Management -->
            <li class="nav-item has-treeview <?= in_array($page, $contentPages) ? 'menu-open' : '' ?>">

              <a href="#" class="nav-link <?= in_array($page, $contentPages) ? 'active' : '' ?>">
                <i class="nav-icon fas fa-file-alt"></i>
                <p>
                  Page Management
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>

              <ul class="nav nav-treeview">

                <li class="nav-item">
                  <a href="sliders.php" class="nav-link <?= ($page == 'sliders') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Main Sliders</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a href="homepage_sections.php"
                    class="nav-link <?= ($page == 'homepage_sections') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Homepage Sections</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a href="pages.php" class="nav-link <?= ($page == 'pages') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Pages</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a href="about_us.php" class="nav-link <?= ($page == 'about_us') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>About Us</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a href="budget_cards.php" class="nav-link <?= ($page == 'budget_cards') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Budget Cards</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="instagram_reels.php" class="nav-link <?= ($page == 'instagram_reels') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Instagram Reels</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="testimonials.php" class="nav-link <?= ($page == 'testimonials') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Testimonials</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="live_counters.php" class="nav-link <?= ($page == 'live_counters') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Live Counters</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="features.php" class="nav-link <?= ($page == 'features') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Features</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a href="secondary_features.php"
                    class="nav-link <?= ($page == 'secondary_features' || $page == 'secondary_feature_form') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Secondary Features</p>
                  </a>
                </li>
              </ul>
            </li>

            <!-- Package Management -->
            <li class="nav-item has-treeview <?= in_array($page, $packagePages) ? 'menu-open' : '' ?>">

              <a href="#" class="nav-link <?= in_array($page, $packagePages) ? 'active' : '' ?>">
                <i class="nav-icon fas fa-suitcase"></i>
                <p>
                  Package Management
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>

              <ul class="nav nav-treeview">

                <li class="nav-item">
                  <a href="categories.php" class="nav-link <?= ($page == 'categories') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Categories</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a href="destinations.php" class="nav-link <?= ($page == 'destinations') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Destinations</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a href="packages.php" class="nav-link <?= ($page == 'packages') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Packages</p>
                  </a>
                </li>



                <li class="nav-item">
                  <a href="group_departures.php" class="nav-link <?= ($page == 'group_departures') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Group Departures</p>
                  </a>
                </li>
              </ul>
            </li>

            <!-- Website Settings -->
            <li class="nav-item has-treeview <?= in_array($page, $settingsPages) ? 'menu-open' : '' ?>">
              <a href="#" class="nav-link <?= in_array($page, $settingsPages) ? 'active' : '' ?>">
                <i class="nav-icon fas fa-globe-americas"></i>
                <p>
                  Website Settings
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <a href="site_settings.php" class="nav-link <?= ($page == 'site_settings') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Website Profile</p>
                  </a>
                </li>
              </ul>
            </li>

            <li class="nav-item">
              <a href="privilege.php" class="nav-link <?= ($page == 'privilege') ? 'active' : '' ?>">
                <i class="nav-icon fas fa-star"></i>
                <p>Privilege Traveller</p>
              </a>
            </li>

            <li class="nav-item">
              <a href="../index.php" target="_blank" class="nav-link">
                <i class="nav-icon fas fa-globe"></i>
                <p style="font-weight:bold;">View Website</p>
              </a>
            </li>

          </ul>
        </li>

        <!-- CRM -->
        <li class="nav-item has-treeview <?= in_array($page, $crmParentPages, true) ? 'menu-open' : '' ?>">
          <a href="#" class="nav-link <?= in_array($page, $crmParentPages, true) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-briefcase"></i>
            <p>
              CRM
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">

            <!-- Leads (admin/crm/) -->
            <!-- <li class="nav-item has-treeview <?= in_array($page, $crmPages) ? 'menu-open' : '' ?>">
              <a href="#" class="nav-link <?= in_array($page, $crmPages) ? 'active' : '' ?>">
                <i class="nav-icon fas fa-user-friends"></i>
                <p>
                  Leads
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <a href="crm/leads.php" class="nav-link <?= ($page == 'crm_leads') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>All Leads</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="crm/lead_add.php" class="nav-link <?= ($page == 'crm_lead_add') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Add New Lead</p>
                  </a>
                </li>
              </ul>
            </li> -->
            <li class="nav-item">
              <a href="crm/leads.php" class="nav-link <?= in_array($page, ['crm_leads', 'crm_lead_add'], true) ? 'active' : '' ?>">
                <i class="nav-icon fas fa-chart-bar"></i>
                <p>Leads</p>
              </a>
            </li>
            <?php
            $pendingIntakeBadge = 0;
            if (isset($conn) && $conn instanceof mysqli) {
              $intakeTbl = $conn->query("SHOW TABLES LIKE 'crm_lead_intake_submissions'");
              if ($intakeTbl && $intakeTbl->num_rows > 0) {
                $pc = $conn->query("SELECT COUNT(*) AS c FROM `crm_lead_intake_submissions` WHERE `status` = 'pending'");
                if ($pc) {
                  $pendingIntakeBadge = (int) ($pc->fetch_assoc()['c'] ?? 0);
                }
              }
            }
            ?>
            

            <!-- Masters (CRM) -->
            <li class="nav-item has-treeview <?= in_array($page, $mastersPages) ? 'menu-open' : '' ?>">
              <a href="#" class="nav-link <?= in_array($page, $mastersPages) ? 'active' : '' ?>">
                <i class="nav-icon fas fa-database"></i>
                <p>
                  Masters
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <a href="crm/city_master.php"
                    class="nav-link <?= in_array($page, ['crm_city_master', 'crm_city_create'], true) ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Cities</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="crm/lead_source_master.php"
                    class="nav-link <?= ($page === 'crm_lead_source_master') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Lead Source</p>
                  </a>
                </li>
                <!-- <li class="nav-item">
                  <a href="geo_import.php"
                    class="nav-link <?= ($page === 'geo_import') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Geo Data Import</p>
                  </a>
                </li> -->
                <li class="nav-item">
                  <a href="crm/hotel_master.php"
                    class="nav-link <?= in_array($page, ['crm_hotel_master', 'crm_hotel_create'], true) ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Hotels</p>
                  </a>
                </li>
                <!-- <li class="nav-item">
              <a href="crm/airline_master.php" class="nav-link <?= in_array($page, ['crm_airline_master', 'crm_airline_create'], true) ? 'active' : '' ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Airlines</p>
              </a>
            </li> -->
                <li class="nav-item">
                  <a href="crm/cruise_master.php"
                    class="nav-link <?= in_array($page, ['crm_cruise_master', 'crm_cruise_create', 'crm_cruise_view'], true) ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Cruises</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="crm/vehicle_master.php"
                    class="nav-link <?= in_array($page, ['crm_vehicle_master', 'crm_vehicle_create', 'crm_vehicle_view'], true) ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Vehicles</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="crm/sightseeing_master.php"
                    class="nav-link <?= in_array($page, ['crm_sightseeing_master', 'crm_sightseeing_create', 'crm_sightseeing_destination', 'crm_sightseeing_view'], true) ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Sightseeing</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="crm/addon_service_master.php"
                    class="nav-link <?= in_array($page, ['crm_addon_service_master', 'crm_addon_service_view'], true) ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Add-on Services</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="crm/testimonial_master.php"
                    class="nav-link <?= in_array($page, ['crm_testimonial_master', 'crm_testimonial_create'], true) ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Testimonials</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="crm/quotation_terms_master.php"
                    class="nav-link <?= ($page === 'crm_quotation_terms_master') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Quotation Terms &amp; Policies</p>
                  </a>
                </li>
              </ul>
            </li>

            <!-- Quotations -->
            <li class="nav-item has-treeview <?= in_array($page, $quotationsPages, true) ? 'menu-open' : '' ?>">
              <a href="#" class="nav-link <?= in_array($page, $quotationsPages, true) ? 'active' : '' ?>">
                <i class="nav-icon fas fa-file-invoice-dollar"></i>
                <p>
                  Quotations
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <a href="crm/quotation-generator-list.php"
                    class="nav-link <?= in_array($page, ['crm_quotation-generator-list', 'crm_quotation_generator'], true) ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Quotations</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="crm/quotation_templates.php"
                    class="nav-link <?= ($page == 'crm_quotation_templates') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Templates</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="crm/quotation_terms_master.php"
                    class="nav-link <?= ($page === 'crm_quotation_terms_master') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Terms &amp; Policies</p>
                  </a>
                </li>
              </ul>
            </li>

            <!-- Bookings -->
            <li class="nav-item has-treeview <?= in_array($page, $bookingsPages, true) ? 'menu-open' : '' ?>">
              <a href="#" class="nav-link <?= in_array($page, $bookingsPages, true) ? 'active' : '' ?>">
                <i class="nav-icon fas fa-plane-departure"></i>
                <p>
                  Bookings
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <a href="crm/bookings.php" class="nav-link <?= ($page == 'crm_bookings') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>All Bookings</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="crm/booking_add.php" class="nav-link <?= ($page == 'crm_booking_add') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Add New Booking</p>
                  </a>
                </li>
              </ul>
            </li>

            <!-- Customers -->
            <li class="nav-item has-treeview <?= in_array($page, $crmCustomersPages, true) ? 'menu-open' : '' ?>">
              <a href="#" class="nav-link <?= in_array($page, $crmCustomersPages, true) ? 'active' : '' ?>">
                <i class="nav-icon fas fa-address-book"></i>
                <p>
                  Customers
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <a href="crm/customers.php" class="nav-link <?= ($page == 'crm_customers') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>All Customers</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="crm/customer_add.php" class="nav-link <?= ($page == 'crm_customer_add') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Add New Customer</p>
                  </a>
                </li>
              </ul>
            </li>

            <!-- Suppliers -->
            <li class="nav-item">
              <a href="crm/suppliers.php" class="nav-link <?= in_array($page, $suppliersPages, true) ? 'active' : '' ?>">
                <i class="nav-icon fas fa-handshake"></i>
                <p>Suppliers</p>
              </a>
            </li>

            <!-- Users -->
            <li class="nav-item">
              <a href="crm/users.php" class="nav-link <?= in_array($page, $crmUsersPages, true) ? 'active' : '' ?>">
              <i class="nav-icon fas fa-users-cog"></i>
                <p>Users</p>
              </a>
            </li>
            

            <!-- Reports -->
            <li class="nav-item">
              <a href="crm/reports.php" class="nav-link <?= in_array($page, $crmReportsPages, true) ? 'active' : '' ?>">
                <i class="nav-icon fas fa-chart-bar"></i>
                <p>Reports</p>
              </a>
            </li>

            <!-- Settings (CRM) -->
            <li class="nav-item has-treeview <?= in_array($page, $crmOfficePages, true) ? 'menu-open' : '' ?>">
              <a href="#" class="nav-link <?= in_array($page, $crmOfficePages, true) ? 'active' : '' ?>">
                <i class="nav-icon fas fa-cog"></i>
                <p>
                  Settings
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <a href="crm/office_settings.php"
                    class="nav-link <?= ($page === 'crm_office_settings') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Email Configuration</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="crm/email_master.php"
                    class="nav-link <?= ($page === 'crm_email_master') ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Email Master</p>
                  </a>
                </li>
              </ul>
            </li>

          </ul>
        </li>

        

        <!-- Mail -->
        <li class="nav-item">
          <a href="mail/inbox.php" class="nav-link <?= ($page === 'mail_inbox') ? 'active' : '' ?>">
            <i class="nav-icon fas fa-envelope"></i>
            <p>Mail</p>
          </a>
        </li>

        <!-- Payment Links -->
        <li class="nav-item has-treeview <?= in_array($page, $paymentPages) ? 'menu-open' : '' ?>">
          <a href="#" class="nav-link <?= in_array($page, $paymentPages) ? 'active' : '' ?>">
            <i class="nav-icon fas fa-link"></i>
            <p>
              Payment Links
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="payment_link_create.php"
                class="nav-link <?= ($page === 'payment_link_create') ? 'active' : '' ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>Create Payment Link</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="payment_links.php" class="nav-link <?= ($page == 'payment_links') ? 'active' : '' ?>">
                <i class="far fa-circle nav-icon"></i>
                <p>All Payment Links</p>
              </a>
            </li>
          </ul>
        </li>

        <li class="nav-item">
          <a href="lead_contacts.php" class="nav-link <?= ($page == 'lead_contacts') ? 'active' : '' ?>">
            <i class="nav-icon fas fa-user"></i>
            <p>Contacts</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="user_profile.php" class="nav-link <?= ($page == 'user_profile') ? 'active' : '' ?>">
            <i class="nav-icon fas fa-user"></i>
            <p>Profile</p>
          </a>
        </li>

        <!-- Logout -->
        <!-- <li class="nav-item mt-3">
          <a href="logout.php" class="nav-link">
            <i class="nav-icon fas fa-sign-out-alt"></i>
            <p style="font-weight:bold;">Logout</p>
          </a>
        </li> -->

      </ul>
    </nav>

  </div>

</aside></aside>
