<?php

declare(strict_types=1);

namespace LootRadar\Commands;

use LootRadar\Services\RadarService;
use LootRadar\Services\ThemeManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Termwind\render;

#[AsCommand(
    name: 'free',
    description: 'Lista todos os jogos gratuitos da semana nas lojas digitais.'
)]
class FreeGamesCommand extends Command
{
    public function __construct(private RadarService $radarService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'theme',
            't',
            InputOption::VALUE_OPTIONAL,
            'Define o tema visual (default, cyberpunk)',
            'default'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $themeOption = $input->getOption('theme');
        $theme = is_string($themeOption) ? $themeOption : 'default';
        $styles = ThemeManager::getStylesByTheme($theme);

        $games = $this->radarService->getFreeGames();

        $items = array_reduce($games, function (string $carry, array $game) use ($styles): string {
            $title = (string)($game['title'] ?? 'Desconhecido');
            $store = (string)($game['storeName'] ?? '');
            $url = (string)($game['checkoutUrl'] ?? '');

            return $carry . "
                <li class='mb-1'>
                    <span class='{$styles['badge']}'>FREE</span>
                    <b>{$title}</b> ({$store})
                    <br/><span class='text-gray-500'>Resgate em: {$url}</span>
                </li>";
        }, '');

        if ($items === '') {
            $items = "<li class='text-gray-500'>Nenhum jogo gratuito encontrado no momento.</li>";
        }

        // Obs.: o Termwind não renderiza bordas de caixa (border-solid/double/cor)
        // em <div>; o token {$styles['border']} fica disponível no ThemeManager
        // para outras camadas de UI. Aqui a separação visual usa <hr/>.
        render(
            "
            <div class='p-2 {$styles['bg']}'>
                <span class='font-bold'>LOOTRADAR - CENTRAL DE JOGOS GRATUITOS</span>
                <hr class='my-1'/>
                <ul>
                    {$items}
                </ul>
            </div>
        "
        );

        return Command::SUCCESS;
    }
}
