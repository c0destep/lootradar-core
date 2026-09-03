<?php

declare(strict_types=1);

namespace LootRadar\Cli;

use GuzzleHttp\ClientInterface;
use LogicException;
use LootRadar\Adapters\EpicGamesAdapter;
use LootRadar\Adapters\GogAdapter;
use LootRadar\Adapters\ItadAdapter;
use LootRadar\Adapters\SteamAdapter;
use LootRadar\Contracts\CacheInterface;
use LootRadar\Contracts\ExchangeRateProviderInterface;
use LootRadar\Services\CurrencyConverter;
use LootRadar\Services\FrankfurterExchangeRateProvider;
use LootRadar\Services\RadarService;
use LootRadar\Services\ShovelwareFilter;

/**
 * Composition root dos serviços usados por cada comando da CLI.
 */
final readonly class CliRadarFactory implements CliRadarFactoryInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private CacheInterface $cache,
        private ?string $itadApiKey = null,
        private ?ExchangeRateProviderInterface $exchangeRateProvider = null,
    ) {
    }

    public function createFreeRadar(CliOptions $options): RadarService
    {
        $radar = $this->createRadar($options);
        $radar->registerAdapter(new EpicGamesAdapter($this->httpClient));
        $radar->registerAdapter(new SteamAdapter(
            $this->httpClient,
            country: $options->country,
            language: self::steamLanguage($options->locale),
        ));
        $radar->registerAdapter(new GogAdapter(
            $this->httpClient,
            country: $options->country,
            locale: $options->locale,
        ));

        return $radar;
    }

    public function createDealRadar(CliOptions $options, int $limit): RadarService
    {
        $apiKey = trim($this->itadApiKey ?? '');
        if ($apiKey === '') {
            throw new LogicException('Defina ITAD_API_KEY para usar o comando deal.');
        }

        $radar = $this->createRadar($options, ['source_limit' => $limit]);
        $radar->registerAdapter(new ItadAdapter(
            $this->httpClient,
            apiKey: $apiKey,
            country: $options->country,
            limit: $limit,
        ));

        return $radar;
    }

    /** @param array<string, int|string> $extraCacheContext */
    private function createRadar(CliOptions $options, array $extraCacheContext = []): RadarService
    {
        $rateProvider = $this->exchangeRateProvider
            ?? new FrankfurterExchangeRateProvider($this->httpClient);
        $currencyConverter = $options->currency === null
            ? null
            : new CurrencyConverter($rateProvider, $this->cache);

        return new RadarService(
            cache: $this->cache,
            shovelwareFilter: new ShovelwareFilter($options->minimumScore),
            currencyConverter: $currencyConverter,
            targetCurrency: $options->currency,
            cacheContext: [...$options->cacheContext(), ...$extraCacheContext],
        );
    }

    private static function steamLanguage(string $locale): string
    {
        return match ($locale) {
            'pt-BR' => 'brazilian',
            'pt-PT' => 'portuguese',
            'de-DE' => 'german',
            'es-ES' => 'spanish',
            'fr-FR' => 'french',
            'it-IT' => 'italian',
            'ja-JP' => 'japanese',
            'ko-KR' => 'koreana',
            'pl-PL' => 'polish',
            'ru-RU' => 'russian',
            default => 'english',
        };
    }
}
