<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch payment records from database with tenant and room information
$payments_query = $conn->query("
    SELECT 
        p.payment_id,
        t.full_name as tenant_name,
        r.room_number,
        p.amount,
        p.payment_date,
        p.payment_method,
        p.remarks,
        CASE 
            WHEN p.remarks LIKE '%paid%' OR p.remarks LIKE '%completed%' THEN 'Paid'
            WHEN p.remarks LIKE '%pending%' OR p.remarks IS NULL THEN 'Pending'
            ELSE 'Pending'
        END as status
    FROM payments p
    LEFT JOIN tenants t ON p.tenant_id = t.tenant_id
    LEFT JOIN rooms r ON t.room_id = r.room_id
    ORDER BY p.payment_date DESC, p.payment_id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Management</title>
    <link rel="stylesheet" href="dash.css">
    <style>
        .paid {
            color: #10b981;
            font-weight: bold;
        }
        .pending {
            color: #b91c1c;
            font-weight: bold;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

</head>
<body>

<div class="container">
    <aside class="sidebar">
    <h2 class="logo">Boarding House</h2>
    <ul class="menu">
        <li>
            <a href="dash.php">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="room.php">
                <i class="fas fa-bed"></i> Rooms
            </a>
        </li>
        <li>
            <a href="tenant.php">
                <i class="fas fa-users"></i> Tenants
            </a>
        </li>
        <li class="active">
            <a href="payment.php">
                <i class="fas fa-hand-holding-dollar"></i> Payments
            </a>
        </li>
        <li>
            <a href="mainten.php">
                <i class="fas fa-tools"></i> Maintenance
            </a>
        </li>
        <li>
            <a href="reports.php">
                <i class="fas fa-file-alt"></i> Reports
            </a>
        </li>
        <li>
            <a href="expenses.php">
                <i class="fas fa-receipt"></i> Expenses
            </a>
        </li>
        <li>
            <a href="settings.php">
                <i class="fas fa-cog"></i> Settings
            </a>
        </li>
        <li class="logout">
            <a href="logout.php">
                <i class="fas fa-right-from-bracket"></i> Logout
            </a>
        </li>
    </ul>
</aside>

    <main class="main">
        <header class="topbar">
            <h1>Payments</h1>
            <div class="profile">
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
                (<?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?>)
            </div>
        </header>

        <section class="table-section">
            <h2>Payment Records</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tenant Name</th>
                        <th>Room</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payments_query->num_rows > 0): ?>
                        <?php while ($payment = $payments_query->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['tenant_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($payment['room_number'] ?? 'N/A'); ?></td>
                            <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                            <td><?php echo $payment['payment_date'] ? date('Y-m-d', strtotime($payment['payment_date'])) : 'N/A'; ?></td>
                            <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                            <td class="<?php echo strtolower($payment['status']); ?>">
                                <?php echo $payment['status']; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No payment records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

</body>
</html>