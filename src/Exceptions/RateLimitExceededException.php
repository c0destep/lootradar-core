<?php

declare(strict_types=1);

namespace LootRadar\Exceptions;

use RuntimeException;
use Throwable;

final class RateLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfterSeconds,
        ?Throwable $previous = null,
    ) {
        $unit = $this->retryAfterSeconds === 1 ? 'segundo' : 'segundos';

        parent::__construct(
            "A cota de requisições foi atingida. Tente novamente em {$this->retryAfterSeconds} {$unit}.",
            previous: $previous,
        );
    }
}
