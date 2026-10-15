<?php

declare(strict_types=1);

/**
 * Loads application configuration.
 *
 * Order of precedence (later wins):
 *   1. built-in defaults
 *   2. config/config.php        (admin login, database, optional API keys)
 *   3. storage/settings.php     (values saved from the Settings page)
 *
 * Both config/ and storage/ are blocked from web access by .htaccess.
 */
final class AppConfig
{
    /** Keys that may be changed from the Settings page. */
    public const EDITABLE = [
        'provider',
        'gemini_model',
        'gemini_fallback_models',
        'gemini_api_key',
        'agnes_model',
        'agnes_fallback_models',
        'agnes_base_url',
        'agnes_api_key',
        'timeout',
        'web_lookup',
    ];

    private static ?array $cache = null;

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'provider' => 'auto',
            'gemini_model' => 'gemini-2.5-flash',
            'gemini_fallback_models' => "gemini-2.0-flash",
            'gemini_api_key' => '',
            'agnes_model' => '',
            'agnes_fallback_models' => '',
            'agnes_base_url' => '',
            'agnes_api_key' => '',
            'timeout' => 45,
            'web_lookup' => true,
            'db_enabled' => false,
            'db_host' => 'localhost',
            'db_name' => 'ai_ar_scanner',
            'db_user' => 'root',
            'db_pass' => '',
            'admin_user' => 'admin',
            'admin_password_hash' => '',
            'timezone' => 'UTC',
        ];
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $config = self::defaults();

        $file = APP_ROOT . '/config/config.php';
        if (is_file($file)) {
            $loaded = require $file;
            if (is_array($loaded)) {
                $config = array_merge($config, $loaded);
            }
        }

        $stored = self::readStore();
        foreach (self::EDITABLE as $key) {
            if (array_key_exists($key, $stored)) {
                // An empty saved key must not wipe a key defined in config.php.
                if (str_ends_with($key, '_api_key') && $stored[$key] === '') {
                    continue;
                }
                $config[$key] = $stored[$key];
            }
        }

        self::$cache = $config;
        return $config;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    private static function readStore(): array
    {
        $path = self::storePath();
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        // The first line is a PHP guard (see save()); the JSON starts on line 2.
        $json = $raw === false ? false : substr($raw, (int) strpos($raw, "\n") + 1);
        $data = $json === false ? null : json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private static function storePath(): string
    {
        return APP_ROOT . '/storage/settings.php';
    }

    /**
     * Save editable values to storage/settings.php.
     *
     * @param array<string, mixed> $values
     */
    public static function save(array $values): void
    {
        $dir = APP_ROOT . '/storage';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            throw new RuntimeException('storage folder is missing');
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('storage folder is not writable');
        }

        $current = self::readStore();
        foreach (self::EDITABLE as $key) {
            if (array_key_exists($key, $values)) {
                $current[$key] = $values[$key];
            }
        }

        $tmp = self::storePath() . '.tmp';
        $json = json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // Saved as a .php file with a guard line: if a server ignores .htaccess and
        // someone requests this file directly, PHP runs the guard and prints nothing.
        $guard = "<?php http_response_code(404); exit; ?>\n";
        if ($json === false || file_put_contents($tmp, $guard . $json, LOCK_EX) === false) {
            throw new RuntimeException('could not write settings');
        }
        @chmod($tmp, 0600);
        if (!rename($tmp, self::storePath())) {
            throw new RuntimeException('could not replace settings file');
        }
        self::$cache = null;
    }

    /**
     * Settings that are safe to send to the browser. API keys are never included,
     * only whether one has been saved.
     *
     * @return array<string, mixed>
     */
    public static function publicSettings(): array
    {
        $all = self::all();
        $out = [];
        foreach (self::EDITABLE as $key) {
            if (!str_ends_with($key, '_api_key')) {
                $out[$key] = $all[$key];
            }
        }
        $out['gemini_key_saved'] = trim((string) $all['gemini_api_key']) !== '';
        $out['agnes_key_saved'] = trim((string) $all['agnes_api_key']) !== '';
        return $out;
    }

    /** Split a "one model per line" (or comma separated) text into a clean list. */
    public static function modelList(string $text): array
    {
        $parts = preg_split('/[\r\n,]+/', $text) ?: [];
        $list = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '' && preg_match('/^[A-Za-z0-9._\-\/:]{1,100}$/', $part)) {
                $list[] = $part;
            }
        }
        return array_values(array_unique($list));
    }

    /** Remove any configured API key from a string before it is logged. */
    public static function redact(string $text): string
    {
        try {
            $config = self::all();
        } catch (Throwable) {
            return $text;
        }
        foreach (['gemini_api_key', 'agnes_api_key'] as $key) {
            $secret = trim((string) ($config[$key] ?? ''));
            if (strlen($secret) >= 8) {
                $text = str_replace($secret, '[redacted]', $text);
            }
        }
        return $text;
    }
}
