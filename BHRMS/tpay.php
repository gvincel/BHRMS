<?php
session_start();
include "db.php";


if (!isset($_SESSION['tenant_id'])) {
    header("Location: login.php");
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$tenant_name = $_SESSION['tenant_name'];


$room_query = $conn->prepare("SELECT r.room_number, r.monthly_rent FROM tenants t LEFT JOIN rooms r ON t.room_id = r.room_id WHERE t.tenant_id = ?");
$room_query->bind_param("i", $tenant_id);
$room_query->execute();
$room_result = $room_query->get_result();
$room_data = $room_result->fetch_assoc();
$room_number = $room_data['room_number'] ?? 'N/A';
$monthly_rent = $room_data['monthly_rent'] ?? 0;
$total_query = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE tenant_id = ?");
$total_query->bind_param("i", $tenant_id);
$total_query->execute();
$total_result = $total_query->get_result();
$total_payments = $total_result->fetch_assoc()['total'];

$pending_query = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as pending FROM payments WHERE tenant_id = ? AND (remarks LIKE '%pending%' OR remarks IS NULL)");
$pending_query->bind_param("i", $tenant_id);
$pending_query->execute();
$pending_result = $pending_query->get_result();
$pending_payments = $pending_result->fetch_assoc()['pending'];

$completed_query = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as completed FROM payments WHERE tenant_id = ? AND (remarks LIKE '%paid%' OR remarks LIKE '%completed%')");
$completed_query->bind_param("i", $tenant_id);
$completed_query->execute();
$completed_result = $completed_query->get_result();
$completed_payments = $completed_result->fetch_assoc()['completed'];

$payments_query = $conn->prepare("
    SELECT p.*, t.full_name, r.room_number 
    FROM payments p
    LEFT JOIN tenants t ON p.tenant_id = t.tenant_id
    LEFT JOIN rooms r ON t.room_id = r.room_id
    WHERE p.tenant_id = ?
    ORDER BY p.payment_date DESC
");
$payments_query->bind_param("i", $tenant_id);
$payments_query->execute();
$payments_result = $payments_query->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Payments</title>
  <link rel="stylesheet" href="tpay.css">
  <style>
    .paid {
        color: #10b981;
        font-weight: bold;
    }
    .pending {
        color: #f59e0b;
        font-weight: bold;
    }
    .monthly-rent-card {
        background: linear-gradient(135deg, var(--primary) 0%, #10b981 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    .monthly-rent-card h3 {
        margin: 0 0 10px 0;
        font-size: 18px;
    }
    .monthly-rent-card p {
        margin: 5px 0;
        font-size: 14px;
    }
    .monthly-rent-amount {
        font-size: 28px !important;
        font-weight: bold;
        margin: 10px 0 !important;
    }
    .menu a {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .menu a i {
        width: 18px;
        text-align: center;
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
</head>
<body>
  <div class="container">
    <aside class="sidebar">
      <div class="logo">Boarding House</div>
      <ul class="menu">
        <li><a href="tdash.php"><i class="fas fa-chart-line"></i>Dashboard</a></li>
        <li><a href="tmain.php"><i class="fas fa-tools"></i>Maintenance Request</a></li>
        <li class="active"><a href="tpay.php"><i class="fas fa-hand-holding-dollar"></i>Payments</a></li>
        <li><a href="trom.php"><i class="fas fa-bed"></i>Rooms</a></li>
        <li><a href="tprof.php"><i class="fas fa-user"></i>Profile</a></li>
        <li class="logout"><a href="logout.php"><i class="fas fa-right-from-bracket"></i>Logout</a></li>
      </ul>
    </aside>
    <main class="main">
      <div class="topbar">
        <h1>Payments</h1>
        <div class="profile"><?php echo htmlspecialchars($tenant_name); ?></div>
      </div>

      <div class="monthly-rent-card">
        <h3>Monthly Rent Due</h3>
        <p class="monthly-rent-amount">₱<?php echo number_format($monthly_rent, 2); ?></p>
        <p>Room: <?php echo htmlspecialchars($room_number); ?></p>
        <p>Next Payment: <?php echo date('F d, Y', strtotime('+1 month')); ?></p>
      </div>

      <div class="cards">
        <div class="card">
          <h3>Total Payments</h3>
          <p>₱<?php echo number_format($total_payments, 2); ?></p>
        </div>
        <div class="card">
          <h3>Pending Payments</h3>
          <p>₱<?php echo number_format($pending_payments, 2); ?></p>
        </div>
        <div class="card">
          <h3>Completed Payments</h3>
          <p>₱<?php echo number_format($completed_payments, 2); ?></p>
        </div>
      </div>

      <div class="table-section">
        <h2>My Payment Records</h2>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Amount</th>
              <th>Payment Method</th>
              <th>Status</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($payments_result->num_rows > 0): ?>
                <?php while ($payment = $payments_result->fetch_assoc()): ?>
                <tr>
                  <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                  <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                  <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                  <td class="<?php 
                    if (isset($payment['remarks']) && (stripos($payment['remarks'], 'paid') !== false || stripos($payment['remarks'], 'completed') !== false)) {
                        echo 'paid';
                    } else {
                        echo 'pending';
                    }
                  ?>">
                    <?php 
                    if (isset($payment['remarks']) && (stripos($payment['remarks'], 'paid') !== false || stripos($payment['remarks'], 'completed') !== false)) {
                        echo 'Paid';
                    } else {
                        echo 'Pending';
                    }
                    ?>
                  </td>
                  <td><?php echo htmlspecialchars($payment['remarks'] ?? 'N/A'); ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center;">No payment records found</td>
                </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>

</html>
