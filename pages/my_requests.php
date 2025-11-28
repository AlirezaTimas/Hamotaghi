<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

$userStmt = $conn->prepare("SELECT full_name, usertype FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser || $currentUser['usertype'] !== 'هم‌خانه') {
    header('Location: dashboard.php');
    exit();
}

$statuses = [
    'در انتظار' => 'pending',
    'تایید شده' => 'accepted',
    'رد شده' => 'rejected',
    'لغو شده' => 'cancelled'
];

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    $requestId = (int)$_POST['cancel_request'];
    if ($requestId > 0) {
        try {
            // Check which table exists - same logic as above
            $requestsExists = $conn->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0;
            $matchesExists = $conn->query("SHOW TABLES LIKE 'matches'")->rowCount() > 0;
            
            $tableName = null;
            $idField = null;
            $userField = null;
            $statusField = null;
            
            if ($requestsExists) {
                $tableName = 'requests';
                $idField = 'request_id';
                $userField = 'sender_user_id';
                $statusField = 'request_status';
            } elseif ($matchesExists) {
                $tableName = 'matches';
                $idField = 'id';
                $userField = 'user_id';
                $statusField = 'status';
            } else {
                $errorMessage = 'جدول درخواست‌ها وجود ندارد.';
            }
            
            if ($tableName) {
                            // Map status - requests uses English, matches uses Farsi
                            $cancelledStatus = ($tableName === 'requests') ? 'cancelled' : 'لغو شده';
                            $pendingStatus = ($tableName === 'requests') ? 'pending' : 'در انتظار';
                            
                            $cancelStmt = $conn->prepare("
                                UPDATE {$tableName}
                                SET {$statusField} = ?, updated_at = NOW()
                                WHERE {$idField} = ? AND {$userField} = ? AND ({$statusField} = ? OR {$statusField} = 'در انتظار')
                            ");
                            $cancelStmt->execute([$cancelledStatus, $requestId, $userId, $pendingStatus]);
                $cancelStmt->execute([$requestId, $userId]);
                if ($cancelStmt->rowCount() > 0) {
                    $successMessage = 'درخواست شما لغو شد.';
                } else {
                    $errorMessage = 'امکان لغو این درخواست وجود ندارد.';
                }
            }
        } catch (Throwable $e) {
            error_log("Error canceling request: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $errorMessage = 'خطا در لغو درخواست: ' . $e->getMessage();
        }
    }
}

// Check which table exists - MUST check requests first (same as send_listing_request.php)
$requests = [];
$tableName = null;
$idField = null;
$houseField = null;
$userField = null;
$statusField = null;

try {
    $requestsExists = $conn->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0;
    $matchesExists = $conn->query("SHOW TABLES LIKE 'matches'")->rowCount() > 0;
    
    // Use same logic as send_listing_request.php - check requests first
    if ($requestsExists) {
        $tableName = 'requests';
        $idField = 'request_id';
        $houseField = 'listing_id';
        $userField = 'sender_user_id';
        $statusField = 'request_status';
    } elseif ($matchesExists) {
        $tableName = 'matches';
        $idField = 'id';
        $houseField = 'house_id';
        $userField = 'user_id';
        $statusField = 'status';
    } else {
        // No table exists
        $requests = [];
        error_log("No requests or matches table found in database");
    }
    
    if ($tableName) {
        $requestsStmt = $conn->prepare("
            SELECT 
                r.{$idField} as id,
                r.message,
                r.move_in_date,
                r.duration,
                r.budget,
                r.{$statusField} as status,
                r.created_at,
                r.updated_at,
                h.id AS house_id,
                h.title,
                h.city,
                h.province,
                h.price,
                h.gender,
                h.available_capacity,
                u.full_name AS owner_name,
                u.id AS owner_id
            FROM {$tableName} r
            JOIN houses h ON r.{$houseField} = h.id
            JOIN users u ON h.user_id = u.id
            WHERE r.{$userField} = ?
            ORDER BY FIELD(r.{$statusField},'pending','approved','rejected','cancelled','در انتظار','تایید شده','رد شده','لغو شده'), r.created_at DESC
        ");
        $requestsStmt->execute([$userId]);
        $requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug logging
        error_log("=== my_requests.php Debug ===");
        error_log("Table used: {$tableName}");
        error_log("User ID: {$userId}");
        error_log("Requests found: " . count($requests));
        if (count($requests) > 0) {
            error_log("First request: " . print_r($requests[0], true));
        }
        error_log("===========================");
    }
} catch (PDOException $e) {
    error_log("Error fetching requests: " . $e->getMessage());
    error_log("SQL Error Info: " . print_r($e->errorInfo(), true));
    $requests = [];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>درخواست‌های من | هم‌اتاقی</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg,#050912,#0b141f);
            color: #fff;
        }
        .top-nav {
            background: rgba(11,20,25,0.95);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            color: #ffab00;
            text-decoration: none;
            font-size: 20px;
        }
        .nav-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .nav-btn {
            padding: 8px 14px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.08);
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px 60px;
        }
        .card {
            background: rgba(15,26,33,0.9);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
        }
        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .alert.success {
            background: rgba(76,175,80,0.2);
            border: 1px solid rgba(76,175,80,0.35);
        }
        .alert.error {
            background: rgba(244,67,54,0.2);
            border: 1px solid rgba(244,67,54,0.35);
        }
        .request-card {
            background: rgba(12,18,28,0.95);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 18px;
        }
        .request-header {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .status-badge {
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
        }
        .status-pending { background: rgba(255,193,7,0.2); color:#ffc107; }
        .status-accepted { background: rgba(76,175,80,0.2); color:#4caf50; }
        .status-rejected { background: rgba(244,67,54,0.2); color:#ff5252; }
        .status-cancelled { background: rgba(158,158,158,0.2); color:#e0e0e0; }
        .request-body {
            display: grid;
            grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
            gap: 15px;
        }
        .request-body div {
            background: rgba(255,255,255,0.04);
            border-radius: 12px;
            padding: 12px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .request-body span {
            display: block;
            font-size: 13px;
            color: #aeb4c4;
            margin-bottom: 6px;
        }
        .request-body strong {
            font-size: 15px;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }
        .btn {
            border: none;
            border-radius: 12px;
            padding: 10px 18px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary {
            background: linear-gradient(135deg,#ffab00,#ff8c00);
            color: #111;
        }
        .btn-secondary {
            background: rgba(255,255,255,0.12);
            color: #fff;
        }
        .btn-danger {
            background: rgba(244,67,54,0.9);
            color: #fff;
        }
        h1 {
            margin-bottom: 10px;
        }
        p.subtitle {
            color: #aeb4c4;
            margin-top: 0;
        }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="nav-container">
            <a href="dashboard.php" class="nav-brand">
                <i class="fas fa-home-heart"></i>
                هم‌اتاقی
            </a>
            <div class="nav-actions">
                <a href="available_houses.php" class="nav-btn"><i class="fas fa-search"></i>جستجوی خانه</a>
                <a href="favorites.php" class="nav-btn"><i class="fas fa-heart"></i>علاقه‌مندی‌ها</a>
                <a href="my_requests.php" class="nav-btn"><i class="fas fa-envelope-open-text"></i>درخواست‌های من</a>
                <a href="messages.php" class="nav-btn"><i class="fas fa-comments"></i>پیام‌ها</a>
                <a href="../logout.php" class="nav-btn"><i class="fas fa-sign-out-alt"></i>خروج</a>
            </div>
        </div>
    </nav>
    <div class="container">
        <div class="card">
            <h1>درخواست‌های من</h1>
            <p class="subtitle">وضعیت تمامی درخواست‌های ارسال‌شده برای خانه‌های مختلف را در این بخش دنبال کنید.</p>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert success"><?= htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert error"><?= htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <?php 
        // Debug mode
        $debugMode = isset($_GET['debug']);
        if ($debugMode): 
        ?>
            <div class="alert" style="background: rgba(255,152,0,0.1); border: 1px solid rgba(255,152,0,0.3); color: #ff9800;">
                <strong>🔍 Debug Mode:</strong><br>
                جدول استفاده شده: <strong><?= htmlspecialchars($tableName ?? 'هیچکدام'); ?></strong><br>
                User ID: <strong><?= $userId; ?></strong><br>
                Requests table exists: <strong><?= ($requestsExists ?? false) ? 'بله' : 'خیر'; ?></strong><br>
                Matches table exists: <strong><?= ($matchesExists ?? false) ? 'بله' : 'خیر'; ?></strong><br>
                تعداد درخواست‌های یافت شده: <strong><?= count($requests); ?></strong><br>
                <a href="debug_requests.php?user_id=<?= $userId; ?>" style="color: #4caf50; margin-top: 10px; display: inline-block;">مشاهده جزئیات بیشتر</a>
            </div>
        <?php endif; ?>

        <?php if (empty($requests)): ?>
            <div class="card">هنوز درخواستی ارسال نکرده‌اید. از بخش «جستجوی خانه» شروع کنید.</div>
        <?php else: ?>
            <?php 
            // Helper function to convert English status to Farsi
            function getStatusFarsi($status) {
                $statusMap = [
                    'pending' => 'در انتظار',
                    'approved' => 'تایید شده',
                    'rejected' => 'رد شده',
                    'cancelled' => 'لغو شده'
                ];
                return $statusMap[$status] ?? $status;
            }
            
            foreach ($requests as $request): 
                $rawStatus = $request['status'] ?? '';
                $statusFarsi = getStatusFarsi($rawStatus);
                $statusClass = $statuses[$rawStatus] ?? ($statuses[$statusFarsi] ?? 'pending');
                $statusCss = 'status-' . $statusClass;
                $canCancel = ($rawStatus === 'pending' || $rawStatus === 'در انتظار');
                $canMessage = in_array($rawStatus, ['pending', 'approved', 'در انتظار', 'تایید شده'], true);
            ?>
                <div class="request-card">
                    <div class="request-header">
                        <div>
                            <strong><?= htmlspecialchars($request['title'] ?? ''); ?> — <?= htmlspecialchars(($request['city'] ?? '') . '، ' . ($request['province'] ?? '')); ?></strong>
                            <div style="color:#aeb4c4;font-size:13px;">صاحب‌خانه: <?= htmlspecialchars($request['owner_name'] ?? ''); ?></div>
                        </div>
                        <span class="status-badge <?= $statusCss; ?>"><?= htmlspecialchars($statusFarsi); ?></span>
                    </div>
                    <div class="request-body">
                        <div>
                            <span>تاریخ ارسال</span>
                            <strong><?= !empty($request['created_at']) ? date('Y/m/d', strtotime($request['created_at'])) : '—'; ?></strong>
                        </div>
                        <div>
                            <span>تاریخ ورود پیشنهادی</span>
                            <strong><?= $request['move_in_date'] ? date('Y/m/d', strtotime($request['move_in_date'])) : '—'; ?></strong>
                        </div>
                        <div>
                            <span>مدت اقامت</span>
                            <strong><?= htmlspecialchars($request['duration']); ?></strong>
                        </div>
                        <div>
                            <span>بودجه</span>
                            <strong><?= $request['budget'] ? number_format((int)$request['budget']) . ' تومان' : '—'; ?></strong>
                        </div>
                    </div>
                    <p style="margin-top:15px;color:#d7dae5;line-height:1.7;">
                        <span style="display:block;color:#aeb4c4;font-size:13px;">پیام شما</span>
                        <?= nl2br(htmlspecialchars($request['message'] ?? '')); ?>
                    </p>
                    <div class="actions">
                        <a href="house_details.php?id=<?= (int)($request['house_id'] ?? 0); ?>" class="btn btn-secondary">
                            <i class="fas fa-eye"></i>
                            مشاهده آگهی
                        </a>
                        <?php if ($canMessage): ?>
                            <a href="messages.php?house_id=<?= (int)($request['house_id'] ?? 0); ?>&with=<?= (int)($request['owner_id'] ?? 0); ?>" class="btn btn-primary">
                                <i class="fas fa-comments"></i>
                                پیام به صاحب‌خانه
                            </a>
                        <?php endif; ?>
                        <?php if ($canCancel): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="cancel_request" value="<?= (int)$request['id']; ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('آیا از لغو این درخواست مطمئن هستید؟');">
                                    <i class="fas fa-ban"></i>
                                    لغو درخواست
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>

