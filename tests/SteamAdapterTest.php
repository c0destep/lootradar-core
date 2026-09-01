<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use LootRadar\Adapters\SteamAdapter;

function steamFixture(): string
{
    $contents = file_get_contents(__DIR__ . '/fixtures/steam-featured-categories.json');
    expect($contents)->not->toBeFalse();

    return $contents;
}

it('converte promoções da Steam e regionaliza a consulta', function () {
    $requests = [];
    $handler = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], steamFixture()),
    ]));
    $handler->push(Middleware::history($requests));

    $deals = new SteamAdapter(new Client(['handler' => $handler]), 'BR', 'portuguese')->fetchDeals();

    expect($deals)->toHaveCount(2)
        ->and($deals[0]->title)->toBe('Hades')
        ->and($deals[0]->currentPrice)->toBe(9.99)
        ->and($deals[0]->checkoutUrl)->toBe('https://store.steampowered.com/app/1145360')
        ->and($deals[1]->isFree)->toBeTrue()
        ->and($requests[0]['request']->getUri()->getQuery())->toContain('cc=BR')
        ->and($requests[0]['request']->getUri()->getQuery())->toContain('l=portuguese');
});

it('retorna apenas promoções gratuitas da Steam', function () {
    $client = new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], steamFixture()),
    ]))]);

    $deals = new SteamAdapter($client)->fetchFreeGames();

    expect($deals)->toHaveCount(1)
        ->and($deals[0]->title)->toBe('Darkest Dungeon II')
        ->and($deals[0]->getDiscountPercentage())->toBe(100);
});
