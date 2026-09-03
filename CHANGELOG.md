# Changelog

Todas as mudanças relevantes deste projeto serão documentadas neste arquivo.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/)
e o versionamento segue [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Unreleased]

### Added

- Limitadores de requisições em janela deslizante para o ITAD, com proteção local
  em memória e coordenação atômica entre processos por SQLite.
- Tratamento de `HTTP 429` e `Retry-After`, sem repetição automática da chamada.

### Changed

- A configuração local do ITAD passou a reservar o `.env` somente para a chave da
  API; a região comercial continua sendo informada ao adapter, separadamente do locale.

### Removed

- Smoke test manual do ITAD, seu comando Composer e a dependência exclusiva de ambiente.

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
