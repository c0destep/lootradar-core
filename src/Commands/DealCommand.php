<?php

declare(strict_types=1);

namespace LootRadar\Commands;

use InvalidArgumentException;
use LogicException;
use LootRadar\Cli\CliOptions;
use LootRadar\Cli\CliRadarFactoryInterface;
use LootRadar\Cli\Presentation\OfferListRenderer;
use LootRadar\Services\ThemeManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'deal',
    description: 'Lista os maiores descontos da Steam e da GOG, com ITAD opcional.'
)]
final class DealCommand extends Command
{
    public function __construct(private readonly CliRadarFactoryInterface $radarFactory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $themes = implode(', ', ThemeManager::availableThemes());

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
            "Define o tema visual ({$themes}).",
            'default',
        );
        $this->addOption(
            'theme-file',
            null,
            InputOption::VALUE_REQUIRED,
            'Carrega um tema customizado de um arquivo JSON; tem prioridade sobre --theme.',
        )->setHelp(<<<'HELP'
            Consulta as maiores promoções diretamente na Steam e na GOG.
            Quando ITAD_API_KEY está definida, inclui também ofertas agregadas e dados sobre o menor preço histórico.

            Exemplos:
              ./bin/lootradar deal --top=5 --country=BR
              ./bin/lootradar deal --top=20 --currency=BRL --theme=cyberpunk --no-cache
              ./bin/lootradar deal --top=5 --theme-file=meu-tema.json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $options = CliOptions::fromInput($input);
            $top = self::top($input->getOption('top'));
            $themeOption = $input->getOption('theme');
            $themeName = is_string($themeOption) ? $themeOption : 'default';
            $themeFileOption = $input->getOption('theme-file');
            $themeFile = is_string($themeFileOption) ? $themeFileOption : null;
            $theme = ThemeManager::resolveTheme($themeName, $themeFile);
            $radarService = $this->radarFactory->createDealRadar($options, $top);
        } catch (InvalidArgumentException $exception) {
            $output->writeln('<error>' . self::escape($exception->getMessage()) . '</error>');

            return Command::INVALID;
        } catch (LogicException $exception) {
            $output->writeln('<error>' . self::escape($exception->getMessage()) . '</error>');

            return Command::FAILURE;
        }

        $deals = $radarService->getTopDeals($top, $options->bypassCache);

        (new OfferListRenderer($output, $theme))->renderDeals(
            $deals,
            $radarService->getFailures(),
            $options,
        );

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

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
