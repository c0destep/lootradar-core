# LootRadar Core

**v0.1.0** — núcleo PHP 8.5+ para coletar, normalizar, filtrar e exibir
jogos gratuitos e promoções de lojas digitais.

Nesta primeira versão estável, o Core consulta os jogos gratuitos da Epic Games,
mantém um cache local em JSON ou SQLite, sanitiza URLs de checkout e disponibiliza
uma CLI temática.

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
- `SqliteCache`: indicado para futuras consultas de histórico e wishlist.

## O que está incluído no v0.1.0

- Adaptador defensivo da Epic Games para jogos gratuitos.
- `GameDeal` imutável e `StoreAdapterInterface`.
- `RadarService` resiliente: falhas de uma loja não derrubam as outras.
- Filtro configurável de shovelware e sanitização de URLs.
- Cache com TTL, compressão gzip opcional e SQLite.
- CLI `free` com os temas padrão, Cyberpunk e Dracula.

ITAD, Steam, GOG, histórico de preços e o comando `deal` pertencem às próximas
versões. Veja o [ROADMAP.md](ROADMAP.md).

## Desenvolvimento

```bash
composer lint
composer test
composer analyse
```

`composer analyse` depende de o PHPStan conseguir abrir seu servidor local;
em alguns ambientes isolados essa etapa pode falhar antes da análise.

## Documentação

- [Roadmap de desenvolvimento](ROADMAP.md)
- [Histórico de alterações](CHANGELOG.md)
- [Licença MIT](LICENSE)

## Licença

Distribuído sob a Licença MIT.
