<?php
require_once __DIR__ . '/bootstrap.php';

$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9_-]/i', '', $_GET['slug']) : 'kashmir';
if ($slug === '') {
	$slug = 'kashmir';
}

$destinationsMeta = [
	'kashmir' => ['name' => 'Kashmir', 'attractions' => 13, 'cities' => 3],
	'andaman' => ['name' => 'Andaman Island', 'attractions' => 21, 'cities' => 3],
	'assam' => ['name' => 'Assam', 'attractions' => 2, 'cities' => 1],
	'azerbaijan' => ['name' => 'Azerbaijan', 'attractions' => 6, 'cities' => 1],
	'goa' => ['name' => 'Goa', 'attractions' => 4, 'cities' => 1],
	'himachal' => ['name' => 'Himachal', 'attractions' => 8, 'cities' => 3],
];

$kashmirGroups = [
	'Gulmarg' => [
		[
			'id' => 101,
			'title' => 'Pahalgam – Gulmarg | approx 3:30 Hrs per way - 142 Km',
			'snippet' => '• Breakfast at Pahalgam Hotel, scenic drive via valleys and pine forests toward Gulmarg.',
			'city' => 'Gulmarg',
			'sequence' => 1,
			'duration' => '5.0',
			'start' => '8:00 AM',
		],
		[
			'id' => 102,
			'title' => 'Gondola Phase 1 & Snow Activities',
			'snippet' => '• Cable car ride and leisure time on the meadows — duration as per weather.',
			'city' => 'Gulmarg',
			'sequence' => 2,
			'duration' => null,
			'start' => '10:30 AM',
		],
	],
	'Pahalgam' => [
		[
			'id' => 103,
			'title' => 'Betaab Valley & Aru Valley excursion',
			'snippet' => '• Full-day sightseeing with packed lunch — riverside walks and photography stops.',
			'city' => 'Pahalgam',
			'sequence' => 1,
			'duration' => '6.0',
			'start' => '9:00 AM',
		],
	],
];

$meta = $destinationsMeta[$slug] ?? ['name' => ucfirst($slug), 'attractions' => 0, 'cities' => 0];
$groups = ($slug === 'kashmir') ? $kashmirGroups : [];
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<base href="../">
	<title>Sightseeing — <?= htmlspecialchars($meta['name']) ?></title>
	<?php include __DIR__ . '/../includes/header-links.php'; ?>
	<style>
		.crm-ss-dest .content-wrapper > .content { background: #f4f7f6; }
		.crm-ss-dest .top-bar {
			display: flex; justify-content: space-between; align-items: center;
			flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;
			padding: 0.85rem 1rem; background: #fff; border: 1px solid #dee2e6;
			border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,.04);
		}
		.crm-ss-dest .top-bar .tb-left {
			display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;
		}
		.crm-ss-dest .top-bar .tb-left i { color: #007bff; font-size: 1.35rem; }
		.crm-ss-dest .top-bar .tb-title {
			font-weight: 700; font-size: 1.15rem; color: #007bff; margin: 0;
		}
		.crm-ss-dest .top-bar .tb-stats { font-size: 0.9rem; color: #6c757d; margin-left: 0.25rem; }
		.crm-ss-dest .top-bar .tb-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
		.crm-ss-dest .btn-add-solid {
			background: #007bff; border: none; color: #fff; font-weight: 600;
			padding: 0.4rem 0.85rem; border-radius: 4px; font-size: 0.875rem;
		}
		.crm-ss-dest .btn-add-solid:hover { background: #0069d9; color: #fff; }
		.crm-ss-dest .btn-view-light {
			background: #f8f9fa; border: 1px solid #ced4da; color: #495057;
			font-weight: 600; padding: 0.4rem 0.85rem; border-radius: 4px; font-size: 0.875rem;
		}
		.crm-ss-dest .btn-view-light:hover { background: #e9ecef; color: #212529; }

		.crm-ss-dest .table-card {
			background: #fff; border: 1px solid #dee2e6; border-radius: 6px;
			overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,.04);
		}
		.crm-ss-dest .ss-table { margin: 0; font-size: 0.875rem; }
		.crm-ss-dest .ss-table thead th {
			background: #f1f3f5; font-weight: 700; color: #212529;
			padding: 0.55rem 0.65rem; border-bottom: 2px solid #dee2e6;
			white-space: nowrap; vertical-align: middle;
		}
		.crm-ss-dest .ss-table tbody td {
			padding: 0.55rem 0.65rem; vertical-align: middle;
			border-color: #e9ecef;
		}
		.crm-ss-dest .thumb-ph {
			width: 44px; height: 44px; background: #e9ecef; border-radius: 4px;
			display: inline-flex; align-items: center; justify-content: center;
			color: #6c757d;
		}
		.crm-ss-dest .title-main {
			font-weight: 700; color: #007bff; font-size: 0.9rem; line-height: 1.35;
			display: block;
		}
		.crm-ss-dest .title-snippet {
			font-size: 0.78rem; color: #6c757d; margin-top: 0.25rem; line-height: 1.35;
		}
		.crm-ss-dest .badge-city {
			background: #e9ecef; color: #495057; font-weight: 600;
			font-size: 0.72rem; padding: 0.25rem 0.55rem; border-radius: 1rem;
		}
		.crm-ss-dest .badge-seq {
			background: #007bff; color: #fff; font-weight: 700;
			min-width: 26px; height: 26px; display: inline-flex;
			align-items: center; justify-content: center;
			border-radius: 4px; font-size: 0.75rem;
		}
		.crm-ss-dest .badge-dur {
			background: #28a745; color: #fff; font-size: 0.72rem;
			font-weight: 600; padding: 0.2rem 0.45rem; border-radius: 1rem;
		}
		.crm-ss-dest .start-cell { color: #007bff; font-size: 0.85rem; white-space: nowrap; }
		.crm-ss-dest .start-cell i { margin-right: 0.25rem; }
		.crm-ss-dest .action-btns .btn {
			width: 28px; height: 28px; padding: 0; line-height: 1;
			display: inline-flex; align-items: center; justify-content: center;
			margin-right: 3px;
		}
		.crm-ss-dest .city-group-bar {
			background: #f8f9fa; border-top: 1px solid #dee2e6;
			padding: 0.5rem 0.75rem; display: flex; align-items: center;
			justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;
		}
		.crm-ss-dest .city-group-bar .cg-left {
			display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
		}
		.crm-ss-dest .city-group-bar .cg-name {
			font-weight: 700; color: #212529; margin: 0;
		}
		.crm-ss-dest .badge-count {
			background: #007bff; color: #fff; font-size: 0.7rem;
			min-width: 22px; height: 22px; border-radius: 50%;
			display: inline-flex; align-items: center; justify-content: center;
			font-weight: 700;
		}
		.crm-ss-dest .btn-add-city {
			background: #007bff; border: none; color: #fff; font-weight: 600;
			font-size: 0.75rem; padding: 0.25rem 0.55rem; border-radius: 4px;
		}
		.crm-ss-dest .btn-add-city:hover { color: #fff; background: #0069d9; }
		.crm-ss-dest .empty-msg {
			padding: 2rem 1rem; text-align: center; color: #6c757d;
			background: #fff; border: 1px dashed #ced4da; border-radius: 6px;
		}
		.crm-ss-dest .breadcrumb-mini { font-size: 0.85rem; margin-bottom: 1rem; }
		.crm-ss-dest .breadcrumb-mini a { color: #007bff; }
	</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper crm-ss-dest">

	<?php include __DIR__ . '/../includes/top-header.php'; ?>
	<?php include __DIR__ . '/../includes/sidebar.php'; ?>

	<div class="content-wrapper">
		<?php include __DIR__ . '/../includes/page-header.php'; ?>

		<section class="content">
			<div class="container-fluid">

				<nav class="breadcrumb-mini">
					<a href="crm/sightseeing_master.php">Sightseeing Master</a>
					<span class="text-muted"> / </span>
					<span class="text-muted"><?= htmlspecialchars($meta['name']) ?></span>
				</nav>

				<div class="top-bar">
					<div class="tb-left">
						<i class="fas fa-route" aria-hidden="true"></i>
						<h2 class="tb-title"><?= htmlspecialchars($meta['name']) ?></h2>
						<span class="tb-stats"><?= (int) $meta['attractions'] ?> attractions (<?= (int) $meta['cities'] ?> cities)</span>
					</div>
					<div class="tb-actions">
						<a href="crm/sightseeing_create.php?destination=<?= urlencode($slug) ?>" class="btn btn-add-solid"><i class="fas fa-plus mr-1"></i> Add Sightseeing</a>
						<a href="destinations.php" class="btn btn-view-light"><i class="fas fa-eye mr-1"></i> View Dest.</a>
						<a href="crm/sightseeing_master.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Back</a>
					</div>
				</div>

				<?php if (empty($groups)) { ?>
					<div class="empty-msg">
						No sightseeing items are set up for this destination yet.
						<a href="crm/sightseeing_create.php?destination=<?= urlencode($slug) ?>">Add sightseeing</a>
					</div>
				<?php } else { ?>
					<div class="table-card">
						<table class="table table-bordered ss-table mb-0">
							<thead>
								<tr>
									<th style="width:72px;">IMAGE</th>
									<th>TITLE</th>
									<th style="width:100px;">CITY</th>
									<th style="width:90px;">SEQUENCE</th>
									<th style="width:100px;">DURATION</th>
									<th style="width:110px;">START TIME</th>
									<th style="width:190px;">ACTIONS</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($groups as $cityName => $items) {
									$n = count($items);
									?>
									<tr class="table-active">
										<td colspan="7" class="p-0">
											<div class="city-group-bar">
												<div class="cg-left">
													<i class="fas fa-city text-muted"></i>
													<span class="cg-name"><?= htmlspecialchars($cityName) ?></span>
													<span class="badge-count"><?= (int) $n ?></span>
												</div>
												<button type="button" class="btn btn-add-city"><i class="fas fa-plus mr-1"></i> Add Sightseeing</button>
											</div>
										</td>
									</tr>
									<?php foreach ($items as $item) { ?>
										<tr>
											<td><span class="thumb-ph"><i class="fas fa-image"></i></span></td>
											<td>
												<span class="title-main"><?= htmlspecialchars($item['title']) ?></span>
												<div class="title-snippet"><?= htmlspecialchars($item['snippet']) ?></div>
											</td>
											<td><span class="badge-city"><?= htmlspecialchars($item['city']) ?></span></td>
											<td><span class="badge-seq"><?= (int) $item['sequence'] ?></span></td>
											<td>
												<?php if ($item['duration'] !== null && $item['duration'] !== '') { ?>
													<span class="badge-dur"><i class="far fa-clock mr-1"></i><?= htmlspecialchars($item['duration']) ?>h</span>
												<?php } else { ?>
													<span class="text-muted">—</span>
												<?php } ?>
											</td>
											<td class="start-cell"><i class="far fa-clock"></i><?= htmlspecialchars($item['start']) ?></td>
											<td>
												<div class="action-btns">
													<button type="button" class="btn btn-outline-secondary btn-sm" title="Move up"><i class="fas fa-arrow-up"></i></button>
													<button type="button" class="btn btn-outline-secondary btn-sm" title="Move down"><i class="fas fa-arrow-down"></i></button>
													<a href="crm/sightseeing_view.php?id=<?= (int) $item['id'] ?>" class="btn btn-outline-primary btn-sm" title="View"><i class="fas fa-eye"></i></a>
													<a href="crm/sightseeing_create.php?edit=<?= (int) $item['id'] ?>" class="btn btn-outline-primary btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
													<button type="button" class="btn btn-outline-danger btn-sm" title="Delete" onclick="return confirm('Delete this item?');"><i class="fas fa-trash-alt"></i></button>
												</div>
											</td>
										</tr>
									<?php } ?>
								<?php } ?>
							</tbody>
						</table>
					</div>
				<?php } ?>

			</div>
		</section>
	</div>

	<?php include __DIR__ . '/../includes/footer-links.php'; ?>
</div>
</body>
</html>
