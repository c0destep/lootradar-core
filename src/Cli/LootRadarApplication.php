<?php

declare(strict_types=1);

namespace LootRadar\Cli;

use LootRadar\Services\ThemeManager;
use Symfony\Component\Console\Application;

/**
 * Aplicação Console com uma apresentação completa dos recursos públicos da CLI.
 */
final class LootRadarApplication extends Application
{
    public function __construct(string $version)
    {
        parent::__construct('LootRadar', $version);
    }

    #[\Override]
    public function getHelp(): string
    {
        return parent::getHelp() . "\n\n" . self::capabilitiesHelp();
    }

    public static function capabilitiesHelp(): string
    {
        $themes = implode(', ', ThemeManager::availableThemes());

        return <<<HELP
            Encontre jogos gratuitos e promoções nas principais lojas digitais.

            Recursos principais:
              free  Consulta jogos gratuitos na Epic Games, na Steam e na GOG.
              deal  Consulta descontos na Steam e na GOG; o ITAD (IsThereAnyDeal) acrescenta ofertas e histórico quando configurado.
              snapshot  Exporta os dois conjuntos em JSON versionado para clientes Web.

            Temas disponíveis: {$themes}.
              Use --theme=<nome> depois de free ou deal.

            Opções globais permitem escolher região comercial, locale, moeda e score mínimo.
              Use --no-cache para consultar as fontes sem ler nem gravar o cache de ofertas.

            Exemplos:
              ./bin/lootradar free --country=BR --locale=pt-BR --theme=dracula
              ./bin/lootradar deal --top=5 --country=BR --currency=BRL --theme=cyberpunk
              ./bin/lootradar snapshot --top=50 --country=BR --currency=BRL > data/lootradar.json

            ITAD_API_KEY é opcional e habilita ofertas agregadas e dados sobre o menor preço histórico nos comandos deal e snapshot.
            Use ./bin/lootradar help <comando> para consultar todas as opções de um comando.
            HELP;
    }
}
