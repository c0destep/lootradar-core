<?php

declare(strict_types=1);

namespace LootRadar\Cache;

use JsonException;
use LootRadar\Contracts\CacheInterface;
use RuntimeException;

/**
 * Cache em arquivos JSON — um arquivo por chave.
 *
 * Cada arquivo guarda um envelope {stored_at, expires_at, payload}: a validade
 * não depende do mtime, então copiar ou restaurar o diretório de cache não
 * "revive" entradas vencidas.
 *
 * A compressão gzip é opcional. Não há criptografia por decisão de projeto
 * (ROADMAP §4): são dados públicos de promoções.
 */
final class JsonCache implements CacheInterface
{
    private const string FILE_SUFFIX = '.lrcache.json';

    private string $directory;

    public function __construct(
        string $directory,
        private readonly int $defaultTtlSeconds = 43200,
        private readonly bool $compress = false,
    ) {
        $this->directory = rtrim($directory, '/\\');

        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException("Não foi possível criar o diretório de cache: {$this->directory}");
        }
    }

    /**
     * @return array<mixed>|null
     */
    #[\NoDiscard('o payload lido do cache precisa ser consumido; descartá-lo refaz a coleta sem motivo')]
    public function get(string $key): ?array
    {
        return $this->readEnvelope($key)['payload'] ?? null;
    }

    #[\NoDiscard('consulte o retorno para decidir entre usar o cache ou coletar de novo')]
    public function has(string $key): bool
    {
        return $this->readEnvelope($key) !== null;
    }

    /**
     * @param array<mixed> $payload
     */
    public function put(string $key, array $payload, ?int $ttlSeconds = null): void
    {
        $ttl = $ttlSeconds ?? $this->defaultTtlSeconds;

        $envelope = [
            'key'        => $key,
            'stored_at'  => time(),
            'expires_at' => time() + $ttl,
            'payload'    => $payload,
        ];

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        if (!$this->compress) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $encoded = json_encode($envelope, $flags);
        $contents = $this->compress ? (string)gzencode($encoded, 6) : $encoded;

        // Escrita atômica: um leitor concorrente nunca vê um arquivo pela metade.
        $target = $this->pathFor($key, $this->compress);
        $temporary = $target . '.tmp' . getmypid();

        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Falha ao escrever o cache em: {$target}");
        }
        if (!rename($temporary, $target)) {
            @unlink($temporary);
            throw new RuntimeException("Falha ao mover o cache para: {$target}");
        }

        // Remove a variante com a outra compressão para não deixar entrada órfã
        // quando o modo `compress` muda entre execuções.
        $counterpart = $this->pathFor($key, !$this->compress);
        if (is_file($counterpart)) {
            @unlink($counterpart);
        }
    }

    public function forget(string $key): void
    {
        foreach ([$this->pathFor($key, false), $this->pathFor($key, true)] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function flush(): void
    {
        $pattern = $this->directory . DIRECTORY_SEPARATOR . '*' . self::FILE_SUFFIX . '*';
        foreach ((array)glob($pattern) as $path) {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Lê e valida o envelope, apagando a entrada quando expirada ou corrompida.
     *
     * @return array{expires_at: int, payload: array<mixed>}|null
     */
    private function readEnvelope(string $key): ?array
    {
        foreach ([$this->pathFor($key, $this->compress), $this->pathFor($key, !$this->compress)] as $path) {
            if (!is_file($path)) {
                continue;
            }

            $raw = file_get_contents($path);
            if ($raw === false || $raw === '') {
                @unlink($path);
                continue;
            }

            // Detecta gzip pelo magic number, não pelo nome do arquivo.
            if (str_starts_with($raw, "\x1f\x8b")) {
                $inflated = @gzdecode($raw);
                if ($inflated === false) {
                    @unlink($path);
                    continue;
                }
                $raw = $inflated;
            }

            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                @unlink($path);
                continue;
            }

            if (!is_array($decoded) || !is_int($decoded['expires_at'] ?? null) || !is_array($decoded['payload'] ?? null)) {
                @unlink($path);
                continue;
            }

            if ($decoded['expires_at'] <= time()) {
                @unlink($path);
                continue;
            }

            /** @var array<mixed> $payload */
            $payload = $decoded['payload'];

            return ['expires_at' => $decoded['expires_at'], 'payload' => $payload];
        }

        return null;
    }

    private function pathFor(string $key, bool $compressed): string
    {
        // Slug legível para inspeção manual + hash para garantir unicidade.
        $slug = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '-', $key));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'key';
        }
        $slug = substr($slug, 0, 48) . '-' . substr(sha1($key), 0, 8);

        return $this->directory . DIRECTORY_SEPARATOR . $slug . self::FILE_SUFFIX . ($compressed ? '.gz' : '');
    }
}
