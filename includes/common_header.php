<?php
/**
 * Common Header for All Pages
 * Matches dashboard.php design
 */
if (!isset($pageTitle)) {
    $pageTitle = 'هم‌اتاقی';
}
if (!isset($showParticles)) {
    $showParticles = true;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | هم‌اتاقی</title>
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

        .brand i { font-size: 28px; }

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
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .page-header {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 15px;
            padding: 25px 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(15px);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-light);
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            backdrop-filter: blur(10px);
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid var(--danger);
            color: #ff5252;
        }

        .alert-info {
            background: rgba(33, 150, 243, 0.2);
            border: 1px solid var(--info);
            color: var(--info);
        }

        .glass-effect {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 15px;
            backdrop-filter: blur(15px);
        }

        @keyframes gridMove {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(-60px) translateY(-60px); }
        }

        @keyframes floatLight {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(120px, -80px) scale(1.3); }
            50% { transform: translate(-80px, 120px) scale(0.9); }
            75% { transform: translate(-120px, -120px) scale(1.2); }
        }
    </style>
    <?php if (isset($additionalStyles)): ?>
        <style><?php echo $additionalStyles; ?></style>
    <?php endif; ?>
</head>
<body>
    <?php if ($showParticles): ?>
    <div id="particles-js"></div>
    <div class="grid-lines"></div>
    <div class="light-spot"></div>
    <div class="light-spot"></div>
    <div class="light-spot"></div>
    <?php endif; ?>

    <nav class="main-nav">
        <div class="nav-container">
            <a href="dashboard.php" class="brand">
                <i class="fas fa-home-heart"></i>
                هم‌اتاقی
            </a>
            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="available_houses.php" class="nav-btn">
                        <i class="fas fa-search"></i> جستجوی خانه
                    </a>
                    <?php if (isset($currentUser) && $currentUser['usertype'] === 'صاحب‌خانه'): ?>
                        <a href="my_houses.php" class="nav-btn">
                            <i class="fas fa-home"></i> خانه‌های من
                        </a>
                        <a href="add_house.php" class="nav-btn primary">
                            <i class="fas fa-plus"></i> افزودن خانه
                        </a>
                    <?php else: ?>
                        <a href="my_requests.php" class="nav-btn">
                            <i class="fas fa-envelope"></i> درخواست‌های من
                        </a>
                    <?php endif; ?>
                    <a href="favorites.php" class="nav-btn">
                        <i class="fas fa-heart"></i> علاقه‌مندی‌ها
                    </a>
                    <a href="messages.php" class="nav-btn">
                        <i class="fas fa-comments"></i> پیام‌ها
                        <?php if (isset($unreadCount) && $unreadCount > 0): ?>
                            <span class="notification-badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="profile.php" class="nav-btn">
                        <i class="fas fa-user"></i> پروفایل
                    </a>
                    <a href="logout.php" class="nav-btn logout">
                        <i class="fas fa-sign-out-alt"></i> خروج
                    </a>
                <?php else: ?>
                    <a href="login.php" class="nav-btn">ورود</a>
                    <a href="register.php" class="nav-btn primary">ثبت‌نام</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container">

