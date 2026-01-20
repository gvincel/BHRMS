<?php
session_start();
include "db.php";


if (!isset($_SESSION['user_id']) && !isset($_SESSION['tenant_id'])) {
    header("Location: login.php");
    exit();
}


if (isset($_SESSION['tenant_id'])) {
    $user_type = 'tenant';
    $tenant_id = $_SESSION['tenant_id'];
} else {
    $user_type = 'admin';
}

$message = '';


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_request'])) {
    if ($user_type == 'tenant') {
        $tenant_id = $_SESSION['tenant_id'];
        $issue = trim($_POST['issue']);
        
        $room_query = $conn->prepare("SELECT room_id FROM tenants WHERE tenant_id = ?");
        $room_query->bind_param("i", $tenant_id);
        $room_query->execute();
        $room_result = $room_query->get_result();
        
        if ($room_row = $room_result->fetch_assoc()) {
            $room_id = $room_row['room_id'];
            
            $stmt = $conn->prepare("INSERT INTO maintenance_requests (room_id, tenant_id, issue_description, request_date, status) VALUES (?, ?, ?, CURDATE(), 'Pending')");
            $stmt->bind_param("iis", $room_id, $tenant_id, $issue);
            
            if ($stmt->execute()) {
                $message = "Maintenance request submitted successfully!";
            } else {
                $message = "Error submitting request!";
            }
            $stmt->close();
        }
        $room_query->close();
    }
}

if ($user_type == 'tenant') {
    $requests_query = $conn->prepare("
        SELECT mr.*, r.room_number, t.full_name 
        FROM maintenance_requests mr
        LEFT JOIN rooms r ON mr.room_id = r.room_id
        LEFT JOIN tenants t ON mr.tenant_id = t.tenant_id
        WHERE mr.tenant_id = ?
        ORDER BY mr.request_date DESC
    ");
    $requests_query->bind_param("i", $tenant_id);
} else {
    $requests_query = $conn->prepare("
        SELECT mr.*, r.room_number, t.full_name 
        FROM maintenance_requests mr
        LEFT JOIN rooms r ON mr.room_id = r.room_id
        LEFT JOIN tenants t ON mr.tenant_id = t.tenant_id
        ORDER BY mr.request_date DESC
    ");
}
$requests_query->execute();
$requests_result = $requests_query->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Maintenance Requests</title>
  <link rel="stylesheet" href="tmain.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>

</head>
<body>
  <div class="container">
    <aside class="sidebar">
      <div class="logo">Boarding House</div>
      <ul class="menu">
        <?php if ($user_type == 'tenant'): ?>
          <li><a href="tdash.php"><i class="fas fa-chart-line"></i>Dashboard</a></li>
          <li class="active"><a href="tmain.php"><i class="fas fa-tools"></i>Maintenance Request</a></li>
          <li><a href="tpay.php"><i class="fas fa-hand-holding-dollar"></i>Payments</a></li>
          <li><a href="trom.php"><i class="fas fa-bed"></i>Rooms</a></li>
          <li><a href="tprof.php"><i class="fas fa-user"></i>Profile</a></li>
          <li class="logout"><a href="logout.php"><i class="fas fa-right-from-bracket"></i>Logout</a></li>
        <?php else: ?>
          <li><a href="tdash.php"><i class="fas fa-chart-line"></i>Dashboard</a></li>
          <li class="active"><a href="tmain.php"><i class="fas fa-tools"></i>Maintenance Request</a></li>
          <li><a href="tpay.php"><i class="fas fa-hand-holding-dollar"></i>Payments</a></li>
          <li><a href="trom.php"><i class="fas fa-bed"></i>Rooms</a></li>
          <li><a href="tprof.php"><i class="fas fa-user"></i>Profile</a></li>
          <li class="logout"><a href="logout.php"><i class="fas fa-right-from-bracket"></i>Logout</a></li>
        <?php endif; ?>
      </ul>
    </aside>

    <main class="main">
      <div class="topbar">
        <h1>Maintenance Requests</h1>
        <div class="profile">
            <?php 
            if ($user_type == 'tenant') {
                echo htmlspecialchars($_SESSION['tenant_name'] ?? 'Tenant');
            } else {
                echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin');
            }
            ?>
        </div>
      </div>

      <div class="cards">
        <div class="card">
          <h3>Submit a New Request</h3>
          <?php if ($message): ?>
          <div style="color: <?php echo strpos($message, 'success') !== false ? 'green' : 'red'; ?>; margin: 10px 0; text-align: center;">
              <?php echo htmlspecialchars($message); ?>
          </div>
          <?php endif; ?>
          
          <form class="maintenance-form" method="POST" action="">
            <?php if ($user_type == 'tenant'): ?>
            <input type="hidden" name="submit_request" value="1">
            <label for="tenant">Tenant Name:</label>
            <input type="text" id="tenant" value="<?php echo htmlspecialchars($_SESSION['tenant_name'] ?? ''); ?>" readonly>
            
            <?php 
            $room_query = $conn->prepare("SELECT r.room_number FROM tenants t LEFT JOIN rooms r ON t.room_id = r.room_id WHERE t.tenant_id = ?");
            $room_query->bind_param("i", $tenant_id);
            $room_query->execute();
            $room_result = $room_query->get_result();
            $room_data = $room_result->fetch_assoc();
            ?>
            <label for="room">Room Number:</label>
            <input type="text" id="room" value="<?php echo htmlspecialchars($room_data['room_number'] ?? ''); ?>" readonly>
            
            <label for="issue">Issue Description:</label>
            <textarea id="issue" name="issue" placeholder="Describe the issue" required></textarea>
            
            <button type="submit">Submit Request</button>
            <?php else: ?>
            <p style="color: #666; text-align: center; margin: 10px 0;">
                Admin: Use the admin maintenance page for full functionality
            </p>
            <label for="tenant">Tenant Name:</label>
            <input type="text" id="tenant" placeholder="Enter tenant name">
            
            <label for="room">Room Number:</label>
            <input type="text" id="room" placeholder="Enter room number">
            
            <label for="issue">Issue Description:</label>
            <textarea id="issue" name="issue" placeholder="Describe the issue"></textarea>
            
            <button type="button" onclick="alert('Use the admin maintenance page for full functionality')">Submit Request</button>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <div class="table-section">
        <h2>Submitted Requests</h2>
        <table>
          <thead>
            <tr>
              <th>Tenant</th>
              <th>Room</th>
              <th>Issue</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($requests_result->num_rows > 0): ?>
                <?php while ($request = $requests_result->fetch_assoc()): ?>
                <tr>
                  <td><?php echo htmlspecialchars($request['full_name'] ?? 'Unknown'); ?></td>
                  <td><?php echo htmlspecialchars($request['room_number'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($request['issue_description']); ?></td>
                  <td class="<?php 
                    if ($request['status'] == 'Pending') echo 'pending';
                    elseif ($request['status'] == 'Resolved') echo 'paid';
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
                    <td colspan="4" style="text-align: center;">No maintenance requests found</td>
                </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>

</html>
