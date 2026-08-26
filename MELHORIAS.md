# MELHORIAS.md — DuoFund

Análise completa do projeto (backend, frontend, UX, modelagem, testes) feita em 2026-07-01.
Organizado por prioridade. Cada item tem contexto e, quando aplicável, arquivo:linha.

## ✅ Status da implementação (2026-07-02)

**Feitos e em produção (deploy 2026-07-02, 47/47 testes passando):**
#1–#17, #19 e toda a seção 🗑️ (exceto consolidar migrations de cards, já rodadas em prod).

**Extras encontrados/corrigidos durante os testes:**
- Middleware MCP usava `env()` direto — retorna null com `config:cache` (o deploy roda
  `optimize`). Migrado para `config/services.php`.
- Migration `change_url_to_text_in_wishlist_items` (timestamp 053616) rodava ANTES da
  create (120000) em banco zerado — guard `hasTable` + create já usa `text`.
- Página 2FA (`/configuracoes/duas-etapas`) dava 500: view sem `#[Layout]` caía no
  default `layouts::app` do Livewire, e o model User não tinha o trait
  `TwoFactorAuthenticatable`. Ambos corrigidos.
- `config/fortify.php` `home` apontava para `/dashboard` (URL antiga) → `/painel`.
- `Money::toDecimal` centralizado; redirects por Referer com fallback.
- Cron mensal criado no servidor (dia 1, 03h): `duofund:extend-recurrences`.

**Pendentes:**
- `#18` validação em tempo real (exige reestruturar as rules dos modais)
- `#20–#23` modelagem (categoria FK, centavos, uuid, soft deletes)
- Seção 💡 (features de produto)
- Fluxo de convite para usuário JÁ LOGADO nunca funciona: todo usuário ganha família
  própria no cadastro (`User::created`), e a rota de convite rejeita quem tem
  `family_id`. Convite só funciona via cadastro/login com sessão. Rever quando tocar
  no onboarding.

---

## 🔴 Críticos — bugs e segurança (fazer primeiro)

### 1. Transação espelho órfã ao deletar série recorrente
Quando uma receita `shared` recorrente é criada, o sistema cria despesas espelho
"Transferido para conta conjunta" com `recurring_group_id = "{uuid}_personal"`
(`transaction-modal.blade.php:249,276`). Ao deletar a série inteira
(`expenses.blade.php:111`), só o grupo original é apagado:

```php
Transaction::where('recurring_group_id', $tx->recurring_group_id)->delete();
// não apaga o grupo "{uuid}_personal" → despesas espelho ficam órfãs pra sempre
```

**Fix:** incluir `orWhere('recurring_group_id', $tx->recurring_group_id . '_personal')`
no delete. Idealmente centralizar toda lógica de espelho num `TransactionMirrorService`
(ver item 5).

### 2. Autorização de metas shared incompleta (API MCP)
`DuofundMcpController::deleteGoal` (linha 200) só checa `user_id === auth()->id()`.
Consequência: o parceiro não consegue deletar/gerenciar uma meta `shared` criada
pelo outro — inconsistente com a semântica de "casal". Mesmo padrão vale revisar
em `deposit_to_goal`, `delete_transaction`, `delete_category`.

**Fix:** para entidades `shared`, autorizar qualquer membro da família
(`getFamilyUserIds()`); para `personal`, só o dono. Extrair para uma Policy.

### 3. Batch delete de série sem verificação item a item
`expenses.blade.php:109-112`: a autorização é checada só na transação inicial,
depois deleta a série inteira por `recurring_group_id`. Se grupos de usuários
diferentes compartilharem um group_id (hoje improvável, mas nada impede), apaga
dados de terceiros.

**Fix:** filtrar o delete por `whereIn('user_id', $familyIds)` além do group_id.

### 4. Operações não atômicas
- Criação de receita shared + espelho não está em `DB::transaction()`
  (`transaction-modal.blade.php:207+`). Falha no meio = estado inconsistente.
- `depositGoal` no MCP controller (linha ~177): `$goal->increment()` +
  `Transaction::create()` sem transaction — meta pode incrementar sem registro.

**Fix:** envolver ambos em `DB::transaction(fn () => ...)`.

### 5. Rate limiting ausente
- `/api/mcp/*` sem throttle — token pode sofrer força bruta.
- Rota de aceitar convite (`routes/web.php:16`) sem throttle — permite enumerar famílias.

**Fix:** `->middleware('throttle:60,1')` no grupo MCP e `throttle:10,1` no convite.

---

## 🟠 Alta prioridade — arquitetura e performance

### 6. Extrair lógica duplicada de scope personal/shared
O padrão abaixo está copiado em dashboard, expenses, budget, goals, cards,
installments, report **e** no `DuofundMcpController` (~10 lugares):

```php
if ($view === 'personal') {
    $query->where('user_id', $user->id)->where('scope', 'personal');
} else {
    $query->whereIn('user_id', $familyIds)->where('scope', 'shared');
}
```

**Fix:** model scope reutilizável nos 4 models:

```php
// Transaction.php, Category.php, Goal.php, Card.php
public function scopeForView(Builder $q, User $user, string $view): Builder
{
    return $view === 'personal'
        ? $q->where('user_id', $user->id)->where('scope', 'personal')
        : $q->whereIn('user_id', $user->getFamilyUserIds())->where('scope', 'shared');
}
```

### 7. Service de resumo mensal
Cálculo de entradas/saídas/saldo do mês duplicado em dashboard, expenses, report
e em 3 métodos do MCP controller. Criar `TransactionSummaryService` (ou métodos
estáticos no model) usado por todos — inclusive garante que o app e a API MCP
nunca divirjam no número mostrado.

### 8. TransactionMirrorService
A lógica do espelho "Transferido para conta conjunta" está espalhada em
criar/editar/deletar dentro do `transaction-modal.blade.php` (linhas 173-190,
207-280, 321) com 4 caminhos de sincronização. É a parte mais frágil do sistema
(ver bug #1). Centralizar em service ou Observer no model `Transaction`.

### 9. N+1 query em transações shared
`expenses.blade.php:~350` acessa `$t->user->name` em loop; a query usa só
`->with('card')`. **Fix:** `->with(['card', 'user'])`. Verificar dashboard e
report também.

### 10. Índices compostos
Queries mais comuns filtram `(user_id, scope)` e `(recurring_group_id, date)`
sem índice composto. Migration:

```php
$table->index(['user_id', 'scope', 'date']);
$table->index(['recurring_group_id', 'date']);
```

### 11. Recorrências: 60 transações criadas de uma vez
Ao criar recorrência, o sistema insere 5 anos de transações
(`transaction-modal.blade.php:255+`). Incha o banco, torna edição/deleção em
lote pesada e trava o request.

**Fix (escolher um):**
- Gerar sob demanda: comando agendado mensal (`schedule`) que materializa o
  próximo mês de cada série ativa; ou
- Reduzir horizonte para 12 meses + job que estende.

### 12. Testes das regras de negócio críticas
Hoje só existe `TransactionBalanceTest` + testes padrão de auth. Faltam os que
protegem o que mais quebra:
- deletar série apaga espelho junto (bug #1 — escrever o teste antes do fix);
- usuário A não deleta dado personal do usuário B (mesmo na mesma família);
- parceiro consegue gerenciar entidade shared;
- depósito em meta cria transação savings atomicamente;
- 3º membro não entra na família;
- endpoints MCP com token inválido retornam 401.

---

## 🟡 Média prioridade — frontend e UX

### 13. Remover Tailwind CDN duplicado
`app.blade.php:9` e `brand-auth.blade.php:10` carregam `cdn.tailwindcss.com`
**e** o projeto também compila Tailwind via Vite (`resources/css/app.css`).
Resultado: ~140KB de CSS duplicado, LCP pior, tema definido em 2 lugares
(inline `tailwind.config` + app.css).

**Fix:** remover o CDN, mover cores primary/secondary/accent para o Tailwind
compilado, usar `@vite` em todos os layouts. O CDN também exibe warning de
"não usar em produção" no console.

### 14. Componentizar UI repetida
Quatro extrações com maior retorno (150+ linhas duplicadas):

| Componente | Duplicado em | Ganho |
|---|---|---|
| `<x-modal-sheet>` | 5 modais (overlay + sheet + swipe + header idênticos) | mudança visual em 1 lugar |
| `<x-scope-toggle>` | 8 páginas + 4 modais (toggle Pessoal/Casal) | consistência do conceito central do app |
| `<x-currency-input>` | só transaction-modal tem máscara; goal/category/card usam `type="number"` cru | UX de valor uniforme |
| `<x-button>` | botão primário copiado em todos os modais | tema em 1 lugar |

### 15. Máscara de moeda em todos os campos de valor
Hoje só transação tem máscara pt-BR (39 linhas de Alpine inline em
`transaction-modal.blade.php:432-471`). Metas, categorias e cartões usam input
numérico sem formatação. Resolver junto com `<x-currency-input>` (item 14).

### 16. Acessibilidade básica
- Modais sem `role="dialog"` / `aria-modal="true"`.
- Ícones puros (ex: help `app.blade.php:135`) sem `aria-label`.
- Labels sem `for`/`id` associados nos modais.
- Contraste do hover da nav (`#2674D9` sobre `#eff6ff` ≈ 3:1, WCAG AA pede 4.5:1).

### 17. `payment_method` inconsistente
Validação aceita `pix,card,boleto` (`transaction-modal.blade.php:93`), mas o
display em `expenses.blade.php:360` mapeia também `cash`, `credit`, `debit`.
A migration `normalize_payment_methods` sugere que já houve limpeza — alinhar
validação, labels e ícones numa fonte única (enum PHP 8.1):

```php
enum PaymentMethod: string
{
    case Pix = 'pix';
    case Card = 'card';
    case Boleto = 'boleto';

    public function label(): string { /* ... */ }
    public function icon(): string { /* ... */ }
}
```

### 18. Validação em tempo real nos formulários
Feedback só após submit. Adicionar `wire:blur` validation nos campos principais
(valor, descrição) para errar menos no fluxo mais usado do app.

### 19. Safe-area no toast e bottom sheets
`safe-area-bottom` aplicado na nav, mas o toast (`app.blade.php:305`) e os
bottom sheets não usam `env(safe-area-inset-bottom)` — em iPhone com notch o
toast fica encoberto.

---

## 🔵 Modelagem — dívidas a planejar (não urgente, mas quanto antes menor a dor)

### 20. `Transaction.category` é string, não FK
Renomear categoria não propaga; typo cria categoria fantasma; relatórios
agrupam errado. Migração em 3 passos: adicionar `category_id` nullable →
backfill por nome+user → trocar leituras. Manter a string durante a transição.

### 21. Dinheiro em `decimal(10,2)`
Funciona, mas cálculos em PHP com float acumulam arredondamento. Se for mexer
no schema (item 20), avaliar migrar para inteiro em centavos (`amount_cents`).
Prioridade baixa — o app soma valores simples, não faz juros compostos.

### 22. `recurring_group_id` string sem constraint
Trocar para `uuid` tipado. O sufixo `_personal` do espelho é uma gambiarra que
liga duas séries por convenção de string — melhor seria coluna
`mirror_of_group_id` explícita ou FK `mirror_transaction_id`.

### 23. Soft deletes + auditoria
Delete de transação/meta/categoria é definitivo. Para app financeiro de casal,
`softDeletes()` dá segurança (desfazer) e histórico ("quem apagou isso?").
Combina com um toast "Desfazer" de 5s após deletar.

---

## 💡 O que agregaria ao produto (fluxos novos)

Em ordem de valor/esforço:

1. **Undo após deletar** — toast com botão "Desfazer" (depende de soft deletes,
   item 23). Erro mais comum em app de dedo no celular.
2. **Alertas de orçamento** — notificação (ou banner no painel) ao atingir
   80%/100% do limite da categoria. O dado já existe; hoje o usuário precisa
   abrir /budget pra descobrir que estourou.
3. **Duplicar transação / "lançar de novo"** — long-press ou botão numa transação
   existente pré-preenche o modal. Compras repetidas viram 2 toques.
4. **Fatura do cartão** — a página /cards mostra gasto por cartão; evoluir para
   ciclo de fatura (dia de fechamento/vencimento no model `Card`) e projeção da
   fatura com parcelas futuras. Conecta cards + installments, hoje ilhas.
5. **Comparativo mensal no report** — "vs mês anterior" por categoria e no total.
   Transforma o relatório de extrato em ferramenta de decisão.
6. **Export CSV/OFX** — botão no report. Barato de fazer, muito pedido em app
   de finanças.
7. **Onboarding do casal** — após registro, wizard de 3 passos: convidar
   parceiro → criar 3 categorias sugeridas → primeira transação. Hoje o usuário
   cai num painel vazio.
8. **Divisão configurável da conta conjunta** — hoje o espelho transfere 100%
   do valor. Casais reais dividem 50/50 ou proporcional à renda. Campo
   `split_ratio` na família resolveria.

---

## 🗑️ Remover / limpar

- **Tailwind CDN** (item 13) — a maior remoção com maior ganho.
- **`tailwind.config` inline** nos layouts — junto com o item acima.
- **`base.html` e `claude-notes.md` na raiz** — parecem sobras; confirmar e apagar.
- **Redirects manuais** em `routes/web.php:94-105` — colapsar num loop
  `foreach ($map as $en => $pt) Route::redirect($en, $pt);`.
- **Labels/ícones de payment_method mortos** (`cash`, `credit`, `debit`) após
  alinhar o enum (item 17).
- **Consolidar as 3 migrations de cards** (`2026_06_30_*`): se ainda não rodaram
  em produção, colapsar em uma só; se já rodaram, deixar como está.

---

## Sugestão de ordem de ataque

| Fase | Itens | Por quê |
|---|---|---|
| 1 | #1, #3, #4 (+ testes do #12 que os cobrem) | bugs que corrompem dados do usuário |
| 2 | #2, #5 | segurança da API |
| 3 | #6, #7, #8 | destrava tudo que vem depois; reduz o app inteiro |
| 4 | #13, #14, #15 | performance + base de UI pra evoluir telas |
| 5 | #16–#19, produto (undo, alertas) | polimento e valor visível pro usuário |
| 6 | #20–#23 | dívidas de modelagem, quando houver fôlego |
