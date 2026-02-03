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
// UPDATE PAYMENT STATUS (AJAX endpoint)
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $payment_id = (int)$_POST['payment_id'];
    $new_status = trim($_POST['status']);
    
    // Update payment remarks
    $sql = "UPDATE payments SET remarks = ? WHERE payment_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_status, $payment_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating status']);
    }
    $stmt->close();
    exit();
}

// ==========================
// ADD PAYMENT
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $tenant_id = (int)$_POST['tenant_id'];
    $amount = (float)$_POST['amount'];
    $payment_date = $_POST['payment_date'];
    $payment_method = trim($_POST['payment_method']);
    $status = trim($_POST['status']);
    
    // Insert new payment
    $sql = "INSERT INTO payments (tenant_id, amount, payment_date, payment_method, remarks) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idsss", $tenant_id, $amount, $payment_date, $payment_method, $status);
    
    if ($stmt->execute()) {
        $message = "Payment added successfully!";
        $message_type = "success";
        header("Location: payment.php?message=" . urlencode($message) . "&type=" . $message_type);
        exit();
    } else {
        $message = "Error adding payment: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Check for message from URL
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $message_type = $_GET['type'] ?? 'info';
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
        CASE 
            WHEN p.remarks LIKE '%paid%' THEN 'Paid'
            WHEN p.remarks LIKE '%partial%' THEN 'Partial'
            WHEN p.remarks IS NULL THEN 'Partial'
            ELSE 'Partial'
        END as status
    FROM payments p
    LEFT JOIN tenants t ON p.tenant_id = t.tenant_id
    LEFT JOIN rooms r ON t.room_id = r.room_id
    ORDER BY p.payment_date DESC, p.payment_id DESC
");

// Fetch tenants for the add payment form
$tenants_query = $conn->query("
    SELECT t.tenant_id, t.full_name, r.room_number 
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
<title>Payments Management</title>
<link rel="stylesheet" href="dash.css">

<style>
.paid { color: #10b981; font-weight: bold; }
.partial { color: #f59e0b; font-weight: bold; }

/* STATUS BUTTONS */
.status-btn {
    padding: 6px 12px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: bold;
    font-size: 14px;
    transition: all 0.3s ease;
    min-width: 80px;
}

.status-paid {
    background-color: #10b981;
    color: white;
}

.status-partial {
    background-color: #f59e0b;
    color: white;
}

.status-btn:hover {
    opacity: 0.9;
    transform: scale(1.05);
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

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: #000;
    text-decoration: none;
    cursor: pointer;
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
    <li><a href="expenses.php"><i class="fas fa-file-invoice-dollar"></i> Expenses</a></li>
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

<div class="add-btn-container">
    <button class="add-btn" onclick="openModal()">+ Add Payment</button>
</div>

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
<td>
    <button class="status-btn status-<?php echo strtolower($payment['status']); ?>" 
            onclick="toggleStatus(this, <?php echo $payment['payment_id']; ?>)">
        <?php echo $payment['status']; ?>
    </button>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="6" style="text-align:center;">No payment records found</td>
</tr>
<?php endif; ?>

</tbody>
</table>
</section>
</main>
</div>

<!-- ADD PAYMENT MODAL -->
<div id="paymentModal" class="modal">
<div class="modal-content">
<span class="close" onclick="closeModal()">&times;</span>
<h2>Add New Payment</h2>

<form method="POST">
<input type="hidden" name="add_payment" value="1">

<label>Tenant</label>
<select name="tenant_id" id="tenant_id" required>
    <option value="">Select Tenant</option>
    <?php if ($tenants_query->num_rows > 0): ?>
        <?php while ($tenant = $tenants_query->fetch_assoc()): ?>
            <option value="<?php echo $tenant['tenant_id']; ?>">
                <?php echo htmlspecialchars($tenant['full_name']) . ' - Room ' . htmlspecialchars($tenant['room_number']); ?>
            </option>
        <?php endwhile; ?>
    <?php endif; ?>
</select>

<label>Amount</label>
<input type="number" name="amount" step="0.01" placeholder="Enter amount" required>

<label>Payment Date</label>
<input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">

<label>Payment Method</label>
<select name="payment_method" required>
    <option value="">Select Method</option>
    <option value="Cash">Cash</option>
</select>

<label>Status</label>
<select name="status" required>
    <option value="">Select Status</option>
    <option value="Partial">Partial</option>
    <option value="Paid">Paid</option>
</select>

<button type="submit">Add Payment</button>
</form>
</div>
</div>

<script>
function toggleStatus(button, paymentId) {
    // Get current status
    let currentStatus = button.textContent.trim();
    
    // Toggle between Partial and Paid only
    let newStatus = currentStatus === 'Paid' ? 'Partial' : 'Paid';
    
    // Show loading state
    const originalText = button.textContent;
    button.textContent = 'Updating...';
    button.disabled = true;
    
    // Send AJAX request to update status
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `update_status=1&payment_id=${paymentId}&status=${newStatus}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update button text
            button.textContent = newStatus;
            
            // Update button class
            button.className = 'status-btn status-' + newStatus.toLowerCase();
            
            // Show success message
            showMessage('Status updated successfully!', 'success');
        } else {
            button.textContent = originalText;
            showMessage('Failed to update status', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.textContent = originalText;
        showMessage('Network error. Please try again.', 'error');
    })
    .finally(() => {
        button.disabled = false;
    });
}

function showMessage(text, type) {
    // Remove existing messages
    const existingMsg = document.querySelector('.message');
    if (existingMsg) existingMsg.remove();
    
    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    messageDiv.textContent = text;
    
    // Insert message
    const header = document.querySelector('.topbar');
    header.parentNode.insertBefore(messageDiv, header.nextSibling);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        messageDiv.remove();
    }, 3000);
}

function openModal() {
    document.getElementById('paymentModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('paymentModal')) {
        closeModal();
    }
}
</script>

</body>
</html>