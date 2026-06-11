import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

const BASE_URL = process.env.DUOFUND_URL?.replace(/\/$/, "") ?? "http://localhost:8080";
const TOKEN    = process.env.DUOFUND_TOKEN ?? "";

if (!TOKEN) {
  process.stderr.write("DUOFUND_TOKEN não configurado. Defina no mcp.json env.\n");
  process.exit(1);
}

async function api(method, path, body = null) {
  const url = `${BASE_URL}/api/mcp${path}`;
  const opts = {
    method,
    headers: {
      "Authorization": `Bearer ${TOKEN}`,
      "Content-Type": "application/json",
      "Accept": "application/json",
    },
  };
  if (body) opts.body = JSON.stringify(body);

  const res = await fetch(url, opts);
  const json = await res.json();

  if (!res.ok) {
    throw new Error(json.error ?? json.message ?? `HTTP ${res.status}`);
  }
  return json;
}

function fmt(json) {
  return JSON.stringify(json, null, 2);
}

// ─── Server ─────────────────────────────────────────────────────────────────

const server = new McpServer({
  name: "duofund",
  version: "1.0.0",
});

// ─── SUMMARY ────────────────────────────────────────────────────────────────

server.tool(
  "get_summary",
  "Resumo financeiro do mês: entradas, saídas, saldo, orçamento usado.",
  {
    scope: z.enum(["personal", "shared"]).optional().describe("'personal' (padrão) ou 'shared' (casal)"),
    month: z.string().optional().describe("Mês no formato YYYY-MM. Padrão: mês atual"),
  },
  async ({ scope = "personal", month }) => {
    const params = new URLSearchParams({ scope, ...(month && { month }) });
    const data = await api("GET", `/summary?${params}`);
    return { content: [{ type: "text", text: fmt(data) }] };
  }
);

// ─── CATEGORIES ─────────────────────────────────────────────────────────────

server.tool(
  "list_categories",
  "Lista categorias de orçamento com gasto atual e % do limite.",
  {
    scope: z.enum(["personal", "shared"]).optional(),
    month: z.string().optional().describe("Formato YYYY-MM"),
  },
  async ({ scope = "personal", month }) => {
    const params = new URLSearchParams({ scope, ...(month && { month }) });
    const data = await api("GET", `/categories?${params}`);
    return { content: [{ type: "text", text: fmt(data) }] };
  }
);

server.tool(
  "create_category",
  "Cria uma categoria de orçamento com limite mensal opcional.",
  {
    name:  z.string().describe("Nome da categoria (ex: Alimentação, Lazer)"),
    limit: z.number().min(0).optional().describe("Limite mensal em R$. 0 = sem limite"),
    scope: z.enum(["personal", "shared"]).optional(),
  },
  async ({ name, limit = 0, scope = "personal" }) => {
    const data = await api("POST", "/categories", { name, limit, scope });
    return { content: [{ type: "text", text: data.message }] };
  }
);

server.tool(
  "delete_category",
  "Remove uma categoria de orçamento pelo ID.",
  {
    id: z.number().int().describe("ID da categoria"),
  },
  async ({ id }) => {
    const data = await api("DELETE", `/categories/${id}`);
    return { content: [{ type: "text", text: data.message }] };
  }
);

// ─── GOALS ──────────────────────────────────────────────────────────────────

server.tool(
  "list_goals",
  "Lista metas financeiras com progresso e valor restante.",
  {
    scope: z.enum(["personal", "shared"]).optional(),
  },
  async ({ scope = "personal" }) => {
    const data = await api("GET", `/goals?scope=${scope}`);
    return { content: [{ type: "text", text: fmt(data) }] };
  }
);

server.tool(
  "create_goal",
  "Cria uma meta financeira (ex: viagem, reserva de emergência).",
  {
    name:    z.string().describe("Nome da meta (ex: Viagem Europa)"),
    target:  z.number().min(0.01).describe("Valor alvo em R$"),
    current: z.number().min(0).optional().describe("Valor já guardado. Padrão: 0"),
    scope:   z.enum(["personal", "shared"]).optional(),
  },
  async ({ name, target, current = 0, scope = "personal" }) => {
    const data = await api("POST", "/goals", { name, target, current, scope });
    return { content: [{ type: "text", text: data.message }] };
  }
);

server.tool(
  "deposit_to_goal",
  "Adiciona um valor ao progresso de uma meta.",
  {
    id:     z.number().int().describe("ID da meta"),
    amount: z.number().min(0.01).describe("Valor em R$ a adicionar"),
    date:   z.string().optional().describe("Data do depósito (YYYY-MM-DD). Padrão: hoje"),
  },
  async ({ id, amount, date }) => {
    const data = await api("POST", `/goals/${id}/deposit`, { amount, ...(date && { date }) });
    return { content: [{ type: "text", text: data.message }] };
  }
);

server.tool(
  "delete_goal",
  "Remove uma meta pelo ID.",
  {
    id: z.number().int(),
  },
  async ({ id }) => {
    const data = await api("DELETE", `/goals/${id}`);
    return { content: [{ type: "text", text: data.message }] };
  }
);

// ─── TRANSACTIONS ────────────────────────────────────────────────────────────

server.tool(
  "list_transactions",
  "Lista transações do mês (até 50). Filtra por scope e tipo.",
  {
    scope: z.enum(["personal", "shared"]).optional(),
    month: z.string().optional().describe("Formato YYYY-MM"),
    type:  z.enum(["income", "expense"]).optional().describe("Filtrar por tipo"),
  },
  async ({ scope = "personal", month, type }) => {
    const params = new URLSearchParams({ scope, ...(month && { month }), ...(type && { type }) });
    const data = await api("GET", `/transactions?${params}`);
    return { content: [{ type: "text", text: fmt(data) }] };
  }
);

server.tool(
  "create_transaction",
  "Registra uma transação (receita ou despesa).",
  {
    description: z.string().describe("Descrição (ex: Salário, Supermercado)"),
    amount:      z.number().min(0.01).describe("Valor em R$"),
    type:        z.enum(["income", "expense"]).describe("'income' = receita, 'expense' = despesa"),
    category:    z.string().optional().describe("Categoria (ex: Alimentação). Criada automaticamente se não existir"),
    date:        z.string().optional().describe("Data YYYY-MM-DD. Padrão: hoje"),
    scope:       z.enum(["personal", "shared"]).optional(),
  },
  async ({ description, amount, type, category, date, scope = "personal" }) => {
    const data = await api("POST", "/transactions", { description, amount, type, category, date, scope });
    return { content: [{ type: "text", text: data.message }] };
  }
);

server.tool(
  "delete_transaction",
  "Remove uma transação pelo ID.",
  {
    id: z.number().int(),
  },
  async ({ id }) => {
    const data = await api("DELETE", `/transactions/${id}`);
    return { content: [{ type: "text", text: data.message }] };
  }
);

// ─── WISHLIST ────────────────────────────────────────────────────────────────

server.tool(
  "list_wishlist",
  "Lista itens da lista de desejos com preço e prioridade.",
  {
    scope: z.enum(["personal", "shared"]).optional(),
  },
  async ({ scope = "personal" }) => {
    const data = await api("GET", `/wishlist?scope=${scope}`);
    return { content: [{ type: "text", text: fmt(data) }] };
  }
);

server.tool(
  "create_wishlist_item",
  "Adiciona um produto à lista de desejos.",
  {
    name:     z.string().describe("Nome do produto (ex: iPhone 16 Pro)"),
    price:    z.number().min(0.01).describe("Preço em R$"),
    url:      z.string().url().optional().describe("Link do produto"),
    priority: z.enum(["high", "medium", "low"]).optional().describe("Prioridade. Padrão: medium"),
    scope:    z.enum(["personal", "shared"]).optional(),
    notes:    z.string().optional().describe("Cor, modelo, onde encontrou..."),
  },
  async ({ name, price, url, priority = "medium", scope = "personal", notes }) => {
    const data = await api("POST", "/wishlist", { name, price, url, priority, scope, notes });
    return { content: [{ type: "text", text: data.message }] };
  }
);

server.tool(
  "delete_wishlist_item",
  "Remove um item da lista de desejos pelo ID.",
  {
    id: z.number().int(),
  },
  async ({ id }) => {
    const data = await api("DELETE", `/wishlist/${id}`);
    return { content: [{ type: "text", text: data.message }] };
  }
);

// ─── Start ───────────────────────────────────────────────────────────────────

const transport = new StdioServerTransport();
await server.connect(transport);
