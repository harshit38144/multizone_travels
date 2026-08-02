<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/quotation_terms_db.php';

crmEnsureQuotationTermsMasterTable($conn);

$richSections = crmQuotationTermsFields();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [];
    foreach (array_keys($richSections) as $field) {
        $payload[$field] = (string) ($_POST[$field] ?? '');
    }

    if (crmSaveQuotationTermsMaster($conn, $payload)) {
        $_SESSION['q_terms_master_flash'] = 'Terms & Policies master saved successfully.';
        $_SESSION['q_terms_master_flash_type'] = 'success';
    } else {
        $_SESSION['q_terms_master_flash'] = 'Could not save Terms & Policies master.';
        $_SESSION['q_terms_master_flash_type'] = 'danger';
    }

    header('Location: quotation_terms_master.php');
    exit;
}

$terms = crmGetQuotationTermsMaster($conn);
$flashMsg = (string) ($_SESSION['q_terms_master_flash'] ?? '');
$flashType = (string) ($_SESSION['q_terms_master_flash_type'] ?? 'success');
unset($_SESSION['q_terms_master_flash'], $_SESSION['q_terms_master_flash_type']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Quotation Terms &amp; Policies Master</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        .crm-qterms-master .content-wrapper > .content {
            background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
            padding: 0.75rem;
        }
        .crm-qterms-master .page-title-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .crm-qterms-master .page-title {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
        }
        .crm-qterms-master .page-subtitle {
            margin: 0.35rem 0 0;
            color: #64748b;
            font-size: 0.92rem;
            max-width: 720px;
        }
        .crm-qterms-master .breadcrumbs {
            font-size: 0.875rem;
            color: #2563eb;
        }
        .crm-qterms-master .breadcrumbs a { color: #2563eb; }
        .crm-qterms-master .master-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }
        .crm-qterms-master .master-card-head {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .crm-qterms-master .master-card-head h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #334155;
        }
        .crm-qterms-master .q-term-section {
            border-bottom: 1px solid #eef2f7;
            padding: 1rem 1.25rem;
        }
        .crm-qterms-master .q-term-section:last-child {
            border-bottom: 0;
        }
        .crm-qterms-master .q-term-section label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 0.5rem;
        }
        .crm-qterms-master .q-term-section .note-editor {
            border-color: #e2e8f0;
            border-radius: 8px;
        }
        .crm-qterms-master .master-actions {
            padding: 1rem 1.25rem;
            border-top: 1px solid #e9ecef;
            background: #fafbfc;
            display: flex;
            justify-content: flex-end;
            gap: 0.65rem;
        }
        .crm-qterms-master .btn-save-master {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
            font-weight: 600;
        }
        .crm-qterms-master .btn-save-master:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed page-bg crm-qterms-master">
<div class="wrapper">
    <?php include __DIR__ . '/../includes/top-header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content pt-3">
            <div class="container-fluid">
                <div class="page-title-row">
                    <div>
                        <h1 class="page-title">Quotation Terms &amp; Policies Master</h1>
                        <p class="page-subtitle">
                            Set default inclusion, exclusion, payment policy, cancellation policy, terms and other details.
                            New quotations will use these values automatically.
                        </p>
                    </div>
                    <nav class="breadcrumbs">
                        <a href="dashboard.php">Home</a> /
                        <a href="crm/quotation-generator-list.php">Quotations</a> /
                        Terms Master
                    </nav>
                </div>

                <?php if ($flashMsg !== '') { ?>
                    <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show">
                        <?= htmlspecialchars($flashMsg) ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php } ?>

                <form method="post" id="qTermsMasterForm">
                    <div class="master-card">
                        <div class="master-card-head">
                            <h2>Default content for quotation Step 5 — Terms &amp; Policies</h2>
                            <a href="crm/quotation_generator.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-file-invoice mr-1"></i> Open Quotation Generator
                            </a>
                        </div>

                        <?php foreach ($richSections as $field => $label) {
                            $val = (string) ($terms[$field] ?? '');
                            ?>
                            <div class="q-term-section">
                                <label for="qtm_<?= htmlspecialchars($field) ?>"><?= htmlspecialchars($label) ?></label>
                                <textarea name="<?= htmlspecialchars($field) ?>" id="qtm_<?= htmlspecialchars($field) ?>"
                                          class="form-control q-term-editor"><?= htmlspecialchars($val) ?></textarea>
                            </div>
                        <?php } ?>

                        <div class="master-actions">
                            <button type="submit" class="btn btn-save-master">
                                <i class="fas fa-save mr-1"></i> Save Master
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer-links.php'; ?>
<script>
$(function () {
    if (!$.fn.summernote) {
        return;
    }
    $('.q-term-editor').each(function () {
        var $ta = $(this);
        $ta.summernote({
            height: 160,
            toolbar: [
                ['style', ['bold', 'italic', 'underline']],
                ['para', ['ul', 'ol']],
                ['insert', ['link']],
                ['view', ['codeview']]
            ],
            callbacks: {
                onInit: function () {
                    if ($ta.val()) {
                        $ta.summernote('code', $ta.val());
                    }
                }
            }
        });
    });

    $('#qTermsMasterForm').on('submit', function () {
        $('.q-term-editor').each(function () {
            var $ta = $(this);
            if ($ta.data('summernote')) {
                $ta.val($ta.summernote('code'));
            }
        });
    });
});
</script>
</body>
</html>
