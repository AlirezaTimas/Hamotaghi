<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

// دریافت اطلاعات کاربر برای نمایش و اعتبارسنجی نقش
$userStmt = $conn->prepare("SELECT full_name, usertype, gender FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    header('Location: logout.php');
    exit();
}

$successMessage = '';
$errorMessage = '';

// حذف علاقه‌مندی
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['favorite_id'])) {
    $favoriteId = (int)$_POST['favorite_id'];
    try {
        $deleteStmt = $conn->prepare("DELETE FROM favorites WHERE id = ? AND user_id = ?");
        $deleteStmt->execute([$favoriteId, $userId]);
        $successMessage = 'آگهی از فهرست علاقه‌مندی‌های شما حذف شد.';
    } catch (Throwable $e) {
        $errorMessage = 'خطا در حذف علاقه‌مندی: ' . $e->getMessage();
    }
}

// دریافت علاقه‌مندی‌ها
$favoritesStmt = $conn->prepare("
    SELECT 
        f.id as favorite_id,
        h.*,
        u.full_name as owner_name,
        u.gender as owner_gender,
        u.personality_type as owner_personality
    FROM favorites f
    JOIN houses h ON f.house_id = h.id
    JOIN users u ON h.user_id = u.id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");
$favoritesStmt->execute([$userId]);
$favorites = $favoritesStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>علاقه‌مندی‌ها | هم‌اتاقی</title>
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

        .nav-btn.logout {
            background: rgba(244, 67, 54, 0.1);
            color: #ff5252;
            border-color: rgba(255, 82, 82, 0.3);
        }

        .nav-btn.logout:hover {
            background: rgba(244, 67, 54, 0.2);
            transform: translateY(-2px);
        }

        .nav-links a {
            color: #eee;
            text-decoration: none;
            margin-right: 15px;
            font-weight: 500;
        }
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .page-header p {
            color: #a8b3c5;
        }
        .alert {
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: rgba(76, 175, 80, 0.15);
            border: 1px solid rgba(76, 175, 80, 0.3);
            color: #4caf50;
        }
        .alert-error {
            background: rgba(244, 67, 54, 0.15);
            border: 1px solid rgba(244, 67, 54, 0.3);
            color: #ff7961;
        }
        .favorites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .favorite-card {
            background: rgba(20, 31, 44, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0,0,0,0.25);
            display: flex;
            flex-direction: column;
        }
        .favorite-image {
            height: 180px;
            background: #111;
            position: relative;
        }
        .favorite-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .gender-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
        }
        .gender-male {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
        }
        .gender-female {
            background: rgba(233, 30, 99, 0.2);
            color: #e91e63;
        }
        .favorite-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .favorite-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .favorite-location,
        .favorite-detail {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #a8b3c5;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .favorite-price {
            margin-top: auto;
            font-weight: 800;
            font-size: 18px;
            color: #ffca28;
        }
        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            border: none;
            border-radius: 10px;
            padding: 10px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            flex: 1;
        }
        .btn-view {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }
        .btn-remove {
            background: rgba(244, 67, 54, 0.15);
            color: #ff7961;
        }
        .empty-state {
            text-align: center;
            color: #a8b3c5;
            padding: 60px 20px;
            border: 1px dashed rgba(255,255,255,0.15);
            border-radius: 20px;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #ffab00;
        }
        .empty-state a {
            color: #ffab00;
        }
        @media (max-width: 600px) {
            .nav-links { display: none; }
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
                <i class="fas fa-search"></i>
                جستجوی خانه
            </a>
            <a href="favorites.php" class="nav-btn">
                <i class="fas fa-heart"></i>
                علاقه‌مندی‌ها
            </a>
            <a href="my_requests.php" class="nav-btn">
                <i class="fas fa-envelope"></i>
                درخواست‌های من
            </a>
            <a href="dashboard.php" class="nav-btn">
                <i class="fas fa-tachometer-alt"></i>
                داشبورد
            </a>
            <a href="messages.php" class="nav-btn">
                <i class="fas fa-comments"></i>
                پیام‌ها
            </a>
            <a href="logout.php" class="nav-btn logout">
                <i class="fas fa-sign-out-alt"></i>
                خروج
            </a>
        </div>
    </div>
</nav>

    <div class="container">
        <div class="page-header">
            <h1>❤️ علاقه‌مندی‌های شما</h1>
            <p>آگهی‌هایی که برای بازگشت سریع علامت‌گذاری کردید.</p>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <?php if (empty($favorites)): ?>
            <div class="empty-state">
                <i class="fas fa-heart-circle-plus"></i>
                <h3>لیست شما خالی است</h3>
                <p>به صفحه <a href="available_houses.php">خانه‌ها</a> بروید و گزینه‌های محبوبتان را ذخیره کنید.</p>
            </div>
        <?php else: ?>
            <div class="favorites-grid">
                <?php foreach ($favorites as $favorite): 
                    $images = [];
                    if (!empty($favorite['images'])) {
                        $decoded = json_decode($favorite['images'], true);
                        if (is_array($decoded)) {
                            $images = $decoded;
                        }
                    }
                    $imageSrc = $images[0] ?? 'assets/images/roommates.jpg';
                    ?>
                    <div class="favorite-card">
                        <div class="favorite-image">
                            <img src="../<?= htmlspecialchars($imageSrc); ?>" alt="<?= htmlspecialchars($favorite['title']); ?>">
                            <span class="gender-badge <?= $favorite['gender'] === 'خانم' ? 'gender-female' : 'gender-male'; ?>">
                                <?= htmlspecialchars($favorite['gender']); ?>
                            </span>
                        </div>
                        <div class="favorite-body">
                            <div class="favorite-title"><?= htmlspecialchars($favorite['title']); ?></div>
                            <div class="favorite-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($favorite['city'] . '، ' . $favorite['province']); ?>
                            </div>
                            <div class="favorite-detail">
                                <i class="fas fa-user"></i>
                                میزبان: <?= htmlspecialchars($favorite['owner_name']); ?>
                            </div>
                            <?php if (!empty($favorite['owner_personality'])): ?>
                            <div class="favorite-detail">
                                <i class="fas fa-brain"></i>
                                تیپ شخصیتی مالک: <?= htmlspecialchars($favorite['owner_personality']); ?>
                            </div>
                            <?php endif; ?>
                            <div class="favorite-detail">
                                <i class="fas fa-users"></i>
                                ظرفیت خالی: <?= (int)$favorite['available_capacity']; ?> / <?= (int)$favorite['capacity']; ?>
                            </div>
                            <div class="favorite-price">
                                <?= number_format((int)$favorite['price']); ?> تومان
                            </div>
                            <div class="card-actions">
                                <a class="btn btn-view" href="house_details.php?id=<?= (int)$favorite['id']; ?>">
                                    مشاهده جزئیات
                                </a>
                                <form method="POST">
                                    <input type="hidden" name="favorite_id" value="<?= (int)$favorite['favorite_id']; ?>">
                                    <button type="submit" class="btn btn-remove">
                                        حذف
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
</script>
</body>
</html>

