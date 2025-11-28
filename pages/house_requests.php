<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Get unread count for header
$unreadCount = 0;
try {
    if (isset($_SESSION['user_id'])) {
        $unreadStmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
        $unreadStmt->execute([$_SESSION['user_id']]);
        $unreadCount = (int)$unreadStmt->fetchColumn();
    }
} catch (PDOException $e) {
    $unreadCount = 0;
}

// بررسی ورود کاربر
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// دریافت اطلاعات کاربر
try {
    $user_stmt = $conn->prepare("SELECT full_name, usertype FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_user || ($current_user['usertype'] ?? '') !== 'صاحب‌خانه') {
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching user: " . $e->getMessage());
    header("Location: dashboard.php");
    exit();
}

// پردازش اقدامات روی درخواست‌ها
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $action = $_POST['action'] ?? '';
    
    if ($request_id > 0 && $action) {
        try {
            // Check which table exists (requests or matches)
            $requestsExists = $conn->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0;
            $matchesExists = $conn->query("SHOW TABLES LIKE 'matches'")->rowCount() > 0;
            
            if (!$requestsExists && !$matchesExists) {
                $error_message = "جدول درخواست‌ها وجود ندارد.";
            } else {
                $tableName = $requestsExists ? 'requests' : 'matches';
                $idField = $requestsExists ? 'request_id' : 'id';
                $houseField = $requestsExists ? 'listing_id' : 'house_id';
                $userField = $requestsExists ? 'sender_user_id' : 'user_id';
                $statusField = $requestsExists ? 'request_status' : 'status';
                
                // Check request ownership - for requests table, check receiver_owner_id directly
                if ($requestsExists) {
                    $check_stmt = $conn->prepare("
                        SELECT r.*, h.title as house_title, h.available_capacity, u.full_name as applicant_name 
                        FROM {$tableName} r 
                        JOIN houses h ON r.{$houseField} = h.id 
                        JOIN users u ON r.{$userField} = u.id
                        WHERE r.{$idField} = ? AND r.receiver_owner_id = ?
                    ");
                    $check_stmt->execute([$request_id, $user_id]);
                } else {
                    $check_stmt = $conn->prepare("
                        SELECT r.*, h.title as house_title, h.available_capacity, u.full_name as applicant_name 
                        FROM {$tableName} r 
                        JOIN houses h ON r.{$houseField} = h.id 
                        JOIN users u ON r.{$userField} = u.id
                        WHERE r.{$idField} = ? AND h.user_id = ?
                    ");
                    $check_stmt->execute([$request_id, $user_id]);
                }
                $request_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$request_data) {
                    $error_message = "درخواست مورد نظر یافت نشد یا شما دسترسی لازم را ندارید";
                } else {
                    // Map status values - requests table uses English, matches uses Farsi
                    if ($requestsExists) {
                        $pendingStatus = 'pending';
                        $acceptedStatus = 'approved';
                        $rejectedStatus = 'rejected';
                    } else {
                        $pendingStatus = 'در انتظار';
                        $acceptedStatus = 'تایید شده';
                        $rejectedStatus = 'رد شده';
                    }
                    
                    $new_status = $action === 'accept' ? $acceptedStatus : $rejectedStatus;
                    try {
                        $conn->beginTransaction();
                        if ($action === 'accept') {
                            if ((int)($request_data['available_capacity'] ?? 0) <= 0) {
                                throw new RuntimeException('ظرفیت این خانه تکمیل شده است.');
                            }
                            // Update query - handle empty status as pending
                            if ($requestsExists) {
                                // For requests table, check for pending, empty, or null status
                                $update_stmt = $conn->prepare("UPDATE {$tableName} SET {$statusField} = ?, updated_at = NOW() WHERE {$idField} = ? AND ({$statusField} = ? OR {$statusField} = '' OR {$statusField} IS NULL OR TRIM({$statusField}) = '')");
                            } else {
                                // For matches table, only check for pending status
                                $update_stmt = $conn->prepare("UPDATE {$tableName} SET {$statusField} = ?, updated_at = NOW() WHERE {$idField} = ? AND {$statusField} = ?");
                            }
                            $update_stmt->execute([$new_status, $request_id, $pendingStatus]);
                            if ($update_stmt->rowCount() === 0) {
                                throw new RuntimeException('امکان تایید این درخواست وجود ندارد. وضعیت فعلی: ' . ($request_data[$statusField] ?? 'خالی'));
                            }
                            $houseIdField = $requestsExists ? 'listing_id' : 'house_id';
                            $houseId = $request_data[$houseIdField] ?? $request_data['house_id'] ?? 0;
                            $capacityStmt = $conn->prepare("UPDATE houses SET available_capacity = GREATEST(available_capacity - 1, 0) WHERE id = ? AND available_capacity > 0");
                            $capacityStmt->execute([$houseId]);
                            if ($capacityStmt->rowCount() === 0) {
                                throw new RuntimeException('ظرفیت کافی برای تایید وجود ندارد.');
                            }
                        } else {
                            // Update query for reject - handle empty status as pending
                            if ($requestsExists) {
                                // For requests table, check for pending, empty, or null status
                                $update_stmt = $conn->prepare("UPDATE {$tableName} SET {$statusField} = ?, updated_at = NOW() WHERE {$idField} = ? AND ({$statusField} = ? OR {$statusField} = '' OR {$statusField} IS NULL OR TRIM({$statusField}) = '')");
                            } else {
                                // For matches table, only check for pending status
                                $update_stmt = $conn->prepare("UPDATE {$tableName} SET {$statusField} = ?, updated_at = NOW() WHERE {$idField} = ? AND {$statusField} = ?");
                            }
                            $update_stmt->execute([$new_status, $request_id, $pendingStatus]);
                            if ($update_stmt->rowCount() === 0) {
                                throw new RuntimeException('امکان رد این درخواست وجود ندارد. وضعیت فعلی: ' . ($request_data[$statusField] ?? 'خالی'));
                            }
                        }

                        // Create notification if table exists
                        try {
                            $notificationsExists = $conn->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0;
                            if ($notificationsExists) {
                                $notifStmt = $conn->prepare("
                                    INSERT INTO notifications (user_id, title, message, type, related_id, created_at)
                                    VALUES (?, ?, ?, 'request_response', ?, NOW())
                                ");
                                $notifTitle = $action === 'accept' ? 'درخواست تایید شد' : 'درخواست رد شد';
                                $notifMessage = ($action === 'accept')
                                    ? "درخواست شما برای خانه «{$request_data['house_title']}» تایید شد."
                                    : "درخواست شما برای خانه «{$request_data['house_title']}» رد شد.";
                                $houseIdField = $requestsExists ? 'listing_id' : 'house_id';
                                $houseId = $request_data[$houseIdField] ?? $request_data['house_id'] ?? 0;
                                $userIdField = $requestsExists ? 'sender_user_id' : 'user_id';
                                $applicantId = $request_data[$userIdField] ?? $request_data['user_id'] ?? 0;
                                $notifStmt->execute([
                                    $applicantId,
                                    $notifTitle,
                                    $notifMessage,
                                    $houseId
                                ]);
                            }
                        } catch (PDOException $e) {
                            error_log("Error creating notification: " . $e->getMessage());
                            // Continue - notifications are optional
                        }

                        $conn->commit();
                        $success_message = "درخواست اقامت {$request_data['applicant_name']} برای خانه «{$request_data['house_title']}» با موفقیت {$new_status} شد";
                    } catch (Throwable $e) {
                        if ($conn->inTransaction()) {
                            $conn->rollBack();
                        }
                        $error_message = $e->getMessage();
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error processing request: " . $e->getMessage());
            $error_message = "خطا در پردازش درخواست: " . $e->getMessage();
        }
    }
}

// نمایش پیام موفقیت از پارامتر URL
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8');
}

// Check which table exists and fetch requests
$pending_requests = [];
$history_requests = [];
$houseId = isset($_GET['house_id']) ? (int)$_GET['house_id'] : 0;

try {
    $requestsExists = $conn->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0;
    $matchesExists = $conn->query("SHOW TABLES LIKE 'matches'")->rowCount() > 0;
    
    if ($requestsExists) {
        $tableName = 'requests';
        $houseField = 'listing_id';
        $userField = 'sender_user_id';
        $idField = 'request_id';
        $statusField = 'request_status'; // requests table uses request_status
    } elseif ($matchesExists) {
        $tableName = 'matches';
        $houseField = 'house_id';
        $userField = 'user_id';
        $idField = 'id';
        $statusField = 'status'; // matches table uses status
    } else {
        // No requests table exists
        $pending_requests = [];
        $history_requests = [];
    }
    
    if ($requestsExists || $matchesExists) {
        // Get pending requests - use correct status field and check receiver_owner_id for requests table
        if ($requestsExists) {
            // For requests table, check receiver_owner_id directly (it's NOT NULL in DB)
            // This is the primary way to identify requests for the owner
            // Also handle empty/null status as pending (for existing records)
            // Check for pending status (English), Farsi pending, or empty string
            $whereClause = "r.receiver_owner_id = ? AND (r.{$statusField} = 'pending' OR r.{$statusField} = 'در انتظار' OR r.{$statusField} = '' OR r.{$statusField} IS NULL OR TRIM(r.{$statusField}) = '')";
            if ($houseId > 0) {
                $whereClause .= " AND r.{$houseField} = ?";
            }
        } else {
            // For matches table, only check h.user_id
            $whereClause = "h.user_id = ? AND r.{$statusField} = 'در انتظار'";
            if ($houseId > 0) {
                $whereClause .= " AND r.{$houseField} = ?";
            }
        }
        
        $pending_requests_stmt = $conn->prepare("
            SELECT 
                r.*,
                r.{$idField} as request_id,
                r.{$houseField} as house_id,
                r.{$userField} as user_id,
                r.{$statusField} as status,
                h.title as house_title,
                h.city as house_city,
                h.province as house_province,
                h.price as house_price,
                h.capacity,
                h.available_capacity,
                u.full_name as applicant_name,
                u.personality_type as applicant_personality,
                u.gender as applicant_gender,
                u.user_score as applicant_score
            FROM {$tableName} r
            JOIN houses h ON r.{$houseField} = h.id
            JOIN users u ON r.{$userField} = u.id
            WHERE {$whereClause}
            ORDER BY r.created_at DESC
        ");
        
        if ($requestsExists) {
            // For requests table, pass user_id once (for receiver_owner_id)
            if ($houseId > 0) {
                $pending_requests_stmt->execute([$user_id, $houseId]);
            } else {
                $pending_requests_stmt->execute([$user_id]);
            }
        } else {
            // For matches table, only pass user_id once
            if ($houseId > 0) {
                $pending_requests_stmt->execute([$user_id, $houseId]);
            } else {
                $pending_requests_stmt->execute([$user_id]);
            }
        }
        $pending_requests = $pending_requests_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug logging
        error_log("=== house_requests.php Debug ===");
        error_log("Table used: {$tableName}");
        error_log("User ID (owner): {$user_id}");
        error_log("House ID filter: " . ($houseId > 0 ? $houseId : 'all'));
        error_log("Pending requests found: " . count($pending_requests));
        if (count($pending_requests) > 0) {
            error_log("First pending request: " . print_r($pending_requests[0], true));
        }
        error_log("===========================");
        
        // Get history requests - use correct status field and check receiver_owner_id for requests table
        $history_requests = [];
        if ($requestsExists) {
            // For requests table, check receiver_owner_id directly (it's NOT NULL in DB)
            // Handle both English (from DB) and Farsi (for compatibility) status values
            $historyWhereClause = "r.receiver_owner_id = ? AND (r.{$statusField} IN ('approved', 'rejected', 'cancelled') OR r.{$statusField} IN ('تایید شده', 'رد شده','لغو شده'))";
            if ($houseId > 0) {
                $historyWhereClause .= " AND r.{$houseField} = ?";
            }
        } else {
            // For matches table, only check h.user_id
            $historyWhereClause = "h.user_id = ? AND r.{$statusField} IN ('تایید شده', 'رد شده','لغو شده')";
            if ($houseId > 0) {
                $historyWhereClause .= " AND r.{$houseField} = ?";
            }
        }
        
        $history_requests_stmt = $conn->prepare("
            SELECT 
                r.*,
                r.{$idField} as request_id,
                r.{$houseField} as house_id,
                r.{$userField} as user_id,
                r.{$statusField} as status,
                h.title as house_title,
                h.city as house_city,
                h.province as house_province,
                h.price as house_price,
                h.capacity,
                h.available_capacity,
                u.full_name as applicant_name,
                u.personality_type as applicant_personality,
                u.gender as applicant_gender,
                u.user_score as applicant_score
            FROM {$tableName} r
            JOIN houses h ON r.{$houseField} = h.id
            JOIN users u ON r.{$userField} = u.id
            WHERE {$historyWhereClause}
            ORDER BY r.updated_at DESC
            LIMIT 20
        ");
        
        if ($requestsExists) {
            // For requests table, pass user_id once (for receiver_owner_id)
            if ($houseId > 0) {
                $history_requests_stmt->execute([$user_id, $houseId]);
            } else {
                $history_requests_stmt->execute([$user_id]);
            }
        } else {
            // For matches table, only pass user_id once
            if ($houseId > 0) {
                $history_requests_stmt->execute([$user_id, $houseId]);
            } else {
                $history_requests_stmt->execute([$user_id]);
            }
        }
        $history_requests = $history_requests_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching requests: " . $e->getMessage());
    $pending_requests = [];
    $history_requests = [];
}

// Calculate stats from fetched data
$stats = [
    'pending' => count($pending_requests),
    'accepted' => 0,
    'rejected' => 0,
    'cancelled' => 0,
    'total' => 0
];

foreach ($history_requests as $request) {
    $status = $request['status'] ?? '';
    if ($status === 'تایید شده') $stats['accepted']++;
    if ($status === 'رد شده') $stats['rejected']++;
    if ($status === 'لغو شده') $stats['cancelled']++;
}

$stats['total'] = $stats['pending'] + $stats['accepted'] + $stats['rejected'] + $stats['cancelled'];

// تابع برای نمایش وضعیت به فارسی
function getStatusText($status) {
    // Handle empty/null status as pending
    if (empty($status) || $status === '' || $status === null) {
        return 'در انتظار';
    }
    
    $statusMap = [
        'pending' => 'در انتظار',
        'approved' => 'تایید شده',
        'rejected' => 'رد شده',
        'cancelled' => 'لغو شده'
    ];
    return $statusMap[$status] ?? $status; // اگر فارسی بود، همان را برگردان
}

// تابع برای نمایش امتیاز کاربر
function getScoreStars($score) {
    $stars = '';
    $fullStars = floor($score);
    $halfStar = ($score - $fullStars) >= 0.5;
    
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $fullStars) {
            $stars .= '<i class="fas fa-star" style="color: #ffab00;"></i>';
        } elseif ($i == $fullStars + 1 && $halfStar) {
            $stars .= '<i class="fas fa-star-half-alt" style="color: #ffab00;"></i>';
        } else {
            $stars .= '<i class="far fa-star" style="color: #ffab00;"></i>';
        }
    }
    return $stars;
}

// تابع برای ایجاد کلاس CSS برای وضعیت
function getStatusClass($status) {
    // Handle empty/null status as pending
    if (empty($status) || $status === '' || $status === null) {
        return 'status-pending';
    }
    
    $statuses = [
        'pending' => 'status-pending',
        'approved' => 'status-accepted',
        'rejected' => 'status-rejected',
        'cancelled' => 'status-cancelled',
        'در انتظار' => 'status-pending',
        'تایید شده' => 'status-accepted',
        'رد شده' => 'status-rejected',
        'لغو شده' => 'status-cancelled'
    ];
    return $statuses[$status] ?? 'status-pending';
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت درخواست‌ها | هم‌اتاقی</title>
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

    .nav-btn.logout:hover {
        background: rgba(244, 67, 54, 0.2);
        box-shadow: 0 4px 15px rgba(244, 67, 54, 0.3);
    }

    /* محتوای اصلی */
    .requests-container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px;
    }

    /* هدر صفحه */
    .page-header {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
        backdrop-filter: blur(15px);
        position: relative;
        overflow: hidden;
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

    /* پیام‌های وضعیت */
    .alert {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideInUp 0.5s ease-out;
    }

    .alert-success {
        background: rgba(76, 175, 80, 0.15);
        color: var(--success);
        border: 1px solid rgba(76, 175, 80, 0.3);
    }

    .alert-error {
        background: rgba(244, 67, 54, 0.15);
        color: var(--danger);
        border: 1px solid rgba(244, 67, 54, 0.3);
    }

    .alert i {
        font-size: 1.2rem;
    }

    /* آمار */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 15px;
        padding: 25px;
        text-align: center;
        backdrop-filter: blur(15px);
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-icon {
        font-size: 2.5rem;
        margin-bottom: 15px;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .stat-label {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    /* بخش‌بندی */
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 40px 0 20px 0;
        padding-bottom: 15px;
        border-bottom: 2px solid rgba(255, 171, 0, 0.3);
    }

    .section-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-light);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-badge {
        background: var(--gold);
        color: var(--navy-black);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
    }

    .toggle-history {
        background: rgba(139, 92, 246, 0.2);
        color: var(--purple);
        border: 1px solid rgba(139, 92, 246, 0.3);
        padding: 8px 16px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: 'Tajawal', sans-serif;
        font-weight: 600;
    }

    .toggle-history:hover {
        background: rgba(139, 92, 246, 0.3);
        transform: translateY(-2px);
    }

    /* لیست درخواست‌ها */
    .requests-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .request-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 20px;
        padding: 25px;
        backdrop-filter: blur(15px);
        transition: all 0.3s ease;
    }

    .request-card:hover {
        border-color: var(--gold);
    }

    .request-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .request-info h3 {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-light);
        margin-bottom: 8px;
    }

    .request-house {
        color: var(--text-muted);
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .request-status {
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 14px;
        position: relative;
        padding-left: 25px;
    }

    .request-status:before {
        content: '';
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        width: 8px;
        height: 8px;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    .status-pending {
        background: rgba(255, 152, 0, 0.2);
        color: var(--warning);
        border: 1px solid rgba(255, 152, 0, 0.3);
    }

    .status-pending:before {
        background: var(--warning);
    }

    .status-accepted {
        background: rgba(76, 175, 80, 0.2);
        color: var(--success);
        border: 1px solid rgba(76, 175, 80, 0.3);
    }

    .status-accepted:before {
        background: var(--success);
    }

    .status-rejected {
        background: rgba(244, 67, 54, 0.2);
        color: var(--danger);
        border: 1px solid rgba(244, 67, 54, 0.3);
    }

    .status-rejected:before {
        background: var(--danger);
    }

    .status-cancelled {
        background: rgba(158, 158, 158, 0.2);
        color: #e0e0e0;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .status-cancelled:before {
        background: #bdbdbd;
    }

    .request-content {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 25px;
        margin-bottom: 20px;
    }

    .applicant-info {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .applicant-header {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .applicant-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--gold), var(--purple));
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--navy-black);
        font-weight: 700;
        font-size: 1.2rem;
    }

    .applicant-details h4 {
        color: var(--text-light);
        margin-bottom: 5px;
        font-size: 1.1rem;
    }

    .applicant-details p {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .applicant-score {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    .applicant-personality {
        background: rgba(139, 92, 246, 0.1);
        color: var(--purple);
        padding: 8px 12px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
        text-align: center;
        border: 1px solid rgba(139, 92, 246, 0.3);
    }

    .request-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-top: 15px;
    }

    .detail-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .detail-item i {
        color: var(--gold);
        width: 16px;
    }

    .request-message {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 20px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .message-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        color: var(--text-light);
        font-weight: 600;
    }

    .message-content {
        color: var(--text-muted);
        line-height: 1.6;
        white-space: pre-line;
    }

    .request-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        padding-top: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .action-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
        font-family: 'Tajawal', sans-serif;
        position: relative;
        overflow: hidden;
        text-decoration: none;
    }

    .action-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }

    .action-btn.loading {
        color: transparent;
    }

    .action-btn.loading::after {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        top: 50%;
        left: 50%;
        margin: -10px 0 0 -10px;
        border: 2px solid transparent;
        border-top: 2px solid currentColor;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    .accept-btn {
        background: linear-gradient(135deg, var(--success), #45a049);
        color: white;
    }

    .reject-btn {
        background: rgba(244, 67, 54, 0.2);
        color: #ff5252;
        border: 1px solid rgba(244, 67, 54, 0.3);
    }

    .message-btn {
        background: rgba(255, 171, 0, 0.2);
        color: var(--gold);
        border: 1px solid rgba(255, 171, 0, 0.3);
    }

    .action-btn:hover:not(:disabled) {
        transform: translateY(-2px);
    }

    /* پیام عدم وجود درخواست */
    .no-requests {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 20px;
        backdrop-filter: blur(15px);
    }

    .no-requests i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .no-requests h3 {
        font-size: 1.5rem;
        margin-bottom: 10px;
        color: var(--text-light);
    }

    /* سوابق درخواست‌ها */
    .history-section {
        display: none;
        margin-top: 40px;
    }

    .history-section.show {
        display: block;
    }

    .history-item {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 15px;
        backdrop-filter: blur(15px);
        transition: all 0.3s ease;
    }

    .history-item:hover {
        border-color: var(--gold);
    }

    .history-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .history-info h4 {
        color: var(--text-light);
        margin-bottom: 5px;
        font-size: 16px;
    }

    .history-info p {
        color: var(--text-muted);
        font-size: 14px;
    }

    .history-date {
        color: var(--text-muted);
        font-size: 12px;
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

    @keyframes pulse {
        0% { opacity: 0.7; }
        50% { opacity: 1; }
        100% { opacity: 0.7; }
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .page-header, .stat-card, .request-card {
        animation: slideInUp 0.6s ease-out;
    }

    /* ریسپانسیو */
    @media (max-width: 768px) {
        .request-content {
            grid-template-columns: 1fr;
        }
        
        .request-header {
            flex-direction: column;
            gap: 15px;
        }
        
        .request-actions {
            flex-direction: column;
        }
        
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        
        .nav-container {
            flex-direction: column;
            gap: 15px;
        }
        
        .section-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
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
            <a href="add_house.php" class="nav-btn primary">
                <i class="fas fa-plus"></i>
                ثبت خانه جدید
            </a>
            <a href="my_houses.php" class="nav-btn">
                <i class="fas fa-house-user"></i>
                خانه‌های من
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

<!-- محتوای اصلی -->
<div class="requests-container">
    <!-- هدر صفحه -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-text">
                <h1>📬 مدیریت درخواست‌های اقامت</h1>
                <p>درخواست‌های ارسالی برای خانه‌های شما</p>
            </div>
        </div>
    </div>

    <!-- نمایش پیام‌ها -->
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- آمار -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="color: var(--info);">
                <i class="fas fa-inbox"></i>
            </div>
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">کل درخواست‌ها</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="color: var(--warning);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-number"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">در انتظار بررسی</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="color: var(--success);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-number"><?php echo $stats['accepted']; ?></div>
            <div class="stat-label">تأیید شده</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="color: var(--danger);">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-number"><?php echo $stats['rejected']; ?></div>
            <div class="stat-label">رد شده</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="color: #bdbdbd;">
                <i class="fas fa-ban"></i>
            </div>
            <div class="stat-number"><?php echo $stats['cancelled'] ?? 0; ?></div>
            <div class="stat-label">لغو شده</div>
        </div>
    </div>

    <!-- درخواست‌های در انتظار -->
    <div class="section-header">
        <div class="section-title">
            <i class="fas fa-clock"></i>
            درخواست‌های در انتظار بررسی
            <span class="section-badge"><?php echo $stats['pending']; ?></span>
        </div>
    </div>

    <?php if (!empty($pending_requests)): ?>
        <div class="requests-list">
            <?php foreach($pending_requests as $request): ?>
                <div class="request-card">
                    <div class="request-header">
                        <div class="request-info">
                            <h3>درخواست اقامت</h3>
                            <div class="request-house">
                                <i class="fas fa-home"></i>
                                <?php echo htmlspecialchars($request['house_title'] ?? ''); ?> - 
                                <?php echo htmlspecialchars(($request['house_city'] ?? '') . '، ' . ($request['house_province'] ?? '')); ?>
                            </div>
                        </div>
                        <div class="request-status <?php echo getStatusClass($request['status'] ?? ''); ?>">
                            <?php echo getStatusText($request['status'] ?? ''); ?>
                        </div>
                    </div>

                    <div class="request-content">
                        <div class="applicant-info">
                            <div class="applicant-header">
                                <div class="applicant-avatar">
                                    <?php echo mb_substr($request['applicant_name'] ?? '?', 0, 1); ?>
                                </div>
                                <div class="applicant-details">
                                    <h4><?php echo htmlspecialchars($request['applicant_name'] ?? ''); ?></h4>
                                    <p><?php echo htmlspecialchars($request['applicant_gender'] ?? ''); ?></p>
                                    <?php if(!empty($request['applicant_score'])): ?>
                                    <div class="applicant-score">
                                        <span>امتیاز:</span>
                                        <?php echo getScoreStars((float)$request['applicant_score']); ?>
                                        <span>(<?php echo number_format((float)$request['applicant_score'], 1); ?>)</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if(!empty($request['applicant_personality'])): ?>
                                <div class="applicant-personality">
                                    <i class="fas fa-brain"></i>
                                    <?php echo htmlspecialchars($request['applicant_personality']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="request-details">
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo $request['duration']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span><?php echo !empty($request['move_in_date']) ? date('Y/m/d', strtotime($request['move_in_date'])) : '—'; ?></span>
                                </div>
                                <?php if(!empty($request['budget'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span><?php echo number_format((int)$request['budget']) . ' تومان'; ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <i class="fas fa-users"></i>
                                    <span>ظرفیت: <?php echo (int)($request['available_capacity'] ?? 0); ?> از <?php echo (int)($request['capacity'] ?? 0); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="request-message">
                            <div class="message-header">
                                <i class="fas fa-comment-dots"></i>
                                پیام متقاضی
                            </div>
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($request['message'] ?? '')); ?>
                            </div>
                        </div>
                    </div>

                    <!-- بخش دکمه‌های اقدام - اصلاح شده -->
                    <div class="request-actions">
                        <a href="messages.php?house_id=<?php echo $request['house_id']; ?>&with=<?php echo $request['user_id']; ?>" class="action-btn message-btn">
                            <i class="fas fa-comments"></i>
                            ارسال پیام
                        </a>
                        
                        <!-- فرم رد درخواست -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="request_id" value="<?php echo (int)($request['request_id'] ?? $request['id'] ?? 0); ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="action-btn reject-btn" 
                                    onclick="return confirm('آیا از رد درخواست <?php echo htmlspecialchars($request['applicant_name'] ?? ''); ?> برای خانه «<?php echo htmlspecialchars($request['house_title'] ?? ''); ?>» مطمئن هستید؟')">
                                <i class="fas fa-times"></i>
                                رد درخواست
                            </button>
                        </form>
                        
                        <!-- فرم تأیید درخواست -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="request_id" value="<?php echo (int)($request['request_id'] ?? $request['id'] ?? 0); ?>">
                            <input type="hidden" name="action" value="accept">
                            <button type="submit" class="action-btn accept-btn"
                                    onclick="return confirm('آیا از تأیید درخواست <?php echo htmlspecialchars($request['applicant_name'] ?? ''); ?> برای خانه «<?php echo htmlspecialchars($request['house_title'] ?? ''); ?>» مطمئن هستید؟')">
                                <i class="fas fa-check"></i>
                                تأیید درخواست
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-requests">
            <i class="fas fa-inbox"></i>
            <h3>هیچ درخواست در انتظاری ندارید!</h3>
            <p>وقتی کاربران برای خانه‌های شما درخواست ارسال کنند، اینجا نمایش داده می‌شوند.</p>
        </div>
    <?php endif; ?>

    <!-- دکمه نمایش سوابق -->
    <?php if (!empty($history_requests)): ?>
    <div class="section-header">
        <div class="section-title">
            <i class="fas fa-history"></i>
            سوابق درخواست‌ها
        </div>
        <button class="toggle-history" onclick="toggleHistory()">
            <i class="fas fa-eye"></i>
            نمایش سوابق
        </button>
    </div>

    <!-- سوابق درخواست‌ها -->
    <div class="history-section" id="historySection">
        <div class="requests-list">
            <?php foreach($history_requests as $request): ?>
                <div class="request-card">
                    <div class="request-header">
                        <div class="request-info">
                            <h3>درخواست اقامت</h3>
                            <div class="request-house">
                                <i class="fas fa-home"></i>
                                <?php echo htmlspecialchars($request['house_title'] ?? ''); ?> - 
                                <?php echo htmlspecialchars(($request['house_city'] ?? '') . '، ' . ($request['house_province'] ?? '')); ?>
                            </div>
                        </div>
                        <div class="request-status <?php echo getStatusClass($request['status'] ?? ''); ?>">
                            <?php echo getStatusText($request['status'] ?? ''); ?>
                        </div>
                    </div>

                    <div class="request-content">
                        <div class="applicant-info">
                            <div class="applicant-header">
                                <div class="applicant-avatar">
                                    <?php echo mb_substr($request['applicant_name'] ?? '?', 0, 1); ?>
                                </div>
                                <div class="applicant-details">
                                    <h4><?php echo htmlspecialchars($request['applicant_name'] ?? ''); ?></h4>
                                    <p><?php echo htmlspecialchars($request['applicant_gender'] ?? ''); ?></p>
                                    <?php if(!empty($request['applicant_score'])): ?>
                                    <div class="applicant-score">
                                        <span>امتیاز:</span>
                                        <?php echo getScoreStars((float)$request['applicant_score']); ?>
                                        <span>(<?php echo number_format((float)$request['applicant_score'], 1); ?>)</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if(!empty($request['applicant_personality'])): ?>
                                <div class="applicant-personality">
                                    <i class="fas fa-brain"></i>
                                    <?php echo htmlspecialchars($request['applicant_personality']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="request-details">
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo $request['duration']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span><?php echo !empty($request['move_in_date']) ? date('Y/m/d', strtotime($request['move_in_date'])) : '—'; ?></span>
                                </div>
                                <?php if(!empty($request['budget'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span><?php echo number_format((int)$request['budget']) . ' تومان'; ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>تاریخ پردازش: <?php echo !empty($request['updated_at']) ? date('Y/m/d H:i', strtotime($request['updated_at'])) : '—'; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="request-message">
                            <div class="message-header">
                                <i class="fas fa-comment-dots"></i>
                                پیام متقاضی
                            </div>
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($request['message'] ?? '')); ?>
                            </div>
                        </div>
                    </div>

                    <div class="request-actions">
                        <a href="messages.php?house_id=<?php echo $request['house_id']; ?>&with=<?php echo $request['user_id']; ?>" class="action-btn message-btn">
                            <i class="fas fa-comments"></i>
                            ارتباط با متقاضی
                        </a>
                        <span class="action-btn <?php echo ($request['status'] ?? '') === 'تایید شده' ? 'accept-btn' : 'reject-btn'; ?>" style="cursor: default;">
                            <i class="fas fa-<?php echo ($request['status'] ?? '') === 'تایید شده' ? 'check' : 'times'; ?>-circle"></i>
                            <?php echo getStatusText($request['status'] ?? ''); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
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

// نمایش/مخفی کردن سوابق
function toggleHistory() {
    const historySection = document.getElementById('historySection');
    const toggleBtn = document.querySelector('.toggle-history');
    
    if (historySection.classList.contains('show')) {
        historySection.classList.remove('show');
        toggleBtn.innerHTML = '<i class="fas fa-eye"></i> نمایش سوابق';
    } else {
        historySection.classList.add('show');
        toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> مخفی کردن سوابق';
    }
}

// انیمیشن المان‌ها و بهبود تعامل با دکمه‌ها
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.request-card, .stat-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
    });
    
    // بهبود تعامل با دکمه‌ها
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const buttons = this.querySelectorAll('button[type="submit"]');
            buttons.forEach(btn => {
                btn.disabled = true;
                btn.classList.add('loading');
            });
        });
    });
    
    // پنهان کردن خودکار پیام‌ها پس از 5 ثانیه
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