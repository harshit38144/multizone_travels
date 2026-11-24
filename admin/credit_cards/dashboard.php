<?php
/**
 * Credit Card Management Dashboard — UI prototype with sample data.
 */
session_start();
if (!isset($_SESSION['role']) || (string) $_SESSION['role'] !== '1') {
    header('Location: ../index.php');
    exit;
}

function ccInr($n): string
{
    $n = (float) $n;
    $neg = $n < 0;
    $n = abs($n);
    $int = (string) (int) round($n);
    $last3 = substr($int, -3);
    $rest = substr($int, 0, -3);
    if ($rest !== '') {
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $int = $rest . ',' . $last3;
    }
    return ($neg ? '-₹' : '₹') . $int;
}

function ccPts($n): string
{
    return number_format((int) $n);
}

$kpis = [
    ['label' => 'Total Credit Cards', 'value' => '10', 'sub' => '', 'icon' => 'fa-credit-card', 'tone' => 'blue'],
    ['label' => 'Total Credit Limit', 'value' => ccInr(1000000), 'sub' => '', 'icon' => 'fa-wallet', 'tone' => 'purple'],
    ['label' => 'Total Outstanding', 'value' => ccInr(245230), 'sub' => '', 'icon' => 'fa-chart-pie', 'tone' => 'orange'],
    ['label' => 'Total Payment Due', 'value' => ccInr(98450), 'sub' => 'This Month', 'icon' => 'fa-file-invoice-dollar', 'tone' => 'red'],
    ['label' => 'Due Within 7 Days', 'value' => ccInr(45230), 'sub' => '', 'icon' => 'fa-calendar-check', 'tone' => 'green'],
    ['label' => 'Overdue Payments', 'value' => ccInr(12430), 'sub' => '', 'icon' => 'fa-exclamation-triangle', 'tone' => 'rose'],
    ['label' => 'Total Reward Points', 'value' => ccPts(124560), 'sub' => '', 'icon' => 'fa-gift', 'tone' => 'teal'],
    ['label' => 'Available Credit', 'value' => ccInr(754770), 'sub' => '', 'icon' => 'fa-star', 'tone' => 'sky'],
];

$outstandingByCard = [
    ['name' => 'HDFC Regalia', 'amount' => 68500, 'color' => '#3b82f6'],
    ['name' => 'SBI Cashback', 'amount' => 45200, 'color' => '#22c55e'],
    ['name' => 'ICICI Amazon Pay', 'amount' => 38800, 'color' => '#f59e0b'],
    ['name' => 'Axis Ace', 'amount' => 32100, 'color' => '#ef4444'],
    ['name' => 'Amex Membership', 'amount' => 28400, 'color' => '#8b5cf6'],
    ['name' => 'Others', 'amount' => 32230, 'color' => '#94a3b8'],
];
$outstandingTotal = array_sum(array_column($outstandingByCard, 'amount'));

$monthlyTrend = [
    'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
    'bills' => [92000, 105000, 88000, 112000, 98000, 108000],
    'payments' => [85000, 98000, 90000, 100000, 95000, 102000],
];

$utilizationPct = 24.5;
$utilizationLimit = 1000000;
$utilizationUsed = 245230;

$alerts = [
    [
        'group' => 'Overdue',
        'count' => 1,
        'tone' => 'overdue',
        'items' => [
            ['name' => 'IndusInd Legend', 'meta' => 'Due 12 Mar 2026', 'amount' => 12450],
        ],
    ],
    [
        'group' => 'Due in Next 3 Days',
        'count' => 2,
        'tone' => 'soon',
        'items' => [
            ['name' => 'HDFC Regalia', 'meta' => 'Due 26 Mar 2026', 'amount' => 28500],
            ['name' => 'SBI Cashback', 'meta' => 'Due 27 Mar 2026', 'amount' => 16730],
        ],
    ],
    [
        'group' => 'Paid',
        'count' => 4,
        'tone' => 'paid',
        'items' => [
            ['name' => 'ICICI Amazon Pay', 'meta' => 'Paid 18 Mar 2026', 'amount' => 22100],
        ],
    ],
];

$cardRows = [
    ['name' => 'HDFC Regalia', 'bank' => 'HDFC Bank', 'limit' => 250000, 'bill' => 42000, 'min' => 2100, 'paid' => 0, 'next' => 28500, 'due' => '26 Mar 2026', 'points' => 18420, 'status' => 'due_soon'],
    ['name' => 'SBI Cashback', 'bank' => 'SBI Card', 'limit' => 150000, 'bill' => 31800, 'min' => 1590, 'paid' => 0, 'next' => 16730, 'due' => '27 Mar 2026', 'points' => 9650, 'status' => 'due_soon'],
    ['name' => 'ICICI Amazon Pay', 'bank' => 'ICICI Bank', 'limit' => 200000, 'bill' => 22100, 'min' => 1105, 'paid' => 22100, 'next' => 0, 'due' => '18 Mar 2026', 'points' => 14280, 'status' => 'paid'],
    ['name' => 'Axis Ace', 'bank' => 'Axis Bank', 'limit' => 120000, 'bill' => 18900, 'min' => 945, 'paid' => 5000, 'next' => 945, 'due' => '02 Apr 2026', 'points' => 7120, 'status' => 'due_soon'],
    ['name' => 'Amex Membership', 'bank' => 'American Express', 'limit' => 180000, 'bill' => 25600, 'min' => 2560, 'paid' => 10000, 'next' => 15600, 'due' => '05 Apr 2026', 'points' => 22140, 'status' => 'due_soon'],
    ['name' => 'IndusInd Legend', 'bank' => 'IndusInd Bank', 'limit' => 100000, 'bill' => 12450, 'min' => 1245, 'paid' => 0, 'next' => 12450, 'due' => '12 Mar 2026', 'points' => 5340, 'status' => 'overdue'],
];

$statusMap = [
    'paid' => ['label' => 'Paid', 'cls' => 'is-paid'],
    'due_soon' => ['label' => 'Due Soon', 'cls' => 'is-soon'],
    'overdue' => ['label' => 'Overdue', 'cls' => 'is-overdue'],
];

ob_start();
?>
<div class="cc-dash">
    <div class="cc-dash-head">
        <div>
            <h1 class="cc-dash-title">Credit Card Management</h1>
            <p class="cc-dash-sub">Overview of limits, dues, payments and rewards across all cards</p>
        </div>
        <div class="cc-dash-actions">
            <button type="button" class="btn btn-cc-ghost" disabled title="Coming soon">
                <i class="fas fa-download mr-1"></i>Export
            </button>
            <a href="credit_cards/cards.php" class="btn btn-cc-solid">
                <i class="fas fa-plus mr-1"></i>Add Card
            </a>
        </div>
    </div>

    <div class="cc-kpi-grid">
        <?php foreach ($kpis as $kpi): ?>
            <div class="cc-kpi tone-<?= htmlspecialchars($kpi['tone'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="cc-kpi-icon"><i class="fas <?= htmlspecialchars($kpi['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></div>
                <div class="cc-kpi-label"><?= htmlspecialchars($kpi['label'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="cc-kpi-value"><?= htmlspecialchars($kpi['value'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php if ($kpi['sub'] !== ''): ?>
                    <div class="cc-kpi-sub"><?= htmlspecialchars($kpi['sub'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="cc-mid-grid">
        <div class="cc-panel">
            <div class="cc-panel-head">
                <h2>Outstanding by Card</h2>
            </div>
            <div class="cc-donut-wrap">
                <div class="cc-donut-canvas">
                    <canvas id="ccOutstandingChart" height="220"></canvas>
                    <div class="cc-donut-center">
                        <span class="cc-donut-label">Total</span>
                        <span class="cc-donut-value"><?= htmlspecialchars(ccInr($outstandingTotal), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <ul class="cc-donut-legend">
                    <?php foreach ($outstandingByCard as $row): ?>
                        <li>
                            <span class="dot" style="background:<?= htmlspecialchars($row['color'], ENT_QUOTES, 'UTF-8') ?>"></span>
                            <span class="name"><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="amt"><?= htmlspecialchars(ccInr($row['amount']), ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="cc-panel">
            <div class="cc-panel-head">
                <h2>Monthly Bills vs Payments</h2>
            </div>
            <div class="cc-bar-wrap">
                <canvas id="ccBillsPaymentsChart" height="220"></canvas>
            </div>
        </div>

        <div class="cc-panel">
            <div class="cc-panel-head">
                <h2>Credit Utilization %</h2>
            </div>
            <div class="cc-gauge-wrap">
                <canvas id="ccUtilizationChart" height="160"></canvas>
                <div class="cc-gauge-center">
                    <div class="cc-gauge-pct"><?= htmlspecialchars(number_format($utilizationPct, 1), ENT_QUOTES, 'UTF-8') ?>%</div>
                    <div class="cc-gauge-caption">Utilization</div>
                </div>
                <div class="cc-gauge-meta">
                    <div>
                        <span class="k">Total Limit</span>
                        <span class="v"><?= htmlspecialchars(ccInr($utilizationLimit), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div>
                        <span class="k">Used Limit</span>
                        <span class="v"><?= htmlspecialchars(ccInr($utilizationUsed), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="cc-panel">
            <div class="cc-panel-head">
                <h2>Alerts &amp; Reminders</h2>
                <a href="credit_cards/notifications.php" class="cc-link">View All</a>
            </div>
            <div class="cc-alerts">
                <?php foreach ($alerts as $group): ?>
                    <div class="cc-alert-group tone-<?= htmlspecialchars($group['tone'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="cc-alert-group-title">
                            <span><?= htmlspecialchars($group['group'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="count">(<?= (int) $group['count'] ?>)</span>
                        </div>
                        <?php foreach ($group['items'] as $item): ?>
                            <div class="cc-alert-item">
                                <div class="cc-alert-ico"><i class="fas fa-bell"></i></div>
                                <div class="cc-alert-meta">
                                    <div class="name"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="sub"><?= htmlspecialchars($item['meta'], ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="cc-alert-amt"><?= htmlspecialchars(ccInr($item['amount']), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="cc-panel cc-table-panel">
        <div class="cc-panel-head">
            <h2>Card Summary</h2>
            <a href="credit_cards/cards.php" class="cc-link">Manage Cards</a>
        </div>
        <div class="cc-table-wrap">
            <table class="cc-table">
                <thead>
                    <tr>
                        <th>Card Name</th>
                        <th>Bank</th>
                        <th>Credit Limit</th>
                        <th>Monthly Bill</th>
                        <th>Minimum Due</th>
                        <th>Paid</th>
                        <th>Next Payment</th>
                        <th>Due Date</th>
                        <th>Reward Points</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cardRows as $row):
                        $st = $statusMap[$row['status']] ?? $statusMap['due_soon'];
                        ?>
                        <tr>
                            <td>
                                <div class="cc-card-name">
                                    <span class="cc-card-avatar"><?= htmlspecialchars(strtoupper(substr($row['name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                                    <strong><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($row['bank'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(ccInr($row['limit']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(ccInr($row['bill']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(ccInr($row['min']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(ccInr($row['paid']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(ccInr($row['next']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="cc-due <?= $row['status'] === 'overdue' || $row['status'] === 'due_soon' ? 'is-hot' : '' ?>">
                                <?= htmlspecialchars($row['due'], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td><?= htmlspecialchars(ccPts($row['points']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="cc-status <?= htmlspecialchars($st['cls'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($st['label'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="cc-table-foot">
            <a href="credit_cards/cards.php" class="btn btn-cc-view-all">
                <i class="fas fa-credit-card mr-1"></i>View All Cards
            </a>
        </div>
    </div>
</div>
<?php
$ccBodyHtml = ob_get_clean();

$ccExtraHead = <<<'CSS'
<style>
.cc-dash {
    --cc-red: #e11d2e;
    --cc-border: #e7ebf1;
    --cc-text: #0f172a;
    --cc-muted: #64748b;
    --cc-bg: #f6f8fb;
    padding: .35rem 0 1.5rem;
}
.cc-dash-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem;
}
.cc-dash-title {
    margin: 0; font-size: 1.35rem; font-weight: 700; color: var(--cc-text);
}
.cc-dash-sub {
    margin: .25rem 0 0; color: var(--cc-muted); font-size: .86rem;
}
.cc-dash-actions { display: flex; gap: .5rem; }
.btn-cc-ghost {
    border: 1px solid #cbd5e1; background: #fff; color: #475569;
    border-radius: 8px; font-weight: 600; font-size: .82rem; padding: .4rem .85rem;
}
.btn-cc-solid {
    background: var(--cc-red); border: 1px solid var(--cc-red); color: #fff;
    border-radius: 8px; font-weight: 600; font-size: .82rem; padding: .4rem .85rem;
}
.btn-cc-solid:hover { background: #c81a28; border-color: #c81a28; color: #fff; }

.cc-kpi-grid {
    display: grid;
    grid-template-columns: repeat(8, minmax(0, 1fr));
    gap: .65rem;
    margin-bottom: .9rem;
}
.cc-kpi {
    background: #fff; border: 1px solid var(--cc-border); border-radius: 12px;
    padding: .75rem .8rem; box-shadow: 0 1px 2px rgba(15,23,42,.04);
    min-width: 0;
}
.cc-kpi-icon {
    width: 30px; height: 30px; border-radius: 8px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .85rem; margin-bottom: .45rem;
}
.cc-kpi.tone-blue .cc-kpi-icon { background: #dbeafe; color: #2563eb; }
.cc-kpi.tone-purple .cc-kpi-icon { background: #ede9fe; color: #7c3aed; }
.cc-kpi.tone-orange .cc-kpi-icon { background: #ffedd5; color: #ea580c; }
.cc-kpi.tone-red .cc-kpi-icon { background: #fee2e2; color: #dc2626; }
.cc-kpi.tone-green .cc-kpi-icon { background: #dcfce7; color: #16a34a; }
.cc-kpi.tone-rose .cc-kpi-icon { background: #ffe4e6; color: #e11d48; }
.cc-kpi.tone-teal .cc-kpi-icon { background: #ccfbf1; color: #0d9488; }
.cc-kpi.tone-sky .cc-kpi-icon { background: #e0f2fe; color: #0284c7; }
.cc-kpi-label {
    font-size: .68rem; font-weight: 600; color: var(--cc-muted);
    text-transform: uppercase; letter-spacing: .03em; line-height: 1.25;
    margin-bottom: .2rem;
}
.cc-kpi-value {
    font-size: 1.05rem; font-weight: 750; color: var(--cc-text); line-height: 1.2;
    word-break: break-word;
}
.cc-kpi-sub { font-size: .7rem; color: var(--cc-muted); margin-top: .15rem; }

.cc-mid-grid {
    display: grid;
    grid-template-columns: 1.15fr 1.15fr .9fr .95fr;
    gap: .75rem;
    margin-bottom: .9rem;
}
.cc-panel {
    background: #fff; border: 1px solid var(--cc-border); border-radius: 14px;
    padding: .9rem 1rem 1rem; box-shadow: 0 1px 2px rgba(15,23,42,.04);
    min-width: 0;
}
.cc-panel-head {
    display: flex; align-items: center; justify-content: space-between;
    gap: .5rem; margin-bottom: .75rem;
}
.cc-panel-head h2 {
    margin: 0; font-size: .95rem; font-weight: 700; color: var(--cc-text);
}
.cc-link {
    font-size: .78rem; font-weight: 600; color: #2563eb; text-decoration: none;
}
.cc-link:hover { text-decoration: underline; }

.cc-donut-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; align-items: center; }
.cc-donut-canvas { position: relative; height: 220px; }
.cc-donut-canvas canvas { width: 100% !important; height: 100% !important; }
.cc-donut-center {
    position: absolute; inset: 0; display: flex; flex-direction: column;
    align-items: center; justify-content: center; pointer-events: none;
}
.cc-donut-label { font-size: .68rem; color: var(--cc-muted); font-weight: 600; }
.cc-donut-value { font-size: .92rem; font-weight: 750; color: var(--cc-text); }
.cc-donut-legend { list-style: none; margin: 0; padding: 0; }
.cc-donut-legend li {
    display: grid; grid-template-columns: 10px 1fr auto; gap: .45rem;
    align-items: center; padding: .28rem 0; font-size: .76rem;
}
.cc-donut-legend .dot { width: 8px; height: 8px; border-radius: 50%; }
.cc-donut-legend .name { color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cc-donut-legend .amt { color: var(--cc-text); font-weight: 700; }

.cc-bar-wrap { position: relative; height: 230px; }
.cc-gauge-wrap { position: relative; text-align: center; padding-top: .25rem; }
.cc-gauge-wrap canvas { max-height: 140px; margin: 0 auto; }
.cc-gauge-center {
    position: absolute; left: 0; right: 0; top: 52px;
    display: flex; flex-direction: column; align-items: center;
}
.cc-gauge-pct { font-size: 1.45rem; font-weight: 800; color: var(--cc-text); line-height: 1; }
.cc-gauge-caption { font-size: .72rem; color: var(--cc-muted); font-weight: 600; margin-top: .15rem; }
.cc-gauge-meta {
    display: grid; grid-template-columns: 1fr 1fr; gap: .5rem;
    margin-top: .35rem; text-align: left;
}
.cc-gauge-meta > div {
    background: #f8fafc; border: 1px solid #eef2f7; border-radius: 10px; padding: .55rem .65rem;
}
.cc-gauge-meta .k { display: block; font-size: .68rem; color: var(--cc-muted); font-weight: 600; }
.cc-gauge-meta .v { display: block; font-size: .88rem; font-weight: 750; color: var(--cc-text); margin-top: .1rem; }

.cc-alerts { display: flex; flex-direction: column; gap: .7rem; max-height: 290px; overflow: auto; }
.cc-alert-group-title {
    font-size: .72rem; font-weight: 750; text-transform: uppercase; letter-spacing: .04em;
    margin-bottom: .35rem; display: flex; gap: .3rem; align-items: center;
}
.cc-alert-group.tone-overdue .cc-alert-group-title { color: #dc2626; }
.cc-alert-group.tone-soon .cc-alert-group-title { color: #d97706; }
.cc-alert-group.tone-paid .cc-alert-group-title { color: #16a34a; }
.cc-alert-group .count { font-weight: 600; opacity: .85; }
.cc-alert-item {
    display: flex; align-items: center; gap: .55rem;
    padding: .5rem .55rem; border: 1px solid #eef2f7; border-radius: 10px;
    background: #fafbfc; margin-bottom: .35rem;
}
.cc-alert-ico {
    width: 28px; height: 28px; border-radius: 8px; flex: 0 0 28px;
    display: inline-flex; align-items: center; justify-content: center; font-size: .75rem;
}
.tone-overdue .cc-alert-ico { background: #fee2e2; color: #dc2626; }
.tone-soon .cc-alert-ico { background: #fef3c7; color: #d97706; }
.tone-paid .cc-alert-ico { background: #dcfce7; color: #16a34a; }
.cc-alert-meta { flex: 1 1 auto; min-width: 0; }
.cc-alert-meta .name { font-size: .8rem; font-weight: 700; color: var(--cc-text); }
.cc-alert-meta .sub { font-size: .7rem; color: var(--cc-muted); margin-top: .1rem; }
.cc-alert-amt { font-size: .8rem; font-weight: 750; color: var(--cc-text); white-space: nowrap; }

.cc-table-panel { padding-bottom: .85rem; }
.cc-table-wrap { overflow: auto; border: 1px solid var(--cc-border); border-radius: 12px; }
.cc-table {
    width: 100%; border-collapse: collapse; min-width: 980px; margin: 0; background: #fff;
}
.cc-table th {
    background: #f8fafc; color: #475569; font-size: .7rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em; padding: .6rem .7rem;
    border-bottom: 1px solid var(--cc-border); white-space: nowrap; text-align: left;
}
.cc-table td {
    padding: .65rem .7rem; font-size: .82rem; color: #1e293b;
    border-bottom: 1px solid #f1f5f9; vertical-align: middle;
}
.cc-table tr:last-child td { border-bottom: 0; }
.cc-card-name { display: flex; align-items: center; gap: .5rem; }
.cc-card-avatar {
    width: 28px; height: 28px; border-radius: 8px; background: #eff6ff; color: #2563eb;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .72rem; font-weight: 750;
}
.cc-due.is-hot { color: #dc2626; font-weight: 700; }
.cc-status {
    display: inline-flex; align-items: center; padding: .18rem .55rem;
    border-radius: 999px; font-size: .7rem; font-weight: 750;
}
.cc-status.is-paid { background: #dcfce7; color: #15803d; }
.cc-status.is-soon { background: #fef3c7; color: #b45309; }
.cc-status.is-overdue { background: #fee2e2; color: #b91c1c; }
.cc-table-foot { display: flex; justify-content: center; padding: .9rem 0 .15rem; }
.btn-cc-view-all {
    background: #2563eb; border: 1px solid #2563eb; color: #fff;
    border-radius: 8px; font-weight: 650; font-size: .82rem; padding: .45rem 1rem;
}
.btn-cc-view-all:hover { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }

@media (max-width: 1399.98px) {
    .cc-kpi-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .cc-mid-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 991.98px) {
    .cc-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .cc-donut-wrap { grid-template-columns: 1fr; }
}
@media (max-width: 767.98px) {
    .cc-kpi-grid, .cc-mid-grid { grid-template-columns: 1fr; }
    .cc-dash-title { font-size: 1.15rem; }
}
</style>
CSS;

$outstandingLabels = array_column($outstandingByCard, 'name');
$outstandingValues = array_column($outstandingByCard, 'amount');
$outstandingColors = array_column($outstandingByCard, 'color');

$ccExtraFooter = '<script>
(function () {
  if (typeof Chart === "undefined") { return; }

  var outstandingLabels = ' . json_encode($outstandingLabels, JSON_UNESCAPED_UNICODE) . ';
  var outstandingValues = ' . json_encode($outstandingValues) . ';
  var outstandingColors = ' . json_encode($outstandingColors) . ';
  var monthLabels = ' . json_encode($monthlyTrend['labels']) . ';
  var monthBills = ' . json_encode($monthlyTrend['bills']) . ';
  var monthPayments = ' . json_encode($monthlyTrend['payments']) . ';
  var utilPct = ' . json_encode($utilizationPct) . ';

  var donut = document.getElementById("ccOutstandingChart");
  if (donut) {
    new Chart(donut.getContext("2d"), {
      type: "doughnut",
      data: {
        labels: outstandingLabels,
        datasets: [{
          data: outstandingValues,
          backgroundColor: outstandingColors,
          borderWidth: 2,
          borderColor: "#fff"
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutoutPercentage: 68,
        legend: { display: false },
        tooltips: {
          callbacks: {
            label: function (tip, data) {
              var v = data.datasets[tip.datasetIndex].data[tip.index] || 0;
              return " " + data.labels[tip.index] + ": ₹" + Number(v).toLocaleString("en-IN");
            }
          }
        }
      }
    });
  }

  var bars = document.getElementById("ccBillsPaymentsChart");
  if (bars) {
    new Chart(bars.getContext("2d"), {
      type: "bar",
      data: {
        labels: monthLabels,
        datasets: [
          {
            label: "Bills",
            backgroundColor: "#f87171",
            borderColor: "#ef4444",
            borderWidth: 0,
            data: monthBills,
            maxBarThickness: 18
          },
          {
            label: "Payments",
            backgroundColor: "#4ade80",
            borderColor: "#22c55e",
            borderWidth: 0,
            data: monthPayments,
            maxBarThickness: 18
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: {
          display: true,
          position: "top",
          align: "end",
          labels: { boxWidth: 10, fontSize: 11, fontStyle: "bold", padding: 12 }
        },
        scales: {
          xAxes: [{
            gridLines: { display: false },
            ticks: { fontSize: 11, fontColor: "#64748b" }
          }],
          yAxes: [{
            gridLines: { color: "#f1f5f9", zeroLineColor: "#e2e8f0" },
            ticks: {
              fontSize: 10,
              fontColor: "#94a3b8",
              callback: function (v) {
                if (v >= 100000) return (v / 100000).toFixed(1) + "L";
                if (v >= 1000) return Math.round(v / 1000) + "k";
                return v;
              }
            }
          }]
        },
        tooltips: {
          callbacks: {
            label: function (tip) {
              return " " + tip.datasetLabel + ": ₹" + Number(tip.yLabel).toLocaleString("en-IN");
            }
          }
        }
      }
    });
  }

  var gauge = document.getElementById("ccUtilizationChart");
  if (gauge) {
    var used = Math.max(0, Math.min(100, Number(utilPct) || 0));
    var rest = Math.max(0, 100 - used);
    new Chart(gauge.getContext("2d"), {
      type: "doughnut",
      data: {
        labels: ["Used", "Available"],
        datasets: [{
          data: [used, rest],
          backgroundColor: ["#3b82f6", "#e2e8f0"],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutoutPercentage: 78,
        rotation: -Math.PI,
        circumference: Math.PI,
        legend: { display: false },
        tooltips: { enabled: false }
      }
    });
  }
})();
</script>';

$ccPageTitle = 'Dashboard';
$ccPageSubtitle = 'Credit Card Management';
$ccBodyClass = 'cc-dashboard-page';
include __DIR__ . '/_layout.php';
