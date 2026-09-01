<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use LootRadar\Adapters\EpicGamesAdapter;

it('verifica a saúde da API pelo contrato ClientInterface', function () {
    $client = new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200),
    ]))]);

    expect(new EpicGamesAdapter($client)->verifyApiHealth())->toBeTrue();
});

it('converte somente jogos gratuitos da fixture da Epic', function () {
    $fixture = file_get_contents(__DIR__ . '/fixtures/epic-free-games.json');
    expect($fixture)->not->toBeFalse();

    $client = new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], $fixture),
    ]))]);

    $games = new EpicGamesAdapter($client)->fetchFreeGames();

    expect($games)->toHaveCount(1)
        ->and($games[0]->title)->toBe('Control')
        ->and($games[0]->originalPrice)->toBe(59.99)
        ->and($games[0]->checkoutUrl)->toBe('https://store.epicgames.com/p/control');
});

it('converte descontos da Epic sem confundir preço cheio com promoção', function () {
    $client = new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], file_get_contents(__DIR__ . '/fixtures/epic-free-games.json')),
    ]))]);

    $deals = new EpicGamesAdapter($client)->fetchDeals();

    expect($deals)->toHaveCount(2)
        ->and($deals[0]->isFree)->toBeTrue()
        ->and($deals[1]->title)->toBe('Paid Game')
        ->and($deals[1]->getDiscountPercentage())->toBe(50)
        ->and($deals[1]->currency)->toBe('BRL');
});
