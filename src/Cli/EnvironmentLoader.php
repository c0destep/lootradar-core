<?php

declare(strict_types=1);

namespace LootRadar\Cli;

use Symfony\Component\Dotenv\Dotenv;

/**
 * Carrega a configuração local da CLI sem sobrescrever valores já definidos.
 */
final class EnvironmentLoader
{
    public static function load(string $projectDirectory): void
    {
        $path = rtrim($projectDirectory, '/\\') . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($path)) {
            return;
        }

        (new Dotenv())->load($path);
    }
}
