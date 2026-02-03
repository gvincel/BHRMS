<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$tab = $_GET['tab'] ?? 'payments';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Set headers for Excel file
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=transactions_".$tab."_".date('Y-m-d').".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "Transaction ID\tTenant/Description\tRoom/Category\tAmount\tDate\tStatus\tTransaction Type\n";
?>