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

// Fetch current user data
$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT full_name, username, role FROM users WHERE user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    
    $update_sql = "UPDATE users SET full_name = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $full_name, $user_id);
    
    if ($update_stmt->execute()) {
        // Update session data
        $_SESSION['full_name'] = $full_name;
        $user_data['full_name'] = $full_name;
        
        $message = "Profile updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating profile: " . $conn->error;
        $message_type = "error";
    }
    $update_stmt->close();
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $verify_sql = "SELECT password FROM users WHERE user_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("i", $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $db_password = $verify_result->fetch_assoc()['password'];
    
    if (password_verify($current_password, $db_password)) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $pass_sql = "UPDATE users SET password = ? WHERE user_id = ?";
                $pass_stmt = $conn->prepare($pass_sql);
                $pass_stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($pass_stmt->execute()) {
                    $message = "Password updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating password!";
                    $message_type = "error";
                }
                $pass_stmt->close();
            } else {
                $message = "New password must be at least 6 characters!";
                $message_type = "error";
            }
        } else {
            $message = "New passwords do not match!";
            $message_type = "error";
        }
    } else {
        $message = "Current password is incorrect!";
        $message_type = "error";
    }
    $verify_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Boarding House Management System</title>
    <link rel="stylesheet" href="dash.css">
    <style>
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            padding: 20px;
            border-radius: 10px;
            background-color: #f8f9fa;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #1f2937;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
        }
        .input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        .input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .btn {
            background-color: #3b82f6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            width: 100%;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #2563eb;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #4b5563;
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
        .input[disabled] {
            background-color: #f3f4f6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<div class="container">
    <aside class="sidebar">
        <h2 class="logo">Boarding House</h2>
        <ul class="menu">
            <li><a href="dash.php">Dashboard</a></li>
            <li><a href="room.php">Rooms</a></li>
            <li><a href="tenant.php">Tenants</a></li>
            <li><a href="payment.php">Payments</a></li>
            <li><a href="mainten.php">Maintenance</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="expenses.php">Expenses</a></li>
            <li class="active"><a href="settings.php">Settings</a></li>
            <li class="logout"><a href="logout.php" style="color: inherit; text-decoration: none;">Logout</a></li>
        </ul>
    </aside>

    <main class="main">
        <header class="topbar">
            <h1>Settings</h1>
            <div class="profile">
                <?php echo htmlspecialchars($user_data['full_name'] ?? 'Admin'); ?>
                (<?php echo htmlspecialchars($user_data['role'] ?? 'Admin'); ?>)
            </div>
        </header>

        <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <section class="cards">
            <div class="card">
                <h3>Profile Settings</h3>
                <form method="POST" action="">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <label>Full Name</label>
                    <input type="text" class="input" name="full_name" 
                           value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" required>
                    
                    <label>Username</label>
                    <input type="text" class="input" value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>" disabled>
                    
                    <label>Role</label>
                    <input type="text" class="input" value="<?php echo htmlspecialchars($user_data['role'] ?? 'Staff'); ?>" disabled>
                    
                    <button type="submit" class="btn">Save Profile</button>
                </form>
            </div>

            <div class="card">
                <h3>Change Password</h3>
                <form method="POST" action="">
                    <input type="hidden" name="change_password" value="1">
                    
                    <label>Current Password</label>
                    <input type="password" class="input" name="current_password" required>
                    
                    <label>New Password</label>
                    <input type="password" class="input" name="new_password" minlength="6" required>
                    
                    <label>Confirm Password</label>
                    <input type="password" class="input" name="confirm_password" minlength="6" required>
                    
                    <button type="submit" class="btn">Update Password</button>
                </form>
            </div>

            <div class="card">
                <h3>System Settings</h3>
                <form>
                    <label>User ID</label>
                    <input type="text" class="input" value="<?php echo htmlspecialchars($user_id); ?>" disabled>
                    
                    <label>Account Type</label>
                    <input type="text" class="input" value="Boarding House Management System" disabled>
                    
                    <label>Version</label>
                    <input type="text" class="input" value="1.0.0" disabled>
                    
                    <button type="button" class="btn" onclick="alert('System settings feature coming soon!')">Save System Settings</button>
                </form>
            </div>
        </section>
    </main>
</div>

</body>
</html>