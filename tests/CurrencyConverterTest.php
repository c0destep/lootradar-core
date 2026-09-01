<?php

declare(strict_types=1);

use LootRadar\Cache\SqliteCache;
use LootRadar\Contracts\ExchangeRateProviderInterface;
use LootRadar\Contracts\StoreAdapterInterface;
use LootRadar\DTO\GameDeal;
use LootRadar\DTO\Money;
use LootRadar\Services\CurrencyConverter;
use LootRadar\Services\RadarService;

it('converte valores e reutiliza a taxa cacheada', function () {
    $provider = new class implements ExchangeRateProviderInterface {
        public int $calls = 0;

        public function getExchangeRate(string $fromCurrency, string $toCurrency): float
        {
            $this->calls++;

            return 5.0;
        }
    };
    $converter = new CurrencyConverter($provider, new SqliteCache(':memory:'));

    expect($converter->convert(new Money(10.0, 'USD'), 'BRL')->amount)->toBe(50.0)
        ->and($converter->convert(new Money(2.0, 'USD'), 'BRL')->amount)->toBe(10.0)
        ->and($provider->calls)->toBe(1);
});

it('aplica conversão no pipeline quando a moeda alvo é explícita', function () {
    $provider = new class implements ExchangeRateProviderInterface {
        public function getExchangeRate(string $fromCurrency, string $toCurrency): float
        {
            return 5.0;
        }
    };
    $adapter = new class implements StoreAdapterInterface {
        public function fetchDeals(): array
        {
            return [new GameDeal(
                title: 'Hades',
                storeName: 'Steam',
                originalPrice: 20.0,
                currentPrice: 10.0,
                checkoutUrl: 'https://store.example.com/hades',
                currency: 'USD',
                historicalLow: 8.0,
            )];
        }

        public function fetchFreeGames(): array
        {
            return [];
        }
    };
    $radar = new RadarService(
        cache: new SqliteCache(':memory:'),
        currencyConverter: new CurrencyConverter($provider, new SqliteCache(':memory:')),
        targetCurrency: 'BRL',
    );
    $radar->registerAdapter($adapter);

    $deal = $radar->getDeals()[0];

    expect($deal['currency'])->toBe('BRL')
        ->and($deal['originalPrice'])->toBe(100.0)
        ->and($deal['currentPrice'])->toBe(50.0)
        ->and($deal['historicalLow'])->toBe(40.0);
});
