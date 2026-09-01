<?php

declare(strict_types=1);

namespace LootRadar\Services;

use Uri\WhatWg\Url;

/**
 * Higieniza URLs de checkout usando a extensão URI nativa do PHP 8.5.
 *
 * Por que a validação de esquema é explícita: `Uri\WhatWg\Url::parse()` aceita
 * `javascript:alert(1)` e `data:text/html,...` sem erro algum (verificado no
 * ambiente) — só devolve null para strings realmente malformadas. Parsear com
 * sucesso, portanto, NÃO significa que o link é seguro.
 *
 * O que este serviço faz:
 *  - só libera esquemas de uma allowlist (padrão: https, http);
 *  - exige host real (bloqueia `file:///`, cujo host é string vazia);
 *  - remove credenciais embutidas (`https://user:pw@host` → phishing);
 *  - remove parâmetros de tracking e de redirecionamento aberto;
 *  - serializa em ASCII/punycode, neutralizando hosts homóglifos (IDN).
 */
final class UrlSanitizer
{
    /** Parâmetros de rastreamento e afiliação — ruído, sem valor para o usuário. */
    public const array TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'utm_name',
        'fbclid', 'gclid', 'gbraid', 'wbraid', 'msclkid', 'twclid', 'igshid', 'ttclid', 'yclid',
        'mc_cid', 'mc_eid', '_hsenc', '_hsmi', 'vero_id', 'oly_anon_id', 'oly_enc_id',
        'ref', 'ref_', 'referrer', 'affiliate', 'affiliate_id', 'aff', 'aff_id', 'partner', 'tag',
        'epic_affiliate', 'epic_gameId', 'snr',
    ];

    /**
     * Parâmetros usados em redirecionamento aberto: mesmo num host legítimo,
     * carregam um destino arbitrário. São o vetor mais comum de abuso.
     */
    public const array REDIRECT_PARAMS = [
        'redirect', 'redirect_to', 'redirect_uri', 'redirect_url', 'redirecturl',
        'return', 'returnurl', 'return_url', 'return_to', 'returnto',
        'next', 'url', 'target', 'dest', 'destination', 'continue', 'goto', 'out', 'link', 'to',
    ];

    private const array DEFAULT_ALLOWED_SCHEMES = ['https', 'http'];

    /** @var list<string> */
    private array $allowedSchemes;

    /** @var list<string> */
    private array $strippedParams;

    /** @var list<string> */
    private array $allowedHostSuffixes;

    /**
     * @param list<string>|null $allowedSchemes      Esquemas liberados; null usa https/http.
     * @param list<string>      $allowedHostSuffixes Se não vazio, o host precisa terminar em um destes (allowlist de lojas).
     * @param list<string>|null $strippedParams      Parâmetros removidos; null usa tracking + redirect.
     */
    public function __construct(
        ?array $allowedSchemes = null,
        array $allowedHostSuffixes = [],
        ?array $strippedParams = null,
    ) {
        $this->allowedSchemes = array_values(array_map(
            strtolower(...),
            $allowedSchemes ?? self::DEFAULT_ALLOWED_SCHEMES
        ));
        $this->allowedHostSuffixes = array_values(array_map(strtolower(...), $allowedHostSuffixes));
        $this->strippedParams = array_values(array_map(
            strtolower(...),
            $strippedParams ?? [...self::TRACKING_PARAMS, ...self::REDIRECT_PARAMS]
        ));
    }

    /**
     * Devolve a URL higienizada, ou null quando ela não é confiável.
     *
     * #[\NoDiscard] é intencional: descartar o retorno significa, na prática,
     * seguir usando a URL crua — exatamente o bug que este serviço evita.
     */
    #[\NoDiscard('use a URL higienizada retornada; descartá-la mantém a URL crua em uso')]
    public function sanitize(string $url): ?string
    {
        $candidate = trim($url);
        if ($candidate === '') {
            return null;
        }

        $errors = [];
        $parsed = Url::parse($candidate, null, $errors);
        if ($parsed === null) {
            return null;
        }

        if (!in_array(strtolower($parsed->getScheme()), $this->allowedSchemes, true)) {
            return null;
        }

        // `file:///etc/passwd` tem host string vazia; esquemas opacos (mailto:) têm host null.
        $host = $parsed->getAsciiHost();
        if ($host === null || $host === '' || !$this->isHostAllowed($host)) {
            return null;
        }

        // A ordem importa: limpar só o usuário deixaria um resíduo `:senha@`.
        $clean = $parsed->withUsername(null)->withPassword(null);

        $query = $clean->getQuery();
        if ($query !== null && $query !== '') {
            $filtered = $this->filterQuery($query);
            $clean = $clean->withQuery($filtered === '' ? null : $filtered);
        }

        // toAsciiString() força punycode no host: `stýre.example.com` sai como
        // `xn--stre-6ra.example.com`, sem parecer visualmente com o domínio real.
        return $clean->toAsciiString();
    }

    #[\NoDiscard('o resultado da checagem precisa decidir se o link é exibido')]
    public function isSafe(string $url): bool
    {
        return $this->sanitize($url) !== null;
    }

    /**
     * Preserva ordem e duplicatas dos parâmetros mantidos — `parse_str()` faria
     * o oposto: colapsa duplicatas e reescreve nomes com `.`/`[]`.
     */
    private function filterQuery(string $query): string
    {
        $kept = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            $name = strtolower(urldecode(str_contains($pair, '=') ? strstr($pair, '=', true) : $pair));

            if (!in_array($name, $this->strippedParams, true)) {
                $kept[] = $pair;
            }
        }

        return implode('&', $kept);
    }

    private function isHostAllowed(string $host): bool
    {
        if ($this->allowedHostSuffixes === []) {
            return true;
        }

        $host = strtolower($host);

        // `array_any()` do PHP 8.5 expressa "existe algum sufixo que casa?" sem loop manual.
        return array_any(
            $this->allowedHostSuffixes,
            static fn(string $suffix): bool => $host === $suffix || str_ends_with($host, '.' . $suffix)
        );
    }
}
