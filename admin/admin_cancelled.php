<?php
session_start();
include "../conn.php"; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit("Unauthorized Access");
}

$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $id = intval($_POST['booking_id']);

    // Fetch guest details before archiving
    $guest = null;
    $lookup = $conn->prepare("SELECT name, email, contact, address, room_type, checkin_date, checkout_date FROM bookings WHERE id = ?");
    $lookup->bind_param("i", $id);
    $lookup->execute();
    $guest = $lookup->get_result()->fetch_assoc();
    $lookup->close();

    // Use transaction to ensure safe copy and move
    $conn->begin_transaction();

    try {
        $copySql = "INSERT INTO deleted_bookings (name, email, contact, address, room_type, checkin_date, checkout_date, deletion_date) 
                    SELECT name, email, contact, address, room_type, checkin_date, checkout_date, NOW() 
                    FROM bookings WHERE id = ?";
        
        $stmt = $conn->prepare($copySql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $delStmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
        $delStmt->bind_param("i", $id);
        $delStmt->execute();
        $delStmt->close();

        $conn->commit();

        // Notify guest
        if ($guest && !empty($guest['email'])) {
            $to = $guest['email'];
            $subject = "Reservation Cancelled - Avianna's Inland Resort";

            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: reservations@aviannasresort.com\r\n";

            $message = "
            <html>
            <body style='font-family: Arial, sans-serif; color:#2d3748;'>
                <h2 style='color:#c53030;'>Reservation Cancelled</h2>
                <p>Dear " . htmlspecialchars($guest['name']) . ",</p>
                <p>This is to confirm that your reservation has been cancelled and archived. Details below:</p>
                <table style='border-collapse: collapse; margin-top:10px;'>
                    <tr><td style='padding:4px 10px;font-weight:bold;'>Room Type:</td><td style='padding:4px 10px;'>" . htmlspecialchars($guest['room_type']) . "</td></tr>
                    <tr><td style='padding:4px 10px;font-weight:bold;'>Check-in:</td><td style='padding:4px 10px;'>" . htmlspecialchars($guest['checkin_date']) . "</td></tr>
                    <tr><td style='padding:4px 10px;font-weight:bold;'>Check-out:</td><td style='padding:4px 10px;'>" . htmlspecialchars($guest['checkout_date']) . "</td></tr>
                </table>
                <p style='margin-top:15px;'>If this was a mistake or you'd like to rebook, please contact us directly.</p>
            </body>
            </html>";

            @mail($to, $subject, $message, $headers);
        }

        header("Location: admin.php?cancel=success");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Archiving Failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Processing Cancellation - Avianna's</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .process-card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); text-align: center; max-width: 450px; width: 90%; }
        .loader { border: 4px solid #f3f3f3; border-top: 4px solid #dc3545; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .btn-return { background-color: #1e4d40; color: white; border-radius: 10px; padding: 10px 25px; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>

<div class="process-card">
    <?php if ($error_message): ?>
        <div class="text-danger mb-4">
            <h4>Processing Error</h4>
            <p class="text-muted"><?php echo htmlspecialchars($error_message); ?></p>
        </div>
        <a href="admin.php" class="btn-return">Return to Dashboard</a>
    <?php else: ?>
        <div class="loader"></div>
        <h4>Archiving Booking</h4>
        <p class="text-muted small">Moving record to cancellation history...</p>
        <script>setTimeout(function(){ window.location.href = 'admin.php'; }, 2000);</script>
    <?php endif; ?>
</div>

</body>
</html>