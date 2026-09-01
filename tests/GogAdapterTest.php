<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use LootRadar\Adapters\GogAdapter;

function gogFixture(): string
{
    $contents = file_get_contents(__DIR__ . '/fixtures/gog-catalog.json');
    expect($contents)->not->toBeFalse();

    return $contents;
}

it('converte descontos do catálogo GOG e envia os filtros estáveis', function () {
    $requests = [];
    $handler = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], gogFixture()),
    ]));
    $handler->push(Middleware::history($requests));

    $deals = new GogAdapter(new Client(['handler' => $handler]), 'BR', 'pt-BR', 10)->fetchDeals();

    expect($deals)->toHaveCount(2)
        ->and($deals[0]->title)->toBe('The Witcher 3: Wild Hunt')
        ->and($deals[0]->currentPrice)->toBe(32.49)
        ->and($deals[0]->checkoutUrl)->toBe('https://www.gog.com/en/game/the_witcher_3_wild_hunt')
        ->and($deals[1]->isFree)->toBeTrue()
        ->and($requests[0]['request']->getUri()->getQuery())->toContain('country=BR')
        ->and($requests[0]['request']->getUri()->getQuery())->toContain('locale=pt-BR');
});

it('retorna apenas promoções gratuitas da GOG', function () {
    $client = new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], gogFixture()),
    ]))]);

    $deals = new GogAdapter($client)->fetchFreeGames();

    expect($deals)->toHaveCount(1)
        ->and($deals[0]->title)->toBe('GOG Giveaway')
        ->and($deals[0]->currency)->toBe('BRL');
});
