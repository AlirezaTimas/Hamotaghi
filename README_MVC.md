# MVC Architecture Refactoring Guide

## Overview

This project has been refactored to follow a clean **Model-View-Controller (MVC)** architecture while maintaining backward compatibility with existing pages.

## Directory Structure

```
public_html/
├── core/                    # Core classes (Database, Auth, Session, etc.)
│   ├── Database.php
│   ├── Session.php
│   ├── Auth.php
│   ├── Validator.php
│   ├── Helper.php
│   ├── Router.php
│   └── bootstrap.php
├── models/                  # Data models
│   ├── BaseModel.php
│   ├── User.php
│   ├── House.php
│   ├── Request.php
│   ├── Message.php
│   ├── Favorite.php
│   └── Notification.php
├── controllers/             # Controllers
│   ├── BaseController.php
│   └── AuthController.php
├── views/                   # View templates
│   ├── layouts/
│   │   ├── header.php
│   │   └── footer.php
│   └── auth/
│       └── login.php
├── config/                  # Configuration
│   └── app.php
├── pages/                   # Legacy pages (still functional)
│   ├── login.php           # Old style
│   ├── login_mvc.php       # New MVC style
│   └── ...
└── includes/                # Legacy includes (backward compatible)
    ├── config.php
    ├── header.php
    └── footer.php
```

## Architecture Principles

### 1. **Separation of Concerns**
- **Models**: Handle all database interactions
- **Controllers**: Handle requests, validation, and business logic
- **Views**: Handle display and templates

### 2. **Core Classes**

#### Database (Singleton)
```php
$db = Database::getInstance();
```

#### Session Management
```php
Session::set('key', 'value');
$value = Session::get('key');
```

#### Authentication
```php
if (Auth::check()) {
    $user = Auth::user();
}
Auth::requireAuth(); // Redirects if not logged in
```

#### Validation
```php
$validator = new Validator();
if ($validator->validate($data, $rules)) {
    // Valid
} else {
    $errors = $validator->errors();
}
```

### 3. **Models**

All models extend `BaseModel` and provide:
- `find($id)` - Find by ID
- `findAll($conditions)` - Find all matching
- `create($data)` - Create new record
- `update($id, $data)` - Update record
- `delete($id)` - Delete record

Example:
```php
$userModel = new User();
$user = $userModel->findByEmail('user@example.com');
```

### 4. **Controllers**

All controllers extend `BaseController`:

```php
class MyController extends BaseController
{
    public function index()
    {
        $this->requireAuth(); // Check authentication
        $this->view('my/view', ['data' => $data]);
    }
}
```

### 5. **Views**

Views are PHP templates that receive data from controllers:

```php
// In controller
$this->view('auth/login', ['errors' => $errors]);

// In view (views/auth/login.php)
<?php require __DIR__ . '/../layouts/header.php'; ?>
// View content
<?php require __DIR__ . '/../layouts/footer.php'; ?>
```

## Migration Guide

### Step 1: Update Existing Pages

For a page like `pages/login.php`, create a controller method:

**Old style:**
```php
<?php
require '../includes/config.php';
// Direct database queries, HTML mixed with PHP
?>
```

**New MVC style:**
```php
<?php
require_once __DIR__ . '/../core/bootstrap.php';
$controller = new AuthController();
$controller->login(); // or showLogin()
?>
```

### Step 2: Extract Business Logic to Models

Move database queries from pages to models:

**Before:**
```php
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
```

**After:**
```php
$userModel = new User();
$user = $userModel->find($user_id);
```

### Step 3: Move HTML to Views

Extract HTML to view templates in `views/` directory.

## Usage Examples

### Authentication
```php
// Check if logged in
if (Auth::check()) {
    $user = Auth::user();
}

// Require authentication
Auth::requireAuth('/pages/login.php');

// Require specific role
Auth::requireRole('landlord', '/pages/dashboard.php');
```

### Database Operations
```php
$houseModel = new House();
$houses = $houseModel->search([
    'city' => 'تهران',
    'gender' => 'آقا',
    'min_price' => 1000000
], 'price_low', 12, 0);
```

### Validation
```php
$validator = new Validator();
$rules = [
    'email' => 'required|email',
    'password' => 'required|min:6'
];
if ($validator->validate($_POST, $rules)) {
    // Process
}
```

### Helpers
```php
Helper::redirect('/pages/dashboard.php');
Helper::e($userInput); // Escape output
Helper::formatPrice(1500000); // "1,500,000 تومان"
```

## Backward Compatibility

The old pages in `pages/` directory continue to work. The `includes/config.php` file has been updated to load the MVC core if available, so existing pages can gradually migrate.

## Next Steps

1. **Migrate remaining pages** to MVC architecture
2. **Create more controllers** (HouseController, RequestController, etc.)
3. **Add middleware** for CSRF protection, rate limiting
4. **Implement routing system** for cleaner URLs
5. **Add unit tests** for models and controllers

## Security Features

- ✅ PDO prepared statements (SQL injection prevention)
- ✅ Input sanitization (XSS prevention)
- ✅ Output escaping
- ✅ Password hashing (bcrypt)
- ✅ Session management
- ✅ CSRF token support (Helper::csrfToken())

## Database

The database schema remains the same. All models use the existing tables:
- `users`
- `houses`
- `house_photos`
- `requests`
- `favorites`
- `messages`
- `notifications`
- `user_activities`

## Testing

To test the new MVC architecture:

1. Use `pages/login_mvc.php` instead of `pages/login.php`
2. Check that authentication works
3. Verify database operations through models
4. Test validation and error handling

## Support

For issues or questions about the MVC refactoring, refer to this documentation or the code comments in core classes.

