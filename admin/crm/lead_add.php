<?php
require_once __DIR__ . '/bootstrap.php';

$embed = isset($_GET['embed']) && $_GET['embed'] === '1';

if ($embed) {
    header('Content-Type: text/html; charset=UTF-8');
    $leadFormScope = '#leadFormModal .crm-lead-form-embed';
    include __DIR__ . '/includes/lead_form_styles.php';
    echo '<div class="crm-lead-form-embed">';
    $leadFormInModal = true;
    include __DIR__ . '/includes/lead_form_content.php';
    echo '</div>';
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Create Lead</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <?php
    $leadFormScope = '.crm-create-lead';
    include __DIR__ . '/includes/lead_form_styles.php';
    ?>
    <style>
        .crm-create-lead .content-wrapper>.content {
            background: #f4f6f9;
        }

        .crm-create-lead .page-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .crm-create-lead .page-title {
            font-size: 1.85rem;
            font-weight: 700;
            color: #111;
            margin: 0;
        }

        .crm-create-lead .breadcrumbs {
            font-size: 0.875rem;
            color: #007bff;
        }

        .crm-create-lead .breadcrumbs a {
            color: #007bff;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper crm-create-lead">

        <?php include __DIR__ . '/../includes/top-header.php'; ?>
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="content-wrapper">

            <?php include __DIR__ . '/../includes/page-header.php'; ?>

            <section class="content">
                <div class="container-fluid">

                    <div class="page-title-row">
                        <h1 class="page-title">Create Lead</h1>
                        <nav class="breadcrumbs">
                            <a href="dashboard.php">Home</a> / <a href="crm/leads.php">Leads</a> / Create
                        </nav>
                    </div>

                    <?php
                    $leadFormInModal = false;
                    include __DIR__ . '/includes/lead_form_content.php';
                    ?>

                </div>
            </section>

        </div>

        <?php include __DIR__ . '/../includes/footer-links.php'; ?>

    </div>

</body>

</html>
