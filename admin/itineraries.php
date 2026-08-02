<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Itineraries</title>

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

        .btn-ai {
            background: #1abc9c;
            color: #fff;
            border-radius: 20px;
            padding: 6px 16px;
        }

        .table td {
            vertical-align: middle;
        }

        .thumb {
            width: 60px;
            height: 40px;
            border-radius: 6px;
            object-fit: cover;
        }

        .price-box {
            background: #f3efb3;
            padding: 6px 10px;
            border-radius: 4px;
            font-weight: 600;
        }

        .badge-no {
            background: #dc3545;
            color: #fff;
            padding: 4px 8px;
            border-radius: 5px;
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

        .copy-btn {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #f1f3f5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mini-text {
            font-size: 12px;
            color: #777;
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
                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <div class="d-flex align-items-center" style="gap:15px;">
                            <h5 class="mb-0">Itineraries</h5>
                            <input type="text" class="form-control form-control-sm search-box"
                                placeholder="Search by name">
                        </div>

                        <div>
                            <button class="btn btn-add btn-sm" data-toggle="modal" data-target="#addItineraryModal">
                                <i class="fas fa-plus"></i> Add New
                            </button>

                            <button class="btn btn-ai btn-sm">
                                <i class="fas fa-plus"></i> Create via AI
                            </button>
                        </div>

                    </div>

                    <!-- TABLE -->
                    <div class="card">
                        <div class="card-body p-0">

                            <table class="table table-hover">

                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Title</th>
                                        <th>Duration</th>
                                        <th>Price</th>
                                        <th>Website Cost</th>
                                        <th>Website</th>
                                        <th>Last Updated</th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <tr>
                                        <td>
                                            <img src="https://via.placeholder.com/60x40" class="thumb">
                                        </td>

                                        <td>
                                            <a href="itinerary_builder.php" class="text-dark">
                                                <strong>Amazing Japan</strong>
                                            </a>
                                        </td>

                                        <td>7 Days</td>

                                        <td><span class="price-box">435,000 INR</span></td>

                                        <td>0 INR</td>

                                        <td><span class="badge-no">No</span></td>

                                        <td>22-03-2026</td>

                                        <td class="d-flex" style="gap:8px;">
                                            <div class="copy-btn"><i class="fas fa-copy"></i></div>
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

        <!-- MODAL -->
        <div class="modal fade" id="addItineraryModal">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5>Create Itinerary</h5>
                        <button class="close" data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body">

                        <div class="row">

                            <div class="col-md-12 form-group">
                                <label>Itinerary Name</label>
                                <input type="text" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Start Date</label>
                                <input type="date" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>End Date</label>
                                <input type="date" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Adult</label>
                                <input type="number" class="form-control" value="1">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Child</label>
                                <input type="number" class="form-control">
                            </div>

                            <div class="col-md-4 form-group">
                                <label>Infant</label>
                                <input type="number" class="form-control">
                            </div>

                            <div class="col-md-12 form-group">
                                <label>Destinations</label>
                                <input type="text" class="form-control" placeholder="Enter Destination">
                            </div>

                            <div class="col-md-12 form-group">
                                <label>Notes</label>
                                <textarea class="form-control"></textarea>
                            </div>

                            <div class="col-md-12">
                                <h6 class="mt-2">Website Setting</h6>
                                <hr>
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Theme</label>
                                <select class="form-control">
                                    <option>Adventure</option>
                                </select>
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Show on Website</label>
                                <select class="form-control">
                                    <option>No</option>
                                    <option>Yes</option>
                                </select>
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Per Person Price</label>
                                <input type="text" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Validity</label>
                                <input type="date" class="form-control">
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Popular</label>
                                <select class="form-control">
                                    <option>No</option>
                                    <option>Yes</option>
                                </select>
                            </div>

                            <div class="col-md-6 form-group">
                                <label>Special</label>
                                <select class="form-control">
                                    <option>No</option>
                                    <option>Yes</option>
                                </select>
                            </div>

                            <div class="col-md-12 form-group">
                                <label>About Package</label>
                                <textarea class="form-control" rows="3"></textarea>
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