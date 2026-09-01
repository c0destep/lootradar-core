<?php

declare(strict_types=1);

namespace LootRadar\Cache;

use JsonException;
use LootRadar\Contracts\CacheInterface;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Cache em SQLite, atrás do mesmo `CacheInterface` do `JsonCache`.
 *
 * Existe para o que o JSON não faz bem (ROADMAP §7.1): histórico de preços
 * consultado por período e wishlist matching por join. Passe ':memory:' como
 * caminho para um banco efêmero (usado nos testes).
 */
final class SqliteCache implements CacheInterface
{
    private const string TABLE = 'lootradar_cache';

    private PDO $connection;

    public function __construct(
        string $databasePath,
        private readonly int $defaultTtlSeconds = 43200,
    ) {
        if ($databasePath !== ':memory:') {
            $directory = dirname($databasePath);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException("Não foi possível criar o diretório do banco: {$directory}");
            }
        }

        try {
            $this->connection = new PDO('sqlite:' . $databasePath, options: [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->connection->exec(
                'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                    cache_key  TEXT PRIMARY KEY NOT NULL,
                    payload    TEXT NOT NULL,
                    stored_at  INTEGER NOT NULL,
                    expires_at INTEGER NOT NULL
                )'
            );
            $this->connection->exec(
                'CREATE INDEX IF NOT EXISTS idx_' . self::TABLE . '_expires ON ' . self::TABLE . ' (expires_at)'
            );
        } catch (PDOException $exception) {
            throw new RuntimeException("Falha ao abrir o cache SQLite em {$databasePath}: {$exception->getMessage()}", previous: $exception);
        }
    }

    /**
     * @return array<mixed>|null
     */
    #[\NoDiscard('o payload lido do cache precisa ser consumido; descartá-lo refaz a coleta sem motivo')]
    public function get(string $key): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT payload, expires_at FROM ' . self::TABLE . ' WHERE cache_key = :key LIMIT 1'
        );
        $statement->execute(['key' => $key]);

        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }

        if ((int)($row['expires_at'] ?? 0) <= time()) {
            $this->forget($key);
            return null;
        }

        try {
            $decoded = json_decode((string)($row['payload'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->forget($key);
            return null;
        }

        if (!is_array($decoded)) {
            $this->forget($key);
            return null;
        }

        /** @var array<mixed> $decoded */
        return $decoded;
    }

    #[\NoDiscard('consulte o retorno para decidir entre usar o cache ou coletar de novo')]
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * @param array<mixed> $payload
     */
    public function put(string $key, array $payload, ?int $ttlSeconds = null): void
    {
        $ttl = $ttlSeconds ?? $this->defaultTtlSeconds;

        $statement = $this->connection->prepare(
            'INSERT INTO ' . self::TABLE . ' (cache_key, payload, stored_at, expires_at)
             VALUES (:key, :payload, :stored_at, :expires_at)
             ON CONFLICT(cache_key) DO UPDATE SET
                payload    = excluded.payload,
                stored_at  = excluded.stored_at,
                expires_at = excluded.expires_at'
        );

        $statement->execute([
            'key'        => $key,
            'payload'    => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'stored_at'  => time(),
            'expires_at' => time() + $ttl,
        ]);
    }

    public function forget(string $key): void
    {
        $statement = $this->connection->prepare('DELETE FROM ' . self::TABLE . ' WHERE cache_key = :key');
        $statement->execute(['key' => $key]);
    }

    public function flush(): void
    {
        $this->connection->exec('DELETE FROM ' . self::TABLE);
    }

    /**
     * Remove entradas vencidas. Chamável por rotina de manutenção — o `get()`
     * já limpa sob demanda, então não é obrigatório.
     */
    public function purgeExpired(): int
    {
        $statement = $this->connection->prepare('DELETE FROM ' . self::TABLE . ' WHERE expires_at <= :now');
        $statement->execute(['now' => time()]);

        return $statement->rowCount();
    }
}
