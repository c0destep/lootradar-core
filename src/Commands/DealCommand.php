<?php

declare(strict_types=1);

namespace LootRadar\Commands;

use InvalidArgumentException;
use LogicException;
use LootRadar\Cli\CliOptions;
use LootRadar\Cli\CliRadarFactoryInterface;
use LootRadar\Services\ThemeManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Termwind\render;
use function Termwind\renderUsing;

#[AsCommand(
    name: 'deal',
    description: 'Lista as maiores promoções disponíveis pelo IsThereAnyDeal.'
)]
final class DealCommand extends Command
{
    public function __construct(private readonly CliRadarFactoryInterface $radarFactory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'top',
            null,
            InputOption::VALUE_REQUIRED,
            'Quantidade de promoções exibidas (1–200).',
            '10',
        );
        $this->addOption(
            'theme',
            't',
            InputOption::VALUE_OPTIONAL,
            'Define o tema visual (default, cyberpunk, dracula).',
            'default',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $options = CliOptions::fromInput($input);
            $top = self::top($input->getOption('top'));
            $radarService = $this->radarFactory->createDealRadar($options, $top);
        } catch (InvalidArgumentException $exception) {
            $output->writeln('<error>' . self::escape($exception->getMessage()) . '</error>');

            return Command::INVALID;
        } catch (LogicException $exception) {
            $output->writeln('<error>' . self::escape($exception->getMessage()) . '</error>');

            return Command::FAILURE;
        }

        $themeOption = $input->getOption('theme');
        $theme = is_string($themeOption) ? $themeOption : 'default';
        $styles = ThemeManager::getStylesByTheme($theme);
        $deals = $radarService->getTopDeals($top, $options->bypassCache);

        $rows = '';
        foreach ($deals as $index => $deal) {
            $title = self::escape(self::stringValue($deal, 'title', 'Desconhecido'));
            $store = self::escape(self::stringValue($deal, 'storeName'));
            $discount = (int) ($deal['discountPercentage'] ?? 0);
            $price = self::escape(self::price($deal, 'currentPrice'));
            $history = self::escape(self::price($deal, 'historicalLow', '—'));
            $url = self::escape(self::stringValue($deal, 'checkoutUrl'));
            $historicalStatus = ($deal['isAtHistoricalLow'] ?? false) === true ? ' — MENOR PREÇO' : '';

            $rows .= "
                <tr>
                    <td>" . ($index + 1) . "</td>
                    <td><b>{$title}</b><br/><span class='text-gray-500'>{$url}</span></td>
                    <td>{$store}</td>
                    <td>{$discount}%</td>
                    <td>{$price}</td>
                    <td>{$history}{$historicalStatus}</td>
                </tr>";
        }

        if ($rows === '') {
            $rows = "<tr><td colspan='6'>Nenhuma promoção encontrada no momento.</td></tr>";
        }

        $failureItems = array_reduce(
            $radarService->getFailures(),
            static fn(string $carry, string $failure): string => $carry
                . '<li>' . self::escape($failure) . '</li>',
            '',
        );
        $failures = $failureItems === '' ? '' : "
            <div class='mt-1 text-yellow-400'>
                <span class='font-bold'>Fontes indisponíveis nesta consulta:</span>
                <ul>{$failureItems}</ul>
            </div>";

        $backgroundStyles = self::escape((string) $styles['bg']);
        renderUsing($output);
        try {
            render(
                "
                <div class='p-2 {$backgroundStyles}'>
                    <span class='font-bold'>LOOTRADAR — MAIORES DESCONTOS</span>
                    <hr class='my-1'/>
                    <table>
                        <thead>
                            <tr><th>#</th><th>Jogo</th><th>Loja</th><th>Desconto</th><th>Preço</th><th>Histórico</th></tr>
                        </thead>
                        <tbody>{$rows}</tbody>
                    </table>
                    {$failures}
                </div>
            ",
            );
        } finally {
            renderUsing(null);
        }

        return Command::SUCCESS;
    }

    private static function top(mixed $value): int
    {
        if ((!is_int($value) && !(is_string($value) && preg_match('/^\d+$/', $value) === 1))) {
            throw new InvalidArgumentException('--top deve ser um número inteiro entre 1 e 200.');
        }

        $top = (int) $value;
        if ($top < 1 || $top > 200) {
            throw new InvalidArgumentException('--top deve estar entre 1 e 200.');
        }

        return $top;
    }

    /** @param array<string, mixed> $deal */
    private static function price(array $deal, string $key, string $fallback = ''): string
    {
        $amount = $deal[$key] ?? null;
        if (!is_int($amount) && !is_float($amount)) {
            return $fallback;
        }

        $currency = is_string($deal['currency'] ?? null) ? $deal['currency'] : '';

        return trim(number_format((float) $amount, 2, ',', '.') . ' ' . $currency);
    }

    /** @param array<string, mixed> $deal */
    private static function stringValue(array $deal, string $key, string $fallback = ''): string
    {
        $value = $deal[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
