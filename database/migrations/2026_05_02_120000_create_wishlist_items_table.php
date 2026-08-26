<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->text('url')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('priority')->default('medium'); // high, medium, low
            $table->string('scope')->default('personal');  // personal, shared
            $table->string('status')->default('pending');  // pending, purchased
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};
