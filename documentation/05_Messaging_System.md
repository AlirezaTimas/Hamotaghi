# مستندات کامل: سیستم پیام‌رسانی

## مقدمه

سیستم پیام‌رسانی امکان ارتباط بین صاحب‌خانه و متقاضی را فراهم می‌کند.

### فایل‌های مرتبط
- `pages/messages.php` - رابط پیام‌رسانی

### ویژگی‌های کلیدی
- چت بین کاربران
- بررسی اجازه گفتگو (بر اساس درخواست)
- نمایش پیام‌های خوانده/نخوانده
- رابط کاربری چت

---

## معماری

### معماری: Procedural PHP با AJAX

---

## متدها

### بررسی اجازه گفتگو
```php
function canMessage(PDO $conn, int $userId, int $otherUserId, int $houseId): bool
{
    // بررسی وجود درخواست فعال
    $stmt = $conn->prepare("
        SELECT 1 FROM requests
        WHERE listing_id = :house_id
          AND request_status IN ('pending', 'approved')
          AND (
              (sender_user_id = :user_id AND receiver_owner_id = :other_user)
           OR (sender_user_id = :other_user AND receiver_owner_id = :user_id)
          )
    ");
    return (bool)$stmt->fetchColumn();
}
```

### ارسال پیام
```php
$insertMsg = $conn->prepare("
    INSERT INTO messages (house_id, sender_id, receiver_id, content)
    VALUES (:house_id, :sender_id, :receiver_id, :content)
");
```

---

## جداول دیتابیس

### `messages`
- `house_id`: شناسه خانه مرتبط
- `sender_id`: فرستنده
- `receiver_id`: گیرنده
- `content`: محتوای پیام
- `is_read`: وضعیت خواندن

---

## جریان کار

1. **بررسی اجازه**: بررسی وجود درخواست فعال
2. **ارسال پیام**: اعتبارسنجی → ثبت در DB
3. **نمایش پیام‌ها**: Query → نمایش به ترتیب زمان

---

**تاریخ ایجاد**: 1403/09/08  
**نسخه**: 1.0

