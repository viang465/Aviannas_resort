<?php
session_start();
require_once '../../conn.php';

$booking_id = intval($_GET['id'] ?? 0);

if ($booking_id <= 0) {
    die("Invalid Receipt Request");
}

$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    die("Booking record not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo $booking['id']; ?> - Avianna's Inland Resort</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Courier New', Courier, monospace; }
        .receipt-card {
            max-width: 500px;
            margin: 30px auto;
            background: #fff;
            padding: 30px;
            border: 1px dashed #ccc;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .receipt-card { border: none; box-shadow: none; margin: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="container text-center no-print mt-3">
    <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 me-2">🖨️ Print Receipt</button>
    <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">Back to Dashboard</a>
</div>

<div class="receipt-card">
    <div class="text-center mb-4">
        <h3 class="fw-bold mb-1">AVIANNA'S INLAND RESORT</h3>
        <p class="small mb-0">Zone 6 Cabugao Sur Sta. Barbara, Iloilo</p>
        <p class="small mb-0">Contact: +1 234 567 890</p>
        <hr style="border-top: 2px dashed #000;">
        <h5 class="fw-bold mt-2"><?php echo $booking['status'] === 'Checked Out' ? 'OFFICIAL RECEIPT' : 'CHECK-IN CONFIRMATION'; ?></h5>
        <p class="small">Receipt No: <strong>#REC-<?php echo str_pad($booking['id'], 5, '0', STR_PAD_LEFT); ?></strong></p>
    </div>

    <div class="mb-3 small">
        <p class="mb-1"><strong>Date:</strong> <?php echo date('F d, Y h:i A'); ?></p>
        <p class="mb-1"><strong>Guest:</strong> <?php echo htmlspecialchars($booking['name']); ?></p>
        <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($booking['contact']); ?></p>
        <p class="mb-1"><strong>Payment Method:</strong> <?php echo htmlspecialchars($booking['payment_method']); ?></p>
        <p class="mb-1"><strong>Payment Status:</strong> <?php echo htmlspecialchars($booking['payment_status'] ?? 'Pending'); ?></p>
        <p class="mb-1"><strong>Status:</strong> <?php echo htmlspecialchars($booking['status']); ?></p>
    </div>

    <hr style="border-top: 1px dashed #000;">

    <table class="w-100 small mb-3">
        <thead>
            <tr class="border-bottom">
                <th class="text-start pb-1">Description</th>
                <th class="text-end pb-1">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="pt-2"><?php echo htmlspecialchars($booking['room_type']); ?></td>
                <td class="text-end pt-2">Included</td>
            </tr>
            <?php if ($booking['cottage_type'] !== 'None'): ?>
            <tr>
                <td><?php echo htmlspecialchars($booking['cottage_type']); ?></td>
                <td class="text-end">Included</td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>Stay Duration</td>
                <td class="text-end"><?php echo $booking['checkin_date']; ?> - <?php echo $booking['checkout_date']; ?></td>
            </tr>
        </tbody>
    </table>

    <hr style="border-top: 1px dashed #000;">

    <div class="d-flex justify-content-between fw-bold fs-5 my-2">
        <span><?php echo $booking['status'] === 'Checked Out' ? 'TOTAL PAID:' : 'TOTAL DUE:'; ?></span>
        <span>₱<?php echo number_format($booking['total_price'], 2); ?></span>
    </div>

    <hr style="border-top: 2px dashed #000;">

    <div class="text-center mt-4 small">
        <p class="mb-1 fw-bold">Thank you for staying with us!</p>
        <p class="text-muted">Please present this receipt upon departure.</p>
    </div>
</div>

</body>
</html>