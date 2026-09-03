<?php

declare(strict_types=1);

namespace LootRadar\Services;

use LootRadar\Contracts\CacheInterface;
use LootRadar\Contracts\StoreAdapterInterface;
use LootRadar\DTO\GameDeal;
use Throwable;

/**
 * Orquestrador do Core: coleta nos adapters, aplica o pipeline e serializa.
 *
 * O cache saiu daqui para trás de `CacheInterface` — este serviço não sabe se
 * está falando com JSON ou SQLite.
 */
class RadarService
{
    public const string CACHE_KEY_FREE_GAMES = 'free-games';

    public const string CACHE_KEY_DEALS = 'deals';

    /** @var list<StoreAdapterInterface> */
    private array $adapters = [];

    /** @var list<string> */
    private array $failures = [];

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly UrlSanitizer $urlSanitizer = new UrlSanitizer(),
        private readonly ShovelwareFilter $shovelwareFilter = new ShovelwareFilter(),
        private readonly ?CurrencyConverter $currencyConverter = null,
        private readonly ?string $targetCurrency = null,
    ) {
        if ($this->targetCurrency !== null && $this->currencyConverter === null) {
            throw new \InvalidArgumentException('Uma moeda-alvo requer um CurrencyConverter.');
        }
    }

    public function registerAdapter(StoreAdapterInterface $adapter): void
    {
        $this->adapters[] = $adapter;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFreeGames(bool $bypassCache = false): array
    {
        return $this->collect(
            self::CACHE_KEY_FREE_GAMES,
            static fn(StoreAdapterInterface $adapter): array => $adapter->fetchFreeGames(),
            $bypassCache,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDeals(bool $bypassCache = false): array
    {
        return $this->collect(
            self::CACHE_KEY_DEALS,
            static fn(StoreAdapterInterface $adapter): array => $adapter->fetchDeals(),
            $bypassCache,
        );
    }

    /**
     * As N maiores promoções, já ordenadas por desconto decrescente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopDeals(int $limit = 10, bool $bypassCache = false): array
    {
        $deals = $this->getDeals($bypassCache);

        usort(
            $deals,
            static fn(array $a, array $b): int => ((int)($b['discountPercentage'] ?? 0)) <=> ((int)($a['discountPercentage'] ?? 0))
        );

        return $limit > 0 ? array_slice($deals, 0, $limit) : $deals;
    }

    /**
     * Falhas de adapters da última coleta (uma loja fora do ar não derruba as
     * outras — princípio 3 do ROADMAP). Vazio quando tudo correu bem.
     *
     * @return list<string>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * @param callable(StoreAdapterInterface): array<int, GameDeal> $fetcher
     *
     * @return array<int, array<string, mixed>>
     */
    private function collect(string $cacheKey, callable $fetcher, bool $bypassCache): array
    {
        $this->failures = [];

        if (!$bypassCache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $this->narrowToRows($cached);
            }
        }

        $collection = $this->gather($fetcher);
        $this->failures = $collection['failures'];

        // Pipeline componível com o operador pipe do PHP 8.5:
        // coletar |> filtrar shovelware |> converter moeda |> higienizar URLs |> serializar.
        $preparedDeals = $collection['deals']
                |> $this->shovelwareFilter->filter(...)
                |> $this->convertCurrency(...);
        $sanitization = $this->sanitizeUrls($preparedDeals);
        $this->failures = [...$collection['failures'], ...$sanitization['failures']];
        $payload = $sanitization['deals'] |> $this->serialize(...);

        if ($this->failures === []) {
            $this->cache->put($cacheKey, $payload);
        }

        return $payload;
    }

    /**
     * @param callable(StoreAdapterInterface): array<int, GameDeal> $fetcher
     *
     * @return array{deals: list<GameDeal>, failures: list<string>}
     */
    private function gather(callable $fetcher): array
    {
        $collected = [];
        $failures = [];
        foreach ($this->adapters as $adapter) {
            try {
                $collected = [...$collected, ...$fetcher($adapter)];
            } catch (Throwable $exception) {
                // Resiliência: registra e segue para o próximo adapter.
                $failures[] = $adapter::class . ': ' . $exception->getMessage();
            }
        }

        return ['deals' => $collected, 'failures' => $failures];
    }

    /**
     * Toda URL exibida passa por aqui (Definição de Pronto §8.5). Ofertas cujo
     * link não sobrevive à higienização são descartadas: sem link confiável não
     * há oferta utilizável.
     *
     * @param list<GameDeal> $deals
     *
     * @return array{deals: list<GameDeal>, failures: list<string>}
     */
    private function sanitizeUrls(array $deals): array
    {
        $safe = [];
        $failures = [];
        foreach ($deals as $deal) {
            $sanitized = $this->urlSanitizer->sanitize($deal->checkoutUrl);
            if ($sanitized === null) {
                $failures[] = "URL rejeitada para '{$deal->title}': {$deal->checkoutUrl}";
                continue;
            }

            $safe[] = $sanitized === $deal->checkoutUrl ? $deal : $deal->withCheckoutUrl($sanitized);
        }

        return ['deals' => $safe, 'failures' => $failures];
    }

    /**
     * @param list<GameDeal> $deals
     *
     * @return list<GameDeal>
     */
    private function convertCurrency(array $deals): array
    {
        if ($this->currencyConverter === null || $this->targetCurrency === null) {
            return $deals;
        }

        return array_map(
            fn(GameDeal $deal): GameDeal => $this->currencyConverter->convertDeal($deal, $this->targetCurrency),
            $deals,
        );
    }

    /**
     * @param list<GameDeal> $deals
     *
     * @return array<int, array<string, mixed>>
     */
    private function serialize(array $deals): array
    {
        return array_map(static fn(GameDeal $deal): array => $deal->toArray(), $deals);
    }

    /**
     * O cache devolve `array<mixed>`; aqui garantimos que só linhas associativas
     * cheguem a quem consome, mesmo se o arquivo tiver sido editado à mão.
     *
     * @param array<mixed> $cached
     *
     * @return array<int, array<string, mixed>>
     */
    private function narrowToRows(array $cached): array
    {
        $rows = [];
        foreach ($cached as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
