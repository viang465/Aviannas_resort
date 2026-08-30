<?php
session_start();
require_once '../../conn.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$booking_id     = intval($_POST['booking_id'] ?? 0);
$payment_status = $_POST['payment_status'] ?? 'Pending';
$redirect       = $_POST['redirect'] ?? 'index.php';

// Whitelist the allowed values
if (!in_array($payment_status, ['Paid', 'Partial', 'Pending'], true)) {
    $payment_status = 'Pending';
}

// Only allow redirecting back within this app's own pages (avoid open redirect)
if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+(\?[a-zA-Z0-9_=&%\.\-]*)?$/', $redirect)) {
    $redirect = 'index.php';
}

if ($booking_id > 0) {
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = ? WHERE id = ?");
    $stmt->bind_param("si", $payment_status, $booking_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: " . $redirect);
exit;