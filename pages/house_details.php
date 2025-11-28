<?php
session_start();
include __DIR__ . '/../includes/config.php';

// Initialize message variables
$successMessage = '';
$errorMessage = '';
$errorCode = $_GET['error'] ?? null;

if (isset($_GET['success'])) {
    $successMessage = 'درخواست شما با موفقیت ثبت شد و به صاحب‌خانه اطلاع داده شد.';
}

if ($errorCode === 'gender_mismatch') {
    $errorMessage = 'این خانه برای جنسیت متفاوتی ثبت شده است و امکان ثبت درخواست وجود ندارد.';
} elseif ($errorCode === 'capacity_full') {
    $errorMessage = 'ظرفیت این خانه تکمیل شده است.';
}

// بررسی ورود کاربر
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// دریافت آیدی خانه از URL
$house_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($house_id <= 0) {
    header("Location: available_houses.php");
    exit();
}

// دریافت اطلاعات خانه از دیتابیس
$house = null;
try {
    $stmt = $conn->prepare("
        SELECT h.*, u.full_name, u.personality_type as owner_personality, u.gender as owner_gender, 
               u.created_at as user_created_at, u.user_score as owner_rating
        FROM houses h 
        JOIN users u ON h.user_id = u.id 
        WHERE h.id = ?
    ");
    $stmt->execute([$house_id]);
    $house = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching house: " . $e->getMessage());
    header("Location: available_houses.php?error=not_found");
    exit();
}

if(!$house) {
    header("Location: available_houses.php?error=not_found");
    exit();
}

// دریافت اطلاعات صاحب خانه برای نمایش پروفایل
$owner_id = $house['user_id'] ?? 0;

if ($owner_id <= 0) {
    header("Location: available_houses.php?error=invalid_owner");
    exit();
}

// بررسی وجود جدول reviews
$reviews_exists = false;
try {
    $check_reviews = $conn->query("SHOW TABLES LIKE 'reviews'");
    $reviews_exists = $check_reviews->rowCount() > 0;
} catch (PDOException $e) {
    $reviews_exists = false;
}

if ($reviews_exists) {
    $owner_stmt = $conn->prepare("
        SELECT u.*, 
               (SELECT AVG(rating) FROM reviews WHERE target_user_id = u.id) as avg_rating,
               (SELECT COUNT(*) FROM reviews WHERE target_user_id = u.id) as total_ratings,
               (SELECT COUNT(*) FROM houses WHERE user_id = u.id AND status = 'فعال') as total_houses
        FROM users u 
        WHERE u.id = ?
    ");
} else {
    $owner_stmt = $conn->prepare("
        SELECT u.*,
               u.user_score as avg_rating,
               0 as total_ratings,
               (SELECT COUNT(*) FROM houses WHERE user_id = u.id AND status = 'فعال') as total_houses
        FROM users u 
        WHERE u.id = ?
    ");
}

try {
    $owner_stmt->execute([$owner_id]);
    $owner = $owner_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching owner: " . $e->getMessage());
    header("Location: available_houses.php?error=owner_not_found");
    exit();
}

if (!$owner) {
    header("Location: available_houses.php?error=owner_not_found");
    exit();
}

// محاسبه سال عضویت
$member_since = 0;
if (!empty($owner['created_at'])) {
    $createdYear = (int)date('Y', strtotime($owner['created_at']));
    $currentYear = (int)date('Y');
    $member_since = max(0, $currentYear - $createdYear);
}

// دریافت تصاویر خانه
$images = [];
if (!empty($house['images'])) {
    $images = json_decode($house['images'], true);
    if (!is_array($images)) {
        $images = [];
    }
}

// بررسی وجود جدول favorites
$favorites_exists = false;
try {
    $check_favorites = $conn->query("SHOW TABLES LIKE 'favorites'");
    $favorites_exists = $check_favorites->rowCount() > 0;
} catch (PDOException $e) {
    $favorites_exists = false;
}

// بررسی آیا کاربر این خانه رو ذخیره کرده
$is_favorite = false;
if ($favorites_exists) {
    try {
        $favorite_stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND house_id = ?");
        $favorite_stmt->execute([$_SESSION['user_id'], $house_id]);
        $is_favorite = (bool)$favorite_stmt->fetch();
    } catch (PDOException $e) {
        $is_favorite = false;
    }
}

// بررسی وجود جدول requests
$requests_exists = false;
try {
    $check_requests = $conn->query("SHOW TABLES LIKE 'requests'");
    $requests_exists = $check_requests->rowCount() > 0;
} catch (PDOException $e) {
    $requests_exists = false;
}

// بررسی آیا کاربر قبلاً درخواست داده
$has_requested = false;
if ($requests_exists) {
    try {
        $request_stmt = $conn->prepare("SELECT id FROM requests WHERE user_id = ? AND house_id = ?");
        $request_stmt->execute([$_SESSION['user_id'] ?? 0, $house_id]);
        $has_requested = (bool)$request_stmt->fetch();
    } catch (PDOException $e) {
        $has_requested = false;
    }
}

// دریافت اطلاعات کاربر فعلی
$user_id = $_SESSION['user_id'] ?? 0;
$current_user = ['usertype' => '', 'gender' => '', 'full_name' => ''];
try {
    $user_stmt = $conn->prepare("SELECT full_name, usertype, gender FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $fetched_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched_user) {
        $current_user = $fetched_user;
    }
} catch (PDOException $e) {
    error_log("Error fetching current user: " . $e->getMessage());
}

// بررسی شرایط ارسال درخواست
$isOwner = ((int)($house['user_id'] ?? 0) === (int)$user_id);
$userGender = $current_user['gender'] ?? null;
$genderMismatch = $userGender ? ($userGender !== $house['gender']) : false;
$capacityFull = (int)($house['available_capacity'] ?? 0) <= 0;
$canSendRequest = !$isOwner
    && ($current_user['usertype'] ?? '') === 'هم‌خانه'
    && !$has_requested
    && !$genderMismatch
    && !$capacityFull;

// دریافت خانه‌های مشابه
$similar_houses = [];
try {
    $similar_stmt = $conn->prepare("
        SELECT id, title, price, capacity, available_capacity, city, province, images, gender
        FROM houses 
        WHERE city = ? AND id != ? AND status = 'فعال' 
        ORDER BY RAND() 
        LIMIT 3
    ");
    $similar_stmt->execute([$house['city'] ?? '', $house_id]);
    $similar_houses = $similar_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching similar houses: " . $e->getMessage());
    $similar_houses = [];
}

// پارس کردن امکانات از رشته به آرایه
$amenities_list = [];
if (!empty($house['amenities'] ?? '')) {
    $amenities_list = explode(', ', $house['amenities']);
    $amenities_list = array_map('trim', $amenities_list);
    $amenities_list = array_filter($amenities_list);
}

// تعیین آیکون برای هر امکانات
function getAmenityIcon($amenity) {
    $icons = [
        'اینترنت' => 'wifi',
        'تلفن' => 'phone',
        'مبلمان' => 'couch',
        'آشپزخانه' => 'utensils',
        'لباسشویی' => 'tshirt',
        'پارکینگ' => 'square-parking',
        'آسانسور' => 'elevator',
        'حیاط' => 'tree',
        'تهویه مطبوع' => 'fan',
        'گرمایش مرکزی' => 'temperature-high',
        'انباری' => 'box'
    ];
    return $icons[$amenity] ?? 'check';
}

// فرمت تاریخ فارسی ساده
function formatDate($date) {
    $months = [
        'January' => 'فروردین', 'February' => 'اردیبهشت', 'March' => 'خرداد',
        'April' => 'تیر', 'May' => 'مرداد', 'June' => 'شهریور',
        'July' => 'مهر', 'August' => 'آبان', 'September' => 'آذر',
        'October' => 'دی', 'November' => 'بهمن', 'December' => 'اسفند'
    ];
    
    $english_month = date('F', strtotime($date));
    $persian_month = $months[$english_month] ?? $english_month;
    
    return date('j', strtotime($date)) . ' ' . $persian_month . ' ' . date('Y', strtotime($date));
}

// محاسبه ستاره‌ها برای امتیاز
function generateStars($rating) {
    $stars = '';
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $fullStars) {
            $stars .= '<i class="fas fa-star"></i>';
        } elseif ($halfStar && $i == $fullStars + 1) {
            $stars .= '<i class="fas fa-star-half-alt"></i>';
        } else {
            $stars .= '<i class="far fa-star"></i>';
        }
    }
    
    return $stars;
}

$occupiedSlots = max((int)($house['capacity'] ?? 0) - (int)($house['available_capacity'] ?? 0), 0);
$freeSlots = max((int)($house['available_capacity'] ?? 0), 0);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($house['title']); ?> | هم‌اتاقی</title>
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

    /* محتوای اصلی */
    .house-details {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px;
    }

    /* هدر خانه */
    .house-header-section {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
        backdrop-filter: blur(15px);
        position: relative;
        overflow: hidden;
    }

    .house-header-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--gold), var(--purple), var(--gold));
        animation: shimmer 3s ease-in-out infinite;
    }

    .house-main-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .house-title-section h1 {
        font-size: 32px;
        font-weight: 800;
        color: var(--text-light);
        margin-bottom: 10px;
        background: linear-gradient(135deg, var(--gold), var(--gold-light));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .house-location {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--text-muted);
        font-size: 16px;
    }

    .house-price-section {
        text-align: left;
    }

    .price-tag {
        font-size: 28px;
        font-weight: 800;
        color: var(--gold);
        margin-bottom: 5px;
    }

    .price-label {
        color: var(--text-muted);
        font-size: 14px;
    }

    /* گالری تصاویر */
    .gallery-section {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 30px;
        backdrop-filter: blur(15px);
    }

    .main-image {
        width: 100%;
        height: 500px;
        border-radius: 15px;
        overflow: hidden;
        margin-bottom: 15px;
        position: relative;
    }

    .main-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .image-nav {
        position: absolute;
        top: 50%;
        width: 100%;
        display: flex;
        justify-content: space-between;
        padding: 0 20px;
        transform: translateY(-50%);
    }

    .nav-btn-img {
        background: rgba(0, 0, 0, 0.7);
        color: white;
        border: none;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .nav-btn-img:hover {
        background: var(--gold);
        color: var(--navy-black);
    }

    .thumbnails {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding: 10px 0;
    }

    .thumbnail {
        width: 100px;
        height: 80px;
        border-radius: 10px;
        overflow: hidden;
        cursor: pointer;
        opacity: 0.7;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }

    .thumbnail.active {
        opacity: 1;
        border: 2px solid var(--gold);
    }

    .thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* محتوای اصلی */
    .house-content {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 30px;
        margin-bottom: 40px;
    }

    /* کارت اطلاعات */
    .info-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 20px;
        padding: 30px;
        backdrop-filter: blur(15px);
        margin-bottom: 25px;
    }

    .info-card:last-child {
        margin-bottom: 0;
    }

    .card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .card-header i {
        font-size: 20px;
        color: var(--gold);
    }

    .card-header h3 {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-light);
    }

    /* مشخصات خانه */
    .specs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .spec-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .spec-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 171, 0, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gold);
        font-size: 1.2rem;
    }

    .spec-info h4 {
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-bottom: 5px;
    }

    .spec-info p {
        color: var(--text-light);
        font-weight: 600;
        font-size: 1.1rem;
    }

    /* توضیحات و قوانین */
    .description-text, .rules-text {
        color: var(--text-muted);
        line-height: 1.8;
        white-space: pre-line;
        font-size: 15px;
    }

    /* امکانات */
    .amenities-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }

    .amenity-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 15px;
        background: rgba(76, 175, 80, 0.1);
        border-radius: 10px;
        color: var(--success);
        border: 1px solid rgba(76, 175, 80, 0.3);
        font-weight: 500;
    }

    /* سایدبار */
    .sidebar {
        display: flex;
        flex-direction: column;
        gap: 25px;
    }

    .owner-card, .action-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 20px;
        padding: 25px;
        backdrop-filter: blur(15px);
    }

    .owner-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }

    .owner-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--gold), var(--purple));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--navy-black);
        font-weight: 800;
    }

    .owner-info h3 {
        color: var(--text-light);
        margin-bottom: 5px;
        font-size: 1.1rem;
    }

    .owner-info p {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    /* امتیاز و اطلاعات صاحب خانه */
    .owner-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin: 15px 0;
    }

    .stat-item {
        text-align: center;
        padding: 12px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .stat-value {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--gold);
        margin-bottom: 5px;
    }

    .stat-label {
        color: var(--text-muted);
        font-size: 0.8rem;
    }

    .rating-section {
        text-align: center;
        margin: 15px 0;
    }

    .stars {
        color: var(--gold);
        font-size: 1.1rem;
        margin-bottom: 8px;
    }

    .rating-value {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-light);
        margin-bottom: 5px;
    }

    .rating-count {
        color: var(--text-muted);
        font-size: 0.8rem;
    }

    .owner-personality {
        background: rgba(139, 92, 246, 0.1);
        color: var(--purple);
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-align: center;
        margin: 10px 0;
        border: 1px solid rgba(139, 92, 246, 0.3);
    }

    .contact-info {
        margin: 15px 0;
        text-align: center;
    }

    .contact-message {
        background: rgba(255, 171, 0, 0.1);
        border: 1px solid rgba(255, 171, 0, 0.3);
        border-radius: 15px;
        padding: 15px;
        text-align: center;
    }

    .contact-message i {
        font-size: 1.5rem;
        color: var(--gold);
        margin-bottom: 8px;
    }

    .contact-message h4 {
        color: var(--text-light);
        margin-bottom: 6px;
        font-size: 1rem;
    }

    .contact-message p {
        color: var(--text-muted);
        font-size: 0.8rem;
        line-height: 1.4;
    }

    /* دکمه‌های اقدام */
    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .action-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 15px 20px;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 1rem;
        font-family: 'Tajawal', sans-serif;
        text-decoration: none;
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

    .action-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }

    .action-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    }

    .action-btn.favorite {
        background: <?php echo $is_favorite ? 'rgba(244, 67, 54, 0.2)' : 'rgba(255, 255, 255, 0.1)'; ?>;
        color: <?php echo $is_favorite ? '#ff5252' : 'var(--text-light)'; ?>;
        border: 1px solid <?php echo $is_favorite ? 'rgba(244, 67, 54, 0.3)' : 'rgba(255, 255, 255, 0.2)'; ?>;
    }

    .action-btn.requested {
        background: rgba(76, 175, 80, 0.2);
        color: var(--success);
        border: 1px solid rgba(76, 175, 80, 0.3);
    }

    .action-btn.profile {
        background: rgba(139, 92, 246, 0.1);
        color: var(--purple);
        border: 1px solid rgba(139, 92, 246, 0.3);
        font-size: 0.9rem;
        padding: 12px 15px;
    }

    /* خانه‌های مشابه */
    .similar-houses {
        margin-top: 50px;
    }

    .section-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-light);
        margin-bottom: 25px;
        text-align: center;
    }

    .similar-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
    }

    .alert-banner {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(255, 171, 0, 0.15);
        color: var(--gold);
        font-weight: 600;
    }
    .alert-banner.danger {
        background: rgba(244, 67, 54, 0.2);
        color: var(--danger);
        border-color: rgba(244,67,54,0.3);
    }
    .alert-banner.info {
        background: rgba(33,150,243,0.15);
        color: var(--info);
        border-color: rgba(33,150,243,0.25);
    }

    .similar-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 15px;
        overflow: hidden;
        transition: all 0.3s ease;
        text-decoration: none;
        color: inherit;
    }

    .similar-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .similar-image {
        width: 100%;
        height: 200px;
        overflow: hidden;
    }

    .similar-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .similar-card:hover .similar-image img {
        transform: scale(1.05);
    }

    .similar-content {
        padding: 20px;
    }

    .similar-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--text-light);
    }

    .similar-price {
        color: var(--gold);
        font-weight: 700;
        font-size: 1.2rem;
        margin-bottom: 10px;
    }

    .similar-specs {
        display: flex;
        gap: 15px;
        color: var(--text-muted);
        font-size: 0.9rem;
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

    .house-header-section, .gallery-section, .info-card, .owner-card, .action-card, .similar-card {
        animation: slideInUp 0.6s ease-out;
    }

    /* ریسپانسیو */
    @media (max-width: 768px) {
        .house-content {
            grid-template-columns: 1fr;
        }
        
        .house-main-header {
            flex-direction: column;
            gap: 15px;
        }
        
        .house-price-section {
            text-align: right;
        }
        
        .specs-grid {
            grid-template-columns: 1fr;
        }
        
        .amenities-grid {
            grid-template-columns: 1fr;
        }
        
        .owner-stats {
            grid-template-columns: 1fr;
        }
        
        .main-image {
            height: 300px;
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
            <a href="available_houses.php" class="nav-btn">
                <i class="fas fa-arrow-right"></i>
                بازگشت
            </a>
            <a href="dashboard.php" class="nav-btn">
                <i class="fas fa-tachometer-alt"></i>
                داشبورد
            </a>
        </div>
    </div>
</nav>

<!-- محتوای اصلی -->
<div class="house-details">
    <?php if ($successMessage): ?>
        <div class="alert-banner"><?= htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="alert-banner danger"><?= htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>
    <?php if ($isOwner): ?>
        <div class="alert-banner info">شما صاحب این آگهی هستید. برای مدیریت درخواست‌ها به بخش «خانه‌های من» بروید.</div>
    <?php endif; ?>
    <?php if ($genderMismatch): ?>
        <div class="alert-banner danger">جنسیت شما با محدودیت اعلام‌شده برای این خانه همخوانی ندارد.</div>
    <?php endif; ?>
    <?php if ($capacityFull): ?>
        <div class="alert-banner danger">ظرفیت این خانه تکمیل شده است.</div>
    <?php endif; ?>
    <!-- هدر خانه -->
    <div class="house-header-section">
        <div class="house-main-header">
            <div class="house-title-section">
                <h1><?php echo htmlspecialchars($house['title']); ?></h1>
                <div class="house-location">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?php echo htmlspecialchars($house['city'] . '، ' . $house['province']); ?></span>
                    <span>•</span>
                    <span><?php echo htmlspecialchars($house['address']); ?></span>
                </div>
            </div>
            <div class="house-price-section">
                <div class="price-tag"><?php echo number_format($house['price']); ?> تومان</div>
                <div class="price-label">ماهیانه</div>
            </div>
        </div>
    </div>

    <div class="house-content">
        <!-- ستون اصلی (محوریت با خانه) -->
        <div class="main-column">
            <!-- گالری تصاویر -->
            <div class="gallery-section">
                <div class="main-image">
                    <img id="mainImage" src="<?php echo !empty($images[0]) ? '../' . $images[0] : '../assets/default-house.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($house['title']); ?>">
                    <?php if(count($images) > 1): ?>
                    <div class="image-nav">
                        <button class="nav-btn-img" onclick="changeImage(-1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button class="nav-btn-img" onclick="changeImage(1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if(count($images) > 1): ?>
                <div class="thumbnails">
                    <?php foreach($images as $index => $image): ?>
                    <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                         onclick="showImage(<?php echo $index; ?>)">
                        <img src="<?php echo '../' . $image; ?>" alt="تصویر خانه">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- مشخصات اصلی -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i>
                    <h3>مشخصات خانه</h3>
                </div>
                <div class="specs-grid">
                    <div class="spec-item">
                        <div class="spec-icon">
                            <i class="fas fa-venus-mars"></i>
                        </div>
                        <div class="spec-info">
                            <h4>جنسیت ساکنین</h4>
                            <p><?php echo htmlspecialchars($house['gender']); ?></p>
                        </div>
                    </div>
                    
                    <div class="spec-item">
                        <div class="spec-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="spec-info">
                            <h4>ظرفیت کل</h4>
                            <p><?php echo htmlspecialchars($house['capacity']); ?> نفر</p>
                        </div>
                    </div>

                    <div class="spec-item">
                        <div class="spec-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="spec-info">
                            <h4>ظرفیت خالی</h4>
                            <p><?php echo $freeSlots; ?> جای خالی از <?php echo (int)$house['capacity']; ?></p>
                        </div>
                    </div>
                    
                    <div class="spec-item">
                        <div class="spec-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="spec-info">
                            <h4>تاریخ ثبت</h4>
                            <p><?php echo formatDate($house['created_at']); ?></p>
                        </div>
                    </div>

                    <div class="spec-item">
                        <div class="spec-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="spec-info">
                            <h4>وضعیت</h4>
                            <p><?php echo htmlspecialchars($house['status']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- توضیحات -->
            <?php if(!empty($house['description'])): ?>
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-align-left"></i>
                    <h3>توضیحات خانه</h3>
                </div>
                <div class="description-text">
                    <?php echo htmlspecialchars($house['description']); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- امکانات -->
            <?php if(!empty($amenities_list)): ?>
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-concierge-bell"></i>
                    <h3>امکانات خانه</h3>
                </div>
                <div class="amenities-grid">
                    <?php foreach($amenities_list as $amenity): ?>
                    <div class="amenity-item">
                        <i class="fas fa-<?php echo getAmenityIcon($amenity); ?>"></i>
                        <span><?php echo htmlspecialchars($amenity); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- قوانین -->
            <?php if(!empty($house['rules'])): ?>
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>قوانین خانه</h3>
                </div>
                <div class="rules-text">
                    <?php echo htmlspecialchars($house['rules']); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- سایدبار (کناری و کوچک) -->
        <div class="sidebar">
            <!-- اطلاعات مالک (کوچک و مختصر) -->
            <div class="owner-card">
                <div class="owner-header">
                    <div class="owner-avatar">
                        <?php echo mb_substr($owner['full_name'], 0, 1); ?>
                    </div>
                    <div class="owner-info">
                        <h3><?php echo htmlspecialchars($owner['full_name']); ?></h3>
                        <p>صاحب خانه</p>
                    </div>
                </div>
                
                <!-- امتیاز و اطلاعات صاحب خانه -->
                <?php if(isset($owner['avg_rating']) && $owner['avg_rating'] > 0): ?>
                <div class="rating-section">
                    <div class="stars">
                        <?php echo generateStars($owner['avg_rating']); ?>
                    </div>
                    <div class="rating-value">
                        <?php echo number_format($owner['avg_rating'], 1); ?> از ۵
                    </div>
                    <?php if(isset($owner['total_ratings']) && $owner['total_ratings'] > 0): ?>
                    <div class="rating-count">
                        (<?php echo $owner['total_ratings']; ?> نظر)
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="rating-section">
                    <div class="stars">
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                        <i class="far fa-star"></i>
                    </div>
                    <div class="rating-count" style="color: var(--text-muted);">
                        هنوز امتیازی دریافت نکرده
                    </div>
                </div>
                <?php endif; ?>

                <div class="owner-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $owner['total_houses'] ?? 0; ?></div>
                        <div class="stat-label">خانه فعال</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo (int)$member_since; ?></div>
                        <div class="stat-label">سال عضویت</div>
                    </div>
                </div>
                
                <?php if(!empty($owner['personality_type'] ?? '')): ?>
                <div class="owner-personality">
                    <i class="fas fa-brain"></i>
                    تیپ: <?php echo $owner['personality_type']; ?>
                </div>
                <?php endif; ?>

                <a href="user_profile.php?id=<?php echo $owner_id; ?>" class="action-btn profile">
                    <i class="fas fa-user-circle"></i>
                    مشاهده پروفایل
                </a>
            </div>

            <!-- اقدامات -->
            <div class="action-card">
                <div class="action-buttons">
                    <?php if ($current_user['usertype'] === 'هم‌خانه' && !$isOwner): ?>
                        <?php if ($canSendRequest): ?>
                            <a href="send_request.php?house_id=<?php echo $house_id; ?>" class="action-btn primary">
                                <i class="fas fa-paper-plane"></i>
                                ارسال درخواست اقامت
                            </a>
                        <?php elseif ($has_requested): ?>
                            <button class="action-btn requested" disabled>
                                <i class="fas fa-clock"></i>
                                درخواست شما در انتظار پاسخ است
                            </button>
                        <?php else: ?>
                            <button class="action-btn requested" disabled>
                                <i class="fas fa-ban"></i>
                                امکان ارسال درخواست وجود ندارد
                            </button>
                        <?php endif; ?>
                    <?php elseif ($isOwner): ?>
                        <a href="house_requests.php?house_id=<?php echo $house_id; ?>" class="action-btn primary">
                            <i class="fas fa-list"></i>
                            مدیریت درخواست‌ها
                        </a>
                    <?php endif; ?>

                    <?php if (!$isOwner): ?>
                        <button class="action-btn favorite" id="favoriteBtn" onclick="toggleFavorite()">
                            <i class="fas fa-heart"></i>
                            <?php echo $is_favorite ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها'; ?>
                        </button>
                    <?php endif; ?>
                    
                    <button class="action-btn secondary" onclick="shareHouse()">
                        <i class="fas fa-share-alt"></i>
                        اشتراک‌گذاری
                    </button>

                    <?php if ($has_requested && !$isOwner): ?>
                        <a href="messages.php?house_id=<?php echo $house_id; ?>&with=<?php echo $owner_id; ?>" class="action-btn secondary">
                            <i class="fas fa-comments"></i>
                            پیام به صاحب‌خانه
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- خانه‌های مشابه -->
    <?php if(!empty($similar_houses)): ?>
    <div class="similar-houses">
        <h2 class="section-title">خانه‌های مشابه در <?php echo htmlspecialchars($house['city']); ?></h2>
        <div class="similar-grid">
            <?php foreach($similar_houses as $similar): 
                $similar_images = !empty($similar['images']) ? json_decode($similar['images'], true) : [];
                $main_image = !empty($similar_images[0]) ? '../' . $similar_images[0] : '../assets/default-house.jpg';
            ?>
            <a href="house_details.php?id=<?php echo (int)($similar['id'] ?? 0); ?>" class="similar-card">
                <div class="similar-image">
                    <img src="<?php echo $main_image; ?>" 
                         alt="<?php echo htmlspecialchars($similar['title'] ?? ''); ?>">
                </div>
                <div class="similar-content">
                    <h3 class="similar-title"><?php echo htmlspecialchars($similar['title'] ?? ''); ?></h3>
                    <div class="similar-price"><?php echo number_format($similar['price'] ?? 0); ?> تومان</div>
                    <div class="similar-specs">
                        <span><i class="fas fa-users"></i> <?php echo $similar['capacity'] ?? 0; ?> نفر</span>
                        <span><i class="fas fa-user-friends"></i> <?php echo $similar['available_capacity'] ?? 0; ?> خالی</span>
                        <span><i class="fas fa-venus-mars"></i> <?php echo htmlspecialchars($similar['gender'] ?? ''); ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
<script>
// پارتیکل‌های پیشرفته
particlesJS("particles-js", {
  particles: {
    number: { value: 50, density: { enable: true, value_area: 800 } },
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

// مدیریت گالری تصاویر
let currentImageIndex = 0;
const images = <?php echo json_encode($images); ?>;

function showImage(index) {
    currentImageIndex = index;
    document.getElementById('mainImage').src = '../' + images[index];
    
    // آپدیت thumbnail های فعال
    document.querySelectorAll('.thumbnail').forEach((thumb, i) => {
        thumb.classList.toggle('active', i === index);
    });
}

function changeImage(direction) {
    currentImageIndex = (currentImageIndex + direction + images.length) % images.length;
    showImage(currentImageIndex);
}

// مدیریت علاقه‌مندی‌ها
function toggleFavorite() {
    const btn = document.getElementById('favoriteBtn');
    const isCurrentlyFavorite = btn.innerHTML.includes('حذف');
    
    fetch('toggle_favorite.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            house_id: <?php echo $house_id; ?>,
            action: isCurrentlyFavorite ? 'remove' : 'add'
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            if(isCurrentlyFavorite) {
                btn.innerHTML = '<i class="fas fa-heart"></i> افزودن به علاقه‌مندی‌ها';
                btn.style.background = 'rgba(255, 255, 255, 0.1)';
                btn.style.color = 'var(--text-light)';
                btn.style.border = '1px solid rgba(255, 255, 255, 0.2)';
            } else {
                btn.innerHTML = '<i class="fas fa-heart"></i> حذف از علاقه‌مندی‌ها';
                btn.style.background = 'rgba(244, 67, 54, 0.2)';
                btn.style.color = '#ff5252';
                btn.style.border = '1px solid rgba(244, 67, 54, 0.3)';
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// اشتراک‌گذاری
function shareHouse() {
    const shareUrl = window.location.href;
    const title = '<?php echo htmlspecialchars($house['title']); ?>';
    
    if(navigator.share) {
        navigator.share({
            title: title,
            text: 'نگاه کن به این خانه زیبا در هم‌اتاقی',
            url: shareUrl,
        })
        .catch(error => console.log('Error sharing:', error));
    } else {
        navigator.clipboard.writeText(shareUrl).then(() => {
            alert('لینک خانه در کلیپ‌بورد کپی شد!');
        });
    }
}

// انیمیشن‌ها
document.addEventListener('DOMContentLoaded', function() {
    const animatedElements = document.querySelectorAll('.house-header-section, .gallery-section, .info-card, .owner-card, .action-card, .similar-card');
    
    animatedElements.forEach((element, index) => {
        element.style.animationDelay = (index * 0.1) + 's';
    });
});
</script>

</body>
</html>