<?php
session_start();
include "db.php";


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}



$rooms_query = $conn->query("SELECT COUNT(*) as total_rooms FROM rooms");
$total_rooms = $rooms_query->fetch_assoc()['total_rooms'];


$occupied_query = $conn->query("SELECT COUNT(*) as occupied_rooms FROM rooms WHERE status = 'Occupied'");
$occupied_rooms = $occupied_query->fetch_assoc()['occupied_rooms'];


$available_query = $conn->query("SELECT COUNT(*) as available_rooms FROM rooms WHERE status = 'Available'");
$available_rooms = $available_query->fetch_assoc()['available_rooms'];


$income_query = $conn->query("
    SELECT COALESCE(SUM(r.monthly_rent), 0) as monthly_income 
    FROM rooms r 
    WHERE r.status = 'Occupied'
");
$monthly_income = $income_query->fetch_assoc()['monthly_income'];


$pending_query = $conn->query("SELECT COUNT(*) as pending_payments FROM payments WHERE remarks LIKE '%pending%' OR remarks IS NULL");
$pending_payments = $pending_query->fetch_assoc()['pending_payments'];


$recent_payments_query = $conn->query("
    SELECT 
        p.payment_id,
        t.full_name as tenant_name,
        r.room_number,
        p.amount,
        p.payment_date,
        CASE 
            WHEN p.remarks LIKE '%paid%' THEN 'Paid'
            WHEN p.remarks LIKE '%pending%' OR p.remarks IS NULL THEN 'Pending'
            ELSE 'Unknown'
        END as status
    FROM payments p
    LEFT JOIN tenants t ON p.tenant_id = t.tenant_id
    LEFT JOIN rooms r ON t.room_id = r.room_id
    ORDER BY p.payment_date DESC
    LIMIT 15
");

$recent_payments = [];
while ($row = $recent_payments_query->fetch_assoc()) {
    $recent_payments[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boarding House Management System</title>
    <link rel="stylesheet" href="dash.css">
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <h2 class="logo">Boarding House</h2>
        <ul class="menu">
            <li class="active"><a href="dash.php">Dashboard</a></li>
            <li><a href="room.php">Rooms</a></li>
            <li><a href="tenant.php">Tenants</a></li>
            <li><a href="payment.php">Payments</a></li>
            <li><a href="mainten.php">Maintenance</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="expenses.php">Expenses</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li class="logout"><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <main class="main">
        <header class="topbar">
            <h1>Dashboard</h1>
            <div class="profile">
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
                (<?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?>)
            </div>
        </header>

        <section class="cards">
            <div class="card">
                <h3>Total Rooms</h3>
                <p><?php echo $total_rooms; ?></p>
            </div>
            <div class="card">
                <h3>Occupied Rooms</h3>
                <p><?php echo $occupied_rooms; ?></p>
            </div>
            <div class="card">
                <h3>Monthly Income</h3>
                <p>₱<?php echo number_format($monthly_income, 2); ?></p>
            </div>
            <div class="card">
                <h3>Pending Payments</h3>
                <p><?php echo $pending_payments; ?></p>
            </div>
        </section>

        <section class="table-section">
            <h2>Recent Payments</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Room</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_payments) > 0): ?>
                        <?php foreach ($recent_payments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['tenant_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($payment['room_number'] ?? 'N/A'); ?></td>
                            <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                            <td class="<?php echo strtolower($payment['status']); ?>">
                                <?php echo $payment['status']; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No payment records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

</body>
</html>