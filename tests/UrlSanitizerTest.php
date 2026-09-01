<?php

declare(strict_types=1);

use LootRadar\Services\UrlSanitizer;

it('remove tracking, redirect e credenciais de URLs seguras', function () {
    $sanitizer = new UrlSanitizer(allowedHostSuffixes: ['example.com']);

    expect($sanitizer->sanitize('https://user:pass@shop.example.com/game?utm_source=x&next=https%3A%2F%2Fevil.test&id=42'))
        ->toBe('https://shop.example.com/game?id=42');
});

it('rejeita esquemas perigosos, host vazio e host fora da allowlist', function (string $url) {
    $sanitizer = new UrlSanitizer(allowedHostSuffixes: ['example.com']);

    expect($sanitizer->sanitize($url))->toBeNull();
})->with([
    'javascript:alert(1)',
    'data:text/html,<script>alert(1)</script>',
    'file:///etc/passwd',
    'https://example.net/game',
]);
