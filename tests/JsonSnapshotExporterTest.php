<?php

declare(strict_types=1);

use LootRadar\Services\JsonSnapshotExporter;

it('exporta um documento JSON versionado e determinístico', function () {
    $json = new JsonSnapshotExporter()->export(
        freeGames: [[
            'title' => 'Grátis & ótimo',
            'currentPrice' => 0.0,
            'checkoutUrl' => 'https://store.example.com/free',
        ]],
        deals: [[
            'title' => 'Hades',
            'currentPrice' => 12.5,
            'checkoutUrl' => 'https://store.example.com/hades',
        ]],
        complete: ['freeGames' => true, 'deals' => false],
        context: [
            'country' => 'BR',
            'locale' => 'pt-BR',
            'currency' => 'BRL',
            'minimumScore' => 60,
        ],
        generatedAt: new DateTimeImmutable('2026-09-04T09:30:00-03:00'),
    );

    $snapshot = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    expect($snapshot['schemaVersion'])->toBe(1)
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
});
