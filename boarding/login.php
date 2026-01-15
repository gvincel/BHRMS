<?php
session_start(); 
include "db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    $stmt = $conn->prepare("SELECT user_id, full_name, username, password, role FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
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
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="login.css" />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
    />
    <title>Login</title>
  </head>
  <body>
    <div class="container" id="container">
      <div class="form-container sign-up">
        <form action="signup.php" method="POST">
          <h1>Create Account</h1>
          <div class="social-icons">
            <a href="#" class="icon"><i class="fa-brands fa-google-plus-g"></i></a>
            <a href="#" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
            <a href="#" class="icon"><i class="fa-brands fa-github"></i></a>
            <a href="#" class="icon"><i class="fa-brands fa-linkedin"></i></a>
          </div>
          <span>or use your email for registration</span>
          <input type="text" name="name" placeholder="Name" required />
          <input type="email" name="email" placeholder="Email" required />
          <input type="password" name="password" placeholder="Password" required/>
          <button type="submit">Sign up</button>
        </form>
      </div>
      <div class="form-container sign-in">
        <form action="login.php" method="POST">
          <h1>Sign in</h1>
          <div class="social-icons">
            <a href="#" class="icon"><i class="fa-brands fa-google-plus-g"></i></a>
            <a href="#" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
            <a href="#" class="icon"><i class="fa-brands fa-github"></i></a>
            <a href="#" class="icon"><i class="fa-brands fa-linkedin"></i></a>
          </div>
          <span>or use your email password</span>
          <?php if ($error): ?>
          <div style="color: red; margin: 10px 0; text-align: center;">
              <?php echo htmlspecialchars($error); ?>
          </div>
          <?php endif; ?>
          <input type="text" name="username" placeholder="Username" required />
          <input type="password" name="password" placeholder="Password" required/>
          <a href="">Forget Your Password</a>
          <button type="submit">Sign In</button>
        </form>
      </div>
      <div class="toggle-container">
        <div class="toggle">
          <div class="toggle-panel toggle-left">
            <h1>Welcome Back!</h1>
            <p>Enter your personal details to use all of site features</p>
            <button class="hidden" id="login">Sign In</button>
          </div>
          <div class="toggle-panel toggle-right">
            <h1>BOARDING HOUSE RENTAL MANAGEMENT SYSTEM</h1>
          </div>
        </div>
      </div>
    </div>

    <script src="login.js"></script>
  </body>
</html>