# مراحل بعد از `git add .`

## ✅ کارهای انجام شده

تمام فایل‌های غیرضروری حذف شدند:
- ✅ فایل‌های تست و دیباگ
- ✅ فایل‌های مستندات موقت
- ✅ اسکریپت‌های موقت
- ✅ فایل‌های migration قدیمی

---

## 📋 مراحل بعدی

### 1. اجرای `git add .`

```powershell
git add .
```

این دستور تمام فایل‌های موجود در دایرکتوری را به staging area اضافه می‌کند.

---

### 2. بررسی فایل‌های اضافه شده

```powershell
git status
```

این دستور لیست فایل‌هایی که اضافه شده‌اند را نمایش می‌دهد. بررسی کنید که:
- ✅ تمام فایل‌های PHP موجود باشند
- ✅ فایل‌های SQL موجود باشند
- ✅ پوشه documentation موجود باشد
- ✅ فایل‌های assets موجود باشند

---

### 3. Commit تغییرات

```powershell
git commit -m "Full project upload with latest features and documentation"
```

این دستور تمام تغییرات را commit می‌کند.

**نکته**: اگر اولین commit است، ممکن است از شما نام و ایمیل Git بخواهد:
```powershell
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"
```

---

### 4. Push به GitHub

```powershell
git push -u origin cursor/full-upload
```

این دستور branch را به GitHub push می‌کند.

**نکته**: اگر خطای authentication داد:
- از Personal Access Token استفاده کنید
- یا از GitHub CLI: `gh auth login`

---

### 5. ایجاد Pull Request

پس از push موفق:

**لینک مستقیم:**
```
https://github.com/AlirezaTimas/Hamotaghi/compare/main...cursor/full-upload
```

**یا از طریق GitHub:**
1. رفتن به: https://github.com/AlirezaTimas/Hamotaghi
2. کلیک "Compare & pull request" (اگر نمایش داده شد)
3. یا: Pull requests → New pull request
4. انتخاب:
   - Base: `main`
   - Compare: `cursor/full-upload`
5. عنوان: "Full project upload with latest features and documentation"
6. توضیحات (اختیاری):
   ```
   این PR شامل:
   - تمام ویژگی‌های جدید (مشاهده جزئیات، ارسال درخواست، جزئیات خانه من)
   - مستندات کامل فارسی (11 ماژول)
   - بهبود UI/UX
   - رفع باگ‌ها و بهبود امنیت
   ```
7. کلیک "Create pull request"

---

## 📝 دستورات کامل (کپی و اجرا)

```powershell
# 1. افزودن فایل‌ها
git add .

# 2. بررسی وضعیت
git status

# 3. Commit
git commit -m "Full project upload with latest features and documentation"

# 4. Push
git push -u origin cursor/full-upload

# 5. ایجاد Pull Request (از طریق مرورگر)
# https://github.com/AlirezaTimas/Hamotaghi/compare/main...cursor/full-upload
```

---

## ✅ تأیید موفقیت

پس از انجام مراحل، باید:

- ✅ `git status` نشان دهد "nothing to commit, working tree clean"
- ✅ `git push` موفق باشد
- ✅ Branch `cursor/full-upload` در GitHub نمایش داده شود
- ✅ امکان ایجاد Pull Request وجود داشته باشد

---

## 🆘 در صورت مشکل

### خطای "nothing to commit"
- بررسی کنید که فایل‌ها اضافه شده‌اند: `git status`
- اگر فایل‌ها commit شده‌اند، این طبیعی است

### خطای "authentication failed"
- استفاده از Personal Access Token
- یا: `gh auth login`

### خطای "remote not found"
```powershell
git remote add origin https://github.com/AlirezaTimas/Hamotaghi.git
```

---

**پس از `git add .`، مراحل بالا را دنبال کنید!**

