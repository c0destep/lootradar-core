<?php

declare(strict_types=1);

namespace LootRadar\DTO;

readonly class GameDeal
{
    /**
     * @param int|null    $approvalRating Score 0–100. `null` = desconhecido: o ITAD não expõe score,
     *                                    e "sem nota" não é o mesmo que "nota zero" (ver ShovelwareFilter).
     * @param string|null $currency       Código ISO 4217 do preço (ITAD devolve na moeda do país consultado).
     * @param string|null $expiresAt      Fim da promoção em ISO 8601, quando a loja informa.
     * @param float|null  $historicalLow  Menor preço histórico conhecido, base do "Histórico Alinhado".
     */
    public function __construct(
        public string $title,
        public string $storeName,
        public float $originalPrice,
        public float $currentPrice,
        public string $checkoutUrl,
        public ?int $approvalRating = null,
        public bool $isFree = false,
        public ?string $currency = null,
        public ?string $expiresAt = null,
        public ?float $historicalLow = null,
    ) {
    }

    /**
     * @return array{title: string, storeName: string, originalPrice: float, currentPrice: float, checkoutUrl: string,
     *               approvalRating: int|null, isFree: bool, currency: string|null, expiresAt: string|null,
     *               historicalLow: float|null, discountPercentage: int, isAtHistoricalLow: bool}
     */
    public function toArray(): array
    {
        return [
            'title'              => $this->title,
            'storeName'          => $this->storeName,
            'originalPrice'      => $this->originalPrice,
            'currentPrice'       => $this->currentPrice,
            'checkoutUrl'        => $this->checkoutUrl,
            'approvalRating'     => $this->approvalRating,
            'isFree'             => $this->isFree,
            'currency'           => $this->currency,
            'expiresAt'          => $this->expiresAt,
            'historicalLow'      => $this->historicalLow,
            'discountPercentage' => $this->getDiscountPercentage(),
            'isAtHistoricalLow'  => $this->isAtHistoricalLow(),
        ];
    }

    public function getDiscountPercentage(): int
    {
        if ($this->originalPrice === 0.0) {
            return 100;
        }
        return (int)round((1 - ($this->currentPrice / $this->originalPrice)) * 100);
    }

    /**
     * O preço atual empata ou bate o menor preço já registrado.
     *
     * Retorna false quando não há histórico conhecido — "não sei" não deve ser
     * anunciado ao usuário como "melhor preço de todos os tempos".
     */
    public function isAtHistoricalLow(): bool
    {
        return $this->historicalLow !== null && $this->currentPrice <= $this->historicalLow;
    }

    /**
     * Cria a instância a partir do array serializado (o formato que sai do cache).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: (string)($data['title'] ?? 'Desconhecido'),
            storeName: (string)($data['storeName'] ?? ''),
            originalPrice: (float)($data['originalPrice'] ?? 0.0),
            currentPrice: (float)($data['currentPrice'] ?? 0.0),
            checkoutUrl: (string)($data['checkoutUrl'] ?? ''),
            approvalRating: isset($data['approvalRating']) ? (int)$data['approvalRating'] : null,
            isFree: (bool)($data['isFree'] ?? false),
            currency: isset($data['currency']) ? (string)$data['currency'] : null,
            expiresAt: isset($data['expiresAt']) ? (string)$data['expiresAt'] : null,
            historicalLow: isset($data['historicalLow']) ? (float)$data['historicalLow'] : null,
        );
    }

    /**
     * Cópia com a URL de checkout trocada — usada pelo estágio de higienização
     * do pipeline, já que a classe é readonly.
     */
    public function withCheckoutUrl(string $checkoutUrl): self
    {
        return new self(
            title: $this->title,
            storeName: $this->storeName,
            originalPrice: $this->originalPrice,
            currentPrice: $this->currentPrice,
            checkoutUrl: $checkoutUrl,
            approvalRating: $this->approvalRating,
            isFree: $this->isFree,
            currency: $this->currency,
            expiresAt: $this->expiresAt,
            historicalLow: $this->historicalLow,
        );
    }

    /**
     * Cópia com preços e moeda convertidos pelo estágio de moeda do pipeline.
     */
    public function withPricing(
        float $originalPrice,
        float $currentPrice,
        string $currency,
        ?float $historicalLow,
    ): self {
        return new self(
            title: $this->title,
            storeName: $this->storeName,
            originalPrice: $originalPrice,
            currentPrice: $currentPrice,
            checkoutUrl: $this->checkoutUrl,
            approvalRating: $this->approvalRating,
            isFree: $this->isFree,
            currency: $currency,
            expiresAt: $this->expiresAt,
            historicalLow: $historicalLow,
        );
    }
}
