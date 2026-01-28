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
// UPDATE PAYMENT
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {

    $payment_id     = (int)$_POST['payment_id'];
    $amount         = (float)$_POST['amount'];
    $payment_date   = $_POST['payment_date'];
    $payment_method = trim($_POST['payment_method']);
    $status         = trim($_POST['status']); // updated

    $sql = "UPDATE payments 
            SET amount = ?, payment_date = ?, payment_method = ?, remarks = ?
            WHERE payment_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dsssi", $amount, $payment_date, $payment_method, $status, $payment_id);

    if ($stmt->execute()) {
        $message = "Payment updated successfully!";
        $message_type = "success";
    } else {
        $message = "Failed to update payment!";
        $message_type = "error";
    }
    $stmt->close();
}

// ==========================
// DELETE PAYMENT
// ==========================
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM payments WHERE payment_id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $message = "Payment deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting payment!";
        $message_type = "error";
    }
    $stmt->close();
}

// ==========================
// FETCH PAYMENTS
// ==========================
$payments_query = $conn->query("
    SELECT 
        p.payment_id,
        t.full_name as tenant_name,
        r.room_number,
        p.amount,
        p.payment_date,
        p.payment_method,
        p.remarks AS status,
        CASE 
            WHEN p.remarks LIKE '%Paid%' THEN 'Paid'
            WHEN p.remarks LIKE '%Pending%' OR p.remarks IS NULL THEN 'Pending'
            ELSE 'Pending'
        END as display_status
    FROM payments p
    LEFT JOIN tenants t ON p.tenant_id = t.tenant_id
    LEFT JOIN rooms r ON t.room_id = r.room_id
    ORDER BY p.payment_date DESC, p.payment_id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments Management</title>
<link rel="stylesheet" href="dash.css">

<style>
.paid { color: #10b981; font-weight: bold; }
.pending { color: #b91c1c; font-weight: bold; }

/* ACTION BUTTONS */
.edit-btn, .delete-btn {
    padding: 6px 12px;
    font-size: 14px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    color: #fff;
}
.edit-btn { background-color: #2563eb; }
.edit-btn:hover { background-color: #1e40af; }
.delete-btn { background-color: #dc2626; }
.delete-btn:hover { background-color: #b91c1c; }

.action-buttons {
    display: flex;
    justify-content: center;
    gap: 5px;
}

.message {
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 6px;
    text-align: center;
}
.success { background: #d4edda; color: #065f46; }
.error { background: #f8d7da; color: #b91c1c; }

/* MODAL STYLING */
.modal {
    display: none;
    position: fixed;
    z-index: 999;
    padding-top: 60px;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: auto;
    padding: 20px;
    border-radius: 10px;
    width: 400px;
}

.modal-content input, .modal-content select, .modal-content button {
    width: 100%;
    margin: 6px 0;
    padding: 8px;
    border-radius: 6px;
    border: 1px solid #ccc;
    box-sizing: border-box;
}

.modal-content button {
    background-color: #2563eb;
    color: #fff;
    border: none;
    cursor: pointer;
}

.modal-content button:hover {
    background-color: #1e40af;
}

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
    <li><a href="tenant.php"><i class="fas fa-users"></i> Tenants</a></li>
    <li class="active"><a href="payment.php"><i class="fas fa-hand-holding-dollar"></i> Payments</a></li>
    <li><a href="transaction.php"><i class="fas fa-list-alt"></i> Transaction Records</a></li>
    <li><a href="mainten.php"><i class="fas fa-tools"></i> Maintenance</a></li>
    <li><a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a></li>
    <li><a href="expenses.php"><i class="fas fa-receipt"></i> Expenses</a></li>
    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
    <li class="logout"><a href="logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a></li>
</ul>
</aside>

<main class="main">
<header class="topbar">
<h1>Payments</h1>
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

<section class="table-section">
<h2>Payment Records</h2>

<table>
<thead>
<tr>
    <th>Tenant Name</th>
    <th>Room</th>
    <th>Amount</th>
    <th>Payment Date</th>
    <th>Payment Method</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>
<tbody>

<?php if ($payments_query->num_rows > 0): ?>
<?php while ($payment = $payments_query->fetch_assoc()): ?>
<tr>
<td><?php echo htmlspecialchars($payment['tenant_name'] ?? 'N/A'); ?></td>
<td><?php echo htmlspecialchars($payment['room_number'] ?? 'N/A'); ?></td>
<td>₱<?php echo number_format($payment['amount'], 2); ?></td>
<td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
<td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
<td class="<?php echo strtolower($payment['display_status']); ?>">
<?php echo $payment['display_status']; ?>
</td>

<td>
<div class="action-buttons">
<button class="edit-btn" onclick="editPayment(
<?php echo $payment['payment_id']; ?>,
<?php echo $payment['amount']; ?>,
'<?php echo $payment['payment_date']; ?>',
'<?php echo htmlspecialchars($payment['payment_method']); ?>',
'<?php echo $payment['status']; ?>'
)">Edit</button>

<button class="delete-btn" onclick="confirmDelete(<?php echo $payment['payment_id']; ?>)">
Delete
</button>
</div>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="7" style="text-align:center;">No payment records found</td>
</tr>
<?php endif; ?>

</tbody>
</table>
</section>
</main>
</div>

<!-- EDIT PAYMENT MODAL -->
<div id="editModal" class="modal">
<div class="modal-content">
<span class="close" onclick="closeEditModal()">&times;</span>
<h2>Edit Payment</h2>

<form method="POST">
<input type="hidden" name="update_payment" value="1">
<input type="hidden" name="payment_id" id="edit_payment_id">

<label>Amount</label>
<input type="number" name="amount" id="edit_amount" step="0.01" required>

<label>Payment Date</label>
<input type="date" name="payment_date" id="edit_date" required>

<label>Payment Method</label>
<input type="text" name="payment_method" id="edit_method" required>

<label>Status</label>
<select name="status" id="edit_status" required>
    <option value="Paid">Paid</option>
    <option value="Pending">Pending</option>
</select>

<button type="submit">Update Payment</button>
</form>
</div>
</div>

<script>
function editPayment(id, amount, date, method, status) {
    document.getElementById('edit_payment_id').value = id;
    document.getElementById('edit_amount').value = amount;
    document.getElementById('edit_date').value = date;
    document.getElementById('edit_method').value = method;
    document.getElementById('edit_status').value = status;
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this payment?')) {
        window.location.href = 'payment.php?delete_id=' + id;
    }
}

window.onclick = function(e) {
    if (e.target === document.getElementById('editModal')) {
        closeEditModal();
    }
}
</script>

</body>
</html>
