<?php

declare(strict_types=1);

use LootRadar\DTO\Money;
use LootRadar\DTO\PriceHistory;
use LootRadar\Services\ThemeManager;

it('normaliza e serializa valores monetários', function () {
    $money = new Money(19.9, 'brl');

    expect($money->currency)->toBe('BRL')
        ->and($money->toArray())->toBe(['amount' => 19.9, 'currency' => 'BRL']);
});

it('rejeita valores monetários inválidos', function () {
    expect(fn() => new Money(-1.0, 'BRL'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => new Money(1.0, 'invalid'))->toThrow(InvalidArgumentException::class);
});

it('representa um ponto de histórico de preço', function () {
    $history = new PriceHistory(
        gameId: '018d937f-012f-73f5-834e-c97c587fd7e3',
        recordedAt: new DateTimeImmutable('2026-09-01T12:00:00+00:00'),
        storeName: 'Steam',
        price: new Money(9.99, 'USD'),
        regularPrice: new Money(39.99, 'USD'),
        discountPercentage: 75,
    );

    expect($history->toArray()['discountPercentage'])->toBe(75)
        ->and($history->toArray()['price']['currency'])->toBe('USD');
});

it('carrega o tema como DTO', function () {
    $theme = ThemeManager::getTheme('dracula');

    expect($theme->name)->toBe('dracula')
        ->and($theme->styles['badge'])->toContain('bg-pink-400');
});
