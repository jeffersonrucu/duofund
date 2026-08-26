<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Timestamp desta migration é ANTERIOR à create_wishlist_items (2026_05_02_120000):
        // em banco zerado a tabela ainda não existe aqui — a create já usa text('url').
        if (! Schema::hasTable('wishlist_items')) {
            return;
        }

        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->text('url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->string('url')->nullable()->change();
        });
    }
};
