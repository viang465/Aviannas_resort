<?php
session_start();
include "../conn.php";
include "../accommodations.php"; // shared room/cottage catalog — same one book.php uses

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$booking = null;
$error_msg = "";

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Pricing, derived from the shared catalog — always matches the public booking form.
$prices = accommodations_price_table($accommodations);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name']);
    $email          = trim($_POST['email']);
    $contact        = trim($_POST['contact']);
    $address        = trim($_POST['address']);
    $room_type      = trim($_POST['room_type']    ?? 'None');
    $cottage_type   = trim($_POST['cottage_type'] ?? 'None');
    $checkin_date   = trim($_POST['checkin_date']);
    $checkout_date  = trim($_POST['checkout_date']);
    $payment_method = trim($_POST['payment_method']);

    if (empty($name) || empty($email) || empty($checkin_date) || empty($checkout_date)) {
        $error_msg = "Please fill in all required fields.";
    } else {
        // Calculate nights stayed (same-day/day-use counts as 1 night, matching book.php)
        $start = new DateTime($checkin_date);
        $end   = new DateTime($checkout_date);
        $nights = max(1, $start->diff($end)->days);

        $room_price    = $prices['room'][$room_type]       ?? 0.00;
        $cottage_price = $prices['cottage'][$cottage_type]  ?? 0.00;
        $cottage_meta  = accommodations_find_by_value($accommodations, $cottage_type);
        if ($cottage_meta && !empty($cottage_meta['elec'])) {
            $cottage_price += $cottage_meta['elec'];
        }
        $total_price = ($room_price * $nights) + $cottage_price;

        if ($id > 0) {
            // Update existing booking
            $stmt = $conn->prepare("UPDATE bookings SET name=?, email=?, contact=?, address=?, room_type=?, cottage_type=?, checkin_date=?, checkout_date=?, payment_method=?, total_price=? WHERE id=?");
            $stmt->bind_param("sssssssssdi", $name, $email, $contact, $address, $room_type, $cottage_type, $checkin_date, $checkout_date, $payment_method, $total_price, $id);
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: admin.php?booking=updated");
                exit();
            } else {
                $error_msg = "Database Error: " . $conn->error;
            }
        } else {
            // Create new booking
            $status = 'Pending';
            $stmt = $conn->prepare("INSERT INTO bookings (name, email, contact, address, room_type, cottage_type, checkin_date, checkout_date, payment_method, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssds", $name, $email, $contact, $address, $room_type, $cottage_type, $checkin_date, $checkout_date, $payment_method, $total_price, $status);
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: admin.php?booking=created");
                exit();
            } else {
                $error_msg = "Database Error: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id > 0 ? "Edit Booking" : "New Booking"; ?> | Avianna's Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-green: #1e4d40; --accent-teal: #2c7a7b; --bg-light: #f4f7f6; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-light); padding: 40px 0; }
        .form-card { background: white; border-radius: 16px; padding: 35px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); max-width: 650px; margin: auto; }
        .btn-custom { background-color: var(--primary-green); color: white; border-radius: 8px; font-weight: 600; }
        .btn-custom:hover { background-color: var(--accent-teal); color: white; }
    </style>
</head>
<body>
<div class="container">
    <div class="form-card">
        <h3 class="fw-bold text-dark mb-4"><?php echo $id > 0 ? "Edit Reservation" : "Create New Reservation"; ?></h3>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger mb-4"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Guest Name *</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($booking['name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email Address *</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($booking['email'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Contact Number</label>
                    <input type="text" name="contact" class="form-control" value="<?php echo htmlspecialchars($booking['contact'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Address</label>
                    <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($booking['address'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Room / Pavilion Type</label>
                    <select name="room_type" class="form-select">
                        <option value="None" <?php echo (empty($booking['room_type']) || $booking['room_type'] === 'None') ? 'selected' : ''; ?>>No Accommodation</option>
                        <?php foreach ($accommodations as $item): if ($item['type'] !== 'room') continue; ?>
                            <option value="<?php echo htmlspecialchars($item['value'], ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo (isset($booking['room_type']) && $booking['room_type'] === $item['value']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?> — ₱<?php echo number_format($item['price']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Cottage Type</label>
                    <select name="cottage_type" class="form-select">
                        <option value="None" <?php echo (empty($booking['cottage_type']) || $booking['cottage_type'] === 'None') ? 'selected' : ''; ?>>No Cottage</option>
                        <?php foreach ($accommodations as $item): if ($item['type'] !== 'cottage') continue; ?>
                            <option value="<?php echo htmlspecialchars($item['value'], ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo (isset($booking['cottage_type']) && $booking['cottage_type'] === $item['value']) ? 'selected' : ''; ?>>
                                <?php
                                    echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8');
                                    echo ' — ₱' . number_format($item['price']);
                                    if (!empty($item['elec'])) echo ' + ₱' . number_format($item['elec']) . ' elec';
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="Cash" <?php echo (isset($booking['payment_method']) && $booking['payment_method'] === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                        <option value="GCash" <?php echo (isset($booking['payment_method']) && $booking['payment_method'] === 'GCash') ? 'selected' : ''; ?>>GCash</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Check-in Date *</label>
                    <input type="date" name="checkin_date" class="form-control" value="<?php echo htmlspecialchars($booking['checkin_date'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Check-out Date *</label>
                    <input type="date" name="checkout_date" class="form-control" value="<?php echo htmlspecialchars($booking['checkout_date'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                <a href="admin.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-custom px-4 py-2">Save Booking</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>