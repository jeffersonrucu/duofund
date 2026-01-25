<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('type'); // 'income' ou 'expense'
            $table->decimal('amount', 10, 2);
            $table->string('category'); // Armazenamos o nome (string) para facilitar conforme o original
            $table->date('date');
            
            // Flags para lógica de repetição
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_installment')->default(false);
            $table->integer('installment_current')->nullable();
            $table->integer('installment_count')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};