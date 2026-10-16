<?php
session_start();
include "../conn.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$bannerMsg = "";
$errorMsg = "";

// ---- Handle new expense submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $expense_date = $_POST['expense_date'] ?? '';
    $item_description = trim($_POST['item_description'] ?? '');
    $price = $_POST['price'] ?? '';
    $pcs = $_POST['pcs'] ?? '1';

    if ($expense_date === '' || $item_description === '' || $price === '' || $pcs === '') {
        $errorMsg = "Please fill in all fields.";
    } elseif (!is_numeric($price) || !is_numeric($pcs) || $price < 0 || $pcs <= 0) {
        $errorMsg = "Price and Pcs must be valid positive numbers.";
    } else {
        $price = (float) $price;
        $pcs = (int) $pcs;
        $total = $price * $pcs;
        $created_by = $_SESSION['username'] ?? $_SESSION['name'] ?? null;

        $stmt = $conn->prepare("INSERT INTO expenses (expense_date, item_description, price, pcs, total, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdids", $expense_date, $item_description, $price, $pcs, $total, $created_by);

        if ($stmt->execute()) {
            header("Location: admin_expenses.php?expense=created");
            exit();
        } else {
            $errorMsg = "Failed to save expense. Please try again.";
        }
        $stmt->close();
    }
}

// ---- Delete entry (GET link, matching Walk-in Guests page) ----
if (isset($_GET['delete'])) {
    $delId = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->bind_param("i", $delId);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_expenses.php?expense=deleted");
    exit();
}

// ---- CSV export ----
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportRes = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC, id DESC");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=expenses_export_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Item / Description', 'Price', 'Pcs', 'Total', 'Created By']);
    if ($exportRes) {
        while ($row = $exportRes->fetch_assoc()) {
            fputcsv($out, [
                $row['expense_date'],
                $row['item_description'],
                $row['price'],
                $row['pcs'],
                $row['total'],
                $row['created_by'],
            ]);
        }
    }
    fclose($out);
    exit();
}

if (isset($_GET['expense']) && $_GET['expense'] === 'created') $bannerMsg = "Expense recorded.";
if (isset($_GET['expense']) && $_GET['expense'] === 'deleted') $bannerMsg = "Expense removed.";

// ---- Month / Year filter (matching Walk-in Guests page) ----
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$selectedMonth = isset($_GET['exp_month']) ? max(1, min(12, (int) $_GET['exp_month'])) : (int) date('n');
$selectedYear  = isset($_GET['exp_year'])  ? (int) $_GET['exp_year']  : (int) date('Y');

$availableYears = [(int) date('Y')];
if ($res = $conn->query("SELECT DISTINCT YEAR(expense_date) AS y FROM expenses ORDER BY y DESC")) {
    while ($row = $res->fetch_assoc()) {
        $y = (int) $row['y'];
        if ($y && !in_array($y, $availableYears, true)) $availableYears[] = $y;
    }
}
rsort($availableYears);

// ---- Entries for selected month, grouped by date ----
$expensesByDate = [];
$monthTotal = 0;
$monthCount = 0;

$stmt = $conn->prepare("SELECT * FROM expenses WHERE MONTH(expense_date) = ? AND YEAR(expense_date) = ? ORDER BY expense_date DESC, id DESC");
$stmt->bind_param("ii", $selectedMonth, $selectedYear);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $date = $row['expense_date'];
    if (!isset($expensesByDate[$date])) {
        $expensesByDate[$date] = [
            'rows' => [],
            'dayTotal' => 0
        ];
    }
    $expensesByDate[$date]['rows'][] = $row;
    $expensesByDate[$date]['dayTotal'] += (float) $row['total'];
    $monthTotal += (float) $row['total'];
    $monthCount++;
}
$stmt->close();

// ---- All-time totals ----
$allTimeTotal = 0;
$allTimeCount = 0;
if ($res = $conn->query("SELECT COALESCE(SUM(total),0) AS t, COUNT(*) AS c FROM expenses")) {
    if ($row = $res->fetch_assoc()) {
        $allTimeTotal = (float) $row['t'];
        $allTimeCount = (int) $row['c'];
    }
}

$selectedMonthLabel = $monthNames[$selectedMonth] . ' ' . $selectedYear;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Expenses | Avianna's Admin</title>
    <link rel="icon" type="image/png" href="img/avianna.jpg">

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
        .table-card, .chart-card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 25px; }
        .table thead th { border: none; color: #6c757d; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; padding: 14px; }
        .table tbody td { padding: 14px; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }
        .btn-primary-green { background-color: var(--primary-green); color: white; border-radius: 8px; padding: 10px 20px; font-weight: 600; text-decoration: none; border: none; }
        .btn-primary-green:hover { background-color: var(--accent-teal); color: white; }
        .date-subheader {
            background: #eef6f4; color: var(--primary-green); font-weight: 700;
            padding: 12px 16px; font-size: 0.9rem; display: flex; justify-content: space-between;
            align-items: center; border-radius: 10px; margin-bottom: 0;
        }
        .badge-total { background: #e6fffa; color: #234e52; border: 1px solid #b2f5ea; font-weight: 700; padding: 6px 14px; border-radius: 6px; }
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
        <a class="nav-link" href="admin_walkins.php"><span>Walk-in Guests</span></a>
        <a class="nav-link active" href="admin_expenses.php"><span>Daily Expenses</span></a>
        <hr style="border-color: rgba(255,255,255,0.1); margin: 20px 0;">
        <a class="nav-link text-info" href="../admin/reception/index.php"><span>🛎 Front Desk</span></a>
        <a class="nav-link text-warning" href="../index.php" target="_blank"><span>← View Website</span></a>
        <a class="nav-link text-danger" href="logout.php"><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="header-title mb-0">Daily Expenses</h2>
        <a href="admin_expenses.php?export=csv" class="btn-primary-green">
            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
        </a>
    </div>

    <?php if (!empty($bannerMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($bannerMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($errorMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-revenue"><i class="bi bi-cash-stack"></i></div>
                <p class="stat-value">₱<?php echo number_format($allTimeTotal, 0); ?></p>
                <span class="stat-label">Total Expenses (All Time)</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-guests"><i class="bi bi-receipt"></i></div>
                <p class="stat-value"><?php echo $allTimeCount; ?></p>
                <span class="stat-label">Entries (All Time)</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-revenue"><i class="bi bi-calendar2-week"></i></div>
                <p class="stat-value">₱<?php echo number_format($monthTotal, 0); ?></p>
                <span class="stat-label"><?php echo htmlspecialchars($selectedMonthLabel); ?> Expenses</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-guests"><i class="bi bi-receipt-cutoff"></i></div>
                <p class="stat-value"><?php echo $monthCount; ?></p>
                <span class="stat-label"><?php echo htmlspecialchars($selectedMonthLabel); ?> Entries</span>
            </div>
        </div>
    </div>

    <div class="chart-card">
        <h6 class="fw-bold text-dark mb-3">➕ Log a Daily Expense</h6>
        <form method="POST" class="row g-3">
            <input type="hidden" name="add_expense" value="1">
            <div class="col-md-3">
                <label class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" name="expense_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Item / Description <span class="text-danger">*</span></label>
                <input type="text" name="item_description" class="form-control" placeholder="e.g. Labor, Chlorine, Gasoline" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Price (₱) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" name="price" id="expPrice" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Pcs <span class="text-danger">*</span></label>
                <input type="number" step="1" min="1" name="pcs" id="expPcs" class="form-control" value="1" required>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <div class="w-100">
                    <label class="form-label mb-0">Total</label>
                    <p class="mb-0 fw-bold" style="font-size:1.1rem;color:var(--primary-green);">₱<span id="computedTotal">0.00</span></p>
                </div>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn-primary-green w-100"><i class="bi bi-save"></i></button>
            </div>
        </form>
    </div>

    <div class="chart-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <h6 class="fw-bold text-dark mb-0">📋 Expense Log — <?php echo htmlspecialchars($selectedMonthLabel); ?></h6>
            <form method="GET" class="d-flex gap-2">
                <select name="exp_month" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                    <?php foreach ($monthNames as $num => $label): ?>
                        <option value="<?php echo $num; ?>" <?php echo $num === $selectedMonth ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="exp_year" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                    <?php foreach ($availableYears as $y): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y === $selectedYear ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Go</button></noscript>
            </form>
        </div>

        <?php if (empty($expensesByDate)): ?>
            <p class="text-muted mb-0">No expenses recorded for <?php echo htmlspecialchars($selectedMonthLabel); ?> yet.</p>
        <?php else: ?>
            <?php foreach ($expensesByDate as $date => $data): ?>
                <div class="date-subheader mt-3">
                    <span><i class="bi bi-calendar3 me-2"></i><?php echo date('F d, Y', strtotime($date)); ?></span>
                    <span class="badge-total">Day Total: ₱<?php echo number_format($data['dayTotal'], 2); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Item / Description</th>
                                <th>Price</th>
                                <th>Pcs</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['rows'] as $row): ?>
                            <tr>
                                <td class="fw-medium"><?php echo htmlspecialchars($row['item_description']); ?></td>
                                <td>₱<?php echo number_format((float) $row['price'], 2); ?></td>
                                <td><?php echo (int) $row['pcs']; ?></td>
                                <td class="fw-bold">₱<?php echo number_format((float) $row['total'], 2); ?></td>
                                <td>
                                    <a href="admin_expenses.php?delete=<?php echo (int) $row['id']; ?>"
                                       class="text-danger"
                                       onclick="return confirm('Remove this expense entry?');"
                                       title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            <div class="d-flex justify-content-end mt-3">
                <span class="badge-total" style="font-size:1rem;">Month Total: ₱<?php echo number_format($monthTotal, 2); ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function recalcExpenseTotal() {
    const price = parseFloat(document.getElementById('expPrice').value) || 0;
    const pcs   = parseFloat(document.getElementById('expPcs').value)   || 0;
    document.getElementById('computedTotal').textContent = (price * pcs).toFixed(2);
}

['expPrice', 'expPcs'].forEach(id => {
    document.getElementById(id).addEventListener('input', recalcExpenseTotal);
});
</script>

</body>
</html>