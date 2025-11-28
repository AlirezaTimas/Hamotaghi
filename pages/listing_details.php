<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Initialize variables
$successMessage = '';
$errorMessage = '';
$listing = null;
$owner = null;
$currentUser = null;
$hasRequested = false;
$canSendRequest = false;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];

// Get listing ID from URL (support both 'id' and 'listing_id' parameters)
$listingId = isset($_GET['listing_id']) ? (int)$_GET['listing_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($listingId <= 0) {
    header("Location: available_houses.php?error=invalid_listing");
    exit();
}

// Fetch current user information
try {
    $userStmt = $conn->prepare("SELECT id, full_name, usertype, gender, email, phone FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentUser) {
        header("Location: logout.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching user: " . $e->getMessage());
    header("Location: login.php");
    exit();
}

// Fetch listing (house) information
try {
    $listingStmt = $conn->prepare("
        SELECT 
            h.*,
            u.id as owner_id,
            u.full_name as owner_name,
            u.email as owner_email,
            u.phone as owner_phone,
            u.user_score as owner_rating,
            u.created_at as owner_joined_date,
            COALESCE((SELECT COUNT(*) FROM matches WHERE house_id = h.id AND status = 'تایید شده'), 0) as previous_tenants,
            COALESCE((SELECT COUNT(*) FROM reviews WHERE reviewee_id = u.id), 0) as owner_reviews_count,
            COALESCE((SELECT AVG(rating) FROM reviews WHERE reviewee_id = u.id), 0) as owner_avg_rating
        FROM houses h
        JOIN users u ON h.user_id = u.id
        WHERE h.id = ?
    ");
    $listingStmt->execute([$listingId]);
    $listing = $listingStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$listing) {
        header("Location: available_houses.php?error=listing_not_found");
        exit();
    }
    
    // Set owner information
    $owner = [
        'id' => (int)($listing['owner_id'] ?? 0),
        'name' => $listing['owner_name'] ?? '',
        'email' => $listing['owner_email'] ?? '',
        'phone' => $listing['owner_phone'] ?? '',
        'rating' => (float)($listing['owner_rating'] ?? 0.0),
        'avg_rating' => (float)($listing['owner_avg_rating'] ?? 0.0),
        'reviews_count' => (int)($listing['owner_reviews_count'] ?? 0),
        'previous_tenants' => (int)($listing['previous_tenants'] ?? 0),
        'joined_date' => $listing['owner_joined_date'] ?? null
    ];
    
} catch (PDOException $e) {
    error_log("Error fetching listing: " . $e->getMessage());
    header("Location: available_houses.php?error=database_error");
    exit();
}

// Check if user has already sent a request
try {
    // Check both requests and matches tables for compatibility
    $checkRequests = $conn->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0;
    $checkMatches = $conn->query("SHOW TABLES LIKE 'matches'")->rowCount() > 0;
    
    if ($checkRequests) {
        $requestStmt = $conn->prepare("
            SELECT request_id, request_status 
            FROM requests 
            WHERE listing_id = ? AND sender_user_id = ?
        ");
        $requestStmt->execute([$listingId, $userId]);
        $existingRequest = $requestStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingRequest) {
            $hasRequested = true;
        }
    }
    
    if (!$hasRequested && $checkMatches) {
        $matchStmt = $conn->prepare("
            SELECT id, status 
            FROM matches 
            WHERE house_id = ? AND user_id = ?
        ");
        $matchStmt->execute([$listingId, $userId]);
        $existingMatch = $matchStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingMatch) {
            $hasRequested = true;
        }
    }
} catch (PDOException $e) {
    error_log("Error checking existing request: " . $e->getMessage());
}

// Determine if user can send request
$isOwner = ((int)($listing['user_id'] ?? 0) === $userId);
$genderMatch = (($currentUser['gender'] ?? '') === ($listing['gender'] ?? ''));
$hasCapacity = ((int)($listing['available_capacity'] ?? 0) > 0);
$isRoommate = (($currentUser['usertype'] ?? '') === 'هم‌خانه');

$canSendRequest = !$isOwner && $isRoommate && $genderMatch && $hasCapacity && !$hasRequested;

// Parse amenities
$amenities = [];
if (!empty($listing['amenities'])) {
    $amenities = array_map('trim', explode(',', $listing['amenities']));
    $amenities = array_filter($amenities);
}

// Parse images
$images = [];
if (!empty($listing['images'])) {
    $decoded = json_decode($listing['images'], true);
    $images = is_array($decoded) ? $decoded : [$listing['images']];
}
if (empty($images)) {
    $images = ['assets/images/roommates.jpg'];
}

// Calculate member since
$memberSince = 0;
if (!empty($owner['joined_date'])) {
    $joinedYear = (int)date('Y', strtotime($owner['joined_date']));
    $currentYear = (int)date('Y');
    $memberSince = max(0, $currentYear - $joinedYear);
}

// Get owner's active listings count
try {
    $activeListingsStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM houses 
        WHERE user_id = ? AND status = 'فعال'
    ");
    $activeListingsStmt->execute([$owner['id']]);
    $activeListings = $activeListingsStmt->fetch(PDO::FETCH_ASSOC);
    $owner['active_listings'] = (int)($activeListings['count'] ?? 0);
} catch (PDOException $e) {
    $owner['active_listings'] = 0;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($listing['title']); ?> - جزئیات خانه | هم‌اتاقی</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --navy-dark: #0f1a21;
            --navy-darker: #0b1419;
            --navy-black: #080f13;
            --gold: #ffab00;
            --gold-dark: #ff8f00;
            --gold-light: #ffd54f;
            --text-light: #ffffff;
            --text-muted: #e0e0e0;
            --card-bg: rgba(15, 26, 33, 0.85);
            --card-border: rgba(255, 171, 0, 0.25);
            --success: #4caf50;
            --warning: #ff9800;
            --info: #2196f3;
            --danger: #f44336;
            --purple: #8b5cf6;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: 
                linear-gradient(135deg, var(--navy-black) 0%, var(--navy-darker) 50%, var(--navy-dark) 100%),
                url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.08'/%3E%3C/svg%3E");
            color: var(--text-light);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* افکت‌های بک‌گراند */
        #particles-js {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .grid-lines {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.1;
            pointer-events: none;
            background-image: 
                linear-gradient(rgba(255, 171, 0, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 171, 0, 0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridMove 20s linear infinite;
        }

        .light-spot {
            position: fixed;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 171, 0, 0.1) 0%, transparent 70%);
            animation: floatLight 15s ease-in-out infinite;
            z-index: -1;
        }

        .light-spot:nth-child(1) { top: 20%; left: 10%; animation-delay: 0s; }
        .light-spot:nth-child(2) { top: 60%; left: 80%; animation-delay: 5s; }
        .light-spot:nth-child(3) { top: 80%; left: 20%; animation-delay: 10s; }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid #4caf50;
            color: #4caf50;
        }
        
        .alert-error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid #f44336;
            color: #f44336;
        }
        
        .alert-info {
            background: rgba(33, 150, 243, 0.2);
            border: 1px solid #2196f3;
            color: #2196f3;
        }
        
        /* ناوبری */
        .main-nav {
            background: rgba(11, 20, 25, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--card-border);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
            font-weight: 800;
            color: var(--gold);
            text-decoration: none;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: rgba(255, 171, 0, 0.1);
            color: var(--gold-light);
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 171, 0, 0.2);
        }

        .nav-btn:hover {
            background: rgba(255, 171, 0, 0.2);
            transform: translateY(-2px);
        }

        .nav-btn.logout {
            background: rgba(244, 67, 54, 0.1);
            color: #ff5252;
            border-color: rgba(255, 82, 82, 0.3);
        }

        .nav-btn.logout:hover {
            background: rgba(244, 67, 54, 0.2);
            transform: translateY(-2px);
        }
        
        .listing-header {
            background: rgba(15, 26, 33, 0.85);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 171, 0, 0.25);
        }
        
        .listing-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #ffab00;
        }
        
        .listing-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
            color: #aeb4c4;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .meta-item i {
            color: #ffab00;
        }
        
        .listing-price {
            font-size: 1.8rem;
            font-weight: 700;
            color: #4caf50;
            margin: 20px 0;
        }
        
        .images-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 30px 0;
        }
        
        .gallery-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid rgba(255, 171, 0, 0.3);
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 968px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .details-card {
            background: rgba(15, 26, 33, 0.85);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid rgba(255, 171, 0, 0.25);
        }
        
        .card-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #ffab00;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #aeb4c4;
            font-weight: 500;
        }
        
        .info-value {
            color: #fff;
            font-weight: 600;
        }
        
        .amenities-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .amenity-badge {
            background: rgba(255, 171, 0, 0.1);
            border: 1px solid rgba(255, 171, 0, 0.3);
            padding: 8px 15px;
            border-radius: 20px;
            color: #ffab00;
            font-size: 0.9rem;
        }
        
        .owner-card {
            background: rgba(15, 26, 33, 0.85);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid rgba(255, 171, 0, 0.25);
            text-align: center;
        }
        
        .owner-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffab00, #ff8f00);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: #fff;
            margin: 0 auto 20px;
        }
        
        .owner-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #fff;
        }
        
        .owner-rating {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 15px 0;
        }
        
        .stars {
            color: #ffab00;
        }
        
        .rating-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ffab00;
        }
        
        .owner-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-box {
            background: rgba(255, 171, 0, 0.1);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid rgba(255, 171, 0, 0.2);
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffab00;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #aeb4c4;
            margin-top: 5px;
        }
        
        .request-section {
            background: rgba(15, 26, 33, 0.85);
            border-radius: 16px;
            padding: 30px;
            margin-top: 30px;
            border: 1px solid rgba(255, 171, 0, 0.25);
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ffab00, #ff8f00);
            color: #0b1419;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 171, 0, 0.4);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .request-form {
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #aeb4c4;
            font-weight: 500;
        }
        
        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: #fff;
            font-family: 'Tajawal', sans-serif;
            font-size: 1rem;
        }
        
        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: #ffab00;
            background: rgba(255, 255, 255, 0.12);
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .capacity-info {
            background: rgba(33, 150, 243, 0.1);
            border: 1px solid rgba(33, 150, 243, 0.3);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .capacity-info.full {
            background: rgba(244, 67, 54, 0.1);
            border-color: rgba(244, 67, 54, 0.3);
        }

        @keyframes gridMove {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(-50px) translateY(-50px); }
        }

        @keyframes floatLight {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(100px, -50px) scale(1.2); }
            50% { transform: translate(-50px, 100px) scale(0.8); }
            75% { transform: translate(-100px, -100px) scale(1.1); }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

<!-- بک‌گراند پویا -->
<div id="particles-js"></div>
<div class="grid-lines"></div>
<div class="light-spot"></div>
<div class="light-spot"></div>
<div class="light-spot"></div>

<!-- ناوبری اصلی -->
<nav class="main-nav">
    <div class="nav-container">
        <a href="../index.php" class="brand">
            <i class="fas fa-home-heart"></i>
            هم‌اتاقی
        </a>
        
        <div class="nav-actions">
            <a href="available_houses.php" class="nav-btn">
                <i class="fas fa-home"></i>
                خانه‌ها
            </a>
            <a href="my_requests.php" class="nav-btn">
                <i class="fas fa-envelope"></i>
                درخواست‌های من
            </a>
            <a href="dashboard.php" class="nav-btn">
                <i class="fas fa-tachometer-alt"></i>
                داشبورد
            </a>
            <a href="logout.php" class="nav-btn logout">
                <i class="fas fa-sign-out-alt"></i>
                خروج
            </a>
        </div>
    </div>
</nav>

    <div class="container">

        <!-- Success/Error Messages -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Listing Header -->
        <div class="listing-header">
            <h1 class="listing-title"><?php echo htmlspecialchars($listing['title']); ?></h1>
            
            <div class="listing-meta">
                <div class="meta-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?php echo htmlspecialchars($listing['address']); ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-city"></i>
                    <span><?php echo htmlspecialchars($listing['city'] . '، ' . $listing['province']); ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-users"></i>
                    <span>ظرفیت: <?php echo (int)$listing['capacity']; ?> نفر</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-user-friends"></i>
                    <span>جای خالی: <?php echo (int)($listing['available_capacity'] ?? 0); ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-<?php echo $listing['gender'] === 'خانم' ? 'female' : 'male'; ?>"></i>
                    <span><?php echo htmlspecialchars($listing['gender']); ?></span>
                </div>
            </div>
            
            <div class="listing-price">
                <i class="fas fa-money-bill-wave"></i>
                <?php echo number_format((int)$listing['price']); ?> تومان
            </div>
        </div>

        <!-- Images Gallery -->
        <?php if (!empty($images)): ?>
        <div class="images-gallery">
            <?php foreach ($images as $image): ?>
                <img src="../<?php echo htmlspecialchars($image); ?>" 
                     alt="<?php echo htmlspecialchars($listing['title']); ?>" 
                     class="gallery-image">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Details Grid -->
        <div class="details-grid">
            <!-- Left Column: Listing Details -->
            <div>
                <!-- Description -->
                <?php if (!empty($listing['description'])): ?>
                <div class="details-card">
                    <h2 class="card-title">
                        <i class="fas fa-info-circle"></i>
                        توضیحات
                    </h2>
                    <p style="line-height: 1.8; color: #aeb4c4;">
                        <?php echo nl2br(htmlspecialchars($listing['description'])); ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Amenities -->
                <?php if (!empty($amenities)): ?>
                <div class="details-card">
                    <h2 class="card-title">
                        <i class="fas fa-concierge-bell"></i>
                        امکانات
                    </h2>
                    <div class="amenities-list">
                        <?php foreach ($amenities as $amenity): ?>
                            <span class="amenity-badge">
                                <i class="fas fa-check"></i> <?php echo htmlspecialchars($amenity); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Rules -->
                <?php if (!empty($listing['rules'])): ?>
                <div class="details-card">
                    <h2 class="card-title">
                        <i class="fas fa-clipboard-list"></i>
                        قوانین خانه
                    </h2>
                    <p style="line-height: 1.8; color: #aeb4c4;">
                        <?php echo nl2br(htmlspecialchars($listing['rules'])); ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Listing Information -->
                <div class="details-card">
                    <h2 class="card-title">
                        <i class="fas fa-list-alt"></i>
                        اطلاعات خانه
                    </h2>
                    <div class="info-row">
                        <span class="info-label">آدرس:</span>
                        <span class="info-value"><?php echo htmlspecialchars($listing['address']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">شهر:</span>
                        <span class="info-value"><?php echo htmlspecialchars($listing['city']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">استان:</span>
                        <span class="info-value"><?php echo htmlspecialchars($listing['province']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">ظرفیت کل:</span>
                        <span class="info-value"><?php echo (int)$listing['capacity']; ?> نفر</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">جای خالی:</span>
                        <span class="info-value"><?php echo (int)($listing['available_capacity'] ?? 0); ?> نفر</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">جنسیت:</span>
                        <span class="info-value"><?php echo htmlspecialchars($listing['gender']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">وضعیت:</span>
                        <span class="info-value"><?php echo htmlspecialchars($listing['status'] ?? 'فعال'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">تاریخ ثبت:</span>
                        <span class="info-value"><?php echo date('Y/m/d', strtotime($listing['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Right Column: Owner Information -->
            <div>
                <div class="owner-card">
                    <div class="owner-avatar">
                        <?php echo mb_substr($owner['name'], 0, 1); ?>
                    </div>
                    <h3 class="owner-name"><?php echo htmlspecialchars($owner['name']); ?></h3>
                    <p style="color: #aeb4c4; margin-bottom: 15px;">صاحب خانه</p>
                    
                    <?php if ($owner['avg_rating'] > 0 || $owner['rating'] > 0): ?>
                    <div class="owner-rating">
                        <div class="stars">
                            <?php 
                            $rating = $owner['avg_rating'] > 0 ? $owner['avg_rating'] : $owner['rating'];
                            $fullStars = floor($rating);
                            for ($i = 1; $i <= 5; $i++): 
                            ?>
                                <i class="fas fa-star <?php echo $i <= $fullStars ? '' : 'far'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="rating-value"><?php echo number_format($rating, 1); ?></span>
                        <?php if ($owner['reviews_count'] > 0): ?>
                            <span style="color: #aeb4c4; font-size: 0.9rem;">
                                (<?php echo $owner['reviews_count']; ?> نظر)
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="owner-stats">
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $owner['previous_tenants']; ?></div>
                            <div class="stat-label">هم‌خانه قبلی</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $owner['active_listings']; ?></div>
                            <div class="stat-label">خانه فعال</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $memberSince; ?></div>
                            <div class="stat-label">سال عضویت</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $owner['reviews_count']; ?></div>
                            <div class="stat-label">نظر کاربران</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Send Request Section -->
        <div class="request-section">
            <h2 class="card-title">
                <i class="fas fa-paper-plane"></i>
                ارسال درخواست اقامت
            </h2>
            
            <?php if ($isOwner): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    شما صاحب این خانه هستید و نمی‌توانید برای آن درخواست ارسال کنید.
                </div>
            <?php elseif (!$isRoommate): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    فقط کاربران با نقش "هم‌خانه" می‌توانند درخواست ارسال کنند.
                </div>
            <?php elseif (!$genderMatch): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    این خانه مخصوص <?php echo htmlspecialchars($listing['gender']); ?>ها است و با جنسیت شما تطابق ندارد.
                </div>
            <?php elseif (!$hasCapacity): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    ظرفیت این خانه تکمیل شده است.
                </div>
            <?php elseif ($hasRequested): ?>
                <div class="alert alert-info">
                    <i class="fas fa-check-circle"></i>
                    شما قبلاً برای این خانه درخواست ارسال کرده‌اید. می‌توانید وضعیت درخواست را از بخش "درخواست‌های من" مشاهده کنید.
                </div>
                <a href="my_requests.php" class="btn btn-secondary">
                    <i class="fas fa-envelope"></i> مشاهده درخواست‌های من
                </a>
            <?php else: ?>
                <?php if (!$hasCapacity): ?>
                <div class="capacity-info full">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>ظرفیت تکمیل شده:</strong> این خانه دیگر جای خالی ندارد.
                </div>
                <?php else: ?>
                <div class="capacity-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>جای خالی:</strong> <?php echo (int)($listing['available_capacity'] ?? 0); ?> نفر
                </div>
                <?php endif; ?>
                
                <form id="requestForm" class="request-form">
                    <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
                    <input type="hidden" name="receiver_owner_id" value="<?php echo $owner['id']; ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="message">
                            <i class="fas fa-comment"></i> پیام به صاحب خانه
                        </label>
                        <textarea 
                            id="message" 
                            name="message" 
                            class="form-textarea" 
                            placeholder="خودتان را معرفی کنید و دلیل علاقه‌مندی به این خانه را بیان نمایید..."
                            required></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label" for="move_in_date">
                                <i class="fas fa-calendar"></i> تاریخ ورود
                            </label>
                            <input 
                                type="date" 
                                id="move_in_date" 
                                name="move_in_date" 
                                class="form-input"
                                min="<?php echo date('Y-m-d'); ?>"
                                required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="duration">
                                <i class="fas fa-clock"></i> مدت اقامت
                            </label>
                            <select id="duration" name="duration" class="form-select" required>
                                <option value="">انتخاب کنید</option>
                                <option value="1 ماه">1 ماه</option>
                                <option value="3 ماه">3 ماه</option>
                                <option value="6 ماه">6 ماه</option>
                                <option value="1 سال">1 سال</option>
                                <option value="بیش از 1 سال">بیش از 1 سال</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="budget">
                            <i class="fas fa-money-bill-wave"></i> بودجه پیشنهادی (تومان)
                        </label>
                        <input 
                            type="number" 
                            id="budget" 
                            name="budget" 
                            class="form-input" 
                            placeholder="مثال: 2500000"
                            min="0"
                            value="<?php echo (int)$listing['price']; ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-paper-plane"></i>
                        ارسال درخواست
                    </button>
                </form>
                
                <div id="requestResult" style="margin-top: 20px;"></div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Handle form submission with AJAX
    document.getElementById('requestForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const form = this;
        const submitBtn = document.getElementById('submitBtn');
        const resultDiv = document.getElementById('requestResult');
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ارسال...';
        
        // Get form data
        const formData = new FormData(form);
        
        try {
            const response = await fetch('send_listing_request.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        ${result.message}
                    </div>
                `;
                form.reset();
                form.style.display = 'none';
                
                // Redirect after 2 seconds
                setTimeout(() => {
                    window.location.href = 'my_requests.php';
                }, 2000);
            } else {
                resultDiv.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        ${result.message}
                    </div>
                `;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> ارسال درخواست';
            }
        } catch (error) {
            resultDiv.innerHTML = `
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    خطا در ارسال درخواست. لطفاً دوباره تلاش کنید.
                </div>
            `;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> ارسال درخواست';
            console.error('Error:', error);
        }
    });
    </script>

<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
<script>
// پارتیکل‌های پیشرفته
particlesJS("particles-js", {
  particles: {
    number: { value: 40, density: { enable: true, value_area: 800 } },
    color: { value: ["#ffab00", "#8b5cf6", "#4caf50", "#2196f3"] },
    shape: { type: "circle" },
    opacity: { value: 0.3, random: true },
    size: { value: 3, random: true },
    line_linked: { 
      enable: true, 
      distance: 120, 
      color: "#ffab00", 
      opacity: 0.2, 
      width: 1 
    },
    move: { 
      enable: true, 
      speed: 1, 
      direction: "none", 
      random: true, 
      straight: false, 
      out_mode: "out" 
    }
  },
  interactivity: {
    detect_on: "canvas",
    events: { onhover: { enable: true, mode: "repulse" } }
  }
});

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
</script>
</body>
</html>

