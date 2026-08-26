<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\WishlistItem;
use App\Services\MonthlySummaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DuofundMcpController extends Controller
{
    // ─── SUMMARY ──────────────────────────────────────────────────────────────

    public function summary(Request $request, MonthlySummaryService $summary): JsonResponse
    {
        $user  = auth()->user();
        $scope = $this->validatedScope($request);
        $date  = $this->validatedMonth($request);

        $totals = $summary->for($user, $scope, $date);
        $budget = (float) Category::forView($user, $scope)->sum('limit');
        $budgetExpense = $totals['expense'] - $totals['transfer'];

        return response()->json([
            'month'          => $date->locale('pt_BR')->isoFormat('MMMM [de] YYYY'),
            'scope'          => $scope,
            'income'         => $totals['income'],
            'expenses'       => $totals['expense'],
            'transfer'       => $totals['transfer'],
            'balance'        => $totals['balance'],
            'budget_total'   => $budget,
            'budget_used_pct'=> $budget > 0 ? round(($budgetExpense / $budget) * 100, 1) : 0,
        ]);
    }

    // ─── CATEGORIES ───────────────────────────────────────────────────────────

    public function listCategories(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $scope = $this->validatedScope($request);
        $date  = $this->validatedMonth($request);

        $usage = Transaction::forView($user, $scope)
            ->inMonth($date)
            ->where('type', 'expense')
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $categories = Category::forView($user, $scope)->orderBy('name')->get()->map(fn($c) => [
            'id'        => $c->id,
            'name'      => $c->name,
            'limit'     => (float) $c->limit,
            'scope'     => $c->scope,
            'spent'     => (float) ($usage[$c->name] ?? 0),
            'remaining' => $c->limit > 0 ? (float) ($c->limit - ($usage[$c->name] ?? 0)) : null,
            'pct_used'  => $c->limit > 0 ? round((($usage[$c->name] ?? 0) / $c->limit) * 100, 1) : null,
        ]);

        return response()->json(['data' => $categories]);
    }

    public function createCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'limit' => 'nullable|numeric|min:0',
            'scope' => 'nullable|in:personal,shared',
        ]);

        $category = Category::create([
            'user_id' => auth()->id(),
            'name'    => $data['name'],
            'limit'   => $data['limit'] ?? 0,
            'scope'   => $data['scope'] ?? 'personal',
        ]);

        return response()->json(['data' => $category, 'message' => "Categoria '{$category->name}' criada."], 201);
    }

    public function deleteCategory(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        if (! $category->manageableBy(auth()->user())) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $name = $category->name;
        $category->delete();

        return response()->json(['message' => "Categoria '{$name}' removida."]);
    }

    // ─── GOALS ────────────────────────────────────────────────────────────────

    public function listGoals(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $scope = $this->validatedScope($request);

        $goals = Goal::forView($user, $scope)->orderBy('created_at', 'desc')->get()->map(fn($g) => [
            'id'         => $g->id,
            'name'       => $g->name,
            'target'     => (float) $g->target,
            'current'    => (float) $g->current,
            'remaining'  => (float) max(0, $g->target - $g->current),
            'pct'        => round(min(100, ($g->current / max(1, $g->target)) * 100), 1),
            'scope'      => $g->scope,
            'completed'  => $g->current >= $g->target,
        ]);

        return response()->json(['data' => $goals]);
    }

    public function createGoal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'target'     => 'required|numeric|min:0.01',
            'current'    => 'nullable|numeric|min:0',
            'scope'      => 'nullable|in:personal,shared',
        ]);

        $scope = $data['scope'] ?? 'personal';
        $goal  = Goal::create([
            'user_id'    => auth()->id(),
            'name'       => $data['name'],
            'target'     => $data['target'],
            'current'    => $data['current'] ?? 0,
            'is_private' => $scope === 'personal',
            'scope'      => $scope,
        ]);

        return response()->json(['data' => $goal, 'message' => "Meta '{$goal->name}' criada."], 201);
    }

    public function depositGoal(Request $request, int $id): JsonResponse
    {
        $goal = Goal::findOrFail($id);

        if (! $goal->manageableBy(auth()->user())) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date'   => 'nullable|date',
        ]);

        DB::transaction(function () use ($goal, $data, $request) {
            $goal->increment('current', $data['amount']);

            Transaction::create([
                'user_id'     => auth()->id(),
                'description' => "Reserva para meta: {$goal->name}",
                'amount'      => $data['amount'],
                'type'        => 'savings',
                'category'    => 'Reserva para Metas',
                'date'        => $request->input('date', now()->toDateString()),
                'scope'       => $goal->scope,
            ]);
        });

        return response()->json(['message' => "R$ " . number_format($data['amount'], 2, ',', '.') . " adicionado à meta '{$goal->name}'.", 'current' => $goal->fresh()->current]);
    }

    public function deleteGoal(int $id): JsonResponse
    {
        $goal = Goal::findOrFail($id);

        if (! $goal->manageableBy(auth()->user())) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $name = $goal->name;
        $goal->delete();

        return response()->json(['message' => "Meta '{$name}' removida."]);
    }

    // ─── TRANSACTIONS ─────────────────────────────────────────────────────────

    public function listTransactions(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $request->validate(['type' => 'nullable|in:income,expense,savings']);

        $scope = $this->validatedScope($request);
        $date  = $this->validatedMonth($request);
        $type  = $request->query('type');

        $query = Transaction::forView($user, $scope)->inMonth($date);

        if ($type) $query->where('type', $type);

        $transactions = $query->orderBy('date', 'desc')->take(50)->get()->map(fn($t) => [
            'id'          => $t->id,
            'description' => $t->description,
            'amount'      => (float) $t->amount,
            'type'        => $t->type,
            'category'    => $t->category,
            'date'        => $t->date->format('Y-m-d'),
            'scope'       => $t->scope,
        ]);

        return response()->json(['data' => $transactions]);
    }

    public function createTransaction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => 'required|string|max:255',
            'amount'      => 'required|numeric|min:0.01',
            'type'        => 'required|in:income,expense',
            'category'    => 'nullable|string|max:100',
            'date'        => 'nullable|date',
            'scope'       => 'nullable|in:personal,shared',
        ]);

        $transaction = DB::transaction(function () use ($data) {
            $tx = Transaction::create([
                'user_id'     => auth()->id(),
                'description' => $data['description'],
                'amount'      => $data['amount'],
                'type'        => $data['type'],
                'category'    => $data['category'] ?? ($data['type'] === 'income' ? 'Receita' : 'Sem categoria'),
                'date'        => $data['date'] ?? now()->toDateString(),
                'scope'       => $data['scope'] ?? 'personal',
            ]);

            // Receita shared debita a conta pessoal (mesma regra do app)
            app(\App\Services\TransactionMirrorService::class)->createFor($tx);

            return $tx;
        });

        return response()->json(['data' => $transaction, 'message' => "Transação '{$transaction->description}' criada."], 201);
    }

    public function deleteTransaction(int $id): JsonResponse
    {
        $tx = Transaction::findOrFail($id);

        if (! $tx->manageableBy(auth()->user())) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $desc = $tx->description;
        $tx->delete();

        return response()->json(['message' => "Transação '{$desc}' removida."]);
    }

    // ─── WISHLIST ─────────────────────────────────────────────────────────────

    public function listWishlist(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $scope = $this->validatedScope($request);

        $items = WishlistItem::forView($user, $scope)
            ->orderByRaw("FIELD(priority,'high','medium','low')")
            ->get()
            ->map(fn($i) => [
                'id'       => $i->id,
                'name'     => $i->name,
                'price'    => (float) $i->price,
                'url'      => $i->url,
                'priority' => $i->priority,
                'scope'    => $i->scope,
                'status'   => $i->status,
                'notes'    => $i->notes,
            ]);

        return response()->json(['data' => $items, 'total' => $items->sum('price')]);
    }

    public function createWishlistItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'price'    => 'required|numeric|min:0.01',
            'url'      => 'nullable|url',
            'priority' => 'nullable|in:high,medium,low',
            'scope'    => 'nullable|in:personal,shared',
            'notes'    => 'nullable|string|max:500',
        ]);

        $item = WishlistItem::create([
            'user_id'  => auth()->id(),
            'name'     => $data['name'],
            'price'    => $data['price'],
            'url'      => $data['url'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'scope'    => $data['scope'] ?? 'personal',
            'notes'    => $data['notes'] ?? null,
            'status'   => 'pending',
        ]);

        return response()->json(['data' => $item, 'message' => "'{$item->name}' adicionado à lista de desejos."], 201);
    }

    public function deleteWishlistItem(int $id): JsonResponse
    {
        $item = WishlistItem::findOrFail($id);

        if (! $item->manageableBy(auth()->user())) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $name = $item->name;
        $item->delete();

        return response()->json(['message' => "'{$name}' removido da lista de desejos."]);
    }

    // ─── FILTROS DE QUERY ─────────────────────────────────────────────────────

    /** Escopo da query. Valor fora de personal/shared vira 422, não 'shared' silencioso. */
    private function validatedScope(Request $request): string
    {
        $request->validate(['scope' => 'nullable|in:personal,shared']);

        return $request->query('scope', 'personal');
    }

    /** Mês da query no formato Y-m. Valor inválido vira 422, não 500 no Carbon::parse. */
    private function validatedMonth(Request $request): Carbon
    {
        $request->validate(['month' => 'nullable|date_format:Y-m']);

        $month = $request->query('month');

        return $month
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : now()->startOfMonth();
    }
}
