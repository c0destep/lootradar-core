<?php

declare(strict_types=1);

namespace LootRadar\Contracts;

use LootRadar\Exceptions\RateLimitExceededException;

/**
 * Reserva requisições em uma quota compartilhada e registra bloqueios externos.
 */
interface RateLimiterInterface
{
    /**
     * @throws RateLimitExceededException
     */
    public function consume(string $key): void;

    public function suspend(string $key, int $retryAfterSeconds): void;
}
