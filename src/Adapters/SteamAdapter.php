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
 * Adaptador para as promoções em destaque da Steam Store.
 *
 * O endpoint público retorna preços em centavos e os regionaliza pelos
 * parâmetros cc e l. A resposta é externa e não versionada, por isso cada
 * registro é validado antes de entrar no domínio.
 */
final class SteamAdapter implements StoreAdapterInterface
{
    private const string ENDPOINT = 'https://store.steampowered.com/api/featuredcategories';

    private const string PRODUCT_BASE_URL = 'https://store.steampowered.com/app/';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $country = 'US',
        private readonly string $language = 'english',
    ) {
        if (preg_match('/^[A-Z]{2}$/', $this->country) !== 1) {
            throw new InvalidArgumentException('O país da Steam deve usar ISO 3166-1 alpha-2 em maiúsculas.');
        }

        if (preg_match('/^[a-z]+$/', $this->language) !== 1) {
            throw new InvalidArgumentException('O idioma da Steam deve conter somente letras minúsculas.');
        }
    }

    /**
     * @return list<GameDeal>
     * @throws GuzzleException
     * @throws JsonException
     */
    public function fetchDeals(): array
    {
        return $this->fetchSpecials()
            |> (fn(array $items): array => $this->toDeals($items, onlyFree: false));
    }

    /**
     * @return list<GameDeal>
     * @throws GuzzleException
     * @throws JsonException
     */
    public function fetchFreeGames(): array
    {
        return $this->fetchSpecials()
            |> (fn(array $items): array => $this->toDeals($items, onlyFree: true));
    }

    /**
     * @return list<array<string, mixed>>
     * @throws GuzzleException
     * @throws JsonException
     */
    private function fetchSpecials(): array
    {
        $response = $this->httpClient->request('GET', self::ENDPOINT, [
            'query' => [
                'cc' => $this->country,
                'l' => $this->language,
            ],
        ]);

        return $response->getBody()->getContents()
            |> (fn(string $payload): mixed => json_decode($payload, true, 512, JSON_THROW_ON_ERROR))
            |> $this->extractSpecials(...);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractSpecials(mixed $payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        $items = $payload['specials']['items'] ?? null;
        if (!is_array($items)) {
            return [];
        }

        $specials = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $specials[] = $item;
            }
        }

        return $specials;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<GameDeal>
     */
    private function toDeals(array $items, bool $onlyFree): array
    {
        $deals = [];
        foreach ($items as $item) {
            $id = $item['id'] ?? null;
            $title = $item['name'] ?? null;
            $currency = $item['currency'] ?? null;
            $originalPrice = $this->centsToAmount($item['original_price'] ?? null);
            $currentPrice = $this->centsToAmount($item['final_price'] ?? null);

            if ((!is_int($id) && !(is_string($id) && ctype_digit($id)))
                || (int) $id < 1
                || !is_string($title) || trim($title) === ''
                || !is_string($currency) || preg_match('/^[A-Za-z]{3}$/', $currency) !== 1
                || $originalPrice === null || $currentPrice === null
                || $currentPrice > $originalPrice) {
                continue;
            }

            $isFree = $currentPrice === 0.0 && $originalPrice > 0.0;
            $isDiscounted = $currentPrice < $originalPrice;
            if (($onlyFree && !$isFree) || (!$onlyFree && !$isDiscounted)) {
                continue;
            }

            $deals[] = new GameDeal(
                title: trim($title),
                storeName: 'Steam',
                originalPrice: $originalPrice,
                currentPrice: $currentPrice,
                checkoutUrl: self::PRODUCT_BASE_URL . (string) $id,
                approvalRating: null,
                isFree: $isFree,
                currency: strtoupper($currency),
            );
        }

        return $deals;
    }

    private function centsToAmount(mixed $value): ?float
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < 0) {
            return null;
        }

        return (float) $value / 100;
    }
}
