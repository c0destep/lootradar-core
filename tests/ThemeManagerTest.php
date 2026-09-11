<?php

declare(strict_types=1);

use LootRadar\Services\ThemeManager;

it('carrega estilos dos arquivos JSON e lista os temas disponíveis', function () {
    expect(ThemeManager::getStylesByTheme('cyberpunk')['badge'])->toContain('bg-cyan-400')
        ->and(ThemeManager::getStylesByTheme('dracula')['badge'])->toContain('bg-pink-400')
        ->and(ThemeManager::getStylesByTheme('cyberpunk')['heading'])->toContain('text-yellow-400')
        ->and(ThemeManager::getStylesByTheme('dracula')['historicalLow'])->toContain('text-green-400')
        ->and(ThemeManager::availableThemes())->toContain('cyberpunk', 'dracula', 'default');
});

it('usa o tema padrão para nome desconhecido', function () {
    expect(ThemeManager::getStylesByTheme('does-not-exist')['bg'])->toBe('bg-black text-green-400');
});

it('carrega tema customizado e completa tokens semânticos ausentes', function () {
    $path = tempnam(sys_get_temp_dir(), 'lootradar-theme-');
    expect($path)->not->toBeFalse();

    try {
        file_put_contents($path, json_encode([
            'name' => 'amber',
            'description' => 'Tema local de teste.',
            'styles' => [
                'bg' => 'bg-black text-yellow-400',
                'badge' => 'bg-yellow-400 text-black px-1',
                'border' => 'border-solid border-yellow-400',
                'heading' => 'text-yellow-400 font-bold',
            ],
        ], JSON_THROW_ON_ERROR));

        $theme = ThemeManager::resolveTheme('default', $path);

        expect($theme->name)->toBe('amber')
            ->and($theme->styles['heading'])->toBe('text-yellow-400 font-bold')
            ->and($theme->styles['muted'])->toBe('text-gray-400')
            ->and($theme->styles['link'])->toBe('text-blue-400 underline');
    } finally {
        unlink($path);
    }
});

it('rejeita tema nomeado desconhecido e arquivo customizado ausente', function () {
    expect(fn() => ThemeManager::resolveTheme('unknown'))->toThrow(
        InvalidArgumentException::class,
        'Tema desconhecido: unknown',
    )->and(fn() => ThemeManager::resolveTheme('default', '/tmp/lootradar-theme-missing.json'))->toThrow(
        InvalidArgumentException::class,
        'Arquivo de tema não encontrado',
    );
});
