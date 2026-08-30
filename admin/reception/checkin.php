<?php
session_start();
require_once '../../conn.php';

$message = "";
$error = "";

// Handle Quick Check-In from URL ID
$prefill_id = intval($_GET['id'] ?? 0);
$guest = null;

if ($prefill_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $prefill_id);
    $stmt->execute();
    $guest = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$guest) {
        $error = "Booking #{$prefill_id} was not found. You can register a walk-in guest below.";
    } elseif ($guest['status'] === 'Checked In') {
        $error = "Booking #{$prefill_id} is already checked in.";
    } elseif ($guest['status'] === 'Checked Out') {
        $error = "Booking #{$prefill_id} has already checked out.";
        $guest = null;
    }
}

// Handle Form Submission (Walk-in or Status Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = intval($_POST['booking_id'] ?? 0);

    if ($booking_id > 0) {
        // Checking in an existing reservation
        $payment_status = $_POST['payment_status'] ?? 'Pending';
        if (!in_array($payment_status, ['Paid', 'Partial', 'Pending'], true)) {
            $payment_status = 'Pending';
        }

        $stmt = $conn->prepare("UPDATE bookings SET status = 'Checked In', payment_status = ? WHERE id = ?");
        $stmt->bind_param("si", $payment_status, $booking_id);
        if ($stmt->execute()) {
            header("Location: index.php?msg=checked_in");
            exit;
        } else {
            $error = "Failed to update check-in status.";
        }
        $stmt->close();
    } else {
        // Walk-in Guest Insertion
        $name        = trim($_POST['name'] ?? '');
        $email       = trim($_POST['email'] ?? 'walkin@resort.local');
        $contact     = trim($_POST['contact'] ?? '');
        $address     = trim($_POST['address'] ?? 'Walk-in Guest');
        $room        = $_POST['room_type'] ?? 'None';
        $cottage     = $_POST['cottage_type'] ?? 'None';
        $pax         = $_POST['pax'] ?? '1-6';
        $checkin     = $_POST['checkin_date'] ?? date('Y-m-d');
        $checkout    = $_POST['checkout_date'] ?? date('Y-m-d', strtotime('+1 day'));
        $payment     = $_POST['payment_method'] ?? 'Cash';
        $total_price = floatval($_POST['total_price'] ?? 0);
        $status      = 'Checked In';
        $payment_status = $_POST['payment_status'] ?? 'Pending';
        if (!in_array($payment_status, ['Paid', 'Partial', 'Pending'], true)) {
            $payment_status = 'Pending';
        }

        if (empty($name) || empty($contact)) {
            $error = "Guest Name and Contact Number are required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO bookings (name, email, contact, address, room_type, cottage_type, pax, checkin_date, checkout_date, payment_method, total_price, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssdss", $name, $email, $contact, $address, $room, $cottage, $pax, $checkin, $checkout, $payment, $total_price, $status, $payment_status);
            
            if ($stmt->execute()) {
                $new_id = $stmt->insert_id;
                header("Location: print_receipt.php?id=" . $new_id);
                exit;
            } else {
                $error = "Error saving walk-in booking: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Check-In | Reception</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .bg-tropical { background-color: #1a4731; color: white; }
    </style>
</head>
<body>

<div class="container py-4" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="index.php" class="btn btn-outline-secondary rounded-pill btn-sm"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        <h4 class="fw-bold mb-0">Front Desk Check-In</h4>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 mb-3"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card card-custom p-4">
        <?php if ($guest): ?>
            <!-- Existing Booking Confirmation -->
            <h5 class="fw-bold text-success mb-3"><i class="bi bi-person-check-fill me-2"></i> Confirm Guest Check-In</h5>
            <table class="table table-bordered mb-4">
                <tr><th>Booking ID</th><td>#<?php echo $guest['id']; ?></td></tr>
                <tr><th>Guest Name</th><td><?php echo htmlspecialchars($guest['name']); ?></td></tr>
                <tr><th>Contact</th><td><?php echo htmlspecialchars($guest['contact']); ?></td></tr>
                <tr><th>Accommodation</th><td><?php echo htmlspecialchars($guest['room_type']); ?> / <?php echo htmlspecialchars($guest['cottage_type']); ?></td></tr>
                <tr><th>Total Due</th><td class="fw-bold text-success">₱<?php echo number_format($guest['total_price'], 2); ?></td></tr>
                <tr>
                    <th>Payment Status</th>
                    <td>
                        <?php $curPayStatus = $guest['payment_status'] ?? 'Pending'; ?>
                        <span class="badge <?php echo $curPayStatus === 'Paid' ? 'bg-success' : 'bg-danger'; ?>"><?php echo htmlspecialchars($curPayStatus); ?></span>
                    </td>
                </tr>
            </table>

            <form method="POST">
                <input type="hidden" name="booking_id" value="<?php echo $guest['id']; ?>">
                <div class="mb-3">
                    <label class="form-label fw-bold">Payment Status</label>
                    <select name="payment_status" class="form-select">
                        <option value="Pending" <?php echo $curPayStatus === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Partial" <?php echo $curPayStatus === 'Partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="Paid" <?php echo $curPayStatus === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success w-100 py-2 fw-bold">Confirm Check-In Now</button>
            </form>
        <?php else: ?>
            <!-- Walk-in Form -->
            <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-person-plus-fill me-2"></i> New Walk-In Registration</h5>
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Guest Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Full Name">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Contact Number *</label>
                    <input type="text" name="contact" class="form-control" required placeholder="+63 9XX XXX XXXX">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="Optional">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" placeholder="City / Province">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Room / Pavilion Choice</label>
                    <select name="room_type" class="form-select">
                        <option value="None">None</option>
                        <option value="Overnight Room">Overnight Room (₱2,500)</option>
                        <option value="Poolside Pavilion">Poolside Pavilion (₱2,200)</option>
                        <option value="Pavilion 1">Pavilion 1 (₱2,000)</option>
                        <option value="New Pavilion">New Pavilion (₱3,500)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cottage Type</label>
                    <select name="cottage_type" class="form-select">
                        <option value="None">None</option>
                        <option value="Cottage 6">Cottage 6 (₱500)</option>
                        <option value="Cottage 400">Cottage 400 (₱400)</option>
                        <option value="Cottage 600">Cottage 600 (₱600)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Number of Guests (Pax)</label>
                    <select name="pax" class="form-select">
                        <option value="1-6">1 - 6 pax</option>
                        <option value="7-15">7 - 15 pax</option>
                        <option value="16-30">16 - 30 pax</option>
                        <option value="31-50">31 - 50 pax</option>
                        <option value="50+">50+ pax</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Status</label>
                    <select name="payment_status" class="form-select">
                        <option value="Pending">Pending</option>
                        <option value="Partial">Partial</option>
                        <option value="Paid">Paid</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Check-In Date</label>
                    <input type="date" name="checkin_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Check-Out Date</label>
                    <input type="date" name="checkout_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Total Bill (₱)</label>
                    <input type="number" step="0.01" name="total_price" class="form-control" required placeholder="0.00">
                </div>
                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-success w-100 py-2 fw-bold">Process Check-In & Print Receipt</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>