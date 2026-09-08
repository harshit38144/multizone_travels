<?php
/**
 * Admin multi-tab workspace shell.
 * Sidebar items open as tabs; page content loads in iframes with ?mz_embed=1.
 */
define('MZ_TAB_SHELL', true);

session_start();
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/includes/mz_embed.php';

if (!isset($_SESSION['role']) || (string) $_SESSION['role'] !== '1') {
	header('Location: index.php');
	exit;
}

$adminWebRoot = defined('ADMIN_WEB_ROOT') ? ADMIN_WEB_ROOT : '/admin';
$openPath = isset($_GET['open']) ? (string) $_GET['open'] : '';
// Only allow relative admin *.php paths (optional query string)
if ($openPath !== '' && !preg_match('~^(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_.-]+\.php(?:\?.*)?$~', $openPath)) {
	$openPath = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>Multizone Travels — Admin</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php include __DIR__ . '/includes/header-links.php'; ?>
	<link rel="stylesheet" href="custom/tabs.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed mz-tab-shell">
<div class="wrapper">
	<?php include __DIR__ . '/includes/top-header.php'; ?>
	<?php include __DIR__ . '/includes/sidebar.php'; ?>

	<div class="content-wrapper mz-tab-content-wrapper">
		<?php include __DIR__ . '/includes/tab-bar.php'; ?>
	</div>
</div>

<script>
window.MZ_TABS = window.MZ_TABS || {};
window.MZ_TABS.adminRoot = <?= json_encode($adminWebRoot, JSON_UNESCAPED_SLASHES) ?>;
window.MZ_TABS.initialOpen = <?= json_encode($openPath, JSON_UNESCAPED_SLASHES) ?>;
window.MZ_TABS.dashboardUrl = 'dashboard.php';
window.MZ_TABS.dashboardTitle = 'Dashboard';
window.MZ_TABS.redirectToShell = false;
</script>
<?php include __DIR__ . '/includes/footer-links.php'; ?>
</body>
</html>
