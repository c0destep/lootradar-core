<?php

declare(strict_types=1);

use LootRadar\Cache\JsonCache;
use LootRadar\Cache\SqliteCache;
use LootRadar\Contracts\CacheInterface;
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

it('isola o cache por região, locale, moeda, score e composição de adapters', function () {
    $cache = new class implements CacheInterface {
        /** @var list<string> */
        public array $writtenKeys = [];

        public function get(string $key): ?array { return null; }
        public function has(string $key): bool { return false; }
        public function put(string $key, array $payload, ?int $ttlSeconds = null): void
        {
            $this->writtenKeys[] = $key;
        }
        public function forget(string $key): void {}
        public function flush(): void {}
    };
    $adapterFactory = static fn(): StoreAdapterInterface => new class implements StoreAdapterInterface {
        public function fetchDeals(): array { return []; }
        public function fetchFreeGames(): array
        {
            return [new GameDeal('Control', 'Epic', 60.0, 0.0, 'https://store.example.com/control', 100, true)];
        }
    };
    $contexts = [
        ['country' => 'US', 'locale' => 'en-US', 'currency' => 'native', 'minimum_score' => 60],
        ['country' => 'BR', 'locale' => 'en-US', 'currency' => 'native', 'minimum_score' => 60],
        ['country' => 'US', 'locale' => 'pt-BR', 'currency' => 'native', 'minimum_score' => 60],
        ['country' => 'US', 'locale' => 'en-US', 'currency' => 'BRL', 'minimum_score' => 60],
        ['country' => 'US', 'locale' => 'en-US', 'currency' => 'native', 'minimum_score' => 80],
    ];

    foreach ($contexts as $context) {
        $radar = new RadarService(cache: $cache, cacheContext: $context);
        $radar->registerAdapter($adapterFactory());
        $radar->getFreeGames();
    }

    $radarWithAnotherComposition = new RadarService(cache: $cache, cacheContext: $contexts[0]);
    $radarWithAnotherComposition->registerAdapter($adapterFactory());
    $radarWithAnotherComposition->registerAdapter($adapterFactory());
    $radarWithAnotherComposition->getFreeGames();

    expect($cache->writtenKeys)->toHaveCount(6)
        ->and(array_unique($cache->writtenKeys))->toHaveCount(6)
        ->and($cache->writtenKeys[0])->toStartWith(RadarService::CACHE_KEY_FREE_GAMES . ':');
});

it('não lê nem grava o cache quando o bypass está ativo', function () {
    $cache = new class implements CacheInterface {
        public int $reads = 0;
        public int $writes = 0;

        public function get(string $key): ?array { $this->reads++; return null; }
        public function has(string $key): bool { return false; }
        public function put(string $key, array $payload, ?int $ttlSeconds = null): void { $this->writes++; }
        public function forget(string $key): void {}
        public function flush(): void {}
    };
    $adapter = new class implements StoreAdapterInterface {
        public function fetchDeals(): array { return []; }
        public function fetchFreeGames(): array
        {
            return [new GameDeal('Control', 'Epic', 60.0, 0.0, 'https://store.example.com/control', 100, true)];
        }
    };
    $radar = new RadarService($cache);
    $radar->registerAdapter($adapter);

    expect($radar->getFreeGames(bypassCache: true))->toHaveCount(1)
        ->and($cache->reads)->toBe(0)
        ->and($cache->writes)->toBe(0);
});

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
