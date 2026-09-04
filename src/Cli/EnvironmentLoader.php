<?php

declare(strict_types=1);

namespace LootRadar\Cli;

use Symfony\Component\Dotenv\Dotenv;

/**
 * Carrega a configuração local da CLI sem sobrescrever valores já definidos.
 */
final class EnvironmentLoader
{
    /**
     * Carrega o `.env` da aplicação consumidora quando a CLI é executada pelo
     * proxy de binários do Composer; no checkout do Core, usa a raiz do pacote.
     */
    public static function loadForBinary(string $packageDirectory, ?string $composerAutoloadPath): void
    {
        $projectDirectory = $composerAutoloadPath === null || trim($composerAutoloadPath) === ''
            ? $packageDirectory
            : dirname($composerAutoloadPath, 2);

        self::load($projectDirectory);
    }

    public static function load(string $projectDirectory): void
    {
        $path = rtrim($projectDirectory, '/\\') . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($path)) {
            return;
        }

        (new Dotenv())->load($path);
    }
}
