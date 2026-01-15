<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch all tenants with room information from database
$tenants_query = $conn->query("
    SELECT 
        t.tenant_id,
        t.full_name,
        t.contact_number,
        t.email,
        t.address,
        t.move_in_date,
        t.status,
        r.room_number
    FROM tenants t
    LEFT JOIN rooms r ON t.room_id = r.room_id
    ORDER BY t.full_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Management</title>
    <link rel="stylesheet" href="dash.css">
    <style>
        .active-tenant {
            color: #10b981;
            font-weight: bold;
        }
        .inactive-tenant {
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
        <li class="active">
            <a href="tenant.php">
                <i class="fas fa-users"></i> Tenants
            </a>
        </li>
        <li>
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
            <h1>Tenants</h1>
            <div class="profile">
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
                (<?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?>)
            </div>
        </header>

        <section class="table-section">
            <h2>Tenant List</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tenant Name</th>
                        <th>Room</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Move-in Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tenants_query->num_rows > 0): ?>
                        <?php while ($tenant = $tenants_query->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tenant['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($tenant['room_number'] ?? 'Not Assigned'); ?></td>
                            <td><?php echo htmlspecialchars($tenant['contact_number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($tenant['email'] ?? 'N/A'); ?></td>
                            <td><?php echo $tenant['move_in_date'] ? date('M d, Y', strtotime($tenant['move_in_date'])) : 'N/A'; ?></td>
                            <td class="<?php echo strtolower($tenant['status']) . '-tenant'; ?>">
                                <?php echo $tenant['status']; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No tenants found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

</body>
</html>