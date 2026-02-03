<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'payments';

// Get search parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

switch ($tab) {
    case 'payments':
        $query = "
            SELECT 
                p.payment_id AS transaction_id,
                t.full_name AS tenant_name,
                r.room_number,
                p.amount,
                p.payment_date AS date,
                COALESCE(p.remarks, 'Pending') AS status,
                p.payment_method,
                'Payment' AS transaction_type
            FROM payments p
            LEFT JOIN tenants t ON p.tenant_id = t.tenant_id
            LEFT JOIN rooms r ON t.room_id = r.room_id
            WHERE 1=1
        ";
        
        if (!empty($search)) {
            $query .= " AND (t.full_name LIKE '%$search%' OR r.room_number LIKE '%$search%' OR p.payment_id = '$search')";
        }
        if (!empty($date_from)) {
            $query .= " AND p.payment_date >= '$date_from'";
        }
        if (!empty($date_to)) {
            $query .= " AND p.payment_date <= '$date_to'";
        }
        
        $query .= " ORDER BY p.payment_date DESC";
        $transactions = $conn->query($query);
        break;

    case 'receipts':
        $query = "
            SELECT 
                pr.receipt_id AS transaction_id,
                t.full_name AS tenant_name,
                r.room_number,
                pr.amount,
                pr.receipt_date AS date,
                'Paid' AS status,
                pr.receipt_number,
                'Receipt' AS transaction_type
            FROM payment_receipts pr
            LEFT JOIN tenants t ON pr.tenant_id = t.tenant_id
            LEFT JOIN rooms r ON t.room_id = r.room_id
            WHERE 1=1
        ";
        
        if (!empty($search)) {
            $query .= " AND (t.full_name LIKE '%$search%' OR r.room_number LIKE '%$search%' OR pr.receipt_id = '$search')";
        }
        if (!empty($date_from)) {
            $query .= " AND pr.receipt_date >= '$date_from'";
        }
        if (!empty($date_to)) {
            $query .= " AND pr.receipt_date <= '$date_to'";
        }
        
        $query .= " ORDER BY pr.receipt_date DESC";
        $transactions = $conn->query($query);
        break;

    case 'moveinout':
        $query = "
            SELECT 
                m.move_id AS transaction_id,
                t.full_name AS tenant_name,
                r.room_number,
                NULL AS amount,
                m.date,
                CONCAT('Move ', m.type) AS status,
                m.notes,
                'Move' AS transaction_type
            FROM move_transactions m
            LEFT JOIN tenants t ON m.tenant_id = t.tenant_id
            LEFT JOIN rooms r ON t.room_id = r.room_id
            WHERE 1=1
        ";
        
        if (!empty($search)) {
            $query .= " AND (t.full_name LIKE '%$search%' OR r.room_number LIKE '%$search%' OR m.move_id = '$search')";
        }
        if (!empty($date_from)) {
            $query .= " AND m.date >= '$date_from'";
        }
        if (!empty($date_to)) {
            $query .= " AND m.date <= '$date_to'";
        }
        
        $query .= " ORDER BY m.date DESC";
        $transactions = $conn->query($query);
        break;

    case 'partial':
        $query = "
            SELECT 
                pp.partial_id AS transaction_id,
                t.full_name AS tenant_name,
                r.room_number,
                pp.amount_paid AS amount,
                pp.payment_date AS date,
                'Partial' AS status,
                pp.balance_due,
                'Partial Payment' AS transaction_type
            FROM partial_payments pp
            LEFT JOIN tenants t ON pp.tenant_id = t.tenant_id
            LEFT JOIN rooms r ON t.room_id = r.room_id
            WHERE 1=1
        ";
        
        if (!empty($search)) {
            $query .= " AND (t.full_name LIKE '%$search%' OR r.room_number LIKE '%$search%' OR pp.partial_id = '$search')";
        }
        if (!empty($date_from)) {
            $query .= " AND pp.payment_date >= '$date_from'";
        }
        if (!empty($date_to)) {
            $query .= " AND pp.payment_date <= '$date_to'";
        }
        
        $query .= " ORDER BY pp.payment_date DESC";
        $transactions = $conn->query($query);
        break;

    case 'expenses':
        $query = "
            SELECT 
                e.expense_id AS transaction_id,
                e.description AS tenant_name,
                e.expense_type AS room_number,
                e.amount,
                e.expense_date AS date,
                e.status,
                e.expense_type AS category,
                'Expense' AS transaction_type
            FROM expenses e
            WHERE 1=1
        ";
        
        if (!empty($search)) {
            $query .= " AND (e.description LIKE '%$search%' OR e.expense_type LIKE '%$search%' OR e.expense_id = '$search')";
        }
        if (!empty($date_from)) {
            $query .= " AND e.expense_date >= '$date_from'";
        }
        if (!empty($date_to)) {
            $query .= " AND e.expense_date <= '$date_to'";
        }
        
        $query .= " ORDER BY e.expense_date DESC";
        $transactions = $conn->query($query);
        break;
}

// Get totals for summary
$total_query = $conn->query("
    SELECT 
        (SELECT COALESCE(SUM(amount), 0) FROM payments) as total_payments,
        (SELECT COALESCE(SUM(amount), 0) FROM payment_receipts) as total_receipts,
        (SELECT COALESCE(SUM(amount_paid), 0) FROM partial_payments) as total_partial,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses) as total_expenses
");
$totals = $total_query->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction Records</title>
    <link rel="stylesheet" href="dash.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .record-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 6px;
            justify-content: space-evenly;
        }

        .record-tabs a {
            text-decoration: none;
            padding: 8px 14px;
            font-weight: 500;
            color: white;
            border-radius: 6px 6px 0 0;
            transition: 0.2s ease;
        }

        .record-tabs a:hover {
            background: var(--accent);
            color: #333;
        }

        .record-tabs a.active {
            background: var(--primary);
            color: #fff;
            font-weight: 600;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary);
        }

        .summary-card h3 {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: #666;
        }

        .summary-card .amount {
            font-size: 22px;
            font-weight: bold;
            color: #333;
        }

        .filter-section {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }

        .filter-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        .filter-buttons button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }

        .filter-buttons .apply {
            background: var(--primary);
            color: white;
        }

        .filter-buttons .reset {
            background: #f0f0f0;
            color: #333;
        }

        .status-paid { color: #2ecc71; font-weight: bold; }
        .status-pending { color: #e67e22; font-weight: bold; }
        .status-partial { color: #f59e0b; font-weight: bold; }
        .status-expense { color: #e74c3c; font-weight: bold; }
        .status-move-in { color: #3498db; font-weight: bold; }
        .status-move-out { color: #9b59b6; font-weight: bold; }
        
        .action-btn {
            padding: 4px 8px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            display: inline-block;
            margin-left: 8px;
            border: none;
            cursor: pointer;
        }
        
        .action-btn:hover {
            background: #218838;
        }
        
        .status-container {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 5px;
        }
    </style>
</head>
<body>

<div class="container">

<!-- SIDEBAR -->
<aside class="sidebar">
    <h2 class="logo">Boarding House</h2>
    <ul class="menu">
        <li><a href="dash.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li><a href="room.php"><i class="fas fa-bed"></i> Rooms</a></li>
        <li><a href="tenant.php"><i class="fas fa-users"></i> Tenants</a></li>
        <li><a href="payment.php"><i class="fas fa-hand-holding-dollar"></i> Payments</a></li>
        <li class="active"><a href="transactions.php"><i class="fas fa-list-alt"></i> Transaction Records</a></li>
        <li><a href="mainten.php"><i class="fas fa-tools"></i> Maintenance</a></li>
        <li><a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a></li>
        <li><a href="expenses.php"><i class="fas fa-file-invoice-dollar"></i> Expenses</a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        <li class="logout"><a href="logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</aside>

<!-- MAIN -->
<main class="main">
<header class="topbar">
    <h1>Transaction Records</h1>
    <div class="profile">Zen (Admin)</div>
</header>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card">
        <h3>Total Payments</h3>
        <div class="amount">₱<?= number_format($totals['total_payments'], 2) ?></div>
    </div>
    <div class="summary-card">
        <h3>Total Receipts</h3>
        <div class="amount">₱<?= number_format($totals['total_receipts'], 2) ?></div>
    </div>
    <div class="summary-card">
        <h3>Total Partial Payments</h3>
        <div class="amount">₱<?= number_format($totals['total_partial'], 2) ?></div>
    </div>
    <div class="summary-card">
        <h3>Total Expenses</h3>
        <div class="amount">₱<?= number_format($totals['total_expenses'], 2) ?></div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <form method="GET" class="filter-form">
        <input type="hidden" name="tab" value="<?= $tab ?>">
        <div class="filter-group">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Search by name, room, or ID...">
        </div>
        <div class="filter-group">
            <label for="date_from">Date From</label>
            <input type="date" id="date_from" name="date_from" value="<?= $date_from ?>">
        </div>
        <div class="filter-group">
            <label for="date_to">Date To</label>
            <input type="date" id="date_to" name="date_to" value="<?= $date_to ?>">
        </div>
        <div class="filter-buttons">
            <button type="submit" class="apply">Apply Filters</button>
            <button type="button" class="reset" onclick="window.location.href='transactions.php?tab=<?= $tab ?>'">
                Clear Filters
            </button>
        </div>
    </form>
</div>

<!-- TABS -->
<div class="record-tabs">
    <a href="?tab=payments" class="<?= $tab=='payments'?'active':'' ?>">
        <i class="fas fa-hand-holding-dollar"></i> Payments
    </a>
    <a href="?tab=receipts" class="<?= $tab=='receipts'?'active':'' ?>">
        <i class="fas fa-receipt"></i> Receipts
    </a>
    <a href="?tab=moveinout" class="<?= $tab=='moveinout'?'active':'' ?>">
        <i class="fas fa-exchange-alt"></i> Move In/Out
    </a>
    <a href="?tab=partial" class="<?= $tab=='partial'?'active':'' ?>">
        <i class="fas fa-money-bill-wave"></i> Partial
    </a>
    <a href="?tab=expenses" class="<?= $tab=='expenses'?'active':'' ?>">
        <i class="fas fa-file-invoice-dollar"></i> Expenses
    </a>
</div>

<section class="table-section">
<table>
    <thead>
        <tr>
            <th>#</th>
            <th><?= $tab == 'expenses' ? 'Description' : 'Tenant' ?></th>
            <th><?= $tab == 'expenses' ? 'Category' : 'Room' ?></th>
            <th>Amount</th>
            <th>Date</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($transactions && $transactions->num_rows > 0): ?>
            <?php $counter = 1; ?>
            <?php while ($row = $transactions->fetch_assoc()): ?>
            <tr>
                <td><?= $counter++ ?></td>
                <td>
                    <strong>TRX-<?= str_pad($row['transaction_id'], 6, '0', STR_PAD_LEFT) ?></strong><br>
                    <?= htmlspecialchars($row['tenant_name']) ?>
                </td>
                <td><?= htmlspecialchars($row['room_number']) ?></td>
                <td>
                    <?php if ($row['amount'] !== null): ?>
                        <?= '₱' . number_format($row['amount'], 2) ?>
                        <?php if ($tab == 'partial' && isset($row['balance_due'])): ?>
                            <br><small>Balance: ₱<?= number_format($row['balance_due'], 2) ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td><?= date("M d, Y", strtotime($row['date'])) ?></td>
                <td class="status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                    <div class="status-container">
                        <?= ucfirst($row['status']) ?>
                        <?php if ($tab == 'payments' && $row['status'] == 'Pending'): ?>
                            <a href="mark_paid.php?id=<?= $row['transaction_id'] ?>&type=payment" 
                               class="action-btn" 
                               onclick="return confirm('Mark as paid?')">
                                <i class="fas fa-check"></i> Mark Paid
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($tab == 'expenses' && $row['status'] == 'Pending'): ?>
                            <a href="mark_paid.php?id=<?= $row['transaction_id'] ?>&type=expense" 
                               class="action-btn" 
                               onclick="return confirm('Mark expense as paid?')">
                                <i class="fas fa-check"></i> Pay
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="text-align:center; padding: 40px;">
                    <i class="fas fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i><br>
                    No records found
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
</section>

<?php if ($transactions && $transactions->num_rows > 0): ?>
<div class="table-footer">
    <div class="summary-total">
        Total Amount: 
        <strong>
        <?php
        $total_amount = 0;
        $result = $conn->query($query);
        while ($row = $result->fetch_assoc()) {
            if ($row['amount'] !== null) {
                $total_amount += $row['amount'];
            }
        }
        echo '₱' . number_format($total_amount, 2);
        ?>
        </strong>
    </div>
</div>
<?php endif; ?>

</main>
</div>

<script>
document.getElementById('date_from').addEventListener('change', function() {
    var dateTo = document.getElementById('date_to');
    dateTo.min = this.value;
});

function exportToExcel() {
    window.location.href = 'export_transactions.php?tab=<?= $tab ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>';
}
</script>

</body>
</html>