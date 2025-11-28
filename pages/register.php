<?php
require '../includes/config.php';

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $usertype = $_POST['usertype'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $national_code = trim($_POST['national_code'] ?? '');

    // اعتبارسنجی‌ها
    if (empty($full_name)) $errors[] = "نام و نام خانوادگی را وارد کنید.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "ایمیل معتبر وارد کنید.";
    if (empty($password) || strlen($password) < 6) $errors[] = "رمز عبور باید حداقل ۶ کاراکتر باشد.";
    if ($password !== $confirm_password) $errors[] = "رمز عبور و تکرارش مطابقت ندارند.";
    if (empty($usertype)) $errors[] = "لطفاً نوع کاربری خود را انتخاب کنید.";
    if (empty($gender)) $errors[] = "لطفاً جنسیت خود را انتخاب کنید.";
    if (empty($city)) $errors[] = "لطفاً شهر خود را وارد کنید.";
    if (empty($province)) $errors[] = "لطفاً استان خود را انتخاب کنید.";
    if (empty($phone) || !preg_match('/^09[0-9]{9}$/', $phone)) $errors[] = "شماره موبایل معتبر وارد کنید (09xxxxxxxxx).";
    if (!empty($national_code) && !preg_match('/^[0-9]{10}$/', $national_code)) $errors[] = "کد ملی باید ۱۰ رقم باشد.";

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$email, $phone]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "این ایمیل یا شماره تماس قبلا ثبت شده است.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, usertype, gender, city, province, phone, national_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$full_name, $email, $hashed_password, $usertype, $gender, $city, $province, $phone, $national_code])) {
                $success = "ثبت‌نام با موفقیت انجام شد! اکنون می‌توانید وارد شوید.";
            } else {
                $errors[] = "خطا در ثبت‌نام، دوباره تلاش کنید.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ثبت‌نام | هم‌اتاقی</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>

<style>
body, html {
    min-height: 100%;
    margin: 0;
    padding: 0;
    font-family: 'Tajawal', sans-serif;
    overflow-x: hidden;
    overflow-y: auto;
    background: linear-gradient(135deg, #0d0d1a, #1a1a2e);
}

#particles-js {
    position: fixed;
    width: 100%;
    height: 100%;
    z-index: 0;
    top: 0;
    left: 0;
}

.container {
    position: relative;
    width: 100%;
    max-width: 500px;
    margin: 40px auto;
    padding: 20px;
    z-index: 1;
    pointer-events: none;
}

.card {
    pointer-events: auto;
    background: rgba(20, 20, 35, 0.95);
    border-radius: 20px;
    padding: 40px 30px;
    width: 100%;
    box-shadow: 0 15px 40px rgba(0,0,0,0.5);
    backdrop-filter: blur(10px);
    border: 2px solid #5dade2;
    text-align: center;
}

.card h2 { 
    color: #f1c40f;
    margin-bottom: 30px;
}

.btn-primary {
    background: linear-gradient(135deg,#3498db,#5dade2);
    border:none;
    font-weight: 600;
    padding: 12px;
}
.btn-primary:hover {
    background: linear-gradient(135deg,#5dade2,#3498db);
}

.form-control, .form-select {
    background: rgba(42,42,60,0.95) !important;
    border: 1px solid #5dade2 !important;
    color: #f5f5f5 !important;
    padding: 12px;
}
.form-control:focus, .form-select:focus {
    background: rgba(42,42,60,1) !important;
    color: #f5f5f5 !important;
    box-shadow: 0 0 8px #5dade2 !important;
    border: 1px solid #5dade2 !important;
}

::placeholder {
    color: rgba(245,245,245,0.6) !important;
    opacity: 1 !important;
}

.form-label {
    color: #f0f0f0 !important;
    font-weight: 500;
    margin-bottom: 8px;
}

.text-danger { color: #ff6b6b !important; }
.text-success { color: #2ecc71 !important; }

.card p {
    color: #e0e0e0 !important;
    font-size: 0.95rem;
    margin-top: 20px;
}
.card p a {
    color: #5dade2 !important;
    font-weight: 500;
}
.card p a:hover {
    color: #3498db !important;
    text-decoration: underline;
}

.alert {
    border: none;
    border-radius: 10px;
}
.alert-danger {
    background: rgba(255,107,107,0.1);
    color: #ff6b6b;
}
.alert-success {
    background: rgba(46,204,113,0.1);
    color: #2ecc71;
}
</style>
</head>
<body>

<div id="particles-js"></div>

<div class="container">
    <!-- دکمه بازگشت -->
    <a href="../index.php" style="display: inline-flex; align-items: center; gap: 8px; color: #5dade2; text-decoration: none; margin-bottom: 20px; pointer-events: auto; font-weight: 500; transition: all 0.3s ease;" onmouseover="this.style.color='#3498db'; this.style.transform='translateX(-5px)';" onmouseout="this.style.color='#5dade2'; this.style.transform='translateX(0)';">
        <i class="fas fa-arrow-right"></i>
        بازگشت به صفحه اصلی
    </a>
    
    <div class="card">
        <h2 class="fw-bold">ثبت‌نام در هم‌اتاقی</h2>

        <?php if(!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0"><?php foreach($errors as $error) echo "<li>$error</li>"; ?></ul>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <!-- اطلاعات شخصی -->
            <div class="mb-3">
                <label class="form-label">نام و نام خانوادگی</label>
                <input type="text" name="full_name" class="form-control" placeholder="مثال: علی رضایی" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">ایمیل</label>
                <input type="email" name="email" class="form-control" placeholder="example@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">جنسیت</label>
                    <select name="gender" class="form-select" required>
                        <option value="">-- انتخاب کنید --</option>
                        <option value="آقا" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'آقا') ? 'selected' : ''; ?>>آقا</option>
                        <option value="خانم" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'خانم') ? 'selected' : ''; ?>>خانم</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">نوع کاربری</label>
                    <select name="usertype" class="form-select" required>
                        <option value="">-- انتخاب کنید --</option>
                        <option value="هم‌خانه" <?php echo (isset($_POST['usertype']) && $_POST['usertype'] == 'هم‌خانه') ? 'selected' : ''; ?>>هم‌خانه (دنبال اتاق می‌گردم)</option>
                        <option value="صاحب‌خانه" <?php echo (isset($_POST['usertype']) && $_POST['usertype'] == 'صاحب‌خانه') ? 'selected' : ''; ?>>صاحب‌خانه (اتاق دارم)</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">شماره موبایل</label>
                <input type="tel" name="phone" class="form-control" placeholder="09xxxxxxxxx" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">کد ملی (اختیاری)</label>
                <input type="text" name="national_code" class="form-control" placeholder="۱۰ رقم" value="<?php echo isset($_POST['national_code']) ? htmlspecialchars($_POST['national_code']) : ''; ?>" maxlength="10">
            </div>

            <!-- موقعیت جغرافیایی -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">استان</label>
                    <select name="province" class="form-select" required>
                        <option value="">-- انتخاب استان --</option>
                        <option value="تهران" <?php echo (isset($_POST['province']) && $_POST['province'] == 'تهران') ? 'selected' : ''; ?>>تهران</option>
                        <option value="البرز" <?php echo (isset($_POST['province']) && $_POST['province'] == 'البرز') ? 'selected' : ''; ?>>البرز</option>
                        <option value="اصفهان" <?php echo (isset($_POST['province']) && $_POST['province'] == 'اصفهان') ? 'selected' : ''; ?>>اصفهان</option>
                        <option value="فارس" <?php echo (isset($_POST['province']) && $_POST['province'] == 'فارس') ? 'selected' : ''; ?>>فارس</option>
                        <option value="خراسان رضوی" <?php echo (isset($_POST['province']) && $_POST['province'] == 'خراسان رضوی') ? 'selected' : ''; ?>>خراسان رضوی</option>
                        <option value="آذربایجان شرقی" <?php echo (isset($_POST['province']) && $_POST['province'] == 'آذربایجان شرقی') ? 'selected' : ''; ?>>آذربایجان شرقی</option>
                        <option value="مازندران" <?php echo (isset($_POST['province']) && $_POST['province'] == 'مازندران') ? 'selected' : ''; ?>>مازندران</option>
                        <option value="گیلان" <?php echo (isset($_POST['province']) && $_POST['province'] == 'گیلان') ? 'selected' : ''; ?>>گیلان</option>
                        <option value="سایر" <?php echo (isset($_POST['province']) && $_POST['province'] == 'سایر') ? 'selected' : ''; ?>>سایر</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">شهر</label>
                    <input type="text" name="city" class="form-control" placeholder="مثال: تهران" value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>" required>
                </div>
            </div>

            <!-- رمز عبور -->
            <div class="mb-3">
                <label class="form-label">رمز عبور</label>
                <input type="password" name="password" class="form-control" placeholder="حداقل ۶ کاراکتر" required>
            </div>

            <div class="mb-3">
                <label class="form-label">تکرار رمز عبور</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="رمز عبور را دوباره وارد کنید" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-3">ثبت‌نام</button>
        </form>

        <p class="mt-3">قبلا ثبت‌نام کرده‌اید؟ <a href="login.php">وارد شوید</a></p>
    </div>
</div>

<script>
particlesJS("particles-js", {
  "particles": {
    "number": {"value": 50, "density": {"enable": true, "value_area": 800}},
    "color": {"value": ["#3498db","#5dade2","#f1c40f","#2ecc71"]},
    "shape": {"type": "circle"},
    "opacity": {"value":0.6,"random":true,"anim":{"enable":true,"speed":1,"opacity_min":0.3,"sync":false}},
    "size":{"value":3,"random":true,"anim":{"enable":false}},
    "line_linked":{"enable":true,"distance":150,"color":"#5dade2","opacity":0.4,"width":1},
    "move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}
  },
  "interactivity":{
    "detect_on":"canvas",
    "events":{"onhover":{"enable":true,"mode":"grab"},"onclick":{"enable":true,"mode":"push"},"resize":true},
    "modes":{"grab":{"distance":200,"line_linked":{"opacity":0.8}},"push":{"particles_nb":4}}
  },
  "retina_detect": true
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>