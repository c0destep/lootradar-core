<?php

declare(strict_types=1);

namespace LootRadar\Services;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;

/**
 * Serializa a saída pública do Core em um documento estável para clientes Web.
 */
final readonly class JsonSnapshotExporter
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @param list<array<string, mixed>> $freeGames
     * @param list<array<string, mixed>> $deals
     * @param array{freeGames: bool, deals: bool} $complete
     * @param array{country: string, locale: string, currency: string, minimumScore: int} $context
     *
     * @throws JsonException
     */
    public function export(
        array $freeGames,
        array $deals,
        array $complete,
        array $context,
        ?DateTimeImmutable $generatedAt = null,
    ): string {
        $generatedAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return json_encode(
            [
                'schemaVersion' => self::SCHEMA_VERSION,
                'generatedAt' => $generatedAt
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d\TH:i:s\Z'),
                'context' => $context,
                'data' => [
                    'freeGames' => $freeGames,
                    'deals' => $deals,
                ],
                'complete' => $complete['freeGames'] && $complete['deals'],
                'sources' => [
                    'freeGames' => ['complete' => $complete['freeGames']],
                    'deals' => ['complete' => $complete['deals']],
                ],
            ],
            JSON_THROW_ON_ERROR
                | JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
