<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            // Primeiro dia do mês a partir do qual este limite vale
            $table->date('month');
            $table->decimal('limit', 10, 2);
            $table->timestamps();

            $table->unique(['category_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_limits');
    }
};
