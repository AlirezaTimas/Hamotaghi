<?php
/**
 * Database Connection Handler
 * Singleton pattern for PDO connection
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * Initialize database configuration
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Get database connection instance
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $host = self::$config['host'] ?? 'localhost';
                $dbname = self::$config['dbname'] ?? 'hamotaghi_db';
                $username = self::$config['username'] ?? 'root';
                $password = self::$config['password'] ?? '';
                $charset = self::$config['charset'] ?? 'utf8mb4';

                $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
                
                self::$instance = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                self::$instance->exec("SET NAMES {$charset}");
            } catch (PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                throw new RuntimeException("خطا در اتصال به دیتابیس", 0, $e);
            }
        }

        return self::$instance;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize singleton");
    }
}

