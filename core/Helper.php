<?php
/**
 * Helper Functions
 */

declare(strict_types=1);

class Helper
{
    /**
     * Redirect to URL
     */
    public static function redirect(string $url, int $code = 302): void
    {
        header("Location: {$url}", true, $code);
        exit;
    }

    /**
     * Get base URL
     */
    public static function baseUrl(string $path = ''): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $base = rtrim($base, '/');
        
        return "{$protocol}://{$host}{$base}/{$path}";
    }

    /**
     * Escape output for HTML
     */
    public static function e(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format date (Persian or Gregorian)
     */
    public static function formatDate(string $date, string $format = 'Y/m/d'): string
    {
        return date($format, strtotime($date));
    }

    /**
     * Format price
     */
    public static function formatPrice(int $price): string
    {
        return number_format($price, 0, '.', ',') . ' تومان';
    }

    /**
     * Get status badge HTML
     */
    public static function statusBadge(string $status): string
    {
        $badges = [
            'فعال' => 'success',
            'در انتظار' => 'warning',
            'تایید شده' => 'success',
            'رد شده' => 'danger',
            'لغو شده' => 'secondary',
            'در انتظار تایید' => 'warning',
            'غیرفعال' => 'secondary'
        ];

        $class = $badges[$status] ?? 'secondary';
        return "<span class='badge bg-{$class}'>" . self::e($status) . "</span>";
    }

    /**
     * Generate CSRF token
     */
    public static function csrfToken(): string
    {
        if (!Session::has('csrf_token')) {
            Session::set('csrf_token', bin2hex(random_bytes(32)));
        }
        return Session::get('csrf_token');
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCsrf(string $token): bool
    {
        return Session::has('csrf_token') && hash_equals(Session::get('csrf_token'), $token);
    }

    /**
     * Get request method
     */
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Check if request is POST
     */
    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /**
     * Get input value
     */
    public static function input(string $key, $default = null)
    {
        return $_REQUEST[$key] ?? $default;
    }

    /**
     * Get POST value
     */
    public static function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Get GET value
     */
    public static function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }
}

