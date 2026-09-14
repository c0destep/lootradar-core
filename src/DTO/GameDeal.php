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
        $title = $data['title'] ?? null;
        $storeName = $data['storeName'] ?? null;
        $originalPrice = $data['originalPrice'] ?? null;
        $currentPrice = $data['currentPrice'] ?? null;
        $checkoutUrl = $data['checkoutUrl'] ?? null;
        $approvalRating = $data['approvalRating'] ?? null;
        $isFree = $data['isFree'] ?? null;
        $currency = $data['currency'] ?? null;
        $expiresAt = $data['expiresAt'] ?? null;
        $historicalLow = $data['historicalLow'] ?? null;

        return new self(
            title: self::stringValue($title, 'Desconhecido'),
            storeName: self::stringValue($storeName, ''),
            originalPrice: self::floatValue($originalPrice) ?? 0.0,
            currentPrice: self::floatValue($currentPrice) ?? 0.0,
            checkoutUrl: self::stringValue($checkoutUrl, ''),
            approvalRating: self::intValue($approvalRating),
            isFree: is_scalar($isFree) ? (bool) $isFree : false,
            currency: self::nullableStringValue($currency),
            expiresAt: self::nullableStringValue($expiresAt),
            historicalLow: self::floatValue($historicalLow),
        );
    }

    private static function stringValue(mixed $value, string $default): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    private static function nullableStringValue(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private static function floatValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (float) $value;
        }

        return is_string($value) && is_numeric($value) ? (float) $value : null;
    }

    private static function intValue(mixed $value): ?int
    {
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (int) $value;
        }

        return is_string($value) && is_numeric($value) ? (int) $value : null;
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
