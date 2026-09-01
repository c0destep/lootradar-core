# Changelog

Todas as mudanças relevantes deste projeto serão documentadas neste arquivo.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/)
e o versionamento segue [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [0.1.0] - 2026-09-01

### Added

- Core PHP 8.5+ com DTO imutável, contrato de lojas e orquestrador.
- Jogos gratuitos da Epic Games, cache JSON/SQLite e sanitização de URLs.
- CLI com comando `free` e temas default, Cyberpunk e Dracula.
- Testes offline para cache, Epic, filtro de shovelware, temas e URLs.

### Security

- Todo checkout passa por allowlist de esquema/host e remoção de parâmetros de
  rastreamento e redirecionamento.
