<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Agents</title>

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

        .mini-text {
            font-size: 12px;
            color: #666;
        }

        .highlight-row {
            background: #f3e9c9;
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
                            <h5 class="mb-0">Agents</h5>
                            <input type="text" class="form-control form-control-sm search-box" placeholder="Search...">
                        </div>

                        <button class="btn btn-add btn-sm" data-toggle="modal" data-target="#addAgentModal">
                            <i class="fas fa-plus"></i> Add New
                        </button>

                    </div>

                    <!-- TABLE -->
                    <div class="card">
                        <div class="card-body p-0">

                            <table class="table table-hover">

                                <thead>
                                    <tr>
                                        <th>Company</th>
                                        <th>GST</th>
                                        <th>Name</th>
                                        <th>Mobile</th>
                                        <th>Email</th>
                                        <th>Queries</th>
                                        <th>Last Query</th>
                                        <th>City</th>
                                        <th>By</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <tr>
                                        <td><strong>Fayeda Travel</strong></td>
                                        <td>-</td>
                                        <td><strong>Mr. Pritosh</strong></td>
                                        <td>9855555257</td>
                                        <td>pritosh2006@yahoo.com</td>
                                        <td>1 <i class="fas fa-external-link-alt text-primary"></i></td>
                                        <td>17-03-2026</td>
                                        <td>-</td>
                                        <td>Travbizz Travel IT Solutions</td>
                                    </tr>

                                    <tr class="highlight-row">
                                        <td><strong>Amazing tour and Travel</strong></td>
                                        <td>-</td>
                                        <td><strong>Mr. Renu Mishra</strong></td>
                                        <td>9899911350</td>
                                        <td>renumishra0693@gmail.com</td>
                                        <td>5 <i class="fas fa-external-link-alt text-primary"></i></td>
                                        <td>26-02-2026</td>
                                        <td>-</td>
                                        <td>Travbizz Travel IT Solutions</td>
                                    </tr>

                                    <tr>
                                        <td><strong>Travel Parivar</strong></td>
                                        <td>09AADCW599</td>
                                        <td><strong>Mr. Rahul Kumar</strong></td>
                                        <td>9510186855</td>
                                        <td>thetravelparivar@gmail.com</td>
                                        <td>1 <i class="fas fa-external-link-alt text-primary"></i></td>
                                        <td>22-12-2025</td>
                                        <td>Noida</td>
                                        <td>Travbizz Travel IT Solutions</td>
                                    </tr>

                                </tbody>

                            </table>

                        </div>
                    </div>

                </div>
            </section>
        </div>

        <!-- ADD AGENT MODAL -->
        <div class="modal fade" id="addAgentModal">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5>Add Agent</h5>
                        <button class="close" data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body">

                        <div class="row">

                            <!-- COMPANY -->
                            <div class="col-md-6 form-group">
                                <label>Company</label>
                                <input type="text" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>GST</label>
                                <input type="text" class="form-control">
                            </div>

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
                                <label>City</label>
                                <input type="text" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Address</label>
                                <input type="text" class="form-control">
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