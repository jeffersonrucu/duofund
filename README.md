# DuoFund

Aplicação web de **gestão financeira colaborativa** construída com **Laravel 12** e **Livewire Volt**. O DuoFund permite que casais ou famílias gerenciem suas finanças em conjunto, com suporte a gastos pessoais e compartilhados.

## Sobre o Projeto

O nome "DuoFund" reflete seu propósito: gerenciamento financeiro para **dois** (ou mais membros de uma família). A aplicação oferece uma experiência completa para controle de receitas, despesas, orçamentos por categoria e metas financeiras.

## Funcionalidades

### Dashboard
- Visualização consolidada de receitas e despesas do mês
- Modo de visualização dual: **Pessoal** ou **Compartilhado**
- Navegação entre meses
- Cards de resumo: Receitas, Despesas e Resultado do Mês
- Orçamento por categoria com barras de progresso
- Atividade recente (últimas transações)

### Transações (Expenses)
- Lista paginada de todas as transações
- Suporte para 3 tipos de transações:
  - **Única**: Transação simples, uma vez
  - **Recorrente**: Repetida mensalmente (60 meses)
  - **Parcelada**: Dividida em N parcelas mensais
- Edição e exclusão com opção de afetar toda a série ou apenas uma transação
- Filtros por escopo (pessoal/compartilhado) e mês

### Categorias (Budget)
- Criação de categorias de despesa com limite de gastos
- Visualização de gastos vs limites com barras de progresso coloridas:
  - 🟢 Verde: 0-80% do limite
  - 🟡 Amarelo: 80-100% do limite
  - 🔴 Vermelho: >100% (excedido)

### Metas Financeiras (Goals)
- Criação de objetivos com nome, alvo e valor atual
- Depósitos nas metas via modal
- Suporte a metas privadas
- Progresso visual com barra animada

### Sistema de Famílias
- Convite para parceiro(a) via **link assinado** (válido por 24h)
- Compartilhamento de dados financeiros entre membros da família
- Dados sincronizados em modo "Compartilhado"

### Autenticação
- Registro com convite opcional
- Verificação de email
- Autenticação Two-Factor (2FA)
- Recuperação de senha
- Gerenciamento de perfil

## Stack Tecnológica

### Backend
| Tecnologia | Versão |
|------------|--------|
| PHP | ^8.2 |
| Laravel Framework | ^12.0 |
| Laravel Fortify | ^1.30 |
| Livewire Volt | ^1.7.0 |
| Livewire Flux | ^2.9.0 |

### Frontend
| Tecnologia | Versão |
|------------|--------|
| Vite | ^7.0.4 |
| Tailwind CSS | ^4.0.7 |
| Alpine.js | (via Livewire) |

### Desenvolvimento
| Ferramenta | Versão |
|------------|--------|
| Laravel Sail | ^1.41 |
| Pest PHP | ^4.1 |
| Laravel Pint | ^1.24 |

## Requisitos

- Docker e Docker Compose
- Git

## Instalação

### 1. Clone o repositório

```bash
git clone <url-do-repositorio>
cd duofund
```

### 2. Instale as dependências do Composer

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php83-composer:latest \
    composer install --ignore-platform-reqs
```

### 3. Configure o ambiente

```bash
cp .env.example .env
```

### 4. Instale o Laravel Sail

```bash
php artisan sail:install --with=mysql
```

### 5. Inicie os containers

```bash
./vendor/bin/sail up -d
```

### 6. Gere a chave da aplicação

```bash
./vendor/bin/sail artisan key:generate
```

### 7. Execute as migrations

```bash
./vendor/bin/sail artisan migrate
```

### 8. Instale dependências do NPM e compile os assets

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

## Uso

### Iniciar o ambiente de desenvolvimento

```bash
./vendor/bin/sail up -d
```

A aplicação estará disponível em: `http://localhost`

### Compilar assets em modo de desenvolvimento (com hot reload)

```bash
./vendor/bin/sail npm run dev
```

### Parar os containers

```bash
./vendor/bin/sail down
```

## Comandos Úteis

```bash
# Acessar o container
./vendor/bin/sail shell

# Executar comandos Artisan
./vendor/bin/sail artisan <comando>

# Executar testes
./vendor/bin/sail test

# Formatar código com Pint
./vendor/bin/sail pint

# Ver logs em tempo real
./vendor/bin/sail logs -f
```

## Estrutura do Banco de Dados

### Tabelas Principais

#### users
- Dados do usuário (nome, email, senha)
- Relacionamento com família (`family_id`)

#### families
- Agrupa usuários para compartilhamento de dados

#### transactions
- Registra receitas e despesas
- Campos: `description`, `amount`, `type` (income/expense), `scope` (personal/shared)
- Suporte a recorrência e parcelamento via `recurring_group_id`

#### categories
- Categorias de gastos com limite definido
- Campos: `name`, `limit`, `scope`

#### goals
- Metas financeiras
- Campos: `name`, `target`, `current`, `is_private`, `scope`

## Fluxos Principais

### Registro e Convite de Parceiro

```
1. Usuário A se registra → cria nova família automaticamente
2. Usuário A gera link de convite (válido 24h)
3. Usuário B clica no link e se registra
4. Usuário B é associado à família do Usuário A
5. Ambos podem visualizar dados em modo "Compartilhado"
```

### Criar Transação Recorrente

```
1. Usuário abre modal de nova transação
2. Seleciona frequência "Todo mês"
3. Sistema cria 60 transações (5 anos)
4. Todas vinculadas pelo mesmo `recurring_group_id`
5. Edição pode afetar uma ou todas da série
```

### Visualização Pessoal vs Compartilhado

```
Pessoal: Mostra apenas transações do próprio usuário com scope "personal"
Compartilhado: Consolida transações de todos da família com scope "shared"
```

## Arquitetura

O projeto utiliza o padrão **Livewire Volt** (Single File Components), onde a lógica PHP e o template Blade ficam no mesmo arquivo. Os componentes principais estão em:

```
resources/views/livewire/
├── pages/           # Páginas principais (dashboard, expenses, budget, goals)
├── components/      # Modais e componentes reutilizáveis
├── auth/            # Autenticação (login, register, 2FA)
└── settings/        # Configurações de perfil
```

## Licença

Este projeto é privado e de uso restrito.
