<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// Handle Add Tenant
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_tenant'])) {
    $full_name = trim($_POST['full_name']);
    $contact_number = trim($_POST['contact_number']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $room_id = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : NULL;
    $move_in_date = $_POST['move_in_date'];
    $status = $_POST['status'];

    $check_sql = "SELECT tenant_id FROM tenants WHERE full_name = ? AND contact_number = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $full_name, $contact_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $message = "Tenant already exists!";
        $message_type = "error";
    } else {
        if ($room_id) {
            $sql = "INSERT INTO tenants (full_name, contact_number, email, address, room_id, move_in_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssiss", $full_name, $contact_number, $email, $address, $room_id, $move_in_date, $status);
        } else {
            $sql = "INSERT INTO tenants (full_name, contact_number, email, address, move_in_date, status)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $full_name, $contact_number, $email, $address, $move_in_date, $status);
        }

        if ($stmt->execute()) {
            $message = "Tenant added successfully!";
            $message_type = "success";
        } else {
            $message = "Error adding tenant: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
    $check_stmt->close();
}

// Handle Update Tenant
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_tenant'])) {
    $tenant_id = (int)$_POST['tenant_id'];
    $full_name = trim($_POST['full_name']);
    $contact_number = trim($_POST['contact_number']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $room_id = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : NULL;
    $move_in_date = $_POST['move_in_date'];
    $status = $_POST['status'];

    $update_sql = "UPDATE tenants SET full_name=?, contact_number=?, email=?, address=?, room_id=?, move_in_date=?, status=? WHERE tenant_id=?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssssissi", $full_name, $contact_number, $email, $address, $room_id, $move_in_date, $status, $tenant_id);

    if ($stmt->execute()) {
        $message = "Tenant updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating tenant: " . $stmt->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Handle Delete Tenant
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $delete_sql = "DELETE FROM tenants WHERE tenant_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $delete_id);

    if ($delete_stmt->execute()) {
        $message = "Tenant deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting tenant!";
        $message_type = "error";
    }
    $delete_stmt->close();
}

// Fetch tenants and rooms
$tenants_query = $conn->query("SELECT t.tenant_id, t.full_name, t.contact_number, t.email, t.address, t.move_in_date, t.status, r.room_number FROM tenants t LEFT JOIN rooms r ON t.room_id = r.room_id ORDER BY t.full_name");
$rooms_query = $conn->query("SELECT room_id, room_number FROM rooms WHERE status='Available'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tenant Management</title>
<link rel="stylesheet" href="dash.css">
<style>
.message { padding: 10px; margin: 10px 0; border-radius: 6px; text-align: center; }
.success { background-color: #d4edda; color: #065f46; border: 1px solid #c3e6cb; }
.error { background-color: #f8d7da; color: #b91c1c; border: 1px solid #f5c6cb; }
.active-tenant { color: #10b981; font-weight: bold; }
.inactive-tenant { color: #b91c1c; font-weight: bold; }
.edit-btn, .delete-btn { padding: 6px 12px; font-size: 14px; border: none; border-radius: 6px; cursor: pointer; color: #fff; text-decoration: none; }
.edit-btn { background-color: #2563eb; }
.edit-btn:hover { background-color: #1e40af; }
.delete-btn { background-color: #dc2626; }
.delete-btn:hover { background-color: #b91c1c; }
.action-buttons { display: flex; justify-content: center; gap: 5px; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); overflow-y: auto; padding: 20px; box-sizing: border-box; }
.modal-content { background-color: #fff; margin: 50px auto; padding: 20px; border-radius: 10px; width: 400px; max-width: 100%; box-shadow: 0 4px 15px rgba(0,0,0,0.2); color: black; }
.modal-content h2 { margin-bottom: 15px; }
.modal-content form label { display: block; margin-top: 10px; font-weight: bold; }
.modal-content form input, .modal-content form select { width: 100%; padding: 8px; margin-top: 5px; border-radius: 6px; border: 1px solid #ccc; }
.modal-content form button { margin-top: 15px; width: 100%; padding: 10px; background-color: #10b981; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
.modal-content form button:hover { background-color: #059669; }
.close { float: right; font-size: 24px; font-weight: bold; cursor: pointer; }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
</head>
<body>

<div class="container">
<aside class="sidebar">
<h2 class="logo">Boarding House</h2>
<ul class="menu">
    <li><a href="dash.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
    <li><a href="room.php"><i class="fas fa-bed"></i> Rooms</a></li>
    <li class="active"><a href="tenant.php"><i class="fas fa-users"></i> Tenants</a></li>
    <li><a href="payment.php"><i class="fas fa-hand-holding-dollar"></i> Payments</a></li>
    <li><a href="mainten.php"><i class="fas fa-tools"></i> Maintenance</a></li>
    <li><a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a></li>
    <li><a href="expenses.php"><i class="fas fa-receipt"></i> Expenses</a></li>
    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
    <li class="logout"><a href="logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a></li>
</ul>
</aside>

<main class="main">
<header class="topbar">
    <h1>Tenants</h1>
    <div class="profile">
        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?> (<?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?>)
    </div>
</header>

<?php if ($message): ?>
<div class="message <?php echo $message_type; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="add-btn-container">
    <button class="add-btn" onclick="openTenantModal()">+ Add Tenant</button>
</div>

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
            <th>Action</th>
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
                <td>
                    <div class="action-buttons">
                        <button class="edit-btn"
                            onclick="openEditTenantModal(
                                <?php echo $tenant['tenant_id']; ?>,
                                '<?php echo addslashes($tenant['full_name']); ?>',
                                '<?php echo addslashes($tenant['contact_number']); ?>',
                                '<?php echo addslashes($tenant['email']); ?>',
                                '<?php echo addslashes($tenant['address']); ?>',
                                '<?php echo $tenant['room_number']; ?>',
                                '<?php echo $tenant['move_in_date']; ?>',
                                '<?php echo $tenant['status']; ?>'
                            )">Edit</button>
                        <button class="delete-btn" onclick="confirmDelete(<?php echo $tenant['tenant_id']; ?>)">Delete</button>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7" style="text-align: center;">No tenants found</td></tr>
        <?php endif; ?>
    </tbody>
</table>
</section>
</main>
</div>

<!-- Add Tenant Modal -->
<div id="tenantModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeTenantModal()">&times;</span>
        <h2>Add New Tenant</h2>
        <form method="POST" action="">
            <input type="hidden" name="add_tenant" value="1">
            <label>Full Name</label>
            <input type="text" name="full_name" required>
            <label>Contact Number</label>
            <input type="text" name="contact_number">
            <label>Email</label>
            <input type="email" name="email">
            <label>Address</label>
            <input type="text" name="address">
            <label>Room</label>
            <select name="room_id">
                <option value="">Not Assigned</option>
                <?php 
                $rooms_query->data_seek(0); // Reset pointer
                while ($room = $rooms_query->fetch_assoc()): ?>
                    <option value="<?php echo $room['room_id']; ?>">
                        Room <?php echo htmlspecialchars($room['room_number']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <label>Move-in Date</label>
            <input type="date" name="move_in_date">
            <label>Status</label>
            <select name="status" required>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
            <button type="submit">Add Tenant</button>
        </form>
    </div>
</div>

<!-- Edit Tenant Modal -->
<div id="editTenantModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditTenantModal()">&times;</span>
        <h2>Edit Tenant</h2>
        <form method="POST" action="">
            <input type="hidden" name="update_tenant" value="1">
            <input type="hidden" name="tenant_id" id="edit_tenant_id">
            
            <label>Full Name</label>
            <input type="text" name="full_name" id="edit_full_name" required>
            <label>Contact Number</label>
            <input type="text" name="contact_number" id="edit_contact_number">
            <label>Email</label>
            <input type="email" name="email" id="edit_email">
            <label>Address</label>
            <input type="text" name="address" id="edit_address">
            <label>Room</label>
            <select name="room_id" id="edit_room_id">
                <option value="">Not Assigned</option>
                <?php 
                $rooms_query->data_seek(0); 
                while ($room = $rooms_query->fetch_assoc()): ?>
                    <option value="<?php echo $room['room_id']; ?>">
                        Room <?php echo htmlspecialchars($room['room_number']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <label>Move-in Date</label>
            <input type="date" name="move_in_date" id="edit_move_in_date">
            <label>Status</label>
            <select name="status" id="edit_status" required>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
            <button type="submit">Update Tenant</button>
        </form>
    </div>
</div>

<script>
function openTenantModal() { document.getElementById('tenantModal').style.display = 'block'; }
function closeTenantModal() { document.getElementById('tenantModal').style.display = 'none'; }

function openEditTenantModal(id, full_name, contact, email, address, room, move_in, status) {
    document.getElementById('edit_tenant_id').value = id;
    document.getElementById('edit_full_name').value = full_name;
    document.getElementById('edit_contact_number').value = contact;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_address').value = address;
    document.getElementById('edit_room_id').value = room;
    document.getElementById('edit_move_in_date').value = move_in;
    document.getElementById('edit_status').value = status;
    document.getElementById('editTenantModal').style.display = 'block';
}
function closeEditTenantModal() { document.getElementById('editTenantModal').style.display = 'none'; }

function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this tenant?')) {
        window.location.href = 'tenant.php?delete_id=' + id;
    }
}

window.onclick = function(event) {
    if (event.target == document.getElementById('tenantModal')) closeTenantModal();
    if (event.target == document.getElementById('editTenantModal')) closeEditTenantModal();
}
</script>

</body>
</html>
