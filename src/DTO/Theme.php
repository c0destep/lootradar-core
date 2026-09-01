<?php

declare(strict_types=1);

namespace LootRadar\DTO;

use InvalidArgumentException;

/**
 * Tema de interface carregado da configuração JSON.
 */
readonly class Theme
{
    /**
     * @param array{bg: string, badge: string, border: string} $styles
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public array $styles,
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('O nome do tema não pode ser vazio.');
        }
    }
}
