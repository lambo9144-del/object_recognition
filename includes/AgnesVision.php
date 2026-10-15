<?php

declare(strict_types=1);

/**
 * Agnes vision provider.
 *
 * This class assumes Agnes exposes an OpenAI-compatible API
 * (POST {base}/chat/completions with a Bearer key). Because the endpoint can
 * differ between accounts, the base URL is a setting and nothing is hard-coded.
 * If your Agnes API uses a different format, change only this file.
 */
final class AgnesVision extends AIProvider
{
    public function id(): string
    {
        return 'agnes';
    }

    public function isConfigured(): bool
    {
        return trim((string) ($this->config['agnes_api_key'] ?? '')) !== ''
            && $this->baseUrl() !== '';
    }

    public function recognize(string $binary, string $mime): array
    {
        if (!$this->isConfigured()) {
            throw new ProviderException('Agnes is not configured.', ProviderException::CONFIG, 503);
        }

        $models = $this->modelsToTry('agnes_model', 'agnes_fallback_models');
        $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($binary);

        return $this->tryModels($models, function (string $model) use ($dataUrl): array {
            $payload = [
                'model' => $model,
                'temperature' => 0.1,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $this->prompt()],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                    ],
                ]],
            ];
            $response = $this->request('POST', $this->baseUrl() . '/chat/completions', $this->authHeaders(), $payload);
            if ($response['status'] < 200 || $response['status'] >= 300) {
                $this->failFromStatus($response['status'], $response['body']);
            }

            $content = $response['json']['choices'][0]['message']['content'] ?? '';
            if (is_array($content)) { // some APIs return a list of text parts
                $joined = '';
                foreach ($content as $part) {
                    if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                        $joined .= $part['text'];
                    }
                }
                $content = $joined;
            }
            if (!is_string($content) || trim($content) === '') {
                throw new ProviderException('The AI returned an empty answer.', ProviderException::INVALID, 502);
            }
            return $this->parseModelJson($content);
        });
    }

    public function testConnection(): array
    {
        if (trim((string) ($this->config['agnes_api_key'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'Not configured'];
        }
        if ($this->baseUrl() === '') {
            return ['ok' => false, 'message' => 'Agnes base URL is not set'];
        }
        try {
            $response = $this->request('GET', $this->baseUrl() . '/models', $this->authHeaders(), null, 15);
        } catch (ProviderException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        return match (true) {
            $response['status'] >= 200 && $response['status'] < 300 => ['ok' => true, 'message' => 'Connected'],
            $response['status'] === 401 || $response['status'] === 403 => ['ok' => false, 'message' => 'The API key was rejected'],
            $response['status'] === 404 => ['ok' => false, 'message' => 'Endpoint not found: check the base URL'],
            default => ['ok' => false, 'message' => 'Connection failed (HTTP ' . $response['status'] . ')'],
        };
    }

    /** @return list<string> */
    private function authHeaders(): array
    {
        return ['Authorization: Bearer ' . trim((string) $this->config['agnes_api_key'])];
    }

    private function baseUrl(): string
    {
        $url = rtrim(trim((string) ($this->config['agnes_base_url'] ?? '')), '/');
        return preg_match('#^https?://#i', $url) === 1 ? $url : '';
    }
}
