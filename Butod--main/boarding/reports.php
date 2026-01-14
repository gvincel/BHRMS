<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Calculate report statistics
$stats = [];

// Total Income (sum of all payments)
$income_query = $conn->query("SELECT COALESCE(SUM(amount), 0) as total_income FROM payments");
$stats['total_income'] = $income_query->fetch_assoc()['total_income'];

// Paid Payments (based on remarks)
$paid_query = $conn->query("SELECT COUNT(*) as count FROM payments WHERE remarks LIKE '%paid%' OR remarks LIKE '%completed%'");
$stats['paid_payments'] = $paid_query->fetch_assoc()['count'];

// Pending Payments (based on remarks)
$pending_payments_query = $conn->query("SELECT COUNT(*) as count FROM payments WHERE remarks LIKE '%pending%' OR remarks IS NULL");
$stats['pending_payments'] = $pending_payments_query->fetch_assoc()['count'];

// Total Maintenance Requests
$maintenance_query = $conn->query("SELECT COUNT(*) as count FROM maintenance_requests");
$stats['total_maintenance'] = $maintenance_query->fetch_assoc()['count'];

// Monthly Income Report (by payment month)
$monthly_income_query = $conn->query("
    SELECT 
        MONTHNAME(payment_date) as month_name,
        MONTH(payment_date) as month_num,
        YEAR(payment_date) as year,
        COUNT(*) as total_payments,
        COALESCE(SUM(amount), 0) as total_income,
        CASE 
            WHEN COUNT(*) > 0 THEN 'Completed'
            ELSE 'Pending'
        END as status
    FROM payments
    WHERE payment_date IS NOT NULL
    GROUP BY YEAR(payment_date), MONTH(payment_date), MONTHNAME(payment_date)
    ORDER BY year DESC, month_num DESC
    LIMIT 3
");

// Maintenance Summary by Status
$maintenance_summary_query = $conn->query("
    SELECT 
        status,
        COUNT(*) as total_requests
    FROM maintenance_requests
    GROUP BY status
    ORDER BY 
        CASE status 
            WHEN 'Pending' THEN 1
            WHEN 'In Progress' THEN 2
            WHEN 'Resolved' THEN 3
        END
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Boarding House Management System</title>
    <link rel="stylesheet" href="dash.css">
    <style>
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            background-color: #f8f9fa;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .card h3 {
            margin-top: 0;
            font-size: 1rem;
            color: #4b5563;
        }
        .card p {
            font-size: 1.8rem;
            font-weight: bold;
            margin: 10px 0 0 0;
            color: #1f2937;
        }
        .paid {
            color: #065f46;
            font-weight: bold;
        }
        .pending {
            color: #b91c1c;
            font-weight: bold;
        }
        .progress {
            color: #d97706;
            font-weight: bold;
        }
        .table-section {
            margin-bottom: 30px;
        }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <h2 class="logo">Boarding House</h2>
        <ul class="menu">
            <li><a href="dash.php">Dashboard</a></li>
            <li><a href="room.php">Rooms</a></li>
            <li><a href="tenant.php">Tenants</a></li>
            <li><a href="payment.php">Payments</a></li>
            <li><a href="mainten.php">Maintenance</a></li>
            <li class="active"><a href="reports.php">Reports</a></li>
            <li><a href="expenses.php">Expenses</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li class="logout"><a href="logout.php" style="color: inherit; text-decoration: none;">Logout</a></li>
        </ul>
    </aside>

    <main class="main">
        <header class="topbar">
            <h1>Reports</h1>
            <div class="profile">
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
                (<?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?>)
            </div>
        </header>

        <section class="cards">
            <div class="card">
                <h3>Total Income</h3>
                <p>₱<?php echo number_format($stats['total_income'], 2); ?></p>
            </div>
            <div class="card">
                <h3>Paid Payments</h3>
                <p><?php echo $stats['paid_payments']; ?></p>
            </div>
            <div class="card">
                <h3>Pending Payments</h3>
                <p><?php echo $stats['pending_payments']; ?></p>
            </div>
            <div class="card">
                <h3>Maintenance Requests</h3>
                <p><?php echo $stats['total_maintenance']; ?></p>
            </div>
        </section>

        <section class="table-section">
            <h2>Monthly Income Report</h2>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Total Payments</th>
                        <th>Total Income</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($monthly_income_query->num_rows > 0): ?>
                        <?php while ($monthly = $monthly_income_query->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $monthly['month_name'] . ' ' . $monthly['year']; ?></td>
                            <td><?php echo $monthly['total_payments']; ?></td>
                            <td>₱<?php echo number_format($monthly['total_income'], 2); ?></td>
                            <td class="<?php echo strtolower($monthly['status']); ?>">
                                <?php echo $monthly['status']; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No payment data available for monthly reports</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section class="table-section" style="margin-top: 30px;">
            <h2>Maintenance Summary</h2>
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Total Requests</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($maintenance_summary_query->num_rows > 0): ?>
                        <?php while ($summary = $maintenance_summary_query->fetch_assoc()): ?>
                        <tr>
                            <td class="
                                <?php 
                                if ($summary['status'] == 'Pending') echo 'pending';
                                elseif ($summary['status'] == 'In Progress') echo 'progress';
                                elseif ($summary['status'] == 'Resolved') echo 'paid';
                                else echo 'pending';
                                ?>
                            ">
                                <?php 
                                if ($summary['status'] == 'Resolved') echo 'Completed';
                                else echo $summary['status']; 
                                ?>
                            </td>
                            <td><?php echo $summary['total_requests']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" style="text-align: center;">No maintenance data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

</body>
</html>