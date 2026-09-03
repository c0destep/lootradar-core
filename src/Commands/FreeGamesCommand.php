<?php

declare(strict_types=1);

namespace LootRadar\Commands;

use InvalidArgumentException;
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
    name: 'free',
    description: 'Lista jogos gratuitos da Epic Games, Steam e GOG.'
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
        )->setHelp(<<<'HELP'
            Consulta jogos gratuitos na Epic Games, Steam e GOG. Uma fonte indisponível não interrompe as demais.

            Exemplos:
              ./bin/lootradar free --country=BR --locale=pt-BR
              ./bin/lootradar free --theme=dracula --no-cache
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $options = CliOptions::fromInput($input);
        } catch (InvalidArgumentException $exception) {
            $output->writeln('<error>' . self::escape($exception->getMessage()) . '</error>');

            return Command::INVALID;
        }

        $themeOption = $input->getOption('theme');
        $theme = is_string($themeOption) ? $themeOption : 'default';
        $styles = ThemeManager::getStylesByTheme($theme);

        $radarService = $this->radarFactory->createFreeRadar($options);
        $games = $radarService->getFreeGames($options->bypassCache);

        $items = array_reduce($games, function (string $carry, array $game) use ($styles): string {
            $badgeStyles = self::escape((string)$styles['badge']);
            $title = self::escape((string)($game['title'] ?? 'Desconhecido'));
            $store = self::escape((string)($game['storeName'] ?? ''));
            $url = self::escape((string)($game['checkoutUrl'] ?? ''));

            return $carry . "
                <li class='mb-1'>
                    <span class='{$badgeStyles}'>GRÁTIS</span>
                    <b>{$title}</b> ({$store})
                    <br/><span class='text-gray-500'>Resgate em: {$url}</span>
                </li>";
        }, '');

        if ($items === '') {
            $items = "<li class='text-gray-500'>Nenhum jogo gratuito encontrado no momento.</li>";
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

        // Obs.: o Termwind não renderiza bordas de caixa (border-solid/double/cor)
        // em <div>; o token {$styles['border']} fica disponível no ThemeManager
        // para outras camadas de UI. Aqui a separação visual usa <hr/>.
        $backgroundStyles = self::escape((string)$styles['bg']);
        renderUsing($output);
        try {
            render(
                "
                <div class='p-2 {$backgroundStyles}'>
                    <span class='font-bold'>LOOTRADAR — JOGOS GRATUITOS</span>
                    <hr class='my-1'/>
                    <ul>
                        {$items}
                    </ul>
                    {$failures}
                </div>
            "
            );
        } finally {
            renderUsing(null);
        }

        return Command::SUCCESS;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
