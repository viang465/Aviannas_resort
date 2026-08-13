<?php
session_start();
include "../conn.php"; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Only bookings actually awaiting an admin decision — bookings that have
// moved into the reception flow (Checked In / Checked Out) belong on the
// Approved History page instead, not here.
$sql = "SELECT * FROM bookings WHERE status = 'Pending' OR status IS NULL ORDER BY checkin_date ASC";
$result = $conn->query($sql);

$bannerMsg = "";
if (isset($_GET['booking']) && $_GET['booking'] === 'created') $bannerMsg = "Booking created and guest notified.";
if (isset($_GET['booking']) && $_GET['booking'] === 'updated') $bannerMsg = "Booking updated and guest notified.";
if (isset($_GET['cancel']) && $_GET['cancel'] === 'success') $bannerMsg = "Booking archived and guest notified of cancellation.";
if (isset($_GET['approve']) && $_GET['approve'] === 'success') $bannerMsg = "Booking approved and guest notified.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Bookings | Avianna's Admin</title>
    
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

        body { 
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            margin: 0;
        }

        #scrollUp, .scroll-to-top, .back-to-top, [id*="scroll"], .tp-top-arrow, button[title*="top"], .scrollup {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        .sidebar { 
            height: 100vh; 
            background: linear-gradient(180deg, var(--primary-green) 0%, #0a1a16 100%); 
            color: white; 
            position: fixed; 
            width: var(--sidebar-width); 
            padding: 25px 20px;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .sidebar h4 {
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
        }

        .nav-link { 
            color: rgba(255,255,255,0.7); 
            margin-bottom: 8px; 
            padding: 12px 15px;
            border-radius: 8px; 
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
        }

        .nav-link:hover { 
            color: white; 
            background: rgba(255,255,255,0.1); 
            transform: translateX(5px);
        }

        .nav-link.active { 
            color: white; 
            background: var(--accent-teal); 
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .main-content { 
            margin-left: var(--sidebar-width); 
            padding: 40px; 
            min-height: 100vh; 
        }

        .header-title {
            color: var(--primary-green); 
            border-left: 6px solid var(--accent-teal); 
            padding-left: 20px;
            font-weight: 700;
            margin-bottom: 30px;
        }

        .table-card { 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            overflow: hidden;
            border: none;
        }

        .table thead { background-color: #f8f9fa; }

        .table thead th {
            border: none;
            color: #6c757d;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 20px;
        }

        .table tbody td {
            padding: 20px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .guest-name { display: block; font-weight: 600; color: #2d3748; }
        .guest-email { font-size: 0.85rem; color: #718096; }

        .badge-payment {
            background: #e6fffa;
            color: #234e52;
            border: 1px solid #b2f5ea;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 6px;
        }

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
        <a class="nav-link" href="admin_analytics.php"><span>Dashboard</span></a>
        <hr style="border-color: rgba(255,255,255,0.1); margin: 20px 0;">
        <a class="nav-link text-info" href="../admin/reception/index.php"><span>🛎 Front Desk</span></a>
        <a class="nav-link text-warning" href="../index.php" target="_blank"><span>← View Website</span></a>
        <a class="nav-link text-danger" href="logout.php"><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="header-title">Pending Reservations</h2>
        <a href="manage_booking.php" class="btn btn-custom mb-3" style="background-color: var(--primary-green); color: white; border-radius: 8px; padding: 10px 20px; font-weight: 600; text-decoration: none;">
            <i class="bi bi-plus-circle"></i> New Booking
        </a>
    </div>

    <?php if (!empty($bannerMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($bannerMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="table-card">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Guest Details</th>
                    <th>Contact Number</th>
                    <th>Room Type</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <span class="guest-name"><?php echo htmlspecialchars($row['name']); ?></span>
                            <span class="guest-email"><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></span>
                        </td>
                        <td>
                            <span class="guest-email"><?php echo htmlspecialchars($row['contact'] ?? 'N/A'); ?></span>
                        </td>
                        <td>
                            <span class="fw-medium"><?php echo htmlspecialchars($row['room_type']); ?></span>
                        </td>
                        <td>
                            <span class="text-muted"><?php echo date('M d, Y', strtotime($row['checkin_date'])); ?></span>
                        </td>
                        <td>
                            <span class="text-muted"><?php echo date('M d, Y', strtotime($row['checkout_date'])); ?></span>
                        </td>
                        <td>
                            <span class="badge-payment">
                                <?php echo htmlspecialchars($row['payment_method'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-outline-primary btn-sm px-3 me-2 btn-view-details"
                                        data-booking='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>'>
                                    View
                                </button>
                                <a href="edit_booking.php?id=<?php echo $row['id']; ?>" class="btn btn-success btn-sm px-3 me-2">Approve</a>
                                <a href="manage_booking.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-secondary btn-sm px-3 me-2">Edit</a>
                                <form action="cancel_booking.php" method="POST" onsubmit="return confirm('Archive this booking?');" style="margin:0;">
                                    <input type="hidden" name="booking_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm px-3">Archive</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/4076/4076549.png" width="60" class="opacity-25 mb-3" alt="empty">
                            <p class="text-muted mb-0">No pending reservations found.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Customer Details Modal -->
<div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: var(--primary-green); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title fw-bold">Guest & Booking Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="customerDetailsBody">
                <!-- Populated via JS -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    document.querySelectorAll('.btn-view-details').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const b = JSON.parse(this.getAttribute('data-booking'));
            const rows = [
                ['Guest Name', b.name],
                ['Email', b.email],
                ['Address', b.address || 'N/A'],
                ['Room Type', b.room_type],
                ['Check-in', b.checkin_date],
                ['Check-out', b.checkout_date || 'N/A'],
                ['Payment Method', b.payment_method || 'N/A'],
                ['Total Price', b.total_price ? '₱' + Number(b.total_price).toLocaleString(undefined, {minimumFractionDigits: 2}) : 'N/A'],
                ['Status', b.status || 'Pending']
            ];
            document.getElementById('customerDetailsBody').innerHTML = rows.map(function (r) {
                return '<div class="d-flex justify-content-between py-2 border-bottom">' +
                       '<span class="text-muted fw-medium">' + escapeHtml(r[0]) + '</span>' +
                       '<span class="fw-semibold text-dark text-end">' + escapeHtml(r[1]) + '</span>' +
                       '</div>';
            }).join('');
            new bootstrap.Modal(document.getElementById('customerDetailsModal')).show();
        });
    });
</script>

</body>
</html>