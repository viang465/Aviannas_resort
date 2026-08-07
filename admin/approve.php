<?php
session_start();
include "../conn.php"; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Show the full confirmed pipeline — Approved, Checked In, and Checked Out —
// so a booking doesn't disappear from history the moment reception acts on it.
$sql = "SELECT * FROM bookings WHERE status IN ('Approved', 'Checked In', 'Checked Out') ORDER BY checkin_date DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approved History | Avianna's Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { 
            --primary-green: #1e4d40; 
            --accent-teal: #2c7a7b; 
            --sidebar-width: 260px;
            --bg-light: #f4f7f6;
            --success-green: #27ae60;
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

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-left: 6px solid var(--success-green);
            padding-left: 20px;
        }

        .header-section h2 {
            color: var(--primary-green);
            font-weight: 700;
            margin: 0;
        }

        .table-card { 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            overflow: hidden;
            border: none;
        }

        .table thead th {
            background-color: #f8f9fa;
            color: #6c757d;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 20px;
            border: none;
        }

        .table tbody td {
            padding: 20px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .guest-info strong { display: block; color: #2d3748; }
        .guest-info small { color: #718096; }

        .status-approved { 
            color: var(--success-green); 
            font-weight: 600; 
            background: #eefdf5; 
            padding: 6px 16px; 
            border-radius: 50px; 
            font-size: 0.85rem;
            border: 1px solid #c6f6d5;
            display: inline-block;
        }

        .status-checkedin {
            color: #1e40af;
            font-weight: 600;
            background: #dbeafe;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            border: 1px solid #bfdbfe;
            display: inline-block;
        }

        .status-checkedout {
            color: #374151;
            font-weight: 600;
            background: #e5e7eb;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            border: 1px solid #d1d5db;
            display: inline-block;
        }

        .date-badge {
            background: #edf2f7;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #4a5568;
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
        <a class="nav-link" href="admin.php"><span>Pending Bookings</span></a>
        <a class="nav-link active" href="approve.php"><span>Approved History</span></a>
        <a class="nav-link" href="admin_cancelled.php"><span>Cancellation History</span></a>
        <a class="nav-link" href="admin_announcements.php"><span>Announcements</span></a>
        <a class="nav-link" href="admin_analytics.php"><span>Dashboard</span></a>
        <hr style="border-color: rgba(255,255,255,0.1); margin: 20px 0;">
        <a class="nav-link text-info" href="../reception/index.php"><span>🛎 Front Desk</span></a>
        <a class="nav-link text-warning" href="../index.php" target="_blank"><span>← View Website</span></a>
        <a class="nav-link text-danger" href="logout.php"><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <div class="header-section">
        <div>
            <h2>Confirmed Reservations</h2>
            <p class="text-muted mb-0">Record of all successfully validated bookings.</p>
        </div>
        <button class="btn btn-outline-success btn-sm" onclick="window.print()">Print Report</button>
    </div>

    <div class="table-card">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Guest Details</th>
                    <th>Room Type</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Final Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td class="guest-info">
                            <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                            <small><?php echo htmlspecialchars($row['email']); ?></small>
                        </td>
                        <td>
                            <span class="fw-medium text-dark"><?php echo htmlspecialchars($row['room_type']); ?></span>
                        </td>
                        <td>
                            <span class="date-badge"><?php echo date('M d, Y', strtotime($row['checkin_date'])); ?></span>
                        </td>
                        <td>
                            <span class="date-badge"><?php echo date('M d, Y', strtotime($row['checkout_date'])); ?></span>
                        </td>
                        <td>
                            <?php if ($row['status'] === 'Checked In'): ?>
                                <span class="status-checkedin">🔑 Checked In</span>
                            <?php elseif ($row['status'] === 'Checked Out'): ?>
                                <span class="status-checkedout">✅ Checked Out</span>
                            <?php else: ?>
                                <span class="status-approved">✔ Approved</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-outline-primary btn-sm px-3 me-2 btn-view-details"
                                    data-booking='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>'>
                                View
                            </button>
                            <?php if ($row['status'] === 'Checked In' || $row['status'] === 'Checked Out'): ?>
                                <a href="../reception/print_receipt.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-secondary btn-sm px-3" target="_blank">
                                    <i class="bi bi-receipt"></i> Receipt
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            No approved history found in the database.
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