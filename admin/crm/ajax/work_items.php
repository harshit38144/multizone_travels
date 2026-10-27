<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/work_items.php';

header('Content-Type: application/json; charset=utf-8');

function wiJson(bool $ok, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wiJson(false, 'Invalid request.');
}

crmEnsureWorkItemTables($conn);
$action = trim((string) ($_POST['action'] ?? 'save'));

if ($action === 'save') {
    $result = crmWorkItemSave($conn, $_POST);
    wiJson(!empty($result['ok']), (string) ($result['message'] ?? 'Could not save.'), [
        'item' => $result['item'] ?? null,
    ]);
}

if ($action === 'status') {
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? 'pending');
    if (!crmWorkItemSetStatus($conn, $id, $status)) {
        wiJson(false, 'Could not update this item.');
    }
    $stmt = $conn->prepare('SELECT * FROM `crm_work_items` WHERE `id` = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    wiJson(true, 'Updated.', ['item' => $row ? crmWorkItemPublic($row) : null]);
}

if ($action === 'delete' || $action === 'bulk') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [$ids];
    }
    if ($action === 'bulk' && (string) ($_POST['bulk'] ?? '') === 'complete') {
        $n = 0;
        foreach ($ids as $id) {
            if (crmWorkItemSetStatus($conn, (int) $id, 'completed')) {
                $n++;
            }
        }
        wiJson(true, 'Updated.', ['items' => crmWorkItemList($conn), 'count' => $n]);
    }
    $n = crmWorkItemDelete($conn, $ids);
    wiJson(true, $n ? 'Removed.' : 'Nothing to remove.', ['items' => crmWorkItemList($conn), 'count' => $n]);
}

wiJson(false, 'Unknown action.');
