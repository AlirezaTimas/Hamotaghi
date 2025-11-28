<?php
/**
 * Application Bootstrap
 * Initialize core components
 */

// Prevent multiple includes
if (defined('BOOTSTRAP_LOADED')) {
    return;
}
define('BOOTSTRAP_LOADED', true);

// Start output buffering only if not already started
if (!ob_get_level()) {
    ob_start();
}

// Load core classes first (before autoloader)
if (!class_exists('Database')) {
    require_once __DIR__ . '/Database.php';
}
if (!class_exists('Session')) {
    require_once __DIR__ . '/Session.php';
}
if (!class_exists('Auth')) {
    require_once __DIR__ . '/Auth.php';
}
if (!class_exists('Validator')) {
    require_once __DIR__ . '/Validator.php';
}
if (!class_exists('Helper')) {
    require_once __DIR__ . '/Helper.php';
}
if (!class_exists('Router')) {
    require_once __DIR__ . '/Router.php';
}
if (!class_exists('InputHelper')) {
    require_once __DIR__ . '/InputHelper.php';
}

// Load configuration safely
$configFile = __DIR__ . '/../config/app.php';
if (file_exists($configFile)) {
    try {
        $config = require $configFile;
        
        // Initialize database only if config is valid
        if (isset($config['database']) && class_exists('Database')) {
            try {
                Database::init($config['database']);
            } catch (Exception $e) {
                // Database init failed, but don't break the page
                error_log("Database init failed: " . $e->getMessage());
            }
        }
        
        // Set timezone
        if (isset($config['timezone'])) {
            date_default_timezone_set($config['timezone']);
        }
    } catch (Exception $e) {
        error_log("Config load failed: " . $e->getMessage());
    }
}

// Start session safely
if (class_exists('Session')) {
    try {
        Session::start();
    } catch (Exception $e) {
        error_log("Session start failed: " . $e->getMessage());
    }
}

// Error reporting (disable in production)
if (defined('DEBUG') && DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Autoloader for other classes (models, controllers, app classes)
spl_autoload_register(function ($class) {
    // Handle App namespace (PSR-4)
    if (strpos($class, 'App\\') === 0) {
        $relativeClass = substr($class, 4);
        $file = __DIR__ . '/../app/' . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    
    // Handle legacy classes (models, controllers without namespace)
    $paths = [
        __DIR__ . '/../models/' . $class . '.php',
        __DIR__ . '/../controllers/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

