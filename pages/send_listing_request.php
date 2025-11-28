<?php
/**
 * AJAX Handler for Sending Listing Requests
 * This file handles the AJAX request from listing_details.php
 */

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'لطفاً ابتدا وارد حساب کاربری خود شوید.'
    ]);
    exit();
}

$userId = (int)$_SESSION['user_id'];

// Validate input
$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
$receiverOwnerId = isset($_POST['receiver_owner_id']) ? (int)$_POST['receiver_owner_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$moveInDate = isset($_POST['move_in_date']) ? $_POST['move_in_date'] : null;
$duration = isset($_POST['duration']) ? trim($_POST['duration']) : '';
$budget = isset($_POST['budget']) ? (int)$_POST['budget'] : null;

// Validation
if ($listingId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'شناسه خانه نامعتبر است.'
    ]);
    exit();
}

if ($receiverOwnerId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'شناسه صاحب خانه نامعتبر است.'
    ]);
    exit();
}

if (empty($message)) {
    echo json_encode([
        'success' => false,
        'message' => 'لطفاً پیام خود را وارد کنید.'
    ]);
    exit();
}

if (empty($moveInDate)) {
    echo json_encode([
        'success' => false,
        'message' => 'لطفاً تاریخ ورود را انتخاب کنید.'
    ]);
    exit();
}

if (empty($duration)) {
    echo json_encode([
        'success' => false,
        'message' => 'لطفاً مدت اقامت را انتخاب کنید.'
    ]);
    exit();
}

try {
    // Verify user information
    $userStmt = $conn->prepare("SELECT id, usertype, gender FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'کاربر یافت نشد.'
        ]);
        exit();
    }
    
    if ($user['usertype'] !== 'هم‌خانه') {
        echo json_encode([
            'success' => false,
            'message' => 'فقط کاربران با نقش "هم‌خانه" می‌توانند درخواست ارسال کنند.'
        ]);
        exit();
    }
    
    // Verify listing exists and get details
    $listingStmt = $conn->prepare("
        SELECT id, user_id, gender, available_capacity, status 
        FROM houses 
        WHERE id = ?
    ");
    $listingStmt->execute([$listingId]);
    $listing = $listingStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$listing) {
        echo json_encode([
            'success' => false,
            'message' => 'خانه یافت نشد.'
        ]);
        exit();
    }
    
    // Check if user is the owner
    if ((int)$listing['user_id'] === $userId) {
        echo json_encode([
            'success' => false,
            'message' => 'شما صاحب این خانه هستید و نمی‌توانید برای آن درخواست ارسال کنید.'
        ]);
        exit();
    }
    
    // Check gender match
    if ($user['gender'] !== $listing['gender']) {
        echo json_encode([
            'success' => false,
            'message' => 'این خانه مخصوص ' . htmlspecialchars($listing['gender']) . 'ها است و با جنسیت شما تطابق ندارد.'
        ]);
        exit();
    }
    
    // Check capacity
    if ((int)($listing['available_capacity'] ?? 0) <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ظرفیت این خانه تکمیل شده است.'
        ]);
        exit();
    }
    
    // Check which table exists (requests or matches)
    $requestsExists = $conn->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0;
    $matchesExists = $conn->query("SHOW TABLES LIKE 'matches'")->rowCount() > 0;
    
    // Determine which table to use and field names
    if ($requestsExists) {
        $tableName = 'requests';
        $houseField = 'listing_id';
        $userField = 'sender_user_id';
        $idField = 'request_id';
        $statusField = 'request_status';
        $statusValue = 'pending'; // Will be converted to Persian if needed
    } elseif ($matchesExists) {
        $tableName = 'matches';
        $houseField = 'house_id';
        $userField = 'user_id';
        $idField = 'id';
        $statusField = 'status';
        $statusValue = 'در انتظار';
    } else {
        // Try to create requests table with Persian status values
        try {
            $createTableSQL = "
                CREATE TABLE requests (
                    request_id INT AUTO_INCREMENT PRIMARY KEY,
                    listing_id INT NOT NULL,
                    sender_user_id INT NOT NULL,
                    receiver_owner_id INT NOT NULL,
                    request_status ENUM('در انتظار', 'تایید شده', 'رد شده', 'لغو شده') DEFAULT 'در انتظار',
                    message TEXT,
                    move_in_date DATE,
                    duration VARCHAR(100),
                    budget INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (listing_id) REFERENCES houses(id) ON DELETE CASCADE,
                    FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (receiver_owner_id) REFERENCES users(id) ON DELETE CASCADE,
                    INDEX idx_listing_id (listing_id),
                    INDEX idx_sender_user_id (sender_user_id),
                    INDEX idx_receiver_owner_id (receiver_owner_id),
                    INDEX idx_request_status (request_status),
                    INDEX idx_created_at (created_at),
                    UNIQUE KEY uq_listing_sender (listing_id, sender_user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $conn->exec($createTableSQL);
            $requestsExists = true;
            $tableName = 'requests';
            $houseField = 'listing_id';
            $userField = 'sender_user_id';
            $idField = 'request_id';
            $statusField = 'request_status';
            $statusValue = 'در انتظار';
        } catch (PDOException $e) {
            error_log("Error creating requests table: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'خطا در ایجاد جدول درخواست‌ها. لطفاً با مدیر سیستم تماس بگیرید.'
            ]);
            exit();
        }
    }
    
    // Check if user has already sent a request
    $checkStmt = $conn->prepare("
        SELECT {$idField} 
        FROM {$tableName} 
        WHERE {$houseField} = ? AND {$userField} = ?
    ");
    $checkStmt->execute([$listingId, $userId]);
    $existingRequest = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingRequest) {
        echo json_encode([
            'success' => false,
            'message' => 'شما قبلاً برای این خانه درخواست ارسال کرده‌اید.'
        ]);
        exit();
    }
    
    // Insert new request
    if ($tableName === 'requests') {
        $insertStmt = $conn->prepare("
            INSERT INTO requests (
                listing_id, 
                sender_user_id, 
                receiver_owner_id, 
                request_status, 
                message, 
                move_in_date, 
                duration, 
                budget
            ) VALUES (?, ?, ?, 'pending', ?, ?, ?, ?)
        ");
    } else {
        // matches table
        $insertStmt = $conn->prepare("
            INSERT INTO matches (
                house_id, 
                user_id, 
                message, 
                move_in_date, 
                duration, 
                budget, 
                status
            ) VALUES (?, ?, ?, ?, ?, ?, 'در انتظار')
        ");
    }
    
    try {
        if ($tableName === 'requests') {
            $insertStmt->execute([
                $listingId,
                $userId,
                $receiverOwnerId,
                $message,
                $moveInDate ?: null,
                $duration,
                $budget
            ]);
        } else {
            $insertStmt->execute([
                $listingId,
                $userId,
                $message,
                $moveInDate ?: null,
                $duration,
                $budget
            ]);
        }
        
        $requestId = $conn->lastInsertId();
        
        // Verify insertion was successful
        if (!$requestId || $requestId <= 0) {
            error_log("Failed to insert request - lastInsertId returned: " . $requestId);
            throw new Exception("خطا در ثبت درخواست در دیتابیس");
        }
        
        error_log("Request inserted successfully: ID = {$requestId}, Table = {$tableName}, Listing = {$listingId}, User = {$userId}");
        
    } catch (PDOException $e) {
        error_log("PDO Error inserting request: " . $e->getMessage());
        error_log("SQL Error Info: " . print_r($e->errorInfo(), true));
        throw $e; // Re-throw to be caught by outer catch block
    }
    
    // Create notification for owner (if notifications table exists)
    try {
        $notificationsExists = $conn->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0;
        if ($notificationsExists) {
            // Get sender name
            $userNameStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
            $userNameStmt->execute([$userId]);
            $userNameData = $userNameStmt->fetch(PDO::FETCH_ASSOC);
            $senderName = $userNameData['full_name'] ?? 'کاربر';
            
            // Get house title
            $houseTitleStmt = $conn->prepare("SELECT title FROM houses WHERE id = ?");
            $houseTitleStmt->execute([$listingId]);
            $houseTitleData = $houseTitleStmt->fetch(PDO::FETCH_ASSOC);
            $houseTitle = $houseTitleData['title'] ?? 'خانه';
            
            $notificationStmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_id, is_read)
                VALUES (?, 'درخواست جدید', ?, 'request', ?, 0)
            ");
            $notificationStmt->execute([
                $receiverOwnerId,
                "درخواست جدید از {$senderName} برای خانه «{$houseTitle}»",
                $listingId
            ]);
        }
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        // Continue - notification is optional
    }
    
    // Log activity (if user_activities table exists)
    try {
        $activitiesExists = $conn->query("SHOW TABLES LIKE 'user_activities'")->rowCount() > 0;
        if ($activitiesExists) {
            $activityStmt = $conn->prepare("
                INSERT INTO user_activities (user_id, activity_type, description)
                VALUES (?, 'request_sent', ?)
            ");
            $activityStmt->execute([
                $userId,
                "درخواست اقامت برای خانه #{$listingId} ارسال شد"
            ]);
        }
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        // Continue - activity logging is optional
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'درخواست شما با موفقیت ارسال شد و به صاحب خانه اطلاع داده شد.',
        'request_id' => $requestId
    ]);
    
} catch (PDOException $e) {
    error_log("Error sending request: " . $e->getMessage());
    error_log("SQL Error Info: " . print_r($e->errorInfo(), true));
    echo json_encode([
        'success' => false,
        'message' => 'خطا در ارسال درخواست: ' . $e->getMessage() . ' لطفاً دوباره تلاش کنید.'
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'خطای غیرمنتظره رخ داد: ' . $e->getMessage() . ' لطفاً دوباره تلاش کنید.'
    ]);
}
?>

