<?php 
include "conn.php"; 

// Define Gallery Data — paths explicitly synchronized for case safety
$gallery_items = [
    [
        "title" => "Cottage 1,2,3",
        "desc"  => "Relax and unwind in our classic, native-style bamboo cottage.",
        "cover" => "img/Cottage123_1.jpg",
        "photos" => [
            "img/Cottage123_1.jpg",
            "img/Cottage123_2.jpg",
            "img/Cottage123_3.jpg",
            "img/Cottage123_4.jpg",
            "img/Cottage123_5.jpg",
            "img/Cottage123_6.jpg",
        ]
    ],
    [
        "title" => "Cottage 5",
        "desc"  => "Perfect for family gatherings and outdoor picnics.",
        "cover" => "img/Cottage_5_1.jpg",
        "photos" => [
            "img/Cottage_5_1.jpg",
            "img/Cottage_5_2.jpg",
            "img/Cottage_5_3.jpg",
            
        ]
    ],
    [
        "title" => "Cottage 6",
        "desc"  => "Enjoy the local breeze in our affordable native cottages.",
        "cover" => "img/Cottage_6_1.jpg",
        "photos" => [
            "img/Cottage_6_1.jpg",
            "img/Cottage_6_2.jpg",
            "img/Cottage_6_3.jpg",
        ]
    ],
    [
        "title" => "Large Pavillion",
        "desc"  => "Ideal for events, workshops, or group activities with a spacious open-air design.",
        "cover" => "img/LargePavillion_1.jpg",
        "photos" => [
            "img/LargePavillion_1.jpg",
            "img/LargePavillion_2.jpg",
            "img/LargePavillion_3.jpg",
            "img/LargePavillion_4.jpg",
        ]
    ],
    [
        "title" => "Overnight ",
        "desc"  => "Spacious wooden table arrangements and long benches.",
        "cover" => "img/Overnight_1.jpg",
        "photos" => [
            "img/Overnight_1.jpg",
            "img/Overnight_2.jpg",
            "img/Overnight_3.jpg",
            "img/Overnight_4.jpg",
            "img/Overnight_5.jpg",
            "img/Overnight_6.jpg",
        ]
    ],
    [
        "title" => "Pavilion_ 1",
        "desc"  => "Shaded, comfortable bamboo flooring perfect for resting.",
        "cover" => "img/Semi_open_1.jpg",
        "photos" => [
            "img/Semi_open_1.jpg",
            "img/Semi_open_2.jpg",
            "img/Semi_open_3.jpg",
            "img/Semi_open_4.jpg",
        ]
    ],
    [
        "title" => "Poolside_Pavilion",
        "desc"  => "Shaded, comfortable bamboo flooring perfect for resting.",
        "cover" => "img/PoolSide_1.jpg",
        "photos" => [
            "img/PoolSide_1.jpg",
            "img/PoolSide_2.jpg",
            "img/PoolSide_3.jpg",
            "img/PoolSide_4.jpg",
            "img/PoolSide_5.jpg",
            "img/PoolSide_6.jpg",
            "img/PoolSide_7.jpg",
        ]
    ],
    [
        "title" => "Private SoundProof Room",
        "desc"  => "The perfect space for meetings, workshops, or intimate gatherings.",
        "cover" => "img/SoundProof_1.jpg",
        "photos" => [
            "img/SoundProof_1.jpg",
            "img/SoundProof_2.jpg",
            "img/SoundProof_3.jpg",
            "img/SoundProof_4.jpg",
            "img/SoundProof_5.jpg",
        ]
    ],
    [
        "title" => "Tee pee hut",
        "desc"  => "Take a refreshing dip in our inviting swimming pool.",
        "cover" => "img/Tee_put_1.jpg",
        "photos" => [
            "img/Tee_put_1.jpg",
            "img/Tee_put_2.jpg",
            "img/Tee_put_3.jpg",
            "img/Tee_put_4.jpg",
            "img/Tee_put_5.jpg",
        ]
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery | Avianna's Inland Resort</title>
    <link rel="icon"  type="image/png" href="img/avianna.png" >
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
    :root {
        --tropical-green: #1a4731;
        --accent-gold: #ffc107;
        --deep-palm: #0e2a1d;
        --overlay-bg: rgba(14, 42, 29, 0.82);
    }

    body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; color: #333; }
    h1, .navbar-brand, h4, h5 { font-family: 'Playfair Display', serif; }

    .navbar {
        background-color: var(--tropical-green) !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .header-gallery {
        background: url('img/Avianna_bg.jpg') no-repeat;
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        color: white;
        padding: 120px 0;
    }

    .gallery-card {
        border-radius: 18px;
        overflow: hidden;
        position: relative;
        box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        cursor: pointer;
        height: 100%;
        transition: transform 0.35s ease, box-shadow 0.35s ease;
    }
    .gallery-card:hover { transform: translateY(-6px); box-shadow: 0 20px 45px rgba(0,0,0,0.2); }

    .gallery-img-container {
        position: relative;
        aspect-ratio: 4/3;
        overflow: hidden;
        background-color: #e9ecef;
    }
    
    .gallery-img-container img {
        width: 100%; height: 100%;
        object-fit: cover;
        transition: transform 0.7s ease;
    }
    .gallery-card:hover .gallery-img-container img { transform: scale(1.1); }

    .gallery-overlay {
        position: absolute; inset: 0;
        background: var(--overlay-bg);
        color: white;
        display: flex; align-items: center; justify-content: center; text-align: center;
        opacity: 0;
        transition: opacity 0.35s ease;
        padding: 24px;
        backdrop-filter: blur(3px);
    }
    .gallery-card:hover .gallery-overlay { opacity: 1; }

    .overlay-text h4 {
        color: var(--accent-gold);
        margin-bottom: 8px;
        transform: translateY(16px);
        transition: transform 0.35s ease 0.05s;
    }

    .overlay-text p {
        font-size: 0.88rem; font-weight: 300;
        transform: translateY(16px);
        transition: transform 0.35s ease 0.12s;
    }
    .overlay-text .view-btn {
        margin-top: 12px;
        transform: translateY(16px);
        transition: transform 0.35s ease 0.18s;
    }
    .gallery-card:hover .overlay-text h4,
    .gallery-card:hover .overlay-text p,
    .gallery-card:hover .overlay-text .view-btn { transform: translateY(0); }

    #lightboxModal .modal-content {
        background: var(--deep-palm);
        border: none;
        border-radius: 16px;
        color: white;
    }
    #lightboxModal .modal-header {
        border-bottom: 1px solid rgba(255,255,255,0.1);
        padding: 16px 20px;
    }
    #lightboxModal .modal-title { color: var(--accent-gold); }
    #lightboxModal .btn-close { filter: invert(1); }

    #lightbox-main {
        width: 100%; aspect-ratio: 16/9;
        object-fit: cover;
        border-radius: 10px;
        transition: opacity 0.25s ease;
    }

    .thumb-strip { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
    .thumb-strip img {
        width: 72px; height: 56px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        border: 2px solid transparent;
        transition: border-color 0.2s, transform 0.2s;
        opacity: 0.7;
    }
    .thumb-strip img:hover { opacity: 1; transform: scale(1.05); }
    .thumb-strip img.active { border-color: var(--accent-gold); opacity: 1; }

    .lb-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
    .lb-nav button {
        background: rgba(255,255,255,0.12);
        border: none; color: white;
        border-radius: 50%; width: 38px; height: 38px;
        font-size: 1.1rem; cursor: pointer;
        transition: background 0.2s;
    }
    .lb-nav button:hover { background: var(--accent-gold); color: #000; }
    .lb-nav .lb-counter { font-size: 0.85rem; opacity: 0.7; }

    footer { background-color: var(--deep-palm) !important; border-top: 5px solid var(--accent-gold); }
    .btn-warning { background-color: var(--accent-gold); border: none; color: #000; }
    .btn-warning:hover { background-color: #e5af06; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container">
        <a href="index.php">
            <img src="img/avianna.png" alt="Avianna Logo"
                 style="width:50px;height:auto;margin-bottom:5px;"
                 class="animate__animated animate__fadeIn shadow-sm rounded-circle">
        </a>
        <a class="navbar-brand fw-bold ms-2" href="index.php">Avianna's Inland Resort</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarGallery"
                aria-controls="navbarGallery" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarGallery">
        <div class="ms-auto">
            <a href="index.php"   class="btn btn-sm btn-outline-light rounded-pill px-3 me-2">Home</a>
            <a href="aboutus.php" class="btn btn-sm btn-outline-light rounded-pill px-3 me-2">About</a>
            <a href="gallery.php" class="btn btn-sm btn-light rounded-pill px-3 me-2 text-dark">Gallery</a>
            <a href="reviews.php" class="btn btn-sm btn-outline-light rounded-pill px-3 me-2">Reviews</a>
            <a href="book.php"    class="btn btn-sm btn-warning rounded-pill px-3 fw-bold">Book Now</a>
        </div>
        </div>
    </div>
</nav>

<header class="header-gallery text-center">
    <div class="container">
        <h1 class="display-4 fw-bold animate__animated animate__fadeInDown">Resort Gallery</h1>
        <p class="lead animate__animated animate__fadeInUp">A glimpse into your next tropical escape.</p>
    </div>
</header>

<main class="container my-5">
    <div class="row g-4">
        <?php foreach ($gallery_items as $index => $item): ?>
        <div class="col-lg-4 col-md-6 col-sm-12">
            <div class="gallery-card"
                 onclick="openLightbox(<?php echo $index; ?>)"
                 role="button"
                 aria-label="View photos of <?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="gallery-img-container">
                    <img src="<?php echo htmlspecialchars($item['cover'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="gallery-overlay">
                        <div class="overlay-text">
                            <h4><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                            <p><?php echo htmlspecialchars($item['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="view-btn">
                                <span class="badge bg-warning text-dark px-3 py-2" style="font-size:0.8rem;">
                                    📷 View <?php echo count($item['photos']); ?> Photo<?php echo count($item['photos']) > 1 ? 's' : ''; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header">
                <h5 class="modal-title" id="lightbox-title">Cottage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <img id="lightbox-main" src="" alt="Gallery Photo">

                <div class="lb-nav" id="lb-nav-row">
                    <button onclick="lbStep(-1)" title="Previous">&#8592;</button>
                    <span class="lb-counter" id="lb-counter"></span>
                    <button onclick="lbStep(1)"  title="Next">&#8594;</button>
                </div>

                <div class="thumb-strip" id="thumb-strip"></div>

                <p id="lightbox-desc" class="mt-3 mb-0" style="font-size:0.9rem;opacity:0.8;"></p>
            </div>
        </div>
    </div>
</div>

<footer class="text-white text-center py-4">
    <div class="container">
        <p class="mb-0">&copy; 2026 Avianna's Inland Resort. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const galleryData = <?php echo json_encode($gallery_items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

let currentCottage = 0;
let currentPhoto   = 0;
let lbModal        = null;
let fadeTimeout    = null;

function openLightbox(cottageIndex) {
    currentCottage = cottageIndex;
    currentPhoto   = 0;

    const item = galleryData[cottageIndex];
    document.getElementById('lightbox-title').textContent = item.title;
    document.getElementById('lightbox-desc').textContent  = item.desc;

    buildThumbs(item.photos);
    showPhoto(0);

    document.getElementById('lb-nav-row').style.display =
        item.photos.length > 1 ? 'flex' : 'none';

    if (!lbModal) lbModal = new bootstrap.Modal(document.getElementById('lightboxModal'));
    lbModal.show();
}

function buildThumbs(photos) {
    const strip = document.getElementById('thumb-strip');
    strip.innerHTML = '';
    photos.forEach((src, i) => {
        const img = document.createElement('img');
        img.src = src;
        img.alt = 'Thumbnail ' + (i + 1);
        img.onclick = () => showPhoto(i);
        if (i === 0) img.classList.add('active');
        strip.appendChild(img);
    });
}

function showPhoto(index) {
    const photos = galleryData[currentCottage].photos;
    if (index < 0) index = photos.length - 1;
    if (index >= photos.length) index = 0;
    currentPhoto = index;

    const main = document.getElementById('lightbox-main');
    
    if (fadeTimeout) clearTimeout(fadeTimeout);

    main.style.opacity = 0;
    fadeTimeout = setTimeout(() => { 
        main.src = photos[index]; 
        main.style.opacity = 1; 
    }, 150);

    document.getElementById('lb-counter').textContent = (index + 1) + ' / ' + photos.length;

    document.querySelectorAll('#thumb-strip img').forEach((img, i) => {
        img.classList.toggle('active', i === index);
    });
}

function lbStep(dir) { showPhoto(currentPhoto + dir); }

document.addEventListener('keydown', (e) => {
    const modalEl = document.getElementById('lightboxModal');
    if (!modalEl || !modalEl.classList.contains('show')) return;
    if (e.key === 'ArrowLeft')  lbStep(-1);
    if (e.key === 'ArrowRight') lbStep(1);
});
</script>
</body>
</html>