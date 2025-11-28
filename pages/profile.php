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
$sql = "SELECT full_name, usertype, gender, personality_type, city, province, email, phone, national_code, bio, avatar FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: logout.php");
    exit();
}

// پردازش فرم ویرایش
$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // اگر فرم ویرایش اطلاعات ارسال شده
    if (isset($_POST['full_name'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        // اعتبارسنجی
        if (empty($full_name)) $errors[] = "نام و نام خانوادگی الزامی است";
        if (empty($city)) $errors[] = "شهر الزامی است";
        if (empty($province)) $errors[] = "استان الزامی است";
        if (empty($phone) || !preg_match('/^09[0-9]{9}$/', $phone)) $errors[] = "شماره موبایل معتبر وارد کنید";
        
        if (empty($errors)) {
            $update_sql = "UPDATE users SET full_name = ?, city = ?, province = ?, phone = ?, bio = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            if ($update_stmt->execute([$full_name, $city, $province, $phone, $bio, $user_id])) {
                $success = "پروفایل با موفقیت به‌روزرسانی شد!";
                // بروزرسانی اطلاعات کاربر
                $user['full_name'] = $full_name;
                $user['city'] = $city;
                $user['province'] = $province;
                $user['phone'] = $phone;
                $user['bio'] = $bio;
            } else {
                $errors[] = "خطا در به‌روزرسانی پروفایل";
            }
        }
    }
}

// پردازش آپلود عکس جداگانه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
    $avatar = $_FILES['avatar'];
    
    // بررسی نوع فایل
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $file_type = mime_content_type($avatar['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        $errors[] = "فقط فایل‌های تصویری (JPG, PNG, GIF) مجاز هستند";
    }
    
    // بررسی حجم فایل (حداکثر 2MB)
    if ($avatar['size'] > 2 * 1024 * 1024) {
        $errors[] = "حجم فایل نباید بیشتر از 2 مگابایت باشد";
    }
    
    if (empty($errors)) {
        // ایجاد پوشه آپلود اگر وجود ندارد
        $upload_dir = __DIR__ . '/../assets/uploads/avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // ایجاد نام یکتا برای فایل
        $file_extension = pathinfo($avatar['name'], PATHINFO_EXTENSION);
        $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        // آپلود فایل
        if (move_uploaded_file($avatar['tmp_name'], $upload_path)) {
            // آپدیت مسیر عکس در دیتابیس
            $avatar_path = 'assets/uploads/avatars/' . $new_filename;
            $update_avatar_sql = "UPDATE users SET avatar = ? WHERE id = ?";
            $update_avatar_stmt = $conn->prepare($update_avatar_sql);
            
            if ($update_avatar_stmt->execute([$avatar_path, $user_id])) {
                // حذف عکس قبلی اگر وجود دارد
                if ($user['avatar'] && file_exists(__DIR__ . '/../' . $user['avatar']) && $user['avatar'] !== 'assets/uploads/avatars/default.png') {
                    unlink(__DIR__ . '/../' . $user['avatar']);
                }
                $user['avatar'] = $avatar_path;
                if (empty($success)) {
                    $success = "عکس پروفایل با موفقیت آپلود شد!";
                } else {
                    $success .= " و عکس پروفایل آپلود شد!";
                }
            } else {
                $errors[] = "خطا در ذخیره اطلاعات عکس";
            }
        } else {
            $errors[] = "خطا در آپلود فایل";
        }
    }
}

$username = htmlspecialchars($user['full_name']);
$usertype = $user['usertype'];
$gender = $user['gender'];
$personality = $user['personality_type'];
$city = htmlspecialchars($user['city']);
$province = htmlspecialchars($user['province']);
$email = htmlspecialchars($user['email']);
$phone = htmlspecialchars($user['phone']);
$national_code = htmlspecialchars($user['national_code']);
$bio = htmlspecialchars($user['bio']);
$avatar = $user['avatar'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پروفایل | هم‌اتاقی</title>
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
    --card-bg: rgba(15, 26, 33, 0.9);
    --card-border: rgba(255, 171, 0, 0.25);
    --success: #4caf50;
    --warning: #ff9800;
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
.profile-container {
    max-width: 1000px;
    margin: 40px auto;
    padding: 0 20px;
}

/* هدر پروفایل */
.profile-header {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    backdrop-filter: blur(15px);
    position: relative;
    overflow: visible;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
}

.profile-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--gold), #8b5cf6, var(--gold));
    animation: shimmer 3s ease-in-out infinite;
}

.profile-info {
    display: flex;
    align-items: center;
    gap: 25px;
}

.avatar-container {
    position: relative;
    z-index: 1002;
}

.avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: var(--navy-black);
    border: 4px solid var(--gold);
    box-shadow: 0 8px 25px rgba(255, 171, 0, 0.3);
    transition: all 0.3s ease;
    overflow: hidden;
}

.avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 12px 35px rgba(255, 171, 0, 0.5);
}

.avatar-upload {
    position: absolute;
    bottom: 5px;
    right: 5px;
    background: var(--gold);
    color: var(--navy-black);
    border: none;
    border-radius: 50%;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    z-index: 1003;
}

.avatar-upload:hover {
    background: var(--gold-dark);
    transform: scale(1.1);
}

.avatar-upload-form {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: var(--card-bg);
    border: 2px solid var(--gold);
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7);
    z-index: 10000;
    min-width: 320px;
    backdrop-filter: blur(20px);
}

.avatar-upload-form.active {
    display: block;
    animation: popIn 0.4s ease;
}

.upload-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    z-index: 9999;
}

.upload-overlay.active {
    display: block;
}

.upload-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.upload-header h4 {
    color: var(--text-light);
    font-size: 18px;
    font-weight: 700;
}

.close-upload {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 20px;
    transition: color 0.3s ease;
    padding: 5px;
}

.close-upload:hover {
    color: var(--gold);
    transform: scale(1.1);
}

.file-input-container {
    position: relative;
    margin-bottom: 20px;
}

.file-input {
    width: 100%;
    padding: 12px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 10px;
    color: var(--text-light);
    font-family: 'Tajawal', sans-serif;
    font-size: 14px;
    transition: all 0.3s ease;
}

.file-input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(255, 171, 0, 0.2);
}

.file-input::file-selector-button {
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--navy-black);
    border: none;
    padding: 8px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    margin-left: 10px;
    transition: all 0.3s ease;
}

.file-input::file-selector-button:hover {
    background: linear-gradient(135deg, var(--gold-dark), var(--gold));
    transform: translateY(-1px);
}

.upload-btn {
    width: 100%;
    padding: 12px;
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--navy-black);
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 15px;
}

.upload-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 171, 0, 0.4);
}

.upload-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.upload-info {
    margin-top: 15px;
    padding: 10px;
    background: rgba(255, 171, 0, 0.1);
    border-radius: 8px;
    border: 1px solid rgba(255, 171, 0, 0.2);
}

.upload-info p {
    color: var(--gold-light);
    font-size: 12px;
    text-align: center;
    margin: 0;
}

.user-details {
    flex: 1;
}

.user-name {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.user-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 171, 0, 0.2);
    color: var(--gold);
    padding: 6px 15px;
    border-radius: 20px;
    font-weight: 600;
    margin: 8px 0;
    border: 1px solid rgba(255, 171, 0, 0.3);
    font-size: 14px;
    backdrop-filter: blur(10px);
}

.contact-info {
    display: flex;
    gap: 20px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
    font-size: 14px;
    background: rgba(255, 255, 255, 0.08);
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

/* محتوای پروفایل */
.profile-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 30px;
}

/* کارت اطلاعات شخصی */
.info-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 25px;
    backdrop-filter: blur(15px);
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
    position: relative;
    overflow: hidden;
}

.info-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--gold), transparent);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.card-header i {
    font-size: 22px;
    color: var(--gold);
}

.card-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-light);
}

.info-grid {
    display: grid;
    gap: 15px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    color: var(--text-muted);
    font-weight: 500;
    font-size: 14px;
}

.info-value {
    font-weight: 600;
    color: var(--text-light);
    font-size: 14px;
}

.personality-badge {
    background: linear-gradient(135deg, var(--gold), #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 700;
    font-size: 16px;
}

/* کارت ویرایش پروفایل */
.edit-form {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 25px;
    backdrop-filter: blur(15px);
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
    position: relative;
    overflow: hidden;
}

.edit-form::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #8b5cf6, var(--gold));
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    color: var(--text-light);
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    color: var(--text-light);
    font-family: 'Tajawal', sans-serif;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--gold);
    background: rgba(255, 255, 255, 0.12);
    box-shadow: 0 0 0 3px rgba(255, 171, 0, 0.1);
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

.submit-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--navy-black);
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    justify-content: center;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 171, 0, 0.4);
}

/* آلرت‌ها */
.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    backdrop-filter: blur(10px);
    border: 1px solid;
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

@keyframes popIn {
    from {
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.8);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
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

.profile-header, .info-card, .edit-form {
    animation: slideInUp 0.8s ease-out;
}

/* ریسپانسیو */
@media (max-width: 768px) {
    .profile-content {
        grid-template-columns: 1fr;
    }
    
    .profile-info {
        flex-direction: column;
        text-align: center;
    }
    
    .contact-info {
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
    
    .nav-btn {
        padding: 6px 12px;
        font-size: 13px;
    }
    
    .avatar-upload-form {
        min-width: 90%;
        margin: 0 5%;
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

<!-- بک‌گراند پویا -->
<div id="particles-js"></div>
<div class="grid-lines"></div>
<div class="light-spot"></div>
<div class="light-spot"></div>
<div class="light-spot"></div>

<!-- ناوبری اصلی -->
<nav class="main-nav glass-effect">
    <div class="nav-container">
        <a href="dashboard.php" class="brand">
            <i class="fas fa-home-heart"></i>
            هم‌اتاقی
        </a>
        
        <div class="nav-actions">
            <?php if ($usertype === 'صاحب‌خانه'): ?>
                <a href="add_house.php" class="nav-btn">
                    <i class="fas fa-plus"></i>
                    ثبت خانه
                </a>
                <a href="my_houses.php" class="nav-btn">
                    <i class="fas fa-house-user"></i>
                    خانه‌های من
                </a>
            <?php else: ?>
                <a href="available_houses.php" class="nav-btn">
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
<div class="profile-container">
    <!-- هدر پروفایل -->
    <div class="profile-header glass-effect hover-lift">
        <div class="profile-info">
            <div class="avatar-container">
                <div class="avatar">
                    <?php if ($avatar && file_exists(__DIR__ . '/../' . $avatar)): ?>
                        <img src="../<?php echo $avatar; ?>" alt="عکس پروفایل <?php echo $username; ?>">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <button class="avatar-upload" onclick="openUploadForm()">
                    <i class="fas fa-camera"></i>
                </button>
            </div>
            <div class="user-details">
                <h1 class="user-name"><?php echo $username; ?></h1>
                <div class="user-badge">
                    <i class="fas fa-<?php echo $gender === 'خانم' ? 'female' : 'male'; ?>"></i>
                    <?php echo $gender . ' | ' . $usertype; ?>
                </div>
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo $city . '، ' . $province; ?></span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo $email; ?></span>
                    </div>
                    <?php if ($phone): ?>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <span><?php echo $phone; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- آلرت‌ها -->
    <?php if($success): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- محتوای پروفایل -->
    <div class="profile-content">
        <!-- کارت اطلاعات شخصی -->
        <div class="info-card glass-effect hover-lift">
            <div class="card-header">
                <i class="fas fa-id-card"></i>
                <h3>اطلاعات شخصی</h3>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">نام کامل</span>
                    <span class="info-value"><?php echo $username; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">جنسیت</span>
                    <span class="info-value"><?php echo $gender; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">نوع حساب</span>
                    <span class="info-value"><?php echo $usertype; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">شهر</span>
                    <span class="info-value"><?php echo $city; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">استان</span>
                    <span class="info-value"><?php echo $province; ?></span>
                </div>
                <?php if ($national_code): ?>
                <div class="info-item">
                    <span class="info-label">کد ملی</span>
                    <span class="info-value"><?php echo $national_code; ?></span>
                </div>
                <?php endif; ?>
                <?php if ($personality): ?>
                <div class="info-item">
                    <span class="info-label">تیپ شخصیتی</span>
                    <span class="info-value personality-badge"><?php echo $personality; ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- کارت ویرایش پروفایل -->
        <form method="POST" class="edit-form glass-effect hover-lift">
            <div class="card-header">
                <i class="fas fa-edit"></i>
                <h3>ویرایش پروفایل</h3>
            </div>
            <div class="form-group">
                <label class="form-label">نام و نام خانوادگی</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo $username; ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">شماره موبایل</label>
                <input type="tel" name="phone" class="form-control" value="<?php echo $phone; ?>" placeholder="09xxxxxxxxx" required>
            </div>
            <div class="form-group">
                <label class="form-label">استان</label>
                <select name="province" class="form-control" required>
                    <option value="">انتخاب استان</option>
                    <option value="تهران" <?php echo $province === 'تهران' ? 'selected' : ''; ?>>تهران</option>
                    <option value="اصفهان" <?php echo $province === 'اصفهان' ? 'selected' : ''; ?>>اصفهان</option>
                    <option value="فارس" <?php echo $province === 'فارس' ? 'selected' : ''; ?>>فارس</option>
                    <option value="خراسان رضوی" <?php echo $province === 'خراسان رضوی' ? 'selected' : ''; ?>>خراسان رضوی</option>
                    <option value="آذربایجان شرقی" <?php echo $province === 'آذربایجان شرقی' ? 'selected' : ''; ?>>آذربایجان شرقی</option>
                    <option value="مازندران" <?php echo $province === 'مازندران' ? 'selected' : ''; ?>>مازندران</option>
                    <option value="گیلان" <?php echo $province === 'گیلان' ? 'selected' : ''; ?>>گیلان</option>
                    <option value="البرز" <?php echo $province === 'البرز' ? 'selected' : ''; ?>>البرز</option>
                    <option value="قم" <?php echo $province === 'قم' ? 'selected' : ''; ?>>قم</option>
                    <option value="خوزستان" <?php echo $province === 'خوزستان' ? 'selected' : ''; ?>>خوزستان</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">شهر</label>
                <input type="text" name="city" class="form-control" value="<?php echo $city; ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">بیوگرافی</label>
                <textarea name="bio" class="form-control" placeholder="درباره خودتان بنویسید..."><?php echo $bio; ?></textarea>
            </div>
            <button type="submit" class="submit-btn">
                <i class="fas fa-save"></i>
                ذخیره تغییرات
            </button>
        </form>
    </div>
</div>

<!-- فرم آپلود عکس -->
<div class="upload-overlay" id="uploadOverlay"></div>
<div class="avatar-upload-form" id="avatarUploadForm">
    <div class="upload-header">
        <h4>تغییر عکس پروفایل</h4>
        <button class="close-upload" onclick="closeUploadForm()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <form method="POST" enctype="multipart/form-data" id="avatarForm">
        <div class="file-input-container">
            <input type="file" name="avatar" class="file-input" accept="image/jpeg,image/jpg,image/png,image/gif" required>
        </div>
        <button type="submit" class="upload-btn" id="uploadBtn">
            <i class="fas fa-upload"></i>
            آپلود عکس
        </button>
    </form>
    <div class="upload-info">
        <p>حداکثر حجم: 2MB | فرمت‌های مجاز: JPG, PNG, GIF</p>
    </div>
</div>

<script>
// توابع مدیریت فرم آپلود
function openUploadForm() {
    document.getElementById('avatarUploadForm').classList.add('active');
    document.getElementById('uploadOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeUploadForm() {
    document.getElementById('avatarUploadForm').classList.remove('active');
    document.getElementById('uploadOverlay').classList.remove('active');
    document.body.style.overflow = 'auto';
    // ریست کردن فرم
    document.getElementById('avatarForm').reset();
}

// بستن فرم با کلیک روی overlay
document.getElementById('uploadOverlay').addEventListener('click', closeUploadForm);

// جلوگیری از بسته شدن فرم با کلیک روی خود فرم
document.getElementById('avatarUploadForm').addEventListener('click', function(e) {
    e.stopPropagation();
});

// مدیریت ارسال فرم آپلود
document.getElementById('avatarForm').addEventListener('submit', function(e) {
    const uploadBtn = document.getElementById('uploadBtn');
    const fileInput = this.querySelector('input[type="file"]');
    
    if (!fileInput.files.length) {
        e.preventDefault();
        alert('لطفاً یک فایل انتخاب کنید');
        return;
    }
    
    // نمایش وضعیت آپلود
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال آپلود...';
    uploadBtn.disabled = true;
});

// پارتیکل‌های بک‌گراند
document.addEventListener('DOMContentLoaded', function() {
    if (typeof particlesJS !== 'undefined') {
        particlesJS('particles-js', {
            particles: {
                number: { value: 80, density: { enable: true, value_area: 800 } },
                color: { value: "#ffab00" },
                shape: { type: "circle" },
                opacity: { value: 0.5, random: true },
                size: { value: 3, random: true },
                line_linked: {
                    enable: true,
                    distance: 150,
                    color: "#ffab00",
                    opacity: 0.2,
                    width: 1
                },
                move: {
                    enable: true,
                    speed: 2,
                    direction: "none",
                    random: true,
                    straight: false,
                    out_mode: "out",
                    bounce: false
                }
            },
            interactivity: {
                detect_on: "canvas",
                events: {
                    onhover: { enable: true, mode: "repulse" },
                    onclick: { enable: true, mode: "push" },
                    resize: true
                }
            },
            retina_detect: true
        });
    }
});
</script>

<script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
</body>
</html>