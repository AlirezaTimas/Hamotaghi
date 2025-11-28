<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// بررسی ورود کاربر
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// دریافت اطلاعات کاربر
try {
    $user_stmt = $conn->prepare("SELECT id, full_name, usertype, email, phone, user_score, created_at FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_user || ($current_user['usertype'] ?? '') !== 'صاحب‌خانه') {
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching user: " . $e->getMessage());
    header("Location: dashboard.php");
    exit();
}

// دریافت house_id از URL
$house_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($house_id <= 0) {
    header("Location: my_houses.php?error=invalid_house");
    exit();
}

// دریافت اطلاعات خانه و اطمینان از اینکه متعلق به کاربر است
$house = null;
$house_photos = [];
$stats = [
    'total_requests' => 0,
    'pending_requests' => 0,
    'approved_requests' => 0,
    'rejected_requests' => 0,
    'current_tenants' => 0,
    'past_tenants' => 0,
    'total_messages' => 0,
    'unread_messages' => 0
];

try {
    // دریافت اطلاعات خانه
    $house_stmt = $conn->prepare("
        SELECT h.*, 
               u.full_name as owner_name,
               u.email as owner_email,
               u.phone as owner_phone,
               u.user_score as owner_score,
               u.created_at as owner_joined_date
        FROM houses h
        JOIN users u ON h.user_id = u.id
        WHERE h.id = ? AND h.user_id = ?
    ");
    $house_stmt->execute([$house_id, $user_id]);
    $house = $house_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$house) {
        header("Location: my_houses.php?error=house_not_found");
        exit();
    }
    
    // دریافت عکس‌های خانه
    try {
        $photos_stmt = $conn->prepare("SELECT file_path, is_primary FROM house_photos WHERE house_id = ? ORDER BY is_primary DESC, id ASC");
        $photos_stmt->execute([$house_id]);
        $house_photos = $photos_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching house photos: " . $e->getMessage());
        $house_photos = [];
    }
    
    // اگر عکسی در house_photos نبود، از images در houses استفاده کن
    if (empty($house_photos) && !empty($house['images'])) {
        $images = json_decode($house['images'], true);
        if (is_array($images)) {
            foreach ($images as $index => $img) {
                $house_photos[] = [
                    'file_path' => $img,
                    'is_primary' => $index === 0 ? 1 : 0
                ];
            }
        } else {
            $house_photos[] = [
                'file_path' => $house['images'],
                'is_primary' => 1
            ];
        }
    }
    
    // اگر هنوز عکسی نبود، از تصویر پیش‌فرض استفاده کن
    if (empty($house_photos)) {
        $house_photos[] = [
            'file_path' => 'assets/images/roommates.jpg',
            'is_primary' => 1
        ];
    }
    
    // دریافت آمار درخواست‌ها
    try {
        // Check which table exists (requests or matches)
        $requestsExists = $conn->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0;
        $matchesExists = $conn->query("SHOW TABLES LIKE 'matches'")->rowCount() > 0;
        
        if ($requestsExists) {
            $stats_stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN request_status = 'pending' OR request_status = 'در انتظار' OR request_status = '' OR request_status IS NULL THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN request_status = 'approved' OR request_status = 'تایید شده' THEN 1 ELSE 0 END) as approved_requests,
                    SUM(CASE WHEN request_status = 'rejected' OR request_status = 'رد شده' THEN 1 ELSE 0 END) as rejected_requests
                FROM requests
                WHERE listing_id = ?
            ");
            $stats_stmt->execute([$house_id]);
            $request_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request_stats) {
                $stats['total_requests'] = (int)($request_stats['total_requests'] ?? 0);
                $stats['pending_requests'] = (int)($request_stats['pending_requests'] ?? 0);
                $stats['approved_requests'] = (int)($request_stats['approved_requests'] ?? 0);
                $stats['rejected_requests'] = (int)($request_stats['rejected_requests'] ?? 0);
            }
            
            // Current tenants (approved requests)
            $tenants_stmt = $conn->prepare("
                SELECT COUNT(*) as current_tenants
                FROM requests
                WHERE listing_id = ? 
                  AND (request_status = 'approved' OR request_status = 'تایید شده')
            ");
            $tenants_stmt->execute([$house_id]);
            $tenants_result = $tenants_stmt->fetch(PDO::FETCH_ASSOC);
            $stats['current_tenants'] = (int)($tenants_result['current_tenants'] ?? 0);
            
        } elseif ($matchesExists) {
            $stats_stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN status = 'در انتظار' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN status = 'تایید شده' THEN 1 ELSE 0 END) as approved_requests,
                    SUM(CASE WHEN status = 'رد شده' THEN 1 ELSE 0 END) as rejected_requests
                FROM matches
                WHERE house_id = ?
            ");
            $stats_stmt->execute([$house_id]);
            $request_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request_stats) {
                $stats['total_requests'] = (int)($request_stats['total_requests'] ?? 0);
                $stats['pending_requests'] = (int)($request_stats['pending_requests'] ?? 0);
                $stats['approved_requests'] = (int)($request_stats['approved_requests'] ?? 0);
                $stats['rejected_requests'] = (int)($request_stats['rejected_requests'] ?? 0);
            }
            
            // Current tenants
            $tenants_stmt = $conn->prepare("
                SELECT COUNT(*) as current_tenants
                FROM matches
                WHERE house_id = ? AND status = 'تایید شده'
            ");
            $tenants_stmt->execute([$house_id]);
            $tenants_result = $tenants_stmt->fetch(PDO::FETCH_ASSOC);
            $stats['current_tenants'] = (int)($tenants_result['current_tenants'] ?? 0);
        }
        
        // Past tenants (completed/cancelled requests)
        if ($requestsExists) {
            $past_tenants_stmt = $conn->prepare("
                SELECT COUNT(*) as past_tenants
                FROM requests
                WHERE listing_id = ? 
                  AND (request_status = 'cancelled' OR request_status = 'لغو شده')
            ");
            $past_tenants_stmt->execute([$house_id]);
            $past_result = $past_tenants_stmt->fetch(PDO::FETCH_ASSOC);
            $stats['past_tenants'] = (int)($past_result['past_tenants'] ?? 0);
        } elseif ($matchesExists) {
            $past_tenants_stmt = $conn->prepare("
                SELECT COUNT(*) as past_tenants
                FROM matches
                WHERE house_id = ? 
                  AND status = 'لغو شده'
            ");
            $past_tenants_stmt->execute([$house_id]);
            $past_result = $past_tenants_stmt->fetch(PDO::FETCH_ASSOC);
            $stats['past_tenants'] = (int)($past_result['past_tenants'] ?? 0);
        }
        
    } catch (PDOException $e) {
        error_log("Error fetching request stats: " . $e->getMessage());
    }
    
    // دریافت آمار پیام‌ها
    try {
        $messages_stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_messages,
                SUM(CASE WHEN is_read = 0 AND receiver_id = ? THEN 1 ELSE 0 END) as unread_messages
            FROM messages
            WHERE house_id = ?
        ");
        $messages_stmt->execute([$user_id, $house_id]);
        $messages_stats = $messages_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($messages_stats) {
            $stats['total_messages'] = (int)($messages_stats['total_messages'] ?? 0);
            $stats['unread_messages'] = (int)($messages_stats['unread_messages'] ?? 0);
        }
    } catch (PDOException $e) {
        error_log("Error fetching message stats: " . $e->getMessage());
    }
    
    // Parse amenities
    $amenities = [];
    if (!empty($house['amenities'])) {
        $amenities = array_map('trim', explode(',', $house['amenities']));
        $amenities = array_filter($amenities);
    }
    
    // Parse rules
    $rules = [];
    if (!empty($house['rules'])) {
        $rules = array_map('trim', explode(',', $house['rules']));
        $rules = array_filter($rules);
    }
    
    // Calculate member since
    $member_since = 0;
    if (!empty($house['owner_joined_date'])) {
        $joined_year = (int)date('Y', strtotime($house['owner_joined_date']));
        $current_year = (int)date('Y');
        $member_since = max(0, $current_year - $joined_year);
    }
    
} catch (PDOException $e) {
    error_log("Error fetching house details: " . $e->getMessage());
    header("Location: my_houses.php?error=database_error");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات خانه: <?php echo htmlspecialchars($house['title']); ?> | هم‌اتاقی</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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

        .nav-btn.primary {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--navy-black);
            font-weight: 600;
            border: 1px solid var(--gold);
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

        /* محتوای اصلی */
        .details-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* دکمه بازگشت */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255, 171, 0, 0.1);
            color: var(--gold-light);
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 171, 0, 0.2);
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: rgba(255, 171, 0, 0.2);
            transform: translateY(-2px);
        }

        /* هدر صفحه */
        .page-header {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(15px);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--gold), var(--purple), var(--gold));
            animation: shimmer 3s ease-in-out infinite;
        }

        .header-content h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-content p {
            color: var(--text-muted);
            font-size: 16px;
        }

        /* کارت‌های اطلاعات */
        .info-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            backdrop-filter: blur(15px);
            transition: all 0.3s ease;
        }

        .info-card:hover {
            border-color: var(--gold);
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--gold);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-item i {
            color: var(--gold);
            font-size: 1.2rem;
            margin-top: 2px;
        }

        .info-item-content {
            flex: 1;
        }

        .info-item-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .info-item-value {
            color: var(--text-light);
            font-size: 1rem;
            font-weight: 600;
        }

        /* گالری تصاویر */
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .gallery-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 16/9;
            border: 2px solid var(--card-border);
            transition: all 0.3s ease;
        }

        .gallery-item:hover {
            border-color: var(--gold);
            transform: scale(1.05);
        }

        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .gallery-item.primary::after {
            content: 'تصویر اصلی';
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--gold);
            color: var(--navy-black);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* آمار */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: rgba(255, 171, 0, 0.1);
            border: 1px solid rgba(255, 171, 0, 0.3);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--gold);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: var(--gold);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-light);
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* لیست امکانات و قوانین */
        .list-items {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .list-item {
            background: rgba(255, 171, 0, 0.1);
            color: var(--gold-light);
            padding: 8px 15px;
            border-radius: 20px;
            border: 1px solid rgba(255, 171, 0, 0.3);
            font-size: 0.9rem;
        }

        /* توضیحات */
        .description {
            color: var(--text-muted);
            line-height: 1.8;
            margin-top: 15px;
            white-space: pre-line;
        }

        /* دکمه‌های عملیات */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-family: 'Tajawal', sans-serif;
        }

        .action-btn.primary {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--navy-black);
        }

        .action-btn.secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-light);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 171, 0, 0.3);
        }

        /* انیمیشن‌ها */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shimmer {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
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

        .info-card, .page-header {
            animation: slideInUp 0.6s ease-out;
        }

        /* ریسپانسیو */
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .gallery {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .nav-container {
                flex-direction: column;
                gap: 15px;
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
            <a href="my_houses.php" class="nav-btn">
                <i class="fas fa-house-user"></i>
                خانه‌های من
            </a>
            <a href="add_house.php" class="nav-btn primary">
                <i class="fas fa-plus"></i>
                ثبت خانه جدید
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

<!-- محتوای اصلی -->
<div class="details-container">
    <!-- دکمه بازگشت -->
    <a href="my_houses.php" class="back-btn">
        <i class="fas fa-arrow-right"></i>
        بازگشت به لیست خانه‌ها
    </a>

    <!-- هدر صفحه -->
    <div class="page-header">
        <div class="header-content">
            <h1>🏠 <?php echo htmlspecialchars($house['title']); ?></h1>
            <p>جزئیات کامل خانه شما</p>
        </div>
    </div>

    <!-- اطلاعات کلی خانه -->
    <div class="info-card">
        <h2 class="card-title">
            <i class="fas fa-info-circle"></i>
            اطلاعات کلی
        </h2>
        <div class="info-grid">
            <div class="info-item">
                <i class="fas fa-map-marker-alt"></i>
                <div class="info-item-content">
                    <div class="info-item-label">آدرس</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($house['address']); ?></div>
                </div>
            </div>
            <div class="info-item">
                <i class="fas fa-city"></i>
                <div class="info-item-content">
                    <div class="info-item-label">شهر و استان</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($house['city'] . '، ' . $house['province']); ?></div>
                </div>
            </div>
            <div class="info-item">
                <i class="fas fa-money-bill-wave"></i>
                <div class="info-item-content">
                    <div class="info-item-label">قیمت اجاره (ماهانه)</div>
                    <div class="info-item-value"><?php echo number_format($house['price']); ?> تومان</div>
                </div>
            </div>
            <div class="info-item">
                <i class="fas fa-users"></i>
                <div class="info-item-content">
                    <div class="info-item-label">ظرفیت</div>
                    <div class="info-item-value">
                        <?php 
                        $occupied = max((int)$house['capacity'] - (int)$house['available_capacity'], 0);
                        $free = max((int)$house['available_capacity'], 0);
                        echo $occupied . ' / ' . $house['capacity'] . ' (خالی: ' . $free . ')';
                        ?>
                    </div>
                </div>
            </div>
            <div class="info-item">
                <i class="fas fa-<?php echo $house['gender'] === 'خانم' ? 'female' : 'male'; ?>"></i>
                <div class="info-item-content">
                    <div class="info-item-label">جنسیت</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($house['gender']); ?></div>
                </div>
            </div>
            <div class="info-item">
                <i class="fas fa-check-circle"></i>
                <div class="info-item-content">
                    <div class="info-item-label">وضعیت</div>
                    <div class="info-item-value">
                        <?php 
                        $status_text = [
                            'فعال' => '✅ فعال',
                            'در انتظار تایید' => '⏳ در انتظار تایید',
                            'غیرفعال' => '❌ غیرفعال'
                        ];
                        echo $status_text[$house['status']] ?? $house['status'];
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- گالری تصاویر -->
    <div class="info-card">
        <h2 class="card-title">
            <i class="fas fa-images"></i>
            تصاویر خانه
        </h2>
        <div class="gallery">
            <?php foreach ($house_photos as $index => $photo): ?>
                <div class="gallery-item <?php echo $photo['is_primary'] ? 'primary' : ''; ?>">
                    <img src="../<?php echo htmlspecialchars($photo['file_path']); ?>" 
                         alt="تصویر خانه <?php echo $index + 1; ?>"
                         onerror="this.src='../assets/images/roommates.jpg'">
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- توضیحات -->
    <?php if (!empty($house['description'])): ?>
    <div class="info-card">
        <h2 class="card-title">
            <i class="fas fa-align-right"></i>
            توضیحات
        </h2>
        <div class="description">
            <?php echo nl2br(htmlspecialchars($house['description'])); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- امکانات -->
    <?php if (!empty($amenities)): ?>
    <div class="info-card">
        <h2 class="card-title">
            <i class="fas fa-star"></i>
            امکانات
        </h2>
        <div class="list-items">
            <?php foreach ($amenities as $amenity): ?>
                <span class="list-item">
                    <i class="fas fa-check"></i>
                    <?php echo htmlspecialchars($amenity); ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- قوانین -->
    <?php if (!empty($rules)): ?>
    <div class="info-card">
        <h2 class="card-title">
            <i class="fas fa-gavel"></i>
            قوانین خانه
        </h2>
        <div class="list-items">
            <?php foreach ($rules as $rule): ?>
                <span class="list-item">
                    <i class="fas fa-info-circle"></i>
                    <?php echo htmlspecialchars($rule); ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- آمار درخواست‌ها -->
    <div class="info-card">
        <h2 class="card-title">
            <i class="fas fa-chart-bar"></i>
            آمار درخواست‌ها
        </h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_requests']; ?></div>
                <div class="stat-label">کل درخواست‌ها</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--warning);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                <div class="stat-label">در انتظار بررسی</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--success);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['approved_requests']; ?></div>
                <div class="stat-label">تأیید شده</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--danger);">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['rejected_requests']; ?></div>
                <div class="stat-label">رد شده</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--info);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $stats['current_tenants']; ?></div>
                <div class="stat-label">هم‌خانه‌های فعلی</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--text-muted);">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['past_tenants']; ?></div>
                <div class="stat-label">هم‌خانه‌های سابق</div>
            </div>
        </div>
    </div>

    <!-- آمار پیام‌ها -->
    <div class="info-card">
        <h2 class="card-title">
            <i class="fas fa-comments"></i>
            آمار پیام‌ها
        </h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--info);">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_messages']; ?></div>
                <div class="stat-label">کل پیام‌ها</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="color: var(--warning);">
                    <i class="fas fa-envelope-open"></i>
                </div>
                <div class="stat-number"><?php echo $stats['unread_messages']; ?></div>
                <div class="stat-label">پیام‌های خوانده نشده</div>
            </div>
        </div>
    </div>

    <!-- اطلاعات صاحب خانه -->
    <div class="info-card">
        <h2 class="card-title">
            <i class="fas fa-user-tie"></i>
            اطلاعات شما (صاحب خانه)
        </h2>
        <div class="info-grid">
            <div class="info-item">
                <i class="fas fa-user"></i>
                <div class="info-item-content">
                    <div class="info-item-label">نام</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($house['owner_name']); ?></div>
                </div>
            </div>
            <div class="info-item">
                <i class="fas fa-envelope"></i>
                <div class="info-item-content">
                    <div class="info-item-label">ایمیل</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($house['owner_email'] ?? '—'); ?></div>
                </div>
            </div>
            <div class="info-item">
                <i class="fas fa-phone"></i>
                <div class="info-item-content">
                    <div class="info-item-label">تلفن</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($house['owner_phone'] ?? '—'); ?></div>
                </div>
            </div>
            <div class="info-item">
                <i class="fas fa-star"></i>
                <div class="info-item-content">
                    <div class="info-item-label">امتیاز</div>
                    <div class="info-item-value">
                        <?php 
                        $score = (float)($house['owner_score'] ?? 0);
                        echo number_format($score, 1) . ' / 5.0';
                        ?>
                    </div>
                </div>
            </div>
            <?php if ($member_since > 0): ?>
            <div class="info-item">
                <i class="fas fa-calendar-alt"></i>
                <div class="info-item-content">
                    <div class="info-item-label">عضو از</div>
                    <div class="info-item-value"><?php echo $member_since; ?> سال پیش</div>
                </div>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <i class="fas fa-calendar"></i>
                <div class="info-item-content">
                    <div class="info-item-label">تاریخ ثبت خانه</div>
                    <div class="info-item-value">
                        <?php echo date('Y/m/d', strtotime($house['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- دکمه‌های عملیات -->
    <div class="action-buttons">
        <a href="edit_house.php?id=<?php echo $house_id; ?>" class="action-btn primary">
            <i class="fas fa-edit"></i>
            ویرایش خانه
        </a>
        <a href="house_requests.php?house_id=<?php echo $house_id; ?>" class="action-btn secondary">
            <i class="fas fa-envelope"></i>
            مشاهده درخواست‌ها
        </a>
        <a href="messages.php?house_id=<?php echo $house_id; ?>" class="action-btn secondary">
            <i class="fas fa-comments"></i>
            پیام‌ها
        </a>
        <a href="my_houses.php" class="action-btn secondary">
            <i class="fas fa-arrow-right"></i>
            بازگشت به لیست
        </a>
    </div>
</div>

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

// انیمیشن المان‌ها
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.info-card, .page-header');
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
    });
});
</script>

</body>
</html>

