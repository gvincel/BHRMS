<?php
session_start();
include "db.php";

if (!isset($_SESSION['tenant_id'])) {
    header("Location: login.php");
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$tenant_name = $_SESSION['tenant_name'];
$message = '';
$message_type = '';
$tenant_query = $conn->prepare("SELECT * FROM tenants WHERE tenant_id = ?");
$tenant_query->bind_param("i", $tenant_id);
$tenant_query->execute();
$tenant_result = $tenant_query->get_result();
$tenant = $tenant_result->fetch_assoc();
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_info'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    $address = trim($_POST['address']);
    
    $check_email = $conn->prepare("SELECT tenant_id FROM tenants WHERE email = ? AND tenant_id != ?");
    $check_email->bind_param("si", $email, $tenant_id);
    $check_email->execute();
    
    if ($check_email->get_result()->num_rows > 0) {
        $message = "Email already registered by another tenant!";
        $message_type = "error";
    } else {
        $update_sql = "UPDATE tenants SET full_name = ?, email = ?, contact_number = ?, address = ? WHERE tenant_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssi", $full_name, $email, $contact_number, $address, $tenant_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['tenant_name'] = $full_name;
            $tenant['full_name'] = $full_name;
            $tenant['email'] = $email;
            $tenant['contact_number'] = $contact_number;
            $tenant['address'] = $address;
            
            $message = "Personal information updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating information!";
            $message_type = "error";
        }
        $update_stmt->close();
    }
    $check_email->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile</title>
  <link rel="stylesheet" href="tprof.css">
  <style>
    .message {
        padding: 12px;
        margin-bottom: 20px;
        border-radius: 6px;
        text-align: center;
    }
    .success {
        background-color: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .error {
        background-color: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
    .info {
        background-color: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }
    .tenant-info-note {
        background-color: #fef3c7;
        border: 1px solid #fde68a;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 15px;
        color: #92400e;
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
        <li><a href="tpay.php"><i class="fas fa-hand-holding-dollar"></i>Payments</a></li>
        <li><a href="trom.php"><i class="fas fa-bed"></i>Rooms</a></li>
        <li class="active"><a href="tprof.php"><i class="fas fa-user"></i>Profile</a></li>
        <li class="logout"><a href="logout.php"><i class="fas fa-right-from-bracket"></i>Logout</a></li>
      </ul>
    </aside>

    <main class="main">
      <div class="topbar">
        <h1>Profile</h1>
        <div class="profile"><?php echo htmlspecialchars($tenant_name); ?></div>
      </div>

      <?php if ($message): ?>
      <div class="message <?php echo $message_type; ?>">
          <?php echo htmlspecialchars($message); ?>
      </div>
      <?php endif; ?>

      <div class="tenant-info-note">
          <strong>Tenant ID:</strong> <?php echo $tenant_id; ?> 
          | <strong>Room:</strong> 
          <?php 
          $room_query = $conn->prepare("SELECT r.room_number FROM tenants t LEFT JOIN rooms r ON t.room_id = r.room_id WHERE t.tenant_id = ?");
          $room_query->bind_param("i", $tenant_id);
          $room_query->execute();
          $room_result = $room_query->get_result();
          $room_data = $room_result->fetch_assoc();
          echo htmlspecialchars($room_data['room_number'] ?? 'Not assigned');
          ?>
      </div>

      <div class="table-section">
        <h2>Personal Information</h2>
        <form class="settings-form" method="POST" action="">
          <input type="hidden" name="update_info" value="1">
          
          <label for="name">Full Name</label>
          <input type="text" id="name" name="full_name" 
                 value="<?php echo htmlspecialchars($tenant['full_name'] ?? ''); ?>" 
                 placeholder="John Doe" required>
          
          <label for="email">Email</label>
          <input type="email" id="email" name="email" 
                 value="<?php echo htmlspecialchars($tenant['email'] ?? ''); ?>" 
                 placeholder="john@example.com">
          
          <label for="phone">Phone Number</label>
          <input type="text" id="phone" name="contact_number" 
                 value="<?php echo htmlspecialchars($tenant['contact_number'] ?? ''); ?>" 
                 placeholder="09123456789">
          
          <label for="address">Address</label>
          <textarea id="address" name="address" 
                    placeholder="Enter your address" 
                    style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 15px;"><?php echo htmlspecialchars($tenant['address'] ?? ''); ?></textarea>
          
          <button type="submit">Update Info</button>
        </form>
      </div>

      <div class="table-section" style="margin-top: 30px;">
        <h2>Account Information</h2>
        
          <p><strong>Tenant ID:</strong> <?php echo $tenant_id; ?></p>
          <p><strong>Move-in Date:</strong> <?php echo date('F d, Y', strtotime($tenant['move_in_date'] ?? '')); ?></p>
          <p><strong>Account Status:</strong> <?php echo htmlspecialchars($tenant['status'] ?? 'Active'); ?></p>
          <p><strong>Room ID:</strong> <?php echo htmlspecialchars($tenant['room_id'] ?? 'Not assigned'); ?></p>
        </div>
      </div>
    </main>
  </div>
</body>

</html>

