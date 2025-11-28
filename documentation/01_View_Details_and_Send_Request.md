# مستندات کامل: مشاهده جزئیات و ارسال درخواست

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

این ماژول امکان مشاهده جزئیات کامل یک خانه و ارسال درخواست اقامت را برای کاربران فراهم می‌کند. این قابلیت یکی از اصلی‌ترین ویژگی‌های پلتفرم هم‌اتاقی است که ارتباط بین صاحب‌خانه و متقاضی را تسهیل می‌کند.

### فایل‌های مرتبط
- `pages/listing_details.php` - صفحه نمایش جزئیات خانه
- `pages/send_listing_request.php` - پردازشگر AJAX برای ارسال درخواست

### ویژگی‌های کلیدی
- نمایش کامل اطلاعات خانه (آدرس، قیمت، ظرفیت، امکانات)
- نمایش اطلاعات صاحب‌خانه (نام، امتیاز، تعداد مستأجران قبلی)
- اعتبارسنجی قبل از ارسال درخواست (جنسیت، ظرفیت)
- ارسال درخواست به صورت AJAX بدون رفرش صفحه
- مدیریت خطا و پیام‌های مناسب به کاربر

---

## معماری و طراحی

### انتخاب معماری

این ماژول از **معماری MVC ترکیبی** استفاده می‌کند:

1. **Model Layer**: استفاده مستقیم از PDO برای دسترسی به دیتابیس
2. **View Layer**: HTML/CSS/JavaScript برای رابط کاربری
3. **Controller Logic**: منطق کنترل در همان فایل صفحه (Procedural PHP)

### دلیل انتخاب این معماری

- **سادگی**: برای صفحات ساده، استفاده از MVC کامل پیچیدگی غیرضروری ایجاد می‌کند
- **سرعت توسعه**: کد مستقیم و قابل فهم برای توسعه‌دهندگان
- **سازگاری**: با کدهای موجود پروژه هماهنگ است
- **کارایی**: بدون لایه‌های اضافی، سرعت اجرا بالاتر است

### جایگزین‌های بررسی شده

1. **Pure MVC**: برای این ماژول ساده، پیچیدگی اضافی ایجاد می‌کرد
2. **API + Frontend Framework**: نیاز به زیرساخت بیشتر داشت
3. **Component-Based**: برای این سطح از پیچیدگی مناسب نبود

---

## کلاس‌ها و متدها

### فایل: `listing_details.php`

#### متدهای اصلی

##### 1. بررسی ورود کاربر
```php
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}
```
**هدف**: اطمینان از ورود کاربر قبل از نمایش صفحه  
**استفاده**: بررسی session و redirect در صورت نیاز

##### 2. دریافت اطلاعات خانه
```php
$listingStmt = $conn->prepare("
    SELECT 
        h.*,
        u.id as owner_id,
        u.full_name as owner_name,
        u.user_score as owner_rating,
        COALESCE((SELECT COUNT(*) FROM matches WHERE house_id = h.id AND status = 'تایید شده'), 0) as previous_tenants
    FROM houses h
    JOIN users u ON h.user_id = u.id
    WHERE h.id = ?
");
```
**هدف**: دریافت تمام اطلاعات خانه و صاحب‌خانه  
**پارامترها**: `$listingId` - شناسه خانه  
**بازگشت**: آرایه اطلاعات خانه و صاحب‌خانه

##### 3. بررسی درخواست قبلی
```php
$requestStmt = $conn->prepare("
    SELECT request_id, request_status 
    FROM requests 
    WHERE listing_id = ? AND sender_user_id = ?
");
```
**هدف**: بررسی اینکه آیا کاربر قبلاً درخواست ارسال کرده است  
**پارامترها**: `$listingId`, `$userId`  
**بازگشت**: وضعیت درخواست قبلی یا null

##### 4. اعتبارسنجی امکان ارسال درخواست
```php
$canSendRequest = (
    !$hasRequested &&
    $currentUser['usertype'] === 'هم‌خانه' &&
    $currentUser['gender'] === $listing['gender'] &&
    (int)$listing['available_capacity'] > 0
);
```
**هدف**: بررسی شرایط لازم برای ارسال درخواست  
**شرایط**:
- کاربر قبلاً درخواست نکرده باشد
- نوع کاربری "هم‌خانه" باشد
- جنسیت کاربر با جنسیت خانه مطابقت داشته باشد
- ظرفیت خانه موجود باشد

### فایل: `send_listing_request.php`

#### متدهای اصلی

##### 1. اعتبارسنجی ورودی‌ها
```php
if ($listingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'شناسه خانه نامعتبر است.']);
    exit();
}
```
**هدف**: بررسی صحت داده‌های ورودی  
**اعتبارسنجی‌ها**:
- شناسه خانه
- شناسه صاحب‌خانه
- پیام (اجباری)
- تاریخ ورود (اجباری)
- مدت اقامت (اجباری)
- بودجه (اختیاری)

##### 2. بررسی جنسیت و ظرفیت
```php
if ($user['gender'] !== $house['gender']) {
    echo json_encode(['success' => false, 'message' => 'جنسیت شما با این خانه مطابقت ندارد.']);
    exit();
}

if ((int)$house['available_capacity'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'ظرفیت این خانه تکمیل شده است.']);
    exit();
}
```
**هدف**: اعتبارسنجی نهایی قبل از ثبت درخواست

##### 3. ثبت درخواست در دیتابیس
```php
$insertStmt = $conn->prepare("
    INSERT INTO requests (
        listing_id, sender_user_id, receiver_owner_id,
        message, move_in_date, duration, budget, request_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
");
```
**هدف**: ذخیره درخواست در جدول `requests`  
**پارامترها**: تمام اطلاعات درخواست  
**بازگشت**: JSON response با وضعیت موفقیت/خطا

##### 4. ایجاد اعلان
```php
$notifStmt = $conn->prepare("
    INSERT INTO notifications (user_id, title, message, type, related_id)
    VALUES (?, 'درخواست جدید', ?, 'request', ?)
");
```
**هدف**: اطلاع‌رسانی به صاحب‌خانه درباره درخواست جدید

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

**فیلدهای کلیدی**:
- `id`: شناسه یکتای خانه
- `user_id`: شناسه صاحب‌خانه
- `available_capacity`: ظرفیت موجود برای اقامت
- `gender`: جنسیت مجاز برای اقامت
- `status`: وضعیت خانه (فعال/غیرفعال)

### جدول `requests`
```sql
CREATE TABLE requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    sender_user_id INT NOT NULL,
    receiver_owner_id INT NOT NULL,
    request_status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    message TEXT,
    move_in_date DATE,
    duration VARCHAR(100),
    budget INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES houses(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_owner_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_listing_sender (listing_id, sender_user_id)
);
```

**فیلدهای کلیدی**:
- `request_id`: شناسه یکتای درخواست
- `listing_id`: شناسه خانه مورد نظر
- `sender_user_id`: شناسه متقاضی
- `receiver_owner_id`: شناسه صاحب‌خانه
- `request_status`: وضعیت درخواست
- `UNIQUE KEY`: جلوگیری از درخواست تکراری

### جدول `users`
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    usertype ENUM('هم‌خانه','صاحب‌خانه') NOT NULL,
    gender ENUM('آقا','خانم') NOT NULL,
    user_score DECIMAL(3,2) DEFAULT 0.00,
    ...
);
```

**فیلدهای مرتبط**:
- `usertype`: نوع کاربری (هم‌خانه یا صاحب‌خانه)
- `gender`: جنسیت کاربر
- `user_score`: امتیاز کاربر

### جدول `notifications`
```sql
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type VARCHAR(50),
    related_id INT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## جریان کار

### 1. دسترسی به صفحه جزئیات
```
کاربر → کلیک روی "مشاهده جزئیات" → listing_details.php?id=123
```

### 2. بررسی احراز هویت
```
سیستم → بررسی session → اگر لاگین نیست → redirect به login.php
```

### 3. دریافت اطلاعات
```
سیستم → Query دیتابیس → دریافت اطلاعات خانه و صاحب‌خانه → نمایش در صفحه
```

### 4. بررسی امکان ارسال درخواست
```
سیستم → بررسی:
  - آیا قبلاً درخواست داده؟
  - نوع کاربری = هم‌خانه؟
  - جنسیت مطابقت دارد؟
  - ظرفیت موجود است؟
→ نمایش/مخفی کردن دکمه "ارسال درخواست"
```

### 5. ارسال درخواست (AJAX)
```
کاربر → پر کردن فرم → کلیک "ارسال درخواست"
→ AJAX Request → send_listing_request.php
→ اعتبارسنجی → ثبت در دیتابیس → ایجاد اعلان
→ Response JSON → نمایش پیام موفقیت/خطا
```

### 6. به‌روزرسانی UI
```
JavaScript → دریافت Response → نمایش پیام → به‌روزرسانی دکمه
→ تغییر وضعیت به "درخواست ارسال شده"
```

---

## الگوهای طراحی

### 1. Prepared Statements Pattern
```php
$stmt = $conn->prepare("SELECT * FROM houses WHERE id = ?");
$stmt->execute([$houseId]);
```
**هدف**: جلوگیری از SQL Injection  
**استفاده**: در تمام Query های دیتابیس

### 2. Error Handling Pattern
```php
try {
    // عملیات دیتابیس
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    // مدیریت خطا
}
```
**هدف**: مدیریت خطاها به صورت graceful  
**استفاده**: در تمام عملیات دیتابیس

### 3. AJAX Response Pattern
```php
header('Content-Type: application/json');
echo json_encode([
    'success' => true/false,
    'message' => 'پیام مناسب'
]);
```
**هدف**: پاسخ استاندارد به درخواست‌های AJAX

### 4. Session Management Pattern
```php
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
}
```
**هدف**: مدیریت احراز هویت و redirect

---

## بهترین روش‌ها

### امنیت

1. **Prepared Statements**: استفاده از PDO Prepared Statements برای تمام Query ها
2. **Input Validation**: اعتبارسنجی کامل تمام ورودی‌ها
3. **Output Escaping**: استفاده از `htmlspecialchars()` برای نمایش داده‌ها
4. **Session Security**: بررسی session در تمام صفحات حساس
5. **CSRF Protection**: در صورت نیاز، استفاده از CSRF tokens

### کارایی

1. **Query Optimization**: استفاده از JOIN به جای Query های متعدد
2. **Indexing**: استفاده از Index روی فیلدهای پرکاربرد
3. **Caching**: در صورت نیاز، cache کردن اطلاعات ثابت
4. **Lazy Loading**: بارگذاری تصاویر به صورت lazy

### قابلیت نگهداری

1. **Code Organization**: جداسازی منطق از نمایش
2. **Error Logging**: ثبت تمام خطاها در error_log
3. **Comments**: توضیح کدهای پیچیده
4. **Consistent Naming**: استفاده از نام‌گذاری یکسان

### تجربه کاربری

1. **Loading States**: نمایش وضعیت بارگذاری
2. **Error Messages**: پیام‌های خطای واضح و مفید
3. **Success Feedback**: تأیید عملیات موفق
4. **Form Validation**: اعتبارسنجی سمت کلاینت و سرور

---

## خلاصه

این ماژول با استفاده از معماری ساده و کارآمد، امکان مشاهده جزئیات خانه و ارسال درخواست را فراهم می‌کند. استفاده از Prepared Statements، مدیریت خطا، و AJAX باعث ایجاد تجربه کاربری روان و امن شده است.

**نقاط قوت**:
- کد ساده و قابل فهم
- امنیت بالا با Prepared Statements
- تجربه کاربری خوب با AJAX
- مدیریت خطای مناسب

**نقاط بهبود احتمالی**:
- جداسازی کامل منطق از View
- استفاده از Service Layer
- اضافه کردن Unit Tests
- بهبود Caching

---

**تاریخ ایجاد**: 1403/09/08  
**نسخه**: 1.0  
**نویسنده**: تیم توسعه هم‌اتاقی

