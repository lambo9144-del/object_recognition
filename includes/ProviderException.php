<?php

declare(strict_types=1);

/**
 * Error raised by an AI provider. The message is safe to show to users;
 * technical detail is logged separately.
 */
final class ProviderException extends RuntimeException
{
    public const TIMEOUT = 'timeout';
    public const AUTH = 'auth';
    public const CONFIG = 'config';
    public const API = 'api';
    public const INVALID = 'invalid';

    public function __construct(string $message, private string $kind = self::API, int $httpStatus = 502)
    {
        parent::__construct($message, $httpStatus);
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function httpStatus(): int
    {
        return $this->getCode() > 0 ? $this->getCode() : 502;
    }
}
