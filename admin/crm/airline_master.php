<?php
require_once __DIR__ . '/bootstrap.php';

$airlines = [
    ['id' => 75, 'name' => 'Air Asia', 'created' => 'Apr 21, 2026'],
    ['id' => 77, 'name' => 'Air India', 'created' => 'Apr 21, 2026'],
    ['id' => 79, 'name' => 'Air India Express', 'created' => 'Apr 20, 2026'],
    ['id' => 81, 'name' => 'Azerbaijan Airlines', 'created' => 'Apr 20, 2026'],
    ['id' => 83, 'name' => 'Emirates', 'created' => 'Apr 19, 2026'],
    ['id' => 85, 'name' => 'Etihad Airways', 'created' => 'Apr 19, 2026'],
    ['id' => 87, 'name' => 'Indigo', 'created' => 'Apr 18, 2026'],
    ['id' => 89, 'name' => 'Singapore Airline', 'created' => 'Apr 18, 2026'],
];
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Airline Master</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        .crm-airline-master .content-wrapper>.content {
            background: #f4f6f9;
        }

        .crm-airline-master .page-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .crm-airline-master .page-title {
            font-size: 1.85rem;
            font-weight: 700;
            color: #212529;
            margin: 0;
        }

        .crm-airline-master .breadcrumbs {
            font-size: 0.875rem;
        }

        .crm-airline-master .breadcrumbs a {
            color: #007bff;
        }

        .crm-airline-master .breadcrumbs .bc-muted {
            color: #6c757d;
        }

        .crm-airline-master .master-card {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
            overflow: hidden;
        }

        .crm-airline-master .master-card-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e9ecef;
        }

        .crm-airline-master .master-card-head h2 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
            color: #222;
        }

        .crm-airline-master .btn-add {
            background: #007bff;
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 0.45rem 1rem;
            border-radius: 4px;
            white-space: nowrap;
        }

        .crm-airline-master .btn-add:hover {
            color: #fff;
            background: #0069d9;
        }

        a.btn-add,
        a.btn-add:hover {
            color: #fff !important;
            text-decoration: none;
        }

        .crm-airline-master .search-row {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e9ecef;
        }

        .crm-airline-master .search-row .input-group {
            max-width: 360px;
        }

        .crm-airline-master .search-row .btn-search {
            background: #6c757d;
            color: #fff;
            border: 1px solid #6c757d;
        }

        .crm-airline-master .search-row .btn-search:hover {
            background: #5a6268;
            color: #fff;
        }

        .crm-airline-master .table-wrap {
            overflow-x: auto;
        }

        .crm-airline-master table.airline-table {
            width: 100%;
            margin: 0;
            font-size: 0.875rem;
            border-collapse: collapse;
        }

        .crm-airline-master table.airline-table thead th {
            background: #f8f9fa;
            color: #212529;
            font-weight: 700;
            padding: 0.65rem 1rem;
            border: 1px solid #dee2e6;
            white-space: nowrap;
            vertical-align: middle;
        }

        .crm-airline-master table.airline-table tbody td {
            padding: 0.65rem 1rem;
            vertical-align: middle;
            border: 1px solid #dee2e6;
        }

        .crm-airline-master table.airline-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        .crm-airline-master table.airline-table tbody tr:nth-child(odd) {
            background: #fff;
        }

        .crm-airline-master .logo-placeholder {
            display: inline-block;
            background: #adb5bd;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.35rem 0.5rem;
            border-radius: 6px;
            white-space: nowrap;
        }

        .crm-airline-master table.airline-table tbody td:last-child {
            text-align: left;
            white-space: nowrap;
        }

        .crm-airline-master .action-btns {
            display: inline-flex;
            gap: 5px;
        }

        .crm-airline-master .action-btns .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            border: none;
            color: #fff !important;
        }

        .crm-airline-master .action-btns .btn-view {
            background: #20c997;
        }

        .crm-airline-master .action-btns .btn-edit {
            background: #007bff;
        }

        .crm-airline-master .action-btns .btn-del {
            background: #dc3545;
        }

        /* Airline form modal */
        #airlineFormModal .airline-modal-dialog {
            max-width: 520px;
            width: calc(100% - 1.5rem);
            margin: 1rem auto;
        }
        #airlineFormModal .modal-content.airline-modal-shell {
            border: none;
            border-radius: 12px;
            overflow-x: hidden;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 2rem);
        }
        #airlineFormModal #airlineFormInner {
            display: flex;
            flex-direction: column;
            flex: 0 0 auto;
            max-height: inherit;
        }
        #airlineFormModal .modal-header.airline-modal-hd {
            flex: 0 0 auto;
            background: linear-gradient(125deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
            color: #fff;
            border: none;
            padding: 1rem 1.25rem;
        }
        #airlineFormModal .modal-header.airline-modal-hd .modal-title {
            font-weight: 800;
            font-size: 1.1rem;
        }
        #airlineFormModal .modal-header.airline-modal-hd .close {
            color: #fff;
            text-shadow: none;
            opacity: 0.9;
        }
        #airlineFormModal .modal-body.airline-modal-bd {
            flex: 0 0 auto;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding: 1rem 1.35rem;
            background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
        }
        #airlineFormModal .modal-body label {
            font-weight: 700;
            color: #334155;
            font-size: 0.85rem;
            margin-bottom: 0.35rem;
        }
        #airlineFormModal .label-req::after {
            content: " *";
            color: #dc3545;
        }
        #airlineFormModal .modal-body .form-control {
            border-radius: 8px;
            border-color: #e2e8f0;
        }
        #airlineFormModal .modal-body .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }
        #airlineFormModal .text-help {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.35rem;
        }
        #airlineFormModal .text-disclaimer {
            font-size: 0.75rem;
            color: #dc3545;
            margin-top: 0.35rem;
        }
        #airlineFormModal .text-tip {
            font-size: 0.75rem;
            color: #28a745;
            margin-top: 0.35rem;
        }
        #airlineFormModal .modal-footer.airline-modal-ft {
            flex: 0 0 auto;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-top: 1px solid #e2e8f0;
            padding: 0.85rem 1.25rem 1rem;
            gap: 0.5rem;
        }
        #airlineFormModal .btn-airline-primary {
            background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
            border: none;
            color: #fff;
            font-weight: 700;
            padding: 0.5rem 1.2rem;
            border-radius: 8px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
        }
        #airlineFormModal .btn-airline-primary:hover {
            color: #fff;
            transform: translateY(-1px);
        }
        #airlineFormModal .btn-airline-ghost,
        #airlineViewModal .btn-airline-ghost {
            background: #fff;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-weight: 700;
            padding: 0.5rem 1.1rem;
            border-radius: 8px;
        }
        #airlineFormModal .btn-airline-ghost:hover,
        #airlineViewModal .btn-airline-ghost:hover {
            background: #f8fafc;
            color: #334155;
        }

        /* View airline modal */
        #airlineViewModal .airline-view-dialog {
            max-width: 520px;
            width: calc(100% - 1.5rem);
            margin: 1rem auto;
        }
        #airlineViewModal .modal-content.airline-view-shell {
            border: none;
            border-radius: 12px;
            overflow-x: hidden;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.18);
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 2rem);
        }
        #airlineViewModal #airlineViewInner {
            display: flex;
            flex-direction: column;
            flex: 0 0 auto;
            max-height: inherit;
        }
        #airlineViewModal .modal-header.airline-view-hd {
            flex: 0 0 auto;
            background: linear-gradient(125deg, #1e40af 0%, #2563eb 55%, #3b82f6 100%);
            color: #fff;
            border: none;
            padding: 1rem 1.25rem;
        }
        #airlineViewModal .modal-header.airline-view-hd .modal-title {
            font-weight: 800;
            font-size: 1.1rem;
        }
        #airlineViewModal .modal-header.airline-view-hd .close {
            color: #fff;
            text-shadow: none;
            opacity: 0.9;
        }
        #airlineViewModal .modal-body.airline-view-bd {
            flex: 0 0 auto;
            padding: 1rem 1.35rem;
            background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
        }
        #airlineViewModal .airline-view-table {
            margin-bottom: 0;
            font-size: 0.9rem;
        }
        #airlineViewModal .airline-view-table th {
            width: 38%;
            background: #f8f9fa;
            font-weight: 700;
            color: #212529;
            vertical-align: middle;
            padding: 0.55rem 0.75rem;
            border-color: #dee2e6;
        }
        #airlineViewModal .airline-view-table td {
            vertical-align: middle;
            padding: 0.55rem 0.75rem;
            border-color: #dee2e6;
            color: #334155;
        }
        #airlineViewModal .modal-footer.airline-view-ft {
            flex: 0 0 auto;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-top: 1px solid #e2e8f0;
            padding: 0.85rem 1.25rem 1rem;
            gap: 0.5rem;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper crm-airline-master">

        <?php include __DIR__ . '/../includes/top-header.php'; ?>
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="content-wrapper">

            <?php include __DIR__ . '/../includes/page-header.php'; ?>

            <section class="content">
                <div class="container-fluid">

                    <div class="page-title-row">
                        <h1 class="page-title">Airline Master</h1>
                        <nav class="breadcrumbs">
                            <a href="dashboard.php">Home</a> / <a href="crm/airline_master.php">Masters</a> / <span class="bc-muted">Airline</span>
                        </nav>
                    </div>

                    <div class="master-card">
                        <div class="master-card-head">
                            <h2>Airlines List</h2>
                            <button type="button" class="btn btn-add" id="btnOpenAirlineFormModal"><i class="fas fa-plus mr-1"></i> Add New Airline</button>
                        </div>

                        <div class="search-row">
                            <div class="input-group">
                                <input type="search" class="form-control" placeholder="Search...">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-search"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="table-wrap">
                            <table class="airline-table mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Logo</th>
                                        <th>Airline Name</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($airlines as $row) { ?>
                                        <tr>
                                            <td><?= (int) $row['id'] ?></td>
                                            <td><span class="logo-placeholder">No Logo</span></td>
                                            <td><?= htmlspecialchars($row['name']) ?></td>
                                            <td><?= htmlspecialchars($row['created']) ?></td>
                                            <td>
                                                <div class="action-btns">
                                                    <button type="button" class="btn-icon btn-view" title="View"><i class="far fa-eye"></i></button>
                                                    <button type="button" class="btn-icon btn-edit" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                                                    <button type="button" class="btn-icon btn-del" title="Delete"><i class="fas fa-trash-alt"></i></button>
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

        <!-- Create / Edit Airline modal -->
        <div class="modal fade" id="airlineFormModal" tabindex="-1" role="dialog" aria-labelledby="airlineFormModalLabel" aria-hidden="true" data-backdrop="static">
            <div class="modal-dialog modal-dialog-centered airline-modal-dialog" role="document">
                <div class="modal-content airline-modal-shell">
                    <form id="airlineFormInner" action="#" method="post" onsubmit="return false;" enctype="multipart/form-data">
                        <input type="hidden" name="id" id="airlineFormId" value="">
                        <div class="modal-header airline-modal-hd text-white">
                            <h5 class="modal-title mb-0" id="airlineFormModalLabel">Airline Information</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body airline-modal-bd">
                            <div class="form-group">
                                <label class="label-req" for="airlineFormName">Airline Name</label>
                                <input type="text" class="form-control" id="airlineFormName" name="airline_name" placeholder="Enter airline name" required>
                            </div>
                            <div class="form-group mb-0">
                                <label for="airlineFormLogo">Airline Logo</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="airlineFormLogo" name="logo" accept=".jpg,.jpeg,.png,.gif">
                                    <label class="custom-file-label" for="airlineFormLogo">Choose file</label>
                                </div>
                                <div class="text-help">Recommended: 200×100 px. Max 2MB. JPG, PNG, GIF.</div>
                                <div class="text-disclaimer"><i class="fas fa-exclamation-triangle mr-1"></i>Use royalty-free or owned images only.</div>
                                <div class="text-tip"><i class="fas fa-check-circle mr-1"></i>
                                    <a href="https://www.pexels.com" target="_blank" rel="noopener">Pexels</a> /
                                    <a href="https://pixabay.com" target="_blank" rel="noopener">Pixabay</a> /
                                    <a href="https://unsplash.com" target="_blank" rel="noopener">Unsplash</a>.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer airline-modal-ft justify-content-start flex-wrap">
                            <button type="submit" class="btn btn-airline-primary" id="airlineFormSubmitBtn"><i class="fas fa-save mr-2"></i><span id="airlineFormSubmitText">Create</span></button>
                            <button type="button" class="btn btn-airline-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Airline modal -->
        <div class="modal fade" id="airlineViewModal" tabindex="-1" role="dialog" aria-labelledby="airlineViewModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered airline-view-dialog" role="document">
                <div class="modal-content airline-view-shell">
                    <div id="airlineViewInner">
                        <div class="modal-header airline-view-hd text-white">
                            <h5 class="modal-title mb-0" id="airlineViewModalLabel">View Airline</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body airline-view-bd">
                            <p class="font-weight-bold mb-2" style="font-size: 0.95rem; color: #222;">Airline Information</p>
                            <table class="table table-bordered airline-view-table">
                                <tbody>
                                    <tr>
                                        <th scope="row">ID</th>
                                        <td id="airlineViewId">—</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Logo</th>
                                        <td id="airlineViewLogo">—</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Airline Name</th>
                                        <td id="airlineViewName">—</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Created At</th>
                                        <td id="airlineViewCreated">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="modal-footer airline-view-ft justify-content-start flex-wrap">
                            <button type="button" class="btn btn-airline-ghost" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/../includes/footer-links.php'; ?>

    </div>

<script>
$(function () {
	function resetAirlineForm() {
		var f = document.getElementById('airlineFormInner');
		if (f) f.reset();
		$('#airlineFormId').val('');
		$('#airlineFormModalLabel').text('Airline Information');
		$('#airlineFormSubmitText').text('Create');
		$('#airlineFormLogo').siblings('.custom-file-label').removeClass('selected').html('Choose file');
	}

	function openAirlineFormModal(mode, $tr) {
		resetAirlineForm();
		if (mode === 'edit' && $tr && $tr.length) {
			var cells = $tr.children('td');
			$('#airlineFormId').val($.trim(cells.eq(0).text()));
			$('#airlineFormName').val($.trim(cells.eq(2).text()));
			$('#airlineFormModalLabel').text('Edit Airline');
			$('#airlineFormSubmitText').text('Save changes');
		}
		$('#airlineFormModal').modal('show');
	}

	function openAirlineViewModal($tr) {
		var cells = $tr.children('td');
		if (cells.length < 4) return;
		$('#airlineViewId').text($.trim(cells.eq(0).text()));
		var logoText = cells.eq(1).find('.logo-placeholder').length
			? $.trim(cells.eq(1).find('.logo-placeholder').text()) || 'No Logo'
			: $.trim(cells.eq(1).text()) || '—';
		$('#airlineViewLogo').text(logoText);
		$('#airlineViewName').text($.trim(cells.eq(2).text()));
		$('#airlineViewCreated').text($.trim(cells.eq(3).text()));
		$('#airlineViewModal').modal('show');
	}

	$('#btnOpenAirlineFormModal').on('click', function () {
		openAirlineFormModal('add', null);
	});

	$(document).on('click', '.airline-table .btn-view', function () {
		openAirlineViewModal($(this).closest('tr'));
	});

	$(document).on('click', '.airline-table .btn-edit', function () {
		openAirlineFormModal('edit', $(this).closest('tr'));
	});

	$('#airlineFormInner').on('submit', function () {
		alert('Demo only: connect this form to your save endpoint.');
		$('#airlineFormModal').modal('hide');
		return false;
	});

	$('#airlineFormLogo').on('change', function () {
		var fileName = $(this).val().split('\\').pop();
		$(this).siblings('.custom-file-label').addClass('selected').html(fileName || 'Choose file');
	});

	var q = new URLSearchParams(window.location.search);
	if (q.get('open') === 'create') {
		openAirlineFormModal('add', null);
		if (window.history && window.history.replaceState) {
			var u = new URL(window.location.href);
			u.search = '';
			window.history.replaceState({}, '', u.pathname + u.search);
		}
	}
});
</script>

</body>

</html>
