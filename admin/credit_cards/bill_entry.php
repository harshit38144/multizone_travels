<?php
/**
 * Monthly Bill Entry — UI prototype with sample data.
 */

function ccBillInr($n): string
{
    $n = abs((float) $n);
    $int = (string) (int) round($n);
    $last3 = substr($int, -3);
    $rest = substr($int, 0, -3);
    if ($rest !== '') {
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $int = $rest . ',' . $last3;
    }
    return '₹' . $int;
}

$bills = [
    ['card' => 'HDFC Regalia', 'bank' => 'HDFC Bank', 'month' => 'May 2024', 'bill_date' => '02 May 2024', 'due_date' => '15 May 2024', 'bill' => 68450, 'paid' => 68450, 'pending' => 0, 'status' => 'paid', 'card_tone' => 'regalia', 'bank_tone' => 'hdfc', 'bank_mark' => 'H'],
    ['card' => 'SBI Cashback', 'bank' => 'SBI Bank', 'month' => 'May 2024', 'bill_date' => '03 May 2024', 'due_date' => '18 May 2024', 'bill' => 45230, 'paid' => 20000, 'pending' => 25230, 'status' => 'partial', 'card_tone' => 'sbi', 'bank_tone' => 'sbi', 'bank_mark' => 'S'],
    ['card' => 'ICICI Amazon Pay', 'bank' => 'ICICI Bank', 'month' => 'May 2024', 'bill_date' => '01 May 2024', 'due_date' => '12 May 2024', 'bill' => 38750, 'paid' => 0, 'pending' => 38750, 'status' => 'unpaid', 'card_tone' => 'amazon', 'bank_tone' => 'icici', 'bank_mark' => 'I'],
    ['card' => 'Axis Flipkart', 'bank' => 'Axis Bank', 'month' => 'Apr 2024', 'bill_date' => '04 Apr 2024', 'due_date' => '20 Apr 2024', 'bill' => 52100, 'paid' => 52100, 'pending' => 0, 'status' => 'paid', 'card_tone' => 'axis', 'bank_tone' => 'axis', 'bank_mark' => 'A'],
    ['card' => 'IndusInd Legend', 'bank' => 'IndusInd Bank', 'month' => 'Apr 2024', 'bill_date' => '05 Apr 2024', 'due_date' => '22 Apr 2024', 'bill' => 29800, 'paid' => 15000, 'pending' => 14800, 'status' => 'partial', 'card_tone' => 'indus', 'bank_tone' => 'indus', 'bank_mark' => 'In'],
    ['card' => 'Amex Membership', 'bank' => 'American Express', 'month' => 'Apr 2024', 'bill_date' => '02 Apr 2024', 'due_date' => '16 Apr 2024', 'bill' => 86500, 'paid' => 86500, 'pending' => 0, 'status' => 'paid', 'card_tone' => 'amex', 'bank_tone' => 'amex', 'bank_mark' => 'AE'],
    ['card' => 'Yes Bank Marquee', 'bank' => 'Yes Bank', 'month' => 'Mar 2024', 'bill_date' => '03 Mar 2024', 'due_date' => '18 Mar 2024', 'bill' => 41200, 'paid' => 41200, 'pending' => 0, 'status' => 'paid', 'card_tone' => 'yes', 'bank_tone' => 'yes', 'bank_mark' => 'Y'],
    ['card' => 'Kotak Zen', 'bank' => 'Kotak Mahindra', 'month' => 'Mar 2024', 'bill_date' => '06 Mar 2024', 'due_date' => '21 Mar 2024', 'bill' => 27600, 'paid' => 0, 'pending' => 27600, 'status' => 'unpaid', 'card_tone' => 'kotak', 'bank_tone' => 'kotak', 'bank_mark' => 'K'],
    ['card' => 'IDFC First Select', 'bank' => 'IDFC First', 'month' => 'Mar 2024', 'bill_date' => '04 Mar 2024', 'due_date' => '19 Mar 2024', 'bill' => 36400, 'paid' => 36400, 'pending' => 0, 'status' => 'paid', 'card_tone' => 'idfc', 'bank_tone' => 'idfc', 'bank_mark' => 'IF'],
    ['card' => 'SC Ultimate', 'bank' => 'Standard Chartered', 'month' => 'Feb 2024', 'bill_date' => '02 Feb 2024', 'due_date' => '15 Feb 2024', 'bill' => 72220, 'paid' => 42220, 'pending' => 30000, 'status' => 'partial', 'card_tone' => 'sc', 'bank_tone' => 'sc', 'bank_mark' => 'SC'],
];

$totalBills = count($bills);
$totalBillAmount = array_sum(array_column($bills, 'bill'));
$totalPaid = array_sum(array_column($bills, 'paid'));
$totalPending = array_sum(array_column($bills, 'pending'));
$dueThisMonth = 64000;
$dueThisMonthCount = 2;

$statusMeta = [
    'paid' => ['label' => 'Paid', 'cls' => 'is-paid', 'icon' => 'fa-check-circle'],
    'partial' => ['label' => 'Partially Paid', 'cls' => 'is-partial', 'icon' => 'fa-clock'],
    'unpaid' => ['label' => 'Unpaid', 'cls' => 'is-unpaid', 'icon' => 'fa-exclamation-circle'],
];

ob_start();
?>
<div class="cc-bill-page">
    <div class="cc-bill-head">
        <div class="cc-bill-head-left">
            <a href="credit_cards/dashboard.php" class="cc-bill-back" title="Back" aria-label="Back">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="cc-bill-title">Monthly Bill Entry</h1>
                <nav class="cc-bill-crumb" aria-label="Breadcrumb">
                    <a href="credit_cards/dashboard.php">Dashboard</a>
                    <span class="sep">›</span>
                    <a href="credit_cards/cards.php">Credit Cards</a>
                    <span class="sep">›</span>
                    <a href="credit_cards/bills.php">Bills</a>
                    <span class="sep">›</span>
                    <span class="current">Monthly Bill Entry</span>
                </nav>
            </div>
        </div>
        <button type="button" class="btn cc-bill-add" id="ccAddBillBtn" data-toggle="modal" data-target="#ccAddBillModal">
            <i class="fas fa-plus mr-1"></i>Add Monthly Bill
        </button>
    </div>

    <div class="cc-bill-kpis">
        <div class="cc-bill-kpi">
            <span class="cc-bill-kpi-icon tone-red"><i class="fas fa-credit-card"></i></span>
            <div>
                <div class="k">Total Bills</div>
                <div class="v"><?= (int) $totalBills ?></div>
            </div>
        </div>
        <div class="cc-bill-kpi">
            <span class="cc-bill-kpi-icon tone-blue"><i class="fas fa-file-invoice"></i></span>
            <div>
                <div class="k">Total Bill Amount</div>
                <div class="v"><?= htmlspecialchars(ccBillInr($totalBillAmount), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <div class="cc-bill-kpi">
            <span class="cc-bill-kpi-icon tone-green"><i class="fas fa-check-circle"></i></span>
            <div>
                <div class="k">Paid Amount</div>
                <div class="v"><?= htmlspecialchars(ccBillInr($totalPaid), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <div class="cc-bill-kpi">
            <span class="cc-bill-kpi-icon tone-orange"><i class="fas fa-clock"></i></span>
            <div>
                <div class="k">Pending Amount</div>
                <div class="v"><?= htmlspecialchars(ccBillInr($totalPending), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <div class="cc-bill-kpi">
            <span class="cc-bill-kpi-icon tone-purple"><i class="fas fa-calendar-alt"></i></span>
            <div>
                <div class="k">Due This Month</div>
                <div class="v"><?= htmlspecialchars(ccBillInr($dueThisMonth), ENT_QUOTES, 'UTF-8') ?> <span class="sub">(<?= (int) $dueThisMonthCount ?> Bills)</span></div>
            </div>
        </div>
    </div>

    <div class="cc-bill-toolbar">
        <div class="cc-bill-search">
            <i class="fas fa-search"></i>
            <input type="search" id="ccBillSearch" placeholder="Search by card name, bank or month..." autocomplete="off" aria-label="Search bills">
        </div>
        <div class="cc-bill-filters">
            <div class="cc-bill-select">
                <i class="far fa-calendar-alt"></i>
                <select id="ccBillMonth" aria-label="Select Month">
                    <option value="">Select Month</option>
                    <option>May 2024</option>
                    <option>Apr 2024</option>
                    <option>Mar 2024</option>
                    <option>Feb 2024</option>
                </select>
            </div>
            <div class="cc-bill-select">
                <i class="fas fa-university"></i>
                <select id="ccBillBank" aria-label="Select Bank">
                    <option value="">Select Bank</option>
                    <option>HDFC Bank</option>
                    <option>SBI Bank</option>
                    <option>ICICI Bank</option>
                    <option>Axis Bank</option>
                    <option>IndusInd Bank</option>
                    <option>American Express</option>
                    <option>Yes Bank</option>
                    <option>Kotak Mahindra</option>
                    <option>IDFC First</option>
                    <option>Standard Chartered</option>
                </select>
            </div>
            <div class="cc-bill-select">
                <i class="fas fa-tag"></i>
                <select id="ccBillStatus" aria-label="Select Status">
                    <option value="">Select Status</option>
                    <option value="paid">Paid</option>
                    <option value="partial">Partially Paid</option>
                    <option value="unpaid">Unpaid</option>
                </select>
            </div>
            <div class="dropdown">
                <button type="button" class="btn cc-bill-export dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" disabled title="Coming soon">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="cc-bill-panel">
        <div class="cc-bill-table-wrap">
            <table class="cc-bill-table" id="ccBillTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Card Name</th>
                        <th>Bank</th>
                        <th>Bill Month</th>
                        <th>Bill Date</th>
                        <th>Due Date</th>
                        <th>Bill Amount</th>
                        <th>Paid Amount</th>
                        <th>Pending Amount</th>
                        <th>Status</th>
                        <th class="cc-bill-actions-col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bills as $i => $row):
                        $st = $statusMeta[$row['status']] ?? $statusMeta['unpaid'];
                        $search = strtolower($row['card'] . ' ' . $row['bank'] . ' ' . $row['month'] . ' ' . $row['status']);
                        ?>
                        <tr
                            data-search="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                            data-month="<?= htmlspecialchars($row['month'], ENT_QUOTES, 'UTF-8') ?>"
                            data-bank="<?= htmlspecialchars($row['bank'], ENT_QUOTES, 'UTF-8') ?>"
                            data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <td><?= $i + 1 ?></td>
                            <td>
                                <div class="cc-bill-card-cell">
                                    <span class="cc-bill-mini-card tone-<?= htmlspecialchars($row['card_tone'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                                        <span class="chip"></span>
                                    </span>
                                    <strong><?= htmlspecialchars($row['card'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                            </td>
                            <td>
                                <div class="cc-bill-bank-cell">
                                    <span class="cc-bill-bank-logo tone-<?= htmlspecialchars($row['bank_tone'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($row['bank_mark'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span><?= htmlspecialchars($row['bank'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="cc-bill-month"><i class="far fa-calendar-alt"></i><?= htmlspecialchars($row['month'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td><?= htmlspecialchars($row['bill_date'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="cc-bill-due"><i class="far fa-calendar-times"></i><?= htmlspecialchars($row['due_date'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td class="cc-bill-amt"><?= htmlspecialchars(ccBillInr($row['bill']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="cc-bill-paid"><?= htmlspecialchars(ccBillInr($row['paid']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="cc-bill-pending"><?= htmlspecialchars(ccBillInr($row['pending']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="cc-bill-status <?= htmlspecialchars($st['cls'], ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fas <?= htmlspecialchars($st['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                    <?= htmlspecialchars($st['label'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="cc-bill-actions-col">
                                <div class="cc-bill-actions">
                                    <button type="button" class="cc-bill-act is-view" title="View" aria-label="View" disabled><i class="far fa-eye"></i></button>
                                    <button type="button" class="cc-bill-act is-edit" title="Edit" aria-label="Edit" disabled><i class="fas fa-pen"></i></button>
                                    <button type="button" class="cc-bill-act is-del" title="Delete" aria-label="Delete" disabled><i class="fas fa-trash-alt"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="cc-bill-foot">
            <span id="ccBillShowing">Showing 1 to <?= (int) $totalBills ?> of <?= (int) $totalBills ?> entries</span>
            <div class="cc-bill-pager" aria-label="Pagination">
                <button type="button" class="cc-page-btn" disabled>&laquo;</button>
                <button type="button" class="cc-page-btn" disabled>&lsaquo;</button>
                <button type="button" class="cc-page-btn is-active">1</button>
                <button type="button" class="cc-page-btn" disabled>&rsaquo;</button>
                <button type="button" class="cc-page-btn" disabled>&raquo;</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Monthly Bill modal (UI only) -->
<div class="modal fade" id="ccAddBillModal" tabindex="-1" role="dialog" aria-labelledby="ccAddBillModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered cc-add-bill-dialog" role="document">
        <div class="modal-content cc-add-bill-content">
            <div class="cc-add-bill-header">
                <div class="cc-add-bill-header-main">
                    <span class="cc-add-bill-header-icon"><i class="far fa-calendar-alt"></i></span>
                    <div>
                        <h5 class="cc-add-bill-title" id="ccAddBillModalTitle">Add Monthly Bill</h5>
                        <p class="cc-add-bill-sub">Create a new monthly bill for a credit card.</p>
                    </div>
                </div>
                <div class="cc-add-bill-deco" aria-hidden="true">
                    <span class="cc-add-bill-deco-card is-dark"></span>
                    <span class="cc-add-bill-deco-card is-red"></span>
                    <span class="cc-add-bill-deco-cal"><i class="fas fa-check"></i></span>
                </div>
                <button type="button" class="cc-add-bill-close" data-dismiss="modal" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="ccAddBillForm" autocomplete="off" onsubmit="return false;">
                <div class="cc-add-bill-body">
                    <div class="cc-add-bill-section">
                        <div class="cc-add-bill-section-head">
                            <i class="fas fa-credit-card"></i>
                            <span>Card &amp; Billing Details</span>
                        </div>
                        <div class="cc-add-bill-grid">
                            <div class="cc-add-bill-field">
                                <label for="ccBillCard">Select Card <span class="req">*</span></label>
                                <div class="cc-add-bill-input is-select has-card">
                                    <span class="cc-add-bill-mini-card" aria-hidden="true"></span>
                                    <select class="form-control" id="ccBillCard" name="card">
                                        <option value="">Select Card</option>
                                        <option value="HDFC Regalia" selected>HDFC Regalia</option>
                                        <option value="SBI Cashback">SBI Cashback</option>
                                        <option value="ICICI Amazon Pay">ICICI Amazon Pay</option>
                                        <option value="Axis Flipkart">Axis Flipkart</option>
                                        <option value="IndusInd Legend">IndusInd Legend</option>
                                        <option value="Amex Membership">Amex Membership</option>
                                    </select>
                                    <i class="fas fa-chevron-down caret"></i>
                                </div>
                            </div>
                            <div class="cc-add-bill-field">
                                <label for="ccBillMonthModal">Bill Month <span class="req">*</span></label>
                                <div class="cc-add-bill-input is-select">
                                    <i class="far fa-calendar-alt"></i>
                                    <select class="form-control" id="ccBillMonthModal" name="bill_month">
                                        <option value="">Select Month</option>
                                        <option value="May 2024" selected>May 2024</option>
                                        <option value="Apr 2024">Apr 2024</option>
                                        <option value="Mar 2024">Mar 2024</option>
                                        <option value="Feb 2024">Feb 2024</option>
                                    </select>
                                    <i class="fas fa-chevron-down caret"></i>
                                </div>
                            </div>
                            <div class="cc-add-bill-field">
                                <label for="ccBillDate">Bill Date <span class="req">*</span></label>
                                <div class="cc-add-bill-input">
                                    <i class="far fa-calendar"></i>
                                    <input type="date" class="form-control" id="ccBillDate" name="bill_date" value="2024-05-02">
                                </div>
                            </div>
                            <div class="cc-add-bill-field">
                                <label for="ccDueDate">Due Date <span class="req">*</span></label>
                                <div class="cc-add-bill-input">
                                    <i class="far fa-calendar-check"></i>
                                    <input type="date" class="form-control" id="ccDueDate" name="due_date" value="2024-05-15">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="cc-add-bill-section">
                        <div class="cc-add-bill-section-head">
                            <i class="fas fa-layer-group"></i>
                            <span>Amount Details</span>
                        </div>
                        <div class="cc-add-bill-grid">
                            <div class="cc-add-bill-field">
                                <label for="ccTotalBillAmt">Total Bill Amount <span class="req">*</span></label>
                                <div class="cc-add-bill-input">
                                    <span class="cc-rupee is-red">₹</span>
                                    <input type="text" class="form-control" id="ccTotalBillAmt" name="total_amount" inputmode="numeric" value="68,450" placeholder="e.g. 68,450">
                                </div>
                            </div>
                            <div class="cc-add-bill-field">
                                <label for="ccPaidAmt">Paid Amount</label>
                                <div class="cc-add-bill-input">
                                    <span class="cc-rupee">₹</span>
                                    <input type="text" class="form-control" id="ccPaidAmt" name="paid_amount" inputmode="numeric" value="68,450" placeholder="e.g. 68,450">
                                </div>
                            </div>
                            <div class="cc-add-bill-field">
                                <label for="ccPendingAmt">Pending Amount</label>
                                <div class="cc-add-bill-input is-readonly">
                                    <span class="cc-rupee is-blue">₹</span>
                                    <input type="text" class="form-control" id="ccPendingAmt" name="pending_amount" value="0" readonly tabindex="-1">
                                </div>
                            </div>
                            <div class="cc-add-bill-field">
                                <label>Mark as Paid</label>
                                <label class="cc-mark-paid is-on" id="ccMarkPaidWrap">
                                    <input type="checkbox" id="ccMarkPaid" checked>
                                    <span class="box"><i class="fas fa-check"></i></span>
                                    <span class="txt">Mark as Paid</span>
                                </label>
                            </div>
                            <div class="cc-add-bill-field cc-add-bill-field-full">
                                <label for="ccBillNotes">Notes <span class="opt">(Optional)</span></label>
                                <div class="cc-add-bill-input is-textarea">
                                    <i class="far fa-file-alt"></i>
                                    <textarea class="form-control" id="ccBillNotes" name="notes" rows="3" placeholder="Add note (e.g. payment reference, remark, etc.)"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cc-add-bill-footer">
                    <button type="button" class="btn cc-add-bill-cancel" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn cc-add-bill-save" id="ccSaveBillBtn">
                        <i class="fas fa-save"></i> Save Bill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$ccBodyHtml = ob_get_clean();

$ccExtraHead = <<<'CSS'
<style>
.cc-bill-page {
    --cc-red: #e11d2e;
    --cc-border: #e7ebf1;
    --cc-text: #0f172a;
    --cc-muted: #64748b;
    padding: .35rem 0 1.5rem;
}
.cc-bill-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem;
}
.cc-bill-head-left { display: flex; align-items: flex-start; gap: .75rem; min-width: 0; }
.cc-bill-back {
    width: 36px; height: 36px; border-radius: 8px; border: 1px solid #e2e8f0;
    background: #fff; color: #475569; display: inline-flex; align-items: center;
    justify-content: center; flex: 0 0 36px; margin-top: .15rem; text-decoration: none;
    box-shadow: 0 1px 2px rgba(15,23,42,.04);
}
.cc-bill-back:hover { background: #f8fafc; color: #0f172a; }
.cc-bill-title {
    margin: 0; font-size: 1.45rem; font-weight: 750; color: var(--cc-text); line-height: 1.2;
}
.cc-bill-crumb {
    margin-top: .3rem; font-size: .8rem; color: var(--cc-muted);
    display: flex; flex-wrap: wrap; align-items: center; gap: .3rem;
}
.cc-bill-crumb a { color: var(--cc-muted); text-decoration: none; }
.cc-bill-crumb a:hover { color: #2563eb; }
.cc-bill-crumb .sep { color: #94a3b8; }
.cc-bill-crumb .current { color: #334155; font-weight: 600; }
.cc-bill-add {
    background: var(--cc-red); border: 1px solid var(--cc-red); color: #fff;
    border-radius: 10px; font-weight: 650; font-size: .88rem; padding: .55rem 1.05rem;
    box-shadow: 0 6px 16px rgba(225,29,46,.22);
}
.cc-bill-add:hover { background: #c81a28; border-color: #c81a28; color: #fff; }

.cc-bill-kpis {
    display: grid; grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: .7rem; margin-bottom: .9rem;
}
.cc-bill-kpi {
    background: #fff; border: 1px solid var(--cc-border); border-radius: 14px;
    padding: .85rem .9rem; display: flex; align-items: center; gap: .75rem;
    box-shadow: 0 4px 14px rgba(15,23,42,.04); min-width: 0;
}
.cc-bill-kpi-icon {
    width: 42px; height: 42px; border-radius: 12px; flex: 0 0 42px;
    display: inline-flex; align-items: center; justify-content: center; font-size: 1rem;
}
.cc-bill-kpi-icon.tone-red { background: #fee2e2; color: #dc2626; }
.cc-bill-kpi-icon.tone-blue { background: #dbeafe; color: #2563eb; }
.cc-bill-kpi-icon.tone-green { background: #dcfce7; color: #16a34a; }
.cc-bill-kpi-icon.tone-orange { background: #ffedd5; color: #ea580c; }
.cc-bill-kpi-icon.tone-purple { background: #ede9fe; color: #7c3aed; }
.cc-bill-kpi .k {
    font-size: .72rem; font-weight: 650; color: var(--cc-muted);
    text-transform: uppercase; letter-spacing: .03em; margin-bottom: .15rem;
}
.cc-bill-kpi .v {
    font-size: 1.05rem; font-weight: 750; color: var(--cc-text); line-height: 1.2;
    word-break: break-word;
}
.cc-bill-kpi .sub { font-size: .78rem; font-weight: 600; color: var(--cc-muted); }

.cc-bill-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    gap: .75rem; flex-wrap: wrap; margin-bottom: .85rem;
}
.cc-bill-search {
    position: relative; flex: 1 1 280px; max-width: 420px;
}
.cc-bill-search i {
    position: absolute; left: .85rem; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: .85rem; pointer-events: none;
}
.cc-bill-search input {
    width: 100%; height: 40px; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 0 1rem 0 2.35rem; background: #fff; color: var(--cc-text);
    font-size: .88rem; box-shadow: 0 1px 2px rgba(15,23,42,.03);
}
.cc-bill-search input:focus {
    outline: 0; border-color: #fda4af; box-shadow: 0 0 0 3px rgba(225,29,46,.1);
}
.cc-bill-filters { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.cc-bill-select {
    position: relative; min-width: 150px;
}
.cc-bill-select > i {
    position: absolute; left: .75rem; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: .8rem; pointer-events: none; z-index: 1;
}
.cc-bill-select select {
    width: 100%; height: 40px; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 0 1.8rem 0 2.15rem; background: #fff; color: #334155;
    font-size: .84rem; font-weight: 600; appearance: none; -webkit-appearance: none;
    box-shadow: 0 1px 2px rgba(15,23,42,.03);
}
.cc-bill-select::after {
    content: "\f078"; font-family: "Font Awesome 5 Free"; font-weight: 900;
    position: absolute; right: .75rem; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: .65rem; pointer-events: none;
}
.cc-bill-export {
    height: 40px; border: 1px solid #e2e8f0; background: #fff; color: #334155;
    border-radius: 10px; font-size: .84rem; font-weight: 600; padding: 0 .9rem;
    display: inline-flex; align-items: center; gap: .4rem;
}
.cc-bill-export:disabled { opacity: .75; cursor: not-allowed; }

.cc-bill-panel {
    background: #fff; border: 1px solid var(--cc-border); border-radius: 14px;
    box-shadow: 0 1px 2px rgba(15,23,42,.04); overflow: hidden;
}
.cc-bill-table-wrap { overflow: auto; }
.cc-bill-table {
    width: 100%; border-collapse: collapse; min-width: 1180px; margin: 0;
}
.cc-bill-table th {
    background: #fff1f2; color: #0f172a; font-size: .74rem; font-weight: 750;
    text-align: left; padding: .8rem .85rem; border-bottom: 1px solid #fecaca;
    white-space: nowrap; text-transform: uppercase; letter-spacing: .03em;
}
.cc-bill-table td {
    padding: .85rem .85rem; border-bottom: 1px solid #f1f5f9;
    color: #1e293b; font-size: .86rem; vertical-align: middle;
}
.cc-bill-table tbody tr:last-child td { border-bottom: 0; }
.cc-bill-table tbody tr:hover { background: #fafbfc; }
.cc-bill-table tbody tr.is-hidden { display: none; }

.cc-bill-card-cell, .cc-bill-bank-cell {
    display: flex; align-items: center; gap: .65rem; min-width: 0;
}
.cc-bill-mini-card {
    width: 40px; height: 26px; border-radius: 6px; flex: 0 0 40px;
    position: relative; overflow: hidden;
    box-shadow: 0 2px 6px rgba(15,23,42,.16);
}
.cc-bill-mini-card .chip {
    position: absolute; left: 5px; top: 6px; width: 10px; height: 7px;
    border-radius: 2px; background: rgba(255,255,255,.45);
}
.cc-bill-mini-card.tone-regalia { background: linear-gradient(135deg, #1f2937, #111827); }
.cc-bill-mini-card.tone-sbi { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
.cc-bill-mini-card.tone-amazon { background: linear-gradient(135deg, #f97316, #ea580c); }
.cc-bill-mini-card.tone-axis { background: linear-gradient(135deg, #7c3aed, #5b21b6); }
.cc-bill-mini-card.tone-indus { background: linear-gradient(135deg, #b45309, #92400e); }
.cc-bill-mini-card.tone-amex { background: linear-gradient(135deg, #0ea5e9, #0369a1); }
.cc-bill-mini-card.tone-yes { background: linear-gradient(135deg, #0d9488, #0f766e); }
.cc-bill-mini-card.tone-kotak { background: linear-gradient(135deg, #dc2626, #991b1b); }
.cc-bill-mini-card.tone-idfc { background: linear-gradient(135deg, #9a3412, #7c2d12); }
.cc-bill-mini-card.tone-sc { background: linear-gradient(135deg, #065f46, #064e3b); }

.cc-bill-bank-logo {
    width: 26px; height: 26px; border-radius: 8px; flex: 0 0 26px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .58rem; font-weight: 800; color: #fff;
}
.cc-bill-bank-logo.tone-hdfc { background: #1d4ed8; }
.cc-bill-bank-logo.tone-sbi { background: #2563eb; }
.cc-bill-bank-logo.tone-icici { background: #f97316; }
.cc-bill-bank-logo.tone-axis { background: #7c3aed; }
.cc-bill-bank-logo.tone-indus { background: #b45309; }
.cc-bill-bank-logo.tone-amex { background: #0284c7; }
.cc-bill-bank-logo.tone-yes { background: #0d9488; }
.cc-bill-bank-logo.tone-kotak { background: #dc2626; }
.cc-bill-bank-logo.tone-idfc { background: #9a3412; }
.cc-bill-bank-logo.tone-sc { background: #047857; }

.cc-bill-month, .cc-bill-due {
    display: inline-flex; align-items: center; gap: .35rem; white-space: nowrap;
}
.cc-bill-month i { color: #64748b; }
.cc-bill-due { color: #dc2626; font-weight: 650; }
.cc-bill-due i { color: #ef4444; }
.cc-bill-amt { font-weight: 650; white-space: nowrap; }
.cc-bill-paid { color: #16a34a; font-weight: 750; white-space: nowrap; }
.cc-bill-pending { color: #ea580c; font-weight: 750; white-space: nowrap; }

.cc-bill-status {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .28rem .6rem; border-radius: 999px;
    font-size: .72rem; font-weight: 750; white-space: nowrap;
}
.cc-bill-status.is-paid { background: #dcfce7; color: #15803d; }
.cc-bill-status.is-partial { background: #ffedd5; color: #c2410c; }
.cc-bill-status.is-unpaid { background: #fee2e2; color: #b91c1c; }

.cc-bill-actions-col { width: 110px; text-align: right; }
.cc-bill-actions { display: inline-flex; align-items: center; gap: .3rem; }
.cc-bill-act {
    width: 30px; height: 30px; border-radius: 8px; border: 1px solid #e2e8f0;
    background: #fff; display: inline-flex; align-items: center; justify-content: center;
    font-size: .78rem; cursor: not-allowed;
}
.cc-bill-act.is-view, .cc-bill-act.is-edit { color: #2563eb; }
.cc-bill-act.is-del { color: #e11d48; border-color: #fecaca; }
.cc-bill-act:disabled { opacity: .9; }

.cc-bill-foot {
    display: flex; align-items: center; justify-content: space-between;
    gap: .75rem; flex-wrap: wrap; padding: .85rem 1rem;
    border-top: 1px solid #f1f5f9; color: var(--cc-muted); font-size: .82rem; font-weight: 500;
}
.cc-bill-pager { display: inline-flex; align-items: center; gap: .3rem; }
.cc-page-btn {
    min-width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0;
    background: #fff; color: #475569; font-size: .8rem; font-weight: 650;
    display: inline-flex; align-items: center; justify-content: center; padding: 0 .45rem;
}
.cc-page-btn.is-active {
    background: var(--cc-red); border-color: var(--cc-red); color: #fff;
}
.cc-page-btn:disabled { opacity: .55; cursor: not-allowed; }

@media (max-width: 1399.98px) {
    .cc-bill-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 991.98px) {
    .cc-bill-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .cc-bill-search { max-width: none; }
}
@media (max-width: 767.98px) {
    .cc-bill-title { font-size: 1.2rem; }
    .cc-bill-kpis { grid-template-columns: 1fr; }
    .cc-bill-filters { width: 100%; }
    .cc-bill-select { flex: 1 1 140px; }
}

/* Add Monthly Bill modal */
#ccAddBillModal .cc-add-bill-dialog {
    max-width: 680px; width: calc(100% - 1.5rem); margin: 1rem auto;
}
#ccAddBillModal .cc-add-bill-content {
    border: 0; border-radius: 22px; overflow: hidden;
    box-shadow: 0 24px 60px rgba(15,23,42,.28);
}
#ccAddBillModal .cc-add-bill-header {
    position: relative; display: flex; align-items: center; gap: 1rem;
    padding: 1.25rem 3.2rem 1.25rem 1.25rem;
    background:
        radial-gradient(ellipse 80% 120% at 100% 0%, rgba(255,255,255,.18), transparent 55%),
        linear-gradient(135deg, #e11d2e 0%, #c81a28 48%, #9f1239 100%);
    color: #fff; overflow: hidden;
}
#ccAddBillModal .cc-add-bill-header::before {
    content: ""; position: absolute; inset: auto -10% -55% -10%; height: 120%;
    background: radial-gradient(circle at 20% 40%, rgba(255,255,255,.12), transparent 42%),
                radial-gradient(circle at 70% 60%, rgba(255,255,255,.1), transparent 45%);
    pointer-events: none;
}
#ccAddBillModal .cc-add-bill-header-main {
    position: relative; z-index: 1; display: flex; align-items: flex-start; gap: .85rem; min-width: 0; flex: 1 1 auto;
}
#ccAddBillModal .cc-add-bill-header-icon {
    width: 46px; height: 46px; border-radius: 12px; flex: 0 0 46px;
    display: inline-flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.28);
    font-size: 1.15rem; color: #fff;
}
#ccAddBillModal .cc-add-bill-title {
    margin: 0; font-size: 1.2rem; font-weight: 750; line-height: 1.25; color: #fff;
}
#ccAddBillModal .cc-add-bill-sub {
    margin: .28rem 0 0; font-size: .82rem; color: rgba(255,255,255,.88); line-height: 1.4;
}
#ccAddBillModal .cc-add-bill-deco {
    position: relative; z-index: 1; width: 104px; height: 64px; flex: 0 0 104px; display: none;
}
@media (min-width: 576px) {
    #ccAddBillModal .cc-add-bill-deco { display: block; }
}
#ccAddBillModal .cc-add-bill-deco-card {
    position: absolute; width: 58px; height: 36px; border-radius: 8px;
    box-shadow: 0 8px 18px rgba(0,0,0,.22);
}
#ccAddBillModal .cc-add-bill-deco-card.is-dark {
    left: 0; top: 16px; background: linear-gradient(135deg, #1f2937, #0f172a);
    transform: rotate(-12deg);
}
#ccAddBillModal .cc-add-bill-deco-card.is-red {
    left: 22px; top: 4px; background: linear-gradient(135deg, #fb7185, #e11d2e);
    transform: rotate(10deg);
}
#ccAddBillModal .cc-add-bill-deco-cal {
    position: absolute; right: 0; bottom: 2px; width: 34px; height: 34px; border-radius: 10px;
    background: #fff; color: #16a34a; display: inline-flex; align-items: center; justify-content: center;
    box-shadow: 0 8px 16px rgba(0,0,0,.18); font-size: .85rem;
}
#ccAddBillModal .cc-add-bill-close {
    position: absolute; z-index: 2; top: .85rem; right: .85rem;
    width: 32px; height: 32px; border-radius: 50%; border: 0;
    background: rgba(255,255,255,.18); color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .85rem; cursor: pointer;
}
#ccAddBillModal .cc-add-bill-close:hover { background: rgba(255,255,255,.28); }

#ccAddBillModal .cc-add-bill-body { padding: 1.1rem 1.25rem .35rem; background: #fff; }
#ccAddBillModal .cc-add-bill-section { margin-bottom: 1rem; }
#ccAddBillModal .cc-add-bill-section-head {
    display: flex; align-items: center; gap: .5rem;
    background: #fff1f2; color: #e11d2e; border-radius: 10px;
    padding: .55rem .75rem; font-size: .88rem; font-weight: 750; margin-bottom: .85rem;
}
#ccAddBillModal .cc-add-bill-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: .85rem .9rem;
}
#ccAddBillModal .cc-add-bill-field-full { grid-column: 1 / -1; }
#ccAddBillModal .cc-add-bill-field > label {
    display: block; margin: 0 0 .35rem; color: #334155;
    font-size: .8rem; font-weight: 650;
}
#ccAddBillModal .cc-add-bill-field .req { color: #e11d2e; }
#ccAddBillModal .cc-add-bill-field .opt { color: #94a3b8; font-weight: 550; }

#ccAddBillModal .cc-add-bill-input {
    position: relative; display: flex; align-items: center;
}
#ccAddBillModal .cc-add-bill-input > i:first-child,
#ccAddBillModal .cc-add-bill-input .cc-rupee {
    position: absolute; left: .85rem; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: .85rem; z-index: 1; pointer-events: none;
}
#ccAddBillModal .cc-add-bill-input .cc-rupee {
    font-weight: 700; font-style: normal; font-size: .95rem;
}
#ccAddBillModal .cc-add-bill-input .cc-rupee.is-red { color: #e11d2e; }
#ccAddBillModal .cc-add-bill-input .cc-rupee.is-blue { color: #2563eb; }
#ccAddBillModal .cc-add-bill-input .form-control {
    height: 42px; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 0 2.1rem 0 2.35rem; font-size: .9rem; color: #0f172a;
    background: #fff; box-shadow: none;
}
#ccAddBillModal .cc-add-bill-input .form-control:focus {
    border-color: #fda4af; box-shadow: 0 0 0 3px rgba(225,29,46,.12);
}
#ccAddBillModal .cc-add-bill-input.is-select .form-control {
    appearance: none; -webkit-appearance: none; cursor: pointer;
}
#ccAddBillModal .cc-add-bill-input .caret {
    position: absolute; right: .85rem; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: .7rem; pointer-events: none;
}
#ccAddBillModal .cc-add-bill-input.has-card .form-control { padding-left: 2.7rem; }
#ccAddBillModal .cc-add-bill-mini-card {
    position: absolute; left: .7rem; top: 50%; transform: translateY(-50%);
    width: 28px; height: 18px; border-radius: 4px; z-index: 1;
    background: linear-gradient(135deg, #1f2937, #111827);
    box-shadow: 0 1px 3px rgba(15,23,42,.2); pointer-events: none;
}
#ccAddBillModal .cc-add-bill-input.is-readonly .form-control {
    background: #f1f5f9; color: #334155; cursor: default;
}
#ccAddBillModal .cc-add-bill-input.is-textarea { align-items: flex-start; }
#ccAddBillModal .cc-add-bill-input.is-textarea > i {
    top: .95rem; transform: none;
}
#ccAddBillModal .cc-add-bill-input.is-textarea .form-control {
    height: auto; min-height: 88px; padding: .75rem .95rem .75rem 2.35rem;
    resize: vertical; line-height: 1.45;
}

#ccAddBillModal .cc-mark-paid {
    margin: 0; height: 42px; border: 1px solid #bbf7d0; border-radius: 10px;
    background: #dcfce7; color: #15803d; display: flex; align-items: center; gap: .6rem;
    padding: 0 .9rem; cursor: pointer; user-select: none; font-size: .84rem; font-weight: 700;
}
#ccAddBillModal .cc-mark-paid input {
    position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none;
}
#ccAddBillModal .cc-mark-paid .box {
    width: 18px; height: 18px; border-radius: 5px; border: 2px solid #e11d2e;
    background: #fff; color: #e11d2e; display: inline-flex; align-items: center; justify-content: center;
    font-size: .65rem; flex: 0 0 18px;
}
#ccAddBillModal .cc-mark-paid .box i { opacity: 0; }
#ccAddBillModal .cc-mark-paid.is-on .box i { opacity: 1; }
#ccAddBillModal .cc-mark-paid:not(.is-on) {
    background: #fff; border-color: #e2e8f0; color: #475569;
}
#ccAddBillModal .cc-mark-paid:not(.is-on) .box { border-color: #cbd5e1; color: transparent; }

#ccAddBillModal .cc-add-bill-footer {
    display: flex; align-items: center; justify-content: flex-end; gap: .65rem;
    padding: .95rem 1.25rem 1.15rem; border-top: 1px solid #eef2f7; background: #fff;
}
#ccAddBillModal .cc-add-bill-cancel {
    min-width: 110px; height: 42px; border-radius: 10px; border: 1px solid #e2e8f0;
    background: #fff; color: #334155; font-weight: 650; font-size: .88rem;
    display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
}
#ccAddBillModal .cc-add-bill-cancel:hover { background: #f8fafc; }
#ccAddBillModal .cc-add-bill-save {
    min-width: 120px; height: 42px; border-radius: 10px; border: 1px solid #e11d2e;
    background: #e11d2e; color: #fff; font-weight: 700; font-size: .88rem;
    display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
    box-shadow: 0 8px 18px rgba(225,29,46,.25);
}
#ccAddBillModal .cc-add-bill-save:hover { background: #c81a28; border-color: #c81a28; color: #fff; }

@media (max-width: 575.98px) {
    #ccAddBillModal .cc-add-bill-grid { grid-template-columns: 1fr; }
    #ccAddBillModal .cc-add-bill-footer { flex-direction: column-reverse; }
    #ccAddBillModal .cc-add-bill-cancel,
    #ccAddBillModal .cc-add-bill-save { width: 100%; }
}
</style>
CSS;

$ccExtraFooter = <<<'JS'
<script>
(function ($) {
  var $rows = $('#ccBillTable tbody tr');
  var total = $rows.length;

  function updateShowing(visible) {
    if (!visible) {
      $('#ccBillShowing').text('Showing 0 of ' + total + ' entries');
      return;
    }
    $('#ccBillShowing').text('Showing 1 to ' + visible + ' of ' + total + ' entries');
  }

  function applyFilters() {
    var q = String($('#ccBillSearch').val() || '').trim().toLowerCase();
    var month = String($('#ccBillMonth').val() || '');
    var bank = String($('#ccBillBank').val() || '');
    var status = String($('#ccBillStatus').val() || '');
    var visible = 0;
    $rows.each(function () {
      var $row = $(this);
      var hay = String($row.attr('data-search') || '');
      var ok = (!q || hay.indexOf(q) !== -1)
        && (!month || $row.attr('data-month') === month)
        && (!bank || $row.attr('data-bank') === bank)
        && (!status || $row.attr('data-status') === status);
      $row.toggleClass('is-hidden', !ok);
      if (ok) visible++;
    });
    updateShowing(visible);
  }

  $('#ccBillSearch').on('input', applyFilters);
  $('#ccBillMonth, #ccBillBank, #ccBillStatus').on('change', applyFilters);

  function parseAmt(v) {
    return parseInt(String(v || '').replace(/[^\d]/g, ''), 10) || 0;
  }
  function formatAmt(n) {
    return Number(n || 0).toLocaleString('en-IN');
  }
  function syncPending(fromMarkPaid) {
    var totalAmt = parseAmt($('#ccTotalBillAmt').val());
    var paid = parseAmt($('#ccPaidAmt').val());
    if (fromMarkPaid && $('#ccMarkPaid').is(':checked')) {
      paid = totalAmt;
      $('#ccPaidAmt').val(formatAmt(paid));
    }
    if (paid > totalAmt) {
      paid = totalAmt;
      $('#ccPaidAmt').val(formatAmt(paid));
    }
    var pending = Math.max(0, totalAmt - paid);
    $('#ccPendingAmt').val(formatAmt(pending));
    var fullyPaid = totalAmt > 0 && pending === 0;
    $('#ccMarkPaid').prop('checked', fullyPaid);
    $('#ccMarkPaidWrap').toggleClass('is-on', fullyPaid);
  }

  function resetAddBillForm() {
    var form = document.getElementById('ccAddBillForm');
    if (form) form.reset();
    $('#ccBillCard').val('HDFC Regalia');
    $('#ccBillMonthModal').val('May 2024');
    $('#ccBillDate').val('2024-05-02');
    $('#ccDueDate').val('2024-05-15');
    $('#ccTotalBillAmt').val('68,450');
    $('#ccPaidAmt').val('68,450');
    $('#ccPendingAmt').val('0');
    $('#ccBillNotes').val('');
    $('#ccMarkPaid').prop('checked', true);
    $('#ccMarkPaidWrap').addClass('is-on');
  }

  $('#ccAddBillModal').on('show.bs.modal', resetAddBillForm);

  $('#ccTotalBillAmt, #ccPaidAmt').on('input', function () {
    var raw = String(this.value || '').replace(/[^\d]/g, '');
    this.value = raw ? formatAmt(raw) : '';
    syncPending(false);
  });

  $('#ccMarkPaid').on('change', function () {
    if ($(this).is(':checked')) {
      syncPending(true);
    } else {
      $('#ccPaidAmt').val('0');
      syncPending(false);
    }
  });

  $('#ccAddBillForm').on('submit', function (e) {
    e.preventDefault();
    // UI-only stage: close modal without backend save.
    $('#ccAddBillModal').modal('hide');
  });
})(jQuery);
</script>
JS;

$ccPageTitle = 'Monthly Bill Entry';
$ccBodyClass = 'cc-bill-entry-page';
include __DIR__ . '/_layout.php';
