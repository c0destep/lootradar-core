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
    public const string VERSION = '0.3.0';

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
            $itadApiKey = self::itadApiKeyFromEnvironment();
        }

        $application = new LootRadarApplication(self::VERSION);
        self::addGlobalOptions($application);

        $radarFactory = new CliRadarFactory($client, $cache, $itadApiKey, $exchangeRateProvider);
        $application->addCommand(new FreeGamesCommand($radarFactory));
        $application->addCommand(new DealCommand($radarFactory));
        $application->get('help')
            ->setDescription('Exibe a ajuda geral ou detalha um comando.')
            ->setHelp(LootRadarApplication::capabilitiesHelp());
        $application->get('list')
            ->setDescription('Lista os comandos e apresenta os recursos disponíveis.')
            ->setHelp(LootRadarApplication::capabilitiesHelp());

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
            'Moeda-alvo dos preços, em ISO 4217 (ex.: BRL); sem esta opção, mantém a moeda da fonte.',
        ));
        $definition->addOption(new InputOption(
            'country',
            null,
            InputOption::VALUE_REQUIRED,
            'Região comercial dos preços, em ISO 3166-1 alpha-2 (ex.: BR).',
            'US',
        ));
        $definition->addOption(new InputOption(
            'locale',
            null,
            InputOption::VALUE_REQUIRED,
            'Idioma e localidade das fontes compatíveis (ex.: pt-BR); não altera a região comercial.',
            'en-US',
        ));
        $definition->addOption(new InputOption(
            'min-score',
            null,
            InputOption::VALUE_REQUIRED,
            'Score mínimo entre 0 e 100; ofertas sem avaliação são mantidas.',
            '60',
        ));
        $definition->addOption(new InputOption(
            'no-cache',
            null,
            InputOption::VALUE_NONE,
            'Não lê nem grava o cache de ofertas nesta execução.',
        ));
    }

    private static function itadApiKeyFromEnvironment(): ?string
    {
        $processValue = getenv('ITAD_API_KEY');
        $candidates = [
            $processValue,
            $_SERVER['ITAD_API_KEY'] ?? null,
            $_ENV['ITAD_API_KEY'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
