# LootRadar — Roadmap Mestre de Desenvolvimento

> Documento único e vivo que consolida `LootRadar.md` (especificação técnica/funcional),
> `Plano de Desenvolvimento.md` (cronograma em 6 fases) e `lootradar-specs.md` (spec do Core)
> com o **estado real do código** e as **decisões de engenharia** já tomadas.
> Sempre que uma ideia dos documentos originais for inconsistente, ela é marcada como
> **DESCARTADA** ou **REFINADA** com a devida justificativa.

Última sincronização: 2026-09-03

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
| Contratos | `src/Contracts/{StoreAdapter,Cache,PriceHistoryProvider,ExchangeRateProvider}Interface.php` | ✅ |
| Adapters de lojas | `src/Adapters/{EpicGames,Itad,Steam,Gog}Adapter.php` | ✅ promoções; ITAD também expõe histórico |
| Orquestrador + cache | `src/Services/RadarService.php`, `src/Contracts/CacheInterface.php`, `src/Cache/*` | ✅ abstração JSON/SQLite; CLI compõe `JsonCache` |
| Serviços transversais | `UrlSanitizer`, `ShovelwareFilter`, `CurrencyConverter` | ✅ URL segura, filtro de score e conversão com cache |
| Temas CLI | `src/Services/ThemeManager.php`, `config/themes/*.json` | ✅ loader JSON + temas default/cyberpunk/dracula |
| Comando `free` | `src/Commands/FreeGamesCommand.php` (Symfony Console + Termwind) | ✅ |
| Entrypoint CLI | `bin/lootradar` | ✅ executável |
| Testes | `tests/` (Pest, 34 casos / 97 asserções) | ✅ cache, domínio, moeda, URL, temas e todos os adapters cobertos offline |
| Análise estática | `phpstan.neon` (level 5) | ✅ modo serial com limite explícito de 512 MB |
| Credenciais locais | `.env` + `.env.example` | ✅ chave do ITAD isolada do Git e smoke test manual disponível |
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
| **GitHub Pages** servindo o backend PHP | **CORRIGIDA** | Pages é estático — não roda PHP. Trilha gratuita: o Core roda em **CI agendado (cron)**, gera **snapshots JSON versionados** e o PWA (estático) os consome. Backend PHP vivo é alternativa para quem tiver host. |
| Wishlist por **scraping de HTML** do perfil Steam | **REFINADA** | Usar o endpoint **JSON** `wishlistdata` da Steam; respeitar rate limit e ToS; tratar como fonte **frágil** com fallback silencioso. |
| **Smart Conversion** de moeda por fuso horário | **REFINADA** | Fuso ≠ moeda. Moeda vem de **config explícita do usuário** ou do **pricing regional** das APIs. Fuso serve só para exibir "termina em X". |
| PHPStan **Level 8+** já (Plano Fase 4) vs. Level 5 entregue | **REFINADA** | Ratchet incremental: **5 (hoje) → 6 → 8/max**. Subir de nível exige endurecer o `mixed` dos parsers. Meta de longo prazo mantida. |
| Desktop via **Electron + PHP** | **DESCARTADA** em favor de **NativePHP** | Electron+PHP é pesado e duplica runtime. NativePHP entrega `.exe`/`.app` com o próprio PHP embarcado. |
| Prime Gaming como fonte de 1ª classe | **REBAIXADA** | Sem API pública (ver §3). |
| `ThemeManager` hardcoded via `match` | **CONCLUÍDA** | Temas são carregados de arquivos JSON em `config/themes/*.json`; o tema `default` permanece como fallback seguro. |
| Tokens de borda de caixa no Termwind (`border-solid/double/cor`) | **LIMITAÇÃO conhecida** | Termwind só renderiza `border-t` em `<hr>`, não bordas de `<div>`. Tokens `border` ficam no tema como dado para Web/Desktop; a CLI usa `<hr/>` como divisória. |

---

## 5. Arquitetura-alvo de pastas

```text
lootradar/                          (monorepo do ecossistema)
├── src/                            # CORE (pacote Packagist)
│   ├── Contracts/                  # StoreAdapterInterface, CacheInterface, PriceHistoryProvider...
│   ├── Adapters/                   # Epic, Steam, GOG e ITAD ✅
│   ├── DTO/                        # GameDeal, Money, PriceHistory e Theme ✅
│   ├── Services/                   # RadarService, ThemeManager, CurrencyConverter, UrlSanitizer, ShovelwareFilter ✅
│   ├── Cache/                      # JsonCache ✅(embutido hoje), SqliteCache
│   ├── Pipeline/                   # estágios reutilizáveis do pipe |>
│   └── Commands/                   # free ✅, deal
├── config/themes/                  # dracula.json, cyberpunk.json ✅, rgb-gamer.json
├── tests/                          # unit + fixtures (JSON estáticos das APIs)
├── bin/lootradar                   # CLI ✅
├── apps/
│   ├── web/                        # PWA (frontend estático + Tailwind) — Fase 2.2
│   └── desktop/                    # NativePHP — Fase 3
├── .github/workflows/              # CI/CD — Fase 5
├── box.json                        # build .phar — Fase 5.2
├── phpstan.neon ✅  phpunit.xml ✅
└── ROADMAP.md (este)  README.md ✅
```

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

**1.4 Cache**
- ✅ Cache JSON e SQLite com expiração (TTL 12h), atrás de `CacheInterface`.
- ✅ Compressão opcional gzip no `JsonCache`. **Sem criptografia** (ver §4).
- ✅ CLI compõe `JsonCache` antes de construir o `RadarService`.
- ✅ Mesma suíte de testes passa para `JsonCache` e `SqliteCache` (Win/Linux/macOS).

### Fase 2 — Interfaces CLI e Web/PWA

**2.1 CLI (Termwind)**   *(base feita)*
- ✅ Comando `free` + temas.
- 🔧 Comando `deal --top=N` (tabela dos maiores descontos; usa ITAD).
- ✅ `ThemeManager` carregando `config/themes/*.json`; temas default/cyberpunk/dracula disponíveis.
- 🔧 Flags globais: `--currency`, `--min-score`, `--no-cache`.
- 🎯 `lootradar free` e `lootradar deal --top=5` renderizam nos temas default/cyberpunk/dracula.

**2.2 Web / PWA**
- 🔧 Camada de exposição JSON do Core (endpoints ou **snapshots** gerados por CI — ver §7.2).
- 🔧 Frontend responsivo Tailwind, mobile-first.
- 🔧 `manifest.json` + Service Worker (instalação + cache offline do layout).
- 🔧 Temas via `data-theme` (cyberpunk, dracula, …) com CSS custom properties.
- 🔧 Notificações **locais**; push em background só na trilha com backend (§7.2).
- 🎯 PWA instalável, Lighthouse PWA ok, alterna tema instantaneamente.

### Fase 3 — Desktop instalável (NativePHP)
- 🔧 Empacotar Core + interface com **NativePHP** (`.exe`/`.app`/Linux).
- 🔧 System Tray: roda minimizado, acorda para alertar promoção-relâmpago.
- 🔧 Ajuste de `memory_limit` para consumo mínimo.
- 🎯 Executável autocontido abre e lista jogos sem PHP/Composer instalados.

### Fase 4 — QA
- ✅ Pest configurado, 34 testes / 97 asserções.
- ✅ **Fixtures** JSON estáticos e testes de parser offline para Epic, ITAD, Steam e GOG.
- ✅ Testes de integração de cache JSON e SQLite.
- ✅ PHPStan level 5 executado em modo serial com limite de memória explícito de 512 MB.
- ✅ Smoke test manual do ITAD com credencial carregada de `.env` e sem exposição da chave.
- 🔧 Subir PHPStan 5 → 6 → 8/max.
- 🔧 (Web) testes de layout Playwright.
- 🎯 Cobertura dos parsers e do pipeline; CI verde nas 3 camadas.

### Fase 5 — CI/CD (GitHub Actions)
- ✅ Workflow em push/PR para PHP 8.5: validação Composer + lint + Pest + PHPStan.
- ✅ Workflow publicado e executado com sucesso no GitHub Actions.
- 🔧 `box.json` → compilar `.phar` em tags de versão.
- 🔧 Builders desktop (NativePHP) anexando binários em Releases.
- 🔧 **Cron** rodando o Core para gerar snapshots JSON do PWA (trilha estática).
- 🎯 Tag `vX.Y.Z` gera `.phar` + binários + snapshots automaticamente.

### Fase 6 — Publicação
- ✅ Preparação do release local `v0.1.0`: `.gitignore`, `LICENSE` MIT,
  `CHANGELOG.md`, README de instalação/uso, scripts Composer e lockfile sincronizado.
- ✅ Repositório público: [c0destep/lootradar-core](https://github.com/c0destep/lootradar-core).
- ✅ Release inicial publicada: [v0.1.0](https://github.com/c0destep/lootradar-core/releases/tag/v0.1.0).
- ✅ Preparação do release local `v0.2.0`: changelog, README e roadmap sincronizados
  com as alterações acumuladas desde `v0.1.0`.
- 🔧 **Packagist**: decidir nome (ver §1) e configurar webhook.
- 🔧 Deploy do PWA (Vercel/Netlify/Pages) com HTTPS válido (requisito de PWA).
- 🔧 README de alto nível: badges, GIFs da CLI e do mobile, instalação via Composer, seção Download.
- 🎯 `composer require <nome>` funciona; PWA público; Release com binários.

---

## 7. Decisões de arquitetura em aberto

### 7.1 Cache: JSON vs SQLite
JSON já atende o MVP. SQLite entra quando houver **histórico de preços** (consultas por período)
e **wishlist matching** (joins). Ambos atrás de `CacheInterface` para troca sem impacto.

### 7.2 Distribuição do PWA — duas trilhas
- **Trilha A (gratuita, estática):** Core roda em **CI agendado**, gera `data/*.json` versionado,
  servido por Pages/Vercel. PWA consome arquivos estáticos. Sem servidor PHP, **sem push em background**.
- **Trilha B (backend vivo):** API PHP hospedada expõe endpoints em tempo real e habilita
  **Web Push** (VAPID). Custa hospedagem.

Recomendação: começar pela **Trilha A** (custo zero, prova de valor) e migrar para B se/quando o
push em background virar requisito.

---

## 8. Definição de Pronto (global)
1. `find src -name '*.php' | xargs -n1 php -l` sem erros.
2. `vendor/bin/phpstan analyse src` verde no nível vigente (meta: subir gradualmente até max).
3. `vendor/bin/pest` verde, com fixtures cobrindo cada parser.
4. Todo adapter novo implementa `StoreAdapterInterface` + tem fixture + teste offline.
5. Todo link exibido passa por `UrlSanitizer`.

---

## 9. Próximos passos imediatos (ordem sugerida)
1. Implementar o comando `deal --top=N`, compondo o ITAD no entrypoint da CLI.
2. Adicionar as flags da CLI (`--currency`, `--min-score`, `--no-cache`) sem vazar lógica de negócio para o comando.
3. Acompanhar o CI a cada alteração e corrigir diferenças de ambiente; subir o PHPStan gradualmente de 5 para 6.
4. Decidir o nome do pacote no Packagist (§1) antes da publicação.
5. Iniciar a camada de exposição JSON e o PWA da Fase 2.2.
```
