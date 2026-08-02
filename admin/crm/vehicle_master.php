<?php
require_once __DIR__ . '/bootstrap.php';

$vehicles = [
	[
		'id' => 40,
		'name' => 'Vehicle on Disposal',
		'type' => 'Car',
		'capacity' => '4 persons',
		'image' => null,
		'description' => 'Dedicated vehicle available on disposal basis for city and outstation use.',
		'created_by' => 'Vivek Giri',
		'created' => 'Apr 21, 2026',
	],
	[
		'id' => 39,
		'name' => 'Innova Crysta',
		'type' => 'SUV',
		'capacity' => '5 persons',
		'image' => 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=96&h=96&fit=crop&q=80',
		'description' => 'Premium SUV suitable for family and small groups.',
		'created_by' => 'Vivek Giri',
		'created' => 'Apr 21, 2026',
	],
	[
		'id' => 38,
		'name' => 'Maruti Swift / Etios',
		'type' => 'Car',
		'capacity' => '4 persons',
		'image' => null,
		'description' => 'Economical sedan options for comfortable transfers.',
		'created_by' => 'Vivek Giri',
		'created' => 'Apr 21, 2026',
	],
	[
		'id' => 37,
		'name' => 'Tempo Traveller',
		'type' => 'Coach',
		'capacity' => '12 persons',
		'image' => null,
		'description' => 'Spacious coach for medium-sized groups.',
		'created_by' => 'Vivek Giri',
		'created' => 'Apr 21, 2026',
	],
];
$total = count($vehicles);
$typeOptions = array_unique(array_column($vehicles, 'type'));
sort($typeOptions);
$vehiclesById = [];
foreach ($vehicles as $row) {
	$vehiclesById[(int) $row['id']] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<base href="../">
	<title>Vehicle Master</title>
	<?php include __DIR__ . '/../includes/header-links.php'; ?>
	<style>
		.crm-vehicle-master .content-wrapper > .content { background: #f4f7f6; }
		.crm-vehicle-master .page-head-row {
			display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;
			gap: 1rem; margin-bottom: 1.25rem;
		}
		.crm-vehicle-master .page-head-row h1 {
			margin: 0; font-size: 1.75rem; font-weight: 700; color: #212529;
		}
		.crm-vehicle-master .breadcrumbs { font-size: 0.875rem; }
		.crm-vehicle-master .breadcrumbs a { color: #007bff; }
		.crm-vehicle-master .breadcrumbs .bc-muted { color: #6c757d; }
		.crm-vehicle-master .list-card {
			background: #fff; border: 1px solid #dee2e6; border-radius: 6px;
			box-shadow: 0 1px 2px rgba(0,0,0,.04); overflow: hidden;
		}
		.crm-vehicle-master .list-card-head {
			display: flex; justify-content: space-between; align-items: center;
			flex-wrap: wrap; gap: 0.75rem; padding: 0.9rem 1.15rem;
			border-bottom: 1px solid #e9ecef;
		}
		.crm-vehicle-master .list-card-head .card-title {
			font-weight: 700; font-size: 1rem; color: #212529; margin: 0;
		}
		.crm-vehicle-master .btn-add-vehicle {
			background: #007bff; border: none; color: #fff; font-weight: 600;
			padding: 0.45rem 1rem; border-radius: 4px; white-space: nowrap;
		}
		.crm-vehicle-master .btn-add-vehicle:hover { background: #0069d9; color: #fff; }
		a.btn-add-vehicle, a.btn-add-vehicle:hover { color: #fff !important; text-decoration: none; }
		.crm-vehicle-master .toolbar-row {
			display: flex; justify-content: space-between; align-items: center;
			flex-wrap: wrap; gap: 0.75rem; padding: 0.85rem 1.15rem;
			border-bottom: 1px solid #e9ecef; background: #fff;
		}
		.crm-vehicle-master .toolbar-left {
			display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;
			flex: 1; min-width: 0;
		}
		.crm-vehicle-master .search-wrap { position: relative; flex: 1; max-width: 320px; min-width: 180px; }
		.crm-vehicle-master .search-wrap input { padding-right: 2.5rem; }
		.crm-vehicle-master .search-wrap .btn-search-ico {
			position: absolute; right: 0; top: 0; bottom: 0;
			border: 1px solid #ced4da; border-left: none; border-radius: 0 4px 4px 0;
			background: #f8f9fa; color: #6c757d; padding: 0 0.75rem;
		}
		.crm-vehicle-master .filter-type { min-width: 180px; max-width: 220px; }
		.crm-vehicle-master .table-wrap { overflow-x: auto; }
		.crm-vehicle-master .table-vehicles {
			margin: 0; font-size: 0.9rem;
		}
		.crm-vehicle-master .table-vehicles thead th {
			background: #f8f9fa; border-bottom: 2px solid #dee2e6;
			font-weight: 700; color: #212529; white-space: nowrap;
			padding: 0.65rem 0.75rem; vertical-align: middle;
		}
		.crm-vehicle-master .table-vehicles tbody td {
			vertical-align: middle; padding: 0.65rem 0.75rem;
			border-color: #dee2e6;
		}
		.crm-vehicle-master .vehicle-name-cell { font-weight: 700; color: #212529; }
		.crm-vehicle-master .thumb-cell { width: 72px; }
		.crm-vehicle-master .thumb-img {
			width: 48px; height: 48px; object-fit: cover; border-radius: 6px;
			display: block;
		}
		.crm-vehicle-master .badge-no-img {
			display: inline-flex; align-items: center; justify-content: center;
			min-width: 72px; height: 32px; padding: 0 0.5rem;
			background: #495057; color: #fff; font-size: 0.7rem; font-weight: 600;
			border-radius: 6px;
		}
		.crm-vehicle-master .btn-action {
			width: 32px; height: 32px; padding: 0; display: inline-flex;
			align-items: center; justify-content: center; border-radius: 4px;
			border: none; margin-right: 4px;
		}
		.crm-vehicle-master .btn-action:last-child { margin-right: 0; }
		.crm-vehicle-master .btn-view { background: #17a2b8; color: #fff; }
		.crm-vehicle-master .btn-edit { background: #007bff; color: #fff; }
		.crm-vehicle-master .btn-del { background: #dc3545; color: #fff; }
		.crm-vehicle-master .btn-action:hover { opacity: 0.92; color: #fff; filter: brightness(0.95); }
		.crm-vehicle-master .btn-edit:hover { color: #fff; }

		/* Vehicle form modal */
		#vehicleFormModal .vehicle-form-dialog {
			max-width: 720px;
			width: calc(100% - 1.5rem);
			margin: 1rem auto;
		}
		#vehicleFormModal .modal-content.vehicle-form-shell {
			border: none;
			border-radius: 12px;
			overflow-x: hidden;
			overflow-y: auto;
			box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
			display: flex;
			flex-direction: column;
			max-height: calc(100vh - 2rem);
		}
		#vehicleFormModal #vehicleFormInner {
			display: flex;
			flex-direction: column;
			flex: 0 0 auto;
			max-height: inherit;
		}
		#vehicleFormModal .modal-header.vehicle-form-hd {
			flex: 0 0 auto;
			background: linear-gradient(125deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
			color: #fff;
			border: none;
			padding: 1rem 1.25rem;
		}
		#vehicleFormModal .modal-header.vehicle-form-hd .modal-title { font-weight: 800; font-size: 1.1rem; }
		#vehicleFormModal .modal-header.vehicle-form-hd .close {
			color: #fff;
			text-shadow: none;
			opacity: 0.9;
		}
		#vehicleFormModal .modal-body.vehicle-modal-bd {
			flex: 0 0 auto;
			overflow-y: auto;
			-webkit-overflow-scrolling: touch;
			padding: 1rem 1.25rem;
			background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
		}
		#vehicleFormModal .vehicle-modal-bd label {
			font-weight: 700;
			color: #334155;
			font-size: 0.85rem;
			margin-bottom: 0.35rem;
		}
		#vehicleFormModal .label-req::after { content: " *"; color: #dc3545; }
		#vehicleFormModal .text-help { font-size: 0.75rem; color: #6c757d; margin-top: 0.35rem; }
		#vehicleFormModal .text-disclaimer { font-size: 0.75rem; color: #dc3545; margin-top: 0.35rem; }
		#vehicleFormModal .text-tip { font-size: 0.75rem; color: #28a745; margin-top: 0.35rem; }
		#vehicleFormModal .modal-body .form-control {
			border-radius: 8px;
			border-color: #e2e8f0;
		}
		#vehicleFormModal .modal-body .form-control:focus {
			border-color: #2563eb;
			box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
		}
		#vehicleFormModal .modal-footer.vehicle-form-ft {
			flex: 0 0 auto;
			background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
			border-top: 1px solid #e2e8f0;
			padding: 0.85rem 1.25rem 1rem;
			gap: 0.5rem;
		}
		#vehicleFormModal .btn-vehicle-primary {
			background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
			border: none;
			color: #fff;
			font-weight: 700;
			padding: 0.5rem 1.2rem;
			border-radius: 8px;
			box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
		}
		#vehicleFormModal .btn-vehicle-primary:hover { color: #fff; transform: translateY(-1px); }
		#vehicleFormModal .btn-vehicle-ghost,
		#vehicleViewModal .btn-vehicle-ghost {
			background: #fff;
			border: 1px solid #e2e8f0;
			color: #475569;
			font-weight: 700;
			padding: 0.5rem 1.1rem;
			border-radius: 8px;
		}
		#vehicleFormModal .btn-vehicle-ghost:hover,
		#vehicleViewModal .btn-vehicle-ghost:hover { background: #f8fafc; color: #334155; }

		/* View vehicle modal */
		#vehicleViewModal .vehicle-view-dialog {
			max-width: 640px;
			width: calc(100% - 1.5rem);
			margin: 1rem auto;
		}
		#vehicleViewModal .modal-content.vehicle-view-shell {
			border: none;
			border-radius: 12px;
			overflow-x: hidden;
			overflow-y: auto;
			box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
			display: flex;
			flex-direction: column;
			max-height: calc(100vh - 2rem);
		}
		#vehicleViewModal #vehicleViewInner {
			display: flex;
			flex-direction: column;
			flex: 0 0 auto;
			max-height: inherit;
		}
		#vehicleViewModal .modal-header.vehicle-view-hd {
			flex: 0 0 auto;
			background: linear-gradient(125deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
			color: #fff;
			border: none;
			padding: 1rem 1.25rem;
		}
		#vehicleViewModal .modal-header.vehicle-view-hd .modal-title { font-weight: 800; font-size: 1.1rem; }
		#vehicleViewModal .modal-header.vehicle-view-hd .close {
			color: #fff;
			text-shadow: none;
			opacity: 0.9;
		}
		#vehicleViewModal .modal-body.vehicle-view-bd {
			flex: 0 0 auto;
			padding: 1rem 1.35rem;
			background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
		}
		#vehicleViewModal .vehicle-view-table { margin-bottom: 0; font-size: 0.9rem; }
		#vehicleViewModal .vehicle-view-table th {
			width: 34%;
			background: #f8f9fa;
			font-weight: 700;
			color: #212529;
			vertical-align: top;
			padding: 0.55rem 0.75rem;
			border-color: #dee2e6;
		}
		#vehicleViewModal .vehicle-view-table td {
			vertical-align: top;
			padding: 0.55rem 0.75rem;
			border-color: #dee2e6;
			color: #334155;
		}
		#vehicleViewModal .vehicle-view-desc {
			max-height: 120px;
			overflow-y: auto;
			white-space: pre-wrap;
			word-break: break-word;
			font-size: 0.88rem;
			line-height: 1.45;
		}
		#vehicleViewModal .modal-footer.vehicle-view-ft {
			flex: 0 0 auto;
			background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
			border-top: 1px solid #e2e8f0;
			padding: 0.85rem 1.25rem 1rem;
			gap: 0.5rem;
		}
	</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper crm-vehicle-master">

	<?php include __DIR__ . '/../includes/top-header.php'; ?>
	<?php include __DIR__ . '/../includes/sidebar.php'; ?>

	<div class="content-wrapper">
		<?php include __DIR__ . '/../includes/page-header.php'; ?>

		<section class="content">
			<div class="container-fluid">

				<div class="page-head-row">
					<h1>Vehicle Master</h1>
					<nav class="breadcrumbs">
						<a href="dashboard.php">Home</a> /
						<a href="crm/vehicle_master.php">Masters</a> /
						<span class="bc-muted">Vehicle</span>
					</nav>
				</div>

				<div class="list-card">
					<div class="list-card-head">
						<h2 class="card-title">Vehicles List</h2>
						<button type="button" class="btn btn-add-vehicle" id="btnOpenVehicleFormAdd"><i class="fas fa-plus mr-1"></i> Add New Vehicle</button>
					</div>
					<div class="toolbar-row">
						<div class="toolbar-left">
							<div class="search-wrap">
								<input type="search" class="form-control" id="vehicleSearch" placeholder="Search..." autocomplete="off">
								<button type="button" class="btn-search-ico" aria-label="Search"><i class="fas fa-search"></i></button>
							</div>
							<select class="form-control filter-type" id="vehicleTypeFilter" aria-label="Vehicle type">
								<option value="">All Vehicle Types</option>
								<?php foreach ($typeOptions as $t) { ?>
									<option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
								<?php } ?>
							</select>
						</div>
					</div>
					<div class="table-wrap">
						<table class="table table-bordered table-hover table-striped table-vehicles mb-0">
							<thead>
								<tr>
									<th style="width:56px;">ID</th>
									<th class="thumb-cell">Image</th>
									<th>Vehicle Name</th>
									<th style="width:100px;">Type</th>
									<th style="width:120px;">Capacity</th>
									<th style="width:130px;">Created At</th>
									<th style="width:130px;">Actions</th>
								</tr>
							</thead>
							<tbody id="vehicleTableBody">
								<?php foreach ($vehicles as $row) {
									$rid = (int) $row['id'];
									$hasImg = !empty($row['image']);
									?>
									<tr class="vehicle-row"
										data-id="<?= $rid ?>"
										data-search="<?= htmlspecialchars(strtolower($row['name'] . ' ' . $row['type'] . ' ' . $row['capacity'] . ' ' . ($row['description'] ?? ''))) ?>"
										data-type="<?= htmlspecialchars($row['type']) ?>">
										<td><?= $rid ?></td>
										<td>
											<?php if ($hasImg) { ?>
												<img class="thumb-img" src="<?= htmlspecialchars($row['image']) ?>" alt="">
											<?php } else { ?>
												<span class="badge-no-img">No Image</span>
											<?php } ?>
										</td>
										<td class="vehicle-name-cell"><?= htmlspecialchars($row['name']) ?></td>
										<td><?= htmlspecialchars($row['type']) ?></td>
										<td><?= htmlspecialchars($row['capacity']) ?></td>
										<td><?= htmlspecialchars($row['created']) ?></td>
										<td>
											<button type="button" class="btn btn-action btn-view btn-vehicle-view" data-id="<?= $rid ?>" title="View"><i class="fas fa-eye"></i></button>
											<button type="button" class="btn btn-action btn-edit btn-vehicle-edit" data-id="<?= $rid ?>" title="Edit"><i class="fas fa-edit"></i></button>
											<button type="button" class="btn btn-action btn-del" title="Delete" onclick="return confirm('Delete this vehicle?');"><i class="fas fa-trash-alt"></i></button>
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

	<!-- Create / Edit Vehicle modal -->
	<div class="modal fade" id="vehicleFormModal" tabindex="-1" role="dialog" aria-labelledby="vehicleFormModalLabel" aria-hidden="true" data-backdrop="static">
		<div class="modal-dialog modal-dialog-centered modal-lg vehicle-form-dialog" role="document">
			<div class="modal-content vehicle-form-shell">
				<form id="vehicleFormInner" action="#" method="post" enctype="multipart/form-data" onsubmit="return false;">
					<input type="hidden" name="id" id="vehicleFormId" value="">
					<div class="modal-header vehicle-form-hd text-white">
						<h5 class="modal-title mb-0" id="vehicleFormModalLabel">Create Vehicle</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					</div>
					<div class="modal-body vehicle-modal-bd">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label class="label-req" for="vehicleFormName">Vehicle Name</label>
									<input type="text" class="form-control" id="vehicleFormName" name="name" placeholder="Enter vehicle name" required>
								</div>
								<div class="form-group">
									<label class="label-req" for="vehicleFormType">Vehicle Type</label>
									<input type="text" class="form-control" id="vehicleFormType" name="type" placeholder="Enter vehicle type" required>
								</div>
								<div class="form-group mb-md-0">
									<label for="vehicleFormCapacity">Capacity (persons)</label>
									<input type="text" class="form-control" id="vehicleFormCapacity" name="capacity" placeholder="e.g. 5 persons">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="vehicleFormImage">Vehicle Image</label>
									<div class="custom-file">
										<input type="file" class="custom-file-input" id="vehicleFormImage" name="image" accept=".jpg,.jpeg,.png,.gif">
										<label class="custom-file-label" for="vehicleFormImage">Choose file</label>
									</div>
									<div class="text-help">Recommended: 800×600 px. Max 2MB. JPG, PNG, GIF.</div>
									<div class="text-disclaimer"><i class="fas fa-exclamation-triangle mr-1"></i>Use royalty-free or owned images only.</div>
									<div class="text-tip"><i class="fas fa-check-circle mr-1"></i>
										<a href="https://www.pexels.com" target="_blank" rel="noopener">Pexels</a> /
										<a href="https://pixabay.com" target="_blank" rel="noopener">Pixabay</a> /
										<a href="https://unsplash.com" target="_blank" rel="noopener">Unsplash</a>.
									</div>
								</div>
								<div class="form-group mb-0">
									<label for="vehicleFormDescription">Description</label>
									<textarea class="form-control" id="vehicleFormDescription" name="description" rows="5" placeholder="Enter vehicle description"></textarea>
								</div>
							</div>
						</div>
					</div>
					<div class="modal-footer vehicle-form-ft justify-content-start flex-wrap">
						<button type="submit" class="btn btn-vehicle-primary" id="vehicleFormSubmitBtn"><i class="fas fa-save mr-2"></i><span id="vehicleFormSubmitText">Create Vehicle</span></button>
						<button type="button" class="btn btn-vehicle-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- View Vehicle modal -->
	<div class="modal fade" id="vehicleViewModal" tabindex="-1" role="dialog" aria-labelledby="vehicleViewModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered vehicle-view-dialog" role="document">
			<div class="modal-content vehicle-view-shell">
				<div id="vehicleViewInner">
					<div class="modal-header vehicle-view-hd text-white">
						<h5 class="modal-title mb-0" id="vehicleViewModalLabel">View Vehicle</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					</div>
					<div class="modal-body vehicle-view-bd">
						<p class="font-weight-bold mb-2" style="font-size: 0.95rem; color: #222;">Vehicle details</p>
						<table class="table table-bordered vehicle-view-table">
							<tbody>
								<tr>
									<th scope="row">ID</th>
									<td id="vehicleViewId">—</td>
								</tr>
								<tr>
									<th scope="row">Image</th>
									<td id="vehicleViewImage">—</td>
								</tr>
								<tr>
									<th scope="row">Vehicle Name</th>
									<td id="vehicleViewName">—</td>
								</tr>
								<tr>
									<th scope="row">Type</th>
									<td id="vehicleViewType">—</td>
								</tr>
								<tr>
									<th scope="row">Capacity</th>
									<td id="vehicleViewCapacity">—</td>
								</tr>
								<tr>
									<th scope="row">Description</th>
									<td><div class="vehicle-view-desc" id="vehicleViewDesc">—</div></td>
								</tr>
								<tr>
									<th scope="row">Created By</th>
									<td id="vehicleViewCreatedBy">—</td>
								</tr>
								<tr>
									<th scope="row">Created At</th>
									<td id="vehicleViewCreated">—</td>
								</tr>
							</tbody>
						</table>
					</div>
					<div class="modal-footer vehicle-view-ft justify-content-between flex-wrap">
						<button type="button" class="btn btn-vehicle-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Close</button>
						<button type="button" class="btn btn-vehicle-primary" id="vehicleViewEditBtn"><i class="fas fa-edit mr-2"></i>Edit</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php include __DIR__ . '/../includes/footer-links.php'; ?>
</div>
<script>
$(function () {
	var vehicleById = <?= json_encode($vehiclesById, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

	var input = document.getElementById('vehicleSearch');
	var typeSel = document.getElementById('vehicleTypeFilter');
	var body = document.getElementById('vehicleTableBody');
	function filterVehicleTable() {
		if (!body) return;
		var q = (input && input.value || '').toLowerCase().trim();
		var t = typeSel && typeSel.value || '';
		var rows = body.querySelectorAll('.vehicle-row');
		rows.forEach(function (row) {
			var hay = row.getAttribute('data-search') || '';
			var ty = row.getAttribute('data-type') || '';
			var okQ = !q || hay.indexOf(q) !== -1;
			var okT = !t || ty === t;
			row.style.display = okQ && okT ? '' : 'none';
		});
	}
	if (input) input.addEventListener('input', filterVehicleTable);
	if (typeSel) typeSel.addEventListener('change', filterVehicleTable);

	function resetVehicleForm() {
		var f = document.getElementById('vehicleFormInner');
		if (f) f.reset();
		$('#vehicleFormId').val('');
		$('#vehicleFormModalLabel').text('Create Vehicle');
		$('#vehicleFormSubmitText').text('Create Vehicle');
		$('#vehicleFormImage').siblings('.custom-file-label').removeClass('selected').html('Choose file');
	}

	function openVehicleFormModal(mode, id) {
		resetVehicleForm();
		if (mode === 'edit' && id != null && vehicleById[id]) {
			var v = vehicleById[id];
			$('#vehicleFormId').val(v.id);
			$('#vehicleFormName').val(v.name || '');
			$('#vehicleFormType').val(v.type || '');
			$('#vehicleFormCapacity').val(v.capacity || '');
			$('#vehicleFormDescription').val(v.description || '');
			$('#vehicleFormModalLabel').text('Edit Vehicle');
			$('#vehicleFormSubmitText').text('Save Vehicle');
		} else {
			$('#vehicleFormModalLabel').text('Create Vehicle');
			$('#vehicleFormSubmitText').text('Create Vehicle');
		}
		$('#vehicleFormModal').modal('show');
	}

	function openVehicleViewModal(id) {
		var v = vehicleById[id];
		if (!v) return;
		$('#vehicleViewId').text(v.id);
		if (v.image && String(v.image).trim()) {
			$('#vehicleViewImage').text('Image URL on file (see list thumbnail)');
		} else {
			$('#vehicleViewImage').text('No image');
		}
		$('#vehicleViewName').text(v.name || '—');
		$('#vehicleViewType').text(v.type || '—');
		$('#vehicleViewCapacity').text(v.capacity || '—');
		$('#vehicleViewDesc').text(v.description || '—');
		$('#vehicleViewCreatedBy').text(v.created_by || '—');
		$('#vehicleViewCreated').text(v.created || '—');
		$('#vehicleViewEditBtn').data('id', id);
		$('#vehicleViewModal').modal('show');
	}

	$('#btnOpenVehicleFormAdd').on('click', function () {
		openVehicleFormModal('add', null);
	});

	$(document).on('click', '.btn-vehicle-view', function () {
		var id = parseInt($(this).data('id'), 10);
		openVehicleViewModal(id);
	});

	$(document).on('click', '.btn-vehicle-edit', function () {
		var id = parseInt($(this).data('id'), 10);
		openVehicleFormModal('edit', id);
	});

	$('#vehicleViewEditBtn').on('click', function () {
		var id = parseInt($(this).data('id'), 10);
		$('#vehicleViewModal').modal('hide');
		setTimeout(function () { openVehicleFormModal('edit', id); }, 250);
	});

	$('#vehicleFormInner').on('submit', function () {
		alert('Demo only: connect this form to your save endpoint.');
		$('#vehicleFormModal').modal('hide');
		return false;
	});

	$('#vehicleFormImage').on('change', function () {
		var fileName = $(this).val().split('\\').pop();
		$(this).siblings('.custom-file-label').addClass('selected').html(fileName || 'Choose file');
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
		openVehicleFormModal('add', null);
		stripOpenParams();
	} else if (open === 'edit' && !isNaN(qid) && vehicleById[qid]) {
		openVehicleFormModal('edit', qid);
		stripOpenParams();
	} else if (open === 'view' && !isNaN(qid) && vehicleById[qid]) {
		openVehicleViewModal(qid);
		stripOpenParams();
	}
});
</script>
</body>
</html>
