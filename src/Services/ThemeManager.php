<?php

declare(strict_types=1);

namespace LootRadar\Services;

use LootRadar\DTO\Theme;
use RuntimeException;

final class ThemeManager
{
    /** @var array{bg: string, badge: string, border: string} */
    private const array DEFAULT_STYLES = [
        'bg' => 'bg-black text-green-400',
        'badge' => 'bg-green-500 text-black font-bold px-2',
        'border' => 'border-solid border-green-700',
    ];

    private const string THEME_DIRECTORY = __DIR__ . '/../../config/themes';

    /**
     * @return array{bg: string, badge: string, border: string}
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

        $path = self::THEME_DIRECTORY . '/' . strtolower($themeName) . '.json';
        if (!is_file($path)) {
            return new Theme('default', 'Tema padrão do LootRadar.', self::DEFAULT_STYLES);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Não foi possível ler o tema: {$themeName}");
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException("Tema inválido: {$themeName}", previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException("Tema inválido: {$themeName}");
        }

        $styles = is_array($decoded['styles'] ?? null) ? $decoded['styles'] : [];

        return new Theme(
            name: is_string($decoded['name'] ?? null) ? $decoded['name'] : $themeName,
            description: is_string($decoded['description'] ?? null) ? $decoded['description'] : null,
            styles: [
                'bg' => self::style($styles, 'bg'),
                'badge' => self::style($styles, 'badge'),
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
}
