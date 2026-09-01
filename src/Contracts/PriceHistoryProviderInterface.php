<?php

declare(strict_types=1);

namespace LootRadar\Contracts;

use DateTimeImmutable;
use LootRadar\DTO\PriceHistory;

/**
 * Fonte de histórico de preços por jogo.
 */
interface PriceHistoryProviderInterface
{
    /**
     * @return list<PriceHistory>
     */
    public function fetchPriceHistory(
        string $gameId,
        string $country = 'US',
        ?DateTimeImmutable $since = null,
    ): array;
}
