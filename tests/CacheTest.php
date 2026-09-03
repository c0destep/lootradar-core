<?php

declare(strict_types=1);

use LootRadar\Cache\JsonCache;
use LootRadar\Cache\SqliteCache;
use LootRadar\Contracts\StoreAdapterInterface;
use LootRadar\DTO\GameDeal;
use LootRadar\Services\RadarService;

dataset('cache implementations', [
    'json' => fn() => new JsonCache(sys_get_temp_dir() . '/lootradar-test-' . bin2hex(random_bytes(6))),
    'sqlite' => fn() => new SqliteCache(':memory:'),
]);

it('guarda, lê e remove payloads em qualquer cache', function (callable $factory) {
    $cache = $factory();
    $payload = [['title' => 'Hades', 'currentPrice' => 25]];

    $cache->put('deals', $payload, 60);
    expect($cache->has('deals'))->toBeTrue()
        ->and($cache->get('deals'))->toBe($payload);

    $cache->forget('deals');
    expect($cache->get('deals'))->toBeNull();
})->with('cache implementations');

it('limpa todas as entradas do cache', function (callable $factory) {
    $cache = $factory();
    $cache->put('one', ['value' => 1]);
    $cache->put('two', ['value' => 2]);

    $cache->flush();

    expect($cache->get('one'))->toBeNull()->and($cache->get('two'))->toBeNull();
})->with('cache implementations');

it('integra o RadarService com qualquer implementação de cache', function (callable $factory) {
    $cache = $factory();
    $adapter = new class implements StoreAdapterInterface {
        public int $calls = 0;

        public function fetchDeals(): array { return []; }

        public function fetchFreeGames(): array
        {
            $this->calls++;
            return [new GameDeal('Control', 'Epic', 60.0, 0.0, 'https://store.example.com/control', 100, true)];
        }
    };
    $radar = new RadarService($cache);
    $radar->registerAdapter($adapter);

    expect($radar->getFreeGames())->toHaveCount(1)
        ->and($radar->getFreeGames())->toHaveCount(1)
        ->and($adapter->calls)->toBe(1);
})->with('cache implementations');

it('não armazena uma coleta quando alguma fonte falha', function (callable $factory) {
    $cache = $factory();
    $adapter = new class implements StoreAdapterInterface {
        public int $calls = 0;

        public function fetchDeals(): array
        {
            return [];
        }

        public function fetchFreeGames(): array
        {
            $this->calls++;

            throw new RuntimeException('fonte indisponível');
        }
    };
    $radar = new RadarService($cache);
    $radar->registerAdapter($adapter);

    expect($radar->getFreeGames())->toBe([])
        ->and($radar->getFreeGames())->toBe([])
        ->and($adapter->calls)->toBe(2)
        ->and($cache->has(RadarService::CACHE_KEY_FREE_GAMES))->toBeFalse();
})->with('cache implementations');

it('não armazena uma coleta quando uma URL externa é rejeitada', function (callable $factory) {
    $cache = $factory();
    $adapter = new class implements StoreAdapterInterface {
        public int $calls = 0;

        public function fetchDeals(): array
        {
            return [];
        }

        public function fetchFreeGames(): array
        {
            $this->calls++;

            return [
                new GameDeal(
                    'Link inseguro',
                    'Fonte externa',
                    10.0,
                    0.0,
                    'javascript:alert(1)',
                    100,
                    true,
                ),
            ];
        }
    };
    $radar = new RadarService($cache);
    $radar->registerAdapter($adapter);

    expect($radar->getFreeGames())->toBe([])
        ->and($radar->getFailures())->toHaveCount(1)
        ->and($radar->getFreeGames())->toBe([])
        ->and($adapter->calls)->toBe(2)
        ->and($cache->has(RadarService::CACHE_KEY_FREE_GAMES))->toBeFalse();
})->with('cache implementations');
