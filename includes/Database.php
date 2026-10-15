<?php

declare(strict_types=1);

/**
 * Optional MySQL connection (PDO). Only used for scan history.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function enabled(): bool
    {
        return (bool) AppConfig::get('db_enabled', false);
    }

    /** @throws PDOException */
    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                (string) AppConfig::get('db_host', 'localhost'),
                (string) AppConfig::get('db_name', 'ai_ar_scanner')
            );
            self::$pdo = new PDO($dsn, (string) AppConfig::get('db_user', 'root'), (string) AppConfig::get('db_pass', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }
}
