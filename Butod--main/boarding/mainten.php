<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch maintenance statistics
$stats = [];
// Pending requests
$pending_query = $conn->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE status = 'Pending'");
$stats['pending'] = $pending_query->fetch_assoc()['count'];

// In Progress requests
$progress_query = $conn->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE status = 'In Progress'");
$stats['progress'] = $progress_query->fetch_assoc()['count'];

// Resolved/Completed requests
$resolved_query = $conn->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE status = 'Resolved'");
$stats['resolved'] = $resolved_query->fetch_assoc()['count'];

// Fetch maintenance requests with tenant and room information
$maintenance_query = $conn->query("
    SELECT 
        mr.request_id,
        t.full_name as tenant_name,
        r.room_number,
        mr.issue_description,
        mr.request_date,
        mr.status
    FROM maintenance_requests mr
    LEFT JOIN tenants t ON mr.tenant_id = t.tenant_id
    LEFT JOIN rooms r ON mr.room_id = r.room_id
    ORDER BY 
        CASE mr.status 
            WHEN 'Pending' THEN 1
            WHEN 'In Progress' THEN 2
            WHEN 'Resolved' THEN 3
        END,
        mr.request_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance | Boarding House Management System</title>
    <link rel="stylesheet" href="dash.css">
    <style>
        .pending {
            color: #b91c1c;
            font-weight: bold;
        }
        .progress {
            color: #d97706;
            font-weight: bold;
        }
        .completed {
            color: #065f46;
            font-weight: bold;
        }
        .pending-card { background-color: #fef3c7; }
        .progress-card { background-color: #dbeafe; }
        .completed-card { background-color: #d1fae5; }
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .card h3 {
            margin-top: 0;
            font-size: 1rem;
            color: #4b5563;
        }
        .card p {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0 0 0;
            color: #1f2937;
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
            <li class="active"><a href="mainten.php">Maintenance</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="expenses.php">Expenses</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li class="logout"><a href="logout.php" style="color: inherit; text-decoration: none;">Logout</a></li>
        </ul>
    </aside>

    <main class="main">
        <header class="topbar">
            <h1>Maintenance</h1>
            <div class="profile">
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
                (<?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?>)
            </div>
        </header>

        <section class="cards">
            <div class="card pending-card">
                <h3>Pending Requests</h3>
                <p><?php echo $stats['pending']; ?></p>
            </div>
            <div class="card progress-card">
                <h3>In Progress</h3>
                <p><?php echo $stats['progress']; ?></p>
            </div>
            <div class="card completed-card">
                <h3>Completed</h3>
                <p><?php echo $stats['resolved']; ?></p>
            </div>
        </section>

        <section class="table-section">
            <h2>Maintenance Requests</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Room</th>
                        <th>Issue</th>
                        <th>Date Reported</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($maintenance_query->num_rows > 0): ?>
                        <?php while ($request = $maintenance_query->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['tenant_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($request['room_number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($request['issue_description']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                            <td class="<?php 
                                if ($request['status'] == 'Pending') echo 'pending';
                                elseif ($request['status'] == 'In Progress') echo 'progress';
                                elseif ($request['status'] == 'Resolved') echo 'completed';
                                else echo 'pending';
                            ?>">
                                <?php 
                                if ($request['status'] == 'Resolved') echo 'Completed';
                                else echo $request['status']; 
                                ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No maintenance requests found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

</body>
</html>