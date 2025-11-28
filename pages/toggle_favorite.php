<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'برای استفاده از علاقه‌مندی‌ها ابتدا وارد شوید.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = [];
if (!empty($_POST)) {
    $payload = $_POST;
} elseif ($raw) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $payload = $decoded;
    }
}

$userId = (int)$_SESSION['user_id'];
$houseId = isset($payload['house_id']) ? (int)$payload['house_id'] : 0;
$action = strtolower((string)($payload['action'] ?? 'toggle'));

if ($houseId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'شناسه خانه معتبر نیست.']);
    exit;
}

try {
    $conn->beginTransaction();

    $userStmt = $conn->prepare("SELECT gender FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('کاربر یافت نشد.');
    }

    $houseStmt = $conn->prepare("
        SELECT id, user_id, gender, available_capacity, status
        FROM houses
        WHERE id = ?
        LIMIT 1
    ");
    $houseStmt->execute([$houseId]);
    $house = $houseStmt->fetch(PDO::FETCH_ASSOC);

    if (!$house || $house['status'] !== 'فعال') {
        throw new RuntimeException('این آگهی در دسترس نیست.');
    }

    if ((int)$house['user_id'] === $userId && $action !== 'remove') {
        throw new RuntimeException('نمی‌توانید خانه خودتان را به علاقه‌مندی اضافه کنید.');
    }

    if ($user['gender'] !== $house['gender']) {
        throw new RuntimeException('این آگهی برای جنسیت شما فعال نیست.');
    }

    if ((int)$house['available_capacity'] <= 0 && $action !== 'remove') {
        throw new RuntimeException('ظرفیت این آگهی تکمیل شده است.');
    }

    $favoriteStmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND house_id = ?");
    $favoriteStmt->execute([$userId, $houseId]);
    $favoriteId = $favoriteStmt->fetchColumn() ?: false;

    $shouldRemove = $action === 'remove' || ($action === 'toggle' && $favoriteId);

    if ($shouldRemove) {
        if ($favoriteId) {
            $deleteStmt = $conn->prepare("DELETE FROM favorites WHERE id = ?");
            $deleteStmt->execute([$favoriteId]);
        }
        $status = 'removed';
    } else {
        if (!$favoriteId) {
            $insertStmt = $conn->prepare("INSERT INTO favorites (user_id, house_id) VALUES (?, ?)");
            $insertStmt->execute([$userId, $houseId]);
        }
        $status = 'added';
    }

    $countStmt = $conn->prepare("SELECT COUNT(*) FROM favorites WHERE house_id = ?");
    $countStmt->execute([$houseId]);
    $favoritesCount = (int)$countStmt->fetchColumn();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'status' => $status,
        'favorites_count' => $favoritesCount
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}