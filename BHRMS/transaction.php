<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'payments';

switch ($tab) {
    case 'payments':
        $transactions = $conn->query("
            SELECT 
                t.full_name AS tenant_name,
                r.room_number,
                p.amount,
                p.payment_date AS date,
                COALESCE(p.remarks, 'Pending') AS status
            FROM payments p
            LEFT JOIN tenants t ON p.tenant_id = t.tenant_id
            LEFT JOIN rooms r ON t.room_id = r.room_id
            ORDER BY p.payment_date DESC
        ");
        break;

    case 'receipts':
        $transactions = $conn->query("
            SELECT 
                t.full_name AS tenant_name,
                r.room_number,
                pr.amount,
                pr.receipt_date AS date,
                'Paid' AS status
            FROM payment_receipts pr
            LEFT JOIN tenants t ON pr.tenant_id = t.tenant_id
            LEFT JOIN rooms r ON t.room_id = r.room_id
            ORDER BY pr.receipt_date DESC
        ");
        break;

    case 'moveinout':
        $transactions = $conn->query("
            SELECT 
                t.full_name AS tenant_name,
                r.room_number,
                m.type AS amount,
                m.date,
                CONCAT('Move ', m.type) AS status
            FROM move_transactions m
            LEFT JOIN tenants t ON m.tenant_id = t.tenant_id
            LEFT JOIN rooms r ON t.room_id = r.room_id
            ORDER BY m.date DESC
        ");
        break;

    case 'partial':
        $transactions = $conn->query("
            SELECT 
                t.full_name AS tenant_name,
                r.room_number,
                pp.amount_paid AS amount,
                pp.payment_date AS date,
                'Partial' AS status
            FROM partial_payments pp
            LEFT JOIN tenants t ON pp.tenant_id = t.tenant_id
            LEFT JOIN rooms r ON t.room_id = r.room_id
            ORDER BY pp.payment_date DESC
        ");
        break;

    case 'expenses':
        $transactions = $conn->query("
            SELECT 
                e.description AS tenant_name,
                e.category AS room_number,
                e.amount,
                e.expense_date AS date,
                'Expense' AS status
            FROM expenses e
            ORDER BY e.expense_date DESC
        ");
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction Records</title>
    <link rel="stylesheet" href="dash.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- TAB CSS -->
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
            color: #555;
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
        <li><a href="expenses.php"><i class="fas fa-receipt"></i> Expenses</a></li>
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

<!-- TABS -->
<div class="record-tabs">
    <a href="?tab=payments" class="<?= $tab=='payments'?'active':'' ?>">Payments</a>
    <a href="?tab=receipts" class="<?= $tab=='receipts'?'active':'' ?>">Receipts</a>
    <a href="?tab=moveinout" class="<?= $tab=='moveinout'?'active':'' ?>">Move In/Out</a>
    <a href="?tab=partial" class="<?= $tab=='partial'?'active':'' ?>">Partial</a>
    <a href="?tab=expenses" class="<?= $tab=='expenses'?'active':'' ?>">Expenses</a>
</div>

<section class="table-section">
<table>
    <thead>
        <tr>
            <th><?= $tab == 'expenses' ? 'Description' : 'Tenant' ?></th>
            <th><?= $tab == 'expenses' ? 'Category' : 'Room' ?></th>
            <th>Amount</th>
            <th>Date</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($transactions && $transactions->num_rows > 0): ?>
            <?php while ($row = $transactions->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['tenant_name']) ?></td>
                <td><?= htmlspecialchars($row['room_number']) ?></td>
                <td>
                    <?= is_numeric($row['amount'])
                        ? '₱' . number_format($row['amount'], 2)
                        : htmlspecialchars($row['amount']) ?>
                </td>
                <td><?= date("M d, Y", strtotime($row['date'])) ?></td>
                <td class="<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                    <?= ucfirst($row['status']) ?>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" style="text-align:center;">No records found</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
</section>

</main>
</div>

</body>
</html>
