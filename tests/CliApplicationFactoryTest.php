<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use LootRadar\Cache\SqliteCache;
use LootRadar\Cli\ApplicationFactory;
use LootRadar\Contracts\ExchangeRateProviderInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;

function cliFixture(string $name): string
{
    $contents = file_get_contents(__DIR__ . '/fixtures/' . $name);
    expect($contents)->not->toBeFalse();

    return $contents;
}

it('expõe a versão pública e os comandos disponíveis', function () {
    $application = ApplicationFactory::create(
        new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
        new SqliteCache(':memory:'),
    );

    expect($application->getVersion())->toBe('0.4.1')
        ->and($application->has('free'))->toBeTrue()
        ->and($application->has('deal'))->toBeTrue()
        ->and($application->has('snapshot'))->toBeTrue()
        ->and($application->getDefinition()->hasOption('currency'))->toBeTrue()
        ->and($application->getDefinition()->hasOption('country'))->toBeTrue()
        ->and($application->getDefinition()->hasOption('locale'))->toBeTrue()
        ->and($application->getDefinition()->hasOption('min-score'))->toBeTrue()
        ->and($application->getDefinition()->hasOption('no-cache'))->toBeTrue();
});

it('apresenta recursos, temas e exemplos na ajuda geral', function (array $input) {
    $application = ApplicationFactory::create(
        new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
        new SqliteCache(':memory:'),
    );
    $application->setAutoExit(false);
    $tester = new ApplicationTester($application);

    expect($tester->run($input, ['decorated' => false]))->toBe(0)
        ->and($tester->getDisplay())->toContain('Recursos principais:')
        ->and($tester->getDisplay())->toContain('Epic Games, na Steam e na GOG')
        ->and($tester->getDisplay())->toContain('IsThereAnyDeal')
        ->and($tester->getDisplay())->toContain('snapshot')
        ->and($tester->getDisplay())->toContain('Temas disponíveis: cyberpunk, default, dracula.')
        ->and($tester->getDisplay())->toContain('--theme=<nome>')
        ->and($tester->getDisplay())->toContain('--currency')
        ->and($tester->getDisplay())->toContain('não altera a região comercial')
        ->and($tester->getDisplay())->toContain('ofertas sem avaliação são mantidas')
        ->and($tester->getDisplay())->toContain('--no-cache')
        ->and($tester->getDisplay())->toContain('Não lê nem grava o cache')
        ->and($tester->getDisplay())->toContain('ITAD_API_KEY');
})->with([
    'sem comando' => [[]],
    'comando help' => [['command' => 'help']],
    'opção help' => [['--help' => true]],
]);

it('detalha fontes, requisitos e exemplos na ajuda de cada comando', function (array $input, array $expected) {
    $application = ApplicationFactory::create(
        new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
        new SqliteCache(':memory:'),
    );
    $application->setAutoExit(false);
    $tester = new ApplicationTester($application);

    expect($tester->run($input, ['decorated' => false]))->toBe(0);

    foreach ($expected as $text) {
        expect($tester->getDisplay())->toContain($text);
    }
})->with([
    'help free' => [
        ['command' => 'help', 'command_name' => 'free'],
        ['Epic Games, na Steam e na GOG', '--theme', '--country', '--locale', '--no-cache'],
    ],
    'free --help' => [
        ['command' => 'free', '--help' => true],
        ['Epic Games, na Steam e na GOG', '--theme', '--country', '--locale', '--no-cache'],
    ],
    'help deal' => [
        ['command' => 'help', 'command_name' => 'deal'],
        ['Steam', 'GOG', 'ITAD_API_KEY', '--top', '--theme', '--currency'],
    ],
    'deal --help' => [
        ['command' => 'deal', '--help' => true],
        ['Steam', 'GOG', 'ITAD_API_KEY', '--top', '--theme', '--currency'],
    ],
    'help snapshot' => [
        ['command' => 'help', 'command_name' => 'snapshot'],
        ['JSON versionado', 'ITAD_API_KEY', '--top', '--currency', '--no-cache'],
    ],
    'snapshot --help' => [
        ['command' => 'snapshot', '--help' => true],
        ['JSON versionado', 'ITAD_API_KEY', '--top', '--currency', '--no-cache'],
    ],
]);

it('compõe todas as fontes públicas de jogos gratuitos', function () {
    $requests = [];
    $handler = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('epic-free-games.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('steam-featured-categories.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('gog-catalog.json')),
    ]));
    $handler->push(Middleware::history($requests));

    $radar = ApplicationFactory::createRadar(
        new Client(['handler' => $handler]),
        new SqliteCache(':memory:'),
    );
    $games = $radar->getFreeGames();

    expect(array_column($games, 'title'))->toBe([
        'Control',
        'Darkest Dungeon II',
        'GOG Giveaway',
    ])->and($requests)->toHaveCount(3)
        ->and($requests[0]['request']->getUri()->getHost())->toBe('store-site-backend-static.ak.epicgames.com')
        ->and($requests[1]['request']->getUri()->getHost())->toBe('store.steampowered.com')
        ->and($requests[2]['request']->getUri()->getHost())->toBe('catalog.gog.com');
});

it('aplica região e locale globais ao comando free em todos os temas', function (string $theme) {
    $requests = [];
    $handler = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('epic-free-games.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('steam-featured-categories.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('gog-catalog.json')),
    ]));
    $handler->push(Middleware::history($requests));
    $application = ApplicationFactory::create(
        new Client(['handler' => $handler]),
        new SqliteCache(':memory:'),
    );

    $tester = new CommandTester($application->find('free'));
    $exitCode = $tester->execute([
        '--country' => 'br',
        '--locale' => 'pt_br',
        '--no-cache' => true,
        '--theme' => $theme,
    ]);

    expect($exitCode)->toBe(0)
        ->and($requests)->toHaveCount(3)
        ->and($requests[1]['request']->getUri()->getQuery())->toContain('cc=BR')
        ->and($requests[1]['request']->getUri()->getQuery())->toContain('l=brazilian')
        ->and($requests[2]['request']->getUri()->getQuery())->toContain('country=BR')
        ->and($requests[2]['request']->getUri()->getQuery())->toContain('locale=pt-BR');
})->with(['default', 'cyberpunk', 'dracula']);

it('renderiza descontos da Steam, da GOG e do ITAD em todos os temas', function (string $theme) {
    $requests = [];
    $handler = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('steam-featured-categories.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('gog-catalog.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('itad-deals.json')),
    ]));
    $handler->push(Middleware::history($requests));
    $application = ApplicationFactory::create(
        new Client(['handler' => $handler]),
        new SqliteCache(':memory:'),
        itadApiKey: 'cli-test-api-key',
    );

    $tester = new CommandTester($application->find('deal'));
    $exitCode = $tester->execute([
        '--top' => '5',
        '--country' => 'BR',
        '--locale' => 'pt-BR',
        '--theme' => $theme,
    ]);
    $display = $tester->getDisplay();

    expect($exitCode)->toBe(0)
        ->and($display)->toContain('LOOTRADAR — MAIORES DESCONTOS')
        ->and($display)->toContain('Hades')
        ->and($display)->toContain('The Witcher 3: Wild Hunt')
        ->and($display)->toContain('Steam')
        ->and($display)->toContain('GOG')
        ->and($display)->toContain('75%')
        ->and($display)->toContain('MENOR PREÇO')
        ->and($requests)->toHaveCount(3)
        ->and($requests[0]['request']->getUri()->getQuery())->toContain('cc=BR')
        ->and($requests[0]['request']->getUri()->getQuery())->toContain('l=brazilian')
        ->and($requests[1]['request']->getUri()->getQuery())->toContain('country=BR')
        ->and($requests[1]['request']->getUri()->getQuery())->toContain('locale=pt-BR')
        ->and($requests[1]['request']->getUri()->getQuery())->toContain('limit=5')
        ->and($requests[2]['request']->getUri()->getQuery())->toContain('country=BR')
        ->and($requests[2]['request']->getUri()->getQuery())->toContain('limit=5');
})->with(['default', 'cyberpunk', 'dracula']);

it('converte a moeda solicitada pelo comando deal', function () {
    $provider = new class implements ExchangeRateProviderInterface {
        public function getExchangeRate(string $fromCurrency, string $toCurrency): float
        {
            expect($fromCurrency)->toBe('BRL')->and($toCurrency)->toBe('USD');

            return 0.2;
        }
    };
    $application = ApplicationFactory::create(
        new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], cliFixture('steam-featured-categories.json')),
            new Response(200, ['Content-Type' => 'application/json'], cliFixture('gog-catalog.json')),
            new Response(200, ['Content-Type' => 'application/json'], cliFixture('itad-deals.json')),
        ]))]),
        new SqliteCache(':memory:'),
        itadApiKey: 'cli-test-api-key',
        exchangeRateProvider: $provider,
    );

    $tester = new CommandTester($application->find('deal'));
    $exitCode = $tester->execute([
        '--top' => '5',
        '--country' => 'BR',
        '--currency' => 'usd',
    ]);

    expect($exitCode)->toBe(0)
        ->and($tester->getDisplay())->toContain('3,00 USD');
});

it('não reutiliza o cache de promoções entre limites diferentes', function () {
    $requests = [];
    $handler = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('steam-featured-categories.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('gog-catalog.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('itad-deals.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('steam-featured-categories.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('gog-catalog.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('itad-deals.json')),
    ]));
    $handler->push(Middleware::history($requests));
    $application = ApplicationFactory::create(
        new Client(['handler' => $handler]),
        new SqliteCache(':memory:'),
        itadApiKey: 'cli-test-api-key',
    );
    $tester = new CommandTester($application->find('deal'));

    expect($tester->execute(['--top' => '1']))->toBe(0)
        ->and($tester->execute(['--top' => '2']))->toBe(0)
        ->and($requests)->toHaveCount(6)
        ->and($requests[1]['request']->getUri()->getQuery())->toContain('limit=1')
        ->and($requests[2]['request']->getUri()->getQuery())->toContain('limit=1')
        ->and($requests[4]['request']->getUri()->getQuery())->toContain('limit=2')
        ->and($requests[5]['request']->getUri()->getQuery())->toContain('limit=2');
});

it('consulta promoções da Steam e da GOG sem exigir chave do ITAD', function () {
    $requests = [];
    $handler = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('steam-featured-categories.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('gog-catalog.json')),
    ]));
    $handler->push(Middleware::history($requests));
    $application = ApplicationFactory::create(
        new Client(['handler' => $handler]),
        new SqliteCache(':memory:'),
        itadApiKey: '',
    );

    $tester = new CommandTester($application->find('deal'));

    expect($tester->execute(['--top' => '200']))->toBe(0)
        ->and($tester->getDisplay())->toContain('Hades')
        ->and($tester->getDisplay())->toContain('The Witcher 3: Wild Hunt')
        ->and($requests)->toHaveCount(2)
        ->and($requests[0]['request']->getUri()->getHost())->toBe('store.steampowered.com')
        ->and($requests[1]['request']->getUri()->getHost())->toBe('catalog.gog.com')
        ->and($requests[1]['request']->getUri()->getQuery())->toContain('limit=100');
});

it('gera snapshot com promoções da Steam e da GOG sem chave do ITAD', function () {
    $requests = [];
    $handler = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('epic-free-games.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('steam-featured-categories.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('gog-catalog.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('steam-featured-categories.json')),
        new Response(200, ['Content-Type' => 'application/json'], cliFixture('gog-catalog.json')),
    ]));
    $handler->push(Middleware::history($requests));
    $application = ApplicationFactory::create(
        new Client(['handler' => $handler]),
        new SqliteCache(':memory:'),
        itadApiKey: '',
    );

    $tester = new CommandTester($application->find('snapshot'));
    $exitCode = $tester->execute(['--top' => '5'], ['capture_stderr_separately' => true]);
    $snapshot = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and(array_column($snapshot['data']['freeGames'], 'title'))->toBe([
            'Control',
            'Darkest Dungeon II',
            'GOG Giveaway',
        ])
        ->and(array_column($snapshot['data']['deals'], 'title'))->toContain('Hades')
        ->and(array_column($snapshot['data']['deals'], 'title'))->toContain('The Witcher 3: Wild Hunt')
        ->and($snapshot['complete'])->toBeTrue()
        ->and($requests)->toHaveCount(5);
});

it('rejeita limites e opções globais inválidos', function (array $input, string $message) {
    $application = ApplicationFactory::create(
        new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
        new SqliteCache(':memory:'),
        itadApiKey: 'cli-test-api-key',
    );
    $tester = new CommandTester($application->find('deal'));

    expect($tester->execute($input))->toBe(2)
        ->and($tester->getDisplay())->toContain($message);
})->with([
    'top zero' => [['--top' => '0'], '--top deve estar entre 1 e 200'],
    'score maior que cem' => [['--min-score' => '101'], '--min-score deve estar entre 0 e 100'],
    'locale inválido' => [['--locale' => 'portugues'], '--locale deve usar o formato'],
]);
