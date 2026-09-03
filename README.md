# LootRadar Core

Núcleo PHP 8.5+ para coletar, normalizar, filtrar e exibir
jogos gratuitos e promoções de lojas digitais.

Nesta versão, o Core consulta jogos gratuitos e promoções da Epic Games, ITAD,
Steam e GOG, mantém um cache local em JSON ou SQLite, oferece histórico de preços
e conversão de moeda, sanitiza URLs de checkout e disponibiliza uma CLI temática.

## Requisitos

- PHP 8.5 ou superior
- Composer 2
- Extensões `pdo`, `pdo_sqlite` e `zlib`

## Instalação e uso

Clone o repositório e instale as dependências:

```bash
git clone https://github.com/c0destep/lootradar-core.git
cd lootradar-core
composer install
```

Liste os jogos gratuitos disponíveis nas fontes públicas:

```bash
./bin/lootradar free
./bin/lootradar free --theme=cyberpunk
./bin/lootradar free --theme=dracula
```

Liste os maiores descontos do ITAD depois de exportar a chave da API:

```bash
export ITAD_API_KEY='sua-chave'
./bin/lootradar deal --top=5 --country=BR --locale=pt-BR
./bin/lootradar deal --top=5 --country=BR --currency=BRL --theme=cyberpunk
```

Os dois comandos aceitam as opções globais `--currency`, `--country`, `--locale`,
`--min-score` e `--no-cache`. O país define a região comercial dos preços, enquanto
o locale configura o idioma das fontes compatíveis. A opção `--currency` converte os
valores pela [API pública Frankfurter v2](https://frankfurter.dev/); `--min-score`
filtra apenas as ofertas cuja fonte informa uma avaliação, e `--no-cache` força uma
nova coleta sem ler ou gravar o resultado.

Na primeira execução, o comando consulta Epic Games, Steam e GOG. As execuções
seguintes reutilizam o cache temporário por até doze horas. Se uma fonte estiver
indisponível, a CLI informa a falha e continua consultando as demais.

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

## Recursos disponíveis

- Adapters defensivos da Epic Games, ITAD, Steam e GOG, com testes offline.
- `GameDeal`, `Money`, `PriceHistory` e `Theme` como DTOs imutáveis.
- Contratos para adapters de lojas, histórico de preços e taxas de câmbio.
- `RadarService` resiliente: falhas de uma loja não derrubam as outras.
- Pipeline de coleta, filtro, conversão de moeda e sanitização de URLs.
- Cache com TTL, compressão gzip opcional e SQLite.
- CLI `free` com os temas padrão, Cyberpunk e Dracula.
- CLI `deal --top=N` com dados do ITAD e os mesmos temas do comando `free`.
- Opções globais de moeda, país, locale, score mínimo e uso do cache.
- Workflow de CI com lint, testes e análise estática.

A Fase 2.1 da CLI está concluída. Veja as próximas etapas no [ROADMAP.md](ROADMAP.md).

## Desenvolvimento

```bash
composer lint
composer test
composer analyse
```

### Credencial local do ITAD

Crie o arquivo local de ambiente a partir do modelo versionado:

```bash
cp .env.example .env
```

Preencha `ITAD_API_KEY` em `.env`. O arquivo local é ignorado pelo Git e não deve
ser versionado. O Core lê a chave do ambiente do processo por meio de
`ItadAdapter::fromEnvironment()`, e a CLI usa essa variável ao executar `deal`.
O entrypoint não carrega arquivos `.env`; exporte a variável no shell antes de chamar
o comando.

No shell, o arquivo pode ser carregado antes de executar um consumidor local:

```sh
set -a
. ./.env
set +a
```

Os testes executados por `composer test` são totalmente offline e não usam credenciais.
O país passado ao adapter define a região dos preços; ele não define o idioma da
interface. O futuro suporte a múltiplos idiomas usará uma configuração de locale
independente.

### Limite de requisições do ITAD

Cada processo limita, por padrão, todas as suas instâncias de `ItadAdapter` a 950
requisições em uma janela deslizante de cinco minutos. Aplicações com vários processos
devem compartilhar o limitador SQLite:

```php
use GuzzleHttp\Client;
use LootRadar\Adapters\ItadAdapter;
use LootRadar\Services\SqliteSlidingWindowRateLimiter;

$limiter = new SqliteSlidingWindowRateLimiter(
    __DIR__ . '/var/itad-rate-limit.sqlite',
);

$itad = ItadAdapter::fromEnvironment(
    new Client(),
    country: 'BR',
    rateLimiter: $limiter,
);
```

O arquivo SQLite reserva cada chamada em uma transação atômica, inclusive consultas de
histórico. Se a API responder com `HTTP 429`, o adapter respeita `Retry-After` e bloqueia
novas chamadas durante o período informado, sem fazer tentativas automáticas. O cache de
promoções, com TTL padrão de 12 horas, continua reduzindo as consultas repetidas.

## Documentação

- [Roadmap de desenvolvimento](ROADMAP.md)
- [Histórico de alterações](CHANGELOG.md)
- [Licença MIT](LICENSE)

## Licença

Distribuído sob a Licença MIT.
