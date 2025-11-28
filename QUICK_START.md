# Quick Start Guide - MVC Architecture

## Installation

1. **Database Setup**
   ```bash
   # Run the database setup script
   php install/setup_database.php
   ```

2. **Configuration**
   - Edit `config/app.php` for database settings
   - Or use `includes/config.php` (legacy, still works)

3. **Test the System**
   - Visit `pages/login_mvc.php` to test MVC login
   - Or use `pages/login.php` for legacy version

## Basic Usage

### 1. Database Connection

```php
require_once __DIR__ . '/core/bootstrap.php';

// Database is automatically initialized
$db = Database::getInstance();
```

### 2. Authentication

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

### 3. Using Models

```php
// User operations
$userModel = new User();
$user = $userModel->find(1);
$user = $userModel->findByEmail('user@example.com');

// House operations
$houseModel = new House();
$houses = $houseModel->search([
    'city' => 'تهران',
    'gender' => 'آقا'
], 'price_low', 12, 0);

// Request operations
$requestModel = new Request();
$requests = $requestModel->findByUser($userId, 'در انتظار');
```

### 4. Creating a Controller

```php
class MyController extends BaseController
{
    public function index()
    {
        $this->requireAuth();
        
        $data = ['message' => 'Hello World'];
        $this->view('my/view', $data);
    }
    
    public function api()
    {
        $this->json(['status' => 'success']);
    }
}
```

### 5. Creating a View

**File**: `views/my/view.php`
```php
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="container">
    <h1><?= Helper::e($message) ?></h1>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
```

### 6. Validation

```php
$validator = new Validator();
$rules = [
    'email' => 'required|email',
    'password' => 'required|min:6'
];

if ($validator->validate($_POST, $rules)) {
    // Valid
} else {
    $errors = $validator->errors();
}
```

### 7. Helper Functions

```php
// Redirect
Helper::redirect('/pages/dashboard.php');

// Escape output
echo Helper::e($userInput);

// Format price
echo Helper::formatPrice(1500000); // "1,500,000 تومان"

// Format date
echo Helper::formatDate('2025-01-01'); // "2025/01/01"

// CSRF token
$token = Helper::csrfToken();
```

## Common Patterns

### Pattern 1: List Page with Filters

```php
class HouseController extends BaseController
{
    public function index()
    {
        $this->requireAuth();
        
        $filters = [
            'city' => Helper::get('city'),
            'gender' => Helper::get('gender'),
            'min_price' => Helper::get('min_price'),
            'max_price' => Helper::get('max_price')
        ];
        
        $sort = Helper::get('sort', 'newest');
        $page = (int)Helper::get('page', 1);
        $perPage = 12;
        $offset = ($page - 1) * $perPage;
        
        $houseModel = new House();
        $houses = $houseModel->search($filters, $sort, $perPage, $offset);
        
        $this->view('houses/index', [
            'houses' => $houses,
            'filters' => $filters,
            'sort' => $sort,
            'page' => $page
        ]);
    }
}
```

### Pattern 2: Form Submission

```php
class RequestController extends BaseController
{
    public function create()
    {
        $this->requireAuth();
        $this->requireRole('roommate');
        
        if (Helper::isPost()) {
            $validator = new Validator();
            $rules = [
                'house_id' => 'required|numeric',
                'message' => 'required|min:10',
                'move_in_date' => 'required|date_future'
            ];
            
            if ($validator->validate($_POST, $rules)) {
                $requestModel = new Request();
                $requestId = $requestModel->create([
                    'user_id' => Auth::id(),
                    'house_id' => (int)$_POST['house_id'],
                    'message' => Validator::sanitize($_POST['message']),
                    'move_in_date' => $_POST['move_in_date'],
                    'status' => 'در انتظار'
                ]);
                
                // Create notification
                $notificationModel = new Notification();
                // ... notification logic
                
                $this->redirect('/pages/my_requests.php?success=1');
            } else {
                $errors = $validator->errors();
            }
        }
        
        $this->view('requests/create', [
            'errors' => $errors ?? []
        ]);
    }
}
```

### Pattern 3: AJAX Endpoint

```php
class FavoriteController extends BaseController
{
    public function toggle()
    {
        $this->requireAuth();
        
        if (!Helper::isPost()) {
            $this->json(['error' => 'Invalid method'], 405);
            return;
        }
        
        $houseId = (int)Helper::post('house_id', 0);
        
        if ($houseId <= 0) {
            $this->json(['error' => 'Invalid house ID'], 400);
            return;
        }
        
        $favoriteModel = new Favorite();
        $result = $favoriteModel->toggle(Auth::id(), $houseId);
        $count = $favoriteModel->getCount($houseId);
        
        $this->json([
            'success' => true,
            'is_favorited' => $result,
            'favorites_count' => $count
        ]);
    }
}
```

## Security Checklist

- ✅ Always use prepared statements (models do this automatically)
- ✅ Always escape output with `Helper::e()`
- ✅ Always validate inputs with `Validator`
- ✅ Always check authentication with `Auth::requireAuth()`
- ✅ Always check roles with `Auth::requireRole()`
- ✅ Use CSRF tokens for forms (when implemented)
- ✅ Hash passwords with `User::hashPassword()`
- ✅ Sanitize user inputs with `Validator::sanitize()`

## Troubleshooting

### Database Connection Error
- Check `config/app.php` database settings
- Ensure MySQL/MariaDB is running
- Verify database `hamotaghi_db` exists

### Session Not Working
- Check PHP session configuration
- Ensure `core/bootstrap.php` is loaded
- Check file permissions

### Model Not Found
- Ensure `core/bootstrap.php` is loaded first
- Check autoloader in `bootstrap.php`
- Verify model file exists in `models/` directory

### View Not Found
- Check view file path
- Ensure view file exists in `views/` directory
- Check file permissions

## Next Steps

1. Read `README_MVC.md` for detailed architecture guide
2. Read `REFACTORING_REPORT.md` for complete refactoring details
3. Read `DATABASE_SCHEMA.md` for database structure
4. Review example controllers and views
5. Start migrating your pages to MVC

---

**Quick Reference**: Keep this file handy for common patterns and examples.

