<?php

declare(strict_types=1);

namespace LootRadar\Contracts;

/**
 * Fonte configurável de taxas entre moedas ISO 4217.
 */
interface ExchangeRateProviderInterface
{
    /**
     * Retorna quantas unidades de $toCurrency equivalem a uma unidade de
     * $fromCurrency.
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency): float;
}
