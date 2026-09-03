<?php

declare(strict_types=1);

namespace LootRadar\Services;

use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use JsonException;
use LootRadar\Contracts\ExchangeRateProviderInterface;
use UnexpectedValueException;

/**
 * Taxas de referência diárias fornecidas pela API pública Frankfurter v2.
 */
final class FrankfurterExchangeRateProvider implements ExchangeRateProviderInterface
{
    private const string API_BASE_URL = 'https://api.frankfurter.dev/v2/rate';

    public function __construct(private readonly ClientInterface $httpClient)
    {
    }

    /**
     * @throws JsonException
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        $from = self::currency($fromCurrency);
        $to = self::currency($toCurrency);

        if ($from === $to) {
            return 1.0;
        }

        $response = $this->httpClient->request(
            'GET',
            self::API_BASE_URL . '/' . rawurlencode($from) . '/' . rawurlencode($to),
            ['headers' => ['Accept' => 'application/json']],
        );
        $decoded = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        $rate = is_array($decoded) ? ($decoded['rate'] ?? null) : null;

        if ((!is_int($rate) && !is_float($rate)) || !is_finite((float) $rate) || $rate <= 0) {
            throw new UnexpectedValueException('A API Frankfurter devolveu uma taxa inválida.');
        }

        return (float) $rate;
    }

    private static function currency(string $currency): string
    {
        $normalized = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/', $normalized) !== 1) {
            throw new InvalidArgumentException('A moeda deve usar um código ISO 4217 com três letras.');
        }

        return $normalized;
    }
}
