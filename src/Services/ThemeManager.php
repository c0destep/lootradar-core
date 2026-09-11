<?php

declare(strict_types=1);

namespace LootRadar\Services;

use InvalidArgumentException;
use LootRadar\DTO\Theme;
use RuntimeException;

final class ThemeManager
{
    /**
     * @var array{
     *     bg: string,
     *     heading: string,
     *     badge: string,
     *     discountBadge: string,
     *     price: string,
     *     muted: string,
     *     historicalLow: string,
     *     link: string,
     *     warning: string,
     *     separator: string,
     *     border: string
     * }
     */
    private const array DEFAULT_STYLES = [
        'bg' => 'bg-black text-green-400',
        'heading' => 'text-green-400 font-bold',
        'badge' => 'bg-green-500 text-black font-bold px-2',
        'discountBadge' => 'bg-yellow-400 text-black font-bold px-1',
        'price' => 'text-green-400 font-bold',
        'muted' => 'text-gray-400',
        'historicalLow' => 'text-green-400 font-bold',
        'link' => 'text-blue-400 underline',
        'warning' => 'text-yellow-400',
        'separator' => 'text-gray-700',
        'border' => 'border-solid border-green-700',
    ];

    private const string THEME_DIRECTORY = __DIR__ . '/../../config/themes';

    /**
     * @return array{
     *     bg: string,
     *     heading: string,
     *     badge: string,
     *     discountBadge: string,
     *     price: string,
     *     muted: string,
     *     historicalLow: string,
     *     link: string,
     *     warning: string,
     *     separator: string,
     *     border: string
     * }
     */
    public static function getStylesByTheme(string $themeName): array
    {
        return self::getTheme($themeName)->styles;
    }

    public static function getTheme(string $themeName): Theme
    {
        if ($themeName === 'default') {
            return new Theme('default', 'Tema padrão do LootRadar.', self::DEFAULT_STYLES);
        }

        $path = self::themePath($themeName);
        if (!is_file($path)) {
            return new Theme('default', 'Tema padrão do LootRadar.', self::DEFAULT_STYLES);
        }

        return self::themeFromFile($path, $themeName);
    }

    public static function resolveTheme(string $themeName, ?string $themeFile = null): Theme
    {
        if ($themeFile !== null) {
            $themeFile = trim($themeFile);
            if ($themeFile === '') {
                throw new InvalidArgumentException('--theme-file requer o caminho de um arquivo JSON.');
            }

            if (!is_file($themeFile)) {
                throw new InvalidArgumentException("Arquivo de tema não encontrado: {$themeFile}");
            }

            try {
                return self::themeFromFile($themeFile, pathinfo($themeFile, PATHINFO_FILENAME));
            } catch (RuntimeException $exception) {
                throw new InvalidArgumentException($exception->getMessage(), previous: $exception);
            }
        }

        $normalizedName = strtolower(trim($themeName));
        if ($normalizedName !== 'default' && !is_file(self::themePath($normalizedName))) {
            $available = implode(', ', self::availableThemes());
            throw new InvalidArgumentException("Tema desconhecido: {$themeName}. Disponíveis: {$available}.");
        }

        return self::getTheme($normalizedName);
    }

    private static function themeFromFile(string $path, string $fallbackName): Theme
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Não foi possível ler o tema: {$path}");
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException("Tema inválido: {$path}", previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException("Tema inválido: {$path}");
        }

        $styles = is_array($decoded['styles'] ?? null) ? $decoded['styles'] : [];

        return new Theme(
            name: is_string($decoded['name'] ?? null) ? $decoded['name'] : $fallbackName,
            description: is_string($decoded['description'] ?? null) ? $decoded['description'] : null,
            styles: [
                'bg' => self::style($styles, 'bg'),
                'heading' => self::style($styles, 'heading'),
                'badge' => self::style($styles, 'badge'),
                'discountBadge' => self::style($styles, 'discountBadge'),
                'price' => self::style($styles, 'price'),
                'muted' => self::style($styles, 'muted'),
                'historicalLow' => self::style($styles, 'historicalLow'),
                'link' => self::style($styles, 'link'),
                'warning' => self::style($styles, 'warning'),
                'separator' => self::style($styles, 'separator'),
                'border' => self::style($styles, 'border'),
            ],
        );
    }

    /** @return list<string> */
    public static function availableThemes(): array
    {
        $themes = ['default'];
        foreach ((array)glob(self::THEME_DIRECTORY . '/*.json') as $path) {
            $name = pathinfo($path, PATHINFO_FILENAME);
            if ($name !== '' && !in_array($name, $themes, true)) {
                $themes[] = $name;
            }
        }
        sort($themes);
        return $themes;
    }

    /** @param array<string, mixed> $styles */
    private static function style(array $styles, string $key): string
    {
        $value = $styles[$key] ?? self::DEFAULT_STYLES[$key];
        return is_string($value) && trim($value) !== '' ? $value : self::DEFAULT_STYLES[$key];
    }

    private static function themePath(string $themeName): string
    {
        $normalized = strtolower(trim($themeName));
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $normalized) !== 1) {
            return '';
        }

        return self::THEME_DIRECTORY . '/' . $normalized . '.json';
    }
}
