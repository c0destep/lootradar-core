# LootRadar Core

Núcleo PHP 8.5+ para coletar, normalizar, filtrar e exibir
jogos gratuitos e promoções de lojas digitais.

Nesta versão, o Core consulta jogos gratuitos e promoções da Epic Games, ITAD,
Steam e GOG, mantém um cache local em JSON ou SQLite, oferece histórico de preços
e conversão de moeda, sanitiza URLs de checkout e disponibiliza uma CLI temática.

Este repositório é a fonte canônica das regras de negócio e dos contratos do LootRadar.
As interfaces Web/PWA e Desktop serão desenvolvidas como consumidores independentes,
sem transferir lógica de negócio para as camadas de apresentação.

## Ecossistema e arquitetura

A arquitetura-alvo distribui o projeto entre três repositórios, cada um com CI,
versionamento e publicação próprios:

| Repositório | Responsabilidade | Forma de consumir o Core | Publicação |
|-------------|------------------|--------------------------|------------|
| `lootradar-core` | Biblioteca PHP, adapters, DTOs, serviços, cache, CLI e snapshot | Fonte canônica | Packagist, `.phar` e GitHub Release |
| `lootradar-web` | PWA, Service Worker, temas, acessibilidade e notificações Web | Snapshot JSON versionado | Pages, Vercel ou Netlify |
| `lootradar-desktop` | Aplicação NativePHP, System Tray e integrações do sistema operacional | Dependência Composer | Binários para Windows, macOS e Linux |

Os repositórios `lootradar-web` e `lootradar-desktop` ainda serão criados. Não haverá
uma tag única para todo o ecossistema: cada aplicação poderá evoluir e ser publicada sem
exigir uma nova versão das demais.

Essa separação mantém dependências Node, artefatos do NativePHP e toolchains dos sistemas
operacionais fora do pacote PHP. Um pacote de componentes visuais compartilhados só será
extraído quando houver duplicação concreta entre as interfaces Web e Desktop.

A PWA instalará uma versão explícita do Core no workflow agendado, executará o comando
`snapshot` e publicará o JSON junto da interface estática. O campo `schemaVersion` identifica
o contrato consumido e `producerVersion` registra a versão do Core que gerou o documento;
mudanças incompatíveis criam uma nova versão do schema, e a PWA mantém fixtures das versões
aceitas para os testes anteriores ao deploy.

A aplicação Desktop declarará o Core no `composer.json` com uma faixa de versão compatível.
As atualizações dessa dependência ocorrerão por Pull Request e só poderão ser integradas após
o CI do consumidor. A CLI continuará neste repositório porque valida o uso isolado da
biblioteca, integra as releases existentes e gera o snapshot usado pela PWA.

## Requisitos

- PHP 8.5 ou superior
- Composer 2
- Extensões `json`, `pdo`, `pdo_sqlite`, `uri` e `zlib`

## Instalação e uso

O nome definitivo do pacote é `lootradar/lootradar`. Depois da publicação da release no
Packagist, instale uma versão compatível no projeto consumidor:

```bash
composer require lootradar/lootradar:^0.4
vendor/bin/lootradar --version
```

Enquanto a primeira versão no Packagist não for publicada, ou para contribuir com o Core,
clone o repositório e instale as dependências:

```bash
git clone https://github.com/c0destep/lootradar-core.git
cd lootradar-core
composer install
```

Execute a CLI sem argumentos ou use `help` para consultar os comandos, as fontes,
os temas, as opções globais e exemplos de uso:

```bash
./bin/lootradar
./bin/lootradar help
./bin/lootradar help free
./bin/lootradar help deal
```

Liste os jogos gratuitos disponíveis nas fontes públicas:

```bash
./bin/lootradar free
./bin/lootradar free --theme=cyberpunk
./bin/lootradar free --theme=dracula
```

Liste os maiores descontos do ITAD depois de preencher `ITAD_API_KEY` no arquivo `.env`:

```bash
./bin/lootradar deal --top=5 --country=BR --locale=pt-BR
./bin/lootradar deal --top=5 --country=BR --currency=BRL --theme=cyberpunk
```

Gere o snapshot JSON versionado que servirá de contrato para a PWA. Grave primeiro em um
arquivo temporário, valide-o e só então substitua atomicamente o documento publicado:

```bash
./bin/lootradar snapshot --top=50 --country=BR --currency=BRL > lootradar.tmp.json
```

O snapshot contém o contexto da consulta, a integridade das fontes, os jogos gratuitos e
as maiores promoções. As URLs já são higienizadas pelo Core antes da serialização. Esse
comando também requer `ITAD_API_KEY` no arquivo `.env` ou no ambiente do processo. Erros são
enviados para `stderr`; um documento com `complete: false` continua sendo JSON válido, mas
indica que ao menos uma fonte falhou e não deve substituir silenciosamente o último snapshot
completo. O contrato formal está em
[`resources/schema/lootradar-snapshot-v1.schema.json`](resources/schema/lootradar-snapshot-v1.schema.json).

Os três comandos aceitam as opções globais `--currency`, `--country`, `--locale`,
`--min-score` e `--no-cache`. O país define a região comercial dos preços, enquanto
o locale configura o idioma das fontes compatíveis. A opção `--currency` converte os
valores pela [API pública Frankfurter v2](https://frankfurter.dev/); `--min-score`
filtra apenas as ofertas cuja fonte informa uma avaliação, e `--no-cache` força uma
nova coleta sem ler ou gravar o resultado.

Na primeira execução, o comando `free` consulta Epic Games, Steam e GOG. As execuções
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
- Comando `snapshot`, Schema JSON v1 e versão do produtor para consumidores automatizados.
- Ajuda integrada com fontes, temas disponíveis, requisitos, opções e exemplos.
- Workflow de CI com lint, testes e análise estática.

A Fase 2.1 da CLI está concluída. A PWA e a aplicação Desktop seguirão o modelo de
repositórios independentes descrito no [ROADMAP.md](ROADMAP.md#5-arquitetura-alvo-do-ecossistema).

## Desenvolvimento

```bash
composer lint
composer test
composer analyse
```

### Compatibilidade pública

O Core segue Semantic Versioning para contratos PHP sob o namespace `LootRadar\`, comandos,
opções e códigos de saída da CLI, além dos schemas de snapshot publicados. A partir da versão
`1.0.0`, mudanças incompatíveis nesses contratos exigem uma nova versão principal; durante a
série `0.x`, elas podem exigir uma nova versão menor, sempre com registro no changelog.

Classes auxiliares não documentadas como ponto de integração e detalhes internos dos adapters
não constituem API estável. Consumidores devem depender dos contratos, DTOs, serviços e formatos
explicitamente documentados.

### Credencial local do ITAD

Crie o arquivo local de ambiente a partir do modelo versionado:

```bash
cp .env.example .env
```

Preencha `ITAD_API_KEY` em `.env`. O arquivo local é ignorado pelo Git e não deve
ser versionado. A CLI carrega esse arquivo automaticamente antes de executar os
comandos. Quando `ITAD_API_KEY` também estiver definida no ambiente do processo, o
valor exportado terá prioridade sobre a configuração local.

O Core também permite que outra aplicação leia a variável do processo por meio de
`ItadAdapter::fromEnvironment()`. Nesse caso, o arquivo pode ser carregado pelo shell:

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
- [Publicação e validação no Packagist](docs/PACKAGIST.md)
- [Histórico de alterações](CHANGELOG.md)
- [Licença MIT](LICENSE)

## Licença

Distribuído sob a Licença MIT.
