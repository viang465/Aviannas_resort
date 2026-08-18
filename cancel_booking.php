<?php
/**
 * cancel_reservation.php
 * Public-facing page that lets a guest cancel their booking by email.
 * Separated from mail_config.php so requiring mail_config.php in book.php
 * no longer accidentally triggers cancellation logic on every booking load.
 */
session_start();
include "conn.php";

$message      = "";
$status_class = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    if ($email) {
        // Verify there is actually a booking for this email first
        $check = $conn->prepare("SELECT id FROM bookings WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows === 0) {
            $message      = "No active booking found for that email address.";
            $status_class = "alert-warning";
            $check->close();
        } else {
            $check->close();

            // Copy to deleted_bookings archive using the correct column names
            // (checkin_date / checkout_date / deletion_date match cancel_booking.php)
            $copySql = "
                INSERT INTO deleted_bookings
                    (name, email, contact, address, room_type, cottage_type, pax,
                     payment_method, total_price,
                     checkin_date, checkout_date, deletion_date, deleted_at)
                SELECT
                    name, email, contact, address, room_type, cottage_type, pax,
                    payment_method, total_price,
                    checkin_date, checkout_date, NOW(), NOW()
                FROM bookings
                WHERE email = ?
            ";
            $stmt_copy = $conn->prepare($copySql);
            $stmt_copy->bind_param("s", $email);

            if ($stmt_copy->execute()) {
                $stmt_del = $conn->prepare("DELETE FROM bookings WHERE email = ?");
                $stmt_del->bind_param("s", $email);
                $stmt_del->execute();
                $stmt_del->close();
                $message      = "Your reservation has been successfully cancelled.";
                $status_class = "alert-success";
            } else {
                $message      = "An error occurred while cancelling your booking. Please try again.";
                $status_class = "alert-danger";
                error_log("Cancel reservation error: " . $stmt_copy->error);
            }
            $stmt_copy->close();
        }
    } else {
        $message      = "Please enter a valid email address.";
        $status_class = "alert-danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Reservation — Avianna's Inland Resort</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --dark: #1e4d40; }
        body { background: #f8fafc; display: flex; align-items: center; min-height: 100vh; }
        .cancel-card {
            max-width: 450px; width: 90%; margin: auto;
            background: white; padding: 40px;
            border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .btn-cancel-res {
            background-color: #c53030; border: none; color: white;
            width: 100%; padding: 12px; border-radius: 10px;
            font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s;
        }
        .btn-cancel-res:hover { background-color: #9b2c2c; }
    </style>
</head>
<body>
<div class="cancel-card">
    <h3 class="mb-1 text-center fw-bold" style="color: var(--dark);">Cancel Your Reservation</h3>
    <p class="text-center text-muted mb-4" style="font-size:.88rem;">
        Enter the email address you used when booking.
    </p>

    <?php if ($message): ?>
        <div class="alert <?= $status_class ?> rounded-3">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($status_class !== 'alert-success'): ?>
    <form method="POST">
        <div class="mb-3">
            <label for="email" class="form-label fw-semibold">Booking Email</label>
            <input type="email" name="email" id="email" class="form-control"
                   placeholder="yourname@email.com" required>
        </div>
        <button type="submit" class="btn-cancel-res">Cancel My Reservation</button>
    </form>
    <?php else: ?>
        <div class="text-center mt-3">
            <a href="index.php" class="btn btn-outline-success px-4">← Back to Home</a>
        </div>
    <?php endif; ?>

    <div class="text-center mt-4">
        <a href="index.php" class="text-muted text-decoration-none" style="font-size:.82rem;">← Return to Home</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
