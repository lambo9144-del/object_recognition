<?php

declare(strict_types=1);

/**
 * Chooses the AI provider(s) according to the "provider" setting
 * (auto / gemini / agnes) and returns a validated result.
 */
final class ObjectRecognizer
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? AppConfig::all();
    }

    /**
     * @return array<string, mixed> normalized result (see ProductResult)
     * @throws ProviderException
     */
    public function recognize(string $binary, string $mime): array
    {
        $providers = $this->selectedProviders();
        $configured = array_values(array_filter($providers, static fn (AIProvider $p): bool => $p->isConfigured()));

        if ($configured === []) {
            throw new ProviderException('AI provider is not configured.', ProviderException::CONFIG, 503);
        }

        $partial = null;     // a result that is valid but has no bounding box
        $lastError = null;

        foreach ($configured as $provider) {
            try {
                $raw = $provider->recognize($binary, $mime);
                $result = ProductResult::normalize($raw, $provider->id());
                if (ProductResult::isComplete($result)) {
                    return $result;
                }
                $partial ??= $result; // incomplete: try the next provider (Auto mode)
            } catch (ProviderException $e) {
                $lastError = $e;
                log_error('Provider ' . $provider->id() . ' failed: ' . $e->getMessage());
            } catch (InvalidArgumentException $e) {
                log_error('Provider ' . $provider->id() . ' gave an incomplete result: ' . $e->getMessage());
            }
        }

        if ($partial !== null) {
            return $partial;
        }

        if ($lastError !== null) {
            if ($lastError->httpStatus() === 429) {
                throw $lastError;
            }
            match ($lastError->kind()) {
                ProviderException::TIMEOUT => throw new ProviderException('The AI service took too long. Please try again.', ProviderException::TIMEOUT, 504),
                ProviderException::AUTH => throw new ProviderException('The AI provider rejected its API key. Please check Settings.', ProviderException::AUTH, 502),
                default => null,
            };
        }
        throw new ProviderException('Unable to identify the object. Please try again.', ProviderException::API, 502);
    }

    /** @return list<AIProvider> in the order they should be tried */
    private function selectedProviders(): array
    {
        $gemini = new GeminiVision($this->config);
        $agnes = new AgnesVision($this->config);

        return match ((string) ($this->config['provider'] ?? 'auto')) {
            'gemini' => [$gemini],
            'agnes' => [$agnes],
            default => [$gemini, $agnes],
        };
    }

    /**
     * Used by the Settings page.
     *
     * @return array<string, array{ok: bool, message: string}>
     */
    public function testAll(): array
    {
        return [
            'gemini' => (new GeminiVision($this->config))->testConnection(),
            'agnes' => (new AgnesVision($this->config))->testConnection(),
        ];
    }
}
