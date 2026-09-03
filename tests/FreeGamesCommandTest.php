<?php

declare(strict_types=1);

use LootRadar\Cache\SqliteCache;
use LootRadar\Commands\FreeGamesCommand;
use LootRadar\Contracts\StoreAdapterInterface;
use LootRadar\DTO\GameDeal;
use LootRadar\Services\RadarService;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('trata dados externos como texto ao renderizar a CLI', function () {
    $adapter = new class implements StoreAdapterInterface {
        public function fetchFreeGames(): array
        {
            return [new GameDeal(
                title: '<b>Oferta externa</b>',
                storeName: "Steam'><div>injetado</div>",
                originalPrice: 10.0,
                currentPrice: 0.0,
                checkoutUrl: 'https://store.steampowered.com/app/10',
                isFree: true,
            )];
        }

        public function fetchDeals(): array
        {
            return [];
        }
    };

    $radar = new RadarService(new SqliteCache(':memory:'));
    $radar->registerAdapter($adapter);

    $output = new BufferedOutput();
    $exitCode = new FreeGamesCommand($radar)->run(new ArrayInput([]), $output);
    $rendered = $output->fetch();

    expect($exitCode)->toBe(0)
        ->and($rendered)->toContain('<b>Oferta externa</b>')
        ->and($rendered)->toContain("Steam'><div>injetado</div>");
});
