<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope')->default('personal'); // personal | shared
            $table->string('brand');                       // Visa, Mastercard, Elo...
            $table->string('last4', 4);                    // só os 4 últimos dígitos
            $table->string('label')->nullable();           // apelido opcional
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            // pix | cash | credit | debit | boleto | transfer | null
            $table->string('payment_method')->nullable()->after('category');
            $table->foreignId('card_id')->nullable()->after('payment_method')
                  ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('card_id');
            $table->dropColumn('payment_method');
        });

        Schema::dropIfExists('cards');
    }
};
