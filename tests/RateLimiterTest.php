<?php

declare(strict_types=1);

use LootRadar\Exceptions\RateLimitExceededException;
use LootRadar\Services\InMemorySlidingWindowRateLimiter;
use LootRadar\Services\SqliteSlidingWindowRateLimiter;

it('aplica a proteção padrão em memória', function () {
    $now = 500.0;
    $limiter = new InMemorySlidingWindowRateLimiter(
        maxRequests: 1,
        windowSeconds: 300,
        clock: static function () use (&$now): float {
            return $now;
        },
    );

    $limiter->consume('itad:test-key');

    expect(fn() => $limiter->consume('itad:test-key'))
        ->toThrow(RateLimitExceededException::class);
});

it('bloqueia novas reservas quando a janela está cheia', function () {
    $now = 1_000.0;
    $limiter = new SqliteSlidingWindowRateLimiter(
        ':memory:',
        maxRequests: 2,
        windowSeconds: 300,
        clock: static function () use (&$now): float {
            return $now;
        },
    );

    $limiter->consume('itad:test-key');
    $limiter->consume('itad:test-key');

    $exception = null;
    try {
        $limiter->consume('itad:test-key');
    } catch (RateLimitExceededException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(RateLimitExceededException::class)
        ->and($exception?->retryAfterSeconds)->toBe(300);

    $now += 300.1;

    expect(fn() => $limiter->consume('itad:test-key'))->not->toThrow(RateLimitExceededException::class);
});

it('compartilha a janela SQLite entre instâncias', function () {
    $databasePath = sys_get_temp_dir() . '/lootradar-rate-limit-' . bin2hex(random_bytes(8)) . '.sqlite';

    try {
        $first = new SqliteSlidingWindowRateLimiter($databasePath, maxRequests: 1);
        $second = new SqliteSlidingWindowRateLimiter($databasePath, maxRequests: 1);

        $first->consume('itad:test-key');

        expect(fn() => $second->consume('itad:test-key'))
            ->toThrow(RateLimitExceededException::class);
    } finally {
        unset($first, $second);

        foreach ([$databasePath, $databasePath . '-journal', $databasePath . '-shm', $databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

it('compartilha suspensões informadas pelo provedor', function () {
    $now = 2_000.0;
    $limiter = new SqliteSlidingWindowRateLimiter(
        ':memory:',
        clock: static function () use (&$now): float {
            return $now;
        },
    );

    $limiter->suspend('itad:test-key', 42);

    $exception = null;
    try {
        $limiter->consume('itad:test-key');
    } catch (RateLimitExceededException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(RateLimitExceededException::class)
        ->and($exception?->retryAfterSeconds)->toBe(42);

    $now += 42;

    expect(fn() => $limiter->consume('itad:test-key'))->not->toThrow(RateLimitExceededException::class);
});
