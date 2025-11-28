<?php
/**
 * Input Helper - Safe input access
 */

declare(strict_types=1);

class InputHelper
{
    /**
     * Get GET parameter safely
     */
    public static function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Get POST parameter safely
     */
    public static function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Get SESSION value safely
     */
    public static function session(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Get integer from GET
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }

    /**
     * Get integer from POST
     */
    public static function postInt(string $key, int $default = 0): int
    {
        $value = self::post($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }

    /**
     * Get string from GET (trimmed)
     */
    public static function getString(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);
        return is_string($value) ? trim($value) : $default;
    }

    /**
     * Get string from POST (trimmed)
     */
    public static function postString(string $key, string $default = ''): string
    {
        $value = self::post($key, $default);
        return is_string($value) ? trim($value) : $default;
    }

    /**
     * Get array from POST
     */
    public static function postArray(string $key, array $default = []): array
    {
        $value = self::post($key, $default);
        return is_array($value) ? $value : $default;
    }

    /**
     * Check if POST key exists
     */
    public static function hasPost(string $key): bool
    {
        return isset($_POST[$key]);
    }

    /**
     * Check if GET key exists
     */
    public static function hasGet(string $key): bool
    {
        return isset($_GET[$key]);
    }
}

