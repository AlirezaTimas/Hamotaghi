<?php
// دریافت آمار واقعی از دیتابیس
require_once __DIR__ . '/includes/config.php';

$stats_data = [
    'total_users' => 0,
    'total_houses' => 0,
    'total_requests' => 0,
    'total_cities' => 0,
    'satisfaction_rate' => 95
];

try {
    // تعداد کاربران فعال
    $users_stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $users_result = $users_stmt->fetch(PDO::FETCH_ASSOC);
    $stats_data['total_users'] = (int)($users_result['count'] ?? 0);
    
    // تعداد خانه‌های فعال
    $houses_stmt = $conn->query("SELECT COUNT(*) as count FROM houses WHERE status = 'فعال'");
    $houses_result = $houses_stmt->fetch(PDO::FETCH_ASSOC);
    $stats_data['total_houses'] = (int)($houses_result['count'] ?? 0);
    
    // تعداد درخواست‌های موفق (تایید شده)
    $total_requests = 0;
    try {
        $requests_exists = $conn->query("SHOW TABLES LIKE 'requests'")->rowCount() > 0;
        if ($requests_exists) {
            $requests_stmt = $conn->query("
                SELECT COUNT(*) as count 
                FROM requests 
                WHERE request_status = 'approved' OR request_status = 'تایید شده'
            ");
            $requests_result = $requests_stmt->fetch(PDO::FETCH_ASSOC);
            $total_requests += (int)($requests_result['count'] ?? 0);
        }
    } catch (PDOException $e) {
        // Ignore
    }
    
    try {
        $matches_exists = $conn->query("SHOW TABLES LIKE 'matches'")->rowCount() > 0;
        if ($matches_exists) {
            $matches_stmt = $conn->query("
                SELECT COUNT(*) as count 
                FROM matches 
                WHERE status = 'تایید شده'
            ");
            $matches_result = $matches_stmt->fetch(PDO::FETCH_ASSOC);
            $total_requests += (int)($matches_result['count'] ?? 0);
        }
    } catch (PDOException $e) {
        // Ignore
    }
    
    $stats_data['total_requests'] = $total_requests;
    
    // تعداد شهرهای منحصر به فرد
    $cities_stmt = $conn->query("SELECT COUNT(DISTINCT city) as count FROM houses WHERE city IS NOT NULL AND city != ''");
    $cities_result = $cities_stmt->fetch(PDO::FETCH_ASSOC);
    $stats_data['total_cities'] = (int)($cities_result['count'] ?? 0);
    
    // محاسبه نرخ رضایت (بر اساس امتیاز کاربران)
    $satisfaction_stmt = $conn->query("
        SELECT AVG(user_score) as avg_score 
        FROM users 
        WHERE user_score > 0 AND is_active = 1
    ");
    $satisfaction_result = $satisfaction_stmt->fetch(PDO::FETCH_ASSOC);
    if ($satisfaction_result && $satisfaction_result['avg_score']) {
        $avg_score = (float)$satisfaction_result['avg_score'];
        $stats_data['satisfaction_rate'] = (int)round(($avg_score / 5.0) * 100);
    }
} catch (PDOException $e) {
    // در صورت خطا، از مقادیر پیش‌فرض استفاده می‌شود
    error_log("Error fetching stats: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>هم‌اتاقی | پیدا کردن هم‌خانه مطمئن</title>
  
  <!-- Bootstrap -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- فونت فارسی Vazir -->
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/font-face.css" rel="stylesheet">

  <style>
    :root {
      --navy-dark: #0f1a21;
      --navy-darker: #0b1419;
      --navy-black: #080f13;
      --gold: #ffab00;
      --gold-dark: #ff8f00;
      --gold-light: #ffd54f;
      --text-light: #ffffff;
      --text-muted: #e0e0e0;
      --card-bg: rgba(15, 26, 33, 0.85);
      --card-border: rgba(255, 171, 0, 0.25);
      --success: #4caf50;
      --warning: #ff9800;
      --info: #2196f3;
      --danger: #f44336;
      --purple: #8b5cf6;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Vazir, sans-serif;
      background: 
          linear-gradient(135deg, var(--navy-black) 0%, var(--navy-darker) 50%, var(--navy-dark) 100%),
          url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.08'/%3E%3C/svg%3E"),
          radial-gradient(circle at 10% 20%, rgba(255, 171, 0, 0.15) 0%, transparent 50%),
          radial-gradient(circle at 90% 80%, rgba(15, 26, 33, 0.4) 0%, transparent 50%),
          radial-gradient(circle at 30% 70%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
          radial-gradient(circle at 70% 30%, rgba(0, 210, 106, 0.08) 0%, transparent 50%);
      color: var(--text-light);
      min-height: 100vh;
      line-height: 1.6;
      position: relative;
      overflow-x: hidden;
    }

    /* افکت پارتیکل پویا */
    #particles-js {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: -1;
      pointer-events: none;
    }

    /* شبکه خطوط متحرک */
    .grid-lines {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: -1;
      opacity: 0.1;
      pointer-events: none;
      background-image: 
          linear-gradient(rgba(255, 171, 0, 0.1) 1px, transparent 1px),
          linear-gradient(90deg, rgba(255, 171, 0, 0.1) 1px, transparent 1px);
      background-size: 50px 50px;
      animation: gridMove 20s linear infinite;
    }

    /* افکت نورپردازی داینامیک */
    .light-spot {
      position: fixed;
      width: 300px;
      height: 300px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(255, 171, 0, 0.1) 0%, transparent 70%);
      animation: floatLight 15s ease-in-out infinite;
      z-index: -1;
    }

    .light-spot:nth-child(1) {
      top: 20%;
      left: 10%;
      animation-delay: 0s;
    }

    .light-spot:nth-child(2) {
      top: 60%;
      left: 80%;
      animation-delay: 5s;
    }

    .light-spot:nth-child(3) {
      top: 80%;
      left: 20%;
      animation-delay: 10s;
    }

    /* دکمه اسکرول به بالا */
    .scroll-to-top {
      position: fixed;
      bottom: 30px;
      left: 30px;
      width: 50px;
      height: 50px;
      background: linear-gradient(135deg, var(--gold), var(--gold-dark));
      color: var(--navy-black);
      border: none;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(255, 171, 0, 0.4);
      z-index: 1000;
      opacity: 0;
      visibility: hidden;
    }

    .scroll-to-top.show {
      opacity: 1;
      visibility: visible;
    }

    .scroll-to-top:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(255, 171, 0, 0.6);
    }

    /* ناوبری اصلی */
    .main-nav {
      background: rgba(11, 20, 25, 0.95);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--card-border);
      padding: 15px 0;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
    }

    .nav-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 0 25px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 26px;
      font-weight: 800;
      color: var(--gold);
      text-decoration: none;
      text-shadow: 0 0 20px rgba(255, 171, 0, 0.5);
    }

    .brand i {
      font-size: 30px;
    }

    .nav-links {
      display: flex;
      align-items: center;
      gap: 35px;
    }

    .nav-link {
      color: var(--text-muted);
      text-decoration: none;
      font-weight: 500;
      transition: all 0.3s ease;
      position: relative;
      padding: 8px 0;
      font-size: 16px;
    }

    .nav-link:hover {
      color: var(--gold);
    }

    .nav-link::after {
      content: '';
      position: absolute;
      bottom: 0;
      right: 0;
      width: 0;
      height: 2px;
      background: var(--gold);
      transition: width 0.3s ease;
    }

    .nav-link:hover::after {
      width: 100%;
    }

    .nav-actions {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .nav-btn {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      background: rgba(255, 171, 0, 0.1);
      color: var(--gold-light);
      text-decoration: none;
      border-radius: 12px;
      font-weight: 500;
      transition: all 0.3s ease;
      border: 1px solid rgba(255, 171, 0, 0.2);
      font-size: 15px;
    }

    .nav-btn:hover {
      background: rgba(255, 171, 0, 0.2);
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(255, 171, 0, 0.3);
    }

    .nav-btn.primary {
      background: linear-gradient(135deg, var(--gold), var(--gold-dark));
      color: var(--navy-black);
      font-weight: 600;
      border: 1px solid var(--gold);
    }

    /* هیرو سکشن */
    .hero {
      padding: 180px 0 120px;
      position: relative;
      overflow: hidden;
      background: 
          radial-gradient(circle at 20% 50%, rgba(255, 171, 0, 0.15) 0%, transparent 50%),
          radial-gradient(circle at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
    }

    .hero-content {
      max-width: 1400px;
      margin: 0 auto;
      padding: 0 25px;
      text-align: center;
      position: relative;
      z-index: 2;
    }

    .hero-title {
      font-size: 4rem;
      font-weight: 800;
      margin-bottom: 25px;
      background: linear-gradient(135deg, var(--gold), var(--gold-light), var(--purple));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      text-shadow: 0 4px 30px rgba(255, 171, 0, 0.3);
      line-height: 1.2;
    }

    .hero-subtitle {
      font-size: 1.4rem;
      color: var(--text-muted);
      margin-bottom: 45px;
      max-width: 800px;
      margin-left: auto;
      margin-right: auto;
      line-height: 1.8;
    }

    .hero-buttons {
      display: flex;
      gap: 20px;
      justify-content: center;
      flex-wrap: wrap;
    }

    .btn-hero {
      padding: 16px 35px;
      font-size: 1.2rem;
      font-weight: 600;
      border-radius: 14px;
      text-decoration: none;
      transition: all 0.4s ease;
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--gold), var(--gold-dark));
      color: var(--navy-black);
      border: 1px solid var(--gold);
      box-shadow: 0 6px 25px rgba(255, 171, 0, 0.4);
    }

    .btn-primary:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 35px rgba(255, 171, 0, 0.6);
    }

    .btn-outline {
      background: rgba(255, 255, 255, 0.1);
      color: var(--text-light);
      border: 1px solid rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(15px);
    }

    .btn-outline:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-4px);
      box-shadow: 0 10px 30px rgba(255, 255, 255, 0.1);
    }

    /* ویژگی‌ها */
    .features {
      padding: 120px 0;
      position: relative;
    }

    .section-title {
      text-align: center;
      font-size: 3rem;
      font-weight: 800;
      margin-bottom: 80px;
      color: var(--text-light);
      position: relative;
    }

    .section-title::after {
      content: '';
      position: absolute;
      bottom: -20px;
      left: 50%;
      transform: translateX(-50%);
      width: 100px;
      height: 5px;
      background: linear-gradient(90deg, var(--gold), var(--purple));
      border-radius: 3px;
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
      gap: 35px;
      max-width: 1400px;
      margin: 0 auto;
      padding: 0 25px;
    }

    .feature-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 24px;
      padding: 45px 35px;
      text-align: center;
      backdrop-filter: blur(20px);
      transition: all 0.5s ease;
      position: relative;
      overflow: hidden;
    }

    .feature-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--accent, var(--gold));
    }

    .feature-card:hover {
      transform: translateY(-12px);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
    }

    .feature-icon {
      font-size: 4.5rem;
      margin-bottom: 30px;
      color: var(--accent, var(--gold));
      transition: all 0.3s ease;
    }

    .feature-card:hover .feature-icon {
      transform: scale(1.1);
    }

    .feature-title {
      font-size: 1.7rem;
      font-weight: 700;
      margin-bottom: 20px;
      color: var(--text-light);
    }

    .feature-description {
      color: var(--text-muted);
      line-height: 1.8;
      font-size: 1.1rem;
    }

    /* نظرات کاربران */
    .testimonials {
      padding: 120px 0;
      background: rgba(11, 20, 25, 0.7);
    }

    .testimonials-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
      gap: 35px;
      max-width: 1400px;
      margin: 0 auto;
      padding: 0 25px;
    }

    .testimonial-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 24px;
      padding: 40px;
      backdrop-filter: blur(20px);
      transition: all 0.4s ease;
      position: relative;
    }

    .testimonial-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
    }

    .testimonial-content {
      color: var(--text-muted);
      font-size: 1.15rem;
      line-height: 1.8;
      margin-bottom: 25px;
      font-style: italic;
      position: relative;
    }

    .testimonial-content::before {
      content: '"';
      font-size: 4rem;
      color: var(--gold);
      position: absolute;
      top: -20px;
      right: -10px;
      opacity: 0.3;
    }

    .testimonial-author {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .author-avatar {
      width: 65px;
      height: 65px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--gold), var(--purple));
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.6rem;
      font-weight: 700;
    }

    .author-info h4 {
      color: var(--text-light);
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 5px;
    }

    .author-info p {
      color: var(--text-muted);
      font-size: 1rem;
    }

    /* آمار */
    .stats {
      padding: 100px 0;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 35px;
      max-width: 1400px;
      margin: 0 auto;
      padding: 0 25px;
    }

    .stat-item {
      text-align: center;
      padding: 35px;
      background: var(--card-bg);
      border-radius: 20px;
      border: 1px solid var(--card-border);
      backdrop-filter: blur(15px);
      transition: all 0.4s ease;
    }

    .stat-item:hover {
      transform: translateY(-8px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
    }

    .stat-number {
      font-size: 3.5rem;
      font-weight: 800;
      color: var(--gold);
      margin-bottom: 12px;
      text-shadow: 0 0 25px rgba(255, 171, 0, 0.4);
    }

    .stat-label {
      color: var(--text-muted);
      font-size: 1.3rem;
      font-weight: 500;
    }

    /* نحوه کار */
    .how-it-works {
      padding: 120px 0;
      background: rgba(11, 20, 25, 0.7);
    }

    .steps {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 45px;
      max-width: 1400px;
      margin: 0 auto;
      padding: 0 25px;
    }

    .step {
      text-align: center;
      position: relative;
    }

    .step-number {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, var(--gold), var(--purple));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      font-weight: 800;
      color: var(--text-light);
      margin: 0 auto 25px;
      box-shadow: 0 6px 20px rgba(255, 171, 0, 0.4);
      transition: all 0.3s ease;
    }

    .step:hover .step-number {
      transform: scale(1.1);
      box-shadow: 0 8px 25px rgba(255, 171, 0, 0.6);
    }

    .step-title {
      font-size: 1.6rem;
      font-weight: 700;
      margin-bottom: 18px;
      color: var(--text-light);
    }

    .step-description {
      color: var(--text-muted);
      line-height: 1.8;
      font-size: 1.1rem;
    }

    /* CTA */
    .cta {
      padding: 120px 0;
      position: relative;
      background: 
          radial-gradient(circle at 70% 30%, rgba(139, 92, 246, 0.2) 0%, transparent 50%),
          radial-gradient(circle at 30% 70%, rgba(255, 171, 0, 0.15) 0%, transparent 50%);
    }

    .cta-content {
      max-width: 900px;
      margin: 0 auto;
      text-align: center;
      padding: 0 25px;
    }

    .cta-title {
      font-size: 3rem;
      font-weight: 800;
      margin-bottom: 25px;
      color: var(--text-light);
      line-height: 1.3;
    }

    .cta-description {
      font-size: 1.4rem;
      color: var(--text-muted);
      margin-bottom: 45px;
      line-height: 1.8;
    }

    .btn-cta {
      padding: 18px 45px;
      font-size: 1.3rem;
      font-weight: 700;
      background: linear-gradient(135deg, var(--gold), var(--gold-dark));
      color: var(--navy-black);
      border: none;
      border-radius: 16px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 12px;
      transition: all 0.4s ease;
      box-shadow: 0 8px 30px rgba(255, 171, 0, 0.5);
    }

    .btn-cta:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 40px rgba(255, 171, 0, 0.7);
    }

    /* تماس با ما */
    .contact {
      padding: 120px 0;
      background: rgba(11, 20, 25, 0.7);
    }

    .contact-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 60px;
      max-width: 1400px;
      margin: 0 auto;
      padding: 0 25px;
    }

    .contact-info {
      display: flex;
      flex-direction: column;
      gap: 30px;
    }

    .contact-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 20px;
      padding: 35px;
      backdrop-filter: blur(20px);
      transition: all 0.4s ease;
    }

    .contact-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
    }

    .contact-icon {
      font-size: 2.8rem;
      color: var(--gold);
      margin-bottom: 20px;
    }

    .contact-title {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 12px;
      color: var(--text-light);
    }

    .contact-detail {
      color: var(--text-muted);
      line-height: 1.7;
      font-size: 1.15rem;
    }

    .contact-form {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 20px;
      padding: 45px;
      backdrop-filter: blur(20px);
    }

    .form-group {
      margin-bottom: 25px;
    }

    .form-label {
      display: block;
      margin-bottom: 10px;
      color: var(--text-light);
      font-weight: 600;
      font-size: 1.15rem;
    }

    .form-control {
      width: 100%;
      padding: 15px 20px;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid var(--card-border);
      border-radius: 12px;
      color: var(--text-light);
      font-family: Vazir, sans-serif;
      transition: all 0.3s ease;
      font-size: 1.05rem;
    }

    .form-control:focus {
      outline: none;
      border-color: var(--gold);
      box-shadow: 0 0 0 3px rgba(255, 171, 0, 0.3);
      background: rgba(255, 255, 255, 0.12);
    }

    textarea.form-control {
      min-height: 150px;
      resize: vertical;
    }

    .btn-submit {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, var(--gold), var(--gold-dark));
      color: var(--navy-black);
      border: none;
      border-radius: 12px;
      font-size: 1.2rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.4s ease;
      box-shadow: 0 6px 20px rgba(255, 171, 0, 0.4);
    }

    .btn-submit:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(255, 171, 0, 0.6);
    }

    /* فوتر */
    footer {
      background: rgba(8, 12, 24, 0.98);
      border-top: 1px solid var(--card-border);
      padding: 60px 0 25px;
      backdrop-filter: blur(25px);
    }

    .footer-content {
      max-width: 1400px;
      margin: 0 auto;
      padding: 0 25px;
      display: grid;
      grid-template-columns: 2fr 1fr 1fr;
      gap: 50px;
      margin-bottom: 40px;
    }

    .footer-brand {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .footer-logo {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--gold);
      text-decoration: none;
    }

    .footer-description {
      color: var(--text-muted);
      line-height: 1.8;
      font-size: 1.1rem;
      max-width: 450px;
    }

    .footer-links h4,
    .footer-contact h4 {
      color: var(--text-light);
      margin-bottom: 25px;
      font-size: 1.3rem;
      font-weight: 700;
    }

    .footer-links ul {
      list-style: none;
      padding: 0;
    }

    .footer-links li {
      margin-bottom: 12px;
    }

    .footer-links a {
      color: var(--text-muted);
      text-decoration: none;
      transition: all 0.3s ease;
      font-size: 1.1rem;
    }

    .footer-links a:hover {
      color: var(--gold);
      padding-right: 8px;
    }

    .footer-contact p {
      color: var(--text-muted);
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 1.1rem;
    }

    .footer-bottom {
      text-align: center;
      padding-top: 30px;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .footer-text {
      color: var(--text-muted);
      font-size: 1rem;
    }

    /* انیمیشن‌ها */
    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(40px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes gridMove {
      0% { transform: translateX(0) translateY(0); }
      100% { transform: translateX(-60px) translateY(-60px); }
    }

    @keyframes floatLight {
      0%, 100% { transform: translate(0, 0) scale(1); }
      25% { transform: translate(120px, -80px) scale(1.3); }
      50% { transform: translate(-80px, 120px) scale(0.9); }
      75% { transform: translate(-120px, -120px) scale(1.2); }
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.1); opacity: 0.8; }
    }

    /* افکت‌های ویژه */
    .glass-effect {
      backdrop-filter: blur(25px);
      -webkit-backdrop-filter: blur(25px);
    }

    .hover-lift {
      transition: all 0.4s ease;
    }

    .hover-lift:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
    }

    /* ریسپانسیو */
    @media (max-width: 1200px) {
      .hero-title {
        font-size: 3.5rem;
      }
      
      .section-title {
        font-size: 2.6rem;
      }
      
      .features-grid {
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
      }
    }

    @media (max-width: 768px) {
      .hero-title {
        font-size: 2.8rem;
      }
      
      .hero-subtitle {
        font-size: 1.2rem;
      }
      
      .section-title {
        font-size: 2.2rem;
      }
      
      .nav-container {
        flex-direction: column;
        gap: 20px;
      }
      
      .nav-links {
        order: 3;
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
        gap: 20px;
      }
      
      .nav-actions {
        width: 100%;
        justify-content: center;
        flex-wrap: wrap;
      }
      
      .hero-buttons {
        flex-direction: column;
        align-items: center;
      }
      
      .btn-hero {
        width: 100%;
        max-width: 320px;
        justify-content: center;
      }
      
      .contact-grid {
        grid-template-columns: 1fr;
        gap: 40px;
      }
      
      .footer-content {
        grid-template-columns: 1fr;
        gap: 40px;
      }
      
      .steps {
        grid-template-columns: 1fr;
      }
      
      .features-grid,
      .testimonials-grid {
        grid-template-columns: 1fr;
      }
      
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .scroll-to-top {
        bottom: 20px;
        left: 20px;
        width: 45px;
        height: 45px;
        font-size: 1rem;
      }
    }

    @media (max-width: 480px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .hero-title {
        font-size: 2.4rem;
      }
      
      .section-title {
        font-size: 2rem;
      }
    }
  </style>
</head>
<body>

<!-- بک‌گراند پویا -->
<div id="particles-js"></div>
<div class="grid-lines"></div>
<div class="light-spot"></div>
<div class="light-spot"></div>
<div class="light-spot"></div>

<!-- دکمه اسکرول به بالا -->
<button class="scroll-to-top" id="scrollToTop">
  <i class="fas fa-chevron-up"></i>
</button>

<!-- ناوبری اصلی -->
<nav class="main-nav glass-effect">
  <div class="nav-container">
    <a href="#" class="brand">
      <i class="fas fa-home-heart"></i>
      هم‌اتاقی
    </a>
    
    <div class="nav-links">
      <a href="#home" class="nav-link">خانه</a>
      <a href="#features" class="nav-link">ویژگی‌ها</a>
      <a href="#testimonials" class="nav-link">نظرات کاربران</a>
      <a href="#how-it-works" class="nav-link">نحوه کار</a>
      <a href="#stats" class="nav-link">آمار</a>
      <a href="#contact" class="nav-link">تماس با ما</a>
    </div>
    
    <div class="nav-actions">
      <a href="pages/login.php" class="nav-btn">
        <i class="fas fa-sign-in-alt"></i>
        ورود
      </a>
      <a href="pages/register.php" class="nav-btn primary">
        <i class="fas fa-user-plus"></i>
        ثبت‌نام
      </a>
    </div>
  </div>
</nav>

<!-- هیرو سکشن -->
<section id="home" class="hero">
  <div class="hero-content">
    <h1 class="hero-title">هم‌اتاقی مناسب خودت رو پیدا کن</h1>
    <p class="hero-subtitle">
      پلتفرم هوشمند هم‌اتاقی، راهکاری مطمئن برای پیدا کردن هم‌خانه ایده‌آل بر اساس علایق، 
      شخصیت و بودجه. با سیستم تطبیق‌دهی پیشرفته ما، زندگی مشترک لذت‌بخش‌تری را تجربه کنید.
    </p>
    <div class="hero-buttons">
      <a href="pages/register.php" class="btn-hero btn-primary">
        <i class="fas fa-rocket"></i>
        شروع کنید
      </a>
      <a href="#features" class="btn-hero btn-outline">
        <i class="fas fa-info-circle"></i>
        بیشتر بدانید
      </a>
    </div>
  </div>
</section>

<!-- ویژگی‌ها -->
<section id="features" class="features">
  <h2 class="section-title">چرا هم‌اتاقی؟</h2>
  
  <div class="features-grid">
    <div class="feature-card hover-lift" style="--accent: var(--info)">
      <div class="feature-icon">
        <i class="fas fa-search"></i>
      </div>
      <h3 class="feature-title">جستجوی هوشمند</h3>
      <p class="feature-description">
        بر اساس محل زندگی، بودجه، علایق و تیپ شخصیتی، هم‌اتاقی مناسب خود را پیدا کنید. 
        الگوریتم‌های هوشمند ما بهترین پیشنهادات را به شما ارائه می‌دهند.
      </p>
    </div>
    
    <div class="feature-card hover-lift" style="--accent: var(--success)">
      <div class="feature-icon">
        <i class="fas fa-comments"></i>
      </div>
      <h3 class="feature-title">ارتباط امن</h3>
      <p class="feature-description">
        با کاربران از طریق چت داخلی و بدون افشای شماره تماس آشنا شوید. 
        سیستم پیام‌رسانی امن ما حریم خصوصی شما را کاملاً حفظ می‌کند.
      </p>
    </div>
    
    <div class="feature-card hover-lift" style="--accent: var(--gold)">
      <div class="feature-icon">
        <i class="fas fa-certificate"></i>
      </div>
      <h3 class="feature-title">اعتماد و اطمینان</h3>
      <p class="feature-description">
        پروفایل کاربران تأیید می‌شود تا محیطی امن و مطمئن داشته باشید. 
        سیستم امتیازدهی و نظرات به شما در انتخاب بهتر کمک می‌کند.
      </p>
    </div>
    
    <div class="feature-card hover-lift" style="--accent: var(--purple)">
      <div class="feature-icon">
        <i class="fas fa-brain"></i>
      </div>
      <h3 class="feature-title">تست شخصیت MBTI</h3>
      <p class="feature-description">
        با انجام تست شخصیت MBTI، هم‌اتاقی‌های سازگارتر با شخصیت خود را پیدا کنید. 
        این تست به ما کمک می‌کند بهترین هماهنگی‌ها را شناسایی کنیم.
      </p>
    </div>
    
    <div class="feature-card hover-lift" style="--accent: var(--danger)">
      <div class="feature-icon">
        <i class="fas fa-mobile-alt"></i>
      </div>
      <h3 class="feature-title">دسترسی آسان</h3>
      <p class="feature-description">
        از هر دستگاه و در هر مکان به پلتفرم دسترسی داشته باشید. 
        رابط کاربری واکنش‌گرا تجربه‌ای عالی در همه دستگاه‌ها ارائه می‌دهد.
      </p>
    </div>
    
    <div class="feature-card hover-lift" style="--accent: var(--info)">
      <div class="feature-icon">
        <i class="fas fa-filter"></i>
      </div>
      <h3 class="feature-title">فیلتر پیشرفته</h3>
      <p class="feature-description">
        با فیلترهای دقیق و پیشرفته، خانه‌های مناسب را بر اساس شهر، قیمت، 
        امکانات و جنسیت پیدا کنید. جستجوی سریع و دقیق در کسری از ثانیه.
      </p>
    </div>
  </div>
</section>

<!-- نظرات کاربران -->
<section id="testimonials" class="testimonials">
  <h2 class="section-title">نظرات کاربران هم‌اتاقی</h2>
  
  <div class="testimonials-grid">
    <div class="testimonial-card hover-lift">
      <div class="testimonial-content">
        از طریق هم‌اتاقی با سارا آشنا شدم که واقعاً هم‌خونه ایده‌آلی بود. 
        هر دو عاشق کتابخونی و سکوت هستیم. سیستم تطبیق‌دهی عالی کار کرده!
      </div>
      <div class="testimonial-author">
        <div class="author-avatar">ف</div>
        <div class="author-info">
          <h4>فاطمه محمدی</h4>
          <p>دانشجوی مهندسی</p>
        </div>
      </div>
    </div>
    
    <div class="testimonial-card hover-lift">
      <div class="testimonial-content">
        به عنوان صاحب‌خانه، هم‌اتاقی زندگی من رو متحول کرد. 
        دیگه نگران پیدا کردن هم‌خانه مطمئن نیستم. سیستم امتیازدهی واقعاً کمک کننده‌ست.
      </div>
      <div class="testimonial-author">
        <div class="author-avatar">م</div>
        <div class="author-info">
          <h4>محمد رضایی</h4>
          <p>صاحب‌خانه</p>
        </div>
      </div>
    </div>
    
    <div class="testimonial-card hover-lift">
      <div class="testimonial-content">
        تست شخصیت MBTI واقعاً جالب بود! با هم‌اتاقی‌هایی آشنا شدم که 
        واقعاً با شخصیت من سازگار بودن. زندگی مشترکمون فوق‌العاده شده.
      </div>
      <div class="testimonial-author">
        <div class="author-avatar">ع</div>
        <div class="author-info">
          <h4>علی کریمی</h4>
          <p>طراح گرافیک</p>
        </div>
      </div>
    </div>

    <div class="testimonial-card hover-lift">
      <div class="testimonial-content">
        رابط کاربری ساده و جستجوی هوشمند واقعاً عالی کار می‌کنه. 
        در کمتر از یک هفته هم‌اتاقی مناسب خودم رو پیدا کردم.
      </div>
      <div class="testimonial-author">
        <div class="author-avatar">ز</div>
        <div class="author-info">
          <h4>زهرا احمدی</h4>
          <p>پزشک</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- آمار -->
<section id="stats" class="stats">
  <div class="stats-grid">
    <div class="stat-item hover-lift">
      <div class="stat-number" data-target="<?php echo $stats_data['total_users']; ?>"><?php echo number_format($stats_data['total_users']); ?>+</div>
      <div class="stat-label">کاربر فعال</div>
    </div>
    <div class="stat-item hover-lift">
      <div class="stat-number" data-target="<?php echo $stats_data['total_requests']; ?>"><?php echo number_format($stats_data['total_requests']); ?>+</div>
      <div class="stat-label">هم‌اتاقی موفق</div>
    </div>
    <div class="stat-item hover-lift">
      <div class="stat-number" data-target="<?php echo $stats_data['total_cities']; ?>"><?php echo number_format($stats_data['total_cities']); ?>+</div>
      <div class="stat-label">شهر</div>
    </div>
    <div class="stat-item hover-lift">
      <div class="stat-number" data-target="<?php echo $stats_data['satisfaction_rate']; ?>"><?php echo $stats_data['satisfaction_rate']; ?>%</div>
      <div class="stat-label">رضایت کاربران</div>
    </div>
  </div>
</section>

<!-- نحوه کار -->
<section id="how-it-works" class="how-it-works">
  <h2 class="section-title">چگونه کار می‌کند؟</h2>
  
  <div class="steps">
    <div class="step">
      <div class="step-number">۱</div>
      <h3 class="step-title">پروفایل بسازید</h3>
      <p class="step-description">
        در کمتر از ۵ دقیقه ثبت‌نام کنید و پروفایل کاملی از خودتان ایجاد نمایید. 
        تست شخصیت MBTI را انجام دهید تا نتایج بهتری دریافت کنید.
      </p>
    </div>
    
    <div class="step">
      <div class="step-number">۲</div>
      <h3 class="step-title">جستجوی هوشمند</h3>
      <p class="step-description">
        با فیلترهای پیشرفته، بین صدها پروفایل جستجو کنید. 
        سیستم ما بر اساس علایق و شخصیت شما، بهترین گزینه‌ها را پیشنهاد می‌دهد.
      </p>
    </div>
    
    <div class="step">
      <div class="step-number">۳</div>
      <h3 class="step-title">ارتباط امن</h3>
      <p class="step-description">
        از طریق چت داخلی امن با هم‌اتاقی‌های مورد نظر ارتباط برقرار کنید. 
        اطلاعات تماس شما محفوظ می‌ماند.
      </p>
    </div>
    
    <div class="step">
      <div class="step-number">۴</div>
      <h3 class="step-title">شروع زندگی مشترک</h3>
      <p class="step-description">
        پس از توافق، زندگی مشترک را آغاز کنید. 
        سیستم پیام‌رسانی و پشتیبانی ما در تمام مراحل همراه شماست.
      </p>
    </div>
  </div>
</section>

<!-- CTA -->
<section id="cta" class="cta">
  <div class="cta-content">
    <h2 class="cta-title">آماده‌ای زندگی بهتری رو شروع کنی؟</h2>
    <p class="cta-description">
      همین حالا به جامعه هم‌اتاقی بپیوندید و تجربه‌ای متفاوت از زندگی مشترک را آغاز کنید. 
      هزاران کاربر در سراسر ایران از طریق پلتفرم ما هم‌اتاقی مناسب خود را پیدا کرده‌اند.
    </p>
    <a href="pages/register.php" class="btn-cta">
      <i class="fas fa-home"></i>
      شروع سفر هم‌اتاقی
    </a>
  </div>
</section>

<!-- تماس با ما -->
<section id="contact" class="contact">
  <h2 class="section-title">در تماس باشید</h2>
  
  <div class="contact-grid">
    <div class="contact-info">
      <div class="contact-card hover-lift">
        <div class="contact-icon">
          <i class="fas fa-phone"></i>
        </div>
        <h3 class="contact-title">تلفن تماس</h3>
        <p class="contact-detail">۰۹۱۷۳۵۰۳۰۴۱</p>
      </div>
      
      <div class="contact-card hover-lift">
        <div class="contact-icon">
          <i class="fas fa-envelope"></i>
        </div>
        <h3 class="contact-title">ایمیل</h3>
        <p class="contact-detail">info@hamotaghi.ir</p>
        <p class="contact-detail">support@hamotaghi.ir</p>
      </div>
      
      <div class="contact-card hover-lift">
        <div class="contact-icon">
          <i class="fas fa-map-marker-alt"></i>
        </div>
        <h3 class="contact-title">آدرس</h3>
        <p class="contact-detail">شیراز، دانشگاه آپادانا، ساختمان مرکزی، واحد ۳۰۴</p>
      </div>
      
      <div class="contact-card hover-lift">
        <div class="contact-icon">
          <i class="fas fa-clock"></i>
        </div>
        <h3 class="contact-title">ساعات کاری</h3>
        <p class="contact-detail">شنبه تا چهارشنبه: ۸ صبح تا ۵ عصر</p>
        <p class="contact-detail">پنجشنبه: ۸ صبح تا ۱۲ ظهر</p>
      </div>
    </div>
    
    <div class="contact-form">
      <h3 style="color: var(--text-light); margin-bottom: 30px; font-size: 1.5rem; font-weight: 700;">پیام بفرستید</h3>
      <form>
        <div class="form-group">
          <label class="form-label" for="name">نام کامل</label>
          <input type="text" id="name" class="form-control" placeholder="نام و نام خانوادگی خود را وارد کنید">
        </div>
        
        <div class="form-group">
          <label class="form-label" for="email">ایمیل</label>
          <input type="email" id="email" class="form-control" placeholder="example@email.com">
        </div>
        
        <div class="form-group">
          <label class="form-label" for="subject">موضوع</label>
          <input type="text" id="subject" class="form-control" placeholder="موضوع پیام خود را بنویسید">
        </div>
        
        <div class="form-group">
          <label class="form-label" for="message">پیام</label>
          <textarea id="message" class="form-control" placeholder="متن پیام خود را اینجا بنویسید..."></textarea>
        </div>
        
        <button type="submit" class="btn-submit">
          <i class="fas fa-paper-plane"></i>
          ارسال پیام
        </button>
      </form>
    </div>
  </div>
</section>

<!-- فوتر -->
<footer class="glass-effect">
  <div class="footer-content">
    <div class="footer-brand">
      <a href="#" class="footer-logo">
        <i class="fas fa-home-heart"></i>
        هم‌اتاقی
      </a>
      <p class="footer-description">
        پلتفرم هوشمند هم‌اتاقی، راهکاری مطمئن و نوین برای پیدا کردن هم‌خانه مناسب 
        بر اساس علایق، شخصیت و بودجه. با ما زندگی مشترک لذت‌بخش‌تری را تجربه کنید.
      </p>
    </div>
    
    <div class="footer-links">
      <h4>لینک‌های سریع</h4>
      <ul>
        <li><a href="#home">خانه</a></li>
        <li><a href="#features">ویژگی‌ها</a></li>
        <li><a href="#testimonials">نظرات کاربران</a></li>
        <li><a href="#how-it-works">نحوه کار</a></li>
        <li><a href="#stats">آمار</a></li>
        <li><a href="#contact">تماس با ما</a></li>
      </ul>
    </div>
    
    <div class="footer-contact">
      <h4>ارتباط با ما</h4>
      <p><i class="fas fa-phone"></i> ۰۹۱۷۳۵۰۳۰۴۱</p>
      <p><i class="fas fa-envelope"></i> info@hamotaghi.ir</p>
      <p><i class="fas fa-map-marker-alt"></i> شیراز، دانشگاه آپادانا</p>
    </div>
  </div>
  
  <div class="footer-bottom">
    <p class="footer-text">© ۱۴۰۳ هم‌اتاقی — همه حقوق محفوظ است.</p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
<script>
// پارتیکل‌های پیشرفته
particlesJS("particles-js", {
  particles: {
    number: { value: 80, density: { enable: true, value_area: 1000 } },
    color: { value: ["#ffab00", "#8b5cf6", "#4caf50", "#2196f3"] },
    shape: { 
      type: ["circle", "triangle", "polygon"],
      polygon: { nb_sides: 6 }
    },
    opacity: { value: 0.3, random: true, anim: { enable: true, speed: 1, opacity_min: 0.1 } },
    size: { value: 3, random: true, anim: { enable: true, speed: 2, size_min: 1 } },
    line_linked: { 
      enable: true, 
      distance: 120, 
      color: "#ffab00", 
      opacity: 0.2, 
      width: 1,
      shadow: {
        enable: true,
        color: "#ffab00",
        blur: 5
      }
    },
    move: { 
      enable: true, 
      speed: 1.5, 
      direction: "none", 
      random: true, 
      straight: false, 
      out_mode: "bounce",
      attract: { enable: true, rotateX: 600, rotateY: 1200 }
    }
  },
  interactivity: {
    detect_on: "canvas",
    events: { 
      onhover: { enable: true, mode: "repulse" },
      onclick: { enable: true, mode: "push" },
      resize: true
    },
    modes: {
      repulse: { distance: 100, duration: 0.4 },
      push: { particles_nb: 4 }
    }
  },
  retina_detect: true
});

// اسکرول نرم
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      target.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }
  });
});

// دکمه اسکرول به بالا
const scrollToTopBtn = document.getElementById('scrollToTop');

window.addEventListener('scroll', () => {
  if (window.pageYOffset > 300) {
    scrollToTopBtn.classList.add('show');
  } else {
    scrollToTopBtn.classList.remove('show');
  }
});

scrollToTopBtn.addEventListener('click', () => {
  window.scrollTo({
    top: 0,
    behavior: 'smooth'
  });
});

// انیمیشن ورود المان‌ها
const observerOptions = {
  threshold: 0.1,
  rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.style.animation = `slideInUp 0.8s ease-out forwards`;
      observer.unobserve(entry.target);
    }
  });
}, observerOptions);

// مشاهده المان‌ها برای انیمیشن
document.querySelectorAll('.feature-card, .section-title, .cta-content, .stat-item, .step, .contact-card, .contact-form, .testimonial-card').forEach(el => {
  el.style.opacity = '0';
  observer.observe(el);
});

// افکت hover برای کارت‌ها
const hoverCards = document.querySelectorAll('.hover-lift');
hoverCards.forEach(card => {
  card.addEventListener('mouseenter', function() {
    this.style.transform = 'translateY(-10px) scale(1.02)';
  });
  
  card.addEventListener('mouseleave', function() {
    this.style.transform = 'translateY(0) scale(1)';
  });
});

// فرم تماس
document.querySelector('.contact-form form').addEventListener('submit', function(e) {
  e.preventDefault();
  alert('پیام شما با موفقیت ارسال شد! به زودی با شما تماس خواهیم گرفت.');
  this.reset();
});

// شمارش آمار
function animateCounter(element, target, duration, suffix = '+') {
  let start = 0;
  const increment = target / (duration / 16);
  const timer = setInterval(() => {
    start += increment;
    if (start >= target) {
      if (suffix === '%') {
        element.textContent = target + suffix;
      } else {
        element.textContent = number_format(target) + suffix;
      }
      clearInterval(timer);
    } else {
      if (suffix === '%') {
        element.textContent = Math.floor(start) + suffix;
      } else {
        element.textContent = number_format(Math.floor(start)) + suffix;
      }
    }
  }, 16);
}

// تابع فرمت اعداد فارسی
function number_format(num) {
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// راه‌اندازی شمارشگرها هنگام اسکرول
const statsObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const statNumbers = document.querySelectorAll('.stat-number');
      statNumbers.forEach((number) => {
        const target = parseInt(number.getAttribute('data-target')) || 0;
        const isPercentage = number.textContent.includes('%');
        if (isPercentage) {
          animateCounter(number, target, 2000, '%');
        } else {
          animateCounter(number, target, 2000, '+');
        }
      });
      statsObserver.disconnect();
    }
  });
}, { threshold: 0.5 });

statsObserver.observe(document.querySelector('.stats'));
</script>

</body>
</html>