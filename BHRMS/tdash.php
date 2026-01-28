<?php
session_start();
include "db.php";

if (!isset($_SESSION['tenant_id']) || $_SESSION['user_type'] !== 'tenant') {
    header("Location: login.php");
    exit();
}

$tenant_id = $_SESSION['tenant_id'];

$tenant_query = $conn->prepare("
    SELECT t.*, r.room_number, r.monthly_rent 
    FROM tenants t 
    LEFT JOIN rooms r ON t.room_id = r.room_id 
    WHERE t.tenant_id = ?
");
$tenant_query->bind_param("i", $tenant_id);
$tenant_query->execute();
$tenant_result = $tenant_query->get_result(); 
$tenant = $tenant_result->fetch_assoc(); 

$pending_query = $conn->prepare("SELECT COUNT(*) as count FROM maintenance_requests WHERE tenant_id = ? AND status = 'Pending'");
$pending_query->bind_param("i", $tenant_id);
$pending_query->execute();
$pending_result = $pending_query->get_result(); 
$pending_row = $pending_result->fetch_assoc();
$pending_count = $pending_row['count'];

$paid_query = $conn->prepare("SELECT COUNT(*) as count FROM payments WHERE tenant_id = ? AND (remarks LIKE '%paid%' OR remarks LIKE '%completed%')");
$paid_query->bind_param("i", $tenant_id);
$paid_query->execute();
$paid_result = $paid_query->get_result(); 
$paid_row = $paid_result->fetch_assoc(); 
$paid_count = $paid_row['count'];

$recent_payments = $conn->prepare("
    SELECT * FROM payments 
    WHERE tenant_id = ? 
    ORDER BY payment_date DESC 
    LIMIT 3
");
$recent_payments->bind_param("i", $tenant_id);
$recent_payments->execute();
$payments_result = $recent_payments->get_result(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tenant Dashboard</title>
  <link rel="stylesheet" href="tdash.css">
  <style>
    .paid {
        color: #10b981;
        font-weight: bold;
    }
    .pending {
        color: #f59e0b;
        font-weight: bold;
    }
    .welcome-message {
        background: var(--primary);
        color: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .welcome-message h2 {
        margin: 0 0 10px 0;
    }
    .tenant-info {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .info-badge {
        background: rgba(255,255,255,0.2);
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 14px;
    }
    .cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .card h3 {
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 10px;
    }
    .card p {
        font-size: 16px;
        color: #1f2937;
        line-height: 1.5;
    }
    .table-section {
        background: rgba(255, 255, 255, 0.08);
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    th, td {
        padding: 12px;
        border-bottom: 1px solid #e5e7eb;
        text-align: center;
    }
    th {
        background: var(--primary);
        color: white;
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
    
    /* ============ ADD NOTIFICATION STYLES ============ */
    .notification-container {
        position: relative;
        display: inline-block;
        margin-right: 20px;
    }
    
    .notification-icon {
        position: relative;
        cursor: pointer;
        font-size: 1.5rem;
        color: #333;
        padding: 8px;
        border-radius: 50%;
        transition: background 0.3s;
    }
    
    .notification-icon:hover {
        background: #f0f0f0;
    }
    
    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        background-color: #ff4757;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        border: 2px solid white;
    }
    
    .notification-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        width: 300px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        margin-top: 10px;
    }
    
    .notification-dropdown.active {
        display: block;
    }
    
    .notification-header {
        padding: 15px;
        border-bottom: 1px solid #e5e7eb;
        background: var(--primary);
        color: white;
        border-radius: 8px 8px 0 0;
    }
    
    .notification-header h3 {
        margin: 0;
        font-size: 16px;
    }
    
    .no-notifications {
        padding: 30px 20px;
        text-align: center;
        color: #6b7280;
        font-size: 14px;
    }
    
    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
    }
    
    .profile-area {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    
    .profile {
        font-weight: 600;
        color: white;
    }
    /* ============ END NOTIFICATION STYLES ============ */
  </style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
</head>
<body>
  <div class="container">

    <aside class="sidebar">
      <div class="logo">Boarding House</div>
      <ul class="menu">
        <li class="active"><a href="tdash.php"><i class="fas fa-chart-line"></i>Dashboard</a></li>
        <li><a href="tmain.php"><i class="fas fa-tools"></i>Maintenance Request</a></li>
        <li><a href="tpay.php"><i class="fas fa-hand-holding-dollar"></i>Payments</a></li>
        <li><a href="trom.php"><i class="fas fa-bed"></i>Rooms</a></li>
        <li><a href="tprof.php"><i class="fas fa-user"></i>Profile</a></li>
        <li class="logout"><a href="logout.php"><i class="fas fa-right-from-bracket"></i>Logout</a></li>
      </ul>
    </aside>
     
    <main class="main">
      
      <div class="topbar">
        <h1>My Dashboard</h1>
        <div class="profile-area">
          <!-- ============ ADD NOTIFICATION ICON HERE ============ -->
          <div class="notification-container">
            <div class="notification-icon" onclick="toggleNotifications()">
              <i class="fas fa-bell"></i>
              <!-- Static badge for testing - you can remove or change later -->
              <span class="notification-badge">3</span>
            </div>
            <div class="notification-dropdown" id="notificationDropdown">
              <div class="notification-header">
                <h3>Notifications</h3>
              </div>
              <div class="no-notifications">
                <p><i class="fas fa-bell" style="font-size: 24px; color: #9ca3af; margin-bottom: 10px;"></i></p>
                <p>No notifications yet</p>
                <p style="font-size: 12px; margin-top: 5px; color: #9ca3af;">Connect to database to see real notifications</p>
              </div>
            </div>
          </div>
          <!-- ============ END NOTIFICATION ICON ============ -->
          
          <div class="profile"><?php echo htmlspecialchars($_SESSION['tenant_name']); ?></div>
        </div>
      </div>

      <div class="welcome-message">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['tenant_name']); ?>!</h2>
        <div class="tenant-info">
          <span class="info-badge">Tenant ID: <?php echo $_SESSION['tenant_id']; ?></span>
          <?php if ($tenant && isset($tenant['room_number'])): ?>
          <span class="info-badge">Room: <?php echo htmlspecialchars($tenant['room_number']); ?></span>
          <?php endif; ?>
          <?php if ($tenant && isset($tenant['status'])): ?>
          <span class="info-badge">Status: <?php echo htmlspecialchars($tenant['status']); ?></span>
          <?php endif; ?>
        </div>
      </div>

      
      <div class="cards">
        <div class="card">
          <h3>My Information</h3>
          <?php if ($tenant): ?>
          <p style="font-size: 16px; margin-top: 10px;">
            <strong>Name:</strong> <?php echo htmlspecialchars($tenant['full_name']); ?><br>
            <strong>Room:</strong> <?php echo htmlspecialchars($tenant['room_number'] ?? 'N/A'); ?><br>
            <strong>Rent:</strong> ₱<?php echo number_format($tenant['monthly_rent'] ?? 0, 2); ?><br>
            <strong>Contact:</strong> <?php echo htmlspecialchars($tenant['contact_number'] ?? 'N/A'); ?>
          </p>
          <?php else: ?>
          <p>Information not available</p>
          <?php endif; ?>
        </div>
        <div class="card">
          <h3>Pending Requests</h3>
          <p style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo $pending_count; ?></p>
        </div>
        <div class="card">
          <h3>Paid Payments</h3>
          <p style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo $paid_count; ?></p>
        </div>
      </div>

      <div class="table-section">
        <h2>Recent Payments</h2>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Amount</th>
              <th>Method</th>
              <th>Status</th>
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
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px;">No payment records found</td>
                </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <!-- ============ ADD JAVASCRIPT FOR NOTIFICATIONS ============ -->
  <script>
    function toggleNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.classList.toggle('active');
        console.log('Notifications toggled');
    }
    
    // Close notifications when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('notificationDropdown');
        const icon = document.querySelector('.notification-icon');
        
        if (!icon.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.classList.remove('active');
        }
    });
  </script>
</body>

</html>