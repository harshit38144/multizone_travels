<?php
require_once __DIR__ . '/bootstrap.php';

$services = [
	[
		'id' => 44,
		'name' => 'Arrival Card',
		'description_html' => '',
		'price' => 0.00,
		'active' => true,
	],
	[
		'id' => 48,
		'name' => 'Bali eVISA',
		'description_html' => '<p><mark><strong>Docs Required :</strong></mark> Passport Copies (Front + Back) | White background Photos | Pan Cards | Flight tickets (both ways) | Hotel Voucher</p>',
		'price' => 0.00,
		'active' => true,
	],
	[
		'id' => 41,
		'name' => 'Ferry Service',
		'description_html' => '',
		'price' => 1500.00,
		'active' => true,
	],
	[
		'id' => 52,
		'name' => 'Travel Insurance',
		'description_html' => '',
		'price' => 899.00,
		'active' => true,
	],
	[
		'id' => 39,
		'name' => 'Airport Meet & Greet',
		'description_html' => '',
		'price' => 0.00,
		'active' => false,
	],
];

$servicesById = [];
foreach ($services as $s) {
	$servicesById[$s['id']] = $s;
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<base href="../">
	<title>Add-On Services Master</title>
	<?php include __DIR__ . '/../includes/header-links.php'; ?>
	<style>
		.crm-addon-master {
			--addon-primary: #2563eb;
			--addon-primary-hover: #1d4ed8;
			--addon-surface: #f0f4f8;
			--addon-card: #ffffff;
			--addon-text: #0f172a;
			--addon-muted: #64748b;
			--addon-border: #e2e8f0;
			--addon-success: #059669;
			--addon-radius: 12px;
			--addon-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.07), 0 2px 4px -2px rgba(15, 23, 42, 0.05);
			--addon-shadow-lg: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
		}
		.crm-addon-master .content-wrapper > .content {
			background: linear-gradient(165deg, #eef2f7 0%, #f8fafc 45%, #f1f5f9 100%);
			min-height: calc(100vh - 120px);
		}
		.crm-addon-master .page-head-row {
			display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;
			gap: 1rem; margin-bottom: 1.5rem;
		}
		.crm-addon-master .page-head-row h1 {
			margin: 0; font-size: 1.875rem; font-weight: 800; color: var(--addon-text);
			letter-spacing: -0.03em; line-height: 1.2;
		}
		.crm-addon-master .page-head-row .page-kicker {
			display: block; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.14em;
			text-transform: uppercase; color: var(--addon-primary); margin-bottom: 0.35rem;
		}
		.crm-addon-master .breadcrumbs {
			font-size: 0.8125rem; padding: 0.45rem 0.85rem; background: rgba(255,255,255,.75);
			border-radius: 999px; border: 1px solid var(--addon-border); backdrop-filter: blur(8px);
		}
		.crm-addon-master .breadcrumbs a { color: var(--addon-primary); font-weight: 600; }
		.crm-addon-master .breadcrumbs .bc-muted { color: var(--addon-muted); font-weight: 500; }

		.crm-addon-master .list-card {
			background: var(--addon-card); border: 1px solid var(--addon-border);
			border-radius: var(--addon-radius); box-shadow: var(--addon-shadow);
			overflow: hidden;
		}
		.crm-addon-master .list-card-head {
			display: flex; justify-content: space-between; align-items: center;
			flex-wrap: wrap; gap: 1rem; padding: 1.15rem 1.35rem;
			background: linear-gradient(180deg, #ffffff 0%, #fafbfc 100%);
			border-bottom: 1px solid var(--addon-border);
		}
		.crm-addon-master .list-card-head .title-wrap { display: flex; align-items: center; gap: 0.75rem; }
		.crm-addon-master .list-card-head .title-icon {
			width: 42px; height: 42px; border-radius: 10px;
			background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
			color: #fff; display: flex; align-items: center; justify-content: center;
			font-size: 1rem; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
		}
		.crm-addon-master .list-card-head .card-title {
			font-weight: 800; font-size: 1.05rem; color: var(--addon-text); margin: 0;
			letter-spacing: -0.02em;
		}
		.crm-addon-master .list-card-head .card-sub {
			font-size: 0.8rem; color: var(--addon-muted); margin: 0.15rem 0 0;
			font-weight: 500;
		}
		.crm-addon-master .btn-add-service {
			background: linear-gradient(180deg, #3b82f6 0%, var(--addon-primary) 100%);
			border: none; color: #fff; font-weight: 700; font-size: 0.875rem;
			padding: 0.55rem 1.15rem; border-radius: 10px; white-space: nowrap;
			box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35); transition: transform 0.15s ease, box-shadow 0.15s ease;
		}
		.crm-addon-master .btn-add-service:hover {
			background: linear-gradient(180deg, #2563eb 0%, var(--addon-primary-hover) 100%);
			color: #fff; transform: translateY(-1px);
			box-shadow: 0 8px 22px rgba(37, 99, 235, 0.4);
		}

		.crm-addon-master .toolbar-row {
			padding: 1rem 1.35rem; background: #f8fafc;
			border-bottom: 1px solid var(--addon-border);
		}
		.crm-addon-master .search-wrap {
			position: relative; flex: 1; max-width: 380px; min-width: 200px;
		}
		.crm-addon-master .search-wrap input {
			padding: 0.6rem 1rem 0.6rem 2.65rem; border-radius: 10px;
			border: 1px solid var(--addon-border); background: #fff;
			font-size: 0.9rem; transition: border-color 0.15s, box-shadow 0.15s;
		}
		.crm-addon-master .search-wrap input:focus {
			border-color: var(--addon-primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
			outline: none;
		}
		.crm-addon-master .search-wrap .search-ico-left {
			position: absolute; left: 0.95rem; top: 50%; transform: translateY(-50%);
			color: var(--addon-muted); pointer-events: none; font-size: 0.9rem;
		}
		.crm-addon-master .search-wrap .btn-search-ico { display: none; }

		.crm-addon-master .table-wrap {
			overflow-x: auto; padding: 0 0.25rem;
		}
		.crm-addon-master .table-addons {
			margin: 0; font-size: 0.9rem; border-collapse: separate; border-spacing: 0;
		}
		.crm-addon-master .table-addons thead th {
			background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
			font-weight: 700; font-size: 0.68rem; text-transform: uppercase;
			letter-spacing: 0.06em; color: var(--addon-muted);
			padding: 0.85rem 1rem; vertical-align: middle; border-bottom: 2px solid var(--addon-border);
			white-space: nowrap;
		}
		.crm-addon-master .table-addons tbody tr {
			transition: background 0.12s ease;
		}
		.crm-addon-master .table-addons tbody tr:hover {
			background: rgba(37, 99, 235, 0.04);
		}
		.crm-addon-master .table-addons tbody td {
			vertical-align: middle; padding: 0.85rem 1rem; border-color: var(--addon-border);
			border-top: none;
		}
		.crm-addon-master .table-addons tbody tr:last-child td:first-child { border-radius: 0 0 0 10px; }
		.crm-addon-master .table-addons tbody tr:last-child td:last-child { border-radius: 0 0 10px 0; }
		.crm-addon-master .id-pill {
			display: inline-flex; align-items: center; justify-content: center;
			min-width: 2.25rem; padding: 0.2rem 0.5rem; font-size: 0.75rem; font-weight: 700;
			color: var(--addon-muted); background: #f1f5f9; border-radius: 8px;
		}
		.crm-addon-master .service-name-cell {
			font-weight: 700; color: var(--addon-text); letter-spacing: -0.01em;
		}
		.crm-addon-master .price-cell {
			font-variant-numeric: tabular-nums; font-weight: 700; color: #0f172a;
			font-size: 0.95rem;
		}
		.crm-addon-master .desc-preview {
			max-width: 420px; max-height: 4.5rem; overflow: hidden; font-size: 0.82rem;
			line-height: 1.45; color: #475569;
		}
		.crm-addon-master .desc-preview p { margin: 0; }
		.crm-addon-master .badge-status {
			font-size: 0.7rem; font-weight: 700; padding: 0.35rem 0.75rem; border-radius: 999px;
			letter-spacing: 0.02em;
		}
		.crm-addon-master .badge-active {
			background: linear-gradient(180deg, #34d399 0%, var(--addon-success) 100%);
			color: #fff; box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
		}
		.crm-addon-master .badge-inactive {
			background: linear-gradient(180deg, #94a3b8 0%, #64748b 100%);
			color: #fff;
		}
		.crm-addon-master .action-cluster {
			display: inline-flex; gap: 6px; flex-wrap: nowrap;
		}
		.crm-addon-master .btn-action {
			width: 36px; height: 36px; padding: 0; display: inline-flex;
			align-items: center; justify-content: center; border-radius: 10px;
			border: none; font-size: 0.85rem;
			transition: transform 0.15s ease, box-shadow 0.15s ease;
		}
		.crm-addon-master .btn-action:hover { transform: translateY(-2px); color: #fff; }
		.crm-addon-master .btn-view {
			background: linear-gradient(180deg, #22d3ee 0%, #06b6d4 100%);
			color: #fff; box-shadow: 0 3px 10px rgba(6, 182, 212, 0.35);
		}
		.crm-addon-master .btn-edit {
			background: linear-gradient(180deg, #60a5fa 0%, var(--addon-primary) 100%);
			color: #fff; box-shadow: 0 3px 10px rgba(37, 99, 235, 0.35);
		}
		.crm-addon-master .btn-del {
			background: linear-gradient(180deg, #fb7185 0%, #e11d48 100%);
			color: #fff; box-shadow: 0 3px 10px rgba(225, 29, 72, 0.3);
		}

		/* Modal — premium shell (flex layout = sticky footer + scrollable body) */
		#addonServiceModal .addon-modal-dialog {
			max-width: 640px;
			width: calc(100% - 1.5rem);
			max-height: calc(100vh - 2rem);
			margin: 1rem auto;
			display: flex;
			align-items: stretch;
		}
		#addonServiceModal .modal-content.addon-modal-shell {
			border: none; border-radius: 16px;
			box-shadow: var(--addon-shadow-lg);
			display: flex;
			flex-direction: column;
			max-height: calc(100vh - 2rem);
			overflow: hidden;
			min-height: 0;
		}
		#addonServiceModal #addonServiceForm {
			display: flex;
			flex-direction: column;
			flex: 1 1 auto;
			min-height: 0;
			max-height: 100%;
			overflow: hidden;
		}
		#addonServiceModal .modal-header.service-info-hd {
			flex: 0 0 auto;
			border-bottom: none; padding: 1rem 1.35rem 1rem;
			background: linear-gradient(125deg, #1e40af 0%, #2563eb 42%, #3b82f6 100%);
			position: relative;
		}
		#addonServiceModal .modal-header.service-info-hd::after {
			content: ''; position: absolute; inset: 0;
			background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
			opacity: 1; pointer-events: none;
		}
		#addonServiceModal .modal-header .modal-title-wrap { position: relative; z-index: 1; }
		#addonServiceModal .modal-header .modal-kicker {
			display: block; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.16em;
			text-transform: uppercase; opacity: 0.85; margin-bottom: 0.35rem;
		}
		#addonServiceModal .modal-header .modal-title {
			font-size: 1.2rem; font-weight: 800; letter-spacing: -0.02em; margin: 0;
		}
		#addonServiceModal .modal-header .modal-subtitle {
			font-size: 0.78rem; opacity: 0.9; font-weight: 500; margin: 0.35rem 0 0;
			max-width: 28rem; line-height: 1.45;
		}
		#addonServiceModal .modal-header.service-info-hd .close {
			position: relative; z-index: 2;
			color: #fff; text-shadow: none; opacity: 0.85;
			font-size: 1.5rem; padding: 0.5rem; margin: -0.25rem -0.35rem 0 0;
			transition: opacity 0.15s, transform 0.15s;
		}
		#addonServiceModal .modal-header.service-info-hd .close:hover { opacity: 1; transform: scale(1.05); }

		#addonServiceModal .modal-body {
			flex: 1 1 auto;
			min-height: 0;
			overflow-x: hidden;
			overflow-y: auto;
			-webkit-overflow-scrolling: touch;
			overscroll-behavior: contain;
			padding: 1rem 1.25rem 1rem;
			background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
		}
		#addonServiceModal .addon-modal-help {
			font-size: 0.75rem; line-height: 1.4; display: block; margin-bottom: 0.35rem;
		}
		#addonServiceModal .modal-body label {
			font-size: 0.78rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;
			letter-spacing: 0.01em;
		}
		#addonServiceModal .modal-body .form-control {
			border-radius: 10px; border-color: var(--addon-border);
			padding: 0.55rem 0.85rem; font-size: 0.925rem;
			transition: border-color 0.15s, box-shadow 0.15s;
		}
		#addonServiceModal .modal-body .form-control:focus {
			border-color: var(--addon-primary);
			box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
		}
		#addonServiceModal .modal-body .input-group-text {
			border-radius: 10px 0 0 10px; background: #f1f5f9;
			border-color: var(--addon-border); font-weight: 700; color: #475569;
		}
		#addonServiceModal .modal-body .input-group .form-control {
			border-radius: 0 10px 10px 0;
		}
		#addonServiceModal .field-panel {
			background: #fff; border: 1px solid var(--addon-border);
			border-radius: 10px; padding: 0.75rem 0.95rem; margin-bottom: 0.65rem;
			box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
		}
		#addonServiceModal .field-panel-desc .note-editor {
			border-radius: 10px;
		}
		#addonServiceModal .price-panel {
			background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
			border: 1px solid #bfdbfe; border-radius: 10px;
			padding: 0.75rem 0.95rem; margin-bottom: 0.65rem;
		}
		#addonServiceModal .active-panel {
			background: linear-gradient(180deg, #ecfdf5 0%, #ffffff 100%);
			border: 1px solid #a7f3d0; border-radius: 10px;
			padding: 0.65rem 0.95rem;
		}
		#addonServiceModal .active-panel .custom-switch .custom-control-label {
			font-weight: 700; color: #065f46; padding-top: 2px;
		}
		#addonServiceModal .active-panel .custom-control-input:checked ~ .custom-control-label::before {
			background-color: var(--addon-success); border-color: var(--addon-success);
		}

		#addonServiceModal .modal-footer.addon-modal-ft {
			flex: 0 0 auto;
			background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
			border-top: 1px solid var(--addon-border);
			padding: 0.85rem 1.25rem 1rem; gap: 0.65rem;
			box-shadow: 0 -4px 16px rgba(15, 23, 42, 0.06);
		}
		#addonServiceModal .btn-modal-primary {
			background: linear-gradient(180deg, #3b82f6 0%, var(--addon-primary) 100%);
			border: none; color: #fff; font-weight: 700; padding: 0.6rem 1.35rem;
			border-radius: 10px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
			transition: transform 0.15s, box-shadow 0.15s;
		}
		#addonServiceModal .btn-modal-primary:hover {
			color: #fff; transform: translateY(-1px);
			box-shadow: 0 8px 22px rgba(37, 99, 235, 0.4);
		}
		#addonServiceModal .btn-modal-ghost {
			background: #fff; border: 1px solid var(--addon-border); color: #475569;
			font-weight: 700; padding: 0.6rem 1.25rem; border-radius: 10px;
			transition: background 0.15s, border-color 0.15s;
		}
		#addonServiceModal .btn-modal-ghost:hover {
			background: #f8fafc; border-color: #cbd5e1; color: #334155;
		}
		#addonServiceModal .note-editor.note-frame { border-color: #cbd5e1 !important; border-radius: 10px !important; }
		#addonServiceModal .note-modal,
		#addonServiceModal .note-dropdown-menu,
		#addonServiceModal .dropdown-menu.show {
			z-index: 1060 !important;
		}

		@media (max-height: 720px) {
			#addonServiceModal .addon-modal-dialog {
				max-height: calc(100vh - 1rem);
				margin: 0.5rem auto;
			}
			#addonServiceModal .modal-content.addon-modal-shell {
				max-height: calc(100vh - 1rem);
			}
			#addonServiceModal .modal-header.service-info-hd {
				padding: 0.75rem 1.1rem 0.75rem;
			}
			#addonServiceModal .modal-header .modal-title {
				font-size: 1.05rem;
			}
		}
	</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper crm-addon-master">

	<?php include __DIR__ . '/../includes/top-header.php'; ?>
	<?php include __DIR__ . '/../includes/sidebar.php'; ?>

	<div class="content-wrapper">
		<?php include __DIR__ . '/../includes/page-header.php'; ?>

		<section class="content">
			<div class="container-fluid">

				<div class="page-head-row">
					<div>
						<span class="page-kicker">Master data</span>
						<h1>Add-On Services Master</h1>
					</div>
					<nav class="breadcrumbs">
						<a href="dashboard.php">Home</a> /
						<a href="crm/addon_service_master.php">Masters</a> /
						<span class="bc-muted">Add-On Service</span>
					</nav>
				</div>

				<div class="list-card">
					<div class="list-card-head">
						<div class="title-wrap">
							<div class="title-icon" aria-hidden="true"><i class="fas fa-puzzle-piece"></i></div>
							<div>
								<h2 class="card-title">Add-On Services List</h2>
								<p class="card-sub">Manage extras, visa assistance, transfers &amp; optional tour items</p>
							</div>
						</div>
						<button type="button" class="btn btn-add-service" id="btnOpenAddAddon">
							<i class="fas fa-plus mr-1"></i> Add New Service
						</button>
					</div>
					<div class="toolbar-row">
						<div class="search-wrap">
							<i class="fas fa-search search-ico-left" aria-hidden="true"></i>
							<input type="search" class="form-control" id="addonSearch" placeholder="Search by service name or description…" autocomplete="off">
							<button type="button" class="btn-search-ico d-none" tabindex="-1" aria-hidden="true"></button>
						</div>
					</div>
					<div class="table-wrap">
						<table class="table table-bordered table-hover table-addons mb-0">
							<thead>
								<tr>
									<th style="width:64px;">ID</th>
									<th>Service Name</th>
									<th>Description</th>
									<th style="width:110px;">Price</th>
									<th style="width:100px;">Status</th>
									<th style="width:130px;">Actions</th>
								</tr>
							</thead>
							<tbody id="addonTableBody">
								<?php foreach ($services as $row) {
									$rid = (int) $row['id'];
									$searchBlob = strtolower($row['name'] . ' ' . strip_tags($row['description_html']));
									?>
									<tr class="addon-row" data-search="<?= htmlspecialchars($searchBlob) ?>">
										<td><span class="id-pill"><?= $rid ?></span></td>
										<td class="service-name-cell"><?= htmlspecialchars($row['name']) ?></td>
										<td>
											<?php if ($row['description_html'] !== '') { ?>
												<div class="desc-preview"><?= $row['description_html'] ?></div>
											<?php } else { ?>
												<span class="text-muted">—</span>
											<?php } ?>
										</td>
										<td class="price-cell">₹<?= number_format((float) $row['price'], 2, '.', ',') ?></td>
										<td>
											<?php if (!empty($row['active'])) { ?>
												<span class="badge badge-status badge-active">Active</span>
											<?php } else { ?>
												<span class="badge badge-status badge-inactive">Inactive</span>
											<?php } ?>
										</td>
										<td>
											<div class="action-cluster">
												<a href="crm/addon_service_view.php?id=<?= $rid ?>" class="btn btn-action btn-view" title="View"><i class="fas fa-eye"></i></a>
												<button type="button" class="btn btn-action btn-edit btn-edit-addon" title="Edit"
													data-id="<?= $rid ?>"><i class="fas fa-edit"></i></button>
												<button type="button" class="btn btn-action btn-del" title="Delete" onclick="return confirm('Delete this service?');"><i class="fas fa-trash-alt"></i></button>
											</div>
										</td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
				</div>

			</div>
		</section>
	</div>

	<!-- Add / Edit Modal -->
	<div class="modal fade" id="addonServiceModal" tabindex="-1" role="dialog" aria-labelledby="addonServiceModalLabel" aria-hidden="true" data-backdrop="static">
		<div class="modal-dialog modal-dialog-centered addon-modal-dialog" role="document">
			<div class="modal-content addon-modal-shell">
				<form id="addonServiceForm" action="#" method="post" onsubmit="return false;">
					<input type="hidden" name="id" id="addon_service_id" value="">
					<div class="modal-header service-info-hd text-white">
						<div class="modal-title-wrap pr-2">
							<span class="modal-kicker" id="addonModalKicker">New service</span>
							<h5 class="modal-title" id="addonServiceModalLabel">Service Information</h5>
							<p class="modal-subtitle mb-0" id="addonModalSubtitle">Define the add-on name, optional rich description, price and availability.</p>
						</div>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="field-panel">
							<div class="form-group mb-0">
								<label for="addon_service_name">Service Name <span class="text-danger">*</span></label>
								<input type="text" class="form-control" id="addon_service_name" name="name" required placeholder="e.g. Bali eVISA, Airport transfer">
							</div>
						</div>
						<div class="field-panel field-panel-desc mb-0">
							<div class="form-group mb-0">
								<label for="addon_description">Description</label>
								<small class="form-text text-muted addon-modal-help">Shown on quotations &amp; vouchers — formatting supported.</small>
								<textarea id="addon_description" name="description" class="form-control"></textarea>
							</div>
						</div>
						<div class="price-panel">
							<div class="form-group mb-0">
								<label for="addon_price">Price <span class="text-danger">*</span></label>
								<div class="input-group">
									<div class="input-group-prepend"><span class="input-group-text">₹</span></div>
									<input type="number" class="form-control" id="addon_price" name="price" step="0.01" min="0" placeholder="0.00" required>
								</div>
							</div>
						</div>
						<div class="active-panel mb-0">
							<div class="custom-control custom-switch mb-0">
								<input type="checkbox" class="custom-control-input" id="addon_active" name="active" value="1" checked>
								<label class="custom-control-label" for="addon_active">Active — show this service when building packages</label>
							</div>
						</div>
					</div>
					<div class="modal-footer addon-modal-ft justify-content-start flex-wrap">
						<button type="submit" class="btn btn-modal-primary" id="addonSubmitBtn">
							<i class="fas fa-check mr-2"></i><span class="btn-submit-text">Create Service</span>
						</button>
						<button type="button" class="btn btn-modal-ghost" data-dismiss="modal">Cancel</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<?php include __DIR__ . '/../includes/footer-links.php'; ?>
</div>
<script>
(function () {
	var addonById = <?= json_encode($servicesById, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

	function destroySummernote() {
		var $ta = $('#addon_description');
		if ($ta.length && $ta.next('.note-editor').length) {
			$ta.summernote('destroy');
		}
	}

	function initSummernote() {
		var $ta = $('#addon_description');
		if (!$ta.next('.note-editor').length) {
			$ta.summernote({
				height: 160,
				toolbar: [
					['style', ['style']],
					['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
					['fontname', ['fontname']],
					['color', ['color']],
					['para', ['ul', 'ol', 'paragraph']],
					['insert', ['link', 'picture']],
					['view', ['fullscreen', 'codeview']]
				],
				placeholder: ''
			});
		}
	}

	function resetForm() {
		$('#addon_service_id').val('');
		$('#addon_service_name').val('');
		$('#addon_price').val('0.00');
		$('#addon_active').prop('checked', true);
	}

	function openModal(mode, id) {
		var $modal = $('#addonServiceModal');
		$modal.data('mode', mode);
		resetForm();
		if (mode === 'edit' && id != null && addonById[id]) {
			var row = addonById[id];
			$('#addon_service_id').val(row.id);
			$('#addon_service_name').val(row.name);
			$('#addon_price').val(parseFloat(row.price).toFixed(2));
			$('#addon_active').prop('checked', !!row.active);
			$modal.data('pendingHtml', row.description_html || '');
			$('#addonModalKicker').text('Editing service');
			$('#addonServiceModalLabel').text('Service Information');
			$('#addonModalSubtitle').text('Update pricing, description or visibility — saved settings apply to new quotations.');
			$('#addonSubmitBtn .btn-submit-text').text('Save changes');
		} else {
			$modal.data('pendingHtml', '');
			$('#addonModalKicker').text('New service');
			$('#addonServiceModalLabel').text('Service Information');
			$('#addonModalSubtitle').text('Define the add-on name, optional rich description, price and availability.');
			$('#addonSubmitBtn .btn-submit-text').text('Create Service');
		}
		$modal.modal('show');
	}

	$('#addonServiceModal').on('shown.bs.modal', function () {
		initSummernote();
		var pending = $(this).data('pendingHtml');
		if (typeof pending === 'string') {
			$('#addon_description').summernote('code', pending);
		}
	});

	$('#addonServiceModal').on('hidden.bs.modal', function () {
		destroySummernote();
	});

	$('#btnOpenAddAddon').on('click', function () {
		openModal('add', null);
	});

	$(document).on('click', '.btn-edit-addon', function () {
		var id = parseInt($(this).data('id'), 10);
		openModal('edit', id);
	});

	$('#addonServiceForm').on('submit', function () {
		var mode = $('#addon_service_id').val() ? 'update' : 'create';
		alert('Demo only: would ' + mode + ' service (connect your API to save).');
		$('#addonServiceModal').modal('hide');
		return false;
	});

	var input = document.getElementById('addonSearch');
	var body = document.getElementById('addonTableBody');
	if (input && body) {
		input.addEventListener('input', function () {
			var q = (input.value || '').toLowerCase().trim();
			body.querySelectorAll('.addon-row').forEach(function (row) {
				var hay = row.getAttribute('data-search') || '';
				row.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
			});
		});
	}

	var pendingEdit = sessionStorage.getItem('addonEditOpen');
	if (pendingEdit) {
		sessionStorage.removeItem('addonEditOpen');
		var pid = parseInt(pendingEdit, 10);
		if (addonById[pid]) {
			openModal('edit', pid);
		}
	}
})();
</script>
</body>
</html>
