<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use LootRadar\Adapters\ItadAdapter;

function itadFixture(string $name): string
{
    $contents = file_get_contents(__DIR__ . '/fixtures/' . $name);
    expect($contents)->not->toBeFalse();

    return $contents;
}

it('converte ofertas do ITAD e envia chave e filtros esperados', function () {
    $requests = [];
    $handler = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], itadFixture('itad-deals.json')),
    ]));
    $handler->push(Middleware::history($requests));
    $client = new Client(['handler' => $handler]);

    $deals = new ItadAdapter($client, 'test-api-key', 'BR', 5)->fetchDeals();

    expect($deals)->toHaveCount(2)
        ->and($deals[0]->title)->toBe('Hades')
        ->and($deals[0]->currency)->toBe('BRL')
        ->and($deals[0]->historicalLow)->toBe(14.99)
        ->and($deals[0]->isAtHistoricalLow())->toBeTrue()
        ->and($requests[0]['request']->getHeaderLine('ITAD-API-Key'))->toBe('test-api-key')
        ->and($requests[0]['request']->getUri()->getQuery())->toContain('country=BR');
});

it('converte histórico de preços do ITAD e descarta entradas inválidas', function () {
    $client = new Client(['handler' => HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], itadFixture('itad-history.json')),
    ]))]);
    $adapter = new ItadAdapter($client, 'test-api-key');

    $history = $adapter->fetchPriceHistory(
        '018d937f-012f-73f8-ab2c-898516969e6a',
        country: 'BR',
        since: new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    expect($history)->toHaveCount(1)
        ->and($history[0]->storeName)->toBe('Steam')
        ->and($history[0]->discountPercentage)->toBe(75);
});

it('exige uma chave de API do ambiente', function () {
    putenv('ITAD_API_KEY');

    expect(fn() => ItadAdapter::fromEnvironment(new Client()))->toThrow(LogicException::class);
});
