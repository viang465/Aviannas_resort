<?php
session_start();
include "../conn.php";
require_once __DIR__ . "/../gallery_data.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

gallery_ensure_schema($conn);

$success_msg = "";
$error_msg   = "";

/* Where uploaded photos land (physical path + web path) */
$uploadFsDir  = __DIR__ . "/../" . GALLERY_UPLOAD_DIR;   // htdocs/uploads/gallery
if (!is_dir($uploadFsDir)) {
    @mkdir($uploadFsDir, 0755, true);
}

$allowedExt  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$maxFileSize = 5 * 1024 * 1024; // 5 MB

/**
 * Handle one uploaded file. Returns the site-relative path, or null on failure.
 */
function gallery_store_upload($file, $uploadFsDir, $allowedExt, $maxFileSize, &$error)
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload failed (error code {$file['error']}). The file may be too large.";
        return null;
    }
    if ($file['size'] > $maxFileSize) {
        $error = "\"{$file['name']}\" is larger than 5 MB. Please compress it first.";
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        $error = "\"{$file['name']}\" is not an allowed image type (JPG, PNG, GIF, WEBP only).";
        return null;
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        $error = "\"{$file['name']}\" is not a valid image file.";
        return null;
    }

    $newName = time() . "_" . bin2hex(random_bytes(6)) . "." . $ext;
    $dest    = rtrim($uploadFsDir, '/') . "/" . $newName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $error = "Could not save \"{$file['name']}\" to the server. Check folder permissions on /uploads/gallery.";
        return null;
    }

    return GALLERY_UPLOAD_DIR . "/" . $newName;
}

/** Delete a physical file, but only if it lives inside uploads/gallery. */
function gallery_delete_file($sitePath)
{
    if (strpos($sitePath, GALLERY_UPLOAD_DIR . '/') !== 0) {
        return; // never touch the original /img assets
    }
    $fs = __DIR__ . "/../" . $sitePath;
    if (is_file($fs)) {
        @unlink($fs);
    }
}

/* ============================ ACTIONS ============================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ---- Add a brand-new gallery section ---- */
    if ($action === 'add_item') {
        $title = trim($_POST['title'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $pax   = trim($_POST['pax'] ?? '');

        if ($title === '') {
            $error_msg = "Please enter a title for the new gallery section.";
        } else {
            $ordRes = $conn->query("SELECT COALESCE(MAX(sort_order), -1) + 1 AS nxt FROM gallery_items");
            $order  = $ordRes ? (int)$ordRes->fetch_assoc()['nxt'] : 0;

            $stmt = $conn->prepare(
                "INSERT INTO gallery_items (title, description, price, pax, sort_order, is_active)
                 VALUES (?, ?, ?, ?, ?, 1)"
            );
            $stmt->bind_param("ssssi", $title, $desc, $price, $pax, $order);
            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                $stmt->close();

                // Optional photos uploaded together with the new section
                $saved = 0;
                if (!empty($_FILES['photos']['name'][0])) {
                    $count = count($_FILES['photos']['name']);
                    for ($i = 0; $i < $count; $i++) {
                        $one = [
                            'name'     => $_FILES['photos']['name'][$i],
                            'type'     => $_FILES['photos']['type'][$i],
                            'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                            'error'    => $_FILES['photos']['error'][$i],
                            'size'     => $_FILES['photos']['size'][$i],
                        ];
                        $err  = '';
                        $path = gallery_store_upload($one, $uploadFsDir, $allowedExt, $maxFileSize, $err);
                        if ($path === null) {
                            if ($err) { $error_msg .= $err . " "; }
                            continue;
                        }
                        $isCover = ($saved === 0) ? 1 : 0;
                        $ps = $conn->prepare(
                            "INSERT INTO gallery_photos (item_id, image_path, is_cover, sort_order)
                             VALUES (?, ?, ?, ?)"
                        );
                        $ps->bind_param("isii", $newId, $path, $isCover, $saved);
                        $ps->execute();
                        $ps->close();
                        $saved++;
                    }
                }
                $success_msg = "\"$title\" added to the gallery" . ($saved ? " with $saved photo(s)." : ". Now upload its photos below.");
            } else {
                $error_msg = "Database error: " . $conn->error;
            }
        }
    }

    /* ---- Update the details of an existing section ---- */
    if ($action === 'update_item') {
        $id    = (int)($_POST['item_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $pax   = trim($_POST['pax'] ?? '');

        if ($id > 0 && $title !== '') {
            $stmt = $conn->prepare(
                "UPDATE gallery_items SET title = ?, description = ?, price = ?, pax = ? WHERE id = ?"
            );
            $stmt->bind_param("ssssi", $title, $desc, $price, $pax, $id);
            $stmt->execute();
            $stmt->close();
            $success_msg = "Details for \"$title\" updated.";
        } else {
            $error_msg = "Title cannot be empty.";
        }
    }

    /* ---- Upload more photos into an existing section ---- */
    if ($action === 'upload_photos') {
        $id = (int)($_POST['item_id'] ?? 0);

        if ($id > 0 && !empty($_FILES['photos']['name'][0])) {
            $ordRes = $conn->query("SELECT COALESCE(MAX(sort_order), -1) + 1 AS nxt FROM gallery_photos WHERE item_id = $id");
            $order  = $ordRes ? (int)$ordRes->fetch_assoc()['nxt'] : 0;

            $cRes    = $conn->query("SELECT COUNT(*) AS c FROM gallery_photos WHERE item_id = $id");
            $existing = $cRes ? (int)$cRes->fetch_assoc()['c'] : 0;

            $saved = 0;
            $count = count($_FILES['photos']['name']);
            for ($i = 0; $i < $count; $i++) {
                $one = [
                    'name'     => $_FILES['photos']['name'][$i],
                    'type'     => $_FILES['photos']['type'][$i],
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                    'error'    => $_FILES['photos']['error'][$i],
                    'size'     => $_FILES['photos']['size'][$i],
                ];
                $err  = '';
                $path = gallery_store_upload($one, $uploadFsDir, $allowedExt, $maxFileSize, $err);
                if ($path === null) {
                    if ($err) { $error_msg .= $err . " "; }
                    continue;
                }
                $isCover = ($existing === 0 && $saved === 0) ? 1 : 0;
                $so      = $order + $saved;
                $ps = $conn->prepare(
                    "INSERT INTO gallery_photos (item_id, image_path, is_cover, sort_order)
                     VALUES (?, ?, ?, ?)"
                );
                $ps->bind_param("isii", $id, $path, $isCover, $so);
                $ps->execute();
                $ps->close();
                $saved++;
            }
            if ($saved > 0) {
                $success_msg = "$saved photo(s) uploaded.";
            }
        } else {
            $error_msg = "Please choose at least one image to upload.";
        }
    }

    /* ---- Replace one existing photo with a new file (substitute) ---- */
    if ($action === 'replace_photo') {
        $photoId = (int)($_POST['photo_id'] ?? 0);

        if ($photoId > 0 && isset($_FILES['photo'])) {
            $err  = '';
            $path = gallery_store_upload($_FILES['photo'], $uploadFsDir, $allowedExt, $maxFileSize, $err);
            if ($path === null) {
                $error_msg = $err ?: "Please choose a replacement image.";
            } else {
                $old = $conn->query("SELECT image_path FROM gallery_photos WHERE id = $photoId");
                $oldPath = $old && $old->num_rows ? $old->fetch_assoc()['image_path'] : '';

                $stmt = $conn->prepare("UPDATE gallery_photos SET image_path = ? WHERE id = ?");
                $stmt->bind_param("si", $path, $photoId);
                $stmt->execute();
                $stmt->close();

                if ($oldPath) { gallery_delete_file($oldPath); }
                $success_msg = "Photo replaced successfully.";
            }
        }
    }
}

/* ---- GET actions ---- */
if (isset($_GET['set_cover'])) {
    $photoId = (int)$_GET['set_cover'];
    $itemId  = (int)($_GET['item'] ?? 0);
    if ($photoId > 0 && $itemId > 0) {
        $conn->query("UPDATE gallery_photos SET is_cover = 0 WHERE item_id = $itemId");
        $conn->query("UPDATE gallery_photos SET is_cover = 1 WHERE id = $photoId");
        header("Location: admin_gallery.php?msg=cover#item-$itemId");
        exit();
    }
}

if (isset($_GET['delete_photo'])) {
    $photoId = (int)$_GET['delete_photo'];
    $itemId  = (int)($_GET['item'] ?? 0);
    if ($photoId > 0) {
        $row = $conn->query("SELECT image_path, is_cover, item_id FROM gallery_photos WHERE id = $photoId");
        if ($row && $row->num_rows) {
            $data = $row->fetch_assoc();
            $conn->query("DELETE FROM gallery_photos WHERE id = $photoId");
            gallery_delete_file($data['image_path']);

            // If the cover was removed, promote the next photo.
            if ((int)$data['is_cover'] === 1) {
                $nid = (int)$data['item_id'];
                $nx  = $conn->query("SELECT id FROM gallery_photos WHERE item_id = $nid ORDER BY sort_order ASC, id ASC LIMIT 1");
                if ($nx && $nx->num_rows) {
                    $nextId = (int)$nx->fetch_assoc()['id'];
                    $conn->query("UPDATE gallery_photos SET is_cover = 1 WHERE id = $nextId");
                }
            }
        }
        header("Location: admin_gallery.php?msg=photo_deleted#item-$itemId");
        exit();
    }
}

if (isset($_GET['delete_item'])) {
    $itemId = (int)$_GET['delete_item'];
    if ($itemId > 0) {
        $photos = $conn->query("SELECT image_path FROM gallery_photos WHERE item_id = $itemId");
        while ($photos && $p = $photos->fetch_assoc()) {
            gallery_delete_file($p['image_path']);
        }
        $conn->query("DELETE FROM gallery_photos WHERE item_id = $itemId");
        $conn->query("DELETE FROM gallery_items WHERE id = $itemId");
        header("Location: admin_gallery.php?msg=item_deleted");
        exit();
    }
}

if (isset($_GET['toggle'])) {
    $itemId = (int)$_GET['toggle'];
    if ($itemId > 0) {
        $conn->query("UPDATE gallery_items SET is_active = 1 - is_active WHERE id = $itemId");
        header("Location: admin_gallery.php?msg=toggled#item-$itemId");
        exit();
    }
}

if (isset($_GET['move'], $_GET['dir'])) {
    $itemId = (int)$_GET['move'];
    $dir    = $_GET['dir'] === 'up' ? 'up' : 'down';
    $cur    = $conn->query("SELECT sort_order FROM gallery_items WHERE id = $itemId");
    if ($cur && $cur->num_rows) {
        $curOrder = (int)$cur->fetch_assoc()['sort_order'];
        $cmp      = $dir === 'up' ? '<' : '>';
        $ord      = $dir === 'up' ? 'DESC' : 'ASC';
        $nb = $conn->query("SELECT id, sort_order FROM gallery_items
                            WHERE sort_order $cmp $curOrder ORDER BY sort_order $ord LIMIT 1");
        if ($nb && $nb->num_rows) {
            $n = $nb->fetch_assoc();
            $conn->query("UPDATE gallery_items SET sort_order = {$n['sort_order']} WHERE id = $itemId");
            $conn->query("UPDATE gallery_items SET sort_order = $curOrder WHERE id = {$n['id']}");
        }
    }
    header("Location: admin_gallery.php?msg=reordered#item-$itemId");
    exit();
}

if (isset($_GET['msg'])) {
    $map = [
        'cover'         => "Cover photo updated — this is what shows on the home page.",
        'photo_deleted' => "Photo removed from the gallery.",
        'item_deleted'  => "Gallery section deleted.",
        'toggled'       => "Visibility updated.",
        'reordered'     => "Order updated.",
    ];
    $success_msg = $map[$_GET['msg']] ?? "";
}

/* Load everything for display (including hidden items) */
$items = gallery_load_items($conn, false, '../');

/* Raw photo rows keyed by item so we can expose photo IDs in the UI */
$photoRows = [];
$pr = $conn->query("SELECT * FROM gallery_photos ORDER BY item_id, sort_order ASC, id ASC");
while ($pr && $row = $pr->fetch_assoc()) {
    $photoRows[(int)$row['item_id']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resort Gallery | Avianna's Admin</title>
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

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            margin: 0;
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
            overflow-y: auto;
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
        .nav-link.active { color: white; background: var(--accent-teal); }

        .content { margin-left: var(--sidebar-width); padding: 30px; }

        .card-panel {
            background: white;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            padding: 24px;
            margin-bottom: 25px;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }

        .photo-tile {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            border: 3px solid transparent;
            background: #f1f3f5;
            aspect-ratio: 4 / 3;
        }
        .photo-tile.is-cover { border-color: #ffc107; }
        .photo-tile img { width: 100%; height: 100%; object-fit: cover; display: block; }

        .photo-actions {
            position: absolute;
            inset: auto 0 0 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.78));
            padding: 8px 6px 6px;
            display: flex;
            gap: 5px;
            justify-content: center;
            opacity: 0;
            transition: opacity .2s ease;
        }
        .photo-tile:hover .photo-actions { opacity: 1; }
        .photo-actions .btn { padding: 2px 8px; font-size: .72rem; }

        .cover-badge {
            position: absolute;
            top: 6px; left: 6px;
            background: #ffc107;
            color: #212529;
            font-size: .68rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .btn-primary-green { background: var(--primary-green); border-color: var(--primary-green); color: #fff; }
        .btn-primary-green:hover { background: var(--accent-teal); border-color: var(--accent-teal); color: #fff; }

        .drop-zone {
            border: 2px dashed #ced4da;
            border-radius: 10px;
            padding: 14px;
            background: #fbfcfc;
        }

        .hidden-item { opacity: .6; }

        @media (max-width: 768px) {
            .sidebar { width: 70px; padding: 15px 8px; }
            .sidebar h4, .nav-link span { display: none; }
            .content { margin-left: 70px; padding: 15px; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h4>Avianna's Admin</h4>
    <a class="nav-link" href="admin.php"><span>Pending Bookings</span></a>
    <a class="nav-link" href="approve.php"><span>Approved History</span></a>
    <a class="nav-link" href="admin_cancelled.php"><span>Cancellation History</span></a>
    <a class="nav-link" href="admin_announcements.php"><span>Announcements</span></a>
    <a class="nav-link active" href="admin_gallery.php"><span>Resort Gallery</span></a>
    <a class="nav-link" href="admin_analytics.php"><span>Dashboard</span></a>
    <a class="nav-link" href="admin_walkins.php"><span>Walk-in Guests</span></a>
    <a class="nav-link" href="admin_expenses.php"><span>Daily Expenses</span></a>
    <a class="nav-link text-info" href="../admin/reception/index.php"><span>🛎 Front Desk</span></a>
    <a class="nav-link text-warning" href="../index.php" target="_blank"><span>← View Website</span></a>
    <a class="nav-link text-danger" href="logout.php"><span>Logout</span></a>
</div>

<div class="content">
    <h2 class="fw-bold mb-1" style="color: var(--primary-green);">Resort Gallery</h2>
    <p class="text-muted">Manage the photos shown on the home page and the Gallery page. Changes appear on the website immediately.</p>

    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-1"></i> <?php echo htmlspecialchars($success_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ADD NEW SECTION -->
    <div class="card-panel">
        <h5 class="fw-bold mb-3"><i class="bi bi-plus-square me-2"></i>Add a New Gallery Section</h5>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_item">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Poolside Pavilion" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Price <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" name="price" class="form-control" placeholder="e.g. 400 php">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Capacity <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" name="pax" class="form-control" placeholder="e.g. 8-12 pax">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Short Description</label>
                    <textarea name="description" rows="2" class="form-control" placeholder="One or two sentences shown over the photo."></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Photos</label>
                    <div class="drop-zone">
                        <input type="file" name="photos[]" class="form-control" accept="image/*" multiple>
                        <small class="text-muted d-block mt-2">
                            You can select several images at once. JPG, PNG, GIF or WEBP, max 5 MB each.
                            The first photo becomes the cover.
                        </small>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary-green mt-3 px-4 fw-semibold">
                <i class="bi bi-plus-lg me-1"></i> Add Section
            </button>
        </form>
    </div>

    <?php if (empty($items)): ?>
        <div class="alert alert-info">No gallery sections yet. Add one using the form above.</div>
    <?php endif; ?>

    <?php foreach ($items as $pos => $item):
        $id     = (int)$item['id'];
        $photos = $photoRows[$id] ?? [];
    ?>
    <div class="card-panel <?php echo $item['is_active'] ? '' : 'hidden-item'; ?>" id="item-<?php echo $id; ?>">
        <div class="item-header">
            <div>
                <h5 class="fw-bold mb-1">
                    <?php echo htmlspecialchars($item['title']); ?>
                    <?php if (!$item['is_active']): ?>
                        <span class="badge bg-secondary ms-2">Hidden</span>
                    <?php endif; ?>
                </h5>
                <small class="text-muted">
                    <?php echo count($photos); ?> photo<?php echo count($photos) === 1 ? '' : 's'; ?>
                    <?php if ($item['price']): ?> &middot; <?php echo htmlspecialchars($item['price']); ?><?php endif; ?>
                    <?php if ($item['pax']): ?> &middot; <?php echo htmlspecialchars($item['pax']); ?><?php endif; ?>
                </small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($pos > 0): ?>
                    <a href="?move=<?php echo $id; ?>&dir=up" class="btn btn-outline-secondary btn-sm" title="Move up"><i class="bi bi-arrow-up"></i></a>
                <?php endif; ?>
                <?php if ($pos < count($items) - 1): ?>
                    <a href="?move=<?php echo $id; ?>&dir=down" class="btn btn-outline-secondary btn-sm" title="Move down"><i class="bi bi-arrow-down"></i></a>
                <?php endif; ?>
                <a href="?toggle=<?php echo $id; ?>" class="btn btn-outline-<?php echo $item['is_active'] ? 'warning' : 'success'; ?> btn-sm">
                    <?php echo $item['is_active'] ? 'Hide from site' : 'Show on site'; ?>
                </a>
                <a href="?delete_item=<?php echo $id; ?>"
                   class="btn btn-outline-danger btn-sm"
                   onclick="return confirm('Delete \'<?php echo htmlspecialchars(addslashes($item['title'])); ?>\' and all of its photos? This cannot be undone.');">
                    <i class="bi bi-trash"></i>
                </a>
            </div>
        </div>

        <!-- PHOTOS -->
        <div class="row g-3 mb-4">
            <?php foreach ($photos as $p):
                $pid   = (int)$p['id'];
                $isCov = (int)$p['is_cover'] === 1;
            ?>
            <div class="col-6 col-md-3 col-lg-2">
                <div class="photo-tile <?php echo $isCov ? 'is-cover' : ''; ?>">
                    <img src="../<?php echo htmlspecialchars($p['image_path']); ?>" alt="">
                    <?php if ($isCov): ?><span class="cover-badge">COVER</span><?php endif; ?>
                    <div class="photo-actions">
                        <?php if (!$isCov): ?>
                        <a href="?set_cover=<?php echo $pid; ?>&item=<?php echo $id; ?>"
                           class="btn btn-warning" title="Use as cover photo">Cover</a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-light"
                                onclick="document.getElementById('replace-<?php echo $pid; ?>').click();"
                                title="Replace this photo">Replace</button>
                        <a href="?delete_photo=<?php echo $pid; ?>&item=<?php echo $id; ?>"
                           class="btn btn-danger"
                           onclick="return confirm('Remove this photo?');" title="Delete">&times;</a>
                    </div>
                </div>
                <!-- hidden replace form -->
                <form method="POST" enctype="multipart/form-data" id="form-replace-<?php echo $pid; ?>">
                    <input type="hidden" name="action" value="replace_photo">
                    <input type="hidden" name="photo_id" value="<?php echo $pid; ?>">
                    <input type="file" name="photo" accept="image/*" class="d-none"
                           id="replace-<?php echo $pid; ?>"
                           onchange="document.getElementById('form-replace-<?php echo $pid; ?>').submit();">
                </form>
            </div>
            <?php endforeach; ?>

            <?php if (empty($photos)): ?>
            <div class="col-12">
                <div class="alert alert-warning mb-0 py-2">
                    This section has no photos yet, so it will not appear on the website. Upload at least one below.
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <!-- UPLOAD MORE -->
            <div class="col-lg-5">
                <h6 class="fw-bold mb-2"><i class="bi bi-cloud-arrow-up me-1"></i>Add Photos</h6>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_photos">
                    <input type="hidden" name="item_id" value="<?php echo $id; ?>">
                    <div class="drop-zone">
                        <input type="file" name="photos[]" class="form-control form-control-sm" accept="image/*" multiple required>
                        <small class="text-muted d-block mt-2">Select one or more images (max 5 MB each).</small>
                    </div>
                    <button type="submit" class="btn btn-primary-green btn-sm mt-2 px-3 fw-semibold">Upload</button>
                </form>
            </div>

            <!-- EDIT DETAILS -->
            <div class="col-lg-7">
                <h6 class="fw-bold mb-2"><i class="bi bi-pencil-square me-1"></i>Edit Details</h6>
                <form method="POST">
                    <input type="hidden" name="action" value="update_item">
                    <input type="hidden" name="item_id" value="<?php echo $id; ?>">
                    <div class="row g-2">
                        <div class="col-md-5">
                            <input type="text" name="title" class="form-control form-control-sm"
                                   value="<?php echo htmlspecialchars($item['title']); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="price" class="form-control form-control-sm" placeholder="Price"
                                   value="<?php echo htmlspecialchars($item['price']); ?>">
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="pax" class="form-control form-control-sm" placeholder="Capacity"
                                   value="<?php echo htmlspecialchars($item['pax']); ?>">
                        </div>
                        <div class="col-12">
                            <textarea name="description" rows="2" class="form-control form-control-sm"
                                      placeholder="Short description"><?php echo htmlspecialchars($item['desc']); ?></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-dark btn-sm mt-2 px-3 fw-semibold">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="text-muted small mb-5">
        <i class="bi bi-info-circle me-1"></i>
        The <strong>cover</strong> photo is the one guests see on the home page card. Click
        <em>Replace</em> on any photo to swap it with a new file while keeping its position.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
