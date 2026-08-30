<?php
session_start();
include "../conn.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

if ($id <= 0) {
    header("Location: approve.php");
    exit();
}

// Fetch the booking
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    header("Location: approve.php?emailerr=notfound");
    exit();
}

// Only bookings that have actually been approved (or moved further along
// the pipeline) can have a confirmation email sent — a booking still
// sitting as 'Pending' has nothing confirmed to email about yet.
$confirmedStatuses = ['Approved', 'Checked In', 'Checked Out'];
if (!in_array($booking['status'], $confirmedStatuses, true)) {
    header("Location: approve.php?emailerr=notapproved");
    exit();
}

$to = $booking['email'];
if (empty($to)) {
    header("Location: approve.php?emailerr=noemail&id=" . $id);
    exit();
}

$paymentStatus = $booking['payment_status'] ?? 'Pending';

$payLine = "Payment is still <strong>pending</strong>. Please settle it upon check-in or contact us to arrange payment in advance.";
if ($paymentStatus === 'Paid') {
    $payLine = "We have you down as <strong>fully paid</strong>. Thank you!";
} elseif ($paymentStatus === 'Partial') {
    $payLine = "We have received a <strong>partial payment</strong> for this booking. The remaining balance is due upon check-in.";
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$baseUrl  = rtrim($protocol . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$confirmationLink = $baseUrl . '/confirmation.php?id=' . $id;

$subject = "Reservation Confirmation - Avianna's Inland Resort";
$htmlBody = "
<html>
<body style='font-family: Arial, sans-serif;'>
    <h2>Reservation Confirmed!</h2>
    <p>Dear " . htmlspecialchars($booking['name']) . ",</p>
    <p>This is a confirmation of your booking for a <strong>" . htmlspecialchars($booking['room_type']) . "</strong>
       from <strong>" . htmlspecialchars($booking['checkin_date']) . "</strong> to
       <strong>" . htmlspecialchars($booking['checkout_date']) . "</strong>.</p>
    <p>Status: <strong>" . htmlspecialchars($booking['status']) . "</strong></p>
    <p>" . $payLine . "</p>
    <p>You can view or print your booking confirmation here: <a href='" . $confirmationLink . "'>View Confirmation</a></p>
    <p>We look forward to welcoming you to Avianna's Inland Resort!</p>
</body>
</html>";
$plainBody = "Reservation Confirmation - Avianna's Inland Resort. Dear {$booking['name']}, this confirms your booking for {$booking['room_type']} from {$booking['checkin_date']} to {$booking['checkout_date']}. Status: {$booking['status']}. Payment status: {$paymentStatus}. View: {$confirmationLink}";

$sent = false;

// Prefer the project's PHPMailer/SMTP setup when it's available (same
// approach as book.php) since it's far more reliable than PHP's mail().
$root = dirname(__DIR__);
if (file_exists($root . '/PHPMailer.php') && file_exists($root . '/SMTP.php') && file_exists($root . '/Exception.php')) {
    require_once $root . '/PHPMailer.php';
    require_once $root . '/Exception.php';
    require_once $root . '/SMTP.php';
    if (file_exists($root . '/mail_config.php')) {
        require_once $root . '/mail_config.php';
    }

    if (!defined('MAIL_HOST'))      define('MAIL_HOST', 'smtp.gmail.com');
    if (!defined('MAIL_USERNAME'))  define('MAIL_USERNAME', 'your-email@gmail.com');
    if (!defined('MAIL_PASSWORD'))  define('MAIL_PASSWORD', 'your-app-password');
    if (!defined('MAIL_PORT'))      define('MAIL_PORT', 587);
    if (!defined('MAIL_FROM'))      define('MAIL_FROM', 'your-email@gmail.com');
    if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', "Avianna's Inland Resort");

    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;
            $mail->Timeout    = 15;
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to, $booking['name']);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $plainBody;
            $mail->send();
            $sent = true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log("Manual confirmation email failed (PHPMailer): " . $e->getMessage());
        }
    }
}

// Fallback to PHP's built-in mail() if PHPMailer isn't set up on this server
if (!$sent) {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: reservations@aviannasresort.com\r\n";
    $sent = @mail($to, $subject, $htmlBody, $headers);
}

if ($sent) {
    header("Location: approve.php?emailed=success");
} else {
    header("Location: approve.php?emailerr=sendfail&id=" . $id);
}
exit();