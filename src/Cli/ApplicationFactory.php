<?php

declare(strict_types=1);

namespace LootRadar\Cli;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use LootRadar\Adapters\EpicGamesAdapter;
use LootRadar\Adapters\GogAdapter;
use LootRadar\Adapters\SteamAdapter;
use LootRadar\Cache\JsonCache;
use LootRadar\Commands\FreeGamesCommand;
use LootRadar\Contracts\CacheInterface;
use LootRadar\Services\RadarService;
use Symfony\Component\Console\Application;

/**
 * Ponto de composição da CLI com os recursos públicos disponíveis no Core.
 */
final class ApplicationFactory
{
    public const string VERSION = '0.2.0';

    public static function create(
        ?ClientInterface $httpClient = null,
        ?CacheInterface $cache = null,
    ): Application {
        $client = $httpClient ?? new Client([
            'connect_timeout' => 5.0,
            'timeout' => 15.0,
            'headers' => ['User-Agent' => 'LootRadar/' . self::VERSION],
        ]);
        $cache ??= new JsonCache(
            sys_get_temp_dir() . '/lootradar/' . self::VERSION,
        );

        $application = new Application('LootRadar', self::VERSION);
        $application->addCommand(new FreeGamesCommand(self::createRadar($client, $cache)));

        return $application;
    }

    public static function createRadar(
        ClientInterface $httpClient,
        CacheInterface $cache,
    ): RadarService {
        $radar = new RadarService($cache);
        $radar->registerAdapter(new EpicGamesAdapter($httpClient));
        $radar->registerAdapter(new SteamAdapter($httpClient));
        $radar->registerAdapter(new GogAdapter($httpClient));

        return $radar;
    }
}
