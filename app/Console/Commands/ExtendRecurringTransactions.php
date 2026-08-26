<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\TransactionMirrorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExtendRecurringTransactions extends Command
{
    protected $signature = 'duofund:extend-recurrences';

    protected $description = 'Estende séries recorrentes ativas até o horizonte de '
        . Transaction::RECURRENCE_HORIZON_MONTHS . ' meses';

    /**
     * Uma série "ativa" é a que ainda está preenchida perto do horizonte
     * (o usuário não a encerrou deletando meses futuros). Séries cujo
     * último mês ficou para trás não são tocadas — foram encerradas.
     */
    public function handle(TransactionMirrorService $mirrors): int
    {
        $horizon = now()->startOfMonth()->addMonths(Transaction::RECURRENCE_HORIZON_MONTHS - 1);
        $activityFloor = now()->startOfMonth()->addMonths(Transaction::RECURRENCE_HORIZON_MONTHS - 3);

        $groups = Transaction::query()
            ->where('is_recurring', true)
            ->whereNotNull('recurring_group_id')
            ->where('recurring_group_id', 'not like', '%personal')
            ->groupBy('recurring_group_id')
            ->havingRaw('MAX(date) >= ?', [$activityFloor->toDateString()])
            ->havingRaw('MAX(date) < ?', [$horizon->toDateString()])
            ->pluck('recurring_group_id');

        $created = 0;

        foreach ($groups as $groupId) {
            $last = Transaction::where('recurring_group_id', $groupId)
                ->orderByDesc('date')
                ->first();

            if (! $last) {
                continue;
            }

            DB::transaction(function () use ($last, $horizon, $mirrors, &$created) {
                $date = $last->date->copy()->addMonth();

                while ($date->lessThanOrEqualTo($horizon)) {
                    $tx = Transaction::create([
                        'user_id' => $last->user_id,
                        'description' => $last->description,
                        'amount' => $last->amount,
                        'type' => $last->type,
                        'category' => $last->category,
                        'scope' => $last->scope,
                        'payment_method' => $last->payment_method,
                        'card_id' => $last->card_id,
                        'date' => $date->format('Y-m-d'),
                        'is_recurring' => true,
                        'recurring_group_id' => $last->recurring_group_id,
                    ]);
                    $mirrors->createFor($tx);
                    $created++;
                    $date->addMonth();
                }
            });
        }

        $this->info("Séries estendidas: {$groups->count()} — transações criadas: {$created}.");

        return self::SUCCESS;
    }
}
