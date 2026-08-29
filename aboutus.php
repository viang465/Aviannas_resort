<?php include "conn.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | Avianna's Inland Resort</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
    :root {
        --tropical-green: #1a4731;
        --accent-gold: #ffc107;
        --deep-palm: #0e2a1d;
        --soft-cream: #f8f9fa;
        --text-muted: #6c757d;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background-color: #ffffff;
        color: #444;
        line-height: 1.6;
    }

    h1, h2, .navbar-brand {
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

    .hero {
        position: relative;
        min-height: 50vh;
        display: flex;
        flex-direction: column; 
        align-items: center;
        justify-content: center;
        background: url('img/Avianna_bg.jpg') center/cover no-repeat;
        background-color: var(--tropical-green);
        color: white;
    }

    .card-feature {
        background: white;
        padding: 40px 20px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        height: 100%;
        border: 1px solid #eee;
    }

    .card-feature:hover {
        transform: translateY(-12px);
        box-shadow: 0 20px 40px rgba(26, 71, 49, 0.15);
        border-color: var(--accent-gold);
    }

    .icon-bounce {
        font-size: 3rem;
        display: inline-block;
        margin-bottom: 15px;
        color: var(--tropical-green);
    }

    .map-container {
        border-radius: 25px;
        overflow: hidden;
        box-shadow: 0 15px 45px rgba(0,0,0,0.1);
        margin-top: 40px;
        border: 5px solid white;
    }

    iframe {
        width: 100%;
        display: block;
    }

    .text-teal {
        color: var(--tropical-green);
    }

    footer {
        background-color: var(--deep-palm) !important;
        border-top: 4px solid var(--accent-gold);
    }

    a { transition: color 0.3s ease; }

    .btn-warning {
        background-color: var(--accent-gold);
        border: none;
    }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container">
        <img src="img/avianna.png" 
             alt="Avianna Logo" 
             style="width: 50px; height: auto; margin-bottom: 5px;" 
             class="animate__animated animate__fadeIn shadow-sm rounded-circle">
        <a class="navbar-brand fw-bold" href="index.php">Avianna's Inland Resort</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarAbout"
                aria-controls="navbarAbout" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarAbout">
        <div class="ms-auto">
            <a href="index.php" class="btn btn-sm btn-outline-light rounded-pill px-3 me-2">Home</a>
            <a href="aboutus.php" class="btn btn-sm btn-light rounded-pill px-3 me-2 text-dark">About</a>
            <a href="gallery.php" class="btn btn-sm btn-outline-light rounded-pill px-3 me-2">Gallery</a>
            <a href="reviews.php" class="btn btn-sm btn-outline-light rounded-pill px-3 me-2">Reviews</a>
            <a href="book.php" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold">Book Now</a>
        </div>
        </div>
    </div>
</nav>

<header class="hero text-center text-white">
    <div class="container py-5"> 
        <h1 class="display-1 fw-bold animate__animated animate__zoomIn">Our Story</h1>
        <p class="lead mb-4 animate__animated animate__fadeInUp animate__delay-1s">Discover the heart and soul of Avianna's Inland Resort.</p>
    </div>
</header>

<div class="container py-5">
    <div class="row g-4 text-center">
        <div class="col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
            <div class="card-feature">
                <div class="icon-bounce"><i class="bi bi-tree-fill"></i></div>
                <h5 class="fw-bold">Nature Friendly</h5>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <div class="card-feature">
                <div class="icon-bounce"><i class="bi bi-water"></i></div>
                <h5 class="fw-bold">Clean Pools</h5>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
            <div class="card-feature">
                <div class="icon-bounce"><i class="bi bi-house-door-fill"></i></div>
                <h5 class="fw-bold">Luxury Rooms</h5>
            </div>
        </div>
        <div class="col-md-3 animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
            <div class="card-feature">
                <div class="icon-bounce"><i class="bi bi-cup-hot-fill"></i></div>
                <h5 class="fw-bold">Local Food</h5>
            </div>
        </div>
    </div>

    <div class="row mt-5 pt-4">
        <div class="col-md-6 animate__animated animate__fadeInLeft">
            <h2 class="fw-bold mb-4 text-teal">Our Commitment</h2>
            <p class="lead">At Avianna's Inland Resort, we are dedicated to providing an unforgettable experience in a serene natural setting. Our commitment to sustainability and guest satisfaction drives everything we do.</p>
        </div>
        <div class="col-md-6 animate__animated animate__fadeInRight">
            <h2 class="fw-bold mb-4 text-teal">Our Vision</h2>
            <p class="lead">We envision a place where guests can escape the hustle and bustle of everyday life and reconnect with nature. Our goal is to be the premier destination for peaceful retreats.</p>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-md-12">
            <h2 class="fw-bold mb-4 text-center">Our Location</h2>
            <p class="lead text-center mx-auto mb-4" style="max-width: 800px;">Nestled in the heart of the countryside, Avianna's Inland Resort offers breathtaking views and a tranquil atmosphere perfect for your next escape.</p>
            
            <div class="map-container">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d579.3414830161042!2d122.53064800665541!3d10.79774391575876!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33aefd05b0cf787b%3A0xe29cfc1c7abdb84c!2sAvianna%20Inland%20Resort!5e1!3m2!1sen!2sph!4v1787727271126!5m2!1sen!2sph" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>

            <div class="text-center mt-4">
                <p class="mb-1 fw-bold">Zone 6 Cabugao Sur Sta. Barbara, Iloilo City, Philippines</p>
                <p class="mb-1">Email: <a href="mailto:aviannasinlandresort@gmail.com">aviannasinlandresort@gmail.com</a> | Phone: <a href="tel:+639388376967">09388376967</a></p>
            </div>
        </div>
    </div>
</div>

<footer class="bg-dark text-white text-center py-4 mt-5">
    <div class="container">
        <p class="mb-0">&copy; 2026 Avianna's Inland Resort. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>