<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Liga a despesa espelho ("Transferido para conta conjunta") à
            // receita shared que a originou. Cascade: deletar o original
            // remove o espelho automaticamente.
            $table->foreignId('mirror_transaction_id')->nullable()
                ->constrained('transactions')->cascadeOnDelete();

            $table->index(['user_id', 'scope', 'date']);
            $table->index(['recurring_group_id', 'date']);
        });

        $this->backfillMirrorLinks();
    }

    /**
     * Espelhos legados eram ligados só por convenção de string
     * (recurring_group_id = "{grupo}_personal"). Vincula cada um ao
     * original do mesmo mês.
     */
    private function backfillMirrorLinks(): void
    {
        DB::table('transactions')
            ->where('recurring_group_id', 'like', '%personal')
            ->orderBy('id')
            ->each(function ($mirror) {
                if (! str_ends_with((string) $mirror->recurring_group_id, '_personal')) {
                    return;
                }

                $originalGroup = substr($mirror->recurring_group_id, 0, -strlen('_personal'));

                $originalId = DB::table('transactions')
                    ->where('recurring_group_id', $originalGroup)
                    ->where('user_id', $mirror->user_id)
                    ->whereDate('date', $mirror->date)
                    ->value('id');

                if ($originalId) {
                    DB::table('transactions')
                        ->where('id', $mirror->id)
                        ->update(['mirror_transaction_id' => $originalId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mirror_transaction_id');
            $table->dropIndex(['user_id', 'scope', 'date']);
            $table->dropIndex(['recurring_group_id', 'date']);
        });
    }
};
