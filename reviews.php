<?php 
include "conn.php"; 

// 1. HANDLE THE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $rating = (int)$_POST['rating'];
    $text = htmlspecialchars(trim($_POST['review_text'] ?? ''), ENT_QUOTES, 'UTF-8');
    $photo_name = "";

    // Validate rating range
    if ($rating < 1 || $rating > 5) $rating = 3;

    if (isset($_FILES['review_photo']) && $_FILES['review_photo']['error'] === 0) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_ext   = strtolower(pathinfo($_FILES['review_photo']['name'], PATHINFO_EXTENSION));
        $allowed    = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($file_ext, $allowed)) {
            $photo_name  = time() . "_" . uniqid() . "." . $file_ext;
            $target_path = $upload_dir . $photo_name;
            if (!move_uploaded_file($_FILES['review_photo']['tmp_name'], $target_path)) {
                $photo_name = ""; 
            }
        }
    }

    // The `approved` column is kept for backwards compatibility with older
    // databases, but every new review goes live right away.
    $hasApproved = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM reviews LIKE 'approved'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasApproved = true;
    }

    if ($hasApproved) {
        $stmt = $conn->prepare("INSERT INTO reviews (name, rating, review_text, photo_path, submission_date, approved) VALUES (?, ?, ?, ?, NOW(), 1)");
    } else {
        $stmt = $conn->prepare("INSERT INTO reviews (name, rating, review_text, photo_path, submission_date) VALUES (?, ?, ?, ?, NOW())");
    }
    $stmt->bind_param("siss", $name, $rating, $text, $photo_name);
    $stmt->execute();
    $stmt->close();

    // Publish any older reviews that were left waiting for approval.
    if ($hasApproved) {
        $conn->query("UPDATE reviews SET approved = 1 WHERE approved = 0 OR approved IS NULL");
    }
    
    header("Location: reviews.php?success=1");
    exit();
}

// 2. FETCH REVIEWS
$reviews = [];
// Reviews are published immediately — no admin approval step.
$result = $conn->query("SELECT * FROM reviews ORDER BY submission_date DESC");
if($result) {
    while($row = $result->fetch_assoc()) $reviews[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Reviews - Avianna's Inland Resort</title>
    <link rel="icon" type="image/png" href="img/avianna.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --tropical-green: #1a4731;
            --accent-gold: #ffc107;
            --soft-sand: #f8f9fa;
            --deep-palm: #0e2a1d;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--soft-sand);
            color: #333333;
            margin: 0;
            padding: 0;
        }

        h1, h2, h3, h4, .navbar-brand {
            font-family: 'Playfair Display', serif;
        }

        .navbar {
            background-color: var(--tropical-green) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 1030;
        }

        .navbar-brand { 
            font-size: 1.8rem; 
            letter-spacing: 1px; 
        }

        .btn-outline-light { 
            border-radius: 50px; 
            transition: all 0.3s ease; 
        }

        .btn-outline-light:hover {
            background-color: var(--accent-gold);
            border-color: var(--accent-gold);
            color: var(--deep-palm) !important;
        }

        .hero {
            position: relative;
            min-height: 50vh;
            display: flex;
            flex-direction: column; 
            align-items: center;
            justify-content: center;
            background: linear-gradient(rgba(26, 71, 49, 0.7), rgba(14, 42, 29, 0.8)), url('img/Avianna_bg.jpg') center/cover no-repeat;
            background-color: var(--tropical-green);
            color: white;
            text-align: center;
        }

        .hero h1 { 
            text-shadow: 2px 2px 10px rgba(0,0,0,0.5); 
        }

        /* Sticky Form Card Styling */
        .sticky-card {
            position: sticky;
            top: 100px;
            z-index: 10;
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            background: #ffffff;
        }

        /* Review Card Component */
        .review-card {
            border: none;
            border-left: 5px solid var(--tropical-green);
            border-radius: 15px;
            background: #ffffff;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .review-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.08) !important;
        }

        .rating-stars {
            color: var(--accent-gold);
            font-size: 1.1rem;
            letter-spacing: 2px;
        }

        .review-img {
            width: 100%;
            max-height: 450px;
            object-fit: cover;
            border-radius: 12px;
        }

        /* Form Controls */
        .form-control, .form-select {
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
            border-radius: 10px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--tropical-green);
            box-shadow: 0 0 0 0.25rem rgba(26, 71, 49, 0.15);
        }

        .btn-submit-review {
            background-color: var(--tropical-green);
            color: #ffffff;
            border: none;
            font-weight: 600;
            border-radius: 50px;
            padding: 12px;
            transition: all 0.3s ease;
        }

        .btn-submit-review:hover {
            background-color: var(--deep-palm);
            color: var(--accent-gold);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(14, 42, 29, 0.3);
        }

        footer {
            background-color: var(--deep-palm) !important;
            border-top: 5px solid var(--accent-gold);
        }

        .admin-link {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.3);
            text-decoration: none;
            transition: color 0.3s;
        }

        .admin-link:hover { 
            color: var(--accent-gold); 
        }

        .fb-link {
            transition: color 0.3s ease;
        }

        .fb-link:hover {
            color: var(--accent-gold) !important;
        }
    </style>
</head>
<body>

<?php include "notification.php"; ?>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container">
        <img src="img/avianna.png" 
             alt="Avianna Logo" 
             style="width: 50px; height: auto; margin-bottom: 5px;" 
             class="animate__animated animate__fadeIn shadow-sm rounded-circle me-2">
        <a class="navbar-brand fw-bold" href="index.php">Avianna's Inland Resort</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="ms-auto">
                <a href="index.php" class="btn btn-sm btn-outline-light px-3 me-2">Home</a>
                <a href="aboutus.php" class="btn btn-sm btn-outline-light px-3 me-2">About</a>
                <a href="gallery.php" class="btn btn-sm btn-outline-light px-3 me-2">Gallery</a>
                <a href="reviews.php" class="btn btn-sm btn-outline-light px-3 me-2 active">Reviews</a>
                <a href="book.php" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold">Book Now</a>
            </div>
        </div>
    </div>
</nav>

<header class="hero text-center">
    <div class="container py-5">
        <h1 class="display-3 fw-bold animate__animated animate__zoomIn">Guest Experiences</h1>
        <p class="lead mb-0 animate__animated animate__fadeInUp animate__delay-1s">Shared memories from our tropical sanctuary</p>
    </div>
</header>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success border-0 text-center mb-0 rounded-0 py-3 fw-semibold shadow-sm animate__animated animate__fadeIn">
    ✅ Your review has been posted. Thank you for sharing!
</div>
<?php endif; ?>

<main class="py-5">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <div class="card sticky-card p-4">
                    <h4 class="fw-bold mb-3" style="color: var(--tropical-green);">Share Your Story</h4>
                    <form action="reviews.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Full Name</label>
                            <input type="text" name="name" class="form-control" placeholder="John Doe" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Your Rating</label>
                            <select name="rating" class="form-select">
                                <option value="5">★★★★★ Excellent</option>
                                <option value="4">★★★★☆ Good</option>
                                <option value="3">★★★☆☆ Average</option>
                                <option value="2">★★☆☆☆ Poor</option>
                                <option value="1">★☆☆☆☆ Terrible</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Upload a Memory</label>
                            <input type="file" name="review_photo" class="form-control" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Review Details</label>
                            <textarea name="review_text" class="form-control" rows="4" placeholder="How was your stay with us?" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-submit-review w-100 shadow-sm mt-2">Post Review</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <?php if (empty($reviews)): ?>
                    <div class="text-center py-5 bg-white rounded-4 shadow-sm">
                        <p class="text-muted fs-5 mb-0">No reviews yet. Be the first to share your experience!</p>
                    </div>
                <?php else: ?>
                    <?php foreach($reviews as $r): ?>
                        <div class="card review-card p-4 mb-4 shadow-sm animate__animated animate__fadeInUp">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="fw-bold mb-1 fs-4" style="color: var(--tropical-green);"><?= htmlspecialchars($r['name']) ?></h5>
                                    <div class="rating-stars mb-2">
                                        <?= str_repeat('★', $r['rating']) ?><?= str_repeat('☆', 5 - $r['rating']) ?>
                                    </div>
                                </div>
                                <small class="text-muted fs-6">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    <?= date('M d, Y', strtotime($r['submission_date'])) ?>
                                </small>
                            </div>

                            <p class="text-dark fs-5 mt-2 mb-0" style="font-style: italic; line-height: 1.6;">
                                "<?= htmlspecialchars($r['review_text']) ?>"
                            </p>

                            <?php if (!empty($r['photo_path'])): ?>
                                <div class="overflow-hidden rounded-3 mt-3">
                                    <img src="uploads/<?= htmlspecialchars($r['photo_path']) ?>" alt="Guest Memory" class="review-img">
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<footer class="bg-dark text-white text-center py-4">
    <div class="container">
        <div class="mb-3">
            <a href="https://web.facebook.com/avianna.inland.resorts" target="_blank" class="text-white fs-4 me-3 text-decoration-none fb-link">
                <i class="bi bi-facebook"></i> <span class="fs-6">Follow us on Facebook</span>
            </a>
        </div>
        
        <p class="mb-0">&copy; 2026 Avianna's Inland Resort. All rights reserved.</p>
        <a href="admin/login.php" class="admin-link">Administrator Portal</a>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>