<?php
session_start();
include __DIR__ . '/../includes/config.php';

// بررسی ورود کاربر
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// استفاده از PDO
$sql = "SELECT full_name, usertype, gender, personality_type, city, province, email, phone FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: logout.php");
    exit();
}

// دریافت آمار کاربر (با مدیریت خطا)
$stats = [];
try {
    if ($user['usertype'] === 'صاحب‌خانه') {
        // آمار صاحب خانه
        $house_stats = $conn->prepare("
            SELECT 
                COUNT(*) as total_houses,
                SUM(CASE WHEN status = 'فعال' THEN 1 ELSE 0 END) as active_houses,
                SUM(CASE WHEN status = 'در انتظار تایید' THEN 1 ELSE 0 END) as pending_houses
            FROM houses 
            WHERE user_id = ?
        ");
        $house_stats->execute([$user_id]);
        $stats = $house_stats->fetch(PDO::FETCH_ASSOC) ?: [];
        
        // دریافت درخواست‌های جدید
        $new_requests = $conn->prepare("
            SELECT COUNT(*) as new_requests 
            FROM requests 
            WHERE house_id IN (SELECT id FROM houses WHERE user_id = ?) 
            AND status = 'در انتظار'
        ");
        $new_requests->execute([$user_id]);
        $requests_count = $new_requests->fetch(PDO::FETCH_ASSOC);
        $stats['new_requests'] = $requests_count['new_requests'] ?? 0;
        
    } else {
        // آمار متقاضی
        $request_stats = $conn->prepare("
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'در انتظار' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE WHEN status = 'تایید شده' THEN 1 ELSE 0 END) as accepted_requests,
                SUM(CASE WHEN status = 'لغو شده' THEN 1 ELSE 0 END) as cancelled_requests
            FROM requests 
            WHERE user_id = ?
        ");
        $request_stats->execute([$user_id]);
        $stats = $request_stats->fetch(PDO::FETCH_ASSOC) ?: [];
        
        // تعداد علاقه‌مندی‌ها
        $favorites = $conn->prepare("SELECT COUNT(*) as favorites FROM favorites WHERE user_id = ?");
        $favorites->execute([$user_id]);
        $fav_count = $favorites->fetch(PDO::FETCH_ASSOC);
        $stats['favorites'] = $fav_count['favorites'] ?? 0;
    }
} catch(PDOException $e) {
    // اگر جدول وجود نداشت، مقادیر پیش‌فرض
    $stats = [
        'total_houses' => 0, 'active_houses' => 0, 'pending_houses' => 0, 'new_requests' => 0,
        'total_requests' => 0, 'pending_requests' => 0, 'accepted_requests' => 0, 'favorites' => 0
    ];
}

// دریافت آخرین فعالیت‌ها
$activities = [];
try {
    $recent_activities = $conn->prepare("
        SELECT activity_type, description, created_at 
        FROM user_activities 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 4
    ");
    $recent_activities->execute([$user_id]);
    $activities = $recent_activities->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $activities = [];
}

$username = htmlspecialchars($user['full_name']);
$usertype = $user['usertype'];
$gender = $user['gender'];
$personality = $user['personality_type'];
$city = htmlspecialchars($user['city']);
$province = htmlspecialchars($user['province']);
$email = htmlspecialchars($user['email']);
$phone = htmlspecialchars($user['phone']);

$unreadCount = 0;
try {
    $unreadStmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $unreadStmt->execute([$user_id]);
    $unreadCount = (int)$unreadStmt->fetchColumn();
} catch (PDOException $e) {
    $unreadCount = 0;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>داشبورد | هم‌اتاقی</title>
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

/* افکت پارتیکل پویا پیشرفته */
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
.dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px 20px;
}

/* هدر کاربر */
.user-header {
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

.user-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--gold), var(--purple), var(--gold));
    animation: shimmer 3s ease-in-out infinite;
}

.user-header::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 171, 0, 0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
    z-index: -1;
}

.user-info {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.user-main {
    flex: 1;
}

.user-greeting {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 10px;
    color: var(--text-light);
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
}

.user-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 171, 0, 0.2);
    color: var(--gold);
    padding: 8px 20px;
    border-radius: 25px;
    font-weight: 600;
    margin: 10px 0;
    border: 1px solid rgba(255, 171, 0, 0.3);
    font-size: 14px;
    backdrop-filter: blur(10px);
}

.user-details {
    display: flex;
    gap: 25px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
    font-size: 14px;
    background: rgba(255, 255, 255, 0.08);
    padding: 8px 16px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(5px);
}

/* کارت‌های آمار */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

/* کارت‌های اصلی */
.cards-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
    margin-bottom: 30px;
}

.main-cards {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

/* کارت تست شخصیت */
.personality-card {
    background: rgba(15, 26, 33, 0.9);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 30px;
    backdrop-filter: blur(15px);
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
}

.personality-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--gold), var(--purple), var(--gold));
    animation: shimmer 3s ease-in-out infinite;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.card-header i {
    font-size: 24px;
    color: var(--gold);
}

.card-header h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-light);
}

.test-description {
    color: var(--text-muted);
    margin-bottom: 25px;
    line-height: 1.7;
    font-size: 15px;
}

.test-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--navy-black);
    padding: 12px 25px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    font-size: 15px;
    box-shadow: 0 4px 15px rgba(255, 171, 0, 0.3);
}

.test-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(255, 171, 0, 0.5);
}

/* کارت نتیجه تست */
.result-card {
    background: rgba(15, 26, 33, 0.9);
    border: 1px solid rgba(139, 92, 246, 0.4);
}

.personality-badge {
    font-size: 42px;
    font-weight: 800;
    text-align: center;
    margin: 20px 0;
    background: linear-gradient(135deg, var(--gold), var(--purple));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: 0 4px 20px rgba(255, 171, 0, 0.3);
}

/* کارت فعالیت‌های اخیر */
.activities-card {
    background: rgba(15, 26, 33, 0.9);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 25px;
    backdrop-filter: blur(15px);
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
}

.activity-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    border-right: 3px solid var(--gold);
    transition: all 0.3s ease;
}

.activity-item:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateX(-5px);
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 171, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
    font-size: 1.1rem;
}

.activity-content {
    flex: 1;
}

.activity-text {
    color: var(--text-light);
    font-weight: 500;
    margin-bottom: 5px;
}

.activity-time {
    color: var(--text-muted);
    font-size: 0.8rem;
}

/* سایدبار */
.sidebar-cards {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.quick-action-card {
    background: rgba(15, 26, 33, 0.9);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 25px;
    backdrop-filter: blur(15px);
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 15px 20px;
    background: rgba(255, 171, 0, 0.1);
    color: var(--text-light);
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 171, 0, 0.2);
    margin-bottom: 10px;
    font-size: 14px;
    backdrop-filter: blur(5px);
    position: relative;
}

.action-btn:last-child {
    margin-bottom: 0;
}

.action-btn:hover {
    background: rgba(255, 171, 0, 0.2);
    transform: translateX(-5px);
    border-color: var(--gold);
    box-shadow: 0 4px 15px rgba(255, 171, 0, 0.2);
}

.action-btn.primary {
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--navy-black);
    border: 1px solid var(--gold);
}

.action-btn i {
    font-size: 18px;
    width: 20px;
}

.action-badge {
    background: var(--danger);
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-right: auto;
}

/* استاتوس کارت */
.status-card {
    background: rgba(15, 26, 33, 0.9);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 25px;
    backdrop-filter: blur(15px);
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.status-item:last-child {
    border-bottom: none;
}

.status-label {
    color: var(--text-muted);
    font-weight: 500;
    font-size: 14px;
}

.status-value {
    font-weight: 600;
    color: var(--text-light);
    font-size: 14px;
}

.status-badge {
    background: rgba(76, 175, 80, 0.2);
    color: var(--success);
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid rgba(76, 175, 80, 0.3);
}

/* انیمیشن‌های پیشرفته */
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

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
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

.user-header, .personality-card, .quick-action-card, .status-card, .stat-card, .activities-card {
    animation: slideInUp 0.8s ease-out;
}

/* ریسپانسیو */
@media (max-width: 768px) {
    .cards-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .user-info {
        flex-direction: column;
        gap: 20px;
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
    
    .user-details {
        flex-direction: column;
        gap: 15px;
    }
    
    .nav-btn {
        padding: 8px 12px;
        font-size: 13px;
    }
}

/* افکت‌های ویژه */
.glass-effect {
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}

.hover-lift {
    transition: all 0.4s ease;
}

.hover-lift:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
}
</style>
</head>
<body>

<!-- بک‌گراند پویا پیشرفته -->
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
                    <?php if (($stats['new_requests'] ?? 0) > 0): ?>
                        <span class="notification-badge"><?php echo $stats['new_requests']; ?></span>
                    <?php endif; ?>
                </a>
            <?php else: ?>
                <a href="available_houses.php" class="nav-btn primary">
                    <i class="fas fa-search"></i>
                    جستجوی خانه
                </a>
                <a href="favorites.php" class="nav-btn">
                    <i class="fas fa-heart"></i>
                    علاقه‌مندی‌ها
                    <?php if (($stats['favorites'] ?? 0) > 0): ?>
                        <span class="notification-badge"><?php echo $stats['favorites']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="my_requests.php" class="nav-btn">
                    <i class="fas fa-envelope-open-text"></i>
                    درخواست‌های من
                </a>
            <?php endif; ?>
            
            <a href="profile.php" class="nav-btn">
                <i class="fas fa-user-cog"></i>
                پروفایل
            </a>
            <a href="messages.php" class="nav-btn">
                <i class="fas fa-comments"></i>
                پیام‌ها
                <?php if ($unreadCount > 0): ?>
                    <span class="notification-badge"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="../logout.php" class="nav-btn logout">
                <i class="fas fa-sign-out-alt"></i>
                خروج
            </a>
        </div>
    </div>
</nav>

<!-- محتوای اصلی -->
<div class="dashboard">
    <!-- هدر کاربر -->
    <div class="user-header glass-effect hover-lift">
        <div class="user-info">
            <div class="user-main">
                <h1 class="user-greeting">سلام <?php echo $username; ?>! 👋</h1>
                <div class="user-badge">
                    <i class="fas fa-<?php echo $gender === 'خانم' ? 'female' : 'male'; ?>"></i>
                    <?php echo $gender . ' | ' . $usertype; ?>
                </div>
                
                <div class="user-details">
                    <?php if ($city && $province): ?>
                    <div class="detail-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo $city . '، ' . $province; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="detail-item">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo $email; ?></span>
                    </div>
                    
                    <?php if ($phone): ?>
                    <div class="detail-item">
                        <i class="fas fa-phone"></i>
                        <span><?php echo $phone; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="user-stats">
                <div class="status-badge">فعال</div>
            </div>
        </div>
    </div>

    <!-- کارت‌های آمار -->
    <div class="stats-grid">
        <?php if ($usertype === 'صاحب‌خانه'): ?>
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
                <div class="stat-number"><?php echo $stats['new_requests'] ?? 0; ?></div>
                <div class="stat-label">درخواست جدید</div>
            </div>
            
        <?php else: ?>
            <div class="stat-card hover-lift" style="--accent: var(--info)">
                <div class="stat-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_requests'] ?? 0; ?></div>
                <div class="stat-label">درخواست‌های ارسالی</div>
            </div>
            
            <div class="stat-card hover-lift" style="--accent: var(--warning)">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['pending_requests'] ?? 0; ?></div>
                <div class="stat-label">در انتظار پاسخ</div>
            </div>
            
            <div class="stat-card hover-lift" style="--accent: var(--success)">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['accepted_requests'] ?? 0; ?></div>
                <div class="stat-label">تایید شده</div>
            </div>
            <div class="stat-card hover-lift" style="--accent: var(--danger)">
                <div class="stat-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="stat-number"><?php echo $stats['cancelled_requests'] ?? 0; ?></div>
                <div class="stat-label">لغو شده</div>
            </div>
            
            <div class="stat-card hover-lift" style="--accent: var(--gold)">
                <div class="stat-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="stat-number"><?php echo $stats['favorites'] ?? 0; ?></div>
                <div class="stat-label">علاقه‌مندی‌ها</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- کارت‌های اصلی -->
    <div class="cards-grid">
        <div class="main-cards">
            <!-- کارت تست شخصیت -->
            <?php if (empty($personality)): ?>
                <div class="personality-card hover-lift">
                    <div class="card-header">
                        <i class="fas fa-brain"></i>
                        <h3>تست شخصیت MBTI</h3>
                    </div>
                    <p class="test-description">
                        برای پیدا کردن هم‌اتاقی‌های سازگارتر، تست شخصیت MBTI رو انجام بده. 
                        این تست به ما کمک می‌کنه بر اساس تیپ شخصیتیت، بهترین پیشنهادات رو برات پیدا کنیم.
                    </p>
                    <a href="personality_test.php" class="test-btn">
                        <i class="fas fa-play"></i>
                        شروع تست شخصیت
                    </a>
                </div>
            <?php else: ?>
                <div class="personality-card result-card hover-lift">
                    <div class="card-header">
                        <i class="fas fa-star"></i>
                        <h3>تیپ شخصیتی شما</h3>
                    </div>
                    <div class="personality-badge"><?php echo $personality; ?></div>
                    <p class="test-description">
                        تیپ شخصیتی شما با موفقیت ثبت شد! حالا می‌تونیم هم‌اتاقی‌های سازگارتر با شخصیت شما رو پیشنهاد بدیم.
                    </p>
                </div>
            <?php endif; ?>

            <!-- فعالیت‌های اخیر -->
            <div class="activities-card hover-lift">
                <div class="card-header">
                    <i class="fas fa-history"></i>
                    <h3>فعالیت‌های اخیر</h3>
                </div>
                <div class="activity-list">
                    <?php if (!empty($activities)): ?>
                        <?php foreach($activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-<?php 
                                        switch($activity['activity_type']) {
                                            case 'login': echo 'sign-in-alt'; break;
                                            case 'profile_update': echo 'user-edit'; break;
                                            case 'house_add': echo 'home'; break;
                                            case 'request_sent': echo 'paper-plane'; break;
                                            default: echo 'bell';
                                        }
                                    ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text"><?php echo $activity['description']; ?></div>
                                    <div class="activity-time">
                                        <?php echo date('j F Y - H:i', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">هنوز فعالیتی ثبت نشده است</div>
                                <div class="activity-time">از سیستم استفاده کنید تا فعالیت‌ها اینجا نمایش داده شوند</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- سایدبار -->
        <div class="sidebar-cards">
            <!-- اقدامات سریع -->
            <div class="quick-action-card glass-effect hover-lift">
                <div class="card-header">
                    <i class="fas fa-bolt"></i>
                    <h3>اقدامات سریع</h3>
                </div>
                
                <?php if ($usertype === 'صاحب‌خانه'): ?>
                    <a href="add_house.php" class="action-btn primary">
                        <i class="fas fa-plus-circle"></i>
                        ثبت خانه جدید
                    </a>
                    <a href="my_houses.php" class="action-btn">
                        <i class="fas fa-list"></i>
                        مدیریت خانه‌ها
                        <?php if (($stats['new_requests'] ?? 0) > 0): ?>
                            <span class="action-badge"><?php echo $stats['new_requests']; ?> جدید</span>
                        <?php endif; ?>
                    </a>
                <?php else: ?>
                    <a href="available_houses.php" class="action-btn primary">
                        <i class="fas fa-search"></i>
                        جستجوی خانه
                    </a>
                    <a href="favorites.php" class="action-btn">
                        <i class="fas fa-heart"></i>
                        علاقه‌مندی‌ها
                        <?php if (($stats['favorites'] ?? 0) > 0): ?>
                            <span class="action-badge"><?php echo $stats['favorites']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="my_requests.php" class="action-btn">
                        <i class="fas fa-envelope-open-text"></i>
                        درخواست‌های من
                    </a>
                <?php endif; ?>
                
                <a href="profile.php" class="action-btn">
                    <i class="fas fa-edit"></i>
                    ویرایش پروفایل
                </a>
                
                <a href="messages.php" class="action-btn">
                    <i class="fas fa-comments"></i>
                    پیام‌ها
                    <?php if ($unreadCount > 0): ?>
                        <span class="action-badge"><?php echo $unreadCount; ?> جدید</span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- وضعیت حساب -->
            <div class="status-card glass-effect hover-lift">
                <div class="card-header">
                    <i class="fas fa-chart-bar"></i>
                    <h3>وضعیت حساب</h3>
                </div>
                
                <div class="status-item">
                    <span class="status-label">جنسیت</span>
                    <span class="status-value"><?php echo $gender; ?></span>
                </div>
                
                <div class="status-item">
                    <span class="status-label">نوع حساب</span>
                    <span class="status-value"><?php echo $usertype; ?></span>
                </div>
                
                <div class="status-item">
                    <span class="status-label">تست شخصیت</span>
                    <span class="status-value">
                        <?php echo empty($personality) ? 'انجام نشده' : 'تکمیل شده'; ?>
                    </span>
                </div>
                
                <div class="status-item">
                    <span class="status-label">عضویت از</span>
                    <span class="status-value"><?php echo date('Y/m/d'); ?></span>
                </div>
                
                <div class="status-item">
                    <span class="status-label">وضعیت</span>
                    <span class="status-badge">فعال</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
<script>
// پارتیکل‌های پیشرفته
particlesJS("particles-js", {
  particles: {
    number: { value: 60, density: { enable: true, value_area: 1000 } },
    color: { value: ["#ffab00", "#8b5cf6", "#4caf50", "#2196f3"] },
    shape: { 
      type: ["circle", "triangle", "polygon"],
      polygon: { nb_sides: 6 }
    },
    opacity: { value: 0.3, random: true, anim: { enable: true, speed: 1, opacity_min: 0.1 } },
    size: { value: 3, random: true, anim: { enable: true, speed: 2, size_min: 1 } },
    line_linked: { 
      enable: true, 
      distance: 120, 
      color: "#ffab00", 
      opacity: 0.2, 
      width: 1,
      shadow: {
        enable: true,
        color: "#ffab00",
        blur: 5
      }
    },
    move: { 
      enable: true, 
      speed: 1.5, 
      direction: "none", 
      random: true, 
      straight: false, 
      out_mode: "bounce",
      attract: { enable: true, rotateX: 600, rotateY: 1200 }
    }
  },
  interactivity: {
    detect_on: "canvas",
    events: { 
      onhover: { enable: true, mode: "repulse" },
      onclick: { enable: true, mode: "push" },
      resize: true
    },
    modes: {
      repulse: { distance: 100, duration: 0.4 },
      push: { particles_nb: 4 }
    }
  },
  retina_detect: true
});

// افکت‌های تعاملی پیشرفته
document.addEventListener('DOMContentLoaded', function() {
    // انیمیشن ورود کارت‌ها
    const cards = document.querySelectorAll('.user-header, .stat-card, .personality-card, .activities-card, .quick-action-card, .status-card');
    
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
    });
    
    // افکت hover برای کارت‌ها
    const hoverCards = document.querySelectorAll('.hover-lift');
    hoverCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // افکت شimmer برای هدر
    const header = document.querySelector('.user-header');
    setInterval(() => {
        header.style.boxShadow = `0 8px 40px rgba(255, 171, 0, ${0.2 + Math.random() * 0.3})`;
    }, 2000);
    
    // انیمیشن برای نوتیفیکیشن‌ها
    const badges = document.querySelectorAll('.notification-badge, .action-badge');
    setInterval(() => {
        badges.forEach(badge => {
            badge.style.animation = 'none';
            setTimeout(() => {
                badge.style.animation = 'pulse 2s infinite';
            }, 10);
        });
    }, 4000);
});
</script>

</body>
</html>