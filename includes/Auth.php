<?php

declare(strict_types=1);

/**
 * Very small admin login used to protect settings.php and history.php.
 * The scanner page itself stays public.
 */
final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_name('aiar_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['admin_ok']);
    }

    public static function login(string $username, string $password): bool
    {
        self::start();
        $expectedUser = (string) AppConfig::get('admin_user', 'admin');
        $hash = (string) AppConfig::get('admin_password_hash', '');

        $userOk = hash_equals($expectedUser, $username);
        $passOk = $hash !== '' && password_verify($password, $hash);

        if ($userOk && $passOk) {
            session_regenerate_id(true);
            $_SESSION['admin_ok'] = true;
            return true;
        }
        usleep(800000); // slow down password guessing
        return false;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::start();
        return $token !== null && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    /** For API endpoints: stop with 401 unless logged in. */
    public static function requireApi(): void
    {
        if (!self::check()) {
            json_out(['ok' => false, 'error' => 'Please sign in first.'], 401);
        }
    }
}
