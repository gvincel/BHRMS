<?php
session_start();
include "db.php";

$error = "";
$login_type = $_POST['login_type'] ?? 'admin';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* ================= ADMIN LOGIN ================= */
    if ($login_type === 'admin') {

        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        $stmt = $conn->prepare(
            "SELECT user_id, full_name, username, password, role
             FROM users
             WHERE username = ?"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['user_type'] = 'admin';

                header("Location: dash.php");
                exit();
            } else {
                $error = "Incorrect password!";
            }
        } else {
            $error = "Account not found!";
        }
        $stmt->close();
    }

    /* ================= TENANT LOGIN ================= */
    if ($login_type === 'tenant') {

        $tenant_id = (int) $_POST['tenant_id'];

        if ($tenant_id > 0) {
            $stmt = $conn->prepare(
                "SELECT tenant_id, full_name, email, contact_number, room_id
                 FROM tenants
                 WHERE tenant_id = ? AND status = 'Active'"
            );
            $stmt->bind_param("i", $tenant_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $tenant = $result->fetch_assoc();

                $_SESSION['tenant_id']      = $tenant['tenant_id'];
                $_SESSION['tenant_name']    = $tenant['full_name'];
                $_SESSION['tenant_email']   = $tenant['email'];
                $_SESSION['tenant_contact'] = $tenant['contact_number'];
                $_SESSION['tenant_room_id'] = $tenant['room_id'];
                $_SESSION['user_type']      = 'tenant';

                header("Location: tdash.php");
                exit();
            } else {
                $error = "Invalid or inactive Tenant ID!";
            }
            $stmt->close();
        } else {
            $error = "Please enter a valid Tenant ID!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Boarding House Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="login.css">
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

<div class="container" id="container">

    <!-- ========== TENANT FORM ========== -->
    <div class="form-container sign-up">
        <form method="POST">
            <h1>Tenant Access</h1>

            <div class="social-icons">
                <a href="#" class="icon"><i class="fa-brands fa-google-plus-g"></i></a>
                <a href="#" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" class="icon"><i class="fa-brands fa-github"></i></a>
                <a href="#" class="icon"><i class="fa-brands fa-linkedin"></i></a>
            </div>

            <span>Use your Tenant ID</span>

            <?php if ($error && $login_type === 'tenant'): ?>
            <div style="color:red;text-align:center;margin:10px 0;">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <input type="hidden" name="login_type" value="tenant">
            <input type="number" name="tenant_id" placeholder="Tenant ID" required>

            <button type="submit">Enter Portal</button>
        </form>
    </div>

    <!-- ========== ADMIN FORM ========== -->
    <div class="form-container sign-in">
        <form method="POST">
            <h1>Admin Login</h1>

            <div class="social-icons">
                <a href="#" class="icon"><i class="fa-brands fa-google-plus-g"></i></a>
                <a href="#" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" class="icon"><i class="fa-brands fa-github"></i></a>
                <a href="#" class="icon"><i class="fa-brands fa-linkedin"></i></a>
            </div>

            <span>Use your username & password</span>

            <?php if ($error && $login_type === 'admin'): ?>
            <div style="color:red;text-align:center;margin:10px 0;">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <input type="hidden" name="login_type" value="admin">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>

            <a href="#">Forgot your password?</a>
            <button type="submit">Sign In</button>
        </form>
    </div>

    <!-- ========== TOGGLE ========== -->
    <div class="toggle-container">
        <div class="toggle">

            <div class="toggle-panel toggle-left">
                <h1>Tenant Portal</h1>
                <p>Login using your Tenant ID</p>
                <button class="hidden" id="login">Admin Login</button>
            </div>

            <div class="toggle-panel toggle-right">
                <h1>Admin Panel</h1>
                <p>Manage tenants and rooms</p>
                <button class="hidden" id="register">Tenant Access</button>
            </div>

        </div>
    </div>

</div>

<script src="login.js"></script>
</body>
</html>
