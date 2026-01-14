<?php
session_start();
include "db.php";


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';


$expense_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;


$expense = null;
if ($expense_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM expenses WHERE expense_id = ?");
    $stmt->bind_param("i", $expense_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $expense = $result->fetch_assoc();
    $stmt->close();
    
    if (!$expense) {
        $message = "Expense not found!";
        $message_type = "error";
    }
} else {
    $message = "Invalid expense ID!";
    $message_type = "error";
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_expense'])) {
    $expense_date = $_POST['expense_date'];
    $expense_type = trim($_POST['expense_type']);
    $description = trim($_POST['description']);
    $amount = (float)$_POST['amount'];
    $status = $_POST['status'];
    
    $sql = "UPDATE expenses SET 
            expense_date = ?, 
            expense_type = ?, 
            description = ?, 
            amount = ?, 
            status = ? 
            WHERE expense_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssdsi", $expense_date, $expense_type, $description, $amount, $status, $expense_id);
    
    if ($stmt->execute()) {
        $message = "Expense updated successfully!";
        $message_type = "success";

        $expense['expense_date'] = $expense_date;
        $expense['expense_type'] = $expense_type;
        $expense['description'] = $description;
        $expense['amount'] = $amount;
        $expense['status'] = $status;
    } else {
        $message = "Error updating expense: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Expense | Boarding House Management System</title>
    <link rel="stylesheet" href="dash.css">
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .card {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .card h2 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #1f2937;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4b5563;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .btn-primary {
            background-color: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background-color: #2563eb;
        }
        .btn-secondary {
            background-color: #6b7280;
            color: white;
            margin-left: 10px;
        }
        .btn-secondary:hover {
            background-color: #4b5563;
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
        .info {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        .action-buttons {
            display: flex;
            justify-content: flex-start;
            margin-top: 30px;
        }
        .expense-info {
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82f6;
        }
        .expense-info p {
            margin: 5px 0;
            color: #6b7280;
        }
        .expense-info strong {
            color: #1f2937;
        }
    </style>
</head>
<body>

<div class="container">
    <?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <?php if ($expense): ?>
    <div class="card">
        <h2>Edit Expense</h2>
        
        <div class="expense-info">
            <p><strong>Expense ID:</strong> <?php echo $expense['expense_id']; ?></p>
            <p><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($expense['created_at'])); ?></p>
            <p><strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime($expense['updated_at'])); ?></p>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="update_expense" value="1">
            
            <div class="form-group">
                <label for="expense_date">Expense Date *</label>
                <input type="date" id="expense_date" name="expense_date" 
                       class="form-control" 
                       value="<?php echo htmlspecialchars($expense['expense_date']); ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label for="expense_type">Expense Type *</label>
                <select id="expense_type" name="expense_type" class="form-control" required>
                    <option value="">Select type</option>
                    <option value="Electricity" <?php echo ($expense['expense_type'] == 'Electricity') ? 'selected' : ''; ?>>Electricity</option>
                    <option value="Water" <?php echo ($expense['expense_type'] == 'Water') ? 'selected' : ''; ?>>Water</option>
                    <option value="Internet" <?php echo ($expense['expense_type'] == 'Internet') ? 'selected' : ''; ?>>Internet</option>
                    <option value="Maintenance" <?php echo ($expense['expense_type'] == 'Maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                    <option value="Repair" <?php echo ($expense['expense_type'] == 'Repair') ? 'selected' : ''; ?>>Repair</option>
                    <option value="Supplies" <?php echo ($expense['expense_type'] == 'Supplies') ? 'selected' : ''; ?>>Supplies</option>
                    <option value="Salary" <?php echo ($expense['expense_type'] == 'Salary') ? 'selected' : ''; ?>>Salary</option>
                    <option value="Taxes" <?php echo ($expense['expense_type'] == 'Taxes') ? 'selected' : ''; ?>>Taxes</option>
                    <option value="Other" <?php echo ($expense['expense_type'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" 
                          class="form-control" 
                          placeholder="Enter expense description"><?php echo htmlspecialchars($expense['description']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="amount">Amount (₱) *</label>
                <input type="number" id="amount" name="amount" 
                       class="form-control" 
                       step="0.01" 
                       min="0" 
                       placeholder="Enter amount" 
                       value="<?php echo htmlspecialchars($expense['amount']); ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label for="status">Status *</label>
                <select id="status" name="status" class="form-control" required>
                    <option value="Pending" <?php echo ($expense['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="Paid" <?php echo ($expense['status'] == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                </select>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-primary">Update Expense</button>
                <a href="expenses.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <?php elseif (!$expense && !$message): ?>
    <div class="message error">
        Expense not found. Please select a valid expense to edit.
    </div>
    <div style="text-align: center; margin-top: 20px;">
        <a href="expenses.php" class="btn btn-primary">Back to Expenses</a>
    </div>
    <?php endif; ?>
</div>
<script>
let formChanged = false;
const form = document.querySelector('form');
const originalData = new FormData(form);

form.addEventListener('input', () => {
    formChanged = true;
});

window.addEventListener('beforeunload', (e) => {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
    }
});


form.addEventListener('submit', () => {
    formChanged = false;
});
</script>

</body>
</html>