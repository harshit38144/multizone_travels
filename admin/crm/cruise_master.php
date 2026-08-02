<?php
require_once __DIR__ . '/bootstrap.php';

$cruises = [
	[
		'id' => 19,
		'name' => 'La Regina Legend Cruise',
		'description' => 'This property boasts clean rooms, a restaurant, a bar and various services for an excellent cruising experience.',
		'image' => null,
		'room_types_count' => 1,
		'price_range' => null,
		'created' => 'Apr 21, 2026',
	],
	[
		'id' => 18,
		'name' => 'Dream Genting Cruise',
		'description' => 'This property boasts clean rooms, a restaurant, a bar and various services for a comfortable stay.',
		'image' => null,
		'room_types_count' => 0,
		'price_range' => null,
		'created' => 'Apr 21, 2026',
	],
];
$total = count($cruises);
$cruisesList = $cruises;
$cruisesById = [];
foreach ($cruises as $row) {
	$id = (int) $row['id'];
	$desc = (string) $row['description'];
	$cruisesById[$id] = [
		'id' => $id,
		'name' => $row['name'],
		'description' => $desc,
		'description_html' => '<p>' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</p>',
		'room_types_count' => (int) $row['room_types_count'],
		'price_range' => $row['price_range'],
		'created' => $row['created'],
	];
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<base href="../">
	<title>Cruise Master</title>
	<?php include __DIR__ . '/../includes/header-links.php'; ?>
	<style>
		.crm-cruise-master .content-wrapper > .content { background: #f4f7f6; }
		.crm-cruise-master .page-head-row {
			display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;
			gap: 1rem; margin-bottom: 1.25rem;
		}
		.crm-cruise-master .page-head-row h1 {
			margin: 0; font-size: 1.75rem; font-weight: 700; color: #212529;
		}
		.crm-cruise-master .breadcrumbs { font-size: 0.875rem; }
		.crm-cruise-master .breadcrumbs a { color: #007bff; }
		.crm-cruise-master .breadcrumbs .bc-muted { color: #6c757d; }
		.crm-cruise-master .list-card {
			background: #fff; border: 1px solid #dee2e6; border-radius: 6px;
			box-shadow: 0 1px 2px rgba(0,0,0,.04); overflow: hidden;
		}
		.crm-cruise-master .list-card-head {
			display: flex; justify-content: space-between; align-items: center;
			flex-wrap: wrap; gap: 0.75rem; padding: 0.9rem 1.15rem;
			border-bottom: 1px solid #e9ecef;
		}
		.crm-cruise-master .list-card-head .card-title {
			font-weight: 700; font-size: 1rem; color: #212529; margin: 0;
		}
		.crm-cruise-master .btn-add-cruise {
			background: #007bff; border: none; color: #fff; font-weight: 600;
			padding: 0.45rem 1rem; border-radius: 4px; white-space: nowrap;
		}
		.crm-cruise-master .btn-add-cruise:hover { background: #0069d9; color: #fff; }
		a.btn-add-cruise, a.btn-add-cruise:hover { color: #fff !important; text-decoration: none; }
		.crm-cruise-master .toolbar-row {
			display: flex; justify-content: space-between; align-items: center;
			flex-wrap: wrap; gap: 0.75rem; padding: 0.85rem 1.15rem;
			border-bottom: 1px solid #e9ecef; background: #fff;
		}
		.crm-cruise-master .search-wrap { position: relative; flex: 1; max-width: 320px; min-width: 180px; }
		.crm-cruise-master .search-wrap input { padding-right: 2.5rem; }
		.crm-cruise-master .search-wrap .btn-search-ico {
			position: absolute; right: 0; top: 0; bottom: 0;
			border: 1px solid #ced4da; border-left: none; border-radius: 0 4px 4px 0;
			background: #f8f9fa; color: #6c757d; padding: 0 0.75rem;
		}
		.crm-cruise-master .showing-meta { font-size: 0.875rem; color: #6c757d; margin: 0; }
		.crm-cruise-master .table-wrap { overflow-x: auto; }
		.crm-cruise-master .table-cruises {
			margin: 0; font-size: 0.9rem;
		}
		.crm-cruise-master .table-cruises thead th {
			background: #f8f9fa; border-bottom: 2px solid #dee2e6;
			font-weight: 700; color: #212529; white-space: nowrap;
			padding: 0.65rem 0.75rem; vertical-align: middle;
		}
		.crm-cruise-master .table-cruises tbody td {
			vertical-align: middle; padding: 0.65rem 0.75rem;
			border-color: #dee2e6;
		}
		.crm-cruise-master .cruise-name-cell { font-weight: 700; color: #212529; }
		.crm-cruise-master .desc-cell {
			max-width: 280px; color: #495057;
			overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
		}
		.crm-cruise-master .thumb-placeholder {
			width: 48px; height: 48px; background: #e9ecef; border-radius: 4px;
			display: inline-flex; align-items: center; justify-content: center;
			color: #6c757d; font-size: 1.25rem;
		}
		.crm-cruise-master .badge-types-yes {
			background: #28a745; color: #fff; font-size: 0.72rem;
			font-weight: 600; padding: 0.25rem 0.5rem; border-radius: 1rem;
		}
		.crm-cruise-master .badge-types-no {
			background: #343a40; color: #fff; font-size: 0.72rem;
			font-weight: 600; padding: 0.25rem 0.5rem; border-radius: 1rem;
		}
		.crm-cruise-master .btn-action {
			width: 32px; height: 32px; padding: 0; display: inline-flex;
			align-items: center; justify-content: center; border-radius: 4px;
			border: none; margin-right: 4px;
		}
		.crm-cruise-master .btn-action:last-child { margin-right: 0; }
		.crm-cruise-master .btn-view { background: #17a2b8; color: #fff; }
		.crm-cruise-master .btn-edit { background: #ffc107; color: #212529; }
		.crm-cruise-master .btn-del { background: #dc3545; color: #fff; }
		.crm-cruise-master .btn-action:hover { opacity: 0.92; color: inherit; filter: brightness(0.95); }

		/* Cruise form modal */
		#cruiseFormModal .cruise-form-dialog {
			max-width: 960px;
			width: calc(100% - 1.5rem);
			max-height: calc(100vh - 2rem);
			margin: 1rem auto;
			display: flex;
			align-items: stretch;
		}
		#cruiseFormModal .modal-content.cruise-form-shell {
			border: none;
			border-radius: 12px;
			box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
			display: flex;
			flex-direction: column;
			max-height: calc(100vh - 2rem);
			overflow: hidden;
			min-height: 0;
		}
		#cruiseFormModal #cruiseFormInner {
			display: flex;
			flex-direction: column;
			flex: 1 1 auto;
			min-height: 0;
			max-height: 100%;
			overflow: hidden;
		}
		#cruiseFormModal .modal-header.cruise-form-hd {
			flex: 0 0 auto;
			background: linear-gradient(125deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
			color: #fff;
			border: none;
			padding: 1rem 1.25rem;
		}
		#cruiseFormModal .modal-header.cruise-form-hd .modal-title { font-weight: 800; font-size: 1.1rem; }
		#cruiseFormModal .modal-header.cruise-form-hd .close {
			color: #fff;
			text-shadow: none;
			opacity: 0.9;
		}
		#cruiseFormModal .modal-body.cruise-modal-bd {
			flex: 1 1 auto;
			min-height: 0;
			overflow-x: hidden;
			overflow-y: auto;
			-webkit-overflow-scrolling: touch;
			overscroll-behavior: contain;
			padding: 1rem 1.25rem;
			background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
		}
		#cruiseFormModal .cruise-modal-bd label { font-weight: 700; color: #212529; font-size: 0.85rem; margin-bottom: 0.35rem; }
		#cruiseFormModal .label-req::after { content: " *"; color: #dc3545; }
		#cruiseFormModal .text-help { font-size: 0.75rem; color: #6c757d; margin-top: 0.35rem; }
		#cruiseFormModal .text-disclaimer { font-size: 0.75rem; color: #dc3545; margin-top: 0.35rem; }
		#cruiseFormModal .text-tip { font-size: 0.75rem; color: #28a745; margin-top: 0.35rem; }
		#cruiseFormModal .cruise-info-panel {
			border: 1px solid #dee2e6;
			border-radius: 8px;
			overflow: hidden;
			margin-bottom: 1rem;
			background: #fff;
		}
		#cruiseFormModal .cruise-panel-hd-blue {
			background: #007bff;
			color: #fff;
			font-weight: 700;
			padding: 0.55rem 1rem;
			font-size: 0.95rem;
			margin: 0;
		}
		#cruiseFormModal .cruise-info-panel-bd { padding: 1rem; }
		#cruiseFormModal .cruise-panel-hd-green {
			background: #28a745;
			color: #fff;
			font-weight: 700;
			padding: 0.5rem 0.85rem;
			font-size: 0.9rem;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.5rem;
			margin: 0;
		}
		#cruiseFormModal .cruise-panel-hd-green .btn-sm-h {
			background: rgba(255,255,255,.2);
			border: 1px solid rgba(255,255,255,.5);
			color: #fff;
			font-size: 0.75rem;
			font-weight: 600;
			padding: 0.2rem 0.5rem;
			border-radius: 4px;
		}
		#cruiseFormModal .cruise-empty-box {
			border: 1px solid #e9ecef;
			border-radius: 4px;
			padding: 1.5rem 1rem;
			text-align: center;
			color: #6c757d;
			font-size: 0.85rem;
			background: #fff;
			margin-bottom: 0;
		}
		#cruiseFormModal .cruise-room-types-list { margin: 0; padding: 0; }
		#cruiseFormModal .cruise-room-line {
			padding-bottom: 1rem;
			margin-bottom: 1rem;
			border-bottom: 1px solid #e9ecef;
		}
		#cruiseFormModal .cruise-room-line:last-child {
			margin-bottom: 0;
			padding-bottom: 0;
			border-bottom: none;
		}
		#cruiseFormModal .cruise-room-line-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.75rem;
			margin-bottom: 0.35rem;
		}
		#cruiseFormModal .cruise-room-line-head label {
			margin-bottom: 0;
		}
		#cruiseFormModal .btn-cruise-room-remove {
			background: #dc3545;
			border: none;
			color: #fff;
			font-weight: 600;
			font-size: 0.8rem;
			padding: 0.25rem 0.6rem;
			border-radius: 4px;
			white-space: nowrap;
			flex-shrink: 0;
		}
		#cruiseFormModal .btn-cruise-room-remove:hover {
			background: #c82333;
			color: #fff;
		}
		#cruiseFormModal .cruise-room-line .input-group-text {
			background: #e9ecef;
			color: #495057;
			font-weight: 600;
		}
		#cruiseFormModal .modal-footer.cruise-form-ft {
			flex: 0 0 auto;
			background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
			border-top: 1px solid #e2e8f0;
			padding: 0.85rem 1.25rem 1rem;
			gap: 0.5rem;
		}
		#cruiseFormModal .btn-cruise-primary {
			background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
			border: none;
			color: #fff;
			font-weight: 700;
			padding: 0.5rem 1.2rem;
			border-radius: 8px;
			box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
		}
		#cruiseFormModal .btn-cruise-primary:hover { color: #fff; transform: translateY(-1px); }
		#cruiseFormModal .btn-cruise-ghost,
		#cruiseViewModal .btn-cruise-ghost {
			background: #fff;
			border: 1px solid #e2e8f0;
			color: #475569;
			font-weight: 700;
			padding: 0.5rem 1.1rem;
			border-radius: 8px;
		}
		#cruiseFormModal .btn-cruise-ghost:hover,
		#cruiseViewModal .btn-cruise-ghost:hover { background: #f8fafc; color: #334155; }
		#cruiseFormModal .note-editor.note-frame { border-color: #ced4da !important; border-radius: 8px !important; }
		#cruiseFormModal .note-modal,
		#cruiseFormModal .note-dropdown-menu,
		#cruiseFormModal .dropdown-menu.show { z-index: 1060 !important; }

		/* View cruise modal */
		#cruiseViewModal .cruise-view-dialog {
			max-width: 640px;
			width: calc(100% - 1.5rem);
			margin: 1rem auto;
		}
		#cruiseViewModal .modal-content.cruise-view-shell {
			border: none;
			border-radius: 12px;
			overflow-x: hidden;
			overflow-y: auto;
			box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
			display: flex;
			flex-direction: column;
			max-height: calc(100vh - 2rem);
		}
		#cruiseViewModal #cruiseViewInner {
			display: flex;
			flex-direction: column;
			flex: 0 0 auto;
			max-height: inherit;
		}
		#cruiseViewModal .modal-header.cruise-view-hd {
			flex: 0 0 auto;
			background: linear-gradient(125deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
			color: #fff;
			border: none;
			padding: 1rem 1.25rem;
		}
		#cruiseViewModal .modal-header.cruise-view-hd .modal-title { font-weight: 800; font-size: 1.1rem; }
		#cruiseViewModal .modal-header.cruise-view-hd .close {
			color: #fff;
			text-shadow: none;
			opacity: 0.9;
		}
		#cruiseViewModal .modal-body.cruise-view-bd {
			flex: 0 0 auto;
			padding: 1rem 1.35rem;
			background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
		}
		#cruiseViewModal .cruise-view-table { margin-bottom: 0; font-size: 0.9rem; }
		#cruiseViewModal .cruise-view-table th {
			width: 34%;
			background: #f8f9fa;
			font-weight: 700;
			color: #212529;
			vertical-align: top;
			padding: 0.55rem 0.75rem;
			border-color: #dee2e6;
		}
		#cruiseViewModal .cruise-view-table td {
			vertical-align: top;
			padding: 0.55rem 0.75rem;
			border-color: #dee2e6;
			color: #334155;
		}
		#cruiseViewModal .cruise-view-desc {
			max-height: 140px;
			overflow-y: auto;
			white-space: pre-wrap;
			word-break: break-word;
			font-size: 0.88rem;
			line-height: 1.45;
		}
		#cruiseViewModal .modal-footer.cruise-view-ft {
			flex: 0 0 auto;
			background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
			border-top: 1px solid #e2e8f0;
			padding: 0.85rem 1.25rem 1rem;
			gap: 0.5rem;
		}

		@media (max-height: 720px) {
			#cruiseFormModal .cruise-form-dialog,
			#cruiseFormModal .modal-content.cruise-form-shell {
				max-height: calc(100vh - 1rem);
			}
		}
	</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper crm-cruise-master">

	<?php include __DIR__ . '/../includes/top-header.php'; ?>
	<?php include __DIR__ . '/../includes/sidebar.php'; ?>

	<div class="content-wrapper">
		<?php include __DIR__ . '/../includes/page-header.php'; ?>

		<section class="content">
			<div class="container-fluid">

				<div class="page-head-row">
					<h1>Cruise Master</h1>
					<nav class="breadcrumbs">
						<a href="dashboard.php">Home</a> /
						<a href="crm/cruise_master.php">Masters</a> /
						<span class="bc-muted">Cruise</span>
					</nav>
				</div>

				<div class="list-card">
					<div class="list-card-head">
						<h2 class="card-title">Cruises List</h2>
						<button type="button" class="btn btn-add-cruise" id="btnOpenCruiseFormAdd"><i class="fas fa-plus mr-1"></i> Add New Cruise</button>
					</div>
					<div class="toolbar-row">
						<div class="search-wrap">
							<input type="search" class="form-control" id="cruiseSearch" placeholder="Search cruises..." autocomplete="off">
							<button type="button" class="btn-search-ico" aria-label="Search"><i class="fas fa-search"></i></button>
						</div>
						<p class="showing-meta mb-0">Showing <span id="cruiseVisibleCount"><?= (int) $total ?></span> of <?= (int) $total ?> cruises</p>
					</div>
					<div class="table-wrap">
						<table class="table table-bordered table-hover table-cruises mb-0">
							<thead>
								<tr>
									<th style="width:56px;">ID</th>
									<th style="width:72px;">Image</th>
									<th>Name</th>
									<th>Description</th>
									<th style="width:110px;">Room Types</th>
									<th style="width:100px;">Price Range</th>
									<th style="width:120px;">Created</th>
									<th style="width:130px;">Actions</th>
								</tr>
							</thead>
							<tbody id="cruiseTableBody">
								<?php foreach ($cruisesList as $row) {
									$rid = (int) $row['id'];
									$rtc = (int) $row['room_types_count'];
									?>
									<tr class="cruise-row" data-id="<?= $rid ?>" data-search="<?= htmlspecialchars(strtolower($row['name'] . ' ' . $row['description'])) ?>">
										<td><?= $rid ?></td>
										<td><span class="thumb-placeholder"><i class="fas fa-ship"></i></span></td>
										<td class="cruise-name-cell"><?= htmlspecialchars($row['name']) ?></td>
										<td><span class="desc-cell d-inline-block" title="<?= htmlspecialchars($row['description']) ?>"><?= htmlspecialchars($row['description']) ?></span></td>
										<td>
											<?php if ($rtc > 0) { ?>
												<span class="badge-types-yes"><?= $rtc ?> types</span>
											<?php } else { ?>
												<span class="badge-types-no">No types</span>
											<?php } ?>
										</td>
										<td class="text-muted"><?= $row['price_range'] !== null ? htmlspecialchars($row['price_range']) : '—' ?></td>
										<td><?= htmlspecialchars($row['created']) ?></td>
										<td>
											<button type="button" class="btn btn-action btn-view btn-cruise-view" data-id="<?= $rid ?>" title="View"><i class="fas fa-eye"></i></button>
											<button type="button" class="btn btn-action btn-edit btn-cruise-edit" data-id="<?= $rid ?>" title="Edit"><i class="fas fa-edit"></i></button>
											<button type="button" class="btn btn-action btn-del" title="Delete" onclick="return confirm('Delete this cruise?');"><i class="fas fa-trash-alt"></i></button>
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

	<!-- Create / Edit Cruise modal -->
	<div class="modal fade" id="cruiseFormModal" tabindex="-1" role="dialog" aria-labelledby="cruiseFormModalLabel" aria-hidden="true" data-backdrop="static">
		<div class="modal-dialog modal-dialog-centered modal-lg cruise-form-dialog" role="document">
			<div class="modal-content cruise-form-shell">
				<form id="cruiseFormInner" action="#" method="post" enctype="multipart/form-data" onsubmit="return false;">
					<input type="hidden" name="id" id="cruiseFormId" value="">
					<div class="modal-header cruise-form-hd text-white">
						<h5 class="modal-title mb-0" id="cruiseFormModalLabel">Create Cruise</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					</div>
					<div class="modal-body cruise-modal-bd">
						<div class="row">
							<div class="col-lg-8 mb-3 mb-lg-0">
								<div class="cruise-info-panel">
									<div class="cruise-panel-hd-blue">Cruise Information</div>
									<div class="cruise-info-panel-bd">
										<div class="form-group">
											<label class="label-req" for="cruiseFormName">Cruise Name</label>
											<input type="text" class="form-control" id="cruiseFormName" name="name" placeholder="Enter cruise name" required>
										</div>
										<div class="form-group">
											<label for="cruiseFormDescription">Description</label>
											<textarea id="cruiseFormDescription" name="description" class="form-control summernote-cruise-modal" rows="4"></textarea>
										</div>
										<div class="form-group mb-0">
											<label for="cruiseFormImage">Cruise Image</label>
											<div class="custom-file">
												<input type="file" class="custom-file-input" id="cruiseFormImage" name="image" accept=".jpg,.jpeg,.png,.gif">
												<label class="custom-file-label" for="cruiseFormImage">Choose file</label>
											</div>
											<div class="text-help">Recommended: 800×600 px. Max 2MB. JPG, PNG, GIF.</div>
											<div class="text-disclaimer"><i class="fas fa-exclamation-triangle mr-1"></i>Use royalty-free or owned images only.</div>
											<div class="text-tip"><i class="fas fa-check-circle mr-1"></i>
												<a href="https://www.pexels.com" target="_blank" rel="noopener">Pexels</a> /
												<a href="https://pixabay.com" target="_blank" rel="noopener">Pixabay</a> /
												<a href="https://unsplash.com" target="_blank" rel="noopener">Unsplash</a>.
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="col-lg-4">
								<div class="cruise-info-panel mb-0">
									<div class="cruise-panel-hd-green">
										<span>Room Types</span>
										<button type="button" class="btn btn-sm-h" id="btnAddCruiseRoomType">+ Add Room Type</button>
									</div>
									<div class="cruise-info-panel-bd">
										<div id="cruiseRoomTypesEmpty" class="cruise-empty-box mb-0">No room types added yet. Click &quot;Add Room Type&quot; to add one.</div>
										<div id="cruiseRoomTypesList" class="cruise-room-types-list"></div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="modal-footer cruise-form-ft justify-content-start flex-wrap">
						<button type="submit" class="btn btn-cruise-primary" id="cruiseFormSubmitBtn"><i class="fas fa-save mr-2"></i><span id="cruiseFormSubmitText">Create Cruise</span></button>
						<button type="button" class="btn btn-cruise-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- View Cruise modal -->
	<div class="modal fade" id="cruiseViewModal" tabindex="-1" role="dialog" aria-labelledby="cruiseViewModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered cruise-view-dialog" role="document">
			<div class="modal-content cruise-view-shell">
				<div id="cruiseViewInner">
					<div class="modal-header cruise-view-hd text-white">
						<h5 class="modal-title mb-0" id="cruiseViewModalLabel">View Cruise</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					</div>
					<div class="modal-body cruise-view-bd">
						<p class="font-weight-bold mb-2" style="font-size: 0.95rem; color: #222;">Cruise details</p>
						<table class="table table-bordered cruise-view-table">
							<tbody>
								<tr>
									<th scope="row">ID</th>
									<td id="cruiseViewId">—</td>
								</tr>
								<tr>
									<th scope="row">Image</th>
									<td id="cruiseViewImage">—</td>
								</tr>
								<tr>
									<th scope="row">Name</th>
									<td id="cruiseViewName">—</td>
								</tr>
								<tr>
									<th scope="row">Description</th>
									<td><div class="cruise-view-desc" id="cruiseViewDesc">—</div></td>
								</tr>
								<tr>
									<th scope="row">Room Types</th>
									<td id="cruiseViewRoomTypes">—</td>
								</tr>
								<tr>
									<th scope="row">Price Range</th>
									<td id="cruiseViewPriceRange">—</td>
								</tr>
								<tr>
									<th scope="row">Created</th>
									<td id="cruiseViewCreated">—</td>
								</tr>
							</tbody>
						</table>
					</div>
					<div class="modal-footer cruise-view-ft justify-content-between flex-wrap">
						<button type="button" class="btn btn-cruise-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Close</button>
						<button type="button" class="btn btn-cruise-primary" id="cruiseViewEditBtn"><i class="fas fa-edit mr-2"></i>Edit</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php include __DIR__ . '/../includes/footer-links.php'; ?>
</div>
<script>
$(function () {
	var cruiseById = <?= json_encode($cruisesById, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

	var input = document.getElementById('cruiseSearch');
	var body = document.getElementById('cruiseTableBody');
	var countEl = document.getElementById('cruiseVisibleCount');
	function filterCruiseTable() {
		if (!input || !body) return;
		var q = (input.value || '').toLowerCase().trim();
		var rows = body.querySelectorAll('.cruise-row');
		var n = 0;
		rows.forEach(function (row) {
			var hay = row.getAttribute('data-search') || '';
			var show = !q || hay.indexOf(q) !== -1;
			row.style.display = show ? '' : 'none';
			if (show) n++;
		});
		if (countEl) countEl.textContent = n;
	}
	if (input) input.addEventListener('input', filterCruiseTable);

	var cruiseRoomSeq = 0;

	function refreshCruiseRoomTypesVisibility() {
		var has = $('#cruiseRoomTypesList').children().length > 0;
		$('#cruiseRoomTypesEmpty').toggle(!has);
	}

	function addCruiseRoomTypeRow() {
		var idx = cruiseRoomSeq++;
		var $block = $('<div class="cruise-room-line" data-room-index="' + idx + '"></div>');
		$block.append(
			'<div class="form-group">' +
				'<div class="cruise-room-line-head">' +
					'<label for="cruise_room_' + idx + '_type">Room Type</label>' +
					'<button type="button" class="btn btn-cruise-room-remove btn-cruise-remove-room" aria-label="Remove room type">' +
						'<i class="fas fa-times mr-1"></i>Remove' +
					'</button>' +
				'</div>' +
				'<input type="text" class="form-control" id="cruise_room_' + idx + '_type" name="room_types[' + idx + '][type]" placeholder="Enter room type (e.g. Interior, Ocean View, Balcony)">' +
			'</div>' +
			'<div class="form-group">' +
				'<label for="cruise_room_' + idx + '_desc">Description</label>' +
				'<textarea class="form-control" id="cruise_room_' + idx + '_desc" name="room_types[' + idx + '][description]" rows="2" placeholder="Enter room description"></textarea>' +
			'</div>' +
			'<div class="form-group mb-0">' +
				'<label for="cruise_room_' + idx + '_price">Price</label>' +
				'<div class="input-group">' +
					'<div class="input-group-prepend"><span class="input-group-text">$</span></div>' +
					'<input type="number" class="form-control" id="cruise_room_' + idx + '_price" name="room_types[' + idx + '][price]" min="0" step="0.01" placeholder="Enter price">' +
				'</div>' +
			'</div>'
		);
		$('#cruiseRoomTypesList').append($block);
		refreshCruiseRoomTypesVisibility();
	}

	function resetCruiseForm() {
		var f = document.getElementById('cruiseFormInner');
		if (f) f.reset();
		$('#cruiseFormId').val('');
		$('#cruiseFormModalLabel').text('Create Cruise');
		$('#cruiseFormSubmitText').text('Create Cruise');
		$('#cruiseFormImage').siblings('.custom-file-label').removeClass('selected').html('Choose file');
		$('#cruiseRoomTypesList').empty();
		cruiseRoomSeq = 0;
		refreshCruiseRoomTypesVisibility();
	}

	function destroyCruiseSummernote() {
		var $ta = $('#cruiseFormDescription');
		if ($ta.length && $ta.next('.note-editor').length) {
			$ta.summernote('destroy');
		}
	}

	function initCruiseSummernote() {
		var $ta = $('#cruiseFormDescription');
		if (!$ta.length || $ta.next('.note-editor').length) return;
		$ta.summernote({
			height: 200,
			dialogsInBody: true,
			toolbar: [
				['style', ['style']],
				['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
				['fontname', ['fontname']],
				['color', ['color']],
				['para', ['ul', 'ol', 'paragraph']],
				['insert', ['link']],
				['view', ['fullscreen', 'codeview']]
			],
			placeholder: ''
		});
	}

	function openCruiseFormModal(mode, id) {
		var $modal = $('#cruiseFormModal');
		resetCruiseForm();
		if (mode === 'edit' && id != null && cruiseById[id]) {
			var r = cruiseById[id];
			$('#cruiseFormId').val(r.id);
			$('#cruiseFormName').val(r.name || '');
			$modal.data('pendingHtml', r.description_html || '');
			$('#cruiseFormModalLabel').text('Edit Cruise');
			$('#cruiseFormSubmitText').text('Save Cruise');
		} else {
			$modal.data('pendingHtml', '');
		}
		$modal.modal('show');
	}

	function openCruiseViewModal(id) {
		var r = cruiseById[id];
		if (!r) return;
		$('#cruiseViewId').text(r.id);
		$('#cruiseViewImage').text('No image uploaded (placeholder)');
		$('#cruiseViewName').text(r.name || '—');
		$('#cruiseViewDesc').text(r.description || '—');
		var rtc = parseInt(r.room_types_count, 10) || 0;
		$('#cruiseViewRoomTypes').text(rtc > 0 ? rtc + ' types' : 'No types');
		$('#cruiseViewPriceRange').text(r.price_range != null && String(r.price_range).trim() !== '' ? r.price_range : '—');
		$('#cruiseViewCreated').text(r.created || '—');
		$('#cruiseViewEditBtn').data('id', id);
		$('#cruiseViewModal').modal('show');
	}

	$('#cruiseFormModal').on('shown.bs.modal', function () {
		initCruiseSummernote();
		var pending = $(this).data('pendingHtml');
		if (typeof pending === 'string' && $('#cruiseFormDescription').next('.note-editor').length) {
			$('#cruiseFormDescription').summernote('code', pending);
		}
	});

	$('#cruiseFormModal').on('hidden.bs.modal', function () {
		destroyCruiseSummernote();
	});

	$('#btnOpenCruiseFormAdd').on('click', function () {
		openCruiseFormModal('add', null);
	});

	$(document).on('click', '.btn-cruise-view', function () {
		var id = parseInt($(this).data('id'), 10);
		openCruiseViewModal(id);
	});

	$(document).on('click', '.btn-cruise-edit', function () {
		var id = parseInt($(this).data('id'), 10);
		openCruiseFormModal('edit', id);
	});

	$('#cruiseViewEditBtn').on('click', function () {
		var id = parseInt($(this).data('id'), 10);
		$('#cruiseViewModal').modal('hide');
		setTimeout(function () { openCruiseFormModal('edit', id); }, 250);
	});

	$('#cruiseFormInner').on('submit', function () {
		alert('Demo only: connect this form to your save endpoint.');
		$('#cruiseFormModal').modal('hide');
		return false;
	});

	$('#cruiseFormImage').on('change', function () {
		var fileName = $(this).val().split('\\').pop();
		$(this).siblings('.custom-file-label').addClass('selected').html(fileName || 'Choose file');
	});

	$('#btnAddCruiseRoomType').on('click', function () {
		addCruiseRoomTypeRow();
	});

	$(document).on('click', '.btn-cruise-remove-room', function () {
		$(this).closest('.cruise-room-line').remove();
		refreshCruiseRoomTypesVisibility();
	});

	function stripOpenParams() {
		if (!window.history || !window.history.replaceState) return;
		var u = new URL(window.location.href);
		u.searchParams.delete('open');
		u.searchParams.delete('id');
		window.history.replaceState({}, '', u.pathname + (u.searchParams.toString() ? '?' + u.searchParams.toString() : ''));
	}

	var q = new URLSearchParams(window.location.search);
	var open = q.get('open');
	var qid = parseInt(q.get('id'), 10);
	if (open === 'create') {
		openCruiseFormModal('add', null);
		stripOpenParams();
	} else if (open === 'edit' && !isNaN(qid) && cruiseById[qid]) {
		openCruiseFormModal('edit', qid);
		stripOpenParams();
	} else if (open === 'view' && !isNaN(qid) && cruiseById[qid]) {
		openCruiseViewModal(qid);
		stripOpenParams();
	}
});
</script>
</body>
</html>
