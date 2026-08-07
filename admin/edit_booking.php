<?php
session_start();
include "../conn.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header("Location: admin.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Fetch guest details for the email
    $stmt = $conn->prepare("SELECT name, email, room_type, checkin_date, checkout_date, contact FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $guest = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($guest) {
        // 2. Update status to Approved
        $update = $conn->prepare("UPDATE bookings SET status='Approved' WHERE id=?");
        $update->bind_param("i", $id);

        if ($update->execute()) {
            $update->close();

            // 3. Automated Email Notification
            $to = $guest['email'];

            if (!empty($to)) {
                $subject = "Reservation Approved - Avianna's Inland Resort";

                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8\r\n";
                $headers .= "From: reservations@aviannasresort.com\r\n";

                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $baseUrl  = rtrim($protocol . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
                $confirmationLink = $baseUrl . '/confirmation.php?id=' . $id;

                $message = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2>Reservation Confirmed!</h2>
                    <p>Dear " . htmlspecialchars($guest['name']) . ",</p>
                    <p>Your booking for a <strong>" . htmlspecialchars($guest['room_type']) . "</strong> from <strong>" . htmlspecialchars($guest['checkin_date']) . "</strong> to <strong>" . htmlspecialchars($guest['checkout_date']) . "</strong> has been officially approved.</p>
                    <p>You can view or print your booking confirmation here: <a href='" . $confirmationLink . "'>View Confirmation</a></p>
                    <p>We look forward to welcoming you to Avianna's Inland Resort!</p>
                </body>
                </html>";

                @mail($to, $subject, $message, $headers);
            }

            header("Location: admin.php?approve=success");
            exit();
        }
        $update->close();
    }
}

// Fetch for display
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    header("Location: admin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Booking | Avianna's Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-green: #1e4d40;
            --accent-teal: #2c7a7b;
            --bg-light: #f4f7f6;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .approve-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.08);
            text-align: center;
            max-width: 460px;
            width: 90%;
        }
        .approve-card h2 { color: var(--primary-green); font-weight: 700; margin-bottom: 10px; }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f1f1;
            text-align: left;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #718096; font-weight: 500; }
        .detail-value { color: #2d3748; font-weight: 600; text-align: right; }
        .btn-confirm {
            background: var(--primary-green);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            margin-top: 20px;
        }
        .btn-confirm:hover { background: var(--accent-teal); color: white; }
        .btn-back { display: inline-block; margin-top: 15px; color: #718096; text-decoration: none; font-size: 0.9rem; }
        .btn-back:hover { color: var(--primary-green); }
    </style>
</head>
<body>

<div class="approve-card">
    <h2><i class="bi bi-check-circle"></i> Approve Booking</h2>
    <p class="text-muted">Confirming will notify the guest by email.</p>

    <div class="mt-3">
        <div class="detail-row"><span class="detail-label">Guest</span><span class="detail-value"><?php echo htmlspecialchars($booking['name']); ?></span></div>
        <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?php echo htmlspecialchars($booking['email']); ?></span></div>
        <div class="detail-row"><span class="detail-label">Contact</span><span class="detail-value"><?php echo htmlspecialchars($booking['contact']); ?></span></div>
        <div class="detail-row"><span class="detail-label">Room Type</span><span class="detail-value"><?php echo htmlspecialchars($booking['room_type']); ?></span></div>
        <div class="detail-row"><span class="detail-label">Check-in</span><span class="detail-value"><?php echo date('M d, Y', strtotime($booking['checkin_date'])); ?></span></div>
        <div class="detail-row"><span class="detail-label">Check-out</span><span class="detail-value"><?php echo date('M d, Y', strtotime($booking['checkout_date'])); ?></span></div>
    </div>

    </div>

    <form method="POST">
        <button type="submit" class="btn-confirm">Confirm and Notify Guest</button>
    </form>
    <a href="admin.php" class="btn-back">← Back to Dashboard</a>
</div>

</body>
</html>