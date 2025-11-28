# مستندات کامل: مدیریت خانه‌ها

## فهرست مطالب
1. [مقدمه](#مقدمه)
2. [معماری و طراحی](#معماری-و-طراحی)
3. [فایل‌ها و متدها](#فایل‌ها-و-متدها)
4. [جداول دیتابیس](#جداول-دیتابیس)
5. [جریان کار](#جریان-کار)

---

## مقدمه

ماژول مدیریت خانه‌ها امکان ثبت، ویرایش، مشاهده و مدیریت خانه‌های صاحب‌خانه را فراهم می‌کند.

### فایل‌های مرتبط
- `pages/add_house.php` - ثبت خانه جدید
- `pages/edit_house.php` - ویرایش خانه
- `pages/my_houses.php` - لیست خانه‌های کاربر
- `pages/my_house_details.php` - جزئیات خانه (مستندات جداگانه)

### ویژگی‌های کلیدی
- ثبت خانه جدید با آپلود تصاویر
- ویرایش اطلاعات خانه
- مدیریت وضعیت خانه (فعال/غیرفعال)
- نمایش لیست خانه‌ها با آمار
- حذف خانه

---

## معماری و طراحی

### معماری انتخابی: Procedural PHP با جداسازی منطق

**دلیل انتخاب**:
- سادگی برای عملیات CRUD
- سرعت توسعه
- سازگاری با کد موجود

---

## فایل‌ها و متدها

### `add_house.php`

#### ثبت خانه جدید
```php
$stmt = $conn->prepare("
    INSERT INTO houses (
        user_id, title, description, address, city, province,
        price, capacity, available_capacity, gender, amenities, rules, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'فعال')
");
```

#### آپلود تصاویر
```php
// آپلود چند تصویر
foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
    $uploadPath = "assets/uploads/houses/house_{$houseId}_{$key}.jpg";
    move_uploaded_file($tmpName, $uploadPath);
    
    // ثبت در house_photos
    $photoStmt = $conn->prepare("
        INSERT INTO house_photos (house_id, file_path, is_primary)
        VALUES (?, ?, ?)
    ");
}
```

### `edit_house.php`

#### به‌روزرسانی اطلاعات
```php
$updateStmt = $conn->prepare("
    UPDATE houses SET
        title = ?, description = ?, address = ?, city = ?, province = ?,
        price = ?, capacity = ?, available_capacity = ?, gender = ?,
        amenities = ?, rules = ?, status = ?
    WHERE id = ? AND user_id = ?
");
```

### `my_houses.php`

#### دریافت لیست خانه‌ها
```php
$housesStmt = $conn->prepare("
    SELECT h.*,
           COUNT(DISTINCT r.request_id) as total_requests,
           COUNT(DISTINCT CASE WHEN r.request_status = 'pending' THEN r.request_id END) as pending_requests
    FROM houses h
    LEFT JOIN requests r ON h.id = r.listing_id
    WHERE h.user_id = ?
    GROUP BY h.id
    ORDER BY h.created_at DESC
");
```

---

## جداول دیتابیس

### `houses`
- تمام فیلدهای اطلاعات خانه
- Foreign Key به `users`

### `house_photos`
- ذخیره تصاویر خانه
- فیلد `is_primary` برای عکس اصلی

---

## جریان کار

1. **ثبت خانه**: فرم → اعتبارسنجی → ثبت در DB → آپلود تصاویر
2. **ویرایش**: انتخاب خانه → بارگذاری اطلاعات → ویرایش → به‌روزرسانی
3. **مشاهده لیست**: Query → نمایش با آمار → لینک به جزئیات

---

**تاریخ ایجاد**: 1403/09/08  
**نسخه**: 1.0

