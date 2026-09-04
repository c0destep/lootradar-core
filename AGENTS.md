# LootRadar — contexto de continuidade

## Estado do trabalho

- O `ROADMAP.md` é o documento de referência e deve ser sincronizado a cada etapa concluída.
- O trabalho posterior já implementou `CacheInterface`, `JsonCache`, `SqliteCache`, `UrlSanitizer`, `ShovelwareFilter` e os campos de histórico/moeda em `GameDeal`.
- O estado validado em 2026-09-04 é: lint verde; Pest com 85 testes e 359 asserções; PHPStan level 5 verde; CLI `0.4.0` com os comandos `free`, `deal`, `snapshot` e ajuda integrada.
- O repositório usa a branch `main`, possui as tags anotadas de `v0.1.0` a `v0.4.0` e o remoto público https://github.com/c0destep/lootradar-core.
- A arquitetura-alvo foi dividida entre `lootradar-core`, `lootradar-web` e `lootradar-desktop`; os dois consumidores ainda serão criados em repositórios independentes.
- O nome definitivo do pacote Composer é `lootradar/lootradar`; não retomar o nome legado `lootradar/core` sem uma decisão explícita de quebra de compatibilidade.
- A tag `v0.4.0` está publicada. A página da release no GitHub e a versão no Packagist ainda
  dependem de autenticação das contas mantenedoras.

## Arquitetura do ecossistema

1. `lootradar-core` é a fonte canônica das regras de negócio, integrações, DTOs, cache, CLI e do contrato de snapshot. Este repositório continua publicando o pacote Composer e a aplicação CLI.
2. `lootradar-web` será um consumidor independente. A PWA instalará uma versão explícita do Core em seu workflow, executará `lootradar snapshot` e consumirá somente o JSON validado pelo schema declarado. O payload informa `schemaVersion` e `producerVersion`.
3. `lootradar-desktop` será um consumidor independente em NativePHP. A aplicação declarará o Core no `composer.json` com uma faixa de versão compatível.
4. Cada repositório terá CI, versionamento e publicação próprios. Não existe uma tag global para o ecossistema: o Core publica o pacote e o `.phar`, a Web publica a PWA e o Desktop publica seus executáveis.
5. Mudanças incompatíveis no snapshot criam uma nova versão do schema. O repositório Web deve conservar fixtures das versões aceitas e validar o contrato antes do deploy.
6. Atualizações do Core nos consumidores devem ocorrer por Pull Request acompanhado do CI do repositório afetado. Automação de dependências não autoriza merge sem validação.
7. A CLI permanece no Core porque valida o uso isolado da biblioteca, integra as releases existentes e produz o snapshot consumido pela PWA.
8. Não criar antecipadamente um pacote de componentes visuais. Essa extração só se justifica quando houver duplicação concreta entre Web e Desktop.
9. Dependências Node, artefatos do NativePHP e toolchains dos sistemas operacionais pertencem aos repositórios consumidores e não devem ser incorporados ao Core.

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
12. Para snapshots automatizados, redirecionar `stdout` a um arquivo temporário, validar o JSON e
    só então substituir atomicamente o arquivo publicado. Tratar `complete: false` como coleta
    parcial e preservar o último snapshot completo conforme a política do consumidor.

## Práticas de PHP

1. Para toda implementação, alteração ou revisão de PHP, consultar a skill `.agents/skills/php-best-practices/SKILL.md` e as regras específicas aplicáveis em `.agents/skills/php-best-practices/rules/`.
2. Antes de sugerir recursos de linguagem, confirmar a versão exigida no `composer.json` e a versão do runtime com `php -v`. O projeto exige e usa PHP 8.5+; recursos mais recentes só podem ser adotados depois de atualizar esse requisito.
3. Priorizar tipagem explícita, `declare(strict_types=1)`, PSR-12/PSR-4, SOLID, validação de entradas externas, consultas SQL preparadas e tratamento de exceções específico. Não introduzir `@` para suprimir erros.
4. Em auditorias de código PHP, registrar achados no formato `arquivo:linha - [categoria] descrição`, usando as categorias da skill.

## Versionamento semântico e releases do Core

1. Toda versão pública segue **Semantic Versioning 2.0.0** e usa tags anotadas no formato `vMAJOR.MINOR.PATCH`.
2. Incrementar `MAJOR` para mudanças incompatíveis na API pública; `MINOR` para funcionalidades retrocompatíveis; e `PATCH` para correções retrocompatíveis, documentação ou manutenção sem mudança de API.
3. Antes de criar uma tag: atualizar `CHANGELOG.md`, executar as validações obrigatórias, conferir a árvore Git limpa e criar um commit de release independente.
4. Criar a tag com `git tag -a vX.Y.Z -m "LootRadar Core vX.Y.Z"`. Depois de publicada, uma tag de release é imutável: não usar `-f`, não reescrever histórico publicado e não fazer push forçado na `main`.
5. Publicar somente commits já presentes na `main`; cada Pull Request precisa ter CI verde e revisão antes do merge.
6. Para o release inicial, `v0.1.0` representa API ainda em evolução. A partir de `v1.0.0`, qualquer quebra de contrato público exige incremento de `MAJOR`.
7. Toda nova release deve ser distribuída com a aplicação CLI funcional. Antes de criar a tag, confirmar que `composer.json` continua expondo `bin/lootradar`, que `bin/lootradar --version` corresponde à versão da tag e que os comandos disponíveis refletem os recursos públicos incluídos no release.
8. Antes de publicar no Packagist, executar `composer check:package`, inspecionar o conteúdo de
   `composer archive`, instalar o artefato em um projeto consumidor limpo e confirmar
   `vendor/bin/lootradar --version`. Nunca publicar uma tag que contenha `.env`, credenciais,
   `vendor/`, caches, arquivos da IDE ou documentação interna.
9. O cadastro no Packagist e a configuração da atualização automática só ocorrem depois que o
   commit e a tag correspondentes estiverem publicados no GitHub. Tags publicadas são imutáveis.

## Ordem de execução recomendada

1. Criar a página da release `v0.4.0` no GitHub e cadastrar `lootradar/lootradar` no Packagist conforme `docs/PACKAGIST.md`; a tag já está publicada.
2. Confirmar a instalação pública do pacote em um projeto limpo antes de criar os consumidores.
3. Criar o repositório `lootradar-web` e implementar a PWA mobile-first sobre o contrato JSON do comando `snapshot`.
4. Configurar no repositório Web o cron que instala uma versão explícita do Core, gera, valida e publica o snapshot da PWA estática.
5. Subir o PHPStan gradualmente do nível 5 para o 6 e, depois, para o 8/max, sempre com o CI verde.
6. Criar `lootradar-desktop` somente após estabilizar o contrato consumido pelas interfaces.

## Comandos de qualidade

```bash
composer lint
composer test
composer analyse
```
