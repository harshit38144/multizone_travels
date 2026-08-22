<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/quotation_db.php';

crmEnsureQuotationTables($conn);
crmSyncQuotationUidsFromLeads($conn);

$quotations = [];
$res = $conn->query("SELECT * FROM `crm_quotations` ORDER BY `id` DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $quotations[] = $row;
    }
}

function qlListDate($value)
{
    if (empty($value) || $value === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime((string) $value);
    return $ts ? date('d-m-Y', $ts) : '—';
}

function qlTravelDate($value)
{
    if (empty($value) || $value === '0000-00-00') {
        return '';
    }
    $ts = strtotime((string) $value);
    return $ts ? date('d/m/Y', $ts) : '';
}

function qlDestinationLine($destination, $nights)
{
    $destination = trim((string) $destination);
    $nights = (int) $nights;
    if ($destination === '') {
        return '—';
    }
    if ($nights > 0) {
        return $destination . ' ' . $nights . 'N | ' . ($nights + 1) . 'D';
    }
    return $destination;
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Quotation List</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <style>
        .crm-quotation-list .content-wrapper > .content {
            background: #f4f6f9;
        }

        .crm-quotation-list .content-header {
            display: none;
        }

        .crm-quotation-list .page-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .crm-quotation-list .page-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        .crm-quotation-list .list-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }

        .crm-quotation-list .list-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .crm-quotation-list .list-card-head h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #334155;
        }

        .crm-quotation-list .list-card-body {
            padding: 0.75rem 1rem 1rem;
        }

        .crm-quotation-list #quotationTable {
            width: 100% !important;
            margin: 0;
        }

        .crm-quotation-list #quotationTable thead th {
            background: #fff;
            border-top: 0;
            border-bottom: 1px solid #e9ecef;
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
            vertical-align: middle;
        }

        .crm-quotation-list #quotationTable tbody td {
            vertical-align: middle;
            border-top: 1px solid #f1f5f9;
            color: #334155;
            font-size: 0.875rem;
        }

        .crm-quotation-list .cell-main {
            font-weight: 600;
            color: #1e293b;
            line-height: 1.35;
        }

        .crm-quotation-list .cell-sub {
            font-size: 0.8rem;
            color: #94a3b8;
            line-height: 1.35;
            margin-top: 0.1rem;
        }

        .crm-quotation-list .q-uid {
            font-weight: 600;
            color: #2563eb;
            font-size: 0.82rem;
            white-space: nowrap;
        }

        .crm-quotation-list .q-draft-badge {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #92400e;
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 999px;
            padding: 0.15rem 0.5rem;
            margin-bottom: 0.25rem;
        }

        .crm-quotation-list .btn-book {
            min-width: 64px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #475569;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.2rem 0.65rem;
        }

        .crm-quotation-list .btn-book:hover {
            background: #f8fafc;
            color: #1e293b;
            border-color: #94a3b8;
        }

        .crm-quotation-list .btn-action-menu {
            border: 0;
            background: transparent;
            color: #64748b;
            padding: 0.15rem 0.45rem;
            line-height: 1;
        }

        .crm-quotation-list .btn-action-menu:hover,
        .crm-quotation-list .btn-action-menu:focus {
            color: #1e293b;
            background: #f1f5f9;
            box-shadow: none;
        }

        .crm-quotation-list .dataTables_wrapper .dataTables_length,
        .crm-quotation-list .dataTables_wrapper .dataTables_filter {
            margin-bottom: 0.65rem;
        }

        .crm-quotation-list .dataTables_wrapper .dataTables_length label,
        .crm-quotation-list .dataTables_wrapper .dataTables_filter label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 400;
            margin: 0;
        }

        .crm-quotation-list .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 0.25rem 0.5rem;
            margin-left: 0.35rem;
        }

        .crm-quotation-list .dataTables_wrapper .dataTables_info,
        .crm-quotation-list .dataTables_wrapper .dataTables_paginate {
            font-size: 0.82rem;
            color: #64748b;
            margin-top: 0.65rem;
        }

        .crm-quotation-list .col-action {
            width: 48px;
            text-align: center;
        }

        .crm-quotation-list .col-book {
            width: 80px;
            text-align: center;
        }

        .crm-quotation-list .col-status {
            min-width: 100px;
        }

        .crm-quotation-list .q-status-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .crm-quotation-list .q-status-badge {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.15rem 0.45rem;
            border-radius: 3px;
            line-height: 1.3;
            white-space: nowrap;
        }

        .crm-quotation-list .q-status-badge.status-full {
            background: #22c55e;
            color: #fff;
        }

        .crm-quotation-list .q-status-badge.status-half {
            background: #f97316;
            color: #fff;
        }

        .crm-quotation-list .q-status-badge.status-minimum {
            background: #ef4444;
            color: #fff;
        }

        .crm-quotation-list .q-status-badge.status-added {
            background: #e2e8f0;
            color: #475569;
        }

        .crm-quotation-list .btn-confirmed {
            min-width: 78px;
            border: 0;
            background: #22c55e;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.2rem 0.55rem;
        }

        .crm-quotation-list .btn-confirmed:hover {
            background: #16a34a;
            color: #fff;
        }

        /* Confirm Tour modal */
        #confirmTourModal .modal-title {
            color: #64748b;
            font-size: 1.05rem;
            font-weight: 600;
        }

        #confirmTourModal .ct-label {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-bottom: 0.2rem;
        }

        #confirmTourModal .ct-section-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #475569;
            margin: 0.85rem 0 0.45rem;
        }

        #confirmTourModal .ct-included {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }

        #confirmTourModal .ct-chip {
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #334155;
            font-size: 0.78rem;
            font-weight: 500;
            padding: 0.25rem 0.55rem;
            border-radius: 3px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        }

        #confirmTourModal .ct-chip:hover,
        #confirmTourModal .ct-chip.active {
            border-color: #93c5fd;
            background: #eff6ff;
            color: #1d4ed8;
        }

        #confirmTourModal .ct-detail-head,
        #confirmTourModal .ct-detail-row {
            display: grid;
            grid-template-columns: 100px 1fr 90px 90px 80px 130px;
            gap: 0.45rem;
            align-items: end;
        }

        #confirmTourModal .ct-detail-head {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 0.65rem;
            padding-bottom: 0.25rem;
            border-bottom: 1px solid #f1f5f9;
        }

        #confirmTourModal .ct-detail-row {
            padding: 0.45rem 0;
            border-bottom: 1px dashed #f1f5f9;
        }

        #confirmTourModal .ct-detail-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            padding-bottom: 0.35rem;
        }

        #confirmTourModal .ct-detail-field .form-control {
            font-size: 0.82rem;
            padding: 0.3rem 0.45rem;
            height: calc(1.5em + 0.55rem + 2px);
            border: 0;
            border-bottom: 1px dotted #cbd5e1;
            border-radius: 0;
            background: transparent;
        }

        #confirmTourModal .ct-detail-field .form-control:focus {
            box-shadow: none;
            border-bottom-color: #3b82f6;
            background: #f8fafc;
        }

        #confirmTourModal .ct-balance-wrap {
            text-align: center;
            padding-bottom: 0.35rem;
        }

        #confirmTourModal .ct-balance-label {
            display: block;
            font-size: 0.72rem;
            color: #94a3b8;
        }

        #confirmTourModal .ct-balance-val {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            border-bottom: 1px dotted #cbd5e1;
            padding-bottom: 0.15rem;
        }

        #confirmTourModal .ct-detail-actions {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            padding-bottom: 0.2rem;
        }

        #confirmTourModal .ct-detail-actions .ct-reminders {
            font-size: 0.75rem;
            padding: 0.2rem 0.45rem;
        }

        #confirmTourModal .ct-detail-actions .ct-remove-row {
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        @media (max-width: 991.98px) {
            #confirmTourModal .ct-detail-head {
                display: none;
            }

            #confirmTourModal .ct-detail-row {
                grid-template-columns: 1fr;
                gap: 0.35rem;
                border: 1px solid #e2e8f0;
                border-radius: 4px;
                padding: 0.5rem;
                margin-bottom: 0.45rem;
            }
        }

        .crm-quotation-list .col-num {
            width: 42px;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper crm-quotation-list">

        <?php include __DIR__ . '/../includes/top-header.php'; ?>
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <?php include __DIR__ . '/../includes/page-header.php'; ?>

            <section class="content">
                <div class="container-fluid">

                    <div class="page-title-row">
                        <h1 class="page-title">Quotations</h1>
                        <a href="crm/quotation_generator.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus mr-1"></i> Generate Quotation
                        </a>
                    </div>

                    <div id="listAlert"></div>

                    <div class="list-card">
                        <div class="list-card-head">
                            <h2>Quotation list</h2>
                        </div>
                        <div class="list-card-body">
                            <table id="quotationTable" class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="col-num">#</th>
                                        <th>Quotation ID</th>
                                        <th>Date</th>
                                        <th>Guest Name</th>
                                        <th>Destination</th>
                                        <th class="col-status">Status</th>
                                        <th class="col-book">Book</th>
                                        <th class="col-action">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$quotations): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">No quotations yet. Click "Generate Quotation" to create one.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $i = 1; foreach ($quotations as $q): ?>
                                            <?php
                                            $id = (int) $q['id'];
                                            $travelDate = qlTravelDate($q['tentative_date'] ?? '');
                                            $destLine = qlDestinationLine($q['destination'] ?? '', $q['no_of_nights'] ?? 0);
                                            $tourConfirmed = (int) ($q['tour_confirmed'] ?? 0);
                                            $isDraft = (($q['status'] ?? 'published') === 'draft');
                                            $statusHtml = $isDraft
                                                ? '<span class="q-draft-badge">Draft</span>'
                                                : crmQuotationRenderStatusBadges($q['tour_confirm_json'] ?? '');
                                            $guestDisplay = trim((string) ($q['guest_name'] ?? ''));
                                            if ($guestDisplay === '') {
                                                $guestDisplay = $isDraft ? '(Untitled draft)' : '—';
                                            }
                                            ?>
                                            <tr data-id="<?= $id ?>"<?= $isDraft ? ' class="is-draft"' : '' ?>>
                                                <td><?= $i++ ?></td>
                                                <td><span class="q-uid"><?= htmlspecialchars($q['quotation_uid']) ?></span></td>
                                                <td><?= htmlspecialchars(qlListDate($q['updated_at'] ?? $q['created_at'])) ?></td>
                                                <td>
                                                    <div class="cell-main js-q-guest-name"><?= htmlspecialchars($guestDisplay) ?></div>
                                                    <?php if (trim((string) ($q['mobile_no'] ?? '')) !== ''): ?>
                                                        <div class="cell-sub js-q-guest-mobile"><?= htmlspecialchars($q['mobile_no']) ?></div>
                                                    <?php else: ?>
                                                        <div class="cell-sub js-q-guest-mobile"></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="cell-main"><?= htmlspecialchars($destLine) ?></div>
                                                    <?php if ($travelDate !== ''): ?>
                                                        <div class="cell-sub"><?= htmlspecialchars($travelDate) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="js-q-status"><?= $statusHtml ?></td>
                                                <td class="text-center">
                                                    <?php if ($isDraft) { ?>
                                                        <span class="text-muted" style="font-size:0.75rem;">—</span>
                                                    <?php } else { ?>
                                                    <button type="button"
                                                        class="btn btn-sm js-q-book <?= $tourConfirmed ? 'btn-confirmed' : 'btn-book' ?>"
                                                        data-id="<?= $id ?>">
                                                        <?= $tourConfirmed ? 'Confirmed' : 'Book' ?>
                                                    </button>
                                                    <?php } ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="dropdown">
                                                        <button type="button" class="btn btn-action-menu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Actions">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right">
                                                            <a class="dropdown-item" href="crm/quotation_generator.php?id=<?= $id ?>">
                                                                <i class="fas fa-edit fa-fw mr-2 text-muted"></i> <?= $isDraft ? 'Continue Draft' : 'Edit' ?>
                                                            </a>
                                                            <button type="button" class="dropdown-item text-danger js-q-delete" data-id="<?= $id ?>">
                                                                <i class="fas fa-trash fa-fw mr-2"></i> Delete
                                                            </button>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </section>
        </div>
    </div>

    <!-- Confirm Tour Modal -->
    <div class="modal fade" id="confirmTourModal" tabindex="-1" role="dialog" aria-labelledby="confirmTourModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="confirmTourModalLabel">Confirm Tour</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="ctQuotationId" value="">
                    <div class="row">
                        <div class="col-md-6 form-group mb-2">
                            <label class="ct-label" for="ctGuestName">GuestName</label>
                            <input type="text" class="form-control" id="ctGuestName" autocomplete="off">
                        </div>
                        <div class="col-md-6 form-group mb-2">
                            <label class="ct-label" for="ctMobileNo">Mobile No</label>
                            <input type="text" class="form-control" id="ctMobileNo" autocomplete="off">
                        </div>
                    </div>

                    <div class="ct-section-title">What is Included</div>
                    <div class="ct-included" id="ctIncludedChips"></div>

                    <div class="ct-section-title">Fill details</div>
                    <div class="ct-detail-head">
                        <div></div>
                        <div>Supplier</div>
                        <div>Total</div>
                        <div>Paid</div>
                        <div>Balance</div>
                        <div></div>
                    </div>
                    <div id="ctDetailRows"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-primary" id="ctSaveBtn">Save</button>
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer-links.php'; ?>
    <script src="crm/assets/quotation_confirm_tour.js?v=3"></script>

    <script>
        $(function () {
            var $table = $('#quotationTable');
            var hasRows = $table.find('tbody tr[data-id]').length > 0;

            if (hasRows && $.fn.DataTable) {
                $table.DataTable({
                    responsive: true,
                    order: [[2, 'desc']],
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                    columnDefs: [
                        { orderable: false, targets: [0, 5, 6, 7] },
                        { searchable: false, targets: [0, 6, 7] }
                    ],
                    language: {
                        emptyTable: 'No quotations yet. Click "Generate Quotation" to create one.'
                    }
                });
            }

            $(document).on('click', '.js-q-delete', function (e) {
                e.preventDefault();
                if (!confirm('Delete this quotation permanently? This cannot be undone.')) {
                    return;
                }
                var id = $(this).data('id');
                var $row = $(this).closest('tr');
                $.ajax({
                    url: 'crm/ajax/delete_quotation.php',
                    type: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .done(function (res) {
                        if (res && res.success) {
                            if ($.fn.DataTable && $.fn.DataTable.isDataTable('#quotationTable')) {
                                $('#quotationTable').DataTable().row($row).remove().draw(false);
                            } else {
                                $row.fadeOut(150, function () { $(this).remove(); });
                            }
                        } else {
                            $('#listAlert').html('<div class="alert alert-danger">' + ((res && res.message) || 'Could not delete.') + '</div>');
                        }
                    })
                    .fail(function () {
                        $('#listAlert').html('<div class="alert alert-danger">Could not delete. Please try again.</div>');
                    });
            });
        });
    </script>
</body>

</html>
