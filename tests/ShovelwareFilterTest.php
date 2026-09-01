<?php

declare(strict_types=1);

use LootRadar\DTO\GameDeal;
use LootRadar\Services\ShovelwareFilter;

it('remove avaliações abaixo do limiar e mantém ofertas sem nota por padrão', function () {
    $filter = new ShovelwareFilter(minimumRating: 60);
    $good = new GameDeal('Good', 'Store', 10.0, 5.0, 'https://example.com/good', 60);
    $bad = new GameDeal('Bad', 'Store', 10.0, 5.0, 'https://example.com/bad', 59);
    $unrated = new GameDeal('Unrated', 'Store', 10.0, 5.0, 'https://example.com/unrated');

    expect($filter->filter([$good, $bad, $unrated]))->toHaveCount(2)
        ->and($filter->accepts($bad))->toBeFalse()
        ->and($filter->accepts($unrated))->toBeTrue();
});
