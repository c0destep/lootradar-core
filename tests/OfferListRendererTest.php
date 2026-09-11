<?php

declare(strict_types=1);

use LootRadar\Cli\CliOptions;
use LootRadar\Cli\Presentation\OfferListRenderer;
use LootRadar\Services\ThemeManager;
use Symfony\Component\Console\Output\BufferedOutput;

it('apresenta promoções como lista completa sem ANSI quando a saída não é decorada', function () {
    $output = new BufferedOutput(decorated: false);
    $renderer = new OfferListRenderer($output, ThemeManager::getTheme('default'));

    $renderer->renderDeals([
        [
            'title' => 'Hades',
            'storeName' => 'Steam',
            'originalPrice' => 59.99,
            'currentPrice' => 14.99,
            'checkoutUrl' => 'https://store.steampowered.com/app/1145360',
            'approvalRating' => 98,
            'currency' => 'BRL',
            'expiresAt' => '2026-09-15T18:00:00Z',
            'historicalLow' => 14.99,
            'discountPercentage' => 75,
            'isAtHistoricalLow' => true,
        ],
        [
            'title' => 'Celeste',
            'storeName' => 'GOG',
            'originalPrice' => 36.99,
            'currentPrice' => 18.49,
            'checkoutUrl' => 'https://www.gog.com/game/celeste',
            'approvalRating' => null,
            'currency' => 'BRL',
            'expiresAt' => null,
            'historicalLow' => 9.24,
            'discountPercentage' => 50,
            'isAtHistoricalLow' => false,
        ],
    ], [], new CliOptions(country: 'BR', locale: 'pt-BR', currency: 'BRL'));

    $rendered = $output->fetch();

    expect($rendered)->toContain('LOOTRADAR — MAIORES DESCONTOS')
        ->and($rendered)->toContain('2 OFERTAS')
        ->and($rendered)->toContain('2 lojas · região BR · score mínimo 60 · moeda BRL')
        ->and($rendered)->toContain('75% OFF')
        ->and($rendered)->toContain('14,99 BRL')
        ->and($rendered)->toContain('(antes 59,99 BRL)')
        ->and($rendered)->toContain('· Steam')
        ->and($rendered)->toContain('MENOR PREÇO HISTÓRICO')
        ->and($rendered)->toContain('Avaliação: 98/100 · Até: 15/09/2026 18:00 UTC')
        ->and($rendered)->toContain('Menor preço histórico: 9,24 BRL')
        ->and($rendered)->toContain('Comprar:')
        ->and($rendered)->toContain('https://www.gog.com/game/celeste')
        ->and($rendered)->not->toContain("\e[")
        ->and($rendered)->not->toMatch('/[ \t]+$/m');
});

it('distingue consulta parcial de uma lista vazia e preserva texto externo escapado', function () {
    $output = new BufferedOutput(decorated: false);
    $renderer = new OfferListRenderer($output, ThemeManager::getTheme('default'));

    $renderer->renderFreeGames([
        [
            'title' => '<b>Control</b>',
            'storeName' => 'Epic Games',
            'originalPrice' => 79.99,
            'checkoutUrl' => 'https://store.epicgames.com/p/control',
            'approvalRating' => 85,
            'currency' => 'BRL',
            'expiresAt' => null,
        ],
    ], ['Steam temporariamente indisponível'], new CliOptions(country: 'BR', locale: 'pt-BR'));

    $rendered = $output->fetch();
    $warningPosition = strpos($rendered, 'Resultado parcial');
    $itemPosition = strpos($rendered, '1. <b>Control</b>');

    expect($rendered)->toContain('LOOTRADAR — JOGOS GRATUITOS')
        ->and($rendered)->toContain('1 JOGO')
        ->and($rendered)->toContain('Epic Games · Preço normal: 79,99 BRL · Avaliação: 85/100')
        ->and($rendered)->toContain('Resultado parcial — Fontes indisponíveis nesta consulta:')
        ->and($rendered)->toContain('Steam temporariamente indisponível')
        ->and($rendered)->toContain('<b>Control</b>')
        ->and($warningPosition)->toBeInt()
        ->and($itemPosition)->toBeInt()
        ->and($warningPosition)->toBeLessThan($itemPosition);
});

it('explica quando nenhuma oferta pode ser confirmada por falha de fonte', function () {
    $output = new BufferedOutput(decorated: false);
    $renderer = new OfferListRenderer($output, ThemeManager::getTheme('default'));

    $renderer->renderDeals([], ['fonte indisponível'], new CliOptions());

    expect($output->fetch())->toContain('Não foi possível confirmar promoções nesta consulta.')
        ->not->toContain('Nenhuma promoção encontrada no momento.');
});
