# LootRadar — Roadmap Mestre de Desenvolvimento

> Documento único e vivo que consolida `LootRadar.md` (especificação técnica/funcional),
> `Plano de Desenvolvimento.md` (cronograma em 6 fases) e `lootradar-specs.md` (spec do Core)
> com o **estado real do código** e as **decisões de engenharia** já tomadas.
> Sempre que uma ideia dos documentos originais for inconsistente, ela é marcada como
> **DESCARTADA** ou **REFINADA** com a devida justificativa.

Última sincronização: 2026-09-04

---

## 0. Visão e princípios

**LootRadar** é um ecossistema open-source que consolida, filtra e monitora **jogos gratuitos** e
**promoções** das principais lojas (Epic, Steam, GOG e agregadores). Arquitetura **Headless**:
toda a inteligência vive no **Core em PHP 8.5+**; as interfaces (CLI, Web/PWA, Desktop) são
apenas camadas de apresentação que consomem o Core via chamada direta de classe ou via JSON.

Princípios inegociáveis:

1. **Core-first.** Nada de lógica de negócio nas interfaces. O Core deve ser publicável no
   Packagist e utilizável isoladamente.
2. **Agnóstico de loja.** Toda fonte de dados entra pelo contrato `StoreAdapterInterface`.
3. **Resiliência a mudança de API.** Parsers defensivos + fixtures + testes offline. Uma loja
   mudar o formato do JSON não pode derrubar as demais.
4. **PHP 8.5 idiomático.** Usar os recursos nativos onde agregam valor real — não por enfeite.
5. **Qualidade como gate.** `php -l`, PHPStan e Pest verdes são condição de merge.

---

## 1. Estado atual (o que já existe)

Base do Core operacional e validada ponta a ponta (**Fase 1 concluída**).

| Item | Caminho | Situação |
|------|---------|----------|
| Pacote / autoload | `composer.json` (`lootradar/lootradar`, PSR-4 `LootRadar\` → `src/`) | ✅ |
| DTOs imutáveis | `src/DTO/GameDeal.php`, `Money.php`, `PriceHistory.php`, `Theme.php` | ✅ |
| Contratos | `src/Contracts/{StoreAdapter,Cache,PriceHistoryProvider,ExchangeRateProvider,RateLimiter}Interface.php` | ✅ |
| Adapters de lojas | `src/Adapters/{EpicGames,Itad,Steam,Gog}Adapter.php` | ✅ promoções; ITAD também expõe histórico |
| Orquestrador + cache | `src/Services/RadarService.php`, `src/Contracts/CacheInterface.php`, `src/Cache/*` | ✅ abstração JSON/SQLite; CLI compõe `JsonCache` |
| Serviços transversais | `UrlSanitizer`, `ShovelwareFilter`, `CurrencyConverter`, `FrankfurterExchangeRateProvider`, limitadores de requisições | ✅ URL segura, filtro de score, conversão com cache e quota do ITAD |
| Temas CLI | `src/Services/ThemeManager.php`, `config/themes/*.json` | ✅ loader JSON + temas default/cyberpunk/dracula |
| Comandos `free`, `deal` e `snapshot` | `src/Commands/*Command.php` (Symfony Console + Termwind) | ✅ consultas humanas e snapshot JSON versionado |
| Entrypoint CLI | `bin/lootradar`, `src/Cli/ApplicationFactory.php` | ✅ base 0.3.0; composição por comando, ajuda completa e opções globais validadas |
| Testes | `tests/` (Pest, 84 casos / 348 asserções) | ✅ cache, domínio, moeda, URL, temas, quota, CLI, snapshot e todas as fontes cobertas offline |
| Análise estática | `phpstan.neon` (level 5) | ✅ modo serial com limite explícito de 512 MB |
| Credenciais locais | `.env` + `.env.example` | ✅ chave do ITAD isolada do Git; a CLI carrega `.env` sem sobrescrever o ambiente do processo |
| Temas de arquivo | `config/themes/cyberpunk.json`, `config/themes/dracula.json` | ✅ carregados dinamicamente |

**Verificado no ambiente:** PHP 8.5.10, Composer 2.10. Recursos nativos disponíveis e testados:
operador pipe `|>`, `#[\NoDiscard]`, `array_first()`/`array_last()`/`array_find()`, e a **extensão
de URI** (`Uri\Rfc3986\Uri`, `Uri\WhatWg\Url`) — base para a higienização de links.

### Ajustes de nomenclatura já aplicados pelo dono do projeto
- Namespace passou de `LootRadar\Core\` → **`LootRadar\`**; pacote `lootradar/lootradar`.
- ⚠️ **Discrepância a reconciliar antes do Packagist:** `LootRadar.md` promete
  `composer require lootradar/core`, enquanto o metadado atual é `lootradar/lootradar`.
  O release Git local `v0.1.0` não publica o pacote; decidir o nome final antes da publicação.

---

## 2. Recursos do PHP 8.5 no Core (verificados)

| Recurso | Onde | Papel |
|---------|------|-------|
| Operador Pipe `\|>` | pipelines dos adapters e do `RadarService` | `decode → normalizar → converter → filtrar → sanitizar → tokens` |
| `#[\NoDiscard]` | métodos de saúde/erro (`verifyApiHealth`, cache, auth) | força consumo do retorno **em runtime + PHPStan** |
| `array_first()` / `array_last()` / `array_find()` | ordenação/seleção de ofertas | seleção concisa de destaques |
| Extensão URI (`Uri\WhatWg\Url`) | `UrlSanitizer` | normalizar/higienizar checkout e remover parâmetros suspeitos |
| `readonly class` | `GameDeal` e futuros DTOs | imutabilidade total |

> **Correção de premissa:** `LootRadar.md` afirma que `#[\NoDiscard]` garante checagem "em tempo
> de compilação". PHP é interpretado — o atributo emite **warning em runtime** se o retorno for
> descartado e é reforçado pela **análise estática** (PHPStan). O benefício é real; a descrição
> original é imprecisa.

---

## 3. Fontes de dados (matriz de realidade)

| Fonte | Acesso | Custo | Uso | Prioridade |
|-------|--------|-------|-----|-----------|
| **Epic Games** | endpoint público `freeGamesPromotions` | grátis | jogos grátis (já integrado) | ✅ feito |
| **IsThereAnyDeal (ITAD)** | API oficial c/ chave | grátis (key) | **espinha dorsal** de deals agregados + histórico de menor preço | 🔴 alta |
| **Steam** | store endpoints (`featuredcategories`, `appdetails`) + Web API | grátis (key p/ Web API) | promoções, score, wishlist | 🟠 média |
| **GOG** | catálogo `catalog.gog.com` (não oficial) | grátis | promoções/grátis | 🟡 baixa |
| **Prime Gaming** | sem API pública; exige sessão autenticada | — | best-effort | ⚪ pós-MVP / candidato a descarte |

Decisões:
- **ITAD é o backbone** para "menor preço histórico" (Módulo A / Histórico Alinhado) e para o
  comando `deal`. Sem ITAD, histórico de preços vira scraping frágil — evitar.
- **Prime Gaming REBAIXADO:** sem API pública, o scraping autenticado é frágil e de manutenção
  cara. Fica como best-effort explícito ou é **descartado do MVP**.

---

## 4. Ideias descartadas ou refinadas (com justificativa)

| Ideia original (doc) | Decisão | Justificativa |
|----------------------|---------|---------------|
| Cache JSON **criptografado** (Plano 1.3) | **DESCARTADA** | São dados **públicos** de promoções. Criptografia só adiciona custo/complexidade sem ganho de segurança. Manter, no máximo, **compressão** opcional (gzip). |
| `#[\NoDiscard]` em "tempo de compilação" | **REFINADA** | PHP não compila; enforcement é runtime + PHPStan. Mantém-se o uso, corrige-se a narrativa. |
| Push **background** "GTA grátis nas próximas 2h" em PWA hospedado de graça | **REFINADA** | Notificação **local/foreground** (Notification API) é trivial. Push **em background** exige Service Worker + servidor Web Push + VAPID. No caminho 100% estático não há como enviar push do servidor. Ver §7.2 para as duas trilhas. |
| **GitHub Pages** servindo o backend PHP | **CORRIGIDA** | Pages é estático — não roda PHP. Trilha gratuita: o workflow da PWA executa o Core em **CI agendado (cron)**, gera **snapshots JSON versionados** e publica os arquivos estáticos. Backend PHP vivo é alternativa para quem tiver host. |
| Wishlist por **scraping de HTML** do perfil Steam | **REFINADA** | Usar o endpoint **JSON** `wishlistdata` da Steam; respeitar rate limit e ToS; tratar como fonte **frágil** com fallback silencioso. |
| **Smart Conversion** de moeda por fuso horário | **REFINADA** | Fuso ≠ moeda. Moeda vem de **config explícita do usuário** ou do **pricing regional** das APIs. Fuso serve só para exibir "termina em X". |
| `country` do ITAD como idioma | **REFINADA** | `country` seleciona a região comercial dos preços. Idioma pertence a uma configuração independente de `locale`; os endpoints de promoções e histórico usados no ITAD não recebem locale. |
| PHPStan **Level 8+** já (Plano Fase 4) vs. Level 5 entregue | **REFINADA** | Ratchet incremental: **5 (hoje) → 6 → 8/max**. Subir de nível exige endurecer o `mixed` dos parsers. Meta de longo prazo mantida. |
| Desktop via **Electron + PHP** | **DESCARTADA** em favor de **NativePHP** | Electron+PHP é pesado e duplica runtime. NativePHP entrega `.exe`/`.app` com o próprio PHP embarcado. |
| Prime Gaming como fonte de 1ª classe | **REBAIXADA** | Sem API pública (ver §3). |
| `ThemeManager` hardcoded via `match` | **CONCLUÍDA** | Temas são carregados de arquivos JSON em `config/themes/*.json`; o tema `default` permanece como fallback seguro. |
| Tokens de borda de caixa no Termwind (`border-solid/double/cor`) | **LIMITAÇÃO conhecida** | Termwind só renderiza `border-t` em `<hr>`, não bordas de `<div>`. Tokens `border` ficam no tema como dado para Web/Desktop; a CLI usa `<hr/>` como divisória. |

---

## 5. Arquitetura-alvo do ecossistema

**Decisão registrada em 2026-09-04:** o ecossistema será dividido em três repositórios.
Web/PWA e Desktop serão consumidores independentes do Core, com ciclos próprios de
desenvolvimento, validação e publicação.

```text
c0destep/
├── lootradar-core/                 # biblioteca PHP, CLI e contrato de snapshot
│   ├── src/                        # contratos, adapters, DTOs e serviços
│   ├── config/themes/              # tokens compartilháveis de apresentação
│   ├── tests/                      # Pest + fixtures offline das fontes
│   ├── bin/lootradar               # CLI e geração de snapshot JSON
│   └── .github/workflows/          # qualidade e release do pacote/.phar
├── lootradar-web/                  # PWA estática e publicação dos snapshots
│   ├── src/                        # interface mobile-first
│   ├── public/                     # manifest, Service Worker e dados publicados
│   ├── tests/                      # contrato JSON, Playwright e Lighthouse
│   └── .github/workflows/          # cron do snapshot e deploy da PWA
└── lootradar-desktop/              # aplicação NativePHP
    ├── composer.json               # dependência versionada do Core
    ├── tests/                      # integração e comportamento da aplicação
    └── .github/workflows/          # builders e releases dos executáveis
```

### 5.1 Responsabilidades e distribuição

| Repositório | Responsabilidade | Consumo do Core | Publicação |
|-------------|------------------|-----------------|------------|
| `lootradar-core` | Regras de negócio, integrações, DTOs, cache, CLI e snapshot | Fonte canônica | Packagist, `.phar` e GitHub Release |
| `lootradar-web` | PWA, Service Worker, temas, acessibilidade e notificações Web | Snapshot JSON com schema versionado | Pages, Vercel ou Netlify |
| `lootradar-desktop` | Interface NativePHP, System Tray e integrações do sistema operacional | Pacote Composer com faixa de versão explícita | Binários para Windows, macOS e Linux |

A separação preserva o foco do pacote PHP e impede que dependências de frontend,
artefatos do NativePHP e toolchains dos sistemas operacionais determinem o ciclo de release do
Core. Cada consumidor pode evoluir e ser publicado sem exigir uma nova versão dos demais.

O custo principal dessa escolha está na coordenação de mudanças de contrato. As seguintes
regras reduzem esse risco:

1. O snapshot mantém um `schemaVersion` explícito. Mudanças incompatíveis criam uma nova versão
   do schema, sem alterar silenciosamente o formato existente.
2. O repositório Web conserva fixtures das versões aceitas e executa testes de contrato antes do
   deploy.
3. O workflow da PWA instala uma versão explícita do Core, executa `lootradar snapshot` e publica
   o resultado com a interface.
4. O Desktop declara o Core no `composer.json` com uma faixa compatível e atualiza essa dependência
   por Pull Request acompanhado dos testes da aplicação.
5. Atualizações de dependência podem ser automatizadas, mas o merge continua condicionado ao CI
   do consumidor.
6. Componentes visuais compartilhados só formarão um pacote próprio quando surgir duplicação
   concreta entre Web e Desktop.

### 5.2 Versionamento e compatibilidade

- O Core segue Semantic Versioning para a API PHP, a CLI e o contrato de snapshot.
- O Desktop possui Semantic Versioning próprio e registra a versão compatível do Core.
- A PWA possui seu próprio histórico de releases e deploys; a compatibilidade depende da versão
  declarada do schema JSON.
- Não haverá uma tag única para todo o ecossistema. Cada repositório publica apenas os artefatos
  sob sua responsabilidade.
- A CLI permanece no Core nesta etapa, pois integra as releases existentes, valida o uso isolado
  da biblioteca e produz o snapshot consumido pela PWA.

---

## 6. Roadmap por fases

Legenda: ✅ feito · 🔧 em aberto · 🎯 critério de pronto.

### Fase 1 — Core robusto (PHP 8.5+)   *(concluída)*

**1.1 Abstrações e contratos**
- ✅ `StoreAdapterInterface`, `GameDeal`, `RadarService`, `ThemeManager`.
- ✅ `CacheInterface`, `JsonCache` e `SqliteCache` extraídos do `RadarService`.
- ✅ `PriceHistoryProviderInterface` (ITAD).
- ✅ DTOs `PriceHistory`, `Money` e `Theme`.

**1.2 Adapters e pipeline**
- ✅ `EpicGamesAdapter::fetchFreeGames()` e `fetchDeals()` com parser defensivo.
- ✅ `SteamAdapter`, `GogAdapter` e `ItadAdapter`; o ITAD também fornece histórico por contrato dedicado.
- ✅ **Pipeline completo** no Core: cada adapter decodifica/normaliza seu payload e o `RadarService`
  compõe `coletar |> filtrarShovelware |> converterMoeda |> sanitizarUrl |> serializar`.
  A aplicação de tokens pertence à camada de apresentação (CLI/Web), preservando o Core-first.
- ✅ Cada adapter tem fixture JSON e teste offline; o pipeline, cache e conversão também são cobertos.

**1.3 Serviços transversais**
- ✅ `ShovelwareFilter` — oculta score < limiar (padrão 60%), configurável.
- ✅ `UrlSanitizer` sobre `Uri\WhatWg\Url` — normaliza checkout, remove params suspeitos, valida esquema.
- ✅ `CurrencyConverter` — taxas via fonte configurável + cache; moeda-alvo explícita.
- ✅ `UrlSanitizer` com testes de injeção (esquemas `javascript:`, hosts falsos, etc.).
- ✅ Quota do ITAD — janela deslizante local por padrão, SQLite compartilhado entre processos
  e suspensão conforme `Retry-After`, sem repetição automática de chamadas.

**1.4 Cache**
- ✅ Cache JSON e SQLite com expiração (TTL 12h), atrás de `CacheInterface`.
- ✅ Compressão opcional gzip no `JsonCache`. **Sem criptografia** (ver §4).
- ✅ CLI compõe `JsonCache` antes de construir o `RadarService`.
- ✅ Mesma suíte de testes passa para `JsonCache` e `SqliteCache` (Win/Linux/macOS).
- ✅ Coletas com falha não são armazenadas; uma indisponibilidade temporária pode ser
  consultada novamente na execução seguinte.
- ✅ Chaves de coleta incluem região, locale, moeda, score mínimo, composição dos adapters
  e limite da fonte; `--no-cache` ignora leitura e escrita.

### Fase 2 — Interfaces CLI e Web/PWA

**2.1 CLI (Termwind)**   *(concluída)*
- ✅ Comando `free` + temas + fontes públicas da Epic Games, Steam e GOG; falhas isoladas
  são informadas sem interromper as demais consultas.
- ✅ Comando `deal --top=N` com tabela dos maiores descontos fornecidos pelo ITAD.
- ✅ `ThemeManager` carregando `config/themes/*.json`; temas default/cyberpunk/dracula disponíveis.
- ✅ Flags globais `--currency`, `--country`, `--locale`, `--min-score` e `--no-cache`;
  a região comercial e o locale permanecem configurações independentes.
- ✅ Conversão monetária da CLI usa a API pública Frankfurter v2, com cache das taxas e
  implementação injetável para testes offline.
- ✅ O entrypoint carrega `.env` automaticamente e preserva a prioridade de
  `ITAD_API_KEY` quando a variável já está definida no processo.
- ✅ A execução sem argumentos, `help` e `--help` apresentam comandos, fontes, temas,
  requisitos, opções e exemplos; `help free` e `help deal` detalham cada comando.
- ✅ `lootradar free` e `lootradar deal --top=5` renderizam nos temas default/cyberpunk/dracula.

**2.2 Web / PWA (`lootradar-web`)**
- ✅ Camada de exposição JSON do Core: comando `snapshot` emite schema versionado com
  contexto, integridade das fontes, jogos gratuitos e maiores promoções; URLs já saem higienizadas.
- 🔧 Criar o repositório consumidor `lootradar-web` com CI e versionamento independentes.
- 🔧 Instalar uma versão explícita do Core e persistir o snapshot por CI agendado para
  consumo estático (ver §7.2).
- 🔧 Frontend responsivo Tailwind, mobile-first.
- 🔧 `manifest.json` + Service Worker (instalação + cache offline do layout).
- 🔧 Temas via `data-theme` (cyberpunk, dracula, …) com CSS custom properties.
- 🔧 Notificações **locais**; push em background só na trilha com backend (§7.2).
- 🔧 Fixtures e testes de contrato para cada `schemaVersion` aceito pela PWA.
- 🎯 PWA instalável, Lighthouse PWA ok, alterna tema instantaneamente.

### Fase 3 — Desktop instalável (`lootradar-desktop`)
- 🔧 Criar o repositório consumidor `lootradar-desktop` com CI e versionamento independentes.
- 🔧 Consumir uma versão compatível do Core como dependência Composer.
- 🔧 Empacotar a interface com **NativePHP** (`.exe`/`.app`/Linux).
- 🔧 System Tray: roda minimizado, acorda para alertar promoção-relâmpago.
- 🔧 Ajuste de `memory_limit` para consumo mínimo.
- 🎯 Executável autocontido abre e lista jogos sem PHP/Composer instalados.

### Fase 4 — QA
- ✅ Pest configurado, 84 testes / 348 asserções.
- ✅ **Fixtures** JSON estáticos e testes de parser offline para Epic, ITAD, Steam e GOG.
- ✅ Testes de integração de cache JSON e SQLite.
- ✅ PHPStan level 5 executado em modo serial com limite de memória explícito de 512 MB.
- 🔧 Subir PHPStan 5 → 6 → 8/max.
- 🔧 No repositório Web, testes de contrato, layout Playwright e auditoria Lighthouse.
- 🔧 No repositório Desktop, testes de integração com as versões aceitas do Core.
- 🎯 Cobertura dos parsers e do pipeline; CI verde nos três repositórios.

### Fase 5 — CI/CD (GitHub Actions)
- ✅ Workflow em push/PR para PHP 8.5: validação Composer + lint + Pest + PHPStan.
- ✅ Workflow publicado e executado com sucesso no GitHub Actions.
- 🔧 No Core, `box.json` → compilar `.phar` em tags de versão.
- 🔧 No Web, **cron** instala o Core, gera os snapshots JSON e publica a PWA estática.
- 🔧 No Desktop, builders do NativePHP anexam os binários à release correspondente.
- 🎯 Cada repositório gera e publica seus próprios artefatos sem depender de uma tag global.

### Fase 6 — Publicação
- ✅ Preparação do release local `v0.1.0`: `.gitignore`, `LICENSE` MIT,
  `CHANGELOG.md`, README de instalação/uso, scripts Composer e lockfile sincronizado.
- ✅ Repositório público: [c0destep/lootradar-core](https://github.com/c0destep/lootradar-core).
- ✅ Release inicial publicada: [v0.1.0](https://github.com/c0destep/lootradar-core/releases/tag/v0.1.0).
- ✅ Release [v0.2.0](https://github.com/c0destep/lootradar-core/releases/tag/v0.2.0)
  publicada com a base do Core, adapters, histórico e conversão monetária.
- ✅ Release [v0.3.0](https://github.com/c0destep/lootradar-core/releases/tag/v0.3.0)
  publicada com a Fase 2.1 completa, carregamento do `.env` e ajuda integrada da CLI.
- 🔧 **Packagist**: decidir nome (ver §1) e configurar webhook.
- 🔧 Criar e publicar os repositórios `lootradar-web` e `lootradar-desktop`.
- 🔧 Deploy da PWA a partir do repositório Web, com HTTPS válido em
  Vercel, Netlify ou Pages.
- 🔧 Releases dos executáveis a partir do repositório Desktop.
- 🔧 README de alto nível: badges, GIFs da CLI e do mobile, instalação via Composer, seção Download.
- 🎯 `composer require <nome>` funciona; PWA pública; releases do Desktop oferecem binários.

---

## 7. Decisões de arquitetura

### 7.1 Cache: JSON vs SQLite
JSON já atende o MVP. SQLite entra quando houver **histórico de preços** (consultas por período)
e **wishlist matching** (joins). Ambos atrás de `CacheInterface` para troca sem impacto.

### 7.2 Distribuição do PWA — duas trilhas
- **Trilha A (gratuita, estática):** o workflow do repositório Web executa o Core em
  **CI agendado**, gera `data/*.json` versionado e publica a PWA em Pages/Vercel. Sem servidor
  PHP, **sem push em background**.
- **Trilha B (backend vivo):** API PHP hospedada expõe endpoints em tempo real e habilita
  **Web Push** (VAPID). Custa hospedagem.

Recomendação: começar pela **Trilha A** (custo zero, prova de valor) e migrar para B se/quando o
push em background virar requisito.

### 7.3 Repositórios dos consumidores
**DECIDIDA:** Web/PWA e Desktop ficam em repositórios independentes e consomem o Core pelos
contratos adequados a cada plataforma. A PWA usa o snapshot JSON versionado; o Desktop usa o
pacote Composer. A estrutura, os motivos e as regras de compatibilidade estão registrados no §5.

---

## 8. Definição de Pronto (global)
1. `find src -name '*.php' | xargs -n1 php -l` sem erros.
2. `vendor/bin/phpstan analyse src` verde no nível vigente (meta: subir gradualmente até max).
3. `vendor/bin/pest` verde, com fixtures cobrindo cada parser.
4. Todo adapter novo implementa `StoreAdapterInterface` + tem fixture + teste offline.
5. Todo link exibido passa por `UrlSanitizer`.
6. Toda release inclui a aplicação CLI funcional: `composer.json` expõe `bin/lootradar`,
   a versão exibida corresponde à tag e os comandos representam os recursos públicos entregues.

---

## 9. Próximos passos imediatos (ordem sugerida)
1. Criar o repositório `lootradar-web` e implementar o frontend PWA mobile-first consumindo o
   contrato JSON do comando `snapshot`.
2. Decidir o nome do pacote no Packagist (§1) antes de configurar a dependência do futuro
   repositório `lootradar-desktop`.
3. Acompanhar o CI a cada alteração e corrigir diferenças de ambiente; subir o PHPStan
   gradualmente de 5 para 6.
