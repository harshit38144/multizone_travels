<?php
/**
 * Credit Cards — Card List (UI prototype with sample data).
 */

function ccListInr($n): string
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

$cards = [
    [
        'name' => 'HDFC Regalia',
        'bank' => 'HDFC Bank',
        'last4' => '1234',
        'limit' => 200000,
        'available' => 131550,
        'status' => 'active',
        'card_tone' => 'regalia',
        'bank_tone' => 'hdfc',
        'bank_mark' => 'H',
    ],
    [
        'name' => 'SBI Cashback',
        'bank' => 'SBI Bank',
        'last4' => '2345',
        'limit' => 150000,
        'available' => 104770,
        'status' => 'active',
        'card_tone' => 'sbi',
        'bank_tone' => 'sbi',
        'bank_mark' => 'S',
    ],
    [
        'name' => 'ICICI Amazon Pay',
        'bank' => 'ICICI Bank',
        'last4' => '3456',
        'limit' => 100000,
        'available' => 68000,
        'status' => 'active',
        'card_tone' => 'amazon',
        'bank_tone' => 'icici',
        'bank_mark' => 'I',
    ],
    [
        'name' => 'Axis Flipkart',
        'bank' => 'Axis Bank',
        'last4' => '4567',
        'limit' => 120000,
        'available' => 91250,
        'status' => 'active',
        'card_tone' => 'axis',
        'bank_tone' => 'axis',
        'bank_mark' => 'A',
    ],
    [
        'name' => 'IndusInd Legend',
        'bank' => 'IndusInd Bank',
        'last4' => '5678',
        'limit' => 125000,
        'available' => 106400,
        'status' => 'active',
        'card_tone' => 'indus',
        'bank_tone' => 'indus',
        'bank_mark' => 'In',
    ],
    [
        'name' => 'Amex Membership Rewards',
        'bank' => 'American Express',
        'last4' => '6789',
        'limit' => 180000,
        'available' => 151600,
        'status' => 'active',
        'card_tone' => 'amex',
        'bank_tone' => 'amex',
        'bank_mark' => 'AE',
    ],
    [
        'name' => 'Yes Bank Marquee',
        'bank' => 'Yes Bank',
        'last4' => '7890',
        'limit' => 90000,
        'available' => 74210,
        'status' => 'inactive',
        'card_tone' => 'yes',
        'bank_tone' => 'yes',
        'bank_mark' => 'Y',
    ],
    [
        'name' => 'Kotak Zen',
        'bank' => 'Kotak Mahindra',
        'last4' => '8901',
        'limit' => 75000,
        'available' => 58340,
        'status' => 'active',
        'card_tone' => 'kotak',
        'bank_tone' => 'kotak',
        'bank_mark' => 'K',
    ],
    [
        'name' => 'IDFC First Select',
        'bank' => 'IDFC First',
        'last4' => '9012',
        'limit' => 110000,
        'available' => 88450,
        'status' => 'active',
        'card_tone' => 'idfc',
        'bank_tone' => 'idfc',
        'bank_mark' => 'IF',
    ],
    [
        'name' => 'Standard Chartered Ultimate',
        'bank' => 'Standard Chartered',
        'last4' => '0123',
        'limit' => 160000,
        'available' => 129870,
        'status' => 'active',
        'card_tone' => 'sc',
        'bank_tone' => 'sc',
        'bank_mark' => 'SC',
    ],
];

$totalCards = count($cards);

ob_start();
?>
<div class="cc-list-page">
    <div class="cc-list-head">
        <div class="cc-list-head-left">
            <a href="credit_cards/dashboard.php" class="cc-list-back" title="Back to Dashboard" aria-label="Back to Dashboard">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="cc-list-title">Card List</h1>
                <nav class="cc-list-crumb" aria-label="Breadcrumb">
                    <a href="credit_cards/dashboard.php">Dashboard</a>
                    <span class="sep">›</span>
                    <a href="credit_cards/cards.php">Credit Cards</a>
                    <span class="sep">›</span>
                    <span class="current">Card List</span>
                </nav>
            </div>
        </div>
        <button type="button" class="btn cc-list-add" id="ccAddCardBtn" data-toggle="modal" data-target="#ccAddCardModal">
            <i class="fas fa-plus mr-1"></i>Add New Card
        </button>
    </div>

    <div class="cc-list-toolbar">
        <div class="cc-list-search">
            <i class="fas fa-search"></i>
            <input type="search" id="ccCardSearch" placeholder="Search card..." autocomplete="off" aria-label="Search card">
        </div>
        <div class="cc-list-tools">
            <div class="dropdown">
                <button type="button" class="btn cc-tool-btn dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="#">All Status</a>
                    <a class="dropdown-item" href="#">Active</a>
                    <a class="dropdown-item" href="#">Inactive</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="#">All Banks</a>
                </div>
            </div>
            <div class="dropdown">
                <button type="button" class="btn cc-tool-btn dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-sort-amount-down"></i> Sort By
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="#">Card Name (A–Z)</a>
                    <a class="dropdown-item" href="#">Credit Limit (High–Low)</a>
                    <a class="dropdown-item" href="#">Available Limit (High–Low)</a>
                    <a class="dropdown-item" href="#">Bank</a>
                </div>
            </div>
            <button type="button" class="btn cc-tool-btn" disabled title="Coming soon">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>

    <div class="cc-list-panel">
        <div class="cc-list-table-wrap">
            <table class="cc-list-table" id="ccCardTable">
                <thead>
                    <tr>
                        <th>Card Name</th>
                        <th>Bank</th>
                        <th>Last 4 Digits</th>
                        <th>Credit Limit</th>
                        <th>Available Limit</th>
                        <th>Status</th>
                        <th class="cc-col-actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cards as $card): ?>
                        <tr data-search="<?= htmlspecialchars(strtolower($card['name'] . ' ' . $card['bank'] . ' ' . $card['last4']), ENT_QUOTES, 'UTF-8') ?>">
                            <td>
                                <div class="cc-card-cell">
                                    <span class="cc-mini-card tone-<?= htmlspecialchars($card['card_tone'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                                        <span class="chip"></span>
                                        <span class="chip chip-sm"></span>
                                    </span>
                                    <span class="cc-card-label"><?= htmlspecialchars($card['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="cc-bank-cell">
                                    <span class="cc-bank-logo tone-<?= htmlspecialchars($card['bank_tone'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($card['bank_mark'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span><?= htmlspecialchars($card['bank'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </td>
                            <td class="cc-digits"><?= htmlspecialchars($card['last4'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="cc-money"><?= htmlspecialchars(ccListInr($card['limit']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="cc-money"><?= htmlspecialchars(ccListInr($card['available']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($card['status'] === 'active'): ?>
                                    <span class="cc-status-pill is-active"><span class="dot"></span>Active</span>
                                <?php else: ?>
                                    <span class="cc-status-pill is-inactive"><span class="dot"></span>Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="cc-col-actions">
                                <div class="cc-row-actions">
                                    <button type="button" class="cc-act-btn is-edit" title="Edit" aria-label="Edit" disabled>
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button type="button" class="cc-act-btn is-delete" title="Delete" aria-label="Delete" disabled>
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="cc-list-foot">
            <span id="ccCardShowing">Showing 1 to <?= (int) $totalCards ?> of <?= (int) $totalCards ?> cards</span>
        </div>
    </div>
</div>

<!-- Add New Credit Card modal (UI only) -->
<div class="modal fade" id="ccAddCardModal" tabindex="-1" role="dialog" aria-labelledby="ccAddCardModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered cc-add-dialog" role="document">
        <div class="modal-content cc-add-content">
            <div class="cc-add-header">
                <div class="cc-add-header-main">
                    <span class="cc-add-header-icon"><i class="fas fa-credit-card"></i></span>
                    <div>
                        <h5 class="cc-add-title" id="ccAddCardModalTitle">Add New Credit Card</h5>
                        <p class="cc-add-sub">Add a new credit card to manage bills, payments and rewards.</p>
                    </div>
                </div>
                <div class="cc-add-cards-deco" aria-hidden="true">
                    <span class="cc-deco-card is-dark"></span>
                    <span class="cc-deco-card is-red"></span>
                </div>
                <button type="button" class="cc-add-close" data-dismiss="modal" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="ccAddCardForm" autocomplete="off" onsubmit="return false;">
                <div class="cc-add-body">
                    <div class="cc-add-section">
                        <div class="cc-add-section-head">
                            <i class="fas fa-credit-card"></i>
                            <span>Card Details</span>
                        </div>
                        <div class="cc-add-grid">
                            <div class="cc-add-field">
                                <label for="ccCardName">Card Name <span class="req">*</span></label>
                                <div class="cc-input-wrap">
                                    <i class="fas fa-credit-card"></i>
                                    <input type="text" class="form-control" id="ccCardName" name="card_name" placeholder="e.g. HDFC Regalia">
                                </div>
                            </div>
                            <div class="cc-add-field">
                                <label for="ccCardBank">Bank <span class="req">*</span></label>
                                <div class="cc-input-wrap is-select">
                                    <i class="fas fa-university"></i>
                                    <select class="form-control" id="ccCardBank" name="bank">
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
                                    <i class="fas fa-chevron-down cc-select-caret"></i>
                                </div>
                            </div>
                            <div class="cc-add-field">
                                <label for="ccCardLast4">Card Number (Last 4 Digits) <span class="req">*</span></label>
                                <div class="cc-input-wrap">
                                    <i class="fas fa-hashtag"></i>
                                    <input type="text" class="form-control" id="ccCardLast4" name="last4" maxlength="4" inputmode="numeric" placeholder="e.g. 1234">
                                </div>
                            </div>
                            <div class="cc-add-field">
                                <label>Card Type <span class="req">*</span></label>
                                <div class="cc-type-pills" role="radiogroup" aria-label="Card Type">
                                    <label class="cc-type-pill is-active">
                                        <input type="radio" name="card_type" value="credit" checked>
                                        <span class="radio" aria-hidden="true"></span>
                                        <span class="cc-type-text">Credit Card</span>
                                    </label>
                                    <label class="cc-type-pill">
                                        <input type="radio" name="card_type" value="addon">
                                        <span class="radio" aria-hidden="true"></span>
                                        <span class="cc-type-text">Add-on Card</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="cc-add-section">
                        <div class="cc-add-section-head">
                            <i class="fas fa-layer-group"></i>
                            <span>Limit &amp; Status</span>
                        </div>
                        <div class="cc-add-grid">
                            <div class="cc-add-field">
                                <label for="ccCreditLimit">Credit Limit <span class="req">*</span></label>
                                <div class="cc-input-wrap">
                                    <span class="cc-rupee">₹</span>
                                    <input type="text" class="form-control" id="ccCreditLimit" name="credit_limit" inputmode="numeric" placeholder="e.g. 2,00,000">
                                </div>
                            </div>
                            <div class="cc-add-field">
                                <label for="ccAvailableLimit">Available Limit <span class="req">*</span></label>
                                <div class="cc-input-wrap">
                                    <span class="cc-rupee">₹</span>
                                    <input type="text" class="form-control" id="ccAvailableLimit" name="available_limit" inputmode="numeric" placeholder="e.g. 1,31,550">
                                </div>
                            </div>
                            <div class="cc-add-field cc-add-field-full">
                                <label for="ccCardStatus">Status <span class="req">*</span></label>
                                <div class="cc-input-wrap is-select has-status">
                                    <span class="cc-status-dot is-active" id="ccStatusDot"></span>
                                    <select class="form-control" id="ccCardStatus" name="status">
                                        <option value="active" selected>Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                    <i class="fas fa-chevron-down cc-select-caret"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cc-add-footer">
                    <button type="button" class="btn cc-add-cancel" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn cc-add-save" id="ccSaveCardBtn">
                        <i class="fas fa-save"></i> Save Card
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
.cc-list-page {
    --cc-red: #e11d2e;
    --cc-border: #e7ebf1;
    --cc-text: #0f172a;
    --cc-muted: #64748b;
    padding: .35rem 0 1.5rem;
}
.cc-list-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem;
}
.cc-list-head-left { display: flex; align-items: flex-start; gap: .75rem; min-width: 0; }
.cc-list-back {
    width: 36px; height: 36px; border-radius: 8px; border: 1px solid #e2e8f0;
    background: #fff; color: #475569; display: inline-flex; align-items: center;
    justify-content: center; flex: 0 0 36px; margin-top: .15rem; text-decoration: none;
    box-shadow: 0 1px 2px rgba(15,23,42,.04);
}
.cc-list-back:hover { background: #f8fafc; color: #0f172a; }
.cc-list-title {
    margin: 0; font-size: 1.45rem; font-weight: 750; color: var(--cc-text); line-height: 1.2;
}
.cc-list-crumb {
    margin-top: .3rem; font-size: .8rem; color: var(--cc-muted);
    display: flex; flex-wrap: wrap; align-items: center; gap: .3rem;
}
.cc-list-crumb a { color: var(--cc-muted); text-decoration: none; }
.cc-list-crumb a:hover { color: #2563eb; }
.cc-list-crumb .sep { color: #94a3b8; }
.cc-list-crumb .current { color: #334155; font-weight: 600; }
.cc-list-add {
    background: var(--cc-red); border: 1px solid var(--cc-red); color: #fff;
    border-radius: 8px; font-weight: 650; font-size: .88rem; padding: .5rem 1rem;
    box-shadow: 0 6px 16px rgba(225,29,46,.22);
}
.cc-list-add:hover { background: #c81a28; border-color: #c81a28; color: #fff; }

.cc-list-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    gap: .75rem; flex-wrap: wrap; margin-bottom: .85rem;
}
.cc-list-search {
    position: relative; flex: 1 1 280px; max-width: 420px;
}
.cc-list-search i {
    position: absolute; left: .85rem; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: .85rem; pointer-events: none;
}
.cc-list-search input {
    width: 100%; height: 40px; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 0 1rem 0 2.35rem; background: #fff; color: var(--cc-text);
    font-size: .9rem; box-shadow: 0 1px 2px rgba(15,23,42,.03);
}
.cc-list-search input:focus {
    outline: 0; border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(59,130,246,.12);
}
.cc-list-tools { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.cc-tool-btn {
    height: 40px; border: 1px solid #e2e8f0; background: #fff; color: #334155;
    border-radius: 10px; font-size: .84rem; font-weight: 600; padding: 0 .9rem;
    display: inline-flex; align-items: center; gap: .4rem;
    box-shadow: 0 1px 2px rgba(15,23,42,.03);
}
.cc-tool-btn:hover, .cc-tool-btn:focus { background: #f8fafc; color: #0f172a; }
.cc-tool-btn:disabled { opacity: .7; cursor: not-allowed; }
.cc-tool-btn.dropdown-toggle::after { margin-left: .35rem; }

.cc-list-panel {
    background: #fff; border: 1px solid var(--cc-border); border-radius: 14px;
    box-shadow: 0 1px 2px rgba(15,23,42,.04); overflow: hidden;
}
.cc-list-table-wrap { overflow: auto; }
.cc-list-table {
    width: 100%; border-collapse: collapse; min-width: 920px; margin: 0;
}
.cc-list-table th {
    background: #f8fafc; color: #0f172a; font-size: .78rem; font-weight: 750;
    text-align: left; padding: .85rem 1rem; border-bottom: 1px solid var(--cc-border);
    white-space: nowrap;
}
.cc-list-table td {
    padding: .95rem 1rem; border-bottom: 1px solid #f1f5f9;
    color: #1e293b; font-size: .9rem; vertical-align: middle;
}
.cc-list-table tbody tr:last-child td { border-bottom: 0; }
.cc-list-table tbody tr:hover { background: #fafbfc; }
.cc-list-table tbody tr.is-hidden { display: none; }

.cc-card-cell, .cc-bank-cell {
    display: flex; align-items: center; gap: .7rem; min-width: 0;
}
.cc-card-label { font-weight: 650; color: var(--cc-text); }
.cc-mini-card {
    width: 42px; height: 28px; border-radius: 6px; flex: 0 0 42px;
    position: relative; overflow: hidden;
    box-shadow: 0 2px 6px rgba(15,23,42,.18);
}
.cc-mini-card .chip {
    position: absolute; left: 5px; top: 6px; width: 10px; height: 7px;
    border-radius: 2px; background: rgba(255,255,255,.45);
}
.cc-mini-card .chip-sm {
    left: auto; right: 5px; top: auto; bottom: 5px;
    width: 14px; height: 3px; background: rgba(255,255,255,.55);
}
.cc-mini-card.tone-regalia { background: linear-gradient(135deg, #1f2937, #111827); }
.cc-mini-card.tone-sbi { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
.cc-mini-card.tone-amazon { background: linear-gradient(135deg, #f97316, #ea580c); }
.cc-mini-card.tone-axis { background: linear-gradient(135deg, #7c3aed, #5b21b6); }
.cc-mini-card.tone-indus { background: linear-gradient(135deg, #b45309, #92400e); }
.cc-mini-card.tone-amex { background: linear-gradient(135deg, #0ea5e9, #0369a1); }
.cc-mini-card.tone-yes { background: linear-gradient(135deg, #0d9488, #0f766e); }
.cc-mini-card.tone-kotak { background: linear-gradient(135deg, #dc2626, #991b1b); }
.cc-mini-card.tone-idfc { background: linear-gradient(135deg, #9a3412, #7c2d12); }
.cc-mini-card.tone-sc { background: linear-gradient(135deg, #065f46, #064e3b); }

.cc-bank-logo {
    width: 28px; height: 28px; border-radius: 8px; flex: 0 0 28px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .62rem; font-weight: 800; color: #fff; letter-spacing: -.02em;
}
.cc-bank-logo.tone-hdfc { background: #1d4ed8; }
.cc-bank-logo.tone-sbi { background: #2563eb; }
.cc-bank-logo.tone-icici { background: #f97316; }
.cc-bank-logo.tone-axis { background: #7c3aed; }
.cc-bank-logo.tone-indus { background: #b45309; }
.cc-bank-logo.tone-amex { background: #0284c7; }
.cc-bank-logo.tone-yes { background: #0d9488; }
.cc-bank-logo.tone-kotak { background: #dc2626; }
.cc-bank-logo.tone-idfc { background: #9a3412; }
.cc-bank-logo.tone-sc { background: #047857; }

.cc-digits { font-variant-numeric: tabular-nums; letter-spacing: .04em; font-weight: 600; color: #334155; }
.cc-money { font-variant-numeric: tabular-nums; font-weight: 650; color: #0f172a; white-space: nowrap; }

.cc-status-pill {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .28rem .65rem; border-radius: 999px;
    font-size: .75rem; font-weight: 700;
}
.cc-status-pill .dot {
    width: 7px; height: 7px; border-radius: 50%; background: currentColor;
}
.cc-status-pill.is-active { background: #dcfce7; color: #15803d; }
.cc-status-pill.is-inactive { background: #f1f5f9; color: #64748b; }

.cc-col-actions { width: 96px; text-align: right; }
.cc-row-actions { display: inline-flex; align-items: center; gap: .35rem; }
.cc-act-btn {
    width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0;
    background: #fff; color: #64748b; display: inline-flex; align-items: center;
    justify-content: center; font-size: .78rem; cursor: not-allowed;
}
.cc-act-btn.is-edit { color: #475569; }
.cc-act-btn.is-delete { color: #e11d48; border-color: #fecaca; background: #fff; }
.cc-act-btn:disabled { opacity: .85; }

.cc-list-foot {
    padding: .85rem 1rem; border-top: 1px solid #f1f5f9;
    color: var(--cc-muted); font-size: .82rem; font-weight: 500;
}

@media (max-width: 767.98px) {
    .cc-list-title { font-size: 1.2rem; }
    .cc-list-search { max-width: none; }
    .cc-list-toolbar { align-items: stretch; }
    .cc-list-tools { width: 100%; }
    .cc-tool-btn { flex: 1 1 auto; justify-content: center; }
}

/* Add New Credit Card modal */
#ccAddCardModal .cc-add-dialog {
    max-width: 640px; width: calc(100% - 1.5rem); margin: 1rem auto;
}
#ccAddCardModal .cc-add-content {
    border: 0; border-radius: 22px; overflow: hidden;
    box-shadow: 0 24px 60px rgba(15,23,42,.28);
}
#ccAddCardModal .cc-add-header {
    position: relative; display: flex; align-items: center; gap: 1rem;
    padding: 1.25rem 3.2rem 1.25rem 1.25rem;
    background:
        radial-gradient(ellipse 80% 120% at 100% 0%, rgba(255,255,255,.18), transparent 55%),
        linear-gradient(135deg, #e11d2e 0%, #c81a28 48%, #9f1239 100%);
    color: #fff; overflow: hidden;
}
#ccAddCardModal .cc-add-header::before {
    content: ""; position: absolute; inset: auto -10% -55% -10%; height: 120%;
    background: radial-gradient(circle at 20% 40%, rgba(255,255,255,.12), transparent 42%),
                radial-gradient(circle at 70% 60%, rgba(255,255,255,.1), transparent 45%);
    pointer-events: none;
}
#ccAddCardModal .cc-add-header-main {
    position: relative; z-index: 1; display: flex; align-items: flex-start; gap: .85rem; min-width: 0; flex: 1 1 auto;
}
#ccAddCardModal .cc-add-header-icon {
    width: 46px; height: 46px; border-radius: 12px; flex: 0 0 46px;
    display: inline-flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.28);
    font-size: 1.15rem; color: #fff;
}
#ccAddCardModal .cc-add-title {
    margin: 0; font-size: 1.2rem; font-weight: 750; line-height: 1.25; color: #fff;
}
#ccAddCardModal .cc-add-sub {
    margin: .28rem 0 0; font-size: .82rem; color: rgba(255,255,255,.88); line-height: 1.4;
}
#ccAddCardModal .cc-add-cards-deco {
    position: relative; z-index: 1; width: 92px; height: 58px; flex: 0 0 92px;
    display: none;
}
@media (min-width: 576px) {
    #ccAddCardModal .cc-add-cards-deco { display: block; }
}
#ccAddCardModal .cc-deco-card {
    position: absolute; width: 64px; height: 40px; border-radius: 8px;
    box-shadow: 0 8px 18px rgba(0,0,0,.22);
}
#ccAddCardModal .cc-deco-card.is-dark {
    left: 0; top: 10px; background: linear-gradient(135deg, #1f2937, #0f172a);
    transform: rotate(-10deg);
}
#ccAddCardModal .cc-deco-card.is-red {
    right: 0; top: 0; background: linear-gradient(135deg, #fb7185, #e11d2e);
    transform: rotate(12deg);
}
#ccAddCardModal .cc-add-close {
    position: absolute; z-index: 2; top: .85rem; right: .85rem;
    width: 32px; height: 32px; border-radius: 50%; border: 0;
    background: rgba(255,255,255,.18); color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .85rem; cursor: pointer;
}
#ccAddCardModal .cc-add-close:hover { background: rgba(255,255,255,.28); }

#ccAddCardModal .cc-add-body { padding: 1.1rem 1.25rem .35rem; background: #fff; }
#ccAddCardModal .cc-add-section { margin-bottom: 1rem; }
#ccAddCardModal .cc-add-section-head {
    display: flex; align-items: center; gap: .5rem;
    background: #fff1f2; color: #e11d2e; border-radius: 10px;
    padding: .55rem .75rem; font-size: .88rem; font-weight: 750; margin-bottom: .85rem;
}
#ccAddCardModal .cc-add-section-head i { font-size: .9rem; }
#ccAddCardModal .cc-add-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: .85rem .9rem;
}
#ccAddCardModal .cc-add-field-full { grid-column: 1 / -1; }
#ccAddCardModal .cc-add-field > label {
    display: block; margin: 0 0 .35rem; color: #334155;
    font-size: .8rem; font-weight: 650;
}
#ccAddCardModal .cc-add-field .req { color: #e11d2e; }
#ccAddCardModal .cc-input-wrap {
    position: relative; display: flex; align-items: center;
}
#ccAddCardModal .cc-input-wrap > i:first-child,
#ccAddCardModal .cc-input-wrap .cc-rupee {
    position: absolute; left: .85rem; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: .85rem; z-index: 1; pointer-events: none;
}
#ccAddCardModal .cc-input-wrap .cc-rupee {
    font-weight: 700; font-style: normal; font-size: .95rem;
}
#ccAddCardModal .cc-input-wrap .form-control {
    height: 42px; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 0 2.1rem 0 2.35rem; font-size: .9rem; color: #0f172a;
    background: #fff; box-shadow: none;
}
#ccAddCardModal .cc-input-wrap .form-control:focus {
    border-color: #fda4af; box-shadow: 0 0 0 3px rgba(225,29,46,.12);
}
#ccAddCardModal .cc-input-wrap.is-select .form-control {
    appearance: none; -webkit-appearance: none; cursor: pointer;
}
#ccAddCardModal .cc-select-caret {
    position: absolute; right: .85rem; top: 50%; transform: translateY(-50%);
    color: #94a3b8; font-size: .7rem; pointer-events: none;
}
#ccAddCardModal .cc-input-wrap.has-status .form-control { padding-left: 2.1rem; }
#ccAddCardModal .cc-status-dot {
    position: absolute; left: .95rem; top: 50%; transform: translateY(-50%);
    width: 9px; height: 9px; border-radius: 50%; z-index: 1;
}
#ccAddCardModal .cc-status-dot.is-active { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.18); }
#ccAddCardModal .cc-status-dot.is-inactive { background: #94a3b8; box-shadow: 0 0 0 3px rgba(148,163,184,.18); }

#ccAddCardModal .cc-type-pills {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .55rem; align-items: stretch; width: 100%;
}
#ccAddCardModal .cc-add-field .cc-type-pill {
    position: relative; box-sizing: border-box;
    display: flex !important; align-items: center; justify-content: flex-start;
    gap: .6rem; width: 100%; min-width: 0; height: 42px; min-height: 42px;
    margin: 0 !important; padding: 0 .9rem;
    border: 1px solid #e2e8f0; border-radius: 10px; background: #fff;
    color: #334155; font-size: .84rem; font-weight: 650; line-height: 1;
    cursor: pointer; user-select: none;
}
#ccAddCardModal .cc-add-field .cc-type-pill input {
    position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%;
    margin: 0; cursor: pointer; z-index: 2;
}
#ccAddCardModal .cc-add-field .cc-type-pill .radio {
    box-sizing: border-box; position: relative; z-index: 1;
    width: 16px; height: 16px; flex: 0 0 16px; margin: 0; padding: 0;
    border: 2px solid #cbd5e1; border-radius: 50%;
    display: grid; place-items: center; line-height: 0;
}
#ccAddCardModal .cc-add-field .cc-type-pill .radio::after {
    content: ""; display: block; width: 8px; height: 8px; border-radius: 50%;
    background: transparent; margin: 0;
}
#ccAddCardModal .cc-add-field .cc-type-pill .cc-type-text {
    position: relative; z-index: 1; display: block; margin: 0; padding: 0;
    font-size: .84rem; font-weight: 650; line-height: 1; white-space: nowrap;
}
#ccAddCardModal .cc-add-field .cc-type-pill.is-active {
    background: #fff1f2; border-color: #fda4af; color: #be123c;
}
#ccAddCardModal .cc-add-field .cc-type-pill.is-active .radio { border-color: #e11d2e; }
#ccAddCardModal .cc-add-field .cc-type-pill.is-active .radio::after { background: #e11d2e; }

#ccAddCardModal .cc-add-footer {
    display: flex; align-items: center; justify-content: space-between; gap: .75rem;
    padding: .95rem 1.25rem 1.15rem; border-top: 1px solid #eef2f7; background: #fff;
}
#ccAddCardModal .cc-add-cancel {
    min-width: 110px; height: 42px; border-radius: 10px; border: 1px solid #e2e8f0;
    background: #fff; color: #334155; font-weight: 650; font-size: .88rem;
    display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
}
#ccAddCardModal .cc-add-cancel:hover { background: #f8fafc; }
#ccAddCardModal .cc-add-save {
    min-width: 130px; height: 42px; border-radius: 10px; border: 1px solid #e11d2e;
    background: #e11d2e; color: #fff; font-weight: 700; font-size: .88rem;
    display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
    box-shadow: 0 8px 18px rgba(225,29,46,.25);
}
#ccAddCardModal .cc-add-save:hover { background: #c81a28; border-color: #c81a28; color: #fff; }

@media (max-width: 575.98px) {
    #ccAddCardModal .cc-add-grid { grid-template-columns: 1fr; }
    #ccAddCardModal .cc-type-pills { grid-template-columns: 1fr; }
    #ccAddCardModal .cc-add-footer { flex-direction: column-reverse; }
    #ccAddCardModal .cc-add-cancel,
    #ccAddCardModal .cc-add-save { width: 100%; }
}
</style>
CSS;

$ccExtraFooter = <<<'JS'
<script>
(function ($) {
  var $rows = $('#ccCardTable tbody tr');
  var total = $rows.length;
  function updateShowing(visible) {
    if (!visible) {
      $('#ccCardShowing').text('Showing 0 of ' + total + ' cards');
      return;
    }
    $('#ccCardShowing').text('Showing 1 to ' + visible + ' of ' + total + ' cards');
  }
  $('#ccCardSearch').on('input', function () {
    var q = String($(this).val() || '').trim().toLowerCase();
    var visible = 0;
    $rows.each(function () {
      var hay = String($(this).attr('data-search') || '');
      var show = !q || hay.indexOf(q) !== -1;
      $(this).toggleClass('is-hidden', !show);
      if (show) visible++;
    });
    updateShowing(visible);
  });

  function resetAddCardForm() {
    var form = document.getElementById('ccAddCardForm');
    if (form) form.reset();
    $('#ccAddCardForm .cc-type-pill').removeClass('is-active');
    $('#ccAddCardForm input[name="card_type"][value="credit"]').prop('checked', true)
      .closest('.cc-type-pill').addClass('is-active');
    $('#ccCardStatus').val('active');
    $('#ccStatusDot').removeClass('is-inactive').addClass('is-active');
  }

  $('#ccAddCardModal').on('show.bs.modal', resetAddCardForm);

  $(document).on('change', '#ccAddCardForm input[name="card_type"]', function () {
    $('#ccAddCardForm .cc-type-pill').removeClass('is-active');
    $(this).closest('.cc-type-pill').addClass('is-active');
  });

  $('#ccCardStatus').on('change', function () {
    var active = String($(this).val()) === 'active';
    $('#ccStatusDot').toggleClass('is-active', active).toggleClass('is-inactive', !active);
  });

  $('#ccCardLast4').on('input', function () {
    this.value = String(this.value || '').replace(/\D+/g, '').slice(0, 4);
  });

  $('#ccCreditLimit, #ccAvailableLimit').on('input', function () {
    var raw = String(this.value || '').replace(/[^\d]/g, '');
    if (!raw) { this.value = ''; return; }
    this.value = Number(raw).toLocaleString('en-IN');
  });

  $('#ccAddCardForm').on('submit', function (e) {
    e.preventDefault();
    // UI-only stage: close modal without backend save.
    $('#ccAddCardModal').modal('hide');
  });
})(jQuery);
</script>
JS;

$ccPageTitle = 'Card List';
$ccBodyClass = 'cc-cards-list-page';
include __DIR__ . '/_layout.php';
