<?php

declare(strict_types=1);

namespace LootRadar\Services;

use InvalidArgumentException;
use LootRadar\Contracts\CacheInterface;
use LootRadar\Contracts\ExchangeRateProviderInterface;
use LootRadar\DTO\GameDeal;
use LootRadar\DTO\Money;
use UnexpectedValueException;

/**
 * Converte preços usando uma fonte de taxas injetada e cacheada.
 *
 * A moeda-alvo deve ser uma configuração explícita de quem consome o Core;
 * localidade e fuso horário não são usados como inferência de moeda.
 */
final class CurrencyConverter
{
    public function __construct(
        private readonly ExchangeRateProviderInterface $rateProvider,
        private readonly CacheInterface $cache,
        private readonly int $rateTtlSeconds = 21600,
    ) {
        if ($this->rateTtlSeconds < 1) {
            throw new InvalidArgumentException('O TTL da taxa deve ser positivo.');
        }
    }

    public function convert(Money $money, string $targetCurrency): Money
    {
        $target = new Money(0.0, $targetCurrency)->currency;
        if ($money->currency === $target) {
            return $money;
        }

        return new Money(
            amount: $money->amount * $this->exchangeRate($money->currency, $target),
            currency: $target,
        );
    }

    /**
     * Ofertas sem moeda informada não podem ser convertidas com segurança e
     * são preservadas como recebidas.
     */
    public function convertDeal(GameDeal $deal, string $targetCurrency): GameDeal
    {
        if ($deal->currency === null) {
            return $deal;
        }

        $original = $this->convert(new Money($deal->originalPrice, $deal->currency), $targetCurrency);
        $current = $this->convert(new Money($deal->currentPrice, $deal->currency), $targetCurrency);
        $historicalLow = $deal->historicalLow === null
            ? null
            : $this->convert(new Money($deal->historicalLow, $deal->currency), $targetCurrency)->amount;

        return $deal->withPricing(
            originalPrice: $original->amount,
            currentPrice: $current->amount,
            currency: $targetCurrency,
            historicalLow: $historicalLow,
        );
    }

    private function exchangeRate(string $fromCurrency, string $toCurrency): float
    {
        $key = 'exchange-rate:' . strtolower($fromCurrency) . ':' . strtolower($toCurrency);
        $cached = $this->cache->get($key);
        $cachedRate = is_array($cached) ? ($cached['rate'] ?? null) : null;
        if ((is_int($cachedRate) || is_float($cachedRate)) && is_finite((float) $cachedRate) && $cachedRate > 0) {
            return (float) $cachedRate;
        }

        $rate = $this->rateProvider->getExchangeRate($fromCurrency, $toCurrency);
        if (!is_finite($rate) || $rate <= 0.0) {
            throw new UnexpectedValueException('A fonte de taxas devolveu uma taxa inválida.');
        }

        $this->cache->put($key, ['rate' => $rate], $this->rateTtlSeconds);

        return $rate;
    }
}
