# مستندات کامل: داشبورد کاربری

## مقدمه

داشبورد صفحه اصلی کاربر پس از ورود است که آمار و اطلاعات کلی را نمایش می‌دهد.

### فایل‌های مرتبط
- `pages/dashboard.php` - صفحه اصلی داشبورد

### ویژگی‌های کلیدی
- نمایش آمار بر اساس نوع کاربری
- لینک‌های سریع به بخش‌های مختلف
- نمایش آخرین فعالیت‌ها
- رابط کاربری یکپارچه

---

## معماری

### معماری: Procedural PHP با Conditional Logic

---

## منطق

### آمار صاحب‌خانه
```php
$house_stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_houses,
        SUM(CASE WHEN status = 'فعال' THEN 1 ELSE 0 END) as active_houses,
        SUM(CASE WHEN status = 'در انتظار تایید' THEN 1 ELSE 0 END) as pending_houses
    FROM houses 
    WHERE user_id = ?
");
```

### آمار متقاضی
```php
$request_stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'در انتظار' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status = 'تایید شده' THEN 1 ELSE 0 END) as accepted_requests
    FROM requests 
    WHERE user_id = ?
");
```

---

## جریان کار

1. **بررسی ورود**: بررسی session
2. **دریافت نوع کاربری**: Query از users
3. **محاسبه آمار**: Query بر اساس نوع کاربری
4. **نمایش**: نمایش آمار و لینک‌ها

---

**تاریخ ایجاد**: 1403/09/08  
**نسخه**: 1.0

