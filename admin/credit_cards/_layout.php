<?php
/**
 * Shared shell for Credit Card module pages (UI-only stage).
 * Expects: $ccPageTitle, optional $ccPageSubtitle, $ccBodyHtml or $ccRenderBody callback.
 *
 * Must load connection.php first so admin_assets helpers (adminPanelBrandFromSettings, etc.)
 * are available before header-links.php / sidebar.php run.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['role']) || (string) $_SESSION['role'] !== '1') {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../connection.php';

$ccPageTitle = $ccPageTitle ?? 'Credit Cards';
$ccPageSubtitle = $ccPageSubtitle ?? '';
$ccBodyClass = $ccBodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <base href="../">
    <title><?= htmlspecialchars($ccPageTitle, ENT_QUOTES, 'UTF-8') ?> — Credit Cards</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <?php if (!empty($ccExtraHead)) { echo $ccExtraHead; } ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed <?= htmlspecialchars($ccBodyClass, ENT_QUOTES, 'UTF-8') ?>">
<div class="wrapper">
    <?php include __DIR__ . '/../includes/top-header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <?php include __DIR__ . '/../includes/page-header.php'; ?>
        <section class="content">
            <div class="container-fluid">
                <?php
                if (isset($ccRenderBody) && is_callable($ccRenderBody)) {
                    $ccRenderBody();
                } elseif (!empty($ccBodyHtml)) {
                    echo $ccBodyHtml;
                } else {
                    ?>
                    <div class="cc-coming-soon">
                        <div class="cc-coming-soon-card">
                            <div class="cc-coming-soon-icon"><i class="fas fa-credit-card"></i></div>
                            <h3><?= htmlspecialchars($ccPageTitle, ENT_QUOTES, 'UTF-8') ?></h3>
                            <p>This section is coming soon. The Credit Card Management Dashboard is available now.</p>
                            <a href="credit_cards/dashboard.php" class="btn btn-cc-primary">
                                <i class="fas fa-tachometer-alt mr-1"></i> Open Dashboard
                            </a>
                        </div>
                    </div>
                    <style>
                        .cc-coming-soon { padding: 2.5rem 1rem 3rem; }
                        .cc-coming-soon-card {
                            max-width: 480px; margin: 2rem auto; text-align: center;
                            background: #fff; border: 1px solid #e7ebf1; border-radius: 14px;
                            padding: 2.25rem 1.75rem; box-shadow: 0 8px 24px rgba(15,23,42,.06);
                        }
                        .cc-coming-soon-icon {
                            width: 56px; height: 56px; margin: 0 auto 1rem; border-radius: 14px;
                            display: flex; align-items: center; justify-content: center;
                            background: #fff1f2; color: #e11d2e; font-size: 1.35rem;
                        }
                        .cc-coming-soon-card h3 {
                            margin: 0 0 .45rem; color: #0f172a; font-size: 1.2rem; font-weight: 700;
                        }
                        .cc-coming-soon-card p {
                            margin: 0 0 1.15rem; color: #64748b; font-size: .9rem; line-height: 1.5;
                        }
                        .btn-cc-primary {
                            background: #e11d2e; border-color: #e11d2e; color: #fff;
                            font-weight: 600; border-radius: 8px; padding: .45rem 1rem;
                        }
                        .btn-cc-primary:hover { background: #c81a28; border-color: #c81a28; color: #fff; }
                    </style>
                    <?php
                }
                ?>
            </div>
        </section>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer-links.php'; ?>
<?php if (!empty($ccExtraFooter)) { echo $ccExtraFooter; } ?>
</body>
</html>
