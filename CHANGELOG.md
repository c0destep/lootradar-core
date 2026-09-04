# Changelog

Todas as mudanças relevantes deste projeto serão documentadas neste arquivo.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/)
e o versionamento segue [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Unreleased]

### Added

- Comando `snapshot` para expor jogos gratuitos, promoções, contexto e integridade em
  um documento JSON versionado, pronto para geração estática pelo futuro cron do PWA.

### Fixed

- A CLI instalada como dependência agora carrega o `.env` da aplicação consumidora,
  em vez de procurar a configuração dentro do diretório do pacote em `vendor/`.

## [0.3.0] - 2026-09-03

### Added

- Comando `deal --top=N`, com os maiores descontos do ITAD e indicação de menor
  preço histórico.
- Opções globais `--currency`, `--country`, `--locale`, `--min-score` e `--no-cache`
  para os comandos da CLI.
- Fonte pública Frankfurter v2 para converter moedas na CLI, com fixture e testes
  offline.
- Carregamento automático do arquivo `.env` pela CLI com o componente Dotenv do
  Symfony, sem substituir variáveis já definidas no processo.
- Limitadores de requisições em janela deslizante para o ITAD, com proteção local
  em memória e coordenação atômica entre processos por SQLite.
- Tratamento de `HTTP 429` e `Retry-After`, sem repetição automática da chamada.
- Ponto de composição testável da CLI para Epic Games, Steam e GOG.
- Ajuda integrada que apresenta comandos, fontes, temas, requisitos, opções e
  exemplos ao executar a CLI sem argumentos, `help` ou `--help`.

### Changed

- As chaves de cache agora separam região, locale, moeda, score mínimo, composição
  dos adapters e limite da fonte; `--no-cache` ignora leitura e escrita da coleta.
- Os comandos `free` e `deal` renderizam nos temas padrão, Cyberpunk e Dracula.
- O parser de ofertas do ITAD passou a reconhecer o envelope `list` devolvido pelo
  endpoint real `/deals/v2`.
- A configuração local do ITAD passou a reservar o `.env` somente para a chave da
  API; a região comercial continua sendo informada ao adapter, separadamente do locale.
- A CLI passou a informar a versão `0.3.0`, consultar todas as fontes públicas de
  jogos gratuitos e exibir falhas isoladas sem interromper a coleta.
- O `RadarService` deixou de armazenar coletas que tiveram falhas em alguma fonte.
- As ajudas de `free` e `deal` agora detalham suas fontes, opções específicas,
  temas disponíveis e exemplos de uso.

### Removed

- Smoke test manual do ITAD, seu comando Composer e a dependência exclusiva de ambiente.

### Security

- Exceções de quota do ITAD descartam a requisição HTTP original para que a chave da API
  não permaneça acessível pela cadeia de exceções.
- Dados externos são escapados antes de entrar no markup renderizado pelo Termwind.

## [0.2.0] - 2026-09-03

### Added

- Adapters defensivos para ITAD, Steam e GOG, com fixtures e testes offline.
- Consulta de deals da Epic Games e do ITAD, incluindo histórico de preços.
- Contratos e DTOs para histórico de preços, dinheiro, taxas de câmbio e temas.
- Pipeline de conversão de moeda com fonte de taxas configurável e cache.
- Workflow do GitHub Actions com gates de Composer, lint, Pest e PHPStan.

### Changed

- `GameDeal` passou a carregar informações de moeda, desconto e histórico de preços.
- O `RadarService` passou a compor coleta, filtro, conversão de moeda, sanitização
  de URLs e serialização em um pipeline único.
- A cobertura offline passou a abranger todos os adapters e os serviços de domínio,
  totalizando 33 testes e 96 asserções.

## [0.1.0] - 2026-09-01

### Added

- Core PHP 8.5+ com DTO imutável, contrato de lojas e orquestrador.
- Jogos gratuitos da Epic Games, cache JSON/SQLite e sanitização de URLs.
- CLI com comando `free` e temas default, Cyberpunk e Dracula.
- Testes offline para cache, Epic, filtro de shovelware, temas e URLs.

### Security

- Todo checkout passa por allowlist de esquema/host e remoção de parâmetros de
  rastreamento e redirecionamento.
