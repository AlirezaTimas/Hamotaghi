<?php
/**
 * Authentication Handler
 * Manages user authentication and authorization
 */

declare(strict_types=1);

class Auth
{
    private static ?array $user = null;

    /**
     * Check if user is logged in
     */
    public static function check(): bool
    {
        return Session::has('user_id');
    }

    /**
     * Get current user ID
     */
    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    /**
     * Get current user data
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        if (self::$user === null) {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT id, full_name, email, usertype, gender, personality_type, 
                       city, province, phone, avatar, bio, user_score
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([self::id()]);
            self::$user = $stmt->fetch() ?: null;
        }

        return self::$user;
    }

    /**
     * Login user
     */
    public static function login(int $userId, array $userData = []): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        if (!empty($userData)) {
            Session::set('user_data', $userData);
        }
        self::$user = null; // Reset cache
    }

    /**
     * Logout user
     */
    public static function logout(): void
    {
        self::$user = null;
        Session::remove('user_id');
        Session::remove('user_data');
        Session::destroy();
    }

    /**
     * Check if user is landlord
     */
    public static function isLandlord(): bool
    {
        $user = self::user();
        return $user && $user['usertype'] === 'صاحب‌خانه';
    }

    /**
     * Check if user is roommate
     */
    public static function isRoommate(): bool
    {
        $user = self::user();
        return $user && $user['usertype'] === 'هم‌خانه';
    }

    /**
     * Require authentication (redirect if not logged in)
     */
    public static function requireAuth(string $redirectTo = '/pages/login.php'): void
    {
        if (!self::check()) {
            header("Location: {$redirectTo}");
            exit;
        }
    }

    /**
     * Require specific user type
     */
    public static function requireRole(string $role, string $redirectTo = '/pages/dashboard.php'): void
    {
        self::requireAuth();
        $user = self::user();
        
        $roleMap = [
            'landlord' => 'صاحب‌خانه',
            'roommate' => 'هم‌خانه'
        ];

        $expectedType = $roleMap[$role] ?? $role;
        
        if (!$user || $user['usertype'] !== $expectedType) {
            header("Location: {$redirectTo}");
            exit;
        }
    }
}

