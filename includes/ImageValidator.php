<?php

declare(strict_types=1);

/**
 * Checks that an uploaded Base64 string really is a reasonable JPEG/PNG/WebP image.
 */
final class ImageValidator
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_DIMENSION = 4096;
    public const MIN_DIMENSION = 32;
    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @return array{0: string, 1: string} [binary image data, mime type]
     * @throws InvalidArgumentException with a message that is safe to show to users
     */
    public static function fromBase64(mixed $input): array
    {
        if (!is_string($input) || trim($input) === '') {
            throw new InvalidArgumentException('No image was received.');
        }

        // Accept both raw Base64 and "data:image/jpeg;base64,...."
        if (preg_match('#^data:[\w/+.\-]+;base64,#i', $input, $m) === 1) {
            $input = substr($input, strlen($m[0]));
        }
        $input = preg_replace('/\s+/', '', $input) ?? '';

        if (strlen($input) > (int) (self::MAX_BYTES * 4 / 3) + 16) {
            throw new InvalidArgumentException('The image is too large.');
        }

        $binary = base64_decode($input, true);
        if ($binary === false || $binary === '') {
            throw new InvalidArgumentException('The image data is not valid.');
        }
        if (strlen($binary) > self::MAX_BYTES) {
            throw new InvalidArgumentException('The image is too large.');
        }

        $info = @getimagesizefromstring($binary);
        if ($info === false) {
            throw new InvalidArgumentException('The file is not a valid image.');
        }

        $mime = (string) ($info['mime'] ?? '');
        if (class_exists('finfo')) {
            $detected = (new finfo(FILEINFO_MIME_TYPE))->buffer($binary);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }
        if (!in_array($mime, self::ALLOWED, true)) {
            throw new InvalidArgumentException('Only JPEG, PNG or WebP images are allowed.');
        }

        [$width, $height] = $info;
        if ($width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION) {
            throw new InvalidArgumentException('The image is too small.');
        }
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new InvalidArgumentException('The image dimensions are too large.');
        }

        return [$binary, $mime];
    }
}
