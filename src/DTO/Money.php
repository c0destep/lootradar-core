<?php

declare(strict_types=1);

namespace LootRadar\DTO;

use InvalidArgumentException;

/**
 * Valor monetário em uma moeda ISO 4217.
 *
 * O Core recebe preços de APIs externas; por isso a validação fica na borda do
 * domínio e impede que valores negativos, infinitos ou moedas malformadas
 * cheguem aos adapters e serviços.
 */
readonly class Money
{
    public float $amount;

    public string $currency;

    public function __construct(float $amount, string $currency)
    {
        $normalizedCurrency = strtoupper(trim($currency));
        if (!is_finite($amount) || $amount < 0.0) {
            throw new InvalidArgumentException('O valor monetário deve ser finito e não negativo.');
        }

        if (preg_match('/^[A-Z]{3}$/', $normalizedCurrency) !== 1) {
            throw new InvalidArgumentException('A moeda deve ser um código ISO 4217 de três letras.');
        }

        $this->amount = $amount;
        $this->currency = $normalizedCurrency;
    }

    /**
     * @return array{amount: float, currency: string}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
