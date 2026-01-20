<?php
include "db.php";

$email = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE email='$email'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) == 1) {
    $user = mysqli_fetch_assoc($result);

    if (password_verify($password, $user['password'])) {
        echo "Login successful! Welcome " . $user['name'];
    } else {
        echo "Incorrect password!";
    }
} else {
    echo "User not found!";
}
?>
Pinned
<?php
include "db.php";

$name = $_POST['name'];
$email = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);


$check = "SELECT * FROM users WHERE email='$email'";
$result = mysqli_query($conn, $check);

if (mysqli_num_rows($result) > 0) {
    echo "Email already exists!";
    exit();
}


$sql = "INSERT INTO users (name, email, password)
        VALUES ('$name', '$email', '$password')";

if (mysqli_query($conn, $sql)) {
    echo "Account created successfully!";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>