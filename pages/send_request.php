<?php
session_start();
include __DIR__ . '/../includes/config.php';

// بررسی ورود کاربر
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// دریافت آیدی خانه از URL
$house_id = isset($_GET['house_id']) ? intval($_GET['house_id']) : 0;

if($house_id > 0) {
    // دریافت اطلاعات خانه
    $stmt = $conn->prepare("
        SELECT h.*, u.full_name as owner_name, u.personality_type as owner_personality
        FROM houses h 
        JOIN users u ON h.user_id = u.id 
        WHERE h.id = ? AND h.status = 'فعال'
    ");
    $stmt->execute([$house_id]);
    $house = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$house) {
        header("Location: 404.php");
        exit();
    }
} else {
    header("Location: available_houses.php");
    exit();
}

// دریافت اطلاعات کاربر فعلی
$user_id = $_SESSION['user_id'];
$user_stmt = $conn->prepare("SELECT full_name, email, phone, personality_type, bio, gender FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// بررسی تطابق جنسیت کاربر با جنسیت خانه
$gender_mismatch = false;
if ($current_user['gender'] !== $house['gender']) {
    $gender_mismatch = true;
    $error_message = "شما نمی‌توانید برای این خانه درخواست دهید. این خانه مخصوص {$house['gender']}ها است.";
}

if ($current_user['usertype'] !== 'هم‌خانه' || $house['user_id'] === $user_id) {
    header("Location: house_details.php?id=" . $house_id);
    exit();
}

// بررسی آیا کاربر قبلاً درخواست داده
$has_requested = false;
if (!$gender_mismatch) {
    try {
        $request_stmt = $conn->prepare("SELECT id FROM requests WHERE user_id = ? AND house_id = ?");
        $request_stmt->execute([$user_id, $house_id]);
        $has_requested = (bool)$request_stmt->fetch();
    } catch (PDOException $e) {
        $has_requested = false;
    }
}

// اگر قبلاً درخواست داده یا جنسیت تطابق ندارد، برگرد به صفحه خانه
if ($has_requested || $gender_mismatch) {
    header("Location: house_details.php?id=" . $house_id . ($gender_mismatch ? "&error=gender_mismatch" : ""));
    exit();
}

if ((int)$house['available_capacity'] <= 0) {
    header("Location: house_details.php?id=" . $house_id . "&error=capacity_full");
    exit();
}

// پردازش فرم ارسال درخواست
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formHouseId = isset($_POST['house_id']) ? (int)$_POST['house_id'] : $house_id;
    if ($formHouseId !== $house_id) {
        $error_message = 'شناسه آگهی معتبر نیست.';
    }

    $message = trim($_POST['message'] ?? '');
    $move_in_date = $_POST['move_in_date'] ?? '';
    $duration = $_POST['duration'] ?? '';
    $budget = isset($_POST['budget']) && $_POST['budget'] !== '' ? max((int)$_POST['budget'], 0) : null;
    if ($budget === 0) {
        $budget = null;
    }
    $durationOptions = [
        'کمتر از 1 ماه',
        '1 تا 3 ماه',
        '3 تا 6 ماه',
        '6 ماه تا 1 سال',
        'بیش از 1 سال',
        'نامشخص'
    ];
    
    if (!$error_message) {
        // اعتبارسنجی
        $moveInTimestamp = strtotime($move_in_date);

        if (empty($message)) {
            $error_message = 'لطفاً پیام خود را وارد کنید';
        } elseif (empty($move_in_date)) {
            $error_message = 'لطفاً تاریخ تقریبی ورود را انتخاب کنید';
        } elseif ($moveInTimestamp === false) {
            $error_message = 'تاریخ ورود معتبر نیست';
        } elseif ($moveInTimestamp < strtotime(date('Y-m-d'))) {
            $error_message = 'تاریخ ورود باید پس از امروز باشد';
        } elseif (empty($duration) || !in_array($duration, $durationOptions, true)) {
            $error_message = 'لطفاً مدت زمان اقامت را انتخاب کنید';
        } else {
            try {
                $insert_stmt = $conn->prepare("
                    INSERT INTO requests (user_id, house_id, message, move_in_date, duration, budget, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'در انتظار', NOW())
                ");
                
                $insert_stmt->execute([
                    $user_id,
                    $house_id,
                    $message,
                    $move_in_date,
                    $duration,
                    $budget
                ]);
                
                $notification_stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, title, message, type, related_id, created_at) 
                    VALUES (?, ?, ?, 'request', ?, NOW())
                ");
                
                $notification_message = "کاربر {$current_user['full_name']} برای خانه «{$house['title']}» درخواست اقامت ارسال کرده است.";
                $notification_stmt->execute([
                    $house['user_id'],
                    'درخواست اقامت جدید',
                    $notification_message,
                    $house_id
                ]);
                
                header("Location: house_details.php?id=" . $house_id . "&success=1");
                exit();
                
            } catch (PDOException $e) {
                $error_message = 'خطا در ارسال درخواست. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

// فرمت تاریخ فارسی
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
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ارسال درخواست اقامت | هم‌اتاقی</title>
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
    .request-container {
        max-width: 800px;
        margin: 30px auto;
        padding: 0 20px;
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

    .house-info {
        background: rgba(255, 171, 0, 0.1);
        padding: 15px 20px;
        border-radius: 15px;
        border: 1px solid rgba(255, 171, 0, 0.3);
    }

    .house-name {
        font-weight: 700;
        color: var(--gold);
        margin-bottom: 5px;
    }

    .house-location {
        color: var(--text-muted);
        font-size: 14px;
    }

    /* کارت فرم */
    .form-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 20px;
        padding: 30px;
        backdrop-filter: blur(15px);
        margin-bottom: 30px;
    }

    .card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 25px;
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

    /* فرم */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 25px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        color: var(--text-light);
        font-weight: 600;
        font-size: 14px;
    }

    .form-input, .form-select, .form-textarea {
        padding: 12px 15px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        color: var(--text-light);
        font-family: 'Tajawal', sans-serif;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .form-textarea {
        resize: vertical;
        min-height: 120px;
        line-height: 1.6;
    }

    .form-input:focus, .form-select:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--gold);
        background: rgba(255, 255, 255, 0.12);
        box-shadow: 0 0 0 3px rgba(255, 171, 0, 0.1);
    }

    .form-hint {
        color: var(--text-muted);
        font-size: 12px;
        margin-top: 5px;
    }

    /* اطلاعات کاربر */
    .user-info-card {
        background: rgba(139, 92, 246, 0.1);
        border: 1px solid rgba(139, 92, 246, 0.3);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
    }

    .user-info-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
    }

    .user-info-header i {
        color: var(--purple);
        font-size: 18px;
    }

    .user-info-header h4 {
        color: var(--text-light);
        font-size: 16px;
    }

    .user-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .user-detail-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .user-detail-item i {
        color: var(--purple);
        width: 16px;
    }

    .user-detail-label {
        color: var(--text-muted);
        font-size: 13px;
    }

    .user-detail-value {
        color: var(--text-light);
        font-weight: 600;
        font-size: 13px;
    }

    /* دکمه‌ها */
    .form-actions {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        margin-top: 30px;
    }

    .btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 25px;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 15px;
        font-family: 'Tajawal', sans-serif;
        text-decoration: none;
    }

    .btn.primary {
        background: linear-gradient(135deg, var(--gold), var(--gold-dark));
        color: var(--navy-black);
    }

    .btn.secondary {
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-light);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
    }

    /* پیام‌ها */
    .alert {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-weight: 500;
    }

    .alert.success {
        background: rgba(76, 175, 80, 0.2);
        color: var(--success);
        border: 1px solid rgba(76, 175, 80, 0.3);
    }

    .alert.error {
        background: rgba(244, 67, 54, 0.2);
        color: #ff5252;
        border: 1px solid rgba(244, 67, 54, 0.3);
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

    .page-header, .form-card, .user-info-card {
        animation: slideInUp 0.6s ease-out;
    }

    /* ریسپانسیو */
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .header-content {
            flex-direction: column;
            text-align: center;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .user-details {
            grid-template-columns: 1fr;
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
            <a href="house_details.php?id=<?php echo $house_id; ?>" class="nav-btn">
                <i class="fas fa-arrow-right"></i>
                بازگشت به صفحه خانه
            </a>
            <a href="dashboard.php" class="nav-btn">
                <i class="fas fa-tachometer-alt"></i>
                داشبورد
            </a>
        </div>
    </div>
</nav>

<!-- محتوای اصلی -->
<div class="request-container">
    <!-- هدر صفحه -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-text">
                <h1>📝 ارسال درخواست اقامت</h1>
                <p>فرم زیر را تکمیل کنید تا درخواست شما برای صاحب خانه ارسال شود</p>
            </div>
            <div class="house-info">
                <div class="house-name"><?php echo htmlspecialchars($house['title']); ?></div>
                <div class="house-location">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars($house['city'] . '، ' . $house['province']); ?>
                    <span style="margin-right: 15px; color: var(--gold);">
                        <i class="fas fa-<?php echo $house['gender'] === 'خانم' ? 'female' : 'male'; ?>"></i>
                        مخصوص <?php echo htmlspecialchars($house['gender']); ?>ها
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- نمایش پیام‌ها -->
    <?php if($success_message): ?>
    <div class="alert success">
        <i class="fas fa-check-circle"></i>
        <?php echo $success_message; ?>
    </div>
    <?php endif; ?>

    <?php if($error_message): ?>
    <div class="alert error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $error_message; ?>
    </div>
    <?php endif; ?>

    <!-- اطلاعات کاربر -->
    <div class="user-info-card">
        <div class="user-info-header">
            <i class="fas fa-user-circle"></i>
            <h4>اطلاعات شما</h4>
        </div>
        <div class="user-details">
            <div class="user-detail-item">
                <i class="fas fa-user"></i>
                <span class="user-detail-label">نام کامل:</span>
                <span class="user-detail-value"><?php echo htmlspecialchars($current_user['full_name']); ?></span>
            </div>
            <div class="user-detail-item">
                <i class="fas fa-venus-mars"></i>
                <span class="user-detail-label">جنسیت:</span>
                <span class="user-detail-value"><?php echo htmlspecialchars($current_user['gender']); ?></span>
            </div>
            <div class="user-detail-item">
                <i class="fas fa-envelope"></i>
                <span class="user-detail-label">ایمیل:</span>
                <span class="user-detail-value"><?php echo htmlspecialchars($current_user['email']); ?></span>
            </div>
            <?php if($current_user['phone']): ?>
            <div class="user-detail-item">
                <i class="fas fa-phone"></i>
                <span class="user-detail-label">تلفن:</span>
                <span class="user-detail-value"><?php echo htmlspecialchars($current_user['phone']); ?></span>
            </div>
            <?php endif; ?>
            <?php if($current_user['personality_type']): ?>
            <div class="user-detail-item">
                <i class="fas fa-brain"></i>
                <span class="user-detail-label">تیپ شخصیتی:</span>
                <span class="user-detail-value"><?php echo htmlspecialchars($current_user['personality_type']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- فرم ارسال درخواست -->
    <form method="POST" class="form-card">
        <div class="card-header">
            <i class="fas fa-edit"></i>
            <h3>مشخصات درخواست</h3>
        </div>

        <div class="form-grid">
            <!-- تاریخ تقریبی ورود -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-calendar-plus"></i>
                    تاریخ تقریبی ورود
                </label>
                <input type="date" name="move_in_date" class="form-input" required 
                       min="<?php echo date('Y-m-d'); ?>">
                <div class="form-hint">تاریخی که قصد دارید ساکن شوید</div>
            </div>

            <!-- مدت زمان اقامت -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-clock"></i>
                    مدت زمان اقامت
                </label>
                <select name="duration" class="form-select" required>
                    <option value="">انتخاب کنید</option>
                    <option value="کمتر از 1 ماه">کمتر از 1 ماه</option>
                    <option value="1 تا 3 ماه">1 تا 3 ماه</option>
                    <option value="3 تا 6 ماه">3 تا 6 ماه</option>
                    <option value="6 ماه تا 1 سال">6 ماه تا 1 سال</option>
                    <option value="بیش از 1 سال">بیش از 1 سال</option>
                    <option value="نامشخص">نامشخص</option>
                </select>
                <div class="form-hint">مدت زمانی که قصد اقامت دارید</div>
            </div>

            <!-- بودجه پیشنهادی -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-money-bill-wave"></i>
                    بودجه پیشنهادی (تومان)
                </label>
                <input type="number" name="budget" class="form-input" 
                       placeholder="مثلاً: 1500000" 
                       min="0" max="100000000"
                       value="<?php echo $house['price']; ?>">
                <div class="form-hint">بودجه ماهانه شما برای اجاره</div>
            </div>

            <!-- پیام به صاحب خانه -->
            <div class="form-group full-width">
                <label class="form-label">
                    <i class="fas fa-comment-dots"></i>
                    پیام به صاحب خانه
                </label>
                <textarea name="message" class="form-textarea" placeholder="در این قسمت خودتان را معرفی کنید و دلیل علاقه‌مندی به این خانه را بیان نمایید..." required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                <div class="form-hint">
                    این پیام برای صاحب خانه ارسال خواهد شد. اطلاعاتی درباره خود، سبک زندگی و انتظاراتتان بنویسید.
                </div>
            </div>
        </div>

        <!-- دکمه‌های اقدام -->
        <div class="form-actions">
            <a href="house_details.php?id=<?php echo $house_id; ?>" class="btn secondary">
                <i class="fas fa-times"></i>
                انصراف
            </a>
            <input type="hidden" name="house_id" value="<?php echo $house_id; ?>">
            <button type="submit" class="btn primary">
                <i class="fas fa-paper-plane"></i>
                ارسال درخواست
            </button>
        </div>
    </form>
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

// تنظیم حداقل تاریخ برای فیلد تاریخ
document.addEventListener('DOMContentLoaded', function() {
    const moveInDate = document.querySelector('input[name="move_in_date"]');
    const today = new Date().toISOString().split('T')[0];
    moveInDate.min = today;
    
    // پر کردن خودکار مدت زمان اقامت
    const durationSelect = document.querySelector('select[name="duration"]');
    if (!durationSelect.value) {
        durationSelect.value = '3 تا 6 ماه';
    }
    
    // انیمیشن‌ها
    const animatedElements = document.querySelectorAll('.page-header, .form-card, .user-info-card');
    animatedElements.forEach((element, index) => {
        element.style.animationDelay = (index * 0.1) + 's';
    });
});

// اعتبارسنجی فرم
document.querySelector('form').addEventListener('submit', function(e) {
    const message = document.querySelector('textarea[name="message"]').value.trim();
    const moveInDate = document.querySelector('input[name="move_in_date"]').value;
    const duration = document.querySelector('select[name="duration"]').value;
    
    if (!message) {
        e.preventDefault();
        alert('لطفاً پیام خود را وارد کنید');
        return;
    }
    
    if (!moveInDate) {
        e.preventDefault();
        alert('لطفاً تاریخ تقریبی ورود را انتخاب کنید');
        return;
    }
    
    if (!duration) {
        e.preventDefault();
        alert('لطفاً مدت زمان اقامت را انتخاب کنید');
        return;
    }
    
    // نمایش پیام تأیید
    if (!confirm('آیا از ارسال درخواست مطمئن هستید؟')) {
        e.preventDefault();
    }
});
</script>

</body>
</html>