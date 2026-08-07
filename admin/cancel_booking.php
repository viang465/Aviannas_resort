<?php
session_start();
include "../conn.php"; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$sql = "SELECT * FROM deleted_bookings ORDER BY deletion_date DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancellation History | Avianna's Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { 
            --primary-green: #1e4d40; 
            --accent-teal: #2c7a7b; 
            --sidebar-width: 260px;
            --bg-light: #fcfcfc;
            --cancel-red: #c53030;
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

        .header-box {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            border-left: 6px solid var(--cancel-red);
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            margin-bottom: 30px;
        }

        .header-box h2 {
            color: var(--primary-green);
            font-weight: 700;
            margin: 0;
        }

        .table-card { 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            overflow: hidden;
        }

        .table thead th {
            background-color: #fdfdfd;
            color: #8898aa;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 20px;
            border-bottom: 1px solid #f1f1f1;
        }

        .table tbody td {
            padding: 20px;
            border-bottom: 1px solid #f8f9fa;
        }

        .guest-name { color: #32325d; font-weight: 600; display: block; }

        .stay-period {
            background: #f4f6f9;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #525f7f;
        }

        .badge-cancelled { 
            background: #fff5f5; 
            color: var(--cancel-red); 
            font-weight: 600; 
            padding: 6px 12px; 
            border-radius: 6px; 
            font-size: 0.8rem;
            border: 1px solid #feb2b2;
        }

        .text-muted-small { font-size: 0.8rem; color: #8898aa; }

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
        <a class="nav-link" href="approve.php"><span>Approved History</span></a>
        <a class="nav-link active" href="admin_cancelled.php"><span>Cancellation History</span></a>
        <a class="nav-link" href="admin_announcements.php"><span>Announcements</span></a>
        <a class="nav-link" href="admin_analytics.php"><span>Dashboard</span></a>
        <hr style="border-color: rgba(255,255,255,0.1); margin: 20px 0;">
        <a class="nav-link text-info" href="../reception/index.php"><span>🛎 Front Desk</span></a>
        <a class="nav-link text-warning" href="../index.php" target="_blank"><span>← View Website</span></a>
        <a class="nav-link text-danger" href="logout.php"><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <div class="header-box">
        <h2>Cancellation History</h2>
        <p class="text-muted mb-0 small">Archived records of deleted or cancelled reservations.</p>
    </div>

    <div class="table-card">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Guest Information</th>
                    <th>Room Type</th>
                    <th>Booking Dates</th>
                    <th>Deletion Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <span class="guest-name"><?php echo htmlspecialchars($row['name']); ?></span>
                            <span class="text-muted-small"><?php echo htmlspecialchars($row['email']); ?></span>
                        </td>
                        <td>
                            <span class="text-muted-small"><?php echo htmlspecialchars($row['contact'] ?? 'N/A'); ?></span>
                        </td>
                        <td>
                            <span class="fw-medium"><?php echo htmlspecialchars($row['room_type']); ?></span>
                        </td>
                        <td>
                            <span class="stay-period">
                                <?php echo date('M d', strtotime($row['checkin_date'])) . " — " . date('M d, Y', strtotime($row['checkout_date'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-cancelled">
                                🗑 <?php echo date('M d, Y | h:i A', strtotime($row['deletion_date'])); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center py-5">
                            <div class="text-muted">
                                <p class="mb-0">The archive is currently empty.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>