<?php
session_start();
include __DIR__ . '/../includes/config.php';

// بررسی ورود کاربر
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// بررسی اینکه کاربر قبلاً تست داده یا نه
$sql = "SELECT personality_type FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && $user['personality_type']) {
    header("Location: dashboard.php");
    exit();
}

// پردازش فرم تست
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = $_POST['answers'] ?? [];
    
    if (empty($answers) || !is_array($answers)) {
        $errors[] = "لطفاً به تمام سوالات پاسخ دهید.";
    } else {
        // محاسبه نتیجه MBTI
        $result = calculateMBTI($answers);
        
        // ذخیره نتیجه در دیتابیس
        try {
            $update_sql = "UPDATE users SET personality_type = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->execute([$result, $user_id]);
            
            $success = "نتیجه تست شخصیت شما ثبت شد: " . $result;
        } catch (PDOException $e) {
            $errors[] = "خطا در ذخیره نتیجه: " . $e->getMessage();
        }
    }
    
    header("Location: dashboard.php?test_completed=true");
    exit();
}

// تابع محاسبه MBTI
function calculateMBTI($answers) {
    $dimensions = [
        'EI' => 0, // برون‌گرایی - درون‌گرایی
        'SN' => 0, // حسی - شهودی
        'TF' => 0, // فکری - احساسی
        'JP' => 0  // قضاوتی - ادراکی
    ];
    
    // وزن‌دهی جواب‌ها
    foreach ($answers as $question => $answer) {
        $dimension = substr($question, 0, 2);
        $value = ($answer == 'A') ? 1 : -1;
        $dimensions[$dimension] += $value;
    }
    
    // تعیین تیپ شخصیتی
    $result = '';
    $result .= ($dimensions['EI'] >= 0) ? 'E' : 'I';
    $result .= ($dimensions['SN'] >= 0) ? 'S' : 'N';
    $result .= ($dimensions['TF'] >= 0) ? 'T' : 'F';
    $result .= ($dimensions['JP'] >= 0) ? 'J' : 'P';
    
    return $result;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تست شخصیت MBTI | هم‌اتاقی</title>
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
        /* گرادیانت اصلی تاریک */
        linear-gradient(135deg, var(--navy-black) 0%, var(--navy-darker) 50%, var(--navy-dark) 100%),
        /* افکت نویز داینامیک */
        url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.08'/%3E%3C/svg%3E"),
        /* گرادیانت‌های رنگی متحرک */
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

/* محتوای اصلی */
.test-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
}

.test-header {
    text-align: center;
    margin-bottom: 40px;
}

.test-header h1 {
    font-size: 32px;
    margin-bottom: 15px;
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.test-header p {
    color: var(--text-muted);
    font-size: 16px;
    max-width: 600px;
    margin: 0 auto;
}

/* کارت تست */
.test-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 20px;
    padding: 30px;
    backdrop-filter: blur(15px);
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.4);
    position: relative;
    overflow: hidden;
}

.test-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--gold), #8b5cf6, var(--gold));
    animation: shimmer 3s ease-in-out infinite;
}

/* نوار پیشرفت */
.progress-container {
    margin-bottom: 30px;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.progress-text {
    color: var(--text-muted);
    font-size: 14px;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--gold), var(--gold-dark));
    border-radius: 10px;
    transition: width 0.5s ease;
    width: 0%;
}

/* سوالات */
.question-container {
    display: none;
}

.question-container.active {
    display: block;
    animation: slideIn 0.5s ease;
}

.question-number {
    color: var(--gold);
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
}

.question-text {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 25px;
    line-height: 1.5;
}

/* گزینه‌ها */
.options-container {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.option-label {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.option-label::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 171, 0, 0.1), transparent);
    transition: left 0.5s ease;
}

.option-label:hover::before {
    left: 100%;
}

.option-label:hover {
    border-color: var(--gold);
    transform: translateX(-5px);
}

.option-input {
    display: none;
}

.option-input:checked + .option-label {
    background: rgba(255, 171, 0, 0.1);
    border-color: var(--gold);
    box-shadow: 0 4px 15px rgba(255, 171, 0, 0.2);
}

.option-letter {
    width: 40px;
    height: 40px;
    border: 2px solid var(--gold);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: var(--gold);
    transition: all 0.3s ease;
}

.option-input:checked + .option-label .option-letter {
    background: var(--gold);
    color: var(--navy-black);
}

.option-text {
    flex: 1;
    font-size: 16px;
    line-height: 1.5;
}

/* دکمه‌های ناوبری */
.navigation-buttons {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    gap: 15px;
}

.nav-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 25px;
    background: rgba(255, 171, 0, 0.1);
    color: var(--gold-light);
    border: 1px solid rgba(255, 171, 0, 0.2);
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.nav-btn:hover {
    background: rgba(255, 171, 0, 0.2);
    transform: translateY(-2px);
}

.nav-btn.primary {
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--navy-black);
    border: 1px solid var(--gold);
}

.nav-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.nav-btn:disabled:hover {
    transform: none;
    background: rgba(255, 171, 0, 0.1);
}

/* انیمیشن‌ها */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
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

/* ریسپانسیو */
@media (max-width: 768px) {
    .test-container {
        margin: 20px auto;
        padding: 0 15px;
    }
    
    .test-card {
        padding: 20px;
    }
    
    .question-text {
        font-size: 18px;
    }
    
    .option-label {
        padding: 15px;
    }
    
    .option-text {
        font-size: 14px;
    }
    
    .navigation-buttons {
        flex-direction: column;
    }
    
    .nav-btn {
        justify-content: center;
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

<!-- ناوبری -->
<nav class="main-nav">
    <div class="nav-container">
        <a href="../index.php" class="brand">
            <i class="fas fa-home-heart"></i>
            هم‌اتاقی
        </a>
        
        <div class="nav-actions">
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
<div class="test-container">
    <div class="test-header">
        <h1>تست شخصیت MBTI</h1>
        <p>با پاسخ به ۱۲ سوال ساده، تیپ شخصیتی خودت رو کشف کن و هم‌اتاقی‌های سازگارتر پیدا کن</p>
    </div>

    <form id="personalityTestForm" method="POST">
        <div class="test-card">
            <!-- نوار پیشرفت -->
            <div class="progress-container">
                <div class="progress-header">
                    <span class="progress-text">پیشرفت تست</span>
                    <span class="progress-text"><span id="currentQuestion">1</span> از 12</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
            </div>

            <!-- سوالات -->
            <div class="questions-container">
                <!-- سوال 1 -->
                <div class="question-container active" data-question="1">
                    <div class="question-number">سوال 1 از 12</div>
                    <div class="question-text">در مهمانی‌ها معمولاً:</div>
                    <div class="options-container">
                        <input type="radio" name="answers[EI1]" value="A" id="q1a" class="option-input">
                        <label for="q1a" class="option-label">
                            <span class="option-letter">A</span>
                            <span class="option-text">با افراد زیادی صحبت می‌کنم و انرژی می‌گیرم</span>
                        </label>
                        
                        <input type="radio" name="answers[EI1]" value="B" id="q1b" class="option-input">
                        <label for="q1b" class="option-label">
                            <span class="option-letter">B</span>
                            <span class="option-text">با چند نفر آشنا گفتگو می‌کنم و ترجیح می‌دهم جمع کوچک‌تر باشد</span>
                        </label>
                    </div>
                </div>

                <!-- سوال 2 -->
                <div class="question-container" data-question="2">
                    <div class="question-number">سوال 2 از 12</div>
                    <div class="question-text">وقتی پروژه جدیدی شروع می‌کنم:</div>
                    <div class="options-container">
                        <input type="radio" name="answers[SN1]" value="A" id="q2a" class="option-input">
                        <label for="q2a" class="option-label">
                            <span class="option-letter">A</span>
                            <span class="option-text">جزئیات و واقعیت‌های موجود را دقیق بررسی می‌کنم</span>
                        </label>
                        
                        <input type="radio" name="answers[SN1]" value="B" id="q2b" class="option-input">
                        <label for="q2b" class="option-label">
                            <span class="option-letter">B</span>
                            <span class="option-text">به احتمالات و ایده‌های جدید فکر می‌کنم</span>
                        </label>
                    </div>
                </div>

                <!-- سوال 3 -->
                <div class="question-container" data-question="3">
                    <div class="question-number">سوال 3 از 12</div>
                    <div class="question-text">در تصمیم‌گیری‌های مهم:</div>
                    <div class="options-container">
                        <input type="radio" name="answers[TF1]" value="A" id="q3a" class="option-input">
                        <label for="q3a" class="option-label">
                            <span class="option-letter">A</span>
                            <span class="option-text">منطق و عدالت برایم اولویت دارد</span>
                        </label>
                        
                        <input type="radio" name="answers[TF1]" value="B" id="q3b" class="option-input">
                        <label for="q3b" class="option-label">
                            <span class="option-letter">B</span>
                            <span class="option-text">به احساسات و ارزش‌های انسانی توجه می‌کنم</span>
                        </label>
                    </div>
                </div>

                <!-- سوال 4 -->
                <div class="question-container" data-question="4">
                    <div class="question-number">سوال 4 از 12</div>
                    <div class="question-text">برای برنامه‌ریزی زندگی:</div>
                    <div class="options-container">
                        <input type="radio" name="answers[JP1]" value="A" id="q4a" class="option-input">
                        <label for="q4a" class="option-label">
                            <span class="option-letter">A</span>
                            <span class="option-text">برنامه مشخص و ساختاریافته را ترجیح می‌دهم</span>
                        </label>
                        
                        <input type="radio" name="answers[JP1]" value="B" id="q4b" class="option-input">
                        <label for="q4b" class="option-label">
                            <span class="option-letter">B</span>
                            <span class="option-text">انعطاف‌پذیری و سازگاری با شرایط را می‌پسندم</span>
                        </label>
                    </div>
                </div>

                <!-- سوال 5 -->
                <div class="question-container" data-question="5">
                    <div class="question-number">سوال 5 از 12</div>
                    <div class="question-text">در محیط کار ترجیح می‌دهم:</div>
                    <div class="options-container">
                        <input type="radio" name="answers[EI2]" value="A" id="q5a" class="option-input">
                        <label for="q5a" class="option-label">
                            <span class="option-letter">A</span>
                            <span class="option-text">با تیم و همکاران تعامل داشته باشم</span>
                        </label>
                        
                        <input type="radio" name="answers[EI2]" value="B" id="q5b" class="option-input">
                        <label for="q5b" class="option-label">
                            <span class="option-letter">B</span>
                            <span class="option-text">به صورت مستقل و تمرکز کامل کار کنم</span>
                        </label>
                    </div>
                </div>

                <!-- سوال 6 -->
                <div class="question-container" data-question="6">
                    <div class="question-number">سوال 6 از 12</div>
                    <div class="question-text">وقتی کتاب می‌خوانم:</div>
                    <div class="options-container">
                        <input type="radio" name="answers[SN2]" value="A" id="q6a" class="option-input">
                        <label for="q6a" class="option-label">
                            <span class="option-letter">A</span>
                            <span class="option-text">به حقایق و اطلاعات دقیق توجه می‌کنم</span>
                        </label>
                        
                        <input type="radio" name="answers[SN2]" value="B" id="q6b" class="option-input">
                        <label for="q6b" class="option-label">
                            <span class="option-letter">B</span>
                            <span class="option-text">به نمادها و معانی پنهان فکر می‌کنم</span>
                        </label>
                    </div>
                </div>

                <!-- سوال 7 -->
                <div class="question-container" data-question="7">
                    <div class="question-number">سوال 7 از 12</div>
                    <div class="question-text">در حل اختلاف با دیگران:</div>
                    <div class="options-container">
                        <input type="radio" name="answers[TF2]" value="A" id="q7a" class="option-input">
                        <label for="q7a" class="option-label">
                            <span class="option-letter">A</span>
                            <span class="option-text">حقایق و منطق را مبنای حل مسئله قرار می‌دهم</span>
                        </label>
                        
                        <input type="radio" name="answers[TF2]" value="B" id="q7b" class="option-input">
                        <label for="q7b" class="option-label">
                            <span class="option-letter">B</span>
                            <span class="option-text">به احساسات و حفظ روابط توجه می‌کنم</span>
                        </label>
                    </div>
                </div>

                <!-- سوال 8 -->
                <div class="question-container" data-question="8">
                    <div class="question-number">سوال 8 از 12</div>
                    <div class="question-text">در مدیریت زمان:</div>
                    <div class="options-container">
                        <input type="radio" name="answers[JP2]" value="A" id="q8a" class="option-input">
                        <label for="q8a" class="option-label">
                            <span class="option-letter">A</span>
                            <span class="option-text">دقیق برنامه‌ریزی می‌کنم و به برنامه پایبندم</span>
                        </label>
                        
                        <input type="radio" name="answers[JP2]" value="B" id="q8b" class="option-input">
                        <label for="q8b" class="option-label">
                            <span class="option-letter">B</span>
                            <span class="option-text">انعطاف‌پذیر هستم و بسته به شرایط عمل می‌کنم</span>
                        </label>
                    </div>
                </div>

                <!-- سوال 9 -->
                <div class="question-container" data-question="9">
                    <div class="question-number">سوال 9 از 12</div>
                    <div class="question-text">برای شارژ انرژی:</div>
                    <div class="options-container">
                        <input type="radio" name="answers[EI3]" value="A" id="q9a" class="option-input">
                        <label for="q9a" class="option-label">
                            <span class="option-letter">A</span>
                            <span class="option-text">با دوستان وقت می‌گذرانم و اجتماعی می‌شوم</span>
                        </label>
                        
                        <input type="radio" name="answers[EI3]" value="B" id="q9b" class="option-input">
                        <label for="q9b" class="option-label">
                            <span class="option-letter">B</span>
                            <span class="option-text">تنهایی وقت می‌گذرانم و به درونم توجه می‌کنم</span>
                        </label>
                    </div>
                </div>

                <!-- سوال 10 -->
                <div class="question-container" data-question="10">
                    <div class="question-number">سوال 10 از 12</div>
                    <div class="question-text">در یادگیری مطالب جدید:</div>
                    <div class="options-container">
                        <input type="radio" name="answers[SN3]" value="A" id="q10a" class="option-input">
                        <label for="q10a" class="option-label">
                            <span class="option-letter">A</span>
                            <span class="option-text">تمرکز روی جزئیات و کاربردهای عملی دارم</span>
                        </label>
                        
                        <input type="radio" name="answers[SN3]" value="B" id="q10b" class="option-input">
                        <label for="q10b" class="option-label">
                            <span class="option-letter">B</span>
                            <span class="option-text">به مفاهیم کلی و ارتباط بین ایده‌ها توجه می‌کنم</span>
                        </label>
                    </div>
                </div>

                <!-- سوال 11 -->
                <div class="question-container" data-question="11">
                    <div class="question-number">سوال 11 از 12</div>
                    <div class="question-text">در قضاوت درباره دیگران:</div>
                    <div class="options-container">
                        <input type="radio" name="answers[TF3]" value="A" id="q11a" class="option-input">
                        <label for="q11a" class="option-label">
                            <span class="option-letter">A</span>
                            <span class="option-text">صادقانه و مستقیم نظرم را می‌گویم</span>
                        </label>
                        
                        <input type="radio" name="answers[TF3]" value="B" id="q11b" class="option-input">
                        <label for="q11b" class="option-label">
                            <span class="option-letter">B</span>
                            <span class="option-text">مراقب احساسات دیگران هستم و ملایمت به خرج می‌دهم</span>
                        </label>
                    </div>
                </div>

                <!-- سوال 12 -->
                <div class="question-container" data-question="12">
                    <div class="question-number">سوال 12 از 12</div>
                    <div class="question-text">در مواجهه با تغییرات ناگهانی:</div>
                    <div class="options-container">
                        <input type="radio" name="answers[JP3]" value="A" id="q12a" class="option-input">
                        <label for="q12a" class="option-label">
                            <span class="option-letter">A</span>
                            <span class="option-text">سعی می‌کنم کنترل اوضاع را به دست بگیرم و برنامه‌ریزی کنم</span>
                        </label>
                        
                        <input type="radio" name="answers[JP3]" value="B" id="q12b" class="option-input">
                        <label for="q12b" class="option-label">
                            <span class="option-letter">B</span>
                            <span class="option-text">با جریان همراه می‌شوم و خودم را با شرایط تطبیق می‌دهم</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- دکمه‌های ناوبری -->
            <div class="navigation-buttons">
                <button type="button" class="nav-btn" id="prevBtn" disabled>
                    <i class="fas fa-arrow-right"></i>
                    سوال قبلی
                </button>
                <button type="button" class="nav-btn primary" id="nextBtn">
                    سوال بعدی
                    <i class="fas fa-arrow-left"></i>
                </button>
                <button type="submit" class="nav-btn primary" id="submitBtn" style="display: none;">
                    مشاهده نتیجه
                    <i class="fas fa-star"></i>
                </button>
            </div>
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

class PersonalityTest {
    constructor() {
        this.currentQuestion = 1;
        this.totalQuestions = 12;
        this.questions = document.querySelectorAll('.question-container');
        this.prevBtn = document.getElementById('prevBtn');
        this.nextBtn = document.getElementById('nextBtn');
        this.submitBtn = document.getElementById('submitBtn');
        this.currentQuestionSpan = document.getElementById('currentQuestion');
        this.progressFill = document.getElementById('progressFill');
        
        this.init();
    }
    
    init() {
        this.updateProgress();
        this.attachEventListeners();
    }
    
    attachEventListeners() {
        this.prevBtn.addEventListener('click', () => this.previousQuestion());
        this.nextBtn.addEventListener('click', () => this.nextQuestion());
        
        // اتوماتیک رفتن به سوال بعدی وقتی جواب انتخاب شد
        this.questions.forEach(question => {
            const inputs = question.querySelectorAll('input[type="radio"]');
            inputs.forEach(input => {
                input.addEventListener('change', () => {
                    // برای سوال آخر، دکمه ارسال رو فعال کن
                    if (this.currentQuestion === this.totalQuestions) {
                        this.updateNavigation();
                    }
                    // برای بقیه سوالات، بعد از 500ms برو سوال بعدی
                    else if (this.currentQuestion < this.totalQuestions) {
                        setTimeout(() => this.nextQuestion(), 500);
                    }
                });
            });
        });
    }
    
    showQuestion(questionNumber) {
        this.questions.forEach(question => {
            question.classList.remove('active');
        });
        
        const targetQuestion = document.querySelector(`[data-question="${questionNumber}"]`);
        if (targetQuestion) {
            targetQuestion.classList.add('active');
        }
        
        this.updateNavigation();
        this.updateProgress();
    }
    
    nextQuestion() {
        if (this.currentQuestion < this.totalQuestions) {
            this.currentQuestion++;
            this.showQuestion(this.currentQuestion);
        }
    }
    
    previousQuestion() {
        if (this.currentQuestion > 1) {
            this.currentQuestion--;
            this.showQuestion(this.currentQuestion);
        }
    }
    
    updateNavigation() {
        this.prevBtn.disabled = this.currentQuestion === 1;
        
        if (this.currentQuestion === this.totalQuestions) {
            this.nextBtn.style.display = 'none';
            this.submitBtn.style.display = 'flex';
        } else {
            this.nextBtn.style.display = 'flex';
            this.submitBtn.style.display = 'none';
        }
        
        // بررسی اینکه آیا جواب فعلی داده شده یا نه
        const currentQuestionElement = document.querySelector(`[data-question="${this.currentQuestion}"]`);
        const hasAnswer = currentQuestionElement.querySelector('input[type="radio"]:checked');
        this.nextBtn.disabled = !hasAnswer && this.currentQuestion < this.totalQuestions;
        
        // برای سوال آخر، چک کن که همه سوالات پاسخ داده شده باشند
        if (this.currentQuestion === this.totalQuestions) {
            this.submitBtn.disabled = !this.isTestComplete();
        }
    }
    
    updateProgress() {
        const progress = (this.currentQuestion / this.totalQuestions) * 100;
        this.progressFill.style.width = `${progress}%`;
        this.currentQuestionSpan.textContent = this.currentQuestion;
    }
    
    isTestComplete() {
        let answeredCount = 0;
        this.questions.forEach(question => {
            const hasAnswer = question.querySelector('input[type="radio"]:checked');
            if (hasAnswer) answeredCount++;
        });
        return answeredCount === this.totalQuestions;
    }
}

// راه‌اندازی تست
document.addEventListener('DOMContentLoaded', function() {
    new PersonalityTest();
    
    // نمایش پیام تأیید قبل از ارسال
    const form = document.getElementById('personalityTestForm');
    form.addEventListener('submit', function(e) {
        if (!confirm('آیا از پاسخ‌های خود مطمئن هستید؟ پس از ثبت نتیجه، امکان تغییر پاسخ‌ها وجود ندارد.')) {
            e.preventDefault();
        }
    });
});
</script>

</body>
</html>