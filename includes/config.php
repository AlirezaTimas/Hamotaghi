<?php
/**
 * Legacy Config File - Backward Compatibility
 * For new MVC code, use core/bootstrap.php and config/app.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- تنظیمات دیتابیس ----------
$host = 'localhost';        // هاست دیتابیس
$db_name = 'hamotaghi_db';  // نام دیتابیس شما
$username = 'root';         // یوزر دیتابیس (برای XAMPP معمولاً root است)
$password = '';             // رمز دیتابیس (معمولاً خالی است)

// ---------- اتصال به دیتابیس با PDO ----------
try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$db_name;charset=utf8mb4",
        $username,
        $password
    );

    // حالت خطا: Exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // تنظیم charset برای فارسی و ایموجی‌ها
    $conn->exec("SET NAMES utf8mb4");

} catch (PDOException $e) {
    die("<strong>خطا در اتصال به دیتابیس:</strong> " . $e->getMessage());
}

// ---------- Load MVC Core (if available) ----------
// Only load bootstrap if not already loaded to prevent conflicts
if (!defined('BOOTSTRAP_LOADED') && file_exists(__DIR__ . '/../core/bootstrap.php')) {
    try {
        require_once __DIR__ . '/../core/bootstrap.php';
    } catch (Exception $e) {
        // If bootstrap fails, continue with legacy config
        error_log("Bootstrap load failed: " . $e->getMessage());
    }
}
