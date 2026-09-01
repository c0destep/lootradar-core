<?php

declare(strict_types=1);

namespace LootRadar\Contracts;

/**
 * Contrato de cache do Core.
 *
 * Implementações guardam apenas payloads já serializáveis (arrays puros), nunca
 * objetos — é o que permite trocar JSON por SQLite sem impacto em quem consome.
 *
 * Nota de PHP 8.5: o atributo #[\NoDiscard] declarado aqui NÃO seria herdado
 * pelas classes concretas (verificado no ambiente), por isso cada implementação
 * repete o atributo em `get()`.
 */
interface CacheInterface
{
    /**
     * Payload guardado, ou null quando ausente ou expirado.
     *
     * @return array<mixed>|null
     */
    public function get(string $key): ?array;

    public function has(string $key): bool;

    /**
     * @param array<mixed> $payload
     * @param int|null     $ttlSeconds TTL específico desta escrita; null usa o TTL padrão da instância.
     */
    public function put(string $key, array $payload, ?int $ttlSeconds = null): void;

    public function forget(string $key): void;

    public function flush(): void;
}
