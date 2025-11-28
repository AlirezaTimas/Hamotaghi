# مستندات کامل: تست شخصیت MBTI

## مقدمه

تست شخصیت MBTI برای تطبیق بهتر کاربران استفاده می‌شود.

### فایل‌های مرتبط
- `pages/personality_test.php` - صفحه تست

---

## معماری

### معماری: Procedural PHP

---

## منطق

### محاسبه نوع شخصیت
```php
// جمع‌آوری پاسخ‌ها
$scores = ['E' => 0, 'I' => 0, 'S' => 0, 'N' => 0, 'T' => 0, 'F' => 0, 'J' => 0, 'P' => 0];

foreach ($answers as $answer) {
    $scores[$answer]++;
}

// تعیین نوع شخصیت
$personality = '';
$personality .= ($scores['E'] > $scores['I']) ? 'E' : 'I';
$personality .= ($scores['S'] > $scores['N']) ? 'S' : 'N';
$personality .= ($scores['T'] > $scores['F']) ? 'T' : 'F';
$personality .= ($scores['J'] > $scores['P']) ? 'J' : 'P';
```

### ذخیره نتیجه
```php
$updateStmt = $conn->prepare("
    UPDATE users SET personality_type = ? WHERE id = ?
");
```

---

## جداول دیتابیس

### `users`
- `personality_type`: نوع شخصیت (ENFP, ISTJ, ...)

---

**تاریخ ایجاد**: 1403/09/08  
**نسخه**: 1.0

