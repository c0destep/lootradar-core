<?php

declare(strict_types=1);

namespace LootRadar\Services;

use Closure;
use InvalidArgumentException;
use LootRadar\Contracts\RateLimiterInterface;
use LootRadar\Exceptions\RateLimitExceededException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Limita requisições em uma janela deslizante compartilhada entre processos.
 *
 * O SQLite é usado separadamente do cache de respostas porque a reserva da
 * quota precisa ocorrer em uma transação atômica.
 */
final class SqliteSlidingWindowRateLimiter implements RateLimiterInterface
{
    private const string EVENTS_TABLE = 'rate_limit_events';

    private const string BLOCKS_TABLE = 'rate_limit_blocks';

    private PDO $connection;

    /** @var Closure(): float */
    private readonly Closure $clock;

    /**
     * O padrão mantém uma margem de 5% diante da quota pública de 1.000
     * requisições por janela de cinco minutos do ITAD.
     *
     * @param null|Closure(): float $clock
     */
    public function __construct(
        string $databasePath,
        private readonly int $maxRequests = 950,
        private readonly int $windowSeconds = 300,
        ?Closure $clock = null,
    ) {
        if ($this->maxRequests < 1) {
            throw new InvalidArgumentException('A quantidade máxima de requisições deve ser positiva.');
        }
        if ($this->windowSeconds < 1) {
            throw new InvalidArgumentException('A janela da quota deve ser positiva.');
        }

        if ($databasePath !== ':memory:') {
            $directory = dirname($databasePath);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException("Não foi possível criar o diretório do limitador: {$directory}");
            }
        }

        $this->clock = $clock ?? static fn(): float => microtime(true);

        try {
            $this->connection = new PDO('sqlite:' . $databasePath, options: [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->connection->exec('PRAGMA busy_timeout = 5000');
            $this->createSchema();
        } catch (PDOException $exception) {
            throw new RuntimeException(
                "Falha ao abrir o limitador SQLite em {$databasePath}: {$exception->getMessage()}",
                previous: $exception,
            );
        }
    }

    #[\Override]
    public function consume(string $key): void
    {
        $this->validateKey($key);

        try {
            $this->beginImmediateTransaction();

            $now = ($this->clock)();
            $this->purgeExpired($now);

            $blockedUntil = $this->blockedUntil($key);
            if ($blockedUntil !== null && $blockedUntil > $now) {
                $retryAfter = max(1, (int)ceil($blockedUntil - $now));
                $this->connection->commit();

                throw new RateLimitExceededException($retryAfter);
            }

            $oldestRequest = $this->oldestRequestWhenFull($key);
            if ($oldestRequest !== null) {
                $retryAfter = max(1, (int)ceil($oldestRequest + $this->windowSeconds - $now));
                $this->connection->commit();

                throw new RateLimitExceededException($retryAfter);
            }

            $statement = $this->connection->prepare(
                'INSERT INTO ' . self::EVENTS_TABLE . ' (limiter_key, requested_at)
                 VALUES (:key, :requested_at)'
            );
            $statement->execute(['key' => $key, 'requested_at' => $now]);

            $this->connection->commit();
        } catch (PDOException $exception) {
            $this->rollbackIfNeeded();

            throw new RuntimeException('Falha ao reservar a quota de requisições.', previous: $exception);
        }
    }

    #[\Override]
    public function suspend(string $key, int $retryAfterSeconds): void
    {
        $this->validateKey($key);
        if ($retryAfterSeconds < 0) {
            throw new InvalidArgumentException('O período de suspensão não pode ser negativo.');
        }
        if ($retryAfterSeconds === 0) {
            return;
        }

        try {
            $this->beginImmediateTransaction();

            $blockedUntil = ($this->clock)() + $retryAfterSeconds;
            $statement = $this->connection->prepare(
                'INSERT INTO ' . self::BLOCKS_TABLE . ' (limiter_key, blocked_until)
                 VALUES (:key, :blocked_until)
                 ON CONFLICT(limiter_key) DO UPDATE SET
                    blocked_until = MAX(blocked_until, excluded.blocked_until)'
            );
            $statement->execute(['key' => $key, 'blocked_until' => $blockedUntil]);

            $this->connection->commit();
        } catch (PDOException $exception) {
            $this->rollbackIfNeeded();

            throw new RuntimeException('Falha ao registrar a suspensão da quota.', previous: $exception);
        }
    }

    private function createSchema(): void
    {
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::EVENTS_TABLE . ' (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                limiter_key  TEXT NOT NULL,
                requested_at REAL NOT NULL
            )'
        );
        $this->connection->exec(
            'CREATE INDEX IF NOT EXISTS idx_' . self::EVENTS_TABLE . '_window
             ON ' . self::EVENTS_TABLE . ' (limiter_key, requested_at)'
        );
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::BLOCKS_TABLE . ' (
                limiter_key   TEXT PRIMARY KEY NOT NULL,
                blocked_until REAL NOT NULL
            )'
        );
    }

    private function beginImmediateTransaction(): void
    {
        $this->connection->exec('BEGIN IMMEDIATE TRANSACTION');
    }

    private function purgeExpired(float $now): void
    {
        $events = $this->connection->prepare(
            'DELETE FROM ' . self::EVENTS_TABLE . ' WHERE requested_at <= :cutoff'
        );
        $events->execute(['cutoff' => $now - $this->windowSeconds]);

        $blocks = $this->connection->prepare(
            'DELETE FROM ' . self::BLOCKS_TABLE . ' WHERE blocked_until <= :now'
        );
        $blocks->execute(['now' => $now]);
    }

    private function blockedUntil(string $key): ?float
    {
        $statement = $this->connection->prepare(
            'SELECT blocked_until FROM ' . self::BLOCKS_TABLE . ' WHERE limiter_key = :key LIMIT 1'
        );
        $statement->execute(['key' => $key]);

        $value = $statement->fetchColumn();

        return $value === false ? null : (float)$value;
    }

    private function oldestRequestWhenFull(string $key): ?float
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) AS request_count, MIN(requested_at) AS oldest_request
             FROM ' . self::EVENTS_TABLE . ' WHERE limiter_key = :key'
        );
        $statement->execute(['key' => $key]);

        $row = $statement->fetch();
        if (!is_array($row) || (int)($row['request_count'] ?? 0) < $this->maxRequests) {
            return null;
        }

        $oldestRequest = $row['oldest_request'] ?? null;

        return is_numeric($oldestRequest) ? (float)$oldestRequest : null;
    }

    private function validateKey(string $key): void
    {
        if (trim($key) === '' || strlen($key) > 255) {
            throw new InvalidArgumentException('A chave da quota deve conter entre 1 e 255 caracteres.');
        }
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }
}
