<?php

declare(strict_types=1);

/**
 * Optional second step: look the product up on Wikipedia.
 *
 * The AI result is never replaced. A web page is only attached as a
 * "reference" when its title contains the manufacturer AND most of the
 * product-name words, so an unrelated search hit is ignored.
 */
final class ProductLookup
{
    private const GENERIC_WORDS = [
        'laptop', 'notebook', 'phone', 'smartphone', 'mobile', 'camera', 'headphones', 'headphone',
        'keyboard', 'mouse', 'bottle', 'book', 'the', 'a', 'an', 'and', 'with', 'wireless', 'device',
    ];

    /**
     * @return array{title: string, url: string}|null
     */
    public function find(string $productName, string $manufacturer): ?array
    {
        $manufacturer = trim($manufacturer);
        if ($manufacturer === '' || strcasecmp($manufacturer, 'Unknown') === 0 || !function_exists('curl_init')) {
            return null;
        }

        $query = stripos($productName, $manufacturer) === false ? "$manufacturer $productName" : $productName;
        $url = 'https://en.wikipedia.org/w/api.php?' . http_build_query([
            'action' => 'opensearch',
            'search' => $query,
            'limit' => 3,
            'namespace' => 0,
            'format' => 'json',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'AI-AR-Object-Scanner/1.0 (student project)',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            return null;
        }
        $data = json_decode((string) $body, true);
        $titles = is_array($data) ? ($data[1] ?? []) : [];
        $links = is_array($data) ? ($data[3] ?? []) : [];

        foreach ($titles as $i => $title) {
            $link = $links[$i] ?? '';
            if (is_string($title) && is_string($link) && $this->sameProduct($title, $productName, $manufacturer)
                && preg_match('#^https://en\.wikipedia\.org/wiki/#', $link) === 1) {
                return ['title' => $title, 'url' => $link];
            }
        }
        return null;
    }

    /** Evidence check: manufacturer in the title and enough product words match. */
    private function sameProduct(string $title, string $productName, string $manufacturer): bool
    {
        $title = mb_strtolower($title);
        if (!str_contains($title, mb_strtolower($manufacturer))) {
            return false;
        }

        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($productName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $manufacturerWords = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($manufacturer), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_diff($words, $manufacturerWords, self::GENERIC_WORDS);
        if ($words === []) {
            return false; // only generic words: nothing specific to verify
        }

        $hits = 0;
        foreach ($words as $word) {
            if (str_contains($title, $word)) {
                $hits++;
            }
        }
        return $hits / count($words) >= 0.6;
    }
}
