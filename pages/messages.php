<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

$userStmt = $conn->prepare("SELECT id, full_name, usertype FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    header('Location: logout.php');
    exit();
}

$successMessage = '';
$errorMessage = '';

/**
 * بررسی اینکه کاربر اجازه گفتگو دارد یا خیر
 */
function canMessage(PDO $conn, int $userId, int $otherUserId, int $houseId): bool
{
    // Check which table exists (requests or matches)
    $requestsExists = $conn->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0;
    $matchesExists = $conn->query("SHOW TABLES LIKE 'matches'")->rowCount() > 0;
    
    if ($requestsExists) {
        // Use requests table structure
        $stmt = $conn->prepare("
            SELECT 1
            FROM requests r
            JOIN houses h ON r.listing_id = h.id
            WHERE r.listing_id = :house_id
              AND (r.request_status = 'pending' OR r.request_status = 'approved' OR r.request_status = 'در انتظار' OR r.request_status = 'تایید شده' OR r.request_status = '' OR r.request_status IS NULL)
              AND (
                  (r.sender_user_id = :user_id AND r.receiver_owner_id = :other_user)
               OR (r.sender_user_id = :other_user AND r.receiver_owner_id = :user_id)
              )
            LIMIT 1
        ");
    } elseif ($matchesExists) {
        // Use matches table structure
        $stmt = $conn->prepare("
            SELECT 1
            FROM matches r
            JOIN houses h ON r.house_id = h.id
            WHERE r.house_id = :house_id
              AND r.status IN ('در انتظار','تایید شده')
              AND (
                  (r.user_id = :user_id AND h.user_id = :other_user)
               OR (r.user_id = :other_user AND h.user_id = :user_id)
              )
            LIMIT 1
        ");
    } else {
        return false;
    }
    
    $stmt->execute([
        ':house_id' => $houseId,
        ':user_id' => $userId,
        ':other_user' => $otherUserId
    ]);
    return (bool)$stmt->fetchColumn();
}

// ارسال پیام
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_content'])) {
    $houseId = (int)($_POST['house_id'] ?? 0);
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $messageContent = trim($_POST['message_content'] ?? '');

    if ($houseId <= 0 || $receiverId <= 0 || $messageContent === '') {
        $errorMessage = 'تمامی فیلدها الزامی هستند.';
    } elseif (!canMessage($conn, $userId, $receiverId, $houseId)) {
        $errorMessage = 'اجازه ارسال پیام برای این آگهی را ندارید.';
    } else {
        try {
            $insertMsg = $conn->prepare("
                INSERT INTO messages (house_id, sender_id, receiver_id, content)
                VALUES (:house_id, :sender_id, :receiver_id, :content)
            ");
            $insertMsg->execute([
                ':house_id' => $houseId,
                ':sender_id' => $userId,
                ':receiver_id' => $receiverId,
                ':content' => $messageContent
            ]);
            $successMessage = 'پیام ارسال شد.';
        } catch (Throwable $e) {
            $errorMessage = 'خطا در ارسال پیام: ' . $e->getMessage();
        }
    }
}

// استخراج مخاطبین
// Check which table exists (requests or matches)
$requestsExists = $conn->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0;
$matchesExists = $conn->query("SHOW TABLES LIKE 'matches'")->rowCount() > 0;

if ($currentUser['usertype'] === 'صاحب‌خانه') {
    if ($requestsExists) {
        // Use requests table structure
        $contactsStmt = $conn->prepare("
            SELECT 
                r.sender_user_id as partner_id,
                u.full_name as partner_name,
                r.listing_id as house_id,
                h.title as house_title,
                COALESCE(
                    (SELECT MAX(created_at) FROM messages 
                     WHERE house_id = r.listing_id 
                       AND ((sender_id = :user_id AND receiver_id = r.sender_user_id) OR (sender_id = r.sender_user_id AND receiver_id = :user_id))
                    ), r.created_at
                ) as last_activity,
                (SELECT COUNT(*) FROM messages 
                 WHERE house_id = r.listing_id 
                   AND sender_id = r.sender_user_id 
                   AND receiver_id = :user_id 
                   AND is_read = 0) as unread_count
            FROM requests r
            JOIN users u ON r.sender_user_id = u.id
            JOIN houses h ON r.listing_id = h.id
            WHERE r.receiver_owner_id = :user_id
              AND (r.request_status = 'pending' OR r.request_status = 'approved' OR r.request_status = 'در انتظار' OR r.request_status = 'تایید شده' OR r.request_status = '' OR r.request_status IS NULL)
            ORDER BY last_activity DESC
        ");
    } else {
        // Use matches table structure
        $contactsStmt = $conn->prepare("
            SELECT 
                r.user_id as partner_id,
                u.full_name as partner_name,
                r.house_id,
                h.title as house_title,
                COALESCE(
                    (SELECT MAX(created_at) FROM messages 
                     WHERE house_id = r.house_id 
                       AND ((sender_id = :user_id AND receiver_id = r.user_id) OR (sender_id = r.user_id AND receiver_id = :user_id))
                    ), r.created_at
                ) as last_activity,
                (SELECT COUNT(*) FROM messages 
                 WHERE house_id = r.house_id 
                   AND sender_id = r.user_id 
                   AND receiver_id = :user_id 
                   AND is_read = 0) as unread_count
            FROM matches r
            JOIN users u ON r.user_id = u.id
            JOIN houses h ON r.house_id = h.id
            WHERE h.user_id = :user_id
              AND r.status IN ('در انتظار','تایید شده')
            ORDER BY last_activity DESC
        ");
    }
} else {
    if ($requestsExists) {
        // Use requests table structure
        $contactsStmt = $conn->prepare("
            SELECT 
                r.receiver_owner_id as partner_id,
                u.full_name as partner_name,
                r.listing_id as house_id,
                h.title as house_title,
                COALESCE(
                    (SELECT MAX(created_at) FROM messages 
                     WHERE house_id = r.listing_id 
                       AND ((sender_id = :user_id AND receiver_id = r.receiver_owner_id) OR (sender_id = r.receiver_owner_id AND receiver_id = :user_id))
                    ), r.created_at
                ) as last_activity,
                (SELECT COUNT(*) FROM messages 
                 WHERE house_id = r.listing_id 
                   AND sender_id = r.receiver_owner_id 
                   AND receiver_id = :user_id 
                   AND is_read = 0) as unread_count
            FROM requests r
            JOIN houses h ON r.listing_id = h.id
            JOIN users u ON r.receiver_owner_id = u.id
            WHERE r.sender_user_id = :user_id
              AND (r.request_status = 'pending' OR r.request_status = 'approved' OR r.request_status = 'در انتظار' OR r.request_status = 'تایید شده' OR r.request_status = '' OR r.request_status IS NULL)
            ORDER BY last_activity DESC
        ");
    } else {
        // Use matches table structure
        $contactsStmt = $conn->prepare("
            SELECT 
                h.user_id as partner_id,
                u.full_name as partner_name,
                r.house_id,
                h.title as house_title,
                COALESCE(
                    (SELECT MAX(created_at) FROM messages 
                     WHERE house_id = r.house_id 
                       AND ((sender_id = :user_id AND receiver_id = h.user_id) OR (sender_id = h.user_id AND receiver_id = :user_id))
                    ), r.created_at
                ) as last_activity,
                (SELECT COUNT(*) FROM messages 
                 WHERE house_id = r.house_id 
                   AND sender_id = h.user_id 
                   AND receiver_id = :user_id 
                   AND is_read = 0) as unread_count
            FROM matches r
            JOIN houses h ON r.house_id = h.id
            JOIN users u ON h.user_id = u.id
            WHERE r.user_id = :user_id
              AND r.status IN ('در انتظار','تایید شده')
            ORDER BY last_activity DESC
        ");
    }
}

try {
    $contactsStmt->execute([':user_id' => $userId]);
    $contacts = $contactsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching contacts: " . $e->getMessage());
    $contacts = [];
}

$activeHouseId = isset($_GET['house_id']) ? (int)$_GET['house_id'] : 0;
$activePartnerId = isset($_GET['with']) ? (int)$_GET['with'] : 0;

if ((!$activeHouseId || !$activePartnerId) && !empty($contacts)) {
    $activeHouseId = (int)$contacts[0]['house_id'];
    $activePartnerId = (int)$contacts[0]['partner_id'];
}

$messages = [];
if ($activeHouseId && $activePartnerId) {
    try {
        if (canMessage($conn, $userId, $activePartnerId, $activeHouseId)) {
            $messagesStmt = $conn->prepare("
                SELECT * FROM messages
                WHERE house_id = :house_id
                  AND ((sender_id = :user_id AND receiver_id = :partner_id) 
                    OR (sender_id = :partner_id AND receiver_id = :user_id))
                ORDER BY created_at ASC
            ");
            $messagesStmt->execute([
                ':house_id' => $activeHouseId,
                ':user_id' => $userId,
                ':partner_id' => $activePartnerId
            ]);
            $messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);

            // علامت‌گذاری پیام‌های خوانده‌نشده
            try {
                $conn->prepare("
                    UPDATE messages SET is_read = 1
                    WHERE house_id = :house_id AND receiver_id = :user_id AND sender_id = :partner_id AND is_read = 0
                ")->execute([
                    ':house_id' => $activeHouseId,
                    ':user_id' => $userId,
                    ':partner_id' => $activePartnerId
                ]);
            } catch (PDOException $e) {
                error_log("Error marking messages as read: " . $e->getMessage());
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching messages: " . $e->getMessage());
        $messages = [];
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیام‌ها | هم‌اتاقی</title>
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

        .nav-btn.logout {
            background: rgba(244, 67, 54, 0.1);
            color: #ff5252;
            border-color: rgba(255, 82, 82, 0.3);
        }

        .nav-btn.logout:hover {
            background: rgba(244, 67, 54, 0.2);
            transform: translateY(-2px);
        }

        .wrapper { 
            display:flex; 
            height:calc(100vh - 70px); 
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
            gap: 20px;
        }
        .sidebar { 
            width:30%; 
            border: 1px solid var(--card-border);
            border-radius: 20px;
            background: var(--card-bg);
            backdrop-filter: blur(15px);
            overflow-y:auto; 
        }
        .sidebar-header { 
            padding:20px; 
            border-bottom:1px solid rgba(255,255,255,0.1); 
        }
        .contact { 
            padding:15px 20px; 
            border-bottom:1px solid rgba(255,255,255,0.05); 
            cursor:pointer; 
            display:flex; 
            justify-content:space-between; 
            align-items:flex-start;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        .contact:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        .contact.active { 
            background:rgba(255, 171, 0, 0.15);
            border-right: 3px solid var(--gold);
        }
        .contact h4 { margin:0 0 5px 0; font-size:16px; color:#fff; }
        .contact small { color:var(--text-muted); }
        .unread-badge { background:#ff5252; color:#fff; padding:2px 8px; border-radius:999px; font-size:12px; }
        .chat { 
            flex:1; 
            display:flex; 
            flex-direction:column;
            border: 1px solid var(--card-border);
            border-radius: 20px;
            background: var(--card-bg);
            backdrop-filter: blur(15px);
            overflow: hidden;
        }
        .chat-header { 
            padding:20px; 
            border-bottom:1px solid rgba(255,255,255,0.1); 
            background:rgba(11, 20, 25, 0.5);
        }
        .chat-body { 
            flex:1; 
            padding:20px; 
            overflow-y:auto; 
            background:transparent;
        }
        .message { 
            margin-bottom:15px; 
            max-width:70%; 
            padding:12px 16px; 
            border-radius:16px; 
            line-height:1.5;
            animation: slideInUp 0.3s ease-out;
        }
        .message.me { 
            margin-left:auto; 
            background:linear-gradient(135deg, var(--gold), var(--gold-dark)); 
            color:#111; 
            border-bottom-right-radius:4px; 
        }
        .message.other { 
            background:rgba(255,255,255,0.1); 
            border-bottom-left-radius:4px; 
        }
        .message time { 
            display:block; 
            font-size:12px; 
            margin-top:6px; 
            opacity:0.7; 
        }
        .chat-footer { 
            padding:15px; 
            border-top:1px solid rgba(255,255,255,0.1); 
            background:rgba(11, 20, 25, 0.5); 
            display:flex; 
            gap:10px; 
        }
        .chat-footer textarea { 
            flex:1; 
            resize:none; 
            border-radius:12px; 
            border:1px solid rgba(255,255,255,0.1); 
            padding:12px; 
            background:rgba(255,255,255,0.05); 
            color:#fff; 
            font-family:inherit; 
        }
        .chat-footer textarea:focus {
            outline: none;
            border-color: var(--gold);
            background: rgba(255,255,255,0.08);
        }
        .chat-footer button { 
            border:none; 
            padding:0 20px; 
            border-radius:12px; 
            background:linear-gradient(135deg,#ffab00,#ff8f00); 
            color:#111; 
            font-weight:700; 
            cursor:pointer;
            transition: all 0.3s ease;
        }
        .chat-footer button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 171, 0, 0.3);
        }
        .alert { 
            margin:15px 20px; 
            border-radius:12px; 
            padding:12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInUp 0.5s ease-out;
        }
        .alert-success { 
            background:rgba(76,175,80,0.15); 
            color:#4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        .alert-error { 
            background:rgba(244,67,54,0.15); 
            color:#ff7961;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }
        .empty-state { 
            text-align:center; 
            padding:40px; 
            color:var(--text-muted); 
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
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

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media(max-width:900px){ 
            .wrapper { 
                flex-direction:column; 
                height:auto; 
            } 
            .sidebar { 
                width:100%; 
                height:auto; 
            }
            .nav-container {
                flex-direction: column;
                gap: 15px;
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

    <div class="wrapper">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>گفتگوها</h3>
                <p class="text-muted">لیست آگهی‌ها و مخاطبین شما</p>
            </div>
            <?php if (empty($contacts)): ?>
                <div class="empty-state">
                    <p>هنوز گفتگویی ندارید. ابتدا برای یک آگهی درخواست ارسال کنید.</p>
                </div>
            <?php else: ?>
                <?php foreach ($contacts as $contact): 
                    $active = ($contact['house_id'] == $activeHouseId && $contact['partner_id'] == $activePartnerId);
                    $link = "?house_id={$contact['house_id']}&with={$contact['partner_id']}";
                ?>
                    <a class="contact <?= $active ? 'active' : ''; ?>" href="<?= $link; ?>">
                        <div>
                            <h4><?= htmlspecialchars($contact['partner_name']); ?></h4>
                            <small><?= htmlspecialchars($contact['house_title']); ?></small>
                        </div>
                        <?php if ((int)$contact['unread_count'] > 0): ?>
                            <span class="unread-badge"><?= (int)$contact['unread_count']; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat">
            <div class="chat-header">
                <h3>پیام‌رسان داخلی</h3>
                <p class="text-muted">گفتگو با میزبان یا متقاضی</p>
            </div>

            <?php if ($successMessage): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert alert-error"><?= htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <div class="chat-body">
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comments mb-3" style="font-size:36px;"></i>
                        <p>گفتگویی انتخاب نشده یا مجاز نیستید.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="message <?= $msg['sender_id'] == $userId ? 'me' : 'other'; ?>">
                            <?= nl2br(htmlspecialchars($msg['content'])); ?>
                            <time><?= date('Y/m/d H:i', strtotime($msg['created_at'])); ?></time>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($activeHouseId && $activePartnerId && empty($errorMessage)): ?>
            <form method="POST" class="chat-footer">
                <textarea name="message_content" rows="2" placeholder="پیام خود را بنویسید..." required></textarea>
                <input type="hidden" name="house_id" value="<?= $activeHouseId; ?>">
                <input type="hidden" name="receiver_id" value="<?= $activePartnerId; ?>">
                <button type="submit">ارسال</button>
            </form>
            <?php endif; ?>
        </div>
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

// Auto-scroll to bottom of chat
document.addEventListener('DOMContentLoaded', function() {
    const chatBody = document.querySelector('.chat-body');
    if (chatBody) {
        chatBody.scrollTop = chatBody.scrollHeight;
    }
    
    // Auto-hide alerts
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

