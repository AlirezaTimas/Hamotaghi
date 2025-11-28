# مستندات کامل: جستجوی خانه‌ها

## مقدمه

سیستم جستجوی خانه‌ها امکان فیلتر و جستجوی خانه‌های موجود را فراهم می‌کند.

### فایل‌های مرتبط
- `pages/available_houses.php` - صفحه جستجو

### ویژگی‌های کلیدی
- فیلتر بر اساس شهر، استان، قیمت
- فیلتر بر اساس جنسیت
- مرتب‌سازی (قیمت، تاریخ)
- جستجوی متنی

---

## معماری

### معماری: Procedural PHP با Dynamic Query Building

---

## متدها

### ساخت Query پویا
```php
$where = ["h.status = 'فعال'"];
$params = [];

if (!empty($city)) {
    $where[] = "h.city = ?";
    $params[] = $city;
}

if (!empty($minPrice)) {
    $where[] = "h.price >= ?";
    $params[] = $minPrice;
}

if (!empty($gender)) {
    $where[] = "h.gender = ?";
    $params[] = $gender;
}

$sql = "SELECT h.*, u.full_name as owner_name 
        FROM houses h 
        JOIN users u ON h.user_id = u.id 
        WHERE " . implode(' AND ', $where) . "
        ORDER BY h.created_at DESC";
```

---

## جداول دیتابیس

### `houses`
- فیلدهای فیلتر: `city`, `province`, `price`, `gender`
- Index روی فیلدهای پرکاربرد

---

**تاریخ ایجاد**: 1403/09/08  
**نسخه**: 1.0

