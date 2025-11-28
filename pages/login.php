<?php
require '../includes/config.php';

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "ایمیل معتبر وارد کنید.";
    if (empty($password)) $errors[] = "رمز عبور را وارد کنید.";

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, password, full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];

            // ✅ تغییر ریدایرکت به داشبورد
            header("Location: ../pages/dashboard.php");
            exit;
        } else {
            $errors[] = "ایمیل یا رمز عبور اشتباه است.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود | هم‌اتاقی</title>

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
    max-width: 420px;
    margin: 40px auto;
    padding: 20px;
    z-index: 1;
    pointer-events: none;
}

.card {
    pointer-events: auto;
    background: rgba(20, 20, 35, 0.9);
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
}

.btn-primary {
    background: linear-gradient(135deg,#3498db,#5dade2);
    border:none;
    font-weight: 600;
}
.btn-primary:hover {
    background: linear-gradient(135deg,#5dade2,#3498db);
}

/* فرم‌ها */
.form-control {
    background: rgba(42,42,60,0.95) !important;
    border: 1px solid #5dade2 !important;
    color: #f5f5f5 !important;
}
.form-control:focus {
    background: rgba(42,42,60,1) !important;
    color: #f5f5f5 !important;
    box-shadow: 0 0 8px #5dade2 !important;
    border: 1px solid #5dade2 !important;
}

/* placeholder روشن */
::placeholder {
    color: rgba(245,245,245,0.8) !important;
    opacity: 1 !important;
}

/* label */
.form-label {
    color: #f0f0f0 !important;
}

/* alerts و لینک‌ها */
.text-danger { color: #ff6b6b !important; }
.text-success { color: #2ecc71 !important; }

/* متن پایین فرم و لینک */
.card p {
    color: #e0e0e0 !important; /* کمی روشن خاکستری */
    font-size: 0.95rem;
}
.card p a {
    color: #5dade2 !important; /* لینک هماهنگ با تم آبی */
    font-weight: 500;
}
.card p a:hover {
    color: #3498db !important;
    text-decoration: underline;
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
        <h2 class="mb-4 fw-bold">ورود به هم‌اتاقی</h2>

        <?php if(!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0"><?php foreach($errors as $error) echo "<li>$error</li>"; ?></ul>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert alert-success text-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="mb-3">
                <label class="form-label">ایمیل</label>
                <input type="email" name="email" class="form-control" placeholder="example@email.com" required>
            </div>
            <div class="mb-3">
                <label class="form-label">رمز عبور</label>
                <input type="password" name="password" class="form-control" placeholder="رمز عبور خود را وارد کنید" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">ورود</button>
        </form>

        <p class="mt-3">حساب کاربری ندارید؟ <a href="register.php">ثبت‌نام کنید</a></p>
    </div>
</div>

<script>
particlesJS("particles-js", {
  "particles": {
    "number": {"value": 70, "density": {"enable": true, "value_area": 650}},
    "color": {"value": ["#3498db","#5dade2","#f1c40f","#2ecc71"]},
    "shape": {"type": "circle"},
    "opacity": {"value":0.75,"random":true,"anim":{"enable":true,"speed":1,"opacity_min":0.3,"sync":false}},
    "size":{"value":3,"random":true,"anim":{"enable":false}},
    "line_linked":{"enable":true,"distance":120,"color":"#5dade2","opacity":0.6,"width":1},
    "move":{"enable":true,"speed":2,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}
  },
  "interactivity":{
    "detect_on":"canvas",
    "events":{"onhover":{"enable":true,"mode":"grab"},"onclick":{"enable":true,"mode":"push"},"resize":true},
    "modes":{"grab":{"distance":180,"line_linked":{"opacity":0.85}},"push":{"particles_nb":6}}
  },
  "retina_detect": true
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
