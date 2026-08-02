<?php
require_once __DIR__ . '/bootstrap.php';

$adminId = mailGetAdminId();
$account = mailGetAccount($conn, $adminId);
$folder = trim($_GET['folder'] ?? 'INBOX');
$search = trim($_GET['q'] ?? '');
$readFilter = trim($_GET['filter'] ?? 'all');
if (!in_array($readFilter, ['all', 'unread', 'read'], true)) {
    $readFilter = 'all';
}
$pageNum = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($pageNum - 1) * $perPage;

$folders = mailFolderMap();
if (!isset($folders[$folder])) {
    $folder = 'INBOX';
}

$messages = ['rows' => [], 'total' => 0];
$folderCounts = [];
$syncMsg = '';
$syncMsgType = 'info';

if (isset($_SESSION['mail_sync_msg'])) {
    $syncMsg = (string) $_SESSION['mail_sync_msg'];
    $syncMsgType = (string) ($_SESSION['mail_sync_msg_type'] ?? 'info');
    unset($_SESSION['mail_sync_msg'], $_SESSION['mail_sync_msg_type']);
}

if ($account && ($account['imap_status'] ?? '') === 'active') {
    $messages = mailListMessages($conn, (int) $account['id'], $folder, $search, $perPage, $offset, $readFilter);
    $folderCounts = mailFolderCounts($conn, (int) $account['id']);
}

mailSeedSmtpMasterFromLegacy($conn);
$mailSenders = mailListSmtpMaster($conn, true);

$total = (int) $messages['total'];
$totalPages = max(1, (int) ceil($total / $perPage));
$fromNum = $total > 0 ? $offset + 1 : 0;
$toNum = min($offset + $perPage, $total);

$composeTo = trim($_GET['to'] ?? '');
$composeSubject = trim($_GET['subject'] ?? '');
$openCompose = isset($_GET['compose']) || $composeTo !== '' || $composeSubject !== '';

$inboxUrl = static function (array $params = []) use ($folder, $search, $pageNum, $readFilter): string {
    $page = (int) ($params['page'] ?? $pageNum);
    $filter = (string) ($params['filter'] ?? $readFilter);
    $q = [];
    $q['folder'] = $folder;
    if ($search !== '') {
        $q['q'] = $search;
    }
    if ($filter !== '' && $filter !== 'all') {
        $q['filter'] = $filter;
    }
    if ($page > 1) {
        $q['page'] = $page;
    }
    return 'mail/inbox.php?' . http_build_query($q);
};

function mailFormatDate(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    return $ts ? date('D, d M Y', $ts) : $dt;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mail</title>
    <base href="../">
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <link rel="stylesheet" href="mail/assets/mail.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed page-bg">
<div class="wrapper">
    <?php include __DIR__ . '/../includes/top-header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content pt-3">
            <div class="container-fluid">
                <?php if ($syncMsg !== '') { ?>
                    <div class="alert alert-<?= htmlspecialchars($syncMsgType) ?> alert-dismissible fade show">
                        <?= htmlspecialchars($syncMsg) ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php } ?>

                <?php if (!$account || ($account['imap_status'] ?? '') !== 'active') { ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Mail inbox requires active IMAP settings.
                        <a href="crm/office_settings.php" class="alert-link">Configure Email Settings</a>
                    </div>
                <?php } ?>

                <div class="mail-app">
                    <aside class="mail-folders">
                        <button type="button" class="btn btn-compose btn-block" id="mailComposeOpen">
                            <i class="fas fa-pen mr-1"></i> Compose
                        </button>
                        <ul class="mail-folder-list">
                            <?php foreach ($folders as $key => $meta) {
                                $count = (int) ($folderCounts[$key] ?? 0);
                                ?>
                                <li>
                                    <a href="mail/inbox.php?<?= http_build_query(array_filter(['folder' => $key, 'filter' => $readFilter !== 'all' ? $readFilter : null])) ?>"
                                       class="<?= $folder === $key ? 'active' : '' ?>">
                                        <i class="fas <?= htmlspecialchars($meta['icon']) ?>"></i>
                                        <?= htmlspecialchars($meta['label']) ?>
                                        <?php if ($count > 0) { ?>
                                            <span class="mail-folder-count"><?= $count ?></span>
                                        <?php } ?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </aside>

                    <div class="mail-main">
                        <div class="mail-topbar">
                            <h1>Mail</h1>
                            <div class="mail-topbar-actions">
                                <form class="mail-search-wrap position-relative" method="get" action="mail/inbox.php">
                                    <input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
                                    <?php if ($readFilter !== 'all') { ?>
                                        <input type="hidden" name="filter" value="<?= htmlspecialchars($readFilter) ?>">
                                    <?php } ?>
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" name="q" class="form-control" placeholder="Search mail"
                                           value="<?= htmlspecialchars($search) ?>">
                                </form>
                                <a href="crm/office_settings.php" class="btn btn-outline-secondary btn-sm mail-config-btn">
                                    <i class="fas fa-cog mr-1"></i> Email Configuration
                                </a>
                            </div>
                        </div>

                        <div class="mail-toolbar">
                            <input type="checkbox" id="mailSelectAll" title="Select all">
                            <button type="button" class="btn-icon" id="mailDeleteBtn" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button type="button" class="btn-icon mail-sync-btn" id="mailSyncBtn" title="Sync all folders">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <span class="mail-sync-status text-muted small" id="mailSyncStatus"></span>
                            <select class="form-control form-control-sm mail-read-filter" id="mailReadFilter" style="width:auto;">
                                <option value="all" <?= $readFilter === 'all' ? 'selected' : '' ?>>All Mail</option>
                                <option value="unread" <?= $readFilter === 'unread' ? 'selected' : '' ?>>Unread</option>
                                <option value="read" <?= $readFilter === 'read' ? 'selected' : '' ?>>Read</option>
                            </select>
                            <div class="mail-pagination">
                                <span><?= $fromNum ?>-<?= $toNum ?> of <?= $total ?></span>
                                <?php if ($pageNum > 1) { ?>
                                    <a href="<?= htmlspecialchars($inboxUrl(['page' => $pageNum - 1])) ?>"
                                       class="btn btn-sm btn-light"><i class="fas fa-chevron-left"></i></a>
                                <?php } ?>
                                <?php if ($pageNum < $totalPages) { ?>
                                    <a href="<?= htmlspecialchars($inboxUrl(['page' => $pageNum + 1])) ?>"
                                       class="btn btn-sm btn-light"><i class="fas fa-chevron-right"></i></a>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="mail-list" id="mailList">
                            <?php if (empty($messages['rows'])) { ?>
                                <div class="mail-empty">
                                    <i class="fas fa-inbox fa-3x mb-3 text-muted"></i>
                                    <p>No messages in this folder.</p>
                                    <?php if ($account && ($account['imap_status'] ?? '') === 'active') { ?>
                                        <button type="button" class="btn btn-success btn-sm mail-sync-btn">
                                            <i class="fas fa-sync-alt mr-1"></i> Sync mail (runs in background)
                                        </button>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <?php foreach ($messages['rows'] as $msg) { ?>
                                    <div class="mail-row <?= empty($msg['is_read']) ? 'unread' : '' ?>"
                                         data-id="<?= (int) $msg['id'] ?>">
                                        <input type="checkbox" class="mail-check mail-item-check"
                                               value="<?= (int) $msg['id'] ?>">
                                        <span class="mail-star <?= !empty($msg['is_starred']) ? 'starred' : '' ?>"
                                              data-id="<?= (int) $msg['id'] ?>">
                                            <i class="fas fa-star"></i>
                                        </span>
                                        <span class="text-warning"><i class="fas fa-caret-right"></i></span>
                                        <div class="mail-from">
                                            <?php
                                            $fromName = trim((string) ($msg['from_name'] ?? ''));
                                            $fromEmail = (string) ($msg['from_email'] ?? '');
                                            if ($fromName === '') {
                                                echo htmlspecialchars($fromEmail);
                                            } else {
                                                echo htmlspecialchars($fromName . ' <' . $fromEmail . '>');
                                            }
                                            ?>
                                        </div>
                                        <div class="mail-subject">
                                            <?= htmlspecialchars($msg['subject'] ?: '(No subject)') ?>
                                            <?php if (!empty($msg['has_attachments'])) { ?>
                                                <i class="fas fa-paperclip ml-1 text-muted"></i>
                                            <?php } ?>
                                        </div>
                                        <div class="mail-date"><?= htmlspecialchars(mailFormatDate($msg['sent_at'] ?? '')) ?></div>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<?php include __DIR__ . '/includes/compose_modal.php'; ?>

<div class="modal fade" id="mailViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mailViewSubject">Message</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="mail-detail-from" id="mailViewFrom"></div>
                <div class="mail-detail-body" id="mailViewBody"></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer-links.php'; ?>
<script>
window.mailOpenComposeOnLoad = <?= $openCompose ? 'true' : 'false' ?>;
window.mailCurrentFolder = <?= json_encode($folder) ?>;
window.mailCurrentFilter = <?= json_encode($readFilter) ?>;
</script>
<script src="mail/assets/mail.js?v=20260702f"></script>
</body>
</html>
