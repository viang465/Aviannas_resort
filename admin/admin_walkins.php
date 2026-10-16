<?php
session_start();
include "../conn.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Entrance fee per guest (change this in one place if it ever changes)
const ENTRANCE_FEE = 75;

// ---- Make sure the table exists (safe to run every load) ----
$conn->query("
    CREATE TABLE IF NOT EXISTS walkins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visit_date DATE NOT NULL,
        num_guests INT NOT NULL,
        entrance_fee INT NOT NULL DEFAULT 75,
        cottage_label VARCHAR(100) NULL,
        cottage_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        extra_charges DECIMAL(10,2) NOT NULL DEFAULT 0,
        discount DECIMAL(10,2) NOT NULL DEFAULT 0,
        total DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$formError = "";

// ---- Add new entry ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_walkin'])) {
    $visitDate     = $_POST['visit_date']     ?? '';
    $numGuests     = isset($_POST['num_guests']) ? (int) $_POST['num_guests'] : 0;
    $cottageLabel  = trim($_POST['cottage_label'] ?? '');
    $cottageAmount = isset($_POST['cottage_amount']) && is_numeric($_POST['cottage_amount']) ? (float) $_POST['cottage_amount'] : 0;
    $extraCharges  = isset($_POST['extra_charges'])  && is_numeric($_POST['extra_charges'])  ? (float) $_POST['extra_charges']  : 0;
    $discount      = isset($_POST['discount'])       && is_numeric($_POST['discount'])       ? (float) $_POST['discount']       : 0;

    if (empty($visitDate) || $numGuests <= 0) {
        $formError = "Please provide a date and a guest count greater than 0.";
    } else {
        $entranceTotal = $numGuests * ENTRANCE_FEE;
        $total = $entranceTotal + $cottageAmount + $extraCharges - $discount;
        $entranceFeeConst = ENTRANCE_FEE;

        $stmt = $conn->prepare("
            INSERT INTO walkins (visit_date, num_guests, entrance_fee, cottage_label, cottage_amount, extra_charges, discount, total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "siisdddd",
            $visitDate, $numGuests, $entranceFeeConst, $cottageLabel,
            $cottageAmount, $extraCharges, $discount, $total
        );
        $stmt->execute();
        $stmt->close();

        header("Location: admin_walkins.php?added=1");
        exit();
    }
}

// ---- Delete entry ----
if (isset($_GET['delete'])) {
    $delId = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM walkins WHERE id = ?");
    $stmt->bind_param("i", $delId);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_walkins.php");
    exit();
}

// ---- CSV export ----
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportRes = $conn->query("SELECT * FROM walkins ORDER BY visit_date DESC, id DESC");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=walkins_export_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Guests', 'Entrance Fee/Head', 'Entrance Total', 'Cottage/Table', 'Cottage Amount', 'Extra Charges', 'Discount', 'Total']);
    if ($exportRes) {
        while ($row = $exportRes->fetch_assoc()) {
            fputcsv($out, [
                $row['visit_date'],
                $row['num_guests'],
                $row['entrance_fee'],
                $row['num_guests'] * $row['entrance_fee'],
                $row['cottage_label'],
                $row['cottage_amount'],
                $row['extra_charges'],
                $row['discount'],
                $row['total'],
            ]);
        }
    }
    fclose($out);
    exit();
}

// ---- Month / Year filter ----
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$selectedMonth = isset($_GET['wb_month']) ? max(1, min(12, (int) $_GET['wb_month'])) : (int) date('n');
$selectedYear  = isset($_GET['wb_year'])  ? (int) $_GET['wb_year']  : (int) date('Y');

$availableYears = [(int) date('Y')];
if ($res = $conn->query("SELECT DISTINCT YEAR(visit_date) AS y FROM walkins ORDER BY y DESC")) {
    while ($row = $res->fetch_assoc()) {
        $y = (int) $row['y'];
        if ($y && !in_array($y, $availableYears, true)) $availableYears[] = $y;
    }
}
rsort($availableYears);

// ---- Entries for selected month ----
$entries = [];
$stmt = $conn->prepare("SELECT * FROM walkins WHERE MONTH(visit_date) = ? AND YEAR(visit_date) = ? ORDER BY visit_date DESC, id DESC");
$stmt->bind_param("ii", $selectedMonth, $selectedYear);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $entries[] = $row;
$stmt->close();

$monthGuests = 0;
$monthRevenue = 0;
foreach ($entries as $e) {
    $monthGuests += (int) $e['num_guests'];
    $monthRevenue += (float) $e['total'];
}

// ---- All-time totals ----
$allTimeGuests = 0;
$allTimeRevenue = 0;
if ($res = $conn->query("SELECT COALESCE(SUM(num_guests),0) AS g, COALESCE(SUM(total),0) AS r FROM walkins")) {
    if ($row = $res->fetch_assoc()) {
        $allTimeGuests  = (int) $row['g'];
        $allTimeRevenue = (float) $row['r'];
    }
}

$selectedMonthLabel = $monthNames[$selectedMonth] . ' ' . $selectedYear;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Guests | Avianna's Admin</title>
    <link rel="icon" type="image/png" href="img/avianna.png">

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
        .sidebar {
            height: 100vh; background: linear-gradient(180deg, var(--primary-green) 0%, #0a1a16 100%);
            color: white; position: fixed; width: var(--sidebar-width); padding: 25px 20px;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1); z-index: 1000;
        }
        .sidebar h4 { font-weight: 700; text-align: center; margin-bottom: 30px; }
        .nav-link {
            color: rgba(255,255,255,0.7); margin-bottom: 8px; padding: 12px 15px;
            border-radius: 8px; font-weight: 500; transition: all 0.3s ease;
            text-decoration: none; display: block;
        }
        .nav-link:hover { color: white; background: rgba(255,255,255,0.1); transform: translateX(5px); }
        .nav-link.active { color: white; background: var(--accent-teal); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .main-content { margin-left: var(--sidebar-width); padding: 40px; min-height: 100vh; }
        .header-title { color: var(--primary-green); border-left: 6px solid var(--accent-teal); padding-left: 20px; font-weight: 700; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 24px; height: 100%; }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 14px; }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: #2d3748; margin: 0; }
        .stat-label { color: #718096; font-size: 0.85rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .bg-revenue  { background: #e6fffa; color: #234e52; }
        .bg-guests   { background: #eefdf5; color: #27ae60; }
        .table-card, .chart-card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 25px; }
        .table thead th { border: none; color: #6c757d; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; padding: 14px; }
        .table tbody td { padding: 14px; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }
        .btn-primary-green { background-color: var(--primary-green); color: white; border-radius: 8px; padding: 10px 20px; font-weight: 600; text-decoration: none; border: none; }
        .btn-primary-green:hover { background-color: var(--accent-teal); color: white; }
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
        <a class="nav-link" href="admin_gallery.php"><span>Resort Gallery</span></a>
        <a class="nav-link" href="admin_analytics.php"><span>Dashboard</span></a>
        <a class="nav-link active" href="admin_walkins.php"><span>Walk-in Guests</span></a>
        <a class="nav-link" href="admin_expenses.php"><span>Daily Expenses</span></a>
        <hr style="border-color: rgba(255,255,255,0.1); margin: 20px 0;">
        <a class="nav-link text-info" href="../admin/reception/index.php"><span>🛎 Front Desk</span></a>
        <a class="nav-link text-warning" href="../index.php" target="_blank"><span>← View Website</span></a>
        <a class="nav-link text-danger" href="logout.php"><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="header-title mb-0">Walk-in Guests</h2>
        <a href="admin_walkins.php?export=csv" class="btn-primary-green">
            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
        </a>
    </div>

    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success">Walk-in entry saved.</div>
    <?php endif; ?>
    <?php if ($formError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($formError); ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-revenue"><i class="bi bi-cash-stack"></i></div>
                <p class="stat-value">₱<?php echo number_format($allTimeRevenue, 0); ?></p>
                <span class="stat-label">Total Revenue (All Time)</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-guests"><i class="bi bi-people-fill"></i></div>
                <p class="stat-value"><?php echo $allTimeGuests; ?></p>
                <span class="stat-label">Guests (All Time)</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-revenue"><i class="bi bi-calendar2-week"></i></div>
                <p class="stat-value">₱<?php echo number_format($monthRevenue, 0); ?></p>
                <span class="stat-label"><?php echo htmlspecialchars($selectedMonthLabel); ?> Revenue</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-guests"><i class="bi bi-person-check"></i></div>
                <p class="stat-value"><?php echo $monthGuests; ?></p>
                <span class="stat-label"><?php echo htmlspecialchars($selectedMonthLabel); ?> Guests</span>
            </div>
        </div>
    </div>

    <div class="chart-card mb-4">
        <h6 class="fw-bold text-dark mb-3">➕ Log a Walk-in Visit</h6>
        <form method="POST" class="row g-3">
            <input type="hidden" name="add_walkin" value="1">
            <div class="col-md-3">
                <label class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" name="visit_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"># of Guests <span class="text-danger">*</span></label>
                <input type="number" min="1" name="num_guests" id="numGuests" class="form-control" required placeholder="e.g. 14">
                <small class="text-muted">Entrance fee: ₱<?php echo ENTRANCE_FEE; ?> / guest</small>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cottage / Table</label>
                <input type="text" name="cottage_label" class="form-control" placeholder="e.g. Cottage 2">
            </div>
            <div class="col-md-3">
                <label class="form-label">Cottage Amount</label>
                <input type="number" step="0.01" min="0" name="cottage_amount" id="cottageAmount" class="form-control" placeholder="600">
            </div>
            <div class="col-md-3">
                <label class="form-label">Extra Charges</label>
                <input type="number" step="0.01" min="0" name="extra_charges" id="extraCharges" class="form-control" placeholder="0">
            </div>
            <div class="col-md-3">
                <label class="form-label">Discount</label>
                <input type="number" step="0.01" min="0" name="discount" id="discount" class="form-control" placeholder="100">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="w-100">
                    <label class="form-label mb-0">Computed Total</label>
                    <p class="mb-0 fw-bold" style="font-size:1.3rem;color:var(--primary-green);">₱<span id="computedTotal">0.00</span></p>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn-primary-green w-100"><i class="bi bi-save"></i> Save Entry</button>
            </div>
        </form>
    </div>

    <div class="chart-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <h6 class="fw-bold text-dark mb-0">📋 Walk-in Log — <?php echo htmlspecialchars($selectedMonthLabel); ?></h6>
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

        <?php if (!empty($entries)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Guests</th>
                            <th>Entrance Total</th>
                            <th>Cottage/Table</th>
                            <th>Extra</th>
                            <th>Discount</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $e): ?>
                            <tr>
                                <td class="text-muted"><?php echo date('M j, Y', strtotime($e['visit_date'])); ?></td>
                                <td><?php echo (int) $e['num_guests']; ?></td>
                                <td>₱<?php echo number_format($e['num_guests'] * $e['entrance_fee'], 2); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($e['cottage_label'] ?: '—'); ?>
                                    <?php if ($e['cottage_amount'] > 0): ?>
                                        <span class="text-muted small">(₱<?php echo number_format($e['cottage_amount'], 2); ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td>₱<?php echo number_format($e['extra_charges'], 2); ?></td>
                                <td>₱<?php echo number_format($e['discount'], 2); ?></td>
                                <td class="fw-bold">₱<?php echo number_format($e['total'], 2); ?></td>
                                <td>
                                    <a href="admin_walkins.php?delete=<?php echo (int) $e['id']; ?>"
                                       class="text-danger"
                                       onclick="return confirm('Delete this entry?');"
                                       title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th><?php echo $monthGuests; ?></th>
                            <th colspan="4"></th>
                            <th>₱<?php echo number_format($monthRevenue, 2); ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">No walk-in entries logged for <?php echo htmlspecialchars($selectedMonthLabel); ?> yet.</p>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ENTRANCE_FEE = <?php echo ENTRANCE_FEE; ?>;

function recalcTotal() {
    const guests   = parseFloat(document.getElementById('numGuests').value)      || 0;
    const cottage  = parseFloat(document.getElementById('cottageAmount').value)  || 0;
    const extra    = parseFloat(document.getElementById('extraCharges').value)   || 0;
    const discount = parseFloat(document.getElementById('discount').value)       || 0;

    const total = (guests * ENTRANCE_FEE) + cottage + extra - discount;
    document.getElementById('computedTotal').textContent = total.toFixed(2);
}

['numGuests', 'cottageAmount', 'extraCharges', 'discount'].forEach(id => {
    document.getElementById(id).addEventListener('input', recalcTotal);
});
</script>

</body>
</html>