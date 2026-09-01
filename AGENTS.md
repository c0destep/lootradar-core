# LootRadar — contexto de continuidade

## Estado do trabalho

- O `ROADMAP.md` é o documento de referência e deve ser sincronizado a cada etapa concluída.
- O trabalho posterior já implementou `CacheInterface`, `JsonCache`, `SqliteCache`, `UrlSanitizer`, `ShovelwareFilter` e os campos de histórico/moeda em `GameDeal`.
- O estado validado em 2026-09-01 é: Pest verde (18 testes); PHPStan level 5 não concluiu por falha do servidor TCP interno do ambiente; a CLI inicializa e expõe o comando `free`.
- Não confiar em histórico Git local para determinar autoria ou sequência: o diretório `.git` está vazio.

## Regras de continuidade

1. Ler `ROADMAP.md` antes de iniciar uma etapa e atualizar sua seção de estado quando uma etapa for concluída.
2. Preservar a arquitetura Core-first: interfaces não devem conter lógica de negócio.
3. Todo adapter implementa `StoreAdapterInterface`, usa parser defensivo e recebe fixture/teste offline.
4. Toda URL exibida passa por `UrlSanitizer`; não reintroduzir validação baseada apenas em `parse_url()`.
5. Manter dados públicos sem criptografia no cache; compressão gzip é opcional.
6. Usar PHP 8.5+, `declare(strict_types=1)`, `readonly` para DTOs e `#[\\NoDiscard]` somente onde o retorno realmente precisa ser consumido.
7. Após mudanças, executar: lint de `src/` e `bin/`, Pest e PHPStan no nível vigente.

## Ordem de execução recomendada

1. Implementar `ItadAdapter`, contrato/DTO de histórico e o comando `deal --top=N`.
2. Adicionar fixture/testes offline do ITAD e, depois, Steam/GOG.
3. Atualizar flags da CLI (`--currency`, `--min-score`, `--no-cache`) e então sincronizar o roadmap.
4. Só depois avançar para PWA, CI/CD e decisão final do nome Packagist.

## Comandos de qualidade

```bash
find src -name '*.php' -print0 | xargs -0 -n1 php -l
php -l bin/lootradar
vendor/bin/pest
vendor/bin/phpstan analyse src --level=5
```
