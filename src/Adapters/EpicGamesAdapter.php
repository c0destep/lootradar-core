<?php

declare(strict_types=1);

namespace LootRadar\Adapters;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LootRadar\Contracts\StoreAdapterInterface;
use LootRadar\DTO\GameDeal;
use NoDiscard;

class EpicGamesAdapter implements StoreAdapterInterface
{
    private const string ENDPOINT = 'https://store-site-backend-static.ak.epicgames.com/freeGamesPromotions';

    private const string PRODUCT_BASE_URL = 'https://store.epicgames.com/p/';

    public function __construct(private Client $httpClient)
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
     */
    public function fetchFreeGames(): array
    {
        $response = $this->httpClient->get(self::ENDPOINT);
        $jsonPayload = $response->getBody()->getContents();

        // Pipeline com o Operador Pipe nativo do PHP 8.5+ (|>).
        // Observação: json_decode recebe um closure porque a sintaxe
        // first-class-callable `func(...)` não aceita argumentos extras
        // (ex.: `json_decode(..., true)` é inválido). O pipe segue sendo usado.
        $processedGames = $jsonPayload
                |> (fn(string $raw): mixed => json_decode($raw, true))
                |> $this->extractElements(...)
                |> $this->filterValidDeals(...);

        return $processedGames;
    }

    /**
     * @return array<GameDeal>
     */
    public function fetchDeals(): array
    {
        return [];
    }

    /**
     * @return array<int, mixed>
     */
    private function extractElements(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        $elements = $data['data']['Catalog']['searchStore']['elements'] ?? [];

        return is_array($elements) ? array_values($elements) : [];
    }

    /**
     * @param array<int, mixed> $elements
     *
     * @return array<GameDeal>
     */
    private function filterValidDeals(array $elements): array
    {
        $results = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $totalPrice = $element['price']['totalPrice'] ?? [];
            if (!is_array($totalPrice)) {
                continue;
            }

            if ((int)($totalPrice['discountPrice'] ?? 1) === 0) {
                $originalPrice = (float)($totalPrice['originalPrice'] ?? 0) / 100;
                $mappings = $element['catalogNs']['mappings'] ?? [];
                $pageSlug = is_array($mappings) ? (string)($mappings[0]['pageSlug'] ?? '') : '';
                if ($pageSlug === '') {
                    continue;
                }

                $results[] = new GameDeal(
                    title: (string)($element['title'] ?? 'Desconhecido'),
                    storeName: 'Epic Games Store',
                    originalPrice: $originalPrice,
                    currentPrice: 0.0,
                    checkoutUrl: self::PRODUCT_BASE_URL . $pageSlug,
                    approvalRating: 100,
                    isFree: true
                );
            }
        }
        return $results;
    }
}
