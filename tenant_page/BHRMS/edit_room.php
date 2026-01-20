<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $room_id = (int)$_POST['room_id'];
    $room_number = trim($_POST['room_number']);
    $room_type = trim($_POST['room_type']);
    $capacity = (int)$_POST['capacity'];
    $monthly_rent = (float)$_POST['monthly_rent'];
    $status = trim($_POST['status']);
    

    $check_sql = "SELECT room_id FROM rooms WHERE room_number = ? AND room_id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $room_number, $room_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        header("Location: room.php?message=" . urlencode("Room number already exists for another room!") . "&type=error");
        exit();
    }
    
    $sql = "UPDATE rooms SET room_number = ?, room_type = ?, capacity = ?, monthly_rent = ?, status = ? WHERE room_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssidsi", $room_number, $room_type, $capacity, $monthly_rent, $status, $room_id);
    
    if ($stmt->execute()) {
        header("Location: room.php?message=" . urlencode("Room updated successfully!") . "&type=success");
    } else {
        header("Location: room.php?message=" . urlencode("Error updating room!") . "&type=error");
    }
    exit();
}
?>