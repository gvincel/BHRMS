<?php
session_start();
include "db.php";


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// Handle form submission for adding a room
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_room'])) {
    $room_number = trim($_POST['room_number']);
    $room_type = trim($_POST['room_type']);
    $capacity = (int)$_POST['capacity'];
    $monthly_rent = (float)$_POST['monthly_rent'];
    $status = trim($_POST['status']);
    
    // Check if room number already exists
    $check_sql = "SELECT room_id FROM rooms WHERE room_number = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $room_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $message = "Room number already exists!";
        $message_type = "error";
    } else {
        // Insert new room
        $sql = "INSERT INTO rooms (room_number, room_type, capacity, monthly_rent, status) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssids", $room_number, $room_type, $capacity, $monthly_rent, $status);
        
        if ($stmt->execute()) {
            $message = "Room added successfully!";
            $message_type = "success";
            // Clear form by redirecting
            header("Location: room.php?message=" . urlencode($message) . "&type=" . $message_type);
            exit();
        } else {
            $message = "Error adding room: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// Check for message from URL
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $message_type = $_GET['type'] ?? 'info';
}

// Fetch all rooms from database
$rooms_query = $conn->query("SELECT * FROM rooms ORDER BY room_number");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rooms - Boarding House Management System</title>
    <link rel="stylesheet" href="dash.css">
    <style>
        <?php echo file_get_contents('room.css'); ?>
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 6px;
        }
        .success {
            background-color: #d4edda;
            color: #065f46;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #b91c1c;
            border: 1px solid #f5c6cb;
        }
        .maintenance {
            color: #d97706;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <h2 class="logo">Boarding House</h2>
        <ul class="menu">
            <li><a href="dash.php">Dashboard</a></li>
            <li class="active"><a href="room.php">Rooms</a></li>
            <li><a href="tenant.php">Tenants</a></li>
            <li><a href="payment.php">Payments</a></li>
            <li><a href="mainten.php">Maintenance</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="expenses.php">Expenses</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li class="logout"><a href="logout.php" style="color: inherit; text-decoration: none;">Logout</a></li>
        </ul>
    </aside>

    <main class="main">
        <header class="topbar">
            <h1>Rooms</h1>
            <div class="profile">
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
                (<?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?>)
            </div>
        </header>

        <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <div class="add-room-container">
            <button class="add-room-btn" onclick="openModal()">+ Add Room</button>
        </div>

        <section class="table-section">
            <h2>All Rooms</h2>
            <table>
                <thead>
                    <tr>
                        <th>Room Number</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th>Price</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rooms_query->num_rows > 0): ?>
                        <?php while ($room = $rooms_query->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                            <td><?php echo htmlspecialchars($room['room_type']); ?></td>
                            <td><?php echo $room['capacity']; ?></td>
                            <td class="<?php echo strtolower($room['status']); ?>">
                                <?php echo $room['status']; ?>
                            </td>
                            <td>₱<?php echo number_format($room['monthly_rent'], 2); ?></td>
                            <td>
                                <button class="edit-btn" onclick="editRoom(
                                    <?php echo $room['room_id']; ?>,
                                    '<?php echo htmlspecialchars($room['room_number']); ?>',
                                    '<?php echo htmlspecialchars($room['room_type']); ?>',
                                    <?php echo $room['capacity']; ?>,
                                    <?php echo $room['monthly_rent']; ?>,
                                    '<?php echo $room['status']; ?>'
                                )">Edit</button>
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
        </section>
    </main>
</div>

<div id="roomModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Add New Room</h2>
        <form method="POST" action="">
            <input type="hidden" name="add_room" value="1">
            <label>Room Number</label>
            <input type="text" name="room_number" placeholder="Enter room number" required>
            
            <label>Type</label>
            <select name="room_type" required>
                <option value="">Select type</option>
                <option value="Single">Single</option>
                <option value="Double">Double</option>
                <option value="Suite">Suite</option>
            </select>
            
            <label>Capacity</label>
            <input type="number" name="capacity" placeholder="Enter capacity" min="1" required>
            
            <label>Monthly Rent</label>
            <input type="number" name="monthly_rent" placeholder="Enter price" step="0.01" required>
            
            <label>Status</label>
            <select name="status" required>
                <option value="">Select status</option>
                <option value="Available">Available</option>
                <option value="Occupied">Occupied</option>
                <option value="Maintenance">Maintenance</option>
            </select>
            
            <button type="submit">Add Room</button>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2>Edit Room</h2>
        <form method="POST" action="edit_room.php" id="editForm">
            <input type="hidden" name="room_id" id="edit_room_id">
            
            <label>Room Number</label>
            <input type="text" name="room_number" id="edit_room_number" required>
            
            <label>Type</label>
            <select name="room_type" id="edit_room_type" required>
                <option value="Single">Single</option>
                <option value="Double">Double</option>
                <option value="Suite">Suite</option>
            </select>
            
            <label>Capacity</label>
            <input type="number" name="capacity" id="edit_capacity" min="1" required>
            
            <label>Monthly Rent</label>
            <input type="number" name="monthly_rent" id="edit_monthly_rent" step="0.01" required>
            
            <label>Status</label>
            <select name="status" id="edit_status" required>
                <option value="Available">Available</option>
                <option value="Occupied">Occupied</option>
                <option value="Maintenance">Maintenance</option>
            </select>
            
            <button type="submit">Update Room</button>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('roomModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('roomModal').style.display = 'none';
}

function openEditModal() {
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function editRoom(id, number, type, capacity, rent, status) {
    document.getElementById('edit_room_id').value = id;
    document.getElementById('edit_room_number').value = number;
    document.getElementById('edit_room_type').value = type;
    document.getElementById('edit_capacity').value = capacity;
    document.getElementById('edit_monthly_rent').value = rent;
    document.getElementById('edit_status').value = status;
    openEditModal();
}

window.onclick = function(event) {
    if (event.target == document.getElementById('roomModal')) {
        closeModal();
    }
    if (event.target == document.getElementById('editModal')) {
        closeEditModal();
    }
}
</script>

</body>
</html>