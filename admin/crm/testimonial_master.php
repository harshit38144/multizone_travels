<?php
require_once __DIR__ . '/bootstrap.php';

$testimonials = [
	[
		'id' => 1,
		'client_name' => 'Priya Sharma',
		'destination' => 'Goa',
		'description' => 'Wonderful beaches and smooth coordination throughout the trip. Highly recommend Multi Zone Travels.',
		'active' => true,
		'visible' => true,
		'created' => 'Apr 21, 2026',
	],
	[
		'id' => 2,
		'client_name' => '',
		'destination' => 'Kashmir',
		'description' => 'Snow views were breathtaking. Drivers were punctual and hotels exceeded expectations.',
		'active' => true,
		'visible' => false,
		'created' => 'Apr 20, 2026',
	],
];

$total = count($testimonials);
$testimonialsById = [];
foreach ($testimonials as $t) {
	$testimonialsById[$t['id']] = $t;
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<base href="../">
	<title>Testimonials — Master</title>
	<?php include __DIR__ . '/../includes/header-links.php'; ?>
	<style>
		.crm-tm-master {
			--tm-primary: #2563eb;
			--tm-primary-hover: #1d4ed8;
			--tm-text: #0f172a;
			--tm-muted: #64748b;
			--tm-border: #e2e8f0;
			--tm-success: #059669;
			--tm-radius: 12px;
			--tm-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.07), 0 2px 4px -2px rgba(15, 23, 42, 0.05);
			--tm-shadow-lg: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
		}
		.crm-tm-master .content-wrapper > .content {
			background: linear-gradient(165deg, #eef2f7 0%, #f8fafc 45%, #f1f5f9 100%);
			min-height: calc(100vh - 120px);
		}
		.crm-tm-master .page-head-row {
			display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;
			gap: 1rem; margin-bottom: 1.5rem;
		}
		.crm-tm-master .page-head-row .page-kicker {
			display: block; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.14em;
			text-transform: uppercase; color: var(--tm-primary); margin-bottom: 0.35rem;
		}
		.crm-tm-master .page-head-row h1 {
			margin: 0; font-size: 1.875rem; font-weight: 800; color: var(--tm-text);
			letter-spacing: -0.03em; line-height: 1.2;
		}
		.crm-tm-master .breadcrumbs {
			font-size: 0.8125rem; padding: 0.45rem 0.85rem; background: rgba(255,255,255,.75);
			border-radius: 999px; border: 1px solid var(--tm-border); backdrop-filter: blur(8px);
		}
		.crm-tm-master .breadcrumbs a { color: var(--tm-primary); font-weight: 600; }
		.crm-tm-master .breadcrumbs .bc-muted { color: var(--tm-muted); font-weight: 500; }

		.crm-tm-master .list-card {
			background: #fff; border: 1px solid var(--tm-border); border-radius: var(--tm-radius);
			box-shadow: var(--tm-shadow); overflow: hidden;
		}
		.crm-tm-master .list-card-head {
			display: flex; justify-content: space-between; align-items: center;
			flex-wrap: wrap; gap: 1rem; padding: 1.15rem 1.35rem;
			background: linear-gradient(180deg, #ffffff 0%, #fafbfc 100%);
			border-bottom: 1px solid var(--tm-border);
		}
		.crm-tm-master .title-wrap { display: flex; align-items: center; gap: 0.75rem; }
		.crm-tm-master .title-icon {
			width: 42px; height: 42px; border-radius: 10px;
			background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
			color: #fff; display: flex; align-items: center; justify-content: center;
			font-size: 1rem; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
		}
		.crm-tm-master .card-title {
			font-weight: 800; font-size: 1.05rem; color: var(--tm-text); margin: 0;
			letter-spacing: -0.02em;
		}
		.crm-tm-master .card-sub {
			font-size: 0.8rem; color: var(--tm-muted); margin: 0.15rem 0 0; font-weight: 500;
		}
		.crm-tm-master .btn-add-tm {
			background: linear-gradient(180deg, #3b82f6 0%, var(--tm-primary) 100%);
			border: none; color: #fff; font-weight: 700; font-size: 0.875rem;
			padding: 0.55rem 1.15rem; border-radius: 10px; white-space: nowrap;
			box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35); transition: transform 0.15s ease, box-shadow 0.15s ease;
		}
		.crm-tm-master .btn-add-tm:hover {
			background: linear-gradient(180deg, #2563eb 0%, var(--tm-primary-hover) 100%);
			color: #fff; transform: translateY(-1px); box-shadow: 0 8px 22px rgba(37, 99, 235, 0.4);
		}

		.crm-tm-master .toolbar-row {
			display: flex; justify-content: space-between; align-items: center;
			flex-wrap: wrap; gap: 0.75rem; padding: 1rem 1.35rem;
			background: #f8fafc; border-bottom: 1px solid var(--tm-border);
		}
		.crm-tm-master .search-wrap {
			position: relative; flex: 1; max-width: 380px; min-width: 200px;
		}
		.crm-tm-master .search-wrap input {
			padding: 0.6rem 1rem 0.6rem 2.65rem; border-radius: 10px;
			border: 1px solid var(--tm-border); background: #fff; font-size: 0.9rem;
			transition: border-color 0.15s, box-shadow 0.15s;
		}
		.crm-tm-master .search-wrap input:focus {
			border-color: var(--tm-primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); outline: none;
		}
		.crm-tm-master .search-wrap .search-ico-left {
			position: absolute; left: 0.95rem; top: 50%; transform: translateY(-50%);
			color: var(--tm-muted); pointer-events: none; font-size: 0.9rem;
		}
		.crm-tm-master .showing-meta {
			font-size: 0.875rem; color: var(--tm-muted); margin: 0; font-weight: 500;
		}

		.crm-tm-master .empty-state {
			padding: 3.5rem 1.5rem 3rem; text-align: center;
			background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
		}
		.crm-tm-master .empty-state .empty-icon-wrap {
			width: 88px; height: 88px; margin: 0 auto 1.25rem;
			border-radius: 50%; background: linear-gradient(180deg, #f1f5f9 0%, #e2e8f0 100%);
			display: flex; align-items: center; justify-content: center;
			box-shadow: inset 0 1px 0 rgba(255,255,255,.8);
		}
		.crm-tm-master .empty-state .empty-icon-wrap i { font-size: 2.25rem; color: #94a3b8; }
		.crm-tm-master .empty-state h3 {
			font-size: 1.15rem; font-weight: 800; color: #475569; margin: 0 0 0.5rem;
			letter-spacing: -0.02em;
		}
		.crm-tm-master .empty-state p {
			font-size: 0.95rem; color: var(--tm-muted); margin: 0 0 1.5rem; max-width: 320px;
			margin-left: auto; margin-right: auto; line-height: 1.5;
		}
		.crm-tm-master .empty-state .btn-add-tm-lg {
			display: inline-flex; align-items: center; justify-content: center;
			padding: 0.65rem 1.5rem; font-size: 0.95rem;
		}

		.crm-tm-master .table-wrap { overflow-x: auto; padding: 0 0.25rem; }
		.crm-tm-master .table-tm { margin: 0; font-size: 0.9rem; border-collapse: separate; border-spacing: 0; }
		.crm-tm-master .table-tm thead th {
			background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
			font-weight: 700; font-size: 0.68rem; text-transform: uppercase;
			letter-spacing: 0.06em; color: var(--tm-muted);
			padding: 0.85rem 1rem; border-bottom: 2px solid var(--tm-border); white-space: nowrap;
		}
		.crm-tm-master .table-tm tbody td {
			padding: 0.85rem 1rem; vertical-align: middle; border-color: var(--tm-border);
		}
		.crm-tm-master .table-tm tbody tr:hover { background: rgba(37, 99, 235, 0.04); }
		.crm-tm-master .id-pill {
			display: inline-flex; min-width: 2.25rem; padding: 0.2rem 0.5rem; font-size: 0.75rem;
			font-weight: 700; color: var(--tm-muted); background: #f1f5f9; border-radius: 8px;
			justify-content: center;
		}
		.crm-tm-master .snippet { max-width: 280px; font-size: 0.82rem; color: #475569; line-height: 1.4; }
		.crm-tm-master .badge-yes {
			font-size: 0.7rem; font-weight: 700; padding: 0.3rem 0.65rem; border-radius: 999px;
			background: linear-gradient(180deg, #34d399 0%, var(--tm-success) 100%);
			color: #fff;
		}
		.crm-tm-master .badge-no {
			font-size: 0.7rem; font-weight: 700; padding: 0.3rem 0.65rem; border-radius: 999px;
			background: #e2e8f0; color: #64748b;
		}
		.crm-tm-master .action-cluster { display: inline-flex; gap: 6px; flex-wrap: nowrap; }
		.crm-tm-master .btn-action {
			width: 36px; height: 36px; padding: 0; display: inline-flex; align-items: center;
			justify-content: center; border-radius: 10px; border: none; font-size: 0.85rem;
			transition: transform 0.15s ease;
		}
		.crm-tm-master .btn-action:hover { transform: translateY(-2px); color: #fff; }
		.crm-tm-master .btn-view-tm {
			background: linear-gradient(180deg, #22d3ee 0%, #06b6d4 100%);
			color: #fff; box-shadow: 0 3px 10px rgba(6, 182, 212, 0.35);
		}
		.crm-tm-master .btn-edit {
			background: linear-gradient(180deg, #60a5fa 0%, var(--tm-primary) 100%);
			color: #fff; box-shadow: 0 3px 10px rgba(37, 99, 235, 0.35);
		}
		.crm-tm-master .btn-del {
			background: linear-gradient(180deg, #fb7185 0%, #e11d48 100%);
			color: #fff; box-shadow: 0 3px 10px rgba(225, 29, 72, 0.3);
		}

		/* —— Form modal (scrollable body, sticky footer) —— */
		#testimonialFormModal .tm-modal-dialog {
			max-width: 720px; width: calc(100% - 1.5rem);
			max-height: calc(100vh - 2rem); margin: 1rem auto;
			display: flex; align-items: stretch;
		}
		#testimonialFormModal .modal-content.tm-modal-shell {
			border: none; border-radius: 16px; overflow: hidden;
			box-shadow: var(--tm-shadow-lg);
			display: flex; flex-direction: column;
			max-height: calc(100vh - 2rem); min-height: 0;
		}
		#testimonialFormModal #testimonialFormInner {
			display: flex; flex-direction: column; flex: 1 1 auto;
			min-height: 0; max-height: 100%; overflow: hidden;
		}
		#testimonialFormModal .modal-header.tm-modal-hd {
			flex: 0 0 auto;
			border-bottom: none; padding: 1rem 1.35rem;
			background: linear-gradient(125deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
			color: #fff; position: relative;
		}
		#testimonialFormModal .modal-header.tm-modal-hd::after {
			content: ''; position: absolute; inset: 0; opacity: 0.08;
			background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
			pointer-events: none;
		}
		#testimonialFormModal .tm-modal-hd .modal-kicker {
			display: block; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.14em;
			text-transform: uppercase; opacity: 0.9; margin-bottom: 0.35rem; position: relative; z-index: 1;
		}
		#testimonialFormModal .tm-modal-hd .modal-title { font-size: 1.15rem; font-weight: 800; margin: 0; position: relative; z-index: 1; }
		#testimonialFormModal .tm-modal-hd .modal-sub { font-size: 0.78rem; opacity: 0.92; margin: 0.35rem 0 0; position: relative; z-index: 1; line-height: 1.45; }
		#testimonialFormModal .tm-modal-hd .close { color: #fff; opacity: 0.9; text-shadow: none; position: relative; z-index: 2; }

		#testimonialFormModal .modal-body.tm-modal-bd {
			flex: 1 1 auto; min-height: 0; overflow-x: hidden; overflow-y: auto;
			-webkit-overflow-scrolling: touch; overscroll-behavior: contain;
			padding: 1rem 1.25rem 0.75rem;
			background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
		}
		#testimonialFormModal .modal-body label { font-size: 0.78rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem; }
		#testimonialFormModal .label-req::after { content: " *"; color: #dc3545; }
		#testimonialFormModal .text-help { font-size: 0.75rem; color: var(--tm-muted); margin-top: 0.25rem; line-height: 1.4; }
		#testimonialFormModal .text-disclaimer { font-size: 0.75rem; color: #dc3545; margin-top: 0.4rem; line-height: 1.45; }
		#testimonialFormModal .form-control {
			border-radius: 10px; border-color: var(--tm-border); font-size: 0.9rem;
			padding: 0.5rem 0.85rem;
		}
		#testimonialFormModal .form-control:focus {
			border-color: var(--tm-primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
		}
		#testimonialFormModal .panel-soft {
			background: #fff; border: 1px solid var(--tm-border); border-radius: 10px;
			padding: 0.75rem 0.95rem; margin-bottom: 0.65rem;
			box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
		}
		#testimonialFormModal .panel-switches {
			background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
			border-color: #bfdbfe;
		}
		#testimonialFormModal .royalty-row {
			display: flex; align-items: flex-start; gap: 0.5rem; margin-top: 0.65rem;
			padding: 0.55rem 0.65rem; border-radius: 8px;
			background: rgba(5, 150, 105, 0.06); border: 1px solid rgba(5, 150, 105, 0.2);
		}
		#testimonialFormModal .royalty-row .form-check-label {
			font-size: 0.78rem; font-weight: 600; color: #065f46; line-height: 1.45;
		}
		#testimonialFormModal .tm-switch-help {
			font-size: 0.75rem; color: var(--tm-muted); margin: 0.15rem 0 0;
			padding-left: 2.75rem; line-height: 1.4;
		}
		#testimonialFormModal .modal-footer.tm-modal-ft {
			flex: 0 0 auto;
			background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
			border-top: 1px solid var(--tm-border);
			padding: 0.85rem 1.25rem 1rem; gap: 0.65rem;
			box-shadow: 0 -4px 16px rgba(15, 23, 42, 0.06);
		}
		#testimonialFormModal .btn-tm-primary {
			background: linear-gradient(180deg, #3b82f6 0%, var(--tm-primary) 100%);
			border: none; color: #fff; font-weight: 700; padding: 0.55rem 1.25rem;
			border-radius: 10px; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
		}
		#testimonialFormModal .btn-tm-primary:hover { color: #fff; transform: translateY(-1px); }
		#testimonialFormModal .btn-tm-ghost {
			background: #fff; border: 1px solid var(--tm-border); color: #475569;
			font-weight: 700; padding: 0.55rem 1.15rem; border-radius: 10px;
		}

		/* View modal */
		#testimonialViewModal .tm-view-dialog {
			max-width: 560px; width: calc(100% - 1.5rem);
			max-height: calc(100vh - 2rem); margin: 1rem auto;
			display: flex; align-items: stretch;
		}
		#testimonialViewModal .modal-content.tm-view-shell {
			border: none; border-radius: 16px; overflow: hidden;
			box-shadow: var(--tm-shadow-lg);
			display: flex; flex-direction: column;
			max-height: calc(100vh - 2rem); min-height: 0;
		}
		#testimonialViewModal #testimonialViewInner { display: flex; flex-direction: column; min-height: 0; max-height: 100%; overflow: hidden; }
		#testimonialViewModal .modal-header.tm-view-hd {
			flex: 0 0 auto;
			background: linear-gradient(125deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
			color: #fff; border: none; padding: 1rem 1.35rem;
		}
		#testimonialViewModal .modal-header .modal-title { font-weight: 800; font-size: 1.1rem; }
		#testimonialViewModal .modal-body.tm-view-bd {
			flex: 1 1 auto; min-height: 0; overflow-y: auto;
			padding: 1.15rem 1.35rem;
		}
		#testimonialViewModal .view-meta {
			font-size: 0.85rem; margin-bottom: 0.65rem;
		}
		#testimonialViewModal .view-meta dt { font-weight: 700; color: var(--tm-muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
		#testimonialViewModal .view-meta dd { margin: 0.15rem 0 0.75rem; color: var(--tm-text); font-weight: 600; }
		#testimonialViewModal .view-desc-box {
			background: #fff; border: 1px solid var(--tm-border); border-radius: 10px;
			padding: 0.85rem 1rem; font-size: 0.9rem; color: #475569; line-height: 1.55;
		}
		#testimonialViewModal .modal-footer.tm-view-ft {
			flex: 0 0 auto;
			background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
			border-top: 1px solid var(--tm-border);
			padding: 0.85rem 1.25rem;
		}

		@media (max-height: 720px) {
			#testimonialFormModal .tm-modal-dialog,
			#testimonialFormModal .modal-content.tm-modal-shell,
			#testimonialViewModal .tm-view-dialog,
			#testimonialViewModal .modal-content.tm-view-shell {
				max-height: calc(100vh - 1rem);
			}
		}
	</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper crm-tm-master">

	<?php include __DIR__ . '/../includes/top-header.php'; ?>
	<?php include __DIR__ . '/../includes/sidebar.php'; ?>

	<div class="content-wrapper">
		<?php include __DIR__ . '/../includes/page-header.php'; ?>

		<section class="content">
			<div class="container-fluid">

				<div class="page-head-row">
					<div>
						<span class="page-kicker">Master data</span>
						<h1>Testimonials</h1>
					</div>
					<nav class="breadcrumbs">
						<a href="dashboard.php">Home</a> /
						<a href="crm/testimonial_master.php">Masters</a> /
						<span class="bc-muted">Testimonials</span>
					</nav>
				</div>

				<div class="list-card">
					<div class="list-card-head">
						<div class="title-wrap">
							<div class="title-icon" aria-hidden="true"><i class="fas fa-comments"></i></div>
							<div>
								<h2 class="card-title">Manage Testimonials</h2>
								<p class="card-sub">Guest reviews linked to destinations — shown on quotes &amp; web</p>
							</div>
						</div>
						<button type="button" class="btn btn-add-tm" id="btnOpenTmFormAdd"><i class="fas fa-plus mr-1"></i> Add New Testimonial</button>
					</div>
					<div class="toolbar-row">
						<div class="search-wrap">
							<i class="fas fa-search search-ico-left" aria-hidden="true"></i>
							<input type="search" class="form-control" id="tmSearch" placeholder="Search testimonials..." autocomplete="off" <?= $total === 0 ? 'disabled' : '' ?>>
						</div>
						<p class="showing-meta mb-0">Showing <span id="tmVisibleCount"><?= (int) $total ?></span> of <?= (int) $total ?> testimonials</p>
					</div>

					<?php if ($total === 0) { ?>
						<div class="empty-state">
							<div class="empty-icon-wrap"><i class="fas fa-comments"></i></div>
							<h3>No testimonials found</h3>
							<p>Start by adding your first testimonial.</p>
							<button type="button" class="btn btn-add-tm btn-add-tm-lg" id="btnOpenTmFormEmpty"><i class="fas fa-plus mr-2"></i> Add New Testimonial</button>
						</div>
					<?php } else { ?>
						<div class="table-wrap">
							<table class="table table-bordered table-hover table-tm mb-0">
								<thead>
									<tr>
										<th style="width:56px;">ID</th>
										<th>Client</th>
										<th>Destination</th>
										<th>Preview</th>
										<th style="width:90px;">Active</th>
										<th style="width:90px;">Visible</th>
										<th style="width:140px;">Actions</th>
									</tr>
								</thead>
								<tbody id="tmTableBody">
									<?php foreach ($testimonials as $row) {
										$rid = (int) $row['id'];
										$blob = strtolower(($row['client_name'] ?? '') . ' ' . $row['destination'] . ' ' . ($row['description'] ?? ''));
										$d = $row['description'];
										$prev = strlen($d) > 120 ? substr($d, 0, 120) . '…' : $d;
										?>
										<tr class="tm-row" data-search="<?= htmlspecialchars($blob) ?>">
											<td><span class="id-pill"><?= $rid ?></span></td>
											<td class="font-weight-bold"><?= htmlspecialchars($row['client_name'] ?: 'Anonymous') ?></td>
											<td><?= htmlspecialchars($row['destination']) ?></td>
											<td><span class="snippet d-inline-block text-truncate" style="max-width:260px;"><?= htmlspecialchars($prev) ?></span></td>
											<td><?= !empty($row['active']) ? '<span class="badge-yes">Yes</span>' : '<span class="badge-no">No</span>' ?></td>
											<td><?= !empty($row['visible']) ? '<span class="badge-yes">Yes</span>' : '<span class="badge-no">No</span>' ?></td>
											<td>
												<div class="action-cluster">
													<button type="button" class="btn btn-action btn-view-tm btn-tm-view" title="View" data-id="<?= $rid ?>"><i class="fas fa-eye"></i></button>
													<button type="button" class="btn btn-action btn-edit btn-tm-edit" title="Edit" data-id="<?= $rid ?>"><i class="fas fa-edit"></i></button>
													<button type="button" class="btn btn-action btn-del" title="Delete" onclick="return confirm('Delete this testimonial?');"><i class="fas fa-trash-alt"></i></button>
												</div>
											</td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						</div>
					<?php } ?>
				</div>

			</div>
		</section>
	</div>

	<!-- Create / Edit modal -->
	<div class="modal fade" id="testimonialFormModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
		<div class="modal-dialog modal-dialog-centered tm-modal-dialog" role="document">
			<div class="modal-content tm-modal-shell">
				<form id="testimonialFormInner" action="#" method="post" enctype="multipart/form-data" onsubmit="return false;">
					<input type="hidden" name="id" id="tmFormId" value="">
					<div class="modal-header tm-modal-hd text-white">
						<div class="pr-3">
							<span class="modal-kicker" id="tmFormKicker">New testimonial</span>
							<h5 class="modal-title" id="tmFormTitle">Testimonial Information</h5>
							<p class="modal-sub mb-0" id="tmFormSub">Add client details, destination and review text.</p>
						</div>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					</div>
					<div class="modal-body tm-modal-bd">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group panel-soft mb-2">
									<label for="tmFormClient">Client Name</label>
									<input type="text" class="form-control" id="tmFormClient" name="client_name" placeholder="Enter client name (optional)">
									<div class="text-help">Leave empty for anonymous testimonial</div>
								</div>
								<div class="form-group panel-soft mb-2">
									<label class="label-req" for="tmFormDestination">Destination Name</label>
									<input type="text" class="form-control" id="tmFormDestination" name="destination" placeholder="Enter destination name" required>
								</div>
								<div class="form-group panel-soft mb-0">
									<label for="tmFormImage">Client Image</label>
									<div class="custom-file">
										<input type="file" class="custom-file-input" id="tmFormImage" name="client_image" accept=".jpg,.jpeg,.png,.gif">
										<label class="custom-file-label" for="tmFormImage">Choose file</label>
									</div>
									<div class="text-help">Recommended 150×150 px. Max 2MB. JPG, PNG, GIF.</div>
									<div class="text-disclaimer"><i class="fas fa-exclamation-triangle text-warning mr-1"></i>Disclaimer: Upload only royalty-free or owned images. Multi Zone Travels is not liable for copyright issues.</div>
									<div class="royalty-row">
										<input type="checkbox" class="form-check-input" id="tmFormRoyalty" name="royalty_ack" value="1">
										<label class="form-check-label mb-0" for="tmFormRoyalty">Use royalty-free photos — <a href="https://www.pexels.com" target="_blank" rel="noopener">Pexels</a> / <a href="https://pixabay.com" target="_blank" rel="noopener">Pixabay</a> / <a href="https://unsplash.com" target="_blank" rel="noopener">Unsplash</a>.</label>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group panel-soft mb-0">
									<label class="label-req" for="tmFormDescription">Testimonial Description</label>
									<textarea class="form-control" id="tmFormDescription" name="description" rows="10" placeholder="Enter testimonial description..." required></textarea>
									<div class="text-help">Write the client&apos;s testimonial or review</div>
								</div>
							</div>
						</div>
						<div class="row mt-1">
							<div class="col-12">
								<div class="panel-soft panel-switches mb-0">
									<div class="custom-control custom-switch mb-2">
										<input type="checkbox" class="custom-control-input" id="tmFormActive" name="active" value="1" checked>
										<label class="custom-control-label" for="tmFormActive">Active</label>
									</div>
									<p class="tm-switch-help">Uncheck to make testimonial inactive</p>
									<div class="custom-control custom-switch mb-2 mt-2">
										<input type="checkbox" class="custom-control-input" id="tmFormVisible" name="visible" value="1" checked>
										<label class="custom-control-label" for="tmFormVisible">Is Visible</label>
									</div>
									<p class="tm-switch-help mb-0">Show on PDF quotations and web links</p>
								</div>
							</div>
						</div>
					</div>
					<div class="modal-footer tm-modal-ft justify-content-start flex-wrap">
						<button type="submit" class="btn btn-tm-primary" id="tmFormSubmit"><i class="fas fa-save mr-2"></i><span id="tmFormSubmitText">Create Testimonial</span></button>
						<button type="button" class="btn btn-tm-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- View modal -->
	<div class="modal fade" id="testimonialViewModal" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered tm-view-dialog" role="document">
			<div class="modal-content tm-view-shell">
				<div id="testimonialViewInner">
					<div class="modal-header tm-view-hd">
						<h5 class="modal-title mb-0">Testimonial details</h5>
						<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					</div>
					<div class="modal-body tm-view-bd">
						<dl class="view-meta mb-0">
							<dt>Client</dt>
							<dd id="tmViewClient">—</dd>
							<dt>Destination</dt>
							<dd id="tmViewDestination">—</dd>
							<dt>Status</dt>
							<dd id="tmViewStatus">—</dd>
							<dt>Created</dt>
							<dd id="tmViewCreated">—</dd>
						</dl>
						<p class="font-weight-bold text-uppercase small text-muted mb-1" style="font-size:0.72rem;letter-spacing:0.06em;">Description</p>
						<div class="view-desc-box" id="tmViewDescription"></div>
					</div>
					<div class="modal-footer tm-view-ft justify-content-between flex-wrap">
						<button type="button" class="btn btn-tm-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Close</button>
						<button type="button" class="btn btn-tm-primary" id="tmViewEditBtn"><i class="fas fa-edit mr-2"></i>Edit</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php include __DIR__ . '/../includes/footer-links.php'; ?>
</div>
<script>
(function () {
	var tmById = <?= json_encode($testimonialsById, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

	function resetForm() {
		$('#tmFormId').val('');
		$('#tmFormClient').val('');
		$('#tmFormDestination').val('');
		$('#tmFormDescription').val('');
		$('#tmFormActive').prop('checked', true);
		$('#tmFormVisible').prop('checked', true);
		$('#tmFormRoyalty').prop('checked', false);
		$('#tmFormImage').val('');
		$('#tmFormImage').siblings('.custom-file-label').removeClass('selected').html('Choose file');
	}

	function openFormModal(mode, id) {
		resetForm();
		if (mode === 'edit' && id != null && tmById[id]) {
			var r = tmById[id];
			$('#tmFormId').val(r.id);
			$('#tmFormClient').val(r.client_name || '');
			$('#tmFormDestination').val(r.destination || '');
			$('#tmFormDescription').val(r.description || '');
			$('#tmFormActive').prop('checked', !!r.active);
			$('#tmFormVisible').prop('checked', !!r.visible);
			$('#tmFormKicker').text('Editing testimonial');
			$('#tmFormSub').text('Update text, destination or visibility — saves apply to new quotes.');
			$('#tmFormSubmitText').text('Save changes');
		} else {
			$('#tmFormKicker').text('New testimonial');
			$('#tmFormSub').text('Add client details, destination and review text.');
			$('#tmFormSubmitText').text('Create Testimonial');
		}
		$('#testimonialFormModal').modal('show');
	}

	function openViewModal(id) {
		var r = tmById[id];
		if (!r) return;
		var client = (r.client_name && String(r.client_name).trim()) ? r.client_name : 'Anonymous';
		$('#tmViewClient').text(client);
		$('#tmViewDestination').text(r.destination || '—');
		var st = [];
		if (r.active) st.push('Active'); else st.push('Inactive');
		if (r.visible) st.push('Visible'); else st.push('Hidden');
		$('#tmViewStatus').text(st.join(' · '));
		$('#tmViewCreated').text(r.created || '—');
		$('#tmViewDescription').text(r.description || '—');
		$('#tmViewEditBtn').data('id', id);
		$('#testimonialViewModal').modal('show');
	}

	$('#btnOpenTmFormAdd, #btnOpenTmFormEmpty').on('click', function () {
		openFormModal('add', null);
	});

	$(document).on('click', '.btn-tm-edit', function () {
		var id = parseInt($(this).data('id'), 10);
		openFormModal('edit', id);
	});

	$(document).on('click', '.btn-tm-view', function () {
		var id = parseInt($(this).data('id'), 10);
		openViewModal(id);
	});

	$('#tmViewEditBtn').on('click', function () {
		var id = parseInt($(this).data('id'), 10);
		$('#testimonialViewModal').modal('hide');
		setTimeout(function () { openFormModal('edit', id); }, 250);
	});

	$('#testimonialFormInner').on('submit', function () {
		alert('Demo only: connect save endpoint to persist testimonials.');
		$('#testimonialFormModal').modal('hide');
		return false;
	});

	$('.custom-file-input').on('change', function () {
		var fileName = $(this).val().split('\\').pop();
		$(this).siblings('.custom-file-label').addClass('selected').html(fileName || 'Choose file');
	});

	var input = document.getElementById('tmSearch');
	var body = document.getElementById('tmTableBody');
	var countEl = document.getElementById('tmVisibleCount');
	var totalRows = <?= (int) $total ?>;
	if (input && body && countEl) {
		input.addEventListener('input', function () {
			var q = (input.value || '').toLowerCase().trim();
			var rows = body.querySelectorAll('.tm-row');
			var n = 0;
			rows.forEach(function (row) {
				var hay = row.getAttribute('data-search') || '';
				var show = !q || hay.indexOf(q) !== -1;
				row.style.display = show ? '' : 'none';
				if (show) n++;
			});
			countEl.textContent = n;
		});
	}

	var pending = sessionStorage.getItem('tmFormOpen');
	if (pending) {
		sessionStorage.removeItem('tmFormOpen');
		try {
			var o = JSON.parse(pending);
			if (o.mode === 'edit' && o.id && tmById[o.id]) {
				openFormModal('edit', parseInt(o.id, 10));
			} else if (o.mode === 'create') {
				openFormModal('add', null);
			}
		} catch (e) {}
	}

	var q = new URLSearchParams(window.location.search);
	if (q.get('open') === 'edit') {
		var eid = parseInt(q.get('id'), 10);
		if (eid && tmById[eid]) {
			openFormModal('edit', eid);
		}
		stripQueryFromUrl();
	} else if (q.get('open') === 'create') {
		openFormModal('add', null);
		stripQueryFromUrl();
	}

	function stripQueryFromUrl() {
		if (!window.history || !window.history.replaceState) return;
		var u = new URL(window.location.href);
		u.search = '';
		window.history.replaceState({}, '', u.pathname + u.search);
	}
})();
</script>
</body>
</html>
