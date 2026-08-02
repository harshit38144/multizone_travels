<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Clients</title>

    <?php include 'includes/header-links.php'; ?>

    <style>
        .search-box {
            width: 220px;
        }

        .btn-add {
            background: #4e73df;
            color: #fff;
            border-radius: 20px;
            padding: 6px 16px;
        }

        .table td {
            vertical-align: middle;
        }

        .edit-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #d4edda;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #28a745;
        }

        .view-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #eef2f7;
            display: flex;
            align-items: center;
            justify-content: center;
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

                        <div class="d-flex align-items-center" style="gap:15px;">
                            <h5 class="mb-0">Clients</h5>
                            <input type="text" class="form-control form-control-sm search-box" placeholder="Search...">
                        </div>

                        <button class="btn btn-add btn-sm" data-toggle="modal" data-target="#addClientModal">
                            <i class="fas fa-plus"></i> Add New
                        </button>

                    </div>

                    <!-- TABLE -->
                    <div class="card">
                        <div class="card-body p-0">

                            <table class="table table-hover">

                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Name</th>
                                        <th>Mobile</th>
                                        <th>Email</th>
                                        <th>Queries</th>
                                        <th>Last Query</th>
                                        <th>City</th>
                                        <th>By</th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <tr>
                                        <td><input type="checkbox"></td>

                                        <td><strong>Mr. Royden d souza</strong></td>
                                        <td>8792504979</td>
                                        <td>dsouzaryoden@gmail.com</td>
                                        <td>1 <i class="fas fa-external-link-alt text-primary"></i></td>
                                        <td>23-03-2026</td>
                                        <td>-</td>
                                        <td>Travbizz Travel IT Solutions</td>

                                        <td class="d-flex" style="gap:8px;">
                                            <div class="view-btn"><i class="fas fa-eye"></i></div>
                                            <div class="edit-btn"><i class="fas fa-pen"></i></div>
                                        </td>
                                    </tr>

                                </tbody>

                            </table>

                        </div>
                    </div>

                </div>
            </section>
        </div>

        <!-- ADD CLIENT MODAL -->
        <div class="modal fade" id="addClientModal">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5>Add Client</h5>
                        <button class="close" data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body">

                        <div class="row">

                            <!-- NAME -->
                            <div class="col-md-2 form-group">
                                <label></label>
                                <select class="form-control">
                                    <option>Mr</option>
                                    <option>Mrs</option>
                                </select>
                            </div>

                            <div class="col-md-5 form-group">
                                <label>First Name</label>
                                <input type="text" class="form-control">
                            </div>

                            <div class="col-md-5 form-group">
                                <label>Last Name</label>
                                <input type="text" class="form-control">
                            </div>

                            <!-- EMAIL + MOBILE -->
                            <div class="col-md-6 form-group">
                                <label>Email</label>
                                <input type="text" class="form-control">
                            </div>

                            <div class="col-md-2 form-group">
                                <label>&nbsp;</label>
                                <input type="text" class="form-control" value="+91">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Mobile</label>
                                <input type="text" class="form-control">
                            </div>

                            <!-- SECOND -->
                            <div class="col-md-6 form-group">
                                <label>Email 2</label>
                                <input type="text" class="form-control">
                            </div>

                            <div class="col-md-2 form-group">
                                <label>&nbsp;</label>
                                <input type="text" class="form-control" value="+91">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Mobile 2</label>
                                <input type="text" class="form-control">
                            </div>

                            <!-- CITY + ADDRESS -->
                            <div class="col-md-6 form-group">
                                <label>City (type slowly)*</label>
                                <input type="text" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Address</label>
                                <input type="text" class="form-control">
                            </div>

                            <!-- DATES -->
                            <div class="col-md-6 form-group">
                                <label>Date of Birth</label>
                                <input type="date" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Marriage Anniversary</label>
                                <input type="date" class="form-control">
                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-primary">Save</button>
                    </div>

                </div>
            </div>
        </div>

        <?php include 'includes/footer-links.php'; ?>

    </div>
</body>

</html>