<?php
session_start();
include_once('connection.php');

if ($_SESSION['role'] != '1') {
    header('location:index.php');
    exit;
}

require_once __DIR__ . '/includes/dashboard_data.php';
if (file_exists(__DIR__ . '/../includes/payment_helpers.php')) {
    require_once __DIR__ . '/../includes/payment_helpers.php';
}

$msg = '';
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

$period = $_GET['period'] ?? 'this_month';
if (!in_array($period, ['this_month', 'last_month', 'all_time'], true)) {
    $period = 'this_month';
}

$stats = dashCollectOrganizationStats($conn, $period);

$pipelineMax = 1;
foreach ($stats['pipeline'] as $count) {
    $pipelineMax = max($pipelineMax, (int) $count);
}

$serviceLabels = array_keys($stats['service_mix']);
$serviceValues = array_values($stats['service_mix']);
$serviceTotal = array_sum($serviceValues) ?: 1;

$invoice = $stats['invoice_status'];
$invoiceTotal = max(1, (int) $invoice['total']);

$trendLabels = array_column($stats['monthly_trend'], 'label');
$trendTotal = array_column($stats['monthly_trend'], 'total');
$trendWon = array_column($stats['monthly_trend'], 'won');
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Organization Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php include 'includes/header-links.php'; ?>
  <style>
    body { background: #eef1f5; }
    .org-dashboard .content-wrapper > .content { background: #eef1f5; padding-top: 0.75rem; }
    .org-dashboard .content-header { display: none; }

    .dash-head {
      display: flex; align-items: flex-start; justify-content: space-between;
      flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;
    }
    .dash-head h1 { font-size: 1.35rem; font-weight: 700; color: #1e293b; margin: 0 0 0.25rem; }
    .dash-head .sub { font-size: 0.82rem; color: #64748b; margin: 0; }
    .dash-filters { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
    .dash-filters select {
      min-width: 150px; height: 34px; font-size: 0.82rem; border-color: #cbd5e1;
      border-radius: 6px; background: #fff;
    }

    .dash-card {
      background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); height: 100%;
      display: flex; flex-direction: column;
    }
    .dash-card .card-top {
      display: flex; align-items: flex-start; justify-content: space-between;
      padding: 0.85rem 1rem 0.35rem;
    }
    .dash-card .card-icon {
      width: 34px; height: 34px; border-radius: 8px; display: inline-flex;
      align-items: center; justify-content: center; font-size: 0.95rem;
      background: #ecfdf5; color: #059669; flex-shrink: 0;
    }
    .dash-card .card-link {
      color: #94a3b8; font-size: 0.85rem; line-height: 1;
    }
    .dash-card .card-body-inner { padding: 0 1rem 1rem; flex: 1; }
    .dash-card .card-title { font-size: 0.78rem; color: #64748b; margin: 0 0 0.35rem; font-weight: 600; }
    .dash-card .card-value { font-size: 1.35rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
    .dash-card .card-sub { font-size: 0.72rem; color: #94a3b8; margin-top: 0.35rem; }

    .kpi-row { margin-bottom: 1rem; }
    .kpi-row > [class*="col-"] { margin-bottom: 0.75rem; }

    .section-head { margin-bottom: 0.65rem; padding-bottom: 0.55rem; border-bottom: 1px solid #eef2f6; }
    .section-head h3 { font-size: 0.92rem; font-weight: 700; color: #1e293b; margin: 0; }
    .section-head p { font-size: 0.74rem; color: #64748b; margin: 0.15rem 0 0; }

    .dash-scroll {
      max-height: 220px;
      overflow-y: auto;
      overflow-x: hidden;
      padding-right: 4px;
      margin-right: -2px;
      scrollbar-width: thin;
      scrollbar-color: #b8c5d6 transparent;
    }
    .dash-scroll::-webkit-scrollbar { width: 6px; }
    .dash-scroll::-webkit-scrollbar-track { background: transparent; }
    .dash-scroll::-webkit-scrollbar-thumb {
      background: #b8c5d6;
      border-radius: 999px;
    }
    .dash-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    .dash-table-scroll {
      max-height: 220px;
      overflow-y: auto;
      overflow-x: hidden;
      padding-right: 4px;
      margin-right: -2px;
      scrollbar-width: thin;
      scrollbar-color: #b8c5d6 transparent;
    }
    .dash-table-scroll::-webkit-scrollbar { width: 6px; }
    .dash-table-scroll::-webkit-scrollbar-track { background: transparent; }
    .dash-table-scroll::-webkit-scrollbar-thumb {
      background: #b8c5d6;
      border-radius: 999px;
    }
    .dash-table-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    .dash-table-scroll .dash-table { margin-bottom: 0; }
    .dash-table-scroll thead th {
      position: sticky;
      top: 0;
      z-index: 2;
      background: #fff;
      box-shadow: 0 1px 0 #e2e8f0;
    }

    .dash-card-body-scroll {
      min-height: 220px;
      display: flex;
      flex-direction: column;
    }
    .dash-card-body-scroll .dash-scroll,
    .dash-card-body-scroll .dash-table-scroll,
    .dash-card-body-scroll .empty-box {
      flex: 1;
    }

    .pipeline-list { list-style: none; padding: 0; margin: 0; }
    .pipeline-list li {
      display: grid; grid-template-columns: 110px 1fr 36px; gap: 0.5rem;
      align-items: center; margin-bottom: 0.55rem; font-size: 0.78rem;
    }
    .pipeline-list .bar-wrap {
      height: 8px; background: #f1f5f9; border-radius: 999px; overflow: hidden;
    }
    .pipeline-list .bar {
      height: 100%; background: linear-gradient(90deg, #34d399, #059669); border-radius: 999px;
    }
    .pipeline-list .count { text-align: right; font-weight: 700; color: #334155; }

    .empty-box {
      border: 1px dashed #cbd5e1; border-radius: 8px; padding: 2rem 1rem;
      text-align: center; color: #94a3b8; font-size: 0.85rem; background: #fafbfc;
    }

    .pay-list { list-style: none; padding: 0; margin: 0; }
    .pay-list li {
      display: flex; justify-content: space-between; align-items: center;
      padding: 0.55rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.78rem;
    }
    .pay-list li:last-child { border-bottom: 0; }
    .pay-list .amt { font-weight: 700; color: #059669; white-space: nowrap; }

    .tour-list { list-style: none; padding: 0; margin: 0; }
    .tour-list li {
      display: flex; justify-content: space-between; gap: 0.75rem;
      padding: 0.65rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.78rem;
    }
    .tour-list li:last-child { border-bottom: 0; }
    .tour-list .title { font-weight: 600; color: #1e293b; margin-bottom: 0.15rem; }
    .tour-list .meta { color: #64748b; font-size: 0.72rem; }
    .badge-ongoing {
      background: #dcfce7; color: #15803d; font-size: 0.68rem; font-weight: 700;
      padding: 0.2rem 0.45rem; border-radius: 999px; white-space: nowrap; height: fit-content;
    }
    .badge-upcoming {
      background: #dbeafe; color: #1d4ed8; font-size: 0.68rem; font-weight: 700;
      padding: 0.2rem 0.45rem; border-radius: 999px; white-space: nowrap; height: fit-content;
    }

    .dash-table { width: 100%; font-size: 0.76rem; }
    .dash-table th {
      text-transform: uppercase; letter-spacing: 0.04em; color: #64748b;
      font-weight: 700; border-top: 0; border-bottom: 1px solid #e2e8f0; padding: 0.45rem 0.35rem;
    }
    .dash-table td { padding: 0.5rem 0.35rem; border-top: 1px solid #f1f5f9; vertical-align: middle; }
    .dash-table .confirmed { color: #059669; font-weight: 700; }

    .chart-wrap { position: relative; height: 210px; }
    .chart-wrap-sm { height: 180px; }
    .chart-center-label {
      position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
      font-size: 1.1rem; font-weight: 700; color: #1e293b; pointer-events: none;
    }
    .chart-wrap.donut-wrap { max-width: 140px; margin: 0 auto; }

    .quick-links {
      display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem;
    }
    .quick-links a {
      font-size: 0.78rem; padding: 0.35rem 0.75rem; border-radius: 999px;
      background: #fff; border: 1px solid #e2e8f0; color: #334155; text-decoration: none;
    }
    .quick-links a:hover { border-color: #059669; color: #059669; }

    @media (max-width: 991px) {
      .dash-card .card-value { font-size: 1.15rem; }
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed org-dashboard">
<div class="wrapper">
  <?php include 'includes/top-header.php'; ?>
  <?php include 'includes/sidebar.php'; ?>

  <div class="content-wrapper">
    <section class="content">
      <div class="container-fluid">

        <?php if ($msg !== '') { ?>
          <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
            <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
          </div>
        <?php } ?>

        <div class="dash-head">
          <div>
            <h1>Organization Dashboard</h1>
            <p class="sub">All users • Live travel CRM overview • <?= htmlspecialchars($stats['period_label'], ENT_QUOTES, 'UTF-8') ?></p>
          </div>
          <form class="dash-filters" method="get">
            <select name="period" onchange="this.form.submit()">
              <option value="this_month" <?= $period === 'this_month' ? 'selected' : '' ?>>This Month</option>
              <option value="last_month" <?= $period === 'last_month' ? 'selected' : '' ?>>Last Month</option>
              <option value="all_time" <?= $period === 'all_time' ? 'selected' : '' ?>>All Time</option>
            </select>
          </form>
        </div>

        <!-- KPI Row -->
        <div class="row kpi-row">
          <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="dash-card">
              <div class="card-top">
                <span class="card-icon"><i class="fas fa-comments"></i></span>
                <a href="crm/leads.php" class="card-link"><i class="fas fa-external-link-alt"></i></a>
              </div>
              <div class="card-body-inner">
                <p class="card-title">Total Queries</p>
                <div class="card-value"><?= number_format($stats['total_queries']) ?></div>
                <div class="card-sub">Leads in selected period</div>
              </div>
            </div>
          </div>
          <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="dash-card">
              <div class="card-top">
                <span class="card-icon"><i class="fas fa-calendar-check"></i></span>
                <a href="crm/quotation-generator-list.php" class="card-link"><i class="fas fa-external-link-alt"></i></a>
              </div>
              <div class="card-body-inner">
                <p class="card-title">Confirmed</p>
                <div class="card-value"><?= number_format($stats['confirmed_count']) ?></div>
                <div class="card-sub">Tours confirmed</div>
              </div>
            </div>
          </div>
          <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="dash-card">
              <div class="card-top">
                <span class="card-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                <a href="crm/quotation-generator-list.php" class="card-link"><i class="fas fa-external-link-alt"></i></a>
              </div>
              <div class="card-body-inner">
                <p class="card-title">Confirmed Value</p>
                <div class="card-value" style="font-size:1.05rem;"><?= dashFormatInr($stats['confirmed_value']) ?></div>
                <div class="card-sub">Quotation total</div>
              </div>
            </div>
          </div>
          <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="dash-card">
              <div class="card-top">
                <span class="card-icon"><i class="fas fa-plane-departure"></i></span>
                <a href="crm/quotation-generator-list.php" class="card-link"><i class="fas fa-external-link-alt"></i></a>
              </div>
              <div class="card-body-inner">
                <p class="card-title">Confirmed Travel</p>
                <div class="card-value"><?= number_format($stats['confirmed_travel']) ?></div>
                <div class="card-sub">Upcoming departures</div>
              </div>
            </div>
          </div>
          <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="dash-card">
              <div class="card-top">
                <span class="card-icon"><i class="fas fa-wallet"></i></span>
                <a href="payment_links.php" class="card-link"><i class="fas fa-external-link-alt"></i></a>
              </div>
              <div class="card-body-inner">
                <p class="card-title">Collection Revenue</p>
                <div class="card-value" style="font-size:1.05rem;"><?= dashFormatInr($stats['collection_revenue']) ?></div>
                <div class="card-sub">Paid payment links</div>
              </div>
            </div>
          </div>
          <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="dash-card">
              <div class="card-top">
                <span class="card-icon"><i class="fas fa-money-bill-wave"></i></span>
                <a href="crm/quotation-generator-list.php" class="card-link"><i class="fas fa-external-link-alt"></i></a>
              </div>
              <div class="card-body-inner">
                <p class="card-title">Supplier Payable</p>
                <div class="card-value" style="font-size:1.05rem;"><?= dashFormatInr($stats['supplier_payable']) ?></div>
                <div class="card-sub">Unpaid supplier balance</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Middle Row -->
        <div class="row mb-3">
          <div class="col-xl-3 col-lg-6 mb-3">
            <div class="dash-card">
              <div class="card-body-inner pt-3">
                <div class="section-head">
                  <h3>Today's Follow-ups</h3>
                  <p>Calls, tasks and reminders due today</p>
                </div>
                <div class="empty-box">No records found</div>
              </div>
            </div>
          </div>
          <div class="col-xl-3 col-lg-6 mb-3">
            <div class="dash-card">
              <div class="card-body-inner pt-3">
                <div class="section-head">
                  <h3>Query Pipeline</h3>
                  <p>Stage-wise live query distribution</p>
                </div>
                <?php if (empty($stats['pipeline'])) { ?>
                  <div class="empty-box">No lead data yet</div>
                <?php } else { ?>
                  <div class="dash-scroll">
                    <ul class="pipeline-list">
                      <?php foreach ($stats['pipeline'] as $stage => $count) {
                          $pct = round(((int) $count / $pipelineMax) * 100);
                      ?>
                        <li>
                          <span><?= htmlspecialchars($stage, ENT_QUOTES, 'UTF-8') ?></span>
                          <span class="bar-wrap"><span class="bar" style="width:<?= $pct ?>%"></span></span>
                          <span class="count"><?= (int) $count ?></span>
                        </li>
                      <?php } ?>
                    </ul>
                  </div>
                <?php } ?>
              </div>
            </div>
          </div>
          <div class="col-xl-3 col-lg-6 mb-3">
            <div class="dash-card">
              <div class="card-body-inner pt-3">
                <div class="section-head">
                  <h3>Service Mix</h3>
                  <p>Travel service-wise query split</p>
                </div>
                <?php if (empty($serviceValues)) { ?>
                  <div class="empty-box">No service data yet</div>
                <?php } else { ?>
                  <div class="row align-items-center">
                    <div class="col-6">
                      <div class="chart-wrap donut-wrap">
                        <canvas id="serviceMixChart"></canvas>
                        <div class="chart-center-label"><?= (int) $stats['total_queries'] ?></div>
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="dash-scroll" style="max-height:180px;">
                        <ul class="list-unstyled mb-0" style="font-size:0.72rem;">
                          <?php
                          $colors = ['#3b82f6', '#22c55e', '#14b8a6', '#f97316', '#8b5cf6', '#64748b', '#ec4899', '#eab308'];
                          foreach ($serviceLabels as $i => $label) {
                              $val = (int) $serviceValues[$i];
                              $pct = round(($val / $serviceTotal) * 100);
                          ?>
                            <li class="mb-1">
                              <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $colors[$i % count($colors)] ?>;margin-right:4px;"></span>
                              <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> <strong><?= $pct ?>%</strong>
                            </li>
                          <?php } ?>
                        </ul>
                      </div>
                    </div>
                  </div>
                <?php } ?>
              </div>
            </div>
          </div>
          <div class="col-xl-3 col-lg-6 mb-3">
            <div class="dash-card">
              <div class="card-body-inner pt-3">
                <div class="section-head">
                  <h3>Pending Supplier Payments</h3>
                  <p>Due and unpaid supplier payables</p>
                </div>
                <?php if (empty($stats['pending_supplier_payments'])) { ?>
                  <div class="empty-box">No pending supplier payments</div>
                <?php } else { ?>
                  <div class="dash-scroll">
                    <ul class="pay-list">
                      <?php foreach ($stats['pending_supplier_payments'] as $pay) { ?>
                        <li>
                          <div>
                            <strong><?= htmlspecialchars($pay['supplier'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                            <span class="text-muted">Due: <?= htmlspecialchars($pay['due'], ENT_QUOTES, 'UTF-8') ?></span>
                          </div>
                          <span class="amt"><?= dashFormatInr($pay['amount']) ?></span>
                        </li>
                      <?php } ?>
                    </ul>
                  </div>
                <?php } ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-3">
          <div class="col-xl-4 col-lg-6 mb-3">
            <div class="dash-card">
              <div class="card-body-inner pt-3">
                <div class="section-head">
                  <h3>Monthly Query Trend</h3>
                  <p>Total queries vs won bookings</p>
                </div>
                <div class="chart-wrap">
                  <canvas id="monthlyTrendChart"></canvas>
                </div>
              </div>
            </div>
          </div>
          <div class="col-xl-4 col-lg-6 mb-3">
            <div class="dash-card">
              <div class="card-body-inner pt-3">
                <div class="section-head">
                  <h3>Ongoing &amp; Upcoming Tours</h3>
                  <p>Current and upcoming confirmed tour schedule</p>
                </div>
                <?php if (empty($stats['ongoing_tours'])) { ?>
                  <div class="empty-box">No confirmed tours scheduled</div>
                <?php } else { ?>
                  <div class="dash-scroll">
                    <ul class="tour-list">
                      <?php foreach ($stats['ongoing_tours'] as $tour) { ?>
                        <li>
                          <div>
                            <div class="title"><?= htmlspecialchars($tour['title'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="meta"><?= htmlspecialchars($tour['destination'], ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars($tour['dates'], ENT_QUOTES, 'UTF-8') ?></div>
                          </div>
                          <span class="<?= $tour['status'] === 'On Going' ? 'badge-ongoing' : 'badge-upcoming' ?>">
                            <?= htmlspecialchars($tour['status'], ENT_QUOTES, 'UTF-8') ?>
                          </span>
                        </li>
                      <?php } ?>
                    </ul>
                  </div>
                <?php } ?>
              </div>
            </div>
          </div>
          <div class="col-xl-4 col-lg-12 mb-3">
            <div class="dash-card">
              <div class="card-body-inner pt-3">
                <div class="section-head">
                  <h3>Payment Link Status</h3>
                  <p>Paid, unpaid and other payment links</p>
                </div>
                <?php if (!$stats['tables']['payment_links'] || $invoiceTotal <= 0) { ?>
                  <div class="empty-box">No payment link data yet</div>
                <?php } else { ?>
                  <div class="row align-items-center">
                    <div class="col-6">
                      <div class="chart-wrap donut-wrap chart-wrap-sm">
                        <canvas id="invoiceStatusChart"></canvas>
                        <div class="chart-center-label"><?= (int) $invoice['total'] ?></div>
                      </div>
                    </div>
                    <div class="col-6">
                      <ul class="list-unstyled mb-0" style="font-size:0.78rem;">
                        <li class="mb-2"><span style="color:#3b82f6;">●</span> Paid <strong><?= round(($invoice['paid'] / $invoiceTotal) * 100) ?>%</strong> (<?= (int) $invoice['paid'] ?>)</li>
                        <li class="mb-2"><span style="color:#22c55e;">●</span> Unpaid <strong><?= round(($invoice['unpaid'] / $invoiceTotal) * 100) ?>%</strong> (<?= (int) $invoice['unpaid'] ?>)</li>
                        <li><span style="color:#14b8a6;">●</span> Other <strong><?= round(($invoice['partial'] / $invoiceTotal) * 100) ?>%</strong> (<?= (int) $invoice['partial'] ?>)</li>
                      </ul>
                    </div>
                  </div>
                <?php } ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Tables Row -->
        <div class="row mb-3">
          <div class="col-xl-3 col-lg-6 mb-3">
            <div class="dash-card">
              <div class="card-body-inner pt-3">
                <div class="section-head">
                  <h3>Top Destinations</h3>
                  <p>Total queries and confirmed leads</p>
                </div>
                <?php if (empty($stats['top_destinations'])) { ?>
                  <div class="empty-box">No destination data</div>
                <?php } else { ?>
                  <div class="dash-table-scroll">
                    <table class="dash-table">
                      <thead><tr><th>Destination</th><th>Total</th><th>Confirmed</th></tr></thead>
                      <tbody>
                        <?php foreach ($stats['top_destinations'] as $row) { ?>
                          <tr>
                            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int) $row['total'] ?></td>
                            <td class="confirmed"><?= (int) $row['confirmed'] ?></td>
                          </tr>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                <?php } ?>
              </div>
            </div>
          </div>
          <div class="col-xl-3 col-lg-6 mb-3">
            <div class="dash-card">
              <div class="card-body-inner pt-3">
                <div class="section-head">
                  <h3>Lead Source Performance</h3>
                  <p>Total queries and confirmed leads</p>
                </div>
                <?php if (empty($stats['lead_sources'])) { ?>
                  <div class="empty-box">No lead source data</div>
                <?php } else { ?>
                  <div class="dash-table-scroll">
                    <table class="dash-table">
                      <thead><tr><th>Lead Source</th><th>Total</th><th>Confirmed</th></tr></thead>
                      <tbody>
                        <?php foreach ($stats['lead_sources'] as $row) { ?>
                          <tr>
                            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int) $row['total'] ?></td>
                            <td class="confirmed"><?= (int) $row['confirmed'] ?></td>
                          </tr>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                <?php } ?>
              </div>
            </div>
          </div>
          <div class="col-xl-3 col-lg-6 mb-3">
            <div class="dash-card">
              <div class="card-body-inner pt-3">
                <div class="section-head">
                  <h3>Team Performance</h3>
                  <p>Total queries and confirmed leads</p>
                </div>
                <?php if (empty($stats['team_performance'])) { ?>
                  <div class="empty-box">No team assignment data</div>
                <?php } else { ?>
                  <div class="dash-table-scroll">
                    <table class="dash-table">
                      <thead><tr><th>Team Member</th><th>Total</th><th>Confirmed</th></tr></thead>
                      <tbody>
                        <?php foreach ($stats['team_performance'] as $row) { ?>
                          <tr>
                            <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int) $row['total'] ?></td>
                            <td class="confirmed"><?= (int) $row['confirmed'] ?></td>
                          </tr>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                <?php } ?>
              </div>
            </div>
          </div>
          <div class="col-xl-3 col-lg-6 mb-3">
            <div class="dash-card">
              <div class="card-body-inner pt-3">
                <div class="section-head">
                  <h3>Quick Access</h3>
                  <p>Jump to CRM modules</p>
                </div>
                <div class="dash-scroll" style="max-height:220px;">
                  <div class="quick-links" style="margin-top:0;">
                    <a href="crm/leads.php"><i class="fas fa-user-plus mr-1"></i> Leads</a>
                    <a href="crm/quotation_generator.php"><i class="fas fa-file-invoice mr-1"></i> Quotation</a>
                    <a href="crm/suppliers.php"><i class="fas fa-handshake mr-1"></i> Suppliers</a>
                    <a href="lead_contacts.php"><i class="fas fa-address-book mr-1"></i> Contacts</a>
                    <a href="crm/lead_intake_pending.php"><i class="fas fa-inbox mr-1"></i> Pending Intake (<?= (int) $stats['pending_intake'] ?>)</a>
                    <a href="payment_links.php"><i class="fas fa-link mr-1"></i> Payments</a>
                  </div>
                </div>
                <?php if (!$stats['tables']['leads']) { ?>
                  <p class="text-muted small mt-3 mb-0">Create your first lead in CRM to populate dashboard metrics.</p>
                <?php } ?>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>
  </div>

  <?php include 'includes/footer-links.php'; ?>

  <script>
  (function () {
    if (typeof Chart === 'undefined') return;

    <?php if (!empty($serviceValues)) { ?>
    new Chart(document.getElementById('serviceMixChart'), {
      type: 'doughnut',
      data: {
        labels: <?= json_encode($serviceLabels) ?>,
        datasets: [{
          data: <?= json_encode(array_map('intval', $serviceValues)) ?>,
          backgroundColor: ['#3b82f6', '#22c55e', '#14b8a6', '#f97316', '#8b5cf6', '#64748b', '#ec4899', '#eab308'],
          borderWidth: 0
        }]
      },
      options: {
        cutoutPercentage: 72,
        legend: { display: false },
        tooltips: { enabled: true }
      }
    });
    <?php } ?>

    new Chart(document.getElementById('monthlyTrendChart'), {
      type: 'bar',
      data: {
        labels: <?= json_encode($trendLabels) ?>,
        datasets: [
          {
            label: 'Total Queries',
            data: <?= json_encode(array_map('intval', $trendTotal)) ?>,
            backgroundColor: '#cbd5e1',
            maxBarThickness: 18
          },
          {
            label: 'Won Queries',
            data: <?= json_encode(array_map('intval', $trendWon)) ?>,
            backgroundColor: '#22c55e',
            maxBarThickness: 18
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: { display: true, position: 'bottom', labels: { boxWidth: 10, fontSize: 11 } },
        scales: {
          xAxes: [{ gridLines: { display: false }, ticks: { fontSize: 10 } }],
          yAxes: [{ ticks: { beginAtZero: true, precision: 0, fontSize: 10 }, gridLines: { color: '#f1f5f9' } }]
        }
      }
    });

    <?php if ($stats['tables']['payment_links'] && $invoiceTotal > 0) { ?>
    new Chart(document.getElementById('invoiceStatusChart'), {
      type: 'doughnut',
      data: {
        labels: ['Paid', 'Unpaid', 'Other'],
        datasets: [{
          data: [<?= (int) $invoice['paid'] ?>, <?= (int) $invoice['unpaid'] ?>, <?= (int) $invoice['partial'] ?>],
          backgroundColor: ['#3b82f6', '#22c55e', '#14b8a6'],
          borderWidth: 0
        }]
      },
      options: {
        cutoutPercentage: 72,
        legend: { display: false }
      }
    });
    <?php } ?>
  })();
  </script>
</div>
</body>
</html>
