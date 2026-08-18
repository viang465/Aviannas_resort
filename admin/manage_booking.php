<?php
session_start();
include "../conn.php"; 

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

// Room pricing array for automated total calculation
// Must match the accommodation options offered on the public book.php form
$room_prices = [
    'None'                  => 0.00,
    'Overnight Room'        => 2500.00,
    'Poolside Pavilion'     => 2200.00,
    'Pavilion 1'            => 2000.00,
    'Pavilion Overlooking'  => 2500.00,
    'Old Pavilion'          => 2000.00,
    'New Pavilion'          => 3500.00,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name']);
    $email          = trim($_POST['email']);
    $contact        = trim($_POST['contact']);
    $address        = trim($_POST['address']);
    $room_type      = trim($_POST['room_type']);
    $checkin_date   = trim($_POST['checkin_date']);
    $checkout_date  = trim($_POST['checkout_date']);
    $payment_method = trim($_POST['payment_method']);

    if (empty($name) || empty($email) || empty($room_type) || empty($checkin_date) || empty($checkout_date)) {
        $error_msg = "Please fill in all required fields.";
    } else {
        // Calculate days stayed
        $start = new DateTime($checkin_date);
        $end   = new DateTime($checkout_date);
        $days  = max(1, $start->diff($end)->days);
        
        $price_per_night = $room_prices[$room_type] ?? 2000.00;
        $total_price     = $days * $price_per_night;

        if ($id > 0) {
            // Update existing booking
            $stmt = $conn->prepare("UPDATE bookings SET name=?, email=?, contact=?, address=?, room_type=?, checkin_date=?, checkout_date=?, payment_method=?, total_price=? WHERE id=?");
            $stmt->bind_param("ssssssssdi", $name, $email, $contact, $address, $room_type, $checkin_date, $checkout_date, $payment_method, $total_price, $id);
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
            $stmt = $conn->prepare("INSERT INTO bookings (name, email, contact, address, room_type, checkin_date, checkout_date, payment_method, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssds", $name, $email, $contact, $address, $room_type, $checkin_date, $checkout_date, $payment_method, $total_price, $status);
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
                    <label class="form-label fw-semibold">Room Type *</label>
                    <select name="room_type" class="form-select" required>
                        <?php foreach (array_keys($room_prices) as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo (isset($booking['room_type']) && $booking['room_type'] === $type) ? 'selected' : ''; ?>>
                                <?php echo $type; ?>
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