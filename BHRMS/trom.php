<?php
session_start();
include "db.php";


if (!isset($_SESSION['tenant_id'])) {
    header("Location: login.php");
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$tenant_name = $_SESSION['tenant_name'];

$room_query = $conn->prepare("
    SELECT 
        t.tenant_id,
        t.full_name,
        t.move_in_date,
        r.room_id,
        r.room_number,
        r.room_type,
        r.capacity,
        r.monthly_rent,
        r.status as room_status
    FROM tenants t
    LEFT JOIN rooms r ON t.room_id = r.room_id
    WHERE t.tenant_id = ?
");
$room_query->bind_param("i", $tenant_id);
$room_query->execute();
$room_result = $room_query->get_result();
$room_data = $room_result->fetch_assoc();

$all_rooms_query = $conn->query("
    SELECT 
        r.room_id,
        r.room_number,
        r.room_type,
        r.capacity,
        r.monthly_rent,
        r.status,
        t.full_name as occupant_name
    FROM rooms r
    LEFT JOIN tenants t ON r.room_id = t.room_id AND t.status = 'Active'
    ORDER BY r.room_number
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Room</title>
  <link rel="stylesheet" href="trom.css">
  <style>
    .paid {
        color: var(--warning);
        font-weight: bold;
    }
    .pending {
        color: #10b981;
        font-weight: bold;
    }
    .my-room-card {
        background: linear-gradient(135deg, var(--primary) 0%, #10b981 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    .my-room-card h3 {
        margin: 0 0 10px 0;
        font-size: 20px;
    }
    .room-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    .detail-item {
        background: rgba(255,255,255,0.1);
        padding: 10px;
        border-radius: 6px;
    }
    .detail-label {
        font-size: 12px;
        opacity: 0.8;
        margin-bottom: 5px;
    }
    .detail-value {
        font-size: 16px;
        font-weight: 600;
    }
    .feature-tag {
        display: inline-block;
        background: rgba(255,255,255,0.2);
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 12px;
        margin-left: 5px;
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
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="logo">Boarding House</div>
      <ul class="menu">
        <li><a href="tdash.php"><i class="fas fa-chart-line"></i>Dashboard</a></li>
        <li><a href="tmain.php"><i class="fas fa-tools"></i>Maintenance Request</a></li>
        <li><a href="tpay.php"><i class="fas fa-hand-holding-dollar"></i>Payments</a></li>
        <li class="active"><a href="trom.php"><i class="fas fa-bed"></i>Rooms</a></li>
        <li><a href="tprof.php"><i class="fas fa-user"></i>Profile</a></li>
        <li class="logout"><a href="logout.php"><i class="fas fa-right-from-bracket"></i>Logout</a></li>
      </ul>
    </aside>

    <main class="main">
      <div class="topbar">
        <h1>Rooms</h1>
        <div class="profile"><?php echo htmlspecialchars($tenant_name); ?></div>
      </div>

      <?php if ($room_data && isset($room_data['room_number'])): ?>
      <div class="my-room-card">
        <h3>My Room: <?php echo htmlspecialchars($room_data['room_number']); ?></h3>
        <div class="room-details">
          <div class="detail-item">
            <div class="detail-label">Room Type</div>
            <div class="detail-value"><?php echo htmlspecialchars($room_data['room_type'] ?? 'N/A'); ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Monthly Rent</div>
            <div class="detail-value">₱<?php echo number_format($room_data['monthly_rent'] ?? 0, 2); ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Capacity</div>
            <div class="detail-value"><?php echo htmlspecialchars($room_data['capacity'] ?? 'N/A'); ?> person(s)</div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Move-in Date</div>
            <div class="detail-value"><?php echo date('F d, Y', strtotime($room_data['move_in_date'] ?? '')); ?></div>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="my-room-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
        <h3>No Room Assigned</h3>
        <p>You haven't been assigned to a room yet. Please contact the administrator.</p>
      </div>
      <?php endif; ?>

      <div class="cards">
        <div class="card">
          <h3>My Room Status</h3>
          <p class="<?php echo ($room_data['room_status'] ?? '') == 'Occupied' ? 'paid' : 'pending'; ?>">
            <?php echo htmlspecialchars($room_data['room_status'] ?? 'Not Assigned'); ?>
          </p>
        </div>
        <div class="card">
          <h3>Room Type</h3>
          <p><?php echo htmlspecialchars($room_data['room_type'] ?? 'N/A'); ?></p>
        </div>
        <div class="card">
          <h3>Monthly Rent</h3>
          <p>₱<?php echo number_format($room_data['monthly_rent'] ?? 0, 2); ?></p>
        </div>
      </div>
      <div class="room-filter">
        <label for="feature-select">Filter by Room Type:</label>
        <select id="feature-select">
          <option value="all">All Rooms</option>
          <option value="Single">Single Rooms</option>
          <option value="Double">Double Rooms</option>
        </select>
      </div>

      <div class="table-section">
        <h2>All Rooms in Boarding House</h2>
        <table id="rooms-table">
          <thead>
            <tr>
              <th>Room Number</th>
              <th>Type</th>
              <th>Capacity</th>
              <th>Monthly Rent</th>
              <th>Occupant</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($all_rooms_query->num_rows > 0): ?>
                <?php while ($room = $all_rooms_query->fetch_assoc()): ?>
                <tr data-feature="<?php echo htmlspecialchars($room['room_type'] ?? ''); ?>">
                  <td>
                    <?php echo htmlspecialchars($room['room_number']); ?>
                    <?php if (isset($room_data['room_id']) && $room['room_id'] == $room_data['room_id']): ?>
                    <span class="feature-tag">My Room</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($room['room_type']); ?></td>
                  <td><?php echo $room['capacity']; ?> person(s)</td>
                  <td>₱<?php echo number_format($room['monthly_rent'], 2); ?></td>
                  <td><?php echo htmlspecialchars($room['occupant_name'] ?? '-'); ?></td>
                  <td class="<?php echo $room['status'] == 'Occupied' ? 'paid' : 'pending'; ?>">
                    <?php echo $room['status']; ?>
                  </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No rooms found</td>
                </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <script>
    const featureSelect = document.getElementById("feature-select");
    const tableRows = document.querySelectorAll("#rooms-table tbody tr");

    featureSelect.addEventListener("change", function() {
      const selected = this.value;
      tableRows.forEach(row => {
        const feature = row.getAttribute("data-feature");
        if (selected === "all" || feature === selected) {
          row.style.display = "";
        } else {
          row.style.display = "none";
        }
      });
    });
  </script>
</body>

</html>
