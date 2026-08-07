<?php
session_start();
include "../conn.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Selected month/year for the weekly breakdown + trend chart (defaults to current month)
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : (int)date('n');
$selectedYear  = isset($_GET['year']) ? intval($_GET['year']) : (int)date('Y');

if ($selectedMonth < 1 || $selectedMonth > 12) $selectedMonth = (int)date('n');
if ($selectedYear < 2000 || $selectedYear > 2100) $selectedYear = (int)date('Y');

// Earliest year with data, for the year dropdown
$earliestYear = (int)date('Y');
$res = $conn->query("SELECT MIN(YEAR(checkin_date)) as min_year FROM bookings");
if ($res && $row = $res->fetch_assoc()) {
    if (!empty($row['min_year'])) $earliestYear = min($earliestYear, (int)$row['min_year']);
}

if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Avianna_Report_' . $selectedYear . '-' . str_pad($selectedMonth, 2, '0', STR_PAD_LEFT) . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Week', 'Bookings', 'Revenue (PHP)']);

    $exportStats = $conn->prepare("
        SELECT FLOOR((DAY(checkin_date)-1)/7)+1 as week_num,
               COUNT(*) as total_bookings,
               SUM(total_price) as week_inflow
        FROM bookings
        WHERE MONTH(checkin_date) = ?
          AND YEAR(checkin_date) = ?
          AND status IN ('Approved', 'Checked In', 'Checked Out')
        GROUP BY week_num ORDER BY week_num ASC
    ");
    $exportStats->bind_param("ii", $selectedMonth, $selectedYear);
    $exportStats->execute();
    $exportResult = $exportStats->get_result();
    if ($exportResult && $exportResult->num_rows > 0) {
        while ($row = $exportResult->fetch_assoc()) {
            $inflow = $row['week_inflow'] ?? 0;
            
            fputcsv($output, [
                'Week ' . $row['week_num'],
                $row['total_bookings'],
                number_format($inflow, 2, '.', '')
            ]);
        }
    }
    fclose($output);
    exit();
}

$totalActive = 0;
$res = $conn->query("SELECT COUNT(*) as total FROM bookings");
if ($res) $totalActive = $res->fetch_assoc()['total'] ?? 0;

$pendingCount = 0;
$res = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'Pending' OR status IS NULL");
if ($res) $pendingCount = $res->fetch_assoc()['total'] ?? 0;

$approvedCount = 0;
$res = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE status IN ('Approved', 'Checked In', 'Checked Out')");
if ($res) $approvedCount = $res->fetch_assoc()['total'] ?? 0;

$archivedCount = 0;
$res = $conn->query("SELECT COUNT(*) as total FROM deleted_bookings");
if ($res) $archivedCount = $res->fetch_assoc()['total'] ?? 0;

$totalRevenue = 0;
$res = $conn->query("SELECT SUM(total_price) as total_rev FROM bookings WHERE status IN ('Approved', 'Checked In', 'Checked Out')");
if ($res) $totalRevenue = $res->fetch_assoc()['total_rev'] ?? 0;

$selectedMonthRevenue = 0;
$stmt = $conn->prepare("
    SELECT SUM(total_price) as month_rev FROM bookings
    WHERE MONTH(checkin_date) = ? AND YEAR(checkin_date) = ?
      AND status IN ('Approved', 'Checked In', 'Checked Out')
");
$stmt->bind_param("ii", $selectedMonth, $selectedYear);
$stmt->execute();
$res = $stmt->get_result();
if ($res) $selectedMonthRevenue = $res->fetch_assoc()['month_rev'] ?? 0;

$weeklyStats = $conn->prepare("
    SELECT FLOOR((DAY(checkin_date)-1)/7)+1 as week_num,
           COUNT(*) as total_bookings,
           SUM(total_price) as week_inflow
    FROM bookings
    WHERE MONTH(checkin_date) = ? AND YEAR(checkin_date) = ? AND status IN ('Approved', 'Checked In', 'Checked Out')
    GROUP BY week_num ORDER BY week_num ASC
");
$weeklyStats->bind_param("ii", $selectedMonth, $selectedYear);
$weeklyStats->execute();
$weeklyStats = $weeklyStats->get_result();

$addressResult = $conn->query("
    SELECT address, COUNT(*) as count FROM bookings
    GROUP BY address ORDER BY count DESC LIMIT 5
");

// Buffer weekly stats into a plain array so we can both render the table
// AND feed the same data into the Chart.js trend chart below.
$weeklyStatsArr = [];
if ($weeklyStats) {
    while ($row = $weeklyStats->fetch_assoc()) {
        $weeklyStatsArr[] = [
            'week'     => 'Week ' . $row['week_num'],
            'bookings' => (int)$row['total_bookings'],
            'revenue'  => (float)($row['week_inflow'] ?? 0),
        ];
    }
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

    <style>
        :root { 
            --primary-green: #1e4d40; 
            --accent-teal: #2c7a7b; 
            --sidebar-width: 260px; 
            --soft-bg: #f4f7f6; 
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--soft-bg); margin: 0; }
        
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
            z-index: 1000; 
            box-shadow: 4px 0 10px rgba(0,0,0,0.1); 
        }
        .sidebar h4 { font-weight: 700; text-align: center; margin-bottom: 30px; }
        .nav-link { 
            color: rgba(255,255,255,0.7); 
            margin-bottom: 8px; 
            padding: 12px 15px; 
            text-decoration: none; 
            display: block; 
            border-radius: 8px; 
            transition: 0.3s; 
        }
        .nav-link:hover { color: white; background: rgba(255,255,255,0.1); transform: translateX(5px); }
        .nav-link.active { background: var(--accent-teal); color: white; box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        
        .main-content { margin-left: var(--sidebar-width); padding: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 20px; border-left: 5px solid transparent; }
        .stat-card.revenue { border-color: #27ae60; }
        .stat-card.bookings { border-color: var(--accent-teal); }
        .stat-card.pending { border-color: #f59e0b; }
        .stat-card.archived { border-color: #e53e3e; }
        .section-card { background: white; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 25px; }

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
        <a class="nav-link text-info" href="../reception/index.php"><span>🛎 Front Desk</span></a>
        <a class="nav-link text-warning" href="../index.php" target="_blank"><span>← View Website</span></a>
        <a class="nav-link text-danger" href="logout.php"><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-dark fw-bold m-0">📊 Performance Insights</h2>
        <a href="admin_analytics.php?export=csv" class="btn btn-success px-4">
            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
        </a>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="stat-card revenue">
                <div class="text-muted small fw-bold mb-1">TOTAL SALES</div>
                <h3 class="text-success fw-bold">₱<?= number_format($totalRevenue, 2) ?></h3>
                <small class="text-muted">Approved, checked in & checked out</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card revenue">
                <div class="text-muted small fw-bold mb-1">SELECTED MONTH'S SALES</div>
                <h3 class="text-success fw-bold">₱<?= number_format($selectedMonthRevenue, 2) ?></h3>
                <small class="text-muted"><?= date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)) ?></small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card pending">
                <div class="text-muted small fw-bold mb-1">PENDING</div>
                <h3 class="text-warning fw-bold"><?= (int)$pendingCount ?></h3>
                <small class="text-muted">Awaiting approval</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card bookings">
                <div class="text-muted small fw-bold mb-1">APPROVED / ACTIVE</div>
                <h3 class="text-dark fw-bold"><?= (int)$approvedCount ?></h3>
                <small class="text-muted">Confirmed, in-house & completed stays</small>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="stat-card archived">
                <div class="text-muted small fw-bold mb-1">ARCHIVED / CANCELLED</div>
                <h3 class="text-danger fw-bold"><?= (int)$archivedCount ?></h3>
                <small class="text-muted">Total cancelled</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card bookings">
                <div class="text-muted small fw-bold mb-1">TOTAL BOOKINGS</div>
                <h3 class="text-dark fw-bold"><?= (int)$totalActive ?></h3>
                <small class="text-muted">All-time, every status</small>
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="fw-bold m-0">📅 Weekly Breakdown — <?= date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)) ?></h5>
            <form method="GET" class="d-flex gap-2">
                <select name="month" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $selectedMonth ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <select name="year" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <?php for ($y = (int)date('Y'); $y >= $earliestYear; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <?php if (isset($_GET['export'])): ?>
                    <input type="hidden" name="export" value="csv">
                <?php endif; ?>
            </form>
        </div>
        <?php if (!empty($weeklyStatsArr)): ?>
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Week</th>
                    <th>Bookings</th>
                    <th>Gross Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($weeklyStatsArr as $w): ?>
                <tr>
                    <td><?= htmlspecialchars($w['week']) ?></td>
                    <td><?= htmlspecialchars($w['bookings']) ?></td>
                    <td class="text-success fw-semibold">₱<?= number_format($w['revenue'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="text-muted">No approved bookings this month yet.</p>
        <?php endif; ?>
    </div>

    <div class="section-card">
        <h5 class="fw-bold mb-3">📈 Weekly Trend — <?= date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)) ?></h5>
        <?php if (!empty($weeklyStatsArr)): ?>
            <canvas id="weeklyTrendChart" height="90"></canvas>
        <?php else: ?>
            <p class="text-muted">No data to chart yet.</p>
        <?php endif; ?>
    </div>

    <div class="section-card">
        <h5 class="fw-bold mb-3">📍 Top Guest Locations</h5>
        <?php if ($addressResult && $addressResult->num_rows > 0): ?>
        <table class="table table-sm">
            <thead><tr><th>Address</th><th>Bookings</th></tr></thead>
            <tbody>
                <?php while ($row = $addressResult->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['address']) ?></td>
                    <td><span class="badge bg-secondary"><?= (int)$row['count'] ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="text-muted">No location data yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($weeklyStatsArr)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const weeklyData = <?= json_encode($weeklyStatsArr) ?>;

    new Chart(document.getElementById('weeklyTrendChart'), {
        type: 'bar',
        data: {
            labels: weeklyData.map(w => w.week),
            datasets: [
                {
                    label: 'Bookings',
                    data: weeklyData.map(w => w.bookings),
                    backgroundColor: '#2c7a7b',
                    yAxisID: 'yBookings',
                    borderRadius: 6
                },
                {
                    label: 'Revenue (₱)',
                    data: weeklyData.map(w => w.revenue),
                    type: 'line',
                    borderColor: '#27ae60',
                    backgroundColor: '#27ae60',
                    tension: 0.3,
                    yAxisID: 'yRevenue'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                yBookings: {
                    type: 'linear',
                    position: 'left',
                    title: { display: true, text: 'Bookings' },
                    ticks: { precision: 0 }
                },
                yRevenue: {
                    type: 'linear',
                    position: 'right',
                    title: { display: true, text: 'Revenue (₱)' },
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
</script>
<?php endif; ?>

</body>
</html>