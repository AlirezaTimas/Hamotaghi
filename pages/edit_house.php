<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

$userStmt = $conn->prepare("SELECT usertype FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser || $currentUser['usertype'] !== 'صاحب‌خانه') {
    header('Location: dashboard.php');
    exit();
}

$houseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($houseId <= 0) {
    header('Location: my_houses.php');
    exit();
}

function getHouse(PDO $conn, int $houseId, int $userId): array
{
    $stmt = $conn->prepare("SELECT * FROM houses WHERE id = ? AND user_id = ?");
    $stmt->execute([$houseId, $userId]);
    $house = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$house) {
        header('Location: my_houses.php');
        exit();
    }
    return $house;
}

function getHousePhotos(PDO $conn, int $houseId): array
{
    $stmt = $conn->prepare("
        SELECT id, file_path, is_primary
        FROM house_photos
        WHERE house_id = ?
        ORDER BY is_primary DESC, id ASC
    ");
    $stmt->execute([$houseId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ensureHousePrimaryPhoto(PDO $conn, int $houseId): void
{
    $primaryStmt = $conn->prepare("SELECT id FROM house_photos WHERE house_id = ? AND is_primary = 1 LIMIT 1");
    $primaryStmt->execute([$houseId]);
    if (!$primaryStmt->fetchColumn()) {
        $firstStmt = $conn->prepare("SELECT id FROM house_photos WHERE house_id = ? ORDER BY id ASC LIMIT 1");
        $firstStmt->execute([$houseId]);
        $firstId = $firstStmt->fetchColumn() ?: null;
        if ($firstId !== null) {
            $updateStmt = $conn->prepare("UPDATE house_photos SET is_primary = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE house_id = ?");
            $updateStmt->execute([$firstId, $houseId]);
        }
    }
}

function syncHouseImagesColumn(PDO $conn, int $houseId): void
{
    $stmt = $conn->prepare("SELECT file_path FROM house_photos WHERE house_id = ? ORDER BY is_primary DESC, id ASC");
    $stmt->execute([$houseId]);
    $paths = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($paths)) {
        $paths = ['assets/images/roommates.jpg'];
        $insertDefault = $conn->prepare("INSERT INTO house_photos (house_id, file_path, is_primary) VALUES (?, ?, 1)");
        $insertDefault->execute([$houseId, $paths[0]]);
    }
    $json = json_encode($paths, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $update = $conn->prepare("UPDATE houses SET images = ? WHERE id = ?");
    $update->execute([$json, $houseId]);
}

$house = getHouse($conn, $houseId, $userId);
$housePhotos = getHousePhotos($conn, $houseId);

$amenitiesList = [
    'اینترنت', 'تلفن', 'مبلمان', 'آشپزخانه',
    'لباسشویی', 'پارکینگ', 'آسانسور', 'حیاط'
];
$statusOptions = ['فعال', 'در انتظار تایید', 'غیرفعال'];

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $price = (int)($_POST['price'] ?? 0);
    $capacity = (int)($_POST['capacity'] ?? 0);
    $gender = $_POST['gender'] ?? '';
    $status = $_POST['status'] ?? 'فعال';
    $rules = trim($_POST['rules'] ?? '');
    $amenitiesSelected = isset($_POST['amenities']) && is_array($_POST['amenities']) ? array_intersect($amenitiesList, $_POST['amenities']) : [];

    if ($title === '') $errors[] = 'عنوان خانه الزامی است.';
    if ($address === '') $errors[] = 'آدرس را وارد کنید.';
    if ($city === '') $errors[] = 'شهر را وارد کنید.';
    if ($province === '') $errors[] = 'استان را وارد کنید.';
    if ($price <= 0) $errors[] = 'قیمت باید عددی مثبت باشد.';
    if ($capacity <= 0) $errors[] = 'ظرفیت باید عددی مثبت باشد.';
    if (!in_array($gender, ['آقا', 'خانم'], true)) $errors[] = 'جنسیت معتبر نیست.';
    if (!in_array($status, $statusOptions, true)) $errors[] = 'وضعیت انتخاب‌شده معتبر نیست.';

    $occupied = max((int)$house['capacity'] - (int)$house['available_capacity'], 0);
    $newAvailableCapacity = max($capacity - $occupied, 0);

    // حذف عکس‌های انتخاب‌شده
    $deletePhotos = isset($_POST['delete_photos']) && is_array($_POST['delete_photos']) ? array_map('intval', $_POST['delete_photos']) : [];

    $uploadDir = __DIR__ . '/../assets/uploads/houses/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newPhotoPaths = [];
    if (!empty($_FILES['new_photos']['name'][0])) {
        foreach ($_FILES['new_photos']['tmp_name'] as $idx => $tmpPath) {
            if ($_FILES['new_photos']['error'][$idx] !== UPLOAD_ERR_OK) {
                $errors[] = 'خطا در آپلود فایل شماره ' . ($idx + 1);
                continue;
            }

            $fileType = mime_content_type($tmpPath);
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
            if (!in_array($fileType, $allowedTypes, true)) {
                $errors[] = 'فرمت تصویر نامعتبر است.';
                continue;
            }

            if ($_FILES['new_photos']['size'][$idx] > 5 * 1024 * 1024) {
                $errors[] = 'حجم هر تصویر باید کمتر از 5 مگابایت باشد.';
                continue;
            }

            $extension = strtolower(pathinfo($_FILES['new_photos']['name'][$idx], PATHINFO_EXTENSION));
            $fileName = sprintf('house_%d_%d_%d.%s', $userId, $houseId, time() + $idx, $extension);
            $destination = $uploadDir . $fileName;

            if (move_uploaded_file($tmpPath, $destination)) {
                $newPhotoPaths[] = 'assets/uploads/houses/' . $fileName;
            } else {
                $errors[] = 'امکان ذخیره تصویر ' . htmlspecialchars($_FILES['new_photos']['name'][$idx]);
            }
        }
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            if (!empty($deletePhotos)) {
                $photoQuery = $conn->prepare("SELECT id, file_path FROM house_photos WHERE house_id = ? AND id = ?");
                $deleteStmt = $conn->prepare("DELETE FROM house_photos WHERE id = ?");
                foreach ($deletePhotos as $photoId) {
                    if ($photoId <= 0) {
                        continue;
                    }
                    $photoQuery->execute([$houseId, $photoId]);
                    if ($photo = $photoQuery->fetch(PDO::FETCH_ASSOC)) {
                        $deleteStmt->execute([$photo['id']]);
                        $fileOnDisk = __DIR__ . '/../' . $photo['file_path'];
                        if (is_file($fileOnDisk)) {
                            @unlink($fileOnDisk);
                        }
                    }
                }
            }

            if (!empty($newPhotoPaths)) {
                $primaryExistsStmt = $conn->prepare("SELECT COUNT(*) FROM house_photos WHERE house_id = ? AND is_primary = 1");
                $primaryExistsStmt->execute([$houseId]);
                $hasPrimary = (int)$primaryExistsStmt->fetchColumn() > 0;

                $insertPhotoStmt = $conn->prepare("INSERT INTO house_photos (house_id, file_path, is_primary) VALUES (?, ?, ?)");
                foreach ($newPhotoPaths as $index => $path) {
                    $isPrimary = (!$hasPrimary && $index === 0) ? 1 : 0;
                    $insertPhotoStmt->execute([$houseId, $path, $isPrimary]);
                    if ($isPrimary) {
                        $hasPrimary = true;
                    }
                }
            }

            // اگر همه عکس‌ها حذف شده‌اند، یک عکس پیش‌فرض اضافه کن
            $photoCountStmt = $conn->prepare("SELECT COUNT(*) FROM house_photos WHERE house_id = ?");
            $photoCountStmt->execute([$houseId]);
            if ((int)$photoCountStmt->fetchColumn() === 0) {
                $conn->prepare("INSERT INTO house_photos (house_id, file_path, is_primary) VALUES (?, ?, 1)")
                     ->execute([$houseId, 'assets/images/roommates.jpg']);
            }

            ensureHousePrimaryPhoto($conn, $houseId);

            $updateStmt = $conn->prepare("
                UPDATE houses
                SET title = :title,
                    description = :description,
                    address = :address,
                    city = :city,
                    province = :province,
                    price = :price,
                    capacity = :capacity,
                    available_capacity = :available_capacity,
                    gender = :gender,
                    amenities = :amenities,
                    rules = :rules,
                    status = :status
                WHERE id = :house_id AND user_id = :user_id
            ");

            $updateStmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':address' => $address,
                ':city' => $city,
                ':province' => $province,
                ':price' => $price,
                ':capacity' => $capacity,
                ':available_capacity' => $newAvailableCapacity,
                ':gender' => $gender,
                ':amenities' => implode(', ', $amenitiesSelected),
                ':rules' => $rules,
                ':status' => $status,
                ':house_id' => $houseId,
                ':user_id' => $userId
            ]);

            syncHouseImagesColumn($conn, $houseId);

            $conn->commit();
            $success = 'اطلاعات خانه با موفقیت به‌روزرسانی شد.';

            $house = getHouse($conn, $houseId, $userId);
            $housePhotos = getHousePhotos($conn, $houseId);
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $errors[] = 'خطا در ذخیره تغییرات: ' . $e->getMessage();
        }
    }
}

$selectedAmenities = array_map('trim', explode(',', $house['amenities'] ?? ''));
$selectedAmenities = array_filter($selectedAmenities, static fn($value) => $value !== '');
$houseImagesJson = $house['images'] ? json_decode($house['images'], true) : [];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ویرایش خانه | هم‌اتاقی</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg,#050912,#0b141f);
            color: #fff;
        }
        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px 60px;
        }
        .card {
            background: rgba(15,26,33,0.9);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.35);
        }
        h1 {
            margin-bottom: 15px;
            font-size: 28px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit,minmax(240px,1fr));
            gap: 20px;
        }
        label {
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
        }
        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.08);
            padding: 12px;
            color: #fff;
            font-family: inherit;
        }
        textarea {
            min-height: 110px;
            resize: vertical;
        }
        .amenities-list {
            display: grid;
            grid-template-columns: repeat(auto-fit,minmax(180px,1fr));
            gap: 10px;
        }
        .amenities-list label {
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit,minmax(160px,1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .photo-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .photo-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .photo-item span {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0,0,0,0.6);
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 12px;
        }
        .delete-photo {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(244,67,54,0.85);
            border: none;
            color: #fff;
            padding: 6px 10px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
        }
        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: rgba(76,175,80,0.2);
            border: 1px solid rgba(76,175,80,0.4);
        }
        .alert-error {
            background: rgba(244,67,54,0.2);
            border: 1px solid rgba(244,67,54,0.4);
        }
        .actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 25px;
        }
        .btn {
            border: none;
            border-radius: 12px;
            padding: 14px 26px;
            font-weight: 600;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg,#ffab00,#ff8c00);
            color: #111;
        }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .upload-input {
            border: 2px dashed rgba(255,255,255,0.3);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
        }
        .upload-input input {
            width: 100%;
            cursor: pointer;
        }
        @media (max-width: 640px) {
            .actions {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>ویرایش خانه: <?= htmlspecialchars($house['title']); ?></h1>
            <p style="color:#ccc;">وضعیت فعلی: <?= htmlspecialchars($house['status']); ?> | ظرفیت خالی: <?= (int)$house['available_capacity']; ?> از <?= (int)$house['capacity']; ?></p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="card">
            <div class="form-grid">
                <div>
                    <label>عنوان</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($house['title']); ?>" required>
                </div>
                <div>
                    <label>قیمت (تومان)</label>
                    <input type="number" name="price" value="<?= (int)$house['price']; ?>" min="0" required>
                </div>
                <div>
                    <label>ظرفیت</label>
                    <input type="number" name="capacity" value="<?= (int)$house['capacity']; ?>" min="1" required>
                </div>
                <div>
                    <label>جنسیت مجاز</label>
                    <select name="gender" required>
                        <option value="آقا" <?= $house['gender'] === 'آقا' ? 'selected' : ''; ?>>آقا</option>
                        <option value="خانم" <?= $house['gender'] === 'خانم' ? 'selected' : ''; ?>>خانم</option>
                    </select>
                </div>
                <div>
                    <label>وضعیت نمایش</label>
                    <select name="status">
                        <?php foreach ($statusOptions as $option): ?>
                            <option value="<?= $option; ?>" <?= $house['status'] === $option ? 'selected' : ''; ?>><?= $option; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid" style="margin-top:25px;">
                <div>
                    <label>استان</label>
                    <input type="text" name="province" value="<?= htmlspecialchars($house['province']); ?>" required>
                </div>
                <div>
                    <label>شهر</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($house['city']); ?>" required>
                </div>
            </div>

            <div style="margin-top:25px;">
                <label>آدرس کامل</label>
                <textarea name="address" required><?= htmlspecialchars($house['address']); ?></textarea>
            </div>

            <div style="margin-top:25px;">
                <label>توضیحات</label>
                <textarea name="description"><?= htmlspecialchars($house['description']); ?></textarea>
            </div>

            <div style="margin-top:25px;">
                <label>قوانین و شرایط</label>
                <textarea name="rules"><?= htmlspecialchars($house['rules']); ?></textarea>
            </div>

            <div style="margin-top:25px;">
                <label>امکانات</label>
                <div class="amenities-list">
                    <?php foreach ($amenitiesList as $amenity): ?>
                        <label>
                            <input type="checkbox" name="amenities[]" value="<?= $amenity; ?>" <?= in_array($amenity, $selectedAmenities, true) ? 'checked' : ''; ?>>
                            <span><?= $amenity; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="margin-top:30px;">
                <label>عکس‌های موجود</label>
                <div class="photos-grid">
                    <?php foreach ($housePhotos as $photo): ?>
                        <div class="photo-item">
                            <img src="../<?= htmlspecialchars($photo['file_path']); ?>" alt="house photo">
                            <?php if ($photo['is_primary']): ?>
                                <span>عکس اصلی</span>
                            <?php endif; ?>
                            <label class="delete-photo">
                                <input type="checkbox" name="delete_photos[]" value="<?= (int)$photo['id']; ?>" style="margin-left:6px;">
                                حذف
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="upload-input" style="margin-top:30px;">
                <label>افزودن عکس جدید (حداکثر 5MB برای هر عکس)</label>
                <input type="file" name="new_photos[]" multiple accept="image/*">
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    ذخیره تغییرات
                </button>
                <a href="my_houses.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i>
                    بازگشت به لیست
                </a>
            </div>
        </form>
    </div>
</body>
</html>

