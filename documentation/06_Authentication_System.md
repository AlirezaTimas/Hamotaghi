# مستندات کامل: سیستم احراز هویت

## مقدمه

سیستم احراز هویت شامل ورود، ثبت‌نام و مدیریت session است.

### فایل‌های مرتبط
- `pages/login.php` - ورود کاربر
- `pages/register.php` - ثبت‌نام کاربر
- `pages/logout.php` - خروج
- `core/Auth.php` - کلاس احراز هویت
- `core/Session.php` - مدیریت session

---

## معماری

### معماری: MVC با Core Classes

**کلاس‌های اصلی**:
- `Auth`: مدیریت احراز هویت
- `Session`: مدیریت session
- `Database`: اتصال دیتابیس

---

## کلاس‌ها و متدها

### `core/Auth.php`

```php
class Auth {
    public static function login(string $email, string $password): ?User
    {
        $user = UserRepository::findByEmail($email);
        if ($user && password_verify($password, $user->password)) {
            Session::set('user_id', $user->id);
            return $user;
        }
        return null;
    }
    
    public static function logout(): void
    {
        Session::destroy();
    }
    
    public static function check(): bool
    {
        return Session::has('user_id');
    }
}
```

### `core/Session.php`

```php
class Session {
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }
    
    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }
}
```

---

## جداول دیتابیس

### `users`
- `email`: ایمیل (UNIQUE)
- `password`: رمز عبور (hashed با bcrypt)
- `usertype`: نوع کاربری
- `is_active`: وضعیت فعال بودن

---

## امنیت

1. **Password Hashing**: استفاده از `password_hash()` و `password_verify()`
2. **Session Security**: تنظیمات امنیتی session
3. **Input Validation**: اعتبارسنجی کامل ورودی‌ها
4. **SQL Injection Prevention**: Prepared Statements

---

**تاریخ ایجاد**: 1403/09/08  
**نسخه**: 1.0

