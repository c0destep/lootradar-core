<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use LootRadar\Cache\SqliteCache;
use LootRadar\Cli\ApplicationFactory;

function cliFixture(string $name): string
{
    $contents = file_get_contents(__DIR__ . '/fixtures/' . $name);
    expect($contents)->not->toBeFalse();

    return $contents;
}

it('expõe a versão pública e os comandos disponíveis', function () {
    $application = ApplicationFactory::create(
        new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
        new SqliteCache(':memory:'),
    );

    expect($application->getVersion())->toBe('0.2.0')
        ->and($application->has('free'))->toBeTrue()
        ->and($application->has('deal'))->toBeFalse();
});

it('compõe todas as fontes públicas de jogos gratuitos', function () {
    $requests = [];
    $handler = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('epic-free-games.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('steam-featured-categories.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('gog-catalog.json')),
    ]));
    $handler->push(Middleware::history($requests));

    $radar = ApplicationFactory::createRadar(
        new Client(['handler' => $handler]),
        new SqliteCache(':memory:'),
    );
    $games = $radar->getFreeGames();

    expect(array_column($games, 'title'))->toBe([
        'Control',
        'Darkest Dungeon II',
        'GOG Giveaway',
    ])->and($requests)->toHaveCount(3)
        ->and($requests[0]['request']->getUri()->getHost())->toBe('store-site-backend-static.ak.epicgames.com')
        ->and($requests[1]['request']->getUri()->getHost())->toBe('store.steampowered.com')
        ->and($requests[2]['request']->getUri()->getHost())->toBe('catalog.gog.com');
});
