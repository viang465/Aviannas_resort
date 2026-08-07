<?php
session_start();
include "../conn.php"; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success_msg = "";
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);

    if (!empty($title) && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO announcements (title, message) VALUES (?, ?)");
        $stmt->bind_param("ss", $title, $message);
        if ($stmt->execute()) {
            $success_msg = "Announcement posted successfully!";
        } else {
            $error_msg = "Database Error: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error_msg = "Please fill in all fields.";
    }
}

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        header("Location: admin_announcements.php?status=deleted");
        exit();
    } else {
        $error_msg = "Failed to delete: " . $conn->error;
    }
    $stmt->close();
}

if (isset($_GET['status']) && $_GET['status'] === 'deleted') {
    $success_msg = "Announcement successfully removed!";
}

$sql = "SELECT * FROM announcements ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements | Avianna's Admin</title>
    
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

        .section-card { 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
            padding: 25px; 
            margin-bottom: 25px; 
        }

        .form-label { font-weight: 600; color: var(--primary-green); }

        .btn-custom {
            background-color: var(--primary-green);
            color: white;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-custom:hover { background-color: var(--accent-teal); color: white; }

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
        <a class="nav-link active" href="admin_announcements.php"><span>Announcements</span></a>
        <a class="nav-link" href="admin_analytics.php"><span>Dashboard</span></a>
        <hr style="border-color: rgba(255,255,255,0.1); margin: 20px 0;">
        <a class="nav-link text-info" href="../reception/index.php"><span>🛎 Front Desk</span></a>
        <a class="nav-link text-warning" href="../index.php" target="_blank"><span>← View Website</span></a>
        <a class="nav-link text-danger" href="logout.php"><span>Logout</span></a>
    </nav>
</div>

<div class="main-content">
    <h2 class="header-title">Resort Announcements</h2>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4 col-md-12">
            <div class="section-card">
                <h5 class="fw-bold mb-4 text-dark"><i class="bi bi-megaphone me-2 text-teal"></i>Post Announcement</h5>
                <form action="admin_announcements.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="title" class="form-label">Subject / Title</label>
                        <input type="text" name="title" id="title" class="form-control" placeholder="E.g., Pool Maintenance" required>
                    </div>
                    <div class="mb-4">
                        <label for="message" class="form-label">Message Details</label>
                        <textarea name="message" id="message" rows="5" class="form-control" placeholder="Write announcement details..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-custom w-100">Publish Announcement</button>
                </form>
            </div>
        </div>

        <div class="col-lg-8 col-md-12">
            <div class="table-card">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Posted On</th>
                            <th style="width: 55%;">Announcement Detail</th>
                            <th style="width: 20%;" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="text-muted d-block fw-semibold"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></span>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($row['created_at'])); ?></small>
                                </td>
                                <td>
                                    <strong class="text-dark d-block mb-1"><?php echo htmlspecialchars($row['title']); ?></strong>
                                    <span class="text-muted small d-block" style="white-space: pre-wrap;"><?php echo htmlspecialchars($row['message']); ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="admin_announcements.php?delete_id=<?php echo $row['id']; ?>" 
                                       class="btn btn-outline-danger btn-sm px-3" 
                                       onclick="return confirm('Are you sure you want to delete this announcement?');">
                                        <i class="bi bi-trash3"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-5">
                                    <img src="https://cdn-icons-png.flaticon.com/512/3208/3208743.png" width="60" class="opacity-25 mb-3" alt="empty">
                                    <p class="text-muted mb-0">No active announcements posted yet.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>