<?php

declare(strict_types=1);

namespace LootRadar\Cli;

use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Opções globais já normalizadas na fronteira da CLI.
 */
final readonly class CliOptions
{
    public function __construct(
        public string $country = 'US',
        public string $locale = 'en-US',
        public ?string $currency = null,
        public int $minimumScore = 60,
        public bool $bypassCache = false,
    ) {
        if (preg_match('/^[A-Z]{2}$/', $this->country) !== 1) {
            throw new InvalidArgumentException('--country deve usar ISO 3166-1 alpha-2, por exemplo BR.');
        }

        if (preg_match('/^[a-z]{2}-[A-Z]{2}$/', $this->locale) !== 1) {
            throw new InvalidArgumentException('--locale deve usar o formato ll-RR, por exemplo pt-BR.');
        }

        if ($this->currency !== null && preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            throw new InvalidArgumentException('--currency deve usar ISO 4217, por exemplo BRL.');
        }

        if ($this->minimumScore < 0 || $this->minimumScore > 100) {
            throw new InvalidArgumentException('--min-score deve estar entre 0 e 100.');
        }
    }

    public static function fromInput(InputInterface $input): self
    {
        $country = self::requiredString(self::option($input, 'country', 'US'), 'country');
        $locale = self::normalizeLocale(self::requiredString(self::option($input, 'locale', 'en-US'), 'locale'));
        $currencyOption = self::option($input, 'currency');
        $currency = $currencyOption === null ? null : self::normalizeCurrency($currencyOption);
        $minimumScore = self::integer(self::option($input, 'min-score', '60'), 'min-score');
        $bypassCache = self::option($input, 'no-cache', false);

        if (!is_bool($bypassCache)) {
            throw new InvalidArgumentException('--no-cache não aceita valor.');
        }

        return new self(
            country: strtoupper($country),
            locale: $locale,
            currency: $currency,
            minimumScore: $minimumScore,
            bypassCache: $bypassCache,
        );
    }

    /**
     * @return array{country: string, locale: string, currency: string, minimum_score: int}
     */
    public function cacheContext(): array
    {
        return [
            'country' => $this->country,
            'locale' => $this->locale,
            'currency' => $this->currency ?? 'native',
            'minimum_score' => $this->minimumScore,
        ];
    }

    private static function option(InputInterface $input, string $name, mixed $default = null): mixed
    {
        try {
            return $input->getOption($name);
        } catch (InvalidArgumentException) {
            return $default;
        }
    }

    private static function requiredString(mixed $value, string $name): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("--{$name} requer um valor.");
        }

        return trim($value);
    }

    private static function normalizeCurrency(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('--currency requer um valor.');
        }

        return strtoupper(trim($value));
    }

    private static function normalizeLocale(string $locale): string
    {
        $parts = preg_split('/[-_]/', $locale);
        if (!is_array($parts) || count($parts) !== 2) {
            return $locale;
        }

        return strtolower($parts[0]) . '-' . strtoupper($parts[1]);
    }

    private static function integer(mixed $value, string $name): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (!is_string($value) || preg_match('/^\d+$/', $value) !== 1) {
            throw new InvalidArgumentException("--{$name} deve ser um número inteiro.");
        }

        return (int) $value;
    }
}
