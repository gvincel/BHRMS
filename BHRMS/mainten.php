<?php
session_start();
include "db.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';

// ==========================
// Handle Edit Maintenance Request
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_request'])) {
    $request_id = (int)$_POST['request_id'];
    $status = trim($_POST['status']);
    $issue_description = trim($_POST['issue_description']);

    $sql = "UPDATE maintenance_requests 
            SET status = ?, issue_description = ?
            WHERE request_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $status, $issue_description, $request_id);

    if ($stmt->execute()) {
        header("Location: mainten.php?message=" . urlencode("Request updated successfully!") . "&type=success");
        exit();
    } else {
        $message = "Error updating request!";
        $message_type = "error";
    }
    $stmt->close();
}

// ==========================
// Handle Delete Maintenance Request
// ==========================
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $delete_sql = "DELETE FROM maintenance_requests WHERE request_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $delete_id);

    if ($delete_stmt->execute()) {
        $message = "Request deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting request!";
        $message_type = "error";
    }
    $delete_stmt->close();
}

// Check for message from URL
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $message_type = $_GET['type'] ?? 'info';
}

// Fetch maintenance statistics
$stats = [];
$pending_query = $conn->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE status = 'Pending'");
$stats['pending'] = $pending_query->fetch_assoc()['count'];

$progress_query = $conn->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE status = 'In Progress'");
$stats['progress'] = $progress_query->fetch_assoc()['count'];

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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
<style>
/* Status styles */
.pending { color: #b91c1c; font-weight: bold; }
.progress { color: #d97706; font-weight: bold; }
.completed { color: #10b981; font-weight: bold; }

/* Cards */
.pending-card { background-color: #fef3c7; }
.progress-card { background-color: #dbeafe; }
.completed-card { background-color: #d1fae5; }

.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.card { padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.card h3 { margin-top: 0; font-size: 1rem; color: #4b5563; }
.card p { font-size: 2rem; font-weight: bold; margin: 10px 0 0 0; color: #1f2937; }

/* Action buttons for edit/delete */
.edit-btn, .delete-btn {
    padding: 6px 12px;
    font-size: 14px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    color: #fff;
    text-decoration: none;
}
.edit-btn { background-color: #2563eb; }
.edit-btn:hover { background-color: #1e40af; }
.delete-btn { background-color: #dc2626; }
.delete-btn:hover { background-color: #b91c1c; }
.action-buttons { display: flex; justify-content: center; gap: 5px; }

/* Modal styles */
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
.modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border-radius: 10px; width: 400px; position: relative; }
.modal .close { position: absolute; top: 10px; right: 15px; font-size: 28px; cursor: pointer; }
.modal label { display: block; margin-top: 10px; }
.modal input, .modal select { width: 100%; padding: 8px; margin-top: 5px; border-radius: 5px; border: 1px solid #ccc; }
.modal button { margin-top: 15px; width: 100%; padding: 10px; background-color: #10b981; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
.modal button:hover { background-color: var(--primary); }

/* Popup notification similar to room.php */
.message {
    padding: 10px;
    margin: 10px 0;
    border-radius: 6px;
    border: 1px solid transparent;
    text-align: center;
}
.success { background-color: #d4edda; color: #065f46; border-color: #c3e6cb; }
.error { background-color: #f8d7da; color: #b91c1c; border-color: #f5c6cb; }
.close {color:black;}
</style>
</head>
<body>

<div class="container">
<aside class="sidebar">
<h2 class="logo">Boarding House</h2>
<ul class="menu">
    <li><a href="dash.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
    <li><a href="room.php"><i class="fas fa-bed"></i> Rooms</a></li>
    <li><a href="tenant.php"><i class="fas fa-users"></i> Tenants</a></li>
    <li><a href="payment.php"><i class="fas fa-hand-holding-dollar"></i> Payments</a></li>
    <li><a href="transaction.php"><i class="fas fa-list-alt"></i> Transaction Records</a></li>
    <li class="active"><a href="mainten.php"><i class="fas fa-tools"></i> Maintenance</a></li>
    <li><a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a></li>
    <li><a href="expenses.php"><i class="fas fa-file-invoice-dollar"></i> Expenses</a></li>
    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
    <li class="logout"><a href="logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a></li>
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

<!-- Popup Notification -->
<?php if ($message): ?>
<div class="message <?php echo $message_type; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<section class="cards">
    <div class="card pending-card"><h3>Pending Requests</h3><p><?php echo $stats['pending']; ?></p></div>
    <div class="card progress-card"><h3>In Progress</h3><p><?php echo $stats['progress']; ?></p></div>
    <div class="card completed-card"><h3>Completed</h3><p><?php echo $stats['resolved']; ?></p></div>
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
            <th>Action</th>
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
                ?>">
                    <?php echo $request['status'] == 'Resolved' ? 'Completed' : $request['status']; ?>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="edit-btn" onclick="editRequest(
                            <?php echo $request['request_id']; ?>,
                            '<?php echo addslashes($request['issue_description']); ?>',
                            '<?php echo $request['status']; ?>'
                        )">Edit</button>
                        <button class="delete-btn" onclick="confirmDelete(<?php echo $request['request_id']; ?>)">Delete</button>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="text-align: center;">No maintenance requests found</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
</section>
</main>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
<div class="modal-content">
    <span class="close" onclick="closeEditModal()">&times;</span>
    <h2>Edit Maintenance Request</h2>
    <form method="POST" action="" id="editForm">
        <input type="hidden" name="edit_request" value="1">
        <input type="hidden" name="request_id" id="edit_request_id">

        <label>Issue Description</label>
        <input type="text" name="issue_description" id="edit_issue_description" required>

        <label>Status</label>
        <select name="status" id="edit_status" required>
            <option value="Pending">Pending</option>
            <option value="In Progress">In Progress</option>
            <option value="Resolved">Resolved</option>
        </select>

        <button type="submit">Update Request</button>
    </form>
</div>
</div>

<script>
function editRequest(id, issue, status) {
    document.getElementById('edit_request_id').value = id;
    document.getElementById('edit_issue_description').value = issue;
    document.getElementById('edit_status').value = status;
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }

function confirmDelete(requestId) {
    if (confirm('Are you sure you want to delete this request?')) {
        window.location.href = 'mainten.php?delete_id=' + requestId;
    }
}

window.onclick = function(event) {
    if (event.target == document.getElementById('editModal')) closeEditModal();
}
</script>

</body>
</html>
