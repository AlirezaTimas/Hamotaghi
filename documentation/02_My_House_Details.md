# مستندات کامل: مشاهده جزئیات خانه من (برای صاحب‌خانه)

## فهرست مطالب
1. [مقدمه](#مقدمه)
2. [معماری و طراحی](#معماری-و-طراحی)
3. [کلاس‌ها و متدها](#کلاس‌ها-و-متدها)
4. [جداول دیتابیس](#جداول-دیتابیس)
5. [جریان کار](#جریان-کار)
6. [الگوهای طراحی](#الگوهای-طراحی)
7. [بهترین روش‌ها](#بهترین-روش‌ها)

---

## مقدمه

این ماژول امکان مشاهده جزئیات کامل خانه‌های ثبت‌شده توسط صاحب‌خانه را فراهم می‌کند. صاحب‌خانه می‌تواند تمام اطلاعات خانه، آمار درخواست‌ها، مستأجران فعلی و گذشته، و پیام‌های مرتبط را مشاهده کند.

### فایل‌های مرتبط
- `pages/my_house_details.php` - صفحه اصلی نمایش جزئیات
- `pages/my_houses.php` - صفحه لیست خانه‌های کاربر (ورودی)

### ویژگی‌های کلیدی
- نمایش کامل اطلاعات خانه (آدرس، قیمت، ظرفیت، امکانات، قوانین)
- نمایش عکس‌های خانه
- آمار درخواست‌ها (کل، در انتظار، تأیید شده، رد شده)
- لیست مستأجران فعلی و گذشته
- آمار پیام‌ها (کل و خوانده نشده)
- اطلاعات صاحب‌خانه
- لینک‌های سریع به مدیریت درخواست‌ها و پیام‌ها

---

## معماری و طراحی

### انتخاب معماری

این ماژول از **معماری Procedural PHP با جداسازی منطق** استفاده می‌کند:

1. **Data Layer**: استفاده مستقیم از PDO
2. **Business Logic**: توابع و منطق در همان فایل
3. **Presentation Layer**: HTML/CSS/JavaScript

### دلیل انتخاب این معماری

- **سادگی**: برای صفحات نمایشی، این معماری کافی و مناسب است
- **سرعت**: بدون لایه‌های اضافی
- **قابلیت نگهداری**: کد واضح و قابل فهم
- **سازگاری**: با سایر صفحات پروژه هماهنگ است

### جایگزین‌های بررسی شده

1. **Full MVC**: برای این صفحه ساده، پیچیدگی اضافی ایجاد می‌کرد
2. **Component-Based**: نیاز به زیرساخت بیشتر داشت
3. **API + SPA**: برای این سطح از پیچیدگی مناسب نبود

---

## کلاس‌ها و متدها

### فایل: `my_house_details.php`

#### متدهای اصلی

##### 1. بررسی احراز هویت و دسترسی
```php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_stmt = $conn->prepare("SELECT id, full_name, usertype FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$current_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user || ($current_user['usertype'] ?? '') !== 'صاحب‌خانه') {
    header("Location: dashboard.php");
    exit();
}
```
**هدف**: اطمینان از اینکه فقط صاحب‌خانه‌ها به این صفحه دسترسی دارند  
**بررسی‌ها**:
- وجود session
- وجود کاربر در دیتابیس
- نوع کاربری = "صاحب‌خانه"

##### 2. دریافت اطلاعات خانه
```php
$house_stmt = $conn->prepare("
    SELECT h.*, 
           u.full_name as owner_name,
           u.email as owner_email,
           u.phone as owner_phone,
           u.user_score as owner_score,
           u.created_at as owner_joined_date
    FROM houses h
    JOIN users u ON h.user_id = u.id
    WHERE h.id = ? AND h.user_id = ?
");
$house_stmt->execute([$house_id, $user_id]);
```
**هدف**: دریافت اطلاعات خانه با اطمینان از مالکیت  
**پارامترها**: 
- `$house_id`: شناسه خانه
- `$user_id`: شناسه کاربر (برای اطمینان از مالکیت)

**بازگشت**: آرایه اطلاعات خانه و صاحب‌خانه

##### 3. دریافت عکس‌های خانه
```php
$photos_stmt = $conn->prepare("
    SELECT file_path, is_primary 
    FROM house_photos 
    WHERE house_id = ? 
    ORDER BY is_primary DESC, id ASC
");
$photos_stmt->execute([$house_id]);
$house_photos = $photos_stmt->fetchAll(PDO::FETCH_ASSOC);
```
**هدف**: دریافت تمام عکس‌های خانه  
**اولویت**: عکس اصلی اول، سپس بقیه به ترتیب

**Fallback**: اگر عکسی در `house_photos` نبود، از فیلد `images` در جدول `houses` استفاده می‌شود

##### 4. دریافت آمار درخواست‌ها
```php
// بررسی وجود جدول requests یا matches
$requestsExists = $conn->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0;
$matchesExists = $conn->query("SHOW TABLES LIKE 'matches'")->rowCount() > 0;

if ($requestsExists) {
    $stats_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN request_status = 'pending' OR request_status = 'در انتظار' OR request_status = '' OR request_status IS NULL THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN request_status = 'approved' OR request_status = 'تایید شده' THEN 1 ELSE 0 END) as approved_requests,
            SUM(CASE WHEN request_status = 'rejected' OR request_status = 'رد شده' THEN 1 ELSE 0 END) as rejected_requests
        FROM requests
        WHERE listing_id = ?
    ");
}
```
**هدف**: محاسبه آمار درخواست‌ها  
**پشتیبانی**: از هر دو جدول `requests` و `matches` برای سازگاری

**آمار محاسبه شده**:
- کل درخواست‌ها
- درخواست‌های در انتظار
- درخواست‌های تأیید شده
- درخواست‌های رد شده

##### 5. دریافت مستأجران فعلی
```php
$tenants_stmt = $conn->prepare("
    SELECT COUNT(*) as current_tenants
    FROM requests
    WHERE listing_id = ? 
      AND (request_status = 'approved' OR request_status = 'تایید شده')
");
```
**هدف**: شمارش مستأجران فعلی (درخواست‌های تأیید شده)

##### 6. دریافت آمار پیام‌ها
```php
$messages_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_messages,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_messages
    FROM messages
    WHERE house_id = ?
");
```
**هدف**: محاسبه آمار پیام‌های مرتبط با خانه

---

## جداول دیتابیس

### جدول `houses`
```sql
CREATE TABLE houses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(120) NOT NULL,
    province VARCHAR(120) NOT NULL,
    price INT NOT NULL,
    capacity INT NOT NULL,
    available_capacity INT NOT NULL DEFAULT 0,
    gender ENUM('آقا','خانم') NOT NULL,
    amenities TEXT,
    rules TEXT,
    images TEXT,
    status ENUM('فعال','در انتظار تایید','غیرفعال') DEFAULT 'فعال',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**فیلدهای کلیدی برای این ماژول**:
- `id`: شناسه خانه
- `user_id`: شناسه صاحب‌خانه (برای اطمینان از مالکیت)
- `title`: عنوان خانه
- `description`: توضیحات
- `address`: آدرس
- `price`: قیمت
- `capacity`: ظرفیت کل
- `available_capacity`: ظرفیت موجود
- `amenities`: امکانات (JSON یا متن)
- `rules`: قوانین خانه
- `images`: تصاویر (JSON یا متن)

### جدول `house_photos`
```sql
CREATE TABLE house_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
);
```

**فیلدهای کلیدی**:
- `house_id`: شناسه خانه
- `file_path`: مسیر فایل عکس
- `is_primary`: آیا عکس اصلی است (1) یا خیر (0)

### جدول `requests`
```sql
CREATE TABLE requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    sender_user_id INT NOT NULL,
    receiver_owner_id INT NOT NULL,
    request_status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    ...
    FOREIGN KEY (listing_id) REFERENCES houses(id) ON DELETE CASCADE
);
```

**استفاده در این ماژول**:
- شمارش درخواست‌ها بر اساس `listing_id`
- فیلتر بر اساس `request_status`

### جدول `messages`
```sql
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    content TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
);
```

**استفاده در این ماژول**:
- شمارش پیام‌های مرتبط با خانه
- شمارش پیام‌های خوانده نشده

---

## جریان کار

### 1. دسترسی به صفحه
```
صاحب‌خانه → my_houses.php → کلیک "مشاهده" → my_house_details.php?id=123
```

### 2. بررسی احراز هویت
```
سیستم → بررسی session → بررسی نوع کاربری → اگر صاحب‌خانه نیست → redirect
```

### 3. بررسی مالکیت
```
سیستم → Query با شرط h.user_id = ? → اطمینان از مالکیت خانه
```

### 4. دریافت اطلاعات
```
سیستم → Query های متعدد:
  - اطلاعات خانه و صاحب‌خانه
  - عکس‌های خانه
  - آمار درخواست‌ها
  - مستأجران فعلی
  - آمار پیام‌ها
```

### 5. نمایش اطلاعات
```
سیستم → نمایش در بخش‌های مختلف:
  - اطلاعات کلی خانه
  - گالری عکس‌ها
  - آمار و نمودارها
  - لینک‌های سریع
```

---

## الگوهای طراحی

### 1. Ownership Verification Pattern
```php
WHERE h.id = ? AND h.user_id = ?
```
**هدف**: اطمینان از اینکه کاربر فقط خانه‌های خودش را می‌بیند  
**امنیت**: جلوگیری از دسترسی غیرمجاز

### 2. Table Compatibility Pattern
```php
$requestsExists = $conn->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0;
$matchesExists = $conn->query("SHOW TABLES LIKE 'matches'")->rowCount() > 0;

if ($requestsExists) {
    // استفاده از requests
} elseif ($matchesExists) {
    // استفاده از matches
}
```
**هدف**: سازگاری با هر دو ساختار دیتابیس  
**استفاده**: در تمام Query های مرتبط با درخواست‌ها

### 3. Fallback Pattern
```php
if (empty($house_photos) && !empty($house['images'])) {
    $images = json_decode($house['images'], true);
    // استفاده از images قدیمی
}
```
**هدف**: پشتیبانی از داده‌های قدیمی  
**استفاده**: برای عکس‌ها و سایر داده‌های JSON

### 4. Status Normalization Pattern
```php
SUM(CASE WHEN request_status = 'pending' OR request_status = 'در انتظار' OR request_status = '' OR request_status IS NULL THEN 1 ELSE 0 END)
```
**هدف**: پشتیبانی از وضعیت‌های مختلف (انگلیسی، فارسی، خالی)  
**استفاده**: در تمام Query های مرتبط با وضعیت

---

## بهترین روش‌ها

### امنیت

1. **Ownership Verification**: همیشه بررسی مالکیت قبل از نمایش
2. **Input Validation**: اعتبارسنجی `house_id` از URL
3. **SQL Injection Prevention**: استفاده از Prepared Statements
4. **Output Escaping**: استفاده از `htmlspecialchars()` برای نمایش

### کارایی

1. **Query Optimization**: استفاده از JOIN به جای Query های متعدد
2. **Lazy Loading**: بارگذاری تصاویر به صورت lazy
3. **Caching**: Cache کردن آمار برای کاهش Query ها
4. **Indexing**: Index روی `house_id` و `user_id`

### قابلیت نگهداری

1. **Error Handling**: مدیریت خطا با try-catch
2. **Logging**: ثبت خطاها در error_log
3. **Code Reusability**: استفاده از توابع مشترک
4. **Documentation**: توضیح کدهای پیچیده

### تجربه کاربری

1. **Loading States**: نمایش وضعیت بارگذاری
2. **Error Messages**: پیام‌های خطای واضح
3. **Navigation**: لینک‌های سریع به صفحات مرتبط
4. **Responsive Design**: طراحی واکنش‌گرا

---

## خلاصه

این ماژول با استفاده از معماری ساده و کارآمد، امکان مشاهده کامل جزئیات خانه‌های صاحب‌خانه را فراهم می‌کند. استفاده از Ownership Verification، Table Compatibility، و Fallback Patterns باعث ایجاد سیستم قابل اعتماد و سازگار شده است.

**نقاط قوت**:
- امنیت بالا با بررسی مالکیت
- سازگاری با ساختارهای مختلف دیتابیس
- پشتیبانی از داده‌های قدیمی
- نمایش کامل اطلاعات

**نقاط بهبود احتمالی**:
- Cache کردن آمار
- استفاده از Service Layer
- اضافه کردن Export به PDF
- بهبود UI/UX

---

**تاریخ ایجاد**: 1403/09/08  
**نسخه**: 1.0  
**نویسنده**: تیم توسعه هم‌اتاقی

