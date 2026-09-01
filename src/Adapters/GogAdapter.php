<?php

declare(strict_types=1);

namespace LootRadar\Adapters;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use JsonException;
use LootRadar\Contracts\StoreAdapterInterface;
use LootRadar\DTO\GameDeal;

/**
 * Adaptador best-effort para o catálogo público, porém não oficial, da GOG.
 *
 * A integração é mantida atrás do contrato comum e coberta por fixture para
 * que alterações no formato não atinjam os outros adapters.
 */
final class GogAdapter implements StoreAdapterInterface
{
    private const string ENDPOINT = 'https://catalog.gog.com/v1/catalog';

    private const string PRODUCT_BASE_URL = 'https://www.gog.com/en/game/';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $country = 'US',
        private readonly string $locale = 'en-US',
        private readonly int $limit = 20,
    ) {
        if (preg_match('/^[A-Z]{2}$/', $this->country) !== 1) {
            throw new InvalidArgumentException('O país da GOG deve usar ISO 3166-1 alpha-2 em maiúsculas.');
        }

        if (preg_match('/^[a-z]{2}-[A-Z]{2}$/', $this->locale) !== 1) {
            throw new InvalidArgumentException('O locale da GOG deve usar o formato ll-RR.');
        }

        if ($this->limit < 1 || $this->limit > 100) {
            throw new InvalidArgumentException('O limite da GOG deve estar entre 1 e 100.');
        }
    }

    /**
     * @return list<GameDeal>
     * @throws GuzzleException
     * @throws JsonException
     */
    public function fetchDeals(): array
    {
        return $this->fetchProducts()
            |> (fn(array $products): array => $this->toDeals($products, onlyFree: false));
    }

    /**
     * @return list<GameDeal>
     * @throws GuzzleException
     * @throws JsonException
     */
    public function fetchFreeGames(): array
    {
        return $this->fetchProducts()
            |> (fn(array $products): array => $this->toDeals($products, onlyFree: true));
    }

    /**
     * @return list<array<string, mixed>>
     * @throws GuzzleException
     * @throws JsonException
     */
    private function fetchProducts(): array
    {
        $response = $this->httpClient->request('GET', self::ENDPOINT, [
            'query' => [
                'country' => $this->country,
                'locale' => $this->locale,
                'limit' => $this->limit,
                'order' => 'desc:trending',
                'productType' => 'in:game',
            ],
        ]);

        return $response->getBody()->getContents()
            |> (fn(string $payload): mixed => json_decode($payload, true, 512, JSON_THROW_ON_ERROR))
            |> $this->extractProducts(...);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractProducts(mixed $payload): array
    {
        if (!is_array($payload) || !is_array($payload['products'] ?? null)) {
            return [];
        }

        $products = [];
        foreach ($payload['products'] as $product) {
            if (is_array($product)) {
                /** @var array<string, mixed> $product */
                $products[] = $product;
            }
        }

        return $products;
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<GameDeal>
     */
    private function toDeals(array $products, bool $onlyFree): array
    {
        $deals = [];
        foreach ($products as $product) {
            $title = $product['title'] ?? null;
            $slug = $product['slug'] ?? null;
            $price = $product['price'] ?? null;
            if (!is_string($title) || trim($title) === '' || !is_string($slug) || trim($slug) === '' || !is_array($price)) {
                continue;
            }

            $originalPrice = $this->amount($price['baseAmount'] ?? null);
            $currentPrice = $this->amount($price['finalAmount'] ?? null);
            $currency = $price['currency'] ?? null;
            if ($originalPrice === null || $currentPrice === null
                || $currentPrice > $originalPrice
                || !is_string($currency) || preg_match('/^[A-Za-z]{3}$/', $currency) !== 1) {
                continue;
            }

            $isFree = $currentPrice === 0.0 && $originalPrice > 0.0;
            $isDiscounted = $currentPrice < $originalPrice;
            if (($onlyFree && !$isFree) || (!$onlyFree && !$isDiscounted)) {
                continue;
            }

            $deals[] = new GameDeal(
                title: trim($title),
                storeName: 'GOG',
                originalPrice: $originalPrice,
                currentPrice: $currentPrice,
                checkoutUrl: self::PRODUCT_BASE_URL . rawurlencode(trim($slug)),
                approvalRating: null,
                isFree: $isFree,
                currency: strtoupper($currency),
            );
        }

        return $deals;
    }

    private function amount(mixed $value): ?float
    {
        if ((!is_int($value) && !is_float($value) && !is_string($value))
            || !is_numeric($value)
            || !is_finite((float) $value)
            || (float) $value < 0) {
            return null;
        }

        return (float) $value;
    }
}
