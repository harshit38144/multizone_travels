<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Queries</title>

    <?php include 'includes/header-links.php'; ?>

    <style>
        .btn-add {
            background: #4e73df;
            color: #fff;
            border-radius: 20px;
            padding: 6px 16px;
        }

        .stats-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .stat-box {
            padding: 12px 20px;
            border-radius: 30px;
            color: #fff;
            font-weight: 600;
            min-width: 120px;
            text-align: center;
        }

        .stat-box small {
            display: block;
            font-size: 11px;
            opacity: 0.9;
        }

        .bg-black {
            background: #222;
        }

        .bg-blue {
            background: #5b84c4;
        }

        .bg-orange {
            background: #d9934f;
        }

        .bg-darkblue {
            background: #405c7d;
        }

        .bg-red {
            background: #e04b4b;
        }

        .bg-purple {
            background: #a24ca3;
        }

        .bg-danger {
            background: #c64c4c;
        }

        .bg-warning {
            background: #ff8c00;
        }

        .bg-green {
            background: #4bc08d;
        }

        .bg-dark {
            background: #000;
        }

        .bg-maroon {
            background: #9c3b45;
        }

        .badge-status {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
        }

        .badge-new {
            background: #d1e3ff;
            color: #1d4ed8;
        }

        .badge-proposal {
            background: #ffe0c7;
            color: #d97706;
        }

        .badge-confirm {
            background: #d1fae5;
            color: #059669;
        }

        .badge-hot {
            background: #fde2e2;
            color: #dc2626;
        }

        .action-icons i {
            margin-right: 8px;
            cursor: pointer;
        }

        .table td {
            vertical-align: middle;
        }

        .mini-text {
            font-size: 12px;
            color: #666;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <?php include 'includes/top-header.php'; ?>
        <?php include 'includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <?php include 'includes/page-header.php'; ?>

            <section class="content">
                <div class="container-fluid">

                    <!-- HEADER -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5>Queries</h5>

                        <div>
                            <button class="btn btn-add btn-sm" data-toggle="modal" data-target="#addQueryModal">
                                <i class="fas fa-plus"></i> Add New
                            </button>
                            <button class="btn btn-light btn-sm">Options</button>
                            <button class="btn btn-light btn-sm">Load Leads</button>
                            <button class="btn btn-light btn-sm"><i class="fas fa-filter"></i> Filter</button>
                        </div>
                    </div>

                    <!-- STATS -->
                    <div class="stats-row">
                        <div class="stat-box bg-black">1662<small>TOTAL</small></div>
                        <div class="stat-box bg-blue">583<small>NEW</small></div>
                        <div class="stat-box bg-orange">68<small>PROPOSAL SENT</small></div>
                        <div class="stat-box bg-darkblue">16<small>NO CONNECT</small></div>
                        <div class="stat-box bg-red">32<small>HOT LEAD</small></div>
                        <div class="stat-box bg-purple">36<small>PROPOSAL CON.</small></div>
                        <div class="stat-box bg-danger">33<small>CANCEL</small></div>
                        <div class="stat-box bg-warning">31<small>FOLLOW UP</small></div>
                        <div class="stat-box bg-green">834<small>CONFIRMED</small></div>
                        <div class="stat-box bg-dark">0<small>POSTPONED</small></div>
                        <div class="stat-box bg-maroon">14<small>INVALID</small></div>
                    </div>

                    <!-- TABLE -->
                    <div class="card">
                        <div class="card-body p-0">

                            <table class="table table-hover">

                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Destination</th>
                                        <th>Tour Date</th>
                                        <th>Package</th>
                                        <th>Assign</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <tr>
                                        <td><input type="checkbox"></td>
                                        <td><strong>101979</strong><br><span class="mini-text">2 Adult</span></td>

                                        <td>
                                            <strong>Mr. Shams kabir</strong><br>
                                            <span class="mini-text">9540015148</span>
                                        </td>

                                        <td>
                                            <span class="badge badge-info">Japan</span><br>
                                            <span class="mini-text">Full package</span>
                                        </td>

                                        <td>
                                            25-12-2025<br>
                                            <span class="mini-text">Till 31-12-2025</span>
                                        </td>

                                        <td>
                                            Amazing Japan<br>
                                            <strong>435000 INR</strong>
                                        </td>

                                        <td>
                                            <select class="form-control form-control-sm">
                                                <option>Assign to me</option>
                                            </select>
                                        </td>

                                        <td>
                                            <span class="badge-status badge-new">New</span>
                                        </td>

                                        <td class="action-icons">
                                            <i class="fas fa-eye"></i>
                                            <i class="fas fa-envelope"></i>
                                            <i class="fab fa-whatsapp"></i>
                                            <i class="fas fa-pen"></i>
                                        </td>
                                    </tr>

                                </tbody>

                            </table>

                        </div>
                    </div>

                </div>
            </section>
        </div>

        <!-- MODAL -->
        <div class="modal fade" id="addQueryModal">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5>ADD QUERY</h5>
                        <button class="close" data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body">

                        <div class="row">

                            <div class="col-md-6 form-group">
                                <label>Type</label>
                                <select class="form-control">
                                    <option>Client</option>
                                </select>
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Mobile *</label>
                                <input type="text" class="form-control" placeholder="Phone / Mobile">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Email</label>
                                <input type="text" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Client Name *</label>
                                <input type="text" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Destination *</label>
                                <input type="text" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Travel Month</label>
                                <select class="form-control">
                                    <option>January</option>
                                </select>
                            </div>

                            <div class="col-md-6 form-group">
                                <label>From Date *</label>
                                <input type="date" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>To Date *</label>
                                <input type="date" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Adult *</label>
                                <select class="form-control">
                                    <option>1</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Child</label>
                                <select class="form-control">
                                    <option>0</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Infant</label>
                                <select class="form-control">
                                    <option>0</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Lead Source *</label>
                                <select class="form-control">
                                    <option>Advertisement</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Priority *</label>
                                <select class="form-control">
                                    <option>General Query</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Assign To *</label>
                                <select class="form-control">
                                    <option>Assign to me</option>
                                </select>
                            </div>

                            <div class="col-md-12 form-group">
                                <label>Service</label>
                                <select class="form-control">
                                    <option>Select Service</option>
                                </select>
                            </div>

                            <div class="col-md-12 form-group">
                                <label>Remark</label>
                                <textarea class="form-control" rows="3"></textarea>
                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-light">Cancel</button>
                        <button class="btn btn-primary">Save</button>
                    </div>

                </div>
            </div>
        </div>

        <?php include 'includes/footer-links.php'; ?>

    </div>
</body>

</html>