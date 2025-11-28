# Complete Database Schema - Hamotaghi Project

## Overview

This document provides the complete database schema for the Hamotaghi roommate-matching platform. All tables use InnoDB engine with UTF-8MB4 charset for full Persian and emoji support.

## Tables

### 1. users

Stores user account information.

```sql
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    usertype ENUM('هم‌خانه','صاحب‌خانه') NOT NULL,
    gender ENUM('آقا','خانم') NOT NULL,
    city VARCHAR(120) NOT NULL,
    province VARCHAR(120) NOT NULL,
    phone VARCHAR(20) UNIQUE,
    national_code VARCHAR(10),
    personality_type VARCHAR(4),
    bio TEXT,
    avatar VARCHAR(255),
    user_score DECIMAL(3,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_usertype (usertype),
    INDEX idx_city (city),
    INDEX idx_province (province)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields**:
- `id`: Primary key
- `full_name`: User's full name
- `email`: Unique email address
- `password`: Bcrypt hashed password
- `usertype`: User role (هم‌خانه = roommate, صاحب‌خانه = landlord)
- `gender`: Gender (آقا = male, خانم = female)
- `city`: City name
- `province`: Province name
- `phone`: Phone number (optional, unique)
- `national_code`: National ID (optional)
- `personality_type`: MBTI personality type (4 characters, e.g., "ENFP")
- `bio`: User biography/description
- `avatar`: Path to avatar image
- `user_score`: User rating score (0.00-5.00)
- `created_at`: Creation timestamp
- `updated_at`: Last update timestamp

### 2. houses

Stores house/room listings.

```sql
CREATE TABLE IF NOT EXISTS houses (
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
    INDEX idx_available_capacity (available_capacity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields**:
- `id`: Primary key
- `user_id`: Foreign key to users (landlord)
- `title`: House/room title
- `description`: Detailed description
- `address`: Full address
- `city`: City name
- `province`: Province name
- `price`: Monthly rent price (in Tomans)
- `capacity`: Total capacity (number of people)
- `available_capacity`: Available slots (must be <= capacity)
- `gender`: Gender restriction (آقا = male, خانم = female)
- `amenities`: Comma-separated amenities list
- `rules`: House rules
- `images`: JSON array of image paths (legacy, use house_photos table)
- `status`: Listing status (فعال = active, در انتظار تایید = pending, غیرفعال = inactive)
- `created_at`: Creation timestamp
- `updated_at`: Last update timestamp

**Business Rules**:
- `available_capacity` must be <= `capacity`
- `available_capacity` decreases when requests are accepted
- Only houses with `status = 'فعال'` and `available_capacity > 0` are shown in search

### 3. house_photos

Stores multiple photos for each house.

```sql
CREATE TABLE IF NOT EXISTS house_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    INDEX idx_house_primary (house_id, is_primary),
    INDEX idx_house_id (house_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields**:
- `id`: Primary key
- `house_id`: Foreign key to houses
- `file_path`: Path to image file (relative to web root)
- `is_primary`: Whether this is the primary/featured image (0 or 1)
- `created_at`: Creation timestamp

**Business Rules**:
- Each house should have at least one photo
- Only one photo per house should have `is_primary = 1`
- Photos are deleted when house is deleted (CASCADE)

### 4. requests

Stores roommate requests for houses.

```sql
CREATE TABLE IF NOT EXISTS requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    house_id INT NOT NULL,
    message TEXT,
    move_in_date DATE,
    duration VARCHAR(100),
    budget INT,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields**:
- `id`: Primary key
- `user_id`: Foreign key to users (roommate making request)
- `house_id`: Foreign key to houses
- `message`: Request message from roommate
- `move_in_date`: Preferred move-in date
- `duration`: Duration of stay (e.g., "6 months", "1 year")
- `budget`: Budget range
- `status`: Request status
  - `در انتظار` = pending (waiting for landlord response)
  - `تایید شده` = accepted (landlord approved)
  - `رد شده` = rejected (landlord rejected)
  - `لغو شده` = cancelled (roommate cancelled)
- `created_at`: Creation timestamp
- `updated_at`: Last update timestamp

**Business Rules**:
- One user can only have one active request per house (UNIQUE constraint)
- When request is accepted, `house.available_capacity` decreases by 1
- Only users with `usertype = 'هم‌خانه'` can create requests
- Users cannot request their own houses
- Gender must match house gender requirement
- House must have `available_capacity > 0`

### 5. favorites

Stores user favorite houses.

```sql
CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    house_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fav (user_id, house_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_house_id (house_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields**:
- `id`: Primary key
- `user_id`: Foreign key to users
- `house_id`: Foreign key to houses
- `created_at`: When favorited

**Business Rules**:
- One user can favorite a house only once (UNIQUE constraint)
- Users cannot favorite their own houses
- Favorites are deleted when user or house is deleted (CASCADE)

### 6. messages

Stores messages between users (roommates and landlords).

```sql
CREATE TABLE IF NOT EXISTS messages (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields**:
- `id`: Primary key
- `house_id`: Foreign key to houses (conversation context)
- `sender_id`: Foreign key to users (message sender)
- `receiver_id`: Foreign key to users (message receiver)
- `content`: Message content (HTML escaped)
- `is_read`: Whether message has been read (0 or 1)
- `created_at`: Message timestamp

**Business Rules**:
- Users can only message if they have an active request (pending or accepted) for the house
- Messages are scoped to a specific house (conversation context)
- Messages are deleted when house is deleted (CASCADE)

### 7. notifications

Stores user notifications.

```sql
CREATE TABLE IF NOT EXISTS notifications (
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
    INDEX idx_created_at (created_at),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields**:
- `id`: Primary key
- `user_id`: Foreign key to users (notification recipient)
- `title`: Notification title
- `message`: Notification message
- `type`: Notification type (e.g., 'request', 'message', 'accept', 'reject')
- `related_id`: Related entity ID (e.g., request_id, message_id)
- `is_read`: Whether notification has been read (0 or 1)
- `created_at`: Creation timestamp

**Notification Types**:
- `request`: New request received (for landlords)
- `accept`: Request accepted (for roommates)
- `reject`: Request rejected (for roommates)
- `message`: New message received
- `system`: System notification

### 8. user_activities

Stores user activity log (optional audit trail).

```sql
CREATE TABLE IF NOT EXISTS user_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fields**:
- `id`: Primary key
- `user_id`: Foreign key to users
- `activity_type`: Type of activity (e.g., 'login', 'house_added', 'request_sent')
- `description`: Activity description
- `created_at`: Activity timestamp

**Activity Types**:
- `login`: User logged in
- `house_added`: House listing created
- `house_updated`: House listing updated
- `request_sent`: Request sent
- `request_accepted`: Request accepted
- `request_rejected`: Request rejected
- `message_sent`: Message sent
- `profile_updated`: Profile updated

## Relationships

### Entity Relationship Diagram (Text)

```
users (1) ──< (many) houses
users (1) ──< (many) requests
houses (1) ──< (many) requests
houses (1) ──< (many) house_photos
users (many) ──< (many) favorites ──> (many) houses
houses (1) ──< (many) messages
users (1) ──< (many) messages (as sender)
users (1) ──< (many) messages (as receiver)
users (1) ──< (many) notifications
users (1) ──< (many) user_activities
```

## Indexes

### Performance Indexes

All foreign keys are automatically indexed. Additional indexes for common queries:

- `users`: email (UNIQUE), phone (UNIQUE), usertype, city, province
- `houses`: user_id, city, province, status, gender, price, available_capacity
- `house_photos`: house_id, (house_id, is_primary) composite
- `requests`: user_id, house_id, status, created_at
- `favorites`: user_id, house_id
- `messages`: house_id, sender_id, receiver_id, is_read, created_at
- `notifications`: user_id, is_read, created_at, type
- `user_activities`: user_id, activity_type, created_at

## Constraints

### Foreign Key Constraints

All foreign keys use `ON DELETE CASCADE`:
- Deleting a user deletes their houses, requests, messages, notifications, activities
- Deleting a house deletes its photos, requests, messages, favorites
- Deleting a request does not affect house capacity (must be handled in application logic)

### Unique Constraints

- `users.email`: Unique
- `users.phone`: Unique (if provided)
- `requests(user_id, house_id)`: One request per user per house
- `favorites(user_id, house_id)`: One favorite per user per house

## Data Integrity Rules

### Application-Level Rules (Enforced in Code)

1. **House Capacity**:
   - `available_capacity` must be <= `capacity`
   - When request is accepted, `available_capacity` decreases
   - Cannot accept request if `available_capacity = 0`

2. **Request Rules**:
   - Only roommates (`usertype = 'هم‌خانه'`) can send requests
   - Cannot request own house
   - Gender must match house gender
   - House must have available capacity

3. **Messaging Rules**:
   - Can only message if have active request (pending or accepted)
   - Messages are scoped to house context

4. **Favorite Rules**:
   - Cannot favorite own house
   - Should check gender and capacity before allowing favorite

5. **Photo Rules**:
   - Each house should have at least one photo
   - Only one primary photo per house
   - Default photo: `assets/images/roommates.jpg`

## Sample Data

See `install/setup_database.php` for sample data generation:
- 10 sample houses
- 1 sample landlord user
- 1 sample roommate user
- Sample requests, favorites, messages, notifications

## Migration Notes

### From Old Schema

If migrating from an older version:
1. Ensure all tables exist with correct structure
2. Add `house_photos` table if missing
3. Update `requests.status` ENUM to include 'لغو شده'
4. Ensure all foreign keys and indexes are in place
5. Run `install/setup_database.php` to populate sample data

### Future Enhancements

Potential additions:
- `reviews` table (user reviews/ratings)
- `payments` table (payment tracking)
- `contracts` table (rental contracts)
- `reports` table (user reports/complaints)

## Maintenance

### Regular Tasks

1. **Cleanup Old Data**:
   ```sql
   -- Delete old notifications (older than 90 days)
   DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
   
   -- Delete old activities (older than 1 year)
   DELETE FROM user_activities WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
   ```

2. **Update Statistics**:
   ```sql
   -- Recalculate available_capacity (if needed)
   UPDATE houses h
   SET available_capacity = h.capacity - (
       SELECT COUNT(*) FROM requests r
       WHERE r.house_id = h.id AND r.status = 'تایید شده'
   );
   ```

3. **Optimize Tables**:
   ```sql
   OPTIMIZE TABLE users, houses, requests, messages, notifications;
   ```

## Backup Recommendations

1. **Daily Backups**: Full database backup
2. **Before Migrations**: Always backup before schema changes
3. **User Data**: Special attention to `users`, `houses`, `requests` tables
4. **File Storage**: Backup `assets/uploads/` directory along with database

---

**Schema Version**: 2.0
**Last Updated**: 2025
**Compatible With**: PHP 8.0+, MySQL 5.7+, MariaDB 10.2+

