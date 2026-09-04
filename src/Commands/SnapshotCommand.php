<?php

declare(strict_types=1);

namespace LootRadar\Commands;

use InvalidArgumentException;
use JsonException;
use LogicException;
use LootRadar\Cli\CliOptions;
use LootRadar\Cli\CliRadarFactoryInterface;
use LootRadar\Services\JsonSnapshotExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'snapshot',
    description: 'Exporta jogos gratuitos e promoções em JSON para clientes Web.'
)]
final class SnapshotCommand extends Command
{
    public function __construct(
        private readonly CliRadarFactoryInterface $radarFactory,
        private readonly JsonSnapshotExporter $exporter = new JsonSnapshotExporter(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'top',
            null,
            InputOption::VALUE_REQUIRED,
            'Quantidade de promoções incluídas (1–200).',
            '50',
        )->setHelp(<<<'HELP'
            Gera em stdout um documento JSON versionado para consumo pelo PWA ou por outro cliente Web.
            O snapshot reúne jogos gratuitos, maiores promoções e o estado de integridade das fontes.
            Este comando requer ITAD_API_KEY no arquivo .env ou no ambiente do processo.

            Exemplo:
              ./bin/lootradar snapshot --top=50 --country=BR --currency=BRL > data/lootradar.json
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $options = CliOptions::fromInput($input);
            $top = self::top($input->getOption('top'));
            $freeRadar = $this->radarFactory->createFreeRadar($options);
            $dealRadar = $this->radarFactory->createDealRadar($options, $top);

            $freeGames = $freeRadar->getFreeGames($options->bypassCache);
            $deals = $dealRadar->getTopDeals($top, $options->bypassCache);
            $json = $this->exporter->export(
                freeGames: $freeGames,
                deals: $deals,
                complete: [
                    'freeGames' => $freeRadar->getFailures() === [],
                    'deals' => $dealRadar->getFailures() === [],
                ],
                context: [
                    'country' => $options->country,
                    'locale' => $options->locale,
                    'currency' => $options->currency ?? 'native',
                    'minimumScore' => $options->minimumScore,
                ],
            );
        } catch (InvalidArgumentException $exception) {
            $output->writeln('<error>' . self::escape($exception->getMessage()) . '</error>');

            return Command::INVALID;
        } catch (LogicException|JsonException $exception) {
            $output->writeln('<error>' . self::escape($exception->getMessage()) . '</error>');

            return Command::FAILURE;
        }

        $output->writeln($json);

        return Command::SUCCESS;
    }

    private static function top(mixed $value): int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^\d+$/', $value) === 1)) {
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
