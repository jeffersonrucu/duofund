<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Origem passou a ser apenas PIX, Cartão, Boleto.
        // Crédito e Débito antigos viram "card".
        DB::table('transactions')
            ->whereIn('payment_method', ['credit', 'debit'])
            ->update(['payment_method' => 'card']);
    }

    public function down(): void
    {
        // sem rollback de dados
    }
};
