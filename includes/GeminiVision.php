<?php

declare(strict_types=1);

/**
 * Google Gemini vision (generateContent REST API).
 * The API key is sent in a header, never in the URL, so it cannot leak into logs.
 */
final class GeminiVision extends AIProvider
{
    private const BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function id(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return trim((string) ($this->config['gemini_api_key'] ?? '')) !== '';
    }

    public function recognize(string $binary, string $mime): array
    {
        if (!$this->isConfigured()) {
            throw new ProviderException('Gemini is not configured.', ProviderException::CONFIG, 503);
        }

        // Use only the active model to prevent 404 fallback errors
        $models = ['gemini-3.8-flash'];
        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $this->prompt()],
                    ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($binary)]],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
            ],
        ];

        return $this->tryModels($models, function (string $model) use ($payload): array {
            $response = $this->request(
                'POST',
                self::BASE . rawurlencode($model) . ':generateContent',
                ['x-goog-api-key: ' . $this->key()],
                $payload,
                60 // Increased timeout to 60s
            );
            if ($response['status'] < 200 || $response['status'] >= 300) {
                $this->failFromStatus($response['status'], $response['body']);
            }

            $text = '';
            foreach ($response['json']['candidates'][0]['content']['parts'] ?? [] as $part) {
                if (isset($part['text']) && is_string($part['text'])) {
                    $text .= $part['text'];
                }
            }
            if (trim($text) === '') {
                throw new ProviderException('The AI returned an empty answer.', ProviderException::INVALID, 502);
            }
            return $this->parseModelJson($text);
        });
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Not configured'];
        }

        $model = 'gemini-3.8-flash';

        try {
            $response = $this->request('GET', self::BASE . rawurlencode($model), ['x-goog-api-key: ' . $this->key()], null, 15);
        } catch (ProviderException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        return match (true) {
            $response['status'] >= 200 && $response['status'] < 300 => ['ok' => true, 'message' => 'Connected'],
            $response['status'] === 400 || $response['status'] === 401 || $response['status'] === 403 => ['ok' => false, 'message' => 'The API key was rejected'],
            $response['status'] === 404 => ['ok' => false, 'message' => 'Model not found: check the model name'],
            $response['status'] === 503 => ['ok' => false, 'message' => 'Model experiencing high demand (503)'],
            default => ['ok' => false, 'message' => 'Connection failed (HTTP ' . $response['status'] . ')'],
        };
    }

    private function key(): string
    {
        return trim((string) $this->config['gemini_api_key']);
    }
}
