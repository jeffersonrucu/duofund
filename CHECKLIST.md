# CHECKLIST — Melhorias 2026-08-25

Continuação do `MELHORIAS.md`. Executando 1 a 1, de cima pra baixo.
Legenda: `[ ]` pendente · `[~]` em andamento · `[x]` feito · `[!]` bloqueado (depende do Jefferson)

---

## Bloco 1 — Segredos e ambiente de produção 🔴

- [x] **1.1** Remover `.env.prod` do versionamento — `git rm --cached` + `.gitignore:30`; backup em `~/.duofund/env.prod` (600)
- [x] **1.2** Histórico limpo via `filter-branch` em mirror + force push (`6115bf7` → `732ae5f`); repo local ressincronizado com `reset --soft`, 58 arquivos pendentes intactos
- [x] **1.3** `APP_KEY` rotacionada em produção (2026-08-25). Backup do `.env` antigo no servidor em `.env.bak-2026-08-25`
- [x] **1.4** Rotacionar senha do banco — **decidido não fazer**. A senha só esteve exposta em repo privado da org e o histórico foi limpo; quando o repo virar público o commit não existe mais. Jefferson avaliou o risco residual como aceitável
- [x] **1.5** `.env` do servidor — `APP_ENV`/`APP_DEBUG` já estavam certos; aplicados `MAIL_MAILER=log` (tira o Mailpit do caminho, reset de senha não dá mais 500) e `LOG_CHANNEL=daily`
- [x] **1.5b** Aplicados em produção: `LOG_LEVEL=warning`, `MAIL_LOG_CHANNEL=mail`. Backup do `.env` em `.env.bak-pos-deploy`
- [x] **1.6** `emailVerification` do Fortify — investigado, **nada a fazer**: a view guarda com `instanceof MustVerifyEmail`, que o `User` não implementa, então todo o caminho é inalcançável. Sem crash. Reavaliar quando houver SMTP
- [x] **1.7** Canal de log `mail` (`config/logging.php`) — mailer `log` grava em `storage/logs/mail.log` a nível debug, pra o link de reset não sumir quando o `LOG_LEVEL` subir. `MAIL_LOG_CHANNEL` documentado no `.env.example`
- [ ] **1.8** SMTP de verdade — Jefferson optou por adiar. Enquanto isso o "esqueci minha senha" mostra sucesso mas não envia nada; o link fica recuperável em `storage/logs/mail.log`

## Bloco 2 — Bugs abertos 🟠

- [x] **2.1** Traits `HasMonthNavigation` + `HasScopeToggle` (`app/Livewire/Concerns/`), aplicados via `uses()` nas 9 páginas — **30 closures duplicados removidos** + o `$goToday` avulso de `installments`
  - saneamento em `booted{Trait}` (cobre URL/hydrate) **e** `updated{Trait}` (o `booted` roda antes do `set()` ser aplicado)
  - `Carbon::createFromFormat` lança em strict mode em vez de retornar `false` — daí o `preg_match` + `try/catch`
- [x] **2.2** `validatedScope()` / `validatedMonth()` no `DuofundMcpController` (6 call sites): `?month=abc` e `?scope=xpto` viram **422**, não 500 nem visão trocada em silêncio. Middleware força `Accept: application/json`. `depositGoal` valida `date`; `listTransactions` valida `type`
- [x] **2.3** `card_id` com `Rule::exists` filtrando por `user_id` da família **e** `scope` igual ao da transação (espelha o `forView`). `loadCats()` limpa o cartão quando ele sai da lista
- [x] **2.4** `decimal:2` em `Transaction.amount`, `Goal.target/current`, `Category.limit` (alinha com o `WishlistItem`, que já tinha)
- [x] **2.5** Testes: `MonthNavigationTest`, `McpQueryValidationTest`, `TransactionCardScopeTest` — **92 passando** (eram 47)

## Bloco 3 — Remover CDNs externos 📦

- [~] **3.1** html2pdf saiu do CDN e virou chunk Vite **sob demanda** (`resources/js/report-pdf.js`), fixado em `0.10.2` — mesma versão de hoje, saída byte-idêntica. Entry 1.27KB; o ~1MB só baixa no clique (antes vinha no load da página)
  - **dompdf ficou de fora de propósito**: 15 das 44 regras `.pdf*` usam flexbox, que o dompdf não suporta. Converter = reescrever em tabelas + embutir DM Sans e os glifos `↑ ◆ ↓`, com saída que eu não consigo conferir visualmente. Item separado abaixo (**5.7**), precisa do Jefferson olhar o PDF
- [x] **3.2** `mallardduck/blade-lucide-icons` (PHP ^8.1, ok pro 8.2 do servidor) — **263 ícones convertidos em 26 arquivos**, `unpkg.com/lucide@latest` removido junto com as 6 chamadas de `createIcons()` e o hook de commit do Livewire que existia só pra isso
  - 18 nomes dinâmicos viraram `<x-dynamic-component>`; os 3 `:class` do Alpine viraram `::class` (o Blade avaliava como expressão PHP e quebrava)
  - todos os 78 nomes estáticos + os dinâmicos validados contra os 2383 SVGs do pacote
- [x] **3.3** Fontes self-hosted: `@fontsource-variable/{dm-sans,fraunces,instrument-sans}`. Saíram o Google Fonts (4 layouts) e o `fonts.bunny.net` (`partials/head`). Famílias renomeadas pra `... Variable` nas stacks
- [x] **3.4** Alpine via `resources/js/landing.js` no `welcome` e `brand-auth` — saiu o jsdelivr `3.x.x` (não pinado)
- [x] **3.5** **Resultado do bloco: zero requisições externas** em `/`, `/privacidade` e `/login` (verificado no HTML renderizado)
- [x] **3.6** Bug pré-existente achado pelo smoke test: `/configuracoes/aparencia` dava **500 em produção** (view Volt sem `#[Layout]`, mesmo bug já corrigido no 2FA). Corrigido. A página é sobra do starter kit, em inglês e não linkada em lugar nenhum — vale decidir se apaga ou constrói
- [x] **3.7** `PageSmokeTest` — renderiza as 16 páginas que servem HTML. É a rede de proteção do 3.2: ícone inexistente vira `InvalidArgumentException`, não espaço em branco

## Bloco 4 — Observabilidade e qualidade 🔧

- [x] **4.1** `sentry/sentry-laravel` **ativo em produção** — o DSN estava só no `.env` local, foi levado pro servidor. Evento de teste `c88f6376` enviado de lá
- [x] **4.2** `scripts/backup-db.sh` — **não usei o `spatie/laravel-backup`**: ele depende de `proc_open` pra chamar o mysqldump, e hospedagem compartilhada costuma bloquear isso por `disable_functions`. Não consigo verificar o servidor daqui, então shell + cron (que ignora o PHP) é mais robusto e sem dependência
  - senha via `--defaults-extra-file` (em `--password=` ela apareceria no `ps`), gzip, rotação de 14 dias, guard de dump truncado
  - o guard já pegou uma falha real no teste: sem `--no-tablespaces` o mysqldump 8 exige privilégio `PROCESS`, que usuário de shared hosting não tem — teria quebrado igual no HostGator
  - **rodando em produção**: dump de 40K gerado, cron diário às 03h20 registrado. Confirmou que o `--no-tablespaces` era mesmo necessário lá
- [x] **4.3** `larastan/larastan` nível 5, **sem erros**. `tests/` ficou fora do escopo (o PHPStan não resolve o `$this` das closures do Pest: 75 falsos positivos)
  - achados reais corrigidos: `User::family()` e `Family::users()` sem tipo de retorno (o Larastan não inferia a relação) e `Auth` sem import no `routes/web.php`, funcionando só pelo alias global
  - scripts `composer analyse` e `composer check` adicionados
- [ ] **4.4** ~~SQLite em memória~~ — **não é trivial**: `listWishlist` usa `orderByRaw("FIELD(...)")`, que é MySQL-only. Ou troca por `CASE WHEN`, ou o item morre
- [x] **4.5** `Model::preventLazyLoading()` fora de produção (`AppServiceProvider`) + `NoLazyLoadingTest`, que renderiza as 8 páginas nos 2 escopos **com dados** — sem dados nenhuma relação é tocada e o guard não acusa nada
  - **4 N+1 reais encontrados e corrigidos**, todos na visão do casal: `Category->user` (orçamento), `Goal->user` (metas), `WishlistItem->user` (desejos), `Card->user` (cartões). É o item #9 do `MELHORIAS.md`, que só tinha sido resolvido em `expenses`

## Bloco 5 — Produto 💡

- [x] **5.1** `help` virou Blade puro (`resources/views/pages/help.blade.php`, `Route::view`), navegação por Alpine. 102 KB → 147 KB numa carga só, mas **zero roundtrip por aba** (cada clique antes devolvia a maior parte dos 102 KB re-renderizados)
- [x] **5.2** Máscara de moeda — **sem dependência nova**: o Livewire já empacota `x-mask` e `x-collapse`. E o `currencyInput` custom **não deve virar `x-mask`**: ele aplica só o delta do evento porque reler o valor formatado corrompe na composição do GBoard (está comentado no código)
  - os 4 modais já usavam o componente; o buraco era o **wishlist**, que ficou com input cru. Corrigido — agora todo campo monetário passa pelo mesmo `<x-ui.currency-input>`
- [x] **5.3** Alerta de orçamento no painel a partir de 80% (âmbar) e >100% (vermelho). O dado já existia — só aparecia em `/orcamento`, onde o usuário tinha que ir procurar. 7 testes cobrindo limiar, ordenação, plural, categoria sem limite e o resumo "e mais N"
- [x] **5.4** Comparativo vs mês anterior no relatório: chips de variação nos totais + nova seção "Por categoria" na tela (antes só existia dentro do PDF). Componente `<x-ui.delta-badge>`
  - bug meu que só o teste pegou: `$catUsage` no relatório é collection de models, não pluck — o `mapWithKeys` estava indexando por posição em vez de categoria. 6 testes, incluindo virada de ano
- [x] **5.5** Export CSV — **sem o `league/csv`**: `fputcsv` resolve. O que importa mais que a lib: `;` + BOM (Excel pt-BR), vírgula decimal, e **neutralização de fórmula** (`=`, `+`, `-`, `@` na descrição, que é input do usuário). 6 testes
- [~] **5.6** Undo após deletar — **decidido não fazer por ora**. Soft deletes quebram o `ON DELETE CASCADE` do `mirror_transaction_id`: com `deleted_at`, `$tx->delete()` vira UPDATE, o banco não cascateia, e a **despesa espelho sobrevive ao original apagado** — o saldo do casal quebra em silêncio. Fazer certo = migration em 4 tabelas + reescrever o caminho de espelho (que o `MELHORIAS.md` chama de parte mais frágil do sistema) + a UI do toast
- [~] **5.7** PDF do relatório server-side (dompdf) — **decidido não fazer por ora** — precisa reescrever o layout flex em tabelas e o Jefferson conferir a saída. Ganho: PDF de texto real em vez de imagem, e −1MB de JS
- [x] **5.8** Removidos: `layouts/app/header.blade.php` (zero referências), `layouts/auth/card` e `auth/split` (só `auth/simple` é usado), e `resources/js/app.js` vazio que gerava chunk de 0 byte

---

## Deploy 2026-08-25

10 commits publicados (`732ae5f` → `ca90d6f`). Migrations: nenhuma pendente, banco intocado.

- [x] Build, rsync, `optimize` e verificação: páginas, assets e API MCP em 200; os 422 novos respondendo
- [x] Config de produção aplicada: `LOG_LEVEL`, `MAIL_LOG_CHANNEL`, DSN do Sentry, cron do backup

### Incidente durante o deploy (~10 min fora do ar)

`composer require` rodou dentro do Sail (PHP 8.5) e resolveu `symfony/options-resolver`
e `symfony/psr-http-message-bridge` na v8.1, que exigem **PHP >= 8.4.1**. O servidor
roda **8.2.33**, então o `platform_check` do Composer abortou toda requisição: 500 em
tudo. O rsync ainda empurrou as dependências de dev, e o Pest 4 (PHP ^8.3) elevava o
piso mesmo sem rodar em produção.

**Corrigido:** os dois pacotes fixados no Symfony 7, `vendor` regerado com `--no-dev`
e sincronizado com `--delete`, manifestos de `bootstrap/cache` limpos (ainda listavam
providers de dev, quebrando o `optimize`).

**Como não repetir:** o passo de deploy precisa gerar o `vendor` com
`composer install --no-dev` antes do rsync. Fixar `config.platform.php` no
`composer.json` seria o ideal, mas o Pest 4 exige ^8.3 e torna o lock irresolvível —
por isso a separação dev/produção é o caminho.

- [ ] Documentar o `--no-dev` no passo de deploy do `CLAUDE.md` — **em aberto e importante**:
      é o que evita repetir a queda de 2026-08-25. O arquivo está fora do versionamento,
      então precisa ser editado à mão
- [~] API MCP devolve float em precisão total (`"income":11419.899999999999636...`).
      Pré-existente e cosmético; Jefferson optou por deixar como está
- [~] **4.4** Testes em SQLite — deixado de lado junto com o resto

---

## Encerramento (2026-08-25)

Blocos 1 a 5 concluídos e em produção, menos os itens que o Jefferson decidiu não fazer
(5.6 undo, 5.7 dompdf, 4.4 SQLite, rotação de senha e o arredondamento da API).

**Números:** 47 → 143 testes · PHPStan nível 5 limpo · 12 commits · zero requisição externa.

**Único item realmente em aberto:** documentar o `--no-dev` no deploy do `CLAUDE.md`.

---

## Repositório novo e esteira (2026-08-26)

O force-push **não** removeu nada do GitHub: os commits pré-rewrite e o blob do
`.env.prod` continuavam sendo servidos pela API por SHA. Confirmado na prática —
`GET /repos/.../git/blobs/0aebb31e...` devolvia os 1205 bytes. Só o suporte do
GitHub faz GC disso, então repo público com aquele histórico vazaria a senha do
banco.

**Solução:** repositório novo, com o histórico filtrado.

- [x] `Studio-STG/duofund` → renomeado para `duofund-legacy`, **arquivado e privado**
      (é ele que retém os objetos órfãos)
- [x] `Studio-STG/duofund` recriado, 19 commits com histórico filtrado
- [x] Removidos de **todo** o histórico: `.env.prod`, `public/error_log` (log de
      produção com caminhos do host, versionado desde o commit inicial) e
      `claude-notes.md`; caminho do servidor no `backup-db.sh` genericizado
- [x] Auditoria final: **0 achados** em 527 objetos — varredura por senha do banco,
      token MCP, chave SSH, `APP_KEY`, IP e usuário do servidor
- [x] Esteira `.github/workflows/ci.yml`: testes + PHPStan no PHP 8.3, `vendor`
      de produção no PHP 8.2 com `composer check-platform-reqs --no-dev`, rsync,
      migrate, optimize e verificação de HTTP 200
- [x] Secrets cadastrados (chave SSH, host, usuário e caminho ficam fora do código)
- [x] Descrição, homepage e 15 topics
- [x] README reescrito com 14 screenshots reais, geradas de **dados semeados**,
      nunca de produção
- [x] **Público** desde 2026-08-26
- [x] Transferido de `Studio-STG` para **`jeffersonrucu/duofund`** (conta pessoal).
      Secrets, topics e workflow sobreviveram; secret scanning e push protection
      resetam na transferência e foram religados
- [x] Secret scanning, push protection e Dependabot ativos — sem alertas
- [ ] Definir licença (hoje sem arquivo = todos os direitos reservados)
