<?php

namespace App\Services;

use App\Models\Transaction;

/**
 * Toda receita 'shared' gera uma despesa espelho 'personal'
 * ("Transferido para conta conjunta") na conta de quem lançou.
 *
 * O espelho é ligado ao original por mirror_transaction_id (FK com
 * ON DELETE CASCADE — deletar o original remove o espelho no banco).
 * Séries legadas usavam a convenção recurring_group_id . '_personal';
 * a migration de 2026-07-01 fez o backfill do vínculo.
 */
class TransactionMirrorService
{
    public const SUFFIX = '_personal';
    public const DESCRIPTION = 'Transferido para conta conjunta';
    public const CATEGORY = 'Transferência';

    public function needsMirror(Transaction $tx): bool
    {
        return $tx->scope === 'shared' && $tx->type === 'income';
    }

    public function createFor(Transaction $tx): ?Transaction
    {
        if (! $this->needsMirror($tx)) {
            return null;
        }

        $description = self::DESCRIPTION;
        if ($tx->is_installment && $tx->installment_count) {
            $description .= " ({$tx->installment_current}/{$tx->installment_count})";
        }

        return Transaction::create([
            'user_id' => $tx->user_id,
            'description' => $description,
            'amount' => $tx->amount,
            'type' => 'expense',
            'category' => self::CATEGORY,
            'scope' => 'personal',
            'date' => $tx->date,
            'is_recurring' => (bool) $tx->is_recurring,
            'is_installment' => (bool) $tx->is_installment,
            'installment_current' => $tx->installment_current,
            'installment_count' => $tx->installment_count,
            'recurring_group_id' => $tx->recurring_group_id
                ? $tx->recurring_group_id . self::SUFFIX
                : null,
            'mirror_transaction_id' => $tx->id,
        ]);
    }

    /**
     * Garante que o espelho reflita o estado atual do original:
     * cria se passou a precisar, atualiza valor/data, remove se não precisa mais
     * (ex.: receita shared virou despesa ou virou personal).
     */
    public function reconcile(Transaction $tx): void
    {
        $mirror = Transaction::where('mirror_transaction_id', $tx->id)->first();

        if ($this->needsMirror($tx)) {
            $mirror
                ? $mirror->update(['amount' => $tx->amount, 'date' => $tx->date])
                : $this->createFor($tx);

            return;
        }

        $mirror?->delete();
    }

    /** Reconcilia os espelhos de um conjunto de originais (edição em lote). */
    public function reconcileMany(iterable $ids): void
    {
        Transaction::whereIn('id', collect($ids)->all())
            ->get()
            ->each(fn (Transaction $tx) => $this->reconcile($tx));
    }

    /**
     * Deleta uma série inteira junto com seus espelhos (inclusive legados
     * sem vínculo), limitada aos usuários da família por segurança.
     *
     * Se $fromDate for informado, remove apenas os lançamentos daquela data
     * em diante (os espelhos compartilham a data do original, então o filtro
     * também os alcança).
     */
    public function deleteSeries(string $groupId, array $familyUserIds, ?string $fromDate = null): void
    {
        Transaction::query()
            ->whereIn('user_id', $familyUserIds)
            ->where(function ($q) use ($groupId) {
                $q->where('recurring_group_id', $groupId)
                  ->orWhere('recurring_group_id', $groupId . self::SUFFIX);
            })
            ->when($fromDate, fn ($q) => $q->where('date', '>=', $fromDate))
            ->delete();
    }
}
