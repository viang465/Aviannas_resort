<?php
/**
 * gallery_data.php
 * ---------------------------------------------------------------
 * Single source of truth for the resort gallery.
 *
 * - Creates the `gallery_items` / `gallery_photos` tables automatically
 *   the first time it runs (no need to import SQL manually).
 * - Seeds them with the original hard-coded photos so nothing disappears.
 * - Used by index.php, gallery.php and admin/admin_gallery.php.
 *
 * Image paths are stored RELATIVE TO THE SITE ROOT (e.g. "img/PoolSide_1.jpg"
 * or "uploads/gallery/abc123.jpg"). Pages inside /admin must prefix "../".
 */

if (!defined('GALLERY_UPLOAD_DIR')) {
    define('GALLERY_UPLOAD_DIR', 'uploads/gallery');
}

/**
 * The original photo set. Used to seed the database on first run and as a
 * safety net if the database is unreachable.
 */
function gallery_default_items()
{
    return [
        [
            "title" => "Cottage 1,2,3",
            "desc"  => "Relax and unwind in our classic, native-style bamboo cottage.",
            "price" => "400 php",
            "pax"   => "8-12 pax",
            "cover" => "img/Cottage123_1.jpg",
            "photos" => [
                "img/Cottage123_1.jpg", "img/Cottage123_2.jpg", "img/Cottage123_3.jpg",
                "img/Cottage123_4.jpg", "img/Cottage123_5.jpg", "img/Cottage123_6.jpg",
            ],
        ],
        [
            "title" => "Cottage 5",
            "desc"  => "Perfect for family gatherings and outdoor picnics.",
            "price" => "400 php",
            "pax"   => "",
            "cover" => "img/Cottage_5_1.jpg",
            "photos" => ["img/Cottage_5_1.jpg", "img/Cottage_5_2.jpg", "img/Cottage_5_3.jpg"],
        ],
        [
            "title" => "Cottage 6",
            "desc"  => "Enjoy the local breeze in our affordable native cottages.",
            "price" => "",
            "pax"   => "",
            "cover" => "img/Cottage_6_1.jpg",
            "photos" => ["img/Cottage_6_1.jpg", "img/Cottage_6_2.jpg", "img/Cottage_6_3.jpg"],
        ],
        [
            "title" => "Large Pavillion",
            "desc"  => "Ideal for events, workshops, or group activities with a spacious open-air design.",
            "price" => "",
            "pax"   => "",
            "cover" => "img/LargePavillion_1.jpg",
            "photos" => [
                "img/LargePavillion_1.jpg", "img/LargePavillion_2.jpg",
                "img/LargePavillion_3.jpg", "img/LargePavillion_4.jpg",
            ],
        ],
        [
            "title" => "Overnight",
            "desc"  => "Spacious wooden table arrangements and long benches.",
            "price" => "",
            "pax"   => "",
            "cover" => "img/Overnight_1.jpg",
            "photos" => [
                "img/Overnight_1.jpg", "img/Overnight_2.jpg", "img/Overnight_3.jpg",
                "img/Overnight_4.jpg", "img/Overnight_5.jpg", "img/Overnight_6.jpg",
            ],
        ],
        [
            "title" => "Pavilion_ 1",
            "desc"  => "Shaded, comfortable bamboo flooring perfect for resting.",
            "price" => "",
            "pax"   => "",
            "cover" => "img/Semi_open_1.jpg",
            "photos" => [
                "img/Semi_open_1.jpg", "img/Semi_open_2.jpg",
                "img/Semi_open_3.jpg", "img/Semi_open_4.jpg",
            ],
        ],
        [
            "title" => "Poolside_Pavilion",
            "desc"  => "Shaded, comfortable bamboo flooring perfect for resting.",
            "price" => "",
            "pax"   => "",
            "cover" => "img/PoolSide_1.jpg",
            "photos" => [
                "img/PoolSide_1.jpg", "img/PoolSide_2.jpg", "img/PoolSide_3.jpg",
                "img/PoolSide_4.jpg", "img/PoolSide_5.jpg", "img/PoolSide_6.jpg",
                "img/PoolSide_7.jpg",
            ],
        ],
        [
            "title" => "Private SoundProof Room",
            "desc"  => "The perfect space for meetings, workshops, or intimate gatherings.",
            "price" => "",
            "pax"   => "",
            "cover" => "img/SoundProof_1.jpg",
            "photos" => [
                "img/SoundProof_1.jpg", "img/SoundProof_2.jpg", "img/SoundProof_3.jpg",
                "img/SoundProof_4.jpg", "img/SoundProof_5.jpg",
            ],
        ],
        [
            "title" => "Tee pee hut",
            "desc"  => "Take a refreshing dip in our inviting swimming pool.",
            "price" => "",
            "pax"   => "",
            "cover" => "img/Tee_put_1.jpg",
            "photos" => [
                "img/Tee_put_1.jpg", "img/Tee_put_2.jpg", "img/Tee_put_3.jpg",
                "img/Tee_put_4.jpg", "img/Tee_put_5.jpg",
            ],
        ],
    ];
}

/**
 * Create the gallery tables if they don't exist yet, then seed them once.
 */
function gallery_ensure_schema($conn)
{
    if (!$conn || $conn->connect_error) {
        return false;
    }

    $itemsSql = "CREATE TABLE IF NOT EXISTS gallery_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        description TEXT,
        price VARCHAR(60) DEFAULT NULL,
        pax VARCHAR(60) DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $photosSql = "CREATE TABLE IF NOT EXISTS gallery_photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        is_cover TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_item (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($itemsSql))  { return false; }
    if (!$conn->query($photosSql)) { return false; }

    // Seed only when completely empty.
    $check = $conn->query("SELECT COUNT(*) AS c FROM gallery_items");
    if ($check && ($row = $check->fetch_assoc()) && (int)$row['c'] === 0) {
        gallery_seed_defaults($conn);
    }

    return true;
}

function gallery_seed_defaults($conn)
{
    $order = 0;
    foreach (gallery_default_items() as $item) {
        $stmt = $conn->prepare(
            "INSERT INTO gallery_items (title, description, price, pax, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?, 1)"
        );
        if (!$stmt) { return; }
        $stmt->bind_param("ssssi", $item['title'], $item['desc'], $item['price'], $item['pax'], $order);
        $stmt->execute();
        $itemId = $stmt->insert_id;
        $stmt->close();

        $p = 0;
        foreach ($item['photos'] as $photo) {
            $isCover = ($photo === $item['cover']) ? 1 : 0;
            $ps = $conn->prepare(
                "INSERT INTO gallery_photos (item_id, image_path, is_cover, sort_order)
                 VALUES (?, ?, ?, ?)"
            );
            if (!$ps) { continue; }
            $ps->bind_param("isii", $itemId, $photo, $isCover, $p);
            $ps->execute();
            $ps->close();
            $p++;
        }
        $order++;
    }
}

/**
 * Load gallery items for display.
 *
 * @param mysqli $conn
 * @param bool   $activeOnly  true for the public site, false for the admin panel
 * @param string $prefix      path prefix for images ("" on root pages, "../" inside /admin)
 * @return array
 */
function gallery_load_items($conn, $activeOnly = true, $prefix = '')
{
    $items = [];

    if (!gallery_ensure_schema($conn)) {
        // Database problem — fall back to the built-in photos so the page still works.
        $fallback = gallery_default_items();
        foreach ($fallback as &$f) {
            $f['id']     = 0;
            $f['cover']  = $prefix . $f['cover'];
            $f['photos'] = array_map(function ($p) use ($prefix) { return $prefix . $p; }, $f['photos']);
        }
        return $fallback;
    }

    $where = $activeOnly ? "WHERE is_active = 1" : "";
    $res = $conn->query("SELECT * FROM gallery_items $where ORDER BY sort_order ASC, id ASC");
    if (!$res) { return $items; }

    while ($row = $res->fetch_assoc()) {
        $itemId = (int)$row['id'];

        $photos  = [];
        $cover   = '';
        $ps = $conn->prepare(
            "SELECT image_path, is_cover FROM gallery_photos
             WHERE item_id = ? ORDER BY sort_order ASC, id ASC"
        );
        if ($ps) {
            $ps->bind_param("i", $itemId);
            $ps->execute();
            $pr = $ps->get_result();
            while ($p = $pr->fetch_assoc()) {
                $path = $prefix . $p['image_path'];
                $photos[] = $path;
                if ((int)$p['is_cover'] === 1 && $cover === '') {
                    $cover = $path;
                }
            }
            $ps->close();
        }

        if (empty($photos)) {
            continue; // an item with no photos has nothing to show
        }
        if ($cover === '') {
            $cover = $photos[0];
        }

        $items[] = [
            'id'        => $itemId,
            'title'     => $row['title'],
            'desc'      => (string)$row['description'],
            'price'     => (string)$row['price'],
            'pax'       => (string)$row['pax'],
            'cover'     => $cover,
            'photos'    => $photos,
            'is_active' => (int)$row['is_active'],
        ];
    }

    return $items;
}
