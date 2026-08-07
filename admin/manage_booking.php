<?php
session_start();
include "../conn.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $id > 0;

$errors = [];
$booking = [
    'name' => '', 'email' => '', 'contact' => '', 'address' => '', 'room_type' => '',
    'cottage_type' => 'None', 'pax' => '1-6',
    'checkin_date' => '', 'checkout_date' => '', 'payment_method' => '',
    'total_price' => '', 'status' => 'Pending'
];

// Load existing booking for edit mode
if ($isEdit) {
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existing) {
        header("Location: admin.php");
        exit();
    }
    $booking = array_merge($booking, $existing);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking['name']           = trim($_POST['name'] ?? '');
    $booking['email']          = trim($_POST['email'] ?? '');
    $booking['contact']        = trim($_POST['contact'] ?? '');
    $booking['address']        = trim($_POST['address'] ?? '');
    $booking['room_type']      = trim($_POST['room_type'] ?? '');
    $booking['cottage_type']   = $_POST['cottage_type'] ?? 'None';
    $booking['pax']            = $_POST['pax'] ?? '1-6';
    $booking['checkin_date']   = trim($_POST['checkin_date'] ?? '');
    $booking['checkout_date']  = trim($_POST['checkout_date'] ?? '');
    $booking['payment_method'] = trim($_POST['payment_method'] ?? '');
    $booking['total_price']    = trim($_POST['total_price'] ?? '');

    if ($booking['name'] === '') $errors[] = "Guest name is required.";
    if ($booking['email'] === '' || !filter_var($booking['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
    if ($booking['contact'] === '') $errors[] = "A contact number is required for the front desk.";
    if ($booking['room_type'] === '') $errors[] = "Room type is required.";
    if ($booking['checkin_date'] === '') $errors[] = "Check-in date is required.";
    if ($booking['checkout_date'] === '') $errors[] = "Check-out date is required.";
    if ($booking['checkin_date'] !== '' && $booking['checkout_date'] !== '' && strtotime($booking['checkout_date']) <= strtotime($booking['checkin_date'])) {
        $errors[] = "Check-out date must be after check-in date.";
    }
    if ($booking['total_price'] !== '' && !is_numeric($booking['total_price'])) $errors[] = "Total price must be a number.";

    $totalPrice = $booking['total_price'] === '' ? 0 : (float)$booking['total_price'];

    if (empty($errors)) {
        if ($isEdit) {
            $stmt = $conn->prepare(
                "UPDATE bookings SET name=?, email=?, contact=?, address=?, room_type=?, cottage_type=?, pax=?, checkin_date=?, checkout_date=?, payment_method=?, total_price=? WHERE id=?"
            );
            $stmt->bind_param(
                "ssssssssssdi",
                $booking['name'], $booking['email'], $booking['contact'], $booking['address'], $booking['room_type'],
                $booking['cottage_type'], $booking['pax'],
                $booking['checkin_date'], $booking['checkout_date'], $booking['payment_method'],
                $totalPrice, $id
            );
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: admin.php?booking=updated");
                exit();
            }
            $errors[] = "Database error: " . $conn->error;
            $stmt->close();
        } else {
            $status = 'Pending';
            $stmt = $conn->prepare(
                "INSERT INTO bookings (name, email, contact, address, room_type, cottage_type, pax, checkin_date, checkout_date, payment_method, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssssssssssds",
                $booking['name'], $booking['email'], $booking['contact'], $booking['address'], $booking['room_type'],
                $booking['cottage_type'], $booking['pax'],
                $booking['checkin_date'], $booking['checkout_date'], $booking['payment_method'],
                $totalPrice, $status
            );
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: admin.php?booking=created");
                exit();
            }
            $errors[] = "Database error: " . $conn->error;
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
    <title><?php echo $isEdit ? 'Edit Booking' : 'New Booking'; ?> | Avianna's Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-green: #1e4d40;
            --accent-teal: #2c7a7b;
            --sidebar-width: 260px;
            --bg-light: #f4f7f6;
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); margin: 0; }

        #scrollUp, .scroll-to-top, .back-to-top, [id*="scroll"], .tp-top-arrow, button[title*="top"], .scrollup {
            display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important;
        }

        .sidebar {
            height: 100vh; background: linear-gradient(180deg, var(--primary-green) 0%, #0a1a16 100%);
            color: white; position: fixed; width: var(--sidebar-width); padding: 25px 20px;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1); z-index: 1000;
        }
        .sidebar h4 { font-weight: 700; text-align: center; margin-bottom: 30px; }
        .nav-link {
            color: rgba(255,255,255,0.7); margin-bottom: 8px; padding: 12px 15px;
            border-radius: 8px; font-weight: 500; transition: all 0.3s ease; text-decoration: none; display: block;
        }
        .nav-link:hover { color: white; background: rgba(255,255,255,0.1); transform: translateX(5px); }
        .nav-link.active { color: white; background: var(--accent-teal); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }

        .main-content { margin-left: var(--sidebar-width); padding: 40px; min-height: 100vh; }

        .header-title {
            color: var(--primary-green); border-left: 6px solid var(--accent-teal);
            padding-left: 20px; font-weight: 700; margin-bottom: 30px;
        }

        .form-card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 35px; max-width: 720px; }
        .form-label { font-weight: 600; color: var(--primary-green); }
        .btn-save { background-color: var(--primary-green); color: white; border-radius: 8px; padding: 10px 24px; font-weight: 600; border: none; }
        .btn-save:hover { background-color: var(--accent-teal); color: white; }
        .btn-cancel { border-radius: 8px; padding: 10px 24px; font-weight: 600; }

        @media (max-width: 992px) {
            .sidebar { width: 80px; padding: 20px 10px; }
            .sidebar h4, .nav-link span { display: none; }
            .main-content { margin-left: 80px; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h4>Avianna's Admin</h4>
    <hr style="border-color: rgba(255,255,255,0.1);">
    <nav class="nav flex-column">
        <a class="nav-link active" href="admin.php"><span>Pending Bookings</span></a>
        <a class="nav-link" href="approve.php"><span>Approved History</span></a>
        <a class="nav-link" href="admin_cancelled.php"><span>Cancellation History</span></a>
        <a class="nav-link" href="admin_announcements.php"><span>Announcements</span></a>
        <a class="nav-link" href="admin_analytics.php"><span>Analytics</span></a>
        <hr style="border-color: rgba(255,255,255,0.1); margin: 20px 0;">
        <a class="nav-link text-info" href="../reception/index.php"><span>🛎 Front Desk</span></a>
        <a class="nav-link text-warning" href="../index.php" target="_blank"><span>← View Website</span></a>
        <a class="nav-link text-danger" href="logout.php"><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <h2 class="header-title"><?php echo $isEdit ? 'Edit Booking' : 'New Booking'; ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Guest Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($booking['name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($booking['email']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact" class="form-control" value="<?php echo htmlspecialchars($booking['contact'] ?? ''); ?>" placeholder="+63 9XX XXX XXXX" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($booking['address'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Room Type</label>
                    <input type="text" name="room_type" class="form-control" value="<?php echo htmlspecialchars($booking['room_type']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cottage Type</label>
                    <select name="cottage_type" class="form-select">
                        <?php foreach (['None', 'Cottage 6', 'Cottage 400', 'Cottage 600'] as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo ($booking['cottage_type'] ?? 'None') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Number of Guests (Pax)</label>
                    <select name="pax" class="form-select">
                        <?php foreach (['1-6', '7-15', '16-30', '31-50', '50+'] as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo ($booking['pax'] ?? '1-6') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?> pax</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <?php foreach (['Cash', 'GCash'] as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo ($booking['payment_method'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Check-in Date</label>
                    <input type="date" name="checkin_date" class="form-control" value="<?php echo htmlspecialchars($booking['checkin_date']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Check-out Date</label>
                    <input type="date" name="checkout_date" class="form-control" value="<?php echo htmlspecialchars($booking['checkout_date']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Total Price (₱)</label>
                    <input type="number" step="0.01" min="0" name="total_price" class="form-control" value="<?php echo htmlspecialchars($booking['total_price']); ?>">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-save"><?php echo $isEdit ? 'Save Changes' : 'Create Booking'; ?></button>
                <a href="admin.php" class="btn btn-outline-secondary btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>