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

$errors = [];
$success = "";

// پردازش فرم ثبت خانه
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $capacity = trim($_POST['capacity'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $amenities = isset($_POST['amenities']) && is_array($_POST['amenities']) ? implode(', ', $_POST['amenities']) : '';
    $rules = trim($_POST['rules'] ?? '');

    // اعتبارسنجی
    if (empty($title)) $errors[] = "عنوان خانه الزامی است";
    if (empty($address)) $errors[] = "آدرس خانه الزامی است";
    if (empty($city)) $errors[] = "شهر الزامی است";
    if (empty($province)) $errors[] = "استان الزامی است";
    if (empty($price) || !is_numeric($price) || $price <= 0) $errors[] = "قیمت معتبر وارد کنید";
    if (empty($capacity) || !is_numeric($capacity) || $capacity <= 0) $errors[] = "ظرفیت معتبر وارد کنید";
    if (empty($gender)) $errors[] = "جنسیت ساکنین را انتخاب کنید";

    // آپلود عکس‌ها
    $uploaded_images = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $images = $_FILES['images'];
        
        foreach ($images['tmp_name'] as $key => $tmp_name) {
            if ($images['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = $images['name'][$key];
                $file_size = $images['size'][$key];
                $file_tmp = $images['tmp_name'][$key];
                $file_type = mime_content_type($file_tmp);
                
                // بررسی نوع فایل
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!in_array($file_type, $allowed_types)) {
                    $errors[] = "فقط فایل‌های تصویری (JPG, PNG, GIF) مجاز هستند";
                    continue;
                }
                
                // بررسی حجم فایل (حداکثر 5MB)
                if ($file_size > 5 * 1024 * 1024) {
                    $errors[] = "حجم هر فایل نباید بیشتر از 5 مگابایت باشد";
                    continue;
                }
                
                // ایجاد پوشه آپلود اگر وجود ندارد
                $upload_dir = __DIR__ . '/../assets/uploads/houses/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // ایجاد نام یکتا برای فایل
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_filename = 'house_' . $user_id . '_' . time() . '_' . $key . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                // آپلود فایل
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $uploaded_images[] = 'assets/uploads/houses/' . $new_filename;
                } else {
                    $errors[] = "خطا در آپلود فایل: " . $file_name;
                }
            }
        }
    }

    if (empty($uploaded_images)) {
        $uploaded_images[] = 'assets/images/roommates.jpg';
    }

    if (empty($errors)) {
        try {
            // بررسی وجود جدول houses
            $tableCheck = $conn->query("SHOW TABLES LIKE 'houses'")->rowCount();
            if ($tableCheck === 0) {
                // اگر جدول houses وجود ندارد، بررسی کنید آیا listings وجود دارد
                $listingsCheck = $conn->query("SHOW TABLES LIKE 'listings'")->rowCount();
                if ($listingsCheck > 0) {
                    $errors[] = "خطا: جدول دیتابیس به 'houses' تغییر نام داده نشده است. لطفاً فایل database/migrate_listings_to_houses.sql را اجرا کنید.";
                } else {
                    $errors[] = "خطا: جدول houses در دیتابیس وجود ندارد. لطفاً فایل database/hamotaghi_complete.sql را اجرا کنید.";
                }
            } else {
                // تغییر مهم: خانه به صورت خودکار تایید میشه (status = 'فعال')
                $insert_sql = "
                    INSERT INTO houses 
                    (user_id, title, description, address, city, province, price, capacity, available_capacity, gender, amenities, rules, images, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'فعال')
                ";
                
                $images_json = json_encode($uploaded_images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->execute([
                    $user_id, $title, $description, $address, $city, $province, 
                    $price, $capacity, $capacity, $gender, $amenities, $rules, $images_json
                ]);
                
                $house_id = $conn->lastInsertId();

                $photoStmt = $conn->prepare("
                    INSERT INTO house_photos (house_id, file_path, is_primary)
                    VALUES (?, ?, ?)
                ");
                foreach ($uploaded_images as $index => $path) {
                    $photoStmt->execute([$house_id, $path, $index === 0 ? 1 : 0]);
                }
                
                // ثبت فعالیت کاربر
                try {
                    $activity_sql = "
                        INSERT INTO user_activities (user_id, activity_type, description) 
                        VALUES (?, 'house_add', ?)
                    ";
                    $activity_stmt = $conn->prepare($activity_sql);
                    $description = "خانه جدید با عنوان '" . $title . "' ثبت شد";
                    $activity_stmt->execute([$user_id, $description]);
                } catch (Exception $e) {
                    // اگر جدول فعالیت‌ها وجود نداشت، خطا نده
                }
                
                // تغییر مهم: پیام موفقیت متفاوت
                $success = "✅ خانه شما با موفقیت ثبت و فعال شد! هم‌اتاقی‌ها می‌تونن الآن خانه شما رو ببینن.";
                
                // پاک کردن فرم
                $_POST = [];
            }
        } catch (PDOException $e) {
            $errors[] = "خطا در ثبت خانه: " . $e->getMessage();
        }
    }
}

$username = htmlspecialchars($user['full_name']);
$usertype = $user['usertype'];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ثبت خانه جدید | هم‌اتاقی</title>
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
.add-house-container {
    max-width: 1000px;
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

/* فرم ثبت خانه */
.add-house-form {
    background: rgba(15, 26, 33, 0.9);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 30px;
    backdrop-filter: blur(15px);
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
    position: relative;
    overflow: hidden;
}

.add-house-form::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--gold), var(--purple), var(--gold));
    animation: shimmer 3s ease-in-out infinite;
}

.form-sections {
    display: grid;
    gap: 30px;
}

.form-section {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    padding: 25px;
    border: 1px solid rgba(255, 255, 255, 0.1);
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
    font-size: 20px;
    color: var(--gold);
}

.section-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-light);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-label {
    color: var(--text-light);
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.required::after {
    content: '*';
    color: var(--danger);
    margin-right: 5px;
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
    resize: vertical;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--gold);
    background: rgba(255, 255, 255, 0.12);
    box-shadow: 0 0 0 3px rgba(255, 171, 0, 0.1);
}

.form-textarea {
    min-height: 100px;
}

/* آپلود عکس */
.upload-container {
    border: 2px dashed rgba(255, 171, 0, 0.3);
    border-radius: 15px;
    padding: 30px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.upload-container:hover {
    border-color: var(--gold);
    background: rgba(255, 171, 0, 0.05);
}

.upload-container.dragover {
    border-color: var(--gold);
    background: rgba(255, 171, 0, 0.1);
}

.upload-icon {
    font-size: 3rem;
    color: var(--gold);
    margin-bottom: 15px;
}

.upload-text h4 {
    color: var(--text-light);
    margin-bottom: 8px;
    font-size: 16px;
}

.upload-text p {
    color: var(--text-muted);
    font-size: 14px;
    margin-bottom: 15px;
}

.upload-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--navy-black);
    padding: 10px 20px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
}

.upload-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 171, 0, 0.3);
}

.file-input {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.preview-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.preview-item {
    position: relative;
    border-radius: 10px;
    overflow: hidden;
    aspect-ratio: 1;
}

.preview-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.remove-btn {
    position: absolute;
    top: 5px;
    left: 5px;
    background: var(--danger);
    color: white;
    border: none;
    border-radius: 50%;
    width: 25px;
    height: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.3s ease;
}

.remove-btn:hover {
    transform: scale(1.1);
}

/* چک‌باکس‌ها */
.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    margin-top: 10px;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox-group input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--gold);
}

.checkbox-group label {
    color: var(--text-light);
    font-size: 14px;
    cursor: pointer;
}

/* دکمه‌های فرم */
.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.submit-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 30px;
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--navy-black);
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 16px;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 171, 0, 0.4);
}

.cancel-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 15px 25px;
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-light);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.cancel-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* آلرت‌ها */
.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    backdrop-filter: blur(10px);
    border: 1px solid;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: rgba(76, 175, 80, 0.15);
    color: var(--success);
    border-color: rgba(76, 175, 80, 0.3);
}

.alert-danger {
    background: rgba(244, 67, 54, 0.15);
    color: #ff5252;
    border-color: rgba(244, 67, 54, 0.3);
}

.alert ul {
    margin: 0;
    padding-right: 20px;
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

.page-header, .add-house-form {
    animation: slideInUp 0.8s ease-out;
}

/* ریسپانسیو */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
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
    
    .header-content {
        flex-direction: column;
        text-align: center;
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
            <a href="my_houses.php" class="nav-btn">
                <i class="fas fa-house-user"></i>
                خانه‌های من
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
<div class="add-house-container">
    <!-- هدر صفحه -->
    <div class="page-header glass-effect">
        <div class="header-content">
            <div class="header-text">
                <h1>🏠 ثبت خانه جدید</h1>
                <p>خانه شما بلافاصله پس از ثبت در سایت نمایش داده می‌شود</p>
            </div>
        </div>
    </div>

    <!-- آلرت‌ها -->
    <?php if($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <ul>
                <?php foreach($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- فرم ثبت خانه -->
    <form method="POST" enctype="multipart/form-data" class="add-house-form glass-effect">
        <div class="form-sections">
            <!-- بخش اطلاعات اصلی -->
            <div class="form-section">
                <div class="section-header">
                    <i class="fas fa-info-circle"></i>
                    <h3>اطلاعات اصلی خانه</h3>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-heading"></i>
                            عنوان خانه
                        </label>
                        <input type="text" name="title" class="form-input" placeholder="مثال: آپارتمان ۷۰ متری در تهرانپارس" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-venus-mars"></i>
                            جنسیت ساکنین
                        </label>
                        <select name="gender" class="form-select" required>
                            <option value="">انتخاب کنید</option>
                            <option value="آقا" <?php echo ($_POST['gender'] ?? '') === 'آقا' ? 'selected' : ''; ?>>آقا</option>
                            <option value="خانم" <?php echo ($_POST['gender'] ?? '') === 'خانم' ? 'selected' : ''; ?>>خانم</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-money-bill-wave"></i>
                            قیمت ماهانه (تومان)
                        </label>
                        <input type="number" name="price" class="form-input" placeholder="مثال: 2500000" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-users"></i>
                            ظرفیت (تعداد نفر)
                        </label>
                        <input type="number" name="capacity" class="form-input" placeholder="مثال: 3" value="<?php echo htmlspecialchars($_POST['capacity'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label class="form-label">
                        <i class="fas fa-align-left"></i>
                        توضیحات خانه
                    </label>
                    <textarea name="description" class="form-textarea" placeholder="در مورد خانه، محیط اطراف، شرایط و ... توضیح دهید"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- بخش موقعیت -->
            <div class="form-section">
                <div class="section-header">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>موقعیت خانه</h3>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-building"></i>
                            استان
                        </label>
                        <select name="province" class="form-select" required>
                            <option value="">انتخاب استان</option>
                            <option value="تهران" <?php echo ($_POST['province'] ?? '') === 'تهران' ? 'selected' : ''; ?>>تهران</option>
                            <option value="اصفهان" <?php echo ($_POST['province'] ?? '') === 'اصفهان' ? 'selected' : ''; ?>>اصفهان</option>
                            <option value="فارس" <?php echo ($_POST['province'] ?? '') === 'فارس' ? 'selected' : ''; ?>>فارس</option>
                            <option value="خراسان رضوی" <?php echo ($_POST['province'] ?? '') === 'خراسان رضوی' ? 'selected' : ''; ?>>خراسان رضوی</option>
                            <option value="آذربایجان شرقی" <?php echo ($_POST['province'] ?? '') === 'آذربایجان شرقی' ? 'selected' : ''; ?>>آذربایجان شرقی</option>
                            <option value="مازندران" <?php echo ($_POST['province'] ?? '') === 'مازندران' ? 'selected' : ''; ?>>مازندران</option>
                            <option value="گیلان" <?php echo ($_POST['province'] ?? '') === 'گیلان' ? 'selected' : ''; ?>>گیلان</option>
                            <option value="البرز" <?php echo ($_POST['province'] ?? '') === 'البرز' ? 'selected' : ''; ?>>البرز</option>
                            <option value="قم" <?php echo ($_POST['province'] ?? '') === 'قم' ? 'selected' : ''; ?>>قم</option>
                            <option value="خوزستان" <?php echo ($_POST['province'] ?? '') === 'خوزستان' ? 'selected' : ''; ?>>خوزستان</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-city"></i>
                            شهر
                        </label>
                        <input type="text" name="city" class="form-input" placeholder="مثال: تهران" value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label class="form-label required">
                        <i class="fas fa-map"></i>
                        آدرس دقیق
                    </label>
                    <textarea name="address" class="form-textarea" placeholder="آدرس کامل و دقیق خانه" required><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- بخش امکانات -->
            <div class="form-section">
                <div class="section-header">
                    <i class="fas fa-concierge-bell"></i>
                    <h3>امکانات خانه</h3>
                </div>
                <div class="checkbox-grid">
                    <div class="checkbox-group">
                        <input type="checkbox" name="amenities[]" value="اینترنت" id="internet" <?php echo (isset($_POST['amenities']) && in_array('اینترنت', $_POST['amenities'])) ? 'checked' : ''; ?>>
                        <label for="internet">اینترنت پرسرعت</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="amenities[]" value="تلفن" id="phone" <?php echo (isset($_POST['amenities']) && in_array('تلفن', $_POST['amenities'])) ? 'checked' : ''; ?>>
                        <label for="phone">تلفن</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="amenities[]" value="مبلمان" id="furniture" <?php echo (isset($_POST['amenities']) && in_array('مبلمان', $_POST['amenities'])) ? 'checked' : ''; ?>>
                        <label for="furniture">مبلمان کامل</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="amenities[]" value="آشپزخانه" id="kitchen" <?php echo (isset($_POST['amenities']) && in_array('آشپزخانه', $_POST['amenities'])) ? 'checked' : ''; ?>>
                        <label for="kitchen">آشپزخانه مجهز</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="amenities[]" value="لباسشویی" id="washing" <?php echo (isset($_POST['amenities']) && in_array('لباسشویی', $_POST['amenities'])) ? 'checked' : ''; ?>>
                        <label for="washing">لباسشویی</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="amenities[]" value="پارکینگ" id="parking" <?php echo (isset($_POST['amenities']) && in_array('پارکینگ', $_POST['amenities'])) ? 'checked' : ''; ?>>
                        <label for="parking">پارکینگ</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="amenities[]" value="آسانسور" id="elevator" <?php echo (isset($_POST['amenities']) && in_array('آسانسور', $_POST['amenities'])) ? 'checked' : ''; ?>>
                        <label for="elevator">آسانسور</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="amenities[]" value="حیاط" id="yard" <?php echo (isset($_POST['amenities']) && in_array('حیاط', $_POST['amenities'])) ? 'checked' : ''; ?>>
                        <label for="yard">حیاط</label>
                    </div>
                </div>
            </div>

            <!-- بخش قوانین -->
            <div class="form-section">
                <div class="section-header">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>قوانین خانه</h3>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-rules"></i>
                        قوانین و شرایط
                    </label>
                    <textarea name="rules" class="form-textarea" placeholder="قوانین خانه، ساعت سکوت، شرایط مهمان و ..."><?php echo htmlspecialchars($_POST['rules'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- بخش عکس‌ها -->
            <div class="form-section">
                <div class="section-header">
                    <i class="fas fa-images"></i>
                    <h3>عکس‌های خانه</h3>
                </div>
                <div class="upload-container" id="uploadContainer">
                    <div class="upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="upload-text">
                        <h4>عکس‌های خانه را آپلود کنید</h4>
                        <p>می‌توانید چند عکس انتخاب کنید (حداکثر ۵MB برای هر عکس)</p>
                    </div>
                    <button type="button" class="upload-btn">
                        <i class="fas fa-folder-open"></i>
                        انتخاب عکس‌ها
                    </button>
                    <input type="file" name="images[]" class="file-input" id="fileInput" multiple accept="image/*">
                </div>
                <div class="preview-container" id="previewContainer"></div>
            </div>
        </div>

        <!-- دکمه‌های فرم -->
        <div class="form-actions">
            <a href="dashboard.php" class="cancel-btn">
                <i class="fas fa-times"></i>
                انصراف
            </a>
            <button type="submit" class="submit-btn">
                <i class="fas fa-home"></i>
                ثبت و فعال‌سازی خانه
            </button>
        </div>
    </form>
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

// مدیریت آپلود عکس‌ها
const fileInput = document.getElementById('fileInput');
const uploadContainer = document.getElementById('uploadContainer');
const previewContainer = document.getElementById('previewContainer');

// کلیک روی container
uploadContainer.addEventListener('click', () => {
    fileInput.click();
});

// drag and drop
uploadContainer.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadContainer.classList.add('dragover');
});

uploadContainer.addEventListener('dragleave', () => {
    uploadContainer.classList.remove('dragover');
});

uploadContainer.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadContainer.classList.remove('dragover');
    fileInput.files = e.dataTransfer.files;
    handleFiles(fileInput.files);
});

// تغییر فایل‌ها
fileInput.addEventListener('change', () => {
    handleFiles(fileInput.files);
});

function handleFiles(files) {
    previewContainer.innerHTML = '';
    
    for (let file of files) {
        if (!file.type.startsWith('image/')) continue;
        
        const reader = new FileReader();
        
        reader.onload = (e) => {
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';
            
            previewItem.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <button type="button" class="remove-btn">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            previewContainer.appendChild(previewItem);
            
            // حذف عکس
            previewItem.querySelector('.remove-btn').addEventListener('click', () => {
                previewItem.remove();
                updateFileInput();
            });
        };
        
        reader.readAsDataURL(file);
    }
}

function updateFileInput() {
    // اینجا می‌تونی برای حذف فایل از input پیاده‌سازی کنی
    // فعلاً فقط از preview حذف می‌کنه
}

// انیمیشن فرم
document.addEventListener('DOMContentLoaded', function() {
    const formSections = document.querySelectorAll('.form-section');
    formSections.forEach((section, index) => {
        section.style.animationDelay = (index * 0.2) + 's';
    });
});
</script>

</body>
</html>