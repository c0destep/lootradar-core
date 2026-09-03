<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use LootRadar\Adapters\ItadAdapter;
use Symfony\Component\Dotenv\Dotenv;

$projectRoot = dirname(__DIR__, 2);
$autoloadPath = $projectRoot . '/vendor/autoload.php';
$envPath = $projectRoot . '/.env';

if (!is_file($autoloadPath)) {
    fwrite(STDERR, "Dependências ausentes. Execute composer install antes do teste.\n");
    exit(1);
}

require $autoloadPath;

if (!is_file($envPath)) {
    fwrite(STDERR, "Arquivo .env ausente. Copie .env.example para .env e informe ITAD_API_KEY.\n");
    exit(1);
}

(new Dotenv())->usePutenv()->load($envPath);

$countryValue = getenv('ITAD_COUNTRY');
$country = is_string($countryValue) && trim($countryValue) !== ''
    ? strtoupper(trim($countryValue))
    : 'BR';

$limitValue = getenv('ITAD_LIMIT');
$limit = is_string($limitValue) && trim($limitValue) !== ''
    ? filter_var($limitValue, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 200],
    ])
    : 5;

if (!is_int($limit)) {
    fwrite(STDERR, "ITAD_LIMIT deve ser um número inteiro entre 1 e 200.\n");
    exit(1);
}

$client = new Client([
    'connect_timeout' => 5.0,
    'timeout' => 15.0,
    'headers' => [
        'User-Agent' => 'LootRadar-Core/0.2',
    ],
]);

try {
    $adapter = ItadAdapter::fromEnvironment($client, $country, $limit);
    $deals = $adapter->fetchDeals();

    fwrite(STDOUT, sprintf(
        "ITAD respondeu com %d promoção(ões) para %s.\n",
        count($deals),
        $country,
    ));

    foreach ($deals as $deal) {
        $title = sanitizeTerminalText($deal->title);
        $store = sanitizeTerminalText($deal->storeName);
        $currency = $deal->currency ?? '---';
        $historicalLow = $deal->historicalLow === null
            ? ''
            : sprintf(' | mínima histórica %.2f', $deal->historicalLow);

        fwrite(STDOUT, sprintf(
            "- %s | %s | %s %.2f -> %.2f%s\n",
            $title,
            $store,
            $currency,
            $deal->originalPrice,
            $deal->currentPrice,
            $historicalLow,
        ));
    }

    $gameId = getenv('ITAD_GAME_ID');
    if (is_string($gameId) && trim($gameId) !== '') {
        $history = $adapter->fetchPriceHistory(trim($gameId), $country);
        fwrite(STDOUT, sprintf(
            "O ITAD retornou %d registro(s) de histórico para o jogo informado.\n",
            count($history),
        ));
    }
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf(
        "Falha no teste manual do ITAD: %s\n",
        sanitizeTerminalText($exception->getMessage()),
    ));
    exit(1);
}

function sanitizeTerminalText(string $value): string
{
    return preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $value) ?? '';
}
