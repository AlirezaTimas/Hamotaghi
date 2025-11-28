<?php
session_start();
include __DIR__ . '/../includes/config.php';

// بررسی ورود کاربر
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// دریافت اطلاعات کاربر
$user_sql = "SELECT full_name, usertype, gender, personality_type FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: logout.php");
    exit();
}

// بررسی وجود جدول houses
try {
    $table_exists = $conn->query("SHOW TABLES LIKE 'houses'")->rowCount() > 0;
} catch (PDOException $e) {
    $table_exists = false;
}

// Initialize filter variables (must be outside if-else for scope)
$search = $_GET['search'] ?? '';
$city = $_GET['city'] ?? '';
$province = $_GET['province'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// اگر جدول وجود ندارد، خانه‌های خالی برگردان
if (!$table_exists) {
    $houses = [];
    $cities = [];
    $provinces = [];
} else {

    // ساخت کوئری جستجو
    $where_conditions = [];
    $params = [];

    // فقط خانه‌های فعال با ظرفیت نمایش داده شوند
    $where_conditions[] = "h.available_capacity > 0";

    // بررسی وجود ستون status
    try {
        $check_status = $conn->query("SHOW COLUMNS FROM houses LIKE 'status'");
        if ($check_status->rowCount() > 0) {
            $where_conditions[] = "h.status = 'فعال'";
        }
    } catch (PDOException $e) {
        // اگر ستون status وجود ندارد، شرط رو اضافه نکن
    }

    if (empty($where_conditions)) {
        $where_conditions[] = "1=1";
    }

    // اگر کاربر فیلتر جنسیت را انتخاب نکرده باشد، بر اساس جنسیت کاربر فیلتر اعمال می‌شود
    if (empty($gender_filter)) {
        // فیلتر پیش‌فرض بر اساس جنسیت کاربر
        $where_conditions[] = "h.gender = ?";
        $params[] = $user['gender'];
    } else {
        // اگر کاربر فیلتر جنسیت را انتخاب کرده، از آن استفاده می‌شود
        $where_conditions[] = "h.gender = ?";
        $params[] = $gender_filter;
    }

    if (!empty($search)) {
        $where_conditions[] = "(h.title LIKE ? OR h.description LIKE ? OR h.address LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($city)) {
        $where_conditions[] = "h.city = ?";
        $params[] = $city;
    }

    if (!empty($province)) {
        $where_conditions[] = "h.province = ?";
        $params[] = $province;
    }

    if (!empty($min_price)) {
        $where_conditions[] = "h.price >= ?";
        $params[] = $min_price;
    }

    if (!empty($max_price)) {
        $where_conditions[] = "h.price <= ?";
        $params[] = $max_price;
    }

    $allowedSorts = [
        'newest' => 'h.created_at DESC',
        'price_low' => 'h.price ASC',
        'price_high' => 'h.price DESC'
    ];
    $orderBy = $allowedSorts[$sort] ?? $allowedSorts['newest'];

    // دریافت خانه‌ها
    $where_sql = implode(" AND ", $where_conditions);
    
    // بررسی وجود جدول favorites
    $favorites_exists = $conn->query("SHOW TABLES LIKE 'favorites'")->rowCount() > 0;
    
    // بررسی وجود جدول ratings برای امتیاز اخلاقی
    $ratings_exists = $conn->query("SHOW TABLES LIKE 'ratings'")->rowCount() > 0;
    
    if ($favorites_exists && $ratings_exists) {
        $houses_sql = "
            SELECT 
                h.*,
                u.full_name as owner_name,
                u.personality_type as owner_personality,
                (SELECT COUNT(*) FROM favorites WHERE house_id = h.id AND user_id = ?) as is_favorite,
                (SELECT COUNT(*) FROM favorites WHERE house_id = h.id) as favorites_count,
                (SELECT AVG(rating) FROM ratings WHERE rated_user_id = u.id) as moral_score,
                (SELECT COUNT(*) FROM ratings WHERE rated_user_id = u.id) as total_ratings
            FROM houses h
            LEFT JOIN users u ON h.user_id = u.id
            WHERE $where_sql
            ORDER BY $orderBy
        ";
        $all_params = array_merge([$user_id], $params);
    } else if ($favorites_exists) {
        $houses_sql = "
            SELECT 
                h.*,
                u.full_name as owner_name,
                u.personality_type as owner_personality,
                (SELECT COUNT(*) FROM favorites WHERE house_id = h.id AND user_id = ?) as is_favorite,
                (SELECT COUNT(*) FROM favorites WHERE house_id = h.id) as favorites_count,
                0 as moral_score,
                0 as total_ratings
            FROM houses h
            LEFT JOIN users u ON h.user_id = u.id
            WHERE $where_sql
            ORDER BY h.created_at DESC
        ";
        $all_params = array_merge([$user_id], $params);
    } else {
        $houses_sql = "
            SELECT 
                h.*,
                u.full_name as owner_name,
                u.personality_type as owner_personality,
                0 as is_favorite,
                0 as favorites_count,
                0 as moral_score,
                0 as total_ratings
            FROM houses h
            LEFT JOIN users u ON h.user_id = u.id
            WHERE $where_sql
            ORDER BY h.created_at DESC
        ";
        $all_params = $params;
    }

    try {
        $houses_stmt = $conn->prepare($houses_sql);
        $houses_stmt->execute($all_params);
        $houses = $houses_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $houses = [];
        error_log("Error fetching houses: " . $e->getMessage());
    }

    // دریافت شهرها و استان‌های موجود برای فیلتر
    try {
        $cities_sql = "SELECT DISTINCT city FROM houses ORDER BY city";
        $cities_stmt = $conn->prepare($cities_sql);
        $cities_stmt->execute();
        $cities = $cities_stmt->fetchAll(PDO::FETCH_COLUMN);

        $provinces_sql = "SELECT DISTINCT province FROM houses ORDER BY province";
        $provinces_stmt = $conn->prepare($provinces_sql);
        $provinces_stmt->execute();
        $provinces = $provinces_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $cities = [];
        $provinces = [];
    }
}

$username = htmlspecialchars($user['full_name']);
$usertype = $user['usertype'];
$gender = $user['gender'];
$user_personality = $user['personality_type'] ?? '';

// تنظیم فیلتر جنسیت پیش‌فرض برای نمایش در فرم
$default_gender_filter = $_GET['gender'] ?? '';
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>جستجوی خانه | هم‌اتاقی</title>
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
        url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.08'/%3E%3C/svg%3E"),
        radial-gradient(circle at 10% 20%, rgba(255, 171, 0, 0.15) 0%, transparent 50%),
        radial-gradient(circle at 90% 80%, rgba(15, 26, 33, 0.4) 0%, transparent 50%),
        radial-gradient(circle at 30% 70%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 70% 30%, rgba(0, 210, 106, 0.08) 0%, transparent 50%);
    color: var(--text-light);
    min-height: 100vh;
    line-height: 1.6;
    position: relative;
    overflow-x: hidden;
}

/* افکت پارتیکل پویا */
#particles-js {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
    pointer-events: none;
}

/* شبکه خطوط متحرک */
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

/* افکت نورپردازی داینامیک */
.light-spot {
    position: fixed;
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 171, 0, 0.1) 0%, transparent 70%);
    animation: floatLight 15s ease-in-out infinite;
    z-index: -1;
}

.light-spot:nth-child(1) {
    top: 20%;
    left: 10%;
    animation-delay: 0s;
}

.light-spot:nth-child(2) {
    top: 60%;
    left: 80%;
    animation-delay: 5s;
}

.light-spot:nth-child(3) {
    top: 80%;
    left: 20%;
    animation-delay: 10s;
}

/* ناوبری اصلی */
.main-nav {
    background: rgba(11, 20, 25, 0.95);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--card-border);
    padding: 15px 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
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
    text-shadow: 0 0 20px rgba(255, 171, 0, 0.5);
}

.brand i {
    font-size: 28px;
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
    font-size: 14px;
    position: relative;
}

.nav-btn:hover {
    background: rgba(255, 171, 0, 0.2);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 171, 0, 0.3);
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

/* محتوای اصلی */
.houses-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 30px 20px;
}

/* هدر صفحه */
.page-header {
    background: rgba(15, 26, 33, 0.9);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    backdrop-filter: blur(15px);
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
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

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.header-text h1 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 10px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.header-text p {
    color: var(--text-muted);
    font-size: 16px;
}

.results-count {
    background: rgba(255, 171, 0, 0.2);
    color: var(--gold);
    padding: 8px 20px;
    border-radius: 25px;
    font-weight: 600;
    border: 1px solid rgba(255, 171, 0, 0.3);
    backdrop-filter: blur(10px);
}

/* فیلترهای جستجو */
.filters-card {
    background: rgba(15, 26, 33, 0.9);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    backdrop-filter: blur(15px);
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-label {
    color: var(--text-light);
    font-weight: 600;
    font-size: 14px;
}

.filter-input, .filter-select {
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    color: var(--text-light);
    font-family: 'Tajawal', sans-serif;
    font-size: 14px;
    transition: all 0.3s ease;
}

.filter-input:focus, .filter-select:focus {
    outline: none;
    border-color: var(--gold);
    background: rgba(255, 255, 255, 0.12);
    box-shadow: 0 0 0 3px rgba(255, 171, 0, 0.1);
}

.filter-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
}

.search-btn {
    padding: 12px 30px;
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--navy-black);
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 15px;
}

.search-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 171, 0, 0.4);
}

.reset-btn {
    padding: 12px 25px;
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-light);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.reset-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* گرید خانه‌ها */
.houses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

/* کارت خانه */
.house-card {
    background: rgba(15, 26, 33, 0.9);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    overflow: hidden;
    backdrop-filter: blur(15px);
    transition: all 0.4s ease;
    position: relative;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
}

.house-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 50px rgba(255, 171, 0, 0.3);
    border-color: var(--gold);
}

.house-image {
    width: 100%;
    height: 200px;
    background: linear-gradient(135deg, var(--navy-dark), var(--navy-darker));
    position: relative;
    overflow: hidden;
}

.house-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.house-card:hover .house-image img {
    transform: scale(1.1);
}

.house-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: var(--gold);
    color: var(--navy-black);
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 700;
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    gap: 5px;
}

.house-badge.female {
    background: var(--purple);
    color: white;
}

.favorite-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    color: var(--text-muted);
}

.favorite-btn:hover {
    background: var(--gold);
    transform: scale(1.1);
    color: var(--navy-black);
}

.favorite-btn.active {
    background: var(--danger);
    color: white;
}

.house-content {
    padding: 20px;
}

.house-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.house-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-light);
    margin-bottom: 5px;
    line-height: 1.4;
}

.house-price {
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--navy-black);
    padding: 6px 12px;
    border-radius: 15px;
    font-weight: 700;
    font-size: 14px;
    white-space: nowrap;
}

.house-location {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
    font-size: 14px;
    margin-bottom: 15px;
}

.house-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 15px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--text-muted);
    font-size: 13px;
}

.detail-item i {
    color: var(--gold);
    width: 16px;
}

.house-owner {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-top: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.owner-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--gold), var(--purple));
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--navy-black);
    font-weight: 600;
    font-size: 14px;
}

.owner-info {
    flex: 1;
}

.owner-name {
    font-weight: 600;
    font-size: 14px;
    color: var(--text-light);
}

.owner-personality {
    font-size: 12px;
    color: var(--text-muted);
}

/* استایل جدید برای امتیاز اخلاقی */
.moral-score {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    padding: 8px 12px;
    background: rgba(139, 92, 246, 0.1);
    border-radius: 10px;
    border: 1px solid rgba(139, 92, 246, 0.3);
}

.score-stars {
    display: flex;
    gap: 2px;
}

.star {
    color: #ddd;
    font-size: 14px;
}

.star.filled {
    color: var(--gold);
}

.score-value {
    font-weight: 600;
    color: var(--gold);
    font-size: 14px;
}

.score-count {
    color: var(--text-muted);
    font-size: 12px;
    margin-right: auto;
}

.personality-match {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: rgba(76, 175, 80, 0.1);
    color: var(--success);
    border-radius: 15px;
    font-size: 12px;
    border: 1px solid rgba(76, 175, 80, 0.3);
    margin-top: 5px;
}

.house-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.action-btn {
    flex: 1;
    padding: 10px;
    text-align: center;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.view-btn {
    background: rgba(255, 171, 0, 0.1);
    color: var(--gold);
    border: 1px solid rgba(255, 171, 0, 0.3);
}

.view-btn:hover {
    background: rgba(255, 171, 0, 0.2);
}

.request-btn {
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--navy-black);
    border: 1px solid var(--gold);
}

.request-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 171, 0, 0.3);
}

/* پیام عدم وجود خانه */
.no-houses {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    backdrop-filter: blur(15px);
}

.no-houses i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.no-houses h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: var(--text-light);
}

.no-houses p {
    margin-bottom: 20px;
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

.page-header, .filters-card, .house-card {
    animation: slideInUp 0.6s ease-out;
}

/* ریسپانسیو */
@media (max-width: 768px) {
    .houses-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .filter-actions {
        justify-content: center;
        flex-direction: column;
    }
    
    .nav-container {
        flex-direction: column;
        gap: 15px;
    }
    
    .nav-actions {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .house-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .house-price {
        align-self: flex-start;
    }
}

/* افکت‌های ویژه */
.glass-effect {
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
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
<nav class="main-nav glass-effect">
    <div class="nav-container">
        <a href="../index.php" class="brand">
            <i class="fas fa-home-heart"></i>
            هم‌اتاقی
        </a>
        
        <div class="nav-actions">
            <?php if ($usertype === 'صاحب‌خانه'): ?>
                <a href="add_house.php" class="nav-btn primary">
                    <i class="fas fa-plus"></i>
                    ثبت خانه
                </a>
                <a href="my_houses.php" class="nav-btn">
                    <i class="fas fa-house-user"></i>
                    خانه‌های من
                </a>
            <?php else: ?>
                <a href="available_houses.php" class="nav-btn primary">
                    <i class="fas fa-search"></i>
                    جستجوی خانه
                </a>
                <a href="favorites.php" class="nav-btn">
                    <i class="fas fa-heart"></i>
                    علاقه‌مندی‌ها
                </a>
                <a href="my_requests.php" class="nav-btn">
                    <i class="fas fa-envelope-open-text"></i>
                    درخواست‌های من
                </a>
            <?php endif; ?>
            
            <a href="dashboard.php" class="nav-btn">
                <i class="fas fa-tachometer-alt"></i>
                داشبورد
            </a>
            <a href="messages.php" class="nav-btn">
                <i class="fas fa-comments"></i>
                پیام‌ها
            </a>
            
            <a href="../logout.php" class="nav-btn logout">
                <i class="fas fa-sign-out-alt"></i>
                خروج
            </a>
        </div>
    </div>
</nav>

<!-- محتوای اصلی -->
<div class="houses-container">
    <!-- هدر صفحه -->
    <div class="page-header glass-effect">
        <div class="header-content">
            <div class="header-text">
                <h1>🏠 جستجوی خانه هم‌اتاقی</h1>
                <p>خانه ایده‌آل خودت رو بین صدها آگهی معتبر پیدا کن</p>
                <?php if($user_personality): ?>
                    <div style="margin-top: 10px;">
                        <span style="background: rgba(139, 92, 246, 0.2); color: var(--purple); padding: 5px 10px; border-radius: 15px; font-size: 12px; border: 1px solid rgba(139, 92, 246, 0.3);">
                            <i class="fas fa-brain"></i>
                            تیپ شخصیتی شما: <?php echo $user_personality; ?>
                        </span>
                    </div>
                <?php endif; ?>
                <div style="margin-top: 10px;">
                    <span style="background: rgba(255, 171, 0, 0.2); color: var(--gold); padding: 5px 10px; border-radius: 15px; font-size: 12px; border: 1px solid rgba(255, 171, 0, 0.3);">
                        <i class="fas fa-user"></i>
                        فیلتر پیش‌فرض: <?php echo $gender; ?>
                        <?php if($default_gender_filter): ?>
                            <span style="color: var(--text-muted);">(تغییر داده شده به: <?php echo $default_gender_filter; ?>)</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <div class="results-count">
                <i class="fas fa-home"></i>
                <?php echo count($houses); ?> خانه پیدا شد
            </div>
        </div>
    </div>

    <!-- فیلترهای جستجو -->
    <div class="filters-card glass-effect">
        <form method="GET" action="" id="searchForm">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">جستجوی آزاد</label>
                    <input type="text" name="search" class="filter-input" placeholder="عنوان، آدرس یا توضیحات..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">استان</label>
                    <select name="province" class="filter-select">
                        <option value="">همه استان‌ها</option>
                        <?php foreach($provinces as $prov): ?>
                            <option value="<?php echo htmlspecialchars($prov); ?>" <?php echo ($province === $prov) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prov); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">شهر</label>
                    <select name="city" class="filter-select">
                        <option value="">همه شهرها</option>
                        <?php foreach($cities as $ct): ?>
                            <option value="<?php echo htmlspecialchars($ct); ?>" <?php echo ($city === $ct) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ct); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">جنسیت</label>
                    <select name="gender" class="filter-select">
                        <option value="">پیش‌فرض (<?php echo $gender; ?>)</option>
                        <option value="آقا" <?php echo $default_gender_filter === 'آقا' ? 'selected' : ''; ?>>آقا</option>
                        <option value="خانم" <?php echo $default_gender_filter === 'خانم' ? 'selected' : ''; ?>>خانم</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">حداقل قیمت (تومان)</label>
                    <input type="number" name="min_price" class="filter-input" placeholder="۰" value="<?php echo htmlspecialchars($min_price); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">حداکثر قیمت (تومان)</label>
                    <input type="number" name="max_price" class="filter-input" placeholder="نامحدود" value="<?php echo htmlspecialchars($max_price); ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">مرتب‌سازی</label>
                    <select name="sort" class="filter-select">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>جدیدترین</option>
                        <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>ارزان‌ترین</option>
                        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>گران‌ترین</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="button" class="reset-btn" onclick="resetFilters()">
                    <i class="fas fa-undo"></i>
                    بازنشانی فیلترها
                </button>
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                    جستجوی خانه‌ها
                </button>
            </div>
        </form>
    </div>

    <!-- لیست خانه‌ها -->
    <?php if (!empty($houses)): ?>
        <div class="houses-grid">
            <?php foreach($houses as $house): 
                // محاسبه تطبیق شخصیتی
                $personality_match = false;
                if ($user_personality && !empty($house['owner_personality'])) {
                    $personality_match = ($user_personality === $house['owner_personality']);
                }
                
                // محاسبه ستاره‌های امتیاز اخلاقی
                $moral_score = floatval($house['moral_score'] ?? 0);
                $total_ratings = intval($house['total_ratings'] ?? 0);
                $stars = round($moral_score);
                $occupiedSlots = max((int)$house['capacity'] - (int)$house['available_capacity'], 0);
                $freeSlots = max((int)$house['available_capacity'], 0);
            ?>
                <div class="house-card glass-effect">
                    <div class="house-image">
                        <?php if (!empty($house['images'])): 
                            $images = json_decode($house['images'], true);
                            $first_image = is_array($images) ? $images[0] : $house['images'];
                        ?>
                            <img src="../<?php echo htmlspecialchars($first_image); ?>" alt="<?php echo htmlspecialchars($house['title']); ?>">
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-muted);">
                                <i class="fas fa-home" style="font-size: 3rem;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="house-badge <?php echo $house['gender'] === 'خانم' ? 'female' : ''; ?>">
                            <i class="fas fa-<?php echo $house['gender'] === 'خانم' ? 'female' : 'male'; ?>"></i>
                            <?php echo htmlspecialchars($house['gender']); ?>
                        </div>
                        
                        <button class="favorite-btn <?php echo $house['is_favorite'] ? 'active' : ''; ?>" 
                                data-house-id="<?php echo $house['id']; ?>">
                            <i class="fas fa-heart"></i>
                        </button>
                    </div>
                    
                    <div class="house-content">
                        <div class="house-header">
                            <h3 class="house-title"><?php echo htmlspecialchars($house['title']); ?></h3>
                            <div class="house-price">
                                <?php echo number_format($house['price']); ?> تومان
                            </div>
                        </div>
                        
                        <div class="house-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($house['city'] . '، ' . $house['province']); ?></span>
                        </div>
                        
                        <div class="house-details">
                            <div class="detail-item">
                                <i class="fas fa-users"></i>
                                <span><?php echo $occupiedSlots . '/' . (int)$house['capacity']; ?> نفر</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-bed"></i>
                                <span><?php echo $freeSlots; ?> جای خالی</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-heart"></i>
                                <span><?php echo $house['favorites_count']; ?> علاقه‌مندی</span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('Y/m/d', strtotime($house['created_at'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="house-owner">
                            <div class="owner-avatar">
                                <?php echo mb_substr($house['owner_name'] ?? 'م', 0, 1); ?>
                            </div>
                            <div class="owner-info">
                                <div class="owner-name"><?php echo htmlspecialchars($house['owner_name'] ?? 'مالک'); ?></div>
                                <?php if (!empty($house['owner_personality'])): ?>
                                    <div class="owner-personality">
                                        تیپ شخصیتی: <?php echo htmlspecialchars($house['owner_personality']); ?>
                                        <?php if ($personality_match): ?>
                                            <span class="personality-match">
                                                <i class="fas fa-check"></i>
                                                تطبیق کامل
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- امتیاز اخلاقی -->
                        <?php if ($moral_score > 0): ?>
                        <div class="moral-score">
                            <div class="score-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="star <?php echo $i <= $stars ? 'filled' : ''; ?>">
                                        <i class="fas fa-star"></i>
                                    </span>
                                <?php endfor; ?>
                            </div>
                            <span class="score-value"><?php echo number_format($moral_score, 1); ?></span>
                            <span class="score-count">(<?php echo $total_ratings; ?> نظر)</span>
                        </div>
                        <?php else: ?>
                        <div class="moral-score">
                            <span style="color: var(--text-muted); font-size: 14px;">
                                <i class="fas fa-info-circle"></i>
                                هنوز امتیازی ثبت نشده
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- فقط دکمه مشاهده جزئیات -->
                        <div class="house-actions">
                            <a href="listing_details.php?id=<?php echo $house['id']; ?>" class="action-btn view-btn" style="flex: 1;">
                                <i class="fas fa-eye"></i>
                                مشاهده جزئیات و ارسال درخواست
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-houses glass-effect">
            <i class="fas fa-home"></i>
            <h3>خانه‌ای یافت نشد!</h3>
            <p>
                <?php if (!$table_exists): ?>
                    جدول خانه‌ها وجود ندارد. لطفاً اول جدول‌ها را ایجاد کنید.
                <?php else: ?>
                    با تغییر فیلترهای جستجو دوباره امتحان کنید
                <?php endif; ?>
            </p>
            <?php if (!$table_exists): ?>
                <a href="create_tables.php" class="search-btn" style="display: inline-block; margin-top: 20px; text-decoration: none; padding: 10px 20px;">
                    <i class="fas fa-database"></i>
                    ایجاد جدول‌ها
                </a>
            <?php endif; ?>
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

// مدیریت علاقه‌مندی‌ها
document.querySelectorAll('.favorite-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const houseId = this.dataset.houseId;
        const isActive = this.classList.contains('active');

        fetch('toggle_favorite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `house_id=${houseId}&action=${isActive ? 'remove' : 'add'}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.classList.toggle('active', data.status === 'added');
                    const countElement = this.closest('.house-card').querySelector('.detail-item:nth-child(3) span');
                    if (countElement && typeof data.favorites_count !== 'undefined') {
                        countElement.textContent = data.favorites_count + ' علاقه‌مندی';
                    }
                } else if (data.message) {
                    alert(data.message);
                }
            })
            .catch(() => {
                alert('در ذخیره علاقه‌مندی خطایی رخ داد.');
            });
    });
});

// بازنشانی فیلترها
function resetFilters() {
    document.getElementById('searchForm').reset();
    document.getElementById('searchForm').submit();
}

// انیمیشن کارت‌ها
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.house-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
    });
});
</script>

</body>
</html>