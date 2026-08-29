<?php include "conn.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avianna's Inland Resort - Tropical Sanctuary</title>
    <link rel="icon"  type="image/png" href="img/avianna.png" >
    
    <!-- External CSS -->
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

        h1, .navbar-brand {
            font-family: 'Playfair Display', serif;
        }

        .navbar {
            background-color: var(--tropical-green) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
            min-height: 80vh;
            display: flex;
            flex-direction: column; 
            align-items: center;
            justify-content: center;
            background: url('img/Avianna_bg.jpg') center/cover no-repeat;
            background-color: var(--tropical-green);
            color: white;
        }

        .hero h1 { 
            text-shadow: 2px 2px 10px rgba(0,0,0,0.5); 
        }

        .btn-warning {
            background-color: var(--accent-gold);
            border: none;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .btn-warning:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.4);
            background-color: #e6af06;
        }

        .announcement-section {
            margin-top: -60px;
            position: relative;
            z-index: 10;
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
             class="animate__animated animate__fadeIn shadow-sm rounded-circle">
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
                <a href="reviews.php" class="btn btn-sm btn-outline-light px-3 me-2">Reviews</a>
            </div>
        </div>
    </div>
</nav>

<header class="hero text-center">
    <div class="container py-5">
        <h1 class="display-1 fw-bold animate__animated animate__zoomIn">Avianna's Inland Resort</h1>
        <p class="lead mb-4 animate__animated animate__fadeInUp animate__delay-1s">Escape to peace and luxury at Avianna's Inland Resort.</p>
        <a href="book.php" class="btn btn-lg btn-warning rounded-pill px-5 animate__animated animate__fadeInUp animate__delay-1s">Book Your Stay</a>
    </div>
</header>

<?php
// Fetch latest announcement safely
$announcementQuery = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 1");
if ($announcementQuery && $announcementQuery->num_rows > 0):
    $announcement = $announcementQuery->fetch_assoc();
?>
<section class="container announcement-section">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="alert alert-warning border-0 shadow-lg p-4 animate__animated animate__fadeInUp">
                <div class="d-flex align-items-center">
                    <span class="fs-2 me-3">📢</span>
                    <div>
                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                        <p class="mb-0 text-dark"><?php echo htmlspecialchars($announcement['message']); ?></p>
                        <small class="text-muted mt-2 d-block">Posted on: <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<main class="text-center py-5">
    <div class="container">
        <h2 class="fw-bold mb-3" style="color: var(--tropical-green);">Welcome to Avianna's Inland Resort</h2>
        <p class="lead text-muted mb-4">Discover a hidden gem nestled in the heart of nature, where tranquility meets luxury. At Avianna's, we offer an unforgettable escape from the ordinary, surrounded by lush greenery and serene landscapes.</p>
        
        <p class="mb-4">Whether you're seeking a romantic getaway, a family adventure, or a peaceful retreat, Avianna's Inland Resort is your perfect destination. Experience our world-class amenities, warm hospitality, and breathtaking views that will leave you rejuvenated and inspired.</p>
        <p class="mb-4">Book your stay with us today and immerse yourself in the ultimate tropical sanctuary. We can't wait to welcome you to Avianna's Inland Resort, where unforgettable memories are made.</p>
        
        <div class="row justify-content-center mb-4">
            <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded shadow-sm bg-white">
                    <h5 class="fw-bold mb-2">🕒 Operating Hours</h5>
                    <p class="mb-0 text-dark">Open Daily: <strong>8:00 AM - 10:00 PM</strong></p>
                    <small class="text-muted d-block">Book your stay 2-3 days in advance</small>
                    <small class="text-muted">*Hours may vary on holidays</small>
                </div>
            </div>
        </div>

        <a href="aboutus.php" class="btn btn-outline-dark rounded-pill px-4">Learn More About Us</a>
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

<!-- External JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>