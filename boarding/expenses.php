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

// Handle Add Expense
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

// Handle Delete Expense
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $delete_sql = "DELETE FROM expenses WHERE expense_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $delete_id);
    
    if ($delete_stmt->execute()) {
        $message = "Expense deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting expense!";
        $message_type = "error";
    }
    $delete_stmt->close();
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .add-btn {
            background-color: #10b981;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .add-btn:hover {
            background-color: #059669;
            color: white;
        }
        .edit-btn {
            padding: 6px 12px;
            background-color: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
            margin-right: 5px;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .edit-btn:hover {
            background-color: #1e40af;
            color: white;
        }
        .delete-btn {
            padding: 6px 12px;
            background-color: #dc2626;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .delete-btn:hover {
            background-color: #b91c1c;
            color: white;
        }
        .expense-paid {
            color: #10b981;
            font-weight: bold;
        }
        .expense-pending {
            color: #d97706;
            font-weight: bold;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            overflow-y: auto; /* <-- allow scrolling if content too tall */
            padding: 20px; /* <-- ensures spacing on smaller screens */
            box-sizing: border-box;
        }

.modal-content {
    background-color: #fff;
    margin: 50px auto; /* margin-top now smaller and fixed for scroll */
    padding: 20px;
    border-radius: 10px;
    width: 400px;
    max-width: 100%; /* responsive on mobile */
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    color: black;
}

        .modal-content h2 {
            margin-bottom: 15px;
        }
        .modal-content form label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }
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
        .modal-content form button:hover {
            background-color: #059669;
        }
        .close {
            float: right;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
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
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .action-buttons a {
            text-decoration: none;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
</head>
<body>

<div class="container">
    <aside class="sidebar">
    <h2 class="logo">Boarding House</h2>
    <ul class="menu">
        <li>
            <a href="dash.php">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="room.php">
                <i class="fas fa-bed"></i> Rooms
            </a>
        </li>
        <li>
            <a href="tenant.php">
                <i class="fas fa-users"></i> Tenants
            </a>
        </li>
        <li>
            <a href="payment.php">
                <i class="fas fa-hand-holding-dollar"></i> Payments
            </a>
        </li>
        <li>
            <a href="mainten.php">
                <i class="fas fa-tools"></i> Maintenance
            </a>
        </li>
        <li>
            <a href="reports.php">
                <i class="fas fa-file-alt"></i> Reports
            </a>
        </li>
        <li class="active">
            <a href="expenses.php">
                <i class="fas fa-receipt"></i> Expenses
            </a>
        </li>
        <li>
            <a href="settings.php">
                <i class="fas fa-cog"></i> Settings
            </a>
        </li>
        <li class="logout">
            <a href="logout.php">
                <i class="fas fa-right-from-bracket"></i> Logout
            </a>
        </li>
    </ul>
</aside>

    <!-- Main Content -->
    <main class="main">
        <!-- Top Bar -->
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

        <!-- Expense Table -->
        <section class="table-section">
            <!-- Title + Add Button -->
            <div class="table-header">
                <h2>Expense Records</h2>
                <button class="add-btn" onclick="openModal()">+ Add Expense</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Expense Type</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
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
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_expense.php?id=<?php echo $expense['expense_id']; ?>" class="edit-btn">Edit</a>
                                    <a href="#" class="delete-btn" onclick="confirmDelete(<?php echo $expense['expense_id']; ?>)">Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No expense records found</td>
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

function confirmDelete(expenseId) {
    if (confirm('Are you sure you want to delete this expense?')) {
        window.location.href = 'expenses.php?delete_id=' + expenseId;
    }
    return false;
}

window.onclick = function(event) {
    if (event.target == document.getElementById('expenseModal')) {
        closeModal();
    }
}
</script>

</body>
</html>