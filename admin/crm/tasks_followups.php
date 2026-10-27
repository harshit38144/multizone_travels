<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/work_items.php';
require_once __DIR__ . '/includes/lead_db.php';

crmEnsureWorkItemTables($conn);
crmEnsureLeadDeleteColumns($conn);

$items = crmWorkItemList($conn);
$types = crmWorkItemTypes();

$assignees = [];
$userRes = $conn->query("SHOW TABLES LIKE 'users'");
if ($userRes && $userRes->num_rows > 0) {
    $res = $conn->query("SELECT `full_name`, `username` FROM `users` WHERE `is_deleted` = 0 ORDER BY `full_name` ASC, `username` ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $name = trim((string) ($row['full_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($row['username'] ?? ''));
            }
            if ($name !== '' && !in_array($name, $assignees, true)) {
                $assignees[] = $name;
            }
        }
    }
}
$selfName = trim((string) ($_SESSION['name'] ?? ''));
if ($selfName !== '' && !in_array($selfName, $assignees, true)) {
    array_unshift($assignees, $selfName);
}

crmEnsureLeadStageColumn($conn);
$destLookup = [];
$destTable = $conn->query("SHOW TABLES LIKE 'destinations'");
if ($destTable && $destTable->num_rows > 0) {
    $destRes = $conn->query('SELECT `id`, `name` FROM `destinations`');
    if ($destRes) {
        while ($dest = $destRes->fetch_assoc()) {
            $destLookup[(int) $dest['id']] = trim((string) ($dest['name'] ?? ''));
        }
    }
}

$relations = [];
$quoteByLead = [];
$starLeads = [];
$quoteTable = $conn->query("SHOW TABLES LIKE 'crm_quotations'");
if ($quoteTable && $quoteTable->num_rows > 0) {
    $quoteRes = $conn->query(
        'SELECT `id`, `lead_id`, `quotation_uid`, `guest_name`, `destination`, `tentative_date`,
                `no_of_adults`, `no_of_children`, `mobile_no`, `email`, `tour_confirmed`
         FROM `crm_quotations`
         ORDER BY `id` DESC
         LIMIT 300'
    );
    if ($quoteRes) {
        while ($quote = $quoteRes->fetch_assoc()) {
            $qid = (int) $quote['id'];
            $leadId = (int) ($quote['lead_id'] ?? 0);
            $confirmed = (int) ($quote['tour_confirmed'] ?? 0) === 1;
            $dest = trim((string) ($quote['destination'] ?? ''));
            $uid = trim((string) ($quote['quotation_uid'] ?? ''));
            $label = trim(($confirmed ? 'Tour' : 'Quotation') . ' ' . $uid . ($dest !== '' ? ' · ' . $dest : ''));
            $dateText = '';
            if (!empty($quote['tentative_date']) && $quote['tentative_date'] !== '0000-00-00') {
                $ts = strtotime((string) $quote['tentative_date']);
                if ($ts !== false) {
                    $dateText = date('j M Y', $ts);
                }
            }
            $row = [
                'id' => $qid,
                'kind' => $confirmed ? 'tour' : 'quotation',
                'lead_id' => $leadId,
                'label' => $label,
                'guest' => trim((string) ($quote['guest_name'] ?? '')),
                'phone' => trim((string) ($quote['mobile_no'] ?? '')),
                'email' => trim((string) ($quote['email'] ?? '')),
                'destination' => $dest,
                'date' => $dateText,
                'adults' => max(0, (int) ($quote['no_of_adults'] ?? 0)),
                'children' => max(0, (int) ($quote['no_of_children'] ?? 0)),
            ];
            $relations[] = $row;
            if ($leadId > 0 && !isset($quoteByLead[$leadId])) {
                $quoteByLead[$leadId] = $row;
            }
            if ($confirmed && $leadId > 0) {
                $starLeads[$leadId] = true;
            }
        }
    }
}

$leads = [];
$leadRes = $conn->query(
    "SELECT `id`, `customer_name`, `customer_phone`, `customer_email`, `stage`, `payload_json`
     FROM `crm_leads`
     WHERE `deleted_at` IS NULL AND TRIM(COALESCE(`customer_name`, '')) <> ''
     ORDER BY `id` DESC
     LIMIT 400"
);
if ($leadRes) {
    while ($row = $leadRes->fetch_assoc()) {
        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $destNames = [];
        $destIds = $payload['tp_destination'] ?? [];
        if (!is_array($destIds)) {
            $destIds = $destIds !== '' && $destIds !== null ? [$destIds] : [];
        }
        foreach ($destIds as $destId) {
            $label = trim((string) $destId);
            if ($label === '') {
                continue;
            }
            if (ctype_digit($label) && isset($destLookup[(int) $label])) {
                $destNames[] = $destLookup[(int) $label];
                continue;
            }
            if (!ctype_digit($label)) {
                $destNames[] = $label;
            }
        }
        if (!$destNames && !empty($payload['tp_arrival'])) {
            $destNames[] = trim((string) $payload['tp_arrival']);
        }
        $travelDate = '';
        if (!empty($payload['tp_travel_date'])) {
            $ts = strtotime((string) $payload['tp_travel_date']);
            if ($ts !== false) {
                $travelDate = date('j M Y', $ts);
            }
        }
        $adults = max(0, (int) ($payload['tp_adults'] ?? 0));
        $children = max(0, (int) ($payload['tp_children'] ?? 0));
        $city = trim((string) ($payload['tp_departure'] ?? ''));
        $leadId = (int) $row['id'];
        $quote = $quoteByLead[$leadId] ?? null;
        if ($quote) {
            if (!$destNames && $quote['destination'] !== '') {
                $destNames[] = $quote['destination'];
            }
            if ($travelDate === '') {
                $travelDate = $quote['date'];
            }
            if ($adults < 1) {
                $adults = (int) $quote['adults'];
            }
            if ($children < 1) {
                $children = (int) $quote['children'];
            }
        }
        $leads[] = [
            'id' => $leadId,
            'name' => (string) $row['customer_name'],
            'phone' => (string) ($row['customer_phone'] ?? ''),
            'email' => (string) ($row['customer_email'] ?? ''),
            'city' => $city,
            'destination' => implode(', ', array_filter($destNames)),
            'date' => $travelDate,
            'adults' => $adults,
            'children' => $children,
            'star' => !empty($starLeads[$leadId]),
        ];
    }
}

$boot = json_encode([
    'items' => $items,
    'types' => $types,
    'taskTypes' => crmWorkItemTaskTypes(),
    'assignees' => $assignees,
    'leads' => $leads,
    'relations' => $relations,
    'self' => $selfName,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>Tasks &amp; Follow-ups</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        .tf-page { background: #f4f7fb; }
        .tf-page .content-wrapper,
        .tf-page .content-wrapper > .content { background: #f4f7fb; }
        .tf-page .content-header { display: none; }
        .tf-page .content { padding: 1.15rem 1.15rem 1.5rem; }
        .tf-page button { font-family: inherit; }
        .tf-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .tf-title { margin: 0; font-size: 1.55rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; }
        .tf-sub { margin: 0.2rem 0 0; color: #94a3b8; font-size: 0.9rem; }
        .tf-head-actions { display: flex; align-items: center; gap: 0.55rem; flex-wrap: wrap; }
        .tf-btn { height: 38px; border-radius: 10px; border: 1px solid #e6ebf2; background: #fff; color: #334155; font-weight: 700; font-size: 0.84rem; padding: 0 0.9rem; display: inline-flex; align-items: center; gap: 0.4rem; }
        .tf-btn:hover { background: #f8fafc; color: #0f172a; }
        .tf-btn-red { background: #e11d48; border-color: #e11d48; color: #fff; border-radius: 999px; padding: 0 1rem; }
        .tf-btn-red:hover { background: #be123c; color: #fff; }
        .tf-menu { position: relative; }
        .tf-pop { position: absolute; right: 0; top: calc(100% + 6px); min-width: 190px; background: #fff; border: 1px solid #e6ebf2; border-radius: 12px; box-shadow: 0 12px 30px rgba(15,23,42,.12); padding: 0.35rem; z-index: 30; display: none; }
        .tf-pop.is-open { display: block; }
        .tf-pop button { width: 100%; text-align: left; border: 0; background: transparent; border-radius: 8px; padding: 0.5rem 0.65rem; font-weight: 650; color: #1e293b; font-size: 0.84rem; }
        .tf-pop button:hover { background: #fff1f2; color: #be123c; }
        .tf-layout { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 1rem; align-items: start; }
        .tf-card { background: #fff; border: 1px solid #eef2f7; border-radius: 16px; box-shadow: 0 8px 24px rgba(15,23,42,.04); }
        .tf-tabs { display: flex; gap: 1.25rem; padding: 0.85rem 1.1rem 0; border-bottom: 1px solid #eef2f7; }
        .tf-tab { border: 0; background: transparent; color: #94a3b8; font-weight: 800; font-size: 0.95rem; padding: 0.35rem 0.1rem 0.75rem; border-bottom: 2px solid transparent; display: inline-flex; align-items: center; gap: 0.4rem; }
        .tf-tab.is-active { color: #e11d48; border-bottom-color: #e11d48; }
        .tf-stats { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 0.75rem; padding: 0.9rem 1rem 0.3rem; }
        .tf-stat { border: 0; border-radius: 14px; padding: 0.75rem 0.8rem 0.7rem; min-height: 92px; text-align: left; cursor: pointer; width: 100%; }
        .tf-stat.is-today { background: #fff1f2; }
        .tf-stat.is-overdue { background: #fff1f2; }
        .tf-stat.is-upcoming { background: #fffbeb; }
        .tf-stat.is-done { background: #f0fdf4; }
        .tf-stat.is-total { background: #eff6ff; }
        .tf-stat-ico { font-size: 0.95rem; margin-bottom: 0.15rem; }
        .tf-stat.is-today .tf-stat-ico, .tf-stat.is-overdue .tf-stat-ico { color: #e11d48; }
        .tf-stat.is-upcoming .tf-stat-ico { color: #d97706; }
        .tf-stat.is-done .tf-stat-ico { color: #16a34a; }
        .tf-stat.is-total .tf-stat-ico { color: #2563eb; }
        .tf-stat b { display: block; font-size: 1.45rem; line-height: 1.1; color: #0f172a; }
        .tf-stat span { display: block; font-size: 0.78rem; font-weight: 800; color: #1e293b; }
        .tf-stat small { color: #94a3b8; font-size: 0.72rem; }
        .tf-buckets { display: flex; flex-wrap: wrap; gap: 0.45rem; padding: 0.75rem 1rem 0.2rem; }
        .tf-bucket { border: 1px solid #e6ebf2; background: #fff; color: #475569; border-radius: 999px; height: 34px; padding: 0 0.8rem; font-size: 0.8rem; font-weight: 750; display: inline-flex; align-items: center; gap: 0.35rem; }
        .tf-bucket.is-active { background: #e11d48; border-color: #e11d48; color: #fff; }
        .tf-tools { display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; padding: 0.7rem 1rem; flex-wrap: wrap; }
        .tf-view { display: inline-flex; background: #f8fafc; border: 1px solid #e6ebf2; border-radius: 10px; padding: 0.15rem; }
        .tf-view button { border: 0; background: transparent; height: 32px; border-radius: 8px; padding: 0 0.7rem; font-size: 0.78rem; font-weight: 800; color: #64748b; }
        .tf-view button.is-active { background: #e11d48; color: #fff; }
        .tf-filters { display: flex; flex-wrap: wrap; gap: 0.5rem; padding: 0 1rem 0.8rem; align-items: center; }
        .tf-search, .tf-select, .tf-dates { height: 38px; border: 1px solid #e6ebf2; border-radius: 10px; background: #fff; color: #334155; font-size: 0.82rem; }
        .tf-search { display: flex; align-items: center; gap: 0.4rem; padding: 0 0.7rem; min-width: 230px; flex: 1 1 220px; color: #94a3b8; }
        .tf-search input { border: 0; outline: 0; width: 100%; font-size: 0.82rem; color: #0f172a; }
        .tf-select { padding: 0 0.6rem; min-width: 130px; }
        .tf-dates { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0 0.45rem; }
        .tf-dates input { border: 0; width: 118px; font-size: 0.78rem; color: #334155; }
        .tf-reset { border: 0; background: transparent; color: #e11d48; font-weight: 800; font-size: 0.8rem; }
        .tf-table-wrap { overflow-x: auto; }
        .tf-table { width: 100%; border-collapse: collapse; min-width: 980px; }
        .tf-table th { text-align: left; font-size: 0.72rem; color: #94a3b8; font-weight: 800; padding: 0.55rem 0.65rem; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
        .tf-table td { padding: 0.72rem 0.65rem; border-bottom: 1px solid #f8fafc; vertical-align: middle; font-size: 0.82rem; color: #334155; }
        .tf-table tr:hover td { background: #fbfdff; }
        .tf-client { display: flex; align-items: center; gap: 0.55rem; }
        .tf-avatar { width: 34px; height: 34px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 0.68rem; font-weight: 800; flex: 0 0 auto; }
        .tf-client b { display: block; color: #0f172a; font-size: 0.84rem; }
        .tf-client small, .tf-when small { color: #94a3b8; }
        .tf-type { display: inline-flex; align-items: center; gap: 0.3rem; border-radius: 999px; padding: 0.18rem 0.5rem; font-size: 0.72rem; font-weight: 800; background: #f8fafc; }
        .tf-type.is-call { background: #fff1f2; color: #e11d48; }
        .tf-type.is-whatsapp { background: #f0fdf4; color: #16a34a; }
        .tf-type.is-email { background: #eff6ff; color: #2563eb; }
        .tf-type.is-meeting { background: #f5f3ff; color: #7c3aed; }
        .tf-type.is-other { background: #f8fafc; color: #64748b; }
        .tf-purpose { max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tf-when b { display: block; color: #0f172a; font-weight: 750; }
        .tf-rel.is-soon { color: #16a34a; font-weight: 700; }
        .tf-rel.is-late { color: #e11d48; font-weight: 700; }
        .tf-pri { font-weight: 800; font-size: 0.78rem; }
        .tf-pri.is-high { color: #e11d48; }
        .tf-pri.is-medium { color: #d97706; }
        .tf-pri.is-low { color: #16a34a; }
        .tf-status { display: inline-flex; align-items: center; border-radius: 999px; padding: 0.18rem 0.55rem; font-size: 0.72rem; font-weight: 800; }
        .tf-status.is-pending { background: #fff7ed; color: #ea580c; }
        .tf-status.is-overdue { background: #fff1f2; color: #e11d48; }
        .tf-status.is-completed { background: #f0fdf4; color: #16a34a; }
        .tf-assignee { display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 700; color: #334155; }
        .tf-assignee i { width: 22px; height: 22px; border-radius: 999px; background: #e2e8f0; color: #475569; font-style: normal; font-size: 0.62rem; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; }
        .tf-acts { display: inline-flex; align-items: center; gap: 0.25rem; position: relative; }
        .tf-act { width: 30px; height: 30px; border-radius: 8px; border: 1px solid #eef2f7; background: #fff; color: #64748b; }
        .tf-act:hover { background: #fff1f2; color: #e11d48; }
        .tf-row-menu { position: absolute; right: 0; top: 34px; background: #fff; border: 1px solid #e6ebf2; border-radius: 10px; box-shadow: 0 10px 24px rgba(15,23,42,.12); min-width: 150px; z-index: 5; padding: 0.25rem; }
        .tf-row-menu button { display: block; width: 100%; text-align: left; border: 0; background: transparent; padding: 0.4rem 0.55rem; border-radius: 7px; font-size: 0.8rem; font-weight: 700; color: #334155; }
        .tf-row-menu button:hover { background: #f8fafc; }
        .tf-empty { text-align: center; color: #94a3b8; padding: 2.2rem 1rem !important; }
        .tf-foot { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.75rem 1rem 0.95rem; flex-wrap: wrap; }
        .tf-bulk { display: flex; gap: 0.4rem; align-items: center; }
        .tf-pages { display: flex; gap: 0.3rem; align-items: center; }
        .tf-pages button { min-width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e6ebf2; background: #fff; color: #475569; font-weight: 800; }
        .tf-pages button.is-active { background: #e11d48; border-color: #e11d48; color: #fff; }
        .tf-note { color: #94a3b8; font-size: 0.78rem; }
        .tf-cal-board { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 0.45rem; padding: 0.2rem 1rem 1rem; }
        .tf-cal-dow { text-align: center; color: #94a3b8; font-size: 0.72rem; font-weight: 800; }
        .tf-cal-cell { min-height: 92px; border: 1px solid #f1f5f9; border-radius: 10px; padding: 0.35rem; background: #fff; }
        .tf-cal-cell.is-out { opacity: 0.45; }
        .tf-cal-cell.is-today { border-color: #fecdd3; background: #fff1f2; }
        .tf-cal-cell b { font-size: 0.75rem; color: #334155; }
        .tf-cal-item { display: block; margin-top: 0.2rem; font-size: 0.68rem; font-weight: 700; color: #be123c; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tf-side { display: flex; flex-direction: column; gap: 0.8rem; }
        .tf-side-card { background: #fff; border: 1px solid #eef2f7; border-radius: 16px; padding: 0.85rem 0.9rem 0.95rem; box-shadow: 0 8px 24px rgba(15,23,42,.04); }
        .tf-side-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.55rem; }
        .tf-side-head h3 { margin: 0; font-size: 0.92rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.35rem; }
        .tf-side-head h3 i { color: #e11d48; }
        .tf-cal-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.45rem; font-weight: 800; color: #334155; font-size: 0.84rem; }
        .tf-cal-nav button { border: 0; background: #f8fafc; width: 26px; height: 26px; border-radius: 8px; color: #64748b; }
        .tf-mini { width: 100%; border-collapse: collapse; }
        .tf-mini th { color: #94a3b8; font-size: 0.68rem; font-weight: 700; text-align: center; padding-bottom: 0.3rem; }
        .tf-mini td { text-align: center; padding: 0.12rem; }
        .tf-day { width: 30px; height: 30px; border: 0; border-radius: 999px; background: transparent; color: #334155; font-size: 0.75rem; font-weight: 700; position: relative; }
        .tf-day.is-today { background: #e11d48; color: #fff; }
        .tf-day.has-dot::after { content: ''; position: absolute; left: 50%; bottom: 3px; width: 4px; height: 4px; border-radius: 99px; transform: translateX(-50%); background: #f59e0b; }
        .tf-day.has-dot.is-late::after { background: #e11d48; }
        .tf-day.has-dot.is-done::after { background: #16a34a; }
        .tf-day.is-today.has-dot::after { background: #fff; }
        .tf-sched { display: flex; gap: 0.55rem; padding: 0.45rem 0; border-top: 1px solid #f8fafc; }
        .tf-sched time { width: 68px; flex: 0 0 auto; color: #64748b; font-size: 0.72rem; font-weight: 800; }
        .tf-sched b { display: block; font-size: 0.8rem; color: #0f172a; }
        .tf-sched span { color: #94a3b8; font-size: 0.72rem; }
        .tf-link { border: 0; background: transparent; color: #e11d48; font-weight: 800; font-size: 0.75rem; }
        .tf-quick { display: grid; grid-template-columns: 1fr 1fr; gap: 0.45rem; }
        .tf-quick button { border: 1px solid #fee2e2; background: #fff; color: #be123c; border-radius: 10px; min-height: 40px; font-size: 0.75rem; font-weight: 800; text-align: left; padding: 0.4rem 0.55rem; }
        .tf-quick button:hover { background: #fff1f2; }
        .tf-type-row { display: flex; align-items: center; justify-content: space-between; padding: 0.38rem 0; font-size: 0.82rem; color: #334155; font-weight: 700; }
        .tf-type-row span { display: inline-flex; align-items: center; gap: 0.4rem; }
        .tf-dot { width: 8px; height: 8px; border-radius: 99px; display: inline-block; }
        .tf-count-pill { background: #f8fafc; border-radius: 999px; min-width: 26px; text-align: center; font-size: 0.75rem; color: #475569; padding: 0.05rem 0.4rem; }
        #tfModal .modal-dialog { max-width: 1040px; width: calc(100% - 1.4rem); margin: 1.2rem auto; }
        #tfModal .modal-content { border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22); }
        .tf-form-head { background: #e11d48; color: #fff; min-height: 58px; display: flex; align-items: center; justify-content: space-between; padding: 0 1.15rem 0 1.35rem; }
        .tf-form-head h5 { margin: 0; font-size: 1.15rem; font-weight: 800; }
        .tf-form-x { border: 0; background: transparent; color: #fff; font-size: 1.55rem; line-height: 1; padding: 0.2rem 0.15rem; }
        .tf-form-shell { display: grid; grid-template-columns: minmax(0, 1fr) 300px; min-height: 520px; }
        .tf-form-main { padding: 1rem 1.15rem 0.4rem; background: #fff; }
        .tf-kind-switch { display: inline-flex; gap: 0.55rem; margin-bottom: 1rem; }
        .tf-kind-switch button { height: 40px; border-radius: 10px; border: 1px solid #e5e7eb; background: #fff; color: #64748b; font-weight: 800; padding: 0 1rem; display: inline-flex; align-items: center; gap: 0.45rem; }
        .tf-kind-switch button.is-on { background: #e11d48; border-color: #e11d48; color: #fff; }
        .tf-task-card { border: 1px solid #e8edf3; border-radius: 14px; padding: 0.9rem 0.95rem 0.35rem; margin-bottom: 0.2rem; }
        .tf-task-card h4 { margin: 0 0 0.85rem; font-size: 0.95rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.45rem; }
        .tf-task-card h4 i { color: #e11d48; }
        .tf-fields { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.85rem 0.8rem; }
        .tf-field label { display: block; font-size: 0.8rem; font-weight: 800; color: #1e293b; margin-bottom: 0.35rem; }
        .tf-field label em { color: #e11d48; font-style: normal; }
        .tf-control, .tf-area { width: 100%; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; color: #0f172a; font-size: 0.88rem; min-height: 42px; padding: 0.45rem 0.75rem 0.45rem 2.15rem; }
        select.tf-control { appearance: none; -webkit-appearance: none; }
        .tf-control:focus, .tf-area:focus { outline: none; border-color: #fda4af; box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.08); }
        .tf-ico { position: relative; }
        .tf-ico > i.tf-lead { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.82rem; pointer-events: none; }
        .tf-ico > i.tf-caret { position: absolute; right: 0.8rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.7rem; pointer-events: none; }
        .tf-control.pad-right { padding-right: 1.8rem; }
        .tf-area { min-height: 92px; resize: vertical; padding: 0.65rem 0.75rem; }
        .tf-count { text-align: right; color: #94a3b8; font-size: 0.72rem; margin-top: 0.2rem; }
        .tf-pills { display: flex; flex-wrap: wrap; gap: 0.45rem; }
        .tf-pill { height: 40px; border-radius: 10px; border: 1px solid #e5e7eb; background: #fff; color: #334155; font-weight: 700; padding: 0 0.75rem; display: inline-flex; align-items: center; gap: 0.4rem; }
        .tf-pill.is-on { border-color: #fb7185; color: #e11d48; background: #fff; box-shadow: inset 0 0 0 1px #fb7185; }
        .tf-pill .wa { color: #16a34a; }
        .tf-pill .em { color: #2563eb; }
        .tf-pill .mt { color: #e11d48; }
        .tf-combo { position: relative; }
        .tf-combo-menu { position: absolute; left: 0; right: 0; top: calc(100% + 4px); background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12); max-height: 230px; overflow: auto; z-index: 5; }
        .tf-combo-menu button { display: block; width: 100%; text-align: left; border: 0; background: #fff; padding: 0.5rem 0.75rem; font-size: 0.84rem; color: #0f172a; }
        .tf-combo-menu button small { display: block; color: #94a3b8; font-size: 0.72rem; }
        .tf-combo-menu button:hover { background: #fff1f2; }
        .tf-drop { border: 1px dashed #e5e7eb; border-radius: 12px; min-height: 92px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: #64748b; cursor: pointer; background: #fff; padding: 0.8rem; }
        .tf-drop.is-hot { border-color: #fb7185; background: #fff1f2; }
        .tf-drop b { display: block; color: #334155; font-size: 0.84rem; font-weight: 700; }
        .tf-drop small { color: #94a3b8; }
        .tf-drop i { color: #94a3b8; font-size: 1.15rem; margin-bottom: 0.25rem; }
        .tf-files { margin: 0.35rem 0 0; padding: 0; list-style: none; }
        .tf-files li { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; font-size: 0.78rem; color: #334155; padding: 0.2rem 0; }
        .tf-files button { border: 0; background: transparent; color: #e11d48; font-weight: 800; }
        .tf-form-foot { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.85rem 1.15rem 1rem; background: #fff; }
        .tf-again { display: inline-flex; align-items: center; gap: 0.45rem; margin: 0; color: #334155; font-size: 0.86rem; font-weight: 700; }
        .tf-again input { accent-color: #e11d48; width: 16px; height: 16px; }
        .tf-foot-actions { display: flex; gap: 0.55rem; }
        .tf-cancel { height: 40px; border-radius: 10px; border: 1px solid #e5e7eb; background: #fff; color: #334155; font-weight: 800; padding: 0 1.1rem; }
        .tf-submit { height: 40px; border-radius: 10px; border: 0; background: #e11d48; color: #fff; font-weight: 800; padding: 0 1rem; display: inline-flex; align-items: center; gap: 0.4rem; }
        .tf-submit:disabled { opacity: 0.7; }
        .tf-form-side { background: #f8fafc; border-left: 1px solid #eef2f6; padding: 0.95rem 0.9rem 1rem; }
        .tf-side-label { font-size: 0.92rem; font-weight: 800; color: #334155; margin: 0 0 0.7rem; }
        .tf-client-card { background: #fff; border: 1px solid #e8edf3; border-radius: 14px; padding: 0.9rem; }
        .tf-client-top { display: flex; align-items: center; gap: 0.7rem; margin-bottom: 0.75rem; }
        .tf-client-top b { font-size: 0.95rem; color: #0f172a; }
        .tf-star { color: #f59e0b; margin-left: 0.2rem; }
        .tf-client-line { display: flex; align-items: center; gap: 0.5rem; color: #475569; font-size: 0.82rem; padding: 0.22rem 0; word-break: break-word; }
        .tf-client-line .wa { margin-left: auto; color: #16a34a; }
        .tf-client-meta { margin-top: 0.7rem; padding-top: 0.55rem; border-top: 1px solid #f1f5f9; }
        .tf-client-empty { background: #fff; border: 1px dashed #e5e7eb; border-radius: 14px; min-height: 180px; display: flex; align-items: center; justify-content: center; text-align: center; color: #94a3b8; font-size: 0.82rem; padding: 1rem; }
        .tf-tpl-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.55rem; }
        .tf-tpl-head h4 { margin: 0; font-size: 0.9rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.4rem; }
        .tf-tpl-head h4 i { color: #e11d48; }
        .tf-tpl-head button { border: 0; background: transparent; color: #e11d48; font-weight: 800; font-size: 0.78rem; }
        .tf-tpl { width: 100%; border: 1px solid #e8edf3; background: #fff; border-radius: 12px; min-height: 46px; margin-bottom: 0.45rem; display: flex; align-items: center; gap: 0.6rem; padding: 0.4rem 0.7rem; text-align: left; font-weight: 700; color: #1e293b; font-size: 0.84rem; }
        .tf-tpl span { margin-left: auto; color: #cbd5e1; }
        .tf-tpl i.ic { width: 22px; text-align: center; }
        .tf-recur { background: #fff; border: 1px solid #e8edf3; border-radius: 14px; padding: 0.75rem 0.8rem; margin: 0.85rem 0; }
        .tf-recur-top { display: flex; align-items: center; justify-content: space-between; gap: 0.45rem; font-weight: 800; color: #0f172a; font-size: 0.8rem; }
        .tf-recur-top span { white-space: nowrap; }
        .tf-recur-top i { color: #e11d48; margin-right: 0.3rem; }
        .tf-switch { width: 42px; height: 24px; border: 0; border-radius: 99px; background: #e2e8f0; position: relative; flex: 0 0 auto; }
        .tf-switch i { position: absolute; width: 18px; height: 18px; border-radius: 50%; background: #fff; top: 3px; left: 3px; transition: left 0.15s ease; }
        .tf-switch.is-on { background: #e11d48; }
        .tf-switch.is-on i { left: 21px; }
        .tf-recur label { display: block; margin: 0.65rem 0 0.3rem; font-size: 0.75rem; color: #64748b; font-weight: 700; }
        .tf-client-note { margin: 0.85rem 0 0.45rem; font-size: 0.86rem; font-weight: 800; color: #0f172a; }
        .tf-client-note i { color: #e11d48; margin-right: 0.3rem; }
        .tf-fields[data-mode="followup"] [data-f="client"] { order: 1; grid-column: 1 / -1; }
        .tf-fields[data-mode="followup"] [data-f="ftype"] { order: 2; grid-column: 1 / -1; }
        .tf-fields[data-mode="followup"] [data-f="title"] { order: 3; grid-column: 1 / -1; }
        .tf-fields[data-mode="followup"] [data-f="notes"] { order: 4; grid-column: 1 / -1; }
        .tf-fields[data-mode="followup"] [data-f="date"] { order: 5; }
        .tf-fields[data-mode="followup"] [data-f="time"] { order: 6; }
        .tf-fields[data-mode="followup"] [data-f="priority"] { order: 7; }
        .tf-fields[data-mode="followup"] [data-f="status"] { order: 8; }
        .tf-fields[data-mode="followup"] [data-f="assign"] { order: 9; }
        .tf-fields[data-mode="followup"] [data-f="reminder"] { order: 10; }
        .tf-fields[data-mode="followup"] [data-f="related"] { order: 11; grid-column: 1 / -1; }
        .tf-fields[data-mode="followup"] [data-f="files"] { order: 12; grid-column: 1 / -1; }
        .tf-fields[data-mode="followup"] [data-f="ttype"],
        .tf-fields[data-mode="followup"] [data-f="due"],
        .tf-fields[data-mode="followup"] .tf-task-head { display: none; }
        .tf-fields[data-mode="task"] { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .tf-fields[data-mode="task"] .tf-task-head { order: 0; grid-column: 1 / -1; }
        .tf-fields[data-mode="task"] [data-f="title"] { order: 1; grid-column: span 2; }
        .tf-fields[data-mode="task"] [data-f="ttype"] { order: 2; }
        .tf-fields[data-mode="task"] [data-f="related"] { order: 3; grid-column: span 2; }
        .tf-fields[data-mode="task"] [data-f="priority"] { order: 4; }
        .tf-fields[data-mode="task"] [data-f="notes"] { order: 5; grid-column: 1 / -1; }
        .tf-fields[data-mode="task"] [data-f="date"] { order: 6; }
        .tf-fields[data-mode="task"] [data-f="due"] { order: 7; }
        .tf-fields[data-mode="task"] [data-f="time"] { order: 8; }
        .tf-fields[data-mode="task"] [data-f="status"] { order: 9; }
        .tf-fields[data-mode="task"] [data-f="assign"] { order: 10; }
        .tf-fields[data-mode="task"] [data-f="reminder"] { order: 11; }
        .tf-fields[data-mode="task"] [data-f="files"] { order: 12; grid-column: 1 / -1; }
        .tf-fields[data-mode="task"] [data-f="client"],
        .tf-fields[data-mode="task"] [data-f="ftype"] { display: none; }
        .tf-fields.is-task { border: 1px solid #e8edf3; border-radius: 14px; padding: 0.9rem 0.95rem 1rem; }
        .tf-task-head h4 { margin: 0; font-size: 0.95rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.45rem; }
        .tf-task-head h4 i { color: #e11d48; }
        @media (max-width: 991.98px) {
            .tf-form-shell { grid-template-columns: 1fr; }
            .tf-form-side { border-left: 0; border-top: 1px solid #eef2f6; }
            .tf-fields, .tf-fields[data-mode="task"] { grid-template-columns: 1fr; }
            .tf-fields[data-mode="task"] [data-f="time"],
            .tf-fields[data-mode="task"] [data-f="reminder"] { grid-column: auto; }
        }
        @media (max-width: 1199.98px) {
            .tf-layout { grid-template-columns: 1fr; }
            .tf-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed tf-page">
<div class="wrapper">
    <?php include __DIR__ . '/../includes/top-header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="content-wrapper">
        <?php include __DIR__ . '/../includes/page-header.php'; ?>
        <section class="content">
            <div class="container-fluid">
                <div class="tf-head">
                    <div>
                        <h1 class="tf-title">Tasks &amp; Follow-ups</h1>
                        <p class="tf-sub">Manage your team tasks and client follow-ups in one place.</p>
                    </div>
                    <div class="tf-head-actions">
                        <button type="button" class="tf-btn" id="tfViewCalendar"><i class="far fa-calendar-alt"></i> View Calendar</button>
                        <div class="tf-menu">
                            <button type="button" class="tf-btn" id="tfExportBtn"><i class="fas fa-download"></i> Export <i class="fas fa-chevron-down"></i></button>
                            <div class="tf-pop" id="tfExportPop">
                                <button type="button" id="tfExportCsv">Export CSV</button>
                            </div>
                        </div>
                        <div class="tf-menu">
                            <button type="button" class="tf-btn tf-btn-red" id="tfCreateBtn"><i class="fas fa-plus"></i> Create Task / Follow-up <i class="fas fa-chevron-down"></i></button>
                            <div class="tf-pop" id="tfCreatePop">
                                <button type="button" data-create="followup">Create Follow-up</button>
                                <button type="button" data-create="task">Create Task</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tf-layout">
                    <div class="tf-card" id="tfMain">
                        <div class="tf-tabs">
                            <button type="button" class="tf-tab is-active" data-kind="followup"><i class="fas fa-phone-alt"></i> Follow-ups</button>
                            <button type="button" class="tf-tab" data-kind="task"><i class="far fa-check-square"></i> Tasks</button>
                        </div>
                        <div class="tf-stats" id="tfStats"></div>
                        <div class="tf-buckets" id="tfBuckets"></div>
                        <div class="tf-tools">
                            <div></div>
                            <div class="tf-view">
                                <button type="button" class="is-active" data-view="list"><i class="fas fa-list"></i> List View</button>
                                <button type="button" data-view="calendar"><i class="far fa-calendar-alt"></i> Calendar View</button>
                            </div>
                        </div>
                        <div class="tf-filters">
                            <label class="tf-search"><i class="fas fa-search"></i><input type="search" id="tfSearch" placeholder="Search by client name, phone, email..."></label>
                            <select class="tf-select" id="tfType"><option value="">Follow-up Type</option></select>
                            <select class="tf-select" id="tfStatus">
                                <option value="">Status</option>
                                <option value="pending">Pending</option>
                                <option value="overdue">Overdue</option>
                                <option value="completed">Completed</option>
                            </select>
                            <select class="tf-select" id="tfPriority">
                                <option value="">Priority</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                            <select class="tf-select" id="tfAssignee"><option value="">Assigned To</option></select>
                            <label class="tf-dates"><i class="far fa-calendar-alt"></i><input type="date" id="tfFrom" aria-label="From date"><input type="date" id="tfTo" aria-label="To date"></label>
                            <button type="button" class="tf-reset" id="tfReset">Reset</button>
                        </div>
                        <div id="tfList">
                            <div class="tf-table-wrap">
                                <table class="tf-table">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="tfCheckAll" aria-label="Select all"></th>
                                            <th>#</th>
                                            <th>Client / Lead</th>
                                            <th>Type</th>
                                            <th>Purpose / Notes</th>
                                            <th>Follow-up Date &amp; Time</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Assigned To</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tfRows"></tbody>
                                </table>
                            </div>
                            <div class="tf-foot">
                                <div class="tf-bulk">
                                    <select class="tf-select" id="tfBulk">
                                        <option value="">Bulk Actions</option>
                                        <option value="complete">Mark completed</option>
                                        <option value="delete">Delete</option>
                                    </select>
                                    <button type="button" class="tf-btn" id="tfBulkApply">Apply</button>
                                </div>
                                <div class="tf-note" id="tfRange"></div>
                                <div class="tf-pages" id="tfPages"></div>
                            </div>
                        </div>
                        <div id="tfCalendar" hidden></div>
                    </div>

                    <aside class="tf-side">
                        <div class="tf-side-card">
                            <div class="tf-side-head"><h3><i class="far fa-calendar-alt"></i> <span id="tfCalTitle">Follow-up Calendar</span></h3></div>
                            <div class="tf-cal-nav">
                                <button type="button" id="tfCalPrev" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>
                                <span id="tfCalLabel"></span>
                                <button type="button" id="tfCalNext" aria-label="Next month"><i class="fas fa-chevron-right"></i></button>
                            </div>
                            <table class="tf-mini" id="tfMini"></table>
                        </div>
                        <div class="tf-side-card">
                            <div class="tf-side-head">
                                <h3><i class="far fa-clock"></i> Today's Schedule <span class="tf-count-pill" id="tfTodayCount">0</span></h3>
                                <button type="button" class="tf-link" id="tfViewToday">View All</button>
                            </div>
                            <div id="tfSchedule"></div>
                        </div>
                        <div class="tf-side-card">
                            <div class="tf-side-head"><h3><i class="fas fa-bolt"></i> Quick Actions</h3></div>
                            <div class="tf-quick">
                                <button type="button" data-create="followup"><i class="fas fa-plus"></i> Create Follow-up</button>
                                <button type="button" data-create="task"><i class="fas fa-plus"></i> Create Task</button>
                                <button type="button" id="tfQuickOverdue"><i class="fas fa-exclamation-circle"></i> View Overdue</button>
                                <button type="button" id="tfQuickReport"><i class="fas fa-file-export"></i> Follow-up Report</button>
                            </div>
                        </div>
                        <div class="tf-side-card">
                            <div class="tf-side-head"><h3 id="tfTypeTitle">Follow-up Types</h3></div>
                            <div id="tfTypeList"></div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="tfModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form class="modal-content" id="tfForm">
            <div class="tf-form-head">
                <h5 id="tfModalTitle">Create Task / Follow-up</h5>
                <button type="button" class="tf-form-x" data-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <div class="tf-form-shell">
                <div class="tf-form-main">
                    <input type="hidden" id="tfId">
                    <input type="hidden" id="tfKind" value="followup">
                    <input type="hidden" id="tfLeadId">
                    <input type="hidden" id="tfPhone">
                    <input type="hidden" id="tfEmail">
                    <input type="hidden" id="tfItemType" value="call">
                    <div class="tf-kind-switch">
                        <button type="button" class="is-on" data-form-kind="followup"><i class="fas fa-phone-alt"></i> Follow-up</button>
                        <button type="button" data-form-kind="task"><i class="fas fa-check"></i> Task</button>
                    </div>
                    <div class="tf-fields" id="tfFields" data-mode="followup">
                        <div class="tf-task-head"><h4><i class="far fa-clipboard"></i> Task Details</h4></div>
                        <div class="tf-field" data-f="client">
                            <label for="tfClient">Client / Lead <em>*</em></label>
                            <div class="tf-combo tf-ico">
                                <i class="fas fa-user tf-lead"></i>
                                <input class="tf-control pad-right" id="tfClient" autocomplete="off" placeholder="Search client / lead by name, phone or email...">
                                <i class="fas fa-chevron-down tf-caret"></i>
                                <div class="tf-combo-menu" id="tfClientMenu" hidden></div>
                            </div>
                        </div>
                        <div class="tf-field" data-f="ftype">
                            <label>Follow-up Type <em>*</em></label>
                            <div class="tf-pills" id="tfTypePills">
                                <button type="button" class="tf-pill is-on" data-type="call"><i class="fas fa-phone-alt"></i> Call</button>
                                <button type="button" class="tf-pill" data-type="whatsapp"><i class="fab fa-whatsapp wa"></i> WhatsApp</button>
                                <button type="button" class="tf-pill" data-type="email"><i class="fas fa-envelope em"></i> Email</button>
                                <button type="button" class="tf-pill" data-type="meeting"><i class="far fa-calendar-alt mt"></i> Meeting</button>
                                <button type="button" class="tf-pill" data-type="other"><i class="fas fa-ellipsis-h"></i> Other</button>
                            </div>
                        </div>
                        <div class="tf-field" data-f="title">
                            <label for="tfTitle">Title <em>*</em></label>
                            <input class="tf-control" id="tfTitle" style="padding-left:0.75rem" placeholder="Enter follow-up title">
                        </div>
                        <div class="tf-field" data-f="ttype">
                            <label for="tfTaskType">Task Type <em>*</em></label>
                            <div class="tf-ico">
                                <i class="far fa-clipboard tf-lead"></i>
                                <select class="tf-control pad-right" id="tfTaskType"></select>
                                <i class="fas fa-chevron-down tf-caret"></i>
                            </div>
                        </div>
                        <div class="tf-field" data-f="related">
                            <label for="tfRelated" id="tfRelatedLabel">Related To (Optional)</label>
                            <div class="tf-ico">
                                <i class="fas fa-link tf-lead"></i>
                                <select class="tf-control pad-right" id="tfRelated"></select>
                                <i class="fas fa-chevron-down tf-caret"></i>
                            </div>
                        </div>
                        <div class="tf-field" data-f="priority">
                            <label for="tfItemPriority" id="tfPriorityLabel">Priority</label>
                            <div class="tf-ico">
                                <i class="fas fa-flag tf-lead" id="tfPriFlag" style="color:#f59e0b"></i>
                                <select class="tf-control pad-right" id="tfItemPriority">
                                    <option value="high">High</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="low">Low</option>
                                </select>
                                <i class="fas fa-chevron-down tf-caret"></i>
                            </div>
                        </div>
                        <div class="tf-field" data-f="notes">
                            <label for="tfNotes">Description / Notes</label>
                            <textarea class="tf-area" id="tfNotes" maxlength="500" placeholder="Add notes, discussion points, or next steps..."></textarea>
                            <div class="tf-count"><span id="tfNoteCount">0</span>/500</div>
                        </div>
                        <div class="tf-field" data-f="date">
                            <label for="tfDate" id="tfDateLabel">Follow-up Date <em>*</em></label>
                            <div class="tf-ico">
                                <i class="far fa-calendar-alt tf-lead"></i>
                                <input class="tf-control" type="date" id="tfDate">
                            </div>
                        </div>
                        <div class="tf-field" data-f="due">
                            <label for="tfDue">Due Date <em>*</em></label>
                            <div class="tf-ico">
                                <i class="far fa-calendar-alt tf-lead"></i>
                                <input class="tf-control" type="date" id="tfDue">
                            </div>
                        </div>
                        <div class="tf-field" data-f="time">
                            <label for="tfTime" id="tfTimeLabel">Time <em>*</em></label>
                            <div class="tf-ico">
                                <i class="far fa-clock tf-lead"></i>
                                <select class="tf-control pad-right" id="tfTime"></select>
                                <i class="fas fa-chevron-down tf-caret"></i>
                            </div>
                        </div>
                        <div class="tf-field" data-f="status">
                            <label for="tfItemStatus" id="tfStatusLabel">Status</label>
                            <div class="tf-ico">
                                <i class="far fa-clock tf-lead" style="color:#f59e0b"></i>
                                <select class="tf-control pad-right" id="tfItemStatus">
                                    <option value="pending">Pending</option>
                                    <option value="completed">Completed</option>
                                </select>
                                <i class="fas fa-chevron-down tf-caret"></i>
                            </div>
                        </div>
                        <div class="tf-field" data-f="assign">
                            <label for="tfAssign" id="tfAssignLabel">Assign To</label>
                            <div class="tf-ico">
                                <i class="far fa-user tf-lead"></i>
                                <select class="tf-control pad-right" id="tfAssign"></select>
                                <i class="fas fa-chevron-down tf-caret"></i>
                            </div>
                        </div>
                        <div class="tf-field" data-f="reminder">
                            <label for="tfReminder" id="tfReminderLabel">Reminder</label>
                            <div class="tf-ico">
                                <i class="far fa-bell tf-lead" style="color:#f59e0b"></i>
                                <select class="tf-control pad-right" id="tfReminder">
                                    <option value="none">No reminder</option>
                                    <option value="15m">15 minutes before</option>
                                    <option value="30m" selected>30 minutes before</option>
                                    <option value="1h">1 hour before</option>
                                    <option value="1d">1 day before</option>
                                </select>
                                <i class="fas fa-chevron-down tf-caret"></i>
                            </div>
                        </div>
                        <div class="tf-field" data-f="files">
                            <label>Attachments (Optional)</label>
                            <div class="tf-drop" id="tfDrop">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <b>Drag &amp; drop files here or click to upload</b>
                                <small>PDF, DOC, XLS, JPG, PNG (Max 5 MB each)</small>
                            </div>
                            <input type="file" id="tfFiles" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" hidden>
                            <ul class="tf-files" id="tfFileList"></ul>
                        </div>
                    </div>
                </div>
                <aside class="tf-form-side">
                    <div id="tfFollowSide">
                        <h3 class="tf-side-label">Client Details</h3>
                        <div id="tfClientCard"></div>
                    </div>
                    <div id="tfTaskSide" hidden>
                        <div class="tf-tpl-head">
                            <h4><i class="far fa-clipboard"></i> Quick Task Templates</h4>
                            <button type="button" id="tfUseTemplate">Use Template</button>
                        </div>
                        <div id="tfTemplates"></div>
                        <div class="tf-recur">
                            <div class="tf-recur-top"><span><i class="fas fa-sync-alt"></i> Set Recurring Task (Optional)</span><button type="button" class="tf-switch" id="tfRecur" aria-pressed="false"><i></i></button></div>
                            <label for="tfRecurEvery">Repeat</label>
                            <div class="tf-ico">
                                <select class="tf-control pad-right" id="tfRecurEvery" disabled style="padding-left:0.75rem">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                                <i class="fas fa-chevron-down tf-caret"></i>
                            </div>
                        </div>
                        <div class="tf-client-note"><i class="far fa-user"></i> Client Information <span style="font-weight:600;color:#94a3b8">(if related)</span></div>
                        <div id="tfTaskClient"></div>
                    </div>
                </aside>
            </div>
            <div class="tf-form-foot">
                <label class="tf-again"><input type="checkbox" id="tfAnother" checked> Create another after saving</label>
                <div class="tf-foot-actions">
                    <button type="button" class="tf-cancel" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="tf-submit" id="tfSave"><i class="far fa-save"></i> <span>Save Follow-up</span></button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer-links.php'; ?>
<script>
(function ($) {
    var boot = <?= $boot ?: '{}' ?>;
    var items = boot.items || [];
    var types = boot.types || {};
    var state = { kind: 'followup', bucket: 'all', view: 'list', page: 1, pageSize: 10, selected: {}, month: new Date() };
    state.month = new Date(state.month.getFullYear(), state.month.getMonth(), 1);
    var typeOrder = ['call', 'whatsapp', 'email', 'meeting', 'other'];
    var typeIcon = { call: 'fas fa-phone-alt', whatsapp: 'fab fa-whatsapp', email: 'fas fa-envelope', meeting: 'fas fa-users', other: 'fas fa-ellipsis-h' };
    var taskIcons = { general: 'far fa-clipboard', prepare_quotation: 'far fa-file-alt', check_payment: 'fas fa-rupee-sign', send_itinerary: 'fas fa-plane', collect_documents: 'fas fa-file-download', call_supplier: 'fas fa-phone-alt', other: 'fas fa-ellipsis-h' };
    var taskIconColor = { general: '#e11d48', prepare_quotation: '#e11d48', check_payment: '#16a34a', send_itinerary: '#2563eb', collect_documents: '#16a34a', call_supplier: '#7c3aed', other: '#64748b' };
    var keptFiles = [];
    var newFiles = [];
    function labelFor(item) {
        var map = item.kind === 'task' ? (boot.taskTypes || {}) : types;
        return map[item.item_type] || item.item_type || '';
    }
    function iconFor(item) {
        return item.kind === 'task' ? (taskIcons[item.item_type] || 'far fa-clipboard') : (typeIcon[item.item_type] || 'fas fa-phone-alt');
    }
    function displayName(item) { return item.client_name || item.title || '—'; }
    function displayNote(item) { return item.title || item.purpose || item.notes || ''; }
    var avatarColors = ['#e11d48', '#7c3aed', '#2563eb', '#ea580c', '#0d9488', '#db2777', '#16a34a', '#d97706'];

    function parseWhen(value) {
        if (!value) return null;
        var d = new Date(String(value).replace(' ', 'T'));
        return isNaN(d.getTime()) ? null : d;
    }
    function startOfDay(d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }
    function sameDay(a, b) { return a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }
    function weekStart(d) {
        var day = d.getDay();
        var diff = day === 0 ? 6 : day - 1;
        return startOfDay(new Date(d.getFullYear(), d.getMonth(), d.getDate() - diff));
    }
    function derived(item) {
        if (item.status === 'completed') return 'completed';
        var when = parseWhen(item.scheduled_at);
        if (!when) return 'pending';
        if (startOfDay(when).getTime() < startOfDay(new Date()).getTime()) return 'overdue';
        return 'pending';
    }
    function inBucket(item, bucket) {
        var when = parseWhen(item.scheduled_at);
        var today = startOfDay(new Date());
        var status = derived(item);
        if (bucket === 'all') return true;
        if (bucket === 'completed') {
            if (status !== 'completed') return false;
            var done = parseWhen(item.completed_at) || when;
            if (!done) return false;
            var ws = weekStart(new Date());
            var we = new Date(ws.getFullYear(), ws.getMonth(), ws.getDate() + 7);
            return done >= ws && done < we;
        }
        if (status === 'completed') return false;
        if (!when) return false;
        if (bucket === 'today') return sameDay(when, today);
        if (bucket === 'overdue') return status === 'overdue';
        if (bucket === 'upcoming') {
            var end = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 8);
            return when >= new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1) && when < end;
        }
        return true;
    }
    function kindItems() {
        return items.filter(function (item) { return item.kind === state.kind; });
    }
    function filtered() {
        var q = $.trim($('#tfSearch').val() || '').toLowerCase();
        var type = $('#tfType').val();
        var status = $('#tfStatus').val();
        var priority = $('#tfPriority').val();
        var assignee = $('#tfAssignee').val();
        var from = $('#tfFrom').val();
        var to = $('#tfTo').val();
        return kindItems().filter(function (item) {
            if (!inBucket(item, state.bucket)) return false;
            if (type && item.item_type !== type) return false;
            if (priority && item.priority !== priority) return false;
            if (assignee && item.assigned_name !== assignee) return false;
            var live = derived(item);
            if (status === 'overdue' && live !== 'overdue') return false;
            if (status === 'pending' && live !== 'pending') return false;
            if (status === 'completed' && live !== 'completed') return false;
            var when = parseWhen(item.scheduled_at);
            if (from && when && when < new Date(from + 'T00:00:00')) return false;
            if (to && when && when > new Date(to + 'T23:59:59')) return false;
            if (!q) return true;
            var blob = (item.client_name + ' ' + item.client_phone + ' ' + item.client_email + ' ' + (item.title || '') + ' ' + (item.purpose || '') + ' ' + (item.notes || '') + ' ' + item.assigned_name).toLowerCase();
            return blob.indexOf(q) !== -1;
        });
    }
    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function initials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        var a = (parts[0] || '').charAt(0);
        var b = parts.length > 1 ? parts[parts.length - 1].charAt(0) : (parts[0] || '').charAt(1);
        return (a + b).toUpperCase() || '—';
    }
    function colorFor(name) {
        var n = 0;
        String(name || '').split('').forEach(function (ch) { n += ch.charCodeAt(0); });
        return avatarColors[n % avatarColors.length];
    }
    function formatDate(d) {
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    }
    function formatTime(d) {
        var h = d.getHours();
        var m = String(d.getMinutes()).padStart(2, '0');
        var ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return h + ':' + m + ' ' + ap;
    }
    function relative(item) {
        var when = parseWhen(item.scheduled_at);
        if (!when) return { text: '', cls: '' };
        if (derived(item) === 'completed') return { text: 'Completed', cls: '' };
        var now = new Date();
        var today = startOfDay(now);
        var day = startOfDay(when);
        var diffDays = Math.round((day.getTime() - today.getTime()) / 86400000);
        if (diffDays < 0) return { text: 'Overdue by ' + Math.abs(diffDays) + (Math.abs(diffDays) === 1 ? ' day' : ' days'), cls: 'is-late' };
        if (diffDays === 1) return { text: 'Tomorrow', cls: '' };
        if (diffDays > 1) return { text: 'In ' + diffDays + ' days', cls: '' };
        var mins = Math.round((when.getTime() - now.getTime()) / 60000);
        if (mins < -1) return { text: 'Overdue by ' + Math.ceil(Math.abs(mins) / 60) + ' hours', cls: 'is-late' };
        if (mins <= 1) return { text: 'Now', cls: 'is-soon' };
        if (mins < 60) return { text: 'In ' + mins + ' min', cls: 'is-soon' };
        return { text: 'In ' + Math.round(mins / 60) + (Math.round(mins / 60) === 1 ? ' hour' : ' hours'), cls: 'is-soon' };
    }
    function phoneHref(item) {
        var digits = String(item.client_phone || '').replace(/[^\d+]/g, '');
        if (item.item_type === 'email' && item.client_email) return 'mailto:' + item.client_email;
        if (item.item_type === 'whatsapp' && digits) return 'https://wa.me/' + digits.replace(/^\+/, '');
        if (digits) return 'tel:' + digits;
        return '';
    }
    function counts() {
        var list = kindItems();
        return {
            all: list.length,
            today: list.filter(function (i) { return inBucket(i, 'today'); }).length,
            overdue: list.filter(function (i) { return inBucket(i, 'overdue'); }).length,
            upcoming: list.filter(function (i) { return inBucket(i, 'upcoming'); }).length,
            completed: list.filter(function (i) { return inBucket(i, 'completed'); }).length
        };
    }
    function renderStats() {
        var c = counts();
        var noun = state.kind === 'task' ? 'Tasks' : 'Follow-ups';
        var cards = [
            ['is-today', 'fa-phone-alt', c.today, "Today's " + noun, 'Scheduled for today', 'today'],
            ['is-overdue', 'fa-exclamation-triangle', c.overdue, 'Overdue', 'Need attention', 'overdue'],
            ['is-upcoming', 'fa-calendar-alt', c.upcoming, 'Upcoming', 'Next 7 days', 'upcoming'],
            ['is-done', 'fa-check-circle', c.completed, 'Completed', 'This week', 'completed'],
            ['is-total', 'fa-list', c.all, 'Total ' + noun, 'All ' + noun.toLowerCase(), 'all']
        ];
        $('#tfStats').html(cards.map(function (card) {
            return '<button type="button" class="tf-stat ' + card[0] + '" data-bucket="' + card[5] + '">' +
                '<div class="tf-stat-ico"><i class="fas ' + card[1] + '"></i></div><b>' + card[2] + '</b><span>' + esc(card[3]) + '</span><small>' + esc(card[4]) + '</small></button>';
        }).join(''));
        var buckets = [
            ['all', 'fa-list', 'All', c.all],
            ['today', 'fa-sun', 'Today', c.today],
            ['overdue', 'fa-trash-alt', 'Overdue', c.overdue],
            ['upcoming', 'fa-calendar-day', 'Upcoming', c.upcoming],
            ['completed', 'fa-check', 'Completed', c.completed]
        ];
        $('#tfBuckets').html(buckets.map(function (b) {
            return '<button type="button" class="tf-bucket' + (state.bucket === b[0] ? ' is-active' : '') + '" data-bucket="' + b[0] + '"><i class="fas ' + b[1] + '"></i> ' + b[2] + ' (' + b[3] + ')</button>';
        }).join(''));
    }
    function activeTypeKeys() {
        return state.kind === 'task' ? Object.keys(boot.taskTypes || {}) : typeOrder;
    }
    function renderTypeFilters() {
        var map = state.kind === 'task' ? (boot.taskTypes || {}) : types;
        var html = '<option value="">' + (state.kind === 'task' ? 'Task Type' : 'Follow-up Type') + '</option>';
        activeTypeKeys().forEach(function (key) {
            html += '<option value="' + key + '">' + esc(map[key] || key) + '</option>';
        });
        var current = $('#tfType').val();
        $('#tfType').html(html).val(current);
        var assign = '<option value="">Assigned To</option>';
        (boot.assignees || []).forEach(function (name) {
            assign += '<option value="' + esc(name) + '">' + esc(name) + '</option>';
        });
        var curA = $('#tfAssignee').val();
        $('#tfAssignee').html(assign).val(curA);
    }
    function renderRows() {
        var rows = filtered();
        var pages = Math.max(1, Math.ceil(rows.length / state.pageSize));
        if (state.page > pages) state.page = pages;
        var start = (state.page - 1) * state.pageSize;
        var slice = rows.slice(start, start + state.pageSize);
        if (!slice.length) {
            $('#tfRows').html('<tr><td class="tf-empty" colspan="10">No ' + (state.kind === 'task' ? 'tasks' : 'follow-ups') + ' match these filters.</td></tr>');
        } else {
            $('#tfRows').html(slice.map(function (item, idx) {
                var when = parseWhen(item.scheduled_at);
                var rel = relative(item);
                var status = derived(item);
                var href = phoneHref(item);
                var actIcon = iconFor(item);
                var note = displayNote(item);
                var selected = !!state.selected[item.id];
                return '<tr>' +
                    '<td><input type="checkbox" class="tf-check" data-id="' + item.id + '"' + (selected ? ' checked' : '') + '></td>' +
                    '<td>' + (start + idx + 1) + '</td>' +
                    '<td><div class="tf-client"><span class="tf-avatar" style="background:' + colorFor(displayName(item)) + '">' + esc(initials(displayName(item))) + '</span><div><b>' + esc(displayName(item)) + '</b><small>' + esc(item.client_phone || '—') + '</small></div></div></td>' +
                    '<td><span class="tf-type is-' + esc(item.item_type) + '"><i class="' + actIcon + '"></i> ' + esc(labelFor(item)) + '</span></td>' +
                    '<td><div class="tf-purpose" title="' + esc(note) + '">' + esc(note || '—') + '</div></td>' +
                    '<td><div class="tf-when"><b>' + (when ? formatDate(when) : '—') + '</b><small>' + (when ? formatTime(when) : '') + '</small><div class="tf-rel ' + rel.cls + '">' + esc(rel.text) + '</div></div></td>' +
                    '<td><span class="tf-pri is-' + esc(item.priority) + '"><i class="fas fa-flag"></i> ' + esc(item.priority.charAt(0).toUpperCase() + item.priority.slice(1)) + '</span></td>' +
                    '<td><span class="tf-status is-' + status + '">' + esc(status.charAt(0).toUpperCase() + status.slice(1)) + '</span></td>' +
                    '<td><span class="tf-assignee"><i>' + esc(initials(item.assigned_name || '?')) + '</i> ' + esc(item.assigned_name || 'Unassigned') + '</span></td>' +
                    '<td><div class="tf-acts">' +
                    (href ? '<a class="tf-act" href="' + esc(href) + '" target="_blank" rel="noopener" title="Contact"><i class="' + actIcon + '"></i></a>' : '') +
                    '<button type="button" class="tf-act tf-edit" data-id="' + item.id + '" title="Edit"><i class="fas fa-pen"></i></button>' +
                    '<button type="button" class="tf-act tf-more" data-id="' + item.id + '" title="More"><i class="fas fa-ellipsis-v"></i></button>' +
                    '</div></td></tr>';
            }).join(''));
        }
        var fromN = rows.length ? start + 1 : 0;
        var toN = Math.min(rows.length, start + state.pageSize);
        $('#tfRange').text('Showing ' + fromN + ' to ' + toN + ' of ' + rows.length + ' entries');
        var pageHtml = '';
        pageHtml += '<button type="button" data-page="' + Math.max(1, state.page - 1) + '" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>';
        for (var p = 1; p <= pages && p <= 6; p++) {
            pageHtml += '<button type="button" data-page="' + p + '"' + (p === state.page ? ' class="is-active"' : '') + '>' + p + '</button>';
        }
        pageHtml += '<button type="button" data-page="' + Math.min(pages, state.page + 1) + '" aria-label="Next"><i class="fas fa-chevron-right"></i></button>';
        $('#tfPages').html(pageHtml);
        $('#tfCheckAll').prop('checked', slice.length > 0 && slice.every(function (item) { return state.selected[item.id]; }));
    }
    function renderCalendarBoard() {
        var month = state.month;
        var first = new Date(month.getFullYear(), month.getMonth(), 1);
        var start = new Date(first);
        start.setDate(1 - first.getDay());
        var html = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(function (d) {
            return '<div class="tf-cal-dow">' + d + '</div>';
        }).join('');
        for (var i = 0; i < 42; i++) {
            var day = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
            var mine = kindItems().filter(function (item) {
                var when = parseWhen(item.scheduled_at);
                return when && sameDay(when, day);
            });
            html += '<div class="tf-cal-cell' + (day.getMonth() !== month.getMonth() ? ' is-out' : '') + (sameDay(day, new Date()) ? ' is-today' : '') + '"><b>' + day.getDate() + '</b>';
            mine.slice(0, 3).forEach(function (item) {
                html += '<span class="tf-cal-item">' + esc(displayName(item)) + '</span>';
            });
            if (mine.length > 3) html += '<span class="tf-cal-item">+' + (mine.length - 3) + ' more</span>';
            html += '</div>';
        }
        $('#tfCalendar').html('<div class="tf-cal-board">' + html + '</div>');
    }
    function renderMini() {
        var month = state.month;
        var label = month.toLocaleString('en-US', { month: 'long', year: 'numeric' });
        $('#tfCalLabel').text(label);
        var first = new Date(month.getFullYear(), month.getMonth(), 1);
        var start = new Date(first);
        start.setDate(1 - first.getDay());
        var html = '<thead><tr>' + ['S', 'M', 'T', 'W', 'T', 'F', 'S'].map(function (d) { return '<th>' + d + '</th>'; }).join('') + '</tr></thead><tbody>';
        for (var w = 0; w < 6; w++) {
            html += '<tr>';
            for (var d = 0; d < 7; d++) {
                var day = new Date(start.getFullYear(), start.getMonth(), start.getDate() + (w * 7 + d));
                if (day.getMonth() !== month.getMonth()) {
                    html += '<td></td>';
                    continue;
                }
                var mine = kindItems().filter(function (item) {
                    var when = parseWhen(item.scheduled_at);
                    return when && sameDay(when, day);
                });
                var cls = 'tf-day';
                if (sameDay(day, new Date())) cls += ' is-today';
                if (mine.length) {
                    cls += ' has-dot';
                    if (mine.some(function (item) { return derived(item) === 'overdue'; })) cls += ' is-late';
                    else if (mine.every(function (item) { return derived(item) === 'completed'; })) cls += ' is-done';
                }
                html += '<td><button type="button" class="' + cls + '" data-day="' + day.getFullYear() + '-' + String(day.getMonth() + 1).padStart(2, '0') + '-' + String(day.getDate()).padStart(2, '0') + '">' + day.getDate() + '</button></td>';
            }
            html += '</tr>';
        }
        html += '</tbody>';
        $('#tfMini').html(html);
    }
    function renderSchedule() {
        var today = new Date();
        var list = kindItems().filter(function (item) {
            var when = parseWhen(item.scheduled_at);
            return when && sameDay(when, today);
        }).sort(function (a, b) { return String(a.scheduled_at).localeCompare(String(b.scheduled_at)); });
        $('#tfTodayCount').text(String(list.length));
        if (!list.length) {
            $('#tfSchedule').html('<div class="tf-note">Nothing scheduled today.</div>');
            return;
        }
        $('#tfSchedule').html(list.slice(0, 6).map(function (item) {
            var when = parseWhen(item.scheduled_at);
            var note = displayNote(item);
            return '<div class="tf-sched"><time>' + (when ? formatTime(when) : '') + '</time><div><b>' + esc(displayName(item)) + '</b><span>' + esc(labelFor(item)) + (note ? ' · ' + note : '') + '</span></div></div>';
        }).join(''));
    }
    function renderTypes() {
        var list = kindItems();
        $('#tfTypeTitle').text(state.kind === 'task' ? 'Task Types' : 'Follow-up Types');
        $('#tfCalTitle').text(state.kind === 'task' ? 'Task Calendar' : 'Follow-up Calendar');
        var dots = { call: '#e11d48', whatsapp: '#16a34a', email: '#2563eb', meeting: '#7c3aed', other: '#94a3b8', general: '#e11d48', prepare_quotation: '#e11d48', check_payment: '#16a34a', send_itinerary: '#2563eb', collect_documents: '#0d9488', call_supplier: '#7c3aed' };
        var map = state.kind === 'task' ? (boot.taskTypes || {}) : types;
        $('#tfTypeList').html(activeTypeKeys().map(function (key) {
            var n = list.filter(function (item) { return item.item_type === key; }).length;
            return '<div class="tf-type-row"><span><i class="tf-dot" style="background:' + (dots[key] || '#94a3b8') + '"></i> ' + esc(map[key] || key) + '</span><span class="tf-count-pill">' + n + '</span></div>';
        }).join(''));
    }
    function render() {
        renderStats();
        renderSchedule();
        renderTypes();
        renderMini();
        if (state.view === 'calendar') {
            $('#tfList').attr('hidden', true);
            $('#tfCalendar').removeAttr('hidden');
            renderCalendarBoard();
        } else {
            $('#tfCalendar').attr('hidden', true);
            $('#tfList').removeAttr('hidden');
            renderRows();
        }
        var dateLabel = state.kind === 'task' ? 'Task Date & Time' : 'Follow-up Date & Time';
        $('.tf-table thead th').eq(5).text(dateLabel);
    }
    function upsert(item) {
        var found = false;
        items = items.map(function (row) {
            if (row.id === item.id) { found = true; return item; }
            return row;
        });
        if (!found) items.push(item);
    }
    function todayInput() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function dateInput(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function timeValue(d) {
        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }
    function findLead(id) {
        id = parseInt(id, 10) || 0;
        return (boot.leads || []).filter(function (lead) { return lead.id === id; })[0] || null;
    }
    function findRelation(value) {
        var parts = String(value || '').split(':');
        var kind = parts[0];
        var id = parseInt(parts[1], 10) || 0;
        return (boot.relations || []).filter(function (row) { return row.kind === kind && row.id === id; })[0] || null;
    }
    function paxText(adults, children) {
        var bits = [];
        adults = parseInt(adults, 10) || 0;
        children = parseInt(children, 10) || 0;
        if (adults > 0) bits.push(adults + (adults === 1 ? ' Adult' : ' Adults'));
        if (children > 0) bits.push(children + (children === 1 ? ' Child' : ' Children'));
        return bits.join(', ');
    }
    function clientCard(person) {
        if (!person || !(person.name || person.guest)) {
            return '<div class="tf-client-empty">Select a client or lead to view details here.</div>';
        }
        var name = person.name || person.guest;
        var phone = person.phone || '';
        var digits = String(phone).replace(/[^\d]/g, '');
        var wa = digits ? '<a class="wa" href="https://wa.me/' + digits + '" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i></a>' : '';
        var pax = paxText(person.adults, person.children);
        var html = '<div class="tf-client-card"><div class="tf-client-top"><span class="tf-avatar" style="background:' + colorFor(name) + '">' + esc(initials(name)) + '</span><div><b>' + esc(name) + (person.star ? ' <i class="fas fa-star tf-star"></i>' : '') + '</b></div></div>';
        if (phone) html += '<div class="tf-client-line"><i class="fas fa-phone-alt"></i> ' + esc(phone) + wa + '</div>';
        if (person.email) html += '<div class="tf-client-line"><i class="fas fa-envelope"></i> ' + esc(person.email) + '</div>';
        if (person.city) html += '<div class="tf-client-line"><i class="fas fa-map-marker-alt"></i> ' + esc(person.city) + '</div>';
        html += '<div class="tf-client-meta">';
        if (pax) html += '<div class="tf-client-line"><i class="fas fa-user-friends"></i> ' + esc(pax) + '</div>';
        if (person.destination) html += '<div class="tf-client-line"><i class="fas fa-plane"></i> ' + esc(person.destination) + '</div>';
        if (person.date) html += '<div class="tf-client-line"><i class="far fa-calendar-alt"></i> ' + esc(person.date) + '</div>';
        html += '</div></div>';
        return html;
    }
    function selectedPerson() {
        var lead = findLead($('#tfLeadId').val());
        if (lead) return lead;
        var rel = findRelation($('#tfRelated').val());
        if (!rel) return null;
        var fromLead = findLead(rel.lead_id);
        if (fromLead) return fromLead;
        return { name: rel.guest, phone: rel.phone, email: rel.email, destination: rel.destination, date: rel.date, adults: rel.adults, children: rel.children };
    }
    function paintClient() {
        var card = clientCard(selectedPerson());
        $('#tfClientCard').html(card);
        var taskEmpty = '<div class="tf-client-empty">Select a lead, quotation, or tour to view client details here.</div>';
        $('#tfTaskClient').html(selectedPerson() ? card : taskEmpty);
    }
    function paintFiles() {
        var html = '';
        keptFiles.forEach(function (file, idx) {
            html += '<li><a href="crm/uploads/work_items/' + encodeURIComponent(file.file) + '" target="_blank" rel="noopener">' + esc(file.name) + '</a><button type="button" data-kept="' + idx + '">Remove</button></li>';
        });
        newFiles.forEach(function (file, idx) {
            html += '<li><span>' + esc(file.name) + '</span><button type="button" data-new="' + idx + '">Remove</button></li>';
        });
        $('#tfFileList').html(html);
    }
    function setMode(kind, fresh) {
        kind = kind === 'task' ? 'task' : 'followup';
        $('#tfKind').val(kind);
        $('#tfFields').attr('data-mode', kind).toggleClass('is-task', kind === 'task');
        $('.tf-kind-switch button').removeClass('is-on').filter('[data-form-kind="' + kind + '"]').addClass('is-on');
        $('#tfFollowSide').prop('hidden', kind === 'task');
        $('#tfTaskSide').prop('hidden', kind !== 'task');
        var editing = !!$('#tfId').val();
        if (kind === 'task') {
            $('#tfModalTitle').text(editing ? 'Edit Task' : 'Create Task');
            $('#tfSave span').text(editing ? 'Save Task' : 'Create Task');
            $('#tfTitle').attr('placeholder', 'Enter task title');
            $('#tfNotes').attr('placeholder', 'Add task details, checklist or instructions...');
            $('#tfDateLabel').html('Start Date <em>*</em>');
            $('#tfTimeLabel').text('Time (Optional)');
            $('#tfPriorityLabel').html('Priority <em>*</em>');
            $('#tfStatusLabel').html('Status <em>*</em>');
            $('#tfAssignLabel').html('Assign To <em>*</em>');
            $('#tfReminderLabel').text('Reminder (Optional)');
            $('#tfRelatedLabel').text('Related To (Optional)');
            $('#tfRelated option[value=""]').text('Select lead / quotation / booking / tour...');
            $('#tfTime option[value=""]').prop('hidden', false);
        } else {
            $('#tfModalTitle').text(editing ? 'Edit Follow-up' : 'Create Task / Follow-up');
            $('#tfSave span').text('Save Follow-up');
            $('#tfTitle').attr('placeholder', 'Enter follow-up title');
            $('#tfNotes').attr('placeholder', 'Add notes, discussion points, or next steps...');
            $('#tfDateLabel').html('Follow-up Date <em>*</em>');
            $('#tfTimeLabel').html('Time <em>*</em>');
            $('#tfPriorityLabel').text('Priority');
            $('#tfStatusLabel').text('Status');
            $('#tfAssignLabel').text('Assign To');
            $('#tfReminderLabel').text('Reminder');
            $('#tfRelatedLabel').text('Related To (Optional)');
            $('#tfRelated option[value=""]').text('Select quotation / booking / tour...');
            if (!$('#tfTime').val()) $('#tfTime').val('11:00');
            $('#tfTime option[value=""]').prop('hidden', true);
        }
        if (fresh) $('#tfAnother').prop('checked', kind === 'followup');
    }
    function renderClientMenu(q) {
        q = $.trim(q || '').toLowerCase();
        var rows = (boot.leads || []).filter(function (lead) {
            if (!q) return true;
            return (lead.name + ' ' + lead.phone + ' ' + lead.email).toLowerCase().indexOf(q) !== -1;
        }).slice(0, 12);
        if (!rows.length) {
            $('#tfClientMenu').html('<button type="button" disabled>No matching client</button>').prop('hidden', false);
            return;
        }
        $('#tfClientMenu').html(rows.map(function (lead) {
            return '<button type="button" data-lead="' + lead.id + '">' + esc(lead.name) + '<small>' + esc([lead.phone, lead.email].filter(Boolean).join(' · ')) + '</small></button>';
        }).join('')).prop('hidden', false);
    }
    function applyTemplate(key) {
        var label = (boot.taskTypes || {})[key] || '';
        if (!label || key === 'general') label = key === 'other' ? 'Other Task' : label;
        if (key === 'other') label = 'Other Task';
        $('#tfTaskType').val(key && (boot.taskTypes || {})[key] ? key : 'general');
        if (label) $('#tfTitle').val(label);
        setMode('task', false);
    }
    function openForm(kind, item) {
        item = item || {};
        var creating = !item.id;
        $('#tfId').val(item.id || '');
        $('#tfLeadId').val(item.lead_id || '');
        $('#tfClient').val(item.client_name || '');
        $('#tfPhone').val(item.client_phone || '');
        $('#tfEmail').val(item.client_email || '');
        $('#tfTitle').val(item.title || (item.notes ? '' : (item.purpose || '')));
        $('#tfNotes').val(item.notes || '');
        $('#tfNoteCount').text(String(($('#tfNotes').val() || '').length));
        $('#tfItemPriority').val(item.priority || 'medium');
        $('#tfItemStatus').val(item.status === 'completed' ? 'completed' : 'pending');
        $('#tfAssign').val(item.assigned_name || boot.self || '');
        $('#tfReminder').val(item.reminder || '30m');
        var relVal = item.related_kind && item.related_id ? (item.related_kind + ':' + item.related_id) : '';
        $('#tfRelated').val(relVal);
        var when = parseWhen(item.scheduled_at);
        var start = parseWhen(item.start_at);
        $('#tfDate').val(creating ? todayInput() : (item.kind === 'task' ? (start ? dateInput(start) : '') : (when ? dateInput(when) : '')));
        $('#tfDue').val(when ? dateInput(when) : (creating ? todayInput() : ''));
        if (creating) $('#tfDate').val(todayInput());
        var clock = when ? timeValue(when) : '11:00';
        if (when && !$('#tfTime option[value="' + clock + '"]').length) {
            $('#tfTime').append('<option value="' + clock + '">' + formatTime(when) + '</option>');
        }
        $('#tfTime').val(clock);
        var followType = item.item_type && types[item.item_type] ? item.item_type : 'call';
        $('#tfItemType').val(item.kind === 'task' ? 'call' : followType);
        $('.tf-pill').removeClass('is-on').filter('[data-type="' + $('#tfItemType').val() + '"]').addClass('is-on');
        var taskType = item.kind === 'task' && (boot.taskTypes || {})[item.item_type] ? item.item_type : 'general';
        $('#tfTaskType').val(taskType);
        keptFiles = (item.attachments || []).slice();
        newFiles = [];
        paintFiles();
        var recur = item.recurring || '';
        $('#tfRecur').toggleClass('is-on', !!recur).attr('aria-pressed', recur ? 'true' : 'false');
        $('#tfRecurEvery').prop('disabled', !recur).val(recur || 'daily');
        paintPriFlag();
        setMode(item.kind || kind || 'followup', creating);
        paintClient();
        $('#tfClientMenu').prop('hidden', true);
        $('#tfModal').modal('show');
    }
    function paintPriFlag() {
        var color = { high: '#e11d48', medium: '#f59e0b', low: '#16a34a' }[$('#tfItemPriority').val()] || '#f59e0b';
        $('#tfPriFlag').css('color', color);
    }
    function fillFormChoices() {
        var times = '<option value="">No time</option>';
        for (var h = 0; h < 24; h++) {
            [0, 30].forEach(function (m) {
                var hh = h % 12 || 12;
                var val = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
                times += '<option value="' + val + '">' + hh + ':' + String(m).padStart(2, '0') + (h < 12 ? ' AM' : ' PM') + '</option>';
            });
        }
        $('#tfTime').html(times).val('11:00');
        var tasks = '';
        Object.keys(boot.taskTypes || {}).forEach(function (key) {
            tasks += '<option value="' + key + '">' + esc(boot.taskTypes[key]) + '</option>';
        });
        $('#tfTaskType').html(tasks);
        var assign = '<option value="">Unassigned</option>';
        (boot.assignees || []).forEach(function (name) { assign += '<option value="' + esc(name) + '">' + esc(name) + '</option>'; });
        $('#tfAssign').html(assign);
        var rel = '<option value="">Select quotation / booking / tour...</option>';
        (boot.relations || []).forEach(function (row) {
            rel += '<option value="' + row.kind + ':' + row.id + '">' + esc(row.label) + '</option>';
        });
        $('#tfRelated').html(rel);
        var tpl = [
            ['prepare_quotation', 'far fa-file-alt', 'Prepare Quotation'],
            ['check_payment', 'fas fa-rupee-sign', 'Check Payment Status'],
            ['send_itinerary', 'fas fa-plane', 'Send Itinerary'],
            ['collect_documents', 'fas fa-file-download', 'Collect Documents'],
            ['call_supplier', 'fas fa-phone-alt', 'Call Supplier'],
            ['other', 'fas fa-ellipsis-h', 'Other Task']
        ];
        $('#tfTemplates').html(tpl.map(function (row) {
            return '<button type="button" class="tf-tpl" data-template="' + row[0] + '"><i class="' + row[1] + ' ic" style="color:' + (taskIconColor[row[0]] || '#64748b') + '"></i> ' + row[2] + ' <span><i class="fas fa-chevron-right"></i></span></button>';
        }).join(''));
    }
    function post(data) {
        if (window.FormData && data instanceof FormData) {
            return $.ajax({ url: 'crm/ajax/work_items.php', method: 'POST', dataType: 'json', data: data, processData: false, contentType: false });
        }
        return $.ajax({ url: 'crm/ajax/work_items.php', method: 'POST', dataType: 'json', data: data });
    }

    fillFormChoices();
    renderTypeFilters();
    render();

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.tf-menu').length) $('.tf-pop').removeClass('is-open');
        if (!$(e.target).closest('.tf-acts').length) $('.tf-row-menu').remove();
    });
    $('#tfCreateBtn').on('click', function (e) { e.stopPropagation(); $('#tfExportPop').removeClass('is-open'); $('#tfCreatePop').toggleClass('is-open'); });
    $('#tfExportBtn').on('click', function (e) { e.stopPropagation(); $('#tfCreatePop').removeClass('is-open'); $('#tfExportPop').toggleClass('is-open'); });
    $(document).on('click', '[data-create]', function () {
        $('.tf-pop').removeClass('is-open');
        openForm($(this).data('create'));
    });
    $('#tfClient').on('focus input', function () { renderClientMenu($(this).val()); });
    $('#tfClient').on('input', function () {
        var typed = $(this).val();
        var match = (boot.leads || []).filter(function (lead) { return lead.name === typed; })[0];
        if (!match) {
            $('#tfLeadId').val('');
            $('#tfPhone').val('');
            $('#tfEmail').val('');
        }
        paintClient();
    });
    $(document).on('click', '#tfClientMenu [data-lead]', function () {
        var lead = findLead($(this).data('lead'));
        if (!lead) return;
        $('#tfLeadId').val(lead.id);
        $('#tfClient').val(lead.name);
        $('#tfPhone').val(lead.phone || '');
        $('#tfEmail').val(lead.email || '');
        $('#tfClientMenu').prop('hidden', true);
        paintClient();
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.tf-combo').length) $('#tfClientMenu').prop('hidden', true);
    });
    $('.tf-pill').on('click', function () {
        $('.tf-pill').removeClass('is-on');
        $(this).addClass('is-on');
        $('#tfItemType').val($(this).data('type'));
    });
    $('[data-form-kind]').on('click', function () { setMode($(this).data('form-kind'), false); });
    $('#tfNotes').on('input', function () { $('#tfNoteCount').text(String(($(this).val() || '').length)); });
    $('#tfItemPriority').on('change', paintPriFlag);
    $('#tfRelated').on('change', function () {
        var rel = findRelation($(this).val());
        if (rel && !$('#tfLeadId').val() && rel.lead_id) {
            var lead = findLead(rel.lead_id);
            if (lead && $('#tfKind').val() === 'followup' && !$('#tfClient').val()) {
                $('#tfLeadId').val(lead.id);
                $('#tfClient').val(lead.name);
                $('#tfPhone').val(lead.phone || '');
                $('#tfEmail').val(lead.email || '');
            }
            if (lead && $('#tfKind').val() === 'task') {
                $('#tfLeadId').val(lead.id);
                $('#tfPhone').val(lead.phone || '');
                $('#tfEmail').val(lead.email || '');
            }
        }
        paintClient();
    });
    $('#tfDrop').on('click', function () { $('#tfFiles').trigger('click'); });
    $('#tfDrop').on('dragover', function (e) { e.preventDefault(); $(this).addClass('is-hot'); });
    $('#tfDrop').on('dragleave drop', function (e) {
        e.preventDefault();
        $(this).removeClass('is-hot');
        if (e.type === 'drop' && e.originalEvent.dataTransfer) addUploadFiles(e.originalEvent.dataTransfer.files);
    });
    $('#tfFiles').on('change', function () { addUploadFiles(this.files); this.value = ''; });
    function addUploadFiles(list) {
        Array.prototype.forEach.call(list || [], function (file) {
            var ok = /\.(pdf|doc|docx|xls|xlsx|jpg|jpeg|png)$/i.test(file.name);
            if (!ok || file.size > 5 * 1024 * 1024) return;
            if (keptFiles.length + newFiles.length >= 8) return;
            newFiles.push(file);
        });
        paintFiles();
    }
    $(document).on('click', '#tfFileList [data-kept]', function () {
        keptFiles.splice(parseInt($(this).data('kept'), 10), 1);
        paintFiles();
    });
    $(document).on('click', '#tfFileList [data-new]', function () {
        newFiles.splice(parseInt($(this).data('new'), 10), 1);
        paintFiles();
    });
    $('#tfRecur').on('click', function () {
        var on = !$(this).hasClass('is-on');
        $(this).toggleClass('is-on', on).attr('aria-pressed', on ? 'true' : 'false');
        $('#tfRecurEvery').prop('disabled', !on);
    });
    $(document).on('click', '[data-template]', function () { applyTemplate($(this).data('template')); });
    $('#tfUseTemplate').on('click', function () {
        var first = $('#tfTemplates .tf-tpl').first();
        if (first.length) applyTemplate(first.data('template'));
    });
    $('#tfForm').on('submit', function (e) {
        e.preventDefault();
        var kind = $('#tfKind').val() === 'task' ? 'task' : 'followup';
        var title = $.trim($('#tfTitle').val());
        var date = kind === 'task' ? $('#tfDue').val() : $('#tfDate').val();
        var start = $('#tfDate').val();
        var time = $('#tfTime').val() || (kind === 'task' ? '00:00' : '');
        if (!title) { alert(kind === 'task' ? 'Task title is required.' : 'Follow-up title is required.'); return; }
        if (kind === 'followup' && !$.trim($('#tfClient').val())) { alert('Client or lead is required.'); return; }
        if (!date || (kind === 'followup' && !time)) { alert(kind === 'task' ? 'Due date is required.' : 'Follow-up date and time are required.'); return; }
        if (kind === 'task' && !start) { alert('Start date is required.'); return; }
        var rel = findRelation($('#tfRelated').val());
        var fd = new FormData();
        fd.append('action', 'save');
        fd.append('id', $('#tfId').val());
        fd.append('kind', kind);
        fd.append('lead_id', $('#tfLeadId').val() || (rel ? rel.lead_id : ''));
        fd.append('client_name', kind === 'task' ? ($('#tfClient').val() || (selectedPerson() && selectedPerson().name) || '') : $('#tfClient').val());
        fd.append('client_phone', $('#tfPhone').val());
        fd.append('client_email', $('#tfEmail').val());
        fd.append('item_type', kind === 'task' ? $('#tfTaskType').val() : $('#tfItemType').val());
        fd.append('title', title);
        fd.append('notes', $('#tfNotes').val());
        fd.append('scheduled_at', date + ' ' + (time || '00:00'));
        fd.append('start_at', kind === 'task' ? (start + ' 00:00') : '');
        fd.append('priority', $('#tfItemPriority').val());
        fd.append('status', $('#tfItemStatus').val());
        fd.append('assigned_name', $('#tfAssign').val());
        fd.append('reminder', $('#tfReminder').val());
        fd.append('related_kind', rel ? rel.kind : '');
        fd.append('related_id', rel ? rel.id : '');
        fd.append('related_label', rel ? rel.label : '');
        fd.append('recurring', kind === 'task' && $('#tfRecur').hasClass('is-on') ? $('#tfRecurEvery').val() : '');
        fd.append('keep_attachments', JSON.stringify(keptFiles));
        newFiles.forEach(function (file) { fd.append('attachments[]', file); });
        var $btn = $('#tfSave').prop('disabled', true);
        post(fd).done(function (res) {
            if (!res || !res.success || !res.item) { alert((res && res.message) || 'Could not save.'); return; }
            upsert(res.item);
            state.kind = res.item.kind;
            $('.tf-tab').removeClass('is-active').filter('[data-kind="' + state.kind + '"]').addClass('is-active');
            renderTypeFilters();
            render();
            if ($('#tfAnother').prop('checked')) {
                openForm(kind);
                return;
            }
            $('#tfModal').modal('hide');
        }).fail(function () { alert('Could not save.'); })
          .always(function () { $btn.prop('disabled', false); });
    });
    $(document).on('click', '.tf-tab', function () {
        state.kind = $(this).data('kind');
        state.page = 1;
        $('.tf-tab').removeClass('is-active');
        $(this).addClass('is-active');
        renderTypeFilters();
        render();
    });
    $(document).on('click', '[data-bucket]', function () {
        state.bucket = $(this).data('bucket');
        state.page = 1;
        state.view = 'list';
        $('.tf-view button').removeClass('is-active').filter('[data-view="list"]').addClass('is-active');
        render();
    });
    $('.tf-view button').on('click', function () {
        state.view = $(this).data('view');
        $('.tf-view button').removeClass('is-active');
        $(this).addClass('is-active');
        render();
    });
    $('#tfViewCalendar').on('click', function () {
        state.view = 'calendar';
        $('.tf-view button').removeClass('is-active').filter('[data-view="calendar"]').addClass('is-active');
        render();
        var el = document.getElementById('tfCalendar');
        if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    $('#tfSearch, #tfType, #tfStatus, #tfPriority, #tfAssignee, #tfFrom, #tfTo').on('input change', function () {
        state.page = 1;
        render();
    });
    $('#tfReset').on('click', function () {
        $('#tfSearch, #tfFrom, #tfTo').val('');
        $('#tfType, #tfStatus, #tfPriority, #tfAssignee').val('');
        state.bucket = 'all';
        state.page = 1;
        render();
    });
    $(document).on('click', '#tfPages [data-page]', function () {
        state.page = parseInt($(this).data('page'), 10) || 1;
        renderRows();
    });
    $('#tfCheckAll').on('change', function () {
        var on = $(this).prop('checked');
        filtered().slice((state.page - 1) * state.pageSize, state.page * state.pageSize).forEach(function (item) {
            if (on) state.selected[item.id] = true;
            else delete state.selected[item.id];
        });
        renderRows();
    });
    $(document).on('change', '.tf-check', function () {
        var id = $(this).data('id');
        if ($(this).prop('checked')) state.selected[id] = true;
        else delete state.selected[id];
    });
    $('#tfBulkApply').on('click', function () {
        var bulk = $('#tfBulk').val();
        var ids = Object.keys(state.selected);
        if (!bulk || !ids.length) return;
        if (bulk === 'delete' && !window.confirm('Delete the selected items?')) return;
        post({ action: 'bulk', bulk: bulk, ids: ids }).done(function (res) {
            if (res && res.items) items = res.items;
            state.selected = {};
            $('#tfBulk').val('');
            render();
        });
    });
    $(document).on('click', '.tf-edit', function () {
        var id = $(this).data('id');
        var item = items.filter(function (row) { return row.id === id; })[0];
        if (item) openForm(item.kind, item);
    });
    $(document).on('click', '.tf-more', function (e) {
        e.stopPropagation();
        $('.tf-row-menu').remove();
        var id = $(this).data('id');
        var item = items.filter(function (row) { return row.id === id; })[0];
        if (!item) return;
        var done = item.status === 'completed';
        var menu = $('<div class="tf-row-menu"></div>');
        menu.append('<button type="button" data-act="edit">Edit</button>');
        menu.append('<button type="button" data-act="status">' + (done ? 'Reopen' : 'Mark completed') + '</button>');
        menu.append('<button type="button" data-act="delete">Delete</button>');
        $(this).closest('.tf-acts').append(menu);
        menu.on('click', 'button', function () {
            var act = $(this).data('act');
            menu.remove();
            if (act === 'edit') openForm(item.kind, item);
            if (act === 'status') {
                post({ action: 'status', id: id, status: done ? 'pending' : 'completed' }).done(function (res) {
                    if (res && res.item) upsert(res.item);
                    render();
                });
            }
            if (act === 'delete' && window.confirm('Delete this item?')) {
                post({ action: 'delete', ids: [id] }).done(function (res) {
                    if (res && res.items) items = res.items;
                    delete state.selected[id];
                    render();
                });
            }
        });
    });
    $('#tfCalPrev').on('click', function () { state.month = new Date(state.month.getFullYear(), state.month.getMonth() - 1, 1); renderMini(); if (state.view === 'calendar') renderCalendarBoard(); });
    $('#tfCalNext').on('click', function () { state.month = new Date(state.month.getFullYear(), state.month.getMonth() + 1, 1); renderMini(); if (state.view === 'calendar') renderCalendarBoard(); });
    $(document).on('click', '.tf-day', function () {
        var day = $(this).data('day');
        $('#tfFrom').val(day);
        $('#tfTo').val(day);
        state.bucket = 'all';
        state.view = 'list';
        state.page = 1;
        $('.tf-view button').removeClass('is-active').filter('[data-view="list"]').addClass('is-active');
        render();
    });
    $('#tfViewToday, #tfQuickOverdue').on('click', function () {
        state.bucket = this.id === 'tfQuickOverdue' ? 'overdue' : 'today';
        state.view = 'list';
        state.page = 1;
        $('.tf-view button').removeClass('is-active').filter('[data-view="list"]').addClass('is-active');
        render();
    });
    $('#tfExportCsv, #tfQuickReport').on('click', function () {
        $('.tf-pop').removeClass('is-open');
        var rows = filtered();
        var lines = [['Kind', 'Client', 'Phone', 'Email', 'Type', 'Purpose', 'Scheduled', 'Priority', 'Status', 'Assigned']];
        rows.forEach(function (item) {
            lines.push([item.kind, displayName(item), item.client_phone, item.client_email, labelFor(item), displayNote(item), item.scheduled_at, item.priority, derived(item), item.assigned_name]);
        });
        var csv = lines.map(function (line) {
            return line.map(function (cell) {
                var text = String(cell == null ? '' : cell).replace(/"/g, '""');
                return '"' + text + '"';
            }).join(',');
        }).join('\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = (state.kind === 'task' ? 'tasks' : 'follow-ups') + '.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    });
})(jQuery);
</script>
</body>
</html>
