<?php

declare(strict_types=1);

use LootRadar\DTO\GameDeal;

it('calcula 100% de desconto para jogos gratuitos', function () {
    $deal = new GameDeal(
        title: 'Control',
        storeName: 'Epic Games Store',
        originalPrice: 99.99,
        currentPrice: 0.0,
        checkoutUrl: 'https://store.epicgames.com/p/control',
        approvalRating: 95,
        isFree: true,
    );

    expect($deal->getDiscountPercentage())->toBe(100);
});

it('calcula desconto parcial corretamente', function () {
    $deal = new GameDeal(
        title: 'Hades',
        storeName: 'Epic Games Store',
        originalPrice: 100.0,
        currentPrice: 25.0,
        checkoutUrl: 'https://store.epicgames.com/p/hades',
        approvalRating: 98,
    );

    expect($deal->getDiscountPercentage())->toBe(75);
});

it('retorna 100% quando o preço original é zero', function () {
    $deal = new GameDeal(
        title: 'Free Demo',
        storeName: 'Epic Games Store',
        originalPrice: 0.0,
        currentPrice: 0.0,
        checkoutUrl: 'https://store.epicgames.com/p/free-demo',
        approvalRating: 80,
        isFree: true,
    );

    expect($deal->getDiscountPercentage())->toBe(100);
});
