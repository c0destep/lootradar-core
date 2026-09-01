<?php

declare(strict_types=1);

use LootRadar\Services\ThemeManager;

it('carrega estilos dos arquivos JSON e lista os temas disponíveis', function () {
    expect(ThemeManager::getStylesByTheme('cyberpunk')['badge'])->toContain('bg-cyan-400')
        ->and(ThemeManager::getStylesByTheme('dracula')['badge'])->toContain('bg-pink-400')
        ->and(ThemeManager::availableThemes())->toContain('cyberpunk', 'dracula', 'default');
});

it('usa o tema padrão para nome desconhecido', function () {
    expect(ThemeManager::getStylesByTheme('does-not-exist')['bg'])->toBe('bg-black text-green-400');
});
