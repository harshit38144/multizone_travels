<?php
require_once __DIR__ . '/bootstrap.php';

$destinations = [
	['slug' => 'andaman', 'name' => 'Andaman Island', 'attractions' => 21, 'cities' => 3, 'city_tags' => ['Port Blair', 'Havelock', 'Neil Island']],
	['slug' => 'assam', 'name' => 'Assam', 'attractions' => 2, 'cities' => 1, 'city_tags' => ['Guwahati']],
	['slug' => 'azerbaijan', 'name' => 'Azerbaijan', 'attractions' => 6, 'cities' => 1, 'city_tags' => ['Baku']],
	['slug' => 'goa', 'name' => 'Goa', 'attractions' => 4, 'cities' => 1, 'city_tags' => ['Panaji']],
	['slug' => 'himachal', 'name' => 'Himachal', 'attractions' => 8, 'cities' => 3, 'city_tags' => ['Shimla', 'Manali', 'Dharamshala']],
	['slug' => 'kashmir', 'name' => 'Kashmir', 'attractions' => 13, 'cities' => 3, 'city_tags' => ['Srinagar', 'Gulmarg', 'Pahalgam']],
];

$allCities = [];
foreach ($destinations as $d) {
	foreach ($d['city_tags'] as $c) {
		$allCities[$c] = true;
	}
}
$cityList = array_keys($allCities);
sort($cityList);

$citiesByDest = [
	'andaman' => ['Port Blair', 'Havelock', 'Neil Island'],
	'assam' => ['Guwahati'],
	'azerbaijan' => ['Baku'],
	'goa' => ['Panaji'],
	'himachal' => ['Shimla', 'Manali', 'Dharamshala'],
	'kashmir' => ['Srinagar', 'Gulmarg', 'Pahalgam'],
];

$destinationOptions = [['slug' => '', 'label' => 'Select Destination']];
foreach ($destinations as $d) {
	$destinationOptions[] = ['slug' => $d['slug'], 'label' => $d['name']];
}

$sightseeingById = [
	101 => [
		'id' => 101,
		'destination_slug' => 'kashmir',
		'destination_name' => 'Kashmir',
		'city' => 'Gulmarg',
		'title' => 'Pahalgam – Gulmarg | approx 3:30 Hrs per way - 142 Km',
		'sequence' => 1,
		'estimated_hours' => '5.0',
		'start_time' => '08:00',
		'start_display' => '8:00 AM',
		'additional_notes' => 'Include meals, dress code, or seasonal closures if applicable.',
		'description_html' => '<p>Scenic drive with breakfast stop; ideal for day excursions.</p>',
		'description_plain' => 'Scenic drive with breakfast stop; ideal for day excursions.',
		'remarks_html' => '<p>Internal remarks for operations — optional.</p>',
		'created' => 'Apr 21, 2026',
		'created_by' => 'Vivek Giri',
	],
	102 => [
		'id' => 102,
		'destination_slug' => 'kashmir',
		'destination_name' => 'Kashmir',
		'city' => 'Gulmarg',
		'title' => 'Gondola Phase 1 & Snow Activities',
		'sequence' => 2,
		'estimated_hours' => '',
		'start_time' => '10:30',
		'start_display' => '10:30 AM',
		'additional_notes' => '',
		'description_html' => '<p>Cable car and snow activities — subject to weather.</p>',
		'description_plain' => 'Cable car and snow activities — subject to weather.',
		'remarks_html' => '',
		'created' => 'Apr 21, 2026',
		'created_by' => 'Vivek Giri',
	],
	103 => [
		'id' => 103,
		'destination_slug' => 'kashmir',
		'destination_name' => 'Kashmir',
		'city' => 'Pahalgam',
		'title' => 'Betaab Valley & Aru Valley excursion',
		'sequence' => 1,
		'estimated_hours' => '6.0',
		'start_time' => '09:00',
		'start_display' => '9:00 AM',
		'additional_notes' => '',
		'description_html' => '<p>Full-day valley sightseeing with packed lunch.</p>',
		'description_plain' => 'Full-day valley sightseeing with packed lunch.',
		'remarks_html' => '',
		'created' => 'Apr 21, 2026',
		'created_by' => 'Vivek Giri',
	],
];
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<base href="../">
	<title>Sightseeing Master</title>
	<?php include __DIR__ . '/../includes/header-links.php'; ?>
	<style>
		.crm-ss-master .content-wrapper > .content { background: #f4f7f6; }
		.crm-ss-master .page-intro {
			display: flex; justify-content: space-between; align-items: flex-start;
			flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;
		}
		.crm-ss-master .page-intro .titles { display: flex; align-items: flex-start; gap: 0.65rem; }
		.crm-ss-master .page-intro .titles > i { font-size: 1.75rem; color: #007bff; margin-top: 0.15rem; }
		.crm-ss-master .page-intro h1 {
			margin: 0; font-size: 1.75rem; font-weight: 700; color: #212529;
		}
		.crm-ss-master .page-intro .subtitle {
			font-size: 0.875rem; color: #6c757d; margin: 0.2rem 0 0;
		}
		.crm-ss-master .breadcrumbs { font-size: 0.875rem; align-self: center; }
		.crm-ss-master .breadcrumbs a { color: #007bff; }
		.crm-ss-master .breadcrumbs .bc-muted { color: #6c757d; }

		.crm-ss-master .filters-card {
			background: #fff; border: 1px solid #dee2e6; border-radius: 6px;
			box-shadow: 0 1px 2px rgba(0,0,0,.04); margin-bottom: 1rem; overflow: hidden;
		}
		.crm-ss-master .filters-head {
			display: flex; justify-content: space-between; align-items: center;
			flex-wrap: wrap; gap: 0.75rem; padding: 0.85rem 1.15rem;
			border-bottom: 1px solid #e9ecef;
		}
		.crm-ss-master .filters-head .fh-label {
			font-weight: 700; font-size: 0.95rem; color: #212529; margin: 0;
		}
		.crm-ss-master .filters-head .fh-label i { color: #6c757d; margin-right: 0.35rem; }
		.crm-ss-master .btn-add-main {
			background: #007bff; border: none; color: #fff; font-weight: 600;
			padding: 0.45rem 1rem; border-radius: 4px; white-space: nowrap;
		}
		.crm-ss-master .btn-add-main:hover { background: #0069d9; color: #fff; }
		a.btn-add-main, a.btn-add-main:hover { color: #fff !important; text-decoration: none; }

		.crm-ss-master .filters-toolbar {
			display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;
			padding: 1rem 1.15rem;
		}
		.crm-ss-master .search-wrap { position: relative; flex: 1; max-width: 320px; min-width: 180px; }
		.crm-ss-master .search-wrap input { padding-right: 2.5rem; }
		.crm-ss-master .search-wrap .btn-search-ico {
			position: absolute; right: 0; top: 0; bottom: 0;
			border: 1px solid #ced4da; border-left: none; border-radius: 0 4px 4px 0;
			background: #f8f9fa; color: #6c757d; padding: 0 0.75rem;
		}
		.crm-ss-master .toolbar-select { min-width: 180px; max-width: 220px; flex: 1; }

		.crm-ss-master .dest-list { display: flex; flex-direction: column; gap: 0; }
		.crm-ss-master .dest-row {
			background: #fff; border: 1px solid #dee2e6; border-radius: 6px;
			box-shadow: 0 1px 2px rgba(0,0,0,.03); margin-bottom: 0.65rem; overflow: hidden;
		}
		.crm-ss-master .dest-row.hidden-filter { display: none !important; }
		.crm-ss-master .dest-row-head {
			display: flex; align-items: center; flex-wrap: wrap; gap: 0.75rem;
			padding: 0.85rem 1rem;
			border-bottom: 1px solid transparent;
		}
		.crm-ss-master .dest-row-head.has-collapse.collapsed-here { border-bottom-color: #e9ecef; }
		.crm-ss-master .dest-toggle {
			width: 34px; height: 34px; padding: 0; border: 1px solid #ced4da; border-radius: 4px;
			background: #fff; color: #495057; cursor: pointer; flex-shrink: 0;
		}
		.crm-ss-master .dest-toggle:hover { background: #f8f9fa; }
		.crm-ss-master .dest-toggle:focus { outline: none; box-shadow: 0 0 0 2px rgba(0,123,255,.2); }
		.crm-ss-master .dest-route { color: #6c757d; font-size: 1.15rem; flex-shrink: 0; }
		.crm-ss-master .dest-name-wrap { flex: 1; min-width: 180px; }
		.crm-ss-master .dest-name {
			font-weight: 700; font-size: 1rem; color: #007bff; margin: 0; display: inline;
		}
		.crm-ss-master .dest-stats {
			font-size: 0.85rem; color: #6c757d; margin-left: 0.35rem;
		}
		.crm-ss-master .dest-actions {
			display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;
			margin-left: auto;
		}
		.crm-ss-master .btn-outline-add {
			background: #fff; border: 1px solid #007bff; color: #007bff;
			font-weight: 600; font-size: 0.85rem; padding: 0.35rem 0.75rem; border-radius: 4px;
		}
		.crm-ss-master .btn-outline-add:hover { background: #007bff; color: #fff; }
		.crm-ss-master .btn-view-dest {
			background: #fff; border: 1px solid #ced4da; color: #495057;
			font-weight: 600; font-size: 0.85rem; padding: 0.35rem 0.75rem; border-radius: 4px;
		}
		.crm-ss-master .btn-view-dest:hover { background: #f8f9fa; color: #212529; }

		.crm-ss-master .dest-collapse-in {
			padding: 0.75rem 1rem 1rem 3.1rem; background: #f8f9fa;
			font-size: 0.9rem; color: #6c757d; border-top: 1px solid #e9ecef;
		}
		.crm-ss-master .dest-collapse-in a { font-weight: 600; }

		/* Sightseeing form modal */
		#ssFormModal .ss-form-dialog {
			max-width: 1000px;
			width: calc(100% - 1.5rem);
			max-height: calc(100vh - 2rem);
			margin: 1rem auto;
			display: flex;
			align-items: stretch;
		}
		#ssFormModal .modal-content.ss-form-shell {
			border: none;
			border-radius: 12px;
			box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
			display: flex;
			flex-direction: column;
			max-height: calc(100vh - 2rem);
			overflow: hidden;
			min-height: 0;
		}
		#ssFormModal #ssFormInner {
			display: flex;
			flex-direction: column;
			flex: 1 1 auto;
			min-height: 0;
			max-height: 100%;
			overflow: hidden;
		}
		#ssFormModal .modal-header.ss-form-hd {
			flex: 0 0 auto;
			background: linear-gradient(125deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
			color: #fff;
			border: none;
			padding: 1rem 1.25rem;
		}
		#ssFormModal .modal-header.ss-form-hd .modal-title { font-weight: 800; font-size: 1.1rem; }
		#ssFormModal .modal-header.ss-form-hd .close { color: #fff; text-shadow: none; opacity: 0.9; }
		#ssFormModal .modal-body.ss-modal-bd {
			flex: 1 1 auto;
			min-height: 0;
			overflow-x: hidden;
			overflow-y: auto;
			-webkit-overflow-scrolling: touch;
			overscroll-behavior: contain;
			padding: 1rem 1.25rem;
			background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
		}
		#ssFormModal .ss-info-card {
			border: 1px solid #dee2e6;
			border-radius: 8px;
			overflow: hidden;
			background: #fff;
			margin-bottom: 0;
		}
		#ssFormModal .ss-panel-hd-blue {
			background: #007bff;
			color: #fff;
			font-weight: 700;
			padding: 0.55rem 1rem;
			font-size: 0.95rem;
			margin: 0;
		}
		#ssFormModal .ss-info-card-bd { padding: 1rem; }
		#ssFormModal .ss-modal-bd label { font-weight: 700; color: #212529; font-size: 0.85rem; margin-bottom: 0.35rem; }
		#ssFormModal .label-req::after { content: " *"; color: #dc3545; }
		#ssFormModal .text-help { font-size: 0.75rem; color: #6c757d; margin-top: 0.35rem; }
		#ssFormModal .text-disclaimer { font-size: 0.75rem; color: #dc3545; margin-top: 0.35rem; }
		#ssFormModal .text-tip { font-size: 0.75rem; color: #28a745; margin-top: 0.35rem; }
		#ssFormModal .modal-footer.ss-form-ft {
			flex: 0 0 auto;
			background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
			border-top: 1px solid #e2e8f0;
			padding: 0.85rem 1.25rem 1rem;
			gap: 0.5rem;
		}
		#ssFormModal .btn-ss-primary {
			background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
			border: none;
			color: #fff;
			font-weight: 700;
			padding: 0.5rem 1.2rem;
			border-radius: 8px;
			box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
		}
		#ssFormModal .btn-ss-primary:hover { color: #fff; transform: translateY(-1px); }
		#ssFormModal .btn-ss-ghost,
		#ssDestViewModal .btn-ss-ghost,
		#ssItemViewModal .btn-ss-ghost {
			background: #fff;
			border: 1px solid #e2e8f0;
			color: #475569;
			font-weight: 700;
			padding: 0.5rem 1.1rem;
			border-radius: 8px;
		}
		#ssFormModal .btn-ss-ghost:hover,
		#ssDestViewModal .btn-ss-ghost:hover,
		#ssItemViewModal .btn-ss-ghost:hover { background: #f8fafc; color: #334155; }
		#ssFormModal .note-editor.note-frame { border-color: #ced4da !important; border-radius: 8px !important; }
		#ssFormModal .note-modal,
		#ssFormModal .note-dropdown-menu,
		#ssFormModal .dropdown-menu.show { z-index: 1060 !important; }

		#ssDestViewModal .ss-dest-view-dialog,
		#ssItemViewModal .ss-item-view-dialog {
			max-width: 560px;
			width: calc(100% - 1.5rem);
			margin: 1rem auto;
		}
		#ssDestViewModal .modal-content.ss-dest-view-shell,
		#ssItemViewModal .modal-content.ss-item-view-shell {
			border: none;
			border-radius: 12px;
			overflow-x: hidden;
			overflow-y: auto;
			box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
			display: flex;
			flex-direction: column;
			max-height: calc(100vh - 2rem);
		}
		#ssDestViewModal #ssDestViewInner,
		#ssItemViewModal #ssItemViewInner {
			display: flex;
			flex-direction: column;
			flex: 0 0 auto;
			max-height: inherit;
		}
		#ssDestViewModal .modal-header.ss-dest-view-hd,
		#ssItemViewModal .modal-header.ss-item-view-hd {
			flex: 0 0 auto;
			background: linear-gradient(125deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
			color: #fff;
			border: none;
			padding: 1rem 1.25rem;
		}
		#ssDestViewModal .modal-header .modal-title,
		#ssItemViewModal .modal-header .modal-title { font-weight: 800; font-size: 1.1rem; }
		#ssDestViewModal .modal-header .close,
		#ssItemViewModal .modal-header .close { color: #fff; text-shadow: none; opacity: 0.9; }
		#ssDestViewModal .modal-body.ss-dest-view-bd,
		#ssItemViewModal .modal-body.ss-item-view-bd {
			flex: 0 0 auto;
			padding: 1rem 1.35rem;
			background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
		}
		#ssDestViewModal .ss-view-table,
		#ssItemViewModal .ss-view-table { margin-bottom: 0; font-size: 0.9rem; }
		#ssDestViewModal .ss-view-table th,
		#ssItemViewModal .ss-view-table th {
			width: 36%;
			background: #f8f9fa;
			font-weight: 700;
			color: #212529;
			vertical-align: top;
			padding: 0.55rem 0.75rem;
			border-color: #dee2e6;
		}
		#ssDestViewModal .ss-view-table td,
		#ssItemViewModal .ss-view-table td {
			vertical-align: top;
			padding: 0.55rem 0.75rem;
			border-color: #dee2e6;
			color: #334155;
		}
		#ssItemViewModal .ss-view-desc {
			max-height: 100px;
			overflow-y: auto;
			white-space: pre-wrap;
			word-break: break-word;
			font-size: 0.88rem;
			line-height: 1.45;
		}
		#ssDestViewModal .modal-footer.ss-dest-view-ft,
		#ssItemViewModal .modal-footer.ss-item-view-ft {
			flex: 0 0 auto;
			background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
			border-top: 1px solid #e2e8f0;
			padding: 0.85rem 1.25rem 1rem;
			gap: 0.5rem;
		}

		@media (max-height: 720px) {
			#ssFormModal .ss-form-dialog,
			#ssFormModal .modal-content.ss-form-shell {
				max-height: calc(100vh - 1rem);
			}
		}
	</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper crm-ss-master">

	<?php include __DIR__ . '/../includes/top-header.php'; ?>
	<?php include __DIR__ . '/../includes/sidebar.php'; ?>

	<div class="content-wrapper">
		<?php include __DIR__ . '/../includes/page-header.php'; ?>

		<section class="content">
			<div class="container-fluid">

				<div class="page-intro">
					<div class="titles">
						<i class="fas fa-map-marked-alt" aria-hidden="true"></i>
						<div>
							<h1>Sightseeing Master</h1>
							<p class="subtitle">Organized by destinations</p>
						</div>
					</div>
					<nav class="breadcrumbs">
						<a href="dashboard.php">Home</a> /
						<a href="crm/sightseeing_master.php">Masters</a> /
						<span class="bc-muted">Sightseeing</span>
					</nav>
				</div>

				<div class="filters-card">
					<div class="filters-head">
						<p class="fh-label"><i class="fas fa-filter"></i>Filters &amp; Actions</p>
						<button type="button" class="btn btn-add-main" id="btnOpenSsFormAdd"><i class="fas fa-plus mr-1"></i> Add New Sightseeing</button>
					</div>
					<div class="filters-toolbar">
						<div class="search-wrap">
							<input type="search" class="form-control" id="ssSearch" placeholder="Search sightseeing..." autocomplete="off">
							<button type="button" class="btn-search-ico" aria-label="Search"><i class="fas fa-search"></i></button>
						</div>
						<select class="form-control toolbar-select" id="ssDestFilter" aria-label="Destination">
							<option value="">All Destinations</option>
							<?php foreach ($destinations as $d) { ?>
								<option value="<?= htmlspecialchars($d['slug']) ?>"><?= htmlspecialchars($d['name']) ?></option>
							<?php } ?>
						</select>
						<select class="form-control toolbar-select" id="ssCityFilter" aria-label="City">
							<option value="">All Cities</option>
							<?php foreach ($cityList as $c) { ?>
								<option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
							<?php } ?>
						</select>
					</div>
				</div>

				<div class="dest-list" id="destList">
					<?php foreach ($destinations as $d) {
						$slug = $d['slug'];
						$citiesJson = htmlspecialchars(json_encode($d['city_tags']), ENT_QUOTES, 'UTF-8');
						$searchBlob = strtolower($d['name'] . ' ' . implode(' ', $d['city_tags']));
						?>
						<div class="dest-row"
							id="dest-row-<?= htmlspecialchars($slug) ?>"
							data-slug="<?= htmlspecialchars($slug) ?>"
							data-cities="<?= $citiesJson ?>"
							data-search="<?= htmlspecialchars($searchBlob) ?>">
							<div class="dest-row-head has-collapse collapsed-here">
								<button type="button" class="dest-toggle" data-toggle="collapse" data-target="#collapse-<?= htmlspecialchars($slug) ?>"
									aria-expanded="false" aria-controls="collapse-<?= htmlspecialchars($slug) ?>"
									title="Expand">
									<i class="fas fa-plus collapse-icon-plus"></i>
								</button>
								<i class="fas fa-route dest-route" aria-hidden="true"></i>
								<div class="dest-name-wrap">
									<span class="dest-name"><?= htmlspecialchars($d['name']) ?></span>
									<span class="dest-stats"><?= (int) $d['attractions'] ?> attractions (<?= (int) $d['cities'] ?> cities)</span>
								</div>
								<div class="dest-actions">
									<button type="button" class="btn btn-outline-add btn-ss-add-row" data-slug="<?= htmlspecialchars($slug) ?>"><i class="fas fa-plus mr-1"></i> Add Sightseeing</button>
									<button type="button" class="btn btn-view-dest btn-ss-view-dest"
										data-name="<?= htmlspecialchars($d['name']) ?>"
										data-attractions="<?= (int) $d['attractions'] ?>"
										data-citycount="<?= (int) $d['cities'] ?>"
										data-cities="<?= htmlspecialchars(implode(', ', $d['city_tags'])) ?>"
									><i class="fas fa-eye mr-1"></i> View Dest.</button>
								</div>
							</div>
							<div class="collapse" id="collapse-<?= htmlspecialchars($slug) ?>">
								<div class="dest-collapse-in">
									Cities include: <?= htmlspecialchars(implode(', ', $d['city_tags'])) ?>.
									Open the full list to manage sequences and details.
									<a href="crm/sightseeing_destination.php?slug=<?= urlencode($slug) ?>">Open destination</a>
								</div>
							</div>
						</div>
					<?php } ?>
				</div>

			</div>
		</section>
	</div>

	<!-- Create / Edit Sightseeing modal -->
	<div class="modal fade" id="ssFormModal" tabindex="-1" role="dialog" aria-labelledby="ssFormModalLabel" aria-hidden="true" data-backdrop="static">
		<div class="modal-dialog modal-dialog-centered modal-xl ss-form-dialog" role="document">
			<div class="modal-content ss-form-shell">
				<form id="ssFormInner" action="#" method="post" enctype="multipart/form-data" onsubmit="return false;">
					<input type="hidden" name="id" id="ssFormId" value="">
					<div class="modal-header ss-form-hd text-white">
						<h5 class="modal-title mb-0" id="ssFormModalLabel">Create Sightseeing</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					</div>
					<div class="modal-body ss-modal-bd">
						<div class="ss-info-card">
							<div class="ss-panel-hd-blue">Sightseeing Information</div>
							<div class="ss-info-card-bd">
								<div class="row">
									<div class="col-md-6">
										<div class="form-group">
											<label class="label-req" for="ssFormDestination">Destination</label>
											<select class="form-control" id="ssFormDestination" name="destination" required>
												<?php foreach ($destinationOptions as $opt) {
													if ($opt['slug'] === '') { ?>
														<option value=""><?= htmlspecialchars($opt['label']) ?></option>
													<?php } else { ?>
														<option value="<?= htmlspecialchars($opt['slug']) ?>"><?= htmlspecialchars($opt['label']) ?></option>
													<?php }
												} ?>
											</select>
										</div>
										<div class="form-group">
											<label class="label-req" for="ssFormCity">City</label>
											<select class="form-control" id="ssFormCity" name="city" required>
												<option value="">Select City</option>
											</select>
											<div class="text-help">Cities load from the selected destination</div>
										</div>
										<div class="form-group">
											<label class="label-req" for="ssFormTitle">Title</label>
											<input type="text" class="form-control" id="ssFormTitle" name="title" placeholder="Enter sightseeing title" required>
										</div>
										<div class="form-group">
											<label class="label-req" for="ssFormSequence">Sequence</label>
											<input type="number" class="form-control" id="ssFormSequence" name="sequence" min="1" step="1" value="1" required>
											<div class="text-help">Lower numbers appear first in itineraries</div>
										</div>
										<div class="form-group">
											<label for="ssFormHours">Estimated Hours</label>
											<input type="text" class="form-control" id="ssFormHours" name="estimated_hours" placeholder="e.g. 2.5">
										</div>
										<div class="form-group">
											<label for="ssFormStart">Suggested Start Time</label>
											<input type="time" class="form-control" id="ssFormStart" name="start_time" value="">
										</div>
										<div class="form-group mb-0">
											<label for="ssFormImage">Image</label>
											<div class="custom-file">
												<input type="file" class="custom-file-input" id="ssFormImage" name="image" accept=".jpg,.jpeg,.png,.gif">
												<label class="custom-file-label" for="ssFormImage">Choose file</label>
											</div>
											<div class="text-help">800×600 px recommended. Max 2MB.</div>
											<div class="text-disclaimer"><i class="fas fa-exclamation-triangle mr-1"></i>Royalty-free or owned images only.</div>
											<div class="text-tip"><i class="fas fa-check-circle mr-1"></i>
												<a href="https://www.pexels.com" target="_blank" rel="noopener">Pexels</a> /
												<a href="https://pixabay.com" target="_blank" rel="noopener">Pixabay</a> /
												<a href="https://unsplash.com" target="_blank" rel="noopener">Unsplash</a>.
											</div>
										</div>
									</div>
									<div class="col-md-6">
										<div class="form-group">
											<label for="ssFormDescription">Description</label>
											<textarea id="ssFormDescription" name="description" class="form-control summernote-ss-modal"></textarea>
										</div>
										<div class="form-group">
											<label for="ssFormRemarks">Remarks</label>
											<textarea id="ssFormRemarks" name="remarks" class="form-control summernote-ss-modal"></textarea>
										</div>
										<div class="form-group mb-0">
											<label for="ssFormNotes">Additional Notes</label>
											<textarea class="form-control" id="ssFormNotes" name="additional_notes" rows="5" placeholder="Optional"></textarea>
											<div class="text-help">Tips, dress code, seasonal notes</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="modal-footer ss-form-ft justify-content-start flex-wrap">
						<button type="submit" class="btn btn-ss-primary" id="ssFormSubmitBtn"><i class="fas fa-save mr-2"></i><span id="ssFormSubmitText">Create Sightseeing</span></button>
						<button type="button" class="btn btn-ss-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- View destination summary -->
	<div class="modal fade" id="ssDestViewModal" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered ss-dest-view-dialog" role="document">
			<div class="modal-content ss-dest-view-shell">
				<div id="ssDestViewInner">
					<div class="modal-header ss-dest-view-hd text-white">
						<h5 class="modal-title mb-0">View Destination</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					</div>
					<div class="modal-body ss-dest-view-bd">
						<p class="font-weight-bold mb-2" style="font-size: 0.95rem; color: #222;">Destination summary</p>
						<table class="table table-bordered ss-view-table">
							<tbody>
								<tr><th scope="row">Destination</th><td id="ssDestViewName">—</td></tr>
								<tr><th scope="row">Attractions</th><td id="ssDestViewAttractions">—</td></tr>
								<tr><th scope="row">Cities</th><td id="ssDestViewCityCount">—</td></tr>
								<tr><th scope="row">City list</th><td id="ssDestViewCities">—</td></tr>
							</tbody>
						</table>
					</div>
					<div class="modal-footer ss-dest-view-ft justify-content-start flex-wrap">
						<button type="button" class="btn btn-ss-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Close</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- View sightseeing item (deep link / future list) -->
	<div class="modal fade" id="ssItemViewModal" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-lg ss-item-view-dialog" role="document">
			<div class="modal-content ss-item-view-shell">
				<div id="ssItemViewInner">
					<div class="modal-header ss-item-view-hd text-white">
						<h5 class="modal-title mb-0">View Sightseeing</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					</div>
					<div class="modal-body ss-item-view-bd">
						<p class="font-weight-bold mb-2" style="font-size: 0.95rem; color: #222;">Sightseeing details</p>
						<table class="table table-bordered ss-view-table">
							<tbody>
								<tr><th scope="row">ID</th><td id="ssItemViewId">—</td></tr>
								<tr><th scope="row">Title</th><td id="ssItemViewTitle">—</td></tr>
								<tr><th scope="row">Destination</th><td id="ssItemViewDestination">—</td></tr>
								<tr><th scope="row">City</th><td id="ssItemViewCity">—</td></tr>
								<tr><th scope="row">Sequence</th><td id="ssItemViewSequence">—</td></tr>
								<tr><th scope="row">Estimated hours</th><td id="ssItemViewHours">—</td></tr>
								<tr><th scope="row">Start time</th><td id="ssItemViewStart">—</td></tr>
								<tr><th scope="row">Description</th><td><div class="ss-view-desc" id="ssItemViewDesc">—</div></td></tr>
								<tr><th scope="row">Additional notes</th><td id="ssItemViewNotes">—</td></tr>
								<tr><th scope="row">Created by</th><td id="ssItemViewCreatedBy">—</td></tr>
								<tr><th scope="row">Created</th><td id="ssItemViewCreated">—</td></tr>
							</tbody>
						</table>
					</div>
					<div class="modal-footer ss-item-view-ft justify-content-between flex-wrap">
						<button type="button" class="btn btn-ss-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Close</button>
						<button type="button" class="btn btn-ss-primary" id="ssItemViewEditBtn"><i class="fas fa-edit mr-2"></i>Edit</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php include __DIR__ . '/../includes/footer-links.php'; ?>
</div>
<script>
$(function () {
	var citiesByDest = <?= json_encode($citiesByDest, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
	var sightseeingById = <?= json_encode($sightseeingById, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

	var search = document.getElementById('ssSearch');
	var destF = document.getElementById('ssDestFilter');
	var cityF = document.getElementById('ssCityFilter');
	var rows = document.querySelectorAll('.dest-row');

	function applyFilters() {
		var q = (search && search.value || '').toLowerCase().trim();
		var ds = destF && destF.value || '';
		var ct = cityF && cityF.value || '';

		rows.forEach(function (row) {
			var slug = row.getAttribute('data-slug') || '';
			var hay = row.getAttribute('data-search') || '';
			var cities = [];
			try {
				cities = JSON.parse(row.getAttribute('data-cities') || '[]');
			} catch (e) {}

			var okQ = !q || hay.indexOf(q) !== -1;
			var okD = !ds || slug === ds;
			var okC = !ct || cities.indexOf(ct) !== -1;
			row.classList.toggle('hidden-filter', !(okQ && okD && okC));
		});
	}

	if (search) search.addEventListener('input', applyFilters);
	if (destF) destF.addEventListener('change', applyFilters);
	if (cityF) cityF.addEventListener('change', applyFilters);

	$('.collapse').on('show.bs.collapse', function () {
		var id = $(this).attr('id');
		var btn = $('[data-target="#' + id + '"]');
		btn.find('.collapse-icon-plus').removeClass('fa-plus').addClass('fa-minus');
		btn.attr('aria-expanded', 'true');
	});
	$('.collapse').on('hide.bs.collapse', function () {
		var id = $(this).attr('id');
		var btn = $('[data-target="#' + id + '"]');
		btn.find('.collapse-icon-plus').removeClass('fa-minus').addClass('fa-plus');
		btn.attr('aria-expanded', 'false');
	});

	function refreshSsFormCityOptions() {
		var dest = document.getElementById('ssFormDestination').value;
		var sel = document.getElementById('ssFormCity');
		var cities = citiesByDest[dest] || [];
		sel.innerHTML = '<option value="">Select City</option>';
		cities.forEach(function (c) {
			var o = document.createElement('option');
			o.value = c;
			o.textContent = c;
			sel.appendChild(o);
		});
	}

	function destroySsSummernotes() {
		['#ssFormDescription', '#ssFormRemarks'].forEach(function (sel) {
			var $ta = $(sel);
			if ($ta.length && $ta.next('.note-editor').length) {
				$ta.summernote('destroy');
			}
		});
	}

	function initSsSummernotes() {
		['#ssFormDescription', '#ssFormRemarks'].forEach(function (sel) {
			var $ta = $(sel);
			if (!$ta.length || $ta.next('.note-editor').length) return;
			$ta.summernote({
				height: 180,
				dialogsInBody: true,
				toolbar: [
					['style', ['style']],
					['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
					['fontname', ['fontname']],
					['color', ['color']],
					['para', ['ul', 'ol', 'paragraph']],
					['insert', ['link']],
					['view', ['fullscreen', 'codeview']]
				]
			});
		});
	}

	function resetSsForm() {
		var f = document.getElementById('ssFormInner');
		if (f) f.reset();
		$('#ssFormId').val('');
		$('#ssFormSequence').val('1');
		$('#ssFormModalLabel').text('Create Sightseeing');
		$('#ssFormSubmitText').text('Create Sightseeing');
		$('#ssFormImage').siblings('.custom-file-label').removeClass('selected').html('Choose file');
		$('#ssFormDestination').prop('selectedIndex', 0);
		refreshSsFormCityOptions();
		$('#ssFormModal').removeData('pendingSn');
	}

	function openSsFormModal(mode, opts) {
		opts = opts || {};
		var $modal = $('#ssFormModal');
		resetSsForm();
		if (mode === 'edit' && opts.editId != null && sightseeingById[opts.editId]) {
			var v = sightseeingById[opts.editId];
			$('#ssFormId').val(v.id);
			$('#ssFormDestination').val(v.destination_slug || '');
			refreshSsFormCityOptions();
			$('#ssFormCity').val(v.city || '');
			$('#ssFormTitle').val(v.title || '');
			$('#ssFormSequence').val(v.sequence != null ? v.sequence : 1);
			$('#ssFormHours').val(v.estimated_hours || '');
			$('#ssFormStart').val(v.start_time || '');
			$('#ssFormNotes').val(v.additional_notes || '');
			$modal.data('pendingSn', {
				desc: v.description_html || '',
				remarks: v.remarks_html || ''
			});
			$('#ssFormModalLabel').text('Edit Sightseeing');
			$('#ssFormSubmitText').text('Save Sightseeing');
		} else {
			if (opts.destinationSlug && $('#ssFormDestination option[value="' + opts.destinationSlug + '"]').length) {
				$('#ssFormDestination').val(opts.destinationSlug);
				refreshSsFormCityOptions();
			}
			$modal.data('pendingSn', { desc: '', remarks: '' });
		}
		$modal.modal('show');
	}

	function openSsDestViewModal($btn) {
		$('#ssDestViewName').text($btn.data('name') || '—');
		var n = parseInt($btn.data('attractions'), 10);
		$('#ssDestViewAttractions').text(!isNaN(n) ? String(n) : '—');
		var c = parseInt($btn.data('citycount'), 10);
		$('#ssDestViewCityCount').text(!isNaN(c) ? (c === 1 ? '1 city' : c + ' cities') : '—');
		$('#ssDestViewCities').text(($btn.attr('data-cities') || '').trim() || '—');
		$('#ssDestViewModal').modal('show');
	}

	function openSsItemViewModal(id) {
		var v = sightseeingById[id];
		if (!v) return;
		$('#ssItemViewId').text(v.id);
		$('#ssItemViewTitle').text(v.title || '—');
		$('#ssItemViewDestination').text(v.destination_name || '—');
		$('#ssItemViewCity').text(v.city || '—');
		$('#ssItemViewSequence').text(v.sequence != null ? String(v.sequence) : '—');
		$('#ssItemViewHours').text(v.estimated_hours != null && String(v.estimated_hours).trim() !== '' ? v.estimated_hours : '—');
		$('#ssItemViewStart').text(v.start_display || v.start_time || '—');
		$('#ssItemViewDesc').text(v.description_plain || '—');
		$('#ssItemViewNotes').text(v.additional_notes && String(v.additional_notes).trim() ? v.additional_notes : '—');
		$('#ssItemViewCreatedBy').text(v.created_by || '—');
		$('#ssItemViewCreated').text(v.created || '—');
		$('#ssItemViewEditBtn').data('id', id);
		$('#ssItemViewModal').modal('show');
	}

	$('#ssFormModal').on('shown.bs.modal', function () {
		initSsSummernotes();
		var p = $(this).data('pendingSn') || {};
		if ($('#ssFormDescription').next('.note-editor').length) {
			$('#ssFormDescription').summernote('code', p.desc || '');
		}
		if ($('#ssFormRemarks').next('.note-editor').length) {
			$('#ssFormRemarks').summernote('code', p.remarks || '');
		}
	});

	$('#ssFormModal').on('hidden.bs.modal', function () {
		destroySsSummernotes();
	});

	$('#ssFormDestination').on('change', refreshSsFormCityOptions);

	$('#btnOpenSsFormAdd').on('click', function () {
		openSsFormModal('add', {});
	});

	$(document).on('click', '.btn-ss-add-row', function () {
		var slug = $(this).data('slug');
		openSsFormModal('add', { destinationSlug: typeof slug === 'string' ? slug : '' });
	});

	$(document).on('click', '.btn-ss-view-dest', function () {
		openSsDestViewModal($(this));
	});

	$('#ssFormInner').on('submit', function () {
		alert('Demo only: connect this form to your save endpoint.');
		$('#ssFormModal').modal('hide');
		return false;
	});

	$('#ssFormImage').on('change', function () {
		var fileName = $(this).val().split('\\').pop();
		$(this).siblings('.custom-file-label').addClass('selected').html(fileName || 'Choose file');
	});

	$('#ssItemViewEditBtn').on('click', function () {
		var id = parseInt($(this).data('id'), 10);
		$('#ssItemViewModal').modal('hide');
		setTimeout(function () { openSsFormModal('edit', { editId: id }); }, 250);
	});

	function stripSsOpenParams() {
		if (!window.history || !window.history.replaceState) return;
		var u = new URL(window.location.href);
		u.searchParams.delete('open');
		u.searchParams.delete('id');
		u.searchParams.delete('destination');
		window.history.replaceState({}, '', u.pathname + (u.searchParams.toString() ? '?' + u.searchParams.toString() : ''));
	}

	var q = new URLSearchParams(window.location.search);
	var open = q.get('open');
	var qid = parseInt(q.get('id'), 10);
	var destQ = q.get('destination');
	if (open === 'create') {
		openSsFormModal('add', { destinationSlug: destQ || '' });
		stripSsOpenParams();
	} else if (open === 'edit' && !isNaN(qid) && sightseeingById[qid]) {
		openSsFormModal('edit', { editId: qid });
		stripSsOpenParams();
	} else if (open === 'view' && !isNaN(qid) && sightseeingById[qid]) {
		openSsItemViewModal(qid);
		stripSsOpenParams();
	}
});
</script>
</body>
</html>
