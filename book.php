<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// Include database connection gracefully
if (file_exists(__DIR__ . '/conn.php')) {
    include(__DIR__ . '/conn.php');
}

// Safely require mail_config.php without throwing a Fatal Error
if (file_exists(__DIR__ . '/mail_config.php')) {
    require_once __DIR__ . '/mail_config.php';
}

// Safely require PHPMailer files
if (file_exists(__DIR__ . '/PHPMailer.php')) {
    require_once __DIR__ . '/PHPMailer.php';
    require_once __DIR__ . '/Exception.php';
    require_once __DIR__ . '/SMTP.php';
}

// Define fallback constants if mail_config.php does not exist yet
if (!defined('MAIL_HOST'))     define('MAIL_HOST', 'smtp.gmail.com');
if (!defined('MAIL_USERNAME')) define('MAIL_USERNAME', 'your-email@gmail.com');
if (!defined('MAIL_PASSWORD')) define('MAIL_PASSWORD', 'your-app-password');
if (!defined('MAIL_PORT'))     define('MAIL_PORT', 587);
if (!defined('MAIL_FROM'))     define('MAIL_FROM', 'your-email@gmail.com');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', "Avianna's Inland Resort");

// Entrance fee charged per person (PHP pesos)
if (!defined('ENTRANCE_FEE')) define('ENTRANCE_FEE', 75);

$booking_success = false;
$booking_error   = "";
$name            = "";

// ── Pricing Rules ──────────────────────────────────────────────────────────
// NOTE: these keys MUST match the <option value="..."> values in the form below.
$prices = [
    'room'    => [
        'None'                     => 0,
        'Tee Pee Hut (overnight)'  => 2000,
        'Tee Pee Hut (day)'        => 800,
        'Large Pavillion'          => 4000,
        'Poolside Pavillion'       => 2500,
        'Pavillion 1'              => 2000,
        'Overnight'                => 2500,
    ],
    'cottage' => [
        'None'          => 0,
        'Cottage 1,2,3' => 600,   // +₱100 electricity surcharge applied separately
        'Cottage 5'     => 400,
        'Cottage 6'     => 400,   // +₱100 electricity surcharge applied separately
    ],
];

// ── Shared mailer factory ──────────────────────────────────────────────────
function getMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAIL_PORT;
    $mail->Timeout    = 15;
    $mail->SMTPOptions = ['ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
    ]];
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    return $mail;
}

// ── Send "Booking Received / Pending" email to guest ──────────────────────
function sendPendingEmail(array $d): bool {
    // Check if PHPMailer is loaded before attempting email
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer class not found. Skipping email sending.");
        return false;
    }

    try {
        $mail = getMailer();
        $mail->addAddress($d['email'], $d['name']);
        $mail->Subject = "Booking Received - Avianna's Inland Resort";

        $name     = htmlspecialchars($d['name']);
        $contact  = htmlspecialchars($d['contact']);
        $address  = htmlspecialchars($d['address']);
        $room     = htmlspecialchars($d['room']);
        $cottage  = htmlspecialchars($d['cottage']);
        $pax      = (int)$d['pax'];
        $checkin  = date('F d, Y', strtotime($d['checkin']));
        $checkout = date('F d, Y', strtotime($d['checkout']));
        $payment  = htmlspecialchars($d['payment']);
        $total    = '₱' . number_format($d['total_price'], 2);
        $rp       = '₱' . number_format($d['room_price'], 2);
        $cp       = '₱' . number_format($d['cottage_price'], 2);
        $ef       = '₱' . number_format($d['entrance_fee'], 2);
        $efRate   = '₱' . number_format(ENTRANCE_FEE, 2);

        $mail->Body = "
        <div style='font-family:Arial,sans-serif;max-width:640px;margin:0 auto;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;'>
            <div style='background:#1e4d40;padding:32px;text-align:center;'>
                <div style='font-size:48px;margin-bottom:8px;'>📋</div>
                <h1 style='color:#ffffff;margin:0;font-size:1.7rem;'>Booking Received!</h1>
                <p style='color:rgba(255,255,255,0.75);margin:8px 0 0;'>Avianna's Inland Resort</p>
            </div>
            <div style='padding:30px 35px;color:#333;line-height:1.7;'>
                <p>Hi <strong>{$name}</strong>,</p>
                <p>Thank you for choosing <strong>Avianna's Inland Resort</strong>! Your reservation is <strong style='color:#d97706;'>PENDING</strong> and under review.</p>
                <p style='font-size:0.9rem;color:#555;'>You will receive a second email once your booking is <strong>Approved</strong>.</p>
                <div style='background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:20px 25px;margin:24px 0;'>
                    <h3 style='color:#1e4d40;margin-top:0;font-size:1rem;text-transform:uppercase;letter-spacing:1px;'>Booking Summary</h3>
                    <table style='width:100%;border-collapse:collapse;font-size:0.93rem;'>
                        <tr style='border-bottom:1px solid #d1fae5;'><td style='padding:8px 0;color:#555;width:42%;'>Name</td><td style='font-weight:600;'>{$name}</td></tr>
                        <tr style='border-bottom:1px solid #d1fae5;'><td style='padding:8px 0;color:#555;'>Contact</td><td>{$contact}</td></tr>
                        <tr style='border-bottom:1px solid #d1fae5;'><td style='padding:8px 0;color:#555;'>Address</td><td>{$address}</td></tr>
                        <tr style='border-bottom:1px solid #d1fae5;'><td style='padding:8px 0;color:#555;'>Room / Pavilion</td><td>{$room} <span style='color:#6b7280;font-size:.85rem;'>({$rp})</span></td></tr>
                        <tr style='border-bottom:1px solid #d1fae5;'><td style='padding:8px 0;color:#555;'>Cottage</td><td>{$cottage} <span style='color:#6b7280;font-size:.85rem;'>({$cp})</span></td></tr>
                        <tr style='border-bottom:1px solid #d1fae5;'><td style='padding:8px 0;color:#555;'>Guests</td><td>{$pax}</td></tr>
                        <tr style='border-bottom:1px solid #d1fae5;'><td style='padding:8px 0;color:#555;'>Entrance Fee</td><td>{$pax} × {$efRate} <span style='color:#6b7280;font-size:.85rem;'>({$ef})</span></td></tr>
                        <tr style='border-bottom:1px solid #d1fae5;'><td style='padding:8px 0;color:#555;'>Check-in</td><td style='font-weight:600;color:#1e4d40;'>{$checkin}</td></tr>
                        <tr style='border-bottom:1px solid #d1fae5;'><td style='padding:8px 0;color:#555;'>Check-out</td><td style='font-weight:600;color:#1e4d40;'>{$checkout}</td></tr>
                        <tr style='border-bottom:1px solid #d1fae5;'><td style='padding:8px 0;color:#555;'>Payment</td><td>{$payment}</td></tr>
                        <tr><td style='padding:10px 0;font-weight:700;'>Estimated Total</td><td style='font-size:1.1rem;font-weight:700;color:#16a34a;'>{$total}</td></tr>
                    </table>
                </div>
                <div style='background:#fffbeb;border-left:4px solid #f59e0b;padding:14px 18px;border-radius:6px;margin-bottom:20px;font-size:0.9rem;color:#555;'>
                    <strong>What happens next?</strong>
                    <ul style='margin:8px 0 0;padding-left:18px;'>
                        <li>Our team will review your booking within 24 hours.</li>
                        <li>You will receive an Approval Email once confirmed.</li>
                        <li>Bring a valid government-issued ID upon check-in.</li>
                    </ul>
                </div>
                <p style='margin:0;'>Questions? Email: <a href='mailto:aviannasinlandresort@gmail.com' style='color:#1e4d40;'>aviannasinlandresort@gmail.com</a></p>
                <p style='margin:4px 0 0;'>Zone 6 Cabugao Sur Sta. Barbara, Iloilo City, Philippines</p>
            </div>
            <div style='background:#0e2a1d;padding:18px;text-align:center;color:rgba(255,255,255,0.5);font-size:0.82rem;'>
                &copy; 2026 Avianna's Inland Resort. All rights reserved.
            </div>
        </div>";

        $mail->AltBody = "Booking Received (Pending). Dear {$name}, guests: {$pax}, entrance fee: {$ef}, check-in: {$checkin}, check-out: {$checkout}, total: {$total}. You will receive an approval email within 24 hours.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Pending email failed: " . $e->getMessage());
        return false;
    }
}

// ── Handle POST ────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name         = trim($_POST['name']           ?? '');
    $email        = trim($_POST['email']          ?? '');
    $contact      = trim($_POST['contact_number'] ?? '');
    $address      = trim($_POST['address']        ?? '');
    $room         = $_POST['room_type']           ?? 'None';
    $cottage      = $_POST['cottage_type']        ?? 'None';
    $pax          = (int)($_POST['pax']           ?? 0);
    $checkin      = $_POST['checkin']             ?? '';
    $checkout     = $_POST['checkout']            ?? '';
    $payment      = $_POST['payment_method']      ?? '';
    $total_price  = 0.00; // calculated server-side below
    $status       = 'Pending';

    if (empty($name) || empty($email) || empty($contact) || empty($address) || empty($checkin) || empty($checkout) || $pax < 1 || empty($payment)) {
        $booking_error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $booking_error = "Please enter a valid email address.";
    } elseif ($pax > 40) {
        $booking_error = "Maximum capacity is 40 guests.";
    } elseif ($checkin < date('Y-m-d', strtotime('today'))) {
        $booking_error = "Check-in date cannot be in the past.";
    } elseif ($checkout < $checkin) {
        $booking_error = "Check-out date cannot be earlier than check-in date.";
    } elseif ($room === 'Tee Pee Hut (overnight)' && $pax > 6) {
        $booking_error = "Tee Pee Hut (Overnight) is limited to a maximum of 6 guests.";
    } elseif ($cottage === 'Cottage 6' && $pax > 12) {
        $booking_error = "Cottage 6 is limited to a maximum of 12 guests (day stay only).";
    } else {
        $room_price    = $prices['room'][$room]       ?? 0;
        $cottage_price = $prices['cottage'][$cottage] ?? 0;

        // Electricity surcharge applies to Cottage 1,2,3 and Cottage 6
        if ($cottage === 'Cottage 1,2,3' || $cottage === 'Cottage 6') {
            $cottage_price += 100;
        }

        // Nights (same-day = 1 night)
        $nights = max(1, (int)round((strtotime($checkout) - strtotime($checkin)) / 86400));

        // Entrance fee: ₱75 per person (one-time, not per night)
        $entrance_fee = $pax * ENTRANCE_FEE;

        // Server-side total (never trust the browser value)
        $total_price = ($room_price * $nights) + $cottage_price + $entrance_fee;

        if (isset($conn)) {
            $stmt = $conn->prepare("
                INSERT INTO bookings
                (name, email, contact, address, room_type, cottage_type, pax, checkin_date, checkout_date, payment_method, total_price, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            // pax stored as the exact guest count (as string; works for VARCHAR or INT columns)
            $pax_str = (string)$pax;

            $stmt->bind_param("ssssssssssds",
                $name, $email, $contact, $address,
                $room, $cottage, $pax_str,
                $checkin, $checkout, $payment,
                $total_price, $status
            );

            if ($stmt->execute()) {
                $booking_success = true;
                sendPendingEmail([
                    'name'          => $name,
                    'email'         => $email,
                    'contact'       => $contact,
                    'address'       => $address,
                    'room'          => $room,
                    'cottage'       => $cottage,
                    'pax'           => $pax,
                    'checkin'       => $checkin,
                    'checkout'      => $checkout,
                    'payment'       => $payment,
                    'total_price'   => $total_price,
                    'room_price'    => $room_price * $nights,
                    'cottage_price' => $cottage_price,
                    'entrance_fee'  => $entrance_fee,
                ]);
            } else {
                error_log("DB Error: " . $stmt->error);
                $booking_error = "There was an error saving your booking. Please try again.";
            }
            $stmt->close();
        } else {
            $booking_error = "Database connection unavailable. Please check conn.php.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Your Stay | Avianna's Inland Resort</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --tropical-green: #1a4731; --accent-gold: #ffc107; --deep-palm: #0e2a1d; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%);
            min-height: 100vh; padding: 40px 15px;
        }
        h1, h2, .resort-title { font-family: 'Playfair Display', serif; }
        .card-booking {
            border: none; border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            padding: 45px; background: white;
        }
        .resort-title { color: var(--tropical-green); font-size: 2rem; }
        .form-label { font-weight: 600; font-size: 0.88rem; color: #444; margin-bottom: 6px; }
        .form-control, .form-select {
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            padding: 11px 14px; font-size: 0.93rem; transition: border-color 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--tropical-green);
            box-shadow: 0 0 0 0.2rem rgba(26,71,49,0.1);
        }
        .btn-confirm {
            background: var(--tropical-green); color: white; border: none;
            border-radius: 12px; padding: 14px; font-size: 1rem;
            font-weight: 600; width: 100%; transition: all 0.3s ease; cursor: pointer;
        }
        .btn-confirm:hover {
            background: var(--deep-palm); transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(26,71,49,0.25);
        }
        .price-display {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 2px solid #86efac; border-radius: 12px;
            padding: 16px 22px; font-size: 1.2rem;
            font-weight: 700; color: var(--tropical-green);
        }
        .success-icon { font-size: 4rem; margin-bottom: 15px; }
        .btn-home-outline {
            border: 2px solid var(--tropical-green); color: var(--tropical-green);
            padding: 10px 30px; border-radius: 12px; font-weight: 600;
            text-decoration: none; display: inline-block; transition: all 0.3s;
        }
        .btn-home-outline:hover { background: var(--tropical-green); color: white; }
        .section-label {
            font-size: .78rem; color: #888; letter-spacing: 1px;
            text-transform: uppercase; font-weight: 700; margin-bottom: 0;
        }
        .section-divider { border: none; border-top: 2px dashed #e2e8f0; margin: 8px 0 4px; }
        @media (max-width: 576px) { .card-booking { padding: 25px 18px; } .resort-title { font-size: 1.5rem; } }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card-booking">

            <?php if ($booking_success): ?>
            <div class="text-center py-4">
                <div class="success-icon">📋</div>
                <h2 class="fw-bold resort-title">Booking Received!</h2>
                <p class="text-muted mb-2">Thank you, <strong><?php echo htmlspecialchars($name); ?></strong>!</p>
                <p class="mb-4" style="max-width:480px;margin:0 auto;">
                    Your reservation is <strong style="color:#d97706;">pending review</strong>.
                    We've sent a confirmation to your email.
                    You'll receive another email once your booking is
                    <strong style="color:#1a4731;">approved</strong> by our team.
                </p>
                <div class="alert alert-warning d-inline-block px-4 py-2 rounded-pill mb-4" style="font-size:.9rem;">
                    ⏳ Approval usually takes within <strong>24 hours</strong>
                </div>
            </div>

            <?php else: ?>
            <h2 class="text-center fw-bold mb-1 resort-title">Reserve Your Stay</h2>
            <p class="text-center text-muted mb-4" style="font-size:.9rem;">
                A confirmation email will be sent immediately. Approval within 2-3 hours.
            </p>

            <?php if ($booking_error): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-3">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($booking_error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="row g-3" novalidate id="bookingForm">

                <div class="col-12"><p class="section-label">👤 Personal Information</p></div>
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Juan Dela Cruz"
                           value="<?php echo htmlspecialchars($name); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="yourname@email.com" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                    <input type="tel" name="contact_number" class="form-control" placeholder="+63 9XX XXX XXXX" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address <span class="text-danger">*</span></label>
                    <input type="text" name="address" class="form-control" placeholder="City, Province" required>
                </div>

                <div class="col-12"><hr class="section-divider"><p class="section-label">🏨 Stay Details / Accommodation</p></div>

                <div class="col-md-6">
                    <label class="form-label">Room / Accommodation Type</label>
                    <select name="room_type" class="form-select" id="roomType">
                        <option value="None" data-price="0">No Accommodation</option>
                        <option value="Tee Pee Hut (overnight)" data-price="2000">Tee Pee Hut (Overnight) — ₱2,000</option>
                        <option value="Tee Pee Hut (day)" data-price="800">Tee Pee Hut (Day) — ₱800</option>
                        <option value="Large Pavillion" data-price="4000">Large Pavilion — ₱4,000</option>
                        <option value="Poolside Pavillion" data-price="2500">Poolside Pavilion — ₱2,500</option>
                        <option value="Pavillion 1" data-price="2000">Pavilion 1 — ₱2,000</option>
                        <option value="Overnight" data-price="2500">Overnight — ₱2,500</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cottage Type</label>
                    <select name="cottage_type" class="form-select" id="cottageType">
                        <option value="None" data-price="0" data-elec="0">No Cottage</option>
                        <option value="Cottage 1,2,3" data-price="600" data-elec="100">Cottage 1, 2, 3 — ₱600 + ₱100 electricity</option>
                        <option value="Cottage 5" data-price="400" data-elec="0">Cottage 5 (Good for 10) — ₱400</option>
                        <option value="Cottage 6" data-price="400" data-elec="100">Cottage 6 (Good for 12, day stay only) — ₱400 + ₱100 electricity</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Number of Guests <span class="text-danger">*</span></label>
                    <input type="number" name="pax" class="form-control" id="paxType"
                           min="1" max="40" step="1" placeholder="e.g. 8" required>
                    <small class="text-muted">Entrance fee: ₱75 per person</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" id="paymentMethod" required>
                        <option value="" disabled selected>Select method</option>
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash</option>
                    </select>
                </div>

                <div class="col-12" id="gcashQrBox" style="display:none;">
                    <div class="text-center p-3" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;">
                        <p class="mb-2 fw-semibold" style="color:#1a4731;">📱 Scan to pay via GCash</p>
                        <img src="img/gcash_qr.jpg" alt="GCash QR Code" id="gcashQrImg"
                             style="width:180px;height:180px;object-fit:contain;border-radius:10px;border:2px solid #86efac;cursor:pointer;background:#fff;padding:8px;"
                             onclick="openQrModal()">
                        <p class="text-muted mt-2 mb-0" style="font-size:.82rem;">
                            Tap the QR code to view it larger. Please keep your payment receipt / reference number — you'll need it for verification at check-in.
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Check-in Date <span class="text-danger">*</span></label>
                    <input type="date" name="checkin" class="form-control" id="checkinDate"
                           required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Check-out Date <span class="text-danger">*</span></label>
                    <input type="date" name="checkout" class="form-control" id="checkoutDate"
                           required min="<?php echo date('Y-m-d'); ?>">
                    <small class="text-muted d-block mt-1">Same-day (day-use) bookings are allowed — checkout can be the same date as check-in.</small>
                </div>

                <div class="col-12">
                    <div class="price-display">
                        <div class="d-flex justify-content-between" style="font-size:.9rem;font-weight:400;">
                            <span>🎟️ Entrance fee (<span id="paxCount">0</span> × ₱75)</span>
                            <span>₱<span id="entranceAmount">0.00</span></span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>💰 Total</span>
                            <span>₱<span id="totalAmount">0.00</span></span>
                        </div>
                    </div>
                    <input type="hidden" name="total_price" id="totalPriceInput" value="0">
                    <small class="text-muted mt-2 d-block">
                        Calculation: (Accommodation rate × nights) + flat cottage rate + (₱75 entrance fee × number of guests).
                        Same-day/day-use bookings are billed as 1 night. Cottages 1,2,3 and 6 include a ₱100 electricity surcharge.
                        All pavilions include free table, chairs, and 1 fan.
                    </small>
                </div>

                <div class="col-12 mt-2">
                    <button type="submit" class="btn-confirm">
                        <i class="bi bi-calendar-check me-2"></i> Confirm Booking
                    </button>
                    <p class="text-center text-muted mt-3" style="font-size:.82rem;">
                        A pending confirmation email will be sent immediately. You'll get an approval email once reviewed.
                    </p>
                </div>
            </form>
            <?php endif; ?>

            <div class="text-center mt-3">
                <a href="index.php" class="text-muted text-decoration-none" style="font-size:.85rem;">← Back to Home</a>
            </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold" style="color:#1a4731;">GCash QR Code</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center pt-2 pb-4">
                <img src="img/gcash_qr.jpg" alt="GCash QR Code" style="width:100%;max-width:320px;border-radius:10px;">
            </div>
        </div>
    </div>
</div>

<script>
const ENTRANCE_FEE = 75; // per person

function calculateTotal() {
    const roomSel    = document.getElementById('roomType');
    const cottageSel = document.getElementById('cottageType');

    const roomPrice     = roomSel ? (parseInt(roomSel.selectedOptions[0].dataset.price) || 0) : 0;
    const cottagePrice  = cottageSel ? (parseInt(cottageSel.selectedOptions[0].dataset.price) || 0) : 0;
    const elecSurcharge = cottageSel ? (parseInt(cottageSel.selectedOptions[0].dataset.elec)  || 0) : 0;

    const pax      = Math.max(0, parseInt(document.getElementById('paxType').value) || 0);
    const entrance = pax * ENTRANCE_FEE;

    const checkin  = document.getElementById('checkinDate').value;
    const checkout = document.getElementById('checkoutDate').value;

    let nights = 1;
    if (checkin && checkout) {
        const diff = Math.round((new Date(checkout) - new Date(checkin)) / 86400000);
        if (diff > 0) nights = diff;
    }

    const total = (roomPrice * nights) + cottagePrice + elecSurcharge + entrance;
    document.getElementById('paxCount').textContent       = pax;
    document.getElementById('entranceAmount').textContent = entrance.toFixed(2);
    document.getElementById('totalAmount').textContent    = total.toFixed(2);
    document.getElementById('totalPriceInput').value      = total.toFixed(2);
}

function handleCheckinChange() {
    const checkinEl  = document.getElementById('checkinDate');
    const checkoutEl = document.getElementById('checkoutDate');
    if (checkinEl.value) {
        const minCheckout = checkinEl.value;
        checkoutEl.min = minCheckout;
        if (checkoutEl.value && checkoutEl.value < checkinEl.value) {
            checkoutEl.value = minCheckout;
        }
    }
    calculateTotal();
}

['roomType','cottageType'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', calculateTotal);
});

const paxEl = document.getElementById('paxType');
if (paxEl) paxEl.addEventListener('input', calculateTotal);

document.getElementById('checkinDate').addEventListener('change', handleCheckinChange);
document.getElementById('checkoutDate').addEventListener('change', calculateTotal);

// ── GCash QR reveal ─────────────────────────────────────────────────────
function toggleGcashQr() {
    const pm  = document.getElementById('paymentMethod');
    const box = document.getElementById('gcashQrBox');
    if (!pm || !box) return;
    box.style.display = (pm.value === 'GCash') ? 'block' : 'none';
}

let qrModal = null;
function openQrModal() {
    if (!qrModal) qrModal = new bootstrap.Modal(document.getElementById('qrModal'));
    qrModal.show();
}

document.getElementById('paymentMethod').addEventListener('change', toggleGcashQr);

window.addEventListener('DOMContentLoaded', () => {
    handleCheckinChange();
    toggleGcashQr();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>