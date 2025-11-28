# مستندات کامل: مدیریت پروفایل

## مقدمه

ماژول مدیریت پروفایل امکان مشاهده و ویرایش اطلاعات کاربر را فراهم می‌کند.

### فایل‌های مرتبط
- `pages/profile.php` - صفحه پروفایل

### ویژگی‌های کلیدی
- نمایش اطلاعات کاربر
- ویرایش اطلاعات
- آپلود آواتار
- به‌روزرسانی اطلاعات

---

## معماری

### معماری: Procedural PHP

---

## متدها

### به‌روزرسانی پروفایل
```php
$updateStmt = $conn->prepare("
    UPDATE users SET
        full_name = ?, email = ?, phone = ?, city = ?, province = ?,
        bio = ?, updated_at = NOW()
    WHERE id = ?
");
```

### آپلود آواتار
```php
$uploadPath = "assets/uploads/avatars/avatar_{$userId}_{$timestamp}.jpg";
move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath);

$avatarStmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
```

---

## جداول دیتابیس

### `users`
- تمام فیلدهای اطلاعات کاربر
- `avatar`: مسیر فایل آواتار

---

**تاریخ ایجاد**: 1403/09/08  
**نسخه**: 1.0

