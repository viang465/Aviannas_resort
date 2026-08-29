<?php
session_start();
require_once '../../conn.php';

$message = "";
$error = "";

// Messages passed back from checkin.php / checkout.php after a successful action
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'checked_in') {
        $message = "Guest successfully checked in!";
    } elseif ($_GET['msg'] === 'checked_out') {
        $message = "Guest successfully checked out!";
    }
}
if (isset($_GET['err'])) {
    $error = "Something went wrong processing that request. Please try again.";
}

// Fetch Today's & Active Bookings
$today = date('Y-m-d');
$search = trim($_GET['search'] ?? '');

if (!empty($search)) {
    $search_param = "%{$search}%";
    $search_id = ctype_digit($search) ? intval($search) : 0;
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE name LIKE ? OR email LIKE ? OR id = ? ORDER BY checkin_date ASC");
    $stmt->bind_param("ssi", $search_param, $search_param, $search_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE checkin_date = ? OR status IN ('Approved', 'Checked In') ORDER BY checkin_date ASC");
    $stmt->bind_param("s", $today);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reception Desk | Avianna's Inland Resort</title>
    <link rel="icon"  type="image/png" href="img/avianna.png" >
    
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --tropical-green: #1a4731;
            --accent-gold: #ffc107;
            --deep-palm: #0e2a1d;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f9;
        }
        .navbar-reception {
            background-color: var(--tropical-green);
        }
        .card-stat {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .badge-approved { background-color: #d1fae5; color: #065f46; }
        .badge-pending { background-color: #fef3c7; color: #92400e; }
        .badge-checkedin { background-color: #dbeafe; color: #1e40af; }
        .badge-checkedout { background-color: #e5e7eb; color: #374151; }
        .badge-paid { background-color: #d1fae5; color: #065f46; }
        .badge-unpaid { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<!-- Reception Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-reception sticky-top shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-bell-fill me-2 text-warning"></i> Reception Desk
        </a>
        <div class="d-flex align-items-center">
            <span class="text-light me-3 small"><i class="bi bi-calendar3 me-1"></i> Today: <?php echo date('M d, Y'); ?></span>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="../admin.php" class="btn btn-sm btn-outline-warning rounded-pill me-2">
                    <i class="bi bi-speedometer2 me-1"></i> Admin Panel
                </a>
            <?php endif; ?>
            <a href="../index.php" class="btn btn-sm btn-outline-light rounded-pill"><i class="bi bi-box-arrow-right me-1"></i> Exit</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">

    <!-- Notifications -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Header Tools -->
    <div class="row align-items-center mb-4">
        <div class="col-md-6 mb-2 mb-md-0">
            <h3 class="fw-bold text-dark mb-0">Front Desk Manager</h3>
            <p class="text-muted small mb-0">Manage today's arrivals, active guests, and quick check-ins.</p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="checkin.php" class="btn btn-outline-success fw-semibold rounded-3 me-2">
                <i class="bi bi-person-plus-fill me-1"></i> Walk-In Registration
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="card card-stat mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" 
                               placeholder="Search guest name, email, or Booking ID..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary fw-semibold">Search Guest</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Guest Table -->
    <div class="card card-stat">
        <div class="card-header bg-white py-3 border-0">
            <h5 class="fw-bold mb-0 text-dark">
                <?php echo !empty($search) ? 'Search Results' : "Today's Arrivals, Approved Bookings & Active Stays"; ?>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Guest Name</th>
                        <th>Accommodation</th>
                        <th>Check-In / Out</th>
                        <th>Total Fee</th>
                        <th>Status</th>
                        <th>Payment Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($row['id']); ?></strong></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></div>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($row['contact']); ?></small>
                                </td>
                                <td>
                                    <div><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['room_type']); ?></span></div>
                                    <?php if (isset($row['cottage_type']) && $row['cottage_type'] !== 'None'): ?>
                                        <small class="text-muted">+ <?php echo htmlspecialchars($row['cottage_type']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="d-block"><strong>In:</strong> <?php echo date('M d, Y', strtotime($row['checkin_date'])); ?></small>
                                    <small class="d-block text-muted"><strong>Out:</strong> <?php echo date('M d, Y', strtotime($row['checkout_date'])); ?></small>
                                </td>
                                <td>
                                    <strong class="text-success">₱<?php echo number_format($row['total_price'], 2); ?></strong>
                                    <div class="small text-muted"><?php echo htmlspecialchars($row['payment_method'] ?? 'N/A'); ?></div>
                                </td>
                                <td>
                                    <?php 
                                        $st = $row['status'];
                                        $badgeClass = 'badge-pending';
                                        if ($st === 'Approved') $badgeClass = 'badge-approved';
                                        elseif ($st === 'Checked In') $badgeClass = 'badge-checkedin';
                                        elseif ($st === 'Checked Out') $badgeClass = 'badge-checkedout';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?> px-2 py-1"><?php echo htmlspecialchars($st); ?></span>
                                </td>
                                <td>
                                    <?php 
                                        $payStatus = $row['payment_status'] ?? 'Pending';
                                        $payBadgeClass = ($payStatus === 'Paid') ? 'badge-paid' : 'badge-unpaid';
                                        $redirectTarget = 'index.php' . (!empty($search) ? ('?search=' . urlencode($search)) : '');
                                    ?>
                                    <form method="POST" action="update_payment_status.php" class="d-inline-flex align-items-center gap-1">
                                        <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirectTarget); ?>">
                                        <select name="payment_status" class="form-select form-select-sm <?php echo $payBadgeClass; ?> border-0 fw-semibold"
                                                style="width:auto; padding-right:1.75rem;" onchange="this.form.submit()">
                                            <option value="Pending" <?php echo $payStatus === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Paid" <?php echo $payStatus === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="text-end">
                                    <?php if ($row['status'] === 'Approved' || $row['status'] === 'Pending'): ?>
                                        <a href="checkin.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success rounded-pill px-3">
                                            <i class="bi bi-box-arrow-in-right me-1"></i> Check In
                                        </a>
                                    <?php elseif ($row['status'] === 'Checked In'): ?>
                                        <a href="checkout.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning rounded-pill px-3">
                                            <i class="bi bi-box-arrow-right me-1"></i> Check Out
                                        </a>
                                    <?php else: ?>
                                        <a href="print_receipt.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                            <i class="bi bi-receipt me-1"></i> View Receipt
                                        </a>
                                    <?php endif; ?>
                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                        <a href="../admin/manage_booking.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 ms-1">
                                            <i class="bi bi-pencil-square me-1"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x display-5 d-block mb-2"></i>
                                No arrivals or active stays found for today.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>