# 📚 مستندات کامل پروژه هم‌اتاقی (Hamotaghi)

<div style="text-align: center; direction: rtl; font-family: Tahoma, Arial, sans-serif;">

**نسخه:** 1.0  
**تاریخ ایجاد:** 1403/09/08  
**وضعیت:** ✅ کامل و به‌روز

---

## 📋 فهرست مطالب

1. [خلاصه اجرایی](#خلاصه-اجرایی)
2. [معرفی پروژه](#معرفی-پروژه)
3. [معماری پروژه](#معماری-پروژه)
4. [کلاس‌ها و متدها](#کلاس‌ها-و-متدها)
5. [ساختار دیتابیس](#ساختار-دیتابیس)
6. [ویژگی‌های پیاده‌سازی شده](#ویژگی‌های-پیاده‌سازی-شده)
7. [UI/UX Specification](#uiux-specification)
8. [بهترین روش‌ها](#بهترین-روش‌ها)
9. [راهنمای توسعه](#راهنمای-توسعه)
10. [Roadmap آینده](#roadmap-آینده)

---

</div>

## 1. خلاصه اجرایی

### 1.1. معرفی کوتاه

**هم‌اتاقی (Hamotaghi)** یک پلتفرم آنلاین برای پیدا کردن هم‌اتاقی مناسب است که با استفاده از تکنولوژی‌های مدرن PHP و MySQL توسعه یافته است. این سیستم به کاربران امکان می‌دهد تا به عنوان **صاحب‌خانه** یا **هم‌خانه** ثبت‌نام کنند و با استفاده از الگوریتم‌های تطبیق هوشمند، بهترین هم‌اتاقی را پیدا کنند.

### 1.2. اهداف اصلی

- ✅ ایجاد بستری امن و قابل اعتماد برای پیدا کردن هم‌اتاقی
- ✅ استفاده از تست شخصیت MBTI برای تطبیق بهتر
- ✅ مدیریت کامل درخواست‌ها و پیام‌رسانی
- ✅ سیستم امتیازدهی و اعتبارسنجی کاربران
- ✅ رعایت قوانین فرهنگی ایران (جدا بودن جنسیت)

### 1.3. تکنولوژی‌های استفاده شده

| تکنولوژی | نسخه | کاربرد |
|---------|------|--------|
| **PHP** | 8.0+ | Backend Development |
| **MySQL** | 8.0+ | Database Management |
| **PDO** | - | Database Abstraction |
| **HTML5/CSS3** | - | Frontend Structure |
| **JavaScript** | ES6+ | Frontend Interactivity |
| **Bootstrap** | 5.x | UI Framework |
| **Font Awesome** | 6.x | Icons |
| **Particles.js** | - | Visual Effects |

---

## 2. معرفی پروژه

### 2.1. مشکل حل شده

در ایران، پیدا کردن هم‌اتاقی مناسب با چالش‌های زیادی روبرو است:
- عدم وجود پلتفرم اختصاصی
- نبود سیستم تطبیق هوشمند
- عدم رعایت قوانین فرهنگی (جدا بودن جنسیت)
- نبود سیستم اعتبارسنجی

### 2.2. راه‌حل ارائه شده

پلتفرم هم‌اتاقی با ویژگی‌های زیر این مشکلات را حل می‌کند:
- ✅ سیستم تطبیق هوشمند بر اساس تست شخصیت MBTI
- ✅ فیلتر پیشرفته بر اساس شهر، قیمت، جنسیت
- ✅ سیستم پیام‌رسانی داخلی
- ✅ مدیریت کامل درخواست‌ها
- ✅ سیستم امتیازدهی کاربران
- ✅ رعایت کامل قوانین فرهنگی ایران

### 2.3. کاربران هدف

1. **صاحب‌خانه‌ها**: افرادی که خانه دارند و می‌خواهند هم‌اتاقی پیدا کنند
2. **هم‌خانه‌ها**: افرادی که به دنبال خانه و هم‌اتاقی مناسب هستند

---

## 3. معماری پروژه

### 3.1. انتخاب معماری

پروژه از **معماری MVC (Model-View-Controller)** استفاده می‌کند که ترکیبی از:
- **Layered Architecture** برای جداسازی لایه‌ها
- **Repository Pattern** برای دسترسی به دیتابیس
- **Service Layer** برای منطق کسب‌وکار

### 3.2. چرا این معماری انتخاب شد؟

#### ✅ مزایا:

1. **قابلیت نگهداری (Maintainability)**
   - جداسازی منطق کسب‌وکار از نمایش
   - کد تمیز و قابل فهم

2. **قابلیت توسعه (Scalability)**
   - افزودن فیچرهای جدید بدون تغییر کد موجود
   - استفاده از Dependency Injection

3. **قابلیت تست (Testability)**
   - جداسازی لایه‌ها امکان Unit Testing را فراهم می‌کند
   - Mock کردن Repository ها برای تست

4. **امنیت (Security)**
   - استفاده از Prepared Statements
   - اعتبارسنجی ورودی‌ها
   - مدیریت Session امن

#### ❌ مقایسه با معماری‌های دیگر:

| معماری | مزایا | معایب | چرا انتخاب نشد؟ |
|--------|-------|-------|----------------|
| **Monolithic** | ساده، سریع | غیرقابل نگهداری | کد درهم و برهم |
| **Microservices** | مقیاس‌پذیر | پیچیده برای پروژه کوچک | Over-engineering |
| **Laravel Framework** | کامل، قدرتمند | سنگین، یادگیری سخت | نیاز به یادگیری Framework |

### 3.3. لایه‌بندی کامل

```
┌─────────────────────────────────────────┐
│         Presentation Layer              │
│  (Pages, Views, HTML/CSS/JS)           │
└─────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────┐
│         Controller Layer               │
│  (AuthController, BaseController)     │
└─────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────┐
│         Service Layer                   │
│  (MatchingService, Business Logic)     │
└─────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────┐
│         Repository Layer                │
│  (UserRepository, HouseRepository)     │
└─────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────┐
│         Model Layer                     │
│  (User, House, Request, Message)      │
└─────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────┐
│         Database Layer                  │
│  (MySQL via PDO)                       │
└─────────────────────────────────────────┘
```

### 3.4. ساختار دایرکتوری

```
public_html/
├── admin/                    # پنل مدیریت
├── app/                      # لایه Application
│   ├── Domain/               # Domain Layer
│   │   ├── Entities/        # Entity Classes
│   │   └── Interfaces/      # Repository Interfaces
│   ├── Repositories/        # Repository Implementations
│   └── Services/            # Business Logic Services
├── assets/                   # فایل‌های استاتیک
│   ├── css/                 # استایل‌ها
│   ├── js/                  # اسکریپت‌ها
│   ├── images/              # تصاویر
│   └── uploads/             # فایل‌های آپلود شده
├── config/                   # تنظیمات
│   └── app.php              # تنظیمات اصلی
├── controllers/              # کنترلرها
│   ├── AuthController.php
│   └── BaseController.php
├── core/                     # کلاس‌های اصلی
│   ├── Database.php         # مدیریت اتصال دیتابیس
│   ├── Session.php          # مدیریت Session
│   ├── Auth.php             # احراز هویت
│   ├── Validator.php        # اعتبارسنجی
│   ├── Helper.php           # توابع کمکی
│   ├── Router.php           # مسیریابی
│   └── bootstrap.php        # راه‌اندازی
├── database/                 # اسکریپت‌های دیتابیس
│   └── hamotaghi_complete.sql
├── documentation/            # مستندات
├── includes/                 # فایل‌های مشترک
│   ├── config.php           # تنظیمات قدیمی (Backward Compatible)
│   ├── header.php           # هدر مشترک
│   └── footer.php           # فوتر مشترک
├── models/                   # مدل‌های داده
│   ├── BaseModel.php        # مدل پایه
│   ├── User.php
│   ├── House.php
│   ├── Request.php
│   ├── Message.php
│   ├── Favorite.php
│   └── Notification.php
├── pages/                    # صفحات اصلی
│   ├── dashboard.php
│   ├── login.php
│   ├── register.php
│   ├── my_houses.php
│   ├── my_house_details.php
│   ├── house_requests.php
│   ├── messages.php
│   └── ...
├── views/                    # View Templates
│   ├── layouts/
│   └── auth/
└── index.php                 # صفحه اصلی
```

---

## 4. کلاس‌ها و متدها

### 4.1. Core Classes

#### 4.1.1. Database (Singleton Pattern)

**مسئولیت:** مدیریت اتصال به دیتابیس

**موقعیت:** `core/Database.php`

**متدها:**

| متد | نوع | ورودی | خروجی | توضیح |
|-----|-----|-------|-------|-------|
| `init()` | static | `array $config` | `void` | تنظیمات اولیه دیتابیس |
| `getInstance()` | static | - | `PDO` | دریافت instance اتصال |

**مثال استفاده:**

```php
// تنظیمات اولیه
Database::init([
    'host' => 'localhost',
    'dbname' => 'hamotaghi_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
]);

// دریافت اتصال
$db = Database::getInstance();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([1]);
```

**الگوی طراحی:** Singleton Pattern - اطمینان از یک اتصال واحد

---

#### 4.1.2. Session

**مسئولیت:** مدیریت Session امن

**موقعیت:** `core/Session.php`

**متدها:**

| متد | نوع | ورودی | خروجی | توضیح |
|-----|-----|-------|-------|-------|
| `start()` | static | - | `void` | شروع Session |
| `get()` | static | `string $key, $default` | `mixed` | دریافت مقدار |
| `set()` | static | `string $key, $value` | `void` | تنظیم مقدار |
| `has()` | static | `string $key` | `bool` | بررسی وجود کلید |
| `remove()` | static | `string $key` | `void` | حذف کلید |
| `destroy()` | static | - | `void` | نابودی Session |
| `regenerate()` | static | - | `void` | بازتولید ID (امنیت) |

**مثال استفاده:**

```php
Session::start();
Session::set('user_id', 123);
$userId = Session::get('user_id');
if (Session::has('user_id')) {
    // کاربر لاگین است
}
```

---

#### 4.1.3. Auth

**مسئولیت:** احراز هویت و مجوزدهی

**موقعیت:** `core/Auth.php`

**متدها:**

| متد | نوع | ورودی | خروجی | توضیح |
|-----|-----|-------|-------|-------|
| `check()` | static | - | `bool` | بررسی لاگین بودن |
| `id()` | static | - | `?int` | دریافت ID کاربر |
| `user()` | static | - | `?array` | دریافت اطلاعات کاربر |
| `login()` | static | `int $userId, array $userData` | `void` | لاگین کاربر |
| `logout()` | static | - | `void` | خروج کاربر |
| `isLandlord()` | static | - | `bool` | بررسی صاحب‌خانه بودن |
| `isRoommate()` | static | - | `bool` | بررسی هم‌خانه بودن |

**مثال استفاده:**

```php
if (Auth::check()) {
    $user = Auth::user();
    if (Auth::isLandlord()) {
        // دسترسی صاحب‌خانه
    }
}
```

---

#### 4.1.4. Validator

**مسئولیت:** اعتبارسنجی و پاکسازی ورودی‌ها

**موقعیت:** `core/Validator.php`

**متدها:**

| متد | نوع | ورودی | خروجی | توضیح |
|-----|-----|-------|-------|-------|
| `validate()` | public | `array $data, array $rules` | `bool` | اعتبارسنجی داده‌ها |
| `errors()` | public | - | `array` | دریافت خطاها |
| `firstError()` | public | `string $field` | `?string` | اولین خطای فیلد |
| `sanitize()` | static | `string $input` | `string` | پاکسازی رشته |
| `sanitizeArray()` | static | `array $data` | `array` | پاکسازی آرایه |
| `isValidEmail()` | static | `string $email` | `bool` | بررسی ایمیل |
| `isValidPhone()` | static | `string $phone` | `bool` | بررسی شماره تلفن |

**قوانین اعتبارسنجی:**

- `required`: فیلد الزامی
- `email`: فرمت ایمیل
- `min:X`: حداقل X کاراکتر
- `max:X`: حداکثر X کاراکتر
- `numeric`: عددی
- `in:value1,value2`: یکی از مقادیر
- `date`: تاریخ معتبر
- `date_future`: تاریخ آینده

**مثال استفاده:**

```php
$validator = new Validator();
$rules = [
    'email' => 'required|email',
    'password' => 'required|min:8',
    'phone' => 'required|numeric'
];

if ($validator->validate($_POST, $rules)) {
    // داده‌ها معتبر هستند
} else {
    $errors = $validator->errors();
    foreach ($errors as $field => $fieldErrors) {
        echo $validator->firstError($field);
    }
}
```

---

### 4.2. Model Classes

#### 4.2.1. BaseModel

**مسئولیت:** کلاس پایه برای تمام مدل‌ها

**موقعیت:** `models/BaseModel.php`

**متدها:**

| متد | نوع | ورودی | خروجی | توضیح |
|-----|-----|-------|-------|-------|
| `find()` | public | `int $id` | `?array` | پیدا کردن بر اساس ID |
| `findAll()` | public | `array $conditions, string $orderBy, int $limit` | `array` | پیدا کردن همه |
| `create()` | public | `array $data` | `bool` | ایجاد رکورد جدید |
| `update()` | public | `int $id, array $data` | `bool` | به‌روزرسانی |
| `delete()` | public | `int $id` | `bool` | حذف |

**مثال استفاده:**

```php
class User extends BaseModel
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
}

$user = new User();
$userData = $user->find(1);
$user->update(1, ['full_name' => 'علی احمدی']);
```

---

#### 4.2.2. User Model

**مسئولیت:** مدیریت کاربران

**موقعیت:** `models/User.php`

**متدها:**

| متد | نوع | ورودی | خروجی | توضیح |
|-----|-----|-------|-------|-------|
| `findByEmail()` | public | `string $email` | `?array` | پیدا کردن بر اساس ایمیل |
| `findByPhone()` | public | `string $phone` | `?array` | پیدا کردن بر اساس تلفن |
| `verifyPassword()` | public | `string $password, string $hash` | `bool` | بررسی رمز عبور |
| `hashPassword()` | public | `string $password` | `string` | هش کردن رمز عبور |
| `updatePersonality()` | public | `int $userId, string $personalityType` | `bool` | به‌روزرسانی نوع شخصیت |
| `emailExists()` | public | `string $email, ?int $excludeId` | `bool` | بررسی وجود ایمیل |
| `phoneExists()` | public | `string $phone, ?int $excludeId` | `bool` | بررسی وجود تلفن |

**مثال استفاده:**

```php
$user = new User();
$userData = $user->findByEmail('user@example.com');
if ($userData && $user->verifyPassword($password, $userData['password'])) {
    // لاگین موفق
}
```

---

#### 4.2.3. House Model

**مسئولیت:** مدیریت خانه‌ها

**موقعیت:** `models/House.php`

**متدها:**

| متد | نوع | ورودی | خروجی | توضیح |
|-----|-----|-------|-------|-------|
| `search()` | public | `array $filters, string $sort, int $limit, int $offset` | `array` | جستجوی خانه‌ها |
| `findByOwner()` | public | `int $ownerId` | `array` | خانه‌های صاحب |
| `updateCapacity()` | public | `int $houseId, int $newCapacity` | `bool` | به‌روزرسانی ظرفیت |
| `getPhotos()` | public | `int $houseId` | `array` | دریافت عکس‌ها |

**فیلترهای جستجو:**

- `status`: وضعیت خانه (فعال، غیرفعال)
- `city`: شهر
- `province`: استان
- `gender`: جنسیت
- `min_price`: حداقل قیمت
- `max_price`: حداکثر قیمت
- `include_full`: شامل خانه‌های پر

**مثال استفاده:**

```php
$house = new House();
$filters = [
    'city' => 'تهران',
    'gender' => 'آقا',
    'min_price' => 3000000,
    'max_price' => 5000000
];
$houses = $house->search($filters, 'price_low', 12, 0);
```

---

#### 4.2.4. Request Model

**مسئولیت:** مدیریت درخواست‌ها

**موقعیت:** `models/Request.php`

**متدها:**

| متد | نوع | ورودی | خروجی | توضیح |
|-----|-----|-------|-------|-------|
| `hasPendingRequest()` | public | `int $userId, int $houseId` | `bool` | بررسی درخواست در انتظار |
| `findWithDetails()` | public | `int $id` | `?array` | درخواست با جزئیات |
| `findByHouse()` | public | `int $houseId, ?string $status` | `array` | درخواست‌های خانه |
| `findByUser()` | public | `int $userId, ?string $status` | `array` | درخواست‌های کاربر |
| `updateStatus()` | public | `int $id, string $status` | `bool` | به‌روزرسانی وضعیت |
| `getStats()` | public | `int $userId, string $userType` | `array` | آمار درخواست‌ها |

**مثال استفاده:**

```php
$request = new Request();
if (!$request->hasPendingRequest($userId, $houseId)) {
    // می‌تواند درخواست بدهد
}
$requests = $request->findByHouse($houseId, 'pending');
```

---

#### 4.2.5. Message Model

**مسئولیت:** مدیریت پیام‌ها

**موقعیت:** `models/Message.php`

**متدها:**

| متد | نوع | ورودی | خروجی | توضیح |
|-----|-----|-------|-------|-------|
| `getConversation()` | public | `int $houseId, int $userId1, int $userId2` | `array` | دریافت مکالمه |
| `getContacts()` | public | `int $userId` | `array` | دریافت مخاطبین |
| `getUnreadCount()` | public | `int $userId` | `int` | تعداد پیام‌های خوانده نشده |
| `markAsRead()` | public | `int $houseId, int $receiverId, int $senderId` | `bool` | علامت‌گذاری خوانده شده |
| `canMessage()` | public | `int $userId, int $otherUserId, int $houseId` | `bool` | بررسی اجازه پیام |

**مثال استفاده:**

```php
$message = new Message();
if ($message->canMessage($userId, $otherUserId, $houseId)) {
    $conversation = $message->getConversation($houseId, $userId, $otherUserId);
}
```

---

### 4.3. Service Classes

#### 4.3.1. MatchingService

**مسئولیت:** منطق تطبیق و پیشنهاد

**موقعیت:** `app/Services/MatchingService.php`

**متدها:**

| متد | نوع | ورودی | خروجی | توضیح |
|-----|-----|-------|-------|-------|
| `getRecommendations()` | public | `int $userId, array $options` | `array` | دریافت پیشنهادات |
| `sendRequest()` | public | `int $fromUserId, int $listingId, ?string $message` | `array` | ارسال درخواست |
| `accept()` | public | `int $matchId, int $actorId` | `array` | تأیید درخواست |
| `reject()` | public | `int $matchId, int $actorId, ?string $reason` | `bool` | رد درخواست |
| `computeMatchScore()` | public | `array $requesterProfile, array $ownerProfile, array $listing` | `int` | محاسبه امتیاز تطبیق |

**الگوریتم تطبیق:**

امتیاز نهایی از 4 بخش تشکیل می‌شود:
1. **Personality Score (40%)**: تطبیق نوع شخصیت MBTI
2. **Lifestyle Score (30%)**: تطبیق سبک زندگی (سیگار، حیوان خانگی، نظافت)
3. **Budget Score (20%)**: تطبیق بودجه
4. **Location Score (10%)**: تطبیق موقعیت جغرافیایی

**مثال استفاده:**

```php
$matchingService = new MatchingService();
$recommendations = $matchingService->getRecommendations($userId);
foreach ($recommendations as $rec) {
    echo "Match: {$rec['match_percentage']}%";
}
```

---

### 4.4. Domain Entities

#### 4.4.1. User Entity

**موقعیت:** `app/Domain/Entities/User.php`

**Properties:**

- `id`: شناسه کاربر
- `email`: ایمیل
- `phone`: شماره تلفن
- `password`: رمز عبور (هش شده)
- `role`: نقش (owner/roommate)
- `gender`: جنسیت
- `firstLogin`: اولین ورود
- `isActive`: فعال بودن

**متدها:**

- `isOwner()`: بررسی صاحب‌خانه بودن
- `isRoommate()`: بررسی هم‌خانه بودن
- `toArray()`: تبدیل به آرایه

---

## 5. ساختار دیتابیس

### 5.1. جداول اصلی

#### 5.1.1. users

**هدف:** ذخیره اطلاعات کاربران

| ستون | نوع | توضیح |
|------|-----|-------|
| `id` | INT | شناسه یکتا (Primary Key) |
| `full_name` | VARCHAR(150) | نام کامل |
| `email` | VARCHAR(190) | ایمیل (Unique) |
| `phone` | VARCHAR(20) | شماره تلفن (Unique) |
| `password` | VARCHAR(255) | رمز عبور (BCrypt) |
| `usertype` | ENUM | نوع کاربر (هم‌خانه/صاحب‌خانه) |
| `gender` | ENUM | جنسیت (آقا/خانم) |
| `city` | VARCHAR(120) | شهر |
| `province` | VARCHAR(120) | استان |
| `national_code` | VARCHAR(10) | کد ملی |
| `personality_type` | VARCHAR(4) | نوع شخصیت MBTI |
| `bio` | TEXT | بیوگرافی |
| `avatar` | VARCHAR(255) | مسیر آواتار |
| `user_score` | DECIMAL(3,2) | امتیاز کاربر (0-5) |
| `first_login` | TINYINT(1) | اولین ورود |
| `is_active` | TINYINT(1) | فعال بودن |
| `created_at` | TIMESTAMP | تاریخ ایجاد |
| `updated_at` | TIMESTAMP | تاریخ به‌روزرسانی |

**Indexes:**
- `idx_email`: برای جستجوی سریع ایمیل
- `idx_phone`: برای جستجوی سریع تلفن
- `idx_usertype`: برای فیلتر نوع کاربر
- `idx_gender`: برای فیلتر جنسیت
- `idx_city`: برای فیلتر شهر

---

#### 5.1.2. houses

**هدف:** ذخیره اطلاعات خانه‌ها

| ستون | نوع | توضیح |
|------|-----|-------|
| `id` | INT | شناسه یکتا (Primary Key) |
| `user_id` | INT | شناسه صاحب خانه (Foreign Key → users.id) |
| `title` | VARCHAR(200) | عنوان آگهی |
| `description` | TEXT | توضیحات |
| `address` | VARCHAR(255) | آدرس |
| `city` | VARCHAR(120) | شهر |
| `province` | VARCHAR(120) | استان |
| `price` | INT | قیمت اجاره (تومان) |
| `capacity` | INT | ظرفیت کل |
| `available_capacity` | INT | ظرفیت خالی |
| `gender` | ENUM | جنسیت مجاز (آقا/خانم) |
| `amenities` | TEXT | امکانات (JSON) |
| `rules` | TEXT | قوانین خانه |
| `images` | TEXT | مسیر تصاویر |
| `status` | ENUM | وضعیت (فعال/در انتظار تایید/غیرفعال) |
| `created_at` | TIMESTAMP | تاریخ ایجاد |
| `updated_at` | TIMESTAMP | تاریخ به‌روزرسانی |

**Foreign Keys:**
- `user_id` → `users.id` (ON DELETE CASCADE)

**Indexes:**
- `idx_user_id`: برای جستجوی خانه‌های یک کاربر
- `idx_city`: برای فیلتر شهر
- `idx_status`: برای فیلتر وضعیت
- `idx_gender`: برای فیلتر جنسیت
- `idx_price`: برای مرتب‌سازی قیمت

---

#### 5.1.3. requests

**هدف:** ذخیره درخواست‌های اقامت

| ستون | نوع | توضیح |
|------|-----|-------|
| `request_id` | INT | شناسه یکتا (Primary Key) |
| `listing_id` | INT | شناسه خانه (Foreign Key → houses.id) |
| `sender_user_id` | INT | شناسه فرستنده (Foreign Key → users.id) |
| `receiver_owner_id` | INT | شناسه صاحب خانه (Foreign Key → users.id) |
| `request_status` | ENUM | وضعیت (pending/approved/rejected/cancelled) |
| `message` | TEXT | پیام درخواست |
| `move_in_date` | DATE | تاریخ ورود |
| `duration` | VARCHAR(100) | مدت اقامت |
| `budget` | INT | بودجه پیشنهادی |
| `created_at` | TIMESTAMP | تاریخ ایجاد |
| `updated_at` | TIMESTAMP | تاریخ به‌روزرسانی |

**Foreign Keys:**
- `listing_id` → `houses.id` (ON DELETE CASCADE)
- `sender_user_id` → `users.id` (ON DELETE CASCADE)
- `receiver_owner_id` → `users.id` (ON DELETE CASCADE)

**Unique Constraint:**
- `uq_listing_sender`: یک کاربر نمی‌تواند دو بار برای یک خانه درخواست بدهد

---

#### 5.1.4. messages

**هدف:** ذخیره پیام‌های بین کاربران

| ستون | نوع | توضیح |
|------|-----|-------|
| `id` | INT | شناسه یکتا (Primary Key) |
| `house_id` | INT | شناسه خانه (Foreign Key → houses.id) |
| `sender_id` | INT | شناسه فرستنده (Foreign Key → users.id) |
| `receiver_id` | INT | شناسه گیرنده (Foreign Key → users.id) |
| `content` | TEXT | محتوای پیام |
| `is_read` | TINYINT(1) | خوانده شده (0/1) |
| `created_at` | TIMESTAMP | تاریخ ایجاد |

**Foreign Keys:**
- `house_id` → `houses.id` (ON DELETE CASCADE)
- `sender_id` → `users.id` (ON DELETE CASCADE)
- `receiver_id` → `users.id` (ON DELETE CASCADE)

---

#### 5.1.5. house_photos

**هدف:** ذخیره عکس‌های خانه

| ستون | نوع | توضیح |
|------|-----|-------|
| `id` | INT | شناسه یکتا (Primary Key) |
| `house_id` | INT | شناسه خانه (Foreign Key → houses.id) |
| `file_path` | VARCHAR(255) | مسیر فایل |
| `is_primary` | TINYINT(1) | عکس اصلی (0/1) |
| `created_at` | TIMESTAMP | تاریخ ایجاد |

---

#### 5.1.6. favorites

**هدف:** ذخیره علاقه‌مندی‌های کاربران

| ستون | نوع | توضیح |
|------|-----|-------|
| `id` | INT | شناسه یکتا (Primary Key) |
| `user_id` | INT | شناسه کاربر (Foreign Key → users.id) |
| `house_id` | INT | شناسه خانه (Foreign Key → houses.id) |
| `created_at` | TIMESTAMP | تاریخ ایجاد |

**Unique Constraint:**
- `uq_fav`: یک کاربر نمی‌تواند دو بار یک خانه را به علاقه‌مندی اضافه کند

---

#### 5.1.7. notifications

**هدف:** ذخیره اعلان‌های کاربران

| ستون | نوع | توضیح |
|------|-----|-------|
| `id` | INT | شناسه یکتا (Primary Key) |
| `user_id` | INT | شناسه کاربر (Foreign Key → users.id) |
| `title` | VARCHAR(150) | عنوان اعلان |
| `message` | TEXT | پیام اعلان |
| `type` | VARCHAR(50) | نوع اعلان |
| `related_id` | INT | شناسه مرتبط |
| `is_read` | TINYINT(1) | خوانده شده (0/1) |
| `created_at` | TIMESTAMP | تاریخ ایجاد |

---

### 5.2. روابط جداول (ERD)

```
users (1) ────< (many) houses
users (1) ────< (many) requests (sender)
users (1) ────< (many) requests (receiver)
houses (1) ────< (many) requests
houses (1) ────< (many) house_photos
houses (1) ────< (many) messages
users (1) ────< (many) messages (sender)
users (1) ────< (many) messages (receiver)
users (1) ────< (many) favorites
users (1) ────< (many) notifications
```

---

## 6. ویژگی‌های پیاده‌سازی شده

### 6.1. سیستم احراز هویت

#### 6.1.1. ثبت‌نام

**صفحه:** `pages/register.php`

**ویژگی‌ها:**
- ✅ ثبت‌نام با ایمیل و تلفن
- ✅ انتخاب نوع کاربر (صاحب‌خانه/هم‌خانه)
- ✅ انتخاب جنسیت
- ✅ اعتبارسنجی کامل
- ✅ هش کردن رمز عبور با BCrypt
- ✅ بررسی تکراری نبودن ایمیل/تلفن

**جریان کار:**

```
1. کاربر فرم ثبت‌نام را پر می‌کند
2. اعتبارسنجی سمت کلاینت (JavaScript)
3. ارسال به سرور
4. اعتبارسنجی سمت سرور (Validator)
5. بررسی تکراری نبودن
6. هش کردن رمز عبور
7. ذخیره در دیتابیس
8. ایجاد Session
9. هدایت به داشبورد
```

---

#### 6.1.2. ورود

**صفحه:** `pages/login.php`

**ویژگی‌ها:**
- ✅ ورود با ایمیل یا تلفن
- ✅ بررسی رمز عبور
- ✅ ایجاد Session امن
- ✅ بازتولید Session ID (امنیت)
- ✅ هدایت بر اساس نوع کاربر

---

### 6.2. داشبورد

#### 6.2.1. داشبورد صاحب‌خانه

**صفحه:** `pages/dashboard.php`

**ویژگی‌ها:**
- ✅ نمایش آمار خانه‌ها (کل، فعال، در انتظار)
- ✅ نمایش درخواست‌های جدید
- ✅ لینک‌های سریع:
  - افزودن خانه جدید
  - خانه‌های من
  - درخواست‌ها
  - پیام‌ها

---

#### 6.2.2. داشبورد هم‌خانه

**ویژگی‌ها:**
- ✅ نمایش درخواست‌های من
- ✅ نمایش پیشنهادات
- ✅ لینک‌های سریع:
  - جستجوی خانه
  - علاقه‌مندی‌ها
  - پیام‌ها
  - تست شخصیت

---

### 6.3. مدیریت خانه‌ها

#### 6.3.1. افزودن خانه

**صفحه:** `pages/add_house.php`

**ویژگی‌ها:**
- ✅ فرم کامل اطلاعات خانه
- ✅ آپلود چند عکس
- ✅ اعتبارسنجی کامل
- ✅ ذخیره در دیتابیس
- ✅ ایجاد رکورد در `house_photos`

---

#### 6.3.2. ویرایش خانه

**صفحه:** `pages/edit_house.php`

**ویژگی‌ها:**
- ✅ بارگذاری اطلاعات موجود
- ✅ ویرایش تمام فیلدها
- ✅ حذف/افزودن عکس
- ✅ به‌روزرسانی ظرفیت

---

#### 6.3.3. لیست خانه‌های من

**صفحه:** `pages/my_houses.php`

**ویژگی‌ها:**
- ✅ نمایش تمام خانه‌های کاربر
- ✅ نمایش وضعیت هر خانه
- ✅ دکمه‌های عملیات:
  - مشاهده جزئیات
  - ویرایش
  - حذف

---

#### 6.3.4. جزئیات خانه من

**صفحه:** `pages/my_house_details.php`

**ویژگی‌ها:**
- ✅ نمایش کامل اطلاعات خانه
- ✅ گالری عکس‌ها
- ✅ آمار درخواست‌ها:
  - کل درخواست‌ها
  - در انتظار
  - تایید شده
  - رد شده
- ✅ آمار مستأجران:
  - مستأجران فعلی
  - مستأجران قبلی
- ✅ آمار پیام‌ها:
  - کل پیام‌ها
  - خوانده نشده
- ✅ دکمه‌های عملیات:
  - ویرایش
  - مشاهده درخواست‌ها
  - پیام‌ها

---

### 6.4. سیستم درخواست‌ها

#### 6.4.1. ارسال درخواست

**صفحه:** `pages/listing_details.php` + `pages/send_listing_request.php`

**ویژگی‌ها:**
- ✅ بررسی اجازه ارسال (جنسیت، ظرفیت)
- ✅ فرم درخواست:
  - پیام
  - تاریخ ورود
  - مدت اقامت
  - بودجه
- ✅ اعتبارسنجی
- ✅ ذخیره در `requests`
- ✅ ایجاد اعلان برای صاحب خانه

**قوانین:**
- ❌ کاربر نمی‌تواند برای خانه خود درخواست بدهد
- ❌ کاربر نمی‌تواند دو بار برای یک خانه درخواست بدهد
- ❌ جنسیت باید مطابقت داشته باشد
- ❌ ظرفیت باید خالی باشد

---

#### 6.4.2. مدیریت درخواست‌ها (صاحب‌خانه)

**صفحه:** `pages/house_requests.php`

**ویژگی‌ها:**
- ✅ نمایش تمام درخواست‌ها برای خانه‌های کاربر
- ✅ فیلتر بر اساس وضعیت
- ✅ نمایش اطلاعات متقاضی:
  - نام
  - امتیاز
  - نوع شخصیت
  - آواتار
- ✅ دکمه‌های عملیات:
  - تأیید
  - رد
  - مشاهده پروفایل
  - ارسال پیام

**جریان تأیید:**

```
1. صاحب‌خانه روی "تأیید" کلیک می‌کند
2. بررسی ظرفیت خالی
3. به‌روزرسانی وضعیت درخواست به "approved"
4. کاهش ظرفیت خالی خانه
5. ایجاد اعلان برای متقاضی
6. اگر ظرفیت پر شد، غیرفعال کردن خانه
```

---

#### 6.4.3. درخواست‌های من (هم‌خانه)

**صفحه:** `pages/my_requests.php`

**ویژگی‌ها:**
- ✅ نمایش تمام درخواست‌های کاربر
- ✅ فیلتر بر اساس وضعیت
- ✅ نمایش اطلاعات خانه
- ✅ دکمه لغو درخواست

---

### 6.5. سیستم پیام‌رسانی

**صفحه:** `pages/messages.php`

**ویژگی‌ها:**
- ✅ لیست مخاطبین
- ✅ نمایش مکالمه
- ✅ ارسال پیام جدید
- ✅ علامت‌گذاری خوانده شده
- ✅ شمارش پیام‌های خوانده نشده

**قوانین:**
- ✅ فقط کاربرانی که درخواست فعال دارند می‌توانند پیام بفرستند
- ✅ پیام‌ها مرتبط با یک خانه خاص هستند

---

### 6.6. سیستم علاقه‌مندی‌ها

**صفحه:** `pages/favorites.php`

**ویژگی‌ها:**
- ✅ افزودن/حذف علاقه‌مندی
- ✅ لیست علاقه‌مندی‌ها
- ✅ لینک به جزئیات خانه

**API:** `pages/toggle_favorite.php` (AJAX)

---

### 6.7. تست شخصیت MBTI

**صفحه:** `pages/personality_test.php`

**ویژگی‌ها:**
- ✅ 60 سوال تست MBTI
- ✅ محاسبه نوع شخصیت
- ✅ ذخیره در پروفایل کاربر
- ✅ استفاده در الگوریتم تطبیق

---

### 6.8. جستجوی خانه‌ها

**صفحه:** `pages/available_houses.php`

**ویژگی‌ها:**
- ✅ فیلتر پیشرفته:
  - شهر
  - استان
  - جنسیت
  - محدوده قیمت
- ✅ مرتب‌سازی:
  - جدیدترین
  - قدیمی‌ترین
  - ارزان‌ترین
  - گران‌ترین
- ✅ Pagination
- ✅ نمایش کارت خانه‌ها

---

## 7. UI/UX Specification

### 7.1. طراحی کلی

**تم:** مدرن، تمیز، حرفه‌ای

**رنگ‌ها:**
- Primary: `#4A90E2` (آبی)
- Success: `#28A745` (سبز)
- Danger: `#DC3545` (قرمز)
- Warning: `#FFC107` (زرد)
- Info: `#17A2B8` (آبی روشن)

**فونت:**
- فارسی: Tahoma, Arial
- انگلیسی: Arial, sans-serif

---

### 7.2. صفحات اصلی

#### 7.2.1. صفحه اصلی (Landing Page)

**فایل:** `index.php`

**بخش‌ها:**
1. **Header**: نوار ناوبری + دکمه ورود/ثبت‌نام
2. **Hero Section**: عنوان + دکمه CTA
3. **Features**: 6 کارت ویژگی
4. **Statistics**: آمار واقعی از دیتابیس
5. **How It Works**: مراحل کار
6. **Testimonials**: نظرات کاربران
7. **Footer**: لینک‌ها و اطلاعات

**انیمیشن‌ها:**
- Particles.js برای پس‌زمینه
- Intersection Observer برای انیمیشن اسکرول
- Counter Animation برای آمار

---

#### 7.2.2. داشبورد

**المان‌ها:**
- نوار ناوبری بالا (Dashboard, Logout)
- کارت‌های آمار
- لینک‌های سریع
- پس‌زمینه داینامیک (particles.js)

---

#### 7.2.3. فرم‌ها

**المان‌های مشترک:**
- Label در بالا
- Input با border radius
- پیام خطا زیر input
- دکمه Submit با انیمیشن hover
- اعتبارسنجی Real-time

---

### 7.3. Navigation Flow

```
Landing Page (index.php)
    ↓
[ورود/ثبت‌نام]
    ↓
Dashboard
    ↓
┌─────────────┬─────────────┐
│ صاحب‌خانه   │ هم‌خانه      │
└─────────────┴─────────────┘
    ↓              ↓
My Houses    Available Houses
    ↓              ↓
House Details → Send Request
    ↓              ↓
Requests      My Requests
    ↓              ↓
Messages      Messages
```

---

## 8. بهترین روش‌ها

### 8.1. امنیت

#### 8.1.1. SQL Injection Prevention

✅ **استفاده از Prepared Statements:**

```php
// ✅ درست
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);

// ❌ اشتباه
$db->query("SELECT * FROM users WHERE id = $id");
```

#### 8.1.2. XSS Prevention

✅ **پاکسازی خروجی:**

```php
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');
```

#### 8.1.3. CSRF Protection

✅ **استفاده از Token:**

```php
// ایجاد Token
$token = bin2hex(random_bytes(32));
Session::set('csrf_token', $token);

// بررسی Token
if ($_POST['csrf_token'] !== Session::get('csrf_token')) {
    die('Invalid CSRF token');
}
```

#### 8.1.4. Password Hashing

✅ **استفاده از BCrypt:**

```php
$hash = password_hash($password, PASSWORD_BCRYPT);
if (password_verify($password, $hash)) {
    // رمز صحیح است
}
```

#### 8.1.5. Session Security

✅ **بازتولید Session ID:**

```php
Session::regenerate(); // بعد از لاگین
```

---

### 8.2. Performance

#### 8.2.1. Database Optimization

✅ **استفاده از Indexes:**

```sql
CREATE INDEX idx_city ON houses(city);
CREATE INDEX idx_status ON houses(status);
```

✅ **استفاده از LIMIT:**

```php
$houses = $house->search($filters, 'newest', 12, 0); // Pagination
```

#### 8.2.2. Caching

✅ **Cache کردن Query های سنگین:**

```php
// استفاده از Session برای cache
if (!Session::has('user_stats')) {
    $stats = computeStats();
    Session::set('user_stats', $stats);
}
```

---

### 8.3. Validation

✅ **اعتبارسنجی سمت سرور:**

```php
$validator = new Validator();
$rules = [
    'email' => 'required|email',
    'password' => 'required|min:8'
];
if (!$validator->validate($_POST, $rules)) {
    // نمایش خطاها
}
```

✅ **اعتبارسنجی سمت کلاینت (JavaScript):**

```javascript
function validateForm() {
    const email = document.getElementById('email').value;
    if (!isValidEmail(email)) {
        showError('ایمیل معتبر نیست');
        return false;
    }
    return true;
}
```

---

### 8.4. Error Handling

✅ **استفاده از Try-Catch:**

```php
try {
    $result = $db->query($sql);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    // نمایش پیام خطای عمومی به کاربر
    echo "خطایی رخ داد. لطفا دوباره تلاش کنید.";
}
```

✅ **Logging:**

```php
error_log("User login failed: " . $email);
```

---

## 9. راهنمای توسعه

### 9.1. نصب و راه‌اندازی

#### 9.1.1. پیش‌نیازها

- PHP 8.0+
- MySQL 8.0+
- Apache/Nginx
- Composer (اختیاری)

#### 9.1.2. مراحل نصب

1. **Clone Repository:**

```bash
git clone https://github.com/AlirezaTimas/Hamotaghi.git
cd Hamotaghi
```

2. **ایجاد دیتابیس:**

```bash
mysql -u root -p < database/hamotaghi_complete.sql
```

3. **تنظیمات:**

ویرایش `includes/config.php`:

```php
$db_host = 'localhost';
$db_name = 'hamotaghi_db';
$db_user = 'root';
$db_pass = '';
```

4. **تنظیمات آپلود:**

```php
// در php.ini
upload_max_filesize = 10M
post_max_size = 10M
```

---

### 9.2. افزودن فیچر جدید

#### 9.2.1. مراحل

1. **ایجاد Model (اگر نیاز باشد):**

```php
class NewModel extends BaseModel
{
    protected string $table = 'new_table';
    protected string $primaryKey = 'id';
}
```

2. **ایجاد Controller (اگر نیاز باشد):**

```php
class NewController extends BaseController
{
    public function index() {
        // منطق
    }
}
```

3. **ایجاد View:**

```php
// pages/new_feature.php
<?php
require_once __DIR__ . '/../includes/config.php';
// کد صفحه
?>
```

4. **به‌روزرسانی Navigation:**

افزودن لینک به نوار ناوبری

---

### 9.3. Testing

#### 9.3.1. Manual Testing Checklist

- [ ] ثبت‌نام کاربر جدید
- [ ] ورود کاربر
- [ ] افزودن خانه
- [ ] جستجوی خانه
- [ ] ارسال درخواست
- [ ] تأیید/رد درخواست
- [ ] ارسال پیام
- [ ] تست شخصیت

---

## 10. Roadmap آینده

### 10.1. فیچرهای پیشنهادی

#### 10.1.1. کوتاه‌مدت (1-3 ماه)

- [ ] **سیستم پرداخت آنلاین**
  - اتصال به درگاه پرداخت
  - پرداخت پیش‌پرداخت
  - مدیریت تراکنش‌ها

- [ ] **اعلان‌های Real-time**
  - استفاده از WebSocket
  - اعلان فوری پیام‌ها
  - اعلان درخواست‌های جدید

- [ ] **سیستم Review و Rating**
  - امتیازدهی متقابل
  - نظرات کاربران
  - نمایش در پروفایل

#### 10.1.2. میان‌مدت (3-6 ماه)

- [ ] **اپلیکیشن موبایل**
  - React Native
  - Push Notifications
  - دسترسی به دوربین

- [ ] **سیستم پیشنهاد هوشمند**
  - Machine Learning
  - بهبود الگوریتم تطبیق
  - پیشنهادات شخصی‌سازی شده

- [ ] **چت Real-time**
  - WebSocket
  - ارسال فایل
  - تایپینگ Indicator

#### 10.1.3. بلندمدت (6-12 ماه)

- [ ] **API عمومی**
  - RESTful API
  - Documentation (Swagger)
  - Rate Limiting

- [ ] **پنل مدیریت پیشرفته**
  - مدیریت کاربران
  - آمار و گزارشات
  - مدیریت محتوا

- [ ] **سیستم چندزبانه**
  - پشتیبانی از انگلیسی
  - RTL/LTR Support

---

### 10.2. Refactorهای لازم

#### 10.2.1. تبدیل به Framework

**پیشنهاد:** استفاده از Laravel یا Symfony

**دلایل:**
- ساختار استاندارد
- پشتیبانی بهتر
- Package های آماده
- Testing بهتر

#### 10.2.2. استفاده از ORM

**پیشنهاد:** Eloquent (Laravel) یا Doctrine

**مزایا:**
- کد تمیزتر
- روابط خودکار
- Migration ها

#### 10.2.3. Frontend Framework

**پیشنهاد:** Vue.js یا React

**مزایا:**
- Component-based
- State Management
- Better UX

---

### 10.3. بهبودهای Performance

- [ ] **Caching Layer**
  - Redis برای Cache
  - Query Caching
  - Session Caching

- [ ] **CDN**
  - آپلود تصاویر به CDN
  - کاهش بار سرور

- [ ] **Database Optimization**
  - Query Optimization
  - Index Tuning
  - Partitioning

---

## 11. تغییرات اعمال شده

### 11.1. فایل‌های ساخته شده

1. **`pages/my_house_details.php`**
   - صفحه جدید برای نمایش جزئیات خانه برای صاحب‌خانه
   - نمایش آمار کامل
   - لینک به صفحات مرتبط

2. **`pages/listing_details.php`**
   - صفحه نمایش جزئیات خانه برای هم‌خانه
   - دکمه ارسال درخواست
   - گالری عکس‌ها

3. **`pages/send_listing_request.php`**
   - API برای ارسال درخواست (AJAX)
   - اعتبارسنجی کامل
   - بررسی قوانین

---

### 11.2. فایل‌های تغییر یافته

1. **`pages/my_houses.php`**
   - افزودن دکمه "مشاهده جزئیات"
   - لینک به `my_house_details.php`

2. **`pages/house_requests.php`**
   - رفع باگ نمایش درخواست‌ها
   - بهبود Query ها
   - مدیریت وضعیت‌های انگلیسی/فارسی

3. **`pages/messages.php`**
   - رفع باگ بررسی اجازه پیام
   - بهبود Query ها
   - مدیریت جداول `requests` و `matches`

4. **`index.php`**
   - افزودن آیکون به بخش "اعتماد و اطمینان"
   - حذف بخش "مدیریت هزینه‌ها"
   - آمار واقعی از دیتابیس
   - افزودن کارت ششم

5. **`pages/login.php` و `pages/register.php`**
   - رفع مشکل اسکرول
   - افزودن دکمه "بازگشت به خانه"

---

### 11.3. دلیل تغییرات

1. **افزودن `my_house_details.php`:**
   - نیاز کاربران به مشاهده جزئیات کامل خانه
   - نمایش آمار برای تصمیم‌گیری بهتر

2. **رفع باگ‌های `house_requests.php`:**
   - مشکل نمایش درخواست‌ها برای صاحب‌خانه
   - عدم تطابق فیلدهای دیتابیس

3. **رفع باگ‌های `messages.php`:**
   - خطای 500 هنگام ارسال پیام
   - مشکل در بررسی اجازه پیام

4. **بهبود `index.php`:**
   - UI بهتر
   - آمار واقعی برای اعتماد بیشتر

---

## 12. نتیجه‌گیری

پروژه **هم‌اتاقی (Hamotaghi)** یک پلتفرم کامل و حرفه‌ای برای پیدا کردن هم‌اتاقی است که با استفاده از معماری MVC و بهترین روش‌های توسعه نرم‌افزار پیاده‌سازی شده است.

### نقاط قوت:

✅ معماری تمیز و قابل نگهداری  
✅ امنیت بالا (Prepared Statements, Password Hashing)  
✅ UI/UX مدرن و کاربرپسند  
✅ سیستم تطبیق هوشمند  
✅ رعایت قوانین فرهنگی ایران  

### نقاط قابل بهبود:

⚠️ تبدیل به Framework (Laravel)  
⚠️ استفاده از ORM  
⚠️ افزودن Unit Tests  
⚠️ بهبود Performance با Caching  

---

<div style="text-align: center; direction: rtl; margin-top: 50px; padding-top: 20px; border-top: 2px solid #ddd;">

**پایان مستندات**

**نسخه:** 1.0  
**تاریخ:** 1403/09/08  
**وضعیت:** ✅ کامل

---

*این مستندات به‌طور مداوم به‌روزرسانی می‌شوند.*

</div>

