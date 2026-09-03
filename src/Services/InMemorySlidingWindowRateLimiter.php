<?php

declare(strict_types=1);

namespace LootRadar\Services;

use Closure;
use InvalidArgumentException;
use LootRadar\Contracts\RateLimiterInterface;
use LootRadar\Exceptions\RateLimitExceededException;

/**
 * Proteção de quota para uma única instância do processo.
 *
 * Use SqliteSlidingWindowRateLimiter quando vários processos compartilharem a
 * mesma chave de API.
 */
final class InMemorySlidingWindowRateLimiter implements RateLimiterInterface
{
    /** @var array<string, list<float>> */
    private array $events = [];

    /** @var array<string, float> */
    private array $blockedUntil = [];

    /** @var Closure(): float */
    private readonly Closure $clock;

    /**
     * @param null|Closure(): float $clock
     */
    public function __construct(
        private readonly int $maxRequests = 950,
        private readonly int $windowSeconds = 300,
        ?Closure $clock = null,
    ) {
        if ($this->maxRequests < 1) {
            throw new InvalidArgumentException('A quantidade máxima de requisições deve ser positiva.');
        }
        if ($this->windowSeconds < 1) {
            throw new InvalidArgumentException('A janela da quota deve ser positiva.');
        }

        $this->clock = $clock ?? static fn(): float => microtime(true);
    }

    #[\Override]
    public function consume(string $key): void
    {
        $this->validateKey($key);

        $now = ($this->clock)();
        $blockedUntil = $this->blockedUntil[$key] ?? null;
        if ($blockedUntil !== null && $blockedUntil > $now) {
            throw new RateLimitExceededException(max(1, (int)ceil($blockedUntil - $now)));
        }
        unset($this->blockedUntil[$key]);

        $cutoff = $now - $this->windowSeconds;
        $events = array_values(array_filter(
            $this->events[$key] ?? [],
            static fn(float $requestedAt): bool => $requestedAt > $cutoff,
        ));

        if (count($events) >= $this->maxRequests) {
            $oldestRequest = $events[0];

            throw new RateLimitExceededException(
                max(1, (int)ceil($oldestRequest + $this->windowSeconds - $now)),
            );
        }

        $events[] = $now;
        $this->events[$key] = $events;
    }

    #[\Override]
    public function suspend(string $key, int $retryAfterSeconds): void
    {
        $this->validateKey($key);
        if ($retryAfterSeconds < 1) {
            throw new InvalidArgumentException('O período de suspensão deve ser positivo.');
        }

        $blockedUntil = ($this->clock)() + $retryAfterSeconds;
        $this->blockedUntil[$key] = max($this->blockedUntil[$key] ?? 0.0, $blockedUntil);
    }

    private function validateKey(string $key): void
    {
        if (trim($key) === '' || strlen($key) > 255) {
            throw new InvalidArgumentException('A chave da quota deve conter entre 1 e 255 caracteres.');
        }
    }
}
