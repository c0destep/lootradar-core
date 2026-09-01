<?php

declare(strict_types=1);

namespace LootRadar\DTO;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Um ponto do histórico de preço de um jogo em uma loja.
 */
readonly class PriceHistory
{
    public function __construct(
        public string $gameId,
        public DateTimeImmutable $recordedAt,
        public string $storeName,
        public Money $price,
        public Money $regularPrice,
        public int $discountPercentage,
    ) {
        if (trim($this->gameId) === '') {
            throw new InvalidArgumentException('O identificador do jogo não pode ser vazio.');
        }

        if (trim($this->storeName) === '') {
            throw new InvalidArgumentException('O nome da loja não pode ser vazio.');
        }

        if ($this->price->currency !== $this->regularPrice->currency) {
            throw new InvalidArgumentException('Preço e preço regular devem usar a mesma moeda.');
        }

        if ($this->discountPercentage < 0 || $this->discountPercentage > 100) {
            throw new InvalidArgumentException('O desconto deve estar entre 0 e 100.');
        }
    }

    /**
     * @return array{gameId: string, recordedAt: string, storeName: string, price: array{amount: float, currency: string}, regularPrice: array{amount: float, currency: string}, discountPercentage: int}
     */
    public function toArray(): array
    {
        return [
            'gameId' => $this->gameId,
            'recordedAt' => $this->recordedAt->format(DATE_ATOM),
            'storeName' => $this->storeName,
            'price' => $this->price->toArray(),
            'regularPrice' => $this->regularPrice->toArray(),
            'discountPercentage' => $this->discountPercentage,
        ];
    }
}
