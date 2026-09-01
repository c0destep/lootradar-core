# LootRadar — contexto de continuidade

## Estado do trabalho

- O `ROADMAP.md` é o documento de referência e deve ser sincronizado a cada etapa concluída.
- O trabalho posterior já implementou `CacheInterface`, `JsonCache`, `SqliteCache`, `UrlSanitizer`, `ShovelwareFilter` e os campos de histórico/moeda em `GameDeal`.
- O estado validado em 2026-09-01 é: Pest verde (18 testes); PHPStan level 5 não concluiu por falha do servidor TCP interno do ambiente; a CLI inicializa e expõe o comando `free`.
- O repositório local usa a branch `main`, possui o commit raiz do release e a tag anotada `v0.1.0`. O remoto GitHub ainda não foi criado porque a sessão do GitHub CLI precisa ser renovada.

## Regras de continuidade

1. Ler `ROADMAP.md` antes de iniciar uma etapa e atualizar sua seção de estado quando uma etapa for concluída.
2. Preservar a arquitetura Core-first: interfaces não devem conter lógica de negócio.
3. Todo adapter implementa `StoreAdapterInterface`, usa parser defensivo e recebe fixture/teste offline.
4. Toda URL exibida passa por `UrlSanitizer`; não reintroduzir validação baseada apenas em `parse_url()`.
5. Manter dados públicos sem criptografia no cache; compressão gzip é opcional.
6. Usar PHP 8.5+, `declare(strict_types=1)`, `readonly` para DTOs e `#[\\NoDiscard]` somente onde o retorno realmente precisa ser consumido.
7. Não agrupar alterações sem relação. Todo recurso novo, bugfix, ajuste de documentação ou manutenção deve terminar em seu próprio commit semântico, pequeno e revisável.
8. Antes de **todo commit**, executar e obter sucesso em `composer lint` e `composer test`. É proibido criar commits com testes falhando.
9. Antes de abrir ou atualizar **todo Pull Request**, executar `composer lint`, `composer test` e `composer analyse`; os três devem estar verdes. Se o ambiente local bloquear o PHPStan, registrar a evidência no PR e confirmar a análise no CI antes de aprovar ou fazer merge.
10. Após cada commit, conferir `git status --short` para garantir que não restaram alterações acidentais. Nunca commitar `vendor/`, cache, credenciais, arquivos da IDE ou documentos ignorados.
11. Usar mensagens no padrão Conventional Commits: `feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `build:`, `ci:` ou `chore:`. O escopo é opcional e deve nomear o módulo quando ajudar, por exemplo `fix(cache): expira entradas inválidas`.

## Versionamento semântico e releases

1. Toda versão pública segue **Semantic Versioning 2.0.0** e usa tags anotadas no formato `vMAJOR.MINOR.PATCH`.
2. Incrementar `MAJOR` para mudanças incompatíveis na API pública; `MINOR` para funcionalidades retrocompatíveis; e `PATCH` para correções retrocompatíveis, documentação ou manutenção sem mudança de API.
3. Antes de criar uma tag: atualizar `CHANGELOG.md`, executar as validações obrigatórias, conferir a árvore Git limpa e criar um commit de release independente.
4. Criar a tag com `git tag -a vX.Y.Z -m "LootRadar Core vX.Y.Z"`. Depois de publicada, uma tag de release é imutável: não usar `-f`, não reescrever histórico publicado e não fazer push forçado na `main`.
5. Publicar somente commits já presentes na `main`; cada Pull Request precisa ter CI verde e revisão antes do merge.
6. Para o release inicial, `v0.1.0` representa API ainda em evolução. A partir de `v1.0.0`, qualquer quebra de contrato público exige incremento de `MAJOR`.

## Ordem de execução recomendada

1. Implementar `ItadAdapter`, contrato/DTO de histórico e o comando `deal --top=N`.
2. Adicionar fixture/testes offline do ITAD e, depois, Steam/GOG.
3. Atualizar flags da CLI (`--currency`, `--min-score`, `--no-cache`) e então sincronizar o roadmap.
4. Só depois avançar para PWA, CI/CD e decisão final do nome Packagist.

## Comandos de qualidade

```bash
composer lint
composer test
composer analyse
```
