<?php

declare(strict_types=1);

namespace LootRadar\Contracts;

use LootRadar\DTO\GameDeal;

interface StoreAdapterInterface
{
    /**
     * @return array<GameDeal>
     */
    public function fetchDeals(): array;

    /**
     * @return array<GameDeal>
     */
    public function fetchFreeGames(): array;
}
