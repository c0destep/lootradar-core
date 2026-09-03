<?php

declare(strict_types=1);

namespace LootRadar\Cli;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use LootRadar\Cache\JsonCache;
use LootRadar\Commands\DealCommand;
use LootRadar\Commands\FreeGamesCommand;
use LootRadar\Contracts\CacheInterface;
use LootRadar\Contracts\ExchangeRateProviderInterface;
use LootRadar\Services\RadarService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

/**
 * Ponto de composição da CLI com os recursos públicos disponíveis no Core.
 */
final class ApplicationFactory
{
    public const string VERSION = '0.2.0';

    public static function create(
        ?ClientInterface $httpClient = null,
        ?CacheInterface $cache = null,
        ?string $itadApiKey = null,
        ?ExchangeRateProviderInterface $exchangeRateProvider = null,
    ): Application {
        $client = $httpClient ?? new Client([
            'connect_timeout' => 5.0,
            'timeout' => 15.0,
            'headers' => ['User-Agent' => 'LootRadar/' . self::VERSION],
        ]);
        $cache ??= new JsonCache(
            sys_get_temp_dir() . '/lootradar/' . self::VERSION,
        );
        if ($itadApiKey === null) {
            $environmentApiKey = getenv('ITAD_API_KEY');
            $itadApiKey = is_string($environmentApiKey) ? $environmentApiKey : null;
        }

        $application = new Application('LootRadar', self::VERSION);
        self::addGlobalOptions($application);

        $radarFactory = new CliRadarFactory($client, $cache, $itadApiKey, $exchangeRateProvider);
        $application->addCommand(new FreeGamesCommand($radarFactory));
        $application->addCommand(new DealCommand($radarFactory));

        return $application;
    }

    public static function createRadar(
        ClientInterface $httpClient,
        CacheInterface $cache,
    ): RadarService {
        return new CliRadarFactory($httpClient, $cache)->createFreeRadar(new CliOptions());
    }

    private static function addGlobalOptions(Application $application): void
    {
        $definition = $application->getDefinition();
        $definition->addOption(new InputOption(
            'currency',
            null,
            InputOption::VALUE_REQUIRED,
            'Converte os preços para uma moeda ISO 4217 (ex.: BRL).',
        ));
        $definition->addOption(new InputOption(
            'country',
            null,
            InputOption::VALUE_REQUIRED,
            'Região comercial ISO 3166-1 alpha-2.',
            'US',
        ));
        $definition->addOption(new InputOption(
            'locale',
            null,
            InputOption::VALUE_REQUIRED,
            'Idioma da interface e das fontes compatíveis no formato ll-RR.',
            'en-US',
        ));
        $definition->addOption(new InputOption(
            'min-score',
            null,
            InputOption::VALUE_REQUIRED,
            'Score mínimo aceito, entre 0 e 100.',
            '60',
        ));
        $definition->addOption(new InputOption(
            'no-cache',
            null,
            InputOption::VALUE_NONE,
            'Ignora o cache de ofertas nesta execução.',
        ));
    }
}
