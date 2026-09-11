<?php

declare(strict_types=1);

namespace LootRadar\Commands;

use InvalidArgumentException;
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
    name: 'free',
    description: 'Lista jogos gratuitos da Epic Games, da Steam e da GOG.'
)]
class FreeGamesCommand extends Command
{
    public function __construct(private readonly CliRadarFactoryInterface $radarFactory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $themes = implode(', ', ThemeManager::availableThemes());

        $this->addOption(
            'theme',
            't',
            InputOption::VALUE_OPTIONAL,
            "Define o tema visual ({$themes}).",
            'default'
        )->addOption(
            'theme-file',
            null,
            InputOption::VALUE_REQUIRED,
            'Carrega um tema customizado de um arquivo JSON; tem prioridade sobre --theme.',
        )->setHelp(<<<'HELP'
            Consulta jogos gratuitos na Epic Games, na Steam e na GOG. Uma fonte indisponível não interrompe as demais.

            Exemplos:
              ./bin/lootradar free --country=BR --locale=pt-BR
              ./bin/lootradar free --theme=dracula --no-cache
              ./bin/lootradar free --theme-file=meu-tema.json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $options = CliOptions::fromInput($input);
            $themeOption = $input->getOption('theme');
            $themeName = is_string($themeOption) ? $themeOption : 'default';
            $themeFileOption = $input->getOption('theme-file');
            $themeFile = is_string($themeFileOption) ? $themeFileOption : null;
            $theme = ThemeManager::resolveTheme($themeName, $themeFile);
        } catch (InvalidArgumentException $exception) {
            $output->writeln('<error>' . self::escape($exception->getMessage()) . '</error>');

            return Command::INVALID;
        }

        $radarService = $this->radarFactory->createFreeRadar($options);
        $games = $radarService->getFreeGames($options->bypassCache);

        (new OfferListRenderer($output, $theme))->renderFreeGames(
            $games,
            $radarService->getFailures(),
            $options,
        );

        return Command::SUCCESS;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
