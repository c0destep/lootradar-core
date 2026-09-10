<?php

declare(strict_types=1);

use LootRadar\Cache\SqliteCache;
use LootRadar\Cli\CliOptions;
use LootRadar\Cli\CliRadarFactoryInterface;
use LootRadar\Commands\DealCommand;
use LootRadar\Contracts\StoreAdapterInterface;
use LootRadar\DTO\GameDeal;
use LootRadar\Services\RadarService;
use Symfony\Component\Console\Tester\CommandTester;

it('respeita valores de top maiores que o padrão de dez', function () {
    $adapter = new class implements StoreAdapterInterface {
        public function fetchFreeGames(): array
        {
            return [];
        }

        public function fetchDeals(): array
        {
            $deals = [];
            foreach (range(1, 15) as $position) {
                $deals[] = new GameDeal(
                    title: "Jogo {$position}",
                    storeName: 'Loja de teste',
                    originalPrice: 100.0,
                    currentPrice: (float) $position,
                    checkoutUrl: "https://store.example.com/game/{$position}",
                    currency: 'BRL',
                );
            }

            return $deals;
        }
    };
    $radar = new RadarService(new SqliteCache(':memory:'));
    $radar->registerAdapter($adapter);
    $factory = new class($radar) implements CliRadarFactoryInterface {
        public int $requestedLimit = 0;

        public function __construct(private readonly RadarService $radar)
        {
        }

        public function createFreeRadar(CliOptions $options): RadarService
        {
            return $this->radar;
        }

        public function createDealRadar(CliOptions $options, int $limit): RadarService
        {
            $this->requestedLimit = $limit;

            return $this->radar;
        }
    };
    $tester = new CommandTester(new DealCommand($factory));

    $exitCode = $tester->execute(['--top' => '12']);

    expect($exitCode)->toBe(0)
        ->and($factory->requestedLimit)->toBe(12)
        ->and($tester->getDisplay())->toContain('Jogo 12')
        ->and($tester->getDisplay())->not->toContain('Jogo 13');
});

it('distribui o top entre todas as fontes disponíveis', function () {
    $adapter = static fn(string $source, float $firstPrice): StoreAdapterInterface => new class($source, $firstPrice) implements StoreAdapterInterface {
        public function __construct(
            private readonly string $source,
            private readonly float $firstPrice,
        ) {
        }

        public function fetchFreeGames(): array
        {
            return [];
        }

        public function fetchDeals(): array
        {
            $deals = [];
            foreach (range(0, 4) as $position) {
                $deals[] = new GameDeal(
                    title: "{$this->source} {$position}",
                    storeName: $this->source,
                    originalPrice: 100.0,
                    currentPrice: $this->firstPrice + $position,
                    checkoutUrl: "https://store.example.com/{$this->source}/{$position}",
                    currency: 'BRL',
                );
            }

            return $deals;
        }
    };
    $radar = new RadarService(new SqliteCache(':memory:'));
    $radar->registerAdapter($adapter('Steam', 20.0));
    $radar->registerAdapter($adapter('GOG', 10.0));
    $radar->registerAdapter($adapter('ITAD', 1.0));

    $deals = $radar->getTopDeals(6, bypassCache: true);

    expect($deals)->toHaveCount(6)
        ->and(array_count_values(array_column($deals, 'storeName')))->toBe([
            'ITAD' => 4,
            'GOG' => 1,
            'Steam' => 1,
        ]);
});
