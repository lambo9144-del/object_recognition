<?php

declare(strict_types=1);

/**
 * Cleans and validates what an AI provider returned.
 * AI output is untrusted: every field is type-checked, trimmed and length-limited.
 */
final class ProductResult
{
    /**
     * @param array<string, mixed> $raw decoded JSON from the AI
     * @return array<string, mixed> normalized result (boundingBox may be null)
     * @throws InvalidArgumentException when productName or objectLabel is missing
     */
    public static function normalize(array $raw, string $provider): array
    {
        $objectLabel = self::text($raw['objectLabel'] ?? '', 80);
        $productName = self::text($raw['productName'] ?? '', 120);

        if ($productName === '' || $objectLabel === '') {
            throw new InvalidArgumentException('AI result is missing the object or product name.');
        }

        $manufacturer = self::text($raw['manufacturer'] ?? '', 80);
        $specification = self::text($raw['specification'] ?? '', 300);
        $description = self::text($raw['description'] ?? '', 500);

        return [
            'objectLabel' => $objectLabel,
            'productName' => $productName,
            'manufacturer' => $manufacturer !== '' ? $manufacturer : 'Unknown',
            'specification' => $specification !== '' ? $specification : 'Not visible in the image',
            'description' => $description !== '' ? $description : 'No description available.',
            'boundingBox' => self::boundingBox($raw['boundingBox'] ?? null),
            'confidence' => self::confidence($raw['confidence'] ?? null),
            'provider' => $provider,
        ];
    }

    /** A result is complete when it has a usable bounding box. */
    public static function isComplete(array $result): bool
    {
        return is_array($result['boundingBox'] ?? null);
    }

    /** Plain text only, no HTML, no control characters, limited length. */
    private static function text(mixed $value, int $max): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return '';
        }
        $text = strip_tags((string) $value);
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        return mb_substr($text, 0, $max);
    }

    /** Accepts 0-1 or 0-100 and always returns a float between 0 and 1. */
    private static function confidence(mixed $value): float
    {
        if (is_string($value)) {
            $value = rtrim(trim($value), '%');
        }
        if (!is_numeric($value)) {
            return 0.0;
        }
        $number = (float) $value;
        if ($number > 1 && $number <= 100) {
            $number /= 100;
        }
        return round(max(0.0, min(1.0, $number)), 2);
    }

    /**
     * @return array{ymin:int,xmin:int,ymax:int,xmax:int}|null
     */
    private static function boundingBox(mixed $box): ?array
    {
        if (!is_array($box)) {
            return null;
        }
        // Some models return [ymin, xmin, ymax, xmax] as a list.
        if (array_is_list($box) && count($box) === 4) {
            $box = ['ymin' => $box[0], 'xmin' => $box[1], 'ymax' => $box[2], 'xmax' => $box[3]];
        }

        $values = [];
        foreach (['ymin', 'xmin', 'ymax', 'xmax'] as $key) {
            if (!isset($box[$key]) || !is_numeric($box[$key])) {
                return null;
            }
            $values[$key] = (int) round(max(0.0, min(1000.0, (float) $box[$key])));
        }

        if ($values['ymin'] > $values['ymax']) {
            [$values['ymin'], $values['ymax']] = [$values['ymax'], $values['ymin']];
        }
        if ($values['xmin'] > $values['xmax']) {
            [$values['xmin'], $values['xmax']] = [$values['xmax'], $values['xmin']];
        }
        if ($values['ymax'] - $values['ymin'] < 20 || $values['xmax'] - $values['xmin'] < 20) {
            return null; // too small to be a real detection
        }
        return $values;
    }
}
