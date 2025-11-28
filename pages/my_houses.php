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
$user_sql = "SELECT full_name, usertype FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: logout.php");
    exit();
}

// بررسی اینکه کاربر صاحب‌خانه باشد
if ($user['usertype'] !== 'صاحب‌خانه') {
    header("Location: dashboard.php");
    exit();
}

// دریافت خانه‌های کاربر
$houses = [];
$stats = ['total_houses' => 0, 'active_houses' => 0, 'pending_houses' => 0, 'inactive_houses' => 0];
$new_requests = ['new_requests' => 0];

try {
    $houses_sql = "
        SELECT *,
            CASE status 
                WHEN 'فعال' THEN 'active'
                WHEN 'در انتظار تایید' THEN 'pending' 
                ELSE 'inactive' 
            END as status_class,
            CASE status 
                WHEN 'فعال' THEN '✅ فعال' 
                WHEN 'در انتظار تایید' THEN '⏳ در انتظار تایید' 
                ELSE '❌ غیرفعال' 
            END as status_text
        FROM houses 
        WHERE user_id = ? 
        ORDER BY 
            CASE status 
                WHEN 'فعال' THEN 1
                WHEN 'در انتظار تایید' THEN 2
                ELSE 3
            END,
            created_at DESC
    ";
    $houses_stmt = $conn->prepare($houses_sql);
    $houses_stmt->execute([$user_id]);
    $houses = $houses_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching houses: " . $e->getMessage());
    $houses = [];
}

try {
    // دریافت آمار خانه‌ها
    $stats_sql = "
        SELECT 
            COUNT(*) as total_houses,
            SUM(CASE WHEN status = 'فعال' THEN 1 ELSE 0 END) as active_houses,
            SUM(CASE WHEN status = 'در انتظار تایید' THEN 1 ELSE 0 END) as pending_houses,
            SUM(CASE WHEN status = 'غیرفعال' THEN 1 ELSE 0 END) as inactive_houses
        FROM houses 
        WHERE user_id = ?
    ";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->execute([$user_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: $stats;
} catch (PDOException $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}

try {
    // دریافت درخواست‌های جدید
    $new_requests_sql = "
        SELECT COUNT(*) as new_requests 
        FROM requests 
        WHERE house_id IN (SELECT id FROM houses WHERE user_id = ?) 
        AND status = 'در انتظار'
    ";
    $new_requests_stmt = $conn->prepare($new_requests_sql);
    $new_requests_stmt->execute([$user_id]);
    $new_requests = $new_requests_stmt->fetch(PDO::FETCH_ASSOC) ?: $new_requests;
} catch (PDOException $e) {
    error_log("Error fetching new requests: " . $e->getMessage());
}

$username = htmlspecialchars($user['full_name']);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>خانه‌های من | هم‌اتاقی</title>
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

.notification-badge {
    position: absolute;
    top: -8px;
    left: -8px;
    background: var(--danger);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    animation: pulse 2s infinite;
}

/* محتوای اصلی */
.my-houses-container {
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

.header-actions {
    display: flex;
    gap: 15px;
}

/* کارت‌های آمار */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 15px;
    padding: 25px;
    backdrop-filter: blur(15px);
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--accent, var(--gold));
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.stat-icon {
    font-size: 2.5rem;
    margin-bottom: 15px;
    opacity: 0.9;
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
    font-weight: 500;
}

/* فیلترها و جستجو */
.filters-card {
    background: rgba(15, 26, 33, 0.9);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    backdrop-filter: blur(15px);
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
}

.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-light);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.filter-tab:hover {
    background: rgba(255, 255, 255, 0.2);
}

.filter-tab.active {
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--navy-black);
    border-color: var(--gold);
}

.search-box {
    display: flex;
    gap: 15px;
    align-items: center;
}

.search-input {
    flex: 1;
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    color: var(--text-light);
    font-family: 'Tajawal', sans-serif;
    font-size: 14px;
}

.search-input:focus {
    outline: none;
    border-color: var(--gold);
}

/* لیست خانه‌ها */
.houses-section {
    margin-bottom: 40px;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.section-header i {
    font-size: 24px;
    color: var(--gold);
}

.section-header h2 {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-light);
}

.houses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
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

.status-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 700;
    backdrop-filter: blur(10px);
}

.status-active {
    background: rgba(76, 175, 80, 0.9);
    color: white;
}

.status-pending {
    background: rgba(255, 152, 0, 0.9);
    color: white;
}

.status-inactive {
    background: rgba(244, 67, 54, 0.9);
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

.edit-btn {
    background: rgba(33, 150, 243, 0.1);
    color: var(--info);
    border: 1px solid rgba(33, 150, 243, 0.3);
}

.edit-btn:hover {
    background: rgba(33, 150, 243, 0.2);
}

.requests-btn {
    background: rgba(76, 175, 80, 0.1);
    color: var(--success);
    border: 1px solid rgba(76, 175, 80, 0.3);
}

.requests-btn:hover {
    background: rgba(76, 175, 80, 0.2);
}

.requests-badge {
    background: var(--danger);
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 600;
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
    grid-column: 1 / -1;
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

/* دکمه‌های action */
.primary-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 25px;
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--navy-black);
    border: none;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 15px;
}

.primary-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 171, 0, 0.4);
}

.secondary-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-light);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.secondary-btn:hover {
    background: rgba(255, 255, 255, 0.2);
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

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.8; }
}

.page-header, .stat-card, .filters-card, .house-card {
    animation: slideInUp 0.6s ease-out;
}

/* ریسپانسیو */
@media (max-width: 768px) {
    .houses-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .header-actions {
        justify-content: center;
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
    
    .house-actions {
        flex-direction: column;
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
            <a href="add_house.php" class="nav-btn primary">
                <i class="fas fa-plus"></i>
                ثبت خانه جدید
            </a>
            <a href="available_houses.php" class="nav-btn">
                <i class="fas fa-search"></i>
                جستجوی خانه
            </a>
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
<div class="my-houses-container">
    <!-- هدر صفحه -->
    <div class="page-header glass-effect">
        <div class="header-content">
            <div class="header-text">
                <h1>🏠 خانه‌های من</h1>
                <p>مدیریت و مشاهده تمام خانه‌های ثبت‌شده شما</p>
            </div>
            <div class="header-actions">
                <a href="add_house.php" class="primary-btn">
                    <i class="fas fa-plus"></i>
                    ثبت خانه جدید
                </a>
            </div>
        </div>
    </div>

    <!-- کارت‌های آمار -->
    <div class="stats-grid">
        <div class="stat-card hover-lift" style="--accent: var(--info)">
            <div class="stat-icon">
                <i class="fas fa-home"></i>
            </div>
            <div class="stat-number"><?php echo $stats['total_houses'] ?? 0; ?></div>
            <div class="stat-label">کل خانه‌ها</div>
        </div>
        
        <div class="stat-card hover-lift" style="--accent: var(--success)">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-number"><?php echo $stats['active_houses'] ?? 0; ?></div>
            <div class="stat-label">خانه‌های فعال</div>
        </div>
        
        <div class="stat-card hover-lift" style="--accent: var(--warning)">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-number"><?php echo $stats['pending_houses'] ?? 0; ?></div>
            <div class="stat-label">در انتظار تایید</div>
        </div>
        
        <div class="stat-card hover-lift" style="--accent: var(--danger)">
            <div class="stat-icon">
                <i class="fas fa-bell"></i>
            </div>
            <div class="stat-number"><?php echo $new_requests['new_requests'] ?? 0; ?></div>
            <div class="stat-label">درخواست جدید</div>
        </div>
    </div>

    <!-- فیلترها و جستجو -->
    <div class="filters-card glass-effect">
        <div class="filter-tabs">
            <button class="filter-tab active" data-filter="all">همه خانه‌ها</button>
            <button class="filter-tab" data-filter="active">فعال</button>
            <button class="filter-tab" data-filter="pending">در انتظار</button>
            <button class="filter-tab" data-filter="inactive">غیرفعال</button>
        </div>
        <div class="search-box">
            <input type="text" class="search-input" placeholder="جستجو در خانه‌های من..." id="searchInput">
            <button class="secondary-btn">
                <i class="fas fa-search"></i>
                جستجو
            </button>
        </div>
    </div>

    <!-- لیست خانه‌ها -->
    <div class="houses-section">
        <div class="section-header">
            <i class="fas fa-list"></i>
            <h2>لیست خانه‌های شما</h2>
        </div>

        <?php if (!empty($houses)): ?>
            <div class="houses-grid">
                <?php foreach($houses as $house): ?>
                    <div class="house-card glass-effect" data-status="<?php echo $house['status_class']; ?>">
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
                            
                            <div class="status-badge status-<?php echo $house['status_class']; ?>">
                                <?php echo $house['status_text']; ?>
                            </div>
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
                            
                            <?php
                                $occupied = max((int)$house['capacity'] - (int)$house['available_capacity'], 0);
                                $free = max((int)$house['available_capacity'], 0);
                            ?>
                            <div class="house-details">
                                <div class="detail-item">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo $occupied . '/' . (int)$house['capacity']; ?> نفر</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-bed"></i>
                                    <span><?php echo $free; ?> جای خالی</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('Y/m/d', strtotime($house['created_at'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-eye"></i>
                                    <span>۰ بازدید</span>
                                </div>
                            </div>
                            
                            <div class="house-actions">
                                <a href="my_house_details.php?id=<?php echo $house['id']; ?>" class="action-btn view-btn">
                                    <i class="fas fa-eye"></i>
                                    مشاهده
                                </a>
                                <a href="edit_house.php?id=<?php echo $house['id']; ?>" class="action-btn edit-btn">
                                    <i class="fas fa-edit"></i>
                                    ویرایش
                                </a>
                                <a href="house_requests.php?house_id=<?php echo $house['id']; ?>" class="action-btn requests-btn">
                                    <i class="fas fa-envelope"></i>
                                    درخواست‌ها
                                    <?php if ($house['status_class'] === 'active' && ($new_requests['new_requests'] ?? 0) > 0): ?>
                                        <span class="requests-badge"><?php echo $new_requests['new_requests']; ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-houses glass-effect">
                <i class="fas fa-home"></i>
                <h3>هنوز خانه‌ای ثبت نکرده‌اید!</h3>
                <p>برای شروع، اولین خانه خود را ثبت کنید</p>
                <a href="add_house.php" class="primary-btn">
                    <i class="fas fa-plus"></i>
                    ثبت اولین خانه
                </a>
            </div>
        <?php endif; ?>
    </div>
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

// فیلتر خانه‌ها
document.addEventListener('DOMContentLoaded', function() {
    const filterTabs = document.querySelectorAll('.filter-tab');
    const houseCards = document.querySelectorAll('.house-card');
    const searchInput = document.getElementById('searchInput');
    
    // فیلتر بر اساس تب
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const filter = this.dataset.filter;
            
            // آپدیت تب فعال
            filterTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // فیلتر خانه‌ها
            houseCards.forEach(card => {
                if (filter === 'all' || card.dataset.status === filter) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
    
    // جستجو
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        houseCards.forEach(card => {
            const title = card.querySelector('.house-title').textContent.toLowerCase();
            const location = card.querySelector('.house-location span').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || location.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
    
    // انیمیشن کارت‌ها
    houseCards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
    });
});
</script>

</body>
</html>