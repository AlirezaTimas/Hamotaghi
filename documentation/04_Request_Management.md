# مستندات کامل: مدیریت درخواست‌ها

## فهرست مطالب
1. [مقدمه](#مقدمه)
2. [معماری و طراحی](#معماری-و-طراحی)
3. [کلاس‌ها و متدها](#کلاس‌ها-و-متدها)
4. [جداول دیتابیس](#جداول-دیتابیس)
5. [جریان کار](#جریان-کار)

---

## مقدمه

ماژول مدیریت درخواست‌ها امکان مشاهده، تأیید، رد و مدیریت درخواست‌های اقامت را برای صاحب‌خانه‌ها فراهم می‌کند.

### فایل‌های مرتبط
- `pages/house_requests.php` - مدیریت درخواست‌های صاحب‌خانه
- `pages/my_requests.php` - درخواست‌های کاربر (متقاضی)

### ویژگی‌های کلیدی
- نمایش درخواست‌های در انتظار
- تأیید/رد درخواست‌ها
- مدیریت ظرفیت خانه
- ایجاد اعلان برای کاربر
- نمایش سوابق درخواست‌ها

---

## معماری و طراحی

### معماری: Procedural PHP با Transaction Management

**ویژگی‌های خاص**:
- استفاده از Transactions برای عملیات چند مرحله‌ای
- سازگاری با هر دو جدول `requests` و `matches`
- مدیریت ظرفیت خودکار

---

## کلاس‌ها و متدها

### تأیید درخواست
```php
$conn->beginTransaction();
try {
    // 1. به‌روزرسانی وضعیت درخواست
    $update_stmt = $conn->prepare("
        UPDATE requests 
        SET request_status = 'approved', updated_at = NOW() 
        WHERE request_id = ? AND request_status = 'pending'
    ");
    
    // 2. کاهش ظرفیت خانه
    $capacityStmt = $conn->prepare("
        UPDATE houses 
        SET available_capacity = GREATEST(available_capacity - 1, 0) 
        WHERE id = ? AND available_capacity > 0
    ");
    
    // 3. ایجاد اعلان
    $notifStmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, type)
        VALUES (?, 'درخواست تایید شد', ?, 'request_response')
    ");
    
    $conn->commit();
} catch (Exception $e) {
    $conn->rollBack();
    throw $e;
}
```

### سازگاری با جداول مختلف
```php
$requestsExists = $conn->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0;
$matchesExists = $conn->query("SHOW TABLES LIKE 'matches'")->rowCount() > 0;

$tableName = $requestsExists ? 'requests' : 'matches';
$idField = $requestsExists ? 'request_id' : 'id';
$statusField = $requestsExists ? 'request_status' : 'status';
```

---

## جداول دیتابیس

### `requests`
- `request_id`: شناسه درخواست
- `listing_id`: شناسه خانه
- `sender_user_id`: متقاضی
- `receiver_owner_id`: صاحب‌خانه
- `request_status`: وضعیت (pending/approved/rejected/cancelled)

### `houses`
- `available_capacity`: ظرفیت موجود (کاهش خودکار با تأیید)

### `notifications`
- اعلان‌های مرتبط با درخواست‌ها

---

## جریان کار

1. **نمایش درخواست‌ها**: Query → فیلتر بر اساس صاحب‌خانه → نمایش
2. **تأیید**: کلیک تأیید → Transaction → به‌روزرسانی وضعیت → کاهش ظرفیت → اعلان
3. **رد**: کلیک رد → به‌روزرسانی وضعیت → اعلان

---

**تاریخ ایجاد**: 1403/09/08  
**نسخه**: 1.0

