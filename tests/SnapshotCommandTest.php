<?php

declare(strict_types=1);

use LootRadar\Cache\SqliteCache;
use LootRadar\Cli\CliOptions;
use LootRadar\Cli\CliRadarFactoryInterface;
use LootRadar\Commands\SnapshotCommand;
use LootRadar\Contracts\StoreAdapterInterface;
use LootRadar\DTO\GameDeal;
use LootRadar\Services\RadarService;
use Symfony\Component\Console\Tester\CommandTester;

it('expõe jogos gratuitos e maiores promoções em JSON', function () {
    $freeAdapter = new class implements StoreAdapterInterface {
        public function fetchFreeGames(): array
        {
            return [new GameDeal(
                'Control',
                'Epic Games Store',
                99.0,
                0.0,
                'https://store.epicgames.com/p/control?utm_source=test',
                90,
                true,
                'BRL',
            )];
        }

        public function fetchDeals(): array
        {
            return [];
        }
    };
    $dealAdapter = new class implements StoreAdapterInterface {
        public function fetchFreeGames(): array
        {
            return [];
        }

        public function fetchDeals(): array
        {
            return [
                new GameDeal('Menor desconto', 'ITAD', 100.0, 50.0, 'https://isthereanydeal.com/game/one', null, false, 'BRL'),
                new GameDeal('Maior desconto', 'ITAD', 100.0, 10.0, 'https://isthereanydeal.com/game/two', null, false, 'BRL'),
            ];
        }
    };
    $freeRadar = new RadarService(new SqliteCache(':memory:'));
    $freeRadar->registerAdapter($freeAdapter);
    $dealRadar = new RadarService(new SqliteCache(':memory:'));
    $dealRadar->registerAdapter($dealAdapter);

    $factory = new class($freeRadar, $dealRadar) implements CliRadarFactoryInterface {
        public int $requestedLimit = 0;

        public function __construct(
            private readonly RadarService $freeRadar,
            private readonly RadarService $dealRadar,
        ) {
        }

        public function createFreeRadar(CliOptions $options): RadarService
        {
            return $this->freeRadar;
        }

        public function createDealRadar(CliOptions $options, int $limit): RadarService
        {
            $this->requestedLimit = $limit;

            return $this->dealRadar;
        }
    };

    $tester = new CommandTester(new SnapshotCommand($factory));
    $exitCode = $tester->execute(['--top' => '1'], ['capture_stderr_separately' => true]);
    $snapshot = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($factory->requestedLimit)->toBe(1)
        ->and($snapshot['producerVersion'])->toBe('0.4.1')
        ->and($snapshot['context']['currency'])->toBe('native')
        ->and($snapshot['data']['freeGames'][0]['checkoutUrl'])->toBe('https://store.epicgames.com/p/control')
        ->and($snapshot['data']['deals'])->toHaveCount(1)
        ->and($snapshot['data']['deals'][0]['title'])->toBe('Maior desconto')
        ->and($snapshot['complete'])->toBeTrue()
        ->and($snapshot['sources'])->toBe([
            'freeGames' => ['complete' => true],
            'deals' => ['complete' => true],
        ]);
});

it('rejeita limite inválido sem consultar as fontes', function () {
    $factory = new class implements CliRadarFactoryInterface {
        public function createFreeRadar(CliOptions $options): RadarService
        {
            throw new LogicException('não deveria criar radar');
        }

        public function createDealRadar(CliOptions $options, int $limit): RadarService
        {
            throw new LogicException('não deveria criar radar');
        }
    };
    $tester = new CommandTester(new SnapshotCommand($factory));
    $exitCode = $tester->execute(['--top' => '0'], ['capture_stderr_separately' => true]);

    expect($exitCode)->toBe(2)
        ->and($tester->getDisplay())->toBe('')
        ->and($tester->getErrorOutput())->toContain('--top deve estar entre 1 e 200');
});
