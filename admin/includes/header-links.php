<?php
if (!function_exists('adminThemeResolveForRequest')) {
    require_once __DIR__ . '/admin_theme.php';
}
if (!function_exists('mz_is_embed_request')) {
    require_once __DIR__ . '/mz_embed.php';
}
$mzEmbed = mz_is_embed_request();
$mzTabShell = mz_is_tab_shell();
$mzAdminThemeMode = adminThemeResolveForRequest();
$mzAdminThemeLoggedIn = !empty($_SESSION['role']) && (string) $_SESSION['role'] === '1';
if (!function_exists('admin_url') && is_file(__DIR__ . '/../bootstrap.php')) {
    require_once __DIR__ . '/../bootstrap.php';
}
$mzAdminThemeSaveUrl = function_exists('admin_url') ? admin_url('ajax/save_theme_preference.php') : 'ajax/save_theme_preference.php';
$mzAdminWebRoot = defined('ADMIN_WEB_ROOT') ? ADMIN_WEB_ROOT : '/admin';
?>
<script>
(function () {
  var storageKey = 'mz.admin.theme';
  var serverTheme = <?= json_encode($mzAdminThemeMode) ?>;
  var theme = serverTheme === 'dark' ? 'dark' : 'light';
  try {
    var stored = localStorage.getItem(storageKey);
    if (stored === 'dark' || stored === 'light') {
      theme = stored;
    } else {
      localStorage.setItem(storageKey, theme);
    }
  } catch (e) {}
  document.documentElement.setAttribute('data-theme', theme);
  document.documentElement.style.colorScheme = theme;
  window.MZ_ADMIN = window.MZ_ADMIN || {};
  window.MZ_ADMIN.themeSaveUrl = <?= json_encode($mzAdminThemeSaveUrl) ?>;
  window.MZ_ADMIN.serverTheme = serverTheme === 'dark' ? 'dark' : 'light';
  window.MZ_ADMIN.loggedIn = <?= json_encode($mzAdminThemeLoggedIn) ?>;
  window.MZ_ADMIN.adminRoot = <?= json_encode($mzAdminWebRoot, JSON_UNESCAPED_SLASHES) ?>;
  window.MZ_TABS = window.MZ_TABS || {};
  window.MZ_TABS.adminRoot = window.MZ_ADMIN.adminRoot;

  // Force embed chrome-stripping when inside the tab workspace iframe
  // (covers missing mz_embed query or cached responses).
  (function forceEmbedIfInTabFrame () {
    var embedParam = /(?:\?|&)mz_embed=1(?:&|$)/.test(String(window.location.search || ''));
    var inIframe = false;
    var parentIsShell = false;
    try { inIframe = window.self !== window.top; } catch (e) { inIframe = true; }
    if (inIframe) {
      try {
        parentIsShell = !!(window.parent && window.parent.document &&
          window.parent.document.body &&
          window.parent.document.body.classList.contains('mz-tab-shell'));
      } catch (e2) {
        parentIsShell = true; // cross-read blocked but we are framed — treat as embed
      }
    }
    if (embedParam || parentIsShell) {
      document.documentElement.classList.add('mz-embed');
      window.MZ_EMBED = true;
    }
  })();

<?php if ($mzEmbed) : ?>
  document.documentElement.classList.add('mz-embed');
  window.MZ_EMBED = true;
<?php elseif (!$mzTabShell && $mzAdminThemeLoggedIn) : ?>
  // Bounce top-level admin pages into the multi-tab workspace (preserve path/query).
  window.MZ_TABS.redirectToShell = true;
  (function () {
    try {
      if (window.top !== window.self) return;
      if (window.MZ_EMBED) return;
      var path = window.location.pathname || '';
      var rawRoot = (typeof window.MZ_ADMIN.adminRoot === 'string') ? window.MZ_ADMIN.adminRoot : '/admin';
      var root = (rawRoot === '/' || rawRoot === '') ? '' : String(rawRoot).replace(/\/$/, '');
      if (/\/(app|index|logout)\.php$/i.test(path)) return;
      if (/\/ajax\//i.test(path)) return;
      if (root && path.indexOf(root) !== 0) return;
      var rel = (root ? path.slice(root.length) : path).replace(/^\/+/, '');
      if (!rel || !/\.php$/i.test(rel.split('?')[0])) return;
      var open = rel + (window.location.search || '') + (window.location.hash || '');
      open = open.replace(/([?&])mz_embed=1(&)?/g, function (_, a, b) { return b ? a : ''; }).replace(/[?&]$/, '');
      window.location.replace((root || '') + '/app.php?open=' + encodeURIComponent(open));
    } catch (e) {}
  })();
<?php endif; ?>
})();
</script>
<style id="mz-embed-critical">
  /* Hide nested admin chrome immediately when a page is shown inside a tab iframe */
  html.mz-embed .main-header,
  html.mz-embed .main-sidebar,
  html.mz-embed .main-sidebar::before,
  html.mz-embed .main-footer {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
    overflow: hidden !important;
    visibility: hidden !important;
  }
  html.mz-embed body .content-wrapper,
  html.mz-embed body .main-footer,
  html.mz-embed body .main-header,
  html.mz-embed body:not(.sidebar-collapse) .content-wrapper,
  html.mz-embed body:not(.sidebar-collapse) .main-footer,
  html.mz-embed body:not(.sidebar-collapse) .main-header,
  html.mz-embed body.sidebar-mini .content-wrapper,
  html.mz-embed body.sidebar-mini.sidebar-collapse .content-wrapper {
    margin-left: 0 !important;
    padding-top: 0 !important;
  }
</style>
<!-- Tell the browser to be responsive to screen width -->
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Font Awesome -->
<link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
<!-- Ionicons -->
<link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
<!-- Tempusdominus Bbootstrap 4 -->
<link rel="stylesheet" href="plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
<!-- iCheck -->
<link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
<!-- JQVMap -->
<link rel="stylesheet" href="plugins/jqvmap/jqvmap.min.css">
<!-- Theme style -->
<link rel="stylesheet" href="dist/css/adminlte.min.css">
<!-- overlayScrollbars -->
<link rel="stylesheet" href="plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
<!-- Daterange picker -->
<link rel="stylesheet" href="plugins/daterangepicker/daterangepicker.css">
<!-- summernote -->
<link rel="stylesheet" href="plugins/summernote/summernote-bs4.css">
<link rel="stylesheet" href="custom/admin-modals.css">
<link rel="stylesheet" href="crm/assets/crm-list.css">
<!-- DataTables -->
<link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- Custom CSS last: system UI font stack overrides AdminLTE -->
<link rel="stylesheet" href="custom/custom-css.css">
<link rel="stylesheet" href="custom/theme.css">
<link rel="stylesheet" href="<?= htmlspecialchars(function_exists('admin_url') ? admin_url('custom/tabs.css') : 'custom/tabs.css', ENT_QUOTES, 'UTF-8') ?>">
<?php
$adminSiteSettings = isset($siteSettings) && is_array($siteSettings) ? $siteSettings : [];
$adminFaviconUrl = adminPanelBrandFromSettings($adminSiteSettings['favicon_path'] ?? '', 'img/icons1.png');
$adminFaviconFallback = adminPanelBrandUrl('img/icons1.png');
?>
<link rel="icon" href="<?= htmlspecialchars($adminFaviconUrl, ENT_QUOTES, 'UTF-8') ?>" type="image/png">
<link rel="shortcut icon" href="<?= htmlspecialchars($adminFaviconFallback, ENT_QUOTES, 'UTF-8') ?>" type="image/png">
<script>
(function () {
  var storageKey = 'remember.lte.pushmenu';
  var collapsedClass = 'sidebar-collapse';
  try {
    if (localStorage.getItem(storageKey) === null) {
      localStorage.setItem(storageKey, collapsedClass);
    }
  } catch (e) {}

  function applyCollapsedBodyClass() {
    if (document.documentElement.classList.contains('mz-embed')) return;
    if (document.body && !document.body.classList.contains(collapsedClass)) {
      document.body.classList.add(collapsedClass);
    }
  }

  function stripEmbedChrome () {
    if (!document.documentElement.classList.contains('mz-embed')) return;
    var nodes = document.querySelectorAll('.main-header, .main-sidebar, .main-footer');
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i] && nodes[i].parentNode) {
        nodes[i].parentNode.removeChild(nodes[i]);
      }
    }
    var cw = document.querySelectorAll('.content-wrapper');
    for (var j = 0; j < cw.length; j++) {
      cw[j].style.marginLeft = '0';
      cw[j].style.paddingTop = '0';
    }
    if (document.body) {
      document.body.classList.add('mz-embed-body');
      document.body.classList.remove('sidebar-mini', 'sidebar-collapse', 'layout-fixed');
    }
  }

  if (document.body) {
    applyCollapsedBodyClass();
    stripEmbedChrome();
  } else {
    new MutationObserver(function (_mutations, observer) {
      if (document.body) {
        applyCollapsedBodyClass();
        stripEmbedChrome();
        observer.disconnect();
      }
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  document.addEventListener('DOMContentLoaded', stripEmbedChrome);
})();
</script>