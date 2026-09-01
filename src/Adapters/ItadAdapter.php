<?php

declare(strict_types=1);

namespace LootRadar\Adapters;

use DateTimeImmutable;
use DateMalformedStringException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use JsonException;
use LogicException;
use LootRadar\Contracts\PriceHistoryProviderInterface;
use LootRadar\Contracts\StoreAdapterInterface;
use LootRadar\DTO\GameDeal;
use LootRadar\DTO\Money;
use LootRadar\DTO\PriceHistory;

/**
 * Fonte oficial de promoções e histórico de preços do IsThereAnyDeal.
 *
 * A API exige uma chave registrada para o aplicativo. Em execução real, use
 * ITAD_API_KEY; os testes usam um cliente HTTP simulado e fixtures estáticas.
 *
 * @see https://docs.isthereanydeal.com/
 */
final class ItadAdapter implements StoreAdapterInterface, PriceHistoryProviderInterface
{
    private const string API_BASE_URL = 'https://api.isthereanydeal.com';

    private const string DEALS_ENDPOINT = self::API_BASE_URL . '/deals/v2';

    private const string HISTORY_ENDPOINT = self::API_BASE_URL . '/games/history/v2';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $country = 'US',
        private readonly int $limit = 20,
    ) {
        if (trim($this->apiKey) === '') {
            throw new InvalidArgumentException('A chave da API do ITAD não pode ser vazia.');
        }

        if (preg_match('/^[A-Z]{2}$/', $this->country) !== 1) {
            throw new InvalidArgumentException('O país do ITAD deve usar ISO 3166-1 alpha-2.');
        }

        if ($this->limit < 1 || $this->limit > 200) {
            throw new InvalidArgumentException('O limite do ITAD deve estar entre 1 e 200.');
        }
    }

    public static function fromEnvironment(
        ClientInterface $httpClient,
        string $country = 'US',
        int $limit = 20,
    ): self {
        $apiKey = getenv('ITAD_API_KEY');
        if (!is_string($apiKey) || trim($apiKey) === '') {
            throw new LogicException('Defina ITAD_API_KEY para consultar promoções do IsThereAnyDeal.');
        }

        return new self($httpClient, $apiKey, strtoupper($country), $limit);
    }

    /**
     * @return list<GameDeal>
     * @throws GuzzleException
     * @throws JsonException
     */
    public function fetchDeals(): array
    {
        $response = $this->httpClient->request('GET', self::DEALS_ENDPOINT, [
            'headers' => $this->headers(),
            'query' => [
                'country' => $this->country,
                'limit' => $this->limit,
                'sort' => '-cut',
                'nondeals' => 'false',
                'mature' => 'false',
            ],
        ]);

        return $this->decode($response->getBody()->getContents())
            |> $this->toDeals(...);
    }

    /**
     * ITAD não é uma fonte de jogos gratuitos semanais. Promoções que chegam
     * a custo zero ainda aparecem em fetchDeals(), como parte do catálogo.
     *
     * @return list<GameDeal>
     */
    public function fetchFreeGames(): array
    {
        return [];
    }

    /**
     * @return list<PriceHistory>
     * @throws GuzzleException
     * @throws JsonException
     */
    public function fetchPriceHistory(
        string $gameId,
        string $country = 'US',
        ?DateTimeImmutable $since = null,
    ): array {
        if (trim($gameId) === '') {
            throw new InvalidArgumentException('O identificador do jogo não pode ser vazio.');
        }

        $normalizedCountry = strtoupper($country);
        if (preg_match('/^[A-Z]{2}$/', $normalizedCountry) !== 1) {
            throw new InvalidArgumentException('O país do ITAD deve usar ISO 3166-1 alpha-2.');
        }

        $query = [
            'id' => $gameId,
            'country' => $normalizedCountry,
        ];
        if ($since !== null) {
            $query['since'] = $since->format(DATE_ATOM);
        }

        $response = $this->httpClient->request('GET', self::HISTORY_ENDPOINT, [
            'headers' => $this->headers(),
            'query' => $query,
        ]);

        return $this->decode($response->getBody()->getContents())
            |> (fn(array $entries): array => $this->toPriceHistory($gameId, $entries));
    }

    /**
     * @return array{Accept: string, ITAD-API-Key: string}
     */
    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'ITAD-API-Key' => $this->apiKey,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     * @throws JsonException
     */
    private function decode(string $payload): array
    {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $entry) {
            if (is_array($entry)) {
                /** @var array<string, mixed> $entry */
                $rows[] = $entry;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<GameDeal>
     */
    private function toDeals(array $items): array
    {
        $deals = [];
        foreach ($items as $item) {
            $deal = $item['deal'] ?? null;
            if (!is_array($deal)) {
                continue;
            }

            $title = $item['title'] ?? null;
            $shop = $deal['shop'] ?? null;
            $url = $deal['url'] ?? null;
            if (!is_string($title) || trim($title) === ''
                || !is_array($shop) || !is_string($shop['name'] ?? null)
                || !is_string($url) || trim($url) === '') {
                continue;
            }

            $price = $this->moneyFrom($deal['price'] ?? null);
            $regular = $this->moneyFrom($deal['regular'] ?? null);
            if ($price === null || $regular === null || $price->currency !== $regular->currency) {
                continue;
            }

            $historyLow = $this->moneyFrom($deal['historyLow'] ?? null);
            if ($historyLow !== null && $historyLow->currency !== $price->currency) {
                $historyLow = null;
            }

            $expiry = $deal['expiry'] ?? null;
            $deals[] = new GameDeal(
                title: trim($title),
                storeName: trim($shop['name']),
                originalPrice: $regular->amount,
                currentPrice: $price->amount,
                checkoutUrl: $url,
                currency: $price->currency,
                expiresAt: is_string($expiry) && $expiry !== '' ? $expiry : null,
                historicalLow: $historyLow?->amount,
            );
        }

        return $deals;
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return list<PriceHistory>
     */
    private function toPriceHistory(string $gameId, array $entries): array
    {
        $history = [];
        foreach ($entries as $entry) {
            $timestamp = $entry['timestamp'] ?? null;
            $shop = $entry['shop'] ?? null;
            $deal = $entry['deal'] ?? null;
            if (!is_string($timestamp) || !is_array($shop) || !is_string($shop['name'] ?? null) || !is_array($deal)) {
                continue;
            }

            $price = $this->moneyFrom($deal['price'] ?? null);
            $regular = $this->moneyFrom($deal['regular'] ?? null);
            $cut = $deal['cut'] ?? null;
            if ($price === null || $regular === null || !is_int($cut)) {
                continue;
            }

            try {
                $history[] = new PriceHistory(
                    gameId: $gameId,
                    recordedAt: new DateTimeImmutable($timestamp),
                    storeName: trim($shop['name']),
                    price: $price,
                    regularPrice: $regular,
                    discountPercentage: $cut,
                );
            } catch (DateMalformedStringException | InvalidArgumentException) {
                continue;
            }
        }

        return $history;
    }

    /**
     * @param mixed $value
     */
    private function moneyFrom(mixed $value): ?Money
    {
        if (!is_array($value) || !isset($value['amount'], $value['currency'])) {
            return null;
        }

        $amount = $value['amount'];
        $currency = $value['currency'];
        if ((!is_int($amount) && !is_float($amount)) || !is_string($currency)) {
            return null;
        }

        try {
            return new Money((float) $amount, $currency);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
