<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($title) ? Helper::e($title) . ' | ' : '' ?>هم‌اتاقی</title>
    
    <!-- Bootstrap RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <?php if (isset($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?= Helper::e($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <?php if (Auth::check()): ?>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="/pages/dashboard.php">هم‌اتاقی</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="/pages/dashboard.php">
                                <i class="fas fa-home"></i> داشبورد
                            </a>
                        </li>
                        <?php if (Auth::isRoommate()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/pages/available_houses.php">
                                    <i class="fas fa-search"></i> جستجوی خانه
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/pages/my_requests.php">
                                    <i class="fas fa-list"></i> درخواست‌های من
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if (Auth::isLandlord()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/pages/my_houses.php">
                                    <i class="fas fa-home"></i> خانه‌های من
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/pages/add_house.php">
                                    <i class="fas fa-plus"></i> افزودن خانه
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/pages/favorites.php">
                                <i class="fas fa-heart"></i> علاقه‌مندی‌ها
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/pages/messages.php">
                                <i class="fas fa-comments"></i> پیام‌ها
                                <?php
                                $messageModel = new Message();
                                $unreadCount = $messageModel->getUnreadCount(Auth::id());
                                if ($unreadCount > 0):
                                ?>
                                    <span class="badge bg-danger"><?= $unreadCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/pages/profile.php">
                                <i class="fas fa-user"></i> پروفایل
                            </a>
                        </li>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="/logout.php">
                                <i class="fas fa-sign-out-alt"></i> خروج
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    <?php endif; ?>
    
    <main class="<?= isset($mainClass) ? $mainClass : '' ?>">

