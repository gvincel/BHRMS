<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ================= DASHBOARD DATA ================= */
$rooms_query = $conn->query("SELECT COUNT(*) as total_rooms FROM rooms");
$total_rooms = $rooms_query->fetch_assoc()['total_rooms'];

$occupied_query = $conn->query("SELECT COUNT(*) as occupied_rooms FROM rooms WHERE status='Occupied'");
$occupied_rooms = $occupied_query->fetch_assoc()['occupied_rooms'];

$income_query = $conn->query("
    SELECT COALESCE(SUM(monthly_rent),0) as monthly_income 
    FROM rooms WHERE status='Occupied'
");
$monthly_income = $income_query->fetch_assoc()['monthly_income'];

$pending_query = $conn->query("
    SELECT COUNT(*) as pending_payments 
    FROM payments 
    WHERE remarks LIKE '%pending%' OR remarks IS NULL
");
$pending_payments = $pending_query->fetch_assoc()['pending_payments'];

/* ================= RECENT PAYMENTS ================= */
$recent_payments_query = $conn->query("
    SELECT 
        t.tenant_id,
        t.full_name AS tenant_name,
        r.room_number,
        p.amount,
        CASE 
            WHEN p.remarks LIKE '%paid%' THEN 'Paid'
            ELSE 'Pending'
        END AS status
    FROM payments p
    LEFT JOIN tenants t ON p.tenant_id = t.tenant_id
    LEFT JOIN rooms r ON t.room_id = r.room_id
    ORDER BY p.payment_date DESC
    LIMIT 10
");

$recent_payments = [];
while ($row = $recent_payments_query->fetch_assoc()) {
    $recent_payments[] = $row;
}

/* ================= ADVERTISEMENT UPLOAD ================= */
$ad_message = "";
if (isset($_POST['upload_ad'])) {
    if (!empty($_FILES['ad_image']['name'])) {
        $target_dir = "uploads/ads/";
        $file_name = time() . "_" . basename($_FILES["ad_image"]["name"]);
        $target_file = $target_dir . $file_name;
        $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];

        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($_FILES["ad_image"]["tmp_name"], $target_file)) {
                $_SESSION['ad_image'] = $target_file;
                $ad_message = "Advertisement uploaded successfully!";
            } else {
                $ad_message = "Upload failed.";
            }
        } else {
            $ad_message = "Only JPG, PNG, GIF allowed.";
        }
    }
}

/* ================= SEND MESSAGE ================= */
$msg_feedback = "";
if (isset($_POST['send_message'])) {
    $tenant_id = $_POST['tenant_id'];
    $message = trim($_POST['message']);

    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO messages (tenant_id, message) VALUES (?, ?)");
        $stmt->bind_param("is", $tenant_id, $message);
        $stmt->execute();
        $msg_feedback = "Message sent successfully!";
    } else {
        $msg_feedback = "Message cannot be empty.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard</title>
<link rel="stylesheet" href="dash.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
<div class="container">

<!-- ================= SIDEBAR ================= -->
<aside class="sidebar">
    <h2 class="logo">Boarding House</h2>
    <ul class="menu">
        <li class="active"><a href="dash.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li><a href="room.php"><i class="fas fa-bed"></i> Rooms</a></li>
        <li><a href="tenant.php"><i class="fas fa-users"></i> Tenants</a></li>
        <li><a href="payment.php"><i class="fas fa-hand-holding-dollar"></i> Payments</a></li>
        <li><a href="mainten.php"><i class="fas fa-tools"></i> Maintenance</a></li>
        <li><a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a></li>
        <li><a href="expenses.php"><i class="fas fa-receipt"></i> Expenses</a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        <li class="logout"><a href="logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</aside>

<!-- ================= MAIN ================= -->
<main class="main">

<header class="topbar">
    <h1>Dashboard</h1>
    <button class="add-btn" onclick="openAdModal()">
        <i class="fas fa-bullhorn"></i> Advertisement
    </button>
    <div class="profile">
        <?php echo $_SESSION['full_name'] ?? 'Admin'; ?>
    </div>
</header>

<!-- ================= CARDS ================= -->
<section class="cards">
    <div class="card"><h3>Total Rooms</h3><p><?php echo $total_rooms; ?></p></div>
    <div class="card"><h3>Occupied Rooms</h3><p><?php echo $occupied_rooms; ?></p></div>
    <div class="card"><h3>Monthly Income</h3><p>₱<?php echo number_format($monthly_income,2); ?></p></div>
    <div class="card"><h3>Pending Payments</h3><p><?php echo $pending_payments; ?></p></div>
</section>

<!-- ================= ADVERTISEMENT DISPLAY ================= -->
<?php if (!empty($_SESSION['ad_image'])): ?>
<section class="ad-section">
    <h2>Advertisement</h2>
    <img src="<?php echo $_SESSION['ad_image']; ?>">
</section>
<?php endif; ?>

<!-- ================= RECENT PAYMENTS ================= -->
<section class="table-section">
<h2>Recent Payments</h2>
<table>
<thead>
<tr>
<th>Tenant</th>
<th>Room</th>
<th>Amount</th>
<th>Status</th>
<th>Message</th>
</tr>
</thead>
<tbody>
<?php if (count($recent_payments) > 0): ?>
<?php foreach ($recent_payments as $p): ?>
<tr>
<td><?php echo htmlspecialchars($p['tenant_name']); ?></td>
<td><?php echo htmlspecialchars($p['room_number']); ?></td>
<td>₱<?php echo number_format($p['amount'],2); ?></td>
<td class="<?php echo strtolower($p['status']); ?>">
<?php echo $p['status']; ?>
</td>
<td>
<button class="msg-btn"
onclick="openMsgModal(<?php echo $p['tenant_id']; ?>,'<?php echo htmlspecialchars($p['tenant_name']); ?>')">
<i class="fas fa-envelope"></i>
</button>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="5">No records found</td></tr>
<?php endif; ?>
</tbody>
</table>
</section>

</main>
</div>

<!-- ================= AD MODAL ================= -->
<div id="adModal" class="modal">
<div class="modal-content">
<span class="close" onclick="closeAdModal()">&times;</span>
<h2>Upload Advertisement</h2>

<?php if ($ad_message): ?>
<p class="msg"><?php echo $ad_message; ?></p>
<?php endif; ?>

<div class="cntr">
<form method="POST" action enctype="multipart/form-data">
<input type="file" name="ad_image" required>
<button type="submit" name="upload_ad">Upload</button>
</div>

</form>
</div>
</div>

<!-- ================= MESSAGE MODAL ================= -->
<div id="msgModal" class="modal">
<div class="modal-content">
<span class="close" onclick="closeMsgModal()">&times;</span>

<h2>Send Message</h2>

<?php if ($msg_feedback): ?>
<p class="msg"><?php echo $msg_feedback; ?></p>
<?php endif; ?>

<form method="POST">
<input type="hidden" name="tenant_id" id="tenant_id">

<label>To</label>
<input type="text" id="tenant_name" disabled>

<textarea name="message" placeholder="Type your message..." required></textarea>

<button type="submit" name="send_message">Send</button>
</form>
</div>
</div>

<script>
function openAdModal(){
    document.getElementById("adModal").style.display="flex";
}
function closeAdModal(){
    document.getElementById("adModal").style.display="none";
}

function openMsgModal(id,name){
    document.getElementById("tenant_id").value=id;
    document.getElementById("tenant_name").value=name;
    document.getElementById("msgModal").style.display="flex";
}
function closeMsgModal(){
    document.getElementById("msgModal").style.display="none";
}
</script>

</body>
</html>