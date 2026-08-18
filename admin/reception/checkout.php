<?php
session_start();
require_once '../../conn.php';

$booking_id = intval($_GET['id'] ?? 0);
$guest = null;
$error = "";

if ($booking_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $guest = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$guest) {
        $error = "Booking #{$booking_id} was not found.";
    } elseif ($guest['status'] !== 'Checked In') {
        $error = "This guest is not currently checked in (status: {$guest['status']}).";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $guest && $guest['status'] === 'Checked In') {
    $incidentals = floatval($_POST['incidentals'] ?? 0);
    $final_total = floatval($guest['total_price']) + $incidentals;

    $stmt = $conn->prepare("UPDATE bookings SET status = 'Checked Out', total_price = ? WHERE id = ?");
    $stmt->bind_param("di", $final_total, $booking_id);
    
    if ($stmt->execute()) {
        header("Location: print_receipt.php?id=" . $booking_id);
        exit;
    } else {
        $error = "Failed to complete check-out.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Check-Out | Reception</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .card-custom { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="container py-5" style="max-width: 650px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="index.php" class="btn btn-outline-secondary rounded-pill btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
        <h4 class="fw-bold mb-0">Express Check-Out</h4>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-warning text-center">
            <?php echo htmlspecialchars($error); ?>
            <?php if ($guest && $guest['status'] === 'Checked Out'): ?>
                <div class="mt-2"><a href="print_receipt.php?id=<?php echo $guest['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill">View Receipt</a></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($guest && $guest['status'] === 'Checked In'): ?>
        <div class="card card-custom p-4">
            <h5 class="fw-bold text-dark border-bottom pb-2"><i class="bi bi-box-arrow-right me-2 text-warning"></i> Checkout Confirmation</h5>
            
            <div class="my-3">
                <p class="mb-1"><strong>Guest Name:</strong> <?php echo htmlspecialchars($guest['name']); ?></p>
                <p class="mb-1"><strong>Accommodation:</strong> <?php echo htmlspecialchars($guest['room_type']); ?></p>
                <p class="mb-1"><strong>Stay Dates:</strong> <?php echo $guest['checkin_date']; ?> to <?php echo $guest['checkout_date']; ?></p>
                <p class="mb-1"><strong>Base Total:</strong> ₱<?php echo number_format($guest['total_price'], 2); ?></p>
            </div>

            <form method="POST">
    
                <button type="submit" class="btn btn-warning w-100 py-2 fw-bold text-dark">Complete Check-Out & Generate Invoice</button>
            </form>
        </div>
    <?php elseif (!$error): ?>
        <div class="alert alert-warning text-center">Guest record not found. <a href="index.php">Return to dashboard</a>.</div>
    <?php endif; ?>
</div>

</body>
</html>