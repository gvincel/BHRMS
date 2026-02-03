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

// Handle Add Expense only
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    $expense_date = $_POST['expense_date'];
    $expense_type = trim($_POST['expense_type']);
    $description = trim($_POST['description']);
    $amount = (float)$_POST['amount'];
    $status = $_POST['status'];
    
    $sql = "INSERT INTO expenses (expense_date, expense_type, description, amount, status) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssds", $expense_date, $expense_type, $description, $amount, $status);
    
    if ($stmt->execute()) {
        $message = "Expense added successfully!";
        $message_type = "success";
        header("Location: expenses.php?message=" . urlencode($message) . "&type=" . $message_type);
        exit();
    } else {
        $message = "Error adding expense: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// Check for message from URL
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $message_type = $_GET['type'] ?? 'info';
}

// Fetch all expenses
$expenses_query = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses</title>
    <link rel="stylesheet" href="dash.css">
    <style>
        .table-header {
            margin-bottom: 20px;
        }
        .add-btn {
            text-decoration: none;
            display: inline-block;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
            background-color: #10b981;
            color: white;
            padding: 8px 16px;
            font-weight: bold;
            border: none;
        }
        .add-btn:hover { background-color: #059669; }
        .expense-paid { color: #10b981; font-weight: bold; }
        .expense-pending { color: #d97706; font-weight: bold; }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            overflow-y: auto;
            padding: 20px;
            box-sizing: border-box;
        }
        .modal-content {
            background-color: #fff;
            margin: 50px auto;
            padding: 20px;
            border-radius: 10px;
            width: 400px;
            max-width: 100%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            color: black;
        }
        .modal-content h2 { margin-bottom: 15px; }
        .modal-content form label { display: block; margin-top: 10px; font-weight: bold; }
        .modal-content form input,
        .modal-content form select,
        .modal-content form textarea {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
        .modal-content form button {
            margin-top: 15px;
            width: 100%;
            padding: 10px;
            background-color: #10b981;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        .modal-content form button:hover { background-color: #059669; }
        .close { float: right; font-size: 24px; font-weight: bold; cursor: pointer; }
        .message { padding: 12px; margin-bottom: 20px; border-radius: 6px; text-align: center; }
        .success { background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        
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
            <li><a href="payment.php"><i class="fas fa-hand-holding-dollar"></i> Payments</a></li>
            <li><a href="transaction.php"><i class="fas fa-list-alt"></i> Transaction Records</a></li>
            <li><a href="mainten.php"><i class="fas fa-tools"></i> Maintenance</a></li>
            <li><a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a></li>
            <li class="active"><a href="expenses.php"><i class="fas fa-file-invoice-dollar"></i> Expenses</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li class="logout"><a href="logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main">
        <header class="topbar">
            <h1>Expenses</h1>
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
        <div class="add-btn-container"> <button class="add-btn" onclick="openModal()">+ Add Expense</button></div>
        <section class="table-section">
            <div class="table-header">
                <h2>Expense Records</h2>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Expense Type</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($expenses_query->num_rows > 0): ?>
                        <?php while ($expense = $expenses_query->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($expense['expense_date'])); ?></td>
                            <td><?php echo htmlspecialchars($expense['expense_type']); ?></td>
                            <td><?php echo htmlspecialchars($expense['description']); ?></td>
                            <td>₱<?php echo number_format($expense['amount'], 2); ?></td>
                            <td class="expense-<?php echo strtolower($expense['status']); ?>">
                                <?php echo $expense['status']; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No expense records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<!-- Add Expense Modal -->
<div id="expenseModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Add New Expense</h2>
        <form method="POST" action="">
            <input type="hidden" name="add_expense" value="1">
            
            <label>Expense Date</label>
            <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
            
            <label>Expense Type</label>
            <select name="expense_type" required>
                <option value="">Select type</option>
                <option value="Electricity">Electricity</option>
                <option value="Water">Water</option>
                <option value="Internet">Internet</option>
                <option value="Maintenance">Maintenance</option>
                <option value="Repair">Repair</option>
                <option value="Supplies">Supplies</option>
                <option value="Salary">Salary</option>
                <option value="Taxes">Taxes</option>
                <option value="Other">Other</option>
            </select>
            
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Enter expense description"></textarea>
            
            <label>Amount (₱)</label>
            <input type="number" name="amount" step="0.01" min="0" placeholder="Enter amount" required>
            
            <label>Status</label>
            <select name="status" required>
                <option value="Pending">Pending</option>
                <option value="Paid">Paid</option>
            </select>
            
            <button type="submit">Add Expense</button>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('expenseModal').style.display = 'block';
}
function closeModal() {
    document.getElementById('expenseModal').style.display = 'none';
}
window.onclick = function(event) {
    if (event.target == document.getElementById('expenseModal')) closeModal();
}
</script>

</body>
</html>