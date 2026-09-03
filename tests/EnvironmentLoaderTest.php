<?php

declare(strict_types=1);

use LootRadar\Cli\EnvironmentLoader;

it('carrega variáveis do arquivo env sem sobrescrever valores existentes', function () {
    $directory = sys_get_temp_dir() . '/lootradar-env-test-' . bin2hex(random_bytes(6));
    expect(mkdir($directory))->toBeTrue();
    $path = $directory . '/.env';
    expect(file_put_contents(
        $path,
        "LOOTRADAR_DOTENV_NEW=from-file\nLOOTRADAR_DOTENV_EXISTING=from-file\n",
    ))->not->toBeFalse();

    $originalNewEnv = $_ENV['LOOTRADAR_DOTENV_NEW'] ?? null;
    $originalNewServer = $_SERVER['LOOTRADAR_DOTENV_NEW'] ?? null;
    $originalExistingEnv = $_ENV['LOOTRADAR_DOTENV_EXISTING'] ?? null;
    $originalExistingServer = $_SERVER['LOOTRADAR_DOTENV_EXISTING'] ?? null;
    $originalDotenvVarsEnv = $_ENV['SYMFONY_DOTENV_VARS'] ?? null;
    $originalDotenvVarsServer = $_SERVER['SYMFONY_DOTENV_VARS'] ?? null;

    try {
        unset($_ENV['LOOTRADAR_DOTENV_NEW'], $_SERVER['LOOTRADAR_DOTENV_NEW']);
        $_ENV['LOOTRADAR_DOTENV_EXISTING'] = 'from-process';
        $_SERVER['LOOTRADAR_DOTENV_EXISTING'] = 'from-process';

        EnvironmentLoader::load($directory);

        expect($_ENV['LOOTRADAR_DOTENV_NEW'] ?? null)->toBe('from-file')
            ->and($_SERVER['LOOTRADAR_DOTENV_NEW'] ?? null)->toBe('from-file')
            ->and($_ENV['LOOTRADAR_DOTENV_EXISTING'] ?? null)->toBe('from-process')
            ->and($_SERVER['LOOTRADAR_DOTENV_EXISTING'] ?? null)->toBe('from-process');
    } finally {
        restoreEnvironmentValue('LOOTRADAR_DOTENV_NEW', $originalNewEnv, $originalNewServer);
        restoreEnvironmentValue('LOOTRADAR_DOTENV_EXISTING', $originalExistingEnv, $originalExistingServer);
        restoreEnvironmentValue('SYMFONY_DOTENV_VARS', $originalDotenvVarsEnv, $originalDotenvVarsServer);
        unlink($path);
        rmdir($directory);
    }
});

it('ignora a ausência do arquivo env', function () {
    EnvironmentLoader::load(sys_get_temp_dir() . '/lootradar-env-missing-' . bin2hex(random_bytes(6)));

    expect(true)->toBeTrue();
});

function restoreEnvironmentValue(string $name, mixed $envValue, mixed $serverValue): void
{
    if ($envValue === null) {
        unset($_ENV[$name]);
    } else {
        $_ENV[$name] = $envValue;
    }

    if ($serverValue === null) {
        unset($_SERVER[$name]);
    } else {
        $_SERVER[$name] = $serverValue;
    }
}
