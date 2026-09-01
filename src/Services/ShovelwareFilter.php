<?php

declare(strict_types=1);

namespace LootRadar\Services;

use LootRadar\DTO\GameDeal;

/**
 * Descarta ofertas de jogos com avaliação abaixo do limiar ("shovelware").
 *
 * Decisão de projeto: ofertas com score `null` (desconhecido) são MANTIDAS por
 * padrão. O ITAD não expõe score algum — tratar "sem nota" como "nota zero"
 * esvaziaria o comando `deal` inteiro. Quem quiser rigor máximo passa
 * `keepUnrated: false`.
 */
final class ShovelwareFilter
{
    public function __construct(
        private readonly int $minimumRating = 60,
        private readonly bool $keepUnrated = true,
    ) {
    }

    /**
     * @param array<int, GameDeal> $deals
     *
     * @return list<GameDeal>
     */
    #[\NoDiscard('a lista filtrada precisa ser usada; descartá-la mantém o shovelware visível')]
    public function filter(array $deals): array
    {
        return array_values(array_filter($deals, $this->accepts(...)));
    }

    public function accepts(GameDeal $deal): bool
    {
        if ($deal->approvalRating === null) {
            return $this->keepUnrated;
        }

        return $deal->approvalRating >= $this->minimumRating;
    }

    public function minimumRating(): int
    {
        return $this->minimumRating;
    }
}
