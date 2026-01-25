<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('scope')->default('personal')->after('user_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('scope')->default('personal')->after('user_id');
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->string('scope')->default('personal')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
