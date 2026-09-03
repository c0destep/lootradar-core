<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use LootRadar\Services\FrankfurterExchangeRateProvider;

it('obtém e valida uma taxa de câmbio da fixture offline', function () {
    $fixture = file_get_contents(__DIR__ . '/fixtures/frankfurter-rate.json');
    expect($fixture)->not->toBeFalse();

    $requests = [];
    $handler = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], $fixture),
    ]));
    $handler->push(Middleware::history($requests));
    $provider = new FrankfurterExchangeRateProvider(new Client(['handler' => $handler]));

    expect($provider->getExchangeRate('usd', 'brl'))->toBe(5.42)
        ->and((string) $requests[0]['request']->getUri())->toBe('https://api.frankfurter.dev/v2/rate/USD/BRL')
        ->and($requests[0]['request']->getHeaderLine('Accept'))->toBe('application/json');
});

it('rejeita respostas sem taxa positiva', function (string $payload) {
    $provider = new FrankfurterExchangeRateProvider(new Client([
        'handler' => HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $payload),
        ])),
    ]));

    expect(fn() => $provider->getExchangeRate('USD', 'BRL'))
        ->toThrow(UnexpectedValueException::class);
})->with(['{}', '{"rate":0}', '{"rate":"5.42"}']);
