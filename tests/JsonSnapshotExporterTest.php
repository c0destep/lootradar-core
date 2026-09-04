<?php

declare(strict_types=1);

use LootRadar\Services\JsonSnapshotExporter;
use Opis\JsonSchema\Validator;

it('exporta um documento JSON versionado e determinístico', function () {
    $json = new JsonSnapshotExporter()->export(
        freeGames: [[
            'title' => 'Grátis & ótimo',
            'storeName' => 'Epic Games Store',
            'originalPrice' => 99.0,
            'currentPrice' => 0.0,
            'checkoutUrl' => 'https://store.example.com/free',
            'approvalRating' => 90,
            'isFree' => true,
            'currency' => 'BRL',
            'expiresAt' => '2026-09-10T15:00:00Z',
            'historicalLow' => 0.0,
            'discountPercentage' => 100,
            'isAtHistoricalLow' => true,
        ]],
        deals: [[
            'title' => 'Hades',
            'storeName' => 'ITAD',
            'originalPrice' => 50.0,
            'currentPrice' => 12.5,
            'checkoutUrl' => 'https://store.example.com/hades',
            'approvalRating' => null,
            'isFree' => false,
            'currency' => 'BRL',
            'expiresAt' => null,
            'historicalLow' => 10.0,
            'discountPercentage' => 75,
            'isAtHistoricalLow' => false,
        ]],
        complete: ['freeGames' => true, 'deals' => false],
        context: [
            'country' => 'BR',
            'locale' => 'pt-BR',
            'currency' => 'BRL',
            'minimumScore' => 60,
        ],
        producerVersion: '0.4.0',
        generatedAt: new DateTimeImmutable('2026-09-04T09:30:00-03:00'),
    );

    $snapshot = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    expect($snapshot['schemaVersion'])->toBe(1)
        ->and($snapshot['producerVersion'])->toBe('0.4.0')
        ->and($snapshot['generatedAt'])->toBe('2026-09-04T12:30:00Z')
        ->and($snapshot['context'])->toBe([
            'country' => 'BR',
            'locale' => 'pt-BR',
            'currency' => 'BRL',
            'minimumScore' => 60,
        ])
        ->and($snapshot['data']['freeGames'][0]['title'])->toBe('Grátis & ótimo')
        ->and($snapshot['data']['deals'][0]['currentPrice'])->toBe(12.5)
        ->and($snapshot['complete'])->toBeFalse()
        ->and($snapshot['sources'])->toBe([
            'freeGames' => ['complete' => true],
            'deals' => ['complete' => false],
        ])
        ->and($json)->toContain('https://store.example.com/free')
        ->and($json)->toContain('0.0');

    $schema = json_decode(
        file_get_contents(__DIR__ . '/../resources/schema/lootradar-snapshot-v1.schema.json'),
        flags: JSON_THROW_ON_ERROR,
    );
    $document = json_decode($json, flags: JSON_THROW_ON_ERROR);

    expect((new Validator())->validate($document, $schema)->isValid())->toBeTrue()
        ->and($json . PHP_EOL)->toBe(file_get_contents(__DIR__ . '/fixtures/snapshot-v1.json'));
});
