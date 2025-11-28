SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci';

DROP DATABASE IF EXISTS hamotaghi_db;
CREATE DATABASE hamotaghi_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hamotaghi_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(20) UNIQUE,
    password VARCHAR(255) NOT NULL,
    usertype ENUM('هم‌خانه','صاحب‌خانه') NOT NULL,
    gender ENUM('آقا','خانم') NOT NULL,
    city VARCHAR(120) NOT NULL,
    province VARCHAR(120) NOT NULL,
    national_code VARCHAR(10),
    personality_type VARCHAR(4),
    bio TEXT,
    avatar VARCHAR(255),
    user_score DECIMAL(3,2) DEFAULT 0.00,
    first_login TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_usertype (usertype),
    INDEX idx_gender (gender),
    INDEX idx_city (city),
    INDEX idx_province (province),
    INDEX idx_is_active (is_active),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_city (city),
    INDEX idx_province (province),
    INDEX idx_status (status),
    INDEX idx_gender (gender),
    INDEX idx_price (price),
    INDEX idx_available_capacity (available_capacity),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE house_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    INDEX idx_house_id (house_id),
    INDEX idx_house_primary (house_id, is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    house_id INT NOT NULL,
    message TEXT,
    move_in_date DATE,
    duration VARCHAR(100),
    budget INT,
    match_percentage TINYINT UNSIGNED DEFAULT NULL,
    status ENUM('در انتظار','تایید شده','رد شده','لغو شده') DEFAULT 'در انتظار',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_house (user_id, house_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_house_id (house_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    INDEX idx_listing_id (listing_id),
    INDEX idx_sender_user_id (sender_user_id),
    INDEX idx_receiver_owner_id (receiver_owner_id),
    INDEX idx_request_status (request_status),
    INDEX idx_created_at (created_at),
    UNIQUE KEY uq_listing_sender (listing_id, sender_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    content TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_house_id (house_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_receiver_id (receiver_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    house_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fav (user_id, house_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_house_id (house_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50),
    related_id INT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reviewer_id INT NOT NULL,
    reviewee_id INT NOT NULL,
    listing_id INT,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT,
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES houses(id) ON DELETE SET NULL,
    INDEX idx_reviewer_id (reviewer_id),
    INDEX idx_reviewee_id (reviewee_id),
    INDEX idx_listing_id (listing_id),
    INDEX idx_rating (rating),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    listing_id INT,
    match_id INT,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'IRR',
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    status ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
    payment_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES houses(id) ON DELETE SET NULL,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_listing_id (listing_id),
    INDEX idx_match_id (match_id),
    INDEX idx_status (status),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(50) DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (full_name, email, phone, password, usertype, gender, city, province, national_code, personality_type, bio, user_score, first_login, is_active) VALUES
('مدیر سیستم', 'admin@admin.com', '09120000001', '$2y$10$eKfFiWKQyok59v8ur2kgkOJ2ZBsR3POfD9ttfunpD4aTJzcv9FQIS', 'صاحب‌خانه', 'آقا', 'تهران', 'تهران', '0000000001', 'ENTJ', 'مدیر سیستم', 5.00, 0, 1),
('کاربر تست', 'customer@customer.com', '09120000002', '$2y$10$eKfFiWKQyok59v8ur2kgkOJ2ZBsR3POfD9ttfunpD4aTJzcv9FQIS', 'هم‌خانه', 'خانم', 'تهران', 'تهران', '0000000002', 'ENFP', 'کاربر تست هم‌خانه', 4.50, 0, 1),
('علی احمدی', 'ali@example.com', '09123456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'صاحب‌خانه', 'آقا', 'تهران', 'تهران', '1234567890', 'ENTJ', 'صاحب خانه با تجربه در اجاره دادن', 4.50, 0, 1),
('سارا محمدی', 'sara@example.com', '09123456790', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'هم‌خانه', 'خانم', 'تهران', 'تهران', '0987654321', 'ENFP', 'دانشجوی کارشناسی ارشد، به دنبال هم‌خانه مناسب', 4.20, 0, 1),
('محمد رضایی', 'mohammad@example.com', '09123456791', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'صاحب‌خانه', 'آقا', 'اصفهان', 'اصفهان', '1122334455', 'ISTJ', 'خانه دارم و به دنبال هم‌خانه مناسب', 4.80, 0, 1),
('فاطمه کریمی', 'fateme@example.com', '09123456792', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'هم‌خانه', 'خانم', 'شیراز', 'فارس', '2233445566', 'ISFJ', 'کارمند، به دنبال خانه مناسب', 4.30, 0, 1);

INSERT INTO houses (user_id, title, description, address, city, province, price, capacity, available_capacity, gender, amenities, rules, images, status) VALUES
(1, 'آپارتمان 2 خوابه در تهرانپارس', 'آپارتمان زیبا و تمیز در منطقه تهرانپارس، نزدیک به مترو و مراکز خرید', 'تهران، تهرانپارس، خیابان فرجام', 'تهران', 'تهران', 5000000, 3, 1, 'آقا', 'پارکینگ,انباری,آسانسور,بالکن', 'سیگار ممنوع,حیوان خانگی ممنوع', 'assets/images/roommates.jpg', 'فعال'),
(1, 'خانه ویلایی در شمال تهران', 'خانه ویلایی با حیاط بزرگ، مناسب برای 4 نفر', 'تهران، شمال شهر، خیابان ولیعصر', 'تهران', 'تهران', 8000000, 4, 2, 'آقا', 'پارکینگ,حیاط,انباری', 'سیگار ممنوع', 'assets/images/roommates.jpg', 'فعال'),
(3, 'آپارتمان 3 خوابه در اصفهان', 'آپارتمان مدرن و مجهز در مرکز اصفهان', 'اصفهان، خیابان چهارباغ', 'اصفهان', 'اصفهان', 4000000, 4, 1, 'خانم', 'پارکینگ,انباری,آسانسور,بالکن,پکیج', 'حیوان خانگی مجاز', 'assets/images/roommates.jpg', 'فعال'),
(1, 'استودیو در منطقه 2 تهران', 'استودیو کوچک و دنج برای یک نفر', 'تهران، منطقه 2، خیابان ولیعصر', 'تهران', 'تهران', 3000000, 1, 1, 'آقا', 'پارکینگ,انباری', 'سیگار ممنوع', 'assets/images/roommates.jpg', 'فعال'),
(3, 'آپارتمان 2 خوابه در اصفهان', 'آپارتمان تمیز و مرتب، نزدیک به دانشگاه', 'اصفهان، خیابان دانشگاه', 'اصفهان', 'اصفهان', 3500000, 2, 1, 'خانم', 'پارکینگ,انباری', 'حیوان خانگی ممنوع', 'assets/images/roommates.jpg', 'فعال');

INSERT INTO house_photos (house_id, file_path, is_primary) VALUES
(1, 'assets/images/roommates.jpg', 1),
(2, 'assets/images/roommates.jpg', 1),
(3, 'assets/images/roommates.jpg', 1),
(4, 'assets/images/roommates.jpg', 1),
(5, 'assets/images/roommates.jpg', 1);

INSERT INTO matches (user_id, house_id, message, move_in_date, duration, budget, match_percentage, status) VALUES
(2, 1, 'سلام، علاقه‌مند به این خانه هستم. لطفا با من تماس بگیرید.', '2025-02-01', '6 ماه', 5000000, 85, 'در انتظار'),
(4, 3, 'به دنبال خانه مناسب برای اقامت طولانی مدت هستم.', '2025-02-15', '1 سال', 4000000, 90, 'در انتظار');

INSERT INTO messages (house_id, sender_id, receiver_id, content, is_read) VALUES
(1, 2, 1, 'سلام، در مورد خانه سوال داشتم.', 0),
(1, 1, 2, 'سلام، بله بفرمایید.', 0),
(3, 4, 3, 'سلام، آیا خانه هنوز خالی است؟', 0);

INSERT INTO favorites (user_id, house_id) VALUES
(2, 1),
(2, 2),
(4, 3);

INSERT INTO notifications (user_id, title, message, type, related_id, is_read) VALUES
(1, 'درخواست جدید', 'درخواست جدید برای خانه شما دریافت شد', 'request', 1, 0),
(3, 'درخواست جدید', 'درخواست جدید برای خانه شما دریافت شد', 'request', 2, 0),
(2, 'پیام جدید', 'پیام جدید از صاحب خانه دریافت شد', 'message', 1, 0);

INSERT INTO user_activities (user_id, activity_type, description) VALUES
(1, 'house_added', 'خانه جدید اضافه شد'),
(2, 'request_sent', 'درخواست اقامت ارسال شد'),
(3, 'house_added', 'خانه جدید اضافه شد');

INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'هم‌اتاقی', 'string', 'نام سایت'),
('site_email', 'info@hamotaghi.com', 'string', 'ایمیل سایت'),
('max_upload_size', '5242880', 'integer', 'حداکثر حجم آپلود (بایت)'),
('items_per_page', '12', 'integer', 'تعداد آیتم در هر صفحه'),
('maintenance_mode', '0', 'boolean', 'حالت تعمیرات'),
('registration_enabled', '1', 'boolean', 'فعال بودن ثبت‌نام');

