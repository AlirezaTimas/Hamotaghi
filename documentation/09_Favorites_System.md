# مستندات کامل: سیستم علاقه‌مندی‌ها

## مقدمه

سیستم علاقه‌مندی‌ها امکان ذخیره خانه‌های مورد علاقه کاربر را فراهم می‌کند.

### فایل‌های مرتبط
- `pages/favorites.php` - لیست علاقه‌مندی‌ها
- `pages/toggle_favorite.php` - افزودن/حذف علاقه‌مندی

---

## معماری

### معماری: AJAX Handler

---

## متدها

### افزودن/حذف علاقه‌مندی
```php
// بررسی وجود
$checkStmt = $conn->prepare("
    SELECT id FROM favorites 
    WHERE user_id = ? AND house_id = ?
");

if ($exists) {
    // حذف
    $deleteStmt = $conn->prepare("DELETE FROM favorites WHERE id = ?");
} else {
    // افزودن
    $insertStmt = $conn->prepare("
        INSERT INTO favorites (user_id, house_id) VALUES (?, ?)
    ");
}
```

---

## جداول دیتابیس

### `favorites`
- `user_id`: شناسه کاربر
- `house_id`: شناسه خانه
- UNIQUE KEY روی (user_id, house_id)

---

**تاریخ ایجاد**: 1403/09/08  
**نسخه**: 1.0

