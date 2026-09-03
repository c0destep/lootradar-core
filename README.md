# LootRadar Core

**v0.2.0** — núcleo PHP 8.5+ para coletar, normalizar, filtrar e exibir
jogos gratuitos e promoções de lojas digitais.

Nesta versão, o Core consulta jogos gratuitos e promoções da Epic Games, ITAD,
Steam e GOG, mantém um cache local em JSON ou SQLite, oferece histórico de preços
e conversão de moeda, sanitiza URLs de checkout e disponibiliza uma CLI temática.

## Requisitos

- PHP 8.5 ou superior
- Composer 2
- Extensões `pdo` e `zlib`

## Instalação e uso

Clone o repositório e instale as dependências:

```bash
git clone https://github.com/c0destep/lootradar-core.git
cd lootradar-core
composer install
```

Liste os jogos grátis da Epic:

```bash
./bin/lootradar free
./bin/lootradar free --theme=cyberpunk
./bin/lootradar free --theme=dracula
```

O comando consulta o endpoint público da Epic na primeira execução e reutiliza
o cache temporário nas próximas doze horas.

### Uso como biblioteca

```php
use GuzzleHttp\Client;
use LootRadar\Adapters\EpicGamesAdapter;
use LootRadar\Cache\JsonCache;
use LootRadar\Services\RadarService;

$radar = new RadarService(
    new JsonCache(sys_get_temp_dir() . '/lootradar')
);
$radar->registerAdapter(new EpicGamesAdapter(new Client()));

foreach ($radar->getFreeGames() as $game) {
    echo $game['title'] . PHP_EOL;
}
```

As duas implementações de cache seguem o mesmo contrato:

- `JsonCache`: simples, portátil e padrão para CLI.
- `SqliteCache`: indicado para consultas de histórico e futuras integrações de wishlist.

## O que está incluído no v0.2.0

- Adapters defensivos da Epic Games, ITAD, Steam e GOG, com testes offline.
- `GameDeal`, `Money`, `PriceHistory` e `Theme` como DTOs imutáveis.
- Contratos para adapters de lojas, histórico de preços e taxas de câmbio.
- `RadarService` resiliente: falhas de uma loja não derrubam as outras.
- Pipeline de coleta, filtro, conversão de moeda e sanitização de URLs.
- Cache com TTL, compressão gzip opcional e SQLite.
- CLI `free` com os temas padrão, Cyberpunk e Dracula.
- Workflow de CI com lint, testes e análise estática.

O comando `deal` e as flags globais de moeda, score mínimo e desativação do cache
continuam previstos para as próximas versões. Veja o [ROADMAP.md](ROADMAP.md).

## Desenvolvimento

```bash
composer lint
composer test
composer analyse
```

### Credenciais locais e teste manual do ITAD

Crie o arquivo local de ambiente a partir do modelo versionado:

```bash
cp .env.example .env
```

Preencha `ITAD_API_KEY` em `.env`. O arquivo local é ignorado pelo Git e não deve
ser versionado. `ITAD_COUNTRY` e `ITAD_LIMIT` controlam o país e a quantidade de
promoções consultadas; `ITAD_GAME_ID` é opcional e habilita a consulta de histórico.

Execute o smoke test contra a API real:

```bash
composer test:itad-live
```

Os testes executados por `composer test` continuam totalmente offline e não usam
credenciais. O smoke test requer acesso à internet e consome a API do ITAD.

## Documentação

- [Roadmap de desenvolvimento](ROADMAP.md)
- [Histórico de alterações](CHANGELOG.md)
- [Licença MIT](LICENSE)

## Licença

Distribuído sob a Licença MIT.
