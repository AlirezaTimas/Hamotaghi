# دستورالعمل Git و GitHub

## پیش‌نیازها

1. نصب Git: [https://git-scm.com/downloads](https://git-scm.com/downloads)
2. اتصال به GitHub repository

## مراحل Commit و Push

### 1. بررسی وضعیت Git

```bash
git status
```

### 2. افزودن تمام فایل‌ها

```bash
git add .
```

یا برای افزودن فایل‌های خاص:

```bash
git add pages/
git add documentation/
git add database/
```

### 3. ایجاد Branch جدید

```bash
git checkout -b cursor/full-upload
```

### 4. Commit تغییرات

```bash
git commit -m "feat: اضافه کردن تمام ویژگی‌های جدید

- اضافه کردن صفحه مشاهده جزئیات و ارسال درخواست
- اضافه کردن صفحه جزئیات خانه من برای صاحب‌خانه
- بهبود سیستم مدیریت درخواست‌ها
- بهبود سیستم پیام‌رسانی
- اضافه کردن مستندات کامل فارسی
- بهبود UI/UX تمام صفحات
- رفع باگ‌ها و بهبود امنیت"
```

### 5. Push به GitHub

```bash
git push origin cursor/full-upload
```

### 6. ایجاد Pull Request

1. رفتن به GitHub repository
2. کلیک روی "Compare & pull request"
3. انتخاب base branch: `main`
4. انتخاب compare branch: `cursor/full-upload`
5. اضافه کردن توضیحات
6. کلیک "Create pull request"

## ساختار فایل‌های Commit شده

```
public_html/
├── pages/
│   ├── listing_details.php (جدید)
│   ├── send_listing_request.php (جدید)
│   ├── my_house_details.php (جدید)
│   ├── house_requests.php (بهبود یافته)
│   ├── messages.php (بهبود یافته)
│   └── ...
├── documentation/
│   ├── 01_View_Details_and_Send_Request.md
│   ├── 02_My_House_Details.md
│   ├── 03_House_Management.md
│   ├── 04_Request_Management.md
│   ├── 05_Messaging_System.md
│   ├── 06_Authentication_System.md
│   ├── 07_Dashboard.md
│   ├── 08_Profile_Management.md
│   ├── 09_Favorites_System.md
│   ├── 10_Personality_Test.md
│   ├── 11_House_Search.md
│   └── GIT_INSTRUCTIONS.md
├── database/
│   └── hamotaghi_complete.sql
└── ...
```

## تبدیل Markdown به PDF

### روش 1: استفاده از Pandoc

```bash
# نصب Pandoc
# Windows: choco install pandoc
# Mac: brew install pandoc
# Linux: sudo apt-get install pandoc

# تبدیل به PDF
pandoc documentation/01_View_Details_and_Send_Request.md -o documentation/01_View_Details_and_Send_Request.pdf --pdf-engine=xelatex -V mainfont="Tahoma" -V dir=rtl
```

### روش 2: استفاده از ابزارهای آنلاین

1. [Markdown to PDF](https://www.markdowntopdf.com/)
2. [Dillinger](https://dillinger.io/)
3. [StackEdit](https://stackedit.io/)

### روش 3: استفاده از VS Code Extension

1. نصب extension "Markdown PDF"
2. باز کردن فایل Markdown
3. کلیک راست → "Markdown PDF: Export (pdf)"

## نکات مهم

1. **قبل از Commit**: بررسی کنید که تمام فایل‌ها درست کار می‌کنند
2. **پیام Commit**: از پیام‌های واضح و توصیفی استفاده کنید
3. **Branch Name**: از نام branch مشخص استفاده کنید
4. **Pull Request**: توضیحات کامل در PR اضافه کنید

## Troubleshooting

### خطای "git not found"
- اطمینان از نصب Git
- اضافه کردن Git به PATH

### خطای "remote not found"
```bash
git remote add origin https://github.com/username/repository.git
```

### خطای "permission denied"
- بررسی دسترسی‌های GitHub
- استفاده از Personal Access Token

---

**تاریخ ایجاد**: 1403/09/08

