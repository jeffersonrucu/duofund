<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

/**
 * Totais do mês (entradas, saídas, reservas, saldo) para uma visão
 * personal/shared. Fonte única usada pelas páginas Volt e pela API MCP.
 */
class MonthlySummaryService
{
    /** @return array{income: float, expense: float, savings: float, balance: float} */
    public function for(User $user, string $view, Carbon $month): array
    {
        $totals = Transaction::forView($user, $view)
            ->inMonth($month)
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $income = (float) ($totals['income'] ?? 0);
        $expense = (float) ($totals['expense'] ?? 0);
        $savings = (float) ($totals['savings'] ?? 0);

        return [
            'income' => $income,
            'expense' => $expense,
            'savings' => $savings,
            'balance' => $income - $expense,
        ];
    }
}
