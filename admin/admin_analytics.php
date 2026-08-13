<?php
session_start();
include "../conn.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// ---- CSV Export ----
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportSql = "SELECT id, name, email, contact, address, room_type, checkin_date, checkout_date, payment_method, total_price, status FROM bookings ORDER BY checkin_date DESC";
    $exportRes = $conn->query($exportSql);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=bookings_export_' . date('Y-m-d') . '.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Name', 'Email', 'Contact', 'Address', 'Room Type', 'Check-in', 'Check-out', 'Payment Method', 'Total Price', 'Status']);

    if ($exportRes) {
        while ($row = $exportRes->fetch_assoc()) {
            fputcsv($out, [
                $row['id'],
                $row['name'],
                $row['email'],
                $row['contact'],
                $row['address'],
                $row['room_type'],
                $row['checkin_date'],
                $row['checkout_date'],
                $row['payment_method'],
                $row['total_price'],
                $row['status'] ?: 'Pending'
            ]);
        }
    }

    fclose($out);
    exit();
}

// ---- Core stat counts (from bookings table) ----
$counts = [
    'pending'     => 0,
    'approved'    => 0,
    'checkedin'   => 0,
    'checkedout'  => 0,
    'total'       => 0,
];

$statusSql = "SELECT
                SUM(CASE WHEN status = 'Pending' OR status IS NULL THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN status = 'Checked In' THEN 1 ELSE 0 END) AS checkedin,
                SUM(CASE WHEN status = 'Checked Out' THEN 1 ELSE 0 END) AS checkedout,
                COUNT(*) AS total
              FROM bookings";
if ($res = $conn->query($statusSql)) {
    if ($row = $res->fetch_assoc()) {
        $counts['pending']    = (int) $row['pending'];
        $counts['approved']   = (int) $row['approved'];
        $counts['checkedin']  = (int) $row['checkedin'];
        $counts['checkedout'] = (int) $row['checkedout'];
        $counts['total']      = (int) $row['total'];
    }
}

// ---- Cancellations (archived table) ----
$cancelledCount = 0;
if ($res = $conn->query("SELECT COUNT(*) AS c FROM deleted_bookings")) {
    if ($row = $res->fetch_assoc()) $cancelledCount = (int) $row['c'];
}

// ---- Revenue (confirmed pipeline only) ----
$totalRevenue = 0;
if ($res = $conn->query("SELECT COALESCE(SUM(total_price),0) AS rev FROM bookings WHERE status IN ('Approved','Checked In','Checked Out')")) {
    if ($row = $res->fetch_assoc()) $totalRevenue = (float) $row['rev'];
}

// ---- Room type breakdown ----
$roomLabels = [];
$roomCounts = [];
if ($res = $conn->query("SELECT room_type, COUNT(*) AS c FROM bookings GROUP BY room_type ORDER BY c DESC")) {
    while ($row = $res->fetch_assoc()) {
        $roomLabels[] = $row['room_type'];
        $roomCounts[] = (int) $row['c'];
    }
}

// ---- Bookings trend: last 6 months (by check-in month) ----
$trendLabels = [];
$trendCounts = [];
$monthBuckets = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i months"));
    $monthBuckets[$key] = 0;
    $trendLabels[] = date('M Y', strtotime("-$i months"));
}
$trendSql = "SELECT DATE_FORMAT(checkin_date, '%Y-%m') AS ym, COUNT(*) AS c
             FROM bookings
             WHERE checkin_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY ym";
if ($res = $conn->query($trendSql)) {
    while ($row = $res->fetch_assoc()) {
        if (isset($monthBuckets[$row['ym']])) {
            $monthBuckets[$row['ym']] = (int) $row['c'];
        }
    }
}
$trendCounts = array_values($monthBuckets);

// ---- This month's revenue (confirmed pipeline, by check-in month) ----
$thisMonthRevenue = 0;
$thisMonthLabel = date('F Y');
$monthRevSql = "SELECT COALESCE(SUM(total_price),0) AS rev
                FROM bookings
                WHERE status IN ('Approved','Checked In','Checked Out')
                  AND MONTH(checkin_date) = MONTH(CURDATE())
                  AND YEAR(checkin_date) = YEAR(CURDATE())";
if ($res = $conn->query($monthRevSql)) {
    if ($row = $res->fetch_assoc()) $thisMonthRevenue = (float) $row['rev'];
}

// ---- Weekly breakdown: browsable by month + year (confirmed pipeline) ----
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$selectedMonth = isset($_GET['wb_month']) ? max(1, min(12, (int) $_GET['wb_month'])) : (int) date('n');
$selectedYear  = isset($_GET['wb_year'])  ? (int) $_GET['wb_year']  : (int) date('Y');

// Years available in the data, so the dropdown always covers real bookings (plus the current year)
$availableYears = [(int) date('Y')];
if ($res = $conn->query("SELECT DISTINCT YEAR(checkin_date) AS y FROM bookings WHERE checkin_date IS NOT NULL ORDER BY y DESC")) {
    while ($row = $res->fetch_assoc()) {
        $y = (int) $row['y'];
        if ($y && !in_array($y, $availableYears, true)) $availableYears[] = $y;
    }
}
rsort($availableYears);

$weeklyBreakdown = []; // week number => ['count' => n, 'revenue' => n, 'start' => DateTime, 'end' => DateTime]
$daysInSelectedMonth = (int) date('t', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));

$weekSql = "SELECT checkin_date, total_price
            FROM bookings
            WHERE status IN ('Approved','Checked In','Checked Out')
              AND MONTH(checkin_date) = ?
              AND YEAR(checkin_date) = ?";
$weekStmt = $conn->prepare($weekSql);
$weekStmt->bind_param("ii", $selectedMonth, $selectedYear);
$weekStmt->execute();
$weekRes = $weekStmt->get_result();
while ($row = $weekRes->fetch_assoc()) {
    $day = (int) date('j', strtotime($row['checkin_date']));
    $week = (int) ceil($day / 7);
    if (!isset($weeklyBreakdown[$week])) {
        $startDay = ($week - 1) * 7 + 1;
        $endDay   = min($week * 7, $daysInSelectedMonth);
        $weeklyBreakdown[$week] = [
            'count'   => 0,
            'revenue' => 0,
            'start'   => mktime(0, 0, 0, $selectedMonth, $startDay, $selectedYear),
            'end'     => mktime(0, 0, 0, $selectedMonth, $endDay, $selectedYear),
        ];
    }
    $weeklyBreakdown[$week]['count']++;
    $weeklyBreakdown[$week]['revenue'] += (float) $row['total_price'];
}
$weekStmt->close();
ksort($weeklyBreakdown);

$selectedMonthLabel = $monthNames[$selectedMonth] . ' ' . $selectedYear;

// ---- Top guest locations (from address field) ----
$topLocations = [];
$locSql = "SELECT address, COUNT(*) AS c
           FROM bookings
           WHERE address IS NOT NULL AND address != ''
           GROUP BY address
           ORDER BY c DESC
           LIMIT 5";
if ($res = $conn->query($locSql)) {
    while ($row = $res->fetch_assoc()) {
        $topLocations[] = $row;
    }
}

// ---- Recent activity (latest 6 bookings) ----
$recent = [];
if ($res = $conn->query("SELECT id, name, room_type, status, checkin_date FROM bookings ORDER BY id DESC LIMIT 6")) {
    while ($row = $res->fetch_assoc()) $recent[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Avianna's Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

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

        .sidebar h4 { font-weight: 700; text-align: center; margin-bottom: 30px; }

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

        .nav-link:hover { color: white; background: rgba(255,255,255,0.1); transform: translateX(5px); }
        .nav-link.active { color: white; background: var(--accent-teal); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }

        .main-content { margin-left: var(--sidebar-width); padding: 40px; min-height: 100vh; }

        .header-title {
            color: var(--primary-green);
            border-left: 6px solid var(--accent-teal);
            padding-left: 20px;
            font-weight: 700;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            padding: 24px;
            height: 100%;
        }

        .stat-icon {
            width: 50px; height: 50px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 14px;
        }

        .stat-value { font-size: 1.8rem; font-weight: 700; color: #2d3748; margin: 0; }
        .stat-label { color: #718096; font-size: 0.85rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }

        .bg-pending  { background: #fffbea; color: #b7791f; }
        .bg-approved { background: #eefdf5; color: #27ae60; }
        .bg-checkin  { background: #dbeafe; color: #1e40af; }
        .bg-checkout { background: #e5e7eb; color: #374151; }
        .bg-cancel   { background: #fff5f5; color: #c53030; }
        .bg-revenue  { background: #e6fffa; color: #234e52; }

        .table-card, .chart-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            padding: 25px;
        }

        .table thead th {
            border: none;
            color: #6c757d;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 14px;
        }

        .table tbody td { padding: 14px; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }

        .status-pill {
            font-weight: 600;
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 0.78rem;
            display: inline-block;
        }
        .pill-Pending  { background: #fffbea; color: #b7791f; border: 1px solid #fbd38d; }
        .pill-Approved { background: #eefdf5; color: #27ae60; border: 1px solid #c6f6d5; }
        .pill-Checkedin  { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .pill-Checkedout { background: #e5e7eb; color: #374151; border: 1px solid #d1d5db; }

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
        <a class="nav-link" href="admin_cancelled.php"><span>Cancellation History</span></a>
        <a class="nav-link" href="admin_announcements.php"><span>Announcements</span></a>
        <a class="nav-link active" href="admin_analytics.php"><span>Dashboard</span></a>
        <hr style="border-color: rgba(255,255,255,0.1); margin: 20px 0;">
        <a class="nav-link text-info" href="../admin/reception/index.php"><span>🛎 Front Desk</span></a>
        <a class="nav-link text-warning" href="../index.php" target="_blank"><span>← View Website</span></a>
        <a class="nav-link text-danger" href="logout.php"><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="header-title mb-0">Dashboard Overview</h2>
        <a href="admin_analytics.php?export=csv" class="btn btn-custom" style="background-color: var(--primary-green); color: white; border-radius: 8px; padding: 10px 20px; font-weight: 600; text-decoration: none;">
            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
        </a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-6 col-lg-2">
            <div class="stat-card">
                <div class="stat-icon bg-revenue"><i class="bi bi-cash-stack"></i></div>
                <p class="stat-value">₱<?php echo number_format($totalRevenue, 0); ?></p>
                <span class="stat-label">Total Revenue</span>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="stat-card">
                <div class="stat-icon bg-revenue"><i class="bi bi-calendar2-week"></i></div>
                <p class="stat-value">₱<?php echo number_format($thisMonthRevenue, 0); ?></p>
                <span class="stat-label"><?php echo htmlspecialchars($thisMonthLabel); ?></span>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="stat-card">
                <div class="stat-icon bg-pending"><i class="bi bi-hourglass-split"></i></div>
                <p class="stat-value"><?php echo $counts['pending']; ?></p>
                <span class="stat-label">Pending</span>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="stat-card">
                <div class="stat-icon bg-approved"><i class="bi bi-check-circle"></i></div>
                <p class="stat-value"><?php echo $counts['approved']; ?></p>
                <span class="stat-label">Approved</span>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="stat-card">
                <div class="stat-icon bg-checkin"><i class="bi bi-key"></i></div>
                <p class="stat-value"><?php echo $counts['checkedin']; ?></p>
                <span class="stat-label">Checked In</span>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="stat-card">
                <div class="stat-icon bg-checkout"><i class="bi bi-door-open"></i></div>
                <p class="stat-value"><?php echo $counts['checkedout']; ?></p>
                <span class="stat-label">Checked Out</span>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="stat-card">
                <div class="stat-icon bg-cancel"><i class="bi bi-x-circle"></i></div>
                <p class="stat-value"><?php echo $cancelledCount; ?></p>
                <span class="stat-label">Cancelled</span>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="chart-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <h6 class="fw-bold text-dark mb-0">📅 Weekly Breakdown — <?php echo htmlspecialchars($selectedMonthLabel); ?></h6>
                    <form method="GET" class="d-flex gap-2">
                        <select name="wb_month" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                            <?php foreach ($monthNames as $num => $label): ?>
                                <option value="<?php echo $num; ?>" <?php echo $num === $selectedMonth ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="wb_year" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                            <?php foreach ($availableYears as $y): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y === $selectedYear ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Go</button></noscript>
                    </form>
                </div>
                <?php if (!empty($weeklyBreakdown)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th class="text-muted small text-uppercase">Week</th>
                                    <th class="text-muted small text-uppercase">Date Range</th>
                                    <th class="text-muted small text-uppercase">Bookings</th>
                                    <th class="text-muted small text-uppercase">Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($weeklyBreakdown as $weekNum => $data): ?>
                                    <tr>
                                        <td class="fw-medium">Week <?php echo $weekNum; ?></td>
                                        <td class="text-muted">
                                            <?php echo date('M j, Y', $data['start']); ?> – <?php echo date('M j, Y', $data['end']); ?>
                                        </td>
                                        <td><?php echo $data['count']; ?></td>
                                        <td>₱<?php echo number_format($data['revenue'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No approved bookings found for <?php echo htmlspecialchars($selectedMonthLabel); ?>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="chart-card">
                <h6 class="fw-bold text-dark mb-3">📍 Top Guest Locations</h6>
                <?php if (!empty($topLocations)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th class="text-muted small text-uppercase">Address</th>
                                    <th class="text-muted small text-uppercase">Bookings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topLocations as $loc): ?>
                                    <tr>
                                        <td class="fw-medium"><?php echo htmlspecialchars($loc['address']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo (int) $loc['c']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No guest address data available yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="chart-card h-100">
                <h6 class="fw-bold text-dark mb-3">Bookings Trend (Last 6 Months)</h6>
                <canvas id="trendChart" height="140"></canvas>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="chart-card h-100">
                <h6 class="fw-bold text-dark mb-3">Bookings by Room Type</h6>
                <canvas id="roomChart" height="140"></canvas>
            </div>
        </div>
    </div>

    <div class="table-card">
        <h6 class="fw-bold text-dark mb-3">Recent Bookings</h6>
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Guest</th>
                    <th>Room Type</th>
                    <th>Check-in</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recent)): ?>
                    <?php foreach ($recent as $r): ?>
                        <?php
                            $status = $r['status'] ?: 'Pending';
                            $pillClass = 'pill-' . str_replace(' ', '', $status);
                        ?>
                        <tr>
                            <td class="fw-medium"><?php echo htmlspecialchars($r['name']); ?></td>
                            <td><?php echo htmlspecialchars($r['room_type']); ?></td>
                            <td class="text-muted"><?php echo date('M d, Y', strtotime($r['checkin_date'])); ?></td>
                            <td><span class="status-pill <?php echo $pillClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No bookings yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const trendCtx = document.getElementById('trendChart');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trendLabels); ?>,
            datasets: [{
                label: 'Bookings',
                data: <?php echo json_encode($trendCounts); ?>,
                borderColor: '#2c7a7b',
                backgroundColor: 'rgba(44, 122, 123, 0.12)',
                tension: 0.35,
                fill: true,
                pointBackgroundColor: '#1e4d40',
                pointRadius: 4
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    const roomCtx = document.getElementById('roomChart');
    new Chart(roomCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($roomLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($roomCounts); ?>,
                backgroundColor: ['#1e4d40', '#2c7a7b', '#4fd1c5', '#b7791f', '#c53030', '#1e40af']
            }]
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } } }
        }
    });
</script>

</body>
</html>