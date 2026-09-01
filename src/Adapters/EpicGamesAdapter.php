<?php

declare(strict_types=1);

namespace LootRadar\Adapters;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use LootRadar\Contracts\StoreAdapterInterface;
use LootRadar\DTO\GameDeal;
use NoDiscard;

final class EpicGamesAdapter implements StoreAdapterInterface
{
    private const string ENDPOINT = 'https://store-site-backend-static.ak.epicgames.com/freeGamesPromotions';

    private const string PRODUCT_BASE_URL = 'https://store.epicgames.com/p/';

    public function __construct(private readonly ClientInterface $httpClient)
    {
    }

    /**
     * Verifica a saúde da API pública da Epic Games.
     *
     * Marcado com #[\NoDiscard] (PHP 8.5): o resultado NÃO pode ser
     * descartado — quem chamar é obrigado a consumir o booleano retornado.
     */
    #[NoDiscard('verifique o status da API antes de prosseguir com a coleta')]
    public function verifyApiHealth(): bool
    {
        $response = $this->httpClient->get(self::ENDPOINT);
        return $response->getStatusCode() === 200;
    }

    /**
     * @return array<GameDeal>
     * @throws GuzzleException
     * @throws JsonException
     */
    public function fetchFreeGames(): array
    {
        return $this->fetchElements()
            |> (fn(array $elements): array => $this->toDeals($elements, onlyFree: true));
    }

    /**
     * @return list<GameDeal>
     * @throws GuzzleException
     * @throws JsonException
     */
    public function fetchDeals(): array
    {
        return $this->fetchElements()
            |> (fn(array $elements): array => $this->toDeals($elements, onlyFree: false));
    }

    /**
     * @return list<array<string, mixed>>
     * @throws GuzzleException
     * @throws JsonException
     */
    private function fetchElements(): array
    {
        $response = $this->httpClient->request('GET', self::ENDPOINT);
        $jsonPayload = $response->getBody()->getContents();

        // Pipeline com o Operador Pipe nativo do PHP 8.5+ (|>).
        // Observação: json_decode recebe um closure porque a sintaxe
        // first-class-callable `func(...)` não aceita argumentos extras
        // (ex.: `json_decode(..., true)` é inválido). O pipe segue sendo usado.
        return $jsonPayload
            |> (fn(string $raw): mixed => json_decode($raw, true, 512, JSON_THROW_ON_ERROR))
            |> $this->extractElements(...);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractElements(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        $elements = $data['data']['Catalog']['searchStore']['elements'] ?? [];

        if (!is_array($elements)) {
            return [];
        }

        $rows = [];
        foreach ($elements as $element) {
            if (is_array($element)) {
                /** @var array<string, mixed> $element */
                $rows[] = $element;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $elements
     *
     * @return list<GameDeal>
     */
    private function toDeals(array $elements, bool $onlyFree): array
    {
        $results = [];
        foreach ($elements as $element) {
            $totalPrice = $element['price']['totalPrice'] ?? [];
            if (!is_array($totalPrice)) {
                continue;
            }

            $originalPrice = $totalPrice['originalPrice'] ?? null;
            $discountPrice = $totalPrice['discountPrice'] ?? null;
            if ((!is_int($originalPrice) && !is_float($originalPrice))
                || (!is_int($discountPrice) && !is_float($discountPrice))
                || $originalPrice < 0 || $discountPrice < 0 || $discountPrice > $originalPrice) {
                continue;
            }

            $isFree = $discountPrice === 0 || $discountPrice === 0.0;
            if (($onlyFree && !$isFree) || (!$onlyFree && $discountPrice >= $originalPrice)) {
                continue;
            }

            $title = $element['title'] ?? null;
            $mappings = $element['catalogNs']['mappings'] ?? [];
            $pageSlug = is_array($mappings) ? ($mappings[0]['pageSlug'] ?? null) : null;
            if (!is_string($title) || trim($title) === '' || !is_string($pageSlug) || trim($pageSlug) === '') {
                continue;
            }

            $currency = $totalPrice['currencyCode'] ?? null;
            $results[] = new GameDeal(
                title: trim($title),
                storeName: 'Epic Games Store',
                originalPrice: (float) $originalPrice / 100,
                currentPrice: (float) $discountPrice / 100,
                checkoutUrl: self::PRODUCT_BASE_URL . $pageSlug,
                approvalRating: null,
                isFree: $isFree,
                currency: is_string($currency) && $currency !== '' ? strtoupper($currency) : null,
            );
        }

        return $results;
    }
}
