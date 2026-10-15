<?php

declare(strict_types=1);

/**
 * Tiny per-IP rate limit for the public scan endpoint, so a stranger who finds
 * the URL cannot burn through your AI quota. Uses plain files in storage/rate/.
 */
final class RateLimiter
{
    public static function allow(string $ip, int $max = 15, int $windowSeconds = 60): bool
    {
        $dir = APP_ROOT . '/storage/rate';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            return true; // cannot store state: do not block real users
        }

        $file = $dir . '/' . hash('sha256', $ip) . '.json';
        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            return true;
        }
        flock($handle, LOCK_EX);

        $now = time();
        $stamps = json_decode((string) stream_get_contents($handle), true);
        $stamps = is_array($stamps) ? array_values(array_filter($stamps, static fn ($t): bool => is_int($t) && $t > $now - $windowSeconds)) : [];

        $allowed = count($stamps) < $max;
        if ($allowed) {
            $stamps[] = $now;
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($stamps));
        flock($handle, LOCK_UN);
        fclose($handle);

        // Occasionally remove old files.
        if (random_int(1, 50) === 1) {
            foreach (glob($dir . '/*.json') ?: [] as $old) {
                if (filemtime($old) < $now - 3600) {
                    @unlink($old);
                }
            }
        }
        return $allowed;
    }
}
