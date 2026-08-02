<?php
require_once __DIR__ . '/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 48;

$servicesById = [
	44 => [
		'name' => 'Arrival Card',
		'description_html' => '',
		'price' => 0.00,
		'active' => true,
		'created' => 'Apr 21, 2026',
		'created_by' => 'Uttam Mistry',
	],
	48 => [
		'name' => 'Bali eVISA',
		'description_html' => '<p><mark><strong>Docs Required :</strong></mark> Passport Copies (Front + Back) | White background Photos | Pan Cards | Flight tickets (both ways) | Hotel Voucher</p>',
		'price' => 0.00,
		'active' => true,
		'created' => 'Apr 21, 2026',
		'created_by' => 'Uttam Mistry',
	],
	41 => [
		'name' => 'Ferry Service',
		'description_html' => '',
		'price' => 1500.00,
		'active' => true,
		'created' => 'Apr 21, 2026',
		'created_by' => 'Uttam Mistry',
	],
	52 => [
		'name' => 'Travel Insurance',
		'description_html' => '',
		'price' => 899.00,
		'active' => true,
		'created' => 'Apr 21, 2026',
		'created_by' => 'Uttam Mistry',
	],
	39 => [
		'name' => 'Airport Meet & Greet',
		'description_html' => '',
		'price' => 0.00,
		'active' => false,
		'created' => 'Apr 21, 2026',
		'created_by' => 'Uttam Mistry',
	],
];

$s = $servicesById[$id] ?? $servicesById[48];
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<base href="../">
	<title>View Add-On Service</title>
	<?php include __DIR__ . '/../includes/header-links.php'; ?>
	<style>
		.crm-addon-view {
			--v-primary: #2563eb;
			--v-text: #0f172a;
			--v-muted: #64748b;
			--v-border: #e2e8f0;
			--v-success: #059669;
			--v-radius: 16px;
			--v-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.07), 0 2px 4px -2px rgba(15, 23, 42, 0.05);
		}
		.crm-addon-view .content-wrapper > .content {
			background: linear-gradient(165deg, #eef2f7 0%, #f8fafc 50%, #f1f5f9 100%);
			min-height: calc(100vh - 120px);
		}
		.crm-addon-view .page-head-row {
			display: flex; justify-content: space-between; align-items: flex-start;
			margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;
		}
		.crm-addon-view .page-kicker {
			display: block; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.14em;
			text-transform: uppercase; color: var(--v-primary); margin-bottom: 0.35rem;
		}
		.crm-addon-view .page-head-row h1 {
			margin: 0; font-size: 1.875rem; font-weight: 800; color: var(--v-text);
			letter-spacing: -0.03em;
		}
		.crm-addon-view .breadcrumbs {
			font-size: 0.8125rem; padding: 0.45rem 0.85rem; background: rgba(255,255,255,.8);
			border-radius: 999px; border: 1px solid var(--v-border);
		}
		.crm-addon-view .breadcrumbs a { color: var(--v-primary); font-weight: 600; }
		.crm-addon-view .breadcrumbs .bc-muted { color: var(--v-muted); font-weight: 500; }

		.crm-addon-view .info-card {
			background: #fff; border: 1px solid var(--v-border); border-radius: var(--v-radius);
			box-shadow: var(--v-shadow); overflow: hidden; max-width: 720px;
			margin-left: auto; margin-right: auto;
		}
		.crm-addon-view .info-card-h {
			display: flex; justify-content: space-between; align-items: center;
			padding: 1rem 1.35rem;
			background: linear-gradient(125deg, #1e40af 0%, #2563eb 50%, #3b82f6 100%);
			color: #fff; border-bottom: none;
		}
		.crm-addon-view .info-card-h span:first-child {
			font-weight: 800; font-size: 1rem; letter-spacing: -0.02em;
		}
		.crm-addon-view .btn-edit-hd {
			background: rgba(255,255,255,.2); border: 1px solid rgba(255,255,255,.45);
			color: #fff; font-weight: 700; padding: 0.4rem 0.95rem; border-radius: 10px;
			font-size: 0.875rem; transition: background 0.15s, transform 0.15s;
		}
		.crm-addon-view .btn-edit-hd:hover {
			background: rgba(255,255,255,.32); color: #fff; transform: translateY(-1px);
		}
		.crm-addon-view .info-card-bd {
			padding: 1.75rem 1.5rem 1.5rem; text-align: center;
			background: linear-gradient(180deg, #fafbfc 0%, #ffffff 40%);
		}
		.crm-addon-view .svc-title {
			font-size: 1.5rem; font-weight: 800; color: var(--v-text);
			margin: 0 0 0.85rem; letter-spacing: -0.03em; line-height: 1.25;
		}
		.crm-addon-view .badge-lg-active {
			display: inline-block; background: linear-gradient(180deg, #34d399 0%, var(--v-success) 100%);
			color: #fff; font-weight: 700; font-size: 0.78rem;
			padding: 0.4rem 1rem; border-radius: 999px;
			margin-bottom: 1.35rem; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
			letter-spacing: 0.04em;
		}
		.crm-addon-view .badge-lg-inactive {
			display: inline-block; background: linear-gradient(180deg, #94a3b8 0%, #64748b 100%);
			color: #fff; font-weight: 700; font-size: 0.78rem;
			padding: 0.4rem 1rem; border-radius: 999px; margin-bottom: 1.35rem;
		}
		.crm-addon-view .desc-label {
			text-align: left; font-weight: 800; margin-bottom: 0.5rem; color: var(--v-text);
			font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.06em;
		}
		.crm-addon-view .desc-body {
			text-align: left; font-size: 0.95rem; color: #475569; line-height: 1.6;
			margin-bottom: 1.35rem; padding: 1rem 1.1rem;
			background: #fff; border: 1px solid var(--v-border); border-radius: 12px;
			box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
		}
		.crm-addon-view .desc-body p { margin: 0; }
		.crm-addon-view .meta-table {
			width: 100%; text-align: left; font-size: 0.92rem;
			border-collapse: separate; border-spacing: 0;
			border-radius: 12px; overflow: hidden;
			border: 1px solid var(--v-border);
		}
		.crm-addon-view .meta-table tr:not(:last-child) td {
			border-bottom: 1px solid #f1f5f9;
		}
		.crm-addon-view .meta-table td {
			padding: 0.75rem 1rem; vertical-align: middle; background: #fff;
		}
		.crm-addon-view .meta-table td:first-child {
			font-weight: 700; color: var(--v-muted); width: 38%;
			background: #f8fafc; font-size: 0.78rem; text-transform: uppercase;
			letter-spacing: 0.05em;
		}
		.crm-addon-view .meta-table td:last-child {
			color: var(--v-primary); font-weight: 700; font-variant-numeric: tabular-nums;
		}

		.crm-addon-view .foot-actions {
			display: flex; justify-content: center; flex-wrap: wrap; gap: 0.85rem;
			margin-top: 1.75rem;
		}
		.crm-addon-view .btn-back {
			background: #fff; border: 1px solid var(--v-border); color: var(--v-text);
			font-weight: 700; padding: 0.55rem 1.35rem; border-radius: 10px;
			box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
			transition: transform 0.15s, box-shadow 0.15s;
		}
		.crm-addon-view .btn-back:hover {
			background: #f8fafc; color: var(--v-text); transform: translateY(-1px);
			box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
		}
		.crm-addon-view .btn-edit-main {
			background: linear-gradient(180deg, #3b82f6 0%, var(--v-primary) 100%);
			border: none; color: #fff; font-weight: 700;
			padding: 0.55rem 1.35rem; border-radius: 10px;
			box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
			transition: transform 0.15s, box-shadow 0.15s;
		}
		.crm-addon-view .btn-edit-main:hover {
			color: #fff; transform: translateY(-1px);
			box-shadow: 0 8px 22px rgba(37, 99, 235, 0.4);
		}
	</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper crm-addon-view">

	<?php include __DIR__ . '/../includes/top-header.php'; ?>
	<?php include __DIR__ . '/../includes/sidebar.php'; ?>

	<div class="content-wrapper">
		<?php include __DIR__ . '/../includes/page-header.php'; ?>

		<section class="content">
			<div class="container-fluid">

				<div class="page-head-row">
					<div>
						<span class="page-kicker">Read-only</span>
						<h1>View Add-On Service</h1>
					</div>
					<nav class="breadcrumbs">
						<a href="dashboard.php">Home</a> /
						<a href="crm/addon_service_master.php">Masters</a> /
						<span class="bc-muted">Add-On Services</span> /
						<span class="bc-muted">View</span>
					</nav>
				</div>

				<div class="info-card">
					<div class="info-card-h">
						<span>Service Information</span>
						<button type="button" class="btn btn-edit-hd btn-edit-addon-view" data-id="<?= (int) $id ?>">
							<i class="fas fa-edit mr-1"></i> Edit
						</button>
					</div>
					<div class="info-card-bd">
						<div class="svc-title"><?= htmlspecialchars($s['name']) ?></div>
						<?php if (!empty($s['active'])) { ?>
							<span class="badge-lg-active">Active</span>
						<?php } else { ?>
							<span class="badge-lg-inactive">Inactive</span>
						<?php } ?>

						<div class="desc-label">Description</div>
						<div class="desc-body">
							<?php if ($s['description_html'] !== '') { ?>
								<?= $s['description_html'] ?>
							<?php } else { ?>
								<span class="text-muted">—</span>
							<?php } ?>
						</div>

						<table class="meta-table">
							<tr>
								<td>Price</td>
								<td>₹<?= number_format((float) $s['price'], 2, '.', ',') ?></td>
							</tr>
							<tr>
								<td>Status</td>
								<td><?= !empty($s['active']) ? 'Active' : 'Inactive' ?></td>
							</tr>
							<tr>
								<td>Created</td>
								<td><?= htmlspecialchars($s['created']) ?></td>
							</tr>
							<tr>
								<td>Created By</td>
								<td><?= htmlspecialchars($s['created_by']) ?></td>
							</tr>
						</table>
					</div>
				</div>

				<div class="foot-actions">
					<a href="crm/addon_service_master.php" class="btn btn-back"><i class="fas fa-arrow-left mr-1"></i> Back to List</a>
					<button type="button" class="btn btn-edit-main btn-edit-addon-view" data-id="<?= (int) $id ?>">
						<i class="fas fa-edit mr-1"></i> Edit Service
					</button>
				</div>

			</div>
		</section>
	</div>

	<?php include __DIR__ . '/../includes/footer-links.php'; ?>
</div>
<script>
$(function () {
	$('.btn-edit-addon-view').on('click', function () {
		var id = $(this).data('id');
		sessionStorage.setItem('addonEditOpen', id);
		window.location.href = 'addon_service_master.php';
	});
});
</script>
</body>
</html>
