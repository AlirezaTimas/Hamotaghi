<?php
/**
 * Session Management
 * Handles session initialization and security
 */

declare(strict_types=1);

class Session
{
    private static bool $started = false;

    /**
     * Start session if not already started
     */
    public static function start(): void
    {
        if (!self::$started) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            self::$started = true;
        }
    }

    /**
     * Get session value
     */
    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set session value
     */
    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Check if session key exists
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session key
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Destroy session
     */
    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
        self::$started = false;
    }

    /**
     * Regenerate session ID (security)
     */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }
}

